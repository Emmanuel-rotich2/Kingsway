<?php

namespace App\API\Services;

use PDO;

/**
 * Replays completed mutating API responses for a repeated Idempotency-Key.
 */
final class RequestIdempotencyService
{
    private const TTL_SECONDS = 86400;
    private const LEASE_SECONDS = 120;

    public static function keyFromRequest(): ?string
    {
        $key = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        return $key !== '' && preg_match('/^[A-Za-z0-9._:-]{8,160}$/', $key) ? $key : null;
    }

    public static function requestHash(string $key): string
    {
        $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $identity = $authorization !== '' ? hash('sha256', $authorization) : 'anonymous';
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        return hash('sha256', implode("\n", [$identity, $method, $path, $key]));
    }

    public static function payloadHash(string $body): string
    {
        return hash('sha256', $body);
    }

    public static function reserve(PDO $db, string $requestHash, string $payloadHash): array
    {
        $ownerToken = bin2hex(random_bytes(16));
        $stmt = $db->prepare(
            'UPDATE api_idempotency_keys
             SET payload_hash = ?, owner_token = ?, status = \'processing\',
                 response_json = NULL, status_code = 0,
                 lease_until = DATE_ADD(NOW(), INTERVAL ' . self::LEASE_SECONDS . ' SECOND),
                 expires_at = DATE_ADD(NOW(), INTERVAL ' . self::TTL_SECONDS . ' SECOND)
             WHERE request_hash = ?
               AND (expires_at <= NOW() OR lease_until < NOW())'
        );
        $stmt->execute([$payloadHash, $ownerToken, $requestHash]);

        $stmt = $db->prepare(
            'INSERT IGNORE INTO api_idempotency_keys
                (request_hash, payload_hash, owner_token, status, response_json,
                 status_code, lease_until, expires_at)
             VALUES (?, ?, ?, \'processing\', NULL, 0,
                     DATE_ADD(NOW(), INTERVAL ' . self::LEASE_SECONDS . ' SECOND),
                     DATE_ADD(NOW(), INTERVAL ' . self::TTL_SECONDS . ' SECOND))'
        );
        $stmt->execute([$requestHash, $payloadHash, $ownerToken]);

        $stmt = $db->prepare(
            'SELECT payload_hash, owner_token, status, response_json, status_code, lease_until
             FROM api_idempotency_keys
             WHERE request_hash = ? AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$requestHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['type' => 'owner', 'owner_token' => $ownerToken];
        }
        if (!hash_equals((string) $row['payload_hash'], $payloadHash)) {
            throw new \DomainException('Idempotency-Key was already used with a different request payload.');
        }

        if (hash_equals((string) $row['owner_token'], $ownerToken)) {
            return ['type' => 'owner', 'owner_token' => $ownerToken];
        }
        if ($row['status'] === 'completed' && is_string($row['response_json'])) {
            $response = json_decode($row['response_json'], true);
            if (is_array($response)) {
                return [
                    'type' => 'replay',
                    'response' => $response,
                    'status_code' => (int) $row['status_code'],
                ];
            }
        }

        return ['type' => 'in_progress'];
    }

    public static function complete(
        PDO $db,
        string $requestHash,
        string $payloadHash,
        string $ownerToken,
        array $response,
        int $statusCode
    ): void
    {
        $encoded = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $stmt = $db->prepare(
            'UPDATE api_idempotency_keys
             SET status = \'completed\', response_json = ?, status_code = ?,
                 lease_until = NULL, expires_at = DATE_ADD(NOW(), INTERVAL ' . self::TTL_SECONDS . ' SECOND)
             WHERE request_hash = ? AND payload_hash = ? AND owner_token = ? AND status = \'processing\''
        );
        $stmt->execute([$encoded, $statusCode, $requestHash, $payloadHash, $ownerToken]);
    }
}
