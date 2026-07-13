# Guised Up — Real Connections Feed

## Overview

Guised Up is a focused social-feed assessment that prioritizes authentic expression, meaningful relationship depth, semantic relevance, and recency. Total engagement popularity is intentionally not a ranking signal.

The repository combines a Laravel REST API, PostgreSQL, a Python FastAPI embedding service, persistent Chroma vector storage, and one Expo React Native Feed Screen.

## Assignment Deliverables

- [Technical Solution Document](docs/TSD.md)
- Laravel API with Sanctum authentication and deterministic tests
- Python embedding service with transformer and explicit hash providers
- React Native Feed Screen with pagination, semantic search, and reactions
- [Four PostgreSQL challenge queries](sql/queries.sql)
- Automated Laravel, Python, and mobile tests

## Architecture Summary

The mobile application calls Laravel only. Laravel owns authentication, API contracts, ranking, and relational data in PostgreSQL. Laravel calls FastAPI for embedding and vector operations; FastAPI owns the local Chroma client. PostgreSQL remains authoritative, including when Chroma contains a stale document ID.

See the [TSD](docs/TSD.md) for the architecture diagram, data flows, schema, and design decisions.

## Monorepo Structure

```text
apps/
  api/                       Laravel 13 API, migrations, seeders, and PHPUnit tests
  mobile/                    Expo SDK 57 single-screen React Native application
docs/
  TSD.md                     Technical Solution Document
services/
  embeddings/               FastAPI, embedding providers, Chroma, and pytest tests
sql/
  queries.sql                PostgreSQL challenge answers D1-D4
```

## Prerequisites

The final validation used these versions:

- PHP 8.4.23
- Composer 2.10.1
- PostgreSQL 18.4 and `psql`
- Node.js 24.18.0 and npm 11.18.0
- Python 3.14.4
- Expo SDK 57 with an iOS simulator, Android emulator, physical device, or Expo Go

## Quick Local Setup

Run the following from a fresh clone. Commands that start a server remain in the foreground; leave that terminal open and use a new terminal for the next service.

### A. Prepare PostgreSQL

```bash
createdb guised_up
```

The local PostgreSQL role and password depend on the developer's PostgreSQL installation. If `createdb` cannot use the current role, create `guised_up` with a suitable local administrator and use that role in the Laravel environment file.

### B. Install and prepare Laravel

```bash
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

Before running the Artisan commands, set `DB_HOST`, `DB_PORT`, `DB_DATABASE=guised_up`, `DB_USERNAME`, and `DB_PASSWORD` in `apps/api/.env` for the local PostgreSQL installation.

### C. Start the Python embedding service

From the repository root:

```bash
cd services/embeddings
python3.14 -m venv .venv
source .venv/bin/activate
python -m pip install -r requirements.txt
cp .env.example .env
uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Leave Uvicorn running. The first transformer-backed operation may download and load `sentence-transformers/all-MiniLM-L6-v2`.

### D. Index the seeded posts

In a new terminal from the repository root:

```bash
cd apps/api
php artisan app:index-posts
```

FastAPI must be running before indexing.

### E. Generate a local Sanctum token

From `apps/api`:

```bash
php artisan app:issue-demo-token vipul@example.com
```

The plaintext token is displayed only once. Store it only in the untracked mobile `.env` file and never commit it.

### F. Start Laravel

From `apps/api`:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Leave the Laravel server running.

### G. Start the React Native application

In a new terminal from the repository root:

```bash
cd apps/mobile
npm install
cp .env.example .env
npm start
```

Set `EXPO_PUBLIC_API_TOKEN` in `apps/mobile/.env` to the locally issued token. Set `EXPO_PUBLIC_API_BASE_URL` to the URL reachable from the selected simulator or device.

## Required Service Startup Order

1. PostgreSQL
2. FastAPI
3. Laravel
4. Expo mobile application

Post indexing is performed after FastAPI starts and before exercising semantic feed or search behavior.

## Simulator and Device Networking

- iOS simulator: `http://127.0.0.1:8000/api`
- Android emulator: `http://10.0.2.2:8000/api`
- Physical device: use the development machine's LAN IP, for example `http://192.168.x.x:8000/api`

