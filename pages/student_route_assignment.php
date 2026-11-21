<?php
/* Student Route Assignment — assign/withdraw students to transport routes. */

?>

<style>
.sra-table td,.sra-table th { vertical-align:middle;font-size:.85rem; }
.sra-form label { font-size:.82rem;font-weight:600;color:#374151;margin-bottom:4px;display:block; }
.sra-form input,.sra-form select { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.88rem;outline:none; }
.sra-form input:focus,.sra-form select:focus { border-color:#198754;box-shadow:0 0 0 3px rgba(25,135,84,.1); }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-person-check text-success me-2"></i>Student Route Assignment</h4>
      <p class="text-muted small mb-0 mt-1">Assign students to transport routes and stops, or withdraw existing assignments.</p>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body sra-form">
          <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle text-success me-1"></i>Assign a Student</h6>
          <div class="mb-2">
            <label>Route *</label>
            <select id="assignRoute" required></select>
          </div>
          <div class="mb-2">
            <label>Stop</label>
            <select id="assignStop"></select>
          </div>
          <div class="mb-2">
            <label>Admission No. / Parent Phone</label>
            <input type="text" id="assignQuery" placeholder="e.g. KA-2026-0142 or 07xxxxxxxx">
          </div>
          <div class="mb-2">
            <label>Billing Month</label>
            <select id="assignMonth"></select>
          </div>
          <div class="mb-2">
            <label>Year</label>
            <select id="assignYear"></select>
          </div>
          <div id="verifiedStudent" class="small text-success fw-semibold mb-2"></div>
          <button class="btn btn-success btn-sm w-100" id="assignBtn" data-kw-onclick="routeAssignController.assign()">
            <i class="bi bi-check-lg me-1"></i>Assign Student
          </button>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="fw-bold mb-0"><i class="bi bi-bus-front me-1"></i>Students on Route</h6>
            <select id="viewRoute" class="form-select form-select-sm" style="width:240px"></select>
          </div>
          <div class="table-responsive">
            <table class="table table-hover sra-table mb-0">
              <thead class="table-light"><tr>
                <th scope="col">Adm No.</th><th scope="col">Student</th><th scope="col">Stop</th><th scope="col">Month / Year</th><th scope="col">Status</th><th class="text-end">Actions</th>
              </tr></thead>
              <tbody id="routeStudentsBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/student_route_assignment.js'); ?>
