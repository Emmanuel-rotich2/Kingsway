<?php
/* Fee Structure Approvals — Director approval queue for submitted fee bundles. */

?>

<style>
.fs-table td,.fs-table th { vertical-align:middle;font-size:.85rem; }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-check2-square text-success me-2"></i>Fee Structure Approvals</h4>
      <p class="text-muted small mb-0 mt-1">Review fee structure bundles submitted by the accounts office. Approval immediately bills all affected students.</p>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
      <select id="bundleStatusFilter" class="form-select form-select-sm" style="width:160px">
        <option value="submitted">Submitted (pending)</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="">All statuses</option>
      </select>
      <input type="text" id="bundleYearFilter" class="form-control form-control-sm" placeholder="Year e.g. 2026" style="width:120px">
    </div>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="feeApprovalsController.loadData()">
      <i class="bi bi-arrow-clockwise"></i>
    </button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <table class="table table-hover fs-table mb-0">
      <thead class="table-light"><tr>
        <th scope="col">Academic Year</th><th scope="col">Terms</th><th scope="col">Student Types</th><th scope="col">Classes</th><th scope="col">Submitted By</th><th scope="col">Status</th><th class="text-end">Actions</th>
      </tr></thead>
    <tbody id="bundleTableBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
    </table>
  </div>

</div>

<div class="modal fade" id="approvalMatrixModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h5 class="modal-title mb-1">Fee Structure Matrix</h5><small class="text-muted" id="approvalMatrixMeta"></small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="approvalMatrixBody"><div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div> Loading…</div></div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/components/fee_structure_matrix.js'); ?>
<?php asset_script($appBase, 'js/pages/fee_structure_approvals.js'); ?>
