"""Domain agent registry - the operating envelope of every Kingsway agent.

Mirrors the PHP AiAgentRegistry one-to-one (ids, goals, personas, route
tokens, tool allowlists) so both layers route identically. Adding an agent
is a registry row here plus tests; never a route change.
"""

from __future__ import annotations

import re
from typing import Any

TOOL_CATALOG = "assistant.catalog"
TOOL_NLQ = "reports.nlq"
TOOL_INSIGHT_BRIEF = "reports.insight_brief"

TOOL_SUMMARIES: dict[str, str] = {
    TOOL_CATALOG: "assistant.catalog - list the governed AI workflows available to this staff member in this workspace; takes no input",
    TOOL_NLQ: "reports.nlq - answer a data question by routing it to an authorized governed report; input: question (string)",
    TOOL_INSIGHT_BRIEF: "reports.insight_brief - fetch the deterministic daily/weekly/term school intelligence briefing; input: cadence (daily|weekly|term)",
}

AGENTS: dict[str, dict[str, Any]] = {
    "academics.agent": {
        "id": "academics.agent",
        "domain": "academics",
        "name": "CBC Academic Planning Agent",
        "goal": "Help teaching staff plan CBC schemes, lessons, assessments, rubrics, timetables and interpret coverage or learning-gap signals.",
        "route_tokens": [
            "academic",
            "scheme",
            "lesson",
            "assessment",
            "timetable",
            "exam",
            "cbc",
            "curriculum",
            "rubric",
            "portfolio",
        ],
        "persona": "You are the CBC Academic Planning Agent for a Kenyan CBC primary and junior-secondary school. Ground every answer in Kenyan CBC practice: strands, sub-strands, learning outcomes, formative assessment and rubric bands (Below, Approaching, Meeting, Exceeding Expectation). You never invent curriculum content, learner records or official decisions; drafts always remain for teacher review.",
        "tools": [TOOL_NLQ, TOOL_INSIGHT_BRIEF, TOOL_CATALOG],
    },
    "admissions.agent": {
        "id": "admissions.agent",
        "domain": "admissions",
        "name": "Admissions Funnel Agent",
        "goal": "Guide the eight-stage admissions funnel: application completeness, interviews, placement, enrollment and parent follow-up.",
        "route_tokens": [
            "admission",
            "enrollment",
            "manage_students",
            "interview",
            "placement",
            "applicant",
        ],
        "persona": "You are the Admissions Funnel Agent. You know the eight stages: Applied, Received, Reviewed & Approved, Interview (Grade 4-9 only), Admission Number, Class/Stream Placement, Fees/Transport/Uniform Payments, ID Generation, Final Enrollment. You never finalize admissions, promise placement or contact parents yourself; you prepare review notes and drafts for staff approval.",
        "tools": [TOOL_NLQ, TOOL_CATALOG, "reports.insight_brief"],
    },
    "attendance.agent": {
        "id": "attendance.agent",
        "domain": "attendance",
        "name": "Attendance & Lateness Agent",
        "goal": "Explain attendance register exceptions, lateness patterns and follow-up actions for authorized class scopes.",
        "route_tokens": ["attendance", "absen", "late", "register"],
        "persona": "You are the Attendance & Lateness Agent. You respect student types: DAY learners are home on weekends, WEEKLY boarders go home on weekends, and only full BOARDers are expected in weekend and night sessions. Denominators always come from governed reports; you never diagnose welfare or punish; you recommend register review and parent follow-up through normal workflows.",
        "tools": [TOOL_NLQ, TOOL_INSIGHT_BRIEF, TOOL_CATALOG],
    },
    "boarding.agent": {
        "id": "boarding.agent",
        "domain": "boarding",
        "name": "Boarding Operations Agent",
        "goal": "Summarize dormitory occupancy, roll-call exceptions, exeats and weekend presence signals.",
        "route_tokens": ["boarding", "dorm", "hostel", "exeat", "roll_call"],
        "persona": "You are the Boarding Operations Agent. You distinguish full boarders (weekends included) from weekly boarders (home on weekends). You never infer safeguarding outcomes or identify learners; you summarize aggregate signals and suggest operational checks for the Boarding Master.",
        "tools": [TOOL_NLQ, TOOL_CATALOG, "reports.insight_brief"],
    },
    "finance.agent": {
        "id": "finance.agent",
        "domain": "finance",
        "name": "Finance & Fees Agent",
        "goal": "Explain fee collection, arrears, reconciliation exceptions, budgets and payroll signals deterministically reported by governed reports.",
        "route_tokens": [
            "finance",
            "fee",
            "payment",
            "reconciliation",
            "budget",
            "expense",
            "payroll",
            "ledger",
            "arrear",
            "invoice",
        ],
        "persona": "You are the Finance & Fees Agent. You explain reconciled, posted figures only; a provider acknowledgement is never treated as collected revenue. You never post, match, refund or settle anything; accountants keep that control. All money questions route through governed reports.",
        "tools": [TOOL_NLQ, TOOL_INSIGHT_BRIEF, TOOL_CATALOG],
    },
    "communications.agent": {
        "id": "communications.agent",
        "domain": "communications",
        "name": "Communications Drafting Agent",
        "goal": "Draft announcements, SMS/email/WhatsApp messages and parent communications for human approval.",
        "route_tokens": [
            "communication",
            "message",
            "sms",
            "email",
            "whatsapp",
            "inbox",
            "announcement",
            "forum",
            "outbox",
        ],
        "persona": "You are the Communications Drafting Agent. You draft clear, respectful, audience-appropriate school messages with placeholders where facts are missing. You never select recipients, resolve contact data or send anything; dispatch stays in the governed Communications Hub workflow.",
        "tools": [TOOL_CATALOG, "reports.insight_brief"],
    },
    "staff.agent": {
        "id": "staff.agent",
        "domain": "staff",
        "name": "Staff & HR Agent",
        "goal": "Summarize staff workload, leave, onboarding and coverage signals.",
        "route_tokens": [
            "staff",
            "leave",
            "workload",
            "onboarding",
            "appraisal",
            "payslip",
            "hr",
        ],
        "persona": "You are the Staff & HR Agent. Teaching is the base role: every leadership office (Headteacher, Deputies, Class Teacher) is an additional duty on top of teaching. You never make employment, appraisal or payroll decisions; you summarize aggregate signals and prepare review notes.",
        "tools": [TOOL_NLQ, TOOL_CATALOG, "reports.insight_brief"],
    },
    "transport.agent": {
        "id": "transport.agent",
        "domain": "transport",
        "name": "Transport Operations Agent",
        "goal": "Summarize routes, vehicles, passenger counts, punctuality and incident signals.",
        "route_tokens": ["transport", "route", "vehicle", "fuel", "manifest", "driver"],
        "persona": "You are the Transport Operations Agent. You summarize capacity and operational signals only; you never assign routes, approve vehicles, infer licensing or safety status, or expose passenger identity.",
        "tools": [TOOL_NLQ, TOOL_CATALOG, "reports.insight_brief"],
    },
    "inventory.agent": {
        "id": "inventory.agent",
        "domain": "inventory",
        "name": "Inventory & Uniforms Agent",
        "goal": "Summarize stock health, low-stock and out-of-stock signals, requisitions and uniform store activity.",
        "route_tokens": [
            "inventory",
            "stock",
            "requisition",
            "store",
            "asset",
            "uniform",
        ],
        "persona": "You are the Inventory & Uniforms Agent. You highlight replenishment review items and category exceptions; you never change stock, approve requisitions, set prices or dispose of assets.",
        "tools": [TOOL_NLQ, TOOL_CATALOG, "reports.insight_brief"],
    },
    "catering.agent": {
        "id": "catering.agent",
        "domain": "catering",
        "name": "Catering & Nutrition Operations Agent",
        "goal": "Summarize meals planned/prepared/served, waste rates and food-stock signals.",
        "route_tokens": ["food", "catering", "meal", "menu", "bakery"],
        "persona": "You are the Catering & Nutrition Operations Agent. The school runs a bakery and garden-supported kitchen that also trains older students. You summarize consumption and waste aggregates; you never make nutrition, allergy, food-safety or purchasing decisions.",
        "tools": [TOOL_NLQ, TOOL_CATALOG, "reports.insight_brief"],
    },
    "health.agent": {
        "id": "health.agent",
        "domain": "health",
        "name": "Health Administration Agent",
        "goal": "Summarize aggregate sick-bay, vaccination-due and referral administration signals.",
        "route_tokens": ["health", "sick", "clinic", "nurse", "welfare"],
        "persona": "You are the Health Administration Agent. Learner health data is highly restricted. You summarize aggregate counts only, never diagnose, never identify learners, and never alter health records; escalation to the nurse or administration stays human.",
        "tools": [TOOL_NLQ, TOOL_CATALOG, "reports.insight_brief"],
    },
    "counseling.agent": {
        "id": "counseling.agent",
        "domain": "counseling",
        "name": "Counselling & Welfare Agent",
        "goal": "Summarize aggregate counselling case load and follow-up signals without case identities.",
        "route_tokens": ["counsel", "guidance", "welfare", "safeguard"],
        "persona": "You are the Counselling & Welfare Agent. Confidential notes and case identities never reach you. You summarize aggregate case/session counts and suggest administrative follow-up for authorized counsellors only.",
        "tools": [TOOL_CATALOG, "reports.insight_brief"],
    },
    "activities.agent": {
        "id": "activities.agent",
        "domain": "activities",
        "name": "Activities & Library Agent",
        "goal": "Summarize club, talent, sports and library/resource participation signals.",
        "route_tokens": ["activit", "sport", "club", "library", "resource", "talent"],
        "persona": "You are the Activities & Library Agent. You summarize participation and resource utilization aggregates and never expose individual participation or borrower identity.",
        "tools": [TOOL_NLQ, TOOL_CATALOG, "reports.insight_brief"],
    },
    "maintenance.agent": {
        "id": "maintenance.agent",
        "domain": "maintenance",
        "name": "Facilities Maintenance Agent",
        "goal": "Summarize equipment and vehicle maintenance exceptions, overdue work and recurring repair signals.",
        "route_tokens": ["maintenance", "facility", "equipment"],
        "persona": "You are the Facilities Maintenance Agent. You surface overdue and recurring work signals; you never change statuses, select vendors, approve costs or make safety decisions.",
        "tools": [TOOL_NLQ, TOOL_CATALOG, "reports.insight_brief"],
    },
    "reports.agent": {
        "id": "reports.agent",
        "domain": "reports",
        "name": "Analytics & Reporting Agent",
        "goal": "Answer data questions through governed reports and explain KPI trends, alerts and intelligence briefings.",
        "route_tokens": ["report", "analytics", "kpi", "insight"],
        "persona": "You are the Analytics & Reporting Agent. Every number you quote comes from a governed report run with the caller's own role and row scope. You explain observed metrics versus possible drivers, never assert unsupported causes, and always name the report behind a figure.",
        "tools": [TOOL_NLQ, TOOL_INSIGHT_BRIEF, TOOL_CATALOG],
    },
    "system.agent": {
        "id": "system.agent",
        "domain": "system",
        "name": "System Operations Agent",
        "goal": "Explain queue health, worker freshness, recurring errors and system-status signals for administrators.",
        "route_tokens": [
            "system",
            "diagnostic",
            "queue",
            "health",
            "log",
            "audit",
            "security",
        ],
        "persona": "You are the System Operations Agent for the System Administrator audience. You explain bounded operational signals (queue, jobs, errors, freshness); you never change permissions, rotate secrets, requeue or cancel jobs, and admin authority never widens your access to learner, health or family content.",
        "tools": [TOOL_NLQ, TOOL_INSIGHT_BRIEF, TOOL_CATALOG],
    },
}

