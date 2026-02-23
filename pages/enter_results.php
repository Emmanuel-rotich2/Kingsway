<?php
/**
 * Enter Results Page – Production UI
 * All logic handled in: js/pages/enter_results.js (enterResultsController)
 * UI Theme: Green / White (Academic Professional)
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
<div class="academic-header d-flex justify-content-between align-items-center">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-pencil-square me-2"></i>Enter Student Results
        </h2>
        <small class="opacity-75">
            Record and submit student assessment marks
        </small>
    </div>
    <button class="btn btn-light btn-sm" type="submit" form="resultsForm">
        <i class="bi bi-check2-circle me-1"></i>Submit Results
    </button>
</div>

<!-- =======================================================
 FILTER SELECTORS
======================================================= -->
<div class="academic-card p-3 mb-4">
    <form id="resultsForm">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Class</label>
                <select id="classSelect" class="form-select" required onchange="enterResultsController.loadStudents()">
                    <option value="">-- Select Class --</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Subject / Learning Area</label>
                <select id="subjectSelect" class="form-select" required>
                    <option value="">-- Select Subject --</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Academic Year</label>
                <select id="yearSelect" class="form-select" required>
                    <option value="">-- Select Year --</option>
                </select>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Term</label>
                <select id="termSelect" class="form-select" required>
                    <option value="">-- Select Term --</option>
                    <option value="Term 1">Term 1</option>
                    <option value="Term 2">Term 2</option>
                    <option value="Term 3">Term 3</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Assessment Type</label>
                <select id="assessmentType" class="form-select" required>
                    <option value="">-- Select Type --</option>
                    <option value="CAT">CAT</option>
                    <option value="Exam">End of Term Exam</option>
                    <option value="Assignment">Assignment</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-academic w-100">
                    <i class="bi bi-check2-circle me-1"></i>Submit Results
                </button>
            </div>
        </div>
    </form>
</div>

<!-- =======================================================
 MARKS ENTRY GRID (JS renders into this container)
======================================================= -->
<div class="academic-card p-3">
    <div id="studentsContainer">
        <div class="text-center text-muted py-5">
            <i class="bi bi-people" style="font-size: 2.5rem; color: var(--acad-primary);"></i>
            <p class="mt-3 mb-0">Select a class above to load the student marks grid</p>
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
<script src="/Kingsway/js/pages/enter_results.js"></script>
