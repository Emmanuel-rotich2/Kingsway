/** Teacher's current class and learning-area assignments. */
const myClassesTaughtController = {
  state: { rows: [] },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]); },
  async load() {
    const body = document.getElementById('myClassesTableBody');
    if (body) body.innerHTML = '<tr><td colspan="8" class="text-center py-4">Loading your teaching assignments…</td></tr>';
    try {
      const response = await window.API.academic.getMyClasses();
      const payload = response?.data?.data ?? response?.data ?? response ?? [];
      this.state.rows = Array.isArray(payload) ? payload : [];
      this.render();
    } catch (error) {
      if (body) body.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${this.esc(error?.message || 'Teaching assignments could not be loaded.')}</td></tr>`;
    }
  },
  render() {
    const rows = this.state.rows;
    const body = document.getElementById('myClassesTableBody');
    if (body) body.innerHTML = rows.length ? rows.map((row, index) => `<tr><td>${index + 1}</td><td>${this.esc(row.class_name || '—')}</td><td>${this.esc(row.stream_name || '—')}</td><td>${Number(row.student_count || 0)}</td><td>${this.esc(row.subject_name || '—')}</td><td>${Number(row.lessons_per_week || 0)}</td><td>${this.esc(row.class_teacher_name || '—')}</td><td>—</td></tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No teaching assignments are recorded for the current academic year.</td></tr>';
    const classes = new Map();
    rows.forEach((row) => { const key = row.class_id ?? row.class_name; const current = classes.get(key) || { students: 0 }; current.students = Math.max(current.students, Number(row.student_count || 0)); classes.set(key, current); });
    this.text('totalClassesCount', classes.size);
    this.text('totalStudentsCount', [...classes.values()].reduce((sum, row) => sum + row.students, 0));
    this.text('subjectsTeachingCount', new Set(rows.map((row) => row.subject_id).filter(Boolean)).size);
    this.text('lessonsPerWeekCount', rows.reduce((sum, row) => sum + Number(row.lessons_per_week || 0), 0));
  },
  text(id, value) { const node = document.getElementById(id); if (node) node.textContent = String(value); },
  exportClasses() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) return window.showNotification?.('You do not have permission to export assignments.', 'error');
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const data = [['Class','Stream','Students','Learning Area','Lessons per Week','Class Teacher'], ...this.state.rows.map((row) => [row.class_name,row.stream_name,row.student_count,row.subject_name,row.lessons_per_week,row.class_teacher_name])];
    window.KingswayFileLifecycle?.exportText?.(data.map((row) => row.map(quote).join(',')).join('\r\n'), 'my-teaching-assignments.csv', 'text/csv;charset=utf-8;');
  },
  init() {
    document.getElementById('myClassesRefresh')?.addEventListener('click', () => this.load());
    document.getElementById('myClassesExport')?.addEventListener('click', () => this.exportClasses());
    document.getElementById('myClassesPrint')?.addEventListener('click', () => { if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('academic')) window.print(); });
    if (window.AuthContext?.ready) window.AuthContext.ready().then(() => this.load()); else this.load();
    window.APIRealtime?.register?.('my_classes_taught', { refresh: () => this.load() }, ['academic']);
  },
};
window.myClassesTaughtController = myClassesTaughtController;
document.addEventListener('DOMContentLoaded', () => myClassesTaughtController.init());
