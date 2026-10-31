/**
 * Comparative Reports
 * Renders the annual and term class summaries provided by the academic API.
 */
const comparativeReportsController = (() => {
  const state = { years: [], terms: [], rows: [], charts: [] };

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
  }

  function rowsFrom(response) {
    const payload = response?.data ?? response ?? {};
    if (Array.isArray(payload)) return payload;
    return Array.isArray(payload.rows) ? payload.rows : [];
  }

  function setCell(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = String(value ?? '—');
  }

  async function get(path, params = {}) {
    return window.API.apiCall(path, 'GET', null, params, { checkPermission: false });
  }

  async function loadReferenceData() {
    const [yearsResponse] = await Promise.all([
      get('/academic/years/list')
    ]);
    state.years = rowsFrom(yearsResponse);
    const currentYear = state.years.find((year) => Boolean(Number(year.is_current)) || year.status === 'active')
      || state.years[0];
    if (!currentYear) {
      state.terms = [];
      return null;
    }
    const termsResponse = await get('/academic/terms', { academic_year_id: currentYear.id });
    state.terms = rowsFrom(termsResponse);
    return currentYear;
  }

  async function loadClassMetrics(year, term = null) {
    const params = { view_mode: 'class', year_id: year.id, limit: 500 };
    if (term) params.term_id = term.id;
    const response = await get('/academic/performance-overview', params);
    return rowsFrom(response);
  }

  async function load() {
    const body = document.querySelector('#dataTable tbody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Loading comparative data…</td></tr>';
    try {
      const year = await loadReferenceData();
      if (!year) {
        state.rows = [];
        render();
        return;
      }

      const date = document.getElementById('dateFilter')?.value || '';
      const relevantTerms = state.terms.filter((term) => !date || (term.start_date <= date && term.end_date >= date));
      const termByNumber = new Map();
      for (const term of relevantTerms) {
        const number = Number(String(term.term_number ?? term.term_code ?? term.code ?? term.name ?? '').match(/\d+/)?.[0]);
        if (number >= 1 && number <= 3) termByNumber.set(number, term);
      }

      const [annual, term1, term2, term3] = await Promise.all([
        loadClassMetrics(year),
        termByNumber.has(1) ? loadClassMetrics(year, termByNumber.get(1)) : [],
        termByNumber.has(2) ? loadClassMetrics(year, termByNumber.get(2)) : [],
        termByNumber.has(3) ? loadClassMetrics(year, termByNumber.get(3)) : [],
      ]);
      const maps = [term1, term2, term3].map((rows) => new Map(rows.map((row) => [String(row.class_id), Number(row.average_score)])));
      state.rows = annual.map((row) => {
        const averages = maps.map((map) => map.has(String(row.class_id)) ? map.get(String(row.class_id)) : null);
        return {
          class_id: row.class_id,
          class_name: row.class_name,
          term1: averages[0],
          term2: averages[1],
          term3: averages[2],
          annual: row.average_score == null ? null : Number(row.average_score),
        };
      });
      render();
    } catch (error) {
      console.error('Comparative reports failed to load:', error);
      state.rows = [];
      body.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(error.message || 'Could not load comparative data.')}</td></tr>`;
      ['statBestClass', 'statImproved', 'statLeaders', 'statMean'].forEach((id) => setCell(id, '—'));
    }
  }

  function filteredRows() {
    const search = (document.getElementById('searchInput')?.value || '').trim().toLowerCase();
    const status = document.getElementById('filterSelect')?.value || '';
    return state.rows.filter((row) => (!search || row.class_name.toLowerCase().includes(search))
      && (!status || String(row.class_id) === status));
  }

  function render() {
    const body = document.querySelector('#dataTable tbody');
    if (!body) return;
    const rows = filteredRows();
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No academic performance data is available for this scope.</td></tr>';
    } else {
      body.innerHTML = rows.map((row, index) => {
        const values = [row.term1, row.term2, row.term3, row.annual];
        const trend = row.term1 == null || row.term3 == null ? '—' : row.term3 > row.term1 ? '↑ Improving' : row.term3 < row.term1 ? '↓ Declining' : '→ Stable';
        return `<tr><td>${index + 1}</td><td>${escapeHtml(row.class_name || '—')}</td>${values.map((value) => `<td>${value == null || !Number.isFinite(value) ? '—' : `${value.toFixed(1)}%`}</td>`).join('')}<td>${trend}</td></tr>`;
      }).join('');
    }
    const scored = rows.filter((row) => Number.isFinite(row.annual));
    const best = scored.reduce((result, row) => !result || row.annual > result.annual ? row : result, null);
    const improved = rows.reduce((result, row) => {
      const difference = row.term1 == null || row.term3 == null ? null : row.term3 - row.term1;
      return difference == null || (result && difference <= result.difference) ? result : { row, difference };
    }, null);
    setCell('statBestClass', best?.class_name || '—');
    setCell('statImproved', improved?.row.class_name || '—');
    setCell('statLeaders', '—'); // This endpoint does not return subject-level leaders.
    setCell('statMean', scored.length ? `${(scored.reduce((sum, row) => sum + row.annual, 0) / scored.length).toFixed(1)}%` : '—');
    drawCharts(rows);
  }

  function drawCharts(rows) {
    if (typeof Chart === 'undefined') return;
    const definitions = [
      { id: 'trendChart', type: 'line', labels: ['Term 1', 'Term 2', 'Term 3'], values: [1, 2, 3].map((number) => {
        const vals = rows.map((row) => row[`term${number}`]).filter(Number.isFinite);
        return vals.length ? vals.reduce((sum, value) => sum + value, 0) / vals.length : null;
      }) },
      { id: 'comparisonChart', type: 'bar', labels: rows.map((row) => row.class_name), values: rows.map((row) => row.annual) },
    ];
    definitions.forEach((definition, index) => {
      const canvas = document.getElementById(definition.id);
      if (!canvas) return;
      state.charts[index]?.destroy();
      state.charts[index] = new Chart(canvas, {
        type: definition.type,
        data: { labels: definition.labels, datasets: [{ label: definition.id === 'trendChart' ? 'Mean score (%)' : 'Annual mean (%)', data: definition.values, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.35)', spanGaps: true }] },
        options: { responsive: true, scales: { y: { beginAtZero: true, max: 100 } } },
      });
    });
  }

  function exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport()) return;
    const rows = filteredRows();
    if (!rows.length) return;
    const fields = ['Class', 'Term 1 Mean', 'Term 2 Mean', 'Term 3 Mean', 'Annual Mean', 'Trend'];
    const csvCell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [fields, ...rows.map((row) => [row.class_name, row.term1, row.term2, row.term3, row.annual, row.term1 == null || row.term3 == null ? '' : row.term3 > row.term1 ? 'Improving' : row.term3 < row.term1 ? 'Declining' : 'Stable'])]
      .map((row) => row.map(csvCell).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'comparative_reports.csv', 'text/csv;charset=utf-8;');
  }

  function print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint()) return;
    window.print();
  }

  function bind() {
    const filter = document.getElementById('filterSelect');
    if (filter) filter.innerHTML = '<option value="">All Classes</option>';
    document.getElementById('searchInput')?.addEventListener('input', render);
    filter?.addEventListener('change', render);
    document.getElementById('dateFilter')?.addEventListener('change', load);
  }

  async function init() {
    await window.AuthContext?.ready?.();
    bind();
    await load();
    const filter = document.getElementById('filterSelect');
    if (filter) filter.innerHTML = '<option value="">All Classes</option>' + state.rows.map((row) => `<option value="${Number(row.class_id)}">${escapeHtml(row.class_name)}</option>`).join('');
  }

  return { init, refresh: load, exportCSV, print };
})();

window.comparativeReportsController = comparativeReportsController;
window.ComparativeReportsController = {
  refresh: () => comparativeReportsController.refresh(),
  exportCSV: () => comparativeReportsController.exportCSV(),
  print: () => comparativeReportsController.print(),
};
document.addEventListener('DOMContentLoaded', () => comparativeReportsController.init());
