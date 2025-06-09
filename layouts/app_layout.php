<?php
// layouts/app_layout.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle logout before any output
if (isset($_GET['route']) && $_GET['route'] === 'logout') {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Get user info from session
$main_role = $_SESSION['main_role'] ?? ($_SESSION['roles'][0] ?? 'guest');
$roles = $_SESSION['roles'] ?? [$main_role];
$username = $_SESSION['username'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// Initialize empty sidebar items - will be populated by JavaScript
$sidebar_items = [];
$default_dashboard = null;

// Load menu items config as fallback
$menu_items = include __DIR__ . '/../config/menu_items.php';

// Set initial sidebar items from config (will be replaced by API call)
foreach ($roles as $role) {
    if (isset($menu_items[$role])) {
        $sidebar_items = array_merge($sidebar_items, $menu_items[$role]);
    }
}
if (isset($menu_items['universal'])) {
    $sidebar_items = array_merge($sidebar_items, $menu_items['universal']);
}

// Default dashboard mapping (only used if API fails)
$default_dashboards = [
    'admin' => 'admin_dashboard',
    'teacher' => 'teacher_dashboard',
    'accountant' => 'accounts_dashboard',
    'registrar' => 'admissions_dashboard',
    'headteacher' => 'head_teacher_dashboard',
    'head_teacher' => 'head_teacher_dashboard',
    'non_teaching' => 'non_teaching_dashboard',
    'student' => 'student_dashboard',
];

// Get current route or default dashboard
$route = $_GET['route'] ?? '';
if (!$route) {
    $route = $default_dashboard ?? $default_dashboards[$main_role] ?? 'admin_dashboard';
}
?>

<!-- Main Layout Container -->
<div class="app-layout d-flex">
    <!-- Sidebar -->
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
                // Try to load dashboard first, then regular page
                $found = false;
                
                // First try dashboard path
                $dashboard_path = __DIR__ . "/../components/dashboards/{$route}.php";
                if (file_exists($dashboard_path)) {
                    include $dashboard_path;
                    $found = true;
                }
                
                // If not found, try regular page path
                if (!$found) {
                    $page_path = __DIR__ . "/../pages/{$route}.php";
                    if (file_exists($page_path)) {
                        include $page_path;
                        $found = true;
                    }
                }
                
                // If still not found, show error
                if (!$found) {
                    echo "<div class='alert alert-warning'>Page not found: {$route}</div>";
                }
                ?>
            </div>
        </main>
        
        <!-- Footer -->
        <?php include __DIR__ . '/../components/global/footer.php'; ?>
    </div>
</div>

<script>
// Make sidebar items available to JavaScript
window.SIDEBAR_ITEMS = <?php echo json_encode($sidebar_items); ?>;
window.MAIN_ROLE = <?php echo json_encode($main_role); ?>;
window.USER_ROLES = <?php echo json_encode($roles); ?>;
window.USERNAME = <?php echo json_encode($username); ?>;
window.USER_ID = <?php echo json_encode($user_id); ?>;
window.CURRENT_ROUTE = <?php echo json_encode($route); ?>;

// Initialize sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    // Fetch sidebar items using API
    window.API.users.getSidebar(window.USER_ID) // USER_ID is already set from PHP session
        .then(response => {
            if (response && response.data) {
                // Update global variables
                window.SIDEBAR_ITEMS = response.data.sidebar || window.SIDEBAR_ITEMS;
                window.MAIN_ROLE = response.data.mainRole || window.MAIN_ROLE;
                window.USER_ROLES = response.data.extraRoles || window.USER_ROLES;
                
                // Update sidebar DOM
                const sidebarContainer = document.getElementById('sidebar-container');
                if (sidebarContainer) {
                    fetch('/Kingsway/api/sidebar_render.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            sidebar_items: window.SIDEBAR_ITEMS
                        })
                    })
                    .then(res => res.text())
                    .then(html => {
                        sidebarContainer.innerHTML = html;
                        initializeSidebarBehavior();
                    })
                    .catch(error => {
                        console.error('Failed to render sidebar:', error);
                        initializeSidebarBehavior(); // Still initialize behavior with fallback items
                    });
                }
            } else {
                throw new Error('Invalid response format');
            }
        })
        .catch(error => {
            console.error('Failed to fetch sidebar:', error);
            // Sidebar will use fallback items from PHP
            initializeSidebarBehavior();
        });

    function initializeSidebarBehavior() {
        // Handle sidebar toggle
        const sidebarToggles = document.querySelectorAll('.sidebar-toggle');
        sidebarToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const targetId = this.getAttribute('data-bs-target');
                const target = document.querySelector(targetId);
                if (target) {
                    const bsCollapse = new bootstrap.Collapse(target, {
                        toggle: true
                    });
                }
            });
        });

        // Handle navigation
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const route = this.getAttribute('data-route');
                if (route) {
                    // Store current scroll position
                    sessionStorage.setItem('scrollPos', window.scrollY);
                    window.location.href = `?route=${route}`;
                }
            });
        });

        // Restore scroll position if coming back
        const scrollPos = sessionStorage.getItem('scrollPos');
        if (scrollPos) {
            window.scrollTo(0, parseInt(scrollPos));
            sessionStorage.removeItem('scrollPos');
        }

        // Highlight current route in sidebar
        const currentRoute = window.CURRENT_ROUTE;
        if (currentRoute) {
            const activeLink = document.querySelector(`.sidebar-link[data-route="${currentRoute}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
                // If in submenu, expand parent
                const parentCollapse = activeLink.closest('.collapse');
                if (parentCollapse) {
                    new bootstrap.Collapse(parentCollapse, { show: true });
                }
            }
        }
    }
});
</script>