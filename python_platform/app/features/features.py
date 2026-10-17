"""
Feature Store - SQLite (online) + Parquet (offline) with entity-centric design.
"""

from datetime import date, datetime
from typing import Any, Dict, List, Optional
import sqlite3
import polars as pl
import pandas as pd
from pathlib import Path
from dataclasses import dataclass, asdict
from typing import Dict, List, Optional, Any
import time

from ..config import Config


@dataclass
class Feature:
    name: str
    value: float
    updated_at: str
    entity_type: str
    entity_id: int


class FeatureStore:
    """
    Hybrid Feature Store: SQLite (online, low latency) + Parquet (offline, training).
    """

    def __init__(self, db_path: Optional[str] = None):
        # Storage root comes from Config (STORAGE_ROOT/python by default);
        # db_path stays as an explicit override for tests.
        features_dir = Config().storage_dir / "buffers" / "features"
        self.db_path = Path(db_path) if db_path else features_dir / "feature_store.db"
        self.db_path.parent.mkdir(parents=True, exist_ok=True)
        self.offline_path = features_dir / "offline"
        self.offline_path.mkdir(parents=True, exist_ok=True)
        self._init_db()

    def _init_db(self):
        with sqlite3.connect(self.db_path) as conn:
            conn.execute("PRAGMA journal_mode=WAL")
            conn.execute("PRAGMA busy_timeout=5000")
            conn.execute("""
                CREATE TABLE IF NOT EXISTS features (
                    entity_type TEXT NOT NULL,
                    entity_id INTEGER NOT NULL,
                    feature_name TEXT NOT NULL,
                    value REAL NOT NULL,
                    updated_at REAL NOT NULL,
                    PRIMARY KEY (entity_type, entity_id, feature_name)
                )
            """)
            conn.execute("""
                CREATE INDEX IF NOT EXISTS idx_features_entity
                ON features(entity_type, entity_id)
            """)
            conn.execute("""
                CREATE INDEX IF NOT EXISTS idx_features_updated
                ON features(updated_at)
            """)

    def set(
        self,
        entity_type: str,
        entity_id: int,
        feature_name: str,
        value: float,
        ttl: int = 86400,
    ) -> None:
        """Set a feature value (online store)."""
        with sqlite3.connect(self.db_path) as conn:
            conn.execute("PRAGMA journal_mode=WAL")
            conn.execute("PRAGMA busy_timeout=5000")
            conn.execute(
                """INSERT INTO features (entity_type, entity_id, feature_name, value, updated_at)
                   VALUES (?, ?, ?, ?, ?)
                   ON CONFLICT(entity_type, entity_id, feature_name) DO UPDATE SET
                   value=excluded.value, updated_at=excluded.updated_at""",
                (feature_name, entity_id, feature_name, float(value), time.time()),
            )

    def get(
        self,
        entity_type: str,
        entity_id: int,
        feature_names: Optional[List[str]] = None,
    ) -> Dict[str, float]:
        """Get feature values for an entity."""
        with sqlite3.connect(self.db_path) as conn:
            if feature_names:
                placeholders = ",".join(["?"] * len(feature_names))
                query = f"""SELECT feature_name, value FROM features
                           WHERE entity_type=? AND entity_id=? AND feature_name IN ({",".join(["?"] * len(feature_names))})"""
                params = [entity_type, entity_id] + feature_names
            else:
                query = "SELECT feature_name, value FROM features WHERE entity_type=? AND entity_id=?"
                params = [entity_type, entity_id]

            cursor = conn.execute(query, params)
            return {row[0]: row[1] for row in cursor.fetchall()}

    def get_many(
        self,
        entity_type: str,
        entity_ids: List[int],
        feature_names: Optional[List[str]] = None,
    ) -> Dict[int, Dict[str, float]]:
        """Batch get features for multiple entities."""
        if not entity_ids:
            return {}

        placeholders = ",".join(["?"] * len(entity_ids))
        with sqlite3.connect(self.db_path) as conn:
            if feature_names:
                fn_placeholders = ",".join(["?"] * len(feature_names))
                query = f"""SELECT entity_id, feature_name, value FROM features
                           WHERE entity_type=? AND entity_id IN ({",".join(["?"] * len(entity_ids))})
                           AND feature_name IN ({",".join(["?"] * len(feature_names))})"""
                params = [entity_type] + entity_ids + feature_names
            else:
                query = f"""SELECT entity_id, feature_name, value FROM features
                           WHERE entity_type=? AND entity_id IN ({",".join(["?"] * len(entity_ids))})"""
                params = [entity_type] + entity_ids

            cursor = conn.execute(query, params)
            results = {}
            for row in cursor.fetchall():
                entity_id, feature_name, value = row
                if entity_id not in results:
                    results[entity_id] = {}
                results[entity_id][feature_name] = value
            return results

    def set_batch(
        self, entity_type: str, features: List[Dict], ttl: int = 86400
    ) -> int:
        """Batch set features."""
        with sqlite3.connect(self.db_path) as conn:
            conn.execute("PRAGMA journal_mode=WAL")
            conn.execute("PRAGMA busy_timeout=5000")
            conn.executemany(
                """INSERT INTO features (entity_type, entity_id, feature_name, value, updated_at)
                   VALUES (?, ?, ?, ?, ?)
                   ON CONFLICT(entity_type, entity_id, feature_name) DO UPDATE SET
                   value=excluded.value, updated_at=excluded.updated_at""",
                [
                    (
                        ft["entity_type"],
                        ft["entity_id"],
                        ft["feature_name"],
                        float(ft["value"]),
                        time.time(),
                    )
                    for ft in features
                ],
            )
            return len(features)

    def materialize_offline(
        self, entity_types: List[str], output_path: Optional[str] = None
    ) -> Dict[str, int]:
        """Materialize online features to Parquet for offline training."""
        output_path = Path(output_path) if output_path else self.offline_path
        output_path.mkdir(parents=True, exist_ok=True)

        counts = {}
        for entity_type in entity_types:
            with sqlite3.connect(self.db_path) as conn:
                df = pd.read_sql_query(
                    f"SELECT * FROM features WHERE entity_type = ?",
                    conn,
                    params=[entity_type],
                )
                if len(df) > 0:
                    partition_path = (
                        self.offline_path
                        / entity_type
                        / f"date={date.today().isoformat()}"
                    )
                    partition_path.mkdir(parents=True, exist_ok=True)
                    df.to_parquet(
                        partition_path
                        / f"features_{datetime.now().strftime('%H%M%S')}.parquet",
                        compression="snappy",
                    )
                    counts[entity_type] = len(df)
        return counts

    def get_training_data(
        self, entity_type: str, feature_names: List[str], date_from: str, date_to: str
    ) -> pd.DataFrame:
        """Get training data from offline store."""
        # Would read from parquet files partitioned by date
        # For now, return from online store
        with sqlite3.connect(self.db_path) as conn:
            query = f"""SELECT entity_id, feature_name, value, updated_at
                       FROM features WHERE entity_type = ? AND feature_name IN ({",".join(["?"] * len(feature_names))})"""
            params = [entity_type] + feature_names
            df = pd.read_sql_query(query, conn, params=params)
            if len(df) == 0:
                return pd.DataFrame()

            # Pivot to wide format
            df = df.pivot_table(
                index=["entity_id", "updated_at"],
                columns="feature_name",
                values="value",
                aggfunc="first",
            ).reset_index()
            return df


