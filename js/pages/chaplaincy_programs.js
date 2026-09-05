/**
 * chaplaincy_programs.js — Spiritual Programs Controller (Phase B).
 * SDA program catalog, session scheduling, and per-person attendance.
 */
const ChaplaincyProgramsController = {
  state: {
    programs: [],
    sessions: [],
    catalog: [],
    editing: null,
    attSession: null,
    attRows: [],
  },

  API(path, method = 'GET', data = null, params = null) {
    return window.callAPI('/chaplaincy' + path, method, data, params);
  },

  esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString();
  },

  fmtTime(v) {
    if (!v) return '';
    const [h, m] = String(v).split(':');
    const d = new Date();
    d.setHours(+h, +m);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  },

  badge(label, cls = 'bg-secondary') {
    return `<span class="badge ${cls}">${this.esc(label)}</span>`;
  },

  progName(code) {
    const p = this.state.programs.find((x) => x.code === code);
    return p ? p.name : (code || '\u2014');
  },

  sabbathBadge(appliesSabbath) {
    return appliesSabbath
      ? `<span class="badge bg-success-subtle text-success border border-success-subtle">Sabbath</span>`
      : '';
  },

  /* ---------- load ---------- */
  async load() {
    const [progRes, sessRes] = await Promise.all([
      this.API('/programs').catch(() => null),
      this.API('/sessions').catch(() => null),
    ]);
    this.state.programs = progRes?.data || progRes || [];
    this.state.sessions = sessRes?.data || sessRes || [];
    this.fillProgramFilter();
    this.renderPrograms();
    this.renderSessions();
    this.renderUpcoming();
  },

  fillProgramFilter() {
    const sel = document.getElementById('cpProgramFilter');
    if (!sel) return;
    sel.innerHTML = '<option value="">All programs</option>' +
      this.state.programs.map((p) =>
        `<option value="${this.esc(p.id)}">${this.esc(p.name)}</option>`).join('');
    sel.onchange = () => this.renderSessions(sel.value);
  },

  fillProgramSelect() {
    const sel = document.getElementById('cpProgSel');
    if (!sel) return;
    sel.innerHTML = this.state.programs
      .map((p) => `<option value="${this.esc(p.id)}">${this.esc(p.name)}${p.applies_sabbath ? ' (Sabbath)' : ''}</option>`).join('');
  },

  /* ---------- render ---------- */
  renderPrograms() {
    const wrap = document.getElementById('cpPrograms');
    if (!wrap) return;
    if (!this.state.programs.length) { wrap.innerHTML = '<div class="text-center text-muted py-3">No programs.</div>'; return; }
    wrap.innerHTML = this.state.programs.map((p) => `
      <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
        <div>
          <div class="fw-semibold small">${this.esc(p.name)} ${this.sabbathBadge(p.applies_sabbath)}</div>
          <div class="text-muted small">${this.esc(p.description || '')}</div>
        </div>
        <div class="text-muted small text-nowrap ms-3">${this.esc(p.default_day || '')}${p.default_start_time ? ' ' + this.fmtTime(p.default_start_time) : ''}</div>
      </div>`).join('');
  },

  renderSessions(filterId = '') {
    const tb = document.getElementById('cpSessions');
    if (!tb) return;
    let list = this.state.sessions;
    if (filterId) list = list.filter((s) => String(s.program_id) === String(filterId));
    if (!list.length) {
      tb.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No sessions scheduled.</td></tr>';
      return;
    }
    tb.innerHTML = list.map((s) => {
      const statusCls = s.status === 'completed' ? 'bg-success-subtle text-success border border-success-subtle'
        : s.status === 'cancelled' ? 'bg-danger-subtle text-danger border border-danger-subtle'
        : 'bg-info-subtle text-info border border-info-subtle';
      return `
      <tr>
        <td class="small text-nowrap"><span class="fw-semibold">${this.fmtDate(s.session_date)}</span> ${s.start_at ? this.fmtTime(s.start_at) : ''}</td>
        <td class="small">${this.esc(s.program_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(s.title || '\u2014')}</td>
        <td class="small text-muted">${this.esc(s.speaker || '\u2014')}</td>
        <td class="small text-center">${this.esc(s.expected_attendance || '\u2014')}</td>
        <td class="small">${this.badge(s.status, statusCls)}</td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-primary" data-cp-action="attendance" data-id="${this.esc(s.id)}" title="Attendance"><i class="bi bi-person-check"></i></button>
          <button class="btn btn-sm btn-outline-danger" data-cp-action="delete" data-id="${this.esc(s.id)}" title="Delete"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`;
    }).join('');
  },

  renderUpcoming() {
    const tb = document.getElementById('cpUpcoming');
    if (!tb) return;
    const up = this.state.sessions
      .filter((s) => s.status === 'scheduled' && new Date(s.session_date) >= new Date(new Date().toDateString()))
      .sort((a, b) => new Date(a.session_date) - new Date(b.session_date))
      .slice(0, 8);
    if (!up.length) {
      tb.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">No upcoming sessions.</td></tr>';
      return;
    }
    tb.innerHTML = up.map((s) => `
      <tr>
        <td class="small text-nowrap">${this.fmtDate(s.session_date)}</td>
        <td class="small">${this.esc(s.program_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(s.class_name || '\u2014')}</td>
        <td class="small">${s.applies_sabbath ? this.badge('Sabbath', 'bg-success-subtle text-success border border-success-subtle') : this.badge('Weekday', 'bg-light text-muted border')}</td>
        <td class="text-end"><button class="btn btn-sm btn-outline-primary" data-cp-action="attendance" data-id="${this.esc(s.id)}"><i class="bi bi-person-check"></i></button></td>
      </tr>`).join('');
  },

  /* ---------- catalog management ---------- */
  async openCatalog() {
    this.state.editing = null;
    this.state.catalog = [];
    this.renderCatalog();
    const res = await this.API('/programs', 'GET', null, { include_inactive: 1 }).catch(() => null);
    this.state.catalog = (res && (res.data || res)) || [];
    this.renderCatalog();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('cpCatalogModal')).show();
  },

  renderCatalog() {
    const tb = document.getElementById('cpProgList');
    if (!tb) return;
    if (!this.state.catalog.length) {
      tb.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No programs in the catalog.</td></tr>';
      return;
    }
    tb.innerHTML = this.state.catalog.map((p, i) => {
      const first = i === 0;
      const last = i === this.state.catalog.length - 1;
      return `
      <tr class="${p.is_active ? '' : 'table-secondary'}">
        <td class="text-nowrap">
          <button class="btn btn-sm btn-outline-secondary py-0 px-1" data-cp-cat="up" data-id="${this.esc(p.id)}" title="Move up" ${first ? 'disabled' : ''}><i class="bi bi-arrow-up"></i></button>
          <button class="btn btn-sm btn-outline-secondary py-0 px-1" data-cp-cat="down" data-id="${this.esc(p.id)}" title="Move down" ${last ? 'disabled' : ''}><i class="bi bi-arrow-down"></i></button>
        </td>
        <td class="small">${this.esc(p.name)} ${this.sabbathBadge(p.applies_sabbath)}</td>
        <td class="small text-muted">${this.esc(p.description || '\u2014')}</td>
        <td class="small">${this.esc(p.default_day || '\u2014')}</td>
        <td class="small">${p.default_start_time ? this.fmtTime(p.default_start_time) : '\u2014'}</td>
        <td class="small">${this.esc(p.applies_to || 'both')}</td>
        <td class="small">
          <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" data-cp-cat="toggle" data-id="${this.esc(p.id)}" ${p.is_active ? 'checked' : ''}></div>
        </td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-primary" data-cp-cat="edit" data-id="${this.esc(p.id)}" title="Edit"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger" data-cp-cat="delete" data-id="${this.esc(p.id)}" title="Delete"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`;
    }).join('');
  },

  populateProgramForm(prog) {
    document.getElementById('cpProgId').value = prog ? prog.id : '';
    document.getElementById('cpProgName').value = prog ? prog.name : '';
    document.getElementById('cpProgDesc').value = prog ? (prog.description || '') : '';
    document.getElementById('cpProgDay').value = prog ? (prog.default_day || 'ANY') : 'SATURDAY';
    document.getElementById('cpProgTime').value = prog && prog.default_start_time ? String(prog.default_start_time).slice(0, 5) : '';
    document.getElementById('cpProgApplyTo').value = prog ? (prog.applies_to || 'both') : 'both';
    document.getElementById('cpProgSabbath').checked = !!(prog && prog.applies_sabbath);
    document.getElementById('cpProgActive').checked = prog ? !!prog.is_active : true;
    document.getElementById('cpProgCancel').classList.toggle('d-none', !prog);
  },

  async saveProgram(e) {
    e?.preventDefault();
    const id = document.getElementById('cpProgId').value || null;
    const name = document.getElementById('cpProgName').value?.trim();
    if (!name) { showNotification?.('Program name is required', 'error'); return; }
    const data = {
      name,
      description: document.getElementById('cpProgDesc').value?.trim() || null,
      default_day: document.getElementById('cpProgDay').value,
      default_start_time: document.getElementById('cpProgTime').value || null,
      applies_to: document.getElementById('cpProgApplyTo').value,
      applies_sabbath: document.getElementById('cpProgSabbath').checked,
      is_active: document.getElementById('cpProgActive').checked,
    };
    const res = id
      ? await this.API(`/programs/${id}`, 'PUT', data)
      : await this.API('/programs', 'POST', data);
    if (res && (res.success || res.status === 'success')) {
      showNotification?.(id ? 'Program updated' : 'Program created', 'success');
      this.cancelEditForm();
      await this.reloadCatalog();
      this.load();
    } else {
      showNotification?.(res?.message || 'Failed to save program', 'error');
    }
  },

  cancelEditForm() {
    this.state.editing = null;
    this.populateProgramForm(null);
  },

  async removeProgram(id) {
    if (!confirm('Delete this program from the catalog?')) return;
    const res = await this.API(`/programs/${id}`, 'DELETE');
    if (res && (res.success || res.status === 'success')) {
      showNotification?.(res.data?.deactivated ? 'Program has scheduled sessions — deactivated instead' : 'Program deleted', 'success');
      if (this.state.editing === id) this.cancelEditForm();
      await this.reloadCatalog();
      this.load();
    } else {
      showNotification?.(res?.message || 'Failed to delete program', 'error');
    }
  },

  async toggleProgram(id, checked) {
    const res = await this.API(`/programs/${id}`, 'PUT', { is_active: checked });
    if (res && (res.success || res.status === 'success')) {
      showNotification?.(checked ? 'Program activated' : 'Program deactivated', 'success');
      await this.reloadCatalog();
      this.load();
    } else {
      this.renderCatalog();
    }
  },

  async moveProgram(id, dir) {
    const idx = this.state.catalog.findIndex((p) => String(p.id) === String(id));
    const swapIdx = idx + (dir === 'up' ? -1 : 1);
    if (idx < 0 || swapIdx < 0 || swapIdx >= this.state.catalog.length) return;
    const a = this.state.catalog[idx];
    const b = this.state.catalog[swapIdx];
    const results = await Promise.all([
      this.API(`/programs/${a.id}`, 'PUT', { display_order: b.display_order }).catch(() => null),
      this.API(`/programs/${b.id}`, 'PUT', { display_order: a.display_order }).catch(() => null),
    ]);
    if (results[0] && results[1]) await this.reloadCatalog();
  },

  async reloadCatalog() {
    const res = await this.API('/programs', 'GET', null, { include_inactive: 1 }).catch(() => null);
    this.state.catalog = (res && (res.data || res)) || [];
    this.renderCatalog();
  },

  /* ---------- schedule session ---------- */
  openSchedule() {
    this.fillProgramSelect();
    const form = document.getElementById('cpSessionForm');
    if (form) form.reset();
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cpSessionModal'));
    modal.show();
  },

  async saveSession(e) {
    e?.preventDefault();
    const programId = document.getElementById('cpProgSel')?.value;
    const date = document.getElementById('cpDate')?.value;
    const title = document.getElementById('cpTitle')?.value?.trim();
    if (!programId || !date || !title) { showNotification?.('Program, date and theme are required', 'error'); return; }
    // Prefill the suggested next date for the chosen program if empty.
    const classText = (document.getElementById('cpClass')?.value || '').trim();
    let classStreamId = /^\d+$/.test(classText) ? parseInt(classText, 10) : null;
    if (!classText) {
      const nd = await this.API('/programs/next-date', 'GET', null, { program_id: programId }).catch(() => null);
      if (!date && nd?.data?.suggested_date) document.getElementById('cpDate').value = nd.data.suggested_date;
    }
    const data = {
      program_id: programId,
      session_date: document.getElementById('cpDate')?.value,
      title,
      start_at: document.getElementById('cpStart')?.value || null,
      end_at: document.getElementById('cpEnd')?.value || null,
      location: document.getElementById('cpLocation')?.value || null,
      class_stream_id: classStreamId,
      speaker: document.getElementById('cpSpeaker')?.value || null,
      worship_leader: document.getElementById('cpWorship')?.value || null,
      music_director: document.getElementById('cpMusic')?.value || null,
      expected_attendance: document.getElementById('cpExpected')?.value || null,
      status: document.getElementById('cpStatus')?.value || 'scheduled',
      notes: document.getElementById('cpNotes')?.value || null,
    };
    const res = await this.API('/sessions', 'POST', data);
    if (res && (res.success || res.status === 'success')) {
      showNotification?.('Session scheduled', 'success');
      bootstrap.Modal.getInstance(document.getElementById('cpSessionModal'))?.hide();
      this.load();
    } else {
      showNotification?.(res?.message || 'Failed to schedule session', 'error');
    }
  },

  async deleteSession(id) {
    if (!confirm('Delete this scheduled session?')) return;
    const res = await this.API(`/sessions/${id}`, 'DELETE');
    if (res && (res.success || res.status === 'success')) {
      showNotification?.('Session deleted', 'success');
      this.load();
    } else {
      showNotification?.(res?.message || 'Failed to delete session', 'error');
    }
  },

  /* ---------- attendance ---------- */
  async openAttendance(id) {
    const res = await this.API(`/sessions/${id}/attendance`).catch(() => null);
    if (!res?.data) { showNotification?.('Could not load session', 'error'); return; }
    this.state.attSession = res.data.session;
    this.state.attRows = (res.data.attendance || []).map((r) => ({
      attendee_type: r.attendee_type,
      student_id: r.student_id,
      staff_id: r.staff_id,
      parent_id: r.parent_id,
      name: r.name,
      attended: !!r.attended,
    }));
    this.renderAttendanceModal();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('cpAttModal')).show();
  },

  renderAttendanceModal() {
    const s = this.state.attSession;
    if (!s) return;
    document.getElementById('cpAttTitle').textContent = `${s.title} (${s.program_name || ''})`;
    document.getElementById('cpAttDate').textContent = this.fmtDate(s.session_date);
    this.renderAttRows();
  },

  renderAttRows() {
    const tb = document.getElementById('cpAttRows');
    if (!tb) return;
    if (!this.state.attRows.length) {
      tb.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No attendance recorded yet.</td></tr>';
      return;
    }
    tb.innerHTML = this.state.attRows.map((r, i) => `
      <tr>
        <td><input type="checkbox" class="form-check-input" data-cp-att="toggle" data-i="${i}" ${r.attended ? 'checked' : ''}></td>
        <td class="small">${this.esc(r.name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(r.attendee_type)}</td>
      </tr>`).join('');
  },

  addAttendeeRow(type) {
    const name = prompt(`${type === 'student' ? 'Student' : 'Staff'} ID:`);
    if (!name) return;
    if (!/^\d+$/.test(name)) { showNotification?.(`Enter a numeric ${type} ID`, 'error'); return; }
    this.state.attRows.push({
      attendee_type: type,
      student_id: type === 'student' ? parseInt(name, 10) : null,
      staff_id: type === 'staff' ? parseInt(name, 10) : null,
      parent_id: null,
      name: `${type} #${name}`,
      attended: true,
    });
    this.renderAttRows();
  },

  async saveAttendance() {
    const sid = this.state.attSession?.id;
    if (!sid) return;
    const rows = this.state.attRows.map((r, i) => {
      const cb = document.querySelector(`[data-cp-att="toggle"][data-i="${i}"]`);
      return {
        attendee_type: r.attendee_type,
        student_id: r.student_id || null,
        staff_id: r.staff_id || null,
        parent_id: r.parent_id || null,
        attended: cb ? cb.checked : r.attended,
      };
    });
    const res = await this.API(`/sessions/${sid}/attendance`, 'PUT', { attendance: rows });
    if (res && (res.success || res.status === 'success')) {
      showNotification?.(`Attendance saved (${res.data?.saved || rows.length})`, 'success');
      bootstrap.Modal.getInstance(document.getElementById('cpAttModal'))?.hide();
    } else {
      showNotification?.(res?.message || 'Failed to save attendance', 'error');
    }
  },

  /* ---------- bind ---------- */
  bind() {
    document.getElementById('cpRefresh')?.addEventListener('click', () => this.load());
    document.getElementById('cpSchedule')?.addEventListener('click', () => this.openSchedule());
    document.getElementById('cpManage')?.addEventListener('click', () => this.openCatalog());
    document.getElementById('cpProgSave')?.addEventListener('click', (e) => this.saveProgram(e));
    document.getElementById('cpProgCancel')?.addEventListener('click', () => this.cancelEditForm());
    document.getElementById('cpSave')?.addEventListener('click', (e) => this.saveSession(e));
    document.getElementById('cpAttAddStudent')?.addEventListener('click', () => this.addAttendeeRow('student'));
    document.getElementById('cpAttAddStaff')?.addEventListener('click', () => this.addAttendeeRow('staff'));
    document.getElementById('cpAttSave')?.addEventListener('click', () => this.saveAttendance());
    document.addEventListener('click', (ev) => {
      const att = ev.target.closest('[data-cp-action="attendance"]');
      if (att) { this.openAttendance(att.getAttribute('data-id')); return; }
      const del = ev.target.closest('[data-cp-action="delete"]');
      if (del) { this.deleteSession(del.getAttribute('data-id')); return; }
      const edit = ev.target.closest('[data-cp-cat="edit"]');
      if (edit) {
        const prog = this.state.catalog.find((p) => String(p.id) === edit.getAttribute('data-id'));
        if (prog) { this.state.editing = prog.id; this.populateProgramForm(prog); }
        return;
      }
      const rm = ev.target.closest('[data-cp-cat="delete"]');
      if (rm) { this.removeProgram(rm.getAttribute('data-id')); return; }
      const up = ev.target.closest('[data-cp-cat="up"]');
      if (up) { this.moveProgram(up.getAttribute('data-id'), 'up'); return; }
      const dn = ev.target.closest('[data-cp-cat="down"]');
      if (dn) { this.moveProgram(dn.getAttribute('data-id'), 'down'); return; }
    });
    document.addEventListener('change', (ev) => {
      const tog = ev.target.closest('[data-cp-cat="toggle"]');
      if (tog) this.toggleProgram(tog.getAttribute('data-id'), tog.checked);
    });
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.bind();
    await this.load();
  },
};

window.ChaplaincyProgramsController = ChaplaincyProgramsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => ChaplaincyProgramsController.init().catch(() => {}));
} else {
  ChaplaincyProgramsController.init().catch(() => {});
}
