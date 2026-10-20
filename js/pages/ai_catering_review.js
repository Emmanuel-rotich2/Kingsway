/**
 * AI Catering Reviews Controller
 * Migrated to use AgentTaskPanel with Node gateway
 * Single workflow: catering consumption review
 */

(function () {
    'use strict';

    const esc = (value) => { const el = document.createElement('div'); el.textContent = String(value ?? ''); return el.innerHTML; };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);

    let panel = null;

    async function init() {
        if (window.AI_FEATURES.shouldUseAgentTaskPanel('ai_catering_review')) {
            const host = document.getElementById('aiCateringReviews');
            if (!host) return;

            if (!panel) panel = new window.AgentTaskPanel({
                container: host,
                tool: 'reports.aggregate',
                question: '',
                route: 'catering',
                module: 'consumption_reviews',
                targetComponent: null,
                componentId: 'ai-catering-review',
                onComplete: (result) => {
                    notify('Catering review ready!', 'success');
                },
                onError: (error) => {
                    notify(error.message || 'Failed to generate catering review', 'danger');
                },
                onProgress: (progress) => {
                    if (progress.type === 'status') {
                        console.log('[Catering Review]', progress.text);
                    }
                },
            });

            document.getElementById('queueAiCateringReview')?.addEventListener('click', async () => {
                const button = document.getElementById('queueAiCateringReview');
                if (!button) return;
                button.disabled = true;
                try {
                    const range = () => ({ from: document.getElementById('fcFrom')?.value || new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10), to: document.getElementById('fcTo')?.value || new Date().toISOString().slice(0, 10) });
                    panel.question = `Generate a catering consumption review from ${range().from} to ${range().to}. Analyze meal uptake data, identify variances, and provide recommendations for menu planning and portion control.`;
                    await panel.start();
                } catch (error) {
                    notify(error.message || 'Unable to queue catering review', 'danger');
                } finally {
                    button.disabled = false;
                }
            });
        } else {
            // Legacy PHP queue polling pattern (original implementation)
            const host = document.getElementById('aiCateringReviews');
            if (!host || !window.API?.catering) return;
            try {
                const own = await window.API.catering.getAiConsumptionReviews('own');
                let review = { drafts: [] };
                try { review = await window.API.catering.getAiConsumptionReviews('review'); } catch (_) {}
                const rows = [...(own?.data?.drafts || own?.drafts || []).map((x) => ({ ...x, __review: false })), ...(review?.data?.drafts || review?.drafts || []).map((x) => ({ ...x, __review: true }))].filter((x) => x.metadata?.subject_type === 'catering_consumption_review');
                host.innerHTML = rows.length ? rows.map((draft) => { const body = draft.draft || {}; const approve = draft.__review && draft.status === 'pending_approval'; return `<div class="col-12"><article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(body.title || 'Catering consumption review')}</strong><span class="badge text-bg-secondary">${esc(draft.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(body.body || 'Review is still being generated.')}</p>${Array.isArray(body.next_steps) && body.next_steps.length ? `<ul class="small mb-2">${body.next_steps.map((step) => `<li>${esc(step)}</li>`).join('')}</ul>` : ''}${approve ? `<button class="btn btn-sm btn-success" data-ai-catering-approve="${Number(draft.id)}">Approve guidance</button>` : ''}${draft.status === 'approved' ? '<div class="small text-info">Approved for staff guidance. Catering records and controls remain authoritative.</div>' : ''}</article></div>`; }).join('') : '<div class="col-12 small text-muted">No catering variance review has been requested.</div>';
                host.querySelectorAll('[data-ai-catering-approve]').forEach((button) => button.addEventListener('click', async () => { try { await window.API.catering.approveAiConsumptionReview(button.dataset.aiCateringApprove); window.showNotification?.('Catering guidance approved', 'success'); await init(); } catch (error) { window.showNotification?.(error.message || 'Unable to approve catering guidance', 'danger'); } }));
            } catch (_) { host.innerHTML = '<div class="col-12 small text-warning">Catering assistance is temporarily unavailable.</div>'; }
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();