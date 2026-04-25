<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-data_import">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-cloud-upload me-2 text-primary"></i>Bulk Data Import</h3>
      <p class="text-muted mb-0">Upload existing school data using Excel (.xlsx) or CSV templates.<br>
        <small>Supports: Students · Staff · Fees · Payments · Expenses · Results · Attendance · Inventory · and more.</small>
      </p>
    </div>
    <button class="btn btn-outline-secondary btn-sm" onclick="dataImportController.showLogs()">
      <i class="bi bi-clock-history me-1"></i> Import History
    </button>
  </div>

  <!-- Step indicator -->
  <div class="d-flex align-items-center mb-4 gap-0" id="diStepBar">
    <div class="di-step active" data-step="1"><span class="di-num">1</span><span class="di-label">Select Type</span></div>
    <div class="di-connector"></div>
    <div class="di-step" data-step="2"><span class="di-num">2</span><span class="di-label">Upload File</span></div>
    <div class="di-connector"></div>
    <div class="di-step" data-step="3"><span class="di-num">3</span><span class="di-label">Preview</span></div>
    <div class="di-connector"></div>
    <div class="di-step" data-step="4"><span class="di-num">4</span><span class="di-label">Confirm</span></div>
    <div class="di-connector"></div>
    <div class="di-step" data-step="5"><span class="di-num">5</span><span class="di-label">Results</span></div>
  </div>

  <!-- ── STEP 1: Select import type ──────────────────────────────────────── -->
  <div id="diStep1">
    <div class="row g-3" id="diCategoryCards">
      <div class="col-12 text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2">Loading import types…</p>
      </div>
    </div>
  </div>

  <!-- ── STEP 2: Upload file ─────────────────────────────────────────────── -->
  <div id="diStep2" style="display:none;">
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-file-earmark-arrow-up me-2 text-primary"></i>Upload File
            <span class="badge bg-primary ms-2" id="diSelectedTypeBadge"></span>
          </div>
          <div class="card-body">
            <!-- Drop zone -->
            <div id="diDropZone"
                 class="border border-2 rounded-3 p-5 text-center mb-3"
                 style="border-style:dashed!important; border-color:#adb5bd!important; cursor:pointer; transition:background .2s;">
              <i class="bi bi-cloud-arrow-up fs-1 text-muted mb-2 d-block"></i>
              <p class="fw-semibold text-muted mb-1">Drag &amp; drop your file here</p>
              <p class="text-muted small mb-3">Supports CSV and Excel (.xlsx / .xls)</p>
              <input type="file" id="diFileInput" class="d-none" accept=".csv,.xlsx,.xls">
              <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('diFileInput').click()">
                <i class="bi bi-folder2-open me-1"></i> Browse File
              </button>
            </div>
            <!-- File info -->
            <div id="diFileInfo" class="d-none">
              <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3">
                <i class="bi bi-file-earmark-check fs-2 text-primary" id="diFileIcon"></i>
                <div class="flex-grow-1">
                  <div class="fw-semibold" id="diFileName">—</div>
                  <div class="text-muted small" id="diFileSize">—</div>
                </div>
                <button class="btn btn-sm btn-outline-danger" onclick="dataImportController.clearFile()">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
            </div>
            <div id="diUploadError" class="alert alert-danger mt-3 d-none"></div>
          </div>
          <div class="card-footer bg-transparent d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="dataImportController.goStep(1)">
              <i class="bi bi-arrow-left me-1"></i> Back
            </button>
            <button class="btn btn-primary ms-auto" id="diPreviewBtn" disabled onclick="dataImportController.runPreview()">
              <i class="bi bi-eye me-1"></i> Preview & Validate
            </button>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-info-circle me-2 text-info"></i>Template Guide
          </div>
          <div class="card-body">
            <p class="text-muted small mb-3">Download the template for your selected type, fill it in, then upload.</p>
            <button class="btn btn-success w-100 mb-3" id="diDownloadTemplateBtn" onclick="dataImportController.downloadTemplate()">
              <i class="bi bi-download me-1"></i> Download CSV Template
            </button>
            <div id="diRequiredCols">
              <h6 class="fw-semibold small text-muted text-uppercase mb-2">Required Columns</h6>
              <div id="diRequiredColsList" class="d-flex flex-wrap gap-1"></div>
            </div>
            <hr>
            <div class="small text-muted">
              <p class="mb-1"><i class="bi bi-check-circle text-success me-1"></i> First row must be column headers</p>
              <p class="mb-1"><i class="bi bi-check-circle text-success me-1"></i> Dates must be YYYY-MM-DD format</p>
              <p class="mb-1"><i class="bi bi-check-circle text-success me-1"></i> Leave optional columns blank if unknown</p>
              <p class="mb-0"><i class="bi bi-check-circle text-success me-1"></i> Max file size: 10 MB</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── STEP 3: Preview ─────────────────────────────────────────────────── -->
  <div id="diStep3" style="display:none;">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-table me-2 text-primary"></i>Data Preview (first 10 rows)</span>
        <span class="badge bg-secondary" id="diPreviewCountBadge">0 rows</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="max-height:340px; overflow-y:auto;">
          <table class="table table-sm table-hover align-middle mb-0 table-bordered">
            <thead class="table-dark sticky-top" id="diPreviewHead"></thead>
            <tbody id="diPreviewBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Validation summary -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-primary" id="diValTotal">—</div>
            <div class="text-muted small">Total Rows</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-success" id="diValValid">—</div>
            <div class="text-muted small">Valid</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-danger" id="diValErrors">—</div>
            <div class="text-muted small">Rows with Errors</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-warning" id="diValMissing">—</div>
            <div class="text-muted small">Missing Columns</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Missing columns warning -->
    <div id="diMissingColsAlert" class="alert alert-danger d-none mb-3">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <strong>Missing required columns:</strong> <span id="diMissingColsList"></span>
    </div>

    <!-- Validation errors table -->
    <div id="diErrorsSection" class="d-none">
      <div class="card border-0 shadow-sm border-start border-danger border-3">
        <div class="card-header bg-transparent d-flex justify-content-between">
          <span class="fw-semibold text-danger"><i class="bi bi-x-circle me-2"></i>Validation Errors</span>
          <button class="btn btn-sm btn-outline-secondary" onclick="dataImportController.downloadErrorReport()">
            <i class="bi bi-download me-1"></i> Download Error Report
          </button>
        </div>
        <div class="card-body p-0" style="max-height:250px;overflow-y:auto;">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Row</th><th>Field</th><th>Error</th></tr></thead>
            <tbody id="diErrorsBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4">
      <button class="btn btn-outline-secondary" onclick="dataImportController.goStep(2)">
        <i class="bi bi-arrow-left me-1"></i> Back
      </button>
      <button class="btn btn-warning" id="diProceedWithErrors" onclick="dataImportController.goStep(4)" style="display:none;">
        <i class="bi bi-exclamation-triangle me-1"></i> Proceed (skip error rows)
      </button>
      <button class="btn btn-primary ms-auto" id="diConfirmBtn" onclick="dataImportController.goStep(4)">
        <i class="bi bi-check-circle me-1"></i> Looks Good — Continue
      </button>
    </div>
  </div>

  <!-- ── STEP 4: Confirm ─────────────────────────────────────────────────── -->
  <div id="diStep4" style="display:none;">
    <div class="card border-0 shadow-sm mb-4 border-start border-primary border-3">
      <div class="card-body">
        <h5 class="fw-semibold"><i class="bi bi-check2-square me-2 text-primary"></i>Ready to Import</h5>
        <div class="row g-3 mt-2" id="diConfirmSummary"></div>
        <div class="alert alert-warning mt-3 mb-0">
          <i class="bi bi-exclamation-triangle me-2"></i>
          <strong>This will write data to the database.</strong> Existing records with matching keys will be updated.
          Make sure you have reviewed the preview before proceeding.
        </div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary" onclick="dataImportController.goStep(3)">
        <i class="bi bi-arrow-left me-1"></i> Back to Preview
      </button>
      <button class="btn btn-success ms-auto btn-lg" id="diRunImportBtn" onclick="dataImportController.runImport()">
        <i class="bi bi-cloud-upload me-2"></i> Run Import Now
      </button>
    </div>
  </div>

  <!-- ── STEP 5: Results ─────────────────────────────────────────────────── -->
  <div id="diStep5" style="display:none;">
    <div class="card border-0 shadow-sm mb-4" id="diResultCard">
      <div class="card-body text-center py-5">
        <div class="spinner-border text-primary mb-3"></div>
        <p class="text-muted">Importing data…</p>
      </div>
    </div>
    <div class="d-flex gap-2 justify-content-center mt-3">
      <button class="btn btn-primary" onclick="dataImportController.reset()">
        <i class="bi bi-plus-circle me-1"></i> Import More Data
      </button>
      <button class="btn btn-outline-secondary" onclick="dataImportController.showLogs()">
        <i class="bi bi-clock-history me-1"></i> View Import History
      </button>
    </div>
  </div>

