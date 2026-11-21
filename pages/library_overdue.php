<?php
/* Library Overdue — overdue books and pending fines. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-alarm text-danger me-2"></i>Library Overdue</h4>
      <p class="text-muted small mb-0 mt-1">Overdue book loans and outstanding fines.</p>
    </div>
    <button class="btn btn-outline-danger btn-sm" data-kw-onclick="libraryOverdueController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Overdue Books</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Book</th><th>Borrower</th><th>Due Date</th><th>Days Overdue</th></tr></thead>
            <tbody id="loBody"><tr><td colspan="4" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Fines</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Book</th><th>Borrower</th><th>Amount (KES)</th><th>Status</th></tr></thead>
            <tbody id="loFineBody"><tr><td colspan="4" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/library_overdue.js'); ?>
