<?php

// ============================================================
// GLOBAL FAILSAFE — must be first, before any require or use
// ============================================================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set((string) (getenv('APP_TIMEZONE') ?: 'Africa/Nairobi'));

// Keep legacy error_log() calls inside the governed environment log area.
// A daily filename prevents the unbounded root-level PHP error file that older
// installations produced; the System Administrator viewer parses these lines.
$nativeLogEnv = strtolower((string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development')));
$nativeLogEnv = in_array($nativeLogEnv, ['production', 'staging'], true) ? $nativeLogEnv : 'development';
$nativeLogDir = dirname(__DIR__) . '/logs/' . $nativeLogEnv;
if (!is_dir($nativeLogDir)) {
    @mkdir($nativeLogDir, 0770, true);
}
ini_set('error_log', $nativeLogDir . '/php-errors-' . date('Y-m-d') . '.log');

ob_start();

$emitError = function (array $payload): void {
    while (ob_get_level()) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

set_exception_handler(function (\Throwable $e) use ($emitError) {
    if (class_exists(\App\API\Services\Logger::class)) {
        \App\API\Services\Logger::critical('errors', 'Unhandled exception', [
            'exception' => get_class($e), 'error' => $e->getMessage(),
            'file' => $e->getFile(), 'line' => $e->getLine(),
        ]);
    } else {
        error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
    $emitError([
        'status'  => 'error',
        'message' => 'An internal error occurred',
        'code'    => 500,
    ]);
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () use ($emitError) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (class_exists(\App\API\Services\Logger::class)) {
            \App\API\Services\Logger::critical('errors', 'Fatal PHP error', $e);
        } else {
            error_log('Fatal error: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        }
        $emitError([
            'status'  => 'error',
            'message' => 'An internal error occurred',
            'code'    => 500,
        ]);
    }
});
// ============================================================

use App\API\Router\Router;
use App\API\Services\RequestIdempotencyService;
use App\Config\Config;

require_once __DIR__ . '/../vendor/autoload.php';

Config::init();

// ============================================================
// REQUEST-ID CORRELATION BOOTSTRAP
// The browser sends X-Request-ID and X-Browser-Session-Id. We validate the
// client value (safe charset + bounded length) or generate one, bind it to
// this execution for every Logger entry, and echo the accepted value back so
// a support engineer can copy it from the browser Network tab and search the
// log files. Async/queued work reuses this value via Logger context.
// ============================================================
try {
    $incoming = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
    $requestId = '';
    if ($incoming !== '' && preg_match('/^[A-Za-z0-9._-]{1,128}$/', $incoming)) {
        $requestId = $incoming;
    } else {
        $requestId = 'req_' . bin2hex(random_bytes(8));
    }
    $_SERVER['REQUEST_ID'] = $requestId;

    $browserSession = (string) ($_SERVER['HTTP_X_BROWSER_SESSION_ID'] ?? '');
    if ($browserSession !== '' && preg_match('/^[A-Za-z0-9._-]{1,128}$/', $browserSession)) {
        $_SERVER['browser_session_id'] = $browserSession;
    }

    if (!headers_sent()) {
        header('X-Request-ID: ' . $requestId);
    }
} catch (\Throwable $e) {
    $_SERVER['REQUEST_ID'] = 'req_init_failed';
}
// ============================================================

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin');
    // Camera access is required by the authenticated QR scanner page. Keep
    // microphone and geolocation disabled; the page still requires HTTPS and
    // the browser's explicit camera permission.
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src \'self\' data: blob: https://placehold.co https://images.unsplash.com; connect-src \'self\'; frame-ancestors \'none\'');
    if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header_remove('X-Powered-By');
}

$idempotencyKey = null;
$idempotencyHash = null;
$idempotencyPayloadHash = null;
$idempotencyOwnerToken = null;
$idempotencyDb = null;
$idempotencyConflict = false;
$idempotencyInProgress = false;
$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (in_array($requestMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $idempotencyKey = RequestIdempotencyService::keyFromRequest();
    if ($idempotencyKey !== null) {
        try {
            $idempotencyDb = \App\Database\Database::getInstance()->getConnection();
            $idempotencyHash = RequestIdempotencyService::requestHash($idempotencyKey);
            $idempotencyPayloadHash = RequestIdempotencyService::payloadHash(
                (string) file_get_contents('php://input')
            );
            $reservation = RequestIdempotencyService::reserve(
                $idempotencyDb,
                $idempotencyHash,
                $idempotencyPayloadHash
            );
            if ($reservation['type'] === 'owner') {
                $idempotencyOwnerToken = $reservation['owner_token'];
            } elseif ($reservation['type'] === 'replay') {
                http_response_code($reservation['status_code']);
                $response = $reservation['response'];
                $replayed = true;
            } else {
                $idempotencyInProgress = true;
                $response = [
                    'success' => false,
                    'status' => 'error',
                    'data' => null,
                    'message' => 'An identical request is already being processed. Retry with the same Idempotency-Key.',
                    'errors' => [],
                    'code' => 409,
                ];
                http_response_code(409);
            }
        } catch (\DomainException $e) {
            $idempotencyConflict = true;
            $response = [
                'success' => false,
                'status' => 'error',
                'data' => null,
                'message' => $e->getMessage(),
                'errors' => [],
                'code' => 409,
            ];
            http_response_code(409);
        } catch (\Throwable $e) {
            $idempotencyDb = null;
            $idempotencyHash = null;
            \App\API\Services\Logger::warning('idempotency', 'Idempotency lookup unavailable', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

if (!isset($response)) {
    $router = new Router();
    $response = $router->handle();
}

// MCP speaks the protocol's native JSON-RPC envelope. It is intentionally
// marked by McpController so the normal application envelope does not wrap it.
if (is_array($response) && !empty($response['mcp_raw'])) {
    unset($response['mcp_raw']);
    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}
$response = \App\API\Includes\ApiResponse::normalize(
    is_array($response) ? $response : ['data' => $response]
);
if (!headers_sent() && !$response['success']) {
    http_response_code((int) ($response['code'] ?? 500));
}

if ($idempotencyDb !== null && $idempotencyHash !== null
    && $idempotencyPayloadHash !== null && $idempotencyOwnerToken !== null
    && !isset($replayed) && !$idempotencyConflict && !$idempotencyInProgress) {
    try {
        RequestIdempotencyService::complete(
            $idempotencyDb,
            $idempotencyHash,
            $idempotencyPayloadHash,
            $idempotencyOwnerToken,
            $response,
            http_response_code() ?: (int) ($response['code'] ?? 200)
        );
    } catch (\Throwable $e) {
        \App\API\Services\Logger::warning('idempotency', 'Idempotency response could not be stored', [
            'error' => $e->getMessage(),
        ]);
    }
}

ob_end_clean();

$json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Response serialization failed: ' . json_last_error_msg(),
        'code'    => 500,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo $json;
}
