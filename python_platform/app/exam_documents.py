"""Local, review-only extraction for ministry exam documents.

No provider is called here. Extraction returns a bounded preview only; the PHP
academic service remains responsible for matching and validating every row
before any timetable or learner result is saved.
"""

from __future__ import annotations

import base64
import csv
import io
import os
import re
import zipfile
from typing import Any

MAX_FILE_BYTES = 4 * 1024 * 1024
MAX_ROWS = 5000
MAX_COLUMNS = 40
MAX_CELL = 1000
MAX_PDF_PAGES = 30
MAX_PDF_OCR_PAGES = 4


class DocumentPreviewError(ValueError):
    pass


ALIASES = {
    "class": ("class", "grade", "level"),
    "learning_area": ("learning area", "subject", "paper", "learning area/subject"),
    "date": ("date", "exam date", "day"),
    "start_time": ("start", "start time", "time"),
    "end_time": ("end", "end time", "finish"),
    "max_marks": ("max marks", "maximum marks", "out of", "total marks"),
    "venue": ("venue", "room", "centre"),
    "admission_no": ("admission no", "admission number", "adm no", "assessment number", "uan"),
    "learner_name": ("learner", "learner name", "student", "student name", "candidate name"),
    "marks": ("marks", "score", "marks obtained", "total score"),
    "comment": ("comment", "remarks", "teacher comment"),
}


def _cell(value: Any) -> str:
    if value is None:
        return ""
    # Spreadsheet engines commonly decode integer-looking numeric cells as
    # floats. Keep a mark such as 82 as "82" and, critically, preserve 0.
    if isinstance(value, float) and value.is_integer():
        value = int(value)
    return str(value).replace("\x00", "").strip()[:MAX_CELL]


def _bounded_grid(grid: list[list[Any]]) -> list[list[str]]:
    if len(grid) > MAX_ROWS + 1:
        raise DocumentPreviewError("document has too many rows (maximum 5,000 plus header)")
    out = []
    for row in grid:
        if len(row) > MAX_COLUMNS:
            raise DocumentPreviewError("document has too many columns (maximum 40)")
        values = [_cell(value) for value in row]
        while values and not values[-1]:
            values.pop()
        if any(values):
            out.append(values)
    if not out:
        raise DocumentPreviewError("no readable table rows were found")
    width = max(map(len, out))
    return [row + [""] * (width - len(row)) for row in out]


def _xlsx_grid(data: bytes) -> list[list[str]]:
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        if sum(item.file_size for item in archive.infolist()) > 20 * 1024 * 1024:
            raise DocumentPreviewError("expanded spreadsheet exceeds the 20 MB safety limit")
    try:
        from python_calamine import CalamineWorkbook

        with CalamineWorkbook.from_filelike(io.BytesIO(data)) as workbook:
            if not workbook.sheet_names:
                raise DocumentPreviewError("workbook contains no sheets")
            sheet = workbook.get_sheet_by_index(0)
            rows = []
            for row in sheet.iter_rows():
                rows.append(list(row))
                if len(rows) > MAX_ROWS + 1:
                    break
        return _bounded_grid(rows)
    except DocumentPreviewError:
        raise
    except Exception as error:
        raise DocumentPreviewError("XLSX workbook could not be read") from error


def _ods_grid(data: bytes) -> list[list[str]]:
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        if sum(item.file_size for item in archive.infolist()) > 20 * 1024 * 1024:
            raise DocumentPreviewError("expanded spreadsheet exceeds the 20 MB safety limit")
    try:
        from python_calamine import CalamineWorkbook

        with CalamineWorkbook.from_filelike(io.BytesIO(data)) as workbook:
            if not workbook.sheet_names:
                raise DocumentPreviewError("workbook contains no sheets")
            sheet = workbook.get_sheet_by_index(0)
            rows = []
            for row in sheet.iter_rows():
                rows.append(list(row))
                if len(rows) > MAX_ROWS + 1:
                    break
        return _bounded_grid(rows)
    except DocumentPreviewError:
        raise
    except Exception as error:
        raise DocumentPreviewError("ODS workbook could not be read") from error


