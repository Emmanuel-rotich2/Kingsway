"""Deterministic pre-flight guard: instant answers and confidentiality refusal.

Two jobs, both served with **zero provider calls**:

1. Latency. Conversational turns ("hello", "thanks") and questions the
   deterministic workspace scan already answers must never wait on a model.
   A greeting that costs 8 seconds is the clearest possible latency defect, so
   it is answered locally in well under a millisecond.
2. Confidentiality. Kingsway Preparatory School's architecture, folder layout,
   database design, deployment topology and credentials are confidential school
   and Angisoft Technologies property. The model must never receive a request
   to disclose them, and must never be able to answer one. Detection is a
   deterministic pattern match, so a refusal cannot be talked around, cannot
   leak the matched terms, and costs nothing.

Refusals are deliberately generic: they state the boundary and route the
person to the authorized contact instead of confirming what exists.
"""

from __future__ import annotations

import re

# Phrases that probe for confidential technical detail.
_CONFIDENTIAL_PATTERNS = tuple(
    re.compile(pattern, re.IGNORECASE)
    for pattern in (
        # source tree, folder and file layout
        r"\b(folder|directory|directories|file\s?structure|folder\s+(layout|structure|arrangement)|"
        r"source\s+code|source\s+tree|repository\s+layout|project\s+structure)\b",
        r"\b(show|list|give|tell|what(?:'s| is)|describe|explain|reveal|walk)\b[^?.]{0,40}"
        r"\b(folder|directory|file|repo|repository|tree)s?\b",
        r"\bwhere\s+(is|are|do)\b[^?.]{0,40}\b(stored|saved|kept|located|file|folder|directory)\b",
        # schema, tables and internal records
        r"\b(database|db|schema|table|column|index|view|stored\s+procedure|migration|"
        r"foreign\s+key|primary\s+key|table\s+name|column\s+name)\b",
        r"\b(dump|export|schema\s+of|structure\s+of)\b[^?.]{0,30}\b(database|db|table|schema)\b",
        r"\b(sql|query)\b[^?.]{0,20}\b(show|reveal|give|send)\b",
        # Bare "list all tables" style probes, minus ordinary school language
        # (a school legitimately talks about canteen tables, desks and rooms).
        r"\b(list|show|tell|give|reveal|what|which|enumerate)\b[^?.]{0,25}"
        r"\b(all\s+|the\s+|every\s+)?(tables?|columns?|rows?|records?)\b"
        r"(?![^?.]{0,20}\b(canteen|dining|dining\s+hall|kitchen|classroom|desk|"
        r"seats?|timetable|meal|furniture|pool|field)\b)",
        # architecture and deployment internals
        r"\b(system\s+architecture|technical\s+architecture|architecture\s+(diagram|of)|"
        r"stack\s+(of|used)|tech\s+stack|technology\s+stack)\b",
        r"\b(api\s+key|api\s+keys|secret|secrets|password|passwords|credential|credentials|"
        r"token|tokens|jwt|private\s+key|\.env|env\s+file|connection\s+string|"
        r"db\s+password|root\s+password)\b",
        r"\b(which|what)\s+port\b|\bport\s+(number|used|is\s+it)\b|\bserver\s+port\b|"
        r"\b(host(server)?|ip\s+address|port\s+number|socket|deploy(ment)?\s+(detail|topology)|"
        r"server\s+(detail|topology|ip|config)|cpanel|directadmin|passenger|nginx|apache)\b",
        # infrastructure / vendor topology
        r"\b(vps|virtual\s+private\s+server|shared\s+hosting|hosting\s+(detail|plan|account)|"
        r"cpanel\s+account|ftp\s+credential|ssh\s+key)\b",
    )
)

# Nothing here may be answered from a school system context.
_CONFIDENTIAL_REDIRECT = (
    "That request touches how the system itself is built — its architecture, "
    "file layout, database design or credentials. Those details are confidential "
    "property of Kingsway Preparatory School and Angisoft Technologies, are not "
    "available through this assistant, and your question was not sent to any "
    "external service. For anything technical about the platform, please contact "
    "Angisoft Technologies through the school's official channels — they built and "
    "maintain the system and are the authorized technical and security contact."
)
_CONFIDENTIAL_NEXT_STEPS = [
    "Contact Angisoft Technologies for technical questions about the platform.",
    "Angisoft Technologies built and maintains the system and is the authorized "
    "technical and security contact.",
    "For school records, policies or reports, use the relevant module in this portal.",
]

_GREETINGS = tuple(
    re.compile(pattern, re.IGNORECASE)
    for pattern in (
        r"^\s*(hi|hey|hello|hola|hallo|good\s+(morning|afternoon|evening|day)|"
        r"greetings|morning|afternoon|evening)\b[\s!.?]*$",
        r"^\s*(hi|hey|hello)\b[\s!.?]*$",
    )
)

# Slash-style quick actions (/recap, /explain more, ...). These map to a
# deterministic instruction that narrows the LLM's answer and costs zero
# provider calls for the command itself.
_SLASH_COMMANDS = {
    "/recap": "Give a concise recap (3–5 bullets) of the key points.",
    "/summary": "Summarise in 3–5 bullet points, plain language.",
    "/list": "List clear, actionable items (numbered).",
    "/list down": "List clear, actionable items (numbered).",
    "/elaborate": "Elaborate further with concrete examples and detail.",
    "/explain more": "Explain in more detail with examples and simple terms.",
    "/explain less": "Explain more concisely, simpler words, fewer details.",
    "/step by step": "Explain step by step, numbered steps.",
    "/use cases": "Give practical use cases with examples.",
    "/exam questions": "Generate 3–5 exam-style questions with answers.",
    "/revision notes": "Create revision notes in bullet form, key points only.",
    "/conclusion points": "Give 3–5 conclusion points, concise.",
    "/concise": "Answer very concisely (1–3 sentences max).",
    "/simple": "Answer in very simple language (primary-school level).",
    "/complex": "Answer with more technical detail and justification.",
    "/bullet": "Answer in bullet points only.",
    "/table": "Present the answer in a markdown table where helpful.",
    "/timeline": "Present the answer as a clear timeline if chronological.",
}

