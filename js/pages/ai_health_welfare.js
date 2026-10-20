/**
 * AI Health and Welfare Reviews Controller
 * Migrated to use AgentTaskPanel with Node gateway
 * Single workflow: welfare review
 */

(function () {
    'use strict';

    const esc = (v) => { const e = document.createElement('div'); e.textContent = String(v ?? ''); return e.innerHTML; };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);

    let panel = null;

    async function init() {
        if (window.AI_FEATURES.shouldUseAgentTaskPanel('ai_health_welfare')) {
            const host = document.getElementById('aiHealthWelfareReviews');
            if (!host) return;

            if (!panel) panel = new window.AgentTaskPanel({
                container: host,
                tool: 'reports.aggregate',
                question: '',
                route: 'system',
                module: 'welfare_reviews',
                targetComponent: null,
                componentId: 'ai-health-welfare',
                onComplete: (result) => {
                    notify('Health and welfare review ready!', 'success');
                },
                onError: (error) => {
                    notify(error.message || 'Failed to generate health and welfare review', 'danger');
                },
                onProgress: (progress) => {
                    if (progress.type === 'status') {
                        console.log('[Health Welfare Review]', progress.text);
                    }
                },
            });

            document.getElementById('queueAiHealthWelfare')?.addEventListener('click', async () => {
                const button = document.getElementById('queueAiHealthWelfare');
                if (!button) return;
                button.disabled = true;
                try {
                    panel.question = `Generate a health and welfare review for the current academic period. Identify students with health concerns, analyze welfare data, and recommend interventions and referrals.`;
                    await panel.start();
                } catch (error) {
                    notify(error.message || 'Unable to queue health review', 'danger');
                } finally {
                    button.disabled = false;
                }
            });
        } else {
            // Legacy PHP queue polling pattern (original implementation)
            const host = document.getElementById('aiHealthWelfareReviews');
            if (!host || !window.API?.system) return;
            try {
                const own = await window.API.system.getAiWelfareReviews('own'); let review = { drafts: [] };
                try { review = await window.API.system.getAiWelfareReviews('review'); } catch (_) {}
                const rows = [...(own?.data?.drafts || own?.drafts || []).map(x => ({ ...x, __review: false })), ...(review?.data?.drafts || review?.drafts || []).map(x => ({ ...x, __review: true }))];
                host.innerHTML = rows.length ? rows.map(x => { const d = x.draft || {}; const approve = x.__review && x.status === 'pending_approval'; return `<div class="col-12"><article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(d.title || 'Health and welfare review')}</strong><span class="badge text-bg-secondary">${esc(x.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(d.body || 'Review is still being generated.')}</p>${approve ? `<button class="btn btn-sm btn-success" data-ai-health-approve="${Number(x.id)}">Approve guidance</button>` : ''}${x.status === 'approved' ? '<div class="small text-info">Approved for staff guidance; health records remain authoritative.</div>' : ''}</article></div>`; }).join('') : '<div class="col-12 small text-muted">No health review has been requested.</div>';
                host.querySelectorAll('[data-ai-health-approve]').forEach((b) => b.addEventListener('click', async () => { try { await window.API.system.approveAiWelfareReview(b.dataset.aiHealthApprove); window.showNotification?.('Health guidance approved', 'success'); await init(); } catch (e) { window.showNotification?.(e.message || 'Unable to approve health guidance', 'danger'); } }));
            } catch (_) { host.innerHTML = '<div class="col-12 small text-warning">Health assistance is temporarily unavailable.</div>'; }
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();