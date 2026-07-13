# Embedding Service

Small network-private FastAPI service for post embeddings and persistent Chroma
vector search. PostgreSQL remains the source of truth; this service stores only
caller-supplied IDs, post text, scalar metadata, and vectors.

## Setup

Python 3.14.4 is the tested interpreter for the pinned dependencies on macOS
arm64. Create the local environment without changing the system Python:

```bash
cd services/embeddings
python3.14 -m venv .venv
.venv/bin/python -m pip install -r requirements.txt
cp .env.example .env
```

The normal provider is `sentence_transformer`, using the open
`sentence-transformers/all-MiniLM-L6-v2` model on CPU. The model loads lazily on
the first embedding operation and may be downloaded to the user's normal model
cache if it is not already available.

Start the service:

```bash
.venv/bin/uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Check it locally:

```bash
curl http://127.0.0.1:8001/health
```

Chroma uses one cosine-distance collection named `posts` and persists under
`./storage/chroma` by default. That directory and `.venv` are ignored by Git.
Use deterministic caller-owned IDs such as `post-15`; repeated upserts replace
the same vector document.

## Explicit hash mode

Set `EMBEDDING_PROVIDER=hash` only when deterministic local fallback behavior is
intended:

```bash
EMBEDDING_PROVIDER=hash .venv/bin/uvicorn app.main:app \
  --host 127.0.0.1 --port 8001
```

The hash provider produces normalized, deterministic lexical-similarity vectors
with stable cryptographic token hashing. It is useful for tests and compatible
local environments, but it is not equivalent to a transformer model and must
not be described as semantic understanding. The service never silently changes
from the sentence-transformer provider to hash mode.

## Internal endpoints

- `GET /health` reports the configured provider and collection without paths or
  secrets.
- `POST /documents/upsert` embeds and idempotently stores one caller-identified
  document with optional scalar metadata.
- `POST /search` embeds a natural-language query and returns IDs, bounded
  higher-is-better scores, and metadata.
- `POST /recommendations` averages and normalizes all existing supplied seed
  vectors, excludes the seeds, and returns the same result shape as search.

This service has no public authentication in the assessment and must be kept on
a private application network in production.

## Tests

```bash
.venv/bin/python -m pytest -q
```

Tests force explicit hash mode, use a separate temporary Chroma directory per
test, and do not access the network or load the transformer model.
