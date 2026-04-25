<?php
/**
 * Competency Checklist — PARTIAL
 * Teaching competency self-assessment; intern rates self, mentor validates.
 * JS controller: js/pages/competency_checklist.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-competency_checklist">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Competency Checklist</h3>
      <small class="text-muted">Self-assessment by domain — mentor validates each item</small>
    </div>
    <div class="d-flex gap-2">
      <span class="badge bg-secondary align-self-center">Rating: 1=Beginner · 2=Developing · 3=Proficient · 4=Expert</span>
      <button class="btn btn-primary btn-sm" id="ccSaveAllBtn">
        <i class="bi bi-check-all me-1"></i> Save All Ratings
      </button>
    </div>
  </div>

  <div id="ccLoading" class="text-center py-5">
    <div class="spinner-border text-primary"></div>
    <p class="text-muted mt-2 mb-0">Loading competencies…</p>
  </div>

  <div id="ccContent" style="display:none;">
    <!-- Domains rendered dynamically -->
    <div id="ccDomainList"></div>
  </div>

  <div id="ccEmpty" style="display:none;" class="text-center py-5">
    <i class="bi bi-list-check fs-1 text-muted"></i>
    <p class="text-muted mt-2 mb-0">No competency checklist has been assigned.</p>
  </div>

</div>
<script src="<?= $appBase ?>/js/pages/competency_checklist.js?v=<?= time() ?>"></script>
