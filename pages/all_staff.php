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

<?php asset_script($appBase, 'js/pages/all_staff.js'); ?>
