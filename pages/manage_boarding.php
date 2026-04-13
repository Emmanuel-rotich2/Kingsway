<?php
/**
 * Manage Boarding Page — JWT-Based Role Router
 *
 * Uses PageShell.loadRoleTemplate() to select the correct sub-template
 * based on the current user's permissions. Fully stateless (no PHP session).
 */
?>

<!-- Loading state while determining user role -->
<div id="boarding-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading boarding interface...</p>
</div>

<!-- Container where the correct template will be injected -->
<div id="boarding-content" style="display: none;"></div>

<script>
(function () {
    PageShell.loadRoleTemplate({
        loadingId:   'boarding-loading',
        contentId:   'boarding-content',
        templateDir: '/pages/boarding/',
        module:      'Boarding',
        scriptSrc:   '/js/pages/boarding.js',
        levels: [
            {
                file: 'admin_boarding.php',
                test: function () {
                    return PageShell.hasAny(['boarding_manage', 'boarding_admin', 'boarding_delete']) ||
                           PageShell.hasRole(['system_administrator', 'director', 'director_owner', 'headteacher', 'school_administrator']);
                },
            },
            {
                file: 'manager_boarding.php',
                test: function () {
                    return PageShell.hasAny(['boarding_edit', 'boarding_assign', 'boarding_roll_call']) ||
                           PageShell.hasRole(['boarding_master', 'matron_housemother', 'deputy_head_discipline', 'deputy_head_academic']);
                },
            },
            {
                file: 'operator_boarding.php',
                test: function () {
                    return PageShell.hasAny(['boarding_view', 'boarding_view_all']) ||
                           PageShell.hasRole(['class_teacher', 'subject_teacher', 'school_counselor_chaplain', 'support_staff']);
                },
            },
            {
                file: 'viewer_boarding.php',
                test: function () {
                    return PageShell.hasAny(['boarding_view_own']) ||
                           PageShell.hasRole(['parent', 'student']);
                },
            },
        ],
    });
})();
</script>
