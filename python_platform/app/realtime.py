"""Python -> Node.js realtime event publish path.

Mirror of the PHP RealtimeGatewayPublisher: completed batch jobs report
telemetry (JOB_COMPLETE/JOB_FAILED) to the gateway over a non-blocking
internal HTTP POST. The gateway fans the event out to connected browsers
over SSE; this module never talks to a browser.

Environment-agnostic by design: the address is resolved per call -- the
loopback base URL + Host header is tried first (NODE_REALTIME_INTERNAL_BASE_URL
+ NODE_REALTIME_INTERNAL_HOST), the public NODE_REALTIME_URL is the fallback --
so the exact same code runs on localhost and on the HostAfrica deployment.

Safety rules (identical to the PHP side):
  - Payloads carry change descriptors (job_id, status, domain) only; never
    learner/payment/health/case record bodies.
  - A real HTTP status (including 4xx/5xx) is an answer and is never retried
    against the other address; only a connection-level failure falls forward,
    so one failure cannot become two publishes.
  - Fire-and-forget with a tight timeout: a realtime outage must never slow
    down or fail the queue loop. publish() returns False, it never raises.
  - Dormant until routing keys are configured; publishing with no address is
    a no-op that returns False.
"""

from __future__ import annotations

import json
import re
import ssl
import urllib.error
import urllib.request
from datetime import datetime, timezone

TYPE_PATTERN = re.compile(r"^[A-Z][A-Z0-9_]{1,63}$")
SCOPE_PATTERN = re.compile(r"^[a-zA-Z0-9:_-]{1,128}$")
MAX_PAYLOAD_BYTES = 16384
PUBLISH_TIMEOUT_SECONDS = 2

# Mirror of the Node gateway descriptor allowlist (node_platform/src/events.js):
# enforcing it client-side means a malformed descriptor never consumes a
# request or a queue slot.
PAYLOAD_FIELDS = frozenset(
    {
        "id",
        "ids",
        "entity_id",
        "record_id",
        "job_id",
        "version",
        "progress",
        "status",
        "targets",
        "changed_fields",
        "domain",
        "action",
        "method",
    }
)


def _is_descriptor(payload) -> bool:
    if not isinstance(payload, dict) or isinstance(payload, list):
        return False
    if len(payload) > 20:
        return False
    for key, value in payload.items():
        if key not in PAYLOAD_FIELDS:
            return False
        if isinstance(value, list):
            if len(value) > 100:
                return False
            if not all(
                (isinstance(item, str) and len(item) <= 128)
                or (isinstance(item, int) and not isinstance(item, bool) and item >= 0)
                for item in value
            ):
                return False
            continue
        if value is None or isinstance(value, bool):
            continue
        if isinstance(value, int):
            if value < 0:
                return False
            continue
        if isinstance(value, str):
            if len(value) > 256:
                return False
            continue
        return False
    return True


class RealtimePublisher:
    """Publishes change descriptors to the Node realtime gateway."""

    def __init__(self, config, transport=None) -> None:
        self.config = config
        self._transport = transport

    # -- address resolution -------------------------------------------------

    def _candidates(self) -> list[tuple[str, str | None]]:
        candidates: list[tuple[str, str | None]] = []
        internal = (self.config.node_realtime_internal_base_url or "").rstrip("/")
        if internal:
            host = (self.config.node_realtime_internal_host or "").strip()
            candidates.append((internal, host or None))
        for key in ("node_realtime_url", "node_realtime_public_url"):
            public = (getattr(self.config, key, "") or "").rstrip("/")
            if public:
                candidates.append((public, None))
                break
        return candidates

    def configured(self) -> bool:
        return bool(self._candidates())

    # -- publish ------------------------------------------------------------

    def publish(self, event_type: str, scope: str, payload: dict) -> bool:
        if not TYPE_PATTERN.match(event_type or "") or not SCOPE_PATTERN.match(
            scope or ""
        ):
            return False
        if not _is_descriptor(payload if payload is not None else {}):
            return False

        body = json.dumps(
            {
                "type": event_type,
                "scope": scope,
                "payload": payload or {},
                "emitted_at": datetime.now(timezone.utc).isoformat(),
            },
            ensure_ascii=False,
        )
        if len(body.encode("utf-8")) > MAX_PAYLOAD_BYTES:
            return False

        candidates = self._candidates()
        if not candidates:
            return False

        last_status = 0
        for base, host in candidates:
            # A real HTTP status is a genuine answer, including 4xx/5xx: those
            # must not be retried against a different address. Only a
            # connection failure falls through to the next candidate.
            try:
                status = self._send(base, host, body)
                last_status = status
                return 200 <= status < 300
            except Exception:
                continue
        return False if last_status == 0 else 200 <= last_status < 300

    def _send(self, base: str, host: str | None, body: str) -> int:
        if self._transport is not None:
            raw, status = self._transport(base + "/internal/publish", body, host)
            return int(status)

        request = urllib.request.Request(
            base + "/internal/publish", data=body.encode("utf-8"), method="POST"
        )
        request.add_header("Content-Type", "application/json")
        request.add_header("Accept", "application/json")
        if host:
            request.add_header("Host", host)
        if self.config.node_realtime_publish_secret:
            request.add_header(
                "X-Kingsway-Worker-Secret", self.config.node_realtime_publish_secret
            )
        is_secure = base.lower().startswith("https://")
        context = (
            ssl._create_unverified_context()
            if is_secure and not self.config.node_realtime_verify_tls
            else None
        )
        with urllib.request.urlopen(
            request, timeout=PUBLISH_TIMEOUT_SECONDS, context=context
        ) as response:
            response.read()
            return int(response.status)
