<?php
/**
 * Exam Schedule Page
 * HTML structure only - logic in js/pages/exam_schedule.js
 * Embedded in app_layout.php
 *
 * Role-based access:
 * - DH-Academic: Full access (create, edit, delete schedules)
 * - Headteacher: View all, approve schedules
 * - Subject Teacher: View schedules for own subjects
 * - Admin: Full access
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Exam Schedule</h4>
                    <p class="text-muted mb-0">Plan, manage, and track examination schedules</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary btn-sm" id="addExamBtn"
                            data-role="dh_academic,headteacher,admin">
                        <i class="bi bi-plus-circle me-1"></i> Add Exam
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="exportScheduleBtn"
                            data-role="dh_academic,headteacher,admin">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="printScheduleBtn">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Exams</h6>
                    <h3 class="text-primary mb-0" id="totalExams">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Upcoming</h6>
                    <h3 class="text-info mb-0" id="upcomingExams">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">In Progress</h6>
                    <h3 class="text-warning mb-0" id="inProgressExams">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Completed</h6>
                    <h3 class="text-success mb-0" id="completedExams">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search Row -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Term</label>
                    <select class="form-select" id="termFilter">
                        <option value="">All Terms</option>
                        <option value="1">Term 1</option>
                        <option value="2">Term 2</option>
                        <option value="3">Term 3</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Subject</label>
                    <select class="form-select" id="subjectFilter">
                        <option value="">All Subjects</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
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
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="examScheduleTable">
                    <thead class="table-light">
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
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav>
                <ul class="pagination justify-content-center" id="pagination">
                    <!-- Dynamic pagination -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Add / Edit Exam Modal -->
<div class="modal fade" id="examModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="examModalTitle">Add Exam Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="examForm">
                    <input type="hidden" id="examId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Exam Name*</label>
                            <input type="text" class="form-control" id="examName" required
                                   placeholder="e.g., End of Term 1 Exam">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject*</label>
                            <select class="form-select" id="examSubject" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Class*</label>
                            <select class="form-select" id="examClass" required>
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Term*</label>
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
                            <label class="form-label">Date*</label>
                            <input type="date" class="form-control" id="examDate" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Time*</label>
                            <input type="time" class="form-control" id="examTime" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duration (minutes)*</label>
                            <input type="number" class="form-control" id="examDuration" required
                                   placeholder="e.g., 120" min="15" max="300">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Venue*</label>
                            <input type="text" class="form-control" id="examVenue" required
                                   placeholder="e.g., Main Hall, Room 201">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supervisor</label>
                            <select class="form-select" id="examSupervisor">
                                <option value="">Select Supervisor</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Instructions / Notes</label>
                        <textarea class="form-control" id="examNotes" rows="3"
                                  placeholder="Special instructions for this exam..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveExamBtn">Save Exam</button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/exam_schedule.js"></script>
