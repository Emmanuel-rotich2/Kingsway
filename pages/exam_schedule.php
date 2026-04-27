<?php
/**
 * Exam Schedule Page – Production UI
 * All logic handled in: js/pages/exam_schedule.js
 * UI Theme: Green / White (Academic Professional)
 *
 * Role-based access:
 * - DH-Academic: Full access (create, edit, delete schedules)
 * - Headteacher: View all, approve schedules
 * - Subject Teacher: View schedules for own subjects
 * - Admin: Full access
 */
?>

<style>
/* =========================================================
   DESIGN TOKENS
========================================================= */
:root {
    --acad-primary: #198754;
    --acad-primary-dark: #146c43;
    --acad-primary-soft: #d1e7dd;
    --acad-bg-light: #f8f9fa;
    --acad-white: #ffffff;
    --acad-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.academic-header {
    background: linear-gradient(135deg, var(--acad-primary), var(--acad-primary-dark));
    color: #fff;
    border-radius: 12px;
    padding: 1.75rem 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--acad-shadow);
}

.academic-card {
    background: var(--acad-white);
    border-radius: 12px;
    border-left: 4px solid var(--acad-primary);
    box-shadow: var(--acad-shadow);
    margin-bottom: 1.75rem;
}

.stat-card {
    background: var(--acad-primary-soft);
    border-radius: 10px;
    padding: 1.2rem;
    height: 100%;
    text-align: center;
}

.stat-number {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--acad-primary-dark);
}

.btn-academic {
    background: var(--acad-primary);
    color: #fff;
    border: none;
}

.btn-academic:hover {
    background: var(--acad-primary-dark);
    color: #fff;
}

.table-academic thead {
    background: var(--acad-primary);
    color: #fff;
}
</style>

<!-- =======================================================
 HEADER
======================================================= -->
<div class="academic-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-calendar-event me-2"></i>Exam Schedule
        </h2>
        <small class="opacity-75">
            Plan, manage, and track examination schedules
        </small>
    </div>
    <div class="btn-group">
        <button class="btn btn-light btn-sm" id="addExamBtn"
                data-role="dh_academic,headteacher,admin">
            <i class="bi bi-plus-circle me-1"></i>Add Exam
        </button>
        <button class="btn btn-light btn-sm" id="exportScheduleBtn"
                data-role="dh_academic,headteacher,admin">
            <i class="bi bi-download me-1"></i>Export
        </button>
        <button class="btn btn-light btn-sm" id="printScheduleBtn">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<!-- =======================================================
 KPI STATISTICS
======================================================= -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="totalExams">0</div>
            <small>Total Exams</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="upcomingExams">0</div>
            <small>Upcoming</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="inProgressExams">0</div>
            <small>In Progress</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="completedExams">0</div>
            <small>Completed</small>
        </div>
    </div>
</div>

<!-- =======================================================
 FILTER BAR
======================================================= -->
<div class="academic-card p-3 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold">Term</label>
            <select class="form-select" id="termFilter">
                <option value="">All Terms</option>
                <option value="1">Term 1</option>
                <option value="2">Term 2</option>
                <option value="3">Term 3</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Class</label>
            <select class="form-select" id="classFilter">
                <option value="">All Classes</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Subject</label>
            <select class="form-select" id="subjectFilter">
                <option value="">All Subjects</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Status</label>
            <select class="form-select" id="statusFilter">
                <option value="">All Status</option>
                <option value="upcoming">Upcoming</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="postponed">Postponed</option>
            </select>
        </div>
    </div>
</div>

<!-- =======================================================
 DATA TABLE
======================================================= -->
<div class="academic-card p-3">
    <div class="table-responsive">
        <table class="table table-hover table-academic" id="examScheduleTable">
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Subject</th>
                    <th>Class</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Duration</th>
                    <th>Venue</th>
                    <th>Supervisor</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm text-success me-2"></div>
                        Loading exam schedule...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <nav>
        <ul class="pagination justify-content-center" id="pagination"></ul>
    </nav>
</div>

<!-- =======================================================
 ADD / EDIT EXAM MODAL
======================================================= -->
<div class="modal fade" id="examModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--acad-primary); color: #fff;">
                <h5 class="modal-title" id="examModalTitle">Add Exam Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="examForm">
                    <input type="hidden" id="examId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Exam Name *</label>
                            <input type="text" class="form-control" id="examName" required
                                   placeholder="e.g., End of Term 1 Exam">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Subject *</label>
                            <select class="form-select" id="examSubject" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Class *</label>
                            <select class="form-select" id="examClass" required>
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Term *</label>
                            <select class="form-select" id="examTerm" required>
                                <option value="">Select Term</option>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Date *</label>
                            <input type="date" class="form-control" id="examDate" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Start Time *</label>
                            <input type="time" class="form-control" id="examTime" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Duration (minutes) *</label>
                            <input type="number" class="form-control" id="examDuration" required
                                   placeholder="e.g., 120" min="15" max="300">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Venue *</label>
                            <input type="text" class="form-control" id="examVenue" required
                                   placeholder="e.g., Main Hall, Room 201">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Supervisor</label>
                            <select class="form-select" id="examSupervisor">
                                <option value="">Select Supervisor</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Instructions / Notes</label>
                        <textarea class="form-control" id="examNotes" rows="3"
                                  placeholder="Special instructions for this exam..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-academic" id="saveExamBtn">
                    <i class="bi bi-check-circle me-1"></i>Save Exam
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
 TOAST NOTIFICATIONS
======================================================= -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 11000;">
    <div id="academicToast" class="toast">
        <div class="toast-header">
            <strong id="toastTitle" class="me-auto">Notice</strong>
            <button class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<!-- =======================================================
 SCRIPTS
======================================================= -->
<script src="<?= $appBase ?>/js/pages/exam_schedule.js"></script>
