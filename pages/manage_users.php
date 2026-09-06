<?php
/**
 * System Administrator — User Accounts
 * Controller: js/pages/manage_users.js
 */
?>
<div class="container-fluid py-4" id="manageUsersPage">
    <style>
        #manageUsersPage .table-responsive { min-width: 0; }
        @media print {
            @page { size: A4 landscape; margin: 8mm; }
            body { background: #fff !important; }
            #manageUsersPage .no-print,
            #pageTopbar, #pageFooter, .sidebar, .navbar, .toast-container { display: none !important; }
            #manageUsersPage .card { border: none !important; box-shadow: none !important; }
            #manageUsersPage .table-responsive { overflow: visible !important; }
            #manageUsersPage table { width: 100%; }
            #manageUsersPage thead { display: table-header-group; }
            #manageUsersPage tr { break-inside: avoid; }
            #manageUsersPage td, #manageUsersPage th { font-size: 8pt; padding: 2px 4px; }
        }
    </style>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">User Accounts</h2>
            <p class="text-muted mb-0">
                Create and maintain technical user identities and their primary roles.
                Activation, suspension and unlocking are managed from Account Status.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" id="exportUsersBtn">
                <i class="bi bi-download me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="printUsersBtn">
                <i class="bi bi-printer me-1"></i> Print
            </button>
            <button type="button" class="btn btn-outline-secondary" id="refreshUsersBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
            <button type="button" class="btn btn-primary" id="createUserBtn">
                <i class="bi bi-person-plus me-1"></i> Create user
            </button>
        </div>
    </div>

    <div class="card border-warning shadow-sm mb-4" id="operatingModeCard">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h3 class="h5 mb-1">Environment and phase control</h3>
                    <p class="text-muted mb-0" id="operatingModeDescription">Loading the current environment phase...</p>
                </div>
                <span class="badge text-bg-secondary fs-6" id="operatingModeBadge">Loading</span>
            </div>
            <div class="row g-3 mt-2 align-items-end" id="environmentPhaseControls">
                <div class="col-md-4">
                    <label class="form-label mb-1" for="phaseHostSelect">Host (root from config)</label>
                    <select class="form-select" id="phaseHostSelect"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1" for="phaseSelect">Phase (database-driven)</label>
                    <select class="form-select" id="phaseSelect">
                        <option value="test">Test</option>
                        <option value="live">Live</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="col-md-3 form-check form-switch pt-4">
                    <input class="form-check-input" type="checkbox" id="autoLockSwitch">
                    <label class="form-check-label" for="autoLockSwitch">Auto-lock test accounts in release phase</label>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-primary w-100" id="applyPhaseBtn">
                        Apply phase
                    </button>
                </div>
            </div>
            <div class="row g-3 mt-1" id="testDataInventory"></div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="userAccountsSummary"></div>

    <div
        class="alert alert-info"
        id="userAccountsState"
        role="status"
        aria-live="polite"
    >
        Waiting for authentication...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <strong>User Accounts</strong>
            <div class="d-flex gap-2 align-items-center">
                <div class="input-group" style="max-width: 360px">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input
                        class="form-control"
                        id="searchUsers"
                        type="search"
                        placeholder="Search name, username, email, role or status"
                        autocomplete="off"
                    >
                </div>
            </div>
        </div>
        <div class="card-body border-bottom py-2 bg-light" id="bulkActionBar" hidden>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold small" id="bulkSelectedCount">0 selected</span>
                <button type="button" class="btn btn-sm btn-outline-success" id="bulkGrantBtn">
                    <i class="bi bi-check2-circle me-1"></i> Grant test access
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="bulkRevokeBtn">
                    <i class="bi bi-x-circle me-1"></i> Revoke test access
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="bulkRoleBtn">
                    <i class="bi bi-people me-1"></i> Assign role
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkClearBtn">
                    Clear selection
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead id="userAccountsTableHead">
                    <tr><th scope="col">Loading</th></tr>
                </thead>
                <tbody id="userAccountsTableBody">
                    <tr>
                        <td class="text-center py-5 text-muted">Waiting for authentication...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white text-muted small" id="userAccountsCount"></div>
    </div>
</div>

<div class="modal fade" id="userAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="userAccountForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="userAccountModalTitle">User Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="userAccountFormFields"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveUserBtn">
                        Save user
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign role to selected accounts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="bulkRoleCount"></p>
                <label class="form-label" for="bulkRoleSelect">Role</label>
                <select class="form-select" id="bulkRoleSelect">
                    <option value="">Select a role</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="bulkRoleApplyBtn">Assign role</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkGrantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="bulkGrantForm">
                <div class="modal-header">
                    <h5 class="modal-title">Grant test access</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" id="bulkGrantCount"></p>
                    <div class="mb-3">
                        <label class="form-label" for="bulkGrantPurpose">Testing purpose</label>
                        <input class="form-control" id="bulkGrantPurpose" type="text" maxlength="500" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="bulkGrantStartsAt">Starts at</label>
                            <input class="form-control" id="bulkGrantStartsAt" type="datetime-local" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="bulkGrantExpiresAt">Expires at</label>
                            <input class="form-control" id="bulkGrantExpiresAt" type="datetime-local" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="bulkGrantApplyBtn">Grant access</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkRevokeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Revoke test access</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="bulkRevokeCount"></p>
                <label class="form-label" for="bulkRevokeReason">Revocation reason</label>
                <input class="form-control" id="bulkRevokeReason" type="text" maxlength="500"
                       value="Feature test completed" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="bulkRevokeApplyBtn">Revoke access</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/manage_users.js?v=<?= asset_version('js/pages/manage_users.js') ?>"></script>