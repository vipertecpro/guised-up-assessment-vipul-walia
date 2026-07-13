"""Small persistent Chroma wrapper for post-vector operations."""

import math
from collections.abc import Sequence

import chromadb

from app.schemas import MetadataValue, SearchResult


class VectorStoreError(RuntimeError):
    """Raised when Chroma cannot complete an internal vector operation."""


class SeedDocumentsNotFoundError(LookupError):
    """Raised when no supplied recommendation seed exists in Chroma."""


class VectorStore:
    """Own the single persistent Chroma collection used by this service."""

    def __init__(self, path: str, collection_name: str, distance: str) -> None:
        try:
            self.client = chromadb.PersistentClient(path=path)
            self.collection = self.client.get_or_create_collection(
                name=collection_name,
                metadata={"hnsw:space": distance},
            )
        except Exception as error:
            raise VectorStoreError(
                "Chroma could not initialize its persistent post collection."
            ) from error

    def upsert(
        self,
        document_id: str,
        text: str,
        embedding: list[float],
        metadata: dict[str, MetadataValue],
    ) -> None:
        """Insert or replace one caller-identified post document."""

        arguments = {
            "ids": [document_id],
            "documents": [text],
            "embeddings": [embedding],
        }
        if metadata:
            arguments["metadatas"] = [metadata]

        try:
            self.collection.upsert(**arguments)
        except Exception as error:
            raise VectorStoreError("Chroma could not upsert the document.") from error

    def query(
        self,
        embedding: list[float],
        limit: int,
        excluded_document_ids: set[str],
    ) -> list[SearchResult]:
        """Return higher-is-better cosine results, excluding requested IDs."""

        try:
            count = self.collection.count()
            if count == 0:
                return []

            result = self.collection.query(
                query_embeddings=[embedding],
                n_results=min(count, limit + len(excluded_document_ids)),
                include=["metadatas", "distances"],
            )
        except Exception as error:
            raise VectorStoreError("Chroma could not query the collection.") from error

        ids = result.get("ids", [[]])[0]
        distances = result.get("distances", [[]])[0]
        metadatas = result.get("metadatas", [[]])[0]
        matches: list[SearchResult] = []

        for document_id, distance, metadata in zip(ids, distances, metadatas):
            if document_id in excluded_document_ids:
                continue

            # Cosine distance is 1 - similarity. Mapping [-1, 1] to [0, 1]
            # gives API consumers an intuitive, bounded higher-is-better score.
            score = max(0.0, min(1.0, 1.0 - (float(distance) / 2.0)))
            matches.append(
                SearchResult(
                    document_id=document_id,
                    score=score,
                    metadata=metadata or {},
                )
            )
            if len(matches) == limit:
                break

        return matches

    def mean_seed_embedding(self, document_ids: Sequence[str]) -> list[float]:
        """Retrieve, average, and normalize all existing seed vectors."""

        try:
            result = self.collection.get(
                ids=list(dict.fromkeys(document_ids)),
                include=["embeddings"],
            )
        except Exception as error:
            raise VectorStoreError(
                "Chroma could not retrieve recommendation seeds."
            ) from error

        embeddings = result.get("embeddings")
        if embeddings is None or len(embeddings) == 0:
            raise SeedDocumentsNotFoundError(
                "None of the supplied seed documents exist in the vector collection."
            )

        vectors = [list(map(float, vector)) for vector in embeddings]
        dimensions = len(vectors[0])
        mean = [
            sum(vector[index] for vector in vectors) / len(vectors)
            for index in range(dimensions)
        ]
        magnitude = math.sqrt(sum(component * component for component in mean))
        if magnitude == 0:
            raise VectorStoreError(
                "The supplied seed documents did not produce a usable mean vector."
            )

        return [component / magnitude for component in mean]

    def count(self) -> int:
        """Return the collection size for deterministic test assertions."""

        try:
            return self.collection.count()
        except Exception as error:
            raise VectorStoreError("Chroma could not count the collection.") from error
