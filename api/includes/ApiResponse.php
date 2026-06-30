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
