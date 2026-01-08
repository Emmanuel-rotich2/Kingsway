<?php
/**
 * Staff Attendance Reports Page
 * Enhanced with duty types, off-day patterns, and leave indicators
 * HTML structure only - logic will be in js/pages/staff_attendance.js
 * Embedded in app_layout.php
 * 
 * Features:
 * - Duty type display (Teaching, Boarding, Gate, etc.)
 * - Off-day pattern awareness
 * - Leave status indicators
 * - Department filtering
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-secondary text-white">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="mb-0"><i class="fas fa-user-clock"></i> Staff Attendance Reports</h4>
            <div class="btn-group">
                <a href="#" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#markStaffModal">
                    <i class="bi bi-check2-square"></i> Mark Today
                </a>
                <button class="btn btn-outline-light btn-sm" id="exportBtn">
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
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Duty Type</label>
                <select class="form-select" id="dutyType">
                    <option value="">All Types</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                    <option value="on_leave">On Leave</option>
                    <option value="off_day">Off Day</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary w-100" id="generateBtn">
                    <i class="bi bi-search me-1"></i>Generate
                </button>
            </div>
        </div>

        <!-- Attendance Summary -->
        <div class="row mb-4">
            <div class="col">
                <div class="card border-success">
                    <div class="card-body text-center py-2">
                        <h6 class="text-muted mb-1 small">Avg Attendance</h6>
                        <h4 class="text-success mb-0" id="avgAttendance">0%</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-primary">
                    <div class="card-body text-center py-2">
                        <h6 class="text-muted mb-1 small">Present</h6>
                        <h4 class="text-primary mb-0" id="presentDays">0</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-danger">
                    <div class="card-body text-center py-2">
                        <h6 class="text-muted mb-1 small">Absent</h6>
                        <h4 class="text-danger mb-0" id="absentDays">0</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-warning">
                    <div class="card-body text-center py-2">
                        <h6 class="text-muted mb-1 small">Late</h6>
                        <h4 class="text-warning mb-0" id="lateDays">0</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-info">
                    <div class="card-body text-center py-2">
                        <h6 class="text-muted mb-1 small">On Leave</h6>
                        <h4 class="text-info mb-0" id="leaveDays">0</h4>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-secondary">
                    <div class="card-body text-center py-2">
                        <h6 class="text-muted mb-1 small">Off Days</h6>
                        <h4 class="text-secondary mb-0" id="offDays">0</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Staff Overview -->
        <div class="card mb-4" id="todayOverviewCard">
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-calendar-day me-2"></i>Today's Staff Status</h5>
                    <span class="badge bg-primary" id="todayDate"></span>
                </div>
            </div>
            <div class="card-body">
                <div class="row" id="todayStaffGrid">
                    <!-- Today's staff status will be rendered here -->
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
                        <h5 class="card-title">Status Distribution</h5>
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
                                <th>Duty Type</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Leave</th>
                                <th>Off Days</th>
                                <th>Attendance %</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
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
                                <th>Duty</th>
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
                        <span class="badge bg-warning">L</span> Late
                        <span class="badge bg-info">LV</span> Leave
                        <span class="badge bg-secondary">O</span> Off Day
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mark Staff Attendance Modal -->
<div class="modal fade" id="markStaffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-check2-square me-2"></i>Mark Staff Attendance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" id="markDate">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Department</label>
                        <select class="form-select" id="markDepartment">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary w-100" id="loadStaffForMarkingBtn">Load Staff</button>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mb-3">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-success" id="markAllPresentBtn">All Present</button>
                        <button class="btn btn-outline-danger" id="markAllAbsentBtn">All Absent</button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover table-sm" id="markStaffTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Staff</th>
                                <th>Department</th>
                                <th>Duty</th>
                                <th>Status Today</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody id="markStaffTableBody">
                            <!-- Staff list for marking -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitStaffAttendanceBtn">
                    <i class="bi bi-check-circle me-1"></i>Submit Attendance
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.staff-status-card {
    border-left: 4px solid;
    transition: all 0.2s;
}
.staff-status-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.status-present { border-left-color: #28a745 !important; }
.status-absent { border-left-color: #dc3545 !important; }
.status-late { border-left-color: #ffc107 !important; }
.status-on-leave { border-left-color: #17a2b8 !important; }
.status-off-day { border-left-color: #6c757d !important; }
</style>

<script src="/Kingsway/js/pages/staff_attendance.js?v=<?php echo time(); ?>"></script>