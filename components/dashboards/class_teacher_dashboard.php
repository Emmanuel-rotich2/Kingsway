<?php
/**
 * Class Teacher Dashboard Component
 * 
 * Purpose: MY CLASS MANAGEMENT
 * - Monitor assigned class only (data isolation)
 * - Track student attendance and performance
 * - Manage assessments and lesson plans
 * - Communicate with students and parents
 * 
 * Role: Class Teacher (Role ID: 7)
 * Update Frequency: 15-minute refresh
 * 
 * Data Isolation: ONLY MY ASSIGNED CLASS
 * 
 * Summary Cards (6):
 * 1. My Students - Total students in assigned class
 * 2. Today Attendance - Attendance rate for today
 * 3. Pending Assessments - Assessments to grade
 * 4. Lesson Plans - Weekly lesson plans status
 * 5. Communications - Messages sent this week
 * 6. Class Performance - Average grade for class
 * 
 * Charts (2):
 * 1. Weekly Attendance Trend
 * 2. Assessment Performance Distribution
 * 
 * Tables (2):
 * 1. Today's Schedule
 * 2. Student Assessment Status
 */
require_once __DIR__ . '/../../components/global/dashboard_base.php';
?>

<div class="container-fluid py-4" id="class-teacher-dashboard">
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-people-fill me-2"></i>My Class Dashboard</h4>
                    <p class="text-muted mb-0">Manage your assigned class, track attendance and student progress</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary" id="classNameBadge">Loading...</span>
                    <span class="text-muted small">Last updated: <span id="lastRefreshTime">--</span></span>
                    <button class="btn btn-outline-primary btn-sm" id="refreshDashboard">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Indicator -->
    <div id="dashboardLoading" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading your class data...</p>
    </div>
    
    <!-- Error State -->
    <div id="dashboardError" class="alert alert-danger" role="alert" style="display: none;">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <span id="dashboardErrorMessage">Failed to load dashboard data</span>
        <button class="btn btn-sm btn-outline-danger ms-3" onclick="classTeacherDashboardController.loadDashboardData()">Retry</button>
    </div>
    
    <!-- Summary Cards Container -->
    <div class="row g-3 mb-4" id="summaryCardsContainer">
        <!-- Cards will be dynamically rendered by JS -->
    </div>

    <!-- Charts Row -->
    <div class="row mt-4" id="chartsRow">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Weekly Attendance Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Assessment Performance</h5>
                </div>
                <div class="card-body">
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row mt-4" id="tablesRow">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Today's Schedule</h5>
                    <a href="/Kingsway/home.php?route=manage_timetable" class="btn btn-sm btn-outline-primary">View Full</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="scheduleTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Topic</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be dynamically rendered -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Student Assessment Status</h5>
                    <a href="/Kingsway/home.php?route=manage_assessments" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="assessmentTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Assessment</th>
                                    <th>Score</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be dynamically rendered -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Student Roster Quick View -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-people me-2"></i>Class Roster</h5>
                    <a href="/Kingsway/home.php?route=manage_students" class="btn btn-sm btn-outline-primary">View All Students</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="studentRosterTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Adm No</th>
                                    <th>Gender</th>
                                    <th>Attendance</th>
                                    <th>Avg Score</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be dynamically rendered -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard JS Controller -->
<script src="/Kingsway/js/dashboards/class_teacher_dashboard.js?v=<?php echo time(); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        console.log('👨‍🏫 Class Teacher Dashboard PHP loaded');
        if (typeof classTeacherDashboardController !== 'undefined') {
            classTeacherDashboardController.init();
        } else {
            console.error('❌ classTeacherDashboardController not found');
        }
    });
</script>