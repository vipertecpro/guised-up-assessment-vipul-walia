"""Minimal FastAPI application for embedding-service infrastructure checks."""

from fastapi import FastAPI
from pydantic import BaseModel
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Environment-backed service settings."""

    app_name: str = "Guised Up Embeddings"
    app_env: str = "local"
    app_host: str = "127.0.0.1"
    app_port: int = 8001
    chroma_path: str = "./storage/chroma"

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")


class HealthResponse(BaseModel):
    """Health-check response contract."""

    status: str
    service: str


settings = Settings()
app = FastAPI(title=settings.app_name)


@app.get("/health", response_model=HealthResponse, tags=["infrastructure"])
async def health() -> HealthResponse:
    """Confirm that the embedding service process is available."""

    return HealthResponse(status="ok", service="embeddings")
