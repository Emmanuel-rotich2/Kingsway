<?php
/** Kingsway System Administrator: Permission Policies (blue-card variant). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="permissionPoliciesPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-file-earmark-lock text-info me-2"></i>Permission Policies</h2>
            <p class="text-muted mb-0">Named policy rules that govern how the permission engine evaluates access.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="permissionPoliciesExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="permissionPoliciesPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="permissionPoliciesCreateBtn">
                <i class="bi bi-plus-lg me-1"></i> New policy
            </button>
            <button type="button" class="btn btn-outline-secondary" id="permissionPoliciesRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="permissionPoliciesState" role="status" aria-live="polite">
        Loading permission policies...
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid var(--bs-info)">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5 col-xl-4">
                    <label class="form-label small text-muted mt-2 mb-1" for="permissionPoliciesSearch">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="permissionPoliciesSearch" type="search" maxlength="200" placeholder="Name, description or status" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg mt-2">
                    <span class="text-muted small" id="permissionPoliciesCount">No policies loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Name</th>
                        <th scope="col">Description</th>
                        <th scope="col">Rules</th>
                        <th scope="col">Status</th>
                        <th scope="col">Updated</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="permissionPoliciesTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">Loading permission policies...</td>
                    </tr>
                </tbody>
                <template id="permissionPoliciesRowTemplate">
                    <tr>
                        <td class="text-nowrap" data-fill="id"></td>
                        <td><strong data-fill="name"></strong></td>
                        <td class="text-wrap" data-fill="description"></td>
                        <td class="text-break"><code data-fill="rules"></code></td>
                        <td><span class="badge" data-fill="status"></span></td>
                        <td class="text-nowrap text-muted" data-fill="updated_at"></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" data-action="edit" title="Edit policy">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" data-action="delete" title="Delete policy">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </table>
        </div>

        <div class="card-footer bg-white d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span class="text-muted small">Permission Policies</span>
            <nav aria-label="Permission policies pages">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="permissionPoliciesPreviousPage">
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </button>
                    <span class="btn btn-outline-secondary disabled" id="permissionPoliciesPageIndicator">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline-secondary" id="permissionPoliciesNextPage">
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="permissionPoliciesModal" tabindex="-1" aria-labelledby="permissionPoliciesModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="permissionPoliciesForm" novalidate>
                <input type="hidden" id="permissionPoliciesEditId">
                <div class="modal-header">
                    <h5 class="modal-title" id="permissionPoliciesModalTitle">New Permission Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="permissionPoliciesName">Name</label>
                            <input type="text" class="form-control" id="permissionPoliciesName" required maxlength="150" placeholder="e.g. finance.review">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="permissionPoliciesStatus">Status</label>
                            <select class="form-select" id="permissionPoliciesStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="permissionPoliciesDescription">Description</label>
                            <textarea class="form-control" id="permissionPoliciesDescription" rows="2" maxlength="500" placeholder="What does this policy control?"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="permissionPoliciesRules">Rules (JSON)</label>
                            <textarea class="form-control font-monospace" id="permissionPoliciesRules" rows="5" placeholder='{ "allow": ["finance.*"], "deny": ["finance.refund"] }'></textarea>
                            <div class="form-text">Must be valid JSON. Plain rules like <code>*</code> or <code>module.action</code> are also accepted.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="permissionPoliciesSaveBtn">Save policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/permission_policies.js?v=<?= asset_version('js/pages/system/permission_policies.js') ?>"></script>
