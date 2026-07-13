# Guised Up Laravel API

This Laravel 13 REST API is the application's main backend. It owns Sanctum authentication, PostgreSQL persistence, API contracts, authenticity scoring, relationship aggregation, feed ranking, and coordination with the internal FastAPI embedding service.

## Setup

Create the `guised_up` PostgreSQL database, then run these commands from the repository root:

```bash
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
```

Configure the local PostgreSQL connection and confirm `EMBEDDINGS_SERVICE_URL=http://127.0.0.1:8001` in `apps/api/.env`. Then run:

```bash
php artisan migrate:fresh --seed
php artisan app:index-posts
php artisan app:issue-demo-token vipul@example.com
php artisan serve --host=127.0.0.1 --port=8000
```

`migrate:fresh` is destructive to the configured database and should be used only with the dedicated local database. FastAPI must be running before post indexing. The seed creates 3 users, 15 posts, and 18 interactions. The token command displays a local plaintext token once and replaces the prior `assessment-mobile` token for that user.

## Authenticated Routes

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/user` | Return the authenticated user |
| `POST` | `/api/posts` | Create a post and attempt synchronous indexing |
| `GET` | `/api/feed?page=1` | Return a 20-item personalized feed page |
| `GET` | `/api/search?q=...` | Return up to 10 semantic-search results |
| `POST` | `/api/interactions` | Record a `view`, `reaction`, or `reply` |

All routes require `Authorization: Bearer <local-token>` through Sanctum.

## Feed Ranking

```text
score = 0.25 × authenticity
      + 0.30 × relationship depth
      + 0.30 × semantic similarity
      + 0.15 × time decay
```

Relationship weights are `view = 1`, `reaction = 3`, and `reply = 5`. Time decay is `exp(-age_in_hours / 72)`. Platform-wide popularity is not used.

## FastAPI Dependency and Degradation

FastAPI supplies vector indexing, semantic search, and recommendation similarity. PostgreSQL remains authoritative:

- Post creation remains successful when indexing fails and records `embedding_status=failed`.
- `php artisan app:index-posts` retries pending and failed posts; `--force` re-indexes all posts.
- Search returns HTTP `503` when semantic infrastructure is unavailable.
- Feed ranking remains available with semantic similarity set to zero.
- Stale Chroma IDs are ignored when no corresponding PostgreSQL post exists.

## Validation

From `apps/api`:

```bash
composer validate
php artisan test
vendor/bin/pint --test
php artisan route:list --path=api -v
```

The last local run passed 22 tests with 126 assertions. Rerun the commands after cloning.

For complete fresh-clone instructions, API examples, and architecture, see the [root README](../../README.md) and [Technical Solution Document](../../docs/TSD.md).
