<?php
/**
 * Deputy Headteacher (Discipline) Dashboard — dual role: teacher + discipline administrator.
 * Role ID: 63
 *
 * Layout:
 *   A. Greeting bar
 *   B. MY TEACHING TODAY — own class, attendance, today's lessons, pending plans
 *   C. DISCIPLINE ADMIN KPIs — open cases, truancy, suspensions, parent meetings pending
 *   D. Charts: discipline trend + attendance (truancy)
 *   E. Tables: open discipline cases + attendance anomalies
 *   F. Quick Actions
 */
?>

<div class="container-fluid py-4" id="dh-discipline-dashboard">

    <!-- A. Greeting Bar -->
    <div class="dash-greeting-bar mb-4" style="background: linear-gradient(135deg,#b91c1c 0%,#7f1d1d 100%); box-shadow: 0 3px 10px rgba(185,28,28,0.3)">
        <div>
            <h5 id="dhdGreeting">Good morning!</h5>
            <p>Teaching &amp; Discipline Administration — <span id="dhdTermBadge">—</span></p>
        </div>
        <div class="dash-meta">
            <span class="dash-badge"><i class="bi bi-shield-exclamation me-1"></i>Deputy Head (Discipline)</span>
            <span class="text-white-50 small">Updated: <span id="lastUpdated">—</span></span>
            <button class="dash-refresh-btn" id="refreshDashboard">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- B. MY TEACHING TODAY -->
    <div class="card dash-card mb-4 border-start border-4 border-danger">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0"><i class="bi bi-chalkboard me-2 text-danger"></i>My Teaching Today</h6>
                <small class="text-muted" id="dhdMyClassLabel">Loading class assignment...</small>
            </div>
            <div class="d-flex gap-2">
                <a href="home.php?route=mark_attendance" class="btn btn-sm btn-danger">
                    <i class="bi bi-clipboard-check me-1"></i>Mark Attendance
                </a>
                <a href="home.php?route=manage_lesson_plans" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-journal-plus me-1"></i>My Lesson Plans
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-2 fw-bold text-danger" id="dhdMyStudents">—</div>
                        <div class="small text-muted">My Students</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-2 fw-bold" id="dhdMyAttendance" style="color:#16a34a">—</div>
                        <div class="small text-muted">Attendance Today</div>
                        <div class="tiny text-muted" id="dhdAttendanceSub" style="font-size:.72rem"></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-2 fw-bold text-warning" id="dhdMyLessonsToday">—</div>
                        <div class="small text-muted">Lessons Today</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-2 fw-bold text-secondary" id="dhdMyPendingPlans">—</div>
                        <div class="small text-muted">Plans Pending Submit</div>
                    </div>
                </div>
            </div>
            <div class="mt-3" id="dhdMyScheduleWrap">
                <p class="text-muted small mb-2"><i class="bi bi-clock me-1"></i>Today's Schedule</p>
                <div id="dhdMySchedule" class="d-flex flex-wrap gap-2">
                    <span class="text-muted small">Loading...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- C. DISCIPLINE ADMIN KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-red">
                <div class="dash-stat-value" id="disciplineCasesValue">—</div>
                <div class="dash-stat-label">Open Cases</div>
                <div class="dash-stat-sub" id="disciplineDetail">Active investigations</div>
                <i class="bi bi-shield-exclamation dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-orange">
                <div class="dash-stat-value" id="suspensionsValue">—</div>
                <div class="dash-stat-label">Suspensions</div>
                <div class="dash-stat-sub">This term</div>
                <i class="bi bi-person-slash dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-amber">
                <div class="dash-stat-value" id="truancyCasesValue">—</div>
                <div class="dash-stat-label">Truancy Cases</div>
                <div class="dash-stat-sub">Chronic absentees</div>
                <i class="bi bi-person-x-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-purple">
                <div class="dash-stat-value" id="parentMeetingsValue">—</div>
                <div class="dash-stat-label">Parent Meetings</div>
                <div class="dash-stat-sub">Pending this week</div>
                <i class="bi bi-people-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-teal">
                <div class="dash-stat-value" id="counselingReferrals">—</div>
                <div class="dash-stat-label">Counseling Referrals</div>
                <div class="dash-stat-sub">Awaiting follow-up</div>
                <i class="bi bi-heart-pulse dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-blue">
                <div class="dash-stat-value" id="attendanceValue">—</div>
                <div class="dash-stat-label">School Attendance</div>
                <div class="dash-stat-sub" id="attendanceDetail">All classes today</div>
                <i class="bi bi-calendar-check dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- D. Charts -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-activity me-2 text-danger"></i>Discipline Cases (This Term)</h6>
                    <small class="text-muted">Cases logged per week</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap"><canvas id="disciplineTrendChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Attendance Trend (Truancy Focus)</h6>
                    <small class="text-muted">Absences vs present — last 7 school days</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap"><canvas id="disciplineAttendanceChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- E. Tables row -->
    <div class="row g-4 mb-4">
        <!-- Open Discipline Cases -->
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Open Discipline Cases</h6>
                        <small class="text-muted">Cases requiring action or follow-up</small>
                    </div>
                    <div class="d-flex gap-1">
                        <a href="home.php?route=student_discipline" class="btn btn-sm btn-danger">
                            <i class="bi bi-plus me-1"></i>Log Case
                        </a>
                        <a href="home.php?route=discipline_cases" class="btn btn-sm btn-outline-danger">View All</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Student</th><th>Class</th><th>Offence</th><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody id="disciplineTableBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Upcoming Events + Parent Meetings -->
        <div class="col-lg-5">
            <div class="card dash-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-people me-2 text-purple"></i>Pending Parent Meetings</h6>
                    <a href="home.php?route=parent_meetings" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="list-group list-group-flush" id="parentMeetingsList">
                    <div class="text-center text-muted py-3">Loading...</div>
                </div>
            </div>
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar-event me-2 text-info"></i>Upcoming Events</h6>
                </div>
                <div class="list-group list-group-flush" id="eventsList">
                    <div class="text-center text-muted py-3">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- F. Quick Actions -->
    <div class="card dash-card">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h6>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <a href="home.php?route=mark_attendance" class="dash-quick-link">
                        <i class="bi bi-clipboard-check ql-icon bg-danger text-white"></i>
                        <span>Mark My Class Attendance</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="home.php?route=student_discipline" class="dash-quick-link">
                        <i class="bi bi-shield-exclamation ql-icon bg-danger text-white"></i>
                        <span>Log Discipline Case</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="home.php?route=discipline_cases" class="dash-quick-link">
                        <i class="bi bi-list-check ql-icon bg-secondary text-white"></i>
                        <span>Manage Cases</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="home.php?route=manage_communications" class="dash-quick-link">
                        <i class="bi bi-chat-dots ql-icon bg-info text-white"></i>
                        <span>Parent Communications</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="home.php?route=formative_assessments" class="dash-quick-link">
                        <i class="bi bi-pencil-square ql-icon bg-primary text-white"></i>
                        <span>Enter My Assessment Marks</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="home.php?route=view_attendance" class="dash-quick-link">
                        <i class="bi bi-graph-up ql-icon bg-warning text-white"></i>
                        <span>Attendance Overview</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="home.php?route=student_counseling" class="dash-quick-link">
                        <i class="bi bi-heart-pulse ql-icon bg-purple text-white" style="background:#7c3aed"></i>
                        <span>Refer to Counseling</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="home.php?route=conduct_reports" class="dash-quick-link">
                        <i class="bi bi-journal-text ql-icon bg-teal text-white" style="background:#0d9488"></i>
                        <span>Conduct Reports</span><i class="bi bi-chevron-right ql-arrow"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="<?= $appBase ?>js/dashboards/deputy_head_discipline_dashboard.js"></script>
<script>
    (function () {
        const user = (typeof AuthContext !== 'undefined') ? AuthContext.getUser() : null;
        if (user) {
            const hr = new Date().getHours();
            const greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
            const name = user.first_name || user.name || '';
            document.getElementById('dhdGreeting').textContent = greet + (name ? ', ' + name : '') + '!';
        }
    })();
</script>
