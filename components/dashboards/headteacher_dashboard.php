<?php
/**
 * Headteacher Dashboard — Academic oversight and administration
 * Role ID: 5
 */
?>

<div class="container-fluid py-4">

    <!-- Greeting Bar -->
    <div class="dash-greeting-bar mb-4">
        <div>
            <h5 id="htGreeting">Good morning!</h5>
            <p>Academic oversight &amp; administration</p>
        </div>
        <div class="dash-meta">
            <span class="dash-badge"><i class="bi bi-calendar3 me-1"></i><span id="htTermBadge">—</span></span>
            <span class="text-white-50 small">Updated: <span id="lastUpdated">—</span></span>
            <button class="dash-refresh-btn" id="refreshDashboard">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- KPI Cards Row 1 -->
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-indigo">
                <div class="dash-stat-value" id="totalStudents">—</div>
                <div class="dash-stat-label">Total Students</div>
                <div class="dash-stat-sub" id="studentGrowth">Enrolled this term</div>
                <i class="bi bi-people dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value" id="attendanceToday">—</div>
                <div class="dash-stat-label">Today's Attendance</div>
                <div class="dash-stat-sub" id="attendanceDetails">Present / Absent</div>
                <i class="bi bi-calendar-check dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-cyan">
                <div class="dash-stat-value" id="classSchedules">—</div>
                <div class="dash-stat-label">Active Schedules</div>
                <div class="dash-stat-sub">Classes this week</div>
                <i class="bi bi-calendar-week dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-teal">
                <div class="dash-stat-value" id="pendingAdmissions">—</div>
                <div class="dash-stat-label">Pending Admissions</div>
                <div class="dash-stat-sub" id="admissionDetails">Applications awaiting</div>
                <i class="bi bi-person-plus dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- KPI Cards Row 2 -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-red">
                <div class="dash-stat-value" id="disciplineCases">—</div>
                <div class="dash-stat-label">Discipline Cases</div>
                <div class="dash-stat-sub" id="disciplineDetails">Open, needs attention</div>
                <i class="bi bi-exclamation-triangle dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-purple">
                <div class="dash-stat-value" id="parentComms">—</div>
                <div class="dash-stat-label">Parent Messages</div>
                <div class="dash-stat-sub">Sent this week</div>
                <i class="bi bi-chat-dots dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-amber">
                <div class="dash-stat-value" id="assessments">—</div>
                <div class="dash-stat-label">Assessments</div>
                <div class="dash-stat-sub" id="assessmentDetails">Recent tests &amp; exams</div>
                <i class="bi bi-clipboard-data dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="dash-stat dsc-orange">
                <div class="dash-stat-value" id="classPerformance">—</div>
                <div class="dash-stat-label">Class Performance</div>
                <div class="dash-stat-sub">Average academic score</div>
                <i class="bi bi-graph-up dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Weekly Attendance Trend</h6>
                    <small class="text-muted">Last 7 school days</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="attendanceChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-success"></i>Performance by Class</h6>
                    <small class="text-muted">Average scores per class</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap-lg"><canvas id="performanceChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-person-plus me-2 text-success"></i>Pending Admissions</h6>
                        <small class="text-muted">Applications awaiting review</small>
                    </div>
                    <a href="home.php?route=manage_students_admissions" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Name</th><th>Class</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody id="admissionsTableBody">
                            <tr><td colspan="4" class="text-center text-muted py-4">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Open Discipline Cases</h6>
                        <small class="text-muted">Requiring attention</small>
                    </div>
                    <a href="home.php?route=discipline_cases" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Student</th><th>Class</th><th>Issue</th><th>Status</th></tr></thead>
                        <tbody id="disciplineTableBody">
                            <tr><td colspan="4" class="text-center text-muted py-4">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions + Events -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <a href="home.php?route=all_students" class="dash-quick-link">
                                <i class="bi bi-people ql-icon bg-primary text-white"></i>
                                <span>All Students</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="home.php?route=manage_staff" class="dash-quick-link">
                                <i class="bi bi-person-badge ql-icon bg-success text-white"></i>
                                <span>All Staff</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="home.php?route=manage_timetable" class="dash-quick-link">
                                <i class="bi bi-calendar-week ql-icon bg-info text-white"></i>
                                <span>Timetable</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="home.php?route=assessments_exams" class="dash-quick-link">
                                <i class="bi bi-clipboard-check ql-icon bg-warning text-white"></i>
                                <span>Assessments</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="home.php?route=academic_reports" class="dash-quick-link">
                                <i class="bi bi-file-earmark-bar-graph ql-icon bg-danger text-white"></i>
                                <span>Academic Reports</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="home.php?route=manage_communications" class="dash-quick-link">
                                <i class="bi bi-chat-dots ql-icon bg-secondary text-white"></i>
                                <span>Communications</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar-event me-2 text-info"></i>Upcoming Events</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush" id="upcomingEvents">
                        <li class="list-group-item text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading events...
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="<?= $appBase ?>/js/dashboards/headteacher_dashboard.js"></script>
<script>
    (function () {
        const user = (typeof AuthContext !== 'undefined') ? AuthContext.getUser() : null;
        if (user) {
            const hr = new Date().getHours();
            const greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
            const name = user.first_name || user.name || 'Headteacher';
            document.getElementById('htGreeting').textContent = greet + ', ' + name + '!';
        }
    })();
</script>
