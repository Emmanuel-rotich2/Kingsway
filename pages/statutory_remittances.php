<?php /** Statutory Remittances — track PAYE/SHIF/NSSF/Housing Levy payments to government agencies */ ?>
<div class="container-fluid py-4" id="statutoryRemittancesPage">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-1"><i class="bi bi-building me-2 text-primary"></i>Statutory Remittances</h3>
      <p class="text-muted mb-0">Track PAYE (KRA), SHIF, NSSF, and Housing Levy remittances to government agencies.</p>
    </div>
    <button class="btn btn-primary" id="srNewBtn"><i class="bi bi-plus-lg me-1"></i>New Remittance</button>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Total Deducted (YTD)</small><h3 id="srTotalDeducted">KES 0.00</h3></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Total Remitted (YTD)</small><h3 id="srTotalRemitted" class="text-success">KES 0.00</h3></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Outstanding Balance</small><h3 id="srOutstanding" class="text-warning">KES 0.00</h3></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Overdue Filings</small><h3 id="srOverdue" class="text-danger">0</h3></div></div></div>
  </div>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
      <div class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label small fw-semibold">Agency</label>
          <select id="srAgencyFilter" class="form-select form-select-sm">
            <option value="">All Agencies</option>
            <option value="KRA">KRA (PAYE)</option>
            <option value="SHIF">SHIF</option>
            <option value="NSSF">NSSF</option>
            <option value="Housing Levy">Housing Levy</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">Year</label>
          <select id="srYearFilter" class="form-select form-select-sm"></select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">Status</label>
          <select id="srStatusFilter" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="filed">Filed</option>
            <option value="paid">Paid</option>
            <option value="overdue">Overdue</option>
            <option value="partial">Partial</option>
          </select>
        </div>
        <div class="col-md-2">
          <button id="srRefreshBtn" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Agency</th>
              <th>Period</th>
              <th class="text-end">Deducted</th>
              <th class="text-end">Remitted</th>
              <th class="text-end">Balance</th>
              <th>Due Date</th>
              <th>Filed Date</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="srTableBody">
            <tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white"><h6 class="mb-0">Monthly Breakdown by Agency</h6></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
          <thead class="table-light">
            <tr><th>Month</th><th class="text-end">KRA (PAYE)</th><th class="text-end">SHIF</th><th class="text-end">NSSF</th><th class="text-end">Housing Levy</th><th class="text-end">Total</th></tr>
          </thead>
          <tbody id="srBreakdownBody">
            <tr><td colspan="6" class="text-center py-3 text-muted">Loading breakdown…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mb-0">Compliance Records</h6>
        <small class="text-muted">Immutable monthly payroll registers, employee deductions, employer costs and effective-dated statutory settings.</small>
      </div>
      <div class="d-flex gap-2 align-items-end">
        <select id="srRegisterMonth" class="form-select form-select-sm" aria-label="Register month"></select>
        <button id="srGenerateRegisterBtn" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-check me-1"></i>Generate Register</button>
        <button id="srCertificateBtn" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-person me-1"></i>Certificate of Service</button>
      </div>
    </div>
    <div class="card-body">
      <h6 class="fw-semibold">Monthly Payroll Registers</h6>
      <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered">
          <thead class="table-light"><tr><th>Period</th><th>Employees</th><th class="text-end">Gross</th><th class="text-end">Employee Deductions</th><th class="text-end">Employer Contributions</th><th>Status</th><th>Retain Until</th></tr></thead>
          <tbody id="srRegisterBody"><tr><td colspan="7" class="text-center text-muted">Loading…</td></tr></tbody>
        </table>
      </div>
      <h6 class="fw-semibold">Certificates of Service</h6>
      <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered">
          <thead class="table-light"><tr><th>Certificate</th><th>Staff</th><th>Employment Period</th><th>Issued</th><th>Status</th><th>Retain Until</th></tr></thead>
          <tbody id="srCertificateBody"><tr><td colspan="6" class="text-center text-muted">Loading…</td></tr></tbody>
        </table>
      </div>
      <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div>
          <h6 class="fw-semibold mb-1">Current Statutory Settings</h6>
          <p class="small text-muted mb-0">These are the rates and tax bands currently used when preparing payroll. A new official notice is added as a new effective-dated setting; completed payrolls are not changed.</p>
        </div>
        <button id="srAddRuleBtn" class="btn btn-primary btn-sm flex-shrink-0"><i class="bi bi-sliders me-1"></i>Manage statutory settings</button>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
          <thead class="table-light"><tr><th>Statutory item</th><th>Employee contribution</th><th>Employer contribution</th><th>How it is applied</th><th>Due</th><th>Effective from</th><th>Reference</th><th class="text-end">Actions</th></tr></thead>
          <tbody id="srRuleBody"><tr><td colspan="8" class="text-center text-muted">Loading…</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="srRuleModal" tabindex="-1" aria-labelledby="srRuleTitle">
  <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header bg-dark text-white"><h5 class="modal-title" id="srRuleTitle">Add Statutory Setting</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <p class="small text-muted">Add a new effective-dated setting when an official rate, band, cap or deadline changes. Updating a row creates a new version; existing payroll records are not rewritten.</p>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Agency</label><select id="srRuleAgency" class="form-select"><option>KRA</option><option>SHIF</option><option>NSSF</option><option>Housing Levy</option></select></div>
        <div class="col-md-4"><label class="form-label">Rule code</label><select id="srRuleCode" class="form-select"><option value="employee_contribution">Employee contribution</option><option value="employee_employer_contribution">Employee + employer contribution</option><option value="paye_bands">PAYE bands</option></select></div>
        <div class="col-md-4"><label class="form-label">Version</label><input id="srRuleVersion" class="form-control" placeholder="e.g. 2026-year-4"></div>
        <div class="col-md-4"><label class="form-label">Effective from</label><input type="date" id="srRuleFrom" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Employee rate (%)</label><input type="number" step="0.0001" id="srRuleEmployeeRate" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Employer rate (%)</label><input type="number" step="0.0001" id="srRuleEmployerRate" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Lower earnings limit</label><input type="number" step="0.01" id="srRuleLowerLimit" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Upper earnings limit</label><input type="number" step="0.01" id="srRuleUpperLimit" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Contribution cap</label><input type="number" step="0.01" id="srRuleCapAmount" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Personal relief</label><input type="number" step="0.01" id="srRuleRelief" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Deadline day</label><input type="number" min="1" max="31" id="srRuleDeadlineDay" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Deadline basis</label><select id="srRuleDeadlineBasis" class="form-select"><option value="working_day_of_following_month">Working day of following month</option><option value="calendar_day_of_following_month">Calendar day of following month</option></select></div>
        <div class="col-md-4"><label class="form-label">Source name</label><input id="srRuleSourceName" class="form-control" placeholder="Official notice / circular"></div>
        <div class="col-md-8"><label class="form-label">Source URL</label><input type="url" id="srRuleSourceUrl" class="form-control"></div>
      </div>
      <div class="mt-3" id="srRuleBandsPanel" style="display:none">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <label class="form-label mb-0">PAYE tax bands</label>
          <button type="button" class="btn btn-sm btn-outline-primary" id="srAddBandBtn"><i class="bi bi-plus-lg me-1"></i>Add tax band</button>
        </div>
        <p class="small text-muted">Enter each income range as separate values. Leave the upper limit blank for the final band.</p>
        <div id="srRuleBandsEditor"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button id="srSaveRuleBtn" class="btn btn-dark">Save Statutory Setting</button></div>
  </div></div>
