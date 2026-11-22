"""Audience-scoped assistant skills.

Every assistant that used to be written in PHP lives here now. PHP only
authenticates, authorizes, and relays the bounded envelope; this module owns:

  * prompt policy / field minimization
  * prompt template resolution
  * provider invocation
  * output confidentiality scrubbing
  * citation and escalation enforcement
"""

from __future__ import annotations

import json
from typing import Any

from . import guard, output_guard, policy, providers
from .journal import Journal, hash_text

MAX_QUESTION_CHARS = 500
MAX_RESEARCH_CHARS = 1000
MAX_CORPUS = 60
MAX_SOURCES = 20


# Greetings are conversational intent, not a knowledge lookup. They are
# recognised deterministically (no provider cost) but the WELCOME copy is
# composed from the approved public corpus and current school context in
# greet(), never written as a fixed string in code.
def _greeting(provider, journal, question: str, corpus: list[dict]) -> dict:
    from .skills import web_search

    search = web_search.WebSearchSkill(journal)
    facts = search.school_identity()
    context = {
        "question": question,
        "school": facts.get("school_name", ""),
        "term": facts.get("current_term", ""),
        "motto": facts.get("motto", ""),
        "corpus_titles": [source["title"] for source in corpus[:8]],
    }
    body = _grounded_reply(
        provider,
        journal,
        "public.greeting",
        "Reply to the visitor's greeting in one short sentence. Use only the "
        "school facts given. Offer to answer questions about the school.",
        context,
    )
    return {
        "status": "answered",
        "title": facts.get("school_name") or "Kingsway Preparatory School",
        "body": body,
        "next_steps": [],
        "suggested_questions": _grounded_suggestions(provider, journal, corpus),
        "sources": [source["id"] for source in corpus[:3]],
        "human_referral": None,
    }


def _bounded_corpus(corpus: Any) -> list[dict]:
    safe: list[dict] = []
    if not isinstance(corpus, list):
        return safe
    for source in corpus[:MAX_CORPUS]:
        if not isinstance(source, dict):
            continue
        source_id = str(source.get("id") or "").strip()
        content = str(source.get("content") or "").strip()
        if not source_id or not content:
            continue
        safe.append(
            {
                "id": source_id[:100],
                "title": str(source.get("title") or "")[:200],
                "content": content[:4000],
            }
        )
    return safe


_WORD = __import__("re").compile(r"[a-z0-9]{3,}")


def _rank_by_relevance(question: str, items: list[dict]) -> list[dict]:
    """Most relevant first, by shared words with the question (stable).

    The prompt policy trims a grounding list from the end to fit its byte
    budget, so the order decides which sources the model actually sees.
    """
    wanted = set(_WORD.findall(question.lower()))
    if not wanted:
        return items

    def score(item: dict) -> int:
        title = set(_WORD.findall(str(item.get("title") or "").lower()))
        body = set(_WORD.findall(str(item.get("content") or "").lower()))
        return 3 * len(wanted & title) + len(wanted & body)

    return sorted(items, key=score, reverse=True)


def _bounded_conversation(conversation: Any) -> list[dict]:
    safe: list[dict] = []
    if not isinstance(conversation, list):
        return safe
    for turn in conversation[-6:]:
        if not isinstance(turn, dict):
            continue
        role = str(turn.get("role") or "")
        content = str(turn.get("content") or "").strip()
        if role not in ("user", "assistant") or not content:
            continue
        safe.append({"role": role, "content": content[:300]})
    return safe


def _next_steps(value: Any, limit: int = 5, chars: int = 300) -> list[str]:
    if not isinstance(value, list):
        return []
    bounded = []
    for item in value[:limit]:
        text = str(item).strip()[:chars]
        if text:
            bounded.append(text)
    return bounded


