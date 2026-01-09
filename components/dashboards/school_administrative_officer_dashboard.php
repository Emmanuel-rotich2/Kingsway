<?php
/**
 * School Administrative Officer Dashboard
 * 
 * TIER 3: Operational School Management Dashboard
 * 
 * Purpose:
 * - Manage day-to-day operations
 * - Coordinate activities and staff
 * - Monitor student enrollment and attendance  
 * - Manage communications and admissions
 * 
 * Summary Cards (10): 2 rows × 5 columns
 * Row 1: Active Students, Teaching Staff, Staff Activities, Class Timetables, Daily Attendance
 * Row 2: Announcements, Student Admissions, Staff Leaves, Class Distribution, System Status
 * 
 * Charts (2): Weekly Attendance Trend (Line), Class Distribution (Bar)
 * Tables (3): Pending Items, Today's Schedule, Staff Directory
 * 
 * @package App\Components\Dashboards
 * @since 2025-01-06
 */

// Include required components
include_once __DIR__ . '/../charts/chart.php';
include_once __DIR__ . '/../tables/table.php';
include_once __DIR__ . '/../cards/card_component.php';
?>

<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="mb-1"><i class="fas fa-briefcase me-2"></i>School Administrator Dashboard</h2>
                <p class="text-muted mb-0">Operational overview and daily management</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <small class="text-muted me-3">
                    <i class="fas fa-clock me-1"></i>
                    Last refresh: <span id="lastRefreshTime">--:--:--</span>
                </small>
                <button id="refreshDashboard" class="btn btn-sm btn-primary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button id="exportDashboard" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- Row 1: Operational Status Cards (5 cards) -->
    <div class="row g-3 mb-3">
        <!-- 1. Active Students -->
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm" id="card-active-students">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1 text-truncate">Active Students</h6>
                            <h3 class="mb-0 fw-bold" id="active-students-value">--</h3>
                            <small class="text-muted" id="active-students-subtitle">Enrolled Students</small>
                        </div>
                        <div class="icon-circle bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-user-graduate fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-secondary" id="active-students-secondary">Classes: --</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Teaching Staff -->
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm" id="card-teaching-staff">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1 text-truncate">Teaching Staff</h6>
                            <h3 class="mb-0 fw-bold" id="teaching-staff-value">--</h3>
                            <small class="text-muted" id="teaching-staff-subtitle">Teaching Staff</small>
                        </div>
                        <div class="icon-circle bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-chalkboard-teacher fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-secondary" id="teaching-staff-secondary">Present today: --%</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Staff Activities -->
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm" id="card-staff-activities">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1 text-truncate">Staff Activities</h6>
                            <h3 class="mb-0 fw-bold" id="staff-activities-value">--</h3>
                            <small class="text-muted" id="staff-activities-subtitle">Staff Coordination</small>
                        </div>
                        <div class="icon-circle" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                            <i class="fas fa-tasks fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-secondary" id="staff-activities-secondary">On leave: -- | Assignments: --</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Class Timetables -->
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm" id="card-class-timetables">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1 text-truncate">Class Timetables</h6>
                            <h3 class="mb-0 fw-bold" id="class-timetables-value">--</h3>
                            <small class="text-muted" id="class-timetables-subtitle">Academic Schedules</small>
                        </div>
                        <div class="icon-circle bg-info bg-opacity-10 text-info">
                            <i class="fas fa-calendar-alt fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-secondary" id="class-timetables-secondary">Classes/week: --</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Daily Attendance -->
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm" id="card-daily-attendance">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1 text-truncate">Daily Attendance</h6>
                            <h3 class="mb-0 fw-bold" id="daily-attendance-value">--%</h3>
                            <small class="text-muted" id="daily-attendance-subtitle">Daily Attendance</small>
                        </div>
                        <div class="icon-circle" style="background-color: rgba(0, 128, 128, 0.1); color: teal;">
                            <i class="fas fa-clipboard-check fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-secondary" id="daily-attendance-secondary">Present: -- | Absent: --</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Communications & Operations Cards (5 cards) -->
    <div class="row g-3 mb-4">
        <!-- 6. Announcements -->
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm" id="card-announcements">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1 text-truncate">Announcements</h6>
                            <h3 class="mb-0 fw-bold" id="announcements-value">--</h3>
                            <small class="text-muted" id="announcements-subtitle">School Communications</small>
                        </div>
                        <div class="icon-circle" style="background-color: rgba(128, 0, 128, 0.1); color: purple;">
                            <i class="fas fa-bullhorn fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-secondary" id="announcements-secondary">To: -- recipients</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. Student Admissions -->
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm" id="card-student-admissions">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1 text-truncate">Student Admissions</h6>
                            <h3 class="mb-0 fw-bold" id="student-admissions-value">--</h3>
                            <small class="text-muted" id="student-admissions-subtitle">Admission Pipeline</small>
                        </div>
                        <div class="icon-circle bg-success bg-opacity-10 text-success">
                            <i class="fas fa-user-plus fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-secondary" id="student-admissions-secondary">Pending: -- | Approved: --</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 8. Staff Leaves -->
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm" id="card-staff-leaves">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1 text-truncate">Staff Leaves</h6>
                            <h3 class="mb-0 fw-bold" id="staff-leaves-value">--</h3>
                            <small class="text-muted" id="staff-leaves-subtitle">Staff Leave Status</small>
                        </div>
                        <div class="icon-circle bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-calendar-times fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-secondary" id="staff-leaves-secondary">On leave today: --</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 9. Class Distribution -->
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm" id="card-class-distribution">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1 text-truncate">Class Distribution</h6>
                            <h3 class="mb-0 fw-bold" id="class-distribution-value">--</h3>
                            <small class="text-muted" id="class-distribution-subtitle">Class Sizes</small>
                        </div>
                        <div class="icon-circle" style="background-color: rgba(199, 21, 133, 0.1); color: #c71585;">
                            <i class="fas fa-chart-pie fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-secondary" id="class-distribution-secondary">Avg: -- | Max: --</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 10. System Status -->
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm" id="card-system-status">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted fw-normal mb-1 text-truncate">System Status</h6>
                            <h3 class="mb-0 fw-bold text-success" id="system-status-value">--</h3>
                            <small class="text-muted" id="system-status-subtitle">System Performance</small>
                        </div>
                        <div class="icon-circle bg-success bg-opacity-10 text-success">
                            <i class="fas fa-check-circle fa-lg"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-secondary" id="system-status-secondary">Uptime: --%</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section (2 charts side by side) -->
    <div class="row g-4 mb-4">
        <!-- Weekly Attendance Trend (Line Chart) -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-teal"></i>Weekly Attendance Trend</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary active" data-range="4weeks">4 Weeks</button>
                            <button type="button" class="btn btn-outline-secondary" data-range="8weeks">8 Weeks</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="attendanceTrendChart" height="280"></canvas>
                </div>
            </div>
        </div>

        <!-- Class Distribution (Bar Chart) -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Class Distribution</h5>
                        <select class="form-select form-select-sm" style="width: auto;" id="classDistributionFilter">
                            <option value="all">All Classes</option>
                            <option value="form1">Form 1</option>
                            <option value="form2">Form 2</option>
                            <option value="form3">Form 3</option>
                            <option value="form4">Form 4</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="classDistributionChart" height="280"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabbed Data Tables Section -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="dashboardTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                                <i class="fas fa-clock me-2"></i>Pending Items
                                <span class="badge bg-warning text-dark ms-2" id="pending-count">--</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab" aria-controls="schedule" aria-selected="false">
                                <i class="fas fa-calendar-day me-2"></i>Today's Schedule
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab" aria-controls="staff" aria-selected="false">
                                <i class="fas fa-address-book me-2"></i>Staff Directory
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="dashboardTabsContent">
                        <!-- Pending Items Tab -->
                        <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Count</th>
                                            <th>Priority</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pending-items-table">
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Loading pending items...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Today's Schedule Tab -->
                        <div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Time</th>
                                            <th>Event</th>
                                            <th>Location</th>
                                            <th>Attendees</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="schedule-items-table">
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Loading schedule...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Staff Directory Tab -->
                        <div class="tab-pane fade" id="staff" role="tabpanel" aria-labelledby="staff-tab">
                            <div class="p-3">
                                <div class="input-group mb-3">
                                    <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="staffSearchInput" placeholder="Search staff by name, position, or department...">
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Position</th>
                                            <th>Department</th>
                                            <th>Contact</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="staff-directory-table">
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Loading staff directory...
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
    </div>

    <!-- Quick Actions Floating Button (Mobile) -->
    <div class="d-lg-none position-fixed bottom-0 end-0 p-3" style="z-index: 1030;">
        <div class="dropdown dropup">
            <button class="btn btn-primary btn-lg rounded-circle shadow" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-plus"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end mb-2">
                <li><a class="dropdown-item" href="home.php?route=manage_admissions"><i class="fas fa-user-plus me-2"></i>New Admission</a></li>
                <li><a class="dropdown-item" href="home.php?route=manage_announcements"><i class="fas fa-bullhorn me-2"></i>New Announcement</a></li>
                <li><a class="dropdown-item" href="home.php?route=mark_attendance"><i class="fas fa-clipboard-check me-2"></i>Mark Attendance</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="home.php?route=manage_staff"><i class="fas fa-users me-2"></i>View Staff</a></li>
            </ul>
        </div>
    </div>
</div>

<style>
/* Dashboard-specific styles */
.icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.nav-tabs .nav-link {
    color: #6c757d;
    border: none;
    border-bottom: 2px solid transparent;
}

.nav-tabs .nav-link.active {
    color: #0d6efd;
    background: transparent;
    border-bottom: 2px solid #0d6efd;
}

.nav-tabs .nav-link:hover:not(.active) {
    border-bottom: 2px solid #dee2e6;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .icon-circle {
        width: 40px;
        height: 40px;
    }
    
    .icon-circle i {
        font-size: 1rem !important;
    }
    
    .card-body h3 {
        font-size: 1.25rem;
    }
}

/* Text teal color helper */
.text-teal {
    color: teal !important;
}
</style>

<!-- Dashboard Controller Script -->
<script src="/Kingsway/js/dashboards/school_administrative_officer_dashboard.js?v=<?php echo time(); ?>"></script>
<script>
    // Initialize the dashboard controller when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        // Wait for API to be available (loaded in home.php)
        if (typeof schoolAdminDashboardController !== 'undefined') {
            schoolAdminDashboardController.init();
        } else {
            console.error('School Admin Dashboard Controller not loaded!');
        }
    });
</script>