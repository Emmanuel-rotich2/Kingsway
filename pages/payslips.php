<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via app_layout.php */
?>
<div class="container-fluid py-4" id="payslipsPage">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Payslips &amp; P9 Forms</h3>
      <small class="text-muted">View employee payroll records or your personal payslips</small>
    </div>
    <button class="btn btn-outline-secondary btn-sm" id="psP9Button" onclick="payslipsController.downloadP9()" disabled>
      <i class="bi bi-file-earmark-pdf me-1"></i>P9 Form
    </button>
    <button class="btn btn-outline-primary btn-sm" id="psViewSelected" onclick="payslipsController.viewSelected()" hidden disabled>
      <i class="bi bi-eye me-1"></i>View Selected
    </button>
    <button class="btn btn-outline-dark btn-sm" id="psPrintSelected" onclick="payslipsController.printSelected()" hidden disabled>
      <i class="bi bi-printer me-1"></i>Print Selected
    </button>
    <button class="btn btn-outline-secondary btn-sm" id="psGenerateSelectedP9" onclick="payslipsController.downloadP9()" hidden disabled>
      <i class="bi bi-file-earmark-pdf me-1"></i>Generate P9
    </button>
    <button class="btn btn-outline-success btn-sm" id="psShareEmail" onclick="payslipsController.shareSelected('email')" hidden disabled>
      <i class="bi bi-envelope me-1"></i>Email
    </button>
    <button class="btn btn-outline-success btn-sm" id="psShareWhatsapp" onclick="payslipsController.shareSelected('whatsapp')" hidden disabled>
      <i class="bi bi-whatsapp me-1"></i>WhatsApp
    </button>
  </div>

  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation" id="employeePayslipsTabItem" hidden>
      <button class="nav-link" id="employeePayslipsTab" type="button" role="tab" onclick="payslipsController.switchTab('employees')">
        <i class="bi bi-people me-1"></i>Employee Payslips &amp; P9 Forms
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="personalPayslipsTab" type="button" role="tab" onclick="payslipsController.switchTab('personal')">
        <i class="bi bi-person me-1"></i>Personal Payslips &amp; P9 Forms
      </button>
    </li>
  </ul>

  <!-- Current month highlight card -->
  <div class="card border-0 shadow-sm mb-4 bg-primary text-white" id="psCurrentSummary">
    <div class="card-body">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h5 class="mb-1" id="psCurrentMonth">Loading…</h5>
          <div class="d-flex gap-4 mt-2">
            <div><div class="small opacity-75">Gross Pay</div><div class="fw-bold fs-5" id="psGross">—</div></div>
            <div><div class="small opacity-75">Deductions</div><div class="fw-bold fs-5 text-warning" id="psDeductions">—</div></div>
            <div><div class="small opacity-75">Net Pay</div><div class="fw-bold fs-5" style="color:#a3ffb3" id="psNet">—</div></div>
          </div>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
          <span class="badge bg-light text-primary fs-6 mb-2" id="psStatus">—</span><br>
          <button class="btn btn-light btn-sm" onclick="payslipsController.viewSlip(null)">
            <i class="bi bi-eye me-1"></i>View Full Payslip
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Year filter -->
  <div class="row g-2 mb-3 align-items-end">
    <div class="col-auto">
      <label class="form-label small fw-semibold">Year</label>
      <select id="psYear" class="form-select form-select-sm" onchange="payslipsController.load()"></select>
    </div>
  </div>

  <!-- History table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">Month</th>
              <th class="text-end">Gross Pay</th>
              <th class="text-end">PAYE</th>
              <th class="text-end">SHIF</th>
              <th class="text-end">NSSF</th>
              <th class="text-end">Net Pay</th>
              <th scope="col">Status</th>
              <th scope="col"></th>
            </tr>
          </thead>
          <tbody id="psTableBody">
            <tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Payslip detail modal -->
  <div class="modal fade" id="payslipModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Payslip Detail</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="payslipModalBody">
          <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary" onclick="payslipsController.printCurrentSlip()"><i class="bi bi-printer me-1"></i>Print</button>
        </div>
      </div>
    </div>
  </div>

</div>
<?php asset_script($appBase, 'js/pages/staff_access.js'); ?>
<?php asset_script($appBase, 'js/pages/payslips.js'); ?>
