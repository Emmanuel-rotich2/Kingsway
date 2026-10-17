"""
Backup & Restore - Automated Parquet snapshots + Point-in-time restore.
"""

from datetime import date, datetime, timedelta
from typing import Any, Dict, List, Optional
import json
import shutil
import tarfile
import hashlib
from pathlib import Path
from dataclasses import dataclass, asdict, field

from .config import Config, APP_ROOT


@dataclass
class BackupSnapshot:
    snapshot_id: str
    created_at: str
    size_mb: float
    include: List[str]
    status: str  # creating, completed, failed
    checksum: str = ""
    tables: List[str] = field(default_factory=list)
    error: Optional[str] = None


@dataclass
class RestoreJob:
    restore_id: str
    snapshot_id: str
    started_at: str
    completed_at: Optional[str] = None
    status: str = "running"
    tables_restored: int = 0
    error: Optional[str] = None


class BackupManager:
    """Manage Parquet snapshots and point-in-time restores."""

    def __init__(self, base_path: Optional[str] = None):
        # Storage root comes from Config (STORAGE_ROOT/python by default);
        # base_path stays as an explicit override for tests.
        cfg = Config()
        self.storage_dir = cfg.storage_dir
        self.repo_root = cfg.repo_root
        self.base_path = Path(base_path) if base_path else self.storage_dir / "buffers"
        self.snapshots_dir = self.base_path / "snapshots"
        self.snapshots_dir.mkdir(parents=True, exist_ok=True)
        self.metadata_file = self.snapshots_dir / "metadata.json"
        self._load_metadata()

    def _load_metadata(self):
        self.metadata_file.parent.mkdir(parents=True, exist_ok=True)
        if self.metadata_file.exists():
            with open(self.metadata_file) as f:
                self.metadata = json.load(f)
        else:
            self.metadata = {"snapshots": {}}

    def _save_metadata(self):
        with open(self.metadata_file, "w") as f:
            json.dump(self.metadata, f, indent=2, default=str)

    def create_snapshot(
        self, job_id: str, include: Optional[List[str]] = None
    ) -> BackupSnapshot:
        """Create a snapshot of specified data."""
        include = include or ["parquet", "models", "configs"]
        snapshot_id = f"snap-{datetime.now().strftime('%Y%m%d-%H%M%S')}-{job_id[:8]}"
        snapshot_dir = self.snapshots_dir / snapshot_id
        snapshot_dir.mkdir(parents=True, exist_ok=True)

        snapshot = BackupSnapshot(
            snapshot_id=snapshot_id,
            created_at=datetime.utcnow().isoformat(),
            size_mb=0,
            include=include,
            status="creating",
        )

        self.metadata["snapshots"][snapshot_id] = asdict(snapshot)
        self._save_metadata()

        try:
            total_size = 0
            tables = []

            if "parquet" in include:
                # Backup parquet data lake
                parquet_dir = self.base_path / "parquet"
                if parquet_dir.exists():
                    dest = snapshot_dir / "parquet"
                    shutil.copytree(parquet_dir, dest)
                    size = sum(f.stat().st_size for f in dest.rglob("*") if f.is_file())
                    tables.append(f"parquet:{size}")

            if "models" in include:
                models_dir = APP_ROOT / "models"
                if models_dir.exists():
                    dest = snapshot_dir / "models"
                    shutil.copytree(models_dir, dest)
                    size = sum(f.stat().st_size for f in dest.rglob("*") if f.is_file())
                    tables.append(f"models:{size}")

            if "configs" in include:
                configs = [
                    "config/.env",
                    "config/config_production.php",
                    "config/config_development.php",
                    "python_platform/app/config.py",
                ]
                configs_dir = snapshot_dir / "configs"
                configs_dir.mkdir(parents=True, exist_ok=True)
                for cfg in configs:
                    src = self.repo_root / cfg
                    if src.exists():
                        dest = configs_dir / src.name
                        shutil.copy2(src, dest)

            # Create compressed archive
            archive_path = self.snapshots_dir / f"{snapshot_id}.tar.gz"
            with tarfile.open(archive_path, "w:gz") as tar:
                tar.add(snapshot_dir, arcname=snapshot_id)

            archive_size = archive_path.stat().st_size / (1024 * 1024)

            # Calculate checksum
            import hashlib

            with open(archive_path, "rb") as f:
                checksum = hashlib.sha256(f.read()).hexdigest()

            # Update metadata
            snapshot.size_mb = round(archive_size, 2)
            snapshot.status = "completed"
            snapshot.checksum = checksum
            snapshot.tables = tables

            self.metadata["snapshots"][snapshot_id] = asdict(snapshot)
            self._save_metadata()

            return snapshot

        except Exception as e:
            snapshot.status = "failed"
            snapshot.error = str(e)
            self.metadata["snapshots"][snapshot_id] = asdict(snapshot)
            self._save_metadata()
            raise

    def list_snapshots(self) -> List[Dict]:
        """List all available snapshots."""
        return list(self.metadata.get("snapshots", {}).values())

    def get_snapshot(self, snapshot_id: str) -> Optional[Dict]:
        return self.metadata.get("snapshots", {}).get(snapshot_id)

    def delete_snapshot(self, snapshot_id: str) -> bool:
        """Delete a snapshot."""
        if snapshot_id in self.metadata.get("snapshots", {}):
            # Remove directory and archive
            snap_dir = self.snapshots_dir / snapshot_id
            archive = self.snapshots_dir / f"{snapshot_id}.tar.gz"
            if snap_dir.exists():
                shutil.rmtree(snap_dir)
            if archive.exists():
                archive.unlink()

            del self.metadata["snapshots"][snapshot_id]
            self._save_metadata()
            return True
        return False


