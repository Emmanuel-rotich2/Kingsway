"""
Task Queue for async agent tasks.
Uses SQLite for persistence (shared hosting compatible).
"""

import json
import sqlite3
import time
import uuid
from pathlib import Path
from typing import Any, Dict, List, Optional

from app.config import Config


class TaskQueue:
    def __init__(self):
        self.cfg = Config()
        self.db_path = self.cfg.shared_storage_root / "agent_tasks.sqlite"
        self._init_db()

    def _init_db(self) -> None:
        self.db_path.parent.mkdir(parents=True, exist_ok=True)
        with sqlite3.connect(self.db_path) as conn:
            conn.execute("""
                CREATE TABLE IF NOT EXISTS agent_tasks (
                    id TEXT PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    tool TEXT NOT NULL,
                    question TEXT NOT NULL,
                    route TEXT,
                    module TEXT,
                    permissions TEXT,
                    options TEXT,
                    session_id TEXT,
                    status TEXT NOT NULL DEFAULT 'queued',
                    progress INTEGER DEFAULT 0,
                    message TEXT,
                    result TEXT,
                    error TEXT,
                    created_at REAL NOT NULL,
                    updated_at REAL NOT NULL
                )
            """)
            conn.execute("""
                CREATE INDEX IF NOT EXISTS idx_agent_tasks_user_id
                ON agent_tasks(user_id)
            """)
            conn.execute("""
                CREATE INDEX IF NOT EXISTS idx_agent_tasks_status
                ON agent_tasks(status)
            """)
            conn.commit()

    def _get_conn(self) -> sqlite3.Connection:
        conn = sqlite3.connect(self.db_path, timeout=30)
        conn.row_factory = sqlite3.Row
        return conn

    def enqueue(
        self,
        user_id: int,
        tool: str,
        question: str,
        route: str,
        module: str,
        permissions: List[str],
        options: Dict[str, Any],
        session_id: str = "",
    ) -> str:
        task_id = str(uuid.uuid4())
        now = time.time()
        with self._get_conn() as conn:
            conn.execute(
                """
                INSERT INTO agent_tasks
                (id, user_id, tool, question, route, module, permissions, options, session_id, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'queued', ?, ?)
                """,
                (
                    task_id,
                    user_id,
                    tool,
                    question,
                    route,
                    module,
                    json.dumps(permissions),
                    json.dumps(options),
                    session_id,
                    now,
                    now,
                ),
            )
            conn.commit()
        return task_id

    def get(self, task_id: str, user_id: int) -> Optional[Dict[str, Any]]:
        with self._get_conn() as conn:
            row = conn.execute(
                "SELECT * FROM agent_tasks WHERE id = ? AND user_id = ?",
                (task_id, user_id),
            ).fetchone()
            if row is None:
                return None
            return self._row_to_dict(row)

    def list(self, user_id: int, limit: int = 30) -> List[Dict[str, Any]]:
        with self._get_conn() as conn:
            rows = conn.execute(
                "SELECT * FROM agent_tasks WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
                (user_id, limit),
            ).fetchall()
            return [self._row_to_dict(row) for row in rows]

    def cancel(self, task_id: str, user_id: int) -> bool:
        with self._get_conn() as conn:
            row = conn.execute(
                "SELECT status FROM agent_tasks WHERE id = ? AND user_id = ?",
                (task_id, user_id),
            ).fetchone()
            if row is None:
                return False
            if row["status"] not in ("queued", "processing"):
                return False
            conn.execute(
                "UPDATE agent_tasks SET status = 'cancelled', updated_at = ? WHERE id = ?",
                (time.time(), task_id),
            )
            conn.commit()
        return True

    def update_status(
        self,
        task_id: str,
        status: str,
        progress: Optional[int] = None,
        message: Optional[str] = None,
        result: Optional[Dict[str, Any]] = None,
        error: Optional[str] = None,
    ) -> None:
        now = time.time()
        with self._get_conn() as conn:
            updates = ["status = ?", "updated_at = ?"]
            params: List[Any] = [status, now]
            if progress is not None:
                updates.append("progress = ?")
                params.append(progress)
            if message is not None:
                updates.append("message = ?")
                params.append(message)
            if result is not None:
                updates.append("result = ?")
                params.append(json.dumps(result))
            if error is not None:
                updates.append("error = ?")
                params.append(error)
            params.append(task_id)
            conn.execute(
                f"UPDATE agent_tasks SET {', '.join(updates)} WHERE id = ?",
                params,
            )
            conn.commit()

    def _row_to_dict(self, row: sqlite3.Row) -> Dict[str, Any]:
        d = dict(row)
        for field in ("permissions", "options", "result"):
            if d.get(field):
                try:
                    d[field] = json.loads(d[field])
                except (json.JSONDecodeError, TypeError):
                    d[field] = {}
        return d
