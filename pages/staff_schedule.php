<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-staff_schedule">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-calendar3-week me-2 text-primary"></i>Staff Schedule</h3>
      <p class="text-muted mb-0 small">Your weekly teaching timetable, duties and upcoming commitments.</p>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <select class="form-select form-select-sm" id="ssTermFilter" style="width:auto;" onchange="staffScheduleController.loadTimetable()">
        <option value="">Current Term</option>
      </select>
      <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
        <i class="bi bi-printer me-1"></i> Print
      </button>
    </div>
  </div>

  <!-- Workload summary cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="ssTotalPeriods">—</div>
          <div class="text-muted small">Periods / Week</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="ssClassesCount">—</div>
          <div class="text-muted small">Classes Taught</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="ssSubjectsCount">—</div>
          <div class="text-muted small">Subjects</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="ssFreePeriodsCount">—</div>
          <div class="text-muted small">Free Periods</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Weekly timetable grid -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold d-flex justify-content-between">
      <span><i class="bi bi-grid-3x3 me-2 text-primary"></i>Weekly Timetable</span>
      <span class="badge bg-secondary" id="ssTimetableTerm">—</span>
    </div>
    <div class="card-body p-0">
      <div id="ssTimetableLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2">Loading your timetable…</p>
      </div>
      <div id="ssTimetableContent" style="display:none;">
        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0" id="ssTimetableGrid">
            <thead class="table-dark">
              <tr>
                <th style="width:100px;">Period</th>
                <th class="text-center">Monday</th>
                <th class="text-center">Tuesday</th>
                <th class="text-center">Wednesday</th>
                <th class="text-center">Thursday</th>
                <th class="text-center">Friday</th>
              </tr>
            </thead>
            <tbody id="ssTimetableBody"></tbody>
          </table>
        </div>
      </div>
      <div id="ssTimetableEmpty" style="display:none;" class="text-center py-5">
        <i class="bi bi-calendar-x fs-1 text-muted"></i>
        <p class="text-muted mt-2">No timetable assigned for this term yet.</p>
        <p class="text-muted small">Contact the academic coordinator to set up your schedule.</p>
      </div>
    </div>
  </div>

  <!-- Split: Classes I teach + Upcoming duties -->
  <div class="row g-4">
    <!-- Classes list -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">
          <i class="bi bi-mortarboard me-2 text-success"></i>My Classes This Term
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>Class</th><th>Subject</th><th>Periods/Wk</th><th>Students</th></tr>
              </thead>
              <tbody id="ssClassesBody">
                <tr><td colspan="4" class="text-center py-4">
                  <div class="spinner-border spinner-border-sm text-primary"></div>
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <!-- Upcoming duties / supervision -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">
          <i class="bi bi-clipboard-check me-2 text-warning"></i>Upcoming Duties & Supervision
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>Date</th><th>Type</th><th>Time</th><th>Location</th></tr>
              </thead>
              <tbody id="ssDutiesBody">
                <tr><td colspan="4" class="text-center py-4">
                  <div class="spinner-border spinner-border-sm text-primary"></div>
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<style>
@media print {
  .btn, .card-header { display: none !important; }
  #ssTimetableContent { display: block !important; }
  .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
}
.ss-lesson-cell {
  background: #e8f4fd;
  border-radius: 4px;
  padding: 6px 8px;
  font-size: 12px;
  line-height: 1.3;
}
.ss-break-cell { background: #fff3cd; font-size: 12px; text-align:center; color:#856404; }
.ss-free-cell  { color: #adb5bd; font-size: 11px; text-align:center; }
</style>

<script src="<?= $appBase ?>/js/pages/staff_schedule.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => staffScheduleController.init());</script>
