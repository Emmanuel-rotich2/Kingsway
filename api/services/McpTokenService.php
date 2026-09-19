<?php

namespace App\API\Services;

use PDO;

/**
 * Authenticates machine clients for the MCP endpoint.
 *
 * Tokens are opaque and are stored only as SHA-256 hashes. The raw token is
 * returned once by the local provisioning helper and is never logged.
 */
class McpTokenService
{
    public static function authenticate(PDO $pdo, string $token, string $ip): ?array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 240) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, client_id, token_hash, scopes, ip_address, expires_at
             FROM mcp_client_tokens
             WHERE is_active = 1 AND token_hash = ? AND expires_at > UTC_TIMESTAMP()
             LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !hash_equals((string) $row['token_hash'], hash('sha256', $token))) {
            return null;
        }

        $boundIp = trim((string) ($row['ip_address'] ?? ''));
        if ($boundIp !== '' && !hash_equals($boundIp, $ip)) {
            return null;
        }

        $scopes = json_decode((string) ($row['scopes'] ?? '[]'), true);
        if (!is_array($scopes)) {
            $scopes = array_filter(array_map('trim', explode(',', (string) $row['scopes'])));
        }

        $pdo->prepare('UPDATE mcp_client_tokens SET last_used_at = UTC_TIMESTAMP() WHERE id = ?')
            ->execute([(int) $row['id']]);

        return [
            'id' => (int) $row['id'],
            'client_id' => (string) $row['client_id'],
            'scopes' => array_values(array_unique(array_map('strval', $scopes))),
        ];
    }

    public static function hasScope(array $client, string $scope): bool
    {
        $scopes = $client['scopes'] ?? [];
        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }

    /** Provisioning helper for a trusted local operator; the raw token is returned once. */
    public static function issue(PDO $pdo, string $clientId, array $scopes, string $expiresAt, ?string $ip = null): string
    {
        $raw = 'mcp_' . bin2hex(random_bytes(32));
        $stmt = $pdo->prepare(
            'INSERT INTO mcp_client_tokens
                (client_id, token_hash, scopes, ip_address, expires_at, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $clientId,
            hash('sha256', $raw),
            json_encode(array_values(array_unique(array_map('strval', $scopes))), JSON_THROW_ON_ERROR),
            $ip,
            $expiresAt,
        ]);
        return $raw;
    }
}
