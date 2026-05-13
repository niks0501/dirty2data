"""Deterministic CSV/XLS/XLSX parsing for the data processing service."""

from io import BytesIO
from pathlib import Path
from typing import BinaryIO

import pandas as pd
from fastapi import HTTPException, UploadFile, status

_SUPPORTED_EXTENSIONS = frozenset({".csv", ".xls", ".xlsx"})
_MAX_FILE_SIZE_BYTES = 100 * 1024 * 1024  # 100 MB


def _validate_extension(filename: str | None) -> str:
    """Validate the file extension and return the lowercased extension.

    Raises HTTPException 415 for unsupported types.
    """
    if not filename:
        raise HTTPException(status_code=status.HTTP_415_UNSUPPORTED_MEDIA_TYPE,
                             detail="Upload is missing a filename.")

    ext = Path(filename).suffix.lower()

    if ext not in _SUPPORTED_EXTENSIONS:
        raise HTTPException(
            status_code=status.HTTP_415_UNSUPPORTED_MEDIA_TYPE,
            detail=f"Unsupported file type '{ext or filename}'. "
                   f"Accepted: .csv, .xls, .xlsx.",
        )

    return ext


def _check_empty(content_length: int) -> None:
    """Reject zero-byte uploads before attempting to parse."""
    if content_length == 0:
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
                             detail="Uploaded file is empty (zero bytes). Cannot profile.")


async def parse_upload_to_dataframe(file: UploadFile) -> tuple[pd.DataFrame, str]:
    """Parse an uploaded file into a deterministic Pandas DataFrame.

    Returns a (DataFrame, extension) tuple.
    Raises structured HTTPException on validation or parser errors.
    """
    await file.seek(0)
    content = await file.read()
    ext = _validate_extension(file.filename)
    _check_empty(len(content))

    stream = BytesIO(content)

    try:
        if ext == ".csv":
            df = pd.read_csv(stream, dtype=object, keep_default_na=True, na_filter=True)
        else:
            df = pd.read_excel(stream, dtype=object, engine=_excel_engine(ext))
    except pd.errors.ParserError as exc:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail=f"Could not parse file as {ext.upper()}: {exc}",
        ) from exc
    except ValueError as exc:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail=f"Invalid file content for {ext.upper()} parser: {exc}",
        ) from exc
    except Exception as exc:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail=f"Unexpected parser error for {ext.upper()}: {exc}",
        ) from exc

    if df.empty or df.shape[0] == 0:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="Dataset contains zero rows. Cannot compute quality score.",
        )

    if df.shape[1] == 0:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="Dataset contains zero columns. Cannot compute quality score.",
        )

    return df, ext


def parse_filepath_to_dataframe(filepath: str) -> tuple[pd.DataFrame, str]:
    """Parse a server-side file path (used internally or for testing).

    Returns a (DataFrame, extension) tuple.
    """
    path = Path(filepath)
    ext = path.suffix.lower()

    if ext == ".csv":
        df = pd.read_csv(filepath, dtype=object, keep_default_na=True, na_filter=True)
    else:
        df = pd.read_excel(filepath, dtype=object, engine=_excel_engine(ext))

    return df, ext


def _excel_engine(ext: str) -> str:
    """Select appropriate pandas Excel engine per extension."""
    eng = {"xlsx": "openpyxl", "xls": "xlrd"}.get(ext.lstrip("."), "openpyxl")

    return eng
