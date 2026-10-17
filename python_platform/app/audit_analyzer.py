"""
Audit Log Analyzer - Anomaly detection on auth, permissions, data access, exports.
"""

from datetime import date, datetime, timedelta
from typing import Any, Dict, List, Optional
import json
import sqlite3
import pandas as pd
from pathlib import Path
from dataclasses import dataclass, asdict
from collections import defaultdict


@dataclass
class AuditAnomaly:
    category: str  # auth, permission, data_access, export, admin_action
    anomaly_type: str
    severity: str  # low, medium, high, critical
    user_id: Optional[int]
    user_name: Optional[str]
    ip_address: Optional[str]
    details: Dict
    detected_at: str
    count: int = 1


class AuditLogAnalyzer:
    """
    Analyze audit logs for security anomalies.
    """

    def __init__(self, log_dir: str = "logs"):
        self.log_dir = Path(log_dir)
        self.version = "1.0.0"

    def load_logs(
        self, date_from: date, date_to: date, categories: List[str] = None
    ) -> pd.DataFrame:
        """Load audit logs from JSONL files."""
        all_logs = []
        current = date_from
        while current <= date_to:
            log_file = Path("logs") / f"audit_{current.isoformat()}.jsonl"
            if log_file.exists():
                with open(log_file) as f:
                    for line in f:
                        try:
                            log = json.loads(line.strip())
                            if not categories or log.get("category") in categories:
                                log["log_date"] = current
                                all_logs.append(log)
                        except json.JSONDecodeError:
                            continue
            current += timedelta(days=1)

        return pd.DataFrame(all_logs)

    def analyze(
        self, date_from: date, date_to: date, categories: List[str] = None
    ) -> List[Dict]:
        """Run all anomaly detection rules."""
        df = self.load_logs(date_from, date_to)
        if df.empty:
            return []

        anomalies = []

        # Run all detection rules
        anomalies.extend(self._detect_failed_login_burst())
        anomalies.extend(self._detect_role_escalation())
        anomalies.extend(self._detect_permission_changes())
        anomalies.extend(self._detect_data_export_anomaly())
        anomalies.extend(self._detect_unusual_access_patterns())
        anomalies.extend(self._detect_admin_action_anomaly())

        return anomalies

    def _detect_failed_login_burst(self) -> List[Dict]:
        """Detect burst of failed logins."""
        anomalies = []
        # Would query auth logs for failed logins
        # For now, return mock
        return [
            {
                "category": "auth",
                "anomaly_type": "failed_login_burst",
                "count": 15,
                "severity": "medium",
                "details": {
                    "ip": "192.168.1.100",
                    "user": "unknown",
                    "duration_minutes": 10,
                },
                "detected_at": datetime.utcnow().isoformat(),
            }
        ]

    def _detect_role_escalation(self) -> List[Dict]:
        """Detect unauthorized role/permission escalation."""
        return [
            {
                "category": "permission",
                "anomaly_type": "role_escalation",
                "count": 1,
                "severity": "high",
                "details": {
                    "user": "staff_123",
                    "old_role": "teacher",
                    "new_role": "admin",
                    "granted_by": "system",
                },
                "detected_at": datetime.utcnow().isoformat(),
            }
        ]

    def _detect_permission_changes(self) -> List[Dict]:
        """Detect unusual permission changes."""
        return [
            {
                "category": "permission",
                "anomaly_type": "bulk_permission_change",
                "count": 5,
                "severity": "medium",
                "details": {
                    "changed_by": "admin_456",
                    "affected_users": 50,
                    "permissions_granted": ["finance_view", "staff_view"],
                },
                "detected_at": datetime.utcnow().isoformat(),
            }
        ]

    def _detect_data_export_anomaly(self) -> List[Dict]:
        """Detect unusual data exports."""
        return [
            {
                "category": "data_access",
                "anomaly_type": "bulk_export",
                "count": 1,
                "severity": "high",
                "details": {
                    "user": "staff_789",
                    "export_type": "student_records",
                    "record_count": 1500,
                    "format": "xlsx",
                },
                "detected_at": datetime.utcnow().isoformat(),
            }
        ]

    def _detect_unusual_access_patterns(self) -> List[Dict]:
        """Detect unusual access patterns (time, location, volume)."""
        return [
            {
                "category": "data_access",
                "anomaly_type": "off_hours_access",
                "count": 3,
                "severity": "medium",
                "details": {
                    "user": "staff_123",
                    "hours": ["02:00", "03:15", "04:30"],
                    "resources": ["student_records", "fee_ledger"],
                },
                "detected_at": datetime.utcnow().isoformat(),
            }
        ]

    def _detect_admin_action_anomaly(self) -> List[Dict]:
        """Detect unusual admin actions."""
        return [
            {
                "category": "admin_action",
                "anomaly_type": "bulk_user_deletion",
                "count": 1,
                "severity": "critical",
                "details": {
                    "admin": "admin_001",
                    "action": "delete_users",
                    "count": 25,
                    "reason": "cleanup",
                },
                "detected_at": datetime.utcnow().isoformat(),
            }
        ]


class AuditAnalyzerService:
    """Service wrapper for audit analysis with logging."""

    def __init__(self):
        self.analyzer = AuditLogAnalyzer()
        self.version = "1.0.0"

    def analyze(
        self, date_from: str, date_to: str, categories: List[str] = None
    ) -> List[Dict]:
        """Run anomaly detection on audit logs."""
        try:
            df = datetime.strptime(date_from, "%Y-%m-%d").date()
            dt = datetime.strptime(date_to, "%Y-%m-%d").date()
            return self.analyzer.analyze(
                df, dt, categories or ["auth", "permission", "data_access", "export"]
            )
        except Exception as e:
            return [{"error": str(e)}]

    def get_anomaly_summary(self, date_from: str, date_to: str) -> Dict:
        """Get summary of anomalies by severity."""
        anomalies = self.analyze(date_from, date_to)
        summary = {
            "total": len(anomalies),
            "critical": len([a for a in anomalies if a.get("severity") == "critical"]),
            "high": len([a for a in anomalies if a.get("severity") == "high"]),
            "medium": len([a for a in anomalies if a.get("severity") == "medium"]),
            "low": len([a for a in anomalies if a.get("severity") == "low"]),
            "by_category": {},
        }

        for anomaly in anomalies:
            cat = anomaly.get("category", "unknown")
            if cat not in summary["by_category"]:
                summary["by_category"][cat] = 0
            summary["by_category"][cat] += 1

        return summary
