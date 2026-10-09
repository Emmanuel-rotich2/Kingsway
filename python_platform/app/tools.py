"""Governed tool execution: Python agents never touch school data directly.

Every tool request is relayed to the PHP edge (POST /api/dashboard/agent-tool),
which re-authorizes the recorded operator against the tool's workflow and
executes it through the existing governed services (NLQ reports, deterministic
intelligence brief, workflow catalogue). This keeps ALL authorization,
row-level scope and audit inside PHP while ALL agent logic runs in Python.
"""

from __future__ import annotations

import json
import ssl
import urllib.error
import urllib.request
from typing import Any, Callable

from .config import Config

ALLOWED_TOOLS = (
    "reports.nlq",
    "reports.insight_brief",
    "assistant.catalog",
    "assistant.workspace_scan",
)
VALID_CADENCES = ("daily", "weekly", "term")


class ToolError(RuntimeError):
    pass


class ToolBridge:
    def __init__(
        self,
        config: Config,
        journal=None,
        transport: Callable[[str, str, dict, str, int], tuple[Any, int, str]]
        | None = None,
    ) -> None:
        self.config = config
        self.journal = journal
        self._transport = transport
        # Remembers which address answered, mirroring InterServiceClient in PHP.
        self._link: tuple[str, str] | None = None

    def _candidates(self) -> list[tuple[str, str]]:
        """(base_url, host_header) pairs, internal loopback first."""
        ordered: list[tuple[str, str]] = []
        if self.config.php_internal_base_url:
            ordered.append(
                (
                    self.config.php_internal_base_url.rstrip("/"),
                    self.config.php_internal_host,
                )
            )
        ordered.append((self.config.php_base_url.rstrip("/"), ""))
        if self._link is not None:
            winner = self._link[0]
            ordered.sort(key=lambda candidate: candidate[0] != winner)
        return ordered

    def _send(self, endpoint: str, body: str) -> tuple[Any, int, str, str]:
        """POST to the PHP edge, falling forward to the public URL on a
        connection-level failure. Returns (raw, status, error, link)."""
        last: tuple[Any, int, str, str] = (None, 0, "no address configured", "")
        for base, host in self._candidates():
            url = base + endpoint
            # A real HTTP status is a genuine answer, including 4xx/5xx: those
            # must not be retried against a different address. Only a
            # connection failure falls through to the next candidate.
            try:
                if self._transport is not None:
                    raw, status, error = self._transport(
                        url,
                        "POST",
                        {"X-Kingsway-Worker-Secret": self.config.php_worker_secret},
                        body,
                        30,
                    )
                    last = (raw, int(status), error, host or base)
                else:
                    request = urllib.request.Request(
                        url, data=body.encode("utf-8"), method="POST"
                    )
                    request.add_header("Content-Type", "application/json")
                    request.add_header("Accept", "application/json")
                    if host:
                        request.add_header("Host", host)
                    if self.config.php_worker_secret:
                        request.add_header(
                            "X-Kingsway-Worker-Secret", self.config.php_worker_secret
                        )
                    context = (
                        ssl._create_unverified_context()
                        if not self.config.php_verify_tls
                        else None
                    )
                    with urllib.request.urlopen(
                        request, timeout=40, context=context
                    ) as response:
                        raw, status, error = (
                            response.read().decode("utf-8", "replace"),
                            response.status,
                            "",
                        )
                    last = (raw, status, error, host or base)
            except urllib.error.HTTPError as http_error:
                try:
                    raw = http_error.read().decode("utf-8", "replace")
                except OSError:
                    raw = ""
                last = (raw, int(http_error.code), "", host or base)
            except (urllib.error.URLError, TimeoutError, OSError):
                continue

            if int(last[1]) > 0:
                self._link = (base, host)
                return last

        return last

    def execute(
        self,
        user_id: int,
        permissions: list[str],
        tool: str,
        tool_input: dict,
        request_id: str = "",
    ) -> dict[str, Any]:
        if tool not in ALLOWED_TOOLS:
            return {"status": "unknown_tool"}
        payload = {
            "operator": {"user_id": user_id, "permissions": permissions},
            "tool": tool,
            "tool_input": self._bound_input(tool, tool_input),
            "request_id": request_id[:100],
        }
        body = json.dumps(payload, ensure_ascii=False)
        endpoint = "/api/dashboard/agent-tool"
        raw, status, error, link = self._send(endpoint, body)

        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "agent_tool_relay",
                    "tool": tool,
                    "http_status": int(status or 0),
                    "link": link if not link.startswith("http") else "public",
                },
            )

        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "agent_tool_relay",
                    "tool": tool,
                    "http_status": int(status or 0),
                },
            )

        if not isinstance(raw, str) or not raw:
            return {
                "status": "unavailable",
                "message": "the governed data service did not respond",
            }
        try:
            decoded = json.loads(raw)
        except json.JSONDecodeError:
            return {
                "status": "unavailable",
                "message": "the governed data service returned an invalid response",
            }
        success = decoded.get("success", decoded.get("ok"))
        data = decoded.get("data", decoded)
        if (
            int(status or 0) >= 200
            and int(status or 0) < 300
            and success is not False
            and isinstance(data, dict)
        ):
            return data
        message = decoded.get("message") if isinstance(decoded, dict) else None
        return {
            "status": "unavailable",
            "message": str(
                message or "the governed data service rejected the tool request"
            )[:200],
        }

    @staticmethod
    def _bound_input(tool: str, tool_input: dict) -> dict:
        bounded: dict[str, Any] = {}
        if not isinstance(tool_input, dict):
            return bounded
        if tool == "reports.nlq":
            question = str(tool_input.get("question") or "")[:500]
            if question:
                bounded["question"] = question
        elif tool == "reports.insight_brief":
            cadence = str(tool_input.get("cadence") or "daily")
            bounded["cadence"] = cadence if cadence in VALID_CADENCES else "daily"
        elif tool == "assistant.workspace_scan":
            route = str(tool_input.get("route") or "dashboard")[:120]
            bounded["route"] = route
        return bounded
