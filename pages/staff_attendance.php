<?php
/**
 * Staff Attendance Reports Page - Production UI
 * All logic in: js/pages/staff_attendance.js (StaffAttendanceController)
 * UI Theme: Professional green-gradient header + white cards
 *
 * Features:
 *   - Today's staff overview grid grouped by attendance status
 *   - Duty type display (Teaching, Boarding, Gate, etc.)
 *   - Off-day pattern awareness
 *   - Leave status indicators
 *   - Department / duty-type / status filtering
 *   - Summary KPI cards with Charts (Chart.js)
 *   - Detailed report table + Daily breakdown
 *   - Bulk mark attendance modal
 *   - Export CSV / Print
 * API routes consumed:
 *   GET  /api/attendance/staff-today
 *   GET  /api/attendance/staff-report
 *   GET  /api/attendance/duty-types
 *   POST /api/attendance/mark-staff
 *   GET  /api/staff/departments/get
 */
?>

<!-- =========================================================
     SCOPED STYLES
========================================================= -->
<style>
    :root {
        --sa-primary: #198754;
        --sa-primary-dark: #146c43;
        --sa-primary-soft: #d1e7dd;
        --sa-bg: #f8f9fa;
        --sa-white: #fff;
        --sa-shadow: 0 2px 8px rgba(0, 0, 0, .06);
        --sa-shadow-lg: 0 4px 16px rgba(0, 0, 0, .10);
        --sa-border: #dee2e6;
    }

    .sa-header {
        background: linear-gradient(135deg, var(--sa-primary), var(--sa-primary-dark));
        color: #fff;
        border-radius: 12px;
        padding: 1.75rem 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--sa-shadow);
    }

    .sa-card {
        background: var(--sa-white);
        border-radius: 12px;
        border-left: 4px solid var(--sa-primary);
        box-shadow: var(--sa-shadow);
        margin-bottom: 1.5rem;
    }

    .sa-card .card-body {
        padding: 1.25rem 1.5rem;
    }

    .stat-mini {
        background: var(--sa-primary-soft);
        border-radius: 10px;
        padding: 1rem;
        text-align: center;
        height: 100%;
    }

    .stat-mini .num {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--sa-primary-dark);
    }

    .stat-mini .lbl {
        font-size: .78rem;
        color: #6c757d;
    }

    .btn-sa {
        background: var(--sa-primary);
        color: #fff;
        border: none;
    }

    .btn-sa:hover {
        background: var(--sa-primary-dark);
        color: #fff;
    }

    .staff-status-card {
        border-left: 4px solid;
        transition: all .2s;
    }

    .staff-status-card:hover {
        box-shadow: var(--sa-shadow-lg);
    }

    .status-present {
        border-left-color: #28a745 !important;
    }

    .status-absent {
        border-left-color: #dc3545 !important;
    }

    .status-late {
        border-left-color: #ffc107 !important;
    }

    .status-on-leave {
        border-left-color: #17a2b8 !important;
    }

    .status-off-day {
        border-left-color: #6c757d !important;
    }

    .status-not-marked {
        border-left-color: #adb5bd !important;
    }

    .table-sa thead {
        background: var(--sa-primary);
        color: #fff;
    }
</style>

<!-- =========================================================
     HEADER
========================================================= -->
<div class="sa-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-1"><i class="bi bi-calendar2-check me-2"></i>Staff Attendance</h2>
        <small class="opacity-75" id="todayDate">Loading date...</small>
    </div>
    <div class="btn-group">
        <button class="btn btn-light btn-sm"
                id="markTodayBtn"
                data-bs-toggle="modal"
                data-bs-target="#markStaffModal"
                data-permission="attendance_staff_create,attendance_staff_submit,attendance_staff_edit,attendance_staff_edit_own">
            <i class="bi bi-check2-square me-1"></i>Mark Today
        </button>
        <button class="btn btn-outline-light btn-sm"
                id="exportBtn"
                data-permission="attendance_staff_export">
            <i class="bi bi-download me-1"></i>Export
        </button>
        <button class="btn btn-outline-light btn-sm"
                id="printBtn"
                data-permission="attendance_staff_print,attendance_staff_view,attendance_staff_view_all,attendance_staff_view_own">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>
        
        <!-- =========================================================
                     TODAY'S STAFF OVERVIEW
                ========================================================= -->
        <div class="sa-card">
            <div class="card-body">
                <h6 class="fw-semibold mb-3"><i class="bi bi-people me-1"></i>Today's Staff Overview</h6>
                <div class="row g-3" id="todayStaffGrid">
                    <div class="col-12 text-center text-muted py-3">
                        <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
                        Loading today's attendance&hellip;
                    </div>
        </div>
    </div>
</div>

<!-- =========================================================
     FILTER BAR
