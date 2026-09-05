<?php
/* Chaplaincy Department — team roster, borrowed members and volunteers. */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <style>
    .chd-person-picker { max-height: 320px; overflow-y: auto; background: var(--bs-body-bg, #fff); }
    .chd-person-option { cursor: pointer; }
    .chd-person-option:hover { background: var(--bs-tertiary-bg, #f5f6f7); }
    .chd-person-option.is-checked { background: var(--bs-primary-bg-subtle, #e7f1ff); }
    .chd-stage-strip { background: var(--bs-tertiary-bg, #f5f6f7); }
    #chdMemberModal .modal-body { max-height: 70vh; overflow-y: auto; }
  </style>

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-church text-primary me-2"></i>Chaplaincy Department</h4>
      <p class="text-muted small mb-0 mt-1">
        Spiritual leadership &amp; campus ministry (SDA). The Chaplain heads the department;
        members are borrowed from teaching, administration and parents.
      </p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-primary btn-sm" id="chdRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
      <button class="btn btn-primary btn-sm" id="chdAddMember"><i class="bi bi-person-plus me-1"></i>Assign Member</button>
      <button class="btn btn-outline-secondary btn-sm" id="chdAddVolunteer"><i class="bi bi-people me-1"></i>Add Volunteer</button>
    </div>
  </div>

  <!-- Department head card -->
  <div class="bg-white border rounded-3 p-3 mb-4 d-flex align-items-center gap-3">
    <div class="dash-stat dsc-indigo" style="flex:0 0 auto;margin:0">
      <i class="bi bi-person-badge dash-stat-icon"></i>
      <div class="dash-stat-value" id="chdHeadName">—</div>
      <div class="dash-stat-label">Department Head (Chaplain)</div>
      <div class="dash-stat-sub" id="chdHeadStaffNo"></div>
    </div>
    <div class="text-muted small">
      <div class="fw-semibold text-dark">Department code: <code>CHAP</code></div>
      <div>Team members lead Sabbath worship, Sabbath School, Vespers, AY, Pathfinders, choral &amp; pastoral care.</div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Team members -->
    <div class="col-lg-7">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">
          <i class="bi bi-people-fill me-1"></i>Team Members (borrowed staff)
          <span class="badge bg-soft text-dark ms-1" id="chdMemberCount">0</span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
              <th>Member</th><th>Staff No</th><th>Ministry Role</th><th>Home Dept</th><th>Effective</th><th></th>
            </tr></thead>
            <tbody id="chdMembers">
              <tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mt-4 bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">
          <i class="bi bi-grid-1x2 me-1"></i>Ministry Roles (Chaplains &amp; Team)
        </div>
        <div class="p-2" id="chdRoles"><div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div></div></div>
      </div>
    </div>

    <!-- Volunteers -->
    <div class="col-lg-5">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">
          <i class="bi bi-person-hearts me-1"></i>Parent &amp; Community Volunteers
          <span class="badge bg-soft text-dark ms-1" id="chdVolunteerCount">0</span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
              <th>Name</th><th>Role</th><th>Contact</th><th>Clearance</th><th></th>
            </tr></thead>
            <tbody id="chdVolunteers">
              <tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Assign Ministry Member Modal (tabbed multi-type staging) -->
<div class="modal fade" id="chdMemberModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-fullscreen-xl-down">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title"><i class="bi bi-people text-primary me-1"></i>Assign Ministry Member</h5>
          <p class="text-muted small mb-0">
            Select staff, parents and students across the tabs, assign each type a ministry role, then press
            <strong>Assign Selected</strong>. Roles come from the system registry (Department Head is appointed
            by the School Administrator, so it is not shown). Selections are staged locally until you submit.
          </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <!-- Staging summary strip -->
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 p-2 rounded-3 chd-stage-strip">
          <span class="small fw-semibold text-muted"><i class="bi bi-stack me-1"></i>Staged:</span>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="chdStageStaff">Staff 0</span>
          <span class="badge bg-success-subtle text-success border border-success-subtle" id="chdStageParents">Parents 0</span>
          <span class="badge bg-warning-subtle text-warning border border-warning-subtle" id="chdStageStudents">Students 0</span>
          <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="chdStageClear"><i class="bi bi-x-circle me-1"></i>Clear</button>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-pills nav-justified mb-3 gap-2" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="chdTabStaffBtn" data-bs-toggle="pill" data-bs-target="#chdTabStaff" type="button" role="tab"><i class="bi bi-person-badge me-1"></i>Staff</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="chdTabParentsBtn" data-bs-toggle="pill" data-bs-target="#chdTabParents" type="button" role="tab"><i class="bi bi-person-heart me-1"></i>Parents</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="chdTabStudentsBtn" data-bs-toggle="pill" data-bs-target="#chdTabStudents" type="button" role="tab"><i class="bi bi-mortarboard me-1"></i>Students</button>
          </li>
        </ul>

        <div class="tab-content">
          <!-- ============ STAFF ============ -->
          <div class="tab-pane fade show active" id="chdTabStaff" role="tabpanel">
            <div class="row g-2 align-items-end mb-2">
              <div class="col-md-7">
                <label class="form-label small mb-1">Filter staff</label>
                <input type="text" class="form-control" id="chdStaffFilter" placeholder="Name or staff no…" autocomplete="off">
              </div>
              <div class="col-md-5">
                <label class="form-label small mb-1">Ministry Role*</label>
                <select class="form-select" id="chdStaffRole"></select>
              </div>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-1">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="chdStaffAll">
                <label class="form-check-label small" for="chdStaffAll">Select all (filtered)</label>
              </div>
              <span class="small text-muted" id="chdStaffCount">0 shown</span>
            </div>
            <div class="border rounded-3 chd-person-picker" id="chdStaffList"><div class="text-center text-muted py-4">Loading staff…</div></div>
          </div>

          <!-- ============ PARENTS ============ -->
          <div class="tab-pane fade" id="chdTabParents" role="tabpanel">
            <div class="row g-2 align-items-end mb-2">
              <div class="col-md-7">
                <label class="form-label small mb-1">Filter parents</label>
                <input type="text" class="form-control" id="chdParentFilter" placeholder="Name or phone…" autocomplete="off">
              </div>
              <div class="col-md-5">
                <label class="form-label small mb-1">Ministry Role*</label>
                <select class="form-select" id="chdParentRole"></select>
              </div>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-1">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="chdParentAll">
                <label class="form-check-label small" for="chdParentAll">Select all (filtered)</label>
              </div>
              <span class="small text-muted" id="chdParentCount">0 shown</span>
            </div>
            <div class="border rounded-3 chd-person-picker" id="chdParentList"><div class="text-center text-muted py-4">Loading parents…</div></div>
          </div>

          <!-- ============ STUDENTS ============ -->
          <div class="tab-pane fade" id="chdTabStudents" role="tabpanel">
            <div class="row g-2 align-items-end mb-2">
              <div class="col-md-3">
                <label class="form-label small mb-1">School Level</label>
                <select class="form-select" id="chdStudentLevel"><option value="">All levels</option></select>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Grade / Class</label>
                <select class="form-select" id="chdStudentGrade"><option value="">All classes</option></select>
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">Student Type</label>
                <select class="form-select" id="chdStudentType"><option value="">All</option></select>
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1">Search</label>
                <input type="text" class="form-control" id="chdStudentFilter" placeholder="Name or admission no…" autocomplete="off">
              </div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-md-7">
                <label class="form-label small mb-1">Ministry Role*</label>
                <select class="form-select" id="chdStudentRole"></select>
                <div class="form-text">Students are recorded in the Leadership &amp; Participation register as spiritual ministry leaders; the role name is kept as note.</div>
              </div>
              <div class="col-md-2 align-self-end">
                <label class="form-label small mb-1">Academic Year</label>
                <select class="form-select" id="chdStudentYear"><option value="">Auto (from date)</option></select>
              </div>
              <div class="col-md-3 align-self-end">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" id="chdStudentAll">
                  <label class="form-check-label small" for="chdStudentAll">Select all (filtered)</label>
                </div>
              </div>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-1">
              <span class="small text-muted" id="chdStudentCount">0 shown</span>
            </div>
            <div class="border rounded-3 chd-person-picker" id="chdStudentList"><div class="text-center text-muted py-4">Loading students…</div></div>
          </div>
        </div>

        <!-- Shared effective dates -->
        <div class="row g-3 mt-1 border-top pt-3">
          <div class="col-md-3">
            <label class="form-label small mb-1">Effective From</label>
            <input type="date" class="form-control" id="chdMemberFrom">
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Effective To</label>
            <input type="date" class="form-control" id="chdMemberTo">
          </div>
          <div class="col-md-6 d-flex align-items-end justify-content-end">
            <span class="small text-muted" id="chdAssignSummary">Nothing staged yet</span>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="chdSaveMember"><i class="bi bi-check2-circle me-1"></i>Assign Selected</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Volunteer Modal -->
<div class="modal fade" id="chdVolunteerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Register Volunteer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="chdVolunteerForm">
          <div class="mb-3">
            <label class="form-label">Full Name*</label>
            <input type="text" class="form-control" id="chdVolName" required>
          </div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Phone</label>
              <input type="text" class="form-control" id="chdVolPhone">
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" id="chdVolEmail">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Ministry Role</label>
            <select class="form-select" id="chdVolRole"></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Linked Student ID</label>
            <input type="number" class="form-control" id="chdVolStudent" min="0">
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="chdVolClearance">
            <label class="form-check-label" for="chdVolClearance">Police clearance verified (safeguarding)</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea class="form-control" id="chdVolNotes" rows="2"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="chdSaveVolunteer">Register</button>
      </div>
    </div>
  </div>
</div>

<?php asset_script($appBase, 'js/pages/chaplaincy_department.js'); ?>
