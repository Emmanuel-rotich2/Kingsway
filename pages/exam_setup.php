<?php
/**
 * Exam Setup & Configuration Page – Production UI
 * All logic handled in: js/pages/exam_setup.js (examSetupController)
 * UI Theme: Green / White (Academic Professional)
 *
 * Purpose: Create and manage exam configurations, define exam papers,
 *          set marking schemes, subject weights, and grading systems
 *
 * API Endpoints:
 *   GET    /academic/exam-schedule         – List exams
 *   POST   /academic/exam-schedule         – Create exam
 *   PUT    /academic/exam-schedule/{id}    – Update exam
 *   DELETE /academic/exam-schedule/{id}    – Delete exam
 *   GET    /academic/years/list            – Academic years
 *   GET    /academic/terms/list            – Terms
 *   GET    /academic/classes/list          – Classes
 *   GET    /academic/learning-areas/list   – Subjects / Learning areas
 *
 * Role-based access:
 *   - DH-Academic / Admin: Full CRUD
 *   - Headteacher: View all, approve configurations
 *   - Subject Teacher: View assigned exams only
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
    --acad-shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.10);
    --acad-border: #dee2e6;
    --acad-text-muted: #6c757d;
    --acad-danger: #dc3545;
    --acad-warning: #ffc107;
    --acad-info: #0dcaf0;
}

/* ---- Layout ---- */
.academic-header {
    background: linear-gradient(135deg, #198754, #146c43);
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

/* ---- KPI Stats ---- */
.stat-card {
    background: var(--acad-primary-soft);
    border-radius: 10px;
    padding: 1.2rem;
    height: 100%;
    text-align: center;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--acad-shadow-lg);
}
.stat-icon {
    font-size: 1.6rem;
    color: var(--acad-primary);
    margin-bottom: 0.35rem;
}
.stat-number {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--acad-primary-dark);
}
.stat-label {
    font-size: 0.82rem;
    color: var(--acad-text-muted);
    font-weight: 500;
}

/* ---- Buttons ---- */
.btn-academic {
    background: var(--acad-primary);
    color: #fff;
    border: none;
    font-weight: 500;
}
.btn-academic:hover,
.btn-academic:focus {
    background: var(--acad-primary-dark);
    color: #fff;
}
.btn-academic-outline {
    color: var(--acad-primary);
    border: 1px solid var(--acad-primary);
    background: transparent;
}
.btn-academic-outline:hover {
    background: var(--acad-primary);
    color: #fff;
}

/* ---- Table ---- */
.table-academic thead {
    background: var(--acad-primary);
    color: #fff;
}
.table-academic thead th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
    vertical-align: middle;
    border-color: var(--acad-primary-dark);
}
.table-academic tbody td {
    vertical-align: middle;
    font-size: 0.9rem;
}
.table-academic .actions-cell {
    white-space: nowrap;
    min-width: 130px;
}

/* ---- Status Badges ---- */
.badge-status {
    font-size: 0.78rem;
    font-weight: 500;
    padding: 0.35em 0.7em;
    border-radius: 50px;
}

/* ---- Filter Bar ---- */
.filter-bar .form-select,
.filter-bar .form-control {
    border-radius: 8px;
    font-size: 0.9rem;
}

/* ---- Modal Enhancements ---- */
.modal-header-academic {
    background: linear-gradient(135deg, var(--acad-primary), var(--acad-primary-dark));
    color: #fff;
    border-bottom: none;
}
.modal-header-academic .btn-close {
    filter: brightness(0) invert(1);
}

/* ---- Subject Configuration Table ---- */
.subject-config-table {
    border-collapse: separate;
    border-spacing: 0;
}
.subject-config-table thead {
    background: var(--acad-primary-soft);
}
.subject-config-table thead th {
    color: var(--acad-primary-dark);
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    padding: 0.6rem 0.5rem;
}
.subject-config-table tbody td {
    padding: 0.4rem 0.35rem;
    vertical-align: middle;
}
.subject-config-table .form-control,
.subject-config-table .form-select {
    font-size: 0.85rem;
    padding: 0.3rem 0.5rem;
}

/* ---- Class Chips ---- */
.class-chip {
    display: inline-block;
    padding: 0.25em 0.65em;
    background: var(--acad-primary-soft);
    color: var(--acad-primary-dark);
    border-radius: 50px;
    font-size: 0.78rem;
    font-weight: 500;
    margin: 0.1rem;
}

