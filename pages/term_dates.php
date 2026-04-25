<?php
/**
 * Term Dates — PARTIAL
 * View and edit academic term start/end dates and holidays.
 * JS controller: js/pages/term_dates.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-calendar-range me-2 text-primary"></i>Term Dates</h2>
      <small class="text-muted">Academic term start/end dates and school holidays</small>
    </div>
    <button class="btn btn-primary d-none" id="tdAddBtn" onclick="termDatesController.showAddModal()">
      <i class="bi bi-plus-circle me-1"></i> Add Term
    </button>
  </div>

  <!-- Year filter -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
      <div class="row g-2 align-items-center">
        <div class="col-auto">
          <label class="form-label fw-semibold mb-0">Academic Year</label>
        </div>
        <div class="col-md-3">
          <select id="tdYear" class="form-select" onchange="termDatesController.loadTerms(this.value)">
            <option value="">— Loading years… —</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Terms table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div id="tdTableBody">
        <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
      </div>
    </div>
  </div>

</div>

<!-- EDIT TERM MODAL -->
<div class="modal fade" id="tdModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tdModalTitle">Edit Term Dates</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="tdTermId">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Term Name</label>
            <input type="text" id="tdTermName" class="form-control" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
            <input type="date" id="tdStartDate" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
            <input type="date" id="tdEndDate" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Holiday / Break Notes</label>
            <textarea id="tdHolidayNotes" class="form-control" rows="3" placeholder="e.g. Mid-term break: 15–19 Oct…"></textarea>
          </div>
        </div>
        <div id="tdError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="termDatesController.saveTerm()">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>js/pages/term_dates.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => termDatesController.init());</script>
