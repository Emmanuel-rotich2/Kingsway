<?php
/* Pending Exeat Requests — leave requests awaiting approval. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-hourglass-split text-warning me-2"></i>Pending Exeat Requests</h4>
      <p class="text-muted small mb-0 mt-1">Leave requests awaiting approval.</p>
    </div>
    <button class="btn btn-outline-warning btn-sm" data-kw-onclick="pendingExeatRequestsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Adm No</th><th>Student</th><th>Type</th><th>Start</th><th>Return</th><th>Reason</th><th>Dormitory</th><th></th>
        </tr></thead>
        <tbody id="pexBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/pending_exeat_requests.js'); ?>
