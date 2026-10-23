"""Agent orchestration: triage -> bounded tool loop -> structured synthesis.

Control flow stays deterministic Python; the model only ever proposes a
strict-JSON action (tool or final). Every proposal is validated against the
agent's tool allowlist, and every tool executes through the PHP governance
edge. Bounded: max 2 tool steps, max 3 provider calls per assist.
"""

from __future__ import annotations

import concurrent.futures
import json
import re
import time
from datetime import datetime
from typing import Any
from zoneinfo import ZoneInfo

from . import agents, guard, output_guard, policy
from .behavior import BehaviorStore
from .chat_sessions import ChatSessionStore
from .conversation import ConversationStore
from .journal import Journal, hash_text
from .providers import Provider, ProviderError
from .tools import ToolBridge, SYSTEM_ADMIN_PERMISSION

MAX_TOOL_STEPS = 2
MAX_TOOL_EXECUTIONS = 6
MAX_PROVIDER_CALLS = 2
# Retained follow-up turns supplied by the client for conversational context.
MAX_HISTORY_TURNS = 12
CONVERSATION_PROMPT_TURNS = 12
HISTORY_MESSAGE_CHARS = 1200
HISTORY_CHAR_BUDGET = 5000
SUMMARY_LINES = 8
MAX_TOOLS_PER_STEP = 3
MAX_TOOL_RESULT_CHARS = 4000
TOOL_RESULT_FIELDS = (
    "status",
    "report_code",
    "report_title",
    "row_count",
    "as_of",
    "summary",
    "alert_count",
    "alerts",
    "metrics",
    "workflows",
    "message",
    "reason",
    "columns",
    "rows",
    "explanation",
    "intent",
    "preview_limited",
)


def _decode_partial_json_string(value: str, cursor: int) -> tuple[str, int, bool]:
    """Decode complete JSON string characters without waiting for its closing quote."""
    output: list[str] = []
    index = cursor
    escapes = {
        '"': '"',
        "\\": "\\",
        "/": "/",
        "b": "\b",
        "f": "\f",
        "n": "\n",
        "r": "\r",
        "t": "\t",
    }
    while index < len(value):
        char = value[index]
        if char == '"':
            return "".join(output), index + 1, True
        if char != "\\":
            output.append(char)
            index += 1
            continue
        if index + 1 >= len(value):
            break
        escape = value[index + 1]
        if escape in escapes:
            output.append(escapes[escape])
            index += 2
            continue
        if escape != "u" or index + 6 > len(value):
            break
        try:
            codepoint = int(value[index + 2 : index + 6], 16)
        except ValueError:
            break
        index += 6
        if 0xD800 <= codepoint <= 0xDBFF:
            if index + 6 > len(value) or value[index : index + 2] != "\\u":
                index -= 6
                break
            try:
                low = int(value[index + 2 : index + 6], 16)
            except ValueError:
                index -= 6
                break
            if not 0xDC00 <= low <= 0xDFFF:
                index -= 6
                break
            codepoint = 0x10000 + ((codepoint - 0xD800) << 10) + (low - 0xDC00)
            index += 6
        elif 0xDC00 <= codepoint <= 0xDFFF:
            codepoint = 0xFFFD
        output.append(chr(codepoint))
    return "".join(output), index, False


