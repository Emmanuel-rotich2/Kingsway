<?php
/**
 * Staff Attendance Reports Page
 * HTML structure only - logic will be in js/pages/staff_attendance.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-secondary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-user-clock"></i> Staff Attendance Reports</h4>
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
            <div class="col-md-2">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" id="dateFrom">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" id="dateTo">
            </div>
            <div class="col-md-2">
                <label class="form-label">Department</label>
                <select class="form-select" id="department">
                    <option value="">All Departments</option>
                    <option value="teaching">Teaching</option>
                    <option value="non_teaching">Non-Teaching</option>
                    <option value="admin">Administration</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Staff</label>
                <select class="form-select" id="staffFilter">
                    <option value="">All Staff</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary w-100" id="generateBtn">Generate</button>
            </div>
        </div>

        <!-- Attendance Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Average Attendance</h6>
                        <h3 class="text-success mb-0" id="avgAttendance">0%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Present Days</h6>
                        <h3 class="text-primary mb-0" id="presentDays">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Absent Days</h6>
                        <h3 class="text-warning mb-0" id="absentDays">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Leave Days</h6>
                        <h3 class="text-info mb-0" id="leaveDays">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Attendance Trend</h5>
                        <canvas id="attendanceTrendChart" height="80"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Attendance Status</h5>
                        <canvas id="statusPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Detailed Attendance Report</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="attendanceTable">
                        <thead class="table-light">
                            <tr>
                                <th>Staff Name</th>
                                <th>Department</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Leave</th>
                                <th>Late</th>
                                <th>Attendance %</th>
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

        <!-- Daily Breakdown (expandable) -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Daily Attendance Breakdown</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="dailyBreakdownTable">
                        <thead class="table-light">
                            <tr id="dailyHeaders">
                                <th>Staff</th>
                                <!-- Dynamic date columns -->
                            </tr>
                        </thead>
                        <tbody id="dailyBody">
                            <!-- Dynamic content with color-coded attendance -->
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">
                    <small>
                        <span class="badge bg-success">P</span> Present
                        <span class="badge bg-danger">A</span> Absent
                        <span class="badge bg-info">L</span> Leave
                        <span class="badge bg-warning">Late</span> Late
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement staffAttendanceController in js/pages/staff_attendance.js
        console.log('Staff Attendance Reports page loaded');
    });
</script>