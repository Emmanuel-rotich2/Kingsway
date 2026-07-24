<?php
/**
 * System Administrator — Authentication Logs
 * Controller: js/pages/authentication_logs.js
 */
?>
<div class="container-fluid py-4" id="authenticationLogsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Authentication Logs</h2>
            <p class="text-muted mb-0">
                Review successful and failed sign-in attempts recorded by the
                authentication service.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button
                type="button"
                class="btn btn-outline-secondary"
                id="resetAuthenticationLogFiltersBtn"
            >
                <i class="fas fa-undo me-1"></i> Reset filters
            </button>
            <button
                type="button"
                class="btn btn-primary"
                id="refreshAuthenticationLogsBtn"
            >
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4" id="authenticationLogsSummary"></div>

    <div
        class="alert alert-info"
        id="authenticationLogsState"
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
                        for="authenticationLogSearch"
                    >
                        Search
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input
                            class="form-control"
                            id="authenticationLogSearch"
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
                        for="authenticationLogStatusFilter"
                    >
                        Result
                    </label>
                    <select class="form-select" id="authenticationLogStatusFilter">
                        <option value="">All results</option>
                        <option value="success">Successful</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="authenticationLogReasonFilter"
                    >
                        Failure reason
                    </label>
                    <select class="form-select" id="authenticationLogReasonFilter">
                        <option value="">All reasons</option>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="authenticationLogDateFrom"
                    >
                        From date
                    </label>
                    <input
                        class="form-control"
                        id="authenticationLogDateFrom"
                        type="date"
                    >
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="authenticationLogDateTo"
                    >
                        To date
                    </label>
                    <input
                        class="form-control"
                        id="authenticationLogDateTo"
                        type="date"
                    >
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="authenticationLogPageSize"
                    >
                        Rows per page
                    </label>
                    <select class="form-select" id="authenticationLogPageSize">
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
                        <th>Result</th>
                        <th>IP address</th>
                        <th>Failure reason</th>
                        <th>Client</th>
                    </tr>
                </thead>
                <tbody id="authenticationLogsTableBody">
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
            <span class="text-muted small" id="authenticationLogsCount">
                No authentication events loaded
            </span>
            <nav aria-label="Authentication log pages">
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="authenticationLogsPreviousPage"
                    >
                        <i class="fas fa-chevron-left me-1"></i> Previous
                    </button>
                    <span
                        class="btn btn-outline-secondary disabled"
                        id="authenticationLogsPageIndicator"
                    >
                        Page 1 of 1
                    </span>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="authenticationLogsNextPage"
                    >
                        Next <i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/authentication_logs.js?v=<?= asset_version('js/pages/authentication_logs.js') ?>"></script>
