<?php
/**
 * Class Teacher Dashboard — My Class Management
 * Role: Class Teacher (Role ID 7)
 */
?>

<div class="container-fluid py-4" id="class-teacher-dashboard">

    <!-- Greeting Bar -->
    <div class="dash-greeting-bar mb-4">
        <div>
            <h5 id="ctGreeting">Good morning!</h5>
            <p id="ctSubGreeting">Manage your class, attendance, and student progress.</p>
        </div>
        <div class="dash-meta">
            <span class="dash-badge"><i class="bi bi-mortarboard me-1"></i><span id="classNameBadge">My Class</span></span>
            <span class="dash-badge"><i class="bi bi-calendar3 me-1"></i><span id="ctTermBadge">—</span></span>
            <span class="text-white-50 small">Updated: <span id="lastRefreshTime">—</span></span>
            <button class="dash-refresh-btn" id="refreshDashboard">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Error State -->
    <div id="dashboardError" class="alert alert-danger d-none">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <span id="dashboardErrorMessage">Failed to load dashboard data</span>
        <button class="btn btn-sm btn-outline-danger ms-3"
                onclick="classTeacherDashboardController.loadDashboardData()">Retry</button>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4" id="summaryCardsContainer">
        <div class="col-6 col-lg-2">
            <div class="dash-stat dsc-blue">
                <div class="dash-stat-value" id="ctTotalStudents">—</div>
                <div class="dash-stat-label">My Students</div>
                <i class="bi bi-people-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value" id="ctAttendanceRate">—</div>
                <div class="dash-stat-label">Today's Attendance</div>
                <i class="bi bi-clipboard-check-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="dash-stat dsc-orange">
                <div class="dash-stat-value" id="ctPendingAssessments">—</div>
                <div class="dash-stat-label">Pending Assessments</div>
                <i class="bi bi-pencil-square dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="dash-stat dsc-teal">
                <div class="dash-stat-value" id="ctLessonPlans">—</div>
                <div class="dash-stat-label">Lesson Plans</div>
                <i class="bi bi-journal-text dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="dash-stat dsc-indigo">
                <div class="dash-stat-value" id="ctMessages">—</div>
                <div class="dash-stat-label">Messages</div>
                <i class="bi bi-chat-dots-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="dash-stat dsc-purple">
                <div class="dash-stat-value" id="ctAvgScore">—</div>
                <div class="dash-stat-label">Class Avg Score</div>
                <i class="bi bi-bar-chart-fill dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-success"></i>Weekly Attendance Trend</h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2 text-primary"></i>Assessment Performance</h6>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar4-week me-2 text-info"></i>Today's Schedule</h6>
                    <a href="<?= $appBase ?>home.php?route=manage_timetable" class="btn btn-sm btn-outline-primary">Full Timetable</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="scheduleTable">
                        <thead class="table-light">
                            <tr><th>Time</th><th>Subject</th><th>Topic</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="4" class="text-center text-muted py-3">Loading schedule...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-check2-all me-2 text-success"></i>Assessment Status</h6>
                    <a href="<?= $appBase ?>home.php?route=formative_assessments" class="btn btn-sm btn-outline-success">Enter Marks</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="assessmentTable">
                        <thead class="table-light">
                            <tr><th>Student</th><th>Subject</th><th>Assessment</th><th>Score</th><th>CBC</th></tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Class Roster -->
    <div class="card dash-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-people me-2 text-primary"></i>Class Roster</h6>
            <a href="<?= $appBase ?>home.php?route=all_students" class="btn btn-sm btn-outline-primary">View All Students</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="studentRosterTable">
                <thead class="table-light">
                    <tr><th>#</th><th>Student</th><th>Adm No</th><th>Gender</th><th>Attendance</th><th>Avg Score</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <tr><td colspan="7" class="text-center text-muted py-3">Loading roster...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="<?= $appBase ?>js/dashboards/class_teacher_dashboard.js?v=<?php echo time(); ?>"></script>
<script>
    (function () {
        const user = (typeof AuthContext !== 'undefined') ? AuthContext.getUser() : null;
        if (user) {
            const hr = new Date().getHours();
            const greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
            const name = user.first_name || user.name || 'Teacher';
            document.getElementById('ctGreeting').textContent = greet + ', ' + name + '!';
        }
        if (typeof classTeacherDashboardController !== 'undefined') {
            classTeacherDashboardController.init();
        }
    })();
</script>
