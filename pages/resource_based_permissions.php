<?php
/**
 * System Administrator — Resource-Based Permissions
 * Controller: js/pages/resource_based_permissions.js
 */
?>
<div class="container-fluid py-4" id="resourceBasedPermissionsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Resource-Based Permissions</h2>
            <p class="text-muted mb-0">
                Maintain the canonical permission definitions used by roles,
                routes, workflows and user-level access rules.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a
                class="btn btn-outline-primary"
                href="<?= htmlspecialchars($appBase) ?>/home.php?route=role_permission_matrix"
            >
                <i class="fas fa-key me-1"></i> Role matrix
            </a>
            <button
                type="button"
                class="btn btn-outline-secondary"
                id="refreshResourcePermissionsBtn"
            >
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
            <button
                type="button"
                class="btn btn-primary"
                id="createResourcePermissionBtn"
            >
                <i class="fas fa-plus me-1"></i> Create permission
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4" id="resourcePermissionsSummary"></div>

    <div
        class="alert alert-info"
        id="resourcePermissionsState"
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
                        for="resourcePermissionSearch"
                    >
                        Search
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input
                            class="form-control"
                            id="resourcePermissionSearch"
                            type="search"
                            placeholder="Search code, description or resource"
                            autocomplete="off"
                        >
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="resourcePermissionModuleFilter"
                    >
                        Module
                    </label>
                    <select class="form-select" id="resourcePermissionModuleFilter">
                        <option value="">All modules</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="resourcePermissionEntityFilter"
                    >
                        Resource
                    </label>
                    <select class="form-select" id="resourcePermissionEntityFilter">
                        <option value="">All resources</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="resourcePermissionActionFilter"
                    >
                        Action
                    </label>
                    <select class="form-select" id="resourcePermissionActionFilter">
                        <option value="">All actions</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label
                        class="form-label small text-muted"
                        for="resourcePermissionPageSize"
                    >
                        Rows per page
                    </label>
                    <select class="form-select" id="resourcePermissionPageSize">
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
                        <th>Permission code</th>
                        <th>Resource</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Usage</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="resourcePermissionsTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
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
            <span class="text-muted small" id="resourcePermissionsCount">
                No permissions loaded
            </span>
            <nav aria-label="Resource permission pages">
                <div class="btn-group btn-group-sm">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="resourcePermissionsPreviousPage"
                    >
                        <i class="fas fa-chevron-left me-1"></i> Previous
                    </button>
                    <span
                        class="btn btn-outline-secondary disabled"
                        id="resourcePermissionsPageIndicator"
                    >
                        Page 1 of 1
                    </span>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        id="resourcePermissionsNextPage"
                    >
                        Next <i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<div
    class="modal fade"
    id="resourcePermissionModal"
    tabindex="-1"
    aria-labelledby="resourcePermissionModalTitle"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="resourcePermissionForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="resourcePermissionModalTitle">
                        Create permission
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="resourcePermissionId">

                    <div
                        class="alert alert-warning"
                        id="resourcePermissionUsageWarning"
                        role="alert"
                        hidden
                    ></div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="resourcePermissionCode">
                                Permission code <span class="text-danger">*</span>
                            </label>
                            <input
                                class="form-control font-monospace"
                                id="resourcePermissionCode"
                                maxlength="255"
                                pattern="[A-Za-z0-9._:-]+"
                                autocomplete="off"
                                required
                            >
                            <div class="form-text">
                                Use a stable identifier such as
                                <code>students.records.view</code>. Codes already in
                                use cannot be renamed.
                            </div>
                            <div class="invalid-feedback">
                                Enter a valid permission code using letters, numbers,
                                dots, underscores, colons or hyphens.
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="resourcePermissionEntity">
                                Resource
                            </label>
                            <input
                                class="form-control"
                                id="resourcePermissionEntity"
                                maxlength="100"
                                list="resourcePermissionEntities"
                                autocomplete="off"
                                placeholder="e.g. students"
                            >
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="resourcePermissionAction">
                                Action
                            </label>
                            <input
                                class="form-control"
                                id="resourcePermissionAction"
                                maxlength="100"
                                list="resourcePermissionActions"
                                autocomplete="off"
                                placeholder="e.g. view"
                            >
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="resourcePermissionModule">
                                Module
                            </label>
                            <input
                                class="form-control"
                                id="resourcePermissionModule"
                                maxlength="100"
                                list="resourcePermissionModules"
                                autocomplete="off"
                                placeholder="e.g. Students"
                            >
                        </div>

                        <div class="col-12">
                            <label
                                class="form-label"
                                for="resourcePermissionDescription"
                            >
                                Description
                            </label>
                            <textarea
                                class="form-control"
                                id="resourcePermissionDescription"
                                rows="4"
                                maxlength="500"
                                placeholder="Describe what this permission allows."
                            ></textarea>
                        </div>
                    </div>

                    <datalist id="resourcePermissionEntities"></datalist>
                    <datalist id="resourcePermissionActions"></datalist>
                    <datalist id="resourcePermissionModules"></datalist>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="saveResourcePermissionBtn"
                    >
                        Save permission
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/resource_based_permissions.js?v=<?= asset_version('js/pages/resource_based_permissions.js') ?>"></script>
