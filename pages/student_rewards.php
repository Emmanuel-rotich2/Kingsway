<?php
/**
 * Student Rewards & Recognition — PARTIAL
 * Record and display student awards, merit points, certificates, and achievements.
 * JS controller: js/pages/student_rewards.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-trophy me-2 text-warning"></i>Student Rewards &amp; Recognition</h2>
      <small class="text-muted">Merit points · Certificates · Achievements · Special recognition</small>
    </div>
    <button class="btn btn-primary" onclick="studentRewardsController.showAwardModal()">
      <i class="bi bi-plus-circle me-1"></i> Add Award
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="srStatTotal">—</div>
          <div class="text-muted small">Total Awards This Term</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="srStatPoints">—</div>
          <div class="text-muted small">Merit Points Issued</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="srStatCerts">—</div>
          <div class="text-muted small">Certificates</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="srStatStudents">—</div>
          <div class="text-muted small">Students Recognised</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter Tabs + Actions -->
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <ul class="nav nav-tabs border-0" id="srTabs">
      <li class="nav-item">
        <button class="nav-link active" data-filter="all" onclick="studentRewardsController.filterByType('all', this)">All</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-filter="Merit Point" onclick="studentRewardsController.filterByType('Merit Point', this)">Merit Points</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-filter="Certificate" onclick="studentRewardsController.filterByType('Certificate', this)">Certificates</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-filter="Trophy" onclick="studentRewardsController.filterByType('Trophy', this)">Trophies/Cups</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-filter="Special Recognition" onclick="studentRewardsController.filterByType('Special Recognition', this)">Special Recognition</button>
      </li>
    </ul>
    <button class="btn btn-outline-secondary btn-sm" onclick="studentRewardsController.exportCSV()">
      <i class="bi bi-download me-1"></i> Export CSV
    </button>
  </div>

  <!-- Awards Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Student</th>
              <th>Class</th>
              <th>Award Type</th>
              <th>Description</th>
              <th>Awarded By</th>
              <th>Date</th>
              <th class="text-center">Points</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="srTableBody">
            <tr>
              <td colspan="8" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <span class="ms-2 text-muted">Loading awards…</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ADD / EDIT AWARD MODAL -->
<div class="modal fade" id="srModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="srModalTitle">Add Award</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="srEditId">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
            <select id="srStudentId" class="form-select">
              <option value="">— Select student —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Award Type <span class="text-danger">*</span></label>
            <select id="srType" class="form-select" onchange="studentRewardsController.onTypeChange()">
              <option value="">— Select type —</option>
              <option value="Merit Point">Merit Point</option>
              <option value="Certificate">Certificate</option>
              <option value="Trophy">Trophy/Cup</option>
              <option value="Special Recognition">Special Recognition</option>
              <option value="Academic Excellence">Academic Excellence</option>
              <option value="Behaviour">Behaviour</option>
            </select>
          </div>
          <div class="col-md-6" id="srPointsGroup">
            <label class="form-label fw-semibold">Points</label>
            <input type="number" id="srPoints" class="form-control" min="1" max="100" value="1" placeholder="e.g. 5">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
            <textarea id="srDesc" class="form-control" rows="3" placeholder="Describe the achievement or reason for this award…"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Date Awarded <span class="text-danger">*</span></label>
            <input type="date" id="srDate" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Awarded By</label>
            <input type="text" id="srAwardedBy" class="form-control" placeholder="Teacher / staff name">
          </div>
        </div>
        <div id="srError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="studentRewardsController.saveReward()">Save Award</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>js/pages/student_rewards.js?v=<?= time() ?>"></script>
<script>document.addEventListener('DOMContentLoaded', () => studentRewardsController.init());</script>
