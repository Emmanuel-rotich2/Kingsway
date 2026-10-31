<?php
/** Learners requiring attendance follow-up, scoped by the attendance API. */
?>
<div class="container-fluid mt-3">
  <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
    <div><h2 class="mb-1"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Attendance Follow-up</h2><small class="text-muted">Learners below the attendance follow-up threshold · <span id="arDateRange"></span></small></div>
    <div class="d-flex gap-2"><button class="btn btn-outline-secondary" id="arExportBtn" type="button"><i class="bi bi-download me-1"></i>Export CSV</button><button class="btn btn-outline-secondary" id="arPrintBtn" type="button"><i class="bi bi-printer me-1"></i>Print / PDF</button></div>
  </div>
  <div class="row g-3 mb-4"><div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Learners needing attendance follow-up</div><strong class="fs-2 text-warning" id="arStatAbsent">—</strong></div></div></div></div>
  <div class="card border-0 shadow-sm"><div class="card-body" id="arTableContainer"><div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span></div></div></div>
</div>
<style>@media print { #arExportBtn, #arPrintBtn { display:none !important; } }</style>
<?php asset_script($appBase, 'js/pages/at_risk_students.js'); ?>
