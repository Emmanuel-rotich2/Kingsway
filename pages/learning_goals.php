<?php
/**
 * Learning Goals — PARTIAL
 * Personal learning objectives for the internship placement (CRUD).
 * JS controller: js/pages/learning_goals.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-learning_goals">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="mb-0"><i class="bi bi-bullseye me-2 text-primary"></i>Learning Goals</h3>
      <small class="text-muted">Personal learning objectives for your internship placement</small>
    </div>
    <button class="btn btn-primary btn-sm" id="lgAddBtn">
      <i class="bi bi-plus-circle me-1"></i> Add Goal
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="lgStatTotal">—</div>
          <div class="text-muted small">Total Goals</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="lgStatInProgress">—</div>
          <div class="text-muted small">In Progress</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="lgStatCompleted">—</div>
          <div class="text-muted small">Completed</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="lgStatOverdue">—</div>
          <div class="text-muted small">Overdue</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Goals List -->
  <div id="lgLoading" class="text-center py-5">
    <div class="spinner-border text-primary"></div>
    <p class="text-muted mt-2 mb-0">Loading goals…</p>
  </div>
  <div id="lgGoalsList" class="row g-3"></div>
  <div id="lgEmpty" style="display:none;" class="text-center py-5">
    <i class="bi bi-bullseye fs-1 text-muted"></i>
    <p class="text-muted mt-2">No learning goals set yet. Add your first goal!</p>
    <button class="btn btn-primary btn-sm" onclick="learningGoalsController.showAddModal()">
      <i class="bi bi-plus-circle me-1"></i> Add Goal
    </button>
  </div>

</div>

<!-- Add/Edit Goal Modal -->
<div class="modal fade" id="lgModal" tabindex="-1" aria-labelledby="lgModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom">
        <h5 class="modal-title" id="lgModalLabel"><i class="bi bi-bullseye me-2 text-primary"></i>Learning Goal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="lgGoalId">
        <div class="mb-3">
          <label class="form-label fw-semibold">Goal Description <span class="text-danger">*</span></label>
          <textarea class="form-control" id="lgGoalText" rows="3" placeholder="Describe your learning goal…"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Category</label>
          <select class="form-select" id="lgCategory">
            <option value="Professional">Professional</option>
            <option value="Pedagogical">Pedagogical</option>
            <option value="Subject Knowledge">Subject Knowledge</option>
            <option value="Classroom Management">Classroom Management</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Target Date</label>
          <input type="date" class="form-control" id="lgTargetDate">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Status</label>
          <select class="form-select" id="lgStatus">
            <option value="not_started">Not Started</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
          </select>
        </div>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" id="lgSaveBtn">
          <i class="bi bi-check-circle me-1"></i> Save Goal
        </button>
      </div>
    </div>
  </div>
</div>
<script src="<?= $appBase ?>/js/pages/learning_goals.js?v=<?= time() ?>"></script>
