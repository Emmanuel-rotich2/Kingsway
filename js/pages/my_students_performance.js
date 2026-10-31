/**
 * Class-teacher performance view. Results are scoped server-side to the
 * authenticated teacher's assigned streams by AcademicManager::getReports.
 */
const myStudentsPerformanceController = {
  state: { rows: [], students: [], classes: [], years: [], terms: [], currentYearId: null, currentTermId: null },

  esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[ch]));
  },
  list(response, keys = []) {
    let value = response;
    for (let depth = 0; depth < 6; depth += 1) {
      if (Array.isArray(value)) return value;
      if (!value || typeof value !== 'object') return [];
      const nextKey = keys.find(key => Array.isArray(value[key]));
      if (nextKey) return value[nextKey];
      value = value.data ?? value.items ?? value.rows ?? value.records;
    }
    return Array.isArray(value) ? value : [];
  },
  api(path, method = 'GET', body) { return window.API.callAPI(path, method, body); },
  setText(id, value) { const node = document.getElementById(id); if (node) node.textContent = value ?? ''; },
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const exportButton = document.getElementById('exportBtn');
    const printButton = document.getElementById('printBtn');
    if (window.AuthContext?.canExport && exportButton) exportButton.hidden = !window.AuthContext.canExport('academic');
    if (window.AuthContext?.canPrint && printButton) printButton.hidden = !window.AuthContext.canPrint('academic');
    document.getElementById('performanceLoading')?.classList.remove('d-none');
    document.getElementById('performanceContent')?.style && (document.getElementById('performanceContent').style.display = 'none');
    this.bind();
    try {
      const [contextResponse, yearsResponse] = await Promise.all([
        this.api('/academic/context'),
        this.api('/academic/years/list'),
      ]);
      const context = contextResponse?.data || contextResponse || {};
      this.state.years = this.list(yearsResponse, ['academic_years', 'years']);
      this.state.currentYearId = Number(context.academic_year_id || context.current_year?.id || 0) || null;
      this.state.currentTermId = Number(context.term_id || context.current_term?.id || 0) || null;
      this.renderYears();
      await this.loadTerms();
      await this.loadPerformance();
      window.APIRealtime?.register?.('my_students_performance', {
        refresh: () => this.loadPerformance(),
      }, ['academic', 'results']);
    } catch (error) {
      this.showError(error?.message || 'Performance data could not be loaded.');
    }
  },
  bind() {
    document.getElementById('yearFilter')?.addEventListener('change', async () => {
      await this.loadTerms();
      await this.loadPerformance();
    });
    document.getElementById('termFilter')?.addEventListener('change', () => this.loadPerformance());
    document.getElementById('classFilter')?.addEventListener('change', () => this.render());
    document.getElementById('exportBtn')?.addEventListener('click', () => this.exportCsv());
    document.getElementById('printBtn')?.addEventListener('click', () => {
      if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('academic')) {
        return window.showNotification?.('You do not have permission to print academic results.', 'warning');
      }
      window.print();
    });
  },
  renderYears() {
    const select = document.getElementById('yearFilter');
    if (!select) return;
    select.innerHTML = '<option value="">All assigned years</option>' + this.state.years.map(year => {
      const id = Number(year.id || year.academic_year_id);
      const name = year.year_name || year.name || year.year_code || `Year ${id}`;
      return `<option value="${id}">${this.esc(name)}</option>`;
    }).join('');
    if (this.state.currentYearId) select.value = String(this.state.currentYearId);
  },
  async loadTerms() {
    const yearId = Number(document.getElementById('yearFilter')?.value || 0);
    const select = document.getElementById('termFilter');
    if (!select) return;
    select.innerHTML = '<option value="">All terms</option>';
    const query = yearId ? `?academic_year_id=${encodeURIComponent(yearId)}` : '';
    const response = await this.api(`/academic/terms${query}`);
    this.state.terms = this.list(response, ['terms', 'academic_terms']);
    select.innerHTML = '<option value="">All terms</option>' + this.state.terms.map(term => {
      const id = Number(term.id || term.academic_year_term_id || term.term_id);
      const name = term.term_name || term.name || `Term ${term.term_number || id}`;
      return `<option value="${id}">${this.esc(name)}</option>`;
    }).join('');
    if (this.state.currentTermId && (!yearId || yearId === this.state.currentYearId)) {
      select.value = String(this.state.currentTermId);
    }
  },
  async loadPerformance() {
    const loading = document.getElementById('performanceLoading');
    const content = document.getElementById('performanceContent');
    const body = document.getElementById('performanceTableBody');
    const error = document.getElementById('performanceError');
    if (loading) loading.classList.remove('d-none');
    if (content) content.style.display = 'none';
    if (error) error.remove();
    try {
      const params = new URLSearchParams({ report_type: 'performance', class_teacher_only: '1' });
      const yearId = document.getElementById('yearFilter')?.value;
      const termId = document.getElementById('termFilter')?.value;
      if (yearId) params.set('year_id', yearId);
      if (termId) params.set('term_id', termId);
      const response = await this.api(`/academic/reports?${params.toString()}`);
      const payload = response?.data || response || {};
      this.state.rows = Array.isArray(payload.rows) ? payload.rows : [];
      this.state.classes = [...new Map(this.state.rows.filter(row => row.class_id).map(row => [String(row.class_id), {
        id: Number(row.class_id), name: row.class_name || `Class ${row.class_id}`,
      }])).values()];
      this.renderClassFilter();
      this.state.students = this.aggregateStudents(this.state.rows);
      this.render();
      if (content) content.style.display = '';
    } catch (loadError) {
      this.showError(loadError?.message || 'Performance data could not be loaded.');
    } finally {
      if (loading) loading.classList.add('d-none');
    }
  },
  aggregateStudents(rows) {
    const grouped = new Map();
    rows.forEach(row => {
      const id = Number(row.student_id || row.id || 0);
      if (!id) return;
      if (!grouped.has(id)) grouped.set(id, {
        id, name: row.student_name || '', admission_no: row.admission_no || '', class_id: Number(row.class_id || 0),
        class_name: row.class_name || '', scores: [], subjectScores: new Map(),
      });
      const student = grouped.get(id);
      const score = Number(row.score);
      if (Number.isFinite(score)) {
        student.scores.push(score);
        const subject = row.subject_name || 'Other';
        if (!student.subjectScores.has(subject)) student.subjectScores.set(subject, []);
        student.subjectScores.get(subject).push(score);
      }
    });
    const students = [...grouped.values()].map(student => {
      const average = student.scores.length
        ? student.scores.reduce((sum, score) => sum + score, 0) / student.scores.length : null;
      const subjects = [...student.subjectScores.entries()].map(([name, scores]) => ({
        name, average: scores.reduce((sum, score) => sum + score, 0) / scores.length,
      })).sort((a, b) => b.average - a.average);
      return { ...student, average, result_count: student.scores.length,
        best_subject: subjects[0]?.name || '—', support_subject: subjects.length ? subjects[subjects.length - 1].name : '—' };
    }).sort((a, b) => (b.average ?? -1) - (a.average ?? -1) || a.name.localeCompare(b.name));
    let rank = 0;
    let previousAverage = null;
    students.forEach((student, index) => {
      if (student.average !== previousAverage) rank = index + 1;
      student.rank = student.average === null ? '—' : rank;
      previousAverage = student.average;
    });
    return students;
  },
  renderClassFilter() {
    const select = document.getElementById('classFilter');
    if (!select) return;
    const selected = select.value;
    select.innerHTML = '<option value="">All assigned classes</option>' + this.state.classes.map(item =>
      `<option value="${item.id}">${this.esc(item.name)}</option>`).join('');
    if (this.state.classes.some(item => String(item.id) === selected)) select.value = selected;
  },
  filteredStudents() {
    const classId = document.getElementById('classFilter')?.value;
    return this.state.students.filter(student => !classId || String(student.class_id) === classId);
  },
  render() {
    const body = document.getElementById('performanceTableBody');
    if (!body) return;
    const students = this.filteredStudents();
    if (!students.length) {
      body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No assessment results exist for your assigned learners in this scope.</td></tr>';
    } else {
      body.innerHTML = students.map(student => `<tr>
        <td class="small">${this.esc(student.name || '—')}</td>
        <td class="small">${this.esc(student.admission_no || '—')}</td>
        <td class="small">${this.esc(student.class_name || '—')}</td>
        <td class="small text-center">${student.average === null ? '—' : `${student.average.toFixed(1)}%`}</td>
        <td class="small">${this.esc(student.best_subject)}</td>
        <td class="small">${this.esc(student.support_subject)}</td>
        <td class="small text-center">${student.rank}</td>
        <td class="small text-center">${student.result_count}</td>
      </tr>`).join('');
    }
    const scored = students.filter(student => student.average !== null);
    const classAverage = scored.length ? scored.reduce((sum, student) => sum + student.average, 0) / scored.length : 0;
    this.setText('totalStudents', students.length);
    this.setText('aboveAverage', scored.filter(student => student.average > classAverage).length);
    this.setText('classAverage', `${classAverage.toFixed(1)}%`);
  },
  showError(message) {
    const loading = document.getElementById('performanceLoading');
    const content = document.getElementById('performanceContent');
    if (loading) loading.classList.add('d-none');
    if (content) content.style.display = '';
    let error = document.getElementById('performanceError');
    if (!error) {
      error = document.createElement('div');
      error.id = 'performanceError';
      error.className = 'alert alert-danger';
      content?.prepend(error);
    }
    if (error) error.textContent = message;
    const body = document.getElementById('performanceTableBody');
    if (body) body.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Performance data could not be loaded.</td></tr>';
  },
  exportCsv() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) {
      return window.showNotification?.('You do not have permission to export academic results.', 'warning');
    }
    const rows = [['Student', 'Admission number', 'Class', 'Overall average', 'Best learning area', 'Area for support', 'Rank', 'Graded assessments']];
    this.filteredStudents().forEach(student => rows.push([
      student.name, student.admission_no, student.class_name,
      student.average === null ? '' : student.average.toFixed(1), student.best_subject, student.support_subject, student.rank, student.result_count,
    ]));
    const csv = rows.map(row => row.map(value => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
    if (window.KingswayFileLifecycle?.exportText) {
      window.KingswayFileLifecycle.exportText(csv, 'my-students-performance.csv', 'text/csv;charset=utf-8');
    }
  },
};

window.myStudentsPerformanceController = myStudentsPerformanceController;
document.addEventListener('DOMContentLoaded', () => myStudentsPerformanceController.init().catch(error => {
  console.error('My students performance page failed to initialize:', error);
}));
