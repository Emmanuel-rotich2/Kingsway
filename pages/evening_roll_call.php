<?php
/* Evening Roll Call — night roll call attendance summary. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-moon-stars text-info me-2"></i>Evening Roll Call</h4>
      <p class="text-muted small mb-0 mt-1">Night roll call attendance per dormitory.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <input type="date" class="form-control form-control-sm" id="ercDate" value="<?= date('Y-m-d') ?>">
      <button class="btn btn-outline-info btn-sm" data-kw-onclick="eveningRollCallController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
    </div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Dormitory</th><th>House Parent</th><th>Session</th><th>Students</th><th>Present</th><th>Absent</th><th>On Leave</th><th>Sick Bay</th><th>Attendance</th>
        </tr></thead>
        <tbody id="ercBody"><tr><td colspan="9" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/evening_roll_call.js'); ?>
