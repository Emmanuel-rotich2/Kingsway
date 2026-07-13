<?php
// home.php - Main application entry point
// Uses JWT authentication (stateless) - compatible with load balancing

require_once __DIR__ . '/config/DashboardRouter.php';

// Auto-detect the application subfolder path so JS can build correct URLs.
// Produces '' when deployed at domain root (production) and '/Kingsway' on localhost/XAMPP.
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';

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
    <link href="<?= $appBase ?>/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= $appBase ?>/css/school-theme.css">
    <link rel="stylesheet" href="<?= $appBase ?>/css/dashboards.css">
    <link rel="stylesheet" href="<?= $appBase ?>/king.css">
    <link rel="stylesheet" href="<?= $appBase ?>/assets/css/print.css">
    <style>
        /* route-guard overlay removed — PHP already serves the correct page */
    </style>
    
    <!-- Global path variable — must be in <head> so all inline scripts can use it -->
    <script>
        window.APP_BASE = <?php echo json_encode($appBase); ?>;
        window.USER_ROLES = <?php echo json_encode($roles); ?>;
        window.MAIN_ROLE = <?php echo json_encode($main_role); ?>;
        window.REQUESTED_ROUTE = <?php echo json_encode($route); ?>;
        window.SCHOOL_CONFIG = {
            name: <?php echo json_encode(defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Kingsway Preparatory School'); ?>,
            code: <?php echo json_encode(defined('SCHOOL_CODE') ? SCHOOL_CODE : 'KWPS'); ?>,
            motto: <?php echo json_encode(defined('SCHOOL_MOTTO') ? SCHOOL_MOTTO : 'In God We Soar'); ?>,
            logo: <?php echo json_encode(defined('SCHOOL_LOGO_URL') ? SCHOOL_LOGO_URL : ($appBase . '/images/logo.jpg')); ?>,
            address: <?php echo json_encode(defined('SCHOOL_ADDRESS') ? SCHOOL_ADDRESS : 'P.O Box 203-20203, Londiani, Kenya'); ?>,
            phone: <?php echo json_encode(defined('SCHOOL_PHONE') ? SCHOOL_PHONE : '+254-720-113030 / +254-720-113031'); ?>,
            email: <?php echo json_encode(defined('SCHOOL_EMAIL') ? SCHOOL_EMAIL : 'info@kingswaypreparatoryschool.sc.ke'); ?>,
            website: <?php echo json_encode(defined('SCHOOL_WEBSITE') ? SCHOOL_WEBSITE : 'www.kingswaypreparatoryschool.sc.ke'); ?>,
            principal: <?php echo json_encode(defined('SCHOOL_PRINCIPAL_NAME') ? SCHOOL_PRINCIPAL_NAME : 'Mr Bett Junior'); ?>,
            principalTitle: <?php echo json_encode(defined('SCHOOL_PRINCIPAL_TITLE') ? SCHOOL_PRINCIPAL_TITLE : 'Headteacher'); ?>
        };
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

    <?php
    // All JS loaded AFTER the DOM so Bootstrap/jQuery find document.body immediately
    // and sidebar/header elements exist when scripts initialize.
    $v = time();
    ?>
    <!-- Third-party libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" referrerpolicy="no-referrer"></script>
    <script src="<?= $appBase ?>/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js" referrerpolicy="no-referrer"></script>
    <!-- Application scripts -->
    <script src="<?= $appBase ?>/js/api.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/components/ActionButtons.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/components/RoleBasedUI.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/components/EnhancedRoleBasedUI.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/components/DataTable.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/components/ModalForm.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/components/UIComponents.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/components/PageNavigator.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/components/PageShell.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/utils/print_manager.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/sidebar.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/main.js?v=<?= $v ?>"></script>
    <script src="<?= $appBase ?>/js/index.js?v=<?= $v ?>"></script>
    <script>
        // JWT-based authentication check (stateless)
        document.addEventListener('DOMContentLoaded', async function () {
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
                window.location.href = (window.APP_BASE || '') + '/index.php';
                return;
            }

            // Get current route
            const urlParams = new URLSearchParams(window.location.search);
            const route = urlParams.get('route');
            const routeAccess = window.AppRouteAccess;

            // Route is required - if missing, PHP already redirected
            if (route) {
                const user = AuthContext.getUser();
                console.log(`Authenticated as: ${user.username}`);
                console.log(`Current route: ${route}`);

                if (route !== 'loading' && routeAccess && typeof routeAccess.authorizeRoute === 'function') {
                    try {
                        const authorization = await routeAccess.authorizeRoute(route);
                        if (!authorization.authorized) {
                            console.warn('Route not permitted for current user. Redirecting.', authorization);
                            showNotification('You are not allowed to open that page.', NOTIFICATION_TYPES.WARNING);
                            await routeAccess.redirectToAllowedRoute(route);
                            return;
                        }
                    } catch (e) {
                        console.warn('Route authorization guard failed:', e);
                    }
                }
            } else {
                // This shouldn't happen because PHP redirects, but handle it anyway
                const dashboardInfo = AuthContext.getDashboardInfo();
                if (dashboardInfo && dashboardInfo.key) {
                    window.location.href = (window.APP_BASE || '') + '/home.php?route=' + dashboardInfo.key;
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
