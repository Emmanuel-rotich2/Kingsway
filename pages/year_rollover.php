<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-year-rollover">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
    <div>
      <h3 class="mb-0"><i class="bi bi-arrow-clockwise me-2 text-primary"></i>Year Transition / Rollover</h3>
      <small class="text-muted">A resumable 23-stage transition: prepare the next year, complete context and finance checks, assign promotions in batches, reconcile balances, archive history and activate Term 1.</small>
    </div>
    <span class="badge bg-warning fs-6 align-self-center" id="yrCurrentYearBadge">Loading…</span>
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

  <div id="yrNotReady" class="alert alert-warning d-none">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Not ready to roll over yet:</strong> all terms of the current year must be
    <strong>closed</strong> (use <a href="<?= $appBase ?>/home.php?route=term_transition" class="alert-link">Term Transition</a>) before the new year can be activated.
    You may still create and prepare the new year's structure below.
  </div>

  <div id="yrExistingYear" class="alert alert-info d-none">
    <i class="bi bi-calendar2-plus me-2"></i>
    <strong>New academic year already created.</strong>
    <a href="<?= $appBase ?>/home.php?route=academic_years" class="alert-link">Open Academic Years</a> to view it.
  </div>

  <!-- Workflow Card -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white fw-semibold">
      <i class="bi bi-list-ol me-2"></i>Year Transition Steps <span class="float-end small" id="yrWfInfo"></span>
    </div>
    <div class="card-body">
      <!-- Create form -->
      <div id="yrCreateForm">
        <div class="alert alert-light border">
          <h6 class="fw-semibold mb-3"><i class="bi bi-calendar-plus me-2"></i>Stages 1–4 — Confirm, create, date and generate the new academic year</h6>
          <p class="small text-muted mb-3">
            Enter the canonical next-year code, approved academic-year dates and
            all three term date ranges. Half-term breaks are optional per term:
            enable the checkbox and enter both dates when a break applies; leave
            it disabled when there is no half-term break. The system validates
            the supplied dates and derives school weeks from them; it never
            invents dates.
          </p>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label fw-semibold">New Year Code</label>
              <input type="text" id="yrToYear" class="form-control" placeholder="2027/2028" pattern="[0-9]{4}(/[0-9]{4})?">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Year Start Date</label>
              <input type="date" id="yrStartDate" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Year End Date</label>
              <input type="date" id="yrEndDate" class="form-control">
            </div>
          </div>
          <div class="table-responsive mt-3">
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <th>Term</th><th>Opening Date</th><th>Closing Date</th><th>Half-term break (optional)</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($termNo = 1; $termNo <= 3; $termNo++): ?>
                <tr>
                  <td class="fw-semibold">Term <?= $termNo ?></td>
                  <td><input type="date" id="yrT<?= $termNo ?>Start" class="form-control" required></td>
                  <td><input type="date" id="yrT<?= $termNo ?>End" class="form-control" required></td>
                  <td>
                    <div class="form-check">
                      <input class="form-check-input yr-half-term-toggle" type="checkbox" id="yrT<?= $termNo ?>HasHalfTerm" data-term="<?= $termNo ?>">
                      <label class="form-check-label small" for="yrT<?= $termNo ?>HasHalfTerm">Has half-term break</label>
                    </div>
                    <div class="yr-half-term-fields d-none mt-2" id="yrT<?= $termNo ?>HalfTermFields">
                      <div class="input-group input-group-sm">
                        <span class="input-group-text">From</span>
                        <input type="date" id="yrT<?= $termNo ?>HalfStart" class="form-control" aria-label="Term <?= $termNo ?> half-term start">
                        <span class="input-group-text">to</span>
                        <input type="date" id="yrT<?= $termNo ?>HalfEnd" class="form-control" aria-label="Term <?= $termNo ?> half-term end">
                      </div>
                    </div>
                  </td>
                </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
          <button class="btn btn-primary" id="yrStartBtn" onclick="yearRolloverController.createNewYear()">
            <i class="bi bi-calendar-plus me-1"></i> Create New Academic Year &amp; Calendar
          </button>
          <span class="ms-2 small text-muted" id="yrCreateMsg"></span>
        </div>
      </div>

      <!-- Stage list -->
      <div id="yrStageWrap" class="d-none">
        <div class="list-group" id="yrStages"></div>
      </div>

      <div id="yrPromotionBoard" class="d-none mt-4">
        <div class="alert alert-info mb-3">
          <strong>Administrator stream assignment</strong>
          <div class="small">Assign learners in batches. Your selections are saved immediately; you may leave and continue later. The transition cannot continue until every learner has a target stream.</div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-semibold" id="yrPromotionProgress">Loading learners…</span>
          <button class="btn btn-primary btn-sm" id="yrSavePromotionAssignments" onclick="yearRolloverController.savePromotionAssignments()"><i class="bi bi-save me-1"></i>Save selected assignments</button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr><th>Learner</th><th>Current class/stream</th><th>Next class</th><th>Target stream</th><th>Status</th></tr></thead>
            <tbody id="yrPromotionBoardBody"><tr><td colspan="5" class="text-muted text-center">Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Rollover Log -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">
      <i class="bi bi-journal-text me-2"></i>Transition Audit Log
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">Stage</th><th scope="col">Status</th><th scope="col">Details</th><th scope="col">Performed</th>
            </tr>
          </thead>
          <tbody id="yrLogBody">
            <tr><td colspan="4" class="text-center text-muted py-3">No transition activity yet.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php asset_script($appBase, 'js/pages/year_rollover.js'); ?>
