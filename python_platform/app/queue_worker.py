"""Python handlers for the PHP-owned durable work queue.

Only explicitly registered Python job types are claimed. PHP retains queue
ownership, retry/backoff/dead-letter policy and its worker-secret boundary.
"""

from __future__ import annotations

import base64
import json
import logging
import ssl
from threading import Event, Thread
import urllib.error
import urllib.request
from typing import Any

from .config import Config
from .automations import AutomationEngine
from .batch_contracts import BATCH_FAMILY_CONTRACTS, is_batch_family_enabled
from .read_models import ReadModelError, ReadModelRefresher, PolarsReadModelRefresher
from .realtime import RealtimePublisher

PYTHON_JOB_TYPES = {
    "reads.projection.refresh",
    "automation.run",
    # Registered for the shared queue contract. These remain non-claimable
    # until PHP exposes job-specific reauthorization, bounded input and
    # validated artifact/result handling for each family.
    *BATCH_FAMILY_CONTRACTS.keys(),
}


class QueueWorkerError(RuntimeError):
    pass


class PhpQueueBridge:
    def __init__(self, config: Config):
        self.config = config

    def _bases(self) -> list[tuple[str, str]]:
        bases = []
        if self.config.php_internal_base_url:
            bases.append(
                (
                    self.config.php_internal_base_url.rstrip("/"),
                    self.config.php_internal_host,
                )
            )
        bases.append((self.config.php_base_url.rstrip("/"), ""))
        return bases

    def post(self, endpoint: str, payload: dict[str, Any]) -> dict[str, Any]:
        if not self.config.php_worker_secret:
            raise QueueWorkerError("PHP worker credential is not configured")
        body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
        last_error: Exception | None = None
        for base, host in self._bases():
            request = urllib.request.Request(base + endpoint, data=body, method="POST")
            request.add_header("Content-Type", "application/json")
            request.add_header("Accept", "application/json")
            request.add_header(
                "X-Kingsway-Worker-Secret", self.config.php_worker_secret
            )
            if host:
                request.add_header("Host", host)
            context = (
                None if self.config.php_verify_tls else ssl._create_unverified_context()
            )
            try:
                with urllib.request.urlopen(
                    request, timeout=90, context=context
                ) as response:
                    raw = response.read(8 * 1024 * 1024 + 1)
                if len(raw) > 8 * 1024 * 1024:
                    raise QueueWorkerError(
                        "PHP queue response exceeds the configured limit"
                    )
                decoded = json.loads(raw.decode("utf-8"))
                if not isinstance(decoded, dict) or decoded.get("success") is not True:
                    raise QueueWorkerError("PHP queue endpoint rejected the operation")
                return decoded
            except urllib.error.HTTPError as error:
                # A real HTTP response is authoritative. Do not retry a 4xx/5xx
                # against a second hostname and risk duplicate state changes.
                raise QueueWorkerError(
                    f"PHP queue endpoint returned HTTP {error.code}"
                ) from error
            except (
                urllib.error.URLError,
                TimeoutError,
                OSError,
                json.JSONDecodeError,
            ) as error:
                last_error = error
        raise QueueWorkerError("PHP queue endpoint is unavailable") from last_error


