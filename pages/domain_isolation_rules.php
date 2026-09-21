<?php
/** Kingsway System Administrator: Domain Isolation Rules (dark-accent isolation panel). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="domainIsolationPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-lock text-dark me-2"></i>Domain Isolation Rules</h2>
            <p class="text-muted mb-0">Name-connectivity rules that isolate the system, buffer and log namespaces.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="domainIsolationExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="domainIsolationPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="domainIsolationRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="domainIsolationState" role="status" aria-live="polite">
        Loading isolation rules...
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid var(--bs-dark)">
        <div class="card-header bg-light">
            <div class="row g-2 align-items-center">
                <div class="col-lg-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="domainIsolationSearch" type="search" maxlength="200" placeholder="Rule or description" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg text-lg-end mt-2 mt-lg-0">
                    <span class="text-muted small" id="domainIsolationCount">No rules loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Rule</th>
                        <th scope="col">Description</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="domainIsolationTableBody">
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">Loading isolation rules...</td>
                    </tr>
                </tbody>
                <template id="domainIsolationRowTemplate">
                    <tr>
                        <td class="text-wrap"><strong data-fill="key"></strong></td>
                        <td class="text-wrap" data-fill="description"></td>
                        <td><span class="badge" data-fill="status"></span></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-action="edit" title="Edit rule">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                </template>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="domainIsolationModal" tabindex="-1" aria-labelledby="domainIsolationModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="domainIsolationForm" novalidate>
                <input type="hidden" id="domainIsolationEditKey">
                <div class="modal-header">
                    <h5 class="modal-title" id="domainIsolationModalTitle">Edit Isolation Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" id="domainIsolationRuleLabel">Rule</label>
                        <div class="text-muted small" id="domainIsolationRuleDescription"></div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="domainIsolationEnabled">
                        <label class="form-check-label" for="domainIsolationEnabled">Rule is enforced</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="domainIsolationNotes">Notes</label>
                        <textarea class="form-control" id="domainIsolationNotes" rows="4" maxlength="1000" placeholder="Why is this rule in place?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/domain_isolation_rules.js?v=<?= asset_version('js/pages/system/domain_isolation_rules.js') ?>"></script>