</div>

<div class="modal fade" id="srModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-building me-2"></i><span id="srModalTitle">New Remittance</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="srEditId">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Agency</label>
            <select id="srAgency" class="form-select" required>
              <option value="KRA">KRA (PAYE)</option>
              <option value="SHIF">SHIF</option>
              <option value="NSSF">NSSF</option>
              <option value="Housing Levy">Housing Levy</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Period Month</label>
            <select id="srMonth" class="form-select" required></select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Period Year</label>
            <input type="number" id="srYear" class="form-control" required>
          </div>
        </div>
        <div class="row g-3 mt-2">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Total Deducted</label>
            <input type="number" id="srDeducted" class="form-control" step="0.01" min="0" readonly>
            <small class="text-muted">Auto-calculated from payslips</small>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Amount Remitted</label>
            <input type="number" id="srRemitted" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Status</label>
            <select id="srStatus" class="form-select" required>
              <option value="pending">Pending</option>
              <option value="filed">Filed (awaiting payment)</option>
              <option value="paid">Paid</option>
              <option value="partial">Partially Paid</option>
            </select>
          </div>
        </div>
        <div class="row g-3 mt-2">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Due Date</label>
            <input type="date" id="srDueDate" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Remittance / Filing Date</label>
            <input type="date" id="srFiledDate" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Filing Reference</label>
            <input type="text" id="srReference" class="form-control" placeholder="Receipt / acknowledgement no.">
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label fw-semibold">Notes</label>
          <textarea id="srNotes" class="form-control" rows="2"></textarea>
        </div>
        <div id="srStaffBreakdown" class="mt-3" style="display:none">
          <h6 class="fw-semibold">Staff Breakdown</h6>
          <div class="table-responsive" style="max-height:200px;overflow:auto">
            <table class="table table-sm table-striped mb-0">
              <thead class="table-light"><tr><th>Staff</th><th class="text-end">Amount</th></tr></thead>
              <tbody id="srStaffBody"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="srSaveBtn" class="btn btn-primary">Save Remittance</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="srPaymentModal" tabindex="-1" aria-labelledby="srPaymentTitle">
  <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header bg-success text-white"><h5 class="modal-title" id="srPaymentTitle">Submit statutory payment</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <p class="small text-muted mb-3">Select the configured agency receiving account. The bank payment will remain pending until a verified callback or status confirmation is received.</p>
      <label class="form-label fw-semibold" for="srAgencyAccount">Agency account</label>
      <select id="srAgencyAccount" class="form-select" required><option value="">Loading accounts…</option></select>
      <label class="form-label fw-semibold mt-3" for="srSourceAccount">School source account</label>
      <select id="srSourceAccount" class="form-select" required><option value="">Loading authorized statutory accounts…</option></select>
      <div id="srPaymentHint" class="form-text mt-2"></div>
      <input type="hidden" id="srPaymentId">
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" id="srSubmitPaymentBtn">Submit payment</button></div>
  </div></div>
</div>

<div class="modal fade" id="srCertificateModal" tabindex="-1" aria-labelledby="srCertificateTitle">
  <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header bg-secondary text-white"><h5 class="modal-title" id="srCertificateTitle">Record Certificate of Service</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Staff member</label><select id="srCertificateStaff" class="form-select" required><option value="">Loading staff…</option></select></div>
        <div class="col-md-3"><label class="form-label">Employment start</label><input type="date" id="srCertificateStart" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Employment end</label><input type="date" id="srCertificateEnd" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Designation</label><input type="text" id="srCertificateDesignation" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Department</label><input type="text" id="srCertificateDepartment" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Reason for leaving</label><input type="text" id="srCertificateReason" class="form-control"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="srSaveCertificateBtn">Save Certificate</button></div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/statutory_remittances.js'); ?>
