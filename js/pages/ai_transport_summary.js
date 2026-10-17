/**
 * AI Transport Summary Controller
 * AI-generated transport insights and summaries.
 */
const aiTransportSummaryController = {
  state: { referenceCache: null, summary: null, insights: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    // AI summaries typically don't need static references
    this.state.referenceCache = {};
  },

  async load() {
    try {
      const [summary, insights] = await Promise.all([
        this.API('GET', '/api/transport/ai-summary'),
        this.API('GET', '/api/transport/ai-insights'),
      ]);
      this.state.summary = summary?.data || summary || {};
      this.state.insights = Array.isArray(insights?.data) ? insights.data : (Array.isArray(insights) ? insights : []);
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const summary = this.state.summary;
    const insights = this.state.insights;
    document.getElementById('atsSummaryText')?.textContent = summary?.overview || 'No summary available.';
    document.getElementById('atsEfficiency')?.textContent = `${summary?.efficiency_score ?? '—'}%`;
    document.getElementById('atsCostPerKm')?.textContent = `KES ${Number(summary?.cost_per_km || 0).toLocaleString()}`;
    document.getElementById('atsRecommendations')?.textContent = summary?.recommendations || '—';
    const insightsBody = document.getElementById('atsInsightsBody');
    if (!insights.length) { insightsBody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No AI insights.</td></tr>'; return; }
    insightsBody.innerHTML = insights.map(i => `<tr>
      <td class="small"><span class="badge bg-${i.type === 'alert' ? 'danger' : i.type === 'warning' ? 'warning' : 'info'}">${this.esc(i.type)}</span></td>
      <td class="small">${this.esc(i.title || '—')}</td>
      <td class="small">${this.esc(i.description || '—')}</td>
    </tr>`).join('');
  },

  async refresh() {
    try {
      await this.API('POST', '/api/transport/ai-summary/refresh');
      this.notify('Summary refreshed', 'success');
      await this.load();
    } catch (e) { this.notify('Refresh failed', 'danger'); }
  },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
    window.APIRealtime?.register?.("ai_transport_summary", {
      refresh: async () => await this.load()
    }, ["transport", "ai"]);
  },
};

window.aiTransportSummaryController = aiTransportSummaryController;
document.addEventListener('DOMContentLoaded', () => aiTransportSummaryController.init().catch(() => {}));