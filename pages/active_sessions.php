<?php
/**
 * System Administrator — Active Sessions
 * Controller: js/pages/active_sessions.js
 */
?>
<div class="container-fluid py-4" id="activeSessionsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Active Sessions</h2>
            <p class="text-muted mb-0">
                Review authenticated user sessions and revoke compromised or
                unrecognized access without exposing authentication tokens.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button
                type="button"
                class="btn btn-outline-secondary"
                id="resetActiveSessionFiltersBtn"
            >
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset filters
            </button>
            <button
                type="button"
                class="btn btn-primary"
                id="refreshActiveSessionsBtn"
            >
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4" id="activeSessionsSummary"></div>

    <div
        class="alert alert-info"
        id="activeSessionsState"
        role="status"
        aria-live="polite"
    >
        Waiting for authentication...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-6">
                    <label
                        class="form-label small text-muted"
                        for="activeSessionSearch"
                    >
                        Search
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input
                            class="form-control"
                            id="activeSessionSearch"
                            type="search"
                            maxlength="200"
                            placeholder="User, email, role, IP address or client"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label
                        class="form-label small text-muted"
                        for="activeSessionRoleFilter"
                    >
                        Role
                    </label>
                    <select class="form-select" id="activeSessionRoleFilter">
                        <option value="">All roles</option>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label
                        class="form-label small text-muted"
                        for="activeSessionPageSize"
                    >
                        Rows per page
                    </label>
                    <select class="form-select" id="activeSessionPageSize">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-body border-bottom py-2 bg-light" id="activeSessionsBulkBar" hidden>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold small" id="activeSessionsBulkCount">0 selected</span>
                <button type="button" class="btn btn-sm btn-outline-danger" id="bulkRevokeSessionsBtn">
                    <i class="bi bi-sign-stop me-1"></i> Revoke selected
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkClearSessionsBtn">
                    Clear selection
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead id="activeSessionsTableHead">
                    <tr>
                        <th class="text-center" style="width: 40px">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                data-select-all
                                aria-label="Select all matching rows"
                            >
                        </th>
                        <th scope="col">User</th>
                        <th scope="col">Role</th>
                        <th scope="col">Source</th>
                        <th scope="col">Client</th>
                        <th scope="col">Last activity</th>
                        <th scope="col">Expires</th>
                        <th scope="col" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="activeSessionsTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            Waiting for authentication...
                        </td>
                    </tr>
                </tbody>
            </table>
            <template id="activeSessionsRowTemplate">
                <tr>
                    <td class="text-center">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            aria-label="Select this session"
                        >
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div>
                                <div class="fw-semibold" data-row-fill="name"></div>
                                <div class="small text-muted" data-row-fill="identity"></div>
                            </div>
                            <span class="badge bg-primary" data-row-fill="currentBadge" hidden>Current</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border" data-row-fill="roleBadge"></span>
                    </td>
                    <td>
                        <code data-row-fill="ip"></code>
                    </td>
                    <td class="small text-muted" data-row-fill="client"></td>
                    <td>
                        <div data-row-fill="lastActivity"></div>
                        <div class="small text-muted" data-row-fill="idle"></div>
                    </td>
                    <td class="text-nowrap" data-row-fill="expires"></td>
                    <td class="text-end" data-row-fill="actions"></td>
                </tr>
            </template>
        </div>

        <div
            class="card-footer bg-white d-flex flex-wrap gap-3
                   justify-content-between align-items-center"
        >
            <span class="text-muted small" id="activeSessionsCount">
                No active sessions loaded
            </span>
            <nav aria-label="Active session pages">
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="activeSessionsPreviousPage"
                    >
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </button>
                    <span
                        class="btn btn-outline-secondary disabled"
                        id="activeSessionsPageIndicator"
                    >
                        Page 1 of 1
                    </span>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="activeSessionsNextPage"
                    >
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/active_sessions.js?v=<?= asset_version('js/pages/active_sessions.js') ?>"></script>