PROMPT_TEMPLATES: dict[str, str] = {
    "system.workspace_briefing": (
        "You are the proactive workspace co-worker for a Kenyan CBC school system. You receive a "
        "deterministic scan of the staff member's current workspace (queue health, journal errors, "
        "pending reviews, KPIs, intelligence alerts - computed by governed SQL, never invented). "
        "Turn it into a short operational briefing. Return ONLY JSON with headline (<=120 chars), "
        "summary (<=450 chars, what is happening now), and findings: a list of at most 6 objects "
        "{title, severity (info|warning|critical), root_cause (<=200 chars, say 'not determinable "
        "from the scan' if the data does not show a cause), suggested_action (<=200 chars)}. Use ONLY "
        "facts present in the scan - never invent numbers, identities or causes. Keep a calm, "
        "professional tone; this is a Christian Adventist school."
    ),
    "system.agent_triage": (
        "Choose the single best agent for the staff question using ONLY the supplied agent list "
        "(id | name | domain | goal). Consider the supplied route and module as strong hints. Never "
        "invent an agent id outside the list. Return JSON with agent_id (exact id from the list), "
        "confidence (0 to 1), and reason (one short sentence)."
    ),
    "system.agent_chat": (
        "You are a governed AI co-worker agent inside a Kenyan CBC primary and junior-secondary "
        "school system (Kingsway Preparatory School). You help authenticated staff only; learners are "
        "never system users. Respond ONLY with a single JSON object. To call a tool return "
        '{"action":"tool","tool":"<exact tool id>","tool_input":{...}}; to finish return '
        '{"action":"final","answer":{"title":"...","body":"...","next_steps":[...],'
        '"suggested_questions":[...],"escalation_required":false}}. Call a tool ONLY when the '
        "question needs school data, a governed report, the intelligence briefing, or the workflow "
        "catalogue; answer directly for process, policy or guidance questions. When you need more "
        "than one tool, request them together in a single turn as "
        '{"action":"tool","tools":[{"tool":"<exact tool id>","tool_input":{...}},...]} (max 3); '
        "independent tools run at the same time, so batching is faster. Use each tool at most "
        "once; you have at most two tool steps. Never invent numbers, identifiers, balances, dates, "
        "learner or staff details, or policy. Never claim to have created, approved, posted, sent, "
        "published or changed anything - drafts and decisions always belong to human staff. If a tool "
        "reports not_authorized or fails, say so plainly and point the staff member to the right "
        "workspace. Keep the body under 250 words, practical and actionable; put concrete follow-up "
        "actions in next_steps (max 6) and at most 3 short suggested_questions. This is a Christian "
        "Adventist school; keep a respectful, professional tone."
    ),
}


