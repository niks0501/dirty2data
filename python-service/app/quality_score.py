"""Deterministic, rule-based data quality scoring.

All formulas are explainable and consume profiling metrics only. No AI or randomness.

Weights (must sum to 1.0):
    completeness  = 0.30   (missing values)
    uniqueness    = 0.20   (duplicate rows)
    validity      = 0.20   (invalid / impossible values)
    consistency   = 0.15   (formatting inconsistencies)
    type_accuracy = 0.15   (columns with mixed or incorrect types)

Status labels:
    90–100  → Excellent
    75–89   → Good
    60–74   → Fair
    40–59   → Poor
     0–39   → Critical
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any

WEIGHT_COMPLETENESS = 0.30
WEIGHT_UNIQUENESS = 0.20
WEIGHT_VALIDITY = 0.20
WEIGHT_CONSISTENCY = 0.15
WEIGHT_TYPE_ACCURACY = 0.15


@dataclass(frozen=True)
class SubScore:
    score: float
    weight: float
    weighted_score: float
    details: str


@dataclass(frozen=True)
class QualityResult:
    final_score: float
    status: str
    completeness: SubScore
    uniqueness: SubScore
    validity: SubScore
    consistency: SubScore
    type_accuracy: SubScore
    issues: list[dict[str, Any]]
    recommendations: list[dict[str, Any]]


def _clamp_score(score: float) -> float:
    return max(0.0, min(100.0, round(score, 1)))


def _status_label(score: float) -> str:
    if score >= 90:
        return "Excellent"
    elif score >= 75:
        return "Good"
    elif score >= 60:
        return "Fair"
    elif score >= 40:
        return "Poor"
    else:
        return "Critical"


def _completeness_score(profile: dict[str, Any]) -> SubScore:
    total = max(profile.get("total_cell_count", 1), 1)
    missing = profile.get("missing_cell_count", total)
    pct = (missing / total) * 100
    score = _clamp_score(100 - pct)

    return SubScore(
        score=score,
        weight=WEIGHT_COMPLETENESS,
        weighted_score=_clamp_score(score * WEIGHT_COMPLETENESS),
        details=f"{missing} missing cells out of {total} ({pct:.1f}%).",
    )


def _uniqueness_score(profile: dict[str, Any]) -> SubScore:
    rows = max(profile.get("row_count", 1), 1)
    dupes = profile.get("duplicate_count", 0)
    pct = (dupes / rows) * 100
    score = _clamp_score(100 - pct)

    return SubScore(
        score=score,
        weight=WEIGHT_UNIQUENESS,
        weighted_score=_clamp_score(score * WEIGHT_UNIQUENESS),
        details=f"{dupes} duplicate rows out of {rows} ({pct:.1f}%).",
    )


def _validity_score(profile: dict[str, Any]) -> SubScore:
    total = max(profile.get("total_cell_count", 1), 1)
    invalid = profile.get("invalid_cell_count", 0)
    pct = (invalid / total) * 100
    score = _clamp_score(100 - pct)

    return SubScore(
        score=score,
        weight=WEIGHT_VALIDITY,
        weighted_score=_clamp_score(score * WEIGHT_VALIDITY),
        details=f"{invalid} invalid cells out of {total} ({pct:.1f}%).",
    )


def _consistency_score(profile: dict[str, Any]) -> SubScore:
    col_count = max(profile.get("column_count", 1), 1)
    inconsistent_count = profile.get("inconsistent_cell_count", 0)
    penalty_pct = min(100.0, (inconsistent_count / max(profile.get("total_cell_count", 1), 1)) * 100)
    score = _clamp_score(100 - penalty_pct)

    return SubScore(
        score=score,
        weight=WEIGHT_CONSISTENCY,
        weighted_score=_clamp_score(score * WEIGHT_CONSISTENCY),
        details=f"{inconsistent_count} inconsistently formatted cells across {col_count} columns.",
    )


def _type_accuracy_score(profile: dict[str, Any]) -> SubScore:
    col_count = max(profile.get("column_count", 1), 1)
    mismatch_count = profile.get("type_mismatch_cell_count", 0)
    penalty_pct = min(100.0, (mismatch_count / max(profile.get("total_cell_count", 1), 1)) * 100)
    score = _clamp_score(100 - penalty_pct)

    return SubScore(
        score=score,
        weight=WEIGHT_TYPE_ACCURACY,
        weighted_score=_clamp_score(score * WEIGHT_TYPE_ACCURACY),
        details=f"{mismatch_count} type-mismatched cells across {col_count} columns.",
    )


def _detect_columns_where(profile: dict[str, Any], predicate) -> list[str]:
    columns = profile.get("columns", [])
    return [c["name"] for c in columns if predicate(c)]


def _generate_issues(profile: dict[str, Any], subscores: dict[str, SubScore]) -> list[dict[str, Any]]:
    issues = []
    total_rows = profile.get("row_count", 0)
    total_columns = profile.get("column_count", 0)

    # Missing values
    missing = profile.get("missing_cell_count", 0)
    if missing > 0:
        issues.append({
            "code": "missing_values",
            "severity": "warning",
            "message": f"Dataset contains {missing} missing values across {total_columns} columns.",
            "column": None,
            "count": missing,
        })
    else:
        issues.append({
            "code": "missing_values",
            "severity": "info",
            "message": "No missing values detected.",
            "column": None,
            "count": 0,
        })

    # Duplicates
    dupes = profile.get("duplicate_count", 0)
    if dupes > 0:
        issues.append({
            "code": "duplicate_rows",
            "severity": "warning" if dupes > total_rows * 0.05 else "info",
            "message": f"{dupes} duplicate rows detected out of {total_rows}.",
            "column": None,
            "count": dupes,
        })

    # Invalid values
    invalid = profile.get("invalid_cell_count", 0)
    if invalid > 0:
        issues.append({
            "code": "invalid_values",
            "severity": "warning",
            "message": f"{invalid} cells contain invalid or impossible values.",
            "column": None,
            "count": invalid,
        })

    # Inconsistency
    inconsistent = profile.get("inconsistent_cell_count", 0)
    if inconsistent > 0:
        issues.append({
            "code": "inconsistent_formatting",
            "severity": "info",
            "message": f"{inconsistent} cells have inconsistent formatting (whitespace, casing, date formats).",
            "column": None,
            "count": inconsistent,
        })

    # Type issues
    type_mismatch = profile.get("type_mismatch_cell_count", 0)
    if type_mismatch > 0:
        issues.append({
            "code": "type_issues",
            "severity": "info",
            "message": f"{type_mismatch} cells have values that don't match their column's detected type.",
            "column": None,
            "count": type_mismatch,
        })

    return issues


def _generate_recommendations(subscores: dict[str, SubScore]) -> list[dict[str, Any]]:
    recommendations = []

    if subscores["completeness"].score < 80:
        recommendations.append({
            "code": "impute_or_drop_missing",
            "message": "Some columns contain missing values that may need imputation or removal.",
            "recommended_action": "Review columns with high missing percentages and apply imputation or drop.",
        })

    if subscores["uniqueness"].score < 80:
        recommendations.append({
            "code": "review_duplicates",
            "message": "Duplicate rows were detected and should be reviewed.",
            "recommended_action": "Remove exact duplicate rows to improve data uniqueness.",
        })

    if subscores["validity"].score < 80:
        recommendations.append({
            "code": "correct_invalid_values",
            "message": "Invalid or impossible values were found in some columns.",
            "recommended_action": "Correct negative values, out-of-range percentages, and malformed dates.",
        })

    if subscores["consistency"].score < 80:
        recommendations.append({
            "code": "standardize_formats",
            "message": "Some columns show inconsistent formatting.",
            "recommended_action": "Trim whitespace, normalize text casing, and standardize date formats.",
        })

    if subscores["type_accuracy"].score < 80:
        recommendations.append({
            "code": "correct_data_types",
            "message": "Some columns contain values that don't match their detected type.",
            "recommended_action": "Convert numeric and date columns to proper types after fixing non-conforming values.",
        })

    if not recommendations:
        recommendations.append({
            "code": "no_issues",
            "message": "Dataset quality is high. No immediate cleaning actions needed.",
            "recommended_action": "Proceed to analysis and visualization.",
        })

    return recommendations


def calculate_quality_score(profile: dict[str, Any]) -> QualityResult:
    """Calculate a deterministic 0-100 quality score from profiling metrics.

    The score combines five rule-based sub-scores with fixed weights.
    Each sub-score and the final score are clamped to [0, 100].
    """
    completeness = _completeness_score(profile)
    uniqueness = _uniqueness_score(profile)
    validity = _validity_score(profile)
    consistency = _consistency_score(profile)
    type_accuracy = _type_accuracy_score(profile)

    final = completeness.weighted_score + uniqueness.weighted_score + \
            validity.weighted_score + consistency.weighted_score + \
            type_accuracy.weighted_score

    final = _clamp_score(final)
    subscores = {
        "completeness": completeness,
        "uniqueness": uniqueness,
        "validity": validity,
        "consistency": consistency,
        "type_accuracy": type_accuracy,
    }

    return QualityResult(
        final_score=final,
        status=_status_label(final),
        completeness=completeness,
        uniqueness=uniqueness,
        validity=validity,
        consistency=consistency,
        type_accuracy=type_accuracy,
        issues=_generate_issues(profile, subscores),
        recommendations=_generate_recommendations(subscores),
    )
