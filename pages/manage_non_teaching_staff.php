<?php
/**
 * Non-Teaching Staff Management Page - Pure UI/UX Layout
 * Controller: manage_non_teaching_staff.js
 * Authentication: JWT via api.js + backend middleware
 * Role-based access: JavaScript AuthContext + permission system
 */
?>
<div class="non-teaching-staff-container">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1"><i class="bi bi-person-badge me-2"></i>Non-Teaching Staff Management</h4>
                <p class="text-muted mb-0">Manage administrative, support, and auxiliary staff members</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" id="exportStaffBtn" data-permission-module="staff" data-permission-action="export">
                    <i class="bi bi-download me-1"></i>Export
                </button>
                <button class="btn btn-primary" id="addStaffBtn" data-permission-module="staff" data-permission-action="create">
                    <i class="bi bi-plus-circle me-1"></i>Add Staff
                </button>
            </div>
        </div>
    </div>

    <!-- KPI Statistics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 id="statTotalStaff">0</h3>
                    <p class="mb-0">Total Staff</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 id="statActiveStaff">0</h3>
                    <p class="mb-0">Active</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3 id="statDepartments">0</h3>
                    <p class="mb-0">Departments</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h3 id="statPresentToday">0</h3>
                    <p class="mb-0">Present Today</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-semibold mb-1">Search</label>
                    <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="Name, staff no, email...">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label fw-semibold mb-1">Department</label>
                    <select class="form-select form-select-sm" id="filterDepartment">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label fw-semibold mb-1">Category</label>
                    <select class="form-select form-select-sm" id="filterCategory">
                        <option value="">All Categories</option>
                        <option value="Administrative">Administrative</option>
                        <option value="Security">Security</option>
                        <option value="Kitchen">Kitchen</option>
                        <option value="Transport">Transport</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Accounts">Accounts</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label fw-semibold mb-1">Status</label>
                    <select class="form-select form-select-sm" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="on_leave">On Leave</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label fw-semibold mb-1">Contract</label>
                    <select class="form-select form-select-sm" id="filterContract">
                        <option value="">All Contracts</option>
                        <option value="permanent">Permanent</option>
                        <option value="contract">Contract</option>
                        <option value="temporary">Temporary</option>
                        <option value="intern">Intern</option>
                    </select>
                </div>
                <div class="col-lg-1 col-md-12">
                    <button class="btn btn-outline-secondary btn-sm w-100" id="resetFiltersBtn">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="staffTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Staff No</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Category</th>
                            <th>Position</th>
                            <th>Contract</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody">
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                Loading non-teaching staff...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Staff Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffModalLabel">Add Non-Teaching Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="staffForm">
                    <input type="hidden" id="staffId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="firstName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="lastName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select class="form-select" id="departmentSelect">
                                <option value="">Select Department</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position/Role</label>
                            <input type="text" class="form-control" id="role">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select" id="category">
                                <option value="">Select Category</option>
                                <option value="Administrative">Administrative</option>
                                <option value="Security">Security</option>
                                <option value="Kitchen">Kitchen</option>
                                <option value="Transport">Transport</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Accounts">Accounts</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contract Type</label>
                            <select class="form-select" id="contractType">
                                <option value="permanent">Permanent</option>
                                <option value="contract">Contract</option>
                                <option value="temporary">Temporary</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="statusSelect">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveStaffBtn">Save Staff</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_non_teaching_staff.js"></script>