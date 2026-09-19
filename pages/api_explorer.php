<?php
/** Kingsway System Administrator: API Explorer (route directory). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="apiExplorerPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">API Explorer</h2>
            <p class="text-muted mb-0">Route directory of the registered application API, grouped by controller.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="apiExplorerExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="apiExplorerPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="apiExplorerRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="apiExplorerState" role="status" aria-live="polite">
        Loading API routes...
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="btn-group btn-group-sm" role="group" aria-label="Method filter" id="apiExplorerMethodFilter">
            <button type="button" class="btn btn-outline-secondary active" data-method="all">All</button>
            <button type="button" class="btn btn-outline-secondary" data-method="GET">GET</button>
            <button type="button" class="btn btn-outline-secondary" data-method="POST">POST</button>
            <button type="button" class="btn btn-outline-secondary" data-method="PUT">PUT</button>
            <button type="button" class="btn btn-outline-secondary" data-method="PATCH">PATCH</button>
            <button type="button" class="btn btn-outline-secondary" data-method="DELETE">DELETE</button>
        </div>
        <div class="input-group input-group-sm ms-auto" style="max-width: 320px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="apiExplorerSearch" type="search" maxlength="200" placeholder="Path, controller or middleware" autocomplete="off">
        </div>
        <span class="text-muted small" id="apiExplorerCount">No routes loaded</span>
    </div>

    <div id="apiExplorerDirectory">
        <div class="text-center py-5 text-muted">Loading routes...</div>
    </div>
</div>

<template id="apiExplorerGroupTemplate">
    <section class="card border-0 shadow-sm mb-3 route-group">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <strong class="text-break" data-fill="controller"></strong>
            <span class="badge bg-light text-dark border" data-fill="group_count"></span>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush" data-fill="rows"></div>
        </div>
    </section>
</template>

<template id="apiExplorerRouteTemplate">
    <div class="list-group-item d-flex flex-wrap gap-2 align-items-start py-2">
        <span class="badge method-badge mt-1" data-fill="method"></span>
        <code class="text-break flex-grow-1" data-fill="path"></code>
        <span class="badge mt-1" data-fill="isActive"></span>
        <span class="small text-muted mt-1 flex-basis-100" data-fill="middleware"></span>
    </div>
</template>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/api_explorer.js?v=<?= asset_version('js/pages/system/api_explorer.js') ?>"></script>