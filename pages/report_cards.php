<?php
/**
 * Report Cards Page – Production UI
 * All logic handled in: js/pages/report_cards.js
 * UI Theme: Green / White (Academic Professional)
 *
 * Role-based access:
 * - Class Teacher: Generate and manage report cards for own class
 * - Headteacher: View all, approve, and sign report cards
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
            <i class="bi bi-file-earmark-text me-2"></i>Report Cards
        </h2>
        <small class="opacity-75">
            Generate, manage, and distribute student report cards
        </small>
    </div>
    <div class="btn-group">
        <button class="btn btn-light btn-sm" id="generateAllBtn"
                data-role="class_teacher,headteacher,admin">
            <i class="bi bi-file-earmark-plus me-1"></i>Generate All
        </button>
        <button class="btn btn-light btn-sm" id="downloadAllBtn"
                data-role="class_teacher,headteacher,admin">
            <i class="bi bi-download me-1"></i>Download All
        </button>
        <button class="btn btn-light btn-sm" id="printAllBtn"
                data-role="class_teacher,headteacher,admin">
            <i class="bi bi-printer me-1"></i>Print All
        </button>
    </div>
</div>

<!-- =======================================================
 KPI STATISTICS
======================================================= -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="totalStudents">0</div>
            <small>Total Students</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="cardsGenerated">0</div>
            <small>Cards Generated</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="cardsPending">0</div>
            <small>Pending</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="cardsDownloaded">0</div>
            <small>Downloaded</small>
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
        <div class="col-md-4">
            <label class="form-label fw-semibold">Search Student</label>
            <input type="text" class="form-control" id="searchBox"
                   placeholder="Search by name or admission number...">
        </div>
        <div class="col-md-2">
            <button class="btn btn-academic w-100" id="loadBtn">
                <i class="bi bi-search me-1"></i>Search
            </button>
        </div>
    </div>
</div>

<!-- =======================================================
 DATA TABLE
======================================================= -->
<div class="academic-card p-3">
    <div class="table-responsive">
        <table class="table table-hover table-academic" id="reportCardsTable">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>Student Name</th>
                    <th>Admission No</th>
                    <th>Class</th>
                    <th>Average Score</th>
                    <th>Rank</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Dynamic content -->
            </tbody>
        </table>
    </div>
    <nav>
        <ul class="pagination justify-content-center" id="pagination"></ul>
    </nav>
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
<script src="/Kingsway/js/pages/report_cards.js"></script>
