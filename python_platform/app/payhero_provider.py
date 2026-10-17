"""PayHero provider transport for the Python platform.

PHP owns payment orchestration, authorization and ledger handoff; this module
performs only the PayHero HTTP exchanges. The shared host cannot load PHP's
curl extension, so the Python platform is the primary executor of external
provider calls and PHP keeps a stream fallback for relay outages.

Every request uses :mod:`urllib` from the standard library (this platform
intentionally avoids third-party HTTP clients). The normalized response
contract mirrors ``sms_provider``:

    {"ok": bool, "http": int, "status": str, "data": Any,
     "message": str, "raw": str}

Only connection-level failures are retried, and only for methods that are
safe to repeat (GET/HEAD). A real HTTP response — including 4xx/5xx — is
authoritative and is never re-sent: PayHero treats repeated failed STK pushes
as abuse and can block a phone number for 24 hours.
"""

from __future__ import annotations

import json
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Any
from urllib.parse import urlparse

DEFAULT_BASE_URL = "https://backend.payhero.co.ke"
ALLOWED_HOST_SUFFIX = "payhero.co.ke"
REQUEST_TIMEOUT_SECONDS = 30
MAX_RESPONSE_BYTES = 2 * 1024 * 1024
MAX_RAW_SNIPPET = 400
TRANSPORT_RETRIES = 2
RETRY_BACKOFF_SECONDS = 1.0

ACTIONS = {
    "raw",
    "initiate",
    "status",
    "wallet",
    "channels",
    "create_channel",
    "bank_paybills",
}

PAYMENTS_PATH = "/api/v2/payments"
TRANSACTION_STATUS_PATH = "/api/v2/transaction-status"
WALLET_PATH = "/api/v2/wallets"
PAYMENT_CHANNELS_PATH = "/api/v2/payment_channels"
BANK_PAYBILLS_PATH = "/api/v2/bank_paybills"


class PayheroProviderError(RuntimeError):
    """Raised when the provider is misconfigured or a request is refused."""


