<?php
/** Kingsway System Administrator: Backups (dark-green archive dashboard). */

?>
<div class="container-fluid" id="backupsPage">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="mb-1"><i class="bi bi-database-check text-success me-2" aria-hidden="true"></i>Backups</h2>
            <p class="text-muted mb-0">Database and storage backups with on-demand snapshot creation.</p>
        </div>
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Backup actions">
            <button type="button" class="btn btn-outline-secondary" id="backupsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="backupsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1" aria-hidden="true"></i>Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="backupsCreateBtn">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create backup
            </button>
            <button type="button" class="btn btn-outline-secondary" id="backupsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh
            </button>
        </div>
    </header>

    <div class="alert alert-info" id="backupsState" role="status" aria-live="polite">
        Loading backups...
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-lg-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input class="form-control" id="backupsSearch" type="search" maxlength="200" placeholder="Filename or status" autocomplete="off" aria-label="Search backups">
                    </div>
                </div>
                <div class="col-12 col-lg text-lg-end">
                    <span class="text-muted small" id="backupsCount">No backups loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Available backups</caption>
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Filename</th>
                        <th scope="col">Size</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="backupsTableBody">
                    <tr id="backupsTableState">
                        <td colspan="6" class="text-center py-5 text-muted">Loading backups...</td>
                    </tr>
                </tbody>
                <template id="backupsRowTemplate">
                    <tr>
                        <td class="text-nowrap" data-fill="id"></td>
                        <td class="text-break"><code data-fill="filename"></code></td>
                        <td class="text-nowrap" data-fill="size"></td>
                        <td><span class="badge" data-fill="status"></span></td>
                        <td class="text-nowrap text-muted" data-fill="created_at"></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-danger" data-action="delete" title="Delete backup" aria-label="Delete backup">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </table>
        </div>

        <div class="card-footer d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span class="text-muted small">Backups</span>
            <nav aria-label="Backups pages">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="backupsPreviousPage">
                        <i class="bi bi-chevron-left me-1" aria-hidden="true"></i>Previous
                    </button>
                    <span class="btn btn-outline-secondary disabled" id="backupsPageIndicator">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline-secondary" id="backupsNextPage">
                        Next <i class="bi bi-chevron-right ms-1" aria-hidden="true"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="backupsModal" tabindex="-1" aria-labelledby="backupsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="backupsForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="backupsModalTitle">Create backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="backupsLabel">Label</label>
                        <input type="text" class="form-control" id="backupsLabel" placeholder="Optional backup label" maxlength="200">
                        <div class="form-text">A memorable label is optional; the snapshot will be taken immediately.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="backupsSaveBtn">Create backup</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/backups.js?v=<?= asset_version('js/pages/system/backups.js') ?>"></script>
