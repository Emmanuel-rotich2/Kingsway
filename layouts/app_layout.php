<?php
// layouts/app_layout.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting (development only)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect to login if no role is set
if (!isset($_SESSION['role'])) {
    header('Location: ../index.php');
    exit;
}

$user_role = $_SESSION['role'];

// Define sidebar items for each role
$sidebar_items_admin = [
    ['label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => '?route=admin_dashboard'],
    ['label' => 'Manage', 'icon' => 'fas fa-users-cog', 'subitems' => [
        ['label' => 'Students', 'icon' => 'fas fa-user-graduate', 'url' => '?route=manage_students'],
        ['label' => 'Teachers', 'icon' => 'fas fa-chalkboard-teacher', 'url' => '?route=manage_teachers'],
        ['label' => 'Non Teaching Staff', 'icon' => 'fas fa-users', 'url' => '?route=manage_non_teaching_staff'],
        ['label' => 'Parents', 'icon' => 'fas fa-user-friends', 'url' => '?route=manage_parents'],
    ]],
    ['label' => 'Finance', 'icon' => 'fas fa-money-bill', 'subitems' => [
        ['label' => 'Fee Management', 'icon' => 'fas fa-money-bill-wave', 'url' => '?route=fees'],
        ['label' => 'Payroll', 'icon' => 'fas fa-money-check-alt', 'url' => '?route=payroll'],
    ]],
    ['label' => 'Reports', 'icon' => 'fas fa-chart-line', 'subitems' => [
        ['label' => 'Student Performance', 'icon' => 'fas fa-graduation-cap', 'url' => '?route=student_performance'],
        ['label' => 'Attendance Reports', 'icon' => 'fas fa-calendar-check', 'url' => '?route=attendance_reports'],
        ['label' => 'Financial Reports', 'icon' => 'fas fa-file-invoice-dollar', 'url' => '?route=financial_reports'],
    ]],
    ['label' => 'Settings', 'icon' => 'fas fa-cog', 'url' => '?route=settings'],
];

$sidebar_items_teacher = [
  ['label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => '?route=teacher_dashboard'], 
    ['label' => 'Class Management', 'icon' => 'fas fa-chalkboard-teacher', 'url' => '?route=myclasses'], 
    ['label' => 'Attendance Management', 'icon' => 'fas fa-user-check', 'url' => '?route=mark_attendance'], 
    ['label' => 'Results Management', 'icon' => 'fas fa-graduation-cap', 'url' => '?route=enter_results'], 
    ['label' => 'Assignments', 'icon' => 'fas fa-tasks', 'url' => '?route=resources'], 
    ['label' => 'Timetable', 'icon' => 'fas fa-calendar-alt', 'url' => '?route=timetable'], 
    ['label' => 'Communications', 'icon' => 'fas fa-comments', 'url' => '?route=communications'], 
    ['label' => 'CATs and Reports', 'icon' => 'fas fa-chart-line', 'url' => '?route=cats_reports'], 
    ['label' => 'STG', 'icon' => 'fas fa-lightbulb', 'url' => '?route=stg'], 
    ['label' => 'Report & Analysis', 'icon' => 'fas fa-chart-pie', 'url' => '?route=report_analysis'], 
    ['label' => 'Profile Settings', 'icon' => 'fas fa-user-cog', 'url' => '?route=settings'], 
];

$sidebar_items_accountant = [
    ['label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => '?route=accounts_dashboard'],
    ['label' => 'Fee Management', 'icon' => 'fas fa-money-bill-wave', 'url' => '?route=fees'],
    ['label' => 'Payroll', 'icon' => 'fas fa-money-check-alt', 'url' => '?route=payroll'],
    ['label' => 'Financial Reports', 'icon' => 'fas fa-file-invoice-dollar', 'url' => '?route=financial_reports'],
    ['label' => 'Settings', 'icon' => 'fas fa-cog', 'url' => '?route=settings'],
];

$sidebar_items_registrar = [
    ['label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => '?route=admissions_dashboard'],
    ['label' => 'Student Management', 'icon' => 'fas fa-user-graduate', 'subitems' => [
        ['label' => 'Admissions', 'icon' => 'fas fa-user-plus', 'url' => '?route=admissions'],
        ['label' => 'Student Records', 'icon' => 'fas fa-file-alt', 'url' => '?route=student_records'],
    ]],
    ['label' => 'Teacher Management', 'icon' => 'fas fa-chalkboard-teacher', 'url' => '?route=manage_teachers'],
    ['label' => 'Attendance Management', 'icon' => 'fas fa-calendar-check', 'url' => '?route=attendance_management'],
    ['label' => 'Settings', 'icon' => 'fas fa-cog', 'url' => '?route=settings'],
];
$sidebar_items_head_teacher = [
    ['label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => '?route=head_teacher'],
    ['label' => 'Student Management', 'icon' => 'fas fa-user-graduate', 'subitems' => [
        ['label' => 'Admissions', 'icon' => 'fas fa-user-plus', 'url' => '?route=admissions'],
        ['label' => 'Student Records', 'icon' => 'fas fa-file-alt', 'url' => '?route=student_records'],
    ]],
    ['label' => 'Teacher Management', 'icon' => 'fas fa-chalkboard-teacher', 'url' => '?route=manage_teachers'],
    ['label' => 'Attendance Management', 'icon' => 'fas fa-calendar-check', 'url' => '?route=attendance_management'],
    ['label' => 'Settings', 'icon' => 'fas fa-cog', 'url' => '?route=settings'],
];


// Sidebar selection and default dashboard
switch ($user_role) {
    case 'teacher':
        $sidebar_items = $sidebar_items_teacher;
        $default_dashboard = 'teacher_dashboard';
        break;
    case 'accountant':
        $sidebar_items = $sidebar_items_accountant;
        $default_dashboard = 'accounts_dashboard';
        break;
    case 'registrar':
        $sidebar_items = $sidebar_items_registrar;
        $default_dashboard = 'admissions_dashboard';
        break;
    case 'admin':
        $sidebar_items = $sidebar_items_admin;
        $default_dashboard = 'admin_dashboard';
        break;
    case 'head_teacher':
        $sidebar_items = $sidebar_items_head_teacher;
        $default_dashboard = 'head_teacher';
        break;
    default:
        // Unrecognized role
        header('Location: ../index.php');
        exit;
}

// Handle logout early
if (isset($_GET['route']) && $_GET['route'] === 'logout') {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Set current route
$route = $_GET['route'] ?? $default_dashboard;

// Redirect if 'route=dashboard' or no route specified
if (!isset($_GET['route']) || $_GET['route'] === 'dashboard') {
    header("Location: ?route={$default_dashboard}");
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

<<<<<<< HEAD
<!-- HTML Layout Starts -->
<div class="app-layout d-flex" style="margin:0; padding:0;">
    <?php
    $collapsed = false;
    include __DIR__ . '/../components/global/sidebar.php';
    ?>

=======
<!-- Main Layout Container -->
<div class="app-layout d-flex">
    <!-- Sidebar -->
    <div id="sidebar-container">
        <?php include __DIR__ . '/../components/global/sidebar.php'; ?>
    </div>

    <!-- Main Content Area -->
>>>>>>> 015101eaa5fcec34bce60a268265d985d4998948
    <div class="main-flex-layout d-flex flex-column flex-grow-1 min-vh-100" style="margin-left:250px; transition:margin-left 0.3s;">
        <!-- Header -->
        <?php include __DIR__ . '/../components/global/header.php'; ?>
<<<<<<< HEAD

        <main class="main-content flex-grow-1" id="main">
            <div class="container-fluid py-3">
                <?php
                $page_file = __DIR__ . '/../pages/' . $route . '.php';
                $dash_file = __DIR__ . '/../components/dashboards/' . $route . '.php';

                if (isset($content_file) && file_exists($content_file)) {
                    include $content_file;
                } elseif (file_exists($page_file)) {
                    include $page_file;
                } elseif (file_exists($dash_file)) {
                    include $dash_file;
                } else {
                    echo "<div class='alert alert-warning'>Page not found.</div>";
=======
        
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
>>>>>>> 015101eaa5fcec34bce60a268265d985d4998948
                }
                ?>
            </div>
        </main>
<<<<<<< HEAD

=======
        
        <!-- Footer -->
>>>>>>> 015101eaa5fcec34bce60a268265d985d4998948
        <?php include __DIR__ . '/../components/global/footer.php'; ?>
    </div>
</div>

<<<<<<< HEAD
<!-- Scripts -->
<script src="../../js/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
=======
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
>>>>>>> 015101eaa5fcec34bce60a268265d985d4998948
