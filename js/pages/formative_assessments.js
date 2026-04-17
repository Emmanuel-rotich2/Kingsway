/**
 * Formative Assessments Controller
 * CBC Classroom Assessments: Assignments, Homework, Quizzes, Projects, Oral, Portfolio, Observation
 * API: /api/academic/formative-assessments
 */

const fAssCtrl = {

  _terms:    [],
  _classes:  [],
  _subjects: [],
  _types:    [],
  _assessments: [],

  // ── INIT ──────────────────────────────────────────────────────────────

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([
      this._loadTerms(),
      this._loadClasses(),
      this._loadSubjects(),
      this._loadTypes(),
    ]);
    this.loadAll();
    this._loadSummary();

    // Wire summary tab on first show
    const summaryTab = document.querySelector('[data-bs-target="#faTabSummary"]');
    if (summaryTab) {
      summaryTab.addEventListener('shown.bs.tab', () => this._loadSummary());
    }
    // Wire marks tab
    const marksTab = document.querySelector('[data-bs-target="#faTabMarks"]');
    if (marksTab) {
      marksTab.addEventListener('shown.bs.tab', () => this._populateMarksDropdown());
    }
  },

  // ── DROPDOWN LOADERS ──────────────────────────────────────────────────

  _loadTerms: async function () {
    try {
      const r = await callAPI('/academic/terms-list', 'GET');
      this._terms = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      ['faTermFilter', 'faTerm'].forEach(id => {
        const sel = document.getElementById(id);
        if (!sel) return;
        const placeholder = sel.options[0].textContent;
        sel.innerHTML = `<option value="">${placeholder}</option>`;
        this._terms.forEach(t => {
          sel.insertAdjacentHTML('beforeend', `<option value="${t.id}">${this._esc(t.name)}</option>`);
        });
      });
    } catch (e) { console.warn('Terms load failed:', e); }
  },

  _loadClasses: async function () {
    try {
      const r = await callAPI('/academic/classes-list', 'GET');
      const list = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      ['faClassFilter', 'faClass'].forEach(id => {
        const sel = document.getElementById(id);
        if (!sel) return;
        const placeholder = sel.options[0].textContent;
        sel.innerHTML = `<option value="">${placeholder}</option>`;
        list.forEach(c => {
          sel.insertAdjacentHTML('beforeend', `<option value="${c.id}">${this._esc(c.name)}</option>`);
        });
      });
    } catch (e) { console.warn('Classes load failed:', e); }
  },

  _loadSubjects: async function () {
    try {
      const r = await callAPI('/academic/learning-areas-list', 'GET');
      this._subjects = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      ['faSubjectFilter', 'faSubject'].forEach(id => {
        const sel = document.getElementById(id);
        if (!sel) return;
        const placeholder = sel.options[0].textContent;
        sel.innerHTML = `<option value="">${placeholder}</option>`;
        this._subjects.forEach(s => {
          sel.insertAdjacentHTML('beforeend', `<option value="${s.id}">${this._esc(s.name)}</option>`);
        });
      });
    } catch (e) { console.warn('Subjects load failed:', e); }
  },

  _loadTypes: async function () {
    try {
      const r = await callAPI('/academic/assessment-types?filter=formative', 'GET');
      this._types = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      ['faTypeFilter', 'faType'].forEach(id => {
        const sel = document.getElementById(id);
        if (!sel) return;
        const placeholder = sel.options[0].textContent;
        sel.innerHTML = `<option value="">${placeholder}</option>`;
        this._types.forEach(t => {
          sel.insertAdjacentHTML('beforeend', `<option value="${t.id}">${this._esc(t.name)}</option>`);
        });
      });
    } catch (e) { console.warn('Types load failed:', e); }
  },

  // ── ASSESSMENTS LIST ──────────────────────────────────────────────────

  loadAll: async function () {
    const container = document.getElementById('faListContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

    try {
      const params = new URLSearchParams();
      const term    = document.getElementById('faTermFilter')?.value;
      const cls     = document.getElementById('faClassFilter')?.value;
      const subject = document.getElementById('faSubjectFilter')?.value;
      const type    = document.getElementById('faTypeFilter')?.value;
      if (term)    params.set('term_id',    term);
      if (cls)     params.set('class_id',   cls);
      if (subject) params.set('subject_id', subject);
      if (type)    params.set('type_id',    type);

      const qs = params.toString() ? '?' + params : '';
      const r  = await callAPI('/academic/formative-assessments' + qs, 'GET');
      this._assessments = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!this._assessments.length) {
        container.innerHTML = '<div class="alert alert-info text-center">No formative assessments found. Use <strong>New Assessment</strong> to create one.</div>';
        return;
      }

      const rows = this._assessments.map(a => {
        const gc = this._gradeColor(a.class_average_pct);
        return `<tr>
          <td class="fw-semibold">${this._esc(a.title)}</td>
          <td><span class="fa-type">${this._esc(a.type_name || '—')}</span></td>
          <td>${this._esc(a.term_name || '—')}</td>
          <td>${this._esc(a.class_name || '—')}</td>
          <td>${this._esc(a.subject_name || a.learning_area_name || '—')}</td>
          <td class="text-center">${a.max_marks ?? '—'}</td>
          <td class="text-center">${a.submitted_count ?? 0} / ${a.total_students ?? '—'}</td>
          <td class="text-center">${a.class_average_pct != null ? `<span class="badge bg-${gc}">${a.class_average_pct}%</span>` : '—'}</td>
          <td>${a.assessment_date || '—'}</td>
          <td>
            <button class="btn btn-sm btn-outline-primary me-1" onclick="fAssCtrl._openMarksTab(${a.id})" title="Enter Marks">
              <i class="bi bi-pencil-square"></i>
            </button>
          </td>
        </tr>`;
      }).join('');

      container.innerHTML = `<div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light"><tr>
            <th>Title</th><th>Type</th><th>Term</th><th>Class</th><th>Learning Area</th>
            <th class="text-center">Max</th><th class="text-center">Submitted</th>
            <th class="text-center">Class Avg</th><th>Date</th><th></th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table></div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load assessments: ${this._esc(e.message)}</div>`;
    }
  },

  _gradeColor: function (pct) {
    if (pct == null) return 'secondary';
    if (pct >= 75)   return 'success';
    if (pct >= 60)   return 'primary';
    if (pct >= 40)   return 'warning';
    return 'danger';
  },

  // ── CREATE ASSESSMENT ─────────────────────────────────────────────────

  showCreateModal: function () {
    ['faTitle','faMaxMarks'].forEach(id => {
      const el = document.getElementById(id);
      if (el) { el.value = id === 'faMaxMarks' ? '100' : ''; }
    });
    ['faType','faTerm','faClass','faSubject'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    const dateEl = document.getElementById('faDate');
    if (dateEl) dateEl.value = new Date().toISOString().split('T')[0];
    document.getElementById('faCreateError')?.classList.add('d-none');
    new bootstrap.Modal(document.getElementById('faCreateModal')).show();
  },

  saveAssessment: async function () {
    const errEl = document.getElementById('faCreateError');
    errEl?.classList.add('d-none');

    const title    = document.getElementById('faTitle')?.value.trim();
    const typeId   = document.getElementById('faType')?.value;
    const termId   = document.getElementById('faTerm')?.value;
    const classId  = document.getElementById('faClass')?.value;
    const subjectId= document.getElementById('faSubject')?.value;
    const maxMarks = document.getElementById('faMaxMarks')?.value;
    const date     = document.getElementById('faDate')?.value;

    if (!title || !typeId || !termId || !classId || !subjectId || !maxMarks || !date) {
      if (errEl) { errEl.textContent = 'All fields are required.'; errEl.classList.remove('d-none'); }
      return;
    }

    const payload = {
      title,
      assessment_type_id: parseInt(typeId),
      term_id:            parseInt(termId),
      class_id:           parseInt(classId),
      subject_id:         parseInt(subjectId),
      max_marks:          parseFloat(maxMarks),
      assessment_date:    date,
    };

    try {
      await callAPI('/academic/formative-assessments', 'POST', payload);
      bootstrap.Modal.getInstance(document.getElementById('faCreateModal')).hide();
      showNotification('Assessment created successfully', 'success');
      this.loadAll();
      this._populateMarksDropdown();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to create assessment'; errEl.classList.remove('d-none'); }
    }
  },

  // ── MARKS ENTRY ───────────────────────────────────────────────────────

  _openMarksTab: function (assessmentId) {
    // Switch to Enter Marks tab and pre-select the assessment
    const tabBtn = document.querySelector('[data-bs-target="#faTabMarks"]');
    if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();

    const sel = document.getElementById('marksAssessmentSelect');
    if (sel) {
      // Wait for tab to show and dropdown to be populated
      const trySelect = () => {
        if (sel.querySelector(`option[value="${assessmentId}"]`)) {
          sel.value = assessmentId;
        } else {
          this._populateMarksDropdown().then(() => { sel.value = assessmentId; });
        }
      };
      setTimeout(trySelect, 100);
    }
  },

  _populateMarksDropdown: async function () {
    const sel = document.getElementById('marksAssessmentSelect');
    if (!sel) return;
    try {
      const r = await callAPI('/academic/formative-assessments', 'GET');
      const list = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      sel.innerHTML = '<option value="">— Select a formative assessment —</option>';
      list.forEach(a => {
        sel.insertAdjacentHTML('beforeend',
          `<option value="${a.id}">${this._esc(a.title)} — ${this._esc(a.class_name || '')} — ${this._esc(a.term_name || '')} (Max: ${a.max_marks})</option>`
        );
      });
    } catch (e) { console.warn('Marks dropdown failed:', e); }
  },

  loadMarksEntry: async function () {
    const container = document.getElementById('faMarksContainer');
    if (!container) return;
    const assessmentId = document.getElementById('marksAssessmentSelect')?.value;
    if (!assessmentId) {
      container.innerHTML = '<div class="alert alert-warning">Please select an assessment first.</div>';
      return;
    }

    // Find assessment in cached list to get max_marks
    let assessment = this._assessments.find(a => a.id == assessmentId);
    if (!assessment) {
      // Fetch fresh if not in cache
      try {
        const r2 = await callAPI('/academic/formative-assessments?id=' + assessmentId, 'GET');
        const list = Array.isArray(r2?.data) ? r2.data : [];
        assessment = list.find(a => a.id == assessmentId) || { max_marks: 100 };
      } catch (_) { assessment = { max_marks: 100 }; }
    }

    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

    try {
      // Load existing marks for this assessment
      const r = await callAPI('/academic/formative-assessment-marks?assessment_id=' + assessmentId, 'GET');
      const marks = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!marks.length) {
        container.innerHTML = '<div class="alert alert-info">No students found for this assessment. Ensure the class has enrolled students.</div>';
        return;
      }

      const maxMarks = assessment.max_marks ?? 100;

      const rows = marks.map(m => {
        const pct    = m.percentage ?? '';
        const grade  = m.cbc_grade  ?? '';
        const badgeCls = grade ? `grade-${grade}` : '';
        return `<tr data-student="${m.student_id}">
          <td class="fw-semibold">${this._esc(m.first_name + ' ' + m.last_name)}</td>
          <td>${this._esc(m.admission_no || '—')}</td>
          <td class="text-center" style="width:120px;">
            <input type="number" class="form-control form-control-sm text-center marks-input"
              data-student="${m.student_id}" value="${m.score ?? ''}"
              min="0" max="${maxMarks}" step="0.5"
              onchange="fAssCtrl._onMarkChange(this, ${maxMarks})"
              placeholder="/${maxMarks}">
          </td>
          <td class="text-center pct-cell">${pct ? pct + '%' : '—'}</td>
          <td class="text-center grade-cell">${grade ? `<span class="${badgeCls}">${grade}</span>` : '—'}</td>
          <td><input type="text" class="form-control form-control-sm remarks-input" data-student="${m.student_id}"
            value="${this._esc(m.remarks || '')}" placeholder="Optional remarks" maxlength="255"></td>
        </tr>`;
      }).join('');

      container.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <strong>${marks.length} students</strong>
            <span class="text-muted ms-2">Max marks: ${maxMarks}</span>
          </div>
          <button class="btn btn-success" onclick="fAssCtrl.saveAllMarks(${assessmentId}, ${maxMarks})">
            <i class="bi bi-floppy me-1"></i> Save All Marks
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle" id="marksTable">
            <thead class="table-light"><tr>
              <th>Student</th><th>Adm No</th>
              <th class="text-center">Score (/${maxMarks})</th>
              <th class="text-center">%</th>
              <th class="text-center">Grade</th>
              <th>Remarks</th>
            </tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
        <div class="mt-3">
          <button class="btn btn-success" onclick="fAssCtrl.saveAllMarks(${assessmentId}, ${maxMarks})">
            <i class="bi bi-floppy me-1"></i> Save All Marks
          </button>
        </div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load students: ${this._esc(e.message)}</div>`;
    }
  },

  _onMarkChange: function (input, maxMarks) {
    const row = input.closest('tr');
    const score = parseFloat(input.value);
    if (isNaN(score) || score < 0 || score > maxMarks) {
      input.classList.add('is-invalid');
      return;
    }
    input.classList.remove('is-invalid');
    const pct   = Math.round((score / maxMarks) * 100 * 100) / 100;
    const grade = pct >= 75 ? 'EE' : pct >= 60 ? 'ME' : pct >= 40 ? 'AE' : 'BE';
    const gc    = pct >= 75 ? 'EE' : pct >= 60 ? 'ME' : pct >= 40 ? 'AE' : 'BE';

    const pctCell   = row.querySelector('.pct-cell');
    const gradeCell = row.querySelector('.grade-cell');
    if (pctCell)   pctCell.textContent   = pct + '%';
    if (gradeCell) gradeCell.innerHTML   = `<span class="grade-${gc}">${grade}</span>`;
  },

  saveAllMarks: async function (assessmentId, maxMarks) {
    const marks = [];
    let hasError = false;

    document.querySelectorAll('#marksTable .marks-input').forEach(input => {
      const studentId = parseInt(input.dataset.student);
      const scoreVal  = input.value.trim();
      if (!scoreVal) return; // skip empty — not entered yet

      const score = parseFloat(scoreVal);
      if (isNaN(score) || score < 0 || score > maxMarks) {
        input.classList.add('is-invalid');
        hasError = true;
        return;
      }
      input.classList.remove('is-invalid');

      const remarksInput = document.querySelector(`.remarks-input[data-student="${studentId}"]`);
      marks.push({
        student_id: studentId,
        score,
        max_score:  maxMarks,
        remarks:    remarksInput?.value.trim() || null,
      });
    });

    if (hasError) {
      showNotification('Please fix invalid scores before saving', 'warning');
      return;
    }
    if (!marks.length) {
      showNotification('No scores entered yet', 'info');
      return;
    }

    try {
      await callAPI('/academic/formative-assessment-marks', 'POST', { assessment_id: assessmentId, marks });
      showNotification(`Saved ${marks.length} marks successfully`, 'success');
      this.loadAll(); // refresh class average in list
    } catch (e) {
      showNotification('Save failed: ' + (e.message || 'Unknown error'), 'danger');
    }
  },

  // ── FORMATIVE SUMMARY ─────────────────────────────────────────────────

  _loadSummary: async function () {
    const container = document.getElementById('faSummaryContainer');
    if (!container) return;

    const term = document.getElementById('faTermFilter')?.value;
    const cls  = document.getElementById('faClassFilter')?.value;
    if (!term || !cls) {
      container.innerHTML = '<div class="alert alert-info text-center"><i class="bi bi-funnel me-1"></i>Select a <strong>Term</strong> and <strong>Class</strong> in the filters above to view the formative summary.</div>';
      return;
    }

    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    try {
      const params = new URLSearchParams();
      params.set('term_id',  term);
      params.set('class_id', cls);
      const subject = document.getElementById('faSubjectFilter')?.value;
      if (subject) params.set('subject_id', subject);
      const qs = '?' + params;

      const r    = await callAPI('/academic/formative-summary' + qs, 'GET');
      const data = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!data.length) {
        container.innerHTML = '<div class="alert alert-info text-center">No formative data yet. Enter marks in the <strong>Enter Marks</strong> tab.</div>';
        return;
      }

      // Group by student
      const studentMap = {};
      const subjects   = new Set();
      data.forEach(row => {
        const key = row.student_id;
        if (!studentMap[key]) {
          studentMap[key] = { name: row.student_name, admission_no: row.admission_no, averages: {} };
        }
        const subjectName = row.subject_name || row.learning_area_name || '—';
        subjects.add(subjectName);
        studentMap[key].averages[subjectName] = {
          avg_pct:    row.avg_percentage ?? row.formative_avg_pct,
          cbc_grade:  row.overall_cbc_grade ?? row.formative_grade,
          count:      row.assessment_count,
        };
      });

      const subjectList = Array.from(subjects).sort();
      const headers = subjectList.map(s => `<th class="text-center" style="min-width:100px;">${this._esc(s)}</th>`).join('');

      const rows = Object.values(studentMap).map(student => {
        const cells = subjectList.map(s => {
          const entry = student.averages[s];
          if (!entry) return '<td class="text-center text-muted">—</td>';
          const gc = entry.cbc_grade || '';
          return `<td class="text-center">
            <div>${entry.avg_pct != null ? entry.avg_pct + '%' : '—'}</div>
            ${gc ? `<span class="grade-${gc}">${gc}</span>` : ''}
            ${entry.count ? `<small class="text-muted d-block">${entry.count} tasks</small>` : ''}
          </td>`;
        }).join('');
        return `<tr>
          <td class="fw-semibold">${this._esc(student.name || '—')}</td>
          <td>${this._esc(student.admission_no || '—')}</td>
          ${cells}
        </tr>`;
      }).join('');

      container.innerHTML = `<div class="table-responsive">
        <table class="table table-sm table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Student</th><th>Adm No</th>
              ${headers}
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
      <p class="text-muted small mt-2">
        <i class="bi bi-info-circle me-1"></i>
        Percentages shown are averages across all formative tasks per learning area.
        <span class="grade-EE">EE</span> ≥75% &nbsp;
        <span class="grade-ME">ME</span> 60–74% &nbsp;
        <span class="grade-AE">AE</span> 40–59% &nbsp;
        <span class="grade-BE">BE</span> 0–39%
      </p>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load summary: ${this._esc(e.message)}</div>`;
    }
  },

  // ── UTILS ──────────────────────────────────────────────────────────────

  _esc: function (s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  },
};
