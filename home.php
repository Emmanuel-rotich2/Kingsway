<?php
// home.php - Main application entry point
// Uses JWT authentication (stateless) - compatible with load balancing

require_once __DIR__ . '/config/DashboardRouter.php';

// Note: Authentication is handled via JWT token in localStorage
// PHP session is NOT used to maintain stateless REST API architecture
// This allows the application to work with round-robin load balancing

// Get user role from query parameter or use default
// The actual authentication check happens via JWT token in JavaScript
$route = $_GET['route'] ?? '';

// If no route specified, JavaScript will redirect to appropriate dashboard
// based on user's role from JWT token
if (empty($route)) {
    // Let JavaScript handle the redirect based on stored user data
    $route = 'loading'; // Temporary route while JS loads
}

$main_role = 'user'; // Default, will be populated by JavaScript from token
$roles = [$main_role];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Kingsway Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="king.css">
</head>

<body>
    <div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content notification-info">
                <div class="modal-body d-flex align-items-center">
                    <span class="notification-icon me-3"><i class="bi bi-info-circle"></i></span>
                    <span class="notification-message"></span>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/layouts/app_layout.php'; ?>

    <!-- Application Scripts -->
    <script src="/Kingsway/js/api.js?v=<?php echo time(); ?>"></script>
    <script src="/Kingsway/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script src="/Kingsway/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="/Kingsway/js/index.js?v=<?php echo time(); ?>"></script>
    
    <script>
        // JWT-based authentication check (stateless)
        document.addEventListener('DOMContentLoaded', function () {
            // Check if user is authenticated via JWT token
            if (!AuthContext.isAuthenticated()) {
                console.warn('No valid JWT token found, redirecting to login');
                window.location.href = '/Kingsway/index.php';
                return;
            }

            // Get current route
            const urlParams = new URLSearchParams(window.location.search);
            const route = urlParams.get('route');

            // If no route or loading route, redirect to user's dashboard
            if (!route || route === 'loading') {
                const dashboardInfo = AuthContext.getDashboardInfo();
                if (dashboardInfo && dashboardInfo.key) {
                    window.location.href = '/Kingsway/home.php?route=' + dashboardInfo.key;
                } else {
                    // Fallback: use role to determine dashboard
                    const user = AuthContext.getUser();
                    const role = user.roles && user.roles[0] ?
                        (user.roles[0].name || user.roles[0]) : 'user';
                    console.log('No dashboard info, using role:', role);
                    // Let the page load and show a default view
                }
            }

            console.log('Authenticated as:', AuthContext.getUser().username);
            console.log('Current route:', route);
        });
    </script>
</body>

</html>