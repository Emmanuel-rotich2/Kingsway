<?php
/**
 * System Administrator — Role Definitions
 * Controller: js/pages/manage_roles.js
 */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') $appBase = '';
}
?>
<div class="container-fluid py-4" id="manageRolesPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Role Definitions</h2>
            <p class="text-muted mb-0">
                Create and maintain role names, descriptions, scope and activation status.
                Permission assignments remain in the Role-Permission Matrix.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a
                class="btn btn-outline-primary"
                href="<?= htmlspecialchars($appBase) ?>/home.php?route=role_permission_matrix"
            >
                <i class="bi bi-key me-1"></i> Permission matrix
            </a>
            <button type="button" class="btn btn-outline-secondary" id="exportRolesBtn">
                <i class="bi bi-file-export me-1"></i> Export
            </button>
            <button type="button" class="btn btn-outline-secondary" id="refreshRolesBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
            <button type="button" class="btn btn-primary" id="createRoleBtn">
                <i class="bi bi-plus-lg me-1"></i> Create role
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4" id="roleDefinitionsSummary"></div>

    <div
        class="alert alert-info"
        id="roleDefinitionsState"
        role="status"
        aria-live="polite"
    >
        Waiting for authentication...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-6">
                    <label class="form-label small text-muted" for="searchRoles">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input
                            class="form-control"
                            id="searchRoles"
                            type="search"
                            placeholder="Search role name or description"
                            autocomplete="off"
                        >
                    </div>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <label class="form-label small text-muted" for="roleScopeFilter">Scope</label>
                    <select class="form-select" id="roleScopeFilter">
                        <option value="">All scopes</option>
                        <option value="system">System</option>
                        <option value="school">School</option>
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <label class="form-label small text-muted" for="roleTypeFilter">Type</label>
                    <select class="form-select" id="roleTypeFilter">
                        <option value="">All types</option>
                        <option value="protected">Protected</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <label class="form-label small text-muted" for="roleStatusFilter">Status</label>
                    <select class="form-select" id="roleStatusFilter">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead id="roleDefinitionsTableHead">
                    <tr>
                        <th scope="col">Role</th>
                        <th scope="col">Description</th>
                        <th scope="col">Scope</th>
                        <th scope="col">Type</th>
                        <th class="text-center">Users</th>
                        <th class="text-center">Permissions</th>
                        <th scope="col">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="roleDefinitionsTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            Waiting for authentication...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white text-muted small" id="roleDefinitionsCount"></div>
    </div>
</div>

<div class="modal fade" id="roleDefinitionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="roleDefinitionForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="roleDefinitionModalTitle">Create Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="roleDefinitionId">

                    <ul class="nav nav-pills nav-justified mb-4" id="roleWizardSteps">
                        <li class="nav-item">
                            <button type="button" class="nav-link active" id="roleStep1Btn" data-role-step="1">
                                <i class="bi bi-pencil-square me-1"></i>Details
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" id="roleStep2Btn" data-role-step="2" disabled>
                                <i class="bi bi-key me-1"></i>Permissions
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" id="roleStep3Btn" data-role-step="3" disabled>
                                <i class="bi bi-check2-circle me-1"></i>Confirm
                            </button>
                        </li>
                    </ul>

                    <div id="roleStep1Content">
                        <div class="mb-3">
                            <label class="form-label" for="roleDefinitionName">Role name</label>
                            <input
                                class="form-control"
                                id="roleDefinitionName"
                                name="name"
                                maxlength="50"
                                required
                                autocomplete="off"
                            >
                            <div class="invalid-feedback">Enter a role name of at most 50 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="roleDefinitionDescription">Description</label>
                            <textarea
                                class="form-control"
                                id="roleDefinitionDescription"
                                name="description"
                                rows="4"
                                placeholder="Describe this role's responsibility"
                            ></textarea>
                        </div>
                        <div class="mb-3" id="roleDefinitionScopeGroup">
                            <label class="form-label" for="roleDefinitionScope">Scope</label>
                            <select class="form-select" id="roleDefinitionScope" name="scope">
                                <option value="school">School operations</option>
                                <option value="system">System administration</option>
                            </select>
                            <div class="form-text">
                                System scope is reserved for technical administration roles.
                            </div>
                        </div>
                    </div>

                    <div id="roleStep2Content" class="d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <p class="text-muted mb-0">Select the permissions this role should grant.</p>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="rolePermissionsSelectAll">Select all</button>
                        </div>
                        <input class="form-control mb-3" type="search"
                               id="rolePermissionsSearch" placeholder="Search permissions..."
                               autocomplete="off">
                        <div class="border rounded p-3" id="rolePermissionsContainer"
                             style="max-height:340px;overflow-y:auto">
                            <div class="text-muted py-3" id="rolePermissionsLoading">
                                <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading permissions...
                            </div>
                            <div id="rolePermissionsList"></div>
                            <div class="text-muted d-none py-3" id="rolePermissionsEmpty">No permissions match your search.</div>
                        </div>
                        <div class="form-text mt-2" id="rolePermissionsCount"></div>
                    </div>

                    <div id="roleStep3Content" class="d-none">
                        <div class="alert alert-light border mb-3">
                            <div class="mb-1"><strong>Role name:</strong> <span id="roleConfirmName"></span></div>
                            <div class="mb-1"><strong>Scope:</strong> <span id="roleConfirmScope"></span></div>
                            <div class="mb-0"><strong>Permissions:</strong> <span id="roleConfirmPermissions"></span></div>
                        </div>
                        <p class="text-muted mb-0" id="roleConfirmActionText">
                            Review and save. The new role will be created with the permissions above.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-role-wiz="prev" id="roleWizardPrevBtn" disabled>
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </button>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="roleWizardNextBtn">
                            Next<i class="bi bi-arrow-right ms-1"></i>
                        </button>
                        <button type="submit" class="btn btn-success d-none" id="roleWizardSaveBtn">
                            <i class="bi bi-check-lg me-1"></i>Save role
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/manage_roles.js?v=<?= asset_version('js/pages/manage_roles.js') ?>"></script>
