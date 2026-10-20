/**
 * AI Staff HR Reviews Controller
 * Migrated to use AgentTaskPanel with Node gateway
 * Single workflow: HR review
 */

(function () {
    'use strict';

    const esc = (v) => { const e = document.createElement('div'); e.textContent = String(v ?? ''); return e.innerHTML; };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);

    let panel = null;

    async function init() {
        if (window.AI_FEATURES.shouldUseAgentTaskPanel('ai_staff_hr')) {
            const host = document.getElementById('aiHrReviews');
            if (!host) return;

            if (!panel) panel = new window.AgentTaskPanel({
                container: host,
                tool: 'reports.aggregate',
                question: '',
                route: 'system',
                module: 'hr_reviews',
                targetComponent: null,
                componentId: 'ai-staff-hr',
                onComplete: (result) => {
                    notify('HR review ready!', 'success');
                },
                onError: (error) => {
                    notify(error.message || 'Failed to generate HR review', 'danger');
                },
                onProgress: (progress) => {
                    if (progress.type === 'status') {
                        console.log('[HR Review]', progress.text);
                    }
                },
            });

            document.getElementById('queueAiHrReview')?.addEventListener('click', async () => {
                const button = document.getElementById('queueAiHrReview');
                if (!button) return;
                button.disabled = true;
                try {
                    panel.question = `Generate a staff HR review for the current period. Analyze performance data, identify trends, and recommend personnel actions and development interventions.`;
                    await panel.start();
                } catch (error) {
                    notify(error.message || 'Unable to queue HR review', 'danger');
                } finally {
                    button.disabled = false;
                }
            });
        } else {
            // Legacy PHP queue polling pattern (original implementation)
            const host = document.getElementById('aiHrReviews');
            if (!host || !window.API?.system) return;
            try {
                const own = await window.API.system.getAiHrReviews('own'); let review = { drafts: [] };
                try { review = await window.API.system.getAiHrReviews('review'); } catch (_) {}
                const rows = [...(own?.data?.drafts || own?.drafts || []).map(x => ({ ...x, __review: false })), ...(review?.data?.drafts || review?.drafts || []).map(x => ({ ...x, __review: true }))];
                host.innerHTML = rows.length ? rows.map(x => { const d = x.draft || {}; return `<div class="col-12"><article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(d.title || 'Staff HR review')}</strong><span class="badge text-bg-secondary">${esc(x.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(d.body || 'Review is still being generated.')}</p>${x.__review && x.status === 'pending_approval' ? `<button class="btn btn-sm btn-success" data-ai-hr-approve="${Number(x.id)}">Approve guidance</button>` : ''}</article></div>`; }).join('') : '<div class="col-12 small text-muted">No HR review has been requested.</div>';
                host.querySelectorAll('[data-ai-hr-approve]').forEach((b) => b.addEventListener('click', async () => { try { await window.API.system.approveAiHrReview(b.dataset.aiHrApprove); window.showNotification?.('HR guidance approved', 'success'); await init(); } catch (e) { window.showNotification?.(e.message || 'Unable to approve HR guidance', 'danger'); } })); }
            } catch (_) { host.innerHTML = '<div class="col-12 small text-warning">HR assistance is temporarily unavailable.</div>'; }
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();