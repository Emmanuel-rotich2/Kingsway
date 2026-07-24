<?php

namespace App\API\Middleware;

use App\API\Services\IpAccessControlService;
use App\Database\Database;
use Throwable;

/**
 * Enforce the canonical system_ip_rules registry before authentication.
 */
final class IpAccessControlMiddleware
{
    public static function handle(): void
    {
        $clientIp = IpAccessControlService::resolveClientIp();
        if ($clientIp === '') {
            error_log(
                '[IpAccessControlMiddleware] Client IP could not be resolved'
            );
            self::abortRequest(
                503,
                'IP security policy could not verify this request.'
            );
        }

        try {
            $service = new IpAccessControlService(
                Database::getInstance()->getConnection()
            );
            $decision = $service->evaluate($clientIp);
        } catch (Throwable $error) {
            error_log(
                '[IpAccessControlMiddleware] Policy evaluation failed: ' .
                $error->getMessage()
            );
            self::abortRequest(
                503,
                'IP security policy is temporarily unavailable.'
            );
        }

        if (!($decision['allowed'] ?? false)) {
            self::abortRequest(
                403,
                'Access denied by IP security policy.'
            );
        }
    }

    private static function abortRequest(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }

        $payload = json_encode([
            'success' => false,
            'status' => 'error',
            'data' => null,
            'message' => $message,
            'errors' => [],
            'code' => $statusCode,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        echo $payload !== false
            ? $payload
            : (
                $statusCode === 403
                    ? '{"success":false,"status":"error","message":"Access denied","code":403}'
                    : '{"success":false,"status":"error","message":"IP security policy failed","code":503}'
            );
        exit;
    }
}
