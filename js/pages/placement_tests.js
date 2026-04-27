/**
 * Placement Tests Controller
 * Schedule tests for new admissions, enter scores, manage results.
 * API: /students/admissions, /academic/academic-years
 */

const placementTestsController = {

  _data: [],
  _activeTab: 'pending',
  _schedModal: null,
  _scoreModal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._schedModal = new bootstrap.Modal(document.getElementById('ptScheduleModal'));
    this._scoreModal = new bootstrap.Modal(document.getElementById('ptScoreModal'));
    await Promise.all([this._loadData(), this._loadApplicants()]);
  },

  _loadData: async function () {
    const container = document.getElementById('ptTableBody');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    try {
      const r = await callAPI('/students/admissions?status=pending', 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      // Compute stats
      const pending   = this._data.filter(t => !t.test_date || !t.score).length;
      const thisMonth = this._data.filter(t => {
        if (!t.test_date) return false;
        const d = new Date(t.test_date);
        const n = new Date();
        return d.getMonth() === n.getMonth() && d.getFullYear() === n.getFullYear();
      }).length;
      const graded    = this._data.filter(t => t.score !== null && t.score !== undefined);
      const passed    = graded.filter(t => Number(t.score) >= Number(t.pass_mark || 50));
      const passRate  = graded.length ? Math.round((passed.length / graded.length) * 100) + '%' : '—';
      const awaiting  = this._data.filter(t => t.score !== null && !t.class_recommended).length;

      this._set('ptStatPending',  pending);
      this._set('ptStatMonth',    thisMonth);
      this._set('ptStatPassRate', passRate);
      this._set('ptStatAwaiting', awaiting);

      this._renderTab(this._activeTab);
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load placement tests: ${this._esc(e.message)}</div>`;
    }
  },

  _loadApplicants: async function () {
    try {
      const r = await callAPI('/students/admissions?status=pending', 'GET');
      const applicants = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('ptApplicantId');
      if (sel) {
        sel.innerHTML = '<option value="">— Select applicant —</option>' +
          applicants.map(a => `<option value="${a.id}">${this._esc((a.first_name||'') + ' ' + (a.last_name||''))} — ${this._esc(a.class_applied||'')}</option>`).join('');
      }
    } catch (e) { console.warn('Applicants failed:', e); }
  },

  switchTab: function (btn, status) {
    this._activeTab = status;
    document.querySelectorAll('#ptTabs .nav-link').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    this._renderTab(status);
  },

  _renderTab: function (status) {
    const container = document.getElementById('ptTableBody');
    if (!container) return;
    const filtered = status === 'all' ? this._data :
      status === 'pending' ? this._data.filter(t => !t.score) :
      this._data.filter(t => t.score !== null && t.score !== undefined);

    if (!filtered.length) {
      container.innerHTML = '<div class="alert alert-info text-center mt-2">No records found for this filter.</div>';
      return;
    }

    const rows = filtered.map(t => {
      const hasScore = t.score !== null && t.score !== undefined;
      const pct      = hasScore ? Math.round((Number(t.score) / Number(t.max_score || 100)) * 100) : null;
      const passed   = hasScore && pct >= Number(t.pass_mark || 50);
      return `<tr>
        <td class="fw-semibold">${this._esc((t.first_name||'') + ' ' + (t.last_name||''))}</td>
        <td>${this._esc(t.class_applied || '—')}</td>
        <td>${this._esc(t.test_type || '—')}</td>
        <td>${this._esc(t.test_date || 'Not scheduled')}</td>
        <td>${hasScore ? `<span class="fw-bold ${passed?'text-success':'text-danger'}">${pct}% <small>(${t.score}/${t.max_score||100})</small></span>` : '<span class="text-muted">—</span>'}</td>
        <td>${hasScore ? `<span class="badge bg-${passed?'success':'danger'}">${passed?'Pass':'Fail'}</span>` : '<span class="badge bg-secondary">Pending</span>'}</td>
        <td>${this._esc(t.class_recommended || '—')}</td>
        <td class="text-end">
          ${!hasScore ? `<button class="btn btn-sm btn-primary" onclick="placementTestsController.showScoreModal(${t.id})">Enter Score</button>` : ''}
        </td>
      </tr>`;
    }).join('');

    container.innerHTML = `
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Applicant</th><th>Class Applied</th><th>Test Type</th><th>Test Date</th>
              <th>Score</th><th>Result</th><th>Class Recommended</th><th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  },

  showScheduleModal: function () {
    ['ptApplicantId','ptAppliedClass','ptTestDate'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    const err = document.getElementById('ptScheduleError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._schedModal.show();
  },

  saveSchedule: async function () {
    const applicant = document.getElementById('ptApplicantId')?.value;
    const cls       = document.getElementById('ptAppliedClass')?.value.trim();
    const date      = document.getElementById('ptTestDate')?.value;
    const type      = document.getElementById('ptTestType')?.value;
    const errEl     = document.getElementById('ptScheduleError');

    if (!applicant || !cls || !date) {
      if (errEl) { errEl.textContent = 'Applicant, class, and date are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      await callAPI('/students/admissions', 'POST', { applicant_id: applicant, class_applied: cls, test_date: date, test_type: type });
      showNotification('Placement test scheduled.', 'success');
      this._schedModal.hide();
      await this._loadData();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to schedule.'; errEl.classList.remove('d-none'); }
    }
  },

  showScoreModal: function (id) {
    document.getElementById('ptScoreTestId').value = id;
    ['ptScore','ptClassRecommended','ptScoreNotes'].forEach(fid => { const el = document.getElementById(fid); if (el) el.value = ''; });
    document.getElementById('ptResult').value = '';
    const err = document.getElementById('ptScoreError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._scoreModal.show();
  },

  computeResult: function () {
    const score    = Number(document.getElementById('ptScore')?.value    || 0);
    const max      = Number(document.getElementById('ptMaxScore')?.value  || 100);
    const pass     = Number(document.getElementById('ptPassMark')?.value  || 50);
    const resultEl = document.getElementById('ptResult');
    if (!resultEl || !score) return;
    const pct = max > 0 ? Math.round((score / max) * 100) : 0;
    resultEl.value = pct >= pass ? `Pass (${pct}%)` : `Fail (${pct}%)`;
  },

  saveScore: async function () {
    const id       = document.getElementById('ptScoreTestId')?.value;
    const score    = document.getElementById('ptScore')?.value;
    const max      = document.getElementById('ptMaxScore')?.value || 100;
    const pass     = document.getElementById('ptPassMark')?.value || 50;
    const cls      = document.getElementById('ptClassRecommended')?.value.trim();
    const notes    = document.getElementById('ptScoreNotes')?.value.trim();
    const errEl    = document.getElementById('ptScoreError');

    if (!score) {
      if (errEl) { errEl.textContent = 'Please enter a score.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      await callAPI('/students/admissions/' + id, 'PUT', { score, max_score: max, pass_mark: pass, class_recommended: cls, notes });
      showNotification('Score saved.', 'success');
      this._scoreModal.hide();
      await this._loadData();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to save score.'; errEl.classList.remove('d-none'); }
    }
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => placementTestsController.init());
