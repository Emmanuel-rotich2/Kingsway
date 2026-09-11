<?php
declare(strict_types=1);

namespace App\API\Middleware;

use App\Database\Database;

class ParentAuthMiddleware
{
    private const SESSION_TTL_DAYS = 7;

    /**
     * Validate Bearer token against user_sessions (normalised parent sessions).
     * The legacy parent_portal_sessions table does not exist; sessions live in
     * `user_sessions` and the account/person in `users`/`persons`, with the
     * parent subtype resolved through `parents.person_id`.
     *
     * Sets $_SERVER['parent_auth'] = [
     *   'parent_id' => N, 'user_id' => N, 'session_id' => N, 'session_token' => '...'
     * ]
     * On failure: returns 401 JSON and exits.
     */
    public static function handle(): void
    {
        // Extract bearer token from Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        $token = null;
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $token = trim($m[1]);
        }

        if (!$token) {
            self::unauthorized('No authentication token provided');
            return;
        }

        try {
            $db      = Database::getInstance();
            $session = $db->query(
                "SELECT us.id AS session_id, us.user_id, us.session_token,
                        pr.id AS parent_id, u.status AS user_status
                 FROM user_sessions us
                 JOIN users u ON u.id = us.user_id
                 JOIN persons p ON p.id = u.person_id
                 JOIN parents pr ON pr.person_id = u.person_id
                 WHERE us.session_token = :token
                   AND us.session_status = 'active'
                   AND us.logout_time IS NULL
                   AND u.data_scope = p.data_scope
                   AND pr.status = 'active'
                   AND EXISTS (
                       SELECT 1
                       FROM user_roles ur
                       JOIN roles r ON r.id = ur.role_id
                       WHERE ur.user_id = u.id
                         AND r.id = 73
                         AND r.name = 'Parent'
                   )
                   AND us.login_time > DATE_SUB(NOW(), INTERVAL " . self::SESSION_TTL_DAYS . " DAY)
                 LIMIT 1",
                [':token' => $token]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$session || ($session['user_status'] ?? '') !== 'active') {
                self::unauthorized('Invalid or expired session token');
                return;
            }

            $_SERVER['parent_auth'] = [
                'parent_id'     => (int)$session['parent_id'],
                'user_id'       => (int)$session['user_id'],
                'session_id'    => (int)$session['session_id'],
                'session_token' => $token,
            ];
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentAuthMiddleware] ' . $e->getMessage());
            self::unauthorized('Authentication service unavailable');
        }
    }

    private static function unauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => $message, 'code' => 401]);
        exit;
    }
}
