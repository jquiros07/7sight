# Deploying 7Sight to a live server

This covers taking the stack (`app`, `mysql`, `redis`, `analysis-worker` ×5) from
local Docker Compose to a real VPS — DigitalOcean Droplet or Hostinger VPS both
work the same way here, since both are just a plain Ubuntu server once you have
SSH access. Differences between the two are called out where they matter.

## 1. Prerequisites

- **A VPS.** Minimum: 4 vCPU / 8 GB RAM. This runs MySQL, Redis, the app, Caddy,
  and 5 analysis-worker replicas on one box — the workers are mostly idle/waiting
  on network I/O (polling Rekognition), not CPU-bound, but MySQL and PHP-FPM want
  real memory. If budget is tight, drop `analysis-worker` replicas to 2-3 first
  (see §6) before shrinking the box.
- **A domain name** with an A record pointed at the server's IP. Caddy (below)
  needs this to issue a TLS certificate automatically.
- **An AWS account** with:
  - An S3 bucket (any name/region — this is only used as scratch space for
    Rekognition, videos are deleted right after each analysis job).
  - An IAM user with a policy scoped to just what the worker needs — don't use
    root/admin credentials:
    ```json
    {
      "Version": "2012-10-17",
      "Statement": [
        { "Effect": "Allow", "Action": ["rekognition:StartLabelDetection", "rekognition:GetLabelDetection", "rekognition:StartContentModeration", "rekognition:GetContentModeration"], "Resource": "*" },
        { "Effect": "Allow", "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"], "Resource": "arn:aws:s3:::YOUR_BUCKET/analysis-tmp/*" }
      ]
    }
    ```
  - `AWS_DEFAULT_REGION` must match the bucket's region — Rekognition Video reads
    the S3 object in-region.

## 2. Server setup

SSH in as root (or your provider's default user), then:

```bash
# Non-root user
adduser deploy && usermod -aG sudo deploy
# Firewall — only SSH, HTTP, HTTPS from the outside
ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw enable

# Docker Engine + Compose plugin (Ubuntu)
curl -fsSL https://get.docker.com | sh
usermod -aG docker deploy
```

Log back in as `deploy` for the rest of this.

## 3. Get the code and configure it

```bash
git clone <your-repo-url> 7sight && cd 7sight
cp .env.example .env
```

Edit `.env`:
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain.com`
- Generate a real key later with `artisan key:generate` (step 5) — don't hand-write one.
- `MYSQL_PASSWORD` / `MYSQL_ROOT_PASSWORD` — replace the dev defaults with strong, unique secrets.
- `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` from step 1.
- `SENTRY_LARAVEL_DSN` / `SENTRY_DSN` — optional but recommended (separate Sentry projects for the PHP app and the Python worker).
- **Remove or comment out `COMPOSE_PROFILES=dev`.** This is what keeps the Vite dev server from starting in production — the `production` Docker build target (below) already ships pre-built assets.

```bash
cp Caddyfile.example Caddyfile
# edit Caddyfile, replace your-domain.com with your real domain
```

## 4. First deploy

`docker-compose.prod.yml` overrides the dev-oriented base file: builds the `app`
image from the `production` target (composer install `--no-dev`, pre-built
frontend assets baked in, no live bind-mount of the repo), stores uploaded
videos in a named Docker volume instead of a path tied to wherever the repo
happens to be checked out, drops MySQL's host-exposed port, and adds Caddy as
the public HTTPS entrypoint.

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# One-time setup inside the running app container
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
```

Every subsequent deploy (after a `git pull`) is just:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
docker compose exec app php artisan migrate --force
```

### Verify

- `https://your-domain.com` loads over HTTPS with a valid certificate (Caddy
  issues it automatically on first request — give it a few seconds).
- `docker compose ps` — everything `Up`, `mysql` shows `(healthy)`.
- `docker compose logs analysis-worker --tail 20` — 5 replicas, each logging
  `analysis worker started (provider=rekognition, consumer=<unique-id>)`.
- Upload a short test video through the UI, click Analyze, confirm it reaches
  `ready` and `analysis_jobs`/`analysis_results` populate.

## 5. Ongoing operations

- **Logs**: `docker compose logs -f <service>`. Rotation is already configured
  (10 MB × 3 files per container) so this can't fill the disk unbounded.
