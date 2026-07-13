# Guised Up Assessment

## Overview

This private take-home repository is a minimal monorepo for a social feed assessment. It contains a Laravel API, one Expo React Native Feed Screen, and an internal FastAPI vector service. Later assignment phases remain intentionally deferred.

## Monorepo Structure

```text
apps/
  api/                 # Laravel 13 REST API scaffold with Sanctum
  mobile/              # Expo SDK 57 single-screen React Native client
services/
  embeddings/          # FastAPI embeddings and persistent Chroma vector search
docs/
  TSD.md                # Technical solution document
sql/
  .gitkeep              # SQL challenge directory; queries are not implemented yet
```

## Prerequisites

- PHP 8.3 or later and Composer
- PostgreSQL and its command-line client
- Node.js LTS and npm
- Python 3.14.4 for the tested embedding-service dependency set
- Expo-compatible iOS or Android simulator, device, or Expo Go

## Current Implementation Status

Phase 6 completes the required React Native Feed Screen on top of the Phase 5 API:

- Laravel uses the local PostgreSQL database `guised_up` with migrations for users, posts, interaction events, and Sanctum tokens.
- Eloquent relationships, reusable factories, and deterministic demo seed data are implemented.
- A local-only Artisan command issues replacement Sanctum tokens for demo users.
- All four required Sanctum-protected endpoints are implemented: post creation, personalized feed, semantic search, and interaction logging.
- Post authenticity uses a deterministic, explainable text heuristic and makes no visual-analysis or truthfulness claims.
- Feed ranking combines authenticity, the current user's relationship depth, semantic similarity, and time decay.
- Laravel synchronously indexes created posts and provides `app:index-posts` to retry pending or failed records.
- FastAPI provides idempotent post-vector upserts, natural-language search, and seed-document recommendations through one persistent cosine-distance Chroma collection.
- The normal local embedding provider is `sentence-transformers/all-MiniLM-L6-v2` on CPU.
- An explicit deterministic hash provider supports tests and environments that cannot run the model; it provides lexical similarity, not true semantic understanding, and is never selected silently.
- The Python service tests are isolated from normal persistent data and never download the transformer model.
- The Expo app implements only the required Feed Screen; it adds no navigation, authentication UI, or secondary screens.
- The screen loads the authenticated personalized feed, paginates with `meta.has_more_pages`, refreshes page one, performs debounced natural-language search, and records session-only reactions.
- Mobile API responses are checked against the Laravel feed, search, interaction, validation, and authentication contracts without exposing ranking or embedding details in the UI.
- Loading, empty, authentication, configuration, image, search, reaction, refresh, and pagination failures have intentional inline states.
- SQL challenge queries and final repository polish remain intentionally pending.

## Local Setup Summary

### Laravel API

```bash
cd apps/api
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate:fresh --seed
```

Configure `.env` to use the local PostgreSQL database `guised_up` before running migrations. The intended repeatable reset is:

```bash
php artisan migrate:fresh --seed
```

After FastAPI is running, index the deterministic posts and then start Laravel:

```bash
php artisan app:index-posts
php artisan serve
```

The local-only demo accounts all use the password `password`:

- `vipul@example.com`
- `maya@example.com`
- `arjun@example.com`

Issue a replacement Sanctum token for the default demo user with:

```bash
php artisan app:issue-demo-token
```

Pass another demo email as the optional argument when needed. The command displays the plaintext token once for local assessment use; never commit plaintext tokens. Laravel Herd may be used instead of `php artisan serve`.

### Embedding Service

```bash
cd services/embeddings
python3.14 -m venv .venv
.venv/bin/python -m pip install -r requirements.txt
cp .env.example .env
.venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Start PostgreSQL first, FastAPI second, index posts, and start Laravel last. The health endpoint is `http://127.0.0.1:8001/health`. Chroma persists under
`services/embeddings/storage/chroma/`, which is ignored by Git.

Run the deterministic service tests:

```bash
cd services/embeddings
.venv/bin/python -m pytest -q
```

To opt into the non-semantic lexical fallback for local operation, set
`EMBEDDING_PROVIDER=hash`. The service does not silently fall back when the
configured sentence-transformer model fails.

### Authenticated API

The public Laravel endpoints are:

- `POST /api/posts`
- `GET /api/feed?page=1`
- `GET /api/search?q=journey%20abroad`
- `POST /api/interactions`

Use the demo-token command above, keep the plaintext value local, and substitute it only at request time:

```bash
curl -H 'Authorization: Bearer <LOCAL_SANCTUM_TOKEN>' \
  -H 'Accept: application/json' \
  http://127.0.0.1:8000/api/feed
```

Semantic search returns HTTP `503` when FastAPI is unavailable. Feed ranking remains available with semantic similarity set to zero and `semantic_ranking_available: false`. Never commit a plaintext Sanctum token.

### Mobile App

See [apps/mobile/README.md](apps/mobile/README.md) for complete backend preparation, token, and networking instructions.

```bash
cd apps/mobile
cp .env.example .env
npm install
npm run typecheck
npm start
```

Mobile configuration requires `EXPO_PUBLIC_API_BASE_URL` and `EXPO_PUBLIC_API_TOKEN`. Use `http://127.0.0.1:8000/api` for the iOS simulator, `http://10.0.2.2:8000/api` for the Android emulator, or the development machine's LAN IP for a physical device. `EXPO_PUBLIC_*` values are bundled and the token approach is only for local assessment demonstration.

## Environment Files

- Laravel: `apps/api/.env.example`
- Mobile: `apps/mobile/.env.example`
- Embedding service: `services/embeddings/.env.example`

Local `.env` files are ignored and must not contain committed credentials.

## Documentation

See the [Technical Solution Document](docs/TSD.md) for the planned architecture, data model, API contracts, and implementation sequence.

## Confidentiality

This repository must remain private because the assessment and its supporting materials are confidential. Do not copy the assignment PDF into the repository.
