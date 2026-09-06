<?php
/* Job Vacancies — standalone page (split from Website Management). */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.ws-stat-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;display:flex;align-items:center;gap:16px; }
.ws-stat-icon { width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
.ws-table td,.ws-table th { vertical-align:middle;font-size:.85rem; }
.ws-tag-chip { display:inline-block;padding:2px 8px;border-radius:12px;font-size:.72rem;font-weight:600;border:1px solid; }
.ws-form-group label { font-size:.82rem;font-weight:600;color:#374151;margin-bottom:4px;display:block; }
.ws-form-group input,.ws-form-group select,.ws-form-group textarea { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.88rem;outline:none;transition:border-color .15s; }
.ws-form-group input:focus,.ws-form-group select:focus,.ws-form-group textarea:focus { border-color:#198754;box-shadow:0 0 0 3px rgba(25,135,84,.1); }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-briefcase text-success me-2"></i>Job Vacancies</h4>
      <p class="text-muted small mb-0 mt-1">Post and manage the vacancies shown on the public Careers page.</p>
    </div>
    <a href="<?= $appBase ?>/" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3">
      <i class="bi bi-box-arrow-up-right me-1"></i>View Public Site
    </a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#fff8e1"><i class="bi bi-briefcase text-warning"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statJobs">—</div><div class="text-muted small">Open Vacancies</div></div>
    </div></div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0">Closing a vacancy removes it from the public site.</p>
    <button class="btn btn-success btn-sm rounded-pill px-3" onclick="vacanciesOpenModal()">
      <i class="bi bi-plus-lg me-1"></i>Post Vacancy
    </button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <table class="table table-hover ws-table mb-0">
      <thead class="table-light"><tr>
        <th scope="col">Title</th><th scope="col">Department</th><th scope="col">Type</th><th scope="col">Deadline</th><th scope="col">Status</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody id="jobsTableBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
    </table>
  </div>

</div>

<!-- Job Modal -->
<div class="modal fade" id="wsJobModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <h5 class="modal-title fw-bold" id="wsJobModalTitle">Post Vacancy</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="jobEditId">
      <div class="row g-3">
        <div class="col-md-8 ws-form-group"><label>Job Title *</label><input type="text" id="jobTitle" placeholder="e.g. Class Teacher — Grade 5"></div>
        <div class="col-md-4 ws-form-group">
          <label>Status</label>
          <select id="jobStatus"><option value="open">Open</option><option value="closed">Closed</option><option value="filled">Filled</option></select>
        </div>
        <div class="col-md-4 ws-form-group"><label>Department</label><input type="text" id="jobDepartment" placeholder="e.g. Teaching"></div>
        <div class="col-md-4 ws-form-group">
          <label>Job Type</label>
          <select id="jobType"><option value="Full-Time">Full-Time</option><option value="Part-Time">Part-Time</option><option value="Contract">Contract</option></select>
        </div>
        <div class="col-md-4 ws-form-group"><label>Deadline *</label><input type="date" id="jobDeadline"></div>
        <div class="col-12 ws-form-group"><label>Location</label><input type="text" id="jobLocation" value="Londiani, Kenya"></div>
        <div class="col-12 ws-form-group"><label>Description *</label><textarea id="jobDescription" rows="4" placeholder="Role description…"></textarea></div>
        <div class="col-12 ws-form-group"><label>Requirements (one per line)</label><textarea id="jobRequirements" rows="4" placeholder="P1 or B.Ed (Primary Education)&#10;TSC Registration&#10;2+ years experience"></textarea></div>
        <div class="col-12 ws-form-group"><label>Responsibilities (one per line)</label><textarea id="jobResponsibilities" rows="4" placeholder="Deliver CBC-aligned lessons&#10;Maintain class registers"></textarea></div>
      </div>
    </div>
    <div class="modal-footer border-0">
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-success btn-sm px-4" onclick="vacanciesSave()"><i class="bi bi-briefcase me-1"></i>Post Vacancy</button>
    </div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/manage_job_vacancies.js'); ?>
