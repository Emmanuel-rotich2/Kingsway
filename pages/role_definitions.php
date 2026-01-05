<?php
/**
 * Role Definitions Management Page
 * 
 * Allows System Administrators to:
 * - View all system roles
 * - Create new roles
 * - Edit role properties
 * - Enable/disable roles
 * 
 * Logic handled by: js/pages/role_definitions.js
 * 
 * @package App\Pages\System
 * @since 2025-01-01
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="mb-1"><i class="fas fa-user-tag me-2"></i>Role Definitions</h2>
                    <p class="text-muted mb-0">Define and manage system roles and their properties</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                    <i class="fas fa-plus me-2"></i>Add Role
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-primary bg-opacity-10 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-primary text-white me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 fw-bold" id="totalRoles">0</h3>
                            <small class="text-muted">Total Roles</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-success bg-opacity-10 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-success text-white me-3">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 fw-bold" id="activeRoles">0</h3>
                            <small class="text-muted">Active Roles</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-danger bg-opacity-10 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-danger text-white me-3">
                            <i class="fas fa-server"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 fw-bold" id="systemRoles">0</h3>
                            <small class="text-muted">System Roles</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-info bg-opacity-10 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-info text-white me-3">
                            <i class="fas fa-school"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 fw-bold" id="schoolRoles">0</h3>
                            <small class="text-muted">School Roles</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="searchRoles"
                            placeholder="Search roles...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterDomain">
                        <option value="">All Domains</option>
                        <option value="SYSTEM">SYSTEM</option>
                        <option value="SCHOOL">SCHOOL</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" onclick="RoleDefinitionsController.refresh()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Roles Grid -->
    <div class="row g-4" id="rolesGrid">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted mt-2">Loading roles...</p>
        </div>
    </div>
</div>

<!-- Create/Edit Role Modal -->
<div class="modal fade" id="createRoleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-tag me-2"></i><span id="modalTitle">Create Role</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="roleForm">
                <div class="modal-body">
                    <input type="hidden" id="roleId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="roleName" required
                                placeholder="e.g., school_administrator">
                            <small class="text-muted">System identifier (lowercase, underscores)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Display Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="roleDisplayName" required
                                placeholder="e.g., School Administrator">
                            <small class="text-muted">Human-readable label</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Domain <span class="text-danger">*</span></label>
                            <select class="form-select" id="roleDomain" required>
                                <option value="SCHOOL">SCHOOL</option>
                                <option value="SYSTEM">SYSTEM</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="roleStatus">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="roleDescription" rows="2"
                                placeholder="Describe the role's purpose and responsibilities"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Icon</label>
                            <div class="input-group">
                                <span class="input-group-text"><i id="iconPreview" class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="roleIcon" placeholder="fas fa-user">
                            </div>
                            <small class="text-muted">FontAwesome class (e.g., fas fa-user-tie)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="roleColor"
                                    value="#0d6efd" style="width: 50px;">
                                <input type="text" class="form-control" id="roleColorText" value="#0d6efd"
                                    placeholder="#0d6efd">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveRoleBtn">
                        <i class="fas fa-save me-1"></i>Save Role
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
</style>

<!-- Page Controller -->
<script src="/Kingsway/js/pages/role_definitions.js?v=<?php echo time(); ?>"></script>
