<?php
/** Kingsway System Administrator: Security Incidents (incident board). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="securityIncidentsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">Security Incidents</h2>
            <p class="text-muted mb-0">Authentication and authorization incidents recorded by the security layer.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="securityIncidentsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="securityIncidentsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="securityIncidentsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="securityIncidentsState" role="status" aria-live="polite">
        Loading security incidents...
    </div>

    <div class="row g-3 mb-3" id="securityIncidentsStrip"></div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="btn-group btn-group-sm" role="group" aria-label="Status filter" id="securityIncidentsStatusFilter">
            <button type="button" class="btn btn-outline-secondary active" data-status="all">All</button>
            <button type="button" class="btn btn-outline-secondary" data-status="open">Open</button>
            <button type="button" class="btn btn-outline-secondary" data-status="investigating">Investigating</button>
            <button type="button" class="btn btn-outline-secondary" data-status="resolved">Resolved</button>
            <button type="button" class="btn btn-outline-secondary" data-status="dismissed">Dismissed</button>
        </div>
        <div class="input-group input-group-sm ms-auto" style="max-width: 300px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="securityIncidentsSearch" type="search" maxlength="200" placeholder="Event, user or entity" autocomplete="off">
        </div>
        <span class="text-muted small" id="securityIncidentsCount">No incidents loaded</span>
    </div>

    <div class="row g-3" id="securityIncidentsBoard">
        <div class="col-12 text-center py-5 text-muted">Loading incidents...</div>
    </div>
</div>

<template id="securityIncidentsCardTemplate">
    <div class="col-12 col-lg-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 incident-card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <span class="badge" data-fill="action"></span>
                    <span class="badge status-badge" data-fill="status"></span>
                </div>
                <div class="mt-2">
                    <div class="fw-semibold" data-fill="username"></div>
                    <div class="small text-muted mt-1">
                        <span class="me-2"><i class="bi bi-box me-1"></i><span data-fill="entity"></span></span>
                        <span><i class="bi bi-hash me-1"></i><span data-fill="entity_id"></span></span>
                    </div>
                    <div class="small text-muted text-break mt-1" data-fill="details"></div>
                    <div class="small text-muted mt-2"><i class="bi bi-clock me-1"></i><span data-fill="created_at"></span></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/security_incidents.js?v=<?= asset_version('js/pages/system/security_incidents.js') ?>"></script>