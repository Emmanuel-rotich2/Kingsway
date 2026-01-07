<?php
/**
 * Academic Management System – Professional UI
 * --------------------------------------------------
 * View-only layer
 * All business logic handled in: academicsManager.js
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

/* =========================================================
   HEADER
========================================================= */
.academic-header {
    background: linear-gradient(135deg, var(--acad-primary), var(--acad-primary-dark));
    color: #fff;
    border-radius: 12px;
    padding: 1.75rem 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--acad-shadow);
}

/* =========================================================
   CARDS
========================================================= */
.academic-card {
    background: var(--acad-white);
    border-radius: 12px;
    border-left: 4px solid var(--acad-primary);
    box-shadow: var(--acad-shadow);
    margin-bottom: 1.75rem;
}

/* =========================================================
   STATISTICS
========================================================= */
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

/* =========================================================
   TABS
========================================================= */
.nav-tabs {
    border-bottom: none;
}

.nav-tabs .nav-link {
    border: none;
    font-weight: 500;
    color: var(--acad-primary-dark);
    padding: 0.75rem 1.1rem;
}

.nav-tabs .nav-link.active {
    border-bottom: 3px solid var(--acad-primary);
    color: var(--acad-primary);
    background: transparent;
}

/* =========================================================
   BUTTONS
========================================================= */
.btn-academic {
    background: var(--acad-primary);
    color: #fff;
    border: none;
}

.btn-academic:hover {
    background: var(--acad-primary-dark);
    color: #fff;
}

/* =========================================================
   TABLES
========================================================= */
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
            <i class="bi bi-mortarboard-fill me-2"></i>Academic Management
        </h2>
        <small class="opacity-75">
            Central control of academic structure & curriculum
        </small>
    </div>

    <button class="btn btn-light btn-sm" onclick="academicsManager.showQuickActions()">
        <i class="bi bi-lightning-fill me-1"></i>Quick Actions
    </button>
</div>

<!-- =======================================================
 STATISTICS
======================================================= -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="totalClasses">0</div>
            <small>Total Classes</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="totalSubjects">0</div>
            <small>Learning Areas</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="totalStudents">0</div>
            <small>Students</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="activeYear">—</div>
            <small>Current Academic Year</small>
        </div>
    </div>
</div>

<!-- =======================================================
 MAIN NAVIGATION
======================================================= -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#yearsTab">Years</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#termsTab">Terms</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#classesTab">Classes</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#subjectsTab">Learning Areas</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#streamsTab">Streams</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#schedulesTab">Timetable</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#curriculumTab">Curriculum</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#teachersTab">Teachers</button></li>
</ul>

<!-- =======================================================
 TAB CONTENT
======================================================= -->
<div class="tab-content">

    <!-- Academic Years -->
    <div class="tab-pane fade show active" id="yearsTab">
        <div class="academic-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Academic Years</h5>
                <button class="btn btn-academic btn-sm" onclick="academicsManager.showYearModal()">Add Year</button>
            </div>

            <div id="yearsContainer" class="text-center text-muted py-4">
                <div class="spinner-border text-success mb-2"></div>
                <div>Loading academic years…</div>
            </div>
        </div>
    </div>

    <!-- Academic Terms -->
    <div class="tab-pane fade" id="termsTab">
        <div class="academic-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Academic Terms</h5>
                <button class="btn btn-academic btn-sm" onclick="academicsManager.showTermModal()">Add Term</button>
            </div>
            <div id="termsContainer"></div>
        </div>
    </div>

    <!-- Classes -->
    <div class="tab-pane fade" id="classesTab">
        <div class="academic-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Classes</h5>
                <button class="btn btn-academic btn-sm" onclick="academicsManager.showClassModal()">Add Class</button>
            </div>
            <div id="classesContainer"></div>
        </div>
    </div>

    <!-- Learning Areas -->
    <div class="tab-pane fade" id="subjectsTab">
        <div class="academic-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Learning Areas</h5>
                <button class="btn btn-academic btn-sm" onclick="academicsManager.showSubjectModal()">Add Learning Area</button>
            </div>
            <div id="subjectsContainer"></div>
        </div>
    </div>

    <!-- Streams -->
    <div class="tab-pane fade" id="streamsTab">
        <div class="academic-card p-3">
            <h5>Streams</h5>
            <div id="streamsContainer"></div>
        </div>
    </div>

    <!-- Timetable -->
    <div class="tab-pane fade" id="schedulesTab">
        <div class="academic-card p-3">
            <h5>Class Timetable</h5>
            <div id="schedulesContainer"></div>
        </div>
    </div>

    <!-- Curriculum -->
    <div class="tab-pane fade" id="curriculumTab">
        <div class="academic-card p-3">
            <h5>Curriculum</h5>
            <div id="curriculumUnitsContainer"></div>
        </div>
    </div>

    <!-- Teachers -->
    <div class="tab-pane fade" id="teachersTab">
        <div class="academic-card p-3">
            <h5>Teacher Assignments</h5>
            <div id="teacherDetailsContainer"></div>
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
<script src="/Kingsway/js/pages/academicsManager.js"></script>
