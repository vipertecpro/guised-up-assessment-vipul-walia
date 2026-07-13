# Guised Up Assessment

## Overview

This private take-home repository is a minimal monorepo for a planned social feed assessment. It currently contains runnable framework scaffolding for a Laravel API, an Expo React Native TypeScript app, and a FastAPI service; assignment business logic is intentionally deferred to later phases.

## Monorepo Structure

```text
apps/
  api/                 # Laravel 13 REST API scaffold with Sanctum
  mobile/              # Expo SDK 57 React Native TypeScript scaffold
services/
  embeddings/          # FastAPI scaffold with an infrastructure health route
docs/
  TSD.md                # Technical solution document
sql/
  .gitkeep              # SQL challenge directory; queries are not implemented yet
```

## Prerequisites

- PHP 8.3 or later and Composer
- PostgreSQL and its command-line client
- Node.js LTS and npm
- Python 3.14.4
- Expo-compatible iOS or Android simulator, device, or Expo Go

## Current Implementation Status

Phase 2 provides framework scaffolding only:

- Laravel and Sanctum are installed, API routing is enabled, and PostgreSQL placeholders are configured.
- The mobile app renders one minimal placeholder screen and has no navigation or Feed Screen implementation.
- FastAPI exposes only `GET /health` for infrastructure validation.
- Feed APIs, post and interaction models, ranking, search, embeddings, Chroma, and SQL challenge queries are not implemented.

## Local Setup Summary

### Laravel API

```bash
cd apps/api
cp .env.example .env
composer install
php artisan key:generate
php artisan serve
```

Configure the local PostgreSQL database before running migrations in a later phase. Laravel Herd may be used instead of `php artisan serve`.

### Embedding Service

```bash
cd services/embeddings
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
.venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 8001
```

The health endpoint is `http://127.0.0.1:8001/health`.

### Mobile App

```bash
cd apps/mobile
cp .env.example .env
npm install
npm start
```

## Environment Files

- Laravel: `apps/api/.env.example`
- Mobile: `apps/mobile/.env.example`
- Embedding service: `services/embeddings/.env.example`

Local `.env` files are ignored and must not contain committed credentials.

## Documentation

See the [Technical Solution Document](docs/TSD.md) for the planned architecture, data model, API contracts, and implementation sequence.

## Confidentiality

This repository must remain private because the assessment and its supporting materials are confidential. Do not copy the assignment PDF into the repository.
