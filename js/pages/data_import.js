/**
 * Data Import Controller — data_import.php
 * Wizard: Select type → Upload → Preview & Validate → Confirm → Results
 * API: /api/import/types, /api/import/preview, /api/import/execute, /api/import/template, /api/import/logs
 */
const dataImportController = {

  // ── State ─────────────────────────────────────────────────────────────────
  _step:        1,
  _type:        null,
  _typeLabel:   '',
  _file:        null,
  _previewData: null,
  _logsModal:   null,
  _allTypes:    {},

  CATEGORIES: {
    students:  { icon: 'bi-people-fill',        color: 'primary',  label: 'Students' },
    staff:     { icon: 'bi-person-badge-fill',   color: 'success',  label: 'Staff' },
    financial: { icon: 'bi-cash-coin',           color: 'warning',  label: 'Financial' },
    academic:  { icon: 'bi-book-fill',           color: 'info',     label: 'Academic' },
    inventory: { icon: 'bi-box-seam-fill',       color: 'secondary',label: 'Inventory' },
  },

  // ── Init ──────────────────────────────────────────────────────────────────
  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._logsModal = new bootstrap.Modal(document.getElementById('diLogsModal'));
    this._bindDrop();
    await this._loadTypes();
  },

  // ── Step navigation ───────────────────────────────────────────────────────
  goStep: function (step) {
    this._step = step;
    for (let i = 1; i <= 5; i++) {
      const el = document.getElementById('diStep' + i);
      if (el) el.style.display = i === step ? '' : 'none';
    }
    document.querySelectorAll('.di-step').forEach(el => {
      const s = parseInt(el.dataset.step);
      el.classList.toggle('active', s === step);
      el.classList.toggle('done',   s < step);
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  },

  reset: function () {
    this._type = null; this._typeLabel = ''; this._file = null; this._previewData = null;
    this.clearFile();
    this.goStep(1);
  },

  // ── STEP 1: Load and render type cards ───────────────────────────────────
  _loadTypes: async function () {
    const grid = document.getElementById('diCategoryCards');
    try {
      const r = await callAPI('/import/types', 'GET');
      this._allTypes = r?.data ?? {};
      this._renderTypeCards();
    } catch (e) {
      if (grid) grid.innerHTML = `<div class="col-12"><div class="alert alert-danger">Failed to load import types: ${this._esc(e.message)}</div></div>`;
    }
  },

  _renderTypeCards: function () {
    const grid = document.getElementById('diCategoryCards');
    if (!grid) return;
    grid.innerHTML = Object.entries(this._allTypes).map(([cat, types]) => {
      const meta = this.CATEGORIES[cat] || { icon: 'bi-grid', color: 'secondary', label: cat };
      const typeButtons = types.map(t => `
        <div class="p-2 border rounded mb-1 di-type-btn" id="diType_${t.type}"
             onclick="dataImportController.selectType('${t.type}','${this._esc(t.label)}')">
          <div class="d-flex justify-content-between align-items-center">
            <span class="small fw-semibold">${this._esc(t.label)}</span>
            <i class="bi bi-chevron-right text-muted small"></i>
          </div>
          <div class="text-muted" style="font-size:11px;">Required: ${t.required.slice(0,3).join(', ')}${t.required.length>3?'…':''}</div>
        </div>`).join('');
      return `<div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm di-category-card h-100">
          <div class="card-header bg-${meta.color} bg-opacity-10 border-0 py-2">
            <span class="fw-semibold text-${meta.color}">
              <i class="bi ${meta.icon} me-2"></i>${meta.label}
            </span>
          </div>
          <div class="card-body py-2">${typeButtons}</div>
        </div>
      </div>`;
    }).join('');
  },

  selectType: function (type, label) {
    this._type = type; this._typeLabel = label;
    // Highlight selection
    document.querySelectorAll('.di-type-btn').forEach(el => el.classList.remove('selected'));
    document.getElementById('diType_' + type)?.classList.add('selected');

    // Find required cols for this type
    const allMeta = Object.values(this._allTypes).flat().find(t => t.type === type);
    const required = allMeta?.required ?? [];

    // Update step 2 info
    const badge = document.getElementById('diSelectedTypeBadge');
    if (badge) badge.textContent = label;

    const dlBtn = document.getElementById('diDownloadTemplateBtn');
    if (dlBtn) dlBtn.textContent = `Download ${label} Template`;

    const colList = document.getElementById('diRequiredColsList');
    if (colList) colList.innerHTML = required.map(c => `<span class="badge bg-light text-dark border">${c}</span>`).join('');

    setTimeout(() => this.goStep(2), 250);
  },

  // ── STEP 2: File upload ───────────────────────────────────────────────────
  _bindDrop: function () {
    const zone  = document.getElementById('diDropZone');
    const input = document.getElementById('diFileInput');
    if (!zone || !input) return;

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.background = '#e9f0ff'; });
    zone.addEventListener('dragleave', () => { zone.style.background = ''; });
    zone.addEventListener('drop', e => {
      e.preventDefault(); zone.style.background = '';
      const file = e.dataTransfer.files[0];
      if (file) this._selectFile(file);
    });
    input.addEventListener('change', e => { if (e.target.files[0]) this._selectFile(e.target.files[0]); });
  },

  _selectFile: function (file) {
    const allowed = ['csv','xlsx','xls'];
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (!allowed.includes(ext)) {
      showNotification('Only CSV and Excel files are supported.', 'warning');
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      showNotification('File exceeds 10 MB limit.', 'warning');
      return;
    }
    this._file = file;
    document.getElementById('diFileInfo')?.classList.remove('d-none');
    this._set('diFileName', file.name);
    this._set('diFileSize', this._formatSize(file.size));
    const iconEl = document.getElementById('diFileIcon');
    if (iconEl) iconEl.className = `bi ${ext === 'csv' ? 'bi-filetype-csv' : 'bi-file-earmark-excel'} fs-2 text-primary`;
    const previewBtn = document.getElementById('diPreviewBtn');
    if (previewBtn) previewBtn.disabled = false;
    const errEl = document.getElementById('diUploadError');
    if (errEl) errEl.classList.add('d-none');
  },

  clearFile: function () {
    this._file = null;
    document.getElementById('diFileInput').value = '';
    document.getElementById('diFileInfo')?.classList.add('d-none');
    const btn = document.getElementById('diPreviewBtn');
    if (btn) btn.disabled = true;
  },

  downloadTemplate: function () {
    if (!this._type) { showNotification('Select an import type first.', 'warning'); return; }
    const base = (window.APP_BASE || '').replace(/\/$/, '');
    window.open(base + '/api/import/template?type=' + this._type, '_blank');
  },

  // ── STEP 3: Preview & validate ────────────────────────────────────────────
  runPreview: async function () {
    if (!this._file || !this._type) return;
    const btn = document.getElementById('diPreviewBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div> Validating…'; }

    const formData = new FormData();
    formData.append('file', this._file);
    formData.append('type', this._type);

    const errEl = document.getElementById('diUploadError');
    if (errEl) errEl.classList.add('d-none');

    try {
      const token = AuthContext.getToken ? AuthContext.getToken() : (AuthContext.token || localStorage.getItem('auth_token') || '');
      const base  = (window.APP_BASE || '').replace(/\/$/, '');
      const res   = await fetch(base + '/api/import/preview', {
        method: 'POST',
        headers: { Authorization: 'Bearer ' + token },
        body: formData,
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(json.message || 'Preview failed');

      this._previewData = json.data ?? json;
      this._renderPreview(this._previewData);
      this.goStep(3);
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Preview failed.'; errEl.classList.remove('d-none'); }
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-eye me-1"></i> Preview & Validate'; }
    }
  },

  _renderPreview: function (data) {
    const totalRows = data.total_rows ?? 0;
    const errors    = data.errors ?? [];
    const missing   = data.missing_cols ?? [];
    const errorRows = [...new Set(errors.map(e => e.row))].length;
    const validRows = totalRows - errorRows;

    this._set('diValTotal',   totalRows);
    this._set('diValValid',   validRows);
    this._set('diValErrors',  errorRows);
    this._set('diValMissing', missing.length);
    this._set('diPreviewCountBadge', `${totalRows} total rows`);

    // Missing columns alert
    const missingAlert = document.getElementById('diMissingColsAlert');
    if (missingAlert) {
      if (missing.length) {
        document.getElementById('diMissingColsList').textContent = missing.join(', ');
        missingAlert.classList.remove('d-none');
      } else {
        missingAlert.classList.add('d-none');
      }
    }

    // Preview table
    const head = document.getElementById('diPreviewHead');
    const body = document.getElementById('diPreviewBody');
    const headers = data.headers ?? [];
    const rows    = data.preview_rows ?? [];

    if (head && headers.length) {
      head.innerHTML = '<tr>' + ['#', ...headers].map(h => `<th class="text-nowrap">${this._esc(h)}</th>`).join('') + '</tr>';
    }
    if (body && rows.length) {
      // Build error map by row number
      const errorMap = {};
      errors.forEach(e => { errorMap[e.row] = errorMap[e.row] || []; errorMap[e.row].push(e.field); });
      body.innerHTML = rows.map((row, i) => {
        const rowNum = i + 1;
        const hasErr = errorMap[rowNum];
        const cells  = headers.map(h => `<td class="${hasErr?.includes(h) ? 'table-danger' : ''}">${this._esc(row[h] ?? '')}</td>`).join('');
        return `<tr class="${hasErr ? 'table-warning' : ''}"><td class="text-muted small">${rowNum}</td>${cells}</tr>`;
      }).join('');
    }

    // Errors section
    const errSec = document.getElementById('diErrorsSection');
    const errBody = document.getElementById('diErrorsBody');
    if (errSec && errBody) {
      if (errors.length) {
        errSec.classList.remove('d-none');
        errBody.innerHTML = errors.map(e => `<tr>
          <td class="fw-bold text-danger">${e.row}</td>
          <td><code>${this._esc(e.field)}</code></td>
          <td class="small">${this._esc(e.message)}</td>
        </tr>`).join('');
      } else {
        errSec.classList.add('d-none');
      }
    }

    // Buttons
    const confirmBtn = document.getElementById('diConfirmBtn');
    const proceedBtn = document.getElementById('diProceedWithErrors');
    if (missing.length) {
      if (confirmBtn) confirmBtn.style.display = 'none';
      if (proceedBtn) proceedBtn.style.display = 'none';
    } else if (errors.length && validRows > 0) {
      if (confirmBtn) confirmBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Continue (valid rows only)';
      if (proceedBtn) proceedBtn.style.display = '';
    } else if (!errors.length) {
      if (confirmBtn) confirmBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> All Good — Continue';
      if (proceedBtn) proceedBtn.style.display = 'none';
    }
  },

  downloadErrorReport: function () {
    if (!this._previewData?.errors?.length) return;
    const rows = [['Row','Field','Error'], ...this._previewData.errors.map(e => [e.row, e.field, e.message])];
    const csv  = rows.map(r => r.map(c => `"${String(c).replace(/"/g,"''")}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob);
    a.download = `import_errors_${this._type}_${new Date().toISOString().split('T')[0]}.csv`;
    a.click(); URL.revokeObjectURL(a.href);
  },

  // ── STEP 4: Confirm ───────────────────────────────────────────────────────
  _renderConfirm: function () {
    const d = this._previewData;
    if (!d) return;
    const errRows = [...new Set((d.errors || []).map(e => e.row))].length;
    const valid   = (d.total_rows ?? 0) - errRows;
    const sumEl   = document.getElementById('diConfirmSummary');
    if (sumEl) sumEl.innerHTML = `
      <div class="col-md-3"><div class="card border-0 bg-primary bg-opacity-10 text-center p-3">
        <div class="fs-2 fw-bold text-primary">${d.total_rows ?? 0}</div><div class="small text-muted">Total Rows</div>
      </div></div>
      <div class="col-md-3"><div class="card border-0 bg-success bg-opacity-10 text-center p-3">
        <div class="fs-2 fw-bold text-success">${valid}</div><div class="small text-muted">Will Import</div>
      </div></div>
      <div class="col-md-3"><div class="card border-0 bg-danger bg-opacity-10 text-center p-3">
        <div class="fs-2 fw-bold text-danger">${errRows}</div><div class="small text-muted">Will Skip (errors)</div>
      </div></div>
      <div class="col-md-3"><div class="card border-0 bg-light text-center p-3">
        <div class="fs-5 fw-bold">${this._esc(this._typeLabel)}</div><div class="small text-muted">Import Type</div>
      </div></div>`;
  },

  // ── STEP 5: Execute import ────────────────────────────────────────────────
  runImport: async function () {
    if (!this._file || !this._type) return;
    this.goStep(5);
    this._renderConfirm(); // prep step 4 display in case of back
    const card = document.getElementById('diResultCard');

    const btn = document.getElementById('diRunImportBtn');
    if (btn) btn.disabled = true;

    const formData = new FormData();
    formData.append('file', this._file);
    formData.append('type', this._type);

    try {
      const token = AuthContext.getToken ? AuthContext.getToken() : (AuthContext.token || localStorage.getItem('auth_token') || '');
      const base  = (window.APP_BASE || '').replace(/\/$/, '');
      const res   = await fetch(base + '/api/import/execute', {
        method: 'POST',
        headers: { Authorization: 'Bearer ' + token },
        body: formData,
      });
      const json = await res.json().catch(() => ({}));
      this._renderResults(json.data ?? json, json.status);
    } catch (e) {
      if (card) card.innerHTML = `<div class="card-body"><div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>${this._esc(e.message || 'Import failed.')}</div></div>`;
    }
  },

  _renderResults: function (d, status) {
    const card = document.getElementById('diResultCard');
    if (!card) return;
    const isOk   = status === 'success' || d?.status === 'completed';
    const isPartial = d?.status === 'partial';
    const icon   = isOk ? 'bi-check-circle-fill text-success' : isPartial ? 'bi-exclamation-circle-fill text-warning' : 'bi-x-circle-fill text-danger';
    const heading = isOk ? 'Import Completed Successfully!' : isPartial ? 'Import Partially Completed' : 'Import Failed';

    let errTable = '';
    if (d?.errors?.length) {
      const rows = d.errors.slice(0, 50).map(e => `<tr><td>${e.row}</td><td><code>${this._esc(e.field)}</code></td><td class="small">${this._esc(e.message)}</td></tr>`).join('');
      errTable = `<div class="table-responsive mt-3" style="max-height:200px;overflow-y:auto;">
        <table class="table table-sm table-hover"><thead class="table-light"><tr><th>Row</th><th>Field</th><th>Error</th></tr></thead>
        <tbody>${rows}</tbody></table></div>`;
    }

    card.innerHTML = `<div class="card-body text-center py-4">
      <i class="bi ${icon} fs-1 mb-3 d-block"></i>
      <h4 class="fw-bold">${heading}</h4>
      <div class="row g-3 justify-content-center mt-2 mb-3">
        <div class="col-auto"><div class="card border-0 bg-success bg-opacity-10 px-4 py-3">
          <div class="fs-2 fw-bold text-success">${d?.success_rows ?? 0}</div><div class="small text-muted">Imported</div></div></div>
        <div class="col-auto"><div class="card border-0 bg-danger bg-opacity-10 px-4 py-3">
          <div class="fs-2 fw-bold text-danger">${d?.error_rows ?? 0}</div><div class="small text-muted">Errors</div></div></div>
        <div class="col-auto"><div class="card border-0 bg-warning bg-opacity-10 px-4 py-3">
          <div class="fs-2 fw-bold text-warning">${d?.skipped_rows ?? 0}</div><div class="small text-muted">Skipped</div></div></div>
      </div>
      ${errTable}
    </div>`;
  },

  // ── Import History ─────────────────────────────────────────────────────────
  showLogs: async function () {
    this._logsModal.show();
    const tbody = document.getElementById('diLogsBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const r = await callAPI('/import/logs', 'GET');
      const logs = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      if (!logs.length) { tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No imports yet.</td></tr>'; return; }
      const stCls = { completed:'success', partial:'warning', failed:'danger', preview:'secondary' };
      tbody.innerHTML = logs.map(l => `<tr>
        <td class="small text-muted">${l.id}</td>
        <td class="fw-semibold">${this._esc(l.import_type?.replace(/_/g,' ') ?? '—')}</td>
        <td class="small text-truncate" style="max-width:140px;">${this._esc(l.original_filename ?? '—')}</td>
        <td class="text-center">${l.total_rows ?? 0}</td>
        <td class="text-center text-success fw-semibold">${l.success_rows ?? 0}</td>
        <td class="text-center text-danger">${l.error_rows ?? 0}</td>
        <td><span class="badge bg-${stCls[l.status] || 'secondary'}">${this._esc(l.status ?? '—')}</span></td>
        <td class="small">${this._esc(l.imported_by_name ?? '—')}</td>
        <td class="small text-muted">${this._esc((l.created_at ?? '').split('T')[0] || '—')}</td>
      </tr>`).join('');
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-4">Failed to load history.</td></tr>`;
    }
  },

  // ── Override goStep to fill confirm page ──────────────────────────────────
  _originalGoStep: null,

  // ── Utilities ─────────────────────────────────────────────────────────────
  _formatSize: b => b < 1024 ? b + ' B' : b < 1048576 ? (b/1024).toFixed(1) + ' KB' : (b/1048576).toFixed(1) + ' MB',
  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

// Patch goStep to auto-render confirm when going to step 4
const _origGoStep = dataImportController.goStep.bind(dataImportController);
dataImportController.goStep = function (step) {
  _origGoStep(step);
  if (step === 4) this._renderConfirm();
};

document.addEventListener('DOMContentLoaded', () => dataImportController.init());
