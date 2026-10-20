/**
 * AI KPI Briefs Controller
 * Migrated to use AgentTaskPanel with Node gateway
 * Single workflow: KPI brief generation
 */

(function () {
    'use strict';

    const esc = (v) => { const d = document.createElement('div'); d.textContent = String(v ?? ''); return d.innerHTML; };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);

    let panel = null;

    async function drafts() {
        const el = document.getElementById('aiKpiBriefs');
        if (!el) return;
        try {
            if (window.AI_FEATURES.shouldUseAgentTaskPanel('ai_kpi_brief')) {
                // Use AgentTaskPanel
                if (!panel) {
                    panel = new window.AgentTaskPanel({
                        container: el,
                        tool: 'reports.aggregate',
                        question: '',
                        route: 'reports',
                        module: 'kpi_briefs',
                        targetComponent: null,
                        componentId: 'ai-kpi-brief',
                        onComplete: (result) => {
                            notify('KPI brief ready!', 'success');
                        },
                        onError: (error) => {
                            notify(error.message || 'Failed to generate KPI brief', 'danger');
                        },
                        onProgress: (progress) => {
                            if (progress.type === 'status') {
                                console.log('[KPI Brief]', progress.text);
                            }
                        },
                    });
                }
                const controller = window.analyticsCatalogueController;
                const definition = controller?.state?.currentDefinition;
                if (!definition || !controller?.state?.currentResult) {
                    window.showNotification?.('Run the report before requesting a KPI brief', 'warning');
                    return;
                }
                const form = document.getElementById('analyticsFilterForm');
                let filters = {};
                if (form) new FormData(form).forEach((v, k) => { if (String(v).trim() !== '') filters[k] = v; });
                panel.question = `Generate a KPI brief for report definition ${definition.code} with filters: ${JSON.stringify(filters) || 'none'}. Provide explanation, key metrics, and actionable insights.`;
                await panel.start();
            } else {
                // Legacy PHP queue polling pattern
                const all = el;
                const own = await window.API.reports.getAiKpiBriefs('own');
                let review = { drafts: [] };
                try { review = await window.API.reports.getAiKpiBriefs('review'); } catch (_) { }
                const code = window.analyticsCatalogueController?.state?.currentDefinition?.code;
                const rows = [...(own?.data?.drafts || own?.drafts || []).map(x => ({ ...x, __review: false })), ...(review?.data?.drafts || review?.drafts || []).map(x => ({ ...x, __review: true }))].filter(x => !code || !x.metadata?.report_code || x.metadata.report_code === code);
                all.innerHTML = rows.length ? rows.map(d => { const x = d.draft || {}; const approve = d.__review && d.status === 'pending_approval'; return `<div class="col-12"><article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(x.title || 'Report explanation')}</strong><span class="badge text-bg-secondary">${esc(d.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(x.body || 'Explanation is still being generated.')}</p>${Array.isArray(x.next_steps) && x.next_steps.length ? `<ul class="small mb-2">${x.next_steps.map(s => `<li>${esc(s)}</li>`).join('')}</ul>` : ''}${approve ? `<button class="btn btn-sm btn-success" data-ai-approve="${Number(d.id)}">Approve explanation</button>` : ''}${d.status === 'approved' ? '<div class="small text-info">Approved for staff guidance. The governed report remains authoritative.</div>' : ''}</article></div>`; }).join('') : '<div class="col-12 small text-muted">No AI explanation has been requested for this report.</div>';
                all.querySelectorAll('[data-ai-approve]').forEach(b => b.addEventListener('click', async () => { try { await window.API.reports.approveAiKpiBrief(b.dataset.aiApprove); window.showNotification?.('Report explanation approved', 'success'); await drafts(); } catch (e) { window.showNotification?.(e.message || 'Unable to approve report explanation', 'danger'); } }));
            }
        } catch (e) { el.innerHTML = '<div class="col-12 small text-warning">Report assistance is temporarily unavailable.</div>'; }
    }

    const queue = async () => {
        const controller = window.analyticsCatalogueController;
        const definition = controller?.state?.currentDefinition;
        const form = document.getElementById('analyticsFilterForm');
        if (!definition || !controller?.state?.currentResult) { window.showNotification?.('Run the report before requesting an explanation', 'warning'); return; }
        const filters = {};
        if (form) new FormData(form).forEach((v, k) => { if (String(v).trim() !== '') filters[k] = v; });
        const b = document.getElementById('queueAiKpiBrief');
        if (b) b.disabled = true;
        try {
            if (window.AI_FEATURES.shouldUseAgentTaskPanel('ai_kpi_brief')) {
                if (!panel) {
                    panel = new window.AgentTaskPanel({
                        container: document.createElement('div'),
                        tool: 'reports.aggregate',
                        question: '',
                        route: 'reports',
                        module: 'kpi_briefs',
                        targetComponent: null,
                        componentId: 'ai-kpi-brief-queuer',
                        onComplete: (result) => {
                            notify('KPI brief ready!', 'success');
                        },
                        onError: (error) => {
                            notify(error.message || 'Failed to generate KPI brief', 'danger');
                        },
                    });
                }
                panel.question = `Generate a KPI brief for report definition ${definition.code} with filters: ${JSON.stringify(filters) || 'none'}. Provide explanation, key metrics, and actionable insights.`;
                await panel.start();
            } else {
                const r = await window.API.reports.queueAiKpiBrief(definition.code, filters);
                window.showNotification?.(`Report explanation queued${r?.job_id ? ` (job ${r.job_id})` : ''}`, 'info');
                for (let i = 0; i < 8; i += 1) { await new Promise(resolve => setTimeout(resolve, i === 0 ? 1000 : 2500)); await drafts(); }
            }
        } finally { if (b) b.disabled = false; }
    };

    const init = () => { document.getElementById('queueAiKpiBrief')?.addEventListener('click', queue); drafts(); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
})();