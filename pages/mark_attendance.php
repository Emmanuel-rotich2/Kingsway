<?php
/**
 * Mark Attendance Page
 * Allows class teachers to mark daily attendance for their class
 * 
 * Features:
 * - Select class/stream from dropdown
 * - Select attendance session (Morning Class, Afternoon Class, etc.)
 * - Select date (defaults to today)
 * - Mark each student as Present, Absent, or Late
 * - Permission indicators for students on leave
 * - Bulk actions (Mark all Present, Mark all Absent)
 * - Submit attendance for the entire class
 */
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1"><i class="bi bi-check2-square text-success me-2"></i>Mark Student Attendance</h4>
                    <p class="text-muted mb-0">Record daily attendance for your class</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="/Kingsway/home.php?route=boarding_roll_call" class="btn btn-outline-info">
                        <i class="bi bi-house-door"></i> Boarding Roll Call
                    </a>
                    <a href="/Kingsway/home.php?route=view_attendance" class="btn btn-outline-primary">
                        <i class="bi bi-eye"></i> View Attendance
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- School Day Alert -->
    <div id="schoolDayAlert" class="alert alert-warning d-none mb-4">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong id="schoolDayAlertTitle">Non-School Day</strong>: <span id="schoolDayAlertText"></span>
    </div>

    <!-- Selection Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Select Class, Session & Date</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Class / Stream</label>
                    <select id="classSelect" class="form-select">
                        <option value="">-- Select Class --</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">
                        Session 
                        <i class="bi bi-info-circle" data-bs-toggle="tooltip" 
                           title="Select the attendance session (Morning or Afternoon class)"></i>
                    </label>
                    <select id="sessionSelect" class="form-select">
                        <option value="">-- Select Session --</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Date</label>
                    <input type="date" id="attendanceDate" class="form-control">
                </div>
                <div class="col-md-3 mb-3 d-flex align-items-end">
                    <button type="button" id="loadStudentsBtn" class="btn btn-success w-100">
                        <i class="bi bi-people me-1"></i> Load Students
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Attendance Table -->
    <div class="card shadow-sm" id="attendanceCard" style="display: none;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0" id="classTitle">Students</h5>
                <small class="text-muted" id="attendanceInfo">-</small>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-success btn-sm" id="markAllPresent">
                    <i class="bi bi-check-all"></i> All Present
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="markAllAbsent">
                    <i class="bi bi-x-circle"></i> All Absent
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm" id="markAllLate">
                    <i class="bi bi-clock"></i> All Late
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="studentsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 120px;">Adm No</th>
                            <th>Student Name</th>
                            <th style="width: 80px;">Type</th>
                            <th style="width: 120px;">Permission</th>
                            <th style="width: 250px;">Attendance Status</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <!-- Students will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div id="attendanceSummary">
                    <span class="badge bg-success me-2" id="presentCount">Present: 0</span>
                    <span class="badge bg-danger me-2" id="absentCount">Absent: 0</span>
                    <span class="badge bg-warning text-dark me-2" id="lateCount">Late: 0</span>
                    <span class="badge bg-info text-dark" id="permissionCount">Permission: 0</span>
                </div>
                <button type="button" id="submitAttendance" class="btn btn-success px-5">
                    <i class="bi bi-check-circle me-2"></i>Submit Attendance
                </button>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div id="loadingState" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-success" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading students...</p>
    </div>

    <!-- Empty State -->
    <div id="emptyState" class="text-center py-5" style="display: none;">
        <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
        <h5 class="mt-3 text-muted">No students found</h5>
        <p class="text-muted">Select a class to load students for attendance marking</p>
    </div>
</div>

<script src="/Kingsway/js/pages/mark_attendance.js?v=<?php echo time(); ?>"></script>