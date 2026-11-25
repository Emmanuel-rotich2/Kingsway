<?php
/** Kingsway System Administrator: System Health. */
?>
<div class="container-fluid" id="systemHealthPage">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="mb-1">System health</h2>
            <p class="text-muted mb-0">Inspect database, PHP, storage and service health.</p>
        </div>
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="System health actions">
            <button type="button" class="btn btn-outline-secondary" id="systemHealthExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="systemHealthPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1" aria-hidden="true"></i>Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="systemHealthRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh
            </button>
        </div>
    </header>

    <div class="alert alert-info" id="systemHealthState" role="status" aria-live="polite">
        Loading system health...
    </div>

    <div class="row g-3 mb-3" id="systemHealthKpis"></div>

    <div class="card mb-4" id="systemOperationsPanel">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h3 class="h6 mb-0">Operations and observability</h3>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-ops-refresh>
                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh
                </button>
                <button type="button" class="btn btn-success btn-sm" data-ops-generate>
                    <i class="bi bi-stars me-1" aria-hidden="true"></i>Generate AI review
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="alert small text-muted mb-3" id="systemOperationsState" role="status">
                Loading operations summary...
            </div>
            <div class="row g-3 mb-3" id="systemOperationsKpis"></div>
            <div class="alert alert-warning d-none" id="systemOperationsErrorScanNotice" role="status"></div>
            <div class="row g-3" id="systemOperationsQueue"></div>
            <hr class="my-3">
            <h6 class="mb-2">Recurring error / critical signals (24h)</h6>
            <div class="d-flex gap-2 justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-ops-csv>
                    <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Export CSV
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-ops-print>
                    <i class="bi bi-printer me-1" aria-hidden="true"></i>Print / PDF
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <caption class="visually-hidden">Recurring error and critical signatures in the last 24 hours</caption>
                    <thead>
                        <tr>
                            <th scope="col">Recurring signature</th>
                            <th scope="col">Count (24h)</th>
                        </tr>
                    </thead>
                    <tbody id="systemOperationsRows">
                        <tr id="systemOperationsRowsState"><td class="text-center text-muted py-4" colspan="2">Loading...</td></tr>
                    </tbody>
                </table>
                <template id="systemOperationsRowTemplate"><tr><td class="font-monospace small" data-fill="signature"></td><td><span class="badge text-bg-secondary" data-fill="count"></span></td></tr></template>
            </div>
            <div class="mt-3" id="systemOperationsDrafts"></div>
        </div>
    </div>

    <div class="card mb-4" id="systemSecurityAiPanel">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h3 class="h6 mb-0">Security signals assistant</h3>
            <button type="button" class="btn btn-outline-danger btn-sm" data-security-generate><i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>Generate advisory review</button>
        </div>
        <div class="card-body"><div class="small text-muted" data-security-state>Loading identity-free security aggregates...</div><div class="row g-3 mt-1" data-security-kpis></div></div>
    </div>

    <div class="card mb-4" id="systemUsagePanel">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h3 class="h6 mb-0">Staff usage and adoption</h3>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-usage-refresh><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh</button>
                <button type="button" class="btn btn-success btn-sm" data-usage-generate><i class="bi bi-stars me-1" aria-hidden="true"></i>Generate AI review</button>
            </div>
        </div>
        <div class="card-body">
            <div class="alert small text-muted mb-3" id="systemUsageState" role="status">Loading usage analytics...</div>
            <div class="row g-3 mb-3" id="systemUsageKpis"></div>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><h6 class="mb-2">Top API routes (24h)</h6><div class="table-responsive"><table class="table table-sm mb-0"><caption class="visually-hidden">Top API routes in the last 24 hours</caption><tbody id="systemUsageRoutes"></tbody></table></div></div>
                <div class="col-md-4"><h6 class="mb-2">Top pages (24h)</h6><div class="table-responsive"><table class="table table-sm mb-0"><caption class="visually-hidden">Top pages in the last 24 hours</caption><tbody id="systemUsagePages"></tbody></table></div></div>
                <div class="col-md-4"><h6 class="mb-2">Active users by role</h6><div class="table-responsive"><table class="table table-sm mb-0"><caption class="visually-hidden">Active users by role</caption><tbody id="systemUsageRoles"></tbody></table></div></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><h6 class="mb-2">Failed requests by role</h6><div class="table-responsive"><table class="table table-sm mb-0"><caption class="visually-hidden">Failed requests by role</caption><tbody id="systemUsageErrorRoles"></tbody></table></div></div>
                <div class="col-md-6"><h6 class="mb-2">Device &amp; IP rotation signals</h6><div class="table-responsive"><table class="table table-sm mb-0"><caption class="visually-hidden">Device and IP rotation signals</caption><tbody id="systemUsageDevices"></tbody></table></div></div>
            </div>
            <div class="row g-2" id="systemUsageDrafts"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h3 class="h6 mb-0">Health report</h3>
            <span class="badge text-bg-light border" id="systemHealthAsOf">Not loaded</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">System health checks</caption>
                <thead>
                    <tr>
                        <th scope="col">Check</th>
                        <th scope="col">Value</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody id="systemHealthTableBody">
                    <tr id="systemHealthTableState">
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
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/system_usage_assistant.js?v=<?= asset_version('js/pages/system/system_usage_assistant.js') ?>"></script>
