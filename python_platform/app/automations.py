"""Non-AI automations: the general-purpose half of the PHP + Python split.

AI agents live in orchestrator.py. This module is the OTHER reason the
Python platform exists: fast, bounded, deterministic business automations —
artifact generation, batch formatting, aggregation packaging — that PHP is
slow or awkward at. Every automation:

  1. is allowlisted here (the PHP side authorizes FIRST against
     AutomationRegistry; this registry is the second boundary, not the first);
  2. receives ONLY the bounded, already-authorized input from the PHP edge
     (never SQL, never credentials, no direct database access);
  3. returns an artifact envelope; PHP stores, checksums, journals and
     schedules it. This engine never creates an official school record.
"""

from __future__ import annotations

import csv
import json
import re
from io import StringIO
from typing import Any, Callable

from .exam_documents import DocumentPreviewError, preview_document

MAX_ROWS = 5000
MAX_COLUMNS = 40
MAX_CELL_CHARS = 2000


class AutomationError(ValueError):
    """The request was invalid or out of bounds (never retried)."""


# A cell that opens with a spreadsheet formula trigger would execute when the
# artifact is opened in Excel. Prefixing with an apostrophe neutralises it;
# control characters are stripped outright.
_FORMULA_RE = re.compile(r"\A[=\-+@\t]|^[\r\n]", re.MULTILINE)


def _clean_cell(value: Any) -> str:
    if value is None:
        return ""
    text = str(value)[:MAX_CELL_CHARS]
    text = "".join(
        ch
        for ch in text
        if ch == "\t" or ch == "\n" or (ord(ch) >= 32 and ord(ch) != 127)
    )
    if _FORMULA_RE.match(text):
        text = "'" + text
    return text


def _governed_artifact(payload: dict) -> dict:
    fmt = str(payload.get("format", "csv")).lower()
    columns = payload.get("columns") or []
    rows = payload.get("rows") or []
    if not isinstance(columns, list) or not isinstance(rows, list):
        raise AutomationError("columns and rows must be lists")
    if fmt not in ("csv", "json"):
        raise AutomationError("unsupported artifact format")
    if not (1 <= len(columns) <= MAX_COLUMNS):
        raise AutomationError(f"columns count out of bounds (1..{MAX_COLUMNS})")
    if len(rows) > MAX_ROWS:
        raise AutomationError(f"row count out of bounds (max {MAX_ROWS})")

    headers = [
        _clean_cell(
            col.get("label", col.get("key", "")) if isinstance(col, dict) else col
        )
        for col in columns
    ]
    keys = [
        str(col.get("key", "")) if isinstance(col, dict) else str(col)
        for col in columns
    ]
    keys = [k for k in keys if k != ""] if all(k for k in keys) else []

    if fmt == "json":
        records: list[dict] = []
        for row in rows:
            if isinstance(row, dict):
                records.append(
                    {key: _clean_cell(row.get(key)) for key in (keys or row.keys())}
                )
            elif isinstance(row, list):
                records.append(
                    {
                        key: _clean_cell(row[i] if i < len(row) else "")
                        for i, key in enumerate(keys)
                    }
                )
            else:
                raise AutomationError("each row must be a dict or a list")
        return {
            "format": "json",
            "content": json.dumps(records, ensure_ascii=False, indent=1),
            "row_count": len(records),
            "column_count": len(headers),
        }

    output = StringIO()
    writer = csv.writer(output)
    writer.writerow(headers)
    for row in rows:
        if isinstance(row, list):
            cells = [_clean_cell(value) for value in row[:MAX_COLUMNS]]
            cells += [""] * (len(headers) - len(cells))
            writer.writerow(cells[: len(headers)])
        else:
            raise AutomationError("each row must be a list for csv")
    return {
        "format": "csv",
        "content": output.getvalue(),
        "row_count": len(rows),
        "column_count": len(headers),
    }


def _exam_document_preview(payload: dict) -> dict:
    try:
        preview = preview_document(payload)
    except DocumentPreviewError as error:
        raise AutomationError(str(error)) from error
    return {
        "format": "json",
        "content": json.dumps(preview, ensure_ascii=False, separators=(",", ":")),
        "row_count": preview["row_count"],
        "column_count": len(preview["headers"]),
        "preview_only": True,
    }


AUTOMATIONS: dict[str, dict[str, Any]] = {
    "exports.governed_artifact": {
        "name": "Governed artifact export",
        "action_level": "execute",
        "formats": ("csv", "json"),
        "handler": _governed_artifact,
    },
    "academics.exam_document_preview": {
        "name": "Summative exam document preview",
        "action_level": "prepare",
        "formats": ("json",),
        "handler": _exam_document_preview,
    },
}


class AutomationEngine:
    """Allowlisted dispatcher. The PHP edge is the first boundary; this engine
    is a fail-closed second boundary and performs no authorization of its own
    beyond validating the allowlist and bounds."""

    def execute(
        self, automation: str, payload: dict, journal=None, operator_id: int = 0
    ) -> dict:
        definition = AUTOMATIONS.get(automation)
        if definition is None:
            raise AutomationError("unknown automation")
        handler: Callable[[dict], dict] = definition["handler"]
        result = handler(payload if isinstance(payload, dict) else {})
        if not isinstance(result.get("content"), str):
            raise AutomationError("automation produced no content")
        if len(result["content"].encode("utf-8")) > 4 * 1024 * 1024:
            raise AutomationError("automation result exceeds the artifact limit")
        result["automation"] = automation
        result["status"] = "completed"
        if journal is not None:
            journal.write(
                "automation",
                {
                    "type": "automation_completed",
                    "automation": automation,
                    "operator_id": operator_id,
                    "row_count": result.get("row_count", 0),
                    "format": result.get("format", ""),
                },
            )
        return result
