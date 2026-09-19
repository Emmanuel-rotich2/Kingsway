<?php
/** Kingsway System Administrator: Activity Audit Logs (timeline). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="activityAuditLogsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">Activity Audit Logs</h2>
            <p class="text-muted mb-0">Immutable system activity as a chronological timeline.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="activityAuditLogsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="activityAuditLogsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="activityAuditLogsLiveBtn">
                <i class="bi bi-broadcast me-1"></i> Live
            </button>
            <button type="button" class="btn btn-primary" id="activityAuditLogsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="activityAuditLogsState" role="status" aria-live="polite">
        Loading activity audit logs...
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="input-group input-group-sm" style="max-width: 340px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="activityAuditLogsSearch" type="search" maxlength="200" placeholder="User, action, resource or IP" autocomplete="off">
        </div>
        <span class="text-muted small ms-auto" id="activityAuditLogsCount">No activity records loaded</span>
    </div>

    <div id="activityAuditLogsTimeline">
        <div class="text-center py-5 text-muted">Loading activity...</div>
    </div>
</div>

<template id="activityAuditLogsEntryTemplate">
    <div class="d-flex gap-3">
        <div class="audit-node d-flex flex-column align-items-center">
            <span class="rounded-circle border bg-white d-inline-block" style="width:12px;height:12px"></span>
            <span class="flex-grow-1 border-start"></span>
        </div>
        <div class="card border-0 shadow-sm flex-grow-1 mb-3">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="fw-semibold" data-fill="user_name"></span>
                        <code class="small" data-fill="action"></code>
                    </div>
                    <span class="small text-muted" data-fill="created_at"></span>
                </div>
                <div class="small text-muted d-flex flex-wrap gap-3 mt-1">
                    <span><i class="bi bi-box me-1"></i><span data-fill="resource_type"></span></span>
                    <span><i class="bi bi-hash me-1"></i><span data-fill="resource_id"></span></span>
                    <span><i class="bi bi-globe2 me-1"></i><code data-fill="ip_address"></code></span>
                </div>
            </div>
        </div>
    </div>
</template>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/activity_audit_logs.js?v=<?= asset_version('js/pages/system/activity_audit_logs.js') ?>"></script>