<?php
/** Kingsway System Administrator: WhatsApp Provider Configuration (system domain). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-whatsapp me-2 text-info"></i>WhatsApp Provider Configuration</h2>
            <p class="text-muted mb-0">Configure and test your WhatsApp messaging gateway.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-secondary btn-sm" id="whatsappExportCsv"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
            <button class="btn btn-outline-secondary btn-sm" id="whatsappPrint"><i class="bi bi-printer me-1"></i>Print/PDF</button>
            <button class="btn btn-info btn-sm" id="whatsappRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
        </div>
    </div>
    <div id="whatsappState" class="alert alert-info" role="status"><i class="bi bi-hourglass-split me-1"></i>Loading WhatsApp configuration...</div>
    <div class="row g-4" id="whatsappConfigPanel">
        <div class="col-12"><div class="text-center py-5 text-muted">Loading...</div></div>
    </div>
    <div class="mt-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">WhatsApp Provider Fields</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive"><table class="table table-sm mb-0" id="whatsappFieldsTable">
                    <thead><tr><th>Setting</th><th>Value</th><th>Source</th><th>Actions</th></tr></thead>
                    <tbody id="whatsappFieldsBody"><tr><td colspan="4" class="text-muted">Loading...</td></tr></tbody>
                </table></div>
            </div>
        </div>
    </div>
    <div class="mt-3"><button class="btn btn-info" id="whatsappSaveBtn"><i class="bi bi-check-lg me-1"></i>Save WhatsApp Settings</button></div>
    <hr class="my-4">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="bi bi-activity me-1"></i>Test WhatsApp Connection</h6></div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><button class="btn btn-outline-info" id="whatsappTestBtn"><i class="bi bi-lightning me-1"></i>Test Connection</button></div>
                <div class="col-md-9"><div id="whatsappTestResult" class="text-muted small"></div></div>
            </div>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/manage_whatsapp_configurations.js?v=<?= asset_version('js/pages/system/manage_whatsapp_configurations.js') ?>"></script>