<?php

declare(strict_types=1);

/**
 * Bounded local resilience evidence for the AI platform.
 *
 * This command creates one uniquely-keyed queue row, verifies duplicate
 * suppression, and removes only that row. It also verifies offline replay
 * idempotency in an isolated temporary directory. No prompts, credentials,
 * learner data, or provider responses are printed.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\API\Services\JobQueue;
use App\API\Services\OfflineReplayBuffer;
use App\Database\ConnectionManager;

$jobIds = [];
$tempDirectory = sys_get_temp_dir() . '/kingsway-ai-resilience-' . bin2hex(random_bytes(6));

$cleanupDirectory = static function (string $directory): void {
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($directory);
};

try {
    $idempotencyKey = 'ai-resilience-' . bin2hex(random_bytes(8));
    $payload = ['idempotency_key' => $idempotencyKey, 'request_id' => 'local-resilience-smoke'];
    $jobIds[] = JobQueue::push('rebuild.read.replica', $payload, 3600, 1, 5);
    $jobIds[] = JobQueue::push('rebuild.read.replica', $payload, 3600, 1, 5);

    if ($jobIds[0] !== $jobIds[1]) {
        throw new RuntimeException('Live queue idempotency suppression failed.');
    }

    $buffer = new OfflineReplayBuffer($tempDirectory);
    $first = $buffer->enqueue('ai.scope', 'draft.save', ['status' => 'pending'], 'offline-resilience-1', 3600);
    $second = $buffer->enqueue('ai.scope', 'draft.save', ['status' => 'changed'], 'offline-resilience-1', 3600);
    if ($first !== $second) {
        throw new RuntimeException('Offline duplicate envelope suppression failed.');
    }

    $calls = 0;
    $replayed = $buffer->replayPending('ai.scope', static function (array $entry) use (&$calls): array {
        $calls++;
        return ['accepted' => true];
    });
    $again = $buffer->replayPending('ai.scope', static function () use (&$calls): void {
        $calls++;
    });
    if (($replayed[0]['status'] ?? '') !== 'replayed' || $calls !== 1 || $again !== []) {
        throw new RuntimeException('Offline replay idempotency verification failed.');
    }

    echo json_encode([
        'live_queue_duplicate_suppressed' => true,
        'offline_duplicate_suppressed' => true,
        'offline_replay_calls' => $calls,
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'AI resilience smoke failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($jobIds !== []) {
        try {
            ConnectionManager::run(static function (PDO $pdo) use ($jobIds): void {
                $delete = $pdo->prepare('DELETE FROM jobs_queue WHERE id = ?');
                foreach (array_unique($jobIds) as $jobId) {
                    $delete->execute([(int) $jobId]);
                }
            }, ConnectionManager::NS_BUFFERS);
        } catch (Throwable $ignored) {
            // Never hide the primary smoke failure; the ids are uniquely scoped.
        }
    }
    $cleanupDirectory($tempDirectory);
}
