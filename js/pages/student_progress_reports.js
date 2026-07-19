/**
 * Student Progress Reports Controller - Class Teacher Progress Reports Viewing
 * Role: Class Teacher (7)
 * Shows only progress reports for the teacher's assigned classes
 * Integrates with AcademicContext for academic year awareness
 */

const studentProgressReportsCtrl = (() => {
    const state = {
        progress: [],
        classes: [],
        years: [],
        terms: [],
        currentAcademicYear: null,
        currentTerm: null
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('progressToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'progressToast';
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

    async function loadClasses() {
        try {
            // Load only classes assigned to this teacher
            const response = await apiCall('academic/classes-list', 'GET', {
                class_teacher_only: true
            });
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

    async function loadProgressReports() {
        try {
            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';
            const classId = document.getElementById('classFilter')?.value || '';

            const response = await apiCall('academic/performance-overview', 'GET', {
                year_id: yearId,
                term_id: termId,
                class_id: classId,
                class_teacher_only: true // Only show progress for this teacher's classes
            });

            const summary = response.data?.summary || {};
            const prevAvg = summary.year_average ?? 0;
            // Map performance-overview rows to the per-student progress shape this page renders.
            state.progress = (response.data?.rows || []).map(r => ({
                student_id: r.student_id,
                student_name: r.full_name || `${r.first_name || ''} ${r.last_name || ''}`.trim(),
                admission_no: r.admission_no,
                class_name: r.class_name,
                previous_average: prevAvg,
                current_average: r.average_score ?? 0
            }));
            renderProgressReports();
            updateStats();
        } catch (error) {
            console.error('Failed to load progress reports:', error);
            toast('Failed to load progress reports', 'error');
        }
    }

    function renderProgressReports() {
        const tbody = document.getElementById('progressTableBody');
        if (!tbody) return;

        if (!state.progress.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No progress reports found for your classes
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = state.progress.map(student => {
            const previousAvg = student.previous_average || 0;
            const currentAvg = student.current_average || 0;
            const change = currentAvg - previousAvg;
            const isImproved = change > 0;
            const isDeclined = change < 0;
            const trendIcon = isImproved ? 'bi-arrow-up-circle-fill text-success' : isDeclined ? 'bi-arrow-down-circle-fill text-danger' : 'bi-dash-circle-fill text-secondary';
            const trendText = isImproved ? 'Improving' : isDeclined ? 'Declining' : 'Stable';

            return `
                <tr>
                    <td>
                        <strong>${student.student_name || 'Unknown'}</strong>
                    </td>
                    <td>${student.admission_no || '—'}</td>
                    <td>${student.class_name || '—'}</td>
                    <td><strong>${previousAvg.toFixed(1)}%</strong></td>
                    <td><strong>${currentAvg.toFixed(1)}%</strong></td>
                    <td>
                        <span class="${isImproved ? 'text-success' : isDeclined ? 'text-danger' : 'text-muted'}">
                            ${change > 0 ? '+' : ''}${change.toFixed(1)}%
                        </span>
                    </td>
                    <td>
                        <i class="bi ${trendIcon}"></i> ${trendText}
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="studentProgressReportsCtrl.viewDetails(${student.student_id})">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button class="btn btn-outline-success" onclick="studentProgressReportsCtrl.generateReport(${student.student_id})">
                                <i class="bi bi-file-earmark-text"></i> Report
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function updateStats() {
        const total = state.progress.length;
        const improved = state.progress.filter(s => (s.current_average || 0) > (s.previous_average || 0)).length;
        const declined = state.progress.filter(s => (s.current_average || 0) < (s.previous_average || 0)).length;

        document.getElementById('totalStudents').textContent = total;
        document.getElementById('improvedStudents').textContent = improved;
        document.getElementById('declinedStudents').textContent = declined;
    }

    function viewDetails(studentId) {
        // Navigate to detailed progress view
        window.location.href = `?route=performance_analysis&student_id=${studentId}`;
    }

    function generateReport(studentId) {
        // Navigate to report generation
        window.location.href = `?route=report_cards&student_id=${studentId}`;
    }

    async function exportProgress() {
        if (!state.progress.length) {
            toast('No progress reports to export', 'warning');
            return;
        }

        // Use PrintManager for CSV export if available
        if (window.PrintManager) {
            const columns = [
                { key: 'student_name', label: 'Student Name' },
                { key: 'admission_no', label: 'Adm No' },
                { key: 'class_name', label: 'Class' },
                { key: 'previous_average', label: 'Previous Average' },
                { key: 'current_average', label: 'Current Average' },
                { key: 'change', label: 'Change' },
                { key: 'trend', label: 'Trend' }
            ];

            const rows = state.progress.map(student => {
                const previousAvg = student.previous_average || 0;
                const currentAvg = student.current_average || 0;
                const change = currentAvg - previousAvg;
                const isImproved = change > 0;
                const isDeclined = change < 0;
                const trendText = isImproved ? 'Improving' : isDeclined ? 'Declining' : 'Stable';

                return {
                    student_name: student.student_name || 'Unknown',
                    admission_no: student.admission_no || '—',
                    class_name: student.class_name || '—',
                    previous_average: previousAvg.toFixed(1) + '%',
                    current_average: currentAvg.toFixed(1) + '%',
                    change: (change > 0 ? '+' : '') + change.toFixed(1) + '%',
                    trend: trendText
                };
            });

            window.PrintManager.exportToCSV({
                filename: `student_progress_${new Date().toISOString().slice(0,10)}.csv`,
                columns: columns,
                rows: rows
            });
        } else {
            toast('PrintManager not available', 'error');
        }
    }

    function printProgress() {
        if (!state.progress.length) {
            toast('No progress reports to print', 'warning');
            return;
        }

        // Use PrintManager for printing if available
        if (window.PrintManager) {
            const columns = [
                { key: 'student_name', label: 'Student Name' },
                { key: 'admission_no', label: 'Adm No' },
                { key: 'class_name', label: 'Class' },
                { key: 'previous_average', label: 'Previous Average' },
                { key: 'current_average', label: 'Current Average' },
                { key: 'change', label: 'Change' },
                { key: 'trend', label: 'Trend' }
            ];

            const rows = state.progress.map(student => {
                const previousAvg = student.previous_average || 0;
                const currentAvg = student.current_average || 0;
                const change = currentAvg - previousAvg;
                const isImproved = change > 0;
                const isDeclined = change < 0;
                const trendText = isImproved ? 'Improving' : isDeclined ? 'Declining' : 'Stable';

                return {
                    student_name: student.student_name || 'Unknown',
                    admission_no: student.admission_no || '—',
                    class_name: student.class_name || '—',
                    previous_average: previousAvg.toFixed(1) + '%',
                    current_average: currentAvg.toFixed(1) + '%',
                    change: (change > 0 ? '+' : '') + change.toFixed(1) + '%',
                    trend: trendText
                };
            });

            const yearText = document.getElementById('yearFilter')?.options[document.getElementById('yearFilter').selectedIndex]?.text || 'All Years';
            const termText = document.getElementById('termFilter')?.options[document.getElementById('termFilter').selectedIndex]?.text || 'All Terms';
            const classText = document.getElementById('classFilter')?.options[document.getElementById('classFilter').selectedIndex]?.text || 'All Classes';

            window.PrintManager.printTable({
                title: 'Student Progress Reports',
                subtitle: `Progress Report for ${classText} - ${yearText} - ${termText}`,
                columns: columns,
                rows: rows,
                summary: {
                    'Total Students': state.progress.length,
                    'Improved': document.getElementById('improvedStudents').textContent,
                    'Declined': document.getElementById('declinedStudents').textContent,
                    'Generated Date': new Date().toLocaleDateString()
                },
                filters: {
                    'Academic Year': yearText,
                    'Term': termText,
                    'Class': classText
                },
                orientation: 'landscape',
                paperSize: 'A4',
                reportCode: 'PR-' + new Date().toISOString().slice(0, 10).replace(/-/g, ''),
                signatureSection: [
                    { label: 'Class Teacher' },
                    { label: 'Principal' }
                ]
            });
        } else {
            toast('PrintManager not available', 'error');
        }
    }

    function bindEvents() {
        document.getElementById('loadReportsBtn')?.addEventListener('click', loadProgressReports);
        document.getElementById('exportBtn')?.addEventListener('click', exportProgress);
        document.getElementById('printBtn')?.addEventListener('click', printProgress);
        
        document.getElementById('yearFilter')?.addEventListener('change', () => {
            if (document.getElementById('classFilter').value) {
                loadProgressReports();
            }
        });
        document.getElementById('termFilter')?.addEventListener('change', () => {
            if (document.getElementById('classFilter').value) {
                loadProgressReports();
            }
        });
        document.getElementById('classFilter')?.addEventListener('change', () => {
            loadProgressReports();
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
                console.log('AcademicContext changed in student_progress_reports:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                    if (document.getElementById('classFilter').value) {
                        loadProgressReports();
                    }
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }

        document.getElementById('progressLoading').style.display = 'none';
        document.getElementById('progressContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadClasses()]);
        bindEvents();
    }

    return {
        init,
        viewDetails,
        generateReport
    };
})();

document.addEventListener('DOMContentLoaded', studentProgressReportsCtrl.init);
