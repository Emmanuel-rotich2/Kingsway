<?php /** Shared "Report a security incident" dialog (gate desk and incidents page). */ ?>
<div class="modal fade" id="secReportModal" tabindex="-1" aria-labelledby="secReportTitle">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content kw-gate">
    <div class="modal-header"><h2 class="modal-title h5" id="secReportTitle">Report a security incident</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <p class="small text-muted">In an emergency, raise the alarm and call for help first. Record it here as soon as it is safe.</p>
      <form id="secReportForm" novalidate>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label" for="secCategory">What happened *</label>
            <select class="form-select" id="secCategory">
              <option value="trespass">Trespass / unauthorised entry</option>
              <option value="suspicious_person">Suspicious person</option>
              <option value="suspicious_vehicle">Suspicious vehicle</option>
              <option value="unauthorised_exit">Learner left without permission</option>
              <option value="missing_learner">Learner missing</option>
              <option value="theft">Theft</option>
              <option value="break_in">Break-in</option>
              <option value="fight">Fight / assault</option>
              <option value="injury">Injury</option>
              <option value="fire">Fire</option>
              <option value="property_damage">Damage to property</option>
              <option value="threat">Threat</option>
              <option value="power_water_failure">Power or water failure</option>
              <option value="other">Other</option>
            </select></div>
          <div class="col-md-3"><label class="form-label" for="secSeverity">How serious *</label>
            <select class="form-select" id="secSeverity"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option></select></div>
          <div class="col-md-3"><label class="form-label" for="secWhen">When *</label><input class="form-control" type="datetime-local" id="secWhen"></div>
          <div class="col-12"><label class="form-label" for="secWhere">Where *</label><input class="form-control" id="secWhere" maxlength="200" placeholder="e.g. main gate, back fence by the farm, boys' dormitory"></div>
          <div class="col-12"><label class="form-label" for="secDescription">What happened, in order *</label><textarea class="form-control" id="secDescription" rows="4"></textarea></div>
          <div class="col-md-6"><label class="form-label" for="secPersons">People involved</label><input class="form-control" id="secPersons" maxlength="500"></div>
          <div class="col-md-6"><label class="form-label" for="secAction">Action already taken</label><input class="form-control" id="secAction" maxlength="1000"></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button><button class="btn btn-danger" type="button" id="secReportSubmit">Report incident</button></div>
  </div></div>
</div>
