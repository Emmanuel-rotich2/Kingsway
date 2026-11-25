"""Bearer (HMAC-compared) service authentication and payload bounds.

Only the PHP edge calls this service, always over HTTPS with the shared
secret; the service is never browser-facing and carries no human sessions.
"""

from __future__ import annotations

import hmac
import re

MAX_QUESTION_CHARS = 500
MAX_PAYLOAD_BYTES = 200_000

_SESSION_ID_RE = re.compile(r"^[a-f0-9]{8,64}$")


def _bounded_label(value: object, limit: int) -> str:
    """Short scalar label only; page hints must never become a data path."""
    if isinstance(value, (str, int, float)):
        return str(value).strip()[:limit]
    return ""


def _bounded_label_map(
    value: object, keys: tuple[str, ...], limit: int = 80
) -> dict[str, str]:
    if not isinstance(value, dict):
        return {}
    out: dict[str, str] = {}
    for key in keys:
        label = _bounded_label(value.get(key), limit)
        if label:
            out[key] = label
    return out


def _bounded_label_list(value: object, limit: int = 60) -> list[str]:
    if not isinstance(value, list):
        return []
    return [
        label for label in (_bounded_label(item, limit) for item in value[:10]) if label
    ]


def _bounded_filter_map(value: object) -> dict[str, str]:
    if not isinstance(value, dict):
        return {}
    out: dict[str, str] = {}
    for key, raw in list(value.items())[:10]:
        label = _bounded_label(raw, 60)
        if label:
            out[str(key)[:40]] = label
    return out


def bearer_authorized(headers, secret: str) -> bool:
    if not secret:
        return False
    auth = headers.get("Authorization", "")
    if not auth.startswith("Bearer "):
        return False
    return hmac.compare_digest(auth[len("Bearer ") :].encode(), secret.encode())


def ensure_staff_context(context: dict) -> dict:
    """Validate and bound the operator envelope relayed by PHP."""
    if not isinstance(context, dict):
        raise ValueError("context must be an object")
    user_id = int(context.get("user_id") or 0)
    if user_id < 1:
        raise PermissionError("an authenticated staff member is required")
    audience = str(context.get("audience") or "staff")
    if audience != "staff":
        raise PermissionError("this assistant only serves staff")
    permissions = [
        str(p) for p in (context.get("permissions") or []) if isinstance(p, (str, int))
    ]
    session_id = str(context.get("session_id") or "")
    if session_id and not _SESSION_ID_RE.match(session_id):
        session_id = ""
    user_name = str(context.get("user_name") or "").strip()
    roles = context.get("roles")
    role_labels = []
    if isinstance(roles, list):
        for role in roles:
            if isinstance(role, dict):
                label = next(
                    (
                        role.get(key)
                        for key in ("name", "role_name", "label", "slug", "code")
                        if role.get(key)
                    ),
                    "",
                )
            else:
                label = role
            if isinstance(label, (str, int)) and str(label).strip():
                role_labels.append(str(label).strip()[:80])
    if not role_labels:
        role_labels = ["Staff"]
    scope_labels = context.get("scope_labels")
    if isinstance(scope_labels, list):
        scope_labels = [str(s).strip() for s in scope_labels if str(s).strip()]
    if not scope_labels:
        _humanized = []
        for p in context.get("permissions") or []:
            s = str(p)
            t = re.sub(r"[^a-zA-Z0-9]", " ", s).strip()
            if t:
                _humanized.append(" ".join(w.capitalize() for w in t.split()))
        scope_labels = _humanized
    normalized_roles = {
        re.sub(r"[^a-z0-9]+", " ", label.lower()).strip() for label in role_labels
    }
    is_system_admin = "system_admin" in permissions
    # Role labels select advisory tone only. The system administrator
    # capability includes operational diagnostics, so require the effective
    # server-resolved permission; a label must never grant that surface.
    if is_system_admin:
        assistant_profile = "system_admin"
    elif normalized_roles & {"director", "director owner"}:
        assistant_profile = "director"
    elif normalized_roles & {"school administrator", "school admin"}:
        assistant_profile = "school_administrator"
    else:
        assistant_profile = "staff"
    requested_domain = str(context.get("assistant_domain") or "staff").lower()
    assistant_domain = (
        "system_admin"
        if requested_domain == "system_admin" and is_system_admin
        else "staff"
    )
    return {
        "user_id": user_id,
        "permissions": sorted(set(permissions)),
        "audience": "staff",
        "route": str(context.get("route") or "")[:120],
        "module": str(context.get("module") or "dashboard")[:60],
        "request_id": str(context.get("request_id") or "ai-python")[:100],
        "session_id": session_id,
        "user_name": user_name[:120],
        "roles": role_labels[:10],
        "scope_labels": scope_labels[:40],
        "system_admin": bool(context.get("system_admin")) and is_system_admin,
        "assistant_domain": assistant_domain,
        "assistant_profile": assistant_profile,
        # Bounded page hints from the PHP edge. They are labels the operator can
        # already see, never a second route to school data: the governed tools
        # remain the only source of figures.
        "page_state": _bounded_label(context.get("page_state"), 60),
        "page_entity": _bounded_label_map(
            context.get("page_entity"), ("kind", "label", "name", "id", "status")
        ),
        "selection": _bounded_label_map(
            context.get("selection"), ("label", "text"), 200
        ),
        "modal": _bounded_label(context.get("modal"), 100),
        "visible_metrics": _bounded_label_list(context.get("visible_metrics")),
        "filters": _bounded_filter_map(context.get("filters")),
        "page_info": {
            **_bounded_label_map(
                context.get("page_info"), ("route", "label", "domain", "status"), 120
            ),
            "components": _bounded_label_list(
                (context.get("page_info") or {}).get("components")
                if isinstance(context.get("page_info"), dict)
                else None,
                40,
            ),
        },
    }


def bound_question(question) -> str:
    if not isinstance(question, str):
        raise ValueError("question must be a string")
    question = question.strip()
    if not question or len(question) > MAX_QUESTION_CHARS:
        raise ValueError("a question of up to 500 characters is required")
    return question
