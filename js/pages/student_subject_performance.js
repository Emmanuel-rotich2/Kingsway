/**
 * Student Subject Performance Controller - Subject Teacher Student Performance Viewing
 * Role: Subject Teacher (8)
 * Shows only students in classes where the teacher teaches the subject
 * Integrates with AcademicContext for academic year awareness
 */

const studentSubjectPerformanceCtrl = (() => {
    const state = {
        performance: [],
        subjects: [],
        years: [],
        terms: [],
        currentAcademicYear: null,
        currentTerm: null
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('performanceToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'performanceToast';
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

    async function loadPerformance() {
        try {
            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';
            const subjectId = document.getElementById('subjectFilter')?.value || '';

            // Re-pointed from the non-existent `performance-analysis` slug (which fell
            // through the router into the subjects-list fallback). `performance-overview`
            // returns real per-student average_score rows for the teacher's scope.
            const response = await apiCall('academic/performance-overview', 'GET', {
                year_id: yearId,
                term_id: termId,
                subject_id: subjectId
            });

            state.performance = (response.data?.rows || []).map(r => ({
                student_id: r.student_id,
                student_name: r.full_name || `${r.first_name || ''} ${r.last_name || ''}`.trim(),
                admission_no: r.admission_no,
                class_name: r.class_name,
                subject_average: r.average_score ?? 0,
                best_assessment: r.best_subject || '—',
                needs_improvement: r.needs_improvement || '—',
                trend: r.trend || 'Stable'
            }));
            renderPerformance();
            updateStats();
        } catch (error) {
            console.error('Failed to load performance:', error);
            toast('Failed to load performance', 'error');
        }
    }

    function renderPerformance() {
        const tbody = document.getElementById('performanceTableBody');
        if (!tbody) return;

        if (!state.performance.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No performance data found for your students
                    </td>
                </tr>
            `;
            return;
        }

        const subjectAverage = state.performance.reduce((sum, p) => sum + (p.subject_average || 0), 0) / state.performance.length;

        tbody.innerHTML = state.performance.map(student => {
            const avg = student.subject_average || 0;
            const isAboveAverage = avg >= subjectAverage;
            const trendIcon = student.trend === 'improving' ? 'bi-arrow-up-circle-fill text-success' : 
                           student.trend === 'declining' ? 'bi-arrow-down-circle-fill text-danger' : 
                           'bi-dash-circle-fill text-secondary';
            const trendText = student.trend || 'Stable';

            return `
                <tr>
                    <td>
                        <strong>${student.student_name || 'Unknown'}</strong>
                    </td>
                    <td>${student.admission_no || '—'}</td>
                    <td>${student.class_name || '—'}</td>
                    <td><strong>${avg.toFixed(1)}%</strong></td>
                    <td><span class="badge bg-success">${student.best_assessment || '—'}</span></td>
                    <td><span class="badge bg-warning">${student.needs_improvement || '—'}</span></td>
                    <td>
                        <i class="bi ${trendIcon}"></i> ${trendText}
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="studentSubjectPerformanceCtrl.viewDetails(${student.student_id})">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button class="btn btn-outline-success" onclick="studentSubjectPerformanceCtrl.generateReport(${student.student_id})">
                                <i class="bi bi-file-earmark-text"></i> Report
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function updateStats() {
        const total = state.performance.length;
        const subjectAverage = state.performance.reduce((sum, p) => sum + (p.subject_average || 0), 0) / (state.performance.length || 1);
        const aboveAverage = state.performance.filter(p => (p.subject_average || 0) >= subjectAverage).length;

        document.getElementById('totalStudents').textContent = total;
        document.getElementById('aboveAverage').textContent = aboveAverage;
        document.getElementById('subjectAverage').textContent = subjectAverage.toFixed(1) + '%';
    }

    function viewDetails(studentId) {
        // Navigate to detailed performance view
        window.location.href = `?route=performance_analysis&student_id=${studentId}`;
    }

    function generateReport(studentId) {
        // Navigate to report generation
        window.location.href = `?route=report_cards&student_id=${studentId}`;
    }

    async function exportPerformance() {
        if (!state.performance.length) {
            toast('No performance data to export', 'warning');
            return;
        }

        // Use PrintManager for CSV export if available
        if (window.PrintManager) {
            const columns = [
                { key: 'student_name', label: 'Student Name' },
                { key: 'admission_no', label: 'Adm No' },
                { key: 'class_name', label: 'Class' },
                { key: 'subject_average', label: 'Subject Average' },
                { key: 'best_assessment', label: 'Best Assessment' },
                { key: 'needs_improvement', label: 'Needs Improvement' },
                { key: 'trend', label: 'Trend' }
            ];

            const rows = state.performance.map(student => {
                return {
                    student_name: student.student_name || 'Unknown',
                    admission_no: student.admission_no || '—',
                    class_name: student.class_name || '—',
                    subject_average: (student.subject_average || 0).toFixed(1) + '%',
                    best_assessment: student.best_assessment || '—',
                    needs_improvement: student.needs_improvement || '—',
                    trend: student.trend || 'Stable'
                };
            });

            window.PrintManager.exportToCSV({
                filename: `student_subject_performance_${new Date().toISOString().slice(0,10)}.csv`,
                columns: columns,
                rows: rows
            });
        } else {
            toast('PrintManager not available', 'error');
        }
    }

    function printPerformance() {
        if (!state.performance.length) {
            toast('No performance data to print', 'warning');
            return;
        }

        // Use PrintManager for printing if available
        if (window.PrintManager) {
            const columns = [
                { key: 'student_name', label: 'Student Name' },
                { key: 'admission_no', label: 'Adm No' },
                { key: 'class_name', label: 'Class' },
                { key: 'subject_average', label: 'Subject Average' },
                { key: 'best_assessment', label: 'Best Assessment' },
                { key: 'needs_improvement', label: 'Needs Improvement' },
                { key: 'trend', label: 'Trend' }
            ];

            const rows = state.performance.map(student => {
                return {
                    student_name: student.student_name || 'Unknown',
                    admission_no: student.admission_no || '—',
                    class_name: student.class_name || '—',
                    subject_average: (student.subject_average || 0).toFixed(1) + '%',
                    best_assessment: student.best_assessment || '—',
                    needs_improvement: student.needs_improvement || '—',
                    trend: student.trend || 'Stable'
                };
            });

            const yearText = document.getElementById('yearFilter')?.options[document.getElementById('yearFilter').selectedIndex]?.text || 'All Years';
            const termText = document.getElementById('termFilter')?.options[document.getElementById('termFilter').selectedIndex]?.text || 'All Terms';
            const subjectText = document.getElementById('subjectFilter')?.options[document.getElementById('subjectFilter').selectedIndex]?.text || 'All Subjects';

            window.PrintManager.printTable({
                title: 'Student Subject Performance',
                subtitle: `Subject Performance Analysis for ${subjectText} - ${yearText} - ${termText}`,
                columns: columns,
                rows: rows,
                summary: {
                    'Total Students': state.performance.length,
                    'Above Average': document.getElementById('aboveAverage').textContent,
                    'Subject Average': document.getElementById('subjectAverage').textContent,
                    'Generated Date': new Date().toLocaleDateString()
                },
                filters: {
                    'Academic Year': yearText,
                    'Term': termText,
                    'Subject': subjectText
                },
                orientation: 'landscape',
                paperSize: 'A4',
                reportCode: 'SSP-' + new Date().toISOString().slice(0, 10).replace(/-/g, ''),
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
        document.getElementById('loadPerformanceBtn')?.addEventListener('click', loadPerformance);
        document.getElementById('exportBtn')?.addEventListener('click', exportPerformance);
        document.getElementById('printBtn')?.addEventListener('click', printPerformance);
        
        document.getElementById('yearFilter')?.addEventListener('change', () => {
            if (document.getElementById('subjectFilter').value) {
                loadPerformance();
            }
        });
        document.getElementById('termFilter')?.addEventListener('change', () => {
            if (document.getElementById('subjectFilter').value) {
                loadPerformance();
            }
        });
        document.getElementById('subjectFilter')?.addEventListener('change', loadPerformance);
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                console.log('AcademicContext changed in student_subject_performance:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                    if (document.getElementById('subjectFilter').value) {
                        loadPerformance();
                    }
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }

        document.getElementById('performanceLoading').style.display = 'none';
        document.getElementById('performanceContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadSubjects()]);
        bindEvents();
    }

    return {
        init,
        viewDetails,
        generateReport
    };
})();

document.addEventListener('DOMContentLoaded', studentSubjectPerformanceCtrl.init);
