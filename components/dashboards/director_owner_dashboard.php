
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
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                    <i class="fas fa-cogs text-primary fa-lg"></i>
                </div>
                <h4 class="mb-0">Operations & Compliance</h4>
            </div>
            
            <!-- Pending Approvals -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h6 class="mb-0 "><i class="fas fa-hourglass-half me-2"></i>Pending Approvals</h6>
                        </div>
                        <div class="card-body p-0" id="pending_approvals_table">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin me-2"></i>Loading...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admissions Queue -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <h6 class="mb-0 "><i class="fas fa-user-graduate me-2"></i>Admissions Queue</h6>
                        </div>
                        <div class="card-body p-0" id="admissions_queue_table">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin me-2"></i>Loading...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Discipline Cases -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                            <h6 class="mb-0 "><i class="fas fa-exclamation-triangle me-2"></i>Discipline Cases</h6>
                        </div>
                        <div class="card-body p-0" id="discipline_summary_table">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin me-2"></i>Loading...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audit Logs -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);">
                            <h6 class="mb-0 "><i class="fas fa-history me-2"></i>Recent Audit Logs</h6>
                        </div>
                        <div class="card-body p-0" id="audit_logs_table">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin me-2"></i>Loading...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approval Status Chart -->
            <div class="row">
                <div class="col-lg-6 col-md-8 mx-auto">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h6 class="mb-0 "><i class="fas fa-chart-pie me-2"></i>Approval Status Overview</h6>
                        </div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <canvas id="approval_status_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- G. ATTENDANCE & DISCIPLINE -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3">
                    <i class="fas fa-user-check text-success fa-lg"></i>
                </div>
                <h4 class="mb-0">Attendance & Discipline</h4>
            </div>
            
            <!-- Attendance Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body text-white text-center py-3">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-users fa-2x opacity-75"></i>
                            </div>
                            <h3 class="mb-1" id="attendance_total_marked"><i class="fas fa-spinner fa-spin"></i></h3>
                            <small class="opacity-75">Marked Today</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <div class="card-body text-white text-center py-3">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-check-circle fa-2x opacity-75"></i>
                            </div>
                            <h3 class="mb-1" id="attendance_present_count"><i class="fas fa-spinner fa-spin"></i></h3>
                            <small class="opacity-75">Present Today</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="card-body text-white text-center py-3">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-times-circle fa-2x opacity-75"></i>
                            </div>
                            <h3 class="mb-1" id="attendance_absent_count"><i class="fas fa-spinner fa-spin"></i></h3>
                            <small class="opacity-75">Absent Today</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f5af19 0%, #f12711 100%);">
                        <div class="card-body text-white text-center py-3">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-clock fa-2x opacity-75"></i>
                            </div>
                            <h3 class="mb-1" id="attendance_late_count"><i class="fas fa-spinner fa-spin"></i></h3>
                            <small class="opacity-75">Late Today</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-gradient " style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Attendance Trends (30 days)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="attendance_trends_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-gradient " style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Chronic Absenteeism by Date</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="chronic_absenteeism_chart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Absent Lists Row -->
            <div class="row mt-4 g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-gradient  d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #f5af19 0%, #f12711 100%);">
                            <h6 class="mb-0"><i class="fas fa-user-times me-2"></i>Students Absent Today</h6>
                            <span class="badge bg-white text-dark" id="students_absent_badge">0</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0" id="students_absent_today_table">
                                    <thead class="table-light position-sticky top-0">
                                        <tr>
                                            <th>Name</th>
                                            <th>Class</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="3" class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-gradient  d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <h6 class="mb-0"><i class="fas fa-user-tie me-2"></i>Staff Absent Today</h6>
                            <span class="badge bg-white text-dark" id="staff_absent_badge">0</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0" id="staff_absent_today_table">
                                    <thead class="table-light position-sticky top-0">
                                        <tr>
                                            <th>Name</th>
                                            <th>Department</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="3" class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>
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
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                    <i class="fas fa-bullhorn text-primary fa-lg"></i>
                </div>
                <h4 class="mb-0">Communications & Announcements</h4>
            </div>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-gradient text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h6 class="mb-0 bg-white text-dark"><i class="fas fa-newspaper me-2"></i>Announcements Feed</h6>
                            <span class="badge bg-white text-dark" id="announcements_count">0</span>
                        </div>
                        <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                            <div id="announcements_feed" class="p-3">
                                <div class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">Loading announcements...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-gradient text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #f5af19 0%, #f12711 100%);">
                            <h6 class="mb-0 bg-white text-dark"><i class="fas fa-clock me-2"></i>Expiring Soon</h6>
                            <span class="badge bg-white text-dark" id="expiring_count">0</span>
                        </div>
                        <div class="card-body p-0">
                            <div id="expiring_notices" class="p-3">
                                <div class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin text-muted"></i>
                                    <p class="text-muted mb-0 mt-2">Loading...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- I. QUICK LINKS (TOOLBOX) -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center mb-3">
                <div class="rounded-circle bg-dark bg-opacity-10 p-2 me-3">
                    <i class="fas fa-toolbox text-dark fa-lg"></i>
                </div>
                <h4 class="mb-0">Toolbox</h4>
            </div>
            <div class="row g-3">
                <div class="col-md-3 col-sm-6">
                    <a href="#" data-route="financial_reports" class="card border-0 shadow-sm text-decoration-none h-100 toolbox-card">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="bi-file-earmark-spreadsheet fs-2 text-primary"></i>
                            </div>
                            <h6 class="mb-1">Financial Reports</h6>
                            <small class="text-muted">Revenue, expenses & budgets</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" data-route="performance_reports" class="card border-0 shadow-sm text-decoration-none h-100 toolbox-card">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="bi-graph-up fs-2 text-success"></i>
                            </div>
                            <h6 class="mb-1">Academic Reports</h6>
                            <small class="text-muted">Student performance analytics</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" data-route="manage_staff" class="card border-0 shadow-sm text-decoration-none h-100 toolbox-card">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle bg-info bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="bi-people fs-2 text-info"></i>
                            </div>
                            <h6 class="mb-1">HR & Staff</h6>
                            <small class="text-muted">Staff records & payroll</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" data-route="manage_admissions" class="card border-0 shadow-sm text-decoration-none h-100 toolbox-card">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="bi-person-plus fs-2 text-warning"></i>
                            </div>
                            <h6 class="mb-1">Admissions Panel</h6>
                            <small class="text-muted">New applications & enrollment</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" data-route="manage_fee_structure" class="card border-0 shadow-sm text-decoration-none h-100 toolbox-card">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="bi-cash fs-2 text-danger"></i>
                            </div>
                            <h6 class="mb-1">Fee Structure</h6>
                            <small class="text-muted">Configure fees & charges</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" data-route="manage_users" class="card border-0 shadow-sm text-decoration-none h-100 toolbox-card">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle bg-secondary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="bi-terminal fs-2 text-secondary"></i>
                            </div>
                            <h6 class="mb-1">System Logs</h6>
                            <small class="text-muted">Audit trails & activities</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" data-route="manage_users" class="card border-0 shadow-sm text-decoration-none h-100 toolbox-card">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle bg-dark bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="bi-gear fs-2 text-dark"></i>
                            </div>
                            <h6 class="mb-1">User Management</h6>
                            <small class="text-muted">Users, roles & permissions</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3 col-sm-6">
                    <a href="#" data-route="system_settings" class="card border-0 shadow-sm text-decoration-none h-100 toolbox-card">
                        <div class="card-body text-center py-4">
                            <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                <i class="bi-shield-check fs-2 text-success"></i>
                            </div>
                            <h6 class="mb-1">Security Center</h6>
                            <small class="text-muted">Security & access settings</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <style>
    .toolbox-card {
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .toolbox-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    .toolbox-card:hover h6 {
        color: var(--bs-primary);
    }
    </style>

    <script>
    // CEO Toolbox Navigation Handler (follows sidebar pattern)
    document.addEventListener('DOMContentLoaded', function() {
        const toolboxCards = document.querySelectorAll('.toolbox-card[data-route]');
        
        toolboxCards.forEach(card => {
            card.addEventListener('click', function(e) {
                e.preventDefault();
                const route = this.getAttribute('data-route');
                
                if (route && route !== '#') {
                    // Navigate using the same pattern as sidebar
                    window.location.href = `/Kingsway/home.php?route=${route}`;
                }
            });
        });
    });
    </script>
</div>

<!-- Director Dashboard Controller Script (with cache-busting) -->
<script src="js/dashboards/director_dashboard.js?v=<?php echo time(); ?>"></script>