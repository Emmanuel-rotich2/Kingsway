/**
 * Past Papers Controller
 * Library of past exam papers filterable by subject, year, and class level.
 * API base: /api/academic/resources?type=past_paper
 */

const pastPapersController = {

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([
      this._loadSubjectDropdown(),
      this._loadYearDropdown(),
    ]);
    this.loadPapers();
  },

  // ── DROPDOWNS ──────────────────────────────────────────────────────

  _loadSubjectDropdown: async function () {
    try {
      const r = await callAPI('/academic/learning-areas/list', 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('ppSubject');
      if (!sel) return;
      items.forEach(s => {
        const o = document.createElement('option');
        o.value = s.id || s.name;
        o.textContent = this._esc(s.name || s.subject_name || s.learning_area_name || '');
        sel.appendChild(o);
      });
    } catch (e) { console.warn('Could not load subjects:', e); }
  },

  _loadYearDropdown: function () {
    const sel = document.getElementById('ppYear');
    if (!sel) return;
    const current = new Date().getFullYear();
    for (let y = current; y >= current - 7; y--) {
      const o = document.createElement('option');
      o.value = y;
      o.textContent = y;
      sel.appendChild(o);
    }
  },

  // ── LOAD PAPERS ────────────────────────────────────────────────────

  loadPapers: async function () {
    const container = document.getElementById('ppTableContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><div class="text-muted mt-2">Loading past papers…</div></div>';
    try {
      const params = this._buildParams();
      const r = await callAPI('/academic/resources?type=past_paper' + params, 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      // Update count badge
      this._set('ppTotalCount', items.length + ' paper' + (items.length !== 1 ? 's' : ''));

      if (!items.length) {
        container.innerHTML = `
          <div class="text-center py-5">
            <i class="bi bi-files fs-1 text-muted"></i>
            <p class="text-muted mt-3">No past papers found. Try adjusting your filters.</p>
          </div>`;
        return;
      }

      const rows = items.map((p, i) => {
        const date = p.created_at ? new Date(p.created_at).getFullYear() : (p.year || '—');
        return `<tr>
          <td class="text-muted">${i + 1}</td>
          <td class="fw-semibold">${this._esc(p.subject_name || p.learning_area || '—')}</td>
          <td><span class="badge ${this._typeBadge(p.exam_type || p.resource_type || p.type)}">${this._esc(p.exam_type || p.resource_type || p.type || '—')}</span></td>
          <td>${this._esc(String(p.year || date))}</td>
          <td>${this._esc(p.term ? 'Term ' + p.term : '—')}</td>
          <td>${this._esc(p.class_level || p.class_name || '—')}</td>
          <td>${this._esc(p.pages ? p.pages + ' pg' : '—')}</td>
          <td>
            <button class="btn btn-sm btn-outline-primary me-1"
                    onclick="pastPapersController.download(${p.id})">
              <i class="bi bi-download"></i> Download
            </button>
            <button class="btn btn-sm btn-outline-secondary"
                    onclick="pastPapersController.preview(${p.id})">
              <i class="bi bi-eye"></i> Preview
            </button>
          </td>
        </tr>`;
      }).join('');

      container.innerHTML = `
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:40px">#</th>
                <th>Subject</th>
                <th>Exam Type</th>
                <th>Year</th>
                <th>Term</th>
                <th>Class Level</th>
                <th>Pages</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger m-3">Failed to load papers: ${this._esc(e.message)}</div>`;
    }
  },

  filter: function () {
    this.loadPapers();
  },

  // ── ACTIONS ────────────────────────────────────────────────────────

  download: function (id) {
    window.location.href = (window.APP_BASE || '') + '/api/academic/resources/' + id + '/download';
  },

  preview: function (id) {
    window.open((window.APP_BASE || '') + '/api/academic/resources/' + id + '/download', '_blank');
  },

  // ── HELPERS ────────────────────────────────────────────────────────

  _buildParams: function () {
    const q     = document.getElementById('ppSearch')?.value.trim()    || '';
    const subj  = document.getElementById('ppSubject')?.value           || '';
    const year  = document.getElementById('ppYear')?.value              || '';
    const level = document.getElementById('ppClassLevel')?.value        || '';
    const type  = document.getElementById('ppType')?.value              || '';
    const parts = [];
    if (q)     parts.push('search='      + encodeURIComponent(q));
    if (subj)  parts.push('subject='     + encodeURIComponent(subj));
    if (year)  parts.push('year='        + encodeURIComponent(year));
    if (level) parts.push('class_level=' + encodeURIComponent(level));
    if (type)  parts.push('exam_type='   + encodeURIComponent(type));
    return parts.length ? '&' + parts.join('&') : '';
  },

  _typeBadge: function (type) {
    const map = {
      'Mid-Term':  'bg-primary',
      'End-Term':  'bg-success',
      'Mock':      'bg-warning text-dark',
      'KNEC':      'bg-danger',
    };
    return map[type] || 'bg-secondary';
  },

  _set: function (id, val) { const el = document.getElementById(id); if (el) el.textContent = val; },
  _esc: function (s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },
};
