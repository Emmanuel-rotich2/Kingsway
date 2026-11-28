"""Prompt field policies - the same allowlist/minimize contract as the
PHP AiPromptPolicy, enforced on every envelope before it reaches a model.

Context limits: 10,000 bytes per envelope; arrays capped at 20 items of
<=500 scalar characters; strings capped at 500 characters per field.
"""

from __future__ import annotations

import json
from typing import Any

FIELDS: dict[str, list[str]] = {
    "system.agent_triage": ["question", "audience", "agents", "route", "module"],
    # Field ORDER is deliberate: OpenAI-compatible providers cache by exact
    # token prefix, so everything stable for a given agent and route comes
    # first and the per-request question comes last. With the question first,
    # every request had a different prefix and could never reuse a cached
    # prompt; with this order the audience/agent/route/tools/hints head is
    # byte-identical across turns for the same operator context.
    "system.agent_chat": [
        "audience",
        "agent_id",
        "route",
        "module",
        "tools",
        "behavior_hints",
        # Constant guidance: answer from the governed results already supplied.
        "grounding_note",
        "tool_results",
        # Conversational continuity: bounded prior turns and the resolved
        # quick-command instruction. Both are capped by the caller and carry
        # no learner identity.
        "conversation",
        # Session-level continuity: stable topic labels, the operator's stated
        # preferences, and where they are in the app. All are short labels with
        # no learner identity.
        "session_topics",
        "preferences",
        "navigation",
        # What the operator is looking at right now: the record open in a modal,
        # the rows they selected, visible KPI labels and active filters. Short
        # labels only, so the assistant can offer page-relevant help without
        # receiving a second copy of the page's data.
        "page_context",
        "instruction",
        "question",
    ],
    "system.workspace_briefing": [
        "route",
        "module",
        "audience",
        "scan",
        "behavior_hints",
    ],
    # Academic planning (lesson plans, schemes of work, assessments, notes,
    # objectives). This entry was missing, so skills/academic_planning.py raised
    # PolicyError("workflow academics.planning has no approved prompt policy")
    # on every call and routes.py, which did not catch PolicyError, turned that
    # into a 500.
    #
    # school_context and web_ideas are JSON blobs rather than short labels, so
    # they need their own caps (see FIELD_CAPS below): the default 500-character
    # cap would truncate the KICD outcomes and the web results into invalid
    # JSON and hand the model a broken grounding payload - the same class of
    # silent evidence loss described in the MAX_CONTEXT_BYTES note above.
    "academics.planning": [
        "kind",
        "question",
        "audience",
        "school_context",
        "web_ideas",
        "instruction",
    ],
    # ============================================================
    # Explicit allowlists for the governed workflows that reach
    # POST /api/workflows/draft.
    #
    # Every key below is derived from the PHP call site that builds the payload
    # - buildAiSchemeInput, buildAiLessonPlanInput, buildAiExceptionSummaryInput,
    # buildAiLatenessPatternInput, BoardingManager::buildAiExceptionSummaryInput,
    # TransportAPI::buildAiOperationsSummaryInput and the inline $input arrays.
    # Nothing here is guessed: a key that appeared in no call site would only
    # widen the prompt for no benefit.
    #
    # Before this block, 42 of the 44 workflows in PHP's WorkflowRegistry had
    # no entry and could run only under the bounded default policy. Every
    # shipped workflow now has an explicit entry, so allow_default=True is no
    # longer needed on any request path and the fallback can be retired.
    # ============================================================
    # Cross-cutting keys every governed payload carries. They are already in the
    # envelope PHP sends, so allowing them costs no scope and loses nothing.
    "_common": [
        "report_date",
        "date_from",
        "date_to",
        "grade_level",
        "term_name",
        "scope",
        "audience",
        "follow_up_intent",
    ],
    "admissions.application_followup_draft": [
        "stage",
        "grade_band",
        "days_waiting",
        "missing_items",
        "channel",
    ],
    "admissions.interview_preparation": [
        "stage",
        "grade_band",
        "days_waiting",
        "interview_focus",
    ],
    "admissions.placement_review": [
        "grade_band",
        "applicant_count",
        "assessed_count",
        "recommendation_band",
    ],
    "admissions.pipeline_review": [
        "stage_counts",
        "oldest_waiting_days",
        "applications_received",
        "interviews_due",
        "placements_pending",
        "payments_pending",
        "enrollment_ready",
    ],
    "academics.scheme_draft": [
        "learning_area",
        "instructional_weeks",
        "strands",
        "sub_strands",
        "learning_outcomes",
        "teacher_intent",
    ],
    "academics.lesson_plan_draft": [
        "learning_area",
        "week_number",
        "strand",
        "sub_strand",
        "scheme_title",
        "learning_outcomes",
        "learning_experiences",
        "resources",
        "assessment_tools",
        "competencies",
        "inquiry_questions",
        "teacher_intent",
    ],
    "academics.assessment_draft": [
        "learning_area",
        "strand",
        "sub_strand",
        "question_count",
        "teacher_intent",
        "learning_outcome",
        "assessment_type",
        "max_marks",
        "competencies",
        "inquiry_questions",
        "assessment_tools",
        "assessment_count",
    ],
    "academics.rubric_draft": [
        "learning_area",
        "learning_outcome",
        "criterion_count",
        "performance_levels",
    ],
    "academics.coverage_review": [
        "configured_learning_area_count",
        "mapped_learning_area_count",
        "unmapped_learning_area_count",
        "mapping_rate",
        "coverage_rate",
        "planned_count",
        "completed_count",
        "overdue_count",
    ],
    "academics.learning_gap_review": [
        "learning_area",
        "learner_count",
        "scored_learner_count",
        "below_threshold_count",
        "missing_evidence_count",
        "gap_bands",
        "assessment_count",
    ],
    # Built by SchedulesController::postAiTimetablePlanningQueue from the
    # planner's message, saved constraints and TimetablePlanningFacts (the
    # same aggregate teaching load the draft solver uses). The old list named
    # keys PHP never sent, so even the planner's question was stripped.
    "academics.timetable_planning": [
        "class_band",
        "period_count",
        "lessons_ready",
        "stream_count",
        "lessons_to_place",
        "stream_loads",
        "setup_gaps",
        "teacher_load",
        "availability_constraints",
        "workload_constraints",
        "room_constraints",
        "double_periods",
        "user_constraints",
        "question",
    ],
    "academics.timetable_review": [
        "review_scope",
        "review_brief",
        "period_count",
        "lessons_ready",
        "stream_count",
        "lessons_to_place",
        "stream_loads",
        "setup_gaps",
        "teacher_load",
    ],
    "academics.exam_timetable_planning": [
        "exam_window",
        "grade_bands",
        "candidate_count",
        "invigilator_count",
        "constraints",
        "unresolved_constraints",
    ],
    "learners.support_planning": [
        "learner_count",
        "support_case_count",
        "referral_count",
        "open_cases",
        "performance_band",
        "resource_type_count",
    ],
    "attendance.exception_summary": [
        "register_count",
        "completed_count",
        "open_count",
        "overdue_count",
        "not_marked_count",
        "scope_stream_count",
        "exception_types",
        "completed",
        "open",
        "overdue",
        "not_marked",
    ],
    "attendance.lateness_pattern_review": [
        "present_count",
        "absent_count",
        "late_count",
        "pattern_band",
        "class_scope",
    ],
    "boarding.exception_summary": [
        "roll_call_session_groups",
        "roll_call_expected_records",
        "roll_call_present_count",
        "roll_call_absent_count",
        "roll_call_permission_count",
        "roll_call_sick_bay_count",
        "recorded_attendance_rate",
        "dormitory_count",
        "capacity",
        "assigned_beds",
        "available_beds",
        "occupancy_rate",
        "present_tonight",
        "absent_or_unknown",
        "on_leave",
        "pending_leaves",
        "urgent_notes",
        "roll_call_rate",
    ],
    "transport.operations_summary": [
        "active_route_count",
        "active_vehicle_count",
        "assigned_passenger_count",
        "vehicle_total",
        "total_active_capacity",
        "overdue_service_count",
        "expired_insurance_count",
        "maintenance_vehicle_count",
        "routes_without_vehicle_count",
        "vehicle_inspections",
        "vehicle_routine",
        "vehicle_repairs",
    ],
    "inventory.replenishment_review": [
        "active_item_count",
        "low_stock_count",
        "out_of_stock_count",
        "low_stock",
        "pending_requisition_count",
        "record_count",
    ],
    "catering.consumption_review": [
        "meals_planned",
        "prepared_meals",
        "actual_servings",
        "quantity_used",
        "waste_quantity",
        "waste_rate",
        "food_items",
        "planned_servings",
    ],
    "maintenance.facilities_review": [
        "equipment_total",
        "equipment_scheduled",
        "equipment_in_progress",
        "equipment_overdue",
        "equipment_pending",
    ],
    "health.welfare_review": [
        "active_visit_count",
        "vaccination_due_count",
        "reconciliation_rule",
        "urgent_cases",
    ],
    "activities.resource_review": [
        "activity_count",
        "participant_count",
        "resource_count",
        "upcoming_activity_count",
        "active_activity_count",
    ],
    "activities.library_review": [
        "days_open",
        "record_count",
        "resource_count",
        "overdue_count",
        "category_exception_count",
    ],
    "staff.hr_review": [
        "staff_count",
        "teaching_staff_count",
        "non_teaching_staff_count",
        "pending_leave_count",
        "on_leave_count",
        "onboarding_count",
        "workload_exception_count",
    ],
    "counseling.welfare_review": [
        "total_cases",
        "open_cases",
        "referral_count",
        "student_case_count",
        "staff_case_count",
        "urgent_cases",
    ],
    "curriculum.kicd_change_interpretation": [
        "change_summary",
        "effective_date",
        "affected_learning_areas",
        "source_url",
    ],
    "finance.reconciliation_review": [
        "unmatched_count",
        "amount_band",
        "currency",
        "reconciliation_rule",
        "record_count",
        "sessions_count",
    ],
    "finance.arrears_review": [
        "student_count",
        "total_outstanding_band",
        "currency",
        "aging_bands",
        "class_concentration",
        "collection_notes",
    ],
    "communications.parent_message_draft": [
        "tone",
        "channel",
        "purpose",
        "follow_ups_due",
        "drafts",
    ],
    "drafts.create": [
        "purpose",
        "tone",
        "channel",
        "format",
        "rows",
        "columns",
    ],
    "communications.parent_portal_assistant": [
        "question",
        "context",
    ],
    # The approved corpus and the bounded prior turns ARE the grounding: with
    # only the question allowed, the model never saw the corpus, could cite
    # nothing, and every public question was escalated.
    "public.faq_assistant": [
        "audience",
        "corpus",
        "conversation",
        "context",
        "question",
    ],
    "research.external_knowledge": [
        "audience",
        "sources",
        "context",
        "question",
    ],
    "reports.kpi_brief": [
        "kpi_count",
        "trend_band",
    ],
    "reports.insight_brief": [
        "signal_count",
        "severity_band",
    ],
    "assistant.catalog": ["route", "module"],
    "assistant.workspace_scan": [
        "route",
        "module",
        "scan",
        "behavior_hints",
    ],
    "reports.school_brief": [
        "domain",
        "signal_count",
    ],
    "reports.nlq": [
        "question",
        "route",
        "module",
        "columns",
        "rows",
    ],
    "reports.aggregate": [
        "aggregate",
        "dimension",
        "value",
    ],
    "charts.generate": [
        "chart_type",
        "series",
        "labels",
        "title",
    ],
    "system.nlq_query": [
        "question",
        "route",
        "module",
        "columns",
        "rows",
    ],
    "system.operations_brief": [
        "queue_total",
        "queue_pending",
        "queue_processing",
        "queue_stale",
        "queue_failed",
        "queue_done",
        "queue_cancelled",
        "queue_dead_letter",
        "oldest_processing_minutes",
        "oldest_pending_minutes",
        "worker_freshness_minutes",
        "error_total_24h",
        "error_critical_24h",
        "error_category_count",
        "error_scan_complete",
        "error_scan_truncated_category_count",
        "error_signatures",
    ],
    "system.security_brief": [
        "failed_login_count",
        "failed_login_identities",
        "permission_denied_count",
        "security_incident_count",
        "top_signals",
    ],
    "system.usage_brief": [
        "period_days",
        "total_requests",
        "unique_users",
        "unique_sessions",
        "login_successes",
        "login_failures",
        "error_count",
        "warning_count",
        "audit_actions",
        "active_users_now",
        "top_routes",
        "top_pages",
        "top_roles",
        "top_users",
        "top_actions",
        "error_roles",
        "device_signals",
    ],
}

