"""Bounded, deterministic document-type verification for school uploads.

This is an AI-assisted gate in the narrow, testable sense: it uses the text
already extracted from an authorized upload (native text or bounded OCR) and
looks for strong document-type evidence before a human reviewer opens the file.
It never trusts the filename, never invents a document type, and never rejects
on uncertainty. Only a confident conflict with the requested type becomes an
automatic rejection; everything else is referred to a school administrator.
"""

from __future__ import annotations

import re
from typing import Any

# Strong signals are weighted heavily: one is enough to establish a document
# type. Supporting signals only refine a decision and can never reject alone.
DOCUMENT_RULES: dict[str, dict[str, Any]] = {
    "birth_certificate": {
        "label": "Birth Certificate",
        "strong": (
            "birth certificate",
            "certificate of birth",
            "certificate of registration of birth",
            "registrar of births",
            "birth registration",
        ),
        "support": (
            "date of birth",
            "place of birth",
            "registration number",
            "registration no",
            "district registrar",
            "mother name",
            "father name",
            "sex",
        ),
    },
    "parent_id": {
        "label": "Parent/Guardian ID",
        "strong": (
            "national identity card",
            "national identity",
            "national id",
            "identity card",
            "alien identity card",
            "alien id",
            "foreign national identity",
            "passport no",
            "passport number",
            "republic of kenya passport",
            "kenyan passport",
            "re: p[<]{1,2}(ken|KEN)",
        ),
        "support": (
            "id number",
            "identity number",
            "card number",
            "serial number",
            "nationality",
            "date of birth",
            "passport",
            "photo",
        ),
    },
    "previous_school_report": {
        "label": "Previous School Report",
        "strong": (
            "previous school report",
            "school leaving report",
            "end of term report",
            "end of year report",
        ),
        "support": (
            "school report",
            "progress report",
            "teacher remarks",
            "attendance",
            "term",
            "subject",
        ),
    },
    "progress_report": {
        "label": "Progress Report",
        "strong": (
            "progress report",
            "latest progress report",
            "pupil progress report",
            "learner progress report",
        ),
        "support": (
            "school report",
            "teacher remarks",
            "attendance",
            "term",
            "subject",
            "grade",
        ),
    },
    "medical_records": {
        "label": "Health / Medical Records",
        "strong": (
            "medical report",
            "medical form",
            "health record",
            "immunization record",
            "clinical record",
        ),
        "support": (
            "patient",
            "diagnosis",
            "condition",
            "medication",
            "doctor",
            "hospital",
            "clinic",
        ),
    },
    "immunization_card": {
        "label": "Immunization Card",
        "strong": (
            "immunization card",
            "immunisation card",
            "vaccination card",
            "vaccination record",
            "child health card",
        ),
        "support": (
            "vaccine",
            "dose",
            "date administered",
            "next appointment",
            "clinic",
        ),
    },
    "leaving_certificate": {
        "label": "Leaving Certificate",
        "strong": (
            "leaving certificate",
            "school leaving certificate",
            "transfer certificate",
        ),
        "support": (
            "previous school",
            "transferred",
            "headteacher",
            "date of leaving",
        ),
    },
    "transfer_letter": {
        "label": "Transfer Letter",
        "strong": (
            "transfer letter",
            "letter of transfer",
            "school transfer",
        ),
        "support": (
            "previous school",
            "transferred",
            "headteacher",
            "date of leaving",
        ),
    },
    "invoice": {
        "label": "Invoice / Receipt",
        "strong": (
            "invoice",
            "tax invoice",
            "payment receipt",
            "receipt",
        ),
        "support": (
            "amount",
            "total",
            "bank",
            "paid",
            "balance",
            "payment",
        ),
    },
    "timetable": {
        "label": "Timetable / Exam Schedule",
        "strong": (
            "timetable",
            "time table",
            "exam timetable",
            "examination timetable",
            "exam schedule",
        ),
        "support": (
            "monday",
            "tuesday",
            "wednesday",
            "thursday",
            "friday",
            "venue",
            "time",
        ),
    },
    "exam_results": {
        "label": "Exam Results",
        "strong": (
            "exam results",
            "examination results",
            "candidate number",
            "candidate no",
            "results slip",
        ),
        "support": (
            "marks",
            "score",
            "grade",
            "subject",
            "candidate",
        ),
    },
}

