<?php
/**
 * Reflection Journal — PARTIAL
 * Personal teaching reflection journal (CRUD entries).
 * JS controller: js/pages/reflection_journal.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-reflection_journal">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="mb-0"><i class="bi bi-book-half me-2 text-primary"></i>Reflection Journal</h3>
      <small class="text-muted">Record and review your teaching practice reflections</small>
    </div>
    <button class="btn btn-primary btn-sm" id="rjWriteBtn">
      <i class="bi bi-pencil-square me-1"></i> Write Entry
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="rjStatTotal">—</div>
          <div class="text-muted small">Total Entries</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="rjStatThisWeek">—</div>
          <div class="text-muted small">This Week</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="rjStatThisMonth">—</div>
          <div class="text-muted small">This Month</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Journal Entries -->
  <div id="rjLoading" class="text-center py-5">
    <div class="spinner-border text-primary"></div>
    <p class="text-muted mt-2 mb-0">Loading journal…</p>
  </div>
  <div id="rjEntriesList" class="row g-3"></div>
  <div id="rjEmpty" style="display:none;" class="text-center py-5">
    <i class="bi bi-book fs-1 text-muted"></i>
    <p class="text-muted mt-2">Start your reflective practice — write your first entry!</p>
    <button class="btn btn-primary btn-sm" id="rjFirstEntryBtn">
      <i class="bi bi-pencil-square me-1"></i> Write Entry
    </button>
  </div>

</div>

<!-- Write Entry Modal -->
<div class="modal fade" id="rjModal" tabindex="-1" aria-labelledby="rjModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom">
        <h5 class="modal-title" id="rjModalLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Reflection Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="rjDate">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Lesson / Class <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="rjLessonClass" placeholder="e.g. Grade 4 — Mathematics">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">What went well?</label>
            <textarea class="form-control" id="rjWentWell" rows="3" placeholder="Describe what worked effectively…"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">What could be improved?</label>
            <textarea class="form-control" id="rjImprove" rows="3" placeholder="Describe what you'd do differently…"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Next steps / Actions</label>
            <textarea class="form-control" id="rjNextSteps" rows="2" placeholder="Specific actions for your next lesson…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" id="rjSaveBtn">
          <i class="bi bi-check-circle me-1"></i> Save Entry
        </button>
      </div>
    </div>
  </div>
</div>
<script src="<?= $appBase ?>/js/pages/reflection_journal.js?v=<?= time() ?>"></script>
