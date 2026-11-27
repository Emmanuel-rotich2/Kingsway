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

/**
 * Indexable public pages that have a clean, crawlable URL (/about, /news …).
 * .htaccess rewrites each to index.php?route=<key>; canonical tags, the
 * sitemap and the site navigation all use these so search engines see one
 * URL per page instead of opaque route tokens.
 */
function public_clean_route_keys(): array
{
    return [
        'about', 'admissions', 'news', 'events', 'careers', 'contact',
        'downloads', 'gallery', 'uniform-catalog',
        'privacy-policy', 'terms-of-use', 'cookie-policy',
    ];
}

/**
 * Non-indexable facade routes that also have clean URLs (/login, …).
 *
 * home.php must hand these to the public facade too when a host's rewrite
 * rules are stale. They stay out of public_clean_route_keys() so canonicals,
 * hreflang and the sitemap never advertise auth pages as public content.
 */
function public_auth_clean_route_keys(): array
{
    return ['login', 'forgot-password', 'reset-password', 'reset-default-password'];
}

/** Relative clean URL for an indexable page; other routes keep their token URL. */
function public_clean_url(string $routeKey, array $query = [], string $fragment = ''): string
{
    if ($routeKey === 'home') {
        $url = '';
    } elseif (in_array($routeKey, public_clean_route_keys(), true)) {
        $url = $routeKey;
    } else {
        return public_route_url($routeKey, $query, $fragment);
    }
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }
    return $fragment !== '' ? $url . '#' . $fragment : $url;
}

/**
 * Public origin used in canonical URLs, hreflang, structured data and sitemaps.
 *
 * BASE_URL is a routing setting and legitimately points at the local dev host.
 * Search-facing URLs must instead use the one production origin advertised in
 * robots.txt, so an explicit PUBLIC_SITE_URL overrides it. Invalid or empty
 * values fall back to BASE_URL to keep local development self-consistent.
 */
function public_site_base(): string
{
    if (!defined('BASE_URL') && class_exists(\App\Config\Config::class)) {
        \App\Config\Config::init();
    }
    $configured = trim((string) (getenv('PUBLIC_SITE_URL') ?: ($_ENV['PUBLIC_SITE_URL'] ?? '')));
    if ($configured !== '') {
        $parts = parse_url($configured);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!empty($parts['host']) && in_array($scheme, ['https', 'http'], true)) {
            return rtrim($configured, '/');
        }
    }
    return rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
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
