"""Deterministic dataset profiling consumed by the quality scoring service."""

from __future__ import annotations

import re
from datetime import datetime

import pandas as pd

# Column name patterns that suggest the column should contain non-negative values
_NON_NEGATIVE_PATTERNS: tuple[re.Pattern[str], ...] = tuple(
    re.compile(p, re.IGNORECASE)
    for p in [
        r"\bage\b",
        r"\bquantity\b",
        r"\bqty\b",
        r"\bprice\b",
        r"\bamount\b",
        r"\btotal\b",
        r"\bsales\b",
        r"\bincome\b",
        r"\brevenue\b",
        r"\bcost\b",
        r"\bcount\b",
        r"\bnumber\b",
        r"\bweight\b",
        r"\bheight\b",
        r"\blength\b",
        r"\bwidth\b",
        r"\bduration\b",
    ]
)

# Column name patterns that suggest the column should be a percentage (0-100)
_PERCENTAGE_PATTERNS: tuple[re.Pattern[str], ...] = tuple(
    re.compile(p, re.IGNORECASE)
    for p in [
        r"\bpercent\b",
        r"\bpct\b",
        r"\brate\b",
        r"\bratio\b",
        r"\bpercentage\b",
        r"\bproportion\b",
    ]
)

_COMMON_DATE_FORMATS = [
    "%Y-%m-%d",
    "%m/%d/%Y",
    "%d/%m/%Y",
    "%Y/%m/%d",
    "%d-%m-%Y",
    "%b %d, %Y",
    "%d %B %Y",
    "%B %d, %Y",
    "%Y%m%d",
]


def _is_numeric_value(val: str) -> bool:
    """Check if a string value can be interpreted as a number."""
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return False

    try:
        float(str(val))

        return True
    except (ValueError, TypeError):
        return False


def _is_date_value(val: str) -> bool:
    """Check if a string value can be parsed as a date."""
    if val is None or (isinstance(val, float) and pd.isna(val)):
        return False
    if not isinstance(val, str) or not val.strip():
        return False

    for fmt in _COMMON_DATE_FORMATS:
        try:
            datetime.strptime(val.strip(), fmt)

            return True
        except ValueError:
            continue

    return False


def _detect_column_type(series: pd.Series) -> str:
    """Detect the logical type of a column: numeric, date, or text."""
    non_null = series.dropna()
    if len(non_null) == 0:
        return "text"

    sample = non_null.head(50).astype(str).tolist()
    numeric_count = sum(1 for v in sample if _is_numeric_value(str(v)))
    date_count = sum(1 for v in sample if _is_date_value(str(v)))

    if numeric_count == len(sample):
        return "numeric"
    elif date_count > len(sample) * 0.7:
        return "date"
    else:
        return "text"


def _count_invalid_cells(series: pd.Series, col_name: str, col_type: str) -> int:
    """Count cells with invalid values in a column.

    Rules:
    - Non-negative columns (age, price, etc.): negative numbers are invalid.
    - Percentage columns: values outside 0-100 are invalid.
    - Date columns: values that fail all date format parsing are invalid.
    """
    count = 0
    is_non_negative = any(p.search(col_name) for p in _NON_NEGATIVE_PATTERNS)
    is_percentage = any(p.search(col_name) for p in _PERCENTAGE_PATTERNS)

    for val in series.dropna():
        str_val = str(val)

        if col_type == "numeric" or is_non_negative or is_percentage:
            try:
                num = float(str_val)

                if is_non_negative and num < 0:
                    count += 1
                elif is_percentage and (num < 0 or num > 100):
                    count += 1
            except ValueError:
                pass  # type mismatch is counted separately

        if col_type == "date" and not _is_date_value(str_val):
            count += 1

    return count


def _count_inconsistent_cells(series: pd.Series, col_type: str) -> int:
    """Count cells with inconsistent formatting.

    - Text columns: leading/trailing whitespace, mixed casing
    - Date columns: varied date format patterns
    """
    count = 0
    non_null = series.dropna().astype(str)

    if col_type in ("text", "date") and len(non_null) > 0:
        # Leading or trailing whitespace
        whitespace_mask = non_null.str.strip() != non_null
        count += int(whitespace_mask.sum())

        # Mixed casing in text columns (both upper and lower versions exist)
        if col_type == "text" and len(non_null) > 1:
            samples = [s for s in non_null.head(500) if any(c.isalpha() for c in str(s))]
            if len(samples) >= 2:
                has_upper = any(s.isupper() for s in samples if str(s).isalpha())
                has_lower = any(s.islower() for s in samples if str(s).isalpha())
                mixed_caps = any(
                    not str(s).isupper() and not str(s).islower()
                    for s in samples
                    if str(s).isalpha()
                )
                if has_upper and has_lower and mixed_caps:
                    count += 1  # flag the column, not every cell

    return count


def _count_type_mismatch_cells(series: pd.Series, col_type: str) -> int:
    """Count cells where the value type mismatches the column's detected type.

    - Numeric columns: values that can't be parsed as numbers
    - Date columns: values that can't be parsed as dates
    - Text columns: not applicable (everything is valid text)
    """
    if col_type not in ("numeric", "date"):
        return 0

    count = 0

    for val in series.dropna():
        str_val = str(val)

        if col_type == "numeric":
            try:
                float(str_val)
            except ValueError:
                count += 1
        elif col_type == "date":
            if not _is_date_value(str_val):
                count += 1

    return count


def profile_dataframe(df: pd.DataFrame) -> dict:
    """Profile a DataFrame and return structured metrics for quality scoring.

    Returns a dict matching the DatasetProfileMetrics and ColumnProfileMetric schemas.
    """
    row_count = len(df)
    column_count = len(df.columns)
    total_cells = row_count * column_count
    duplicate_count = int(df.duplicated().sum())
    missing_cell_count = int(df.isna().sum().sum())

    columns = []
    invalid_total = 0
    inconsistent_total = 0
    type_mismatch_total = 0

    for col_name in df.columns:
        series = df[col_name]
        col_type = _detect_column_type(series)
        missing = int(series.isna().sum())
        invalid = _count_invalid_cells(series, str(col_name), col_type)
        inconsistent = _count_inconsistent_cells(series, col_type)
        mismatch = _count_type_mismatch_cells(series, col_type)

        invalid_total += invalid
        inconsistent_total += inconsistent
        type_mismatch_total += mismatch

        columns.append({
            "name": str(col_name),
            "detected_type": col_type,
            "missing_count": missing,
            "missing_percentage": round((missing / max(row_count, 1)) * 100, 2),
            "unique_count": int(series.nunique(dropna=True)),
            "invalid_count": invalid,
            "inconsistent_count": inconsistent,
            "type_mismatch_count": mismatch,
        })

    return {
        "row_count": row_count,
        "column_count": column_count,
        "duplicate_count": duplicate_count,
        "total_cell_count": total_cells,
        "missing_cell_count": missing_cell_count,
        "invalid_cell_count": invalid_total,
        "inconsistent_cell_count": inconsistent_total,
        "type_mismatch_cell_count": type_mismatch_total,
        "columns": columns,
    }
