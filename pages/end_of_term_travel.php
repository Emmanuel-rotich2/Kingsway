<?php
/* End of Term Travel — leave requests for end-of-term travel. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-airplane text-success me-2"></i>End of Term Travel</h4>
      <p class="text-muted small mb-0 mt-1">Travel and end-of-term leave requests for boarders.</p>
    </div>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="endOfTermTravelController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Adm No</th><th>Student</th><th>Type</th><th>Start</th><th>Return</th><th>Reason</th><th>Status</th>
        </tr></thead>
        <tbody id="etBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/end_of_term_travel.js'); ?>
