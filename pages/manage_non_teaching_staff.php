<?php
/**
 * Non-Teaching Staff Management Page - Production UI
 * All logic handled in: js/pages/manage_non_teaching_staff.js
 * Controller: manageNonTeachingStaffController
 *
 * API Endpoints:
 *   GET    /api/staff?staff_type=non-teaching  - list non-teaching staff
 *   GET    /api/staff/{id}                     - single staff record
 *   POST   /api/staff                          - create staff
 *   PUT    /api/staff/{id}                     - update staff
 *   DELETE /api/staff/{id}                     - delete staff
 *   GET    /api/staff/stats                    - staff statistics
 */
?>

<style>
/* =========================================================
   DESIGN TOKENS - Non-Teaching Staff
========================================================= */
:root {
    --nts-primary: #0d6efd;
    --nts-accent: #6610f2;
    --nts-primary-soft: #e7f1ff;
    --nts-accent-soft: #f0e6ff;
    --nts-success: #198754;
    --nts-warning: #fd7e14;
    --nts-danger: #dc3545;
    --nts-muted: #6c757d;
    --nts-bg-light: #f8f9fa;
    --nts-white: #ffffff;
    --nts-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    --nts-shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.1);
    --nts-radius: 12px;
}

/* ---------- Page Header ---------- */
.nts-header {
    background: linear-gradient(135deg, #0d6efd, #6610f2) !important;
    color: #fff;
    border-radius: var(--nts-radius);
    padding: 1.75rem 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--nts-shadow-lg);
}

.nts-header h2 {
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.nts-header small {
    opacity: 0.85;
}

/* ---------- KPI Stat Cards ---------- */
.nts-stat-card {
    background: var(--nts-white);
    border-radius: var(--nts-radius);
    padding: 1.25rem 1.5rem;
    box-shadow: var(--nts-shadow);
    border-left: 4px solid var(--nts-primary);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
}

.nts-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--nts-shadow-lg);
}

.nts-stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.nts-stat-card .stat-number {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.2;
}

.nts-stat-card .stat-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--nts-muted);
    font-weight: 600;
}

/* ---------- Filter Bar ---------- */
.nts-filter-card {
    background: var(--nts-white);
    border-radius: var(--nts-radius);
    border-left: 4px solid var(--nts-accent);
    box-shadow: var(--nts-shadow);
    padding: 1rem 1.5rem;
    margin-bottom: 1.75rem;
}

/* ---------- Data Table Card ---------- */
.nts-table-card {
    background: var(--nts-white);
    border-radius: var(--nts-radius);
    box-shadow: var(--nts-shadow);
    overflow: hidden;
    margin-bottom: 1.75rem;
}

.nts-table-card .table {
    margin-bottom: 0;
}

.nts-table-card .table thead {
    background: linear-gradient(135deg, #0d6efd, #6610f2) !important;
    color: #fff;
}

.nts-table-card .table thead th {
    font-weight: 600;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border: none;
    padding: 0.85rem 0.75rem;
    vertical-align: middle;
    white-space: nowrap;
}

.nts-table-card .table tbody tr {
    transition: background 0.15s ease;
}

.nts-table-card .table tbody tr:hover {
    background: var(--nts-primary-soft);
}

.nts-table-card .table tbody td {
    vertical-align: middle;
    padding: 0.7rem 0.75rem;
    font-size: 0.88rem;
}

/* ---------- Avatar ---------- */
.staff-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.82rem;
    color: #fff;
    flex-shrink: 0;
}

