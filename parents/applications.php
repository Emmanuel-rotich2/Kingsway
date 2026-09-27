<?php
declare(strict_types=1);
$parentPageTitle  = 'Admission Applications';
$parentActive     = 'applications';
$parentPageScript = 'parents/applications';
require __DIR__ . '/_head.php';
?>
<div class="container pp-container">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
      <h1 class="h4 fw-bold mb-1"><i class="bi bi-clipboard2-check me-2 text-success"></i>Admission applications</h1>
      <p class="text-muted small mb-0">Track every application you have submitted and watch its progress through the admissions journey.</p>
    </div>
    <div class="d-flex gap-2 no-print">
      <button class="btn btn-outline-secondary btn-sm rounded-pill" type="button" id="btnExportApplications"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
      <button class="btn btn-outline-secondary btn-sm rounded-pill" type="button" id="btnPrintApplications"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
  </div>
  <div class="row g-3 mb-4" id="ppAppKpis"></div>
  <div id="ppApplicationsList"></div>
</div>
<?php require __DIR__ . '/_foot.php'; ?>