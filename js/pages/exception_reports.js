/**
 * Exception Reports Controller
 * Financial anomalies: unmatched payments, duplicate receipts, policy breaches.
 * API: /finance/exception-report, /payments/unmatched
 */

const exceptionReportsController = {

  _data: [],
  _activeTab: 'all',
  _activeFilter: 'all',

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([this._loadStats(), this._loadData()]);
  },

  _loadStats: async function () {
    try {
      const [rException, rUnmatched] = await Promise.allSettled([
        callAPI('/finance/exception-report', 'GET'),
        callAPI('/payments/unmatched', 'GET'),
      ]);
      const exceptions = this._extract(rException);
      const unmatched  = this._extract(rUnmatched);
      const critical   = exceptions.filter(e => (e.severity||'').toLowerCase() === 'critical').length;
      const high       = exceptions.filter(e => (e.severity||'').toLowerCase() === 'high').length;

      this._set('erStatTotal',     exceptions.length);
      this._set('erStatUnmatched', unmatched.length);
      this._set('erStatCritical',  critical);
      this._set('erStatHigh',      high);
    } catch (e) { console.warn('Stats failed:', e); }
  },

  _loadData: async function () {
    const tbody = document.getElementById('erTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const [rException, rUnmatched] = await Promise.allSettled([
        callAPI('/finance/exception-report', 'GET'),
        callAPI('/payments/unmatched', 'GET'),
      ]);
      const exceptions = this._extract(rException).map(e => ({ ...e, _source: 'exception' }));
      const unmatched  = this._extract(rUnmatched).map(e => ({ ...e, _source: 'unmatched', exception_type: e.exception_type || 'Unmatched Payment', severity: e.severity || 'medium' }));
      this._data = [...exceptions, ...unmatched];
      this._render(this._data);
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-4">Failed to load exception reports.</td></tr>`;
    }
  },

  _render: function (items) {
    const tbody = document.getElementById('erTableBody');
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>No exceptions found.</td></tr>';
      return;
    }

    const severityCls = { critical: 'danger', high: 'warning', medium: 'info', low: 'secondary' };
    const statusCls   = { open: 'danger', investigating: 'warning', resolved: 'success', dismissed: 'secondary' };

    tbody.innerHTML = items.map(e => {
      const severity = (e.severity || 'medium').toLowerCase();
      const status   = (e.status || 'open').toLowerCase();
      return `<tr class="${severity==='critical'?'table-danger':severity==='high'?'table-warning':''}">
        <td>${this._esc(e.reference || e.receipt_number || e.id || '—')}</td>
        <td>
          <span class="badge bg-${severityCls[severity]||'secondary'} me-1">${this._esc(severity.toUpperCase())}</span>
          ${this._esc(e.exception_type || e.type || '—')}
        </td>
        <td class="small text-muted" style="max-width:200px;">${this._esc((e.description || e.notes || '—').substring(0,80))}</td>
        <td class="fw-bold">KES ${Number(e.amount||0).toLocaleString()}</td>
        <td>${this._esc(e.affected_party || e.student_name || e.payer_name || '—')}</td>
        <td>${this._esc(e.detected_at || e.created_at || '—')}</td>
        <td>
          <span class="badge bg-${statusCls[status]||'secondary'}">${this._esc(e.status || 'open')}</span>
          ${status === 'open' ? `
            <div class="btn-group btn-group-sm ms-2">
              <button class="btn btn-outline-secondary" onclick="exceptionReportsController.updateStatus(${e.id},'investigating')" title="Investigate">
                <i class="bi bi-search"></i>
              </button>
              <button class="btn btn-outline-success" onclick="exceptionReportsController.updateStatus(${e.id},'resolved')" title="Resolve">
                <i class="bi bi-check2"></i>
              </button>
              <button class="btn btn-outline-secondary" onclick="exceptionReportsController.updateStatus(${e.id},'dismissed')" title="Dismiss">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>` : ''}
        </td>
      </tr>`;
    }).join('');
  },

  filterBySeverity: function (severity, btn) {
    this._activeFilter = severity;
    document.querySelectorAll('#erSeverityTabs .nav-link').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    const filtered = severity === 'all' ? this._data : this._data.filter(e => (e.severity||'medium').toLowerCase() === severity);
    this._render(filtered);
  },

  updateStatus: async function (id, status) {
    const confirmMsg = { resolved: 'Mark this exception as resolved?', dismissed: 'Dismiss this exception?', investigating: 'Mark as investigating?' };
    if (!confirm(confirmMsg[status] || 'Update status?')) return;
    try {
      await callAPI('/finance/exception-report/' + id, 'PUT', { status });
      showNotification('Exception status updated.', 'success');
      await Promise.all([this._loadStats(), this._loadData()]);
    } catch (e) { showNotification(e.message || 'Update failed.', 'danger'); }
  },

  exportCSV: function () {
    if (!this._data.length) { showNotification('No data to export.', 'warning'); return; }
    const header = ['Reference','Type','Severity','Amount','Affected Party','Detected','Status'];
    const rows = [header.join(','), ...this._data.map(e => [
      `"${e.reference||e.id||''}"`, `"${e.exception_type||''}"`, `"${e.severity||''}"`,
      e.amount||0, `"${e.affected_party||e.student_name||''}"`,
      `"${e.detected_at||e.created_at||''}"`, `"${e.status||'open'}"`,
    ].join(','))];
    const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `exception_report_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
  },

  _extract: function (settled) {
    if (settled.status !== 'fulfilled') return [];
    const r = settled.value;
    return Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => exceptionReportsController.init());
