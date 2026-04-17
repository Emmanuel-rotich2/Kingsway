<?php
/**
 * Manage Transport Page — JWT-Based Role Router
 *
 * Uses PageShell.loadRoleTemplate() to select the correct sub-template
 * based on the current user's permissions. Fully stateless (no PHP session).
 */
?>

<!-- Loading state while determining user role -->
<div id="transport-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-warning" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading transport management interface...</p>
</div>

<!-- Container where the correct template will be injected -->
<div id="transport-content" style="display: none;"></div>

<script>
(function () {
    PageShell.loadRoleTemplate({
        loadingId:   'transport-loading',
        contentId:   'transport-content',
        templateDir: '/pages/transport/',
        module:      'Transport',
        scriptSrc:   '/js/pages/transport.js',
        levels: [
            {
                file: 'admin_transport.php',
                test: function () {
                    return PageShell.hasAny(['transport_manage', 'transport_admin', 'transport_delete']) ||
                           PageShell.hasRole(['system_administrator', 'director', 'director_owner', 'headteacher', 'school_administrator']);
                },
            },
            {
                file: 'manager_transport.php',
                test: function () {
                    return PageShell.hasAny(['transport_edit', 'transport_create', 'transport_assign']) ||
                           PageShell.hasRole(['driver', 'school_administrative_officer']);
                },
            },
            {
                file: 'operator_transport.php',
                test: function () {
                    return PageShell.hasAny(['transport_view', 'transport_view_all']) ||
                           PageShell.hasRole(['class_teacher', 'support_staff']);
                },
            },
            {
                file: 'viewer_transport.php',
                test: function () {
                    return PageShell.hasAny(['transport_view_own']) ||
                           PageShell.hasRole(['parent', 'student']);
                },
            },
        ],
    });
})();
</script>
