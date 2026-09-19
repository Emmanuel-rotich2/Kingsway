<?php
/** Kingsway System Administrator: Dashboard Registry (secondary-accent grid). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="dashboardRegistryPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-speedometer2 text-secondary me-2"></i>Dashboard Registry</h2>
            <p class="text-muted mb-0">Register dashboard routes and their role assignments.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="dashboardRegistryExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="dashboardRegistryPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="dashboardRegistryRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
            <button type="button" class="btn btn-primary" id="dashboardRegistryCreateBtn">
                <i class="bi bi-plus-lg me-1"></i> Add dashboard
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="dashboardRegistryState" role="status" aria-live="polite">
        Loading dashboards...
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid var(--bs-secondary)">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5 col-xl-4">
                    <label class="form-label small text-muted mt-2 mb-1" for="dashboardRegistrySearch">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="dashboardRegistrySearch" type="search" maxlength="200" placeholder="Key, name, domain or status" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg mt-2">
                    <span class="text-muted small" id="dashboardRegistryCount">No dashboards loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Key</th>
                        <th scope="col">Name</th>
                        <th scope="col">Domain</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="dashboardRegistryTableBody">
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">Loading dashboards...</td>
                    </tr>
                </tbody>
                <template id="dashboardRegistryRowTemplate">
                    <tr>
                        <td class="text-nowrap" data-fill="id"></td>
                        <td class="fw-semibold text-break" data-fill="key"></td>
                        <td class="text-break" data-fill="name"></td>
                        <td><span class="badge" data-fill="domain"></span></td>
                        <td><span class="badge" data-fill="status"></span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-info" data-action="edit" title="Edit dashboard">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" data-action="delete" title="Delete dashboard">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </table>
        </div>

        <div class="card-footer bg-white d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span class="text-muted small">Dashboard Registry</span>
            <nav aria-label="Dashboard registry pages">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="dashboardRegistryPreviousPage">
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </button>
                    <span class="btn btn-outline-secondary disabled" id="dashboardRegistryPageIndicator">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline-secondary" id="dashboardRegistryNextPage">
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="dashboardRegistryModal" tabindex="-1" aria-labelledby="dashboardRegistryModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="dashboardRegistryForm" novalidate>
                <input type="hidden" id="dashboardRegistryEditId">
                <div class="modal-header">
                    <h5 class="modal-title" id="dashboardRegistryModalTitle">Add Dashboard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="dashboardRegistryKey">Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dashboardRegistryKey" placeholder="e.g. director_dashboard" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="dashboardRegistryName">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dashboardRegistryName" placeholder="Display name" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="dashboardRegistryDescription">Description</label>
                            <textarea class="form-control" id="dashboardRegistryDescription" rows="3"></textarea>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="dashboardRegistryDomain">Domain</label>
                            <select class="form-select" id="dashboardRegistryDomain">
                                <option value="SCHOOL">SCHOOL</option>
                                <option value="SYSTEM">SYSTEM</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="dashboardRegistryStatus">Status</label>
                            <select class="form-select" id="dashboardRegistryStatus">
                                <option value="active">active</option>
                                <option value="inactive">inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="dashboardRegistrySaveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/dashboard_registry.js?v=<?= asset_version('js/pages/system/dashboard_registry.js') ?>"></script>