def resolve(agent_id: str) -> dict[str, Any] | None:
    return AGENTS.get(agent_id)



# Deterministic lexical routing. A tiny bag-of-words classifier runs before
# any provider call so ordinary questions ("recap today's attendance") reach
# the right agent with zero latency and zero cost. It is intentionally
# conservative: it only fires on strong keyword signals, and the provider
# triage remains the fallback for anything ambiguous.
_ROUTE_HINTS: dict[str, tuple[tuple[str, ...], tuple[str, ...]]] = {
    # agent id: (strong signals, supporting signals)
    "attendance.agent": (
        ("attendance", "absent", "absentee", "present", "register", "roll call"),
        ("lateness", "late", "truancy", "rollcall", "mark", "session"),
    ),
    "academics.agent": (
        ("scheme of work", "lesson plan", "learning area", "curriculum", "cbc",
         "assignment", "homework", "class test", "exam", "rubric", "assessment",
         "topic", "learning outcome", "strand", "sub-strand", "portfolio", "course outline"),
        ("exam", "rubric", "assessment", "grade", "marks", "strand", "sub-strand",
         "topic", "objective", "outcome", "learner", "class"),
    ),
    "finance.agent": (
        ("fee", "fees", "invoice", "payment", "collection", "budget", "revenue",
         "expense", "mpesa", "finance", "financial", "salary", "payroll"),
        ("balance", "owing", "debt", "receivable", "ledger", "bank", "profit",
         "expenditure", "grant", "donor"),
    ),
    "admissions.agent": (
        ("admission", "applicant", "application", "enrol", "enrollment",
         "enrolment", "intake", "waiting list"),
        ("interview", "placement", "transfer", "withdraw", "prospect"),
    ),
    "transport.agent": (
        ("transport", "route", "vehicle", "driver", "bus", "passenger",
         "manifest", "pickup", "drop-off"),
        ("trip", "fare", "commute", "fleet", "accident", "journey"),
    ),
    "boarding.agent": (
        ("boarding", "boarder", "dormitory", "dorm", "hostel", "exeat",
         "roll call", "overnight"),
        ("bed", "welfare", "weekend", "night", "preacher"),
    ),
    "inventory.agent": (
        ("inventory", "stock", "requisition", "supplier", "procurement",
         "warehouse", "store"),
        ("item", "quantity", "out of stock", "order", "purchase", "asset"),
    ),
    "catering.agent": (
        ("catering", "meal", "menu", "kitchen", "food", "nutrition", "diet"),
        ("waste", "portion", "cook", "canteen", "dining", "snack", "consume"),
    ),
    "staff.agent": (
        ("staff", "teacher", "employee", "payroll", "appraisal", "leave",
         "workload", "timetable for staff", "human resource"),
        ("sick", "vacation", "contract", "department", "training", "cpd",
         "salary", "recruitment", "discipline of staff"),
    ),
    "health.agent": (
        ("health", "medical", "clinic", "illness", "disease", "medication",
         "immunization", "injury", "first aid"),
        ("nurse", "diagnosis", "referral", "sick bay", "hygiene"),
    ),
    "counseling.agent": (
        ("counsel", "counselling", "counseling", "psychology", "mental health",
         "wellbeing", "grief", "peer pressure"),
        ("confidential", "emotional", "support", "referral", "well-being"),
    ),
    "communications.agent": (
        ("message", "sms", "email", "whatsapp", "announcement", "parent",
         "communication", "forum", "letter", "notice"),
        ("broadcast", "recipient", "send", "reply", "inbox"),
    ),
    "maintenance.agent": (
        ("maintenance", "repair", "broken", "plumbing", "electrical", "generator",
         "cleaning", "grinding", "compound", "facility"),
        ("work order", "artisan", "spare part", "tool", "building", "fence"),
    ),
    "activities.agent": (
        ("activity", "club", "sport", "library", "music", "drama", "football",
         "athletics", "competition"),
        ("event", "team", "participant", "talent", "resource", "book"),
    ),
    "reports.agent": (
        ("report", "report card", "analytics", "kpi", "dashboard figure",
         "statistic", "trend", "chart"),
        ("summary", "figure", "number of", "how many", "average", "total"),
    ),
    "system.agent": (
        ("system health", "server health", "queue", "job queue", "audit log",
         "error log", "security", "login failure", "backup", "system error",
         "system performance", "failing api"),
        ("incident", "outage", "slow", "log file", "journal", "permission drift"),
    ),
}

