<?php
/**
 * Schemes of Work Page
 * HTML structure only - logic in js/pages/schemes_of_work.js
 * Embedded in app_layout.php
 *
 * Role-based access:
 * - Class Teacher: View and generate drafts for own class subjects
 * - Headteacher: View all, approve/reject schemes
 * - Intern: View only (read-only access)
 * - Subject Teacher: Generate and manage own subject schemes
 * - Admin: Full access
 */
?>

<div>
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-book-open me-2"></i>Schemes of Work</h4>
                    <p class="text-muted mb-0">Manage and track teaching schemes across all subjects and classes</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-success btn-sm" id="generateSchemeBtn"
                            data-role="class_teacher,subject_teacher,headteacher,admin">
                        <i class="bi bi-magic me-1"></i> Auto-Generate
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
                            <th scope="col">Subject</th>
                            <th scope="col">Class / Stream</th>
                            <th scope="col">Teacher</th>
                            <th scope="col">Term</th>
                            <th scope="col">Topic Count</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last Updated</th>
                            <th scope="col">Actions</th>
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

<!-- Auto-Generate Schemes Modal -->
<div class="modal fade" id="generateSchemeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-magic me-1"></i>Auto-Generate Schemes of Work</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small">
                    Generates weekly scheme-of-work entries from the CBC curriculum strands and sub-strands.
                    Generates a draft for one exact class stream and learning-area assignment. Existing entries for that stream, learning area and calendar week are skipped.
                </div>
                <form id="generateSchemeForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Class*</label>
                            <select class="form-select" id="genClass" required>
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Class / Stream*</label>
                            <select class="form-select" id="genStream" required>
                                <option value="">Select a class first</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject / Learning Area*</label>
                            <select class="form-select" id="genLearningArea" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Academic Term</label>
                            <input type="hidden" id="genTerm">
                            <div class="form-control bg-light" id="genTermLabel">Current term supplied automatically</div>
                        </div>
                        <div class="col-md-6 mb-3" id="genTeacherField">
                            <label class="form-label">Responsible Teacher*</label>
                            <select class="form-select" id="genTeacher">
                                <option value="">Select teacher</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Strand (optional)</label>
                            <select class="form-select" id="genStrand">
                                <option value="">All Strands</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sub-Strand (optional)</label>
                        <select class="form-select" id="genSubStrand">
                            <option value="">All Sub-Strands</option>
                        </select>
                    </div>
                    <div class="form-text">
                        Leave strand and sub-strand empty to generate the full curriculum for the subject.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmGenerateBtn">
                    <i class="bi bi-magic me-1"></i> Generate
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Scheme Details Modal -->
<div class="modal fade" id="viewSchemeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
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

<?php asset_script($appBase, 'js/pages/schemes_of_work.js'); ?>
