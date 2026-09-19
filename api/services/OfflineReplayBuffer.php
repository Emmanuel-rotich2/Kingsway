<?php

declare(strict_types=1);

namespace App\API\Services;

use RuntimeException;

/**
 * Durable local queue for explicitly idempotent, non-authoritative retries.
 *
 * This is an offline transport buffer, not an alternative source of truth.
 * Callers must send a stable idempotency key and the replay callback must
 * write through the normal authenticated API/service layer.
 */
final class OfflineReplayBuffer
{
    private const NAMESPACE = 'offline_replay_v1';

    private LocalSqliteBuffer $buffer;
    private string $lockDirectory;

    public function __construct(?string $directory = null)
    {
        $directory = $directory ?: (sys_get_temp_dir() . '/kingsway_offline_replay');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create offline replay directory.');
        }
        @chmod($directory, 0700);
        $this->buffer = new LocalSqliteBuffer($directory . '/store');
        $this->lockDirectory = $directory . '/locks';
        if (!is_dir($this->lockDirectory) && !mkdir($this->lockDirectory, 0700, true) && !is_dir($this->lockDirectory)) {
            throw new RuntimeException('Unable to create offline replay lock directory.');
        }
    }

    public function enqueue(string $scope, string $operation, array $payload, string $idempotencyKey, int $ttl = 86400): array
    {
        $this->validate($scope, $operation, $idempotencyKey);
        $key = $this->entryKey($scope, $idempotencyKey);
        $payloadHash = $this->payloadHash($payload);
        $existing = $this->buffer->get(self::NAMESPACE, $key);
        if (is_array($existing)) {
            $existingHash = (string) ($existing['payload_hash'] ?? '');
            if (($existing['operation'] ?? null) !== $operation || ($existingHash !== '' && !hash_equals($existingHash, $payloadHash))) {
                throw new RuntimeException('Offline replay idempotency key conflicts with an existing operation or payload.');
            }
            return $existing;
        }
        $entry = [
            'scope' => $scope,
            'operation' => $operation,
            'payload' => $payload,
            'payload_hash' => $payloadHash,
            'idempotency_key' => $idempotencyKey,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ];
        $this->buffer->put(self::NAMESPACE, $key, $entry, $ttl);
        return $entry;
    }

    /** Replay pending entries for a scope. Returns per-entry evidence. */
    public function replayPending(string $scope, callable $replay, int $maxAttempts = 3): array
    {
        $results = [];
        foreach ($this->buffer->list(self::NAMESPACE) as $entry) {
            if (!is_array($entry) || ($entry['scope'] ?? null) !== $scope || ($entry['status'] ?? null) !== 'pending') {
                continue;
            }
            $results[] = $this->replayOne($entry, $replay, $maxAttempts);
        }
        return $results;
    }

    public function pending(string $scope): array
    {
        return array_values(array_filter(
            $this->buffer->list(self::NAMESPACE),
            static fn (mixed $entry): bool => is_array($entry) && ($entry['scope'] ?? null) === $scope && ($entry['status'] ?? null) === 'pending'
        ));
    }

    private function replayOne(array $entry, callable $replay, int $maxAttempts): array
    {
        $key = $this->entryKey((string) $entry['scope'], (string) $entry['idempotency_key']);
        $lockHandle = fopen($this->lockDirectory . '/' . hash('sha256', $key) . '.lock', 'c+');
        if (!$lockHandle || !flock($lockHandle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock offline replay entry.');
        }
        try {
            $current = $this->buffer->get(self::NAMESPACE, $key);
            if (!is_array($current) || ($current['status'] ?? null) !== 'pending') {
                return $current ?: $entry;
            }
            $current['status'] = 'processing';
            $current['attempts'] = (int) ($current['attempts'] ?? 0) + 1;
            $current['updated_at'] = gmdate('c');
            $this->buffer->put(self::NAMESPACE, $key, $current);
            try {
                $result = $replay($current);
                $current['status'] = 'replayed';
                $current['result'] = is_scalar($result) || $result === null ? $result : ['accepted' => true];
            } catch (\Throwable $error) {
                $current['status'] = $current['attempts'] >= $maxAttempts ? 'failed' : 'pending';
                $current['last_error'] = substr($error->getMessage(), 0, 240);
            }
            $current['updated_at'] = gmdate('c');
            $this->buffer->put(self::NAMESPACE, $key, $current);
            return $current;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function entryKey(string $scope, string $idempotencyKey): string
    {
        return hash('sha256', $scope . "\0" . $idempotencyKey);
    }

    private function payloadHash(array $payload): string
    {
        try {
            return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            throw new RuntimeException('Offline replay payload must be JSON serializable.');
        }
    }

    private function validate(string $scope, string $operation, string $idempotencyKey): void
    {
        if ($scope === '' || strlen($scope) > 120 || preg_match('/^[a-z0-9_.:-]+$/i', $scope) !== 1 ||
            $operation === '' || strlen($operation) > 160 || preg_match('/^[a-z0-9_.:-]+$/i', $operation) !== 1 ||
            $idempotencyKey === '' || strlen($idempotencyKey) > 180) {
            throw new RuntimeException('Invalid offline replay envelope.');
        }
    }
}
