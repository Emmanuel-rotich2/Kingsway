<?php
/**
 * Intern Schedule — PARTIAL
 * Weekly teaching schedule for the intern placement.
 * JS controller: js/pages/intern_schedule.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-intern_schedule">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="mb-0"><i class="bi bi-calendar-week me-2 text-primary"></i>My Teaching Schedule</h3>
      <small class="text-muted">Weekly timetable for your internship placement</small>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <button class="btn btn-outline-secondary btn-sm" id="isPrevWeek"><i class="bi bi-chevron-left"></i> Prev</button>
      <span class="badge bg-primary px-3 py-2" style="font-size:.9rem;" id="isWeekLabel">—</span>
      <button class="btn btn-outline-secondary btn-sm" id="isNextWeek">Next <i class="bi bi-chevron-right"></i></button>
      <button class="btn btn-outline-primary btn-sm" id="isToday">Today</button>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="isStatPeriods">—</div>
          <div class="text-muted small">Periods This Week</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="isStatClasses">—</div>
          <div class="text-muted small">Classes Assigned</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="isStatSubjects">—</div>
          <div class="text-muted small">Subjects Taught</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Schedule Grid -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center py-2">
      <i class="bi bi-grid-3x5 me-2 text-primary"></i>
      <span class="fw-semibold">Weekly Timetable</span>
    </div>
    <div class="card-body p-0">
      <div id="isLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Loading schedule…</p>
      </div>
      <div id="isContent" style="display:none;">
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:90px;">Period / Time</th>
                <th>Monday</th><th>Tuesday</th><th>Wednesday</th><th>Thursday</th><th>Friday</th>
              </tr>
            </thead>
            <tbody id="isTableBody"></tbody>
          </table>
        </div>
      </div>
      <div id="isEmpty" style="display:none;" class="text-center py-5">
        <i class="bi bi-calendar-x fs-1 text-muted"></i>
        <p class="text-muted mt-2 mb-0">No schedule found for this week.</p>
      </div>
    </div>
  </div>

</div>
<script src="<?= $appBase ?>/js/pages/intern_schedule.js?v=<?= time() ?>"></script>
