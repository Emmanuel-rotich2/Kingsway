<?php
/**
 * Manage Staff Page - Role-Based Router
 * Routes users to appropriate staff management interface based on their role hierarchy
 * 
 * Hierarchy:
 * - School Administrator (role_id=4): Full CRUD operations
 * - Headteacher (role_id=5): Staff management + approvals
 * - Director (role_id=3): View-only access
 * - Others: Limited or no access
 */

// Get user role information
$userRole = $_SESSION['role'] ?? 'guest';
$userId = $_SESSION['user_id'] ?? 0;
$userRoles = $_SESSION['roles'] ?? [];

// Determine role hierarchy level
$roleId = 0;
if (is_array($userRoles) && !empty($userRoles)) {
    $roleId = $userRoles[0]['id'] ?? 0;
}

// Define role-based access levels
$accessLevel = 'none';
$templateFile = '';

switch ($userRole) {
    case 'School Administrator':
    case 'school_administrator':
        $accessLevel = 'admin';
        $templateFile = 'staff/manage_staff_production.php'; // Production UI
        break;

    case 'Headteacher':
    case 'headteacher':
        $accessLevel = 'manager';
        $templateFile = 'staff/manage_staff_production.php'; // Production UI
        break;

    case 'Director':
    case 'director':
        $accessLevel = 'viewer';
        $templateFile = 'staff/manage_staff_production.php'; // Production UI
        break;

    case 'Deputy Head - Discipline':
    case 'deputy_head_discipline':
        $accessLevel = 'operator';
        $templateFile = 'staff/manage_staff_production.php'; // Production UI
        break;

    default:
        // Check if user has any staff management permissions
        if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
            $hasStaffView = in_array('staff_view', $_SESSION['permissions']) ||
                in_array('manage_staff_view', $_SESSION['permissions']);
            if ($hasStaffView) {
                $accessLevel = 'viewer';
                $templateFile = 'staff/manage_staff_production.php'; // Production UI
            }
        }
        break;
}

// If no template file determined, show access denied
if (empty($templateFile) || $accessLevel === 'none') {
    echo '<div class="alert alert-danger">
        <i class="bi bi-shield-x me-2"></i>
        <strong>Access Denied</strong><br>
        You do not have permission to access staff management.
    </div>';
    return;
}

// Set global JavaScript variables for role-aware UI
echo "<script>
    window.currentUserRole = " . json_encode($userRole) . ";
    window.currentUserId = " . json_encode($userId) . ";
    window.staffAccessLevel = " . json_encode($accessLevel) . ";
</script>";

// Load the appropriate template
$fullPath = __DIR__ . '/' . $templateFile;
if (file_exists($fullPath)) {
    include $fullPath;
} else {
    echo '<div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Template file not found: ' . htmlspecialchars($templateFile) . '
    </div>';
    // Fallback to basic interface
    include __DIR__ . '/staff/manage_staff_base.php';
}
?>

<!-- Load Staff Management Controllers -->
<script src="<?= $appBase ?>js/pages/staff.js"></script>
<script src="<?= $appBase ?>js/pages/staff_production_ui.js"></script>
<script>
    console.log('[Manage Staff Router] Loaded for role:', window.currentUserRole);
    console.log('[Manage Staff Router] Access level:', window.staffAccessLevel);
    console.log('[Manage Staff Router] Using staffManagementController from js/pages/staff.js');
    console.log('[Manage Staff Router] UI enhancements from staff_production_ui.js');

    // Initialize both controllers when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        // staffManagementController.init() is called automatically from staff.js
        
        // Initialize production UI after staff data loads
        if (window.StaffProductionUI) {
            setTimeout(() => {
                StaffProductionUI.init();
            }, 500); // Give time for DOM to fully render
        }
    });
</script>
