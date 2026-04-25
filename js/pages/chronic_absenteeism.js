/**
 * Chronic Absenteeism Controller
 * Students with persistent attendance issues, filterable by threshold.
 * API: GET /attendance/chronic-student-absentees?threshold=N
 */
const chronicAbsenteeismController = {
  _data: [],

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    document.getElementById('caApplyBtn')?.addEventListener('click', () => this._load());
    await this._load();
  },

  _load: async function () {
    const threshold = document.getElementById('caThreshold')?.value || '80';
    this._show('caLoading');

    try {
      const r = await callAPI('/attendance/chronic-student-absentees?threshold=' + threshold, 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._setStats();
      this._render();
    } catch (e) {
      this._show('caEmpty');
      console.warn('Chronic absenteeism load failed:', e);
    }
  },

  _setStats: function () {
    const critical = this._data.filter(s => Number(s.attendance_percentage ?? s.attendance_rate ?? 100) < 70).length;
    const warning  = this._data.filter(s => {
      const pct = Number(s.attendance_percentage ?? s.attendance_rate ?? 100);
      return pct >= 70 && pct < 80;
    }).length;
    this._set('caStatCritical', critical);
    this._set('caStatWarning',  warning);
    this._set('caStatTotal',    this._data.length);
  },

  _render: function () {
    const tbody = document.getElementById('caTableBody');
    if (!tbody) return;

    if (!this._data.length) {
      this._show('caEmpty');
      return;
    }

    this._show('caContent');
    tbody.innerHTML = this._data.map(s => {
      const pct = Number(s.attendance_percentage ?? s.attendance_rate ?? 0);
      const barCls = pct < 70 ? 'bg-danger' : pct < 80 ? 'bg-warning' : 'bg-info';
      const txtCls = pct < 70 ? 'text-danger fw-bold' : pct < 80 ? 'text-warning fw-semibold' : '';
      return `<tr>
        <td>
          <div class="fw-semibold">${this._esc((s.first_name || '') + ' ' + (s.last_name || s.student_name || ''))}</div>
          <div class="text-muted small">${this._esc(s.admission_no || '')}</div>
        </td>
        <td>${this._esc(s.class_name || s.grade || '—')}</td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="progress flex-grow-1" style="height:6px; min-width:60px;">
              <div class="progress-bar ${barCls}" style="width:${Math.min(pct,100)}%"></div>
            </div>
            <span class="${txtCls}">${pct.toFixed(1)}%</span>
          </div>
        </td>
        <td class="fw-semibold text-danger">${this._esc(s.days_absent ?? s.absent_days ?? '—')}</td>
        <td class="small">${this._esc(s.last_absence_date ?? s.last_absent ?? '—')}</td>
        <td>
          ${s.parent_notified
            ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Yes</span>'
            : '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>No</span>'}
        </td>
        <td>
          ${s.id
            ? `<a href="${window.APP_BASE || ''}home.php?route=student_profiles&id=${s.student_id || s.id}"
                class="btn btn-sm btn-outline-primary">Profile</a>`
            : ''}
        </td>
      </tr>`;
    }).join('');
  },

  _show: function (id) {
    ['caLoading', 'caContent', 'caEmpty'].forEach(el => {
      const e = document.getElementById(el);
      if (e) e.style.display = el === id ? '' : 'none';
    });
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => chronicAbsenteeismController.init());
