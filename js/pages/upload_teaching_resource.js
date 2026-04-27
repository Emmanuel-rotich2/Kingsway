/**
 * Upload Teaching Resource Controller
 * Handles drag-drop / browse file selection + metadata form submission.
 * API: /academic/resources (POST), /academic/subjects
 */

const uploadResourceController = {

  _file: null,
  _subjects: [],

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._setupDragDrop();
    await this._loadSubjects();
  },

  _setupDragDrop: function () {
    const zone  = document.getElementById('utrDropZone');
    const input = document.getElementById('utrFile');
    if (!zone || !input) return;

    zone.addEventListener('click', () => input.click());
    input.addEventListener('change', e => { if (e.target.files[0]) this._selectFile(e.target.files[0]); });

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.background = '#e9f0ff'; });
    zone.addEventListener('dragleave', () => { zone.style.background = ''; });
    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.style.background = '';
      const file = e.dataTransfer.files[0];
      if (file) this._selectFile(file);
    });
  },

  _loadSubjects: async function () {
    try {
      const r = await callAPI('/academic/subjects', 'GET');
      this._subjects = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      ['utrSubject', 'tmSubject'].forEach(selId => {
        const sel = document.getElementById(selId);
        if (!sel) return;
        const opts = this._subjects.map(s => `<option value="${s.id||s.name}">${this._esc(s.name||s.subject_name||'')}</option>`).join('');
        sel.innerHTML = '<option value="">— Select subject —</option>' + opts;
      });
    } catch (e) { console.warn('Subjects failed:', e); }
  },

  _selectFile: function (file) {
    this._file = file;
    const info    = document.getElementById('utrFileInfo');
    const nameEl  = document.getElementById('utrFileName');
    const sizeEl  = document.getElementById('utrFileSize');
    const iconEl  = document.getElementById('utrFileIcon');

    if (info)   info.classList.remove('d-none');
    if (nameEl) nameEl.textContent = file.name;
    if (sizeEl) sizeEl.textContent = this._formatSize(file.size);

    if (iconEl) {
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      const iconMap = { pdf: 'bi-file-earmark-pdf', doc: 'bi-file-earmark-word', docx: 'bi-file-earmark-word',
        ppt: 'bi-file-earmark-ppt', pptx: 'bi-file-earmark-ppt',
        mp4: 'bi-file-earmark-play', mov: 'bi-file-earmark-play',
        png: 'bi-file-earmark-image', jpg: 'bi-file-earmark-image', jpeg: 'bi-file-earmark-image',
        zip: 'bi-file-earmark-zip' };
      iconEl.className = `bi ${iconMap[ext] || 'bi-file-earmark-check'} fs-2 text-primary`;
    }
  },

  clearFile: function () {
    this._file = null;
    const input = document.getElementById('utrFile');
    const info  = document.getElementById('utrFileInfo');
    if (input) input.value = '';
    if (info)  info.classList.add('d-none');
    const prog = document.getElementById('utrProgress');
    if (prog) prog.classList.add('d-none');
  },

  upload: async function () {
    const title    = (document.getElementById('utrTitle')?.value   || '').trim();
    const subject  = document.getElementById('utrSubject')?.value  || '';
    const cls      = document.getElementById('utrClass')?.value    || '';
    const type     = document.getElementById('utrType')?.value     || 'Worksheet';
    const term     = document.getElementById('utrTerm')?.value     || '1';
    const desc     = document.getElementById('utrDescription')?.value || '';
    const errEl    = document.getElementById('utrError');
    const succEl   = document.getElementById('utrSuccess');
    const btn      = document.getElementById('utrUploadBtn');

    if (errEl)  { errEl.classList.add('d-none'); errEl.textContent = ''; }
    if (succEl) { succEl.classList.add('d-none'); }

    if (!title) {
      if (errEl) { errEl.textContent = 'Please enter a title for this resource.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (!this._file) {
      if (errEl) { errEl.textContent = 'Please select a file to upload.'; errEl.classList.remove('d-none'); }
      return;
    }

    if (btn) { btn.disabled = true; btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Uploading…'; }

    const prog    = document.getElementById('utrProgress');
    const progBar = document.getElementById('utrProgressBar');
    const progTxt = document.getElementById('utrProgressText');
    if (prog) prog.classList.remove('d-none');

    try {
      // Simulate progress during upload
      let pct = 0;
      const ticker = setInterval(() => {
        pct = Math.min(pct + 8, 85);
        if (progBar) progBar.style.width = pct + '%';
        if (progTxt) progTxt.textContent = pct + '% — uploading…';
      }, 200);

      const formData = new FormData();
      formData.append('file',        this._file);
      formData.append('title',       title);
      formData.append('subject_id',  subject);
      formData.append('class',       cls);
      formData.append('type',        type);
      formData.append('term',        term);
      formData.append('description', desc);

      // callAPI doesn't handle FormData natively — use fetch directly
      const token = AuthContext.getToken ? AuthContext.getToken() : (AuthContext.token || localStorage.getItem('auth_token') || '');
      const base  = (window.APP_BASE || '').replace(/\/$/, '');
      const res   = await fetch(base + '/api/academic/resources', {
        method:  'POST',
        headers: { Authorization: 'Bearer ' + token },
        body:    formData,
      });

      clearInterval(ticker);
      const json = await res.json().catch(() => ({}));

      if (progBar) progBar.style.width = '100%';
      if (progTxt) progTxt.textContent = 'Done!';

      if (!res.ok && !json.success) {
        throw new Error(json.message || json.error || 'Upload failed.');
      }

      if (succEl) {
        succEl.innerHTML = '✔ <strong>' + this._esc(title) + '</strong> uploaded successfully!';
        succEl.classList.remove('d-none');
      }
      this.clearFile();
      // Reset form
      ['utrTitle','utrDescription'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    } catch (e) {
      if (progBar) progBar.classList.add('bg-danger');
      if (errEl) { errEl.textContent = e.message || 'Upload failed.'; errEl.classList.remove('d-none'); }
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-cloud-upload me-2"></i>Upload Resource'; }
    }
  },

  _formatSize: function (bytes) {
    if (bytes < 1024)       return bytes + ' B';
    if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  },

  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => uploadResourceController.init());
