# Guised Up Mobile Application

This Expo SDK 57 React Native application implements the assessment's single authenticated Feed Screen. It intentionally has no navigation, login screen, or post-creation screen.

## Prerequisites and Service Dependencies

- Node.js 24 and npm 11 were used for validation.
- Use an iOS simulator, Android emulator, or supported physical Expo environment.
- PostgreSQL, the Laravel API, and the FastAPI embedding service must be configured first.
- FastAPI must be running when seeded posts are indexed and when semantic search is used. The feed can degrade when semantic ranking is unavailable.

From the repository root, prepare the backend:

```bash
cd apps/api
php artisan migrate:fresh --seed
php artisan app:index-posts
php artisan app:issue-demo-token vipul@example.com
php artisan serve --host=127.0.0.1 --port=8000
```

Start FastAPI before running `app:index-posts`; see the [embedding-service guide](../../services/embeddings/README.md). The token command replaces the user's previous `assessment-mobile` token and displays the new plaintext token once.

## Setup

In a new terminal from the repository root:

```bash
cd apps/mobile
npm install
cp .env.example .env
```

Set both values in the untracked `apps/mobile/.env` file:

```dotenv
EXPO_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api
EXPO_PUBLIC_API_TOKEN=replace-with-local-sanctum-token
```

Choose the API URL the target can reach:

- iOS simulator: `http://127.0.0.1:8000/api`
- Android emulator: `http://10.0.2.2:8000/api`
- Physical device: the development computer's LAN address, such as `http://192.168.x.x:8000/api`

For a physical device, run Laravel from `apps/api` with `php artisan serve --host=0.0.0.0 --port=8000` and ensure the development computer is reachable on the local network.

`EXPO_PUBLIC_*` values are bundled into the application and can be read from the bundle. `EXPO_PUBLIC_API_TOKEN` is local-assessment configuration only; a production application should use a real authentication flow and protected token storage.

## Start the Application

From `apps/mobile`:

```bash
npm start
```

Press `i` for iOS, press `a` for Android, or scan the QR code for a supported physical Expo environment.

## Feed Screen Behaviour

- Loads the authenticated personalized feed from Laravel.
- Uses page-based infinite scroll and prevents duplicate concurrent page loads.
- Supports pull-to-refresh on the normal feed.
- Debounces and cancels stale natural-language semantic searches.
- Records one reaction per post for the current app session.
- Handles missing configuration, initial loading, empty feed, empty search, authentication errors, network failures, pagination failures, and image-load failures without fabricating data.

The mobile application communicates only with Laravel; it never calls FastAPI or Chroma directly.

## Validation

From `apps/mobile`:

```bash
npm test
npm run typecheck
```

Optional bundle validation:

```bash
npx expo export --platform android --output-dir /tmp/guised-up-android
npx expo export --platform ios --output-dir /tmp/guised-up-ios
```

The last local run passed 9 Jest tests, TypeScript validation, and both native Expo exports. Rerun the commands after cloning.

## Development Transparency

The mobile screen was implemented with AI-assisted development under candidate direction and review. Laravel is the candidate's primary stack; React Native and Expo are areas of working familiarity.

For complete setup, architecture, and API examples, see the [root README](../../README.md) and [Technical Solution Document](../../docs/TSD.md).
