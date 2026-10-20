<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Config\Config;
use RuntimeException;

/** Internal bridge for allowlisted, deterministic Python read-model refreshes. */
final class ReadProjectionBridge
{
    private const ENDPOINT = '/api/read-models/refresh';

    private InterServiceClient $client;

    public function __construct(?InterServiceClient $client = null)
    {
        $this->client = $client ?? new InterServiceClient();
    }

    public function enabled(): bool
    {
        return filter_var(Config::get('KINGSWAY_PYTHON_READ_MODELS_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
            && $this->client->isConfigured('python_ai')
            && trim((string) Config::get('AI_PYTHON_SECRET', (string) Config::get('AI_API_KEY', ''))) !== '';
    }

    /** @return array<string,mixed> */
    public function refresh(string $projection, int $timeoutSeconds = 900): array
    {
        if (!ReadProjectionSynchronizer::supports($projection)) {
            throw new RuntimeException('Read projection is not allowlisted.');
        }
        if (!$this->enabled()) {
            return $this->refreshWithPhp($projection);
        }

        try {
            return $this->refreshWithPython($projection, $timeoutSeconds);
        } catch (RuntimeException $error) {
            if ($error->getCode() === 409) {
                throw $error;
            }
            try {
                return $this->refreshWithPhp($projection);
            } catch (RuntimeException $fallbackError) {
                if ($fallbackError->getCode() === 409) {
                    throw $fallbackError;
                }
                throw $error;
            }
        }
    }

    /** @return array<string,mixed> */
    private function refreshWithPython(string $projection, int $timeoutSeconds): array
    {
        $body = json_encode(['projection' => $projection], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $secret = (string) Config::get('AI_PYTHON_SECRET', (string) Config::get('AI_API_KEY', ''));
        [$raw, $status] = $this->client->post('python_ai', self::ENDPOINT, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $secret,
        ], $body, max(1, min(900, $timeoutSeconds)));

        if ($status === 503) {
            $failure = json_decode((string) $raw, true);
            if (is_array($failure)
                && str_contains(strtolower((string) ($failure['message'] ?? '')), 'already being refreshed')) {
                throw new RuntimeException('Read projection refresh is already in progress.', 409);
            }
        }

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Python read-model refresh failed.');
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !($decoded['success'] ?? false) || !is_array($decoded['data'] ?? null)) {
            throw new RuntimeException('Python read-model refresh returned an invalid response.');
        }
        $data = $decoded['data'];
        if (($data['projection'] ?? null) !== $projection
            || ($data['target'] ?? null) !== ReadProjectionSynchronizer::targetTable($projection)
            || ($data['status'] ?? null) !== 'published') {
            throw new RuntimeException('Python read-model refresh returned an unexpected result.');
        }
        return $data + ['engine' => 'python'];
    }

    /**
     * PHP engine fallback: the synchronizer rebuilds the projection under its
     * own advisory lock. Used when the Python platform is not configured or
     * is unreachable, so an ordinary read can still repair a stale projection
     * instead of failing the request.
     *
     * @return array<string,mixed>
     */
    private function refreshWithPhp(string $projection): array
    {
        try {
            $result = ReadProjectionSynchronizer::synchronize($projection);
        } catch (RuntimeException $error) {
            if (str_contains($error->getMessage(), 'already being refreshed')) {
                throw new RuntimeException($error->getMessage(), 409, $error);
            }
            throw $error;
        }

        return $result + ['engine' => 'php_fallback'];
    }

    /**
     * Refresh a stale projection before an ordinary read. Concurrent callers
     * wait briefly for the single in-flight refresh holding its lock. The
     * short timeout keeps an ordinary page request bounded.
     *
     * @return array<string,mixed>
     */
    public function refreshForRead(string $projection): array
    {
        $attempts = 10;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->refresh($projection, 12);
            } catch (RuntimeException $error) {
                if ($error->getCode() !== 409 || $attempt === $attempts) {
                    throw $error;
                }
                usleep(350000);
            }
        }

        throw new RuntimeException('Read projection refresh did not complete.');
    }
}
