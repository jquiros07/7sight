# 7Sight

**AI-powered video intelligence for teams.** Upload a video, choose what to
analyze, get structured results and plain-language insights back automatically
— no manual review, no separate tooling per analysis type.

![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)
![TypeScript](https://img.shields.io/badge/TypeScript-strict-3178C6?logo=typescript&logoColor=white)
![Python](https://img.shields.io/badge/Python-3.12-3776AB?logo=python&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)

## Contents

- [Overview](#overview)
- [Vision](#vision)
- [Features](#features)
- [Tech stack](#tech-stack)
- [Configuration](#configuration)
- [Local development](#local-development)
- [Production deployment](#production-deployment)

## Overview

Reviewing video content for what it contains — flagged objects, unsafe
material, specific items of interest — is slow to do by hand and doesn't scale
past a handful of videos. 7Sight lets a team upload a video once, declare what
they care about (content moderation, specific objects, or all detected
objects), and have that analysis run automatically in the background. The
results come back structured, tied to the video and its workspace, and are
summarized in plain language by an AI insights layer built specifically for
what each analysis type produces.

## Vision

The analysis engine is built behind a provider interface on purpose — Amazon
Rekognition is the first implementation, not the only one intended.
`Object Detection`, `Content Moderation`, `Threat Detection`, and
`Text/OCR Detection` are all live today.

On top of the raw detections, an AI insights layer turns structured Rekognition
output into analyst-style summaries — with a distinct prompt and output schema
per analysis type, not one generic paraphrase applied everywhere. Every
generated insight is persisted, and real, data-backed dashboards exist at both
the workspace and account level. A free-text Inquire agent lets a user ask a
specific question about a single video, grounded in that video's own detection
data rather than a fixed set of prompts. The near-term direction: more
analysis providers behind the same interface.

## Features

**Workspaces** — shared containers for videos, with per-workspace roles
(owner / admin / member) enforced by a real permission system (Spatie
Laravel Permission, one global role catalog assigned per workspace via its
teams feature). Day-to-day actions — upload, editing your own content — are
open to any member; cost-driving actions that trigger real AWS/Gemini spend
(AI analysis, AI insights, Inquire) require admin or owner.

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
   - **Threat Detection** — correlates weapon/hazard objects with content-moderation violence labels.
   - **Text/OCR Detection** — reads on-screen text (signage, captions, labels) via Rekognition's text-detection API.
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
             → Python worker picks it up (each replica handles several jobs
               concurrently, not one at a time), retries up to 3 times on
               failure
             → uploads a scratch copy to S3, calls Rekognition Video
               (threat detection's two Rekognition calls run concurrently)
             → results aggregated per label (occurrences, confidence,
               first/last seen) and written to MySQL
             → video status recomputed; once every job for a video
               completes, AI insight generation is queued as a background
               job rather than run inline in the callback request
```

Analysis-worker replicas scale horizontally with zero code changes
(`docker compose up -d --scale analysis-worker=N`) — the consumer group
guarantees safe distribution across replicas, and each replica's own
concurrency (`WORKER_CONCURRENCY`, default 3) multiplies effective throughput
further.

**AI-powered insights** — once a video has completed analysis, generate an
analyst-style summary from the raw detections. Four purpose-built prompts,
not one generic one, matched to what each analysis type actually produces:
- **Object detection** — what's in the video, grouped and time-stamped, with
  notable combinations or changes over time called out.
- **Threat detection** — a LOW / MEDIUM / HIGH / CRITICAL risk assessment that
  correlates detections across *all* of a video's completed analysis types
  (e.g. a person + a weapon), the way a human analyst would.
- **Content moderation** — a SAFE / REVIEW / UNSAFE verdict with severity,
  scoped to Rekognition's own moderation labels.
- **Text detection** — the on-screen text found, grouped and time-stamped,
  with anything notable (warnings, names, addresses) called out.

Insights can be generated for a single analysis type or all of them at once,
and every generated insight is persisted (`video_insights`) for later
reference. Each agent call is retried with backoff on a transient AI-provider
rate limit or overload, and the queue job itself backs off further (20s, then
60s) between retries — long enough to ride out a provider rate-limit window
under concurrent load, since several videos can be analyzed at once. If
generation still fails after exhausting retries, the results page shows the
failure plainly with a one-click retry, rather than leaving the page stuck on
"not ready yet" indefinitely.

**Report export** — download a PDF summary of a video's analysis results and
AI insights (overview, per-label bar charts, threat/moderation assessments,
suggestions) directly from the results page, rendered server-side via headless
Chrome.

**AI Search** — free-text, natural-language search across every analyzed
video in a user's workspaces at once. Not a keyword filter: a single AI call
reasons over each candidate video's already-generated insights and returns
ranked matches (HIGH/MEDIUM/LOW relevance) with a one-sentence reason grounded
in that video's own data.

**Inquire** — ask a specific free-text question about a single video (e.g.
"was a weapon visible near the entrance?") and get a direct, evidence-cited
answer grounded in that video's detection data and generated insights, rather
than a fixed prompt. Suggested starter questions are tailored to whichever
analysis types actually ran on the video. Every answer includes an evidence
table, a chronological investigation timeline reconstructed from the cited
detections, and a "Why?" section explaining the reasoning behind it. The
agent is built to say so plainly when the available analysis doesn't
actually support an answer, instead of guessing. Every question and answer
is saved to that video's history (paginated for long histories) and included
in the video's exported PDF report.

**Workspace dashboard** — real, per-workspace analytics linked directly from
the workspace table: an AI-generated narrative summary of the workspace's
analyzed videos with notable highlights (manually triggered via a
Generate/Refresh button rather than regenerated on every page load, so
viewing the dashboard never costs an AI call), video/storage/analysis stat
cards, a 14-day upload activity chart, a videos-by-status breakdown,
analysis jobs by type, the top detected labels across the workspace, and
threat/moderation flag counts sourced from generated AI insights.

**Account dashboard** — a cross-workspace overview: account-wide totals
(including total Inquire questions asked), a safety spotlight surfacing the
videos most worth a human's attention (ranked by risk/severity across every
workspace), a per-workspace leaderboard, and a recent cross-workspace activity
feed.

**Observability** — Sentry error tracking and performance tracing on both the
PHP app and the Python worker, including Sentry's Queues dashboard for both
job queues in the system: Laravel's own queue (insight generation) and the
Redis Stream that hands analysis jobs to the Python worker. A single
"Analyze" request is traced end to end as one distributed trace across the
PHP → Redis → Python boundary (`queue.publish` / `queue.process` spans with
trace-context propagation), so a slow or failed job can be followed straight
from the request that queued it into the worker's own processing. Structured
job lifecycle logging (queued, attempt N/3, succeeded, failed) from the
worker; a durable audit trail in `analysis_jobs`/`analysis_results` (status,
attempts, error messages) queryable without a separate dashboard.

## Tech stack

**Backend**
- [Laravel 13](https://laravel.com) (PHP 8.3+) — Action-per-operation pattern, thin controllers
- [Laravel Fortify](https://laravel.com/docs/fortify) — authentication (registration, email verification, password reset)
- [Laravel Sanctum](https://laravel.com/docs/sanctum) — API auth for the SPA
- [laravel/ai](https://github.com/laravel/ai) + Google Gemini — six structured-output agents: one per analysis type, plus cross-video Search and per-video Inquire
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) — per-workspace roles/permissions (teams feature), enforced across every Action
- MySQL 8.4
- Redis 7 — two independent uses: the Redis Stream (+ consumer group) that hands analysis jobs to the Python worker, and Laravel's own queue (`queue:work`, run via Supervisor) for insight generation
- [Spatie Laravel PDF](https://github.com/spatie/laravel-pdf) + Browsershot (headless Chrome) — video report export
- [getID3](https://github.com/JamesHeinrich/getID3) — video metadata inspection on upload
- [Sentry](https://sentry.io) — error tracking + performance tracing (Queues dashboard, distributed traces)

**Frontend**
- React 19 + TypeScript, React Router 7 (SPA, no Inertia)
- Tailwind CSS 4
- [Preline UI](https://preline.co) — themed widgets (select, stepper, tooltip, overlay, dropdown) driven via its JS plugin API
- ApexCharts (dashboards, video results), Axios, Vite

**Analysis worker** (`analysis-worker/`)
- Python 3.12, plain functions over a framework — no ORM, no DI container
- `redis-py` — consumes the job queue; each replica processes several jobs concurrently via a thread pool (`WORKER_CONCURRENCY`, default 3), since a job spends nearly all its time waiting on Rekognition, not on CPU
- `boto3` — Amazon Rekognition Video (async label detection, content moderation, and text detection) and S3
- `PyMySQL` — writes job/result status directly to the same MySQL database
- `sentry-sdk` — error tracking + performance tracing, mirroring the PHP side; continues the distributed trace the PHP app started, across the Redis Stream queue boundary

**Infrastructure**
- Docker Compose: `app`, `mysql`, `redis`, `analysis-worker` (horizontally scaled), `vite` (dev only)
- Multi-stage Dockerfile with separate `dev` and `production` targets
- Supervisor inside the `app` container runs nginx, PHP-FPM, and a Laravel queue worker side by side
- Caddy — reverse proxy and automatic HTTPS in production
- See [`DEPLOYMENT.md`](DEPLOYMENT.md) for taking this to a live server

## Configuration

Copy `.env.example` to `.env` and fill in:

| Variable | Purpose |
|---|---|
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` | Rekognition Video + S3 scratch space, used by the analysis worker |
| `GEMINI_API_KEY` | Google Gemini — powers the AI insights layer (`app/Ai/Agents/*`) |
| `ANALYSIS_PROVIDER` | Which provider implements the analysis-worker's provider interface (`rekognition` today) |
| `SENTRY_DSN` | Optional error tracking + tracing, shared by the PHP app (as a fallback) and the Python worker |
| `SENTRY_LARAVEL_DSN` | Optional override if the PHP app should report to a different Sentry project than the worker — leave unset (not blank) to fall back to `SENTRY_DSN` |
| `SENTRY_TRACES_SAMPLE_RATE` | Performance tracing sample rate (0.0–1.0), shared by both. Unset disables tracing entirely |

Everything else (`DB_*`, `REDIS_*`, `MYSQL_*`) has working local defaults for
Docker Compose out of the box.

## Local development

```bash
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

App: `http://localhost:8000` · Vite dev server: `http://localhost:5173`

Run the test suite:

```bash
docker compose exec app php artisan test
```

## Production deployment

See [`DEPLOYMENT.md`](DEPLOYMENT.md) for server setup, first deploy, and
performance tuning (analysis-worker scaling, database/PHP-FPM tuning, Redis
sizing, monitoring).
