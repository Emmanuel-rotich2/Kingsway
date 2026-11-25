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

Web tools (web.*) are also executed locally: a Kenyan CBC school needs current
world information (KICD/KNEC/Ministry guidance, teaching resources) that the
school database cannot hold. They are outbound, so they are bounded hard and
journalled; they never carry school data because the only input is the
operator's own question.
"""

from __future__ import annotations

import json
import ipaddress
import os
import re
import ssl
import threading
import time
import urllib.error
import urllib.request
from collections import deque
from pathlib import Path
from typing import Any, Callable
from urllib.parse import urlparse

from . import mcp_client
from .mcp_client import McpClient
from .config import Config

ALLOWED_TOOLS = (
    "reports.nlq",
    "reports.aggregate",
    "reports.insight_brief",
    "assistant.catalog",
    "assistant.workspace_scan",
    # The agents' map of their own environment: pages, components, role
    # access and real journal usage, scoped to the operator's roles.
    "assistant.page_catalog",
    "assistant.page_activity",
    "charts.generate",
    "drafts.create",
    # web.* tools are outbound public-internet reads executed by the engine.
    "web.search",
    "web.fetch",
    "web.crawl",
    # Curated, deduplicated education-news digest (MoE/KICD/CBC/CBE + global
    # trends) built on the full public web; outbound like web.* above.
    "education.news",
    # Advisory public-content coworker: post ideas, headlines, events, a draft
    # and how-to guidance. Permission-gated and never publishes.
    "public_content.advisor",
    # Analytics: real figures from governed rows. The rows are already
    # authorized when they arrive here; the skill only draws them.
    "analytics.summary",
    "analytics.chart",
    "analytics.forecast",
    # Named, permission-scoped aggregate datasets released by PHP from the read
    # replica. No SQL or rows are accepted from the caller.
    "data.query",
    # Columnar analysis of the rows data.query (or a governed report) released,
    # executed in Python with Polars. It never opens a database connection and
    # never accepts caller-supplied rows.
    "data.polars",
    # System-administrator tools — guarded by the system_admin permission.
    "system.code_read",
    "system.log_read",
    "system.error_analyze",
    "system.fix_suggest",
)
VALID_CADENCES = ("daily", "weekly", "term")

# Tools that run inside Python instead of being relayed to the governed PHP
# data service. Split by guard: system.* needs the system_admin permission,
# web.* is open to every authenticated staff member but is outbound traffic
# and therefore rate-limited.
SYSTEM_TOOL_PREFIX = "system."
WEB_TOOL_PREFIX = "web."
WEB_TOOLS = ("web.search", "web.fetch", "web.crawl")
EDUCATION_NEWS_TOOL = "education.news"
PUBLIC_CONTENT_TOOL = "public_content.advisor"
# Real permission codes that authorize public-content advice. Advisory only,
# but generating copy for a school's public channels must still be scoped to
# staff who already hold a communications/website grant.
PUBLIC_CONTENT_PERMISSIONS = (
    "communications_all_permissions",
    "communications_announcements_create",
    "communications_announcements_generate",
    "communications_announcements_publish",
    "website_content_manage",
    "website_news_manage",
    "website_events_manage",
)
ANALYTICS_TOOLS = ("analytics.summary", "analytics.chart", "analytics.forecast")
# Document, slide and media producers. They render the rows PHP already
# released for this operator, never the database.
DOCUMENT_TOOL = "documents.produce"
DOCUMENT_FORMATS = ("pdf", "docx", "video", "audio")
# Governed tools whose result may contain the rows the engine analyses.
# data.query releases permission-scoped aggregate rows from PHP's read replica,
# so it joins the reports as a legitimate row source for Polars/charts.
ROW_RELEASING_TOOLS = (
    "reports.nlq",
    "reports.aggregate",
    "charts.generate",
    "data.query",
)
# How long an operator's released rows may be reused for a figure.
ANALYTICS_ROW_TTL_SECONDS = 300
ANALYTICS_CHART_TYPES = (
    "bar",
    "line",
    "pie",
    "doughnut",
    "area",
    "scatter",
    "histogram",
    "box",
)
ANALYTICS_MAX_ROWS = 40
# data.query releases NAMED, permission-scoped aggregate rows from PHP's read
# replica into the same short-lived cache the analytics tools consume.
DATA_QUERY_TOOL = "data.query"
# data.polars is a bounded transformation of already-authorized rows.
POLARS_TOOL = "data.polars"
POLARS_OPERATIONS = ("summary", "group_by", "top")
POLARS_AGGREGATIONS = ("count", "sum", "mean", "min", "max")
POLARS_MAX_RESULT_ROWS = 40
POLARS_MAX_COLUMN_CHARS = 120

# Outbound web search bounds. A staff question must never fan out into a
# scrape: one query, a bounded result count, a bounded character budget.
WEB_MAX_QUERY_CHARS = 200
WEB_MAX_RESULTS = 8
WEB_FETCH_TIMEOUT = 8

# An unreachable search engine must not be retried inside a single turn, or
# one flaky DNS lookup would burn the whole provider-call budget.
_WEB_FAILURE_COOLDOWN_SECONDS = 30

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


# Every external MCP tool carries this prefix, so a tool id that is not a
# governed tool, an analytics tool, a web tool or an mcp.* tool is refused
# before any dispatch decision is made.
MCP_TOOL_PREFIX = "mcp."


def mcp_tools_available(config: Config) -> dict[str, tuple[str, str]]:
    """External MCP tools a deployment has configured, dormant when none."""
    return McpClient(
        mcp_client.parse_servers(getattr(config, "mcp_servers_raw", "")), None
    ).known_tools()


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
        # monotonic deadline until which a failed web search is not retried.
        self._web_unavailable_until: float = 0.0
        # The most recent governed rows PHP released for an operator.
        #
        # The engine never queries the database. It can only analyse rows PHP
        # already authorised for that operator, and this cache holds exactly
        # those rows, keyed by operator id, so a figure can be drawn from the
        # data the assistant is already looking at without the model having to
        # copy rows back and forth through the prompt.
        self._recent_rows: dict[int, list[dict[str, Any]]] = {}
        self._recent_rows_at: dict[int, float] = {}
        # The last figure drawn for this operator, so a PDF or a short video can
        # include the same picture instead of drawing a second, different one.
        self._recent_chart: dict[int, str] = {}
        self._recent_chart_at: dict[int, float] = {}
        # External MCP consumption. Dormant until AI_MCP_SERVERS is configured,
        # in which case every callable external tool is exposed as
        # mcp.<server>.<tool> and dispatched through MCP_TOOL_PREFIX below.
        self.mcp = McpClient(
            mcp_client.parse_servers(getattr(config, "mcp_servers_raw", "")),
            journal,
        )
        # Direct, read-only database access, approved by the platform owner and
        # coordinated with PHP's governed PolarsDatasetService. Lazily built so
        # a deployment without DB credentials keeps using the PHP relay.
        self._dataset_service_cache = None
        self._access_resolver_cache = None

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
        # Web tools also run locally, but they reach the public internet, so
        # they are bounded separately and never carry school data or PHP.
        if str(tool or "").startswith("web."):
            return self._execute_web_tool(user_id, str(tool), tool_input or {})
        if str(tool or "") == EDUCATION_NEWS_TOOL:
            return self._execute_education_news_tool(tool_input or {})
        if str(tool or "") == PUBLIC_CONTENT_TOOL:
            return self._execute_public_content_tool(
                user_id, permissions, tool_input or {}
            )
        if str(tool or "") in ANALYTICS_TOOLS:
            return self._execute_analytics_tool(str(tool), tool_input or {}, user_id)
        if str(tool or "") == POLARS_TOOL:
            return self._execute_polars_tool(tool_input or {}, user_id)
        # Direct, read-only database access (approved by the platform owner).
        # When configured, data.query reads the named dataset straight from the
        # school database using the engine's own credentials, re-resolving the
        # operator's authority from their user id. PHP's governed relay remains
        # the fallback for deployments that have not enabled direct access.
        if str(tool or "") == DATA_QUERY_TOOL and self._direct_db_ready():
            direct = self._execute_direct_data_query(
                user_id, tool_input or {}, request_id
            )
            # The docstring promises it, and the governed relay is the safety
            # net: a misconfigured/unreachable direct database must not turn a
            # legitimate question into an "unavailable" answer. Fall through to
            # the PHP relay whenever the direct read did not succeed.
            if direct.get("status") in {"ok", "success", "answered", "ready"}:
                return direct
            # A permission or validation decision is final; only a read failure
            # ("unavailable") may fall back to the PHP relay. Otherwise a clear
            # "your role does not grant that" became a vague unknown_dataset.
            if direct.get("status") in {
                "not_authorized", "invalid_query", "unknown_source", "unknown_dataset"
            }:
                return direct
        # External MCP tools run locally too, but only the ones the operator
        # configured by name. The client re-checks its own allowlist, so a
        # tool id invented by the model is refused twice over.
        if str(tool or "").startswith(MCP_TOOL_PREFIX):
            return self._execute_mcp_tool(str(tool), tool_input or {})
        if str(tool or "") == DOCUMENT_TOOL:
            return self._execute_document_tool(tool_input or {}, user_id)
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
            self._remember_rows(user_id, tool, data)
            return data
        message = decoded.get("message") if isinstance(decoded, dict) else None
        return {
            "status": "unavailable",
            "message": str(
                message or "the governed data service rejected the tool request"
            )[:200],
        }

    # ------------------------------------------------------------------ MCP tools
    def _execute_mcp_tool(self, tool: str, tool_input: dict) -> dict[str, Any]:
        """Call one operator-configured external MCP tool.

        The ``mcp.<server>.<tool>`` id is split here, and the client refuses
        anything that is not on the operator's own allowlist for that server.
        The result is data for the model to reason over, never an instruction.
        """
        parts = tool.split(".", 2)
        if len(parts) != 3 or not parts[1] or not parts[2]:
            return {"status": "unknown_tool"}
        if not self.mcp.owns(tool):
            return {
                "status": "unavailable",
                "message": "that external tool is not enabled",
            }
        result = self.mcp.call(parts[1], parts[2], tool_input or {})
        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "mcp_tool_relay",
                    "tool": tool,
                    "status": str(result.get("status") or ""),
                },
            )
        return result

    # ------------------------------------------------------------------ web tools
    def _execute_web_tool(
        self, user_id: int, tool: str, tool_input: dict
    ) -> dict[str, Any]:
        """Search the public web for current world information.

        This is the capability the school asked for: a Kenyan CBC school needs
        the latest KICD/KNEC/Ministry guidance, teaching resources and news,
        none of which can live in the school database. Search results are
        untrusted public content, so they are bounded hard, journalled, and
        returned as pre-flattened strings that the answer guard can scrub.

        No school data ever leaves this process: the query is the only input,
        and it is the operator's own question text, already bounded by
        security.bound_question on the orchestrator side.
        """
        if tool not in WEB_TOOLS:
            return {"status": "unknown_tool"}

        # Imported lazily so a platform without the skill module still boots.
        from .skills.web_search import WebSearchSkill

        skill = WebSearchSkill(self.journal)

        if tool == "web.fetch":
            bounded = self._bound_input(tool, tool_input)
            url = bounded.get("url") or ""
            if not url:
                return {"status": "unavailable", "message": "a url is required"}
            return skill.fetch(url, bounded.get("max_chars", 6000))

        if tool == "web.crawl":
            bounded = self._bound_input(tool, tool_input)
            url = bounded.get("url") or ""
            if not url:
                return {"status": "unavailable", "message": "a url is required"}
            return skill.crawl(
                url,
                depth=bounded.get("depth", 1),
                max_pages=bounded.get("max_pages", 4),
                max_chars=bounded.get("max_chars", 2000),
                same_site=bounded.get("same_site", True),
            )

        now = time.monotonic()
        if self._web_unavailable_until > now:
            return {
                "status": "unavailable",
                "message": "web search is briefly unavailable after a failed attempt",
            }

        bounded = self._bound_input(tool, tool_input)
        query = bounded.get("query") or ""
        if not query:
            return {"status": "unavailable", "message": "a search query is required"}

        started = time.monotonic()
        try:
            results = skill.search(
                query,
                bounded.get("limit", WEB_MAX_RESULTS),
                fanout=bounded.get("fanout", False),
            )
        except Exception as error:  # noqa: BLE001 - search must never break a turn
            self._web_unavailable_until = (
                time.monotonic() + _WEB_FAILURE_COOLDOWN_SECONDS
            )
            if self.journal is not None:
                self.journal.write(
                    "ai_generation",
                    {
                        "type": "web_search_tool_failed",
                        "operator_id": user_id,
                        "error_class": type(error).__name__,
                    },
                )
            return {
                "status": "unavailable",
                "message": "web search could not be completed",
            }

        # Flatten to strings: the orchestrator's _compact_result only keeps
        # scalar list items, and a dict would silently vanish from the prompt.
        # The URL is embedded so the model cites provenance instead of
        # restating an unsourced claim.
        findings: list[str] = []
        sources: list[str] = []
        for item in results[:WEB_MAX_RESULTS]:
            title = str(item.get("title") or "").strip()
            url = str(item.get("url") or "").strip()
            snippet = str(item.get("snippet") or "").strip()
            if not url:
                continue
            label = title or url
            if snippet:
                label = f"{label} — {snippet}"
            findings.append(label[:400])
            sources.append(url[:300])

        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "web_search_tool",
                    "operator_id": user_id,
                    "result_count": len(findings),
                    "duration_ms": int((time.monotonic() - started) * 1000),
                },
            )

        if not findings:
            return {
                "status": "answered",
                "query": query[:WEB_MAX_QUERY_CHARS],
                "results": [],
                "source_count": 0,
                "summary": "no public results were found for this query",
            }
        return {
            "status": "answered",
            "query": query[:WEB_MAX_QUERY_CHARS],
            "results": findings,
            "sources": sources,
            "source_count": len(findings),
        }

    def _execute_education_news_tool(self, tool_input: dict) -> dict[str, Any]:
        """Curated, deduplicated MoE/KICD/CBC/CBE + global education digest.

        Outbound public read like web.*: no school data is an input, the topic
        label is restricted to a fixed set, and the skill deduplicates by
        canonical URL and bounds the result. The caller cannot supply raw
        queries or URLs.
        """
        bounded = self._bound_input(EDUCATION_NEWS_TOOL, tool_input)
        topic = str(bounded.get("topic") or "all")
        limit = int(bounded.get("limit") or 10)
        try:
            from .skills.education_news import EducationNewsSkill

            return EducationNewsSkill(self.journal).digest(topic, limit)
        except Exception as error:  # noqa: BLE001 - one tool must not fail a turn
            if self.journal is not None:
                self.journal.write(
                    "ai_generation",
                    {
                        "type": "education_news_failed",
                        "error_class": type(error).__name__,
                    },
                )
            return {
                "status": "unavailable",
                "message": "education news could not be retrieved right now",
            }

    def _execute_public_content_tool(
        self, user_id: int, permissions: list[str], tool_input: dict
    ) -> dict[str, Any]:
        """Advisory public-content ideas, headlines, events and a draft.

        Gate 1: the operator must hold a real communications/website grant.
        Gate 2: inputs are bounded by _bound_input. The skill publishes nothing
        and reads no school data, so there is no PHP data-layer call. Current
        headlines are opt-in via the same curated education.news digest.
        """
        held = set(str(p) for p in (permissions or []))
        if not held.intersection(PUBLIC_CONTENT_PERMISSIONS):
            if self.journal is not None:
                self.journal.write(
                    "ai_generation",
                    {
                        "type": "public_content_denied",
                        "operator_id": user_id,
                    },
                )
            return {
                "status": "not_authorized",
                "message": "public-content guidance requires a communications or website grant",
            }
        bounded = self._bound_input(PUBLIC_CONTENT_TOOL, tool_input)
        try:
            from .skills.education_news import EducationNewsSkill
            from .skills.public_content import PublicContentSkill

            wants_news = bool(bounded.get("include_news"))
            skill = PublicContentSkill(
                self.journal,
                news=EducationNewsSkill(self.journal).digest if wants_news else None,
            )
            return skill.advise(
                purpose=str(bounded.get("purpose") or "general"),
                audience=str(bounded.get("audience") or "parents and guardians"),
                channel=str(bounded.get("channel") or "website"),
                topic=str(bounded.get("topic") or ""),
                include_news=wants_news,
                limit=int(bounded.get("limit") or 5),
            )
        except Exception as error:  # noqa: BLE001 - one tool must not fail a turn
            if self.journal is not None:
                self.journal.write(
                    "ai_generation",
                    {
                        "type": "public_content_failed",
                        "error_class": type(error).__name__,
                    },
                )
            return {
                "status": "unavailable",
                "message": "public-content guidance is unavailable right now",
            }

    # ------------------------------------------------------- direct database access
    def _direct_db_ready(self) -> bool:
        return bool(
            getattr(self.config, "direct_db_access", False)
            and getattr(self.config, "direct_db_configured", False)
        )

    def _dataset_service(self):
        if self._dataset_service_cache is None:
            from .db_datasets import DirectDatasetService

            self._dataset_service_cache = DirectDatasetService(
                self.config, journal=self.journal
            )
        return self._dataset_service_cache

    def _access_resolver(self):
        if self._access_resolver_cache is None:
            from .db_datasets import UserAccessResolver

            self._access_resolver_cache = UserAccessResolver(self.config)
        return self._access_resolver_cache

    def _execute_direct_data_query(
        self, user_id: int, tool_input: dict, request_id: str = ""
    ) -> dict[str, Any]:
        """Read an allowlisted dataset directly from the school database.

        Authority is re-resolved from the user id in the database - the operator
        id is trusted because it comes from the authenticated PHP edge, never
        from the model. If the direct read fails, the caller falls back to the
        governed PHP relay rather than returning an error to the operator.
        """
        bounded = self._bound_input(DATA_QUERY_TOOL, tool_input)
        dataset = str(bounded.get("dataset") or "").strip().lower()
        # Resolve the operator's CURRENT permissions from the master database.
        # Model-supplied permission strings are ignored: only the user id, set
        # by the authenticated edge, can widen or narrow scope.
        access = self._access_resolver().resolve(user_id)
        # No verified staff user (e.g. the public assistant): no school data.
        effective = (
            list(access.get("permissions") or [])
            if access.get("status") == "ok"
            else []
        )
        if bounded.get("source"):
            result = self._dataset_service().query(bounded, effective, user_id)
            if result.get("status") == "ok":
                self._remember_rows(user_id, DATA_QUERY_TOOL, result)
            return result
        result = self._dataset_service().rows(dataset, effective, user_id)
        if result.get("status") == "ok":
            self._remember_rows(user_id, DATA_QUERY_TOOL, result)
        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "direct_data_query",
                    "operator_id": int(user_id or 0),
                    "dataset": dataset,
                    "status": str(result.get("status") or ""),
                },
            )
        return result

    # ------------------------------------------------------------------ analytics
    def _remember_rows(self, user_id: int, tool: str, data: dict[str, Any]) -> None:
        """Keep the governed rows PHP just released for this operator.

        Only reports release rows, only for the operator who asked, and the
        rows are replaced (never merged) so a figure can never mix two
        different scopes.
        """
        if tool not in ROW_RELEASING_TOOLS:
            return
        rows = data.get("rows")
        if not isinstance(rows, list) or not rows:
            return
        bounded = [
            {
                str(key): value
                for key, value in row.items()
                if isinstance(value, (int, float, str, bool)) or value is None
            }
            for row in rows[:ANALYTICS_MAX_ROWS]
            if isinstance(row, dict)
        ]
        if not bounded:
            return
        self._recent_rows[int(user_id)] = bounded
        self._recent_rows_at[int(user_id)] = time.monotonic()

    def _rows_for(self, user_id: int) -> list[dict[str, Any]]:
        """The rows this operator's last governed report released."""
        rows = self._recent_rows.get(int(user_id))
        if not rows:
            return []
        # A stale row set is worse than none: it would draw a figure of old
        # data. The cache is short-lived on purpose.
        seen_at = self._recent_rows_at.get(int(user_id), 0.0)
        if time.monotonic() - seen_at > ANALYTICS_ROW_TTL_SECONDS:
            return []
        return rows

    def _execute_analytics_tool(
        self, tool: str, tool_input: dict, user_id: int = 0
    ) -> dict[str, Any]:
        """Draw a real figure, statistics or a trend from authorized rows.

        The rows must come from a governed tool the caller already ran: this
        module never queries the database, so a chart can only ever contain
        what PHP authorised for that operator. Imported lazily so a deployment
        without matplotlib still boots and degrades to a numeric summary.
        """
        try:
            from .skills import analytics
        except ImportError:
            return {
                "status": "unavailable",
                "message": "the analytics libraries are not installed on this server",
            }

        bounded = self._bound_input(tool, tool_input)
        rows = bounded.get("rows")
        if not isinstance(rows, list) or not rows:
            # The assistant does not have to copy rows through the prompt: the
            # rows PHP authorised for this operator are already held here.
            rows = self._rows_for(user_id)
        if not isinstance(rows, list):
            rows = []
        rows = [row for row in rows if isinstance(row, dict)][:ANALYTICS_MAX_ROWS]
        if not rows:
            return {
                "status": "unavailable",
                "message": (
                    "run a governed report first so the figure is drawn from "
                    "authorized rows"
                ),
            }

        try:
            if tool == "analytics.chart":
                result = analytics.chart(
                    rows,
                    chart_type=bounded.get("chart_type", "bar"),
                    x=str(bounded.get("x") or ""),
                    y=str(bounded.get("y") or ""),
                    title=str(bounded.get("title") or ""),
                    focus=str(bounded.get("focus") or ""),
                )
                image = result.get("image") if isinstance(result, dict) else None
                if isinstance(image, str) and image.startswith(
                    "data:image/png;base64,"
                ):
                    self._recent_chart[int(user_id)] = image
                    self._recent_chart_at[int(user_id)] = time.monotonic()
                return result
            if tool == "analytics.forecast":
                return analytics.forecast(
                    rows,
                    periods=int(bounded.get("periods") or 3),
                    column=str(bounded.get("column") or ""),
                )
            return analytics.summarize(rows, focus=str(bounded.get("focus") or ""))
        except Exception as error:  # noqa: BLE001 - one tool must not fail the turn
            if self.journal is not None:
                self.journal.write(
                    "ai_generation",
                    {
                        "type": "analytics_tool_failed",
                        "tool": tool,
                        "error_class": type(error).__name__,
                    },
                )
            return {
                "status": "unavailable",
                "message": "that figure could not be drawn from the supplied rows",
            }

    def _execute_polars_tool(
        self, tool_input: dict, user_id: int = 0
    ) -> dict[str, Any]:
        """Bounded columnar analysis over the rows PHP already released.

        This is the governed answer to "let the agents use Polars on the
        database": the engine still never opens a database connection. The only
        input is the short-lived row cache populated by a report PHP authorized
        for this exact operator, and caller-supplied rows are deliberately
        ignored so a prompt cannot smuggle unauthorized data into the result.
        """
        rows = self._rows_for(user_id)
        if not rows:
            return {
                "status": "unavailable",
                "message": (
                    "run a governed report first so the analysis uses only rows "
                    "authorized for you"
                ),
            }
        bounded = self._bound_input(POLARS_TOOL, tool_input)
        operation = str(bounded.get("operation") or "summary")
        column = str(bounded.get("column") or "")
        agg = str(bounded.get("agg") or "count")

        try:
            import polars as pl
        except ImportError:
            return {
                "status": "unavailable",
                "message": "Polars is not installed on this server",
            }

        def clean_value(value: Any) -> Any:
            if value is None or isinstance(value, (bool, int, float)):
                return value
            return str(value)[:POLARS_MAX_COLUMN_CHARS]

        def clean_rows(frame: Any) -> list[dict[str, Any]]:
            return [
                {str(key): clean_value(value) for key, value in row.items()}
                for row in frame.to_dicts()[:POLARS_MAX_RESULT_ROWS]
            ]

        try:
            frame = pl.DataFrame(rows)
            if frame.is_empty():
                return {
                    "status": "unavailable",
                    "message": "the authorized report returned no rows",
                }

            if operation == "group_by":
                if not column or column not in frame.columns:
                    return {
                        "status": "invalid_column",
                        "message": "choose a column from the authorized report",
                        "columns": list(frame.columns)[:POLARS_MAX_RESULT_ROWS],
                    }
                if agg == "count":
                    result = frame.group_by(column).len().sort("len", descending=True)
                else:
                    value_column = str(bounded.get("value_column") or "")
                    numeric = [
                        name
                        for name, dtype in frame.schema.items()
                        if dtype.is_numeric()
                    ]
                    if value_column not in numeric:
                        value_column = numeric[0] if numeric else ""
                    if not value_column:
                        return {
                            "status": "invalid_column",
                            "message": "no numeric column is available to aggregate",
                            "columns": list(frame.columns)[:POLARS_MAX_RESULT_ROWS],
                        }
                    expression = getattr(pl.col(value_column), agg)()
                    result = frame.group_by(column).agg(expression).sort(column)
            elif operation == "top":
                if not column or column not in frame.columns:
                    return {
                        "status": "invalid_column",
                        "message": "choose a column from the authorized report",
                        "columns": list(frame.columns)[:POLARS_MAX_RESULT_ROWS],
                    }
                limit = int(bounded.get("limit") or 10)
                result = frame.sort(column, descending=True).head(limit)
            else:
                result = frame.describe()

            body = clean_rows(result)
            return {
                "status": "ok",
                "operation": operation,
                "columns": list(result.columns)[:POLARS_MAX_RESULT_ROWS],
                "rows": body,
                "row_count": len(body),
                "note": "Computed from permission-scoped aggregate rows PHP released for this operator; the AI engine never queried the database directly.",
            }
        except Exception as error:  # noqa: BLE001 - one tool must not fail a turn
            if self.journal is not None:
                self.journal.write(
                    "ai_generation",
                    {
                        "type": "polars_tool_failed",
                        "error_class": type(error).__name__,
                    },
                )
            return {
                "status": "unavailable",
                "message": "that analysis could not be completed from the supplied rows",
            }

    def _chart_for(self, user_id: int) -> str:
        """The figure already drawn for this operator, if it is still fresh."""
        image = self._recent_chart.get(int(user_id), "")
        if not image:
            return ""
        if (
            time.monotonic() - self._recent_chart_at.get(int(user_id), 0.0)
            > ANALYTICS_ROW_TTL_SECONDS
        ):
            return ""
        return image

    def _document_rows(
        self, user_id: int, tool_input: dict
    ) -> tuple[list[dict[str, Any]], list[str], str]:
        """The authorized rows, their columns and a title for a document.

        The rows come from the same short-lived cache a figure is drawn from,
        or from rows the caller passed explicitly. A document therefore can
        never contain a row the operator was not already shown.
        """
        bounded = self._bound_input(DOCUMENT_TOOL, tool_input)
        rows = bounded.get("rows")
        if not isinstance(rows, list) or not rows:
            rows = self._rows_for(user_id)
        rows = [row for row in (rows or []) if isinstance(row, dict)][
            :ANALYTICS_MAX_ROWS
        ]
        columns = bounded.get("columns")
        columns = [str(name) for name in columns] if isinstance(columns, list) else []
        title = str(bounded.get("title") or "").strip()
        return rows, columns, title

    def _execute_document_tool(
        self, tool_input: dict, user_id: int = 0
    ) -> dict[str, Any]:
        """Produce a PDF, DOCX, short video or audio summary from released rows.

        Every format is built in memory and returned as a data URI, so nothing
        is written to disk and no chat turn can create a durable copy of school
        records.
        """
        try:
            from .skills import documents
        except ImportError:
            return {
                "status": "unavailable",
                "message": "the document tools are not installed on this server",
            }
        rows, columns, title = self._document_rows(user_id, tool_input)
        fmt = str(tool_input.get("format") or "pdf").strip().lower()
        summary = str(tool_input.get("summary") or "").strip()
        subtitle = str(tool_input.get("subtitle") or "").strip()
        result = documents.produce(
            fmt,
            title=title or "School report",
            subtitle=subtitle,
            summary=summary,
            columns=columns,
            rows=rows,
            chart_data_uri=self._chart_for(user_id) if fmt in ("pdf", "video") else "",
        )
        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "document_tool",
                    "format": fmt,
                    "status": str(result.get("status") or ""),
                    "row_count": int(result.get("row_count") or 0),
                    "size_bytes": int(result.get("size_bytes") or 0),
                },
            )
        return result

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
        return self._system_redact(content)

    @staticmethod
    def _system_redact(text: str) -> str:
        """Remove common credentials and direct contact identifiers before model use."""
        value = str(text or "")
        value = re.sub(
            r"(?i)([\"']?\b(?:api[_-]?key|access[_-]?token|refresh[_-]?token|auth(?:orization)?|password|passwd|secret|client[_-]?secret|cookie|session(?:[_-]?id)?|learner[_-]?(?:name|number|no|id|phone|email)|student[_-]?(?:name|number|no|id|phone|email)|admission[_-]?(?:name|number|no|id)|parent[_-]?(?:name|phone|email)|guardian[_-]?(?:name|phone|email)|staff[_-]?(?:name|id|phone|email))\b[\"']?\s*[:=]\s*)(\"[^\"]*\"|'[^']*'|[^,\s}]+)",
            r'\1"[REDACTED]"',
            value,
        )
        value = re.sub(
            r"(?i)\bBearer\s+[A-Za-z0-9._~+/=-]+", "Bearer [REDACTED]", value
        )
        value = re.sub(
            r"\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{8,}\b",
            "[REDACTED_TOKEN]",
            value,
        )
        value = re.sub(
            r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}", "[REDACTED_EMAIL]", value
        )
        value = re.sub(
            r"(?<!\w)(?:\+?\d[\d ()-]{7,}\d)(?!\w)", "[REDACTED_PHONE]", value
        )
        return value

    def _system_code_read(self, path: str = "", max_chars: int = 0) -> dict[str, Any]:
        if not self._system_code_context_allowed():
            return {
                "status": "capability_disabled",
                "message": "Source-code context is disabled for this assistant configuration.",
            }
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
                lines = list(deque(handle, maxlen=tail))
        except OSError as error:
            return {"status": "error", "message": str(error)[:200]}
        selected = lines
        return {
            "status": "ok",
            "path": str(candidate_path.relative_to(log_dir)),
            "lines": [
                self._system_redact(line.rstrip("\n"))[:4000] for line in selected
            ],
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
                    lines = list(deque(handle, maxlen=limit * 4))
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
                text = self._system_redact(line.strip())[:4000]
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
        if not self._system_code_context_allowed():
            return {
                "status": "capability_disabled",
                "message": "Source-code context is disabled for this assistant configuration.",
            }
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
            "context": self._system_redact(str(context or "")[:1000]),
            "advice": (
                "Review the file above with the administrator. Suggest only "
                "bounded, reversible changes; do not rotate credentials or "
                "modify learner, health or family data."
            ),
        }

    def _system_code_context_allowed(self) -> bool:
        """Permit proprietary source context only for explicitly enabled local models."""
        if not getattr(self.config, "allow_system_code_context", False):
            return False
        if not getattr(self.config, "enabled", False):
            return False

        endpoints: list[str] = []
        primary = str(getattr(self.config, "provider_base_url", "") or "").strip()
        if primary:
            endpoints.append(primary)
        raw_fallbacks = str(getattr(self.config, "fallbacks_raw", "") or "[]")
        try:
            fallbacks = json.loads(raw_fallbacks)
        except json.JSONDecodeError:
            return False
        if not isinstance(fallbacks, list):
            return False
        for fallback in fallbacks[:4]:
            if not isinstance(fallback, dict):
                return False
            endpoint = str(fallback.get("base_url") or "").strip()
            model = str(fallback.get("model") or "").strip()
            if endpoint and model:
                endpoints.append(endpoint)
        if not endpoints:
            return False

        for endpoint in endpoints:
            hostname = (urlparse(endpoint).hostname or "").lower()
            if hostname == "localhost":
                continue
            try:
                if ipaddress.ip_address(hostname).is_loopback:
                    continue
            except ValueError:
                pass
            return False
        return True

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
        elif tool == "assistant.page_activity":
            route = str(tool_input.get("route") or "")[:160]
            if route:
                bounded["route"] = route
        elif tool == DATA_QUERY_TOOL:
            # A dataset NAME, or a structured source query. Never SQL or rows:
            # the query is compiled and column-checked in db_datasets.
            dataset = str(tool_input.get("dataset") or "").strip().lower()[:60]
            if dataset:
                bounded["dataset"] = dataset
            source = str(tool_input.get("source") or "").strip().lower()[:60]
            if source:
                bounded["source"] = source
                bounded["measure"] = str(tool_input.get("measure") or "count").strip().lower()[:20]
                column = str(tool_input.get("column") or "").strip()[:64]
                if column:
                    bounded["column"] = column
                group_by = tool_input.get("group_by")
                if isinstance(group_by, str):
                    group_by = [group_by]
                if isinstance(group_by, list):
                    bounded["group_by"] = [str(c).strip()[:64] for c in group_by[:3] if str(c).strip()]
                filters = tool_input.get("filters")
                if isinstance(filters, dict):
                    clean: dict[str, Any] = {}
                    for key, value in list(filters.items())[:6]:
                        if isinstance(value, list):
                            value = [v for v in value[:20] if isinstance(v, (str, int, float))]
                        elif not isinstance(value, (str, int, float)):
                            continue
                        clean[str(key).strip()[:64]] = value
                    bounded["filters"] = clean
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
        elif tool == EDUCATION_NEWS_TOOL:
            topic = str(tool_input.get("topic") or "all").strip().lower()
            bounded["topic"] = topic[:24] if topic else "all"
            try:
                bounded["limit"] = max(1, min(int(tool_input.get("limit") or 0), 10))
            except (TypeError, ValueError):
                bounded["limit"] = 10
        elif tool == PUBLIC_CONTENT_TOOL:
            bounded["purpose"] = (
                str(tool_input.get("purpose") or "general").strip().lower()[:24]
            )
            bounded["audience"] = str(
                tool_input.get("audience") or "parents and guardians"
            ).strip()[:60]
            bounded["channel"] = (
                str(tool_input.get("channel") or "website").strip().lower()[:24]
            )
            bounded["topic"] = str(tool_input.get("topic") or "").strip()[:160]
            bounded["include_news"] = bool(tool_input.get("include_news"))
            try:
                bounded["limit"] = max(1, min(int(tool_input.get("limit") or 0), 5))
            except (TypeError, ValueError):
                bounded["limit"] = 5
        elif tool == "web.search":
            query = str(tool_input.get("query") or "").strip()
            if query:
                bounded["query"] = query[:WEB_MAX_QUERY_CHARS]
            try:
                bounded["limit"] = max(
                    1, min(int(tool_input.get("limit") or 0), WEB_MAX_RESULTS)
                )
            except (TypeError, ValueError):
                bounded["limit"] = WEB_MAX_RESULTS
            # Broad/recall-first queries opt into the five-engine fan-out and
            # round-robin merge. Default stays the cheap strict provider chain.
            bounded["fanout"] = bool(tool_input.get("fanout"))
        elif tool == "web.fetch":
            url = str(tool_input.get("url") or "").strip()
            if url:
                bounded["url"] = url[:500]
            try:
                bounded["max_chars"] = max(
                    200, min(int(tool_input.get("max_chars") or 0), 8000)
                )
            except (TypeError, ValueError):
                bounded["max_chars"] = 6000
        elif tool == "web.crawl":
            url = str(tool_input.get("url") or "").strip()
            if url:
                bounded["url"] = url[:500]
            try:
                bounded["depth"] = max(0, min(int(tool_input.get("depth") or 0), 2))
            except (TypeError, ValueError):
                bounded["depth"] = 1
            try:
                bounded["max_pages"] = max(
                    1, min(int(tool_input.get("max_pages") or 0), 6)
                )
            except (TypeError, ValueError):
                bounded["max_pages"] = 4
            try:
                bounded["max_chars"] = max(
                    400, min(int(tool_input.get("max_chars") or 0), 4000)
                )
            except (TypeError, ValueError):
                bounded["max_chars"] = 2000
            bounded["same_site"] = bool(tool_input.get("same_site", True))
        elif tool == POLARS_TOOL:
            operation = str(tool_input.get("operation") or "summary")[:20]
            bounded["operation"] = (
                operation if operation in POLARS_OPERATIONS else "summary"
            )
            bounded["column"] = str(tool_input.get("column") or "")[
                :POLARS_MAX_COLUMN_CHARS
            ]
            bounded["value_column"] = str(tool_input.get("value_column") or "")[
                :POLARS_MAX_COLUMN_CHARS
            ]
            agg = str(tool_input.get("agg") or "count")[:20]
            bounded["agg"] = agg if agg in POLARS_AGGREGATIONS else "count"
            try:
                bounded["limit"] = max(
                    1, min(int(tool_input.get("limit") or 0), POLARS_MAX_RESULT_ROWS)
                )
            except (TypeError, ValueError):
                bounded["limit"] = 10
        elif tool.startswith("analytics."):
            # Only scalar rows survive: a nested object is dropped rather than
            # flattened, so a chart cannot become a disclosure channel.
            bounded["rows"] = [
                {
                    k: v
                    for k, v in row.items()
                    if isinstance(v, (int, float, str, bool)) or v is None
                }
                for row in (tool_input.get("rows") or [])[:ANALYTICS_MAX_ROWS]
                if isinstance(row, dict)
            ]
            if str(tool) == "analytics.chart":
                bounded["chart_type"] = (
                    str(tool_input.get("chart_type") or "bar")
                    if str(tool_input.get("chart_type") or "bar")
                    in ANALYTICS_CHART_TYPES
                    else "bar"
                )
            for key in ("x", "y", "column", "title", "focus"):
                value = str(tool_input.get(key) or "").strip()
                if value:
                    bounded[key] = value[:120]
            try:
                bounded["periods"] = max(
                    1, min(int(tool_input.get("periods") or 0), 12)
                )
            except (TypeError, ValueError):
                bounded["periods"] = 3
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
