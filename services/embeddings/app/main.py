"""FastAPI boundary for internal post embedding and vector retrieval."""

from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from dataclasses import dataclass

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse

from app.config import Settings, get_settings
from app.embeddings import (
    EmbeddingProvider,
    EmbeddingProviderError,
    create_embedding_provider,
)
from app.schemas import (
    DocumentUpsertRequest,
    DocumentUpsertResponse,
    HealthResponse,
    RecommendationRequest,
    SearchRequest,
    SearchResponse,
)
from app.vector_store import (
    SeedDocumentsNotFoundError,
    VectorStore,
    VectorStoreError,
)


@dataclass
class ServiceContext:
    """Process-scoped provider and persistent vector store."""

    provider: EmbeddingProvider
    vector_store: VectorStore


def error_response(status_code: int, message: str) -> JSONResponse:
    """Return the consistent application-error shape."""

    return JSONResponse(
        status_code=status_code,
        content={"message": message, "errors": {}},
    )


def create_app(settings: Settings | None = None) -> FastAPI:
    """Create one configured FastAPI service instance."""

    active_settings = settings or get_settings()

    @asynccontextmanager
    async def lifespan(application: FastAPI) -> AsyncIterator[None]:
        application.state.context = ServiceContext(
            provider=create_embedding_provider(active_settings),
            vector_store=VectorStore(
                path=active_settings.chroma_path,
                collection_name=active_settings.chroma_collection,
                distance=active_settings.chroma_distance,
            ),
        )
        yield

    application = FastAPI(title=active_settings.app_name, lifespan=lifespan)

    @application.exception_handler(EmbeddingProviderError)
    async def handle_embedding_error(
        _request: Request,
        error: EmbeddingProviderError,
    ) -> JSONResponse:
        return error_response(503, str(error))

    @application.exception_handler(VectorStoreError)
    async def handle_vector_store_error(
        _request: Request,
        error: VectorStoreError,
    ) -> JSONResponse:
        return error_response(503, str(error))

    @application.exception_handler(SeedDocumentsNotFoundError)
    async def handle_missing_seeds(
        _request: Request,
        error: SeedDocumentsNotFoundError,
    ) -> JSONResponse:
        return error_response(404, str(error))

    @application.get(
        "/health",
        response_model=HealthResponse,
        tags=["infrastructure"],
    )
    async def health() -> HealthResponse:
        """Report safe provider and collection configuration."""

        return HealthResponse(
            status="ok",
            service="embeddings",
            provider=active_settings.embedding_provider,
            collection=active_settings.chroma_collection,
        )

    @application.post(
        "/documents/upsert",
        response_model=DocumentUpsertResponse,
        status_code=201,
        tags=["vectors"],
    )
    def upsert_document(
        payload: DocumentUpsertRequest,
        request: Request,
    ) -> DocumentUpsertResponse:
        """Embed and idempotently persist one caller-identified post."""

        context: ServiceContext = request.app.state.context
        embedding = context.provider.embed_documents([payload.text])[0]
        context.vector_store.upsert(
            document_id=payload.document_id,
            text=payload.text,
            embedding=embedding,
            metadata=payload.metadata,
        )
        return DocumentUpsertResponse(
            document_id=payload.document_id,
            status="ready",
        )

    @application.post(
        "/search",
        response_model=SearchResponse,
        tags=["vectors"],
    )
    def search(payload: SearchRequest, request: Request) -> SearchResponse:
        """Search post vectors using a natural-language query."""

        context: ServiceContext = request.app.state.context
        embedding = context.provider.embed_query(payload.query)
        return SearchResponse(
            results=context.vector_store.query(
                embedding=embedding,
                limit=payload.limit,
                excluded_document_ids=set(payload.exclude_document_ids),
            )
        )

    @application.post(
        "/recommendations",
        response_model=SearchResponse,
        tags=["vectors"],
    )
    def recommendations(
        payload: RecommendationRequest,
        request: Request,
    ) -> SearchResponse:
        """Find posts near the normalized mean of existing seed vectors."""

        context: ServiceContext = request.app.state.context
        embedding = context.vector_store.mean_seed_embedding(
            payload.seed_document_ids
        )
        exclusions = set(payload.seed_document_ids) | set(
            payload.exclude_document_ids
        )
        return SearchResponse(
            results=context.vector_store.query(
                embedding=embedding,
                limit=payload.limit,
                excluded_document_ids=exclusions,
            )
        )

    return application


app = create_app()
