<?php
/**
 * Headteacher Dashboard Component
 * 
 * Purpose: Academic oversight and administration dashboard
 * Scope: Academic Schedules, Students, Staff, Communications, Discipline
 * 
 * Features:
 * - 8 Summary Cards (KPIs)
 * - 2 Charts (Attendance Trend, Academic Performance)
 * - 2 Data Tables (Pending Admissions, Open Discipline Cases)
 * 
 * Auto-Refresh: 30 minutes
 * Role ID: 5 (Headteacher)
 * 
 * This is an embedded dashboard component - loaded via dashboard.php
 */
?>

<style>
    .dashboard-card {
        transition: all 0.3s ease;
        border-radius: 12px;
        border: none;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .dashboard-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }

    .stat-card {
        padding: 1.25rem;
        border-radius: 12px;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: rgba(255, 255, 255, 0.1);
        transform: rotate(30deg);
    }

    .stat-card .icon {
        font-size: 2.5rem;
        opacity: 0.3;
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
    }

    .stat-card .value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
    }

    .stat-card .label {
        font-size: 0.875rem;
        opacity: 0.9;
        margin-top: 0.25rem;
    }

    .stat-card .secondary {
        font-size: 0.75rem;
        opacity: 0.7;
        margin-top: 0.5rem;
    }

    .bg-students {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .bg-attendance {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }

    .bg-schedules {
        background: linear-gradient(135deg, #00c6fb 0%, #005bea 100%);
    }

    .bg-admissions {
        background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
    }

    .bg-discipline {
        background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    }

    .bg-communications {
        background: linear-gradient(135deg, #834d9b 0%, #d04ed6 100%);
    }

    .bg-assessments {
        background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
    }

    .bg-performance {
        background: linear-gradient(135deg, #ff6a00 0%, #ee0979 100%);
    }

    .chart-container {
        position: relative;
        height: 300px;
    }

    .quick-link {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        background: #f8f9fa;
        margin-bottom: 0.5rem;
        text-decoration: none;
        color: #333;
        transition: all 0.2s;
    }

    .quick-link:hover {
        background: #e9ecef;
        transform: translateX(5px);
    }

    .quick-link i {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        margin-right: 0.75rem;
        font-size: 0.875rem;
    }

    .table-card .badge {
        font-weight: 500;
    }

    .refresh-indicator {
        font-size: 0.75rem;
        color: #6c757d;
    }
</style>

<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h3 class="mb-1"><i class="bi bi-mortarboard me-2"></i>Headteacher Dashboard</h3>
                    <p class="text-muted mb-0">Academic oversight and administration</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="refresh-indicator">
                        <i class="bi bi-clock me-1"></i>Last updated: <span id="lastUpdated">--</span>
                    </span>
                    <button class="btn btn-outline-primary btn-sm" id="refreshDashboard">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards Row 1 -->
    <div class="row g-3 mb-4">
        <!-- Total Students -->
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-students">
                <div class="value" id="totalStudents">--</div>
                <div class="label">Total Students</div>
                <div class="secondary" id="studentGrowth">Enrolled this term</div>
                <i class="bi bi-people icon"></i>
            </div>
        </div>

        <!-- Today's Attendance -->
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-attendance">
                <div class="value" id="attendanceToday">--%</div>
                <div class="label">Today's Attendance</div>
                <div class="secondary" id="attendanceDetails">Present: -- | Absent: --</div>
                <i class="bi bi-calendar-check icon"></i>
            </div>
        </div>

        <!-- Class Schedules -->
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-schedules">
                <div class="value" id="classSchedules">--</div>
                <div class="label">Class Schedules</div>
                <div class="secondary">Active classes this week</div>
                <i class="bi bi-calendar-week icon"></i>
            </div>
        </div>

        <!-- Pending Admissions -->
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-admissions">
                <div class="value" id="pendingAdmissions">--</div>
                <div class="label">Pending Admissions</div>
                <div class="secondary" id="admissionDetails">Applications awaiting review</div>
                <i class="bi bi-person-plus icon"></i>
            </div>
        </div>
    </div>

    <!-- Summary Cards Row 2 -->
    <div class="row g-3 mb-4">
        <!-- Discipline Cases -->
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-discipline">
                <div class="value" id="disciplineCases">--</div>
                <div class="label">Discipline Cases</div>
                <div class="secondary" id="disciplineDetails">Open cases requiring attention</div>
                <i class="bi bi-exclamation-triangle icon"></i>
            </div>
        </div>

        <!-- Parent Communications -->
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-communications">
                <div class="value" id="parentComms">--</div>
                <div class="label">Parent Communications</div>
                <div class="secondary">Messages sent this week</div>
                <i class="bi bi-chat-dots icon"></i>
            </div>
        </div>

        <!-- Student Assessments -->
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-assessments">
                <div class="value" id="assessments">--</div>
                <div class="label">Student Assessments</div>
                <div class="secondary" id="assessmentDetails">Recent tests & exams</div>
                <i class="bi bi-clipboard-data icon"></i>
            </div>
        </div>

        <!-- Class Performance -->
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="stat-card bg-performance">
                <div class="value" id="classPerformance">--%</div>
                <div class="label">Class Performance</div>
                <div class="secondary">Average academic score</div>
                <i class="bi bi-graph-up icon"></i>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <!-- Weekly Attendance Trend -->
        <div class="col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Weekly Attendance Trend</h5>
                    <small class="text-muted">Last 7 days attendance percentage</small>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Academic Performance by Class -->
        <div class="col-lg-6">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2 text-success"></i>Academic Performance by Class</h5>
                    <small class="text-muted">Average scores per class</small>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Tables Row -->
    <div class="row g-4 mb-4">
        <!-- Pending Admissions Table -->
        <div class="col-lg-6">
            <div class="card dashboard-card table-card h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-person-plus me-2 text-success"></i>Pending Admissions</h5>
                        <small class="text-muted">Applications awaiting review</small>
                    </div>
                    <a href="home.php?route=new_applications" class="btn btn-sm btn-outline-primary">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="admissionsTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="admissionsTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm" role="status"></div>
                                        Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Discipline Cases Table -->
        <div class="col-lg-6">
            <div class="card dashboard-card table-card h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Open Discipline Cases</h5>
                        <small class="text-muted">Active cases requiring attention</small>
                    </div>
                    <a href="home.php?route=discipline_cases" class="btn btn-sm btn-outline-danger">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="disciplineTable">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Issue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="disciplineTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm" role="status"></div>
                                        Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card dashboard-card">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <a href="home.php?route=all_students" class="quick-link">
                                <i class="bi bi-people bg-primary text-white"></i>
                                <span>View All Students</span>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="home.php?route=all_teachers" class="quick-link">
                                <i class="bi bi-person-badge bg-success text-white"></i>
                                <span>View All Teachers</span>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="home.php?route=timetable" class="quick-link">
                                <i class="bi bi-calendar-week bg-info text-white"></i>
                                <span>Manage Timetable</span>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="home.php?route=assessments_exams" class="quick-link">
                                <i class="bi bi-clipboard-check bg-warning text-white"></i>
                                <span>Assessments & Exams</span>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="home.php?route=academic_reports" class="quick-link">
                                <i class="bi bi-file-earmark-bar-graph bg-danger text-white"></i>
                                <span>Academic Reports</span>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="home.php?route=manage_communications" class="quick-link">
                                <i class="bi bi-chat-dots bg-secondary text-white"></i>
                                <span>Communications</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card dashboard-card">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-0"><i class="bi bi-calendar-event me-2 text-info"></i>Upcoming Events</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush" id="upcomingEvents">
                        <li class="list-group-item text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                            Loading events...
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/dashboards/headteacher_dashboard.js"></script>