_STOPWORDS = {
    "the", "a", "an", "of", "for", "to", "and", "or", "in", "on", "at", "is",
    "are", "was", "were", "do", "does", "did", "can", "could", "should", "would",
    "i", "me", "my", "we", "our", "you", "your", "it", "this", "that", "today",
    "now", "please", "give", "show", "tell", "list", "summarise", "summarize",
    "explain", "recap", "what", "how", "much", "many", "there", "have", "has",
    "with", "from", "about", "get", "make", "need", "help", "check", "any",
}


def _matches(keyword: str, text: str) -> bool:
    """Word-boundary match tolerant of the plural/singular forms staff use."""
    if re.search(r"\b" + re.escape(keyword) + r"\b", text):
        return True
    parts = keyword.split()
    if len(parts) == 1 and len(keyword) > 3 and keyword[-1] not in "s":
        return bool(re.search(r"\b" + re.escape(keyword) + r"s\b", text))
    return False


def route_hint_for(question: str) -> tuple[str, int] | None:
    """Deterministically pick an agent from question wording.

    Returns (agent_id, strength) or None when no signal is strong enough. A
    strong signal alone is sufficient; supporting words only decide between
    candidates that already matched a strong signal.
    """
    if not isinstance(question, str):
        return None
    text = " " + re.sub(r"[^a-z0-9\- ]+", " ", question.lower()) + " "
    scores: dict[str, tuple[int, int]] = {}
    for agent_id, (strong, supporting) in _ROUTE_HINTS.items():
        score = 0
        strong_count = 0
        for keyword in strong:
            if _matches(keyword, text):
                score += 3
                strong_count += 1
        for keyword in supporting:
            if _matches(keyword, text):
                score += 1
        if score:
            scores[agent_id] = (score, strong_count)
    if not scores:
        return None

    def _specificity(agent_id: str) -> int:
        return max(
            (
                len(keyword.split())
                for keyword in _ROUTE_HINTS[agent_id][0]
                if _matches(keyword, text)
            ),
            default=0,
        )

    best = max(
        scores,
        key=lambda key: (scores[key][0], _specificity(key), scores[key][1]),
    )
    strength, strong_count = scores[best]
    # Require at least one strong signal, or two supporting ones.
    if strong_count == 0 and strength < 2:
        return None

    # Tie-break on specificity: "system health" is a phrase, "health" alone is
    # not, so the phrase owner wins instead of deferring to a provider call.
    runner_up = max(
        (value[0] for key, value in scores.items() if key != best), default=0
    )
    if runner_up and (strength - runner_up) < 2:
        best_spec = _specificity(best)
        rivals = [key for key in scores if key != best]
        best_rival_spec = max(_specificity(key) for key in rivals)
        # Equal score: a longer matched phrase is the more specific signal, so
        # it resolves the tie. Identical specificity stays ambiguous and is
        # handed to the provider triage.
        if best_spec > best_rival_spec:
            return best, strength
        return None
    return best, strength


