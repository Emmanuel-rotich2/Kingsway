<div class="container py-4" id="staffProfileCompletionPage">
  <div class="card border-0 shadow-sm mx-auto" style="max-width:900px">
    <div class="card-body p-4">
      <h3>Complete Your Staff Profile</h3><p class="text-muted">Finish the required information before opening your role dashboard.</p>
      <div id="spState" class="alert alert-info">Loading your onboarding profile…</div>
      <form id="spForm" class="row g-3 d-none">
        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Communication email</label><input name="communication_email" type="email" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Date of birth</label><input name="date_of_birth" type="date" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Gender</label><select name="gender" class="form-select" required><option value="">Select</option><option>male</option><option>female</option><option>other</option></select></div>
        <div class="col-md-3"><label class="form-label">Marital status</label><select name="marital_status" class="form-select" required><option value="">Select</option><option>single</option><option>married</option><option>divorced</option><option>widowed</option></select></div>
        <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" required></textarea></div>
        <div class="col-md-6"><label class="form-label">Emergency contact name</label><input name="emergency_contact_name" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Emergency contact phone</label><input name="emergency_contact_phone" class="form-control"></div>
        <div class="col-12"><button class="btn btn-success" type="submit">Save and Continue</button></div>
      </form>
    </div>
  </div>
</div>
<script src="<?= $appBase ?>/js/pages/complete_staff_profile.js"></script>
