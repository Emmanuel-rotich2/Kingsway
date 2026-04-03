/**
 * academic_reports.js — Academic Analytics Controller
 * Uses live classes/students data and avoids synthetic placeholder scores.
 */
const academicReportsCtrl = (() => {
    let perfChart = null;
    let trendsChart = null;
    let gradeChart = null;

    const state = {
        years: [],
        terms: [],
        classes: [],
        learningAreas: [],
        classMetrics: []
    };

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
        if (n >= 80) return 'EE';
        if (n >= 50) return 'ME';
        if (n >= 25) return 'AE';
        return 'BE';
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
        const years = await apiGet('academic/years-list');
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
        const terms = await apiGet('academic/terms-list');
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
        const classes = await apiGet('academic/classes-list');
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
            const res = await window.API.academic.listLearningAreas().catch(() => null)
                || await apiGet('academic/learning-areas/list').catch(() => null);
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
        const classes = selectedClassId
            ? state.classes.filter((c) => Number(c.id) === Number(selectedClassId))
            : state.classes;

        const metrics = await Promise.all(
            classes.map(async (cls) => {
                const students = await fetchStudentsByClass(cls.id);
                return computeClassMetric(cls, students);
            })
        );

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
        if (!metrics.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="ar-empty"><i class="bi bi-table"></i>No class data available</td></tr>';
            return;
        }

        tbody.innerHTML = metrics.map((m) => {
            const avg = m.average_score != null ? `${m.average_score.toFixed(1)}%` : '—';
            const pass = m.pass_rate != null ? `${m.pass_rate.toFixed(1)}%` : '—';
            const top = m.top_score != null ? `${m.top_score.toFixed(1)}%` : '—';
            const scoreHealth = m.scored_count > 0 ? `${m.scored_count}/${m.student_count}` : '0';
            return `
                <tr>
                    <td><strong>${m.class_name}</strong></td>
                    <td>${m.level_name}</td>
                    <td>${m.student_count}</td>
                    <td>${m.stream_count}</td>
                    <td>${avg}</td>
                    <td>${pass}</td>
                    <td>${top}</td>
                    <td>${scoreHealth}</td>
                    <td>${m.term1_avg != null ? `${m.term1_avg.toFixed(1)}%` : '—'}</td>
                    <td>${m.term3_avg != null ? `${m.term3_avg.toFixed(1)}%` : '—'}</td>
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
                labels: ['EE (≥80%)', 'ME (50-79%)', 'AE (25-49%)', 'BE (<25%)'],
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

        const header = ['Class', 'Level', 'Students', 'Scored Students', 'Average Score', 'Pass Rate', 'Top Score'];
        const data = rows.map((m) => [
            m.class_name,
            m.level_name,
            m.student_count,
            m.scored_count,
            m.average_score != null ? m.average_score.toFixed(2) : '',
            m.pass_rate != null ? m.pass_rate.toFixed(2) : '',
            m.top_score != null ? m.top_score.toFixed(2) : '',
        ]);

        const csv = [header, ...data]
            .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(','))
            .join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `academic_report_${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        if (typeof AuthContext !== 'undefined' && !AuthContext.hasPermission('academic_view') && !AuthContext.hasPermission('reports_view')) {
            toast('Access denied: insufficient permissions to view academic reports.', 'danger');
            return;
        }
        try {
            await Promise.all([loadYears(), loadTerms(), loadClasses(), loadLearningAreas()]);
            await generateReport();
        } catch (error) {
            toast(`Failed to initialise: ${error.message}`, 'danger');
        }
    }

    return { init, generateReport, exportReport };
})();

document.addEventListener('DOMContentLoaded', academicReportsCtrl.init);
