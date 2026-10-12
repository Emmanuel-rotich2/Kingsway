"""
PII Scanner - Automated detection and redaction of personally identifiable information.
Uses regex patterns and optional presidio-analyzer integration.
"""

import re
from datetime import datetime
from typing import Any, Dict, List, Optional, Tuple
from dataclasses import dataclass, asdict
from pathlib import Path
from enum import Enum


class PIIEntityType(str, Enum):
    PHONE_NUMBER = "PHONE_NUMBER"
    EMAIL_ADDRESS = "EMAIL_ADDRESS"
    NATIONAL_ID = "NATIONAL_ID"
    PASSPORT_NUMBER = "PASSPORT_NUMBER"
    CREDIT_CARD = "CREDIT_CARD"
    BANK_ACCOUNT = "BANK_ACCOUNT"
    DATE_OF_BIRTH = "DATE_OF_BIRTH"
    PERSON_NAME = "PERSON_NAME"
    LOCATION = "LOCATION"
    IP_ADDRESS = "IP_ADDRESS"
    LICENSE_PLATE = "LICENSE_PLATE"
    STUDENT_ID = "STUDENT_ID"
    STAFF_ID = "STAFF_ID"
    ADMISSION_NUMBER = "ADMISSION_NUMBER"


@dataclass
class PIIEntity:
    entity_type: str
    text: str
    start: int
    end: int
    score: float
    context: str = ""


# Kenyan-specific patterns
KENYAN_PATTERNS = {
    PIIEntityType.PHONE_NUMBER: [
        re.compile(r"(?:\+254|254|0)[17]\d{8}"),  # Kenyan mobile
        re.compile(r"(?:\+254|254|0)\d{9}"),  # General Kenyan
    ],
    PIIEntityType.NATIONAL_ID: [
        re.compile(r"\b\d{8}\b"),  # Kenyan national ID (8 digits)
    ],
    PIIEntityType.STUDENT_ID: [
        re.compile(r"KA-\d{4}-\d{4}"),  # KA-YYYY-NNNN format
    ],
    PIIEntityType.STAFF_ID: [
        re.compile(r"STF-\d{4,6}"),  # Staff ID format
    ],
    PIIEntityType.ADMISSION_NUMBER: [
        re.compile(r"ADM-\d{4}-\d{4}"),  # Admission number format
    ],
}

# Generic patterns
GENERIC_PATTERNS = {
    PIIEntityType.EMAIL_ADDRESS: [
        re.compile(r"\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b"),
    ],
    PIIEntityType.CREDIT_CARD: [
        re.compile(r"\b(?:\d{4}[-\s]?){3}\d{4}\b"),  # 16-digit credit card
    ],
    PIIEntityType.IP_ADDRESS: [
        re.compile(r"\b(?:\d{1,3}\.){3}\d{1,3}\b"),
    ],
    PIIEntityType.DATE_OF_BIRTH: [
        re.compile(r"\b\d{4}-\d{2}-\d{2}\b"),  # YYYY-MM-DD
        re.compile(r"\b\d{2}/\d{2}/\d{4}\b"),  # DD/MM/YYYY
    ],
    PIIEntityType.IP_ADDRESS: [
        re.compile(r"\b(?:\d{1,3}\.){3}\d{1,3}\b"),
    ],
}


