/**
 * Student Growth Controller
 * Comprehensive student progress: performance, growth trend, competencies, insights.
 * APIs: /students, /academic/student-results, /academic/competency-ratings,
 *       /academic/formative-summary, /academic/annual-scores
 */
const studentGrowthCtrl = {

  _studentId:   null,
  _student:     null,
  _terms:       [],
  _currentTermId: null,
  _perfData:    [],
  _annualData:  null,
  _searchTimer: null,
  _allStudents: [],

  // ── Init ──────────────────────────────────────────────────────────────────
  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([this._loadTerms(), this._loadClasses()]);

    // Check for ?student_id= in URL
    const params = new URLSearchParams(window.location.search);
    const sid    = parseInt(params.get('student_id') || params.get('id'));
    if (sid > 0) {
      await this.loadStudent(sid);
    }
  },

  _loadTerms: async function () {
    try {
      const r = await callAPI('/academic/terms-list', 'GET');
      this._terms = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const current = this._terms.find(t => (t.status || '') === 'current');
      if (current) this._currentTermId = current.id;

      const opts = this._terms.map(t => `<option value="${t.id}">${t.name} ${t.year || ''}</option>`).join('');
      ['sgPerfTermFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = '<option value="">Current Term</option>' + opts;
      });
    } catch (e) { console.warn('Terms failed:', e); }
  },

  _loadClasses: async function () {
    try {
      const r = await callAPI('/academic/classes-list', 'GET');
      const classes = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const el = document.getElementById('sgClassFilter');
      if (el) el.innerHTML = '<option value="">All Classes</option>' +
        classes.map(c => `<option value="${c.id}">${this._esc(c.name || '')}</option>`).join('');
    } catch (e) { console.warn('Classes failed:', e); }
  },

  // ── Search ────────────────────────────────────────────────────────────────
  search: function (query) {
    clearTimeout(this._searchTimer);
    const container = document.getElementById('sgSearchResults');
    if (!container) return;
    if (!query || query.trim().length < 2) {
      container.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-search fs-1 d-block mb-2 opacity-25"></i>Start typing to find a student.</div>';
      return;
    }
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    this._searchTimer = setTimeout(async () => {
      try {
        const r = await callAPI('/students?search=' + encodeURIComponent(query.trim()), 'GET');
        const students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
        if (!students.length) {
          container.innerHTML = `<div class="alert alert-warning">No students found matching "${this._esc(query)}".</div>`;
          return;
        }
        container.innerHTML = `<div class="row g-2">${students.map(s => `
          <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm" style="cursor:pointer;" onclick="studentGrowthCtrl.loadStudent(${s.id})">
              <div class="card-body d-flex align-items-center gap-3 py-2">
                <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;flex-shrink:0;">
                  <i class="bi bi-person-fill text-success"></i>
                </div>
                <div>
                  <div class="fw-semibold">${this._esc((s.first_name||'') + ' ' + (s.last_name||''))}</div>
                  <div class="text-muted small">${this._esc(s.class_name||s.grade||'—')} · Adm: ${this._esc(s.admission_no||'')}</div>
                </div>
              </div>
            </div>
          </div>`).join('')}</div>`;
      } catch (e) {
        container.innerHTML = `<div class="alert alert-danger">Search failed.</div>`;
      }
    }, 350);
  },

  filterByClass: async function () {
    const classId = document.getElementById('sgClassFilter')?.value || '';
    if (!classId) { document.getElementById('sgSearchResults').innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-search fs-1 d-block mb-2 opacity-25"></i>Filter by class or search by name.</div>'; return; }
    const container = document.getElementById('sgSearchResults');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    try {
      const r = await callAPI('/academic/class-students?class_id=' + classId, 'GET');
      const students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      if (!students.length) { container.innerHTML = '<div class="alert alert-info">No students in this class.</div>'; return; }
      container.innerHTML = `<div class="row g-2">${students.map(s => `
        <div class="col-md-4 col-lg-3">
          <div class="card border-0 shadow-sm" style="cursor:pointer;" onclick="studentGrowthCtrl.loadStudent(${s.id || s.student_id})">
            <div class="card-body py-2 px-3">
              <div class="fw-semibold small">${this._esc((s.first_name||'') + ' ' + (s.last_name||''))}</div>
              <div class="text-muted" style="font-size:11px;">${this._esc(s.admission_no||'')}</div>
            </div>
          </div>
        </div>`).join('')}</div>`;
    } catch (e) { container.innerHTML = '<div class="alert alert-danger">Failed to load class.</div>'; }
  },

  backToSearch: function () {
    this._studentId = null;
    document.getElementById('sgSearchView').style.display  = '';
    document.getElementById('sgProfileView').style.display = 'none';
  },

  // ── Load Student ──────────────────────────────────────────────────────────
  loadStudent: async function (id) {
    this._studentId = id;
    document.getElementById('sgSearchView').style.display  = 'none';
    document.getElementById('sgProfileView').style.display = '';

    await Promise.all([
      this._loadStudentProfile(id),
      this.loadPerformance(),
      this._loadAnnualScores(id),
      this._loadCompetencies(id),
      this._loadAssessmentHistory(id),
    ]);
  },

  _loadStudentProfile: async function (id) {
    try {
      const r = await callAPI('/students/profile-get/' + id, 'GET');
      const s = r?.data ?? r ?? {};
      this._student = s;
      const name = ((s.first_name || '') + ' ' + (s.last_name || '')).trim();
      this._set('sgStudentName', name);
      this._set('sgClass',  s.class_name || s.grade || '—');
      this._set('sgAdmNo',  s.admission_no || '—');
    } catch (e) { console.warn('Profile failed:', e); }
  },

  // ── Performance Tab ───────────────────────────────────────────────────────
  loadPerformance: async function () {
    const termId  = document.getElementById('sgPerfTermFilter')?.value || this._currentTermId || '';
    const tbody   = document.getElementById('sgPerfBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';

    const term = this._terms.find(t => t.id == termId) || this._terms.find(t => (t.status||'') === 'current');
    this._set('sgTerm', term?.term_number || '—');
    this._set('sgPerfTerm', term?.name || 'Current');

    try {
      const qs = new URLSearchParams();
      qs.set('student_id', this._studentId);
      if (termId) qs.set('term_id', termId);
      const r = await callAPI('/academic/student-results?' + qs.toString(), 'GET');
      this._perfData = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!this._perfData.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">No assessment records for this term.</td></tr>';
        this._renderStrengthsWeaknesses([]);
        return;
      }

      // Populate learning area dropdown for growth chart
      const laOpts = [...new Set(this._perfData.map(r => r.learning_area_id || r.subject_id))];
      const laEl = document.getElementById('sgGrowthLASelect');
      if (laEl) laEl.innerHTML = '<option value="">— Select learning area —</option>' +
        this._perfData.map(r => `<option value="${r.learning_area_id||r.subject_id}">${this._esc(r.learning_area_name||r.subject_name||'—')}</option>`).join('');

      tbody.innerHTML = this._perfData.map(r => {
        const caAvg   = Number(r.formative_percentage  || 0);
        const examPct = Number(r.summative_percentage  || 0);
        const overall = Number(r.overall_percentage    || 0);
        const grade   = r.overall_grade || this._cbcGrade(overall);
        const color   = this._gradeColor(grade);
        const pctBar  = Math.min(overall, 100);
        return `<tr class="sg-grade-row">
          <td class="fw-semibold">${this._esc(r.learning_area_name || r.subject_name || '—')}</td>
          <td class="text-center text-muted small">${r.formative_count || r.assessment_count || '—'}</td>
          <td class="text-center ${this._gradeCls(caAvg)}">${caAvg > 0 ? caAvg.toFixed(1)+'%' : '—'}</td>
          <td class="text-center ${this._gradeCls(examPct)}">${examPct > 0 ? examPct.toFixed(1)+'%' : '—'}</td>
          <td class="text-center fw-bold ${this._gradeCls(overall)}">${overall > 0 ? overall.toFixed(1)+'%' : '—'}</td>
          <td class="text-center"><span class="badge bg-${color} fs-6 py-1 px-2">${grade}</span></td>
          <td style="min-width:100px;">
            <div class="progress sg-grade-bar">
              <div class="progress-bar bg-${color}" style="width:${pctBar}%"></div>
            </div>
          </td>
        </tr>`;
      }).join('');

      this._renderStrengthsWeaknesses(this._perfData);
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-4">Failed to load performance.</td></tr>`;
    }
  },

  // ── Annual Scores ─────────────────────────────────────────────────────────
  _loadAnnualScores: async function (id) {
    try {
      const r = await callAPI('/academic/student-results?student_id=' + id + '&type=annual', 'GET');
      this._annualData = r?.data ?? (Array.isArray(r) ? r[0] : null);
      const d = this._annualData || {};

      const yearAvg = Number(d.annual_percentage || d.year_average || 0);
      this._set('sgYearAvg',    yearAvg > 0 ? yearAvg.toFixed(1) + '%' : '—');

      const gradeEl = document.getElementById('sgOverallGradeBadge');
      if (gradeEl) {
        const g = d.annual_grade || this._cbcGrade(yearAvg);
        gradeEl.textContent  = g;
        gradeEl.className    = `badge fs-6 py-2 px-3 bg-${this._gradeColor(g)}`;
      }
      const pathEl = document.getElementById('sgPathwayBadge');
      if (pathEl) {
        const pathMap = { excelling:'success', on_track:'primary', support_needed:'warning' };
        const path    = d.pathway_classification || (yearAvg >= 75 ? 'excelling' : yearAvg >= 60 ? 'on_track' : 'support_needed');
        pathEl.textContent = path.replace('_',' ').replace(/\b\w/g, l => l.toUpperCase());
        pathEl.className   = `badge fs-6 py-2 px-3 bg-${pathMap[path] || 'secondary'}`;
      }

      // Year summary card
      const sumEl = document.getElementById('sgYearSummary');
      if (sumEl && d) {
        sumEl.innerHTML = `
          <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Term 1</span><span class="fw-bold">${d.term1_score ? d.term1_score+'%' : '—'} <span class="badge bg-${this._gradeColor(d.term1_grade||'—')} ms-1">${d.term1_grade||'—'}</span></span></div>
          <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Term 2</span><span class="fw-bold">${d.term2_score ? d.term2_score+'%' : '—'} <span class="badge bg-${this._gradeColor(d.term2_grade||'—')} ms-1">${d.term2_grade||'—'}</span></span></div>
          <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Term 3</span><span class="fw-bold">${d.term3_score ? d.term3_score+'%' : '—'} <span class="badge bg-${this._gradeColor(d.term3_grade||'—')} ms-1">${d.term3_grade||'—'}</span></span></div>
          <div class="d-flex justify-content-between py-2 fw-bold"><span>Year Average</span><span>${yearAvg > 0 ? yearAvg.toFixed(1)+'%' : '—'} <span class="badge bg-${this._gradeColor(d.annual_grade||'—')} ms-1">${d.annual_grade||'—'}</span></span></div>
          ${d.annual_rank ? `<div class="text-muted small text-center mt-2">Rank: ${d.annual_rank} of ${d.grade_total_students || '?'} (${d.grade_percentile ? d.grade_percentile+'th percentile' : ''})</div>` : ''}`;
      }
    } catch (e) { console.warn('Annual scores failed:', e); }
  },

  // ── Competencies Tab ──────────────────────────────────────────────────────
  _loadCompetencies: async function (id) {
    const grid = document.getElementById('sgCompetenciesGrid');
    if (!grid) return;
    try {
      const r = await callAPI('/academic/competency-ratings?student_id=' + id, 'GET');
      const ratings = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      const COMPETENCIES = [
        { code:'CC001', name:'Communication & Collaboration' },
        { code:'CC002', name:'Critical Thinking & Problem Solving' },
        { code:'CC003', name:'Creativity & Imagination' },
        { code:'CC004', name:'Citizenship' },
        { code:'CC005', name:'Digital Literacy' },
        { code:'CC006', name:'Learning to Learn' },
        { code:'CC007', name:'Self-Efficacy' },
        { code:'CC008', name:'Cultural Identity' },
      ];

      const rMap = {};
      ratings.forEach(r => { rMap[r.competency_id || r.code] = r; });

      if (!ratings.length) {
        grid.innerHTML = '<div class="col-12 text-center py-5 text-muted">No competency ratings recorded yet.</div>';
        return;
      }

      grid.innerHTML = COMPETENCIES.map(comp => {
        const r    = ratings.find(x => x.code === comp.code || x.competency_code === comp.code) || {};
        const rat  = r.rating || r.level || '—';
        const clr  = { EE:'success', ME:'primary', AE:'warning', BE:'danger' }[rat] || 'secondary';
        const pct  = { EE:100, ME:75, AE:50, BE:25 }[rat] || 0;
        return `<div class="col-md-6 col-lg-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="fw-semibold small">${this._esc(comp.name)}</span>
                <span class="badge bg-${clr}">${rat}</span>
              </div>
              <div class="progress" style="height:6px;">
                <div class="progress-bar bg-${clr}" style="width:${pct}%"></div>
              </div>
              ${r.evidence ? `<div class="text-muted mt-2" style="font-size:11px;">${this._esc(r.evidence)}</div>` : ''}
            </div>
          </div>
        </div>`;
      }).join('');
    } catch (e) {
      if (grid) grid.innerHTML = '<div class="col-12 text-muted small text-center py-3">Competency data unavailable.</div>';
    }
  },

  // ── Assessment History Tab ────────────────────────────────────────────────
  _loadAssessmentHistory: async function (id) {
    const tbody = document.getElementById('sgAssessmentsBody');
    if (!tbody) return;
    try {
      const r = await callAPI('/academic/student-assessment-history?student_id=' + id, 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">No individual assessment records found.</td></tr>';
        return;
      }
      const typeCls = { Assignment:'info', Homework:'secondary', Quiz:'primary', 'Short Test':'warning',
        Project:'success', 'End of Term Exam':'danger', 'Mid-Term Test':'orange' };
      tbody.innerHTML = items.map(a => {
        const pct  = Number(a.percentage || (a.marks_obtained && a.max_marks ? (a.marks_obtained/a.max_marks*100) : 0));
        const grade = a.grade || a.cbc_grade || this._cbcGrade(pct);
        return `<tr>
          <td class="fw-semibold small">${this._esc(a.title || a.assessment_title || '—')}</td>
          <td><span class="badge bg-${typeCls[a.type_name||'']||'secondary'}">${this._esc(a.type_name||'—')}</span></td>
          <td class="small">${this._esc(a.learning_area_name || a.subject_name || '—')}</td>
          <td class="small">${this._esc(a.assessment_date || a.date || '—')}</td>
          <td class="text-center">${a.marks_obtained ?? '—'}/${a.max_marks ?? '—'}</td>
          <td class="text-center ${this._gradeCls(pct)}">${pct > 0 ? pct.toFixed(1)+'%' : '—'}</td>
          <td class="text-center"><span class="badge bg-${this._gradeColor(grade)}">${grade}</span></td>
        </tr>`;
      }).join('');
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-3">Assessment history unavailable.</td></tr>';
    }
  },

  // ── Strengths & Weaknesses ────────────────────────────────────────────────
  _renderStrengthsWeaknesses: function (perfData) {
    const strEl  = document.getElementById('sgStrengthsList');
    const wkEl   = document.getElementById('sgWeaknessesList');

    const strengths  = perfData.filter(r => ['EE','ME'].includes(r.overall_grade || this._cbcGrade(Number(r.overall_percentage || 0))));
    const weaknesses = perfData.filter(r => ['AE','BE'].includes(r.overall_grade || this._cbcGrade(Number(r.overall_percentage || 0))));

    if (strEl) {
      strEl.innerHTML = strengths.length
        ? strengths.map(r => `<div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-success bg-opacity-10 rounded">
            <span class="small fw-semibold">${this._esc(r.learning_area_name||r.subject_name||'—')}</span>
            <span class="badge bg-${this._gradeColor(r.overall_grade||'ME')}">${r.overall_grade||'—'} · ${Number(r.overall_percentage||0).toFixed(1)}%</span>
          </div>`).join('')
        : '<div class="text-muted small">No EE/ME areas identified yet.</div>';
    }

    if (wkEl) {
      wkEl.innerHTML = weaknesses.length
        ? weaknesses.map(r => `<div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-warning bg-opacity-10 rounded">
            <span class="small fw-semibold">${this._esc(r.learning_area_name||r.subject_name||'—')}</span>
            <span class="badge bg-${this._gradeColor(r.overall_grade||'AE')}">${r.overall_grade||'—'} · ${Number(r.overall_percentage||0).toFixed(1)}%</span>
          </div>`).join('')
        : '<div class="text-muted small text-success"><i class="bi bi-check-circle me-1"></i>No areas of concern — great performance!</div>';
    }

    // Comments
    const comEl = document.getElementById('sgCommentsBlock');
    if (comEl && this._annualData) {
      const d = this._annualData;
      comEl.innerHTML = [
        d.insights_summary ? `<p class="small"><strong>AI Insights:</strong> ${this._esc(d.insights_summary)}</p>` : '',
        d.strengths        ? `<p class="small text-success"><strong>Strengths:</strong> ${this._esc(d.strengths)}</p>` : '',
        d.weaknesses       ? `<p class="small text-warning"><strong>Areas to Improve:</strong> ${this._esc(d.weaknesses)}</p>` : '',
      ].filter(Boolean).join('') || '<div class="text-muted small">No comments recorded yet.</div>';
    }
  },

  // ── Growth Chart (simple text-based trend) ────────────────────────────────
  plotGrowth: async function () {
    const laId  = document.getElementById('sgGrowthLASelect')?.value;
    const chart = document.getElementById('sgGrowthChart');
    if (!laId || !chart) return;

    chart.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

    try {
      const r = await callAPI(`/academic/student-growth-trend?student_id=${this._studentId}&learning_area_id=${laId}`, 'GET');
      const trend = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!trend.length) {
        chart.innerHTML = '<div class="text-muted text-center py-3">No multi-term data available yet.</div>';
        return;
      }

      // Simple visual bars
      const max = Math.max(...trend.map(t => Number(t.overall_percentage || 0)), 1);
      chart.innerHTML = `<div class="d-flex align-items-end gap-3 justify-content-center" style="height:160px; padding-top:20px;">` +
        trend.map(t => {
          const pct   = Number(t.overall_percentage || 0);
          const h     = Math.max(Math.round((pct / max) * 140), 4);
          const grade = t.overall_grade || this._cbcGrade(pct);
          const color = this._gradeColor(grade);
          return `<div class="text-center d-flex flex-column align-items-center" style="min-width:60px;">
            <span class="small fw-bold text-${color} mb-1">${pct.toFixed(0)}%</span>
            <div class="bg-${color} rounded-top" style="width:40px;height:${h}px;"></div>
            <div class="mt-1 small text-muted">${this._esc(t.term_name || 'T'+t.term_number)}</div>
            <span class="badge bg-${color} mt-1">${grade}</span>
          </div>`;
        }).join('') + '</div>';
    } catch (e) {
      chart.innerHTML = '<div class="text-muted text-center py-3">Trend data unavailable.</div>';
    }
  },

  // ── Utilities ─────────────────────────────────────────────────────────────
  _cbcGrade: function (pct) {
    if (pct >= 75) return 'EE';
    if (pct >= 60) return 'ME';
    if (pct >= 40) return 'AE';
    return 'BE';
  },
  _gradeColor: g => ({ EE:'success', ME:'primary', AE:'warning', BE:'danger' })[g] || 'secondary',
  _gradeCls: function (v) {
    const n = Number(v);
    if (isNaN(n) || n === 0) return '';
    return n >= 75 ? 'text-success fw-bold' : n >= 60 ? 'text-primary fw-semibold' : n >= 40 ? 'text-warning' : 'text-danger';
  },
  _extract: function (settled) {
    if (settled.status !== 'fulfilled') return [];
    const r = settled.value;
    return Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
  },
  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => studentGrowthCtrl.init());
