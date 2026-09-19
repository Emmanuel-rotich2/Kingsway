<?php
/** Kingsway System Administrator: System Settings (tabbed form). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="systemSettingsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">System Settings</h2>
            <p class="text-muted mb-0">Maintain system-level key/value configuration, grouped by category.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="systemSettingsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="systemSettingsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="systemSettingsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="systemSettingsState" role="status" aria-live="polite">
        Loading system settings...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <ul class="nav nav-tabs card-header-tabs" id="systemSettingsCategoryTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" type="button" role="tab" data-category="all">All</button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <div class="input-group input-group-sm ms-auto" style="max-width: 300px">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" id="systemSettingsSearch" type="search" maxlength="200" placeholder="Key, value or description" autocomplete="off">
                </div>
                <span class="text-muted small" id="systemSettingsCount">No settings loaded</span>
            </div>
            <div id="systemSettingsPanel">
                <div class="text-center py-5 text-muted">Loading settings...</div>
            </div>
        </div>
    </div>
</div>

<template id="systemSettingsCategoryTemplate">
    <section class="mb-4">
        <h6 class="text-primary text-uppercase small mb-2"><i class="bi bi-tag me-1"></i><span data-fill="category"></span></h6>
        <div class="list-group" data-fill="settings"></div>
    </section>
</template>

<template id="systemSettingsRowTemplate">
    <div class="list-group-item d-flex flex-wrap gap-3 py-3 align-items-center setting-row">
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

<div class="modal fade" id="systemSettingsModal" tabindex="-1" aria-labelledby="systemSettingsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="systemSettingsForm" novalidate>
                <input type="hidden" id="systemSettingsEditId">
                <div class="modal-header">
                    <h5 class="modal-title" id="systemSettingsModalTitle">Edit Setting</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="systemSettingsKey">Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="systemSettingsKey" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="systemSettingsDescription">Description</label>
                            <input type="text" class="form-control" id="systemSettingsDescription" placeholder="Human-readable purpose">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="systemSettingsValue">Value <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="systemSettingsValue" rows="4" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="systemSettingsSaveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/system_settings.js?v=<?= asset_version('js/pages/system/system_settings.js') ?>"></script>