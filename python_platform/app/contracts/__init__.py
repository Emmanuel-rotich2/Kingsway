"""
Contracts package - Pydantic schemas, validation, and registry.
"""

from .schemas import (
    CONTRACT_REGISTRY,
    BaseJobPayload,
    JobResult,
    JobMeta,
)

from .registry import (
    ContractRegistry,
    get_contract_registry,
    validate_contract,
    get_contract_schema,
)

__all__ = [
    "CONTRACT_REGISTRY",
    "BaseJobPayload",
    "JobResult",
    "JobMeta",
    "validate_contract",
    "ContractRegistry",
    "get_contract_registry",
    "get_contract_schema",
]
