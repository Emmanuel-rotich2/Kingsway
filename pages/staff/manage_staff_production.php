<?php
/**
 * Staff Management Page - Production Level UI
 * Advanced Bootstrap 5 + Material Design + DataTables + Chart.js
 * 
 * Features:
 * - DataTables with server-side processing
 * - Chart.js dashboard visualizations  
 * - Material Design components
 * - Advanced modals with validation
 * - Profile avatars & image uploads
 * - Action dropdown menus
 * - Loading skeletons & empty states
 * - Toast notifications
 * - Export functionality (Excel, PDF, CSV)
 * - Print views
 * - Responsive mobile-first design
 */
?>

<!-- Include Required Libraries -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

<style>
    /* Production-Level Custom Styles */
    :root {
        --primary-color: #4CAF50;
        --secondary-color: #2196F3;
        --success-color: #8BC34A;
        --warning-color: #FF9800;
        --danger-color: #F44336;
        --info-color: #00BCD4;
        --dark-color: #37474F;
        --light-color: #ECEFF1;
    }

    /* Glass morphism effect */
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        border-radius: 15px;
    }

    /* Gradient headers */
    .gradient-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 15px 15px 0 0;
    }

    /* Modern statistics cards */
    .stat-card {
        position: relative;
        padding: 1.5rem;
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
        border: none;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin-bottom: 1rem;
    }

    /* Profile avatar */
    .profile-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .avatar-placeholder {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 16px;
    }

    /* Status badges */
    .status-badge {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-active {
        background: #D4EDDA;
        color: #155724;
    }

    .status-inactive {
        background: #F8D7DA;
        color: #721C24;
    }

    .status-on-leave {
        background: #FFF3CD;
        color: #856404;
    }

    /* Action buttons */
    .action-btn {
        width: 35px;
        height: 35px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        margin: 0 2px;
    }

    .action-btn:hover {
        transform: scale(1.1);
    }

    .action-btn-view {
        background: #E3F2FD;
        color: #1976D2;
    }

    .action-btn-edit {
        background: #FFF9C4;
        color: #F57F17;
    }

    .action-btn-delete {
        background: #FFEBEE;
        color: #C62828;
    }

    /* Loading skeleton */
    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s ease-in-out infinite;
        border-radius: 4px;
    }

    @keyframes loading {
        0% {
            background-position: 200% 0;
        }
        100% {
            background-position: -200% 0;
        }
    }

    .skeleton-text {
        height: 12px;
        margin-bottom: 8px;
    }

    .skeleton-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }

    .empty-state-icon {
        font-size: 80px;
        color: #BDBDBD;
        margin-bottom: 1rem;
    }

    /* DataTables enhancements */
    .dataTables_wrapper .dataTables_filter input {
        border-radius: 20px;
        padding: 0.5rem 1rem;
        border: 2px solid #E0E0E0;
        transition: all 0.3s ease;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
    }

    /* Chart container */
    .chart-container {
        position: relative;
        height: 300px;
        margin-bottom: 1rem;
    }

    /* Floating action button */
    .fab {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        border: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        transition: all 0.3s ease;
        z-index: 1000;
    }

    .fab:hover {
        transform: scale(1.1) rotate(90deg);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
    }

    /* Filter chips */
    .filter-chip {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        margin: 0.25rem;
        background: #E3F2FD;
        border-radius: 20px;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .filter-chip:hover {
        background: #BBDEFB;
    }

    .filter-chip.active {
        background: var(--primary-color);
        color: white;
    }

    .filter-chip .material-icons {
        font-size: 18px;
        margin-left: 0.5rem;
    }

    /* Modal enhancements */
    .modal-header {
        border-bottom: none;
        padding-bottom: 0;
    }

    .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    }

    /* Form enhancements */
    .form-label {
        font-weight: 600;
        color: #37474F;
        margin-bottom: 0.5rem;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
    }

    /* Progress bars */
    .progress-thin {
        height: 6px;
        border-radius: 3px;
    }

    /* Tooltips */
    .tooltip-inner {
        background: #37474F;
        border-radius: 8px;
        padding: 0.5rem 0.75rem;
    }

    /* Tab enhancements */
    .nav-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #757575;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .nav-tabs .nav-link:hover {
        border-bottom-color: #E0E0E0;
    }

    .nav-tabs .nav-link.active {
        border-bottom-color: var(--primary-color);
        color: var(--primary-color);
        background: transparent;
    }

    /* Page header */
    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .page-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
    }

    /* Breadcrumb */
    .breadcrumb {
        background: transparent;
        padding: 0;
        margin-bottom: 1rem;
    }

    .breadcrumb-item a {
        color: white;
        text-decoration: none;
    }

    .breadcrumb-item.active {
        color: rgba(255, 255, 255, 0.8);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .stat-card {
            margin-bottom: 1rem;
        }

        .fab {
            bottom: 1rem;
            right: 1rem;
        }

        .filter-chip {
            width: 100%;
            justify-content: space-between;
        }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= $appBase ?>pages/home.php"><i class="material-icons" style="font-size: 18px; vertical-align: middle;">home</i> Home</a></li>
            <li class="breadcrumb-item"><a href="#">HR Management</a></li>
            <li class="breadcrumb-item active" aria-current="page">Staff Management</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">
                <i class="material-icons" style="font-size: 40px; vertical-align: middle;">groups</i>
                Staff Management
            </h1>
            <p class="page-subtitle mb-0">Manage your school staff, payroll, contracts, and assignments</p>
        </div>
        <div class="d-none d-md-block">
            <button class="btn btn-light btn-lg" onclick="staffManagementController.showStaffModal()">
                <i class="material-icons" style="vertical-align: middle;">person_add</i>
                Add Staff
            </button>
        </div>
    </div>
</div>

<!-- Dashboard Statistics Cards with Charts -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4CAF50, #8BC34A);">
                <i class="material-icons" style="color: white;">groups</i>
            </div>
            <h6 class="text-muted mb-2">Total Staff</h6>
            <div class="d-flex align-items-baseline">
                <h2 class="mb-0 me-2" id="totalStaffCount" style="color: #4CAF50;">0</h2>
                <span class="badge bg-success-subtle text-success">
                    <i class="material-icons" style="font-size: 14px;">trending_up</i> 5%
                </span>
            </div>
            <canvas id="totalStaffChart" height="60"></canvas>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #2196F3, #64B5F6);">
                <i class="material-icons" style="color: white;">school</i>
            </div>
            <h6 class="text-muted mb-2">Teaching Staff</h6>
            <div class="d-flex align-items-baseline">
                <h2 class="mb-0 me-2" id="teachingStaffCount" style="color: #2196F3;">0</h2>
                <span class="badge bg-primary-subtle text-primary">
                    <i class="material-icons" style="font-size: 14px;">trending_flat</i> 0%
                </span>
            </div>
            <canvas id="teachingStaffChart" height="60"></canvas>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #FF9800, #FFB74D);">
                <i class="material-icons" style="color: white;">event_busy</i>
            </div>
            <h6 class="text-muted mb-2">On Leave</h6>
            <div class="d-flex align-items-baseline">
                <h2 class="mb-0 me-2" id="onLeaveCount" style="color: #FF9800;">0</h2>
                <span class="badge bg-warning-subtle text-warning">
                    <i class="material-icons" style="font-size: 14px;">priority_high</i>
                </span>
            </div>
            <div class="progress progress-thin mt-2">
                <div class="progress-bar bg-warning" id="leaveProgressBar" role="progressbar" style="width: 0%"></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #9C27B0, #BA68C8);">
                <i class="material-icons" style="color: white;">check_circle</i>
            </div>
            <h6 class="text-muted mb-2">Present Today</h6>
            <div class="d-flex align-items-baseline">
                <h2 class="mb-0 me-2" id="presentTodayCount" style="color: #9C27B0;">0</h2>
                <small class="text-muted">/ <span id="totalActiveStaff">0</span></small>
            </div>
            <div class="progress progress-thin mt-2">
                <div class="progress-bar bg-purple" id="attendanceProgressBar" role="progressbar" style="width: 0%; background: #9C27B0;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Analytics Dashboard (HR & Finance) -->
<div class="row g-4 mb-4" data-role="hr_manager,accountant,bursar,director,admin">
    <div class="col-lg-6">
        <div class="card glass-card">
            <div class="card-header bg-transparent border-0">
                <h5 class="mb-0"><i class="material-icons" style="vertical-align: middle;">trending_up</i> Staff Distribution</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="staffDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card glass-card">
            <div class="card-header bg-transparent border-0">
                <h5 class="mb-0"><i class="material-icons" style="vertical-align: middle;">attach_money</i> Monthly Payroll Trend</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="payrollTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="card glass-card mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-sm" onclick="staffManagementController.showBulkImportModal()">
                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">upload_file</i>
                Bulk Import
            </button>
            <button class="btn btn-success btn-sm" onclick="staffManagementController.exportStaff()">
                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">download</i>
                Export
            </button>
            <button class="btn btn-info btn-sm" onclick="staffManagementController.showLeaveRequests()">
                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">event_note</i>
                Leave Requests <span class="badge bg-danger ms-1" id="pendingLeavesBadge">0</span>
            </button>
            <button class="btn btn-warning btn-sm" onclick="staffManagementController.showContractRenewals()" data-role="hr_manager,director,admin">
                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">schedule</i>
                Contract Renewals <span class="badge bg-danger ms-1" id="expiringContractsBadge">0</span>
            </button>
            <button class="btn btn-secondary btn-sm" onclick="staffManagementController.printReport()">
                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">print</i>
                Print
            </button>
        </div>
    </div>
</div>

<!-- Staff Management Tabs -->
<div class="card glass-card">
    <div class="card-header bg-transparent border-0">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#allStaffTab">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">groups</i>
                    All Staff
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#teachingTab">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">school</i>
                    Teaching
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#nonTeachingTab">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">business_center</i>
                    Non-Teaching
                </a>
            </li>
            <li class="nav-item" data-role="hr_manager,accountant,bursar,director,admin">
                <a class="nav-link" data-bs-toggle="tab" href="#payrollTab">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">payments</i>
                    Payroll
                </a>
            </li>
            <li class="nav-item" data-role="hr_manager,headteacher,deputy_head_academic,admin">
                <a class="nav-link" data-bs-toggle="tab" href="#attendanceTab">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">how_to_reg</i>
                    Attendance
                </a>
            </li>
            <li class="nav-item" data-role="hr_manager,director,admin">
                <a class="nav-link" data-bs-toggle="tab" href="#contractsTab">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">description</i>
                    Contracts
                </a>
            </li>
        </ul>
    </div>
    
    <div class="card-body">
        <div class="tab-content">
            <!-- All Staff Tab -->
            <div class="tab-pane fade show active" id="allStaffTab">
                <!-- Advanced Filters -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="material-icons" style="font-size: 20px;">search</i>
                            </span>
                            <input type="text" id="staffSearchInput" class="form-control border-start-0 ps-0" 
                                   placeholder="Search by name, staff number, email...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select id="departmentFilterSelect" class="form-select">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="staffTypeFilterSelect" class="form-select">
                            <option value="">All Types</option>
                            <option value="teaching">Teaching</option>
                            <option value="non-teaching">Non-Teaching</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="statusFilterSelect" class="form-select">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="on_leave">On Leave</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" onclick="staffManagementController.resetFilters()">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle;">refresh</i>
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Active Filters (Chips) -->
                <div id="activeFiltersContainer" class="mb-3"></div>

                <!-- Staff DataTable -->
                <div class="table-responsive">
                    <table id="staffDataTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Staff</th>
                                <th>Staff No.</th>
                                <th>Type</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTable will populate this -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Teaching Staff Tab -->
            <div class="tab-pane fade" id="teachingTab">
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="material-icons" style="font-size: 20px;">search</i>
                            </span>
                            <input type="text" id="teachingSearchInput" class="form-control border-start-0 ps-0" 
                                   placeholder="Search teaching staff...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select id="teachingDeptFilter" class="form-select">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="teachingStaffTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Teacher</th>
                                <th>Staff No.</th>
                                <th>Department/Subject</th>
                                <th>Qualifications</th>
                                <th>Workload (hrs/week)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Non-Teaching Staff Tab -->
            <div class="tab-pane fade" id="nonTeachingTab">
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="material-icons" style="font-size: 20px;">search</i>
                            </span>
                            <input type="text" id="nonTeachingSearchInput" class="form-control border-start-0 ps-0" 
                                   placeholder="Search non-teaching staff...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select id="nonTeachingDeptFilter" class="form-select">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="nonTeachingStaffTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Staff Member</th>
                                <th>Staff No.</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Employment Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Payroll Tab -->
            <div class="tab-pane fade" id="payrollTab" data-role="hr_manager,accountant,bursar,director,admin">
                <!-- Payroll Summary Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="card border-0" style="background: linear-gradient(135deg, #4CAF50, #8BC34A); color: white;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-white-50 mb-2">Gross Payroll</h6>
                                        <h3 class="mb-0" id="grossPayrollAmount">KES 0</h3>
                                    </div>
                                    <i class="material-icons" style="font-size: 40px; opacity: 0.3;">account_balance_wallet</i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card border-0" style="background: linear-gradient(135deg, #FF9800, #FFB74D); color: white;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-white-50 mb-2">Deductions</h6>
                                        <h3 class="mb-0" id="totalDeductionsAmount">KES 0</h3>
                                    </div>
                                    <i class="material-icons" style="font-size: 40px; opacity: 0.3;">money_off</i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card border-0" style="background: linear-gradient(135deg, #2196F3, #64B5F6); color: white;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-white-50 mb-2">Net Payroll</h6>
                                        <h3 class="mb-0" id="netPayrollAmount">KES 0</h3>
                                    </div>
                                    <i class="material-icons" style="font-size: 40px; opacity: 0.3;">payments</i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card border-0" style="background: linear-gradient(135deg, #F44336, #E57373); color: white;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-white-50 mb-2">Pending Approval</h6>
                                        <h3 class="mb-0" id="pendingPayrollCount">0</h3>
                                    </div>
                                    <i class="material-icons" style="font-size: 40px; opacity: 0.3;">pending_actions</i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="staffManagementController.runPayroll()" data-role="hr_manager,bursar,admin">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle;">calculate</i>
                            Run Payroll
                        </button>
                        <button class="btn btn-success" onclick="staffManagementController.exportPayroll()">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle;">download</i>
                            Export Excel
                        </button>
                    </div>
                    <button class="btn btn-outline-success" onclick="staffManagementController.approvePayroll()" data-role="bursar,director,admin">
                        <i class="material-icons" style="font-size: 18px; vertical-align: middle;">check_circle</i>
                        Approve Payroll
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="payrollDataTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Staff No.</th>
                                <th>Name</th>
                                <th>Basic Salary</th>
                                <th>Allowances</th>
                                <th>Gross Pay</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Attendance Tab -->
            <div class="tab-pane fade" id="attendanceTab" data-role="hr_manager,headteacher,deputy_head_academic,admin">
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <div class="btn-group">
                            <button class="btn btn-primary" onclick="staffManagementController.markAttendance()" data-role="hr_manager,admin">
                                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">check_box</i>
                                Mark Attendance
                            </button>
                            <button class="btn btn-outline-info" onclick="staffManagementController.showAttendanceReport()">
                                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">assessment</i>
                                View Report
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <input type="date" class="form-control" id="attendanceDateFilter" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="attendanceDataTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Staff No.</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Hours Worked</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Contracts Tab -->
            <div class="tab-pane fade" id="contractsTab" data-role="hr_manager,director,admin">
                <div class="d-flex justify-content-between mb-3">
                    <button class="btn btn-primary" onclick="staffManagementController.showContractModal()">
                        <i class="material-icons" style="font-size: 18px; vertical-align: middle;">add_circle</i>
                        New Contract
                    </button>
                    <div class="btn-group">
                        <button class="btn btn-outline-warning" onclick="staffManagementController.showRenewalQueue()">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle;">schedule</i>
                            Renewal Queue
                        </button>
                        <button class="btn btn-outline-secondary" onclick="staffManagementController.exportContracts()">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle;">download</i>
                            Export
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="contractsDataTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Staff No.</th>
                                <th>Name</th>
                                <th>Contract Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Button -->
<button class="fab d-md-none" onclick="staffManagementController.showStaffModal()">
    <i class="material-icons">add</i>
</button>

<!-- Advanced Staff Modal -->
<div class="modal fade" id="staffModalAdvanced" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header gradient-header">
                <h4 class="modal-title">
                    <i class="material-icons" style="vertical-align: middle;">person_add</i>
                    <span id="staffModalTitle">Add New Staff Member</span>
                </h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="staffFormAdvanced">
                    <input type="hidden" id="staffIdHidden">
                    
                    <ul class="nav nav-pills mb-4" id="staffFormTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="pill" href="#personalInfoTab">
                                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">person</i>
                                Personal Info
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="pill" href="#employmentTab">
                                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">work</i>
                                Employment
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="pill" href="#bankingTab">
                                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">account_balance</i>
                                Banking & Tax
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="pill" href="#documentsTab">
                                <i class="material-icons" style="font-size: 18px; vertical-align: middle;">folder</i>
                                Documents
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Personal Info Tab -->
                        <div class="tab-pane fade show active" id="personalInfoTab">
                            <div class="row g-3">
                                <div class="col-12 text-center mb-3">
                                    <div class="position-relative d-inline-block">
                                        <img src="<?= $appBase ?>images/avatar-placeholder.png" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #E0E0E0;" id="staffAvatarPreview">
                                        <label for="staffAvatar" class="btn btn-primary btn-sm position-absolute" style="bottom: 0; right: 0; border-radius: 50%; width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                            <i class="material-icons" style="font-size: 20px;">camera_alt</i>
                                        </label>
                                        <input type="file" id="staffAvatar" accept="image/*" class="d-none">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">badge</i>
                                        First Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="staffFirstName" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">badge</i>
                                        Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="staffLastName" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">phone</i>
                                        Phone Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="tel" id="staffPhone" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">email</i>
                                        Email Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" id="staffEmail" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">cake</i>
                                        Date of Birth
                                    </label>
                                    <input type="date" id="staffDOB" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">wc</i>
                                        Gender
                                    </label>
                                    <select id="staffGender" class="form-select">
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">fingerprint</i>
                                        ID/Passport Number
                                    </label>
                                    <input type="text" id="staffIdNumber" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">group</i>
                                        Marital Status
                                    </label>
                                    <select id="staffMaritalStatus" class="form-select">
                                        <option value="">Select Status</option>
                                        <option value="single">Single</option>
                                        <option value="married">Married</option>
                                        <option value="divorced">Divorced</option>
                                        <option value="widowed">Widowed</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">home</i>
                                        Residential Address
                                    </label>
                                    <textarea id="staffAddress" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Tab -->
                        <div class="tab-pane fade" id="employmentTab">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">confirmation_number</i>
                                        Staff Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="staffNumber" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">category</i>
                                        Staff Type <span class="text-danger">*</span>
                                    </label>
                                    <select id="staffType" class="form-select" required>
                                        <option value="">Select Type</option>
                                        <option value="teaching">Teaching</option>
                                        <option value="non-teaching">Non-Teaching</option>
                                        <option value="admin">Administrative</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">apartment</i>
                                        Department <span class="text-danger">*</span>
                                    </label>
                                    <select id="staffDepartment" class="form-select select2" required>
                                        <option value="">Select Department</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">work</i>
                                        Position/Designation <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="staffPosition" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">admin_panel_settings</i>
                                        Role
                                    </label>
                                    <select id="staffRole" class="form-select select2">
                                        <option value="">Select Role</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">event</i>
                                        Employment Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" id="staffEmploymentDate" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">description</i>
                                        Contract Type
                                    </label>
                                    <select id="staffContractType" class="form-select">
                                        <option value="">Select Type</option>
                                        <option value="permanent">Permanent</option>
                                        <option value="contract">Contract</option>
                                        <option value="temporary">Temporary</option>
                                        <option value="intern">Intern</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">toggle_on</i>
                                        Status
                                    </label>
                                    <select id="staffStatus" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="on_leave">On Leave</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">school</i>
                                        Qualifications/Education
                                    </label>
                                    <textarea id="staffQualifications" class="form-control" rows="3" placeholder="e.g., B.Ed Mathematics, PGDE"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">verified</i>
                                        Certifications/Licenses
                                    </label>
                                    <textarea id="staffCertifications" class="form-control" rows="3" placeholder="e.g., TSC Number, Teaching License"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Banking & Tax Tab -->
                        <div class="tab-pane fade" id="bankingTab">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">account_balance</i>
                                        Bank Name
                                    </label>
                                    <select id="staffBankName" class="form-select">
                                        <option value="">Select Bank</option>
                                        <option value="KCB">Kenya Commercial Bank</option>
                                        <option value="Equity">Equity Bank</option>
                                        <option value="Cooperative">Co-operative Bank</option>
                                        <option value="NCBA">NCBA Bank</option>
                                        <option value="Absa">Absa Bank</option>
                                        <option value="Standard">Standard Chartered</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">numbers</i>
                                        Account Number
                                    </label>
                                    <input type="text" id="staffAccountNumber" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">money</i>
                                        Basic Salary (KES)
                                    </label>
                                    <input type="number" id="staffBasicSalary" class="form-control" step="500" min="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">attach_money</i>
                                        Allowances (KES)
                                    </label>
                                    <input type="number" id="staffAllowances" class="form-control" step="100" min="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">receipt_long</i>
                                        KRA PIN Number
                                    </label>
                                    <input type="text" id="staffKraPin" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">health_and_safety</i>
                                        NHIF Number
                                    </label>
                                    <input type="text" id="staffNhifNumber" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">savings</i>
                                        NSSF Number
                                    </label>
                                    <input type="text" id="staffNssfNumber" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">payments</i>
                                        Payment Method
                                    </label>
                                    <select id="staffPaymentMethod" class="form-select">
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="mpesa">M-Pesa</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="cash">Cash</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="documentsTab">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="material-icons" style="vertical-align: middle;">info</i>
                                        Upload scanned copies of important documents (ID, Certificates, Contracts, etc.)
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">badge</i>
                                        ID Card/Passport Copy
                                    </label>
                                    <input type="file" class="form-control" id="staffIdDocument" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">school</i>
                                        Academic Certificates
                                    </label>
                                    <input type="file" class="form-control" id="staffCertificates" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">work</i>
                                        Employment Contract
                                    </label>
                                    <input type="file" class="form-control" id="staffContract" accept=".pdf,.doc,.docx">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">receipt</i>
                                        Tax Documents
                                    </label>
                                    <input type="file" class="form-control" id="staffTaxDocs" accept=".pdf" multiple>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">
                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">folder</i>
                                        Other Documents
                                    </label>
                                    <input type="file" class="form-control" id="staffOtherDocs" multiple>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">close</i>
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="staffManagementController.saveStaff()">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">save</i>
                    Save Staff Member
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Required JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Load Core Staff Controller -->
<script src="<?= $appBase ?>js/pages/staff.js"></script>

<!-- Production-Level UI Enhancements Script -->
<script src="<?= $appBase ?>js/pages/staff_production_ui.js"></script>

<script>
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[Staff Management Production] Initializing advanced UI...');
        
        // Initialize staff management controller
        if (typeof staffManagementController !== 'undefined') {
            staffManagementController.init();
        }
        
        // Initialize production UI enhancements
        if (typeof StaffProductionUI !== 'undefined') {
            StaffProductionUI.init();
        }
    });
</script>
