<?php
/* Budget vs Actuals — budget allocation, spending and utilization. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-speedometer text-success me-2"></i>Budget vs Actuals</h4>
      <p class="text-muted small mb-0 mt-1">Compare budgeted amounts against allocated, committed and actual spend.</p>
    </div>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="budgetActualsController.load()"><i class="bi bi-arrow-clockwise"></i></button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Budget</th><th>Year</th><th>Term</th><th>Budget (KES)</th><th>Allocated</th><th>Committed</th><th>Actual Spend</th><th style="width:180px">Utilization</th><th>Status</th>
        </tr></thead>
        <tbody id="baBody"><tr><td colspan="9" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/budget_actuals.js'); ?>
