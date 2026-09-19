<?php
/** Kingsway System Administrator: Rate Limiting Status. */
?>
<div class="container-fluid py-4" id="rateLimitingStatusPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Rate Limiting Status</h2>
            <p class="text-muted mb-0">Current rate-limit configuration and burst-aware bucket utilisation.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="rateLimitingStatusExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="rateLimitingStatusPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="rateLimitingStatusRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="rateLimitingStatusState" role="status" aria-live="polite">
        Loading rate limiting status...
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="h6 mb-0">Buckets</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Bucket / Scope</th>
                                <th scope="col">Limit</th>
                                <th scope="col">Window</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody id="rateLimitingStatusTableBody">
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">Loading buckets...</td>
                            </tr>
                        </tbody>
                        <template id="rateLimitingStatusRowTemplate">
                            <tr>
                                <td class="text-wrap"><strong data-fill="key"></strong></td>
                                <td class="text-nowrap" data-fill="limit"></td>
                                <td class="text-nowrap" data-fill="window"></td>
                                <td><span class="badge" data-fill="status"></span></td>
                            </tr>
                        </template>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="h6 mb-0">Global policy</h3>
                </div>
                <div class="card-body">
                    <dl class="row small mb-0" id="rateLimitingStatusPolicy">
                        <dt class="col-6">Enabled</dt>
                        <dd class="col-6">—</dd>
                        <dt class="col-6">Default per-user</dt>
                        <dd class="col-6">—</dd>
                        <dt class="col-6">Burst allowance</dt>
                        <dd class="col-6">—</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/rate_limiting_status.js?v=<?= asset_version('js/pages/system/rate_limiting_status.js') ?>"></script>