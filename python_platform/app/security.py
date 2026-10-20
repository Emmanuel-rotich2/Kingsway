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
        role_labels = [str(r).strip() for r in roles if str(r).strip()]
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
    }


def bound_question(question) -> str:
    if not isinstance(question, str):
        raise ValueError("question must be a string")
    question = question.strip()
    if not question or len(question) > MAX_QUESTION_CHARS:
        raise ValueError("a question of up to 500 characters is required")
    return question
