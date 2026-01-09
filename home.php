<?php
// home.php - Main application entry point
// Uses JWT authentication (stateless) - compatible with load balancing

require_once __DIR__ . '/config/DashboardRouter.php';

// Note: Authentication is handled via JWT token in localStorage
// PHP session is NOT used to maintain stateless REST API architecture
// This allows the application to work with round-robin load balancing

// Get user role from query parameter
// The actual authentication check happens via JWT token in JavaScript
$route = $_GET['route'] ?? '';

// If no route specified, show 'loading' and let JavaScript determine the correct dashboard
// JavaScript will read JWT token from localStorage and determine the correct dashboard via DashboardRouter
// This ensures the director gets director_owner_dashboard, not system_administrator_dashboard
if (empty($route)) {
    // Use 'loading' placeholder - JavaScript will determine actual dashboard
    $route = 'loading';
}

$main_role = 'user'; // Default, will be populated by JavaScript from token
$roles = [$main_role];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Kingsway Preparatory Academy | <?php echo ucfirst($main_role); ?> Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="images/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="images/favicon/favicon.svg" />
    <link rel="shortcut icon" href="images/favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="KingsWay Preparatory School Dashboard" />
    <link rel="manifest" href="images/favicon/site.webmanifest" />
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/Kingsway/king.css">
    
    <!-- JavaScript Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // User data is managed by AuthContext in api.js (JWT-based, stateless)
        // AuthContext loads from localStorage on page load
        // No PHP session needed - this maintains stateless architecture
        window.USER_ROLES = <?php echo json_encode($roles); ?>;
        window.MAIN_ROLE = <?php echo json_encode($main_role); ?>;
    </script>
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
    <script src="/Kingsway/js/components/ActionButtons.js?v=<?php echo time(); ?>"></script>
    <script src="/Kingsway/js/components/RoleBasedUI.js?v=<?php echo time(); ?>"></script>
    <script src="/Kingsway/js/components/DataTable.js?v=<?php echo time(); ?>"></script>
    <script src="/Kingsway/js/sidebar.js?v=<?php echo time(); ?>"></script>
    <script src="/Kingsway/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="/Kingsway/js/index.js?v=<?php echo time(); ?>"></script>
    
    <script>
        // JWT-based authentication check (stateless)
        document.addEventListener('DOMContentLoaded', function () {
            // DEVELOPMENT BYPASS: If no token but we detect user info, redirect to login
            // This handles cases where user accessed page directly without JWT
            if (!AuthContext.isAuthenticated()) {
                console.warn('No valid JWT token found');
                
                // Check if we're in development and allow bypass with localStorage flag
                const devBypass = localStorage.getItem('dev_bypass_auth');
                if (devBypass === 'true') {
                    console.warn('⚠️ DEV MODE: Auth bypass enabled - using mock token');
                    // For development only - this would be replaced with proper login
                    return;
                }
                
                console.warn('Redirecting to login to obtain JWT token');
                window.location.href = '/Kingsway/index.php';
                return;
            }

            // Get current route
            const urlParams = new URLSearchParams(window.location.search);
            const route = urlParams.get('route');

            // Route is required - if missing, PHP already redirected
            if (route) {
                const user = AuthContext.getUser();
                console.log(`Authenticated as: ${user.username}`);
                console.log(`Current route: ${route}`);

                // Server-validated sidebar/route guard: ensure current route is allowed
                // Uses backend UsersAPI::getSidebarItems which filters by effective permissions
                API.users.getSidebarItems(user.id)
                    .then(items => {
                        try {
                            const allowed = new Set();
                            const stack = Array.isArray(items) ? items.slice() : (items?.data || []);
                            while (stack.length) {
                                const it = stack.pop();
                                if (it?.url) allowed.add(String(it.url));
                                if (Array.isArray(it?.subitems)) {
                                    for (const sub of it.subitems) stack.push(sub);
                                }
                            }
                            if (route && !allowed.has(route)) {
                                console.warn('Route not permitted by server-filtered sidebar. Redirecting.');
                                const first = [...allowed][0] || (AuthContext.getDashboardInfo()?.url) || 'home';
                                window.location.replace(`/Kingsway/home.php?route=${first}`);
                            }
                        } catch (e) {
                            console.warn('Sidebar route guard failed:', e);
                        }
                    })
                    .catch(err => {
                        console.warn('Failed to fetch sidebar items for guard:', err);
                    });
            } else {
                // This shouldn't happen because PHP redirects, but handle it anyway
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