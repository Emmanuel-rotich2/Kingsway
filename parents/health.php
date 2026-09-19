<?php
declare(strict_types=1);
/* Health & Welfare — per-page sidebar (child list) */
$parentPageTitle  = 'Health & Welfare';
$parentActive     = 'health';
$parentPageScript = 'parents/health';
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
          <h1><i class="bi bi-heart-pulse-fill me-2 text-success"></i><span id="ppStudentName">Health & Welfare</span></h1>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-success btn-sm" type="button" id="btnHealthPrint"><i class="bi bi-printer me-1"></i>Print</button>
          </div>
        </div>
        <div class="pp-card-body" id="ppHealthContent"></div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_foot.php'; ?>