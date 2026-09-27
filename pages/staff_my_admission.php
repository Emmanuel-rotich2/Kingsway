<?php
/** Staff self-service admission: this stays inside the authenticated staff shell. */
?>
<section class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <div>
      <div class="text-uppercase small text-success fw-semibold">Staff self-service</div>
      <h4 class="mb-1">Apply for admission for my child</h4>
      <p class="text-muted small mb-0">Your staff identity is loaded from your account. Only learner details and relationship are requested.</p>
    </div>
    <i class="bi bi-person-vcard fs-2 text-success"></i>
  </div>
  <div class="card-body">
    <div id="staffAdmissionIdentity" class="alert alert-light border mb-4">Loading your saved staff profile…</div>
    <form id="staffAdmissionForm" enctype="multipart/form-data">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Child's full name <span class="text-danger">*</span></label><input name="child_name" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Date of birth <span class="text-danger">*</span></label><input type="date" name="child_dob" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Birth certificate number</label><input type="text" name="birth_certificate_no" class="form-control" maxlength="64" autocomplete="off"></div>
        <div class="col-md-3"><label class="form-label">Gender <span class="text-danger">*</span></label><select name="child_gender" class="form-select" required><option value="">Select</option><option value="male">Male</option><option value="female">Female</option></select></div>
        <div class="col-md-4"><label class="form-label">Grade applying for <span class="text-danger">*</span></label><select name="grade_applying" id="staffAdmissionGrade" class="form-select" required><option value="">Loading grades…</option></select></div>
        <div class="col-md-5"><label class="form-label">Admission application window <span class="text-danger">*</span></label><select name="admission_window_id" id="staffAdmissionWindow" class="form-select" required><option value="">Loading open windows…</option></select></div>
        <div class="col-md-3"><label class="form-label">Boarding preference</label><select name="boarding_preference" class="form-select"><option value="day">Day Scholar</option><option value="full_boarding">Full Boarding</option></select></div>
        <div class="col-md-5"><label class="form-label">Relationship to child <span class="text-danger">*</span></label><select name="parent_relationship" class="form-select" required><option value="father">Father</option><option value="mother">Mother</option><option value="guardian">Guardian</option><option value="step_father">Step-father</option><option value="step_mother">Step-mother</option><option value="grandparent">Grandparent</option><option value="uncle">Uncle</option><option value="aunt">Aunt</option><option value="sibling">Sibling</option><option value="other">Other</option></select></div>
        <div class="col-md-7"><label class="form-label">Special medical / learning / dietary needs</label><textarea name="special_needs" class="form-control" rows="2"></textarea></div>
        <div class="col-md-6"><label class="form-label">Birth certificate <span class="text-danger">*</span></label><input type="file" name="doc_birth_certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required></div>
        <div class="col-md-6"><label class="form-label">Passport photo <span class="text-danger">*</span></label><input type="file" name="doc_passport_photo" class="form-control" accept=".jpg,.jpeg,.png" required></div>
        <div class="col-12"><div id="staffAdmissionMessage" class="alert d-none mb-0"></div></div>
      </div>
      <div class="mt-4 d-flex justify-content-end"><button class="btn btn-success" id="staffAdmissionSubmit"><i class="bi bi-send me-1"></i>Submit application</button></div>
    </form>
  </div>
</section>
<?php asset_script($appBase, 'js/pages/staff_my_admission.js'); ?>
