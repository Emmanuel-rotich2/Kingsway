/**
 * Past Papers Controller
 * Library of past exam papers filterable by subject, year, and class level.
 * API base: /api/academic/resources?type=past_paper
 * Integrates with AcademicContext for academic year awareness
 */

const pastPapersController = {
  _currentAcademicYear: null,
  _currentTerm: null,
  _papers: [],

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    
    // Initialize Academic Context if available
    if (window.AcademicContext) {
      // Subscribe to context changes
      window.AcademicContext.subscribe((context, event, data) => {
        console.log('AcademicContext changed in past_papers:', event, data);
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          // Reload papers when academic year or term changes
          this.loadPapers();
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
    
    await Promise.all([
      this._loadSubjectDropdown(),
      this._loadYearDropdown(),
    ]);
    this.loadPapers();
  },

  // ── LOAD SUBJECTS ──────────────────────────────────────────────────

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

  // ── LOAD YEARS ─────────────────────────────────────────────────────

  _loadYearDropdown: async function () {
    try {
      const r = await callAPI('/academic/years', 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('ppYear');
      if (!sel) return;
      items.forEach(y => {
        const o = document.createElement('option');
        o.value = y.id || y.year_code || y.year_name;
        o.textContent = this._esc(y.year_code || y.year_name || y.name || '');
        sel.appendChild(o);
      });
    } catch (e) { console.warn('Could not load years:', e); }
  },

  // ── LOAD PAPERS ───────────────────────────────────────────────────

  loadPapers: async function () {
    const container = document.getElementById('ppTableContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><div class="text-muted mt-2">Loading past papers…</div></div>';
    try {
      const params = this._buildParams();
      const r = await callAPI('/academic/resources?type=past_paper' + params, 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._papers = items;
      
      document.getElementById('ppTotalCount').textContent = items.length + ' papers';
      
      if (!items.length) {
        container.innerHTML = `
          <div class="text-center py-5">
            <i class="bi bi-files fs-1 text-muted"></i>
            <p class="text-muted mt-3">No past papers found. Try adjusting your filters.</p>
          </div>`;
        return;
      }
      container.innerHTML = `<div class="table-responsive"><table class="table table-hover align-middle mb-0">${this._renderTable(items)}</table></div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load past papers: ${this._esc(e.message)}</div>`;
    }
  },

  filter: function () {
    this.loadPapers();
  },

  // ── RENDER TABLE ────────────────────────────────────────────────────

  _renderTable: function (items) {
    return `
      <thead class="table-light">
        <tr>
          <th>Title</th>
          <th>Subject</th>
          <th>Year</th>
          <th>Class Level</th>
          <th>Type</th>
          <th>Uploaded By</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        ${items.map(p => this._renderRow(p)).join('')}
      </tbody>
    `;
  },

  _renderRow: function (p) {
    const date = p.created_at ? new Date(p.created_at).toLocaleDateString() : '—';
    return `
      <tr>
        <td><strong>${this._esc(p.title || 'Untitled')}</strong></td>
        <td>${this._esc(p.subject_name || p.learning_area || '—')}</td>
        <td>${this._esc(p.exam_year || p.year || '—')}</td>
        <td>${this._esc(p.class_level || '—')}</td>
        <td><span class="badge bg-info">${this._esc(p.exam_type || p.type || '—')}</span></td>
        <td>${this._esc(p.uploaded_by_name || p.uploaded_by || '—')}</td>
        <td>${date}</td>
        <td>
          <button class="btn btn-sm btn-outline-success" onclick="pastPapersController.download(${p.id})">
            <i class="bi bi-download"></i> Download
          </button>
        </td>
      </tr>
    `;
  },

  // ── DOWNLOAD ───────────────────────────────────────────────────────

  download: function (id) {
    window.location.href = (window.APP_BASE || '') + '/api/academic/resources/download/' + id;
  },

  // ── HELPERS ────────────────────────────────────────────────────────

  _buildParams: function () {
    const q     = document.getElementById('ppSearch')?.value.trim()  || '';
    const subj  = document.getElementById('ppSubject')?.value         || '';
    const year  = document.getElementById('ppYear')?.value            || '';
    const level = document.getElementById('ppClassLevel')?.value       || '';
    const type  = document.getElementById('ppType')?.value            || '';
    const parts = [];
    if (q)     parts.push('search='  + encodeURIComponent(q));
    if (subj)  parts.push('subject=' + encodeURIComponent(subj));
    if (year)  parts.push('year='    + encodeURIComponent(year));
    if (level) parts.push('class_level=' + encodeURIComponent(level));
    if (type)  parts.push('exam_type=' + encodeURIComponent(type));
    return parts.length ? '&' + parts.join('&') : '';
  },

  _esc: function (s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },
};
