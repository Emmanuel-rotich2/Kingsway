<?php
/** Kingsway System Administrator: Time-Bound Access (amber-accent clock panel). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="timeBoundAccessPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-hourglass-split text-warning me-2"></i>Time-Bound Access</h2>
            <p class="text-muted mb-0">Temporary access grants that expire, with extension and revocation controls.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="timeBoundAccessExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="timeBoundAccessPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="timeBoundAccessRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="timeBoundAccessState" role="status" aria-live="polite">
        Loading access grants...
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid var(--bs-warning)">
        <div class="card-header bg-light">
            <div class="row g-2 align-items-center">
                <div class="col-lg-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="timeBoundAccessSearch" type="search" maxlength="200" placeholder="User, role or reason" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg text-lg-end mt-2 mt-lg-0">
                    <span class="text-muted small" id="timeBoundAccessCount">No grants loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">User</th>
                        <th scope="col">Role / Scope</th>
                        <th scope="col">Granted</th>
                        <th scope="col">Expires</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="timeBoundAccessTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">Loading access grants...</td>
                    </tr>
                </tbody>
                <template id="timeBoundAccessRowTemplate">
                    <tr>
                        <td class="text-nowrap" data-fill="id"></td>
                        <td class="text-wrap"><strong data-fill="username"></strong></td>
                        <td class="text-wrap small" data-fill="grant"></td>
                        <td class="text-nowrap text-muted" data-fill="granted_at"></td>
                        <td class="text-nowrap" data-fill="expires_at"></td>
                        <td><span class="badge" data-fill="status"></span></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-action="edit" title="Extend or manage">
                                <i class="bi bi-hourglass-split"></i>
                            </button>
                        </td>
                    </tr>
                </template>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="timeBoundAccessModal" tabindex="-1" aria-labelledby="timeBoundAccessModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="timeBoundAccessForm" novalidate>
                <input type="hidden" id="timeBoundAccessEditId">
                <div class="modal-header">
                    <h5 class="modal-title" id="timeBoundAccessModalTitle">Manage Access Grant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="timeBoundAccessExpiry">Expires at</label>
                        <input type="datetime-local" class="form-control" id="timeBoundAccessExpiry" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="timeBoundAccessReason">Reason</label>
                        <textarea class="form-control" id="timeBoundAccessReason" rows="3" maxlength="500" placeholder="Why is this access time-bound?"></textarea>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="timeBoundAccessActive" checked>
                        <label class="form-check-label" for="timeBoundAccessActive">Grant is active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save grant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/time_bound_access.js?v=<?= asset_version('js/pages/system/time_bound_access.js') ?>"></script>
