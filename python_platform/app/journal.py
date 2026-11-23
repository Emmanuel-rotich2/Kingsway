"""JSON-lines file journals, fully compatible with PHP FileLogger contract.

Writes to the SAME log directory and format as PHP's FileLogger:
- Directory: <project_root>/logs/<env>/
- Format: JSON-lines with timestamp, env, level, category, and data
- Rotation follows the same rules as PHP's FileLogger, done here because
  PHP never touches the Python platform's own log directory (in production
  Python runs on its own subdomain): a category file is closed when the day
  changes or it passes MAX_SIZE, compressed to <name>.log.<stamp>-<pid>.gz,
  and archives beyond the retention window are deleted.
"""

from __future__ import annotations

import gzip
import hashlib
import json
import os
import shutil
import threading
import time
from datetime import date, datetime
from pathlib import Path
from typing import Any

try:  # serialise rotation across processes (ticker, Passenger workers)
    import fcntl
except ImportError:  # pragma: no cover - non-POSIX
    fcntl = None

# Same thresholds as PHP FileLogger, so the two journals behave alike.
MAX_SIZE = 10 * 1024 * 1024  # 10 MB
MAX_ARCHIVES = 400


def _retention_days() -> int:
    """Days to keep compressed archives (KINGSWAY_LOG_RETENTION_DAYS, default 30)."""
    try:
        return max(1, int(os.environ.get("KINGSWAY_LOG_RETENTION_DAYS", "30")))
    except ValueError:
        return 30


def rotate_if_due(path: Path, force: bool = False, now: float | None = None) -> bool:
    """Close the current period of a category file when it is due.

    Due when the file was last written on a previous day, or is MAX_SIZE or
    larger. The file is renamed atomically, compressed and old archives are
    pruned. Never raises: journaling must not fail a run. Returns True when a
    rotation happened.
    """
    try:
        st = path.stat()
    except OSError:
        return False
    today = date.fromtimestamp(now) if now is not None else date.today()

    def due(stat_result: os.stat_result) -> bool:
        return force or stat_result.st_size >= MAX_SIZE or date.fromtimestamp(stat_result.st_mtime) < today

    if not due(st) or st.st_size == 0:
        return False
    lock_path = path.with_name(path.name + ".rotation.lock")
    try:
        with open(lock_path, "a", encoding="utf-8") as lock:
            if fcntl is not None:
                fcntl.flock(lock, fcntl.LOCK_EX)
            try:
                try:
                    st = path.stat()  # another process may have rotated meanwhile
                except OSError:
                    return False
                if not due(st) or st.st_size == 0:
                    return False
                stamp = datetime.fromtimestamp(now if now is not None else time.time()).strftime("%Y-%m-%d-%H%M%S")
                archive = path.with_name(f"{path.name}.{stamp}-{os.getpid()}")
                os.replace(path, archive)
                try:
                    with open(archive, "rb") as src, gzip.open(f"{archive}.gz", "wb", compresslevel=9) as dst:
                        shutil.copyfileobj(src, dst, 1024 * 1024)
                    archive.unlink()
                except OSError:
                    pass  # keep the plain archive rather than lose it
                _prune_archives(path, now)
                return True
            finally:
                if fcntl is not None:
                    fcntl.flock(lock, fcntl.LOCK_UN)
    except OSError:
        return False


def _prune_archives(path: Path, now: float | None = None) -> None:
    """Delete archives older than the retention window, keep at most MAX_ARCHIVES."""
    cutoff = (now if now is not None else time.time()) - _retention_days() * 86400
    archives = sorted(
        (p for p in path.parent.glob(path.name + ".*") if not p.name.endswith(".rotation.lock")),
        key=lambda p: p.stat().st_mtime,
        reverse=True,
    )
    for index, archive in enumerate(archives):
        try:
            if index >= MAX_ARCHIVES or archive.stat().st_mtime < cutoff:
                archive.unlink()
        except OSError:
            continue


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
                "level": (level or "info").lower()[:16],
                "category": category[:60],
                **entry,
            }
            line = json.dumps(entry, ensure_ascii=False, separators=(",", ":"))
            with self._lock:
                log_root = self._dir
                log_root.mkdir(parents=True, exist_ok=True)
                path = log_root / f"{category[:60]}.log"
                rotate_if_due(path)
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
