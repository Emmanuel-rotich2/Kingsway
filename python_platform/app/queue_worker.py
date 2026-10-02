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
from .read_models import ReadModelError, ReadModelRefresher

PYTHON_JOB_TYPES = {"reads.projection.refresh", "automation.run"}


class QueueWorkerError(RuntimeError):
    pass


class PhpQueueBridge:
    def __init__(self, config: Config):
        self.config = config

    def _bases(self) -> list[tuple[str, str]]:
        bases = []
        if self.config.php_internal_base_url:
            bases.append((self.config.php_internal_base_url.rstrip("/"), self.config.php_internal_host))
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
            request.add_header("X-Kingsway-Worker-Secret", self.config.php_worker_secret)
            if host:
                request.add_header("Host", host)
            context = None if self.config.php_verify_tls else ssl._create_unverified_context()
            try:
                with urllib.request.urlopen(request, timeout=90, context=context) as response:
                    raw = response.read(8 * 1024 * 1024 + 1)
                if len(raw) > 8 * 1024 * 1024:
                    raise QueueWorkerError("PHP queue response exceeds the configured limit")
                decoded = json.loads(raw.decode("utf-8"))
                if not isinstance(decoded, dict) or decoded.get("success") is not True:
                    raise QueueWorkerError("PHP queue endpoint rejected the operation")
                return decoded
            except urllib.error.HTTPError as error:
                # A real HTTP response is authoritative. Do not retry a 4xx/5xx
                # against a second hostname and risk duplicate state changes.
                raise QueueWorkerError(f"PHP queue endpoint returned HTTP {error.code}") from error
            except (urllib.error.URLError, TimeoutError, OSError, json.JSONDecodeError) as error:
                last_error = error
        raise QueueWorkerError("PHP queue endpoint is unavailable") from last_error


class PythonQueueWorker:
    def __init__(self, config: Config, bridge: PhpQueueBridge | None = None):
        self.config = config
        self.bridge = bridge or PhpQueueBridge(config)

    def run_batch(self, limit: int = 3) -> dict[str, Any]:
        limit = max(1, min(10, int(limit)))
        processed = 0
        succeeded = 0
        failed = 0
        types: list[str] = []
        for _ in range(limit):
            claim = self.bridge.post("/api/realtime/python-job-claim", {})
            job = ((claim.get("data") or {}).get("job"))
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

                heartbeat_thread = Thread(target=heartbeat, name=f"queue-lease-{job_id}", daemon=True)
                heartbeat_thread.start()
                if job_type == "reads.projection.refresh":
                    projection = str(payload.get("projection") or "")
                    result = ReadModelRefresher(self.config).refresh(projection)
                    # The queue stores state and the journal stores only safe
                    # operational facts; projection row contents never leave DB.
                    self.bridge.post(
                        "/api/realtime/python-job-complete",
                        {"job_id": job_id, "attempts": claimed_attempts, "result": {
                            "projection": projection,
                            "rows_count": int(result.get("rows_count") or 0),
                            "duration_ms": int(result.get("duration_ms") or 0),
                        }},
                    )
                    succeeded += 1
                elif job_type == "automation.run":
                    envelope = self.bridge.post(
                        "/api/realtime/python-job-input",
                        {"job_id": job_id, "attempts": claimed_attempts},
                    ).get("data") or {}
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
                    wire_result["content_base64"] = base64.b64encode(content.encode("utf-8")).decode("ascii")
                    self.bridge.post(
                        "/api/realtime/python-job-complete",
                        {"job_id": job_id, "attempts": claimed_attempts, "result": wire_result},
                    )
                    succeeded += 1
                else:
                    raise QueueWorkerError("Python handler is not implemented for this job type")
            except Exception as error:  # queue policy remains centralized in PHP
                try:
                    self.bridge.post(
                        "/api/realtime/python-job-fail",
                        {"job_id": job_id, "attempts": claimed_attempts, "reason": type(error).__name__},
                    )
                except Exception as finalize_error:
                    raise QueueWorkerError("Python job failed and PHP could not record the failure") from finalize_error
                failed += 1
            finally:
                heartbeat_stop.set()
                if heartbeat_thread is not None:
                    heartbeat_thread.join(timeout=2)
        return {"processed": processed, "succeeded": succeeded, "failed": failed, "job_types": types}

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
