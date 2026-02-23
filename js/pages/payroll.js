/**
 * Payroll Page Controller
 * Staff self-service payroll view: process payroll, view payslips, reports
 */

const payrollController = {
  staff: [],
  payPeriods: [],
  selectedStaffId: null,
  currentPayslip: null,

  notify: function (message, type) {
    if (typeof showNotification === "function") {
      showNotification(message, type || "info");
    } else if (
      window.API &&
      typeof window.API.showNotification === "function"
    ) {
      window.API.showNotification(message, type || "info");
    } else {
      alert(message);
    }
  },

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    await Promise.all([this.loadStaffList(), this.populatePayPeriods()]);
    this.bindEvents();
  },

  bindEvents: function () {
    const form = document.getElementById("payrollForm");
    if (form) form.addEventListener("submit", (e) => this.processPayroll(e));

    const staffSelect = document.getElementById("staffSelect");
    if (staffSelect)
      staffSelect.addEventListener("change", () => this.onStaffChange());
  },

  loadStaffList: async function () {
    try {
      const response = await window.API.staff.index();
      const list =
        response?.data?.staff ||
        response?.data ||
        response?.staff ||
        response ||
        [];
      this.staff = Array.isArray(list) ? list : [];

      const select = document.getElementById("staffSelect");
      if (select) {
        select.innerHTML = '<option value="">-- Select Staff --</option>';
        this.staff.forEach((s) => {
          select.innerHTML += `<option value="${s.id}" data-salary="${s.salary || 0}">${s.first_name || ""} ${s.last_name || ""} (${s.staff_no || "-"})</option>`;
        });
      }
    } catch (error) {
      console.error("Error loading staff:", error);
    }
  },

  populatePayPeriods: function () {
    const select = document.getElementById("payPeriodSelect");
    if (!select) return;
    select.innerHTML = '<option value="">-- Select Period --</option>';

    const now = new Date();
    for (let i = 0; i < 12; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      const val = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
      const label = d.toLocaleString("default", {
        month: "long",
        year: "numeric",
      });
      select.innerHTML += `<option value="${val}">${label}</option>`;
    }
  },

  onStaffChange: function () {
    const select = document.getElementById("staffSelect");
    const salaryInput = document.getElementById("basicSalary");
    if (!select || !salaryInput) return;

    const opt = select.selectedOptions[0];
    this.selectedStaffId = select.value;
    salaryInput.value = opt?.dataset?.salary || 0;
    this.calculateNetSalary();
  },

  calculateNetSalary: function () {
    const basic = parseFloat(
      document.getElementById("basicSalary")?.value || 0,
    );
    const allowances = parseFloat(
      document.getElementById("allowances")?.value || 0,
    );
    const deductions = parseFloat(
      document.getElementById("deductions")?.value || 0,
    );
    const net = basic + allowances - deductions;
    const netInput = document.getElementById("netSalary");
    if (netInput) netInput.value = net.toFixed(2);
  },

  processPayroll: async function (event) {
    event.preventDefault();
    const staffId = document.getElementById("staffSelect")?.value;
    const period = document.getElementById("payPeriodSelect")?.value;

    if (!staffId || !period) {
      this.notify("Please select staff and pay period", "warning");
      return;
    }

    const [year, month] = period.split("-");
    try {
      const response = await window.API.staff.generateDetailedPayslip(
        staffId,
        parseInt(month),
        parseInt(year),
      );
      if (response?.success || response?.data) {
        this.notify("Payroll processed successfully!", "success");
        this.loadReport();
      } else {
        this.notify(response?.message || "Failed to process payroll", "error");
      }
    } catch (error) {
      console.error("Error processing payroll:", error);
      this.notify(
        "Error processing payroll: " + (error.message || "Unknown error"),
        "error",
      );
    }
  },

  loadReport: async function () {
    const container = document.getElementById("reportContainer");
    if (!container) return;
    container.innerHTML =
      '<div class="text-center py-3"><div class="spinner-border"></div> Loading report...</div>';

    try {
      const response = await window.API.staff.listPayroll();
      const payroll =
        response?.data?.payroll || response?.payroll || response?.data || [];
      const list = Array.isArray(payroll) ? payroll : [];

      if (!list.length) {
        container.innerHTML =
          '<p class="text-muted">No payroll records found.</p>';
        return;
      }

      const fmt = (v) =>
        "KES " +
        Number(v || 0).toLocaleString("en-KE", { minimumFractionDigits: 2 });

      container.innerHTML = `
        <div class="table-responsive">
          <table class="table table-hover table-striped">
            <thead class="table-light">
              <tr>
                <th>Staff No.</th><th>Name</th><th>Basic</th><th>Allowances</th>
                <th>Deductions</th><th>Net Pay</th><th>Period</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              ${list
                .map(
                  (r) => `
                <tr>
                  <td>${r.staff_no || "-"}</td>
                  <td>${r.first_name || ""} ${r.last_name || ""}</td>
                  <td>${fmt(r.basic_salary)}</td>
                  <td>${fmt(r.allowances)}</td>
                  <td>${fmt(r.total_deductions || r.deductions)}</td>
                  <td><strong>${fmt(r.net_salary || r.net_pay)}</strong></td>
                  <td>${r.month || "-"}/${r.year || "-"}</td>
                  <td>
                    <button class="btn btn-sm btn-outline-info" onclick="payrollController.viewPayslip(${r.staff_id || r.id}, ${r.month || 0}, ${r.year || 0})">
                      <i class="bi bi-receipt"></i> Payslip
                    </button>
                  </td>
                </tr>
              `,
                )
                .join("")}
            </tbody>
          </table>
        </div>
      `;
    } catch (error) {
      console.error("Error loading report:", error);
      container.innerHTML =
        '<p class="text-danger">Failed to load payroll report.</p>';
    }
  },

  viewPayslip: async function (staffId, month, year) {
    try {
      const response = await window.API.staff.getPayslip(staffId, {
        month,
        year,
      });
      const payslip = response?.data || response || {};
      const months = [
        "",
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
      ];
      const fmt = (v) =>
        "KES " +
        Number(v || 0).toLocaleString("en-KE", { minimumFractionDigits: 2 });

      const win = window.open("", "_blank");
      win.document.write(`<html><head><title>Payslip</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head><body class="p-4">
        <div class="container">
          <div class="text-center mb-3"><h3>Kingsway Academy</h3><h5 class="text-muted">Payslip - ${months[parseInt(month)] || month} ${year}</h5></div>
          <hr>
          <p><strong>Name:</strong> ${payslip.first_name || payslip.staff_name || ""} ${payslip.last_name || ""} | <strong>Staff No:</strong> ${payslip.staff_no || "-"}</p>
          <table class="table table-bordered">
            <tr><td>Basic Salary</td><td class="text-end">${fmt(payslip.basic_salary || payslip.salary)}</td></tr>
            <tr><td>Allowances</td><td class="text-end">${fmt(payslip.allowances || payslip.total_allowances)}</td></tr>
            <tr class="table-success"><td><strong>Gross Pay</strong></td><td class="text-end"><strong>${fmt(payslip.gross_pay || payslip.gross_salary)}</strong></td></tr>
            <tr><td>PAYE</td><td class="text-end text-danger">-${fmt(payslip.paye || payslip.tax)}</td></tr>
            <tr><td>NHIF</td><td class="text-end text-danger">-${fmt(payslip.nhif_deduction)}</td></tr>
            <tr><td>NSSF</td><td class="text-end text-danger">-${fmt(payslip.nssf_deduction)}</td></tr>
            <tr><td>Other Deductions</td><td class="text-end text-danger">-${fmt(payslip.other_deductions)}</td></tr>
            <tr class="table-primary"><td><strong>Net Pay</strong></td><td class="text-end"><strong>${fmt(payslip.net_pay || payslip.net_salary)}</strong></td></tr>
          </table>
        </div></body></html>`);
      win.document.close();
    } catch (error) {
      console.error("Error:", error);
      this.notify("Failed to load payslip", "error");
    }
  },
};

document.addEventListener("DOMContentLoaded", () => payrollController.init());
