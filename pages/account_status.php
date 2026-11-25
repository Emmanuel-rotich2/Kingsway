<?php
/**
 * System Administrator — Account Status
 * Controller: js/pages/account_status.js
 */

?>
<div class="container-fluid py-4" id="accountStatusPage">
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/account-status.css?v=<?= asset_version('css/pages/account-status.css') ?>"
    >
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Account status</h2>
            <p class="text-muted mb-0">
                Activate, suspend or unlock accounts and control first-login password changes.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 no-print">
            <button
                type="button"
                class="btn btn-outline-secondary"
                data-kw-sa-export
                data-kw-sa-export-target="#accountStatusTable"
                data-kw-sa-export-name="account_status"
            >
                <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" data-kw-sa-print>
                <i class="bi bi-printer me-1" aria-hidden="true"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="refreshAccountStatusBtn">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i> Refresh
            </button>
        </div>
    </header>

    <div class="row g-3 mb-4" id="accountStatusSummary"></div>

    <div
        class="alert alert-info"
        id="accountStatusState"
        role="status"
        aria-live="polite"
    >
        Waiting for authentication...
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h3 class="h6 mb-0">Account status</h3>
            <div class="input-group" style="max-width: 360px">
                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input
                    class="form-control"
                    id="searchAccountStatus"
                    type="search"
                    placeholder="Search name, username, email or status"
                    aria-label="Search accounts"
                    autocomplete="off"
                >
            </div>
        </div>
        <div class="card-body border-bottom py-2 bg-light" id="accountStatusBulkBar" hidden>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold small" id="accountStatusBulkCount">0 selected</span>
                <button type="button" class="btn btn-sm btn-outline-success" id="bulkActivateAccountsBtn">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Activate
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning" id="bulkSuspendAccountsBtn">
                    <i class="bi bi-pause-circle me-1" aria-hidden="true"></i> Suspend
                </button>
                <button type="button" class="btn btn-sm btn-outline-info" id="bulkUnlockAccountsBtn">
                    <i class="bi bi-unlock me-1" aria-hidden="true"></i> Unlock
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="bulkForcePwAccountsBtn">
                    <i class="bi bi-shield-lock me-1" aria-hidden="true"></i> Require password change
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkClearAccountsBtn">
                    Clear selection
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="accountStatusTable">
                <caption class="visually-hidden">Account status and access controls</caption>
                <thead id="accountStatusTableHead">
                    <tr>
                        <th class="text-center" scope="col" style="width: 40px">
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
                    <tr id="accountStatusTableState">
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
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                <span class="small text-muted" id="accountsPageInfo"></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="accountsNextBtn" aria-label="Next page" disabled>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
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
                    <h5 class="modal-title" id="accountStatusModalTitle">Manage account status</h5>
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
