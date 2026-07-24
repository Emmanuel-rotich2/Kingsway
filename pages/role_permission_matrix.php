<?php
/**
 * System Administrator — Role-Permission Matrix
 * Controller: js/pages/role_permission_matrix.js
 */
?>
<div class="container-fluid py-4" id="rolePermissionMatrixPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Role-Permission Matrix</h2>
            <p class="text-muted mb-0">Review, assign and revoke permissions for each role.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary" id="refreshRolePermissionMatrixBtn">
            <i class="fas fa-sync-alt me-1"></i> Refresh
        </button>
    </div>

    <div
        class="alert alert-info"
        id="rolePermissionMatrixState"
        role="status"
        aria-live="polite"
    >
        Waiting for authentication...
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
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
                        autocomplete="off"
                        disabled
                    >
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="rolePermissionMatrixSummary"></div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong id="rolePermissionMatrixTitle">Permissions</strong>
            <small class="text-muted" id="rolePermissionMatrixCount"></small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 90px">Assigned</th>
                        <th>Permission</th>
                        <th>Module</th>
                        <th>Entity</th>
                        <th>Action</th>
                        <th>Description</th>
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
