/** Performance Analysis page controller. */
const performanceAnalysisController = (() => {
  const state = { subjects: [], years: [], terms: [], rows: [], charts: [] };

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
  }

  function payloadOf(response) { return response?.data ?? response ?? {}; }
  function rowsOf(response) {
    const payload = payloadOf(response);
    return Array.isArray(payload) ? payload : Array.isArray(payload.rows) ? payload.rows : [];
  }
  function setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = String(value ?? '—');
  }
  async function get(path, params = {}) {
    return window.API.apiCall(path, 'GET', null, params, { checkPermission: false });
  }

  async function loadContext() {
    const years = await get('/academic/years/list');
    state.years = rowsOf(years);
    const year = state.years.find((item) => Boolean(Number(item.is_current)) || item.status === 'active') || state.years[0];
    if (!year) return null;
    const terms = await get('/academic/terms', { academic_year_id: year.id });
    state.terms = rowsOf(terms);
    return year;
  }

  function activeTermForDate() {
    const date = document.getElementById('dateFilter')?.value || '';
    if (!date) return null;
    return state.terms.find((term) => term.start_date <= date && term.end_date >= date) || null;
  }

  async function load() {
    const body = document.querySelector('#dataTable tbody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading performance data…</td></tr>';
    try {
      const year = await loadContext();
      if (!year) {
        state.rows = [];
        render();
        return;
      }
      const term = activeTermForDate();
      const params = { year_id: year.id, limit: 500 };
      if (term) params.term_id = term.id;
      const [analysisResponse, studentsResponse] = await Promise.all([
        get('/academic/results-analysis', params),
        get('/academic/performance-overview', { ...params, view_mode: 'students' }),
      ]);
      const analysis = payloadOf(analysisResponse);
      const subjectMetrics = Array.isArray(analysis.subject_metrics) ? analysis.subject_metrics : [];
      const students = rowsOf(studentsResponse);
      const subjectIds = new Map(state.subjects.map((subject) => [Number(subject.id), subject.name || subject.subject_name]));
      state.rows = subjectMetrics.map((metric) => ({
        subject_id: Number(metric.subject_id || 0),
        subject: metric.subject_name || subjectIds.get(Number(metric.subject_id)) || '—',
        students: Number(metric.students_assessed || 0),
        mean: metric.avg_overall_pct == null ? null : Number(metric.avg_overall_pct),
        highest: null,
        lowest: null,
        pass_rate: metric.pass_rate == null ? null : Number(metric.pass_rate),
        ee: Number(metric.ee_count || 0), me: Number(metric.me_count || 0),
        ae: Number(metric.ae_count || 0), be: Number(metric.be_count || 0),
      }));
      const scored = students.map((student) => student.average_score == null ? null : Number(student.average_score)).filter((score) => score !== null && Number.isFinite(score));
      const mean = scored.length ? scored.reduce((sum, score) => sum + score, 0) / scored.length : null;
      const above = mean == null ? null : scored.filter((score) => score > mean).length;
      setText('statMean', mean == null ? '—' : `${mean.toFixed(1)}%`);
      const best = state.rows.reduce((result, row) => row.mean == null || (result && row.mean <= result.mean) ? result : row, null);
      setText('statBestSubject', best?.subject || '—');
      setText('statAboveAvg', above == null ? '—' : above);
      setText('statBelowAvg', above == null ? '—' : scored.length - above);
      render();
    } catch (error) {
      console.error('Performance analysis failed to load:', error);
      state.rows = [];
      body.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${escapeHtml(error.message || 'Could not load performance data.')}</td></tr>`;
      ['statMean', 'statBestSubject', 'statAboveAvg', 'statBelowAvg'].forEach((id) => setText(id, '—'));
    }
  }

  function visibleRows() {
    const search = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const subjectId = document.getElementById('filterSelect')?.value || '';
    return state.rows.filter((row) => (!search || row.subject.toLowerCase().includes(search))
      && (!subjectId || String(row.subject_id) === subjectId));
  }

  function render() {
    const body = document.querySelector('#dataTable tbody');
    if (!body) return;
    const rows = visibleRows();
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No subject performance data is available for this scope.</td></tr>';
    } else {
      body.innerHTML = rows.map((row) => `<tr>
        <td>${escapeHtml(row.subject)}</td><td>${row.students}</td>
        <td>${row.mean == null ? '—' : `${row.mean.toFixed(1)}%`}</td>
        <td>${row.ee}</td><td>${row.me}</td><td>${row.ae}</td><td>${row.be}</td>
        <td>${row.pass_rate == null ? '—' : `${row.pass_rate.toFixed(1)}%`}</td>
      </tr>`).join('');
    }
    drawCharts(rows);
  }

  function drawCharts(rows) {
    if (typeof Chart === 'undefined') return;
    const chartData = [
      { id: 'trendChart', type: 'line', labels: rows.map((row) => row.subject), values: rows.map((row) => row.mean), label: 'Mean score (%)' },
      { id: 'comparisonChart', type: 'bar', labels: rows.map((row) => row.subject), values: rows.map((row) => row.pass_rate), label: 'Pass rate (%)' },
    ];
    chartData.forEach((item, index) => {
      const canvas = document.getElementById(item.id);
      if (!canvas) return;
      state.charts[index]?.destroy();
      state.charts[index] = new Chart(canvas, {
        type: item.type,
        data: { labels: item.labels, datasets: [{ label: item.label, data: item.values, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.3)', spanGaps: true }] },
        options: { responsive: true, scales: { y: { beginAtZero: true, max: 100 } } },
      });
    });
  }

  function exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport()) return;
    const rows = visibleRows();
    if (!rows.length) return;
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [['Subject', 'Students Assessed', 'Mean Score (%)', 'EE', 'ME', 'AE', 'BE', 'Pass Rate (%)'],
      ...rows.map((row) => [row.subject, row.students, row.mean, row.ee, row.me, row.ae, row.be, row.pass_rate])]
      .map((row) => row.map(cell).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'performance_analysis.csv', 'text/csv;charset=utf-8;');
  }

  function print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint()) return;
    window.print();
  }

  function bind() {
    document.getElementById('searchInput')?.addEventListener('input', render);
    document.getElementById('filterSelect')?.addEventListener('change', render);
    document.getElementById('dateFilter')?.addEventListener('change', load);
  }

  async function init() {
    await window.AuthContext?.ready?.();
    try {
      const subjects = await get('/academic/learning-areas/list');
      state.subjects = rowsOf(subjects);
    } catch (error) {
      console.warn('Performance subject list unavailable:', error);
    }
    bind();
    await load();
    const filter = document.getElementById('filterSelect');
    if (filter) filter.innerHTML = '<option value="">All Subjects</option>' + state.subjects.map((subject) => `<option value="${Number(subject.id)}">${escapeHtml(subject.name || subject.subject_name)}</option>`).join('');
  }

  return { init, refresh: load, exportCSV, print };
})();

window.performanceAnalysisController = performanceAnalysisController;
window.PerformanceAnalysisController = {
  refresh: () => performanceAnalysisController.refresh(),
  exportCSV: () => performanceAnalysisController.exportCSV(),
  print: () => performanceAnalysisController.print(),
};
document.addEventListener('DOMContentLoaded', () => performanceAnalysisController.init());
