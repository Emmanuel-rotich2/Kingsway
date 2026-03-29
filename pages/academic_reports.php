<?php
/**
 * Academic Reports Page — Production UI
 * JS: js/pages/academic_reports.js
 *
 * API:
 *   GET /academic/years-list   → years
 *   GET /academic/terms-list   → terms
 *   GET /academic/classes-list → classes
 *   GET /academic/years        → learning areas (49)
 */
?>
<style>
    :root {
        --ar-primary: #1b5e20;
        --ar-mid: #2e7d32;
        --ar-soft: #c8e6c9;
        --ar-shadow: 0 2px 10px rgba(0, 0, 0, .07);
        --ar-radius: 12px;
    }

    .ar-hero {
        background: linear-gradient(135deg, var(--ar-primary) 0%, #388e3c 100%);
        color: #fff;
        border-radius: var(--ar-radius);
        padding: 1.5rem 2rem;
        margin-bottom: 1.4rem;
        box-shadow: 0 4px 16px rgba(27, 94, 32, .22);
    }

    .ar-hero h4 {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 .2rem;
    }

    .ar-filter {
        background: #fff;
        border-radius: var(--ar-radius);
        border: 1px solid #e8f5e9;
        padding: 1rem 1.4rem;
        box-shadow: var(--ar-shadow);
        margin-bottom: 1.2rem;
    }

    .ar-card {
        background: #fff;
        border-radius: var(--ar-radius);
        border-left: 4px solid var(--ar-mid);
        box-shadow: var(--ar-shadow);
        overflow: hidden;
    }

    .ar-card .card-header {
        background: #f1f8e9;
        border-bottom: 1px solid var(--ar-soft);
        padding: .8rem 1.2rem;
        font-weight: 600;
        font-size: .9rem;
        color: var(--ar-primary);
    }

    .ar-kpi {
        background: #fff;
        border-radius: var(--ar-radius);
        border-top: 4px solid var(--ar-mid);
        padding: 1rem 1.2rem;
        box-shadow: var(--ar-shadow);
    }

    .ar-kpi .kv {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--ar-primary);
    }

    .ar-kpi .kl {
        font-size: .78rem;
        color: #666;
    }

    .ar-table {
        font-size: .875rem;
    }

    .ar-table thead th {
        background: var(--ar-primary);
        color: #fff;
        font-weight: 600;
        border: none;
        padding: .7rem 1rem;
    }

    .ar-table tbody tr:hover {
        background: #f1f8e9;
    }

    .ar-table tbody td {
        vertical-align: middle;
        padding: .6rem 1rem;
    }

    .grade-EE {
        background: #1b5e20;
        color: #fff;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .grade-ME {
        background: #388e3c;
        color: #fff;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .grade-AE {
        background: #f57c00;
        color: #fff;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .grade-BE {
        background: #b71c1c;
        color: #fff;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
    }

    .ar-empty {
        text-align: center;
        padding: 3rem;
        color: #9e9e9e;
    }

    .ar-empty i {
        font-size: 2.5rem;
        display: block;
        margin-bottom: .5rem;
        opacity: .4;
    }

    .ar-loading {
        text-align: center;
        padding: 3rem;
    }

    .ar-loading .spinner-border {
        width: 2.5rem;
        height: 2.5rem;
        border-color: var(--ar-primary);
        border-right-color: transparent;
    }

    .btn-ar {
        background: var(--ar-primary);
        color: #fff;
        border: none;
        border-radius: 8px;
    }

    .btn-ar:hover {
        background: var(--ar-mid);
        color: #fff;
    }

    .btn-ar-outline {
        background: #fff;
        color: var(--ar-primary);
        border: 2px solid var(--ar-primary);
        border-radius: 8px;
    }

    .btn-ar-outline:hover {
        background: var(--ar-primary);
        color: #fff;
    }
</style>

<!-- HERO -->
<div class="ar-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-graph-up-arrow me-2"></i>Academic Reports &amp; Analytics</h4>
        <small>Class performance, subject analysis, CBC grade trends and student progress</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-light btn-sm" id="generateReport" onclick="academicReportsCtrl.generateReport()">
            <i class="bi bi-file-earmark-pdf me-1"></i>Generate Report
        </button>
        <button class="btn btn-light btn-sm" onclick="academicReportsCtrl.exportReport()">
            <i class="bi bi-download me-1"></i>Export
        </button>
    </div>
</div>

<!-- FILTER BAR -->
<div class="ar-filter">
    <div class="row g-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Year</label>
            <select class="form-select form-select-sm" id="selectYear">
                <option value="">All Years</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Term</label>
            <select class="form-select form-select-sm" id="selectTerm">
                <option value="">All Terms</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Report Type</label>
            <select class="form-select form-select-sm" id="reportType">
                <option value="class">Class Performance</option>
                <option value="subject">Subject Analysis</option>
                <option value="student">Student Progress</option>
                <option value="comparison">Term Comparison</option>
                <option value="grade_distribution">Grade Distribution</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Class</label>
            <select class="form-select form-select-sm" id="selectClass">
                <option value="">All Classes</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-ar btn-sm w-100" onclick="academicReportsCtrl.generateReport()">
                <i class="bi bi-bar-chart me-1"></i>Analyse
            </button>
        </div>
    </div>
