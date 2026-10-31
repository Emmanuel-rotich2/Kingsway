/** Boarding report view backed by the canonical occupancy and roll-call APIs. */
const boardingReportsController = {
  state: { stats: {}, occupancy: [] },
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]); },
  data(response) { return response?.data?.data ?? response?.data ?? response ?? {}; },
  async load() {
    const body = document.getElementById('brBody');
    if (body) body.innerHTML = '<tr><td colspan="8" class="text-center py-4">Loading dormitory occupancy…</td></tr>';
    const error = document.getElementById('brError'); if (error) error.classList.add('d-none');
    try {
      const [statsResponse, occupancyResponse] = await Promise.all([
        this.API('GET', '/api/boarding/stats'), this.API('GET', '/api/boarding/occupancy')
      ]);
      this.state.stats = this.data(statsResponse);
      const rows = this.data(occupancyResponse);
      this.state.occupancy = Array.isArray(rows) ? rows : [];
      this.render();
    } catch (exception) {
      if (body) body.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Boarding data could not be loaded.</td></tr>';
      if (error) { error.textContent = exception?.message || 'Boarding data could not be loaded.'; error.classList.remove('d-none'); }
    }
  },
  render() {
    const stats = this.state.stats;
    const metrics = [
      ['Boarders', stats.total_boarders ?? '—'], ['Dormitories', stats.dormitories ?? '—'],
      ['Bed capacity', stats.total_capacity ?? '—'], ['Occupied beds', stats.assigned_beds ?? '—'],
      ['Available beds', stats.available_beds ?? '—'], ['Occupancy', `${stats.occupancy_rate ?? '—'}%`],
      ['Present tonight', stats.present_tonight ?? '—'], ['Roll-call coverage', `${stats.roll_call_pct ?? '—'}%`]
    ];
    const kpis = document.getElementById('brKpis');
    if (kpis) kpis.innerHTML = metrics.map(([label, value]) => `<div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">${label}</div><div class="fs-4 fw-semibold">${this.esc(value)}</div></div></div></div>`).join('');
    const body = document.getElementById('brBody');
    if (!body) return;
    body.innerHTML = this.state.occupancy.length ? this.state.occupancy.map((row, index) => `<tr>
      <td>${this.esc(row.dormitory_name || row.name || '—')}</td><td>${this.esc(row.code || '—')}</td>
      <td>${this.esc(row.gender || '—')}</td><td>${this.esc(row.house_parent || '—')}</td>
      <td class="text-end">${Number(row.capacity || 0)}</td><td class="text-end">${Number(row.occupied || 0)}</td>
      <td class="text-end">${Number(row.available || 0)}</td><td class="text-end">${Number(row.capacity) > 0 ? `${(Number(row.occupied || 0) / Number(row.capacity) * 100).toFixed(1)}%` : '—'}</td>
    </tr>`).join('') : '<tr><td colspan="8" class="text-center py-4 text-muted">No active dormitories are configured.</td></tr>';
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('boarding')) return window.showNotification?.('You do not have permission to export boarding data.', 'error');
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const rows = [['Dormitory','Code','Gender','House Parent','Capacity','Occupied','Available'], ...this.state.occupancy.map((row) => [row.dormitory_name || row.name,row.code,row.gender,row.house_parent,row.capacity,row.occupied,row.available])];
    window.KingswayFileLifecycle?.exportText?.(rows.map((row) => row.map(quote).join(',')).join('\r\n'), 'boarding-occupancy.csv', 'text/csv;charset=utf-8;');
  },
  init() {
    document.getElementById('brPrint')?.addEventListener('click', () => {
      if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('boarding')) window.print();
    });
    window.APIRealtime?.register?.('boarding_reports', { refresh: () => this.load() }, ['boarding']);
    return this.load();
  }
};
window.boardingReportsController = boardingReportsController;
document.addEventListener('DOMContentLoaded', () => boardingReportsController.init());
