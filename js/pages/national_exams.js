/** National assessment results, backed by AcademicController's national-exams API. */
const nationalExamsController = {
  results: [],
  years: [],
  students: [],

  esc(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  },

  async api(path, method = 'GET', data = null, params = null) {
    return window.callAPI(path, method, data, params);
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const year = document.getElementById('natExamYearFilter');
    const yearInput = document.getElementById('natEntryYear');
    if (year && yearInput) year.value = yearInput.value;
    const classes = await window.API.academic.listClasses({ limit: 500 });
    const classRows = Array.isArray(classes?.data) ? classes.data : (Array.isArray(classes) ? classes : []);
    const classSelect = document.getElementById('natEntryClass');
    if (classSelect) classSelect.innerHTML = '<option value="">— Select class —</option>' + classRows.map((item) => `<option value="${Number(item.id)}">${this.esc(item.name || item.class_name)}</option>`).join('');
    const subjects = await window.API.academic.listLearningAreas({ limit: 500 });
    const subjectRows = Array.isArray(subjects?.data) ? subjects.data : (Array.isArray(subjects) ? subjects : []);
    const subjectSelect = document.getElementById('natEntrySubject');
    if (subjectSelect) subjectSelect.innerHTML = '<option value="">— Select learning area —</option>' + subjectRows.map((item) => `<option value="${Number(item.id)}">${this.esc(item.name || item.learning_area_name)}</option>`).join('');
    await this.loadAll();
    window.APIRealtime?.register?.('national_exams', { refresh: () => this.loadAll() }, ['academic']);
  },

  async loadAll() {
    const container = document.getElementById('natResultsContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><span class="spinner-border text-success" role="status"></span><span class="visually-hidden">Loading national assessment results</span></div>';
    try {
      const params = {};
      const type = document.getElementById('natExamTypeFilter')?.value;
      const year = document.getElementById('natExamYearFilter')?.value;
      if (type) params.exam_type = type;
      if (year) params.exam_year = year;
      const response = await this.api('/api/academic/national-exams', 'GET', null, params);
      const payload = response?.data ?? response;
      this.results = Array.isArray(payload) ? payload : [];
      const yearSelect = document.getElementById('natExamYearFilter');
      if (yearSelect && !this.years.length) {
        const selectedYear = yearSelect.value;
        this.years = [...new Set(this.results.map((row) => String(row.exam_year || '')).filter(Boolean))].sort((a, b) => b.localeCompare(a));
        yearSelect.innerHTML = '<option value="">All Years</option>' + this.years.map((value) => `<option value="${this.esc(value)}">${this.esc(value)}</option>`).join('');
        yearSelect.value = this.years.includes(selectedYear) ? selectedYear : '';
      }
      this.renderResults();
      this.renderPathways();
    } catch (error) {
      const message = this.esc(error?.message || 'Could not load national assessment results.');
      container.innerHTML = `<div class="alert alert-danger mb-0">${message}</div>`;
      const pathways = document.getElementById('natPathwayContainer');
      if (pathways) pathways.innerHTML = `<div class="alert alert-danger mb-0">${message}</div>`;
    }
  },

  filteredResults() {
    const subjectId = document.getElementById('natExamSubjectFilter')?.value || '';
    return this.results.filter((row) => !subjectId || String(row.learning_area_id) === subjectId);
  },

  renderResults() {
    const container = document.getElementById('natResultsContainer');
    if (!container) return;
    const rows = this.filteredResults();
    if (!rows.length) {
      container.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No national assessment results match these filters.</div>';
      return;
    }
    container.innerHTML = `<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Learner</th><th>Admission No.</th><th>Assessment</th><th>Year</th><th>Learning Area</th><th>Score</th><th>Grade</th><th>Pathway</th></tr></thead><tbody>${rows.map((row) => `<tr><td>${this.esc(row.student_name || '—')}</td><td>${this.esc(row.admission_no || '—')}</td><td>${this.esc(row.exam_type || '—')}</td><td>${this.esc(row.exam_year || '—')}</td><td>${this.esc(row.learning_area_name || '—')}</td><td>${this.esc(row.score ?? '—')}/${this.esc(row.max_score ?? 100)} <span class="text-muted small">(${this.esc(row.percentage ?? '—')}%)</span></td><td>${this.esc(row.cbc_grade || '—')}</td><td>${this.esc(row.pathway || '—')}</td></tr>`).join('')}</tbody></table></div>`;
  },

  renderPathways() {
    const container = document.getElementById('natPathwayContainer');
    if (!container) return;
    const rows = this.results.filter((row) => row.exam_type === 'KJSEA_G9' && row.pathway);
    if (!rows.length) {
      container.innerHTML = '<div class="text-center text-muted py-4">No KJSEA pathway allocations are recorded.</div>';
      return;
    }
    container.innerHTML = `<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Learner</th><th>Admission No.</th><th>Learning Area</th><th>Grade</th><th>Pathway</th></tr></thead><tbody>${rows.map((row) => `<tr><td>${this.esc(row.student_name || '—')}</td><td>${this.esc(row.admission_no || '—')}</td><td>${this.esc(row.learning_area_name || '—')}</td><td>${this.esc(row.cbc_grade || '—')}</td><td>${this.esc(row.pathway)}</td></tr>`).join('')}</tbody></table></div>`;
  },

  showEnterModal() {
    const modal = document.getElementById('natEnterModal');
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  },

  onExamTypeChange() {
    const row = document.getElementById('kjseaPathwayRow');
    row?.classList.toggle('d-none', document.getElementById('natEntryType')?.value !== 'KJSEA_G9');
  },

  async loadEntryStudents() {
    const classId = Number(document.getElementById('natEntryClass')?.value || 0);
    const learningAreaId = Number(document.getElementById('natEntrySubject')?.value || 0);
    const container = document.getElementById('natEntryStudentsContainer');
    if (!container) return;
    if (!classId || !learningAreaId) {
      container.innerHTML = '<div class="text-muted text-center py-3">Select class and learning area to load students.</div>';
      return;
    }
    container.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Loading learners…</div>';
    try {
      const response = await this.api('/api/academic/class-students', 'GET', null, { class_id: classId });
      const payload = response?.data ?? response;
      this.students = Array.isArray(payload) ? payload : [];
      container.innerHTML = this.students.length ? `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Learner</th><th>Admission No.</th><th>Score</th><th>Max Score</th><th>Pathway</th></tr></thead><tbody>${this.students.map((student) => `<tr data-student-id="${Number(student.id)}"><td>${this.esc([student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ') || student.student_name || '—')}</td><td>${this.esc(student.admission_no || '—')}</td><td><input class="form-control form-control-sm nat-score" type="number" min="0" step="0.01" required></td><td><input class="form-control form-control-sm nat-max-score" type="number" min="1" step="0.01" value="100" required></td><td><select class="form-select form-select-sm nat-pathway"><option value="">—</option><option>AST</option><option>STEM</option><option>Social Sciences</option><option>Humanities</option></select></td></tr>`).join('')}</tbody></table></div>` : '<div class="text-muted text-center py-3">No active learners are enrolled in this class.</div>';
    } catch (error) {
      container.innerHTML = `<div class="alert alert-danger">${this.esc(error?.message || 'Could not load learners.')}</div>`;
    }
  },

  async saveResults() {
    const errorBox = document.getElementById('natEntryError');
    const showError = (message) => { if (errorBox) { errorBox.textContent = message; errorBox.classList.remove('d-none'); } };
    const examType = document.getElementById('natEntryType')?.value;
    const examYear = Number(document.getElementById('natEntryYear')?.value);
    const learningAreaId = Number(document.getElementById('natEntrySubject')?.value);
    const learnerRows = Array.from(document.querySelectorAll('#natEntryStudentsContainer tbody tr[data-student-id]'));
    if (learnerRows.some((row) => row.querySelector('.nat-score')?.value === '' || row.querySelector('.nat-max-score')?.value === '')) return showError('Enter a score and maximum score for each learner before saving.');
    const results = learnerRows.map((row) => ({
      student_id: Number(row.dataset.studentId), learning_area_id: learningAreaId,
      score: Number(row.querySelector('.nat-score')?.value),
      max_score: Number(row.querySelector('.nat-max-score')?.value),
      pathway: row.querySelector('.nat-pathway')?.value || null,
    }));
    if (!examType || !examYear || !learningAreaId || !results.length) return showError('Select the assessment, year, class and learning area, then load learners.');
    if (results.some((row) => row.max_score <= 0 || row.score < 0 || row.score > row.max_score)) return showError('Each score must be between zero and its maximum score.');
    if (errorBox) errorBox.classList.add('d-none');
    try {
      await this.api('/api/academic/national-exams', 'POST', { exam_type: examType, exam_year: examYear, results });
      bootstrap.Modal.getInstance(document.getElementById('natEnterModal'))?.hide();
      await this.loadAll();
      window.showNotification?.('National assessment results saved.', 'success');
    } catch (error) {
      showError(error?.message || 'Could not save results.');
    }
  },
};

window.nationalExamsController = nationalExamsController;
window.natExamCtrl = nationalExamsController;
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => nationalExamsController.init().catch(console.error));
} else {
  nationalExamsController.init().catch(console.error);
}
