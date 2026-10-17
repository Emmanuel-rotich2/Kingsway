/**
 * payment_reports.js — Payment Reports Controller (standalone page).
 * Accountant views fee collection statistics, trends and revenue sources.
 */
const paymentReportsController = {
  state: { referenceCache: null },

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

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  fmtKES(n) { return 'KES ' + Number(n || 0).toLocaleString(); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("finance", "payment-reports-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Payment reports Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    document.getElementById('prStatsCards').innerHTML = '<div class="text-muted small py-2">Loading…</div>';
    document.getElementById('prTrends').textContent = 'Loading…';
    document.getElementById('prSources').textContent = 'Loading…';
    try {
      const cache = this.state.referenceCache;
      const [stats, trends, sources] = await Promise.all([
        cache?.stats !== undefined ? cache.stats : this.API('GET', 'payments/stats'),
        cache?.trends !== undefined ? cache.trends : this.API('GET', 'payments/collection-trends'),
        cache?.sources !== undefined ? cache.sources : this.API('GET', 'payments/revenue-sources'),
      ]);
      this.renderStats(stats?.data ?? stats ?? {});
      this.renderTrends(trends?.data ?? trends ?? {});
      this.renderSources(sources?.data ?? sources ?? {});
    } catch (e) { this.notify(e.message || 'Failed to load reports', 'danger'); }
  },

  renderStats(s) {
    const cards = [
      { label: 'Collected (30 days)', value: this.fmtKES(s.amount), icon: 'bi-cash-stack', color: 'text-success' },
      { label: 'Monthly Collected', value: this.fmtKES(s.monthly_collected), icon: 'bi-calendar-month', color: 'text-primary' },
      { label: 'Collection Rate', value: `${Number(s.percentage || 0)}%`, icon: 'bi-percent', color: 'text-info' },
      { label: 'Outstanding', value: this.fmtKES(s.outstanding), icon: 'bi-exclamation-triangle', color: 'text-danger' },
      { label: 'Total Expected', value: this.fmtKES(s.total_expected), icon: 'bi-bullseye', color: 'text-warning' },
      { label: 'Overdue Accounts', value: Number(s.overdue_count || 0), icon: 'bi-clock-history', color: 'text-secondary' },
    ];
    document.getElementById('prStatsCards').innerHTML = cards.map(c => `
      <div class="col-md-6 col-xl-2">
        <div class="bg-white border rounded-3 p-3 h-100">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi ${c.icon} ${c.color} fs-4"></i>
            <span class="small text-muted">${this.esc(c.label)}</span>
          </div>
          <div class="fw-bold fs-5">${this.esc(c.value)}</div>
        </div>
      </div>`).join('');
  },

  renderTrends(t) {
    const trends = Array.isArray(t?.trends) ? t.trends : (Array.isArray(t) ? t : (t?.monthly || []));
    const el = document.getElementById('prTrends');
    if (!trends.length) { el.textContent = 'No collection data available.'; return; }
    const max = Math.max(...trends.map(x => Number(x.collected || x.total || 0)), 1);
    el.innerHTML = trends.map(x => {
      const v = Number(x.collected || x.total || 0);
      const w = Math.max(2, Math.round((v / max) * 100));
      const label = x.month_label || x.month || x.month_name || '\u2014';
      return `<div class="mb-2">
        <div class="d-flex justify-content-between small"><span>${this.esc(label)}</span><span class="fw-semibold">${this.fmtKES(v)}</span></div>
        <div class="progress" style="height:8px"><div class="progress-bar bg-success" style="width:${w}%"></div></div>
      </div>`;
    }).join('');
  },

  renderSources(s) {
    const sources = s?.sources || [];
    const el = document.getElementById('prSources');
    if (!sources.length) { el.textContent = 'No revenue source data available.'; return; }
    const total = sources.reduce((a, x) => a + Number(x.total || 0), 0) || 1;
    el.innerHTML = sources.map(x => {
      const v = Number(x.total || 0);
      const pct = Math.round((v / total) * 100);
      return `<div class="mb-2">
        <div class="d-flex justify-content-between small"><span>${this.esc(x.source || x.method || '\u2014')}</span><span class="fw-semibold">${this.fmtKES(v)} (${pct}%)</span></div>
        <div class="progress" style="height:8px"><div class="progress-bar bg-primary" style="width:${pct}%"></div></div>
      </div>`;
    }).join('');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("payment_reports", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.load();
      }
    }, ["finance", "payments"]);
  },
};

window.paymentReportsController = paymentReportsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => paymentReportsController.init().catch(() => {}));
} else {
  paymentReportsController.init().catch(() => {});
}