Do not copy the example LAN pattern literally. Laravel must listen on an interface reachable by the device when physical-device testing is required.

## Seeded Development Credentials

These accounts are for local development only and use the password `password`:

- `vipul@example.com`
- `maya@example.com`
- `arjun@example.com`

## API Endpoints

| Method | Endpoint | Purpose | Authentication |
|---|---|---|---|
| `GET` | `/api/user` | Return the authenticated user | Sanctum bearer token |
| `POST` | `/api/posts` | Create and synchronously index a post | Sanctum bearer token |
| `GET` | `/api/feed?page=1` | Return the personalized paginated feed | Sanctum bearer token |
| `GET` | `/api/search?q=...` | Search indexed posts by semantic meaning | Sanctum bearer token |
| `POST` | `/api/interactions` | Record a view, reaction, or reply event | Sanctum bearer token |

## Feed-Ranking Formula

```text
score = 0.25 authenticity
      + 0.30 relationship depth
      + 0.30 semantic similarity
      + 0.15 time decay
```

Relationship depth is normalized from the current user's weighted interactions with each author. Semantic recommendations use that user's interacted post vectors. Time decay is `exp(-age_hours / 72)`. Platform-wide engagement popularity is not used. See the [TSD ranking section](docs/TSD.md#11-feed-ranking-algorithm) for details.

## Failure Behavior

- A post remains created in PostgreSQL if semantic indexing fails and can be retried with `php artisan app:index-posts`.
- Semantic search returns HTTP `503` when FastAPI is unavailable; no lexical result is misrepresented as semantic search.
- The feed remains available with semantic similarity set to zero when semantic ranking is unavailable.
- Laravel ignores stale Chroma IDs that do not resolve to PostgreSQL posts.

## Testing

Laravel, from `apps/api`:

```bash
composer validate
php artisan test
vendor/bin/pint --test
```

Python, from `services/embeddings` with `.venv` active:

```bash
pytest -q
```

Mobile, from `apps/mobile`:

```bash
npm test
npm run typecheck
```

SQL, from the repository root:

```bash
psql --dbname=guised_up --file=sql/queries.sql
```

Pass the appropriate `psql` role, host, and port options when the local defaults differ. D2 contains a clearly marked one-row parameter CTE; replace its sample user ID before evaluating another user.

### Current Validation

The final Phase 8 validation on 2026-07-13 passed 22 Laravel tests with 126 assertions, 7 Python tests, and 9 mobile Jest tests, plus Composer, Pint, TypeScript, Expo public-config, Android/iOS export, migration, route, transformer initialization, SQL, and authenticated end-to-end checks. Against the deterministic seed data, D1-D4 returned 3, 9, 0, and 0 rows respectively. The final restored state contains 3 users, 15 posts, 18 interactions, 15 ready embeddings, and no Sanctum tokens.

## Documentation

- [Technical Solution Document](docs/TSD.md)
- [Video walkthrough recording guide](docs/VIDEO_WALKTHROUGH.md)
- [PostgreSQL challenge queries](sql/queries.sql)
- [Mobile setup and behavior](apps/mobile/README.md)
- [Embedding-service setup and API](services/embeddings/README.md)

## Known Limitations and Trade-offs

- Embeddings are generated synchronously for take-home simplicity.
- The Expo token configuration is only for local demonstration because `EXPO_PUBLIC_*` values are bundled.
- The explicit hash provider is a deterministic lexical fallback, not transformer semantics.
- Authenticity uses post text only; no visual image-authenticity detection exists.
- PostgreSQL and Chroma do not share a transaction, so deterministic IDs and re-indexing provide reconciliation rather than atomicity.

## AI-Assisted Workflow

ChatGPT was used to analyze the assignment and divide it into focused phases. OpenAI Codex Goal Mode was used to inspect, implement, test, and document each phase, with human review after every phase. The detailed running log is in the [TSD](docs/TSD.md#18-ai-assisted-workflow).

## Submission Note

This repository is intentionally public to provide the recruiter with direct review access. The original confidential assignment document is not included. No secrets, access tokens, private credentials, or local environment files are included. The implementation and documentation are provided only for assessment and portfolio review purposes. The explanation video is prepared separately; the repository contains only its recording guide and does not claim that recording or upload is complete.
