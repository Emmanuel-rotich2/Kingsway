/**
 * AI Timetable Review panel (manage_timetable).
 * Queues academics.timetable_review for the year/term/class-band selected on
 * the page, polls drafts, allows coordinator approval.
 * Aggregate guidance only — never names staff and never places lessons.
 */
(function () {
    'use strict';

    const esc = (value) => { const d = document.createElement('div'); d.textContent = String(value ?? ''); return d.innerHTML; };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);
    let pollTimer = null;
    let pollRun = 0;

    function selectedContext() {
        const yearId = Number(document.getElementById('academicYearFilter')?.value || 0);
        const termId = Number(document.getElementById('academicTermFilter')?.value || 0);
        const bandSelect = document.getElementById('classBandFilter') || document.getElementById('timetableLevelFilter');
        const band = bandSelect?.value || 'whole_school';
        return {
            academic_year_id: yearId || undefined,
            academic_year_term_id: termId || undefined,
            class_band: ['whole_school', 'lower_primary', 'upper_primary'].includes(band) ? band : 'whole_school',
        };
    }

    async function load() {
        const el = document.getElementById('aiTimetableReviewDrafts');
        if (!el) return;
        let own = [];
        try { own = await window.API.schedules.getAiTimetableReviewDrafts('own'); } catch (_) { }
        let review = { drafts: [] };
        try { review = await window.API.schedules.getAiTimetableReviewDrafts('review'); } catch (_) { }
        const drafts = [
            ...(own?.data?.drafts || own?.drafts || []).map(x => ({ ...x, __review: false })),
            ...(review?.data?.drafts || review?.drafts || []).map(x => ({ ...x, __review: true })),
        ];
        el.innerHTML = drafts.length ? drafts.map(d => {
            const x = d.draft || {};
            const approve = d.__review && d.status === 'pending_approval';
            return `<div class="col-12 col-xl-6"><article class="card h-100 bg-light border-0"><div class="card-body"><div class="d-flex justify-content-between"><strong>${esc(x.title || 'Timetable review')}</strong><span class="badge text-bg-secondary">${esc(d.status || 'queued')}</span></div><p class="small mt-2">${esc(x.body || 'Review is still being generated.')}</p>${Array.isArray(x.next_steps) && x.next_steps.length ? `<ul class="small">${x.next_steps.map(s => `<li>${esc(s)}</li>`).join('')}</ul>` : ''}${approve ? `<button class="btn btn-sm btn-success" data-ai-tt-approve="${Number(d.id)}">Approve guidance</button>` : ''}${d.status === 'approved' ? '<div class="alert alert-info small mb-0">Approved for staff guidance; lesson placement stays in the normal timetable workflow.</div>' : ''}</div></article></div>`;
        }).join('') : '<div class="col-12 small text-muted">No timetable review has been requested.</div>';
        el.querySelectorAll('[data-ai-tt-approve]').forEach((btn) => btn.addEventListener('click', async () => {
            try {
                await window.API.schedules.approveAiTimetableReviewDraft(btn.dataset.aiTtApprove);
                notify('Timetable review approved', 'success');
                await load();
            } catch (e) { notify(e.message || 'Unable to approve timetable review', 'danger'); }
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
        const btn = document.getElementById('queueAiTimetableReview');
        if (!btn || btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            try {
                await window.API.schedules.queueAiTimetableReview(selectedContext());
                notify('Timetable review queued', 'success');
                const runId = ++pollRun;
                poll(runId);
                await load();
            } catch (e) {
                notify(e.message || 'Unable to queue timetable review', 'danger');
            } finally {
                btn.disabled = false;
            }
        });
        await load();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
