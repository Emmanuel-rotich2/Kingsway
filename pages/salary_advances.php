<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-salary-advances">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-cash-stack me-2 text-warning"></i>Salary Advances</h3>
      <small class="text-muted">Staff salary advance requests, approvals, and payroll deduction tracking</small>
    </div>
    <button class="btn btn-sm btn-warning" id="addAdvanceBtn" style="display:none"
            onclick="salaryAdvancesController.showModal()">
      <i class="bi bi-plus-circle me-1"></i> Request Advance
    </button>
  </div>

  <!-- KPI Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-warning" id="statTotalIssued">—</div>
          <div class="text-muted small">Total Issued (KES)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-danger" id="statTotalOutstanding">—</div>
          <div class="text-muted small">Outstanding Balance</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-secondary" id="statPendingCount">—</div>
          <div class="text-muted small">Pending Approval</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-primary" id="statTotalCount">—</div>
          <div class="text-muted small">Total Requests</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-md-4">
          <input type="text" class="form-control form-control-sm" id="advSearch"
                 placeholder="Search staff name, employee no, advance no…">
        </div>
        <div class="col-md-3">
          <select class="form-select form-select-sm" id="advStatusFilter">
            <option value="">All Statuses</option>
            <option value="pending">Pending Approval</option>
            <option value="active">Active (being deducted)</option>
            <option value="fully_deducted">Fully Deducted</option>
            <option value="rejected">Rejected</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-sm btn-outline-secondary w-100" onclick="salaryAdvancesController.clearFilters()">Clear</button>
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
              <th>Advance #</th>
              <th>Staff Member</th>
              <th class="text-end">Requested</th>
              <th class="text-end">Approved</th>
              <th>Schedule</th>
              <th>Start Month</th>
              <th class="text-end">Deducted</th>
              <th class="text-end">Remaining</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="advTableBody">
            <tr><td colspan="10" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-warning"></div>
              <span class="ms-2 text-muted">Loading…</span>
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-between py-2">
      <small class="text-muted" id="advTableInfo"></small>
      <ul class="pagination pagination-sm mb-0" id="advPagination"></ul>
    </div>
  </div>
</div>

<!-- ============================================================
     REQUEST MODAL
     ============================================================ -->
<div class="modal fade" id="advanceModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title"><i class="bi bi-cash-stack me-2"></i>Request Salary Advance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="adv_id">
        <div class="alert alert-info small">
          <i class="bi bi-info-circle me-2"></i>
          Maximum advance is one month's basic salary. Cannot have two active advances simultaneously.
          Amount is deducted automatically from payroll.
        </div>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Staff Member <span class="text-danger">*</span></label>
            <select class="form-select" id="adv_staff_id" required></select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Amount Requested (KES) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="adv_amount" step="100" min="500" required>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Deduction Schedule</label>
            <select class="form-select" id="adv_schedule">
              <option value="single_month">Single month (full deduction)</option>
              <option value="two_months">Spread over 2 months</option>
              <option value="three_months">Spread over 3 months</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
            <textarea class="form-control" id="adv_reason" rows="3"
                      placeholder="State the purpose of the advance…" required></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning" onclick="salaryAdvancesController.save()">Submit Request</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     APPROVAL MODAL
     ============================================================ -->
<div class="modal fade" id="approveAdvModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" id="approveAdvHeader">
        <h5 class="modal-title" id="approveAdvTitle">Approve Advance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="approve_adv_id">
        <input type="hidden" id="approve_adv_action">
        <div class="card bg-light p-3 mb-3 small" id="advSummaryBox"></div>
        <div id="approveFields">
          <div class="mb-3">
            <label class="form-label fw-semibold">Approved Amount (KES)</label>
            <input type="number" class="form-control" id="approve_amount" step="100" min="1">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Deduction Start Month</label>
            <input type="month" class="form-control" id="approve_start_month">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" id="approveNotesLabel">Notes / Reason</label>
          <textarea class="form-control" id="approve_notes" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn" id="approveAdvBtn" onclick="salaryAdvancesController.confirmApproval()">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/salary_advances.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => salaryAdvancesController.init());</script>
