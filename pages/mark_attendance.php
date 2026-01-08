<?php
/**
 * Mark Attendance Page
 * HTML structure only - all logic in js/pages/attendance.js (markAttendanceController)
 * Embedded in app_layout.php
 * 
 * Role-based access:
 * - Class Teacher: Mark attendance for assigned classes
 * - Subject Teacher: View attendance for classes they teach
 * - Deputy Head Academic: View all, override capabilities
 * - Headteacher: View all, generate reports
 * - Admin: Full access
 */
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-check2-square text-success me-2"></i>Mark Student Attendance</h4>
            <p class="text-muted mb-0">Record daily attendance for your class</p>
        </div>
        <div class="d-flex gap-2">
            <!-- View Attendance History - Academic staff -->
            <a href="/Kingsway/home.php?route=view_attendance" class="btn btn-outline-primary"
               data-permission-any="attendance_view,attendance_manage">
                <i class="bi bi-eye"></i> View History
            </a>
            <!-- Export Reports - Academic leadership only -->
            <button type="button" class="btn btn-outline-success" id="exportAttendanceBtn"
                    data-permission="attendance_export"
                    data-role="deputy_head_academic,headteacher,admin">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
    </div>
    
    <div class="card shadow">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Attendance Form</h5>
            <!-- Admin override for past dates -->
            <div class="form-check form-check-inline m-0" data-role="deputy_head_academic,headteacher,admin">
                <input type="checkbox" class="form-check-input" id="overridePastDate">
                <label class="form-check-label text-white" for="overridePastDate">
                    <small>Allow past date edit</small>
                </label>
            </div>
        </div>
        <div class="card-body">
            <form id="attendanceForm">
                <div class="row">
                    <!-- Class selector - Hidden for class teachers (auto-selected) -->
                    <div class="col-md-6 mb-3" id="classFilterContainer">
                        <label class="form-label fw-bold">Class</label>
                        <select id="classSelect" class="form-select" required
                            onchange="markAttendanceController.loadStudents()">
                            <option value="">-- Select Class --</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" id="attendanceDate" class="form-control" required>
                    </div>
                </div>

                <div id="studentsContainer" class="mt-4">
                    <p class="text-muted">Please select a class to load students</p>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <!-- Bulk actions - Teachers and above -->
                    <div class="btn-group" data-permission="attendance_manage">
                        <button type="button" class="btn btn-outline-success btn-sm" id="markAllPresent">
                            <i class="bi bi-check-all"></i> All Present
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="markAllAbsent">
                            <i class="bi bi-x-circle"></i> All Absent
                        </button>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg" data-permission="attendance_manage">
                        <i class="bi bi-save me-2"></i>Submit Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>