def _grounded_reply(
    provider: providers.Provider,
    journal: Journal,
    workflow: str,
    instruction: str,
    facts: dict,
) -> str:
    """Ask the model for one grounded sentence. Never returns a canned string."""
    from .agents import PROMPT_TEMPLATES

    system = PROMPT_TEMPLATES.get(workflow, instruction)
    raw = provider.complete(
        [
            {"role": "system", "content": system},
            {"role": "user", "content": instruction},
            {
                "role": "user",
                "content": json.dumps(facts, ensure_ascii=False),
            },
        ],
        {"response_format": "json_object", "temperature": 0.2, "max_tokens": 300},
    )
    scrubbed = output_guard.scrub(raw)
    for key in ("body", "reply", "message", "text", "greeting"):
        value = scrubbed.get(key) if isinstance(scrubbed, dict) else None
        if isinstance(value, str) and value.strip():
            return value.strip()[:600]
    if isinstance(scrubbed, str) and scrubbed.strip():
        return scrubbed.strip()[:600]
    journal.write(
        "ai_generation", {"type": "grounded_reply_unavailable", "workflow": workflow}
    )
    return ""


def _grounded_suggestions(
    provider: providers.Provider, journal: Journal, corpus: list[dict]
) -> list[str]:
    """Follow-up questions derived from the approved corpus, not fixed chips."""
    if not corpus:
        return []
    from .agents import PROMPT_TEMPLATES

    titles = [source["title"] for source in corpus[:10]]
    raw = provider.complete(
        [
            {
                "role": "system",
                "content": PROMPT_TEMPLATES.get("public.followup_questions", ""),
            },
            {
                "role": "user",
                "content": "Propose three follow-up questions a visitor may ask.",
            },
            {
                "role": "user",
                "content": json.dumps({"topics": titles}, ensure_ascii=False),
            },
        ],
        {"response_format": "json_object", "temperature": 0.3, "max_tokens": 200},
    )
    scrubbed = output_guard.scrub(raw)
    questions = (scrubbed.get("questions") if isinstance(scrubbed, dict) else None) or (
        scrubbed.get("suggested_questions") if isinstance(scrubbed, dict) else None
    )
    return _next_steps(questions, limit=3, chars=140)


def _blocked_answer() -> dict:
    """The blunt refusal every assistant returns for a malicious prompt.

    ``blocked`` tells PHP to block the session; the matched text is never
    echoed back.
    """
    return {
        "status": "blocked",
        "blocked": True,
        "title": "Malicious prompt blocked",
        "body": guard.MALICIOUS_PROMPT_MESSAGE,
        "next_steps": [],
        "suggested_questions": [],
        "sources": [],
        "human_referral": None,
    }


