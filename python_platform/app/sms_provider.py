"""TALKSASA provider transport for the Python platform.

PHP owns configuration and business records; this module performs only the
provider HTTP calls. The shared host cannot install PHP's curl extension and
this platform intentionally avoids third-party HTTP clients, so every request
uses :mod:`urllib` from the standard library.

The normalized response contract mirrors PHP's ``SMSGateway::request()`` so a
relayed call round-trips without reshaping:

    {"ok": bool, "http": int, "status": str, "data": Any,
     "message": str, "raw": str}

Only connection-level failures are retryable; a real HTTP response (including
4xx/5xx) is authoritative and is never re-sent, because a duplicated send has
real cost and reaches real phones.
"""

from __future__ import annotations

import json
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Any, Callable, Iterable

DEFAULT_BASE_URL = "https://bulksms.talksasa.com/api/v3"
REQUEST_TIMEOUT_SECONDS = 30
MAX_RAW_SNIPPET = 400
TRANSPORT_RETRIES = 2
RETRY_BACKOFF_SECONDS = 1.0

SEND_TYPES = {"plain", "sms", "whatsapp", "voice", "mms", "hashed"}


class SmsProviderError(RuntimeError):
    """Raised when the provider cannot be reached or is misconfigured."""


class SmsProvider:
    """Stateless TALKSASA client; one instance per execution."""

    def __init__(
        self,
        api_key: str,
        base_url: str = DEFAULT_BASE_URL,
        sender_id: str = "TALKSASA",
        timeout: int = REQUEST_TIMEOUT_SECONDS,
    ):
        self.api_key = (api_key or "").strip()
        self.base_url = (base_url or DEFAULT_BASE_URL).rstrip("/")
        self.sender_id = (sender_id or "TALKSASA").strip() or "TALKSASA"
        self.timeout = max(5, min(120, int(timeout or REQUEST_TIMEOUT_SECONDS)))

    # -- transport ---------------------------------------------------------

    def request(
        self,
        method: str,
        path: str,
        body: dict[str, Any] | None = None,
        query: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        """Perform one provider call and normalize the outcome."""
        if self.api_key == "":
            return self._normalized(
                False, 0, "error", None, "Messaging API key is not configured.", ""
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
            "Authorization": "Bearer " + self.api_key,
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
                    raw = response.read(2 * 1024 * 1024 + 1)
                    http = int(response.status)
                if len(raw) > 2 * 1024 * 1024:
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
                    raw = error.read(2 * 1024 * 1024)
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
            f"Messaging request failed: {type(last_error).__name__}",
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
        if isinstance(decoded, dict):
            status = str(decoded.get("status") or decoded.get("result") or "").lower()
        ok = 200 <= http < 300 and not (
            isinstance(decoded, dict)
            and ("error" in decoded or "errors" in decoded or status == "error")
        )
        message = ""
        if isinstance(decoded, dict):
            message = str(decoded.get("message") or "")
            if message == "" and "error" in decoded:
                error = decoded["error"]
                message = (
                    json.dumps(error) if isinstance(error, (dict, list)) else str(error)
                )
        if message == "" and not ok:
            message = text[:300] if text else "Messaging request failed."
        data: Any = None
        if isinstance(decoded, dict):
            data = decoded.get("data", decoded)
        elif decoded is not None:
            data = decoded
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

    def balance(self) -> dict[str, Any]:
        return self.request("GET", "/balance")

    def profile(self) -> dict[str, Any]:
        return self.request("GET", "/me")

    def send(
        self,
        recipient: str,
        message: str,
        sms_type: str = "plain",
        sender_id: str | None = None,
        schedule_time: str | None = None,
    ) -> dict[str, Any]:
        sms_type = (sms_type or "plain").lower()
        if sms_type not in SEND_TYPES:
            return self._normalized(
                False, 0, "error", None, "Unsupported message type.", ""
            )
        body: dict[str, Any] = {
            "recipient": recipient,
            "sender_id": sender_id or self.sender_id,
            "type": "plain" if sms_type == "sms" else sms_type,
            "message": message,
        }
        if schedule_time:
            body["schedule_time"] = schedule_time
        return self.request("POST", "/sms/send", body)

    def send_hashed(
        self,
        recipient: str,
        message: str,
        sender_id: str | None = None,
        recipient_format: str = "hashed",
    ) -> dict[str, Any]:
        return self.request(
            "POST",
            "/sms/send-hashed",
            {
                "recipient": recipient,
                "recipient_format": recipient_format or "hashed",
                "sender_id": sender_id or self.sender_id,
                "message": message,
            },
        )

    def campaign(
        self,
        contact_list_id: str,
        message: str,
        sender_id: str | None = None,
        schedule_time: str | None = None,
        dlt_template_id: str | None = None,
    ) -> dict[str, Any]:
        body: dict[str, Any] = {
            "recipient": contact_list_id,
            "sender_id": sender_id or self.sender_id,
            "type": "plain",
            "message": message,
        }
        if schedule_time:
            body["schedule_time"] = schedule_time
        if dlt_template_id:
            body["dlt_template_id"] = dlt_template_id
        return self.request("POST", "/sms/campaign", body)

    def queue_status(self, uid: str) -> dict[str, Any]:
        return self.request("GET", f"/sms/queue/{uid}")

    def groups_list(self) -> dict[str, Any]:
        return self.request("GET", "/contacts")

    def group_show(self, group_id: str) -> dict[str, Any]:
        return self.request("POST", f"/contacts/{group_id}/show")

    def group_all_contacts(self, group_id: str) -> dict[str, Any]:
        return self.request("POST", f"/contacts/{group_id}/all")

    def group_create(self, name: str) -> dict[str, Any]:
        return self.request("POST", "/contacts", {"name": name})

    def group_update(self, group_id: str, name: str) -> dict[str, Any]:
        return self.request("PATCH", f"/contacts/{group_id}", {"name": name})

    def group_delete(self, group_id: str) -> dict[str, Any]:
        return self.request("DELETE", f"/contacts/{group_id}")

    def contact_store(
        self, group_id: str, phone: str, first_name: str = "", last_name: str = ""
    ) -> dict[str, Any]:
        return self.request(
            "POST",
            "/contacts",
            {
                "group_id": group_id,
                "phone": phone,
                "first_name": first_name,
                "last_name": last_name,
            },
        )

    def contact_search(self, group_id: str, uid: str) -> dict[str, Any]:
        return self.request("POST", f"/contacts/{group_id}/search/{uid}")

    def contact_update(
        self,
        group_id: str,
        uid: str,
        phone: str,
        first_name: str = "",
        last_name: str = "",
    ) -> dict[str, Any]:
        return self.request(
            "PATCH",
            f"/contacts/{group_id}",
            {
                "uid": uid,
                "phone": phone,
                "first_name": first_name,
                "last_name": last_name,
            },
        )

    def contact_delete(self, group_id: str, uid: str) -> dict[str, Any]:
        return self.request("DELETE", f"/contacts/{group_id}/delete/{uid}")

    def messages_list(self, **filters: Any) -> dict[str, Any]:
        return self.request("GET", "/sms", query=filters)

    def message_get(self, uid: str) -> dict[str, Any]:
        return self.request("GET", f"/sms/{uid}")

    # -- batch -------------------------------------------------------------

    def send_batch(
        self,
        entries: Iterable[dict[str, Any]],
        message: str,
        sms_type: str = "plain",
        sender_id: str | None = None,
        media_url: str | None = None,
        language: str | None = None,
        gender: str | None = None,
        recipient_format: str | None = None,
        schedule_time: str | None = None,
        on_progress: Callable[[int, int], None] | None = None,
    ) -> list[dict[str, Any]]:
        """Send one message to many recipients, pacing provider calls.

        Each entry is ``{"endpoint_id": int, "address": str}``. Results are
        returned in input order with a bounded error string per row. The
        runner keeps going after per-recipient failures so a single bad number
        never aborts the batch; a hard transport outage still fails fast
        because every row would fail anyway.
        """
        entries = list(entries)
        total = len(entries)
        results: list[dict[str, Any]] = []
        consecutive_transport_failures = 0
        body = message
        if sms_type == "mms" and media_url:
            body = f"{message}\n{media_url}".strip()

        for index, entry in enumerate(entries, start=1):
            address = str(entry.get("address") or "").strip()
            endpoint_id = int(entry.get("endpoint_id") or 0)
            if address == "":
                results.append(
                    self._batch_row(
                        endpoint_id, False, None, "error", "Missing recipient address"
                    )
                )
                continue
            if sms_type == "hashed":
                outcome = self.send_hashed(
                    address, body, sender_id, recipient_format or "hashed"
                )
            else:
                outcome = self.send(address, body, sms_type, sender_id, schedule_time)
            provider_id = self._extract_uid(outcome.get("data"))
            status = str(outcome.get("status") or "")
            if outcome.get("ok"):
                consecutive_transport_failures = 0
            elif outcome.get("status") == "unreachable":
                consecutive_transport_failures += 1
                if consecutive_transport_failures >= 5:
                    for remaining in entries[index:]:
                        results.append(
                            self._batch_row(
                                int(remaining.get("endpoint_id") or 0),
                                False,
                                None,
                                "unreachable",
                                "Provider unreachable; batch stopped",
                            )
                        )
                    if on_progress:
                        on_progress(total, total)
                    raise SmsProviderError(
                        "Provider unreachable after "
                        f"{consecutive_transport_failures} consecutive attempts"
                    )
            results.append(
                self._batch_row(
                    endpoint_id,
                    bool(outcome.get("ok")),
                    provider_id,
                    status,
                    str(outcome.get("message") or ""),
                )
            )
            if sms_type in ("plain", "sms", "whatsapp") and index % 20 == 0:
                time.sleep(0.5)
            if on_progress and (index % 25 == 0 or index == total):
                on_progress(index, total)
        return results

    @staticmethod
    def _batch_row(
        endpoint_id: int, ok: bool, provider_id: str | None, status: str, error: str
    ) -> dict[str, Any]:
        return {
            "endpoint_id": endpoint_id,
            "ok": bool(ok),
            "provider_message_id": provider_id,
            "status": status,
            "error": error[:500],
        }

    @staticmethod
    def _extract_uid(data: Any) -> str | None:
        if not isinstance(data, dict):
            return None
        for key in ("uid", "queue_uid", "message_id", "messageId", "id"):
            value = data.get(key)
            if value not in (None, ""):
                return str(value)
        return None


# -- execute dispatcher ----------------------------------------------------


def execute(
    config: dict[str, Any], action: str, params: dict[str, Any]
) -> dict[str, Any]:
    """Run one named provider action for the PHP relay.

    ``config`` is supplied by PHP (the configuration authority): ``api_key``,
    optional ``api_url`` and ``sender_id``. ``raw`` relays an already-shaped
    provider path from the PHP gateways so every TALKSASA call executes here
    without duplicating endpoint knowledge.
    """
    provider = SmsProvider(
        api_key=str(config.get("api_key") or ""),
        base_url=str(config.get("api_url") or DEFAULT_BASE_URL),
        sender_id=str(config.get("sender_id") or "TALKSASA"),
    )
    params = params or {}
    if action == "raw":
        return provider.request(
            str(params.get("method") or "GET"),
            str(params.get("path") or ""),
            params.get("body") if isinstance(params.get("body"), dict) else None,
            params.get("query") if isinstance(params.get("query"), dict) else None,
        )
    if action == "balance":
        return provider.balance()
    if action == "profile":
        return provider.profile()
    if action == "send":
        return provider.send(
            str(params.get("recipient") or ""),
            str(params.get("message") or ""),
            str(params.get("type") or "plain"),
            params.get("sender_id"),
            params.get("schedule_time"),
        )
    if action == "send_hashed":
        return provider.send_hashed(
            str(params.get("recipient") or ""),
            str(params.get("message") or ""),
            params.get("sender_id"),
            str(params.get("recipient_format") or "hashed"),
        )
    if action == "campaign":
        return provider.campaign(
            str(params.get("recipient") or ""),
            str(params.get("message") or ""),
            params.get("sender_id"),
            params.get("schedule_time"),
            params.get("dlt_template_id"),
        )
    if action == "queue_status":
        return provider.queue_status(str(params.get("uid") or ""))
    if action == "groups_list":
        return provider.groups_list()
    if action == "group_show":
        return provider.group_show(str(params.get("group_id") or ""))
    if action == "group_create":
        return provider.group_create(str(params.get("name") or ""))
    if action == "group_update":
        return provider.group_update(
            str(params.get("group_id") or ""), str(params.get("name") or "")
        )
    if action == "group_delete":
        return provider.group_delete(str(params.get("group_id") or ""))
    if action == "contact_store":
        return provider.contact_store(
            str(params.get("group_id") or ""),
            str(params.get("phone") or ""),
            str(params.get("first_name") or ""),
            str(params.get("last_name") or ""),
        )
    if action == "contact_search":
        return provider.contact_search(
            str(params.get("group_id") or ""), str(params.get("uid") or "")
        )
    if action == "contact_update":
        return provider.contact_update(
            str(params.get("group_id") or ""),
            str(params.get("uid") or ""),
            str(params.get("phone") or ""),
            str(params.get("first_name") or ""),
            str(params.get("last_name") or ""),
        )
    if action == "contact_delete":
        return provider.contact_delete(
            str(params.get("group_id") or ""), str(params.get("uid") or "")
        )
    if action == "messages_list":
        return provider.messages_list()
    if action == "message_get":
        return provider.message_get(str(params.get("uid") or ""))
    return SmsProvider._normalized(
        False, 0, "error", None, f"Unsupported provider action: {action}", ""
    )
