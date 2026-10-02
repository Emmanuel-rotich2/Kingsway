"""Output confidentiality guard for model responses.

`guard.py` refuses a *question* that probes confidential internals. This module
covers the other direction: a model can be induced to volunteer internal detail
that was never asked for, or simply narrate the machinery it was given.

It mirrors `App\\API\\Services\\AiOutputGuard` on the PHP edge so both engines
apply the same policy, and is applied at the single funnel where every model
answer is validated (`Orchestrator._validate_answer`).

Design rules:
  - Only high-signal, unambiguous markers are removed. Ordinary school
    vocabulary ("select", "create", "update", "from the class list") must
    survive: the co-worker has to stay useful and conversational, and a filter
    that mangles CBC and operations language would be worse than none.
  - Replacements are readable phrases rather than blanks, so sentences stay
    coherent for the reader.
  - Recursion is bounded (depth, node count, string length) so a large or
    malformed payload cannot exhaust memory.
  - Keys are scrubbed as well as values: a model can disclose a source by
    naming it in an object key.
"""

from __future__ import annotations

import re
from typing import Any

MAX_DEPTH = 12
MAX_NODES = 5000
MAX_STRING = 20000

# Prefixes this schema uses for database objects. They never occur in ordinary
# school prose, so they are safe to redact without needing context.
OBJECT_PREFIXES = ("vw_", "mmv_", "mv_", "sp_", "fn_", "trg_")

_OBJECT_RE = re.compile(r"\b(?:%s)[A-Za-z0-9_]+\b" % "|".join(OBJECT_PREFIXES))
# Governed registry report codes, e.g. FIN_FEE_SUMMARY_CLASS: uppercase
# identifiers built from two or more underscore segments.
_REGISTRY_CODE_RE = re.compile(r"\b[A-Z]{2,}(?:_[A-Z0-9]+){2,}\b")

_PATTERNS: tuple[tuple[re.Pattern[str], str], ...] = (
    # Credentials and secrets.
    (re.compile(r"\bsk-[A-Za-z0-9_\-]{16,}\b"), "[redacted credential]"),
    (
        re.compile(r"\beyJ[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{6,}"),
        "[redacted token]",
    ),
    (re.compile(r"\bBearer\s+[A-Za-z0-9._~+/-]{16,}=*"), "Bearer [redacted]"),
    (
        re.compile(
            r"\b(?:mysql|mariadb|pgsql|postgres|sqlserver|redis|mongodb)://[^\s\"']+",
            re.I,
        ),
        "[redacted connection]",
    ),
    (
        re.compile(
            r"\b(?:[A-Za-z0-9_.\-]*[_.\-])?(?:password|passwd|secret|api[_-]?key|"
            r"auth[_-]?token|access[_-]?token|client[_-]?secret)\b(\s*[:=]\s*)(\S{4,})",
            re.I,
        ),
        r"\1[redacted]",
    ),
    # Filesystem paths and code file names.
    (re.compile(r"\b[A-Za-z]:\\[^\s\"'<>|]+"), "[redacted path]"),
    (
        re.compile(
            r"(?<![\w/])(?:/(?:home|var|etc|usr|opt|srv|www|root|tmp|storage|logs|mnt|app)/)"
            r"[^\s\"'`,;)\]]+"
        ),
        "[redacted path]",
    ),
    (
        re.compile(
            r"\b(?:api|js|pages|components|layouts|database|vendor|config|includes|tests|scripts|ai_platform)"
            r"/[\w./\-]+\.(?:php|js|sql|json|py|env|ini|yml|yaml)\b"
        ),
        "[redacted file]",
    ),
    (re.compile(r"\b[\w\-]+\.(?:php|sql|env)\b"), "[redacted file]"),
    # Network and deployment detail.
    (re.compile(r"\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b"), "[redacted host]"),
    (re.compile(r"\blocalhost:\d+\b"), "[redacted host]"),
    (
        re.compile(
            r"\b[a-z0-9\-]+\.(?:kingswaypreparatoryschool\.sc\.ke|hostafrica\.com|"
            r"ngrok\.io|trycloudflare\.com)\b",
            re.I,
        ),
        "[redacted host]",
    ),
    # Explicit source citation phrasing, which is not allowed on screen or in a
    # conversational answer.
    (
        re.compile(
            r"\b(?:Source|Data source|Source view|Source table|Backed by|"
            r"Pulled from|Fetched from)\s*:[^\n.;]{0,120}",
            re.I,
        ),
        "Based on school records",
    ),
)

