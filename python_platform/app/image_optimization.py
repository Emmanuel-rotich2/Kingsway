"""Bounded raster optimization shared by generated files and storage sweeps.

The PHP edge already optimizes uploads at the moment they arrive. This module
covers the other half of the lifecycle: images the system generates or that
already sit in storage. It deliberately keeps the original filename, format and
URL unless a caller explicitly asks for a different output format.
"""

from __future__ import annotations

import io
import os
import stat
import tempfile
from pathlib import Path
from typing import Any

from PIL import Image, ImageOps

SUPPORTED_FORMATS = {"JPEG", "PNG", "WEBP"}
EXTENSION_FORMAT = {"jpg": "JPEG", "jpeg": "JPEG", "png": "PNG", "webp": "WEBP"}
FORMAT_MIME = {"JPEG": "image/jpeg", "PNG": "image/png", "WEBP": "image/webp"}
DEFAULT_MAX_EDGE = 1600
DEFAULT_QUALITY = 80
MIN_OPTIMISE_BYTES = 256 * 1024


def optimise_bytes(
    data: bytes,
    *,
    max_edge: int = DEFAULT_MAX_EDGE,
    quality: int = DEFAULT_QUALITY,
    output_format: str | None = None,
    min_bytes: int = MIN_OPTIMISE_BYTES,
) -> tuple[bytes, str] | None:
    """Return optimized bytes and their MIME type, or None when unchanged.

    A file at or below ``min_bytes`` is left alone, as is anything that would
    not actually get smaller. Animated images and unsupported formats are never
    touched. EXIF orientation is baked in so a rotated phone photo stays upright
    after the pixel dimensions are capped.
    """
    if len(data) <= min_bytes:
        return None

    with Image.open(io.BytesIO(data)) as source:
        fmt = (output_format or source.format or "").upper()
        if source.format not in SUPPORTED_FORMATS or fmt not in SUPPORTED_FORMATS:
            return None
        if getattr(source, "n_frames", 1) != 1:
            return None

        image = ImageOps.exif_transpose(source)
        if max(image.size) > max_edge:
            image.thumbnail((max_edge, max_edge), Image.Resampling.LANCZOS)

        if fmt == "JPEG" and image.mode not in ("RGB", "L"):
            background = Image.new("RGB", image.size, "white")
            if image.mode in ("RGBA", "LA"):
                background.paste(image, mask=image.getchannel("A"))
            else:
                background.paste(image)
            image = background

        buffer = io.BytesIO()
        if fmt == "JPEG":
            image.save(buffer, format="JPEG", quality=quality, optimize=True)
        elif fmt == "WEBP":
            image.save(buffer, format="WEBP", quality=quality, method=6)
        else:
            image.save(buffer, format="PNG", optimize=True)
        result = buffer.getvalue()

    if not result or len(result) >= len(data):
        return None
    return result, FORMAT_MIME[fmt]


def optimise_file(
    path: str | Path,
    *,
    max_edge: int = DEFAULT_MAX_EDGE,
    quality: int = DEFAULT_QUALITY,
    min_bytes: int = MIN_OPTIMISE_BYTES,
) -> dict[str, Any] | None:
    """Optimize one image in place, atomically, preserving mode and extension."""
    target = Path(path)
    if not target.is_file():
        return None
    extension = target.suffix.lower().lstrip(".")
    output_format = EXTENSION_FORMAT.get(extension)
    if output_format is None:
        return None

    data = target.read_bytes()
    optimized = optimise_bytes(
        data,
        max_edge=max_edge,
        quality=quality,
        output_format=output_format,
        min_bytes=min_bytes,
    )
    if optimized is None:
        return None

    payload, mime = optimized
    mode = stat.S_IMODE(target.stat().st_mode)
    descriptor, temporary = tempfile.mkstemp(prefix=".kw-opt-", dir=target.parent)
    try:
        with os.fdopen(descriptor, "wb") as handle:
            handle.write(payload)
        os.chmod(temporary, mode)
        os.replace(temporary, target)
    except Exception:
        if os.path.exists(temporary):
            os.unlink(temporary)
        raise

    return {
        "path": str(target),
        "mime_type": mime,
        "before_bytes": len(data),
        "after_bytes": len(payload),
        "saved_bytes": len(data) - len(payload),
    }