# These have an expected label but no safe automatic rejection rule. A photo
# must be seen by a person; an unknown type has no positive evidence at all.
REVIEW_ONLY_TYPES = {
    "passport_photo": "Passport Photograph",
    "other": "Other Document",
}

MAX_SIGNALS_SHOWN = 6
MIN_TEXT_CHARS = 24


def _normalise_text(text: str) -> str:
    lowered = str(text or "").lower()
    return re.sub(r"[^a-z0-9]+", " ", lowered).strip()


def _signal_matches(text: str, signals: tuple[str, ...]) -> list[str]:
    normalised = _normalise_text(text)
    lowered = str(text or "").lower()
    matches: list[str] = []
    for signal in signals:
        if signal.startswith("re:"):
            found = re.search(signal[3:], lowered) is not None
        else:
            found = _normalise_text(signal) in normalised
        if found:
            matches.append(signal.replace("re:", "").strip())
    return matches


def _score(text: str, document_type: str) -> dict[str, Any]:
    rule = DOCUMENT_RULES[document_type]
    strong = _signal_matches(text, rule["strong"])
    supporting = _signal_matches(text, rule["support"])
    score = len(strong) * 3.0 + min(len(supporting), 4) * 0.5
    return {
        "document_type": document_type,
        "label": str(rule["label"]),
        "score": round(score, 2),
        "strong": strong,
        "support": supporting,
    }


def _id_subtype(text: str) -> str | None:
    """Return the concrete identity-document family when it can be named."""
    normalised = _normalise_text(text)
    lowered = str(text or "").lower()
    if "alien" in normalised or "foreign national" in normalised:
        return "alien_id"
    if "passport no" in normalised or "passport number" in normalised:
        return "passport"
    if re.search(r"p[<]{1,2}(ken|KEN)", lowered):
        return "passport"
    if "republic of kenya passport" in normalised or "kenyan passport" in normalised:
        return "passport"
    if (
        "national identity" in normalised
        or "national id" in normalised
        or "identity card" in normalised
    ):
        return "national_id"
    return None


def _result(
    *,
    expected_type: str,
    expected_label: str,
    decision: str,
    confidence: float,
    detected: dict[str, Any] | None,
    reasons: list[str],
    subtype: str | None = None,
    text_chars: int = 0,
) -> dict[str, Any]:
    return {
        "expected_type": expected_type,
        "expected_label": expected_label,
        "detected_type": (detected or {}).get("document_type", "other"),
        "detected_label": (detected or {}).get("label", "Unidentified document"),
        "decision": decision,
        "confidence": round(max(0.0, min(1.0, confidence)), 2),
        "identity_subtype": subtype,
        "matched_signals": (detected or {}).get("strong", [])[:MAX_SIGNALS_SHOWN],
        "conflicting_signals": (detected or {}).get("strong", [])[:MAX_SIGNALS_SHOWN]
        if decision == "mismatch"
        else [],
        "text_chars": text_chars,
        "reasons": reasons[:4],
        "requires_human_review": decision in {"needs_review", "not_applicable"},
        "auto_reject": decision == "mismatch" and confidence >= 0.8,
    }


