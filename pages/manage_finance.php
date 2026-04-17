<?php
/**
 * Manage Finance Page — JWT-Based Role Router
 *
 * Uses PageShell.loadRoleTemplate() to select the correct sub-template
 * based on the current user's permissions. Fully stateless (no PHP session).
 */
?>

<!-- Loading state while determining user role -->
<div id="manage-finance-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-success" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading finance management interface...</p>
</div>

<!-- Container where the correct template will be loaded -->
<div id="manage-finance-content" style="display: none;"></div>

<script>
(function () {
    PageShell.loadRoleTemplate({
        loadingId:   'manage-finance-loading',
        contentId:   'manage-finance-content',
        templateDir: '/pages/finance/',
        module:      'Finance',
        scriptSrc:   '/js/pages/finance.js',
        levels: [
            {
                file: 'admin_finance.php',
                test: function () {
                    return PageShell.hasAny(['finance_manage', 'finance_admin', 'finance_delete', 'finance_approve']) ||
                           PageShell.hasRole(['system_administrator', 'director', 'director_owner', 'headteacher', 'school_administrator']);
                },
            },
            {
                file: 'manager_finance.php',
                test: function () {
                    return PageShell.hasAny(['finance_edit', 'finance_create', 'finance_approve_payments']) ||
                           PageShell.hasRole(['school_accountant', 'accountant', 'bursar', 'finance_manager']);
                },
            },
            {
                file: 'operator_finance.php',
                test: function () {
                    return PageShell.hasAny(['finance_view', 'finance_view_all']) ||
                           PageShell.hasRole(['class_teacher', 'subject_teacher', 'intern_student_teacher', 'teacher']);
                },
            },
            {
                file: 'viewer_finance.php',
                test: function () {
                    return PageShell.hasAny(['finance_view_own', 'fees_view']) ||
                           PageShell.hasRole(['parent', 'student']);
                },
            },
        ],
    });
})();
</script>
