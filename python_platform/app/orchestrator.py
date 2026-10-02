"""Agent orchestration: triage -> bounded tool loop -> structured synthesis.

Control flow stays deterministic Python; the model only ever proposes a
strict-JSON action (tool or final). Every proposal is validated against the
agent's tool allowlist, and every tool executes through the PHP governance
edge. Bounded: max 2 tool steps, max 3 provider calls per assist.
"""

from __future__ import annotations

import concurrent.futures
import json
import time
from typing import Any

from . import agents, guard, output_guard, policy
from .behavior import BehaviorStore
from .conversation import ConversationStore
from .journal import Journal, hash_text
from .providers import Provider, ProviderError
from .tools import ToolBridge

MAX_TOOL_STEPS = 2
MAX_TOOL_EXECUTIONS = 6
MAX_PROVIDER_CALLS = 2
# Retained follow-up turns supplied by the client for conversational context.
MAX_HISTORY_TURNS = 6
CONVERSATION_PROMPT_TURNS = 4
MAX_TOOLS_PER_STEP = 3
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
        conversation: ConversationStore | None = None,
    ) -> None:
        self.provider = provider
        self.tools = tools
        self.behavior = behavior
        self.journal = journal
        # Optional so tests and ephemeral contexts keep working unchanged.
        self.conversation = conversation
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

        agent = agents.for_route(route)

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
        self._remember(
            int(context["user_id"]),
            str(context.get("route", "")),
            question,
            result.get("answer"),
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

        return {
            **result,
            "routing": routing,
            "agent": {
                "id": agent["id"],
                "name": agent["name"],
                "domain": agent["domain"],
            },
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
                        user_id, permissions, tool_id, tool_input, request_id
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
        agent = agents.for_route(route)

        # Same deterministic pre-flight as the buffered path.
        if guard.is_confidential(question):
            result = guard.confidential_response(agent)
            yield {"kind": "final", **result}
            return
        if guard.is_smalltalk(question):
            result = guard.greeting_response(question, agent)
            yield {"kind": "final", **result}
            return

        routing = "route_match"
        if agent is None:
            hinted = agents.route_hint_for(question)
            if hinted is not None:
                agent = agents.AGENTS.get(hinted[0])
                routing = "lexical"
        if agent is None:
            agent = agents.default_agent()
            routing = "default"

        started = time.monotonic()
        allowed_tools = list(agent["tools"])
        prefetched = self._prefetch_context(
            agent, user_id, permissions, request_id, route
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

        history = self._conversation_turns(user_id, route)
        if not history:
            history = list((context.get("history") or []))[-MAX_HISTORY_TURNS:]
        instruction = (
            slash_instruction or str((context.get("instruction") or "") or "")
        )[:400]
        base_envelope = {
            "question": question,
            "audience": "staff",
            "agent_id": agent["id"],
            "route": route,
            "module": module,
            "behavior_hints": self._hint_lines(hints),
            "grounding_note": (
                "Governed results are already in tool_results. Answer from them; "
                "only request a tool if the results genuinely do not cover the "
                "question."
            ),
            "tool_results": tool_results,
            "conversation": history,
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
                {"role": "system", "content": agents.stream_prompt(agent)},
                {"role": "user", "content": json.dumps(envelope, ensure_ascii=False)},
            ]
            # An oversized envelope must be trimmed rather than lose the answer.
            self._assert_prompt_fits(messages)

            body = ""
            buffered = ""
            emitted = 0
            is_tool_frame: bool | None = None
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
                        "text": "Consulting the governed school records…",
                    }
                    for tool_id, tool_input in requests:
                        payload = dict(tool_input or {})
                        if tool_id == "reports.nlq":
                            payload.setdefault("question", question)
                        result = self.tools.execute(
                            user_id, permissions, tool_id, payload, request_id
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
        self._remember(user_id, route, question, answer)
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
        }

    def _conversation_turns(self, user_id: int, route: str) -> list[str]:
        """Prior turns flattened to "Q: ... / A: ..." strings.

        Flat strings are required: the prompt policy accepts only scalar array
        items, which is what keeps arbitrary nested content out of the prompt.
        The list is also kept short so the total envelope stays well inside the
        approved context budget alongside governed tool results.
        """
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

    def _remember(
        self, user_id: int, route: str, question: str, answer: dict[str, Any] | None
    ) -> None:
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

        Governed tool results are the only unbounded part of the envelope. If a
        rare report result pushes the prompt past the approved budget, drop the
        oldest tool results one at a time rather than losing the answer.
        """
        if policy.MAX_CONTEXT_BYTES <= 0:
            return
        while self._prompt_bytes(messages) > policy.MAX_CONTEXT_BYTES:
            user_turn = next(
                (message for message in messages if message.get("role") == "user"),
                None,
            )
            if user_turn is None:
                return
            try:
                payload = json.loads(user_turn["content"])
            except (TypeError, ValueError):
                return
            results = payload.get("tool_results")
            if not isinstance(results, list) or len(results) <= 1:
                payload.pop("tool_results", None)
            else:
                payload["tool_results"] = results[1:]
            user_turn["content"] = json.dumps(payload, ensure_ascii=False)
            if "tool_results" not in payload and not results:
                return

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
        return self._validate_answer(parsed)

    # ----------------------------------------------------------- prefetch
    def _prefetch_context(
        self,
        agent: dict[str, Any],
        user_id: int,
        permissions: list[str],
        request_id: str,
        route: str,
    ) -> list[tuple[str, dict[str, Any]]]:
        """Warm the governed facts the agent almost always needs.

        The workspace scan and the workflow catalogue are both cheap,
        deterministic, authorized reads, and the model needs both to answer
        anything about this user's context. Running them concurrently here
        removes one full provider-call/tool-step pair from the critical path.
        """
        # The briefing is the deterministic, authorized, cached source of real
        # numbers for the current route. Without it the model has no facts and
        # can only restate the workflow catalogue, which is why an ungrounded
        # question previously produced a placeholder answer.
        wanted = {
            name: args
            for name, args in (
                ("assistant.workspace_scan", {"route": route or "dashboard"}),
                ("reports.insight_brief", {"cadence": "daily"}),
                ("assistant.catalog", {}),
            )
            if name in agent.get("tools", ())
        }
        if not wanted:
            return []

        results: list[tuple[str, dict[str, Any]]] = []
        workers = min(
            len(wanted), max(1, int(getattr(self.config, "max_tool_parallel", 3) or 3))
        )

        def _run(item: tuple[str, dict[str, Any]]) -> tuple[str, dict[str, Any]]:
            name, args = item
            return name, self.tools.execute(
                user_id, permissions, name, args, request_id
            )

        with concurrent.futures.ThreadPoolExecutor(max_workers=workers) as pool:
            for name, result in pool.map(_run, list(wanted.items())):
                results.append((name, result))
        return results

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
        tool_steps = 0
        provider_calls = 0
        answer: dict[str, Any] | None = None

        # Prefetch deterministic context up front, concurrently. Every serial
        # round trip is multiplied by the model's own latency, so the loop
        # starts with the governed facts already in hand and the first provider
        # call can usually answer directly instead of spending a whole
        # provider-call + tool-step pair just to discover what to ask for.
        prefetched = self._prefetch_context(
            agent, user_id, permissions, request_id, route
        )
        for tool_id, result in prefetched:
            tools_used.append(tool_id)
            tool_results.append(self._compact_result(tool_id, result))

        history = self._conversation_turns(user_id, route)
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
                            "conversation": history,
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
            "title": "Assistant ran out of steps",
            "body": "The assistant gathered the information it could within its step budget but could not complete a final summary. The collected tool results are reflected in the workspace.",
            "next_steps": [
                "Review the results above in the related workspace.",
                "Re-ask the specific part that is still unclear.",
            ],
            "suggested_questions": [],
            "escalation_required": False,
        }
