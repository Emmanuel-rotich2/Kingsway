/**
 * performance_reports.js — Performance Reports Controller
 * Live class metrics only (no random/demo values).
 */
const performanceReportsCtrl = (() => {
    let subjectChart = null;
    let gradeChart = null;

    const state = {
        terms: [],
        classes: [],
        subjects: [],
        classMetrics: [],
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('prToast');
        if (!el) return;
        el.className = `toast align-items-center text-bg-${type} border-0`;
        const body = document.getElementById('prToastBody');
        if (body) body.textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, { delay: 3200 }).show();
    }

    function cbcBand(score) {
        const n = Number(score);
        if (!Number.isFinite(n)) return null;
        if (n >= 80) return 'EE';
        if (n >= 50) return 'ME';
        if (n >= 25) return 'AE';
        return 'BE';
    }

    function gradeBadge(code) {
        if (!code) return '—';
        return `<span class="grade-${code}">${code}</span>`;
    }

    async function apiGet(route, params = {}) {
        return window.API.apiCall(`/${route}`, 'GET', null, params, { checkPermission: false });
    }

    function renderNoData(canvasId, message) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !canvas.parentElement) return;
        const parent = canvas.parentElement;
        const existing = parent.querySelector('.pr-chart-empty');
        if (existing) existing.remove();
        const msg = document.createElement('div');
        msg.className = 'pr-chart-empty text-muted text-center py-4';
        msg.textContent = message;
        parent.appendChild(msg);
    }

    function clearNoData(canvasId) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !canvas.parentElement) return;
        const existing = canvas.parentElement.querySelector('.pr-chart-empty');
        if (existing) existing.remove();
    }

    async function loadTerms() {
        const terms = await apiGet('academic/terms-list');
        state.terms = Array.isArray(terms) ? terms : [];
        const sel = document.getElementById('examTerm');
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
        const sel = document.getElementById('classFilter');
        if (!sel) return;
        sel.innerHTML = '<option value="">All Classes</option>';
        state.classes.forEach((c) => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = `${c.name} (${c.level_code || ''})`;
            sel.appendChild(opt);
        });
    }

    async function loadSubjects() {
        const subjects = await apiGet('academic');
        state.subjects = Array.isArray(subjects) ? subjects : [];
        const sel = document.getElementById('subjectFilter');
        if (!sel) return;
        sel.innerHTML = '<option value="">All Subjects</option>';
        state.subjects.forEach((s) => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = `${s.name}${s.code ? ` (${s.code})` : ''}`;
            sel.appendChild(opt);
        });
    }

    async function fetchStudentsByClass(classId) {
        try {
            const rows = await apiGet(`students/by-class-get/${classId}`);
            return Array.isArray(rows) ? rows : [];
        } catch {
            return [];
        }
    }

    function computeMetric(classInfo, students) {
        const scores = students
            .map((s) => (s.year_average != null ? Number(s.year_average) : null))
            .filter((v) => v !== null && Number.isFinite(v));

        const avg = scores.length ? scores.reduce((a, b) => a + b, 0) / scores.length : null;
        const passRate = scores.length ? (scores.filter((v) => v >= 50).length / scores.length) * 100 : null;
        const top = scores.length ? Math.max(...scores) : null;

        return {
            class_name: classInfo.name,
            level_name: classInfo.level_name || classInfo.level_code || '—',
            class_teacher: classInfo.class_teacher_name || '—',
            students: students.length,
            streams: Number(classInfo.stream_count || 0),
            scored: scores.length,
            avg,
            passRate,
            top,
            grades: {
                EE: scores.filter((v) => cbcBand(v) === 'EE').length,
                ME: scores.filter((v) => cbcBand(v) === 'ME').length,
                AE: scores.filter((v) => cbcBand(v) === 'AE').length,
                BE: scores.filter((v) => cbcBand(v) === 'BE').length,
            }
        };
    }

    async function buildMetrics(selectedClassId = '') {
        const classes = selectedClassId
            ? state.classes.filter((c) => Number(c.id) === Number(selectedClassId))
            : state.classes;

        const metrics = await Promise.all(
            classes.map(async (c) => {
                const students = await fetchStudentsByClass(c.id);
                return computeMetric(c, students);
            })
        );

        state.classMetrics = metrics;
        return metrics;
    }

    function updateKpis(metrics) {
        const totalStudents = metrics.reduce((sum, m) => sum + m.students, 0);
        const avgValues = metrics.map((m) => m.avg).filter((v) => v != null);
        const passValues = metrics.map((m) => m.passRate).filter((v) => v != null);
        const topValues = metrics.map((m) => m.top).filter((v) => v != null);

        const overallAvg = avgValues.length ? avgValues.reduce((a, b) => a + b, 0) / avgValues.length : null;
        const passRate = passValues.length ? passValues.reduce((a, b) => a + b, 0) / passValues.length : null;
        const topScore = topValues.length ? Math.max(...topValues) : null;

        document.getElementById('studentsCount').textContent = totalStudents;
        document.getElementById('classAverage').textContent = overallAvg != null ? `${overallAvg.toFixed(1)}%` : 'No scores';
        document.getElementById('passRate').textContent = passRate != null ? `${passRate.toFixed(1)}%` : 'No scores';
        document.getElementById('topScore').textContent = topScore != null ? `${topScore.toFixed(1)}%` : 'No scores';
        document.getElementById('prMeta').textContent = `${metrics.length} class(es) · ${totalStudents} students`;
    }

    function renderTable(reportType, metrics) {
        const headers = document.getElementById('tableHeaders');
        const body = document.getElementById('tableBody');
        const footer = document.getElementById('tableFooter');
        if (!headers || !body || !footer) return;

        if (reportType === 'grade_distribution') {
            headers.innerHTML = '<th>Class</th><th>EE</th><th>ME</th><th>AE</th><th>BE</th><th>Scored</th>';
            body.innerHTML = metrics.map((m) => `
                <tr>
                    <td><strong>${m.class_name}</strong></td>
                    <td>${m.grades.EE}</td>
                    <td>${m.grades.ME}</td>
                    <td>${m.grades.AE}</td>
                    <td>${m.grades.BE}</td>
                    <td>${m.scored}</td>
                </tr>
            `).join('') || '<tr><td colspan="6" class="pr-empty">No records</td></tr>';
            footer.innerHTML = '';
            return;
        }

        headers.innerHTML = '<th>Class</th><th>Level</th><th>Students</th><th>Streams</th><th>Teacher</th><th>Average</th><th>Pass Rate</th><th>Top Score</th><th>CBC Band</th>';
        body.innerHTML = metrics.map((m) => {
            const band = m.avg != null ? cbcBand(m.avg) : null;
            return `
                <tr>
                    <td><strong>${m.class_name}</strong></td>
                    <td>${m.level_name}</td>
                    <td>${m.students}</td>
                    <td>${m.streams}</td>
                    <td>${m.class_teacher}</td>
                    <td>${m.avg != null ? `${m.avg.toFixed(1)}%` : '—'}</td>
                    <td>${m.passRate != null ? `${m.passRate.toFixed(1)}%` : '—'}</td>
                    <td>${m.top != null ? `${m.top.toFixed(1)}%` : '—'}</td>
                    <td>${band ? gradeBadge(band) : '—'}</td>
                </tr>
            `;
        }).join('') || '<tr><td colspan="9" class="pr-empty">No records</td></tr>';
        footer.innerHTML = '';
    }

    function drawCharts(metrics) {
        const scoreCanvas = document.getElementById('subjectPerformanceChart');
        const gradeCanvas = document.getElementById('gradeDistributionChart');
        if (typeof Chart === 'undefined' || !scoreCanvas || !gradeCanvas) return;

        if (subjectChart) subjectChart.destroy();
        if (gradeChart) gradeChart.destroy();
        clearNoData('subjectPerformanceChart');
        clearNoData('gradeDistributionChart');

        const withScores = metrics.filter((m) => m.avg != null);
        if (!withScores.length) {
            renderNoData('subjectPerformanceChart', 'No class score data available yet.');
            renderNoData('gradeDistributionChart', 'No graded records available yet.');
            return;
        }

        subjectChart = new Chart(scoreCanvas, {
            type: 'bar',
            data: {
                labels: withScores.map((m) => m.class_name),
                datasets: [{
                    label: 'Average Score (%)',
                    data: withScores.map((m) => Number(m.avg.toFixed(2))),
                    backgroundColor: '#1565c0',
                    borderRadius: 6,
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 100 } } }
        });

        const totals = { EE: 0, ME: 0, AE: 0, BE: 0 };
        withScores.forEach((m) => {
            totals.EE += m.grades.EE;
            totals.ME += m.grades.ME;
            totals.AE += m.grades.AE;
            totals.BE += m.grades.BE;
        });

        gradeChart = new Chart(gradeCanvas, {
            type: 'doughnut',
            data: {
                labels: ['EE', 'ME', 'AE', 'BE'],
                datasets: [{
                    data: [totals.EE, totals.ME, totals.AE, totals.BE],
                    backgroundColor: ['#1b5e20', '#2e7d32', '#f57c00', '#b71c1c'],
                    borderWidth: 0,
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }

    function renderSubjectSection(reportType) {
        const card = document.getElementById('subjectAnalysisCard');
        const body = document.getElementById('subjectStatsBody');
        const chartCanvas = document.getElementById('strengthsWeaknessChart');

        if (!card || !body) return;
        if (reportType !== 'subject_analysis') {
            card.style.display = 'none';
            return;
        }

        card.style.display = 'block';
        body.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Subject-level scores are not recorded yet. Enter assessments and marks to populate this section.</td></tr>';

        if (chartCanvas && chartCanvas.parentElement) {
            const existing = chartCanvas.parentElement.querySelector('.pr-chart-empty');
            if (existing) existing.remove();
            const msg = document.createElement('div');
            msg.className = 'pr-chart-empty text-muted text-center py-4';
            msg.textContent = 'No subject analytics available.';
            chartCanvas.parentElement.appendChild(msg);
        }
    }

    async function generateReport() {
        try {
            const reportType = document.getElementById('reportType')?.value || 'class_performance';
            const classId = document.getElementById('classFilter')?.value || '';
            document.getElementById('tableTitle').textContent = reportType.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

            const metrics = await buildMetrics(classId);
            updateKpis(metrics);
            renderTable(reportType, metrics);
            drawCharts(metrics);
            renderSubjectSection(reportType);

            const chartsRow = document.getElementById('chartsRow');
            if (chartsRow) chartsRow.style.display = 'flex';
            toast('Performance report refreshed', 'success');
        } catch (error) {
            toast(error.message || 'Failed to generate report', 'danger');
        }
    }

    function exportReport() {
        if (!state.classMetrics.length) {
            toast('No class metrics available to export', 'warning');
            return;
        }
        const rows = [
            ['Class', 'Students', 'Scored', 'Average', 'Pass Rate', 'Top'],
            ...state.classMetrics.map((m) => [
                m.class_name,
                m.students,
                m.scored,
                m.avg != null ? m.avg.toFixed(2) : '',
                m.passRate != null ? m.passRate.toFixed(2) : '',
                m.top != null ? m.top.toFixed(2) : ''
            ])
        ];
        const csv = rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `performance_report_${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function printReport() {
        window.print();
    }

    async function init() {
        try {
            await Promise.all([loadTerms(), loadClasses(), loadSubjects()]);
            await generateReport();
        } catch (error) {
            toast(`Failed to initialise: ${error.message}`, 'danger');
        }
    }

    return { init, generateReport, exportReport, printReport };
})();

document.addEventListener('DOMContentLoaded', performanceReportsCtrl.init);
