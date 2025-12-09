<?php
/**
 * Manage Payrolls Page
 * HTML structure only - all logic in js/pages/finance.js (managePayrollsController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
  <div class="card-header bg-success text-white">
    <h2 class="mb-0">💼 Manage Payrolls</h2>
  </div>
  <div class="card-body">
    <!-- Search and Filter -->
    <div class="row mb-3">
      <div class="col-md-6">
        <input type="text" id="searchPayrolls" class="form-control" placeholder="Search staff/payroll..." 
               onkeyup="managePayrollsController.search(this.value)">
      </div>
      <div class="col-md-3">
        <select id="payPeriodFilter" class="form-select" onchange="managePayrollsController.filterByPeriod(this.value)">
          <option value="">-- All Periods --</option>
        </select>
      </div>
      <div class="col-md-3">
        <select id="statusFilter" class="form-select" onchange="managePayrollsController.filterByStatus(this.value)">
          <option value="">-- All Status --</option>
          <option value="pending">Pending</option>
          <option value="paid">Paid</option>
        </select>
      </div>
    </div>

    <!-- Payrolls Table -->
    <div id="payrollsTableContainer">
      <p class="text-muted">Loading payroll records...</p>
    </div>
  </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="payrollModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payroll Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="payrollForm">
        <div class="modal-body">
          <input type="hidden" id="payrollId">
          <div class="mb-3">
            <label class="form-label">Staff Member</label>
            <select id="staffSelect" class="form-select" required>
              <option value="">-- Select Staff --</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Basic Salary</label>
            <input type="number" id="basicSalary" class="form-control" step="0.01" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Allowances</label>
            <input type="number" id="allowances" class="form-control" step="0.01" value="0">
          </div>
          <div class="mb-3">
            <label class="form-label">Deductions</label>
            <input type="number" id="deductions" class="form-control" step="0.01" value="0">
          </div>
          <div class="mb-3">
            <label class="form-label">Pay Period</label>
            <input type="month" id="payPeriod" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select id="statusSelect" class="form-select" required>
              <option value="pending">Pending</option>
              <option value="paid">Paid</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
async function loadEmployees() {
    const res = await fetch('payroll.php?action=list-employees');
    const employees = await res.json();
    const select = document.getElementById('employeeSelect');
    select.innerHTML = '';
    employees.forEach(e => {
        const option = document.createElement('option');
        option.value = e.id;
        option.text = e.first_name + ' ' + e.last_name;
        select.add(option);
    });
}

document.getElementById('employeeForm').addEventListener('submit', async e => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const res = await fetch('payroll.php?action=add-employee', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)});
    const result = await res.json();
    alert(result.message);
    e.target.reset();
    loadEmployees();
});

document.getElementById('payrollForm').addEventListener('submit', async e => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const res = await fetch('payroll.php?action=process-payroll', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)});
    const result = await res.json();
    alert(result.message);
    e.target.reset();
    loadPayrollReport();
});

async function loadPayrollReport() {
    const res = await fetch('payroll.php?action=payroll-report');
    const payrolls = await res.json();
    const tbody = document.querySelector('#payrollTable tbody');
    tbody.innerHTML = '';
    payrolls.forEach(p => {
        tbody.innerHTML += `<tr>
            <td>${p.first_name} ${p.last_name}</td>
            <td>${p.basic_salary}</td>
            <td>${p.allowances}</td>
            <td>${p.deductions}</td>
            <td>${p.net_salary}</td>
            <td>${p.payment_date}</td>
        </tr>`;
    });
}

loadEmployees();
loadPayrollReport();
</script>
</div>
