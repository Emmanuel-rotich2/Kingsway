"""ChatGPT-style durable assistant sessions.

Unlike the older rolling ``ConversationStore`` (one implicit thread per user),
staff need named sessions: a sidebar of conversations, each holding many
user/assistant turns. The store is deliberately local, owned by the Python AI
platform, and SQLite-backed so session reads never touch the school's MySQL
primary:

* **Per-user isolation** — every query is keyed by ``user_id``. A session id is
  an unguessable random token; ownership is re-checked on every read/write.
* **Bounded** — sessions expire after ``TTL_SECONDS``; each user keeps at most
  ``MAX_SESSIONS`` sessions and each session at most ``MAX_MESSAGES`` messages,
  each message clipped to ``MAX_MESSAGE_CHARS``.
* **Erasable** — ``forget()`` removes all of a user's sessions and messages.
  This is the DPA erasure path for chat data.
* **Operational journaling only** — the JSONL journal records ids, counts and
  hashes, never message text. Answer content lives only in the erasable SQLite
  store.
* **Advisory only** — prior turns shape the prompt; permissions are always
  re-resolved from the server for each question.
"""

from __future__ import annotations

import json
import sqlite3
import threading
import time
import uuid
from pathlib import Path
from typing import Any

TTL_SECONDS = 30 * 24 * 3600
MAX_SESSIONS = 50
MAX_MESSAGES = 200
MAX_MESSAGE_CHARS = 4000
MAX_TITLE_CHARS = 80
MAX_META_CHARS = 2000
STORE_VERSION = "assistant_chat_v1"

_ROLES = {"user", "assistant"}


