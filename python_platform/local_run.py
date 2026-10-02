"""Local development runner for the Kingsway AI platform.

This is NOT a production entry point and is NOT scheduled anywhere. It lets a
developer run the SAME Flask app that Passenger serves in production, on the
loopback address the PHP development environment already points at
(AI_PYTHON_URL=http://127.0.0.1:8077 in config/.env).

Usage from the ai_platform directory:

    .venv/bin/python local_run.py

It loads the PHP config/.env so the bearer secret and provider keys match the
local PHP edge, then serves on 127.0.0.1:8077. Production is served by wsgi.py
under Passenger and reads the panel environment variables instead.
"""

from __future__ import annotations

import os
from pathlib import Path

import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

REPO_ROOT = Path(__file__).resolve().parent.parent
PHP_ENV = REPO_ROOT / "config" / ".env"


def _load_php_env() -> None:
    """Import the PHP edge's environment so both logics share the same secrets.

    Values already set in this process (explicitly exported) win over the file.
    No values are printed; this only mutates os.environ.
    """
    if not PHP_ENV.exists():  # pragma: no cover - env file present in dev only
        return
    for raw_line in PHP_ENV.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        key = key.strip()
        value = value.strip().strip('"').strip("'")
        if key and key not in os.environ:
            os.environ.setdefault(key, value)


if __name__ == "__main__":
    _load_php_env()
    os.environ.setdefault("KINGSWAY_AI_PRODUCTION", "false")

    from app.routes import create_app

    app = create_app()
    host = os.environ.get("KINGSWAY_AI_DEV_HOST", "127.0.0.1")
    port = int(os.environ.get("KINGSWAY_AI_DEV_PORT", "8077"))
    print(f"Kingsway AI platform (local dev) on http://{host}:{port}")
    app.run(host=host, port=port, debug=False, threaded=True)
