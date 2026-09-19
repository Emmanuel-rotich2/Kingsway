<?php
/** Kingsway System Administrator: Widget Registry (amber-accent catalogue). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="widgetRegistryPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-window-sidebar text-warning me-2"></i>Widget Registry</h2>
            <p class="text-muted mb-0">Registered dashboard widgets with their type, permission gate and status.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="widgetRegistryExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="widgetRegistryPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="widgetRegistryCreateBtn">
                <i class="bi bi-plus-lg me-1"></i> New widget
            </button>
            <button type="button" class="btn btn-outline-secondary" id="widgetRegistryRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="widgetRegistryState" role="status" aria-live="polite">
        Loading widgets...
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid var(--bs-warning)">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5 col-xl-4">
                    <label class="form-label small text-muted mt-2 mb-1" for="widgetRegistrySearch">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="widgetRegistrySearch" type="search" maxlength="200" placeholder="Name, type or permission" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg mt-2">
                    <span class="text-muted small" id="widgetRegistryCount">No widgets loaded</span>
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
                        <th scope="col">Type</th>
                        <th scope="col">Permission</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="widgetRegistryTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">Loading widgets...</td>
                    </tr>
                </tbody>
                <template id="widgetRegistryRowTemplate">
                    <tr>
                        <td class="text-nowrap" data-fill="id"></td>
                        <td class="text-break"><code data-fill="key"></code></td>
                        <td><strong data-fill="name"></strong></td>
                        <td><span class="badge" data-fill="type"></span></td>
                        <td class="text-nowrap small"><code data-fill="permission"></code></td>
                        <td><span class="badge" data-fill="status"></span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" data-action="edit" title="Edit widget">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" data-action="delete" title="Delete widget">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </table>
        </div>

        <div class="card-footer bg-white d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span class="text-muted small">Widget Registry</span>
            <nav aria-label="Widget pages">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="widgetRegistryPreviousPage">
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </button>
                    <span class="btn btn-outline-secondary disabled" id="widgetRegistryPageIndicator">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline-secondary" id="widgetRegistryNextPage">
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="widgetRegistryModal" tabindex="-1" aria-labelledby="widgetRegistryModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="widgetRegistryForm" novalidate>
                <input type="hidden" id="widgetRegistryEditId">
                <div class="modal-header">
                    <h5 class="modal-title" id="widgetRegistryModalTitle">New Widget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="widgetRegistryKey">Key</label>
                            <input type="text" class="form-control font-monospace" id="widgetRegistryKey" required maxlength="150" placeholder="attendance_today">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="widgetRegistryType">Type</label>
                            <select class="form-select" id="widgetRegistryType">
                                <option value="chart">Chart</option>
                                <option value="stat">Stat</option>
                                <option value="table">Table</option>
                                <option value="list">List</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="widgetRegistryName">Name</label>
                            <input type="text" class="form-control" id="widgetRegistryName" required maxlength="200" placeholder="Attendance today">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="widgetRegistryStatus">Status</label>
                            <select class="form-select" id="widgetRegistryStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="widgetRegistryPermission">Required permission</label>
                            <input type="text" class="form-control font-monospace" id="widgetRegistryPermission" maxlength="200" placeholder="analytics_dashboard_view or *">
                            <div class="form-text">Leave empty for widgets visible to all authenticated staff.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="widgetRegistryDescription">Description</label>
                            <textarea class="form-control" id="widgetRegistryDescription" rows="2" maxlength="500" placeholder="What does this widget show?"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="widgetRegistrySaveBtn">Save widget</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/widget_registry.js?v=<?= asset_version('js/pages/system/widget_registry.js') ?>"></script>