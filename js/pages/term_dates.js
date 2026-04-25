/**
 * Term Dates Controller
 * View and edit academic term start/end dates and holidays.
 * API base: /api/schedules/term-dates or /api/academic/terms
 */

const termDatesController = {

  _currentYearId: null,
  _terms: [],

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    // Show Add button only to users with manage permission
    if (AuthContext.hasPermission('academic.manage') || AuthContext.hasPermission('academic_manage')) {
      const btn = document.getElementById('tdAddBtn');
      if (btn) btn.classList.remove('d-none');
    }
    await this._loadYears();
  },

  // ── ACADEMIC YEARS ──────────────────────────────────────────────────

  _loadYears: async function () {
    const sel = document.getElementById('tdYear');
    if (!sel) return;
    try {
      const r = await callAPI('/academic/academic-years', 'GET')
        .catch(() => callAPI('/schedules/academic-years', 'GET'));
      const years = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      sel.innerHTML = '<option value="">— Select Year —</option>';
      years.forEach(y => {
        const o = document.createElement('option');
        o.value = y.id;
        o.textContent = y.name || y.year_name || y.year;
        if (y.is_current || y.status === 'active') o.selected = true;
        sel.appendChild(o);
      });
      if (sel.value) {
        this._currentYearId = sel.value;
        await this.loadTerms(sel.value);
      } else if (years.length) {
        sel.value = years[0].id;
        this._currentYearId = years[0].id;
        await this.loadTerms(years[0].id);
      } else {
        this._renderEmpty('No academic years found.');
      }
    } catch (e) {
      this._renderError('Failed to load academic years: ' + this._esc(e.message));
    }
  },

  // ── TERMS ───────────────────────────────────────────────────────────

  loadTerms: async function (yearId) {
    this._currentYearId = yearId;
    const container = document.getElementById('tdTableBody');
    if (!container) return;
    if (!yearId) { container.innerHTML = '<div class="alert alert-info">Select an academic year to view terms.</div>'; return; }
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    try {
      let r = await callAPI('/schedules/term-dates?year_id=' + yearId, 'GET')
        .catch(() => callAPI('/academic/terms?academic_year_id=' + yearId, 'GET'));
      this._terms = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      if (!this._terms.length) {
        container.innerHTML = '<div class="alert alert-info text-center">No terms found for this academic year.</div>';
        return;
      }
      this._renderTable(container);
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load terms: ${this._esc(e.message)}</div>`;
    }
  },

  _renderTable: function (container) {
    const rows = this._terms.map(t => {
      const start = t.start_date || t.term_start || '—';
      const end   = t.end_date   || t.term_end   || '—';
      const weeks = this._calcWeeks(start, end);
      const badge = this._statusBadge(t.status || t.term_status);
      return `<tr>
        <td class="fw-semibold">${this._esc(t.name || t.term_name)}</td>
        <td>${this._esc(t.academic_year_name || t.year_name || '—')}</td>
        <td>${this._esc(start)}</td>
        <td>${this._esc(end)}</td>
        <td>${weeks ? weeks + ' wks' : '—'}</td>
        <td>${badge}</td>
        <td>
          <button class="btn btn-sm btn-outline-primary" onclick="termDatesController.editTerm(${t.id})">
            <i class="bi bi-pencil"></i> Edit
          </button>
        </td>
      </tr>`;
    }).join('');
    container.innerHTML = `<div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Term Name</th><th>Academic Year</th><th>Start Date</th>
            <th>End Date</th><th>Weeks</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
  },

  _calcWeeks: function (start, end) {
    if (!start || !end || start === '—' || end === '—') return null;
    const ms = new Date(end) - new Date(start);
    if (isNaN(ms) || ms <= 0) return null;
    return Math.round(ms / (1000 * 60 * 60 * 24 * 7));
  },

  _statusBadge: function (status) {
    const map = { active: 'success', current: 'success', upcoming: 'primary', completed: 'secondary', past: 'secondary' };
    const s    = (status || 'unknown').toLowerCase();
    const cls  = map[s] || 'secondary';
    return `<span class="badge bg-${cls}">${this._esc(status || 'Unknown')}</span>`;
  },

  // ── EDIT MODAL ──────────────────────────────────────────────────────

  editTerm: function (id) {
    const term = this._terms.find(t => t.id == id);
    if (!term) { showNotification('Term not found', 'danger'); return; }
    document.getElementById('tdTermId').value       = id;
    document.getElementById('tdTermName').value     = term.name || term.term_name || '';
    document.getElementById('tdStartDate').value    = term.start_date || term.term_start || '';
    document.getElementById('tdEndDate').value      = term.end_date   || term.term_end   || '';
    document.getElementById('tdHolidayNotes').value = term.holiday_notes || term.notes   || '';
    document.getElementById('tdError').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('tdModal')).show();
  },

  showAddModal: function () {
    ['tdTermId','tdTermName','tdStartDate','tdEndDate','tdHolidayNotes'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    document.getElementById('tdTermName').removeAttribute('readonly');
    document.getElementById('tdModalTitle').textContent = 'Add Term';
    document.getElementById('tdError').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('tdModal')).show();
  },

  saveTerm: async function () {
    const errEl = document.getElementById('tdError');
    errEl.classList.add('d-none');
    const id        = document.getElementById('tdTermId').value;
    const startDate = document.getElementById('tdStartDate').value;
    const endDate   = document.getElementById('tdEndDate').value;
    if (!startDate || !endDate) {
      errEl.textContent = 'Start date and end date are required';
      errEl.classList.remove('d-none');
      return;
    }
    if (new Date(endDate) <= new Date(startDate)) {
      errEl.textContent = 'End date must be after start date';
      errEl.classList.remove('d-none');
      return;
    }
    const payload = {
      start_date:    startDate,
      end_date:      endDate,
      holiday_notes: document.getElementById('tdHolidayNotes').value.trim(),
    };
    if (!id) {
      payload.name           = document.getElementById('tdTermName').value.trim();
      payload.academic_year_id = this._currentYearId;
    }
    try {
      if (id) {
        await callAPI('/schedules/term-dates/' + id, 'PUT', payload)
          .catch(() => callAPI('/academic/terms/' + id, 'PUT', payload));
      } else {
        await callAPI('/schedules/term-dates', 'POST', payload)
          .catch(() => callAPI('/academic/terms', 'POST', payload));
      }
      bootstrap.Modal.getInstance(document.getElementById('tdModal')).hide();
      showNotification('Term dates saved', 'success');
      await this.loadTerms(this._currentYearId);
    } catch (e) {
      errEl.textContent = e.message || 'Save failed';
      errEl.classList.remove('d-none');
    }
  },

  // ── UTILS ────────────────────────────────────────────────────────────

  _renderEmpty: function (msg) {
    const el = document.getElementById('tdTableBody');
    if (el) el.innerHTML = `<div class="alert alert-info text-center">${this._esc(msg)}</div>`;
  },

  _renderError: function (msg) {
    const el = document.getElementById('tdTableBody');
    if (el) el.innerHTML = `<div class="alert alert-danger">${this._esc(msg)}</div>`;
  },

  _set: function (id, val) { const el = document.getElementById(id); if (el) el.textContent = val; },

  _esc: function (s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  },
};