# Byte budget for a prompt and for one envelope payload.
#
# This number used to be 10,000, which is barely larger than the guardrail
# block plus the agent contract that every staff prompt must carry. The
# consequence was deterministic: the prompt-size trimmer in the orchestrator
# removed the governed tool_results on EVERY turn, so the model answered fee
# arrears, attendance and enrolment questions with no evidence in front of it -
# and a staff member was answered with a refusal for rows the server had
# already released to them. The budget has to leave room for the evidence,
# which is the only part of the prompt that is actually about this question.
#
# 24,000 bytes is still a hard bound (roughly 6,000 tokens of prompt), and it
# is small next to the models the provider chain serves, which carry contexts
# two orders of magnitude larger.
MAX_CONTEXT_BYTES = 24_000

# How many distinct fields a default (unlisted) workflow may carry. Explicit
# allowlists are unaffected; this only caps a fallback payload.
MAX_DEFAULT_FIELDS = 40

# Workflows that fell back to the bounded default policy, for journaling.
DEFAULT_POLICY_USE: list[str] = []

MAX_ARRAY_ITEMS = 20
MAX_SCALAR_CHARS = 500

# Per-field character caps, for fields whose whole content is one longer blob.
# A field listed here is capped at its own value; everything else keeps
# MAX_SCALAR_CHARS. Truncating a JSON blob at an arbitrary character count
# produces invalid JSON, so these are bounded here AND compacted by the caller,
# and the cap is a backstop rather than the only limit.
FIELD_CAPS: dict[str, int] = {
    "school_context": 8000,
    "web_ideas": 8000,
    "tool_results": 8000,
    "grounding_note": 2000,
    "behavior_hints": 2000,
    "conversation": 4000,
    "page_context": 4000,
    # Grounding corpora (public FAQ, research sources). Callers rank them by
    # relevance first, so the bound drops the least relevant items.
    "corpus": 12000,
    "sources": 12000,
}


