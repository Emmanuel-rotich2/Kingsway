/**
 * Class Parent Contacts Controller
 * Parent/guardian contact directory for the teacher's assigned class.
 * API: GET /students/parents?class=self
 */
const classParentContactsController = {
  _data: [],
  _filtered: [],

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    document.getElementById('cpSearchBtn')?.addEventListener('click', () => this._applyFilter());
    document.getElementById('cpSearch')?.addEventListener('keydown', e => { if (e.key === 'Enter') this._applyFilter(); });
    document.getElementById('cpExportBtn')?.addEventListener('click', () => this.exportCSV());
    await this._load();
  },

  _load: async function () {
    this._show('cpLoading');
    const cls = document.getElementById('cpClassFilter')?.value || 'self';

    try {
      const r = await callAPI('/students/parents?class=' + cls, 'GET');
      this._data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._filtered = [...this._data];
      this._render();
    } catch (e) {
      this._show('cpEmpty');
      console.warn('Parent contacts load failed:', e);
    }
  },

  _applyFilter: function () {
    const q = (document.getElementById('cpSearch')?.value || '').toLowerCase().trim();
    this._filtered = !q
      ? [...this._data]
      : this._data.filter(c =>
          (c.student_name || (c.first_name || '') + ' ' + (c.last_name || '')).toLowerCase().includes(q) ||
          (c.parent_name  || c.guardian_name || '').toLowerCase().includes(q) ||
          (c.parent_phone || c.guardian_phone || '').includes(q)
        );
    this._render();
  },

  _render: function () {
    const tbody = document.getElementById('cpTableBody');
    const count = document.getElementById('cpContactCount');
    if (!tbody) return;

    if (count) count.textContent = this._filtered.length + ' contact' + (this._filtered.length !== 1 ? 's' : '');

    if (!this._filtered.length) {
      this._show('cpEmpty');
      return;
    }

    this._show('cpContent');
    const preferredIcon = method => {
      const map = { phone: 'bi-telephone', whatsapp: 'bi-whatsapp', email: 'bi-envelope', sms: 'bi-chat-text' };
      return `<i class="bi ${map[method] || 'bi-chat'} me-1"></i>${method || 'Phone'}`;
    };

    tbody.innerHTML = this._filtered.map(c => {
      const studentName = c.student_name || ((c.first_name || '') + ' ' + (c.last_name || '')).trim() || '—';
      const parentName  = c.parent_name  || c.guardian_name  || '—';
      const phone       = c.parent_phone || c.guardian_phone || '—';
      const email       = c.parent_email || c.guardian_email || '—';
      const relation    = c.relationship || c.parent_relationship || '—';
      const pref        = (c.preferred_contact_method || c.preferred_contact || 'phone').toLowerCase();
      const lastContact = c.last_contacted || '—';

      return `<tr>
        <td>
          <div class="fw-semibold">${this._esc(studentName)}</div>
          <div class="text-muted small">${this._esc(c.class_name || c.grade || '')}</div>
        </td>
        <td class="fw-semibold">${this._esc(parentName)}</td>
        <td>${this._esc(relation)}</td>
        <td>
          ${phone !== '—'
            ? `<a href="tel:${this._esc(phone)}" class="text-decoration-none">${this._esc(phone)}</a>`
            : '—'}
        </td>
        <td>
          ${email !== '—'
            ? `<a href="mailto:${this._esc(email)}" class="text-decoration-none text-truncate d-block" style="max-width:180px;">${this._esc(email)}</a>`
            : '—'}
        </td>
        <td>
          <span class="badge bg-light text-dark border">
            ${preferredIcon(pref)}
          </span>
        </td>
        <td class="small text-muted">${this._esc(lastContact)}</td>
      </tr>`;
    }).join('');
  },

  exportCSV: function () {
    if (!this._filtered.length) { showNotification('No data to export.', 'warning'); return; }
    const header = ['Student', 'Class', 'Parent/Guardian', 'Relationship', 'Phone', 'Email', 'Preferred Contact', 'Last Contacted'];
    const rows = [
      header.join(','),
      ...this._filtered.map(c => [
        `"${c.student_name || (c.first_name || '') + ' ' + (c.last_name || '')}"`,
        `"${c.class_name || c.grade || ''}"`,
        `"${c.parent_name || c.guardian_name || ''}"`,
        `"${c.relationship || ''}"`,
        `"${c.parent_phone || c.guardian_phone || ''}"`,
        `"${c.parent_email || c.guardian_email || ''}"`,
        `"${c.preferred_contact_method || 'phone'}"`,
        `"${c.last_contacted || ''}"`,
      ].join(',')),
    ];
    const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `parent_contacts_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
  },

  _show: function (id) {
    ['cpLoading', 'cpContent', 'cpEmpty'].forEach(el => {
      const e = document.getElementById(el);
      if (e) e.style.display = el === id ? '' : 'none';
    });
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => classParentContactsController.init());
