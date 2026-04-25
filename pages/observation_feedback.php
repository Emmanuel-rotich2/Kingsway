<?php
/**
 * Observation Feedback — PARTIAL
 * Feedback received from classroom observations with rating dimensions.
 * JS controller: js/pages/observation_feedback.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-observation_feedback">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-chat-left-text me-2 text-primary"></i>Observation Feedback</h3>
      <small class="text-muted">Feedback from classroom observation sessions</small>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="ofStatTotal">—</div>
          <div class="text-muted small">Total Feedback</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="ofStatThisTerm">—</div>
          <div class="text-muted small">This Term</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="ofStatAvgRating">—</div>
          <div class="text-muted small">Avg Rating (/ 4)</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Feedback Cards -->
  <div id="ofLoading" class="text-center py-5">
    <div class="spinner-border text-primary"></div>
    <p class="text-muted mt-2 mb-0">Loading feedback…</p>
  </div>

  <div id="ofFeedbackList" class="row g-3"></div>

  <div id="ofEmpty" style="display:none;" class="text-center py-5">
    <i class="bi bi-chat-dots fs-1 text-muted"></i>
    <p class="text-muted mt-2 mb-0">No observation feedback received yet.</p>
  </div>

</div>
<script src="<?= $appBase ?>/js/pages/observation_feedback.js?v=<?= time() ?>"></script>
