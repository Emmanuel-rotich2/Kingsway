/**
 * Year Transition / Rollover Controller
 * Drives the canonical 23-stage AcademicYearTransitionWorkflow:
 * All persistent stages are defined by migration 100. Preparation entered in
 * the create form records stages 1-4 atomically; the remaining stages remain
 * resumable and are displayed in their canonical order below.
 *
 * API:
 *   POST /academic/year-transition/start-workflow
 *   POST /academic/year-transition/setup-new-year
 *   POST /academic/year-transition/archive-data
 *   POST /academic/year-transition/execute-promotions
 *   POST /academic/year-transition/migrate-competency-baselines
 *   POST /academic/year-transition/validate-readiness
 *   PUT  /academic/years/set-current/{id}
 *   GET  /academic/workflow/status?workflow_type=year-transition&instance_id=
 */
const yearRolloverController = {

  _currentYear: null,
  _terms: [],
  _instanceId: null,
  _workflowData: null,
  _running: false,
  _log: [],
  _promotionCandidates: [],
  _currentStage: null,
  _promotionBoardLoaded: false,

  _stages: [
    ['confirm_current_year', 'Confirm current academic year', 'Verify the outgoing year and its current context.', 'bi-shield-check', 'primary'],
    ['create_next_year', 'Create/find immediate next year', 'Use the canonical YYYY/YYYY+1 year code.', 'bi-calendar-plus', 'primary'],
    ['enter_year_term_dates', 'Enter year and term dates', 'Record year, term and optional half-term dates.', 'bi-calendar2-range', 'primary'],
    ['generate_calendar', 'Generate calendar', 'Derive school weeks and calendar days from the dates.', 'bi-calendar-week', 'info'],
    ['configure_classes_streams', 'Configure classes and streams', 'Prepare the target classes and administrator-selected streams.', 'bi-diagram-3', 'info'],
    ['configure_learning_areas', 'Configure learning areas, strands, and substrands', 'Copy or configure CBC curriculum context for the target year.', 'bi-book', 'info'],
    ['configure_teachers', 'Configure class and subject teachers', 'Assign target-year class and subject teacher context.', 'bi-person-workspace', 'info'],
    ['prepare_fee_structures', 'Prepare fee structures', 'Copy fee structures as drafts for the target year.', 'bi-cash-stack', 'warning'],
    ['approve_fee_structures', 'Review and approve fee structures', 'Fee structures must be approved before billing readiness.', 'bi-check2-square', 'warning'],
    ['configure_operational_context', 'Configure events, timetable, transport, boarding, and assessments', 'Complete the remaining target-year operational setup.', 'bi-gear', 'info'],
    ['current_year_readiness', 'Complete current-year readiness checks', 'Check teaching, attendance, assessment and finance readiness.', 'bi-clipboard-check', 'warning'],
    ['close_current_year_terms', 'Close current-year terms', 'Close all outgoing-year terms before cutover.', 'bi-lock', 'secondary'],
    ['review_promotion_candidates', 'Review promotion candidates', 'Review each continuing learner and their target class.', 'bi-people', 'primary'],
    ['assign_promotion_decisions', 'Assign promotion decisions', 'Promote, retain, transfer, graduate or otherwise decide each learner.', 'bi-person-check', 'primary'],
    ['assign_target_streams', 'Assign each learner to a target class and stream', 'Assign in batches; saved progress can be resumed later.', 'bi-diagram-2', 'primary'],
    ['create_new_year_enrollments', 'Create new-year enrollments', 'Create target-year enrollment records without overwriting history.', 'bi-person-plus', 'info'],
    ['carry_forward_finances', 'Carry forward arrears, credits, and advance payments', 'Preserve and map old-year financial positions.', 'bi-arrow-down-up', 'warning'],
    ['generate_obligations', 'Generate new-year obligations', 'Generate current/future term obligations from approved fees.', 'bi-receipt', 'warning'],
    ['reconcile_balances', 'Reconcile balances', 'Validate payments, arrears, credits and advances.', 'bi-calculator', 'warning'],
    ['migrate_baselines', 'Migrate competency baselines', 'Carry CBC competency baselines into the new year.', 'bi-graph-up-arrow', 'primary'],
    ['archive_previous_year', 'Archive the previous year', 'Finalize the outgoing-year history and audit record.', 'bi-archive', 'secondary'],
    ['activate_new_year_term_one', 'Activate the new academic year and Term 1', 'Perform the final controlled cutover.', 'bi-play-circle', 'success'],
    ['begin_new_year_operations', 'Begin new-year operations', 'Open the new-year operating context.', 'bi-rocket-takeoff', 'success'],
  ].map(([code, label, desc, icon, color]) => ({ code, label, desc, icon, color })),

  _STORE_KEY: 'kingsway_yr_transition_instance',

  // ── Init ──────────────────────────────────────────────────────────────────
  init: async function () {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([this._loadStatus(), this._loadYears()]);
    await this._resumeInstance();
    this._bindHalfTermToggles();
    this._render();
  },

  _bindHalfTermToggles: function () {
    document.querySelectorAll('.yr-half-term-toggle').forEach(toggle => {
      const update = () => {
        const fields = document.getElementById(`yrT${toggle.dataset.term}HalfTermFields`);
        if (fields) fields.classList.toggle('d-none', !toggle.checked);
      };
      toggle.addEventListener('change', update);
      update();
    });
  },

  // ── Load data ─────────────────────────────────────────────────────────────
  _loadStatus: async function () {
    try {
      const r = await callAPI('/academic/year-rollover-status', 'GET');
      this._status = r?.data || r;
    } catch (e) {
      this._status = {};
    }
  },

  _loadYears: async function () {
    try {
      const r = await callAPI('/academic/years/list', 'GET');
      const years = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._years = years;
      this._currentYear = years.find(y => y.is_current) || years.find(y => (y.status || '').toLowerCase() === 'active') || years[0] || null;
      if (this._currentYear) {
        this._loadTermsForPrefill(this._currentYear.id);
      }
    } catch (e) {
      console.warn('Failed to load academic years:', e);
    }
  },

  _loadTermsForPrefill: async function (yearId) {
    try {
      // /academic/terms returns terms across ALL years (getAcademicTerms ignores
      // academic_year_id), so filter client-side on the `year` field.
      const r = await callAPI('/academic/terms', 'GET');
      const all = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._terms = all.filter(t => Number(t.year) === Number(yearId));
      this._prefillForm();
    } catch (e) {
      console.warn('Failed to load terms for prefill:', e);
    }
  },

  _resumeInstance: async function () {
    const stored = localStorage.getItem(this._STORE_KEY);
    if (!stored) return;
    try {
      const r = await callAPI(
        '/academic/workflow/status?workflow_type=year-transition&instance_id=' + stored,
        'GET',
      );
      const inst = r?.data || r;
      if (!inst || !inst.id) {
        localStorage.removeItem(this._STORE_KEY);
        return;
      }
      this._instanceId = inst.id;
      this._workflowData = inst.data || {};
      this._currentStage = this._canonicalStage(inst.current_stage_code || inst.current_stage || this._currentStage);
      this._importWorkflowLog();
    } catch (e) {
      console.warn('Could not resume transition instance:', e);
      localStorage.removeItem(this._STORE_KEY);
    }
  },

  _canonicalStage: function (code) {
    const legacy = {
      prepare_calendar: 'generate_calendar',
      setup_new_year: 'configure_operational_context',
      execute_promotions: 'assign_target_streams',
      migrate_baselines: 'migrate_baselines',
      archive_data: 'archive_previous_year',
      validate_readiness: 'activate_new_year_term_one',
    };
    return legacy[code] || code;
  },

  _prefillForm: function () {
    const cur = this._currentYear;
    if (!cur) return;
    const startYear = Number(String(cur.year_code || '').slice(0, 4));
    const toYear = startYear ? startYear + 1 : '';
    const set = (id, v) => { const el = document.getElementById(id); if (el && !el.value) el.value = v; };
    set('yrToYear', toYear);
  },

  // ── Rendering ─────────────────────────────────────────────────────────────
  _render: function () {
    this._renderPreflight();
    this._renderExistingYearWarning();

    const hasInstance = !!this._instanceId;
    const createForm = document.getElementById('yrCreateForm');
    const stageWrap = document.getElementById('yrStageWrap');
    if (createForm) createForm.classList.toggle('d-none', hasInstance);
    if (stageWrap) stageWrap.classList.toggle('d-none', !hasInstance);

    const info = document.getElementById('yrWfInfo');
    if (info) {
      info.textContent = this._instanceId
        ? `#${this._instanceId} · ${this._workflowData?.from_year || ''} → ${this._workflowData?.to_year || ''}`
        : '';
    }

    if (hasInstance) {
      this._renderStages();
      if (['review_promotion_candidates', 'assign_promotion_decisions', 'assign_target_streams'].includes(this._currentStage) && !this._promotionBoardLoaded) {
        this.loadPromotionBoard().catch(e => showNotification(e.message || 'Could not load promotion assignments.', 'danger'));
      }
    }
    this._renderLog();
  },

  _renderPreflight: function () {
    const s = this._status || {};
    const badge = document.getElementById('yrCurrentYearBadge');
    if (badge) badge.textContent = s.current_year?.year_name || this._currentYear?.year_name || '—';

    const setCheck = (iconId, statusId, ok, trueText, falseText) => {
      const icon = document.getElementById(iconId);
      const stat = document.getElementById(statusId);
      if (icon) { icon.style.color = ok ? 'green' : 'red'; icon.className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + ' fs-3 mb-1'; }
      if (stat) stat.textContent = ok ? trueText : falseText;
    };

    setCheck('yrTermsIcon', 'yrTermsStatus', s.all_terms_complete, 'All Closed', 'Incomplete Terms');
    setCheck('yrResultsIcon', 'yrResultsStatus', (s.pending_results || 0) === 0, 'Finalised', `${s.pending_results || 0} Pending`);
    setCheck('yrPromotionsIcon', 'yrPromotionsStatus', (s.pending_promotions || 0) === 0, 'Done', `${s.pending_promotions || 0} Pending`);

    const feesIcon = document.getElementById('yrFeesIcon');
    const feesStatus = document.getElementById('yrFeesStatus');
    if (feesIcon) {
      feesIcon.style.color = (s.students_with_fees || 0) > 0 ? 'orange' : 'green';
      feesIcon.className = 'bi bi-cash-coin fs-3 mb-1';
    }
    if (feesStatus) feesStatus.textContent = (s.students_with_fees || 0) > 0 ? `${s.students_with_fees} students` : 'All Cleared';

    const notReady = document.getElementById('yrNotReady');
    if (notReady) notReady.classList.toggle('d-none', !!s.all_terms_complete);
  },

  _renderExistingYearWarning: function () {
    const el = document.getElementById('yrExistingYear');
    if (!el) return;
    const startYear = this._currentYear ? Number(String(this._currentYear.year_code || '').slice(0, 4)) : null;
    const toYear = startYear ? startYear + 1 : null;
    const exists = this._years && this._years.some(y => Number(String(y.year_code || '').slice(0, 4)) === toYear);
    if (exists && !this._instanceId) {
      el.classList.remove('d-none');
    } else {
      el.classList.add('d-none');
    }
  },

  _renderStages: function () {
    const container = document.getElementById('yrStages');
    if (!container) return;

    const done = {};
    this._log.forEach(l => { if (l.status === 'completed') done[l.stage] = true; });

    const currentIndex = Math.max(0, this._stages.findIndex(s => s.code === this._currentStage));
    container.innerHTML = this._stages.map((stage, index) => {
      const completed = !!done[stage.code];
      const actionable = !completed && (stage.code === this._currentStage || (!this._currentStage && index === currentIndex));
      return `<div class="list-group-item d-flex align-items-center gap-3 py-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:42px;height:42px;background:var(--bs-${completed ? 'success' : stage.color}-bg-subtle)">
          <i class="bi ${stage.icon} text-${completed ? 'success' : stage.color} fs-5"></i>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold">${stage.label}</div>
          <div class="text-muted small">${stage.desc}</div>
        </div>
        <div class="flex-shrink-0">
          ${completed
            ? `<span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Done</span>`
            : !actionable
              ? `<span class="badge bg-light text-muted">Waiting</span>`
              : `<button class="btn btn-sm btn-${stage.color}" onclick="yearRolloverController.runStage('${stage.code}')">
                   <i class="bi bi-play-fill me-1"></i>Run
                 </button>`
          }
        </div>
      </div>`;
    }).join('');
  },

  _renderLog: function () {
    const tbody = document.getElementById('yrLogBody');
    if (!tbody) return;
    if (!this._log.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No transition activity yet.</td></tr>';
      return;
    }
    const colors = { completed: 'success', failed: 'danger', in_progress: 'warning', pending: 'secondary' };
    tbody.innerHTML = this._log.map(l => `<tr>
      <td>${this._esc(l.stage)}</td>
      <td><span class="badge bg-${colors[l.status] || 'secondary'}">${l.status || '—'}</span></td>
      <td class="small">${this._esc(l.details)}</td>
      <td class="small">${l.performed ? new Date(l.performed).toLocaleString('en-KE') : '—'}</td>
    </tr>`).join('');
  },

  // ── Stage 1: create new year ──────────────────────────────────────────────
  createNewYear: async function () {
    if (this._running) return;
    const toYear = String(document.getElementById('yrToYear')?.value || '').trim();
    const startDate = document.getElementById('yrStartDate')?.value;
    const endDate = document.getElementById('yrEndDate')?.value;
    const msg = document.getElementById('yrCreateMsg');

    if (!/^\d{4}(\/\d{4})?$/.test(toYear) || !startDate || !endDate) {
      if (msg) msg.textContent = 'New year code, start and end dates are required.';
      showNotification('New year code, start and end dates are required.', 'warning');
      return;
    }
    const terms = [1, 2, 3].map(n => {
      const hasHalfTerm = !!document.getElementById(`yrT${n}HasHalfTerm`)?.checked;
      return {
        term_name: `Term ${n}`,
        start_date: document.getElementById(`yrT${n}Start`)?.value,
        end_date: document.getElementById(`yrT${n}End`)?.value,
        has_half_term: hasHalfTerm,
        half_term_start: hasHalfTerm ? (document.getElementById(`yrT${n}HalfStart`)?.value || null) : null,
        half_term_end: hasHalfTerm ? (document.getElementById(`yrT${n}HalfEnd`)?.value || null) : null,
      };
    });
    if (terms.some(t => !t.start_date || !t.end_date)) {
      showNotification('Please provide start and end dates for all three terms.', 'warning');
      return;
    }
    if (terms.some(t => t.has_half_term && (!t.half_term_start || !t.half_term_end))) {
      showNotification('Provide both half-term dates or leave the half-term option disabled.', 'warning');
      return;
    }

    this._running = true;
    if (msg) msg.textContent = 'Creating new year and generating calendar…';
    try {
      const data = await callAPI('/academic/year-transition/start-workflow', 'POST', {
          from_year: String(this._currentYear?.year_code || ''),
        to_year: toYear,
        year_start_date: startDate,
        year_end_date: endDate,
        terms,
        transition_notes: `Rollover from ${this._currentYear?.year_code} prepared by ${AuthContext.getUser?.().username || 'admin'}`,
      });
      this._instanceId = data.instance_id;
      this._workflowData = data.workflow_data || {};
      this._currentStage = 'generate_calendar';
      localStorage.setItem(this._STORE_KEY, String(this._instanceId));
      this._log.push({ stage: 'generate_calendar', status: 'completed', details: `${data.terms_created} terms created · calendar generated`, performed: new Date().toISOString() });
      showNotification(`New academic year ${toYear} created with ${data.terms_created} terms.`, 'success');
      await this._loadStatus();
      this._render();
    } catch (e) {
      if (msg) msg.textContent = '';
      showNotification(e.message || 'Failed to create the new academic year.', 'danger');
    } finally {
      this._running = false;
    }
  },

  // ── Stages 2-6 ────────────────────────────────────────────────────────────
  runStage: async function (code) {
    if (this._running) return;
    const stage = this._stages.find(s => s.code === code);
    const confirmMsg = `Run stage: "${stage?.label || code}"?\n\nThis action modifies database records.`;
    if (!await window.confirmAction('Confirm', confirmMsg)) return;

    this._running = true;
    this._setRunning(code, true);
    try {
      let data = null;
      if (code === 'configure_classes_streams') {
        data = await callAPI('/academic/year-transition/setup-new-year', 'POST', {
          instance_id: this._instanceId,
          from_year: String(this._workflowData?.from_year_code || this._workflowData?.from_year || this._currentYear?.year_code || ''),
          capacity: 40,
          default_ecd_streams: 2,
        });
        this._currentStage = 'approve_fee_structures';
        const la = data.learning_area_setup || {};
        this._log.push({
          stage: code, status: 'completed',
          details: `${data.classes_created} classes · ${(la.classes || []).length} classes with learning areas`,
          performed: new Date().toISOString(),
        });
        showNotification(`New year structure created: ${data.classes_created} classes (mode: ${data.mode}).`, 'success');
      } else if (['approve_fee_structures', 'configure_operational_context', 'current_year_readiness', 'close_current_year_terms', 'carry_forward_finances', 'generate_obligations', 'reconcile_balances'].includes(code)) {
        data = await callAPI('/academic/year-transition/complete-stage', 'POST', {
          instance_id: this._instanceId,
          stage_code: code,
        });
        this._currentStage = data.current_stage || this._currentStage;
        const financeDetails = data.finance_rollover?.[code] || data[code] || {};
        this._log.push({ stage: code, status: 'completed', details: financeDetails.learners_recorded != null
          ? `${financeDetails.learners_recorded} learner balances recorded.`
          : financeDetails.learners_reconciled != null ? `${financeDetails.learners_reconciled} learner balances reconciled.`
            : financeDetails.fee_bundles_processed != null ? `${financeDetails.fee_bundles_processed} fee bundles processed.`
              : 'Stage completed and progress saved.', performed: new Date().toISOString() });
        showNotification(`${stage?.label || code} completed.`, 'success');
      } else if (code === 'archive_previous_year') {
        data = await callAPI('/academic/year-transition/archive-data', 'POST', {
          instance_id: this._instanceId,
          archive_assessments: true, archive_attendance: true, archive_reports: true, archive_competencies: true,
        });
        this._currentStage = 'activate_new_year_term_one';
        this._log.push({
          stage: code, status: 'completed',
          details: `${data.assessments_archived ?? 0} assessments · ${data.attendance_records_archived ?? 0} attendance · ${data.competency_records_archived ?? 0} competencies`,
          performed: new Date().toISOString(),
        });
        showNotification('Previous year data archived.', 'success');
      } else if (['review_promotion_candidates', 'assign_promotion_decisions', 'assign_target_streams'].includes(code)) {
        await this.loadPromotionBoard();
        this._running = false;
        this._setRunning(code, false);
        return;
      } else if (code === 'migrate_baselines') {
        data = await callAPI('/academic/year-transition/migrate-competency-baselines', 'POST', {
          instance_id: this._instanceId,
        });
        this._currentStage = 'archive_previous_year';
        this._log.push({
          stage: 'migrate_baselines', status: 'completed',
          details: `${data.total_baselines ?? 0} baselines · ${data.students_tracked ?? 0} students · ${data.competencies_tracked ?? 0} competencies`,
          performed: new Date().toISOString(),
        });
        showNotification(`Migrated ${data.total_baselines ?? 0} competency baselines.`, 'success');
      } else if (code === 'activate_new_year_term_one') {
        await this._validateAndActivate();
        await this._loadStatus();
        this._running = false;
        this._setRunning(code, false);
        this._render();
        return;
      }
      this._renderStages();
      this._renderLog();
    } catch (e) {
      // Readiness validation can legitimately fail on partial prep — show the
      // detailed results instead of a bare error.
      if (code === 'activate_new_year_term_one' && e.response?.data?.validation_results) {
        this._log.push({
          stage: 'validate_readiness', status: 'failed',
          details: this._validationSummary(e.response.data.validation_results),
          performed: new Date().toISOString(),
        });
      } else {
        this._log.push({ stage: code, status: 'failed', details: e.message || 'Stage failed', performed: new Date().toISOString() });
      }
      showNotification(e.message || `Stage "${code}" failed.`, 'danger');
      this._renderLog();
    } finally {
      this._running = false;
      this._setRunning(code, false);
    }
  },

  loadPromotionBoard: async function () {
    const res = await callAPI('/academic/year-transition/promotion-candidates?instance_id=' + encodeURIComponent(this._instanceId), 'GET');
    this._promotionCandidates = res.candidates || [];
    this._promotionBoardLoaded = true;
    const board = document.getElementById('yrPromotionBoard');
    if (board) board.classList.remove('d-none');
    const assigned = res.assigned || 0;
    const total = res.total || 0;
    const progress = document.getElementById('yrPromotionProgress');
    if (progress) progress.textContent = `${assigned} of ${total} learners assigned`;
    const body = document.getElementById('yrPromotionBoardBody');
    if (!body) return;
    body.innerHTML = this._promotionCandidates.map(c => {
      const options = (c.target_streams || []).map(s => `<option value="${this._esc(s.target_stream_id)}">${this._esc(s.stream_name)}</option>`).join('');
      return `<tr data-student-id="${this._esc(c.student_id)}">
        <td><div class="fw-semibold">${this._esc(c.student_name)}</div><div class="small text-muted">${this._esc(c.admission_no)}</div></td>
        <td>${this._esc(c.source_class_name)} / ${this._esc(c.source_stream_name)}</td>
        <td>${this._esc(c.target_class_name || 'Graduating / no target')}</td>
        <td>${c.target_class_id ? `<select class="form-select form-select-sm yr-target-stream"><option value="">Select stream…</option>${options}</select>` : '<span class="text-muted">Graduation</span>'}</td>
        <td>${c.assigned ? '<span class="badge bg-success">Assigned</span>' : '<span class="badge bg-warning text-dark">Pending</span>'}</td>
      </tr>`;
    }).join('') || '<tr><td colspan="5" class="text-center text-muted">No active learners require promotion.</td></tr>';
    this._promotionCandidates.forEach(c => {
      const row = body.querySelector(`tr[data-student-id="${CSS.escape(String(c.student_id))}"]`);
      const select = row?.querySelector('.yr-target-stream');
      if (select && c.target_enrollment_id) select.value = String(c.target_stream_id || '');
    });
  },

  savePromotionAssignments: async function () {
    if (this._running) return;
    const assignments = [];
    document.querySelectorAll('#yrPromotionBoardBody tr[data-student-id]').forEach(row => {
      const select = row.querySelector('.yr-target-stream');
      if (select && select.value) assignments.push({ student_id: Number(row.dataset.studentId), target_stream_id: Number(select.value) });
    });
    if (!assignments.length) { showNotification('Select at least one target stream before saving.', 'warning'); return; }
    this._running = true;
    try {
      const res = await callAPI('/academic/year-transition/assign-promotion-streams', 'POST', { instance_id: this._instanceId, assignments });
      showNotification(res.complete ? 'All promotion assignments are complete.' : 'Assignments saved. You can continue later.', 'success');
      this._log.push({ stage: 'assign_target_streams', status: res.complete ? 'completed' : 'in_progress', details: `${res.saved || assignments.length} saved · ${res.unassigned || 0} remaining`, performed: new Date().toISOString() });
      if (res.complete) this._currentStage = 'create_new_year_enrollments';
      await this.loadPromotionBoard();
      this._renderStages();
      this._renderLog();
    } catch (e) { showNotification(e.message || 'Could not save promotion assignments.', 'danger'); }
    finally { this._running = false; }
  },

  _validateAndActivate: async function () {
    const res = await callAPI('/academic/year-transition/validate-readiness', 'POST', {
      instance_id: this._instanceId,
    });
    const ready = !!res.ready_for_new_year;
    const checks = res.validation_results || {};
    if (!ready) {
      const err = new Error('New academic year is not ready yet');
      err.response = { data: { validation_results: checks } };
      throw err;
    }
    // Activate: switch the whole system to the new year.
    const targetCode = this._workflowData?.to_year_code || (this._workflowData?.to_year ? `${this._workflowData.to_year}/${Number(this._workflowData.to_year) + 1}` : '');
    const newYearId = this._workflowData?.academic_year_id || this._years?.find(y => String(y.year_code) === String(targetCode))?.id;
    if (newYearId) {
      await callAPI(`/academic/years/set-current/${newYearId}`, 'PUT');
    }
    this._log.push({
      stage: 'activate_new_year_term_one', status: 'completed',
      details: 'All checks passed · Term 1 activated · system switched to new year',
      performed: new Date().toISOString(),
    });
    if (window.AcademicContext) await window.AcademicContext.refresh();
    if (window.DataStore) window.DataStore.invalidateMany(['academic', 'attendance', 'finance']).catch(() => {});
    showNotification('New academic year is now active! Term 1 is current.', 'success');

    // Workflow is complete — clear the persisted instance so the page returns
    // to a clean, ready-to-create state on next load.
    this._instanceId = null;
    this._workflowData = null;
    localStorage.removeItem(this._STORE_KEY);
  },

  _validationSummary: function (checks) {
    const fails = [];
    for (const [key, check] of Object.entries(checks || {})) {
      if (check && check.status === 'fail') {
        fails.push(key.replace(/_/g, ' '));
      }
    }
    return fails.length ? 'Not ready — fix: ' + fails.join(', ') : 'All checks passed';
  },

  _setRunning: function (code, running) {
    document.querySelectorAll('#yrStages .btn').forEach(b => { if (b && !b.disabled) b.disabled = running; });
  },

  _importWorkflowLog: function () {
    const d = this._workflowData || {};
    if (d.archive_summary) this._log.push({ stage: 'archive_previous_year', status: 'completed', details: 'Previously archived', performed: d.archive_summary.archived_at || null });
    if (d.promotion_summary) {
      const complete = d.promotion_summary.assignment_complete === true;
      this._log.push({ stage: 'assign_target_streams', status: complete ? 'completed' : 'in_progress', details: complete ? 'All learner streams assigned' : `${d.promotion_summary.unassigned ?? 'Some'} learners still require stream assignment`, performed: null });
    }
    if (d.new_classes && d.new_classes.length) this._log.push({ stage: 'configure_classes_streams', status: 'completed', details: `${d.new_classes.length} classes`, performed: null });
    if (d.migrated_baselines) this._log.push({ stage: 'migrate_baselines', status: 'completed', details: 'Previously migrated', performed: null });
    if (d.ready_for_new_year) this._log.push({ stage: 'activate_new_year_term_one', status: 'completed', details: 'Already validated', performed: null });
  },

  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => yearRolloverController.init());

window.yearRolloverController = yearRolloverController;
