<?php
/**
 * System Administrator — Role-Permission Matrix
 * Controller: js/pages/role_permission_matrix.js
 */
?>
<div class="container-fluid py-4" id="rolePermissionMatrixPage">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Role-permission matrix</h2>
            <p class="text-muted mb-0">Review, assign and revoke permissions for each role.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 no-print">
            <button type="button" class="btn btn-outline-secondary" data-kw-sa-print>
                <i class="bi bi-printer me-1" aria-hidden="true"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="refreshRolePermissionMatrixBtn">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i> Refresh
            </button>
        </div>
    </header>

    <div
        class="alert alert-info"
        id="rolePermissionMatrixState"
        role="status"
        aria-live="polite"
    >
        Waiting for authentication...
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h3 class="h6 mb-3">Filters</h3>
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="matrixRole">Role</label>
                    <select class="form-select" id="matrixRole" disabled>
                        <option value="">Select a role</option>
                    </select>
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="matrixModule">Permission module</label>
                    <select class="form-select" id="matrixModule" disabled>
                        <option value="">All modules</option>
                    </select>
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="matrixSearch">Search permissions</label>
                    <input
                        class="form-control"
                        id="matrixSearch"
                        type="search"
                        placeholder="Code, entity, action or description"
                        aria-label="Search permissions"
                        autocomplete="off"
                        disabled
                    >
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="rolePermissionMatrixSummary"></div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h3 class="h6 mb-0" id="rolePermissionMatrixTitle">Permissions</h3>
            <small class="text-muted" id="rolePermissionMatrixCount"></small>
        </div>
        <div class="card-body border-bottom py-2 bg-light" id="bulkActionBar" hidden>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold small" id="bulkSelectedCount">0 selected</span>
                <button type="button" class="btn btn-sm btn-outline-success" id="bulkGrantBtn">
                    <i aria-hidden="true" class="bi bi-check2-circle me-1"></i> Assign selected
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="bulkRevokeBtn">
                    <i aria-hidden="true" class="bi bi-x-circle me-1"></i> Revoke selected
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkClearBtn">
                    Clear selection
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Permissions assigned to the selected role</caption>
                <thead id="rolePermissionMatrixTableHead">
                    <tr>
                        <th class="text-center" style="width: 40px">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                data-select-all
                                aria-label="Select all permissions"
                            >
                        </th>
                        <th style="width: 90px">Assigned</th>
                        <th scope="col">Permission</th>
                        <th scope="col">Module</th>
                        <th scope="col">Entity</th>
                        <th scope="col">Action</th>
                        <th scope="col">Description</th>
                    </tr>
                </thead>
                <tbody id="rolePermissionMatrixBody">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            Waiting for authentication...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/role_permission_matrix.js?v=<?= asset_version('js/pages/role_permission_matrix.js') ?>"></script>
