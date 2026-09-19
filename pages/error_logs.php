<?php
/** Kingsway System Administrator: Error Logs (severity console). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="errorLogsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">Error Logs</h2>
            <p class="text-muted mb-0">Application errors and warnings captured from the API, filtered by severity.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="errorLogsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="errorLogsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="errorLogsLiveBtn">
                <i class="bi bi-broadcast me-1"></i> Live
            </button>
            <button type="button" class="btn btn-primary" id="errorLogsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="errorLogsState" role="status" aria-live="polite">
        Loading error logs...
    </div>

    <div class="row g-3 mb-3" id="errorLogsSeverityStrip"></div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-dark text-white d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-uppercase tracking-wide me-1">Console</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Severity filter" id="errorLogsLevelFilter">
                <button type="button" class="btn btn-outline-light active" data-level="all">All</button>
                <button type="button" class="btn btn-outline-light" data-level="critical">Critical</button>
                <button type="button" class="btn btn-outline-light" data-level="error">Error</button>
                <button type="button" class="btn btn-outline-light" data-level="warning">Warning</button>
                <button type="button" class="btn btn-outline-light" data-level="info">Info</button>
            </div>
            <div class="input-group input-group-sm ms-auto" style="max-width: 300px">
                <span class="input-group-text bg-secondary-subtle border-secondary-subtle"><i class="bi bi-search"></i></span>
                <input class="form-control form-control-sm bg-secondary-subtle border-secondary-subtle" id="errorLogsSearch" type="search" maxlength="200" placeholder="Message, file or level" autocomplete="off">
            </div>
            <span class="small text-light-emphasis ms-auto" id="errorLogsCount">No errors loaded</span>
        </div>
        <div id="errorLogsConsole" class="console-body">
            <div class="text-center py-5 text-muted">Loading errors...</div>
        </div>
    </div>
</div>

<template id="errorLogsRowTemplate">
    <div class="console-line px-3 py-2 border-bottom">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge severity-badge" data-fill="level"></span>
            <span class="small text-muted me-auto" data-fill="time"></span>
            <span class="small text-muted" data-fill="id"></span>
        </div>
        <div class="text-break mt-1" data-fill="message"></div>
        <div class="small text-muted text-break"><i class="bi bi-file-earmark-code me-1"></i><span data-fill="file"></span></div>
    </div>
</template>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/error_logs.js?v=<?= asset_version('js/pages/system/error_logs.js') ?>"></script>