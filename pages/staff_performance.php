<?php
/**
 * Staff Performance Page
 * HTML structure only - logic will be in js/pages/staff_performance.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-dark text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-user-tie"></i> Staff Performance Reports</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="exportBtn">
                    <i class="bi bi-download"></i> Export
                </button>
                <button class="btn btn-outline-light btn-sm" id="printBtn">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Report Filters -->
        <div class="row mb-4">
            <div class="col-md-3">
                <label class="form-label">Staff Member</label>
                <select class="form-select" id="staffSelect">
                    <option value="">All Staff</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Department</label>
                <select class="form-select" id="department">
                    <option value="">All</option>
                    <option value="teaching">Teaching</option>
                    <option value="non_teaching">Non-Teaching</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Period</label>
                <select class="form-select" id="period">
                    <option value="current_term">Current Term</option>
                    <option value="previous_term">Previous Term</option>
                    <option value="current_year">Current Year</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary w-100" id="generateBtn">Generate</button>
            </div>
        </div>

        <!-- Performance Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Average Rating</h6>
                        <h3 class="text-success mb-0" id="avgRating">0.0</h3>
                        <small class="text-muted">out of 5.0</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Attendance Rate</h6>
                        <h3 class="text-primary mb-0" id="attendanceRate">0%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Classes/Tasks Completed</h6>
                        <h3 class="text-info mb-0" id="tasksCompleted">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Students' Pass Rate</h6>
                        <h3 class="text-warning mb-0" id="passRate">0%</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Performance Metrics</h5>
                        <canvas id="metricsRadarChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Performance Trend</h5>
                        <canvas id="trendLineChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Table -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Staff Performance Overview</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="performanceTable">
                        <thead class="table-light">
                            <tr>
                                <th>Staff Name</th>
                                <th>Department</th>
                                <th>Attendance</th>
                                <th>Punctuality</th>
                                <th>Task Completion</th>
                                <th>Student Results</th>
                                <th>Overall Rating</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Key Performance Indicators -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Key Performance Indicators (KPIs)</h5>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Teaching Staff KPIs</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Lesson Planning & Preparation
                                <span class="badge bg-success" id="lessonPlanningScore">-</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Student Performance (Class Average)
                                <span class="badge bg-primary" id="studentPerfScore">-</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Classroom Management
                                <span class="badge bg-info" id="classroomMgmtScore">-</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Professional Development
                                <span class="badge bg-warning" id="profDevScore">-</span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Common KPIs</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Punctuality & Attendance
                                <span class="badge bg-success" id="punctualityScore">-</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Teamwork & Collaboration
                                <span class="badge bg-primary" id="teamworkScore">-</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Communication Skills
                                <span class="badge bg-info" id="communicationScore">-</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Initiative & Innovation
                                <span class="badge bg-warning" id="initiativeScore">-</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appraisal History -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Appraisal History</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Period</th>
                                <th>Appraiser</th>
                                <th>Overall Score</th>
                                <th>Comments</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="appraisalHistoryBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement staffPerformanceController in js/pages/staff_performance.js
        console.log('Staff Performance page loaded');
    });
</script>