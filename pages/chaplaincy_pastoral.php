<?php
/* Chaplaincy Pastoral — recurring spiritual groups, confidential learner
   spiritual profiles & milestones, and the pastoral care visit log.
   Sensitive data is served only to authorized pastoral roles (see controller). */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-heart-pulse text-primary me-2"></i>Spiritual Groups &amp; Pastoral Care</h4>
      <p class="text-muted small mb-0 mt-1">
        Recurring discipleship groups (Pathfinders, Adventurers, AY, Choir, small-group Bible study),
        confidential learner spiritual profiles &amp; milestones, and the pastoral visitation log.
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1">Confidential</span>
      </p>
    </div>
    <button class="btn btn-outline-primary btn-sm" id="cpastRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="border-bottom d-flex flex-wrap">
      <ul class="nav nav-tabs border-0 mb-0 flex-nowrap overflow-auto" style="min-width:100%">
        <li class="nav-item"><button class="nav-link cpast-tab active" data-cpast-tab="groups">Groups</button></li>
        <li class="nav-item"><button class="nav-link cpast-tab" data-cpast-tab="profiles">Learner Spiritual Profiles</button></li>
        <li class="nav-item"><button class="nav-link cpast-tab" data-cpast-tab="visits">Pastoral Visits</button></li>
      </ul>
    </div>

    <!-- ===== Groups tab ===== -->
    <div class="p-3 cpast-pane" id="cpastPane-groups">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="fw-semibold small text-uppercase text-muted">Recurring Spiritual Groups</span>
        <button class="btn btn-primary btn-sm" id="cpastAddGroup"><i class="bi bi-plus-lg me-1"></i>New Group</button>
      </div>
      <div class="row g-3" id="cpastGroups"><div class="col-12 text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</div></div>
    </div>

    <!-- ===== Profiles tab ===== -->
    <div class="p-3 cpast-pane d-none" id="cpastPane-profiles">
      <div class="row g-3">
        <div class="col-lg-4">
          <div class="input-group input-group-sm mb-3">
            <input type="text" class="form-control" id="cpastStudentSearch" placeholder="Search learner by admission no / name / ID">
            <button class="btn btn-outline-secondary" id="cpastStudentSearchBtn"><i class="bi bi-search"></i></button>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light"><tr><th>Learner</th><th>Adm No</th></tr></thead>
              <tbody id="cpastStudentResults"><tr><td colspan="2" class="text-center text-muted py-3">Type a name or admission number to search.</td></tr></tbody>
            </table>
          </div>
        </div>
        <div class="col-lg-8">
          <div id="cpastProfileView">
            <div class="text-center text-muted py-5">
              <i class="bi bi-person-badge display-6 d-block mb-2"></i>
              Select a learner to view / edit their confidential spiritual profile.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Visits tab ===== -->
    <div class="p-3 cpast-pane d-none" id="cpastPane-visits">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="fw-semibold small text-uppercase text-muted">Pastoral Care Visits</span>
        <button class="btn btn-primary btn-sm" id="cpastAddVisit"><i class="bi bi-plus-lg me-1"></i>Record Visit</button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr>
            <th>Date</th><th>Subject</th><th>Type</th><th>Purpose</th><th>Next Action</th><th>Status</th><th>Conducted By</th><th></th>
          </tr></thead>
          <tbody id="cpastVisits"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ===== New Group Modal ===== -->
