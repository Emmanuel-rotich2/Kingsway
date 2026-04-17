<?php
/**
 * Sick Bay Management — PARTIAL
 * Real-time log of students currently in the sick bay.
 * JS controller: js/pages/sick_bay.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-hospital me-2 text-danger"></i>Sick Bay</h2>
      <small class="text-muted">Daily log of sick bay visits</small>
    </div>
    <div class="d-flex gap-2">
      <input type="date" id="sbDateFilter" class="form-control form-control-sm"
             onchange="sickBayController.loadVisits()" style="width:auto">
      <button class="btn btn-primary" onclick="sickBayController.showAdmitModal()">
        <i class="bi bi-plus-circle me-1"></i> Admit Student
      </button>
    </div>
  </div>

  <!-- Stats row -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center bg-danger bg-opacity-10">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="sbStatActive">—</div>
          <div class="text-muted small">Currently In</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="sbStatToday">—</div>
          <div class="text-muted small">Today's Total</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="sbStatReferred">—</div>
          <div class="text-muted small">Referred</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="sbStatDismissed">—</div>
          <div class="text-muted small">Dismissed Today</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Status filter tabs -->
  <ul class="nav nav-pills mb-3" id="sbStatusTabs">
    <li class="nav-item"><button class="nav-link active" data-status="">All</button></li>
    <li class="nav-item"><button class="nav-link" data-status="active">Active</button></li>
    <li class="nav-item"><button class="nav-link" data-status="dismissed">Dismissed</button></li>
    <li class="nav-item"><button class="nav-link" data-status="referred">Referred</button></li>
  </ul>

  <!-- Visits table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div id="sbVisitsContainer">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      </div>
    </div>
  </div>
</div>

<!-- ADMIT STUDENT MODAL -->
<div class="modal fade" id="admitModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="admitModalTitle">Admit Student to Sick Bay</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="sbVisitId">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
            <select id="sbStudentId" class="form-select">
              <option value="">— Select student —</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Visit Date</label>
            <input type="date" id="sbVisitDate" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Time</label>
            <input type="time" id="sbVisitTime" class="form-control">
          </div>
          <div class="col-md-8">
            <label class="form-label fw-semibold">Complaint / Reason <span class="text-danger">*</span></label>
            <input type="text" id="sbComplaint" class="form-control" placeholder="e.g. headache, stomach pain…">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Symptoms</label>
            <textarea id="sbSymptoms" class="form-control" rows="2"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Diagnosis</label>
            <textarea id="sbDiagnosis" class="form-control" rows="2"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Treatment Given</label>
            <textarea id="sbTreatment" class="form-control" rows="2"></textarea>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Temperature (°C)</label>
            <input type="number" id="sbTemp" class="form-control" step="0.1" placeholder="36.5">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Weight (kg)</label>
            <input type="number" id="sbWeight" class="form-control" step="0.1" placeholder="—">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Medication Given</label>
            <input type="text" id="sbMeds" class="form-control" placeholder="Drug name, dosage…">
          </div>
          <div class="col-md-6">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" id="sbReferred"
                     onchange="document.getElementById('sbRefHospital').closest('.col-md-6').style.display=this.checked?'block':'none'">
              <label class="form-check-label fw-semibold" for="sbReferred">Referred to hospital</label>
            </div>
          </div>
          <div class="col-md-6" style="display:none">
            <label class="form-label fw-semibold">Hospital Name</label>
            <input type="text" id="sbRefHospital" class="form-control">
          </div>
          <div class="col-md-6">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" id="sbParentNotified">
              <label class="form-check-label fw-semibold" for="sbParentNotified">Parent notified</label>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select id="sbStatus" class="form-select">
              <option value="active">Active (in sick bay)</option>
              <option value="dismissed">Dismissed</option>
              <option value="referred">Referred</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notes</label>
            <textarea id="sbNotes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div id="sbError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="sickBayController.saveVisit()">Save Visit</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/sick_bay.js?v=<?= time() ?>"></script>
<script>document.addEventListener('DOMContentLoaded', () => sickBayController.init());</script>