class PublicAssistantSkill:
    """Public FAQ assistant — public corpus only, never authenticated data."""

    WORKFLOW = "public.faq_assistant"

    def __init__(self, provider: providers.Provider, journal: Journal) -> None:
        self.provider = provider
        self.journal = journal

    def ask(self, question: str, corpus: Any, conversation: Any = None) -> dict:
        try:
            return self._answer(question, corpus, conversation)
        except providers.ProviderError as error:
            # A visitor question must not fail with a server error when the
            # model is rate-limited or down: refer them to the school instead.
            self.journal.write(
                "ai_generation",
                {
                    "type": "public_assistant_provider_failed",
                    "reason": str(error)[:200],
                },
            )
            return {
                "status": "escalate",
                "reason": "assistant_unavailable",
                "message": "Please contact the school assistant for help.",
            }

    def _answer(self, question: str, corpus: Any, conversation: Any) -> dict:
        question = str(question or "").strip()
        if not question or len(question) > MAX_QUESTION_CHARS:
            raise ValueError("A valid question of up to 500 characters is required.")

        verdict = guard.screen(question, "public", conversation)
        if verdict["malicious"]:
            self.journal.write(
                "ai_generation",
                {
                    "type": "prompt_injection_blocked",
                    "audience": "public",
                    "category": verdict["category"],
                    "question_hash": hash_text(question),
                },
            )
            return _blocked_answer()

        # Bounded once, before either branch: the greeting branch read it
        # before assignment and raised UnboundLocalError on every "hello".
        safe_corpus = _bounded_corpus(corpus)

        if guard.is_smalltalk(question):
            self.journal.write(
                "ai_generation",
                {
                    "type": "public_assistant_greeting",
                    "question_hash": hash_text(question),
                },
            )
            if not safe_corpus:
                return {
                    "status": "escalate",
                    "reason": "no_approved_public_source",
                    "message": "Please contact the school assistant for help.",
                }
            from .skills import web_search

            facts = web_search.WebSearchSkill(self.journal).school_identity()
            body = _grounded_reply(
                self.provider,
                self.journal,
                "public.greeting",
                "Reply to the visitor's greeting in one short sentence using only "
                "the school facts provided. Offer to answer questions about the school.",
                {
                    "question": question,
                    "school": facts.get("school_name", ""),
                    "motto": facts.get("motto", ""),
                    "topics": [source["title"] for source in safe_corpus[:8]],
                },
            )
            followups = _grounded_suggestions(self.provider, self.journal, safe_corpus)
            return {
                "status": "answered",
                "title": facts.get("school_name") or "Kingsway Preparatory School",
                "body": body,
                "next_steps": [],
                "suggested_questions": followups,
                "sources": [source["id"] for source in safe_corpus[:3]],
                "human_referral": None,
            }

        if not safe_corpus:
            return {
                "status": "escalate",
                "reason": "no_approved_public_source",
                "message": "Please contact the school assistant for help.",
            }

        envelope = policy.minimize(
            self.WORKFLOW,
            {
                "question": question,
                "audience": "public_visitor",
                "corpus": json.dumps(
                    _rank_by_relevance(question, safe_corpus), ensure_ascii=False
                ),
                "conversation": json.dumps(
                    _bounded_conversation(conversation), ensure_ascii=False
                ),
            },
        )
        result = self._complete(
            envelope,
            {
                "response_format": "json_object",
                "temperature": 0.1,
                "max_tokens": 1000,
            },
        )

        if not isinstance(result.get("title"), str) or not isinstance(
            result.get("body"), str
        ):
            raise ValueError("The public assistant returned an invalid answer.")

        allowed = {source["id"] for source in safe_corpus}
        sources = []
        for source in result.get("sources") or []:
            source = str(source).strip()
            if source and source in allowed:
                sources.append(source)
        if not sources:
            return {
                "status": "escalate",
                "reason": "answer_without_verified_source",
                "message": "Please contact the school assistant so the question can be verified.",
            }

        escalate = bool(result.get("escalation_required"))
        self.journal.write(
            "ai_generation",
            {
                "type": "public_assistant_answered",
                "question_hash": hash_text(question),
                "source_count": len(sources),
                "escalated": escalate,
            },
        )
        return {
            "status": "escalate" if escalate else "answered",
            "title": result["title"].strip()[:200],
            "body": result["body"].strip()[:5000],
            "next_steps": _next_steps(result.get("next_steps")),
            "suggested_questions": _next_steps(
                result.get("suggested_questions"), limit=3, chars=140
            ),
            "sources": sources,
            "human_referral": (
                "Please contact the school assistant for confirmation or further help."
                if escalate
                else None
            ),
        }

    def _complete(self, envelope: dict, options: dict) -> Any:
        raw = self.provider.complete(
            [
                {"role": "system", "content": self._template()},
                {"role": "user", "content": json.dumps(envelope, ensure_ascii=False)},
            ],
            options,
        )
        return output_guard.scrub(raw)

    def _template(self) -> str:
        from .agents import PROMPT_TEMPLATES

        return PROMPT_TEMPLATES[self.WORKFLOW]


class ParentAssistantSkill:
    """Parent portal assistant — linked children only, staff review may apply."""

    WORKFLOW = "communications.parent_portal_assistant"

    def __init__(self, provider: providers.Provider, journal: Journal) -> None:
        self.provider = provider
        self.journal = journal

    def ask(self, question: str, context: dict) -> dict:
        question = str(question or "").strip()
        if not question or len(question) > MAX_QUESTION_CHARS:
            raise ValueError("A valid question of up to 500 characters is required.")
        verdict = guard.screen(question, "parent", None)
        if verdict["malicious"]:
            self.journal.write(
                "ai_generation",
                {
                    "type": "prompt_injection_blocked",
                    "audience": "parent",
                    "category": verdict["category"],
                    "question_hash": hash_text(question),
                },
            )
            return _blocked_answer()
        children = (context or {}).get("linked_children")
        if not isinstance(children, list) or not children:
            return {
                "status": "cannot_answer",
                "reason": "no_linked_children",
                "message": "No linked learner is available in this family account.",
            }

        envelope = policy.minimize(
            self.WORKFLOW,
            {
                "question": question,
                "audience": "authenticated_parent",
                "context": json.dumps(
                    {"linked_children": children[:10]}, ensure_ascii=False
                ),
            },
        )
        from .agents import PROMPT_TEMPLATES

        result = output_guard.scrub(
            self.provider.complete(
                [
                    {"role": "system", "content": PROMPT_TEMPLATES[self.WORKFLOW]},
                    {
                        "role": "user",
                        "content": json.dumps(envelope, ensure_ascii=False),
                    },
                ],
                {
                    "response_format": "json_object",
                    "temperature": 0.2,
                    "max_tokens": 1200,
                },
            )
        )
        if not isinstance(result.get("title"), str) or not isinstance(
            result.get("body"), str
        ):
            raise ValueError("The assistant returned an invalid answer.")

        self.journal.write(
            "ai_generation",
            {
                "type": "parent_assistant_answered",
                "question_hash": hash_text(question),
            },
        )
        return {
            "status": "answered",
            "title": result["title"].strip()[:200],
            "body": result["body"].strip()[:5000],
            "next_steps": _next_steps(result.get("next_steps")),
        }


