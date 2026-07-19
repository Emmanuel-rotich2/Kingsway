/**
 * Subject Exam Schedule Controller - Subject Teacher Exam Schedule Viewing
 * Role: Subject Teacher (8)
 * Shows only exams for the teacher's assigned subjects
 * Integrates with AcademicContext for academic year awareness
 */

const subjectExamScheduleCtrl = (() => {
    const state = {
        exams: [],
        subjects: [],
        years: [],
        terms: [],
        currentAcademicYear: null,
        currentTerm: null
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('scheduleToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'scheduleToast';
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
                select.innerHTML = '<option value="">All Years</option>';
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
                select.innerHTML = '<option value="">All Terms</option>';
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

    async function loadSubjects() {
        try {
            // Load only subjects assigned to this teacher
            const response = await apiCall('academic/subjects-list', 'GET', {
                subject_teacher_only: true
            });
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

    async function loadExamSchedule() {
        try {
            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';
            const subjectId = document.getElementById('subjectFilter')?.value || '';

            const response = await apiCall('academic/exam-schedule', 'GET', {
                year_id: yearId,
                term_id: termId,
                subject_id: subjectId,
                subject_teacher_only: true // Only show exams for this teacher's subjects
            });

            state.exams = response.data || [];
            renderExamSchedule();
            updateStats();
        } catch (error) {
            console.error('Failed to load exam schedule:', error);
            toast('Failed to load exam schedule', 'error');
        }
    }

    function renderExamSchedule() {
        const tbody = document.getElementById('scheduleTableBody');
        if (!tbody) return;

        if (!state.exams.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No exam schedules found for your subjects
                    </td>
                </tr>
            `;
            return;
        }

        const today = new Date();

        tbody.innerHTML = state.exams.map(exam => {
            const examDate = new Date(exam.exam_date);
            const isUpcoming = examDate > today;
            const statusClass = isUpcoming ? 'success' : 'warning';
            const statusText = isUpcoming ? 'Upcoming' : 'Completed';

            return `
                <tr>
                    <td><strong>${exam.name || 'Unnamed Exam'}</strong></td>
                    <td>${exam.subject_name || '—'}</td>
                    <td>${exam.class_name || '—'}</td>
                    <td><span class="badge bg-secondary">${exam.type || '—'}</span></td>
                    <td>${exam.exam_date || '—'}</td>
                    <td>${exam.exam_time || '—'}</td>
                    <td>${exam.duration || '—'}</td>
                    <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                </tr>
            `;
        }).join('');
    }

    function updateStats() {
        const total = state.exams.length;
        const today = new Date();
        const upcoming = state.exams.filter(e => new Date(e.exam_date) > today).length;
        const completed = total - upcoming;

        document.getElementById('totalExams').textContent = total;
        document.getElementById('upcomingExams').textContent = upcoming;
        document.getElementById('completedExams').textContent = completed;
    }

    async function exportSchedule() {
        if (!state.exams.length) {
            toast('No exam schedules to export', 'warning');
            return;
        }

        // Use PrintManager for CSV export if available
        if (window.PrintManager) {
            const columns = [
                { key: 'exam_name', label: 'Exam Name' },
                { key: 'subject_name', label: 'Subject' },
                { key: 'class_name', label: 'Class' },
                { key: 'type', label: 'Type' },
                { key: 'exam_date', label: 'Date' },
                { key: 'exam_time', label: 'Time' },
                { key: 'duration', label: 'Duration' },
                { key: 'status', label: 'Status' }
            ];

            const today = new Date();
            const rows = state.exams.map(exam => {
                const examDate = new Date(exam.exam_date);
                const isUpcoming = examDate > today;
                const statusText = isUpcoming ? 'Upcoming' : 'Completed';

                return {
                    exam_name: exam.name || 'Unnamed Exam',
                    subject_name: exam.subject_name || '—',
                    class_name: exam.class_name || '—',
                    type: exam.type || '—',
                    exam_date: exam.exam_date || '—',
                    exam_time: exam.exam_time || '—',
                    duration: exam.duration || '—',
                    status: statusText
                };
            });

            window.PrintManager.exportToCSV({
                filename: `subject_exam_schedule_${new Date().toISOString().slice(0,10)}.csv`,
                columns: columns,
                rows: rows
            });
        } else {
            toast('PrintManager not available', 'error');
        }
    }

    function bindEvents() {
        document.getElementById('refreshBtn')?.addEventListener('click', loadExamSchedule);
        document.getElementById('exportBtn')?.addEventListener('click', exportSchedule);
        
        document.getElementById('yearFilter')?.addEventListener('change', loadExamSchedule);
        document.getElementById('termFilter')?.addEventListener('change', loadExamSchedule);
        document.getElementById('subjectFilter')?.addEventListener('change', loadExamSchedule);
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                console.log('AcademicContext changed in subject_exam_schedule:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                    loadExamSchedule();
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }

        document.getElementById('scheduleLoading').style.display = 'none';
        document.getElementById('scheduleContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadSubjects()]);
        await loadExamSchedule();
        bindEvents();
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', subjectExamScheduleCtrl.init);
