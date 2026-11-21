<?php
/* Payment Reports — collection statistics and trends. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-graph-up text-success me-2"></i>Payment Reports</h4>
      <p class="text-muted small mb-0 mt-1">Collection statistics, trends and revenue sources.</p>
    </div>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="paymentReportsController.load()"><i class="bi bi-arrow-clockwise"></i></button>
  </div>

  <div class="row g-3 mb-4" id="prStatsCards"></div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 p-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart me-1"></i>Collection Trends</h6>
        <div id="prTrends" class="text-muted small">Loading…</div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 p-3">
        <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart me-1"></i>Revenue by Source</h6>
        <div id="prSources" class="text-muted small">Loading…</div>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/payment_reports.js'); ?>
