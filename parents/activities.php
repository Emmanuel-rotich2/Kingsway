<?php
declare(strict_types=1);
/* Clubs & Activities — per-page sidebar (child list) */
$parentPageTitle  = 'Clubs & Activities';
$parentActive     = 'activities';
$parentPageScript = 'parents/activities';
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
          <h1><i class="bi bi-trophy-fill me-2 text-success"></i><span id="ppStudentName">Clubs & Activities</span></h1>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-success btn-sm" type="button" id="btnActivitiesCsv"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button class="btn btn-outline-success btn-sm" type="button" id="btnActivitiesPrint"><i class="bi bi-printer me-1"></i>Print</button>
          </div>
        </div>
        <div class="pp-card-body" id="ppActivitiesContent"></div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_foot.php'; ?>