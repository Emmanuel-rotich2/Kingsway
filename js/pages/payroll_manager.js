/**
 * Payroll Manager Controller
 * Handles payroll processing with staff children fee deductions
 *
 * @package App\JS\Pages
 * @since 2025-01-05
 */

const PayrollManagerController = {
  // State
  payrolls: [],
  filteredPayrolls: [],
  staff: [],
  selectedStaff: null,
  childrenDeductions: [],
  currentPage: 1,
  perPage: 15,
  currentPayslipId: null,

  /**
   * Initialize controller
   */
  init: async function () {
    try {
      console.log("🚀 Initializing Payroll Manager...");

      // Set current month in filters
      const now = new Date();
      document.getElementById("filterMonth").value = now.getMonth() + 1;
      document.getElementById("payrollMonth").value = now.getMonth() + 1;

      // Populate year filters
      this.populateYearFilters();

      // Load initial data
      await Promise.all([
        this.loadPayrolls(),
        this.loadStats(),
        this.loadStaffList(),
      ]);

      console.log("✅ Payroll Manager initialized");
    } catch (error) {
      console.error("❌ Error initializing Payroll Manager:", error);
      this.showError("Failed to initialize payroll manager");
    }
  },

  /**
   * Populate year filter dropdowns
   */
  populateYearFilters: function () {
    const currentYear = new Date().getFullYear();
    const yearSelect = document.getElementById("filterYear");

    for (let y = currentYear; y >= currentYear - 5; y--) {
      const option = document.createElement("option");
      option.value = y;
      option.textContent = y;
      if (y === currentYear) option.selected = true;
      yearSelect.appendChild(option);
    }
  },

  /**
   * Load payroll records
   */
  loadPayrolls: async function () {
    try {
      const filters = {
        month: document.getElementById("filterMonth").value,
        year: document.getElementById("filterYear").value,
        status: document.getElementById("filterStatus").value,
        search: document.getElementById("searchStaff").value,
      };

      const response = await API.finance.getPayrollList(filters);

      if (response.success) {
        this.payrolls = response.data || [];
      } else {
        this.payrolls = [];
      }

      this.filteredPayrolls = [...this.payrolls];
      this.renderTable();
      this.updatePayrollCount();
    } catch (error) {
      console.error("Error loading payrolls:", error);
      this.payrolls = [];
      this.renderTable();
    }
  },

  /**
   * Load payroll statistics
   */
  loadStats: async function () {
    try {
      const month =
        document.getElementById("filterMonth").value ||
        new Date().getMonth() + 1;
      const year =
        document.getElementById("filterYear").value || new Date().getFullYear();

      const response = await API.finance.getPayrollStats(month, year);

      if (response.success) {
        const stats = response.data;
        document.getElementById("statTotalStaff").textContent =
          stats.total_staff || 0;
        document.getElementById("statStaffWithChildren").textContent =
          stats.staff_with_children || 0;
        document.getElementById("statThisMonthNet").textContent =
          "KES " + this.formatCurrency(stats.this_month_net || 0);
        document.getElementById("statChildrenFees").textContent =
          "KES " + this.formatCurrency(stats.children_fees_deducted || 0);
      }
    } catch (error) {
      console.error("Error loading stats:", error);
    }
  },

  /**
   * Load staff list for payroll modal
   */
  loadStaffList: async function () {
    try {
      const response = await API.finance.getStaffForPayroll();

      if (response.success) {
        this.staff = response.data || [];
        this.populateStaffSelect();
      }
    } catch (error) {
      console.error("Error loading staff:", error);
    }
  },

  /**
   * Populate staff select dropdown
   */
  populateStaffSelect: function () {
    const select = document.getElementById("payrollStaffSelect");
    if (!select) return;

    select.innerHTML = '<option value="">-- Select Staff --</option>';

    this.staff.forEach((s) => {
      const option = document.createElement("option");
      option.value = s.id;
      option.textContent = `${s.full_name} (${s.position || "Staff"})`;
      if (s.children_count > 0) {
        option.textContent += ` 👶 ${s.children_count}`;
      }
      select.appendChild(option);
    });
  },

  /**
   * Apply filters
   */
  applyFilters: function () {
    this.loadPayrolls();
    this.loadStats();
  },

  /**
   * Refresh data
   */
  refresh: async function () {
    await Promise.all([this.loadPayrolls(), this.loadStats()]);
    this.showSuccess("Data refreshed");
  },

  /**
   * Render payroll table
   */
  renderTable: function () {
    const tbody = document.getElementById("payrollTableBody");
    if (!tbody) return;

    if (this.filteredPayrolls.length === 0) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-4">
                        <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No payroll records found</p>
                    </td>
                </tr>`;
      return;
    }

    const start = (this.currentPage - 1) * this.perPage;
    const end = start + this.perPage;
    const pagePayrolls = this.filteredPayrolls.slice(start, end);

    let html = "";
    pagePayrolls.forEach((p) => {
      const statusBadge = this.getStatusBadge(p.status);
      const monthNames = [
        "",
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec",
      ];
      const period = `${monthNames[p.payroll_month]} ${p.payroll_year}`;

      const childrenFees = parseFloat(p.children_fees_deducted) || 0;
      const statutoryDed =
        (parseFloat(p.nssf_deduction) || 0) +
        (parseFloat(p.nhif_deduction) || 0) +
        (parseFloat(p.paye_tax) || 0) +
        parseFloat(p.gross_salary) * 0.015;
      const otherDed = (parseFloat(p.other_deductions) || 0) - childrenFees;

      html += `
                <tr>
                    <td>
                        <strong>${this.escapeHtml(p.staff_name)}</strong>
                        <br><small class="text-muted">${this.escapeHtml(
                          p.position || ""
                        )}</small>
                    </td>
                    <td>${period}</td>
                    <td class="text-end">${this.formatCurrency(
                      p.basic_salary
                    )}</td>
                    <td class="text-end text-success">${this.formatCurrency(
                      p.allowances
                    )}</td>
                    <td class="text-end text-danger">${this.formatCurrency(
                      statutoryDed
                    )}</td>
                    <td class="text-end ${
                      childrenFees > 0 ? "text-warning fw-bold" : ""
                    }">
                        ${
                          childrenFees > 0
                            ? this.formatCurrency(childrenFees)
                            : "-"
                        }
                    </td>
                    <td class="text-end text-danger">${
                      otherDed > 0 ? this.formatCurrency(otherDed) : "-"
                    }</td>
                    <td class="text-end fw-bold text-success">${this.formatCurrency(
                      p.net_salary
                    )}</td>
                    <td class="text-center">${statusBadge}</td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-info" onclick="PayrollManagerController.viewPayslip(${
                              p.id
                            })" title="View Payslip">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${
                              p.status === "pending"
                                ? `
                                <button class="btn btn-outline-success" onclick="PayrollManagerController.markAsPaid(${p.id})" title="Mark as Paid">
                                    <i class="fas fa-check"></i>
                                </button>
                            `
                                : ""
                            }
                        </div>
                    </td>
                </tr>`;
    });

    tbody.innerHTML = html;
    this.renderPagination();
  },

  /**
   * Get status badge HTML
   */
  getStatusBadge: function (status) {
    const badges = {
      pending: '<span class="badge bg-warning">Pending</span>',
      processing: '<span class="badge bg-info">Processing</span>',
      paid: '<span class="badge bg-success">Paid</span>',
      cancelled: '<span class="badge bg-secondary">Cancelled</span>',
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
  },

  /**
   * Update payroll count
   */
  updatePayrollCount: function () {
    const countEl = document.getElementById("payrollCount");
    if (countEl) {
      countEl.textContent = `${this.filteredPayrolls.length} records`;
    }
  },

  /**
   * Render pagination
   */
  renderPagination: function () {
    const pagination = document.getElementById("payrollPagination");
    if (!pagination) return;

    const totalPages = Math.ceil(this.filteredPayrolls.length / this.perPage);

    if (totalPages <= 1) {
      pagination.innerHTML = "";
      return;
    }

    let html = "";
    html += `<li class="page-item ${this.currentPage === 1 ? "disabled" : ""}">
            <a class="page-link" href="#" onclick="PayrollManagerController.goToPage(${
              this.currentPage - 1
            }); return false;">&laquo;</a>
        </li>`;

    for (let i = 1; i <= totalPages; i++) {
      if (
        i === 1 ||
        i === totalPages ||
        (i >= this.currentPage - 2 && i <= this.currentPage + 2)
      ) {
        html += `<li class="page-item ${
          i === this.currentPage ? "active" : ""
        }">
                    <a class="page-link" href="#" onclick="PayrollManagerController.goToPage(${i}); return false;">${i}</a>
                </li>`;
      } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
        html += `<li class="page-item disabled"><a class="page-link">...</a></li>`;
      }
    }

    html += `<li class="page-item ${
      this.currentPage === totalPages ? "disabled" : ""
    }">
            <a class="page-link" href="#" onclick="PayrollManagerController.goToPage(${
              this.currentPage + 1
            }); return false;">&raquo;</a>
        </li>`;

    pagination.innerHTML = html;
  },

  goToPage: function (page) {
    const totalPages = Math.ceil(this.filteredPayrolls.length / this.perPage);
    if (page >= 1 && page <= totalPages) {
      this.currentPage = page;
      this.renderTable();
    }
  },

  // ========================================================================
  // PROCESS PAYROLL MODAL
  // ========================================================================

  /**
   * Show process payroll modal
   */
  showProcessPayrollModal: function () {
    this.resetPayrollForm();
    const modal = new bootstrap.Modal(
      document.getElementById("processPayrollModal")
    );
    modal.show();
  },

  /**
   * Reset payroll form
   */
  resetPayrollForm: function () {
    this.selectedStaff = null;
    this.childrenDeductions = [];

    document.getElementById("payrollStaffSelect").value = "";
    document.getElementById("staffInfoCard").classList.add("d-none");
    document.getElementById("payrollStep2").classList.add("d-none");
    document.getElementById("payrollStep3").classList.add("d-none");
    document.getElementById("processPayrollBtn").disabled = true;

    // Reset allowances and deductions
    document.getElementById("houseAllowance").value = 0;
    document.getElementById("transportAllowance").value = 0;
    document.getElementById("otherAllowances").value = 0;
    document.getElementById("otherDeductions").value = 0;
  },

  /**
   * On staff selected in modal
   */
  onStaffSelected: async function () {
    const staffId = document.getElementById("payrollStaffSelect").value;

    if (!staffId) {
      document.getElementById("staffInfoCard").classList.add("d-none");
      document.getElementById("payrollStep2").classList.add("d-none");
      document.getElementById("payrollStep3").classList.add("d-none");
      document.getElementById("processPayrollBtn").disabled = true;
      return;
    }

    try {
      const response = await API.finance.getStaffPayrollDetails(staffId);

      if (response.success) {
        this.selectedStaff = response.data;
        this.displayStaffInfo();
        this.displayChildrenSection();
        this.showSalaryCalculation();
      } else {
        this.showError(response.message || "Failed to load staff details");
      }
    } catch (error) {
      console.error("Error loading staff details:", error);
      this.showError("Failed to load staff details");
    }
  },

  /**
   * Display staff info card
   */
  displayStaffInfo: function () {
    const staff = this.selectedStaff;

    document.getElementById(
      "staffInfoName"
    ).textContent = `${staff.first_name} ${staff.last_name}`;
    document.getElementById("staffInfoPosition").textContent =
      staff.position || "-";
    document.getElementById("staffInfoDept").textContent =
      staff.department || "-";
    document.getElementById("staffInfoSalary").textContent =
      "KES " + this.formatCurrency(staff.basic_salary);
    document.getElementById("staffInfoChildrenCount").textContent =
      staff.children?.length || 0;

    document.getElementById("staffInfoCard").classList.remove("d-none");
  },

  /**
   * Display children fee deduction section
   */
  displayChildrenSection: function () {
    const staff = this.selectedStaff;
    const step2 = document.getElementById("payrollStep2");

    if (!staff.has_children || staff.children.length === 0) {
      step2.classList.add("d-none");
      this.childrenDeductions = [];
      return;
    }

    step2.classList.remove("d-none");
    document.getElementById(
      "childrenCountBadge"
    ).textContent = `${staff.children.length} children`;

    let html = "";
    let totalFees = 0;
    this.childrenDeductions = [];

    staff.children.forEach((child, index) => {
      const feeBalance = parseFloat(child.fee_balance) || 0;
      totalFees += feeBalance;

      // Default deduction amount (can be full balance or partial)
      const defaultDeduction = child.fee_deduction_enabled
        ? Math.min(
            feeBalance,
            (parseFloat(staff.basic_salary) * 0.3) / staff.children.length
          )
        : 0;

      this.childrenDeductions.push({
        staff_child_id: child.staff_child_id,
        student_id: child.student_id,
        student_name: child.student_name,
        fee_balance: feeBalance,
        amount: child.fee_deduction_enabled ? defaultDeduction : 0,
        enabled: child.fee_deduction_enabled,
      });

      html += `
                <div class="card mb-2 ${
                  child.fee_deduction_enabled ? "" : "bg-light"
                }">
                    <div class="card-body py-2">
                        <div class="row align-items-center">
                            <div class="col-md-1">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" 
                                           id="childEnabled${index}" 
                                           ${
                                             child.fee_deduction_enabled
                                               ? "checked"
                                               : ""
                                           }
                                           onchange="PayrollManagerController.toggleChildDeduction(${index}, this.checked)">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <strong>${this.escapeHtml(
                                  child.student_name
                                )}</strong>
                                <br><small class="text-muted">${
                                  child.class_name || ""
                                } | ${child.admission_no}</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <small class="text-muted">Fee Balance</small>
                                <br><strong class="text-danger">KES ${this.formatCurrency(
                                  feeBalance
                                )}</strong>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0">Deduct Amount</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">KES</span>
                                    <input type="number" class="form-control" id="childDeduction${index}"
                                           value="${defaultDeduction.toFixed(
                                             2
                                           )}" step="0.01" min="0" max="${feeBalance}"
                                           ${
                                             child.fee_deduction_enabled
                                               ? ""
                                               : "disabled"
                                           }
                                           onchange="PayrollManagerController.updateChildDeduction(${index}, this.value)">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
    });

    document.getElementById("childrenFeesList").innerHTML = html;
    document.getElementById("totalChildrenFees").textContent =
      "KES " + this.formatCurrency(totalFees);
    this.updateTotalDeductionDisplay();
  },

  /**
   * Toggle child deduction enabled/disabled
   */
  toggleChildDeduction: function (index, enabled) {
    this.childrenDeductions[index].enabled = enabled;
    const input = document.getElementById(`childDeduction${index}`);

    if (enabled) {
      input.disabled = false;
      this.childrenDeductions[index].amount = parseFloat(input.value) || 0;
    } else {
      input.disabled = true;
      this.childrenDeductions[index].amount = 0;
    }

    this.updateTotalDeductionDisplay();
    this.recalculatePayroll();
  },

  /**
   * Update child deduction amount
   */
  updateChildDeduction: function (index, value) {
    const amount = parseFloat(value) || 0;
    const maxAmount = this.childrenDeductions[index].fee_balance;

    // Clamp to max
    this.childrenDeductions[index].amount = Math.min(amount, maxAmount);

    if (amount > maxAmount) {
      document.getElementById(`childDeduction${index}`).value =
        maxAmount.toFixed(2);
    }

    this.updateTotalDeductionDisplay();
    this.recalculatePayroll();
  },

  /**
   * Update total deduction display
   */
  updateTotalDeductionDisplay: function () {
    const total = this.childrenDeductions.reduce(
      (sum, d) => sum + (d.enabled ? d.amount : 0),
      0
    );
    document.getElementById("totalDeductionAmount").textContent =
      "KES " + this.formatCurrency(total);
  },

  /**
   * Show salary calculation section
   */
  showSalaryCalculation: function () {
    document.getElementById("payrollStep3").classList.remove("d-none");
    document.getElementById("calcBasicSalary").textContent =
      this.formatCurrency(this.selectedStaff.basic_salary);
    document.getElementById("processPayrollBtn").disabled = false;

    this.recalculatePayroll();
  },

  /**
   * Recalculate payroll totals
   */
  recalculatePayroll: function () {
    if (!this.selectedStaff) return;

    const basicSalary = parseFloat(this.selectedStaff.basic_salary) || 0;
    const houseAllowance =
      parseFloat(document.getElementById("houseAllowance").value) || 0;
    const transportAllowance =
      parseFloat(document.getElementById("transportAllowance").value) || 0;
    const otherAllowances =
      parseFloat(document.getElementById("otherAllowances").value) || 0;
    const otherDeductions =
      parseFloat(document.getElementById("otherDeductions").value) || 0;

    const totalAllowances =
      houseAllowance + transportAllowance + otherAllowances;
    const grossSalary = basicSalary + totalAllowances;

    // Calculate statutory deductions
    const nssf = this.calculateNSSF(grossSalary);
    const nhif = this.calculateNHIF(grossSalary);
    const paye = this.calculatePAYE(grossSalary - nssf);
    const housingLevy = grossSalary * 0.015;

    // Children fees
    const childrenFees = this.childrenDeductions.reduce(
      (sum, d) => sum + (d.enabled ? d.amount : 0),
      0
    );

    const totalDeductions =
      nssf + nhif + paye + housingLevy + childrenFees + otherDeductions;
    const netSalary = grossSalary - totalDeductions;

    // Update display
    document.getElementById("calcGrossSalary").textContent =
      this.formatCurrency(grossSalary);
    document.getElementById("calcNSSF").textContent = this.formatCurrency(nssf);
    document.getElementById("calcNHIF").textContent = this.formatCurrency(nhif);
    document.getElementById("calcPAYE").textContent = this.formatCurrency(paye);
    document.getElementById("calcHousingLevy").textContent =
      this.formatCurrency(housingLevy);
    document.getElementById("calcChildrenFees").textContent =
      this.formatCurrency(childrenFees);
    document.getElementById("calcTotalDeductions").textContent =
      this.formatCurrency(totalDeductions);
    document.getElementById("calcNetPay").textContent =
      "KES " + this.formatCurrency(netSalary);
  },

  /**
   * Calculate NSSF (Kenya rates)
   */
  calculateNSSF: function (gross) {
    const tierI = Math.min(gross, 7000) * 0.06;
    const tierII = Math.max(0, Math.min(gross - 7000, 29000)) * 0.06;
    return tierI + tierII;
  },

  /**
   * Calculate NHIF (Kenya rates)
   */
  calculateNHIF: function (gross) {
    const rates = [
      [5999, 150],
      [7999, 300],
      [11999, 400],
      [14999, 500],
      [19999, 600],
      [24999, 750],
      [29999, 850],
      [34999, 900],
      [39999, 950],
      [44999, 1000],
      [49999, 1100],
      [59999, 1200],
      [69999, 1300],
      [79999, 1400],
      [89999, 1500],
      [99999, 1600],
      [Infinity, 1700],
    ];
    for (const [limit, contribution] of rates) {
      if (gross <= limit) return contribution;
    }
    return 1700;
  },

  /**
   * Calculate PAYE (Kenya tax bands)
   */
  calculatePAYE: function (taxableIncome) {
    const bands = [
      [24000, 0.1],
      [32333, 0.25],
      [500000, 0.3],
      [800000, 0.325],
      [Infinity, 0.35],
    ];
    const personalRelief = 2400;
    let tax = 0;
    let remaining = taxableIncome;
    let prevLimit = 0;

    for (const [limit, rate] of bands) {
      const taxable = Math.min(remaining, limit - prevLimit);
      tax += taxable * rate;
      remaining -= taxable;
      prevLimit = limit;
      if (remaining <= 0) break;
    }

    return Math.max(0, tax - personalRelief);
  },

  /**
   * Submit payroll
   */
  submitPayroll: async function () {
    if (!this.selectedStaff) {
      this.showError("Please select a staff member");
      return;
    }

    const btn = document.getElementById("processPayrollBtn");
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';

    try {
      const basicSalary = parseFloat(this.selectedStaff.basic_salary) || 0;
      const allowances = {
        house: parseFloat(document.getElementById("houseAllowance").value) || 0,
        transport:
          parseFloat(document.getElementById("transportAllowance").value) || 0,
        other:
          parseFloat(document.getElementById("otherAllowances").value) || 0,
      };
      const otherDeductions =
        parseFloat(document.getElementById("otherDeductions").value) || 0;

      // Prepare children deductions
      const childrenDeductions = this.childrenDeductions
        .filter((d) => d.enabled && d.amount > 0)
        .map((d) => ({
          staff_child_id: d.staff_child_id,
          student_id: d.student_id,
          amount: d.amount,
        }));

      const data = {
        staff_id: this.selectedStaff.id,
        payroll_month: document.getElementById("payrollMonth").value,
        payroll_year: document.getElementById("payrollYear").value,
        basic_salary: basicSalary,
        allowances: allowances,
        other_deductions: otherDeductions,
        children_deductions: childrenDeductions,
      };

      const response = await API.finance.processPayrollWithDeductions(data);

      if (response.success) {
        bootstrap.Modal.getInstance(
          document.getElementById("processPayrollModal")
        ).hide();
        this.showSuccess("Payroll processed successfully");
        await this.refresh();
      } else {
        this.showError(response.message || "Failed to process payroll");
      }
    } catch (error) {
      console.error("Error processing payroll:", error);
      this.showError("Failed to process payroll: " + error.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  },

  // ========================================================================
  // VIEW PAYSLIP
  // ========================================================================

  /**
   * View detailed payslip
   */
  viewPayslip: async function (payrollId) {
    try {
      this.currentPayslipId = payrollId;
      const response = await API.finance.getDetailedPayslip(payrollId);

      if (response.success) {
        this.renderPayslip(response.data);
        const modal = new bootstrap.Modal(
          document.getElementById("viewPayslipModal")
        );
        modal.show();
      } else {
        this.showError(response.message || "Failed to load payslip");
      }
    } catch (error) {
      console.error("Error loading payslip:", error);
      this.showError("Failed to load payslip");
    }
  },

  /**
   * Render payslip content
   */
  renderPayslip: function (data) {
    const monthNames = [
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
    const period = `${monthNames[data.payroll_month]} ${data.payroll_year}`;

    const grossSalary = parseFloat(data.gross_salary) || 0;
    const housingLevy = grossSalary * 0.015;
    const childrenFees = parseFloat(data.total_children_fees) || 0;

    let childrenHtml = "";
    if (data.children_deductions && data.children_deductions.length > 0) {
      childrenHtml = `
                <tr class="table-warning">
                    <td colspan="2"><strong>Children School Fees Deductions</strong></td>
                </tr>`;
      data.children_deductions.forEach((child) => {
        childrenHtml += `
                    <tr>
                        <td class="ps-4">
                            <small>${child.student_name} (${
          child.class_name || "-"
        })</small>
                        </td>
                        <td class="text-end">${this.formatCurrency(
                          child.deducted_amount
                        )}</td>
                    </tr>`;
      });
    }

    const html = `
            <div class="payslip-container" id="payslipPrintArea">
                <div class="text-center mb-4">
                    <h4 class="mb-1">KINGSWAY ACADEMY</h4>
                    <p class="mb-0">P.O. Box 123, Nairobi, Kenya</p>
                    <h5 class="mt-3">PAYSLIP</h5>
                    <p class="text-muted">${period}</p>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><td><strong>Employee Name:</strong></td><td>${
                              data.first_name
                            } ${data.last_name}</td></tr>
                            <tr><td><strong>Staff Number:</strong></td><td>${
                              data.staff_number || "-"
                            }</td></tr>
                            <tr><td><strong>Position:</strong></td><td>${
                              data.position || "-"
                            }</td></tr>
                            <tr><td><strong>Department:</strong></td><td>${
                              data.department || "-"
                            }</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><td><strong>Bank:</strong></td><td>${
                              data.bank_name || "-"
                            }</td></tr>
                            <tr><td><strong>Account:</strong></td><td>${
                              data.bank_account_number || "-"
                            }</td></tr>
                            <tr><td><strong>Pay Period:</strong></td><td>${period}</td></tr>
                            <tr><td><strong>Status:</strong></td><td>${this.getStatusBadge(
                              data.status
                            )}</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success"><i class="fas fa-plus-circle me-1"></i>EARNINGS</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Basic Salary</td>
                                <td class="text-end">${this.formatCurrency(
                                  data.basic_salary
                                )}</td>
                            </tr>
                            <tr>
                                <td>Allowances</td>
                                <td class="text-end">${this.formatCurrency(
                                  data.allowances
                                )}</td>
                            </tr>
                            <tr class="table-success fw-bold">
                                <td>Gross Salary</td>
                                <td class="text-end">${this.formatCurrency(
                                  grossSalary
                                )}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-danger"><i class="fas fa-minus-circle me-1"></i>DEDUCTIONS</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>NSSF</td>
                                <td class="text-end">${this.formatCurrency(
                                  data.nssf_deduction
                                )}</td>
                            </tr>
                            <tr>
                                <td>NHIF</td>
                                <td class="text-end">${this.formatCurrency(
                                  data.nhif_deduction
                                )}</td>
                            </tr>
                            <tr>
                                <td>PAYE Tax</td>
                                <td class="text-end">${this.formatCurrency(
                                  data.paye_tax
                                )}</td>
                            </tr>
                            <tr>
                                <td>Housing Levy (1.5%)</td>
                                <td class="text-end">${this.formatCurrency(
                                  housingLevy
                                )}</td>
                            </tr>
                            ${childrenHtml}
                            ${
                              childrenFees > 0
                                ? `
                            <tr class="table-warning fw-bold">
                                <td>Total Children Fees</td>
                                <td class="text-end">${this.formatCurrency(
                                  childrenFees
                                )}</td>
                            </tr>`
                                : ""
                            }
                            <tr>
                                <td>Other Deductions</td>
                                <td class="text-end">${this.formatCurrency(
                                  (data.other_deductions || 0) - childrenFees
                                )}</td>
                            </tr>
                            <tr class="table-danger fw-bold">
                                <td>Total Deductions</td>
                                <td class="text-end">${this.formatCurrency(
                                  data.total_deductions
                                )}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="card bg-success text-white mt-4">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-1">NET PAY</h5>
                        <h2 class="mb-0">KES ${this.formatCurrency(
                          data.net_salary
                        )}</h2>
                    </div>
                </div>
                
                <div class="mt-4 text-center text-muted small">
                    <p class="mb-0">This is a computer generated payslip and does not require a signature.</p>
                    <p class="mb-0">Generated on ${new Date().toLocaleDateString()}</p>
                </div>
            </div>`;

    document.getElementById("payslipContent").innerHTML = html;
  },

  /**
   * Mark payroll as paid
   */
  markAsPaid: async function (payrollId) {
    if (
      !confirm(
        "Mark this payroll as paid? This will also record fee payments for any children deductions."
      )
    ) {
      return;
    }

    try {
      const paymentRef = prompt("Enter payment reference (optional):") || "";
      const response = await API.finance.markPayrollPaid(payrollId, paymentRef);

      if (response.success) {
        this.showSuccess("Payroll marked as paid");
        await this.refresh();
      } else {
        this.showError(response.message || "Failed to mark as paid");
      }
    } catch (error) {
      console.error("Error marking as paid:", error);
      this.showError("Failed to mark payroll as paid");
    }
  },

  /**
   * Print payslip
   */
  printPayslip: function () {
    const content = document.getElementById("payslipPrintArea").innerHTML;
    const printWindow = window.open("", "_blank");
    printWindow.document.write(`
            <html>
            <head>
                <title>Payslip</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { padding: 20px; }
                    @media print { .no-print { display: none; } }
                </style>
            </head>
            <body>${content}</body>
            </html>
        `);
    printWindow.document.close();
    printWindow.onload = () => {
      printWindow.print();
    };
  },

  /**
   * Download payslip as PDF
   */
  downloadPayslip: function () {
    this.showSuccess("PDF download feature coming soon");
  },

  // ========================================================================
  // UTILITIES
  // ========================================================================

  formatCurrency: function (amount) {
    return parseFloat(amount || 0).toLocaleString("en-KE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  },

  escapeHtml: function (text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  },

  showSuccess: function (message) {
    if (typeof showNotification === "function") {
      showNotification(message, "success");
    } else {
      alert("✅ " + message);
    }
  },

  showError: function (message) {
    if (typeof showNotification === "function") {
      showNotification(message, "error");
    } else {
      alert("❌ " + message);
    }
  },
};

// Initialize on DOM ready
document.addEventListener("DOMContentLoaded", () => {
  PayrollManagerController.init();
});
