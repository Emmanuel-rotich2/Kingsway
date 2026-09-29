<?php /* Staff import workspace panel */ ?>
<section class="container-fluid py-3" id="staffMigrationPage" aria-labelledby="smTitle">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
      <button type="button" class="btn btn-link ps-0" data-staff-workspace="directory"><i class="bi bi-arrow-left me-1"></i>Staff directory</button>
      <h3 class="mb-1" id="smTitle">Import existing staff</h3>
      <p class="text-muted mb-0">Choose a completed staff sheet and review its contents before validation.</p>
    </div>
    <div class="dropdown">
      <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download me-1"></i>Download template</button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><button class="dropdown-item" id="smTemplateXlsx" type="button">Excel (.xlsx)</button></li>
        <li><button class="dropdown-item" id="smTemplateCsv" type="button">CSV (.csv)</button></li>
        <li><button class="dropdown-item" id="smTemplateOds" type="button">OpenDocument (.ods)</button></li>
      </ul>
    </div>
  </div>

  <div id="smState" class="small mb-3" role="status" aria-live="polite"></div>
  <div class="card border shadow-sm">
    <div class="card-body p-3 p-md-4">
      <label class="form-label fw-semibold" for="smFile">Select completed staff file</label>
      <input id="smFile" class="form-control" type="file" accept=".csv,.xlsx,.xls,.ods">
      <div class="form-text">CSV, Excel and OpenDocument sheets are supported. The server checks every row before creating accounts.</div>

      <section id="smClientPreview" class="mt-4" aria-live="polite" hidden>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
          <h5 class="mb-0">Import preview</h5><span class="small text-muted" id="smFilename"></span>
        </div>
        <div id="smClientSummary" class="mb-2"></div>
        <div id="smClientTable" class="table-responsive border rounded" style="max-height:min(55vh,620px)"></div>
        <div id="smClientNote" class="form-text mt-2"></div>
        <button id="smPreview" class="btn btn-primary mt-3" type="button" disabled><i class="bi bi-shield-check me-1"></i>Validate all rows</button>
      </section>
    </div>
  </div>

  <section class="card border shadow-sm mt-3 d-none" id="smPreviewCard" aria-live="polite">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span class="fw-semibold">Server validation</span>
      <div class="d-flex gap-2"><button id="smRollback" class="btn btn-outline-danger btn-sm" type="button" disabled>Rollback</button><button id="smCommit" class="btn btn-success btn-sm" type="button" disabled>Create staff and queue invitations</button></div>
    </div>
    <div class="card-body"><div id="smSummary" class="row g-2 mb-3"></div><div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0"><thead id="smRowsHead"></thead><tbody id="smRows"></tbody></table></div></div>
  </section>

</section>
<?php asset_script($appBase, 'public/vendor/sheetjs/xlsx.full.min.js'); ?>
<?php asset_script($appBase, 'js/pages/import_existing_staff.js'); ?>