def _bounded_json(value: str, cap: int) -> str | None:
    """Fit a JSON-string field under cap without ever emitting invalid JSON.

    Slicing a JSON document at a character limit produces broken JSON, and a
    model handed a truncated blob is worse off than one handed a smaller one.
    Keys are dropped largest-first until the document fits. Returns None when
    the value is not a JSON object/array (an ordinary long string), so the
    caller falls back to the plain character slice.
    """
    try:
        parsed = json.loads(value)
    except (ValueError, TypeError):
        return None
    if not isinstance(parsed, (dict, list)):
        return None

    if isinstance(parsed, list):
        while (
            parsed
            and len(json.dumps(parsed, ensure_ascii=False, separators=(",", ":"))) > cap
        ):
            parsed = parsed[:-1]
        return json.dumps(parsed, ensure_ascii=False, separators=(",", ":")) or None

    for key in sorted(
        parsed,
        key=lambda k: len(json.dumps(parsed[k], ensure_ascii=False)),
        reverse=True,
    ):
        if len(json.dumps(parsed, ensure_ascii=False, separators=(",", ":"))) <= cap:
            break
        del parsed[key]
    return json.dumps(parsed, ensure_ascii=False, separators=(",", ":")) or None


class PolicyError(ValueError):
    pass


def _clean_scalar(
    value: Any, depth: int = 0, limit: int | None = None
) -> str | int | float | bool | None:
    if value is None:
        return None
    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return value
    if isinstance(value, str):
        cap = MAX_SCALAR_CHARS if limit is None else limit
        # A cap larger than the default only ever applies to fields declared in
        # FIELD_CAPS, and for those the slice must never yield invalid JSON.
        if limit is not None and cap > MAX_SCALAR_CHARS and len(value) > cap:
            bounded = _bounded_json(value, cap)
            if bounded is not None:
                return bounded
        return value.strip()[:cap]
    if depth == 0 and isinstance(value, (list, tuple)):
        raise PolicyError("nested arrays are not allowed in prompt fields")
    raise PolicyError("unsupported value in prompt field")


