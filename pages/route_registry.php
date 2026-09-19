<?php
/** Kingsway System Administrator: Route Registry (method-augmented). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="routeRegistryPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-diagram-3 text-primary me-2"></i>Route Registry</h2>
            <p class="text-muted mb-0">Maintain the canonical application route registry. CRUD controls below.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="routeRegistryExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="routeRegistryPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="routeRegistryRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
            <button type="button" class="btn btn-primary" id="routeRegistryCreateBtn">
                <i class="bi bi-plus-lg me-1"></i> Add route
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="routeRegistryState" role="status" aria-live="polite">
        Loading route registry...
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid var(--bs-primary)">
        <div class="card-header bg-light">
            <div class="row g-2 align-items-center">
                <div class="col-lg-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="routeRegistrySearch" type="search" maxlength="200" placeholder="Method, path, controller or description" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg text-lg-end mt-2 mt-lg-0">
                    <span class="text-muted small" id="routeRegistryCount">No routes loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Method</th>
                        <th scope="col">Path</th>
                        <th scope="col">Controller</th>
                        <th scope="col">Active</th>
                        <th scope="col">Description</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="routeRegistryTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">Loading routes...</td>
                    </tr>
                </tbody>
                <template id="routeRegistryRowTemplate">
                    <tr>
                        <td class="text-nowrap" data-fill="id"></td>
                        <td><span class="badge" data-fill="method"></span></td>
                        <td><code class="text-break" data-fill="path"></code></td>
                        <td class="text-break" data-fill="controller"></td>
                        <td><span class="badge" data-fill="isActive"></span></td>
                        <td data-fill="description"></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-info" data-action="edit" title="Edit route">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" data-action="delete" title="Delete route">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </table>
        </div>

        <div class="card-footer bg-light d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span class="text-muted small">Route Registry</span>
            <nav aria-label="Route registry pages">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="routeRegistryPreviousPage">
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </button>
                    <span class="btn btn-outline-secondary disabled" id="routeRegistryPageIndicator">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline-secondary" id="routeRegistryNextPage">
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="routeRegistryModal" tabindex="-1" aria-labelledby="routeRegistryModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="routeRegistryForm" novalidate>
                <input type="hidden" id="routeRegistryEditId">
                <div class="modal-header">
                    <h5 class="modal-title" id="routeRegistryModalTitle">Add Route</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="routeRegistryMethod">HTTP Method <span class="text-danger">*</span></label>
                            <select class="form-select" id="routeRegistryMethod" required>
                                <option value="">Select method</option>
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="routeRegistryPath">Path <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="routeRegistryPath" placeholder="/api/example" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="routeRegistryController">Controller</label>
                            <input type="text" class="form-control" id="routeRegistryController" placeholder="Controller@method">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="routeRegistryMiddleware">Middleware</label>
                            <input type="text" class="form-control" id="routeRegistryMiddleware" placeholder="auth,throttle">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="routeRegistryIsActive">Active</label>
                            <select class="form-select" id="routeRegistryIsActive">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="routeRegistryDescription">Description</label>
                            <input type="text" class="form-control" id="routeRegistryDescription" placeholder="Short description">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="routeRegistrySaveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/route_registry.js?v=<?= asset_version('js/pages/system/route_registry.js') ?>"></script>