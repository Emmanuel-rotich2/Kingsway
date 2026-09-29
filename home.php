<?php
// Main authenticated application shell.

require_once __DIR__ . '/vendor/autoload.php';

$appBase = rtrim(
    str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')),
    '/'
);

if ($appBase === '.') {
    $appBase = '';
}

$route = trim((string)($_GET['route'] ?? '')) ?: 'loading';

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
    header("Content-Security-Policy: default-src 'self'; object-src 'self' blob:; frame-src 'self' blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com https://code.jquery.com https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: blob: https://placehold.co https://images.unsplash.com; connect-src 'self' http://localhost:* ws://localhost:*; frame-ancestors 'none'; form-action 'self'");
}
?>
<!doctype html>
<html lang="en">
<head>
    <!-- Load first: silences every console.* call and routes warnings/errors to the
         central file logger (never the browser console). -->
    <?php asset_script($appBase, 'js/core/console_logger.js'); ?>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="theme-color" content="#178a50">

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
        href="<?= htmlspecialchars($appBase) ?>/assets/css/print.css?v=<?= asset_version('assets/css/print.css') ?>"
        media="print"
    >

    <script>
        window.APP_BASE = <?= json_encode($appBase) ?>;
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
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/layouts/app_layout.php'; ?>

    <script
        src="https://code.jquery.com/jquery-3.6.0.min.js"
        referrerpolicy="no-referrer"
    ></script>
    <script
        src="<?= htmlspecialchars($appBase) ?>/public/vendor/bootstrap/js/bootstrap.bundle.min.js?v=<?= asset_version('public/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"
    ></script>
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"
        referrerpolicy="no-referrer"
    ></script>

<?php
$files = [
    'js/api.js',
    'js/core/frontend_logger.js',
    'js/core/grading_scale.js',
    'js/core/session_manager.js',
    'js/core/service_worker_manager.js',
    'js/core/realtime_manager.js',
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
    'js/utils/file_lifecycle.js',
    'js/utils/print_manager.js',
    'js/utils/academic_context.js',
    'js/utils/form-validation.js',
    'js/index.js',
    'js/sidebar.js',
    'js/app_shell_ui.js',
    'js/main.js',
    'js/core/app_bootstrap.js',
    'js/core/form_draft_manager.js',
];

foreach ($files as $file) {
    asset_script($appBase, $file);
}
?>
<script>
    // Frontend telemetry logger: safe to initialize once the API client exists.
    // It buffers + batches events to the same file logger as the backend.
    if (window.AppLogger && typeof window.AppLogger.init === "function") {
        window.AppLogger.init();
    }
</script>
<script>
    // Self-binding form validation kernel: wire every decoded form that carries
    // data-kw-validate fields (names, DOB, email, phone). Injected modals are
    // covered because the kernel delegates submit/blur handling globally.
    if (window.FormValidation) {
        document.addEventListener('DOMContentLoaded', function () {
            FormValidation.bindAllForms();
        });
    }
</script>
<script>
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

        try {
            // Wait for auth to be fully initialized before checking route access.
            // Without this, the check runs before AuthContext.initialize() completes
            // so isAuthenticated() returns false and every route is denied.
            if (window.AuthContext?.ready) {
                await window.AuthContext.ready();
            } else if (window.KingswayBootstrap?.initialize) {
                await window.KingswayBootstrap.initialize();
            }

            const auth = await window.AppRouteAccess?.authorizeRouteAccess?.(route);
            if (auth && !auth.authorized) {
                const seg = document.getElementById('main-content-segment');
                if (seg) {
                    seg.innerHTML =
                        '<div class="alert alert-danger border-0 shadow-sm mt-3">' +
                        '<i class="bi bi-shield-lock me-2"></i>' +
                        '<strong>Access denied.</strong> ' +
                        'You do not have permission to view this page.' +
                        '</div>';
                }
                window.showNotification?.(
                    'You are not allowed to open that page.',
                    'warning'
                );
                setTimeout(function () {
                    window.AppRouteAccess.redirectToAllowedRoute?.(route);
                }, 2000);
            }
        } catch (e) {
            console.warn('Route authorization check failed:', e);
        }
    })();
</script>
</body>
</html>