class ChatSessionStore:
    """SQLite-backed session + message store, isolated per staff user."""

    def __init__(self, data_dir: Path, journal=None) -> None:
        self._journal = journal
        self._lock = threading.Lock()
        self._db_path = Path(data_dir) / "assistant_chat.sqlite"
        with self._lock:
            self._connect().executescript(
                """
                CREATE TABLE IF NOT EXISTS chat_sessions (
                    id TEXT PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    title TEXT NOT NULL DEFAULT '',
                    route TEXT NOT NULL DEFAULT '',
                    created_at INTEGER NOT NULL,
                    updated_at INTEGER NOT NULL,
                    message_count INTEGER NOT NULL DEFAULT 0
                );
                CREATE INDEX IF NOT EXISTS idx_chat_sessions_user_time
                    ON chat_sessions(user_id, updated_at DESC);
                CREATE TABLE IF NOT EXISTS chat_messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id TEXT NOT NULL,
                    user_id INTEGER NOT NULL,
                    role TEXT NOT NULL,
                    content TEXT NOT NULL,
                    agent_id TEXT NOT NULL DEFAULT '',
                    meta_json TEXT NOT NULL DEFAULT '',
                    created_at INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_chat_messages_session
                    ON chat_messages(session_id, id);
                CREATE TABLE IF NOT EXISTS assistant_prefs (
                    user_id INTEGER PRIMARY KEY,
                    prefs_json TEXT NOT NULL DEFAULT '{}',
                    updated_at INTEGER NOT NULL
                );
                """
            )

    def _connect(self) -> sqlite3.Connection:
        connection = sqlite3.connect(str(self._db_path), timeout=3)
        connection.row_factory = sqlite3.Row
        connection.execute("PRAGMA busy_timeout = 3000")
        connection.execute("PRAGMA journal_mode = WAL")
        return connection

    # ---------------------------------------------------------------- sessions
    def create(self, user_id: int, route: str = "", title: str = "") -> dict[str, Any]:
        now = int(time.time())
        session_id = uuid.uuid4().hex
        try:
            with self._lock:
                connection = self._connect()
                connection.execute(
                    "INSERT INTO chat_sessions"
                    " (id, user_id, title, route, created_at, updated_at, message_count)"
                    " VALUES (?, ?, ?, ?, ?, ?, 0)",
                    (
                        session_id,
                        int(user_id),
                        self._clean_title(title),
                        str(route or "")[:120],
                        now,
                        now,
                    ),
                )
                connection.execute(
                    "DELETE FROM chat_sessions WHERE user_id = ? AND id NOT IN ("
                    "  SELECT id FROM chat_sessions WHERE user_id = ?"
                    "  ORDER BY updated_at DESC LIMIT ?"
                    ")",
                    (int(user_id), int(user_id), MAX_SESSIONS),
                )
                connection.commit()
            self._journal_event(
                user_id, "assistant_session_created", {"session_id": session_id[:12]}
            )
            return self._session_row(
                {
                    "id": session_id,
                    "user_id": int(user_id),
                    "title": self._clean_title(title),
                    "route": str(route or "")[:120],
                    "created_at": now,
                    "updated_at": now,
                    "message_count": 0,
                }
            )
        except Exception:
            self._journal_event(user_id, "assistant_session_write_failed", {})
            return {}

    def list_sessions(self, user_id: int, limit: int = 30) -> list[dict[str, Any]]:
        limit = max(1, min(int(limit or 30), MAX_SESSIONS))
        try:
            with self._lock:
                connection = self._connect()
                rows = connection.execute(
                    "SELECT id, user_id, title, route, created_at, updated_at, message_count"
                    " FROM chat_sessions"
                    " WHERE user_id = ? AND updated_at >= ?"
                    " ORDER BY updated_at DESC LIMIT ?",
                    (int(user_id), int(time.time()) - TTL_SECONDS, limit),
                ).fetchall()
            return [self._session_row(row) for row in rows]
        except Exception:
            return []

    def get_session(
        self, user_id: int, session_id: str, limit: int = MAX_MESSAGES
    ) -> dict[str, Any] | None:
        session_id = str(session_id or "").strip()
        if not session_id:
            return None
        try:
            with self._lock:
                connection = self._connect()
                row = connection.execute(
                    "SELECT id, user_id, title, route, created_at, updated_at, message_count"
                    " FROM chat_sessions WHERE id = ? AND user_id = ?",
                    (session_id, int(user_id)),
                ).fetchone()
                if row is None:
                    return None
                limit = max(1, min(int(limit or MAX_MESSAGES), MAX_MESSAGES))
                messages = connection.execute(
                    "SELECT id, role, content, agent_id, meta_json, created_at"
                    " FROM chat_messages WHERE session_id = ? AND user_id = ?"
                    " ORDER BY id ASC LIMIT ?",
                    (session_id, int(user_id), limit),
                ).fetchall()
            payload = self._session_row(row)
            payload["messages"] = [
                {
                    "id": int(message["id"]),
                    "role": str(message["role"]),
                    "content": str(message["content"]),
                    "agent_id": str(message["agent_id"] or ""),
                    "meta": self._load_meta(message["meta_json"]),
                    "created_at": int(message["created_at"]),
                }
                for message in messages
            ]
            return payload
        except Exception:
            return None

    def append(
        self,
        user_id: int,
        session_id: str,
        role: str,
        content: str,
        agent_id: str = "",
        meta: dict[str, Any] | None = None,
    ) -> int | None:
        role = str(role or "").strip().lower()
        content = self._clip(content, MAX_MESSAGE_CHARS)
        session_id = str(session_id or "").strip()
        if role not in _ROLES or not content or not session_id:
            return None
        now = int(time.time())
        meta_json = ""
        if isinstance(meta, dict) and meta:
            try:
                meta_json = json.dumps(meta, ensure_ascii=False)[:MAX_META_CHARS]
            except Exception:
                meta_json = ""
        try:
            with self._lock:
                connection = self._connect()
                owned = connection.execute(
                    "SELECT id FROM chat_sessions WHERE id = ? AND user_id = ?",
                    (session_id, int(user_id)),
                ).fetchone()
                if owned is None:
                    return None
                cursor = connection.execute(
                    "INSERT INTO chat_messages"
                    " (session_id, user_id, role, content, agent_id, meta_json, created_at)"
                    " VALUES (?, ?, ?, ?, ?, ?, ?)",
                    (
                        session_id,
                        int(user_id),
                        role,
                        content,
                        str(agent_id or "")[:100],
                        meta_json,
                        now,
                    ),
                )
                message_id = int(cursor.lastrowid or 0)
                title_update = ""
                if role == "user":
                    current = connection.execute(
                        "SELECT title FROM chat_sessions WHERE id = ?",
                        (session_id,),
                    ).fetchone()
                    if current is not None and not str(current["title"] or "").strip():
                        title_update = ", title = ?"
                connection.execute(
                    "UPDATE chat_sessions SET updated_at = ?,"
                    " message_count = (SELECT COUNT(*) FROM chat_messages WHERE session_id = ?)"
                    + title_update
                    + " WHERE id = ? AND user_id = ?",
                    (
                        now,
                        session_id,
                        *([self._clean_title(content)] if title_update else []),
                        session_id,
                        int(user_id),
                    ),
                )
                # Bound the thread: keep the newest MAX_MESSAGES messages.
                connection.execute(
                    "DELETE FROM chat_messages WHERE session_id = ? AND id NOT IN ("
                    "  SELECT id FROM chat_messages WHERE session_id = ?"
                    "  ORDER BY id DESC LIMIT ?"
                    ")",
                    (session_id, session_id, MAX_MESSAGES),
                )
                connection.commit()
            return message_id
        except Exception:
            self._journal_event(
                user_id,
                "assistant_message_write_failed",
                {"session_id": session_id[:12]},
            )
            return None

    def rename(self, user_id: int, session_id: str, title: str) -> bool:
        clean = self._clean_title(title)
        if not clean:
            return False
        try:
            with self._lock:
                connection = self._connect()
                cursor = connection.execute(
                    "UPDATE chat_sessions SET title = ?, updated_at = ?"
                    " WHERE id = ? AND user_id = ?",
                    (clean, int(time.time()), str(session_id), int(user_id)),
                )
                connection.commit()
            return bool(cursor.rowcount)
        except Exception:
            return False

    def delete_session(self, user_id: int, session_id: str) -> bool:
        try:
            with self._lock:
                connection = self._connect()
                owned = connection.execute(
                    "SELECT id FROM chat_sessions WHERE id = ? AND user_id = ?",
                    (str(session_id), int(user_id)),
                ).fetchone()
                if owned is None:
                    return False
                connection.execute(
                    "DELETE FROM chat_messages WHERE session_id = ? AND user_id = ?",
                    (str(session_id), int(user_id)),
                )
                cursor = connection.execute(
                    "DELETE FROM chat_sessions WHERE id = ? AND user_id = ?",
                    (str(session_id), int(user_id)),
                )
                connection.commit()
            self._journal_event(
                user_id,
                "assistant_session_deleted",
                {"session_id": str(session_id)[:12]},
            )
            return bool(cursor.rowcount)
        except Exception:
            return False

    def clear(self, user_id: int) -> int:
        """Erase every session and message for one user (DPA erasure)."""
        try:
            with self._lock:
                connection = self._connect()
                connection.execute(
                    "DELETE FROM chat_messages WHERE user_id = ?", (int(user_id),)
                )
                cursor = connection.execute(
                    "DELETE FROM chat_sessions WHERE user_id = ?", (int(user_id),)
                )
                connection.execute(
                    "DELETE FROM assistant_prefs WHERE user_id = ?", (int(user_id),)
                )
                connection.commit()
                removed = int(cursor.rowcount or 0)
            self._journal_event(
                user_id,
                "assistant_sessions_forgotten",
                {"sessions_removed": removed, "store": STORE_VERSION},
            )
            return removed
        except Exception:
            return 0

    # ----------------------------------------------------------------- history
    def conversation(
        self, user_id: int, session_id: str, max_turns: int = 6
    ) -> list[str]:
        """Prior turns flattened to scalar "Q: ... / A: ..." strings.

        The prompt policy accepts only scalar arrays, which keeps arbitrary
        nested content out of the provider envelope. Only the current session
        is used; a new chat starts clean.
        """
        session = self.get_session(user_id, session_id)
        if not session:
            return []
        messages = session.get("messages") or []
        turns: list[str] = []
        pending_question = ""
        for message in messages[-max(1, max_turns * 2) :]:
            role = str(message.get("role") or "")
            content = str(message.get("content") or "").strip()[:240]
            if role == "user":
                pending_question = content
            elif role == "assistant" and pending_question:
                turns.append(f"Q: {pending_question} / A: {content}")
                pending_question = ""
        if pending_question:
            turns.append(f"Q: {pending_question}")
        return turns[-max(1, max_turns) :]

    # --------------------------------------------------------------- settings
    def get_prefs(self, user_id: int) -> dict[str, Any]:
        try:
            with self._lock:
                connection = self._connect()
                row = connection.execute(
                    "SELECT prefs_json FROM assistant_prefs WHERE user_id = ?",
                    (int(user_id),),
                ).fetchone()
            if row is None:
                return {}
            data = json.loads(str(row["prefs_json"] or "{}"))
            return data if isinstance(data, dict) else {}
        except Exception:
            return {}

    def set_prefs(self, user_id: int, prefs: dict[str, Any]) -> dict[str, Any]:
        allowed = {
            "save_history": bool,
            "response_style": str,
            "auto_charts": bool,
            "language": str,
        }
        clean: dict[str, Any] = {}
        for key, expected in allowed.items():
            if key not in (prefs or {}):
                continue
            value = prefs[key]
            if expected is bool:
                clean[key] = bool(value)
            else:
                text = str(value or "").strip()[:40]
                if text:
                    clean[key] = text
        try:
            with self._lock:
                connection = self._connect()
                connection.execute(
                    "INSERT INTO assistant_prefs (user_id, prefs_json, updated_at)"
                    " VALUES (?, ?, ?)"
                    " ON CONFLICT(user_id) DO UPDATE SET"
                    " prefs_json = excluded.prefs_json, updated_at = excluded.updated_at",
                    (
                        int(user_id),
                        json.dumps(clean, ensure_ascii=False),
                        int(time.time()),
                    ),
                )
                connection.commit()
            return clean
        except Exception:
            return {}

    # ------------------------------------------------------------------ upkeep
    def purge_expired(self) -> int:
        cutoff = int(time.time()) - TTL_SECONDS
        try:
            with self._lock:
                connection = self._connect()
                expired = connection.execute(
                    "SELECT id FROM chat_sessions WHERE updated_at < ?", (cutoff,)
                ).fetchall()
                ids = [str(row["id"]) for row in expired]
                for session_id in ids:
                    connection.execute(
                        "DELETE FROM chat_messages WHERE session_id = ?", (session_id,)
                    )
                cursor = connection.execute(
                    "DELETE FROM chat_sessions WHERE updated_at < ?", (cutoff,)
                )
                connection.commit()
                return int(cursor.rowcount or 0)
        except Exception:
            return 0

    # ----------------------------------------------------------------- helpers
    @staticmethod
    def _session_row(row: Any) -> dict[str, Any]:
        return {
            "id": str(row["id"]),
            "title": str(row["title"] or ""),
            "route": str(row["route"] or ""),
            "created_at": int(row["created_at"]),
            "updated_at": int(row["updated_at"]),
            "message_count": int(row["message_count"] or 0),
        }

    @staticmethod
    def _load_meta(raw: Any) -> dict[str, Any]:
        try:
            data = json.loads(str(raw or "{}"))
            return data if isinstance(data, dict) else {}
        except Exception:
            return {}

    @staticmethod
    def _clean_title(value: Any) -> str:
        text = " ".join(str(value or "").split())
        return text[:MAX_TITLE_CHARS]

    @staticmethod
    def _clip(value: Any, limit: int = MAX_MESSAGE_CHARS) -> str:
        return str(value or "").strip()[:limit]

    def _journal_event(self, user_id: int, event: str, extra: dict[str, Any]) -> None:
        if self._journal is None:
            return
        try:
            self._journal.write(
                "ai_generation",
                {
                    "type": event,
                    "operator_id": int(user_id),
                    "store": STORE_VERSION,
                    **extra,
                },
            )
        except Exception:
            pass
