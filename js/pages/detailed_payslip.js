document.addEventListener('DOMContentLoaded', async () => { if (window.StaffAccess) await StaffAccess.require('staff.payslip.self'); });
/**
 * Detailed Payslip Page Controller
 * Shows comprehensive payslip with all deductions including staff children fees
 * Uses api.js for all API calls
 */

const DetailedPayslipController = {
  data: {
    staffList: [],
    history: [],
    currentPayslip: null,
    selectedStaffId: null,
    staffId: null,
  },

  /**
   * Initialize the controller
   */
  init: async function () {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    const user = window.AuthContext.getUser?.() || {};
    this.data.staffId = user.staff_id || user.staff?.id || null;
    this.data.selectedStaffId = this.data.staffId;

    // Set current period and load the employee's own payroll records.
    const currentMonth = new Date().getMonth() + 1;
    const monthSelect = document.getElementById("payrollMonth");
    if (monthSelect) {
      monthSelect.value = currentMonth;
    }

    this.populateYearSelect();
    document.getElementById("payrollYear")?.addEventListener("change", () => this.loadHistory());
    await this.loadHistory();
  },

  populateYearSelect: function () {
    const select = document.getElementById("payrollYear");
    if (!select) return;
    const currentYear = new Date().getFullYear();
    select.innerHTML = "";
    for (let year = currentYear; year >= currentYear - 4; year -= 1) {
      select.add(new Option(year, year, year === currentYear, year === currentYear));
    }
  },

  unwrap: function (response) {
    return response?.data ?? response ?? {};
  },

  money: function (value) {
    return "KES " + this.formatNumber(Number(value || 0));
  },

  escape: function (value) {
    return String(value ?? "").replace(/[&<>'"]/g, char => ({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#039;",'"':"&quot;"}[char]));
  },

  loadHistory: async function () {
    const body = document.getElementById("payrollHistoryBody");
    if (!this.data.staffId) {
      if (body) body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Your account is not linked to a staff profile. Please contact the school administrator.</td></tr>';
      return;
    }
    const year = document.getElementById("payrollYear")?.value || new Date().getFullYear();
    if (body) body.innerHTML = '<tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm text-success me-2"></span>Loading your payslips…</td></tr>';
    try {
      const response = await window.API.staff.getPayrollHistory(this.data.staffId, { year });
      const payload = this.unwrap(response);
      this.data.history = Array.isArray(payload) ? payload : (payload.payroll_history || payload.rows || []);
      this.renderHistory();
      this.renderSummary();
      this.renderChart();
      this.renderAnnualBreakdown();
      const latest = this.data.history[0];
      if (latest) {
        document.getElementById("payrollMonth").value = Number(latest.payroll_month || latest.month);
        document.getElementById("payrollYear").value = Number(latest.payroll_year || latest.year || year);
        this.loadPayslip(Number(latest.payroll_month), Number(latest.payroll_year || year));
      }
    } catch (error) {
      console.error("Unable to load payroll history", error);
      if (body) body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">We could not load your payslip history. Please try again.</td></tr>';
    }
  },

  statusClass: function (status) {
    const value = String(status || "pending").toLowerCase();
    return value.includes("paid") || value.includes("disburs") || value.includes("settled") || value.includes("completed") ? "status-paid" : (value.includes("pending") || value.includes("process") ? "status-pending" : (value.includes("fail") || value.includes("cancel") ? "status-failed" : "status-draft"));
  },

  renderHistory: function () {
    const body = document.getElementById("payrollHistoryBody");
    if (!body) return;
    if (!this.data.history.length) { body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-calendar2-x fs-2 d-block mb-2"></i>No payslips have been published for this year.</td></tr>'; return; }
    body.innerHTML = this.data.history.map(row => {
      const month = Number(row.payroll_month || row.month || 0), year = Number(row.payroll_year || row.year || 0);
      const status = row.payment_status || row.payslip_status || row.status || "pending";
      return `<tr><td><strong>${this.escape(this.getMonthName(month))}</strong><div class="small text-muted">${year}</div></td><td>${this.money(row.gross_salary || row.gross_pay)}</td><td>${this.money(row.total_deductions || row.deductions_total)}</td><td class="fw-semibold">${this.money(row.net_salary || row.net_pay)}</td><td><span class="status-badge ${this.statusClass(status)}">${this.escape(status)}</span></td><td class="text-end"><button class="btn btn-sm btn-outline-success" data-kw-onclick="detailedPayslipController.loadPayslip(${month},${year})">View</button></td></tr>`;
    }).join("");
  },

  renderSummary: function () {
    const rows = this.data.history, paid = rows.filter(row => this.statusClass(row.payment_status || row.payslip_status || row.status) === "status-paid");
    const sum = (key, alt) => rows.reduce((total, row) => total + Number(row[key] || row[alt] || 0), 0);
    const net = sum("net_salary", "net_pay"), gross = sum("gross_salary", "gross_pay");
    const latest = rows[0];
    document.getElementById("latestNetPay").textContent = this.money(latest?.net_salary || latest?.net_pay);
    document.getElementById("latestPayPeriod").textContent = latest ? `${this.getMonthName(latest.payroll_month)} ${latest.payroll_year}` : "No payslip yet";
    document.getElementById("paidTotal").textContent = this.money(paid.reduce((total, row) => total + Number(row.net_salary || row.net_pay || 0), 0));
    document.getElementById("paidCount").textContent = `${paid.length} paid payslip${paid.length === 1 ? "" : "s"}`;
    document.getElementById("pendingCount").textContent = rows.length - paid.length;
    document.getElementById("grossTotal").textContent = this.money(gross);
    document.getElementById("averageNet").textContent = `Average net: ${this.money(rows.length ? net / rows.length : 0)}`;
  },

  renderChart: function () {
    const chart = document.getElementById("earningsChart"); if (!chart) return;
    const rows = [...this.data.history].reverse(), max = Math.max(1, ...rows.map(row => Number(row.gross_salary || row.gross_pay || 0)));
    chart.innerHTML = rows.slice(-12).map(row => { const gross = Number(row.gross_salary || row.gross_pay || 0), net = Number(row.net_salary || row.net_pay || 0); return `<div class="chart-column" title="${this.escape(this.getMonthName(row.payroll_month))}: ${this.money(net)} net"><div class="chart-bar" style="height:${Math.max(3, gross / max * 100)}%"></div><div class="chart-bar net" style="height:${Math.max(3, net / max * 100)}%"></div><span class="chart-label">${this.escape(this.getMonthName(row.payroll_month).slice(0,3))}</span></div>`; }).join("") || '<div class="text-muted m-auto">No earnings data to chart.</div>';
  },

  renderAnnualBreakdown: function () {
    const rows = this.data.history, total = key => rows.reduce((sum, row) => sum + Number(row[key] || 0), 0);
    const data = [["Gross earnings", total("gross_salary") || total("gross_pay")], ["Total deductions", total("total_deductions")], ["Net earnings", total("net_salary") || total("net_pay")]];
    document.getElementById("annualBreakdown").innerHTML = data.map(([label, value]) => `<div class="breakdown-row"><span>${label}</span><strong>${this.money(value)}</strong></div>`).join("");
  },

  loadPayslip: async function (month, year) {
    document.getElementById("payrollMonth").value = month;
    document.getElementById("payrollYear").value = year;
    await this.generatePayslip();
    document.getElementById("payslipViewerPanel")?.scrollIntoView({ behavior: "smooth", block: "start" });
  },

  /**
   * Generate payslip
   */
  generatePayslip: async function () {
    const staffId = this.data.staffId;
    const month = document.getElementById("payrollMonth").value;
    const year = document.getElementById("payrollYear").value;

    if (!staffId) {
      this.showError("Your account is not linked to a staff profile.");
      return;
    }

    try {
      document.getElementById("payslipContainer").innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-3">Generating payslip...</p>
                </div>
            `;

      const response = await window.API.staff.generateDetailedPayslip(staffId, month, year);

      if (response) {
        const payload = this.unwrap(response);
        this.data.currentPayslip = payload.payslip || payload;
        this.renderPayslip(payload);
        document.getElementById("downloadPayslipButton")?.removeAttribute("disabled");
        document.getElementById("printPayslipButton")?.removeAttribute("disabled");
        const status = this.data.currentPayslip.payment_status || this.data.currentPayslip.payslip_status || this.data.currentPayslip.status || "pending";
        const statusEl = document.getElementById("selectedPayslipStatus");
        if (statusEl) { statusEl.textContent = status; statusEl.className = `status-badge ${this.statusClass(status)}`; }
      } else {
        this.showError("Failed to generate payslip");
        this.showEmptyState();
      }
    } catch (error) {
      console.error("Error generating payslip:", error);
      this.showError("An error occurred while generating payslip");
      this.showEmptyState();
    }
  },

  /**
   * Render payslip
   */
  renderPayslip: function (payslip) {
    const payload = this.unwrap(payslip);
    payslip = payload.payslip || payload;
    payslip.earnings = payslip.earnings || payload.allowances_breakdown || [];
    payslip.deductions = payslip.deductions || payload.deductions_breakdown || [];
    const container = document.getElementById("payslipContainer");
    const template = document.getElementById("payslipTemplate");

    if (!template) {
      console.error("Payslip template not found");
      return;
    }

    // Clone template content
    const clone = template.content.cloneNode(true);
    container.innerHTML = "";
    container.appendChild(clone);

    // Populate employee details
    const payslipMonth = payslip.month || payslip.payroll_month;
    const payslipYear = payslip.year || payslip.payroll_year;
    document.getElementById("payslipPeriod").textContent =
      this.getMonthName(payslipMonth) + " " + payslipYear;
    document.getElementById("employeeName").textContent =
      payslip.employee_name || payslip.staff_name || "-";
    document.getElementById("employeeId").textContent =
      payslip.employee_number || payslip.staff_no || payslip.staff_id || "-";
    document.getElementById("employeeDepartment").textContent =
      payslip.department || payslip.department_name || "-";
    document.getElementById("employeeDesignation").textContent =
      payslip.designation || payslip.position || "-";
    document.getElementById("employeeKraPin").textContent =
      payslip.kra_pin || "-";
    document.getElementById("employeeNssf").textContent =
      payslip.nssf_number || payslip.nssf_no || "-";
    document.getElementById("employeeShif").textContent =
      payslip.shif_number || payslip.shif_no || payslip.nhif_number || payslip.nhif_no || "-";
    document.getElementById("employeeBankAccount").textContent =
      payslip.bank_name
        ? `${payslip.bank_name} - ${payslip.account_number || payslip.bank_account || "***"}`
        : (payslip.bank_account || "-");

    // Populate earnings; the basic salary is stored as a column while other
    // earnings are returned as line items.
    const earnings = [...(payslip.earnings || payslip.allowances || [])];
    if (Number(payslip.basic_salary || payslip.basic_pay || 0) > 0 && !earnings.some(item => String(item.type || item.name || '').toLowerCase().includes('basic'))) {
      earnings.unshift({ type: "basic", name: "Basic Salary", amount: payslip.basic_salary || payslip.basic_pay });
    }
    this.populateEarnings(earnings);

    // Populate deductions
    this.populateDeductions(payslip.deductions || []);

    // Populate statutory breakdown
    const statutory = payslip.statutory || {};
    document.getElementById("payeAmount").textContent =
      "KES " + this.formatNumber(statutory.paye ?? payslip.paye_tax ?? 0);
    document.getElementById("nssfAmount").textContent =
      "KES " + this.formatNumber(statutory.nssf ?? payslip.nssf_contribution ?? 0);
    document.getElementById("shifAmount").textContent =
      "KES " + this.formatNumber(statutory.shif ?? statutory.nhif ?? payslip.shif_deduction ?? payslip.nhif_deduction ?? payslip.nhif_contribution ?? 0);
    document.getElementById("housingLevyAmount").textContent =
      "KES " + this.formatNumber(statutory.housing_levy ?? payslip.housing_levy ?? 0);
    document.getElementById("employerNssfAmount").textContent =
      "KES " + this.formatNumber(payslip.employer_nssf_contribution || 0);
    document.getElementById("employerHousingLevyAmount").textContent =
      "KES " + this.formatNumber(payslip.employer_housing_levy || 0);

    // Populate children fee deductions
    this.populateChildrenFees(payslip.children_fee_deductions || []);

    // Populate other deductions
    this.populateOtherDeductions(payslip.other_deductions || []);

    // Calculate and populate summary
    const grossEarnings = parseFloat(
      payslip.gross_salary || payslip.gross_earnings || 0
    );
    const totalDeductions = parseFloat(payslip.total_deductions ?? payslip.total_deductions_amount ?? 0);
    const netPay = parseFloat(payslip.net_pay || payslip.net_salary || 0);

    document.getElementById("grossEarnings").textContent =
      "KES " + this.formatNumber(grossEarnings);
    document.getElementById("totalDeductions").textContent =
      "KES " + this.formatNumber(totalDeductions);
    document.getElementById("summaryGross").textContent =
      "KES " + this.formatNumber(grossEarnings);
    document.getElementById("summaryDeductions").textContent =
      "KES " + this.formatNumber(totalDeductions);
    document.getElementById("netPay").textContent =
      "KES " + this.formatNumber(netPay);

    // Footer details
    document.getElementById("generatedDate").textContent =
      new Date().toLocaleDateString("en-KE", {
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    document.getElementById("payslipReference").textContent = `PAY-${
      payslipYear
    }${String(payslipMonth).padStart(2, "0")}-${payslip.staff_id}`;
  },

  /**
   * Populate earnings table
   */
  populateEarnings: function (earnings) {
    const tbody = document.getElementById("earningsTable");
    if (!tbody) return;

    tbody.innerHTML = "";

    // Add basic salary first
    const basicSalary = earnings.find(
      (e) => e.type === "basic" || e.name?.toLowerCase().includes("basic")
    );
    if (basicSalary) {
      tbody.innerHTML += `
                <tr>
                    <td>Basic Salary</td>
                    <td class="text-end">KES ${this.formatNumber(
                      basicSalary.amount
                    )}</td>
                </tr>
            `;
    }

    // Add other earnings
    earnings.forEach((earning) => {
      if (
        earning.type !== "basic" &&
        !earning.name?.toLowerCase().includes("basic")
      ) {
        tbody.innerHTML += `
                    <tr>
                        <td>${earning.name || earning.type || "Allowance"}</td>
                        <td class="text-end">KES ${this.formatNumber(
                          earning.amount
                        )}</td>
                    </tr>
                `;
      }
    });

    // If no earnings, show placeholder
    if (earnings.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="2" class="text-muted text-center">No earnings data</td></tr>';
    }
  },

  /**
   * Populate deductions table
   */
  populateDeductions: function (deductions) {
    const tbody = document.getElementById("deductionsTable");
    if (!tbody) return;

    tbody.innerHTML = "";

    deductions.forEach((deduction) => {
      tbody.innerHTML += `
                <tr>
                    <td>${deduction.name || deduction.type || "Deduction"}</td>
                    <td class="text-end">KES ${this.formatNumber(
                      deduction.amount
                    )}</td>
                </tr>
            `;
    });

    // If no deductions, show placeholder
    if (deductions.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="2" class="text-muted text-center">No deductions</td></tr>';
    }
  },

  /**
   * Populate children fee deductions
   */
  populateChildrenFees: function (childrenFees) {
    const section = document.getElementById("childrenFeeSection");
    const tbody = document.getElementById("childrenFeeTable");

    if (!section || !tbody) return;

    if (!childrenFees || childrenFees.length === 0) {
      section.style.display = "none";
      return;
    }

    section.style.display = "block";
    tbody.innerHTML = "";

    let totalChildFees = 0;

    childrenFees.forEach((child) => {
      const deduction = parseFloat(
        child.monthly_deduction || child.amount || 0
      );
      totalChildFees += deduction;

      tbody.innerHTML += `
                <tr>
                    <td>${
                      child.student_name || child.child_name || "Child"
                    }</td>
                    <td>${child.class_name || child.current_class || "-"}</td>
                    <td>KES ${this.formatNumber(child.term_fees || 0)}</td>
                    <td class="text-success">${
                      child.discount_percent || 0
                    }%</td>
                    <td class="text-end">KES ${this.formatNumber(
                      deduction
                    )}</td>
                </tr>
            `;
    });

    document.getElementById("totalChildrenFees").textContent =
      "KES " + this.formatNumber(totalChildFees);
  },

  /**
   * Populate other deductions
   */
  populateOtherDeductions: function (otherDeductions) {
    const section = document.getElementById("otherDeductionsSection");
    const tbody = document.getElementById("otherDeductionsTable");

    if (!section || !tbody) return;

    if (!otherDeductions || otherDeductions.length === 0) {
      section.style.display = "none";
      return;
    }

    section.style.display = "block";
    tbody.innerHTML = "";

    otherDeductions.forEach((deduction) => {
      tbody.innerHTML += `
                <tr>
                    <td>${
                      deduction.description || deduction.name || "Deduction"
                    }</td>
                    <td>${deduction.reference || "-"}</td>
                    <td class="text-end">KES ${this.formatNumber(
                      deduction.amount || 0
                    )}</td>
                </tr>
            `;
    });
  },

  /**
   * Download payslip as PDF
   */
  downloadPayslip: async function () {
    if (!this.data.currentPayslip) {
      this.showError("Please generate a payslip first");
      return;
    }

    const staffId = this.data.staffId;
    const month = document.getElementById("payrollMonth").value;
    const year = document.getElementById("payrollYear").value;

    try {
      // Use the download API
      await window.API.staff.downloadDetailedPayslip(staffId, month, year);
      this.showSuccess("Payslip downloaded");
    } catch (error) {
      console.error("Error downloading:", error);
      // Fallback to print
      this.printPayslip();
    }
  },

  /**
   * Print payslip
   */
  printPayslip: function () {
    if (!this.data.currentPayslip) {
      this.showError("Please generate a payslip first");
      return;
    }

    const payslip = this.data.currentPayslip;
    const staff = this.data.currentPayslip || {};

    if (window.PrintManager && window.PrintManager.printDedicatedPayslip) {
      window.PrintManager.printDedicatedPayslip({
        employeeName: `${staff.first_name || ''} ${staff.last_name || ''}`.trim(),
        staffNo: staff.staff_no || '',
        department: staff.department_name || staff.department || '',
        designation: staff.designation || staff.position || '',
        kraPin: staff.kra_pin || '',
        nssfNo: staff.nssf_no || '',
        nhifNo: staff.shif_no || staff.nhif_no || '',
        period: payslip.period || new Date().toISOString().slice(0, 7),
        basicSalary: payslip.basic_salary || payslip.basic_pay || 0,
        allowances: (payslip.earnings || []).map(e => ({ name: e.description || e.name || '', amount: e.amount || e.value || 0 })),
        deductions: (payslip.deductions || []).map(d => ({ name: d.description || d.name || '', amount: d.amount || d.value || 0 })),
        statutory: {
          paye: payslip.paye || payslip.tax || 0,
          nssf: payslip.nssf || 0,
          nhif_shif: payslip.shif || payslip.nhif || 0,
          housing_levy: payslip.housing_levy || 0,
        },
        grossPay: payslip.gross_pay || payslip.gross_salary || 0,
        totalDeductions: payslip.total_deductions || 0,
        netPay: payslip.net_pay || payslip.net_salary || 0,
        bankName: staff.bank_name || '',
        bankBranch: staff.bank_branch || '',
        bankAccount: staff.bank_account || '',
        filename: `payslip_${staff.staff_no || staff.id || 'staff'}_${payslip.period || new Date().toISOString().slice(0, 7)}`,
      });
    } else {
      this.showError("PrintManager not available");
    }
  },

  /**
   * Show empty state
   */
  showEmptyState: function () {
    document.getElementById("payslipContainer").innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-file-earmark-text" style="font-size: 4rem;"></i>
                <p class="mt-3">Choose a pay period above or select a payslip from your history.</p>
            </div>
        `;
  },

  downloadP9: async function () {
    const year = document.getElementById("payrollYear")?.value || new Date().getFullYear();
    if (!this.data.staffId) return this.showError("Your account is not linked to a staff profile.");
    try {
      await window.API.staff.downloadP9(this.data.staffId, year);
      this.showSuccess(`P9 form for ${year} downloaded`);
    } catch (error) {
      console.error("Error downloading P9", error);
      this.showError("P9 form is not available for this year yet.");
    }
  },

  /**
   * Get month name
   */
  getMonthName: function (month) {
    const months = [
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
    return months[parseInt(month) - 1] || "Unknown";
  },

  /**
   * Format number with commas
   */
  formatNumber: function (num) {
    if (!num && num !== 0) return "0.00";
    return parseFloat(num).toLocaleString("en-KE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  },

  /**
   * Show success message
   */
  showSuccess: function (message) {
    if (window.showToast) {
      window.showToast(message, "success");
    } else {
      this._showNotification(message, "success");
    }
  },

  /**
   * Show error message
   */
  showError: function (message) {
    if (window.showToast) {
      window.showToast(message, "error");
    } else {
      this._showNotification(message, "error");
    }
  },

  /**
   * Show confirmation dialog using Bootstrap modal
   */
  showConfirm: function (title, message, onConfirm, type) {
    var modal = document.getElementById("confirmModal");
    if (!modal) return;

    document.getElementById("confirmModalTitle").textContent = title;
    document.getElementById("confirmModalMessage").textContent = message;

    var headerEl = modal.querySelector(".modal-header");
    headerEl.style.background =
      type === "danger"
        ? "linear-gradient(135deg, #8B0000, #dc3545)"
        : "linear-gradient(135deg, #0d4f2a, #198754)";

    var okBtn = document.getElementById("confirmModalOk");
    okBtn.style.background = type === "danger" ? "#8B0000" : "#0d4f2a";

    var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
    bsModal.show();

    // Remove old listeners by cloning
    var newOk = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(newOk, okBtn);
    newOk.id = "confirmModalOk";

    newOk.addEventListener("click", function () {
      bsModal.hide();
      if (typeof onConfirm === "function") onConfirm();
    });
  },

  /**
   * Show notification toast (safe DOM methods, no innerHTML)
   */
  _showNotification: function (message, type) {
    var container = document.getElementById("toastContainer");
    if (!container) {
      container = document.createElement("div");
      container.id = "toastContainer";
      container.style.cssText =
        "position:fixed;top:20px;right:20px;z-index:9999;";
      document.body.appendChild(container);
    }

    var bgClass = type === "success" ? "bg-success" : "bg-danger";

    var toast = document.createElement("div");
    toast.className =
      "toast show align-items-center text-white " +
      bgClass +
      " border-0 shadow-lg";
    toast.style.cssText = "border-radius:8px;min-width:300px;margin-bottom:8px;";
    toast.setAttribute("role", "alert");

    var flex = document.createElement("div");
    flex.className = "d-flex";

    var body = document.createElement("div");
    body.className = "toast-body";
    body.textContent = message;

    var closeBtn = document.createElement("button");
    closeBtn.type = "button";
    closeBtn.className = "btn-close btn-close-white me-2 m-auto";
    closeBtn.setAttribute("data-bs-dismiss", "toast");
    closeBtn.addEventListener("click", function () {
      toast.classList.remove("show");
      setTimeout(function () { toast.remove(); }, 300);
    });

    flex.appendChild(body);
    flex.appendChild(closeBtn);
    toast.appendChild(flex);
    container.appendChild(toast);

    setTimeout(function () {
      toast.classList.remove("show");
      setTimeout(function () { toast.remove(); }, 300);
    }, 4000);
  },
};

// Export for global access
window.detailedPayslipController = DetailedPayslipController;

// Initialize on DOM ready
document.addEventListener("DOMContentLoaded", () =>
  DetailedPayslipController.init()
);

window.DetailedPayslipController = DetailedPayslipController;
