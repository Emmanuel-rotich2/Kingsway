<?php
/* Participation Reports — participation by activity and class. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-people text-primary me-2"></i>Participation Reports</h4>
      <p class="text-muted small mb-0 mt-1">Student participation across co-curricular activities.</p>
    </div>
    <button class="btn btn-outline-primary btn-sm" data-kw-onclick="participationReportsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden mb-4">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Participation by Activity</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Activity</th><th>Category</th><th>Participants</th><th>Active</th><th>Share</th>
        </tr></thead>
        <tbody id="prByActivity"><tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Participation by Class</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Class</th><th>Participants</th><th>Share</th>
        </tr></thead>
        <tbody id="prByClass"><tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/participation_reports.js'); ?>
