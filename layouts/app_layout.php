<?php
// layouts/app_layout.php
// 
// Stateless layout for REST API architecture
// Authentication is handled via JWT tokens (no PHP sessions)
// Compatible with load balancing and horizontal scaling

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load DashboardRouter for role-to-dashboard mapping
require_once __DIR__ . '/../config/DashboardRouter.php';
use App\Config\DashboardRouter;

// Get route from URL (authentication verified by JWT in JavaScript)
$route = $_GET['route'] ?? 'loading';
$route = is_string($route) ? trim($route) : 'loading';
$isCanonicalRoute = $route === 'loading' || preg_match('/^[A-Za-z0-9_\-\/]+$/', $route);

// Verify the requested route/page exists
$requestedPath = null;
if ($route !== 'loading' && $isCanonicalRoute) {
    $pagePath = realpath(__DIR__ . "/../pages/{$route}.php");
    $pagesDir = realpath(__DIR__ . '/../pages');

    if ($pagePath && $pagesDir && str_starts_with($pagePath, $pagesDir . DIRECTORY_SEPARATOR)) {
        $requestedPath = $pagePath;
    } elseif (DashboardRouter::dashboardExists($route)) {
        $dashboardPath = realpath(DashboardRouter::getDashboardPath($route));
        $dashboardDir = realpath(__DIR__ . '/../components/dashboards');
        if ($dashboardPath && $dashboardDir && str_starts_with($dashboardPath, $dashboardDir . DIRECTORY_SEPARATOR)) {
            $requestedPath = $dashboardPath;
        }
    }
}

// Default values (will be populated by JavaScript from JWT token)
$main_role = 'user';
$roles = [];
$username = '';
$user_id = null;
$sidebar_items = [];
?>

<!-- Main Layout Container -->
<!-- Uses AuthContext from js/api.js for permission-based rendering -->
<div class="app-layout">
    <!-- Sidebar (populated by JavaScript based on user permissions) -->
    <div id="sidebar-container">
        <?php include __DIR__ . '/../components/global/sidebar.php'; ?>
    </div>

    <!-- Mobile overlay — closes sidebar when tapping outside -->
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Main Content Area -->
    <div class="main-flex-layout d-flex flex-column min-vh-100">
        <!-- Header -->
        <?php include __DIR__ . '/../components/global/header.php'; ?>
        
        <!-- Main Content -->
        <main  id="main-content-area">
            <div class="container-fluid py-3" id="main-content-segment">
                <?php
                if ($route === 'loading') {
                    echo '<div class="d-flex align-items-center justify-content-center py-5 text-muted">';
                    echo '<div class="text-center">';
                    echo '<div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>';
                    echo '<div>Loading dashboard...</div>';
                    echo '</div>';
                    echo '</div>';
                } elseif ($requestedPath && file_exists($requestedPath)) {
                    // Load the requested dashboard or page directly
                    include $requestedPath;
                } elseif ($route) {
                    // Route specified but not found
                    echo '<div class="alert alert-warning">';
                    echo '<i class="bi bi-exclamation-triangle me-2"></i>';
                    echo 'Page not found: ' . htmlspecialchars($route);
                    echo '</div>';
                } else {
                    // No route (shouldn't happen because home.php redirects)
                    echo '<div class="alert alert-info">Redirecting to dashboard...</div>';
                }
                ?>
            </div>
        </main>
        
        <!-- Footer -->
        <?php include __DIR__ . '/../components/global/footer.php'; ?>
    </div>
</div>