/* ---------- Status Badges ---------- */
.badge-status {
    padding: 0.3rem 0.65rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.badge-active    { background: #d1e7dd; color: #0f5132; }
.badge-inactive  { background: #f8d7da; color: #842029; }
.badge-on-leave  { background: #fff3cd; color: #664d03; }
.badge-suspended { background: #e2e3e5; color: #41464b; }

/* ---------- Action Buttons ---------- */
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: transform 0.15s ease;
    font-size: 0.85rem;
    padding: 0;
}

.action-btn:hover { transform: scale(1.12); }
.action-btn-view   { background: #e7f1ff; color: #0d6efd; }
.action-btn-edit   { background: #fff3cd; color: #997404; }
.action-btn-delete { background: #f8d7da; color: #dc3545; }

/* ---------- Modal Enhancements ---------- */
.nts-modal .modal-content {
    border: none;
    border-radius: var(--nts-radius);
    box-shadow: var(--nts-shadow-lg);
}

.nts-modal .modal-header {
    background: linear-gradient(135deg, #0d6efd, #6610f2) !important;
    color: #fff;
    border-radius: var(--nts-radius) var(--nts-radius) 0 0;
    padding: 1.25rem 1.5rem;
}

.nts-modal .modal-header .btn-close {
    filter: brightness(0) invert(1);
}

.nts-modal .nav-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--nts-muted);
    font-weight: 600;
    font-size: 0.88rem;
    padding: 0.65rem 1rem;
    transition: all 0.2s ease;
}

.nts-modal .nav-tabs .nav-link:hover {
    border-bottom-color: #dee2e6;
    color: #333;
}

.nts-modal .nav-tabs .nav-link.active {
    border-bottom-color: var(--nts-primary);
    color: var(--nts-primary);
    background: transparent;
}

.nts-modal .form-label {
    font-weight: 600;
    font-size: 0.85rem;
    color: #495057;
}

/* ---------- View Modal Profile ---------- */
.profile-header {
    background: linear-gradient(135deg, #0d6efd, #6610f2) !important;
    color: #fff;
    padding: 2rem;
    border-radius: var(--nts-radius) var(--nts-radius) 0 0;
    text-align: center;
}

.profile-avatar-lg {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.75rem;
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    background: rgba(255, 255, 255, 0.2);
}

.profile-detail-label {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--nts-muted);
    font-weight: 600;
    margin-bottom: 0.15rem;
}

.profile-detail-value {
    font-size: 0.92rem;
    color: #212529;
    font-weight: 500;
}

/* ---------- Pagination ---------- */
.nts-pagination .page-link {
    border-radius: 8px;
    margin: 0 2px;
    font-size: 0.85rem;
    color: var(--nts-primary);
    border: 1px solid #dee2e6;
}

.nts-pagination .page-item.active .page-link {
    background: var(--nts-primary);
    border-color: var(--nts-primary);
    color: #fff;
}

/* ---------- Loading Skeleton ---------- */
.skeleton-row td {
    position: relative;
    overflow: hidden;
}

.skeleton-bar {
    height: 14px;
    border-radius: 4px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s ease-in-out infinite;
}

@keyframes shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ---------- Empty State ---------- */
.nts-empty-state {
    text-align: center;
    padding: 3.5rem 1.5rem;
}

.nts-empty-state .empty-icon {
    font-size: 3.5rem;
    color: #ced4da;
    margin-bottom: 1rem;
}

/* ---------- Responsive ---------- */
@media (max-width: 768px) {
    .nts-header { padding: 1.25rem 1rem; }
    .nts-stat-card { margin-bottom: 0.75rem; }
    .nts-stat-card .stat-number { font-size: 1.4rem; }
}
</style>

<!-- =======================================================
     HEADER
======================================================= -->
<div class="nts-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-person-badge me-2"></i>Non-Teaching Staff Management
        </h2>
        <small>Manage administrative, support, and auxiliary staff members</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <div class="btn-group">
            <button class="btn btn-light btn-sm" id="exportStaffBtn" title="Export to CSV">
                <i class="bi bi-download me-1"></i>Export
            </button>
            <button class="btn btn-light btn-sm" id="printStaffBtn" title="Print staff list">
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
        <button class="btn btn-warning btn-sm fw-semibold" id="addStaffBtn">
            <i class="bi bi-plus-circle me-1"></i>Add Staff
        </button>
    </div>
</div>

<!-- =======================================================
     KPI STATISTICS
======================================================= -->
<div class="row g-3 mb-4">
    <!-- Total Staff -->
    <div class="col-lg-3 col-md-6">
        <div class="nts-stat-card" style="border-left-color: #0d6efd;">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: #e7f1ff; color: #0d6efd;">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="stat-number" id="statTotalStaff">0</div>
                    <div class="stat-label">Total Staff</div>
                </div>
            </div>
        </div>
    </div>
    <!-- Active -->
    <div class="col-lg-3 col-md-6">
        <div class="nts-stat-card" style="border-left-color: #198754;">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: #d1e7dd; color: #198754;">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div>
                    <div class="stat-number" id="statActiveStaff" style="color: #198754;">0</div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
        </div>
    </div>
    <!-- Department Distribution -->
    <div class="col-lg-3 col-md-6">
        <div class="nts-stat-card" style="border-left-color: #6610f2;">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: #f0e6ff; color: #6610f2;">
                    <i class="bi bi-diagram-3-fill"></i>
                </div>
                <div>
                    <div class="stat-number" id="statDepartments" style="color: #6610f2;">0</div>
                    <div class="stat-label">Departments</div>
                </div>
            </div>
        </div>
    </div>
    <!-- Present Today -->
    <div class="col-lg-3 col-md-6">
        <div class="nts-stat-card" style="border-left-color: #fd7e14;">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: #fff3e0; color: #fd7e14;">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div>
                    <div class="stat-number" id="statPresentToday" style="color: #fd7e14;">0</div>
                    <div class="stat-label">Present Today</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
     FILTER BAR
======================================================= -->
<div class="nts-filter-card">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label fw-semibold mb-1">
                <i class="bi bi-search me-1"></i>Search
            </label>
            <input type="text" class="form-control form-control-sm" id="filterSearch"
                   placeholder="Name, staff no, email...">
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
            <button class="btn btn-outline-secondary btn-sm w-100" id="resetFiltersBtn" title="Reset all filters">
                <i class="bi bi-arrow-counterclockwise"></i>
            </button>
        </div>
    </div>
</div>

<!-- =======================================================
     DATA TABLE
======================================================= -->
<div class="nts-table-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="staffTable">
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
                <!-- Loading skeleton rows -->
                <tr class="skeleton-row">
                    <td colspan="9" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                        Loading non-teaching staff...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
        <small class="text-muted">
            Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span>
            of <span id="totalRecords">0</span> records
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0 nts-pagination" id="pagination"></ul>
        </nav>
    </div>
</div>

<!-- =======================================================
     ADD / EDIT STAFF MODAL
======================================================= -->
<div class="modal fade nts-modal" id="staffFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>
                    <span id="staffFormModalLabel">Add Non-Teaching Staff</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="staffForm" novalidate>
                    <input type="hidden" id="staffId">

                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" id="staffFormTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-personal" data-bs-toggle="tab"
                                    data-bs-target="#pane-personal" type="button" role="tab">
                                <i class="bi bi-person me-1"></i>Personal
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-employment" data-bs-toggle="tab"
                                    data-bs-target="#pane-employment" type="button" role="tab">
                                <i class="bi bi-briefcase me-1"></i>Employment
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-statutory" data-bs-toggle="tab"
                                    data-bs-target="#pane-statutory" type="button" role="tab">
                                <i class="bi bi-bank me-1"></i>Statutory
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="staffFormTabContent">

                        <!-- Tab 1: Personal -->
                        <div class="tab-pane fade show active" id="pane-personal" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="firstName" class="form-label">
                                        First Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="firstName" required
                                           placeholder="Enter first name">
                                </div>
                                <div class="col-md-6">
                                    <label for="lastName" class="form-label">
                                        Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="lastName" required
                                           placeholder="Enter last name">
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email"
                                           placeholder="email@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone"
                                           placeholder="e.g., 0712 345 678">
                                </div>
                                <div class="col-md-4">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender">
                                        <option value="">-- Select --</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="dateOfBirth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="dateOfBirth">
                                </div>
                                <div class="col-md-4">
                                    <label for="maritalStatus" class="form-label">Marital Status</label>
                                    <select class="form-select" id="maritalStatus">
                                        <option value="">-- Select --</option>
                                        <option value="single">Single</option>
                                        <option value="married">Married</option>
                                        <option value="divorced">Divorced</option>
                                        <option value="widowed">Widowed</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="address" class="form-label">Residential Address</label>
                                    <textarea class="form-control" id="address" rows="2"
                                              placeholder="Enter residential address"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Employment -->
                        <div class="tab-pane fade" id="pane-employment" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="staffNo" class="form-label">
                                        Staff No <span class="text-muted">(auto-generated)</span>
                                    </label>
                                    <input type="text" class="form-control" id="staffNo" readonly
                                           placeholder="Auto-generated on save">
                                </div>
                                <div class="col-md-6">
                                    <label for="department" class="form-label">
                                        Department <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="department" required>
                                        <option value="">-- Select Department --</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="staffCategory" class="form-label">
                                        Category <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="staffCategory" required>
                                        <option value="">-- Select Category --</option>
                                        <option value="Administrative">Administrative</option>
                                        <option value="Security">Security</option>
                                        <option value="Kitchen">Kitchen</option>
                                        <option value="Transport">Transport</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Accounts">Accounts</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="position" class="form-label">
                                        Position <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="position" required
                                           placeholder="e.g., Driver, Cook, Accountant">
                                </div>
                                <div class="col-md-6">
                                    <label for="employmentDate" class="form-label">
                                        Employment Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="employmentDate" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="contractType" class="form-label">
                                        Contract Type <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="contractType" required>
                                        <option value="">-- Select --</option>
                                        <option value="permanent">Permanent</option>
                                        <option value="contract">Contract</option>
                                        <option value="temporary">Temporary</option>
                                        <option value="intern">Intern</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 3: Statutory -->
                        <div class="tab-pane fade" id="pane-statutory" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="kraPin" class="form-label">KRA PIN</label>
                                    <input type="text" class="form-control" id="kraPin"
                                           placeholder="e.g., A012345678Z">
                                </div>
                                <div class="col-md-6">
                                    <label for="nhifNo" class="form-label">NHIF No</label>
                                    <input type="text" class="form-control" id="nhifNo"
                                           placeholder="NHIF membership number">
                                </div>
                                <div class="col-md-6">
                                    <label for="nssfNo" class="form-label">NSSF No</label>
                                    <input type="text" class="form-control" id="nssfNo"
                                           placeholder="NSSF membership number">
                                </div>
                                <div class="col-md-6">
                                    <label for="bankAccount" class="form-label">Bank Account</label>
                                    <input type="text" class="form-control" id="bankAccount"
                                           placeholder="Bank account number">
                                </div>
                                <div class="col-md-6">
                                    <label for="salary" class="form-label">Salary (KES)</label>
                                    <input type="number" class="form-control" id="salary"
                                           min="0" step="500" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                    </div><!-- /.tab-content -->
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="saveStaffBtn">
                    <i class="bi bi-check-circle me-1"></i>Save Staff
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
     VIEW STAFF MODAL
======================================================= -->
<div class="modal fade nts-modal" id="staffViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <!-- Profile Header -->
            <div class="profile-header" id="viewProfileHeader">
                <div class="profile-avatar-lg" id="viewAvatar">--</div>
                <h4 class="mb-1" id="viewFullName">Staff Name</h4>
                <p class="mb-0 opacity-75" id="viewStaffNo">KWPS---</p>
                <div class="mt-2">
                    <span class="badge-status badge-active" id="viewStatusBadge">Active</span>
                </div>
            </div>

            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Left Column: Personal Info -->
                    <div class="col-lg-6">
                        <h6 class="fw-bold text-uppercase text-muted mb-3">
                            <i class="bi bi-person me-1"></i>Personal Information
                        </h6>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="profile-detail-label">First Name</div>
                                <div class="profile-detail-value" id="viewFirstName">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">Last Name</div>
                                <div class="profile-detail-value" id="viewLastName">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">Email</div>
                                <div class="profile-detail-value" id="viewEmail">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">Phone</div>
                                <div class="profile-detail-value" id="viewPhone">--</div>
                            </div>
                            <div class="col-4">
                                <div class="profile-detail-label">Gender</div>
                                <div class="profile-detail-value" id="viewGender">--</div>
                            </div>
                            <div class="col-4">
                                <div class="profile-detail-label">Date of Birth</div>
                                <div class="profile-detail-value" id="viewDOB">--</div>
                            </div>
                            <div class="col-4">
                                <div class="profile-detail-label">Marital Status</div>
                                <div class="profile-detail-value" id="viewMarital">--</div>
                            </div>
                            <div class="col-12">
                                <div class="profile-detail-label">Address</div>
                                <div class="profile-detail-value" id="viewAddress">--</div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Employment & Statutory -->
                    <div class="col-lg-6">
                        <h6 class="fw-bold text-uppercase text-muted mb-3">
                            <i class="bi bi-briefcase me-1"></i>Employment Details
                        </h6>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="profile-detail-label">Department</div>
                                <div class="profile-detail-value" id="viewDepartment">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">Category</div>
                                <div class="profile-detail-value" id="viewCategory">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">Position</div>
                                <div class="profile-detail-value" id="viewPosition">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">Contract Type</div>
                                <div class="profile-detail-value" id="viewContract">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">Employment Date</div>
                                <div class="profile-detail-value" id="viewEmploymentDate">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">Status</div>
                                <div class="profile-detail-value" id="viewStatus">--</div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <h6 class="fw-bold text-uppercase text-muted mb-3">
                            <i class="bi bi-bank me-1"></i>Statutory &amp; Finance
                        </h6>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="profile-detail-label">KRA PIN</div>
                                <div class="profile-detail-value" id="viewKraPin">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">NHIF No</div>
                                <div class="profile-detail-value" id="viewNhif">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">NSSF No</div>
                                <div class="profile-detail-value" id="viewNssf">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">Bank Account</div>
                                <div class="profile-detail-value" id="viewBankAccount">--</div>
                            </div>
                            <div class="col-6">
                                <div class="profile-detail-label">Salary</div>
                                <div class="profile-detail-value" id="viewSalary">--</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" id="viewEditBtn">
                    <i class="bi bi-pencil-square me-1"></i>Edit
                </button>
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
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-1"></i>Confirm Delete
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-1">Are you sure you want to delete</p>
                <p class="fw-bold" id="deleteStaffName">this staff member</p>
                <small class="text-muted">This action cannot be undone.</small>
                <input type="hidden" id="deleteStaffId">
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">
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
    <div id="ntsToast" class="toast" role="alert">
        <div class="toast-header">
            <i class="bi bi-info-circle me-2" id="toastIcon"></i>
            <strong class="me-auto" id="toastTitle">Notice</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<!-- =======================================================
     SCRIPTS
======================================================= -->
<script src="/Kingsway/js/pages/manage_non_teaching_staff.js?v=<?php echo time(); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof manageNonTeachingStaffController !== 'undefined') {
            manageNonTeachingStaffController.init();
        } else {
            console.warn('[Non-Teaching Staff] Controller not found. Ensure manage_non_teaching_staff.js is loaded.');
        }
    });
</script>