# SQL detection is structural, never a bare keyword match. "Select a class from
# the list" is ordinary school language and must survive, so a SELECT/FROM pair
# is only treated as a query when it also carries an unmistakable SQL marker:
# a terminating semicolon, a quoted identifier, a snake_case table name, or a
# projection wildcard. Without this, every answer mentioning "from" is redacted.
_SQL_CANDIDATE_RE = re.compile(r"\bSELECT\b[^;\n]{0,200}?\bFROM\b[^;\n]{0,200};?", re.I)
_SQL_MARKER_RE = re.compile(r"(?:;|`[^`]+`|\bFROM\s+[A-Za-z_]\w*_\w+|\*)", re.I)
# DDL/DML requires an uppercase verb or a terminating semicolon, so prose such
# as "insert into the register" is left alone.
_DDL_RE = re.compile(
    r"\b(?:INSERT\s+INTO|DELETE\s+FROM|DROP\s+TABLE|ALTER\s+TABLE|"
    r"TRUNCATE\s+TABLE|UPDATE\s+[a-z_]+\s+SET)\b[^;\n]{0,200}?(?:;|$)",
    re.I,
)


def _scrub_sql(text: str) -> str:
    """Redact only text that is structurally a SQL statement."""

    def _candidate(match: re.Match[str]) -> str:
        if _SQL_MARKER_RE.search(match.group(0)):
            return "[redacted query]"
        return match.group(0)

    def _ddl(match: re.Match[str]) -> str:
        verb = match.group(0).split()[0]
        if verb.isupper() or match.group(0).rstrip().endswith(";"):
            return "[redacted query]"
        return match.group(0)

    text = _SQL_CANDIDATE_RE.sub(_candidate, text)
    return _DDL_RE.sub(_ddl, text)


def scrub_text(text: str) -> str:
    """Remove internal markers and secret material from one string."""
    if not isinstance(text, str) or not text:
        return text
    if len(text) > MAX_STRING:
        text = text[:MAX_STRING] + "…"

    text = _OBJECT_RE.sub("school data", text)
    text = _REGISTRY_CODE_RE.sub("the school report", text)
    for pattern, replacement in _PATTERNS:
        text = pattern.sub(replacement, text)
    return _scrub_sql(text)


def scrub(value: Any, _depth: int = 0) -> Any:
    """Scrub a decoded model payload, preserving its shape.

    Keys are scrubbed alongside values because a model can disclose a source by
    naming it in an object key.
    """
    if isinstance(value, str):
        return scrub_text(value)
    if not isinstance(value, (dict, list)):
        return value
    if _depth >= MAX_DEPTH:
        return (
            {"_truncated": "nested response truncated"}
            if isinstance(value, dict)
            else ["[truncated]"]
        )
    if _depth + 1 > MAX_NODES:
        return "[truncated]"

    if isinstance(value, list):
        return [scrub(item, _depth + 1) for item in value]

    out: dict[str, Any] = {}
    for key, item in value.items():
        clean_key = scrub_text(str(key))
        if clean_key in out:
            clean_key += "_"
        out[clean_key] = scrub(item, _depth + 1)
    return out


def contains_internal_reference(value: Any) -> bool:
    """True when a payload still carries internal disclosure.

    Used by tests so the guard cannot silently fail open.
    """
    if isinstance(value, str):
        haystack = [value]
    elif isinstance(value, dict):
        haystack = [str(k) for k in value] + [
            v for v in value.values() if isinstance(v, str)
        ]
    elif isinstance(value, list):
        haystack = [v for v in value if isinstance(v, str)]
    else:
        return False

    for text in haystack:
        if _OBJECT_RE.search(text) or _REGISTRY_CODE_RE.search(text):
            return True
        for pattern, _ in _PATTERNS:
            if pattern.search(text):
                return True
    return False