/* ---- Grading Scale Preview ---- */
.grading-preview-table td,
.grading-preview-table th {
    padding: 0.35rem 0.6rem;
    font-size: 0.82rem;
}

/* ---- View Details Sections ---- */
.detail-section {
    border-bottom: 1px solid var(--acad-border);
    padding-bottom: 1rem;
    margin-bottom: 1rem;
}
.detail-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}
.detail-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--acad-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.2rem;
}
.detail-value {
    font-size: 0.95rem;
    font-weight: 500;
    color: #212529;
}

/* ---- Empty State ---- */
.empty-state {
    padding: 3rem 1rem;
    text-align: center;
}
.empty-state i {
    font-size: 3rem;
    color: var(--acad-primary);
    opacity: 0.5;
}
.empty-state p {
    color: var(--acad-text-muted);
    margin-top: 0.75rem;
    font-size: 0.95rem;
}

/* ---- Pagination ---- */
.pagination .page-link {
    color: var(--acad-primary);
    border-radius: 6px;
    margin: 0 2px;
    font-size: 0.85rem;
}
.pagination .page-item.active .page-link {
    background: var(--acad-primary);
    border-color: var(--acad-primary);
    color: #fff;
}

/* ---- Responsive ---- */
@media (max-width: 768px) {
    .academic-header {
        padding: 1.25rem 1rem;
    }
    .academic-header h2 {
        font-size: 1.25rem;
    }
    .stat-number {
        font-size: 1.5rem;
    }
}
</style>

<!-- =======================================================
     HEADER
======================================================= -->
<div class="academic-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-gear-wide-connected me-2"></i>Exam Setup &amp; Configuration
        </h2>
        <small class="opacity-75">
            Create exam configurations, define papers, set marking schemes and grading systems
        </small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-light btn-sm" id="btnImportConfig"
                title="Import exam configuration from template or previous term">
            <i class="bi bi-upload me-1"></i>Import Config
        </button>
        <button class="btn btn-warning btn-sm text-dark" id="btnCreateExam">
            <i class="bi bi-plus-circle-fill me-1"></i>Create Exam
        </button>
    </div>
</div>

<!-- =======================================================
     KPI STATISTICS
======================================================= -->
<div class="row g-3 mb-4" id="kpiRow">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
            <div class="stat-number" id="kpiTotal">--</div>
            <div class="stat-label">Total Exams</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-lightning-charge-fill"></i></div>
            <div class="stat-number" id="kpiActive">--</div>
            <div class="stat-label">Active Exams</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
            <div class="stat-number" id="kpiUpcoming">--</div>
            <div class="stat-label">Upcoming</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-number" id="kpiCompleted">--</div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
</div>

<!-- =======================================================
     FILTER BAR
======================================================= -->
<div class="academic-card p-3 mb-4 filter-bar">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold">
                <i class="bi bi-calendar3 me-1 text-success"></i>Academic Year
            </label>
            <select class="form-select" id="filterYear">
                <option value="">All Years</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">
                <i class="bi bi-calendar-week me-1 text-success"></i>Term
            </label>
            <select class="form-select" id="filterTerm">
                <option value="">All Terms</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">
                <i class="bi bi-mortarboard me-1 text-success"></i>Class Level
            </label>
            <select class="form-select" id="filterClass">
                <option value="">All Classes</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">
                <i class="bi bi-funnel me-1 text-success"></i>Status
            </label>
            <select class="form-select" id="filterStatus">
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="upcoming">Upcoming</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="archived">Archived</option>
            </select>
        </div>
    </div>
    <!-- Quick search row -->
    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search text-success"></i></span>
                <input type="text" class="form-control" id="filterSearch"
                       placeholder="Search by exam name...">
            </div>
        </div>
        <div class="col-md-6 d-flex align-items-end justify-content-end gap-2">
            <button class="btn btn-sm btn-academic-outline" id="btnClearFilters">
                <i class="bi bi-x-circle me-1"></i>Clear Filters
            </button>
            <button class="btn btn-sm btn-academic" id="btnApplyFilters">
                <i class="bi bi-funnel-fill me-1"></i>Apply
            </button>
        </div>
    </div>
</div>

<!-- =======================================================
     EXAMS DATA TABLE
