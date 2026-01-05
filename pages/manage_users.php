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
                <form id="userForm" enctype="multipart/form-data">
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

                    <!-- Roles -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Main Role *</label>
                            <select id="mainRole" class="form-select" required>
                                <option value="">-- Select Role --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Additional Roles</label>
                            <div id="extraRolesCreateContainer" class="border rounded p-2" style="max-height:120px;overflow-y:auto;"></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select id="userStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="passwordSection">
                            <label class="form-label">Password</label>
                            <input type="password" id="password" class="form-control">
                            <small class="text-muted">Leave blank to auto-generate (for new users only)</small>
                        </div>
                    </div>

                    <!-- Staff info collapsible -->
                    <div class="accordion" id="staffInfoAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#staffInfoCollapse" aria-expanded="false" aria-controls="staffInfoCollapse">
                                    Staff Information (optional)
                                </button>
                            </h2>
                            <div id="staffInfoCollapse" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#staffInfoAccordion">
                                <div class="accordion-body">
                                    <!-- Row 1: Department, Position, Employment Date -->
                                    <div class="row mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Department <span class="text-danger">*</span></label>
                                            <select id="departmentId" class="form-select">
                                                <option value="">-- Select Department --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Position <span class="text-danger">*</span></label>
                                            <input type="text" id="position" class="form-control" placeholder="e.g. Teacher, Accountant">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Employment Date <span class="text-danger">*</span></label>
                                            <input type="date" id="employmentDate" class="form-control">
                                        </div>
                                    </div>
                                    <!-- Row 2: Contract Type, Supervisor, Staff Type -->
                                    <div class="row mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Contract Type</label>
                                            <select id="contractType" class="form-select">
                                                <option value="">-- Select --</option>
                                                <option value="permanent">Permanent</option>
                                                <option value="contract">Contract</option>
                                                <option value="probation">Probation</option>
                                                <option value="intern">Intern</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Supervisor</label>
                                            <select id="supervisorId" class="form-select">
                                                <option value="">-- Select Supervisor --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Staff Type</label>
                                            <select id="staffTypeId" class="form-select">
                                                <option value="">-- Select Type --</option>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Row 3: Staff Category -->
                                    <div class="row mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Staff Category</label>
                                            <select id="staffCategoryId" class="form-select">
                                                <option value="">-- Select Category --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                                            <select id="gender" class="form-select">
                                                <option value="">-- Select --</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                            <input type="date" id="dateOfBirth" class="form-control">
                                        </div>
                                    </div>
                                    <!-- Row 4: Statutory Info -->
                                    <h6 class="mb-2 mt-3 text-secondary"><i class="bi bi-file-earmark-text"></i> Statutory Information</h6>
                                    <div class="row mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label">NSSF No <span class="text-danger">*</span></label>
                                            <input type="text" id="nssfNo" class="form-control" placeholder="NSSF Number">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">KRA PIN <span class="text-danger">*</span></label>
                                            <input type="text" id="kraPin" class="form-control" placeholder="A000000000X">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">NHIF No <span class="text-danger">*</span></label>
                                            <input type="text" id="nhifNo" class="form-control" placeholder="NHIF Number">
                                        </div>
                                    </div>
                                    <!-- Row 5: Financial Info -->
                                    <h6 class="mb-2 mt-3 text-secondary"><i class="bi bi-bank"></i> Financial Information</h6>
                                    <div class="row mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                                            <input type="text" id="bankAccount" class="form-control" placeholder="Bank account number">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Salary <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">KES</span>
                                                <input type="number" step="0.01" id="salary" class="form-control" placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Row 6: Personal Info -->
                                    <h6 class="mb-2 mt-3 text-secondary"><i class="bi bi-person-lines-fill"></i> Additional Personal Info</h6>
                                    <div class="row mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Marital Status</label>
                                            <select id="maritalStatus" class="form-select">
                                                <option value="">-- Select --</option>
                                                <option value="single">Single</option>
                                                <option value="married">Married</option>
                                                <option value="divorced">Divorced</option>
                                                <option value="widowed">Widowed</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">TSC No (teachers only)</label>
                                            <input type="text" id="tscNo" class="form-control" placeholder="TSC Registration Number">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">National ID</label>
                                            <input type="text" id="nationalId" class="form-control" placeholder="ID Number">
                                        </div>
                                    </div>
                                    <!-- Row 7: Address and Profile -->
                                    <div class="row mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Address</label>
                                            <textarea id="address" class="form-control" rows="2" placeholder="Physical or postal address"></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Profile Picture</label>
                                            <input type="file" id="profilePicFile" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                                            <small class="text-muted">Accepted: JPG, PNG, GIF, WEBP (max 5MB)</small>
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