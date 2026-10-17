/**
 * AI Boarding Summary Controller
 * AI-generated boarding insights and summaries.
 */
const aiBoardingSummaryController = {
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
        this.API('GET', '/api/boarding/ai-summary'),
        this.API('GET', '/api/boarding/ai-insights'),
      ]);
      this.state.summary = summary?.data || summary || {};
      this.state.insights = Array.isArray(insights?.data) ? insights.data : (Array.isArray(insights) ? insights : []);
      this.render();
    } catch (e) { console.error(e); }
  },

  render() {
    const summary = this.state.summary;
    const insights = this.state.insights;
    document.getElementById('absSummaryText')?.textContent = summary?.overview || 'No summary available.';
    document.getElementById('absRiskLevel')?.textContent = summary?.risk_level || '—';
    document.getElementById('absRecommendations')?.textContent = summary?.recommendations || '—';
    const insightsBody = document.getElementById('absInsightsBody');
    if (!insights.length) { insightsBody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No AI insights.</td></tr>'; return; }
    insightsBody.innerHTML = insights.map(i => `<tr>
      <td class="small"><span class="badge bg-${i.type === 'alert' ? 'danger' : i.type === 'warning' ? 'warning' : 'info'}">${this.esc(i.type)}</span></td>
      <td class="small">${this.esc(i.title || '—')}</td>
      <td class="small">${this.esc(i.description || '—')}</td>
    </tr>`).join('');
  },

  async refresh() {
    try {
      await this.API('POST', '/api/boarding/ai-summary/refresh');
      this.notify('Summary refreshed', 'success');
      await this.load();
    } catch (e) { this.notify('Refresh failed', 'danger'); }
  },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
    window.APIRealtime?.register?.("ai_boarding_summary", {
      refresh: async () => await this.load()
    }, ["boarding", "ai"]);
  },
};

window.aiBoardingSummaryController = aiBoardingSummaryController;
document.addEventListener('DOMContentLoaded', () => aiBoardingSummaryController.init().catch(() => {}));