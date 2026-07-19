<?php
/**
 * Student Performance Overview
 * One-page module:
 * - overview list
 * - filters
 * - view modes
 * - student performance modal
 *
 * Embedded inside app_layout.php
 */

// Ensure $appBase is available for script loading
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if ($appBase === '.' || $appBase === '/') {
        $appBase = '';
    }
}
?>

<div class="container-fluid py-4" id="studentPerformancePage">

    <!-- Print Header (only visible when printing) -->
    <div class="print-header">
        <h1>KINGSWAY PREPARATORY ACADEMY</h1>
        <h2>Student Performance Report</h2>
        <div class="date">Printed on: <span id="printDate"></span></div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-success text-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Student Performance Overview
                    </h4>
                    <small>Analyze performance by student, class, stream, term, month, or whole school.</small>
                </div>

                <div class="btn-group">
                    <button class="btn btn-light btn-sm" id="exportOverviewBtn">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button class="btn btn-outline-light btn-sm" id="printOverviewBtn">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">

            <!-- Filters -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">View Mode</label>
                    <select class="form-select" id="viewMode">
                        <option value="students">Students</option>
                        <option value="class">Class</option>
                        <option value="stream">Stream</option>
                        <option value="school">Whole School</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Academic Year</label>
                    <select class="form-select" id="academicYearFilter">
                        <option value="">All Years</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Term</label>
                    <select class="form-select" id="termFilter">
                        <option value="">All Terms</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Stream</label>
                    <select class="form-select" id="streamFilter">
                        <option value="">All Streams</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Gender</label>
                    <select class="form-select" id="genderFilter">
                        <option value="">All Genders</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="col-xl-2 col-md-4">
                    <label class="form-label fw-semibold">Month</label>
                    <input type="month" class="form-control" id="monthFilter">
                </div>

                <div class="col-xl-4 col-md-8">
                    <label class="form-label fw-semibold">Search Student</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="studentSearch"
                               placeholder="Search by name, admission number, student ID, or UPI">
                    </div>
                </div>

                <div class="col-xl-2 col-md-6 d-flex align-items-end">
                    <button class="btn btn-success w-100" id="applyFiltersBtn">
                        <i class="fas fa-filter me-1"></i> Apply
                    </button>
                </div>

                <div class="col-xl-2 col-md-6 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" id="resetFiltersBtn">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success text-white p-3">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Students</small>
                                    <h4 class="mb-0" id="summaryStudents">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-primary text-white p-3">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Average Score</small>
                                    <h4 class="mb-0" id="summaryAverage">0%</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-warning text-dark p-3">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Top Performer</small>
                                    <h6 class="mb-0" id="summaryTopStudent">-</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-info text-white p-3">
                                    <i class="fas fa-school"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Best Class / Stream</small>
                                    <h6 class="mb-0" id="summaryBestGroup">-</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- States -->
            <div id="overviewLoading" class="alert alert-info d-none">
                <i class="fas fa-spinner fa-spin me-2"></i> Loading performance records...
            </div>

            <div id="overviewError" class="alert alert-danger d-none"></div>

            <div id="overviewEmpty" class="alert alert-warning d-none">
                <i class="fas fa-info-circle me-2"></i> No performance records found for the selected filters.
            </div>

            <!-- Overview Table -->
            <div class="card border-0 shadow-sm" id="overviewCard">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>
                        <i class="fas fa-list me-2 text-success"></i>
                        Performance Records
                    </strong>
                    <span class="badge bg-success" id="viewModeBadge">Students View</span>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light" id="overviewTableHead"></thead>
                        <tbody id="performanceOverviewBody">
                            <tr>
                                <td class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Student Performance Modal -->
