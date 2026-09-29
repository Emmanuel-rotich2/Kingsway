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
  selectedPaymentIds: new Set(),
  statutoryRules: {},
  accessReady: false,
  compensationSetup: null,

  /**
   * Initialize controller
   */
  init: async function () {
    try {
      if (window.AuthContext?.ready) await window.AuthContext.ready();

      const allowed =
        this.canManagePayroll() ||
        this.canApprovePayroll() ||
        this.canProcessPayroll() ||
        window.AuthContext?.hasPermission?.('staff.payslip.manage') ||
        window.AuthContext?.hasPermission?.('staff.payslip.self');
      if (!allowed) {
        window.showNotification?.('You do not have payroll access.', 'error');
        return;
      }

      this.applyRoleMode();
      this.applyPayrollFormMode();
      this.renderPayrollHeader();
      this.setupCompensationEvents();

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
        this.loadStatutoryRules(),
      ]);

    } catch (error) {
      console.error("❌ Error initializing Payroll Manager:", error);
      this.showError("Failed to initialize payroll manager");
    }
  },

  loadStatutoryRules: async function () {
    try {
      const response = await callAPI('/staff/statutory-compliance?year=' + new Date().getFullYear(), 'GET');
      const data = response?.data || response || {};
      this.statutoryRules = {};
      (Array.isArray(data.rules) ? data.rules : []).forEach((rule) => {
        this.statutoryRules[rule.agency + ':' + rule.rule_code] = rule.rules || {};
      });
    } catch (error) {
      console.error('Unable to load statutory rules:', error);
      this.statutoryRules = {};
    }
  },

  canManagePayroll: function () {
    return window.AuthContext?.hasPermission?.('staff.payroll.manage') || false;
  },

  canManageCompensation: function () {
    const user=window.AuthContext?.getUser?.()||{};
    const roles=[user.role_name,...(Array.isArray(user.roles)?user.roles.map(r=>r?.name||r):[])].filter(Boolean).map(r=>String(r).toLowerCase());
    return window.AuthContext?.hasPermission?.('staff.payroll.manage') && roles.some(r=>['school administrator','director','system administrator'].includes(r));
  },

  setupCompensationEvents: function () {
    const button=document.querySelector('[onclick="PayrollManagerController.showCompensationModal()"]');
    if(button&&!this.canManageCompensation())button.remove();
    document.getElementById('roleSalaryRateForm')?.addEventListener('submit',async e=>{
      e.preventDefault();try{await API.finance.saveRoleSalaryRate({role_id:Number(document.getElementById('salaryRateRole').value),gross_salary:Number(document.getElementById('salaryRateAmount').value),effective_from:document.getElementById('salaryRateFrom').value});this.showSuccess('Role salary rate saved');await this.loadCompensationSetup();await this.prepareBulkPayrollRows();}catch(err){this.showError(err.message||'Could not save role salary rate');}
    });
    document.getElementById('individualSalaryForm')?.addEventListener('submit',async e=>{
      e.preventDefault();try{await API.finance.saveStaffSalaryOverride({staff_id:Number(document.getElementById('individualSalaryStaff').value),gross_salary:Number(document.getElementById('individualSalaryAmount').value),effective_from:document.getElementById('individualSalaryFrom').value});this.showSuccess('Individual salary override saved');await this.loadCompensationSetup();await this.prepareBulkPayrollRows();}catch(err){this.showError(err.message||'Could not save individual salary');}
    });
    document.getElementById('clearIndividualSalaryOverride')?.addEventListener('click',async()=>{
      const staffId=Number(document.getElementById('individualSalaryStaff').value);if(!staffId)return;
      try{await API.finance.saveStaffSalaryOverride({staff_id:staffId,clear_override:true,effective_from:document.getElementById('individualSalaryFrom').value});this.showSuccess('Role salary will apply from the selected date');await this.loadCompensationSetup();await this.prepareBulkPayrollRows();}catch(err){this.showError(err.message||'Could not end individual override');}
    });
    document.getElementById('individualSalaryStaff')?.addEventListener('change',()=>this.syncIndividualSalarySelection());
    document.getElementById('awardSelectionMode')?.addEventListener('change',e=>{
      document.getElementById('awardDepartmentWrap')?.classList.toggle('d-none',e.target.value!=='department_all');
      document.getElementById('awardStaffWrap')?.classList.toggle('d-none',e.target.value!=='selected_staff');
    });
    document.getElementById('compensationAwardHistoryBody')?.addEventListener('click',async e=>{
      const button=e.target.closest('[data-cancel-award]');
      if(!button)return;
      try{await API.finance.cancelCompensationAward(Number(button.dataset.cancelAward));this.showSuccess('Award batch cancelled');await this.loadCompensationSetup();await this.prepareBulkPayrollRows();}
      catch(err){this.showError(err.message||'This award could not be cancelled');}
    });
    document.getElementById('compensationAwardForm')?.addEventListener('submit',async e=>{
      e.preventDefault();
      const periods=[...document.querySelectorAll('#awardMonths input:checked')].map(x=>({month:Number(x.value),year:Number(document.getElementById('awardYear').value)}));
      const mode=document.getElementById('awardSelectionMode').value;
      const payload={award_kind:document.getElementById('awardKind').value,award_name:document.getElementById('awardName').value.trim(),award_type:document.getElementById('awardType').value,amount_per_month:Number(document.getElementById('awardAmount').value),selection_mode:mode,department_id:mode==='department_all'?Number(document.getElementById('awardDepartment').value):null,staff_ids:mode==='selected_staff'?[...document.getElementById('awardStaff').selectedOptions].map(o=>Number(o.value)):[],periods};
      try{const result=await API.finance.createCompensationAward(payload);this.showSuccess(`Award scheduled for ${result?.recipient_count||0} staff members across ${result?.period_count||0} months`);document.getElementById('compensationAwardForm').reset();this.setDefaultAwardMonths();await this.prepareBulkPayrollRows();}catch(err){this.showError(err.message||'Could not schedule award');}
    });
    this.setDefaultAwardMonths();
  },

  setDefaultAwardMonths: function () {
    const now=new Date(),year=document.getElementById('awardYear');
    if(year){const current=now.getFullYear();year.innerHTML=[current-1,current,current+1].map(y=>`<option value="${y}" ${y===current?'selected':''}>${y}</option>`).join('');}
    const salaryFrom=document.getElementById('individualSalaryFrom');if(salaryFrom&&!salaryFrom.value)salaryFrom.value=`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-01`;
    const roleFrom=document.getElementById('salaryRateFrom');if(roleFrom&&!roleFrom.value)roleFrom.value=`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-01`;
    const holder=document.getElementById('awardMonths');if(holder&&!holder.children.length)holder.innerHTML=Array.from({length:12},(_,i)=>`<label class="btn btn-sm btn-outline-secondary"><input class="form-check-input me-1" type="checkbox" value="${i+1}" ${i===now.getMonth()?'checked':''}>${new Date(2000,i,1).toLocaleString('en',{month:'short'})}</label>`).join('');
  },

  showCompensationModal: async function () {
    if(!this.canManageCompensation()){this.showError('Only an authorised school administrator or director can manage salary rates and awards');return;}
    await this.loadCompensationSetup();bootstrap.Modal.getOrCreateInstance(document.getElementById('compensationModal')).show();
  },

  loadCompensationSetup: async function () {
    const response=await API.finance.getCompensationSetup();this.compensationSetup=response?.data||response||{};
    const roles=this.compensationSetup.roles||[],staff=this.compensationSetup.staff||[],departments=this.compensationSetup.departments||[];
    const roleSelect=document.getElementById('salaryRateRole');if(roleSelect)roleSelect.innerHTML='<option value="">Select primary role</option>'+roles.map(r=>`<option value="${Number(r.id)}">${this.escapeHtml(r.name)}</option>`).join('');
    const rateBody=document.getElementById('roleSalaryRatesBody');if(rateBody)rateBody.innerHTML=roles.map(r=>`<tr><td>${this.escapeHtml(r.name)}</td><td>${r.gross_salary==null?'Not configured':this.formatCurrency(r.gross_salary)}</td><td>${this.escapeHtml(r.effective_from||'')}</td><td>${this.escapeHtml(r.effective_to||'—')}</td></tr>`).join('');
    const staffOptions=staff.map(s=>`<option value="${Number(s.id)}" data-override="${Number(s.salary_override)||0}" data-salary="${Number(s.individual_salary)||0}" data-role="${this.escapeHtml(s.primary_role||'No primary role')}" data-rate="${Number(s.role_gross_salary)||0}">${this.escapeHtml(s.full_name)} · ${this.escapeHtml(s.staff_no||'')} · ${this.escapeHtml(s.primary_role||'No primary role')}</option>`).join('');
    for(const id of ['individualSalaryStaff','awardStaff']){const select=document.getElementById(id);if(select){select.innerHTML=(id==='individualSalaryStaff'?'<option value="">Select staff member</option>':'')+staffOptions;}}
    const dept=document.getElementById('awardDepartment');if(dept)dept.innerHTML='<option value="">Select department</option>'+departments.map(d=>`<option value="${Number(d.id)}">${this.escapeHtml(d.name)}</option>`).join('');
    const overrides=this.compensationSetup.salaryOverrides||[];
    const overrideBody=document.getElementById('individualSalaryHistoryBody');
    if(overrideBody)overrideBody.innerHTML=overrides.map(o=>`<tr><td>${this.escapeHtml(o.full_name)} · ${this.escapeHtml(o.staff_no||'')}</td><td>${this.formatCurrency(o.gross_salary)}</td><td>${this.escapeHtml(o.effective_from)}</td><td>${this.escapeHtml(o.effective_to||'Current')}</td></tr>`).join('')||'<tr><td colspan="4" class="text-muted">No individual salary exceptions are recorded.</td></tr>';
    const awardBody=document.getElementById('compensationAwardHistoryBody');
    if(awardBody)awardBody.innerHTML=(this.compensationSetup.awardBatches||[]).map(b=>`<tr><td>${this.escapeHtml(b.award_kind)}</td><td>${this.escapeHtml(b.award_name)}</td><td>${Number(b.recipient_count)||0}</td><td>${this.escapeHtml(b.department||'Selected staff')}</td><td>${this.formatCurrency(b.amount_per_month)}</td><td>${this.escapeHtml(b.periods||'')}</td><td>${this.escapeHtml(b.status)}</td><td>${b.status==='active'?`<button class="btn btn-sm btn-outline-danger" type="button" data-cancel-award="${Number(b.id)}">Cancel</button>`:''}</td></tr>`).join('')||'<tr><td colspan="8" class="text-muted">No department awards or deductions have been scheduled.</td></tr>';
    this.syncIndividualSalarySelection();this.setDefaultAwardMonths();
  },

  syncIndividualSalarySelection: function () {
    const select=document.getElementById('individualSalaryStaff'),option=select?.selectedOptions?.[0];if(!option)return;
    document.getElementById('individualSalaryAmount').value=option.dataset.override==='1'?option.dataset.salary:'';
    document.getElementById('individualSalaryRoleHint').textContent=`Primary role: ${option.dataset.role||'Not assigned'}; individual override: ${option.dataset.override==='1'?'active':'none'}`;
  },

  canApprovePayroll: function () {
    const user = window.AuthContext?.getUser?.() || {};
    const roleNames = [user.role_name, ...(Array.isArray(user.roles) ? user.roles.map((role) => role?.name || role) : [])]
      .filter(Boolean).map((name) => String(name).toLowerCase());
    if (roleNames.includes('accountant')) return false;
    return window.AuthContext?.hasPermission?.('staff.payroll.approve') || false;
  },

  canProcessPayroll: function () {
    const user = window.AuthContext?.getUser?.() || {};
    const names = [user.role_name, ...(Array.isArray(user.roles) ? user.roles.map((role) => role?.name || role) : [])]
      .filter(Boolean).map((name) => String(name).toLowerCase());
    if (names.includes('accountant')) return false;
    if (window.AuthContext?.hasPermission?.('staff.payroll.process')) return true;
    return names.some((name) => ['director', 'school administrator', 'system administrator'].includes(name));
  },

  isPayrollReviewer: function () {
    const user = window.AuthContext?.getUser?.() || {};
    const roleNames = [];
    if (user.role_name) roleNames.push(user.role_name);
    if (Array.isArray(user.roles)) roleNames.push(...user.roles.map((role) => role?.name || role));
    if (roleNames.some((role) => String(role).toLowerCase() === "accountant")) return false;
    return this.canApprovePayroll() || roleNames.some((role) =>
      ["director", "school administrator", "system administrator"].includes(String(role).toLowerCase())
    );
  },

  applyPayrollFormMode: function () {
    const reviewer = this.isPayrollReviewer();
    document.querySelectorAll("[data-review-only]").forEach((element) => {
      element.hidden = !reviewer;
    });
    document.querySelectorAll("[data-accountant-hidden]").forEach((element) => {
      element.hidden = !reviewer;
    });
    const title = document.getElementById("processPayrollModalTitle");
    const button = document.getElementById("processPayrollButtonLabel");
    if (title) title.textContent = reviewer ? "Review Staff Payroll" : "Prepare Staff Payroll";
    if (button) button.textContent = reviewer ? "Save Review Draft" : "Prepare Draft";
  },

  applyRoleMode: function () {
    const title = document.querySelector(".payroll-title");
    const subtitle = document.querySelector(".payroll-subtitle");
    const eyebrow = document.querySelector(".payroll-eyebrow");
    const statusFilter = document.getElementById("filterStatus");
    const page = document.querySelector(".director-payroll-page");
    if (page) page.dataset.payrollMode = this.getPayrollMode();
    const paySelectedButton = document.getElementById("paySelectedPayrollBtn");
    if (paySelectedButton) paySelectedButton.hidden = !this.canProcessPayroll();

    const visibleCards = new Set(this.getVisiblePayrollCards());
    document.querySelectorAll("[data-payroll-card]").forEach((card) => {
      card.hidden = !visibleCards.has(card.dataset.payrollCard);
    });

    if (this.canProcessPayroll() && !this.canManagePayroll()) {
      if (eyebrow) eyebrow.innerHTML = '<i class="fas fa-money-check-alt"></i> Executive Payroll Control';
      if (title) title.textContent = "Payroll Payment Queue";
      if (subtitle) subtitle.textContent = "Review approved payrolls, verify source accounts, and release selected staff payments.";
      if (statusFilter && !statusFilter.value) statusFilter.value = "approved";
      return;
    }

    if (this.canApprovePayroll() && !this.canManagePayroll()) {
      if (eyebrow) eyebrow.innerHTML = '<i class="fas fa-shield-alt"></i> Director Payroll Control';
      if (title) title.textContent = "Payroll Approval";
      if (subtitle) subtitle.textContent = "Review pending payrolls and approve them for payment release.";
      if (statusFilter && !statusFilter.value) statusFilter.value = "pending";
      return;
    }

    if (this.canManagePayroll()) {
      if (eyebrow) eyebrow.innerHTML = '<i class="fas fa-users-cog"></i> Payroll Operations';
      if (title) title.textContent = this.canProcessPayroll() ? "Payroll Management & Payment Control" : "Payroll Management";
      if (subtitle) subtitle.textContent = this.canProcessPayroll()
        ? "Prepare payroll, review deductions, approve the payable amount, and release selected staff payments."
        : "Prepare staff payroll and track approval/payment status.";
    }
  },

  getPayrollMode: function () {
    if (this.canManagePayroll()) return "operations";
    if (this.canApprovePayroll()) return "approval";
    if (this.canProcessPayroll()) return "payment";
    return "viewer";
  },

  getVisiblePayrollCards: function () {
    const mode = this.getPayrollMode();
    if (mode === "operations") return ["net", "staff", "children_staff", "children_fees"];
    if (mode === "approval") return ["net", "staff", "children_fees"];
    if (mode === "payment") return ["net", "staff"];
    return ["net"];
  },

  getPayrollColumns: function () {
    const base = {
      select: { label: '<input type="checkbox" aria-label="Select all approved staff" onchange="PayrollManagerController.toggleAllPaymentSelection(this.checked)">', className: "text-center" },
      staff: { label: "Staff" },
      period: { label: "Period" },
      basic: { label: "Basic Salary", className: "text-end" },
      allowances: { label: "Allowances", className: "text-end" },
      statutory: { label: "Statutory Ded.", className: "text-end" },
      children: { label: "Children Fees", className: "text-end" },
      other: { label: "Other Ded.", className: "text-end" },
      net: { label: "Net Pay", className: "text-end" },
      status: { label: "Status", className: "text-center" },
      actions: { label: "Actions", className: "text-center" },
    };

    const byMode = {
      operations: ["select", "staff", "period", "basic", "allowances", "statutory", "children", "other", "net", "status", "actions"],
      approval: ["staff", "period", "basic", "allowances", "statutory", "children", "net", "status", "actions"],
      payment: ["select", "staff", "period", "children", "net", "status", "actions"],
      viewer: ["staff", "period", "net", "status", "actions"],
    };

    return (byMode[this.getPayrollMode()] || byMode.viewer).map((key) => ({
      key,
      ...base[key],
    }));
  },

  renderPayrollHeader: function () {
    const header = document.getElementById("payrollTableHeader");
    if (!header) return;
    header.innerHTML = this.getPayrollColumns()
      .map((column) => `<th class="${column.className || ""}">${column.label}</th>`)
      .join("");
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
    this.renderPayrollHeader();
    const columns = this.getPayrollColumns();

    if (this.filteredPayrolls.length === 0) {
      var emptyRow = document.createElement("tr");
      var emptyCell = document.createElement("td");
      emptyCell.setAttribute("colspan", String(columns.length));
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
      const computed = this.computePayrollRow(p);
      html += `<tr>${columns.map((column) => this.renderPayrollCell(p, computed, column.key)).join("")}</tr>`;
    });

    tbody.innerHTML = html;
    this.renderPagination();
  },

  computePayrollRow: function (p) {
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
    const childrenFees = parseFloat(p.children_fees_deducted) || 0;
    const statutoryDed =
      (parseFloat(p.nssf_deduction) || 0) +
      (parseFloat(p.shif_deduction ?? p.nhif_deduction) || 0) +
      (parseFloat(p.paye_tax) || 0) +
      (parseFloat(p.housing_levy) || 0);
    const otherDed = (parseFloat(p.other_deductions) || 0) - childrenFees;
    return {
      period: `${monthNames[p.payroll_month] || ""} ${p.payroll_year || ""}`.trim(),
      statusBadge: this.getStatusBadge(p.status),
      childrenFees,
      statutoryDed,
      otherDed,
    };
  },

  renderPayrollCell: function (p, computed, column) {
    const cellMap = {
      select: `<td class="text-center"><input type="checkbox" class="payroll-payment-select" value="${Number(p.id)}" ${this.selectedPaymentIds.has(Number(p.id)) ? "checked" : ""} ${p.status !== "approved" || p.payroll_run_status !== "approved" ? "disabled" : ""} onchange="PayrollManagerController.togglePaymentSelection(${Number(p.id)}, this.checked)" aria-label="Select ${this.escapeHtml(p.staff_name || "staff")} for payment"></td>`,
      staff: `
        <td>
          <div style="font-weight: 700; color: var(--payroll-ink, #1a1f2e);">${this.escapeHtml(p.staff_name)}</div>
          <small style="color: #8895a7; font-size: 0.78rem;">${this.escapeHtml(p.position || "")}</small>
        </td>`,
      period: `<td style="font-weight: 600;">${this.escapeHtml(computed.period || "-")}</td>`,
      basic: `<td class="table-amount">${this.formatCurrency(p.basic_salary)}</td>`,
      allowances: `<td class="table-amount" style="color: #1a7a4c;">${this.formatCurrency(p.allowances)}</td>`,
      statutory: `<td class="table-amount negative">${this.formatCurrency(computed.statutoryDed)}</td>`,
      children: `
        <td class="table-amount" style="${computed.childrenFees > 0 ? 'color: #9a7d2e; font-weight: 700;' : 'color: #8895a7;'}">
          ${computed.childrenFees > 0 ? this.formatCurrency(computed.childrenFees) : "-"}
        </td>`,
      other: `<td class="table-amount negative">${computed.otherDed > 0 ? this.formatCurrency(computed.otherDed) : "-"}</td>`,
      net: `<td class="table-amount" style="font-weight: 800; color: #1a7a4c; font-size: 0.92rem;">${this.formatCurrency(p.net_salary)}</td>`,
      status: `<td class="text-center">${computed.statusBadge}</td>`,
      actions: `<td class="text-center">${this.renderPayrollActions(p)}</td>`,
    };
    return cellMap[column] || "";
  },

  togglePaymentSelection: function (payslipId, checked) {
    const id = Number(payslipId);
    if (checked) this.selectedPaymentIds.add(id);
    else this.selectedPaymentIds.delete(id);
    this.updatePaymentSelectionSummary();
  },

  toggleAllPaymentSelection: function (checked) {
    this.filteredPayrolls.filter((p) => p.status === "approved" && p.payroll_run_status === "approved").forEach((p) => {
      const id = Number(p.id);
      if (checked) this.selectedPaymentIds.add(id);
      else this.selectedPaymentIds.delete(id);
    });
    this.renderTable();
    this.updatePaymentSelectionSummary();
  },

  updatePaymentSelectionSummary: function () {
    const selected = this.filteredPayrolls.filter((p) => this.selectedPaymentIds.has(Number(p.id)));
    const total = selected.reduce((sum, p) => sum + (Number(p.net_salary) || 0), 0);
    const label = document.getElementById("payrollPaymentSelectionSummary");
    if (label) label.textContent = selected.length ? `${selected.length} selected · KES ${this.formatCurrency(total)}` : "No staff selected";
    const button = document.getElementById("paySelectedPayrollBtn");
    if (button) button.disabled = !selected.length;
  },

  paySelected: function () {
    const selected = this.filteredPayrolls.filter((p) => this.selectedPaymentIds.has(Number(p.id)) && p.status === "approved" && p.payroll_run_status === "approved");
    if (!selected.length) return this.showError("Select at least one approved staff payment.");
    this.markAsPaid(Number(selected[0].id), selected.map((p) => Number(p.id)));
  },

  renderPayrollActions: function (p) {
    return `
      <button class="table-action-btn" onclick="PayrollManagerController.viewPayslip(${p.id})" title="View Payslip">
        <i class="fas fa-eye"></i>
      </button>
      ${(p.status === "pending" || p.status === "draft") && this.canApprovePayroll() ? `
        <button class="table-action-btn" onclick="PayrollManagerController.reviewPayroll(${p.id})" title="Review payroll inputs">
          <i class="fas fa-pen-to-square"></i>
        </button>
        <button class="table-action-btn approve" onclick="PayrollManagerController.approvePayroll(${p.id})" title="Director Approve">
          <i class="fas fa-user-check"></i>
        </button>
      ` : ""}
      ${p.status === "approved" && p.payroll_run_status === "approved" && this.canProcessPayroll() ? `
        <button class="table-action-btn approve" onclick="PayrollManagerController.markAsPaid(${p.id})" title="Release Payment">
          <i class="fas fa-check-circle"></i>
        </button>
      ` : ""}
      ${p.status === "approved" && p.payroll_run_status && p.payroll_run_status !== "approved" ? `<span class="text-muted small" title="Payment has already started for this payroll run"><i class="fas fa-lock"></i> ${this.escapeHtml(p.payroll_run_status)}</span>` : ""}
    `;
  },

  /**
   * Get status badge HTML
   */
  getStatusBadge: function (status) {
    const badges = {
      draft: '<span class="status-badge pending"><i class="fas fa-pen"></i> Draft</span>',
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
    if (!this.canManagePayroll() && !this.isPayrollReviewer()) {
      this.showError("You do not have permission to prepare or review payroll.");
      return;
    }
    this.resetPayrollForm();
    this.applyPayrollFormMode();
    const modal = new bootstrap.Modal(
      document.getElementById("processPayrollModal")
    );
    modal.show();
  },

  /**
   * Show bulk payroll modal
   */
  showBulkPayrollModal: async function () {
    if (!this.canManagePayroll()) {
      this.showError("You do not have permission to prepare payroll.");
      return;
    }
    const month = new Date().getMonth() + 1;
    const year = new Date().getFullYear();
    document.getElementById("bulkPayrollMonth").value = month;
    document.getElementById("bulkPayrollYear").value = year;
    const modal = new bootstrap.Modal(document.getElementById("bulkPayrollModal"));
    modal.show();
    await this.prepareBulkPayrollRows();
  },

  prepareBulkPayrollRows: async function () {
    if (!this.canManagePayroll()) return;
    const month = document.getElementById("bulkPayrollMonth").value;
    const year = document.getElementById("bulkPayrollYear").value;
    try {
      const response = await API.finance.getBulkPayrollPreview(month, year);
      this.bulkPayrollRows = Array.isArray(response) ? response.map((row) => ({
        ...row,
        selected: row.payroll_eligible === true && !row.already_prepared && (parseFloat(row.basic_salary) || 0) > 0,
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
      checkbox.disabled = !row.payroll_eligible || row.already_prepared;
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
      if (row.already_prepared) {
        const prepared = document.createElement("div");
        prepared.className = "text-warning small fw-bold mt-1";
        prepared.textContent = "Already prepared (" + (row.existing_payslip_status || "draft") + ")";
        staffTd.appendChild(prepared);
      } else if (!row.payroll_eligible) {
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
      row.selected = selected && row.payroll_eligible && !row.already_prepared && row.basic_salary > 0;
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
    if (!this.canManagePayroll()) {
      this.showError("You do not have permission to prepare payroll.");
      return;
    }
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
      const skipped = response && response.skipped_count ? response.skipped_count : 0;
      if (failed > 0) {
        const firstFailure = response.failed?.[0]?.message;
        this.showError("Prepared " + processed + "; skipped " + skipped + "; failed " + failed + "." + (firstFailure ? " " + firstFailure : ""));
        console.warn("Bulk payroll failures:", response.failed || []);
      } else if (skipped > 0 && processed === 0) {
        this.showSuccess("No new payrolls prepared. " + skipped + " staff payrolls were already prepared for this period.");
      } else {
        this.showSuccess("Bulk payroll prepared for director review: " + processed + " staff members." + (skipped ? " " + skipped + " already prepared." : ""));
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
    if (document.getElementById("bonusAllowance")) document.getElementById("bonusAllowance").value = 0;
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
      const configuredAmount = parseFloat(child.fee_deduction_amount);
      const configuredPercentage = parseFloat(child.fee_deduction_percentage);
      const defaultDeduction = child.fee_deduction_enabled
        ? Math.min(
            feeBalance,
            Number.isFinite(configuredAmount) && configuredAmount >= 0
              ? configuredAmount
              : feeBalance * (Number.isFinite(configuredPercentage) ? configuredPercentage : 100) / 100
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
                                <label class="form-label small mb-0">Authorized deduction</label>
                                <small class="d-block text-muted mb-1">$${Number.isFinite(configuredAmount) ? `Fixed KES ${this.formatCurrency(configuredAmount)}` : `${Number.isFinite(configuredPercentage) ? configuredPercentage : 100}% of balance`}</small>
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
    const bonusAllowance =
      parseFloat(document.getElementById("bonusAllowance")?.value) || 0;
    const otherDeductions =
      parseFloat(document.getElementById("otherDeductions").value) || 0;

    const totalAllowances =
      houseAllowance + transportAllowance + otherAllowances + bonusAllowance;
    const grossSalary = basicSalary + totalAllowances;

    // Calculate statutory deductions
    const nssf = this.calculateNSSF(grossSalary);
    const shif = this.calculateSHIF(grossSalary);
    const housingLevy = this.calculateHousingLevy(grossSalary);
    const paye = this.calculatePAYE(grossSalary - nssf - shif - housingLevy);
    const employerNSSF = this.calculateNSSF(grossSalary);
    const employerHousingLevy = housingLevy;

    // Children fees
    const childrenFees = this.childrenDeductions.reduce(
      (sum, d) => sum + (d.enabled ? d.amount : 0),
      0
    );

    const totalDeductions =
      nssf + shif + paye + housingLevy + childrenFees + otherDeductions;
    const netSalary = grossSalary - totalDeductions;

    // Update display
    document.getElementById("calcGrossSalary").textContent =
      this.formatCurrency(grossSalary);
    document.getElementById("calcNSSF").textContent = this.formatCurrency(nssf);
    document.getElementById("calcSHIF").textContent = this.formatCurrency(shif);
    document.getElementById("calcPAYE").textContent = this.formatCurrency(paye);
    document.getElementById("calcHousingLevy").textContent =
      this.formatCurrency(housingLevy);
    document.getElementById("calcEmployerNSSF").textContent =
      this.formatCurrency(employerNSSF);
    document.getElementById("calcEmployerHousingLevy").textContent =
      this.formatCurrency(employerHousingLevy);
    document.getElementById("calcChildrenFees").textContent =
      this.formatCurrency(childrenFees);
    document.getElementById("calcTotalDeductions").textContent =
      this.formatCurrency(totalDeductions);
    document.getElementById("calcNetPay").textContent =
      "KES " + this.formatCurrency(netSalary);
  },

  /** Calculate NSSF from the active effective-dated rule snapshot. */
  calculateNSSF: function (gross) {
    const rule = this.statutoryRules['NSSF:employee_employer_contribution'] || {};
    const rate = Number(rule.employee_rate || 0) / 100;
    const upper = Number(rule.upper_earnings_limit || 0);
    return upper > 0 ? Math.min(Math.max(0, Number(gross) || 0), upper) * rate : 0;
  },

  /** Calculate SHIF from the active effective-dated rule snapshot. */
  calculateSHIF: function (gross) {
    const rule = this.statutoryRules['SHIF:employee_contribution'] || {};
    return Math.max(0, Number(gross) || 0) * (Number(rule.employee_rate || 0) / 100);
  },

  calculateHousingLevy: function (gross) {
    const rule = this.statutoryRules['Housing Levy:employee_employer_contribution'] || {};
    return Math.max(0, Number(gross) || 0) * (Number(rule.employee_rate || 0) / 100);
  },

  /** Calculate PAYE from the active effective-dated rule snapshot. */
  calculatePAYE: function (taxableIncome) {
    const rule = this.statutoryRules['KRA:paye_bands'] || {};
    const bands = Array.isArray(rule.bands) ? rule.bands : [];
    const personalRelief = Number(rule.personal_relief || 0);
    let tax = 0;
    let remaining = Math.max(0, Number(taxableIncome) || 0);
    let prevLimit = 0;

    for (const band of bands) {
      const limit = band.up_to === null || band.up_to === undefined ? Infinity : Number(band.up_to);
      const taxable = Math.min(remaining, Math.max(0, limit - prevLimit));
      tax += taxable * (Number(band.rate || 0) / 100);
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
    if (!this.canManagePayroll() && !this.isPayrollReviewer()) {
      this.showError("You do not have permission to prepare or review payroll.");
      return;
    }
    if (!this.selectedStaff) {
      this.showError("Please select a staff member");
      return;
    }

    const btn = document.getElementById("processPayrollBtn");
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';

    try {
      const reviewer = this.isPayrollReviewer();
      const basicSalary = parseFloat(this.selectedStaff.basic_salary) || 0;
      const allowances = reviewer ? {
        house: parseFloat(document.getElementById("houseAllowance").value) || 0,
        transport: parseFloat(document.getElementById("transportAllowance").value) || 0,
        other: parseFloat(document.getElementById("otherAllowances").value) || 0,
        bonus: parseFloat(document.getElementById("bonusAllowance")?.value) || 0,
      } : {};
      const otherDeductions = reviewer
        ? parseFloat(document.getElementById("otherDeductions").value) || 0
        : 0;

      // Prepare children deductions
      const childrenDeductions = reviewer ? this.childrenDeductions
        .filter((d) => d.enabled && d.amount > 0)
        .map((d) => ({
          staff_child_id: d.staff_child_id,
          student_id: d.student_id,
          amount: d.amount,
          fee_invoice_id: d.fee_invoice_id,
          term_id: d.term_id,
          gross_fee_amount: d.gross_fee_amount,
        })) : [];

      const data = {
        staff_id: this.selectedStaff.id,
        payroll_month: document.getElementById("payrollMonth").value,
        payroll_year: document.getElementById("payrollYear").value,
        basic_salary: basicSalary,
        allowances: allowances,
        other_deductions: otherDeductions,
        children_deductions: childrenDeductions,
        children_deductions_explicit: reviewer,
        preparation_only: !reviewer,
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

  reviewPayroll: async function (payrollId) {
    if (!this.isPayrollReviewer()) {
      this.showError("Only the director or school administrator can review payroll inputs.");
      return;
    }
    try {
      const response = await API.finance.getDetailedPayslip(payrollId);
      if (!response || !response.id) throw new Error("Payroll draft not found");

      this.resetPayrollForm();
      this.applyPayrollFormMode();
      document.getElementById("payrollMonth").value = response.payroll_month;
      document.getElementById("payrollYear").value = response.payroll_year;
      document.getElementById("payrollStaffSelect").value = response.staff_id;

      const modal = new bootstrap.Modal(document.getElementById("processPayrollModal"));
      modal.show();
      await this.onStaffSelected();

      const allowancesTotal = parseFloat(response.allowances_total) || 0;
      document.getElementById("houseAllowance").value = 0;
      document.getElementById("transportAllowance").value = 0;
      document.getElementById("otherAllowances").value = allowancesTotal;
      document.getElementById("otherDeductions").value = parseFloat(response.other_deductions_total) || 0;
      this.childrenDeductions = Array.isArray(response.children_deductions)
        ? response.children_deductions.map((child) => ({ ...child, enabled: true, amount: parseFloat(child.deducted_amount || child.amount || 0) }))
        : [];
      this.displayChildrenSection();
      this.showSalaryCalculation();
    } catch (error) {
      console.error("Error loading payroll for review:", error);
      this.showError(error.message || "Failed to load payroll draft for review");
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
    const period = `${monthNames[Number(data.payroll_month)] || "-"} ${data.payroll_year || "-"}`;

    const grossSalary = parseFloat(data.gross_salary) || 0;
    const housingLevy = parseFloat(data.housing_levy) || 0;
    const employeeNssf = parseFloat(data.nssf_deduction ?? data.nssf_contribution) || 0;
    const employeeShif = parseFloat(data.shif_deduction ?? data.shif_contribution ?? data.nhif_contribution) || 0;
    const paye = parseFloat(data.paye_tax) || 0;
    const employerNssf = parseFloat(data.employer_nssf_contribution) || 0;
    const employerHousing = parseFloat(data.employer_housing_levy) || 0;
    const childrenFees = parseFloat(data.total_children_fees) || 0;
    const otherDeductions = parseFloat(data.other_deductions ?? data.other_deductions_total) || 0;
    const totalDeductions = parseFloat(data.total_deductions ?? data.employee_deductions_total) ||
      employeeNssf + employeeShif + paye + housingLevy + childrenFees + otherDeductions;
    const school = data.school_profile || {};
    const schoolName = school.school_name || "School profile not configured";
    const schoolAddress = [school.address, school.city].filter(Boolean).join(", ");
    const schoolLocation = [schoolAddress, school.postal_code ? `Postal code ${school.postal_code}` : null, school.country]
      .filter(Boolean).join(" · ") || "School address not configured";
    const logoPath = school.logo_url || "uploads/school_assets/official_school_logo.png";
    const logoUrl = /^https?:\/\//i.test(logoPath) ? logoPath : `${window.APP_BASE || ""}/${String(logoPath).replace(/^\//, "")}`;

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
    const paymentMode = paymentModeLabels[data.payment_mode || data.payment_method] || (data.payment_mode || data.payment_method || "Not Recorded");
    const paidDateValue = data.paid_at || data.payment_date;
    const datePaid = paidDateValue
      ? new Date(paidDateValue).toLocaleString("en-KE")
      : "Not Paid";
    const status = data.status || data.payslip_status || data.payment_status || "Unknown";

    const html = `
            <div class="payslip-container" id="payslipPrintArea">
                <div class="text-center mb-4">
                    <h5 class="mt-3">EMPLOYEE PAYSLIP</h5>
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
                            <tr><td><strong>SHIF No:</strong></td><td>${this.escapeHtml(
                              data.shif_no || data.nhif_no || "-"
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
                              status
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
                              data.allowances ?? data.allowances_total ?? 0
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
                                  employeeNssf
                                )}</td>
                            </tr>
                            <tr>
                                <td>SHIF</td>
                                <td class="text-end">${this.formatCurrency(
                                  employeeShif
                                )}</td>
                            </tr>
                            <tr>
                                <td>PAYE Tax</td>
                                <td class="text-end">${this.formatCurrency(
                                  paye
                                )}</td>
                            </tr>
                            <tr>
                                <td>Employee Housing Levy (1.5%)</td>
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
                                  otherDeductions
                                )}</td>
                            </tr>
                            <tr class="table-danger fw-bold">
                                <td>Total Deductions</td>
                                <td class="text-end">${this.formatCurrency(
                                  totalDeductions
                                )}</td>
                            </tr>
                        </table>
                        <h6 class="text-secondary mt-3"><i class="fas fa-building me-1"></i>EMPLOYER CONTRIBUTIONS</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Employer NSSF (school cost)</td>
                                <td class="text-end">${this.formatCurrency(employerNssf)}</td>
                            </tr>
                            <tr>
                                <td>Employer Housing Levy (school cost)</td>
                                <td class="text-end">${this.formatCurrency(employerHousing)}</td>
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
    if (!this.canApprovePayroll()) {
      this.showError("You do not have permission to approve payroll.");
      return;
    }
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

  markAsPaid: function (payrollId, selectedIds = []) {
    if (!this.canProcessPayroll()) {
      this.showError("You do not have permission to release payroll payments.");
      return;
    }
    // A payroll run becomes immutable as soon as disbursement starts. Keep a
    // defensive client-side check here because an old table row, browser tab,
    // or direct onclick can otherwise open the payment modal after the run
    // has moved to processing/completed/failed.
    const payroll = (this.payrolls || []).find((p) => Number(p.id) === Number(payrollId))
      || (this.filteredPayrolls || []).find((p) => Number(p.id) === Number(payrollId));
    const runStatus = payroll && String(payroll.payroll_run_status || '').toLowerCase();
    if (runStatus && runStatus !== "approved") {
      this.showError(`Payroll payment is locked because disbursement has already started (${runStatus}). Refresh the page for the latest status.`);
      return;
    }
    var self = this;
    self.showConfirm(
      "Mark this payroll as paid? This will also record fee payments for any children deductions.",
      function () {
        self.showPaymentModeModal(function (mode, reference, sourceId) {
          self._executeMarkAsPaid(payrollId, mode, reference, sourceId, selectedIds);
        }, payrollId);
      }
    );
  },

  _executeMarkAsPaid: async function (payrollId, paymentMode, paymentRef, sourceId = null, selectedIds = []) {
    try {
      const response = await API.finance.markPayrollPaid(payrollId, paymentRef, paymentMode, sourceId, selectedIds);

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
  printPayslip: async function () {
    if (!this.currentPayslipId) {
      this.showError("Payslip is not ready to print");
      return;
    }

    try {
      const data = await API.finance.getDetailedPayslip(this.currentPayslipId);
      if (!data || !data.id) throw new Error("Payslip details are unavailable");
      const number = (value) => Number.parseFloat(value) || 0;
      const monthNames = ["", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
      const period = `${monthNames[Number(data.payroll_month)] || "-"} ${data.payroll_year || "-"}`;
      const children = Array.isArray(data.children_deductions) ? data.children_deductions : [];
      const childrenList = children.map((child) => ({
        student_name: child.student_name || "Child",
        class_name: child.class_name || "",
        deducted_amount: number(child.deducted_amount || child.amount),
      }));
      const other = number(data.other_deductions ?? data.other_deductions_total);
      const mode = data.payment_mode || data.payment_method;
      const modeLabels = { bank: "Bank Transfer", cash: "Cash", mpesa: "M-Pesa", airtel_money: "Airtel Money" };
      const paymentMethod = mode ? (modeLabels[mode] || mode) : "Not Recorded";
      const paidDateValue = data.paid_at || data.payment_date;
      const datePaid = paidDateValue
        ? new Date(paidDateValue).toLocaleString("en-KE")
        : "Not Paid";
      const status = String(data.status || data.payslip_status || data.payment_status || "Unknown");
      const school = data.school_profile || {};

      // Payload mirrors every field rendered by renderPayslip(). The backend
      // renders the payslip's complete standalone template (including its
      // own header/footer), not the generic report shell.
      await window.PrintManager.printDedicatedPayslip({
        title: "Staff Payslip",
        subtitle: period,
        employeeName: `${data.first_name || ""} ${data.last_name || ""}`.trim(),
        staffNo: data.staff_no || data.staff_number || "-",
        department: data.department || "-",
        designation: data.position || data.designation || "-",
        kraPin: data.kra_pin || "-",
        nssfNo: data.nssf_no || "-",
        shifNo: data.shif_no || data.nhif_no || "-",
        period,
        basicSalary: number(data.basic_salary),
        allowances: [{ name: "Allowances", amount: number(data.allowances ?? data.allowances_total) }],
        statutory: {
          paye: number(data.paye_tax),
          nssf: number(data.nssf_deduction ?? data.nssf_contribution),
          nhif_shif: number(data.shif_deduction ?? data.shif_contribution ?? data.nhif_contribution),
          housing_levy: number(data.housing_levy),
        },
        childrenDeductions: childrenList,
        otherDeductions: other,
        deductions: [],
        grossPay: number(data.gross_salary),
        totalDeductions: number(data.total_deductions ?? data.employee_deductions_total),
        netPay: number(data.net_salary),
        employerNssf: number(data.employer_nssf_contribution),
        employerHousing: number(data.employer_housing_levy),
        bankName: data.bank_name || "-",
        bankAccountNumber: data.bank_account_number || data.bank_account || "-",
        bankAccount: [data.bank_name, data.bank_account_number || data.bank_account].filter(Boolean).join(" / ") || "-",
        paymentMethod,
        paymentReference: data.payment_reference || "-",
        datePaid,
        status,
        generatedOn: new Date().toLocaleDateString("en-KE"),
        schoolName: school.school_name,
        schoolLogo: school.logo_url,
        schoolMotto: school.motto,
        filename: `staff_payslip_${data.payroll_year || new Date().getFullYear()}_${data.payroll_month || ""}`,
        reportCode: `PAYSLIP-${data.id}`,
        signatureSection: [
          { label: "Accounts Officer", dateLine: true },
          { label: "Employee Acknowledgement", dateLine: true },
        ],
      });
    } catch (error) {
      console.error("Error printing payslip:", error);
      this.showError(error.message || "Unable to generate the payslip");
    }
  },

  /**
   * Download payslip as PDF (uses print dialog with auto-trigger)
   */
  downloadPayslip: async function () {
    return this.printPayslip();
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

    const exportRows = rows.map((payroll) => ({
      staff_name:
        payroll.staff_name
        || `${payroll.first_name || ""} ${payroll.last_name || ""}`.trim(),
      period: `${this.getMonthName(payroll.payroll_month)} ${payroll.payroll_year}`,
      basic_salary: Number(payroll.basic_salary || 0),
      allowances: Number(payroll.allowances || 0),
      statutory_deductions: Number(payroll.statutory_deductions || 0),
      children_fee_deductions: Number(payroll.children_fee_deductions || 0),
      other_deductions: Number(payroll.other_deductions || 0),
      net_salary: Number(payroll.net_salary || 0),
      status: payroll.status || "",
    }));

    window.PrintManager.exportToCSV({
      columns: [
        { key: "staff_name", label: "Staff" },
        { key: "period", label: "Period" },
        { key: "basic_salary", label: "Basic Salary" },
        { key: "allowances", label: "Allowances" },
        { key: "statutory_deductions", label: "Statutory Deductions" },
        { key: "children_fee_deductions", label: "Children Fees" },
        { key: "other_deductions", label: "Other Deductions" },
        { key: "net_salary", label: "Net Pay" },
        { key: "status", label: "Status" },
      ],
      rows: exportRows,
      filename: `payroll_report_${new Date().toISOString().slice(0, 10)}`,
    });
  },

  /**
   * Print the payroll table as a PDF via the browser print dialog
   */
  printPayrollReport: async function () {
    const rows = this.filteredPayrolls || [];

    if (!rows.length) {
      this.showError("No payroll records to print");
      return;
    }

    const reportRows = rows.map((payroll) => {
      const nssf = Number(payroll.nssf_deduction ?? payroll.nssf_contribution ?? 0);
      const shif = Number(payroll.shif_deduction ?? payroll.shif_contribution ?? payroll.nhif_contribution ?? 0);
      const paye = Number(payroll.paye_deduction ?? payroll.paye_tax ?? 0);
      const housing = Number(payroll.housing_levy ?? 0);
      const children = Number(payroll.children_fees_deducted ?? payroll.child_fees_deduction ?? payroll.children_fee_deductions ?? 0);
      const other = Number(payroll.other_deductions ?? payroll.other_deductions_total ?? 0);
      const employeeDeductions = nssf + shif + paye + housing + children + other;
      const employerContributions = Number(payroll.employer_nssf_contribution ?? 0)
        + Number(payroll.employer_housing_levy ?? 0);

      return {
        staff_name:
          payroll.staff_name
          || `${payroll.first_name || ""} ${payroll.last_name || ""}`.trim(),
        staff_no: payroll.staff_no || "—",
        position: payroll.position || "—",
        department: payroll.department || "—",
        period: `${this.getMonthName(payroll.payroll_month)} ${payroll.payroll_year}`,
        basic_salary: Number(payroll.basic_salary || 0),
        allowances: Number(payroll.allowances ?? payroll.allowances_total ?? 0),
        gross_salary: Number(payroll.gross_salary || 0),
        nssf,
        shif,
        paye,
        housing,
        children,
        other,
        employee_deductions: employeeDeductions,
        employer_contributions: employerContributions,
        net_salary: Number(payroll.net_salary || 0),
        status: payroll.status || payroll.payslip_status || "—",
      };
    });

    const currency = (value) =>
      `KSh ${Number(value || 0).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`;
    const tableAmount = (value) =>
      Number(value || 0).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });

    await window.PrintManager.printTable({
      title: "Payroll Report",
      description: "Consolidated staff payroll report. All monetary values are in KES.",
      documentClass: "payroll-report",
      columns: [
        { key: "staff_name", label: "Staff", width: "11%" },
        { key: "staff_no", label: "Staff No.", width: "5%" },
        { key: "position", label: "Position", width: "6%" },
        { key: "department", label: "Department", width: "6%" },
        { key: "period", label: "Period", width: "5%" },
        {
          key: "basic_salary",
          label: "Basic Salary",
          type: "currency",
          width: "6%",
          formatter: tableAmount,
        },
        {
          key: "allowances",
          label: "Allowances",
          type: "currency",
          width: "5%",
          formatter: tableAmount,
        },
        {
          key: "gross_salary",
          label: "Gross Salary",
          type: "currency",
          width: "6%",
          formatter: tableAmount,
        },
        { key: "nssf", label: "NSSF", type: "currency", width: "4%", formatter: tableAmount },
        { key: "shif", label: "SHIF", type: "currency", width: "4%", formatter: tableAmount },
        { key: "paye", label: "PAYE", type: "currency", width: "4%", formatter: tableAmount },
        { key: "housing", label: "Employee Housing Levy", type: "currency", width: "6%", formatter: tableAmount },
        { key: "children", label: "Children Fees", type: "currency", width: "4%", formatter: tableAmount },
        { key: "other", label: "Other Deductions", type: "currency", width: "4%", formatter: tableAmount },
        { key: "employee_deductions", label: "Total Employee Deductions", type: "currency", width: "8%", formatter: tableAmount },
        { key: "employer_contributions", label: "Employer Contributions", type: "currency", width: "7%", formatter: tableAmount },
        {
          key: "net_salary",
          label: "Net Pay",
          type: "currency",
          width: "5%",
          formatter: tableAmount,
        },
        { key: "status", label: "Status", width: "4%" },
      ],
      rows: reportRows,
      orientation: "landscape",
      filename: `payroll_report_${new Date().toISOString().slice(0, 10)}`,
      reportCode: `PAYROLL-${new Date().toISOString().slice(0, 10).replace(/-/g, "")}`,
      summary: {
        "Staff Records": reportRows.length,
        "Total Gross Salary": currency(
          reportRows.reduce((sum, row) => sum + row.gross_salary, 0)
        ),
        "Total Employee Deductions": currency(
          reportRows.reduce((sum, row) => sum + row.employee_deductions, 0)
        ),
        "Total Employer Contributions": currency(
          reportRows.reduce((sum, row) => sum + row.employer_contributions, 0)
        ),
        "Total Net Pay": currency(
          reportRows.reduce((sum, row) => sum + row.net_salary, 0)
        ),
      },
      signatureSection: [
        { label: "Payroll Officer", dateLine: true },
        { label: "Accountant", dateLine: true },
        { label: "Headteacher", dateLine: true },
      ],
    });
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

  showSuccess: async function (message) {
    if (typeof showNotification === "function") {
      showNotification(message, "success");
    } else {
      await window.infoDialog('Notice', "✅ " + message);
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
  showPaymentModeModal: function (onConfirm, payrollId = null) {
    var modal = document.getElementById("payrollPaymentModeModal");
    var refInput = document.getElementById("paymentReferenceInput");
    var okBtn = document.getElementById("payrollPaymentConfirmOk");

    // Reset
    refInput.value = "";
    document.getElementById("modeBank").checked = true;
    const sourceSelect = document.getElementById("payrollPaymentSourceFinancialAccount");
    if (sourceSelect && payrollId) sourceSelect.dataset.payrollId = String(payrollId);

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
      var sourceId = sourceSelect ? Number(sourceSelect.value || 0) || null : null;
      bsModal.hide();
      if (typeof onConfirm === "function") onConfirm(mode, reference, sourceId);
    });
  },

  showError: async function (message) {
    if (typeof showNotification === "function") {
      showNotification(message, "error");
    } else {
      await window.infoDialog('Notice', "❌ " + message);
    }
  },
};

// Initialize on DOM ready
document.addEventListener("DOMContentLoaded", () => {
  PayrollManagerController.init();
});

window.PayrollManagerController = PayrollManagerController;
