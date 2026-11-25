<?php
// Main authenticated application shell.

require_once __DIR__ . '/vendor/autoload.php';
\App\Config\Config::init();

// URL path prefix comes from the configured BASE_URL (config/.env), never from
// the request path, so it is identical on every route and every surface.
$appBase = app_base_path();

$route = trim((string)($_GET['route'] ?? '')) ?: 'loading';

// Defensive public-route handoff.
//
// Clean public URLs must be served by index.php (public CSP, public layout).
// Some production rewrites still send /about, /events ... to home.php, which
// produced a staff shell with `REQUESTED_ROUTE = "events"` and a soft 404 in
// the website preview. Hand those routes to the public facade here as well, so
// the preview works even when the host rewrite has not been refreshed.
if (
    !isset($_GET['fragment']) &&
    function_exists('public_clean_route_keys') &&
    (
        in_array($route, public_clean_route_keys(), true) ||
        $route === 'verify-report'
    )
) {
    require __DIR__ . '/index.php';
    exit;
}

// Browser-facing realtime origin: environment-agnostic resolver — public URL
// first; the internal address is only usable when the client shares the host
// with PHP (loopback development). Never handed to a remote browser.
$nodeRealtimeOrigin = \App\API\Services\RealtimeGatewayPublisher::liveBrowserStreamBase();

// Define the filesystem root so page files under pages/ can resolve
// Absolute paths used by page asset helpers for cache-busting version parameters.
if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', __DIR__);
}

