"""Python handlers for the PHP-owned durable work queue.

Only explicitly registered Python job types are claimed. PHP retains queue
ownership, retry/backoff/dead-letter policy and its worker-secret boundary.
"""

from __future__ import annotations

import base64
import json
import logging
import os
import ssl
from pathlib import Path
from threading import Event, Thread
import urllib.error
import urllib.request
from typing import Any

from .config import Config
from .automations import AutomationEngine
from .batch_contracts import BATCH_FAMILY_CONTRACTS, is_batch_family_enabled
from .read_models import ReadModelError, ReadModelRefresher, PolarsReadModelRefresher
from .realtime import RealtimePublisher
from .sms_provider import DEFAULT_BASE_URL, SmsProvider, SmsProviderError

PYTHON_JOB_TYPES = {
    "reads.projection.refresh",
    "automation.run",
    # PHP stages one bounded batch per bulk messaging send; the Python worker
    # owns the provider transport (TALKSASA) and reports per-recipient rows
    # back for PHP to apply to the communications ledger.
    "communications.sms_batch",
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

    @staticmethod
    def _notification_scope(payload: dict[str, Any]) -> str | None:
        try:
            operator_id = int(payload.get("user_id") or payload.get("operator_id") or 0)
        except (TypeError, ValueError):
            return None
        return f"users:{operator_id}" if operator_id > 0 else None

    def _notify_progress(
        self, job_id: int, progress: int, scope: str | None, status: str = "running"
    ) -> None:
        """Report intermediate progress for a long job.

        Progress is clamped to 0-100 and forced to an int: the engine validates
        this field and a float or an out-of-range value would be rejected as an
        invalid descriptor, turning a progress report into a dropped event.

        Reported only for jobs that are expected to run long. Emitting one frame
        per batch row for a fast job is pure noise on the SSE stream.
        """
        if not scope:
            return
        try:
            self.realtime.publish(
                "JOB_PROGRESS",
                scope,
                {
                    "job_id": job_id,
                    "progress": max(0, min(100, int(progress))),
                    "status": status,
                    "domain": "queue",
                },
            )
        except Exception:
            pass

    def _notify_complete(self, job_id: int, scope: str | None) -> None:
        if not scope:
            return
        try:
            self.realtime.publish(
                "JOB_COMPLETE",
                scope,
                {"job_id": job_id, "status": "completed", "domain": "queue"},
            )
        except Exception:
            pass

    def _notify_failed(self, job_id: int, scope: str | None) -> None:
        if not scope:
            return
        try:
            self.realtime.publish(
                "JOB_FAILED",
                scope,
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
            scope: str | None = None
            progress_holder: dict[str, int] = {}
            try:
                if job_id < 1 or job_type not in PYTHON_JOB_TYPES:
                    raise QueueWorkerError("Unregistered Python job type")
                payload = job.get("payload") or {}
                scope = self._notification_scope(payload)
                self._notify_progress(job_id, 0, scope, "running")

                def heartbeat() -> None:
                    while not heartbeat_stop.wait(120):
                        try:
                            beat: dict[str, Any] = {
                                "job_id": job_id,
                                "attempts": claimed_attempts,
                            }
                            if progress_holder:
                                beat["progress"] = dict(progress_holder)
                            self.bridge.post("/api/realtime/python-job-heartbeat", beat)
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
                    self._notify_complete(job_id, scope)
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
                    self._notify_complete(job_id, scope)
                    succeeded += 1
                elif job_type == "communications.sms_batch":
                    envelope = (
                        self.bridge.post(
                            "/api/realtime/python-job-input",
                            {"job_id": job_id, "attempts": claimed_attempts},
                        ).get("data")
                        or {}
                    )
                    recipient_rows = envelope.get("recipients") or []
                    if not recipient_rows:
                        raise QueueWorkerError("SMS batch input contains no recipients")
                    provider_config = envelope.get("provider") or {}
                    provider = SmsProvider(
                        api_key=str(provider_config.get("api_key") or ""),
                        base_url=str(provider_config.get("api_url") or "")
                        or DEFAULT_BASE_URL,
                        sender_id=str(provider_config.get("sender_id") or "TALKSASA"),
                    )
                    progress_holder["processed"] = 0
                    progress_holder["total"] = len(recipient_rows)

                    def on_batch_progress(processed: int, total_count: int) -> None:
                        progress_holder["processed"] = int(processed)
                        progress_holder["total"] = int(total_count)
                        self._notify_progress(
                            job_id,
                            int(processed * 100 / max(1, total_count)),
                            scope,
                            "running",
                        )

                    rows = provider.send_batch(
                        recipient_rows,
                        str(envelope.get("message") or ""),
                        sms_type=str(envelope.get("mode") or "sms"),
                        sender_id=provider_config.get("sender_id"),
                        media_url=envelope.get("media_url"),
                        language=envelope.get("language"),
                        gender=envelope.get("gender"),
                        recipient_format=envelope.get("recipient_format"),
                        schedule_time=envelope.get("schedule_time"),
                        on_progress=on_batch_progress,
                    )
                    batch_ref = str(
                        envelope.get("batch_ref") or payload.get("batch_ref") or ""
                    )
                    if batch_ref == "":
                        raise QueueWorkerError("SMS batch reference is missing")
                    results_file = f"sms_{batch_ref}.jsonl"
                    results_dir = (
                        Path(self.config.storage_dir) / "communications" / "sms_batches"
                    )
                    results_dir.mkdir(parents=True, exist_ok=True)
                    target = results_dir / results_file
                    temporary = target.with_name(results_file + ".tmp")
                    with open(temporary, "w", encoding="utf-8") as handle:
                        for row in rows:
                            handle.write(json.dumps(row, separators=(",", ":")) + "\n")
                    os.replace(temporary, target)
                    sent = sum(1 for row in rows if row.get("ok"))
                    failed = len(rows) - sent
                    failures = [
                        {
                            "endpoint_id": int(row.get("endpoint_id") or 0),
                            "error": str(row.get("error") or "")[:300],
                        }
                        for row in rows
                        if not row.get("ok")
                    ][:50]
                    self.bridge.post(
                        "/api/realtime/python-job-complete",
                        {
                            "job_id": job_id,
                            "attempts": claimed_attempts,
                            "result": {
                                "batch_ref": batch_ref,
                                "total": len(rows),
                                "sent": sent,
                                "failed": failed,
                                "failures": failures,
                                "results_file": f"communications/sms_batches/{results_file}",
                            },
                        },
                    )
                    self._notify_complete(job_id, scope)
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
                self._notify_failed(job_id, scope)
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
