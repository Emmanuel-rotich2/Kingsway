<?php
/**
 * Manage Communications Page — JWT-Based Role Router
 *
 * Uses PageShell.loadRoleTemplate() to select the correct sub-template
 * based on the current user's permissions. Fully stateless (no PHP session).
 */
?>

<!-- Loading state while determining user role -->
<div id="communications-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-info" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading communications interface...</p>
</div>

<!-- Container where the correct template will be injected -->
<div id="communications-content" style="display: none;"></div>

<script>
(function () {
    PageShell.loadRoleTemplate({
        loadingId:   'communications-loading',
        contentId:   'communications-content',
        templateDir: '/pages/communications/',
        module:      'Communications',
        scriptSrc:   '/js/pages/communications.js',
        levels: [
            {
                file: 'admin_communications.php',
                test: function () {
                    return PageShell.hasAny(['communications_admin', 'communications_manage', 'communications_delete', 'communications_campaign']) ||
                           PageShell.hasRole(['system_administrator', 'director', 'director_owner', 'headteacher', 'school_administrator']);
                },
            },
            {
                file: 'manager_communications.php',
                test: function () {
                    return PageShell.hasAny(['communications_create', 'communications_edit', 'communications_send']) ||
                           PageShell.hasRole(['deputy_head_academic', 'deputy_head_discipline', 'school_accountant', 'school_administrative_officer']);
                },
            },
            {
                file: 'operator_communications.php',
                test: function () {
                    return PageShell.hasAny(['communications_view', 'communications_view_all']) ||
                           PageShell.hasRole(['class_teacher', 'subject_teacher', 'hod_talent_development', 'school_counselor_chaplain']);
                },
            },
            {
                file: 'viewer_communications.php',
                test: function () {
                    return PageShell.hasAny(['communications_view_own']) ||
                           PageShell.hasRole(['parent', 'student', 'support_staff']);
                },
            },
        ],
    });
})();
</script>
