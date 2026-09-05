<?php
/* Chaplaincy Programs — SDA spiritual program catalog, schedule & attendance. */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-church text-primary me-2"></i>Spiritual Programs</h4>
      <p class="text-muted small mb-0 mt-1">
        SDA ministries — Sabbath Worship (Saturday), class-linked Sabbath School, Friday Vespers,
        Adventist Youth, Week of Prayer, Pathfinders &amp; Adventurers, Choral, Bible Study, Outreach, Baptism services.
      </p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-primary btn-sm" id="cpRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
      <button class="btn btn-outline-secondary btn-sm" id="cpManage"><i class="bi bi-gear me-1"></i>Manage Catalog</button>
      <button class="btn btn-primary btn-sm" id="cpSchedule"><i class="bi bi-calendar-plus me-1"></i>Schedule Session</button>
    </div>
  </div>

  <div class="row g-4">
    <!-- Program catalog -->
    <div class="col-lg-5">
      <div class="bg-white border rounded-3 overflow-hidden mb-4">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">
          <i class="bi bi-grid-1x2 me-1"></i>Program Catalog
        </div>
        <div class="p-2" id="cpPrograms"><div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div></div></div>
      </div>

      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">
          <i class="bi bi-calendar-week me-1"></i>Upcoming Sessions
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Program</th><th>Class</th><th>Status</th><th></th></tr></thead>
            <tbody id="cpUpcoming">
              <tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Sessions list -->
    <div class="col-lg-7">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
          <span class="fw-semibold small text-uppercase text-muted"><i class="bi bi-calendar3 me-1"></i>All Sessions</span>
          <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="cpProgramFilter" style="width:auto">
              <option value="">All programs</option>
            </select>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
              <th>Date / Time</th><th>Program</th><th>Theme</th><th>Speaker</th><th>Attend</th><th>Status</th><th></th>
            </tr></thead>
            <tbody id="cpSessions">
              <tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Catalog Management Modal -->
<div class="modal fade" id="cpCatalogModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-grid-1x2 me-1"></i>Manage Program Catalog</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="cpProgForm" class="row g-2 p-3 border rounded-3 bg-light mb-3 align-items-end">
          <input type="hidden" id="cpProgId">
          <div class="col-md-3">
            <label class="form-label small mb-1">Name*</label>
            <input type="text" class="form-control form-control-sm" id="cpProgName" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1">Description</label>
            <input type="text" class="form-control form-control-sm" id="cpProgDesc">
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Default Day</label>
            <select class="form-select form-select-sm" id="cpProgDay">
              <option value="SATURDAY">SATURDAY</option>
              <option value="SUNDAY">SUNDAY</option>
              <option value="FRIDAY">FRIDAY</option>
              <option value="WEEKDAY">WEEKDAY</option>
              <option value="ANY">ANY</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Start Time</label>
            <input type="time" class="form-control form-control-sm" id="cpProgTime">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Applies To</label>
            <select class="form-select form-select-sm" id="cpProgApplyTo">
              <option value="both">Students &amp; Staff</option>
              <option value="students">Students only</option>
              <option value="staff">Staff only</option>
            </select>
          </div>
          <div class="col-md-3">
            <div class="form-check form-switch mt-4">
              <input class="form-check-input" type="checkbox" id="cpProgSabbath">
              <label class="form-check-label small" for="cpProgSabbath">Sabbath program</label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-check form-switch mt-4">
              <input class="form-check-input" type="checkbox" id="cpProgActive" checked>
              <label class="form-check-label small" for="cpProgActive">Active</label>
            </div>
          </div>
          <div class="col-md-9 d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" id="cpProgSave"><i class="bi bi-check me-1"></i>Save Program</button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="cpProgCancel">Cancel Edit</button>
          </div>
        </form>
        <div class="table-responsive" style="max-height:52vh">
          <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light small">
              <tr><th style="width:34px"></th><th>Program</th><th>Description</th><th>Day</th><th>Time</th><th>Applies to</th><th style="width:84px">Status</th><th style="width:110px"></th></tr>
            </thead>
            <tbody id="cpProgList"><tr><td colspan="8" class="text-center text-muted py-3">Loading…</td></tr></tbody>
          </table>
        </div>
        <p class="text-muted small mt-2 mb-0">
          Deleting a program that already has scheduled sessions <strong>deactivates</strong> it instead, so past records are preserved.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Schedule Session Modal -->
<div class="modal fade" id="cpSessionModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Schedule Spiritual Session</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="cpSessionForm">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Program*</label>
              <select class="form-select" id="cpProgSel" required></select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Date*</label>
              <input type="date" class="form-control" id="cpDate" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Theme / Title*</label>
            <input type="text" class="form-control" id="cpTitle" required placeholder="e.g. Sabbath Worship — Week 1">
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Start Time</label>
              <input type="time" class="form-control" id="cpStart">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">End Time</label>
              <input type="time" class="form-control" id="cpEnd">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Location</label>
              <input type="text" class="form-control" id="cpLocation">
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Sabbath School Class (if any)</label>
              <input type="text" class="form-control" id="cpClass" placeholder="e.g. Grade 5 A">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Speaker / Preacher</label>
              <input type="text" class="form-control" id="cpSpeaker">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Worship Leader</label>
              <input type="text" class="form-control" id="cpWorship">
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Music Director</label>
              <input type="text" class="form-control" id="cpMusic">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Expected Attendance</label>
              <input type="number" class="form-control" id="cpExpected" min="0">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Status</label>
              <select class="form-select" id="cpStatus"><option value="scheduled">Scheduled</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea class="form-control" id="cpNotes" rows="2"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="cpSave">Save Session</button>
      </div>
    </div>
  </div>
</div>

<!-- Attendance Modal -->
<div class="modal fade" id="cpAttModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Record Attendance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3"><strong id="cpAttTitle"></strong> — <span id="cpAttDate"></span></p>
        <div class="mb-3 d-flex gap-2 flex-wrap">
          <button class="btn btn-sm btn-outline-secondary" id="cpAttAddStudent"><i class="bi bi-person-plus"></i> Add Student</button>
          <button class="btn btn-sm btn-outline-secondary" id="cpAttAddStaff"><i class="bi bi-person-plus"></i> Add Staff</button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light"><tr><th style="width:40px">Present</th><th>Attendee</th><th>Type</th></tr></thead>
            <tbody id="cpAttRows"><tr><td colspan="3" class="text-center text-muted py-3">No attendance recorded yet.</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="cpAttSave">Save Attendance</button>
      </div>
    </div>
  </div>
</div>

<?php asset_script($appBase, 'js/pages/chaplaincy_programs.js'); ?>
