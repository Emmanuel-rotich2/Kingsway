<?php
/* Room Assignments — dormitory occupancy and bed assignments. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-door-open text-primary me-2"></i>Room Assignments</h4>
      <p class="text-muted small mb-0 mt-1">Dormitory occupancy and student bed assignments.</p>
    </div>
    <button class="btn btn-outline-primary btn-sm" data-kw-onclick="roomAssignmentsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div id="raDorms" class="row g-3"></div>

  <div class="bg-white border rounded-3 overflow-hidden mt-4">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Dormitory Roster</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Adm No</th><th>Student</th><th>Gender</th><th>Class</th><th>Dormitory</th><th>Bed No</th>
        </tr></thead>
        <tbody id="raBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/room_assignments.js'); ?>
