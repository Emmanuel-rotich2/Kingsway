<?php
/* Manage School Fee Structure — approved grade/student-type/term matrix. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.mb-fee-matrix input { min-width:90px; }
.mb-fee-matrix th, .mb-fee-matrix td { vertical-align:middle; }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-building text-success me-2"></i>School Fee Structure</h4>
      <p class="text-muted small mb-0 mt-1">Manage the approved grade, student type and term fee matrix.</p>
    </div>
    <button class="btn btn-success btn-sm" onclick="manageBoardingFeeStructureController.edit()"><i class="bi bi-pencil me-1"></i>Edit School Fees</button>
  </div>

  <div class="bg-white border rounded-3 p-3">
    <div class="d-flex gap-2 mb-3 flex-wrap">
      <select id="mbfYear" class="form-select form-select-sm" style="width:170px"></select>
      <select id="mbfLevel" class="form-select form-select-sm" style="width:190px"></select>
      <button class="btn btn-outline-success btn-sm" onclick="manageBoardingFeeStructureController.loadGrid()"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light"><tr>
          <th>Year</th><th>Grade/Level</th><th>Student Type</th><th>Term 1</th><th>Term 2</th><th>Term 3</th><th>Total</th><th>Status</th>
        </tr></thead>
        <tbody id="mbfBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<!-- Boarding fees edit modal -->
<div class="modal fade" id="mbfModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-xl"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <h5 class="modal-title fw-bold">Edit School Fee Structure</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body" id="mbfModalBody"></div>
    <div class="modal-footer border-0">
      <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-outline-primary" onclick="manageBoardingFeeStructureController.save('draft')"><i class="bi bi-save me-1"></i>Save as Draft</button>
      <button class="btn btn-success" onclick="manageBoardingFeeStructureController.save('submit')"><i class="bi bi-send me-1"></i>Save &amp; Submit for Review</button>
    </div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/manage_boarding_fee_structure.js'); ?>
