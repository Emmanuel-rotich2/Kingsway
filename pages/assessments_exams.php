<?php
/**
 * Assessments & Exams Management Page
 * API routes: /academic/years-list | /academic/terms-list | /academic/classes-list
 *             /academic/years (learning areas) | /academic/assessments-* | /academic/exams-*
 * Auth: X-Test-Token: devtest  |  CBC Grading: EE / ME / AE / BE
 */
?>
<style>
    /* -- Design Tokens ---------------------------------------- */
    :root {
        --ex-primary: #1565c0;
        --ex-primary-dark: #0d3c7a;
        --ex-primary-soft: #e3f2fd;
        --ex-success: #2e7d32;
        --ex-warning: #e65100;
        --ex-danger: #c62828;
        --ex-info: #01579b;
        --ex-radius: 12px;
        --ex-shadow: 0 2px 12px rgba(0, 0, 0, .07);
        --ex-surface: #ffffff;
    }

    /* -- Page shell ------------------------------------------ */
    .ex-page {
        padding: 0;
    }

/* -- Top hero bar ---------------------------------------- */
.ex-hero {
    background: linear-gradient(135deg, var(--ex-primary), var(--ex-primary-dark));
    color: #fff;
    border-radius: var(--ex-radius);
    padding: 1.6rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--ex-shadow);
}
.ex-hero h4 { font-weight: 700; margin-bottom: .15rem; }
.ex-hero small { opacity: .8; }

