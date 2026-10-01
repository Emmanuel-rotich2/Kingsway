"""Environment-driven configuration for the Kingsway AI platform.

The platform is the PRIMARY AI engine of the school system:
agents, assistants, provider calls, prompts, behavior study, and
automations all run here in Python. PHP remains the authentication
and governance edge and relays requests to this service.
"""

from __future__ import annotations

import os
from pathlib import Path

APP_ROOT = Path(__file__).resolve().parent.parent


def _env(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


def _env_bool(name: str, default: bool = False) -> bool:
    raw = os.environ.get(name)
    if raw is None:
        return default
    return raw.strip().lower() in ("1", "true", "yes", "on")


def _env_int(name: str, default: int) -> int:
    try:
        return int(os.environ.get(name, default))
    except (TypeError, ValueError):
        return default


class Config:
    """Runtime settings, mirrored from the PHP deployment environment."""

    def __init__(self) -> None:
        # Service-to-service bearer secret shared with the PHP edge.
        self.secret = _env("KINGSWAY_AI_SECRET")
        if not self.secret:
            self.secret = _env("AI_PYTHON_SECRET")

        # The PHP application (governed data tools live there).
        self.php_base_url = _env(
            "KINGSWAY_PHP_BASE_URL", "https://kingswaypreparatoryschool.sc.ke"
        )
        self.php_worker_secret = _env(
            "KINGSWAY_PHP_WORKER_SECRET", _env("COMMUNICATION_WORKER_SECRET")
        )

        # Primary LLM provider (same semantics as the PHP client).
        self.provider_name = _env("AI_PROVIDER_NAME", "primary")
        self.provider_base_url = _env("AI_PROVIDER_BASE_URL")
        self.provider_kind = _env("AI_PROVIDER_KIND", "generic")
        self.model = _env("AI_MODEL")
        self.api_key = _env("AI_API_KEY")
        self.response_format = _env("AI_RESPONSE_FORMAT")
        self.reasoning_effort = _env("AI_REASONING_EFFORT")
        self.max_tokens = _env_int("AI_MAX_TOKENS", 1200)
        self.timeout = _env_int("AI_TIMEOUT", 25)
        self.connect_timeout = _env_int("AI_CONNECT_TIMEOUT", 5)
        self.provider_retries = _env_int("AI_PROVIDER_RETRIES", 2)
        self.retry_delay_ms = _env_int("AI_PROVIDER_RETRY_DELAY_MS", 250)
        self.enabled = _env_bool("AI_ENABLED", False)

        # Additional fallback providers as a JSON list, mirroring the
        # PHP gateway contract: [{"name","base_url","model","api_key"?,"provider_kind"?}]
        self.fallbacks_raw = _env("AI_PROVIDER_FALLBACKS")

        # Optional fast/cheap model for classification-style work (triage,
        # routing, short extraction). Omit it and every task uses the primary
        # model; when set, "fast" tasks prefer it and transparently fall back
        # to the primary chain. Same provider credentials by default.
        self.fast_model = _env("AI_FAST_MODEL")
        self.fast_model_base_url = _env("AI_FAST_MODEL_BASE_URL")
        self.fast_model_api_key = _env("AI_FAST_MODEL_API_KEY")

        # Concurrency bounds. The co-worker is deliberately aggressive about
        # parallel work but never unbounded: hard caps keep request latency
        # and provider spend predictable on shared hosting.
        self.max_tool_parallel = min(4, _env_int("AI_MAX_TOOL_PARALLEL", 3))
        self.briefing_specialists = min(3, _env_int("AI_BRIEFING_SPECIALISTS", 2))

        # Storage (behavior study + journals) under the app root by default.
        self.data_dir = Path(_env("KINGSWAY_AI_DATA_DIR", str(APP_ROOT / "data")))
        self.log_dir = Path(_env("KINGSWAY_AI_LOG_DIR", str(APP_ROOT / "logs")))
        for directory in (self.data_dir, self.log_dir):
            directory.mkdir(parents=True, exist_ok=True)
            try:
                directory.chmod(0o700)
            except OSError:
                pass

        self.production = _env_bool("KINGSWAY_AI_PRODUCTION", True)

        # TLS verification for calls back into the PHP edge. Default ON.
        # Set to false ONLY for local development with a self-signed cert.
        self.php_verify_tls = _env_bool("KINGSWAY_PHP_VERIFY_TLS", True)
