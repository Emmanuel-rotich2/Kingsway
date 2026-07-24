<?php /** Canonical staff lifecycle workspace. */ ?>
<div class="container-fluid py-4" id="staffLifecycleApp">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><h2 class="mb-1">Staff Lifecycle Management</h2><p class="text-muted mb-0">Manage appointments, promotions, demotions, transfers, contract changes, suspension, reinstatement and exits.</p></div>
    <button class="btn btn-primary" id="newLifecycleAction"><i class="fas fa-plus me-2"></i>New staff action</button>
  </div>
  <div id="lifecycleAlert"></div>
  <div class="row g-3 mb-4" id="lifecycleSummary"></div>
  <div class="card border-0 shadow-sm"><div class="card-body">
    <div class="row g-2 mb-3"><div class="col-md-4"><input class="form-control" id="staffSearch" placeholder="Search staff number, name or position"></div><div class="col-md-3"><select class="form-select" id="staffStatus"><option value="">All statuses</option><option>active</option><option>suspended</option><option>resigned</option><option>retired</option><option>terminated</option></select></div><div class="col-md-2"><button class="btn btn-outline-secondary w-100" id="refreshLifecycle">Refresh</button></div></div>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Staff</th><th>Position</th><th>Department</th><th>Status</th><th>Onboarding</th><th></th></tr></thead><tbody id="staffLifecycleRows"></tbody></table></div>
    <div id="staffLifecycleEmpty" class="text-center text-muted py-5 d-none">No staff records match the current filters.</div>
  </div></div>
</div>
<div class="modal fade" id="lifecycleActionModal" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" id="lifecycleActionForm"><div class="modal-header"><h5 class="modal-title">Create staff lifecycle action</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
<div class="col-md-6"><label class="form-label">Staff member</label><select class="form-select" name="staff_id" required></select></div>
<div class="col-md-6"><label class="form-label">Action</label><select class="form-select" name="action_type" required></select></div>
<div class="col-md-6"><label class="form-label">Effective date</label><input type="date" class="form-control" name="effective_date" required></div>
<div class="col-md-6"><label class="form-label">New position</label><input class="form-control" name="to_position"></div>
<div class="col-md-6"><label class="form-label">New department</label><select class="form-select" name="to_department_id"><option value="">Keep current</option></select></div>
<div class="col-md-6"><label class="form-label">New salary</label><input type="number" min="0" step="0.01" class="form-control" name="to_salary"></div>
<div class="col-12"><label class="form-label">Reason</label><textarea class="form-control" name="reason" rows="3" required></textarea></div>
<div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
</div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Submit for approval</button></div></form></div></div>
<div class="modal fade" id="staffTimelineModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Staff career timeline</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="staffTimelineBody"></div></div></div></div>
<script src="<?= $appBase ?>/js/pages/staff_lifecycle.js?v=<?= time() ?>"></script>
