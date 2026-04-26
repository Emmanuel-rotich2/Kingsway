<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-term_transition">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-arrow-right-circle-fill me-2 text-primary"></i>Term Transition</h3>
      <p class="text-muted mb-0 small">Close the current term, roll over the timetable, and activate the next term.</p>
    </div>
    <span class="badge bg-warning fs-6 align-self-center" id="ttCurrentTermBadge">Loading…</span>
  </div>

  <!-- Step bar -->
  <div class="d-flex align-items-center mb-4 gap-0" id="ttStepBar">
    <div class="tt-step active" data-step="1"><span class="tt-num">1</span><span class="tt-lbl">Review Term 1</span></div>
    <div class="tt-connector"></div>
    <div class="tt-step" data-step="2"><span class="tt-num">2</span><span class="tt-lbl">Close Term</span></div>
    <div class="tt-connector"></div>
    <div class="tt-step" data-step="3"><span class="tt-num">3</span><span class="tt-lbl">Rollover Timetable</span></div>
    <div class="tt-connector"></div>
    <div class="tt-step" data-step="4"><span class="tt-num">4</span><span class="tt-lbl">Setup Term 2</span></div>
    <div class="tt-connector"></div>
    <div class="tt-step" data-step="5"><span class="tt-num">5</span><span class="tt-lbl">Activate</span></div>
  </div>

  <!-- ── STEP 1: Review current term ─────────────────────────────────────── -->
  <div id="ttStep1">
    <div class="row g-3 mb-4" id="ttReviewStats">
      <div class="col-12 text-center py-4"><div class="spinner-border text-primary"></div></div>
    </div>
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-check2-circle text-success me-2"></i>Completed This Term
          </div>
          <div class="card-body" id="ttCompletedList">
            <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-success"></div></div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-exclamation-triangle text-warning me-2"></i>Pending / Incomplete
          </div>
          <div class="card-body" id="ttPendingList">
            <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></div>
          </div>
        </div>
      </div>
    </div>
    <div class="d-flex justify-content-end">
      <button class="btn btn-primary" onclick="termTransitionController.goStep(2)">
        Proceed to Close Term <i class="bi bi-arrow-right ms-1"></i>
      </button>
    </div>
  </div>

  <!-- ── STEP 2: Close current term ──────────────────────────────────────── -->
  <div id="ttStep2" style="display:none;">
    <div class="card border-0 shadow-sm mb-4 border-start border-danger border-3">
      <div class="card-body">
        <h5 class="fw-semibold mb-3"><i class="bi bi-lock-fill text-danger me-2"></i>Close Current Term</h5>
        <p class="text-muted">This will mark <strong id="ttCurrentTermName"></strong> as <span class="badge bg-secondary">Completed</span>.
          Lesson plans, attendance, and results will be locked from further edits.</p>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="ttConfirmClose">
          <label class="form-check-label fw-semibold" for="ttConfirmClose">
            I confirm that all term 1 data (results, attendance, lesson plans) has been finalised
          </label>
        </div>
        <div id="ttCloseError" class="alert alert-danger d-none"></div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary" onclick="termTransitionController.goStep(1)">
        <i class="bi bi-arrow-left me-1"></i> Back
      </button>
      <button class="btn btn-danger ms-auto" id="ttCloseTermBtn" onclick="termTransitionController.closeTerm()">
        <i class="bi bi-lock me-1"></i> Close Term 1
      </button>
    </div>
  </div>

  <!-- ── STEP 3: Rollover timetable ──────────────────────────────────────── -->
  <div id="ttStep3" style="display:none;">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <h5 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat text-primary me-2"></i>Roll Over Timetable</h5>
        <p class="text-muted">Copy the Term 1 class timetable to Term 2. You can edit individual slots after activation.</p>

        <div class="row g-3 mb-3" id="ttTimetableStats">
          <div class="col-md-3">
            <div class="card border-0 bg-light text-center p-3">
              <div class="fs-2 fw-bold text-primary" id="ttSlotCount">—</div>
              <div class="small text-muted">Timetable Slots (Term 1)</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card border-0 bg-light text-center p-3">
              <div class="fs-2 fw-bold text-success" id="ttClassCount">—</div>
              <div class="small text-muted">Classes Covered</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card border-0 bg-light text-center p-3">
              <div class="fs-2 fw-bold text-info" id="ttTeacherCount">—</div>
              <div class="small text-muted">Teachers in Timetable</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card border-0 bg-light text-center p-3">
              <div class="fs-2 fw-bold text-secondary" id="ttRolloverStatus">Ready</div>
              <div class="small text-muted">Rollover Status</div>
            </div>
          </div>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="ttKeepTeachers" checked>
          <label class="form-check-label" for="ttKeepTeachers">Keep same teacher assignments</label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="ttKeepRooms" checked>
          <label class="form-check-label" for="ttKeepRooms">Keep same room assignments</label>
        </div>
        <div id="ttRolloverError" class="alert alert-danger d-none"></div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary" onclick="termTransitionController.goStep(2)">
        <i class="bi bi-arrow-left me-1"></i> Back
      </button>
      <button class="btn btn-primary ms-auto" onclick="termTransitionController.rolloverTimetable()">
        <i class="bi bi-arrow-repeat me-1"></i> Roll Over Timetable to Term 2
      </button>
      <button class="btn btn-outline-secondary" onclick="termTransitionController.goStep(4)">
        Skip (no timetable yet) <i class="bi bi-arrow-right ms-1"></i>
      </button>
    </div>
  </div>

  <!-- ── STEP 4: Setup Term 2 ─────────────────────────────────────────────── -->
  <div id="ttStep4" style="display:none;">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <h5 class="fw-semibold mb-3"><i class="bi bi-calendar2-check text-info me-2"></i>Term 2 Setup</h5>
        <div class="row g-3" id="ttNextTermDetails">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Term 2 Start Date</label>
            <input type="date" id="ttTerm2Start" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Term 2 End Date</label>
            <input type="date" id="ttTerm2End" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Mid-Term Break Start</label>
            <input type="date" id="ttMidtermStart" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Mid-Term Break End</label>
            <input type="date" id="ttMidtermEnd" class="form-control">
          </div>
        </div>
        <div class="mt-3">
          <h6 class="fw-semibold">Term 2 Checklist</h6>
          <div id="ttSetupChecklist" class="row g-2 mt-1"></div>
        </div>
        <div id="ttSetupError" class="alert alert-danger mt-3 d-none"></div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary" onclick="termTransitionController.goStep(3)">
        <i class="bi bi-arrow-left me-1"></i> Back
      </button>
      <button class="btn btn-success ms-auto" onclick="termTransitionController.saveTerm2Setup()">
        Save Term 2 Setup <i class="bi bi-arrow-right ms-1"></i>
      </button>
    </div>
  </div>

  <!-- ── STEP 5: Activate Term 2 ─────────────────────────────────────────── -->
  <div id="ttStep5" style="display:none;">
    <div class="card border-0 shadow-sm mb-4 border-start border-success border-3">
      <div class="card-body">
        <h5 class="fw-semibold mb-3"><i class="bi bi-play-fill text-success me-2"></i>Activate Term 2</h5>
        <div id="ttActivateSummary" class="row g-3 mb-3"></div>
        <div class="alert alert-success mb-0">
          <i class="bi bi-check-circle me-2"></i>
          Activating Term 2 will:
          <ul class="mb-0 mt-1">
            <li>Set Term 2 status to <strong>Current</strong></li>
            <li>Mark Term 1 as <strong>Completed</strong> (if not already done)</li>
            <li>Enable lesson planning for Term 2</li>
            <li>Make Term 2 timetable active across all teacher views</li>
          </ul>
        </div>
        <div id="ttActivateError" class="alert alert-danger mt-3 d-none"></div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary" onclick="termTransitionController.goStep(4)">
        <i class="bi bi-arrow-left me-1"></i> Back
      </button>
      <button class="btn btn-success ms-auto btn-lg" id="ttActivateBtn" onclick="termTransitionController.activateTerm2()">
        <i class="bi bi-play-fill me-1"></i> Activate Term 2 Now
      </button>
    </div>
  </div>

  <!-- ── DONE ───────────────────────────────────────────────────────────────── -->
  <div id="ttDone" style="display:none;">
    <div class="card border-0 shadow-sm text-center py-5">
      <div class="card-body">
        <i class="bi bi-check-circle-fill text-success fs-1 mb-3 d-block"></i>
        <h4 class="fw-bold text-success">Term 2 is Now Active!</h4>
        <p class="text-muted">All systems have been updated. Teachers can now start creating lesson plans and the timetable is live.</p>
        <div class="d-flex justify-content-center gap-3 mt-3">
          <a href="<?= $appBase ?>/home.php?route=manage_timetable" class="btn btn-primary">
            <i class="bi bi-calendar3 me-1"></i> View Timetable
          </a>
          <a href="<?= $appBase ?>/home.php?route=manage_lesson_plans" class="btn btn-outline-primary">
            <i class="bi bi-journal-plus me-1"></i> Start Lesson Plans
          </a>
          <a href="<?= $appBase ?>/home.php?route=exam_schedule" class="btn btn-outline-secondary">
            <i class="bi bi-calendar-event me-1"></i> Plan Exams
          </a>
        </div>
      </div>
    </div>
  </div>

</div>

<style>
.tt-step { display:flex; flex-direction:column; align-items:center; gap:4px; min-width:80px; }
.tt-num  { width:34px; height:34px; border-radius:50%; background:#dee2e6; color:#6c757d;
           display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; transition:.2s; }
.tt-lbl  { font-size:11px; color:#6c757d; text-align:center; }
.tt-connector { flex:1; height:2px; background:#dee2e6; min-width:20px; margin-top:-20px; }
.tt-step.active .tt-num { background:#0d6efd; color:#fff; }
.tt-step.active .tt-lbl { color:#0d6efd; font-weight:600; }
.tt-step.done .tt-num { background:#198754; color:#fff; }
.tt-step.done .tt-lbl { color:#198754; }
</style>

<script src="<?= $appBase ?>/js/pages/term_transition.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => termTransitionController.init());</script>
