<?php
/** Kingsway System Administrator: Feature Flags (toggle card grid). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="featureFlagsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">Feature Flags</h2>
            <p class="text-muted mb-0">Runtime toggles that switch product features on, off, or to a rolling release.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="featureFlagsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="featureFlagsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="featureFlagsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="featureFlagsState" role="status" aria-live="polite">
        Loading feature flags...
    </div>

    <div class="row g-3 mb-3" id="featureFlagsStrip"></div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="btn-group btn-group-sm" role="group" aria-label="State filter" id="featureFlagsStateFilter">
            <button type="button" class="btn btn-outline-secondary active" data-state="all">All</button>
            <button type="button" class="btn btn-outline-secondary" data-state="enabled">Enabled</button>
            <button type="button" class="btn btn-outline-secondary" data-state="disabled">Disabled</button>
        </div>
        <div class="input-group input-group-sm ms-auto" style="max-width: 300px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="featureFlagsSearch" type="search" maxlength="200" placeholder="Flag name or description" autocomplete="off">
        </div>
        <span class="text-muted small" id="featureFlagsCount">No flags loaded</span>
    </div>

    <div class="row g-3" id="featureFlagsGrid">
        <div class="col-12 text-center py-5 text-muted">Loading feature flags...</div>
    </div>
</div>

<template id="featureFlagsCardTemplate">
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 flag-card">
            <div class="card-body d-flex flex-column">
                <div class="d-flex flex-wrap gap-2 align-items-start justify-content-between">
                    <div>
                        <strong class="font-monospace" data-fill="key"></strong>
                    </div>
                    <span class="flag-state-badge badge" data-fill="state"></span>
                </div>
                <div class="text-muted small flex-grow-1 mt-2" data-fill="description"></div>
                <div class="d-flex flex-wrap align-items-center justify-content-between mt-3 pt-2 border-top">
                    <span class="small text-muted" data-fill="rollout"></span>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input fs-5" type="checkbox" role="switch" data-flag-key="">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/feature_flags.js?v=<?= asset_version('js/pages/system/feature_flags.js') ?>"></script>