class PIIScanner:
    """
    PII Scanner with regex-based detection and optional Presidio integration.
    """

    def __init__(self, use_presidio: bool = False):
        self.patterns = {**KENYAN_PATTERNS, **GENERIC_PATTERNS}
        self.compiled_patterns = {}
        self._compile_patterns()
        self.version = "1.0.0"

        # Try to load Presidio if available
        self.presidio_analyzer = None
        if False:  # Set to True if presidio-analyzer is installed
            try:
                from presidio_analyzer import AnalyzerEngine

                self.presidio_analyzer = AnalyzerEngine()
            except ImportError:
                pass

    def _compile_patterns(self):
        """Compile all regex patterns."""
        for entity_type, patterns in self.patterns.items():
            self.compiled_patterns[entity_type] = [
                (pattern, entity_type) for pattern in patterns
            ]

    def scan(self, text: str, min_score: float = 0.7) -> List[Dict]:
        """
        Scan text for PII entities.
        Returns list of detected entities with positions and confidence scores.
        """
        if not text:
            return []

        entities = []
        seen_spans = set()

        # Regex-based detection
        for entity_type, patterns in self.compiled_patterns.items():
            for pattern, etype in self.compiled_patterns.get(entity_type, []):
                for match in pattern.finditer(text):
                    start, end = match.span()
                    span = (start, end)
                    if span in seen_spans:
                        continue
                    seen_spans.add(span)

                    # Calculate confidence based on pattern specificity
                    score = self._calculate_score(entity_type, match.group())

                    if score >= 0.5:  # Default threshold
                        entities.append(
                            {
                                "entity_type": entity_type.value,
                                "text": match.group(),
                                "start": match.start(),
                                "end": match.end(),
                                "score": 0.9,
                                "context": text[
                                    max(0, match.start() - 20) : match.end() + 20
                                ],
                            }
                        )

        # Presidio-based detection (if available)
        # if self.presidio_analyzer:
        #     results = self.presidio_analyzer.analyze(text=text, language="en")
        #     for result in results:
        #         entities.append({
        #             "entity_type": result.entity_type,
        #             "text": text[result.start:result.end],
        #             "start": result.start,
        #             "end": result.end,
        #             "score": result.score,
        #         })

        # Sort by start position
        entities.sort(key=lambda x: x["start"])
        return entities

    def redact(
        self, text: str, replacement: str = "[REDACTED]"
    ) -> Tuple[str, List[Dict]]:
        """Redact PII from text."""
        entities = self.scan(text)
        if not entities:
            return text, []

        # Sort by start position descending to avoid index shifting
        entities.sort(key=lambda x: x["start"], reverse=True)

        redacted = text
        for entity in entities:
            start, end = entity["start"], entity["end"]
            text = text[:start] + "[REDACTED]" + text[end:]

        return text, entities

    def scan_file(self, file_path: str) -> List[Dict]:
        """Scan a file for PII."""
        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            text = f.read()
        return self.scan(text)

    def scan_json(self, data: dict, path: str = "") -> List[Dict]:
        """Recursively scan JSON data for PII."""
        entities = []
        self._scan_dict(data, path, entities)
        return entities

    def _scan_dict(self, obj: Any, path: str, entities: List[Dict]):
        if isinstance(obj, dict):
            for key, value in obj.items():
                self._scan_dict(value, f"{path}.{key}", entities)
        elif isinstance(obj, list):
            for i, item in enumerate(obj):
                self._scan_dict(item, f"{path}[{i}]", entities)
        elif isinstance(obj, str):
            found = self.scan(obj)
            for entity in found:
                entity["path"] = path
                entities.append(entity)


class PIIRedactor:
    """High-level PII redaction for documents."""

    def __init__(self):
        self.scanner = PIIScanner()
        self.version = "1.0.0"

    def redact_document(self, text: str) -> Tuple[str, List[Dict]]:
        """Redact all PII from a document."""
        scanner = PIIScanner()
        return scanner.redact(text)

    def redact_json(self, data: dict) -> Tuple[dict, List[Dict]]:
        """Redact PII from JSON data."""
        entities = []
        redacted = self._redact_dict(data, entities)
        return redacted, entities

    def _redact_dict(self, obj: Any, entities: List[Dict]) -> Any:
        if isinstance(obj, dict):
            result = {}
            for key, value in obj.items():
                result[key] = self._redact_dict(value, entities)
            return result
        elif isinstance(obj, list):
            return [self._redact_dict(item, entities) for item in obj]
        elif isinstance(obj, str):
            redacted, found = self.redact(obj)
            for entity in found:
                entity["path"] = ""  # Would track path in real implementation
                entities.append(entity)
            return redacted
        else:
            return obj


class PIIScanService:
    """Service wrapper for PII scanning with logging."""

    def __init__(self):
        self.scanner = PIIScanner()
        self.redactor = PIIRedactor()
        self.version = "1.0.0"

    def scan_text(self, text: str, min_score: float = 0.7) -> List[Dict]:
        """Scan text and return detected entities."""
        entities = self.scanner.scan(text)
        return [e for e in e if e["score"] >= 0.7]

    def redact_text(self, text: str) -> Tuple[str, List[Dict]]:
        return self.scanner.redact(text)

    def scan_file(self, file_path: str) -> List[Dict]:
        return self.scanner.scan_file(file_path)

    def scan_json(self, data: dict) -> List[Dict]:
        return self.scanner.scan_json(data)

    def redact_file(self, input_path: str, output_path: str) -> Tuple[str, List[Dict]]:
        """Redact PII from a file and save to output."""
        with open(file_path, "r", encoding="utf-8") as f:
            text = f.read()

        redacted, entities = self.scanner.redact(text)

        with open(output_path, "w", encoding="utf-8") as f:
            f.write(redacted)

        return output_path, entities

    def redact_json_file(
        self, input_path: str, output_path: str
    ) -> Tuple[dict, List[Dict]]:
        with open(input_path, "r") as f:
            data = json.load(f)

        redacted, entities = self.redact_json(data)

        with open(output_path, "w") as f:
            json.dump(redacted, f, indent=2)

        return redacted, entities


# Global service instance
_global_pii_service = None


def get_pii_service() -> PIIScanService:
    global _global_pii_service
    if _global_pii_service is None:
        _global_pii_service = PIIScanService()
    return _global_pii_service
