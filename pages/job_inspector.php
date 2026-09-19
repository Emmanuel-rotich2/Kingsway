<?php
/** Kingsway System Administrator: Job Inspector (master–detail). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="jobInspectorPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">Job Inspector</h2>
            <p class="text-muted mb-0">Select a job from the queue to inspect its payload, attempts and failures.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="jobInspectorExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="jobInspectorPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="jobInspectorRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="jobInspectorState" role="status" aria-live="polite">
        Loading job inspector...
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <strong class="small text-uppercase text-muted">Queue</strong>
                        <span class="text-muted small" id="jobInspectorCount">No jobs loaded</span>
                    </div>
                    <div class="input-group input-group-sm mt-2">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="jobInspectorSearch" type="search" maxlength="200" placeholder="Queue, type or status" autocomplete="off">
                    </div>
                </div>
                <div class="list-group list-group-flush" style="max-height: 72vh; overflow-y: auto" id="jobInspectorList">
                    <div class="text-center py-5 text-muted">Loading jobs...</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <strong class="small text-uppercase text-muted">Job Detail</strong>
                    <span id="jobInspectorDetailStatus"></span>
                </div>
                <div class="card-body" id="jobInspectorDetail">
                    <div class="text-center py-5 text-muted">Select a job to inspect its payload.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="jobInspectorItemTemplate">
    <button type="button" class="list-group-item list-group-item-action text-start" data-job-id="">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <span class="fw-semibold text-break" data-fill="queue"></span>
            <span class="badge" data-fill="status"></span>
        </div>
        <div class="small text-muted text-break" data-fill="payload_type"></div>
        <div class="small text-muted mt-1">
            <span class="me-2"><i class="bi bi-hash me-1"></i><span data-fill="id"></span></span>
            <span class="me-2"><i class="bi bi-arrow-repeat me-1"></i><span data-fill="attempts"></span>/<span data-fill="max_attempts"></span></span>
            <span><i class="bi bi-clock me-1"></i><span data-fill="created_at"></span></span>
        </div>
    </button>
</template>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/job_inspector.js?v=<?= asset_version('js/pages/system/job_inspector.js') ?>"></script>