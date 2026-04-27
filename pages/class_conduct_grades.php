<?php
/**
 * Class Conduct Grades — PARTIAL
 * Conduct ratings per student in the teacher's class.
 * JS controller: js/pages/class_conduct_grades.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-class_conduct_grades">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="mb-0"><i class="bi bi-star-half me-2 text-primary"></i>Class Conduct Grades</h3>
      <small class="text-muted">Behavioural ratings and conduct assessments for your class</small>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <select class="form-select form-select-sm" id="cgTermFilter" style="width:auto;">
        <option value="">All Terms</option>
        <option value="1">Term 1</option>
        <option value="2">Term 2</option>
        <option value="3">Term 3</option>
      </select>
      <select class="form-select form-select-sm" id="cgClassFilter" style="width:auto;">
        <option value="self">My Class</option>
      </select>
      <button class="btn btn-outline-primary btn-sm" id="cgApplyBtn">Filter</button>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="cgStatExcellent">—</div>
          <div class="text-muted small">Excellent (A)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="cgStatSatisfactory">—</div>
          <div class="text-muted small">Satisfactory (B/C)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="cgStatNeedsImprovement">—</div>
          <div class="text-muted small">Needs Improvement (D)</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Grades Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center py-2">
      <i class="bi bi-table me-2 text-primary"></i>
      <span class="fw-semibold">Student Conduct Records</span>
    </div>
    <div class="card-body p-0">
      <div id="cgLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Loading…</p>
      </div>
      <div id="cgContent" style="display:none;">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Student</th>
                <th>Conduct Grade</th>
                <th>Key Strengths</th>
                <th>Areas to Improve</th>
                <th>Teacher Comments</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="cgTableBody"></tbody>
          </table>
        </div>
      </div>
      <div id="cgEmpty" style="display:none;" class="text-center py-5">
        <i class="bi bi-star fs-1 text-muted"></i>
        <p class="text-muted mt-2 mb-0">No conduct grades recorded for this selection.</p>
      </div>
    </div>
  </div>

</div>
<script src="<?= $appBase ?>/js/pages/class_conduct_grades.js?v=<?= time() ?>"></script>
