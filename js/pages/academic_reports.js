/**
 * academic_reports.js — Academic Analytics Controller
 * Uses live classes/students data and avoids synthetic placeholder scores.
 * Integrates with AcademicContext for academic year awareness
 */
const academicReportsCtrl = (() => {
    let perfChart = null;
    let trendsChart = null;
    let gradeChart = null;
    let contextReady = false;

    const state = {
        years: [],
        terms: [],
        classes: [],
        learningAreas: [],
        classMetrics: [],
        currentAcademicYear: null,
        currentTerm: null,
        referenceCache: null
    };

    // Aggregate the context-free reference rows through one authorized Node
    // view; the year-dependent term list stays direct.
    async function loadReferencesComposed() {
        try {
            const response = await window.API.nodeViews.resolve("academic", "academic-reports-references");
            state.referenceCache = response?.data?.sources || response?.sources || {};
        } catch (error) {
            console.warn("Academic reports reference Node view failed; using direct reads:", error);
            state.referenceCache = null;
        }
    }

    function toast(msg, type = 'info') {
        const el = document.getElementById('arToast');
        if (!el) return;
        el.className = `toast align-items-center text-bg-${type} border-0`;
        const body = document.getElementById('arToastBody');
        if (body) body.textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, { delay: 3200 }).show();
    }

    function toNumber(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n : 0;
    }

    function cbcBand(score) {
        const n = Number(score);
        if (!Number.isFinite(n)) return null;
        return GradingScale.grade(n) || null;
    }

    async function apiGet(route, params = {}) {
        return window.API.apiCall(`/${route}`, 'GET', null, params, { checkPermission: false });
    }

    function renderNoData(canvasId, message) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const parent = canvas.parentElement;
        if (!parent) return;

        const existing = parent.querySelector('.ar-chart-empty');
        if (existing) existing.remove();

        const msg = document.createElement('div');
        msg.className = 'ar-chart-empty text-muted text-center py-4';
        msg.textContent = message;
        parent.appendChild(msg);
    }

    function clearNoData(canvasId) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !canvas.parentElement) return;
        const existing = canvas.parentElement.querySelector('.ar-chart-empty');
        if (existing) existing.remove();
    }

    async function loadYears() {
        const years = state.referenceCache?.years ?? (await apiGet('academic/years-list'));
        state.years = Array.isArray(years) ? years : [];
        const sel = document.getElementById('selectYear');
        if (!sel) return;
        sel.innerHTML = '<option value="">All Years</option>';
        state.years.forEach((y) => {
            const opt = document.createElement('option');
            opt.value = y.id;
            opt.textContent = y.year_name;
            if (y.is_current) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    async function loadTerms() {
        const yearId = document.getElementById('selectYear')?.value || '';
        const terms = await apiGet('academic/terms-list', yearId ? { academic_year_id: yearId, year_id: yearId } : {});
        state.terms = Array.isArray(terms) ? terms : [];
        const sel = document.getElementById('selectTerm');
        if (!sel) return;
        sel.innerHTML = '<option value="">All Terms</option>';
        state.terms.forEach((t) => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = `${t.name} (${t.year_code || ''})`;
            if (t.status === 'current') opt.selected = true;
            sel.appendChild(opt);
        });
    }

    async function loadClasses() {
        const classes = state.referenceCache?.classes ?? (await apiGet('academic/classes-list'));
        state.classes = Array.isArray(classes) ? classes : [];
        const sel = document.getElementById('selectClass');
        if (!sel) return;
        sel.innerHTML = '<option value="">All Classes</option>';
        state.classes.forEach((c) => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = `${c.name} (${c.level_code || ''})`;
            sel.appendChild(opt);
        });
    }

    async function loadLearningAreas() {
        try {
            let res;
            if (state.referenceCache?.learningAreas !== undefined) {
                res = state.referenceCache.learningAreas;
            } else {
                res = await window.API.academic.listLearningAreas().catch(() => null)
                    || await apiGet('academic/learning-areas/list').catch(() => null);
            }
            const areas = res?.data ?? res ?? [];
            state.learningAreas = Array.isArray(areas) ? areas : [];
        } catch {
            state.learningAreas = [];
        }
    }

    async function fetchStudentsByClass(classId) {
        try {
            const rows = await apiGet(`students/by-class-get/${classId}`);
            return Array.isArray(rows) ? rows : [];
        } catch {
            return [];
        }
    }

    function responseRows(response, key = 'rows') {
        const payload = response?.data ?? response ?? {};
        if (Array.isArray(payload)) return payload;
        return Array.isArray(payload[key]) ? payload[key] : [];
    }

    function computeClassMetric(classInfo, students) {
        const scores = students
            .map((s) => (s.year_average != null ? Number(s.year_average) : null))
            .filter((v) => v !== null && Number.isFinite(v));

        const term1 = students
            .map((s) => (s.term1_average != null ? Number(s.term1_average) : null))
            .filter((v) => v !== null && Number.isFinite(v));
        const term2 = students
            .map((s) => (s.term2_average != null ? Number(s.term2_average) : null))
            .filter((v) => v !== null && Number.isFinite(v));
        const term3 = students
            .map((s) => (s.term3_average != null ? Number(s.term3_average) : null))
            .filter((v) => v !== null && Number.isFinite(v));

        const avg = scores.length ? scores.reduce((a, b) => a + b, 0) / scores.length : null;
        const passRate = scores.length ? (scores.filter((v) => v >= 50).length / scores.length) * 100 : null;
        const top = scores.length ? Math.max(...scores) : null;

        return {
            class_id: classInfo.id,
            class_name: classInfo.name,
            level_name: classInfo.level_name || classInfo.level_code || '—',
            student_count: students.length,
            stream_count: toNumber(classInfo.stream_count),
            scored_count: scores.length,
            average_score: avg,
            pass_rate: passRate,
            top_score: top,
            term1_avg: term1.length ? term1.reduce((a, b) => a + b, 0) / term1.length : null,
            term2_avg: term2.length ? term2.reduce((a, b) => a + b, 0) / term2.length : null,
            term3_avg: term3.length ? term3.reduce((a, b) => a + b, 0) / term3.length : null,
            grade_counts: {
                EE: scores.filter((v) => cbcBand(v) === 'EE').length,
                ME: scores.filter((v) => cbcBand(v) === 'ME').length,
                AE: scores.filter((v) => cbcBand(v) === 'AE').length,
                BE: scores.filter((v) => cbcBand(v) === 'BE').length,
            }
        };
    }

    async function buildClassMetrics(selectedClassId = '') {
        const reportType = document.getElementById('reportType')?.value || 'class';
        const classes = selectedClassId
            ? state.classes.filter((c) => Number(c.id) === Number(selectedClassId))
            : state.classes;

        const yearId = document.getElementById('selectYear')?.value || state.currentAcademicYear || '';
        const termId = document.getElementById('selectTerm')?.value || state.currentTerm || '';
        const filters = { view_mode: reportType === 'student' ? 'students' : 'class', limit: 500 };
        if (yearId) filters.year_id = yearId;
        if (termId) filters.term_id = termId;
        if (selectedClassId) filters.class_id = selectedClassId;

        const [overviewResponse, analysisResponse] = await Promise.all([
            apiGet('academic/performance-overview', filters),
            apiGet('academic/results-analysis', filters)
        ]);
        const overviewRows = responseRows(overviewResponse);
        const analysisPayload = analysisResponse?.data ?? analysisResponse ?? {};
        const scoreRows = Array.isArray(analysisPayload.class_metrics) ? analysisPayload.class_metrics : [];
        const subjectRows = Array.isArray(analysisPayload.subject_metrics) ? analysisPayload.subject_metrics : [];
        if (reportType === 'subject') {
            return subjectRows.map(row => ({
                class_id: row.subject_id, class_name: row.subject_name, level_name: row.level_name || 'All levels',
                student_count: Number(row.students_assessed || 0), stream_count: '—', scored_count: Number(row.students_assessed || 0),
                average_score: row.avg_overall_pct == null ? null : Number(row.avg_overall_pct), pass_rate: row.pass_rate == null ? null : Number(row.pass_rate), top_score: null,
                term1_avg: null, term2_avg: null, term3_avg: null,
                grade_counts: { EE: Number(row.ee_count || 0), ME: Number(row.me_count || 0), AE: Number(row.ae_count || 0), BE: Number(row.be_count || 0) }
            }));
        }
        if (reportType === 'student') {
            return overviewRows.map(row => ({
                class_id: row.student_id, class_name: row.admission_no || '—', student_name: row.full_name || row.student_name || 'Unnamed learner', admission_no: row.admission_no || '—',
                level_name: row.class_name || '—', stream_name: row.stream_name || '—', student_count: 1, stream_count: row.stream_name || '—', scored_count: row.average_score != null ? 1 : 0,
                average_score: row.average_score == null || Number(row.average_score) === 0 ? null : Number(row.average_score),
                pass_rate: row.average_score == null ? null : (Number(row.average_score) >= 50 ? 100 : 0), top_score: null,
                term1_avg: null, term2_avg: null, term3_avg: null,
                grade_counts: { EE: row.grade === 'EE' ? 1 : 0, ME: row.grade === 'ME' ? 1 : 0, AE: row.grade === 'AE' ? 1 : 0, BE: row.grade === 'BE' ? 1 : 0 }
            }));
        }
        const byClass = new Map(scoreRows.map(row => [String(row.class_id), row]));
        const overviewByClass = new Map(overviewRows.map(row => [String(row.class_id), row]));

        const metrics = classes.map((cls) => {
            const overview = overviewByClass.get(String(cls.id)) || {};
            const score = byClass.get(String(cls.id)) || {};
            const average = score.average_overall != null ? Number(score.average_overall) : (overview.average_score ? Number(overview.average_score) : null);
            const studentCount = Number(overview.total_students ?? overview.student_count ?? score.students_assessed ?? 0);
            const scoredCount = Number(score.students_assessed ?? 0);
            return {
                class_id: cls.id,
                class_name: cls.name,
                level_name: cls.level_name || cls.level_code || '—',
                student_count: studentCount,
                stream_count: toNumber(cls.stream_count),
                scored_count: scoredCount,
                average_score: score.average_overall != null
                    ? (Number.isFinite(average) ? average : null)
                    : (overview.average_score != null && Number(overview.average_score) !== 0 ? Number(overview.average_score) : null),
                pass_rate: score.pass_rate != null ? Number(score.pass_rate) : null,
                top_score: null,
                term1_avg: null, term2_avg: null, term3_avg: null,
                grade_counts: {
                    EE: Number(score.ee_count || 0), ME: Number(score.me_count || 0),
                    AE: Number(score.ae_count || 0), BE: Number(score.be_count || 0)
                }
            };
        });

        state.classMetrics = metrics;
        return metrics;
    }

    function updateKpis(metrics) {
        const totalClasses = metrics.length;
        const totalStudents = metrics.reduce((sum, m) => sum + m.student_count, 0);
        const allScored = metrics.flatMap((m) => {
            const count = m.scored_count;
            if (!count || m.average_score == null) return [];
            return Array(count).fill(m.average_score);
        });

        const avg = allScored.length ? allScored.reduce((a, b) => a + b, 0) / allScored.length : null;
        const pass = metrics
            .filter((m) => m.pass_rate != null)
            .map((m) => m.pass_rate);
        const passRate = pass.length ? pass.reduce((a, b) => a + b, 0) / pass.length : null;

        document.getElementById('arKpiClasses').textContent = totalClasses;
        document.getElementById('arKpiStudents').textContent = totalStudents;
        document.getElementById('arKpiAvg').textContent = avg != null ? `${avg.toFixed(1)}%` : 'No scores';
        document.getElementById('arKpiPassRate').textContent = passRate != null ? `${passRate.toFixed(1)}%` : 'No scores';

        document.getElementById('qs_classes').textContent = totalClasses;
        document.getElementById('qs_students').textContent = totalStudents;
        document.getElementById('qs_subjects').textContent = state.learningAreas.length || 0;
        document.getElementById('avgScore').textContent = avg != null ? `${avg.toFixed(1)}%` : 'No scores';
        document.getElementById('passRate').textContent = passRate != null ? `${passRate.toFixed(1)}%` : 'No scores';

        const topClass = metrics
            .filter((m) => m.average_score != null)
            .sort((a, b) => b.average_score - a.average_score)[0];
        document.getElementById('topPerformers').textContent = topClass ? topClass.class_name : 'No scores';
    }

    function renderDetailedTable(metrics) {
        const tbody = document.getElementById('detailedTbody');
        if (!tbody) return;
        const reportType = document.getElementById('reportType')?.value || 'class';
        const thead = document.querySelector('#detailedTable thead');
        if (thead) {
            thead.innerHTML = reportType === 'student'
                ? '<tr><th>Student Name</th><th>Adm No.</th><th>Class</th><th>Stream</th><th>Avg %</th><th>EE</th><th>ME</th><th>AE</th><th>BE</th><th>Pass Rate</th></tr>'
                : reportType === 'subject'
                    ? '<tr><th>Learning Area</th><th>Level</th><th>Students Assessed</th><th>Avg %</th><th>EE</th><th>ME</th><th>AE</th><th>BE</th><th>Pass Rate</th></tr>'
                    : '<tr><th>Class</th><th>Level</th><th>Students</th><th>Streams</th><th>Avg %</th><th>EE</th><th>ME</th><th>AE</th><th>BE</th><th>Pass Rate</th></tr>';
        }
        if (!metrics.length) {
            tbody.innerHTML = `<tr><td colspan="${reportType === 'subject' ? 9 : 10}" class="ar-empty"><i class="bi bi-table"></i>No report data available</td></tr>`;
            return;
        }
        tbody.innerHTML = metrics.map((m) => {
            const avg = m.average_score != null ? `${m.average_score.toFixed(1)}%` : '—';
            const pass = m.pass_rate != null ? `${m.pass_rate.toFixed(1)}%` : '—';
            const top = m.top_score != null ? `${m.top_score.toFixed(1)}%` : '—';
            const scoreHealth = m.scored_count > 0 ? `${m.scored_count}/${m.student_count}` : '0';
            if (reportType === 'student') return `
                <tr><td><strong>${m.student_name || '—'}</strong></td><td>${m.admission_no || '—'}</td><td>${m.level_name || '—'}</td><td>${m.stream_name || m.stream_count || '—'}</td><td>${avg}</td><td>${m.grade_counts.EE || '—'}</td><td>${m.grade_counts.ME || '—'}</td><td>${m.grade_counts.AE || '—'}</td><td>${m.grade_counts.BE || '—'}</td><td>${pass}</td></tr>`;
            if (reportType === 'subject') return `
                <tr><td><strong>${m.class_name}</strong></td><td>${m.level_name}</td><td>${m.student_count}</td><td>${avg}</td><td>${m.grade_counts.EE || '—'}</td><td>${m.grade_counts.ME || '—'}</td><td>${m.grade_counts.AE || '—'}</td><td>${m.grade_counts.BE || '—'}</td><td>${pass}</td></tr>`;
            return `
                <tr>
                    <td><strong>${m.class_name}</strong></td>
                    <td>${m.level_name}</td>
                    <td>${m.student_count}</td>
                    <td>${m.stream_count}</td>
                    <td>${avg}</td>
                    <td>${m.grade_counts.EE || '—'}</td>
                    <td>${m.grade_counts.ME || '—'}</td>
                    <td>${m.grade_counts.AE || '—'}</td>
                    <td>${m.grade_counts.BE || '—'}</td>
                    <td>${pass}</td>
                </tr>
            `;
        }).join('');
    }

    function drawPerformanceChart(metrics) {
        const canvas = document.getElementById('performanceChart');
        if (!canvas || typeof Chart === 'undefined') return;
        if (perfChart) perfChart.destroy();
        clearNoData('performanceChart');

        const withScores = metrics.filter((m) => m.average_score != null);
        if (!withScores.length) {
            renderNoData('performanceChart', 'No class score records available yet.');
            return;
        }

        perfChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: withScores.map((m) => m.class_name),
                datasets: [
                    {
                        label: 'Average Score (%)',
                        data: withScores.map((m) => Number(m.average_score.toFixed(2))),
                        backgroundColor: '#1b5e20',
                        borderRadius: 6
                    },
                    {
                        label: 'Pass Rate (%)',
                        data: withScores.map((m) => Number((m.pass_rate || 0).toFixed(2))),
                        backgroundColor: '#66bb6a',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
    }

    function drawTrendsChart(metrics) {
        const canvas = document.getElementById('trendsChart');
        if (!canvas || typeof Chart === 'undefined') return;
        if (trendsChart) trendsChart.destroy();
        clearNoData('trendsChart');

        const termAverages = [1, 2, 3].map((termNo) => {
            const values = metrics
                .map((m) => m[`term${termNo}_avg`])
                .filter((v) => v != null && Number.isFinite(v));
            if (!values.length) return null;
            return values.reduce((a, b) => a + b, 0) / values.length;
        });

        if (termAverages.every((v) => v == null)) {
            renderNoData('trendsChart', 'No term-average records captured for trend analysis.');
            return;
        }

        trendsChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: ['Term 1', 'Term 2', 'Term 3'],
                datasets: [
                    {
                        label: 'Average by Term (%)',
                        data: termAverages.map((v) => (v == null ? null : Number(v.toFixed(2)))),
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,0.15)',
                        fill: true,
                        tension: 0.25,
                        spanGaps: true,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, max: 100 } }
            }
        });
    }

    function drawGradeChart(metrics) {
        const canvas = document.getElementById('gradeDistChart');
        if (!canvas || typeof Chart === 'undefined') return;
        if (gradeChart) gradeChart.destroy();
        clearNoData('gradeDistChart');

        const totals = { EE: 0, ME: 0, AE: 0, BE: 0 };
        metrics.forEach((m) => {
            totals.EE += m.grade_counts.EE;
            totals.ME += m.grade_counts.ME;
            totals.AE += m.grade_counts.AE;
            totals.BE += m.grade_counts.BE;
        });

        const totalScored = totals.EE + totals.ME + totals.AE + totals.BE;
        if (!totalScored) {
            renderNoData('gradeDistChart', 'No graded records available for CBC distribution.');
            return;
        }

        gradeChart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: ['EE (≥75%)', 'ME (60-74%)', 'AE (40-59%)', 'BE (<40%)'],
                datasets: [{
                    data: [totals.EE, totals.ME, totals.AE, totals.BE],
                    backgroundColor: ['#1b5e20', '#2e7d32', '#f57c00', '#b71c1c'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }

    async function generateReport() {
        try {
            const classId = document.getElementById('selectClass')?.value || '';
            const reportType = document.getElementById('reportType')?.value || 'class';
            const metrics = await buildClassMetrics(classId);
            updateKpis(metrics);
            renderDetailedTable(metrics);
            drawPerformanceChart(metrics);
            drawTrendsChart(metrics);
            drawGradeChart(metrics);
            toast('Academic report refreshed', 'success');
        } catch (error) {
            toast(error.message || 'Failed to generate report', 'danger');
        }
    }

    function exportReport() {
        const rows = state.classMetrics;
        if (!rows.length) {
            toast('No class analytics available to export', 'warning');
            return;
        }

        // Use PrintManager for CSV export if available
        if (window.PrintManager) {
            const columns = [
                { key: 'class_name', label: 'Class' },
                { key: 'level_name', label: 'Level' },
                { key: 'student_count', label: 'Students' },
                { key: 'scored_count', label: 'Scored Students' },
                { key: 'average_score', label: 'Average Score' },
                { key: 'pass_rate', label: 'Pass Rate' },
                { key: 'top_score', label: 'Top Score' }
            ];

            const data = rows.map((m) => ({
                class_name: m.class_name,
                level_name: m.level_name,
                student_count: m.student_count,
                scored_count: m.scored_count,
                average_score: m.average_score != null ? m.average_score.toFixed(2) : '',
                pass_rate: m.pass_rate != null ? m.pass_rate.toFixed(2) : '',
                top_score: m.top_score != null ? m.top_score.toFixed(2) : ''
            }));

            window.PrintManager.exportToCSV({
                filename: `academic_report_${new Date().toISOString().slice(0, 10)}.csv`,
                columns: columns,
                rows: data
            });
        } else {
            toast('PrintManager not available for export', 'error');
            return;
        }
        toast('Academic report exported successfully', 'success');
    }

    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        await GradingScale.preload();
        if (typeof AuthContext !== 'undefined' && !AuthContext.hasPermission('academic_view') && !AuthContext.hasPermission('reports_view')) {
            toast('Access denied: insufficient permissions to view academic reports.', 'danger');
            return;
        }
        
        // Initialize Academic Context if available
        if (window.AcademicContext) {
            // Subscribe to context changes
            window.AcademicContext.subscribe((context, event, data) => {
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    // Reload years, terms, and report when academic year or term changes
                    if (contextReady) {
                        loadYears().then(loadTerms).then(generateReport);
                    }
                }
            });
            
            // Ensure context is loaded
            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }
            
            // Get current academic context
            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }
        
        try {
            await loadReferencesComposed();
            await loadYears();
            await Promise.all([loadTerms(), loadClasses(), loadLearningAreas()]);
            document.getElementById('selectYear')?.addEventListener('change', async () => {
                await loadTerms();
                await generateReport();
            });
            document.getElementById('selectTerm')?.addEventListener('change', generateReport);
            document.getElementById('selectClass')?.addEventListener('change', generateReport);
            document.getElementById('reportType')?.addEventListener('change', generateReport);
            await generateReport();
            contextReady = true;
            window.APIRealtime?.register?.('academic_reports', { refresh: async () => { await loadReferencesComposed(); await loadYears(); await Promise.all([loadTerms(), loadClasses(), loadLearningAreas()]); await generateReport(); } }, ['academic']);
        } catch (error) {
            toast(`Failed to initialise: ${error.message}`, 'danger');
        }
    }

    return { init, generateReport, exportReport };
})();

document.addEventListener('DOMContentLoaded', academicReportsCtrl.init);

window.academicReportsCtrl = academicReportsCtrl;
window.APIRealtime?.register?.(academicReportsCtrl, academicReportsCtrl);