======================================================= -->
<div class="academic-card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 fw-bold text-success">
            <i class="bi bi-table me-1"></i>Exam Configurations
        </h6>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="btnExportCsv"
                    title="Export as CSV">
                <i class="bi bi-filetype-csv me-1"></i>CSV
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="btnPrint"
                    title="Print table">
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-bordered table-academic mb-0" id="examsTable">
            <thead>
                <tr>
                    <th style="width:45px;">#</th>
                    <th>Exam Name</th>
                    <th>Academic Year</th>
                    <th>Term</th>
                    <th>Classes</th>
                    <th style="width:90px;">Subjects</th>
                    <th style="width:90px;">Max Marks</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody id="examsTableBody">
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
                        Loading exam configurations...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <small class="text-muted">
            Showing <span id="showingFrom">0</span>&ndash;<span id="showingTo">0</span>
            of <span id="totalRecords">0</span> exams
        </small>
        <nav aria-label="Exam table pagination">
            <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
        </nav>
    </div>
</div>

<!-- =======================================================
     CREATE / EDIT EXAM MODAL (modal-lg)
======================================================= -->
<div class="modal fade" id="examFormModal" tabindex="-1" aria-labelledby="examFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header modal-header-academic">
                <h5 class="modal-title" id="examFormModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Create Exam Configuration
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="examForm" novalidate>
                    <input type="hidden" id="formExamId" value="">

                    <!-- Section 1: Basic Information -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-success border-bottom pb-2 mb-3">
                            <i class="bi bi-info-circle me-1"></i>Basic Information
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="formExamName" class="form-label fw-semibold">Exam Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="formExamName" required
                                       placeholder="e.g., End of Term 1 Examination 2026">
                                <div class="invalid-feedback">Please enter the exam name.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="formAcademicYear" class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                                <select class="form-select" id="formAcademicYear" required>
                                    <option value="">-- Select Academic Year --</option>
                                </select>
                                <div class="invalid-feedback">Please select an academic year.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="formTerm" class="form-label fw-semibold">Term <span class="text-danger">*</span></label>
                                <select class="form-select" id="formTerm" required>
                                    <option value="">-- Select Term --</option>
                                </select>
                                <div class="invalid-feedback">Please select a term.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Target Classes -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-success border-bottom pb-2 mb-3">
                            <i class="bi bi-mortarboard me-1"></i>Target Classes
                        </h6>
                        <p class="text-muted small mb-2">Select the classes this exam applies to.</p>
                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto; background: var(--acad-bg-light);">
                            <div id="classCheckboxes" class="row g-2">
                                <div class="col-12 text-center text-muted py-2">
                                    <small>Loading classes...</small>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-success" id="btnSelectAllClasses">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnDeselectAllClasses">Deselect All</button>
                        </div>
                    </div>

                    <!-- Section 3: Subject Configuration -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-success border-bottom pb-2 mb-3">
                            <i class="bi bi-book me-1"></i>Subject Configuration
                        </h6>
                        <p class="text-muted small mb-2">
                            Add subjects and define maximum marks, passing marks, and weight for each.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered subject-config-table mb-0" id="subjectConfigTable">
                                <thead>
                                    <tr>
                                        <th style="min-width:200px;">Subject / Learning Area</th>
                                        <th style="width:100px;">Max Marks</th>
                                        <th style="width:110px;">Passing Marks</th>
                                        <th style="width:100px;">Weight (%)</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="subjectConfigBody">
                                    <!-- Rows added dynamically -->
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-academic-outline mt-2" id="btnAddSubjectRow">
                            <i class="bi bi-plus-lg me-1"></i>Add Subject
                        </button>
                        <div class="d-flex gap-3 mt-2">
                            <small class="text-muted">
                                Total Weight: <strong id="totalWeightDisplay">0</strong>%
                            </small>
                            <small class="text-muted">
                                Subjects: <strong id="subjectCountDisplay">0</strong>
                            </small>
                        </div>
                    </div>

                    <!-- Section 4: Exam Settings -->
                    <div class="mb-3">
                        <h6 class="fw-bold text-success border-bottom pb-2 mb-3">
                            <i class="bi bi-sliders me-1"></i>Exam Settings
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="formStartDate" class="form-label fw-semibold">Start Date</label>
                                <input type="date" class="form-control" id="formStartDate">
                            </div>
                            <div class="col-md-6">
                                <label for="formEndDate" class="form-label fw-semibold">End Date</label>
                                <input type="date" class="form-control" id="formEndDate">
                            </div>
                            <div class="col-md-6">
                                <label for="formGradingSystem" class="form-label fw-semibold">Grading System <span class="text-danger">*</span></label>
                                <select class="form-select" id="formGradingSystem" required>
                                    <option value="standard">Standard (A, B, C, D, E)</option>
                                    <option value="cbc">CBC Rubric (EE, ME, AE, BE)</option>
                                    <option value="percentage">Percentage Only</option>
                                    <option value="gpa">GPA (4.0 Scale)</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="formStatus" class="form-label fw-semibold">Status</label>
                                <select class="form-select" id="formStatus">
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                    <option value="upcoming">Upcoming</option>
                                </select>
                            </div>
                        </div>

                        <!-- Grading Scale Preview -->
                        <div class="mt-3" id="gradingScalePreview">
                            <label class="form-label fw-semibold">Grading Scale Preview</label>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered grading-preview-table mb-0">
                                    <thead style="background: var(--acad-primary-soft);">
                                        <tr>
                                            <th>Grade</th>
                                            <th>Min %</th>
                                            <th>Max %</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody id="gradingScaleBody">
                                        <!-- Populated by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mt-3">
                            <label for="formDescription" class="form-label fw-semibold">Description / Instructions</label>
                            <textarea class="form-control" id="formDescription" rows="3"
                                      placeholder="Additional exam instructions, special notes, or guidelines..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-academic" id="btnSaveExam">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="saveSpinner" role="status"></span>
                    <i class="bi bi-check-circle me-1" id="saveIcon"></i>
                    <span id="saveLabel">Save Exam</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
     VIEW EXAM DETAILS MODAL (modal-xl)
