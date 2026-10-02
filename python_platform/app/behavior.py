"""Persistent staff behaviour study (SQLite) for agent personalization.

Same contract as the PHP profiler: rolling 120-event window, 30-day TTL,
allowlisted non-sensitive facts only, per-user isolation, DPA erasure path.
Storage is a single SQLite file under the app data directory.
"""

from __future__ import annotations

import json
import sqlite3
import threading
import time
from pathlib import Path
from typing import Any

TTL_SECONDS = 30 * 24 * 3600
MAX_EVENTS = 120
FIELD_ALLOWLIST = (
    "route",
    "module",
    "domain",
    "agent_id",
    "tool",
    "workflow_id",
    "outcome",
    "cadence",
)
EVENT_TYPES = ("question", "assist_outcome", "tool_use", "workspace_visit")


class BehaviorStore:
    def __init__(self, data_dir: Path, journal=None) -> None:
        self._journal = journal
        self._lock = threading.Lock()
        self._db_path = Path(data_dir) / "behavior.sqlite"
        with self._lock:
            self._connect().executescript(
                """
                CREATE TABLE IF NOT EXISTS behavior_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    event_type TEXT NOT NULL,
                    created_at INTEGER NOT NULL,
                    facts_json TEXT NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_behavior_user_time
                    ON behavior_events(user_id, created_at);
                """
            )

    def _connect(self) -> sqlite3.Connection:
        connection = sqlite3.connect(str(self._db_path), timeout=3)
        connection.execute("PRAGMA busy_timeout = 3000")
        connection.execute("PRAGMA journal_mode = WAL")
        return connection

    def observe(
        self, user_id: int, event_type: str, facts: dict[str, Any] | None = None
    ) -> None:
        if user_id < 1 or event_type not in EVENT_TYPES:
            return
        clean = {}
        for key, value in (facts or {}).items():
            if key in FIELD_ALLOWLIST and isinstance(value, (str, int, float)):
                text = str(value).strip()[:120]
                if text:
                    clean[key] = text
        now = int(time.time())
        try:
            with self._lock:
                connection = self._connect()
                try:
                    connection.execute(
                        "INSERT INTO behavior_events (user_id, event_type, created_at, facts_json) VALUES (?, ?, ?, ?)",
                        (
                            user_id,
                            event_type,
                            now,
                            json.dumps(clean, ensure_ascii=False),
                        ),
                    )
                    connection.execute(
                        "DELETE FROM behavior_events WHERE created_at < ?",
                        (now - TTL_SECONDS,),
                    )
                    # Enforce the rolling per-user window.
                    connection.execute(
                        "DELETE FROM behavior_events WHERE user_id = ? AND id NOT IN ("
                        "  SELECT id FROM behavior_events WHERE user_id = ? ORDER BY id DESC LIMIT ?"
                        ")",
                        (user_id, user_id, MAX_EVENTS),
                    )
                    connection.commit()
                finally:
                    connection.close()
        except sqlite3.Error:
            return
        if self._journal is not None:
            self._journal.write(
                "ai_behavior",
                {
                    "type": "observation",
                    "user_id": user_id,
                    "interaction": event_type,
                    "facts": clean,
                },
            )

    def hints(self, user_id: int) -> dict[str, Any]:
        cutoff = int(time.time()) - TTL_SECONDS
        try:
            with self._lock:
                connection = self._connect()
                try:
                    rows = connection.execute(
                        "SELECT event_type, facts_json FROM behavior_events "
                        "WHERE user_id = ? AND created_at >= ? ORDER BY id DESC LIMIT ?",
                        (user_id, cutoff, MAX_EVENTS),
                    ).fetchall()
                finally:
                    connection.close()
        except sqlite3.Error:
            return {"known": False}

        domains: dict[str, int] = {}
        workflows: dict[str, int] = {}
        agents: dict[str, int] = {}
        cadences: dict[str, int] = {}
        last_domain = ""
        last_domain_at = 0
        for _, facts_json in reversed(rows):
            facts = json.loads(facts_json or "{}")
            domain = facts.get("domain", "")
            if domain:
                domains[domain] = domains.get(domain, 0) + 1
                last_domain = domain
                last_domain_at = max(last_domain_at, int(time.time()))
            if facts.get("workflow_id"):
                workflows[facts["workflow_id"]] = (
                    workflows.get(facts["workflow_id"], 0) + 1
                )
            if facts.get("agent_id"):
                agents[facts["agent_id"]] = agents.get(facts["agent_id"], 0) + 1
            if facts.get("cadence"):
                cadences[facts["cadence"]] = cadences.get(facts["cadence"], 0) + 1
        if not domains and not workflows and not agents:
            return {"known": False}

        def top(mapping: dict[str, int], limit: int) -> list[str]:
            return [
                k
                for k, _ in sorted(
                    mapping.items(), key=lambda item: (-item[1], item[0])
                )
            ][:limit]

        return {
            "known": True,
            "top_domains": top(domains, 3),
            "top_workflows": top(workflows, 3),
            "top_agents": top(agents, 2),
            "preferred_cadence": top(cadences, 1)[0] if cadences else "",
            "last_domain": last_domain,
            "events_observed": len(rows),
            "last_event_at": last_domain_at,
        }

    def forget(self, user_id: int) -> None:
        if user_id < 1:
            return
        try:
            with self._lock:
                connection = self._connect()
                try:
                    connection.execute(
                        "DELETE FROM behavior_events WHERE user_id = ?", (user_id,)
                    )
                    connection.commit()
                finally:
                    connection.close()
        except sqlite3.Error:
            pass

    def prune(self) -> int:
        cutoff = int(time.time()) - TTL_SECONDS
        try:
            with self._lock:
                connection = self._connect()
                try:
                    cursor = connection.execute(
                        "DELETE FROM behavior_events WHERE created_at < ?", (cutoff,)
                    )
                    connection.commit()
                    return cursor.rowcount or 0
                finally:
                    connection.close()
        except sqlite3.Error:
            return 0
