/**
 * Observation Schedule Controller
 * View classroom observation sessions for the signed-in staff member.
 * API: GET /staff/observation-schedule
 * Integrates with AcademicContext for academic year awareness
 */
const observationScheduleController = {
  _data: [],
  _currentAcademicYear: null,
  _currentTerm: null,

  init: async function () {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    
    // Initialize Academic Context if available
    if (window.AcademicContext) {
      // Subscribe to context changes
      window.AcademicContext.subscribe((context, event, data) => {
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          this._load();
        }
      });
      
      // Ensure context is loaded
      if (!window.AcademicContext.isLoaded()) {
        await window.AcademicContext.init();
      }
      
      // Get current academic context
      this._currentAcademicYear = window.AcademicContext.getAcademicYearId();
      this._currentTerm = window.AcademicContext.getTermId();
    }
    
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
      const message = document.querySelector('#osEmpty p');
      if (message) message.textContent = 'Could not load observation records. Refresh to try again.';
      console.error('Observation schedule failed:', e);
      window.showNotification?.('Could not load observation records. Refresh to try again.', 'danger');
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
      return `<tr>
        <td>${this._esc(date)}</td>
        <td>${this._esc(o.learning_area_name || (o.learning_area_id ? `Learning area ${o.learning_area_id}` : '—'))}</td>
        <td class="fw-semibold">${this._esc(o.class_name || (o.class_id ? `Class ${o.class_id}` : '—'))}</td>
        <td>${this._esc(o.observer_name || (o.observer_id ? `Staff ${o.observer_id}` : '—'))}</td>
        <td><span class="badge bg-${statusCls[status] || 'secondary'}">${this._esc(o.status || 'scheduled')}</span></td>
      </tr>`;
    }).join('');
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

window.observationScheduleController = observationScheduleController;