class Orchestrator:
    def __init__(
        self,
        provider: Provider,
        tools: ToolBridge,
        behavior: BehaviorStore,
        journal: Journal,
        conversation: ConversationStore | None = None,
        chat_sessions: ChatSessionStore | None = None,
    ) -> None:
        self.provider = provider
        self.tools = tools
        self.behavior = behavior
        self.journal = journal
        # Optional so tests and ephemeral contexts keep working unchanged.
        self.conversation = conversation
        self.chat_sessions = chat_sessions
        # Provider carries the runtime Config; tolerate stub providers in tests.
        self.config = getattr(provider, "config", None)

    # ------------------------------------------------------------------ assist
    def assist(self, context: dict, question: str) -> dict[str, Any]:
        from .security import bound_question

        question_raw = question
        question_clean, slash_instr = guard.extract_slash(question_raw)
        question = bound_question(question_clean or question_raw)
        instruction = slash_instr or (context.get("instruction") or "")
        context = {**context, "instruction": instruction}
        user_id = int(context["user_id"])
        permissions = list(context["permissions"])
        route = context.get("route", "")
        module = context.get("module", "dashboard")
        request_id = context.get("request_id", "")
        session_id = str(context.get("session_id") or "")
        assistant_domain = str(context.get("assistant_domain") or "staff")

        # Intent-first agent selection (same as stream_answer)
        agent = None
        hinted = agents.route_hint_for(question)
        if hinted is not None:
            agent = agents.AGENTS.get(hinted[0])
        if agent is None:
            agent = agents.for_route(route)
        if agent is None:
            agent = agents.default_agent()

        # Deterministic pre-flight, before any provider call:
        #   - a confidentiality probe is refused locally and never reaches a
        #     model, so school/Angisoft internals cannot be exfiltrated by
        #     prompt injection;
        #   - small talk is answered locally, so a greeting costs ~0ms
        #     instead of a full model round-trip.
        started_guard = time.monotonic()
        if guard.is_confidential(question):
            self._observe_outcome(user_id, route, module, agent, [], "refused")
            self.journal.write(
                "ai_generation",
                {
                    "type": "agent_confidentiality_refusal",
                    "operator_id": user_id,
                    "route": route,
                    "module": module,
                    "question_hash": hash_text(question),
                    "duration_ms": int((time.monotonic() - started_guard) * 1000),
                    "provider_calls": 0,
                    "request_id": request_id,
                },
            )
            return guard.confidential_response(agent)
        if guard.is_smalltalk(question):
            self._observe_outcome(user_id, route, module, agent, [], "instant")
            self.journal.write(
                "ai_generation",
                {
                    "type": "agent_instant_reply",
                    "operator_id": user_id,
                    "route": route,
                    "module": module,
                    "question_hash": hash_text(question),
                    "duration_ms": int((time.monotonic() - started_guard) * 1000),
                    "provider_calls": 0,
                    "request_id": request_id,
                },
            )
            return guard.greeting_response(question, agent)

        # Resolve or create chat session
        session = None
        if self.chat_sessions is not None:
            if session_id:
                session = self.chat_sessions.get_session(
                    user_id, session_id, limit=1, assistant_domain=assistant_domain
                )
            if session is None:
                session = self.chat_sessions.create(
                    user_id, route, assistant_domain=assistant_domain
                )
            session_id = session["id"]
            context = {**context, "session_id": session_id}
            self._merge_incoming_topics(user_id, session_id, context)

        prefs = self._preferences(user_id, context)

        routing = "route_match"
        if agent is None:
            # Deterministic lexical routing before any provider call: a clear
            # keyword signal ("recap today's attendance") resolves in
            # microseconds instead of spending a full model round-trip.
            hinted = agents.route_hint_for(question)
            if hinted is not None:
                agent = agents.AGENTS.get(hinted[0])
                routing = "lexical"
        if agent is None:
            agent = self._triage(question, route, module)
            routing = "triage"
        if agent is None:
            agent = agents.default_agent()
            routing = "default"

        # The system_admin agent and its file/log tools are reserved for
        # system administrators only. A non-admin request that lands here is
        # demoted to the default agent; an explicit admin-mode request from a
        # verified administrator is honoured.
        wants_admin = bool(context.get("system_admin"))
        if agent["id"] == "system_admin.agent":
            if not (wants_admin and self._is_system_admin(context)):
                agent = agents.default_agent()
                routing = "not_authorized" if wants_admin else routing
        elif wants_admin and self._is_system_admin(context):
            resolved = agents.resolve("system_admin.agent")
            if resolved is not None:
                agent = resolved
                routing = "admin_mode"

        # Assistant domain routing: explicit persona from the frontend.
        # system_admin -> system_admin.agent, parent -> parent.agent,
        # public -> public.agent, staff -> use normal staff routing.
        assistant_domain = str(context.get("assistant_domain") or "").lower()
        if assistant_domain and agent["id"] != "system_admin.agent":
            domain_agent_map = {
                "system_admin": "system_admin.agent",
                "parent": "parent.agent",
                "public": "public.agent",
                "staff": None,  # normal staff routing below
            }
            target = domain_agent_map.get(assistant_domain)
            if target:
                resolved = agents.resolve(target)
                if resolved is not None:
                    agent = resolved
                    routing = f"domain:{assistant_domain}"
                else:
                    agent = agents.default_agent()
                    routing = "default"

        self.behavior.observe(
            user_id,
            "question",
            {
                "route": route,
                "module": module,
                "domain": agent["domain"],
                "agent_id": agent["id"],
            },
        )

        started = time.monotonic()
        result = self._run_agent_loop(agent, context, question)

        # Record turn to session
        self._remember_session(
            session_id,
            user_id,
            route,
            question,
            result.get("answer"),
            save_history=bool(prefs.get("save_history", True)),
            assistant_domain=assistant_domain,
        )

        self.behavior.observe(
            user_id,
            "assist_outcome",
            {
                "agent_id": agent["id"],
                "tool": ",".join(result["tools_used"]),
                "outcome": result["status"],
            },
        )
        self.journal.write(
            "ai_generation",
            {
                "type": "agent_assist",
                "operator_id": user_id,
                "agent_id": agent["id"],
                "routing": routing,
                "question_hash": hash_text(question),
                "outcome": result["status"],
                "tools_used": result["tools_used"],
                "provider_calls": result["provider_calls"],
                "duration_ms": int((time.monotonic() - started) * 1000),
                "request_id": request_id,
            },
        )

        # Include session info in response
        session_payload = {}
        if session_id:
            session_payload = {
                "session_id": session_id,
                "session_title": session.get("title") if session else "",
            }

        return {
            **result,
            "routing": routing,
            "agent": {
                "id": agent["id"],
                "name": agent["name"],
                "domain": agent["domain"],
            },
            **session_payload,
        }

    def _observe_outcome(
        self,
        user_id: int,
        route: str,
        module: str,
        agent: dict[str, Any] | None,
        tools_used: list[str],
        outcome: str,
    ) -> None:
        """Record the question + outcome pair in the behaviour study.

        Only allowlisted scalar facts are stored (see BehaviorStore); no
        question text and no learner data ever reaches the buffer.
        """
        self.behavior.observe(
            user_id,
            "question",
            {
                "route": route,
                "module": module,
                "domain": (agent or {}).get("domain", ""),
                "agent_id": (agent or {}).get("id", ""),
            },
        )
        self.behavior.observe(
            user_id,
            "assist_outcome",
            {
                "agent_id": (agent or {}).get("id", ""),
                "tool": ",".join(tools_used),
                "outcome": outcome,
            },
        )

    # ------------------------------------------------------------------ digest
    def digest(
        self,
        user_id: int,
        permissions: list[str],
        cadence: str,
        request_id: str = "",
        broadcast: bool = False,
    ) -> dict[str, Any]:
        if cadence not in ("daily", "weekly", "term"):
            cadence = "daily"
        brief = self.tools.execute(
            user_id,
            permissions,
            "reports.insight_brief",
            {"cadence": cadence},
            request_id,
        )
        hints = self.behavior.hints(user_id)
        digest = {
            "status": "ready",
            "generated_at": time.strftime("%Y-%m-%dT%H:%M:%S+00:00", time.gmtime()),
            "cadence": cadence,
            "hints": hints,
            "insight_brief": {
                "status": str(brief.get("status") or "unavailable"),
                "alert_count": int(brief.get("alert_count") or 0),
                "alerts": list(brief.get("alerts") or [])[:10],
                "metrics": list(brief.get("metrics") or [])[:10],
                "as_of": str(brief.get("as_of") or ""),
            },
        }
        self.journal.write(
            "ai_generation",
            {
                "type": "agent_digest_ready",
                "operator_id": user_id,
                "cadence": cadence,
                "alert_count": digest["insight_brief"]["alert_count"],
                "hints_known": bool(hints.get("known")),
            },
        )
        return digest

    # ------------------------------------------------------------- briefing
    def briefing(self, context: dict, route: str) -> dict[str, Any]:
        """Proactive workspace co-worker: governed scan + LLM narrative.

        Deterministic-first per the roadmap: the scan comes from PHP
        (allowlisted aggregates, re-authorized operator); the LLM only turns
        facts into a briefing and NEVER invents numbers. When the provider
        is disabled or fails, deterministic findings still surface.
        """
        started = time.monotonic()
        user_id = int(context["user_id"])
        permissions = list(context["permissions"])
        request_id = context.get("request_id", "")
        route = (route or "dashboard")[:120]

        scan = self.tools.execute(
            user_id,
            permissions,
            "assistant.workspace_scan",
            {"route": route},
            request_id,
            roles=context.get("roles"),
            role_ids=context.get("role_ids"),
        )
        hints = self.behavior.hints(user_id)

        specialist_notes = self._run_specialists(scan, route)

        narrative: dict[str, Any] | None = None
        try:
            envelope = policy.minimize(
                "system.workspace_briefing",
                {
                    key: value
                    for key, value in {
                        "route": route,
                        "module": context.get("module", "dashboard"),
                        "audience": "staff",
                        "scan": self._scan_lines(scan),
                        "behavior_hints": self._hint_lines(hints),
                        "specialist_notes": specialist_notes,
                    }.items()
                    if value
                },
            )
            raw = self.provider.complete(
                [
                    {
                        "role": "system",
                        "content": agents.PROMPT_TEMPLATES["system.workspace_briefing"],
                    },
                    {
                        "role": "user",
                        "content": json.dumps(envelope, ensure_ascii=False),
                    },
                ],
                {"response_format": "json_object", "task": "briefing"},
            )
            narrative = self._bound_narrative(raw) if isinstance(raw, dict) else None
        except (ProviderError, policy.PolicyError):
            narrative = None

        findings = (narrative or {}).get("findings") or self._deterministic_findings(
            scan
        )
        headline = (narrative or {}).get("headline") or (
            "Workspace looks healthy"
            if not findings
            else f"{len(findings)} finding(s) in this workspace"
        )
        summary = (narrative or {}).get("summary") or (
            "No exceptions detected in the deterministic workspace scan. Everything within normal bounds."
            if not findings
            else "The workspace scan found items needing attention; review the findings below."
        )
        result = {
            "status": "ready",
            "engine": "python-llm" if narrative else "python-deterministic",
            "route": route,
            "generated_at": time.strftime("%Y-%m-%dT%H:%M:%S+00:00", time.gmtime()),
            "headline": headline,
            "summary": summary,
            "findings": findings,
            "scan": {
                "domain": str(scan.get("domain") or ""),
                "as_of": str(scan.get("as_of") or ""),
                "pending_review_count": scan.get("pending_review_count"),
                "insight_alert_count": scan.get("insight_alert_count"),
                "queue_stale": scan.get("queue_stale"),
                "queue_failed": scan.get("queue_failed"),
                "queue_dead_letter": scan.get("queue_dead_letter"),
                "error_critical_24h": scan.get("error_critical_24h"),
                "error_total_24h": scan.get("error_total_24h"),
                "kpis": scan.get("kpis")
                if isinstance(scan.get("kpis"), dict)
                else None,
            },
        }
        self.behavior.observe(
            user_id,
            "tool_use",
            {
                "tool": "assistant.workspace_scan",
                "route": route,
                "outcome": str(scan.get("status") or "ok"),
            },
        )
        self.journal.write(
            "ai_generation",
            {
                "type": "agent_briefing_ready",
                "operator_id": user_id,
                "route": route,
                "engine": result["engine"],
                "finding_count": len(findings),
            },
        )
        self.journal.write(
            "ai_performance",
            {
                "type": "workspace_briefing",
                "duration_ms": int((time.monotonic() - started) * 1000),
                "engine": result["engine"],
                "specialists": len(specialist_notes),
                "provider_calls": 1 if narrative else 0,
                "finding_count": len(findings),
                "tool_status": str(scan.get("status") or "ok"),
            },
        )
        return result

    def _run_specialists(self, scan: dict[str, Any], route: str) -> list[str]:
        """Run several domain eyes concurrently over one governed scan.

        Each specialist gets a tiny bounded prompt on the fast task class, so
        the fan-out is cheap and its wall-clock cost is one call, not N. Any
        failure degrades silently to the deterministic findings below.
        """
        count = int(getattr(self.config, "briefing_specialists", 0) or 0)
        if count <= 0:
            return []
        lines = self._scan_lines(scan)
        if len(lines) < 3:
            return []

        focuses = [
            "background jobs, queue health and system stability",
            "money: collection, arrears and ledger exceptions",
            "attendance, CBC learning outcomes and assessment gaps",
            "admissions funnel, placement and follow-up work",
        ][: max(1, min(count, 4))]

        def one(focus: str) -> str | None:
            try:
                envelope = policy.minimize(
                    "system.workspace_briefing",
                    {
                        "route": route,
                        "module": "dashboard",
                        "audience": "staff",
                        "scan": lines,
                    },
                )
                raw = self.provider.complete(
                    [
                        {
                            "role": "system",
                            "content": agents.specialist_prompt(focus),
                        },
                        {
                            "role": "user",
                            "content": json.dumps(envelope, ensure_ascii=False),
                        },
                    ],
                    {
                        "response_format": "json_object",
                        "task": "specialist",
                        "max_tokens": 240,
                    },
                )
                if not isinstance(raw, dict):
                    return None
                note = str(raw.get("insight") or raw.get("summary") or "").strip()
                return note[:200] or None
            except (ProviderError, policy.PolicyError):
                return None

        notes: list[str] = []
        with concurrent.futures.ThreadPoolExecutor(
            max_workers=max(1, len(focuses)),
            thread_name_prefix="kingsway-specialist",
        ) as pool:
            futures = [pool.submit(one, focus) for focus in focuses]
            for future in futures:
                try:
                    note = future.result(timeout=60)
                except Exception:  # noqa: BLE001 - specialists are best effort
                    note = None
                if note:
                    notes.append(note)
        return notes

    @staticmethod
    def _scan_lines(scan: dict[str, Any]) -> list[str]:
        lines: list[str] = []
        for key, value in scan.items():
            if len(lines) >= 20:
                break
            if isinstance(value, (list, tuple)):
                text = ",".join(str(v) for v in list(value)[:6])
            elif isinstance(value, (str, int, float)) or value is None:
                text = str(value)
            else:
                continue
            lines.append(f"{key}={text[:160]}")
        return lines

    @staticmethod
    def _bound_narrative(raw: dict[str, Any]) -> dict[str, Any]:
        allowed_severities = {"info", "warning", "critical"}
        findings = []
        for item in raw.get("findings") or []:
            if not isinstance(item, dict):
                continue
            severity = str(item.get("severity") or "info").lower()
            if severity not in allowed_severities:
                severity = "info"
            findings.append(
                {
                    "title": str(item.get("title") or "Finding")[:160],
                    "severity": severity,
                    "root_cause": str(
                        item.get("root_cause") or "not determinable from the scan"
                    )[:240],
                    "suggested_action": str(
                        item.get("suggested_action")
                        or "Review in the related workspace."
                    )[:240],
                }
            )
            if len(findings) >= 6:
                break
        return {
            "headline": str(raw.get("headline") or "")[:120],
            "summary": str(raw.get("summary") or "")[:450],
            "findings": findings,
        }

    @staticmethod
    def _deterministic_findings(scan: dict[str, Any]) -> list[dict[str, str]]:
        findings: list[dict[str, str]] = []

        def add(title: str, severity: str, root_cause: str, action: str) -> None:
            findings.append(
                {
                    "title": title[:160],
                    "severity": severity,
                    "root_cause": root_cause[:240],
                    "suggested_action": action[:240],
                }
            )

        pending = scan.get("pending_review_count")
        if isinstance(pending, int) and pending > 0:
            add(
                f"{pending} AI draft(s) await your review",
                "info",
                "Assistants prepared drafts that need a second person to approve before they become official.",
                "Open the module workspace(s) and approve or reject each draft.",
            )
        if isinstance(scan.get("queue_stale"), int) and int(scan["queue_stale"]) > 0:
            add(
                "Stale background jobs detected",
                "warning",
                "Jobs have sat beyond their lease window - the worker may have missed runs or a job crashed mid-flight.",
                "Check the System Health page; requeue only after reviewing the job payload.",
            )
        if (
            isinstance(scan.get("queue_dead_letter"), int)
            and int(scan["queue_dead_letter"]) > 0
        ):
            add(
                "Dead-letter jobs present",
                "warning",
                "Jobs exhausted their retries.",
                "Inspect the dead-letter queue and fix the underlying failure before requeueing.",
            )
        if (
            isinstance(scan.get("error_critical_24h"), int)
            and int(scan["error_critical_24h"]) > 0
        ):
            add(
                f"{int(scan['error_critical_24h'])} critical journal entries in 24h",
                "warning",
                "Recurring error signatures in the operational journals.",
                "Review the errors journal from Audit & Forensics for the full signatures.",
            )
        for alert in (scan.get("insight_alerts") or [])[:4]:
            if isinstance(alert, (str, int, float)):
                add(
                    f"Intelligence alert: {alert}",
                    "info",
                    "Deterministic detector crossed its threshold.",
                    "Open the related report from the analytics workspace.",
                )
        return findings

    @staticmethod
    def _requested_tools(
        response: dict[str, Any],
        allowed: list[str],
        already_run: set[str] | None = None,
    ) -> tuple[list[tuple[str, dict[str, Any]]], str | None]:
        """Validate one tool proposal, or a batch of up to MAX_TOOLS_PER_STEP.

        Accepts the legacy single {"tool":..,"tool_input":..} shape and the
        batched {"tools":[{tool,tool_input}..]} shape. Every entry is checked
        against the agent allowlist before anything executes.
        """
        proposals: list[tuple[str, dict[str, Any]]] = []
        # Seeded with what already ran so a prefetched governed read is never
        # paid for twice in one turn (the NLQ report run alone costs seconds).
        already: set[str] = set(already_run or ())
        raw_batch = response.get("tools")
        if isinstance(raw_batch, list) and raw_batch:
            for item in raw_batch[:MAX_TOOLS_PER_STEP]:
                if not isinstance(item, dict):
                    continue
                tool_id = str(item.get("tool") or "").strip()
                tool_input = item.get("tool_input")
                proposals.append(
                    (tool_id, tool_input if isinstance(tool_input, dict) else {})
                )
        else:
            tool_id = str(response.get("tool") or "").strip()
            raw_input = response.get("tool_input")
            proposals.append(
                (tool_id, raw_input if isinstance(raw_input, dict) else {})
            )

        valid: list[tuple[str, dict[str, Any]]] = []
        rejected: list[str] = []
        seen: set[str] = set(already)
        for tool_id, tool_input in proposals:
            if tool_id not in allowed:
                rejected.append(tool_id or "(unnamed tool)")
            elif tool_id in seen:
                continue
            else:
                seen.add(tool_id)
                valid.append((tool_id, tool_input))
        note = None
        if rejected:
            note = (
                "error: tool not available to this agent; available tools: "
                + ", ".join(allowed)
            )
        return valid, note

    def _run_tools_parallel(
        self,
        requests: list[tuple[str, dict[str, Any]]],
        user_id: int,
        permissions: list[str],
        request_id: str,
    ) -> list[tuple[str, dict[str, Any]]]:
        if len(requests) == 1:
            tool_id, tool_input = requests[0]
            return [
                (
                    tool_id,
                    self.tools.execute(
                        user_id,
                        permissions,
                        tool_id,
                        tool_input,
                        request_id,
                        roles=context.get("roles"),
                        role_ids=context.get("role_ids"),
                    ),
                )
            ]

        parallel_limit = int(getattr(self.config, "max_tool_parallel", 3) or 3)
        call_timeout = int(getattr(self.config, "timeout", 25) or 25) + 5
        workers = max(1, min(parallel_limit, len(requests)))
        results: list[tuple[str, dict[str, Any]]] = []
        with concurrent.futures.ThreadPoolExecutor(
            max_workers=workers, thread_name_prefix="kingsway-tool"
        ) as pool:
            futures = [
                (
                    tool_id,
                    pool.submit(
                        self.tools.execute,
                        user_id,
                        permissions,
                        tool_id,
                        tool_input,
                        request_id,
                    ),
                )
                for tool_id, tool_input in requests
            ]
            for tool_id, future in futures:
                try:
                    results.append((tool_id, future.result(timeout=call_timeout)))
                except Exception as error:  # noqa: BLE001 - one tool must not fail the turn
                    results.append(
                        (tool_id, {"status": "error", "message": str(error)[:200]})
                    )
        return results

    # -------------------------------------------------------------- streaming
    def stream_answer(self, context: dict, question: str):
        """Yield typed events for the streamed answer surface.

        Order matters for perceived latency: the deterministic answers and the
        governed context are produced first (near-instant), and the model is
        only consulted for the wording. Deltas are emitted as the provider
        produces them, so the first words reach the browser in well under a
        second.
        """
        from .security import bound_question

        question_raw = str(question or "").strip()
        question_clean, slash_instruction = guard.extract_slash(question_raw)
        question = bound_question(question_clean or question_raw)
        user_id = int(context["user_id"])
        permissions = list(context["permissions"])
        route = context.get("route", "")
        module = context.get("module", "dashboard")
        request_id = context.get("request_id", "")
        session_id = str(context.get("session_id") or "")
        assistant_domain = str(context.get("assistant_domain") or "staff")

        # Intent-first agent selection: use fast lexical hint from question
        # before falling back to route. This ensures placement_tests questions
        # about reports go to reports.agent, not admissions.agent.
        # First, check if the question is a governed-report query that should
        # go to reports.agent regardless of route hints.
        agent = None
        routing = "route_match"
        if self._is_report_query(question):
            agent = agents.AGENTS.get("reports.agent")
            routing = "report_query"
        if agent is None:
            hinted = agents.route_hint_for(question)
            if hinted is not None:
                agent = agents.AGENTS.get(hinted[0])
                routing = "lexical"
        if agent is None:
            agent = agents.for_route(route)
            routing = "route_match" if agent else "default"
        if agent is None:
            agent = agents.default_agent()
            routing = "default"

        # Same deterministic pre-flight as the buffered path.
        if guard.is_confidential(question):
            result = guard.confidential_response(agent)
            yield {"kind": "final", **result}
            return
        if guard.is_smalltalk(question):
            result = guard.greeting_response(question, agent)
            yield {"kind": "final", **result}
            return

        # Resolve or create chat session
        session = None
        if self.chat_sessions is not None:
            if session_id:
                session = self.chat_sessions.get_session(
                    user_id, session_id, limit=1, assistant_domain=assistant_domain
                )
            if session is None:
                session = self.chat_sessions.create(
                    user_id, route, assistant_domain=assistant_domain
                )
            session_id = session["id"]
            context = {**context, "session_id": session_id}
            self._merge_incoming_topics(user_id, session_id, context)

        prefs = self._preferences(user_id, context)

        # The system_admin agent and its file/log tools are reserved for
        # system administrators only.
        wants_admin = bool(context.get("system_admin"))
        if agent["id"] == "system_admin.agent":
            if not (wants_admin and self._is_system_admin(context)):
                agent = agents.default_agent()
                routing = "not_authorized" if wants_admin else routing
        elif wants_admin and self._is_system_admin(context):
            resolved = agents.resolve("system_admin.agent")
            if resolved is not None:
                agent = resolved
                routing = "admin_mode"

        # Assistant domain routing: explicit persona from the frontend.
        assistant_domain = str(context.get("assistant_domain") or "").lower()
        if assistant_domain and agent["id"] != "system_admin.agent":
            domain_agent_map = {
                "system_admin": "system_admin.agent",
                "parent": "parent.agent",
                "public": "public.agent",
                "staff": None,
            }
            target = domain_agent_map.get(assistant_domain)
            if target:
                resolved = agents.resolve(target)
                if resolved is not None:
                    agent = resolved
                    routing = f"domain:{assistant_domain}"
                else:
                    agent = agents.default_agent()
                    routing = "default"

        started = time.monotonic()
        allowed_tools = list(agent["tools"])
        yield {"kind": "status", "text": "I'm checking the relevant school records..."}
        prefetched = self._prefetch_context(
            agent,
            user_id,
            permissions,
            request_id,
            route,
            question,
            roles=context.get("roles"),
            role_ids=context.get("role_ids"),
        )
        tool_results = [
            self._compact_result(tool_id, result) for tool_id, result in prefetched
        ]
        tools_used = [tool_id for tool_id, _ in prefetched]

        hints = self.behavior.hints(user_id)
        self.behavior.observe(
            user_id,
            "question",
            {
                "route": route,
                "module": module,
                "domain": agent["domain"],
                "agent_id": agent["id"],
            },
        )

        history_messages = self._conversation_messages(
            user_id, route, session_id, context, question=question
        )
        summary = self._conversation_summary(user_id, session_id, route, context)
        topics = self._session_topics(user_id, session_id, context)
        navigation = self._navigation_lines(context.get("sidebar_state"))
        instruction = (
            slash_instruction or str((context.get("instruction") or "") or "")
        )[:400]
        has_nlq_tool = "reports.nlq" in agent.get("tools", ())
        has_nlq_prefetch = has_nlq_tool and any(
            tool_id == "reports.nlq" for tool_id, _ in prefetched
        )
        if has_nlq_prefetch:
            nlq_result = next(
                (result for tool_id, result in prefetched if tool_id == "reports.nlq"),
                {},
            )
            # DEBUG: log the nlq_result structure
            import sys

            print(
                f"DEBUG nlq_result keys: {list(nlq_result.keys())}",
                file=sys.stderr,
                flush=True,
            )
            if "answer" in nlq_result:
                print(
                    f"DEBUG answer keys: {list(nlq_result['answer'].keys())}",
                    file=sys.stderr,
                    flush=True,
                )
            report_code = nlq_result.get("report_code", "UNKNOWN")
            if report_code == "UNKNOWN" and "answer" in nlq_result:
                report_code = nlq_result["answer"].get("report_code", "UNKNOWN")
            report_title = nlq_result.get("report_title", "")
            if not report_title and "answer" in nlq_result:
                report_title = nlq_result["answer"].get("report_title", "")
            row_count = nlq_result.get("row_count", 0)
            if not row_count and "answer" in nlq_result:
                row_count = nlq_result["answer"].get("row_count", 0)
            columns = nlq_result.get("columns", [])
            if not columns and "answer" in nlq_result:
                columns = nlq_result["answer"].get("columns", [])
            rows = nlq_result.get("rows", [])
            if not rows and "answer" in nlq_result:
                rows = nlq_result["answer"].get("rows", [])
            # Generate deterministic answer directly from data, bypass model
            deterministic_answer = self._generate_nlq_answer(question, nlq_result)
            if deterministic_answer:
                # Generate dynamic follow-up questions based on report data
                followups = self._generate_followup_questions(
                    report_code, rows, columns, question
                )
            # Stream the deterministic answer and finish
            yield {
                "kind": "status",
                "text": "I'm checking the relevant school records...",
            }

            # Track TTFT (time to first token)
            ttft_marked = False
            ttft_ms = 0

            for piece in deterministic_answer.split():
                if not ttft_marked:
                    ttft_ms = int((time.monotonic() - started) * 1000)
                    ttft_marked = True
                yield {"kind": "delta", "text": piece + " "}

            # Build citations from report data
            citations = []
            if report_code and report_code != "UNKNOWN":
                citations.append(
                    {
                        "report_code": report_code,
                        "title": report_title or report_code,
                        "description": f"Governed report executed at {datetime.now(ZoneInfo('Africa/Nairobi')).isoformat()}",
                        "row_count": row_count,
                        "columns": columns,
                    }
                )

            # Check for insufficient data
            insufficient_data = row_count == 0

            total_latency_ms = int((time.monotonic() - started) * 1000)

            yield {
                "kind": "final",
                "answer": {
                    "title": deterministic_answer[:100],
                    "body": deterministic_answer,
                    "next_steps": [],
                    "suggested_questions": followups,
                    "escalation_required": False,
                    "insufficient_data": insufficient_data,
                    "citations": citations,
                },
                "latency": {
                    "ttft_ms": ttft_ms,
                    "total_latency_ms": total_latency_ms,
                    "prefetch_ms": 0,
                    "provider_calls": 0,
                    "tool_calls": 1,
                },
                "routing": "report_query",
                "agent": {
                    "id": agent["id"],
                    "name": agent["name"],
                    "domain": agent["domain"],
                },
            }
            return
            grounding_note = (
                f"A GOVERNED REPORT HAS ALREADY BEEN EXECUTED FOR THIS QUESTION.\n"
                f"Report: {report_code} - {report_title}\n"
                f"Rows returned: {row_count}\n"
                f"Columns: {', '.join(columns) if columns else 'none'}\n"
                f"Sample data: {json.dumps(rows[:5], ensure_ascii=False) if rows else 'no rows'}\n\n"
                f"ANSWER DIRECTLY FROM THIS DATA. Do NOT call any tool. Do NOT reference any other report. "
                f"If the user asks a follow-up, use THIS report's data. "
                f"Today's date in Africa/Nairobi is {datetime.now(ZoneInfo('Africa/Nairobi')).date().isoformat()}."
            )
        else:
            grounding_note = (
                "Prefetched results may be unrelated to this question. Never use "
                "a briefing or workflow catalogue as evidence for school records. "
                "For any other school-data question, request reports.nlq with the "
                f"user's question before answering. Today's date in Africa/Nairobi is "
                f"{datetime.now(ZoneInfo('Africa/Nairobi')).date().isoformat()}."
            )
        base_envelope = {
            "question": question,
            "audience": "staff",
            "agent_id": agent["id"],
            "route": route,
            "module": module,
            "behavior_hints": self._hint_lines(hints),
            "grounding_note": grounding_note,
            "tool_results": tool_results,
            "conversation": summary,
            "session_topics": topics,
            "preferences": self._preference_lines(prefs),
            "navigation": navigation,
        }
        if instruction:
            base_envelope["instruction"] = instruction
        envelope = policy.minimize(
            "system.agent_chat",
            {key: value for key, value in base_envelope.items() if value},
        )
        provider_error: str | None = None
        answer: dict[str, Any] | None = None
        body = ""
        rendered_any = False

        # One governed lookup is allowed mid-stream. The first character decides
        # everything: prose streams straight to the browser, while a tool
        # request is buffered, executed, and answered in a second (final) round.
        # That keeps raw tool frames out of the chat bubble and still lets a
        # genuinely data-hungry question get its facts.
        for attempt in range(2):
            messages = [
                {"role": "system", "content": agents.stream_prompt(agent, context)},
                *history_messages,
                {"role": "user", "content": json.dumps(envelope, ensure_ascii=False)},
            ]
            # An oversized envelope must be trimmed rather than lose the answer.
            self._assert_prompt_fits(messages)

            body = ""
            buffered = ""
            emitted = 0
            is_tool_frame: bool | None = None
            action: str | None = None
            answer_body_cursor: int | None = None
            answer_body_closed = False
            provider_error = None
            try:
                for delta in self.provider.stream(messages, {"task": "chat"}):
                    buffered += delta
                    if is_tool_frame is None:
                        stripped = buffered.lstrip()
                        if stripped:
                            is_tool_frame = stripped.startswith("{")
                    if is_tool_frame is False:
                        # Prose: pass every byte straight through as it lands.
                        yield {"kind": "delta", "text": buffered[emitted:]}
                        emitted = len(buffered)
                        rendered_any = True
                    elif is_tool_frame:
                        # Tool proposals must remain private until validated,
                        # but the final action's JSON answer body is safe to
                        # decode and stream as ordinary assistant text.
                        if action is None:
                            match = re.search(
                                r'(?<!\\)"action"\s*:\s*"([^"\\]*)"',
                                buffered,
                            )
                            if match:
                                action = match.group(1)
                        if action == "final":
                            if answer_body_cursor is None:
                                answer_match = re.search(
                                    r'(?<!\\)"answer"\s*:\s*\{', buffered
                                )
                                if answer_match:
                                    body_match = re.search(
                                        r'(?<!\\)"body"\s*:\s*"',
                                        buffered[answer_match.end() :],
                                    )
                                    if body_match:
                                        answer_body_cursor = (
                                            answer_match.end() + body_match.end()
                                        )
                            if (
                                answer_body_cursor is not None
                                and not answer_body_closed
                            ):
                                piece, answer_body_cursor, answer_body_closed = (
                                    _decode_partial_json_string(
                                        buffered, answer_body_cursor
                                    )
                                )
                                if piece:
                                    yield {"kind": "delta", "text": piece}
                                    rendered_any = True
            except ProviderError as error:
                provider_error = str(error)[:200]
                break

            body = buffered.strip()
            if not body:
                break

            if is_tool_frame:
                proposal = self._json_object(body)
                requests, _rejected = self._requested_tools(
                    proposal, allowed_tools, already_run=set(tools_used)
                )
                if requests and attempt == 0:
                    yield {
                        "kind": "status",
                        "text": "I’m checking those records…",
                    }
                    for tool_id, tool_input in requests:
                        payload = dict(tool_input or {})
                        if tool_id == "reports.nlq":
                            payload.setdefault("question", question)
                        result = self.tools.execute(
                            user_id,
                            permissions,
                            tool_id,
                            payload,
                            request_id,
                            roles=context.get("roles"),
                            role_ids=context.get("role_ids"),
                        )
                        tools_used.append(tool_id)
                        tool_results.append(self._compact_result(tool_id, result))
                    envelope["tool_results"] = tool_results
                    continue
                # A second tool request breaches the step budget: fall through
                # and answer from the governed results gathered so far.

            parsed = self._parse_answer_text(body)
            if parsed:
                answer = parsed
            break

        outcome = "answered" if answer else "unavailable"
        degraded = False
        if answer is None:
            fallback = self._fallback_answer(tools_used, tool_results)
            if provider_error is None:
                answer = fallback
                outcome = "answered"
            elif not rendered_any:
                # Nothing was shown to the user yet, so the governed facts in
                # hand are a better answer than an error bubble: the staff
                # member gets real numbers, flagged as model-degraded.
                answer = fallback
                outcome = "answered"
                degraded = True
            else:
                yield {
                    "kind": "final",
                    "status": "unavailable",
                    "answer": None,
                    "message": "the response was interrupted — please try again",
                    "degraded": True,
                    "tools_used": tools_used,
                    "provider_calls": 1,
                    "routing": routing,
                    "agent": {
                        "id": agent["id"],
                        "name": agent["name"],
                        "domain": agent["domain"],
                    },
                }
                self._observe_outcome(
                    user_id, route, module, agent, tools_used, outcome
                )
                return

        self._observe_outcome(user_id, route, module, agent, tools_used, outcome)
        # Record turn to session
        self._remember_session(
            session_id,
            user_id,
            route,
            question,
            answer,
            save_history=bool(prefs.get("save_history", True)),
            assistant_domain=assistant_domain,
        )
        self.journal.write(
            "ai_generation",
            {
                "type": "agent_assist_stream",
                "operator_id": user_id,
                "agent_id": agent["id"],
                "route": route,
                "outcome": outcome,
                "tools_used": tools_used,
                "duration_ms": int((time.monotonic() - started) * 1000),
                "request_id": request_id,
            },
        )
        # Include session info in final response
        session_payload = {}
        if session_id:
            session_payload = {
                "session_id": session_id,
                "session_title": session.get("title") if session else "",
            }
        yield {
            "kind": "final",
            "status": "answered",
            "answer": answer,
            "degraded": degraded,
            "tools_used": tools_used,
            "provider_calls": 1,
            "routing": routing,
            "agent": {
                "id": agent["id"],
                "name": agent["name"],
                "domain": agent["domain"],
            },
            **session_payload,
        }

    def _conversation_turns(
        self, user_id: int, route: str, session_id: str = ""
    ) -> list[str]:
        """Prior turns flattened to "Q: ... / A: ..." strings.

        Flat strings are required: the prompt policy accepts only scalar array
        items, which is what keeps arbitrary nested content out of the prompt.
        The list is also kept short so the total envelope stays well inside the
        approved context budget alongside governed tool results.
        """
        # Prefer session store if available and session_id provided
        if self.chat_sessions is not None and session_id:
            return self.chat_sessions.conversation(
                user_id, session_id, max_turns=CONVERSATION_PROMPT_TURNS
            )
        # Fall back to legacy conversation store
        store = getattr(self, "conversation", None)
        if store is None:
            return []
        try:
            turns = list(store.turns(int(user_id), route))
        except Exception:
            return []
        lines: list[str] = []
        for turn in turns[-CONVERSATION_PROMPT_TURNS:]:
            question = str(turn.get("question") or "").strip()[:240]
            answer = str(turn.get("answer") or "").strip()[:240]
            if question:
                lines.append(
                    f"Q: {question} / A: {answer}" if answer else f"Q: {question}"
                )
        return lines

    def _merge_incoming_topics(
        self, user_id: int, session_id: str, context: dict
    ) -> None:
        """Persist topic labels the client already extracted from the turn."""
        if self.chat_sessions is None or not session_id:
            return
        topics = context.get("topics")
        if isinstance(topics, list) and topics:
            try:
                self.chat_sessions.add_topics(user_id, session_id, topics)
            except Exception:
                pass

    def _preferences(self, user_id: int, context: dict) -> dict[str, Any]:
        prefs: dict[str, Any] = {}
        if self.chat_sessions is not None:
            try:
                stored = self.chat_sessions.get_prefs(user_id)
                if isinstance(stored, dict):
                    prefs.update(stored)
            except Exception:
                pass
        incoming = context.get("preferences")
        if isinstance(incoming, dict):
            if "save_history" in incoming:
                prefs["save_history"] = bool(incoming.get("save_history"))
            if "auto_charts" in incoming:
                prefs["auto_charts"] = bool(incoming.get("auto_charts"))
            for key in ("response_style", "language"):
                value = str(incoming.get(key) or "").strip()[:40]
                if value:
                    prefs[key] = value
        return prefs

    @staticmethod
    def _preference_lines(prefs: dict[str, Any]) -> list[str]:
        lines: list[str] = []
        style = str(prefs.get("response_style") or "").strip()
        if style:
            lines.append(f"Response style: {style[:40]}")
        language = str(prefs.get("language") or "").strip()
        if language and language.lower() not in ("en", "english"):
            lines.append(f"Preferred language: {language[:40]}")
        if prefs.get("auto_charts") is False:
            lines.append("Charts: only when explicitly requested")
        return lines

    @staticmethod
    def _navigation_lines(state: Any) -> list[str]:
        if not isinstance(state, dict):
            return []
        lines: list[str] = []
        current = str(
            state.get("current_label") or state.get("title") or state.get("route") or ""
        ).strip()
        if current:
            lines.append(f"Current screen: {current[:120]}")
        items = state.get("visible_items")
        if isinstance(items, list):
            labels: list[str] = []
            for item in items[:12]:
                label = ""
                if isinstance(item, dict):
                    label = str(item.get("label") or "").strip()
                elif isinstance(item, str):
                    label = item.strip()
                if label and label not in labels:
                    labels.append(label[:60])
            if labels:
                lines.append(f"Sidebar: {', '.join(labels)}")
        return lines

    def _conversation_messages(
        self,
        user_id: int,
        route: str,
        session_id: str,
        context: dict,
        question: str = "",
    ) -> list[dict[str, str]]:
        """Native role-based history, preferring the server-side transcript."""
        if self.chat_sessions is not None and session_id:
            try:
                messages = self.chat_sessions.history_messages(
                    user_id,
                    session_id,
                    max_turns=MAX_HISTORY_TURNS,
                    message_chars=HISTORY_MESSAGE_CHARS,
                    char_budget=HISTORY_CHAR_BUDGET,
                )
                if messages:
                    return messages
            except Exception:
                pass
        history = context.get("history")
        if not isinstance(history, list):
            return []
        selected: list[dict[str, str]] = []
        for item in history[-(MAX_HISTORY_TURNS * 2) :]:
            if not isinstance(item, dict):
                continue
            role = str(item.get("role") or "")
            if role not in ("user", "assistant"):
                continue
            content = str(item.get("content") or "").strip()[:HISTORY_MESSAGE_CHARS]
            if content:
                selected.append({"role": role, "content": content})
        if question and selected:
            last = selected[-1]
            if last["role"] == "user" and last["content"] == question:
                selected.pop()
        return selected

    def _conversation_summary(
        self, user_id: int, session_id: str, route: str, context: dict
    ) -> list[str]:
        if self.chat_sessions is not None and session_id:
            try:
                return self.chat_sessions.summary(
                    user_id,
                    session_id,
                    recent_turns=MAX_HISTORY_TURNS,
                    max_lines=SUMMARY_LINES,
                )
            except Exception:
                return []
        return []

    def _session_topics(
        self, user_id: int, session_id: str, context: dict
    ) -> list[str]:
        if self.chat_sessions is not None and session_id:
            try:
                topics = self.chat_sessions.get_topics(user_id, session_id)
                if topics:
                    return list(topics)[:12]
            except Exception:
                pass
        incoming = context.get("topics")
        if not isinstance(incoming, list):
            return []
        clean: list[str] = []
        for topic in incoming[:12]:
            text = " ".join(str(topic or "").split())[:40]
            if text and text not in clean:
                clean.append(text)
        return clean

    def _is_system_admin(self, context: dict) -> bool:
        """True only when the authenticated operator is a system administrator.

        Checked via the system_admin permission or an equivalent role label so
        the system_admin agent and its file/log tools can never be reached by
        ordinary staff.
        """
        permissions = set(str(p) for p in (context.get("permissions") or []))
        roles = context.get("roles") or []
        role_text = " ".join(str(r) for r in roles).lower()
        return (
            SYSTEM_ADMIN_PERMISSION in permissions
            or "system admin" in role_text
            or "system_administrator" in role_text
            or "school administrator" in role_text
        )

    def _remember_session(
        self,
        session_id: str,
        user_id: int,
        route: str,
        question: str,
        answer: dict[str, Any] | None,
        save_history: bool = True,
        assistant_domain: str = "staff",
    ) -> None:
        """Record a turn to the chat session store."""
        if self.chat_sessions is None or not session_id:
            # Fall back to legacy store
            self._remember(user_id, route, question, answer)
            return
        if not save_history:
            return
        if not isinstance(answer, dict):
            return
        body = str(answer.get("body") or "")
        if not body:
            return
        try:
            # Record user message
            self.chat_sessions.append(
                user_id, session_id, "user", question, assistant_domain=assistant_domain
            )
            # Record assistant message with meta
            meta = {
                "agent_id": answer.get("agent", {}).get("id", ""),
                "routing": answer.get("routing", ""),
                "tools_used": answer.get("tools_used", []),
            }
            self.chat_sessions.append(
                user_id,
                session_id,
                "assistant",
                body,
                agent_id=answer.get("agent", {}).get("id", ""),
                meta=meta,
                assistant_domain=assistant_domain,
            )
        except Exception:
            pass

    def _remember(
        self, user_id: int, route: str, question: str, answer: dict[str, Any] | None
    ) -> None:
        """Legacy conversation store recording (kept for backward compatibility)."""
        store = getattr(self, "conversation", None)
        if store is None or not isinstance(answer, dict):
            return
        body = str(answer.get("body") or "")
        if not body:
            return
        try:
            store.record(int(user_id), route, question, body)
        except Exception:
            pass

    def _assert_prompt_fits(self, messages: list[dict[str, str]]) -> None:
        """Trim the prompt rather than fail the whole answer.

        Tool results and native history are the unbounded parts of the prompt.
        Overflow first drops the oldest governed results, then the oldest
        history turns, so the current question and the most recent context
        always survive.
        """
        if policy.MAX_CONTEXT_BYTES <= 0:
            return
        while self._prompt_bytes(messages) > policy.MAX_CONTEXT_BYTES:
            user_turn = next(
                (
                    message
                    for message in reversed(messages)
                    if message.get("role") == "user"
                ),
                None,
            )
            if user_turn is None:
                return
            try:
                payload = json.loads(user_turn["content"])
            except (TypeError, ValueError):
                payload = None
            changed = False
            if isinstance(payload, dict):
                results = payload.get("tool_results")
                if isinstance(results, list) and results:
                    if len(results) > 1:
                        payload["tool_results"] = results[1:]
                    else:
                        payload.pop("tool_results", None)
                    changed = True
                else:
                    conversation = payload.get("conversation")
                    if isinstance(conversation, list) and conversation:
                        if len(conversation) > 2:
                            payload["conversation"] = conversation[-2:]
                        else:
                            payload.pop("conversation", None)
                        changed = True
            if changed and isinstance(payload, dict):
                user_turn["content"] = json.dumps(payload, ensure_ascii=False)
                continue
            # No envelope material left: drop the oldest history turn.
            history_index = next(
                (
                    index
                    for index, message in enumerate(messages)
                    if index > 0
                    and message is not user_turn
                    and message.get("role") in ("user", "assistant")
                ),
                None,
            )
            if history_index is None:
                return
            messages.pop(history_index)

    @staticmethod
    def _prompt_bytes(messages: list[dict[str, str]]) -> int:
        return len(json.dumps(messages, ensure_ascii=False).encode("utf-8"))

    @staticmethod
    def _json_object(text: str) -> dict[str, Any]:
        """Best-effort extraction of the model's JSON reply, or an empty dict."""
        cleaned = text.strip()
        if cleaned.startswith("```"):
            cleaned = cleaned.strip("`")
            if cleaned.lower().startswith("json"):
                cleaned = cleaned[4:]
        start = cleaned.find("{")
        if start == -1:
            return {}
        try:
            parsed = json.loads(cleaned[start:])
        except json.JSONDecodeError:
            return {}
        return parsed if isinstance(parsed, dict) else {}

    def _parse_answer_text(self, text: str) -> dict[str, Any] | None:
        """Validate a streamed answer body into the governed answer shape."""
        cleaned = text.strip()
        if cleaned.startswith("```"):
            cleaned = cleaned.strip("`")
            if cleaned.lower().startswith("json"):
                cleaned = cleaned[4:]
        start = cleaned.find("{")
        if start == -1:
            body = cleaned.strip()
            if not body or len(body) > 4000:
                return None
            return {
                "body": body,
                "confidence": "medium",
                "next_steps": [],
                "suggested_questions": [],
                "escalation_required": False,
            }
        try:
            parsed = json.loads(cleaned[start:])
        except json.JSONDecodeError:
            return None
        if not isinstance(parsed, dict):
            return None
        if parsed.get("action") == "final" and isinstance(parsed.get("answer"), dict):
            parsed = parsed["answer"]
        return self._validate_answer(parsed)

    # ----------------------------------------------------------- deterministic NLQ answer
    def _generate_nlq_answer(
        self, question: str, nlq_result: dict[str, Any]
    ) -> str | None:
        """Generate a deterministic answer from NLQ results, bypassing the model.

        Returns the answer string or None if the report type isn't handled.
        """
        # The tool bridge wraps the result in 'answer' key
        answer_data = nlq_result.get("answer", nlq_result)
        report_code = answer_data.get("report_code", "")
        row_count = answer_data.get("row_count", 0)
        columns = answer_data.get("columns", [])
        rows = answer_data.get("rows", [])
        summary = answer_data.get("summary", {})
        filters = answer_data.get("filters", {})

        if not rows and row_count == 0:
            return f"The report **{report_code}** ({nlq_result.get('report_title', '')}) returned no data for the current filters."

        # Handle common report types with specific formatting
        q_lower = question.lower()

        # ENR_CURRENT_BY_CLASS - enrollment by class
        if report_code == "ENR_CURRENT_BY_CLASS":
            total = sum(r.get("total", 0) for r in rows)
            boys = sum(r.get("boys", 0) for r in rows)
            girls = sum(r.get("girls", 0) for r in rows)

            parts = [
                f"**Current Enrollment: {total:,} learners** ({boys:,} boys, {girls:,} girls) across {row_count} class/streams."
            ]

            if "boarder" in q_lower or "boarding" in q_lower:
                # Check if there's a student_type filter or column
                boarder_rows = [
                    r for r in rows if str(r.get("student_type", "")).upper() == "BOARD"
                ]
                if boarder_rows:
                    b_total = sum(r.get("total", 0) for r in boarder_rows)
                    parts.append(f"**Boarders: {b_total:,}**")
                else:
                    parts.append("Boarder breakdown not available in this report.")

            # Show top 5 classes
            if row_count <= 10:
                parts.append("\n**By class:**")
                for r in rows:
                    parts.append(
                        f"• {r.get('class_name', '')} {r.get('stream_name', '')}: {r.get('total', 0):,} ({r.get('boys', 0):,}B / {r.get('girls', 0):,}G)"
                    )
            else:
                top5 = sorted(rows, key=lambda x: x.get("total", 0), reverse=True)[:5]
                parts.append(f"\n**Largest classes:**")
                for r in top5:
                    parts.append(
                        f"• {r.get('class_name', '')} {r.get('stream_name', '')}: {r.get('total', 0):,}"
                    )

            parts.append(
                f"\n_Report: {report_code} — {nlq_result.get('report_title', '')}_"
            )
            return "\n".join(parts)

        # ENR_MONTHLY_TREND
        if report_code == "ENR_MONTHLY_TREND":
            parts = [f"**Enrollment trend** ({row_count} months)"]
            for r in rows[:12]:
                parts.append(f"• {r.get('month', '')}: {r.get('total', 0):,}")
            parts.append(
                f"\n_Report: {report_code} — {nlq_result.get('report_title', '')}_"
            )
            return "\n".join(parts)

        # ADM_CONVERSION, ADM_ENROLLED_COHORT
        if report_code in ("ADM_CONVERSION", "ADM_ENROLLED_COHORT"):
            parts = [
                f"**Admissions {report_code.replace('ADM_', '').replace('_', ' ').title()}**"
            ]
            for r in rows[:10]:
                parts.append(f"• {r}")
            parts.append(
                f"\n_Report: {report_code} — {nlq_result.get('report_title', '')}_"
            )
            return "\n".join(parts)

        # FIN_FEE_SUMMARY_CLASS, FIN_ARREARS_CLASS
        if report_code in ("FIN_FEE_SUMMARY_CLASS", "FIN_ARREARS_CLASS"):
            parts = [f"**{report_code.replace('FIN_', '').replace('_', ' ').title()}**"]
            for r in rows[:10]:
                parts.append(f"• {r.get('class_name', '')}: {r.get('total', 0):,}")
            parts.append(
                f"\n_Report: {report_code} — {nlq_result.get('report_title', '')}_"
            )
            return "\n".join(parts)

        # ATT_CLASS_TERM_RATE
        if report_code == "ATT_CLASS_TERM_RATE":
            parts = [f"**Class Attendance Rate** ({row_count} classes)"]
            for r in rows[:10]:
                parts.append(f"• {r.get('class_name', '')}: {r.get('rate', 0):.1f}%")
            parts.append(
                f"\n_Report: {report_code} — {nlq_result.get('report_title', '')}_"
            )
            return "\n".join(parts)

        # Generic fallback for other reports
        parts = [f"**{report_code}** — {nlq_result.get('report_title', '')}"]
        parts.append(f"Rows: {row_count:,}")
        if summary:
            parts.append(f"Summary: {summary}")
        # Show first 5 rows
        for r in rows[:5]:
            parts.append(f"• {r}")
        if row_count > 5:
            parts.append(f"... and {row_count - 5} more rows")
        parts.append(
            f"\n_Report: {report_code} — {nlq_result.get('report_title', '')}_"
        )
        return "\n".join(parts)

    # ----------------------------------------------------------- dynamic follow-up questions
    def _generate_followup_questions(
        self, report_code: str, rows: list[dict], columns: list[str], question: str
    ) -> list[str]:
        """Generate context-aware follow-up questions based on report data."""
        followups = []
        q_lower = question.lower()

        if report_code == "ENR_CURRENT_BY_CLASS":
            # Enrollment-specific follow-ups
            if rows:
                total = sum(r.get("total", 0) for r in rows)
                if total > 0:
                    followups.append(f"Show me the enrollment breakdown by gender")
                    followups.append(f"Which class has the highest enrollment?")
                    followups.append(f"Compare enrollment trends over the last 3 terms")
            if "boarder" in question.lower():
                followups.append("How many day students are enrolled?")
                followups.append("Show boarding capacity by dormitory")

        elif report_code == "ENR_MONTHLY_TREND":
            followups.append("Show enrollment trend for the current academic year")
            followups.append("Compare this year's enrollment with last year")
            followups.append("What is the projected enrollment for next term?")

        elif report_code == "ADM_CONVERSION":
            followups.append("Show conversion rate by grade level")
            followups.append("Which admission stage has the highest drop-off?")
            followups.append("Compare conversion rates across terms")

        elif report_code == "ADM_ENROLLED_COHORT":
            followups.append("Show cohort retention rate by grade")
            followups.append("How many admitted students are boarders vs day?")
            followups.append("Breakdown by admission window")

        elif report_code == "ATT_CLASS_TERM_RATE":
            if rows:
                followups.append("Which classes have attendance below 90%?")
                followups.append("Show attendance trend for the current term")
                followups.append("Compare attendance by stream")

        elif report_code == "FIN_FEE_SUMMARY_CLASS":
            followups.append("Show fee arrears by class")
            followups.append("Which classes have the highest outstanding balances?")
            followups.append("Compare fee collection rate this term vs last term")

        elif report_code == "FIN_ARREARS_CLASS":
            followups.append("Show top 10 students with highest arrears")
            followups.append("Breakdown by payment method")
            followups.append("Projected collections for next month")

        elif report_code == "DISC_INCIDENT_TREND":
            followups.append("Show incidents by category")
            followups.append("Which classes have the most incidents?")
            followups.append("Compare this term with last term")

        elif report_code == "HR_STAFF_ATTENDANCE_RATE":
            followups.append("Show attendance by department")
            followups.append("Which staff members have the most absences?")
            followups.append("Compare attendance this term vs last term")

        elif report_code == "INV_STOCK_LEVELS":
            followups.append("Show items below reorder level")
            followups.append("Which items have the highest turnover?")
            followups.append("Project stock-out dates for critical items")

        elif report_code == "CAT_FOOD_CONSUMPTION":
            followups.append("Show meal cost per student")
            followups.append("Compare food waste by meal type")
            followups.append("Project food budget for next term")

        elif report_code == "COMMS_DELIVERY_SUMMARY":
            followups.append("Show failed messages by channel")
            followups.append("Which guardians have undelivered messages?")
            followups.append("Compare SMS vs email delivery rates")

        elif report_code in ("CBC_CLASS_ASSESSMENT_SUMMARY", "CBC_RUBRIC_DISTRIBUTION"):
            followups.append("Show results by learning area")
            followups.append("Which classes need intervention?")
            followups.append("Compare performance across terms")

        # Generic fallbacks
        if not followups:
            followups = [
                "Show me the raw data for this report",
                "Export this report to CSV",
                "Open the full report in the analytics catalogue",
            ]

        return followups[:3]

    # ----------------------------------------------------------- report query detection
    def _is_report_query(self, question: str) -> bool:
        """Check if the question is a governed-report query that should route to reports.agent."""
        q = question.lower()
        # Enrollment keywords
        if any(
            kw in q
            for kw in (
                "enroll",
                "enrolment",
                "enrollment",
                "learner",
                "student",
                "pupil",
                "headcount",
                "admission",
                "admit",
                "applied",
                "applicant",
                "conversion",
                "cohort",
                "trend",
                "monthly",
            )
        ):
            return True
        # Boarding enrollment (uses enrollment report)
        if any(kw in q for kw in ("boarder", "boarding", "boarders")) and any(
            kw in q for kw in ("enroll", "enrolled", "count", "how many", "number")
        ):
            return True
        # Attendance
        if any(kw in q for kw in ("attendance", "present", "absent", "absentee")):
            return True
        # Finance
        if any(
            kw in q
            for kw in (
                "fee",
                "fees",
                "arrears",
                "outstanding",
                "balance",
                "payment",
                "billing",
            )
        ):
            return True
        # Discipline
        if any(kw in q for kw in ("discipline", "incident", "behaviour", "behavior")):
            return True
        # Staff
        if any(kw in q for kw in ("staff", "teacher", "employee")) and any(
            kw in q for kw in ("attendance", "absenteeism", "rate")
        ):
            return True
        # Inventory
        if any(
            kw in q
            for kw in (
                "stock",
                "inventory",
                "supply",
                "supplies",
                "food",
                "meal",
                "consumption",
            )
        ):
            return True
        # Communications
        if any(
            kw in q
            for kw in (
                "message",
                "sms",
                "email",
                "notification",
                "delivery",
                "sent",
                "failed",
            )
        ):
            return True
        # CBC
        if any(
            kw in q
            for kw in ("cbc", "competency", "competencies", "rubric", "assessment")
        ):
            return True
        return False

    # ----------------------------------------------------------- prefetch
    def _prefetch_context(
        self,
        agent: dict[str, Any],
        user_id: int,
        permissions: list[str],
        request_id: str,
        route: str,
        question: str = "",
        roles: list | None = None,
        role_ids: list[int] | None = None,
    ) -> list[tuple[str, dict[str, Any]]]:
        """Prefetch deterministic data for the question before the model answers.

        For any agent with the NLQ tool, run reports.nlq proactively so the
        model receives real governed data in the first turn. For other agents,
        only fetch briefing or catalogue when explicitly asked.
        """
        question_lower = str(question or "").lower()
        candidates = []

        # Data-first: proactively run the governed NLQ tool for any agent
        # that has the NLQ tool, so the model receives real row data instead of
        # hallucinating.
        agent_tools = set(agent.get("tools", ()))
        if "reports.nlq" in agent_tools:
            candidates.append(("reports.nlq", {"question": question}))

        asks_for_briefing = any(
            term in question_lower
            for term in (
                "intelligence briefing",
                "daily briefing",
                "weekly briefing",
                "term briefing",
            )
        )
        asks_for_catalog = any(
            term in question_lower
            for term in (
                "available workflows",
                "what workflows",
                "what tools",
                "what can you do",
            )
        )

        if asks_for_briefing and "reports.insight_brief" in agent_tools:
            candidates.append(("reports.insight_brief", {"cadence": "daily"}))
        if asks_for_catalog and "assistant.catalog" in agent_tools:
            candidates.append(("assistant.catalog", {}))

        if not candidates:
            return []

        results: list[tuple[str, dict[str, Any]]] = []
        workers = min(
            len(candidates),
            max(1, int(getattr(self.config, "max_tool_parallel", 3) or 3)),
        )

        def _run(item: tuple[str, dict[str, Any]]) -> tuple[str, dict[str, Any]]:
            name, args = item
            return name, self.tools.execute(
                user_id,
                permissions,
                name,
                args,
                request_id,
                roles=roles,
                role_ids=role_ids,
            )

        with concurrent.futures.ThreadPoolExecutor(max_workers=workers) as pool:
            for name, result in pool.map(_run, candidates):
                results.append((name, result))
        return results

    def _run_agent_loop(
        self, agent: dict[str, Any], context: dict, question: str
    ) -> dict[str, Any]:
        user_id = int(context["user_id"])
        permissions = list(context["permissions"])
        request_id = context.get("request_id", "")
        route = context.get("route", "")
        module = context.get("module", "dashboard")
        session_id = str(context.get("session_id") or "")
        hints = self.behavior.hints(user_id)
        tools = list(agent["tools"])

        prefs = self._preferences(user_id, context)
        topics = self._session_topics(user_id, session_id, context)
        history_messages = self._conversation_messages(
            user_id, route, session_id, context, question=question
        )
        summary = self._conversation_summary(user_id, session_id, route, context)
        navigation = self._navigation_lines(context.get("sidebar_state"))

        system = agents.system_prompt(agent, context)
        messages: list[dict[str, str]] = [
            {"role": "system", "content": system},
            *history_messages,
        ]
        tool_results: list[str] = []
        tools_used: list[str] = []
        tool_steps = 0
        provider_calls = 0
        answer: dict[str, Any] | None = None

        # Prefetch deterministic context up front, concurrently. Every serial
        # round trip is multiplied by the model's own latency, so the loop
        # starts with the governed facts already in hand and the first provider
        # call can usually answer directly instead of spending a whole
        # provider-call + tool-step pair just to discover what to ask for.
        prefetched = self._prefetch_context(
            agent,
            user_id,
            permissions,
            request_id,
            route,
            question,
            roles=context.get("roles"),
            role_ids=context.get("role_ids"),
        )
        for tool_id, result in prefetched:
            tools_used.append(tool_id)
            tool_results.append(self._compact_result(tool_id, result))

        instruction = str(context.get("instruction") or "")[:400]

        try:
            while provider_calls < MAX_PROVIDER_CALLS:
                envelope = policy.minimize(
                    "system.agent_chat",
                    {
                        key: value
                        for key, value in {
                            "question": question,
                            "audience": "staff",
                            "agent_id": agent["id"],
                            "route": route,
                            "module": module,
                            "behavior_hints": self._hint_lines(hints),
                            "tools": tools,
                            "tool_results": tool_results,
                            "conversation": summary,
                            "session_topics": topics,
                            "preferences": self._preference_lines(prefs),
                            "navigation": navigation,
                            "instruction": instruction,
                        }.items()
                        if value
                    },
                )
                messages.append(
                    {
                        "role": "user",
                        "content": json.dumps(envelope, ensure_ascii=False),
                    }
                )

                provider_calls += 1
                response = self.provider.complete(
                    messages,
                    {"response_format": "json_object", "task": "chat"},
                )
                if not isinstance(response, dict):
                    raise ProviderError(
                        "the agent provider returned an unusable response"
                    )

                action = str(response.get("action") or "final")
                if action != "tool":
                    answer = self._validate_answer(response.get("answer") or response)
                    break
                if (
                    tool_steps >= MAX_TOOL_STEPS
                    or len(tools_used) >= MAX_TOOL_EXECUTIONS
                ):
                    answer = self._fallback_answer(tools_used, tool_results)
                    break

                requests, rejected = self._requested_tools(
                    response, tools, already_run=set(tools_used)
                )
                if rejected:
                    tool_results.append(rejected)
                if not requests:
                    messages.append(
                        {
                            "role": "assistant",
                            "content": json.dumps(response, ensure_ascii=False),
                        }
                    )
                    continue
                requests = requests[: max(0, MAX_TOOL_EXECUTIONS - len(tools_used))]

                # Independent governed tools run concurrently: latency drops to
                # the slowest single call instead of their sum, while the
                # worker count stays hard-capped so nothing floods PHP.
                tool_steps += 1
                for tool_id, tool_result in self._run_tools_parallel(
                    requests, user_id, permissions, request_id
                ):
                    tools_used.append(tool_id)
                    tool_results.append(self._compact_result(tool_id, tool_result))
                    self.behavior.observe(
                        user_id,
                        "tool_use",
                        {
                            "agent_id": agent["id"],
                            "tool": tool_id,
                            "outcome": str(tool_result.get("status") or "ok"),
                        },
                    )
                messages.append(
                    {
                        "role": "assistant",
                        "content": json.dumps(response, ensure_ascii=False),
                    }
                )
        except ProviderError as error:
            return {
                "status": "unavailable",
                "answer": None,
                "tools_used": tools_used,
                "provider_calls": provider_calls,
                "message": str(error)[:300],
            }

        if answer is None:
            answer = self._fallback_answer(tools_used, tool_results)
        return {
            "status": "answered",
            "answer": answer,
            "tools_used": tools_used,
            "provider_calls": provider_calls,
        }

    # ------------------------------------------------------------------ triage
    def _triage(self, question: str, route: str, module: str) -> dict[str, Any] | None:
        lines = agents.describe_for_triage()
        if not lines:
            return None
        envelope = policy.minimize(
            "system.agent_triage",
            {
                "question": question,
                "audience": "staff",
                "agents": lines,
                "route": route,
                "module": module,
            },
        )
        try:
            intent = self.provider.complete(
                [
                    {
                        "role": "system",
                        "content": agents.PROMPT_TEMPLATES["system.agent_triage"],
                    },
                    {
                        "role": "user",
                        "content": json.dumps(envelope, ensure_ascii=False),
                    },
                ],
                {"response_format": "json_object", "task": "triage"},
            )
        except ProviderError:
            return None
        if not isinstance(intent, dict):
            return None
        return agents.resolve(str(intent.get("agent_id") or ""))

    # --------------------------------------------------------------- helpers
    @staticmethod
    def _hint_lines(hints: dict[str, Any]) -> list[str]:
        if not hints.get("known"):
            return []
        lines: list[str] = []
        for key in ("top_domains", "top_workflows", "top_agents"):
            values = hints.get(key) or []
            if values:
                lines.append(f"{key}=" + ",".join(str(v) for v in values[:3]))
        if hints.get("preferred_cadence"):
            lines.append(f"preferred_cadence={hints['preferred_cadence']}")
        return lines[:6]

    @staticmethod
    def _compact_result(tool_id: str, result: dict[str, Any]) -> str:
        flat = dict(result)
        nested = result.get("answer")
        if isinstance(nested, dict):
            flat = {**nested, **result}
        compact: dict[str, Any] = {}
        for field in TOOL_RESULT_FIELDS:
            if field not in flat:
                continue
            value = flat[field]
            if isinstance(value, (list, tuple)):
                items = [
                    str(v)[:120]
                    for v in list(value)[:6]
                    if isinstance(v, (str, int, float))
                ]
                if items:
                    compact[field] = items
            elif isinstance(value, (str, int, float, bool)) or value is None:
                compact[field] = value
        encoded = json.dumps({tool_id: compact}, ensure_ascii=False, default=str)
        if len(encoded) > MAX_TOOL_RESULT_CHARS:
            encoded = encoded[: MAX_TOOL_RESULT_CHARS - 3] + "..."
        return encoded

    @staticmethod
    def _clean(value: Any, limit: int) -> str:
        if isinstance(value, (str, int, float)):
            text = str(value).strip()
        else:
            text = ""
        control = "".join(ch for ch in text if ord(ch) >= 32 or ch in "\n\t")
        return control[:limit]

    def _validate_answer(self, draft: Any) -> dict[str, Any]:
        draft = draft if isinstance(draft, dict) else {}
        # Providers disagree about the key for the answer text ("body",
        # "answer", "text", "message"). Treating an unknown key as "no answer"
        # replaced real replies with a generic placeholder, so the common
        # aliases are accepted before falling back.
        body = ""
        for key in ("body", "answer", "text", "message", "content", "summary"):
            body = self._clean(draft.get(key), 2000)
            if body:
                break
        body = body or (
            "The assistant could not prepare a complete answer. Open the related workspace or rephrase the question."
        )
        answer = {
            "title": self._clean(draft.get("title"), 120) or "Assistant",
            "body": body,
            "next_steps": [
                self._clean(step, 200)
                for step in (draft.get("next_steps") or [])[:6]
                if self._clean(step, 200)
            ],
            "suggested_questions": [
                self._clean(q, 120)
                for q in (draft.get("suggested_questions") or [])[:3]
                if self._clean(q, 120)
            ],
            "escalation_required": bool(draft.get("escalation_required")),
        }

        # Response-side confidentiality choke point. guard.py refuses a question
        # that probes internals; this removes internal detail a model volunteers
        # unasked. Every model answer passes through here, so no agent can bypass
        # it. Mirrors App\API\Services\AiOutputGuard on the PHP edge.
        answer = output_guard.scrub(answer)

        if output_guard.contains_internal_reference(answer):
            # Diagnostic only: no operator identity is in scope here, and the
            # journal must never carry answer text.
            self.journal.write(
                "ai_generation",
                {"type": "answer_output_guard_residual"},
            )

        return answer

    @staticmethod
    def _fallback_answer(
        tools_used: list[str], tool_results: list[str]
    ) -> dict[str, Any]:
        return {
            "title": "I couldn’t verify that",
            "body": "I couldn’t produce a reliable answer from the report results available to me. Please open the related report to verify the figures, or ask about a specific date range.",
            "next_steps": [
                "Open the related report to check the current records.",
                "Ask again with the class, report, or date range you need.",
            ],
            "suggested_questions": [],
            "escalation_required": False,
        }