<div class="modal fade" id="cpastGroupModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Spiritual Group</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="cpastGroupForm">
          <div class="mb-3">
            <label class="form-label">Name*</label>
            <input type="text" class="form-control" id="cpastGrpName" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Group Type*</label>
            <select class="form-select" id="cpastGrpType">
              <option value="pathfinders">Pathfinders</option>
              <option value="adventurers">Adventurers</option>
              <option value="ay">Adventist Youth (AY)</option>
              <option value="choir">Choir</option>
              <option value="bible_study">Bible Study Group</option>
              <option value="prayer">Prayer</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Meeting Day</label>
              <select class="form-select" id="cpastGrpDay">
                <option value="">—</option>
                <option value="MONDAY" selected>Monday</option><option value="TUESDAY">Tuesday</option>
                <option value="WEDNESDAY">Wednesday</option><option value="THURSDAY">Thursday</option>
                <option value="FRIDAY">Friday</option><option value="SATURDAY" >Saturday</option>
                <option value="SUNDAY">Sunday</option>
              </select>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Meeting Time</label>
              <input type="time" class="form-control" id="cpastGrpTime">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Meeting Location</label>
            <input type="text" class="form-control" id="cpastGrpLoc">
          </div>
          <div class="mb-3">
            <label class="form-label">Leader (Staff ID)</label>
            <input type="text" class="form-control" id="cpastGrpLeader" placeholder="Staff ID">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="cpastGrpDesc" rows="2"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="cpastSaveGroup">Save Group</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Group Detail / Attendance Modal ===== -->
<div class="modal fade" id="cpastGroupDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cpastGrpTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex gap-2 flex-wrap mb-3">
          <input type="text" class="form-control form-control-sm" id="cpastAddMemberInput" style="max-width:180px" placeholder="Student/Staff ID">
          <select class="form-select form-select-sm" id="cpastAddMemberType" style="max-width:110px">
            <option value="student">Student</option><option value="staff">Staff</option>
          </select>
          <button class="btn btn-sm btn-outline-primary" id="cpastAddMemberBtn"><i class="bi bi-person-plus"></i> Add</button>
        </div>
        <div class="mb-3"><label class="form-label small">Members</label>
          <div class="table-responsive tall-scroll">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr><th>Name</th><th>Type</th><th>Status</th><th></th></tr></thead>
              <tbody id="cpastGrpMembers"></tbody>
            </table>
          </div>
        </div>
        <hr>
        <div class="d-flex align-items-center gap-2 mb-2">
          <label class="form-label small mb-0 fw-semibold">Attendance — </label>
          <input type="date" class="form-control form-control-sm" id="cpastAttDate" style="max-width:170px">
          <button class="btn btn-sm btn-outline-secondary" id="cpastGroupLoadAtt"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
        <div class="table-responsive tall-scroll">
          <table class="table table-sm align-middle">
            <thead class="table-light"><tr><th style="width:40px">✓</th><th>Name</th><th>Type</th></tr></thead>
            <tbody id="cpastGroupAtt"></tbody>
          </table>
        </div>
        <button class="btn btn-sm btn-primary mt-2" id="cpastSaveGroupAtt"><i class="bi bi-check2"></i> Save Attendance</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Pastoral Visit Modal ===== -->
<div class="modal fade" id="cpastVisitModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Record Pastoral Visit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="cpastVisitForm">
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Self-subject</label>
              <select class="form-select" id="cpastVisitSubjectType"><option value="student">Student</option><option value="staff">Staff</option></select>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Visit Date*</label>
              <input type="date" class="form-control" id="cpastVisitDate" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Care Subject ID*</label>
            <input type="number" class="form-control" id="cpastVisitSubjectId" min="1" required placeholder="Student/Staff ID">
          </div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Visit Type</label>
              <select class="form-select" id="cpastVisitType">
                <option value="home">Home</option><option value="campus">Campus</option>
                <option value="hospital">Hospital</option><option value="phone">Phone</option><option value="other">Other</option>
              </select>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Next Action Date</label>
              <input type="date" class="form-control" id="cpastVisitNext">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Purpose*</label>
            <textarea class="form-control" id="cpastVisitPurpose" rows="2" required></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Follow-up Notes</label>
            <textarea class="form-control" id="cpastVisitNotes" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" id="cpastVisitStatus"><option value="open">Open</option><option value="followup">Follow-up</option><option value="closed">Closed</option></select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="cpastSaveVisit">Save Visit</button>
      </div>
    </div>
  </div>
</div>

<?php asset_script($appBase, 'js/pages/chaplaincy_pastoral.js'); ?>
