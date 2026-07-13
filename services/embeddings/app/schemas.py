"""Validated request and response schemas for the internal HTTP boundary."""

import math
from typing import Annotated, Any

from pydantic import BaseModel, ConfigDict, Field, StringConstraints, field_validator

NonEmptyString = Annotated[str, StringConstraints(strip_whitespace=True, min_length=1)]
MetadataValue = str | int | float | bool


class StrictSchema(BaseModel):
    """Base schema that rejects accidental fields in internal requests."""

    model_config = ConfigDict(extra="forbid")


class DocumentUpsertRequest(StrictSchema):
    """One deterministic post document to embed and persist."""

    document_id: NonEmptyString
    text: NonEmptyString
    metadata: dict[str, MetadataValue] = Field(default_factory=dict)

    @field_validator("metadata", mode="before")
    @classmethod
    def validate_metadata(cls, value: Any) -> Any:
        """Reject non-scalar and non-finite metadata before coercion."""

        if not isinstance(value, dict):
            raise ValueError("Metadata must be an object of scalar values.")

        for key, item in value.items():
            if not isinstance(key, str) or not key.strip():
                raise ValueError("Metadata keys must be non-empty strings.")
            if not isinstance(item, (str, int, float, bool)):
                raise ValueError(
                    "Metadata values must be strings, integers, floats, or booleans."
                )
            if isinstance(item, float) and not math.isfinite(item):
                raise ValueError("Metadata float values must be finite.")

        return value


class DocumentUpsertResponse(BaseModel):
    """Confirmation that one document is ready for vector retrieval."""

    document_id: str
    status: str


class SearchRequest(StrictSchema):
    """Natural-language query and bounded result controls."""

    query: NonEmptyString
    limit: int = Field(default=10, ge=1, le=50)
    exclude_document_ids: list[NonEmptyString] = Field(default_factory=list)


class RecommendationRequest(StrictSchema):
    """Seed documents and bounded recommendation controls."""

    seed_document_ids: list[NonEmptyString] = Field(min_length=1)
    limit: int = Field(default=50, ge=1, le=50)
    exclude_document_ids: list[NonEmptyString] = Field(default_factory=list)


class SearchResult(BaseModel):
    """One vector result without duplicating authoritative post content."""

    document_id: str
    score: float
    metadata: dict[str, MetadataValue]


class SearchResponse(BaseModel):
    """Ordered vector results for search and recommendations."""

    results: list[SearchResult]


class HealthResponse(BaseModel):
    """Safe operational summary for the running process."""

    status: str
    service: str
    provider: str
    collection: str
