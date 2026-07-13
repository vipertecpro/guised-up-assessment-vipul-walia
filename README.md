# Guised Up Assessment

This private take-home repository will contain a minimal social feed system: a Laravel API backed by PostgreSQL and Sanctum, a FastAPI service backed by Chroma for embeddings, one Expo React Native TypeScript feed screen, and a small set of raw SQL challenge queries.

## Planned Monorepo Structure

```text
apps/
  api/                 # Laravel REST API
  mobile/              # Expo React Native TypeScript app
services/
  embeddings/          # FastAPI embedding service with persistent Chroma storage
docs/
  TSD.md                # Technical solution document
sql/
  queries.sql           # SQL challenge queries (later phase)
```

Read the [Technical Solution Document](docs/TSD.md) for the architecture, data model, API contracts, ranking design, and implementation sequence.

## Current Status

Phase 1 documentation is complete. Application implementation, scaffolding, dependencies, and SQL challenge queries have not started.

> This repository must remain private because the assessment and its supporting materials are confidential.
