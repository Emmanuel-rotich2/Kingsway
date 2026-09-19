<?php
/** Kingsway System Administrator: Module Enablement (grid tiles). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="moduleEnablementPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">Module Enablement</h2>
            <p class="text-muted mb-0">Which application modules are active and available across the school.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="moduleEnablementExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="moduleEnablementPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="moduleEnablementRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="moduleEnablementState" role="status" aria-live="polite">
        Loading module enablement...
    </div>

    <div class="row g-3 mb-3" id="moduleEnablementStrip"></div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="btn-group btn-group-sm" role="group" aria-label="State filter" id="moduleEnablementStateFilter">
            <button type="button" class="btn btn-outline-secondary active" data-state="all">All</button>
            <button type="button" class="btn btn-outline-secondary" data-state="enabled">Enabled</button>
            <button type="button" class="btn btn-outline-secondary" data-state="disabled">Disabled</button>
        </div>
        <div class="input-group input-group-sm ms-auto" style="max-width: 300px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="moduleEnablementSearch" type="search" maxlength="200" placeholder="Module name or description" autocomplete="off">
        </div>
        <span class="text-muted small" id="moduleEnablementCount">No modules loaded</span>
    </div>

    <div class="row g-3" id="moduleEnablementGrid">
        <div class="col-12 text-center py-5 text-muted">Loading modules...</div>
    </div>
</div>

<template id="moduleEnablementTileTemplate">
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 module-tile">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="module-icon rounded-3 d-flex align-items-center justify-content-center"><i class="bi bi-box"></i></span>
                        <strong data-fill="name"></strong>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input fs-5" type="checkbox" role="switch" data-module-key="">
                    </div>
                </div>
                <div class="text-muted small flex-grow-1 mt-2" data-fill="description"></div>
                <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="badge" data-fill="status"></span>
                    <span class="small text-muted"><i class="bi bi-hdd me-1"></i><span data-fill="key"></span></span>
                </div>
            </div>
        </div>
    </div>
</template>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/module_enablement.js?v=<?= asset_version('js/pages/system/module_enablement.js') ?>"></script>