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
            <form id="staffForm" onsubmit="staffManagementController.saveStaff(event)">
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
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" id="staffDOB" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">National ID</label>
                            <input type="text" id="staffNationalId" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Staff Type <span class="text-danger">*</span></label>
                            <select id="staffType" class="form-select" required>
                                <option value="">Select</option>
                                <option value="teaching">Teaching</option>
                                <option value="non-teaching">Non-Teaching</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    <!-- Employment Information -->
                    <h6 class="mb-3 mt-3 text-success"><i class="bi bi-briefcase"></i> Employment Information</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Staff Number <span class="text-danger">*</span></label>
                            <input type="text" id="staffNumber" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Department</label>
                            <select id="staffDepartment" class="form-select">
                                <option value="">Select Department</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Role/Position</label>
                            <select id="staffRole" class="form-select">
                                <option value="">Select Role</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Employment Date</label>
                            <input type="date" id="employmentDate" class="form-control">
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
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select id="staffStatus" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="on_leave">On Leave</option>
                                <option value="inactive">Inactive</option>
                            </select>
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