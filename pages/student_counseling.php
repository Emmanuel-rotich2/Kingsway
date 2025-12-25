<?php
/**
 * Student Counseling Page
 * HTML structure only - logic will be in js/pages/student_counseling.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-hands-helping"></i> Student Counseling</h4>
            <button class="btn btn-light btn-sm" id="addSessionBtn" data-permission="counseling_manage">
                <i class="bi bi-plus-circle"></i> New Session
            </button>
        </div>
    </div>

    <div class="card-body">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Sessions</h6>
                        <h3 class="text-primary mb-0" id="totalSessions">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Scheduled</h6>
                        <h3 class="text-warning mb-0" id="scheduledSessions">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Completed</h6>
                        <h3 class="text-success mb-0" id="completedSessions">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Active Cases</h6>
                        <h3 class="text-info mb-0" id="activeCases">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Row -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" class="form-control" id="searchBox" placeholder="Search student...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="academic">Academic</option>
                    <option value="behavioral">Behavioral</option>
                    <option value="personal">Personal/Social</option>
                    <option value="family">Family</option>
                    <option value="career">Career Guidance</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateFilter">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" id="exportBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Sessions Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="sessionsTable">
                <thead class="table-light">
                    <tr>
                        <th>Date/Time</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Category</th>
                        <th>Issue Summary</th>
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

<!-- Add/Edit Session Modal -->
<div class="modal fade" id="sessionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sessionModalTitle">New Counseling Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="sessionForm">
                    <input type="hidden" id="sessionId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student*</label>
                            <select class="form-select" id="student" required></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date & Time*</label>
                            <input type="datetime-local" class="form-control" id="sessionDateTime" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category*</label>
                            <select class="form-select" id="category" required>
                                <option value="academic">Academic</option>
                                <option value="behavioral">Behavioral</option>
                                <option value="personal">Personal/Social</option>
                                <option value="family">Family</option>
                                <option value="career">Career Guidance</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority*</label>
                            <select class="form-select" id="priority" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issue/Concern*</label>
                        <textarea class="form-control" id="issue" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Session Notes</label>
                        <textarea class="form-control" id="sessionNotes" rows="4" placeholder="Discussion points, observations..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Action Plan</label>
                        <textarea class="form-control" id="actionPlan" rows="3" placeholder="Recommended actions, follow-up..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status*</label>
                            <select class="form-select" id="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Follow-up Required?</label>
                            <select class="form-select" id="followUp">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3" id="followUpDateDiv" style="display: none;">
                        <label class="form-label">Follow-up Date</label>
                        <input type="date" class="form-control" id="followUpDate">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="notifyParent">
                            <label class="form-check-label" for="notifyParent">
                                Notify Parent/Guardian
                            </label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="confidential">
                            <label class="form-check-label" for="confidential">
                                Mark as Confidential
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveSessionBtn">Save Session</button>
            </div>
        </div>
    </div>
</div>

<!-- View Session Details Modal -->
<div class="modal fade" id="viewSessionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Session Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Student:</strong> <span id="viewStudent"></span></p>
                        <p><strong>Class:</strong> <span id="viewClass"></span></p>
                        <p><strong>Date:</strong> <span id="viewDate"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Category:</strong> <span id="viewCategory"></span></p>
                        <p><strong>Priority:</strong> <span id="viewPriority"></span></p>
                        <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Issue/Concern:</strong>
                    <p id="viewIssue" class="mt-2"></p>
                </div>
                <div class="mb-3">
                    <strong>Session Notes:</strong>
                    <p id="viewNotes" class="mt-2"></p>
                </div>
                <div class="mb-3">
                    <strong>Action Plan:</strong>
                    <p id="viewAction" class="mt-2"></p>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Follow-up Required:</strong> <span id="viewFollowUp"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Confidential:</strong> <span id="viewConfidential"></span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editFromViewBtn">
                    <i class="bi bi-pencil"></i> Edit
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // TODO: Implement studentCounselingController in js/pages/student_counseling.js
    console.log('Student Counseling page loaded');
});
</script>
