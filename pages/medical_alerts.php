<?php
/* Medical Alerts — active health alerts and sick bay visits. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Medical Alerts</h4>
      <p class="text-muted small mb-0 mt-1">Active student health alerts and current sick bay visits.</p>
    </div>
    <button class="btn btn-outline-danger btn-sm" data-kw-onclick="medicalAlertsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white"><div class="small text-muted">Active Sick Bay</div><div class="fs-3 fw-bold" id="maSickBay">—</div></div></div>
    <div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white"><div class="small text-muted">Visits Today</div><div class="fs-3 fw-bold" id="maVisits">—</div></div></div>
    <div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white"><div class="small text-muted">Hospital Referrals</div><div class="fs-3 fw-bold" id="maReferred">—</div></div></div>
    <div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white"><div class="small text-muted">Vaccinations Due</div><div class="fs-3 fw-bold" id="maVaxDue">—</div></div></div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden mb-4">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Active Health Alerts</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Adm No</th><th>Student</th><th>Category</th><th>Alert / Condition</th><th>Severity</th><th>Instructions</th>
        </tr></thead>
        <tbody id="maAlertsBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Sick Bay — Active Visits</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Adm No</th><th>Student</th><th>Class</th><th>Complaint</th><th>Visit Time</th><th>Observation</th>
        </tr></thead>
        <tbody id="maSickBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/medical_alerts.js'); ?>
