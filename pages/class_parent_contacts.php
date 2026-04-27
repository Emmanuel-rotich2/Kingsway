<?php
/**
 * Class Parent Contacts — PARTIAL
 * Parent/guardian contact directory for teacher's assigned class.
 * JS controller: js/pages/class_parent_contacts.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-class_parent_contacts">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="mb-0"><i class="bi bi-telephone-outbound me-2 text-primary"></i>Parent Contacts</h3>
      <small class="text-muted">Parent and guardian contact directory for your class</small>
    </div>
    <button class="btn btn-outline-success btn-sm" id="cpExportBtn">
      <i class="bi bi-download me-1"></i> Export CSV
    </button>
  </div>

  <!-- Search & Filter -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-md-5">
          <input type="text" class="form-control form-control-sm" id="cpSearch" placeholder="Search student or parent name…">
        </div>
        <div class="col-md-4">
          <select class="form-select form-select-sm" id="cpClassFilter">
            <option value="self">My Class</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary btn-sm w-100" id="cpSearchBtn">
            <i class="bi bi-search me-1"></i> Search
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Contacts Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center py-2">
      <i class="bi bi-people me-2 text-primary"></i>
      <span class="fw-semibold">Contact Directory</span>
      <span class="ms-auto badge bg-secondary" id="cpContactCount">0 contacts</span>
    </div>
    <div class="card-body p-0">
      <div id="cpLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Loading contacts…</p>
      </div>
      <div id="cpContent" style="display:none;">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Student</th>
                <th>Parent / Guardian</th>
                <th>Relationship</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Preferred Contact</th>
                <th>Last Contacted</th>
              </tr>
            </thead>
            <tbody id="cpTableBody"></tbody>
          </table>
        </div>
      </div>
      <div id="cpEmpty" style="display:none;" class="text-center py-5">
        <i class="bi bi-people fs-1 text-muted"></i>
        <p class="text-muted mt-2 mb-0">No parent contacts found.</p>
      </div>
    </div>
  </div>

</div>
<script src="<?= $appBase ?>/js/pages/class_parent_contacts.js?v=<?= time() ?>"></script>
