<?php
/**
 * My HR — Report an Incident (staff self-service).
 * Any active staff member reports a workplace, safety, security, welfare or
 * other incident and follows its status. Only their own reports are listed
 * (GET/POST /api/staff/incidents).
 */
?>
<div class="container-fluid py-4" id="myIncidentsPage">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-exclamation-octagon me-2"></i>Report an Incident</h3>
      <p class="text-muted mb-0">Report an accident, hazard, damage, security or welfare concern. The school office follows it up and you can track it here.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-primary btn-sm" type="button" id="incExport"><i class="bi bi-download me-1"></i>CSV</button>
      <button class="btn btn-outline-secondary btn-sm" type="button" id="incPrint"><i class="bi bi-printer me-1"></i>Print</button>
      <button class="btn btn-danger btn-sm" type="button" id="incNew"><i class="bi bi-plus-lg me-1"></i>Report an incident</button>
    </div>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Reported</small><h3 id="incTotal">0</h3></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Open</small><h3 id="incOpen">0</h3></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Resolved</small><h3 id="incResolved">0</h3></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">High / critical</small><h3 id="incHigh">0</h3></div></div></div>
  </div>
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Reference</th><th>Category</th><th>When</th><th>Where</th><th>Severity</th><th>Status</th><th>Resolution</th></tr></thead>
        <tbody id="incBody"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="incModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Report an incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <form id="incForm" novalidate>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="incCategory">What happened *</label>
            <select class="form-select" id="incCategory" required>
              <option value="">Select</option>
              <option value="workplace_accident">Accident or injury at work</option>
              <option value="safety_hazard">Safety hazard</option>
              <option value="property_damage">Damage to property</option>
              <option value="security_concern">Security concern</option>
              <option value="student_welfare">Learner welfare concern</option>
              <option value="harassment">Harassment or misconduct</option>
              <option value="maintenance">Maintenance fault</option>
              <option value="transport">Transport</option>
              <option value="kitchen">Kitchen / food</option>
              <option value="other">Other</option>
            </select></div>
          <div class="col-md-3"><label class="form-label" for="incSeverity">Severity *</label>
            <select class="form-select" id="incSeverity"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option></select></div>
          <div class="col-md-3"><label class="form-label" for="incWhen">When *</label><input type="datetime-local" class="form-control" id="incWhen" required></div>
          <div class="col-12"><label class="form-label" for="incWhere">Where *</label><input class="form-control" id="incWhere" maxlength="200" required placeholder="e.g. Grade 5 classroom, school bus KBX 123A, dormitory"></div>
          <div class="col-12"><label class="form-label" for="incDescription">Describe what happened *</label><textarea class="form-control" id="incDescription" rows="4" required></textarea></div>
          <div class="col-12"><label class="form-label" for="incAction">Action already taken</label><textarea class="form-control" id="incAction" rows="2" placeholder="e.g. first aid given, area cordoned off, parent called"></textarea></div>
          <div class="col-12 small text-muted">For an emergency, call for help first and report here afterwards.</div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="incSubmit">Submit report</button></div>
  </div></div>
</div>
<?php asset_script($appBase, 'js/pages/my_incident_reports.js'); ?>
