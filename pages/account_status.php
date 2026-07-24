<?php
/**
 * System Administrator — Account Status
 * Controller: js/pages/account_status.js
 */
?>
<div class="container-fluid py-4" id="accountStatusPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Account Status</h2>
            <p class="text-muted mb-0">
                Activate, suspend or unlock accounts and control first-login password changes.
            </p>
        </div>
        <button type="button" class="btn btn-outline-secondary" id="refreshAccountStatusBtn">
            <i class="fas fa-sync-alt me-1"></i> Refresh
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
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input
                    class="form-control"
                    id="searchAccountStatus"
                    type="search"
                    placeholder="Search name, username, email or status"
                    autocomplete="off"
                >
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead id="accountStatusTableHead">
                    <tr><th>Loading</th></tr>
                </thead>
                <tbody id="accountStatusTableBody">
                    <tr>
                        <td class="text-center py-5 text-muted">Waiting for authentication...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white text-muted small" id="accountStatusCount"></div>
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
