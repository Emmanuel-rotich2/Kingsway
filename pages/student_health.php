<?php
/**
 * Student Health Records — PARTIAL
 * View/manage student medical profiles and vaccination records.
 * JS controller: js/pages/student_health.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-heart-pulse me-2 text-danger"></i>Student Health Records</h2>
      <small class="text-muted">Medical profiles · Vaccinations · Health history</small>
    </div>
    <button class="btn btn-primary" onclick="healthController.showRecordModal()">
      <i class="bi bi-plus-circle me-1"></i> Add / Update Record
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="hStatActiveSickBay">—</div>
          <div class="text-muted small">In Sick Bay</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="hStatToday">—</div>
          <div class="text-muted small">Visits Today</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="hStatRecords">—</div>
          <div class="text-muted small">Health Profiles</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="hStatVaxDue">—</div>
          <div class="text-muted small">Vax Due (30d)</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3" id="healthTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#hTabRecords">Health Profiles</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#hTabVax">Vaccinations</button></li>
  </ul>

  <div class="tab-content">

    <!-- HEALTH RECORDS TAB -->
    <div class="tab-pane fade show active" id="hTabRecords">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <input type="text" id="hSearch" class="form-control" placeholder="Search by name or admission no…"
                     oninput="healthController.loadRecords()">
            </div>
          </div>
          <div id="healthRecordsContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- VACCINATIONS TAB -->
    <div class="tab-pane fade" id="hTabVax">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="vaxDueOnly"
                     onchange="healthController.loadVaccinations()">
              <label class="form-check-label" for="vaxDueOnly">Show due in 30 days only</label>
            </div>
            <button class="btn btn-outline-primary btn-sm" onclick="healthController.showVaxModal()">
              <i class="bi bi-plus me-1"></i> Record Vaccination
            </button>
          </div>
          <div id="vaccinationsContainer">
            <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- HEALTH RECORD MODAL -->
<div class="modal fade" id="healthRecordModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add / Update Health Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-12">
            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
            <select id="hrStudentId" class="form-select">
              <option value="">— Select student —</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Blood Group</label>
            <select id="hrBloodGroup" class="form-select">
              <option value="Unknown">Unknown</option>
              <option value="A+">A+</option><option value="A-">A-</option>
              <option value="B+">B+</option><option value="B-">B-</option>
              <option value="AB+">AB+</option><option value="AB-">AB-</option>
              <option value="O+">O+</option><option value="O-">O-</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Medical Aid Provider</label>
            <input type="text" id="hrMedAidProvider" class="form-control" placeholder="Insurance/NHIF">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Medical Aid No.</label>
            <input type="text" id="hrMedAidNo" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Allergies</label>
            <textarea id="hrAllergies" class="form-control" rows="2" placeholder="e.g. Penicillin, peanuts…"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Chronic Conditions</label>
            <textarea id="hrChronic" class="form-control" rows="2" placeholder="e.g. Asthma, diabetes…"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Special Diet Requirements</label>
            <textarea id="hrDiet" class="form-control" rows="2" placeholder="e.g. Vegetarian, gluten-free…"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Disability / Special Needs Notes</label>
            <textarea id="hrDisability" class="form-control" rows="2"></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Doctor / GP Name</label>
            <input type="text" id="hrDoctorName" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Doctor Phone</label>
            <input type="tel" id="hrDoctorPhone" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Emergency Contact Name</label>
            <input type="text" id="hrEcName" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Emergency Contact Phone</label>
            <input type="tel" id="hrEcPhone" class="form-control">
          </div>
          <div class="col-md-8">
            <label class="form-label fw-semibold">Additional Notes</label>
            <textarea id="hrNotes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div id="hrError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="healthController.saveRecord()">Save Profile</button>
      </div>
    </div>
  </div>
</div>

<!-- VACCINATION MODAL -->
<div class="modal fade" id="vaxModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Record Vaccination</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
            <select id="vaxStudentId" class="form-select">
              <option value="">— Select student —</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label fw-semibold">Vaccine Name <span class="text-danger">*</span></label>
            <input type="text" id="vaxName" class="form-control" placeholder="e.g. Polio OPV, BCG, MMR…">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Dose #</label>
            <input type="number" id="vaxDose" class="form-control" value="1" min="1">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Date Given <span class="text-danger">*</span></label>
            <input type="date" id="vaxDateGiven" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Next Due Date</label>
            <input type="date" id="vaxNextDue" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Given By</label>
            <input type="text" id="vaxGivenBy" class="form-control" placeholder="Nurse / clinic">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Batch Number</label>
            <input type="text" id="vaxBatch" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notes</label>
            <textarea id="vaxNotes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div id="vaxError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="healthController.saveVax()">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/student_health.js?v=<?= time() ?>"></script>
<script>document.addEventListener('DOMContentLoaded', () => healthController.init());</script>
