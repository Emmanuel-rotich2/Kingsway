"""Real, bounded extraction of uploaded documents using the pinned Python stack.

PHP owns the upload, the authorization and the staged bytes. This module only
ever sees a base64 payload handed to it by an authorized PHP edge, and it never
writes to the database or calls an external provider. Every parser is bounded
so one hostile document cannot exhaust the Passenger worker.

Supported inputs: PDF (text, then bounded OCR for scans), CSV/TSV, XLS/XLSX,
ODS, DOCX, plain text, and JPG/PNG/WEBP images (OCR). Legacy binary .doc has no
safe parser in the pinned stack and is reported for staff follow-up rather than
silently faked.
"""

from __future__ import annotations

import base64
import csv
import io
import re
import zipfile
from typing import Any

from defusedxml import ElementTree as SafeET

from .document_verification import verify_document
from .exam_documents import (
    MAX_ROWS,
    DocumentPreviewError,
    _bounded_grid,
    _ods_grid,
    _pdf_grid,
    _suggest_headers,
    _xlsx_grid,
)

MAX_FILE_BYTES = 8 * 1024 * 1024
MAX_TEXT_BYTES = 4 * 1024 * 1024
TABULAR = {"csv", "tsv", "xlsx", "xls", "ods"}
IMAGES = {"jpg", "jpeg", "png", "webp"}

DOCUMENT_FIELD_ALIASES: dict[str, tuple[str, ...]] = {
    "name": (
        "name",
        "full name",
        "learner",
        "learner name",
        "student",
        "student name",
        "patient",
    ),
    "dob": ("date of birth", "dob", "born"),
    "gender": ("gender", "sex"),
    "parent": ("parent", "guardian"),
    "condition": ("condition", "diagnosis"),
    "medication": ("medication", "drug"),
    "dose": ("dose", "dosage"),
}

DOCUMENT_HINTS: dict[str, tuple[str, ...]] = {
    "birth_cert": ("birth certificate", "date of birth", "born", "birth"),
    "medical_form": (
        "medical",
        "patient",
        "condition",
        "diagnosis",
        "medication",
        "vaccination",
    ),
    "transfer_letter": ("transfer letter", "transfer", "leaving certificate"),
    "id_doc": ("identification", "national id", "id number", "admission no"),
    "invoice": ("invoice", "receipt", "amount", "bank", "payment"),
    "timetable": ("timetable", "time table", "exam schedule"),
    "results": ("results", "marks", "score", "grade", "candidate"),
}


class ExtractionError(ValueError):
    """A bounded, user-safe extraction failure."""


def _safe_extension(filename: str) -> str:
    name = str(filename or "").rsplit("/", 1)[-1].rsplit("\\", 1)[-1]
    return name.rsplit(".", 1)[-1].lower() if "." in name else ""


