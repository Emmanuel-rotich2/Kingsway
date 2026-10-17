<?php
/**
 * NodeJobClient - Legacy compatibility helpers for realtime capability tokens.
 * Uses InterServiceClient for internal communication (loopback + Host header).
 */

namespace App\API\Services;

use App\Config\Config;

class NodeJobClient
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    /** Retained for compatibility with historic idempotency identifiers. */
    private function generateIdempotencyKey(string $job, array $payload, int $operatorId): string
    {
        $sortedPayload = $this->sortArrayKeysRecursive($payload);
        $data = $job . '|' . json_encode($sortedPayload) . '|' . $operatorId;
        return 'nodejob_' . hash('sha256', $data);
    }

    /** Retained for compatibility with historic idempotency identifiers. */
    private function sortArrayKeysRecursive(array $array): array
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sortArrayKeysRecursive($value);
            }
        }
        return $array;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate capability token for browser SSE connection
     * Called by RealtimeController::getStreamToken()
     */
    public function generateCapabilityToken(int $userId, array $channels, array $permissions = []): string
    {
        $payload = [
            'iss' => Config::get('JWT_ISSUER', defined('JWT_ISSUER') ? JWT_ISSUER : 'kingsway-prep-school'),
            'aud' => Config::get('JWT_AUDIENCE', defined('JWT_AUDIENCE') ? JWT_AUDIENCE : 'kingsway-staff'),
            'sub' => (string) $userId,
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + (Config::get('NODE_JWT_CAPABILITY_TTL', 240)),
            'userId' => $userId,
            'realtime' => true,
            'channels' => $channels,
            'permissions' => $permissions
        ];

        return \Firebase\JWT\JWT::encode($payload, defined('JWT_SECRET') ? JWT_SECRET : Config::get('jwt.secret'), 'HS256');
    }

    /**
     * Get allowed channels for a user based on their roles/permissions
     */
    public function getAllowedChannels(int $userId, array $roles = [], array $permissions = []): array
    {
        $channels = [];

        // Base user channel
        $channels[] = "users:{$userId}";

        // Role-based channels
        foreach ($roles as $role) {
            $roleName = is_array($role) ? ($role['name'] ?? '') : $role;
            if ($roleName) {
                $channels[] = "role:{$roleName}";
            }
        }

        // Permission-based channels
        if (in_array('analytics_catalogue_view', $permissions)) {
            $channels[] = 'analytics';
        }
        if (in_array('finance_view', $permissions)) {
            $channels[] = 'finance';
        }
        if (in_array('staff_view', $permissions)) {
            $channels[] = 'staff';
        }

        // System admin gets all
        if (in_array('system_admin', $permissions) || in_array('*', $permissions)) {
            $channels[] = 'all';
        }

        return array_unique($channels);
    }

}
