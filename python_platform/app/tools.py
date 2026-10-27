"""Governed tool execution: Python agents never touch school data directly.

Every tool request is relayed to the PHP edge (POST /api/dashboard/agent-tool),
which re-authorizes the recorded operator against the tool's workflow and
executes it through the existing governed services (NLQ reports, deterministic
intelligence brief, workflow catalogue). This keeps ALL authorization,
row-level scope and audit inside PHP while ALL agent logic runs in Python.

System-administrator tools (system.*) are executed locally and are guarded
behind the ``system_admin`` permission. They read allowlisted repo and log
files so an administrator can diagnose the platform without a model ever
touching school data directly.
"""

from __future__ import annotations

import json
import os
import ssl
import threading
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any, Callable

from .config import Config

ALLOWED_TOOLS = (
    "reports.nlq",
    "reports.aggregate",
    "reports.insight_brief",
    "assistant.catalog",
    "assistant.workspace_scan",
    "charts.generate",
    "drafts.create",
    # System-administrator tools — guarded by the system_admin permission.
    "system.code_read",
    "system.log_read",
    "system.error_analyze",
    "system.fix_suggest",
)
VALID_CADENCES = ("daily", "weekly", "term")

# System tool permission: only an operator holding this permission may execute
# system.* tools. Checked in execute() before any file is read.
SYSTEM_ADMIN_PERMISSION = "system_admin"

