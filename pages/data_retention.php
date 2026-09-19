<?php
/** Kingsway System Administrator: Data Retention. */
?>
<div class="container-fluid py-4" id="dataRetentionPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Data Retention</h2>
            <p class="text-muted mb-0">Retention periods that govern how long school records are kept before purging.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="dataRetentionExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="dataRetentionPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="dataRetentionRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="dataRetentionState" role="status" aria-live="polite">
        Loading retention settings...
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="h6 mb-0">Retention periods</h3>
                    <span class="badge text-bg-light border" id="dataRetentionStatus">Not loaded</span>
                </div>
                <div class="card-body">
                    <div class="text-center py-5 text-muted" id="dataRetentionFormContainer">
                        Loading retention settings...
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="h6 mb-0">About retention</h3>
                </div>
                <div class="card-body small text-muted">
                    <p class="mb-2">Retention periods are expressed in months. The purge job applies the period
                        from the record's last activity date, subject to expiry controls.</p>
                    <ul class="mb-0 list-unstyled">
                        <li class="mb-1"><i class="bi bi-shield-check text-success me-2"></i>Learner records retain at least the DPA-prescribed period.</li>
                        <li class="mb-1"><i class="bi bi-shield-check text-success me-2"></i>Financial records are never auto-purged.</li>
                        <li class="mb-1"><i class="bi bi-shield-check text-success me-2"></i>Journal files have their own file-level lifecycle.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/data_retention.js?v=<?= asset_version('js/pages/system/data_retention.js') ?>"></script>