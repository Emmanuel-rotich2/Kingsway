"""LLM provider layer: model routing, token discipline, retries, JSON repair.

This is where ALL provider traffic of the school system now lives (Python),
replacing the per-request PHP curl work. Provider shapes mirror the PHP
AiProviderClient contract: openai-compatible chat completions, NVIDIA NIM,
Anthropic messages, Google generateContent. Fallbacks come from the same
AI_PROVIDER_FALLBACKS JSON the PHP gateway understands.
"""

from __future__ import annotations

import json
import random
import re
import time
import urllib.error
import urllib.request
from typing import Any, Callable

from .config import Config


class ProviderError(RuntimeError):
    pass


class Provider:
    def __init__(
        self,
        config: Config,
        journal=None,
        transport: Callable[[str, str, dict, str, int], tuple[Any, int, str]]
        | None = None,
    ) -> None:
        self.config = config
        self.journal = journal
        self._transport = transport
        self._chain = self._build_chain()
        # Pre-built so request handling never pays chain construction cost.
        self._fast_chain = self._build_chain(fast=True) if getattr(config, "fast_model", "") else []

    # Task classes that only need cheap, fast classification work (agent
    # routing, short extraction). They may use AI_FAST_MODEL when configured
    # and transparently fall back to the primary chain on any failure.
    FAST_TASKS = ("triage", "classify", "extract", "route")

    # Latency budgets per task class. Latency is roughly linear in generated
    # output tokens, so an uncapped ceiling is the single biggest source of
    # multi-second stalls: a rambling 1200-token JSON intent costs ~32s while
    # the same call bounded to 200 tokens costs ~2.4s. Every internal call
    # therefore declares a real ceiling, and the timeout is never long enough
    # to hold a user waiting on it.
    TASK_BUDGETS: dict[str, tuple[int, int]] = {
        # task:            (max_tokens, timeout_seconds)
        "triage": (200, 8),
        "classify": (200, 8),
        "extract": (200, 8),
        "route": (160, 6),
        "intent": (200, 8),
        "tool_plan": (220, 8),
        "specialist": (240, 10),
        "briefing": (520, 14),
        "chat": (500, 14),
        "answer": (500, 14),
        "draft": (1200, 32),
    }
    DEFAULT_MAX_TOKENS = 600
    DEFAULT_TIMEOUT = 20

    @classmethod
    def budget_for(cls, task: str) -> tuple[int, int]:
        return cls.TASK_BUDGETS.get(task, (cls.DEFAULT_MAX_TOKENS, cls.DEFAULT_TIMEOUT))

    def _build_chain(self, fast: bool = False) -> list[dict[str, str]]:
        chain: list[dict[str, str]] = []
        primary = {
            "name": self.config.provider_name,
            "base_url": self.config.provider_base_url,
            "model": self.config.model,
            "api_key": self.config.api_key,
            "provider_kind": self.config.provider_kind,
        }
        if fast and getattr(self.config, "fast_model", ""):
            primary = {
                "name": f"{self.config.provider_name}-fast",
                "base_url": self.config.fast_model_base_url or self.config.provider_base_url,
                "model": self.config.fast_model,
                "api_key": self.config.fast_model_api_key or self.config.api_key,
                "provider_kind": self.config.provider_kind,
            }
        if primary["base_url"] and primary["model"]:
            chain.append(primary)
        try:
            fallbacks = json.loads(self.config.fallbacks_raw or "[]")
        except json.JSONDecodeError:
            fallbacks = []
        if isinstance(fallbacks, list):
            for fallback in fallbacks[:4]:
                if not isinstance(fallback, dict):
                    continue
                base_url = str(fallback.get("base_url") or "").strip()
                model = str(fallback.get("model") or "").strip()
                if not base_url or not model:
                    continue
                chain.append(
                    {
                        "name": str(fallback.get("name") or f"fallback_{len(chain)}"),
                        "base_url": base_url.rstrip("/"),
                        "model": model,
                        "api_key": str(fallback.get("api_key") or ""),
                        "provider_kind": str(
                            fallback.get("provider_kind") or "generic"
                        ),
                    }
                )
        return chain

    # Model ids that emit reasoning tokens by default. Measured on the live
    # NVIDIA account: nemotron-3 answers take 2.5x longer with thinking on and
    # can exhaust small budgets before any answer text is produced.
    REASONING_MODEL_MARKERS = ("nemotron-3",)

    @classmethod
    def _is_reasoning_model(cls, model: str) -> bool:
        lowered = (model or "").lower()
        return any(marker in lowered for marker in cls.REASONING_MODEL_MARKERS)

    @staticmethod
    def _kind(entry: dict[str, str]) -> str:
        kind = (entry.get("provider_kind") or "generic").strip().lower()
        if kind != "generic":
            return kind
        host = (entry.get("base_url") or "").lower()
        if "anthropic.com" in host:
            return "anthropic"
        if "googleapis.com" in host:
            return "google"
        host_match = re.search(r"(?:^|\.)nvidia\.com", host)
        if host_match:
            return "nvidia"
        return "openai"

    @staticmethod
    def _join_endpoint(base_url: str, path: str) -> str:
        if base_url.endswith("/v1") and path.startswith("/v1/"):
            return base_url + path[3:]
        return base_url + path

    def _build_request(
        self, entry: dict[str, str], messages: list[dict], options: dict
    ) -> tuple[str, str, list[str], str]:
        kind = self._kind(entry)
        model = entry["model"]
        api_key = entry.get("api_key", "")
        task = str(options.get("task") or "default")
        task_max, task_timeout = self.budget_for(task)
        max_tokens = min(
            4096, max(64, int(options.get("max_tokens", task_max or self.DEFAULT_MAX_TOKENS)))
        )
        temperature = float(options.get("temperature", 0.2))
        headers = ["Content-Type: application/json", "Accept: application/json"]
        if kind == "anthropic":
            if api_key:
                headers.append(f"x-api-key: {api_key}")
            headers.append("anthropic-version: 2023-06-01")
            system = "\n\n".join(
                m["content"] for m in messages if m["role"] == "system"
            )
            chat = [
                {"role": m["role"], "content": m["content"]}
                for m in messages
                if m["role"] != "system"
            ]
            payload: dict[str, Any] = {
                "model": model,
                "max_tokens": max_tokens,
                "messages": chat,
                "temperature": temperature,
            }
            if options.get("stream"):
                payload["stream"] = True
            if system:
                payload["system"] = system
            return (
                "POST",
                self._join_endpoint(entry["base_url"], "/v1/messages"),
                headers,
                json.dumps(payload, ensure_ascii=False),
            )
        if kind == "google":
            if api_key:
                headers.append(f"x-goog-api-key: {api_key}")
            contents = []
            system_parts = []
            for message in messages:
                if message["role"] == "system":
                    system_parts.append({"text": message["content"]})
                else:
                    contents.append(
                        {
                            "role": "model"
                            if message["role"] == "assistant"
                            else "user",
                            "parts": [{"text": message["content"]}],
                        }
                    )
            payload = {
                "contents": contents,
                "generationConfig": {
                    "temperature": temperature,
                    "maxOutputTokens": max_tokens,
                },
            }
            if system_parts:
                payload["systemInstruction"] = {"parts": system_parts}
            return (
                "POST",
                self._join_endpoint(
                    entry["base_url"], f"/v1beta/models/{model}:generateContent"
                ),
                headers,
                json.dumps(payload, ensure_ascii=False),
            )
        payload = {
            "model": model,
            "messages": messages,
            "temperature": temperature,
            "max_tokens": max_tokens,
        }
        if options.get("response_format") == "json_object":
            payload["response_format"] = {"type": "json_object"}
        if options.get("stream"):
            payload["stream"] = True
        # Reasoning-family models emit hidden "thinking" tokens that count
        # against max_tokens and multiply latency: a 500-token chat budget was
        # observed consumed by reasoning with the answer arriving only after
        # 6-12s. The NIM chat-template switch turns thinking off for these
        # models; non-reasoning models never receive the parameter.
        if self._is_reasoning_model(model) and not options.get("allow_thinking"):
            payload["chat_template_kwargs"] = {"thinking": False}
        if api_key:
            headers.append(f"Authorization: Bearer {api_key}")
        return (
            "POST",
            self._join_endpoint(entry["base_url"], "/v1/chat/completions"),
            headers,
            json.dumps(payload, ensure_ascii=False),
        )

    def _request(
        self, url: str, method: str, headers: list[str], body: str, timeout: int
    ) -> tuple[Any, int, str]:
        if self._transport is not None:
            raw, status, error = self._transport(url, method, headers, body, timeout)
            return raw, int(status), str(error)
        request = urllib.request.Request(
            url, data=body.encode("utf-8") if body else None, method=method
        )
        for header in headers:
            key, _, value = header.partition(":")
            request.add_header(key.strip(), value.strip())
        try:
            with urllib.request.urlopen(request, timeout=timeout) as response:
                return response.read().decode("utf-8", "replace"), response.status, ""
        except urllib.error.HTTPError as error:
            try:
                raw = error.read().decode("utf-8", "replace")
            except OSError:
                raw = ""
            return raw, error.code, ""
        except (urllib.error.URLError, TimeoutError, OSError) as error:
            return False, 0, f"provider request failed: {error}"

    @staticmethod
    def _response_content(kind: str, decoded: dict) -> str | None:
        if kind == "anthropic":
            parts = [
                p.get("text")
                for p in (decoded.get("content") or [])
                if isinstance(p, dict) and isinstance(p.get("text"), str)
            ]
            return "".join(parts) or None
        if kind == "google":
            parts = ((decoded.get("candidates") or [{}])[0].get("content") or {}).get(
                "parts"
            ) or []
            texts = [
                p.get("text")
                for p in parts
                if isinstance(p, dict) and isinstance(p.get("text"), str)
            ]
            return "".join(texts) or None
        choices = decoded.get("choices") or []
        if choices and isinstance(choices[0], dict):
            content = (choices[0].get("message") or {}).get("content")
            if isinstance(content, str):
                return content
        return None

    @staticmethod
    def decode_json_content(content: str) -> Any:
        text = content.strip()
        try:
            return json.loads(text)
        except json.JSONDecodeError:
            pass
        text = re.sub(
            r"^```(?:json)?\s*|\s*```$", "", text, flags=re.IGNORECASE
        ).strip()
        try:
            return json.loads(text)
        except json.JSONDecodeError:
            pass
        starts = [i for i in (text.find("{"), text.find("[")) if i != -1]
        if not starts:
            return None
        try:
            return json.loads(text[min(starts) :])
        except json.JSONDecodeError:
            return None

    def complete(self, messages: list[dict], options: dict | None = None) -> dict:
        options = options or {}
        if not self.config.enabled:
            raise ProviderError("AI assistance is disabled by configuration.")
        if not messages:
            raise ProviderError("AI request requires at least one message.")
        if not self._chain:
            raise ProviderError("AI provider is not fully configured.")

        task = str(options.get("task") or "default")
        chain = self._chain
        if task in self.FAST_TASKS and self._fast_chain:
            chain = self._fast_chain + self._chain

        errors: list[str] = []
        for index, entry in enumerate(chain):
            try:
                return self._complete_through(entry, messages, options, index)
            except ProviderError as error:
                errors.append(f"{entry['name']}: {error}")
        raise ProviderError(
            "All configured AI providers were unavailable. " + " | ".join(errors[-2:])
        )

    # ------------------------------------------------------------------ stream
    def stream(self, messages: list[dict], options: dict | None = None):
        """Yield text deltas as the provider produces them.

        Streaming is the highest-return latency change available: measured
        time-to-first-token here is well under a second while the same answer
        takes many seconds to finish, so a user watching the text arrive is not
        waiting. Providers that cannot stream, and any failure, fall back to one
        buffered call, so the caller always receives at least one delta.
        """
        options = dict(options or {})
        task = str(options.get("task") or "default")
        chain = self._chain
        if task in self.FAST_TASKS and self._fast_chain:
            chain = self._fast_chain + self._chain

        errors: list[str] = []
        for index, entry in enumerate(chain):
            try:
                delivered = False
                for delta in self._stream_through(entry, messages, options, index):
                    delivered = True
                    yield delta
                if delivered:
                    return
            except ProviderError as error:
                errors.append(f"{entry['name']}: {error}")

        try:
            result = self.complete(messages, options)
        except ProviderError as error:
            raise ProviderError(
                "All configured AI providers were unavailable. " + " | ".join(errors[-2:])
            ) from error
        text = self._response_content(self._kind(chain[0]), result)
        if text:
            yield text

    def _stream_through(
        self, entry: dict[str, str], messages: list[dict], options: dict, index: int
    ):
        kind = self._kind(entry)
        method, url, headers, body = self._build_request(
            entry, messages, {**options, "stream": True}
        )
        headers = [*headers, "Accept: text/event-stream"]
        _, task_timeout = self.budget_for(str(options.get("task") or "default"))
        timeout = min(60, max(3, int(options.get("timeout", task_timeout))))

        request = urllib.request.Request(
            url, data=body.encode("utf-8") if body else None, method=method
        )
        for header in headers:
            key, _, value = header.partition(":")
            request.add_header(key.strip(), value.strip())

        try:
            response = urllib.request.urlopen(request, timeout=timeout)
        except (urllib.error.HTTPError, urllib.error.URLError, TimeoutError, OSError) as error:
            raise ProviderError(f"{entry['name']} stream unavailable: {error}") from error

        started = time.monotonic()
        delivered = False
        try:
            for raw_line in response:
                piece = self._stream_delta(kind, raw_line)
                if piece:
                    delivered = True
                    yield piece
        except (urllib.error.URLError, TimeoutError, OSError) as error:
            if not delivered:
                raise ProviderError(f"{entry['name']} stream failed: {error}") from error
        finally:
            try:
                response.close()
            except Exception:
                pass

        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "provider_stream",
                    "provider_index": index,
                    "model": entry["model"],
                    "delivered": delivered,
                    "duration_ms": int((time.monotonic() - started) * 1000),
                },
            )

    @staticmethod
    def _stream_delta(kind: str, raw_line: bytes) -> str:
        """Extract one text delta from a streamed provider frame."""
        try:
            line = raw_line.decode("utf-8", "replace").strip()
        except Exception:
            return ""
        if not line.startswith("data:"):
            return ""
        payload = line[5:].strip()
        if payload in ("", "[DONE]"):
            return ""
        try:
            frame = json.loads(payload)
        except json.JSONDecodeError:
            return ""
        if kind == "anthropic":
            if frame.get("type") != "content_block_delta":
                return ""
            return str((frame.get("delta") or {}).get("text") or "")
        choices = frame.get("choices")
        if not isinstance(choices, list) or not choices:
            return ""
        delta = choices[0].get("delta") or {}
        return str(delta.get("content") or choices[0].get("text") or "")

    def _complete_through(
        self, entry: dict[str, str], messages: list[dict], options: dict, index: int
    ) -> dict:
        method, url, headers, body = self._build_request(entry, messages, options)
        task_max, task_timeout = self.budget_for(
            str(options.get("task") or "default")
        )
        timeout = min(
            60, max(3, int(options.get("timeout", task_timeout or self.DEFAULT_TIMEOUT)))
        )
        retries = min(
            3, max(0, int(options.get("_retries", self.config.provider_retries)))
        )
        delay_ms = min(
            2000,
            max(0, int(options.get("_retry_delay_ms", self.config.retry_delay_ms))),
        )
        attempt = 0
        started = time.monotonic()
        while True:
            raw, status, error = self._request(url, method, headers, body, timeout)
            transient = bool(error) or status in (408, 425, 429) or status >= 500
            if not transient or attempt >= retries:
                break
            if delay_ms:
                time.sleep(
                    delay_ms * (2**attempt) / 1000.0 * (1 + random.random() * 0.1)
                )
            attempt += 1

        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "provider_call",
                    "provider_index": index,
                    "model": entry["model"],
                    "prompt_hash": __import__("hashlib")
                    .sha256(body.encode("utf-8", "replace"))
                    .hexdigest(),
                    "http_status": int(status or 0),
                    "duration_ms": int((time.monotonic() - started) * 1000),
                },
            )

        if raw is False or error:
            raise ProviderError("AI provider request failed.")
        if status < 200 or status >= 300:
            raise ProviderError(f"AI provider returned HTTP {int(status or 0)}.")
        try:
            decoded = json.loads(raw)
        except json.JSONDecodeError:
            raise ProviderError("AI provider returned an invalid response.")
        if not isinstance(decoded, dict):
            raise ProviderError("AI provider returned an invalid response.")
        content = self._response_content(self._kind(entry), decoded)
        if not content or not content.strip():
            raise ProviderError("AI provider returned no usable content.")
        parsed = self.decode_json_content(content)
        if isinstance(parsed, dict):
            return parsed
        if options.get("_json_repair"):
            raise ProviderError("AI provider returned non-JSON content.")
        # One bounded repair attempt, mirroring the PHP client contract.
        return self._complete_through(
            entry,
            [
                {
                    "role": "system",
                    "content": "Return only one valid JSON object or array. Do not include markdown, prose, or code fences.",
                },
                *messages,
            ],
            {**options, "_json_repair": True, "temperature": 0},
            index,
        )

    def health(self) -> dict[str, Any]:
        return {
            "enabled": self.config.enabled,
            "providers": [
                {
                    "name": entry["name"],
                    "model": entry["model"],
                    "kind": self._kind(entry),
                }
                for entry in self._chain
            ],
        }
