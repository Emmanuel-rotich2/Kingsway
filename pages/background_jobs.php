<?php
/** Kingsway System Administrator: Background Jobs queue board. */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="backgroundJobsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">Background Jobs</h2>
            <p class="text-muted mb-0">Live queue board — pending, processing, completed, failed and dead-letter jobs.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="backgroundJobsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="backgroundJobsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="backgroundJobsLiveBtn">
                <i class="bi bi-broadcast me-1"></i> Live
            </button>
            <button type="button" class="btn btn-primary" id="backgroundJobsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="backgroundJobsState" role="status" aria-live="polite">
        Loading background jobs...
    </div>

    <div class="row g-3 mb-4" id="backgroundJobsStrip"></div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="btn-group btn-group-sm" role="group" aria-label="Status filter" id="backgroundJobsFilter">
            <button type="button" class="btn btn-outline-secondary active" data-status="all">All</button>
            <button type="button" class="btn btn-outline-secondary" data-status="pending">Pending</button>
            <button type="button" class="btn btn-outline-secondary" data-status="processing">Processing</button>
            <button type="button" class="btn btn-outline-secondary" data-status="completed">Completed</button>
            <button type="button" class="btn btn-outline-secondary" data-status="failed">Failed</button>
            <button type="button" class="btn btn-outline-secondary" data-status="cancelled">Cancelled</button>
        </div>
        <div class="input-group input-group-sm ms-auto" style="max-width: 300px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="backgroundJobsSearch" type="search" maxlength="200" placeholder="Queue, type or error" autocomplete="off">
        </div>
        <span class="text-muted small" id="backgroundJobsCount">No jobs loaded</span>
    </div>

    <div class="row g-3" id="backgroundJobsBoard">
        <div class="col-12 text-center py-5 text-muted">Loading jobs...</div>
    </div>
</div>

<template id="backgroundJobsCardTemplate">
    <div class="col-12">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-start justify-content-between">
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-light text-dark border" data-fill="id"></span>
                        <strong data-fill="queue"></strong>
                        <code class="text-break" data-fill="payload_type"></code>
                    </div>
                    <span class="badge" data-fill="status"></span>
                </div>
                <div class="small text-muted mt-2 d-flex flex-wrap gap-3">
                    <span><i class="bi bi-arrow-repeat me-1"></i>Attempt <span data-fill="attempts"></span>/<span data-fill="max_attempts"></span></span>
                    <span><i class="bi bi-hourglass me-1"></i>Backoff <span data-fill="backoff_seconds"></span>s</span>
                    <span><i class="bi bi-clock me-1"></i><span data-fill="created_at"></span></span>
                    <span class="d-none d-md-inline"><i class="bi bi-check2 me-1"></i><span data-fill="completed_at"></span></span>
                </div>
                <div class="small mt-2 text-danger" data-fill="last_error"></div>
                <div class="small mt-1 text-muted" data-fill="dead_letter_reason"></div>
            </div>
        </div>
    </div>
</template>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/background_jobs.js?v=<?= asset_version('js/pages/system/background_jobs.js') ?>"></script>