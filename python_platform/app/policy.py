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
}

MAX_CONTEXT_BYTES = 10_000
MAX_ARRAY_ITEMS = 20
MAX_SCALAR_CHARS = 500


class PolicyError(ValueError):
    pass


def _clean_scalar(value: Any, depth: int = 0) -> str | int | float | bool | None:
    if value is None:
        return None
    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return value
    if isinstance(value, str):
        return value.strip()[:MAX_SCALAR_CHARS]
    if depth == 0 and isinstance(value, (list, tuple)):
        raise PolicyError("nested arrays are not allowed in prompt fields")
    raise PolicyError("unsupported value in prompt field")


def minimize(workflow: str, payload: dict[str, Any]) -> dict[str, Any]:
    allowlist = FIELDS.get(workflow)
    if allowlist is None:
        raise PolicyError(f"workflow {workflow} has no approved prompt policy")
    clean: dict[str, Any] = {}
    for field in allowlist:
        if field not in payload:
            continue
        value = payload[field]
        if isinstance(value, (list, tuple)):
            items: list[Any] = []
            for item in list(value)[:MAX_ARRAY_ITEMS]:
                if isinstance(item, (list, tuple, dict)):
                    raise PolicyError(f"field {field} contains unsupported nested data")
                items.append(_clean_scalar(item, depth=0))
            if items:
                clean[field] = items
        elif value is not None:
            clean[field] = _clean_scalar(value)
    if len(json.dumps(clean, ensure_ascii=False).encode("utf-8")) > MAX_CONTEXT_BYTES:
        raise PolicyError("prompt context exceeds the approved context limit")
    return clean
