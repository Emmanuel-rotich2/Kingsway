/**
 * My Schemes of Work Controller
 * Page: my_schemes_of_work.php
 * Class Teacher-specific view of their own schemes of work
 * Integrates with AcademicContext for academic year awareness
 */
const MySchemesOfWorkController = {
  state: {
    schemes: [],
    currentAcademicYear: null,
    currentTerm: null,
    planningContext: null,
    workbookId: null,
    stats: {
      total: 0,
      approved: 0,
      pending: 0,
      overdue: 0
    }
  },

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    
    
    this.bindEvents();
    // The shared year/term selector (current year, running term by default)
    // loads the page now and on every change.
    const yearBar = await window.AcademicYearFilter?.mountBar({ term: true, planning: true, allTermsLabel: 'All terms', allTermsDefault: false, onChange: (yearId, year, termId) => { this.state.currentAcademicYear = yearId; this.state.currentTerm = termId; this.loadSchemes(); } });
    if (!yearBar) await this.loadSchemes();
    const contextEl = document.getElementById('currentPlanningContext');
    if (contextEl) contextEl.innerHTML = '<i class="bi bi-calendar2-check text-success me-2"></i>Showing the academic year and term selected above (the current ones by default).';
  },

  bindEvents() {
    // Academic year and term come from the shared year/term bar (view only).
  },

  /** Year and term the scheme builder plans for: the ones chosen above. */
  planningParams() {
    const params = {};
    if (this.state.currentAcademicYear) params.academic_year_id = this.state.currentAcademicYear;
    if (this.state.currentTerm) params.academic_year_term_id = this.state.currentTerm;
    return params;
  },

  async loadAcademicYears() {
    try {
      const years = await window.API.academic.listYears() || [];
      const yearSelect = document.getElementById('academicYearSelect');
      if (yearSelect) {
        yearSelect.innerHTML = '<option value="">Select Academic Year</option>' + 
          years.map(year => `<option value="${year.id}">${kwEscapeHtml(year.name)}</option>`).join('');
        
        // Set current academic year if available
        if (this.state.currentAcademicYear) {
          yearSelect.value = this.state.currentAcademicYear;
        }
      }
    } catch (error) {
      console.error('Error loading academic years:', error);
    }
  },

  async loadSchemes() {
    try {
      const user = window.AuthContext?.getUser();
      if (!user || !user.id) {
        this.showNotification('User not authenticated', 'error');
        return;
      }

      const params = {
        teacher_id: user.id
      };
      
      if (this.state.currentAcademicYear) {
        params.academic_year_id = this.state.currentAcademicYear;
      }
      
      if (this.state.currentTerm) {
        params.term_id = this.state.currentTerm;
      }

      const response = await window.API.academic.getMySchemes(params);
      const schemeRows = response?.data?.schemes || response?.data || response || [];
      this.state.schemes = Array.isArray(schemeRows) ? schemeRows : [];

      // A workbook is intentionally saved before its first scheme row is
      // published to the schemes_of_work table. Keep that resumable draft
      // visible here, otherwise the teacher has no way to find it again.
      // A new year or term needs its own planning context.
      this.state.planningContext = null;
      try {
        const contextResponse = await window.API.academic.getTeacherPlanningContext(this.planningParams());
        const context = contextResponse?.data || contextResponse || {};
        this.state.planningContext = context;
        const linkedWorkbookIds = new Set(this.state.schemes.map(s => Number(s.scheme_workbook_id)).filter(Boolean));
        const draftRows = (context.drafts || []).filter(d => !linkedWorkbookIds.has(Number(d.id))).map(d => {
          let stream = null;
          let area = null;
          (context.streams || []).some(candidate => {
            const match = (candidate.learning_areas || []).find(a => Number(a.stream_learning_area_id) === Number(d.academic_year_class_stream_learning_area_id));
            if (match) { stream = candidate; area = match; return true; }
            return false;
          });
          return {
            id: null,
            scheme_workbook_id: Number(d.id),
            workbook_status: 'draft',
            is_workbook_draft: true,
            subject_id: area?.id,
            subject_name: area?.name || 'Learning area',
            class_id: stream?.class_id,
            class_name: stream?.class_name || '--',
            stream_id: stream?.stream_id,
            stream_name: stream?.stream_name,
            week_number: 'Term workspace',
            strand_name: 'Multiple weekly rows',
            status: 'draft',
            progress: this.workbookProgress(d.payload),
            updated_at: d.updated_at
          };
        });
        this.state.schemes = [...draftRows, ...this.state.schemes];
      } catch (contextError) {
        console.warn('Planning context could not be loaded for draft recovery', contextError);
      }
      this.renderSchemesTable();
      this.updateStats();
    } catch (error) {
      console.error('Error loading schemes of work:', error);
      this.showNotification('Failed to load schemes of work', 'error');
    }
  },

  workbookProgress(payload) {
    const rows = (payload || []).flatMap(w => w.items || []);
    if (!rows.length) return 0;
    const complete = rows.filter(i => i.strand_id && i.sub_strand_id && i.title && i.learning_outcomes && i.activities).length;
    return Math.round((complete / rows.length) * 100);
  },

  displaySchemes() {
    const groups = new Map();
    (this.state.schemes || []).forEach(scheme => {
      const key = scheme.scheme_workbook_id ? `workbook:${scheme.scheme_workbook_id}` : `row:${scheme.id}`;
      if (!groups.has(key)) {
        groups.set(key, {...scheme, id: scheme.scheme_workbook_id ? null : scheme.id, representative_id: scheme.id, rows: [], week_numbers: [], strand_labels: []});
      }
      const group = groups.get(key);
      group.rows.push(scheme);
      if (scheme.week_number !== null && scheme.week_number !== undefined && scheme.week_number !== '' && scheme.week_number !== 'Term workspace') group.week_numbers.push(Number(scheme.week_number));
      const label = [scheme.strand_name, scheme.sub_strand_name].filter(Boolean).join(' / ');
      if (label && !group.strand_labels.includes(label)) group.strand_labels.push(label);
      if (scheme.status === 'approved') group.status = 'approved';
      else if (scheme.workbook_status === 'submitted' || scheme.status === 'pending' || scheme.status === 'submitted') group.status = 'pending';
      group.progress = Math.max(Number(group.progress || 0), Number(scheme.progress || 0));
      if (scheme.updated_at && (!group.updated_at || scheme.updated_at > group.updated_at)) group.updated_at = scheme.updated_at;
    });
    return [...groups.values()].map(group => {
      group.week_numbers = [...new Set(group.week_numbers)].sort((a,b) => a-b);
      group.week_summary = group.week_numbers.length ? `Weeks ${group.week_numbers[0]}${group.week_numbers.length > 1 ? `–${group.week_numbers[group.week_numbers.length - 1]}` : ''}` : (group.is_workbook_draft ? 'Term workspace' : '—');
      group.strand_summary = group.strand_labels.length ? (group.strand_labels.length > 2 ? `${group.strand_labels.slice(0,2).join(', ')} +${group.strand_labels.length - 2} more` : group.strand_labels.join(', ')) : 'Multiple weekly rows';
      return group;
    });
  },

  renderSchemesTable() {
    const tbody = document.querySelector('#schemesTable tbody');
    if (!tbody) return;

    const schemes = this.displaySchemes();
    if (schemes.length === 0) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">No schemes of work found for your current stream-learning-area assignments.</td></tr>`;
      return;
    }

    tbody.innerHTML = schemes.map((scheme, index) => {
      const statusBadge = this.getStatusBadge(scheme.status);
      const progress = scheme.progress || 0;
      const progressColor = progress >= 100 ? 'success' : progress >= 50 ? 'primary' : 'warning';
      const lastUpdated = scheme.updated_at ? new Date(scheme.updated_at).toLocaleDateString() : '—';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(scheme.subject_name)}</strong></td>
          <td>${this.escapeHtml((scheme.class_name || '--') + (scheme.stream_name ? ' · ' + scheme.stream_name : ''))}</td>
          <td>${this.escapeHtml(scheme.week_summary)}</td>
          <td>${this.escapeHtml(scheme.strand_summary)}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="progress" style="height: 20px;">
              <div class="progress-bar bg-${progressColor}" style="width: ${progress}%">${progress}%</div>
            </div>
          </td>
          <td>${lastUpdated}</td>
          <td><div class="btn-group btn-group-sm">
            ${scheme.scheme_workbook_id ? `<button class="btn btn-outline-primary" data-kw-onclick="MySchemesOfWorkController.viewWorkbook(${scheme.scheme_workbook_id})" title="View complete term workbook" aria-label="View complete term workbook"><i class="bi bi-eye me-1"></i>View</button>` : `<button class="btn btn-outline-primary" data-kw-onclick="MySchemesOfWorkController.viewScheme(${scheme.id})" title="View weekly plan" aria-label="View weekly plan"><i class="bi bi-eye me-1"></i>View</button>`}
            ${scheme.scheme_workbook_id && scheme.workbook_status === 'draft' ? `<button class="btn btn-outline-secondary" data-kw-onclick="MySchemesOfWorkController.editWorkbook(${scheme.scheme_workbook_id})" title="Continue term workbook" aria-label="Edit term workbook" data-year-write><i class="bi bi-pencil me-1"></i>Edit</button><button class="btn btn-outline-success" data-kw-onclick="MySchemesOfWorkController.submitWorkbook(${scheme.scheme_workbook_id})" title="Submit complete term workbook" aria-label="Submit term workbook" data-year-write><i class="bi bi-send me-1"></i>Submit</button>` : ''}
            ${scheme.scheme_workbook_id && scheme.workbook_status === 'approved' ? `<button class="btn btn-outline-warning" data-kw-onclick="MySchemesOfWorkController.requestRevision(${scheme.scheme_workbook_id})" title="Create a controlled revision" aria-label="Request scheme revision"><i class="bi bi-arrow-repeat me-1"></i>Request revision</button>` : ''}
            ${scheme.is_workbook_draft ? '<span class="badge bg-info text-dark ms-1 align-content-center">Saved draft</span>' : ''}
            ${scheme.workbook_status === 'submitted' ? '<span class="badge bg-warning text-dark ms-1 align-content-center">Workbook submitted</span>' : ''}
            ${!scheme.scheme_workbook_id && scheme.id ? '<span class="badge bg-light text-muted ms-1 align-content-center" title="This legacy row requires reconciliation">View only</span>' : ''}
          </div></td>
        </tr>
      `;
    }).join('');
  },

  getStatusBadge(status) {
    const statusMap = {
      'draft': '<span class="badge bg-secondary">Draft</span>',
      'pending': '<span class="badge bg-warning">Pending Review</span>',
      'approved': '<span class="badge bg-success">Approved</span>',
      'rejected': '<span class="badge bg-danger">Rejected</span>',
      'overdue': '<span class="badge bg-danger">Overdue</span>'
    };
    return statusMap[status] || '<span class="badge bg-secondary">Unknown</span>';
  },

  updateStats() {
    const schemes = this.displaySchemes();
    this.state.stats.total = schemes.length;
    this.state.stats.approved = schemes.filter(s => s.status === 'approved').length;
    this.state.stats.pending = schemes.filter(s => s.status === 'pending').length;
    this.state.stats.overdue = schemes.filter(s => s.status === 'overdue').length;

    document.getElementById('totalSchemesCount').textContent = this.state.stats.total;
    document.getElementById('approvedSchemesCount').textContent = this.state.stats.approved;
    document.getElementById('pendingSchemesCount').textContent = this.state.stats.pending;
    document.getElementById('overdueSchemesCount').textContent = this.state.stats.overdue;
  },

  createScheme() {
    const modal = document.getElementById('teacherSchemeBuilder');
    if (!modal) return;
    this.openTeacherBuilder();
    bootstrap.Modal.getOrCreateInstance(modal).show();
  },

  async openTeacherBuilder() {
    try {
      if (!this.state.planningContext) this.state.planningContext = await window.API.academic.getTeacherPlanningContext(this.planningParams());
      const ctx = this.state.planningContext?.data || this.state.planningContext || {};
      this.state.planningContext = ctx;
      const period = document.getElementById('tsPeriod');
      if (period) period.textContent = ctx.term_name ? `· ${ctx.term_name}, ${ctx.year_code || ''}`.trim() : '';
      const stream = document.getElementById('tsStream');
      stream.innerHTML = '<option value="">Select stream</option>' + (ctx.streams || []).map(s => `<option value="${s.academic_year_class_stream_id}">${this.escapeHtml((s.class_name || '') + ' · ' + (s.stream_name || ''))}</option>`).join('');
      stream.onchange = () => { this.populateTeacherAreas(); };
      document.getElementById('tsArea').onchange = () => this.renderTeacherWeeks();
      document.getElementById('saveTeacherScheme').onclick = () => this.saveTeacherScheme(false);
      document.getElementById('submitTeacherScheme').onclick = () => this.saveTeacherScheme(true);
    } catch (e) { this.showNotification(e.message || 'Unable to load your planning context', 'error'); }
  },

  populateTeacherAreas() {
    const sid = Number(document.getElementById('tsStream').value);
    const s = (this.state.planningContext.streams || []).find(x => Number(x.academic_year_class_stream_id) === sid);
    document.getElementById('tsArea').innerHTML = '<option value="">Select learning area</option>' + (s?.learning_areas || []).map(a => `<option value="${a.id}">${this.escapeHtml(a.name)}</option>`).join('');
    document.getElementById('tsWeeks').innerHTML = '<div class="alert alert-light border">Select a learning area to load every week of the selected term.</div>';
    document.getElementById('tsArea').onchange = () => this.renderTeacherWeeks();
  },

  renderTeacherWeeks() {
    const area = Number(document.getElementById('tsArea').value); const weeks = this.state.planningContext.weeks || [];
    const rows = this.currentCurriculumRows(area); const strands = [...new Map(rows.map(r => [r.strand_id, r])).values()];
    if (!area) { document.getElementById('tsWeeks').innerHTML = '<div class="alert alert-light border">Select a learning area to load every week of the selected term.</div>'; return; }
    const strandOptions = '<option value="">Select strand</option>' + strands.map(r => `<option value="${r.strand_id}">${this.escapeHtml(r.strand_name)}</option>`).join('');
    document.getElementById('tsWeeks').innerHTML = weeks.map(w => w.is_reserved ? `<section class="card border-warning shadow-sm ts-week reserved-week" data-week="${w.week_number}" data-reserved="1"><div class="card-header bg-warning-subtle"><strong>Week ${w.week_number}</strong><small class="text-muted ms-2">${w.week_start} to ${w.week_end}</small><span class="badge text-bg-warning ms-2">Reserved week</span></div><div class="card-body"><div class="alert alert-warning mb-0"><i class="bi bi-calendar2-event me-1"></i>${this.escapeHtml(w.reserved_reason || 'Reserved for examinations and other school activities')}. No scheme rows are required for this week.</div></div></section>` : `<section class="card border shadow-sm ts-week" data-week="${w.week_number}"><div class="card-header bg-success-subtle d-flex justify-content-between align-items-center"><div><strong>Week ${w.week_number}</strong><small class="text-muted ms-2">${w.week_start} to ${w.week_end}</small></div><button type="button" class="btn btn-sm btn-outline-success ts-add-item"><i class="bi bi-plus"></i> Add strand / sub-strand</button></div><div class="card-body ts-items">${this.teacherItemMarkup(strandOptions)}</div></section>`).join('');
    document.querySelectorAll('.ts-add-item').forEach(btn => btn.addEventListener('click', e => this.addTeacherItem(e.currentTarget.closest('.ts-week'), strandOptions)));
    document.querySelectorAll('.ts-strand').forEach(sel => sel.addEventListener('change', e => this.populateItemSubStrands(e.currentTarget)));
    document.querySelectorAll('.ts-substrand').forEach(sel => sel.addEventListener('change', e => this.applyCurriculumDefaults(e.currentTarget)));
    document.querySelectorAll('.ts-assessment-tools-list').forEach(list => list.addEventListener('change', e => this.refreshAssessmentRubrics(e.currentTarget.closest('.ts-item'))));
    document.getElementById('tsWeeks').addEventListener('change', e => { if (e.target.closest('.ts-assessment-tools-list')) this.refreshAssessmentRubrics(e.target.closest('.ts-item')); });
    document.querySelectorAll('.ts-add-outcome').forEach(btn => btn.addEventListener('click', e => this.addPlanningChoice(e.currentTarget.closest('.ts-item'), 'outcome')));
    document.querySelectorAll('.ts-add-experience').forEach(btn => btn.addEventListener('click', e => this.addPlanningChoice(e.currentTarget.closest('.ts-item'), 'experience')));
    document.querySelectorAll('.ts-add-question').forEach(btn => btn.addEventListener('click', e => this.addPlanningChoice(e.currentTarget.closest('.ts-item'), 'question')));
    this.restoreTeacherDraft();
  },

  teacherItemMarkup(strandOptions) {
    return `<div class="ts-item border rounded p-3 mb-3"><div class="row g-2"><div class="col-md-3"><label class="small">Strand</label><select class="form-select form-select-sm ts-strand">${strandOptions}</select></div><div class="col-md-3"><label class="small">Sub-strand</label><select class="form-select form-select-sm ts-substrand"><option value="">Select sub-strand</option></select></div><div class="col-md-6"><label class="small">Focus / title</label><input class="form-control form-control-sm ts-item-title"></div><div class="col-md-6"><label class="small">Specific learning outcomes</label><div class="ts-outcomes-list border rounded p-2 small text-muted">Select a sub-strand.</div><button type="button" class="btn btn-link btn-sm p-0 ts-add-outcome">+ Add custom outcome</button></div><div class="col-md-6"><label class="small">Learning experiences</label><div class="ts-experiences-list border rounded p-2 small text-muted">Select a sub-strand.</div><button type="button" class="btn btn-link btn-sm p-0 ts-add-experience">+ Add custom experience</button></div><div class="col-md-6"><label class="small">Key inquiry questions</label><div class="ts-questions-list border rounded p-2 small text-muted">Select a sub-strand.</div><button type="button" class="btn btn-link btn-sm p-0 ts-add-question">+ Add custom question</button></div><div class="col-md-6"><label class="small">CBC competencies measured</label><div class="ts-competencies-list border rounded p-2 small text-muted">Select a sub-strand.</div></div><div class="col-md-6"><label class="small">Assessment tools</label><div class="ts-assessment-tools-list border rounded p-2 small text-muted">Select a sub-strand.</div></div><div class="col-md-6"><label class="small">CBC sub-strand rubrics</label><div class="ts-rubrics-list border rounded p-2 small text-muted">Select a sub-strand.</div></div><div class="col-md-6"><label class="small">Tool-specific rubric criteria</label><div class="ts-assessment-rubrics-list border rounded p-2 small text-muted">Select assessment tools first.</div></div><div class="col-12"><div class="alert alert-info py-2 mb-0 small"><i class="bi bi-info-circle me-1"></i>Resources, expected coverage and delivery notes belong to the lesson plan created from this approved scheme row.</div></div></div></div>`;
  },

  restoreTeacherDraft() {
    const stream=(this.state.planningContext.streams||[]).find(s=>Number(s.academic_year_class_stream_id)===Number(document.getElementById('tsStream').value)); const area=Number(document.getElementById('tsArea').value); const areaLink=stream?.learning_areas.find(a=>Number(a.id)===area); const draft=(this.state.planningContext.drafts||[]).find(d=>Number(d.academic_year_class_stream_learning_area_id)===Number(areaLink?.stream_learning_area_id)); if(!draft)return; this.state.workbookId=draft.id; if(draft.title)document.getElementById('tsTitle').value=draft.title; (draft.payload||[]).forEach((w)=>{const card=document.querySelector(`.ts-week[data-week="${w.week_number}"]`);if(!card)return; (w.items||[]).forEach((item,ii)=>{if(ii>0)this.addTeacherItem(card, card.querySelector('.ts-strand').innerHTML);const row=card.querySelectorAll('.ts-item')[ii];if(!row)return;row.querySelector('.ts-strand').value=item.strand_id;this.populateItemSubStrands(row.querySelector('.ts-strand'));row.querySelector('.ts-substrand').value=item.sub_strand_id;this.applyCurriculumDefaults(row.querySelector('.ts-substrand'));if(item.outcomes)this.restoreChoiceSelection(row,'outcome',item.outcomes);if(item.experiences)this.restoreChoiceSelection(row,'experience',item.experiences);if(item.questions)this.restoreChoiceSelection(row,'question',item.questions);(item.competency_ids||[]).forEach(id=>{const x=row.querySelector(`.ts-competencies-list input[data-id="${id}"]`);if(x)x.checked=true;});(item.assessment_tool_ids||[]).forEach(id=>{const x=row.querySelector(`.ts-assessment-tools-list input[data-id="${id}"]`);if(x)x.checked=true;});this.refreshAssessmentRubrics(row);(item.rubric_ids||[]).forEach(id=>{const x=row.querySelector(`.ts-rubrics-list input[data-id="${id}"]`);if(x)x.checked=true;});(item.assessment_rubric_ids||[]).forEach(id=>{const x=row.querySelector(`.ts-assessment-rubrics-list input[data-id="${id}"]`);if(x)x.checked=true;});row.querySelector('.ts-item-title').value=item.title||'';});});
  },

  addTeacherItem(week, strandOptions) {
    const item = week.querySelector('.ts-item').cloneNode(true); item.querySelectorAll('input,textarea').forEach(x => x.value = ''); item.querySelectorAll('select').forEach(x => x.value = ''); week.querySelector('.ts-items').appendChild(item); item.querySelector('.ts-strand').addEventListener('change', e => this.populateItemSubStrands(e.currentTarget)); item.querySelector('.ts-substrand').addEventListener('change', e => this.applyCurriculumDefaults(e.currentTarget)); item.querySelector('.ts-add-outcome').addEventListener('click', e => this.addPlanningChoice(e.currentTarget.closest('.ts-item'), 'outcome')); item.querySelector('.ts-add-experience').addEventListener('click', e => this.addPlanningChoice(e.currentTarget.closest('.ts-item'), 'experience')); item.querySelector('.ts-add-question').addEventListener('click', e => this.addPlanningChoice(e.currentTarget.closest('.ts-item'), 'question'));
  },

  populateItemSubStrands(strandSelect) {
    const area = Number(document.getElementById('tsArea').value); const strand = Number(strandSelect.value); const rows = this.currentCurriculumRows(area).filter(r => Number(r.strand_id) === strand); const sub = strandSelect.closest('.ts-item').querySelector('.ts-substrand');
    sub.innerHTML = '<option value="">Select sub-strand</option>' + rows.filter(r => r.sub_strand_id).map(r => `<option value="${r.sub_strand_id}">${this.escapeHtml(r.sub_strand_name)}</option>`).join('');
    sub.onchange = e => this.applyCurriculumDefaults(e.currentTarget);
  },

  applyCurriculumDefaults(subStrandSelect) {
    const area = Number(document.getElementById('tsArea').value);
    const subStrandId = Number(subStrandSelect.value);
    const curriculum = this.currentCurriculumRows(area).find(r => Number(r.sub_strand_id) === subStrandId);
    if (!curriculum) return;
    const row = subStrandSelect.closest('.ts-item');
    this.renderPlanningChoices(row, 'outcome', (curriculum.learning_outcomes || []).map((text, index) => ({text, id: curriculum.learning_outcome_ids?.[index] || null})));
    this.renderPlanningChoices(row, 'experience', (curriculum.suggested_experiences || []).map((text, index) => ({text, id: curriculum.suggested_experience_ids?.[index] || null})));
    this.renderPlanningChoices(row, 'question', (curriculum.key_inquiry_questions || []).map(text => ({text, id: null})));
    this.renderPlanningChoices(row, 'competency', curriculum.competencies || []);
    this.renderPlanningChoices(row, 'assessment_tool', curriculum.assessment_tools || []);
    this.renderPlanningChoices(row, 'rubric', curriculum.rubrics || []);
    this.renderAssessmentRubrics(row, curriculum.assessment_rubrics || []);
  },

  renderPlanningChoices(row, kind, choices, selected = []) {
    const selectors = {outcome:'.ts-outcomes-list', experience:'.ts-experiences-list', question:'.ts-questions-list', competency:'.ts-competencies-list', assessment_tool:'.ts-assessment-tools-list', rubric:'.ts-rubrics-list'};
    const list = row.querySelector(selectors[kind]);
    if (!list) return;
    const selectedIds = selected.map(x => Number(typeof x === 'object' ? x.id : x)).filter(Boolean);
    list.innerHTML = choices.length ? choices.map(choice => { const id=Number(choice.id||choice.competency_id||choice.assessment_tool_id||choice.sub_strand_rubric_id||0); const text=choice.text||choice.name||choice.criteria_name||choice.code||''; const details=choice.code ? `${this.escapeHtml(choice.code)} · ` : ''; if (['outcome','experience','question'].includes(kind)) return `<div class="d-flex align-items-start gap-1 mb-1 ts-choice"><input type="checkbox" class="form-check-input mt-1 ts-choice-check" data-kind="${kind}" data-id="${id}" ${selected.includes(text) ? 'checked' : ''}><input class="form-control form-control-sm ts-choice-text" value="${this.escapeHtml(text)}"><button type="button" class="btn btn-sm btn-link text-danger p-0 ts-remove-choice" title="Remove"><i class="bi bi-x"></i></button></div>`; return `<label class="d-flex align-items-start gap-2 mb-1 ts-choice"><input type="checkbox" class="form-check-input mt-1 ts-choice-check" data-kind="${kind}" data-id="${id}" ${selectedIds.includes(id) ? 'checked' : ''}><span class="small">${details}${this.escapeHtml(text)}${choice.weight ? ` <span class="text-muted">(weight ${this.escapeHtml(choice.weight)})</span>` : ''}</span></label>`; }).join('') : `<span class="text-muted">No ${kind === 'rubric' ? 'CBC sub-strand rubrics' : kind === 'competency' ? 'competency mappings' : 'preloaded items'} are configured for this sub-strand.</span>`;
    list.querySelectorAll('.ts-remove-choice').forEach(button => button.addEventListener('click', e => e.currentTarget.closest('.ts-choice').remove()));
  },

  renderAssessmentRubrics(row, rubrics, selected = []) {
    const list = row.querySelector('.ts-assessment-rubrics-list'); if (!list) return;
    const ids = selected.map(x => Number(typeof x === 'object' ? x.id : x)).filter(Boolean);
    list.innerHTML = rubrics.length ? rubrics.map(r => `<label class="d-flex align-items-start gap-2 mb-1 ts-choice"><input type="checkbox" class="form-check-input mt-1 ts-assessment-rubric-check" data-id="${Number(r.id)}" ${ids.includes(Number(r.id)) ? 'checked' : ''}><span class="small"><strong>${this.escapeHtml(r.criteria_name || '')}</strong><br><span class="text-muted">${this.escapeHtml(r.level_1_descriptor || '')}</span></span></label>`).join('') : '<span class="text-muted">No tool-specific rubric criteria are configured for the selected assessment tools.</span>';
  },

  refreshAssessmentRubrics(row) {
    const area = Number(document.getElementById('tsArea').value);
    const sub = Number(row.querySelector('.ts-substrand')?.value || 0);
    const curriculum = this.currentCurriculumRows(area).find(r => Number(r.sub_strand_id) === sub);
    const selectedTools = [...row.querySelectorAll('.ts-assessment-tools-list .ts-choice-check:checked')].map(x => Number(x.dataset.id));
    this.renderAssessmentRubrics(row, (curriculum?.assessment_rubrics || []).filter(r => selectedTools.includes(Number(r.tool_id))), []);
  },

  addPlanningChoice(row, kind, text = '') {
    const list = row.querySelector(kind === 'outcome' ? '.ts-outcomes-list' : kind === 'experience' ? '.ts-experiences-list' : '.ts-questions-list');
    if (!list) return;
    const empty = list.querySelector('.text-muted'); if (empty) list.innerHTML = '';
    const choice = document.createElement('div'); choice.className = 'd-flex align-items-start gap-1 mb-1 ts-choice';
    choice.innerHTML = `<input type="checkbox" class="form-check-input mt-1 ts-choice-check" data-kind="${kind}" data-id="" checked><input class="form-control form-control-sm ts-choice-text" value="${this.escapeHtml(text)}" placeholder="Add ${kind === 'outcome' ? 'learning outcome' : kind === 'experience' ? 'learning experience' : 'key inquiry question'}"><button type="button" class="btn btn-sm btn-link text-danger p-0 ts-remove-choice" title="Remove"><i class="bi bi-x"></i></button>`;
    list.appendChild(choice);
    choice.querySelector('.ts-remove-choice').addEventListener('click', () => choice.remove());
    choice.querySelector('.ts-choice-text').focus();
  },

  restoreChoiceSelection(row, kind, selected) {
    const texts = selected.map(item => typeof item === 'string' ? item : (item.text || item.outcome || item.experience || '')).filter(Boolean);
    row.querySelectorAll(kind === 'outcome' ? '.ts-outcomes-list .ts-choice' : kind === 'experience' ? '.ts-experiences-list .ts-choice' : '.ts-questions-list .ts-choice').forEach(choice => {
      const input = choice.querySelector('.ts-choice-text');
      if (texts.includes(input.value)) choice.querySelector('.ts-choice-check').checked = true;
      else choice.remove();
    });
    texts.forEach(text => {
      const exists = [...row.querySelectorAll(kind === 'outcome' ? '.ts-outcomes-list .ts-choice-text' : kind === 'experience' ? '.ts-experiences-list .ts-choice-text' : '.ts-questions-list .ts-choice-text')].some(input => input.value === text);
      if (!exists) this.addPlanningChoice(row, kind, text);
    });
  },

  currentCurriculumRows(area) {
    const streamId = Number(document.getElementById('tsStream').value);
    return this.state.planningContext.curriculum?.[`${streamId}:${area}`] || [];
  },

  collectTeacherWorkbook() {
    return [...document.querySelectorAll('.ts-week')].map(week => ({ week_number: Number(week.dataset.week), items: [...week.querySelectorAll('.ts-item')].map(item => ({ strand_id: Number(item.querySelector('.ts-strand').value || 0), sub_strand_id: Number(item.querySelector('.ts-substrand').value || 0), title: item.querySelector('.ts-item-title').value.trim(), outcomes: [...item.querySelectorAll('.ts-outcomes-list .ts-choice')].filter(x => x.querySelector('.ts-choice-check').checked).map(x => ({ learning_outcome_id: Number(x.querySelector('.ts-choice-check').dataset.id || 0) || null, text: x.querySelector('.ts-choice-text').value.trim(), custom: !x.querySelector('.ts-choice-check').dataset.id })), experiences: [...item.querySelectorAll('.ts-experiences-list .ts-choice')].filter(x => x.querySelector('.ts-choice-check').checked).map(x => ({ suggested_experience_id: Number(x.querySelector('.ts-choice-check').dataset.id || 0) || null, text: x.querySelector('.ts-choice-text').value.trim(), custom: !x.querySelector('.ts-choice-check').dataset.id })), questions: [...item.querySelectorAll('.ts-questions-list .ts-choice')].filter(x => x.querySelector('.ts-choice-check').checked).map(x => ({ text: x.querySelector('.ts-choice-text').value.trim(), custom: true })), competency_ids: [...item.querySelectorAll('.ts-competencies-list .ts-choice-check:checked')].map(x => Number(x.dataset.id)), assessment_tool_ids: [...item.querySelectorAll('.ts-assessment-tools-list .ts-choice-check:checked')].map(x => Number(x.dataset.id)), rubric_ids: [...item.querySelectorAll('.ts-rubrics-list .ts-choice-check:checked')].map(x => Number(x.dataset.id)), assessment_rubric_ids: [...item.querySelectorAll('.ts-assessment-rubrics-list .ts-assessment-rubric-check:checked')].map(x => Number(x.dataset.id)) })) }));
  },

  async saveTeacherScheme(submit) {
    const c=this.state.planningContext; const stream=(c.streams||[]).find(s=>Number(s.academic_year_class_stream_id)===Number(document.getElementById('tsStream').value)); const area=Number(document.getElementById('tsArea').value); const weeks=this.collectTeacherWorkbook(); if(!stream||!area)return this.showNotification('Select the authorised stream and learning area first.','warning');
    if(submit){
      const incomplete=[];
      weeks.forEach(w=>{
        if(w.reserved) return;
        w.items.forEach(i=>{
          const started=Boolean(i.strand_id||i.sub_strand_id||i.title||i.outcomes.length||i.experiences.length||i.questions.length||i.competency_ids.length||i.assessment_tool_ids.length);
          if(started && (!i.strand_id||!i.sub_strand_id||!i.title||!i.outcomes.length||!i.experiences.length)) incomplete.push(`Week ${w.week_number}`);
        });
      });
      if(incomplete.length)return this.showNotification(`Complete the started curriculum row(s) in ${[...new Set(incomplete)].join(', ')} by selecting a strand, sub-strand, title, outcome and learning experience. Empty weeks may remain unused; reserved weeks are excluded.`,'warning');
    }
    const streamArea=stream.learning_areas.find(a=>Number(a.id)===area)?.stream_learning_area_id; const payload={workbook_id:this.state.workbookId||null,stream_learning_area_id:streamArea,academic_year_id:c.academic_year_id||null,academic_year_term_id:c.academic_year_term_id||null,title:document.getElementById('tsTitle').value.trim(),weeks}; try { const saved=await window.API.academic.saveSchemeWorkbook(payload); const d=saved?.data||saved||{}; this.state.workbookId=d.workbook_id||this.state.workbookId; if(submit){await window.API.academic.submitSchemeWorkbook({workbook_id:this.state.workbookId,class_id:stream.class_id,stream_id:stream.stream_id,learning_area_id:area}); bootstrap.Modal.getInstance(document.getElementById('teacherSchemeBuilder'))?.hide(); this.showNotification('Complete scheme submitted for review.','success'); await this.loadSchemes();}else{document.getElementById('tsSaveStatus').textContent='Progress saved — you can return later.';this.showNotification('Scheme progress saved.','success');}}catch(e){this.showNotification(e.message||'Unable to save scheme progress.','danger');}
  },

  async viewScheme(id) {
    try {
      const r=await window.API.academic.getSchemeOfWork(id); const s=r?.data||r||{};
      let modal=document.getElementById('schemeViewModal'); if(!modal){modal=document.createElement('div');modal.id='schemeViewModal';modal.className='modal fade';modal.innerHTML='<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Weekly Scheme Detail</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="schemeViewBody"></div></div></div>';document.body.appendChild(modal);}
      document.getElementById('schemeViewBody').innerHTML=`<div class="row g-3"><div class="col-md-6"><strong>Learning Area</strong><br>${this.escapeHtml(s.learning_area_name||s.subject_name||'')}</div><div class="col-md-6"><strong>Class / Stream</strong><br>${this.escapeHtml((s.class_name||'')+' · '+(s.stream_name||''))}</div><div class="col-md-4"><strong>Week</strong><br>${this.escapeHtml(s.week_number||'')}</div><div class="col-md-8"><strong>Strand / Sub-strand</strong><br>${this.escapeHtml((s.strand_name||'')+' / '+(s.sub_strand_name||''))}</div><div class="col-12"><strong>Focus</strong><p>${this.escapeHtml(s.title||'')}</p></div><div class="col-md-6"><strong>Learning experiences</strong><p>${this.escapeHtml(s.activities||'')}</p></div><div class="col-md-6"><strong>Resources</strong><p>${this.escapeHtml(s.resources||'')}</p></div><div class="col-12"><strong>Assessment</strong><p>${this.escapeHtml(s.assessment_methods||'')}</p></div></div>`;
      bootstrap.Modal.getOrCreateInstance(modal).show();
    } catch(e){this.showNotification(e.message||'Unable to view scheme','danger');}
  },

  async editScheme(id) {
    const scheme=this.state.schemes.find(s=>Number(s.id)===Number(id)); if(!scheme?.scheme_workbook_id)return this.showNotification('This legacy row has no editable workbook. Reconcile it first.','warning');
    if(scheme.workbook_status!=='draft')return this.showNotification('This term workbook has already been submitted and is now under review.','info');
    try {
      const r=await window.API.academic.getSchemeWorkbook(id); const book=r?.data||r||{};
      await this.openTeacherBuilder();
      const stream=this.state.planningContext.streams.find(s=>Number(s.academic_year_class_stream_id)===Number(scheme.academic_year_class_stream_id));
      if (!stream) throw new Error('The assigned stream is no longer in your current planning scope.');
      document.getElementById('tsStream').value=scheme.academic_year_class_stream_id;
      this.populateTeacherAreas();
      document.getElementById('tsArea').value=scheme.subject_id;
      this.state.planningContext.drafts=[book]; this.state.workbookId=book.id;
      this.renderTeacherWeeks();
      bootstrap.Modal.getOrCreateInstance(document.getElementById('teacherSchemeBuilder')).show();
    } catch(e){this.showNotification(e.message||'Unable to open workbook','danger');}
  },

  async editWorkbook(workbookId) {
    const draft = (this.state.planningContext?.drafts || []).find(d => Number(d.id) === Number(workbookId));
    if (!draft) return this.showNotification('Saved workbook draft was not found in the selected term.', 'warning');
    let stream = null; let area = null;
    (this.state.planningContext.streams || []).some(candidate => {
      const match = (candidate.learning_areas || []).find(a => Number(a.stream_learning_area_id) === Number(draft.academic_year_class_stream_learning_area_id));
      if (match) { stream = candidate; area = match; return true; }
      return false;
    });
    if (!stream || !area) return this.showNotification('This workbook is outside your current teacher scope.', 'warning');
    await this.openTeacherBuilder();
    document.getElementById('tsStream').value = stream.academic_year_class_stream_id;
    this.populateTeacherAreas();
    document.getElementById('tsArea').value = area.id;
    this.state.planningContext.drafts = [draft];
    this.state.workbookId = draft.id;
    this.renderTeacherWeeks();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('teacherSchemeBuilder')).show();
  },

  async viewWorkbook(workbookId) {
    let draft = (this.state.planningContext?.drafts || []).find(d => Number(d.id) === Number(workbookId));
    if (!draft) {
      const row = (this.state.schemes || []).find(s => Number(s.scheme_workbook_id) === Number(workbookId));
      if (!row?.id) return this.showNotification('The scheme workbook could not be found.', 'warning');
      try { const response = await window.API.academic.getSchemeWorkbook(row.id); draft = response?.data || response || {}; } catch (e) { return this.showNotification(e.message || 'Unable to load the scheme workbook.', 'danger'); }
    }
    const rows = (draft.payload || []).flatMap(w => (w.items || []).map(i => ({...i, week_number: w.week_number})));
    let modal = document.getElementById('schemeViewModal');
    if (!modal) { modal=document.createElement('div'); modal.id='schemeViewModal'; modal.className='modal fade'; modal.innerHTML='<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Saved Scheme Workbook</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="schemeViewBody"></div></div></div>'; document.body.appendChild(modal); }
    document.getElementById('schemeViewBody').innerHTML = `<div class="alert alert-info">This is a saved draft. Use Edit to continue completing all term weeks.</div><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Week</th><th>Strand / Sub-strand</th><th>Focus</th><th>Outcomes</th><th>Activities</th></tr></thead><tbody>${rows.map(r=>`<tr><td>Week ${r.week_number}</td><td>${this.escapeHtml((r.strand_name||r.strand_id||'')+' / '+(r.sub_strand_name||r.sub_strand_id||''))}</td><td>${this.escapeHtml(r.title||'')}</td><td>${this.escapeHtml(r.learning_outcomes||'')}</td><td>${this.escapeHtml(r.activities||'')}</td></tr>`).join('')}</tbody></table></div>`;
    bootstrap.Modal.getOrCreateInstance(modal).show();
  },

  async submitWorkbook(workbookId) {
    const draft = (this.state.planningContext?.drafts || []).find(d => Number(d.id) === Number(workbookId));
    if (!draft) return this.showNotification('Saved workbook draft was not found.', 'warning');
    if (!(await window.confirmAction('Confirm', 'Submit the complete multi-week workbook for approval?'))) return;
    const stream = (this.state.planningContext.streams || []).find(s => (s.learning_areas || []).some(a => Number(a.stream_learning_area_id) === Number(draft.academic_year_class_stream_learning_area_id)));
    const area = stream?.learning_areas.find(a => Number(a.stream_learning_area_id) === Number(draft.academic_year_class_stream_learning_area_id));
    try { await window.API.academic.submitSchemeWorkbook({workbook_id: draft.id, class_id: stream?.class_id, stream_id: stream?.stream_id, learning_area_id: area?.id}); this.showNotification('Complete term workbook submitted for approval.', 'success'); await this.loadSchemes(); } catch (e) { this.showNotification(e.message || 'Unable to submit workbook.', 'danger'); }
  },

  async requestRevision(workbookId) {
    const reason = await (window.promptAction?.('Request revision', 'Why is a revision needed?', 'Add or correct outcomes, experiences, competencies, assessment tools or rubrics') ?? Promise.resolve(null));
    if (reason === null) return;
    try {
      await window.API.academic.requestSchemeWorkbookRevision(workbookId, {reason});
      this.showNotification('A new editable revision draft has been created. The approved version remains unchanged.', 'success');
      await this.loadSchemes();
    } catch (e) { this.showNotification(e.message || 'Unable to create revision.', 'danger'); }
  },

  async submitForApproval(id) {
    const scheme=this.state.schemes.find(s=>Number(s.id)===Number(id)); if(!scheme?.scheme_workbook_id)return this.showNotification('This legacy row cannot be submitted until reconciled.','warning');
    if(scheme.workbook_status!=='draft')return this.showNotification('This workbook has already been submitted for review.','info');
    if (!(await window.confirmAction('Confirm', 'Submit the complete multi-week workbook for approval?'))) return;
    try {
      const r=await window.API.academic.getSchemeWorkbook(id);const book=r?.data||r||{};await window.API.academic.submitSchemeWorkbook({workbook_id:book.id,class_id:scheme.class_id,stream_id:scheme.stream_id,learning_area_id:scheme.subject_id});
      this.showNotification('Complete term workbook submitted for approval', 'success');
      await this.loadSchemes();
    } catch (error) {
      console.error('Error submitting scheme:', error);
      this.showNotification('Failed to submit scheme', 'error');
    }
  },

  exportSchemes() {
    if (!this.state.schemes.length) return;
    
    const headers = ['#', 'Learning Area', 'Class / Stream', 'Week', 'Strand / Sub-strand', 'Status', 'Progress', 'Last Updated'];
    const rows = this.state.schemes.map((scheme, i) => [
      i + 1,
      scheme.subject_name,
      scheme.class_name || '--',
      'Week ' + (scheme.week_number || '--'),
      (scheme.strand_name || '') + (scheme.sub_strand_name ? ' / ' + scheme.sub_strand_name : ''),
      scheme.status || 'Draft',
      scheme.progress || 0 + '%',
      scheme.updated_at ? new Date(scheme.updated_at).toLocaleDateString() : '--'
    ]);
    
    if (window.PrintManager) {
      window.PrintManager.exportToCSV({
        headers,
        rows
      }, 'my_schemes_of_work');
    } else {
      // Fallback
      let csv = headers.join(',') + '\n' + 
        rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
      
      KingswayFileLifecycle.exportText(csv, 'my_schemes_of_work.csv', 'text/csv');
    }
  },

  async refresh() {
    await this.loadSchemes();
  },

  escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  },

  showNotification(message, type = 'info') {
    if (typeof showNotification === 'function') {
      showNotification(message, type);
    } else {
      // Fallback notification
      const container = document.querySelector('.container-fluid') || document.body;
      const alert = document.createElement('div');
      alert.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
      alert.style.zIndex = '9999';
      alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
      container.appendChild(alert);
      setTimeout(() => alert.remove(), 4000);
    }
  }
};

document.addEventListener('DOMContentLoaded', () => MySchemesOfWorkController.init());

window.MySchemesOfWorkController = MySchemesOfWorkController;