- **Backups**: nothing does this for you yet.
  - MySQL: `set -a; source .env; set +a; docker compose exec -T mysql mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" video_intelligence_platform | gzip > backup-$(date +%F).sql.gz`, on a cron, shipped off-host (S3, or your provider's snapshot feature).
  - Video files: back up the `videos-data` Docker volume the same way (`docker run --rm -v 7sight_videos-data:/data -v $(pwd):/backup alpine tar czf /backup/videos-$(date +%F).tar.gz /data`), or use DigitalOcean's Droplet snapshot feature for the whole disk.
- **Provider-specific upgrade paths**: DigitalOcean offers Managed MySQL, Managed
  Redis, and Spaces (S3-compatible) if you later want to move those off the
  single box. Hostinger VPS is closer to bare-metal — expect to self-manage
  everything above rather than lifting pieces onto a managed service.

---

## 6. Making it performant

Everything below assumes the deploy above is working. None of it requires code
changes — it's server/config tuning.

### Analysis throughput (the actual bottleneck)

One `analysis-worker` replica processes one job at a time, and a real
Rekognition Video job (start → poll → fetch results) commonly takes a couple of
minutes. Five replicas (already the default in `docker-compose.yml`) gets you
roughly 100-150 jobs/hour instead of ~20-30 with one, for free — the Redis
Streams consumer group handles distribution and guarantees no job is ever
double-processed, so scaling this is just changing a number:

```yaml
# docker-compose.yml
analysis-worker:
  deploy:
    replicas: 10   # or whatever
```

Before pushing this much past 5-10, check **AWS Rekognition's concurrent-job
quota** for your account/region (Service Quotas console → Rekognition →
`StartLabelDetection`/`StartContentModeration` concurrent jobs). Past that
quota, calls start failing with `LimitExceededException` regardless of how many
workers you run — request an increase there first (usually free, approved in
hours to a couple of days).

Watch for backlog: `docker compose exec redis redis-cli XLEN analysis_jobs`
(pending, unclaimed messages) and `XPENDING analysis_jobs analysis_workers`
(claimed but not yet acknowledged). A consistently growing `XLEN` means you
need more replicas or you're past your Rekognition quota.

### Database

The stock `mysql:8.4` image ships conservative defaults not tuned to your
server's actual RAM. The highest-leverage change is `innodb_buffer_pool_size`
— target roughly 50-70% of the RAM you're willing to dedicate to MySQL (not the
whole server, since PHP-FPM, Redis, and 5+ Python processes also need memory).
For an 8 GB server, ~3-4 GB is reasonable:

```yaml
# docker-compose.prod.yml, mysql service
command: --innodb-buffer-pool-size=3G --max-connections=200
```

### Web tier

- **OPcache**: `docker/opcache.ini` is baked into the `production` build target
  only (not `dev`, so local live-editing still works) — `validate_timestamps=0`
  skips re-checking every PHP file's mtime on each request, since production
  code is baked into the image, never live-edited.
- **PHP-FPM workers**: `docker/fpm-pool.conf` (also `production`-only) sets
  `pm.max_children = 20` as a starting point for ~1-1.5 GB dedicated to PHP.
  Adjust it to `(RAM you allocate to PHP) / (~30-50 MB per worker)` for your
  actual server — too high and concurrent requests can exhaust memory, too low
  and requests queue behind each other under load.

### Redis

Already configured with `--maxmemory-policy noeviction --appendonly yes` (queue
data won't be silently evicted or lost on restart). Add an explicit cap sized
to your server so Redis can't consume unbounded host memory:

```yaml
# docker-compose.prod.yml, redis service
command: redis-server --maxmemory-policy noeviction --appendonly yes --maxmemory 512mb
```

With `noeviction`, once that cap is hit Redis *rejects new writes* rather than
dropping old queue data — the correct failure mode for a job queue, but it
means a full Redis will visibly block new "Analyze" clicks. Size the cap with
real headroom above your expected queue depth, and keep an eye on
`redis-cli INFO memory` if analysis volume grows a lot.

### Resource limits

Nothing currently stops one container from starving the others on the same
host. Worth adding once you know the server's real capacity:

```yaml
# docker-compose.prod.yml, per service
deploy:
  resources:
    limits:
      memory: 512M
```

### Monitoring

- Sentry is already wired for both the PHP app (`SENTRY_LARAVEL_DSN`) and the
  Python worker (`SENTRY_DSN`) — make sure both are actually set in production.
- At minimum, add uptime monitoring on `https://your-domain.com` (any of the
  free-tier services — UptimeRobot, Better Uptime, etc.) so you find out about
  an outage before a user reports it.
- Periodically check `docker stats` and the queue-depth commands above rather
  than waiting for something to visibly break.
