"""FastAPI entrypoint for the dirty2data Python data processing service.

Provides:
- Health check
- Upload + profile + quality score endpoint
- Quality score calculation from pre-profiled metrics
"""

import os
from pathlib import Path
from typing import Annotated

from dotenv import load_dotenv
from fastapi import FastAPI, File, HTTPException, UploadFile, status

_ENV_PATHS = [
    Path(__file__).resolve().parent.parent / ".env",
    Path(__file__).resolve().parent.parent.parent / ".env",
]

for _p in _ENV_PATHS:
    if _p.exists():
        load_dotenv(_p, override=False)
        break

from app.parsing import parse_upload_to_dataframe
from app.profiling import profile_dataframe
from app.quality_score import calculate_quality_score
from app.schemas import (
    DatasetProfileMetrics,
    DatasetUploadAccepted,
    ErrorResponse,
    HealthResponse,
    QualityIssue,
    QualityRecommendation,
    QualityScoreBreakdown,
    QualityScoreRequest,
    QualityScoreResponse,
    QualityStatus,
    ScoreType,
    WeightedSubScore,
)

SERVICE_NAME = "dirty2data-python-service"
SERVICE_VERSION = "0.1.0"

app = FastAPI(
    title="dirty2data Python Data Service",
    version=SERVICE_VERSION,
    description=(
        "FastAPI service for deterministic dataset profiling and rule-based data "
        "quality scoring. Consumed server-to-server by the Laravel application "
        "during the Upload → Profile workflow."
    ),
)


@app.get(
    "/health",
    response_model=HealthResponse,
    status_code=status.HTTP_200_OK,
    summary="Service health check",
    tags=["system"],
)
async def health() -> HealthResponse:
    """Return a stable, dependency-light health payload."""

    return HealthResponse(status="ok", service=SERVICE_NAME, version=SERVICE_VERSION)


@app.post(
    "/datasets/uploads",
    response_model=QualityScoreResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Upload, profile, and score a tabular dataset",
    description=(
        "Accepts a CSV, XLS, or XLSX file via multipart upload, profiles it, "
        "and returns a deterministic data quality score. The caller (Laravel) "
        "is responsible for persisting the result."
    ),
    tags=["datasets"],
    responses={
        400: {"model": ErrorResponse, "description": "Bad request (missing file)."},
        413: {"model": ErrorResponse, "description": "File too large."},
        415: {"model": ErrorResponse, "description": "Unsupported file type."},
        422: {"model": ErrorResponse, "description": "Parse error or empty dataset."},
        500: {"model": ErrorResponse, "description": "Internal processing error."},
    },
)
async def upload_and_profile_dataset(
    file: Annotated[
        UploadFile,
        File(description="CSV, XLS, or XLSX tabular data file."),
    ],
) -> QualityScoreResponse:
    """Parse an uploaded file, profile it, compute a quality score, and return JSON.

    The original filename is treated as display-only metadata and is never used
    as a server-side filesystem path.
    """

    df, _ext = await parse_upload_to_dataframe(file)
    profile = profile_dataframe(df)
    result = calculate_quality_score(profile)

    profile_metrics = DatasetProfileMetrics(**profile)

    return QualityScoreResponse(
        dataset_id=None,
        score_type=ScoreType.before,
        final_score=result.final_score,
        status=QualityStatus(result.status),
        breakdown=_build_breakdown(result),
        issues_summary=[
            QualityIssue(**issue) for issue in result.issues
        ],
        recommendation_summary=[
            QualityRecommendation(**rec) for rec in result.recommendations
        ],
        profile_metrics=profile_metrics,
    )


@app.post(
    "/quality-scores",
    response_model=QualityScoreResponse,
    status_code=status.HTTP_200_OK,
    summary="Calculate a quality score from pre-profiled metrics",
    description=(
        "Accepts profile metrics (from the caller's own profiling step) and returns "
        "a deterministic quality score. No file parsing is performed. "
        "Path inputs are not accepted."
    ),
    tags=["quality-scores"],
)
async def compute_quality_score(
    request: QualityScoreRequest,
) -> QualityScoreResponse:
    """Score already-profiled data from its metrics only."""

    profile = request.profile_metrics.model_dump()
    result = calculate_quality_score(profile)

    return QualityScoreResponse(
        dataset_id=request.dataset_id,
        score_type=request.score_type,
        final_score=result.final_score,
        status=QualityStatus(result.status),
        breakdown=_build_breakdown(result),
        issues_summary=[
            QualityIssue(**issue) for issue in result.issues
        ],
        recommendation_summary=[
            QualityRecommendation(**rec) for rec in result.recommendations
        ],
        profile_metrics=request.profile_metrics,
    )


def _build_breakdown(result) -> QualityScoreBreakdown:
    """Convert internal SubScore objects to Pydantic WeightedSubScore schema."""

    return QualityScoreBreakdown(
        completeness=WeightedSubScore(
            score=result.completeness.score,
            weight=result.completeness.weight,
            weighted_score=result.completeness.weighted_score,
            details=result.completeness.details,
        ),
        uniqueness=WeightedSubScore(
            score=result.uniqueness.score,
            weight=result.uniqueness.weight,
            weighted_score=result.uniqueness.weighted_score,
            details=result.uniqueness.details,
        ),
        validity=WeightedSubScore(
            score=result.validity.score,
            weight=result.validity.weight,
            weighted_score=result.validity.weighted_score,
            details=result.validity.details,
        ),
        consistency=WeightedSubScore(
            score=result.consistency.score,
            weight=result.consistency.weight,
            weighted_score=result.consistency.weighted_score,
            details=result.consistency.details,
        ),
        type_accuracy=WeightedSubScore(
            score=result.type_accuracy.score,
            weight=result.type_accuracy.weight,
            weighted_score=result.type_accuracy.weighted_score,
            details=result.type_accuracy.details,
        ),
    )
