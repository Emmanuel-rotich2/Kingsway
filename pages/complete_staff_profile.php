<div class="container py-4" id="staffProfileCompletionPage">
  <div class="card border-0 shadow-sm mx-auto" style="max-width:1000px">
    <div class="card-body p-4">
      <div id="spState" class="alert alert-info">Loading your profile…</div>
      <div id="spProfileContent" class="d-none">

        <div class="row mb-4" id="spProfileHeader"></div>

        <form id="spForm" class="row g-3">
          <div class="col-12"><p class="text-muted mb-0">Information already recorded by the school is prefilled. Complete your personal details and provide your own payroll contact details. Your role, department and employment assignment are managed by the school.</p></div>
          <div class="col-12">
            <h5 class="border-bottom pb-2">Personal Details</h5>
          </div>
          <div class="col-md-6"><label class="form-label">Middle name</label><input name="middle_name" class="form-control" maxlength="50"></div>
          <div class="col-md-6">
            <label class="form-label">Phone <span class="text-danger" data-required-mark hidden>*</span></label>
            <input name="phone" class="form-control" type="tel" data-phone-canonical data-kw-validate="phone">
          </div>
          <div class="col-md-6">
            <label class="form-label">Date of Birth <span class="text-danger" data-required-mark hidden>*</span></label>
            <input name="date_of_birth" type="date" class="form-control" data-kw-validate="dob">
          </div>
          <div class="col-md-4">
            <label class="form-label">Gender <span class="text-danger" data-required-mark hidden>*</span></label>
            <select name="gender" class="form-select">
              <option value="">Select</option><option>male</option><option>female</option><option>other</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Address <span class="text-danger" data-required-mark hidden>*</span></label>
            <textarea name="address" class="form-control" data-kw-validate="address"></textarea>
          </div>
          <div class="col-md-6"><label class="form-label">National ID</label><input name="national_id_no" class="form-control" maxlength="30"></div>

          <div class="col-12 mt-3"><h5 class="border-bottom pb-2">Payroll payment details</h5><p class="text-muted small">Provide at least the payout details the school will use. M-Pesa and bank details are stored on your confidential payroll profile.</p></div>
          <div class="col-md-6"><label class="form-label">M-Pesa number</label><input name="mpesa_phone" class="form-control" type="tel" data-phone-canonical data-kw-validate="phone" autocomplete="tel"></div>
          <div class="col-md-6"><label class="form-label">Bank name</label><input name="bank_name" class="form-control" maxlength="100" autocomplete="organization"></div>
          <div class="col-md-6"><label class="form-label">Bank account number</label><input name="bank_account" class="form-control" maxlength="50" autocomplete="off"></div>
          <div class="col-md-6"><label class="form-label">TSC number <span class="text-muted">(if applicable)</span></label><input name="tsc_no" class="form-control" maxlength="100"></div>

          <div class="col-12 mt-3" id="spTeachingSection" hidden><h5 class="border-bottom pb-2">Teaching specializations</h5><p class="text-muted small">Select your learning areas. The school will review these with your qualifications before using them for teaching assignments.</p><select name="learning_area_ids" id="spLearningAreas" class="form-select" multiple size="6"></select><label class="form-label mt-2" for="spPrimaryLearningArea">Primary learning area (optional)</label><select name="primary_learning_area_id" id="spPrimaryLearningArea" class="form-select"><option value="">No primary area selected</option></select></div>

          <div class="col-12 mt-3">
            <h5 class="border-bottom pb-2">Contact &amp; Emergency</h5>
          </div>
          <div class="col-md-6"><label class="form-label">Verified account email</label><input id="spAccountEmail" class="form-control" readonly aria-readonly="true"><div class="form-text">This verified address is controlled by the school. Contact the System Administrator if it needs correction.</div></div>
          <div class="col-md-6">
            <label class="form-label">Emergency Contact Name</label>
            <input name="emergency_contact_name" class="form-control" data-kw-validate="name">
          </div>
          <div class="col-md-6">
            <label class="form-label">Emergency Contact Phone</label>
            <input name="emergency_contact_phone" class="form-control" data-phone-canonical data-kw-validate="phone">
          </div>
          <div class="col-md-6"><label class="form-label">Relationship</label><input name="emergency_contact_relationship" class="form-control" maxlength="30"></div>

          <div class="col-12 mt-3">
            <h5 class="border-bottom pb-2">Qualifications &amp; Certifications</h5>
            <p class="text-muted small">Add all relevant qualifications. Submitted records remain pending until the school verifies the evidence.</p>
            <div id="spQualifications"></div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="spAddQualification">Add qualification</button>
          </div>

          <div class="col-12 mt-4 text-end">
            <button class="btn btn-success btn-lg px-5" type="submit">Save and Continue</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>
<?php asset_script($appBase, 'js/pages/complete_staff_profile.js'); ?>
