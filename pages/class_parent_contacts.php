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
      <small class="text-muted">Parents and guardians linked to learners in your assigned class</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-dark btn-sm" id="cpPrintBtn">
        <i class="bi bi-printer me-1"></i> Print
      </button>
      <button class="btn btn-outline-success btn-sm" id="cpExportBtn">
        <i class="bi bi-download me-1"></i> Export CSV
      </button>
    </div>
  </div>

  <!-- Search within the teacher's class list -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-12">
          <input type="text" class="form-control form-control-sm" id="cpSearch" placeholder="Search student, parent name or phone…">
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
                <th scope="col">Student</th>
                <th scope="col">Parent / Guardian</th>
                <th scope="col">Relationship</th>
                <th scope="col">Phone</th>
                <th scope="col">Email</th>
                <th scope="col">Preferred Contact</th>
                <th scope="col">Last Contacted</th>
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
<?php asset_script($appBase, 'js/pages/class_parent_contacts.js'); ?>