class PayheroProvider:
    """Stateless PayHero API v2 client; one instance per execution."""

    def __init__(
        self,
        basic_auth: str,
        base_url: str = DEFAULT_BASE_URL,
        timeout: int = REQUEST_TIMEOUT_SECONDS,
    ):
        self.basic_auth = (basic_auth or "").strip()
        self.base_url = (base_url or DEFAULT_BASE_URL).rstrip("/")
        self.timeout = max(5, min(60, int(timeout or REQUEST_TIMEOUT_SECONDS)))
        self._validate_base_url()

    def _validate_base_url(self) -> None:
        parsed = urlparse(self.base_url)
        host = (parsed.hostname or "").lower()
        if parsed.scheme != "https":
            raise PayheroProviderError("PayHero base URL must use HTTPS.")
        if host != ALLOWED_HOST_SUFFIX and not host.endswith("." + ALLOWED_HOST_SUFFIX):
            raise PayheroProviderError("PayHero base URL host is not permitted.")

    # -- transport ---------------------------------------------------------

    def request(
        self,
        method: str,
        path: str,
        body: dict[str, Any] | None = None,
        query: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        """Perform one PayHero API call and normalize the outcome."""
        if self.basic_auth == "":
            return self._normalized(
                False, 0, "error", None, "PayHero credentials are not configured.", ""
            )
        method = method.upper()
        path = "/" + path.lstrip("/")
        if "?" in path or not all(
            part == "" or all(c.isalnum() or c in "-_." for c in part)
            for part in path.split("/")
        ):
            return self._normalized(
                False, 0, "error", None, "Provider path is not permitted.", ""
            )
        url = self.base_url + path
        if query:
            url += "?" + urllib.parse.urlencode(
                {k: v for k, v in query.items() if v is not None}
            )

        headers = {
            "Authorization": "Basic " + self.basic_auth,
            "Content-Type": "application/json",
            "Accept": "application/json",
        }
        payload = (
            json.dumps(body, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
            if body is not None
            else None
        )

        attempts = TRANSPORT_RETRIES if method in ("GET", "HEAD") else 1
        last_error: Exception | None = None
        for attempt in range(attempts):
            request = urllib.request.Request(
                url, data=payload, headers=headers, method=method
            )
            try:
                with urllib.request.urlopen(request, timeout=self.timeout) as response:
                    raw = response.read(MAX_RESPONSE_BYTES + 1)
                    http = int(response.status)
                if len(raw) > MAX_RESPONSE_BYTES:
                    return self._normalized(
                        False,
                        http,
                        "error",
                        None,
                        "Provider response exceeds the limit.",
                        "",
                    )
                return self._decode(http, raw)
            except urllib.error.HTTPError as error:
                try:
                    raw = error.read(MAX_RESPONSE_BYTES)
                except Exception:  # noqa: BLE001 - the status code is still authoritative
                    raw = b""
                return self._decode(int(error.code), raw)
            except (urllib.error.URLError, TimeoutError, OSError) as error:
                last_error = error
                if attempt + 1 < attempts:
                    time.sleep(RETRY_BACKOFF_SECONDS * (attempt + 1))
        return self._normalized(
            False,
            0,
            "unreachable",
            None,
            f"PayHero request failed: {type(last_error).__name__}",
            "",
        )

    def _decode(self, http: int, raw: bytes) -> dict[str, Any]:
        text = (
            raw.decode("utf-8", "replace")
            if isinstance(raw, (bytes, bytearray))
            else str(raw)
        )
        try:
            decoded = json.loads(text) if text else None
        except json.JSONDecodeError:
            decoded = None

        status = ""
        ok = 200 <= http < 300
        message = ""
        if isinstance(decoded, dict):
            status = str(decoded.get("status") or "")
            if decoded.get("success") is False or status.upper() == "FAILED":
                ok = False
            for key in ("error_message", "message", "ResultDesc", "detail"):
                value = decoded.get(key)
                if value not in (None, ""):
                    message = str(value)
                    break
        if message == "" and not ok:
            message = text[:300] if text else "PayHero request failed."

        data: Any = decoded if decoded is not None else None
        return self._normalized(
            ok,
            http,
            status or ("success" if ok else "error"),
            data,
            message,
            text[:MAX_RAW_SNIPPET],
        )

    @staticmethod
    def _normalized(
        ok: bool, http: int, status: str, data: Any, message: str, raw: str
    ) -> dict[str, Any]:
        return {
            "ok": bool(ok),
            "http": int(http),
            "status": status,
            "data": data,
            "message": message,
            "raw": raw,
        }

    # -- semantic operations -----------------------------------------------

    def initiate(self, payload: dict[str, Any]) -> dict[str, Any]:
        return self.request("POST", PAYMENTS_PATH, payload)

    def transaction_status(self, reference: str) -> dict[str, Any]:
        return self.request(
            "GET", TRANSACTION_STATUS_PATH, None, {"reference": reference}
        )

    def wallet_balance(self) -> dict[str, Any]:
        return self.request("GET", WALLET_PATH, None, {"wallet_type": "service_wallet"})

    def payment_channels(self, active_only: bool = False) -> dict[str, Any]:
        return self.request(
            "GET",
            PAYMENT_CHANNELS_PATH,
            None,
            {"is_active": "true"} if active_only else None,
        )

    def create_payment_channel(self, payload: dict[str, Any]) -> dict[str, Any]:
        return self.request("POST", PAYMENT_CHANNELS_PATH, payload)

    def bank_paybills(self) -> dict[str, Any]:
        return self.request("GET", BANK_PAYBILLS_PATH, None)


# -- execute dispatcher ----------------------------------------------------


def execute(
    config: dict[str, Any], action: str, params: dict[str, Any]
) -> dict[str, Any]:
    """Run one named PayHero action for the PHP relay.

    ``config`` is supplied by PHP (the configuration authority): ``base_url``
    and the already-encoded ``basic_auth`` token. ``raw`` relays an
    already-shaped API path from the PHP client so the provider surface is
    owned by PHP and this module performs only the transport.
    """
    action = (action or "").strip()
    if action not in ACTIONS:
        raise PayheroProviderError("Unsupported PayHero action.")
    provider = PayheroProvider(
        basic_auth=str(config.get("basic_auth") or ""),
        base_url=str(config.get("base_url") or DEFAULT_BASE_URL),
        timeout=int(config.get("timeout") or REQUEST_TIMEOUT_SECONDS),
    )
    params = params or {}

    if action == "raw":
        return provider.request(
            str(params.get("method") or "GET"),
            str(params.get("path") or ""),
            params.get("body") if isinstance(params.get("body"), dict) else None,
            params.get("query") if isinstance(params.get("query"), dict) else None,
        )
    if action == "initiate":
        return provider.initiate(params)
    if action == "status":
        return provider.transaction_status(str(params.get("reference") or ""))
    if action == "wallet":
        return provider.wallet_balance()
    if action == "channels":
        return provider.payment_channels(bool(params.get("active_only", True)))
    if action == "create_channel":
        return provider.create_payment_channel(params)
    return provider.bank_paybills()
