# 7Sight

A video intelligence platform: teams upload videos into shared workspaces,
choose what to analyze, and get the results back automatically — no manual
review, no separate tooling per analysis type.

## Purpose

Reviewing video content for what it contains — flagged objects, unsafe
material, specific items of interest — is slow to do by hand and doesn't scale
past a handful of videos. 7Sight lets a team upload a video once, declare what
they care about (content moderation, specific objects, or all detected
objects), and have that analysis run automatically in the background, with
results tied back to the video and its workspace.

## Vision

The analysis engine is built behind a provider interface on purpose — Amazon
Rekognition is the first implementation, not the only one intended.
`Object Detection` and `Content Moderation` are live today; `AI Generated`
(deepfake/synthetic video detection) already exists as a selectable analysis
type in the UI, deliberately disabled until that capability is actually built.
The near-term direction is: more analysis providers behind the same interface,
and a proper results view (the pipeline already produces structured, queryable
results — surfacing them in the UI is the next step, not yet built).

## Tech stack

**Backend**
- [Laravel 13](https://laravel.com) (PHP 8.3+) — Action-per-operation pattern, thin controllers
- [Laravel Fortify](https://laravel.com/docs/fortify) — authentication (registration, email verification, password reset)
- [Laravel Sanctum](https://laravel.com/docs/sanctum) — API auth for the SPA
- MySQL 8.4
- Redis 7 — cache and the analysis job queue (Redis Streams + consumer groups)
- [getID3](https://github.com/JamesHeinrich/getID3) — video metadata inspection on upload
- [Sentry](https://sentry.io) — error tracking

**Frontend**
- React 19 + TypeScript, React Router 7 (SPA, no Inertia)
- Tailwind CSS 4
- [Preline UI](https://preline.co) — themed widgets (select, stepper, tooltip, overlay, dropdown) driven via its JS plugin API
- ApexCharts (dashboard), Axios, Vite

**Analysis worker** (`analysis-worker/`)
- Python 3.12, plain functions over a framework — no ORM, no DI container
- `redis-py` — consumes the job queue
- `boto3` — Amazon Rekognition Video (async label detection + content moderation) and S3
- `PyMySQL` — writes job/result status directly to the same MySQL database
- `sentry-sdk` — error tracking, mirroring the PHP side

**Infrastructure**
- Docker Compose: `app`, `mysql`, `redis`, `analysis-worker` (horizontally scaled), `vite` (dev only)
- Multi-stage Dockerfile with separate `dev` and `production` targets
- Caddy — reverse proxy and automatic HTTPS in production
- See [`DEPLOYMENT.md`](DEPLOYMENT.md) for taking this to a live server

## Features

**Workspaces** — shared containers for videos, with role-based membership
(owner / admin / member) gating who can upload, edit, or delete.

**Video upload** — a two-step wizard:
1. File (drag-and-drop or picker), title, description, workspace. Uploads are
   validated on duration (≤15 min) and resolution (≤1080p) via metadata
   inspection, not just file size.
2. Analysis configuration — pick one or more analysis types:
   - **Content Moderation** — no further configuration.
   - **Object Detection** — detect all objects, or scope it to a curated
     catalog of ~94 objects across 9 categories (people, vehicles, animals,
     household items, electronics, personal items, food, tools,
     security-relevant items).
   - **AI Generated** — visible, currently disabled (not yet implemented).
   - Optional auto-start: begin analysis immediately after upload finishes.

**Video management** — sortable/paginated list with live status
(`uploaded → processing → ready`/`failed`), edit (same wizard, editable
analysis settings, partial updates supported), delete with confirmation,
manual re-analysis (including retrying a failed video).

**Analysis pipeline** — end to end, this is what happens after upload:

```
Analyze click → AnalysisJob rows created (one per analysis type)
             → queued on a Redis Stream (consumer group: no duplicate
               processing, no lost jobs on a worker crash)
             → Python worker picks it up, retries up to 3 times on failure
             → uploads a scratch copy to S3, calls Rekognition Video
             → results aggregated per label (occurrences, confidence,
               first/last seen) and written to MySQL
             → video status recomputed and reflected in the UI via polling
```

Analysis-worker replicas scale horizontally with zero code changes
(`docker compose up -d --scale analysis-worker=N`) — the consumer group
guarantees safe distribution across replicas.

**Dashboard** — workspace and video counts at a glance.

**Observability** — Sentry on both the PHP app and the Python worker;
structured job lifecycle logging (queued, attempt N/3, succeeded, failed) from
the worker; a durable audit trail in `analysis_jobs`/`analysis_results`
(status, attempts, error messages) queryable without a separate dashboard.

## Local development

```bash
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

App: `http://localhost:8000` · Vite dev server: `http://localhost:5173`

## Production deployment

See [`DEPLOYMENT.md`](DEPLOYMENT.md) for server setup, first deploy, and
performance tuning (analysis-worker scaling, database/PHP-FPM tuning, Redis
sizing, monitoring).
