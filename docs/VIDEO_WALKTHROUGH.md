# Video Walkthrough Recording Guide

This is a preparation guide for a concise 6-to-8-minute explanation video. It does not indicate that the video has been recorded or uploaded.

## Before Recording

Open four terminals from the repository root and prepare the deterministic demo state.

Terminal 1 — PostgreSQL and Laravel preparation:

```bash
cd apps/api
php artisan migrate:fresh --seed
```

Terminal 2 — embedding service:

```bash
cd services/embeddings
source .venv/bin/activate
uvicorn app.main:app --host 127.0.0.1 --port 8001
```

After FastAPI is ready, return to Terminal 1:

```bash
php artisan app:index-posts --force
php artisan app:issue-demo-token vipul@example.com
php artisan serve --host=127.0.0.1 --port=8000
```

Copy the one-time token only into the untracked `apps/mobile/.env`. Never show or read it aloud during the recording.

Terminal 3 — mobile application:

```bash
cd apps/mobile
cp .env.example .env
# Set the reachable API base URL and the freshly issued token in .env.
npm start
```

Terminal 4 — validation commands ready to show:

```bash
cd apps/api && php artisan test
cd ../../services/embeddings && .venv/bin/python -m pytest -q
cd ../../apps/mobile && npm test
```

Recommended tabs and windows:

1. `docs/TSD.md` open at the architecture diagram and ranking section.
2. `apps/api/routes/api.php`, `FeedRanker.php`, and `PostController.php` open in the editor.
3. `sql/queries.sql` open at D1.
4. README setup instructions open in a browser or Markdown preview.
5. A terminal with the three passing test summaries visible and secrets cleared from scrollback.
6. The simulator or device on the loaded feed with the search field empty and network access confirmed.

Use `vipul@example.com` as the demo user. For iOS Simulator use `http://127.0.0.1:8000/api`; for Android Emulator use `http://10.0.2.2:8000/api`; for a physical device use a reachable LAN URL and start Laravel on a reachable interface.

Before sharing the screen, verify that no Sanctum token, database password, local `.env`, absolute home path, or confidential assignment PDF is visible.

## Walkthrough Outline

### 1. Introduction — about 30 seconds

Introduce yourself as Vipul Walia and describe the Real Connections Feed as a focused social-feed proof of concept. The goal is to rank authentic, personally relevant posts without using platform-wide popularity metrics such as total likes or views.

### 2. Architecture — about 60 seconds

Show the TSD Mermaid diagram. Explain that the Expo React Native screen calls only the Laravel API; Laravel owns Sanctum authentication, ranking, contracts, and PostgreSQL records; and Laravel calls the internal FastAPI service for transformer embeddings and Chroma vector operations. Emphasize that PostgreSQL remains authoritative if relational and vector data temporarily diverge.

### 3. Backend API — about 90 seconds

Show the four required Sanctum-protected routes:

- `POST /api/posts`
- `GET /api/feed`
- `GET /api/search`
- `POST /api/interactions`

Demonstrate a genuine post creation request without displaying the bearer token. Explain that Laravel saves the post first and then attempts synchronous indexing; a vector failure leaves the post available with an honest failed state and a retry path through `app:index-posts`. Demonstrate a reaction event and note that views, reactions, and replies remain separate interaction events.

### 4. Feed Ranking — about 75 seconds

Show the exact formula:

```text
score = 0.25 authenticity
      + 0.30 relationship depth
      + 0.30 semantic similarity
      + 0.15 time decay
```

Explain that relationship depth weights views as 1, reactions as 3, and replies as 5. Semantic similarity comes from `sentence-transformers/all-MiniLM-L6-v2`; the explicit deterministic hash provider is only a lexical fallback mode. Recency uses `exp(-age_hours / 72)`. A new user ranks from authenticity and recency, and an unavailable vector service sets semantic similarity to zero while keeping the feed available. Total engagement popularity is never a ranking input.

### 5. Mobile Feed Screen — about 90 seconds

Show the initial feed and point out the initials avatar placeholder, author name, relative time, post text, optional image, and reaction action. Pull to refresh, react to one post, and search with a natural-language phrase such as `journey abroad`. Explain that search results replace the list inline, while feed infinite scroll follows backend pagination at 20 posts per page. Briefly show a loading, empty, configuration, authentication, or error state where practical without changing application scope.

### 6. SQL Challenge and Tests — about 45 seconds

Open `sql/queries.sql` and identify:

- D1: top users by recent event counts, separated by type.
- D2: recent posts from authors ranked by one user's interaction frequency.
- D3: posts with more than 100 views and no reactions.
- D4: users with more than 20 posts in 24 hours.

Show the passing Laravel, Python, and mobile test summaries. Mention that Android and iOS non-interactive Expo exports also pass.

### 7. Trade-offs — about 45 seconds

Call out the deliberate take-home trade-offs: synchronous embedding generation, text-only authenticity scoring, a local Expo token configuration, no shared transaction between PostgreSQL and Chroma, and a deterministic hash provider that does not provide true semantic understanding.

### 8. Closing — about 20 seconds

Show the README setup sequence and note that the repository is intentionally public for direct recruiter review. Thank the reviewer. Do not say that a video link exists until this walkthrough has actually been recorded and uploaded.

## Recording Reminders

- Redact the Sanctum token and every Authorization header.
- Do not show local PostgreSQL usernames or passwords.
- Do not open or display the confidential assignment PDF.
- Keep the repository URL visible only if desired; it is intentionally public.
- Avoid showing unrelated browser tabs, notifications, terminal history, or editor metadata.
- Keep the explanation conversational and use this outline as prompts rather than reading it word for word.

## Cleanup After Recording

Stop Expo, Laravel, and FastAPI with `Ctrl+C`, then restore deterministic local data:

```bash
cd apps/api
php artisan migrate:fresh --seed
```

Start FastAPI briefly in another terminal and restore the seeded post index:

```bash
cd services/embeddings
source .venv/bin/activate
uvicorn app.main:app --host 127.0.0.1 --port 8001
```

```bash
cd apps/api
php artisan app:index-posts
```

Stop FastAPI afterward. The expected final state is 3 users, 15 posts, 18 interactions, 15 ready embeddings, no pending or failed embeddings, and no Sanctum tokens. Remove the untracked `apps/mobile/.env` if the demo token is no longer needed.
