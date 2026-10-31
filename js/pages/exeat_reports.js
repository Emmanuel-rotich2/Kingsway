/**
 * Exeat reports use the authorized boarding endpoint and render the targets in
 * pages/exeat_reports.php. The API returns the newest 200 permission records.
 */
const exeatReportsController = {
  state: { records: [], requestId: 0 },

  escape(value) {
    const element = document.createElement('span');
    element.textContent = String(value ?? '');
    return element.innerHTML;
  },

  rows(response) {
    if (Array.isArray(response)) return response;
    if (Array.isArray(response?.data)) return response.data;
    if (Array.isArray(response?.data?.items)) return response.data.items;
    if (Array.isArray(response?.items)) return response.items;
    return [];
  },

  async load() {
    const requestId = ++this.state.requestId;
    this.renderLoading();

    try {
      const response = await window.API.callAPI('/boarding/exeats', 'GET');
      if (requestId !== this.state.requestId) return;
      this.state.records = this.rows(response);
      this.render();
    } catch (error) {
      if (requestId !== this.state.requestId) return;
      console.error('Exeat report could not be loaded:', error);
      this.renderError();
    }
  },

  renderLoading() {
    const kpis = document.getElementById('xrKpis');
    const statusBody = document.getElementById('xrStatusBody');
    const recordsBody = document.getElementById('xrBody');
    if (kpis) kpis.innerHTML = '';
    if (statusBody) statusBody.innerHTML = '<tr><td colspan="3" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading leave requests…</td></tr>';
    if (recordsBody) recordsBody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading leave requests…</td></tr>';
  },

  renderError() {
    const message = '<tr><td colspan="3" class="text-center py-4 text-danger">Leave requests could not be loaded. Refresh to try again.</td></tr>';
    const statusBody = document.getElementById('xrStatusBody');
    const recordsBody = document.getElementById('xrBody');
    const kpis = document.getElementById('xrKpis');
    if (kpis) kpis.innerHTML = '<div class="col-12"><div class="alert alert-danger mb-0">Leave request data could not be loaded.</div></div>';
    if (statusBody) statusBody.innerHTML = message;
    if (recordsBody) recordsBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Leave requests could not be loaded. Refresh to try again.</td></tr>';
  },

  render() {
    const records = this.state.records;
    const kpis = document.getElementById('xrKpis');
    const statusBody = document.getElementById('xrStatusBody');
    const recordsBody = document.getElementById('xrBody');
    if (!kpis || !statusBody || !recordsBody) return;

    const counts = records.reduce((result, record) => {
      const status = String(record.status || 'unknown').trim().toLowerCase();
      result[status] = (result[status] || 0) + 1;
      return result;
    }, {});
    const total = records.length;
    const pending = counts.pending || 0;
    const approved = counts.approved || counts.out || 0;
    const completed = counts.returned || counts.completed || 0;

    const cards = [
      ['Recent Requests', total, 'primary'],
      ['Pending', pending, 'warning'],
      ['Approved / Out', approved, 'success'],
      ['Returned', completed, 'info'],
    ];
    kpis.innerHTML = cards.map(([label, value, color]) => `
      <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
          <div class="small text-muted">${label}</div>
          <div class="fs-3 fw-bold text-${color}">${value}</div>
        </div></div>
      </div>`).join('');

    const statuses = Object.entries(counts).sort((a, b) => b[1] - a[1]);
    statusBody.innerHTML = statuses.length
      ? statuses.map(([status, count]) => `<tr>
          <td class="text-capitalize">${this.escape(status)}</td>
          <td>${count}</td>
          <td>${total ? `${Math.round((count / total) * 100)}%` : '0%'}</td>
        </tr>`).join('')
      : '<tr><td colspan="3" class="text-center py-4 text-muted">No leave requests found.</td></tr>';

    recordsBody.innerHTML = records.length
      ? records.map((record) => `
          <tr>
            <td class="small">${this.escape(record.admission_no || '—')}</td>
            <td class="small fw-semibold">${this.escape(record.student_name || '—')}</td>
            <td class="small">${this.escape(record.dormitory_name || '—')}</td>
            <td class="small">${this.escape(record.permission_type_name || 'Leave')}</td>
            <td class="small">${this.escape(record.start_date || '—')}</td>
            <td class="small">${this.escape(record.end_date || '—')}</td>
            <td class="small"><span class="badge bg-${this.statusColor(record.status)} text-capitalize">${this.escape(record.status || 'unknown')}</span></td>
          </tr>`).join('')
      : '<tr><td colspan="7" class="text-center py-4 text-muted">No recent leave requests.</td></tr>';
  },

  statusColor(status) {
    switch (String(status || '').toLowerCase()) {
      case 'approved':
      case 'out':
      case 'returned':
      case 'completed': return 'success';
      case 'pending': return 'warning';
      case 'rejected':
      case 'cancelled': return 'danger';
      default: return 'secondary';
    }
  },

  init() {
    this.load();
    window.APIRealtime?.register?.('exeat_reports', {
      refresh: () => this.load(),
    }, ['boarding', 'students']);
  },
};

window.exeatReportsController = exeatReportsController;
document.addEventListener('DOMContentLoaded', () => exeatReportsController.init());
