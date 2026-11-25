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

from . import agents, commands, guard, output_guard, policy
from .behavior import BehaviorStore
from .chat_sessions import ChatSessionStore
from .conversation import ConversationStore
from .journal import Journal, hash_text
from .personality import personality_lines
from .providers import Provider, ProviderError
from .tools import (
    ANALYTICS_CHART_TYPES,
    DOCUMENT_TOOL,
    ToolBridge,
    SYSTEM_ADMIN_PERMISSION,
)

# Briefing findings whose suggested action says nothing is outstanding.
_NON_ACTIONABLE_RE = re.compile(
    r"^(no|none|nothing)\b.*\b(pending|at this time|outstanding|required|needed|to (do|review))\b"
    r"|^no action( is)? (needed|required)",
    re.IGNORECASE,
)

MAX_TOOL_STEPS = 2
MAX_TOOL_EXECUTIONS = 6
# Three provider calls per turn: one to propose tools, one to answer
# after those tools ran, and one spare for a follow-up (a chart drawn
# from a report, or a specialist's answer folded in). Two calls left a
# tool-heavy question with no answer at all.
MAX_PROVIDER_CALLS = 3
# Streamed rounds and mid-stream tool steps. The streamed surface is
# bound tighter than the buffered one only because prose must keep moving;
# the numbers are high enough for report -> chart source -> figure.
MAX_STREAM_ROUNDS = 4
MAX_STREAM_TOOL_STEPS = 3
# Retained follow-up turns supplied by the client for conversational context.
MAX_HISTORY_TURNS = 12
CONVERSATION_PROMPT_TURNS = 12
HISTORY_MESSAGE_CHARS = 1200
HISTORY_CHAR_BUDGET = 5000
SUMMARY_LINES = 8
MAX_TOOLS_PER_STEP = 3
MAX_TOOL_RESULT_CHARS = 4000
# Sub-agent delegation. A primary agent may hand one bounded sub-question to a
# specialist and fold the result back into its own turn. Budgets are smaller
# than the parent's so a chain of delegations cannot outrun the request, and
# depth is capped so a delegate can never delegate again into an unbounded fan
# out. Every sub-turn inherits the SAME operator permissions — delegation is a
# reasoning tool, never a scope-widening one.
MAX_DELEGATIONS = 2
# A sub-turn may run one governed tool round, then must answer from it.
MAX_DELEGATE_TOOL_ROUNDS = 1
# Fields of a governed tool result that reach the model.
#
# Internal identifiers are deliberately absent: `workflow_id`, `draft_job_id`,
# `run_id` and `action_level` describe how the platform is wired, not what the
# staff member asked. Passing them through meant the model echoed "workflow_id:
# drafts.create" back into a chat answer, which is exactly the disclosure the
# confidentiality guard exists to prevent. A staff member needs the draft's
# title and its review state, never the tool id that produced it.
TOOL_RESULT_FIELDS = (
    "status",
    "draft_id",
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
    # What the document producer may report about a file it wrote: its format,
    # its name and its size. The bytes themselves are deliberately absent.
    "format",
    "download_name",
    "size_bytes",
    # Governed report rows. They are dicts, which the generic list filter
    # would drop, so they are flattened to "column=value" lines below. The
    # same rows are what analytics.chart draws from.
    "rows_withheld",
    # Web-search results. Tools._execute_web_tool flattens each finding to a
    # "Title — snippet" string carrying its source URL, because this filter
    # only keeps scalar list items: a dict would silently vanish from the
    # prompt and the agent would answer from model knowledge alone.
    "query",
    "results",
    "sources",
    "source_count",
    # web.fetch fields: the title and the page text itself. Without these a
    # fetched page reaches the agent as a bare "answered" and it answers from
    # model memory instead of the source it just retrieved.
    "url",
    "title",
    "content",
    "char_count",
    "truncated",
    # web.crawl fields: the bounded multi-page reading session. `pages` is a
    # list of "Title — URL" strings and `content` is the concatenated excerpts,
    # so both survive _compact_result and the answer can cite each page.
    "seed",
    "pages",
    "page_count",
)


def _bounded_chart_result(tool_id: str, result: Any) -> dict[str, Any] | None:
    """Extract a small, display-only chart from the governed PHP report tool."""
    if (
        tool_id != "charts.generate"
        or not isinstance(result, dict)
        or result.get("status") != "answered"
    ):
        return None
    answer = result.get("answer") if isinstance(result.get("answer"), dict) else {}
    chart = answer.get("chart")
    if not isinstance(chart, dict) or chart.get("type") not in {
        "bar",
        "line",
        "pie",
        "doughnut",
        "area",
    }:
        return None
    labels = chart.get("labels") if isinstance(chart.get("labels"), list) else []
    labels = [str(label)[:120] for label in labels[:25]]
    datasets = []
    for dataset in (
        chart.get("datasets") if isinstance(chart.get("datasets"), list) else []
    )[:4]:
        if not isinstance(dataset, dict):
            continue
        values = dataset.get("data") if isinstance(dataset.get("data"), list) else []
        data = []
        for value in values[: len(labels)]:
            if isinstance(value, (int, float)) and not isinstance(value, bool):
                data.append(
                    value if value == value and abs(value) != float("inf") else None
                )
            else:
                data.append(None)
        datasets.append(
            {"label": str(dataset.get("label") or "Values")[:80], "data": data}
        )
    if not labels or not datasets:
        return None
    scope = (
        answer.get("effective_scope")
        if isinstance(answer.get("effective_scope"), dict)
        else {}
    )
    allowed_scope = {
        key: scope[key]
        for key in (
            "type",
            "academic_year_id",
            "academic_year_term_id",
            "class_id",
            "stream_id",
            "learning_area_id",
            "user_id",
        )
        if key in scope
    }
    return {
        "chart": {
            "type": chart["type"],
            "title": str(chart.get("title") or "Report chart")[:120],
            "labels": labels,
            "datasets": datasets,
        },
        "report_code": str(answer.get("report_code") or "")[:100],
        "row_count": int(answer.get("row_count") or len(labels)),
        "preview_limited": bool(answer.get("preview_limited")),
        "effective_scope": allowed_scope,
    }


# A rendered analytics figure is a PNG data URI. It is shown, never spoken:
# the image must NOT enter the prompt (base64 would consume the whole context
# budget), so it is carried alongside `charts` in a separate display-only field.
_IMAGE_DATA_URI = re.compile(r"^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/=]+$")
MAX_ARTIFACT_BYTES = 2_000_000


def _analytics_artifact(tool_id: str, result: Any) -> dict[str, Any] | None:
    """Extract a display-only figure produced by analytics.chart."""
    if tool_id != "analytics.chart" or not isinstance(result, dict):
        return None
    if result.get("status") != "answered":
        return None
    image = result.get("image")
    if not isinstance(image, str) or not _IMAGE_DATA_URI.match(image):
        return None
    if len(image) > MAX_ARTIFACT_BYTES:
        return None
    return {
        "kind": "image",
        "mime_type": "image/png",
        "title": str(result.get("title") or "Chart")[:120],
        "image": image,
        "summary": str(result.get("summary") or "")[:400],
        "download_name": _artifact_filename(str(result.get("title") or "chart"), "png"),
    }


# What each produced format is called in the render window, so the browser
# knows which element to draw (a PDF iframe is not a video element).
_ARTIFACT_KINDS = {
    "pdf": "document",
    "docx": "document",
    "video": "video",
    "audio": "audio",
}


def _document_artifact(tool_id: str, result: Any) -> dict[str, Any] | None:
    """Extract a produced PDF, DOCX, video or audio from documents.produce.

    The file itself is a base64 data URI, which is display-only: it is never
    added to TOOL_RESULT_FIELDS, so the model learns that a document exists and
    how large it is, but never receives the bytes.
    """
    if tool_id != DOCUMENT_TOOL or not isinstance(result, dict):
        return None
    if result.get("status") != "answered":
        return None
    data_uri = result.get("data_uri")
    if not isinstance(data_uri, str) or not data_uri.startswith("data:"):
        return None
    if len(data_uri) > MAX_ARTIFACT_BYTES:
        return None
    fmt = str(result.get("format") or "pdf")
    mime = str(result.get("mime_type") or "application/octet-stream")
    title = str(result.get("title") or "Document")[:120]
    return {
        "kind": _ARTIFACT_KINDS.get(fmt, "file"),
        "mime_type": mime,
        "title": title,
        "url": data_uri,
        "summary": str(result.get("summary") or "")[:400],
        "download_name": str(result.get("download_name") or "download"),
        "size_bytes": int(result.get("size_bytes") or 0),
    }


def _artifact_filename(title: str, extension: str) -> str:
    slug = re.sub(r"[^a-z0-9]+", "-", title.lower()).strip("-")[:60] or "chart"
    return f"{slug}.{extension}"


# Staff-facing labels for the governed tools an answer used. The ids themselves
# ("reports.nlq", "charts.generate") are internal: they disclose how the
# platform is built, which the confidentiality guard is there to prevent. The
# labels describe what the staff member got, which is all a chat surface needs.
_TOOL_LABELS = {
    "reports.nlq": "governed school report",
    "reports.aggregate": "governed aggregation",
    "charts.generate": "chart source report",
    "analytics.chart": "chart",
    "analytics.summary": "data summary",
    "analytics.forecast": "trend projection",
    "drafts.create": "draft prepared for review",
    DOCUMENT_TOOL: "document prepared for download",
    "reports.insight_brief": "intelligence briefing",
    "assistant.catalog": "workflow catalogue",
    "web.search": "public web search",
    "web.fetch": "public web page",
    "web.crawl": "public website review",
}


def _public_tool_labels(tools_used: list[str]) -> list[str]:
    """Human labels for the tools an answer used, in order, deduplicated."""
    labels: list[str] = []
    for tool_id in tools_used:
        if tool_id.startswith("delegate:"):
            labels.append("specialist advice")
            continue
        label = _TOOL_LABELS.get(tool_id)
        if label is None:
            # An unknown tool id is never shown: the label falls back to a
            # generic description rather than exposing the identifier.
            label = "governed school data"
        if label not in labels:
            labels.append(label)
    return labels


# Words that make a claim about an extreme. A sentence naming one of these
# and a class label is checkable against the authorized rows, so a swapped
# "highest"/"lowest" is detectable rather than silently delivered.
_SUPERLATIVE_WORDS = (
    "highest",
    "lowest",
    "largest",
    "smallest",
    "biggest",
    "greatest",
    "most",
    "least",
    "top",
)


def _sentences(text: str) -> list[str]:
    """Split prose into sentences without importing a sentence tokenizer."""
    parts = re.split(r"(?<=[.!?])\s+|\n+", str(text or ""))
    return [part.strip() for part in parts if part.strip()]


def _number_tokens(text: str) -> list[float]:
    """Every number written in the prose, as floats."""
    found: list[float] = []
    for match in re.finditer(r"\d[\d,]*(?:\.\d+)?", str(text or "")):
        raw = match.group(0).replace(",", "")
        try:
            found.append(float(raw))
        except ValueError:
            continue
    return found


_HEAD_COUNT_WORDS = (
    "student",
    "learner",
    "pupil",
    "family",
    "families",
    "guardian",
    "parent",
    "staff",
    "teacher",
    "applicant",
    "count",
    "headcount",
    "number_of",
    "num_",
)


def _is_head_count_column(column: str) -> bool:
    """True for columns that count people/records; their per-row values must not be summed."""
    lowered = column.lower()
    return any(word in lowered for word in _HEAD_COUNT_WORDS)


def _close(value: float, numbers: list[float], tolerance: float = 0.02) -> bool:
    for candidate in numbers:
        if candidate == 0:
            if abs(value) < 1e-9:
                return True
            continue
        if abs(value - candidate) / abs(candidate) <= tolerance:
            return True
    return False


# Which numeric column a sentence is claiming about. The words the school
# actually uses map onto the governed column names, so "how many students are
# in arrears" is read as the student-count column and "total arrears" as the
# money column.
_COLUMN_WORDS = {
    "total_arrears": ("arrears", "total", "outstanding", "balance", "owed"),
    "students_in_arrears": ("students", "pupils", "learners", "count", "families"),
    "attendance_rate": ("rate", "attendance", "percent", "present"),
    "present": ("present", "attended"),
    "absent": ("absent", "missed"),
}


def _column_for(
    sentence: str, numeric_columns: list[str], superlatives: list[int]
) -> str:
    """Which numeric column a superlative sentence is claiming about.

    Picked by proximity, not by first match: "highest total arrears ... 48
    students" contains both the money word and the count word, and the claim is
    about the one standing next to the superlative. Matching the first keyword
    instead read every "total arrears" sentence as a student-count claim and
    flagged correct answers as contradictions.
    """
    lowered = sentence.lower()
    best_column = numeric_columns[0]
    best_distance: int | None = None
    for column in numeric_columns:
        for word in _COLUMN_WORDS.get(column, ()):
            position = lowered.find(word)
            if position < 0:
                continue
            distance = min(
                (abs(position - marker) for marker in superlatives), default=None
            )
            if distance is None:
                continue
            if best_distance is None or distance < best_distance:
                best_distance = distance
                best_column = column
    return best_column


def _claims_contradict_rows(rows: list[dict[str, Any]], body: str) -> bool:
    """True when the prose points at the wrong row as the extreme.

    The model is allowed to explain the authorized rows; it is not allowed to
    name the wrong one as the biggest. A live turn delivered "Grade 2 has the
    highest total arrears ... while Grade 7 has the lowest" for data in which
    Grade 7 is the highest and PP3 is the lowest. Each superlative sentence is
    therefore checked against the rows it was given, and when it fails the
    deterministic rendering is used instead - the figures the staff member
    sees are then the ones the server actually authorized.
    """
    if not rows or not body:
        return False
    numeric_columns: list[str] = []
    for row in rows:
        for key, value in row.items():
            if isinstance(value, bool):
                continue
            if isinstance(value, (int, float)) or (
                isinstance(value, str) and _number_tokens(value)
            ):
                if key not in numeric_columns:
                    numeric_columns.append(str(key))
    if not numeric_columns:
        return False

    labels: dict[str, dict[str, Any]] = {}
    for row in rows:
        for key, value in row.items():
            if isinstance(value, str) and value.strip():
                labels.setdefault(value.strip(), row)
    if not labels:
        return False

    for sentence in _sentences(body):
        lowered = sentence.lower()
        asks_high = any(
            word in lowered
            for word in ("highest", "largest", "biggest", "greatest", "most", "top")
        )
        asks_low = any(word in lowered for word in ("lowest", "smallest", "least"))
        if not asks_high and not asks_low:
            continue
        superlatives = [
            lowered.find(word) for word in _SUPERLATIVE_WORDS if lowered.find(word) >= 0
        ]
        mentioned = [label for label in labels if label and label in sentence]
        numbers = _number_tokens(sentence)
        if not mentioned and not numbers:
            continue
        column = _column_for(sentence, numeric_columns, superlatives)
        values = [
            (label, number)
            for label, row in labels.items()
            if (number := _first_number(row.get(column))) is not None
        ]
        if len(values) < 2:
            continue
        if mentioned:
            # The sentence names rows: the row the data calls the extreme must
            # be one of them. This is the reliable signal - a swapped
            # "highest"/"lowest" always names the wrong row.
            for wants_high in (
                (True, False) if (asks_high and asks_low) else (asks_high,)
            ):
                truth_label, _truth_value = (
                    max(values, key=lambda item: item[1])
                    if wants_high
                    else min(values, key=lambda item: item[1])
                )
                if truth_label not in mentioned:
                    return True
            continue
        if numbers:
            # A value-only claim ("the highest figure is 1,485,730"): it stands
            # when any number in the sentence is an extreme of any numeric
            # column. A "highest X and lowest Y" sentence therefore only has to
            # quote two real extremes, whichever columns they come from.
            extremes: list[float] = []
            for candidate_column in numeric_columns:
                candidate_values = [
                    number
                    for row in rows
                    if (number := _first_number(row.get(candidate_column))) is not None
                ]
                if len(candidate_values) >= 2:
                    extremes.extend([max(candidate_values), min(candidate_values)])
            if any(_close(value, extremes) for value in numbers):
                continue
            return True
    return False


