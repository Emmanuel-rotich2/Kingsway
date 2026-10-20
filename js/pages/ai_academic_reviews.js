/**
 * AI Academic Reviews Controller
 * Migrated to use AgentTaskPanel with Node gateway
 * Three workflows: coverage review, learning gap review, rubric draft
 */

(function () {
    'use strict';

    const esc = (value) => {
        const node = document.createElement('span');
        node.textContent = String(value ?? '');
        return node.innerHTML;
    };
    const notify = (message, type = 'info') => window.showNotification?.(message, type);

    let coveragePanel = null;
    let learningGapPanel = null;
    let rubricPanel = null;

    async function loadReviews() {
        const host = document.getElementById('aiAcademicReviews');
        if (!host || !window.API?.academic) return;
        try {
            const own = await window.API.academic.getAiSchemeDrafts('own');
            let review = { drafts: [] };
            try { review = await window.API.academic.getAiSchemeDrafts('review'); } catch (_) { /* optional review scope */ }
            const seen = new Set();
            const rows = [...(own?.data?.drafts || own?.drafts || []).map((row) => ({ ...row, __review: false })),
                         ...(review?.data?.drafts || review?.drafts || []).map((row) => ({ ...row, __review: true }))]
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
                    await loadReviews();
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

    function createCoveragePanel() {
        const container = document.getElementById('aiCoverageReviewPanel');
        if (!container || coveragePanel) return;
        
        coveragePanel = new window.AgentTaskPanel({
            container: container,
            tool: 'reports.aggregate',
            question: '',
            route: 'academics',
            module: 'coverage_review',
            targetComponent: '#aiCoverageReviewOutput',
            componentId: 'ai-coverage-review',
            onComplete: (result) => {
                notify('Coverage review ready!', 'success');
                loadReviews();
            },
            onError: (error) => {
                notify(error.message || 'Failed to generate coverage review', 'danger');
            },
            onProgress: (progress) => {
                if (progress.type === 'status') {
                    console.log('[Coverage Review]', progress.text);
                }
            },
        });
    }

    function createLearningGapPanel() {
        const container = document.getElementById('aiLearningGapReviewPanel');
        if (!container || learningGapPanel) return;
        
        learningGapPanel = new window.AgentTaskPanel({
            container: container,
            tool: 'reports.aggregate',
            question: '',
            route: 'academics',
            module: 'learning_gap_review',
            targetComponent: '#aiLearningGapReviewOutput',
            componentId: 'ai-learning-gap-review',
            onComplete: (result) => {
                notify('Learning gap review ready!', 'success');
                loadReviews();
            },
            onError: (error) => {
                notify(error.message || 'Failed to generate learning gap review', 'danger');
            },
            onProgress: (progress) => {
                if (progress.type === 'status') {
                    console.log('[Learning Gap Review]', progress.text);
                }
            },
        });
    }

    function createRubricPanel() {
        const container = document.getElementById('aiRubricDraftPanel');
        if (!container || rubricPanel) return;
        
        rubricPanel = new window.AgentTaskPanel({
            container: container,
            tool: 'drafts.create',
            question: '',
            route: 'academics',
            module: 'rubric_draft',
            targetComponent: '#aiRubricDraftOutput',
            componentId: 'ai-rubric-draft',
            onComplete: (result) => {
                notify('Rubric draft ready!', 'success');
                loadReviews();
            },
            onError: (error) => {
                notify(error.message || 'Failed to generate rubric draft', 'danger');
            },
            onProgress: (progress) => {
                if (progress.type === 'status') {
                    console.log('[Rubric Draft]', progress.text);
                }
            },
        });
    }

    async function queueCoverageReview() {
        const button = document.getElementById('queueAiCoverageReview');
        if (!button) return;
        const classId = Number(document.getElementById('aiCoverageClassId')?.value || 0);
        if (classId < 1) return notify('Enter an authorized academic year class ID first.', 'warning');
        
        if (!coveragePanel) createCoveragePanel();
        
        const question = `Generate a curriculum coverage review for class ${classId}. Analyze the scheme of work coverage against CBC learning outcomes, identify gaps, and provide actionable recommendations.`;
        
        button.disabled = true;
        try {
            coveragePanel.question = question;
            await coveragePanel.start();
        } catch (error) {
            notify(error.message || 'Unable to queue coverage review', 'danger');
        } finally {
            button.disabled = false;
        }
    }

    async function queueLearningGapReview() {
        const button = document.getElementById('queueAiLearningGapReview');
        if (!button) return;
        
        if (!learningGapPanel) createLearningGapPanel();
        
        const question = `Generate a learning gap review for the current academic period. Identify students at risk of falling behind, analyze assessment data for gaps in understanding, and recommend targeted interventions.`;
        
        button.disabled = true;
        try {
            learningGapPanel.question = question;
            await learningGapPanel.start();
        } catch (error) {
            notify(error.message || 'Unable to queue learning gap review', 'danger');
        } finally {
            button.disabled = false;
        }
    }

    async function queueRubricDraft() {
        const button = document.getElementById('queueAiRubricDraft');
        if (!button) return;
        const outcomeId = Number(document.getElementById('aiRubricOutcomeId')?.value || 0);
        if (outcomeId < 1) return notify('Enter an authorized learning outcome ID first.', 'warning');
        
        if (!rubricPanel) createRubricPanel();
        
        const question = `Create a CBC rubric draft for learning outcome ${outcomeId}. Include performance levels, criteria, descriptors, and alignment with curriculum standards.`;
        
        button.disabled = true;
        try {
            rubricPanel.question = question;
            await rubricPanel.start();
        } catch (error) {
            notify(error.message || 'Unable to queue rubric draft', 'danger');
        } finally {
            button.disabled = false;
        }
    }

    async function init() {
        document.getElementById('queueAiCoverageReview')?.addEventListener('click', queueCoverageReview);
        document.getElementById('queueAiLearningGapReview')?.addEventListener('click', queueLearningGapReview);
        document.getElementById('queueAiRubricDraft')?.addEventListener('click', queueRubricDraft);
        await loadReviews();
    }

    window.APIRealtime?.register?.('ai_academic_reviews', {
        refresh: async () => { await loadReviews(); },
    }, ['academic']);

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();