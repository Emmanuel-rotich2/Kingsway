"""JSON-lines file journals, fully compatible with PHP FileLogger contract.

Writes to the SAME log directory and format as PHP's FileLogger:
- Directory: <project_root>/logs/<env>/
- Format: JSON-lines with timestamp, env, level, category, and data
- Category sanitization and rotation handled by PHP's FileLogger
"""

from __future__ import annotations

import hashlib
import json
import os
import threading
import time
from pathlib import Path
from typing import Any


def _resolve_project_root() -> Path:
    """Resolve the project root (where PHP's FileLogger writes logs)."""
    # Python app is at python_platform/app/... so go up 3 levels to project root
    return Path(__file__).resolve().parent.parent.parent


def _resolve_env() -> str:
    """Resolve environment from APP_ENV, matching PHP's FileLogger."""
    env = os.environ.get("APP_ENV") or os.environ.get("ENV") or "development"
    env = env.lower().strip()
    return env if env in ("production", "staging") else "development"


def _sanitize_category(category: str) -> str:
    """Sanitize category name, matching PHP's FileLogger::sanitizeCategory."""
    return (
        "".join(c if c.isalnum() or c in ("_", "-") else "_" for c in category) or "app"
    )


def _resolve_log_root() -> Path:
    """Resolve the log root directory: <project_root>/logs/"""
    return _resolve_project_root() / "logs"


class Journal:
    """JSON-lines journal, fully compatible with PHP's FileLogger format."""

    def __init__(self, log_dir: Path = None) -> None:
        self._dir = Path(log_dir) if log_dir else _resolve_log_root() / _resolve_env()
        self._lock = threading.Lock()

    def write(self, category: str, entry: dict[str, Any], level: str = "info") -> None:
        """Write a log entry in PHP FileLogger-compatible format."""
        try:
            env = _resolve_env()
            category = self._sanitize(category)
            entry = {
                "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
                "env": self._resolve_env(),
                "level": "info",
                "category": category[:60],
                **entry,
            }
            line = json.dumps(entry, ensure_ascii=False, separators=(",", ":"))
            with self._lock:
                log_root = _resolve_log_root() / self._resolve_env()
                log_root.mkdir(parents=True, exist_ok=True)
                path = log_root / f"{category[:60]}.log"
                with open(path, "a", encoding="utf-8") as handle:
                    handle.write(
                        json.dumps(entry, ensure_ascii=False, separators=(",", ":"))
                        + "\n"
                    )
        except OSError:
            # Journaling must never fail a run.
            pass

    @staticmethod
    def _sanitize(category: str) -> str:
        return (
            "".join(c if c.isalnum() or c in ("_", "-") else "_" for c in category)
            or "app"
        )

    @staticmethod
    def _resolve_env() -> str:
        env = os.environ.get("APP_ENV") or os.environ.get("ENV") or "development"
        env = env.lower().strip()
        return env if env in ("production", "staging") else "development"

    @staticmethod
    def _resolve_log_root() -> Path:
        # Project root is 3 levels up from python_platform/app/journal.py
        return Path(__file__).resolve().parent.parent.parent / "logs"

    @staticmethod
    def _lock():
        return threading.Lock()


def hash_text(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8", "replace")).hexdigest()


def hash_json(payload: Any) -> str:
    return hash_text(
        json.dumps(payload, ensure_ascii=False, sort_keys=True, default=str)
    )
