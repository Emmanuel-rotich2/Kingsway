<?php
/**
 * Schemes of Work Page
 * HTML structure only - logic in js/pages/schemes_of_work.js
 * Embedded in app_layout.php
 *
 * Role-based access:
 * - Class Teacher: View and upload for own class subjects
 * - Headteacher: View all, approve/reject schemes
 * - Intern: View only (read-only access)
 * - Subject Teacher: Upload and manage own subject schemes
 * - Admin: Full access
 */
?>

<div>
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-book-open me-2"></i>Schemes of Work</h4>
                    <p class="text-muted mb-0">Manage and track teaching schemes across all subjects and classes</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary btn-sm" id="uploadSchemeBtn"
                            data-role="class_teacher,subject_teacher,headteacher,admin">
                        <i class="bi bi-upload me-1"></i> Upload Scheme
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="exportSchemesBtn"
                            data-role="headteacher,admin">
                        <i class="bi bi-download me-1"></i> Export
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
                    <h6 class="text-muted mb-2">Total Schemes</h6>
                    <h3 class="text-primary mb-0" id="totalSchemes">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Approved</h6>
                    <h3 class="text-success mb-0" id="approvedSchemes">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Pending Review</h6>
                    <h3 class="text-warning mb-0" id="pendingSchemes">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Overdue</h6>
                    <h3 class="text-danger mb-0" id="overdueSchemes">0</h3>
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
                    <label class="form-label">Subject</label>
                    <select class="form-select" id="subjectFilter">
                        <option value="">All Subjects</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="approved">Approved</option>
                        <option value="pending">Pending Review</option>
                        <option value="rejected">Rejected</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="schemesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Subject</th>
                            <th>Class</th>
                            <th>Teacher</th>
                            <th>Term</th>
                            <th>Topic Count</th>
                            <th>Status</th>
                            <th>Last Updated</th>
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

<!-- Upload / Edit Scheme Modal -->
<div class="modal fade" id="schemeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="schemeModalTitle">Upload Scheme of Work</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="schemeForm">
                    <input type="hidden" id="schemeId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject*</label>
                            <select class="form-select" id="schemeSubject" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Class*</label>
                            <select class="form-select" id="schemeClass" required>
                                <option value="">Select Class</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Term*</label>
                            <select class="form-select" id="schemeTerm" required>
                                <option value="">Select Term</option>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Academic Year*</label>
                            <select class="form-select" id="schemeYear" required>
                                <option value="">Select Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Scheme Title*</label>
                        <input type="text" class="form-control" id="schemeTitle" required
                               placeholder="e.g., Mathematics Term 1 Scheme">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Topics / Content*</label>
                        <textarea class="form-control" id="schemeTopics" rows="5" required
                                  placeholder="Enter topics covered, one per line..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload File (PDF/DOC)</label>
                        <input type="file" class="form-control" id="schemeFile"
                               accept=".pdf,.doc,.docx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="schemeNotes" rows="2"
                                  placeholder="Additional notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveSchemeBtn">Save Scheme</button>
            </div>
        </div>
    </div>
</div>

<!-- View Scheme Details Modal -->
<div class="modal fade" id="viewSchemeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Scheme of Work Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Subject:</strong> <span id="viewSubject"></span></p>
                        <p><strong>Class:</strong> <span id="viewClass"></span></p>
                        <p><strong>Teacher:</strong> <span id="viewTeacher"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Term:</strong> <span id="viewTerm"></span></p>
                        <p><strong>Topic Count:</strong> <span id="viewTopicCount"></span></p>
                        <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Topics:</strong>
                    <div id="viewTopics" class="mt-2 p-3 bg-light rounded"></div>
                </div>
                <div class="mb-3">
                    <strong>Notes:</strong>
                    <p id="viewNotes" class="mt-2"></p>
                </div>
                <div class="mb-3" id="viewFileSection" style="display: none;">
                    <strong>Attached File:</strong>
                    <a href="#" id="viewFileLink" class="btn btn-sm btn-outline-primary mt-2" target="_blank">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Download File
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="approveSchemeBtn"
                        data-role="headteacher,admin">
                    <i class="bi bi-check-circle me-1"></i> Approve
                </button>
                <button type="button" class="btn btn-danger" id="rejectSchemeBtn"
                        data-role="headteacher,admin">
                    <i class="bi bi-x-circle me-1"></i> Reject
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/schemes_of_work.js"></script>
