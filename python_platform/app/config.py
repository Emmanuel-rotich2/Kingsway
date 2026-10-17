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


def _env_list(name: str, default: tuple[str, ...]) -> list[str]:
    raw = os.environ.get(name)
    if not raw:
        return list(default)
    items = [item.strip().lower() for item in raw.split(",") if item.strip()]
    return items or list(default)


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
        # Internal loopback address for the same PHP logic. When both services
        # share a host the call never needs the public edge, so this is tried
        # first and the public base URL stays the fallback. Left empty, the
        # public URL is used directly. Loopback carries no TLS because the
        # packet never leaves the machine; the worker secret is the identity.
        self.php_internal_base_url = _env("KINGSWAY_PHP_INTERNAL_BASE_URL")
        self.php_internal_host = _env("KINGSWAY_PHP_INTERNAL_HOST")
        self.php_worker_secret = _env(
            "KINGSWAY_PHP_WORKER_SECRET", _env("COMMUNICATION_WORKER_SECRET")
        )

        # The Node.js realtime gateway (scaling masterplan): Python job
        # telemetry publishes here over a non-blocking internal POST. The
        # loopback address + Host header is tried first, the public URL is
        # the fallback; the publish secret must match the gateway's env.
        # Dormant until configured (RealtimePublisher returns False).
        self.node_realtime_internal_base_url = _env("NODE_REALTIME_INTERNAL_BASE_URL")
        self.node_realtime_internal_host = _env("NODE_REALTIME_INTERNAL_HOST")
        self.node_realtime_url = _env("NODE_REALTIME_URL")
        self.node_realtime_public_url = _env("NODE_REALTIME_PUBLIC_URL")
        self.node_realtime_publish_secret = _env(
            "NODE_REALTIME_PUBLISH_SECRET", _env("KINGSWAY_WORKER_SECRET")
        )

        # All three runtimes share STORAGE_ROOT, but keep private subsystem
        # namespaces beneath it to avoid filename/database collisions. Python
        # continues to own its AI, automation, import, document and worker data.
        # AI_STORAGE_DIR remains an explicit compatibility override. PHP is the
        # runtime provisioning authority: it creates the shared root and each
        # namespace at runtime, so an unset STORAGE_ROOT falls back to the same
        # default location PHP uses instead of failing the worker.
        configured_shared_root = _env("STORAGE_ROOT")
        shared_root = Path(configured_shared_root or str(APP_ROOT.parent / "storage"))
        if not shared_root.is_absolute():
            raise ValueError("STORAGE_ROOT must be an absolute path")
        self.shared_storage_root = shared_root
        # A configured shared root is authoritative. The legacy AI_STORAGE_DIR
        # remains available only when STORAGE_ROOT is absent during migration.
        raw_storage = (
            str(shared_root / "python")
            if configured_shared_root
            else _env("AI_STORAGE_DIR", str(shared_root / "python"))
        )
        storage = Path(raw_storage)
        self.storage_dir = storage if storage.is_absolute() else (APP_ROOT / storage)

        # The PHP application root (this platform ships nested beside it).
        # Used only to locate repo files (config snapshots); never writable
        # through this platform.
        raw_repo = _env("KINGSWAY_REPO_ROOT", str(APP_ROOT.parent))
        repo = Path(raw_repo)
        self.repo_root = repo if repo.is_absolute() else (APP_ROOT / repo)

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

        # Behavior/conversation stores use Python's namespace under the shared root.
        raw_data_dir = (
            str(self.storage_dir / "data")
            if configured_shared_root
            else _env("KINGSWAY_AI_DATA_DIR", str(self.storage_dir / "data"))
        )
        self.data_dir = Path(raw_data_dir)
        self.log_dir = Path(_env("KINGSWAY_AI_LOG_DIR", str(APP_ROOT / "logs")))
        for directory in (self.data_dir, self.log_dir):
            directory.mkdir(parents=True, exist_ok=True)
            try:
                directory.chmod(0o700)
            except OSError:
                pass

        self.production = _env_bool("KINGSWAY_AI_PRODUCTION", True)

        # Payment gateway hosts the platform may execute HTTP transport for.
        # PHP remains the orchestration authority; the endpoint is
        # bearer-authenticated and HTTPS-only, and this allowlist stops the
        # relay from ever becoming a general-purpose proxy. Extend via
        # PAYMENT_GATEWAY_HOSTS when a deployment overrides a provider host.
        self.payment_gateway_hosts = _env_list(
            "PAYMENT_GATEWAY_HOSTS",
            (
                "backend.payhero.co.ke",
                "api.safaricom.co.ke",
                "sandbox.safaricom.co.ke",
                "api.buni.kcbgroup.com",
                "uat.buni.kcbgroup.com",
                "accounts.buni.kcbgroup.com",
            ),
        )

        # TLS verification for calls back into the PHP edge. Default ON.
        # Set to false ONLY for local development with a self-signed cert.
        self.php_verify_tls = _env_bool("KINGSWAY_PHP_VERIFY_TLS", True)

        # Deterministic read-model worker. Disabled until deployment installs
        # PyMySQL and supplies a least-privilege account for the source and
        # reads schemas. These credentials are never forwarded to an LLM.
        self.read_models_enabled = _env_bool(
            "KINGSWAY_PYTHON_READ_MODELS_ENABLED", False
        )
        self.db_host = _env("KINGSWAY_DB_HOST")
        self.db_port = _env_int("KINGSWAY_DB_PORT", 3306)
        self.db_user = _env("KINGSWAY_DB_USER")
        self.db_password = _env("KINGSWAY_DB_PASSWORD")
        self.db_ssl_ca = _env("KINGSWAY_DB_SSL_CA")
        self.db_master_schema = _env("KINGSWAY_DB_MASTER_SCHEMA", "KingsWayAcademy")
        self.db_reads_schema = _env("KINGSWAY_DB_READS_SCHEMA", "KingsWayReads")
        self.db_connect_timeout = max(
            1, min(15, _env_int("KINGSWAY_DB_CONNECT_TIMEOUT", 5))
        )
        self.db_read_timeout = max(
            30, min(900, _env_int("KINGSWAY_DB_READ_TIMEOUT", 300))
        )