# Repo files an administrator may read for diagnosis. Sensitive files (env,
# keys, certs, anything with secret/password/credential in the name) are
# always excluded even if they match an allowlisted directory.
_SYSTEM_ALLOWED_DIRS = ("api", "js", "python_platform", "config", "node_platform")
_SYSTEM_FORBIDDEN_NAMES = (
    ".env",
    ".env.",
    ".key",
    ".pem",
    ".crt",
    ".cer",
    ".pfx",
    ".p12",
)
_SYSTEM_FORBIDDEN_SUBSTRINGS = (
    "secret",
    "password",
    "credential",
    "api_key",
    "apikey",
    "private_key",
    "access_token",
    "refresh_token",
)
_SYSTEM_MAX_READ_CHARS = 12000
_SYSTEM_MAX_LOG_LINES = 400


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
        roles: list | None = None,
        role_ids: list[int] | None = None,
    ) -> dict[str, Any]:
        # System-administrator tools are executed locally and are guarded by
        # the system_admin permission. No other caller may reach them.
        if str(tool or "").startswith("system."):
            return self._execute_system_tool(
                user_id, permissions, str(tool), tool_input, request_id
            )
        if tool not in ALLOWED_TOOLS:
            return {"status": "unknown_tool"}
        operator: dict[str, Any] = {"user_id": user_id, "permissions": permissions}
        if roles:
            operator["roles"] = roles
        if role_ids:
            operator["role_ids"] = [int(r) for r in role_ids]
        payload = {
            "operator": operator,
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

    # ------------------------------------------------------------------ system tools
    def _execute_system_tool(
        self,
        user_id: int,
        permissions: list[str],
        tool: str,
        tool_input: dict,
        request_id: str,
    ) -> dict[str, Any]:
        """Execute a system-administrator tool locally.

        Only an operator holding the system_admin permission may reach this
        path. Every handler reads allowlisted files only and never writes.
        """
        if SYSTEM_ADMIN_PERMISSION not in set(str(p) for p in (permissions or [])):
            return {
                "status": "not_authorized",
                "message": "system tools require the system administrator permission",
            }
        bounded = self._bound_input(tool, tool_input or {})
        handler = {
            "system.code_read": self._system_code_read,
            "system.log_read": self._system_log_read,
            "system.error_analyze": self._system_error_analyze,
            "system.fix_suggest": self._system_fix_suggest,
        }.get(tool)
        if handler is None:
            return {"status": "unknown_tool"}
        try:
            result = handler(**bounded)
        except Exception as error:  # noqa: BLE001 - one tool must not fail the turn
            return {"status": "error", "message": str(error)[:200]}
        if isinstance(result, dict):
            result.setdefault("tool", tool)
        return result or {"status": "ok", "tool": tool}

    def _system_repo_root(self) -> Path:
        return Path(getattr(self.config, "repo_root", Path.cwd()))

    def _system_log_dir(self) -> Path:
        return Path(getattr(self.config, "log_dir", Path.cwd() / "logs"))

    @staticmethod
    def _system_name_safe(name: str) -> bool:
        lower = name.lower()
        if lower in _SYSTEM_FORBIDDEN_NAMES:
            return False
        return not any(sub in lower for sub in _SYSTEM_FORBIDDEN_SUBSTRINGS)

    def _system_allowed_path(self, path: str) -> Path | None:
        """Resolve a path to an allowlisted repo file, or None if not allowed."""
        try:
            candidate = (self._system_repo_root() / path).resolve(strict=False)
        except (OSError, ValueError):
            return None
        root = self._system_repo_root().resolve()
        try:
            candidate.relative_to(root)
        except ValueError:
            return None
        if not candidate.is_file():
            return None
        name = candidate.name.lower()
        if not self._system_name_safe(name):
            return None
        # Only allowlisted top-level directories may be read.
        try:
            rel = candidate.relative_to(root).parts
        except ValueError:
            return None
        if not rel or rel[0] not in _SYSTEM_ALLOWED_DIRS:
            return None
        return candidate

    def _system_read_file(self, path: Path, max_chars: int) -> str:
        max_chars = max(
            1, min(int(max_chars or _SYSTEM_MAX_READ_CHARS), _SYSTEM_MAX_READ_CHARS)
        )
        with open(path, "r", encoding="utf-8", errors="replace") as handle:
            content = handle.read(max_chars + 1)
        if len(content) > max_chars:
            content = content[:max_chars] + "\n…[truncated]"
        return content

    def _system_code_read(self, path: str = "", max_chars: int = 0) -> dict[str, Any]:
        if not path:
            return {"status": "error", "message": "path is required"}
        candidate = self._system_allowed_path(path)
        if candidate is None:
            return {
                "status": "not_authorized",
                "message": "path is not an allowlisted readable file",
            }
        content = self._system_read_file(
            candidate, int(max_chars or _SYSTEM_MAX_READ_CHARS)
        )
        return {
            "status": "ok",
            "path": str(candidate.relative_to(self._system_repo_root())),
            "size_chars": len(content),
            "content": content,
        }

    def _system_log_read(self, path: str = "", tail_lines: int = 0) -> dict[str, Any]:
        log_dir = self._system_log_dir()
        candidate_path = None
        if path:
            candidate = self._system_allowed_path(path)
            # Logs live under the log dir; allow any file there directly.
            try:
                direct = (log_dir / path).resolve(strict=False)
                if direct.is_file() and direct.relative_to(log_dir.resolve()):
                    candidate_path = direct
            except ValueError:
                candidate_path = None
        if candidate_path is None:
            # Default to the AI generation journal if no path given.
            default_log = log_dir / "ai_generation.jsonl"
            if default_log.is_file():
                candidate_path = default_log
        if candidate_path is None or not candidate_path.is_file():
            return {"status": "error", "message": "log file not found"}
        tail = max(
            1, min(int(tail_lines or _SYSTEM_MAX_LOG_LINES), _SYSTEM_MAX_LOG_LINES)
        )
        try:
            with open(
                candidate_path, "r", encoding="utf-8", errors="replace"
            ) as handle:
                lines = handle.readlines()
        except OSError as error:
            return {"status": "error", "message": str(error)[:200]}
        selected = lines[-tail:] if tail < len(lines) else lines
        return {
            "status": "ok",
            "path": str(candidate_path.relative_to(log_dir)),
            "lines": [line.rstrip("\n") for line in selected],
            "line_count": len(selected),
        }

    def _system_error_analyze(self, limit: int = 0) -> dict[str, Any]:
        log_dir = self._system_log_dir()
        limit = max(1, min(int(limit or 50), 200))
        signatures: dict[str, int] = {}
        samples: list[str] = []
        for log_file in sorted(log_dir.glob("*.jsonl")):
            try:
                with open(log_file, "r", encoding="utf-8", errors="replace") as handle:
                    lines = handle.readlines()
            except OSError:
                continue
            for line in lines[-(limit * 4) :]:
                lower = line.lower()
                if (
                    "error" not in lower
                    and "exception" not in lower
                    and "failed" not in lower
                ):
                    continue
                text = line.strip()
                signature = text[:120]
                signatures[signature] = signatures.get(signature, 0) + 1
                if len(samples) < limit:
                    samples.append(text[:500])
        ranked = sorted(signatures.items(), key=lambda item: (-item[1], item[0]))[
            :limit
        ]
        return {
            "status": "ok",
            "error_signatures": [
                {"signature": sig, "count": count} for sig, count in ranked
            ],
            "recent_samples": samples[:limit],
        }

    def _system_fix_suggest(self, path: str = "", context: str = "") -> dict[str, Any]:
        if not path:
            return {"status": "error", "message": "path is required"}
        candidate = self._system_allowed_path(path)
        if candidate is None:
            return {
                "status": "not_authorized",
                "message": "path is not an allowlisted readable file",
            }
        content = self._system_read_file(candidate, int(_SYSTEM_MAX_READ_CHARS / 2))
        return {
            "status": "ok",
            "path": str(candidate.relative_to(self._system_repo_root())),
            "content": content,
            "context": str(context or "")[:1000],
            "advice": (
                "Review the file above with the administrator. Suggest only "
                "bounded, reversible changes; do not rotate credentials or "
                "modify learner, health or family data."
            ),
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
        elif tool == "reports.aggregate":
            question = str(tool_input.get("question") or "")[:500]
            agg_type = str(tool_input.get("agg_type") or "count")[:20]
            if question and agg_type in {"count", "sum", "avg", "min", "max"}:
                bounded["question"] = question
                bounded["agg_type"] = agg_type
        elif tool == "reports.insight_brief":
            cadence = str(tool_input.get("cadence") or "daily")
            bounded["cadence"] = cadence if cadence in VALID_CADENCES else "daily"
        elif tool == "assistant.workspace_scan":
            route = str(tool_input.get("route") or "dashboard")[:120]
            bounded["route"] = route
        elif tool == "charts.generate":
            question = str(tool_input.get("question") or "")[:500]
            chart_type = str(tool_input.get("chart_type") or "bar")[:20]
            if question and chart_type in {"bar", "line", "pie", "doughnut", "area"}:
                bounded["question"] = question
                bounded["chart_type"] = chart_type
        elif tool == "drafts.create":
            template = str(tool_input.get("template") or "")[:60]
            context = str(tool_input.get("context") or "")[:2000]
            if template:
                bounded["template"] = template
                bounded["context"] = context
        elif tool == "system.code_read":
            bounded["path"] = str(tool_input.get("path") or "")[:200]
            try:
                bounded["max_chars"] = max(
                    1,
                    min(int(tool_input.get("max_chars") or 0), _SYSTEM_MAX_READ_CHARS),
                )
            except (TypeError, ValueError):
                bounded["max_chars"] = 0
        elif tool == "system.log_read":
            bounded["path"] = str(tool_input.get("path") or "")[:200]
            try:
                bounded["tail_lines"] = max(
                    1,
                    min(int(tool_input.get("tail_lines") or 0), _SYSTEM_MAX_LOG_LINES),
                )
            except (TypeError, ValueError):
                bounded["tail_lines"] = 0
        elif tool == "system.error_analyze":
            try:
                bounded["limit"] = max(1, min(int(tool_input.get("limit") or 0), 200))
            except (TypeError, ValueError):
                bounded["limit"] = 0
        elif tool == "system.fix_suggest":
            bounded["path"] = str(tool_input.get("path") or "")[:200]
            bounded["context"] = str(tool_input.get("context") or "")[:1000]
        return bounded
