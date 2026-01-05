<?php
/**
 * Manage Staff Page
 * HTML structure only - all logic in js/pages/staff.js (staffManagementController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-person-workspace"></i> Staff Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="staffManagementController.showStaffModal()"
                    data-permission="staff_create">
                    <i class="bi bi-plus-circle"></i> Add Staff
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="staffManagementController.showBulkImportModal()"
                    data-permission="staff_create">
                    <i class="bi bi-upload"></i> Bulk Import
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="staffManagementController.exportStaff()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Staff</h6>
                        <h3 class="text-success mb-0" id="totalStaffCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Teaching Staff</h6>
                        <h3 class="text-primary mb-0" id="teachingStaffCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Non-Teaching</h6>
                        <h3 class="text-info mb-0" id="nonTeachingCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">On Leave</h6>
                        <h3 class="text-warning mb-0" id="onLeaveCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#allStaff">
                    <i class="bi bi-people"></i> All Staff
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#teachingStaff">
                    <i class="bi bi-mortarboard"></i> Teaching
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#nonTeachingStaff">
                    <i class="bi bi-briefcase"></i> Non-Teaching
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#payrollTab">
                    <i class="bi bi-cash-stack"></i> Payroll
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- All Staff Tab -->
            <div class="tab-pane fade show active" id="allStaff">
                <!-- Filters and Search -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchStaff" class="form-control"
                                placeholder="Search by name, staff number, or ID..."
                                onkeyup="staffManagementController.searchStaff(this.value)">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select id="departmentFilter" class="form-select"
                            onchange="staffManagementController.filterByDepartment(this.value)">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="staffTypeFilter" class="form-select"
                            onchange="staffManagementController.filterByType(this.value)">
                            <option value="">All Types</option>
                            <option value="teaching">Teaching</option>
                            <option value="non-teaching">Non-Teaching</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="staffStatusFilter" class="form-select"
                            onchange="staffManagementController.filterByStatus(this.value)">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="on_leave">On Leave</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="roleFilter" class="form-select"
                            onchange="staffManagementController.filterByRole(this.value)">
                            <option value="">All Roles</option>
                        </select>
                    </div>
                </div>

                <!-- Staff Table -->
                <div class="table-responsive" id="staffTableContainer">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Staff No.</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="staffTableBody">
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border text-success" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="text-muted mt-2">Loading staff...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <span class="text-muted">Showing <span id="staffShowingFrom">0</span> to <span
                                id="staffShowingTo">0</span> of <span id="staffTotalRecords">0</span> staff</span>
                    </div>
                    <nav>
                        <ul class="pagination mb-0" id="staffPagination"></ul>
                    </nav>
                </div>
            </div>

            <!-- Teaching Staff Tab -->
            <div class="tab-pane fade" id="teachingStaff">
                <div id="teachingStaffContainer">
                    <p class="text-muted">Loading teaching staff...</p>
                </div>
            </div>

            <!-- Non-Teaching Staff Tab -->
            <div class="tab-pane fade" id="nonTeachingStaff">
                <div id="nonTeachingStaffContainer">
                    <p class="text-muted">Loading non-teaching staff...</p>
                </div>
            </div>

            <!-- Payroll Tab -->
            <div class="tab-pane fade" id="payrollTab">
                <div id="payrollContainer">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Payroll information and management will appear here.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Staff Modal (Create/Edit) -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="staffModalLabel">Add Staff Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="staffForm" enctype="multipart/form-data" onsubmit="staffManagementController.saveStaff(event)">
                <div class="modal-body">
                    <input type="hidden" id="staffId">

                    <!-- Personal Information -->
                    <h6 class="mb-3 text-success"><i class="bi bi-person"></i> Personal Information</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" id="staffFirstName" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" id="staffMiddleName" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" id="staffLastName" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select id="staffGender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" id="staffDOB" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">National ID</label>
                            <input type="text" id="staffNationalId" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Marital Status</label>
                            <select id="staffMaritalStatus" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="divorced">Divorced</option>
                                <option value="widowed">Widowed</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" id="staffProfilePic" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>
                    </div>

                    <!-- Employment Information -->
                    <h6 class="mb-3 mt-3 text-success"><i class="bi bi-briefcase"></i> Employment Information</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Staff Number</label>
                            <input type="text" id="staffNumber" class="form-control" placeholder="Auto-generated if empty" readonly>
                            <small class="text-muted">Auto-generated by system</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Staff Type <span class="text-danger">*</span></label>
                            <select id="staffType" class="form-select" required>
                                <option value="">Select</option>
                                <option value="teaching">Teaching</option>
                                <option value="non-teaching">Non-Teaching</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Department <span class="text-danger">*</span></label>
                            <select id="staffDepartment" class="form-select" required>
                                <option value="">Select Department</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Role/Position <span class="text-danger">*</span></label>
                            <select id="staffRole" class="form-select" required>
                                <option value="">Select Role</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Position Title <span class="text-danger">*</span></label>
                            <input type="text" id="staffPosition" class="form-control" placeholder="e.g. Senior Teacher" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Employment Date <span class="text-danger">*</span></label>
                            <input type="date" id="employmentDate" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Contract Type</label>
                            <select id="staffContractType" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="permanent">Permanent</option>
                                <option value="contract">Contract</option>
                                <option value="probation">Probation</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Supervisor</label>
                            <select id="staffSupervisor" class="form-select">
                                <option value="">-- Select Supervisor --</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">TSC Number (Teachers only)</label>
                            <input type="text" id="staffTscNo" class="form-control" placeholder="TSC Registration Number">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select id="staffStatus" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="on_leave">On Leave</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" id="staffPassword" class="form-control" placeholder="Leave blank for auto-generated">
                            <small class="text-muted">Auto-generated if blank</small>
                        </div>
                    </div>

                    <!-- Statutory Information -->
                    <h6 class="mb-3 mt-3 text-success"><i class="bi bi-file-earmark-text"></i> Statutory Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">NSSF Number <span class="text-danger">*</span></label>
                            <input type="text" id="staffNssfNo" class="form-control" placeholder="NSSF Number" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">KRA PIN <span class="text-danger">*</span></label>
                            <input type="text" id="staffKraPin" class="form-control" placeholder="A000000000X" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">NHIF Number <span class="text-danger">*</span></label>
                            <input type="text" id="staffNhifNo" class="form-control" placeholder="NHIF Number" required>
                        </div>
                    </div>

                    <!-- Financial Information -->
                    <h6 class="mb-3 mt-3 text-success"><i class="bi bi-bank"></i> Financial Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                            <input type="text" id="staffBankAccount" class="form-control" placeholder="Bank Account Number" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Salary (KES) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">KES</span>
                                <input type="number" step="0.01" id="staffSalary" class="form-control" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <h6 class="mb-3 mt-3 text-success"><i class="bi bi-telephone"></i> Contact Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" id="staffEmail" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" id="staffPhone" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Address</label>
                            <textarea id="staffAddress" class="form-control" rows="1" placeholder="Physical/Postal address"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Save Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Staff Details Modal -->
<div class="modal fade" id="viewStaffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Staff Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewStaffContent">
                <!-- Dynamic content loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Bulk Import Staff</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkImportStaffForm" onsubmit="staffManagementController.bulkImport(event)">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Upload a CSV or Excel file with staff data.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" id="bulkImportStaffFile" class="form-control" accept=".csv,.xlsx,.xls"
                            required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-upload"></i> Import Staff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Link Controller Script -->
<script src="/Kingsway/js/pages/staff.js"></script>