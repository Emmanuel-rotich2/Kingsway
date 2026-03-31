<?php
/**
 * Performance Reports Page — Production UI
 * JS: js/pages/performance_reports.js
 *
 * API:
 *   GET /academic/terms-list   → terms
 *   GET /academic/classes-list → classes
 *   GET /academic/years        → learning areas (subjects)
 */
?>

<style>
:root { --pr-primary:#0d47a1; --pr-mid:#1565c0; --pr-soft:#bbdefb; --pr-shadow:0 2px 10px rgba(0,0,0,.07); --pr-radius:12px; }
.pr-hero { background:linear-gradient(135deg,var(--pr-primary) 0%,#1976d2 100%); color:#fff; border-radius:var(--pr-radius); padding:1.5rem 2rem; margin-bottom:1.4rem; box-shadow:0 4px 16px rgba(13,71,161,.22); }
.pr-hero h4 { font-size:1.25rem; font-weight:700; margin:0 0 .2rem; }
.pr-filter { background:#fff; border-radius:var(--pr-radius); border:1px solid #e3f2fd; padding:1rem 1.4rem; box-shadow:var(--pr-shadow); margin-bottom:1.2rem; }
.pr-card { background:#fff; border-radius:var(--pr-radius); border-left:4px solid var(--pr-mid); box-shadow:var(--pr-shadow); overflow:hidden; }
.pr-card .card-header { background:#e3f2fd; border-bottom:1px solid var(--pr-soft); padding:.8rem 1.2rem; font-weight:600; font-size:.9rem; color:var(--pr-primary); }
.pr-kpi { background:#fff; border-radius:var(--pr-radius); border-top:4px solid var(--pr-mid); padding:1rem 1.2rem; box-shadow:var(--pr-shadow); }
.pr-kpi .kv { font-size:1.8rem; font-weight:700; color:var(--pr-primary); }
.pr-kpi .kl { font-size:.78rem; color:#666; }
.pr-table { font-size:.875rem; }
.pr-table thead th { background:var(--pr-primary); color:#fff; font-weight:600; border:none; padding:.7rem 1rem; }
.pr-table tbody tr:hover { background:#e3f2fd; }
.pr-table tbody td { vertical-align:middle; padding:.6rem 1rem; }
.grade-EE { background:#1b5e20; color:#fff; padding:2px 8px; border-radius:20px; font-size:.75rem; font-weight:700; }
.grade-ME { background:#388e3c; color:#fff; padding:2px 8px; border-radius:20px; font-size:.75rem; font-weight:700; }
.grade-AE { background:#f57c00; color:#fff; padding:2px 8px; border-radius:20px; font-size:.75rem; font-weight:700; }
.grade-BE { background:#b71c1c; color:#fff; padding:2px 8px; border-radius:20px; font-size:.75rem; font-weight:700; }
.btn-pr { background:var(--pr-primary); color:#fff; border:none; border-radius:8px; }
.btn-pr:hover { background:var(--pr-mid); color:#fff; }
.pr-empty { text-align:center; padding:3rem; color:#9e9e9e; }
.pr-empty i { font-size:2.5rem; display:block; margin-bottom:.5rem; opacity:.4; }
.perf-bar-wrap { display:flex; align-items:center; gap:8px; }
.perf-bar { height:8px; border-radius:4px; background:#bbdefb; overflow:hidden; flex:1; }
.perf-bar-fill { height:100%; border-radius:4px; background:var(--pr-mid); transition:width .4s ease; }
</style>

<!-- HERO -->
<div class="pr-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-activity me-2"></i>Performance Reports</h4>
        <small>Subject analysis, student rankings, CBC grade distributions and performance trends</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-light btn-sm" onclick="performanceReportsCtrl.exportReport()">
            <i class="bi bi-file-excel me-1"></i>Export
        </button>
        <button class="btn btn-light btn-sm" onclick="performanceReportsCtrl.printReport()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<!-- FILTER BAR -->
<div class="pr-filter">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Report Type</label>
            <select class="form-select form-select-sm" id="reportType">
                <option value="class_performance">Class Performance Summary</option>
                <option value="subject_analysis">Subject Analysis</option>
                <option value="top_performers">Top Performers</option>
                <option value="grade_distribution">Grade Distribution</option>
                <option value="improvement_tracking">Improvement Tracking</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Term</label>
            <select class="form-select form-select-sm" id="examTerm">
                <option value="">All Terms</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Class</label>
            <select class="form-select form-select-sm" id="classFilter">
                <option value="">All Classes</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Subject</label>
            <select class="form-select form-select-sm" id="subjectFilter">
                <option value="">All Subjects</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-pr btn-sm w-100" id="generateBtn" onclick="performanceReportsCtrl.generateReport()">
                <i class="bi bi-bar-chart me-1"></i>Generate
            </button>
        </div>
    </div>
</div>

<!-- KPI ROW -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="pr-kpi"><div class="kv" id="classAverage">—</div><div class="kl">Class Average</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pr-kpi" style="border-top-color:#1b5e20"><div class="kv" id="passRate" style="color:#1b5e20">—</div><div class="kl">Pass Rate (ME+EE)</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pr-kpi" style="border-top-color:#f57c00"><div class="kv" id="topScore" style="color:#f57c00">—</div><div class="kl">Top Score</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pr-kpi" style="border-top-color:#4a148c"><div class="kv" id="studentsCount" style="color:#4a148c">—</div><div class="kl">Students Analysed</div></div>
    </div>
</div>

<!-- CHARTS ROW -->
<div class="row g-3 mb-3" id="chartsRow" style="display:none!important">
    <div class="col-md-8">
        <div class="pr-card">
            <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Subject Performance Comparison</div>
            <div class="p-3" style="height:300px;position:relative"><canvas id="subjectPerformanceChart"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="pr-card">
            <div class="card-header"><i class="bi bi-pie-chart me-2"></i>CBC Grade Distribution</div>
            <div class="p-3" style="height:300px;position:relative"><canvas id="gradeDistributionChart"></canvas></div>
        </div>
    </div>
</div>

<!-- PERFORMANCE TABLE -->
<div class="pr-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i><span id="tableTitle">Performance Report</span></span>
        <small class="text-muted" id="prMeta"></small>
    </div>
    <div class="p-0">
        <div class="table-responsive">
            <table class="table pr-table mb-0" id="performanceTable">
                <thead>
                    <tr id="tableHeaders"></tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="10" class="pr-empty"><i class="bi bi-graph-up"></i>Select filters and click Generate to view performance data</td></tr>
                </tbody>
                <tfoot class="fw-bold" id="tableFooter"></tfoot>
            </table>
        </div>
    </div>
</div>

<!-- SUBJECT ANALYSIS -->
<div class="pr-card" id="subjectAnalysisCard" style="display:none">
    <div class="card-header"><i class="bi bi-book me-2"></i>Subject-wise Strengths &amp; Weaknesses</div>
    <div class="row g-0 p-2">
        <div class="col-md-6 p-2" style="height:300px;position:relative"><canvas id="strengthsWeaknessChart"></canvas></div>
        <div class="col-md-6 p-2">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Subject</th><th>Mean %</th><th>Highest</th><th>Lowest</th><th>Grade</th></tr></thead>
                    <tbody id="subjectStatsBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:11000">
    <div id="prToast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex"><div class="toast-body" id="prToastBody">Message</div>
        <button class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/performance_reports.js"></script>
