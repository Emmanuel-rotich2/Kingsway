<?php

namespace App\API\Services;

use App\Database\ConnectionManager;
use PDO;
use RuntimeException;

/**
 * JobQueue - background job dispatcher for shared hosting (roadmap §4.1/§4.8).
 *
 * Every queue row lives in the KingsWayBuffers offload schema, switched on the
 * single PDO through ConnectionManager::run(). The transactional KingsWayAcademy
 * database is never touched for queue bookkeeping, so background churn cannot
 * slow down primary reads/writes.
 *
 * Writers push jobs with status 'pending'. The worker at
 * POST /api/realtime/worker — invoked by a single `curl` line in the crontab in
 * both localhost and production — claims, processes and finalises them. The
 * legacy scripts/cron/worker.php CLI is dormant and must not be scheduled; see
 * AGENTS.md "Background scheduling (curl crontab only)". Claims use a guarded UPDATE so
 * concurrent / overlapping workers cannot double-process a row. Failed jobs
 * retry with per-job exponential backoff ($max_attempts / $backoff_seconds)
 * and, once exhausted, are copied to the dead-letter registry for review and
 * safe requeue.
 */
final class JobQueue
{
    /** Job families reserved for Python's internal worker endpoint. */
    public const PYTHON_JOB_TYPES = [
        'reads.projection.refresh',
        'automation.run',
        // Batch families are registered here for the shared queue contract.
        // RealtimeController activates them only after its PHP-owned
        // authorization/input/result boundary has been implemented.
        'academic.report_card_batch',
        'media.photo_normalize',
        'analytics.parquet_buffer',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Priority lanes (scaling masterplan §"Multi-Lane Priority Queue").
     * Claims are ordered (priority ASC, id ASC) so batch floods cannot starve
     * critical payment-webhook and auth work; lane 1 must complete within 5s.
     */
    public const PRIORITY_CRITICAL = 1;
    public const PRIORITY_NORMAL = 2;
    public const PRIORITY_BATCH = 3;

    /** Exact job types that always run on the BATCH lane. */
    private const BATCH_JOB_TYPES = [
        'reads.projection.refresh', 'automation.run', 'ai.analytics.insight',
        'ai.insight.generate', 'ai.agent.run', 'ai.workflow.draft',
        'curriculum.policy_interpret', 'rpc.async.dispatch',
        'rebake_realtime_buffer', 'purge_old_realtime_events',
    ];

    /** Job-type prefixes that always run on the CRITICAL lane. */
    private const CRITICAL_JOB_PREFIXES = [
        'payment.', 'payments.', 'mpesa.', 'kcb.', 'auth.token.', 'security.',
    ];

    /** Job-type prefixes that always run on the BATCH lane. */
    private const BATCH_JOB_PREFIXES = ['export.', 'exports.', 'report.', 'print.'];

    private const MAX_ATTEMPTS = 20;
    private const MAX_BACKOFF = 3600;
    private const MAX_PAYLOAD_BYTES = 131072;

    /**
     * Enqueue a background job.
     *
     * @param string $jobType       Stable job type key consumed by the worker switch.
     * @param array  $payload       Arbitrary worker payload.
     * @param int    $delaySeconds  Optional delay before the job is available.
     * @param int    $maxAttempts   Attempt limit before the job is dead-lettered (1-20).
     * @param int    $backoffSeconds Base backoff for the first retry (5-3600); doubles each retry.
     * @param int|null $priority    Priority lane 1-3; null auto-classifies from the job type.
     * @return int New job id.
     */
    public static function push(
        string $jobType,
        array $payload = [],
        int $delaySeconds = 0,
        int $maxAttempts = 3,
        int $backoffSeconds = 60,
        ?int $priority = null
    ): int {
        if (!preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $jobType)) {
            throw new \InvalidArgumentException('Invalid background job type.');
        }
        $maxAttempts = self::clampAttempts($maxAttempts);
        $backoffSeconds = self::clampBackoff($backoffSeconds);
        $priority = self::clampPriority($priority ?? self::priorityForType($jobType));
        $availableAt = date('Y-m-d H:i:s', time() + max(0, (int) $delaySeconds));
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        if (strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            throw new \InvalidArgumentException('Background job payload exceeds the 128 KB limit.');
        }

        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '' && (strlen($idempotencyKey) > 128 || preg_match('/^[A-Za-z0-9._:-]+$/', $idempotencyKey) !== 1)) {
            throw new \InvalidArgumentException('Background job idempotency key is invalid.');
        }

