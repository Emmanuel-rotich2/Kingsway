<?php
/** Kingsway System Administrator: Route Access Rules (role-augmented). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="routeAccessRulesPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-shield-lock text-success me-2"></i>Route Access Rules</h2>
            <p class="text-muted mb-0">Per-route access policies resolved by the authorization layer.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="routeAccessRulesExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="routeAccessRulesPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="routeAccessRulesCreateBtn">
                <i class="bi bi-plus-lg me-1"></i> New rule
            </button>
            <button type="button" class="btn btn-outline-secondary" id="routeAccessRulesRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="routeAccessRulesState" role="status" aria-live="polite">
        Loading route access rules...
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid var(--bs-success)">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5 col-xl-4">
                    <label class="form-label small text-muted mt-2 mb-1" for="routeAccessRulesSearch">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="routeAccessRulesSearch" type="search" maxlength="200" placeholder="Route, role or policy" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg mt-2">
                    <span class="text-muted small" id="routeAccessRulesCount">No rules loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Route</th>
                        <th scope="col">Role</th>
                        <th scope="col">Policy</th>
                        <th scope="col">Method</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="routeAccessRulesTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">Loading route access rules...</td>
                    </tr>
                </tbody>
                <template id="routeAccessRulesRowTemplate">
                    <tr>
                        <td class="text-nowrap" data-fill="id"></td>
                        <td class="text-break"><code data-fill="route"></code></td>
                        <td><span class="badge bg-light text-dark border" data-fill="role_name"></span></td>
                        <td><span class="badge" data-fill="policy"></span></td>
                        <td class="text-nowrap" data-fill="method"></td>
                        <td><span class="badge" data-fill="is_active"></span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" data-action="edit" title="Edit rule">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" data-action="delete" title="Delete rule">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </table>
        </div>

        <div class="card-footer bg-white d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span class="text-muted small">Route Access Rules</span>
            <nav aria-label="Route access rules pages">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="routeAccessRulesPreviousPage">
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </button>
                    <span class="btn btn-outline-secondary disabled" id="routeAccessRulesPageIndicator">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline-secondary" id="routeAccessRulesNextPage">
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="routeAccessRulesModal" tabindex="-1" aria-labelledby="routeAccessRulesModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="routeAccessRulesForm" novalidate>
                <input type="hidden" id="routeAccessRulesEditId">
                <div class="modal-header">
                    <h5 class="modal-title" id="routeAccessRulesModalTitle">New Route Access Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="routeAccessRulesRoute">Route</label>
                            <input type="text" class="form-control font-monospace" id="routeAccessRulesRoute" required maxlength="200" placeholder="/api/reports/nlq">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="routeAccessRulesMethod">Method</label>
                            <input type="text" class="form-control font-monospace" id="routeAccessRulesMethod" maxlength="10" placeholder="GET">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="routeAccessRulesRoleName">Role</label>
                            <input type="text" class="form-control" id="routeAccessRulesRoleName" placeholder="role_name or *">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="routeAccessRulesPolicy">Policy</label>
                            <select class="form-select" id="routeAccessRulesPolicy">
                                <option value="allow">Allow</option>
                                <option value="deny">Deny</option>
                                <option value="role_only">Role only</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="routeAccessRulesActive" checked>
                                <label class="form-check-label" for="routeAccessRulesActive">Rule is active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="routeAccessRulesSaveBtn">Save rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/route_access_rules.js?v=<?= asset_version('js/pages/system/route_access_rules.js') ?>"></script>
