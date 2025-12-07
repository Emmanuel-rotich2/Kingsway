<?php
// layouts/app_layout.php
// 
// This layout integrates with the frontend AuthContext system to provide
// permission-based UI rendering. Sidebar items, routes, pages, and components
// are filtered based on user roles and permissions from the login response.

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting (development only)
error_reporting(E_ALL);
ini_set('display_errors', 1);

<<<<<<< HEAD
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
=======
// Load DashboardRouter for role-to-dashboard mapping
require_once __DIR__ . '/../config/DashboardRouter.php';

// Handle logout before any output
>>>>>>> 6ee06f9e7438cf4d29968f4b679bd37e1f7f33d1
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

// Initialize sidebar items - will be populated by JavaScript from AuthContext
$sidebar_items = [];

// Get current route or redirect to user's default dashboard
$route = $_GET['route'] ?? '';

// If no route specified, redirect to user's role-specific dashboard
if (empty($route)) {
    DashboardRouter::redirectToDefaultDashboard(true);
}

// Verify the requested route/dashboard exists
$requestedPath = null;
if (DashboardRouter::dashboardExists($route)) {
    $requestedPath = DashboardRouter::getDashboardPath($route);
} else {
    // Try as regular page
    $pagePath = __DIR__ . "/../pages/{$route}.php";
    if (file_exists($pagePath)) {
        $requestedPath = $pagePath;
    }
}

