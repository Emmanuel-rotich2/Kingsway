/**
 * Observation Schedule Controller
 * View and schedule classroom observation sessions.
 * API: GET/POST /staff/observation-schedule
 */
const observationScheduleController = {
  _data: [],
  _modal: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._modal = new bootstrap.Modal(document.getElementById('osModal'));
    document.getElementById('osScheduleBtn')?.addEventListener('click', () => this.showModal());
    document.getElementById('osSaveBtn')?.addEventListener('click', () => this.save());
    await this._load();
  },

  _load: async function () {
    this._show('osLoading');
    try {
      const r = await callAPI('/staff/observation-schedule', 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._setStats();
      this._render();
    } catch (e) {
      this._show('osEmpty');
      console.warn('Observation schedule failed:', e);
    }
  },

  _setStats: function () {
    const today = new Date().toISOString().split('T')[0];
    const upcoming  = this._data.filter(o => (o.observation_date || o.date || '') >= today && (o.status || '') !== 'completed').length;
    const completed = this._data.filter(o => (o.status || '').toLowerCase() === 'completed').length;
    this._set('osStatUpcoming',  upcoming);
    this._set('osStatCompleted', completed);
    this._set('osStatThisTerm',  this._data.length);
  },

  _render: function () {
    const tbody = document.getElementById('osTableBody');
    if (!tbody) return;

    if (!this._data.length) {
      this._show('osEmpty');
      return;
    }

    this._show('osContent');
    const statusCls = { scheduled: 'primary', completed: 'success', cancelled: 'secondary', rescheduled: 'warning' };

    tbody.innerHTML = this._data.map(o => {
      const status = (o.status || 'scheduled').toLowerCase();
      const date   = o.observation_date || o.date || '—';
      const isPast = date < new Date().toISOString().split('T')[0];
      return `<tr>
        <td>${this._esc(date)}</td>
        <td>${this._esc(o.observation_time || o.time || '—')}</td>
        <td class="fw-semibold">${this._esc(o.class_name || o.class || '—')}</td>
        <td>${this._esc(o.subject || '—')}</td>
        <td>${this._esc(o.observer_name || o.observer || '—')}</td>
        <td><span class="badge bg-${statusCls[status] || 'secondary'}">${this._esc(o.status || 'scheduled')}</span></td>
        <td>
          ${status === 'scheduled' && isPast
            ? `<button class="btn btn-sm btn-outline-success" onclick="observationScheduleController.markCompleted(${o.id})">Mark Done</button>`
            : ''}
        </td>
      </tr>`;
    }).join('');
  },

  showModal: function () {
    ['osObsDate', 'osObsTime', 'osClass', 'osSubject', 'osObserver'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    this._modal.show();
  },

  save: async function () {
    const date     = document.getElementById('osObsDate')?.value;
    const time     = document.getElementById('osObsTime')?.value;
    const cls      = document.getElementById('osClass')?.value.trim();
    const subject  = document.getElementById('osSubject')?.value.trim();
    const observer = document.getElementById('osObserver')?.value.trim();

    if (!date || !cls || !subject) {
      showNotification('Date, class, and subject are required.', 'warning');
      return;
    }
    try {
      await callAPI('/staff/observation-schedule', 'POST', {
        observation_date: date, observation_time: time || null,
        class_name: cls, subject, observer_name: observer || null, status: 'scheduled',
      });
      showNotification('Observation scheduled.', 'success');
      this._modal.hide();
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Failed to schedule.', 'danger');
    }
  },

  markCompleted: async function (id) {
    if (!confirm('Mark this observation as completed?')) return;
    try {
      await callAPI('/staff/observation-schedule/' + id, 'PUT', { status: 'completed' });
      showNotification('Marked as completed.', 'success');
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Update failed.', 'danger');
    }
  },

  _show: function (id) {
    ['osLoading', 'osContent', 'osEmpty'].forEach(el => {
      const e = document.getElementById(el);
      if (e) e.style.display = el === id ? '' : 'none';
    });
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => observationScheduleController.init());