</div><!-- /page-data_import -->

<!-- Import History Modal -->
<div class="modal fade" id="diLogsModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Import History</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>#</th><th>Type</th><th>File</th><th>Total</th>
                <th>Imported</th><th>Errors</th><th>Status</th>
                <th>Imported By</th><th>Date</th>
              </tr>
            </thead>
            <tbody id="diLogsBody">
              <tr><td colspan="9" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
              </td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.di-step { display:flex; flex-direction:column; align-items:center; gap:4px; }
.di-num { width:32px; height:32px; border-radius:50%; background:#dee2e6; color:#6c757d;
          display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; transition:.2s; }
.di-label { font-size:11px; color:#6c757d; white-space:nowrap; }
.di-connector { flex:1; height:2px; background:#dee2e6; min-width:20px; margin-top:-20px; }
.di-step.active .di-num { background:#0d6efd; color:#fff; }
.di-step.active .di-label { color:#0d6efd; font-weight:600; }
.di-step.done .di-num { background:#198754; color:#fff; }
.di-step.done .di-label { color:#198754; }
.di-category-card { cursor:pointer; transition:box-shadow .15s, border-color .15s; }
.di-category-card:hover { box-shadow:0 0 0 2px #0d6efd !important; }
.di-type-btn { cursor:pointer; transition:background .1s; }
.di-type-btn:hover { background:#f0f4ff !important; }
.di-type-btn.selected { background:#e7f0ff !important; border-color:#0d6efd !important; }
</style>

<script src="<?= $appBase ?>js/pages/data_import.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => dataImportController.init());</script>
