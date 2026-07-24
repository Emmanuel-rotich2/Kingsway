<?php
/**
 * System Administrator — Failed Login Attempts
 * Controller: js/pages/failed_login_attempts.js
 */
?>
<div class="container-fluid py-4" id="failedLoginAttemptsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Failed Login Attempts</h2>
            <p class="text-muted mb-0">
                Investigate rejected sign-in attempts, affected accounts and
                source addresses recorded by the authentication service.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button
                type="button"
                class="btn btn-outline-secondary"
                id="resetFailedLoginAttemptFiltersBtn"
            >
                <i class="fas fa-undo me-1"></i> Reset filters
            </button>
            <button
                type="button"
                class="btn btn-primary"
                id="refreshFailedLoginAttemptsBtn"
            >
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4" id="failedLoginAttemptsSummary"></div>

    <div
        class="alert alert-info"
        id="failedLoginAttemptsState"
        role="status"
        aria-live="polite"
    >
        Waiting for authentication...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-4">
                    <label
                        class="form-label small text-muted"
                        for="failedLoginAttemptSearch"
                    >
                        Search
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input
                            class="form-control"
                            id="failedLoginAttemptSearch"
                            type="search"
                            maxlength="200"
                            placeholder="Username, email, IP, reason or client"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="failedLoginAttemptReasonFilter"
                    >
                        Failure reason
                    </label>
                    <select class="form-select" id="failedLoginAttemptReasonFilter">
                        <option value="">All reasons</option>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="failedLoginAttemptDateFrom"
                    >
                        From date
                    </label>
                    <input
                        class="form-control"
                        id="failedLoginAttemptDateFrom"
                        type="date"
                    >
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="failedLoginAttemptDateTo"
                    >
                        To date
                    </label>
                    <input
                        class="form-control"
                        id="failedLoginAttemptDateTo"
                        type="date"
                    >
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="failedLoginAttemptPageSize"
                    >
                        Rows per page
                    </label>
                    <select class="form-select" id="failedLoginAttemptPageSize">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date and time</th>
                        <th>Account / identifier</th>
                        <th>IP address</th>
                        <th>Failure reason</th>
                        <th>Account security</th>
                        <th>Client</th>
                    </tr>
                </thead>
                <tbody id="failedLoginAttemptsTableBody">
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            Waiting for authentication...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="card-footer bg-white d-flex flex-wrap gap-3
                   justify-content-between align-items-center"
        >
            <span class="text-muted small" id="failedLoginAttemptsCount">
                No failed login attempts loaded
            </span>
            <nav aria-label="Failed login attempt pages">
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="failedLoginAttemptsPreviousPage"
                    >
                        <i class="fas fa-chevron-left me-1"></i> Previous
                    </button>
                    <span
                        class="btn btn-outline-secondary disabled"
                        id="failedLoginAttemptsPageIndicator"
                    >
                        Page 1 of 1
                    </span>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="failedLoginAttemptsNextPage"
                    >
                        Next <i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/failed_login_attempts.js?v=<?= asset_version('js/pages/failed_login_attempts.js') ?>"></script>
