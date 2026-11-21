/**
 * Statutory Remittances Controller
 * Tracks PAYE (KRA), SHIF, NSSF, and Housing Levy remittances.
 */
const statutoryRemittancesController = {
  remittances: [],
  breakdown: [],
  ruleRows: [],

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this.populateYearFilter();
    this.populateMonthSelects();
    this.populateRegisterMonth();
    this.bind();
    await Promise.all([this.load(), this.loadCompliance()]);
  },

  bind() {
    document.getElementById('srRefreshBtn')?.addEventListener('click', () => this.load());
    document.getElementById('srAgencyFilter')?.addEventListener('change', () => this.render());
    document.getElementById('srYearFilter')?.addEventListener('change', () => Promise.all([this.load(), this.loadCompliance()]));
    document.getElementById('srStatusFilter')?.addEventListener('change', () => this.render());
    document.getElementById('srNewBtn')?.addEventListener('click', () => this.openNew());
    document.getElementById('srSaveBtn')?.addEventListener('click', () => this.save());
    document.getElementById('srAgency')?.addEventListener('change', () => this.fetchDeducted());
    document.getElementById('srMonth')?.addEventListener('change', () => this.fetchDeducted());
    document.getElementById('srYear')?.addEventListener('change', () => this.fetchDeducted());
    document.getElementById('srSubmitPaymentBtn')?.addEventListener('click', () => this.submitPayment());
    document.getElementById('srGenerateRegisterBtn')?.addEventListener('click', () => this.generateRegister());
    document.getElementById('srCertificateBtn')?.addEventListener('click', () => this.openCertificate());
    document.getElementById('srSaveCertificateBtn')?.addEventListener('click', () => this.saveCertificate());
    document.getElementById('srAddRuleBtn')?.addEventListener('click', () => this.openRule());
    document.getElementById('srSaveRuleBtn')?.addEventListener('click', () => this.saveRule());
    document.getElementById('srAddBandBtn')?.addEventListener('click', () => this.addBandRow());
    document.getElementById('srRuleCode')?.addEventListener('change', () => this.toggleBandEditor());
  },

  populateYearFilter() {
    const sel = document.getElementById('srYearFilter');
    const yr = new Date().getFullYear();
    for (let y = yr; y >= yr - 4; y--) sel.add(new Option(y, y, y === yr, y === yr));
    document.getElementById('srYear').value = yr;
  },

  populateMonthSelects() {
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const sel = document.getElementById('srMonth');
    const cm = new Date().getMonth() + 1;
    months.forEach((m, i) => sel.add(new Option(m, i + 1, i + 1 === cm, i + 1 === cm)));
  },

  populateRegisterMonth() {
    const sel = document.getElementById('srRegisterMonth');
    if (!sel) return;
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const current = new Date().getMonth() + 1;
    months.forEach((m, i) => sel.add(new Option(m, i + 1, i + 1 === current, i + 1 === current)));
  },

  async loadCompliance() {
    try {
      const year = document.getElementById('srYearFilter')?.value || new Date().getFullYear();
      const response = await callAPI('/staff/statutory-compliance?year=' + encodeURIComponent(year), 'GET');
      const data = response?.data || response || {};
      this.renderRegisters(Array.isArray(data.registers) ? data.registers : []);
      this.renderCertificates(Array.isArray(data.certificates) ? data.certificates : []);
      this.ruleRows = Array.isArray(data.rules) ? data.rules : [];
      this.renderRules(this.ruleRows);
    } catch (e) {
      ['srRegisterBody','srCertificateBody','srRuleBody'].forEach(id => {
        const body = document.getElementById(id);
        if (body) body.innerHTML = '<tr><td colspan=\"8\" class=\"text-center text-danger\">Unable to load compliance records.</td></tr>';
      });
    }
  },

  renderRegisters(rows) {
    const body = document.getElementById('srRegisterBody');
    if (!body) return;
    if (!rows.length) { body.innerHTML = '<tr><td colspan=\"7\" class=\"text-center text-muted\">No payroll registers generated.</td></tr>'; return; }
    const fmt = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
    body.innerHTML = rows.map(r => '<tr><td>' + this.esc(r.period_month) + '/' + this.esc(r.period_year) + '</td><td>' +
      this.esc(r.employee_count) + '</td><td class=\"text-end\">' + fmt(r.gross_total) + '</td><td class=\"text-end\">' +
      fmt(r.employee_deductions_total) + '</td><td class=\"text-end\">' + fmt(r.employer_contributions_total) +
      '</td><td>' + this.esc(r.status) + '</td><td>' + this.esc(r.retention_until || '—') + '</td></tr>').join('');
  },

  renderCertificates(rows) {
    const body = document.getElementById('srCertificateBody');
    if (!body) return;
    if (!rows.length) { body.innerHTML = '<tr><td colspan=\"6\" class=\"text-center text-muted\">No certificates of service recorded.</td></tr>'; return; }
    body.innerHTML = rows.map(r => '<tr><td>' + this.esc(r.certificate_number) + '</td><td>' +
      this.esc(r.staff_name || r.staff_no) + '</td><td>' + this.esc(r.employment_start_date) + ' – ' +
      this.esc(r.employment_end_date) + '</td><td>' + this.esc(r.issued_date) + '</td><td>' +
      this.esc(r.status) + '</td><td>' + this.esc(r.retention_until || '—') + '</td></tr>').join('');
  },

  renderRules(rows) {
    const body = document.getElementById('srRuleBody');
    if (!body) return;
    if (!rows.length) { body.innerHTML = '<tr><td colspan=\"8\" class=\"text-center text-muted\">No statutory settings configured.</td></tr>'; return; }
    const has = value => value !== undefined && value !== null && value !== '';
    const pct = value => !has(value) ? '—' : this.esc(Number(value).toLocaleString('en-KE', {maximumFractionDigits: 4}) + '%');
    const money = value => !has(value) ? '—' : 'KES ' + this.esc(Number(value).toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    body.innerHTML = rows.map(r => {
      const rules = r.rules || {};
      const application = [];
      if (Array.isArray(rules.bands) && rules.bands.length) {
        application.push('<div class=\"small fw-semibold mb-1\">Progressive PAYE bands</div>');
        application.push('<div class=\"small\">' + rules.bands.map(b => {
          const end = has(b.up_to) ? 'Up to ' + money(b.up_to) : 'Above the previous band';
          return '<span class=\"d-block\">' + end + ': ' + pct(b.rate) + '</span>';
        }).join('') + '</div>');
        if (has(rules.personal_relief)) application.push('<div class=\"small mt-1\">Monthly personal relief: <strong>' + money(rules.personal_relief) + '</strong></div>');
      } else if (has(rules.lower_earnings_limit) || has(rules.upper_earnings_limit)) {
        application.push('<div class=\"small\">Applicable earnings range: <strong>' + money(rules.lower_earnings_limit) + ' – ' + money(rules.upper_earnings_limit) + '</strong></div>');
      } else {
        application.push('<div class=\"small\">Applied to eligible gross pay.</div>');
      }
      if (has(rules.cap_amount)) application.push('<div class=\"small mt-1\">Maximum contribution: <strong>' + money(rules.cap_amount) + '</strong></div>');
      const deadlineBasis = String(rules.deadline_basis || '').replaceAll('_', ' ');
      const deadline = rules.deadline_day ? 'By day ' + this.esc(rules.deadline_day) + ' (' + this.esc(deadlineBasis) + ')' : '—';
      const label = r.agency === 'KRA' ? 'KRA — PAYE' : this.esc(r.agency);
      const source = r.source_name || 'Configured statutory setting';
      const index = rows.indexOf(r);
      return '<tr><td class=\"fw-semibold\">' + label + '</td><td>' + pct(rules.employee_rate) + '</td><td>' + pct(rules.employer_rate) + '</td><td>' + application.join('') +
        '</td><td>' + deadline + '</td><td>' + this.esc(r.effective_from || '—') + '</td><td class=\"small\">' + this.esc(source) +
        '</td><td class=\"text-end\"><button type=\"button\" class=\"btn btn-sm btn-outline-primary\" data-kw-onclick=\"statutoryRemittancesController.updateRule(' + index + ')\"><i class=\"bi bi-pencil-square me-1\"></i>Update</button></td></tr>';
    }).join('');
  },

  async generateRegister() {
    const month = Number(document.getElementById('srRegisterMonth')?.value || new Date().getMonth() + 1);
    const year = Number(document.getElementById('srYearFilter')?.value || new Date().getFullYear());
    const button = document.getElementById('srGenerateRegisterBtn');
    if (button) button.disabled = true;
    try {
      await callAPI('/staff/statutory-compliance/register', 'POST', { month, year });
      this.notify('Monthly statutory payroll register generated.', 'success');
      await this.loadCompliance();
    } catch (e) {
      this.notify(e.message || 'Register generation failed.', 'danger');
    } finally {
      if (button) button.disabled = false;
    }
  },

  async openCertificate() {
    const select = document.getElementById('srCertificateStaff');
    select.innerHTML = '<option value="">Loading staff…</option>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('srCertificateModal')).show();
    try {
      const response = await callAPI('/staff?status=inactive', 'GET');
      const data = response?.data || response || {};
      const staff = Array.isArray(data.staff) ? data.staff : (Array.isArray(data) ? data : []);
      select.innerHTML = staff.length
        ? '<option value="">Select staff member</option>' + staff.map(s => '<option value="' + Number(s.id) + '">' +
          this.esc(s.staff_no || '') + ' · ' + this.esc(s.staff_name || ((s.first_name || '') + ' ' + (s.last_name || '')).trim()) + '</option>').join('')
        : '<option value="">No staff records available</option>';
    } catch (e) {
      select.innerHTML = '<option value="">Unable to load staff</option>';
    }
  },

  async saveCertificate() {
    const payload = {
      staff_id: Number(document.getElementById('srCertificateStaff').value),
      employment_start_date: document.getElementById('srCertificateStart').value,
      employment_end_date: document.getElementById('srCertificateEnd').value,
      designation: document.getElementById('srCertificateDesignation').value.trim() || null,
      department: document.getElementById('srCertificateDepartment').value.trim() || null,
      reason_for_leaving: document.getElementById('srCertificateReason').value.trim() || null,
    };
    if (!payload.staff_id || !payload.employment_start_date || !payload.employment_end_date) {
      return this.notify('Select staff and enter both employment dates.', 'warning');
    }
    const button = document.getElementById('srSaveCertificateBtn');
    button.disabled = true;
    try {
      await callAPI('/staff/statutory-compliance/certificate', 'POST', payload);
      bootstrap.Modal.getInstance(document.getElementById('srCertificateModal'))?.hide();
      this.notify('Certificate of service recorded.', 'success');
      await this.loadCompliance();
    } catch (e) {
      this.notify(e.message || 'Certificate could not be recorded.', 'danger');
    } finally {
      button.disabled = false;
    }
  },

  openRule(existing = null) {
    const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
    document.getElementById('srRuleVersion').value = existing ? ((existing.version || 'updated') + '-update') : '';
    document.getElementById('srRuleFrom').value = existing?.effective_from || tomorrow;
    document.getElementById('srRuleAgency').value = existing?.agency || 'KRA';
    document.getElementById('srRuleCode').value = existing?.rule_code || 'employee_contribution';
    ['srRuleEmployeeRate','srRuleEmployerRate','srRuleLowerLimit','srRuleUpperLimit','srRuleCapAmount','srRuleRelief','srRuleDeadlineDay'].forEach(id => {
      document.getElementById(id).value = '';
    });
    const existingRules = existing?.rules || {};
    document.getElementById('srRuleEmployeeRate').value = existingRules.employee_rate ?? '';
    document.getElementById('srRuleEmployerRate').value = existingRules.employer_rate ?? '';
    document.getElementById('srRuleLowerLimit').value = existingRules.lower_earnings_limit ?? '';
    document.getElementById('srRuleUpperLimit').value = existingRules.upper_earnings_limit ?? '';
    document.getElementById('srRuleCapAmount').value = existingRules.cap_amount ?? '';
    document.getElementById('srRuleRelief').value = existingRules.personal_relief ?? '';
    document.getElementById('srRuleDeadlineDay').value = existingRules.deadline_day ?? '';
    document.getElementById('srRuleDeadlineBasis').value = existingRules.deadline_basis || 'working_day_of_following_month';
    this.renderBandEditor(existingRules.bands || []);
    this.toggleBandEditor();
    document.getElementById('srRuleSourceName').value = existing?.source_name || '';
    document.getElementById('srRuleSourceUrl').value = existing?.source_url || '';
    document.getElementById('srRuleTitle').textContent = existing ? 'Create updated statutory setting' : 'Add statutory setting';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('srRuleModal')).show();
  },

  updateRule(index) {
    const rule = this.ruleRows[index];
    if (rule) this.openRule(rule);
  },

  toggleBandEditor() {
    const panel = document.getElementById('srRuleBandsPanel');
    const isPaye = document.getElementById('srRuleCode')?.value === 'paye_bands';
    if (panel) panel.style.display = isPaye ? '' : 'none';
    if (isPaye && !document.querySelector('#srRuleBandsEditor .sr-band-row')) this.addBandRow();
  },

  addBandRow() {
    const editor = document.getElementById('srRuleBandsEditor');
    if (!editor) return;
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-end mb-2 sr-band-row';
    row.innerHTML = '<div class="col-md-5"><label class="form-label small mb-1">Upper income limit</label><input type="number" min="0" step="0.01" class="form-control sr-band-upper" placeholder="Leave blank for final band"></div>' +
      '<div class="col-md-5"><label class="form-label small mb-1">Tax rate (%)</label><input type="number" min="0" step="0.0001" class="form-control sr-band-rate" placeholder="e.g. 25"></div>' +
      '<div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 sr-remove-band" title="Remove band"><i class="bi bi-trash"></i></button></div>';
    row.querySelector('.sr-remove-band').addEventListener('click', () => row.remove());
    editor.appendChild(row);
  },

  renderBandEditor(bands) {
    const editor = document.getElementById('srRuleBandsEditor');
    if (!editor) return;
    editor.innerHTML = '';
    (Array.isArray(bands) && bands.length ? bands : [{}]).forEach(band => {
      this.addBandRow();
      const row = editor.lastElementChild;
      if (band.up_to !== undefined && band.up_to !== null) row.querySelector('.sr-band-upper').value = band.up_to;
      if (band.rate !== undefined && band.rate !== null) row.querySelector('.sr-band-rate').value = band.rate;
    });
  },

  collectBands() {
    const rows = Array.from(document.querySelectorAll('#srRuleBandsEditor .sr-band-row'));
    const bands = rows.map(row => {
      const upper = row.querySelector('.sr-band-upper').value.trim();
      const rate = row.querySelector('.sr-band-rate').value.trim();
      return { up_to: upper === '' ? null : Number(upper), rate: rate === '' ? null : Number(rate) };
    });
    if (!bands.length || bands.some(b => b.rate === null || (b.up_to !== null && b.up_to < 0))) return null;
    const openBand = bands.findIndex(b => b.up_to === null);
    if (openBand !== -1 && openBand !== bands.length - 1) return null;
    return bands;
  },

  async saveRule() {
    const number = id => {
      const value = document.getElementById(id).value;
      return value === '' ? undefined : Number(value);
    };
    const rules = {
      calculation: document.getElementById('srRuleCode').value === 'paye_bands' ? 'progressive_bands' : 'percentage_or_tiered',
      employee_rate: number('srRuleEmployeeRate'),
      employer_rate: number('srRuleEmployerRate'),
      lower_earnings_limit: number('srRuleLowerLimit'),
      upper_earnings_limit: number('srRuleUpperLimit'),
      cap_amount: number('srRuleCapAmount'),
      personal_relief: number('srRuleRelief'),
      deadline_day: number('srRuleDeadlineDay'),
      deadline_basis: document.getElementById('srRuleDeadlineBasis').value,
    };
    if (document.getElementById('srRuleCode').value === 'paye_bands') {
      rules.bands = this.collectBands();
      if (!rules.bands) return this.notify('Enter each PAYE band separately and leave the upper limit blank only for the final band.', 'warning');
    }
    Object.keys(rules).forEach(key => { if (rules[key] === undefined) delete rules[key]; });
    const payload = {
      agency: document.getElementById('srRuleAgency').value,
      rule_code: document.getElementById('srRuleCode').value,
      version: document.getElementById('srRuleVersion').value.trim(),
      effective_from: document.getElementById('srRuleFrom').value,
      rules,
      source_name: document.getElementById('srRuleSourceName').value.trim() || null,
      source_url: document.getElementById('srRuleSourceUrl').value.trim() || null,
    };
    if (!payload.version || !payload.effective_from) return this.notify('Version and effective date are required.', 'warning');
    const button = document.getElementById('srSaveRuleBtn');
    button.disabled = true;
    try {
      await callAPI('/staff/statutory-rules', 'POST', payload);
      bootstrap.Modal.getInstance(document.getElementById('srRuleModal'))?.hide();
      this.notify('Statutory rule version saved.', 'success');
      await this.loadCompliance();
    } catch (e) {
      this.notify(e.message || 'Rule version could not be saved.', 'danger');
    } finally {
      button.disabled = false;
    }
  },

  async load() {
    const body = document.getElementById('srTableBody');
    body.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const params = new URLSearchParams();
      const yr = document.getElementById('srYearFilter').value;
      const agency = document.getElementById('srAgencyFilter').value;
      const status = document.getElementById('srStatusFilter').value;
      if (yr) params.set('year', yr);
      if (agency) params.set('agency', agency);
      if (status) params.set('status', status);
      const r = await callAPI('/staff/statutory-remittances?' + params.toString(), 'GET');
      const data = r?.data || r;
      this.remittances = Array.isArray(data?.remittances) ? data.remittances : (Array.isArray(data) ? data : []);
      this.breakdown = Array.isArray(data?.breakdown) ? data.breakdown : [];
      this.render();
      this.renderBreakdown();
      this.updateKPIs(data?.summary || {});
    } catch (e) {
      body.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('srTableBody');
    const agency = document.getElementById('srAgencyFilter').value;
    const status = document.getElementById('srStatusFilter').value;
    let rows = this.remittances;
    if (agency) rows = rows.filter(r => r.agency === agency);
    if (status) rows = rows.filter(r => r.status === status);
    if (!rows.length) { body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No remittances found.</td></tr>'; return; }
    const fmt = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
    const months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const statusBadge = s => `<span class="badge bg-${s === 'paid' ? 'success' : s === 'overdue' ? 'danger' : s === 'filed' ? 'info' : s === 'partial' ? 'warning' : 'secondary'}">${this.esc(s)}</span>`;
    body.innerHTML = rows.map(r => {
      const bal = Number(r.total_deducted || 0) - Number(r.amount_remitted || 0);
      return `<tr>
        <td><strong>${this.esc(r.agency)}</strong></td>
        <td>${months[Number(r.period_month)]} ${this.esc(r.period_year)}</td>
        <td class="text-end">${fmt(r.total_deducted)}</td>
        <td class="text-end text-success">${fmt(r.amount_remitted)}</td>
        <td class="text-end ${bal > 0 ? 'text-warning fw-bold' : ''}">${fmt(bal)}</td>
        <td>${r.due_date || '—'}</td>
        <td>${r.remittance_date || r.filed_date || '—'}</td>
        <td>${statusBadge(r.status)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary me-1" data-kw-onclick="statutoryRemittancesController.viewDetail(${r.id})" title="View"><i class="bi bi-eye"></i></button>
          ${bal > 0 ? `<button class="btn btn-sm btn-outline-success me-1" data-kw-onclick="statutoryRemittancesController.initiatePayment(${r.id})" title="Submit bank payment"><i class="bi bi-bank"></i></button>` : ''}
          <button class="btn btn-sm btn-outline-secondary" data-kw-onclick="statutoryRemittancesController.edit(${r.id})" title="Edit" data-year-write><i class="bi bi-pencil"></i></button>
        </td>
      </tr>`;
    }).join('');
  },

  renderBreakdown() {
    const body = document.getElementById('srBreakdownBody');
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const yr = document.getElementById('srYearFilter').value || new Date().getFullYear();
    if (!this.breakdown.length) { body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No breakdown data for this year.</td></tr>'; return; }
    const fmt = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
    body.innerHTML = this.breakdown.map(b => {
      const total = Number(b.kra || 0) + Number(b.shif || 0) + Number(b.nssf || 0) + Number(b.housing_levy || 0);
      return `<tr>
        <td>${months[b.period_month - 1] || b.period_month} ${yr}</td>
        <td class="text-end">${fmt(b.kra)}</td>
        <td class="text-end">${fmt(b.shif)}</td>
        <td class="text-end">${fmt(b.nssf)}</td>
        <td class="text-end">${fmt(b.housing_levy)}</td>
        <td class="text-end fw-bold">${fmt(total)}</td>
      </tr>`;
    }).join('');
  },

  updateKPIs(summary) {
    const fmt = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
    document.getElementById('srTotalDeducted').textContent = fmt(summary.total_deducted || 0);
    document.getElementById('srTotalRemitted').textContent = fmt(summary.total_remitted || 0);
    document.getElementById('srOutstanding').textContent = fmt(summary.outstanding || 0);
    document.getElementById('srOverdue').textContent = summary.overdue_count || 0;
  },

  openNew() {
    document.getElementById('srEditId').value = '';
    document.getElementById('srModalTitle').textContent = 'New Remittance';
    document.getElementById('srAgency').value = 'KRA';
    document.getElementById('srMonth').value = new Date().getMonth() + 1;
    document.getElementById('srYear').value = new Date().getFullYear();
    document.getElementById('srDeducted').value = '';
    document.getElementById('srRemitted').value = '';
    document.getElementById('srStatus').value = 'pending';
    document.getElementById('srDueDate').value = '';
    document.getElementById('srFiledDate').value = '';
    document.getElementById('srReference').value = '';
    document.getElementById('srNotes').value = '';
    document.getElementById('srStaffBreakdown').style.display = 'none';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('srModal')).show();
  },

  async fetchDeducted() {
    const agency = document.getElementById('srAgency').value;
    const month = document.getElementById('srMonth').value;
    const year = document.getElementById('srYear').value;
    if (!agency || !month || !year) return;
    try {
      const r = await callAPI('/staff/statutory-remittances/calc?agency=' + encodeURIComponent(agency) + '&month=' + month + '&year=' + year, 'GET');
      const data = r?.data || r;
      document.getElementById('srDeducted').value = data.total || 0;
      if (data.staff && data.staff.length) {
        const body = document.getElementById('srStaffBody');
        const fmt = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
        body.innerHTML = data.staff.map(s => `<tr><td>${this.esc(s.staff_name || s.staff_no)}</td><td class="text-end">${fmt(s.amount)}</td></tr>`).join('');
        document.getElementById('srStaffBreakdown').style.display = '';
      } else {
        document.getElementById('srStaffBreakdown').style.display = 'none';
      }
    } catch (e) {
      document.getElementById('srDeducted').value = 0;
      document.getElementById('srStaffBreakdown').style.display = 'none';
    }
  },

  async save() {
    const editId = document.getElementById('srEditId').value;
    const payload = {
      agency: document.getElementById('srAgency').value,
      period_month: parseInt(document.getElementById('srMonth').value),
      period_year: parseInt(document.getElementById('srYear').value),
      total_deducted: parseFloat(document.getElementById('srDeducted').value) || 0,
      amount_remitted: parseFloat(document.getElementById('srRemitted').value) || 0,
      status: document.getElementById('srStatus').value,
      due_date: document.getElementById('srDueDate').value || null,
      remittance_date: document.getElementById('srFiledDate').value || null,
      filing_reference: document.getElementById('srReference').value || null,
      notes: document.getElementById('srNotes').value || null,
    };
    if (!payload.period_month || !payload.period_year) return this.notify('Select month and year', 'warning');
    if (payload.amount_remitted <= 0) return this.notify('Enter remittance amount', 'warning');
    const btn = document.getElementById('srSaveBtn');
    btn.disabled = true;
    try {
      if (editId) {
        await callAPI(`/staff/statutory-remittances/${editId}`, 'PUT', payload);
      } else {
        await callAPI('/staff/statutory-remittances', 'POST', payload);
      }
      bootstrap.Modal.getInstance(document.getElementById('srModal'))?.hide();
      this.notify(editId ? 'Remittance updated' : 'Remittance saved', 'success');
      await this.load();
    } catch (e) { this.notify(e.message || 'Save failed', 'danger'); }
    finally { btn.disabled = false; }
  },

  async edit(id) {
    const r = this.remittances.find(x => x.id === id);
    if (!r) return;
    document.getElementById('srEditId').value = r.id;
    document.getElementById('srModalTitle').textContent = 'Edit Remittance';
    document.getElementById('srAgency').value = r.agency;
    document.getElementById('srMonth').value = r.period_month;
    document.getElementById('srYear').value = r.period_year;
    document.getElementById('srDeducted').value = r.total_deducted || 0;
    document.getElementById('srRemitted').value = r.amount_remitted || 0;
    document.getElementById('srStatus').value = r.status;
    document.getElementById('srDueDate').value = r.due_date || '';
    document.getElementById('srFiledDate').value = r.remittance_date || '';
    document.getElementById('srReference').value = r.filing_reference || '';
    document.getElementById('srNotes').value = r.notes || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('srModal')).show();
  },

  async initiatePayment(id) {
    const remittance = this.remittances.find(r => Number(r.id) === Number(id));
    if (!remittance) return;
    const select = document.getElementById('srAgencyAccount');
    const hint = document.getElementById('srPaymentHint');
    document.getElementById('srPaymentId').value = id;
    select.innerHTML = '<option value="">Loading accounts…</option>';
    hint.textContent = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('srPaymentModal')).show();
    try {
      const response = await callAPI('/staff/statutory-agency-accounts?agency=' + encodeURIComponent(remittance.agency), 'GET');
      const data = response?.data || response;
      const accounts = Array.isArray(data?.accounts) ? data.accounts : [];
      select.innerHTML = accounts.length
        ? '<option value="">Select receiving account</option>' + accounts.map(a => `<option value="${Number(a.id)}">${this.esc(a.account_name)} · ${this.esc(a.bank_name || 'Configured bank')} · ${this.esc(a.account_number)}</option>`).join('')
        : '<option value="">No active receiving account configured</option>';
      hint.textContent = accounts.length ? `Amount to submit: KES ${Number(remittance.total_deducted - remittance.amount_remitted).toLocaleString('en-KE', {minimumFractionDigits: 2})}` : 'Ask an administrator to configure this agency account first.';
      const sourceResponse = await callAPI('/finance/financial-accounts', 'GET');
      const sourceAccounts = ((sourceResponse?.data || sourceResponse)?.accounts || []).filter(a => String(a.purposes || '').split(',').includes('statutory') && String(a.channels || '').split(',').includes('buni_transfer'));
      document.getElementById('srSourceAccount').innerHTML = sourceAccounts.length
        ? '<option value="">Select school source account</option>' + sourceAccounts.map(a => '<option value="' + Number(a.id) + '">' + this.esc(a.account_name) + ' · ' + this.esc(a.account_identifier) + '</option>').join('')
        : '<option value="">No authorized source account configured</option>';
    } catch (e) {
      select.innerHTML = '<option value="">Unable to load accounts</option>';
      hint.textContent = e.message || 'Account lookup failed';
    }
  },

  async submitPayment() {
    const id = Number(document.getElementById('srPaymentId').value);
    const accountId = Number(document.getElementById('srAgencyAccount').value);
    const sourceAccountId = Number(document.getElementById('srSourceAccount').value);
    if (!id || !accountId || !sourceAccountId) return this.notify('Select both agency receiving and school source accounts.', 'warning');
    const btn = document.getElementById('srSubmitPaymentBtn');
    btn.disabled = true;
    try {
      await callAPI(`/staff/statutory-remittances/${id}/initiate-payment`, 'POST', { agency_account_id: accountId, source_financial_account_id: sourceAccountId });
      bootstrap.Modal.getInstance(document.getElementById('srPaymentModal'))?.hide();
      this.notify('Payment submitted; final status will update from the bank callback.', 'success');
      await this.load();
    } catch (e) { this.notify(e.message || 'Payment submission failed', 'danger'); }
    finally { btn.disabled = false; }
  },

  async viewDetail(id) {
    const r = this.remittances.find(x => x.id === id);
    if (!r) return;
    this.edit(id);
  },

  async notify(msg, type = 'info') {
    if (window.showNotification) window.showNotification(msg, type);
    else alert(msg);
  },

  esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }
};

window.statutoryRemittancesController = statutoryRemittancesController;
document.addEventListener('DOMContentLoaded', () => statutoryRemittancesController.init().catch(() => {}));
