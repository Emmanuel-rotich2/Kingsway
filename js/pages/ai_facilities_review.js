/**
 * AI Facilities Reviews Controller
 * Migrated to use AgentTaskPanel with Node gateway
 * Single workflow: facilities maintenance review
 */

(function () {
    'use strict';

    const esc = (value) => { const el = document.createElement('div'); el.textContent = String(value ?? ''); return el.innerHTML; };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);

    let panel = null;

    async function init() {
        if (window.AI_FEATURES.shouldUseAgentTaskPanel('ai_facilities_review')) {
            const host = document.getElementById('aiFacilitiesReviews');
            if (!host) return;

            if (!panel) panel = new window.AgentTaskPanel({
                container: host,
                tool: 'reports.aggregate',
                question: '',
                route: 'maintenance',
                module: 'facilities_reviews',
                targetComponent: null,
                componentId: 'ai-facilities-review',
                onComplete: (result) => {
                    notify('Facilities maintenance review ready!', 'success');
                },
                onError: (error) => {
                    notify(error.message || 'Failed to generate facilities maintenance review', 'danger');
                },
                onProgress: (progress) => {
                    if (progress.type === 'status') {
                        console.log('[Facilities Review]', progress.text);
                    }
                },
            });

            document.getElementById('queueAiFacilitiesReview')?.addEventListener('click', async () => {
                const button = document.getElementById('queueAiFacilitiesReview');
                if (!button) return;
                button.disabled = true;
                try {
                    panel.question = `Generate a facilities maintenance review for the current academic period. Analyze maintenance requests, identify recurring issues, and recommend proactive measures and resource allocation.`;
                    await panel.start();
                } catch (error) {
                    notify(error.message || 'Unable to queue facilities review', 'danger');
                } finally {
                    button.disabled = false;
                }
            });
        } else {
            // Legacy PHP queue polling pattern (original implementation)
            const host = document.getElementById('aiFacilitiesReviews');
            if (!host || !window.API?.maintenance) return;
            try {
                const own = await window.API.maintenance.getAiFacilitiesReviews({ scope: 'own' });
                let review = { data: { drafts: [] } };
                try { review = await window.API.maintenance.getAiFacilitiesReviews({ scope: 'review' }); } catch (_) {}
                const rows = [
                    ...(own?.data?.drafts || own?.drafts || []).map((item) => ({ ...item, __review: false })),
                    ...(review?.data?.drafts || review?.drafts || []).map((item) => ({ ...item, __review: true })),
                ].filter((item) => item.metadata?.subject_type === 'maintenance_facilities_review');
                host.innerHTML = rows.length ? rows.map((draft) => { const body = draft.draft || {}; const approve = draft.__review && draft.status === 'pending_approval'; return `<div class="col-12"><article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(body.title || 'Facilities maintenance review')}</strong><span class="badge text-bg-secondary">${esc(draft.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(body.body || 'Review is still being generated.')}</p>${Array.isArray(body.next_steps) && body.next_steps.length ? `<ul class="small mb-2">${body.next_steps.map((step) => `<li>${esc(step)}</li>`).join('')}</ul>` : ''}${approve ? `<button class="btn btn-sm btn-success" data-ai-facilities-approve="${Number(draft.id)}">Approve guidance</button>` : ''}${draft.status === 'approved' ? '<div class="small text-info">Approved for staff guidance. Maintenance records and controls remain authoritative.</div>' : ''}</article></div>`; }).join('') : '<div class="col-12 small text-muted">No facilities maintenance review has been requested.</div>';
                host.querySelectorAll('[data-ai-facilities-approve]').forEach((button) => button.addEventListener('click', async () => { try { await window.API.maintenance.approveAiFacilitiesReview(button.dataset.aiFacilitiesApprove); window.showNotification?.('Maintenance guidance approved', 'success'); await init(); } catch (error) { window.showNotification?.(error.message || 'Unable to approve maintenance guidance', 'danger'); } }));
                // Generate button is inert on the legacy path without this
                // listener: the AgentTaskPanel branch that used to wire it never
                // runs while the Node gateway flag is off.
                const generate = document.getElementById('queueAiFacilitiesReview');
                if (generate) {
                    generate.addEventListener('click', async () => {
                        generate.disabled = true;
                        try {
                            await window.API.maintenance.queueAiFacilitiesReview({ scope: 'current_period' });
                            notify('Facilities review queued. It appears here when ready.', 'success');
                            await init();
                        } catch (error) {
                            notify(error.message || 'Unable to queue facilities review', 'danger');
                        } finally {
                            generate.disabled = false;
                        }
                    });
                }
            } catch (_) { host.innerHTML = '<div class="col-12 small text-warning">Maintenance assistance is temporarily unavailable.</div>'; }
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();