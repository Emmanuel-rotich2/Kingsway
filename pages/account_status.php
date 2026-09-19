<?php
/**
 * System Administrator — Account Status
 * Controller: js/pages/account_status.js
 */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') $appBase = '';
}
?>
<div class="container-fluid py-4" id="accountStatusPage">
    <style>
        #accountStatusPage .table-responsive { min-width: 0; overflow-x: auto; }
        #accountStatusPage table.table { border-collapse: separate; border-spacing: 0; }
        #accountStatusPage table.table thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: #fff;
            box-shadow: inset 0 -2px 0 #dee2e6;
        }
        #accountStatusPage table.table .col-actions {
            position: sticky;
            right: 0;
            z-index: 4;
            background: #fff;
            box-shadow: -6px 0 8px -6px rgba(0, 0, 0, 0.12);
            min-width: 220px;
        }
        #accountStatusPage table.table thead th.col-actions {
            z-index: 6;
            box-shadow: -6px -2px 8px -6px rgba(0, 0, 0, 0.12);
        }
        #accountStatusPage .accounts-pager .form-select { width: auto; min-width: 72px; }
        @media print {
            @page { size: A4 landscape; margin: 8mm; }
            body { background: #fff !important; }
            #accountStatusPage .no-print,
            #pageTopbar, #pageFooter, .sidebar, .navbar, .toast-container { display: none !important; }
            #accountStatusPage .card { border: none !important; box-shadow: none !important; }
            #accountStatusPage .table-responsive { overflow: visible !important; }
            #accountStatusPage table { width: 100%; }
            #accountStatusPage table.table thead th { position: static; }
            #accountStatusPage table.table .col-actions { position: static; box-shadow: none; }
            #accountStatusPage thead { display: table-header-group; }
            #accountStatusPage tr { break-inside: avoid; }
            #accountStatusPage td, #accountStatusPage th { font-size: 8pt; padding: 2px 4px; }
            #accountStatusPage .accounts-pager { display: none !important; }
        }
    </style>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Account Status</h2>
            <p class="text-muted mb-0">
                Activate, suspend or unlock accounts and control first-login password changes.
            </p>
        </div>
        <button type="button" class="btn btn-outline-secondary" id="refreshAccountStatusBtn">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
        </button>
    </div>

    <div class="row g-3 mb-4" id="accountStatusSummary"></div>

    <div
        class="alert alert-info"
        id="accountStatusState"
        role="status"
        aria-live="polite"
    >
        Waiting for authentication...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <strong>Account Status</strong>
            <div class="input-group" style="max-width: 360px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input
                    class="form-control"
                    id="searchAccountStatus"
                    type="search"
                    placeholder="Search name, username, email or status"
                    autocomplete="off"
                >
            </div>
        </div>
        <div class="card-body border-bottom py-2 bg-light" id="accountStatusBulkBar" hidden>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold small" id="accountStatusBulkCount">0 selected</span>
                <button type="button" class="btn btn-sm btn-outline-success" id="bulkActivateAccountsBtn">
                    <i class="bi bi-check2-circle me-1"></i> Activate
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning" id="bulkSuspendAccountsBtn">
                    <i class="bi bi-pause-circle me-1"></i> Suspend
                </button>
                <button type="button" class="btn btn-sm btn-outline-info" id="bulkUnlockAccountsBtn">
                    <i class="bi bi-unlock me-1"></i> Unlock
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="bulkForcePwAccountsBtn">
                    <i class="bi bi-shield-lock me-1"></i> Require password change
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkClearAccountsBtn">
                    Clear selection
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead id="accountStatusTableHead">
                    <tr>
                        <th class="text-center" style="width: 40px">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                data-select-all
                                aria-label="Select all matching accounts"
                            >
                        </th>
                        <th scope="col">Account</th>
                        <th scope="col">Status</th>
                        <th scope="col">Failed logins</th>
                        <th scope="col">Lock</th>
                        <th scope="col">Password change</th>
                        <th scope="col">Last login</th>
                        <th scope="col" class="text-end col-actions">Action</th>
                    </tr>
                </thead>
                <tbody id="accountStatusTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">Waiting for authentication...</td>
                    </tr>
                </tbody>
            </table>
            <template id="accountStatusRowTemplate">
                <tr>
                    <td class="text-center">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            aria-label="Select this account"
                        >
                    </td>
                    <td>
                        <strong data-row-fill="name"></strong>
                        <div class="small text-muted" data-row-fill="meta"></div>
                    </td>
                    <td>
                        <span class="badge" data-row-fill="statusBadge"></span>
                    </td>
                    <td data-row-fill="failedLogins"></td>
                    <td data-row-fill="lockState"></td>
                    <td data-row-fill="passwordChange"></td>
                    <td data-row-fill="lastLogin"></td>
                    <td class="text-end col-actions" data-row-fill="actions"></td>
                </tr>
            </template>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap align-items-center justify-content-between gap-2" id="accountStatusFooter">
            <span class="text-muted small" id="accountStatusCount"></span>
            <div class="d-flex align-items-center gap-2 accounts-pager" id="accountsPager">
                <label class="small text-muted mb-0" for="accountsPageSize">Rows</label>
                <select class="form-select form-select-sm" id="accountsPageSize" aria-label="Rows per page">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="0">All</option>
                </select>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="accountsPrevBtn" aria-label="Previous page" disabled>
                    <i class="bi bi-chevron-left"></i>
                </button>
                <span class="small text-muted" id="accountsPageInfo"></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="accountsNextBtn" aria-label="Next page" disabled>
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="accountStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="accountStatusForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="accountStatusModalTitle">Manage Account Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="accountStatusFormFields"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveAccountStatusBtn">
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/account_status.js?v=<?= asset_version('js/pages/account_status.js') ?>"></script>
