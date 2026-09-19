<?php
declare(strict_types=1);
/* Fees & Payments — per-page sidebar (child list + sub-tabs), M-Pesa */
$parentPageTitle  = 'Fees & Payments';
$parentActive     = 'fees';
$parentPageScript = 'parents/fees';
$parentSidebar    = true;
require __DIR__ . '/_head.php';
?>
<div class="container pp-container">
  <div class="row g-4">
    <div class="col-lg-3">
      <aside class="pp-sidebar">
        <div class="pp-sidebar-title">My children</div>
        <div class="pp-child-list" id="ppChildList"></div>
        <div class="pp-sidebar-actions">
          <button class="btn btn-success btn-sm w-100 rounded-pill" type="button" id="ppPayNow"><i class="bi bi-phone me-1"></i>Make a payment</button>
        </div>
      </aside>
    </div>
    <div class="col-lg-9">
      <div class="pp-card mb-4">
        <div class="pp-card-header">
          <h1><i class="bi bi-receipt-cutoff me-2 text-success"></i><span id="ppStudentName">Fees & Payments</span></h1>
          <div id="ppBalanceBadge"></div>
        </div>
      </div>
      <div class="row g-3 mb-4" id="ppFeeSummaryCards"></div>
      <div class="pp-card">
        <div class="pp-card-body">
          <ul class="nav nav-tabs mb-3" id="ppFeesTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-pp-tab="fees" type="button">Fee history</button></li>
            <li class="nav-item"><button class="nav-link" data-pp-tab="payments" type="button">Payments</button></li>
            <li class="nav-item"><button class="nav-link" data-pp-tab="statement" type="button">Statement</button></li>
          </ul>
          <div class="d-flex gap-2 mb-3 justify-content-end">
            <button class="btn btn-outline-success btn-sm" type="button" data-pp-csv><i class="bi bi-filetype-csv me-1"></i>CSV</button>
            <button class="btn btn-outline-success btn-sm" type="button" data-pp-print><i class="bi bi-printer me-1"></i>Print</button>
          </div>
          <div id="ppFeesContent"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_foot.php'; ?>