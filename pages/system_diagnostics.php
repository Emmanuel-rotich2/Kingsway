<?php
/** Kingsway System Administrator: System Diagnostics (red-accent diagnostic console). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="systemDiagnosticsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-activity text-danger me-2"></i>System Diagnostics</h2>
            <p class="text-muted mb-0">Runtime diagnostics emitted by the platform on request.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="systemDiagnosticsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="systemDiagnosticsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="systemDiagnosticsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="systemDiagnosticsState" role="status" aria-live="polite">
        Loading diagnostics...
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid var(--bs-danger)">
        <div class="card-header bg-light">
            <div class="row g-2 align-items-center">
                <div class="col-lg-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
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
                <thead>
                    <tr>
                        <th scope="col">Key</th>
                        <th scope="col">Value</th>
                        <th scope="col">State</th>
                    </tr>
                </thead>
                <tbody id="systemDiagnosticsTableBody">
                    <tr>
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