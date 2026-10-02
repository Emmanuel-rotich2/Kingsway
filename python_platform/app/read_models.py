"""Allowlisted MySQL read-model refreshes for the deterministic worker.

The database performs the SELECT and materialization; Python owns the bounded
job orchestration so long refreshes do not execute in the PHP application
worker. This module is intentionally independent of the LLM provider.
"""

from __future__ import annotations

import re
import time
import uuid
from typing import Any, Callable

PROJECTIONS: dict[str, dict[str, Any]] = {
    "collection_rate_by_class": {
        "source": "vw_collection_rate_by_class",
        "target": "mmv_collection_rate_by_class",
        "indexes": {"idx_level_term": ("level_code", "academic_term")},
        "sensitivity": "financial", "max_age_seconds": 300,
    },
    "class_learning_area_performance": {
        "source": "vw_class_learning_area_performance",
        "target": "mmv_class_learning_area_performance",
        "indexes": {
            "idx_year_class_term": ("academic_year_class_id", "term_number"),
            "idx_academic_year": ("academic_year",),
            "idx_term_number": ("term_number",),
            "idx_class_name": ("class_name",),
            "idx_stream_name": ("stream_name",),
            "idx_learning_area": ("learning_area",),
        },
        "sensitivity": "personal", "max_age_seconds": 900,
    },
    "budget_utilization": {
        "source": "vw_budget_utilization",
        "target": "mmv_budget_utilization",
        "indexes": {
            "idx_budget": ("budget_id",),
            "idx_year_term": ("academic_year", "term"),
        },
        "sensitivity": "financial", "max_age_seconds": 300,
    },
    "dormitory_occupancy": {
        "source": "vw_dormitory_occupancy",
        "target": "mmv_dormitory_occupancy",
        "indexes": {
            "idx_dormitory_year": ("dormitory_id", "academic_year"),
            "idx_academic_year": ("academic_year",),
            "idx_gender": ("gender",),
        },
        "sensitivity": "personal", "max_age_seconds": 300,
    },
    "fee_collection_monthly_trend": {
        "source": "vw_fee_collection_monthly_trend",
        "target": "mmv_fee_collection_monthly_trend",
        "indexes": {"idx_month": ("month",)},
        "sensitivity": "financial", "max_age_seconds": 900,
    },
    "fee_status_summary": {
        "source": "vw_fee_status_summary",
        "target": "mmv_fee_status_summary",
        "indexes": {
            "idx_year_term_class_stream": ("academic_year", "term_number", "class_id", "stream_id"),
            "idx_class_stream": ("class_id", "stream_id"),
            "idx_academic_year": ("academic_year",),
            "idx_term_number": ("term_number",),
            "idx_student_period": ("student_id", "academic_year", "term_number"),
            "idx_admission_no": ("admission_no",),
            "idx_payment_status": ("payment_status",),
            "idx_current_balance": ("current_balance",),
            "idx_student_type": ("student_type_id",),
            "idx_level": ("level_id",),
        },
        "sensitivity": "financial", "max_age_seconds": 300,
    },
}

_IDENTIFIER = re.compile(r"^[A-Za-z0-9_]{1,64}$")


class ReadModelError(RuntimeError):
    pass


def _qid(value: str) -> str:
    if not _IDENTIFIER.fullmatch(value):
        raise ReadModelError("Unsafe database identifier")
    return f"`{value}`"


def _qualified(schema: str, table: str) -> str:
    return f"{_qid(schema)}.{_qid(table)}"


