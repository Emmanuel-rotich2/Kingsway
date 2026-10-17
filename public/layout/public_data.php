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
    $realtimeBase = \App\API\Services\RealtimeGatewayPublisher::browserStreamBase();
    $parts = is_string($realtimeBase) && $realtimeBase !== '' ? parse_url($realtimeBase) : null;
    if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $realtimeConnectOrigin = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
} catch (\Throwable $cspRealtimeError) {
    $realtimeConnectOrigin = '';
}

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.datatables.com https://cdnjs.cloudflare.com; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
        . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        . "img-src 'self' data: blob: https://placehold.co https://images.unsplash.com; "
        . "connect-src 'self' http://localhost:* ws://localhost:*" . ($realtimeConnectOrigin !== '' ? ' ' . $realtimeConnectOrigin : '') . "; "
        . "frame-ancestors 'none'; form-action 'self'"
    );
}
