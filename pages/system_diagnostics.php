<?php
/** Kingsway System Administrator: System Diagnostics and operations console. */

?>
<div class="container-fluid py-4" id="systemDiagnosticsPage">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 mb-1">System Diagnostics</h2>
            <p class="text-muted mb-0">Runtime diagnostics, operational signals and staff adoption snapshots for the platform.</p>
        </div>
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Diagnostics actions">
            <button type="button" class="btn btn-outline-secondary" id="systemDiagnosticsExportCsvBtn" title="Export to CSV">
                <i aria-hidden="true" class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="systemDiagnosticsPrintBtn" title="Print / save as PDF">
                <i aria-hidden="true" class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="systemDiagnosticsRefreshBtn">
                <i aria-hidden="true" class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </header>

    <div class="alert alert-info" id="systemDiagnosticsState" role="status" aria-live="polite">
        Loading diagnostics...
    </div>

    <div class="card border-0 shadow-sm mb-4" id="systemOperationsPanel">
        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <strong>Operations &amp; Observability</strong>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-ops-refresh>
                    <i aria-hidden="true" class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
                <button type="button" class="btn btn-success btn-sm" data-ops-generate>
                    <i aria-hidden="true" class="bi bi-stars me-1"></i> Generate AI review
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
                    <i aria-hidden="true" class="bi bi-filetype-csv me-1"></i> Export CSV
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-ops-print>
                    <i aria-hidden="true" class="bi bi-printer me-1"></i> Print / PDF
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
                        <tr id="systemOperationsRowsState"><td class="text-center text-muted py-4" colspan="2">Loading...</td></tr>
                    </tbody>
                </table>
                <template id="systemOperationsRowTemplate"><tr><td class="font-monospace small" data-fill="signature"></td><td><span class="badge text-bg-secondary" data-fill="count"></span></td></tr></template>
            </div>
            <div class="mt-3" id="systemOperationsDrafts"></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4" id="systemSecurityAiPanel">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Security signals assistant</strong>
            <button type="button" class="btn btn-outline-danger btn-sm" data-security-generate><i aria-hidden="true" class="bi bi-shield-exclamation me-1"></i> Generate advisory review</button>
        </div>
        <div class="card-body"><div class="small text-muted" data-security-state>Loading identity-free security aggregates...</div><div class="row g-3 mt-1" data-security-kpis></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4" id="systemUsagePanel">
        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <strong>Staff usage &amp; adoption</strong>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-usage-refresh><i aria-hidden="true" class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
                <button type="button" class="btn btn-success btn-sm" data-usage-generate><i aria-hidden="true" class="bi bi-stars me-1"></i> Generate AI review</button>
            </div>
        </div>
        <div class="card-body">
            <div class="alert small text-muted mb-3" id="systemUsageState" role="status">Loading usage analytics...</div>
            <div class="row g-3 mb-3" id="systemUsageKpis"></div>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><h6 class="mb-2">Top API routes (24h)</h6><div class="table-responsive"><table class="table table-sm mb-0"><tbody id="systemUsageRoutes"></tbody></table></div></div>
                <div class="col-md-4"><h6 class="mb-2">Top pages (24h)</h6><div class="table-responsive"><table class="table table-sm mb-0"><tbody id="systemUsagePages"></tbody></table></div></div>
                <div class="col-md-4"><h6 class="mb-2">Active users by role</h6><div class="table-responsive"><table class="table table-sm mb-0"><tbody id="systemUsageRoles"></tbody></table></div></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><h6 class="mb-2">Failed requests by role</h6><div class="table-responsive"><table class="table table-sm mb-0"><tbody id="systemUsageErrorRoles"></tbody></table></div></div>
                <div class="col-md-6"><h6 class="mb-2">Device &amp; IP rotation signals</h6><div class="table-responsive"><table class="table table-sm mb-0"><tbody id="systemUsageDevices"></tbody></table></div></div>
            </div>
            <div class="row g-2" id="systemUsageDrafts"></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-center">
                <div class="col-lg-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i aria-hidden="true" class="bi bi-search"></i></span>
                        <input class="form-control" id="systemDiagnosticsSearch" type="search" maxlength="200" placeholder="Key, value or status" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg text-lg-end mt-2 mt-lg-0">
                    <span class="text-muted small" id="systemDiagnosticsCount">No diagnostics loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Runtime diagnostics</caption>
                <thead>
                    <tr>
                        <th scope="col">Key</th>
                        <th scope="col">Value</th>
                        <th scope="col">State</th>
                    </tr>
                </thead>
                <tbody id="systemDiagnosticsTableBody">
                    <tr id="systemDiagnosticsTableState">
                        <td colspan="3" class="text-center py-5 text-muted">Loading diagnostics...</td>
                    </tr>
                </tbody>
                <template id="systemDiagnosticsRowTemplate">
                    <tr>
                        <td class="text-break"><code data-fill="key"></code></td>
                        <td class="text-break text-muted" data-fill="value"></td>
                        <td><span class="badge" data-fill="state"></span></td>
                    </tr>
                </template>
            </table>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/system_diagnostics.js?v=<?= asset_version('js/pages/system/system_diagnostics.js') ?>"></script>
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/system_operations_assistant.js?v=<?= asset_version('js/pages/system/system_operations_assistant.js') ?>"></script>
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/system_security_assistant.js?v=<?= asset_version('js/pages/system/system_security_assistant.js') ?>"></script>
<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/system_usage_assistant.js?v=<?= asset_version('js/pages/system/system_usage_assistant.js') ?>"></script>