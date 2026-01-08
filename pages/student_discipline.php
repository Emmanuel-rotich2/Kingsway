<?php
/**
 * Student Discipline Page
 * HTML structure only - logic will be in js/pages/student_discipline.js
 * Embedded in app_layout.php
 * 
 * Role-based access:
 * - Deputy Head Discipline: Full access (create, resolve, escalate)
 * - Headteacher: View all, resolve escalated cases
 * - Class Teacher: View and report own class only
 * - Subject Teacher: Report incidents only
 * - Counselor: View for counseling context
 * - Admin: Full access
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-danger text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-gavel"></i> Student Discipline</h4>
            <div class="btn-group">
                <!-- Record Case - Teachers, Deputy Head Discipline -->
                <button class="btn btn-light btn-sm" id="addCaseBtn" 
                        data-permission="discipline_create"
                        data-role="class_teacher,subject_teacher,deputy_head_discipline,headteacher,admin">
                    <i class="bi bi-plus-circle"></i> Record Case
                </button>
                <!-- Resolve Cases - Deputy Head, Headteacher only -->
                <button class="btn btn-outline-light btn-sm" id="resolveMultipleBtn"
                        data-permission="discipline_resolve"
                        data-role="deputy_head_discipline,headteacher,admin">
                    <i class="bi bi-check-circle"></i> Bulk Resolve
                </button>
                <!-- Export - Deputy Head, Headteacher -->
                <button class="btn btn-outline-light btn-sm" id="exportBtn"
                        data-permission="discipline_view"
                        data-role="deputy_head_discipline,headteacher,counselor,admin">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Summary Cards - Visibility varies by role -->
        <div class="row mb-4">
            <!-- Total Cases - Deputy Head, Headteacher, Admin -->
            <div class="col-md-3" data-role="deputy_head_discipline,headteacher,admin">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Cases</h6>
                        <h3 class="text-danger mb-0" id="totalCases">0</h3>
                    </div>
                </div>
            </div>
            <!-- Pending Action - All with view permission -->
            <div class="col-md-3" data-permission="discipline_view">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending Action</h6>
                        <h3 class="text-warning mb-0" id="pendingCases">0</h3>
                    </div>
                </div>
            </div>
            <!-- Resolved - Deputy Head, Headteacher, Admin -->
            <div class="col-md-3" data-role="deputy_head_discipline,headteacher,admin">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Resolved</h6>
                        <h3 class="text-success mb-0" id="resolvedCases">0</h3>
                    </div>
                </div>
            </div>
            <!-- This Term - All with view permission -->
            <div class="col-md-3" data-permission="discipline_view">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">This Term</h6>
                        <h3 class="text-info mb-0" id="casesTerm">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Escalated Cases Card - Headteacher only -->
        <div class="row mb-4" data-role="headteacher,admin">
            <div class="col-md-4">
                <div class="card border-danger bg-danger bg-opacity-10">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Escalated to You</h6>
                        <h3 class="text-danger mb-0" id="escalatedCases">0</h3>
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
                    <option value="pending">Pending</option>
                    <option value="under_review">Under Review</option>
                    <option value="resolved">Resolved</option>
                    <option value="escalated">Escalated</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="severityFilter">
                    <option value="">All Severity</option>
                    <option value="minor">Minor</option>
                    <option value="moderate">Moderate</option>
                    <option value="serious">Serious</option>
                    <option value="severe">Severe</option>
                </select>
            </div>
            <!-- Class filter - hidden for class teachers (locked to their class) -->
            <div class="col-md-2" data-role-exclude="class_teacher,subject_teacher">
                <select class="form-select" id="classFilter">
                    <option value="">All Classes</option>
                </select>
            </div>
        </div>

        <!-- Cases Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="disciplineTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Offense</th>
                        <th>Severity</th>
                        <th>Action Taken</th>
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

<!-- Add/Edit Case Modal -->
<div class="modal fade" id="caseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="caseModalTitle">Record Discipline Case</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="caseForm">
                    <input type="hidden" id="caseId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student*</label>
                            <select class="form-select" id="student" required></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Incident*</label>
                            <input type="date" class="form-control" id="incidentDate" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Offense Category*</label>
                            <select class="form-select" id="offenseCategory" required>
                                <option value="attendance">Attendance Issues</option>
                                <option value="behavior">Behavioral</option>
                                <option value="academic">Academic Misconduct</option>
                                <option value="dress_code">Dress Code Violation</option>
                                <option value="bullying">Bullying/Harassment</option>
                                <option value="substance">Substance Abuse</option>
                                <option value="property">Property Damage</option>
                                <option value="disrespect">Disrespect/Insubordination</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Severity*</label>
                            <select class="form-select" id="severity" required>
                                <option value="minor">Minor</option>
                                <option value="moderate">Moderate</option>
                                <option value="serious">Serious</option>
                                <option value="severe">Severe</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Offense Description*</label>
                        <textarea class="form-control" id="offenseDescription" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location of Incident</label>
                        <input type="text" class="form-control" id="location" placeholder="e.g., Classroom, Dormitory">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reported By*</label>
                        <input type="text" class="form-control" id="reportedBy" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Witnesses</label>
                        <textarea class="form-control" id="witnesses" rows="2" placeholder="Names of witnesses if any"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Action Taken*</label>
                        <select class="form-select" id="actionTaken" required>
                            <option value="verbal_warning">Verbal Warning</option>
                            <option value="written_warning">Written Warning</option>
                            <option value="detention">Detention</option>
                            <option value="suspension">Suspension</option>
                            <option value="community_service">Community Service</option>
                            <option value="parent_conference">Parent Conference</option>
                            <option value="counseling">Counseling Referral</option>
                            <option value="expulsion">Expulsion (Severe)</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Action Details</label>
                        <textarea class="form-control" id="actionDetails" rows="3" placeholder="Describe the action taken..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Parent Notified?*</label>
                            <select class="form-select" id="parentNotified" required>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status*</label>
                            <select class="form-select" id="status" required>
                                <option value="pending">Pending</option>
                                <option value="under_review">Under Review</option>
                                <option value="resolved">Resolved</option>
                                <option value="escalated">Escalated</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Follow-up Notes</label>
                        <textarea class="form-control" id="followUpNotes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCaseBtn">Save Case</button>
            </div>
        </div>
    </div>
</div>

<!-- View Case Details Modal -->
<div class="modal fade" id="viewCaseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Discipline Case Details</h5>
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
                        <p><strong>Severity:</strong> <span id="viewSeverity"></span></p>
                        <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Offense Description:</strong>
                    <p id="viewOffense" class="mt-2"></p>
                </div>
                <div class="mb-3">
                    <p><strong>Location:</strong> <span id="viewLocation"></span></p>
                    <p><strong>Reported By:</strong> <span id="viewReporter"></span></p>
                    <p><strong>Witnesses:</strong> <span id="viewWitnesses"></span></p>
                </div>
                <div class="mb-3">
                    <strong>Action Taken:</strong>
                    <p id="viewAction" class="mt-2"></p>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Parent Notified:</strong> <span id="viewParent"></span></p>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Follow-up Notes:</strong>
                    <p id="viewFollowUp" class="mt-2"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editFromViewBtn">
                    <i class="bi bi-pencil"></i> Edit
                </button>
                <button type="button" class="btn btn-info" id="printCaseBtn">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // TODO: Implement studentDisciplineController in js/pages/student_discipline.js
    console.log('Student Discipline page loaded');
});
</script>
