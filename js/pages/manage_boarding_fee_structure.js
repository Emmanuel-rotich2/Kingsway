/**
 * manage_boarding_fee_structure.js — Approved School Fee Matrix Controller.
 * Accountant manages grade/student-type fee amounts per year and term.
 */
const manageBoardingFeeStructureController = {
  state: { bundles: [], years: [], levels: [], types: [], terms: [], classes: [], editing: null, currentTerms: [], rows: [], referenceCache: null },
  TYPE_NAME: 'BOARD',

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data?.items || r?.data || []); },

  parseYear(y) { const n = parseInt(String(y || '').slice(0, 4), 10); return isNaN(n) ? String(new Date().getFullYear()) : String(n); },

  termNumber(t) { const n = parseInt(String(t?.term_number ?? t?.code ?? '').replace('T', '').replace('t', ''), 10); return isNaN(n) ? null : n; },

  getDefaultYear() {
    const cur = (this.state.years || []).find(y => y.is_current || y.is_current_year);
    return this.parseYear(cur?.year_code || cur?.year || String(new Date().getFullYear()));
  },

  termsForYear(year) {
    const y = String(year);
    return (this.state.terms || []).filter(t => String(t.year_code || t.year || '').slice(0, 4) === y.slice(0, 4));
  },

  typeId() {
    const t = (this.state.types || []).find(t => String(t.name || t.type_name || '').toUpperCase().includes(this.TYPE_NAME));
    return t ? Number(t.id) : null;
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("finance", "manage-boarding-fee-structure-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Manage boarding fee structure Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async loadReference() {
    try {
      const cache = this.state.referenceCache;
      const [years, levels, types, terms, classes] = await Promise.all([
        cache?.years !== undefined ? cache.years : this.API('GET', 'academic/years/list'),
        cache?.levels !== undefined ? cache.levels : this.API('GET', 'academic/levels-list'),
        cache?.studentTypes !== undefined ? cache.studentTypes : this.API('GET', 'finance/student-types-list'),
        cache?.terms !== undefined ? cache.terms : this.API('GET', 'academic/terms-list'),
        cache?.classes !== undefined ? cache.classes : this.API('GET', 'academic/classes-list', null, { limit: 200 }),
      ]);
      this.state.years = this.norm(years);
      this.state.levels = this.norm(levels);
      this.state.types = this.norm(types);
      this.state.terms = this.norm(terms);
      this.state.classes = this.norm(classes);
      const yearSel = document.getElementById('mbfYear');
      yearSel.innerHTML = '<option value="">All years</option>' + this.state.years.map(y => `<option value="${this.esc(y.year_code || y.year)}">${this.esc(y.year_code || y.year)}</option>`).join('');
      const levelSel = document.getElementById('mbfLevel');
      levelSel.innerHTML = '<option value="">All levels</option>' + this.state.levels.map(l => `<option value="${l.id}">${this.esc(l.name || l.level_name)}</option>`).join('');
    } catch (e) { this.notify('Failed to load reference data', 'danger'); }
  },

  async loadGrid() {
    const body = document.getElementById('mbfBody');
    body.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const params = { limit: 100 };
      const y = document.getElementById('mbfYear').value;
      if (y) params.academic_year = y;
      const lv = document.getElementById('mbfLevel').value;
      if (lv) params.level_id = lv;
      const r = await this.API('GET', 'finance/fees-bundle-list', null, params);
      const all = r?.bundles || [];
      const tid = this.typeId();
      this.state.bundles = tid ? all.filter(b => Number(b.student_type_id) === tid) : all.filter(b => String(b.student_type_name || '').toUpperCase().includes(this.TYPE_NAME));
      this.render();
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  render() {
    const body = document.getElementById('mbfBody');
    if (!this.state.bundles.length) { body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No school fee structures found.</td></tr>'; return; }
    body.innerHTML = this.state.bundles.map(b => `
      <tr>
        <td class="small fw-semibold">${this.esc(b.academic_year || '\u2014')}</td>
        <td class="small">${this.esc(b.level_name || '\u2014')}</td>
        <td class="small">${this.esc(b.student_type_name || '\u2014')}</td>
        <td class="small text-end">${this.money(b.term_1_amount)}</td>
        <td class="small text-end">${this.money(b.term_2_amount)}</td>
        <td class="small text-end">${this.money(b.term_3_amount)}</td>
        <td class="small text-end fw-semibold">${this.money(b.total_amount)}</td>
        <td><span class="badge ${this.badgeClass(b.status)}">${this.esc(b.status || 'draft')}</span></td>
      </tr>`).join('');
  },

  money(value) {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toLocaleString('en-KE', { maximumFractionDigits: 0 });
  },

  badgeClass(s) { return { approved: 'bg-success', reviewed: 'bg-info', submitted: 'bg-primary', draft: 'bg-warning text-dark', rejected: 'bg-danger' }[s] || 'bg-secondary'; },

  async edit() {
    const year = document.getElementById('mbfYear').value || this.getDefaultYear();
    const level = document.getElementById('mbfLevel').value;
    const tid = this.typeId();
    const classIds = (this.state.classes || []).filter(c => !level || String(c.level_id) === String(level)).map(c => parseInt(c.id, 10));
    if (!classIds.length) return this.notify('Select a level that has classes first.', 'warning');
    const fromId = Math.min(...classIds), toId = Math.max(...classIds);
    try {
      const resp = await this.API('GET', 'finance/fees-bundle-grid', null, { academic_year: year, from_id: fromId, to_id: toId, student_type_ids: tid ? [tid] : [] });
      const g = resp?.data ?? resp ?? {};
      const gradeRange = g.grade_range || { from_id: fromId, to_id: toId };
      const items = g.items || {};
      this.state.currentTerms = (g.terms || []).map(t => ({ id: t.id, term_number: t.number, code: `T${t.number}`, name: t.name }));
      if (!this.state.currentTerms.length) this.state.currentTerms = this.termsForYear(year);
      this.state.rows = Object.keys(items).length
        ? Object.keys(items).map(code => ({ code, name: items[code].name || code, values: items[code].terms || {} }))
        : [{ code: 'SCHOOL_FEES', name: 'School Fees', values: {} }];
      this.renderForm({ academic_year: year, from_id: gradeRange.from_id, to_id: gradeRange.to_id, student_type_id: tid });
      new bootstrap.Modal(document.getElementById('mbfModal')).show();
    } catch (e) { this.notify(e.message || 'Failed to load school fees', 'danger'); }
  },

  renderForm(initial = {}) {
    const body = document.getElementById('mbfModalBody');
    const tid = initial.student_type_id || this.typeId() || 1;
    const terms = this.state.currentTerms;
    const classes = this.state.classes || [];
    const classOpts = classes.map(c => `<option value="${c.id}" ${c.id == initial.from_id ? 'selected' : ''}>${this.esc(c.name || c.class_name)}</option>`).join('');
    body.innerHTML = `
      <div class="row g-3 mb-3">
        <div class="col-md-4"><label class="form-label small fw-semibold">Academic Year</label><input class="form-control form-control-sm" value="${this.esc(initial.academic_year || '')}" disabled></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">From Class</label><select class="form-select form-select-sm" id="mbfFrom">${classOpts}</select></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">To Class</label><select class="form-select form-select-sm" id="mbfTo"></select></div>
      </div>
      <div class="table-responsive"><table class="table table-bordered align-middle mb-0 mtf-matrix">
        <thead class="table-light"><tr><th>Fee Item</th>${terms.map(t => `<th class="text-center">${this.esc(t.name || t.code)}</th>`).join('')}</tr></thead>
        <tbody id="mbfRows"></tbody>
      </table></div>
      <small class="text-muted">All amounts are in KES. Leave a cell blank to exclude it.</small>`;
    const fromSel = document.getElementById('mbfFrom');
    const toSel = document.getElementById('mbfTo');
    const fill = () => { toSel.innerHTML = [...classes.filter(c => !fromSel.value || c.id >= Number(fromSel.value)).map(c => `<option value="${c.id}" ${c.id == initial.to_id ? 'selected' : ''}>${this.esc(c.name || c.class_name)}</option>`).join('')]; };
    fromSel.value = initial.from_id || fromSel.value;
    fromSel.addEventListener('change', fill);
    fill();
    this.renderRows(terms, tid);
  },

  renderRows(terms, stId) {
    const tbody = document.getElementById('mbfRows');
    const grid = terms.map(t => t.code || `T${t.term_number}`);
    tbody.innerHTML = this.state.rows.map((row, idx) => {
      const cells = grid.map(termKey => {
        const val = row.values?.[termKey]?.[stId];
        return `<td class="text-center"><input type="number" class="form-control form-control-sm" style="width:110px" data-row="${idx}" data-term="${termKey}" value="${val == null || val === '' ? '' : val}" placeholder="0"></td>`;
      }).join('');
      return `<tr><td><div class="fw-semibold small">${this.esc(row.name)}</div><div class="text-muted small">${this.esc(row.code)}</div></td>${cells}</tr>`;
    }).join('');
    tbody.querySelectorAll('input').forEach(inp => inp.addEventListener('input', () => {
      const r = Number(inp.dataset.row);
      this.state.rows[r].values[inp.dataset.term] = this.state.rows[r].values[inp.dataset.term] || {};
      this.state.rows[r].values[inp.dataset.term][stId] = inp.value === '' ? null : Number(inp.value);
    }));
  },

  async save(mode) {
    const tid = this.typeId() || 1;
    const fromId = document.getElementById('mbfFrom').value;
    const toId = document.getElementById('mbfTo').value;
    const year = document.getElementById('mbfYear').value || this.getDefaultYear();
    if (!fromId || !toId) return this.notify('Select the grade range.', 'warning');
    if (parseInt(toId, 10) < parseInt(fromId, 10)) return this.notify('To Class cannot come before From Class.', 'warning');
    const items = {};
    this.state.rows.forEach(row => {
      const code = String(row.code || '').trim();
      if (!code) return;
      items[code] = {};
      this.state.currentTerms.forEach(t => {
        const termKey = t.code || `T${t.term_number}`;
        items[code][termKey] = { [tid]: row.values?.[termKey]?.[tid] ?? null };
      });
    });
    if (!Object.keys(items).length) return this.notify('Add at least one fee item.', 'warning');
    const user = window.AuthContext?.getUser?.() || {};
    const payload = { academic_year: this.parseYear(year), grade_range: { from_id: parseInt(fromId, 10), to_id: parseInt(toId, 10) }, student_type_ids: [tid], items, created_by: user.user_id || user.id || 1 };
    try {
      await this.API('POST', 'finance/fees-create-bundle', payload);
      if (mode === 'submit') {
        const levelId = this.state.bundles[0]?.level_id || Math.round((parseInt(fromId, 10) + parseInt(toId, 10)) / 2);
        for (const t of this.state.currentTerms) {
          await this.API('POST', 'finance/fees-bundle-submit', { level_id: levelId, academic_year: this.parseYear(year), term_id: t.id || this.termNumber(t), student_type_id: tid, notes: 'Submitted for director review' });
        }
      }
      this.notify(mode === 'submit' ? 'Boarding fees saved and submitted for review' : 'Boarding fees saved as draft');
      bootstrap.Modal.getInstance(document.getElementById('mbfModal'))?.hide();
      this.loadGrid();
    } catch (e) { this.notify(e.message || 'Save failed', 'danger'); }
  },

  setupEventListeners() {
    ['mbfYear', 'mbfLevel'].forEach(id => document.getElementById(id)?.addEventListener('change', () => this.loadGrid()));
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.loadReferencesComposed();
    await this.loadReference();
    await this.loadGrid();
    window.APIRealtime?.register?.("manage_boarding_fee_structure", { refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.loadReference(); await this.loadGrid(); } }, ["finance", "boarding"]);
  },
};

window.manageBoardingFeeStructureController = manageBoardingFeeStructureController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => manageBoardingFeeStructureController.init().catch(() => {}));
} else {
  manageBoardingFeeStructureController.init().catch(() => {});
}
