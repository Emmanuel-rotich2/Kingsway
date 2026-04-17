/**
 * National Exams Controller
 * CBC Kenya: KNEC G3, KPSEA G6, KJSEA G9 + Pathway allocation
 * API: /api/academic/national-exams
 */

const natExamCtrl = {

  _subjects: [],
  _classes:  [],

  // ── INIT ──────────────────────────────────────────────────────────────

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([
      this._loadSubjects(),
      this._loadClasses(),
      this._buildYearFilter(),
    ]);
    this.loadAll();

    // Wire pathway tab
    document.querySelector('[data-bs-target="#natTabKJSEA"]')
      ?.addEventListener('shown.bs.tab', () => this._loadPathways());
  },

  _buildYearFilter: function () {
    const sel = document.getElementById('natExamYearFilter');
    if (!sel) return;
    const current = new Date().getFullYear();
    for (let y = current; y >= 2020; y--) {
      sel.insertAdjacentHTML('beforeend', `<option value="${y}">${y}</option>`);
    }
  },

  _loadSubjects: async function () {
    try {
      const r = await callAPI('/academic/learning-areas-list', 'GET');
      this._subjects = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      ['natExamSubjectFilter', 'natEntrySubject'].forEach(id => {
        const sel = document.getElementById(id);
        if (!sel) return;
        const placeholder = sel.options[0].textContent;
        sel.innerHTML = `<option value="">${placeholder}</option>`;
        this._subjects.forEach(s =>
          sel.insertAdjacentHTML('beforeend', `<option value="${s.id}">${this._esc(s.name)}</option>`)
        );
      });
    } catch (e) { console.warn('Subjects load failed:', e); }
  },

  _loadClasses: async function () {
    try {
      const r    = await callAPI('/academic/classes-list', 'GET');
      this._classes = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel  = document.getElementById('natEntryClass');
      if (!sel) return;
      sel.innerHTML = '<option value="">— Select class —</option>';
      this._classes.forEach(c =>
        sel.insertAdjacentHTML('beforeend', `<option value="${c.id}">${this._esc(c.name)}</option>`)
      );
    } catch (e) { console.warn('Classes load failed:', e); }
  },

  // ── RESULTS LIST ──────────────────────────────────────────────────────

  loadAll: async function () {
    const container = document.getElementById('natResultsContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success"></div></div>';

    try {
      const params = new URLSearchParams();
      const type    = document.getElementById('natExamTypeFilter')?.value;
      const year    = document.getElementById('natExamYearFilter')?.value;
      const subject = document.getElementById('natExamSubjectFilter')?.value;
      if (type)    params.set('exam_type',       type);
      if (year)    params.set('exam_year',        year);
      if (subject) params.set('learning_area_id', subject);
      const qs = params.toString() ? '?' + params : '';

      const r    = await callAPI('/academic/national-exams' + qs, 'GET');
      const list = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!list.length) {
        container.innerHTML = '<div class="alert alert-info text-center">No national exam results found. Use <strong>Enter Results</strong> to add records.</div>';
        return;
      }

      const rows = list.map(e => {
        const pathway = e.pathway ? `<span class="pathway-${e.pathway}">${e.pathway.replace('_', ' ')}</span>` : '—';
        const grade   = e.cbc_grade ? `<span class="badge bg-${this._gradeColor(e.cbc_grade)}">${e.cbc_grade}</span>` : '—';
        return `<tr>
          <td class="fw-semibold">${this._esc(e.student_name || '—')}</td>
          <td>${this._esc(e.admission_no || '—')}</td>
          <td><span class="exam-type-badge">${this._esc(e.exam_type_label || e.exam_type)}</span></td>
          <td class="text-center">${e.exam_year}</td>
          <td>${this._esc(e.subject_name || e.learning_area_name || '—')}</td>
          <td class="text-center">${e.score != null ? e.score : '—'} ${e.max_score ? `/ ${e.max_score}` : ''}</td>
          <td class="text-center">${e.percentage != null ? e.percentage + '%' : '—'}</td>
          <td class="text-center">${grade}</td>
          <td class="text-center">${e.raw_grade || '—'}</td>
          <td class="text-center">${e.points != null ? e.points : '—'}</td>
          <td class="text-center">${pathway}</td>
        </tr>`;
      }).join('');

      container.innerHTML = `<div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light"><tr>
            <th>Student</th><th>Adm No</th><th>Exam</th><th class="text-center">Year</th>
            <th>Learning Area</th><th class="text-center">Score</th>
            <th class="text-center">%</th><th class="text-center">CBC Grade</th>
            <th class="text-center">Raw Grade</th><th class="text-center">Points</th>
            <th class="text-center">Pathway</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table></div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load results: ${this._esc(e.message)}</div>`;
    }
  },

  _gradeColor: function (grade) {
    return { EE: 'success', ME: 'primary', AE: 'warning', BE: 'danger' }[grade] || 'secondary';
  },

  // ── KJSEA PATHWAYS ────────────────────────────────────────────────────

  _loadPathways: async function () {
    const container = document.getElementById('natPathwayContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success"></div></div>';
    try {
      const r    = await callAPI('/academic/national-exams?exam_type=KJSEA_G9', 'GET');
      const all  = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      // Unique students with their pathways (aggregate by student, last pathway wins)
      const studentMap = {};
      all.forEach(e => {
        if (!studentMap[e.student_id]) {
          studentMap[e.student_id] = {
            name: e.student_name, admission_no: e.admission_no,
            year: e.exam_year, pathway: e.pathway,
          };
        } else if (e.pathway) {
          studentMap[e.student_id].pathway = e.pathway;
        }
      });

      const students = Object.values(studentMap);
      if (!students.length) {
        container.innerHTML = '<div class="alert alert-info text-center">No KJSEA Grade 9 pathway data yet.</div>';
        return;
      }

      const pathways   = ['AST','STEM','Social_Sciences','Humanities'];
      const grouped    = {};
      pathways.forEach(p => grouped[p] = []);
      const unallocated = [];
      students.forEach(s => {
        if (s.pathway && grouped[s.pathway]) grouped[s.pathway].push(s);
        else unallocated.push(s);
      });

      const pathwayColors = {
        AST: 'purple', STEM: 'primary', Social_Sciences: 'success', Humanities: 'danger'
      };
      const pathwayLabels = {
        AST: 'Arts, Sports & Technical', STEM: 'STEM',
        Social_Sciences: 'Social Sciences', Humanities: 'Humanities'
      };

      let html = '<div class="row g-3">';
      pathways.forEach(p => {
        const students = grouped[p];
        const color    = pathwayColors[p] || 'secondary';
        html += `<div class="col-md-3">
          <div class="card border-${color} h-100">
            <div class="card-header bg-${color} text-white py-2">
              <strong>${pathwayLabels[p]}</strong>
              <span class="badge bg-white text-${color} ms-2">${students.length}</span>
            </div>
            <div class="card-body p-2">`;
        if (students.length) {
          students.forEach(s => {
            html += `<div class="d-flex align-items-center py-1 border-bottom">
              <i class="bi bi-person-circle me-2 text-muted"></i>
              <div>
                <div class="fw-semibold" style="font-size:.85rem;">${this._esc(s.name)}</div>
                <small class="text-muted">${this._esc(s.admission_no || '')} · ${s.year || ''}</small>
              </div>
            </div>`;
          });
        } else {
          html += `<p class="text-muted text-center small py-3">No students allocated</p>`;
        }
        html += `</div></div></div>`;
      });
      html += '</div>';

      if (unallocated.length) {
        html += `<div class="alert alert-warning mt-3"><i class="bi bi-exclamation-triangle me-1"></i>
          ${unallocated.length} student(s) have KJSEA results but no pathway allocated yet.
        </div>`;
      }

      container.innerHTML = html;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load pathways: ${this._esc(e.message)}</div>`;
    }
  },

  // ── ENTER RESULTS ─────────────────────────────────────────────────────

  showEnterModal: function () {
    document.getElementById('natEntryType').value    = '';
    document.getElementById('natEntryYear').value    = new Date().getFullYear();
    document.getElementById('natEntryClass').value   = '';
    document.getElementById('natEntrySubject').value = '';
    document.getElementById('natEntryStudentsContainer').innerHTML =
      '<div class="text-muted text-center py-3">Select class and learning area to load students.</div>';
    document.getElementById('natEntryError')?.classList.add('d-none');
    document.getElementById('kjseaPathwayRow')?.classList.add('d-none');
    new bootstrap.Modal(document.getElementById('natEnterModal')).show();
  },

  onExamTypeChange: function () {
    const type = document.getElementById('natEntryType')?.value;
    const kjseaRow = document.getElementById('kjseaPathwayRow');
    if (kjseaRow) {
      type === 'KJSEA_G9' ? kjseaRow.classList.remove('d-none') : kjseaRow.classList.add('d-none');
    }
  },

  loadEntryStudents: async function () {
    const classId   = document.getElementById('natEntryClass')?.value;
    const subjectId = document.getElementById('natEntrySubject')?.value;
    const examType  = document.getElementById('natEntryType')?.value;
    const examYear  = document.getElementById('natEntryYear')?.value;
    const container = document.getElementById('natEntryStudentsContainer');
    if (!container) return;
    if (!classId) return;

    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-success spinner-border-sm"></div></div>';

    try {
      const r    = await callAPI('/students/by-class-get/' + classId, 'GET');
      const list = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      // Load existing results for pre-fill
      let existing = {};
      if (examType && examYear && subjectId) {
        try {
          const re  = await callAPI(`/academic/national-exams?exam_type=${examType}&exam_year=${examYear}&learning_area_id=${subjectId}`, 'GET');
          const elist = Array.isArray(re?.data) ? re.data : [];
          elist.forEach(e => { existing[e.student_id] = e; });
        } catch (_) {}
      }

      const isKJSEA = examType === 'KJSEA_G9';
      const isKPSEA = examType === 'KPSEA_G6';

      const rows = list.map(s => {
        const ex = existing[s.id] || {};
        const pathwayCell = isKJSEA ? `
          <td>
            <select class="form-select form-select-sm pathway-sel" data-student="${s.id}">
              <option value="">— Pathway —</option>
              <option value="AST"             ${ex.pathway === 'AST'              ? 'selected':''}>AST</option>
              <option value="STEM"            ${ex.pathway === 'STEM'             ? 'selected':''}>STEM</option>
              <option value="Social_Sciences" ${ex.pathway === 'Social_Sciences'  ? 'selected':''}>Social Sciences</option>
              <option value="Humanities"      ${ex.pathway === 'Humanities'       ? 'selected':''}>Humanities</option>
            </select>
          </td>` : '';
        const rawGradeCell = isKPSEA ? `
          <td><input type="text" class="form-control form-control-sm raw-grade-input" data-student="${s.id}"
            value="${this._esc(ex.raw_grade || '')}" placeholder="1–6" maxlength="5" style="width:60px;"></td>` : '';
        const pointsCell = (isKPSEA || isKJSEA) ? `
          <td><input type="number" class="form-control form-control-sm points-input" data-student="${s.id}"
            value="${ex.points || ''}" min="0" max="600" step="0.1" placeholder="pts" style="width:80px;"></td>` : '';

        return `<tr>
          <td class="fw-semibold">${this._esc(s.first_name + ' ' + s.last_name)}</td>
          <td class="text-muted">${this._esc(s.admission_no || '—')}</td>
          <td><input type="number" class="form-control form-control-sm score-input" data-student="${s.id}"
            value="${ex.score || ''}" min="0" max="100" step="0.5" placeholder="Score"></td>
          <td><input type="number" class="form-control form-control-sm max-score-input" data-student="${s.id}"
            value="${ex.max_score || 100}" min="1" max="500" step="1" placeholder="Max" style="width:80px;"></td>
          ${rawGradeCell}
          ${pointsCell}
          ${pathwayCell}
        </tr>`;
      }).join('');

      const pathwayHeader  = isKJSEA ? '<th>Pathway</th>' : '';
      const rawGradeHeader = isKPSEA ? '<th>Raw Grade</th>' : '';
      const pointsHeader   = (isKPSEA || isKJSEA) ? '<th>Points</th>' : '';

      container.innerHTML = `<div class="table-responsive">
        <table class="table table-sm table-hover align-middle" id="natEntryTable">
          <thead class="table-light"><tr>
            <th>Student</th><th>Adm No</th><th>Score</th><th>Max Score</th>
            ${rawGradeHeader}${pointsHeader}${pathwayHeader}
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load students: ${this._esc(e.message)}</div>`;
    }
  },

  saveResults: async function () {
    const errEl     = document.getElementById('natEntryError');
    errEl?.classList.add('d-none');

    const examType  = document.getElementById('natEntryType')?.value;
    const examYear  = parseInt(document.getElementById('natEntryYear')?.value);
    const subjectId = parseInt(document.getElementById('natEntrySubject')?.value);
    const isKJSEA   = examType === 'KJSEA_G9';
    const isKPSEA   = examType === 'KPSEA_G6';

    if (!examType || !examYear || !subjectId) {
      if (errEl) { errEl.textContent = 'Exam type, year, and learning area are required'; errEl.classList.remove('d-none'); }
      return;
    }

    const results = [];
    document.querySelectorAll('#natEntryTable .score-input').forEach(input => {
      const studentId = parseInt(input.dataset.student);
      const scoreVal  = input.value.trim();
      if (!scoreVal) return;

      const maxInput  = document.querySelector(`.max-score-input[data-student="${studentId}"]`);
      const rawGrade  = document.querySelector(`.raw-grade-input[data-student="${studentId}"]`)?.value.trim() || null;
      const points    = document.querySelector(`.points-input[data-student="${studentId}"]`)?.value || null;
      const pathway   = document.querySelector(`.pathway-sel[data-student="${studentId}"]`)?.value || null;

      results.push({
        student_id:       studentId,
        learning_area_id: subjectId,
        score:            parseFloat(scoreVal),
        max_score:        parseFloat(maxInput?.value || 100),
        raw_grade:        rawGrade,
        points:           points ? parseFloat(points) : null,
        pathway:          pathway || null,
      });
    });

    if (!results.length) {
      if (errEl) { errEl.textContent = 'No scores entered'; errEl.classList.remove('d-none'); }
      return;
    }

    try {
      await callAPI('/academic/national-exams', 'POST', { exam_type: examType, exam_year: examYear, results });
      bootstrap.Modal.getInstance(document.getElementById('natEnterModal')).hide();
      showNotification(`Saved ${results.length} national exam results`, 'success');
      this.loadAll();
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Save failed'; errEl.classList.remove('d-none'); }
    }
  },

  // ── UTILS ──────────────────────────────────────────────────────────────

  _esc: function (s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  },
};
