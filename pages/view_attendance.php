<?php
/**
 * View Attendance Page (Teacher's attendance viewing interface)
 * HTML structure only - logic will be in js/pages/view_attendance.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-calendar-check"></i> View Attendance</h4>
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
        <!-- Filter Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <label class="form-label">Class*</label>
                <select class="form-select" id="classSelect" required></select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" id="dateFrom">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" id="dateTo">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" id="statusFilter">
                    <option value="">All</option>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                    <option value="excused">Excused</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary w-100" id="loadAttendanceBtn">Load Attendance</button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Avg Attendance</h6>
                        <h3 class="text-success mb-0" id="avgAttendance">0%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Present</h6>
                        <h3 class="text-primary mb-0" id="presentCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Absent</h6>
                        <h3 class="text-danger mb-0" id="absentCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Late</h6>
                        <h3 class="text-warning mb-0" id="lateCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="attendanceTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button">
                    Student Summary
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily" type="button">
                    Daily Register
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="chart-tab" data-bs-toggle="tab" data-bs-target="#chart" type="button">
                    Trends & Analytics
                </button>
            </li>
        </ul>

        <div class="tab-content" id="attendanceTabContent">
            <!-- Summary Tab -->
            <div class="tab-pane fade show active" id="summary" role="tabpanel">
                <div class="table-responsive mt-3">
                    <table class="table table-hover" id="summaryTable">
                        <thead class="table-light">
                            <tr>
                                <th>Admission No</th>
                                <th>Student Name</th>
                                <th>Total Days</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Excused</th>
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

            <!-- Daily Register Tab -->
            <div class="tab-pane fade" id="daily" role="tabpanel">
                <div class="row mt-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Select Date</label>
                        <input type="date" class="form-control" id="dailyDate">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary w-100" id="loadDailyBtn">Load Register</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dailyTable">
                        <thead class="table-light">
                            <tr>
                                <th>Admission No</th>
                                <th>Student Name</th>
                                <th>Status</th>
                                <th>Time</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Charts Tab -->
            <div class="tab-pane fade" id="chart" role="tabpanel">
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Attendance Trend</h5>
                                <canvas id="trendChart" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Status Distribution</h5>
                                <canvas id="statusPieChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Students with Low Attendance (<80%)</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Attendance %</th>
                                                <th>Days Absent</th>
                                                <th>Last Absent</th>
                                            </tr>
                                        </thead>
                                        <tbody id="lowAttendanceBody">
                                            <!-- Dynamic content -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Student Attendance Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title">Attendance Details</h5>
                    <p class="mb-0"><strong>Student:</strong> <span id="modalStudent"></span></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h6>Present Days</h6>
                                <h4 id="modalPresent">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <h6>Absent Days</h6>
                                <h4 id="modalAbsent">0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h6>Attendance Rate</h6>
                                <h4 id="modalRate">0%</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="mb-2">Daily Attendance Records</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Time</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="modalAttendanceBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printStudentBtn">
                    <i class="bi bi-printer"></i> Print Report
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
}
.status-present { background-color: #d4edda; color: #155724; }
.status-absent { background-color: #f8d7da; color: #721c24; }
.status-late { background-color: #fff3cd; color: #856404; }
.status-excused { background-color: #d1ecf1; color: #0c5460; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // TODO: Implement viewAttendanceController in js/pages/view_attendance.js
    console.log('View Attendance page loaded');
});
</script>