/* -- KPI strip ------------------------------------------- */
.ex-kpi-card {
    background: var(--ex-surface);
    border-radius: var(--ex-radius);
    box-shadow: var(--ex-shadow);
    padding: 1.25rem 1.4rem;
    border-top: 4px solid var(--ex-primary);
    transition: transform .15s;
}
.ex-kpi-card:hover { transform: translateY(-2px); }
.ex-kpi-card .kpi-val {
    font-size: 2rem; font-weight: 800;
    color: var(--ex-primary);
}
.ex-kpi-card .kpi-lbl { font-size: .78rem; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
.ex-kpi-card .kpi-icon { font-size: 1.8rem; opacity: .18; }

/* -- Filter card ----------------------------------------- */
.ex-filter-card {
    background: var(--ex-surface);
    border-radius: var(--ex-radius);
    box-shadow: var(--ex-shadow);
    padding: 1.2rem 1.5rem;
    margin-bottom: 1.25rem;
}

/* -- Tab strip ------------------------------------------- */
.ex-tabs .nav-link {
    color: #495057; font-weight: 600; font-size: .85rem;
    border: none; border-bottom: 3px solid transparent;
    padding: .65rem 1.1rem; border-radius: 0;
}
.ex-tabs .nav-link.active {
    color: var(--ex-primary);
    border-bottom-color: var(--ex-primary);
    background: transparent;
}
.ex-tabs { border-bottom: 2px solid #e9ecef; margin-bottom: 1.25rem; }

/* -- Data table card ------------------------------------- */
.ex-table-card {
    background: var(--ex-surface);
    border-radius: var(--ex-radius);
    box-shadow: var(--ex-shadow);
    overflow: hidden;
}
.ex-table-card .card-header {
    background: var(--ex-primary);
    color: #fff;
    font-weight: 700;
    padding: .9rem 1.25rem;
    font-size: .92rem;
    border-bottom: none;
}
.ex-table thead th {
    background: var(--ex-primary-soft);
    color: var(--ex-primary-dark);
    font-weight: 700;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    border-bottom: 2px solid var(--ex-primary);
    padding: .75rem 1rem;
    white-space: nowrap;
}
.ex-table tbody tr:hover { background: var(--ex-primary-soft); }
.ex-table td { padding: .7rem 1rem; font-size: .88rem; vertical-align: middle; }

/* -- CBC Grade badges ------------------------------------ */
.grade-EE { background: #c8e6c9; color: #1b5e20; font-weight: 700; padding: 2px 9px; border-radius: 20px; font-size: .78rem; }
.grade-ME { background: #b3e5fc; color: #01579b; font-weight: 700; padding: 2px 9px; border-radius: 20px; font-size: .78rem; }
.grade-AE { background: #fff9c4; color: #f57f17; font-weight: 700; padding: 2px 9px; border-radius: 20px; font-size: .78rem; }
.grade-BE { background: #ffcdd2; color: #b71c1c; font-weight: 700; padding: 2px 9px; border-radius: 20px; font-size: .78rem; }

/* -- Status pill ----------------------------------------- */
.status-pill {
    display: inline-block; padding: 3px 10px;
    border-radius: 20px; font-size: .76rem; font-weight: 600;
}
.status-approved   { background:#c8e6c9; color:#1b5e20; }
.status-pending    { background:#fff9c4; color:#e65100; }
.status-submitted  { background:#b3e5fc; color:#0277bd; }
.status-draft      { background:#eeeeee; color:#546e7a; }

/* -- Btn overrides --------------------------------------- */
.btn-ex-primary { background: var(--ex-primary); color:#fff; border:none; }
.btn-ex-primary:hover { background: var(--ex-primary-dark); color:#fff; }
.btn-ex-outline { border: 1.5px solid var(--ex-primary); color: var(--ex-primary); background:transparent; }
.btn-ex-outline:hover { background: var(--ex-primary-soft); }

/* -- Empty state ----------------------------------------- */
.ex-empty { text-align:center; padding:3rem 1rem; color:#90a4ae; }
.ex-empty i { font-size:3rem; margin-bottom:1rem; display:block; }

/* -- Loading spinner ------------------------------------- */
.ex-loading { text-align:center; padding:2.5rem; }
.ex-loading .spinner-border { width:2.5rem; height:2.5rem; color: var(--ex-primary); }

/* -- Progress bar for assessment workflow  --------------- */
.ex-workflow-step {
    display:flex; align-items:center; gap:.75rem;
    padding:.85rem 1rem; border-radius:8px;
    margin-bottom:.5rem; background: var(--ex-primary-soft);
    font-size:.85rem; font-weight:600;
}
.ex-workflow-step.done { background:#c8e6c9; color:#1b5e20; }
.ex-workflow-step.active { background:#e3f2fd; border:2px solid var(--ex-primary); color:var(--ex-primary); }
.ex-workflow-step .step-num { width:28px; height:28px; border-radius:50%; background:currentColor; display:flex; align-items:center; justify-content:center; }
.ex-workflow-step .step-num span { color:#fff; font-size:.75rem; font-weight:800; }
</style>

<div class="ex-page">

    <!-- ═══════════════════════════════════════════════════
         HERO HEADER
    ══════════════════════════════════════════════════════ -->
    <div class="ex-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-journal-text me-2"></i>Assessments &amp; Examinations</h4>
            <small>Manage formative assessments, summative exams, grading and CBC reports — Term 1 2026</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
                <i class="bi bi-plus-circle me-1"></i>New Assessment
            </button>
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#startExamWorkflowModal">
                <i class="bi bi-clipboard2-check me-1"></i>Start Exam Workflow
            </button>
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-lightning me-1"></i>Quick Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="assessExamsCtrl.bulkEnterResults()"><i class="bi bi-pencil-square me-2"></i>Bulk Grade Entry</a></li>
                    <li><a class="dropdown-item" href="#" onclick="assessExamsCtrl.exportGradebook()"><i class="bi bi-download me-2"></i>Export Gradebook</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="assessExamsCtrl.goToReports()"><i class="bi bi-file-earmark-text me-2"></i>Generate Report Cards</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         KPI STRIP
    ══════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="ex-kpi-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="kpi-val" id="kpiUpcoming">—</div>
                        <div class="kpi-lbl">Upcoming Exams</div>
                    </div>
                    <i class="bi bi-calendar-check kpi-icon text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="ex-kpi-card" style="border-top-color:#e65100">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="kpi-val" id="kpiPendingGrading" style="color:#e65100">—</div>
                        <div class="kpi-lbl">Pending Grading</div>
                    </div>
                    <i class="bi bi-hourglass-split kpi-icon text-warning"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="ex-kpi-card" style="border-top-color:#2e7d32">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="kpi-val" id="kpiCompleted" style="color:#2e7d32">—</div>
                        <div class="kpi-lbl">Completed</div>
                    </div>
                    <i class="bi bi-check2-circle kpi-icon text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="ex-kpi-card" style="border-top-color:#01579b">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="kpi-val" id="kpiReportsReady" style="color:#01579b">—</div>
                        <div class="kpi-lbl">Reports Ready</div>
                    </div>
                    <i class="bi bi-file-earmark-pdf kpi-icon text-info"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         CONTEXT BAR (Current Year / Term info)
    ══════════════════════════════════════════════════════ -->
    <div class="ex-filter-card d-flex align-items-center gap-3 flex-wrap mb-0" id="contextBar">
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small fw-semibold">Academic Year:</span>
            <span class="badge bg-primary" id="ctxYear">Loading…</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small fw-semibold">Current Term:</span>
            <span class="badge bg-success" id="ctxTerm">Loading…</span>
        </div>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <label class="text-muted small fw-semibold mb-0">Filter Class:</label>
            <select class="form-select form-select-sm" id="globalClassFilter" style="width:160px">
                <option value="">All Classes</option>
            </select>
            <label class="text-muted small fw-semibold mb-0 ms-2">Filter Term:</label>
            <select class="form-select form-select-sm" id="globalTermFilter" style="width:130px">
                <option value="">All Terms</option>
            </select>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         MAIN TABS
    ══════════════════════════════════════════════════════ -->
    <ul class="nav ex-tabs mt-3" id="examTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabAssessments">
                <i class="bi bi-clipboard2-pulse me-1"></i>Formative Assessments
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabExams">
                <i class="bi bi-book me-1"></i>Examinations
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGrading">
                <i class="bi bi-check2-square me-1"></i>Grading &amp; Results
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAnalysis">
                <i class="bi bi-graph-up me-1"></i>Results Analysis
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabWorkflow">
                <i class="bi bi-diagram-3 me-1"></i>Workflow
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ── TAB 1: FORMATIVE ASSESSMENTS ─────────────── -->
        <div class="tab-pane fade show active" id="tabAssessments">
            <div class="ex-table-card mt-1">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clipboard2-pulse me-2"></i>Formative Assessments (CA) — Current Term</span>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm bg-white text-dark" id="assessClassFilter" style="width:160px">
                            <option value="">All Classes</option>
                        </select>
                        <button class="btn btn-light btn-sm" onclick="assessExamsCtrl.loadAssessments()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                        </button>
                    </div>
                </div>
                <div class="p-0">
                    <div class="table-responsive">
                        <table class="table ex-table mb-0" id="assessmentsTable">
                            <thead>
                                <tr>
                                    <th style="width:40px"><input type="checkbox" id="selAllAssess"></th>
                                    <th>Assessment Title</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Max Marks</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Results</th>
                                    <th style="width:110px">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="assessmentsTbody">
                                <tr><td colspan="10" class="ex-loading"><div class="spinner-border"></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="assessmentsMeta">—</small>
                    <nav><ul class="pagination pagination-sm mb-0" id="assessmentsPagination"></ul></nav>
                </div>
            </div>
        </div>

        <!-- ── TAB 2: EXAMINATIONS ───────────────────────── -->
        <div class="tab-pane fade" id="tabExams">
            <div class="ex-table-card mt-1">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-book me-2"></i>Examination Schedule</span>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm bg-white text-dark" id="examTermFilter" style="width:140px">
                            <option value="">All Terms</option>
                        </select>
                        <select class="form-select form-select-sm bg-white text-dark" id="examTypeFilter" style="width:155px">
                            <option value="">All Types</option>
                            <option value="midterm">Mid-Term</option>
                            <option value="endterm">End-Term</option>
                            <option value="mock">Mock Exam</option>
                        </select>
                    </div>
                </div>
                <div class="p-0">
                    <div class="table-responsive">
                        <table class="table ex-table mb-0" id="examsTable">
                            <thead>
                                <tr>
                                    <th>Exam Title</th>
                                    <th>Type</th>
                                    <th>Classes</th>
                                    <th>Term</th>
                                    <th>Exam Date</th>
                                    <th>Max Marks</th>
                                    <th>Status</th>
                                    <th>Supervisor</th>
                                    <th style="width:110px">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="examsTbody">
                                <tr><td colspan="9" class="ex-loading"><div class="spinner-border"></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="p-3 border-top">
                    <small class="text-muted" id="examsMeta">—</small>
                </div>
            </div>
        </div>

        <!-- ── TAB 3: GRADING & RESULTS ─────────────────── -->
        <div class="tab-pane fade" id="tabGrading">
            <div class="row g-3 mb-3">
                <!-- Filters -->
                <div class="col-12">
                    <div class="ex-filter-card d-flex gap-3 flex-wrap align-items-end">
                        <div>
                            <label class="form-label small fw-semibold mb-1">Class</label>
                            <select class="form-select form-select-sm" id="gradingClassFilter" style="width:160px">
                                <option value="">All Classes</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small fw-semibold mb-1">Subject</label>
                            <select class="form-select form-select-sm" id="gradingSubjectFilter" style="width:180px">
                                <option value="">All Subjects</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small fw-semibold mb-1">Term</label>
                            <select class="form-select form-select-sm" id="gradingTermFilter" style="width:140px">
                                <option value="">All Terms</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small fw-semibold mb-1">Grade</label>
                            <select class="form-select form-select-sm" id="gradingGradeFilter" style="width:120px">
                                <option value="">All Grades</option>
                                <option value="EE">EE — Exceeds</option>
                                <option value="ME">ME — Meets</option>
                                <option value="AE">AE — Approaches</option>
                                <option value="BE">BE — Below</option>
                            </select>
                        </div>
                        <div class="ms-auto">
                            <button class="btn btn-ex-primary btn-sm" onclick="assessExamsCtrl.loadGradingResults()">
                                <i class="bi bi-search me-1"></i>Filter
                            </button>
                            <button class="btn btn-ex-outline btn-sm ms-1" onclick="assessExamsCtrl.exportGradebook()">
                                <i class="bi bi-download me-1"></i>Export
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ex-table-card">
                <div class="card-header">
                    <i class="bi bi-check2-square me-2"></i>Grading Status &amp; Results
                </div>
                <div class="p-0">
                    <div class="table-responsive">
                        <table class="table ex-table mb-0" id="gradingTable">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Admission No</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Formative %</th>
                                    <th>Summative %</th>
                                    <th>Overall %</th>
                                    <th>CBC Grade</th>
                                    <th>Remarks</th>
                                    <th style="width:90px">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="gradingTbody">
                                <tr><td colspan="10" class="ex-empty"><i class="bi bi-funnel-fill"></i>Use filters above to load grading results</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="p-3 border-top d-flex justify-content-between">
                    <small class="text-muted" id="gradingMeta">—</small>
                    <nav><ul class="pagination pagination-sm mb-0" id="gradingPagination"></ul></nav>
                </div>
            </div>
        </div>

        <!-- ── TAB 4: RESULTS ANALYSIS ──────────────────── -->
        <div class="tab-pane fade" id="tabAnalysis">
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Class</label>
                    <select class="form-select form-select-sm" id="analysisClass">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Term</label>
                    <select class="form-select form-select-sm" id="analysisTerm">
                        <option value="">All Terms</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Subject</label>
                    <select class="form-select form-select-sm" id="analysisSubject">
                        <option value="">All Subjects</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-ex-primary btn-sm w-100" onclick="assessExamsCtrl.runAnalysis()">
                        <i class="bi bi-graph-up me-1"></i>Run Analysis
                    </button>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ex-table-card">
                        <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Class Performance Distribution</div>
                        <div class="p-3" style="height:300px; position:relative">
                            <canvas id="classPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ex-table-card">
                        <div class="card-header"><i class="bi bi-pie-chart me-2"></i>CBC Grade Distribution</div>
                        <div class="p-3" style="height:300px; position:relative">
                            <canvas id="gradeDistChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="ex-table-card">
                        <div class="card-header"><i class="bi bi-table me-2"></i>Subject Performance Summary</div>
                        <div class="p-0">
                            <div class="table-responsive">
                                <table class="table ex-table mb-0" id="subjectSummaryTable">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Level</th>
                                            <th>Students Assessed</th>
                                            <th>Avg Formative %</th>
                                            <th>Avg Summative %</th>
                                            <th>Avg Overall %</th>
                                            <th>EE</th><th>ME</th><th>AE</th><th>BE</th>
                                            <th>Pass Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody id="subjectSummaryTbody">
                                        <tr><td colspan="11" class="ex-empty"><i class="bi bi-bar-chart"></i>Run analysis to see subject performance</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── TAB 5: WORKFLOW ───────────────────────────── -->
        <div class="tab-pane fade" id="tabWorkflow">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ex-table-card h-100">
                        <div class="card-header"><i class="bi bi-diagram-3 me-2"></i>Exam Workflow Progress</div>
                        <div class="p-3">
                            <div class="ex-workflow-step done" id="wf-step1">
                                <div class="step-num"><span>1</span></div>
                                <div><div class="fw-bold">Create Exam Schedule</div><small class="opacity-75">Define exam dates, subjects, and rooms</small></div>
                                <span class="ms-auto badge bg-success">Done</span>
                            </div>
                            <div class="ex-workflow-step done" id="wf-step2">
                                <div class="step-num"><span>2</span></div>
                                <div><div class="fw-bold">Submit Question Papers</div><small class="opacity-75">Upload and approve question papers</small></div>
                                <span class="ms-auto badge bg-success">Done</span>
                            </div>
                            <div class="ex-workflow-step active" id="wf-step3">
                                <div class="step-num"><span>3</span></div>
                                <div><div class="fw-bold">Conduct Examination</div><small class="opacity-75">Supervision and exam delivery</small></div>
                                <span class="ms-auto badge bg-primary">Active</span>
                            </div>
                            <div class="ex-workflow-step" id="wf-step4">
                                <div class="step-num"><span>4</span></div>
                                <div><div class="fw-bold">Mark &amp; Grade</div><small class="opacity-75">Enter and verify marks</small></div>
                                <span class="ms-auto badge bg-secondary">Pending</span>
                            </div>
                            <div class="ex-workflow-step" id="wf-step5">
                                <div class="step-num"><span>5</span></div>
                                <div><div class="fw-bold">Moderate &amp; Approve</div><small class="opacity-75">HOD verification and approval</small></div>
                                <span class="ms-auto badge bg-secondary">Pending</span>
                            </div>
                            <div class="ex-workflow-step" id="wf-step6">
                                <div class="step-num"><span>6</span></div>
                                <div><div class="fw-bold">Compile &amp; Release Results</div><small class="opacity-75">Generate and distribute report cards</small></div>
                                <span class="ms-auto badge bg-secondary">Pending</span>
                            </div>
                            <div class="mt-3 d-flex gap-2">
                                <button class="btn btn-ex-primary btn-sm" onclick="assessExamsCtrl.advanceWorkflow()">
                                    <i class="bi bi-arrow-right-circle me-1"></i>Advance Workflow
                                </button>
                                <button class="btn btn-ex-outline btn-sm" onclick="assessExamsCtrl.viewWorkflowLogs()">
                                    <i class="bi bi-list-ul me-1"></i>View Log
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ex-table-card h-100">
                        <div class="card-header"><i class="bi bi-megaphone me-2"></i>Workflow Actions &amp; Notifications</div>
                        <div class="p-3">
                            <div id="workflowActions">
                                <div class="ex-empty">
                                    <i class="bi bi-diagram-3"></i>
                                    <p>Workflow actions will appear here based on current step</p>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-ex-outline btn-sm" onclick="assessExamsCtrl.startExamWorkflow()">
                                    <i class="bi bi-play-circle me-1"></i>Start New Exam Workflow
                                </button>
                                <button class="btn btn-ex-outline btn-sm" onclick="assessExamsCtrl.startAssessmentWorkflow()">
                                    <i class="bi bi-clipboard-plus me-1"></i>Start Assessment Workflow
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /tab-content -->

</div><!-- /ex-page -->

<!-- ═══════════════════════════════════════════════════════
     MODAL: CREATE ASSESSMENT
════════════════════════════════════════════════════════ -->
<div class="modal fade" id="createAssessmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--ex-primary);color:#fff">
                <h5 class="modal-title"><i class="bi bi-clipboard-plus me-2"></i>Create Assessment</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="createAssessmentForm" onsubmit="assessExamsCtrl.submitAssessment(event)">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Assessment Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" placeholder="e.g., Mathematics CAT 1 — Term 1 2026" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="assessment_type" required>
                                <option value="">Select Type</option>
                                <optgroup label="Formative (CA)">
                                    <option value="class_activity">Class Activity</option>
                                    <option value="assignment">Assignment</option>
                                    <option value="oral_test">Oral Test</option>
                                    <option value="quiz">Quiz</option>
                                    <option value="practical">Practical Work</option>
                                    <option value="project">Project</option>
                                </optgroup>
                                <optgroup label="Summative (Exams)">
                                    <option value="midterm">Mid-Term Exam</option>
                                    <option value="endterm">End-Term Exam</option>
                                    <option value="mock">Mock Exam</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
                            <select class="form-select" name="class_id" id="modalClassId" required>
                                <option value="">Select Class</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Subject (Learning Area) <span class="text-danger">*</span></label>
                            <select class="form-select" name="subject_id" id="modalSubjectId" required>
                                <option value="">Select Subject</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Term <span class="text-danger">*</span></label>
                            <select class="form-select" name="term_id" id="modalTermId" required>
                                <option value="">Select Term</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Marks</label>
                            <input type="number" class="form-control" name="max_marks" value="100" min="1" max="1000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Assessment Date</label>
                            <input type="date" class="form-control" name="assessment_date">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Weight (%)</label>
                            <input type="number" class="form-control" name="weight" value="40" min="0" max="100">
                            <small class="text-muted">Formative: 40% | Summative: 60%</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Instructions / Description</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Assessment instructions or notes…"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ex-primary"><i class="bi bi-check2 me-1"></i>Create Assessment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     MODAL: ENTER RESULTS
════════════════════════════════════════════════════════ -->
<div class="modal fade" id="enterResultsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--ex-success);color:#fff">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Enter Assessment Results</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <strong>CBC Grading Scale:</strong>
                    <span class="grade-EE ms-2">EE</span> Exceeds Expectations (80–100%) &nbsp;|&nbsp;
                    <span class="grade-ME">ME</span> Meets Expectations (50–79%) &nbsp;|&nbsp;
                    <span class="grade-AE">AE</span> Approaches Expectations (25–49%) &nbsp;|&nbsp;
                    <span class="grade-BE">BE</span> Below Expectations (0–24%)
                </div>
                <div id="resultsEntryInfo" class="mb-3"></div>
                <div class="table-responsive">
                    <table class="table ex-table" id="resultsEntryTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Admission No</th>
                                <th>Marks <small class="opacity-75">(/ max)</small></th>
                                <th>%</th>
                                <th>Grade</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="resultsEntryTbody">
                            <tr><td colspan="7" class="ex-loading"><div class="spinner-border"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-ex-outline" onclick="assessExamsCtrl.saveDraftResults()">
                    <i class="bi bi-save me-1"></i>Save Draft
                </button>
                <button class="btn btn-ex-primary" onclick="assessExamsCtrl.submitResults()">
                    <i class="bi bi-send me-1"></i>Submit Results
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     MODAL: START EXAM WORKFLOW
════════════════════════════════════════════════════════ -->
<div class="modal fade" id="startExamWorkflowModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--ex-primary);color:#fff">
                <h5 class="modal-title"><i class="bi bi-clipboard2-check me-2"></i>Start Exam Workflow</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="startExamWorkflowForm" onsubmit="assessExamsCtrl.startExamWorkflow(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                        <select class="form-select" name="academic_year_id" id="wfYearId" required>
                            <option value="">Select Year</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Term <span class="text-danger">*</span></label>
                        <select class="form-select" name="term_id" id="wfTermId" required>
                            <option value="">Select Term</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Exam Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="exam_type" required>
                            <option value="">Select Type</option>
                            <option value="midterm">Mid-Term Examination</option>
                            <option value="endterm">End-Term Examination</option>
                            <option value="mock">Mock Examination</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Brief description of this examination cycle…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ex-primary"><i class="bi bi-play-circle me-1"></i>Start Workflow</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:11000">
    <div id="exToast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="exToastBody">Message</div>
            <button class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/assessments_exams.js"></script>
