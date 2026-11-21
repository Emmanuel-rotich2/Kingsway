<?php
/* Rewards & Recognition — student rewards and commendations. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-award text-success me-2"></i>Rewards & Recognition</h4>
      <p class="text-muted small mb-0 mt-1">Student commendations and rewards from discipline records.</p>
    </div>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="rewardsRecognitionController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Student</th><th>Adm No</th><th>Class</th><th>Type</th><th>Date</th><th>Description</th><th>Status</th>
        </tr></thead>
        <tbody id="rrBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/rewards_recognition.js'); ?>