def _first_number(value: Any) -> float | None:
    if isinstance(value, bool):
        return None
    if isinstance(value, (int, float)):
        return float(value)
    if isinstance(value, str):
        numbers = _number_tokens(value)
        return numbers[0] if numbers else None
    return None


# The first words of the guardrail block's own refusal sentence. A weak model
# reaches for it as a default "safe" answer whenever it has nothing solid to
# say - a queued draft, a report with no rows, a projection with too few
# observations all produced "I cannot fulfill this request" for staff who were
# fully authorized. Detecting it lets the deterministic rendering of the
# command's own result take over.
_REFUSAL_OPENING = "i cannot fulfill this request"


def _looks_like_refusal(body: str) -> bool:
    text = str(body or "").strip().lower()
    return text.startswith(_REFUSAL_OPENING) or (
        "does not have authorization" in text and "cannot fulfill" in text
    )


_META_PREAMBLE_MARKERS = (
    "let me ",
    "let's ",
    "i need to",
    "i'll ",
    "i will ",
    "first,",
    "i'll help",
    "i will help",
    "to answer this",
    "i want to",
    "before i ",
    "i should ",
    "i must ",
)


def _looks_like_planning_preamble(body: str) -> bool:
    """Detect a model narrating its plan instead of answering the operator.

    A weak model emits "Let me start by understanding what's being asked... I
    need to check what data I have..." and stops there. That is meta-commentary,
    not an answer, and it must not reach the browser. A genuine answer almost
    always has structure (a heading, a table, or bolded figures); a bare plan
    does not. Keeping this check narrow avoids rejecting short valid replies.
    """
    text = str(body or "").strip()
    if not text:
        return False
    lowered = text.lower()
    if not any(marker in lowered for marker in _META_PREAMBLE_MARKERS):
        return False
    # A real answer carries formatting; a planning monologue does not.
    structured = any(mark in text for mark in ("#", "|", "**"))
    return not structured


# Tool-internals that must never appear in a staff answer. A weak model echoes
# the tool envelope back as narration ("I'm not seeing any rows from the
# data.query call in the envelope you just sent..."), which is meaningless - and
# misleading - to a teacher. These are dropped from the final answer body; the
# authoritative figures still come from the governed tool results.
_META_CHATTER_TOKENS = (
    "data.query",
    "data.polars",
    "reports.nlq",
    "the envelope",
    "tool result",
    "governed data isn't available",
    "governed data is not available",
    "permission-scoped dataset",
    "permission‑scoped dataset",
    "permission‑scoped data",
    # Process narration a weak model emits before an otherwise good answer
    "already executed in this turn",
    "i used the web search tool",
    "the search returned",
    "to provide a timely answer",
    "let me fetch",
    "let me check",
    "let me look",
    "i'll retrieve",
    "i’ll retrieve",
)


def _strip_meta_chatter(body: str) -> str:
    """Remove process narration from a final answer without touching its content.

    The model sometimes prefixes a real, web-grounded answer with paragraphs
    about which tool it checked and what the tool envelope contained. Those
    paragraphs are internal chatter, not information for the operator. Dropping
    them is safe because every figure the answer relies on is still checked
    against governed evidence by ``_guard_grounded_answer`` afterwards.
    """
    text = str(body or "")
    if not text.strip():
        return text
    kept = []
    paragraphs = [p.strip() for p in re.split(r"\n\s*\n", text) if p.strip()]
    for index, stripped in enumerate(paragraphs):
        # Models emit typographic apostrophes ("isn’t", "I’ll"). Normalise
        # quotes for matching only - the answer text itself is never rewritten.
        lowered = (
            stripped.lower()
            .replace("\u2018", "'")
            .replace("\u2019", "'")
            .replace("\u201c", '"')
            .replace("\u201d", '"')
        )
        if any(token in lowered for token in _META_CHATTER_TOKENS):
            continue
        if re.match(r"^step\s*\d+\b", lowered):
            continue
        if index == 0 and any(
            lowered.startswith(marker) for marker in ("i'll ", "i’ll ", "i will ")
        ):
            # A one-sentence opening intention ("I'll retrieve the latest…")
            # is narration, not an answer. A substantive first-person
            # recommendation carries a clause, a figure or a list and survives.
            if "," not in stripped and not any(ch.isdigit() for ch in stripped):
                continue
        kept.append(stripped)
    return "\n\n".join(kept)


# Opening process narration that must never be streamed to the browser. The
# final answer already passes through _strip_meta_chatter, but the SSE deltas
# are sent before finalization, so an operator could see "Let me fetch the
# news..." followed by the real answer. These markers are intentionally narrow:
# a substantive first-person recommendation carries a figure, a list or
# structure and is preserved.
_STREAM_PREAMBLE_MARKERS = (
    "let me ",
    "let's ",
    "lets ",
    "i'll ",
    "i’ll ",
    "i will ",
    "i see ",
    "i can see ",
    "based on the ",
    "i need to ",
    "i'm going to ",
    "i am going to ",
    "i'm checking ",
    "i am checking ",
)


def _is_stream_preamble(sentence: str) -> bool:
    """True when a complete streamed sentence is process narration only."""
    text = str(sentence or "").strip()
    if not text:
        return False
    low = (
        text.lower()
        .replace("\u2018", "'")
        .replace("\u2019", "'")
        .replace("\u201c", '"')
        .replace("\u201d", '"')
    )
    if not any(low.startswith(marker) for marker in _STREAM_PREAMBLE_MARKERS):
        return False
    # A real answer may legitimately begin with "I'll..." or "I found 3...".
    # Figures, structure and newlines mark content, not chatter.
    if any(ch.isdigit() for ch in text):
        return False
    if "|" in text or "**" in text or "\n" in text:
        return False
    return True


def _filter_stream_preamble(buffer: str) -> tuple[str, str]:
    """Return (safe text to emit, incomplete trailing sentence to keep)."""
    remaining = str(buffer or "")
    kept: list[str] = []
    while True:
        match = re.search(r"[.!?…\n]", remaining)
        if not match:
            break
        sentence = remaining[: match.end()]
        remaining = remaining[match.end() :]
        if not _is_stream_preamble(sentence):
            kept.append(sentence)
    return "".join(kept), remaining


def _could_become_refusal(body: str) -> bool:
    """True while the streamed prose is still a possible refusal prefix.

    The refusal sentence is short and the model emits it as the very first
    text. Holding these bytes back until we know whether the reply is a
    refusal stops the canned denial reaching the browser before the
    orchestrator can replace it with governed data.
    """
    text = str(body or "").strip().lower()
    if not text:
        return True
    if _looks_like_refusal(text):
        return True
    # Is everything streamed so far a prefix of the refusal opening? If the
    # model diverges ("I can help...") the next byte breaks the prefix and the
    # held text is released as normal prose.
    return _REFUSAL_OPENING.startswith(text)


def _command_outcome_answer(tool: str, result: Any) -> dict[str, Any] | None:
    """Deterministic answer from a slash command's own tool result.

    A command is a promise: "/draft notice closing arrangements" has to end with
    a draft, whatever the model decides to say. When the model refuses or says
    nothing, the tool's own outcome is rendered instead - it is the authoritative
    record of what the platform actually did.
    """
    if not isinstance(result, dict):
        return None
    status = str(result.get("status") or "")
    message = str(result.get("message") or "").strip()
    draft_id = result.get("draft_id")
    if status == "queued" or draft_id:
        body = (
            "A draft has been prepared and queued for your review. "
            "Open the drafts inbox to read the full text, adjust anything you "
            "want changed, and approve it. Nothing is sent until you approve."
        )
        if draft_id:
            body = (
                f"Draft #{int(draft_id)} is queued for your review. "
                + body.split(
                    "A draft has been prepared and queued for your review. ", 1
                )[-1]
            )
        return {
            "title": "Draft queued for review",
            "body": body,
            "next_steps": [
                "Open the drafts inbox to review and approve it.",
                "Nothing is applied or sent until you approve.",
            ],
            "suggested_questions": [],
            "escalation_required": False,
        }
    if status in {"unavailable", "not_authorized", "unknown_tool"}:
        return {
            "title": "That could not be completed",
            "body": message
            or "The governed tool could not complete this request just now.",
            "next_steps": [
                "Try again, or open the related workspace and check it directly."
            ],
            "suggested_questions": [],
            "escalation_required": False,
        }
    return None


def _expanded_agent_tools(agent: dict[str, Any], tools: Any) -> list[str]:
    """An agent's tool list with any ``mcp.*`` placeholder expanded.

    External MCP tool ids are deployment configuration, not code: the operator
    names the servers and the tools in ``AI_MCP_SERVERS``. An agent declares
    ``mcp.*`` to mean "whatever this deployment has enabled", and the envelope
    and the proposal check both see the concrete ids.
    """
    declared = list(agent.get("tools") or ())
    expanded: list[str] = []
    for tool_id in declared:
        if str(tool_id) == "mcp.*":
            mcp = getattr(tools, "mcp", None)
            known = mcp.known_tools() if mcp is not None else {}
            expanded.extend(sorted(known))
        else:
            expanded.append(str(tool_id))
    # De-duplicate while keeping the declared order.
    seen: set[str] = set()
    ordered: list[str] = []
    for tool_id in expanded:
        if tool_id not in seen:
            seen.add(tool_id)
            ordered.append(tool_id)
    # Every agent knows its own environment: the page catalogue and real
    # usage activity are universal, so a finance question can still be
    # answered with "which page does the bursar actually use" context.
    for universal in ("assistant.page_catalog", "assistant.page_activity"):
        if universal not in seen:
            seen.add(universal)
            ordered.append(universal)
    return ordered


def _rows_bearing_result(result: Any) -> dict[str, Any] | None:
    """The dict inside a governed result that carries the report rows.

    PHP's tool envelope wraps the report in "answer" and keeps the status beside
    it. Both the rows themselves and the deterministic rendering live on that
    inner dict, so anything that wants to render or verify the rows - the
    grounded-claim guard, the rows fallback - has to look there rather than at
    the top level, where "rows" simply does not exist.
    """
    if not isinstance(result, dict):
        return None
    for candidate in (result, result.get("answer")):
        if isinstance(candidate, dict) and isinstance(candidate.get("rows"), list):
            if candidate.get("rows"):
                return candidate
    # Single-figure reports (e.g. admission conversion) return a summary and no
    # rows. Treat the summary as one row so the answer is still grounded: a
    # live turn reported "1,012 applicants / 454 admitted" when the report
    # returned 7 / 6, and nothing checked it because there were no rows.
    for candidate in (result, result.get("answer")):
        if isinstance(candidate, dict):
            summary = candidate.get("summary")
            if (
                isinstance(summary, dict)
                and summary
                and not any(isinstance(v, (dict, list)) for v in summary.values())
            ):
                return {**candidate, "rows": [summary]}
    return None


_DATE_LIKE = re.compile(
    r"\b\d{4}[-/]\d{1,2}[-/]\d{1,2}\b|\b\d{4}/\d{4}\b|\b\d{1,2}:\d{2}\b"
)


def _unsupported_numbers(
    rows: list[dict[str, Any]], body: str, allowed_text: str = ""
) -> list[float]:
    """Figures stated in the prose that the governed rows do not contain.

    Allowed: any value in the rows (numbers inside labels such as "Grade 7"
    included), each money/total column summed over the rows, and the row
    count. Dates, year ranges and small ordinals (< 10, e.g. "step 2") are
    ignored. Head-count columns are deliberately NOT summed, so "555 learners"
    built by adding per-class counts is rejected.

    ``allowed_text`` carries figures the operator quoted in the question or that
    an authorized web/news tool returned. On a composite question (e.g. "what is
    the probability we reach 90% collection given current MoE guidance?") those
    numbers are legitimate evidence, not fabricated school data; without this,
    the guard flattened a good cited answer into a raw fee table.
    """
    # The row count is evidence only when the rows carry no head-count column.
    # A per-class report has 12 rows (Playgroup..Grade 9); allowing "12" let a
    # live turn report "Graduating students: 12" when the real figure was 34.
    has_head_counts = any(
        _is_head_count_column(str(key)) for row in rows for key in row
    )
    allowed: list[float] = [] if has_head_counts else [float(len(rows))]
    sums: dict[str, float] = {}
    for row in rows:
        for key, value in row.items():
            if isinstance(value, bool) or value is None:
                continue
            if isinstance(value, (int, float)):
                allowed.append(float(value))
                number = float(value)
            else:
                allowed.extend(_number_tokens(_DATE_LIKE.sub(" ", str(value))))
                try:
                    number = float(str(value).replace(",", "").strip())
                except ValueError:
                    number = None
            lowered = str(key).lower()
            if (
                number is not None
                and not _is_head_count_column(lowered)
                and (
                    lowered.startswith("total")
                    or "amount" in lowered
                    or "arrears" in lowered
                    or "balance" in lowered
                )
            ):
                sums[lowered] = sums.get(lowered, 0.0) + number
    allowed.extend(sums.values())
    # Prose drops a leading minus sign ("total balance -28,785" is read as the
    # token 28,785), while a numeric row value keeps it. Mirror each allowed
    # figure's magnitude so a legitimate negative balance is not called fake.
    allowed.extend(abs(number) for number in list(allowed))
    # Numbers the operator quoted in the question, or that an authorized
    # web/news tool returned, are legitimate evidence on a composite question.
    # They are not school figures the model invented, so do not reject them.
    if allowed_text:
        allowed.extend(_number_tokens(_DATE_LIKE.sub(" ", str(allowed_text))))
    text = _DATE_LIKE.sub(" ", str(body or ""))
    unsupported: list[float] = []
    for match in re.finditer(r"(?<![\w.])(\d[\d,]*(?:\.\d+)?)(\s*%)?", text):
        try:
            value = float(match.group(1).replace(",", ""))
        except ValueError:
            continue
        if value < 10 and not match.group(2):
            continue
        if not _close(value, allowed, tolerance=0.006):
            unsupported.append(value)
    return unsupported


def _proposed_tool_ids(proposal: dict[str, Any]) -> set[str]:
    """Tool ids a proposal asks for, named or batched."""
    ids: set[str] = set()
    raw = proposal.get("tools")
    if isinstance(raw, list):
        for item in raw:
            if isinstance(item, dict) and str(item.get("tool") or "").strip():
                ids.add(str(item.get("tool")).strip())
    single = str(proposal.get("tool") or "").strip()
    if single:
        ids.add(single)
    return ids


