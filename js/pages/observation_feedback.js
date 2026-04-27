/**
 * Observation Feedback Controller
 * Display feedback received from classroom observation sessions.
 * Ratings: Planning, Delivery, Classroom Management, Student Engagement, Assessment.
 * API: GET /staff/observation-feedback
 */
const observationFeedbackController = {
  _data: [],

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
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
    const term     = this._data.length; // simplified: all as "this term"
    const ratings  = this._data.map(f => this._avgRating(f)).filter(v => v > 0);
    const avg      = ratings.length ? (ratings.reduce((s, v) => s + v, 0) / ratings.length).toFixed(1) : '—';

    this._set('ofStatTotal',     this._data.length);
    this._set('ofStatThisTerm',  term);
    this._set('ofStatAvgRating', avg);
  },

  _avgRating: function (f) {
    const dims = ['planning', 'delivery', 'classroom_management', 'student_engagement', 'assessment'];
    const vals = dims.map(d => Number(f[d] || f['rating_' + d] || 0)).filter(v => v > 0);
    return vals.length ? vals.reduce((s, v) => s + v, 0) / vals.length : 0;
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

    const dims = [
      { key: 'planning',             label: 'Planning' },
      { key: 'delivery',             label: 'Content Delivery' },
      { key: 'classroom_management', label: 'Classroom Mgmt' },
      { key: 'student_engagement',   label: 'Student Engagement' },
      { key: 'assessment',           label: 'Assessment' },
    ];

    const ratingBadge = v => {
      if (!v) return '<span class="badge bg-secondary">—</span>';
      const cls = v >= 3.5 ? 'success' : v >= 2.5 ? 'primary' : v >= 1.5 ? 'warning' : 'danger';
      const lbl = v >= 3.5 ? 'Excellent' : v >= 2.5 ? 'Good' : v >= 1.5 ? 'Developing' : 'Needs Work';
      return `<span class="badge bg-${cls}">${Number(v).toFixed(1)} — ${lbl}</span>`;
    };

    list.innerHTML = this._data.map(f => {
      const avg = this._avgRating(f);
      const dimRows = dims.map(d => {
        const val = Number(f[d.key] || f['rating_' + d.key] || 0);
        const pct = val > 0 ? (val / 4) * 100 : 0;
        return `<div class="d-flex align-items-center gap-2 mb-1">
          <span class="text-muted small" style="width:130px;">${d.label}</span>
          <div class="progress flex-grow-1" style="height:6px;">
            <div class="progress-bar ${pct >= 75 ? 'bg-success' : pct >= 50 ? 'bg-primary' : 'bg-warning'}" style="width:${pct}%"></div>
          </div>
          <span class="small fw-semibold">${val > 0 ? val + '/4' : '—'}</span>
        </div>`;
      }).join('');

      return `<div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
            <div>
              <div class="fw-semibold">${this._esc(f.class_name || f.class || 'Unknown class')}</div>
              <div class="text-muted small">${this._esc(f.subject || '')} · ${this._esc(f.observation_date || f.date || '—')}</div>
            </div>
            ${ratingBadge(avg)}
          </div>
          <div class="card-body">
            ${dimRows}
            ${f.strengths ? `<div class="mt-2"><span class="fw-semibold small text-success">Strengths:</span> <span class="small">${this._esc(f.strengths)}</span></div>` : ''}
            ${f.improvements ? `<div class="mt-1"><span class="fw-semibold small text-warning">Improve:</span> <span class="small">${this._esc(f.improvements)}</span></div>` : ''}
            ${f.comments || f.notes ? `<div class="mt-2 p-2 bg-light rounded small text-muted">${this._esc((f.comments || f.notes || '').substring(0, 150))}</div>` : ''}
          </div>
          <div class="card-footer bg-transparent small text-muted">
            Observer: ${this._esc(f.observer_name || f.observer || '—')}
          </div>
        </div>
      </div>`;
    }).join('');
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => observationFeedbackController.init());
