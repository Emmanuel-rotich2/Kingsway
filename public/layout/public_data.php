<?php
// Autoloader first: the realtime connect-src resolution below needs the
// shared services. Reordering is safe — this file only emits security
// headers and registers Composer.
require_once __DIR__ . '/../../vendor/autoload.php';

// The realtime SSE origin must be connect-src allowed for surfaces that open
// the stream (the parent portal today; any public surface that later adopts
// it). Resolved through the same environment-agnostic, browser-facing
// resolver the staff shell uses, so loopback/private addresses are never
// handed to remotely-served pages. Unresolvable/loopback-only setups keep
// the previous CSP verbatim.
$realtimeConnectOrigin = '';
try {
    $realtimeBase = \App\API\Services\RealtimeGatewayPublisher::liveBrowserStreamBase();
    $parts = is_string($realtimeBase) && $realtimeBase !== '' ? parse_url($realtimeBase) : null;
    if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $realtimeConnectOrigin = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
} catch (\Throwable $cspRealtimeError) {
    $realtimeConnectOrigin = '';
}

// Loopback connect-src is a local-development convenience only; a public
// host must never let page scripts talk to arbitrary localhost ports.
$devConnectSrc = '';
try {
    \App\Config\Config::init();
    if (!\App\Config\Config::isProduction()) {
        $devConnectSrc = ' http://localhost:*';
    }
} catch (\Throwable $cspEnvError) {
    $devConnectSrc = '';
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    csp_send('public', ['connect' => trim($devConnectSrc . ($realtimeConnectOrigin !== '' ? ' ' . $realtimeConnectOrigin : ''))]);
}
