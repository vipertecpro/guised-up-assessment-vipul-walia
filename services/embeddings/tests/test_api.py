"""Focused behavior tests for the internal vector API."""

from fastapi.testclient import TestClient

from app.config import Settings
from app.main import create_app


def upsert(
    client: TestClient,
    document_id: str,
    text: str,
    post_id: int,
) -> None:
    """Insert one test document and require a successful response."""

    response = client.post(
        "/documents/upsert",
        json={
            "document_id": document_id,
            "text": text,
            "metadata": {"post_id": post_id, "published": True},
        },
    )
    assert response.status_code == 201


def test_health_reports_explicit_hash_provider(client: TestClient) -> None:
    response = client.get("/health")
    empty_search = client.post("/search", json={"query": "anything", "limit": 5})

    assert response.status_code == 200
    assert response.json() == {
        "status": "ok",
        "service": "embeddings",
        "provider": "hash",
        "collection": "posts",
    }
    assert empty_search.status_code == 200
    assert empty_search.json() == {"results": []}


def test_upsert_and_search_rank_related_document_first(client: TestClient) -> None:
    upsert(
        client,
        "post-1",
        "A funny travel story about a train journey abroad.",
        1,
    )
    upsert(
        client,
        "post-2",
        "Software development debugging and deployment notes.",
        2,
    )

    response = client.post(
        "/search",
        json={"query": "funny travel journey", "limit": 2},
    )

    assert response.status_code == 200
    results = response.json()["results"]
    assert [result["document_id"] for result in results] == ["post-1", "post-2"]
    assert results[0]["score"] > results[1]["score"]
    assert results[0]["metadata"]["post_id"] == 1
    assert "text" not in results[0]


def test_upsert_is_idempotent_and_replaces_searchable_content(
    client: TestClient,
) -> None:
    upsert(client, "post-7", "A quiet gardening journal.", 7)
    upsert(client, "post-7", "Software debugging coding problems.", 7)

    response = client.post(
        "/search",
        json={"query": "software debugging", "limit": 10},
    )

    assert response.status_code == 200
    assert response.json()["results"][0]["document_id"] == "post-7"
    assert client.app.state.context.vector_store.count() == 1


def test_chroma_data_persists_across_app_instances(tmp_path) -> None:
    chroma_path = str(tmp_path / "persistent-chroma")
    settings = Settings(chroma_path=chroma_path, embedding_provider="hash")

    with TestClient(create_app(settings)) as first_client:
        upsert(first_client, "post-9", "A persistent travel journal.", 9)

    with TestClient(create_app(settings)) as second_client:
        response = second_client.post(
            "/search",
            json={"query": "travel journal", "limit": 5},
        )

    assert response.status_code == 200
    assert response.json()["results"][0]["document_id"] == "post-9"


def test_recommendations_return_related_post_and_exclude_seed(
    client: TestClient,
) -> None:
    upsert(client, "post-2", "Travel journey by train through Europe.", 2)
    upsert(client, "post-5", "Travel journey by bus across India.", 5)
    upsert(client, "post-8", "Software release and coding notes.", 8)

    response = client.post(
        "/recommendations",
        json={"seed_document_ids": ["post-missing", "post-2"], "limit": 2},
    )

    assert response.status_code == 200
    results = response.json()["results"]
    assert results[0]["document_id"] == "post-5"
    assert "post-2" not in [result["document_id"] for result in results]


def test_validation_rejects_empty_text_and_nested_metadata(
    client: TestClient,
) -> None:
    empty_response = client.post(
        "/documents/upsert",
        json={"document_id": "post-1", "text": "   ", "metadata": {}},
    )
    metadata_response = client.post(
        "/documents/upsert",
        json={
            "document_id": "post-1",
            "text": "Valid text",
            "metadata": {"tags": ["travel"]},
        },
    )
    limit_response = client.post(
        "/search",
        json={"query": "valid query", "limit": 51},
    )

    assert empty_response.status_code == 422
    assert metadata_response.status_code == 422
    assert limit_response.status_code == 422


def test_recommendations_reject_when_no_seed_document_exists(
    client: TestClient,
) -> None:
    response = client.post(
        "/recommendations",
        json={"seed_document_ids": ["post-missing"], "limit": 5},
    )

    assert response.status_code == 404
    assert response.json() == {
        "message": (
            "None of the supplied seed documents exist in the vector collection."
        ),
        "errors": {},
    }
