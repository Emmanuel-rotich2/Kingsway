<?php

namespace App\API\Services;

use App\API\Includes\AuditLogger;
use App\Database\Database;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Canonical lifecycle owner for authenticated staff sessions.
 *
 * auth_sessions stores only a SHA-256 hash of the access token. The optional
 * refresh-token relationship is kept as a numeric ID inside the existing JSON
 * payload column; no raw access or refresh token is persisted here.
 */
final class AuthSessionService
{
    private PDO $db;
    private int $idleTimeoutSeconds;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $configuredIdleTimeout = defined('AUTH_IDLE_TIMEOUT_SECONDS')
            ? (int) AUTH_IDLE_TIMEOUT_SECONDS
            : 1800;
        $this->idleTimeoutSeconds = max(300, $configuredIdleTimeout);
    }

    /**
     * Create a session or rotate the access-token hash for an existing refresh
     * session. The returned ID is safe to expose as a session identifier.
     */
    public function upsertAccessSession(
        int $userId,
        string $accessToken,
        ?int $refreshTokenId,
        string $expiresAt
    ): int {
        if ($userId <= 0) {
            throw new InvalidArgumentException('A valid user ID is required');
        }
        if ($accessToken === '') {
            throw new InvalidArgumentException('An access token is required');
        }
        if ($refreshTokenId !== null && $refreshTokenId <= 0) {
            throw new InvalidArgumentException(
                'Refresh token ID must be a positive integer'
            );
        }
        if (strtotime($expiresAt) === false) {
            throw new InvalidArgumentException(
                'Session expiry must be a valid date'
            );
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $sessionId = null;
            if ($refreshTokenId !== null) {
                $stmt = $this->db->prepare(
                    "SELECT id
                     FROM auth_sessions
                     WHERE user_id = ?
                       AND CAST(
                            JSON_UNQUOTE(
                                JSON_EXTRACT(payload, '$.refresh_token_id')
                            ) AS UNSIGNED
                       ) = ?
                     LIMIT 1
                     FOR UPDATE"
                );
                $stmt->execute([$userId, $refreshTokenId]);
                $sessionId = $stmt->fetchColumn();
            }

            $tokenHash = self::hashAccessToken($accessToken);
            $payload = json_encode(
                [
                    'refresh_token_id' => $refreshTokenId,
                    'token_storage' => 'sha256',
                ],
                JSON_UNESCAPED_SLASHES
            );
            if ($payload === false) {
                throw new RuntimeException(
                    'Session metadata could not be encoded'
                );
            }

            $ipAddress = $this->clientIpAddress();
            $userAgent = $this->clientUserAgent();

            if ($sessionId !== false && $sessionId !== null) {
                $stmt = $this->db->prepare(
                    'UPDATE auth_sessions
                     SET token = ?,
                         ip_address = ?,
                         user_agent = ?,
                         payload = ?,
                         last_activity = NOW(),
                         expires_at = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $tokenHash,
                    $ipAddress,
                    $userAgent,
                    $payload,
                    $expiresAt,
                    (int) $sessionId,
                ]);
                $resolvedSessionId = (int) $sessionId;
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO auth_sessions (
                        user_id,
                        token,
                        ip_address,
                        user_agent,
                        payload,
                        last_activity,
                        expires_at,
                        created_at
                     ) VALUES (?, ?, ?, ?, ?, NOW(), ?, NOW())'
                );
                $stmt->execute([
                    $userId,
                    $tokenHash,
                    $ipAddress,
                    $userAgent,
                    $payload,
                    $expiresAt,
                ]);
                $resolvedSessionId = (int) $this->db->lastInsertId();
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $resolvedSessionId;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Validate an access token against the canonical session registry.
     *
     * Activity writes are throttled to once per minute per session.
     */
    public function validateAccessToken(
        string $accessToken,
        int $userId
    ): ?array {
        if ($accessToken === '' || $userId <= 0) {
            return null;
        }

        $idleTimeoutSeconds = $this->idleTimeoutSeconds;
        $stmt = $this->db->prepare(
            "SELECT id, user_id, last_activity, expires_at
             FROM auth_sessions
             WHERE token = ?
               AND user_id = ?
               AND expires_at > NOW()
               AND last_activity >= DATE_SUB(
                    NOW(),
                    INTERVAL {$idleTimeoutSeconds} SECOND
               )
             LIMIT 1"
        );
        $stmt->execute([
            self::hashAccessToken($accessToken),
            $userId,
        ]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return null;
        }

        $stmt = $this->db->prepare(
            'UPDATE auth_sessions
             SET last_activity = NOW()
             WHERE id = ?
               AND last_activity < DATE_SUB(NOW(), INTERVAL 1 MINUTE)'
        );
        $stmt->execute([(int) $session['id']]);

        return [
            'id' => (int) $session['id'],
            'user_id' => (int) $session['user_id'],
            'last_activity' => $session['last_activity'],
            'expires_at' => $session['expires_at'],
        ];
    }

    /**
     * Confirm that a refresh token still belongs to a non-idle canonical
     * browser session. Refresh-token lifetime alone must not bypass the
     * 30-minute inactivity policy.
     */
    public function validateRefreshSession(
        int $userId,
        int $refreshTokenId
    ): ?array {
        if ($userId <= 0 || $refreshTokenId <= 0) {
            return null;
        }

        $idleTimeoutSeconds = $this->idleTimeoutSeconds;
        $stmt = $this->db->prepare(
            "SELECT id, user_id, last_activity, expires_at
             FROM auth_sessions
             WHERE user_id = ?
               AND CAST(
                    JSON_UNQUOTE(
                        JSON_EXTRACT(payload, '$.refresh_token_id')
                    ) AS UNSIGNED
               ) = ?
               AND last_activity >= DATE_SUB(
                    NOW(),
                    INTERVAL {$idleTimeoutSeconds} SECOND
               )
             LIMIT 1"
        );
        $stmt->execute([$userId, $refreshTokenId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return null;
        }

        return [
            'id' => (int) $session['id'],
            'user_id' => (int) $session['user_id'],
            'last_activity' => $session['last_activity'],
            'expires_at' => $session['expires_at'],
        ];
    }

    /**
     * Revoke a refresh-backed session during normal logout.
     */
    public function revokeByRefreshToken(string $refreshToken): bool
    {
        if ($refreshToken === '') {
            return false;
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT id
                 FROM refresh_tokens
                 WHERE token = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$refreshToken]);
            $refreshTokenId = $stmt->fetchColumn();

            if ($refreshTokenId === false) {
                if ($ownsTransaction) {
                    $this->db->commit();
                }
                return false;
            }

            $stmt = $this->db->prepare(
                'UPDATE refresh_tokens
                 SET revoked_at = COALESCE(revoked_at, NOW())
                 WHERE id = ?'
            );
            $stmt->execute([(int) $refreshTokenId]);

            $stmt = $this->db->prepare(
                "DELETE FROM auth_sessions
                 WHERE CAST(
                    JSON_UNQUOTE(
                        JSON_EXTRACT(payload, '$.refresh_token_id')
                    ) AS UNSIGNED
                 ) = ?"
            );
            $stmt->execute([(int) $refreshTokenId]);

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return true;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Revoke another active session and record the administrator action in the
     * same transaction.
     */
    public function revokeByAdministrator(
        int $sessionId,
        int $actorUserId,
        ?int $currentSessionId
    ): array {
        if ($sessionId <= 0) {
            throw new InvalidArgumentException(
                'A valid session ID is required'
            );
        }
        if ($actorUserId <= 0) {
            throw new InvalidArgumentException(
                'An authenticated administrator is required'
            );
        }
        if (
            $currentSessionId !== null &&
            $currentSessionId > 0 &&
            $sessionId === $currentSessionId
        ) {
            throw new DomainException(
                'The current session cannot be revoked from this page'
            );
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT
                    s.id,
                    s.user_id,
                    s.ip_address,
                    s.user_agent,
                    s.last_activity,
                    s.expires_at,
                    s.payload,
                    u.username,
                    u.email
                 FROM auth_sessions s
                 INNER JOIN users u ON u.id = s.user_id
                 WHERE s.id = ?
                   AND s.expires_at > NOW()
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                throw new OutOfBoundsException(
                    'Active session not found'
                );
            }

            $refreshTokenId = $this->readRefreshTokenId(
                $session['payload'] ?? null
            );
            $refreshTokenRevoked = false;
            if ($refreshTokenId !== null) {
                $stmt = $this->db->prepare(
                    'UPDATE refresh_tokens
                     SET revoked_at = COALESCE(revoked_at, NOW())
                     WHERE id = ?'
                );
                $stmt->execute([$refreshTokenId]);
                $refreshTokenRevoked = $stmt->rowCount() > 0;
            }

            $stmt = $this->db->prepare(
                'DELETE FROM auth_sessions WHERE id = ?'
            );
            $stmt->execute([$sessionId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'The active session could not be revoked'
                );
            }

            $auditLogged = (new AuditLogger($this->db))->log(
                'session_revoke',
                'auth_session',
                $sessionId,
                $actorUserId,
                [
                    'target_user_id' => (int) $session['user_id'],
                    'target_username' => $session['username'],
                    'target_ip_address' => $session['ip_address'],
                    'target_user_agent' => $session['user_agent'],
                    'last_activity' => $session['last_activity'],
                    'expires_at' => $session['expires_at'],
                    'refresh_token_revoked' => $refreshTokenRevoked,
                ]
            );
            if (!$auditLogged) {
                throw new RuntimeException(
                    'Session revocation audit could not be recorded'
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return [
                'id' => $sessionId,
                'user_id' => (int) $session['user_id'],
                'username' => $session['username'],
                'revoked' => true,
                'refresh_token_revoked' => $refreshTokenRevoked,
            ];
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /**
     * Return the secret-free refresh/API-token registry for System
     * Administrator review.
     */
    public function getTokenRegistry(
        array $filters = [],
        ?int $currentSessionId = null
    ): array {
        $search = trim((string) ($filters['search'] ?? ''));
        if (strlen($search) > 200) {
            throw new InvalidArgumentException(
                'Search must not exceed 200 characters'
            );
        }

        $tokenType = trim((string) ($filters['token_type'] ?? ''));
        if (!in_array($tokenType, ['', 'refresh', 'api'], true)) {
            throw new InvalidArgumentException(
                'Token type must be refresh or api'
            );
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (!in_array($status, ['', 'active', 'expired', 'revoked'], true)) {
            throw new InvalidArgumentException(
                'Status must be active, expired or revoked'
            );
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = (int) ($filters['limit'] ?? 50);
        if (!in_array($limit, [25, 50, 100], true)) {
            $limit = 50;
        }

        $currentSessionId = max(0, (int) ($currentSessionId ?? 0));
        $currentRefreshTokenId = 0;
        if ($currentSessionId > 0) {
            $stmt = $this->db->prepare(
                'SELECT payload
                 FROM auth_sessions
                 WHERE id = ?
                 LIMIT 1'
            );
            $stmt->execute([$currentSessionId]);
            $currentRefreshTokenId = (int) (
                $this->readRefreshTokenId($stmt->fetchColumn()) ?? 0
            );
        }

        // All interpolated values below are strictly normalized integers.
        $idleTimeoutSeconds = $this->idleTimeoutSeconds;
        $baseSql = "
            SELECT
                CONCAT('refresh:', rt.id) AS registry_key,
                rt.id,
                'refresh' AS token_type,
                rt.user_id,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                NULL AS token_name,
                NULL AS scope,
                rt.created_at,
                sessions.last_activity AS last_used_at,
                rt.expires_at,
                rt.revoked_at,
                CASE
                    WHEN rt.revoked_at IS NOT NULL THEN 'revoked'
                    WHEN rt.expires_at <= NOW() THEN 'expired'
                    ELSE 'active'
                END AS status,
                CASE
                    WHEN rt.id = $currentRefreshTokenId THEN 1
                    ELSE 0
                END AS is_current,
                CASE
                    WHEN sessions.active_session_count > 0 THEN 1
                    ELSE 0
                END AS has_active_session
            FROM refresh_tokens rt
            LEFT JOIN users u ON u.id = rt.user_id
            LEFT JOIN (
                SELECT
                    CAST(
                        JSON_UNQUOTE(
                            JSON_EXTRACT(payload, '$.refresh_token_id')
                        ) AS UNSIGNED
                    ) AS refresh_token_id,
                    MAX(last_activity) AS last_activity,
                    SUM(
                        CASE
                            WHEN expires_at > NOW()
                             AND last_activity >= DATE_SUB(
                                NOW(),
                                INTERVAL {$idleTimeoutSeconds} SECOND
                             )
                            THEN 1
                            ELSE 0
                        END
                    ) AS active_session_count
                FROM auth_sessions
                WHERE JSON_EXTRACT(
                    payload,
                    '$.refresh_token_id'
                ) IS NOT NULL
                GROUP BY refresh_token_id
            ) sessions ON sessions.refresh_token_id = rt.id

            UNION ALL

            SELECT
                CONCAT('api:', at.id) AS registry_key,
                at.id,
                'api' AS token_type,
                at.user_id,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                at.token_name,
                at.scope,
                at.created_date AS created_at,
                at.last_used_date AS last_used_at,
                at.expiry_date AS expires_at,
                audit_revoke.revoked_at,
                CASE
                    WHEN at.is_active = 0 THEN 'revoked'
                    WHEN at.expiry_date IS NOT NULL
                         AND at.expiry_date <= NOW() THEN 'expired'
                    ELSE 'active'
                END AS status,
                0 AS is_current,
                0 AS has_active_session
            FROM api_tokens at
            INNER JOIN users u ON u.id = at.user_id
            LEFT JOIN (
                SELECT entity_id, MAX(created_at) AS revoked_at
                FROM audit_logs
                WHERE action = 'token_revoke'
                  AND entity = 'api_token'
                  AND status = 'success'
                GROUP BY entity_id
            ) audit_revoke ON audit_revoke.entity_id = at.id
        ";

        $where = ['1 = 1'];
        $params = [];
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(
                token_registry.username LIKE ?
                OR token_registry.email LIKE ?
                OR token_registry.first_name LIKE ?
                OR token_registry.last_name LIKE ?
                OR token_registry.token_name LIKE ?
                OR CAST(token_registry.id AS CHAR) LIKE ?
            )';
            array_push(
                $params,
                $term,
                $term,
                $term,
                $term,
                $term,
                $term
            );
        }
        if ($tokenType !== '') {
            $where[] = 'token_registry.token_type = ?';
            $params[] = $tokenType;
        }
        if ($status !== '') {
            $where[] = 'token_registry.status = ?';
            $params[] = $status;
        }

        $whereSql = implode(' AND ', $where);
        $fromSql = "FROM ($baseSql) token_registry";

        $summaryStmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END),
                    0
                ) AS active,
                COALESCE(
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END),
                    0
                ) AS expired,
                COALESCE(
                    SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END),
                    0
                ) AS revoked,
                COALESCE(
                    SUM(CASE WHEN token_type = 'refresh' THEN 1 ELSE 0 END),
                    0
                ) AS refresh_tokens,
                COALESCE(
                    SUM(CASE WHEN token_type = 'api' THEN 1 ELSE 0 END),
                    0
                ) AS api_tokens
             $fromSql
             WHERE $whereSql"
        );
        $summaryStmt->execute($params);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total = (int) ($summary['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        $rowsStmt = $this->db->prepare(
            "SELECT token_registry.*
             $fromSql
             WHERE $whereSql
             ORDER BY
                token_registry.is_current DESC,
                token_registry.created_at DESC,
                token_registry.token_type ASC,
                token_registry.id DESC
             LIMIT $limit OFFSET $offset"
        );
        $rowsStmt->execute($params);

        $registryCountStmt = $this->db->query(
            'SELECT
                (SELECT COUNT(*) FROM refresh_tokens)
                + (SELECT COUNT(*) FROM api_tokens)'
        );
        $registryCount = (int) $registryCountStmt->fetchColumn();

        return [
            'tokens' => $rowsStmt->fetchAll(PDO::FETCH_ASSOC),
            'summary' => [
                'total' => $registryCount > 0 ? $total : null,
                'active' => (int) ($summary['active'] ?? 0),
                'expired' => (int) ($summary['expired'] ?? 0),
                'revoked' => (int) ($summary['revoked'] ?? 0),
                'refresh_tokens' => (int) (
                    $summary['refresh_tokens'] ?? 0
                ),
                'api_tokens' => (int) ($summary['api_tokens'] ?? 0),
                'tracking_available' => $registryCount > 0,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'search' => $search,
                'token_type' => $tokenType,
                'status' => $status,
            ],
            'available_filters' => [
                'token_types' => ['refresh', 'api'],
                'statuses' => ['active', 'expired', 'revoked'],
            ],
            'generated_at' => date('c'),
        ];
    }

    /**
     * Revoke a refresh or API token and write its audit record within the same
     * transaction. Token values and hashes never enter the audit payload.
     */
    public function revokeTokenByAdministrator(
        int $tokenId,
        string $tokenType,
        int $actorUserId,
        ?int $currentSessionId
    ): array {
        if ($tokenId <= 0) {
            throw new InvalidArgumentException(
                'A valid token ID is required'
            );
        }
        if ($actorUserId <= 0) {
            throw new InvalidArgumentException(
                'An authenticated administrator is required'
            );
        }

        $tokenType = $this->normalizeTokenType($tokenType);
        $currentSessionId = max(0, (int) ($currentSessionId ?? 0));

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            if ($tokenType === 'refresh') {
                $result = $this->revokeRefreshTokenByAdministrator(
                    $tokenId,
                    $actorUserId,
                    $currentSessionId
                );
            } else {
                $result = $this->revokeApiTokenByAdministrator(
                    $tokenId,
                    $actorUserId
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $result;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function revokeRefreshTokenByAdministrator(
        int $tokenId,
        int $actorUserId,
        int $currentSessionId
    ): array {
        $stmt = $this->db->prepare(
            'SELECT
                rt.id,
                rt.user_id,
                rt.created_at,
                rt.expires_at,
                rt.revoked_at,
                CASE WHEN rt.expires_at <= NOW() THEN 1 ELSE 0 END
                    AS is_expired,
                u.username,
                u.email
             FROM refresh_tokens rt
             LEFT JOIN users u ON u.id = rt.user_id
             WHERE rt.id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$tokenId]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$token) {
            throw new OutOfBoundsException('Refresh token not found');
        }

        if ($currentSessionId > 0) {
            $stmt = $this->db->prepare(
                'SELECT payload
                 FROM auth_sessions
                 WHERE id = ?
                   AND user_id = ?
                 LIMIT 1'
            );
            $stmt->execute([$currentSessionId, $actorUserId]);
            $currentRefreshTokenId = $this->readRefreshTokenId(
                $stmt->fetchColumn()
            );
            if ($currentRefreshTokenId === $tokenId) {
                throw new DomainException(
                    'The current refresh token cannot be revoked from this page'
                );
            }
        }

        if (!empty($token['revoked_at'])) {
            throw new DomainException(
                'The refresh token has already been revoked'
            );
        }
        if ((int) ($token['is_expired'] ?? 0) === 1) {
            throw new DomainException(
                'The refresh token has already expired'
            );
        }

        $stmt = $this->db->prepare(
            'UPDATE refresh_tokens
             SET revoked_at = NOW()
             WHERE id = ?
               AND revoked_at IS NULL
               AND expires_at > NOW()'
        );
        $stmt->execute([$tokenId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'The refresh token could not be revoked'
            );
        }

        $stmt = $this->db->prepare(
            "DELETE FROM auth_sessions
             WHERE CAST(
                JSON_UNQUOTE(
                    JSON_EXTRACT(payload, '$.refresh_token_id')
                ) AS UNSIGNED
             ) = ?"
        );
        $stmt->execute([$tokenId]);
        $linkedSessionsRevoked = $stmt->rowCount();

        $auditLogged = (new AuditLogger($this->db))->log(
            'token_revoke',
            'refresh_token',
            $tokenId,
            $actorUserId,
            [
                'token_type' => 'refresh',
                'target_user_id' => (int) $token['user_id'],
                'target_username' => $token['username'],
                'target_email' => $token['email'],
                'created_at' => $token['created_at'],
                'expires_at' => $token['expires_at'],
                'linked_sessions_revoked' => $linkedSessionsRevoked,
            ]
        );
        if (!$auditLogged) {
            throw new RuntimeException(
                'Refresh-token revocation audit could not be recorded'
            );
        }

        return [
            'id' => $tokenId,
            'token_type' => 'refresh',
            'user_id' => (int) $token['user_id'],
            'revoked' => true,
            'linked_sessions_revoked' => $linkedSessionsRevoked,
        ];
    }

    private function revokeApiTokenByAdministrator(
        int $tokenId,
        int $actorUserId
    ): array {
        $stmt = $this->db->prepare(
            'SELECT
                at.id,
                at.user_id,
                at.token_name,
                at.scope,
                at.created_date AS created_at,
                at.expiry_date,
                at.is_active,
                CASE
                    WHEN at.expiry_date IS NOT NULL
                         AND at.expiry_date <= NOW() THEN 1
                    ELSE 0
                END AS is_expired,
                u.username,
                u.email
             FROM api_tokens at
             INNER JOIN users u ON u.id = at.user_id
             WHERE at.id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$tokenId]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$token) {
            throw new OutOfBoundsException('API token not found');
        }

        if ((int) ($token['is_active'] ?? 0) !== 1) {
            throw new DomainException(
                'The API token has already been revoked'
            );
        }
        if ((int) ($token['is_expired'] ?? 0) === 1) {
            throw new DomainException(
                'The API token has already expired'
            );
        }

        $stmt = $this->db->prepare(
            'UPDATE api_tokens
             SET is_active = 0
             WHERE id = ?
               AND is_active = 1
               AND (
                    expiry_date IS NULL
                    OR expiry_date > NOW()
               )'
        );
        $stmt->execute([$tokenId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'The API token could not be revoked'
            );
        }

        $scope = $token['scope'] ?? null;
        if (is_string($scope) && trim($scope) !== '') {
            $decoded = json_decode($scope, true);
            $scope = json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : null;
        }

        $auditLogged = (new AuditLogger($this->db))->log(
            'token_revoke',
            'api_token',
            $tokenId,
            $actorUserId,
            [
                'token_type' => 'api',
                'token_name' => $token['token_name'],
                'scope' => $scope,
                'target_user_id' => (int) $token['user_id'],
                'target_username' => $token['username'],
                'target_email' => $token['email'],
                'created_at' => $token['created_at'],
                'expires_at' => $token['expiry_date'],
            ]
        );
        if (!$auditLogged) {
            throw new RuntimeException(
                'API-token revocation audit could not be recorded'
            );
        }

        return [
            'id' => $tokenId,
            'token_type' => 'api',
            'user_id' => (int) $token['user_id'],
            'revoked' => true,
        ];
    }

    private function normalizeTokenType(string $tokenType): string
    {
        $normalized = strtolower(trim($tokenType));
        $aliases = [
            'refresh' => 'refresh',
            'refresh_token' => 'refresh',
            'refresh_tokens' => 'refresh',
            'api' => 'api',
            'api_token' => 'api',
            'api_tokens' => 'api',
        ];

        if (!isset($aliases[$normalized])) {
            throw new InvalidArgumentException(
                'Token type must be refresh or api'
            );
        }

        return $aliases[$normalized];
    }

    public static function hashAccessToken(string $accessToken): string
    {
        return hash('sha256', $accessToken);
    }

    private function readRefreshTokenId($payload): ?int
    {
        if (!is_string($payload) || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        $refreshTokenId = (int) ($decoded['refresh_token_id'] ?? 0);

        return $refreshTokenId > 0 ? $refreshTokenId : null;
    }

    private function clientIpAddress(): ?string
    {
        $ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        return $ipAddress === ''
            ? null
            : substr($ipAddress, 0, 45);
    }

    private function clientUserAgent(): ?string
    {
        $userAgent = trim(
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );

        return $userAgent === ''
            ? null
            : substr($userAgent, 0, 255);
    }
}
