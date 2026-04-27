<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-student_growth">

  <!-- Search / Student selector -->
  <div id="sgSearchView">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
      <div>
        <h3 class="mb-1"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Student Growth & Progress</h3>
        <p class="text-muted mb-0 small">Comprehensive learning progress — formative, summative, competencies, strengths, growth.</p>
      </div>
    </div>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-5">
            <input type="text" id="sgSearchInput" class="form-control form-control-lg"
                   placeholder="Search by name or admission number…"
                   oninput="studentGrowthCtrl.search(this.value)">
          </div>
          <div class="col-md-3">
            <select class="form-control form-control-lg" id="sgClassFilter" onchange="studentGrowthCtrl.filterByClass()">
              <option value="">All Classes</option>
            </select>
          </div>
        </div>
      </div>
    </div>
    <div id="sgSearchResults">
      <div class="text-center text-muted py-5">
        <i class="bi bi-search fs-1 d-block mb-2 opacity-25"></i>
        Start typing to find a student.
      </div>
    </div>
  </div>

  <!-- ── STUDENT PROFILE VIEW ──────────────────────────────────────────────── -->
  <div id="sgProfileView" style="display:none;">

    <!-- Header card -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="row align-items-center g-3">
          <div class="col-auto">
            <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:72px;height:72px;">
              <i class="bi bi-person-fill fs-2 text-success"></i>
            </div>
          </div>
          <div class="col">
            <h4 class="mb-1 fw-bold" id="sgStudentName">—</h4>
            <div class="d-flex flex-wrap gap-3 text-muted small">
              <span><i class="bi bi-mortarboard me-1"></i><span id="sgClass">—</span></span>
              <span><i class="bi bi-hash me-1"></i><span id="sgAdmNo">—</span></span>
              <span><i class="bi bi-calendar me-1"></i>Term <span id="sgTerm">—</span></span>
              <span><i class="bi bi-bar-chart me-1"></i>Year Avg: <strong id="sgYearAvg">—</strong></span>
            </div>
          </div>
          <div class="col-auto d-flex gap-2">
            <span class="badge fs-6 py-2 px-3" id="sgOverallGradeBadge">—</span>
            <span class="badge fs-6 py-2 px-3" id="sgPathwayBadge">—</span>
            <button class="btn btn-outline-secondary btn-sm" onclick="studentGrowthCtrl.backToSearch()">
              <i class="bi bi-arrow-left me-1"></i> Back
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="sgTabs">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sgTabPerformance">
        <i class="bi bi-bar-chart me-1"></i>Performance</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sgTabGrowth">
        <i class="bi bi-graph-up me-1"></i>Growth Trend</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sgTabCompetencies">
        <i class="bi bi-list-check me-1"></i>Competencies</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sgTabAssignments">
        <i class="bi bi-pencil-square me-1"></i>Assessments</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sgTabInsights">
        <i class="bi bi-lightbulb me-1"></i>Strengths &amp; Growth</button></li>
    </ul>

    <div class="tab-content">

      <!-- ── PERFORMANCE TAB ─────────────────────────────────────────────── -->
      <div class="tab-pane fade show active" id="sgTabPerformance">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-transparent fw-semibold d-flex justify-content-between">
            <span>Learning Area Performance — Term <span id="sgPerfTerm">—</span></span>
            <select class="form-select form-select-sm" style="width:auto;" id="sgPerfTermFilter"
                    onchange="studentGrowthCtrl.loadPerformance()">
              <option value="">Current Term</option>
            </select>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Learning Area</th>
                    <th class="text-center">CA Assessments</th>
                    <th class="text-center">CA Avg</th>
                    <th class="text-center">Exam Score</th>
                    <th class="text-center">Overall (40/60)</th>
                    <th class="text-center">CBC Grade</th>
                    <th class="text-center">Progress</th>
                  </tr>
                </thead>
                <tbody id="sgPerfBody">
                  <tr><td colspan="7" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- ── GROWTH TREND TAB ────────────────────────────────────────────── -->
      <div class="tab-pane fade" id="sgTabGrowth">
        <div class="row g-3">
          <div class="col-md-8">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-transparent fw-semibold">Term-over-Term Score Trend</div>
              <div class="card-body">
                <div id="sgGrowthChart" class="text-center py-3 text-muted">Select learning area to plot trend.</div>
                <div class="mt-3">
                  <select id="sgGrowthLASelect" class="form-select form-select-sm" style="max-width:300px;"
                          onchange="studentGrowthCtrl.plotGrowth()">
                    <option value="">— Select learning area —</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-transparent fw-semibold">Year Summary</div>
              <div class="card-body" id="sgYearSummary">
                <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── COMPETENCIES TAB ────────────────────────────────────────────── -->
      <div class="tab-pane fade" id="sgTabCompetencies">
        <div class="row g-3" id="sgCompetenciesGrid">
          <div class="col-12 text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
          </div>
        </div>
      </div>

      <!-- ── ASSESSMENTS TAB ─────────────────────────────────────────────── -->
      <div class="tab-pane fade" id="sgTabAssignments">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Assessment</th><th>Type</th><th>Learning Area</th>
                    <th>Date</th><th class="text-center">Score</th>
                    <th class="text-center">%</th><th class="text-center">Grade</th>
                  </tr>
                </thead>
                <tbody id="sgAssessmentsBody">
                  <tr><td colspan="7" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- ── STRENGTHS & GROWTH TAB ─────────────────────────────────────── -->
      <div class="tab-pane fade" id="sgTabInsights">
        <div class="row g-4">
          <div class="col-md-6">
            <div class="card border-0 shadow-sm border-start border-success border-3">
              <div class="card-header bg-transparent fw-semibold text-success">
                <i class="bi bi-star-fill me-2"></i>Strengths (EE / ME)
              </div>
              <div class="card-body" id="sgStrengthsList">
                <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-success"></div></div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border-0 shadow-sm border-start border-warning border-3">
              <div class="card-header bg-transparent fw-semibold text-warning">
                <i class="bi bi-tools me-2"></i>Areas for Growth (AE / BE)
              </div>
              <div class="card-body" id="sgWeaknessesList">
                <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></div>
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-chat-quote me-2 text-primary"></i>Teacher Comments &amp; Recommendations
              </div>
              <div class="card-body" id="sgCommentsBlock">
                <div class="text-muted small">No comments recorded yet.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /.tab-content -->
  </div><!-- /#sgProfileView -->

</div>

<style>
.sg-grade-bar { height:8px; border-radius:4px; }
.sg-grade-row:hover { background:#f8f9fa; }
</style>

<script src="<?= $appBase ?>/js/pages/student_growth.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => studentGrowthCtrl.init());</script>
