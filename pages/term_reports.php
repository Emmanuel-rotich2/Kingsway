<?php
/**
 * Term Reports Page — Production UI
 * JS: js/pages/term_reports.js
 *
 * API:
 *   GET /academic/years-list   → academic years
 *   GET /academic/terms-list   → terms
 *   GET /academic/classes-list → classes
 *   GET /students/student      → students (data.data.students[], double-wrapped)
 *   POST /academic/reports-generate-student-reports
 */
?>
<style>
    :root {
        --tr-primary: #1b5e20;
        --tr-mid: #2e7d32;
        --tr-soft: #c8e6c9;
        --tr-shadow: 0 2px 10px rgba(0, 0, 0, .07);
        --tr-radius: 12px;
    }

    .tr-hero {
        background: linear-gradient(135deg, var(--tr-primary) 0%, #388e3c 100%);
        color: #fff;
        border-radius: var(--tr-radius);
        padding: 1.5rem 2rem;
        margin-bottom: 1.4rem;
        box-shadow: 0 4px 16px rgba(27, 94, 32, .22);
    }

    .tr-hero h4 {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 .2rem;
    }

    .tr-kpi {
        background: #fff;
        border-radius: var(--tr-radius);
        border-top: 4px solid var(--tr-mid);
        padding: 1rem 1.2rem;
        box-shadow: var(--tr-shadow);
    }

    .tr-kpi .kv {
        font-size: 1.9rem;
        font-weight: 700;
        color: var(--tr-primary);
    }

    .tr-kpi .kl {
        font-size: .78rem;
        color: #666;
    }

    .tr-filter {
        background: #fff;
        border-radius: var(--tr-radius);
        border: 1px solid #e8f5e9;
        padding: 1rem 1.4rem;
        box-shadow: var(--tr-shadow);
        margin-bottom: 1.2rem;
    }

    .tr-card {
        background: #fff;
        border-radius: var(--tr-radius);
        border-left: 4px solid var(--tr-mid);
        box-shadow: var(--tr-shadow);
        overflow: hidden;
    }

    .tr-card .card-header {
        background: #f1f8e9;
        border-bottom: 1px solid var(--tr-soft);
        padding: .8rem 1.2rem;
        font-weight: 600;
        font-size: .9rem;
        color: var(--tr-primary);
    }

    .tr-table {
        font-size: .875rem;
    }

    .tr-table thead th {
        background: var(--tr-primary);
        color: #fff;
        font-weight: 600;
        border: none;
        padding: .75rem 1rem;
        white-space: nowrap;
    }

    .tr-table tbody tr:hover {
        background: #f1f8e9;
    }

    .tr-table tbody td {
        vertical-align: middle;
        padding: .6rem 1rem;
    }

    .grade-EE {
        background: #1b5e20;
        color: #fff;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .grade-ME {
        background: #388e3c;
        color: #fff;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .grade-AE {
        background: #f57c00;
        color: #fff;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .grade-BE {
        background: #b71c1c;
        color: #fff;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .tr-status {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 600;
    }

    .tr-status.generated {
        background: #c8e6c9;
        color: #1b5e20;
    }

    .tr-status.pending {
        background: #fff3e0;
        color: #e65100;
    }

    .tr-status.printed {
        background: #e3f2fd;
        color: #0d47a1;
    }

    .tr-empty {
        text-align: center;
        padding: 3rem;
        color: #9e9e9e;
    }

    .tr-empty i {
        font-size: 2.5rem;
        display: block;
        margin-bottom: .5rem;
        opacity: .4;
    }

    .tr-loading {
        text-align: center;
        padding: 3rem;
    }

    .tr-loading .spinner-border {
        width: 2.5rem;
        height: 2.5rem;
        border-color: var(--tr-primary);
        border-right-color: transparent;
    }

    .btn-tr {
        background: var(--tr-primary);
        color: #fff;
        border: none;
        border-radius: 8px;
    }

    .btn-tr:hover {
        background: var(--tr-mid);
        color: #fff;
    }

    .btn-tr-outline {
        background: #fff;
        color: var(--tr-primary);
        border: 2px solid var(--tr-primary);
        border-radius: 8px;
    }

    .btn-tr-outline:hover {
        background: var(--tr-primary);
        color: #fff;
    }
</style>

<!-- HERO -->
<div class="tr-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-file-earmark-ruled me-2"></i>Term Reports</h4>
        <small>Generate, print, and distribute CBC end-of-term report cards in bulk</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-light btn-sm" id="generateReports" onclick="termReportsCtrl.generateReports()">
            <i class="bi bi-magic me-1"></i>Generate Reports
        </button>
        <button class="btn btn-light btn-sm" id="bulkPrint" onclick="termReportsCtrl.bulkPrint()">
            <i class="bi bi-printer me-1"></i>Bulk Print
        </button>
        <button class="btn btn-light btn-sm" onclick="termReportsCtrl.exportCSV()">
            <i class="bi bi-download me-1"></i>Export
        </button>
    </div>
</div>

<!-- KPI STRIP -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="tr-kpi d-flex justify-content-between">
            <div>
                <div class="kv" id="totalStudents">—</div>
                <div class="kl">Total Students</div>
            </div>
            <i class="bi bi-people" style="font-size:2rem;color:#c8e6c9"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="tr-kpi d-flex justify-content-between" style="border-top-color:#2e7d32">
            <div>
                <div class="kv" id="reportsGenerated" style="color:#2e7d32">—</div>
                <div class="kl">Reports Generated</div>
            </div>
            <i class="bi bi-check2-circle" style="font-size:2rem;color:#c8e6c9"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="tr-kpi d-flex justify-content-between" style="border-top-color:#0d47a1">
            <div>
                <div class="kv" id="reportsPrinted" style="color:#0d47a1">—</div>
                <div class="kl">Printed</div>
            </div>
            <i class="bi bi-printer" style="font-size:2rem;color:#bbdefb"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="tr-kpi d-flex justify-content-between" style="border-top-color:#e65100">
            <div>
                <div class="kv" id="pendingRemarks" style="color:#e65100">—</div>
                <div class="kl">Pending Remarks</div>
            </div>
            <i class="bi bi-pencil-square" style="font-size:2rem;color:#ffe0b2"></i>
        </div>
    </div>
</div>

<!-- FILTER BAR -->
<div class="tr-filter d-flex gap-3 flex-wrap align-items-end">
    <div>
        <label class="form-label small fw-semibold mb-1">Academic Year</label>
        <select class="form-select form-select-sm" id="academicYear" style="width:175px">
            <option value="">Select Year</option>
        </select>
    </div>
    <div>
        <label class="form-label small fw-semibold mb-1">Term</label>
        <select class="form-select form-select-sm" id="term" style="width:165px">
            <option value="">Select Term</option>
        </select>
    </div>
    <div>
        <label class="form-label small fw-semibold mb-1">Class</label>
        <select class="form-select form-select-sm" id="classFilter" style="width:175px">
            <option value="">All Classes</option>
        </select>
    </div>
    <div class="ms-auto">
        <button class="btn btn-tr btn-sm" id="loadReports" onclick="termReportsCtrl.loadReports()">
            <i class="bi bi-search me-1"></i>Load Reports
        </button>
    </div>
</div>

<!-- TABLE -->
<div class="tr-card mt-0">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i>Term Report Card Status</span>
        <small class="text-muted" id="trMeta">—</small>
    </div>
    <div class="p-0">
        <div class="table-responsive">
            <table class="table tr-table mb-0" id="reportsTable">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" id="selectAll"></th>
                        <th>Student</th>
                        <th>Admission No</th>
                        <th>Class</th>
                        <th>Stream</th>
                        <th>CBC Grade</th>
                        <th>Overall %</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody id="reportsTbody">
                    <tr><td colspan="10" class="tr-empty">
                        <i class="bi bi-file-earmark-ruled"></i>
                        Use the filters above and click "Load Reports"
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="p-3 border-top d-flex justify-content-between flex-wrap">
        <small class="text-muted" id="trPgMeta">—</small>
        <nav><ul class="pagination pagination-sm mb-0" id="trPagination"></ul></nav>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:11000">
    <div id="trToast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="trToastBody">Message</div>
            <button class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/term_reports.js"></script>
