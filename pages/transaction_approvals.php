<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-transaction_approvals">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-check2-square me-2 text-primary"></i>Transaction Approvals</h3>
      <small class="text-muted">Review and approve pending financial transactions</small>
    </div>
    <button class="btn btn-sm btn-outline-secondary" onclick="transactionApprovalsController._loadStats(); transactionApprovalsController._loadPending(); transactionApprovalsController._loadHistory();">
      <i class="bi bi-arrow-clockwise me-1"></i> Refresh
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="taStatPending">—</div>
          <div class="text-muted small">Pending Approval</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="taStatTotalAmt">—</div>
          <div class="text-muted small">Total Pending (KES)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="taStatUrgent">—</div>
          <div class="text-muted small">Urgent (&gt;2 days old)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="taStatApproved">—</div>
          <div class="text-muted small">Approved (Total)</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3" id="taTabs">
    <li class="nav-item">
      <button class="nav-link active" onclick="transactionApprovalsController.switchTab(this,'pending')">
        <i class="bi bi-clock me-1"></i>Pending
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" onclick="transactionApprovalsController.switchTab(this,'history')">
        <i class="bi bi-clock-history me-1"></i>History
      </button>
    </li>
  </ul>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Reference</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Submitted By</th>
              <th>Submitted At</th>
              <th>Age</th>
              <th>Notes</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="taTableBody">
            <tr>
              <td colspan="8" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <span class="ms-2 text-muted">Loading…</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>/js/pages/transaction_approvals.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => transactionApprovalsController.init());</script>
