"""
Anomaly Detection Service - Isolation Forest, LSTM Autoencoder, Statistical Control.
"""

from datetime import date, datetime
from typing import Any, Dict, List, Optional
import json
import numpy as np
import pandas as pd
from pathlib import Path
from dataclasses import dataclass, asdict


@dataclass
class Anomaly:
    entity_id: int
    entity_type: str  # student, payment, grade, inventory_item, transport_route
    anomaly_type: str
    severity: str  # low, medium, high, critical
    score: float
    details: Dict
    detected_at: str


class AnomalyDetector:
    """Base anomaly detector."""

    def __init__(self, model_dir: str = "models/anomaly"):
        self.model_dir = Path(model_dir)
        self.model_dir.mkdir(parents=True, exist_ok=True)
        self.version = "1.0.0"

    def detect(self, df: pd.DataFrame) -> List[Dict]:
        raise NotImplementedError


# ─── Attendance Anomaly Detection (Isolation Forest) ───

class AttendanceAnomalyDetector:
    """Detect attendance anomalies using Isolation Forest."""

    def __init__(self, model_dir: str = "models/anomaly"):
        self.model_dir = Path(model_dir)
        self.model_dir.mkdir(parents=True, exist_ok=True)
        self.model = None
        self.version = "1.0.0"

    def train(self, df: pd.DataFrame) -> Dict[str, float]:
        """Train Isolation Forest on attendance patterns."""
        try:
            from sklearn.ensemble import IsolationForest
            from sklearn.preprocessing import StandardScaler
            from sklearn.model_selection import train_test_split
        except ImportError:
            return {"error": "sklearn not installed"}

        # Feature engineering
        df = df.copy()
        features = self._extract_features(df)

        X = features.select_dtypes(include=[np.number]).fillna(0)
        if len(X) < 10:
            return {"error": "Insufficient data"}

        scaler = StandardScaler()
        X_scaled = scaler.fit_transform(X)

        self.scaler = StandardScaler()
        X_scaled = self.scaler.fit_transform(X)

        self.model = IsolationForest(
            contamination=0.05,
            n_estimators=100,
            random_state=42,
        )
        self.model.fit(X_scaled)

        # Save scaler
        import pickle
        with open(Path("models/anomaly/attendance_scaler.pkl"), "wb") as f:
            pickle.dump(scaler, f)

        return {"train_size": len(X), "contamination": 0.05}

    def detect(self, df: pd.DataFrame) -> List[Dict]:
        if not hasattr(self, "model") or self.model is None:
            return []

        features = self._extract_features(df)
        X = features.select_dtypes(include=[np.number]).fillna(0)
        if len(X) == 0:
            return []

        import pickle
        with open("models/anomaly/attendance_scaler.pkl", "rb") as f:
            scaler = pickle.load(f)

        X_scaled = scaler.transform(X.select_dtypes(include=[np.number]).fillna(0))
        scores = self.model.decision_function(X_scaled)
        predictions = self.model.predict(X_scaled)

        anomalies = []
        for i, (idx, row) in enumerate(df.iterrows()):
            if predictions[i] == -1:  # Anomaly
                severity = "high" if X.iloc[i].mean() < -0.5 else "medium"
                anomalies.append({
                    "entity_id": int(row.get("student_id", 0)),
                    "entity_type": "student",
                    "anomaly_type": "attendance_pattern",
                    "severity": "high" if X.iloc[i].mean() < -0.5 else "medium",
                    "score": float(X.iloc[i].mean()),
                    "details": {"anomaly_score": float(predictions[i])},
                    "detected_at": datetime.utcnow().isoformat(),
                })

        return anomalies

    def _extract_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Extract attendance features for anomaly detection."""
        features = df.copy()

        # Ensure date is datetime
        if "date" in features.columns:
            features["date"] = pd.to_datetime(features["date"])

        # Group by student and compute rolling features
        if "student_id" in features.columns:
            features = features.sort_values(["student_id", "date"])

            # Rolling attendance rate (7-day window)
            features["attendance_rate_7d"] = features.groupby("student_id")["is_present"].rolling(7, min_periods=1).mean().reset_index(0, drop=True)

            # Consecutive absences
            features["consecutive_absent"] = features.groupby("student_id")["is_absent"].apply(
                lambda x: (x == 0).astype(int).groupby((x != 0).cumsum()).cumsum()
            ).reset_index(0, drop=True)

            # Day of week pattern
            if "date" in features.columns:
                features["day_of_week"] = pd.to_datetime(features["date"]).dt.dayofweek
                features["is_weekend"] = features["day_of_week"].isin([5, 6]).astype(int)

        return features.select_dtypes(include=[np.number]).fillna(0)


# ─── Payment Anomaly Detector ───

class PaymentAnomalyDetector:
    """Detect payment anomalies: duplicates, outliers, timing anomalies."""

    def __init__(self):
        self.version = "1.0.0"

    def detect(self, df: pd.DataFrame) -> List[Dict]:
        """Detect payment anomalies using statistical methods."""
        anomalies = []

        # Duplicate reference detection
        if "reference" in df.columns:
            dup_refs = df[df.duplicated(subset=["reference"], keep=False)]
            for _, row in dup_refs.iterrows():
                anomalies.append({
                    "entity_id": int(row.get("payment_id", 0)),
                    "entity_type": "payment",
                    "anomaly_type": "duplicate_reference",
                    "severity": "high",
                    "score": 1.0,
                    "details": {"reference": row.get("reference")},
                    "detected_at": datetime.utcnow().isoformat(),
                })

        # Amount outlier detection (IQR method)
        if "amount" in df.columns:
            amounts = df["amount"].dropna()
            Q1 = amounts.quantile(0.25)
            Q3 = amounts.quantile(0.75)
            IQR = Q3 - Q1
            lower = Q1 - 1.5 * IQR
            upper = Q3 + 1.5 * IQR

            outliers = df[(df["amount"] < lower) | (df["amount"] > upper)]
            for _, row in outliers.iterrows():
                anomalies.append({
                    "entity_id": int(row.get("payment_id", 0)),
                    "entity_type": "payment",
                    "anomaly_type": "amount_outlier",
                    "severity": "medium",
                    "score": 0.8,
                    "details": {"amount": float(row.get("amount", 0)), "threshold_upper": float(upper), "threshold_lower": float(lower)},
                    "detected_at": datetime.utcnow().isoformat(),
                })

        # Timing anomaly - payments at unusual hours
        if "paid_at" in df.columns:
            df["paid_at"] = pd.to_datetime(df["paid_at"])
            df["hour"] = df["paid_at"].dt.hour
            unusual_hours = df[(df["hour"] < 6) | (df["hour"] > 22)]
            for _, row in unusual_hours.iterrows():
                anomalies.append({
                    "entity_id": int(row.get("payment_id", 0)),
                    "entity_type": "payment",
                    "anomaly_type": "timing_anomaly",
                    "severity": "low",
                    "score": 0.3,
                    "details": {"hour": int(row["hour"])},
                    "detected_at": datetime.utcnow().isoformat(),
                })

        return anomalies


# ─── Grade Anomaly Detector ───

class GradeAnomalyDetector:
    """Detect grade anomalies: sudden drops, impossible scores."""

    def __init__(self):
        self.version = "1.0.0"

    def detect(self, df: pd.DataFrame) -> List[Dict]:
        anomalies = []

        if "student_id" in df.columns and "score" in df.columns:
            # Sort by student and date
            df = df.sort_values(["student_id", "date"])

            # Detect sudden drops (>25 points drop)
            df["prev_score"] = df.groupby("student_id")["score"].shift(1)
            df["score_drop"] = df["prev_score"] - df["score"]

            sudden_drops = df[df["score_drop"] > 25]
            for _, row in sudden_drops.iterrows():
                anomalies.append({
                    "entity_id": int(row["student_id"]),
                    "entity_type": "student",
                    "anomaly_type": "sudden_grade_drop",
                    "severity": "high",
                    "score": 0.9,
                    "details": {
                        "subject": row.get("subject", ""),
                        "previous_score": float(row["prev_score"]),
                        "current_score": float(row["score"]),
                        "drop": float(row["score_drop"]),
                    },
                    "detected_at": datetime.utcnow().isoformat(),
                })

            # Impossible scores (>100 or <0)
            impossible = df[(df["score"] > 100) | (df["score"] < 0)]
            for _, row in impossible.iterrows():
                anomalies.append({
                    "entity_id": int(row["student_id"]),
                    "entity_type": "grade",
                    "anomaly_type": "impossible_score",
                    "severity": "critical",
                    "score": 1.0,
                    "details": {"score": float(row["score"])},
                    "detected_at": datetime.utcnow().isoformat(),
                })

            # Missing entries where expected
            # (Would need expected assessments list)

        return anomalies


# ─── Inventory Anomaly Detector ───

class InventoryAnomalyDetector:
    """Detect inventory anomalies: consumption spikes, negative stock, shrinkage."""

    def __init__(self):
        self.version = "1.0.0"

    def detect(self, df: pd.DataFrame) -> List[Dict]:
        anomalies = []

        if "quantity" in df.columns and "item_id" in df.columns:
            # Negative stock
            negative = df[df["quantity"] < 0]
            for _, row in negative.iterrows():
                anomalies.append({
                    "entity_id": int(row["item_id"]),
                    "entity_type": "inventory_item",
                    "anomaly_type": "negative_stock",
                    "severity": "critical",
                    "score": 1.0,
                    "details": {"quantity": int(row["quantity"])},
                    "detected_at": datetime.utcnow().isoformat(),
                })

            # Consumption spike (quantity change > 3x median)
            if "consumption" in df.columns:
                df = df.sort_values(["item_id", "date"])
                df["consumption_pct_change"] = df.groupby("item_id")["consumption"].pct_change()

                spikes = df[df["consumption_pct_change"] > 3]
                for _, row in spikes.iterrows():
                    anomalies.append({
                        "entity_id": int(row["item_id"]),
                        "entity_type": "inventory_item",
                        "anomaly_type": "consumption_spike",
                        "severity": "medium",
                        "score": 0.7,
                        "details": {"factor": float(row["consumption_pct_change"])},
                        "detected_at": datetime.utcnow().isoformat(),
                    })

            # Shrinkage: physical count < system count
            if "physical_count" in df.columns and "system_count" in df.columns:
                shrinkage = df[df["physical_count"] < df["system_count"]]
                for _, row in shrinkage.iterrows():
                    anomalies.append({
                        "entity_id": int(row["item_id"]),
                        "entity_type": "inventory_item",
                        "anomaly_type": "shrinkage",
                        "severity": "high",
                        "score": 0.8,
                        "details": {
                            "physical": int(row["physical_count"]),
                            "system": int(row["system_count"]),
                            "missing": int(row["system_count"] - row["physical_count"]),
                        },
                        "detected_at": datetime.utcnow().isoformat(),
                    })

        return anomalies


# ─── Transport Anomaly Detector ───

class TransportAnomalyDetector:
    """Detect transport anomalies: route deviation, fuel theft, overcrowding."""

    def __init__(self):
        self.version = "1.0.0"

    def detect(self, df: pd.DataFrame) -> List[Dict]:
        anomalies = []

        # Fuel anomaly - consumption per km too high
        if "fuel_liters" in df.columns and "distance_km" in df.columns:
            df["fuel_per_km"] = df["fuel_liters"] / df["distance_km"].replace(0, np.nan)
            median_fuel = df["fuel_per_km"].median()

            high_fuel = df[df["fuel_per_km"] > median_fuel * 1.5]
            for _, row in high_fuel.iterrows():
                anomalies.append({
                    "entity_id": int(row.get("vehicle_id", 0)),
                    "entity_type": "vehicle",
                    "anomaly_type": "fuel_anomaly",
                    "severity": "medium",
                    "score": 0.7,
                    "details": {
                        "fuel_per_km": float(row["fuel_liters"] / row["distance_km"]),
                        "median": float(median_fuel),
                        "factor": float(row["fuel_liters"] / row["distance_km"] / median_fuel),
                    },
                    "detected_at": datetime.utcnow().isoformat(),
                })

        # Route deviation
        if "planned_route" in df.columns and "actual_route" in df.columns:
            # Simplified - would need geospatial comparison
            pass

        # Overcrowding
        if "passengers" in df.columns and "capacity" in df.columns:
            df["occupancy"] = df["passengers"] / df["capacity"]
            overcrowded = df[df["occupancy"] > 1.0]
            for _, row in overcrowded.iterrows():
                anomalies.append({
                    "entity_id": int(row.get("vehicle_id", 0)),
                    "entity_type": "vehicle",
                    "anomaly_type": "overcrowding",
                    "severity": "high",
                    "score": 0.9,
                    "details": {"occupancy_rate": float(row["occupancy"]), "passengers": int(row["passengers"]), "capacity": int(row["capacity"])},
                    "detected_at": datetime.utcnow().isoformat(),
                })

        return anomalies


# ─── Anomaly Orchestrator ───

class AnomalyOrchestrator:
    """Orchestrate all anomaly detectors."""

    def __init__(self):
        self.detectors = {
            "attendance": AttendanceAnomalyDetector(),
            "payments": PaymentAnomalyDetector(),
            "grades": GradeAnomalyDetector(),
            "inventory": InventoryAnomalyDetector(),
            "transport": TransportAnomalyDetector(),
        }
        self.version = "1.0.0"

    def detect_all(self, domain: str, df: pd.DataFrame) -> List[Dict]:
        """Run anomaly detection for a specific domain."""
        if domain not in self.detectors:
            return []

        detector = self.detectors[domain]
        return detector.detect(df)

    def detect_all_domains(self, data: Dict[str, pd.DataFrame]) -> Dict[str, List[Dict]]:
        """Run anomaly detection across all domains."""
        results = {}
        for domain, df in data.items():
            if domain in self.detectors:
                results[domain] = self.detectors[domain].detect(df)
        return results
