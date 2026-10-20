"""Generate the public FAQ corpus consumed by the PHP public assistant.

The public assistant grounds every answer in a bounded set of published
sources. Those sources change when a term opens/closes, a new academic year is
published, contacts change, or website content is edited. This module rebuilds
``storage/public/faqs.json`` from the PHP public website endpoint, so Python
(not a hand-edited JSON file) owns periodic refresh.
"""

from __future__ import annotations

import json
import os
import re
import tempfile
from datetime import datetime, timezone
from pathlib import Path
from urllib.error import URLError
from urllib.request import Request, urlopen

MAX_SOURCES = 60
MAX_CONTENT_CHARS = 4000

_MAINTAINER_FACTS = [
    {
        "id": "public_system_maintainer",
        "title": "School system maintenance",
        "content": (
            "The Kingsway school management system is maintained by AngiSoft "
            "Technologies. Public company information is available at "
            "https://www.angisoft.co.ke. The published school information does "
            "not claim that the founders developed the software."
        ),
    },
    {
        "id": "public_system_maintainer_contact",
        "title": "AngiSoft Technologies contact",
        "content": (
            "To contact AngiSoft Technologies, visit "
            "https://www.angisoft.co.ke/contact, call or send SMS/WhatsApp to "
            "+254710398690, or email info@angisoft.co.ke. AngiSoft Technologies "
            "is based in Nairobi, Kenya."
        ),
    },
]

_TEXT_FIELDS = (
    "content_value",
    "content",
    "description",
    "level_range",
    "event_title",
    "bio",
    "setting_value",
    "email",
    "phone",
)


def _slug(value: object) -> str:
    slug = re.sub(r"[^a-z0-9_]+", "_", str(value), flags=re.IGNORECASE).strip("_")
    return slug or "item"


def _flatten(items: object, prefix: str, label: str, sources: list[dict]) -> None:
    if not isinstance(items, dict):
        if not isinstance(items, list):
            return
    iterable = items.items() if isinstance(items, dict) else enumerate(items)

    for index, item in iterable:
        if len(sources) >= MAX_SOURCES:
            return
        source_id = f"{prefix}_{_slug(index)}"
        if isinstance(item, (str, int, float)):
            text = str(item).strip()
            if text:
                sources.append(
                    {
                        "id": source_id,
                        "title": label[:200],
                        "content": text[:MAX_CONTENT_CHARS],
                    }
                )
            continue
        if not isinstance(item, dict):
            continue

        parts = []
        for field in _TEXT_FIELDS:
            value = item.get(field)
            if value is None:
                continue
            text = str(value).strip()
            if text:
                parts.append(text)
        if not parts:
            skip = {
                "id",
                "sort_order",
                "display_order",
                "is_active",
                "created_at",
                "updated_at",
            }
            parts = [
                f"{key}: {value}"
                for key, value in item.items()
                if key not in skip
                and isinstance(value, (str, int, float))
                and str(value).strip()
            ]
        if parts:
            title = (
                item.get("title")
                or item.get("content_key")
                or item.get("name")
                or item.get("event_title")
                or item.get("term_name")
                or label
            )
            sources.append(
                {
                    "id": source_id,
                    "title": str(title)[:200],
                    "content": "\n".join(dict.fromkeys(parts))[:MAX_CONTENT_CHARS],
                }
            )
            continue

        _flatten(item, source_id, str(item.get("name") or label), sources)


def build_sources(payload: dict) -> list[dict]:
    sources: list[dict] = []

    blocks = payload.get("blocks")
    if isinstance(blocks, (list, dict)):
        _flatten(blocks, "public_blocks", "About Kingsway", sources)

    sections = payload.get("sections")
    if isinstance(sections, dict):
        term_dates = sections.get("term_dates")
        if isinstance(term_dates, list):
            _flatten(term_dates, "public_term_dates", "Term dates", sources)
            sections = {
                key: value for key, value in sections.items() if key != "term_dates"
            }

        for key, value in sections.items():
            if key in {"term_dates", "leadership_positions"}:
                continue
            if isinstance(value, (dict, list)):
                _flatten(value, f"public_{_slug(key)}", str(key), sources)
            if len(sources) >= MAX_SOURCES:
                break

    present = {source["id"] for source in sources}
    for fact in _MAINTAINER_FACTS:
        if fact["id"] not in present and len(sources) < MAX_SOURCES:
            sources.append(dict(fact))

    return sources[:MAX_SOURCES]


def _content_candidates(config) -> list[tuple[str, str]]:
    candidates: list[tuple[str, str]] = []
    internal = str(getattr(config, "php_internal_base_url", "") or "").rstrip("/")
    if internal:
        candidates.append(
            (internal, str(getattr(config, "php_internal_host", "") or ""))
        )
    public = str(getattr(config, "php_base_url", "") or "").rstrip("/")
    if public:
        candidates.append((public, ""))
    return candidates


def _fetch_published_content(config) -> dict:
    candidates = _content_candidates(config)
    if not candidates:
        raise RuntimeError("PHP base URL is not configured")

    last_error = "no address configured"
    for base, host in candidates:
        headers = {"Accept": "application/json", "User-Agent": "KingswayPublicFaq/1.0"}
        if host:
            headers["Host"] = host
        request = Request(f"{base}/api/website/content", headers=headers)
        try:
            with urlopen(request, timeout=20) as response:  # noqa: S310 - school host
                raw = response.read()
        except (URLError, OSError) as error:
            last_error = str(error)
            continue

        try:
            decoded = json.loads(raw.decode("utf-8"))
        except (UnicodeDecodeError, ValueError) as error:
            last_error = f"invalid JSON from {base}: {error}"
            continue
        payload = decoded.get("data") if isinstance(decoded, dict) else None
        if isinstance(payload, dict):
            return payload
        last_error = "published website content is unavailable"

    raise RuntimeError(last_error)


def refresh_public_faq(config) -> dict:
    payload = _fetch_published_content(config)
    sources = build_sources(payload)
    if not sources:
        raise RuntimeError("no published sources were found")

    now = datetime.now(timezone.utc)
    document = {
        "version": now.strftime("%Y-%m-%dT%H:%M:%SZ"),
        "generated_at": now.isoformat(),
        "sources": sources,
        "maintainer_fact": _MAINTAINER_FACTS[0]["content"],
    }

    target = Path(config.shared_storage_root) / "public" / "faqs.json"
    target.parent.mkdir(parents=True, exist_ok=True)
    fd, temporary_name = tempfile.mkstemp(
        dir=str(target.parent), prefix="faqs.", suffix=".json.tmp"
    )
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as handle:
            json.dump(document, handle, ensure_ascii=False, indent=2)
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(temporary_name, target)
    except Exception:
        try:
            os.unlink(temporary_name)
        except OSError:
            pass
        raise

    return {
        "path": str(target),
        "version": document["version"],
        "source_count": len(sources),
    }
