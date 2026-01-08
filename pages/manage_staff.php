<?php
/**
 * Manage Staff Page
 * HTML structure only - all logic in js/pages/staff.js (staffManagementController)
 * 
 * Role-based access:
 * - HR Manager: Full CRUD, payroll, contracts
 * - Headteacher: View all, approve leave, assign roles
 * - Deputy Heads: View teaching staff, manage workload
 * - Accountant/Bursar: View for payroll purposes
 * - Admin/Director: Full access
 * 
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-person-workspace"></i> Staff Management</h4>
            <div class="btn-group">
                <!-- Add Staff - HR Manager, Headteacher, Admin only -->
                <button class="btn btn-light btn-sm" onclick="staffManagementController.showStaffModal()"
                    data-permission="staff_create"
                    data-role="hr_manager,headteacher,school_administrator,admin,director">
                    <i class="bi bi-plus-circle"></i> Add Staff
                </button>
                <!-- Bulk Import - HR Manager, Admin only -->
                <button class="btn btn-outline-light btn-sm" onclick="staffManagementController.showBulkImportModal()"
                    data-permission="staff_create"
                    data-role="hr_manager,school_administrator,admin">
                    <i class="bi bi-upload"></i> Bulk Import
                </button>
                <!-- Export - HR, Finance, Leadership -->
                <button class="btn btn-outline-light btn-sm" onclick="staffManagementController.exportStaff()"
                    data-permission="staff_export"
                    data-role="hr_manager,headteacher,accountant,bursar,director,admin">
                    <i class="bi bi-download"></i> Export
                </button>
                <!-- Leave Management - HR and Headteacher -->
                <button class="btn btn-outline-light btn-sm" onclick="staffManagementController.showLeaveRequests()"
                    data-permission="staff_leave"
                    data-role="hr_manager,headteacher,admin">
                    <i class="bi bi-calendar-x"></i> Leave Requests
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

        <!-- HR & Finance Stats - HR Manager, Accountant, Director only -->
        <div class="row mb-4" data-role="hr_manager,accountant,bursar,director,admin">
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending Leave</h6>
                        <h3 class="text-danger mb-0" id="pendingLeaveCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-secondary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Contract Expiring</h6>
                        <h3 class="text-secondary mb-0" id="contractExpiringCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3" data-role="accountant,bursar,director,admin">
                <div class="card border-dark">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Monthly Payroll</h6>
                        <h3 class="text-dark mb-0" id="monthlyPayroll">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3" data-role="hr_manager,director,admin">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Vacancies</h6>
                        <h3 class="text-primary mb-0" id="vacanciesCount">0</h3>
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
            <!-- Payroll Tab - Finance and HR only -->
            <li class="nav-item" data-role="hr_manager,accountant,bursar,director,admin">
                <a class="nav-link" data-bs-toggle="tab" href="#payrollTab">
                    <i class="bi bi-cash-stack"></i> Payroll
                </a>
            </li>
            <!-- Contracts Tab - HR and Director only -->
            <li class="nav-item" data-role="hr_manager,director,admin">
                <a class="nav-link" data-bs-toggle="tab" href="#contractsTab">
                    <i class="bi bi-file-earmark-text"></i> Contracts
                </a>
            </li>
            <!-- Attendance Tab - HR and Leadership only -->
            <li class="nav-item" data-role="hr_manager,headteacher,deputy_head_academic,admin">
                <a class="nav-link" data-bs-toggle="tab" href="#attendanceTab">
                    <i class="bi bi-clock-history"></i> Attendance
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

            <!-- Payroll Tab - Finance and HR roles -->
            <div class="tab-pane fade" id="payrollTab" data-role="hr_manager,accountant,bursar,director,admin">
                <div id="payrollContainer">
                    <!-- Payroll Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Gross Payroll</h6>
                                    <h3 class="text-success" id="grossPayroll">KES 0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Deductions</h6>
                                    <h3 class="text-warning" id="totalDeductions">KES 0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-primary">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Net Payroll</h6>
                                    <h3 class="text-primary" id="netPayroll">KES 0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3" data-role="bursar,director,admin">
                            <div class="card border-danger">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Pending Approval</h6>
                                    <h3 class="text-danger" id="pendingPayrollApproval">0</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payroll Actions -->
                    <div class="d-flex justify-content-between mb-3">
                        <div class="btn-group">
                            <button class="btn btn-outline-primary" onclick="staffManagementController.runPayroll()"
                                    data-role="hr_manager,bursar,admin">
                                <i class="bi bi-calculator"></i> Run Payroll
                            </button>
                            <button class="btn btn-outline-success" onclick="staffManagementController.exportPayroll()"
                                    data-role="accountant,bursar,director,admin">
                                <i class="bi bi-file-earmark-excel"></i> Export Payroll
                            </button>
                            <button class="btn btn-outline-info" onclick="staffManagementController.showPayslips()">
                                <i class="bi bi-receipt"></i> View Payslips
                            </button>
                        </div>
                        <div data-role="bursar,director,admin">
                            <button class="btn btn-success" onclick="staffManagementController.approvePayroll()">
                                <i class="bi bi-check-circle"></i> Approve Payroll
                            </button>
                        </div>
                    </div>
                    
                    <!-- Payroll Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="payrollTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff No.</th>
                                    <th>Name</th>
                                    <th>Basic Salary</th>
                                    <th>Allowances</th>
                                    <th>Deductions</th>
                                    <th>Net Pay</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="payrollTableBody">
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="bi bi-cash-stack fs-1 d-block mb-2"></i>
                                        Loading payroll data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Contracts Tab - HR and Director only -->
            <div class="tab-pane fade" id="contractsTab" data-role="hr_manager,director,admin">
                <div id="contractsContainer">
                    <!-- Contract Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Active Contracts</h6>
                                    <h3 class="text-success" id="activeContracts">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Expiring (30 days)</h6>
                                    <h3 class="text-warning" id="expiringContracts">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-danger">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Expired</h6>
                                    <h3 class="text-danger" id="expiredContracts">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-info">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Pending Renewal</h6>
                                    <h3 class="text-info" id="pendingRenewal">0</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contract Actions -->
                    <div class="d-flex justify-content-between mb-3">
                        <div class="btn-group">
                            <button class="btn btn-primary" onclick="staffManagementController.showContractModal()">
                                <i class="bi bi-plus-circle"></i> New Contract
                            </button>
                            <button class="btn btn-outline-warning" onclick="staffManagementController.showRenewalQueue()">
                                <i class="bi bi-arrow-repeat"></i> Renewal Queue
                            </button>
                        </div>
                        <button class="btn btn-outline-secondary" onclick="staffManagementController.exportContracts()">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                    
                    <!-- Contracts Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="contractsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff No.</th>
                                    <th>Name</th>
                                    <th>Contract Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="contractsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="bi bi-file-earmark-text fs-1 d-block mb-2"></i>
                                        Loading contracts...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Tab - HR and Leadership only -->
            <div class="tab-pane fade" id="attendanceTab" data-role="hr_manager,headteacher,deputy_head_academic,admin">
                <div id="staffAttendanceContainer">
                    <!-- Attendance Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Present Today</h6>
                                    <h3 class="text-success" id="presentToday">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">On Leave</h6>
                                    <h3 class="text-warning" id="staffOnLeave">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-danger">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Absent</h6>
                                    <h3 class="text-danger" id="absentToday">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-info">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Late Arrivals</h6>
                                    <h3 class="text-info" id="lateArrivals">0</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Actions -->
                    <div class="d-flex justify-content-between mb-3">
                        <div class="btn-group">
                            <button class="btn btn-primary" onclick="staffManagementController.markAttendance()"
                                    data-role="hr_manager,admin">
                                <i class="bi bi-check2-square"></i> Mark Attendance
                            </button>
                            <button class="btn btn-outline-info" onclick="staffManagementController.showAttendanceReport()">
                                <i class="bi bi-graph-up"></i> View Report
                            </button>
                        </div>
                        <div class="input-group" style="width: 250px;">
                            <input type="date" class="form-control" id="attendanceDate" 
                                   value="<?php echo date('Y-m-d'); ?>">
                            <button class="btn btn-outline-secondary" onclick="staffManagementController.loadAttendance()">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Attendance Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="staffAttendanceTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff No.</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Check-In</th>
                                    <th>Check-Out</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="staffAttendanceTableBody">
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="bi bi-clock-history fs-1 d-block mb-2"></i>
                                        Loading attendance data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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