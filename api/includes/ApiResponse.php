<?php

namespace App\API\Includes;

class ApiResponse
{
    public static function normalize(array $response, int $defaultCode = 200): array
    {
        $status = $response['status'] ?? null;
        $success = $response['success'] ?? null;
        $code = (int) ($response['code'] ?? $response['status_code'] ?? $defaultCode);
        if ($code < 100) {
            $code = $defaultCode;
        }

        if ($success === null) {
            $success = $status === 'success' || ($status === null && $code < 400);
        }

        if ($status === null) {
            $status = $success ? 'success' : 'error';
        }

        $normalized = [
            'success' => (bool) $success,
            'status' => $status,
            'data' => $response['data'] ?? null,
            'message' => $response['message'] ?? ($success ? 'OK' : 'Request failed'),
            'errors' => $response['errors'] ?? [],
            'code' => $code,
        ];

        foreach ($response as $key => $value) {
            if (!array_key_exists($key, $normalized)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /** Generic text that replaces an internal error before it reaches a user. */
    public const INTERNAL_ERROR_MESSAGE = 'An internal error occurred.';

    /**
     * True when a string carries database or code internals: SQLSTATE text,
     * driver exception names, MariaDB syntax hints, or a PHP file:line trace.
     */
    public static function isInternalErrorText(string $text): bool
    {
        return str_contains($text, 'SQLSTATE[')
            || stripos($text, 'PDOException') !== false
            || stripos($text, 'mysqli_sql_exception') !== false
            || stripos($text, 'error in your SQL syntax') !== false
            || preg_match('/\.php(?::| on line |\()\d+/', $text) === 1
            || str_contains($text, 'Stack trace:');
    }

    /**
     * Last line of defence for the response envelope. Many catch blocks hand
     * $e->getMessage() straight to errorResponse(); when that text is a
     * database or code error, users saw query fragments and table names.
     * Only envelope fields are checked (message, error, errors, and the same
     * keys one level into data), so records that legitimately contain such
     * text (an administrator's log viewer) pass through untouched.
     * The original text is logged against the request id.
     */
    public static function redactInternalErrors(array $response): array
    {
        $leaked = [];
        $scrub = static function ($value, string $path) use (&$leaked) {
            if (is_string($value) && self::isInternalErrorText($value)) {
                $leaked[$path] = mb_substr($value, 0, 2000);
                return self::INTERNAL_ERROR_MESSAGE;
            }
            return $value;
        };

        foreach (['message', 'error'] as $key) {
            if (array_key_exists($key, $response)) {
                $response[$key] = $scrub($response[$key], $key);
            }
        }
        if (isset($response['errors']) && is_array($response['errors'])) {
            foreach ($response['errors'] as $k => $v) {
                $response['errors'][$k] = $scrub($v, 'errors.' . $k);
            }
        } elseif (isset($response['errors'])) {
            $response['errors'] = $scrub($response['errors'], 'errors');
        }
        if (isset($response['data'])) {
            if (is_string($response['data'])) {
                $response['data'] = $scrub($response['data'], 'data');
            } elseif (is_array($response['data'])) {
                foreach (['message', 'error', 'details'] as $key) {
                    if (array_key_exists($key, $response['data'])) {
                        $response['data'][$key] = $scrub($response['data'][$key], 'data.' . $key);
                    }
                }
            }
        }

        if ($leaked !== []) {
            // A database error is a server fault, not a bad request.
            if (($response['success'] ?? true) === false && (int) ($response['code'] ?? 500) < 500) {
                $response['code'] = 500;
            }
            if (class_exists(\App\API\Services\Logger::class)) {
                \App\API\Services\Logger::error('errors', 'Internal error text redacted from API response', [
                    'fields' => $leaked,
                    'route' => $_SERVER['REQUEST_URI'] ?? null,
                    'request_id' => $_SERVER['REQUEST_ID'] ?? null,
                ]);
            }
        }

        return $response;
    }

    public static function success($data = null, string $message = 'OK', int $code = 200): array
    {
        return self::normalize([
            'success' => true,
            'status' => 'success',
            'data' => $data,
            'message' => $message,
            'code' => $code,
        ], $code);
    }

    public static function error(string $message, int $code = 400, array $errors = []): array
    {
        return self::normalize([
            'success' => false,
            'status' => 'error',
            'data' => null,
            'message' => $message,
            'errors' => $errors,
            'code' => $code,
        ], $code);
    }
}
