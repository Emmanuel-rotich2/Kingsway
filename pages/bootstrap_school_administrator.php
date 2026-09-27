<?php /** System Administrator — one-time first School Administrator onboarding. */ ?>
<div class="container-fluid py-4" id="schoolAdminBootstrapPage">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h2 class="h3 mb-1">Create First School Administrator</h2><p class="text-muted mb-0">One-time production bootstrap: identity, employment, payroll, onboarding and secure invitation.</p></div>
    <span class="badge text-bg-warning px-3 py-2" id="bootstrapAvailability">Checking availability…</span>
  </div>
  <div id="bootstrapAlert" class="alert alert-info" role="status">Loading reference data…</div>
  <form id="schoolAdminBootstrapForm" class="d-none">
    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Personal and contact information</strong></div><div class="card-body"><div class="row g-3">
      <div class="col-md-4"><label class="form-label">First name</label><input name="first_name" class="form-control" required maxlength="50" data-kw-validate="name"></div>
      <div class="col-md-4"><label class="form-label">Middle name <span class="text-muted">(optional)</span></label><input name="middle_name" class="form-control" maxlength="50" data-kw-validate="name"></div>
      <div class="col-md-4"><label class="form-label">Last name</label><input name="last_name" class="form-control" required maxlength="50" data-kw-validate="name"></div>
      <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required data-kw-validate="email"></div>
      <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" required placeholder="+2547…" type="tel" data-phone-canonical data-kw-validate="phone"></div>
      <div class="col-md-4"><label class="form-label">Date of birth</label><input name="date_of_birth" type="date" class="form-control" required data-kw-validate="dob"></div>
      <div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-select" required><option value="">Select</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
      <div class="col-md-4"><label class="form-label">National ID</label><input name="national_id_no" class="form-control" required data-kw-validate="national_id"></div>
      <div class="col-md-8"><label class="form-label">Residential address</label><input name="address" class="form-control" required data-kw-validate="address"></div>
      <div class="col-md-4"><label class="form-label">Marital status</label><select name="marital_status" class="form-select" required><option value="">Select</option><option>single</option><option>married</option><option>divorced</option><option>widowed</option><option>separated</option><option>unknown</option></select></div>
      <div class="col-md-4"><label class="form-label">Emergency contact</label><input name="emergency_contact_name" class="form-control" required data-kw-validate="name"></div>
      <div class="col-md-4"><label class="form-label">Emergency phone</label><input name="emergency_contact_phone" class="form-control" required type="tel" data-phone-canonical data-kw-validate="phone"></div>
      <div class="col-md-4"><label class="form-label">Relationship</label><input name="emergency_contact_relationship" class="form-control" required></div>
      <div class="col-12"><label class="form-label">Profile photo <span class="text-muted">(optional; uploaded after the atomic account creation)</span></label><input id="bootstrapProfilePhoto" type="file" class="form-control" accept="image/jpeg,image/png,image/webp"></div>
    </div></div></div>

    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Employment</strong></div><div class="card-body"><div class="row g-3">
      <div class="col-md-4"><label class="form-label">Role</label><input class="form-control" value="School Administrator" readonly></div>
      <div class="col-md-4"><label class="form-label">Department</label><select name="department_id" id="bootstrapDepartment" class="form-select" required></select></div>
      <div class="col-md-4"><label class="form-label">Position</label><input name="position" class="form-control" value="School Administrator" required></div>
      <div class="col-md-4"><label class="form-label">Employment date</label><input name="employment_date" type="date" class="form-control" required data-kw-validate="not_future"></div>
      <div class="col-md-4"><label class="form-label">Contract type</label><select name="contract_type" class="form-select" required><option value="permanent">Permanent</option><option value="contract">Contract</option><option value="temporary">Temporary</option></select></div>
      <div class="col-md-4"><label class="form-label">Supervisor <span class="text-muted">(optional)</span></label><select name="supervisor_id" id="bootstrapSupervisor" class="form-select"><option value="">None</option></select></div>
      <div class="col-md-6"><label class="form-label">Staff type</label><select name="staff_type_id" id="bootstrapStaffType" class="form-select" required></select></div>
      <div class="col-md-6"><label class="form-label">Staff category</label><select name="staff_category_id" id="bootstrapStaffCategory" class="form-select" required></select></div>
    </div></div></div>

    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Statutory and payroll details</strong></div><div class="card-body"><div class="row g-3">
      <div class="col-md-4"><label class="form-label">KRA PIN</label><input name="kra_pin" class="form-control" required></div>
      <div class="col-md-4"><label class="form-label">NSSF number</label><input name="nssf_no" class="form-control" required></div>
      <div class="col-md-4"><label class="form-label">SHA/SHIF or NHIF identifier</label><input name="nhif_no" class="form-control" required></div>
      <div class="col-md-4"><label class="form-label">Bank name</label><input name="bank_name" class="form-control" required></div>
      <div class="col-md-4"><label class="form-label">Bank account</label><input name="bank_account" class="form-control" required></div>
      <div class="col-md-4"><label class="form-label">Basic salary (KES)</label><input name="salary" type="number" min="0" step="0.01" class="form-control" required></div>
    </div></div></div>

    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><strong>Work schedule and invitation</strong></div><div class="card-body"><div class="row g-3">
      <div class="col-md-4"><label class="form-label">Work starts</label><input name="work_start_time" type="time" value="08:00" class="form-control" required></div>
      <div class="col-md-4"><label class="form-label">Work ends</label><input name="work_end_time" type="time" value="17:00" class="form-control" required></div>
      <div class="col-md-4"><label class="form-label">Late threshold (minutes)</label><input name="late_threshold_minutes" type="number" min="0" value="15" class="form-control" required></div>
      <div class="col-12"><div class="alert alert-success mb-0"><i class="bi bi-envelope-check me-2"></i>The system will generate the username and temporary credential, then send a branded 72-hour setup link. The administrator must create a private 10+ character password and complete onboarding before dashboard access.</div></div>
    </div></div></div>
    <div class="d-flex justify-content-end"><button id="bootstrapSubmit" class="btn btn-success btn-lg" type="submit"><i class="bi bi-person-check me-2"></i>Create and send invitation</button></div>
  </form>
</div>
<?php asset_script($appBase, 'js/pages/bootstrap_school_administrator.js'); ?>