def verify_document(
    expected_type: str,
    text: str,
    *,
    extension: str = "",
    filename: str = "",
) -> dict[str, Any]:
    """Verify extracted text against the document type the form requested.

    ``filename`` is accepted for forward compatibility but deliberately unused:
    a file named ``birth_certificate.pdf`` containing an invoice must not pass.
    """
    del extension, filename  # never trust names or extensions for verification

    expected = (
        str(expected_type or "").strip().lower().replace("-", "_").replace(" ", "_")
    )
    text = str(text or "")
    text_chars = len(text.strip())

    if not expected:
        return _result(
            expected_type="",
            expected_label="Unspecified document",
            decision="not_applicable",
            confidence=0.0,
            detected=None,
            reasons=["No document type was requested by the upload form."],
            text_chars=text_chars,
        )

    if expected in REVIEW_ONLY_TYPES:
        return _result(
            expected_type=expected,
            expected_label=REVIEW_ONLY_TYPES[expected],
            decision="needs_review",
            confidence=0.3,
            detected=None,
            reasons=[
                "This document type requires visual confirmation by school staff."
            ],
            text_chars=text_chars,
        )

    if expected not in DOCUMENT_RULES:
        return _result(
            expected_type=expected,
            expected_label=expected.replace("_", " ").title(),
            decision="needs_review",
            confidence=0.2,
            detected=None,
            reasons=["The requested document type has no automatic verification rule."],
            text_chars=text_chars,
        )

    if text_chars < MIN_TEXT_CHARS:
        return _result(
            expected_type=expected,
            expected_label=str(DOCUMENT_RULES[expected]["label"]),
            decision="needs_review",
            confidence=0.2,
            detected=None,
            reasons=[
                (
                    "The upload contains too little readable text for automatic verification. "
                    "Refer it to a school administrator."
                )
            ],
            text_chars=text_chars,
        )

    scored = [_score(text, name) for name in DOCUMENT_RULES]
    by_type = {item["document_type"]: item for item in scored}
    expected_score = by_type[expected]
    others = sorted(
        (item for item in scored if item["document_type"] != expected),
        key=lambda item: item["score"],
        reverse=True,
    )
    strongest_other = others[0] if others else None

    expected_strong = len(expected_score["strong"])
    other_strong = len(strongest_other["strong"]) if strongest_other else 0
    other_score = float(strongest_other["score"]) if strongest_other else 0.0

    subtype = _id_subtype(text) if expected == "parent_id" else None
    # A photograph labelled "passport" is not a passport document. Require a
    # real passport number, MRZ or nationality field before accepting that subtype.
    normalised = _normalise_text(text)
    if (
        expected == "parent_id"
        and subtype == "passport"
        and "passport photo" in normalised
        and not any(
            signal in normalised
            for signal in ("passport no", "passport number", "nationality")
        )
        and not re.search(r"p[<]{1,2}(ken|KEN)", str(text or "").lower())
    ):
        subtype = None

    if other_strong >= 1 and (
        expected_strong == 0 or other_score >= float(expected_score["score"]) + 1.5
    ):
        confidence = min(0.96, 0.8 + 0.04 * other_strong)
        reasons = [
            (
                f"Strong {strongest_other['label']} indicators were found: "
                f"{', '.join(strongest_other['strong'][:3])}."
            ),
            (
                f"Expected {expected_score['label']} indicators were "
                f"{'absent' if expected_strong == 0 else 'outweighed'}."
            ),
        ]
        return _result(
            expected_type=expected,
            expected_label=str(DOCUMENT_RULES[expected]["label"]),
            decision="mismatch",
            confidence=confidence,
            detected=strongest_other,
            reasons=reasons,
            subtype=subtype,
            text_chars=text_chars,
        )

    if (
        expected_strong >= 1
        and float(expected_score["score"]) >= 3.0
        and (other_strong == 0 or float(expected_score["score"]) >= other_score + 1.5)
    ):
        confidence = min(
            0.96,
            0.78
            + 0.05 * expected_strong
            + 0.02 * min(len(expected_score["support"]), 4),
        )
        reasons = [
            (
                f"Strong {expected_score['label']} indicators were found: "
                f"{', '.join(expected_score['strong'][:3])}."
            )
        ]
        if subtype:
            reasons.append(f"Identity document family: {subtype.replace('_', ' ')}.")
        return _result(
            expected_type=expected,
            expected_label=str(DOCUMENT_RULES[expected]["label"]),
            decision="match",
            confidence=confidence,
            detected=expected_score,
            reasons=reasons,
            subtype=subtype,
            text_chars=text_chars,
        )

    reasons = [
        (
            "Document-type evidence is incomplete or conflicting. "
            "Refer the upload to a school administrator for review."
        )
    ]
    if expected_strong == 0:
        reasons.append(f"No strong {expected_score['label']} indicator was found.")
    if other_strong:
        reasons.append(
            f"Other document indicators were present: "
            f"{', '.join(strongest_other['strong'][:3])}."
        )
    return _result(
        expected_type=expected,
        expected_label=str(DOCUMENT_RULES[expected]["label"]),
        decision="needs_review",
        confidence=0.55,
        detected=expected_score
        if float(expected_score["score"]) >= float(strongest_other["score"])
        else strongest_other,
        reasons=reasons,
        subtype=subtype,
        text_chars=text_chars,
    )
