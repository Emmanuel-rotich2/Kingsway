/** Class teacher progress comparison, based on term performance records. */
const studentProgressReportsController = (() => {
  const state = { years: [], terms: [], classes: [], rows: [] };
  const $ = (id) => document.getElementById(id);
  const payloadOf = (response) => response?.data ?? response ?? {};
  const rowsOf = (response) => {
    const payload = payloadOf(response);
    return Array.isArray(payload) ? payload : Array.isArray(payload.rows) ? payload.rows : [];
  };
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[character]);

  async function get(path, params = {}) {
    return window.API.apiCall(path, 'GET', null, params, { checkPermission: false });
  }

  function revealContent() {
    $('progressLoading')?.classList.add('d-none');
    if ($('progressContent')) $('progressContent').style.display = '';
  }

  async function loadReferences() {
    const [years, classes] = await Promise.all([get('/academic/years/list'), get('/academic/classes-list')]);
    state.years = rowsOf(years);
    state.classes = rowsOf(classes);
    const yearSelect = $('yearFilter');
    if (yearSelect) {
      yearSelect.innerHTML = '<option value="">Current Academic Year</option>' + state.years.map((year) => `<option value="${Number(year.id)}">${escapeHtml(year.year_name || year.name || year.year_code)}</option>`).join('');
      const current = state.years.find((year) => Boolean(Number(year.is_current)) || year.status === 'active');
      if (current) yearSelect.value = String(current.id);
    }
    const classSelect = $('classFilter');
    if (classSelect) classSelect.innerHTML = '<option value="">All Classes</option>' + state.classes.map((item) => `<option value="${Number(item.id)}">${escapeHtml(item.name || item.class_name)}</option>`).join('');
    await loadTerms();
  }

  async function loadTerms() {
    const yearId = $('yearFilter')?.value || '';
    const response = await get('/academic/terms', yearId ? { academic_year_id: yearId } : {});
    state.terms = rowsOf(response);
    const select = $('termFilter');
    const previousValue = select?.value || '';
    if (select) {
      select.innerHTML = '<option value="">All Terms</option>' + state.terms.map((term) => `<option value="${Number(term.id)}">${escapeHtml(term.name || term.term_name)}</option>`).join('');
      if (state.terms.some((term) => String(term.id) === previousValue)) select.value = previousValue;
      else {
        const current = state.terms.find((term) => term.status === 'current' || term.is_current);
        if (current) select.value = String(current.id);
      }
    }
  }

  function previousTerm(currentTerm) {
    if (!currentTerm) return null;
    const currentNumber = Number(String(currentTerm.term_number || currentTerm.term_code || currentTerm.name || '').match(/\d+/)?.[0] || 0);
    return state.terms
      .filter((term) => term.id !== currentTerm.id && Number(String(term.term_number || term.term_code || term.name || '').match(/\d+/)?.[0] || 0) < currentNumber)
      .sort((left, right) => Number(String(right.term_number || right.term_code || right.name).match(/\d+/)?.[0] || 0) - Number(String(left.term_number || left.term_code || left.name).match(/\d+/)?.[0] || 0))[0] || null;
  }

  async function requestRows(termId, yearId, classId) {
    const params = { view_mode: 'students', limit: 500, class_teacher_only: true };
    if (yearId) params.year_id = yearId;
    if (termId) params.term_id = termId;
    if (classId) params.class_id = classId;
    return rowsOf(await get('/academic/performance-overview', params));
  }

  async function load() {
    const body = $('progressTableBody');
    if (!body) return;
    $('progressError')?.classList.add('d-none');
    body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading progress comparisons…</td></tr>';
    try {
      const yearId = $('yearFilter')?.value || '';
      const termId = $('termFilter')?.value || '';
      const classId = $('classFilter')?.value || '';
      const term = state.terms.find((item) => String(item.id) === termId) || null;
      const prior = previousTerm(term);
      const [currentRows, previousRows] = await Promise.all([
        requestRows(termId, yearId, classId),
        prior ? requestRows(prior.id, yearId, classId) : Promise.resolve([]),
      ]);
      const previousById = new Map(previousRows.map((row) => [String(row.student_id), row]));
      state.rows = currentRows.map((row) => {
        const previous = previousById.get(String(row.student_id));
        const currentAverage = row.average_score == null ? null : Number(row.average_score);
        const previousAverage = previous?.average_score == null ? null : Number(previous.average_score);
        return { ...row, current_average: currentAverage, previous_average: previousAverage,
          change: currentAverage == null || previousAverage == null ? null : currentAverage - previousAverage };
      });
      render();
    } catch (error) {
      console.error('Student progress report failed:', error);
      state.rows = [];
      body.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${escapeHtml(error.message || 'Could not load progress reports.')}</td></tr>`;
      if ($('progressError')) { $('progressError').textContent = error.message || 'Could not load progress reports.'; $('progressError').classList.remove('d-none'); }
    } finally {
      revealContent();
    }
  }

  function render() {
    const body = $('progressTableBody');
    if (!body) return;
    if (!state.rows.length) {
      body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No performance records found for the selected filters.</td></tr>';
    } else {
      body.innerHTML = state.rows.map((row) => {
        const change = row.change;
        const trend = change == null ? 'No prior term' : change > 0 ? 'Improved' : change < 0 ? 'Declined' : 'No change';
        const color = change == null || change === 0 ? 'secondary' : change > 0 ? 'success' : 'danger';
        const detailsUrl = `${window.APP_BASE || ''}/home.php?route=student_performance&student_id=${Number(row.student_id)}`;
        return `<tr><td>${escapeHtml(row.full_name || '—')}</td><td>${escapeHtml(row.admission_no || '—')}</td><td>${escapeHtml(row.class_name || '—')}</td>
          <td>${row.previous_average == null ? '—' : `${row.previous_average.toFixed(1)}%`}</td><td>${row.current_average == null ? '—' : `${row.current_average.toFixed(1)}%`}</td>
          <td>${change == null ? '—' : `<span class="text-${color}">${change > 0 ? '+' : ''}${change.toFixed(1)}%</span>`}</td><td><span class="badge bg-${color}">${trend}</span></td>
          <td><a class="btn btn-sm btn-outline-primary" href="${escapeHtml(detailsUrl)}" title="View learner report"><i class="bi bi-eye"></i></a></td></tr>`;
      }).join('');
    }
    const values = state.rows.map((row) => row.change).filter((change) => change != null);
    setText('totalStudents', state.rows.length);
    setText('improvedStudents', values.filter((change) => change > 0).length);
    setText('declinedStudents', values.filter((change) => change < 0).length);
  }

  function setText(id, value) { if ($(id)) $(id).textContent = String(value ?? '—'); }

  function exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport()) return;
    if (!state.rows.length) return;
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [['Student', 'Admission No', 'Class', 'Previous Average (%)', 'Current Average (%)', 'Change (%)', 'Trend'],
      ...state.rows.map((row) => [row.full_name, row.admission_no, row.class_name, row.previous_average, row.current_average, row.change, row.change == null ? 'No prior term' : row.change > 0 ? 'Improved' : row.change < 0 ? 'Declined' : 'No change'])]
      .map((row) => row.map(cell).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'student_progress_reports.csv', 'text/csv;charset=utf-8;');
  }

  function print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint()) return;
    window.print();
  }

  function bindFilters() {
    $('yearFilter')?.addEventListener('change', async () => { await loadTerms(); await load(); });
    $('termFilter')?.addEventListener('change', load);
    $('classFilter')?.addEventListener('change', load);
    $('exportBtn')?.addEventListener('click', exportCSV);
    $('printBtn')?.addEventListener('click', print);
  }

  async function init() {
    await window.AuthContext?.ready?.();
    bindFilters();
    try { await loadReferences(); await load(); }
    catch (error) {
      console.error('Student progress report initialization failed:', error);
      revealContent();
      if ($('progressError')) { $('progressError').textContent = error.message || 'Could not prepare progress report filters.'; $('progressError').classList.remove('d-none'); }
      if ($('progressTableBody')) $('progressTableBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Could not prepare progress report filters.</td></tr>';
    }
    window.APIRealtime?.register?.('student_progress_reports', { refresh: load }, ['students', 'academic']);
  }

  return { init, load, exportCSV, print };
})();

window.studentProgressReportsController = studentProgressReportsController;
document.addEventListener('DOMContentLoaded', () => studentProgressReportsController.init());
