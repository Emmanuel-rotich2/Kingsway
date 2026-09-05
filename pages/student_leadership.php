<?php
/* Student Leadership & Participation — leadership, houses and awards.
   Thin HTML shell; all logic in js/pages/student_leadership.js */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>
<style>
.lead-wrap { font-family: var(--bs-body-font-family); }
.lead-hero {
  background: linear-gradient(135deg, #1a2980 0%, #26d0ce 100%);
  color: #fff; border-radius: 12px; padding: 1.3rem 1.6rem;
  margin-bottom: 1.2rem; box-shadow: 0 4px 16px rgba(26,41,128,.22);
}
.lead-hero h4 { font-weight: 700; margin: 0 0 .15rem; }
.lead-hero small { opacity: .88; }
.lead-card { background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.06); }
.lead-card .card-header {
  background:#f8f9fb; font-weight:600; border-bottom:1px solid #eee;
  border-radius:12px 12px 0 0 !important; padding:.8rem 1rem;
}
.per-term-badge { font-size:.72rem; }
.mini-stat { background:#fff; border-radius:10px; border-top:4px solid #1a2980; padding:.7rem .9rem; box-shadow:0 2px 8px rgba(0,0,0,.05); }
.mini-stat .v { font-size:1.5rem; font-weight:700; color:#1a2980; }
.mini-stat .l { font-size:.72rem; color:#666; }
.house-chip { display:inline-flex; align-items:center; gap:.35rem; padding:.25rem .6rem; border-radius:999px; font-size:.78rem; font-weight:600; color:#fff; }
.empty-state { text-align:center; color:#999; padding:2rem 1rem; }
.lead-directory-card { transition:.15s ease; }
.lead-directory-card:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(26,41,128,.12) !important; }
</style>

<div class="container-fluid px-4 py-4 lead-wrap">
  <div class="lead-hero">
    <h4><i class="fas fa-medal me-2"></i>Student Leadership &amp; Participation</h4>
    <small>Per-term leadership, service/office, houses and awards — the single governed record that feeds the student portfolio and certificates.</small>
  </div>

  <div class="alert alert-info border-0 d-none" id="leadReadonlyBanner" role="alert">
    <i class="fas fa-eye me-1"></i><strong>Read-only view.</strong> Student leadership is managed by the School Administrator, Headteacher and Deputies. Contact them to record or change leadership, houses or awards.
  </div>

  <div class="row g-3 mb-3" id="leadKpis"></div>

  <ul class="nav nav-tabs mb-3" id="leadTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-leadership-tab" data-bs-toggle="tab" data-bs-target="#tab-leadership" type="button" role="tab">Leadership &amp; Offices</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-houses-tab" data-bs-toggle="tab" data-bs-target="#tab-houses" type="button" role="tab">Houses</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-awards-tab" data-bs-toggle="tab" data-bs-target="#tab-awards" type="button" role="tab">Awards &amp; Certificates</button>
    </li>
  </ul>

  <div class="tab-content">
    <!-- ================= LEADERSHIP ================= -->
    <div class="tab-pane fade show active" id="tab-leadership" role="tabpanel">
      <div class="lead-card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
          <span><i class="fas fa-gavel me-2"></i>Leadership Assignments</span>
          <button class="btn btn-primary btn-sm lead-manage-only" onclick="StudentLeadershipController.openAssign()"><i class="fas fa-plus me-1"></i>Assign Position</button>
        </div>
        <div class="p-3">
          <div class="row g-2 mb-2">
            <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Academic Year</label>
              <select id="leadYearFilter" class="form-select form-select-sm"></select></div>
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Category</label>
              <select id="leadCatFilter" class="form-select form-select-sm"></select></div>
            <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Student</label>
              <select id="leadStudentFilter" class="form-select form-select-sm"><option value="">All students</option></select></div>
            <div class="col-md-5 d-flex align-items-end justify-content-end">
              <button class="btn btn-outline-secondary btn-sm me-2" onclick="StudentLeadershipController.loadLeadership()"><i class="fas fa-search me-1"></i>Filter</button>
              <button class="btn btn-outline-secondary btn-sm" onclick="StudentLeadershipController.resetLeadershipFilters()">Reset</button>
            </div>
          </div>
          <div class="row g-3 d-none" id="leadershipDirectory"></div>
          <div class="table-responsive" style="max-height:460px;overflow:auto;">
            <table class="table table-sm table-hover align-middle">
              <thead class="table-light"><tr>
                <th>Student</th><th>Adm No.</th><th>Position</th><th>Category</th><th>Year / Term</th><th>House</th><th>Class</th><th>Status</th><th></th>
              </tr></thead>
              <tbody id="leadershipTable"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ================= HOUSES ================= -->
    <div class="tab-pane fade" id="tab-houses" role="tabpanel">
      <div class="lead-card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
          <span><i class="fas fa-home me-2"></i>School Houses</span>
          <button class="btn btn-primary btn-sm mb-1 lead-manage-only" onclick="StudentLeadershipController.openHouse()"><i class="fas fa-plus me-1"></i>Add House</button>
        </div>
        <div class="p-3">
          <div class="row g-3" id="housesGrid"></div>
        </div>
      </div>
    </div>

    <!-- ================= AWARDS ================= -->
    <div class="tab-pane fade" id="tab-awards" role="tabpanel">
      <div class="lead-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
          <span><i class="fas fa-trophy me-2"></i>Awards &amp; Certificates</span>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm lead-manage-only" onclick="StudentLeadershipController.openAwardTypes()"><i class="fas fa-edit me-1"></i>Manage Award Types</button>
            <button class="btn btn-primary btn-sm lead-manage-only" onclick="StudentLeadershipController.openAward()"><i class="fas fa-plus me-1"></i>Issue Award</button>
          </div>
        </div>
        <div class="p-3">
          <div class="row g-2 mb-2">
            <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Category</label>
              <select id="awardCatFilter" class="form-select form-select-sm"></select></div>
            <div class="col-md-9 d-flex align-items-end justify-content-end">
              <button class="btn btn-outline-secondary btn-sm" onclick="StudentLeadershipController.loadAwards()"><i class="fas fa-search me-1"></i>Filter</button>
            </div>
          </div>
          <div class="table-responsive" style="max-height:460px;overflow:auto;">
            <table class="table table-sm table-hover align-middle">
              <thead class="table-light"><tr>
                <th>Student</th><th>Adm No.</th><th>Award</th><th>Type</th><th>Year / Term</th><th>Cert. No.</th><th>Issue Date</th><th>Status</th><th id="awardActionsTh"></th>
              </tr></thead>
              <tbody id="awardsTable"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============ ASSIGN LEADERSHIP MODAL ============ -->
<div class="modal fade" id="assignLeadershipModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assignLeadershipTitle">Assign Leadership Position</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Student</label>
            <select id="leadStudent" class="form-select form-select-sm"></select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Position</label>
            <select id="leadPosition" class="form-select form-select-sm">
              <option value="">Select position...</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Category</label>
            <select id="leadCategory" class="form-select form-select-sm"></select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">House (if applicable)</label>
            <select id="leadHouse" class="form-select form-select-sm"><option value="">— None —</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Academic Year</label>
            <select id="leadYear" class="form-select form-select-sm"></select>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Term</label>
            <select id="leadTerm" class="form-select form-select-sm"><option value="">All year</option></select>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Start Date</label>
            <input type="date" id="leadStart" class="form-control form-control-sm" value="">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Public Bio / Notes</label>
            <textarea id="leadBio" class="form-control form-control-sm" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="StudentLeadershipController.saveLeadership()">Save Assignment</button>
      </div>
    </div>
  </div>
</div>

<!-- ============ HOUSE MODAL ============ -->
<div class="modal fade" id="houseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="houseModalTitle">Add House</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-6"><label class="form-label small fw-semibold">Name</label>
            <input type="text" id="houseName" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-semibold">Code</label>
            <input type="text" id="houseCode" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-semibold">Color</label>
            <input type="text" id="houseColor" class="form-control form-control-sm" placeholder="e.g. red"></div>
          <div class="col-6"><label class="form-label small fw-semibold">Mascot</label>
            <input type="text" id="houseMascot" class="form-control form-control-sm"></div>
          <div class="col-12"><label class="form-label small fw-semibold">Motto</label>
            <input type="text" id="houseMotto" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label small fw-semibold">Thread Title</label>
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" id="houseActive" checked>
              <label class="form-check-label small" for="houseActive">Active</label>
            </div>
          </div>
          <div class="col-6"><label class="form-label small fw-semibold">Display Order</label>
            <input type="number" id="houseOrder" class="form-control form-control-sm" value="0"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="StudentLeadershipController.saveHouse()">Save House</button>
      </div>
    </div>
  </div>
</div>

<!-- ============ AWARD MODAL ============ -->
<div class="modal fade" id="awardModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="awardModalTitle">Issue Award</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Student</label>
            <select id="awardStudent" class="form-select form-select-sm"></select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Category</label>
            <select id="awardCategory" class="form-select form-select-sm" onchange="StudentLeadershipController.fillAwardTypes()">
              <option value="">Select category...</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Award Type</label>
            <select id="awardType" class="form-select form-select-sm">
              <option value="">Select type...</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Title</label>
            <input type="text" id="awardTitle" class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Certificate No. (auto on print)</label>
            <input type="text" id="awardCertNo" class="form-control form-control-sm" readonly placeholder="Assigned when printed">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Issue Date</label>
            <input type="date" id="awardIssueDate" class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Status</label>
            <select id="awardStatus" class="form-select form-select-sm">
              <option value="awarded">Awarded</option>
              <option value="revoked">Revoked</option>
              <option value="expired">Expired</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Description / Achievement</label>
            <textarea id="awardDescription" class="form-control form-control-sm" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="StudentLeadershipController.saveAward()">Save Award</button>
      </div>
    </div>
  </div>
</div>

<!-- ============ AWARD TYPE MANAGEMENT MODAL ============ -->
<div class="modal fade" id="awardTypesModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-tags me-2"></i>Manage Award Types</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3" id="awardTypesSeedRow">
          <div class="col-md-3"><label class="form-label small fw-semibold">Category</label>
            <select id="atCategory" class="form-select form-select-sm"></select></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Department</label>
            <select id="atDepartment" class="form-select form-select-sm"><option value="">School-wide</option></select></div>
          <div class="col-md-2"><label class="form-label small fw-semibold">Code</label>
            <input type="text" id="atCode" class="form-control form-control-sm" maxlength="60" placeholder="e.g. HNR-EXC"></div>
          <div class="col-md-2"><label class="form-label small fw-semibold">Name</label>
            <input type="text" id="atName" class="form-control form-control-sm"></div>
          <div class="col-md-2"><label class="form-label small fw-semibold">Template</label>
            <select id="atTemplate" class="form-select form-select-sm"></select></div>
          <div class="col-md-2"><label class="form-label small fw-semibold">Prefix</label>
            <input type="text" id="atPrefix" class="form-control form-control-sm" maxlength="8" placeholder="e.g. HNR"></div>
          <div class="col-md-10"><label class="form-label small fw-semibold">Description</label>
            <input type="text" id="atDescription" class="form-control form-control-sm"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Primary Signatory (left)</label>
            <input type="text" id="atSignatory" class="form-control form-control-sm" placeholder="e.g. Academic Officer"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Secondary Signatory (right)</label>
            <input type="text" id="atSignatory2" class="form-control form-control-sm" placeholder="e.g. Headteacher"></div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary btn-sm" onclick="StudentLeadershipController.saveAwardType()"><i class="fas fa-save me-1"></i>Save Type</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="StudentLeadershipController.resetAwardTypeForm()">Reset</button>
          </div>
        </div>
        <div class="table-responsive" style="max-height:340px;overflow:auto;">
          <table class="table table-sm table-striped align-middle">
            <thead class="table-light"><tr><th>Code</th><th>Name</th><th>Category</th><th>Department</th><th>Template</th><th>Prefix</th><th>Signatories</th><th class="text-end">Actions</th></tr></thead>
            <tbody id="awardTypesTable"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php asset_script($appBase, 'js/pages/student_leadership.js'); ?>
