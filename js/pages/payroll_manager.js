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
  bulkPayrollRows: [],

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

      this.payrolls = Array.isArray(response) ? response : (response?.payrolls || response?.data || []);

      this.filteredPayrolls = [...this.payrolls];
      this.renderTable();
      this.updatePayrollCount();
    } catch (error) {
      console.error("Error loading payrolls:", error);
      this.payrolls = [];
      this.filteredPayrolls = [];
      this.renderTable();
      this.updatePayrollCount();
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

      const stats = response?.stats || response || {};
      document.getElementById("statTotalStaff").textContent =
        stats.total_staff || 0;
      document.getElementById("statStaffWithChildren").textContent =
        stats.staff_with_children || 0;
      document.getElementById("statThisMonthNet").textContent =
        "KES " + this.formatCurrency(stats.this_month_net || 0);
      document.getElementById("statChildrenFees").textContent =
        "KES " + this.formatCurrency(stats.children_fees_deducted || 0);
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
      // apiCall unwraps the response — response IS the data array
      this.staff = Array.isArray(response) ? response : (response?.data || []);
      this.populateStaffSelect();
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
      const missing = Array.isArray(s.payroll_missing_fields) ? s.payroll_missing_fields : [];
      option.value = s.id;
      option.textContent = `${s.full_name} (${s.position || "Staff"})`;
      if (s.children_count > 0) {
        option.textContent += ` 👶 ${s.children_count}`;
      }
      if (s.payroll_eligible === false) {
        option.disabled = true;
        option.textContent += ` — BLOCKED: Missing ${missing.join(", ")}`;
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
      var emptyRow = document.createElement("tr");
      var emptyCell = document.createElement("td");
      emptyCell.setAttribute("colspan", "10");
      emptyCell.style.textAlign = "center";
      emptyCell.style.padding = "48px 20px";
      emptyCell.style.color = "#8895a7";
      var emptyIcon = document.createElement("div");
      emptyIcon.style.fontSize = "2.5rem";
      emptyIcon.style.marginBottom = "12px";
      emptyIcon.style.opacity = "0.4";
      emptyIcon.textContent = "\uD83D\uDCCB";
      var emptyText = document.createElement("p");
      emptyText.style.fontWeight = "600";
      emptyText.style.margin = "0";
      emptyText.textContent = "No payroll records found";
      emptyCell.appendChild(emptyIcon);
      emptyCell.appendChild(emptyText);
      emptyRow.appendChild(emptyCell);
      tbody.replaceChildren(emptyRow);
      this.renderPagination();
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
                        <div style="font-weight: 700; color: var(--payroll-ink, #1a1f2e);">${this.escapeHtml(p.staff_name)}</div>
                        <small style="color: #8895a7; font-size: 0.78rem;">${this.escapeHtml(p.position || "")}</small>
                    </td>
                    <td style="font-weight: 600;">${period}</td>
                    <td class="table-amount">${this.formatCurrency(p.basic_salary)}</td>
                    <td class="table-amount" style="color: #1a7a4c;">${this.formatCurrency(p.allowances)}</td>
                    <td class="table-amount negative">${this.formatCurrency(statutoryDed)}</td>
                    <td class="table-amount" style="${childrenFees > 0 ? 'color: #9a7d2e; font-weight: 700;' : 'color: #8895a7;'}">
                        ${childrenFees > 0 ? this.formatCurrency(childrenFees) : "-"}
                    </td>
                    <td class="table-amount negative">${otherDed > 0 ? this.formatCurrency(otherDed) : "-"}</td>
                    <td class="table-amount" style="font-weight: 800; color: #1a7a4c; font-size: 0.92rem;">${this.formatCurrency(p.net_salary)}</td>
                    <td class="text-center">${statusBadge}</td>
                    <td class="text-center">
                        <button class="table-action-btn" onclick="PayrollManagerController.viewPayslip(${p.id})" title="View Payslip">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${p.status === "pending" ? `
                            <button class="table-action-btn approve" onclick="PayrollManagerController.approvePayroll(${p.id})" title="Director Approve">
                                <i class="fas fa-user-check"></i>
                            </button>
                        ` : ""}
                        ${p.status === "approved" ? `
                            <button class="table-action-btn approve" onclick="PayrollManagerController.markAsPaid(${p.id})" title="Release Payment">
                                <i class="fas fa-check-circle"></i>
                            </button>
                        ` : ""}
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
      pending: '<span class="status-badge pending"><i class="fas fa-clock"></i> Pending</span>',
      processing: '<span class="status-badge processing"><i class="fas fa-spinner"></i> Processing</span>',
      approved: '<span class="status-badge processing"><i class="fas fa-user-check"></i> Approved</span>',
      paid: '<span class="status-badge paid"><i class="fas fa-check"></i> Paid</span>',
      cancelled: '<span class="status-badge cancelled"><i class="fas fa-times"></i> Cancelled</span>',
    };
    return badges[status] || '<span class="status-badge pending">Unknown</span>';
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
   * Show bulk payroll modal
   */
  showBulkPayrollModal: async function () {
    const month = new Date().getMonth() + 1;
    const year = new Date().getFullYear();
    document.getElementById("bulkPayrollMonth").value = month;
    document.getElementById("bulkPayrollYear").value = year;
    const modal = new bootstrap.Modal(document.getElementById("bulkPayrollModal"));
    modal.show();
    await this.prepareBulkPayrollRows();
  },

  prepareBulkPayrollRows: async function () {
    const month = document.getElementById("bulkPayrollMonth").value;
    const year = document.getElementById("bulkPayrollYear").value;
    try {
      const response = await API.finance.getBulkPayrollPreview(month, year);
      this.bulkPayrollRows = Array.isArray(response) ? response.map((row) => ({
        ...row,
        selected: row.payroll_eligible === true && (parseFloat(row.basic_salary) || 0) > 0,
      })) : [];
    } catch (error) {
      console.error("Error preparing bulk payroll:", error);
      this.bulkPayrollRows = [];
      this.showError(error.message || "Failed to prepare bulk payroll preview");
    }
    this.renderBulkPayrollRows();
  },

  renderBulkPayrollRows: function () {
    const tbody = document.getElementById("bulkPayrollTableBody");
    if (!tbody) return;

    const rows = [];
    if (this.bulkPayrollRows.length === 0) {
      const tr = document.createElement("tr");
      const td = document.createElement("td");
      td.colSpan = 9;
      td.className = "text-center py-4 text-muted";
      td.textContent = "No active staff found";
      tr.appendChild(td);
      rows.push(tr);
      tbody.replaceChildren(...rows);
      this.updateBulkPayrollSummary();
      return;
    }

    this.bulkPayrollRows.forEach((row, index) => {
      const tr = document.createElement("tr");
      const selectTd = document.createElement("td");
      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.className = "form-check-input";
      checkbox.checked = row.selected;
      checkbox.disabled = !row.payroll_eligible;
      checkbox.addEventListener("change", () => this.setBulkStaffSelected(index, checkbox.checked));
      selectTd.appendChild(checkbox);

      const staffTd = document.createElement("td");
      const name = document.createElement("strong");
      name.textContent = row.staff_name;
      const br = document.createElement("br");
      const staffNo = document.createElement("small");
      staffNo.className = "text-muted";
      staffNo.textContent = row.staff_no || "-";
      staffTd.appendChild(name);
      staffTd.appendChild(br);
      staffTd.appendChild(staffNo);
      if (!row.payroll_eligible) {
        const blocked = document.createElement("div");
        blocked.className = "text-danger small fw-bold mt-1";
        blocked.textContent = "Blocked: Missing " + row.missing_fields.join(", ");
        staffTd.appendChild(blocked);
      }

      const positionTd = document.createElement("td");
      positionTd.textContent = row.position;

      const basicTd = document.createElement("td");
      basicTd.className = "text-end";
      basicTd.textContent = this.formatCurrency(row.basic_salary);

      const allowanceTd = document.createElement("td");
      allowanceTd.className = "text-end text-success";
      allowanceTd.textContent = this.formatCurrency(row.allowances || 0);

      const statutoryTd = document.createElement("td");
      statutoryTd.className = "text-end";
      statutoryTd.textContent = this.formatCurrency(row.statutory_deductions);

      const otherDedTd = document.createElement("td");
      otherDedTd.className = "text-end";
      otherDedTd.textContent = this.formatCurrency(row.other_deductions || 0);

      const housingTd = document.createElement("td");
      housingTd.className = "text-end";
      housingTd.textContent = this.formatCurrency(row.housing_levy);

      const netTd = document.createElement("td");
      netTd.className = "text-end fw-bold text-success";
      netTd.textContent = this.formatCurrency(row.net_salary);

      tr.append(selectTd, staffTd, positionTd, basicTd, allowanceTd, statutoryTd, otherDedTd, housingTd, netTd);
      rows.push(tr);
    });

    tbody.replaceChildren(...rows);
    this.updateBulkPayrollSummary();
  },

  setBulkStaffSelected: function (index, selected) {
    this.bulkPayrollRows[index].selected = selected;
    this.updateBulkPayrollSummary();
  },

  toggleBulkStaffSelection: function (selected) {
    this.bulkPayrollRows.forEach((row) => {
      row.selected = selected && row.payroll_eligible && row.basic_salary > 0;
    });
    this.renderBulkPayrollRows();
  },

  updateBulkPayrollSummary: function () {
    const summary = document.getElementById("bulkPayrollSummary");
    if (!summary) return;
    const selectedRows = this.bulkPayrollRows.filter((row) => row.selected);
    const totalNet = selectedRows.reduce((sum, row) => sum + row.net_salary, 0);
    summary.textContent = `${selectedRows.length} selected · ${this.formatCurrency(totalNet)} net`;
  },

  submitBulkPayroll: async function () {
    const selectedRows = this.bulkPayrollRows.filter((row) => row.selected);
    if (selectedRows.length === 0) {
      this.showError("Select at least one staff member to process.");
      return;
    }

    var self = this;
    self.showConfirm(
      "Process payroll for " + selectedRows.length + " staff members?",
      function () {
        self._executeBulkProcess(selectedRows);
      }
    );
  },

  _executeBulkProcess: async function (selectedRows) {
    var self = this;
    const month = document.getElementById("bulkPayrollMonth").value;
    const year = document.getElementById("bulkPayrollYear").value;

    try {
      const response = await API.finance.processBulkPayroll({
        staff_ids: selectedRows.map((row) => row.staff_id),
        payroll_month: month,
        payroll_year: year,
      });

      const modal = bootstrap.Modal.getInstance(document.getElementById("bulkPayrollModal"));
      if (modal) modal.hide();
      await this.refresh();

      const processed = response && response.processed_count ? response.processed_count : 0;
      const failed = response && response.failed_count ? response.failed_count : 0;
      if (failed > 0) {
        this.showError(`Prepared ${processed}; failed ${failed}. Check console for details.`);
        console.warn("Bulk payroll failures:", response.failed || []);
      } else {
        this.showSuccess(`Bulk payroll prepared for director review: ${processed} staff members.`);
      }
    } catch (error) {
      console.error("Error processing bulk payroll:", error);
      this.showError(error.message || "Failed to process bulk payroll");
    }
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
    var staffId = document.getElementById("payrollStaffSelect").value;

    if (!staffId) {
      document.getElementById("staffInfoCard").classList.add("d-none");
      document.getElementById("payrollStep2").classList.add("d-none");
      document.getElementById("payrollStep3").classList.add("d-none");
      document.getElementById("processPayrollBtn").disabled = true;
      return;
    }

    try {
      var response = await API.finance.getStaffPayrollDetails(staffId);

      // apiCall unwraps handleApiResponse: response IS the data payload
      // The backend returns: formatResponse(true, $staff, ...) which becomes
      // {status:'success', data:{id,first_name,...,children:[...]}}
      // After handleApiResponse unwraps: response = {id,first_name,...,children:[...]}
      var staffData = response || {};
      if (staffData && staffData.id) {
        this.selectedStaff = staffData;
        this.selectedStaff.children = staffData.children || [];
        this.displayStaffInfo();
        this.displayChildrenSection();
        this.showSalaryCalculation();
      } else {
        this.showError("Staff not found. Please select a different staff member.");
      }
    } catch (error) {
      console.error("Error loading staff details:", error);
      var msg = "Failed to load staff details. ";
      if (error && error.message) {
        if (error.message.includes("401") || error.message.toLowerCase().includes("auth")) {
          msg += "Your session may have expired. Please refresh the page.";
        } else if (error.message.toLowerCase().includes("not found")) {
          msg += "Staff member not found.";
        } else {
          msg += error.message;
        }
      }
      this.showError(msg);
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
        gross_fee_amount: feeBalance,
        fee_invoice_id: child.fee_invoice_id || child.invoice_id || null,
        term_id: child.term_id || null,
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
          fee_invoice_id: d.fee_invoice_id,
          term_id: d.term_id,
          gross_fee_amount: d.gross_fee_amount,
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

      if (response && (response.id || response.payroll_id || response.payslip_id || response.net_salary !== undefined || response.staff_id)) {
        var modalEl = document.getElementById("processPayrollModal");
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        this.showSuccess("Payroll processed successfully");
        await this.refresh();
      } else {
        this.showError((response && response.message) || "Failed to process payroll");
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

      if (response && response.id) {
        this.renderPayslip(response);
        var modal = new bootstrap.Modal(
          document.getElementById("viewPayslipModal")
        );
        modal.show();
      } else {
        this.showError((response && response.message) || "Failed to load payslip");
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
                            <small>${this.escapeHtml(child.student_name)} (${this.escapeHtml(
          child.class_name || "-"
        )})</small>
                        </td>
                        <td class="text-end">${this.formatCurrency(
                          child.deducted_amount
                        )}</td>
                    </tr>`;
      });
    }

    const paymentModeLabels = {
      bank: "Bank Transfer",
      cash: "Cash",
      mpesa: "M-Pesa",
      airtel_money: "Airtel Money",
    };
    const paymentMode = paymentModeLabels[data.payment_mode] || "Not Recorded";
    const datePaid = data.payment_date
      ? new Date(data.payment_date).toLocaleString("en-KE")
      : "Not Paid";

    const html = `
            <div class="payslip-container" id="payslipPrintArea">
                <div class="text-center mb-4">
                    <img src="${window.APP_BASE || ""}/images/kings%20logo.png" alt="Kingsway Preparatory School Logo" style="width: 72px; height: 72px; object-fit: contain; margin-bottom: 8px;">
                    <h4 class="mb-1">KINGSWAY PREPARATORY SCHOOL</h4>
                    <p class="mb-0">P.O. Box 123, Nairobi, Kenya</p>
                    <h5 class="mt-3">PAYSLIP</h5>
                    <p class="text-muted">${period}</p>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><td><strong>Employee Name:</strong></td><td>${this.escapeHtml(
                              `${data.first_name || ""} ${data.last_name || ""}`.trim()
                            )}</td></tr>
                            <tr><td><strong>Staff Number:</strong></td><td>${this.escapeHtml(
                              data.staff_no || data.staff_number || "-"
                            )}</td></tr>
                            <tr><td><strong>Position:</strong></td><td>${this.escapeHtml(
                              data.position || "-"
                            )}</td></tr>
                            <tr><td><strong>Department:</strong></td><td>${this.escapeHtml(
                              data.department || "-"
                            )}</td></tr>
                            <tr><td><strong>KRA PIN:</strong></td><td>${this.escapeHtml(
                              data.kra_pin || "-"
                            )}</td></tr>
                            <tr><td><strong>NSSF No:</strong></td><td>${this.escapeHtml(
                              data.nssf_no || "-"
                            )}</td></tr>
                            <tr><td><strong>NHIF No:</strong></td><td>${this.escapeHtml(
                              data.nhif_no || "-"
                            )}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><td><strong>Bank:</strong></td><td>${this.escapeHtml(
                              data.bank_name || "-"
                            )}</td></tr>
                            <tr><td><strong>Account Number:</strong></td><td>${this.escapeHtml(
                              data.bank_account_number || data.bank_account || "-"
                            )}</td></tr>
                            <tr><td><strong>Payment Mode:</strong></td><td>${paymentMode}</td></tr>
                            <tr><td><strong>Payment Ref:</strong></td><td>${this.escapeHtml(
                              data.payment_reference || "-"
                            )}</td></tr>
                            <tr><td><strong>Date Paid:</strong></td><td>${datePaid}</td></tr>
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
  approvePayroll: function (payrollId) {
    var self = this;
    self.showConfirm(
      "Approve this payroll for accountant payment release?",
      function () {
        self._executeApprove(payrollId);
      }
    );
  },

  _executeApprove: async function (payrollId) {
    try {
      const response = await API.finance.approvePayroll(payrollId);
      if (response && (response.status === "approved" || response.payroll_id)) {
        this.showSuccess("Payroll approved for payment release");
        await this.refresh();
      } else {
        this.showError((response && response.message) || "Failed to approve payroll");
      }
    } catch (error) {
      console.error("Error approving payroll:", error);
      this.showError(error.message || "Failed to approve payroll");
    }
  },

  markAsPaid: function (payrollId) {
    var self = this;
    self.showConfirm(
      "Mark this payroll as paid? This will also record fee payments for any children deductions.",
      function () {
        self.showPaymentModeModal(function (mode, reference) {
          self._executeMarkAsPaid(payrollId, mode, reference);
        });
      }
    );
  },

  _executeMarkAsPaid: async function (payrollId, paymentMode, paymentRef) {
    try {
      const response = await API.finance.markPayrollPaid(payrollId, paymentRef, paymentMode);

      if (response && (response.payroll_id || response.status === "paid" || response.status === "success" || response.id || response.message)) {
        this.showSuccess("Payroll marked as paid successfully");
        await this.refresh();
      } else {
        this.showError((response && response.message) || "Failed to mark as paid");
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
    const source = document.getElementById("payslipPrintArea");
    if (!source) {
      this.showError("Payslip is not ready to print");
      return;
    }

    const printWindow = window.open("", "_blank");
    const doc = printWindow.document;
    doc.title = "Payslip";

    const bootstrap = doc.createElement("link");
    bootstrap.rel = "stylesheet";
    bootstrap.href = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css";
    doc.head.appendChild(bootstrap);

    const style = doc.createElement("style");
    style.textContent = "body { padding: 20px; } @media print { .no-print { display: none; } }";
    doc.head.appendChild(style);
    doc.body.appendChild(source.cloneNode(true));
    printWindow.focus();
    setTimeout(() => printWindow.print(), 300);
  },

  /**
   * Download payslip as PDF (uses print dialog with auto-trigger)
   */
  downloadPayslip: function () {
    var source = document.getElementById("payslipPrintArea");
    if (!source) {
      this.showError("Payslip is not ready to download");
      return;
    }

    var printWindow = window.open("", "_blank");
    var doc = printWindow.document;
    doc.title = "Payslip - Download";

    var bsLink = doc.createElement("link");
    bsLink.rel = "stylesheet";
    bsLink.href = "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css";
    doc.head.appendChild(bsLink);

    var style = doc.createElement("style");
    style.textContent = "@page { margin: 15mm; } body { padding: 0; font-family: 'DM Sans', Arial, sans-serif; } @media print { .no-print { display: none; } }";
    doc.head.appendChild(style);
    doc.body.appendChild(source.cloneNode(true));
    printWindow.focus();
    setTimeout(function () { printWindow.print(); }, 500);
  },

  /**
   * Export currently visible payroll rows as CSV
   */
  exportCsv: function () {
    const rows = this.filteredPayrolls || [];
    if (!rows.length) {
      this.showError("No payroll records to export");
      return;
    }

    const headers = [
      "Staff",
      "Period",
      "Basic Salary",
      "Allowances",
      "Statutory Deductions",
      "Children Fees",
      "Other Deductions",
      "Net Pay",
      "Status",
    ];

    const csvRows = rows.map((p) => [
      `${p.staff_name || `${p.first_name || ""} ${p.last_name || ""}`.trim()}`,
      `${this.getMonthName(p.payroll_month)} ${p.payroll_year}`,
      p.basic_salary || 0,
      p.allowances || 0,
      p.statutory_deductions || 0,
      p.children_fee_deductions || 0,
      p.other_deductions || 0,
      p.net_salary || 0,
      p.status || "",
    ]);

    const csv = [headers, ...csvRows]
      .map((row) => row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(","))
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `payroll-report-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  },

  /**
   * Print the payroll table as a PDF via the browser print dialog
   */
  printPayrollReport: function () {
    const table = document.getElementById("payrollTable");
    if (!table || !(this.filteredPayrolls || []).length) {
      this.showError("No payroll records to print");
      return;
    }

    const printWindow = window.open("", "_blank");
    const doc = printWindow.document;
    doc.title = "Payroll Report";

    const style = doc.createElement("style");
    style.textContent = `
      body { font-family: Arial, sans-serif; padding: 24px; color: #111814; }
      h1 { margin: 0 0 4px; font-family: Georgia, serif; }
      p { margin: 0 0 18px; color: #536158; }
      table { width: 100%; border-collapse: collapse; font-size: 12px; }
      th { background: #082d21; color: #fff; text-align: left; }
      th, td { border: 1px solid #d9e3dc; padding: 8px; }
      .btn-group, button { display: none !important; }
    `;
    doc.head.appendChild(style);

    const title = doc.createElement("h1");
    title.textContent = "Payroll Report";
    const generated = doc.createElement("p");
    generated.textContent = `Generated ${new Date().toLocaleString()}`;
    const clonedTable = table.cloneNode(true);

    doc.body.appendChild(title);
    doc.body.appendChild(generated);
    doc.body.appendChild(clonedTable);
    printWindow.focus();
    printWindow.print();
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

  /**
   * Get month name from month number (1-12)
   */
  getMonthName: function (monthNum) {
    var months = [
      "", "January", "February", "March", "April", "May", "June",
      "July", "August", "September", "October", "November", "December"
    ];
    return months[parseInt(monthNum)] || "";
  },

  showSuccess: function (message) {
    if (typeof showNotification === "function") {
      showNotification(message, "success");
    } else {
      alert("✅ " + message);
    }
  },

  /**
   * Show confirmation modal (replaces browser confirm())
   */
  showConfirm: function (message, onConfirm, type) {
    var self = this;
    var modal = document.getElementById("payrollConfirmModal");
    var header = document.getElementById("payrollConfirmHeader");
    var okBtn = document.getElementById("payrollConfirmOk");
    var titleText = document.getElementById("payrollConfirmTitleText");

    titleText.textContent = type === "danger" ? "Warning" : "Confirm";
    document.getElementById("payrollConfirmMessage").textContent = message;

    if (type === "danger") {
      header.style.background = "linear-gradient(135deg, #8B0000, #dc3545)";
      okBtn.style.background = "#8B0000";
    } else {
      header.style.background = "linear-gradient(135deg, #0d4f2a, #198754)";
      okBtn.style.background = "#0d4f2a";
    }

    var bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    // Remove old listeners by cloning
    var newOk = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(newOk, okBtn);
    newOk.id = "payrollConfirmOk";

    newOk.addEventListener("click", function () {
      bsModal.hide();
      if (typeof onConfirm === "function") onConfirm();
    });
  },

  /**
   * Show payment mode modal (replaces browser prompt())
   * Returns { mode, reference } via callback
   */
  showPaymentModeModal: function (onConfirm) {
    var modal = document.getElementById("payrollPaymentModeModal");
    var refInput = document.getElementById("paymentReferenceInput");
    var okBtn = document.getElementById("payrollPaymentConfirmOk");

    // Reset
    refInput.value = "";
    document.getElementById("modeBank").checked = true;

    var bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    // Remove old listeners by cloning
    var newOk = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(newOk, okBtn);
    newOk.id = "payrollPaymentConfirmOk";

    newOk.addEventListener("click", function () {
      var selectedMode = document.querySelector('input[name="paymentMode"]:checked');
      var mode = selectedMode ? selectedMode.value : "bank";
      var reference = refInput.value.trim();
      bsModal.hide();
      if (typeof onConfirm === "function") onConfirm(mode, reference);
    });
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
