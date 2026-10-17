/**
 * teacher_timetables.js — Teacher Timetables Controller (standalone page).
 * Deputy views any teacher's weekly schedule from the timetable entries.
 */
const teacherTimetablesController = {
  state: { teachers: [], entries: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  async loadTeachers() {
    try {
      const r = await this.API('GET', 'academic/teachers-list');
      const items = r?.data || r?.items || [];
      this.state.teachers = Array.isArray(items) ? items : items.data || [];
      const sel = document.getElementById('teacherSelect');
      sel.innerHTML = '<option value="">— Select teacher —</option>' + this.state.teachers.map(t => {
        const name = `${t.first_name || ''} ${t.last_name || ''}`.trim() || t.name || t.id;
        return `<option value="${t.id}">${this.esc(name)}</option>`;
      }).join('');
    } catch (e) {
      this.notify('Failed to load teachers', 'danger');
    }
  },

  async loadData() {
    const teacherId = document.getElementById('teacherSelect').value;
    const grid = document.getElementById('timetableGrid');
    if (!teacherId) { grid.innerHTML = '<div class="text-muted small p-3">Select a teacher to view their timetable.</div>'; return; }
    grid.innerHTML = '<div class="text-muted small p-3"><div class="spinner-border spinner-border-sm me-2"></div>Loading timetable…</div>';
    try {
      const r = await this.API('GET', 'academic/teachers-schedule', null, { teacher_id: teacherId });
      const items = r?.data || r?.items || [];
      this.state.entries = Array.isArray(items) ? items : items.data || [];
      this.render();
    } catch (e) {
      grid.innerHTML = `<div class="text-danger small p-3">${this.esc(e.message || 'Load failed')}</div>`;
    }
  },

  render() {
    const grid = document.getElementById('timetableGrid');
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    const periods = [...new Set(this.state.entries.map(e => Number(e.period_number || 0)))].sort((a, b) => a - b);
    if (!periods.length) { grid.innerHTML = '<div class="text-muted small p-3">No scheduled lessons for this teacher.</div>'; return; }

    const dayOfWeek = (d) => (d || '').toLowerCase().slice(0, 3);

    let html = '<div class="tt-cell tt-cell-head">Period</div>';
    html += days.map(d => `<div class="tt-cell tt-cell-head">${d}</div>`).join('');

    periods.forEach(p => {
      html += `<div class="tt-cell tt-period">${p}</div>`;
      days.forEach(d => {
        const entries = this.state.entries.filter(e => dayOfWeek(e.day_of_week) === d.toLowerCase().slice(0, 3) && Number(e.period_number) === p);
        const cells = entries.map(e => `
          <div class="tt-entry">
            <div class="fw-bold">${this.esc(e.subject_name || '\u2014')}</div>
            <div class="text-muted">${this.esc(e.class_name || '')}${e.stream_name ? ` ${this.esc(e.stream_name)}` : ''}</div>
            <div class="text-muted">${this.esc(e.room_name || '')}</div>
          </div>`).join('');
        html += `<div class="tt-cell">${cells || '<span class="text-muted">\u2014</span>'}</div>`;
      });
    });
    grid.innerHTML = html;
  },

  setupEventListeners() {
    document.getElementById('teacherSelect')?.addEventListener('change', () => this.loadData());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.loadTeachers();
    window.APIRealtime?.register?.("teacher_timetables", { refresh: () => Promise.all([this.loadTeachers(), this.loadData()]) }, ["academic"]);
  },
};

window.teacherTimetablesController = teacherTimetablesController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => teacherTimetablesController.init().catch(() => {}));
} else {
  teacherTimetablesController.init().catch(() => {});
}
