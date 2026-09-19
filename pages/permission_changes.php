<?php
/** Kingsway System Administrator: Permission Changes (activity stream). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="permissionChangesPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">Permission Changes</h2>
            <p class="text-muted mb-0">Role and permission mutations recorded by the permission engine.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="permissionChangesExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="permissionChangesPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="permissionChangesRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="permissionChangesState" role="status" aria-live="polite">
        Loading permission changes...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-uppercase text-muted">Change stream</span>
            <div class="input-group input-group-sm ms-auto" style="max-width: 300px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input class="form-control" id="permissionChangesSearch" type="search" maxlength="200" placeholder="Action, entity or user" autocomplete="off">
            </div>
            <span class="text-muted small" id="permissionChangesCount">No changes loaded</span>
        </div>
        <div class="card-body p-0" id="permissionChangesFeed">
            <div class="text-center py-5 text-muted">Loading changes...</div>
        </div>
    </div>
</div>

<template id="permissionChangesItemTemplate">
    <div class="d-flex gap-3 px-3 py-3 border-bottom change-row">
        <span class="change-icon rounded-3 d-flex align-items-center justify-content-center mt-1"><i class="bi bi-shield-lock"></i></span>
        <div class="flex-grow-1">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge action-badge" data-fill="action"></span>
                <span class="fw-semibold" data-fill="entity"></span>
                <span class="small text-muted"><code data-fill="entity_id"></code></span>
                <span class="small text-muted"><i class="bi bi-person me-1"></i><span data-fill="username"></span></span>
                <span class="badge status-badge ms-auto" data-fill="status"></span>
            </div>
            <div class="small text-muted mt-1"><i class="bi bi-clock me-1"></i><span data-fill="created_at"></span></div>
        </div>
    </div>
</template>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/permission_changes.js?v=<?= asset_version('js/pages/system/permission_changes.js') ?>"></script>