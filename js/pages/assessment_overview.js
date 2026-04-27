/**
 * Assessment Overview Controller
 * Teacher's central assessment management: create, grade, track completion.
 * APIs: /academic/assessments-list, /academic/formative-assessments,
 *       /academic/formative-assessment-marks, /academic/learning-areas-list,
 *       /academic/classes-list, /academic/assessment-types
 */
const assessmentOverviewCtrl = {

  _assessments: [],
  _students:    [],
  _classes:     [],
  _learningAreas: [],
  _strands:     [],
  _terms:       [],
  _types:       [],
  _currentTermId: null,
  _marksAssessmentId: null,
  _marksMaxMarks: 20,
  _createModal: null,
  _marksModal:  null,

  // ── Init ──────────────────────────────────────────────────────────────────
  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._createModal = new bootstrap.Modal(document.getElementById('aoCreateModal'));
    this._marksModal  = new bootstrap.Modal(document.getElementById('aoMarksModal'));
    await Promise.all([
      this._loadTerms(),
      this._loadClasses(),
      this._loadAssessmentTypes(),
    ]);
    await this.reload();
  },

  // ── Loaders ───────────────────────────────────────────────────────────────
  _loadTerms: async function () {
    try {
      const r = await callAPI('/academic/terms-list', 'GET');
      this._terms = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const current = this._terms.find(t => (t.status || '') === 'current');
      if (current) this._currentTermId = current.id;

      const opts = this._terms.map(t => `<option value="${t.id}" ${t.id==this._currentTermId?'selected':''}>${t.name} ${t.year||''}</option>`).join('');
      ['aoTermFilter','aoTermModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '<option value="">Current Term</option>' + opts;
      });
    } catch (e) { console.warn('Terms failed:', e); }
  },

  _loadClasses: async function () {
    try {
      const r = await callAPI('/academic/classes-list', 'GET');
      this._classes = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const opts = this._classes.map(c => `<option value="${c.id}">${this._esc(c.name||c.class_name||'')}</option>`).join('');
      ['aoClassFilter','aoClass'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = (id === 'aoClassFilter' ? '<option value="">All My Classes</option>' : '<option value="">— Select class —</option>') + opts;
      });
    } catch (e) { console.warn('Classes failed:', e); }
  },

  _loadAssessmentTypes: async function () {
    try {
      const r = await callAPI('/academic/assessment-types', 'GET');
      this._types = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const fmEl  = document.getElementById('aoTypeFormative');
      const smEl  = document.getElementById('aoTypeSummative');
      if (fmEl) fmEl.innerHTML = this._types.filter(t => t.is_formative).map(t => `<option value="${t.id}">${this._esc(t.name)}</option>`).join('');
      if (smEl) smEl.innerHTML = this._types.filter(t => t.is_summative).map(t => `<option value="${t.id}">${this._esc(t.name)}</option>`).join('');
    } catch (e) { console.warn('Types failed:', e); }
  },

  _loadLearningAreas: async function (classId = '') {
    try {
      const qs = classId ? '?class_id=' + classId : '';
      const r  = await callAPI('/academic/learning-areas-list' + qs, 'GET');
      this._learningAreas = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const el = document.getElementById('aoLearningArea');
      if (el) el.innerHTML = '<option value="">— Select learning area —</option>' +
        this._learningAreas.map(la => `<option value="${la.id}">${this._esc(la.name)} (${this._esc(la.code)})</option>`).join('');
    } catch (e) { console.warn('Learning areas failed:', e); }
  },

  _loadStrands: async function (laId) {
    if (!laId) { const el = document.getElementById('aoStrand'); if (el) el.innerHTML = '<option value="">— Select strand —</option>'; return; }
    try {
      const r = await callAPI('/academic/strands?learning_area_id=' + laId, 'GET');
      this._strands = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const el = document.getElementById('aoStrand');
      if (el) el.innerHTML = '<option value="">— Select strand (optional) —</option>' +
        this._strands.map(s => `<option value="${s.id}">${this._esc(s.name)}</option>`).join('');
    } catch (e) { console.warn('Strands failed:', e); }
  },

  reload: async function () {
    const classId = document.getElementById('aoClassFilter')?.value || '';
    const termId  = document.getElementById('aoTermFilter')?.value  || this._currentTermId || '';
    const params  = new URLSearchParams();
    if (classId) params.set('class_id', classId);
    if (termId)  params.set('term_id',  termId);

    try {
      const r = await callAPI('/academic/assessments-list?' + params.toString(), 'GET');
      this._assessments = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._setStats();
      this._renderFormative();
      this._renderSummative();
      this._renderProgress();
      this._renderByLA();
    } catch (e) {
      ['aoFormativeBody','aoSummativeBody'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = `<tr><td colspan="10" class="text-danger text-center py-4">Failed to load assessments.</td></tr>`;
      });
    }
  },

  _setStats: function () {
    const formative  = this._assessments.filter(a => this._isFormative(a));
    const summative  = this._assessments.filter(a => !this._isFormative(a));
    const pending    = this._assessments.filter(a => Number(a.graded_count || 0) < Number(a.total_students || 1)).length;
    const graded     = this._assessments.filter(a => Number(a.graded_count || 0) >= Number(a.total_students || 1) && Number(a.total_students || 0) > 0).length;
    const totalStudents = [...new Set(this._classes.map(c => c.id))].reduce((s, cid) => {
      const c = this._classes.find(x => x.id == cid);
      return s + Number(c?.enrolled || c?.student_count || 0);
    }, 0);

    this._set('aoStatTotal',    this._assessments.length);
    this._set('aoStatPending',  pending);
    this._set('aoStatGraded',   graded);
    this._set('aoStatStudents', totalStudents || '—');
    this._set('aoCaCount',      formative.length);
    this._set('aoExamCount',    summative.length);
  },

  _isFormative: function (a) {
    const typeId = Number(a.assessment_type_id || 0);
    const type   = this._types.find(t => t.id == typeId);
    return type ? !!type.is_formative : true; // default formative
  },

  _renderFormative: function () {
    const tbody = document.getElementById('aoFormativeBody');
    if (!tbody) return;
    const items = this._assessments.filter(a => this._isFormative(a));
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted">No formative assessments created yet for this term.</td></tr>';
      return;
    }
    tbody.innerHTML = items.map(a => this._assessmentRow(a, 10)).join('');
  },

  _renderSummative: function () {
    const tbody = document.getElementById('aoSummativeBody');
    if (!tbody) return;
    const items = this._assessments.filter(a => !this._isFormative(a));
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted">No exams/summative assessments created yet.</td></tr>';
      return;
    }
    tbody.innerHTML = items.map(a => this._assessmentRow(a, 10)).join('');
  },

  _assessmentRow: function (a, cols) {
    const graded  = Number(a.graded_count    || 0);
    const total   = Number(a.total_students  || 0);
    const avg     = Number(a.average_pct     || 0);
    const pctFill = total > 0 ? Math.round((graded / total) * 100) : 0;
    const statCls = { pending_submission:'warning', submitted:'info', pending_approval:'primary', approved:'success' };
    const status  = a.status || 'pending_submission';
    return `<tr>
      <td class="fw-semibold">${this._esc(a.title || '—')}</td>
      <td><span class="badge bg-secondary">${this._esc(a.type_name || a.assessment_type || '—')}</span></td>
      <td class="small">${this._esc(a.learning_area_name || a.subject_name || '—')}</td>
      <td>${this._esc(a.class_name || '—')}</td>
      <td class="small">${this._esc(a.assessment_date || a.date || '—')}</td>
      <td class="text-center">${this._esc(a.max_marks || '—')}</td>
      <td>
        <div class="d-flex align-items-center gap-2">
          <div class="progress flex-grow-1" style="height:6px;">
            <div class="progress-bar ${pctFill===100?'bg-success':pctFill>50?'bg-warning':'bg-danger'}" style="width:${pctFill}%"></div>
          </div>
          <span class="small">${graded}/${total}</span>
        </div>
      </td>
      <td class="text-center fw-bold ${avg>=75?'text-success':avg>=60?'text-primary':avg>=40?'text-warning':'text-danger'}">
        ${avg > 0 ? avg.toFixed(1) + '%' : '—'}
      </td>
      <td><span class="badge bg-${statCls[status]||'secondary'}">${status.replace('_',' ')}</span></td>
      <td class="text-end">
        <button class="btn btn-sm btn-primary me-1" onclick="assessmentOverviewCtrl.showMarksModal(${a.id})">
          <i class="bi bi-pencil"></i> Marks
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="assessmentOverviewCtrl.computeScores(${a.id})">
          <i class="bi bi-calculator"></i>
        </button>
      </td>
    </tr>`;
  },

  _renderProgress: function () {
    const container = document.getElementById('aoProgressCards');
    if (!container) return;
    const byClass = {};
    this._assessments.forEach(a => {
      const key = a.class_name || a.class_id || 'Unknown';
      if (!byClass[key]) byClass[key] = { total: 0, graded: 0, students: Number(a.total_students || 0) };
      byClass[key].total++;
      byClass[key].graded += Number(a.graded_count || 0) >= Number(a.total_students || 1) && Number(a.total_students || 0) > 0 ? 1 : 0;
    });
    if (!Object.keys(byClass).length) {
      container.innerHTML = '<div class="col-12 text-center py-5 text-muted">No data to show.</div>';
      return;
    }
    container.innerHTML = Object.entries(byClass).map(([cls, d]) => {
      const pct = d.total > 0 ? Math.round((d.graded / d.total) * 100) : 0;
      return `<div class="col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h6 class="fw-semibold mb-1">${this._esc(cls)}</h6>
            <div class="d-flex justify-content-between small text-muted mb-2">
              <span>${d.graded}/${d.total} fully graded</span>
              <span>${pct}%</span>
            </div>
            <div class="progress" style="height:8px;">
              <div class="progress-bar ${pct===100?'bg-success':pct>50?'bg-warning':'bg-danger'}" style="width:${pct}%"></div>
            </div>
          </div>
        </div>
      </div>`;
    }).join('');
  },

  _renderByLA: function () {
    const tbody = document.getElementById('aoLABody');
    if (!tbody) return;
    const byLA = {};
    this._assessments.forEach(a => {
      const key = `${a.learning_area_id || a.subject_id}_${a.class_id}`;
      if (!byLA[key]) byLA[key] = { la: a.learning_area_name || a.subject_name || '—', cls: a.class_name || '—', caCount: 0, examCount: 0, caTotal: 0, examTotal: 0 };
      const formative = this._isFormative(a);
      if (formative) { byLA[key].caCount++; byLA[key].caTotal += Number(a.average_pct || 0); }
      else           { byLA[key].examCount++; byLA[key].examTotal += Number(a.average_pct || 0); }
    });
    if (!Object.keys(byLA).length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No data.</td></tr>'; return; }

    tbody.innerHTML = Object.values(byLA).map(r => {
      const caAvg   = r.caCount   > 0 ? (r.caTotal   / r.caCount).toFixed(1)   : '—';
      const examAvg = r.examCount > 0 ? (r.examTotal / r.examCount).toFixed(1) : '—';
      const overall = (r.caCount + r.examCount) > 0
        ? ((r.caTotal * 0.4 + r.examTotal * 0.6) / (r.caCount * 0.4 + r.examCount * 0.6 || 1)).toFixed(1) : '—';
      const grade = this._cbcGrade(Number(overall) || 0);
      return `<tr>
        <td class="fw-semibold">${this._esc(r.la)}</td>
        <td>${this._esc(r.cls)}</td>
        <td class="text-center">${r.caCount}</td>
        <td class="text-center">${r.examCount}</td>
        <td class="text-center ${this._gradeCls(caAvg)}">${caAvg}${caAvg !== '—' ? '%' : ''}</td>
        <td class="text-center ${this._gradeCls(examAvg)}">${examAvg}${examAvg !== '—' ? '%' : ''}</td>
        <td class="text-center"><span class="badge bg-${this._gradeColor(grade)} fs-6">${grade}</span></td>
      </tr>`;
    }).join('');
  },

  // ── Create Assessment ─────────────────────────────────────────────────────
  showCreateModal: function () {
    ['aoTitle','aoDate','aoInstructions'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    document.getElementById('aoMaxMarks').value = 20;
    const err = document.getElementById('aoCreateError');
    if (err) { err.classList.add('d-none'); err.textContent = ''; }
    this._loadLearningAreas();
    this._createModal.show();
  },

  onClassChange: function () {
    const classId = document.getElementById('aoClass')?.value || '';
    this._loadLearningAreas(classId);
  },

  onLAChange: function () {
    const laId = document.getElementById('aoLearningArea')?.value || '';
    this._loadStrands(laId);
  },

  saveAssessment: async function () {
    const title   = document.getElementById('aoTitle')?.value.trim();
    const type    = document.getElementById('aoType')?.value;
    const cls     = document.getElementById('aoClass')?.value;
    const la      = document.getElementById('aoLearningArea')?.value;
    const strand  = document.getElementById('aoStrand')?.value;
    const date    = document.getElementById('aoDate')?.value;
    const max     = document.getElementById('aoMaxMarks')?.value;
    const termId  = document.getElementById('aoTermModal')?.value || this._currentTermId;
    const notes   = document.getElementById('aoInstructions')?.value.trim();
    const errEl   = document.getElementById('aoCreateError');

    if (!title || !type || !cls || !la || !date || !max) {
      if (errEl) { errEl.textContent = 'All required fields must be filled.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      await callAPI('/academic/formative-assessments', 'POST', {
        title, assessment_type_id: type, class_id: cls, subject_id: la,
        strand_id: strand || null, assessment_date: date, max_marks: max,
        term_id: termId, notes,
      });
      showNotification('Assessment created.', 'success');
      this._createModal.hide();
      await this.reload();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Create failed.'; errEl.classList.remove('d-none'); }
    }
  },

  // ── Marks Entry ───────────────────────────────────────────────────────────
  showMarksModal: async function (assessmentId) {
    this._marksAssessmentId = assessmentId;
    const a = this._assessments.find(x => x.id == assessmentId);
    if (!a) return;

    this._marksMaxMarks = Number(a.max_marks || 20);
    this._set('aoMarksTitle',   `Enter Marks: ${a.title || ''}`);
    this._set('aoMarksSubtitle',`${a.class_name || ''} · ${a.learning_area_name || ''} · Max: ${this._marksMaxMarks}`);
    this._set('aoMrkMax', '/ ' + this._marksMaxMarks);

    const tbody = document.getElementById('aoMarksBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';

    this._marksModal.show();

    try {
      const [rStudents, rMarks] = await Promise.allSettled([
        callAPI('/academic/class-students?class_id=' + a.class_id, 'GET'),
        callAPI('/academic/formative-assessment-marks?assessment_id=' + assessmentId, 'GET'),
      ]);

      const students = this._extract(rStudents);
      const marks    = this._extract(rMarks);
      const markMap  = {};
      marks.forEach(m => { markMap[m.student_id] = m; });

      this._students = students;
      this._set('aoMrkTotal', students.length);
      this._renderMarksGrid(students, markMap);
    } catch (e) {
      if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-3">Failed: ${this._esc(e.message)}</td></tr>`;
    }
  },

  _renderMarksGrid: function (students, markMap) {
    const tbody = document.getElementById('aoMarksBody');
    if (!tbody) return;
    if (!students.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No students enrolled.</td></tr>'; return; }

    tbody.innerHTML = students.map((s, i) => {
      const existing = markMap[s.id] || markMap[s.student_id];
      const score    = existing?.marks_obtained ?? existing?.score ?? '';
      const cbc      = existing?.grade ?? existing?.cbc_grade ?? '';
      const pct      = score !== '' && this._marksMaxMarks > 0 ? ((Number(score)/this._marksMaxMarks)*100).toFixed(1) : '';
      return `<tr id="aoMrkRow_${s.id}">
        <td class="text-muted small">${i+1}</td>
        <td class="fw-semibold">${this._esc((s.first_name||'') + ' ' + (s.last_name||''))}</td>
        <td class="text-muted small">${this._esc(s.admission_no || '')}</td>
        <td>
          <input type="number" class="form-control form-control-sm ao-score-input" min="0"
                 max="${this._marksMaxMarks}" step="0.5" value="${score}"
                 data-student-id="${s.id}"
                 oninput="assessmentOverviewCtrl.onScoreInput(this, ${this._marksMaxMarks})">
        </td>
        <td class="text-center small fw-semibold" id="aoMrkPct_${s.id}">${pct ? pct + '%' : '—'}</td>
        <td class="text-center" id="aoMrkGrade_${s.id}">
          ${cbc ? `<span class="badge bg-${this._gradeColor(cbc)} fs-6">${cbc}</span>` : '—'}
        </td>
        <td>
          <input type="text" class="form-control form-control-sm ao-remark-input" placeholder="Optional remark"
                 data-student-id="${s.id}" value="${this._esc(existing?.remarks || '')}">
        </td>
      </tr>`;
    }).join('');

    this._updateMarksSummary();
  },

  onScoreInput: function (input, maxMarks) {
    const studentId = input.dataset.studentId;
    const score     = Number(input.value);
    const pct       = maxMarks > 0 ? (score / maxMarks) * 100 : 0;
    const grade     = this._cbcGrade(pct);
    const color     = this._gradeColor(grade);

    const pctEl   = document.getElementById('aoMrkPct_' + studentId);
    const gradeEl = document.getElementById('aoMrkGrade_' + studentId);
    if (pctEl)   pctEl.textContent = input.value ? pct.toFixed(1) + '%' : '—';
    if (gradeEl) gradeEl.innerHTML = input.value
      ? `<span class="badge bg-${color} fs-6">${grade}</span>` : '—';

    this._updateMarksSummary();
  },

  _updateMarksSummary: function () {
    const inputs  = document.querySelectorAll('.ao-score-input');
    const entered = [...inputs].filter(i => i.value !== '');
    const scores  = entered.map(i => (Number(i.value) / this._marksMaxMarks) * 100);
    const avg     = scores.length ? (scores.reduce((s,v) => s+v, 0) / scores.length).toFixed(1) : '—';
    const grades  = scores.map(s => this._cbcGrade(s));

    this._set('aoMrkEntered', entered.length);
    this._set('aoMrkAvg',     avg !== '—' ? avg + '%' : '—');
    this._set('aoMrkEE', grades.filter(g => g === 'EE').length);
    this._set('aoMrkME', grades.filter(g => g === 'ME').length);
    this._set('aoMrkAE', grades.filter(g => g === 'AE').length);
    this._set('aoMrkBE', grades.filter(g => g === 'BE').length);
  },

  saveMarks: async function () {
    const assessmentId = this._marksAssessmentId;
    const inputs  = document.querySelectorAll('.ao-score-input');
    const remarks = document.querySelectorAll('.ao-remark-input');
    const errEl   = document.getElementById('aoMarksError');

    const marks = [...inputs].filter(i => i.value !== '').map(i => {
      const remarkEl = document.querySelector(`.ao-remark-input[data-student-id="${i.dataset.studentId}"]`);
      return {
        student_id:    parseInt(i.dataset.studentId),
        marks_obtained:Number(i.value),
        remarks:       remarkEl?.value || '',
      };
    });

    if (!marks.length) {
      if (errEl) { errEl.textContent = 'No marks entered.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    try {
      await callAPI('/academic/formative-assessment-marks', 'POST', { assessment_id: assessmentId, marks });
      showNotification(`${marks.length} marks saved.`, 'success');
      this._marksModal.hide();
      await this.reload();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Save failed.'; errEl.classList.remove('d-none'); }
    }
  },

  computeScores: async function (assessmentId) {
    try {
      await callAPI('/academic/compute-term-scores', 'POST', { assessment_id: assessmentId });
      showNotification('Term scores recomputed.', 'success');
    } catch (e) {
      showNotification(e.message || 'Computation failed.', 'warning');
    }
  },

  // ── Utilities ─────────────────────────────────────────────────────────────
  _cbcGrade: function (pct) {
    if (pct >= 75) return 'EE';
    if (pct >= 60) return 'ME';
    if (pct >= 40) return 'AE';
    return 'BE';
  },
  _gradeColor: function (g) {
    return { EE:'success', ME:'primary', AE:'warning', BE:'danger' }[g] || 'secondary';
  },
  _gradeCls: function (v) {
    const n = Number(v);
    if (isNaN(n)) return '';
    return n >= 75 ? 'text-success fw-bold' : n >= 60 ? 'text-primary' : n >= 40 ? 'text-warning' : 'text-danger';
  },
  _extract: function (settled) {
    if (settled.status !== 'fulfilled') return [];
    const r = settled.value;
    return Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
  },
  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => assessmentOverviewCtrl.init());