class FeatureMaterializer:
    """Materialize features from source data."""

    def __init__(self, feature_store: FeatureStore):
        self.store = feature_store
        self.version = "1.0.0"

    def materialize_student_features(self, df: pd.DataFrame) -> int:
        """Materialize student features from raw data."""
        features = []
        for _, row in df.iterrows():
            features.append(
                {
                    "entity_type": "student",
                    "entity_id": int(row["student_id"]),
                    "feature_name": "attendance_rate_30d",
                    "value": float(row.get("attendance_rate_30d", 0)),
                }
            )
            features.append(
                {
                    "entity_type": "student",
                    "entity_id": int(row["student_id"]),
                    "feature_name": "fee_balance",
                    "value": float(row.get("fee_balance", 0)),
                }
            )
            features.append(
                {
                    "entity_type": "student",
                    "entity_id": int(row["student_id"]),
                    "feature_name": "grade_average",
                    "value": float(row.get("grade_average", 0)),
                }
            )
            features.append(
                {
                    "entity_type": "student",
                    "entity_id": int(row["student_id"]),
                    "feature_name": "consecutive_absences",
                    "value": float(row.get("consecutive_absences", 0)),
                }
            )

        with sqlite3.connect(self.store.db_path) as conn:
            conn.execute("PRAGMA journal_mode=WAL")
            conn.executemany(
                """INSERT INTO features (entity_type, entity_id, feature_name, value, updated_at)
                   VALUES (?, ?, ?, ?, ?)
                   ON CONFLICT(entity_type, entity_id, feature_name) DO UPDATE SET
                   value=excluded.value, updated_at=excluded.updated_at""",
                [
                    (
                        ft["entity_type"],
                        ft["entity_id"],
                        ft["feature_name"],
                        float(ft["value"]),
                        time.time(),
                    )
                    for ft in features
                ],
            )
        return len(features)

    def materialize_staff_features(self, df: pd.DataFrame) -> int:
        return 0

    def materialize_inventory_features(self, df: pd.DataFrame) -> int:
        return 0

    def materialize_transport_features(self, df: pd.DataFrame) -> int:
        return 0


def get_feature_store() -> FeatureStore:
    """Get the global feature store instance."""
    global _global_feature_store
    if _global_feature_store is None:
        _global_feature_store = FeatureStore()
    return _global_feature_store


_global_feature_store = None
