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

<!-- Load Staff Management Controller -->
<script src="<?= $appBase ?>/js/pages/staff_production_ui.js?v=<?= time() ?>"></script>

<script>
(function () {
    function showAuthError() {
        var el = document.getElementById('staff-loading');
        var div = document.createElement('div');
        div.className = 'alert alert-danger';
        div.textContent = 'Authentication required. Please log in again.';
        el.replaceChildren(div);
    }

    function initStaffPage() {
        if (typeof AuthContext === 'undefined' || !AuthContext.isAuthenticated()) {
            showAuthError();
            return;
        }

        var user = AuthContext.getUser();
        var roleId = (user && user.role_id) ? user.role_id : 0;
        var roles  = (user && user.roles) ? user.roles : [];

        var firstRoleName = '';
        if (roles.length > 0) {
            var r = roles[0];
            firstRoleName = (typeof r === 'string' ? r : (r.name || '')).toLowerCase().replace(/\s+/g, '_').replace(/\//g, '_');
        }

        var accessLevel = 'viewer';
        if (roleId === 1 || roleId === 3 || roleId === 4 ||
            ['system_administrator', 'school_administrator', 'director', 'director_owner'].indexOf(firstRoleName) !== -1) {
            accessLevel = 'admin';
        } else if (roleId === 5 || firstRoleName === 'headteacher') {
            accessLevel = 'manager';
        } else if (firstRoleName === 'deputy_head_discipline' || firstRoleName === 'deputy_head_academic') {
            accessLevel = 'operator';
        } else if (AuthContext.hasPermission('staff_view') || AuthContext.hasPermission('manage_staff_view')) {
            accessLevel = 'viewer';
        }

        window.currentUserRole   = firstRoleName;
        window.currentUserId     = (user && user.id) ? user.id : 0;
        window.staffAccessLevel  = accessLevel;

        console.log('[Manage Staff] role:', firstRoleName, '| accessLevel:', accessLevel);

        document.getElementById('staff-loading').style.display = 'none';
        document.getElementById('staff-content').style.display = 'block';

        if (window.StaffProductionUI) {
            setTimeout(function() { StaffProductionUI.init(); }, 300);
        }
    }

    // AuthContext is defined in api.js which loads AFTER this script.
    // Wait for it to become available via DOMContentLoaded + polling.
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof AuthContext !== 'undefined' && AuthContext.isAuthenticated()) {
            initStaffPage();
        } else {
            var attempts = 0;
            var waitForAuth = setInterval(function () {
                attempts++;
                if (typeof AuthContext !== 'undefined' && AuthContext.isAuthenticated()) {
                    clearInterval(waitForAuth);
                    initStaffPage();
                } else if (attempts > 20) {
                    clearInterval(waitForAuth);
                    showAuthError();
                }
            }, 250);
        }
    });
})();
</script>