def decode_document(payload: dict) -> tuple[bytes, str]:
    encoded = payload.get("content_base64")
    if not isinstance(encoded, str) or not encoded:
        raise ExtractionError("content_base64 is required")
    if len(encoded) > ((MAX_FILE_BYTES + 2) // 3) * 4 + 16:
        raise ExtractionError(
            f"document exceeds the {MAX_FILE_BYTES // (1024 * 1024)} MB limit"
        )
    try:
        data = base64.b64decode(encoded, validate=True)
    except (ValueError, base64.binascii.Error) as error:
        raise ExtractionError("document encoding is invalid") from error
    if not data or len(data) > MAX_FILE_BYTES:
        raise ExtractionError("document is empty or exceeds the size limit")
    return data, _safe_extension(str(payload.get("filename") or ""))


def _delimited_grid(data: bytes, delimiter: str) -> list[list[str]]:
    try:
        decoded = data.decode("utf-8-sig")
    except UnicodeDecodeError as error:
        raise ExtractionError("CSV must be UTF-8 encoded") from error
    sample = decoded[:4096]
    try:
        dialect = csv.Sniffer().sniff(sample, delimiters=delimiter)
    except csv.Error:
        dialect = csv.excel
        dialect.delimiter = delimiter
    return _bounded_grid(
        list(csv.reader(io.StringIO(decoded), dialect))[: MAX_ROWS + 1]
    )


def _text_grid(data: bytes) -> list[list[str]]:
    text = data.decode("utf-8-sig", errors="replace")
    if len(text.encode("utf-8")) > MAX_TEXT_BYTES:
        raise ExtractionError("text document exceeds the extraction limit")
    lines = [line.rstrip()[:1000] for line in text.splitlines() if line.strip()]
    if not lines:
        raise ExtractionError("text document contains no readable lines")
    return _bounded_grid([[line] for line in lines[: MAX_ROWS + 1]])


def _xls_grid(data: bytes) -> list[list[str]]:
    """Legacy binary Excel is OLE2, not a ZIP, so it needs its own guarded path."""
    try:
        from python_calamine import CalamineWorkbook

        with CalamineWorkbook.from_filelike(io.BytesIO(data)) as workbook:
            if not workbook.sheet_names:
                raise ExtractionError("XLS workbook contains no sheets")
            sheet = workbook.get_sheet_by_index(0)
            rows = []
            for row in sheet.iter_rows():
                rows.append(list(row))
                if len(rows) > MAX_ROWS + 1:
                    break
        return _bounded_grid(rows)
    except ExtractionError:
        raise
    except Exception as error:
        raise ExtractionError("XLS workbook could not be read") from error


def _docx_grid(data: bytes) -> list[list[str]]:
    try:
        with zipfile.ZipFile(io.BytesIO(data)) as archive:
            xml = archive.read("word/document.xml")
    except (zipfile.BadZipFile, KeyError) as error:
        raise ExtractionError("DOCX document is damaged or has no body") from error
    try:
        root = SafeET.fromstring(xml)
    except Exception as error:
        raise ExtractionError("DOCX document XML could not be parsed") from error

    namespace = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
    paragraphs = [
        "".join(node.text or "" for node in paragraph.iter(namespace + "t")).strip()
        for paragraph in root.iter(namespace + "p")
    ]
    rows: list[list[str]] = []
    for table in root.iter(namespace + "tbl"):
        for row in table.iter(namespace + "tr"):
            cells = [
                "".join(node.text or "" for node in cell.iter(namespace + "t")).strip()
                for cell in row.iter(namespace + "tc")
            ]
            if any(cells):
                rows.append(cells)
    lines = [[value] for value in paragraphs if value]
    if rows:
        lines = [*rows, *lines]
    if not lines:
        raise ExtractionError("DOCX document contains no readable text")
    return _bounded_grid(lines[: MAX_ROWS + 1])


def _image_text(data: bytes) -> str:
    try:
        from .ocr import OcrUnavailableError, ocr_bytes

        return ocr_bytes(data)
    except OcrUnavailableError as error:
        raise ExtractionError(str(error)) from error


FIELD_PATTERNS: dict[str, tuple[str, str]] = {
    "name": (
        r"(?:learner|student|pupil|name|patient)\s*[:\-]\s*([A-Z][A-Za-z' -]{2,60})",
        "generic",
    ),
    "dob": (
        r"(?:date of birth|dob|born)\s*[:\-]\s*(\d{4}-\d{2}-\d{2}|\d{1,2}[./-]\d{1,2}[./-]\d{2,4})",
        "generic",
    ),
    "gender": (r"(?:gender|sex)\s*[:\-]\s*(male|female|M|F)\b", "generic"),
    "parent": (r"(?:parent|guardian)\s*[:\-]\s*([A-Z][A-Za-z' -]{2,60})", "generic"),
    "condition": (
        r"(?:condition|diagnosis)\s*[:\-]\s*([A-Za-z][A-Za-z -]{2,80})",
        "health",
    ),
    "medication": (
        r"(?:medication|drug)\s*[:\-]\s*([A-Za-z][A-Za-z0-9 -]{2,60})",
        "health",
    ),
    "dose": (r"(?:dose|dosage)\s*[:\-]\s*([A-Za-z0-9 -]{2,60})", "health"),
}


def extract_fields(text: str, profile: str) -> dict[str, str]:
    """Return only fields actually found in the document; never invent values."""
    fields: dict[str, str] = {}
    for key, (pattern, owner) in FIELD_PATTERNS.items():
        if owner != "generic" and owner != profile:
            continue
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            fields[key] = match.group(1).strip()[:200]
    return fields


def _suggest_document_headers(headers: list[str]) -> dict[str, str]:
    mapping = _suggest_headers(headers)
    for header in headers:
        normalized = re.sub(r"[^a-z0-9 ]", " ", header.lower()).strip()
        for field, aliases in DOCUMENT_FIELD_ALIASES.items():
            if field not in mapping and normalized in aliases:
                mapping[field] = header
                break
    return mapping


def classify(filename: str, text: str) -> dict[str, Any]:
    """Deterministic keyword classification; content always outranks the name."""
    content = text[:8000].lower()
    scores = {
        kind: sum(1 for hint in hints if hint in content)
        for kind, hints in DOCUMENT_HINTS.items()
    }
    detected = max(scores, key=lambda key: scores[key])
    if scores[detected] > 0:
        total = sum(scores.values())
        confidence = round(
            min(0.95, 0.55 + (scores[detected] / max(1, total)) * 0.4), 2
        )
        return {"detected_type": detected, "confidence": confidence}

    # A filename is only weak context, never proof: this is why verification
    # uses the extracted text alone and deliberately ignores ``filename``.
    name_scores = {
        kind: sum(1 for hint in hints if hint in str(filename or "").lower())
        for kind, hints in DOCUMENT_HINTS.items()
    }
    named = max(name_scores, key=lambda key: name_scores[key])
    if name_scores[named] > 0:
        return {"detected_type": named, "confidence": 0.25}
    return {"detected_type": "other", "confidence": 0.0}


def extraction_confidence(method: str, fields: dict[str, str]) -> float:
    """A conservative quality estimate from the parser actually used."""
    base = {
        "text": 0.97,
        "delimited": 0.97,
        "spreadsheet": 0.94,
        "docx-xml": 0.92,
        "pdf-text-or-ocr": 0.84,
        "image-ocr": 0.72,
    }.get(method, 0.5)
    return round(min(0.98, base + min(0.05, len(fields) * 0.01)), 2)


def extract(payload: dict) -> dict[str, Any]:
    data, extension = decode_document(payload)
    profile = str(payload.get("profile") or "").strip().lower()
    filename = str(payload.get("filename") or "document").rsplit("/", 1)[-1][:120]
    expected_type = (
        str(payload.get("expected_type") or payload.get("document_type") or "")
        .strip()
        .lower()
    )

    method = ""
    grid: list[list[str]] = []
    text = ""
    try:
        if extension in ("txt", "md", "text"):
            method = "text"
            grid = _text_grid(data)
        elif extension in ("csv", "tsv"):
            method = "delimited"
            grid = _delimited_grid(data, "\t" if extension == "tsv" else ",")
        elif extension in ("xlsx", "xls", "ods"):
            method = "spreadsheet"
            if extension == "xls":
                grid = _xls_grid(data)
            elif extension == "xlsx":
                grid = _xlsx_grid(data)
            else:
                grid = _ods_grid(data)
        elif extension == "pdf":
            method = "pdf-text-or-ocr"
            grid = _pdf_grid(data)
        elif extension == "docx":
            method = "docx-xml"
            grid = _docx_grid(data)
        elif extension in IMAGES:
            method = "image-ocr"
            text = _image_text(data)
            lines = [line.strip() for line in text.splitlines() if line.strip()]
            grid = (
                _bounded_grid([[line[:1000]] for line in lines[: MAX_ROWS + 1]])
                if lines
                else []
            )
        elif extension == "doc":
            raise ExtractionError(
                "legacy .doc cannot be parsed safely; save it as .docx or PDF"
            )
        else:
            raise ExtractionError(
                f"unsupported document type: .{extension or 'unknown'}"
            )
    except DocumentPreviewError as error:
        raise ExtractionError(str(error)) from error

    if not grid:
        raise ExtractionError("no readable content was found in the document")
    if not text:
        text = "\n".join(" | ".join(row) for row in grid)
    if len(text.encode("utf-8")) > MAX_TEXT_BYTES:
        raise ExtractionError("extracted text exceeds the limit")

    headers = grid[0]
    rows = grid[1:]
    fields = extract_fields(text, profile)
    suggested_mapping = _suggest_document_headers(headers)
    # Tabular uploads rarely use "field: value" prose. Map the first data row
    # through the detected headers so marks, names and dates are still offered
    # to staff for confirmation instead of being silently discarded.
    if rows:
        for field, header in suggested_mapping.items():
            try:
                column = headers.index(header)
            except ValueError:
                continue
            value = rows[0][column].strip() if column < len(rows[0]) else ""
            if value:
                fields[field] = value[:200]
    detected = classify(filename, text)
    verification = verify_document(
        expected_type,
        text,
        extension=extension,
        filename=filename,
    )
    return {
        "filename": filename,
        "extension": extension,
        "extraction_method": method,
        "headers": headers,
        "suggested_mapping": suggested_mapping,
        "rows": rows[:MAX_ROWS],
        "row_count": len(rows),
        "text": text[:MAX_TEXT_BYTES],
        "fields": fields,
        "classification": detected,
        "verification": verification,
        "confidence": extraction_confidence(method, fields),
        "preview_only": True,
        "requires_staff_confirmation": True,
    }
