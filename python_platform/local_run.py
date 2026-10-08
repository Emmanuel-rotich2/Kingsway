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
from urllib.parse import urlsplit, urlunsplit

import sys

# The documented `python3 local_run.py` command must use the project's locked
# dependencies. A system interpreter may have Flask but lack PyMySQL/Polars,
# leaving the service alive while read-model jobs fail after dispatch.
VENV_PYTHON = Path(__file__).resolve().parent / ".venv" / "bin" / "python"
if VENV_PYTHON.is_file() and Path(sys.executable).resolve() != VENV_PYTHON.resolve():
    os.execv(str(VENV_PYTHON), [str(VENV_PYTHON), str(Path(__file__).resolve()), *sys.argv[1:]])

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
    loaded: dict[str, str] = {}
    for raw_line in PHP_ENV.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        key = key.strip()
        value = value.strip().strip('"').strip("'")
        if key and key not in os.environ:
            os.environ.setdefault(key, value)
        if key:
            loaded[key] = os.environ.get(key, value)

    # Reuse local DB settings for the Python read-model engine. Values remain
    # process-only and are never logged. A dedicated least-privilege account
    # can replace this mapping without changing the worker contract.
    for source, target in (
        ("DB_HOST", "KINGSWAY_DB_HOST"),
        ("DB_PORT", "KINGSWAY_DB_PORT"),
        ("DB_USER", "KINGSWAY_DB_USER"),
        ("DB_PASS", "KINGSWAY_DB_PASSWORD"),
        ("DB_NAME", "KINGSWAY_DB_MASTER_SCHEMA"),
        ("DB_READS_NAME", "KINGSWAY_DB_READS_SCHEMA"),
    ):
        if loaded.get(source):
            os.environ.setdefault(target, loaded[source])

    # The Python queue worker calls back into the PHP queue for claim and
    # acknowledgement. Keep those calls on this local installation instead
    # of Config's production fallback URL.
    php_base = loaded.get("BASE_URL", "").rstrip("/")
    if php_base:
        os.environ.setdefault("KINGSWAY_PHP_BASE_URL", php_base)
        parsed = urlsplit(php_base)
        if parsed.hostname in {"localhost", "127.0.0.1", "::1"}:
            local_netloc = "127.0.0.1" + (f":{parsed.port}" if parsed.port else "")
            os.environ.setdefault(
                "KINGSWAY_PHP_INTERNAL_BASE_URL",
                urlunsplit((parsed.scheme, local_netloc, parsed.path, "", "")),
            )
            os.environ.setdefault("KINGSWAY_PHP_INTERNAL_HOST", parsed.hostname)
            os.environ.setdefault("KINGSWAY_PHP_VERIFY_TLS", "false")

    if all(os.environ.get(key) for key in (
        "KINGSWAY_DB_HOST", "KINGSWAY_DB_USER", "KINGSWAY_DB_PASSWORD"
    )):
        os.environ.setdefault("KINGSWAY_PYTHON_READ_MODELS_ENABLED", "true")


if __name__ == "__main__":
    _load_php_env()
    os.environ.setdefault("KINGSWAY_AI_PRODUCTION", "false")

    from app.routes import create_app

    app = create_app()
    host = os.environ.get("KINGSWAY_AI_DEV_HOST", "127.0.0.1")
    port = int(os.environ.get("KINGSWAY_AI_DEV_PORT", "8077"))
    print(f"Kingsway AI platform (local dev) on http://{host}:{port}")
    app.run(host=host, port=port, debug=False, threaded=True)