========================================================= -->
<div class="sa-card">
    <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-funnel me-1"></i>Generate Report</h6>
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Date From</label>
                <input type="date" class="form-control" id="dateFrom">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Date To</label>
                <input type="date" class="form-control" id="dateTo">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Department</label>
                <select class="form-select" id="department">
                    <option value="">All Departments</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Duty Type</label>
                <select class="form-select" id="dutyType">
                    <option value="">All Types</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Status</label>
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                    <option value="on_leave">On Leave</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">&nbsp;</label>
                <button class="btn btn-sa w-100" id="generateBtn">
                    <i class="bi bi-search me-1"></i>Generate
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     KPI SUMMARY CARDS
========================================================= -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="stat-mini">
            <div class="num" id="avgAttendance">--%</div>
            <div class="lbl">Avg Attendance</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-mini" style="background:#d4edda;">
            <div class="num text-success" id="presentDays">0</div>
            <div class="lbl">Present</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-mini" style="background:#f8d7da;">
            <div class="num text-danger" id="absentDays">0</div>
            <div class="lbl">Absent</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-mini" style="background:#fff3cd;">
            <div class="num text-warning" id="lateDays">0</div>
            <div class="lbl">Late</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-mini" style="background:#cff4fc;">
            <div class="num text-info" id="leaveDays">0</div>
            <div class="lbl">On Leave</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-mini">
            <div class="num text-secondary" id="offDays">0</div>
            <div class="lbl">Off Days</div>
        </div>
    </div>
</div>

<!-- =========================================================
     CHARTS
========================================================= -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="sa-card">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Attendance Trend</h6>
                <canvas id="attendanceTrendChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="sa-card">
            <div class="card-body">
                <h6 class="fw-semibold mb-3">Status Distribution</h6>
                <canvas id="statusPieChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     DETAILED ATTENDANCE TABLE
========================================================= -->
<div class="sa-card">
    <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-table me-1"></i>Detailed Attendance Report</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sa" id="attendanceTable">
                <thead>
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
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            <i class="bi bi-info-circle me-1"></i>Set date range and click <strong>Generate</strong> to view the report.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- =========================================================
     DAILY BREAKDOWN
========================================================= -->
<div class="sa-card">
    <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-calendar3 me-1"></i>Daily Attendance Breakdown</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-sa" id="dailyBreakdownTable">
                <thead>
                    <tr id="dailyHeaders">
                        <th>Staff</th>
                        <th>Duty</th>
                        <!-- JS adds dynamic date columns -->
                    </tr>
                </thead>
                <tbody id="dailyBody">
                    <tr>
                        <td colspan="10" class="text-center text-muted py-3">
                            Generate a report to see the daily breakdown.
                        </td>
                    </tr>
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

<!-- =========================================================
     MARK STAFF ATTENDANCE MODAL
========================================================= -->
<div class="modal fade" id="markStaffModal" tabindex="-1" aria-labelledby="markStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,var(--sa-primary),var(--sa-primary-dark));color:#fff;">
                <h5 class="modal-title" id="markStaffModalLabel">
                    <i class="bi bi-check2-square me-2"></i>Mark Staff Attendance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Filters row inside modal -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Date</label>
                        <input type="date" class="form-control" id="markDate">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Department</label>
                        <select class="form-select" id="markDepartment">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">&nbsp;</label>
                        <button class="btn btn-sa w-100" id="loadStaffForMarkingBtn"
                                data-permission="attendance_staff_create,attendance_staff_submit,attendance_staff_edit,attendance_staff_edit_own">
                            <i class="bi bi-arrow-clockwise me-1"></i>Load Staff
                        </button>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-success" id="markAllPresentBtn"
                                data-permission="attendance_staff_create,attendance_staff_submit,attendance_staff_edit,attendance_staff_edit_own">
                            <i class="bi bi-check-all me-1"></i>All Present
                        </button>
                        <button class="btn btn-outline-danger" id="markAllAbsentBtn"
                                data-permission="attendance_staff_create,attendance_staff_submit,attendance_staff_edit,attendance_staff_edit_own">
                            <i class="bi bi-x-lg me-1"></i>All Absent
                        </button>
                    </div>
                    <small class="text-muted">Click P / A / L for each staff member</small>
                </div>

                <!-- Staff marking table -->
                <div class="table-responsive" style="max-height:400px; overflow-y:auto;">
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
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    Click <strong>Load Staff</strong> to populate the list.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sa" id="submitStaffAttendanceBtn"
                        data-permission="attendance_staff_create,attendance_staff_submit,attendance_staff_edit,attendance_staff_edit_own">
                    <i class="bi bi-check-circle me-1"></i>Submit Attendance
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="staffDetailsModal" tabindex="-1" aria-labelledby="staffDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title" id="staffDetailsModalLabel">Staff Attendance Details</h5>
                    <div class="text-muted small" id="staffDetailsMeta">Loading…</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="stat-mini">
                            <div class="num text-success" id="staffDetailsPresent">0</div>
                            <div class="lbl">Present</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-mini" style="background:#f8d7da;">
                            <div class="num text-danger" id="staffDetailsAbsent">0</div>
                            <div class="lbl">Absent</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-mini" style="background:#fff3cd;">
                            <div class="num text-warning" id="staffDetailsRate">0%</div>
                            <div class="lbl">Attendance Rate</div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody id="staffDetailsBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Loading staff attendance history…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     SCRIPTS
========================================================= -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="/Kingsway/js/pages/staff_attendance.js?v=<?= time() ?>"></script>
