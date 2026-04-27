<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-year-rollover">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-arrow-clockwise me-2 text-primary"></i>Year-End Rollover</h3>
      <small class="text-muted">Close the academic year, promote students, carry over fees, and open the new year</small>
    </div>
    <span class="badge bg-warning fs-6" id="yrCurrentYearBadge">Loading…</span>
  </div>

  <!-- Pre-flight Status -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-calendar-check fs-3 mb-1" id="yrTermsIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrTermsStatus">Checking…</div>
          <div class="text-muted small">All 3 Terms Closed</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-file-earmark-check fs-3 mb-1" id="yrResultsIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrResultsStatus">Checking…</div>
          <div class="text-muted small">Results Finalised</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-person-check fs-3 mb-1" id="yrPromotionsIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrPromotionsStatus">Checking…</div>
          <div class="text-muted small">Promotions Done</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <i class="bi bi-cash-coin fs-3 mb-1" id="yrFeesIcon" style="color:gray"></i>
          <div class="fw-semibold" id="yrFeesStatus">Checking…</div>
          <div class="text-muted small">Students with Outstanding Fees</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Rollover Steps -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white fw-semibold">
      <i class="bi bi-list-ol me-2"></i>Rollover Steps (execute in order)
    </div>
    <div class="card-body p-0">
      <div class="list-group list-group-flush" id="yrStepsList">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      </div>
    </div>
  </div>

  <!-- Rollover Log -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">
      <i class="bi bi-journal-text me-2"></i>Rollover Audit Log
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Step</th><th>Status</th><th>Promoted</th><th>Retained</th>
              <th>Fee Carryovers</th><th>Credit Notes</th><th>Performed</th>
            </tr>
          </thead>
          <tbody id="yrLogBody">
            <tr><td colspan="7" class="text-center text-muted py-3">No rollover activity yet.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/year_rollover.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => yearRolloverController.init());</script>
