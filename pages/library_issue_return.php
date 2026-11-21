<?php
/* Library Issue & Return — issue books and process returns. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-book-half text-info me-2"></i>Library Issue &amp; Return</h4>
      <p class="text-muted small mb-0 mt-1">Issue library books to students or staff and process returns.</p>
    </div>
    <button class="btn btn-outline-info btn-sm" data-kw-onclick="libraryIssueReturnController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="bg-white border rounded-3 p-3">
        <h6 class="fw-semibold mb-3"><i class="bi bi-plus-circle me-1"></i>Issue a Book</h6>
        <div class="mb-2"><label class="form-label small fw-semibold">Book</label>
          <select class="form-select form-select-sm" id="lirBook"><option value="">Select book…</option></select>
        </div>
        <div class="mb-2"><label class="form-label small fw-semibold">Borrower Type</label>
          <select class="form-select form-select-sm" id="lirType" data-kw-onchange="libraryIssueReturnController.onTypeChange()">
            <option value="student">Student</option>
            <option value="staff">Staff</option>
          </select>
        </div>
        <div class="mb-2"><label class="form-label small fw-semibold">Borrower</label>
          <select class="form-select form-select-sm" id="lirBorrower"><option value="">Select borrower…</option></select>
        </div>
        <div class="mb-3"><label class="form-label small fw-semibold">Due Date</label>
          <input type="date" class="form-control form-control-sm" id="lirDue">
        </div>
        <button class="btn btn-primary btn-sm w-100" data-kw-onclick="libraryIssueReturnController.issue()" data-year-write><i class="bi bi-arrow-right-circle me-1"></i>Issue Book</button>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Active Loans</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Book</th><th>Borrower</th><th>Ref</th><th>Issued</th><th>Due</th><th></th></tr></thead>
            <tbody id="lirBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/library_issue_return.js'); ?>
