<?php /* School-domain partial */ ?>
<div class="container-fluid py-4" id="staffMigrationPage">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h3 class="mb-1"><i class="bi bi-people-fill me-2 text-primary"></i>Existing Staff Migration</h3><p class="text-muted mb-0">Validate the complete CSV or Excel file before creating staff, user accounts, roles and invitation emails.</p></div>
    <div class="d-flex gap-2">
      <button id="smTemplateCsv" class="btn btn-outline-primary" type="button"><i class="bi bi-download me-1"></i>CSV Template</button>
      <button id="smTemplateXlsx" class="btn btn-outline-primary" type="button"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel Template</button>
      <button id="smRefresh" class="btn btn-outline-secondary" type="button"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
  </div>
  <div id="smState" class="alert alert-info">Loading staff migration workspace…</div>
  <div class="row g-4">
    <div class="col-lg-5"><div class="card shadow-sm border-0"><div class="card-body">
      <label class="form-label fw-semibold" for="smFile">Completed staff CSV or Excel file</label><input id="smFile" class="form-control" type="file" accept=".csv,.xlsx,.xls">
      <div class="form-text">The import is atomic: no staff record is created while any row is invalid.</div>
      <button id="smPreview" class="btn btn-primary mt-3" disabled><i class="bi bi-shield-check me-1"></i>Validate File</button>
    </div></div></div>
    <div class="col-lg-7"><div class="card shadow-sm border-0"><div class="card-header bg-transparent fw-semibold">Reference values</div><div class="card-body" id="smReference"></div></div></div>
  </div>
  <div class="card shadow-sm border-0 mt-4 d-none" id="smPreviewCard"><div class="card-header bg-transparent d-flex justify-content-between"><span class="fw-semibold">Validation result</span><div class="d-flex gap-2"><button id="smRollback" class="btn btn-outline-danger btn-sm" disabled>Safe Rollback</button><button id="smCommit" class="btn btn-success btn-sm" disabled>Commit Import</button></div></div><div class="card-body"><div id="smSummary" class="row g-2 mb-3"></div><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Row</th><th>Staff</th><th>Email</th><th>Department</th><th>Status</th></tr></thead><tbody id="smRows"></tbody></table></div></div></div>
  <div class="card shadow-sm border-0 mt-4"><div class="card-header bg-transparent fw-semibold">Import history</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Batch</th><th>File</th><th>Rows</th><th>Status</th><th>Imported by</th><th>Date</th><th>Actions</th></tr></thead><tbody id="smBatches"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody></table></div></div>
</div>
<?php $importExistingStaffJs = __DIR__ . '/../js/pages/import_existing_staff.js'; ?>
<script src="<?= $appBase ?>/js/pages/import_existing_staff.js?v=<?= file_exists($importExistingStaffJs) ? filemtime($importExistingStaffJs) : time() ?>"></script>
