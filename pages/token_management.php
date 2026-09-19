<?php
/**
 * System Administrator — Token Management
 * Controller: js/pages/token_management.js
 */
?>
<div class="container-fluid py-4" id="tokenManagementPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Token Management</h2>
            <p class="text-muted mb-0">
                Review refresh and API-token records and revoke compromised
                credentials without exposing token values or hashes.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button
                type="button"
                class="btn btn-outline-secondary"
                id="resetTokenFiltersBtn"
            >
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset filters
            </button>
            <button
                type="button"
                class="btn btn-primary"
                id="refreshTokensBtn"
            >
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4" id="tokenManagementSummary"></div>

    <div
        class="alert alert-info"
        id="tokenManagementState"
        role="status"
        aria-live="polite"
    >
        Waiting for authentication...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5">
                    <label
                        class="form-label small text-muted"
                        for="tokenSearch"
                    >
                        Search
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input
                            class="form-control"
                            id="tokenSearch"
                            type="search"
                            maxlength="200"
                            placeholder="User, email, token name or record ID"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <div class="col-sm-4 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="tokenTypeFilter"
                    >
                        Token type
                    </label>
                    <select class="form-select" id="tokenTypeFilter">
                        <option value="">All types</option>
                        <option value="refresh">Refresh tokens</option>
                        <option value="api">API tokens</option>
                    </select>
                </div>

                <div class="col-sm-4 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="tokenStatusFilter"
                    >
                        Status
                    </label>
                    <select class="form-select" id="tokenStatusFilter">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="revoked">Revoked</option>
                    </select>
                </div>

                <div class="col-sm-4 col-lg-3">
                    <label
                        class="form-label small text-muted"
                        for="tokenPageSize"
                    >
                        Rows per page
                    </label>
                    <select class="form-select" id="tokenPageSize">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-body border-bottom py-2 bg-light" id="tokenManagementBulkBar" hidden>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold small" id="tokenManagementBulkCount">0 selected</span>
                <button type="button" class="btn btn-sm btn-outline-danger" id="bulkRevokeTokensBtn">
                    <i class="bi bi-ban me-1"></i> Revoke selected
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkClearTokensBtn">
                    Clear selection
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead id="tokenManagementTableHead">
                    <tr>
                        <th class="text-center" style="width: 40px">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                data-select-all
                                aria-label="Select all matching rows"
                            >
                        </th>
                        <th scope="col">Type</th>
                        <th scope="col">Owner</th>
                        <th scope="col">Credential</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created</th>
                        <th scope="col">Last used</th>
                        <th scope="col">Expires</th>
                        <th scope="col" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="tokenManagementTableBody">
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            Waiting for authentication...
                        </td>
                    </tr>
                </tbody>
            </table>
            <template id="tokenManagementRowTemplate">
                <tr>
                    <td class="text-center">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            aria-label="Select this token"
                        >
                    </td>
                    <td>
                        <span class="badge" data-row-fill="typeBadge"></span>
                    </td>
                    <td>
                        <div class="fw-semibold" data-row-fill="ownerName"></div>
                        <div class="small text-muted" data-row-fill="ownerIdentity"></div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold" data-row-fill="credentialLabel"></span>
                            <span class="badge bg-primary" data-row-fill="credentialCurrent" hidden>Current</span>
                        </div>
                        <div class="small text-muted" data-row-fill="credentialDetail"></div>
                    </td>
                    <td data-row-fill="statusBadge"></td>
                    <td class="text-nowrap" data-row-fill="createdAt"></td>
                    <td class="text-nowrap" data-row-fill="lastUsedAt"></td>
                    <td class="text-nowrap" data-row-fill="expiresAt"></td>
                    <td class="text-end" data-row-fill="actions"></td>
                </tr>
            </template>
        </div>

        <div
            class="card-footer bg-white d-flex flex-wrap gap-3
                   justify-content-between align-items-center"
        >
            <span class="text-muted small" id="tokenManagementCount">
                No token records loaded
            </span>
            <nav aria-label="Token record pages">
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="tokenPreviousPage"
                    >
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </button>
                    <span
                        class="btn btn-outline-secondary disabled"
                        id="tokenPageIndicator"
                    >
                        Page 1 of 1
                    </span>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="tokenNextPage"
                    >
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/token_management.js?v=<?= asset_version('js/pages/token_management.js') ?>"></script>