def _pdf_grid(data: bytes) -> list[list[str]]:
    document = None
    try:
        import pypdfium2 as pdfium

        document = pdfium.PdfDocument(data)
        page_count = len(document)
        if page_count < 1 or page_count > MAX_PDF_PAGES:
            raise DocumentPreviewError(f"PDF must contain between 1 and {MAX_PDF_PAGES} pages")

        extracted_pages = []
        ocr_pages = 0
        language = os.environ.get("KINGSWAY_OCR_LANG", "eng").strip()
        if not re.fullmatch(r"[A-Za-z_+]{2,32}", language):
            language = "eng"
        for index in range(page_count):
            page = document[index]
            try:
                text_page = page.get_textpage()
                try:
                    text = text_page.get_text_range()
                finally:
                    text_page.close()
                if not text.strip():
                    ocr_pages += 1
                    if ocr_pages > MAX_PDF_OCR_PAGES:
                        raise DocumentPreviewError(
                            f"scanned PDF exceeds the {MAX_PDF_OCR_PAGES}-page OCR limit; split it into smaller files"
                        )
                    image = None
                    try:
                        width, height = page.get_size()
                        scale = 1.5  # 108 dpi: bounded preview resolution.
                        if width <= 0 or height <= 0 or width * height * scale * scale > 20_000_000:
                            raise DocumentPreviewError("PDF page dimensions exceed the OCR safety limit")
                        image = page.render(scale=scale).to_pil().convert("RGB")
                        import pytesseract

                        text = pytesseract.image_to_string(
                            image,
                            lang=language,
                            config="--psm 6",
                            timeout=5,
                        )
                    except DocumentPreviewError:
                        raise
                    except Exception as error:
                        raise DocumentPreviewError(
                            "scanned PDF OCR is unavailable or could not read this page"
                        ) from error
                    finally:
                        if image is not None:
                            image.close()
                extracted_pages.append(text)
                if sum(len(item) for item in extracted_pages) > MAX_ROWS * MAX_CELL:
                    raise DocumentPreviewError("extracted PDF text exceeds the preview size limit")
            finally:
                page.close()
    except DocumentPreviewError:
        raise
    except Exception as error:
        raise DocumentPreviewError("PDF could not be read; upload a valid, unlocked PDF") from error
    finally:
        if document is not None:
            document.close()

    text = "\n".join(extracted_pages)
    if not text.strip():
        raise DocumentPreviewError("PDF contains no readable text")
    lines = [line.rstrip() for line in text.splitlines() if line.strip()]
    # Text geometry is not a reliable substitute for a spreadsheet. Preserve
    # detected column spacing and return ambiguous rows for staff mapping.
    return _bounded_grid([re.split(r"\s{2,}", line.strip()) for line in lines[: MAX_ROWS + 1]])


def _suggest_headers(headers: list[str]) -> dict[str, str]:
    suggestions = {}
    for header in headers:
        normalized = re.sub(r"[^a-z0-9 ]", " ", header.lower()).strip()
        for field, aliases in ALIASES.items():
            if normalized in aliases:
                suggestions[field] = header
                break
    return suggestions


def preview_document(payload: dict) -> dict:
    filename = str(payload.get("filename") or "").lower()
    kind = str(payload.get("document_kind") or "").lower()
    if kind not in ("timetable", "results"):
        raise DocumentPreviewError("document kind must be timetable or results")
    encoded = payload.get("content_base64")
    if not isinstance(encoded, str) or len(encoded) > ((MAX_FILE_BYTES + 2) // 3) * 4 + 8:
        raise DocumentPreviewError("document is missing or exceeds the 4 MB limit")
    try:
        data = base64.b64decode(encoded, validate=True)
    except (ValueError, base64.binascii.Error) as error:
        raise DocumentPreviewError("document encoding is invalid") from error
    if not data or len(data) > MAX_FILE_BYTES:
        raise DocumentPreviewError("document is empty or exceeds the 4 MB limit")
    extension = filename.rsplit(".", 1)[-1] if "." in filename else ""
    try:
        if extension == "csv":
            decoded = data.decode("utf-8-sig")
            try:
                dialect = csv.Sniffer().sniff(decoded[:4096], delimiters=",;\t")
            except csv.Error:
                dialect = csv.excel
            grid = _bounded_grid(list(csv.reader(io.StringIO(decoded), dialect))[: MAX_ROWS + 1])
        elif extension == "xlsx":
            grid = _xlsx_grid(data)
        elif extension == "ods":
            grid = _ods_grid(data)
        elif extension == "pdf":
            extracted = _pdf_grid(data)
            candidate = max(range(len(extracted)), key=lambda index: len(_suggest_headers(extracted[index])), default=0)
            if extracted and _suggest_headers(extracted[candidate]):
                grid = _bounded_grid([extracted[candidate], *extracted[candidate + 1:]])
            else:
                grid = _bounded_grid([["Extracted text"], *[[" | ".join(line)] for line in extracted]])
        else:
            raise DocumentPreviewError("supported files are PDF, CSV, XLSX, and ODS")
    except (zipfile.BadZipFile, UnicodeDecodeError, ValueError) as error:
        if isinstance(error, DocumentPreviewError):
            raise
        raise DocumentPreviewError("document is damaged or could not be parsed") from error
    headers = grid[0]
    rows = grid[1:]
    return {
        "document_kind": kind,
        "filename": filename.rsplit("/", 1)[-1][:120],
        "headers": headers,
        "suggested_mapping": _suggest_headers(headers),
        "rows": rows[:MAX_ROWS],
        "row_count": len(rows),
        "preview_only": True,
        "requires_staff_confirmation": True,
    }
