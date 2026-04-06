<?php

// ============================================================
// GLOBAL FAILSAFE — must be first, before any require or use
// ============================================================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

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
    $emitError([
        'status'  => 'error',
        'message' => 'Unhandled exception: ' . $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'trace'   => explode("\n", $e->getTraceAsString()),
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
        $emitError([
            'status'  => 'error',
            'message' => 'Fatal error: ' . $e['message'],
            'details' => $e,
            'code'    => 500,
        ]);
    }
});
// ============================================================

use App\API\Router\Router;
use App\Config\Config;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/helpers.php';

Config::init();

header('Content-Type: application/json; charset=utf-8');

$router = new Router();
$response = $router->handle();

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
