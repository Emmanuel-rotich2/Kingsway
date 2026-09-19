<?php
/** Kingsway System Administrator: SMS Provider Configuration (system domain). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-phone me-2 text-primary"></i>SMS Provider Configuration</h2>
            <p class="text-muted mb-0">Configure and test your SMS gateway provider settings.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-secondary btn-sm" id="smsExportCsv"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
            <button class="btn btn-outline-secondary btn-sm" id="smsPrint"><i class="bi bi-printer me-1"></i>Print/PDF</button>
            <button class="btn btn-primary btn-sm" id="smsRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
        </div>
    </div>
    <div id="smsState" class="alert alert-info" role="status"><i class="bi bi-hourglass-split me-1"></i>Loading SMS configuration...</div>
    <div class="row g-4" id="smsConfigPanel">
        <div class="col-12"><div class="text-center py-5 text-muted">Loading...</div></div>
    </div>
    <div class="mt-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">SMS Provider Fields</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive"><table class="table table-sm mb-0" id="smsFieldsTable">
                    <thead><tr><th>Setting</th><th>Value</th><th>Source</th><th>Actions</th></tr></thead>
                    <tbody id="smsFieldsBody"><tr><td colspan="4" class="text-muted">Loading...</td></tr></tbody>
                </table></div>
            </div>
        </div>
    </div>
    <div class="mt-3"><button class="btn btn-primary" id="smsSaveBtn"><i class="bi bi-check-lg me-1"></i>Save SMS Settings</button></div>
    <hr class="my-4">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="bi bi-activity me-1"></i>Test Connection</h6></div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4"><label class="form-label fw-semibold">Test Phone Number</label><input type="text" class="form-control" id="smsTestPhone" placeholder="2547XXXXXXXX" value=""></div>
                <div class="col-md-3"><button class="btn btn-outline-primary" id="smsTestBtn"><i class="bi bi-lightning me-1"></i>Test SMS (Balance Check)</button></div>
                <div class="col-md-5"><div id="smsTestResult" class="text-muted small"></div></div>
            </div>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/manage_sms_configurations.js?v=<?= asset_version('js/pages/system/manage_sms_configurations.js') ?>"></script>