<?php
/* Create Activity — create a new co-curricular activity. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-plus-circle text-primary me-2"></i>Create Activity</h4>
      <p class="text-muted small mb-0 mt-1">Create a new co-curricular activity.</p>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="bg-white border rounded-3 p-4">
        <form id="caForm" data-kw-onsubmit="createActivityController.save(event)">
          <div class="mb-3">
            <label class="form-label">Activity Title *</label>
            <input type="text" class="form-control" id="caTitle" required maxlength="255">
          </div>
          <div class="mb-3">
            <label class="form-label">Category *</label>
            <select class="form-select" id="caCategory" required></select>
          </div>
          <div class="row g-3">
            <div class="col-6 mb-3">
              <label class="form-label">Start Date *</label>
              <input type="date" class="form-control" id="caStart" required>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">End Date *</label>
              <input type="date" class="form-control" id="caEnd" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Max Participants</label>
            <input type="number" class="form-control" id="caMax" min="1">
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" id="caStatus">
              <option value="planned" selected>Planned</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="caDesc" rows="3" maxlength="2000"></textarea>
          </div>
          <button class="btn btn-primary w-100" id="caBtn" type="submit" data-year-write><i class="bi bi-check-lg me-1"></i>Create Activity</button>
        </form>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Recent Activities</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
              <th>Title</th><th>Category</th><th>Start</th><th>End</th><th>Participants</th><th>Status</th>
            </tr></thead>
            <tbody id="caRecent"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/create_activity.js'); ?>
