<?php
/* Activity Participants — participant registration for activities. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-person-plus text-success me-2"></i>Activity Participants</h4>
      <p class="text-muted small mb-0 mt-1">Register students for activities and manage participation.</p>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="bg-white border rounded-3 p-4">
        <h6 class="fw-semibold mb-3"><i class="bi bi-person-plus-fill text-success me-2"></i>Register Participant</h6>
        <form id="apForm" data-kw-onsubmit="activityParticipantsController.save(event)">
          <div class="mb-3">
            <label class="form-label">Activity *</label>
            <select class="form-select" id="apActivity" required></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Student *</label>
            <select class="form-select" id="apStudent" required></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <input type="text" class="form-control" id="apRole" placeholder="e.g. Member, Captain">
          </div>
          <button class="btn btn-success w-100" id="apBtn" type="submit" data-year-write><i class="bi bi-check-lg me-1"></i>Register</button>
        </form>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="d-flex gap-2 mb-3 align-items-center">
        <select class="form-select form-select-sm" id="apFilterActivity" style="max-width: 240px;">
          <option value="">All activities</option>
        </select>
        <select class="form-select form-select-sm" id="apFilterStatus" style="max-width: 160px;">
          <option value="">All statuses</option>
          <option>active</option><option>pending</option><option>withdrawn</option>
        </select>
        <button class="btn btn-outline-success btn-sm" data-kw-onclick="activityParticipantsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
      </div>
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
              <th>Adm No</th><th>Student</th><th>Class</th><th>Activity</th><th>Role</th><th>Status</th><th></th>
            </tr></thead>
            <tbody id="apBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/activity_participants.js'); ?>
