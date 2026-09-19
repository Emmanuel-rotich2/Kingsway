<?php
/** Kingsway System Administrator: System Health. */
?>
<div class="container-fluid py-4" id="systemHealthPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">System Health</h2>
            <p class="text-muted mb-0">Inspect database, PHP, storage and service health.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="systemHealthExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="systemHealthPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="systemHealthRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="systemHealthState" role="status" aria-live="polite">
        Loading system health...
    </div>

    <div class="row g-3 mb-4" id="systemHealthKpis"></div>

    <div class="card border-0 shadow-sm mb-4" id="systemOperationsPanel">
        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <strong>Operations &amp; Observability</strong>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-ops-refresh>
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
                <button type="button" class="btn btn-success btn-sm" data-ops-generate>
                    <i class="bi bi-stars me-1"></i> Generate AI review
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="alert small text-muted mb-3" id="systemOperationsState" role="status">
                Loading operations summary...
            </div>
            <div class="row g-3 mb-3" id="systemOperationsKpis"></div>
            <div class="row g-3" id="systemOperationsQueue"></div>
            <hr class="my-3">
            <h6 class="mb-2">Recurring error / critical signals (24h)</h6>
            <div class="d-flex gap-2 justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-ops-csv>
                    <i class="bi bi-filetype-csv me-1"></i> Export CSV
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-ops-print>
                    <i class="bi bi-printer me-1"></i> Print / PDF
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Recurring signature</th>
                            <th scope="col">Count (24h)</th>
                        </tr>
                    </thead>
                    <tbody id="systemOperationsRows">
                        <tr><td class="text-center text-muted py-4" colspan="2">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-3" id="systemOperationsDrafts"></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4" id="systemSecurityAiPanel">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Security signals assistant</strong>
            <button type="button" class="btn btn-outline-danger btn-sm" data-security-generate><i class="bi bi-shield-exclamation me-1"></i> Generate advisory review</button>
        </div>
        <div class="card-body"><div class="small text-muted" data-security-state>Loading identity-free security aggregates...</div><div class="row g-3 mt-1" data-security-kpis></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h3 class="h6 mb-0">Health report</h3>
            <span class="badge text-bg-light border" id="systemHealthAsOf">Not loaded</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Check</th>
                        <th scope="col">Value</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody id="systemHealthTableBody">
                    <tr>
                        <td colspan="3" class="text-center py-5 text-muted">Loading health checks...</td>
                    </tr>
                </tbody>
                <template id="systemHealthRowTemplate">
                    <tr>
                        <td class="text-break"><strong data-fill="key"></strong></td>
                        <td class="text-break text-muted" data-fill="value"></td>
                        <td><span class="badge" data-fill="status"></span></td>
                    </tr>
                </template>
            </table>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/system_health.js?v=<?= asset_version('js/pages/system/system_health.js') ?>"></script>
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/system_operations_assistant.js?v=<?= asset_version('js/pages/system/system_operations_assistant.js') ?>"></script>
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/system_security_assistant.js?v=<?= asset_version('js/pages/system/system_security_assistant.js') ?>"></script>
