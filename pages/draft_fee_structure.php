<?php
/* Draft Fee Structure — Simple Mode (default). Combined per-grade, per-term, per-student-type.
   Itemized mode is frozen (code exists but not exposed in UI). */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.dfs-grade-table input[type="number"] { width: 110px; text-align: right; font-variant-numeric: tabular-nums; }
.dfs-grade-table th, .dfs-grade-table td { vertical-align: middle; padding: .45rem .5rem; }
.dfs-grade-table .grade-name { font-weight: 600; font-size: .85rem; white-space: nowrap; }
.dfs-grade-table .grade-code { font-size: .72rem; color: #6c757d; }
.dfs-student-type-toggle .btn { min-width: 130px; }
.dfs-student-type-toggle .btn.active { font-weight: 700; }
.dfs-summary-card { background: linear-gradient(135deg, #e8f5e9, #f1f8f4); border-radius: 12px; padding: 1rem 1.25rem; }
.dfs-summary-card .label { font-size: .75rem; color: #6c757d; text-transform: uppercase; letter-spacing: .5px; }
.dfs-summary-card .value { font-size: 1.4rem; font-weight: 700; color: #073b20; }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square text-success me-2"></i>Draft Fee Structure</h4>
      <p class="text-muted small mb-0 mt-1">Create or edit Day and Boarding fee amounts together for every grade and term.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-success btn-sm" onclick="draftFeeStructureController.loadGrid()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
      <button class="btn btn-success btn-sm" onclick="draftFeeStructureController.newBundle()"><i class="bi bi-plus-lg me-1"></i>New Structure</button>
    </div>
  </div>

  <!-- Summary cards -->
  <div class="row g-3 mb-4" id="dfsSummary" style="display:none">
    <div class="col-md-3"><div class="dfs-summary-card"><div class="label">Total Structures</div><div class="value" id="dfsSumTotal">0</div></div></div>
    <div class="col-md-3"><div class="dfs-summary-card"><div class="label">Drafts</div><div class="value" id="dfsSumDraft">0</div></div></div>
    <div class="col-md-3"><div class="dfs-summary-card"><div class="label">Submitted</div><div class="value" id="dfsSumSubmitted">0</div></div></div>
    <div class="col-md-3"><div class="dfs-summary-card"><div class="label">Approved</div><div class="value" id="dfsSumApproved">0</div></div></div>
  </div>

  <!-- Grid -->
  <div class="bg-white border rounded-3 p-3">
    <div class="d-flex gap-2 mb-3 flex-wrap">
      <select id="dfsYearFilter" class="form-select form-select-sm" style="width:170px"><option value="">All years</option></select>
      <select id="dfsTypeFilter" class="form-select form-select-sm" style="width:180px"><option value="">All student types</option></select>
      <button class="btn btn-outline-success btn-sm" onclick="draftFeeStructureController.loadGrid()"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light"><tr>
          <th>Academic Year</th><th>Student Types</th><th>Grades</th><th>Terms</th><th>Status</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody id="dfsBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

  <!-- Draft modal -->
<div class="modal fade" id="dfsModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <div>
        <h5 class="modal-title fw-bold" id="dfsModalTitle">New Fee Structure</h5>
        <small class="text-muted" id="dfsModalSub">Enter the fee amount for each grade and term.</small>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body" id="dfsModalBody"></div>
      <div class="modal-footer border-0">
      <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-outline-success" onclick="draftFeeStructureController.showPrintDialog()"><i class="bi bi-printer me-1"></i>Print</button>
      <button class="btn btn-outline-primary" onclick="draftFeeStructureController.save('draft')"><i class="bi bi-save me-1"></i>Save as Draft</button>
      <button class="btn btn-success" onclick="draftFeeStructureController.save('submit')"><i class="bi bi-send me-1"></i>Submit for Review</button>
    </div>
  </div></div>
</div>

<!-- Print Dialog Modal -->
<div class="modal fade" id="dfsPrintModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-md"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <div>
        <h5 class="modal-title fw-bold"><i class="bi bi-printer text-success me-2"></i>Print Fee Structure</h5>
        <small class="text-muted">Select scope and student type</small>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <!-- Scope -->
      <div class="mb-4">
        <label class="form-label fw-bold text-muted small">SCOPE</label>
        <div class="d-flex flex-column gap-2">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="printScope" id="printScopeAll" value="all" checked>
            <label class="form-check-label" for="printScopeAll">All Grades <small class="text-muted">(Playgroup – Grade 9)</small></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="printScope" id="printScopeECD" value="ecd">
            <label class="form-check-label" for="printScopeECD">ECD <small class="text-muted">(Playgroup – PP2)</small></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="printScope" id="printScopeLowerPri" value="lower_primary">
            <label class="form-check-label" for="printScopeLowerPri">Lower Primary <small class="text-muted">(Grade 1 – 3)</small></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="printScope" id="printScopeUpperPri" value="upper_primary">
            <label class="form-check-label" for="printScopeUpperPri">Upper Primary <small class="text-muted">(Grade 4 – 6)</small></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="printScope" id="printScopePrimary" value="primary">
            <label class="form-check-label" for="printScopePrimary">Primary <small class="text-muted">(Playgroup – Grade 6)</small></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="printScope" id="printScopeJSS" value="jss">
            <label class="form-check-label" for="printScopeJSS">Junior School <small class="text-muted">(Grade 7 – 9)</small></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="printScope" id="printScopeClass" value="class">
            <label class="form-check-label" for="printScopeClass">Specific Class</label>
          </div>
          <div id="printClassSelectWrap" class="ms-4" style="display:none">
            <select id="printClassSelect" class="form-select form-select-sm">
              <option value="">Select class…</option>
            </select>
          </div>
        </div>
      </div>
      <!-- Student Type -->
      <div class="mb-3">
        <label class="form-label fw-bold text-muted small">STUDENT TYPE</label>
        <div class="d-flex flex-column gap-2">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="printStudentType" id="printTypeBoth" value="both" checked>
            <label class="form-check-label" for="printTypeBoth">Both <small class="text-muted">(Day + Boarder)</small></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="printStudentType" id="printTypeDay" value="day">
            <label class="form-check-label" for="printTypeDay">Day Scholar Only</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="printStudentType" id="printTypeBoarder" value="boarder">
            <label class="form-check-label" for="printTypeBoarder">Boarder Only</label>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer border-0">
      <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-success" onclick="draftFeeStructureController.executePrint()"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/draft_fee_structure.js'); ?>
