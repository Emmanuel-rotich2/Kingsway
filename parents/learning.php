<?php
declare(strict_types=1);
/* Homework & Objectives — per-page sidebar (child list) */
$parentPageTitle  = 'Homework & Objectives';
$parentActive     = 'learning';
$parentPageScript = 'parents/learning';
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
          <h1><i class="bi bi-journal-text me-2 text-success"></i><span id="ppStudentName">Homework & Objectives</span></h1>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-success btn-sm" type="button" id="btnLearningCsv"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button class="btn btn-outline-success btn-sm" type="button" id="btnLearningPrint"><i class="bi bi-printer me-1"></i>Print</button>
          </div>
        </div>
        <div class="pp-card-body">
          <ul class="nav nav-tabs mb-3" id="ppLearningTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-pp-tab="assignments" type="button">Assignments</button></li>
            <li class="nav-item"><button class="nav-link" data-pp-tab="objectives" type="button">Objectives & Expectations</button></li>
            <li class="nav-item"><button class="nav-link" data-pp-tab="questions" type="button">Practical Questions</button></li>
          </ul>
          <div id="ppLearningContent"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_foot.php'; ?>