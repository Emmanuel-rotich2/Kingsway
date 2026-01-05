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

// Get route from URL (authentication verified by JWT in JavaScript)
$route = $_GET['route'] ?? 'loading';

// Verify the requested route/dashboard exists
$requestedPath = null;
if ($route !== 'loading') {
    if (DashboardRouter::dashboardExists($route)) {
        $requestedPath = DashboardRouter::getDashboardPath($route);
    } else {
        // Try as regular page
        $pagePath = __DIR__ . "/../pages/{$route}.php";
        if (file_exists($pagePath)) {
            $requestedPath = $pagePath;
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
<div class="app-layout d-flex">
    <!-- Sidebar (populated by JavaScript based on user permissions) -->
    <div id="sidebar-container">
        <?php include __DIR__ . '/../components/global/sidebar.php'; ?>
    </div>

    <!-- Main Content Area -->
    <div class="main-flex-layout d-flex flex-column flex-grow-1 min-vh-100" style="margin-left:250px; transition:margin-left 0.3s;">
        <!-- Header -->
        <?php include __DIR__ . '/../components/global/header.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content flex-grow-1" id="main-content-area">
            <div class="container-fluid py-3" id="main-content-segment">
                <?php
                if ($requestedPath && file_exists($requestedPath)) {
                    // Load the requested dashboard or page directly
                    include $requestedPath;
                } elseif ($route) {
                    // Route specified but not found
                    echo '<div class="alert alert-warning">';
                    echo '<i class="bi bi-exclamation-triangle me-2"></i>';
                    echo 'Page not found: ' . htmlspecialchars($route);
                    echo '</div>';
                    echo '<p><a href="/Kingsway/home.php?route=system_administrator_dashboard" class="btn btn-primary">Go to Dashboard</a></p>';
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
