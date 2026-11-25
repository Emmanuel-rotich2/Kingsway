/**
 * AI Fee Arrears Review panel (student_fees).
 * Queues finance.arrears_review, polls drafts, allows senior-finance approval.
 * Aggregate guidance only — never names learners; collections stay in the
 * normal finance workflow.
 */
(function () {
    'use strict';

    const esc = (value) => { const d = document.createElement('div'); d.textContent = String(value ?? ''); return d.innerHTML; };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);
    let pollTimer = null;
    let pollRun = 0;

    async function load() {
        const el = document.getElementById('aiFeeArrearsDrafts');
        if (!el) return;
        let own = [];
        try { own = await window.API.finance.getAiArrearsDrafts('own'); } catch (_) { }
        let review = { drafts: [] };
        try { review = await window.API.finance.getAiArrearsDrafts('review'); } catch (_) { }
        const drafts = [
            ...(own?.data?.drafts || own?.drafts || []).map(x => ({ ...x, __review: false })),
            ...(review?.data?.drafts || review?.drafts || []).map(x => ({ ...x, __review: true })),
        ];
        el.innerHTML = drafts.length ? drafts.map(d => {
            const x = d.draft || {};
            const approve = d.__review && d.status === 'pending_approval';
            return `<div class="col-12 col-xl-6"><article class="card h-100 bg-light border-0"><div class="card-body"><div class="d-flex justify-content-between"><strong>${esc(x.title || 'Fee arrears review')}</strong><span class="badge text-bg-secondary">${esc(d.status || 'queued')}</span></div><p class="small mt-2">${esc(x.body || 'Review is still being generated.')}</p>${Array.isArray(x.next_steps) && x.next_steps.length ? `<ul class="small">${x.next_steps.map(s => `<li>${esc(s)}</li>`).join('')}</ul>` : ''}${approve ? `<button class="btn btn-sm btn-success" data-ai-arrears-approve="${Number(d.id)}">Approve guidance</button>` : ''}${d.status === 'approved' ? '<div class="alert alert-info small mb-0">Approved for staff guidance; collection actions stay in the normal finance workflow.</div>' : ''}</div></article></div>`;
        }).join('') : '<div class="col-12 small text-muted">No fee arrears review has been requested.</div>';
        el.querySelectorAll('[data-ai-arrears-approve]').forEach((btn) => btn.addEventListener('click', async () => {
            try {
                await window.API.finance.approveAiArrearsDraft(btn.dataset.aiArrearsApprove);
                notify('Fee arrears review approved', 'success');
                await load();
            } catch (e) { notify(e.message || 'Unable to approve fee arrears review', 'danger'); }
        }));
    }

    function poll(runId) {
        pollTimer = window.setTimeout(async () => {
            if (runId !== pollRun) return;
            await load();
            poll(runId);
        }, 5000);
    }

    async function init() {
        const btn = document.getElementById('queueAiArrearsReview');
        if (!btn || btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            try {
                await window.API.finance.queueAiArrearsReview();
                notify('Fee arrears review queued', 'success');
                const runId = ++pollRun;
                poll(runId);
                await load();
            } catch (e) {
                notify(e.message || 'Unable to queue fee arrears review', 'danger');
            } finally {
                btn.disabled = false;
            }
        });
        await load();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