// If requested route doesn't exist, redirect to default dashboard
if (!$requestedPath) {
    error_log("Route not found: {$route}, redirecting to default dashboard");
    DashboardRouter::redirectToDefaultDashboard(true);
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
<!-- Uses AuthContext from js/api.js for permission-based rendering -->
<div class="app-layout d-flex">
    <!-- Sidebar (populated by JavaScript based on user permissions) -->
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
<<<<<<< HEAD
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
=======
                // Load the requested dashboard or page
                include $requestedPath;
>>>>>>> 6ee06f9e7438cf4d29968f4b679bd37e1f7f33d1
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
// ============================================================================
// PERMISSION-BASED UI INITIALIZATION
// Uses AuthContext to control sidebar items, pages, and user experience
// ============================================================================

// Make user info available to JavaScript
window.SIDEBAR_ITEMS = <?php echo json_encode($sidebar_items); ?>;
window.MAIN_ROLE = <?php echo json_encode($main_role); ?>;
window.USER_ROLES = <?php echo json_encode($roles); ?>;
window.USERNAME = <?php echo json_encode($username); ?>;
window.USER_ID = <?php echo json_encode($user_id); ?>;
window.CURRENT_ROUTE = <?php echo json_encode($route); ?>;

/**
 * Get default dashboard route for the current user's role
 */
function getDefaultDashboardRoute() {
    const role = window.MAIN_ROLE;
    
    // Role to dashboard mapping (must match DashboardRouter.php)
    const roleToDashboard = {
        // System & Administration
        'system_administrator': 'system_administrator_dashboard',
        'system administrator': 'system_administrator_dashboard',
        'admin': 'system_administrator_dashboard',
        
        // Leadership
        'director/owner': 'director_owner_dashboard',
        'director_owner': 'director_owner_dashboard',
        'director': 'director_owner_dashboard',
        'headteacher': 'headteacher_dashboard',
        'head_teacher': 'headteacher_dashboard',
        'deputy_headteacher': 'deputy_headteacher_dashboard',
        'deputy headteacher': 'deputy_headteacher_dashboard',
        
        // Administrative Staff
        'school_administrative_officer': 'school_administrative_officer_dashboard',
        'school administrative officer': 'school_administrative_officer_dashboard',
        'registrar': 'registrar_dashboard',
        'secretary': 'secretary_dashboard',
        
        // Teaching Staff
        'class_teacher': 'class_teacher_dashboard',
        'class teacher': 'class_teacher_dashboard',
        'subject_teacher': 'subject_teacher_dashboard',
        'subject teacher': 'subject_teacher_dashboard',
        'teacher': 'teacher_dashboard',
        'intern/student_teacher': 'intern_student_teacher_dashboard',
        'intern_student_teacher': 'intern_student_teacher_dashboard',
        
        // Finance
        'school_accountant': 'school_accountant_dashboard',
        'school accountant': 'school_accountant_dashboard',
        'accountant': 'school_accountant_dashboard',
        'accounts_assistant': 'accounts_assistant_dashboard',
        'accounts assistant': 'accounts_assistant_dashboard',
        'accounts': 'accounts_dashboard',
        
        // Operations
        'store_manager': 'store_manager_dashboard',
        'store manager': 'store_manager_dashboard',
        'store_attendant': 'store_attendant_dashboard',
        'store attendant': 'store_attendant_dashboard',
        'catering_manager/cook_lead': 'catering_manager_cook_lead_dashboard',
        'catering_manager_cook_lead': 'catering_manager_cook_lead_dashboard',
        'cook/food_handler': 'cook_food_handler_dashboard',
        'cook_food_handler': 'cook_food_handler_dashboard',
        'matron/housemother': 'matron_housemother_dashboard',
        'matron_housemother': 'matron_housemother_dashboard',
        
        // Heads of Department
        'hod_food_&_nutrition': 'hod_food_nutrition_dashboard',
        'hod_food_nutrition': 'hod_food_nutrition_dashboard',
        'hod_games_&_sports': 'hod_games_sports_dashboard',
        'hod_games_sports': 'hod_games_sports_dashboard',
        'hod_talent_development': 'hod_talent_development_dashboard',
        'hod transport': 'hod_transport_dashboard',
        'hod_transport': 'hod_transport_dashboard',
        
        // Support Services
        'driver': 'driver_dashboard',
        'school_counselor/chaplain': 'school_counselor_chaplain_dashboard',
        'school_counselor_chaplain': 'school_counselor_chaplain_dashboard',
        'security_officer': 'security_officer_dashboard',
        'security officer': 'security_officer_dashboard',
        'cleaner/janitor': 'cleaner_janitor_dashboard',
        'cleaner_janitor': 'cleaner_janitor_dashboard',
        'librarian': 'librarian_dashboard',
        'activities_coordinator': 'activities_coordinator_dashboard',
        'activities coordinator': 'activities_coordinator_dashboard',
        
        // External
        'parent/guardian': 'parent_guardian_dashboard',
        'parent_guardian': 'parent_guardian_dashboard',
        'parent': 'parent_guardian_dashboard',
        'visiting_staff': 'visiting_staff_dashboard',
        'visiting staff': 'visiting_staff_dashboard',
    };
    
    // Normalize role name
    const normalizedRole = role ? role.toLowerCase().trim() : '';
    
    // Check mapping
    if (roleToDashboard[normalizedRole]) {
        return roleToDashboard[normalizedRole];
    }
    
    // Fallback
    return 'visiting_staff_dashboard';
}

/**
 * Navigate to user's default dashboard
 */
function navigateToDefaultDashboard() {
    const dashboard = getDefaultDashboardRoute();
    window.location.href = `?route=${dashboard}`;
}

/**
 * Permission-to-Sidebar mapping
 * Maps permission codes to sidebar menu items/routes
 * Only items where user has permission will be shown
 */
const PERMISSION_TO_SIDEBAR = {
    // Students module
    'students_view': { label: 'Students', route: 'manage_students', icon: 'bi-people' },
    'students_create': { label: 'Enroll Student', route: 'manage_students_admissions', icon: 'bi-person-plus' },
    'students_import': { label: 'Import Students', route: 'import_existing_students', icon: 'bi-upload' },
    
    // Academic module
    'academic_view': { label: 'Academic', route: 'manage_subjects', icon: 'bi-book' },
    'academic_create': { label: 'Manage Subjects', route: 'manage_subjects', icon: 'bi-book-half' },
    
    // Staff module
    'staff_view': { label: 'Staff', route: 'manage_teachers', icon: 'bi-person-workspace' },
    'staff_create': { label: 'Add Staff', route: 'manage_teachers', icon: 'bi-person-plus-fill' },
    
    // Attendance module
    'attendance_view': { label: 'Attendance', route: 'submit_attendance', icon: 'bi-calendar-check' },
    'attendance_create': { label: 'Mark Attendance', route: 'mark_attendance', icon: 'bi-pencil-square' },
    
    // Finance module
    'finance_view': { label: 'Finance', route: 'manage_payrolls', icon: 'bi-wallet2' },
    'finance_create': { label: 'Manage Payroll', route: 'manage_payrolls', icon: 'bi-cash-coin' },
    'finance_approve': { label: 'Approve Payments', route: 'manage_payrolls', icon: 'bi-check-circle' },
    
    // Reports module
    'reports_view': { label: 'Reports', route: 'class_report', icon: 'bi-file-earmark-pdf' },
    
    // System module
    'system_view': { label: 'System', route: 'school_settings', icon: 'bi-gear' },
};

/**
 * Build sidebar items from user permissions
 * Filters PERMISSION_TO_SIDEBAR based on actual permissions
 */
function buildPermissionBasedSidebar() {
    if (!AuthContext.isAuthenticated()) {
        console.warn('User not authenticated, using fallback sidebar');
        return window.SIDEBAR_ITEMS;
    }

    const visibleItems = [];
    const userPerms = AuthContext.getPermissions();

    // Add items user has permission for
    userPerms.forEach(permission => {
        if (PERMISSION_TO_SIDEBAR[permission]) {
            const item = PERMISSION_TO_SIDEBAR[permission];
            // Avoid duplicates
            if (!visibleItems.find(i => i.route === item.route)) {
                visibleItems.push(item);
            }
        }
    });

    return visibleItems.length > 0 ? visibleItems : window.SIDEBAR_ITEMS;
}

/**
 * Check if user has permission to view a route
 */
function canAccessRoute(route) {
    if (!AuthContext.isAuthenticated()) {
        return true; // Fallback to default behavior
    }

    // Get all permissions
    const userPerms = AuthContext.getPermissions();

    // Check if any permission maps to this route
    for (const [perm, item] of Object.entries(PERMISSION_TO_SIDEBAR)) {
        if (item.route === route && userPerms.includes(perm)) {
            return true;
        }
    }

    // If route not in mapping, allow access (backend will check)
    return true;
}

/**
 * Redirect to allowed dashboard if current route is forbidden
 */
function ensureRouteAccess() {
    if (!AuthContext.isAuthenticated()) {
        console.warn('User not authenticated, redirecting to login');
        window.location.href = '/Kingsway/index.php';
        return;
    }

    const currentRoute = window.CURRENT_ROUTE;
    if (currentRoute && !canAccessRoute(currentRoute)) {
        console.warn(`User lacks permission for route: ${currentRoute}`);
        showNotification('Access Denied: You do not have permission to view this page', 'error');
        
        // Redirect to first accessible route
        const userPerms = AuthContext.getPermissions();
        for (const [perm, item] of Object.entries(PERMISSION_TO_SIDEBAR)) {
            if (userPerms.includes(perm)) {
                window.location.href = `?route=${item.route}`;
                return;
            }
        }
        
        // No accessible routes, show empty dashboard
        window.location.href = '/Kingsway/home.php';
    }
}

/**
 * Update sidebar display based on permissions
 */
function updateSidebarForPermissions() {
    if (!AuthContext.isAuthenticated()) {
        console.warn('AuthContext not available, using fallback sidebar');
        initializeSidebarBehavior();
        return;
    }

    // Build permission-based sidebar
    const permissionSidebar = buildPermissionBasedSidebar();
    window.SIDEBAR_ITEMS = permissionSidebar;

    // Render sidebar with permission-filtered items
    const sidebarContainer = document.getElementById('sidebar-container');
    if (sidebarContainer) {
        fetch('/Kingsway/api/sidebar_render.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + (localStorage.getItem('token') || '')
            },
            body: JSON.stringify({
                sidebar_items: permissionSidebar
            })
        })
        .then(res => res.text())
        .then(html => {
            sidebarContainer.innerHTML = html;
            initializeSidebarBehavior();
        })
        .catch(error => {
            console.error('Failed to render sidebar:', error);
            initializeSidebarBehavior();
        });
    } else {
        initializeSidebarBehavior();
    }
}

