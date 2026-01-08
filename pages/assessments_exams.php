<?php
/**
 * Assessments & Exams Management Page
 * 
 * Purpose: Manage all assessments, exams, and grading
 * Features:
 * - Exam schedules and setup
 * - Supervision rosters
 * - Grading status tracking
 * - Results analysis
 * - Report card generation
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-file-alt me-2"></i>Assessments & Exams</h4>
                    <p class="text-muted mb-0">Manage examinations, grading, and academic assessments</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createExamModal">
                        <i class="fas fa-plus me-1"></i> Create Exam
                    </button>
                    <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        Quick Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-action="generate-roster">Generate Supervision
                                Roster</a></li>
                        <li><a class="dropdown-item" href="#" data-action="bulk-grading">Bulk Grade Entry</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="#" data-action="generate-reports">Generate Report Cards</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Upcoming Exams</h6>
                            <h2 class="mb-0" id="upcomingExamsCount">--</h2>
                        </div>
                        <i class="fas fa-calendar-check fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Pending Grading</h6>
                            <h2 class="mb-0" id="pendingGradingCount">--</h2>
                        </div>
                        <i class="fas fa-hourglass-half fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Completed</h6>
                            <h2 class="mb-0" id="completedExamsCount">--</h2>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Reports Ready</h6>
                            <h2 class="mb-0" id="reportsReadyCount">--</h2>
                        </div>
                        <i class="fas fa-file-pdf fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="assessmentsTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#examSchedule">
                <i class="fas fa-calendar me-1"></i> Exam Schedule
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#examSetup">
                <i class="fas fa-cog me-1"></i> Exam Setup
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#supervisionRoster">
                <i class="fas fa-users me-1"></i> Supervision Roster
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#gradingStatus">
                <i class="fas fa-tasks me-1"></i> Grading Status
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#resultsAnalysis">
                <i class="fas fa-chart-bar me-1"></i> Results Analysis
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reportCards">
                <i class="fas fa-file-alt me-1"></i> Report Cards
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Exam Schedule Tab -->
        <div class="tab-pane fade show active" id="examSchedule">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Examination Schedule</h5>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" id="filterTerm" style="width: auto;">
                            <option value="">All Terms</option>
                            <option value="1">Term 1</option>
                            <option value="2">Term 2</option>
                            <option value="3">Term 3</option>
                        </select>
                        <select class="form-select form-select-sm" id="filterExamType" style="width: auto;">
                            <option value="">All Types</option>
                            <option value="cat">CAT</option>
                            <option value="midterm">Mid-Term</option>
                            <option value="endterm">End-Term</option>
                            <option value="mock">Mock</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="examScheduleTable">
                            <thead>
                                <tr>
                                    <th>Exam Name</th>
                                    <th>Type</th>
                                    <th>Classes</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center">Loading exam schedule...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exam Setup Tab -->
        <div class="tab-pane fade" id="examSetup">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Configure Examination</h5>
                </div>
                <div class="card-body">
                    <form id="examSetupForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Select Exam</label>
                                <select class="form-select" name="exam_id" required>
                                    <option value="">Select an exam to configure</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Grading System</label>
                                <select class="form-select" name="grading_system">
                                    <option value="percentage">Percentage (0-100)</option>
                                    <option value="letter">Letter Grades (A-F)</option>
                                    <option value="points">Points (1-12)</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Pass Mark (%)</label>
                                <input type="number" class="form-control" name="pass_mark" value="40">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Marks</label>
                                <input type="number" class="form-control" name="total_marks" value="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Weight (%)</label>
                                <input type="number" class="form-control" name="weight" value="30">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Configuration</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Supervision Roster Tab -->
        <div class="tab-pane fade" id="supervisionRoster">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Exam Supervision Roster</h5>
                    <button class="btn btn-primary btn-sm" id="generateRoster">
                        <i class="fas fa-magic me-1"></i> Auto-Generate
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="supervisionTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Exam</th>
                                    <th>Venue</th>
                                    <th>Invigilator</th>
                                    <th>Assistant</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center">Loading roster...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grading Status Tab -->
        <div class="tab-pane fade" id="gradingStatus">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Grading Progress</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="gradingStatusTable">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Teacher</th>
                                    <th>Progress</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center">Loading grading status...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Analysis Tab -->
        <div class="tab-pane fade" id="resultsAnalysis">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Performance by Class</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="classPerformanceChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Grade Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="gradeDistributionChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Cards Tab -->
        <div class="tab-pane fade" id="reportCards">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Report Card Generation</h5>
                    <button class="btn btn-success btn-sm" id="bulkGenerateReports">
                        <i class="fas fa-file-pdf me-1"></i> Generate All
                    </button>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <select class="form-select" id="reportClass">
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" id="reportTerm">
                                <option value="">Select Term</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" id="previewReports">
                                <i class="fas fa-eye me-1"></i> Preview
                            </button>
                        </div>
                    </div>
                    <div id="reportPreviewArea">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-file-alt fa-4x mb-3"></i>
                            <p>Select a class and term to preview report cards</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Exam Modal -->
<div class="modal fade" id="createExamModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Examination</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createExamForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Exam Name</label>
                            <input type="text" class="form-control" name="name" placeholder="e.g., End of Term 1 Exams"
                                required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Exam Type</label>
                            <select class="form-select" name="type" required>
                                <option value="">Select Type</option>
                                <option value="cat">CAT (Continuous Assessment)</option>
                                <option value="midterm">Mid-Term Exam</option>
                                <option value="endterm">End-Term Exam</option>
                                <option value="mock">Mock Exam</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Applicable Classes</label>
                        <div class="row" id="classCheckboxes">
                            <!-- Classes will be loaded dynamically -->
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/pages/assessments_exams.js"></script>