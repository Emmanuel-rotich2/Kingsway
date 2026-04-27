<?php
/**
 * Cash Reconciliation — PARTIAL
 * Daily cash reconciliation: compare system-recorded cash vs physical count.
 * JS controller: js/pages/cash_reconciliation.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-cash-stack me-2 text-success"></i>Cash Reconciliation</h2>
      <small class="text-muted">Daily cash count vs system-recorded collections</small>
    </div>
    <div class="d-flex align-items-center gap-2">
      <input type="date" id="crDate" class="form-control" style="width:170px;">
      <button class="btn btn-primary" onclick="cashReconcController.loadDay(document.getElementById('crDate').value)">
        <i class="bi bi-search me-1"></i> Load
      </button>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-primary" id="crSystemTotal">—</div>
          <div class="text-muted small">System Total (KES)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-success" id="crPhysicalTotal">—</div>
          <div class="text-muted small">Physical Count (KES)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-3 fw-bold text-warning" id="crVariance">—</div>
          <div class="text-muted small">Variance (KES)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-5 fw-bold" id="crStatus">—</div>
          <div class="text-muted small">Status</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Reconciliation Form (shown after loading) -->
  <div id="crFormSection" style="display:none;">
    <div class="row g-4 mb-4">

      <!-- System Recorded -->
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-light fw-semibold">
            <i class="bi bi-receipt me-1 text-primary"></i> System Recorded Cash Transactions
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Time</th>
                    <th>Description</th>
                    <th class="text-end">Amount (KES)</th>
                  </tr>
                </thead>
                <tbody id="crSystemTable">
                  <tr><td colspan="3" class="text-center py-3 text-muted">Load a date to see transactions</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Physical Count Entry -->
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-light fw-semibold">
            <i class="bi bi-calculator me-1 text-success"></i> Physical Count Entry
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-3">
                <thead class="table-light">
                  <tr>
                    <th>Denomination</th>
                    <th style="width:120px;">Count</th>
                    <th class="text-end">Subtotal (KES)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>KES 1,000 notes</td>
                    <td><input type="number" min="0" value="0" class="form-control form-control-sm" id="crDenom1000" oninput="cashReconcController.computePhysicalTotal()"></td>
                    <td class="text-end" id="crSub1000">0.00</td>
                  </tr>
                  <tr>
                    <td>KES 500 notes</td>
                    <td><input type="number" min="0" value="0" class="form-control form-control-sm" id="crDenom500" oninput="cashReconcController.computePhysicalTotal()"></td>
                    <td class="text-end" id="crSub500">0.00</td>
                  </tr>
                  <tr>
                    <td>KES 200 notes</td>
                    <td><input type="number" min="0" value="0" class="form-control form-control-sm" id="crDenom200" oninput="cashReconcController.computePhysicalTotal()"></td>
                    <td class="text-end" id="crSub200">0.00</td>
                  </tr>
                  <tr>
                    <td>KES 100 notes</td>
                    <td><input type="number" min="0" value="0" class="form-control form-control-sm" id="crDenom100" oninput="cashReconcController.computePhysicalTotal()"></td>
                    <td class="text-end" id="crSub100">0.00</td>
                  </tr>
                  <tr>
                    <td>KES 50 coins</td>
                    <td><input type="number" min="0" value="0" class="form-control form-control-sm" id="crDenom50" oninput="cashReconcController.computePhysicalTotal()"></td>
                    <td class="text-end" id="crSub50">0.00</td>
                  </tr>
                  <tr>
                    <td>KES 20 coins</td>
                    <td><input type="number" min="0" value="0" class="form-control form-control-sm" id="crDenom20" oninput="cashReconcController.computePhysicalTotal()"></td>
                    <td class="text-end" id="crSub20">0.00</td>
                  </tr>
                  <tr>
                    <td>KES 10 coins</td>
                    <td><input type="number" min="0" value="0" class="form-control form-control-sm" id="crDenom10" oninput="cashReconcController.computePhysicalTotal()"></td>
                    <td class="text-end" id="crSub10">0.00</td>
                  </tr>
                  <tr>
                    <td>KES 5 coins</td>
                    <td><input type="number" min="0" value="0" class="form-control form-control-sm" id="crDenom5" oninput="cashReconcController.computePhysicalTotal()"></td>
                    <td class="text-end" id="crSub5">0.00</td>
                  </tr>
                  <tr>
                    <td>KES 1 coins</td>
                    <td><input type="number" min="0" value="0" class="form-control form-control-sm" id="crDenom1" oninput="cashReconcController.computePhysicalTotal()"></td>
                    <td class="text-end" id="crSub1">0.00</td>
                  </tr>
                  <tr class="table-success fw-bold">
                    <td colspan="2">Physical Total</td>
                    <td class="text-end" id="crPhysicalTotalRow">0.00</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Notes / Variance Explanation</label>
              <textarea id="crNotes" class="form-control" rows="3" placeholder="Explain any variance or add remarks…"></textarea>
            </div>

            <div class="d-grid">
              <button class="btn btn-success" onclick="cashReconcController.submitReconciliation()">
                <i class="bi bi-check-circle me-1"></i> Submit Reconciliation
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- History Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-light fw-semibold">
      <i class="bi bi-clock-history me-1"></i> Reconciliation History
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th class="text-end">System Total</th>
              <th class="text-end">Physical Count</th>
              <th class="text-end">Variance</th>
              <th class="text-center">Status</th>
              <th>Submitted By</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="crHistoryTable">
            <tr><td colspan="7" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-primary"></div> Loading history…
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- View Detail Modal -->
<div class="modal fade" id="crViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cash-stack me-2"></i>Reconciliation Detail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="crViewModalBody">
        <div class="text-center py-3"><div class="spinner-border text-primary"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/cash_reconciliation.js"></script>
