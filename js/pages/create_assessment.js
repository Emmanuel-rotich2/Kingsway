/**
 * Create Assessment Controller - School Admin Assessment Creation
 * Role: School Administrator (4)
 * Full access to all classes and subjects for assessment creation
 * Integrates with AcademicContext for academic year awareness
 */

const createAssessmentCtrl = (() => {
    const state = {
        assessments: [],
        classes: [],
        subjects: [],
        years: [],
        terms: [],
        currentAcademicYear: null,
        currentTerm: null
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('assessmentToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'assessmentToast';
            toast.className = `toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3`;
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${msg}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            new bootstrap.Toast(toast, { delay: 3000 }).show();
        } else {
            el.className = `toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3`;
            el.querySelector('.toast-body').textContent = msg;
            new bootstrap.Toast(el, { delay: 3000 }).show();
        }
    }

    async function apiCall(endpoint, method = 'GET', data = null) {
        try {
            return await window.API.apiCall(endpoint, method, data, null, { checkPermission: false });
        } catch (error) {
            console.error('API call failed:', error);
            throw error;
        }
    }

    /** Rows from an unwrapped API payload (list or {data|items|...}). */
    function rowsOf(payload, key = '') {
        if (Array.isArray(payload)) return payload;
        if (key && Array.isArray(payload?.[key])) return payload[key];
        return payload?.data || payload?.items || payload?.assessments || payload?.students || payload?.subjects || payload?.exams || [];
    }

    /** The shared year/term selector; the current year (and running term) by default. */
    async function mountYearFilter() {
        await window.AcademicYearFilter?.mount(document.getElementById('yearFilter'), {
            termSelect: document.getElementById('termFilter'),
            allTermsLabel: 'All terms',
            allTermsDefault: false,
            onChange: (yearId, year, termId) => {
                state.currentAcademicYear = yearId;
                state.currentTerm = termId;
                loadRecentAssessments();
            },
        });
    }

    async function loadClasses() {
        try {
            const response = await apiCall('academic/classes-list');
            state.classes = rowsOf(response);
            const select = document.getElementById('classFilter');
            if (select) {
                select.innerHTML = '<option value="">Select Class</option>';
                state.classes.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.class_name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load classes:', error);
        }
    }

    async function loadSubjects() {
        try {
            const response = await apiCall('academic/subjects-list');
            state.subjects = rowsOf(response);
            const select = document.getElementById('subjectFilter');
            if (select) {
                select.innerHTML = '<option value="">All Subjects</option>';
                state.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = subject.subject_name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load subjects:', error);
        }
    }

    async function loadRecentAssessments() {
        try {
            const yearId = state.currentAcademicYear;
            if (!yearId) return;
            const termId = state.currentTerm || '';

            const response = await window.API.apiCall('academic/formative-assessments', 'GET', null, {
                academic_year_id: yearId,
                year_id: yearId,
                academic_year_term_id: termId,
                term_id: termId,
                limit: 10
            }, { checkPermission: false });

            state.assessments = rowsOf(response);
            renderRecentAssessments();
            updateStats();
        } catch (error) {
            console.error('Failed to load recent assessments:', error);
        }
    }

    function renderRecentAssessments() {
        const tbody = document.getElementById('recentAssessmentsBody');
        if (!tbody) return;

        if (!state.assessments.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No recent assessments
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = state.assessments.map(assessment => {
            const statusClass = assessment.status === 'active' ? 'success' : assessment.status === 'completed' ? 'primary' : 'secondary';
            const statusText = assessment.status ? assessment.status.charAt(0).toUpperCase() + assessment.status.slice(1) : '—';

            return `
                <tr>
                    <td><strong>${assessment.name || 'Unnamed'}</strong></td>
                    <td><span class="badge bg-secondary">${assessment.type || '—'}</span></td>
                    <td>${assessment.class_name || '—'}</td>
                    <td>${assessment.subject_name || '—'}</td>
                    <td>${assessment.assessment_date || '—'}</td>
                    <td><strong>${assessment.max_marks || 0}</strong></td>
                    <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" data-kw-onclick="createAssessmentCtrl.viewAssessment(${assessment.id})" data-year-write>
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button class="btn btn-outline-success" data-kw-onclick="createAssessmentCtrl.enterMarks(${assessment.id})" data-year-write>
                                <i class="bi bi-pencil"></i> Marks
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
        // A completed year stays read-only for users who may not edit it.
        window.YearMode?.refresh(tbody);
    }

    function updateStats() {
        const yearId = state.currentAcademicYear;
        const termId = state.currentTerm || '';

        const yearAssessments = state.assessments.filter(a => a.year_id == yearId).length;
        const termAssessments = state.assessments.filter(a => a.term_id == termId).length;

        document.getElementById('yearAssessments').textContent = yearAssessments;
        document.getElementById('termAssessments').textContent = termAssessments;
    }

    async function createAssessment() {
        try {
            const assessmentName = document.getElementById('assessmentName')?.value;
            const assessmentType = document.getElementById('assessmentType')?.value;
            const yearId = state.currentAcademicYear;
            const termId = state.currentTerm || '';
            const classId = document.getElementById('classFilter')?.value;
            const subjectId = document.getElementById('subjectFilter')?.value;
            const maxMarks = document.getElementById('maxMarks')?.value;
            const assessmentDate = document.getElementById('assessmentDate')?.value;
            const description = document.getElementById('assessmentDescription')?.value;
            const instructions = document.getElementById('assessmentInstructions')?.value;
            const duration = document.getElementById('assessmentDuration')?.value;
            const venue = document.getElementById('assessmentVenue')?.value;
            const status = document.getElementById('assessmentStatus')?.value;

            if (!assessmentName || !assessmentType || !yearId || !termId || !classId || !maxMarks || !assessmentDate) {
                toast('Please fill in all required fields', 'warning');
                return;
            }

            const createBtn = document.getElementById('createAssessmentBtn');
            createBtn.disabled = true;
            createBtn.textContent = 'Creating...';

            const assessmentData = {
                name: assessmentName,
                type: assessmentType,
                academic_year_id: yearId,
                year_id: yearId,
                academic_year_term_id: termId,
                term_id: termId,
                class_id: classId,
                subject_id: subjectId || null,
                max_marks: parseInt(maxMarks),
                assessment_date: assessmentDate,
                description: description || null,
                instructions: instructions || null,
                duration: duration ? parseInt(duration) : null,
                venue: venue || null,
                status: status
            };

            await apiCall('academic/formative-assessments', 'POST', assessmentData);

            toast('Assessment created successfully', 'success');
            resetForm();
            await loadRecentAssessments();
        } catch (error) {
            console.error('Failed to create assessment:', error);
            toast(error?.message || 'Failed to create assessment', 'error');
        } finally {
            const createBtn = document.getElementById('createAssessmentBtn');
            createBtn.disabled = false;
            createBtn.textContent = 'Create Assessment';
        }
    }

    function resetForm() {
        document.getElementById('assessmentName').value = '';
        document.getElementById('assessmentType').value = '';
        document.getElementById('maxMarks').value = '';
        document.getElementById('assessmentDate').value = '';
        document.getElementById('assessmentDescription').value = '';
        document.getElementById('assessmentInstructions').value = '';
        document.getElementById('assessmentDuration').value = '';
        document.getElementById('assessmentVenue').value = '';
        document.getElementById('assessmentStatus').value = 'draft';
    }

    function viewAssessment(assessmentId) {
        window.location.href = `?route=formative_assessments&action=view&id=${assessmentId}`;
    }

    function enterMarks(assessmentId) {
        window.location.href = `?route=enter_marks&assessment_id=${assessmentId}`;
    }

    function bindEvents() {
        document.getElementById('createAssessmentBtn')?.addEventListener('click', createAssessment);
        document.getElementById('resetBtn')?.addEventListener('click', resetForm);
    }

    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                if (event === 'refreshed') loadRecentAssessments();
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }
        }

        document.getElementById('assessmentLoading').style.display = 'none';
        document.getElementById('assessmentContent').style.display = 'block';

        await Promise.all([loadClasses(), loadSubjects()]);

        bindEvents();

        await mountYearFilter(); // loads data for the default year/term

    }

    return {
        init,
        viewAssessment,
        enterMarks
    };
})();

document.addEventListener('DOMContentLoaded', createAssessmentCtrl.init);

window.createAssessmentCtrl = createAssessmentCtrl;
window.APIRealtime?.register?.(createAssessmentCtrl, createAssessmentCtrl);
