<?php
/** Kingsway System Administrator: Policy Violations (flag feed). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="policyViolationsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">Policy Violations</h2>
            <p class="text-muted mb-0">RBAC and policy denials recorded by the permission engine.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="policyViolationsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="policyViolationsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="policyViolationsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="policyViolationsState" role="status" aria-live="polite">
        Loading policy violations...
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="btn-group btn-group-sm" role="group" aria-label="Status filter" id="policyViolationsStatusFilter">
            <button type="button" class="btn btn-outline-secondary active" data-status="all">All</button>
            <button type="button" class="btn btn-outline-secondary" data-status="recorded">Recorded</button>
            <button type="button" class="btn btn-outline-secondary" data-status="reviewed">Reviewed</button>
            <button type="button" class="btn btn-outline-secondary" data-status="resolved">Resolved</button>
        </div>
        <div class="input-group input-group-sm ms-auto" style="max-width: 300px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="policyViolationsSearch" type="search" maxlength="200" placeholder="Event, user or entity" autocomplete="off">
        </div>
        <span class="text-muted small" id="policyViolationsCount">No violations loaded</span>
    </div>

    <div class="list-group" id="policyViolationsFeed">
        <div class="list-group-item text-center py-5 text-muted">Loading violations...</div>
    </div>
</div>

<template id="policyViolationsItemTemplate">
    <div class="list-group-item py-3 violation-row">
        <div class="d-flex gap-3">
            <span class="violation-flag mt-1"><i class="bi bi-exclamation-triangle-fill"></i></span>
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge" data-fill="action"></span>
                        <span class="fw-semibold" data-fill="username"></span>
                        <span class="small text-muted"><code data-fill="entity"></code></span>
                        <span class="small text-muted"><code data-fill="entity_id"></code></span>
                    </div>
                    <span class="badge status-badge" data-fill="status"></span>
                </div>
                <div class="small text-muted text-break mt-1" data-fill="details"></div>
                <div class="small text-muted mt-1"><i class="bi bi-clock me-1"></i><span data-fill="created_at"></span></div>
            </div>
        </div>
    </div>
</template>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/policy_violations.js?v=<?= asset_version('js/pages/system/policy_violations.js') ?>"></script>