def _flatten_row(row: dict[str, Any]) -> str:
    """One bounded, readable line per governed report row."""
    parts: list[str] = []
    for key, value in list(row.items())[:12]:
        if isinstance(value, bool):
            text = "yes" if value else "no"
        elif isinstance(value, (int, float)):
            text = str(value)
        elif isinstance(value, str):
            text = guard.neutralize_untrusted(value.strip(), 80)
        elif value is None:
            continue
        else:
            continue
        parts.append(f"{str(key)[:40]}={text}")
    return "; ".join(parts)[:400]


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
    def _select_agent(
        self, question: str, route: str, module: str, skip_triage: bool = False
    ) -> tuple[dict[str, Any], str]:
        """Pick the agent for a question. One implementation for both surfaces.

        Order: governed-report intent, then a lexical hint that agrees with the
        workspace, then the lexical hint alone, then the workspace agent, then
        provider triage, then the default. Buffered and streamed answers used to
        carry two copies of this and could route the same question differently.
        """
        agent = None
        routing = "default"
        # Advisory coworker prompts ("what should we post", "give me
        # suggestions for today") are not report queries. They used to fall
        # through to the default reports agent, which ran an empty report and
        # told the operator it had no reliable answer. Route them first to the
        # agent that owns the advisory tool.
        advisory = agents.advisory_agent_for(question)
        if advisory is not None:
            return advisory, "advisory"
        if self._is_report_query(question):
            agent = agents.AGENTS.get("reports.agent")
            routing = "report_query"
        routed_agent = agents.for_route(route)
        hinted = agents.route_hint_for(question)
        if (
            agent is None
            and hinted is not None
            and routed_agent is not None
            and hinted[0] == routed_agent["id"]
        ):
            agent = routed_agent
            routing = "route_match"
        if agent is None and hinted is not None:
            agent = agents.AGENTS.get(hinted[0])
            routing = "lexical"
        if agent is None and routed_agent is not None:
            agent = routed_agent
            routing = "route_match"
        if agent is None and not skip_triage:
            agent = self._triage(question, route, module)
            if agent is not None:
                routing = "triage"
        if agent is None:
            agent = agents.default_agent()
            routing = "default"
        return agent, routing

    def _apply_identity(
        self,
        agent: dict[str, Any],
        routing: str,
        context: dict,
        assistant_domain: str,
    ) -> tuple[dict[str, Any], str]:
        """Apply the admin / audience persona overrides. One implementation.

        The system_admin agent and its file/log tools are reserved for verified
        administrators; an explicit parent/public persona is honoured only for
        its own audience. Everything else keeps normal staff routing.
        """
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

        domain = str(assistant_domain or "").lower()
        if domain and agent["id"] != "system_admin.agent":
            if domain == "system_admin":
                if self._is_system_admin(context):
                    agent = (
                        agents.resolve("system_admin.agent") or agents.default_agent()
                    )
                    routing = "domain:system_admin"
                else:
                    agent = agents.default_agent()
                    routing = "not_authorized"
            elif domain in ("parent", "public"):
                resolved = agents.resolve(f"{domain}.agent")
                if resolved is not None:
                    agent = resolved
                    routing = f"domain:{domain}"
                else:
                    agent = agents.default_agent()
                    routing = "default"
        return agent, routing

    def _apply_command(
        self,
        agent: dict[str, Any],
        routing: str,
        context: dict,
        invocation: "commands.Invocation",
    ) -> tuple[dict[str, Any], str, dict]:
        """Fold a slash action command into the turn's agent and context."""
        if invocation.agent:
            resolved = agents.resolve(invocation.agent)
            if resolved is not None:
                agent = resolved
                routing = f"command:{invocation.name}"
        context = {
            **context,
            "forced_tool": invocation.tool,
            "forced_tool_input": invocation.tool_input,
            "command": invocation.name,
        }
        # The command's own declared follow-up (the figure for /chart, the
        # projection for /forecast), read from the same registry the parser
        # used so the two can never disagree about what a command does.
        definition = commands.COMMANDS.get(invocation.name)
        if definition is not None and definition.follow_up:
            context["forced_follow_up"] = definition.follow_up
            if definition.follow_up_input:
                context["forced_follow_up_input"] = dict(definition.follow_up_input)
        return agent, routing, context

    def _run_forced_tool(
        self,
        context: dict,
        agent: dict[str, Any],
        user_id: int,
        permissions: list[str],
        request_id: str,
        already_run: set[str] | None = None,
    ) -> tuple[str, dict[str, Any]] | None:
        """Execute the tool a slash command selected, once, with normal gates.

        The command only names a tool the chosen agent already owns; PHP still
        re-authorises it. Returns None when there is nothing to run.
        """
        tool = str(context.get("forced_tool") or "")
        payload = context.get("forced_tool_input")
        if not tool or tool not in agent["tools"] or not isinstance(payload, dict):
            return None
        required = {
            "web.search": "query",
            "web.crawl": "url",
            "reports.nlq": "question",
            "reports.aggregate": "question",
            "charts.generate": "question",
            "drafts.create": "template",
        }.get(tool)
        if required and not str(payload.get(required) or "").strip():
            return None
        if already_run and tool in already_run:
            return None
        result = self.tools.execute(
            user_id,
            permissions,
            tool,
            payload,
            request_id,
            roles=context.get("roles"),
            role_ids=context.get("role_ids"),
        )
        return tool, result

    def _run_follow_up(
        self,
        context: dict,
        agent: dict[str, Any],
        user_id: int,
        permissions: list[str],
        request_id: str,
        tools_used: list[str],
        already_run: set[str] | None = None,
        source_result: dict[str, Any] | None = None,
    ) -> tuple[str, dict[str, Any]] | None:
        """Run the governed follow-up a command declares, once.

        "/chart" asks for a picture. The chart source report gives rows and a
        chart specification; the figure itself is drawn by analytics.chart.
        Drawing it here - rather than trusting the model to ask for it - makes
        the command behave the same on the buffered and the streamed surface,
        and makes the render window actually receive an image.

        The follow-up only runs when the agent owns it and the rows are in
        hand, so a command can never become a second, differently-gated path
        to data.
        """
        follow_up = str(context.get("forced_follow_up") or "")
        if not follow_up or follow_up not in agent["tools"]:
            return None
        if already_run and follow_up in set(already_run) | set(tools_used):
            return None
        # The rows the tool bridge holds for this operator are the evidence the
        # figure is drawn from, and analytics.chart picks its own x/y columns,
        # so nothing has to be copied through the prompt here. The only thing
        # worth carrying across is the chart type the governed report already
        # chose, so the figure matches what the reports screen would draw.
        # Input the command itself declared for its follow-up: "/pdf" tells the
        # producer which format to render, not the report.
        payload: dict[str, Any] = dict(context.get("forced_follow_up_input") or {})
        if follow_up == DOCUMENT_TOOL and isinstance(source_result, dict):
            # The document is named after the report it renders, so it does not
            # arrive called "School report".
            answer = source_result.get("answer")
            if isinstance(answer, dict):
                report_title = str(answer.get("report_title") or "")
                if report_title:
                    payload.setdefault("title", report_title)
                summary = answer.get("summary")
                if isinstance(summary, str) and summary.strip():
                    payload.setdefault("summary", summary[:800])
        if isinstance(source_result, dict):
            spec = _bounded_chart_result(
                str(context.get("forced_tool") or ""), source_result
            )
            if spec is not None:
                chart = spec.get("chart")
                if (
                    isinstance(chart, dict)
                    and chart.get("type") in ANALYTICS_CHART_TYPES
                ):
                    payload["chart_type"] = str(chart["type"])
                title = str(spec.get("title") or "")
                if title:
                    payload["title"] = title
        result = self.tools.execute(
            user_id,
            permissions,
            follow_up,
            payload,
            request_id,
            roles=context.get("roles"),
            role_ids=context.get("role_ids"),
        )
        # A follow-up that could not run (no rows released yet, or a rendering
        # dependency this deployment lacks) still has a message worth relaying:
        # the staff member must be told why there is no file, not shown silence.
        return follow_up, result

    def _command_help_answer(self) -> dict[str, Any]:
        """Deterministic /help answer. No provider call, no data access."""
        body = commands.help_text(guard.slash_catalogue())
        return {
            "status": "answered",
            "answer": {
                "title": "Commands",
                "body": body,
                "next_steps": ["Type a command, then the question it should act on."],
                "suggested_questions": [],
                "escalation_required": False,
            },
            "tools_used": [],
            "charts": [],
            "artifacts": [],
            "scope_checks": [],
            "provider_calls": 0,
        }

    def assist(self, context: dict, question: str) -> dict[str, Any]:
        from .security import bound_question

        question_raw = question
        user_id = int(context["user_id"])
        permissions = list(context["permissions"])
        route = context.get("route", "")
        module = context.get("module", "dashboard")
        request_id = context.get("request_id", "")
        session_id = str(context.get("session_id") or "")
        assistant_domain = str(context.get("assistant_domain") or "staff")

        # Action commands (/web, /crawl, /report ...) preselect a governed tool.
        # They are honoured for staff audiences only: parent/public sessions
        # keep their narrower tool set and normal routing.
        invocation = (
            commands.parse(question_raw) if assistant_domain in ("", "staff") else None
        )
        if invocation is not None and not invocation.help_requested:
            clean, style_instr = guard.extract_slash(invocation.question)
            question_clean = clean or invocation.question
            instruction = style_instr or invocation.instruction
        else:
            question_clean, slash_instr = guard.extract_slash(question_raw)
            instruction = slash_instr or ""
        instruction = instruction or str(context.get("instruction") or "")
        context = {**context, "instruction": instruction}
        question = bound_question(question_clean or question_raw)

        # Prompt injection must be refused BEFORE agent triage: triage itself
        # calls the provider, so checking later would still leak the attack to a
        # model. The refusal is deterministic and costs zero provider calls.
        started_guard = time.monotonic()
        if guard.is_prompt_injection(question):
            self._observe_outcome(user_id, route, module, None, [], "refused")
            self.journal.write(
                "ai_generation",
                {
                    "type": "agent_prompt_injection_refusal",
                    "operator_id": user_id,
                    "route": route,
                    "module": module,
                    "question_hash": hash_text(question),
                    "duration_ms": int((time.monotonic() - started_guard) * 1000),
                    "provider_calls": 0,
                    "request_id": request_id,
                },
            )
            return guard.prompt_injection_response(None)

        agent, routing = self._select_agent(
            question,
            route,
            module,
            skip_triage=bool(invocation and invocation.help_requested),
        )

        # Deterministic pre-flight, before any provider call:
        #   - a prompt-injection attempt (ignore rules, reveal prompt, escalate
        #     privileges) is refused locally with zero provider calls;
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

        agent, routing = self._apply_identity(agent, routing, context, assistant_domain)

        if invocation is not None and invocation.help_requested:
            result = self._command_help_answer()
            self._observe_outcome(user_id, route, module, agent, [], "instant")
            return {
                **result,
                "routing": routing,
                "agent": {
                    "id": agent["id"],
                    "name": agent["name"],
                    "domain": agent["domain"],
                },
            }

        if invocation is not None and not invocation.help_requested:
            agent, routing, context = self._apply_command(
                agent, routing, context, invocation
            )

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

        # Same page-guidance guarantee as the streamed surface: a "what is
        # this page" prompt that ended in the neutral placeholder is answered
        # deterministically from page_context instead.
        if agents.is_page_help_question(question):
            answer = result.get("answer")
            body = str((answer or {}).get("body") or "")
            failed = (
                result.get("status") != "answered"
                or not body.strip()
                or "reliable answer" in body
            )
            if failed:
                result = {
                    **result,
                    "status": "answered",
                    "answer": self._page_help_answer(context, agent, question),
                }

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

        # Proactive coworker sections. All three are deterministic and bounded
        # and run through the same governed tool edge, so this scheduled digest
        # keeps users updated WITHOUT a model call and WITHOUT broadening scope:
        #   - today: what the operator is already entitled to see (schedule,
        #     attention, notifications) via the PHP re-authorized scan;
        #   - education_news: current MoE/CBC/CBE/global headlines from the
        #     curated public-web digest (each with a source to verify);
        #   - public_content: post ideas/headlines/drafts, which the tool itself
        #     refuses for anyone without a communications/website grant.
        today = self.tools.execute(
            user_id,
            permissions,
            "assistant.workspace_scan",
            {"route": "dashboard"},
            request_id,
        )
        education = self.tools.execute(
            user_id,
            permissions,
            "education.news",
            {"topic": "all", "limit": 5},
            request_id,
        )
        content = self.tools.execute(
            user_id,
            permissions,
            "public_content.advisor",
            {"purpose": "general", "channel": "website", "limit": 3},
            request_id,
        )

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
            "today": {
                "status": str(today.get("status") or "unavailable"),
                "date": str(today.get("date") or ""),
                "sections": self._digest_scan_sections(today),
                "unread_notification_count": int(
                    today.get("unread_notification_count") or 0
                ),
            },
            "education_news": {
                "status": str(education.get("status") or "unavailable"),
                "headlines": [str(h) for h in (education.get("headlines") or [])[:5]],
                "note": str(education.get("note") or ""),
            },
        }
        # Omit public-content guidance entirely for operators without the
        # grant; never leak an authorization decision as a visible section.
        if str(content.get("status") or "") not in ("not_authorized", "unavailable"):
            digest["public_content"] = {
                "status": str(content.get("status") or "advisory"),
                "post_ideas": [str(i) for i in (content.get("post_ideas") or [])[:3]],
                "headlines": [str(h) for h in (content.get("headlines") or [])[:3]],
                "event_ideas": [str(i) for i in (content.get("event_ideas") or [])[:3]],
                "note": str(content.get("note") or ""),
            }
        self.journal.write(
            "ai_generation",
            {
                "type": "agent_digest_ready",
                "operator_id": user_id,
                "cadence": cadence,
                "alert_count": digest["insight_brief"]["alert_count"],
                "hints_known": bool(hints.get("known")),
                "today_items": sum(
                    len(s or []) for s in digest["today"]["sections"].values()
                ),
                "education_headlines": len(digest["education_news"]["headlines"]),
                "public_content_included": "public_content" in digest,
            },
        )
        return digest

    @staticmethod
    def _digest_scan_sections(scan: dict[str, Any]) -> dict[str, list[str]]:
        """Flatten the deterministic scan to short, bounded lines per section."""
        sections: dict[str, list[str]] = {}
        raw = scan.get("sections") or {}
        if isinstance(raw, dict):
            for name, section in raw.items():
                items = (section or {}).get("items") or []
                sections[str(name)] = [str(line) for line in items[:5]]
        return sections

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
            action = str(item.get("suggested_action") or "").strip()
            # A "finding" whose action says nothing is pending is not a finding;
            # it only crowds the panel (e.g. "Pending reviews: none at this time").
            if _NON_ACTIONABLE_RE.search(action):
                continue
            cause = str(item.get("root_cause") or "").strip()
            if cause.lower().startswith("not determinable"):
                cause = ""
            findings.append(
                {
                    "title": str(item.get("title") or "Finding")[:160],
                    "severity": severity,
                    "root_cause": cause[:240],
                    "suggested_action": (action or "Review in the related workspace.")[
                        :240
                    ],
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
    ) -> tuple[list[tuple[str, dict[str, Any]]], list[str]]:
        """Validate one tool proposal, or a batch of up to MAX_TOOLS_PER_STEP.

        Accepts the legacy single {"tool":..,"tool_input":..} shape, the compact
        {"action":"<tool id>",...} shape, and the batched
        {"tools":[{tool,tool_input}..]} shape. Every entry is checked against
        the agent allowlist before anything executes. Returns the accepted
        requests and the list of rejected tool ids.
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
            if not tool_id:
                # Some providers emit the compact form
                # {"action":"<tool id>","tool_input":{...}} instead of the
                # documented {"action":"tool","tool":"<tool id>",...}. Accept
                # it when the action names a real governed tool, otherwise a
                # valid request collapses into a placeholder answer.
                action = str(response.get("action") or "").strip()
                if action in allowed:
                    tool_id = action
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
        # Return the list of rejected ids, NOT a formatted string: callers join
        # it and slice it as a list. A string here silently produced notes like
        # "these tools are not available: e, r, r, o, r, :" from "error:".
        return valid, rejected

    def _run_tools_parallel(
        self,
        requests: list[tuple[str, dict[str, Any]]],
        user_id: int,
        permissions: list[str],
        request_id: str,
        roles: list | None = None,
        role_ids: list[int] | None = None,
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
                        roles=roles,
                        role_ids=role_ids,
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
                        roles=roles,
                        role_ids=role_ids,
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
        user_id = int(context["user_id"])
        permissions = list(context["permissions"])
        route = context.get("route", "")
        module = context.get("module", "dashboard")
        request_id = context.get("request_id", "")
        session_id = str(context.get("session_id") or "")
        assistant_domain = str(context.get("assistant_domain") or "staff")

        invocation = (
            commands.parse(question_raw) if assistant_domain in ("", "staff") else None
        )
        if invocation is not None and not invocation.help_requested:
            clean, style_instr = guard.extract_slash(invocation.question)
            question_clean = clean or invocation.question
            slash_instruction = style_instr or invocation.instruction
        else:
            question_clean, slash_instruction = guard.extract_slash(question_raw)
        question = bound_question(question_clean or question_raw)
        # A "what is this page / how do I use it" prompt carries no governed
        # data question. It is answered as guidance from page_context and the
        # agent persona, so it must not be routed through the row-evidence
        # contract that produced the "couldn't verify" placeholder.
        page_help = agents.is_page_help_question(question)

        # Refuse before triage: triage calls the provider, so checking later
        # would still leak the attack. Same pre-flight as the buffered path.
        if guard.is_prompt_injection(question):
            # Journal it like the buffered path does; the stream path was silent.
            self.journal.write(
                "ai_generation",
                {
                    "type": "agent_prompt_injection_refusal",
                    "operator_id": context.get("user_id"),
                    "route": route,
                    "module": module,
                    "question_hash": hash_text(question),
                    "provider_calls": 0,
                    "stream": True,
                },
            )
            result = guard.prompt_injection_response(None)
            yield {"kind": "final", **result}
            return

        agent, routing = self._select_agent(
            question,
            route,
            module,
            skip_triage=bool(invocation and invocation.help_requested),
        )
        # Same deterministic pre-flight as the buffered path.
        if guard.is_confidential(question):
            result = guard.confidential_response(agent or agents.default_agent())
            yield {"kind": "final", **result}
            return
        if guard.is_smalltalk(question):
            result = guard.greeting_response(question, agent or agents.default_agent())
            yield {"kind": "final", **result}
            return

        if agent is None:
            agent = self._triage(question, route, module)
            routing = "triage" if agent else "default"
        if agent is None:
            agent = agents.default_agent()
            routing = "default"

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

        agent, routing = self._apply_identity(agent, routing, context, assistant_domain)

        if invocation is not None and invocation.help_requested:
            result = self._command_help_answer()
            yield {
                "kind": "final",
                **result,
                "routing": routing,
                "agent": {
                    "id": agent["id"],
                    "name": agent["name"],
                    "domain": agent["domain"],
                },
            }
            return

        if invocation is not None and not invocation.help_requested:
            agent, routing, context = self._apply_command(
                agent, routing, context, invocation
            )

        started = time.monotonic()
        allowed_tools = _expanded_agent_tools(agent, self.tools)
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
            context=context,
        )
        tool_results = [
            self._compact_result(tool_id, result) for tool_id, result in prefetched
        ]
        tools_used = [
            tool_id for tool_id, result in prefetched if self._tool_succeeded(result)
        ]
        # Raw advisory results from both the prefetch path and any tool the
        # model calls this turn. If the model later refuses, these let us render
        # the content it actually retrieved instead of a neutral placeholder.
        advisory_results: dict[str, dict[str, Any]] = {
            tool_id: result
            for tool_id, result in prefetched
            if tool_id in self._ADVISORY_CONTENT_TOOLS
        }
        # The last governed result that carried rows, so a streamed turn that
        # spends its budget on tools can still answer with the real figures.
        last_rows_result: dict[str, Any] | None = None
        for _tool_id, _result in prefetched:
            rows_bearing = _rows_bearing_result(_result)
            if rows_bearing is not None:
                last_rows_result = rows_bearing
        charts = [
            chart
            for tool_id, result in prefetched
            if (chart := _bounded_chart_result(tool_id, result)) is not None
        ][:3]
        artifacts = [
            artifact
            for tool_id, result in prefetched
            if (
                artifact := _analytics_artifact(tool_id, result)
                or _document_artifact(tool_id, result)
            )
            is not None
        ][:3]

        # A slash action command runs its governed tool before the model speaks,
        # using the same helper as the buffered path so the two cannot drift.
        forced_source_result: dict[str, Any] | None = None
        command_turn = False
        forced = self._run_forced_tool(
            context,
            agent,
            user_id,
            permissions,
            request_id,
            already_run=set(tools_used),
        )
        if forced is not None:
            forced_tool, forced_result = forced
            command_turn = True
            forced_source_result = forced_result
            tools_used.append(forced_tool)
            tool_results.append(self._compact_result(forced_tool, forced_result))
            chart = _bounded_chart_result(forced_tool, forced_result)
            if chart is not None and len(charts) < 3:
                charts.append(chart)
            artifact = _analytics_artifact(
                forced_tool, forced_result
            ) or _document_artifact(forced_tool, forced_result)
            if artifact is not None and len(artifacts) < 3:
                artifacts.append(artifact)
        elif context.get("forced_tool"):
            # Same rule as the buffered surface: the command's tool may already
            # have run as a prefetch, and its result is still what the
            # follow-up draws from.
            command_turn = True
            forced_source_result = self._command_source_result(context, prefetched)
        if context.get("forced_follow_up"):
            follow_up = self._run_follow_up(
                context,
                agent,
                user_id,
                permissions,
                request_id,
                tools_used,
                already_run=set(tools_used),
                source_result=forced_source_result,
            )
            if follow_up is not None:
                follow_up_tool, follow_up_result = follow_up
                if self._tool_succeeded(follow_up_result):
                    tools_used.append(follow_up_tool)
                tool_results.append(
                    self._compact_result(follow_up_tool, follow_up_result)
                )
                follow_up_artifact = _analytics_artifact(
                    follow_up_tool, follow_up_result
                ) or _document_artifact(follow_up_tool, follow_up_result)
                if follow_up_artifact is not None and len(artifacts) < 3:
                    artifacts.append(follow_up_artifact)

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
            tool_id == "reports.nlq"
            and isinstance(result, dict)
            and result.get("status") in {"ok", "success", "answered"}
            and isinstance(result.get("answer", result), dict)
            and bool((result.get("answer", result)).get("report_code"))
            for tool_id, result in prefetched
        )
        deterministic_answer = ""
        deterministic_followups: list[str] = []
        deterministic_citations: list[dict[str, Any]] = []
        insufficient_data = False
        if has_nlq_prefetch:
            nlq_result = next(
                (result for tool_id, result in prefetched if tool_id == "reports.nlq"),
                {},
            )
            answer_data = (
                nlq_result.get("answer")
                if isinstance(nlq_result.get("answer"), dict)
                else nlq_result
            )
            report_code = str(answer_data.get("report_code") or "UNKNOWN")
            report_title = str(answer_data.get("report_title") or "")
            row_count = int(answer_data.get("row_count") or 0)
            columns = (
                answer_data.get("columns")
                if isinstance(answer_data.get("columns"), list)
                else []
            )
            rows = (
                answer_data.get("rows")
                if isinstance(answer_data.get("rows"), list)
                else []
            )
            # The deterministic rendering is the FALLBACK: it is always correct
            # because it reads the authorized columns, but the model writes the
            # answer the staff member sees so both surfaces give the same
            # explanation, insight and next steps.
            deterministic_answer = self._generate_nlq_answer(question, nlq_result) or ""
            deterministic_followups = self._generate_followup_questions(
                report_code, rows, columns, question
            )
            if report_code and report_code != "UNKNOWN":
                deterministic_citations = [
                    {
                        "title": report_title or "School report",
                        "description": "Based on authorized school records.",
                        "row_count": row_count,
                        "columns": columns,
                    }
                ]
            insufficient_data = row_count == 0
            grounding_note = (
                "The authorized report rows below are your evidence. Quote the "
                "figures exactly as given, explain the pattern they show, and "
                "add practical next steps for the staff member. Never invent a "
                "value that is not in the rows, and never claim a cause the "
                "rows do not support. Today's date in Africa/Nairobi is "
                f"{datetime.now(ZoneInfo('Africa/Nairobi')).date().isoformat()}."
            )
        elif any(
            tool_id == "data.query" and _rows_bearing_result(result) is not None
            for tool_id, result in prefetched
        ):
            # data.query releases rows straight from the read-only school data
            # after PHP re-checks the operator's permission. These are first-class
            # evidence, not an "unrelated prefetch": telling the model otherwise
            # made it ignore the correct figures and fall back to a denial.
            grounding_note = (
                "The data.query rows below are authorized, permission-scoped "
                "school records released for this operator. Answer from them: "
                "quote the exact figures, compute the trend or comparison the "
                "question asks for, and give practical next steps. For a "
                "prediction or probability, state it as a bounded estimate from "
                "these figures, never a certainty. Public-web results below are "
                "current context - cite their source URLs. Never invent a value "
                "that is not in the rows. Today's date in Africa/Nairobi is "
                f"{datetime.now(ZoneInfo('Africa/Nairobi')).date().isoformat()}."
            )
        elif page_help:
            grounding_note = (
                "This is a page-guidance question, not a school-data request. "
                "Answer helpfully from the page_context below (route, open "
                "records, selections, filters) and your role knowledge: say "
                "what this screen is for, what the operator can do here, and "
                "how the visible filters/records work. Do NOT request data "
                "tools and do NOT state school figures you cannot verify - "
                "if a figure matters, tell the operator which report or "
                "filter shows it. Today's date in Africa/Nairobi is "
                f"{datetime.now(ZoneInfo('Africa/Nairobi')).date().isoformat()}."
            )
        else:
            grounding_note = (
                "Use only relevant, authorized tool_results below as evidence. "
                "A briefing or workflow catalogue is not evidence for a "
                "school-records question. Public-web results are current context: "
                "cite their source URLs and verify against the official source "
                "before acting. If a current school figure is needed, request "
                "data.query (a named dataset) or reports.nlq with the user's "
                "question before answering. Today's date in Africa/Nairobi is "
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
            "page_context": self._page_context_lines(context),
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

        # A small, bounded number of mill rounds. The first character decides
        # everything: prose streams straight to the browser, while a tool
        # request is buffered, executed, and answered in a later round. That
        # keeps raw tool frames out of the chat bubble and still lets a
        # genuinely data-hungry question get its facts.
        #
        # The rounds are not "one request only": a question that asks for a
        # chart needs the report, then the chart source, then the figure. The
        # first version allowed a tool request on the FIRST round only, so a
        # model that spent round one consulting a specialist had its second
        # round's tool request silently dropped and the staff member received
        # a placeholder instead of the figure they asked for.
        stream_tool_steps = 0
        stream_delegations = 0
        # Tool turns the model already spent are replayed in order, so a second
        # round reads as a conversation ("you asked for X, X already ran") and
        # not as the same question asked three times.
        carried: list[dict[str, str]] = []
        for attempt in range(MAX_STREAM_ROUNDS):
            messages = [
                {"role": "system", "content": agents.stream_prompt(agent, context)},
                *history_messages,
                {"role": "user", "content": json.dumps(envelope, ensure_ascii=False)},
                *carried,
            ]
            # An oversized envelope must be trimmed rather than lose the answer.
            self._assert_prompt_fits(messages)

            body = ""
            buffered = ""
            emitted = 0
            prose_pending = ""
            prose_tail = ""
            refusal_in_round = False
            final_text_so_far = ""
            is_tool_frame: bool | None = None
            frame_start = -1
            action: str | None = None
            answer_body_cursor: int | None = None
            answer_body_closed = False
            provider_error = None
            try:
                for delta in self.provider.stream(messages, {"task": "chat"}):
                    buffered += delta
                    if is_tool_frame is not True and frame_start < 0:
                        # A model often writes a one-line preamble before its JSON
                        # action. Recognising a frame only when the first byte is
                        # '{' mistook that tool call for prose, so the tool never
                        # ran and the operator got a placeholder instead of the
                        # answer. Anchor on the "action" key and take the brace
                        # that opens its object, wherever it appears.
                        match = re.search(r'"action"\s*:\s*"', buffered)
                        if match:
                            frame_start = buffered.rfind("{", 0, match.start())
                            if frame_start >= 0:
                                prose = output_guard.scrub(
                                    buffered[emitted:frame_start]
                                )
                                if prose:
                                    safe, prose_tail = _filter_stream_preamble(
                                        prose_tail + prose
                                    )
                                    if safe:
                                        yield {"kind": "delta", "text": safe}
                                        rendered_any = True
                                emitted = frame_start
                                is_tool_frame = True
                    if is_tool_frame is None:
                        stripped = buffered.lstrip()
                        # Still possibly the start of a JSON frame: wait for the
                        # "action" key before streaming those bytes as prose.
                        if stripped and not stripped.startswith(("{", "`", "[")):
                            is_tool_frame = False
                    if is_tool_frame is False:
                        # Prose: pass bytes straight through as they land,
                        # through the same confidentiality scrub the buffered
                        # answer gets. Streamed text reaches the browser through
                        # the PHP SSE relay untouched, so without this the
                        # streamed surface could name an internal report code the
                        # buffered surface redacts.
                        #
                        # Hold back the opening bytes until we know the reply is
                        # not the guardrail's canned authorization denial. A weak
                        # model reaches for that sentence for in-scope questions
                        # it could answer from governed data; letting it stream
                        # meant the operator saw a refusal before the
                        # orchestrator could replace it with the real answer.
                        prose_pending += buffered[emitted:]
                        emitted = len(buffered)
                        if refusal_in_round:
                            # Every chunk after the denial opens is dropped too.
                            # Without this sticky flag only the first chunk was
                            # suppressed and the rest of "…current role (…) does
                            # not have authorization…" still reached the browser.
                            prose_pending = ""
                            continue
                        if _looks_like_refusal(prose_pending):
                            # Drop the denial entirely; finalization turns it
                            # into a governed-tool nudge below.
                            refusal_in_round = True
                            prose_pending = ""
                            continue
                        if _could_become_refusal(prose_pending):
                            # Still a possible prefix ("I c..."); wait for the
                            # next bytes to disambiguate before streaming.
                            continue
                        text = output_guard.scrub(prose_pending)
                        prose_pending = ""
                        if text:
                            safe, prose_tail = _filter_stream_preamble(
                                prose_tail + text
                            )
                            if safe:
                                yield {"kind": "delta", "text": safe}
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
                                # A refusal can also arrive wrapped in a final
                                # JSON frame. Accumulate the decoded body so the
                                # canned denial is recognised and dropped there
                                # too, not just in the plain-prose branch.
                                if refusal_in_round or _looks_like_refusal(
                                    final_text_so_far + piece
                                ):
                                    refusal_in_round = True
                                    continue
                                final_text_so_far += piece
                                piece = output_guard.scrub(piece)
                                if piece:
                                    yield {"kind": "delta", "text": piece}
                                    rendered_any = True
            except ProviderError as error:
                provider_error = str(error)[:200]
                break

            # A held prose prefix that never resolved into a refusal is still a
            # valid answer (e.g. a one-word reply). Release it rather than
            # losing it when the provider closes the stream mid-prefix.
            if prose_pending and not refusal_in_round:
                text = output_guard.scrub(prose_pending)
                prose_pending = ""
                if text:
                    safe, prose_tail = _filter_stream_preamble(prose_tail + text)
                    # The final fragment may have no sentence terminator. If it
                    # is itself process narration, drop it; otherwise release it
                    # so short valid answers are never lost.
                    if prose_tail and not _is_stream_preamble(prose_tail):
                        safe += prose_tail
                    prose_tail = ""
                    if safe:
                        yield {"kind": "delta", "text": safe}
                        rendered_any = True

            body = buffered.strip()
            if not body:
                break

            if is_tool_frame:
                proposal = self._json_object(
                    buffered[frame_start:] if frame_start >= 0 else body
                )
                if (
                    stream_delegations < MAX_DELEGATIONS
                    and str(proposal.get("action") or "") == "delegate"
                ):
                    # Same bounded sub-agent turn as the buffered path, so the
                    # streamed and buffered surfaces behave identically. One
                    # delegation, then the parent's final answer.
                    sub_agent = agents.resolve(
                        str(proposal.get("agent") or proposal.get("agent_id") or "")
                    )
                    sub_question = str(proposal.get("question") or "")[:300]
                    if sub_agent is not None and sub_question:
                        yield {
                            "kind": "status",
                            "text": f"Consulting the {sub_agent['name']}…",
                        }
                        sub = self._run_delegate(
                            sub_agent,
                            {**context, "depth": int(context.get("depth") or 0) + 1},
                            sub_question,
                        )
                        tools_used.append(f"delegate:{sub_agent['id']}")
                        stream_delegations += 1
                        envelope["delegate_result"] = self._delegate_note(sub)
                        if stream_delegations >= MAX_DELEGATIONS:
                            # The delegation budget is spent. Say so plainly so
                            # the model stops proposing the same specialist and
                            # writes the reply the staff member is waiting for.
                            envelope["delegate_note"] = (
                                "the sub-agent budget is spent; answer now from "
                                "the governed results in hand"
                            )
                        continue
                requests, rejected = self._requested_tools(
                    proposal, allowed_tools, already_run=set(tools_used)
                )
                if command_turn and requests:
                    # Same rule as the buffered surface: the command's tool has
                    # already run, so the answer is what this turn still owes.
                    carried.append({"role": "assistant", "content": body[:1500]})
                    carried.append(
                        {
                            "role": "user",
                            "content": json.dumps(
                                {
                                    "question": question,
                                    "note": (
                                        "this command's governed tool has already "
                                        "run and its result is above"
                                    ),
                                    "instruction": (
                                        "This request is in scope and the result "
                                        "was already authorized for this user. "
                                        "Answer now from that result, do not refuse, "
                                        "and do not request any further tool."
                                    ),
                                },
                                ensure_ascii=False,
                            ),
                        }
                    )
                    continue
                if requests and stream_tool_steps < MAX_STREAM_TOOL_STEPS:
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
                        if tool_id in self._ADVISORY_CONTENT_TOOLS:
                            advisory_results[tool_id] = result
                        chart = _bounded_chart_result(tool_id, result)
                        if chart is not None and len(charts) < 3:
                            charts.append(chart)
                        artifact = _analytics_artifact(
                            tool_id, result
                        ) or _document_artifact(tool_id, result)
                        if artifact is not None and len(artifacts) < 3:
                            artifacts.append(artifact)
                        tool_results.append(self._compact_result(tool_id, result))
                        rows_bearing = _rows_bearing_result(result)
                        if rows_bearing is not None:
                            last_rows_result = rows_bearing
                    envelope["tool_results"] = tool_results
                    stream_tool_steps += 1
                    carried = carried[-4:]
                    continue
                proposed_ids = _proposed_tool_ids(proposal)
                already_run_only = bool(proposed_ids) and proposed_ids <= set(
                    tools_used
                )
                if (
                    str(proposal.get("action") or "tool") == "tool"
                    and attempt + 1 < MAX_STREAM_ROUNDS
                    and (rejected or already_run_only)
                ):
                    # The model asked for a governed tool that cannot add
                    # anything: either it already ran this turn, or it is not
                    # one the agent owns. Running it again would be a wasted
                    # round trip, so the turn is answered from the results in
                    # hand instead of collapsing into a placeholder.
                    note = (
                        "these tools are not available to this agent: "
                        + ", ".join(rejected[:6])
                        if rejected
                        else "that governed report already ran in this turn"
                    )
                    carried.append({"role": "assistant", "content": body[:1500]})
                    carried.append(
                        {
                            "role": "user",
                            "content": json.dumps(
                                {
                                    # Restate the question with the note. Without
                                    # it the model answers a message that only
                                    # talks about tools and asks the staff member
                                    # what they wanted.
                                    "question": question,
                                    "note": note,
                                    # Say explicitly that this is an in-scope
                                    # request. A bare instruction reads as a
                                    # role violation to a prompt carrying an
                                    # "if outside scope, refuse" clause, and the
                                    # model answered a request for data it was
                                    # already allowed to see with the canned
                                    # refusal.
                                    "instruction": (
                                        "This request is in scope and the data was "
                                        "already authorized for this user. The "
                                        "authorized rows are in the envelope above. "
                                        "Answer the question now from them, do not "
                                        "refuse, and never ask for the same tool "
                                        "twice."
                                    ),
                                },
                                ensure_ascii=False,
                            ),
                        }
                    )
                    continue
                # A further tool request breaches the step budget: fall through
                # and answer from the governed results gathered so far.

            # The guardrail block gives a weak model a ready-made refusal
            # sentence, and it reaches for that sentence as a "safe" default on
            # in-scope questions it could answer from governed data. By this
            # point the deterministic pre-flight has already cleared the
            # question (injection, confidentiality, smalltalk) and only a tool
            # result can signal a real denial - so a canned refusal here is a
            # model failure, not an authorization decision. Never ship it.
            planning_preamble = _looks_like_planning_preamble(body)
            if _looks_like_refusal(body) or planning_preamble:
                can_nudge = (
                    not command_turn
                    and attempt + 1 < MAX_STREAM_ROUNDS
                    and stream_tool_steps < MAX_STREAM_TOOL_STEPS
                    and any(
                        tool in allowed_tools
                        for tool in ("data.query", "reports.nlq", "analytics.summary")
                    )
                )
                if can_nudge:
                    carried.append({"role": "assistant", "content": body[:1500]})
                    carried.append(
                        {
                            "role": "user",
                            "content": json.dumps(
                                {
                                    "question": question,
                                    "note": (
                                        "the previous reply narrated a plan instead "
                                        "of answering"
                                        if planning_preamble
                                        else "the previous reply used the canned "
                                        "authorization-refusal sentence"
                                    ),
                                    "instruction": (
                                        (
                                            "That reply did not answer the "
                                            "operator. This is a page-guidance "
                                            "question: answer DIRECTLY now with "
                                            "plain guidance - what this screen "
                                            "is for and what the operator can "
                                            "do here, using the page_context "
                                            "labels. No data tools, no figures "
                                            "you cannot verify, no refusal."
                                        )
                                        if page_help
                                        else (
                                            "That reply was meta-commentary about "
                                            "what you intended to do, not an answer. "
                                            "Do NOT describe your plan, list steps, "
                                            "or narrate what you are checking. Answer "
                                            "the operator DIRECTLY and SUBSTANTIVELY "
                                            "now: give the forecast, the probability, "
                                            "the evidence and the single recommended "
                                            "action from the governed results already "
                                            "supplied. If a figure is missing, request "
                                            "ONE governed data tool with a JSON tool "
                                            "frame, then answer from the rows."
                                        )
                                        if planning_preamble
                                        else (
                                            "That refusal was incorrect: this request "
                                            "passed the scope checks and the operator "
                                            "is authorized. Do NOT use the "
                                            "authorization-refusal sentence. If the "
                                            "supplied results do not answer the "
                                            "question, request ONE governed data tool "
                                            "now (data.query to list or read the "
                                            "matching dataset, or reports.nlq with the "
                                            "question) using the JSON tool frame, then "
                                            "answer from the rows you get back."
                                        )
                                    ),
                                },
                                ensure_ascii=False,
                            ),
                        }
                    )
                    continue
                # No retry budget left: render real governed rows if we have
                # them, otherwise the neutral fallback. The refusal itself is
                # never returned to the operator.
                parsed = (
                    self._rows_answer(last_rows_result) if last_rows_result else None
                )
            else:
                parsed = self._parse_answer_text(_strip_meta_chatter(body))
            if parsed:
                if command_turn and (
                    _looks_like_refusal(str(parsed.get("body") or ""))
                    or not str(parsed.get("body") or "").strip()
                ):
                    rendered = _command_outcome_answer(
                        str(context.get("forced_tool") or ""), forced_source_result
                    ) or self._rows_answer(last_rows_result)
                    if rendered is not None:
                        parsed = {**parsed, **rendered}
                answer = self._guard_grounded_answer(
                    parsed,
                    last_rows_result,
                    self._allowed_evidence_text(question, tool_results),
                    strict_numbers=not self._is_analytical_question(question),
                )
            break

        outcome = "answered" if answer else "unavailable"
        degraded = False
        answer = self._guard_grounded_answer(
            answer,
            last_rows_result,
            self._allowed_evidence_text(question, tool_results),
            strict_numbers=not self._is_analytical_question(question),
        )
        answer = self._guard_figures_without_evidence(
            answer,
            last_rows_result,
            tools_used,
            self._allowed_evidence_text(question, tool_results),
        )
        # "What's new in CBC/CBE" is current public information. The curated
        # education.news digest is profile-filtered (no Senior School content,
        # no encyclopedia noise) and source-bearing, whereas the model's own
        # prose can reintroduce off-profile items from the raw web.search tool
        # and mislabel public web facts as "Based on school records". For these
        # questions the governed, filtered digest is authoritative.
        if agents.is_education_news_question(question):
            news_rendered = self._advisory_answer_from_result(
                "education.news", advisory_results.get("education.news") or {}
            )
            if news_rendered is not None:
                answer = news_rendered
                outcome = "answered"
        # "Give me three useful suggestions for my work" is a workspace-review
        # prompt. The wording model sometimes answers it with a bare zero-row
        # reports.nlq result (routing=advisory, agent=reports.agent) even though
        # the governed workspace-scan / insight-brief content was prefetched.
        # For these questions the deterministic advisory renderer is
        # authoritative, so the operator always gets real suggestions.
        if agents.is_advisory_suggestion_question(question):
            suggestion_rendered = self._advisory_prefetch_answer(
                list(advisory_results.items())
            )
            if suggestion_rendered is not None:
                answer = suggestion_rendered
                outcome = "answered"
        if answer is None:
            # The wording model may answer an in-scope advisory question with a
            # canned refusal or pure process narration. The governed advisory
            # tool already returned exactly what was asked for, so render that
            # content instead of the neutral "couldn't verify that" placeholder.
            advisory = self._advisory_prefetch_answer(list(advisory_results.items()))
            if advisory is not None:
                answer = advisory
                outcome = "answered"
            elif deterministic_answer:
                # The provider did not deliver, but the authorized rows are in
                # hand: render them deterministically so the staff member still
                # gets the real figures instead of an error bubble.
                answer = {
                    "title": deterministic_answer[:100],
                    "body": deterministic_answer,
                    "next_steps": [],
                    "suggested_questions": deterministic_followups,
                    "escalation_required": False,
                    "insufficient_data": insufficient_data,
                    "citations": deterministic_citations,
                }
                outcome = "answered"
                degraded = True
                for piece in deterministic_answer.split():
                    piece = output_guard.scrub(piece + " ")
                    if piece:
                        yield {"kind": "delta", "text": piece}
            else:
                # A page-guidance question never needs the "couldn't verify"
                # placeholder: answer it deterministically from the page
                # context the browser sent (route, open record, filters) so
                # the operator always gets a useful reply.
                fallback = (
                    self._page_help_answer(context, agent, question)
                    if page_help
                    else self._fallback_answer(tools_used, tool_results)
                )
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
            # The final frame repeats the answer in full, so it needs the same
            # scrub the deltas received: the streamed relay cannot filter for
            # us, and the guard has exactly one implementation - this one.
            "answer": output_guard.scrub(answer),
            "degraded": degraded,
            "tools_used": _public_tool_labels(tools_used),
            "charts": output_guard.scrub(charts),
            "artifacts": output_guard.scrub_artifact(artifacts),
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
            if "personality" in incoming:
                from .personality import clean_personality

                profile = clean_personality(incoming.get("personality"))
                if profile:
                    prefs["personality"] = profile
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
        # Stable per-user coworker personality (advisory, lower-trust).
        lines.extend(personality_lines(prefs.get("personality")))
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

    def _page_context_lines(self, context: dict) -> list[str]:
        """What the operator is looking at, as short labels.

        The browser sends only labels and ids it already displays: the record
        open in a modal, the rows selected, visible KPI names and active filter
        labels. No row values are carried here — the governed tool still has to
        authorise any real data — so this is context, not a second data path.
        """
        lines: list[str] = []
        page_state = str(context.get("page_state") or "").strip()
        if page_state:
            lines.append(f"Live page state: {page_state[:60]}")
        entity = context.get("page_entity")
        if isinstance(entity, dict):
            parts: list[str] = []
            for key in ("kind", "label", "name", "id", "status"):
                value = entity.get(key)
                if isinstance(value, (str, int)) and str(value).strip():
                    parts.append(f"{key}={str(value).strip()[:80]}")
            if parts:
                lines.append("Open record: " + ", ".join(parts))
        selection = context.get("selection")
        if isinstance(selection, dict):
            label = str(selection.get("label") or "").strip()
            text = str(selection.get("text") or "").strip()
            if label:
                lines.append(f"Selected: {label[:100]}")
            if text:
                lines.append(f"Selection text: {text[:200]}")
        modal = str(context.get("modal") or "").strip()
        if modal:
            lines.append(f"Open dialog: {modal[:100]}")
        metrics = context.get("visible_metrics")
        if isinstance(metrics, list):
            labels = [
                str(item).strip()[:60]
                for item in metrics[:10]
                if isinstance(item, (str, int, float)) and str(item).strip()
            ]
            if labels:
                lines.append("Visible metrics: " + ", ".join(labels))
        filters = context.get("filters")
        if isinstance(filters, dict):
            labels = [
                f"{str(key)[:40]}={str(value)[:60]}"
                for key, value in list(filters.items())[:10]
                if isinstance(value, (str, int, float)) and str(value).strip()
            ]
            if labels:
                lines.append("Active filters: " + ", ".join(labels))
        # Server-side catalog entry: what this screen IS (label, domain,
        # components, role access) — resolved by PHP, not claimed by the
        # browser, so page-guidance answers are grounded in the real page.
        info = context.get("page_info")
        if isinstance(info, dict):
            parts: list[str] = []
            label = str(info.get("label") or "").strip()
            domain = str(info.get("domain") or "").strip()
            if label:
                parts.append(f"page={label[:80]}")
            if domain and domain != "general":
                parts.append(f"domain={domain[:40]}")
            components = info.get("components")
            if isinstance(components, list) and components:
                parts.append(
                    "components=" + ",".join(str(c)[:20] for c in components[:10])
                )
            roles = info.get("staff_roles_with_access")
            if isinstance(roles, int) and roles > 0:
                parts.append(f"staff_roles_with_access={roles}")
            if parts:
                lines.insert(0, "Page catalog: " + "; ".join(parts))
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

        The server-resolved effective permission is the authority. Role labels
        are descriptive only and must never promote a School Administrator,
        Director, or any other role into the system-only code/log tool set.
        """
        permissions = set(str(p) for p in (context.get("permissions") or []))
        return SYSTEM_ADMIN_PERMISSION in permissions

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

    # Envelope fields, cheapest to lose first. The governed tool_results and
    # the question are last: they are the only parts of the prompt that are
    # about this question, so they are the last thing trimmed.
    _TRIM_ORDER = (
        "behavior_hints",
        "navigation",
        "page_context",
        "session_topics",
        "preferences",
        "conversation",
    )

    def _assert_prompt_fits(self, messages: list[dict[str, str]]) -> None:
        """Trim the prompt rather than fail the whole answer.

        Every envelope field above `_TRIM_ORDER` is dropped before a single
        governed result is touched, then the oldest history turns. The previous
        order dropped `tool_results` first, which meant that whenever the fixed
        part of the prompt was large the model answered with no evidence at all
        and produced refusals for data the server had already released.
        """
        if policy.MAX_CONTEXT_BYTES <= 0:
            return
        guard = 0
        while self._prompt_bytes(messages) > policy.MAX_CONTEXT_BYTES:
            guard += 1
            if guard > 64:
                return
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
                for field in self._TRIM_ORDER:
                    if field in payload:
                        payload.pop(field, None)
                        changed = True
                        break
                if not changed:
                    results = payload.get("tool_results")
                    if isinstance(results, list) and results:
                        if len(results) > 1:
                            payload["tool_results"] = results[1:]
                        else:
                            payload.pop("tool_results", None)
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
        """Render the authorized report rows deterministically.

        The rendering is COLUMN-DRIVEN on purpose. It used to hard-code the
        column names of a handful of reports ("total", "rate"), so any report
        that spelled its column differently rendered zeros — the finance report
        showed "Grade 7: 0" while the data said 1,485,730. Reading the columns
        that are actually present removes that whole class of mismatch and
        keeps every new report correct without a code change.
        """
        answer_data = (
            nlq_result.get("answer")
            if isinstance(nlq_result.get("answer"), dict)
            else nlq_result
        )
        report_code = str(answer_data.get("report_code") or "")
        report_title = str(answer_data.get("report_title") or "").strip()
        row_count = int(answer_data.get("row_count") or 0)
        rows = (
            answer_data.get("rows") if isinstance(answer_data.get("rows"), list) else []
        )
        rows = [row for row in rows if isinstance(row, dict)]

        display_title = report_title
        if not display_title and report_code:
            display_title = "Governed report"

        if not rows:
            label = f" ({display_title})" if display_title else ""
            return f"The report{label} returned no data for the current filters."

        def _number(value: Any) -> float | None:
            """A row value as a number, including the strings money columns use."""
            if isinstance(value, bool):
                return None
            if isinstance(value, (int, float)):
                return float(value)
            if isinstance(value, str):
                text = value.strip().replace(",", "")
                if not text:
                    return None
                try:
                    return float(text)
                except ValueError:
                    return None
            return None

        numeric_columns: list[str] = []
        label_column = ""
        for row in rows:
            for key, value in row.items():
                name = str(key)
                if isinstance(value, bool):
                    continue
                if name.endswith("_id") or name in ("id", "run_id"):
                    # An identifier is not a measure; listing it beside the
                    # label adds noise the staff member never asked for.
                    continue
                number = _number(value)
                if number is not None and name not in numeric_columns:
                    numeric_columns.append(name)
                elif isinstance(value, str) and number is None and not label_column:
                    label_column = name
        if not numeric_columns:
            return f"The report{(' (' + display_title + ')') if display_title else ''} returned rows without a numeric value to report."

        parts = [f"**{display_title}**" if display_title else "**Governed report**"]

        def _format(value: Any, column: str = "") -> str:
            if isinstance(value, bool):
                return "yes" if value else "no"
            number = _number(value)
            if number is not None:
                # Follow the shape of the authorized value rather than guessing
                # from the column name: a money column is "1485730.00" in the
                # data, a count is "48". Guessing by name rendered every count
                # as "48.00" and every integer as "1,485,730.0".
                if isinstance(value, str):
                    text_value = value.strip()
                    if "." in text_value:
                        return f"{number:,.2f}"
                    return f"{int(number):,}"
                if isinstance(value, int):
                    return f"{value:,}"
                lowered = column.lower()
                money = any(
                    token in lowered
                    for token in ("arrears", "amount", "balance", "fee", "cost")
                )
                return f"{number:,.2f}" if money else f"{number:,.1f}"
            text = str(value).strip()
            return text[:60]

        # Totals are only summed for columns that clearly ARE a total, so a
        # "class" label or a rate is never added up into nonsense.
        for column in numeric_columns:
            lowered = column.lower()
            if not (
                lowered.startswith("total")
                or lowered in ("arrears", "amount", "balance", "sum")
                or lowered.endswith("_arrears")
                or lowered.endswith("_amount")
            ):
                continue
            # Head counts are never added across rows: a learner can appear in
            # more than one row (two classes, two years), so the sum is not a
            # number of people. "students_in_arrears" ends in "_arrears" and was
            # summed into "555 students in arrears" for a roll of 454.
            if _is_head_count_column(lowered):
                continue
            raw_values = [row.get(column) for row in rows]
            numbers = [n for n in map(_number, raw_values) if n is not None]
            if not numbers:
                continue
            pretty = lowered.replace("_", " ").strip()
            total = sum(numbers)
            # A total keeps the shape of the values it adds: a count sums to a
            # whole number, a money column sums to cents.
            fractional = any(
                isinstance(v, str) and "." in v.strip() for v in raw_values
            )
            if not fractional and total == int(total):
                total_text = f"{int(total):,}"
            else:
                money = any(
                    token in lowered
                    for token in ("arrears", "amount", "balance", "fee", "cost")
                )
                total_text = f"{total:,.2f}" if money else f"{total:,.1f}"
            parts.append(f"**{pretty.capitalize()}**: {total_text}")

        for row in rows[:10]:
            label = str(row.get(label_column, "")).strip() if label_column else ""
            values = ", ".join(
                f"{column.replace('_', ' ')}: {_format(row.get(column), column)}"
                for column in numeric_columns[:3]
                if row.get(column) is not None
            )
            parts.append(f"• {label}: {values}" if label else f"• {values}")
        if row_count > len(rows[:10]):
            parts.append(f"… and {row_count - len(rows[:10])} more row(s)")
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
    # Deterministic prefetch intent. No model call, no extra latency: each
    # question is classified once, provider-free, into the governed tools whose
    # evidence it actually needs. This exists because a blind reports.nlq
    # prefetch mapped "active vehicles overdue for service" to the fee-arrears
    # report, and the prompt then called those wrong rows "your evidence".
    _WEB_INTENT_TERMS = (
        "web",
        "internet",
        "online",
        "latest",
        "current",
        "news",
        "what's new",
        "whats new",
        "trend",
        "ministry of education",
        "moe",
        "cbc",
        "cbe",
        "kicd",
        "knec",
        "around the world",
        "global",
        "guidance",
        "regulation",
        "this term",
        "curriculum design",
    )
    _DATASET_INTENTS: tuple[tuple[tuple[str, ...], str], ...] = (
        (
            (
                "fleet",
                "vehicle",
                "bus",
                "transport",
                "capacity",
                "insurance",
                "service due",
                "overdue for service",
                "driver",
            ),
            "fleet_by_status",
        ),
        (
            (
                "fee balance",
                "fee",
                "arrears",
                "payment status",
                "collection",
                "collected",
                "outstanding",
            ),
            "fee_balance_by_status",
        ),
        (("attendance", "present", "absent", "late"), "attendance_by_class"),
        (
            (
                "classes per year",
                "how many classes",
                "number of classes",
                "class count",
            ),
            "classes_by_year",
        ),
    )
    _DATA_INTENT_TERMS = (
        "how many",
        "count",
        "total",
        "balance",
        "arrears",
        "list",
        "show",
        "report",
        "summary",
        "by class",
        "per class",
        "attendance",
        "enrolled",
        "admission",
        "vehicles",
        "fleet",
        "classes",
    )
    # Fixed, public-safe search queries. The operator's question is never sent
    # to a search engine (it can contain school data); only these curated
    # strings leave the platform.
    _WEB_QUERIES: tuple[tuple[tuple[str, ...], str], ...] = (
        (
            ("vehicle", "fleet", "transport", "bus", "safety", "ntsa", "driver"),
            "Kenya public service vehicle safety regulations NTSA current guidance",
        ),
        (
            ("fee", "capitation", "school fee", "arrears", "collection"),
            "Kenya Ministry of Education school capitation and fee guidelines current",
        ),
        (
            ("cbc", "cbe", "kicd", "curriculum", "strand", "scheme", "lesson"),
            "Kenya KICD CBC CBE curriculum guidance current",
        ),
        (
            ("exam", "assessment", "knec", "rubric"),
            "Kenya KNEC CBC assessment guidance current",
        ),
    )
    _GENERIC_WEB_QUERY = (
        "Kenya CBC education current guidance KICD Ministry of Education"
    )

    @classmethod
    def _prefetch_intent(
        cls, question: str, agent_tools: set[str], forced_tool: str = ""
    ) -> list[tuple[str, dict[str, Any]]]:
        """The governed tools whose evidence this question actually needs.

        A slash command names its own tool, so intent prefetching is skipped for
        those turns. Otherwise the plan can combine current public information
        with a named school dataset: prediction, hypothesis and decision questions
        need BOTH, and neither alone answers them.
        """
        plan: list[tuple[str, dict[str, Any]]] = []
        if forced_tool and forced_tool != "reports.nlq":
            return plan
        query = str(question or "").lower()

        if any(term in query for term in cls._WEB_INTENT_TERMS):
            if "education.news" in agent_tools:
                topic = "all"
                # "education" alone is generic: it is not a Ministry-of-Education
                # question. Mapping it to the narrow "moe" topic made a plain
                # "What's new in education" return an empty digest and a canned
                # refusal. Keep the broad "all" digest for that phrasing; only
                # explicit ministry/MoE wording selects the "moe" topic.
                if any(t in query for t in ("ministry", "moe")):
                    topic = "moe"
                elif any(t in query for t in ("curriculum", "cbc", "cbe", "kicd")):
                    topic = "curriculum"
                elif any(t in query for t in ("exam", "assessment", "knec")):
                    topic = "assessment"
                elif any(t in query for t in ("global", "world", "trend")):
                    topic = "global"
                plan.append(("education.news", {"topic": topic, "limit": 5}))
            if "web.search" in agent_tools:
                search_query = next(
                    (
                        public_query
                        for terms, public_query in cls._WEB_QUERIES
                        if any(term in query for term in terms)
                    ),
                    cls._GENERIC_WEB_QUERY,
                )
                plan.append(("web.search", {"query": search_query, "limit": 5}))

        dataset = next(
            (
                name
                for terms, name in cls._DATASET_INTENTS
                if any(term in query for term in terms)
            ),
            None,
        )
        if dataset and "data.query" in agent_tools:
            plan.append(("data.query", {"dataset": dataset}))

        looks_like_data = any(term in query for term in cls._DATA_INTENT_TERMS)
        if dataset is None and "reports.nlq" in agent_tools:
            if looks_like_data or not plan:
                plan.append(("reports.nlq", {"question": question}))
        return plan

    # A question that asks the model to REASON (predict, test a hypothesis,
    # decide, forecast) is allowed to derive figures the rows do not literally
    # contain: sums, rates, percentages, probabilities. Those are analysis, not
    # fabricated school records. Literal lookups ("how many vehicles", "what are
    # the fee arrears by class") must still quote the rows exactly. Keeping these
    # two classes apart is what stops the number guard from turning a genuinely
    # intelligent answer into a raw table.
    _ANALYTICAL_TERMS = (
        "predict",
        "prediction",
        "probability",
        "probability",
        "forecast",
        "estimate",
        "hypothesis",
        "hypothesise",
        "hypothesize",
        "test that",
        "evidence supports",
        "supports your hypothesis",
        "decide",
        "decision",
        "risk",
        "likelihood",
        "what if",
        "given current",
        "combine that",
        "join three",
        "tomorrow",
        "next week",
        "trend",
        "projection",
        "assign a probability",
        "reach 90%",
        "concentrated",
        "spread evenly",
        "suggest the one action",
        "single decision",
    )

    @classmethod
    def _is_analytical_question(cls, question: str) -> bool:
        q = str(question or "").lower()
        return any(term in q for term in cls._ANALYTICAL_TERMS)

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
        context: dict | None = None,
    ) -> list[tuple[str, dict[str, Any]]]:
        """Prefetch the governed evidence this question actually needs.

        Intent classification (provider-free) picks the right tools: a fleet
        question gets the fleet dataset, a fee question the fee dataset, a
        question about current guidance gets public-web results, and a
        prediction/decision question gets BOTH. The old code ran reports.nlq for
        every agent that owned it, so "active vehicles overdue for service" was
        answered with fee-arrears rows presented to the model as its evidence.
        """
        question_lower = str(question or "").lower()
        agent_tools = set(agent.get("tools", ()))
        forced_tool = str((context or {}).get("forced_tool") or "")
        # A slash command names its own tool; never pile unrelated evidence on
        # top of it (that spent a report run on every /web, /crawl and /draft).
        candidates = self._prefetch_intent(question, agent_tools, forced_tool)

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
        # Advisory coworker prompts need the same deterministic evidence the
        # proactive cards use. Without it the model fell back to an empty
        # reports.nlq run and told the operator it had no reliable answer.
        # Use the shared phrase set so routing (agents.is_advisory_suggestion_question)
        # and prefetch agree on what counts as a suggestions/workspace-review
        # prompt. The old inline list omitted "three useful suggestions",
        # "give me suggestions" and "my schedule today", so those questions
        # routed as advisory but prefetched nothing and fell back to a bare
        # zero-row reports.nlq result.
        asks_for_advice = agents.is_advisory_suggestion_question(question)

        if asks_for_briefing and "reports.insight_brief" in agent_tools:
            candidates.append(("reports.insight_brief", {"cadence": "daily"}))
        if asks_for_advice and "reports.insight_brief" in agent_tools:
            candidates.append(("reports.insight_brief", {"cadence": "daily"}))
        if asks_for_advice:
            # Re-authorized by PHP against the caller's own grants, so this is
            # safe to prefetch even though it is not a model-callable tool.
            candidates.append(("assistant.workspace_scan", {"route": "dashboard"}))
        # A "what should we post / content ideas" question must deterministically
        # fetch the communications agent's advisory tool. Previously the model
        # was trusted to call it; when it refused first, no governed result
        # existed and the turn collapsed to "I couldn't verify that". Prefetch
        # makes the substantive post ideas available regardless of model mood.
        if (
            agents.is_public_content_question(question)
            and "public_content.advisor" in agent_tools
        ):
            candidates.append(("public_content.advisor", {"include_news": True}))
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

    @staticmethod
    def _command_source_result(
        context: dict, prefetched: list
    ) -> dict[str, Any] | None:
        """The governed result a command's follow-up must draw from.

        The command names its own tool, but the prefetch may already have run
        a different one for the same question (a "/pdf fee arrears by class"
        question prefetches the chart source, not the plain report). Matching
        only the exact tool id left the follow-up with no source, so the
        document came out unnamed. Any prefetched result carrying rows is the
        right evidence: it is the same governed data for the same operator.
        """
        wanted = str(context.get("forced_tool") or "")
        for tool_id, result in prefetched:
            if tool_id == wanted:
                return result
        for _tool_id, result in prefetched:
            if _rows_bearing_result(result) is not None:
                return result
        return None

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
        tools = _expanded_agent_tools(agent, self.tools)

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
        scope_checks: list[dict[str, Any]] = []
        charts: list[dict[str, Any]] = []
        artifacts: list[dict[str, Any]] = []
        # The last governed result that carried rows, so a turn that spends
        # its budget on tools can still answer with the real figures.
        last_rows_result: dict[str, Any] | None = None
        tool_steps = 0
        provider_calls = 0
        answer: dict[str, Any] | None = None
        # Delegation budget for THIS turn. Inherited depth comes from the
        # context so a sub-turn cannot start a second chain.
        depth = int(context.get("depth") or 0)
        delegate_count = 0

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
            context=context,
        )
        for tool_id, result in prefetched:
            tool_results.append(self._compact_result(tool_id, result))
            chart = _bounded_chart_result(tool_id, result)
            if chart is not None:
                charts.append(chart)
            artifact = _analytics_artifact(tool_id, result) or _document_artifact(
                tool_id, result
            )
            if artifact is not None:
                artifacts.append(artifact)
            rows_bearing = _rows_bearing_result(result)
            if rows_bearing is not None:
                last_rows_result = rows_bearing
            scope_check = self._report_scope_check(tool_id, result)
            if scope_check is not None and scope_check not in scope_checks:
                scope_checks.append(scope_check)
            if self._tool_succeeded(result):
                tools_used.append(tool_id)

        # A slash action command (/web, /crawl, /report, /draft …) runs its
        # governed tool before the model speaks, exactly like the prefetch, so
        # the command does what it says instead of only nudging the prompt. It
        # is still executed through the same permission gate and only when the
        # selected agent owns the tool.
        forced_source_result: dict[str, Any] | None = None
        # A slash action command is a single-action turn: its governed tool (or
        # its report) has already run, so the remaining budget belongs to the
        # answer. Without this the model spent the rest of the turn asking for
        # a briefing and a catalogue that the command never asked for, and the
        # staff member got "I reached the limit" instead of their draft.
        command_turn = False
        forced = self._run_forced_tool(
            context,
            agent,
            user_id,
            permissions,
            request_id,
            already_run=set(tools_used),
        )
        if forced is not None:
            forced_tool, forced_result = forced
            command_turn = True
            forced_source_result = forced_result
            tools_used.append(forced_tool)
            tool_results.append(self._compact_result(forced_tool, forced_result))
            chart = _bounded_chart_result(forced_tool, forced_result)
            if chart is not None:
                charts.append(chart)
            artifact = _analytics_artifact(
                forced_tool, forced_result
            ) or _document_artifact(forced_tool, forced_result)
            if artifact is not None:
                artifacts.append(artifact)
            rows_bearing = _rows_bearing_result(forced_result)
            if rows_bearing is not None:
                last_rows_result = rows_bearing
        elif context.get("forced_tool"):
            # The command's tool already ran as a prefetch (a /report or
            # /forecast command on an agent that owns reports.nlq prefetches
            # it), so the turn is still a command turn and its result is still
            # the source the follow-up draws from. Nesting the follow-up inside
            # the block above meant /forecast never computed its projection.
            command_turn = True
            forced_source_result = self._command_source_result(context, prefetched)
        if context.get("forced_follow_up"):
            follow_up = self._run_follow_up(
                context,
                agent,
                user_id,
                permissions,
                request_id,
                tools_used,
                already_run=set(tools_used),
                source_result=forced_source_result,
            )
            if follow_up is not None:
                follow_up_tool, follow_up_result = follow_up
                if self._tool_succeeded(follow_up_result):
                    tools_used.append(follow_up_tool)
                tool_results.append(
                    self._compact_result(follow_up_tool, follow_up_result)
                )
                follow_up_artifact = _analytics_artifact(
                    follow_up_tool, follow_up_result
                ) or _document_artifact(follow_up_tool, follow_up_result)
                if follow_up_artifact is not None and len(artifacts) < 3:
                    artifacts.append(follow_up_artifact)

        instruction = str(context.get("instruction") or "")[:400]

        try:
            # Notes the loop writes back to the model ("that tool already ran",
            # "answer now") must survive from one round to the next. They did
            # not: the message list was rebuilt from scratch on every round, so
            # each note was discarded before the provider ever saw it and the
            # model was asked the identical question three times in a row.
            carried: list[dict[str, str]] = []
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
                            "page_context": self._page_context_lines(context),
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
                messages.extend(carried)

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
                if action == "delegate":
                    # Sub-agent turn: a specialist answers this one sub-question
                    # with the operator's own permissions, and the parent folds
                    # the result into its next provider call. Not treated as a
                    # tool, so it costs no ToolBridge round trip.
                    if delegate_count >= MAX_DELEGATIONS or depth >= MAX_DELEGATIONS:
                        messages.append(
                            {
                                "role": "assistant",
                                "content": json.dumps(response, ensure_ascii=False),
                            }
                        )
                        messages.append(
                            {
                                "role": "user",
                                "content": json.dumps(
                                    {
                                        "error": "delegation budget reached; answer from the governed results in hand"
                                    },
                                    ensure_ascii=False,
                                ),
                            }
                        )
                        continue

                    sub_agent = agents.resolve(
                        str(response.get("agent") or response.get("agent_id") or "")
                    )
                    sub_question = str(response.get("question") or "")[:300]
                    if sub_agent is None or not sub_question:
                        messages.append(
                            {
                                "role": "user",
                                "content": json.dumps(
                                    {
                                        "error": "a delegation needs a known agent id and a question"
                                    },
                                    ensure_ascii=False,
                                ),
                            }
                        )
                        continue

                    delegate_count += 1
                    sub = self._run_delegate(
                        sub_agent,
                        {**context, "depth": depth + 1},
                        sub_question,
                    )
                    tools_used.append(f"delegate:{sub_agent['id']}")
                    messages.append(
                        {
                            "role": "assistant",
                            "content": json.dumps(response, ensure_ascii=False),
                        }
                    )
                    messages.append(
                        {
                            "role": "user",
                            "content": json.dumps(
                                {"delegate_result": sub}, ensure_ascii=False
                            ),
                        }
                    )
                    continue

                if action != "tool":
                    answer = self._validate_answer(response.get("answer") or response)
                    if command_turn and (
                        _looks_like_refusal(str(answer.get("body") or ""))
                        or not str(answer.get("body") or "").strip()
                    ):
                        # A slash command is a promise. If the model refuses the
                        # request it just completed, or answers with nothing,
                        # the command's own tool result is rendered instead.
                        rendered = _command_outcome_answer(
                            str(context.get("forced_tool") or ""), forced_source_result
                        ) or self._rows_answer(last_rows_result)
                        if rendered is not None:
                            answer = {**answer, **rendered}
                    break
                if command_turn:
                    # A slash action command already ran its governed tool (and
                    # its follow-up) before the model spoke. The remaining
                    # budget belongs to the answer: the model adding a briefing
                    # and a catalogue on top of /draft spent the turn on tools
                    # the command never asked for and the staff member got
                    # "I reached the limit" instead of their draft.
                    messages.append(
                        {
                            "role": "assistant",
                            "content": json.dumps(response, ensure_ascii=False),
                        }
                    )
                    messages.append(
                        {
                            "role": "user",
                            "content": json.dumps(
                                {
                                    "question": question,
                                    "note": (
                                        "this command's governed tool has already "
                                        "run and its result is above"
                                    ),
                                    "instruction": (
                                        "This request is in scope and the result "
                                        "was already authorized for this user. "
                                        "Answer now from that result, do not refuse, "
                                        "and do not request any further tool."
                                    ),
                                },
                                ensure_ascii=False,
                            ),
                        }
                    )
                    continue
                if (
                    tool_steps >= MAX_TOOL_STEPS
                    or len(tools_used) >= MAX_TOOL_EXECUTIONS
                ):
                    answer = (
                        self._rows_answer(last_rows_result)
                        if last_rows_result
                        else None
                    )
                    if answer is None:
                        answer = {
                            "title": "Assistant ran out of steps",
                            "body": "I reached the limit for this request before I could finish. Please narrow the question or ask for one task at a time.",
                            "next_steps": [
                                "Ask one focused question, then continue with a follow-up."
                            ],
                            "suggested_questions": [],
                            "escalation_required": False,
                        }
                    break

                requests, rejected = self._requested_tools(
                    response, tools, already_run=set(tools_used)
                )
                if rejected:
                    tool_results.append(rejected)
                if not requests:
                    proposed_ids = []
                    raw_tools = response.get("tools")
                    if isinstance(raw_tools, list):
                        proposed_ids = [
                            str(item.get("tool") or "").strip()
                            for item in raw_tools
                            if isinstance(item, dict)
                        ]
                    else:
                        proposed_ids = [str(response.get("tool") or "").strip()]
                    if proposed_ids and all(
                        tool_id in tools for tool_id in proposed_ids
                    ):
                        # The model asked for a tool it already ran. Running it
                        # again cannot add information, so the loop tells the
                        # model to answer from the results it already has
                        # instead of silently spending the turn.
                        messages.append(
                            {
                                "role": "assistant",
                                "content": json.dumps(response, ensure_ascii=False),
                            }
                        )
                        messages.append(
                            {
                                "role": "user",
                                "content": json.dumps(
                                    {
                                        # Restate the question with the note so the
                                        # turn ends with an actual request to
                                        # answer, not a message about tools.
                                        "question": question,
                                        "note": "that governed report already ran in this turn",
                                        # Explicitly in scope: a bare instruction
                                        # reads as a role violation to a prompt
                                        # that carries a "refuse if outside scope"
                                        # clause, which produced the canned
                                        # refusal for data the user may see.
                                        "instruction": (
                                            "This request is in scope and the data "
                                            "was already authorized for this user. "
                                            "The authorized rows are above. Answer "
                                            "the question now from them, do not "
                                            "refuse, and never ask for the same "
                                            "tool twice."
                                        ),
                                    },
                                    ensure_ascii=False,
                                ),
                            }
                        )
                        continue
                    # Repeating a tool that already ran cannot add information
                    # and otherwise burns the entire provider-call budget.
                    answer = {
                        "title": "Assistant ran out of steps",
                        "body": "I reached the limit for this request before I could finish. Please narrow the question or ask for one task at a time.",
                        "next_steps": [
                            "Ask one focused question, then continue with a follow-up."
                        ],
                        "suggested_questions": [],
                        "escalation_required": False,
                    }
                    break
                requests = requests[: max(0, MAX_TOOL_EXECUTIONS - len(tools_used))]

                # Independent governed tools run concurrently: latency drops to
                # the slowest single call instead of their sum, while the
                # worker count stays hard-capped so nothing floods PHP.
                tool_steps += 1
                for tool_id, tool_result in self._run_tools_parallel(
                    requests,
                    user_id,
                    permissions,
                    request_id,
                    roles=context.get("roles"),
                    role_ids=context.get("role_ids"),
                ):
                    tools_used.append(tool_id)
                    chart = _bounded_chart_result(tool_id, tool_result)
                    if chart is not None:
                        charts.append(chart)
                    artifact = _analytics_artifact(
                        tool_id, tool_result
                    ) or _document_artifact(tool_id, tool_result)
                    if artifact is not None:
                        artifacts.append(artifact)
                    rows_bearing = _rows_bearing_result(tool_result)
                    if rows_bearing is not None:
                        last_rows_result = rows_bearing
                    scope_check = self._report_scope_check(tool_id, tool_result)
                    if scope_check is not None and scope_check not in scope_checks:
                        scope_checks.append(scope_check)
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
                "scope_checks": scope_checks,
                "provider_calls": provider_calls,
                "message": str(error)[:300],
            }

        if answer is None:
            answer = self._rows_answer(last_rows_result) if last_rows_result else None
        answer = self._guard_grounded_answer(
            answer,
            last_rows_result,
            self._allowed_evidence_text(question, tool_results),
            strict_numbers=not self._is_analytical_question(question),
        )
        answer = self._guard_figures_without_evidence(
            answer,
            last_rows_result,
            tools_used,
            self._allowed_evidence_text(question, tool_results),
        )
        if answer is None:
            answer = {
                "title": "Assistant ran out of steps",
                "body": "I reached the limit for this request before I could finish. Please narrow the question or ask for one task at a time.",
                "next_steps": [
                    "Ask one focused question, then continue with a follow-up."
                ],
                "suggested_questions": [],
                "escalation_required": False,
            }
        return {
            "status": "answered",
            # Same scrub the streamed surface gets, plus the same human labels
            # instead of internal tool ids.
            "answer": output_guard.scrub(answer),
            "tools_used": _public_tool_labels(tools_used),
            "charts": output_guard.scrub(charts[:3]),
            "artifacts": output_guard.scrub_artifact(artifacts[:3]),
            "scope_checks": scope_checks,
            "provider_calls": provider_calls,
        }

    # ---------------------------------------------------------------- sub-agents
    def _run_delegate(
        self, agent: dict[str, Any], context: dict, question: str
    ) -> dict[str, Any]:
        """Run one bounded specialist turn and return its answer to the parent.

        This is what makes the coworker a team rather than one generalist: the
        primary agent keeps the conversation and the persona, and a specialist
        supplies the domain reasoning.

        The sub-turn is deliberately narrow:
          * it inherits the SAME operator permissions, so a delegate can never
            see what the caller could not;
          * it runs at most one tool round plus one answer call and no
            delegation of its own, so a chain cannot outrun the request;
          * it returns the answer plus the tools it used, never its own session;
          * a failure degrades to a plain reason the parent can act on, so one
            broken specialist cannot fail the whole turn.
        """
        user_id = int(context["user_id"])
        permissions = list(context["permissions"])
        request_id = context.get("request_id", "")

        system = agents.system_prompt(agent, context)
        messages: list[dict[str, str]] = [
            {"role": "system", "content": system},
            {"role": "user", "content": question},
        ]
        tools_used: list[str] = []
        sub_answer: dict[str, Any] | None = None
        try:
            # One tool round, then one answer call. Counting only *tool*
            # rounds is what lets a data question actually come back: the
            # specialist asks for its governed report, the report runs, and it
            # then answers from it. Capping the total number of provider calls
            # at one instead left every data delegation returning nothing, and
            # the parent simply re-delegated the same question.
            tool_rounds = 0
            while tool_rounds < MAX_DELEGATE_TOOL_ROUNDS:
                response = self.provider.complete(
                    messages, {"response_format": "json_object", "task": "chat"}
                )
                if not isinstance(response, dict):
                    break
                action = str(response.get("action") or "final")
                if action == "tool":
                    requests, _rejected = self._requested_tools(
                        response, _expanded_agent_tools(agent, self.tools)
                    )
                    if not requests:
                        break
                    tool_rounds += 1
                    for tool_id, tool_input in requests:
                        result = self.tools.execute(
                            user_id,
                            permissions,
                            tool_id,
                            tool_input,
                            request_id,
                            roles=context.get("roles"),
                            role_ids=context.get("role_ids"),
                        )
                        tools_used.append(tool_id)
                        messages.append(
                            {
                                "role": "user",
                                "content": self._compact_result(tool_id, result),
                            }
                        )
                    continue
                sub_answer = self._validate_answer(response.get("answer") or response)
                break
        except Exception as error:  # noqa: BLE001 - a delegate must never fail the parent
            return {
                "agent": {"id": agent["id"], "name": agent["name"]},
                "answer": None,
                "reason": f"{type(error).__name__}: {str(error)[:200]}",
            }

        return {
            "agent": {"id": agent["id"], "name": agent["name"]},
            "answer": sub_answer,
            "tools_used": tools_used,
        }

    @staticmethod
    def _delegate_note(sub: dict[str, Any]) -> str:
        """Compact, always-readable summary of one specialist turn."""
        if not isinstance(sub, dict):
            return "the specialist produced no answer"
        answer = sub.get("answer")
        if isinstance(answer, dict):
            body = str(answer.get("body") or "").strip()
            if body:
                return body[:2000]
        reason = str(sub.get("reason") or "").strip()
        if reason:
            return f"the specialist added nothing ({reason[:200]})"
        return "the specialist added nothing new"

    @staticmethod
    def _report_scope_check(tool_id: str, result: Any) -> dict[str, Any] | None:
        """Extract PHP-resolved scope metadata for later task-result checks."""
        if tool_id not in {"reports.nlq", "reports.aggregate", "charts.generate"}:
            return None
        if not isinstance(result, dict) or result.get("status") not in {
            "answered",
            "ok",
            "success",
        }:
            return None
        answer = (
            result.get("answer") if isinstance(result.get("answer"), dict) else result
        )
        scope = answer.get("effective_scope")
        if not isinstance(scope, dict):
            return None
        allowed = (
            "academic_year_id",
            "academic_year_term_id",
            "class_id",
            "stream_id",
            "learning_area_id",
            "user_id",
        )
        normalized: dict[str, Any] = {}
        scope_type = scope.get("type")
        if not isinstance(scope_type, str) or scope_type.strip() not in {
            "school",
            "own",
            "class",
            "learning_area",
        }:
            return None
        normalized["type"] = scope_type.strip()
        for key in allowed:
            value = scope.get(key)
            if isinstance(value, int) and value > 0:
                normalized[key] = value
        intent = result.get("intent")
        report_code = (
            str(intent.get("report_code") or "") if isinstance(intent, dict) else ""
        )
        report_code = report_code or str(answer.get("report_code") or "")
        return {"report_code": report_code[:100], "scope": normalized}

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
    def _tool_succeeded(result: Any) -> bool:
        return isinstance(result, dict) and result.get("status") in {
            "ok",
            "success",
            "answered",
            "ready",
            # public_content.advisor returns advisory content (ideas, drafts,
            # channel guidance) under this status. It is a successful governed
            # result, not a failure, and must be recognised as evidence.
            "advisory",
        }

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
            if field == "rows":
                # Rows are dicts. The generic list filter keeps only scalar
                # items, so a dict row would silently vanish and the model
                # would answer from nothing. Flatten each row to one bounded
                # "column=value" line instead.
                lines = [
                    _flatten_row(row)
                    for row in list(value)[:12]
                    if isinstance(row, dict)
                ]
                lines = [line for line in lines if line]
                if lines:
                    compact["rows"] = lines
                continue
            if isinstance(value, (list, tuple)):
                items = [
                    guard.neutralize_untrusted(str(v), 120)
                    if isinstance(v, str)
                    else str(v)[:120]
                    for v in list(value)[:6]
                    if isinstance(v, (str, int, float))
                ]
                if items:
                    compact[field] = items
            elif isinstance(value, str):
                # Tool output (especially web/fetch/crawl) is untrusted data.
                # Neutralize embedded instructions before it reaches the model.
                compact[field] = guard.neutralize_untrusted(value)
            elif isinstance(value, (int, float, bool)) or value is None:
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
    @staticmethod
    def _rows_answer(result: dict[str, Any]) -> dict[str, Any] | None:
        """Build an answer straight from governed rows, with no model call.

        When the model spends its turn on tools instead of words, the staff
        member must still get the numbers they asked for. The rows are already
        authorised, so they can be rendered directly: a compact table plus the
        report they came from.
        """
        rows = result.get("rows")
        if not isinstance(rows, list) or not rows:
            return None
        clean_rows = [row for row in rows[:12] if isinstance(row, dict)]
        if not clean_rows:
            return None
        columns: list[str] = []
        for row in clean_rows:
            for key in row.keys():
                if key not in columns and len(columns) < 8:
                    columns.append(str(key))
        lines = [
            "| " + " | ".join(columns) + " |",
            "| " + " | ".join("---" for _ in columns) + " |",
        ]
        for row in clean_rows:
            cells = []
            for column in columns:
                value = row.get(column, "")
                if isinstance(value, bool):
                    cells.append("yes" if value else "no")
                elif isinstance(value, (int, float)):
                    cells.append(str(value))
                else:
                    cells.append(str(value)[:60])
            lines.append("| " + " | ".join(cells) + " |")
        title = str(result.get("report_title") or "Governed report").strip()[:120]
        code = str(result.get("report_code") or "").strip()[:60]
        heading = f"{title} ({code})" if code else title
        return {
            "title": heading,
            "body": "\n".join(lines),
            "next_steps": ["Open the governed report for the full result and filters."],
            "suggested_questions": [],
            "escalation_required": False,
        }

    # Tools whose returned figures are legitimate external evidence, not
    # fabricated school data. Used to build the allow-text for the number guard.
    _EXTERNAL_EVIDENCE_TOOLS = frozenset(
        {
            "web.search",
            "web.fetch",
            "web.crawl",
            "education.news",
            "public_content.advisor",
        }
    )

    @classmethod
    def _allowed_evidence_text(cls, question: str, tool_results: list[str]) -> str:
        """Figures the operator asked about or an authorized web tool returned.

        The question itself may quote a target ("reach 90% collection"); a web
        result carries current figures. Neither was invented by the model, so
        the number guard must not treat them as fabricated school records.
        Briefer/staff-notice tool text is deliberately excluded - it is advice,
        not evidence for a data answer.
        """
        parts = [str(question or "")]
        for entry in tool_results:
            if not isinstance(entry, str):
                continue
            head = entry.lstrip()[:120]
            if any(f'"{tool}"' in head for tool in cls._EXTERNAL_EVIDENCE_TOOLS):
                parts.append(entry)
        return " ".join(parts)

    def _guard_grounded_answer(
        self,
        answer: dict[str, Any] | None,
        last_rows_result: dict[str, Any] | None,
        allowed_text: str = "",
        strict_numbers: bool = True,
    ) -> dict[str, Any] | None:
        """Replace an answer whose prose contradicts the rows it was given.

        A live turn named Grade 2 as the highest fee arrears and Grade 7 as the
        lowest for data in which the opposite is true. The model's explanation
        is welcome; its arithmetic is not. When a superlative sentence points at
        the wrong row the deterministic rendering is used instead, so the
        figures on screen are the ones the server actually authorized. The
        model's next steps and follow-up questions are kept - they are advice,
        not data.

        ``allowed_text`` (the operator's question plus any authorized web/news
        results) supplies legitimate figures on a composite question so its
        cited prose is not mistaken for fabricated school data.
        """
        if not isinstance(answer, dict):
            return answer
        rows = (
            last_rows_result.get("rows") if isinstance(last_rows_result, dict) else None
        )
        if not isinstance(rows, list) or not rows:
            return answer
        body = str(answer.get("body") or "")
        dict_rows = [row for row in rows if isinstance(row, dict)]
        # Two distinct guarantees, deliberately separated:
        #   1. A named row must not be misrepresented as the extreme. This is a
        #      factual contradiction and is checked for EVERY answer.
        #   2. A figure must come from the rows - but only for literal report
        #      lookups. A prediction/hypothesis/decision legitimately derives
        #      sums, rates and probabilities that the rows do not contain;
        #      rejecting those turned intelligent answers into a dumb raw table.
        unsupported = (
            _unsupported_numbers(dict_rows, body, allowed_text)
            if strict_numbers
            else []
        )
        if not body or not (_claims_contradict_rows(dict_rows, body) or unsupported):
            return answer
        rendered = self._generate_nlq_answer("", last_rows_result)
        if not rendered:
            return answer
        return {
            **answer,
            "title": str(answer.get("title") or "Governed report"),
            "body": rendered,
        }

    _DATA_TOOLS = frozenset(
        {"data.query", "reports.nlq", "analytics.summary", "data.polars"}
    )

    def _guard_figures_without_evidence(
        self,
        answer: dict[str, Any] | None,
        last_rows_result: dict[str, Any] | None,
        tools_used: list[str],
        allowed_text: str = "",
    ) -> dict[str, Any] | None:
        """Never state a school figure when no data tool returned any rows.

        A live turn asked how many learners graduate; data.query refused the
        dataset name the model invented and the model answered "12" anyway,
        labelled as grounded. With no rows there is nothing to quote, so any
        figure not present in the question or an authorized web result is
        fabricated: replace the answer with an honest one.
        """
        if not isinstance(answer, dict):
            return answer
        if not any(tool in self._DATA_TOOLS for tool in tools_used or []):
            return answer
        rows = (
            last_rows_result.get("rows") if isinstance(last_rows_result, dict) else None
        )
        if isinstance(rows, list) and rows:
            return answer
        body = str(answer.get("body") or "")
        if not _unsupported_numbers([], body, allowed_text):
            return answer
        return {
            **answer,
            "title": "No matching school data found",
            "body": (
                "I could not find school records that answer this question, so I "
                "will not give a figure. The data I can read for your role did not "
                "include it. Open the relevant module report, or ask me to look it "
                "up a different way."
            ),
            "insufficient_data": True,
            "citations": [],
        }

    # Advisory tools whose own returned content is already a complete, useful
    # answer. When the wording model emits a canned refusal or pure narration,
    # we must not throw this content away and show the neutral placeholder.
    _ADVISORY_CONTENT_TOOLS = frozenset(
        {
            "public_content.advisor",
            "education.news",
            "reports.insight_brief",
            "assistant.workspace_scan",
        }
    )

    @staticmethod
    def _advisory_answer_from_result(
        tool_id: str, result: dict[str, Any]
    ) -> dict[str, Any] | None:
        """Render a substantive answer straight from a successful advisory tool.

        The tool has already applied the operator's permissions and neutralized
        untrusted text, so this is safe to show directly. It is the deterministic
        guarantee behind the advisory coworker: even if the model refuses, the
        operator still gets the post ideas, news headlines or workspace review
        the tool actually returned instead of "I couldn't verify that".
        """
        if not isinstance(result, dict):
            return None
        # A denial or an empty result must never be dressed up as an answer.
        status = str(result.get("status") or "")
        if status in {"not_authorized", "unavailable", "error", "invalid_topic"}:
            return None

        lines: list[str] = []
        next_steps: list[str] = []
        if tool_id == "public_content.advisor":
            groups = (
                ("post_ideas", "Post ideas"),
                ("headlines", "Headlines"),
                ("event_ideas", "Event ideas"),
                ("news_headlines", "Education news you can draw on"),
            )
            for key, heading in groups:
                values = [
                    str(v).strip() for v in (result.get(key) or []) if str(v).strip()
                ][:6]
                if values:
                    lines.append(f"**{heading}**")
                    lines.extend(f"- {v}" for v in values)
            draft = str(result.get("draft") or "").strip()
            if draft:
                lines.append("**Draft to review**")
                lines.append(draft[:1500])
            how = [
                str(v).strip() for v in (result.get("how_to") or []) if str(v).strip()
            ][:5]
            next_steps = how
            title = "What to post — ideas and draft"
        elif tool_id == "education.news":
            headlines = [
                str(v).strip()
                for v in (result.get("headlines") or [])
                if str(v).strip()
            ][:8]
            for h in headlines:
                lines.append(f"- {h}")
            title = "What's new in education"
        else:  # reports.insight_brief / assistant.workspace_scan
            summary = str(result.get("summary") or "").strip()
            if summary:
                lines.append(summary[:1500])
            sections = result.get("sections")
            if isinstance(sections, dict) and sections:
                # The scanner returns structured sections (schedule, attention,
                # notifications). Present them as clean, grouped, actionable
                # suggestions rather than dumping the prefixed combined lines.
                section_titles = (
                    ("schedule", "Today's schedule"),
                    ("attention", "Needs your attention"),
                    ("notifications", "Notifications"),
                )
                for key, heading in section_titles:
                    section = sections.get(key)
                    if not isinstance(section, dict):
                        continue
                    values = [
                        str(v).strip()
                        for v in (section.get("items") or section.get("lines") or [])
                        if str(v).strip()
                    ][:6]
                    if values:
                        lines.append(f"**{heading}**")
                        lines.extend(f"- {v}" for v in values)
            if not lines:
                # Fall back to the combined lines when a sectioned shape is not
                # available (e.g. the deterministic insight-brief tool).
                risks = [
                    str(v).strip()
                    for v in (result.get("lines") or result.get("alerts") or [])
                    if str(v).strip()
                ][:8]
                for r in risks:
                    lines.append(f"- {r}")
            title = (
                "Your school briefing"
                if tool_id == "reports.insight_brief"
                else "Your workspace review"
            )

        note = str(result.get("note") or "").strip()
        if note:
            lines.append(note)
        body = "\n".join(line for line in lines if line).strip()
        if not body:
            return None
        return {
            "title": title,
            "body": body[:4000],
            "next_steps": next_steps
            or ["Review this in the relevant school workspace before acting."],
            "suggested_questions": [],
            "escalation_required": False,
        }

    @classmethod
    def _advisory_prefetch_answer(
        cls, prefetched: list[tuple[str, dict[str, Any]]]
    ) -> dict[str, Any] | None:
        """Pick the richest successful advisory result and render it.

        ``prefetched`` is the list of (tool_id, raw_result) pairs gathered for
        this turn. Advisory tools are tried in priority order so a posting
        question surfaces post ideas, while a news question surfaces headlines.
        """
        preferred = (
            "public_content.advisor",
            "assistant.workspace_scan",
            "reports.insight_brief",
            "education.news",
        )
        by_tool = {
            tool_id: result
            for tool_id, result in prefetched
            if tool_id in cls._ADVISORY_CONTENT_TOOLS
        }
        for tool_id in preferred:
            result = by_tool.get(tool_id)
            if result is None:
                continue
            rendered = cls._advisory_answer_from_result(tool_id, result)
            if rendered is not None:
                return rendered
        return None

    def _page_help_answer(
        self, context: dict, agent: dict[str, Any], question: str
    ) -> dict[str, Any]:
        """Deterministic guidance answer for 'what is this page' prompts.

        Used only when the wording model produced nothing usable for a
        page-help question. It reuses the same page_context labels the
        envelope carried - route, open record, selection, visible metrics,
        active filters - so the reply reflects what the operator is actually
        looking at, and it stays honest: it describes the screen, never
        invents figures.
        """
        route = str(context.get("route") or "").strip()
        info = (
            context.get("page_info")
            if isinstance(context.get("page_info"), dict)
            else {}
        )
        catalog_label = str(info.get("label") or "").strip()
        page_label = (
            catalog_label or route.replace("_", " ").strip().title() or "this page"
        )
        components = (
            info.get("components") if isinstance(info.get("components"), list) else []
        )
        context_lines = self._page_context_lines(context)
        goal = str(agent.get("goal") or "").strip()
        agent_name = str(agent.get("name") or "Kingsway Assistant").strip()

        body_parts = [
            f"**{page_label}** is part of the Kingsway school management system, "
            f"and you are working with the {agent_name} - {goal}"
            if goal
            else f"**{page_label}** is part of the Kingsway school management "
            "system, and you are working with the "
            f"{agent_name} for this area."
        ]
        if context_lines:
            body_parts.append(
                "What you have open right now:\n"
                + "\n".join(f"- {line}" for line in context_lines[:8])
            )
        if components:
            body_parts.append(
                "This screen contains: "
                + ", ".join(str(c) for c in components[:10])
                + "."
            )
        body_parts.append(
            "To get the most out of this screen: use the filters to narrow the "
            "records you need, open a row to inspect or update it, and use the "
            "export/print controls where your role allows them. If you need a "
            "figure - totals, trends, comparisons - ask me a specific question "
            "and I will look it up in the authorized school records."
        )
        body = "\n\n".join(body_parts)
        return {
            "title": f"Using {page_label}",
            "body": body,
            "next_steps": [
                "Use the page filters to narrow the records you need.",
                "Ask a specific data question and I will query the authorized records.",
            ],
            "suggested_questions": [
                "What can I do on this page?",
                "Summarise the records currently shown.",
                "Which reports cover this area?",
            ],
            "escalation_required": False,
            "insufficient_data": False,
            "citations": [],
        }

    def _fallback_answer(
        self, tools_used: list[str], tool_results: list[str]
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
