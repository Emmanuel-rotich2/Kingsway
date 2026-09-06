<?php
/* Job Applications — standalone page (split from Website Management). */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.ws-stat-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;display:flex;align-items:center;gap:16px; }
.ws-stat-icon { width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
.ws-table td,.ws-table th { vertical-align:middle;font-size:.85rem; }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-inbox-fill text-success me-2"></i>Job Applications</h4>
      <p class="text-muted small mb-0 mt-1">Review applications received through the public Careers page.</p>
    </div>
    <a href="<?= $appBase ?>/manage_job_vacancies" class="btn btn-sm btn-outline-success rounded-pill px-3">
      <i class="bi bi-briefcase me-1"></i>Manage Vacancies
    </a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#fce4ec"><i class="bi bi-inbox-fill text-danger"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statApps">—</div><div class="text-muted small">Applications</div></div>
    </div></div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
      <select id="appStatusFilter" class="form-select form-select-sm" style="width:180px">
        <option value="">All statuses</option>
        <option value="received">Received</option>
        <option value="shortlisted">Shortlisted</option>
        <option value="interview_scheduled">Interview Scheduled</option>
        <option value="interviewed">Interviewed</option>
        <option value="hired">Hired</option>
        <option value="rejected">Rejected</option>
      </select>
      <input type="text" id="appSearch" class="form-control form-control-sm" placeholder="Search name / position…" style="width:220px">
    </div>
    <button class="btn btn-outline-success btn-sm" onclick="jobAppsController.loadData()">
      <i class="bi bi-arrow-clockwise"></i>
    </button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <table class="table table-hover ws-table mb-0">
      <thead class="table-light"><tr>
        <th scope="col">Name</th><th scope="col">Position</th><th scope="col">Email</th><th scope="col">Phone</th><th scope="col">TSC No.</th><th scope="col">Status</th><th scope="col">Date</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody id="jobAppsTableBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
    </table>
  </div>

</div>

<div class="modal fade" id="jobInterviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <form id="jobInterviewForm">
        <div class="modal-header"><h5 class="modal-title" id="jobInterviewModalTitle">Schedule interview</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <input type="hidden" id="jobInterviewApplicationId">
          <input type="hidden" id="jobInterviewId">
          <div class="mb-3" id="jobInterviewScheduleFields">
            <label class="form-label">Date and time *</label>
            <input class="form-control" type="datetime-local" id="jobInterviewScheduledAt">
            <div class="row g-2 mt-1"><div class="col-6"><label class="form-label">Mode</label><select class="form-select" id="jobInterviewMode"><option value="in_person">In person</option><option value="online">Online</option><option value="phone">Phone</option></select></div><div class="col-6"><label class="form-label">Location / link</label><input class="form-control" id="jobInterviewLocation"></div></div>
          </div>
          <div class="mb-3 d-none" id="jobInterviewCompletionFields"><label class="form-label">Score (optional)</label><input class="form-control" type="number" min="0" max="100" id="jobInterviewScore"><label class="form-label mt-3">Interview notes</label><textarea class="form-control" rows="4" id="jobInterviewNotes"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" type="submit">Save</button></div>
      </form>
    </div>
  </div>
</div>

<?php asset_script($appBase, 'js/pages/manage_job_applications.js'); ?>