/**
 * Initialize sidebar behavior (toggle, navigation, etc.)
 */
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

    // Handle navigation (with permission check)
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const route = this.getAttribute('data-route');
            if (route) {
                // Check permission before navigation
                if (!canAccessRoute(route)) {
                    showNotification('You do not have permission to access this page', 'error');
                    return;
                }
                
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

// ============================================================================
// INITIALIZATION
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Wait for AuthContext to be ready (loaded from api.js)
    if (typeof AuthContext === 'undefined') {
        console.error('AuthContext not loaded. Make sure js/api.js is included before app_layout.php');
        initializeSidebarBehavior();
        return;
    }

    // Verify user is authenticated
    if (!AuthContext.isAuthenticated()) {
        console.warn('User not authenticated, redirecting to login');
        window.location.href = '/Kingsway/index.php';
        return;
    }

    // Log user info for debugging
    console.log(`%c👤 Layout Initialized`, 'color: #4CAF50; font-weight: bold;');
    console.log(`User: ${AuthContext.getUser().username}`);
    console.log(`Roles: ${AuthContext.getRoles().join(', ')}`);
    console.log(`Permissions: ${AuthContext.getPermissionCount()} total`);

    // Update sidebar based on permissions
    updateSidebarForPermissions();

    // Ensure user has access to current route
    ensureRouteAccess();

    // Optional: Fetch sidebar items from API (backend can provide role-specific menu)
    if (window.API && window.API.users && typeof window.API.users.getSidebar === 'function') {
        window.API.users.getSidebar(AuthContext.getUser().id)
            .then(response => {
                if (response && response.data && response.data.sidebar) {
                    // Use API-provided sidebar if available
                    window.SIDEBAR_ITEMS = response.data.sidebar;
                    updateSidebarForPermissions();
                }
            })
            .catch(error => {
                console.warn('Could not fetch sidebar from API, using permission-based fallback:', error);
                // Continue with permission-based sidebar
            });
    }
});

// Fallback: Initialize sidebar if DOMContentLoaded fires before AuthContext loads
window.addEventListener('load', function() {
    if (typeof AuthContext !== 'undefined' && AuthContext.isAuthenticated()) {
        console.log('Layout initialized via load event');
        updateSidebarForPermissions();
    }
});
</script>
>>>>>>> 015101eaa5fcec34bce60a268265d985d4998948
