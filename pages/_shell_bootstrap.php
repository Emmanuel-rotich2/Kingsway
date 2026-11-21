<?php
/**
 * PageShell role-template bootstrap.
 *
 * Role templates (pages/{module}/admin_*.php etc.) are fetched as raw HTML
 * by PageShell.loadRoleTemplate() via XHR, so they bypass home.php and never
 * load the Composer autoloader. This prepended shim loads it once, centrally,
 * so templates can call app_base_path(), asset_version(), and asset_script()
 * exactly as they do inside the app shell — no per-file boilerplate.
 *
 * Everything below is guarded: when the template is included through
 * home.php → app_layout.php the autoloader already exists and none of this runs.
 */

// Everything below is guarded: when the template is included through
// home.php → app_layout.php the autoloader already exists and none of this runs.
//
// Ordering matters. Config::init() resolves the configured root and defines
// APP_BASE_PATH itself, but that define is unguarded in
// config/config_{development,production}.php — so defining APP_BASE_PATH here
// FIRST and calling app_base_path() afterwards re-declares the constant and
// emits "Constant APP_BASE_PATH already defined" on every role-template fetch
// (a fatal from PHP 9). Initialise Config before touching the constant, then
// fall back to this file's own location only when Config cannot supply it.
$__kingswayRoot = dirname(__DIR__);

if (!defined('KINGSWAY_AUTOLOAD_READY')) {
    if (is_file($__kingswayRoot . '/vendor/autoload.php')) {
        require_once $__kingswayRoot . '/vendor/autoload.php';
        define('KINGSWAY_AUTOLOAD_READY', 1);
    }
}

if (!defined('APP_BASE_PATH')) {
    if (class_exists(\App\Config\Config::class)) {
        \App\Config\Config::init();
    }
    if (!defined('APP_BASE_PATH')) {
        define('APP_BASE_PATH', $__kingswayRoot);
    }
}

// Templates fetched directly by PageShell.loadRoleTemplate() run in this
// prepend's scope, not home.php's. home.php defines $appBase (and
// APP_BASE_PATH) before including any page; a direct pages/<module>/<role>.php
// request has no such parent, so templates that read $appBase directly would
// resolve to /pages/<module> and 404 every CSS/JS/link asset. Publish the
// BASE_URL-derived prefix as a global so every page surface sees the same one
// home.php uses.
if (!isset($GLOBALS['appBase']) || $GLOBALS['appBase'] === '') {
    $GLOBALS['appBase'] = app_base_path();
}
$appBase = $GLOBALS['appBase'];

// Role templates are fetched as HTML fragments: send the staff CSP and this
// response's nonce (X-CSP-Nonce), which PageShell checks before running the
// template's inline scripts. Skipped when included through home.php, which
// has already sent its headers.
if (function_exists('csp_send') && !headers_sent() && !defined('KINGSWAY_SHELL_CSP_SENT')) {
    define('KINGSWAY_SHELL_CSP_SENT', 1);
    csp_send('staff');
}
