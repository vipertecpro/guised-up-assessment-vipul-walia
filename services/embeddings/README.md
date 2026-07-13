# FastAPI Embedding Service

This network-private FastAPI service provides embedding and vector-search operations for the Laravel API. An embedding is a numeric representation of text that allows meaning-related posts and queries to be compared. The service uses a sentence-transformer for normal semantic inference and Chroma for persistent nearest-vector lookup.

PostgreSQL remains authoritative for users, posts, and interactions. Chroma stores caller-supplied post IDs, text, scalar metadata, and vectors as a replaceable search index; it is not the primary application database.

## Prerequisites

Python 3.14.4 is the tested interpreter for the pinned dependencies on macOS arm64. If binary dependency installation fails in another environment, Python 3.12 or 3.13 is a suitable fallback for the listed ML dependencies.

## Setup

From the repository root:

```bash
cd services/embeddings
python3 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip
pip install -r requirements.txt
cp .env.example .env
```

On Windows, activate the virtual environment with `.venv\Scripts\activate` instead.

The default `.env` configures:

```dotenv
APP_HOST=127.0.0.1
APP_PORT=8001
CHROMA_PATH=./storage/chroma
CHROMA_COLLECTION=posts
EMBEDDING_PROVIDER=sentence_transformer
EMBEDDING_MODEL=sentence-transformers/all-MiniLM-L6-v2
EMBEDDING_DEVICE=cpu
```

The normal `sentence_transformer` provider loads the open `sentence-transformers/all-MiniLM-L6-v2` model lazily on CPU. The first embedding operation may download the model into the user's normal model cache.

## Start the Service

With the virtual environment active and the current directory set to `services/embeddings`:

```bash
uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Keep this terminal running. Check the service with:

```bash
curl http://127.0.0.1:8001/health
```

## Explicit Hash Provider

Set `EMBEDDING_PROVIDER=hash` only when deterministic offline fallback behaviour is intentionally required:

```bash
EMBEDDING_PROVIDER=hash uvicorn app.main:app --host 127.0.0.1 --port 8001
```

The hash provider uses stable token hashing to produce normalized vectors. It is deterministic, offline, test-friendly, and lexical. It is not equivalent to transformer semantic search, and the service never silently switches from the sentence-transformer provider to hash mode.

## Internal Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/health` | Report safe provider and collection configuration |
| `POST` | `/documents/upsert` | Embed and idempotently store one caller-identified document |
| `POST` | `/search` | Embed a natural-language query and return similar document IDs |
| `POST` | `/recommendations` | Average valid seed vectors and return related document IDs |

This service has no public authentication in the assessment. The React Native mobile application never calls it directly; Laravel is the only application client.

## Chroma Persistence

Chroma uses one cosine-distance collection named `posts` and persists under `services/embeddings/storage/chroma/` by default. The persistence directory and `.venv` are ignored by Git. IDs such as `post-15` are deterministic, so repeated upserts replace the same vector document.

## Failure Behaviour

- Transformer load or embedding failures are returned explicitly; no algorithm is substituted silently.
- Vector-store failures return a sanitized service error.
- Missing recommendation seeds return a clear not-found response.
- Laravel preserves a relationally created post with `embedding_status=failed` when indexing fails.
- Laravel's indexing command can retry pending and failed posts.
- Laravel search returns HTTP `503` when semantic infrastructure is unavailable, while feed ranking degrades with semantic similarity set to zero.

## Tests

With the virtual environment active from `services/embeddings`:

```bash
pytest
```

Tests force explicit hash mode, use an isolated temporary Chroma directory, and do not access the network or load the transformer model. The last local run passed 7 tests.

## Candidate Context

Python is not the candidate's primary professional stack. This service was implemented as a focused internal integration using FastAPI, sentence-transformers, and Chroma. The candidate understands and can explain the service boundary, request flow, vector-search purpose, and failure handling.

For the full system flow, see the [root README](../../README.md) and [Technical Solution Document](../../docs/TSD.md).
