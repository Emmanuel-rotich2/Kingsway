/**
 * welfare_summary.js — Welfare Summary Controller (standalone page).
 * Student welfare case analytics by category, priority and status.
 */
const welfareSummaryController = {
  state: { cases: [], meta: {} },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.cases || r || []); },

  group(arr, key) {
    const g = {};
    arr.forEach(x => {
      const k = x[key] || 'unknown';
      g[k] = (g[k] || 0) + 1;
    });
    return g;
  },

  renderBars(groups, tbodyId, total, colorMap) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    const keys = Object.keys(groups).sort((a, b) => groups[b] - groups[a]);
    if (!keys.length) { tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No data.</td></tr>'; return; }
    tbody.innerHTML = keys.map(k => {
      const n = groups[k];
      const pct = total ? (n / total) * 100 : 0;
      const color = colorMap?.[k] || 'bg-primary';
      return `<tr><td class="small fw-semibold">${this.esc(k)}</td><td class="small text-center">${this.esc(n)}</td><td style="width:140px;"><div class="progress" style="height:8px;"><div class="progress-bar ${color}" style="width:${this.esc(pct)}%"></div></div></td></tr>`;
    }).join('');
  },

  async load() {
    const kpis = document.getElementById('wsKpis');
    if (!kpis) return;
    kpis.innerHTML = '';
    ['wsCat','wsPrio','wsStatus'].forEach(id => {
      const body = document.getElementById(id);
      if (body) body.innerHTML = '<tr><td colspan="3" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    });
    try {
      const [casesRes, metaRes] = await Promise.all([
        this.API('GET', '/students/welfare-cases').catch(() => []),
        this.API('GET', '/students/welfare-meta').catch(() => ({})),
      ]);
      this.state.cases = this.norm(casesRes);
      this.state.meta = metaRes?.data || metaRes || {};
      this.render();
    } catch (e) {
      const target = document.getElementById('wsKpis');
      if (target) target.innerHTML = `<p class="text-danger">${this.esc(e.message || 'Load failed')}</p>`;
    }
  },

  render() {
    if (!document.getElementById('wsKpis')) return;
    const cases = this.state.cases;
    const total = cases.length;
    const open = cases.filter(c => ['open','in_progress'].includes(c.status)).length;
    const urgent = cases.filter(c => c.priority === 'urgent').length;
    document.getElementById('wsKpis').innerHTML = [
      { label: 'Total Cases', value: total, icon: 'bi-folder2', color: 'primary' },
      { label: 'Open Cases', value: open, icon: 'bi-folder2-open', color: 'warning' },
      { label: 'Urgent', value: urgent, icon: 'bi-exclamation-circle', color: 'danger' },
      { label: 'Resolved / Closed', value: cases.filter(c => ['resolved','closed'].includes(c.status)).length, icon: 'bi-check2-circle', color: 'success' },
    ].map(k => `
      <div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white">
        <div class="d-flex justify-content-between align-items-center">
          <div><div class="small text-muted">${this.esc(k.label)}</div><div class="fs-4 fw-bold">${this.esc(k.value)}</div></div>
          <i class="bi ${k.icon} fs-3 text-${k.color}"></i>
        </div>
      </div></div>`).join('');

    this.renderBars(this.group(cases, 'welfare_category'), 'wsCat', total, { emotional: 'bg-info', social: 'bg-primary', behavioral: 'bg-warning', family: 'bg-secondary', chapel: 'bg-success', pastoral: 'bg-success', referral: 'bg-danger' });
    this.renderBars(this.group(cases, 'priority'), 'wsPrio', total, { urgent: 'bg-danger', high: 'bg-warning text-dark', medium: 'bg-info text-dark', low: 'bg-secondary' });
    this.renderBars(this.group(cases, 'status'), 'wsStatus', total, { open: 'bg-primary', in_progress: 'bg-info text-dark', resolved: 'bg-success', closed: 'bg-secondary', cancelled: 'bg-danger' });
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.welfareSummaryController = welfareSummaryController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => welfareSummaryController.init().catch(() => {}));
} else {
  welfareSummaryController.init().catch(() => {});
}
