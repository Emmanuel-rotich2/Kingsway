<?php
declare(strict_types=1);
/* Learning & Results — per-page sidebar (child list + sub-tabs) */
$parentPageTitle  = 'Learning & Results';
$parentActive     = 'results';
$parentPageScript = 'parents/results';
$parentSidebar    = true;
require __DIR__ . '/_head.php';
?>
<div class="container pp-container">
  <div class="row g-4">
    <div class="col-lg-3">
      <aside class="pp-sidebar">
        <div class="pp-sidebar-title">My children</div>
        <div class="pp-child-list" id="ppChildList"></div>
      </aside>
    </div>
    <div class="col-lg-9">
      <div class="pp-card">
        <div class="pp-card-header">
          <h1><i class="bi bi-mortarboard-fill me-2 text-success"></i><span id="ppStudentName">Learning & Results</span></h1>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-success btn-sm" type="button" id="btnResultsCsv"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button class="btn btn-outline-success btn-sm" type="button" data-pp-print><i class="bi bi-printer me-1"></i>Print</button>
          </div>
        </div>
        <div class="pp-card-body">
          <ul class="nav nav-tabs mb-3" id="ppResultsTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-pp-tab="performance" type="button">Performance</button></li>
            <li class="nav-item"><button class="nav-link" data-pp-tab="report-card" type="button">Report card</button></li>
            <li class="nav-item"><button class="nav-link" data-pp-tab="covered" type="button">Covered content</button></li>
            <li class="nav-item"><button class="nav-link" data-pp-tab="competencies" type="button">Competencies</button></li>
            <li class="nav-item"><button class="nav-link" data-pp-tab="charts" type="button">Progress & comparison</button></li>
            <li class="nav-item"><button class="nav-link" data-pp-tab="swot" type="button">SWOT analysis</button></li>
          </ul>
          <div id="ppResultsContent"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<?php require __DIR__ . '/_foot.php'; ?>