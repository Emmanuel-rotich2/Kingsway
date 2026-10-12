<?php
/**
 * NodeJobClient - PHP service to enqueue jobs on Node.js gateway
 * Uses InterServiceClient for internal communication (loopback + Host header)
 */

namespace App\API\Services;

use App\API\Services\InterServiceClient;
use App\Config\Config;

class NodeJobClient
{
    private static ?self $instance = null;
    private string $gatewayUrl;
    private string $workerSecret;

    private function __construct()
    {
        // Internal gateway address. Prefer the dedicated internal base URL and
        // fall back to the shared NODE_REALTIME_URL (the same gateway serves both
        // the browser stream base and PHP's internal calls in the default setup).
        $this->gatewayUrl = rtrim((string) (
            Config::get('NODE_REALTIME_INTERNAL_BASE_URL', '')
            ?: Config::get('NODE_REALTIME_URL', 'http://127.0.0.1')
        ), '/');
        // Single shared boundary secret with the Node.js gateway. The legacy
        // NODE_REALTIME_PUBLISH_SECRET remains a deployment fallback.
        $this->workerSecret = (string) Config::get('KINGSWAY_WORKER_SECRET', '');
        if ($this->workerSecret === '') {
            $this->workerSecret = (string) Config::get('NODE_REALTIME_PUBLISH_SECRET', '');
        }
    }

    /**
     * Headers every internal call to the Node.js gateway must carry. The
     * gateway rejects any /internal/* request without the shared worker secret.
     */
    private function gatewayHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->workerSecret !== '') {
            $headers['X-Kingsway-Worker-Secret'] = $this->workerSecret;
        }
        return $headers;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Enqueue a job on the Node.js gateway (browser-facing)
     * This goes through the PHP controller which then pushes to KingsWayBuffers.jobs_queue
     *
     * @param string $job Job name (e.g., 'student_id_cards:load', 'report.aggregate')
     * @param array $payload Job payload data
     * @param string $priority 'critical' | 'normal' | 'batch'
     * @param array $options Additional options
     * @return array ['jobId' => string, 'status' => string, 'deduplicated' => bool]
     */
    public function enqueue(string $job, array $payload, string $priority = 'normal', array $options = []): array
    {
        $operatorId = $options['operator_id'] ?? 0;
        $idempotencyKey = $options['idempotency_key'] ?? $this->generateIdempotencyKey($job, $payload, $operatorId);

        $isc = new InterServiceClient();
        $response = $isc->post('realtime_node', '/internal/queue/enqueue',
            $this->gatewayHeaders(),
            json_encode([
            'job_type' => $job,
            'payload' => $payload,
            'priority' => $priority,
            'operator_id' => $operatorId,
            'idempotency_key' => $idempotencyKey
        ]), 30);

        $body = $response[0] ?? '';
        $status = $response[1] ?? 0;

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Node.js gateway error ($status): $body");
        }

        $data = json_decode($body, true) ?? [];

        return [
            'jobId' => $data['job_id'] ?? $data['jobId'] ?? '',
            'status' => $data['status'] ?? 'queued',
            'deduplicated' => $data['deduplicated'] ?? false
        ];
    }

    /**
     * Get job status from Node.js gateway
     */
    public function getStatus(string $jobId): ?array
    {
        try {
            $isc = new InterServiceClient();
            $response = $isc->get('realtime_node', "/internal/jobs/{$jobId}/status", $this->gatewayHeaders(), 10);
            $body = $response[0] ?? '';
            $status = $response[1] ?? 0;

            if ($status === 404) return null;
            if ($status < 200 || $status >= 300) return null;

            return json_decode($body, true);
        } catch (\Exception $e) {
            error_log("NodeJobClient: Failed to get job status: " . $e->getMessage());
            return null;
        }
    }

    /** Read a completed result only through the private owner-checked gateway. */
    public function getResult(string $jobId, int $operatorId): ?array
    {
        if (!preg_match('/^[1-9][0-9]{0,18}$/', $jobId) || $operatorId < 1) {
            return null;
        }

        try {
            $isc = new InterServiceClient();
            $path = '/internal/jobs/' . rawurlencode($jobId)
                . '/result?operator_id=' . $operatorId;
            $response = $isc->get('realtime_node', $path, $this->gatewayHeaders(), 30);
            $body = $response[0] ?? '';
            $status = (int) ($response[1] ?? 0);
            if ($status < 200 || $status >= 300) {
                return null;
            }
            $decoded = json_decode((string) $body, true);
            return is_array($decoded) && array_key_exists('result', $decoded) ? $decoded : null;
        } catch (\Throwable $error) {
            \App\API\Includes\FileLogger::write('node_jobs', [
                'event' => 'result_fetch_failed',
                'job_id' => (int) $jobId,
                'operator_id' => $operatorId,
                'error_type' => get_class($error),
            ], 'warning');
            return null;
        }
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

    private function generateIdempotencyKey(string $job, array $payload, int $operatorId): string
    {
        // Sort keys manually since JSON_SORT_KEYS may not be available
        $sortedPayload = $this->sortArrayKeysRecursive($payload);
        $data = $job . '|' . json_encode($sortedPayload) . '|' . $operatorId;
        return 'nodejob_' . hash('sha256', $data);
    }

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
}
