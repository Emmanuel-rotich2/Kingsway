/** Activity report using the canonical activities list, statistics and upcoming routes. */
const activityReportsController = {
  state: { activities: [], upcoming: [], stats: {} },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]); },
  data(response) { return response?.data?.data ?? response?.data ?? response ?? []; },
  async load() {
    const upcomingBody = document.getElementById('arUpcoming');
    const listBody = document.getElementById('arList');
    if (upcomingBody) upcomingBody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Loading upcoming activities…</td></tr>';
    if (listBody) listBody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Loading activities…</td></tr>';
    try {
      const [activitiesResponse, upcomingResponse, statsResponse] = await Promise.all([
        window.API.activities.list({ limit: 100 }),
        window.API.activities.getUpcoming(100),
        window.API.activities.getSummary(),
      ]);
      this.state.activities = Array.isArray(this.data(activitiesResponse)) ? this.data(activitiesResponse) : [];
      this.state.upcoming = Array.isArray(this.data(upcomingResponse)) ? this.data(upcomingResponse) : [];
      this.state.stats = this.data(statsResponse) || {};
      this.render();
    } catch (error) {
      const message = this.esc(error?.message || 'Activity reports could not be loaded.');
      if (upcomingBody) upcomingBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${message}</td></tr>`;
      if (listBody) listBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${message}</td></tr>`;
      const kpis = document.getElementById('arKpis'); if (kpis) kpis.innerHTML = `<div class="col-12"><div class="alert alert-danger mb-0">${message}</div></div>`;
    }
  },
  render() {
    const stats = this.state.stats;
    const kpis = document.getElementById('arKpis');
    if (kpis) kpis.innerHTML = [
      ['Total Activities', stats.total_activities], ['Planned', stats.planned], ['Ongoing', stats.ongoing], ['Completed', stats.completed],
    ].map(([label, value]) => `<div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">${label}</div><strong class="fs-4">${Number(value || 0).toLocaleString()}</strong></div></div></div>`).join('');
    const upcomingBody = document.getElementById('arUpcoming');
    if (upcomingBody) upcomingBody.innerHTML = this.state.upcoming.length ? this.state.upcoming.map((activity) => `<tr><td>${this.esc(activity.title || 'Activity')}</td><td>${this.esc(activity.category_name || '—')}</td><td>${this.esc(activity.start_date || '—')}</td><td>${Number(activity.days_until_start || 0)}</td><td>${Number(activity.participant_count || 0)}</td></tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted py-4">No upcoming activities.</td></tr>';
    const listBody = document.getElementById('arList');
    if (listBody) listBody.innerHTML = this.state.activities.length ? this.state.activities.map((activity) => `<tr><td>${this.esc(activity.title || 'Activity')}</td><td>${this.esc(activity.category_name || '—')}</td><td>${this.esc(activity.status || '—')}</td><td>${Number(activity.participant_count || 0)}</td><td>${this.esc(activity.start_date || '—')}</td></tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted py-4">No activities found.</td></tr>';
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('activities')) return window.showNotification?.('You do not have permission to export activity data.', 'error');
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const rows = [['Title', 'Category', 'Status', 'Participants', 'Start Date'], ...this.state.activities.map((activity) => [activity.title, activity.category_name, activity.status, activity.participant_count, activity.start_date])];
    window.KingswayFileLifecycle?.exportText?.(rows.map((row) => row.map(quote).join(',')).join('\r\n'), 'activity-reports.csv', 'text/csv;charset=utf-8;');
  },
  init() {
    if (window.AuthContext?.ready) window.AuthContext.ready().then(() => this.load()); else this.load();
    document.getElementById('arExportBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('arPrintBtn')?.addEventListener('click', () => { if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('activities')) window.print(); });
    window.APIRealtime?.register?.('activity_reports', { refresh: () => this.load() }, ['activities', 'reports']);
  },
};
window.activityReportsController = activityReportsController;
document.addEventListener('DOMContentLoaded', () => activityReportsController.init());
