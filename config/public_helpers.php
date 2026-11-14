<?php

/**
 * Public data helpers — fetches school content for public pages.
 * Only queries publicly-safe data (no student/staff PII).
 *
 * Loaded eagerly via Composer "files" autoload so every process gets them.
 * The header/security side-effects that used to ship with these functions
 * live in public/layout/public_data.php (which pages must still include).
 */

/*
 * The kw_* database helpers were removed (SQL placement rule: config/ is not
 * a legal home for SQL — only api/services|modules|includes may hold it).
 * Public pages now fetch those datasets through /api/website/* endpoints and
 * js/pages/public/*.js controllers. Only the non-DB public routing helpers
 * remain below.
 */

// Route token salt: environment-configurable (production/localhost diverge via
// config/.env APP_PUBLIC_ROUTE_SALT); the fallback is the original constant so
// already-published links keep resolving in every environment.
// Tokens minted with the original salt stay valid even when an environment
// sets its own: about a hundred links in pages, scripts and already-sent
// emails carry them literally, and they must not 404 there.
if (!defined('APP_PUBLIC_ROUTE_LEGACY_SALT')) {
    define('APP_PUBLIC_ROUTE_LEGACY_SALT', 'kingsway-public-route-v1::9f2b7d41');
}
if (!defined('APP_PUBLIC_ROUTE_SALT')) {
    define('APP_PUBLIC_ROUTE_SALT', (string) (getenv('APP_PUBLIC_ROUTE_SALT') ?: APP_PUBLIC_ROUTE_LEGACY_SALT));
}

function public_route_token(string $routeKey): string
{
    return 'r' . substr(hash('sha256', APP_PUBLIC_ROUTE_SALT . '|' . $routeKey), 0, 12);
}

function public_route_url(string $routeKey, array $query = [], string $fragment = ''): string
{
    $url = 'index.php?route=' . public_route_token($routeKey);
    if ($query !== []) {
        $url .= '&' . http_build_query($query);
    }
    if ($fragment !== '') {
        $url .= '#' . $fragment;
    }
    return $url;
}

function public_route_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    $routes = include __DIR__ . '/../public/layout/facade_routes.php';
    foreach ($routes['public'] ?? [] as $routeKey => $_target) {
        $map[public_route_token($routeKey)] = $routeKey;
        $map['r' . substr(hash('sha256', APP_PUBLIC_ROUTE_LEGACY_SALT . '|' . $routeKey), 0, 12)] = $routeKey;
    }
    return $map;
}

/** The public route key a token (current or original salt) stands for. */
function public_route_key_for_token(string $token): ?string
{
    return public_route_map()[$token] ?? null;
}
