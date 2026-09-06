<?php
/* Accountant-only admission payment desk. */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">
  <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-phone text-success me-2"></i>Admission Payment Desk</h4>
      <p class="text-muted small mb-0">Accountant work queue for applicants who have reached the Fees/Admission Payment stage.</p>
    </div>
    <button id="mapRefresh" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="alert alert-info border-0 shadow-sm small mb-4">
    Select an applicant, confirm the system-calculated admission amount, and send an M-Pesa prompt to the parent. The payment is posted and the admission workflow is updated automatically after the provider callback. No manual amount or student-account entry is allowed here.
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Applicants at payment stage</div><div id="mapQueueCount" class="fs-3 fw-bold">0</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Awaiting M-Pesa confirmation</div><div id="mapPendingCount" class="fs-3 fw-bold text-warning">0</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Confirmed in this queue</div><div id="mapConfirmedCount" class="fs-3 fw-bold text-success">0</div></div></div></div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3">
      <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <h6 class="fw-bold mb-0">Applicants awaiting admission payment</h6>
        <input id="mapSearch" class="form-control form-control-sm" style="max-width:320px" placeholder="Search application or applicant">
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Application</th><th>Applicant / Parent</th><th>Grade</th><th>Admission amount (KES)</th><th>Payment status</th><th class="text-end">Action</th></tr></thead>
        <tbody id="mapBody"><tr><td colspan="6" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading applicants…</td></tr></tbody>
      </table>
    </div>
  </div>

  <div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-0 pt-3">
      <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <div><h6 class="fw-bold mb-1">Admission fees received</h6><div class="small text-muted">Paid admission applications for the selected academic year.</div></div>
        <select id="mapYear" class="form-select form-select-sm" style="max-width:220px" aria-label="Academic year"></select>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Adm No</th><th>Student name</th><th>Application window</th><th>Class assigned</th><th>Amount paid (KES)</th><th>Date</th><th>Audit</th></tr></thead>
        <tbody id="mapPaidBody"><tr><td colspan="7" class="text-center py-5 text-muted">Loading paid admission fees…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="mapPromptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Send admission payment prompt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div id="mapPromptSummary" class="alert alert-light border small"></div>
      <form id="mapPromptForm">
        <input type="hidden" id="mapApplicationId">
        <div class="mb-3"><label class="form-label small fw-semibold">Amount calculated by system</label><div class="input-group"><span class="input-group-text">KES</span><input id="mapAmount" class="form-control" readonly></div><div class="form-text">The accountant cannot change this amount.</div></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Parent M-Pesa phone</label><input id="mapPhone" class="form-control" type="tel" readonly required><div class="form-text">This is the parent’s registered number. Update the parent record if it is missing or incorrect.</div></div>
        <div id="mapPromptError" class="alert alert-danger small d-none mb-0"></div>
      </form>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="mapPromptForm" id="mapSendPrompt" class="btn btn-success"><i class="bi bi-phone me-1"></i>Send prompt</button></div>
  </div></div>
</div>

<div class="modal fade" id="mapAuditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Admission payment audit trail</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="mapAuditBody"><div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div></div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/manage_admissions_payments.js'); ?>
