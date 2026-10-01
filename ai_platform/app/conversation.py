"""Durable, per-user assistant conversation history.

Staff expect the assistant to remember the thread they are on ("summarise it",
"make that shorter"), so turns are persisted rather than kept in the browser.
The rules that make this safe are all enforced here:

* **Per-user isolation** — every read and write is keyed by ``user_id``; one
  staff member can never see another's thread.
* **Per-route scoping** — the dashboard thread is not the parent-portal thread,
  so turns are additionally partitioned by route.
* **Bounded** — only the last ``MAX_TURNS`` turns per user are retained, each
  turn has a hard character cap, and rows older than ``TTL_SECONDS`` expire.
* **Erasable** — ``forget()`` removes every stored turn for a user, which is
  the DPA erasure path alongside the behaviour study.
* **Advisory only** — history shapes the prompt; it is never treated as a
  source of authority. Permissions are always re-resolved from the server.

Only the question and the assistant's own answer text are stored. Tool
results, learner identifiers, and any governed aggregate are deliberately not
persisted: history is a convenience, not a second data store.
"""

from __future__ import annotations

import sqlite3
import threading
import time
from pathlib import Path
from typing import Any

TTL_SECONDS = 14 * 24 * 3600
MAX_TURNS = 12
MAX_TURN_CHARS = 1200
STORE_VERSION = "conversation_v1"


class ConversationStore:
    """SQLite-backed turn history, one row per (user, turn)."""

    def __init__(self, data_dir: Path, journal=None) -> None:
        self._journal = journal
        self._lock = threading.Lock()
        self._db_path = Path(data_dir) / "conversation.sqlite"
        with self._lock:
            self._connect().executescript(
                """
                CREATE TABLE IF NOT EXISTS conversation_turns (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    route TEXT NOT NULL DEFAULT '',
                    created_at INTEGER NOT NULL,
                    question TEXT NOT NULL,
                    answer TEXT NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_conversation_user_route_time
                    ON conversation_turns(user_id, route, created_at);
                """
            )

    def _connect(self) -> sqlite3.Connection:
        connection = sqlite3.connect(str(self._db_path), timeout=3)
        connection.execute("PRAGMA busy_timeout = 3000")
        connection.execute("PRAGMA journal_mode = WAL")
        return connection

    # ------------------------------------------------------------------ write
    def record(self, user_id: int, route: str, question: str, answer: str) -> bool:
        question = self._clip(question)
        answer = self._clip(answer)
        if not question or not answer:
            return False
        try:
            with self._lock:
                connection = self._connect()
                connection.execute(
                    "INSERT INTO conversation_turns"
                    " (user_id, route, created_at, question, answer)"
                    " VALUES (?, ?, ?, ?, ?)",
                    (
                        int(user_id),
                        str(route or ""),
                        int(time.time()),
                        question,
                        answer,
                    ),
                )
                # Bound storage: keep only the newest MAX_TURNS turns for this
                # user, across their routes.
                connection.execute(
                    "DELETE FROM conversation_turns WHERE user_id = ? AND id NOT IN ("
                    "  SELECT id FROM conversation_turns WHERE user_id = ?"
                    "  ORDER BY id DESC LIMIT ?"
                    ")",
                    (int(user_id), int(user_id), MAX_TURNS),
                )
                connection.commit()
            return True
        except Exception:
            # History is a convenience: never fail an answer over it.
            if self._journal is not None:
                self._journal.write(
                    "ai_generation",
                    {
                        "type": "conversation_write_failed",
                        "operator_id": user_id,
                        "store": STORE_VERSION,
                    },
                )
            return False

    # ------------------------------------------------------------------- read
    def turns(self, user_id: int, route: str = "") -> list[dict[str, str]]:
        """Recent turns for this operator, oldest first.

        Turns from the requested route are preferred; when the operator has no
        turns there yet the most recent turns overall are used so a thread that
        moves between pages is not silently forgotten.
        """
        try:
            with self._lock:
                connection = self._connect()
                rows = connection.execute(
                    "SELECT route, question, answer, created_at"
                    " FROM conversation_turns"
                    " WHERE user_id = ? AND created_at >= ?"
                    " ORDER BY id DESC LIMIT ?",
                    (int(user_id), int(time.time()) - TTL_SECONDS, MAX_TURNS),
                ).fetchall()
        except Exception:
            return []
        if not rows:
            return []
        scoped = [row for row in rows if row[0] == str(route or "")]
        chosen = scoped or list(reversed(rows))
        if not scoped:
            # Different route: only the most recent handful, so unrelated
            # threads do not crowd out the current one.
            chosen = list(reversed(rows))[-4:]
        history: list[dict[str, str]] = []
        for row in reversed(chosen):
            history.append(
                {
                    "question": self._clip(row[1]),
                    "answer": self._clip(row[2]),
                }
            )
        return history[-MAX_TURNS:]

    # ----------------------------------------------------------------- erase
    def forget(self, user_id: int) -> int:
        """Erase every stored turn for one operator. Returns rows removed."""
        try:
            with self._lock:
                connection = self._connect()
                cursor = connection.execute(
                    "DELETE FROM conversation_turns WHERE user_id = ?", (int(user_id),)
                )
                connection.commit()
                removed = int(cursor.rowcount or 0)
            if self._journal is not None:
                self._journal.write(
                    "ai_behavior",
                    {
                        "type": "conversation_forgotten",
                        "operator_id": user_id,
                        "turns_removed": removed,
                        "store": STORE_VERSION,
                    },
                )
            return removed
        except Exception:
            return 0

    def purge_expired(self) -> int:
        try:
            with self._lock:
                connection = self._connect()
                cursor = connection.execute(
                    "DELETE FROM conversation_turns WHERE created_at < ?",
                    (int(time.time()) - TTL_SECONDS,),
                )
                connection.commit()
                return int(cursor.rowcount or 0)
        except Exception:
            return 0

    @staticmethod
    def _clip(value: Any) -> str:
        text = str(value or "").strip()
        return text[:MAX_TURN_CHARS]
