/**
 * Class Conduct Grades Controller
 * Conduct ratings per student in the teacher's class.
 * API: GET /academic/conduct-grades?class=self
 */
const classConductGradesController = {
  _data: [],

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    document.getElementById('cgApplyBtn')?.addEventListener('click', () => this._load());
    await this._load();
  },

  _load: async function () {
    this._show('cgLoading');
    const term = document.getElementById('cgTermFilter')?.value || '';
    const cls  = document.getElementById('cgClassFilter')?.value || 'self';
    const params = new URLSearchParams({ class: cls });
    if (term) params.set('term', term);

    try {
      const r = await callAPI('/academic/conduct-grades?' + params.toString(), 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._setStats();
      this._render();
    } catch (e) {
      this._show('cgEmpty');
      console.warn('Conduct grades failed:', e);
    }
  },

  _setStats: function () {
    const gradeOf = g => (g || '').toUpperCase();
    const excellent    = this._data.filter(s => gradeOf(s.conduct_grade) === 'A').length;
    const satisfactory = this._data.filter(s => ['B', 'C'].includes(gradeOf(s.conduct_grade))).length;
    const needsWork    = this._data.filter(s => gradeOf(s.conduct_grade) === 'D').length;
    this._set('cgStatExcellent',         excellent);
    this._set('cgStatSatisfactory',      satisfactory);
    this._set('cgStatNeedsImprovement',  needsWork);
  },

  _render: function () {
    const tbody = document.getElementById('cgTableBody');
    if (!tbody) return;

    if (!this._data.length) {
      this._show('cgEmpty');
      return;
    }

    this._show('cgContent');
    const gradeCls = { A: 'success', B: 'primary', C: 'info', D: 'danger' };

    tbody.innerHTML = this._data.map(s => {
      const grade = (s.conduct_grade || '—').toUpperCase();
      return `<tr>
        <td>
          <div class="fw-semibold">${this._esc((s.first_name || '') + ' ' + (s.last_name || s.student_name || ''))}</div>
          <div class="text-muted small">${this._esc(s.admission_no || '')}</div>
        </td>
        <td>
          <span class="badge fs-6 bg-${gradeCls[grade] || 'secondary'}">${grade}</span>
        </td>
        <td class="small text-success">${this._esc(s.strengths || s.key_strengths || '—')}</td>
        <td class="small text-warning">${this._esc(s.improvement_areas || s.areas_to_improve || '—')}</td>
        <td class="small text-muted" style="max-width:180px;">${this._esc((s.teacher_comments || s.comments || '—').substring(0, 80))}</td>
        <td>
          ${s.student_id || s.id
            ? `<a href="${window.APP_BASE || ''}home.php?route=student_profiles&id=${s.student_id || s.id}"
                class="btn btn-sm btn-outline-secondary">Profile</a>`
            : ''}
        </td>
      </tr>`;
    }).join('');
  },

  _show: function (id) {
    ['cgLoading', 'cgContent', 'cgEmpty'].forEach(el => {
      const e = document.getElementById(el);
      if (e) e.style.display = el === id ? '' : 'none';
    });
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => classConductGradesController.init());