if (!headers_sent()) {
    // The authenticated shell contains asset version parameters generated
    // from file modification times. Always revalidate the HTML so a reload
    // can receive the latest asset versions without manual cache clearing.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    // Strict CSP (config/csp_helpers.php): nonce'd inline scripts only, no
    // inline handlers, no eval. Loopback realtime ports in development only.
    $connectSources = \App\Config\Config::isProduction() ? '' : 'http://localhost:*';
    if ($nodeRealtimeOrigin !== '') {
        $connectSources .= ' ' . $nodeRealtimeOrigin;
    }
    csp_send('staff', ['connect' => trim($connectSources)]);
}
?>
<!doctype html>
<html lang="en">
<head>
    <!-- Load first: silences every console.* call and routes warnings/errors to the
         central file logger (never the browser console). -->
    <?= csp_bootstrap_script($appBase) ?>
    <?php asset_script($appBase, 'js/core/console_logger.js'); ?>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <!-- Declare both schemes before first paint so browser UI and the canvas
         do not flash light while a stored dark preference is being restored. -->
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#178a50">
    <script nonce="<?= csp_nonce() ?>">
      (function () {
        var root = document.documentElement;
        var preference = 'system';
        try {
          var uid = localStorage.getItem('kw_active_user_id') || '';
          var key = uid ? 'kw_' + uid + '_theme' : 'kingsway_theme';
          var stored = localStorage.getItem(key);
          // One-time legacy migration only. A stored owner keeps another
          // signed-in user's old preference from leaking into this session.
          var owner = localStorage.getItem('kingsway_theme_owner') || '';
          if (!stored && uid && (!owner || owner === uid)) stored = localStorage.getItem('kingsway_theme');
          if (stored === 'light' || stored === 'dark') preference = stored;
        } catch (_) {}
        var dark = preference === 'dark' ||
          (preference === 'system' && window.matchMedia &&
            window.matchMedia('(prefers-color-scheme: dark)').matches);
        var resolved = dark ? 'dark' : 'light';
        root.dataset.kwTheme = resolved;
        root.dataset.kwThemePreference = preference;
        root.dataset.bsTheme = resolved;
        root.classList.add('kw-theme-ready');
        var meta = document.querySelector('meta[name="color-scheme"]');
        if (meta) meta.content = preference === 'system' ? 'light dark' : resolved;
        var themeColor = document.querySelector('meta[name="theme-color"]');
        if (themeColor) themeColor.content = dark ? '#073523' : '#178a50';
        window.__KW_THEME__ = { preference: preference, resolved: resolved };
      })();
    </script>

    <title>Kingsway Preparatory School</title>

    <link
        rel="icon"
        type="image/png"
        href="<?= htmlspecialchars($appBase) ?>/images/favicon/favicon-96x96.png"
    >
    <link
        rel="manifest"
        href="<?= htmlspecialchars($appBase) ?>/manifest.webmanifest"
    >

    <link
        href="<?= htmlspecialchars($appBase) ?>/public/vendor/bootstrap/css/bootstrap.min.css?v=<?= asset_version('public/vendor/bootstrap/css/bootstrap.min.css') ?>"
        rel="stylesheet"
    >
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
        referrerpolicy="no-referrer"
    >
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        rel="stylesheet"
        referrerpolicy="no-referrer"
    >

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($appBase) ?>/css/school-theme.css?v=<?= asset_version('css/school-theme.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($appBase) ?>/css/app-common.css?v=<?= asset_version('css/app-common.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($appBase) ?>/css/dashboards.css?v=<?= asset_version('css/dashboards.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($appBase) ?>/king.css?v=<?= asset_version('king.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($appBase) ?>/css/kw-clean.css?v=<?= asset_version('css/kw-clean.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($appBase) ?>/css/theme.css?v=<?= asset_version('css/theme.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($appBase) ?>/assets/css/print.css?v=<?= asset_version('assets/css/print.css') ?>"
        media="print"
    >

    <script nonce="<?= csp_nonce() ?>">
        window.APP_BASE = <?= json_encode($appBase) ?>;
        // Build stamp for scripts loaded on demand: new URL on every deploy.
        window.KW_ASSET_BUILD = <?= json_encode(asset_build_id()) ?>;
        window.kwVersionedUrl = function (src) {
            if (!src || /[?&]v=/.test(src) || !window.KW_ASSET_BUILD) return src;
            return src + (src.indexOf('?') === -1 ? '?' : '&') + 'v=' + window.KW_ASSET_BUILD;
        };
        window.UPLOAD_URL = <?= json_encode(defined('UPLOAD_URL') ? rtrim((string) UPLOAD_URL, '/') : rtrim($appBase, '/') . '/uploads') ?>;
        window.REQUESTED_ROUTE = <?= json_encode($route) ?>;
        window.AUTH_SESSION_CONFIG = {
            accessTokenTtlSeconds: <?= (int) (
                defined('JWT_EXPIRY') ? JWT_EXPIRY : 3600
            ) ?>,
            idleTimeoutSeconds: <?= (int) (
                defined('AUTH_IDLE_TIMEOUT_SECONDS')
                    ? AUTH_IDLE_TIMEOUT_SECONDS
                    : 1800
            ) ?>,
            refreshWindowSeconds: <?= (int) (
                defined('AUTH_REFRESH_WINDOW_SECONDS')
                    ? AUTH_REFRESH_WINDOW_SECONDS
                    : 600
            ) ?>,
            monitorIntervalSeconds: <?= (int) (
                defined('AUTH_SESSION_MONITOR_INTERVAL_SECONDS')
                    ? AUTH_SESSION_MONITOR_INTERVAL_SECONDS
                    : 30
            ) ?>
        };
        window.USER_ROLES = ['user'];
        window.MAIN_ROLE = 'user';
        window.SCHOOL_CONFIG = {
            name: <?= json_encode(
                defined('SCHOOL_NAME')
                    ? SCHOOL_NAME
                    : 'Kingsway Preparatory School'
            ) ?>,
            code: <?= json_encode(
                defined('SCHOOL_CODE')
                    ? SCHOOL_CODE
                    : 'KWPS'
            ) ?>,
            motto: <?= json_encode(
                defined('SCHOOL_MOTTO')
                    ? SCHOOL_MOTTO
                    : 'In God We Soar'
            ) ?>,
            logo: <?= json_encode(
                defined('SCHOOL_LOGO_URL')
                    ? SCHOOL_LOGO_URL
                    : (
                        $appBase .
                        '/uploads/school_assets/official_school_logo.png'
                    )
            ) ?>
        };
    </script>
    <?php if (!isset($_GET['fragment']) || $_GET['fragment'] !== '1'): ?>
        <?php asset_script($appBase, 'js/core/soft_navigation.js'); ?>
    <?php endif; ?>
</head>
<body>
    <div
        class="modal fade"
        id="notificationModal"
        tabindex="-1"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content notification-info">
                <div class="modal-body d-flex align-items-center">
                    <span class="notification-icon me-3">
                        <i class="bi bi-info-circle"></i>
                    </span>
                    <span class="notification-message"></span>
                    <!-- Required: without a dismiss control this modal can only be
                         closed by showNotification()'s forced cleanup or the Escape
                         key, so any other path that opens it leaves it - and its
                         backdrop and body scroll-lock - stuck on the page. -->
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/layouts/app_layout.php'; ?>

<?php
$isFragment = isset($_GET['fragment']) && $_GET['fragment'] === '1';
?>
<script
    src="https://code.jquery.com/jquery-3.6.0.min.js"
    referrerpolicy="no-referrer"
    <?= $isFragment ? 'data-skip="1"' : '' ?>>
</script>
<script
    src="<?= htmlspecialchars($appBase) ?>/public/vendor/bootstrap/js/bootstrap.bundle.min.js?v=<?= asset_version('public/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"
    <?= $isFragment ? 'data-skip="1"' : '' ?>>
</script>
<script
    src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"
    referrerpolicy="no-referrer"
    <?= $isFragment ? 'data-skip="1"' : '' ?>>
</script>

<?php if (!$isFragment): ?>
<?php
echo '<script nonce="' . csp_nonce() . '">window.KINGSWAY_REALTIME_SSE_ENABLED = ' . ($nodeRealtimeOrigin !== '' ? 'true' : 'false') . ';</script>';
echo '<script nonce="' . csp_nonce() . '">window.KINGSWAY_REALTIME_ORIGIN = ' . json_encode($nodeRealtimeOrigin, JSON_UNESCAPED_SLASHES) . ';</script>';
// Cache-bust the service worker registration URL: static proxy caches (nginx
// proxy_cache in dev and production CDNs) can otherwise serve a stale SW
// script at an unversioned URL for weeks, freezing every browser on an old
// CACHE_VERSION. filemtime changes whenever service-worker.js is edited.
$__swMtime = @filemtime(__DIR__ . '/service-worker.js');
echo '<script nonce="' . csp_nonce() . '">window.APP_SW_VERSION = ' . ($__swMtime ? (int) $__swMtime : time()) . ';</script>';
$files = [
    'js/api.js',
    'js/config/ai-features.js',
    'js/config/ai_page_tasks.js',
    'js/components/agent_task_panel.js',
    'js/components/ai_inline_draft.js',
    'js/components/ai_chat_panel.js',
    'js/core/frontend_logger.js',
    'js/core/grading_scale.js',
    'js/core/session_manager.js',
    'js/core/service_worker_manager.js',
    'js/core/realtime_dispatch.js',
    'js/core/realtime_sse.js',
    'js/core/realtime_presence.js',
    'js/core/realtime_jobs_widget.js',
    'js/core/realtime_job_wake.js',
    'js/core/connectivity_manager.js',
    'js/core/data_store.js',
    'js/core/storage_monitor.js',
    'js/core/bfcache_handler.js',
    'js/core/speculative_loader.js',
    'js/core/error_reporter.js',
    'js/core/push_notification_manager.js',
    'js/storage/kingsway_db.js',
    'js/sync/sync_queue.js',
    'js/sync/conflict_manager.js',
    'js/utils/storage_manager.js',
    'js/components/ActionButtons.js',
    'js/components/RoleBasedUI.js',
    'js/components/EnhancedRoleBasedUI.js',
    'js/components/DataTable.js',
    'js/components/ModalForm.js',
    'js/components/UIComponents.js',
    'js/components/PageNavigator.js',
    'js/components/PageShell.js',
    'js/pages/role_shell_routes.js',
    'js/core/ui_state_manager.js',
    'js/utils/file_lifecycle.js',
    'js/utils/print_manager.js',
    'js/components/table_toolbar.js',
    'js/utils/academic_context.js',
    'js/components/year_mode.js',
    'js/components/academic_year_filter.js',
    'js/core/year_scope.js',
    'js/core/kw_clean_routes.js',
    'js/utils/form-validation.js',
    'js/utils/document_camera_capture.js',
    'js/core/recipient_picker.js',
    'js/core/markdown.js',
    'js/index.js',
    'js/sidebar.js',
    'js/core/chart_theme.js',
    'js/core/theme_manager.js',
    'js/app_shell_ui.js',
    'js/main.js',
    'js/core/app_bootstrap.js',
    'js/core/form_draft_manager.js',
];

foreach ($files as $file) {
    asset_script($appBase, $file);
}
?>
<script
    src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.2/marked.min.js"
    referrerpolicy="no-referrer"
    crossorigin="anonymous"
    ></script>
<?php endif; ?>

<script nonce="<?= csp_nonce() ?>">
    // Frontend telemetry logger: safe to initialize once the API client exists.
    // It buffers + batches events to the same file logger as the backend.
    if (window.AppLogger && typeof window.AppLogger.init === "function") {
        window.AppLogger.init();
    }
</script>
<?php if (!$isFragment): ?>
<script nonce="<?= csp_nonce() ?>">
    // Self-binding form validation kernel: wire every decoded form that carries
    // data-kw-validate fields (names, DOB, email, phone). Injected modals are
    // covered because the kernel delegates submit/blur handling globally.
    if (window.FormValidation) {
        document.addEventListener('DOMContentLoaded', function () {
            FormValidation.bindAllForms();
        });
    }
</script>
<script nonce="<?= csp_nonce() ?>">
    (async function () {
        const route = window.REQUESTED_ROUTE;

        if (!route || route === 'loading') {
            try {
                if (window.AuthContext?.ready) {
                    await window.AuthContext.ready();
                } else if (window.KingswayBootstrap?.initialize) {
                    await window.KingswayBootstrap.initialize();
                }
            } catch (e) {
                console.warn('Auth init failed during loading redirect:', e);
            }
            const dashboardInfo = window.AuthContext?.getDashboardInfo?.();
            if (dashboardInfo && dashboardInfo.key) {
                window.location.replace(
                    (window.APP_BASE || '') + '/home.php?route=' + dashboardInfo.key
                );
            } else {
                window.location.replace(
                    (window.APP_BASE || '') + '/home.php?route=account_settings'
                );
            }
            return;
        }

        // Route access for a named route is checked once, in app_bootstrap.js
        // (AppRouteAccess.authorizeRoute against the role's sidebar).
    })();
</script>
<?php endif; ?>
</body>
</html>
