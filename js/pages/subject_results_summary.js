/**
 * Subject Results Summary Controller - Subject Teacher Specific Results Viewing
 * Role: Subject Teacher (8)
 * Shows only results for the teacher's assigned subjects
 * Integrates with AcademicContext for academic year awareness
 */

const subjectResultsController = (() => {
    const state = {
        results: [],
        classes: [],
        subjects: [],
        years: [],
        terms: [],
        currentAcademicYear: null,
        currentTerm: null
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('resultsToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'resultsToast';
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

    function cbcGrade(score) {
        const n = Number(score);
        if (!Number.isFinite(n)) return null;
        if (n >= 80) return 'EE';
        if (n >= 50) return 'ME';
        if (n >= 25) return 'AE';
        return 'BE';
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

    async function loadClasses() {
        try {
            const response = await apiCall('academic/classes-list');
            state.classes = response.data || [];
            const select = document.getElementById('classFilter');
            if (select) {
                select.innerHTML = '<option value="">All Classes</option>';
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

    async function loadResults() {
        try {
            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';
            const subjectId = document.getElementById('subjectFilter')?.value || '';
            const classId = document.getElementById('classFilter')?.value || '';

            const response = await apiCall('academic/results', 'GET', {
                year_id: yearId,
                term_id: termId,
                subject_id: subjectId,
                class_id: classId,
                subject_teacher_only: true // Only show results for this teacher's subjects
            });

            state.results = response.data || [];
            renderResults();
            updateStats();
        } catch (error) {
            console.error('Failed to load results:', error);
            toast('Failed to load results', 'error');
        }
    }

    function renderResults() {
        const tbody = document.getElementById('resultsTableBody');
        if (!tbody) return;

        if (!state.results.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No results found for your subjects
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = state.results.map((result, index) => {
            const grade = cbcGrade(result.marks);
            const gradeColor = grade === 'EE' ? 'success' : grade === 'ME' ? 'primary' : grade === 'AE' ? 'warning' : 'danger';
            const remarks = grade === 'EE' ? 'Excellent' : grade === 'ME' ? 'Meeting Expectations' : grade === 'AE' ? 'Approaching Expectations' : 'Below Expectations';

            return `
                <tr>
                    <td>
                        <strong>${result.student_name || 'Unknown'}</strong>
                    </td>
                    <td>${result.admission_no || '—'}</td>
                    <td>${result.class_name || '—'}</td>
                    <td>${result.subject_name || '—'}</td>
                    <td><strong>${result.marks || 0}%</strong></td>
                    <td><span class="badge bg-${gradeColor}">${grade || '—'}</span></td>
                    <td><span class="text-muted small">${remarks || '—'}</span></td>
                </tr>
            `;
        }).join('');
    }

    function updateStats() {
        const total = state.results.length;
        const marks = state.results.map(r => parseFloat(r.marks) || 0);
        const average = marks.length > 0 ? (marks.reduce((a, b) => a + b, 0) / marks.length).toFixed(1) : '0.0';
        const aboveAverage = marks.filter(m => m >= parseFloat(average)).length;

        document.getElementById('totalStudents').textContent = total;
        document.getElementById('averageScore').textContent = `${average}%`;
        document.getElementById('aboveAverage').textContent = aboveAverage;
    }

    async function exportResults() {
        if (!state.results.length) {
            toast('No results to export', 'warning');
            return;
        }

        // Use PrintManager for CSV export if available
        if (window.PrintManager) {
            const columns = [
                { key: 'student_name', label: 'Student Name' },
                { key: 'admission_no', label: 'Adm No' },
                { key: 'class_name', label: 'Class' },
                { key: 'subject_name', label: 'Subject' },
                { key: 'marks', label: 'Marks' },
                { key: 'grade', label: 'Grade' },
                { key: 'remarks', label: 'Remarks' }
            ];

            const rows = state.results.map(result => {
                const grade = cbcGrade(result.marks);
                const remarks = grade === 'EE' ? 'Excellent' : grade === 'ME' ? 'Meeting Expectations' : grade === 'AE' ? 'Approaching Expectations' : 'Below Expectations';

                return {
                    student_name: result.student_name || 'Unknown',
                    admission_no: result.admission_no || '—',
                    class_name: result.class_name || '—',
                    subject_name: result.subject_name || '—',
                    marks: result.marks || 0,
                    grade: grade || '—',
                    remarks: remarks || '—'
                };
            });

            window.PrintManager.exportToCSV({
                filename: `subject_results_${new Date().toISOString().slice(0,10)}.csv`,
                columns: columns,
                rows: rows
            });
        } else {
            toast('PrintManager not available', 'error');
        }
    }

    function printResults() {
        if (!state.results.length) {
            toast('No results to print', 'warning');
            return;
        }

        // Use PrintManager for printing if available
        if (window.PrintManager) {
            const columns = [
                { key: 'student_name', label: 'Student Name' },
                { key: 'admission_no', label: 'Adm No' },
                { key: 'class_name', label: 'Class' },
                { key: 'subject_name', label: 'Subject' },
                { key: 'marks', label: 'Marks' },
                { key: 'grade', label: 'Grade' },
                { key: 'remarks', label: 'Remarks' }
            ];

            const rows = state.results.map(result => {
                const grade = cbcGrade(result.marks);
                const remarks = grade === 'EE' ? 'Excellent' : grade === 'ME' ? 'Meeting Expectations' : grade === 'AE' ? 'Approaching Expectations' : 'Below Expectations';

                return {
                    student_name: result.student_name || 'Unknown',
                    admission_no: result.admission_no || '—',
                    class_name: result.class_name || '—',
                    subject_name: result.subject_name || '—',
                    marks: result.marks || 0,
                    grade: grade || '—',
                    remarks: remarks || '—'
                };
            });

            const yearText = document.getElementById('yearFilter')?.options[document.getElementById('yearFilter').selectedIndex]?.text || 'All Years';
            const termText = document.getElementById('termFilter')?.options[document.getElementById('termFilter').selectedIndex]?.text || 'All Terms';
            const subjectText = document.getElementById('subjectFilter')?.options[document.getElementById('subjectFilter').selectedIndex]?.text || 'All Subjects';

            window.PrintManager.printTable({
                title: 'Subject Results Summary',
                subtitle: `Results for ${subjectText} - ${yearText} - ${termText}`,
                columns: columns,
                rows: rows,
                summary: {
                    'Total Students': state.results.length,
                    'Average Score': document.getElementById('averageScore').textContent,
                    'Above Average': document.getElementById('aboveAverage').textContent,
                    'Generated Date': new Date().toLocaleDateString()
                },
                filters: {
                    'Academic Year': yearText,
                    'Term': termText,
                    'Subject': subjectText
                },
                orientation: 'landscape',
                paperSize: 'A4',
                reportCode: 'SR-' + new Date().toISOString().slice(0, 10).replace(/-/g, ''),
                signatureSection: [
                    { label: 'Subject Teacher' },
                    { label: 'Principal' }
                ]
            });
        } else {
            toast('PrintManager not available', 'error');
        }
    }

    function bindEvents() {
        document.getElementById('loadResultsBtn')?.addEventListener('click', loadResults);
        document.getElementById('exportBtn')?.addEventListener('click', exportResults);
        document.getElementById('printBtn')?.addEventListener('click', printResults);
        
        document.getElementById('yearFilter')?.addEventListener('change', () => {
            // Auto-load results when filters change
            if (document.getElementById('subjectFilter').value && document.getElementById('classFilter').value) {
                loadResults();
            }
        });
        document.getElementById('termFilter')?.addEventListener('change', () => {
            if (document.getElementById('subjectFilter').value && document.getElementById('classFilter').value) {
                loadResults();
            }
        });
        document.getElementById('subjectFilter')?.addEventListener('change', () => {
            if (document.getElementById('classFilter').value) {
                loadResults();
            }
        });
        document.getElementById('classFilter')?.addEventListener('change', () => {
            if (document.getElementById('subjectFilter').value) {
                loadResults();
            }
        });
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                console.log('AcademicContext changed in subject_results_summary:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                    if (document.getElementById('subjectFilter').value && document.getElementById('classFilter').value) {
                        loadResults();
                    }
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }

        document.getElementById('resultsLoading').style.display = 'none';
        document.getElementById('resultsContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadSubjects(), loadClasses()]);
        bindEvents();
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', subjectResultsController.init);
