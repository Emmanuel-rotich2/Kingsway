<?php

declare(strict_types=1);

/**
 * Bounded live provider smoke check.
 *
 * Usage: php .github/scripts/ai_provider_smoke.php --live
 *
 * The script prints only safe health metadata and the normalized response. It
 * never prints credentials, headers, raw provider bodies, or prompt content.
 */

if (!in_array('--live', $argv, true)) {
    fwrite(STDERR, "Refusing a provider call without --live.\n");
    exit(2);
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\API\Services\AiProviderGateway;

$gateway = new AiProviderGateway();
$health = $gateway->health();
$started = microtime(true);

try {
    $response = $gateway->complete([
        ['role' => 'system', 'content' => 'Return only one JSON object with a status field.'],
        ['role' => 'user', 'content' => 'Return exactly {"status":"ok"}.'],
    ], [
        'temperature' => 0,
        'max_tokens' => 64,
        'response_format' => 'json_object',
        'timeout' => 30,
    ]);

    echo json_encode([
        'health' => $health,
        'completion' => $response,
        'duration_ms' => (int) round((microtime(true) - $started) * 1000),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'health' => $health,
        'completion_error' => get_class($exception),
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}
