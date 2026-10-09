"""Bearer (HMAC-compared) service authentication and payload bounds.

Only the PHP edge calls this service, always over HTTPS with the shared
secret; the service is never browser-facing and carries no human sessions.
"""

from __future__ import annotations

import hmac

MAX_QUESTION_CHARS = 500
MAX_PAYLOAD_BYTES = 200_000


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
    return {
        "user_id": user_id,
        "permissions": sorted(set(permissions)),
        "audience": "staff",
        "route": str(context.get("route") or "")[:120],
        "module": str(context.get("module") or "dashboard")[:60],
        "request_id": str(context.get("request_id") or "ai-python")[:100],
    }


def bound_question(question) -> str:
    if not isinstance(question, str):
        raise ValueError("question must be a string")
    question = question.strip()
    if not question or len(question) > MAX_QUESTION_CHARS:
        raise ValueError("a question of up to 500 characters is required")
    return question
