"""Embedding providers supported by the internal service."""

import hashlib
import math
import re
from typing import Protocol

from app.config import Settings

TOKEN_PATTERN = re.compile(r"[\w']+", flags=re.UNICODE)


class EmbeddingProviderError(RuntimeError):
    """Raised when a configured embedding provider cannot produce vectors."""


class EmbeddingProvider(Protocol):
    """Small shared contract for document and query embedding providers."""

    def embed_documents(self, texts: list[str]) -> list[list[float]]:
        """Embed a batch of post documents."""

    def embed_query(self, text: str) -> list[float]:
        """Embed one natural-language query."""


class HashEmbeddingProvider:
    """Deterministic lexical token hashing for tests and explicit fallback use."""

    def __init__(self, dimensions: int) -> None:
        self.dimensions = dimensions

    def embed_documents(self, texts: list[str]) -> list[list[float]]:
        """Embed documents with stable signed token hashing."""

        return [self._embed(text) for text in texts]

    def embed_query(self, text: str) -> list[float]:
        """Embed a query with the same deterministic lexical algorithm."""

        return self._embed(text)

    def _embed(self, text: str) -> list[float]:
        tokens = TOKEN_PATTERN.findall(text.casefold())
        if not tokens:
            raise EmbeddingProviderError("Text must contain at least one token.")

        vector = [0.0] * self.dimensions
        for token in tokens:
            digest = hashlib.sha256(token.encode("utf-8")).digest()
            index = int.from_bytes(digest[:8], byteorder="big") % self.dimensions
            sign = 1.0 if digest[8] & 1 else -1.0
            vector[index] += sign

        magnitude = math.sqrt(sum(component * component for component in vector))
        if magnitude == 0:
            raise EmbeddingProviderError("Text did not produce a usable embedding.")

        return [component / magnitude for component in vector]


class SentenceTransformerEmbeddingProvider:
    """Lazy CPU-backed sentence-transformer provider with normalized vectors."""

    def __init__(self, model_name: str, device: str) -> None:
        self.model_name = model_name
        self.device = device
        self._model = None

    def embed_documents(self, texts: list[str]) -> list[list[float]]:
        """Embed a batch of documents with the configured open model."""

        return self._encode(texts)

    def embed_query(self, text: str) -> list[float]:
        """Embed one query with the same normalized model space."""

        return self._encode([text])[0]

    def _load_model(self):
        if self._model is not None:
            return self._model

        try:
            from sentence_transformers import SentenceTransformer

            self._model = SentenceTransformer(
                self.model_name,
                device=self.device,
            )
        except Exception as error:
            raise EmbeddingProviderError(
                f"Unable to load sentence-transformer model '{self.model_name}'. "
                "Confirm the model is available locally or downloadable, or explicitly "
                "set EMBEDDING_PROVIDER=hash for deterministic lexical fallback mode."
            ) from error

        return self._model

    def _encode(self, texts: list[str]) -> list[list[float]]:
        try:
            vectors = self._load_model().encode(
                texts,
                batch_size=max(1, min(len(texts), 32)),
                convert_to_numpy=True,
                normalize_embeddings=True,
                show_progress_bar=False,
            )
        except EmbeddingProviderError:
            raise
        except Exception as error:
            raise EmbeddingProviderError(
                "The configured sentence-transformer model could not generate "
                "embeddings."
            ) from error

        return vectors.tolist()


def create_embedding_provider(settings: Settings) -> EmbeddingProvider:
    """Build only the explicitly configured embedding provider."""

    if settings.embedding_provider == "hash":
        return HashEmbeddingProvider(settings.hash_embedding_dimensions)

    return SentenceTransformerEmbeddingProvider(
        model_name=settings.embedding_model,
        device=settings.embedding_device,
    )
