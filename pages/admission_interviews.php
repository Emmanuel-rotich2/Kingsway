<?php
/**
 * Admission Interviews — PARTIAL
 * Schedule and record headteacher admission interviews with deep workflow integration.
 * JS controller: js/pages/admission_interviews.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-person-badge me-2 text-info"></i>Admission Interviews</h2>
      <small class="text-muted">Schedule and record headteacher admission interviews with workflow tracking</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-primary" onclick="admissionInterviewsController.loadInterviews()">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
      </button>
          <button class="btn btn-primary" onclick="admissionInterviewsController.manageSessions()">
            <i class="bi bi-calendar2-week me-1"></i> Manage Interview Sessions
      </button>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="aiStatToday">—</div>
          <div class="text-muted small">Scheduled Today</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="aiStatPending">—</div>
          <div class="text-muted small">Pending Scheduling</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="aiStatCompletedMonth">—</div>
          <div class="text-muted small">Completed This Month</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="aiStatAwaitingDecision">—</div>
          <div class="text-muted small">Awaiting Decision</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Interview Status</label>
          <select id="aiFilterStatus" class="form-select">
            <option value="">All Statuses</option>
            <option value="scheduled">Scheduled</option>
            <option value="completed">Completed</option>
            <option value="pending_scheduling">Pending Scheduling</option>
            <option value="awaiting_decision">Awaiting Decision</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Workflow Stage</label>
          <select id="aiFilterStage" class="form-select">
            <option value="">All Stages</option>
            <option value="interview_scheduling">Interview Scheduling</option>
            <option value="interview_results">Assessment Pending</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Interviewer</label>
          <select id="aiFilterInterviewer" class="form-select">
            <option value="">All Interviewers</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Search</label>
          <input type="text" id="aiSearch" class="form-control" placeholder="Search by name or application no...">
        </div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div id="aiTableBody">
        <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
      </div>
    </div>
  </div>

</div>

<!-- SCHEDULE INTERVIEW MODAL -->
<div class="modal fade" id="aiScheduleModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Schedule Interview</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Applicant <span class="text-danger">*</span></label>
            <select id="aiApplicantId" class="form-select">
              <option value="">— Select applicant —</option>
            </select>
            <small class="text-muted">Select an applicant awaiting scheduling, or an already scheduled applicant to switch/reschedule.</small>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Interview session <span class="text-danger">*</span></label>
            <select id="aiSessionId" class="form-select"><option value="">— Select a configured session —</option></select>
            <small class="text-muted">Sessions are created against an intake window. Date, time, venue and interviewer come from the selected session.</small>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notify parent/guardian</label>
            <div class="d-flex gap-3 flex-wrap">
              <label><input type="checkbox" class="form-check-input me-1" name="aiNotifyChannel" value="sms" checked> SMS</label>
              <label><input type="checkbox" class="form-check-input me-1" name="aiNotifyChannel" value="email"> Email</label>
              <label><input type="checkbox" class="form-check-input me-1" name="aiNotifyChannel" value="whatsapp"> WhatsApp</label>
            </div>
          </div>
        </div>
        <div id="aiScheduleError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="admissionInterviewsController.saveSchedule()">
          <i class="bi bi-calendar-check me-1"></i>Assign to session
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="aiSessionsModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="bi bi-calendar2-week me-2"></i>Interview Sessions</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div id="aiSessionsBody"></div><hr><h6>Create / edit session</h6><div class="row g-2">
    <input type="hidden" id="aiSessionEditId"><div class="col-md-4"><label class="form-label">Admission window</label><select id="aiSessionWindow" class="form-select"></select></div>
    <div class="col-md-2"><label class="form-label">Date</label><input type="date" id="aiSessionDate" class="form-control"></div><div class="col-md-2"><label class="form-label">Start</label><input type="time" id="aiSessionStart" class="form-control"></div><div class="col-md-2"><label class="form-label">End</label><input type="time" id="aiSessionEnd" class="form-control"></div><div class="col-md-2"><label class="form-label">Capacity</label><input type="number" id="aiSessionCapacity" class="form-control" min="1" value="20"></div>
    <div class="col-md-6"><label class="form-label">Teacher interviewer <span class="text-danger">*</span></label><select id="aiSessionInterviewer" class="form-select"></select></div><div class="col-md-6"><label class="form-label">Venue</label><input type="text" id="aiSessionVenue" class="form-control" value="Main Office"></div>
  </div><div id="aiSessionError" class="alert alert-danger mt-3 d-none"></div></div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" onclick="admissionInterviewsController.saveSession()">Save session</button></div>
</div></div></div>

<!-- RECORD OUTCOME MODAL -->
<div class="modal fade" id="aiOutcomeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Record Interview Assessment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="aiOutcomeInterviewId">
        <input type="hidden" id="aiOutcomeApplicationId">
        
        <!-- Applicant Summary -->
        <div class="alert alert-info" id="aiApplicantSummary">
          <div class="spinner-border spinner-border-sm me-2"></div>
          Loading applicant details...
        </div>
        
        <div class="row g-3">
          <!-- Assessment Scores -->
          <div class="col-12">
            <h6 class="fw-semibold mb-3">Assessment Scores (0-100)</h6>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Academic Readiness</label>
            <input type="number" id="aiAcademicScore" class="form-control" min="0" max="100" placeholder="0-100">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Behavior / Social</label>
            <input type="number" id="aiBehaviorScore" class="form-control" min="0" max="100" placeholder="0-100">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Communication</label>
            <input type="number" id="aiCommunicationScore" class="form-control" min="0" max="100" placeholder="0-100">
          </div>
          
          <!-- Overall Score -->
          <div class="col-12">
            <div class="card bg-light">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="fw-semibold">Overall Score:</span>
                  <span class="fs-4 fw-bold text-primary" id="aiOverallScore">—</span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Outcome -->
          <div class="col-12">
            <label class="form-label fw-semibold">Recommendation <span class="text-danger">*</span></label>
            <select id="aiOutcome" class="form-select">
              <option value="">Select Recommendation</option>
              <option value="recommended">Recommended for Admission</option>
              <option value="not_recommended">Not Recommended</option>
              <option value="conditional">Conditional Admission</option>
              <option value="placement_test_required">Requires Placement Test</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Interview Notes</label>
            <textarea id="aiOutcomeNotes" class="form-control" rows="3" placeholder="Key observations from the interview…"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Next Workflow Step <span class="text-danger">*</span></label>
            <select id="aiNextStep" class="form-select">
              <option value="">Select Next Step</option>
              <option value="proceed_to_admission">Proceed to Admission Decision</option>
              <option value="waitlist">Add to Waitlist</option>
              <option value="decline">Decline Application</option>
              <option value="placement_test">Schedule Placement Test</option>
            </select>
          </div>
        </div>
        <div id="aiOutcomeError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="admissionInterviewsController.saveOutcome()">
          <i class="bi bi-check2-circle me-1"></i>Save Assessment
        </button>
      </div>
    </div>
  </div>
</div>

<!-- VIEW APPLICATION MODAL -->
<div class="modal fade" id="aiViewModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Application Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="aiViewContent">
        <div class="text-center py-4">
          <div class="spinner-border text-info"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" id="aiConductInterviewBtn">
          <i class="bi bi-clipboard-check me-1"></i>Conduct Interview
        </button>
      </div>
    </div>
  </div>
</div>

<?php asset_script($appBase, 'js/pages/admission_interviews.js'); ?>
<script>
function initWhenAPIReady() {
    if (typeof API !== 'undefined' && API.callAPI) {
        if (typeof admissionInterviewsController !== 'undefined' && admissionInterviewsController.init) {
            admissionInterviewsController.init();
        }
    } else {
        setTimeout(initWhenAPIReady, 100);
    }
}
document.addEventListener('DOMContentLoaded', initWhenAPIReady);
</script>
