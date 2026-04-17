<?php
/**
 * All Staff Page — JWT-Based Role Router
 *
 * Uses PageShell.loadRoleTemplate() to select the correct sub-template
 * based on the current user's permissions. Fully stateless (no PHP session).
 */
?>

<!-- Loading state while determining user role -->
<div id="staff-list-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading staff directory...</p>
</div>

<!-- Container where the correct template will be injected -->
<div id="staff-list-content" style="display: none;"></div>

<script>
(function () {
    PageShell.loadRoleTemplate({
        loadingId:   'staff-list-loading',
        contentId:   'staff-list-content',
        templateDir: '/pages/staff/',
        module:      'Staff',
        levels: [
            {
                file: 'admin_staff.php',
                test: function () {
                    return PageShell.hasAny(['staff_manage', 'staff_admin', 'staff_delete', 'staff_view_all']) ||
                           PageShell.hasRole(['system_administrator', 'director', 'director_owner', 'school_administrator', 'school_administrative_officer']);
                },
            },
            {
                file: 'manager_staff.php',
                test: function () {
                    return PageShell.hasAny(['staff_edit', 'staff_view_department']) ||
                           PageShell.hasRole(['headteacher', 'deputy_head_academic', 'deputy_head_discipline', 'hod_talent_development']);
                },
            },
            {
                file: 'operator_staff.php',
                test: function () {
                    return PageShell.hasAny(['staff_view', 'staff_view_directory']) ||
                           PageShell.hasRole(['class_teacher', 'subject_teacher', 'intern_student_teacher']);
                },
            },
            {
                file: 'viewer_staff.php',
                test: function () {
                    return PageShell.hasAny(['staff_view_own', 'staff_view_contacts']) ||
                           PageShell.hasRole(['parent', 'student', 'support_staff']);
                },
            },
        ],
    });
})();
</script>
