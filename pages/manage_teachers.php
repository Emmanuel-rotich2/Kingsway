<?php
/**
 * Teacher Management Page – Production UI
 * All logic handled in: js/pages/manage_teachers.js (manageTeachersController)
 * UI Theme: Green / White (Academic Professional)
 *
 * Features:
 * - Full CRUD for teaching staff
 * - KPI statistics dashboard
 * - Filterable, paginated data table
 * - Tabbed add/edit modal (Personal, Professional, Assignments, Statutory)
 * - Comprehensive view modal with teacher profile
 *
 * API endpoints:
 * - GET  /api/staff?staff_type=teaching
 * - GET  /api/staff/{id}
 * - POST /api/staff
 * - PUT  /api/staff/{id}
 * - DELETE /api/staff/{id}
 * - GET  /api/staff/stats
 * - GET  /api/academic/classes/list
 * - GET  /api/academic/learning-areas/list
 * - GET  /api/staff/assignments/current
 */
?>

<style>
/* =========================================================
   DESIGN TOKENS
========================================================= */
:root {
    --acad-primary: #198754;
    --acad-primary-dark: #146c43;
    --acad-primary-soft: #d1e7dd;
    --acad-bg-light: #f8f9fa;
    --acad-white: #ffffff;
    --acad-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.academic-header {
    background: linear-gradient(135deg, #198754, #20c997) !important;
    color: #fff;
    border-radius: 12px;
    padding: 1.75rem 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--acad-shadow);
}

.academic-card {
    background: var(--acad-white);
    border-radius: 12px;
    border-left: 4px solid var(--acad-primary);
    box-shadow: var(--acad-shadow);
    margin-bottom: 1.75rem;
}

.stat-card {
    background: var(--acad-primary-soft);
    border-radius: 10px;
    padding: 1.2rem;
    height: 100%;
    text-align: center;
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    font-size: 1.5rem;
    color: var(--acad-primary);
    margin-bottom: 0.25rem;
}

.stat-number {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--acad-primary-dark);
}

.btn-academic {
    background: var(--acad-primary);
    color: #fff;
    border: none;
}

.btn-academic:hover {
    background: var(--acad-primary-dark);
    color: #fff;
}

.table-academic thead {
    background: var(--acad-primary);
    color: #fff;
}

.table-academic thead th {
    font-weight: 600;
    white-space: nowrap;
    border: none;
}

.table-academic tbody tr {
    transition: background-color 0.15s ease;
}

.table-academic tbody tr:hover {
    background-color: var(--acad-primary-soft);
}

/* Tabs */
.nav-tabs-academic {
    border-bottom: 2px solid #dee2e6;
}

.nav-tabs-academic .nav-link {
    border: none;
    font-weight: 500;
    color: #6c757d;
    padding: 0.75rem 1.25rem;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
}

.nav-tabs-academic .nav-link:hover {
    color: var(--acad-primary-dark);
    border-bottom-color: #dee2e6;
}

.nav-tabs-academic .nav-link.active {
    color: var(--acad-primary);
    border-bottom-color: var(--acad-primary);
    background: transparent;
}

/* Status badges */
.badge-active {
    background-color: #198754;
    color: #fff;
}

.badge-inactive {
    background-color: #dc3545;
    color: #fff;
}

.badge-on-leave {
    background-color: #ffc107;
    color: #212529;
}

