<?php /** System-domain school provisioning wizard. */ ?>
<div class="container-fluid py-4" id="schoolProvisioningApp">
  <div class="d-flex justify-content-between align-items-start mb-4"><div><h3><i class="fas fa-school me-2"></i>Initialize School</h3><p class="text-muted">Provision configuration only. No staff, students, finance or attendance records are created.</p></div><span class="badge bg-primary" id="provisionStepBadge">Step 1 of 10</span></div>
  <div id="provisionAlert" class="alert d-none" role="alert"></div>
  <div class="card shadow-sm border-0"><div class="card-body">
    <div class="progress mb-4" style="height:8px"><div class="progress-bar" id="provisionProgress" style="width:10%"></div></div>
    <form id="schoolProvisioningForm">
      <section data-step="1"><h5>Create School</h5><div class="row g-3"><div class="col-md-8"><label class="form-label">School name</label><input class="form-control" name="name" required></div><div class="col-md-4"><label class="form-label">School code</label><input class="form-control" name="code" required></div></div></section>
      <section data-step="2" hidden><h5>School Details</h5><textarea class="form-control" name="school_details" rows="7" placeholder="Address, contacts, registration and institutional details"></textarea></section>
      <section data-step="3" hidden><h5>Academic Structure</h5><textarea class="form-control" name="academic_structure" rows="7" placeholder="Levels and curriculum structure"></textarea></section>
      <section data-step="4" hidden><h5>Current Academic Year</h5><div class="row g-3"><div class="col-md-6"><input class="form-control" name="academic_year" placeholder="2026"></div><div class="col-md-6"><input type="date" class="form-control" name="year_start"></div></div></section>
      <section data-step="5" hidden><h5>Terms</h5><textarea class="form-control" name="terms" rows="7" placeholder="One term per line: Term 1 | start | end"></textarea></section>
      <section data-step="6" hidden><h5>Classes</h5><textarea class="form-control" name="classes" rows="7" placeholder="One class per line"></textarea></section>
      <section data-step="7" hidden><h5>Streams</h5><textarea class="form-control" name="streams" rows="7" placeholder="Class | Stream"></textarea></section>
      <section data-step="8" hidden><h5>Grading System</h5><textarea class="form-control" name="grading" rows="7" placeholder="Grade | minimum | maximum | points"></textarea></section>
      <section data-step="9" hidden><h5>School Administrator</h5><div class="row g-3"><div class="col-md-6"><input class="form-control" name="admin_name" placeholder="Full name"></div><div class="col-md-6"><input type="email" class="form-control" name="admin_email" placeholder="Email"></div></div></section>
      <section data-step="10" hidden><h5>Review and Finish</h5><pre class="bg-light border rounded p-3" id="provisionReview"></pre><div class="alert alert-warning">Finishing activates the school configuration and records a full audit trail.</div></section>
    </form>
    <div class="d-flex justify-content-between mt-4"><button class="btn btn-outline-secondary" id="provisionPrevious" disabled>Previous</button><div><button class="btn btn-outline-primary me-2" id="provisionSave">Save progress</button><button class="btn btn-primary" id="provisionNext">Next</button></div></div>
  </div></div>
</div>
<script src="<?= $appBase ?>/js/pages/system/school_initialization.js?v=<?= time() ?>"></script>