_THANKS = re.compile(
    r"^\s*(thanks?|thank\s+you|many\s+thanks|ta|cheers|appreciate\s+it|asante|"
    r"asante\s+sana|ok(?:ay)?\s+thanks|great\s+thanks)\b[\s!.?]*$",
    re.IGNORECASE,
)

_ACKS = re.compile(
    r"^\s*(ok(?:ay)?|k|kk|yes|yeah|yep|sure|cool|nice|great|perfect|done|"
    r"understood|got\s+it|sounds\s+good|alright|right|continue|go\s+ahead|"
    r"proceed|next)\b[\s!.?]*$",
    re.IGNORECASE,
)

_HELP = re.compile(
    r"^\s*(help|what\s+can\s+you\s+do|what\s+do\s+you\s+do|how\s+can\s+you\s+help|"
    r"commands|what\s+commands|menu)\b[\s!.?]*$",
    re.IGNORECASE,
)

_STUDENT_STATUS_RE = re.compile(
    r"\bhow\s+many\s+(students?|learners?|pupils?|children|boarders?|day\s+students?|"
    r"staff|teachers?)\b|\btotal\s+(number\s+of\s+)?(students?|learners?|staff)\b",
    re.IGNORECASE,
)


def is_confidential(question: str) -> bool:
    """True when the question probes confidential technical internals."""
    if not isinstance(question, str) or not question.strip():
        return False
    return any(pattern.search(question) for pattern in _CONFIDENTIAL_PATTERNS)


def is_smalltalk(question: str) -> bool:
    """True for greetings, thanks, acknowledgements and the help prompt."""
    if not isinstance(question, str):
        return False
    text = question.strip()
    if not text or "\n" in text:
        return False
    for pattern in _GREETINGS:
        if pattern.match(text):
            return True
    return bool(_THANKS.match(text) or _ACKS.match(text) or _HELP.match(text))


def confidential_response(agent: dict | None = None) -> dict:
    """Governed refusal for a confidentiality probe."""
    return {
        "status": "refused",
        "answer": {
            "body": _CONFIDENTIAL_REDIRECT,
            "confidence": "high",
            "next_steps": list(_CONFIDENTIAL_NEXT_STEPS),
            "suggested_questions": [
                "Which reports can I run for my role?",
                "What is pending my review today?",
                "Summarise this week's key figures.",
            ],
            "escalation_required": False,
        },
        "tools_used": [],
        "provider_calls": 0,
        "routing": "confidentiality_guard",
        "agent": {
            "id": (agent or {}).get("id", "system.agent"),
            "name": (agent or {}).get("name", "System Co-worker"),
            "domain": (agent or {}).get("domain", "system"),
        },
    }


def greeting_response(question: str, agent: dict | None = None) -> dict:
    """Instant conversational reply, served without a provider call."""
    text = (question or "").strip()
    if _THANKS.match(text):
        body = "You're welcome. Ask me about any module, report or pending review whenever you need it."
    elif _HELP.match(text):
        body = (
            "I'm your staff co-worker. I can summarize your workspace, run authorized "
            "reports, review pending approvals and explain school figures within your "
            "role and permissions."
        )
    elif _ACKS.match(text):
        body = "Understood. What would you like to look at next?"
    else:
        body = (
            "Hello. I'm your staff co-worker for Kingsway Preparatory School. "
            "I can summarize your workspace, surface anything pending your review, "
            "and run authorized reports within your role."
        )
    return {
        "status": "answered",
        "answer": {
            "body": body,
            "confidence": "high",
            "next_steps": [],
            "suggested_questions": [
                "What needs my attention today?",
                "Summarise my workspace.",
                "Which reports can I run?",
            ],
            "escalation_required": False,
        },
        "tools_used": [],
        "provider_calls": 0,
        "routing": "instant",
        "agent": {
            "id": (agent or {}).get("id", "system.agent"),
            "name": (agent or {}).get("name", "System Co-worker"),
            "domain": (agent or {}).get("domain", "system"),
        },
    }


def wants_enrollment_count(question: str) -> bool:
    """True for the plain learner/staff headcount question."""
    if not isinstance(question, str):
        return False
    return bool(_STUDENT_STATUS_RE.search(question))

def extract_slash(question: str) -> tuple[str, str | None]:
    """Extract a slash command (/recap, /step by step, ...) if present.

    Returns (clean_question, instruction). The instruction is a bounded
    directive appended to the answer, keeping RBAC unchanged.
    """
    if not isinstance(question, str):
        return question, None
    text = question.strip()
    if not text:
        return text, None
    # Match first token that is a known slash command (case-insensitive).
    for token, instr in _SLASH_COMMANDS.items():
        lower = text.lower()
        if lower.startswith(token):
            remainder = text[len(token) :].strip()
            if remainder.startswith(":"):
                remainder = remainder[1:].strip()
            # If only the command was given, keep the question generic but apply
            # the instruction; otherwise preserve the remainder as the question.
            clean = remainder or text
            return clean, instr
    return text, None