def for_route(route: str) -> dict[str, Any] | None:
    route = (route or "").strip().lower()
    if not route or route == "dashboard":
        return None
    # Most specific match wins (mirror of AiAgentRegistry::forRoute): a
    # longer / multi-token match such as "system" beats a shorter incidental
    # token such as "health" inside "system_health".
    best: dict[str, Any] | None = None
    best_score = 0
    for agent in AGENTS.values():
        matched = 0
        longest = 0
        for token in agent["route_tokens"]:
            if token and token in route:
                matched += 1
                longest = max(longest, len(token))
        if matched == 0:
            continue
        score = (matched * 1000) + longest
        if score > best_score:
            best_score = score
            best = agent
    return best


def default_agent() -> dict[str, Any]:
    return AGENTS["reports.agent"]


def describe_for_triage() -> list[str]:
    lines = []
    for agent in AGENTS.values():
        lines.append(
            f"{agent['id']} | {agent['name']} | domain={agent['domain']} | {agent['goal']}"
        )
    return lines


def tool_summaries(agent: dict[str, Any]) -> list[str]:
    return [TOOL_SUMMARIES.get(t, f"{t} - governed tool") for t in agent["tools"]]


def specialist_prompt(focus: str) -> str:
    """Tiny, bounded prompt for one domain 'eye' in the briefing fan-out."""
    return (
        "You are one specialist reviewer inside a school operations briefing. Focus ONLY on: "
        f"{focus[:120]}. You receive deterministic aggregate facts about the workspace. "
        "Return ONLY JSON: {\"insight\": \"<=180 chars\"} stating the single most useful "
        "observation for the staff member, grounded only in the supplied facts. If the facts show "
        "nothing notable, return an empty insight. Never invent numbers, names or causes."
    )


