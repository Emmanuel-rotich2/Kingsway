<?php
/**
 * System Administrator — User Accounts
 * Controller: js/pages/manage_users.js
 */
?>
<div class="container-fluid py-4" id="manageUsersPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">User Accounts</h2>
            <p class="text-muted mb-0">
                Create and maintain technical user identities and their primary roles.
                Activation, suspension and unlocking are managed from Account Status.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" id="refreshUsersBtn">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
            <button type="button" class="btn btn-primary" id="createUserBtn">
                <i class="fas fa-user-plus me-1"></i> Create user
            </button>
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
            <div class="input-group" style="max-width: 360px">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input
                    class="form-control"
                    id="searchUsers"
                    type="search"
                    placeholder="Search name, username, email, role or status"
                    autocomplete="off"
                >
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead id="userAccountsTableHead">
                    <tr><th>Loading</th></tr>
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

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/manage_users.js?v=<?= asset_version('js/pages/manage_users.js') ?>"></script>
