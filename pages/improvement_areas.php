<?php
/**
 * Improvement Areas — PARTIAL
 * Improvement feedback recorded in classroom observations for the signed-in staff member.
 * JS controller: js/pages/improvement_areas.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-improvement_areas">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-tools me-2 text-primary"></i>Improvement Areas</h3>
      <small class="text-muted">Areas for improvement recorded in your classroom observations</small>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="iaStatTotal">—</div>
          <div class="text-muted small">Feedback Findings</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="iaStatResolved">—</div>
          <div class="text-muted small">With Recommendations</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="iaStatPending">—</div>
          <div class="text-muted small">Average Rating</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Areas Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center py-2">
      <i class="bi bi-tools me-2 text-primary"></i>
      <span class="fw-semibold">Areas for Development</span>
    </div>
    <div class="card-body p-0">
      <div id="iaLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Loading…</p>
      </div>
      <div id="iaContent" style="display:none;">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col">Area for Improvement</th>
                <th scope="col">Observation Date</th>
                <th scope="col">Rating</th>
                <th scope="col">Recommendations</th>
                <th scope="col">Observation Status</th>
              </tr>
            </thead>
            <tbody id="iaTableBody"></tbody>
          </table>
        </div>
      </div>
      <div id="iaEmpty" style="display:none;" class="text-center py-5">
        <i class="bi bi-check-circle fs-1 text-success"></i>
        <p class="text-muted mt-2 mb-0">No observation feedback has recorded an area for improvement.</p>
      </div>
      <div id="iaError" style="display:none;" class="alert alert-danger m-3">Could not load observation feedback. Refresh to try again.</div>
    </div>
  </div>

</div>
<?php asset_script($appBase, 'js/pages/improvement_areas.js'); ?>
