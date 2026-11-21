<?php
/* Vaccination Records — immunisation history and upcoming doses. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-eyedropper text-primary me-2"></i>Vaccination Records</h4>
      <p class="text-muted small mb-0 mt-1">Immunisation history and doses due within 30 days.</p>
    </div>
    <button class="btn btn-outline-primary btn-sm" data-kw-onclick="vaccinationRecordsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden mb-4">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Due Within 30 Days</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Adm No</th><th>Student</th><th>Class</th><th>Vaccine</th><th>Dose</th><th>Last Given</th><th>Next Due</th>
        </tr></thead>
        <tbody id="vcDueBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">All Vaccination Records</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Adm No</th><th>Student</th><th>Class</th><th>Vaccine</th><th>Dose</th><th>Date Given</th><th>Next Due</th><th>Given By</th>
        </tr></thead>
        <tbody id="vcAllBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/vaccination_records.js'); ?>
