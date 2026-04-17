<?php
declare(strict_types=1);

namespace App\API\Middleware;

use App\Database\Database;

class ParentAuthMiddleware
{
    /**
     * Validate Bearer token against parent_portal_sessions.
     * Sets $_SERVER['parent_auth'] = ['parent_id' => N, 'session_id' => N, 'session_token' => '...']
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
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT id, parent_id, expires_at FROM parent_portal_sessions
                 WHERE session_token = :token AND status = 'active' AND expires_at > NOW()
                 LIMIT 1"
            );
            $stmt->execute([':token' => $token]);
            $session = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$session) {
                self::unauthorized('Invalid or expired session token');
                return;
            }

            // Update last_login on parents table
            $db->prepare("UPDATE parents SET portal_last_login = NOW() WHERE id = :id")
               ->execute([':id' => $session['parent_id']]);

            $_SERVER['parent_auth'] = [
                'parent_id'     => (int)$session['parent_id'],
                'session_id'    => (int)$session['id'],
                'session_token' => $token,
            ];
        } catch (\Exception $e) {
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
