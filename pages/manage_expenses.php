<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-expenses">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-receipt me-2 text-danger"></i>Expense Management</h3>
      <small class="text-muted">Record, track, and approve school operational expenditures</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-sm btn-outline-secondary" onclick="expensesController.exportCSV()">
        <i class="bi bi-download me-1"></i> Export
      </button>
      <button class="btn btn-sm btn-danger" id="addExpenseBtn" style="display:none"
              onclick="expensesController.showModal()">
        <i class="bi bi-plus-circle me-1"></i> Record Expense
      </button>
    </div>
  </div>

  <!-- KPI Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
              <i class="bi bi-currency-exchange fs-5 text-danger"></i>
            </div>
            <div>
              <div class="text-muted small">Total Expenses (Year)</div>
              <div class="fs-5 fw-bold" id="statTotalExpenses">—</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
              <i class="bi bi-hourglass-split fs-5 text-warning"></i>
            </div>
            <div>
              <div class="text-muted small">Pending Approval</div>
              <div class="fs-5 fw-bold" id="statPendingAmount">—</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
              <i class="bi bi-check-circle fs-5 text-success"></i>
            </div>
            <div>
              <div class="text-muted small">Approved (Unpaid)</div>
              <div class="fs-5 fw-bold" id="statApprovedAmount">—</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
              <i class="bi bi-calendar3 fs-5 text-info"></i>
            </div>
            <div>
              <div class="text-muted small">This Month Paid</div>
              <div class="fs-5 fw-bold" id="statThisMonth">—</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-md-3">
          <input type="text" class="form-control form-control-sm" id="expSearch" placeholder="Search description, vendor…">
        </div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" id="expCategoryFilter">
            <option value="">All Categories</option>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" id="expStatusFilter">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="pending_approval">Pending Approval</option>
            <option value="approved">Approved</option>
            <option value="paid">Paid</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" class="form-control form-control-sm" id="expDateFrom">
        </div>
        <div class="col-md-2">
          <input type="date" class="form-control form-control-sm" id="expDateTo">
        </div>
        <div class="col-md-1">
          <button class="btn btn-sm btn-outline-secondary w-100" onclick="expensesController.clearFilters()">Clear</button>
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
              <th>Ref #</th>
              <th>Date</th>
              <th>Category</th>
              <th>Description</th>
              <th>Vendor</th>
              <th class="text-end">Amount (KES)</th>
              <th>Method</th>
              <th>Recorded By</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="expensesTableBody">
            <tr><td colspan="10" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-danger"></div>
              <span class="ms-2 text-muted">Loading expenses…</span>
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center py-2">
      <small class="text-muted" id="expTableInfo"></small>
      <ul class="pagination pagination-sm mb-0" id="expPagination"></ul>
    </div>
  </div>
</div>

<!-- ============================================================
     EXPENSE MODAL (Create / Edit)
     ============================================================ -->
<div class="modal fade" id="expenseModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="expenseModalTitle">
          <i class="bi bi-receipt me-2"></i>Record Expense
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="exp_id">
        <div class="row g-3">
          <!-- Row 1 -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
            <select class="form-select" id="exp_category_id" required>
              <option value="">Select category…</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Amount (KES) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="exp_amount" step="0.01" min="1" required>
          </div>
          <!-- Row 2 -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Expense Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="exp_date" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
            <select class="form-select" id="exp_payment_method" required>
              <option value="cash">Cash</option>
              <option value="mpesa">M-Pesa</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="cheque">Cheque</option>
              <option value="direct_debit">Direct Debit</option>
            </select>
          </div>
          <!-- Row 3 -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Vendor / Supplier</label>
            <input type="text" class="form-control" id="exp_vendor_name" placeholder="Company or person paid">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Receipt / Invoice No.</label>
            <input type="text" class="form-control" id="exp_receipt_number" placeholder="e.g. INV-2026-001">
          </div>
          <!-- Row 4 -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Reference No. (Cheque/M-Pesa/Bank)</label>
            <input type="text" class="form-control" id="exp_reference_number" placeholder="Transaction reference">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Budget Line Item</label>
            <select class="form-select" id="exp_budget_line_item_id">
              <option value="">— Not linked to budget —</option>
            </select>
          </div>
          <!-- Description -->
          <div class="col-12">
            <label class="form-label fw-semibold">Description / Purpose <span class="text-danger">*</span></label>
            <textarea class="form-control" id="exp_description" rows="3"
                      placeholder="What was this expense for? Be specific." required></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notes (internal)</label>
            <textarea class="form-control" id="exp_notes" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-outline-danger" onclick="expensesController.saveDraft()">Save as Draft</button>
        <button class="btn btn-danger" onclick="expensesController.saveAndSubmit()">Submit for Approval</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     APPROVAL MODAL
     ============================================================ -->
<div class="modal fade" id="approvalModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" id="approvalModalHeader">
        <h5 class="modal-title" id="approvalModalTitle">Approve Expense</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="approval_expense_id">
        <input type="hidden" id="approval_action">
        <div class="mb-3" id="expenseSummaryBox">
          <div class="card bg-light p-3">
            <div class="row g-2 small">
              <div class="col-6"><strong>Amount:</strong> <span id="approvalAmount"></span></div>
              <div class="col-6"><strong>Category:</strong> <span id="approvalCategory"></span></div>
              <div class="col-6"><strong>Date:</strong> <span id="approvalDate"></span></div>
              <div class="col-6"><strong>Vendor:</strong> <span id="approvalVendor"></span></div>
              <div class="col-12"><strong>Description:</strong> <span id="approvalDesc"></span></div>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" id="approvalNotesLabel">Notes (optional)</label>
          <textarea class="form-control" id="approval_notes" rows="3"
                    placeholder="Add any notes or reason…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn" id="approvalConfirmBtn" onclick="expensesController.confirmApproval()">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>js/pages/expenses.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => expensesController.init());</script>
