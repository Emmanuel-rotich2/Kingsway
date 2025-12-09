<?php
/**
 * Manage Users Page
 * HTML structure only - all logic in js/pages/users.js (manageUsersController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
    <div class="card-header bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0"><i class="bi bi-person-gear"></i> Users & Access Management</h2>
            <button id="addUserBtn" class="btn btn-light" onclick="manageUsersController.showCreateModal()">
                <i class="bi bi-plus-circle"></i> Add User
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#allUsers"
                    type="button">
                    <i class="bi bi-people"></i> All Users
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="roles-tab" data-bs-toggle="tab" data-bs-target="#rolesTab" type="button">
                    <i class="bi bi-shield-lock"></i> Roles & Permissions
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activityTab"
                    type="button">
                    <i class="bi bi-clock-history"></i> Activity Logs
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Tab 1: All Users -->
            <div class="tab-pane fade show active" id="allUsers" role="tabpanel">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" id="searchUsers" class="form-control"
                            placeholder="🔍 Search by name, username, email..."
                            onkeyup="manageUsersController.handleSearch(this.value)">
                    </div>
                    <div class="col-md-3">
                        <select id="roleFilter" class="form-select"
                            onchange="manageUsersController.handleRoleFilter(this.value)">
                            <option value="">All Roles</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select"
                            onchange="manageUsersController.handleStatusFilter(this.value)">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" onclick="manageUsersController.clearFilters()">
                            <i class="bi bi-x-circle"></i> Clear
                        </button>
                    </div>
                </div>

                <!-- Users Table -->
                <div id="usersTableContainer">
                    <p class="text-muted text-center">Loading users...</p>
                </div>
            </div>

            <!-- Tab 2: Roles & Permissions -->
            <div class="tab-pane fade" id="rolesTab" role="tabpanel">
                <div class="row">
                    <div class="col-md-12">
                        <h5>System Roles</h5>
                        <div id="rolesListContainer">
                            <p class="text-muted">Loading roles...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Activity Logs -->
            <div class="tab-pane fade" id="activityTab" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="date" id="activityDateFrom" class="form-control" placeholder="From Date">
                    </div>
                    <div class="col-md-4">
                        <input type="date" id="activityDateTo" class="form-control" placeholder="To Date">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" onclick="manageUsersController.loadActivityLogs()">
                            <i class="bi bi-search"></i> Search Logs
                        </button>
                    </div>
                </div>
                <div id="activityLogsContainer">
                    <p class="text-muted">Select date range to view activity logs</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Username *</label>
                            <input type="text" id="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" id="email" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" id="firstName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" id="lastName" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Main Role *</label>
                            <select id="mainRole" class="form-select" required>
                                <option value="">-- Select Role --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select id="userStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3" id="passwordSection">
                        <div class="col-md-12">
                            <label class="form-label">Password *</label>
                            <input type="password" id="password" class="form-control">
                            <small class="text-muted">Leave blank to auto-generate (for new users only)</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="manageUsersController.saveUser()">Save
                    User</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Roles Modal -->
<div class="modal fade" id="rolesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Roles: <span id="roleUserName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Main Role *</label>
                    <select id="mainRoleSelect" class="form-select">
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Additional Roles</label>
                    <div id="extraRolesContainer" class="border rounded p-3"
                        style="max-height: 300px; overflow-y: auto;">
                        <!-- Checkboxes populated by JS -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="manageUsersController.updateUserRoles()">Update
                    Roles</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Permissions Modal -->
<div class="modal fade" id="permissionsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Permissions: <span id="permUserName"></span></h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary active"
                        onclick="manageUsersController.showPermissionsTab('effective')">Effective</button>
                    <button class="btn btn-outline-primary"
                        onclick="manageUsersController.showPermissionsTab('direct')">Direct</button>
                    <button class="btn btn-outline-danger"
                        onclick="manageUsersController.showPermissionsTab('denied')">Denied</button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
                <div id="effectivePermissions" class="permissions-view">
                    <!-- Populated by JS -->
                </div>
                <div id="directPermissions" class="permissions-view d-none">
                    <!-- Populated by JS -->
                </div>
                <div id="deniedPermissions" class="permissions-view d-none">
                    <!-- Populated by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="savePermissionsBtn"
                    onclick="manageUsersController.saveDirectPermissions()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- User Management Scripts -->
<script src="/Kingsway/js/utils/form-validation.js?v=<?php echo time(); ?>"></script>
<script src="/Kingsway/js/pages/users.js?v=<?php echo time(); ?>"></script>