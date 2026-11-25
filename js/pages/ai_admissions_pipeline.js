/**
 * AI Admissions Pipeline Review panel (manage_students_admissions).
 * Queues admissions.pipeline_review, polls drafts, allows approval.
 * Aggregate pipeline guidance only — never names applicants; workflow
 * decisions stay in the normal admissions pipeline.
 */
(function () {
    'use strict';

    const esc = (value) => { const d = document.createElement('div'); d.textContent = String(value ?? ''); return d.innerHTML; };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);
    let pollTimer = null;
    let pollRun = 0;

    async function load() {
        const el = document.getElementById('aiPipelineDrafts');
        if (!el) return;
        let own = [];
        try { own = await window.API.admission.getAiPipelineDrafts('own'); } catch (_) { }
        let review = { drafts: [] };
        try { review = await window.API.admission.getAiPipelineDrafts('review'); } catch (_) { }
        const drafts = [
            ...(own?.data?.drafts || own?.drafts || []).map(x => ({ ...x, __review: false })),
            ...(review?.data?.drafts || review?.drafts || []).map(x => ({ ...x, __review: true })),
        ];
        el.innerHTML = drafts.length ? drafts.map(d => {
            const x = d.draft || {};
            const approve = d.__review && d.status === 'pending_approval';
            return `<div class="col-12 col-xl-6"><article class="card h-100 bg-light border-0"><div class="card-body"><div class="d-flex justify-content-between"><strong>${esc(x.title || 'Pipeline review')}</strong><span class="badge text-bg-secondary">${esc(d.status || 'queued')}</span></div><p class="small mt-2">${esc(x.body || 'Review is still being generated.')}</p>${Array.isArray(x.next_steps) && x.next_steps.length ? `<ul class="small">${x.next_steps.map(s => `<li>${esc(s)}</li>`).join('')}</ul>` : ''}${approve ? `<button class="btn btn-sm btn-success" data-ai-pipeline-approve="${Number(d.id)}">Approve guidance</button>` : ''}${d.status === 'approved' ? '<div class="alert alert-info small mb-0">Approved for staff guidance; admissions decisions stay in the normal workflow.</div>' : ''}</div></article></div>`;
        }).join('') : '<div class="col-12 small text-muted">No pipeline review has been requested.</div>';
        el.querySelectorAll('[data-ai-pipeline-approve]').forEach((btn) => btn.addEventListener('click', async () => {
            try {
                await window.API.admission.approveAiPipelineDraft(btn.dataset.aiPipelineApprove);
                notify('Pipeline review approved', 'success');
                await load();
            } catch (e) { notify(e.message || 'Unable to approve pipeline review', 'danger'); }
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
        const btn = document.getElementById('queueAiPipelineReview');
        if (!btn || btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            try {
                await window.API.admission.queueAiPipelineReview();
                notify('Pipeline review queued', 'success');
                const runId = ++pollRun;
                poll(runId);
                await load();
            } catch (e) {
                notify(e.message || 'Unable to queue pipeline review', 'danger');
            } finally {
                btn.disabled = false;
            }
        });
        await load();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
