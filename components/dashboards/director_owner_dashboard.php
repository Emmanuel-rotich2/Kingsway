
<div class="container-fluid py-4">
    <!-- A. GLOBAL HEADER (Sticky) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-0"><strong>Academic Year: </strong><span id="academic_year">--</span></p>
                        <p class="text-muted mb-0"><strong>Current Term: </strong><span id="current_term">--</span></p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <small class="text-muted">Last updated: <span id="last_refresh">--</span></small>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary btn-sm" id="generate_reports">Reports</button>
                            <button class="btn btn-outline-secondary btn-sm" id="export_dashboard">Export</button>
                            <button class="btn btn-outline-info btn-sm" id="system_health">Health</button>
                            <button class="btn btn-outline-warning btn-sm" id="ceo_settings">Settings</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- B. EXECUTIVE KPI STRIP (12 Cards) -->
    <div class="row g-3 mb-4" id="kpi_strip">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">Total Students</p>
                            <h4 class="mb-0" id="total_students">--</h4>
                            <small class="text-success" id="students_delta">+0%</small>
                        </div>
                        <i class="bi-people fs-3 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">Student Growth (YoY)</p>
                            <h4 class="mb-0" id="student_growth">--</h4>
                            <small class="text-success" id="growth_delta">+0%</small>
                        </div>
                        <i class="bi-graph-up fs-3 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">Total Staff</p>
                            <h4 class="mb-0" id="total_staff">--</h4>
                            <small class="text-info" id="staff_delta">+0%</small>
                        </div>
                        <i class="bi-person-badge fs-3 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">Teacher–Student Ratio</p>
                            <h4 class="mb-0" id="teacher_student_ratio">--</h4>
                            <small class="text-warning" id="ratio_delta">+0%</small>
                        </div>
                        <i class="bi-calculator fs-3 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">Fees Collected (YTD)</p>
                            <h4 class="mb-0" id="fees_collected_ytd">--</h4>
                            <small class="text-success" id="fees_ytd_delta">+0%</small>
                        </div>
                        <i class="bi-cash-coin fs-3 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">Fees Outstanding</p>
                            <h4 class="mb-0" id="fees_outstanding">--</h4>
                            <small class="text-danger" id="outstanding_delta">+0%</small>
                        </div>
                        <i class="bi-exclamation-triangle fs-3 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">Fee Collection Rate</p>
                            <h4 class="mb-0" id="fee_collection_rate">--</h4>
                            <small class="text-primary" id="rate_delta">+0%</small>
                        </div>
                        <i class="bi-percent fs-3 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">Attendance Today</p>
                            <h4 class="mb-0" id="attendance_today">--</h4>
                            <small class="text-info" id="attendance_delta">+0%</small>
                        </div>
                        <i class="bi-calendar-check fs-3 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">Staff Attendance Today</p>
                            <h4 class="mb-0" id="staff_attendance_today">--</h4>
                            <small class="text-secondary" id="staff_attendance_delta">+0%</small>
                        </div>
                        <i class="bi-person-check fs-3 text-secondary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">Pending Approvals</p>
                            <h4 class="mb-0" id="pending_approvals">--</h4>
                            <small class="text-warning" id="approvals_delta">+0%</small>
                        </div>
                        <i class="bi-clock-history fs-3 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">Pending Admissions</p>
                            <h4 class="mb-0" id="pending_admissions">--</h4>
                            <small class="text-primary" id="admissions_delta">+0%</small>
                        </div>
                        <i class="bi-person-plus fs-3 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted small mb-1">System Alerts / Risks</p>
                            <h4 class="mb-0" id="system_alerts">--</h4>
                            <small class="text-danger" id="alerts_delta">+0%</small>
                        </div>
                        <i class="bi-shield-exclamation fs-3 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- C. FINANCIAL INTELLIGENCE SECTION -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">Financial Intelligence</h4>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Fee Collection Trend (YTD)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="fee_collection_trend_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Collected vs Outstanding</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="collected_vs_outstanding_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Revenue Sources</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="revenue_sources_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Fees by Class × Term</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <div id="fees_by_class_table"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- D. ACADEMIC PERFORMANCE ANALYTICS -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">Academic Performance Analytics</h4>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Enrollment per Class</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="enrollment_per_class_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Enrollment Growth (Multi-year)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="enrollment_growth_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4 g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Performance Heatmap</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="performance_heatmap_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Top & Bottom Classes</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="class_ranking_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Academic KPIs</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <div id="academic_kpis_table"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- E. STUDENT & STAFF DEMOGRAPHICS -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">Student & Staff Demographics</h4>
            <div class="row g-4">
                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Students by Gender</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="students_gender_chart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Staff by Role</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="staff_role_chart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Staff by Department</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="staff_department_chart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Age Distribution</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="age_distribution_chart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4 g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Student Distribution</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <div id="student_distribution_table"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Staff Deployment</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <div id="staff_deployment_table"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- F. OPERATIONS & COMPLIANCE -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">Operations & Compliance</h4>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Pending Approvals</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="pending_approvals_table">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="4" class="text-center">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Admissions Queue</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="admissions_queue_table">
                                    <thead>
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Class</th>
                                            <th>Status</th>
                                            <th>Days Pending</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="4" class="text-center">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4 g-4">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Discipline Summary</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="discipline_summary_table">
                                    <thead>
                                        <tr>
                                            <th>Incident Type</th>
                                            <th>Count</th>
                                            <th>This Month</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="3" class="text-center">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Audit Logs</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="audit_logs_table">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>User</th>
                                            <th>Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="3" class="text-center">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Approval Status</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="approval_status_chart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- G. ATTENDANCE & DISCIPLINE -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">Attendance & Discipline</h4>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Attendance Trends (30 days)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="attendance_trends_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Chronic Absenteeism</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="chronic_absenteeism_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4 g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Students Absent Today</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="students_absent_today_table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Class</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="3" class="text-center">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Staff Absent Today</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="staff_absent_today_table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Department</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="3" class="text-center">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- H. COMMUNICATIONS & ANNOUNCEMENTS -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">Communications & Announcements</h4>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Announcements Feed</h6>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <div id="announcements_feed">
                                <p class="text-muted text-center">Loading announcements...</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-bottom">
                            <h6 class="mb-0">Expiring Notices</h6>
                        </div>
                        <div class="card-body">
                            <div id="expiring_notices">
                                <p class="text-muted text-center">Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- I. QUICK LINKS (CEO TOOLBOX) -->
    <div class="row">
        <div class="col-12">
            <h4 class="mb-3">CEO Toolbox</h4>
            <div class="row g-3">
                <div class="col-md-3 col-sm-6">
                    <a href="#" class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body text-center">
                            <i class="bi-file-earmark-spreadsheet fs-2 text-primary mb-2"></i>
                            <h6 class="mb-0">Financial Reports</h6>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body text-center">
                            <i class="bi-graph-up fs-2 text-success mb-2"></i>
                            <h6 class="mb-0">Academic Reports</h6>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body text-center">
                            <i class="bi-people fs-2 text-info mb-2"></i>
                            <h6 class="mb-0">HR Reports</h6>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body text-center">
                            <i class="bi-person-plus fs-2 text-warning mb-2"></i>
                            <h6 class="mb-0">Admissions Panel</h6>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body text-center">
                            <i class="bi-cash fs-2 text-danger mb-2"></i>
                            <h6 class="mb-0">Fee Structure</h6>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body text-center">
                            <i class="bi-terminal fs-2 text-secondary mb-2"></i>
                            <h6 class="mb-0">System Logs</h6>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body text-center">
                            <i class="bi-gear fs-2 text-dark mb-2"></i>
                            <h6 class="mb-0">User Management</h6>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body text-center">
                            <i class="bi-shield-check fs-2 text-success mb-2"></i>
                            <h6 class="mb-0">Security Center</h6>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Director Dashboard Controller Script -->
<script src="js/dashboards/director_dashboard.js"></script>