class RestoreManager:
    """Manage point-in-time restores from snapshots."""

    def __init__(self, backup_manager: BackupManager):
        self.backup_manager = backup_manager
        self.jobs: Dict[str, Dict] = {}

    def restore_snapshot(
        self, job_id: str, snapshot_id: str, target: str = "full"
    ) -> Dict:
        """Restore from a snapshot."""
        snapshot = self.backup_manager.get_snapshot(snapshot_id)
        if not snapshot:
            raise ValueError(f"Snapshot {snapshot_id} not found")

        if snapshot["status"] != "completed":
            raise ValueError(
                f"Snapshot {snapshot_id} not ready (status: {snapshot['status']})"
            )

        # Verify checksum
        archive_path = self.backup_manager.snapshots_dir / f"{snapshot_id}.tar.gz"
        import hashlib

        with open(archive_path, "rb") as f:
            actual_checksum = hashlib.sha256(f.read()).hexdigest()
        if actual_checksum != snapshot.get("checksum"):
            raise ValueError("Snapshot checksum mismatch - data may be corrupted")

        restore_id = f"restore-{datetime.now().strftime('%Y%m%d-%H%M%S')}"
        job = {
            "restore_id": restore_id,
            "snapshot_id": snapshot_id,
            "started_at": datetime.utcnow().isoformat(),
            "status": "running",
            "tables_restored": 0,
            "target": target,
        }

        try:
            # Extract archive
            extract_dir = self.backup_manager.base_path / "restores" / restore_id
            extract_dir.mkdir(parents=True, exist_ok=True)

            with tarfile.open(archive_path, "r:gz") as tar:
                tar.extractall(extract_dir)

            snap_dir = extract_dir / snapshot_id
            tables_restored = 0

            # Restore parquet
            if (snap_dir / "parquet").exists():
                dest = self.backup_manager.base_path / "parquet"
                if dest.exists():
                    shutil.rmtree(dest)
                shutil.copytree(snap_dir / "parquet", dest)
                tables_restored += 1

            # Restore models
            if (snap_dir / "models").exists():
                dest = APP_ROOT / "models"
                if dest.exists():
                    shutil.rmtree(dest)
                shutil.copytree(snap_dir / "models", dest)
                tables_restored += 1

            # Restore configs (basenames only — returned to the PHP repo root,
            # not the service CWD the pre-storage-hardening code wrote to)
            if (snap_dir / "configs").exists():
                for cfg_file in (snap_dir / "configs").iterdir():
                    dest = self.backup_manager.repo_root / cfg_file.name
                    if dest.exists():
                        dest.unlink()
                    shutil.copy2(cfg_file, dest)
                tables_restored += 1

            result = {
                "restore_id": restore_id,
                "snapshot_id": snapshot_id,
                "status": "completed",
                "tables_restored": tables_restored,
                "completed_at": datetime.utcnow().isoformat(),
            }

            return result

        except Exception as e:
            return {
                "restore_id": restore_id,
                "snapshot_id": snapshot_id,
                "status": "failed",
                "error": str(e),
            }

    def list_restore_jobs(self) -> List[Dict]:
        return list(self.jobs.values())
