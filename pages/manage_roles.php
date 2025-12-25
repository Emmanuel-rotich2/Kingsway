<?php
/**
 * Manage Roles Page (RBAC Role Management)
 * HTML structure only - logic will be in js/pages/manage_roles.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-user-shield"></i> Role Management</h4>
            <button class="btn btn-light btn-sm" id="addRoleBtn" data-permission="roles_create">
                <i class="bi bi-plus-circle"></i> Add New Role
            </button>
        </div>
    </div>

    <div class="card-body">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Roles</h6>
                        <h3 class="text-primary mb-0" id="totalRoles">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Active Roles</h6>
                        <h3 class="text-success mb-0" id="activeRoles">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Custom Roles</h6>
                        <h3 class="text-info mb-0" id="customRoles">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Users</h6>
                        <h3 class="text-warning mb-0" id="totalUsers">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Row -->
        <div class="row mb-3">
            <div class="col-md-4">
                <input type="text" class="form-control" id="searchRoles" placeholder="Search roles...">
            </div>
            <div class="col-md-3">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="typeFilter">
                    <option value="">All Types</option>
                    <option value="system">System Roles</option>
                    <option value="custom">Custom Roles</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" id="exportBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Roles Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="rolesTable">
                <thead class="table-light">
                    <tr>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Users</th>
                        <th>Permissions</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic content -->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center" id="pagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Add/Edit Role Modal -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roleModalTitle">Add New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="roleForm">
                    <input type="hidden" id="roleId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role Name*</label>
                            <input type="text" class="form-control" id="roleName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status*</label>
                            <select class="form-select" id="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Permissions*</label>
                        <div class="card">
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <div class="row">
                                    <!-- Users Module -->
                                    <div class="col-md-6 mb-3">
                                        <h6 class="border-bottom pb-2">
                                            <input type="checkbox" class="module-check" data-module="users"> Users
                                            Management
                                        </h6>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="users_view" data-module="users">
                                            <label class="form-check-label" for="users_view">View Users</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="users_create" data-module="users">
                                            <label class="form-check-label" for="users_create">Create Users</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="users_edit" data-module="users">
                                            <label class="form-check-label" for="users_edit">Edit Users</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="users_delete" data-module="users">
                                            <label class="form-check-label" for="users_delete">Delete Users</label>
                                        </div>
                                    </div>

                                    <!-- Students Module -->
                                    <div class="col-md-6 mb-3">
                                        <h6 class="border-bottom pb-2">
                                            <input type="checkbox" class="module-check" data-module="students"> Students
                                        </h6>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="students_view" data-module="students">
                                            <label class="form-check-label" for="students_view">View Students</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="students_create" data-module="students">
                                            <label class="form-check-label" for="students_create">Add Students</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="students_edit" data-module="students">
                                            <label class="form-check-label" for="students_edit">Edit Students</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="students_delete" data-module="students">
                                            <label class="form-check-label" for="students_delete">Delete
                                                Students</label>
                                        </div>
                                    </div>

                                    <!-- Finance Module -->
                                    <div class="col-md-6 mb-3">
                                        <h6 class="border-bottom pb-2">
                                            <input type="checkbox" class="module-check" data-module="finance"> Finance
                                        </h6>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="finance_view" data-module="finance">
                                            <label class="form-check-label" for="finance_view">View Finance</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="finance_manage" data-module="finance">
                                            <label class="form-check-label" for="finance_manage">Manage
                                                Transactions</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="finance_approve" data-module="finance">
                                            <label class="form-check-label" for="finance_approve">Approve
                                                Expenses</label>
                                        </div>
                                    </div>

                                    <!-- Academic Module -->
                                    <div class="col-md-6 mb-3">
                                        <h6 class="border-bottom pb-2">
                                            <input type="checkbox" class="module-check" data-module="academic"> Academic
                                        </h6>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="academic_view" data-module="academic">
                                            <label class="form-check-label" for="academic_view">View Academic
                                                Data</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="academic_manage" data-module="academic">
                                            <label class="form-check-label" for="academic_manage">Manage Classes</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="results_enter" data-module="academic">
                                            <label class="form-check-label" for="results_enter">Enter Results</label>
                                        </div>
                                    </div>

                                    <!-- Reports Module -->
                                    <div class="col-md-6 mb-3">
                                        <h6 class="border-bottom pb-2">
                                            <input type="checkbox" class="module-check" data-module="reports"> Reports
                                        </h6>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="reports_view" data-module="reports">
                                            <label class="form-check-label" for="reports_view">View Reports</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="reports_generate" data-module="reports">
                                            <label class="form-check-label" for="reports_generate">Generate
                                                Reports</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="reports_export" data-module="reports">
                                            <label class="form-check-label" for="reports_export">Export Reports</label>
                                        </div>
                                    </div>

                                    <!-- System Module -->
                                    <div class="col-md-6 mb-3">
                                        <h6 class="border-bottom pb-2">
                                            <input type="checkbox" class="module-check" data-module="system"> System
                                        </h6>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="system_settings" data-module="system">
                                            <label class="form-check-label" for="system_settings">System
                                                Settings</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permission-check"
                                                id="roles_manage" data-module="system">
                                            <label class="form-check-label" for="roles_manage">Manage Roles</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveRoleBtn">Save Role</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement rolesManagementController in js/pages/manage_roles.js
        console.log('Roles Management page loaded');
    });
</script>