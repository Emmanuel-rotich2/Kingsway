"""JSON-lines file journals, mirroring the PHP FileLogger contract.

Only hashes and bounded metadata are ever written - prompts, rows and
identifying content never reach the journals.
"""

from __future__ import annotations

import hashlib
import json
import threading
import time
from pathlib import Path
from typing import Any


class Journal:
    def __init__(self, log_dir: Path) -> None:
        self._dir = Path(log_dir)
        self._lock = threading.Lock()

    def write(self, category: str, entry: dict[str, Any]) -> None:
        try:
            line = json.dumps(
                {
                    "ts": time.strftime("%Y-%m-%dT%H:%M:%S+00:00", time.gmtime()),
                    "category": category[:60],
                    **entry,
                },
                ensure_ascii=False,
            )
            with self._lock:
                path = self._dir / f"{category[:60]}.log"
                with open(path, "a", encoding="utf-8") as handle:
                    handle.write(line + "\n")
        except OSError:
            # Journaling must never fail a run.
            pass


def hash_text(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8", "replace")).hexdigest()


def hash_json(payload: Any) -> str:
    return hash_text(
        json.dumps(payload, ensure_ascii=False, sort_keys=True, default=str)
    )
