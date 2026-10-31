/** Attendance report adapter for the academic-summary response contract. */
const attendanceReportsController = {
  state: { students: [], byClass: [], trend: [], low: [], chart: null, termRange: null },
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]); },
  data(response) { return response?.data?.data ?? response?.data ?? response ?? {}; },
  range() {
    const now = new Date(), period = document.getElementById('arPeriod')?.value || 'this_month';
    const to = new Date(now); let from = new Date(now);
    if (period === 'this_week') from.setDate(now.getDate() - ((now.getDay() + 6) % 7));
    else if (period === 'this_term' && this.state.termRange) {
      return { date_from: this.state.termRange.date_from, date_to: this.state.termRange.date_to };
    }
    else if (period === 'custom') {
      from = new Date(document.getElementById('arDateFrom')?.value || now);
      const customTo = new Date(document.getElementById('arDateTo')?.value || now);
      return { date_from: this.iso(from), date_to: this.iso(customTo) };
    } else from = new Date(now.getFullYear(), now.getMonth(), 1);
    return { date_from: this.iso(from), date_to: this.iso(to) };
  },
  iso(date) { return date.toISOString().slice(0, 10); },
  async load() {
    const body = document.getElementById('arClassTableBody');
    if (body) body.innerHTML = '<tr><td colspan="6" class="text-center py-4">Loading attendance…</td></tr>';
    const error = document.getElementById('arRegisterAlert'); if (error) error.classList.add('d-none');
    try {
      await Promise.all([this.loadClasses(), this.loadTermRange()]);
      const params = new URLSearchParams(this.range());
      const response = await this.API('GET', `/api/attendance/academic-summary?${params}`);
      const payload = this.data(response);
      this.state.students = Array.isArray(payload.students) ? payload.students : [];
      this.state.byClass = Array.isArray(payload.by_class) ? payload.by_class : [];
      this.state.trend = Array.isArray(payload.trend) ? payload.trend : [];
      this.state.low = Array.isArray(payload.low_attendance) ? payload.low_attendance : [];
      this.render(payload.summary || {});
    } catch (exception) {
      if (body) body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Attendance report failed to load.</td></tr>';
      if (error) { error.textContent = exception?.message || 'Attendance report failed to load.'; error.classList.remove('d-none'); }
    }
  },
  async loadTermRange() {
    if (this.state.termRange) return;
    const response = await this.API('GET', '/api/academic/terms-list');
    const value = this.data(response);
    const terms = Array.isArray(value) ? value : (Array.isArray(value.data) ? value.data : []);
    const current = terms.find((term) => String(term.status || '').toLowerCase() === 'current' || Number(term.is_current) === 1);
    if (current && (current.opening_date || current.start_date) && (current.closing_date || current.end_date)) {
      this.state.termRange = { date_from: current.opening_date || current.start_date, date_to: current.closing_date || current.end_date };
    }
  },
  async loadClasses() {
    const select = document.getElementById('arClass');
    if (!select || select.dataset.loaded) return;
    const response = await this.API('GET', '/api/academic/classes-list');
    const classes = this.data(response);
    const rows = Array.isArray(classes) ? classes : (Array.isArray(classes.data) ? classes.data : []);
    select.innerHTML = '<option value="">All Classes</option>' + rows.map((row) => `<option value="${this.esc(row.name || row.class_name)}">${this.esc(row.name || row.class_name)}</option>`).join('');
    select.dataset.loaded = '1';
  },
  render(summary) {
    const el = (id, value) => { const node = document.getElementById(id); if (node) node.textContent = value; };
    const selectedClass = document.getElementById('arClass')?.value || '';
    const students = this.state.students.filter((row) => !selectedClass || row.class_name === selectedClass);
    const byClass = this.state.byClass.filter((row) => !selectedClass || row.class_name === selectedClass);
    const chronic = this.state.low.filter((row) => {
      const student = this.state.students.find((item) => Number(item.student_id) === Number(row.student_id));
      return !selectedClass || student?.class_name === selectedClass;
    });
    el('arStatTotal', students.length);
    const days = students.reduce((n, row) => n + Number(row.total_days || 0), 0);
    const present = students.reduce((n, row) => n + Number(row.present || 0) + Number(row.late || 0), 0);
    el('arStatRate', days ? `${(present / days * 100).toFixed(1)}%` : (summary.average_attendance != null ? `${summary.average_attendance}%` : '—'));
    el('arStatAbsent', students.reduce((n, row) => n + Number(row.absent || 0), 0));
    el('arStatChronic', chronic.length);
    const classBody = document.getElementById('arClassTableBody');
    if (classBody) classBody.innerHTML = byClass.length ? byClass.map((row) => `<tr><td>${this.esc(row.class_name)}</td><td class="text-center">${Number(row.total_enrolled || 0)}</td><td class="text-center">${Number(row.present_today || 0)}</td><td class="text-center">${Number(row.absent_today || 0)}</td><td class="text-center">${row.attendance_rate == null ? '—' : `${row.attendance_rate}%`}</td><td>—</td></tr>`).join('') : '<tr><td colspan="6" class="text-center py-4 text-muted">No attendance records in this period.</td></tr>';
    const chronicBody = document.getElementById('arChronicTableBody');
    if (chronicBody) chronicBody.innerHTML = chronic.length ? chronic.map((row) => {
      const learner = students.find((item) => Number(item.student_id) === Number(row.student_id)) || {};
      return `<tr><td>${this.esc(row.student_name)}</td><td>${this.esc(row.admission_no)}</td><td>${this.esc(learner.class_name || '—')}</td><td class="text-center">${Number(learner.present || 0)}</td><td class="text-center">${Number(row.absent_days || 0)}</td><td class="text-center">${Number(row.attendance_percentage || 0)}%</td><td><span class="badge bg-warning text-dark">Follow up</span></td></tr>`;
    }).join('') : '<tr><td colspan="7" class="text-center py-4 text-muted">No learners below 80%.</td></tr>';
    this.drawTrend();
  },
  drawTrend() {
    const canvas = document.getElementById('arTrendsChart'); if (!canvas || !window.Chart) return;
    this.state.chart?.destroy();
    this.state.chart = new Chart(canvas, { type: 'line', data: { labels: this.state.trend.map((row) => row.date), datasets: [
      { label: 'Present', data: this.state.trend.map((row) => row.present), borderColor: '#198754' },
      { label: 'Absent', data: this.state.trend.map((row) => row.absent), borderColor: '#dc3545' },
      { label: 'Late', data: this.state.trend.map((row) => row.late), borderColor: '#fd7e14' }
    ] }, options: { responsive: true, maintainAspectRatio: false } });
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('attendance')) return window.showNotification?.('You do not have permission to export.', 'warning');
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const rows = [['Student','Admission No','Class','Present','Absent','Late','Attendance %'], ...this.state.students.map((row) => [row.student_name,row.admission_no,row.class_name,row.present,row.absent,row.late,row.attendance_percentage])];
    window.KingswayFileLifecycle?.exportText?.(rows.map((row) => row.map(quote).join(',')).join('\r\n'), 'attendance-report.csv', 'text/csv;charset=utf-8;');
  },
  init() {
    document.getElementById('arPeriod')?.addEventListener('change', () => {
      const custom = document.getElementById('arPeriod').value === 'custom';
      document.getElementById('arDateFromWrap')?.classList.toggle('d-none', !custom);
      document.getElementById('arDateToWrap')?.classList.toggle('d-none', !custom);
      this.load();
    });
    document.getElementById('arClass')?.addEventListener('change', () => this.render({}));
    ['arDateFrom','arDateTo'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.load()));
    document.querySelectorAll('#arTabs [data-tab]').forEach((button) => button.addEventListener('click', () => {
      document.querySelectorAll('#arTabs .nav-link').forEach((tab) => tab.classList.toggle('active', tab === button));
      ['Classes','Chronic','Trends'].forEach((name) => document.getElementById(`arTab${name}`)?.classList.toggle('d-none', name.toLowerCase() !== button.dataset.tab));
    }));
    document.getElementById('printAttendanceReport')?.addEventListener('click', () => { if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('attendance')) window.print(); });
    window.APIRealtime?.register?.('attendance_reports', { refresh: () => this.load() }, ['attendance']);
    return this.load();
  }
};
window.attendanceReportsController = attendanceReportsController;
document.addEventListener('DOMContentLoaded', () => attendanceReportsController.init());
