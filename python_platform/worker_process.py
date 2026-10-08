"""Run the durable Python queue consumer under a process supervisor.

The Flask/Passenger process serves the private service API. This standalone
process is the production queue consumer; it polls PHP's durable queue and
handles one bounded job at a time. Supply all service settings through the
supervisor's protected environment, never through command-line arguments.

Run locally with:
    .venv/bin/python worker_process.py
"""

from __future__ import annotations

import logging
import os
import signal
from threading import Event

from app.config import Config
from app.queue_worker import PythonQueueWorker


def _bounded_seconds(name: str, default: float, lower: float, upper: float) -> float:
    try:
        value = float(os.environ.get(name, default))
    except (TypeError, ValueError):
        value = default
    return max(lower, min(upper, value))


def main() -> None:
    logging.basicConfig(
        level=os.environ.get("KINGSWAY_PYTHON_WORKER_LOG_LEVEL", "INFO").upper(),
        format="%(asctime)s %(levelname)s %(name)s %(message)s",
    )
    stop = Event()
    signal.signal(signal.SIGTERM, lambda _signum, _frame: stop.set())
    signal.signal(signal.SIGINT, lambda _signum, _frame: stop.set())
    worker = PythonQueueWorker(Config())
    mode = os.environ.get("KINGSWAY_PYTHON_WORKER_MODE", "bounded").strip().lower()
    if mode == "bounded":
        # CloudLinux LVE survival default: exit after <=25 jobs or <=45 s so the
        # OS fully reclaims CPU/RAM between cycles. The durable PHP queue
        # retains unclaimed work; the next invocation continues.
        try:
            max_jobs = int(os.environ.get("KINGSWAY_PYTHON_WORKER_MAX_JOBS", "25"))
            max_seconds = float(
                os.environ.get("KINGSWAY_PYTHON_WORKER_MAX_SECONDS", "45")
            )
        except ValueError:
            max_jobs, max_seconds = 25, 45.0
        outcome = worker.run_bounded(
            stop,
            max_jobs=max_jobs,
            max_seconds=max_seconds,
            idle_seconds=_bounded_seconds(
                "KINGSWAY_PYTHON_WORKER_IDLE_SECONDS", 1, 0.1, 30
            ),
            error_seconds=_bounded_seconds(
                "KINGSWAY_PYTHON_WORKER_ERROR_SECONDS", 5, 1, 120
            ),
        )
        logging.getLogger("kingsway.python_worker").info(
            "bounded run finished: %s", outcome
        )
        return
    worker.run_forever(
        stop,
        idle_seconds=_bounded_seconds(
            "KINGSWAY_PYTHON_WORKER_IDLE_SECONDS", 1, 0.1, 30
        ),
        error_seconds=_bounded_seconds(
            "KINGSWAY_PYTHON_WORKER_ERROR_SECONDS", 5, 1, 120
        ),
    )


if __name__ == "__main__":
    main()
