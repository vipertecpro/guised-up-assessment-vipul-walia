# Guised Up Mobile

This Expo SDK 57 application implements the assignment's single authenticated Feed Screen. It has no navigation, authentication UI, or additional screens.

## Prerequisites

- Node.js and npm supported by Expo SDK 57
- An iOS simulator, Android emulator, physical device, or Expo Go
- The Laravel API, PostgreSQL, and FastAPI embedding service configured locally

## Backend preparation

Prepare Laravel from `apps/api`:

```bash
php artisan migrate:fresh --seed
php artisan app:index-posts
php artisan app:issue-demo-token vipul@example.com
php artisan serve
```

The token command prints a local Sanctum token once. Keep it outside source control.

Start the embedding service from `services/embeddings`:

```bash
source .venv/bin/activate
uvicorn app.main:app --reload --port 8001
```

Start FastAPI before indexing posts. The feed can degrade without semantic ranking, but natural-language search requires the embedding service and indexed posts.

## Mobile setup

From `apps/mobile`:

```bash
npm install
cp .env.example .env
npm start
```

Set both variables in the untracked `.env` file:

```dotenv
EXPO_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api
EXPO_PUBLIC_API_TOKEN=replace-with-local-sanctum-token
```

Choose an API URL the target can reach:

- iOS simulator: `http://127.0.0.1:8000/api`
- Android emulator: `http://10.0.2.2:8000/api`
- Physical device: the development machine's LAN address, such as `http://192.168.x.x:8000/api`

Environment detection is intentionally manual. Laravel may need to listen on a reachable interface for a physical device.

`EXPO_PUBLIC_*` values are bundled into the application and are visible to anyone inspecting that bundle. Supplying a Sanctum token this way is acceptable only for this local assessment demonstration; a production application should use a real authentication flow and protected token storage.

## Validation

```bash
npm test
npm run typecheck
npx expo config --type public
```

The screen uses the built-in React Native list, input, refresh, image, and press components. It reads the personalized paginated feed, performs debounced semantic search, and logs one reaction per post for the current application session.