/* Teacher avatar */
.teacher-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--acad-primary-soft);
    color: var(--acad-primary-dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.teacher-avatar-lg {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--acad-primary-soft);
    color: var(--acad-primary-dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.75rem;
    border: 3px solid var(--acad-primary);
}

/* Profile header in view modal */
.profile-header {
    background: linear-gradient(135deg, #198754, #20c997);
    color: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.profile-detail-card {
    background: var(--acad-bg-light);
    border-radius: 10px;
    padding: 1rem;
    height: 100%;
}

.profile-detail-card .detail-label {
    font-size: 0.75rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.profile-detail-card .detail-value {
    font-weight: 600;
    color: #212529;
}

/* Action dropdown */
.action-dropdown .dropdown-toggle::after {
    display: none;
}

/* Empty state */
.empty-state {
    padding: 3rem 1rem;
    text-align: center;
    color: #6c757d;
}

.empty-state i {
    font-size: 3rem;
    color: #dee2e6;
    margin-bottom: 1rem;
}
</style>

<!-- =======================================================
 HEADER
======================================================= -->
<div class="academic-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-mortarboard me-2"></i>Teacher Management
        </h2>
        <small class="opacity-75">
            Manage teaching staff, assignments, and professional records
        </small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <div class="btn-group">
            <button class="btn btn-light btn-sm" id="btnExportTeachers" title="Export to CSV">
                <i class="bi bi-download me-1"></i>Export
            </button>
            <button class="btn btn-light btn-sm" id="btnPrintTeachers" title="Print teacher list">
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
        <button class="btn btn-light btn-sm fw-semibold" id="btnAddTeacher">
            <i class="bi bi-plus-circle me-1"></i>Add Teacher
        </button>
    </div>
</div>

<!-- =======================================================
 KPI STATISTICS
======================================================= -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-number" id="statTotalTeachers">--</div>
            <small class="text-muted">Total Teachers</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-person-check"></i></div>
            <div class="stat-number" id="statActiveTeachers">--</div>
            <small class="text-muted">Active</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-number" id="statPresentToday">--</div>
            <small class="text-muted">Present Today</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-book"></i></div>
            <div class="stat-number" id="statSubjectsCovered">--</div>
            <small class="text-muted">Subjects Covered</small>
        </div>
    </div>
</div>

<!-- =======================================================
 FILTER BAR
======================================================= -->
<div class="academic-card p-3 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="searchTeachers"
                       placeholder="Name, staff no, email...">
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Department</label>
            <select class="form-select" id="filterDepartment">
                <option value="">All Departments</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold">Status</label>
            <select class="form-select" id="filterStatus">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="on_leave">On Leave</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold">Subject</label>
            <select class="form-select" id="filterSubject">
                <option value="">All Subjects</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-secondary w-100" id="btnClearFilters">
                <i class="bi bi-x-circle me-1"></i>Clear
            </button>
        </div>
    </div>
</div>

<!-- =======================================================
 DATA TABLE
======================================================= -->
<div class="academic-card p-3">
    <div class="table-responsive">
        <table class="table table-hover table-bordered table-academic mb-0" id="teachersTable">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Staff No</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Subjects</th>
                    <th>Class Assignments</th>
                    <th>Status</th>
                    <th style="width: 90px;">Actions</th>
                </tr>
            </thead>
            <tbody id="teachersTableBody">
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm text-success me-2"></div>
                        Loading teachers...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <small class="text-muted">
            Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span>
            of <span id="totalRecords">0</span> records
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0" id="teachersPagination"></ul>
        </nav>
    </div>
</div>

<!-- =======================================================
 ADD / EDIT TEACHER MODAL
======================================================= -->
<div class="modal fade" id="teacherFormModal" tabindex="-1" aria-labelledby="teacherFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #198754, #20c997); color: #fff;">
                <h5 class="modal-title" id="teacherFormModalLabel">
                    <i class="bi bi-mortarboard me-2"></i>Add Teacher
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="teacherForm" novalidate>
                    <input type="hidden" id="formTeacherId">

                    <!-- Tabs Navigation -->
                    <ul class="nav nav-tabs nav-tabs-academic mb-3" id="teacherFormTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-personal" data-bs-toggle="tab"
                                    data-bs-target="#pane-personal" type="button" role="tab">
                                <i class="bi bi-person me-1"></i>Personal
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-professional" data-bs-toggle="tab"
                                    data-bs-target="#pane-professional" type="button" role="tab">
                                <i class="bi bi-briefcase me-1"></i>Professional
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-assignments" data-bs-toggle="tab"
                                    data-bs-target="#pane-assignments" type="button" role="tab">
                                <i class="bi bi-diagram-3 me-1"></i>Assignments
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-statutory" data-bs-toggle="tab"
                                    data-bs-target="#pane-statutory" type="button" role="tab">
                                <i class="bi bi-file-earmark-text me-1"></i>Statutory
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Panes -->
                    <div class="tab-content" id="teacherFormTabContent">

                        <!-- Tab 1: Personal Information -->
                        <div class="tab-pane fade show active" id="pane-personal" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="formFirstName" class="form-label fw-semibold">
                                        First Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="formFirstName"
                                           placeholder="Enter first name" required>
                                    <div class="invalid-feedback">First name is required.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="formLastName" class="form-label fw-semibold">
                                        Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="formLastName"
                                           placeholder="Enter last name" required>
                                    <div class="invalid-feedback">Last name is required.</div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="formEmail" class="form-label fw-semibold">Email</label>
                                    <input type="email" class="form-control" id="formEmail"
                                           placeholder="teacher@school.ac.ke">
                                    <div class="invalid-feedback">Enter a valid email address.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="formPhone" class="form-label fw-semibold">Phone Number</label>
                                    <input type="tel" class="form-control" id="formPhone"
                                           placeholder="e.g. 0712345678">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="formGender" class="form-label fw-semibold">Gender</label>
                                    <select class="form-select" id="formGender">
                                        <option value="">-- Select --</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="formDob" class="form-label fw-semibold">Date of Birth</label>
                                    <input type="date" class="form-control" id="formDob">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="formMaritalStatus" class="form-label fw-semibold">Marital Status</label>
                                    <select class="form-select" id="formMaritalStatus">
                                        <option value="">-- Select --</option>
                                        <option value="single">Single</option>
                                        <option value="married">Married</option>
                                        <option value="divorced">Divorced</option>
                                        <option value="widowed">Widowed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="formAddress" class="form-label fw-semibold">Address</label>
                                <textarea class="form-control" id="formAddress" rows="2"
                                          placeholder="Postal or residential address"></textarea>
                            </div>
                        </div>

                        <!-- Tab 2: Professional Information -->
                        <div class="tab-pane fade" id="pane-professional" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="formTscNo" class="form-label fw-semibold">TSC Number</label>
                                    <input type="text" class="form-control" id="formTscNo"
                                           placeholder="e.g. TSC/12345">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="formStaffNo" class="form-label fw-semibold">Staff Number</label>
                                    <input type="text" class="form-control" id="formStaffNo"
                                           placeholder="Auto-generated" readonly>
                                    <small class="text-muted">Automatically generated by the system.</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="formDepartment" class="form-label fw-semibold">
                                        Department <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="formDepartment" required>
                                        <option value="">-- Select Department --</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a department.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="formPosition" class="form-label fw-semibold">Position</label>
                                    <select class="form-select" id="formPosition">
                                        <option value="">-- Select Position --</option>
                                        <option value="Staff">Staff</option>
                                        <option value="Subject Teacher">Subject Teacher</option>
                                        <option value="Class Teacher">Class Teacher</option>
                                        <option value="Senior Teacher">Senior Teacher</option>
                                        <option value="Head of Department">Head of Department</option>
                                        <option value="Deputy Principal">Deputy Principal</option>
                                        <option value="Principal">Principal</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="formEmploymentDate" class="form-label fw-semibold">
                                        Employment Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="formEmploymentDate" required>
                                    <div class="invalid-feedback">Employment date is required.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="formContractType" class="form-label fw-semibold">Contract Type</label>
                                    <select class="form-select" id="formContractType">
                                        <option value="">-- Select --</option>
                                        <option value="permanent">Permanent</option>
                                        <option value="contract">Contract</option>
                                        <option value="temporary">Temporary</option>
                                        <option value="intern">Intern</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="formQualifications" class="form-label fw-semibold">Qualifications</label>
                                <textarea class="form-control" id="formQualifications" rows="2"
                                          placeholder="e.g. B.Ed Mathematics, PGDE, M.Ed Curriculum Studies"></textarea>
                            </div>
                        </div>

                        <!-- Tab 3: Assignments -->
                        <div class="tab-pane fade" id="pane-assignments" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Subject Assignments</label>
                                <select class="form-select" id="formSubjectAssignments" multiple size="5">
                                    <!-- Populated dynamically from /api/academic/learning-areas/list -->
                                </select>
                                <small class="text-muted">
                                    Hold Ctrl (Cmd on Mac) to select multiple subjects.
                                </small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Class Assignments</label>
                                <select class="form-select" id="formClassAssignments" multiple size="5">
                                    <!-- Populated dynamically from /api/academic/classes/list -->
                                </select>
                                <small class="text-muted">
                                    Hold Ctrl (Cmd on Mac) to select multiple classes.
                                </small>
                            </div>
                            <div id="currentAssignmentsSummary" class="mt-3" style="display: none;">
                                <h6 class="fw-semibold mb-2">
                                    <i class="bi bi-info-circle me-1"></i>Current Assignments
                                </h6>
                                <div id="assignmentsList" class="list-group list-group-flush"></div>
                            </div>
                        </div>

                        <!-- Tab 4: Statutory Information -->
                        <div class="tab-pane fade" id="pane-statutory" role="tabpanel">
                            <div class="alert alert-info border-0 mb-3"
                                 style="background: var(--acad-primary-soft); color: var(--acad-primary-dark);">
                                <i class="bi bi-shield-lock me-2"></i>
                                Statutory information is confidential and only accessible to authorized personnel.
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="formKraPin" class="form-label fw-semibold">KRA PIN</label>
                                    <input type="text" class="form-control" id="formKraPin"
                                           placeholder="e.g. A012345678Z">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="formNhifNo" class="form-label fw-semibold">NHIF Number</label>
                                    <input type="text" class="form-control" id="formNhifNo"
                                           placeholder="NHIF membership number">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="formNssfNo" class="form-label fw-semibold">NSSF Number</label>
                                    <input type="text" class="form-control" id="formNssfNo"
                                           placeholder="NSSF membership number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="formBankAccount" class="form-label fw-semibold">Bank Account</label>
                                    <input type="text" class="form-control" id="formBankAccount"
                                           placeholder="Bank name - Account number">
                                </div>
                            </div>
                        </div>

                    </div><!-- /tab-content -->
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-academic" id="btnSaveTeacher">
                    <i class="bi bi-check-circle me-1"></i>Save Teacher
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
 VIEW TEACHER MODAL
======================================================= -->
<div class="modal fade" id="viewTeacherModal" tabindex="-1" aria-labelledby="viewTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #198754, #20c997); color: #fff;">
                <h5 class="modal-title" id="viewTeacherModalLabel">
                    <i class="bi bi-person-badge me-2"></i>Teacher Profile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewTeacherBody">

                <!-- Profile Header -->
                <div class="profile-header d-flex align-items-center gap-3 flex-wrap">
                    <div class="teacher-avatar-lg" id="viewAvatar">--</div>
                    <div class="flex-grow-1">
                        <h4 class="mb-1" id="viewFullName">--</h4>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-light text-dark" id="viewStaffNo">--</span>
                            <span class="badge" id="viewStatusBadge">--</span>
                            <span class="badge bg-light text-dark" id="viewPosition">--</span>
                        </div>
                        <small class="opacity-75 mt-1 d-block" id="viewDepartment">--</small>
                    </div>
                    <div class="text-end d-none d-md-block">
                        <button class="btn btn-light btn-sm me-1" id="btnEditFromView" title="Edit teacher">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                        <button class="btn btn-light btn-sm" id="btnPrintProfile" title="Print profile">
                            <i class="bi bi-printer me-1"></i>Print
                        </button>
                    </div>
                </div>

                <!-- Profile Tabs -->
                <ul class="nav nav-tabs nav-tabs-academic mb-3" id="viewProfileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab"
                                data-bs-target="#viewPane-overview" type="button" role="tab">
                            <i class="bi bi-grid me-1"></i>Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab"
                                data-bs-target="#viewPane-assignments" type="button" role="tab">
                            <i class="bi bi-diagram-3 me-1"></i>Assignments
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab"
                                data-bs-target="#viewPane-statutory" type="button" role="tab">
                            <i class="bi bi-file-earmark-lock me-1"></i>Statutory
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="viewProfileTabContent">

                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="viewPane-overview" role="tabpanel">
                        <div class="row g-3">
                            <!-- Personal Details Column -->
                            <div class="col-md-6">
                                <h6 class="fw-bold text-muted mb-3">
                                    <i class="bi bi-person me-1"></i>Personal Information
                                </h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">First Name</div>
                                            <div class="detail-value" id="viewFirstName">--</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Last Name</div>
                                            <div class="detail-value" id="viewLastName">--</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Email</div>
                                            <div class="detail-value" id="viewEmail">--</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Phone</div>
                                            <div class="detail-value" id="viewPhone">--</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Gender</div>
                                            <div class="detail-value" id="viewGender">--</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Date of Birth</div>
                                            <div class="detail-value" id="viewDob">--</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Marital Status</div>
                                            <div class="detail-value" id="viewMaritalStatus">--</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Address</div>
                                            <div class="detail-value" id="viewAddress">--</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Professional Details Column -->
                            <div class="col-md-6">
                                <h6 class="fw-bold text-muted mb-3">
                                    <i class="bi bi-briefcase me-1"></i>Professional Information
                                </h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">TSC Number</div>
                                            <div class="detail-value" id="viewTscNo">--</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Staff Number</div>
                                            <div class="detail-value" id="viewStaffNoDetail">--</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Department</div>
                                            <div class="detail-value" id="viewDeptDetail">--</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Position</div>
                                            <div class="detail-value" id="viewPositionDetail">--</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Employment Date</div>
                                            <div class="detail-value" id="viewEmploymentDate">--</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Contract Type</div>
                                            <div class="detail-value" id="viewContractType">--</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="profile-detail-card">
                                            <div class="detail-label">Qualifications</div>
                                            <div class="detail-value" id="viewQualifications">--</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Assignments Tab -->
                    <div class="tab-pane fade" id="viewPane-assignments" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6 class="fw-bold text-muted mb-3">
                                    <i class="bi bi-book me-1"></i>Subject Assignments
                                </h6>
                                <div id="viewSubjectAssignments">
                                    <p class="text-muted">No subject assignments found.</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold text-muted mb-3">
                                    <i class="bi bi-building me-1"></i>Class Assignments
                                </h6>
                                <div id="viewClassAssignments">
                                    <p class="text-muted">No class assignments found.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statutory Tab -->
                    <div class="tab-pane fade" id="viewPane-statutory" role="tabpanel">
                        <div class="alert alert-info border-0 mb-3"
                             style="background: var(--acad-primary-soft); color: var(--acad-primary-dark);">
                            <i class="bi bi-shield-lock me-2"></i>
                            This information is confidential and restricted to authorized personnel.
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="profile-detail-card">
                                    <div class="detail-label">KRA PIN</div>
                                    <div class="detail-value" id="viewKraPin">--</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-detail-card">
                                    <div class="detail-label">NHIF Number</div>
                                    <div class="detail-value" id="viewNhifNo">--</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-detail-card">
                                    <div class="detail-label">NSSF Number</div>
                                    <div class="detail-value" id="viewNssfNo">--</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-detail-card">
                                    <div class="detail-label">Bank Account</div>
                                    <div class="detail-value" id="viewBankAccount">--</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /tab-content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
 DELETE CONFIRMATION MODAL
======================================================= -->
<div class="modal fade" id="deleteTeacherModal" tabindex="-1" aria-labelledby="deleteTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title" id="deleteTeacherModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>Confirm Deletion
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="deleteTeacherId">
                <p class="mb-1">Are you sure you want to delete this teacher?</p>
                <p class="fw-semibold mb-0" id="deleteTeacherName">--</p>
                <small class="text-muted">This action cannot be undone.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmDelete">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
 TOAST NOTIFICATIONS
======================================================= -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 11000;">
    <div id="teacherToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="bi bi-bell me-2" id="toastIcon"></i>
            <strong class="me-auto" id="toastTitle">Notice</strong>
            <small class="text-muted">just now</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<!-- =======================================================
 SCRIPTS
======================================================= -->
<script src="/Kingsway/js/pages/manage_teachers.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof manageTeachersController !== 'undefined') {
            manageTeachersController.init();
        } else {
            console.warn('[Manage Teachers] manageTeachersController not found in manage_teachers.js');
        }
    });
</script>
