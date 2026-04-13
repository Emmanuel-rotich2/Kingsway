<?php
/**
 * Discipline Cases Page — JWT-Based Role Router
 *
 * Uses PageShell.loadRoleTemplate() to select the correct sub-template
 * based on the current user's permissions. Fully stateless (no PHP session).
 */
?>

<!-- Loading state while determining user role -->
<div id="discipline-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-danger" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading discipline management interface...</p>
</div>

<!-- Container where the correct template will be injected -->
<div id="discipline-content" style="display: none;"></div>

<script>
(function () {
    PageShell.loadRoleTemplate({
        loadingId:   'discipline-loading',
        contentId:   'discipline-content',
        templateDir: '/pages/discipline/',
        module:      'Discipline',
        levels: [
            {
                file: 'admin_discipline.php',
                test: function () {
                    return PageShell.hasAny(['discipline_manage', 'discipline_admin', 'discipline_delete', 'discipline_approve']) ||
                           PageShell.hasRole(['system_administrator', 'director', 'director_owner', 'headteacher', 'school_administrator']);
                },
            },
            {
                file: 'manager_discipline.php',
                test: function () {
                    return PageShell.hasAny(['discipline_create', 'discipline_edit', 'discipline_resolve']) ||
                           PageShell.hasRole(['deputy_head_discipline', 'deputy_head_academic', 'school_counselor_chaplain']);
                },
            },
            {
                file: 'operator_discipline.php',
                test: function () {
                    return PageShell.hasAny(['discipline_view', 'discipline_view_all', 'discipline_report']) ||
                           PageShell.hasRole(['class_teacher', 'subject_teacher', 'hod_talent_development']);
                },
            },
            {
                file: 'viewer_discipline.php',
                test: function () {
                    return PageShell.hasAny(['discipline_view_own']) ||
                           PageShell.hasRole(['parent', 'student']);
                },
            },
        ],
    });
})();
</script>
