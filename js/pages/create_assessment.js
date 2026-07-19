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

    async function loadYears() {
        try {
            const response = await apiCall('academic/years-list');
            state.years = response.data || [];
            const select = document.getElementById('yearFilter');
            if (select) {
                select.innerHTML = '<option value="">Select Year</option>';
                state.years.forEach(year => {
                    const option = document.createElement('option');
                    option.value = year.id;
                    option.textContent = year.year_name;
                    if (year.is_current) option.selected = true;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load years:', error);
        }
    }

    async function loadTerms() {
        try {
            const response = await apiCall('academic/terms-list');
            state.terms = response.data || [];
            const select = document.getElementById('termFilter');
            if (select) {
                select.innerHTML = '<option value="">Select Term</option>';
                state.terms.forEach(term => {
                    const option = document.createElement('option');
                    option.value = term.id;
                    option.textContent = `${term.name} (${term.year_code || ''})`;
                    if (term.status === 'current') option.selected = true;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load terms:', error);
        }
    }

    async function loadClasses() {
        try {
            const response = await apiCall('academic/classes-list');
            state.classes = response.data || [];
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
            state.subjects = response.data || [];
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
            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';

            const response = await apiCall('academic/formative-assessments', 'GET', {
                year_id: yearId,
                term_id: termId,
                limit: 10
            });

            state.assessments = response.data || [];
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
                            <button class="btn btn-outline-primary" onclick="createAssessmentCtrl.viewAssessment(${assessment.id})">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button class="btn btn-outline-success" onclick="createAssessmentCtrl.enterMarks(${assessment.id})">
                                <i class="bi bi-pencil"></i> Marks
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function updateStats() {
        const yearId = document.getElementById('yearFilter')?.value || '';
        const termId = document.getElementById('termFilter')?.value || '';

        const yearAssessments = state.assessments.filter(a => a.year_id == yearId).length;
        const termAssessments = state.assessments.filter(a => a.term_id == termId).length;

        document.getElementById('yearAssessments').textContent = yearAssessments;
        document.getElementById('termAssessments').textContent = termAssessments;
    }

    async function createAssessment() {
        try {
            const assessmentName = document.getElementById('assessmentName')?.value;
            const assessmentType = document.getElementById('assessmentType')?.value;
            const yearId = document.getElementById('yearFilter')?.value;
            const termId = document.getElementById('termFilter')?.value;
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
                year_id: yearId,
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
            toast('Failed to create assessment', 'error');
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
        
        document.getElementById('yearFilter')?.addEventListener('change', loadRecentAssessments);
        document.getElementById('termFilter')?.addEventListener('change', loadRecentAssessments);
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                console.log('AcademicContext changed in create_assessment:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                    loadRecentAssessments();
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }

        document.getElementById('assessmentLoading').style.display = 'none';
        document.getElementById('assessmentContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadClasses(), loadSubjects()]);
        await loadRecentAssessments();
        bindEvents();
    }

    return {
        init,
        viewAssessment,
        enterMarks
    };
})();

document.addEventListener('DOMContentLoaded', createAssessmentCtrl.init);