class ReadModelRefresher:
    def __init__(self, config: Any, connector: Callable[..., Any] | None = None):
        self.config = config
        self.connector = connector

    def _connect(self):
        if self.connector is not None:
            return self.connector(
                host=self.config.db_host,
                port=self.config.db_port,
                user=self.config.db_user,
                password=self.config.db_password,
                database=self.config.db_master_schema,
                charset="utf8mb4",
                connect_timeout=self.config.db_connect_timeout,
                read_timeout=self.config.db_read_timeout,
                write_timeout=30,
                autocommit=True,
                ssl_ca=self.config.db_ssl_ca or None,
                ssl_verify_cert=bool(self.config.db_ssl_ca),
                ssl_verify_identity=bool(self.config.db_ssl_ca),
            )
        try:
            import pymysql
        except ImportError as error:  # fail closed when not installed
            raise ReadModelError("The read-model database driver is not installed") from error
        return pymysql.connect(
            host=self.config.db_host,
            port=self.config.db_port,
            user=self.config.db_user,
            password=self.config.db_password,
            database=self.config.db_master_schema,
            charset="utf8mb4",
            connect_timeout=self.config.db_connect_timeout,
            read_timeout=self.config.db_read_timeout,
            write_timeout=30,
            autocommit=True,
            ssl_ca=self.config.db_ssl_ca or None,
            ssl_verify_cert=bool(self.config.db_ssl_ca),
            ssl_verify_identity=bool(self.config.db_ssl_ca),
        )

    def refresh(self, projection: str) -> dict[str, Any]:
        if not self.config.read_models_enabled:
            raise ReadModelError("Python read-model refresh is disabled")
        if not self.config.db_host or not self.config.db_user or not self.config.db_password:
            raise ReadModelError("Python read-model database settings are incomplete")
        if self.config.db_host not in {"localhost", "127.0.0.1", "::1"} and not self.config.db_ssl_ca:
            raise ReadModelError("A TLS CA certificate is required for remote database access")
        definition = PROJECTIONS.get(projection)
        if definition is None:
            raise ReadModelError("Unknown read projection")

        source = _qualified(self.config.db_master_schema, definition["source"])
        target = _qualified(self.config.db_reads_schema, definition["target"])
        stage_name = f"__stage_{definition['target']}_{uuid.uuid4().hex[:12]}"
        stage = _qualified(self.config.db_reads_schema, stage_name)
        lock_name = f"KingswayProjection:{projection}"
        global_lock_name = "KingswayProjection:global"
        started = time.perf_counter()
        source_ms = index_ms = 0
        rows = 0
        connection = self._connect()
        locked = False
        global_locked = False
        try:
            with connection.cursor() as cursor:
                cursor.execute("SELECT GET_LOCK(%s, 0)", (global_lock_name,))
                global_locked = bool(cursor.fetchone()[0] == 1)
                if not global_locked:
                    raise ReadModelError("Another read projection is already being refreshed")
                cursor.execute("SELECT GET_LOCK(%s, 0)", (lock_name,))
                locked = bool(cursor.fetchone()[0] == 1)
                if not locked:
                    raise ReadModelError("This read projection is already being refreshed")

                cursor.execute(
                    f"SELECT COUNT(*) FROM information_schema.TABLES "
                    "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
                    (self.config.db_master_schema, definition["source"]),
                )
                if int(cursor.fetchone()[0]) != 1:
                    raise ReadModelError("Read projection source is unavailable")

                cursor.execute(f"DROP TABLE IF EXISTS {stage}")
                cursor.execute(f"CREATE TABLE {stage} AS SELECT * FROM {source} WHERE 1=0")
                phase = time.perf_counter()
                cursor.execute(f"INSERT INTO {stage} SELECT * FROM {source}")
                rows = max(0, int(cursor.rowcount))
                source_ms = int((time.perf_counter() - phase) * 1000)

                phase = time.perf_counter()
                cursor.execute(
                    "SELECT COLUMN_NAME FROM information_schema.COLUMNS "
                    "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
                    (self.config.db_reads_schema, stage_name),
                )
                columns = {str(row[0]) for row in cursor.fetchall()}
                for name, index_columns in definition["indexes"].items():
                    if any(column not in columns for column in index_columns):
                        raise ReadModelError("Projection output does not match its registered index")
                    keys = ",".join(_qid(column) for column in index_columns)
                    cursor.execute(f"ALTER TABLE {stage} ADD INDEX {_qid(name)} ({keys})")
                index_ms = int((time.perf_counter() - phase) * 1000)

                cursor.execute(
                    "SELECT COUNT(*) FROM information_schema.TABLES "
                    "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
                    (self.config.db_reads_schema, definition["target"]),
                )
                exists = int(cursor.fetchone()[0]) == 1
                if exists:
                    old_name = f"__stage_old_{definition['target']}_{uuid.uuid4().hex[:8]}"
                    old = _qualified(self.config.db_reads_schema, old_name)
                    cursor.execute(f"RENAME TABLE {target} TO {old}, {stage} TO {target}")
                    cursor.execute(f"DROP TABLE {old}")
                else:
                    cursor.execute(f"RENAME TABLE {stage} TO {target}")

                watermark = None
                if projection == "fee_collection_monthly_trend":
                    cursor.execute(f"SELECT MAX(`month`) FROM {source}")
                    value = cursor.fetchone()[0]
                    watermark = str(value) if value is not None else None

                cursor.execute(
                    f"INSERT INTO {_qualified(self.config.db_reads_schema, 'reads_meta')} "
                    "(projection, source_view, rows_count, source_watermark, as_of, refreshed_at, status, "
                    "storage_mode, sensitivity, max_age_seconds, last_error) "
                    "VALUES (%s,%s,%s,%s,NOW(),NOW(),'live','materialized_table',%s,%s,NULL) "
                    "ON DUPLICATE KEY UPDATE source_view=VALUES(source_view), rows_count=VALUES(rows_count), "
                    "source_watermark=VALUES(source_watermark), as_of=VALUES(as_of), refreshed_at=VALUES(refreshed_at), "
                    "status='live', storage_mode='materialized_table', sensitivity=VALUES(sensitivity), "
                    "max_age_seconds=VALUES(max_age_seconds), last_error=NULL",
                    (projection, f"{self.config.db_master_schema}.{definition['source']}", rows, watermark,
                     definition["sensitivity"], definition["max_age_seconds"]),
                )

            return {
                "status": "published",
                "projection": projection,
                "target": definition["target"],
                "rows_count": rows,
                "source_query_ms": source_ms,
                "index_build_ms": index_ms,
                "duration_ms": int((time.perf_counter() - started) * 1000),
            }
        except Exception as error:
            try:
                with connection.cursor() as cursor:
                    cursor.execute(f"DROP TABLE IF EXISTS {stage}")
                    cursor.execute(
                        "SELECT COUNT(*) FROM information_schema.TABLES "
                        "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
                        (self.config.db_reads_schema, definition["target"]),
                    )
                    target_exists = int(cursor.fetchone()[0]) == 1
                    if target_exists:
                        cursor.execute(
                            f"UPDATE {_qualified(self.config.db_reads_schema, 'reads_meta')} "
                            "SET last_error=%s WHERE projection=%s",
                            (type(error).__name__, projection),
                        )
                    else:
                        cursor.execute(
                            f"UPDATE {_qualified(self.config.db_reads_schema, 'reads_meta')} "
                            "SET status='failed', refreshed_at=NULL, last_error=%s WHERE projection=%s",
                            (type(error).__name__, projection),
                        )
            except Exception:
                pass
            if isinstance(error, ReadModelError):
                raise
            raise ReadModelError("Read projection refresh failed") from error
        finally:
            if locked:
                try:
                    with connection.cursor() as cursor:
                        cursor.execute("SELECT RELEASE_LOCK(%s)", (lock_name,))
                except Exception:
                    pass
            if global_locked:
                try:
                    with connection.cursor() as cursor:
                        cursor.execute("SELECT RELEASE_LOCK(%s)", (global_lock_name,))
                except Exception:
                    pass
            connection.close()
