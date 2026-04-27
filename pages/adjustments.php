<?php
/**
 * Financial Adjustments — PARTIAL
 * Journal/manual adjustment entries: fee waivers, discounts, corrections, refunds.
 * Accountant creates; Director/Senior approves.
 * JS controller: js/pages/adjustments.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-sliders me-2 text-warning"></i>Financial Adjustments</h2>
      <small class="text-muted">Journal entries · Fee corrections · Approvals</small>
    </div>
    <button class="btn btn-primary" onclick="adjustmentsController.showNewModal()">
      <i class="bi bi-plus-circle me-1"></i> New Adjustment
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-warning" id="ajStatPending">—</div>
          <div class="text-muted small">Pending Approval</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-success" id="ajStatApproved">—</div>
          <div class="text-muted small">Approved This Month</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-primary" id="ajStatAmount">—</div>
          <div class="text-muted small">Total Adjusted (KES)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-danger" id="ajStatRejected">—</div>
          <div class="text-muted small">Rejected</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter Tabs + Export -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <ul class="nav nav-tabs" id="ajTabs">
      <li class="nav-item">
        <button class="nav-link active" data-status="" onclick="adjustmentsController.switchTab(this, '')">All</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-status="pending" onclick="adjustmentsController.switchTab(this, 'pending')">Pending</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-status="approved" onclick="adjustmentsController.switchTab(this, 'approved')">Approved</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-status="rejected" onclick="adjustmentsController.switchTab(this, 'rejected')">Rejected</button>
      </li>
    </ul>
    <button class="btn btn-outline-secondary btn-sm" onclick="adjustmentsController.exportCSV()">
      <i class="bi bi-download me-1"></i> Export CSV
    </button>
  </div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Ref No</th>
              <th>Date</th>
              <th>Type</th>
              <th>Student / Account</th>
              <th class="text-end">Amount (KES)</th>
              <th>Reason</th>
              <th>Submitted By</th>
              <th class="text-center">Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="ajTableBody">
            <tr><td colspan="9" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-primary"></div> Loading…
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- New Adjustment Modal -->
<div class="modal fade" id="ajModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-sliders me-2"></i>New Adjustment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
            <select id="ajType" class="form-select">
              <option value="">— Select type —</option>
              <option value="fee_waiver">Fee Waiver</option>
              <option value="discount">Discount</option>
              <option value="correction">Correction</option>
              <option value="overpayment_refund">Overpayment Refund</option>
              <option value="write_off">Write-off</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Reference / Invoice No.</label>
            <input type="text" id="ajRefNo" class="form-control" placeholder="e.g. INV-2026-001">
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Student</label>
            <select id="ajStudentId" class="form-select">
              <option value="">— Select student (leave blank for general ledger) —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Amount (KES) <span class="text-danger">*</span></label>
            <div class="input-group">
              <select id="ajSign" class="form-select" style="max-width:90px;">
                <option value="+">+ (Credit)</option>
                <option value="-">- (Debit)</option>
              </select>
              <input type="number" id="ajAmount" class="form-control" min="0" step="0.01" placeholder="0.00">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Supporting Document</label>
            <input type="file" id="ajDoc" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
          </div>
          <div class="col-md-12">
            <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
            <textarea id="ajReason" class="form-control" rows="3" placeholder="Provide a clear reason for this adjustment…"></textarea>
          </div>
        </div>
        <div id="ajModalError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="adjustmentsController.saveAdjustment()">
          <i class="bi bi-send me-1"></i> Submit for Approval
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Approve / Reject Modal -->
<div class="modal fade" id="ajApproveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ajApproveModalTitle">Approve / Reject Adjustment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ajApproveId">
        <input type="hidden" id="ajApproveAction">
        <div class="mb-3">
          <label class="form-label fw-semibold">Adjustment Ref: <span id="ajApproveRef" class="text-primary"></span></label>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Notes / Reason</label>
          <textarea id="ajApproveNotes" class="form-control" rows="3" placeholder="Optional remarks…"></textarea>
        </div>
        <div id="ajApproveError" class="alert alert-danger d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="ajApproveConfirmBtn"
                onclick="adjustmentsController.confirmApproveReject()">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/adjustments.js"></script>
