<?php
/**
 * Mentor Notes — PARTIAL
 * Notes and guidance from mentorship sessions (read-only for intern).
 * JS controller: js/pages/mentor_notes.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-mentor_notes">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Mentor Notes</h3>
      <small class="text-muted">Guidance and notes recorded by your mentor</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <label class="fw-semibold small me-1">Filter:</label>
      <select class="form-select form-select-sm" id="mnTypeFilter" style="width:auto;">
        <option value="">All Types</option>
        <option value="scheduled">Scheduled Session</option>
        <option value="informal">Informal</option>
        <option value="observation_debrief">Observation Debrief</option>
      </select>
    </div>
  </div>

  <div id="mnLoading" class="text-center py-5">
    <div class="spinner-border text-primary"></div>
    <p class="text-muted mt-2 mb-0">Loading notes…</p>
  </div>

  <div id="mnNotesList" class="row g-3"></div>

  <div id="mnEmpty" style="display:none;" class="text-center py-5">
    <i class="bi bi-journal fs-1 text-muted"></i>
    <p class="text-muted mt-2 mb-0">No mentor notes recorded yet.</p>
  </div>

</div>
<script src="<?= $appBase ?>/js/pages/mentor_notes.js?v=<?= time() ?>"></script>
