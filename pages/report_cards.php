<?php
/**
 * Report Cards Page — Production UI
 * All logic handled in: js/pages/report_cards.js
 *
 * API Endpoints:
 *   GET /academic/years-list   → academic years
 *   GET /academic/terms-list   → terms (dynamic — replaces hardcoded Term 1/2/3)
 *   GET /academic/classes-list → classes
 *   GET /students/student      → students (double-wrapped: data.data.students[])
 *   POST /academic/reports-start-workflow
 *   POST /academic/reports-compile-data
 *   POST /academic/reports-generate-student-reports
 */
?>
<style>
/* ═══════════════════════════════════════════════
   REPORT CARDS — DESIGN TOKENS & COMPONENTS
═══════════════════════════════════════════════ */
:root {
    --rc-primary:      #1b5e20;
    --rc-primary-mid:  #2e7d32;
    --rc-primary-soft: #c8e6c9;
    --rc-accent:       #f57f17;
    --rc-shadow:       0 2px 10px rgba(0,0,0,0.07);
    --rc-radius:       12px;
}
.rc-hero {
    background: linear-gradient(135deg, var(--rc-primary) 0%, #388e3c 100%);
    color: #fff;
    border-radius: var(--rc-radius);
    padding: 1.6rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 16px rgba(27,94,32,.25);
}
.rc-hero h4 { font-size: 1.3rem; font-weight: 700; margin: 0 0 .2rem; }
.rc-hero small { opacity: .85; }

.rc-kpi {
    background: #fff;
    border-radius: var(--rc-radius);
    border-top: 4px solid var(--rc-primary-mid);
    padding: 1.1rem 1.3rem;
    box-shadow: var(--rc-shadow);
}
.rc-kpi .kv { font-size: 1.9rem; font-weight: 700; color: var(--rc-primary); }
.rc-kpi .kl { font-size: .78rem; color: #666; font-weight: 500; margin-top: .1rem; }
.rc-kpi .ki { font-size: 2rem; color: var(--rc-primary-soft); }

.rc-filter {
    background: #fff;
    border-radius: var(--rc-radius);
    border: 1px solid #e8f5e9;
    padding: 1.1rem 1.4rem;
    box-shadow: var(--rc-shadow);
    margin-bottom: 1.2rem;
}

.rc-card {
    background: #fff;
    border-radius: var(--rc-radius);
    border-left: 4px solid var(--rc-primary);
    box-shadow: var(--rc-shadow);
    overflow: hidden;
}
.rc-card .card-header {
    background: #f1f8e9;
    border-bottom: 1px solid #c8e6c9;
    padding: .85rem 1.2rem;
    font-weight: 600;
    font-size: .93rem;
    color: var(--rc-primary);
}

/* Table */
.rc-table { font-size: .875rem; }
.rc-table thead th {
    background: var(--rc-primary);
    color: #fff;
    font-weight: 600;
    border: none;
    white-space: nowrap;
    padding: .75rem 1rem;
}
.rc-table tbody tr:hover { background: #f1f8e9; }
.rc-table tbody td { vertical-align: middle; padding: .6rem 1rem; }

/* CBC grade badges */
.grade-EE { background:#1b5e20; color:#fff; padding:2px 8px; border-radius:20px; font-size:.75rem; font-weight:700; }
.grade-ME { background:#388e3c; color:#fff; padding:2px 8px; border-radius:20px; font-size:.75rem; font-weight:700; }
.grade-AE { background:#f57c00; color:#fff; padding:2px 8px; border-radius:20px; font-size:.75rem; font-weight:700; }
.grade-BE { background:#b71c1c; color:#fff; padding:2px 8px; border-radius:20px; font-size:.75rem; font-weight:700; }

/* Status pills */
.rc-pill { display:inline-block; padding:3px 12px; border-radius:20px; font-size:.75rem; font-weight:600; text-transform:capitalize; }
.rc-generated  { background:#c8e6c9; color:#1b5e20; }
.rc-pending    { background:#fff3e0; color:#e65100; }
.rc-approved   { background:#e3f2fd; color:#0d47a1; }
.rc-distributed{ background:#f3e5f5; color:#4a148c; }

/* Loading / empty */
.rc-loading { text-align:center; padding:3rem; color:#aaa; }
.rc-loading .spinner-border { width:2.5rem; height:2.5rem; border-color:var(--rc-primary); border-right-color:transparent; }
.rc-empty   { text-align:center; padding:3rem; color:#9e9e9e; font-size:.9rem; }
.rc-empty i { font-size:2.5rem; display:block; margin-bottom:.5rem; opacity:.5; }

.btn-rc { background:var(--rc-primary); color:#fff; border:none; border-radius:8px; }
.btn-rc:hover { background:var(--rc-primary-mid); color:#fff; }
.btn-rc-outline { background:#fff; color:var(--rc-primary); border:2px solid var(--rc-primary); border-radius:8px; }
.btn-rc-outline:hover { background:var(--rc-primary); color:#fff; }
</style>

<!-- ═══════════════════════════════════════════════
     HERO HEADER
══════════════════════════════════════════════════ -->
<div class="rc-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-file-earmark-text me-2"></i>Report Cards</h4>
        <small>Generate, review, and distribute student CBC report cards</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-light btn-sm" id="generateAllBtn">
            <i class="bi bi-file-earmark-plus me-1"></i>Generate All
        </button>
        <button class="btn btn-light btn-sm" id="downloadAllBtn">
            <i class="bi bi-download me-1"></i>Download All
        </button>
        <button class="btn btn-light btn-sm" id="printAllBtn">
            <i class="bi bi-printer me-1"></i>Print All
        </button>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#startReportWorkflowModal">
            <i class="bi bi-diagram-3 me-1"></i>Start Workflow
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     KPI STRIP
══════════════════════════════════════════════════ -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="rc-kpi d-flex justify-content-between align-items-center">
            <div><div class="kv" id="totalStudents">—</div><div class="kl">Total Students</div></div>
            <i class="bi bi-people ki"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rc-kpi d-flex justify-content-between align-items-center" style="border-top-color:#2e7d32">
            <div><div class="kv" id="cardsGenerated" style="color:#2e7d32">—</div><div class="kl">Cards Generated</div></div>
            <i class="bi bi-check2-circle ki" style="color:#c8e6c9"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rc-kpi d-flex justify-content-between align-items-center" style="border-top-color:#e65100">
            <div><div class="kv" id="cardsPending" style="color:#e65100">—</div><div class="kl">Pending</div></div>
            <i class="bi bi-hourglass-split ki" style="color:#ffe0b2"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="rc-kpi d-flex justify-content-between align-items-center" style="border-top-color:#0d47a1">
            <div><div class="kv" id="cardsDownloaded" style="color:#0d47a1">—</div><div class="kl">Downloaded</div></div>
            <i class="bi bi-cloud-download ki" style="color:#bbdefb"></i>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     FILTER BAR — dropdowns loaded dynamically from API
══════════════════════════════════════════════════ -->
<div class="rc-filter d-flex gap-3 flex-wrap align-items-end">
    <div>
        <label class="form-label small fw-semibold mb-1">Academic Year</label>
        <select class="form-select form-select-sm" id="yearFilter" style="width:175px">
            <option value="">All Years</option>
        </select>
    </div>
    <div>
        <label class="form-label small fw-semibold mb-1">Term</label>
        <select class="form-select form-select-sm" id="termFilter" style="width:165px">
            <option value="">All Terms</option>
            <!-- Loaded dynamically from /academic/terms-list -->
        </select>
    </div>
    <div>
        <label class="form-label small fw-semibold mb-1">Class</label>
        <select class="form-select form-select-sm" id="classFilter" style="width:175px">
            <option value="">All Classes</option>
            <!-- Loaded dynamically from /academic/classes-list -->
        </select>
    </div>
    <div class="flex-grow-1" style="min-width:200px;max-width:340px">
        <label class="form-label small fw-semibold mb-1">Search Student</label>
        <input type="text" class="form-control form-control-sm" id="searchBox"
               placeholder="Name or admission number…">
    </div>
    <div>
        <button class="btn btn-rc btn-sm" id="loadBtn">
            <i class="bi bi-search me-1"></i>Search
        </button>
        <button class="btn btn-rc-outline btn-sm ms-1" id="clearBtn">
            <i class="bi bi-x-circle me-1"></i>Clear
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     DATA TABLE
══════════════════════════════════════════════════ -->
<div class="rc-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i>Students &amp; Report Card Status</span>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-rc-outline" onclick="reportCardsCtrl.exportCSV()">
                <i class="bi bi-filetype-csv me-1"></i>Export CSV
            </button>
            <small class="text-muted align-self-center" id="tableMeta">—</small>
        </div>
    </div>
    <div class="p-0">
        <div class="table-responsive">
            <table class="table rc-table mb-0" id="reportCardsTable">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" id="selectAll"></th>
                        <th>Student Name</th>
                        <th>Admission No</th>
                        <th>Class</th>
                        <th>Stream</th>
                        <th>CBC Grade</th>
                        <th>Overall %</th>
                        <th>Rank</th>
                        <th>Card Status</th>
                        <th>Term</th>
                        <th style="width:130px">Actions</th>
                    </tr>
                </thead>
                <tbody id="reportCardsTbody">
                    <tr><td colspan="11" class="rc-loading"><div class="spinner-border"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="p-3 border-top d-flex justify-content-between flex-wrap gap-2">
        <small class="text-muted" id="paginationMeta">—</small>
        <nav><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     MODAL: START REPORT WORKFLOW
══════════════════════════════════════════════════ -->
<div class="modal fade" id="startReportWorkflowModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--rc-primary);color:#fff">
                <h5 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>Start Report Card Workflow</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="reportWorkflowForm" onsubmit="reportCardsCtrl.startWorkflow(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                        <select class="form-select" name="academic_year_id" id="wfRcYear" required>
                            <option value="">Select Year</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Term <span class="text-danger">*</span></label>
                        <select class="form-select" name="term_id" id="wfRcTerm" required>
                            <option value="">Select Term</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Affected Classes</label>
                        <select class="form-select" name="class_id" id="wfRcClass">
                            <option value="">All Classes</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-rc"><i class="bi bi-play-circle me-1"></i>Start Workflow</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:11000">
    <div id="rcToast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="rcToastBody">Message</div>
            <button class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/report_cards.js"></script>
