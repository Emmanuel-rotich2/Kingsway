"""Bounded offline OCR for uploaded document images.

The Python edge never sends school documents to an external OCR service. RapidOCR
runs locally from pinned ONNX models, so shared hosting does not need a system
``tesseract`` binary. Tesseract remains an optional fallback for hosts that
already provide it.
"""

from __future__ import annotations

import io
import os
import re
from functools import lru_cache
from typing import Any

MAX_IMAGE_EDGE = 4000
MIN_TEXT_SCORE = 0.5


class OcrUnavailableError(RuntimeError):
    """No local OCR engine could read the supplied image."""


@lru_cache(maxsize=1)
def _rapid_engine() -> Any:
    try:
        from rapidocr_onnxruntime import RapidOCR
    except ImportError:
        from rapidocr import RapidOCR

    return RapidOCR()


def _rapid_text(image: Any) -> str:
    """Run the pinned, offline ONNX OCR engine and join its text lines."""
    try:
        import numpy as np

        result = _rapid_engine()(np.array(image))
    except Exception:  # noqa: BLE001 - offline OCR is best-effort before the manual fallback
        return ""

    rows: Any
    if isinstance(result, tuple):
        rows = result[0] if result else []
    elif hasattr(result, "txts"):
        rows = getattr(result, "txts", []) or []
    elif isinstance(result, dict):
        rows = result.get("txts") or result.get("result") or []
    else:
        rows = result or []

    lines: list[str] = []
    for row in rows:
        text = ""
        score = 1.0
        if isinstance(row, str):
            text = row
        elif isinstance(row, (list, tuple)) and len(row) >= 2:
            text = str(row[1] or "")
            if len(row) >= 3:
                try:
                    score = float(row[2])
                except (TypeError, ValueError):
                    score = 0.0
        text = text.strip()
        if text and score >= MIN_TEXT_SCORE:
            lines.append(text)

    return "\n".join(lines)


def _tesseract_text(image: Any) -> str:
    """Optional fallback for hosts that already provide the tesseract binary."""
    language = os.environ.get("KINGSWAY_OCR_LANG", "eng").strip()
    if not re.fullmatch(r"[A-Za-z_+]{2,32}", language):
        language = "eng"

    try:
        import pytesseract

        return str(
            pytesseract.image_to_string(
                image, lang=language, config="--psm 6", timeout=15
            )
            or ""
        ).strip()
    except Exception as error:
        raise OcrUnavailableError(
            "No local OCR engine could read this image. The document must be "
            "reviewed by a school administrator."
        ) from error


def ocr_image(image: Any) -> str:
    """Extract readable text from one bounded PIL image."""
    from PIL import Image, ImageOps

    prepared = ImageOps.exif_transpose(image).convert("RGB")
    if max(prepared.size) > MAX_IMAGE_EDGE:
        prepared.thumbnail((MAX_IMAGE_EDGE, MAX_IMAGE_EDGE), Image.Resampling.LANCZOS)

    text = _rapid_text(prepared).strip()
    if text:
        return text

    return _tesseract_text(prepared)


def ocr_bytes(data: bytes) -> str:
    """Extract readable text from image bytes without trusting the filename."""
    from PIL import Image

    try:
        with Image.open(io.BytesIO(data)) as source:
            return ocr_image(source)
    except OcrUnavailableError:
        raise
    except Exception as error:
        raise OcrUnavailableError(
            "The image could not be decoded for OCR. The document must be "
            "reviewed by a school administrator."
        ) from error
