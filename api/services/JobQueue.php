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
 * Writers push jobs with status 'pending'. The cron worker
 * (scripts/cron/worker.php, or the HTTP fallback at POST /api/realtime/worker)
 * claims, processes and finalises them. Claims use a guarded UPDATE so
 * concurrent / overlapping workers cannot double-process a row. Failed jobs
 * retry with per-job exponential backoff ($max_attempts / $backoff_seconds)
 * and, once exhausted, are copied to the dead-letter registry for review and
 * safe requeue.
 */
final class JobQueue
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    private const MAX_ATTEMPTS = 20;
    private const MAX_BACKOFF = 3600;

    /**
     * Enqueue a background job.
     *
     * @param string $jobType       Stable job type key consumed by the worker switch.
     * @param array  $payload       Arbitrary worker payload.
     * @param int    $delaySeconds  Optional delay before the job is available.
     * @param int    $maxAttempts   Attempt limit before the job is dead-lettered (1-20).
     * @param int    $backoffSeconds Base backoff for the first retry (5-3600); doubles each retry.
     * @return int New job id.
     */
    public static function push(
        string $jobType,
        array $payload = [],
        int $delaySeconds = 0,
        int $maxAttempts = 3,
        int $backoffSeconds = 60
    ): int {
        if (!preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $jobType)) {
            throw new \InvalidArgumentException('Invalid background job type.');
        }
        $maxAttempts = self::clampAttempts($maxAttempts);
        $backoffSeconds = self::clampBackoff($backoffSeconds);
        $availableAt = date('Y-m-d H:i:s', time() + max(0, (int) $delaySeconds));
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return ConnectionManager::run(static function (PDO $pdo) use (
            $jobType,
            $encoded,
            $availableAt,
            $maxAttempts,
            $backoffSeconds
        ): int {
            $stmt = $pdo->prepare(
                "INSERT INTO jobs_queue
                    (job_type, payload, attempts, max_attempts, backoff_seconds, status, available_at)
                 VALUES (?, ?, 0, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $jobType,
                $encoded,
                $maxAttempts,
                $backoffSeconds,
                self::STATUS_PENDING,
                $availableAt,
            ]);
            return (int) $pdo->lastInsertId();
        }, ConnectionManager::NS_BUFFERS);
    }

    /**
     * Atomically claim a batch of pending jobs for processing.
     *
     * @return int[] Claimed (now processing) job ids.
     */
    public static function claimBatch(int $limit = 10): array
    {
        $limit = max(1, min(500, $limit));

        return ConnectionManager::run(static function (PDO $pdo) use ($limit): array {
            $ids = [];
            // LIMIT is bound as a prepared literal ($limit is already int-clamped).
            $stmt = $pdo->prepare(
                "SELECT id FROM jobs_queue
                 WHERE status = ? AND available_at <= NOW()
                 ORDER BY id ASC
                 LIMIT {$limit}"
            );
            $stmt->execute([self::STATUS_PENDING]);
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

    /**
     * Fetch one queued job for dispatch.
     *
     * @return array{id:int, job_type:string, payload:array, status:string,
     *               attempts:int, max_attempts:int, backoff_seconds:int,
     *               available_at:string, created_at:string, updated_at:string,
     *               failed_reason:?string, dead_letter_reason:?string}|null
     */
    public static function fetchJob(int $id): ?array
    {
        return ConnectionManager::run(static function (PDO $pdo) use ($id): ?array {
            $stmt = $pdo->prepare(
                "SELECT id, job_type, payload, status, attempts, max_attempts, backoff_seconds,
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

    /**
     * Recover jobs abandoned by a worker crash or hosting timeout. A failed job
     * lease re-queues until its attempt budget is exhausted, then dead-letters.
     */
    public static function recoverStale(int $leaseMinutes = 15): int
    {
        $leaseMinutes = max(5, min(1440, $leaseMinutes));

        return ConnectionManager::run(static function (PDO $pdo) use ($leaseMinutes): int {
            $stmt = $pdo->prepare(
                "SELECT id FROM jobs_queue
                 WHERE status = ? AND updated_at < NOW() - INTERVAL {$leaseMinutes} MINUTE"
            );
            $stmt->execute([self::STATUS_PROCESSING]);
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
                "SELECT id, job_type, status, attempts, max_attempts, backoff_seconds,
                        available_at, created_at, updated_at, failed_reason, dead_letter_reason
                 FROM jobs_queue ORDER BY id DESC LIMIT {$limit}"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    (job_type, payload, attempts, max_attempts, backoff_seconds, status, available_at)
                 VALUES (?, ?, 0, ?, ?, ?, NOW())"
            );
            $insert->execute([
                (string) $row['job_type'],
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

    private static function clampBackoff(int $value): int
    {
        return max(5, min(self::MAX_BACKOFF, $value));
    }

    private static function truncate(string $text): string
    {
        return mb_strlen($text) > 490 ? mb_substr($text, 0, 490) : $text;
    }
}