</div>

<!-- KPI ROW -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="ar-kpi"><div class="kv" id="arKpiClasses">—</div><div class="kl">Active Classes</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ar-kpi" style="border-top-color:#f57c00"><div class="kv" id="arKpiStudents" style="color:#f57c00">—</div><div class="kl">Total Students</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ar-kpi" style="border-top-color:#0d47a1"><div class="kv" id="arKpiAvg" style="color:#0d47a1">—</div><div class="kl">Overall Average</div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ar-kpi" style="border-top-color:#4a148c"><div class="kv" id="arKpiPassRate" style="color:#4a148c">—</div><div class="kl">Pass Rate (ME+EE)</div></div>
    </div>
</div>

<!-- CONTENT TABS -->
<ul class="nav nav-tabs mb-0" style="border-bottom:none" id="arTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#arOverview"><i class="bi bi-grid me-1"></i>Overview</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#arDetailed"><i class="bi bi-table me-1"></i>Detailed Analysis</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#arTrends"><i class="bi bi-graph-up me-1"></i>Trends</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#arGrades"><i class="bi bi-award me-1"></i>CBC Grades</button></li>
</ul>

<div class="tab-content">
    <!-- Overview -->
    <div class="tab-pane fade show active" id="arOverview">
        <div class="row g-3 mt-0">
            <div class="col-md-8">
                <div class="ar-card h-100">
                    <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Class Performance Overview</div>
                    <div class="p-3" style="height:320px;position:relative"><canvas id="performanceChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="ar-card h-100">
                    <div class="card-header"><i class="bi bi-info-circle me-2"></i>Quick Stats</div>
                    <div class="p-3">
                        <ul class="list-group list-group-flush" id="quickStats">
                            <li class="list-group-item d-flex justify-content-between align-items-center">Total Classes<strong id="qs_classes">—</strong></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">Total Students<strong id="qs_students">—</strong></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">Average Score<strong id="avgScore">—</strong></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">Pass Rate<strong id="passRate">—</strong></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">Top Performers<strong id="topPerformers">—</strong></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">Learning Areas<strong id="qs_subjects">—</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed -->
    <div class="tab-pane fade" id="arDetailed">
        <div class="ar-card mt-2">
            <div class="card-header"><i class="bi bi-table me-2"></i>Class-Level Performance Detail</div>
            <div class="p-0">
                <div class="table-responsive">
                    <table class="table ar-table mb-0" id="detailedTable">
                        <thead>
                            <tr><th>Class</th><th>Level</th><th>Students</th><th>Streams</th><th>Avg %</th><th>EE</th><th>ME</th><th>AE</th><th>BE</th><th>Pass Rate</th></tr>
                        </thead>
                        <tbody id="detailedTbody">
                            <tr><td colspan="10" class="ar-empty"><i class="bi bi-table"></i>Generate a report to see detailed analysis</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Trends -->
    <div class="tab-pane fade" id="arTrends">
        <div class="ar-card mt-2">
            <div class="card-header"><i class="bi bi-graph-up me-2"></i>Performance Trends Over Terms</div>
            <div class="p-3" style="height:350px;position:relative"><canvas id="trendsChart"></canvas></div>
        </div>
    </div>

    <!-- CBC Grades -->
    <div class="tab-pane fade" id="arGrades">
        <div class="row g-3 mt-0">
            <div class="col-md-5">
                <div class="ar-card h-100">
                    <div class="card-header"><i class="bi bi-pie-chart me-2"></i>CBC Grade Distribution</div>
                    <div class="p-3" style="height:300px;position:relative"><canvas id="gradeDistChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="ar-card h-100">
                    <div class="card-header"><i class="bi bi-info-circle me-2"></i>CBC Scale Reference</div>
                    <div class="p-3">
                        <table class="table table-sm">
                            <thead><tr><th>Grade</th><th>Description</th><th>Score Range</th><th>Meaning</th></tr></thead>
                            <tbody>
                                <tr><td><span class="grade-EE">EE</span></td><td>Exceeds Expectations</td><td>80 – 100%</td><td>Outstanding mastery of learning outcomes</td></tr>
                                <tr><td><span class="grade-ME">ME</span></td><td>Meets Expectations</td><td>50 – 79%</td><td>Adequate mastery, on track</td></tr>
                                <tr><td><span class="grade-AE">AE</span></td><td>Approaches Expectations</td><td>25 – 49%</td><td>Some understanding, needs support</td></tr>
                                <tr><td><span class="grade-BE">BE</span></td><td>Below Expectations</td><td>0 – 24%</td><td>Significant intervention required</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:11000">
    <div id="arToast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex"><div class="toast-body" id="arToastBody">Message</div>
        <button class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>
    </div>
</div>

<script src="/Kingsway/js/pages/academic_reports.js"></script>
