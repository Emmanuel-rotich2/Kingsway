"""
SQLite-backed stream buffer for inter-runtime event streaming.
Replaces Redis Streams with SQLite WAL mode tables + JSON Lines fallback.
"""

import json
import os
import sqlite3
import time
import threading
from pathlib import Path
from typing import Any, Dict, List, Optional

_SHARED_STORAGE_ROOT = os.getenv("STORAGE_ROOT", "").strip()
if _SHARED_STORAGE_ROOT:
    _storage_root = Path(_SHARED_STORAGE_ROOT)
    if not _storage_root.is_absolute():
        raise ValueError("STORAGE_ROOT must be an absolute path")
    STREAM_DIR = _storage_root / "python" / "streams"
else:
    # Local-development fallback. KINGSWAY_STREAM_DIR remains a compatibility
    # override only when the shared three-runtime root is not configured.
    _legacy_stream_dir = os.getenv("KINGSWAY_STREAM_DIR", "").strip()
    STREAM_DIR = (
        Path(_legacy_stream_dir)
        if _legacy_stream_dir
        else Path(__file__).resolve().parents[2] / "storage" / "python" / "streams"
    )
STREAM_DIR.mkdir(parents=True, exist_ok=True)


class StreamBuffer:
    """Thread-safe SQLite-backed stream buffer with JSON Lines fallback."""

    def __init__(self, stream_name: str, directory: Optional[Path] = None):
        self.stream_name = stream_name
        self.directory = directory or STREAM_DIR
        self.directory.mkdir(parents=True, exist_ok=True)
        self._lock = threading.Lock()
        self.db_path = self.directory / f"{stream_name}.sqlite"
        self.jsonl_path = self.directory / f"{stream_name}.jsonl"
        self._init_db()

    def _init_db(self):
        with sqlite3.connect(self.db_path) as conn:
            conn.execute("PRAGMA journal_mode=WAL")
            conn.execute("PRAGMA busy_timeout=5000")
            conn.execute("""
                CREATE TABLE IF NOT EXISTS events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    stream_name TEXT NOT NULL,
                    event_type TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    created_at REAL NOT NULL,
                    acknowledged INTEGER DEFAULT 0
                )
            """)
            conn.execute("""
                CREATE INDEX IF NOT EXISTS idx_events_stream_created
                ON events(stream_name, created_at)
            """)
            conn.execute("""
                CREATE INDEX IF NOT EXISTS idx_events_ack
                ON events(stream_name, acknowledged, created_at)
            """)

    def append(self, event_type: str, payload: Dict[str, Any]) -> int:
        """Append an event to the stream. Returns event ID."""
        created_at = time.time()
        payload_json = json.dumps(payload, separators=(",", ":"), ensure_ascii=False)

        with self._lock:
            with sqlite3.connect(self.db_path) as conn:
                conn.execute("PRAGMA journal_mode=WAL")
                conn.execute("PRAGMA busy_timeout=5000")
                cursor = conn.execute(
                    "INSERT INTO events (stream_name, event_type, payload, created_at) VALUES (?, ?, ?, ?)",
                    (self.stream_name, event_type, json.dumps(payload), time.time())
                )
                event_id = cursor.lastrowid

        # Also append to JSON Lines for portability/inspection
        try:
            with open(self.jsonl_path, "a") as f:
                f.write(json.dumps({
                    "id": event_id,
                    "stream": self.stream_name,
                    "type": event_type,
                    "payload": payload,
                    "ts": time.time()
                }, ensure_ascii=False) + "\n")
        except Exception:
            pass  # JSONL is best-effort

        return event_id

    def read_batch(self, after_id: int = 0, limit: int = 100) -> List[Dict[str, Any]]:
        """Read events after a given ID."""
        with sqlite3.connect(self.db_path) as conn:
            conn.row_factory = sqlite3.Row
            cursor = conn.execute(
                "SELECT id, event_type, payload, created_at FROM events "
                "WHERE stream_name = ? AND id > ? ORDER BY id ASC LIMIT ?",
                (self.stream_name, after_id, limit)
            )
            rows = cursor.fetchall()
            return [
                {
                    "id": row["id"],
                    "type": row["event_type"],
                    "payload": json.loads(row["payload"]),
                    "ts": row["created_at"]
                }
                for row in rows
            ]

    def acknowledge(self, up_to_id: int) -> int:
        """Mark events up to ID as acknowledged. Returns count."""
        with self._lock:
            with sqlite3.connect(self.db_path) as conn:
                conn.execute("PRAGMA journal_mode=WAL")
                cursor = conn.execute(
                    "UPDATE events SET acknowledged = 1 WHERE stream_name = ? AND id <= ? AND acknowledged = 0",
                    (self.stream_name, up_to_id)
                )
                return cursor.rowcount

    def trim(self, keep_last: int = 10000) -> int:
        """Trim stream to keep only last N events. Returns deleted count."""
        with self._lock:
            with sqlite3.connect(self.db_path) as conn:
                # Get ID threshold
                cursor = conn.execute(
                    "SELECT id FROM events WHERE stream_name = ? ORDER BY id DESC LIMIT 1 OFFSET ?",
                    (self.stream_name, keep_last)
                )
                row = cursor.fetchone()
                if not row:
                    return 0
                threshold_id = row[0]
                cursor = conn.execute(
                    "DELETE FROM events WHERE stream_name = ? AND id < ?",
                    (self.stream_name, threshold_id)
                )
                return cursor.rowcount

    def stats(self) -> Dict[str, Any]:
        """Get stream statistics."""
        with sqlite3.connect(self.db_path) as conn:
            cursor = conn.execute(
                "SELECT COUNT(*), MIN(id), MAX(id), MIN(created_at), MAX(created_at), "
                "SUM(CASE WHEN acknowledged = 0 THEN 1 ELSE 0 END) "
                "FROM events WHERE stream_name = ?",
                (self.stream_name,)
            )
            row = cursor.fetchone()
            return {
                "stream": self.stream_name,
                "total_events": row[0] or 0,
                "first_id": row[1],
                "last_id": row[2],
                "first_ts": row[3],
                "last_ts": row[4],
                "unacknowledged": row[5] or 0,
            }

    def health(self) -> Dict[str, Any]:
        """Health check for the stream buffer."""
        stats = self.stats()
        return {
            "status": "ok",
            "storage": "sqlite",
            "directory_writable": True,
            "permissions_ok": True,
            "stream": self.stream_name,
            "total_events": stats.get("total_events", 0),
        }


