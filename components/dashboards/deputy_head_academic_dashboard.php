<?php
/**
 * Deputy Headteacher (Academic) Dashboard — dual role: teacher + academic administrator.
 * Role ID: 6
 *
 * Layout:
 *   A. Greeting bar (name, term, date)
 *   B. MY TEACHING TODAY — own class, attendance status, today's lessons, pending plans
 *   C. ACADEMIC ADMIN KPIs — admissions, exam cycle, timetable, lesson plan reviews
 *   D. Charts: attendance trend + class performance
 *   E. Tables: pending admissions + pending lesson plan reviews
 *   F. Quick Actions
 */
?>

<div class="container-fluid py-4" id="dh-academic-dashboard">

    <!-- A. Greeting Bar -->
    <div class="dash-greeting-bar mb-4">
        <div>
            <h5 id="dhaGreeting">Good morning!</h5>
            <p>Teaching &amp; Academic Administration — <span id="dhaTermBadge">—</span></p>
        </div>
        <div class="dash-meta">
            <span class="dash-badge"><i class="bi bi-mortarboard me-1"></i>Deputy Head (Academic)</span>
            <span class="text-white-50 small">Updated: <span id="lastUpdated">—</span></span>
            <button class="dash-refresh-btn" id="refreshDashboard">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- B. MY TEACHING TODAY -->
    <div class="card dash-card mb-4 border-start border-4 border-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0"><i class="bi bi-chalkboard me-2 text-primary"></i>My Teaching Today</h6>
                <small class="text-muted" id="dhaMyClassLabel">Loading class assignment...</small>
            </div>
            <div class="d-flex gap-2">
                <a href="home.php?route=mark_attendance" class="btn btn-sm btn-primary">
                    <i class="bi bi-clipboard-check me-1"></i>Mark Attendance
                </a>
                <a href="home.php?route=manage_lesson_plans" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-journal-plus me-1"></i>My Lesson Plans
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <!-- My class KPIs -->
                <div class="col-6 col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-2 fw-bold text-primary" id="dhaMyStudents">—</div>
                        <div class="small text-muted">My Students</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-2 fw-bold" id="dhaMyAttendance" style="color:#16a34a">—</div>
                        <div class="small text-muted">Attendance Today</div>
                        <div class="tiny text-muted" id="dhaAttendanceSub" style="font-size:.72rem"></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-2 fw-bold text-warning" id="dhaMyLessonsToday">—</div>
                        <div class="small text-muted">Lessons Today</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-2 fw-bold text-danger" id="dhaMyPendingPlans">—</div>
                        <div class="small text-muted">Plans Pending Submit</div>
                    </div>
                </div>
            </div>

            <!-- Today's teaching schedule -->
            <div class="mt-3" id="dhaMyScheduleWrap">
                <p class="text-muted small mb-2"><i class="bi bi-clock me-1"></i>Today's Schedule</p>
                <div id="dhaMySchedule" class="d-flex flex-wrap gap-2">
                    <span class="text-muted small">Loading...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- C. ACADEMIC ADMIN KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-teal">
                <div class="dash-stat-value" id="pendingAdmissionsValue">—</div>
                <div class="dash-stat-label">Pending Admissions</div>
                <div class="dash-stat-sub" id="pendingAdmissionsDetail">Awaiting placement</div>
                <i class="bi bi-person-plus-fill dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-blue">
                <div class="dash-stat-value" id="pendingLPReviewValue">—</div>
                <div class="dash-stat-label">Plans Awaiting Review</div>
                <div class="dash-stat-sub">Teacher → me → HT</div>
                <i class="bi bi-journal-check dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-amber">
                <div class="dash-stat-value" id="examSetupValue">—</div>
                <div class="dash-stat-label">Exams Scheduled</div>
                <div class="dash-stat-sub">This term</div>
                <i class="bi bi-clipboard-data dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-orange">
                <div class="dash-stat-value" id="gradingPendingValue">—</div>
                <div class="dash-stat-label">Grading Pending</div>
                <div class="dash-stat-sub">Teachers yet to submit</div>
                <i class="bi bi-hourglass-split dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-indigo">
                <div class="dash-stat-value" id="classSchedulesValue">—</div>
                <div class="dash-stat-label">Active Timetables</div>
                <div class="dash-stat-sub">Sessions this week</div>
                <i class="bi bi-calendar-week dash-stat-icon"></i>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="dash-stat dsc-green">
                <div class="dash-stat-value" id="attendanceValue">—</div>
                <div class="dash-stat-label">School Attendance</div>
                <div class="dash-stat-sub" id="attendanceDetail">All classes today</div>
                <i class="bi bi-people-fill dash-stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- D. Charts -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Attendance Trend (School-wide)</h6>
                    <small class="text-muted">Last 7 school days</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap"><canvas id="academicAttendanceChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-success"></i>Class Performance (This Term)</h6>
                    <small class="text-muted">Average scores per class</small>
                </div>
                <div class="card-body">
                    <div class="dash-chart-wrap"><canvas id="academicPerformanceChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- E. Tables row -->
    <div class="row g-4 mb-4">
        <!-- Pending Admissions -->
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-person-plus me-2 text-teal"></i>Pending Class Placement</h6>
                        <small class="text-muted">Applications awaiting academic assessment</small>
                    </div>
                    <a href="home.php?route=manage_students_admissions" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Name</th><th>Applied Class</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody id="admissionsTableBody">
                            <tr><td colspan="4" class="text-center text-muted py-4">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Lesson Plans Pending Review -->
        <div class="col-lg-6">
            <div class="card dash-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-journal-check me-2 text-warning"></i>Lesson Plans — Pending My Review</h6>
                        <small class="text-muted">Teacher submitted → I review → HT approves</small>
                    </div>
                    <a href="home.php?route=lesson_plan_approval" class="btn btn-sm btn-outline-warning">Review All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Teacher</th><th>Class</th><th>Subject</th><th>Week</th><th>Action</th></tr></thead>
                        <tbody id="lpReviewTableBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- F. Quick Actions -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card dash-card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <a href="home.php?route=mark_attendance" class="dash-quick-link">
                                <i class="bi bi-clipboard-check ql-icon bg-primary text-white"></i>
                                <span>Mark My Class Attendance</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="home.php?route=lesson_plan_approval" class="dash-quick-link">
                                <i class="bi bi-journal-check ql-icon bg-warning text-white"></i>
                                <span>Review Lesson Plans</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="home.php?route=manage_timetable" class="dash-quick-link">
                                <i class="bi bi-calendar-week ql-icon bg-info text-white"></i>
                                <span>Assign Teachers (Timetable)</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="home.php?route=exam_setup" class="dash-quick-link">
                                <i class="bi bi-clipboard-data ql-icon bg-amber text-white" style="background:#f59e0b"></i>
                                <span>Exam Setup</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="home.php?route=grading_status" class="dash-quick-link">
                                <i class="bi bi-hourglass-split ql-icon bg-danger text-white"></i>
                                <span>Grading Status</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="home.php?route=manage_students_admissions" class="dash-quick-link">
                                <i class="bi bi-person-plus ql-icon bg-success text-white"></i>
                                <span>Admissions</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="home.php?route=formative_assessments" class="dash-quick-link">
                                <i class="bi bi-pencil-square ql-icon bg-teal text-white" style="background:#0d9488"></i>
                                <span>Enter My Assessment Marks</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="home.php?route=report_cards" class="dash-quick-link">
                                <i class="bi bi-file-earmark-person ql-icon bg-secondary text-white"></i>
                                <span>Report Cards</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="home.php?route=student_promotion" class="dash-quick-link">
                                <i class="bi bi-arrow-up-circle ql-icon bg-purple text-white" style="background:#7c3aed"></i>
                                <span>Student Promotion</span><i class="bi bi-chevron-right ql-arrow"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-calendar-event me-2 text-info"></i>Upcoming Events</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" id="eventsList">
                        <li class="list-group-item text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="<?= $appBase ?>/js/dashboards/deputy_head_academic_dashboard.js"></script>
<script>
    (function () {
        const user = (typeof AuthContext !== 'undefined') ? AuthContext.getUser() : null;
        if (user) {
            const hr = new Date().getHours();
            const greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
            const name = user.first_name || user.name || '';
            document.getElementById('dhaGreeting').textContent = greet + (name ? ', ' + name : '') + '!';
        }
    })();
</script>