def _allowlist_for(workflow: str, payload: dict[str, Any]) -> list[str]:
    """The allowlist for a workflow: its own entry plus the shared keys."""
    own = FIELDS.get(workflow)
    if own is None:
        return []
    common = FIELDS.get("_common", [])
    return list(dict.fromkeys(common + own))


def default_allowlist(payload: dict[str, Any]) -> list[str]:
    """A bounded allowlist for a workflow that has no explicit entry.

    Every key the caller supplied is permitted, because the payload for these
    workflows is built server-side from aggregates that PHP has already
    re-authorized, and narrowing it here without knowing the workflow would
    silently strip the evidence the model needs. What still applies is the
    whole bound: field count, per-field type, per-field cap and the envelope
    byte budget.

    This is deliberately a FALLBACK, not a policy of record. Every use is
    journaled (see DEFAULT_POLICY_USE) so an explicit FIELDS entry can be
    written for the workflow and this path retired.
    """
    return sorted(str(key) for key in payload.keys())[:MAX_DEFAULT_FIELDS]


def minimize(
    workflow: str,
    payload: dict[str, Any],
    allow_default: bool = False,
) -> dict[str, Any]:
    allowlist = _allowlist_for(workflow, payload)
    if not allowlist:
        if not allow_default:
            if workflow in FIELDS:
                # Listed with no fields of its own beyond _common: that is a
                # valid, deliberately narrow policy, not a missing one.
                allowlist = _allowlist_for(workflow, payload)
            if not allowlist:
                raise PolicyError(f"workflow {workflow} has no approved prompt policy")
        else:
            # Bounded fallback: keep the workflow running, keep every bound, and
            # leave a trace so the gap is visible rather than silent.
            allowlist = default_allowlist(payload)
            DEFAULT_POLICY_USE.append(workflow)

    clean: dict[str, Any] = {}
    for field in allowlist:
        if field not in payload:
            continue
        value = payload[field]
        cap = FIELD_CAPS.get(field)
        if isinstance(value, (list, tuple)):
            items: list[Any] = []
            for item in list(value)[:MAX_ARRAY_ITEMS]:
                if isinstance(item, (list, tuple, dict)):
                    raise PolicyError(f"field {field} contains unsupported nested data")
                items.append(_clean_scalar(item, depth=0, limit=cap))
            if items:
                clean[field] = items
        elif value is not None:
            clean[field] = _clean_scalar(value, limit=cap)
    if len(json.dumps(clean, ensure_ascii=False).encode("utf-8")) > MAX_CONTEXT_BYTES:
        raise PolicyError("prompt context exceeds the approved context limit")
    return clean
