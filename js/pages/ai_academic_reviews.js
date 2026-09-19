(function () {
    'use strict';

    const esc = (value) => {
        const node = document.createElement('span');
        node.textContent = String(value ?? '');
        return node.innerHTML;
    };
    const drafts = (response) => response?.data?.drafts || response?.drafts || [];
    const notify = (message, type = 'info') => window.showNotification?.(message, type);

    async function load() {
        const host = document.getElementById('aiAcademicReviews');
        if (!host || !window.API?.academic) return;
        try {
            const own = await window.API.academic.getAiSchemeDrafts('own');
            let review = { drafts: [] };
            try { review = await window.API.academic.getAiSchemeDrafts('review'); } catch (_) { /* optional review scope */ }
            const seen = new Set();
            const rows = [...drafts(own).map((row) => ({ ...row, __review: false })), ...drafts(review).map((row) => ({ ...row, __review: true }))]
                .filter((row) => {
                    if (seen.has(row.id)) return false;
                    seen.add(row.id);
                    return ['academic_coverage_review', 'academic_rubric_draft', 'academic_learning_gap_review'].includes(row.metadata?.subject_type);
                });
            host.innerHTML = rows.length ? rows.map(render).join('') : '<div class="col-12 small text-muted">No academic assistant reviews have been requested.</div>';
            host.querySelectorAll('[data-ai-academic-approve]').forEach((button) => button.addEventListener('click', async () => {
                button.disabled = true;
                try {
                    const type = button.dataset.aiAcademicType;
                    const id = button.dataset.aiAcademicApprove;
                    const approve = type === 'academic_rubric_draft'
                        ? window.API.academic.approveAiRubricDraft(id)
                        : type === 'academic_learning_gap_review'
                            ? window.API.academic.approveAiLearningGapReview(id)
                            : window.API.academic.approveAiCoverageReview(id);
                    await approve;
                    notify('Academic review approved for staff editing/guidance.', 'success');
                    await load();
                } catch (error) {
                    notify(error.message || 'Unable to approve academic review.', 'danger');
                    button.disabled = false;
                }
            }));
        } catch (_) {
            host.innerHTML = '<div class="col-12 small text-warning">Academic assistant reviews are temporarily unavailable.</div>';
        }
    }

    function render(row) {
        const body = row.draft || {};
        const type = row.metadata?.subject_type || '';
        const label = type === 'academic_rubric_draft' ? 'CBC rubric draft' : type === 'academic_learning_gap_review' ? 'Learning-gap review' : 'Curriculum coverage review';
        const approve = row.__review && row.status === 'pending_approval'
            ? `<button type="button" class="btn btn-sm btn-success" data-ai-academic-approve="${Number(row.id)}" data-ai-academic-type="${esc(type)}">Approve guidance</button>` : '';
        const nextSteps = Array.isArray(body.next_steps) ? `<ul class="small mb-2">${body.next_steps.map((step) => `<li>${esc(step)}</li>`).join('')}</ul>` : '';
        return `<div class="col-12 col-xl-6"><article class="border rounded p-2 h-100"><div class="d-flex justify-content-between gap-2"><strong>${esc(body.title || label)}</strong><span class="badge text-bg-secondary">${esc(row.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(body.body || 'Review is still being generated.')}</p>${nextSteps}${approve}${row.status === 'approved' ? '<div class="small text-info mt-2">Approved advisory output only; normal academic editing and approval remain required.</div>' : ''}</article></div>`;
    }

    async function queue(button, operation, payload, success) {
        if (!button) return;
        button.disabled = true;
        try {
            await operation(payload);
            notify(success, 'info');
            await load();
        } catch (error) {
            notify(error.message || 'Unable to queue academic review.', 'danger');
        } finally { button.disabled = false; }
    }

    function init() {
        document.getElementById('queueAiCoverageReview')?.addEventListener('click', function () {
            const id = Number(document.getElementById('aiCoverageClassId')?.value || 0);
            if (id < 1) return notify('Enter an authorized academic year class ID first.', 'warning');
            queue(this, window.API.academic.queueAiCoverageReview, { academic_year_class_id: id }, 'Coverage review queued.');
        });
        document.getElementById('queueAiLearningGapReview')?.addEventListener('click', function () {
            queue(this, window.API.academic.queueAiLearningGapReview, {}, 'Learning-gap review queued.');
        });
        document.getElementById('queueAiRubricDraft')?.addEventListener('click', function () {
            const id = Number(document.getElementById('aiRubricOutcomeId')?.value || 0);
            if (id < 1) return notify('Enter an authorized learning outcome ID first.', 'warning');
            queue(this, window.API.academic.queueAiRubricDraft, { learning_outcome_id: id }, 'Rubric draft queued.');
        });
        load();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
}());
