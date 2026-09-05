<?php
// layouts/app_layout.php
// Stateless authenticated application layout.

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\DashboardRouter;

$route = $_GET['route'] ?? 'loading';
$route = is_string($route) ? trim($route) : 'loading';
$route = $route === 'loading'
    ? $route
    : DashboardRouter::normalizeDashboardKey($route);

$isCanonicalRoute =
    $route === 'loading' ||
    preg_match('/^[A-Za-z0-9_\-\/]+$/', $route);

$requestedPath = null;

if ($route !== 'loading' && $isCanonicalRoute) {
    $pagesDir = realpath(__DIR__ . '/../pages');
    $pagePath = realpath(__DIR__ . "/../pages/{$route}.php");

    if (
        $pagePath &&
        $pagesDir &&
        str_starts_with($pagePath, $pagesDir . DIRECTORY_SEPARATOR)
    ) {
        $requestedPath = $pagePath;
    } elseif (DashboardRouter::dashboardExists($route)) {
        $dashboardDir = realpath(__DIR__ . '/../components/dashboards');
        $dashboardPath = realpath(
            DashboardRouter::getDashboardPath($route)
        );

        if (
            $dashboardPath &&
            $dashboardDir &&
            str_starts_with(
                $dashboardPath,
                $dashboardDir . DIRECTORY_SEPARATOR
            )
        ) {
            $requestedPath = $dashboardPath;
        }
    }
}

$main_role = 'user';
$roles = [];
$username = '';
$user_id = null;
$sidebar_items = [];
?>

<div class="app-shell" id="app-shell">
    <a href="#main-content-area" class="visually-hidden-focusable skip-link">
        Skip to main content
    </a>

    <aside
        id="sidebar-container"
        class="app-sidebar-container"
        aria-label="Primary navigation"
    >
        <?php include __DIR__ . '/../components/global/sidebar.php'; ?>
    </aside>

    <button
        id="sidebar-overlay"
        class="sidebar-overlay"
        type="button"
        aria-label="Close navigation"
    ></button>

    <div class="app-main-column">
        <?php include __DIR__ . '/../components/global/header.php'; ?>

        <main id="main-content-area" class="app-content" tabindex="-1" role="main">
            <div
                class="container-fluid app-content-inner"
                id="main-content-segment"
            >
                <?php if ($route === 'loading'): ?>
                    <section class="app-loading-state" aria-live="polite">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading dashboard</span>
                        </div>
                        <p>Loading dashboard...</p>
                    </section>
                <?php elseif ($requestedPath && file_exists($requestedPath)): ?>
                    <?php include $requestedPath; ?>
                <?php elseif ($route): ?>
                    <div class="alert alert-warning border-0 shadow-sm">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Page not found:
                        <?= htmlspecialchars($route, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <script>
                    (async function() {
                        try {
                            if (window.AuthContext?.ready) await window.AuthContext.ready();
                            else if (window.KingswayBootstrap?.initialize) await window.KingswayBootstrap.initialize();
                        } catch(e) {}
                        var di = window.AuthContext?.getDashboardInfo?.();
                        var dest = (di && di.key) ? '/home.php?route=' + di.key : '/home.php?route=account_settings';
                        setTimeout(function() { window.location.replace((window.APP_BASE || '') + dest); }, 1500);
                    })();
                    </script>
                <?php else: ?>
                    <div class="alert alert-info border-0 shadow-sm">
                        Redirecting to dashboard...
                    </div>
                    <script>
                    (async function() {
                        try {
                            if (window.AuthContext?.ready) await window.AuthContext.ready();
                            else if (window.KingswayBootstrap?.initialize) await window.KingswayBootstrap.initialize();
                        } catch(e) {}
                        var di = window.AuthContext?.getDashboardInfo?.();
                        var dest = (di && di.key) ? '/home.php?route=' + di.key : '/home.php?route=account_settings';
                        window.location.replace((window.APP_BASE || '') + dest);
                    })();
                    </script>
                <?php endif; ?>
            </div>
        </main>

        <?php include __DIR__ . '/../components/global/footer.php'; ?>
    </div>
</div>