<div class="modal fade" id="studentPerformanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">

            <div class="modal-header bg-success text-white">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-user-graduate me-2"></i>
                        Individual Student Performance Report
                    </h5>
                    <small id="modalStudentSubtitle">Student full school profile</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <!-- Modal Filters -->
                <div class="card border-0 bg-light mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Academic Year</label>
                                <select class="form-select" id="modalAcademicYear"></select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Term</label>
                                <select class="form-select" id="modalTerm"></select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Assessment</label>
                                <select class="form-select" id="modalAssessment">
                                    <option value="">All Assessments</option>
                                </select>
                            </div>

                            <div class="col-md-12 text-end">
                                <button class="btn btn-success" id="reloadStudentReportBtn">
                                    <i class="fas fa-sync-alt me-1"></i> Reload Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="modalLoading" class="alert alert-info d-none">
                    <i class="fas fa-spinner fa-spin me-2"></i> Loading student report...
                </div>

                <div id="modalError" class="alert alert-danger d-none"></div>

                <div id="modalReportContent">

                    <!-- Student Info -->
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-2 text-center">
                                    <img id="studentPhoto" src="" class="rounded-circle border"
                                         style="width: 120px; height: 120px; object-fit: cover;"
                                         alt="Student Photo">
                                </div>

                                <div class="col-md-10">
                                    <h4 id="studentName" class="mb-2">-</h4>

                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <strong>Admission No:</strong>
                                            <span id="admNo">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Class:</strong>
                                            <span id="studentClass">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Stream:</strong>
                                            <span id="stream">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Overall Average:</strong>
                                            <span id="overallAvg" class="badge bg-primary">0%</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Position:</strong>
                                            <span id="position" class="badge bg-success">-</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Grade:</strong>
                                            <span id="overallGrade" class="badge bg-info">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card border-success h-100">
                                <div class="card-body text-center">
                                    <small class="text-muted">Total Marks</small>
                                    <h3 class="text-success mb-0" id="totalMarks">0</h3>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-primary h-100">
                                <div class="card-body text-center">
                                    <small class="text-muted">Mean Score</small>
                                    <h3 class="text-primary mb-0" id="meanScore">0%</h3>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-info h-100">
                                <div class="card-body text-center">
                                    <small class="text-muted">Subjects</small>
                                    <h3 class="text-info mb-0" id="subjectsCount">0</h3>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-warning h-100">
                                <div class="card-body text-center">
                                    <small class="text-muted">Attendance</small>
                                    <h3 class="text-warning mb-0" id="attendanceRate">0%</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Extra school profile summaries -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <small class="text-muted">Discipline Cases</small>
                                    <h4 id="disciplineCases">0</h4>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <small class="text-muted">Activities</small>
                                    <h4 id="activitiesCount">0</h4>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <small class="text-muted">Fee Balance</small>
                                    <h4 id="feeBalance">-</h4>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <small class="text-muted">Health Alerts</small>
                                    <h4 id="healthAlerts">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Performance by Subject</h5>
                                    <canvas id="subjectPerformanceChart" height="90"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Progress Trend</h5>
                                    <canvas id="progressTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subject table -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Subject-wise Performance</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Grade</th>
                                        <th>Class Average</th>
                                        <th>Position</th>
                                        <th>Teacher</th>
                                        <th>Remarks</th>
                                    </tr>
                                    </thead>
                                    <tbody id="subjectsTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Student profile tabs -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#commentsTab" type="button">
                                Teacher Comments
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#disciplineTab" type="button">
                                Discipline
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#activitiesTab" type="button">
                                Co-curricular
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#attendanceTab" type="button">
                                Attendance
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#financeTab" type="button">
                                Finance
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#recommendationsTab" type="button">
                                Recommendations
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="commentsTab">
                            <div id="teacherComments"></div>
                        </div>

                        <div class="tab-pane fade" id="disciplineTab">
                            <div id="disciplineDetails"></div>
                        </div>

                        <div class="tab-pane fade" id="activitiesTab">
                            <div id="activitiesDetails"></div>
                        </div>

                        <div class="tab-pane fade" id="attendanceTab">
                            <div id="attendanceDetails"></div>
                        </div>

                        <div class="tab-pane fade" id="financeTab">
                            <div id="financeDetails"></div>
                        </div>

                        <div class="tab-pane fade" id="recommendationsTab">
                            <div id="recommendations"></div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success" id="printStudentReportBtn">
                    <i class="bi bi-printer me-1"></i> Print Student Report
                </button>
            </div>

        </div>
    </div>
</div>

<script src="<?php echo $appBase; ?>/js/pages/student_performance.js?v=<?php echo time(); ?>"></script>