======================================================= -->
<div class="modal fade" id="viewExamModal" tabindex="-1" aria-labelledby="viewExamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header modal-header-academic">
                <h5 class="modal-title" id="viewExamModalLabel">
                    <i class="bi bi-eye me-2"></i>Exam Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewExamBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="text-muted mt-2">Loading exam details...</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning btn-sm" id="viewEditBtn">
                    <i class="bi bi-pencil me-1"></i>Edit
                </button>
                <button type="button" class="btn btn-academic btn-sm" id="viewDuplicateBtn">
                    <i class="bi bi-copy me-1"></i>Duplicate
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
     IMPORT CONFIG MODAL
======================================================= -->
<div class="modal fade" id="importConfigModal" tabindex="-1" aria-labelledby="importConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-academic">
                <h5 class="modal-title" id="importConfigModalLabel">
                    <i class="bi bi-upload me-2"></i>Import Exam Configuration
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Import From</label>
                    <select class="form-select" id="importSource">
                        <option value="previous_term">Previous Term</option>
                        <option value="template">Saved Template</option>
                        <option value="file">Upload JSON File</option>
                    </select>
                </div>
                <div id="importPreviousTerm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Source Academic Year</label>
                        <select class="form-select" id="importYear">
                            <option value="">-- Select Year --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Source Term</label>
                        <select class="form-select" id="importTerm">
                            <option value="">-- Select Term --</option>
                        </select>
                    </div>
                </div>
                <div id="importFileSection" class="d-none">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Configuration File (JSON)</label>
                        <input type="file" class="form-control" id="importFile" accept=".json">
                    </div>
                </div>
                <div class="alert alert-info border-0 mb-0" style="background: var(--acad-primary-soft); color: var(--acad-primary-dark);">
                    <i class="bi bi-info-circle me-2"></i>
                    Importing will create a new exam configuration based on the selected source.
                    You can edit it after importing.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-academic" id="btnDoImport">
                    <i class="bi bi-download me-1"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
     DELETE CONFIRMATION MODAL
======================================================= -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-1"></i>Confirm Delete
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-1">Are you sure you want to delete this exam?</p>
                <p class="fw-bold text-danger mb-0" id="deleteExamName">--</p>
                <small class="text-muted">This action cannot be undone.</small>
                <input type="hidden" id="deleteExamId" value="">
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-danger" id="btnConfirmDelete">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================
     TOAST NOTIFICATIONS
======================================================= -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 11000;">
    <div id="examSetupToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="bi bi-bell me-2" id="toastIcon"></i>
            <strong class="me-auto" id="toastTitle">Notice</strong>
            <small class="text-muted">just now</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<!-- =======================================================
     SCRIPTS
======================================================= -->
<script src="/Kingsway/js/pages/exam_setup.js?v=<?php echo time(); ?>"></script>
