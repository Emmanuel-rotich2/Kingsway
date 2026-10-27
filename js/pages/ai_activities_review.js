/**
 * AI Activities Reviews Controller
 * Migrated to use AgentTaskPanel with Node gateway
 * Single workflow: activities resource review
 */

(function () {
    'use strict';

    const esc = (value) => {
        const node = document.createElement('span');
        node.textContent = String(value ?? '');
        return node.innerHTML;
    };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);

    let panel = null;

    async function init() {
        // Check if we should use the new Node gateway pattern
        if (window.AI_FEATURES.shouldUseAgentTaskPanel('ai_activities_review')) {
            // Use AgentTaskPanel with SSE streaming
            const container = document.getElementById('aiActivitiesReviews');
            if (!container) return;

            if (!panel) panel = new window.AgentTaskPanel({
                container: container,
                tool: 'reports.aggregate',
                question: '',
                route: 'activities',
                module: 'reviews',
                targetComponent: null,
                componentId: 'ai-activities-review',
                onComplete: (result) => {
                    notify('Activities review ready!', 'success');
                },
                onError: (error) => {
                    notify(error.message || 'Failed to generate activities review', 'danger');
                },
                onProgress: (progress) => {
                    if (progress.type === 'status') {
                        console.log('[Activities Review]', progress.text);
                    }
                },
            });

            document.getElementById('queueAiActivitiesReview')?.addEventListener('click', async () => {
                const button = document.getElementById('queueAiActivitiesReview');
                if (!button) return;
                button.disabled = true;
                try {
                    panel.question = `Generate a review of all activities resource usage for the current academic period. Analyze consumption patterns, identify variances, and provide actionable recommendations.`;
                    await panel.start();
                } catch (error) {
                    notify(error.message || 'Unable to queue activities review', 'danger');
                } finally {
                    button.disabled = false;
                }
            });
        } else {
            // Legacy PHP queue polling pattern
            const host = document.getElementById('aiActivitiesReviews');
            if (!host || !window.API?.system) return;
            try {
                const own = await window.API.system.getAiResourceReviews('own');
                let review = { drafts: [] };
                try { review = await window.API.system.getAiResourceReviews('review'); } catch (_) {}
                const rows = [...(own?.data?.drafts || own?.drafts || []).map((x) => ({ ...x, __review: false })), ...(review?.data?.drafts || review?.drafts || []).map((x) => ({ ...x, __review: true }))];
                host.innerHTML = rows.length ? rows.map(x => { const d = x.draft || {}; return `<div class="col-12"><article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(d.title || 'Activities resource review')}</strong><span class="badge text-bg-secondary">${esc(x.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(d.body || 'Review is still being generated.')}</p>${x.__review && x.status === 'pending_approval' ? `<button class="btn btn-sm btn-success" data-ai-activities-approve="${Number(x.id)}">Approve guidance</button>` : ''}</article></div>`; }).join('') : '<div class="col-12 small text-muted">No activities review has been requested.</div>';
                host.querySelectorAll('[data-ai-activities-approve]').forEach((b) => b.addEventListener('click', async () => { try { await window.API.system.approveAiResourceReview(b.dataset.aiActivitiesApprove); window.showNotification?.('Activities guidance approved', 'success'); await init(); } catch (e) { window.showNotification?.(e.message || 'Unable to approve activities guidance', 'danger'); } }));
                // The generate button must work on the legacy path too. The Node
                // gateway flag is off, and without this listener the button is
                // inert — the panel branch that used to wire it never runs.
                const generate = document.getElementById('queueAiActivitiesReview');
                if (generate) {
                    generate.addEventListener('click', async () => {
                        generate.disabled = true;
                        try {
                            await window.API.system.queueAiResourceReview({
                                review_type: 'activities_resource_usage',
                                note: 'Activities resource usage review for the current academic period.'
                            });
                            notify('Activities review queued. Drafts appear here when ready.', 'success');
                            await init();
                        } catch (error) {
                            notify(error.message || 'Unable to queue activities review', 'danger');
                        } finally {
                            generate.disabled = false;
                        }
                    });
                }
            } catch (_) { host.innerHTML = '<div class="col-12 small text-warning">Activities assistance is temporarily unavailable.</div>'; }
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();