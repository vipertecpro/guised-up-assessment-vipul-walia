"""Isolated fixtures for deterministic hash-provider API tests."""

from collections.abc import Iterator

import pytest
from fastapi.testclient import TestClient

from app.config import Settings
from app.main import create_app


@pytest.fixture
def client(tmp_path) -> Iterator[TestClient]:
    """Run each test against its own temporary Chroma directory."""

    settings = Settings(
        chroma_path=str(tmp_path / "chroma"),
        embedding_provider="hash",
        hash_embedding_dimensions=384,
    )
    with TestClient(create_app(settings)) as test_client:
        yield test_client
