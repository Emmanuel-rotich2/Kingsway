/**
 * AI Payment Reconciliation Controller
 * Migrated to use AgentTaskPanel with Node gateway
 * Single workflow: reconciliation review generation
 */

(function () {
    'use strict';

    const esc = (value) => { const d = document.createElement('div'); d.textContent = String(value ?? ''); return d.innerHTML; };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);

    let panel = null;

    async function init() {
        const el = document.getElementById('aiReconciliationDrafts');
        if (!el) return;

        if (window.AI_FEATURES.shouldUseAgentTaskPanel('payment_reconciliation_ai')) {
            // Use AgentTaskPanel
            if (!panel) {
                panel = new window.AgentTaskPanel({
                    container: el,
                    tool: 'reports.aggregate',
                    question: '',
                    route: 'finance',
                    module: 'reconciliation_reviews',
                    targetComponent: null,
                    componentId: 'ai-payment-reconciliation',
                    onComplete: (result) => {
                        notify('Reconciliation review ready!', 'success');
                    },
                    onError: (error) => {
                        notify(error.message || 'Failed to generate reconciliation review', 'danger');
                    },
                    onProgress: (progress) => {
                        if (progress.type === 'status') {
                            console.log('[Reconciliation Review]', progress.text);
                        }
                    },
                });
            }

            const b = document.getElementById('queueAiReconciliationReview');
            if (b) b.disabled = true;
            try {
                panel.question = `Generate a payment reconciliation review for the current period. Analyze outstanding balances, identify unmatched transactions, aged payables/receivables, and provide recommendations for cash flow management and resolution.`;
                await panel.start();
            } catch (error) {
                notify(error.message || 'Unable to queue reconciliation review', 'danger');
            } finally {
                if (b) b.disabled = false;
            }
        } else {
            // Legacy PHP queue polling pattern (original implementation)
            let own = [];
            try { own = await window.API.finance.getAiReconciliationDrafts('own'); } catch (_) { }
            let review = { drafts: [] };
            try { review = await window.API.finance.getAiReconciliationDrafts('review'); } catch (_) { }
            const drafts = [...(own?.data?.drafts || own?.drafts || []).map(x => ({ ...x, __review: false })), ...(review?.data?.drafts || review?.drafts || []).map(x => ({ ...x, __review: true }))];
            el.innerHTML = drafts.length ? drafts.map(d => { const x = d.draft || {}; const approve = d.__review && d.status === 'pending_approval'; return `<div class="col-12 col-xl-6"><article class="card h-100 bg-light border-0"><div class="card-body"><div class="d-flex justify-content-between"><strong>${esc(x.title||'Reconciliation review')}</strong><span class="badge text-bg-secondary">${esc(d.status||'queued')}</span></div><p class="small mt-2">${esc(x.body||'Review is still being generated.')}</p>${Array.isArray(x.next_steps)&&x.next_steps.length?`<ul class="small">${x.next_steps.map(s=>`<li>${esc(s)}</li>`).join('')}</ul>`:''}${approve?`<button class="btn btn-sm btn-success" onclick="paymentReconciliationController.approveAiReview(${Number(d.id)})">Approve review notes</button>`:''}${d.status==='approved'?'<div class="alert alert-info small mb-0">Approved for staff guidance; use the normal controls below for resolution.</div>':''}</div></article></div>`; }).join('') : '<div class="col-12 small text-muted">No reconciliation review has been requested.</div>';
            el.querySelectorAll('[onclick^="paymentReconciliationController.approveAiReview"]').forEach((btn) => btn.addEventListener('click', async () => { try { const id = btn.onclick.toString().match(/approveAiReview\((\d+)\)/)[1]; await window.API.finance.approveAiReconciliationReview(id); notify('Reconciliation review approved', 'success'); await init(); } catch (e) { notify(e.message || 'Unable to approve reconciliation review', 'danger'); } }));
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();