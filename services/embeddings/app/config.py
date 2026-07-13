"""Environment-backed configuration for the embedding service."""

from functools import lru_cache
from typing import Literal

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Validated settings used to construct one service process."""

    app_name: str = "Guised Up Embeddings"
    app_env: str = "local"
    app_host: str = "127.0.0.1"
    app_port: int = Field(default=8001, ge=1, le=65535)
    chroma_path: str = "./storage/chroma"
    chroma_collection: str = "posts"
    chroma_distance: Literal["cosine"] = "cosine"
    embedding_provider: Literal["sentence_transformer", "hash"] = (
        "sentence_transformer"
    )
    embedding_model: str = "sentence-transformers/all-MiniLM-L6-v2"
    embedding_device: Literal["cpu"] = "cpu"
    hash_embedding_dimensions: int = Field(default=384, ge=8, le=4096)

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )


@lru_cache
def get_settings() -> Settings:
    """Return the process-wide settings instance."""

    return Settings()
