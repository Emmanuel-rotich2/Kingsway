<?php
/** Kingsway System Administrator: Security Incidents. */
?>
<div class="container-fluid py-4"
     data-system-admin-page
     data-resource="incidents"
     data-mode="crud"
     data-title="Security Incidents">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Security Incidents</h2>
            <p class="text-muted mb-0">Track security incidents from detection to resolution.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" data-system-refresh>
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
            <button type="button" class="btn btn-primary" data-system-create>
                <i class="fas fa-plus me-1"></i> Add record
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4" data-system-summary></div>

    <div class="alert alert-info" data-system-state role="status">
        Loading security incidents...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <strong>Security Incidents</strong>
            <div class="input-group" style="max-width: 360px">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input class="form-control" data-system-search placeholder="Search records">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead data-system-head>
                    <tr><th>Loading</th></tr>
                </thead>
                <tbody data-system-body>
                    <tr><td class="text-center py-5 text-muted">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white text-muted small" data-system-count></div>
    </div>
</div>

<div class="modal fade" id="systemAdminRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form data-system-form>
                <div class="modal-header">
                    <h5 class="modal-title" data-system-modal-title>Security Incidents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" data-system-form-fields></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" data-system-save>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/system_admin_console.js?v=<?= asset_version('js/pages/system/system_admin_console.js') ?>"></script>
