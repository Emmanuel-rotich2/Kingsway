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
