/**
 * Teaching Materials Controller
 * Searchable grid of shared teaching resources.
 * API base: /api/academic/resources?type=material
 */

const teachingMaterialsController = {

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([
      this._loadSubjectDropdown(),
      this._loadClassDropdown(),
    ]);
    this.loadMaterials();
  },

  // ── LOAD SUBJECTS ──────────────────────────────────────────────────

  _loadSubjectDropdown: async function () {
    try {
      const r = await callAPI('/academic/learning-areas/list', 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('tmSubject');
      if (!sel) return;
      items.forEach(s => {
        const o = document.createElement('option');
        o.value = s.id || s.name;
        o.textContent = this._esc(s.name || s.subject_name || s.learning_area_name || '');
        sel.appendChild(o);
      });
    } catch (e) { console.warn('Could not load subjects:', e); }
  },

  // ── LOAD CLASSES ───────────────────────────────────────────────────

  _loadClassDropdown: async function () {
    try {
      const r = await callAPI('/academic/classes?status=active', 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('tmClass');
      if (!sel) return;
      items.forEach(c => {
        const o = document.createElement('option');
        o.value = c.id || c.name;
        o.textContent = this._esc(c.name || c.class_name || '');
        sel.appendChild(o);
      });
    } catch (e) { console.warn('Could not load classes:', e); }
  },

  // ── LOAD MATERIALS ─────────────────────────────────────────────────

  loadMaterials: async function () {
    const grid = document.getElementById('tmGrid');
    if (!grid) return;
    grid.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><div class="text-muted mt-2">Loading materials…</div></div>';
    try {
      const params = this._buildParams();
      const r = await callAPI('/academic/resources?type=material' + params, 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      if (!items.length) {
        grid.innerHTML = `
          <div class="text-center py-5">
            <i class="bi bi-folder2 fs-1 text-muted"></i>
            <p class="text-muted mt-3">No materials found. Try adjusting your filters or <a href="${(window.APP_BASE || '')}/home.php?route=upload_teaching_resource">upload a new resource</a>.</p>
          </div>`;
        return;
      }
      grid.innerHTML = `<div class="row row-cols-1 row-cols-md-3 g-3">${items.map(m => this._renderCard(m)).join('')}</div>`;
    } catch (e) {
      grid.innerHTML = `<div class="alert alert-danger">Failed to load materials: ${this._esc(e.message)}</div>`;
    }
  },

  filter: function () {
    this.loadMaterials();
  },

  // ── RENDER CARD ────────────────────────────────────────────────────

  _renderCard: function (m) {
    const icon  = this._typeIcon(m.resource_type || m.type || 'Other');
    const color = this._typeColor(m.resource_type || m.type || 'Other');
    const size  = m.file_size ? this._formatSize(m.file_size) : '—';
    const date  = m.created_at ? new Date(m.created_at).toLocaleDateString() : '—';
    return `
      <div class="col">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-start gap-3">
              <div class="flex-shrink-0">
                <span class="badge ${color} p-2 fs-4"><i class="bi ${icon}"></i></span>
              </div>
              <div class="flex-grow-1 overflow-hidden">
                <h6 class="card-title mb-1 text-truncate" title="${this._esc(m.title || '')}">${this._esc(m.title || 'Untitled')}</h6>
                <div class="text-muted small mb-1">
                  <i class="bi bi-book me-1"></i>${this._esc(m.subject_name || m.learning_area || '—')}
                  &nbsp;·&nbsp;
                  <i class="bi bi-people me-1"></i>${this._esc(m.class_name || '—')}
                </div>
                <div class="text-muted small">
                  <i class="bi bi-person me-1"></i>${this._esc(m.uploaded_by_name || m.uploaded_by || '—')}
                  &nbsp;·&nbsp;
                  <i class="bi bi-calendar me-1"></i>${date}
                  &nbsp;·&nbsp; ${size}
                </div>
              </div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-top-0 pt-0 pb-2 px-3">
            <button class="btn btn-sm btn-outline-primary w-100"
                    onclick="teachingMaterialsController.download(${m.id}, ${JSON.stringify(m.file_name || m.title || 'file')})">
              <i class="bi bi-download me-1"></i> Download
            </button>
          </div>
        </div>
      </div>`;
  },

  // ── DOWNLOAD ───────────────────────────────────────────────────────

  download: function (id, filename) {
    window.location.href = (window.APP_BASE || '') + '/api/academic/resources/' + id + '/download';
  },

  // ── HELPERS ────────────────────────────────────────────────────────

  _buildParams: function () {
    const q     = document.getElementById('tmSearch')?.value.trim()  || '';
    const subj  = document.getElementById('tmSubject')?.value         || '';
    const cls   = document.getElementById('tmClass')?.value           || '';
    const type  = document.getElementById('tmType')?.value            || '';
    const parts = [];
    if (q)    parts.push('search='  + encodeURIComponent(q));
    if (subj) parts.push('subject=' + encodeURIComponent(subj));
    if (cls)  parts.push('class='   + encodeURIComponent(cls));
    if (type) parts.push('resource_type=' + encodeURIComponent(type));
    return parts.length ? '&' + parts.join('&') : '';
  },

  _typeIcon: function (type) {
    const icons = {
      'Worksheet':     'bi-file-earmark-text',
      'Notes':         'bi-journal-text',
      'Presentation':  'bi-file-earmark-slides',
      'Video':         'bi-camera-video',
      'Past Paper':    'bi-file-earmark-ruled',
      'Other':         'bi-file-earmark',
    };
    return icons[type] || 'bi-file-earmark';
  },

  _typeColor: function (type) {
    const colors = {
      'Worksheet':     'bg-primary bg-opacity-10 text-primary',
      'Notes':         'bg-success bg-opacity-10 text-success',
      'Presentation':  'bg-warning bg-opacity-10 text-warning',
      'Video':         'bg-danger bg-opacity-10 text-danger',
      'Past Paper':    'bg-info bg-opacity-10 text-info',
      'Other':         'bg-secondary bg-opacity-10 text-secondary',
    };
    return colors[type] || 'bg-secondary bg-opacity-10 text-secondary';
  },

  _formatSize: function (bytes) {
    if (bytes < 1024)        return bytes + ' B';
    if (bytes < 1048576)     return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  },

  _set: function (id, val) { const el = document.getElementById(id); if (el) el.textContent = val; },
  _esc: function (s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },
};
