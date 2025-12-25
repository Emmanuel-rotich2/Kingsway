<?php
/**
 * Student Performance Page (Individual Student Analysis)
 * HTML structure only - logic will be in js/pages/student_performance.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-user-graduate"></i> Student Performance Analysis</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="exportBtn">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button class="btn btn-outline-light btn-sm" id="printBtn">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Student Selection -->
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Select Student*</label>
                <select class="form-select" id="studentSelect">
                    <option value="">Choose a student...</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Academic Year</label>
                <select class="form-select" id="academicYear"></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select class="form-select" id="term">
                    <option value="">All Terms</option>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary w-100" id="loadBtn">Load Report</button>
            </div>
        </div>

        <div id="reportContent" style="display: none;">
            <!-- Student Info Card -->
            <div class="card bg-light mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <img id="studentPhoto" src="" class="rounded-circle" style="width: 120px; height: 120px;"
                                alt="Student Photo">
                        </div>
                        <div class="col-md-9">
                            <h4 id="studentName"></h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Admission No:</strong> <span id="admNo"></span></p>
                                    <p class="mb-1"><strong>Class:</strong> <span id="studentClass"></span></p>
                                    <p class="mb-1"><strong>Stream:</strong> <span id="stream"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Overall Average:</strong> <span id="overallAvg"
                                            class="badge bg-primary">0%</span></p>
                                    <p class="mb-1"><strong>Class Position:</strong> <span id="position"
                                            class="badge bg-success">-</span></p>
                                    <p class="mb-1"><strong>Grade:</strong> <span id="overallGrade"
                                            class="badge bg-info">-</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <h6 class="text-muted mb-2">Total Marks</h6>
                            <h3 class="text-success mb-0" id="totalMarks">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <h6 class="text-muted mb-2">Mean Score</h6>
                            <h3 class="text-primary mb-0" id="meanScore">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <h6 class="text-muted mb-2">Subjects</h6>
                            <h3 class="text-info mb-0" id="subjectsCount">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <h6 class="text-muted mb-2">Attendance</h6>
                            <h3 class="text-warning mb-0" id="attendanceRate">0%</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Performance by Subject</h5>
                            <canvas id="subjectPerformanceChart" height="80"></canvas>
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

            <!-- Subject Performance Table -->
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
                            <tbody id="subjectsTableBody">
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Teacher Comments -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Teacher Comments</h5>
                    <div id="teacherComments">
                        <!-- Dynamic comments -->
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recommendations</h5>
                    <div id="recommendations">
                        <!-- AI-generated or manual recommendations -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="text-center py-5">
            <i class="fas fa-user-graduate fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">Select a student to view performance analysis</h5>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement studentPerformanceController in js/pages/student_performance.js
        console.log('Student Performance page loaded');
    });
</script>