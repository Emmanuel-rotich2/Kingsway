"""Kingsway AI Platform - the Python agent engine.

Deployed as a Passenger WSGI app (DirectAdmin Python Selector):
    Application root:    python_apps/kingsway_ai
    Startup file:        passenger_wsgi.py
    Entry point:         application
    Environment:         KINGSWAY_AI_SECRET, KINGSWAY_PHP_BASE_URL,
                         KINGSWAY_PHP_WORKER_SECRET, AI_* provider vars

The core engine modules (agents, orchestrator, providers, tools, policy,
behavior) import Flask-free so they can be tested and reused anywhere;
only create_app() pulls the (single) Flask dependency.
"""

from typing import Any

__all__ = ["create_app"]


def __getattr__(name: str) -> Any:
    if name == "create_app":
        from .routes import create_app

        return create_app
    raise AttributeError(f"module {__name__!r} has no attribute {name!r}")
