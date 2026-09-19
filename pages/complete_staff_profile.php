<div class="container py-4" id="staffProfileCompletionPage">
  <div class="card border-0 shadow-sm mx-auto" style="max-width:1000px">
    <div class="card-body p-4">
      <div id="spState" class="alert alert-info">Loading your profile…</div>
      <div id="spProfileContent" class="d-none">

        <div class="row mb-4" id="spProfileHeader"></div>

        <form id="spForm" class="row g-3">
          <div class="col-12">
            <h5 class="border-bottom pb-2">Personal Details</h5>
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone <span class="text-danger">*</span></label>
            <input name="phone" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
            <input name="date_of_birth" type="date" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Gender <span class="text-danger">*</span></label>
            <select name="gender" class="form-select" required>
              <option value="">Select</option><option>male</option><option>female</option><option>other</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Marital Status <span class="text-danger">*</span></label>
            <select name="marital_status" class="form-select" required>
              <option value="">Select</option><option>single</option><option>married</option><option>divorced</option><option>widowed</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Address <span class="text-danger">*</span></label>
            <textarea name="address" class="form-control" required></textarea>
          </div>

          <div class="col-12 mt-3">
            <h5 class="border-bottom pb-2">Contact &amp; Emergency</h5>
          </div>

          <div class="col-12 mt-3">
            <h5 class="border-bottom pb-2">Qualifications &amp; Certifications</h5>
            <p class="text-muted small">Add all relevant qualifications. Submitted records remain pending until the school verifies the evidence.</p>
            <div id="spQualifications"></div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="spAddQualification">Add qualification</button>
          </div>
          <div class="col-md-6">
            <label class="form-label">Communication Email <span class="text-danger">*</span></label>
            <input name="communication_email" type="email" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Communication Phone</label>
            <input name="communication_phone" type="tel" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Emergency Contact Name</label>
            <input name="emergency_contact_name" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Emergency Contact Phone</label>
            <input name="emergency_contact_phone" class="form-control">
          </div>

          <div class="col-12 mt-3">
            <h5 class="border-bottom pb-2">Employment Information</h5>
          </div>
          <div class="col-12">
            <div class="row g-3" id="spEmploymentInfo"></div>
          </div>

          <div class="col-12 mt-3">
            <h5 class="border-bottom pb-2">Payroll &amp; Benefits</h5>
          </div>
          <div class="col-12">
            <div class="row g-3" id="spPayrollInfo"></div>
          </div>

          <div class="col-12 mt-3">
            <h5 class="border-bottom pb-2">Work Schedule</h5>
          </div>
          <div class="col-12">
            <div class="row g-3" id="spScheduleInfo"></div>
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
