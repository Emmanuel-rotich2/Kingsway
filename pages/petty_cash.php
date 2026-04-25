<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-petty-cash">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-wallet2 me-2 text-success"></i>Petty Cash</h3>
      <small class="text-muted">Day-to-day cash expenses, top-ups, and running balance</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-sm btn-outline-secondary" onclick="pettyCashController.exportCSV()">
        <i class="bi bi-download me-1"></i> Export
      </button>
      <button class="btn btn-sm btn-success" id="addPcBtn" onclick="pettyCashController.showModal()"
              style="display:none">
        <i class="bi bi-plus-circle me-1"></i> Record Transaction
      </button>
    </div>
  </div>

  <!-- KPI Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-success" id="kpiCurrentBalance">—</div>
          <div class="text-muted small">Current Balance</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-danger" id="kpiExpensesMonth">—</div>
          <div class="text-muted small">Expenses This Month</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-primary" id="kpiTopupsMonth">—</div>
          <div class="text-muted small">Top-ups This Month</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-5 fw-bold text-secondary" id="kpiLastReconciliation">—</div>
          <div class="text-muted small">Last Reconciliation</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-md-3">
          <input type="text" class="form-control form-control-sm" id="pcSearch" placeholder="Search description, vendor…">
        </div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" id="pcTypeFilter">
            <option value="">All Types</option>
            <option value="expense">Expense</option>
            <option value="top_up">Top-up</option>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" id="pcCategoryFilter">
            <option value="">All Categories</option>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" class="form-control form-control-sm" id="pcDateFrom">
        </div>
        <div class="col-md-2">
          <input type="date" class="form-control form-control-sm" id="pcDateTo">
        </div>
        <div class="col-md-1">
          <button class="btn btn-sm btn-outline-secondary w-100" onclick="pettyCashController.clearFilters()">Clear</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Description</th>
              <th>Category</th>
              <th>Type</th>
              <th class="text-end">Amount (KES)</th>
              <th class="text-end">Balance After</th>
              <th>Recorded By</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="pettyCashTableBody">
            <tr><td colspan="9" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-success"></div>
              <span class="ms-2 text-muted">Loading…</span>
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end py-2">
      <ul class="pagination pagination-sm mb-0" id="pcPagination"></ul>
    </div>
  </div>
</div>

<!-- ============================================================
     PETTY CASH TRANSACTION MODAL
     ============================================================ -->
<div class="modal fade" id="pettyCashModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-wallet2 me-2"></i>Record Petty Cash</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="pc_id">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
            <select class="form-select" id="pc_type" required>
              <option value="expense">Expense (withdrawal)</option>
              <option value="top_up">Top-up (replenishment)</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Amount (KES) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="pc_amount" step="0.01" min="1" required>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="pc_date" required>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Category</label>
            <select class="form-select" id="pc_category_id">
              <option value="">— Select category —</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
            <textarea class="form-control" id="pc_description" rows="2"
                      placeholder="What is this for?" required></textarea>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Vendor / Payee</label>
            <input type="text" class="form-control" id="pc_vendor_name" placeholder="Who was paid">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Receipt Number</label>
            <input type="text" class="form-control" id="pc_receipt_number" placeholder="Receipt/invoice no.">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notes</label>
            <input type="text" class="form-control" id="pc_notes">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" onclick="pettyCashController.save()">Save Record</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>js/pages/petty_cash.js"></script>
