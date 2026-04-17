<?php
/**
 * Manage Staff Page - JWT-Based Client-Side Router
 *
 * STATELESS ARCHITECTURE:
 * - NO PHP sessions (compatible with load balancing)
 * - User role determined from JWT token via JavaScript AuthContext
 * - Access level set client-side; staffManagementController enforces it
 */
?>

<div id="staff-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading staff management...</p>
</div>

<div id="staff-content" style="display: none;">
    <?php
    $templatePath = __DIR__ . '/staff/manage_staff_production.php';
    if (file_exists($templatePath)) {
        include $templatePath;
    } else {
        include __DIR__ . '/staff/manage_staff_base.php';
    }
    ?>
</div>

<!-- Load Staff Management Controllers -->
<script src="<?= $appBase ?>js/pages/staff.js"></script>
<script src="<?= $appBase ?>js/pages/staff_production_ui.js"></script>

<script>
(function () {
    if (typeof AuthContext === 'undefined' || !AuthContext.isAuthenticated()) {
        document.getElementById('staff-loading').innerHTML =
            '<div class="alert alert-danger">Authentication required. Please log in again.</div>';
        return;
    }

    const user = AuthContext.getUser();
    const roleId = user?.role_id ?? 0;
    const roles  = user?.roles ?? [];

    // Determine first role name (normalised)
    const firstRoleName = (() => {
        if (!roles.length) return '';
        const r = roles[0];
        return (typeof r === 'string' ? r : (r.name || '')).toLowerCase().replace(/\s+/g, '_').replace(/\//g, '_');
    })();

    // Access-level mapping (role_id takes priority, then role name)
    let accessLevel = 'viewer';
    if (roleId === 1 || roleId === 3 || roleId === 4 ||
        ['system_administrator', 'school_administrator', 'director', 'director_owner'].includes(firstRoleName)) {
        accessLevel = 'admin';
    } else if (roleId === 5 || ['headteacher'].includes(firstRoleName)) {
        accessLevel = 'manager';
    } else if (['deputy_head_discipline', 'deputy_head_academic'].includes(firstRoleName)) {
        accessLevel = 'operator';
    } else if (AuthContext.hasPermission('staff_view') || AuthContext.hasPermission('manage_staff_view')) {
        accessLevel = 'viewer';
    }

    window.currentUserRole   = firstRoleName;
    window.currentUserId     = user?.id ?? 0;
    window.staffAccessLevel  = accessLevel;

    console.log('[Manage Staff] role:', firstRoleName, '| accessLevel:', accessLevel);

    document.getElementById('staff-loading').style.display = 'none';
    document.getElementById('staff-content').style.display = 'block';

    document.addEventListener('DOMContentLoaded', function () {
        if (window.StaffProductionUI) {
            setTimeout(() => StaffProductionUI.init(), 300);
        }
    });
})();
</script>