class ResearchAssistantSkill:
    """Approved-source research assistant; it never fetches caller URLs."""

    WORKFLOW = "research.external_knowledge"

    def __init__(self, provider: providers.Provider, journal: Journal) -> None:
        self.provider = provider
        self.journal = journal

    def ask(self, question: str, sources: Any) -> dict:
        question = str(question or "").strip()
        if not question or len(question) > MAX_RESEARCH_CHARS:
            raise ValueError(
                "A valid research question of up to 1000 characters is required."
            )
        verdict = guard.screen(question, "staff", None)
        if verdict["malicious"]:
            self.journal.write(
                "ai_generation",
                {
                    "type": "prompt_injection_blocked",
                    "audience": "staff",
                    "category": verdict["category"],
                    "question_hash": hash_text(question),
                },
            )
            return _blocked_answer()


        safe = []
        if isinstance(sources, list):
            for source in sources[:MAX_SOURCES]:
                if not isinstance(source, dict):
                    continue
                source_id = str(source.get("id") or "").strip()
                content = str(source.get("content") or "").strip()
                url = str(source.get("url") or "").strip()
                if (
                    not source_id
                    or not content
                    or not url.lower().startswith("https://")
                ):
                    continue
                safe.append(
                    {
                        "id": source_id[:100],
                        "title": str(source.get("title") or "")[:200],
                        "date": str(source.get("date") or "")[:30],
                        "url": url[:500],
                        "content": content[:4500],
                    }
                )
        if not safe:
            return {
                "status": "escalate",
                "reason": "no_approved_source",
                "message": "No approved research source is available.",
            }

        envelope = policy.minimize(
            self.WORKFLOW,
            {
                "question": question,
                "audience": "authorized_staff",
                "sources": json.dumps(
                    _rank_by_relevance(question, safe), ensure_ascii=False
                ),
            },
        )
        from .agents import PROMPT_TEMPLATES

        result = output_guard.scrub(
            self.provider.complete(
                [
                    {"role": "system", "content": PROMPT_TEMPLATES[self.WORKFLOW]},
                    {
                        "role": "user",
                        "content": json.dumps(envelope, ensure_ascii=False),
                    },
                ],
                {
                    "response_format": "json_object",
                    "temperature": 0.2,
                    "max_tokens": 1600,
                },
            )
        )
        if not isinstance(result.get("title"), str) or not isinstance(
            result.get("body"), str
        ):
            raise ValueError("The research assistant returned an invalid answer.")

        allowed = {source["id"] for source in safe}
        citations = [
            str(source)
            for source in (result.get("sources") or [])
            if str(source) in allowed
        ]
        escalate = bool(result.get("escalation_required")) or not citations
        self.journal.write(
            "ai_generation",
            {
                "type": "research_assistant_answered",
                "question_hash": hash_text(question),
                "source_count": len(citations),
                "escalated": escalate,
            },
        )
        return {
            "status": "escalate" if escalate else "answered",
            "title": result["title"].strip()[:200],
            "body": result["body"].strip()[:6000],
            "facts": [str(item) for item in (result.get("facts") or [])][:10],
            "inferences": [str(item) for item in (result.get("inferences") or [])][:10],
            "next_steps": [str(item) for item in (result.get("next_steps") or [])][:5],
            "sources": citations,
            "human_referral": (
                "Verify this with the relevant school or official source before acting."
                if escalate
                else None
            ),
        }
