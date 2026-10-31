/**
 * Observation Feedback Controller
 * Display feedback received from classroom observation sessions.
 * Displays the single rating and narrative fields stored by lesson_observations.
 * API: GET /staff/observation-feedback
 * Integrates with AcademicContext for academic year awareness
 */
const observationFeedbackController = {
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
    const loading  = document.getElementById('ofLoading');
    const list     = document.getElementById('ofFeedbackList');
    const empty    = document.getElementById('ofEmpty');
    if (loading) loading.style.display = '';
    if (list)   list.innerHTML = '';
    if (empty)  empty.style.display = 'none';

    try {
      const r = await callAPI('/staff/observation-feedback', 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._setStats();
      this._render();
    } catch (e) {
      if (loading) loading.style.display = 'none';
      if (list) list.innerHTML = `<div class="col-12"><div class="alert alert-danger">Failed to load feedback: ${this._esc(e.message)}</div></div>`;
    }
  },

  _setStats: function () {
    const ratings  = this._data.map(f => Number(f.rating || 0)).filter(v => v > 0);
    const avg      = ratings.length ? (ratings.reduce((s, v) => s + v, 0) / ratings.length).toFixed(1) : '—';

    this._set('ofStatTotal',     this._data.length);
    this._set('ofStatThisTerm',  this._data.filter(f => this._isCurrentTerm(f.observation_date)).length);
    this._set('ofStatAvgRating', avg);
  },

  _isCurrentTerm: function (dateValue) {
    if (!dateValue) return false;
    const date = new Date(dateValue);
    const context = window.AcademicContext;
    const start = context?.state?.termStartDate;
    const end = context?.state?.termEndDate;
    if (start && end) return date >= new Date(start) && date <= new Date(end);
    return false;
  },

  _render: function () {
    const loading = document.getElementById('ofLoading');
    const list    = document.getElementById('ofFeedbackList');
    const empty   = document.getElementById('ofEmpty');
    if (loading) loading.style.display = 'none';

    if (!this._data.length) {
      if (empty) empty.style.display = '';
      return;
    }

    const ratingBadge = value => {
      const v = Number(value || 0);
      if (!v) return '<span class="badge bg-secondary">—</span>';
      const cls = v >= 3.5 ? 'success' : v >= 2.5 ? 'primary' : v >= 1.5 ? 'warning' : 'danger';
      const lbl = v >= 3.5 ? 'Excellent' : v >= 2.5 ? 'Good' : v >= 1.5 ? 'Developing' : 'Needs Work';
      return `<span class="badge bg-${cls}">${Number(v).toFixed(1)} — ${lbl}</span>`;
    };

    list.innerHTML = this._data.map(f => {
      return `<div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
            <div>
              <div class="fw-semibold">${this._esc(f.class_name || (f.class_id ? `Class ${f.class_id}` : 'Class not recorded'))}</div>
              <div class="text-muted small">${this._esc(f.learning_area_name || f.subject_name || (f.learning_area_id ? `Learning area ${f.learning_area_id}` : ''))} · ${this._esc(f.observation_date || '—')}</div>
            </div>
            ${ratingBadge(f.rating)}
          </div>
          <div class="card-body">
            ${f.strengths ? `<div class="mt-2"><span class="fw-semibold small text-success">Strengths:</span> <span class="small">${this._esc(f.strengths)}</span></div>` : ''}
            ${f.areas_for_improvement ? `<div class="mt-1"><span class="fw-semibold small text-warning">Areas for improvement:</span> <span class="small">${this._esc(f.areas_for_improvement)}</span></div>` : ''}
            ${f.recommendations ? `<div class="mt-1"><span class="fw-semibold small">Recommendations:</span> <span class="small">${this._esc(f.recommendations)}</span></div>` : ''}
            ${f.feedback ? `<div class="mt-2 p-2 bg-light rounded small text-muted">${this._esc(f.feedback.substring(0, 300))}</div>` : ''}
          </div>
          <div class="card-footer bg-transparent small text-muted">
            Observer staff ID: ${this._esc(f.observer_id || '—')} · ${this._esc(f.status || '—')}
          </div>
        </div>
      </div>`;
    }).join('');
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => observationFeedbackController.init());

window.observationFeedbackController = observationFeedbackController;