class PythonQueueWorker:
    def __init__(self, config: Config, bridge: PhpQueueBridge | None = None):
        self.config = config
        self.bridge = bridge or PhpQueueBridge(config)
        # Job telemetry to connected browsers (SSE). Fire-and-forget: a
        # gateway outage never slows the loop or fails a job.
        self.realtime = RealtimePublisher(config)

    def _notify_progress(
        self, job_id: int, progress: int, status: str = "running"
    ) -> None:
        """Report intermediate progress for a long job.

        Progress is clamped to 0-100 and forced to an int: the engine validates
        this field and a float or an out-of-range value would be rejected as an
        invalid descriptor, turning a progress report into a dropped event.

        Reported only for jobs that are expected to run long. Emitting one frame
        per batch row for a fast job is pure noise on the SSE stream.
        """
        try:
            self.realtime.publish(
                "JOB_PROGRESS",
                "all",
                {
                    "job_id": job_id,
                    "progress": max(0, min(100, int(progress))),
                    "status": status,
                    "domain": "queue",
                },
            )
        except Exception:
            pass

    def _notify_complete(self, job_id: int) -> None:
        try:
            self.realtime.publish(
                "JOB_COMPLETE",
                "all",
                {"job_id": job_id, "status": "completed", "domain": "queue"},
            )
        except Exception:
            pass

    def _notify_failed(self, job_id: int) -> None:
        try:
            self.realtime.publish(
                "JOB_FAILED",
                "all",
                {"job_id": job_id, "status": "failed", "domain": "queue"},
            )
        except Exception:
            pass

    def run_batch(self, limit: int = 3) -> dict[str, Any]:
        limit = max(1, min(50, int(limit)))
        processed = 0
        succeeded = 0
        failed = 0
        types: list[str] = []
        for _ in range(limit):
            claim = self.bridge.post("/api/realtime/python-job-claim", {})
            job = (claim.get("data") or {}).get("job")
            if job is None:
                break
            processed += 1
            job_id = int(job.get("id") or 0)
            job_type = str(job.get("job_type") or "")
            claimed_attempts = int(job.get("attempts") or 0)
            types.append(job_type)
            heartbeat_stop = Event()
            heartbeat_thread: Thread | None = None
            try:
                if job_id < 1 or job_type not in PYTHON_JOB_TYPES:
                    raise QueueWorkerError("Unregistered Python job type")
                payload = job.get("payload") or {}
                self._notify_progress(job_id, 0, "running")

                def heartbeat() -> None:
                    while not heartbeat_stop.wait(120):
                        try:
                            self.bridge.post(
                                "/api/realtime/python-job-heartbeat",
                                {"job_id": job_id, "attempts": claimed_attempts},
                            )
                        except Exception:
                            # The next heartbeat or completion will expose
                            # a lost lease; never log tokens or job payloads.
                            return

                heartbeat_thread = Thread(
                    target=heartbeat, name=f"queue-lease-{job_id}", daemon=True
                )
                heartbeat_thread.start()
                if job_type == "reads.projection.refresh":
                    projection = str(payload.get("projection") or "")
                    # Use Polars for the three heaviest projections
                    if projection in {
                        "student_term_placement",
                        "fee_status_summary",
                        "person_directory",
                    }:
                        result = PolarsReadModelRefresher(self.config).refresh(
                            projection
                        )
                    else:
                        result = ReadModelRefresher(self.config).refresh(projection)
                    # The queue stores state and the journal stores only safe
                    # operational facts; projection row contents never leave DB.
                    self.bridge.post(
                        "/api/realtime/python-job-complete",
                        {
                            "job_id": job_id,
                            "attempts": claimed_attempts,
                            "result": {
                                "projection": projection,
                                "rows_count": int(result.get("rows_count") or 0),
                                "duration_ms": int(result.get("duration_ms") or 0),
                            },
                        },
                    )
                    self._notify_complete(job_id)
                    succeeded += 1
                elif job_type == "automation.run":
                    envelope = (
                        self.bridge.post(
                            "/api/realtime/python-job-input",
                            {"job_id": job_id, "attempts": claimed_attempts},
                        ).get("data")
                        or {}
                    )
                    automation = str(envelope.get("automation") or "")
                    operator = envelope.get("operator") or {}
                    result = AutomationEngine().execute(
                        automation,
                        envelope.get("payload") or {},
                        journal=None,
                        operator_id=int(operator.get("user_id") or 0),
                    )
                    content = str(result.pop("content", ""))
                    wire_result = dict(result)
                    wire_result["content_base64"] = base64.b64encode(
                        content.encode("utf-8")
                    ).decode("ascii")
                    self.bridge.post(
                        "/api/realtime/python-job-complete",
                        {
                            "job_id": job_id,
                            "attempts": claimed_attempts,
                            "result": wire_result,
                        },
                    )
                    self._notify_complete(job_id)
                    succeeded += 1
                elif job_type in BATCH_FAMILY_CONTRACTS:
                    # Fail closed if a deployment accidentally activates a
                    # family before its PHP authorization and artifact
                    # contracts are wired. PHP owns retries and dead letters.
                    if not is_batch_family_enabled(job_type):
                        raise QueueWorkerError(
                            "Python batch family is registered but not enabled"
                        )
                    raise QueueWorkerError("Python batch handler is not implemented")
                else:
                    raise QueueWorkerError(
                        "Python handler is not implemented for this job type"
                    )
            except Exception as error:  # queue policy remains centralized in PHP
                try:
                    self.bridge.post(
                        "/api/realtime/python-job-fail",
                        {
                            "job_id": job_id,
                            "attempts": claimed_attempts,
                            "reason": type(error).__name__,
                        },
                    )
                except Exception as finalize_error:
                    raise QueueWorkerError(
                        "Python job failed and PHP could not record the failure"
                    ) from finalize_error
                self._notify_failed(job_id)
                failed += 1
            finally:
                heartbeat_stop.set()
                if heartbeat_thread is not None:
                    heartbeat_thread.join(timeout=2)
        return {
            "processed": processed,
            "succeeded": succeeded,
            "failed": failed,
            "job_types": types,
        }

    def run_bounded(
        self,
        stop: Event,
        max_jobs: int = 25,
        max_seconds: float = 45.0,
        idle_seconds: float = 1.0,
        error_seconds: float = 5.0,
    ) -> dict[str, int]:
        """CloudLinux LVE-bounded consumer loop (scaling masterplan).

        Processes at most ``max_jobs`` jobs or runs for at most ``max_seconds``
        (whichever the queue yields first), then returns so the operating
        system fully reclaims CPU and RAM between cron cycles. The durable PHP
        queue remains the source of truth, so exiting mid-backlog is safe —
        the next invocation continues where this one stopped.
        """
        import time

        max_jobs = max(1, min(500, int(max_jobs)))
        max_seconds = max(5.0, min(300.0, float(max_seconds)))
        deadline = time.monotonic() + max_seconds
        processed = succeeded = failed = 0
        idle_seconds = max(0.1, min(30.0, float(idle_seconds)))
        error_seconds = max(1.0, min(120.0, float(error_seconds)))

        while (
            not stop.is_set() and processed < max_jobs and time.monotonic() < deadline
        ):
            try:
                outcome = self.run_batch(1)
            except Exception as error:  # noqa: BLE001 - bounded loop reports and yields one cycle
                logging.getLogger("kingsway.python_worker").error(
                    "queue polling failed (%s); retrying after bounded delay",
                    type(error).__name__,
                )
                stop.wait(min(error_seconds, max(0.0, deadline - time.monotonic())))
                continue
            if outcome["processed"] == 0:
                stop.wait(min(idle_seconds, max(0.0, deadline - time.monotonic())))
                continue
            processed += int(outcome["processed"])
            succeeded += int(outcome["succeeded"])
            failed += int(outcome["failed"])

        return {"processed": processed, "succeeded": succeeded, "failed": failed}

    def run_forever(
        self,
        stop: Event,
        idle_seconds: float = 1.0,
        error_seconds: float = 5.0,
    ) -> None:
        """Consume the PHP-owned queue in a separately supervised process.

        This entry point is for a process manager (systemd/supervisor), not a
        WSGI request thread or a cron dispatcher. PHP remains the durable queue
        and permission boundary; one process handles one job at a time.
        """
        idle_seconds = max(0.1, min(30.0, float(idle_seconds)))
        error_seconds = max(1.0, min(120.0, float(error_seconds)))
        while not stop.is_set():
            try:
                outcome = self.run_batch(1)
                if outcome["processed"] == 0:
                    stop.wait(idle_seconds)
            except Exception as error:  # noqa: BLE001 - supervisor restarts process on fatal faults
                logging.getLogger("kingsway.python_worker").error(
                    "queue polling failed (%s); retrying after bounded delay",
                    type(error).__name__,
                )
                stop.wait(error_seconds)
