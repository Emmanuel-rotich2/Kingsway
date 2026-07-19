/**
 * Generate Class Report Controller - Class Teacher Class Report Generation
 * Role: Class Teacher (7)
 * Shows only classes the teacher is assigned to
 * Integrates with AcademicContext for academic year awareness
 */

const generateClassReportCtrl = (() => {
    const state = {
        reports: [],
        classes: [],
        years: [],
        terms: [],
        currentAcademicYear: null,
        currentTerm: null,
        generatedCount: 0
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('reportToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'reportToast';
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

    async function generateReport() {
        try {
            const reportType = document.getElementById('reportType')?.value;
            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';
            const classId = document.getElementById('classFilter')?.value || '';

            if (!reportType) {
                toast('Please select a report type', 'warning');
                return;
            }

            if (!classId) {
                toast('Please select a class', 'warning');
                return;
            }

            const generateBtn = document.getElementById('generateBtn');
            generateBtn.disabled = true;
            generateBtn.textContent = 'Generating...';

            // Get report data from API
            const response = await apiCall('academic/reports', 'GET', {
                report_type: reportType,
                year_id: yearId,
                term_id: termId,
                class_id: classId,
                class_teacher_only: true
            });

            const reportData = response.data || {};

            // Use PrintManager to generate report
            if (window.PrintManager) {
                const reportTypeText = document.getElementById('reportType')?.options[document.getElementById('reportType').selectedIndex]?.text || 'Report';
                const yearText = document.getElementById('yearFilter')?.options[document.getElementById('yearFilter').selectedIndex]?.text || 'All Years';
                const termText = document.getElementById('termFilter')?.options[document.getElementById('termFilter').selectedIndex]?.text || 'All Terms';
                const classText = document.getElementById('classFilter')?.options[document.getElementById('classFilter').selectedIndex]?.text || 'All Classes';

                const columns = reportData.columns || [
                    { key: 'student_name', label: 'Student Name' },
                    { key: 'admission_no', label: 'Adm No' },
                    { key: 'metric1', label: 'Metric 1' },
                    { key: 'metric2', label: 'Metric 2' },
                    { key: 'status', label: 'Status' }
                ];

                const rows = reportData.rows || [];

                window.PrintManager.printTable({
                    title: `${reportTypeText} Report`,
                    subtitle: `${classText} - ${yearText} - ${termText}`,
                    columns: columns,
                    rows: rows,
                    summary: reportData.summary || {
                        'Total Students': rows.length,
                        'Generated Date': new Date().toLocaleDateString()
                    },
                    filters: {
                        'Report Type': reportTypeText,
                        'Academic Year': yearText,
                        'Term': termText,
                        'Class': classText
                    },
                    orientation: 'landscape',
                    paperSize: 'A4',
                    reportCode: `CR-${reportType}-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}`,
                    signatureSection: [
                        { label: 'Class Teacher' },
                        { label: 'Principal' }
                    ]
                });

                state.generatedCount++;
                updateStats();
                toast('Report generated successfully', 'success');
            } else {
                toast('PrintManager not available', 'error');
            }
        } catch (error) {
            console.error('Failed to generate report:', error);
            toast('Failed to generate report', 'error');
        } finally {
            const generateBtn = document.getElementById('generateBtn');
            generateBtn.disabled = false;
            generateBtn.textContent = 'Generate Report';
        }
    }

    function updateStats() {
        document.getElementById('generatedCount').textContent = state.generatedCount;
    }

    function bindEvents() {
        document.getElementById('generateBtn')?.addEventListener('click', generateReport);
        document.getElementById('refreshBtn')?.addEventListener('click', () => {
            // Reload data if needed
            toast('Data refreshed', 'info');
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
                console.log('AcademicContext changed in generate_class_report:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }

        document.getElementById('reportLoading').style.display = 'none';
        document.getElementById('reportContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadClasses()]);
        bindEvents();
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', generateClassReportCtrl.init);
