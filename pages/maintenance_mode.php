<?php
/** Kingsway System Administrator: Maintenance Mode (status banner). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="maintenanceModePage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">Maintenance Mode</h2>
            <p class="text-muted mb-0">Schedule and control maintenance windows.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="maintenanceModeExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="maintenanceModePrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="maintenanceModeRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="card mb-4 border-0 shadow-sm" id="maintenanceModeStatusCard">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <span class="maintenance-status-icon rounded-3 d-flex align-items-center justify-content-center display-6"><i class="bi bi-tools"></i></span>
            <div class="flex-grow-1">
                <div class="h5 mb-1" id="maintenanceModeStatusLabel">Loading maintenance state...</div>
                <div class="text-muted small" id="maintenanceModeStatusSub">Reading configured maintenance window settings.</div>
            </div>
            <span class="badge bg-secondary fs-6" id="maintenanceModeStatusBadge">…</span>
        </div>
    </div>

    <div class="card border-primary-subtle bg-light mb-4" data-maintenance-ai>
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <div>
                    <strong><i class="bi bi-stars text-primary me-1"></i>Facilities maintenance assistant</strong>
                    <div class="small text-muted">Reviews aggregate equipment and vehicle maintenance signals. Staff retain safety, urgency, vendor, cost, and work-order authority.</div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="queueAiFacilitiesReview"><i class="bi bi-stars me-1"></i>Review maintenance</button>
            </div>
            <div id="aiFacilitiesReviews" class="row g-2 mt-2"></div>
        </div>
    </div>

    <div class="alert alert-info" id="maintenanceModeState" role="status" aria-live="polite">
        Loading maintenance settings...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-uppercase text-muted">Settings</span>
            <div class="input-group input-group-sm ms-auto" style="max-width: 300px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input class="form-control" id="maintenanceModeSearch" type="search" maxlength="200" placeholder="Key, value or description" autocomplete="off">
            </div>
            <span class="text-muted small" id="maintenanceModeCount">No settings loaded</span>
        </div>
        <div id="maintenanceModeSettingsList">
            <div class="text-center py-5 text-muted">Loading settings...</div>
        </div>
    </div>
</div>

<template id="maintenanceModeSettingTemplate">
    <div class="d-flex flex-wrap gap-3 px-3 py-3 border-bottom align-items-center setting-row">
        <div class="flex-grow-1 min-w-50">
            <code class="text-break" data-fill="key"></code>
            <div class="text-break mt-1" data-fill="value"></div>
            <div class="small text-muted text-break" data-fill="description"></div>
        </div>
        <button type="button" class="btn btn-outline-info btn-sm" data-action="edit" title="Edit setting">
            <i class="bi bi-pencil me-1"></i> Edit
        </button>
    </div>
</template>

<div class="modal fade" id="maintenanceModeModal" tabindex="-1" aria-labelledby="maintenanceModeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="maintenanceModeForm" novalidate>
                <input type="hidden" id="maintenanceModeEditId">
                <div class="modal-header">
                    <h5 class="modal-title" id="maintenanceModeModalTitle">Edit Setting</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="maintenanceModeKey">Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="maintenanceModeKey" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="maintenanceModeDescription">Description</label>
                            <input type="text" class="form-control" id="maintenanceModeDescription" placeholder="Human-readable purpose">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="maintenanceModeValue">Value <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="maintenanceModeValue" rows="4" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="maintenanceModeSaveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/maintenance_mode.js?v=<?= asset_version('js/pages/system/maintenance_mode.js') ?>"></script>
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/ai_facilities_review.js?v=<?= asset_version('js/pages/ai_facilities_review.js') ?>"></script>