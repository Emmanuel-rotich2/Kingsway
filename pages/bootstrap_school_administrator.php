<?php /** System Administrator — invite a School Administrator. */ ?>
<div class="container-fluid py-4" id="schoolAdminBootstrapPage">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h2 class="h3 mb-1">Invite a School Administrator</h2><p class="text-muted mb-0">Create an account and send a secure setup link. You can invite more than one administrator.</p></div>
    <span class="badge text-bg-warning px-3 py-2" id="bootstrapAvailability">Checking availability…</span>
  </div>
  <div id="bootstrapAlert" class="alert alert-info" role="status">Loading invitation status…</div>
  <section class="card border-0 shadow-sm mb-4" aria-labelledby="schoolAdminInvitationsHeading">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div><h3 class="h5 mb-0" id="schoolAdminInvitationsHeading">School Administrator accounts and invitations</h3><span class="small text-muted" id="schoolAdminInvitationCount">Loading…</span></div>
      <div class="d-flex gap-2 no-print">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="exportSchoolAdminInvitations"><i class="bi bi-download me-1"></i>Export CSV</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="printSchoolAdminInvitations"><i class="bi bi-printer me-1"></i>Print</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="refreshSchoolAdminInvitations"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="schoolAdminInvitationsTable">
        <thead class="table-light"><tr><th scope="col">Administrator</th><th scope="col">Username</th><th scope="col">Department</th><th scope="col">Invitation</th><th scope="col">Account</th><th scope="col">Sent</th><th scope="col">Expires</th><th scope="col">Account created</th><th scope="col" class="no-print">Actions</th></tr></thead>
        <tbody id="schoolAdminInvitationsBody"><tr><td colspan="9" class="text-center text-muted py-4">Loading invitation history…</td></tr></tbody>
      </table>
    </div>
  </section>
  <form id="schoolAdminBootstrapForm" class="d-none">
    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Account details</strong></div><div class="card-body"><div class="row g-3">
      <div class="col-md-4"><label class="form-label">First name</label><input name="first_name" class="form-control" required maxlength="50" data-kw-validate="name"></div>
      <div class="col-md-4"><label class="form-label">Last name</label><input name="last_name" class="form-control" required maxlength="50" data-kw-validate="name"></div>
      <div class="col-md-4"><label class="form-label">Email address</label><input name="email" type="email" class="form-control" required autocomplete="email" data-kw-validate="email"></div>
      <div class="col-md-4"><label class="form-label">System role</label><input class="form-control" value="School Administrator" readonly><div class="form-text">This role is assigned to the account by the system.</div></div>
      <div class="col-md-8"><label class="form-label" for="bootstrapDepartment">Department</label><select name="department_id" id="bootstrapDepartment" class="form-select" required><option value="">Select department</option></select><div class="form-text">Assigned by you and locked for the invitee.</div></div>
      <div class="col-md-6"><label class="form-label" for="bootstrapPosition">Position / job title</label><input name="position" id="bootstrapPosition" class="form-control" value="School Administrator" required maxlength="100"><div class="form-text">The job title is an employment assignment; it is separate from the system role.</div></div>
      <div class="col-md-6"><label class="form-label" for="bootstrapEmploymentDate">Employment date</label><input name="employment_date" id="bootstrapEmploymentDate" type="date" class="form-control" required data-kw-validate="not_future"></div>
      <div class="col-md-4"><label class="form-label" for="bootstrapContractType">Contract type</label><select name="contract_type" id="bootstrapContractType" class="form-select" required><option value="">Select contract type</option><option value="permanent">Permanent</option><option value="contract">Contract</option><option value="temporary">Temporary</option></select></div>
      <div class="col-md-4"><label class="form-label" for="bootstrapStaffType">Staff type</label><select name="staff_type_id" id="bootstrapStaffType" class="form-select" required><option value="">Select staff type</option></select></div>
      <div class="col-md-4"><label class="form-label" for="bootstrapStaffCategory">Staff category</label><select name="staff_category_id" id="bootstrapStaffCategory" class="form-select" required><option value="">Select category</option></select></div>
      <div class="col-md-6"><label class="form-label" for="bootstrapSupervisor">Supervisor / line manager (optional)</label><select name="supervisor_id" id="bootstrapSupervisor" class="form-select"><option value="">No supervisor assigned</option></select><div class="form-text">Used for staff leave and evaluation approval routing. Leave blank if no reporting line is assigned.</div></div>
      <div class="col-12"><div class="alert alert-info mb-0">You assign the department, job title, employment terms and staff classification. The invitee cannot change them. The invitee sets a private password, verifies their email, and completes only their personal details that are missing. Salary and attendance schedule remain unset until authorized staff configure them.</div></div>
    </div></div></div>
    <div class="d-flex justify-content-end"><button id="bootstrapSubmit" class="btn btn-success btn-lg" type="submit"><i class="bi bi-envelope me-2"></i>Create account and send invitation</button></div>
  </form>
</div>
<style>
@media print {
  @page { size: A4 landscape; }
  #schoolAdminBootstrapForm, #bootstrapAlert, .no-print { display: none !important; }
  #schoolAdminInvitationsTable { font-size: 9pt; }
  #schoolAdminInvitationsTable th, #schoolAdminInvitationsTable td { padding: 4px; }
}
</style>
<?php asset_script($appBase, 'js/pages/bootstrap_school_administrator.js'); ?>
