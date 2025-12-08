<!DOCTYPE html>
<html>
<head>
    <title>Payroll Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<div class="p-4">

<h2>Payroll Management</h2>

<h4>Add Employee</h4>
<form id="employeeForm" class="mb-4">
    <input type="text" name="first_name" placeholder="First Name" required>
    <input type="text" name="last_name" placeholder="Last Name" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="number" name="salary" placeholder="Salary" required>
    <button class="btn btn-success">Add</button>
</form>

<h4>Process Payroll</h4>
<form id="payrollForm" class="mb-4">
    <select name="employee_id" id="employeeSelect" required></select>
    <input type="number" name="basic_salary" placeholder="Basic Salary" required>
    <input type="number" name="allowances" placeholder="Allowances">
    <input type="number" name="deductions" placeholder="Deductions">
    <input type="date" name="pay_period_start" required>
    <input type="date" name="pay_period_end" required>
    <button class="btn btn-primary">Process</button>
</form>

<h4>Payroll Report</h4>
<table class="table table-bordered" id="payrollTable">
    <thead>
        <tr>
            <th>Employee</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Net</th><th>Date</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

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
