<?php
/**
 * Shared cache-busted asset helpers for PHP-rendered pages.
 *
 * Local assets are versioned from their modification time so every deployed
 * source change produces a new URL and cannot be hidden by HTTP caches.
 */
if (!function_exists('asset_version')) {
    function asset_version(string $relativePath): string
    {
        $root = defined('APP_BASE_PATH') ? APP_BASE_PATH : dirname(__DIR__);
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $path = $root . '/' . $relativePath;

        return is_file($path) ? (string) filemtime($path) : '0';
    }
}

if (!function_exists('app_base_path')) {
    /**
     * URL path prefix for the application, derived from the configured
     * BASE_URL (config/.env -> App\Config\Config::init()).
     *
     * Deliberately NOT derived from $_SERVER['SCRIPT_NAME']: the request path
     * differs per entry point (/home.php vs /pages/x/y.php vs an XHR fragment),
     * so a request-derived base breaks every file that is not the front
     * controller. BASE_URL is the single configured source of truth, so the
     * prefix is identical on every route and every surface.
     *
     * @return string e.g. '/Kingsway' in development, '' when deployed at the host root.
     */
    function app_base_path(): string
    {
        // Ensure Config is initialized (auto-loads .env and defines BASE_URL)
        if (!defined('BASE_URL')) {
            if (class_exists(\App\Config\Config::class)) {
                \App\Config\Config::init();
            }
        }

        if (!defined('BASE_URL')) {
            return '';
        }

        $path = parse_url((string) BASE_URL, PHP_URL_PATH);
        if (!is_string($path)) {
            return '';
        }

        return rtrim($path, '/');
    }
}

if (!function_exists('asset_script')) {
    function asset_script(?string $appBase, string $path): void
    {
        $base = ($appBase === null || $appBase === '') ? app_base_path() : $appBase;
        $url = rtrim($base, '/') . '/' . ltrim($path, '/');
        echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '?v=' . htmlspecialchars(asset_version($path), ENT_QUOTES, 'UTF-8')
            . '"></script>' . PHP_EOL;
    }
}

require_once __DIR__ . '/csp_helpers.php';
