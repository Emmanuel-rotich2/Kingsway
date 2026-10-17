"""Generic payment-gateway transport for the Python platform.

PHP owns payment orchestration, token caching, payload shaping and ledger
handoff; this module performs only the HTTP exchanges for gateway APIs that
do not have a dedicated provider module (M-Pesa Daraja and KCB Buni; PayHero
and TALKSASA have their own modules). The shared host cannot load PHP's curl
extension, so the Python platform is the primary executor of external
provider calls and PHP keeps a stream fallback for relay outages.

Every request uses :mod:`urllib` from the standard library. Requests are
bounded by two allowlists because this endpoint is service-to-service:

* destination hosts — the configured school providers only, HTTPS only;
* header names — credentials and content negotiation only.

The normalized response contract mirrors ``sms_provider``/``payhero_provider``:

    {"ok": bool, "http": int, "status": str, "data": Any,
     "message": str, "raw": str}

Only connection-level failures are retried, and only for methods that are
safe to repeat (GET/HEAD). A real HTTP response — including 4xx/5xx — is
authoritative and is never re-sent.
"""

from __future__ import annotations

import json
import time
import urllib.error
import urllib.request
from typing import Any
from urllib.parse import urlparse

DEFAULT_ALLOWED_HOSTS = (
    "backend.payhero.co.ke",
    "api.safaricom.co.ke",
    "sandbox.safaricom.co.ke",
    "api.buni.kcbgroup.com",
    "uat.buni.kcbgroup.com",
    "accounts.buni.kcbgroup.com",
)
ALLOWED_HEADERS = {
    "authorization",
    "apikey",
    "content-type",
    "accept",
    # KCB Buni M-Pesa Express requires these operation headers.
    "routecode",
    "operation",
    "messageid",
    # Carried over from the previous PHP curl transport so the provider sees
    # the same request headers it saw before the relay migration.
    "access-control-allow-origin",
}
ALLOWED_METHODS = {"GET", "POST", "PUT", "PATCH", "DELETE"}
REQUEST_TIMEOUT_SECONDS = 45
MAX_RESPONSE_BYTES = 2 * 1024 * 1024
MAX_REQUEST_BYTES = 256 * 1024
MAX_RAW_SNIPPET = 400
TRANSPORT_RETRIES = 2
RETRY_BACKOFF_SECONDS = 1.0


class PaymentGatewayError(RuntimeError):
    """Raised when the request is refused (not a provider outage)."""


def execute(allowed_hosts: Any, params: dict[str, Any]) -> dict[str, Any]:
    """Run one already-shaped gateway request for the PHP relay.

    ``params``: ``{url, method, headers: {name: value}, body: str|None,
    timeout: int|None}``. PHP serializes the body itself, so it is sent
    byte-for-byte and never re-encoded here.
    """
    params = params or {}
    url = str(params.get("url") or "")
    parsed = urlparse(url)
    host = (parsed.hostname or "").lower()
    hosts = {
        str(item).strip().lower()
        for item in (allowed_hosts or DEFAULT_ALLOWED_HOSTS)
        if str(item).strip()
    }
    if parsed.scheme != "https" or host not in hosts:
        raise PaymentGatewayError("Provider URL is not permitted.")

    method = str(params.get("method") or "GET").strip().upper()
    if method not in ALLOWED_METHODS:
        raise PaymentGatewayError("Provider method is not permitted.")

    headers: dict[str, str] = {}
    raw_headers = params.get("headers")
    if isinstance(raw_headers, dict):
        for key, value in raw_headers.items():
            name = str(key).strip()
            if name.lower() in ALLOWED_HEADERS and value not in (None, ""):
                headers[name] = str(value)

    body = params.get("body")
    payload = body.encode("utf-8") if isinstance(body, str) and body != "" else None
    if payload is not None and len(payload) > MAX_REQUEST_BYTES:
        raise PaymentGatewayError("Provider request body exceeds the limit.")

    try:
        timeout = int(params.get("timeout") or REQUEST_TIMEOUT_SECONDS)
    except (TypeError, ValueError):
        timeout = REQUEST_TIMEOUT_SECONDS
    timeout = max(5, min(90, timeout))

    attempts = TRANSPORT_RETRIES if method in ("GET", "HEAD") else 1
    last_error: Exception | None = None
    for attempt in range(attempts):
        request = urllib.request.Request(
            url, data=payload, headers=headers, method=method
        )
        try:
            with urllib.request.urlopen(request, timeout=timeout) as response:
                raw = response.read(MAX_RESPONSE_BYTES + 1)
                http = int(response.status)
            if len(raw) > MAX_RESPONSE_BYTES:
                return _normalized(
                    False,
                    http,
                    "error",
                    None,
                    "Provider response exceeds the limit.",
                    "",
                )
            return _decode(http, raw)
        except urllib.error.HTTPError as error:
            try:
                raw = error.read(MAX_RESPONSE_BYTES)
            except Exception:  # noqa: BLE001 - the status code is still authoritative
                raw = b""
            return _decode(int(error.code), raw)
        except (urllib.error.URLError, TimeoutError, OSError) as error:
            last_error = error
            if attempt + 1 < attempts:
                time.sleep(RETRY_BACKOFF_SECONDS * (attempt + 1))
    return _normalized(
        False,
        0,
        "unreachable",
        None,
        f"Provider request failed: {type(last_error).__name__}",
        "",
    )


def _decode(http: int, raw: bytes) -> dict[str, Any]:
    text = (
        raw.decode("utf-8", "replace")
        if isinstance(raw, (bytes, bytearray))
        else str(raw)
    )
    try:
        decoded = json.loads(text) if text else None
    except json.JSONDecodeError:
        decoded = None

    ok = 200 <= http < 300
    if isinstance(decoded, dict) and decoded.get("success") is False:
        ok = False
    status = ""
    message = ""
    if isinstance(decoded, dict):
        status = str(decoded.get("status") or "")
        if str(decoded.get("ResultCode") or "0") not in ("", "0"):
            ok = False
        for key in ("error_message", "message", "ResultDesc", "error", "detail"):
            value = decoded.get(key)
            if value not in (None, ""):
                message = (
                    json.dumps(value) if isinstance(value, (dict, list)) else str(value)
                )
                break
    if message == "" and not ok:
        message = text[:300] if text else "Provider request failed."

    return _normalized(
        ok,
        http,
        status or ("success" if ok else "error"),
        decoded,
        message,
        text[:MAX_RAW_SNIPPET],
    )


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
