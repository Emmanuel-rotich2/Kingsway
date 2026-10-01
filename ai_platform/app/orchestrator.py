"""Agent orchestration: triage -> bounded tool loop -> structured synthesis.

Control flow stays deterministic Python; the model only ever proposes a
strict-JSON action (tool or final). Every proposal is validated against the
agent's tool allowlist, and every tool executes through the PHP governance
edge. Bounded: max 2 tool steps, max 3 provider calls per assist.
"""

from __future__ import annotations

import json
import time
from typing import Any

from . import agents, policy
from .behavior import BehaviorStore
from .journal import Journal, hash_text
from .providers import Provider, ProviderError
from .tools import ToolBridge

MAX_TOOL_STEPS = 2
MAX_PROVIDER_CALLS = 3
MAX_TOOL_RESULT_CHARS = 500
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
)


class Orchestrator:
    def __init__(
        self,
        provider: Provider,
        tools: ToolBridge,
        behavior: BehaviorStore,
        journal: Journal,
    ) -> None:
        self.provider = provider
        self.tools = tools
        self.behavior = behavior
        self.journal = journal

    # ------------------------------------------------------------------ assist
    def assist(self, context: dict, question: str) -> dict[str, Any]:
        from .security import bound_question

        question = bound_question(question)
        user_id = int(context["user_id"])
        permissions = list(context["permissions"])
        route = context.get("route", "")
        module = context.get("module", "dashboard")
        request_id = context.get("request_id", "")

        agent = agents.for_route(route)
        routing = "route_match"
        if agent is None:
            agent = self._triage(question, route, module)
            routing = "triage"
        if agent is None:
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

        return {
            **result,
            "routing": routing,
            "agent": {
                "id": agent["id"],
                "name": agent["name"],
                "domain": agent["domain"],
            },
        }

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
        )
        hints = self.behavior.hints(user_id)

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
                {"response_format": "json_object"},
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
        return result

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

    # ------------------------------------------------------------- agent loop
    def _run_agent_loop(
        self, agent: dict[str, Any], context: dict, question: str
    ) -> dict[str, Any]:
        user_id = int(context["user_id"])
        permissions = list(context["permissions"])
        request_id = context.get("request_id", "")
        route = context.get("route", "")
        module = context.get("module", "dashboard")
        hints = self.behavior.hints(user_id)
        tools = list(agent["tools"])

        system = agents.system_prompt(agent)
        messages: list[dict[str, str]] = [{"role": "system", "content": system}]
        tool_results: list[str] = []
        tools_used: list[str] = []
        provider_calls = 0
        answer: dict[str, Any] | None = None

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
                    messages, {"response_format": "json_object"}
                )
                if not isinstance(response, dict):
                    raise ProviderError(
                        "the agent provider returned an unusable response"
                    )

                action = str(response.get("action") or "final")
                if action != "tool":
                    answer = self._validate_answer(response.get("answer") or response)
                    break
                if len(tools_used) >= MAX_TOOL_STEPS:
                    answer = self._fallback_answer(tools_used, tool_results)
                    break

                tool_id = str(response.get("tool") or "")
                raw_tool_input = response.get("tool_input")
                tool_input: dict = (
                    raw_tool_input if isinstance(raw_tool_input, dict) else {}
                )
                if tool_id not in tools:
                    tool_results.append(
                        "error: tool not available to this agent; available tools: "
                        + ", ".join(tools)
                    )
                    messages.append(
                        {
                            "role": "assistant",
                            "content": json.dumps(response, ensure_ascii=False),
                        }
                    )
                    continue

                tool_result = self.tools.execute(
                    user_id, permissions, tool_id, tool_input, request_id
                )
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
                {"response_format": "json_object"},
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
        body = self._clean(draft.get("body"), 2000) or (
            "The assistant could not prepare a complete answer. Open the related workspace or rephrase the question."
        )
        return {
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

    @staticmethod
    def _fallback_answer(
        tools_used: list[str], tool_results: list[str]
    ) -> dict[str, Any]:
        return {
            "title": "Assistant ran out of steps",
            "body": "The assistant gathered the information it could within its step budget but could not complete a final summary. The collected tool results are reflected in the workspace.",
            "next_steps": [
                "Review the results above in the related workspace.",
                "Re-ask the specific part that is still unclear.",
            ],
            "suggested_questions": [],
            "escalation_required": False,
        }
