<?php
/**
 * Admissions — Apply for Admission (staff self-service).
 *
 * A staff member applies for their child, sibling, niece/nephew, grandchild
 * or another relative they are responsible for. Their own details (name,
 * phone, email, staff number) are taken from their staff profile and they
 * become the parent/guardian of record; only the learner's details,
 * relationship and documents are entered. This does not grant any admissions
 * review or approval rights.
 */
?>
<div class="container-fluid py-4" id="staffAdmissionPage">
  <section class="card mb-4">
    <header class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h2 class="h4 mb-1"><i class="bi bi-person-plus me-2" aria-hidden="true"></i>Apply for Admission</h2>
        <p class="text-muted mb-0">For your child, sibling or another relative you are responsible for. Your details are filled in from your staff profile.</p>
      </div>
    </header>
    <div class="card-body">
      <h6 class="text-primary">Applying as parent / guardian</h6>
      <div id="staffAdmissionIdentity" class="alert alert-light border mb-4">Loading your staff profile…</div>
      <form id="staffAdmissionForm" enctype="multipart/form-data" novalidate>
        <h6 class="text-primary">Learner</h6>
        <div class="row g-3">
          <div class="col-md-5"><label class="form-label" for="saChildName">Learner's full name <span class="text-danger">*</span></label><input id="saChildName" name="child_name" class="form-control" required maxlength="150"></div>
          <div class="col-md-4"><label class="form-label" for="saRelationship">Your relationship to the learner <span class="text-danger">*</span></label>
            <select id="saRelationship" name="parent_relationship" class="form-select" required>
              <option value="">Select</option>
              <optgroup label="Parent"><option value="father">Father</option><option value="mother">Mother</option><option value="step_father">Step-father</option><option value="step_mother">Step-mother</option></optgroup>
              <optgroup label="Relative"><option value="sibling">Brother / sister</option><option value="grandparent">Grandparent</option><option value="uncle">Uncle</option><option value="aunt">Aunt</option><option value="other">Other relative</option></optgroup>
              <optgroup label="Other"><option value="guardian">Legal guardian</option></optgroup>
            </select>
          </div>
          <div class="col-md-3"><label class="form-label" for="saGender">Gender <span class="text-danger">*</span></label><select id="saGender" name="child_gender" class="form-select" required><option value="">Select</option><option value="male">Male</option><option value="female">Female</option></select></div>
          <div class="col-md-3"><label class="form-label" for="saDob">Date of birth <span class="text-danger">*</span></label><input type="date" id="saDob" name="child_dob" class="form-control" required></div>
          <div class="col-md-3"><label class="form-label" for="saBirthNo">Birth certificate number</label><input type="text" id="saBirthNo" name="birth_certificate_no" class="form-control" maxlength="64" autocomplete="off"></div>
          <div class="col-md-3"><label class="form-label" for="staffAdmissionGrade">Grade applying for <span class="text-danger">*</span></label><select name="grade_applying" id="staffAdmissionGrade" class="form-select" required><option value="">Loading grades…</option></select></div>
          <div class="col-md-3"><label class="form-label" for="saBoarding">Day or boarding</label><select id="saBoarding" name="boarding_preference" class="form-select"><option value="day">Day scholar</option><option value="full_boarding">Full boarding</option></select></div>
          <div class="col-md-6"><label class="form-label" for="staffAdmissionWindow">Admission window <span class="text-danger">*</span></label><select name="admission_window_id" id="staffAdmissionWindow" class="form-select" required><option value="">Loading open windows…</option></select></div>
          <div class="col-md-6"><label class="form-label" for="saPrevSchool">Previous school (if any)</label><input id="saPrevSchool" name="previous_school" class="form-control" maxlength="200"></div>
          <div class="col-12"><label class="form-label" for="saNeeds">Medical, learning or dietary needs</label><textarea id="saNeeds" name="special_needs" class="form-control" rows="2"></textarea></div>
        </div>
        <h6 class="text-primary mt-4">Documents</h6>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="saBirthDoc">Birth certificate <span class="text-danger">*</span></label><input type="file" id="saBirthDoc" name="doc_birth_certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required></div>
          <div class="col-md-6"><label class="form-label" for="saPhotoDoc">Passport photo <span class="text-danger">*</span></label><input type="file" id="saPhotoDoc" name="doc_passport_photo" class="form-control" accept=".jpg,.jpeg,.png" required></div>
          <div class="col-md-6"><label class="form-label" for="saReportDoc">Previous school report</label><input type="file" id="saReportDoc" name="doc_previous_school_report" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
          <div class="col-md-6"><label class="form-label" for="saTransferDoc">Transfer / leaving letter</label><input type="file" id="saTransferDoc" name="doc_transfer_letter" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
          <div class="col-md-6"><label class="form-label" for="saImmunDoc">Immunization card</label><input type="file" id="saImmunDoc" name="doc_immunization_card" class="form-control" accept=".pdf,.jpg,.jpeg,.png"></div>
          <div class="col-12"><div id="staffAdmissionMessage" class="alert d-none mb-0" role="status"></div></div>
        </div>
        <div class="mt-4 d-flex justify-content-end"><button type="submit" class="btn btn-primary" id="staffAdmissionSubmit"><i class="bi bi-send me-1" aria-hidden="true"></i>Submit application</button></div>
      </form>
    </div>
  </section>

  <section class="card">
    <header class="card-header d-flex justify-content-between align-items-center">
      <h3 class="h6 mb-0"><i class="bi bi-list-check me-1"></i>My applications</h3>
      <button class="btn btn-outline-secondary btn-sm" type="button" id="saPrint"><i class="bi bi-printer me-1"></i>Print</button>
    </header>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Application no.</th><th>Learner</th><th>Grade</th><th>Submitted</th><th>Appointment</th><th>Status</th></tr></thead>
        <tbody id="saApplications"><tr><td colspan="6" class="text-center text-muted py-3">Loading…</td></tr></tbody>
      </table>
    </div>
  </section>
</div>
<?php asset_script($appBase, 'js/utils/document_camera_capture.js'); ?>
<?php asset_script($appBase, 'js/pages/staff_my_admission.js'); ?>
