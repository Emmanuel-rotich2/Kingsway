"""
Contract Registry - Versioned schema registry with validation.
Supports Avro/Protobuf-like versioning with JSON Schema export.
"""

from datetime import datetime
from typing import Any, Dict, List, Optional, Type
import json
import hashlib
from pathlib import Path

from .schemas import CONTRACT_REGISTRY, BaseJobPayload as JobPayload


class ContractVersion:
    def __init__(self, version: str, schema: Dict[str, Any], created_at: datetime):
        self.version = version
        self.schema = schema
        self.created_at = created_at
        self.hash = hashlib.sha256(
            json.dumps(schema, sort_keys=True).encode()
        ).hexdigest()[:16]


class ContractRegistry:
    """
    Versioned contract registry with backward compatibility checking.
    """

    def __init__(self, registry_dir: str = "contracts/versions"):
        self.registry_dir = Path(registry_dir)
        self.registry_dir.mkdir(parents=True, exist_ok=True)
        self._contracts: Dict[str, Dict[str, ContractVersion]] = {}
        self._load_versions()

    def _load_versions(self):
        """Load all versioned contracts from disk."""
        for contract_file in self.registry_dir.glob("*.json"):
            try:
                with open(contract_file) as f:
                    data = json.load(f)
                contract_name = contract_file.stem
                self._contracts[contract_name] = {}
                for version, schema_data in data.get("versions", {}).items():
                    self._contracts[contract_name][version] = ContractVersion(
                        version=version,
                        schema=schema_data["schema"],
                        created_at=datetime.fromisoformat(schema_data["created_at"]),
                    )
            except Exception:
                pass

    def register(
        self, contract_name: str, schema: Dict[str, Any], version: Optional[str] = None
    ) -> str:
        """Register a new contract version."""
        if contract_name not in CONTRACT_REGISTRY:
            raise ValueError(f"Unknown contract: {contract_name}")

        # Generate version if not provided
        if version is None:
            existing = self._contracts.get(contract_name, {})
            version = f"v{len(existing) + 1}.0.0"

        # Validate against Pydantic model
        model_class = CONTRACT_REGISTRY[contract_name]
        model_class.model_validate(schema)

        version_obj = ContractVersion(version, schema, datetime.utcnow())
        if contract_name not in self._contracts:
            self._contracts[contract_name] = {}
        self._contracts[contract_name][version] = version_obj
        self._persist(contract_name)
        return version

    def get_latest(self, contract_name: str) -> Optional[ContractVersion]:
        """Get latest version of a contract."""
        versions = self._contracts.get(contract_name, {})
        if not versions:
            return None
        return max(versions.values(), key=lambda v: v.created_at)

    def get_version(
        self, contract_name: str, version: str
    ) -> Optional[ContractVersion]:
        return self._contracts.get(contract_name, {}).get(version)

    def validate(
        self, contract_name: str, data: Dict[str, Any], version: Optional[str] = None
    ) -> tuple[bool, List[str]]:
        """Validate data against contract schema."""
        version_obj = (
            self.get_version(contract_name, version)
            if version
            else self.get_latest(contract_name)
        )
        if not version_obj:
            return False, [f"Contract {contract_name} not found"]

        # Validate required fields
        schema = version_obj.schema
        required = schema.get("required", [])
        properties = schema.get("properties", {})

        errors = []
        for field in required:
            if field not in data:
                errors.append(f"Missing required field: {field}")

        # Type validation (basic)
        for field, value in data.items():
            if field in properties:
                prop_schema = properties[field]
                expected_type = prop_schema.get("type")
                if expected_type and not self._check_type(value, expected_type):
                    errors.append(
                        f"Field {field}: expected {expected_type}, got {type(value).__name__}"
                    )

        return len(errors) == 0, errors

    def _check_type(self, value: Any, expected_type: str) -> bool:
        type_map = {
            "string": str,
            "integer": int,
            "number": (int, float),
            "boolean": bool,
            "array": list,
            "object": dict,
        }
        expected = type_map.get(expected_type)
        if expected is None:
            return True
        return isinstance(value, expected)

    def _persist(self, contract_name: str):
        """Persist contract versions to disk."""
        contract_dir = self.registry_dir / contract_name
        contract_dir.mkdir(parents=True, exist_ok=True)

        versions_data = {}
        for version, vobj in self._contracts[contract_name].items():
            versions_data[version] = {
                "schema": vobj.schema,
                "created_at": vobj.created_at.isoformat(),
                "hash": vobj.hash,
            }

        with open(contract_dir / "versions.json", "w") as f:
            json.dump(
                {
                    "contract": contract_name,
                    "latest_version": max(self._contracts[contract_name].keys()),
                    "versions": versions_data,
                },
                f,
                indent=2,
            )

    def get_json_schema(
        self, contract_name: str, version: Optional[str] = None
    ) -> Dict[str, Any]:
        """Export contract as JSON Schema."""
        version_obj = (
            self.get_version(contract_name, version)
            if version
            else self.get_latest(contract_name)
        )
        if not version_obj:
            return {}
        return {
            "$schema": "http://json-schema.org/draft-07/schema#",
            "title": contract_name,
            "version": version_obj.version,
            "type": "object",
            **version_obj.schema,
        }

    def check_compatibility(
        self, contract_name: str, old_version: str, new_version: str
    ) -> tuple[bool, List[str]]:
        """Check backward compatibility between versions."""
        old = self.get_version(contract_name, old_version)
        new = self.get_version(contract_name, new_version)
        if not old or not new:
            return False, ["Version not found"]

        old_props = old.schema.get("properties", {})
        new_props = new.schema.get("properties", {})
        old_required = set(old.schema.get("required", []))
        new_required = set(new.schema.get("required", []))

        errors = []

        # Check required fields not removed
        removed_required = old_required - new_required
        if removed_required:
            errors.append(f"Required fields removed: {removed_required}")

        # Check field types not changed incompatibly
        for field, old_prop in old_props.items():
            if field in new_props:
                old_type = old_prop.get("type")
                new_type = new_props[field].get("type")
                if old_type != new_type:
                    errors.append(
                        f"Field {field} type changed: {old_type} -> {new_type}"
                    )

        return len(errors) == 0, errors


# Global registry instance
_global_registry: Optional[ContractRegistry] = None


def get_contract_registry() -> ContractRegistry:
    global _global_registry
    if _global_registry is None:
        _global_registry = ContractRegistry()
    return _global_registry


def validate_contract(
    contract_name: str, data: Dict[str, Any], version: Optional[str] = None
) -> tuple[bool, List[str]]:
    """Convenience function to validate contract data."""
    registry = get_contract_registry()
    return registry.validate(contract_name, data, version)


def get_contract_schema(
    contract_name: str, version: Optional[str] = None
) -> Dict[str, Any]:
    """Get JSON schema for a contract."""
    registry = get_contract_registry()
    return registry.get_json_schema(contract_name, version)
