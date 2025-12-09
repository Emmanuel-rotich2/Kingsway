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

    // Get sidebar items from AuthContext (stored from login response)
    const sidebarItems = AuthContext.getSidebarItems();
    
    console.log('Updating sidebar with items:', sidebarItems);
    
    // Use sidebar.js to render the sidebar
    if (typeof window.refreshSidebar === 'function') {
        window.refreshSidebar(sidebarItems);
    } else {
        console.warn('window.refreshSidebar not available, sidebar.js may not be loaded');
    }
    
    // Initialize sidebar behavior (toggle, navigation, etc.)
    initializeSidebarBehavior();
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
    // Note: sidebar.js also handles navigation, but this is a fallback
    const sidebarLinks = document.querySelectorAll('.sidebar-link:not([data-sidebar-managed])');
    sidebarLinks.forEach(link => {
        link.setAttribute('data-sidebar-managed', 'true'); // Prevent duplicate handlers
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const route = this.getAttribute('data-route');
            if (route && route !== '#') {
                // Check permission before navigation
                if (!canAccessRoute(route)) {
                    showNotification('You do not have permission to access this page', 'error');
                    return;
                }
                
                // Direct navigation (no flash)
                window.location.href = `/Kingsway/home.php?route=${route}`;
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
    console.log(`Sidebar items: ${AuthContext.getSidebarItems().length} items`);

    // Wait for sidebar.js to be ready, then update sidebar
    setTimeout(() => {
        updateSidebarForPermissions();
        ensureRouteAccess();
    }, 100);
});

// Fallback: Initialize sidebar if DOMContentLoaded fires before AuthContext loads
window.addEventListener('load', function() {
    if (typeof AuthContext !== 'undefined' && AuthContext.isAuthenticated()) {
        console.log('Layout initialized via load event');
        updateSidebarForPermissions();
    }
});
</script>