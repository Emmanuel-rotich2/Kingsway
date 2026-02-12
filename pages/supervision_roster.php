<?php
/**
 * Supervision Roster Page
 * Purpose: Manage exam and duty supervision schedules for staff
 * Features: Supervision assignment, roster generation, date range filtering, conflict detection
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-clipboard-check"></i> Supervision Roster</h4>
            <small class="text-muted">Manage exam and duty supervision assignments</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportRosterBtn">
                <i class="bi bi-download"></i> Export
            </button>
            <button class="btn btn-outline-info btn-sm" id="autoGenerateBtn">
                <i class="bi bi-magic"></i> Auto-Generate
            </button>
            <button class="btn btn-success btn-sm" id="addSupervisionBtn">
                <i class="bi bi-plus-circle"></i> Add Slot
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Supervisors</h6>
                    <h3 class="text-primary mb-0" id="totalSupervisors">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Assigned Slots</h6>
                    <h3 class="text-success mb-0" id="assignedSlots">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Unassigned Slots</h6>
                    <h3 class="text-warning mb-0" id="unassignedSlots">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Exams Covered</h6>
                    <h3 class="text-info mb-0" id="examsCovered">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search Card -->
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
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="startDateFilter">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" id="endDateFilter">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchRoster" placeholder="Search supervisor or exam...">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="rosterTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Exam / Activity</th>
                            <th>Venue</th>
                            <th>Supervisor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rosterTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalRecords">0</span> slots
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Supervision Slot Modal -->
<div class="modal fade" id="supervisionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supervisionModalLabel">Add Supervision Slot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="supervisionForm">
                    <input type="hidden" id="supervisionId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" id="supDate" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Time *</label>
                            <input type="time" class="form-control" id="supTime" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Exam / Activity *</label>
                        <input type="text" class="form-control" id="supExam" required placeholder="e.g., Mathematics Paper 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Venue *</label>
                        <input type="text" class="form-control" id="supVenue" required placeholder="e.g., Hall A">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Supervisor *</label>
                        <select class="form-select" id="supSupervisor" required>
                            <option value="">Select Supervisor</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="supStatus">
                            <option value="assigned">Assigned</option>
                            <option value="unassigned">Unassigned</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="supNotes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveSupervisionBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/supervision_roster.js?v=<?php echo time(); ?>"></script>
