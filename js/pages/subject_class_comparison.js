/**
 * Subject Class Comparison Controller - Subject Teacher Class Comparison
 * Role: Subject Teacher (8)
 * Shows only classes where the teacher teaches the subject
 * Integrates with AcademicContext for academic year awareness
 */

const subjectClassComparisonCtrl = (() => {
    const state = {
        comparisons: [],
        subjects: [],
        years: [],
        terms: [],
        currentAcademicYear: null,
        currentTerm: null
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('comparisonToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'comparisonToast';
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

    async function loadComparison() {
        try {
            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';
            const subjectId = document.getElementById('subjectFilter')?.value || '';

            // Re-pointed from the non-existent `comparative-reports` slug (which fell
            // through the router into the subjects-list fallback). `results-analysis`
            // returns per-class rollups already aggregated by the backend. We flatten
            // `class_metrics` into the page's comparison shape; grade counts and
            // pass_rate are native, while highest/lowest are not computed server-side
            // so we leave them marked unavailable rather than fabricate numbers.
            const response = await apiCall('academic/results-analysis', 'GET', {
                year_id: yearId,
                term_id: termId,
                subject_id: subjectId
            });

            const data = response.data || {};
            const subjectName = (data.subject_metrics && data.subject_metrics[0] && data.subject_metrics[0].subject_name) || '—';
            const gradeDist = (m) => `EE ${m.ee_count || 0} · ME ${m.me_count || 0} · AE ${m.ae_count || 0} · BE ${m.be_count || 0}`;

            state.comparisons = (data.class_metrics || []).map(m => ({
                class_name: m.class_name || '—',
                subject_name: subjectName,
                student_count: m.students_assessed || 0,
                average_score: m.average_overall != null ? Number(m.average_overall) : 0,
                highest_score: null, // not aggregated server-side
                lowest_score: null,  // not aggregated server-side
                pass_rate: m.pass_rate != null ? Number(m.pass_rate) : 0,
                grade_distribution: gradeDist(m)
            }));
            renderComparison();
            updateStats();
        } catch (error) {
            console.error('Failed to load comparison:', error);
            toast('Failed to load comparison', 'error');
        }
    }

    function renderComparison() {
        const tbody = document.getElementById('comparisonTableBody');
        if (!tbody) return;

        if (!state.comparisons.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No comparison data found for your subjects
                    </td>
                </tr>
            `;
            return;
        }

        const overallAverage = state.comparisons.reduce((sum, c) => sum + (c.average_score || 0), 0) / state.comparisons.length;
        const bestClass = state.comparisons.reduce((best, c) => (c.average_score || 0) > (best.average_score || 0) ? c : best, state.comparisons[0]);

        tbody.innerHTML = state.comparisons.map(comparison => {
            const passRate = comparison.pass_rate || 0;
            const passRateClass = passRate >= 80 ? 'success' : passRate >= 50 ? 'warning' : 'danger';
            const gradeDistribution = comparison.grade_distribution || 'N/A';

            return `
                <tr>
                    <td><strong>${comparison.class_name || '—'}</strong></td>
                    <td>${comparison.subject_name || '—'}</td>
                    <td><strong>${comparison.student_count || 0}</strong></td>
                    <td><strong>${(comparison.average_score || 0).toFixed(1)}%</strong></td>
                    <td><span class="text-success">${comparison.highest_score != null ? comparison.highest_score.toFixed(1) + '%' : '—'}</span></td>
                    <td><span class="text-danger">${comparison.lowest_score != null ? comparison.lowest_score.toFixed(1) + '%' : '—'}</span></td>
                    <td><span class="badge bg-${passRateClass}">${passRate.toFixed(1)}%</span></td>
                    <td><span class="text-muted small">${gradeDistribution}</span></td>
                </tr>
            `;
        }).join('');
    }

    function updateStats() {
        const total = state.comparisons.length;
        const overallAverage = state.comparisons.reduce((sum, c) => sum + (c.average_score || 0), 0) / (state.comparisons.length || 1);
        const bestClass = state.comparisons.reduce((best, c) => (c.average_score || 0) > (best.average_score || 0) ? c : best, state.comparisons[0]);

        document.getElementById('totalClasses').textContent = total;
        document.getElementById('overallAverage').textContent = overallAverage.toFixed(1) + '%';
        document.getElementById('bestClass').textContent = bestClass.class_name || '—';
    }

    async function exportComparison() {
        if (!state.comparisons.length) {
            toast('No comparison data to export', 'warning');
            return;
        }

        // Use PrintManager for CSV export if available
        if (window.PrintManager) {
            const columns = [
                { key: 'class_name', label: 'Class' },
                { key: 'subject_name', label: 'Subject' },
                { key: 'student_count', label: 'Students' },
                { key: 'average_score', label: 'Average Score' },
                { key: 'highest_score', label: 'Highest Score' },
                { key: 'lowest_score', label: 'Lowest Score' },
                { key: 'pass_rate', label: 'Pass Rate' },
                { key: 'grade_distribution', label: 'Grade Distribution' }
            ];

            const rows = state.comparisons.map(comparison => {
                return {
                    class_name: comparison.class_name || '—',
                    subject_name: comparison.subject_name || '—',
                    student_count: comparison.student_count || 0,
                    average_score: (comparison.average_score || 0).toFixed(1) + '%',
                    highest_score: comparison.highest_score != null ? comparison.highest_score.toFixed(1) + '%' : '—',
                    lowest_score: comparison.lowest_score != null ? comparison.lowest_score.toFixed(1) + '%' : '—',
                    pass_rate: (comparison.pass_rate || 0).toFixed(1) + '%',
                    grade_distribution: comparison.grade_distribution || 'N/A'
                };
            });

            window.PrintManager.exportToCSV({
                filename: `subject_class_comparison_${new Date().toISOString().slice(0,10)}.csv`,
                columns: columns,
                rows: rows
            });
        } else {
            toast('PrintManager not available', 'error');
        }
    }

    function printComparison() {
        if (!state.comparisons.length) {
            toast('No comparison data to print', 'warning');
            return;
        }

        // Use PrintManager for printing if available
        if (window.PrintManager) {
            const columns = [
                { key: 'class_name', label: 'Class' },
                { key: 'subject_name', label: 'Subject' },
                { key: 'student_count', label: 'Students' },
                { key: 'average_score', label: 'Average Score' },
                { key: 'highest_score', label: 'Highest Score' },
                { key: 'lowest_score', label: 'Lowest Score' },
                { key: 'pass_rate', label: 'Pass Rate' },
                { key: 'grade_distribution', label: 'Grade Distribution' }
            ];

            const rows = state.comparisons.map(comparison => {
                return {
                    class_name: comparison.class_name || '—',
                    subject_name: comparison.subject_name || '—',
                    student_count: comparison.student_count || 0,
                    average_score: (comparison.average_score || 0).toFixed(1) + '%',
                    highest_score: comparison.highest_score != null ? comparison.highest_score.toFixed(1) + '%' : '—',
                    lowest_score: comparison.lowest_score != null ? comparison.lowest_score.toFixed(1) + '%' : '—',
                    pass_rate: (comparison.pass_rate || 0).toFixed(1) + '%',
                    grade_distribution: comparison.grade_distribution || 'N/A'
                };
            });

            const yearText = document.getElementById('yearFilter')?.options[document.getElementById('yearFilter').selectedIndex]?.text || 'All Years';
            const termText = document.getElementById('termFilter')?.options[document.getElementById('termFilter').selectedIndex]?.text || 'All Terms';
            const subjectText = document.getElementById('subjectFilter')?.options[document.getElementById('subjectFilter').selectedIndex]?.text || 'All Subjects';

            window.PrintManager.printTable({
                title: 'Subject Class Comparison',
                subtitle: `Comparison for ${subjectText} - ${yearText} - ${termText}`,
                columns: columns,
                rows: rows,
                summary: {
                    'Total Classes': state.comparisons.length,
                    'Best Class': document.getElementById('bestClass').textContent,
                    'Overall Average': document.getElementById('overallAverage').textContent,
                    'Generated Date': new Date().toLocaleDateString()
                },
                filters: {
                    'Academic Year': yearText,
                    'Term': termText,
                    'Subject': subjectText
                },
                orientation: 'landscape',
                paperSize: 'A4',
                reportCode: 'SC-' + new Date().toISOString().slice(0, 10).replace(/-/g, ''),
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
        document.getElementById('loadComparisonBtn')?.addEventListener('click', loadComparison);
        document.getElementById('exportBtn')?.addEventListener('click', exportComparison);
        document.getElementById('printBtn')?.addEventListener('click', printComparison);
        
        document.getElementById('yearFilter')?.addEventListener('change', () => {
            if (document.getElementById('subjectFilter').value) {
                loadComparison();
            }
        });
        document.getElementById('termFilter')?.addEventListener('change', () => {
            if (document.getElementById('subjectFilter').value) {
                loadComparison();
            }
        });
        document.getElementById('subjectFilter')?.addEventListener('change', loadComparison);
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                console.log('AcademicContext changed in subject_class_comparison:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                    if (document.getElementById('subjectFilter').value) {
                        loadComparison();
                    }
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }

        document.getElementById('comparisonLoading').style.display = 'none';
        document.getElementById('comparisonContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadSubjects()]);
        bindEvents();
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', subjectClassComparisonCtrl.init);
