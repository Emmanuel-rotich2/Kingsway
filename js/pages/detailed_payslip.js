/**
 * Detailed Payslip Page Controller
 * Shows comprehensive payslip with all deductions including staff children fees
 * Uses api.js for all API calls
 */

const DetailedPayslipController = {
  data: {
    staffList: [],
    currentPayslip: null,
    selectedStaffId: null,
  },

  /**
   * Initialize the controller
   */
  init: async function () {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    // Set current month
    const currentMonth = new Date().getMonth() + 1;
    const monthSelect = document.getElementById("payrollMonth");
    if (monthSelect) {
      monthSelect.value = currentMonth;
    }

    await this.loadStaffList();
  },

  /**
   * Load staff list
   */
  loadStaffList: async function () {
    try {
      const response = await window.API.staff.list();
      if (response?.success) {
        this.data.staffList = response.data || [];
        this.populateStaffSelect();
      }
    } catch (error) {
      console.error("Error loading staff list:", error);
    }
  },

  /**
   * Populate staff dropdown
   */
  populateStaffSelect: function () {
    const select = document.getElementById("staffSelect");
    if (!select) return;

    select.innerHTML = '<option value="">-- Select Staff --</option>';
    this.data.staffList.forEach((staff) => {
      const option = document.createElement("option");
      option.value = staff.id;
      option.textContent = `${staff.first_name} ${staff.last_name} - ${
        staff.department || "General"
      }`;
      select.appendChild(option);
    });
  },

  /**
   * Handle staff selection change
   */
  onStaffChange: function () {
    this.data.selectedStaffId = document.getElementById("staffSelect").value;
  },

  /**
   * Generate payslip
   */
  generatePayslip: async function () {
    const staffId = document.getElementById("staffSelect").value;
    const month = document.getElementById("payrollMonth").value;
    const year = document.getElementById("payrollYear").value;

    if (!staffId) {
      this.showError("Please select a staff member");
      return;
    }

    try {
      document.getElementById("payslipContainer").innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-3">Generating payslip...</p>
                </div>
            `;

      const response = await window.API.staff.generateDetailedPayslip(
        staffId,
        month,
        year
      );

      if (response?.success) {
        this.data.currentPayslip = response.data;
        this.renderPayslip(response.data);
      } else {
        this.showError(response?.message || "Failed to generate payslip");
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
    document.getElementById("payslipPeriod").textContent =
      this.getMonthName(payslip.month) + " " + payslip.year;
    document.getElementById("employeeName").textContent =
      payslip.employee_name || "-";
    document.getElementById("employeeId").textContent =
      payslip.employee_number || payslip.staff_id || "-";
    document.getElementById("employeeDepartment").textContent =
      payslip.department || "-";
    document.getElementById("employeeDesignation").textContent =
      payslip.designation || payslip.position || "-";
    document.getElementById("employeeKraPin").textContent =
      payslip.kra_pin || "-";
    document.getElementById("employeeNssf").textContent =
      payslip.nssf_number || "-";
    document.getElementById("employeeNhif").textContent =
      payslip.nhif_number || "-";
    document.getElementById("employeeBankAccount").textContent =
      payslip.bank_name
        ? `${payslip.bank_name} - ${payslip.account_number || "***"}`
        : "-";

    // Populate earnings
    this.populateEarnings(payslip.earnings || payslip.allowances || []);

    // Populate deductions
    this.populateDeductions(payslip.deductions || []);

    // Populate statutory breakdown
    const statutory = payslip.statutory || {};
    document.getElementById("payeAmount").textContent =
      "KES " + this.formatNumber(statutory.paye || 0);
    document.getElementById("nssfAmount").textContent =
      "KES " + this.formatNumber(statutory.nssf || 0);
    document.getElementById("nhifAmount").textContent =
      "KES " + this.formatNumber(statutory.nhif || 0);
    document.getElementById("housingLevyAmount").textContent =
      "KES " + this.formatNumber(statutory.housing_levy || 0);

    // Populate children fee deductions
    this.populateChildrenFees(payslip.children_fee_deductions || []);

    // Populate other deductions
    this.populateOtherDeductions(payslip.other_deductions || []);

    // Calculate and populate summary
    const grossEarnings = parseFloat(
      payslip.gross_salary || payslip.gross_earnings || 0
    );
    const totalDeductions = parseFloat(payslip.total_deductions || 0);
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
      payslip.year
    }${String(payslip.month).padStart(2, "0")}-${payslip.staff_id}`;
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

    const staffId = document.getElementById("staffSelect").value;
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

    window.print();
  },

  /**
   * Show empty state
   */
  showEmptyState: function () {
    document.getElementById("payslipContainer").innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-file-earmark-text" style="font-size: 4rem;"></i>
                <p class="mt-3">Select a staff member and click "Generate" to view payslip</p>
            </div>
        `;
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
      alert(message);
    }
  },

  /**
   * Show error message
   */
  showError: function (message) {
    if (window.showToast) {
      window.showToast(message, "error");
    } else {
      alert("Error: " + message);
    }
  },
};

// Export for global access
window.detailedPayslipController = DetailedPayslipController;

// Initialize on DOM ready
document.addEventListener("DOMContentLoaded", () =>
  DetailedPayslipController.init()
);
