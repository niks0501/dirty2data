"""Public API contracts for the dirty2data Python data service.

These schemas intentionally avoid accepting user-controlled filesystem paths.
Public file ingestion should use the multipart upload endpoint in ``main.py``.
"""

from enum import StrEnum

from pydantic import BaseModel, ConfigDict, Field


class ScoreType(StrEnum):
    """Supported moments in the Upload → Profile → Clean workflow."""

    before = "before"
    after = "after"


class QualityStatus(StrEnum):
    """Human-readable quality bands for dashboard display."""

    excellent = "Excellent"
    good = "Good"
    fair = "Fair"
    poor = "Poor"
    critical = "Critical"


class IssueSeverity(StrEnum):
    """Severity values for explainable data quality issues."""

    info = "info"
    warning = "warning"
    error = "error"


class HealthResponse(BaseModel):
    """Stable health-check response used by Laravel and local tooling."""

    model_config = ConfigDict(title="HealthResponse")

    status: str = Field(examples=["ok"])
    service: str = Field(examples=["dirty2data-python-service"])
    version: str = Field(examples=["0.1.0"])


class DatasetUploadAccepted(BaseModel):
    """Acknowledges an uploaded dataset without exposing a filesystem path."""

    model_config = ConfigDict(title="DatasetUploadAccepted")

    dataset_id: str = Field(description="Server-generated identifier for this upload.")
    status: str = Field(description="Current processing state.", examples=["accepted"])
    filename: str | None = Field(
        default=None,
        description="Original client filename for display only; never used as a server path.",
    )
    content_type: str | None = Field(default=None, description="Client-provided MIME hint.")
    message: str = Field(description="Short next-step message for the caller.")


class ColumnProfileMetric(BaseModel):
    """Column-level profile values needed by quality scoring."""

    model_config = ConfigDict(title="ColumnProfileMetric")
    name: str
    detected_type: str = Field(description="Detected logical type, such as text, numeric, date.")
    missing_count: int = Field(ge=0)
    missing_percentage: float = Field(ge=0, le=100)
    unique_count: int = Field(ge=0)
    invalid_count: int = Field(default=0, ge=0)
    inconsistent_count: int = Field(default=0, ge=0)
    type_mismatch_count: int = Field(default=0, ge=0)


class DatasetProfileMetrics(BaseModel):
    """Dataset-level profile metrics used to compute a quality score."""

    model_config = ConfigDict(title="DatasetProfileMetrics")
    row_count: int = Field(ge=0)
    column_count: int = Field(ge=0)
    duplicate_count: int = Field(default=0, ge=0)
    total_cell_count: int = Field(default=0, ge=0)
    missing_cell_count: int = Field(default=0, ge=0)
    invalid_cell_count: int = Field(default=0, ge=0)
    inconsistent_cell_count: int = Field(default=0, ge=0)
    type_mismatch_cell_count: int = Field(default=0, ge=0)
    columns: list[ColumnProfileMetric] = Field(default_factory=list)


class QualityScoreRequest(BaseModel):
    """Request body for scoring already-profiled data.

    This contract accepts profile metrics only. It must not accept arbitrary file paths from
    callers; file ingestion belongs to the multipart upload endpoint.
    """

    model_config = ConfigDict(title="QualityScoreRequest")
    dataset_id: str | None = Field(default=None, description="Optional Laravel dataset id.")
    score_type: ScoreType = Field(default=ScoreType.before)
    profile_metrics: DatasetProfileMetrics


class WeightedSubScore(BaseModel):
    """A named 0-100 component score with its rule-based weight."""

    model_config = ConfigDict(title="WeightedSubScore")
    score: float = Field(ge=0, le=100)
    weight: float = Field(ge=0, le=1)
    weighted_score: float = Field(ge=0, le=100)
    details: str


class QualityScoreBreakdown(BaseModel):
    """Default weighted quality score components."""

    model_config = ConfigDict(title="QualityScoreBreakdown")
    completeness: WeightedSubScore
    uniqueness: WeightedSubScore
    validity: WeightedSubScore
    consistency: WeightedSubScore
    type_accuracy: WeightedSubScore


class QualityIssue(BaseModel):
    """Explainable issue surfaced to Laravel and the Profile dashboard."""

    model_config = ConfigDict(title="QualityIssue")
    code: str
    severity: IssueSeverity
    message: str
    column: str | None = None
    count: int | None = Field(default=None, ge=0)


class QualityRecommendation(BaseModel):
    """Plain-language action that can improve data quality."""

    model_config = ConfigDict(title="QualityRecommendation")
    code: str
    message: str
    recommended_action: str


class QualityScoreResponse(BaseModel):
    """Quality score JSON returned after upload/profile processing."""

    model_config = ConfigDict(title="QualityScoreResponse")
    dataset_id: str | None = None
    score_type: ScoreType
    final_score: float = Field(ge=0, le=100)
    status: QualityStatus
    breakdown: QualityScoreBreakdown
    issues_summary: list[QualityIssue] = Field(default_factory=list)
    recommendation_summary: list[QualityRecommendation] = Field(default_factory=list)
    profile_metrics: DatasetProfileMetrics


class ErrorResponse(BaseModel):
    """Stable error envelope for documented API failures."""

    model_config = ConfigDict(title="ErrorResponse")
    detail: str