        return ConnectionManager::run(static function (PDO $pdo) use (
            $jobType,
            $encoded,
            $idempotencyKey,
            $availableAt,
            $maxAttempts,
            $backoffSeconds,
            $priority
        ): int {
            $lockName = '';
            if ($idempotencyKey !== '') {
                // MySQL named locks serialize only the same idempotency key and
                // are released in finally, including worker/request failure.
                $lockName = 'KingswayJob:' . hash('sha256', $jobType . ':' . $idempotencyKey);
                $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
                $lock->execute([$lockName]);
                if ((int) $lock->fetchColumn() !== 1) {
                    throw new RuntimeException('Unable to acquire the job idempotency lock.');
                }
                try {
                    $existing = $pdo->prepare(
                        "SELECT id FROM jobs_queue
                         WHERE job_type = ?
                           AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.idempotency_key')) = ?
                           AND status IN (?, ?, ?)
                         ORDER BY id DESC LIMIT 1"
                    );
                    $existing->execute([$jobType, $idempotencyKey, self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_DONE]);
                    $existingId = $existing->fetchColumn();
                    if ($existingId !== false) return (int) $existingId;

                    $stmt = $pdo->prepare(
                        "INSERT INTO jobs_queue
                            (job_type, priority, payload, attempts, max_attempts, backoff_seconds, status, available_at)
                         VALUES (?, ?, ?, 0, ?, ?, ?, ?)"
                    );
                    $stmt->execute([$jobType, $priority, $encoded, $maxAttempts, $backoffSeconds, self::STATUS_PENDING, $availableAt]);
                    return (int) $pdo->lastInsertId();
                } finally {
                    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                }
            }

            $stmt = $pdo->prepare(
                "INSERT INTO jobs_queue
                    (job_type, priority, payload, attempts, max_attempts, backoff_seconds, status, available_at)
                 VALUES (?, ?, ?, 0, ?, ?, ?, ?)"
            );
            $stmt->execute([$jobType, $priority, $encoded, $maxAttempts, $backoffSeconds, self::STATUS_PENDING, $availableAt]);
            return (int) $pdo->lastInsertId();
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Locate an already-active (pending or processing) job by type and one
     * scalar payload field.
     *
     * Used by schedulers that would otherwise enqueue duplicate work for the
     * same logical unit (e.g. one projection-refresh job per projection): the
     * caller skips the push while a job for that unit is still outstanding.
     * Completed jobs are deliberately NOT matched, so the unit may be
     * scheduled again after it has actually run.
     *
     * @return int|null Outstanding job id, or null when none exists.
     */
    public static function findActiveByTypeAndPayload(string $jobType, string $field, string $value): ?int
    {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $field) !== 1) {
            throw new \InvalidArgumentException('Invalid payload field name.');
        }
        if (strlen($value) > 255) {
            throw new \InvalidArgumentException('Payload value lookup exceeds 255 characters.');
        }

        return ConnectionManager::run(static function (PDO $pdo) use ($jobType, $field, $value): ?int {
            $stmt = $pdo->prepare(
                "SELECT id FROM jobs_queue
                 WHERE job_type = ?
                   AND status IN (?, ?)
                   AND JSON_UNQUOTE(JSON_EXTRACT(payload, ?)) = ?
                 ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute([
                $jobType,
                self::STATUS_PENDING,
                self::STATUS_PROCESSING,
                '$.' . $field,
                $value,
            ]);
            $id = $stmt->fetchColumn();
            return $id === false ? null : (int) $id;
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Atomically claim a batch of pending jobs for processing.
     *
     * @return int[] Claimed (now processing) job ids.
     */
    public static function claimBatch(int $limit = 10, array $excludeJobTypes = []): array
    {
        $limit = max(1, min(500, $limit));
        $excludeJobTypes = self::normalizeJobTypes($excludeJobTypes);

        return ConnectionManager::run(static function (PDO $pdo) use ($limit, $excludeJobTypes): array {
            $ids = [];
            $exclusion = $excludeJobTypes === [] ? '' : ' AND job_type NOT IN (' . implode(',', array_fill(0, count($excludeJobTypes), '?')) . ')';
            // LIMIT is bound as a prepared literal ($limit is already int-clamped).
            $stmt = $pdo->prepare(
                "SELECT id FROM jobs_queue
                 WHERE status = ? AND available_at <= NOW(){$exclusion}
                 ORDER BY priority ASC, id ASC
                 LIMIT {$limit}"
            );
            $stmt->execute(array_merge([self::STATUS_PENDING], $excludeJobTypes));
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($rows as $rawId) {
                $id = (int) $rawId;
                $claim = $pdo->prepare(
                    "UPDATE jobs_queue
                     SET status = ?, updated_at = NOW()
                     WHERE id = ? AND status = ?"
                );
                $claim->execute([self::STATUS_PROCESSING, $id, self::STATUS_PENDING]);
                if ($claim->rowCount() === 1) {
                    $ids[] = $id;
                }
            }
            return $ids;
        }, ConnectionManager::NS_BUFFERS);
    }

    /** Claim one or more jobs exclusively from an allowlisted runtime family. */
    public static function claimBatchForTypes(array $jobTypes, int $limit = 1): array
    {
        $jobTypes = self::normalizeJobTypes($jobTypes);
        if ($jobTypes === []) {
            throw new \InvalidArgumentException('At least one valid job type is required.');
        }
        $limit = max(1, min(50, $limit));

        return ConnectionManager::run(static function (PDO $pdo) use ($limit, $jobTypes): array {
            $ids = [];
            $marks = implode(',', array_fill(0, count($jobTypes), '?'));
            $stmt = $pdo->prepare(
                "SELECT id FROM jobs_queue
                 WHERE status = ? AND available_at <= NOW() AND job_type IN ({$marks})
                 ORDER BY priority ASC, id ASC LIMIT {$limit}"
            );
            $stmt->execute(array_merge([self::STATUS_PENDING], $jobTypes));
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rawId) {
                $id = (int) $rawId;
                $claim = $pdo->prepare(
                    'UPDATE jobs_queue SET status = ?, updated_at = NOW() WHERE id = ? AND status = ?'
                );
                $claim->execute([self::STATUS_PROCESSING, $id, self::STATUS_PENDING]);
                if ($claim->rowCount() === 1) $ids[] = $id;
            }
            return $ids;
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Claim Python-routed jobs that have waited past the takeover window.
     *
     * The Python worker keeps first claim on its job families, but when that
     * runtime is down or draining slower than the refresh cadence, its rows
     * would otherwise sit pending forever while every projection they own
     * drifts stale and consuming pages fail. The PHP worker may take over a
     * starved job only when a registered PHP handler exists for its type
     * (e.g. the ReadProjectionSynchronizer fallback for
     * reads.projection.refresh), so a dead runtime can never strand the
     * pipeline again. Claims are atomic on the same pending->processing
     * transition Python uses, so a job can never execute twice.
     *
     * @return int[] Claimed (now processing) job ids.
     */
    public static function claimStarvedForTypes(array $jobTypes, int $starveSeconds = 120, int $limit = 5): array
    {
        $jobTypes = self::normalizeJobTypes($jobTypes);
        if ($jobTypes === []) {
            return [];
        }
        $starveSeconds = max(30, min(3600, $starveSeconds));
        $limit = max(1, min(50, $limit));

        return ConnectionManager::run(static function (PDO $pdo) use ($jobTypes, $starveSeconds, $limit): array {
            $ids = [];
            $marks = implode(',', array_fill(0, count($jobTypes), '?'));
            // $starveSeconds and $limit are int-clamped prepared literals.
            $stmt = $pdo->prepare(
                "SELECT id FROM jobs_queue
                 WHERE status = ? AND job_type IN ({$marks})
                   AND available_at <= NOW() - INTERVAL {$starveSeconds} SECOND
                 ORDER BY priority ASC, id ASC
                 LIMIT {$limit}"
            );
            $stmt->execute(array_merge([self::STATUS_PENDING], $jobTypes));
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rawId) {
                $id = (int) $rawId;
                $claim = $pdo->prepare(
                    'UPDATE jobs_queue SET status = ?, updated_at = NOW() WHERE id = ? AND status = ?'
                );
                $claim->execute([self::STATUS_PROCESSING, $id, self::STATUS_PENDING]);
                if ($claim->rowCount() === 1) $ids[] = $id;
            }
            return $ids;
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Whether an outstanding job has sat untouched longer than the given
     * window. Schedulers use this to break the "already queued"
     * short-circuit when a previous job was never drained by its owning
     * runtime. A job row that no longer exists counts as stale so the
     * caller re-queues fresh work instead of stalling on a phantom.
     */
    public static function activeJobOlderThan(int $id, int $seconds): bool
    {
        $seconds = max(1, min(86400, $seconds));

        return ConnectionManager::run(static function (PDO $pdo) use ($id, $seconds): bool {
            $stmt = $pdo->prepare(
                'SELECT updated_at < NOW() - INTERVAL ' . $seconds . ' SECOND FROM jobs_queue WHERE id = ?'
            );
            $stmt->execute([$id]);
            $aged = $stmt->fetchColumn();
            return $aged === false ? true : (bool) $aged;
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Fetch one queued job for dispatch.
     *
     * @return array{id:int, job_type:string, priority:int, payload:array, status:string,
     *               attempts:int, max_attempts:int, backoff_seconds:int,
     *               available_at:string, created_at:string, updated_at:string,
     *               failed_reason:?string, dead_letter_reason:?string}|null
     */
    public static function fetchJob(int $id): ?array
    {
        return ConnectionManager::run(static function (PDO $pdo) use ($id): ?array {
            $stmt = $pdo->prepare(
                "SELECT id, job_type, priority, payload, status, attempts, max_attempts, backoff_seconds,
                        available_at, created_at, updated_at, failed_reason, dead_letter_reason
                 FROM jobs_queue WHERE id = ?"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            $decoded = json_decode((string) $row['payload'], true);
            $row['payload'] = is_array($decoded) ? $decoded : [];
            $row['id'] = (int) $row['id'];
            $row['priority'] = (int) ($row['priority'] ?? self::PRIORITY_NORMAL);
            $row['attempts'] = (int) $row['attempts'];
            $row['max_attempts'] = (int) $row['max_attempts'];
            $row['backoff_seconds'] = (int) $row['backoff_seconds'];
            return $row;
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Mark a job as completed.
     */
    public static function markDone(int $id): void
    {
        ConnectionManager::run(static function (PDO $pdo) use ($id): void {
            $stmt = $pdo->prepare(
                "UPDATE jobs_queue
                 SET status = ?, failed_reason = NULL, dead_letter_reason = NULL, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([self::STATUS_DONE, $id]);
        }, ConnectionManager::NS_BUFFERS);
    }

    /** Complete only the exact processing attempt returned to a Python worker. */
    public static function markDoneForAttempt(int $id, int $attempts): bool
    {
        return ConnectionManager::run(static function (PDO $pdo) use ($id, $attempts): bool {
            $stmt = $pdo->prepare(
                'UPDATE jobs_queue SET status = ?, failed_reason = NULL, dead_letter_reason = NULL, updated_at = NOW() '
                . 'WHERE id = ? AND status = ? AND attempts = ?'
            );
            $stmt->execute([self::STATUS_DONE, $id, self::STATUS_PROCESSING, max(0, $attempts)]);
            return $stmt->rowCount() === 1;
        }, ConnectionManager::NS_BUFFERS);
    }

    /** Extend a Python job lease only while the same attempt remains active. */
    public static function touchProcessingAttempt(int $id, int $attempts): bool
    {
        return ConnectionManager::run(static function (PDO $pdo) use ($id, $attempts): bool {
            $stmt = $pdo->prepare(
                'UPDATE jobs_queue SET updated_at = NOW() WHERE id = ? AND status = ? AND attempts = ?'
            );
            $stmt->execute([$id, self::STATUS_PROCESSING, max(0, $attempts)]);
            return $stmt->rowCount() === 1;
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Record a job failure. While attempts remain the job returns to 'pending'
     * with exponential backoff; at exhaustion it becomes 'failed' and a copy is
     * registered in the dead-letter queue.
     *
     * @return string Final status (STATUS_PENDING for retry, STATUS_FAILED when dead-lettered).
     */
    public static function markFailed(int $id, string $reason = ''): string
    {
        return ConnectionManager::run(static function (PDO $pdo) use ($id, $reason): string {
            $row = self::findRow($pdo, $id);
            if ($row === null || $row['status'] !== self::STATUS_PROCESSING) {
                return self::STATUS_FAILED;
            }

            $maxAttempts = self::clampAttempts((int) $row['max_attempts']);
            $backoffSeconds = self::clampBackoff((int) $row['backoff_seconds']);
            $attempts = (int) $row['attempts'] + 1;
            $reason = self::truncate((string) $reason);

            if ($attempts >= $maxAttempts) {
                return self::finishAsFailed($pdo, $row, $attempts, $reason);
            }

            $delay = min(self::MAX_BACKOFF, $backoffSeconds * (int) pow(2, $attempts - 1));
            $stmt = $pdo->prepare(
                "UPDATE jobs_queue
                 SET attempts = ?, status = ?, available_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                     failed_reason = ?, dead_letter_reason = NULL, updated_at = NOW()
                 WHERE id = ? AND status = ?"
            );
            $stmt->execute([$attempts, self::STATUS_PENDING, $delay, $reason, $id, self::STATUS_PROCESSING]);
            return self::STATUS_PENDING;
        }, ConnectionManager::NS_BUFFERS);
    }

    /** Fail only the exact processing attempt returned to a Python worker. */
    public static function markFailedForAttempt(int $id, int $expectedAttempts, string $reason = ''): ?string
    {
        return ConnectionManager::run(static function (PDO $pdo) use ($id, $expectedAttempts, $reason): ?string {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'SELECT id, job_type, payload, status, attempts, max_attempts, backoff_seconds '
                    . 'FROM jobs_queue WHERE id = ? FOR UPDATE'
                );
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['status'] !== self::STATUS_PROCESSING
                    || (int) $row['attempts'] !== max(0, $expectedAttempts)) {
                    $pdo->commit();
                    return null;
                }
                $attempts = (int) $row['attempts'] + 1;
                $reason = self::truncate((string) $reason);
                if ($attempts >= self::clampAttempts((int) $row['max_attempts'])) {
                    $status = self::finishAsFailed($pdo, $row, $attempts, $reason);
                } else {
                    $delay = min(self::MAX_BACKOFF, self::clampBackoff((int) $row['backoff_seconds']) * (int) pow(2, $attempts - 1));
                    $update = $pdo->prepare(
                        'UPDATE jobs_queue SET attempts = ?, status = ?, available_at = DATE_ADD(NOW(), INTERVAL ? SECOND), '
                        . 'failed_reason = ?, dead_letter_reason = NULL, updated_at = NOW() WHERE id = ? AND status = ?'
                    );
                    $update->execute([$attempts, self::STATUS_PENDING, $delay, $reason, $id, self::STATUS_PROCESSING]);
                    $status = self::STATUS_PENDING;
                }
                $pdo->commit();
                return $status;
            } catch (\Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Recover jobs abandoned by a worker crash or hosting timeout. A failed job
     * lease re-queues until its attempt budget is exhausted, then dead-letters.
     */
    public static function recoverStale(int $leaseMinutes = 15, array $excludeJobTypes = []): int
    {
        $leaseMinutes = max(5, min(1440, $leaseMinutes));
        $excludeJobTypes = self::normalizeJobTypes($excludeJobTypes);

        return ConnectionManager::run(static function (PDO $pdo) use ($leaseMinutes, $excludeJobTypes): int {
            $exclusion = $excludeJobTypes === [] ? '' : ' AND job_type NOT IN (' . implode(',', array_fill(0, count($excludeJobTypes), '?')) . ')';
            $stmt = $pdo->prepare(
                "SELECT id FROM jobs_queue
                 WHERE status = ? AND updated_at < NOW() - INTERVAL {$leaseMinutes} MINUTE{$exclusion}"
            );
            $stmt->execute(array_merge([self::STATUS_PROCESSING], $excludeJobTypes));
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $recovered = 0;
            foreach ($ids as $rawId) {
                $id = (int) $rawId;
                $row = self::findRow($pdo, $id);
                if ($row === null || $row['status'] !== self::STATUS_PROCESSING) {
                    continue;
                }
                $maxAttempts = self::clampAttempts((int) $row['max_attempts']);
                $attempts = (int) $row['attempts'] + 1;

                if ($attempts >= $maxAttempts) {
                    self::finishAsFailed($pdo, $row, $attempts, 'Recovered after worker lease expired too many times');
                } else {
                    $stmt = $pdo->prepare(
                        "UPDATE jobs_queue
                         SET attempts = ?, status = ?, available_at = NOW(),
                             failed_reason = ?, dead_letter_reason = NULL, updated_at = NOW()
                         WHERE id = ? AND status = ?"
                    );
                    $stmt->execute([
                        $attempts,
                        self::STATUS_PENDING,
                        'Recovered after worker lease expired',
                        $id,
                        self::STATUS_PROCESSING,
                    ]);
                }
                $recovered++;
            }
            return $recovered;
        }, ConnectionManager::NS_BUFFERS);
    }

    /** Recover stale jobs owned by a specific worker runtime. */
    public static function recoverStaleForTypes(array $jobTypes, int $leaseMinutes = 15): int
    {
        $jobTypes = self::normalizeJobTypes($jobTypes);
        if ($jobTypes === []) return 0;
        $leaseMinutes = max(5, min(1440, $leaseMinutes));

        return ConnectionManager::run(static function (PDO $pdo) use ($leaseMinutes, $jobTypes): int {
            $marks = implode(',', array_fill(0, count($jobTypes), '?'));
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    "SELECT id, job_type, payload, status, attempts, max_attempts, backoff_seconds
                     FROM jobs_queue
                     WHERE status = ? AND job_type IN ({$marks})
                       AND updated_at < NOW() - INTERVAL {$leaseMinutes} MINUTE
                     ORDER BY updated_at ASC LIMIT 50 FOR UPDATE"
                );
                $stmt->execute(array_merge([self::STATUS_PROCESSING], $jobTypes));
                $staleRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $recovered = 0;
                foreach ($staleRows as $row) {
                    $id = (int) $row['id'];
                    $attempts = (int) $row['attempts'] + 1;
                    if ($attempts >= self::clampAttempts((int) $row['max_attempts'])) {
                        self::finishAsFailed($pdo, $row, $attempts, 'Recovered after Python worker lease expired too many times');
                    } else {
                        $update = $pdo->prepare(
                            'UPDATE jobs_queue SET attempts = ?, status = ?, available_at = NOW(), failed_reason = ?, dead_letter_reason = NULL, updated_at = NOW() WHERE id = ? AND status = ?'
                        );
                        $update->execute([$attempts, self::STATUS_PENDING, 'Recovered after Python worker lease expired', $id, self::STATUS_PROCESSING]);
                    }
                    $recovered++;
                }
                $pdo->commit();
                return $recovered;
            } catch (\Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        }, ConnectionManager::NS_BUFFERS);
    }

    /** @return list<string> */
    private static function normalizeJobTypes(array $jobTypes): array
    {
        $valid = [];
        foreach ($jobTypes as $jobType) {
            $jobType = is_string($jobType) ? trim($jobType) : '';
            if ($jobType !== '' && preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $jobType) === 1) $valid[] = $jobType;
        }
        return array_values(array_unique($valid));
    }

    /**
     * Bounded retention maintenance for the offload schema.
     *
     * @return array{jobs_purged:int, failed_jobs_purged:int, dead_letter_purged:int}
     */
    public static function purgeOld(int $doneHours = 24, int $failedDays = 7, int $deadLetterDays = 90): array
    {
        return ConnectionManager::run(static function (PDO $pdo) use (
            $doneHours,
            $failedDays,
            $deadLetterDays
        ): array {
            $doneHours = max(1, min(336, (int) $doneHours));
            $failedDays = max(1, min(90, (int) $failedDays));
            $deadLetterDays = max(1, min(365, (int) $deadLetterDays));

            $report = [];
            $stmt = $pdo->prepare(
                "DELETE FROM jobs_queue
                 WHERE status IN (?, ?) AND updated_at < NOW() - INTERVAL {$doneHours} HOUR"
            );
            $stmt->execute([self::STATUS_DONE, self::STATUS_CANCELLED]);
            $report['jobs_purged'] = $stmt->rowCount();

            $stmt = $pdo->prepare(
                "DELETE FROM jobs_queue
                 WHERE status = ? AND updated_at < NOW() - INTERVAL {$failedDays} DAY"
            );
            $stmt->execute([self::STATUS_FAILED]);
            $report['failed_jobs_purged'] = $stmt->rowCount();

            $stmt = $pdo->prepare(
                "DELETE FROM dead_letter_queue
                 WHERE dead_lettered_at < NOW() - INTERVAL {$deadLetterDays} DAY"
            );
            $stmt->execute();
            $report['dead_letter_purged'] = $stmt->rowCount();

            return $report;
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Recent jobs for the System Administrator console.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listRecent(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        return ConnectionManager::run(static function (PDO $pdo) use ($limit): array {
            $stmt = $pdo->prepare(
                "SELECT id, job_type, priority, status, attempts, max_attempts, backoff_seconds,
                        available_at, created_at, updated_at, failed_reason, dead_letter_reason
                 FROM jobs_queue ORDER BY id DESC LIMIT {$limit}"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Deterministic queue-health snapshot for observability surfaces.
     *
     * Returns counts by status plus dead-letter totals and staleness signals.
     * Stale = a pending/processing job whose updated_at predates the lease
     * window (15 minutes) and is therefore presumed orphaned.
     *
     * @return array{
     *      generated_at:string, statuses:array<string,int>,
     *      deadline_letter_total:int, dead_letter_24h:int,
     *      stale_processing:int, stale_pending:int,
     *      oldest_processing_minutes:int, oldest_pending_minutes:int,
     *      jobs_total:int
     * }
     */
    public static function statusSummary(): array
    {
        return ConnectionManager::run(static function (PDO $pdo): array {
            $statuses = [
                self::STATUS_PENDING => 0,
                self::STATUS_PROCESSING => 0,
                self::STATUS_DONE => 0,
                self::STATUS_FAILED => 0,
                self::STATUS_CANCELLED => 0,
            ];
            foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM jobs_queue GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $status = (string) $row['status'];
                if (isset($statuses[$status])) {
                    $statuses[$status] = (int) $row['total'];
                }
            }

            $staleQ = $pdo->prepare(
                "SELECT
                    SUM(CASE WHEN status = 'processing' AND updated_at < NOW() - INTERVAL 15 MINUTE THEN 1 ELSE 0 END) AS stale_processing,
                    SUM(CASE WHEN status = 'pending' AND available_at <= NOW() AND updated_at < NOW() - INTERVAL 15 MINUTE THEN 1 ELSE 0 END) AS stale_pending,
                    MAX(CASE WHEN status = 'processing' THEN TIMESTAMPDIFF(MINUTE, updated_at, NOW()) ELSE 0 END) AS oldest_processing_minutes,
                    MAX(CASE WHEN status = 'pending' AND available_at <= NOW() THEN TIMESTAMPDIFF(MINUTE, updated_at, NOW()) ELSE 0 END) AS oldest_pending_minutes
                 FROM jobs_queue"
            );
            $staleQ->execute();
            $stale = $staleQ->fetch(PDO::FETCH_ASSOC) ?: [];

            $dl = $pdo->prepare(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN dead_lettered_at >= NOW() - INTERVAL 24 HOUR THEN 1 ELSE 0 END) AS last_24h
                 FROM dead_letter_queue"
            );
            $dl->execute();
            $deadLetter = $dl->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'generated_at' => date('Y-m-d H:i:s'),
                'statuses' => $statuses,
                'jobs_total' => array_sum($statuses),
                'dead_letter_total' => (int) ($deadLetter['total'] ?? 0),
                'dead_letter_24h' => (int) ($deadLetter['last_24h'] ?? 0),
                'stale_processing' => (int) ($stale['stale_processing'] ?? 0),
                'stale_pending' => (int) ($stale['stale_pending'] ?? 0),
                'oldest_processing_minutes' => max(0, (int) ($stale['oldest_processing_minutes'] ?? 0)),
                'oldest_pending_minutes' => max(0, (int) ($stale['oldest_pending_minutes'] ?? 0)),
            ];
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Dead-lettered (poisoned) jobs for review and safe requeue.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listDeadLetter(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));

        return ConnectionManager::run(static function (PDO $pdo) use ($limit): array {
            $stmt = $pdo->prepare(
                "SELECT id, job_id, job_type, attempts, max_attempts, reason, dead_lettered_at
                 FROM dead_letter_queue ORDER BY id DESC LIMIT {$limit}"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Cancel a pending or processing job.
     */
    public static function cancelJob(int $id): bool
    {
        return ConnectionManager::run(static function (PDO $pdo) use ($id): bool {
            $stmt = $pdo->prepare(
                "UPDATE jobs_queue
                 SET status = ?, updated_at = NOW()
                 WHERE id = ? AND status IN (?, ?)"
            );
            $stmt->execute([self::STATUS_CANCELLED, $id, self::STATUS_PENDING, self::STATUS_PROCESSING]);
            return $stmt->rowCount() === 1;
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Re-queue a dead-lettered job as a fresh pending job (dead-letter history
     * is kept). Returns the new job id.
     */
    public static function requeueDeadLetter(int $deadLetterId): int
    {
        return ConnectionManager::run(static function (PDO $pdo) use ($deadLetterId): int {
            $stmt = $pdo->prepare(
                "SELECT id, job_id, job_type, payload, attempts, max_attempts FROM dead_letter_queue WHERE id = ?"
            );
            $stmt->execute([$deadLetterId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Dead-lettered job not found.');
            }
            $decoded = json_decode((string) ($row['payload'] ?? '{}'), true);
            $payload = is_array($decoded) ? $decoded : [];
            $backoffSeconds = self::clampBackoff(60);
            $maxAttempts = self::clampAttempts((int) $row['max_attempts']);

            $insert = $pdo->prepare(
                "INSERT INTO jobs_queue
                    (job_type, priority, payload, attempts, max_attempts, backoff_seconds, status, available_at)
                 VALUES (?, ?, ?, 0, ?, ?, ?, NOW())"
            );
            $insert->execute([
                (string) $row['job_type'],
                self::priorityForType((string) $row['job_type']),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $maxAttempts,
                $backoffSeconds,
                self::STATUS_PENDING,
            ]);
            return (int) $pdo->lastInsertId();
        }, ConnectionManager::NS_BUFFERS);
    }

    // ───────────────────────── internals ─────────────────────────

    private static function findRow(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT id, job_type, payload, status, attempts, max_attempts, backoff_seconds
             FROM jobs_queue WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function finishAsFailed(PDO $pdo, array $row, int $attempts, string $reason): string
    {
        $id = (int) $row['id'];
        $stmt = $pdo->prepare(
            "UPDATE jobs_queue
             SET attempts = ?, status = ?, failed_reason = ?, dead_letter_reason = ?, updated_at = NOW()
             WHERE id = ? AND status = ?"
        );
        $stmt->execute([$attempts, self::STATUS_FAILED, $reason, $reason, $id, self::STATUS_PROCESSING]);

        $insert = $pdo->prepare(
            "INSERT INTO dead_letter_queue (job_id, job_type, payload, attempts, max_attempts, reason)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insert->execute([
            $id,
            (string) $row['job_type'],
            (string) $row['payload'],
            $attempts,
            (int) $row['max_attempts'],
            self::truncate($reason),
        ]);
        return self::STATUS_FAILED;
    }

    private static function clampAttempts(int $value): int
    {
        return max(1, min(self::MAX_ATTEMPTS, $value));
    }

    /**
     * Lane classification used when a producer does not pass an explicit
     * priority. CRITICAL prefixes outrank exact BATCH names; anything unknown
     * stays NORMAL so historical FIFO behaviour is preserved by default.
     */
    private static function priorityForType(string $jobType): int
    {
        foreach (self::CRITICAL_JOB_PREFIXES as $prefix) {
            if (str_starts_with($jobType, $prefix)) {
                return self::PRIORITY_CRITICAL;
            }
        }
        if (in_array($jobType, self::BATCH_JOB_TYPES, true)) {
            return self::PRIORITY_BATCH;
        }
        foreach (self::BATCH_JOB_PREFIXES as $prefix) {
            if (str_starts_with($jobType, $prefix)) {
                return self::PRIORITY_BATCH;
            }
        }
        return self::PRIORITY_NORMAL;
    }

    private static function clampPriority(int $value): int
    {
        if ($value < self::PRIORITY_CRITICAL) {
            return self::PRIORITY_CRITICAL;
        }
        if ($value > self::PRIORITY_BATCH) {
            return self::PRIORITY_BATCH;
        }
        return $value;
    }

    private static function clampBackoff(int $value): int
    {
        return max(5, min(self::MAX_BACKOFF, $value));
    }

    private static function truncate(string $text): string
    {
        return mb_strlen($text) > 490 ? mb_substr($text, 0, 490) : $text;
    }
}
