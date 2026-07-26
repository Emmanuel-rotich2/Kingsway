<?php
// Main authenticated application shell.

require_once __DIR__ . '/config/DashboardRouter.php';

$appBase = rtrim(
    str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')),
    '/'
);

if ($appBase === '.') {
    $appBase = '';
}

$route = trim((string)($_GET['route'] ?? '')) ?: 'loading';

function asset_version(string $relativePath): string
{
    $path = __DIR__ . '/' . ltrim($relativePath, '/');

    return is_file($path)
        ? (string)filemtime($path)
        : '1';
}

function asset_script(string $appBase, string $path): void
{
    $src = htmlspecialchars(
        $appBase . '/' . ltrim($path, '/'),
        ENT_QUOTES,
        'UTF-8'
    );

    $version = htmlspecialchars(
        asset_version($path),
        ENT_QUOTES,
        'UTF-8'
    );

    echo '<script src="' . $src . '?v=' . $version . '"></script>' .
        PHP_EOL;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="theme-color" content="#178a50">

    <title>Kingsway Preparatory Academy</title>

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
        href="<?= htmlspecialchars($appBase) ?>/public/vendor/bootstrap/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css"
        rel="stylesheet"
        referrerpolicy="no-referrer"
    >
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        rel="stylesheet"
        referrerpolicy="no-referrer"
    >

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($appBase) ?>/css/school-theme.css?v=<?= asset_version('css/school-theme.css') ?>"
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
        src="<?= htmlspecialchars($appBase) ?>/public/vendor/bootstrap/js/bootstrap.bundle.min.js"
    ></script>
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"
        referrerpolicy="no-referrer"
    ></script>

<?php
$files = [
    'js/api.js',
    'js/core/session_manager.js',
    'js/core/service_worker_manager.js',
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
    'js/index.js',
    'js/sidebar.js',
    'js/app_shell_ui.js',
    'js/main.js',
    'js/core/app_bootstrap.js',
];

foreach ($files as $file) {
    asset_script($appBase, $file);
}
?>
</body>
</html>