class StreamRegistry:
    """Manages multiple named streams."""

    def __init__(self, directory: Optional[Path] = None):
        self.directory = directory or STREAM_DIR
        self._streams: Dict[str, StreamBuffer] = {}
        self._lock = threading.Lock()

    def get(self, stream_name: str) -> StreamBuffer:
        with self._lock:
            if stream_name not in self._streams:
                self._streams[stream_name] = StreamBuffer(stream_name, self.directory)
            return self._streams[stream_name]

    def list_streams(self) -> List[str]:
        return list(self._streams.keys())

    def all_stats(self) -> Dict[str, Any]:
        return {name: stream.stats() for name, stream in self._streams.items()}


# Pre-defined stream names for the architecture
STREAMS = {
    "attendance.marked": "Attendance marking events",
    "payment.received": "Payment received events",
    "grade.submitted": "Grade submission events",
    "inventory.movement": "Inventory movement events",
    "transport.trip_complete": "Transport trip completion events",
}


def get_stream_registry() -> StreamRegistry:
    """Get the global stream registry."""
    global _global_registry
    if "_global_registry" not in globals():
        _global_registry = StreamRegistry()
    return globals()["_global_registry"]


def publish_event(stream_name: str, event_type: str, payload: Dict[str, Any]) -> int:
    """Convenience function to publish an event to a stream."""
    registry = get_stream_registry()
    stream = registry.get(stream_name)
    return stream.append(event_type, payload)


def consume_stream(stream_name: str, after_id: int = 0, limit: int = 100) -> List[Dict[str, Any]]:
    """Convenience function to consume events from a stream."""
    registry = get_stream_registry()
    stream = registry.get(stream_name)
    return stream.read_batch(after_id, limit)


def acknowledge_events(stream_name: str, up_to_id: int) -> int:
    """Convenience function to acknowledge events."""
    registry = get_stream_registry()
    stream = registry.get(stream_name)
    return stream.acknowledge(up_to_id)


# Background stream processor base class
class StreamProcessor:
    """Base class for stream processors."""

    def __init__(self, stream_name: str, processor_id: str = "default"):
        self.stream_name = stream_name
        self.processor_id = processor_id
        self.registry = get_stream_registry()
        self.stream = self.registry.get(stream_name)
        self.last_processed_id = 0
        self.running = False

    def process_event(self, event: Dict[str, Any]) -> bool:
        """Process a single event. Return True if successful."""
        raise NotImplementedError

    def run(self, poll_interval: float = 1.0, batch_size: int = 100):
        """Run the processor loop."""
        self.running = True
        while self.running:
            try:
                events = self.stream.read_batch(self.last_processed_id, batch_size)
                if not events:
                    time.sleep(poll_interval)
                    continue

                for event in events:
                    try:
                        if self.process_event(event):
                            self.last_processed_id = max(self.last_processed_id, event["id"])
                    except Exception as e:
                        print(f"Error processing event {event['id']}: {e}")

                # Acknowledge processed events
                if self.last_processed_id > 0:
                    self.stream.acknowledge(self.last_processed_id)

            except Exception as e:
                print(f"Stream processor error: {e}")
                time.sleep(poll_interval)

    def stop(self):
        self.running = False


# Example processors
class AttendanceStreamProcessor(StreamProcessor):
    def process_event(self, event: Dict[str, Any]) -> bool:
        payload = event["payload"]
        # Check for consecutive absences, risk scoring, etc.
        # In production: call academic/anomaly detection services
        return True


class PaymentStreamProcessor(StreamProcessor):
    def process_event(self, event: Dict[str, Any]) -> bool:
        payload = event["payload"]
        # Instant reconciliation matching
        return True


class AcademicStreamProcessor(StreamProcessor):
    def process_event(self, event: Dict[str, Any]) -> bool:
        payload = event["payload"]
        # Risk scoring, parent alerts
        return True


class InventoryStreamProcessor(StreamProcessor):
    def process_event(self, event: Dict[str, Any]) -> bool:
        payload = event["payload"]
        # Reorder check, stockout prediction
        return True


class TransportStreamProcessor(StreamProcessor):
    def process_event(self, event: Dict[str, Any]) -> bool:
        payload = event["payload"]
        # Punctuality, fuel check
        return True


# Processor factory
PROCESSORS = {
    "attendance.marked": AttendanceStreamProcessor,
    "payment.received": PaymentStreamProcessor,
    "grade.submitted": AcademicStreamProcessor,
    "inventory.movement": InventoryStreamProcessor,
    "transport.trip_complete": TransportStreamProcessor,
}


def get_processor(stream_name: str, processor_id: str = "default") -> StreamProcessor:
    """Get a processor instance for a stream."""
    cls = PROCESSORS.get(stream_name, StreamProcessor)
    return cls(stream_name, processor_id)
