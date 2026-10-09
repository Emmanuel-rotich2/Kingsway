"""Staged contracts for the Python batch families.

These names are registered for planning and deployment parity only. They are
intentionally disabled until PHP supplies per-job authorization, bounded input
and validated artifact ownership. Do not add them to RealtimeController's
active Python types before that boundary is complete.
"""

from __future__ import annotations

from typing import Any


BATCH_FAMILY_CONTRACTS: dict[str, dict[str, Any]] = {
    "academic.report_card_batch": {
        "enabled": False,
        "input_boundary": "PHP must reauthorize operator, term, cohort and release scope",
        "output_boundary": "PHP must validate staged PDFs and own approval/release",
        "idempotency": "stable request key plus immutable input/version fingerprint",
        "sensitivity": "learner educational records; minimize fields and never log content",
    },
    "media.photo_normalize": {
        "enabled": False,
        "input_boundary": "PHP must authorize source asset and provide a bounded staged file handle",
        "output_boundary": "PHP must validate and atomically own normalized derivative metadata",
        "idempotency": "source content hash plus target profile/version",
        "sensitivity": "learner/staff images; private storage only, no public path from worker",
    },
    "analytics.parquet_buffer": {
        "enabled": False,
        "input_boundary": "PHP must authorize a named aggregate projection and bounded date scope",
        "output_boundary": "PHP must validate buffer descriptor and retain lifecycle ownership",
        "idempotency": "projection name plus normalized scope and source as-of/version",
        "sensitivity": "aggregate-only; no learner-level data or credentials in artifacts",
    },
}


def is_batch_family_enabled(job_type: str) -> bool:
    """Return the explicit activation gate; all new families remain inert."""
    contract = BATCH_FAMILY_CONTRACTS.get(job_type)
    return bool(contract and contract["enabled"])
