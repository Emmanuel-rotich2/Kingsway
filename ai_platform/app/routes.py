"""Flask application factory wiring every AI endpoint of the school system.

Endpoints (all service-to-service, bearer-authenticated, never browser-facing):
  GET  /healthz                    liveness + provider chain summary
  POST /api/agents/assist          full governed agent run (primary AI engine)
  POST /api/agents/digest          deterministic personal digest
  POST /v1/chat/completions        OpenAI-compatible raw provider access
  GET  /v1/models                  model advertisement for health checks
  POST /internal/worker            curl-cron batch processing (digest/assist)
  POST /internal/behavior/forget   DPA erasure for one staff member
"""

from __future__ import annotations

import json
import uuid
from typing import Any

from flask import Flask, jsonify, request

from .agents import default_agent
from .behavior import BehaviorStore
from .config import Config
from .journal import Journal
from .orchestrator import Orchestrator
from .providers import Provider, ProviderError
from .security import bearer_authorized, ensure_staff_context, bound_question
from .tools import ToolBridge


def create_app(config: Config | None = None) -> Flask:
    app = Flask(__name__)
    cfg = config or Config()
    journal = Journal(cfg.log_dir)
    behavior = BehaviorStore(cfg.data_dir, journal)
    provider = Provider(cfg, journal)
    tools = ToolBridge(cfg, journal)
    orchestrator = Orchestrator(provider, tools, behavior, journal)

    def auth_guard() -> Any | None:
        if not bearer_authorized(request.headers, cfg.secret):
            return jsonify({"success": False, "message": "unauthorized"}), 401
        return None

    @app.get("/healthz")
    def healthz():
        return jsonify(
            {
                "status": "ok",
                "engine": "python",
                "providers": provider.health()["providers"],
            }
        )

    @app.post("/api/agents/assist")
    def agent_assist():
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        try:
            context = ensure_staff_context(payload.get("context") or {})
            question = bound_question(payload.get("question"))
        except (ValueError, PermissionError) as error:
            message, code = (
                str(error),
                (403 if isinstance(error, PermissionError) else 422),
            )
            return jsonify({"success": False, "message": message}), code
        try:
            result = orchestrator.assist(context, question)
        except Exception as error:  # noqa: BLE001 - bounded relay surface
            journal.write(
                "ai_generation",
                {
                    "type": "agent_assist_failed",
                    "operator_id": context.get("user_id"),
                    "error_class": type(error).__name__,
                },
            )
            return jsonify(
                {
                    "success": False,
                    "message": "the assistant could not answer right now",
                }
            ), 503
        return jsonify(
            {"success": True, "data": result, "message": "Agent answer prepared"}
        )

    @app.post("/api/agents/digest")
    def agent_digest():
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        try:
            context = ensure_staff_context(payload.get("context") or {})
        except (ValueError, PermissionError) as error:
            message, code = (
                str(error),
                (403 if isinstance(error, PermissionError) else 422),
            )
            return jsonify({"success": False, "message": message}), code
        cadence = str(payload.get("cadence") or "daily")
        try:
            result = orchestrator.digest(
                context["user_id"],
                context["permissions"],
                cadence,
                context.get("request_id", ""),
                bool(payload.get("broadcast")),
            )
        except Exception:  # noqa: BLE001
            return jsonify(
                {"success": False, "message": "the digest could not be prepared"}
            ), 503
        return jsonify(
            {"success": True, "data": result, "message": "Agent digest ready"}
        )

    @app.post("/v1/chat/completions")
    def chat_completions():
        """OpenAI-compatible surface so ANY existing consumer (including the
        PHP AiProviderClient) can use the Python provider chain unchanged."""
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        messages = payload.get("messages")
        if not isinstance(messages, list) or not messages:
            return jsonify({"success": False, "message": "messages are required"}), 422
        if not cfg.enabled:
            return jsonify(
                {
                    "success": False,
                    "message": "AI assistance is disabled by configuration.",
                }
            ), 503
        try:
            parsed = provider.complete(
                messages, {"response_format": payload.get("response_format") or ""}
            )
        except ProviderError as error:
            return jsonify({"success": False, "message": str(error)}), 502
        return jsonify(
            {
                "id": "chatcmpl-" + uuid.uuid4().hex[:12],
                "object": "chat.completion",
                "model": str(payload.get("model") or cfg.model or "kingsway-python"),
                "choices": [
                    {
                        "index": 0,
                        "message": {
                            "role": "assistant",
                            "content": json.dumps(parsed, ensure_ascii=False),
                        },
                        "finish_reason": "stop",
                    }
                ],
            }
        )

    @app.get("/v1/models")
    def models():
        guard = auth_guard()
        if guard is not None:
            return guard
        chain = provider.health()["providers"]
        return jsonify(
            {
                "object": "list",
                "data": [{"id": entry["model"]} for entry in chain]
                or [{"id": default_agent()["id"]}],
            }
        )

    @app.post("/internal/worker")
    def internal_worker():
        """Curl-cron entry: process a batch of {mode, context, ...} runs.
        Kept intentionally stateless - the PHP queue remains the durable
        record; this endpoint executes and returns results."""
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        runs = payload.get("runs")
        if not isinstance(runs, list) or not runs:
            return jsonify({"success": False, "message": "runs[] is required"}), 422
        results = []
        for run in runs[:25]:
            try:
                context = ensure_staff_context(run.get("context") or {})
                if run.get("mode") == "digest":
                    outcome = orchestrator.digest(
                        context["user_id"],
                        context["permissions"],
                        str(run.get("cadence") or "daily"),
                        context.get("request_id", ""),
                        bool(run.get("broadcast")),
                    )
                else:
                    outcome = orchestrator.assist(
                        context, bound_question(run.get("question"))
                    )
                results.append(
                    {"user_id": context["user_id"], "status": outcome.get("status")}
                )
            except (ValueError, PermissionError) as error:
                results.append(
                    {"user_id": None, "status": "rejected", "message": str(error)[:200]}
                )
            except Exception:  # noqa: BLE001
                results.append({"user_id": None, "status": "failed"})
        return jsonify(
            {
                "success": True,
                "data": {"processed": len(results), "results": results},
                "message": "worker batch complete",
            }
        )

    @app.post("/internal/behavior/forget")
    def behavior_forget():
        guard = auth_guard()
        if guard is not None:
            return guard
        payload = request.get_json(force=True, silent=True) or {}
        user_id = int(payload.get("user_id") or 0)
        if user_id < 1:
            return jsonify({"success": False, "message": "user_id is required"}), 422
        behavior.forget(user_id)
        return jsonify({"success": True, "message": "behaviour profile erased"})

    return app