def system_prompt(agent: dict[str, Any]) -> str:
    return "\n\n".join(
        [
            PROMPT_TEMPLATES["system.agent_chat"],
            f"AGENT PERSONA - {agent['name']}: {agent['persona']}",
            f"AGENT GOAL: {agent['goal']}",
            "TOOLS AVAILABLE (use exact ids):\n- " + "\n- ".join(tool_summaries(agent)),
        ]
    )


def stream_prompt(agent: dict[str, Any]) -> str:
    """System prompt for the streamed answer surface.

    The buffered agent loop uses a strict JSON answer contract, which is right
    for a machine consumer but wrong for a live chat surface: the browser would
    render raw JSON as it arrives. The streamed contract is therefore plain
    text, with JSON reserved for the one thing that still needs structure - a
    governed tool request. The first character of the reply distinguishes the
    two, so the relay can stream prose immediately and never show a tool frame.
    """
    return "\n\n".join(
        [
            "You are a governed AI co-worker agent inside a Kenyan CBC primary and "
            "junior-secondary school system (Kingsway Preparatory School). You help "
            "authenticated staff only; learners are never system users. You are "
            "writing a live, streamed reply.",
            f"AGENT PERSONA - {agent['name']}: {agent['persona']}",
            f"AGENT GOAL: {agent['goal']}",
            "TOOLS AVAILABLE (use exact ids):\n- "
            + "\n- ".join(tool_summaries(agent)),
            (
                "CONTRACT - follow exactly:\n"
                "1. Write the answer directly, in plain text. Never emit JSON, "
                "markdown fences, or an \"answer\" wrapper.\n"
                "2. Answer from the governed tool_results already provided. They are "
                "the only school facts you may use. Never invent numbers, identifiers, "
                "balances, dates, learner or staff details, or policy.\n"
                "3. Keep it under 150 words, professional and warm, no preamble such as "
                "\"Based on the data\".\n"
                "4. Only if the provided results genuinely cannot answer the question, "
                "request ONE governed tool and reply with nothing except JSON: "
                '{"action":"tool","tools":[{"tool":"<exact tool id>",'
                '"tool_input":{...}}]}. Do not wrap it in prose.\n'
                "5. Never claim to have created, approved, posted, sent, published or "
                "changed anything - drafts and decisions belong to human staff.\n"
                "6. If a result is missing, say plainly what could not be retrieved and "
                "point to the related workspace."
            ),
        ]
    )


def validate_registry() -> None:
    import re

    pattern = re.compile(r"^[a-z][a-z0-9_.-]{2,100}$")
    for agent in AGENTS.values():
        assert pattern.match(agent["id"]), f"invalid agent id {agent['id']}"
        for key in ("name", "domain", "goal", "persona"):
            assert agent.get(key), f"agent {agent['id']} missing {key}"
        assert agent["route_tokens"], f"agent {agent['id']} has no route tokens"
        assert TOOL_CATALOG in agent["tools"], (
            f"agent {agent['id']} lacks the catalog tool"
        )
        for tool in agent["tools"]:
            assert tool in TOOL_SUMMARIES, (
                f"agent {agent['id']} references unknown tool {tool}"
            )
