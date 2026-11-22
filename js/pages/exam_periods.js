/* Exam-period workflow UI. Migrated to use AgentTaskPanel with Node gateway for AI drafting.
 * Relationships and validation are enforced by the API.
 */

(() => {
  const $ = (id) => document.getElementById(id);
  const state = { terms: [], classes: [], periods: [], kinds: [], activePeriod: null, detail: null, aiPollTimer: null, importPollTimer: null, publishResultsPeriodId: null, publicationRecipients: [] };
  const esc = (value) => { const n = document.createElement('span'); n.textContent = String(value ?? ''); return n.innerHTML; };
  const payload = (response) => response?.data?.data ?? response?.data ?? response;
  const notify = (message, type = 'info') => window.API?.showNotification?.(message, type) || window.showNotification?.(message, type);
  const canManage = () => window.AuthContext?.hasAnyPermission?.(['academic_manage','academic_edit','academics_manage','academics_edit']) || ['System Administrator','School Administrator','Headteacher'].some((r) => window.AuthContext?.hasRole?.(r));
  const canViewResults = () => canManage() || ['Deputy Head - Academic','Deputy Head - Discipline'].some((r) => window.AuthContext?.hasRole?.(r)) || window.AuthContext?.hasAnyPermission?.(['assessments_view','academic_view']);
  const canViewSchoolResults = () => ['System Administrator','School Administrator','Headteacher','Deputy Head - Academic','Deputy Head - Discipline'].some((r) => window.AuthContext?.hasRole?.(r));
  const canPublishResults = () => ['System Administrator','School Administrator'].some((r) => window.AuthContext?.hasRole?.(r));
  const canImportExamDocuments = () => ['System Administrator','School Administrator'].some((r) => window.AuthContext?.hasRole?.(r));
  const termLabel = (term) => `${term.academic_year_name || `Academic year ${term.academic_year_id}`} · Term ${term.term_id}`;
  const printScopeTitles = {
    'exam-workspace': 'Examination Workspace',
    'exam-preview': 'Exam Document Preview',
    'exam-periods': 'Exam Periods',
  };
  function printScope(scope) {
    window.PrintManager?.printPage({ title: printScopeTitles[scope] || 'Examination Workspace', orientation: 'landscape' }) ?? window.print();
  }

  function renderAiTimetableProposal(container, result) {
    if (!container) return;
    const draft = result?.draft && typeof result.draft === 'object' ? result.draft : {};
    const body = result?.answer?.body || result?.body || draft.body || '';
    const nextSteps = result?.next_steps || draft.next_steps || [];
    const alert = document.createElement('div');
    alert.className = 'alert alert-info py-2 mb-0';
    const title = document.createElement('strong');
    title.textContent = result?.answer?.title || result?.title || draft.title || 'AI timetable proposal';
    alert.appendChild(title);
    if (body) {
      const content = document.createElement('div');
      content.textContent = body;
      alert.appendChild(content);
    }
    if (Array.isArray(nextSteps) && nextSteps.length) {
      const list = document.createElement('ul');
      list.className = 'mb-0 mt-1';
      nextSteps.forEach((step) => {
        const item = document.createElement('li');
        item.textContent = String(step ?? '');
        list.appendChild(item);
      });
      alert.appendChild(list);
    }
    container.replaceChildren(alert);
  }

  async function call(path, method = 'GET', body = null, query = null) {
    return payload(await window.API.apiCall(path, method, body, query));
  }
  // Published grading systems for the exam forms (each exam keeps its own).
  async function loadGradingSystems() {
    if (!state.gradingSystems) {
      try { state.gradingSystems = (payload(await call('/academic/grading-systems')) || {}).systems || []; }
      catch (_) { state.gradingSystems = []; }
    }
    ['examPeriodGradingSystem', 'examPeriodEditGradingSystem'].forEach((id) => {
      const sel = $(id); if (!sel) return;
      const keep = sel.value;
      sel.innerHTML = '<option value="">The term\'s grading system</option>' + state.gradingSystems
        .map((g) => `<option value="${Number(g.id)}">${esc(g.name)} (${Number(g.levels_count)} levels)</option>`).join('');
      sel.value = keep;
    });
  }
  async function loadOptions(termId = '') {
    const data = await call('/academic/exam-periods-options', 'GET', null, termId ? { term_id: termId } : null) || {};
    state.terms = data.terms || [];
    state.classes = data.classes || [];
    if ($('examPeriodTerm')) {
      const selected = String(data.selected_term?.id || termId || '');
      $('examPeriodTerm').innerHTML = state.terms.map((term) => `<option value="${Number(term.id)}">${esc(termLabel(term))}</option>`).join('');
      if (selected) $('examPeriodTerm').value = selected;
      const term = state.terms.find((item) => String(item.id) === $('examPeriodTerm').value);
      if (term) {
        $('examPeriodStart').min = term.opening_date || '';
        $('examPeriodStart').max = term.closing_date || '';
        $('examPeriodEnd').min = term.opening_date || '';
        $('examPeriodEnd').max = term.closing_date || '';
      }
    }
    await loadAssessmentKinds();
  }

  // Summative exam types (CA / SBA / SA) come from assessment_type_classifications
  // via /academic/assessment-types?filter=summative — DB-driven, never hardcoded.
  // The dropdown VALUE is the classification id; SA implies the national authority
  // (KNEC-administered), CA/SBA stay school-administered.
  async function loadAssessmentKinds() {
    const selects = [$('examPeriodAssessmentKind'), $('examPeriodEditAssessmentKind')].filter(Boolean);
    if (!selects.length) return;
    try {
      const kinds = await call('/academic/assessment-types?filter=summative', 'GET') || [];
      if (!Array.isArray(kinds) || !kinds.length) throw new Error('No assessment types returned');
      state.kinds = kinds;
      const options = kinds.map((k) => `<option value="${Number(k.id)}" data-code="${esc(k.code)}" title="${esc(k.description || '')}">${esc(k.name)}</option>`).join('');
      selects.forEach((sel) => {
        const current = sel.value;
        sel.innerHTML = options;
        if (current && [...sel.options].some((o) => o.value === current)) sel.value = current;
      });
      selects.forEach((sel) => syncNationalWrap(sel));
    } catch (error) {
      selects.forEach((sel) => { sel.innerHTML = '<option value="">Unable to load assessment types</option>'; });
    }
  }

  function kindCode(selectEl) {
    const opt = selectEl && selectEl.selectedOptions && selectEl.selectedOptions[0];
    return opt ? opt.dataset.code || '' : '';
  }

  function syncNationalWrapper(selectEl) {
    if (!selectEl) return;
    const wrap = selectEl.id === 'examPeriodEditAssessmentKind' ? $('examPeriodEditNationalWrap') : $('nationalAssessmentWrap');
    if (wrap) wrap.classList.toggle('d-none', kindCode(selectEl) !== 'SA');
  }

  function assessmentPayloadFromKind(selectEl) {
    const id = selectEl ? Number(selectEl.value) || null : null;
    const code = kindCode(selectEl);
    return {
      assessment_type_classification_id: id,
      // Authority dimension derived from the type: SA = KNEC national;
      // CA and SBA are administered by the school.
      assessment_kind: code === 'SA' ? 'national' : 'school_based',
    };
  }
  function renderClasses(rootId = 'examPeriodClasses', selectedIds = []) {
    const root = $(rootId);
    if (!root) return;
    const selected = new Set(selectedIds.map(Number));
    const toggleId = rootId === 'examPeriodClasses' ? 'examPeriodToggleClasses' : 'examPeriodEditToggleClasses';
    root.innerHTML = state.classes.length ? state.classes.map((classRow) => {
      const areas = (classRow.learning_areas || []).map((area) => area.name).join(', ');
      const checked = selected.has(Number(classRow.academic_year_class_id)) ? ' checked' : '';
      return `<div class="col-md-6"><label class="form-check border rounded p-2 h-100"><input class="form-check-input me-2 exam-period-class" type="checkbox" value="${Number(classRow.academic_year_class_id)}"${checked} ${areas ? '' : 'disabled'}><span class="fw-semibold">${esc(classRow.class_name)}</span><small class="d-block text-muted ms-4">${esc(areas || 'No configured learning areas')}</small></label></div>`;
    }).join('') : '<div class="text-muted">No classes with active streams are configured for this academic year.</div>';
    const toggle = $(toggleId);
    if (toggle) {
      const boxes = [...root.querySelectorAll('.exam-period-class:not(:disabled)')];
      toggle.textContent = boxes.length && boxes.every((box) => box.checked) ? 'Clear selection' : 'Select all';
    }
  }
  // Year and term shown in the list (AcademicYearFilter; "All terms" = 0).
  const scope = { yearId: 0, termId: 0, ready: false };
  async function mountScope() {
    const yearSelect = $('examPeriodYearFilter');
    if (!window.AcademicYearFilter || !yearSelect) return;
    await window.AcademicYearFilter.mount(yearSelect, {
      termSelect: $('examPeriodTermFilter'),
      allTermsLabel: 'All terms',
      onChange: (yearId, _year, termId) => {
        scope.yearId = Number(yearId || 0);
        scope.termId = Number(termId || 0);
        if (scope.ready) renderPeriods();
      },
    });
    scope.ready = true;
  }
  function inScope(p) {
    if (scope.yearId && Number(p.academic_year_id || 0) !== scope.yearId) return false;
    if (scope.termId && Number(p.academic_year_term_id || 0) !== scope.termId) return false;
    return true;
  }
  async function loadPeriods() {
    try {
      state.allPeriods = await call('/academic/exam-periods') || [];
      if (!Array.isArray(state.allPeriods)) state.allPeriods = state.allPeriods.items || [];
      // Deleted periods are GONE — never render them in the active list.
      state.allPeriods = state.allPeriods.filter((p) => !p.deleted_at);
      renderPeriods();
    } catch (error) { $('examPeriodsBody').innerHTML = `<tr><td colspan="10" class="text-danger">${esc(error.message || 'Unable to load exam periods.')}</td></tr>`; }
  }
  function renderPeriods() {
    const body = $('examPeriodsBody');
    state.periods = (state.allPeriods || []).filter(inScope);
    if (!state.periods.length) { body.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4">${(state.allPeriods || []).length ? 'No exam periods in the selected year and term.' : 'No exam periods yet.'}</td></tr>`; return; }
    body.innerHTML = state.periods.map((p) => {
      const resultCount = `${Number(p.approved_count || 0)} approved · ${Number(p.submitted_count || 0)} submitted`;
      const item = (action, icon, label, extra = '', disabled = false) => `<li><button class="dropdown-item${extra ? ` ${extra}` : ''}" type="button" data-period-action="${action}" data-id="${Number(p.id)}"${disabled ? ' disabled title="Schedule every learning area first"' : ''}><i class="bi ${icon} me-2"></i>${esc(label)}</button></li>`;
      const items = [];
      // Deleted periods are filtered out entirely — no restore action needed.
      items.push('<li><h6 class="dropdown-header">Timetable</h6></li>');
      items.push(item('schedule', 'bi-calendar-week', p.status === 'draft' ? 'Set timetable' : 'View timetable'));
      if (canManage()) items.push(item('papers', 'bi-folder2-open', 'Exam papers'));
      if (canManage()) {
        items.push(item('edit', 'bi-pencil-square', 'Edit details'));
        items.push(item('delete', 'bi-trash', 'Delete', 'text-danger'));
      }
      if (canManage() && ['published','results_open','moderation','completed'].includes(p.status)) items.push(item('reopen', 'bi-arrow-repeat', 'Reopen timetable'));
      if (canManage() && p.status === 'draft') items.push(item('publish', 'bi-send-check', 'Publish', '', Number(p.scheduled_count) < Number(p.learning_area_count)));
      if (canManage() && p.status === 'published') items.push(item('open', 'bi-unlock', 'Open results'));
      if (canManage() && String(p.assessment_kind || '') === 'national') items.push(item('national-timetable', 'bi-file-earmark-rule', 'KNEC timetable'));
      if (canViewSchoolResults() || canViewResults()) {
        items.push('<li><h6 class="dropdown-header">Results</h6></li>');
        if (canViewSchoolResults()) items.push(item('results', 'bi-clipboard-data', 'School results'));
        else items.push(item('my-results', 'bi-clipboard-check', 'My results'));
        if (canPublishResults() && !p.results_published_at && ['results_open','moderation','completed'].includes(p.status) && Number(p.submitted_count || 0) > 0) items.push(item('publish-results', 'bi-broadcast', 'Publish results', 'fw-semibold'));
      }
      const actions = `<div class="dropdown"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Actions for ${esc(p.title)}"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button><ul class="dropdown-menu dropdown-menu-end shadow-sm">${items.join('')}</ul></div>`;
      const classification = (state.kinds || []).find((k) => Number(k.id) === Number(p.assessment_type_classification_id));
      const assessmentType = classification ? classification.name
        : ({ school_based: 'School based', national: `National${p.national_assessment_code ? ` · ${p.national_assessment_code}` : ''}`, mock: 'Mock', other: 'Other' })[p.assessment_kind] || 'School based';
      return `<tr class="${p.deleted_at ? 'table-secondary' : ''}"><td class="fw-semibold">${esc(p.title)}${p.deleted_at ? ' <span class="badge text-bg-danger">deleted</span>' : ''}</td><td>${esc(assessmentType)}</td><td>${esc(p.academic_year_name || 'Academic year')} · Term ${Number(p.term_id || 0)}</td><td>${esc(p.starts_on)} – ${esc(p.ends_on)}</td><td>${Number(p.class_count || 0)}</td><td>${Number(p.learning_area_count || 0)}</td><td>${Number(p.scheduled_count || 0)} / ${Number(p.learning_area_count || 0)}</td><td>${esc(resultCount)}</td><td>${esc(String(p.status).replaceAll('_', ' '))}</td><td class="text-nowrap">${actions}</td></tr>`;
    }).join('');
  }
  async function showSchedule(periodId) {
    const data = await call(`/academic/exam-periods/${periodId}`);
    state.activePeriod = Number(periodId); state.detail = data;
    const p = data.period; const editable = canManage() && !p.deleted_at; const aiDraftable = editable && p.status === 'draft';
    const rows = (data.entries || []).map((entry) => `<tr data-area-id="${Number(entry.exam_period_class_learning_area_id)}"><td>${esc(entry.class_name)}</td><td>${esc(entry.learning_area_name)}</td><td><input type="date" min="${esc(p.starts_on)}" max="${esc(p.ends_on)}" class="form-control form-control-sm slot-date" value="${esc(entry.exam_date || '')}" ${editable ? '' : 'disabled'}></td><td><input type="time" class="form-control form-control-sm slot-start" value="${esc(String(entry.start_time || '').slice(0,5))}" ${editable ? '' : 'disabled'}></td><td><input type="time" class="form-control form-control-sm slot-end" value="${esc(String(entry.end_time || '').slice(0,5))}" ${editable ? '' : 'disabled'}></td><td><input type="number" min="1" step="0.5" class="form-control form-control-sm slot-marks" value="${esc(entry.max_marks || 100)}" ${editable ? '' : 'disabled'} data-year-write></td><td><input type="text" maxlength="100" class="form-control form-control-sm slot-venue" value="${esc(entry.venue || '')}" ${editable ? '' : 'disabled'}></td></tr>`).join('');
    const root = $('examPeriodWorkspace');
    root.querySelector('.modal-dialog').innerHTML = `<div class="modal-content"><div class="modal-header"><h4 class="modal-title fs-5">${esc(p.title)} · Timetable</h4><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="d-flex flex-wrap gap-2 mb-3">${editable ? `<button class="btn btn-sm btn-outline-primary" id="draftExamPeriodTimetable"><i class="bi bi-stars me-1"></i>Draft with AI</button><button class="btn btn-sm btn-primary" id="saveExamPeriodTimetable" data-year-write>${p.status === 'draft' ? 'Save draft' : 'Save timetable changes'}</button><span class="small text-muted" id="timetableLocalStatus">Progress autosaves on this device.</span>` : ''}<button class="btn btn-sm btn-outline-secondary" id="examTimetableCsv">Export CSV</button><button class="btn btn-sm btn-outline-secondary" id="examTimetablePrint">Print / PDF</button></div>${editable ? `<div class="border rounded p-3 mb-3 bg-light"><div class="fw-semibold mb-1">AI timetable draft</div><p class="small text-muted mb-2">AI proposes dates and sittings for your review. It will not save or publish the timetable. Early classes: up to two morning papers a day. Grades 4–9: up to three papers a day. The system checks assigned-teacher conflicts.</p><div class="row g-2 align-items-end"><div class="col-6 col-md-2"><label class="form-label small" for="examDayStart">Exam day starts</label><input type="time" class="form-control form-control-sm" id="examDayStart" required></div><div class="col-6 col-md-2"><label class="form-label small" for="examMorningEnd">Morning ends</label><input type="time" class="form-control form-control-sm" id="examMorningEnd" required></div><div class="col-6 col-md-2"><label class="form-label small" for="examDayEnd">Exam day ends</label><input type="time" class="form-control form-control-sm" id="examDayEnd" required></div><div class="col-6 col-md-2"><label class="form-label small" for="examPaperMinutes">Paper duration (min)</label><div class="col-6 col-md-2"><label class="form-label small" for="examBreakMinutes">Break duration (min)</label></div>` : ''}`;
    bootstrap.Modal.getOrCreateInstance(root).show();
    if (!aiDraftable) { $('examDayStart')?.closest('.border')?.classList.add('d-none'); $('draftExamPeriodTimetable')?.classList.add('d-none'); }
    const exportAllowed = typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
    const printAllowed = typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');
    $('examTimetableCsv').classList.toggle('d-none', !exportAllowed);
    $('examTimetablePrint').classList.toggle('d-none', !printAllowed);
    $('examTimetableCsv').onclick = () => { if (exportAllowed) exportTable('examTimetableTable', `${p.title}-timetable.csv`); };
    $('examTimetablePrint').onclick = () => { if (printAllowed) printScope('exam-workspace'); };
    $('draftExamPeriodTimetable')?.addEventListener('click', () => queueAiTimetableDraft(periodId, p));
    $('saveExamPeriodTimetable')?.addEventListener('click', async () => {
      const entries = [...root.querySelectorAll('tbody tr[data-area-id]')].map((tr) => ({ exam_period_class_learning_area_id: Number(tr.dataset.areaId), exam_date: tr.querySelector('.slot-date').value, start_time: tr.querySelector('.slot-start').value, end_time: tr.querySelector('.slot-end').value, max_marks: Number(tr.querySelector('.slot-marks').value), venue: tr.querySelector('.slot-venue').value })).filter((row) => row.exam_date && row.start_time && row.end_time);
      if (!entries.length) { notify('Complete at least one sitting before saving the draft. Your current edits remain saved on this device.', 'warning'); return; }
      try { await call(`/academic/exam-periods/${periodId}/timetable`, 'PUT', { entries }); localStorage.removeItem('exam-period-timetable-cache:' + periodId); notify('Exam timetable saved.', 'success'); await loadPeriods(); await showSchedule(periodId); }
      catch (error) { notify(error.message || 'Unable to save timetable.', 'error'); }
    });
  }
  async function queueAiTimetableDraft(periodId, period) {
    const status = $('examAiDraftStatus');
    const notes = $('examAiDraftNotes');
    const button = $('draftExamPeriodTimetable');
    const constraints = {
      day_start: $('examDayStart').value,
      morning_end: $('examMorningEnd').value,
      day_end: $('examDayEnd').value,
      paper_minutes: Number($('examPaperMinutes').value),
      break_minutes: Number($('examBreakMinutes').value),
    };
    if (!constraints.day_start || !constraints.morning_end || !constraints.day_end || constraints.paper_minutes < 1 || constraints.break_minutes < 0) {
      notify('Set the exam day times, paper duration, and break before requesting an AI draft.', 'error');
      return;
    }
    if (state.aiPollTimer) clearTimeout(state.aiPollTimer);
    button.disabled = true;
    status.textContent = 'Preparing exam papers and checking teacher assignments…';
    notes.classList.add('d-none');
    try {
      if (window.AI_FEATURES.shouldUseAgentTaskPanel('exam_periods')) {
        // Use AgentTaskPanel with SSE streaming
        if (!state.aiPanel) {
          const panelContainer = document.createElement('div');
          panelContainer.id = 'ai-timetable-draft-panel';
          $('examAiDraftStatus')?.parentNode?.appendChild(panelContainer);
          state.aiPanel = new window.AgentTaskPanel({
            container: panelContainer,
            tool: 'drafts.create',
            question: `Generate a complete exam timetable for ${period.title} with constraints: exam day starts at ${constraints.day_start}, morning ends at ${constraints.morning_end}, day ends at ${constraints.day_end}, paper duration ${constraints.paper_minutes} minutes, break ${constraints.break_minutes} minutes. Assign teachers and rooms, respecting no double-booking and assigned-teacher conflicts. Early classes: up to two morning papers a day for Grades 4-9. Three papers a day maximum.`,
            route: 'academics',
            module: 'exam_timetable',
            targetComponent: null,
            componentId: 'ai-exam-timetable-draft',
            onComplete: (result) => {
              const statusEl = $('examAiDraftStatus');
              const notesEl = $('examAiDraftNotes');
              if (statusEl) statusEl.textContent = 'Draft loaded into the timetable for your review. Nothing has been saved.';
              renderAiTimetableProposal(notesEl, result);
              notesEl.classList.remove('d-none');
              $('draftExamPeriodTimetable').disabled = false;
              notify('AI timetable draft ready!', 'success');
            },
            onError: (error) => {
              const statusEl = $('examAiDraftStatus');
              if (statusEl) statusEl.textContent = 'Unable to retrieve the AI timetable draft.';
              notify(error.message || 'Unable to retrieve the AI timetable draft.', 'error');
            },
          });
        }
        
        status.textContent = 'Preparing exam timetable draft…';
        notes.classList.add('d-none');
        state.aiPanel.question = `Generate a complete exam timetable for ${period.title} with constraints: exam day starts at ${constraints.day_start}, morning ends at ${constraints.morning_end}, day ends at ${constraints.day_end}, paper duration ${constraints.paper_minutes} minutes, break ${constraints.break_minutes} minutes. Assign teachers and rooms, respecting no double-booking and assigned-teacher conflicts. Early classes: up to two morning papers a day for Grades 4-9. Three papers a day maximum.`;
        
        await state.aiPanel.start();
      } else {
        // Legacy polling pattern
        await call('/academic/exam-periods-ai-timetable-draft-queue', 'POST', { period_id: periodId, ...constraints });
        status.textContent = 'Queued. Waiting for the background AI draft…';
        pollAiTimetableDraft(periodId, period, constraints, 0);
      }
    } catch (error) {
      status.textContent = 'Draft request could not be prepared.';
      notes.innerHTML = `<div class="alert alert-warning py-2 mb-0">${esc(error.message || 'Unable to request an AI timetable draft.')}</div>`;
      notes.classList.remove('d-none');
      button.disabled = false;
      notify('Review the AI draft message for the missing prerequisite.', 'error');
    }
  }
  async function pollAiTimetableDraft(periodId, period, constraints, attempt) {
    const status = $('examAiDraftStatus');
    const notes = $('examAiDraftNotes');
    const button = $('draftExamPeriodTimetable');
    try {
      if (window.AI_FEATURES.shouldUseAgentTaskPanel('exam_periods')) {
        // SSE already streaming via AgentTaskPanel, just show status
        if (status) status.textContent = 'Draft available via streaming. Review and save.';
        if (notes) notes.innerHTML = `<div class="alert alert-info py-2 mb-0"><strong>AI timetable proposal</strong>Draft available for review. Nothing has been saved.</div>`;
        notes.classList.remove('d-none');
        if (button) button.disabled = false;
        notify('AI draft loaded via streaming. Review the timetable and save it when ready.', 'success');
        return;
      }
      
      // Legacy polling pattern
      const result = await call('/academic/exam-periods-ai-timetable-drafts', 'GET', null, { period_id: periodId });
      if (result.status === 'pending' && attempt < 20) {
        if (status) status.textContent = `AI is preparing the draft… (${attempt + 1}/20)`;
        state.aiPollTimer = setTimeout(() => pollAiTimetableDraft(periodId, period, constraints, attempt + 1), 6000);
        return;
      }
      if (result.status === 'pending') {
        if (status) status.textContent = 'Still processing. Reopen this timetable later to retrieve the draft.';
        if (button) button.disabled = false;
        return;
      }
      if (!result.ready) {
        if (status) status.textContent = 'No complete timetable draft was produced.';
        if (notes) notes.innerHTML = `<div class="alert alert-warning py-2 mb-0">${(result.unresolved_constraints || []).map((item) => `<div>${esc(item)}</div>`).join('') || 'Review teacher assignments and the available dates or session settings, then request another draft.'}</div>`;
        notes.classList.remove('d-none');
        if (button) button.disabled = false;
        return;
      }
      const byArea = new Map((result.entries || []).map((entry) => [Number(entry.exam_period_class_learning_area_id), entry]));
      const tableRows = [...$('examTimetableTable').querySelectorAll('tbody tr[data-area-id]')];
      if (byArea.size !== tableRows.length || tableRows.some((row) => !byArea.has(Number(row.dataset.areaId)))) throw new Error('The AI draft did not contain exactly one sitting for every class learning area.');
      tableRows.forEach((row) => {
        const entry = byArea.get(Number(row.dataset.areaId));
        row.querySelector('.slot-date').value = entry.exam_date;
        row.querySelector('.slot-start').value = String(entry.start_time).slice(0, 5);
        row.querySelector('.slot-end').value = String(entry.end_time).slice(0, 5);
      });
      if (status) status.textContent = 'Draft loaded into the timetable for your review. Nothing has been saved.';
        renderAiTimetableProposal(notes, result);
      notes.classList.remove('d-none');
      if (button) button.disabled = false;
      notify('AI draft loaded. Review the timetable and save it when ready.', 'success');
    } catch (error) {
      if (status) status.textContent = 'Unable to retrieve the AI draft.';
      if (button) button.disabled = false;
      notify(error.message || 'Unable to retrieve the AI timetable draft.', 'error');
    }
  }
  async function queueDocumentPreview(event) {
    event.preventDefault();
    const file = $('examDocumentFile')?.files?.[0];
    if (!file) return notify('Choose a document to preview.', 'warning');
    if (file.size > 4 * 1024 * 1024) return notify('The document must be smaller than 4 MB.', 'error');
    const form = new FormData();
    form.append('file', file);
    form.append('document_kind', $('examDocumentKind').value);
    const button = $('queueExamDocumentPreview');
    const status = $('examDocumentImportStatus');
    const previewWrap = $('examDocumentPreviewWrap');
    button.disabled = true;
    previewWrap.classList.add('d-none');
    status.textContent = 'Uploading securely and queueing local extraction…';
    try {
      const queued = payload(await window.API.apiCall('/automation/exam-document-preview', 'POST', form, null, { isFile: true, checkPermission: false }));
      const jobId = Number(queued?.job_id || queued?.data?.job_id || 0);
      if (!jobId) throw new Error('The preview job was not queued.');
      status.textContent = 'Queued. Waiting for the local document extractor…';
      if (state.importPollTimer) clearTimeout(state.importPollTimer);
      await pollDocumentPreview(jobId, 0);
    } catch (error) {
      status.textContent = error.message || 'The document could not be queued.';
      notify(status.textContent, 'error');
    } finally {
      button.disabled = false;
    }
  }
  async function pollDocumentPreview(jobId, attempt) {
    const status = $('examDocumentImportStatus');
    const result = await call(`/automation/exam-document-preview/${jobId}`);
    if (!result?.preview) {
      if (['failed', 'dead', 'cancelled'].includes(String(result?.status))) throw new Error('Extraction failed. Check that the document is a valid, text-readable file.');
      if (attempt >= 30) { status.textContent = 'Still processing. Reopen this preview later and check its queue status.'; return; }
      status.textContent = `Local extraction is running… (${attempt + 1}/30)`;
      state.importPollTimer = setTimeout(() => pollDocumentPreview(jobId, attempt + 1).catch((error) => { status.textContent = error.message; }), 2000);
      return;
    }
    renderDocumentPreview(result.preview);
    status.textContent = `${Number(result.preview.row_count)} rows extracted. Suggested field matches are shown for review. Nothing has been saved; use the exam timetable or result-entry controls to make authorized changes.`;
  }
  function renderDocumentPreview(preview) {
    const table = $('examDocumentPreviewTable');
    const headers = Array.isArray(preview.headers) ? preview.headers : [];
    const rows = Array.isArray(preview.rows) ? preview.rows : [];
    table.tHead.innerHTML = `<tr>${headers.map((header) => `<th>${esc(header)}</th>`).join('')}</tr>`;
    table.tBodies[0].innerHTML = rows.slice(0, 80).map((row) => `<tr>${headers.map((_, index) => `<td>${esc(row[index] || '')}</td>`).join('')}</tr>`).join('');
    const mapping = preview.suggested_mapping || {};
    const mappingText = Object.entries(mapping).map(([field, header]) => `${field}: ${header}`).join(' · ');
    $('examDocumentSuggestedMapping').textContent = mappingText ? `Suggested matches: ${mappingText}` : 'No exact field names matched. Review the column headings and rows manually.';
    $('examDocumentSuggestedMapping').classList.remove('d-none');
    $('examDocumentPreviewWrap').classList.remove('d-none');
    $('examDocumentPreviewActions').classList.remove('d-none');
    const exportAllowed = typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
    const printAllowed = typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');
    $('examDocumentPreviewCsv').classList.toggle('d-none', !exportAllowed);
    $('examDocumentPreviewPrint').classList.toggle('d-none', !printAllowed);
    $('examDocumentPreviewCsv').onclick = () => {
      if (!exportAllowed) return;
      const csv = [headers, ...rows].map((row) => row.map((cell) => `"${String(cell ?? '').replaceAll('"', '""')}"`).join(',')).join('\r\n');
      window.KingswayFileLifecycle?.exportText?.(csv, 'exam-document-preview.csv', 'text/csv');
    };
    $('examDocumentPreviewPrint').onclick = () => { if (printAllowed) printScope('exam-preview'); };
  }

  /** Build the field -> column index mapping from the preview headers. */
  function previewMapping() {
    const headers = state.previewPayload?.headers || [];
    const find = (...needles) => headers.findIndex((header) => needles.some((needle) => String(header || '').toLowerCase().includes(needle)));
    return {
      item_no: find('item', 'no.') >= 0 ? find('item', 'no.') : null,
      paper_code: find('paper code', 'code') >= 0 ? find('paper code', 'code') : null,
      paper_name: find('paper', 'subject', 'learning area') >= 0 ? find('paper', 'subject', 'learning area') : null,
      date: find('date', 'day') >= 0 ? find('date', 'day') : null,
      start_time: find('start') >= 0 ? find('start') : null,
      end_time: find('end') >= 0 ? find('end') : null,
      duration: find('duration') >= 0 ? find('duration') : null,
      session: find('session') >= 0 ? find('session') : null,
    };
  }

  /** Show the save controls with the national periods as targets. */
  function renderNationalSaveControls() {
    const row = $('examDocumentSaveRow');
    const select = $('examDocumentTargetPeriod');
    if (!row || !select) return;
    const national = (state.allPeriods || []).filter((period) => String(period.assessment_kind || '') === 'national' && !period.deleted_at);
    if (!national.length) {
      row.classList.add('d-none');
      $('examDocumentSaveStatus').textContent = 'Create a national (KNEC) exam period first, then save this timetable to it.';
      return;
    }
    select.innerHTML = national.map((period) => `<option value="${Number(period.id)}">${esc(period.title)}${period.national_assessment_code ? ' (' + esc(period.national_assessment_code) + ')' : ''}</option>`).join('');
    row.classList.remove('d-none');
  }

  /** Save the reviewed grid to the selected national period. */
  async function saveReviewedTimetable() {
    const status = $('examDocumentSaveStatus');
    const periodId = Number($('examDocumentTargetPeriod')?.value || 0);
    if (!periodId) return notify('Choose the national exam period first.', 'warning');
    if (!state.previewPayload?.rows?.length) return notify('Extract and review a timetable first.', 'warning');
    const button = $('examDocumentSaveTimetable');
    button.disabled = true;
    status.textContent = 'Validating and saving the reviewed rows…';
    try {
      const saved = payload(await call('/academic/exam-period-timetable', 'POST', {
        exam_period_id: periodId,
        rows: state.previewPayload.rows,
        mapping: previewMapping(),
        filename: state.previewPayload.filename,
        source_format: (state.previewPayload.filename.split('.').pop() || '').toLowerCase(),
      }));
      status.textContent = `${Number(saved?.papers || 0)} papers saved${Array.isArray(saved?.warnings) && saved.warnings.length ? ` with ${saved.warnings.length} warning(s): ${saved.warnings.join(' ')}` : '.'}`;
      notify('National timetable saved.', 'success');
    } catch (error) {
      status.textContent = error.message || 'The timetable could not be saved.';
      notify(status.textContent, 'error');
    } finally {
      button.disabled = false;
    }
  }

  /** Generate internal sittings from the saved papers of the selected period. */
  async function generateNationalSittings() {
    const status = $('examDocumentSaveStatus');
    const periodId = Number($('examDocumentTargetPeriod')?.value || 0);
    if (!periodId) return notify('Choose the national exam period first.', 'warning');
    const button = $('examDocumentGenerateSittings');
    button.disabled = true;
    status.textContent = 'Generating sittings and registers from the KNEC papers…';
    try {
      const result = payload(await call('/academic/exam-period-timetable-sittings', 'POST', { exam_period_id: periodId }));
      const created = Number(result?.sittings_created || 0);
      const unmatched = Array.isArray(result?.unmatched_learning_areas) ? result.unmatched_learning_areas : [];
      status.textContent = `${created} sittings created.` + (unmatched.length ? ` Unmatched subjects (no sitting): ${unmatched.join('; ')}.` : '');
      notify(created ? 'Sittings created from the KNEC timetable.' : 'No sittings could be matched.', created ? 'success' : 'warning');
      await loadPeriods();
      renderPeriods();
    } catch (error) {
      status.textContent = error.message || 'Sittings could not be generated.';
      notify(status.textContent, 'error');
    } finally {
      button.disabled = false;
    }
  }

  /** View the saved KNEC papers for a period inside the import modal. */
  async function showNationalPapers(periodId) {
    const wrap = $('nationalPapersWrap');
    const table = $('nationalPapersTable');
    $('examDocumentImportStatus').textContent = '';
    bootstrap.Modal.getOrCreateInstance($('examDocumentImportModal')).show();
    try {
      const data = payload(await call('/academic/exam-period-timetable', 'GET', null, { exam_period_id: periodId }));
      const papers = Array.isArray(data?.papers) ? data.papers : [];
      if (!papers.length) {
        wrap.classList.add('d-none');
        $('examDocumentSaveStatus').textContent = 'Create a national (KNEC) timetable saved for this period yet. Upload and review one to create the paper schedule.';
        return;
      }
      table.tBodies[0].innerHTML = papers.map((paper) => `<tr><td>${esc(paper.item_no || '—')}</td><td>${esc(paper.paper_code || '—')}</td><td>${esc(paper.paper_name || '')}${paper.is_break ? ' <span class="badge bg-secondary">Break</span>' : ''}</td><td>${esc(paper.paper_date || '—')}</td><td>${esc(paper.start_time || '—')}</td><td>${esc(paper.end_time || '—')}</td><td>${esc(paper.duration_label || (paper.duration_minutes ? paper.duration_minutes + ' min' : '—'))}</td><td>${esc(paper.variant || '')}</td></tr>`).join('');
      wrap.classList.remove('d-none');
      $('examDocumentSaveStatus').textContent = `${papers.length} KNEC papers saved for this period.`;
    } catch (error) {
      notify(error.message || 'Unable to load the KNEC papers.', 'error');
    }
  }

  // ── Grading system per term (versioned bindings) ──────────────────────
  async function initGradingScope() {
    const form = $('gradingScopeForm');
    if (!form) return;
    const termSelect = $('gradingTermSelect');
    const systemSelect = $('gradingSystemSelect');
    try {
      const options = payload(await call('/academic/exam-periods-options')) || [];
      const terms = Array.isArray(options) ? options : (options.terms || []);
      termSelect.innerHTML = terms.map((term) => `<option value="${Number(term.id)}" ${String(term.status) === 'current' ? 'selected' : ''}>${esc(term.academic_year_name || '')} · Term ${Number(term.term_id || 0)}</option>`).join('');
    } catch (_) { /* the exam periods select keeps working without it */ }
    try {
      const data = payload(await call('/academic/grading-systems')) || {};
      const systems = Array.isArray(data.systems) ? data.systems : [];
      systemSelect.innerHTML = systems.map((system) => `<option value="${Number(system.id)}">${esc(system.name)} · ${Number(system.levels_count)}-level</option>`).join('');
      renderGradingBindings(Array.isArray(data.bindings) ? data.bindings : []);
    } catch (error) {
      $('gradingScopeStatus').textContent = error.message || 'Grading systems could not be loaded.';
    }
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const termId = Number(termSelect.value || 0);
      const systemId = Number(systemSelect.value || 0);
      if (!termId || !systemId) return;
      const button = $('gradingScopeSave');
      button.disabled = true;
      $('gradingScopeStatus').textContent = 'Saving the binding…';
      try {
        const result = payload(await call('/academic/grading-systems-binding', 'POST', { academic_year_term_id: termId, grading_system_id: systemId }));
        $('gradingScopeStatus').textContent = 'Bound. New exam periods for this term snapshot this system; completed exams keep theirs.';
        renderGradingBindings(Array.isArray(result?.bindings) ? result.bindings : []);
        notify('Grading system bound for the term.', 'success');
      } catch (error) {
        $('gradingScopeStatus').textContent = error.message || 'The binding could not be saved.';
        notify($('gradingScopeStatus').textContent, 'error');
      } finally {
        button.disabled = false;
      }
    });
    termSelect.addEventListener('change', () => refreshGradingBindings(Number(termSelect.value || 0)));
  }
  async function refreshGradingBindings(termId) {
    if (!termId) return;
    try {
      const data = payload(await call('/academic/grading-systems', 'GET', null, { term_id: termId })) || {};
      renderGradingBindings(Array.isArray(data.bindings) ? data.bindings : []);
    } catch (_) { /* non-fatal */ }
  }
  function renderGradingBindings(bindings) {
    const wrap = $('gradingBindingsWrap');
    const body = $('gradingBindingsTable')?.tBodies?.[0];
    if (!wrap || !body) return;
    if (!bindings.length) { wrap.classList.add('d-none'); return; }
    body.innerHTML = bindings.map((binding) => `<tr><td>${esc(binding.binding_scope)}</td><td>${esc(binding.class_name || 'Whole school')}</td><td>${esc(binding.learning_area_name || 'All')}</td><td>${esc(binding.system_name || '')}</td><td>${Number(binding.levels_count || 0)}</td></tr>`).join('');
    wrap.classList.remove('d-none');
  }
  async function showResults(periodId, mine = false, includeDeleted = false) {
    const data = await call(`/academic/exam-periods/${periodId}/${mine ? 'my-results' : 'results'}`, 'GET', null, includeDeleted ? { include_deleted: true } : null); const p = data.period;
    const root = $('examPeriodWorkspace');
    const reviewedAssessments = new Set();
    const canReview = mine && canReviewClassRegisters();
    const rows = (data.items || []).map((r) => {
      let reviewAction='';
      if (canReview && ['submitted','pending_approval'].includes(String(r.assessment_status)) && !reviewedAssessments.has(Number(r.assessment_id))) {
        reviewedAssessments.add(Number(r.assessment_id));
        reviewAction=`<button class="btn btn-sm btn-outline-success" data-register-review="${Number(r.assessment_id)}" data-review-action="approve">Approve</button> <button class="btn btn-sm btn-outline-warning" data-register-review="${Number(r.assessment_id)}" data-review-action="reject">Return</button>`;
      }
      return `<tr class="${r.result_deleted_at ? 'table-secondary' : ''}"><td>${esc(r.class_name)}${r.stream_name ? ` · ${esc(r.stream_name)}` : ''}</td><td>${esc(r.learning_area)}</td><td>${esc(r.admission_no)}</td><td>${esc(r.learner_name)}</td><td>${esc(r.marks_obtained ?? '—')}</td><td>${esc(r.max_marks)}</td><td>${esc(window.CbcLevels.format(r.grade))}</td><td>${esc(r.entry_status || 'Not entered')}</td><td>${esc(r.assessment_status)}</td>${canReview ? `<td>${reviewAction}</td>` : ''}${!mine && canManage() && r.result_id ? `<td>${r.result_deleted_at ? `<button class="btn btn-sm btn-outline-success" data-result-restore="${Number(r.result_id)}">Restore</button>` : `<button class="btn btn-sm btn-outline-primary" data-result-edit="${Number(r.result_id)}" data-marks="${esc(r.marks_obtained ?? '')}" data-entry="${esc(r.entry_status || 'present')}">Edit</button> <button class="btn btn-sm btn-outline-danger" data-result-delete="${Number(r.result_id)}">Delete</button>`}</td>` : ''}</tr>`;
    }).join('');
    root.querySelector('.modal-dialog').innerHTML = `<div class="modal-content"><div class="modal-header"><div><h4 class="modal-title fs-5">${esc(p.title)} · ${mine ? 'My teaching results' : 'School results'}</h4><small class="text-muted">${mine ? 'Results are limited to assigned streams and learning areas.' : 'Leadership view across selected classes and learning areas.'}</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="d-flex gap-2 mb-2"><button class="btn btn-sm btn-outline-secondary" id="examResultsCsv">Export CSV</button><button class="btn btn-sm btn-outline-secondary" id="examResultsPrint">Print / PDF</button>${!mine && canManage() ? `<button class="btn btn-sm btn-outline-warning" id="toggleDeletedResults">${includeDeleted ? 'Hide deleted results' : 'Show deleted results'}</button>` : ''}</div><div class="table-responsive"><table class="table table-sm table-striped" id="examPeriodResultsTable"><thead><tr><th>Class / stream</th><th>Learning area</th><th>Admission no.</th><th>Learner</th><th>Mark</th><th>Out of</th><th>Grade</th><th>Entry</th><th>Assessment</th>${canReview ? '<th>Class-teacher review</th>' : ''}${!mine && canManage() ? '<th>Admin actions</th>' : ''}</tr></thead><tbody>${rows || '<tr><td colspan="10" class="text-center text-muted">No results found for this period and teaching assignment.</td></tr>'}</tbody></table></div></div></div>`;
    bootstrap.Modal.getOrCreateInstance(root).show();
    const exportAllowed = typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
    const printAllowed = typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');
    $('examResultsCsv').classList.toggle('d-none', !exportAllowed);
    $('examResultsPrint').classList.toggle('d-none', !printAllowed);
    $('examResultsPrint').onclick = () => { if (printAllowed) printScope('exam-workspace'); };
    $('examResultsCsv').onclick = () => { if (exportAllowed) exportTable('examPeriodResultsTable', `${p.title}-results.csv`); };
    root.querySelectorAll('[data-register-review]').forEach((button)=>button.onclick=async()=>{const approve=button.dataset.reviewAction==='approve';const reason=approve?'Class teacher review approved':(prompt('Tell the subject teacher what needs correction:')||'').trim();if(!reason)return;try{await call(`/academic/${approve?'approve':'reject'}-assessment`,'POST',{assessment_id:Number(button.dataset.registerReview),reason});notify(approve?'Register approved for School Administrator publication.':'Register returned to the subject teacher.','success');await showResults(periodId,true);}catch(e){notify(e.message||'Unable to review register.','error');}});
    root.querySelectorAll('[data-result-edit]').forEach((button)=>button.onclick=async()=>{ const marks=prompt('Corrected marks',button.dataset.marks); if(marks===null)return; const entry_status=prompt('Result status: present, absent, or exempted',button.dataset.entry); if(!entry_status)return; try{await call(`/academic/exam-period-result/${button.dataset.resultEdit}`, 'PUT', {marks_obtained:marks,entry_status,reason:'School administrator correction'}); notify('Result corrected.','success'); await showResults(periodId,mine);}catch(e){notify(e.message||'Unable to update result.','error');} });
    root.querySelectorAll('[data-result-delete]').forEach((button)=>button.onclick=async()=>{if(!confirm('Delete this result record?'))return;try{await call(`/academic/exam-period-result/${button.dataset.resultDelete}`, 'DELETE');notify('Result record deleted.','success');await showResults(periodId,mine);}catch(e){notify(e.message||'Unable to delete result.','error');}});
    root.querySelectorAll('[data-result-restore]').forEach((button)=>button.onclick=async()=>{try{await call(`/academic/exam-period-result/${button.dataset.resultRestore}/restore`, 'POST', {});notify('Result record restored.','success');await showResults(periodId,mine,true);}catch(e){notify(e.message||'Unable to restore result.','error');}});
    $('toggleDeletedResults')?.addEventListener('click',()=>showResults(periodId,mine,!includeDeleted));
  }
  async function openEditModal(periodId) {
    const form = $('examPeriodEditForm'); if (!form) return;
    form.dataset.periodId = String(periodId);
    const setBusy = (busy) => { ['examPeriodEditTitle','examPeriodEditStart','examPeriodEditEnd','examPeriodEditAssessmentKind','examPeriodEditEntryMode','examPeriodEditNationalCode'].forEach((id) => { if ($(id)) $(id).disabled = busy; }); if ($('examPeriodEditClasses')) $('examPeriodEditClasses').classList.toggle('opacity-50', busy); };
    setBusy(true);
    $('examPeriodEditModalNote').textContent = 'Loading the current exam period details…';
    let detail = null;
    try {
      detail = await call(`/academic/exam-periods/${periodId}`, 'GET');
    } catch (error) {
      $('examPeriodEditModalNote').innerHTML = `<span class="text-danger">${esc(error.message || 'Unable to load this exam period.')}</span>`;
      setBusy(false);
      return;
    }
    const period = detail?.period || {};
    const termId = Number(period.academic_year_term_id || 0);
    try { await loadOptions(termId || ''); } catch (_) { state.classes = []; }
    const term = state.terms.find((t) => Number(t.id) === termId);
    $('examPeriodEditTerm').value = term ? termLabel(term) : `${period.academic_year_name || 'Academic year'} · Term ${Number(period.term_id || 0)}`;
    $('examPeriodEditTitle').value = period.title || '';
    $('examPeriodEditStart').value = period.starts_on || '';
    $('examPeriodEditEnd').value = period.ends_on || '';
    const min = term?.opening_date || ''; const max = term?.closing_date || '';
    ['examPeriodEditStart', 'examPeriodEditEnd'].forEach((fieldId) => { $(fieldId).min = min; $(fieldId).max = max; });
    const editKindSel = $('examPeriodEditAssessmentKind');
    if (editKindSel) editKindSel.value = String(period.assessment_type_classification_id || '');
    $('examPeriodEditEntryMode').value = period.entry_mode || 'timetable';
    $('examPeriodEditNationalCode').value = period.national_assessment_code || '';
    const national = kindCode(editKindSel) === 'SA';
    $('examPeriodEditNationalWrap').classList.toggle('d-none', !national);
    $('examPeriodEditNationalCode').required = national;
    await loadGradingSystems();
    const gradeSel = $('examPeriodEditGradingSystem');
    if (gradeSel) {
      gradeSel.value = period.grading_system_id ? String(period.grading_system_id) : '';
      const fixed = ['results_open', 'moderation', 'completed'].includes(String(period.status || '')) || !!period.results_published_at;
      gradeSel.disabled = fixed;
      gradeSel.dataset.original = gradeSel.value;
      const note = $('examPeriodEditGradingSystemNote');
      if (note) note.textContent = fixed ? 'Results are open for this exam, so its grading system is fixed.' : 'Can be changed until results open.';
    }
    renderClasses('examPeriodEditClasses', detail?.class_ids || period.academic_year_class_ids || []);
    const lockedClasses = Number(detail?.entries?.length || 0) > 0;
    $('examPeriodEditClassesHint').textContent = lockedClasses
      ? 'Sittings already exist for this period. You may add classes; removing a class that already holds results is blocked.'
      : 'Every active stream in the selected class receives each configured learning-area paper.';
    $('examPeriodEditModalNote').textContent = `Exam dates must remain within the academic term shown above. Timetable sittings themselves are managed from the timetable workspace.`;
    setBusy(false);
    bootstrap.Modal.getOrCreateInstance($('examPeriodEditModal')).show();
  }
  function openDeleteModal(periodId) {
    const row = (state.allPeriods || []).find((x) => Number(x.id) === periodId);
    if (!row) return;
    const form = $('examPeriodDeleteForm'); if (!form) return;
    form.dataset.periodId = String(periodId);
    $('examPeriodDeleteName').textContent = row.title || `Exam period #${periodId}`;
    bootstrap.Modal.getOrCreateInstance($('examPeriodDeleteModal')).show();
  }
  function exportTable(tableId, filename) {
    const rows = [...$(tableId).querySelectorAll('tr')].map((row) => [...row.cells].map((cell) => `"${cell.innerText.replaceAll('"','""')}"`).join(','));
    if (window.KingswayFileLifecycle?.exportText) window.KingswayFileLifecycle.exportText(rows.join('\r\n'), filename, 'text/csv');
    else notify('CSV export is unavailable on this page.', 'error');
  }
  async function attach() {
    const exportAllowed = typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
    const printAllowed = typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');
    if (!exportAllowed) { $('examPeriodsCsv')?.classList.add('d-none'); }
    if (!printAllowed) { $('examPeriodsPrint')?.classList.add('d-none'); }
    if (!canManage()) $('openExamPeriodCreate')?.classList.add('d-none');
    if (!canImportExamDocuments()) $('openExamDocumentImport')?.classList.add('d-none');
    $('openExamPeriodCreate')?.addEventListener('click', async () => { await loadOptions($('examPeriodTerm')?.value || ''); await loadGradingSystems(); bootstrap.Modal.getOrCreateInstance($('examPeriodModal')).show(); });
    $('openExamDocumentImport')?.addEventListener('click', () => { $('examDocumentPreviewWrap')?.classList.add('d-none'); $('examDocumentPreviewActions')?.classList.add('d-none'); $('examDocumentSuggestedMapping')?.classList.add('d-none'); $('examDocumentImportStatus').textContent = ''; $('examDocumentImportForm')?.reset(); bootstrap.Modal.getOrCreateInstance($('examDocumentImportModal')).show(); });
    $('examDocumentImportForm')?.addEventListener('submit', queueDocumentPreview);
    $('examDocumentSaveTimetable')?.addEventListener('click', saveReviewedTimetable);
    $('examDocumentGenerateSittings')?.addEventListener('click', generateNationalSittings);
    initGradingScope();
    $('examPeriodTerm')?.addEventListener('change', async (event) => loadOptions(event.target.value));
    $('examPeriodAssessmentKind')?.addEventListener('change', (event) => {
      const national = kindCode(event.target) === 'SA';
      $('nationalAssessmentWrap')?.classList.toggle('d-none', !national);
      if ($('nationalAssessmentCode')) $('nationalAssessmentCode').required = national;
    });
    $('examPeriodEditAssessmentKind')?.addEventListener('change', (event) => {
      const national = kindCode(event.target) === 'SA';
      $('examPeriodEditNationalWrap')?.classList.toggle('d-none', !national);
      $('examPeriodEditNationalCode').required = national;
    });
    const bindClassToggle = (buttonId, rootId) => {
      $(buttonId)?.addEventListener('click', () => {
        const root = $(rootId); if (!root) return;
        const boxes = [...root.querySelectorAll('.exam-period-class:not(:disabled)')];
        const select = boxes.some((box) => !box.checked);
        boxes.forEach((box) => { box.checked = select; });
        $(buttonId).textContent = select ? 'Clear selection' : 'Select all';
      });
    };
    bindClassToggle('examPeriodToggleClasses', 'examPeriodClasses');
    bindClassToggle('examPeriodEditToggleClasses', 'examPeriodEditClasses');
    $('examPeriodForm')?.addEventListener('submit', async (event) => {
      event.preventDefault(); const academic_year_class_ids = [...document.querySelectorAll('.exam-period-class:checked')].map((box) => Number(box.value));
      const startsOn = $('examPeriodStart').value;
      const endsOn = $('examPeriodEnd').value;
      if (!startsOn || !endsOn || startsOn > endsOn) {
        notify('Check the exam dates: the end date must be on or after the start date.', 'error');
        return;
      }
      const button = $('createExamPeriodBtn'); button.disabled = true;
      try {
        const kindPayload = assessmentPayloadFromKind($('examPeriodAssessmentKind'));
        await call('/academic/exam-periods', 'POST', { academic_year_term_id: Number($('examPeriodTerm').value), title: $('examPeriodTitle').value.trim(), starts_on: startsOn, ends_on: endsOn, kind: $('examPeriodKind').value, entry_mode: $('examPeriodEntryMode').value, ...kindPayload, assessment_authority: kindPayload.assessment_kind === 'national' ? 'KNEC' : null, national_assessment_code: $('nationalAssessmentCode')?.value || null, grading_system_id: $('examPeriodGradingSystem')?.value || null, academic_year_class_ids }); bootstrap.Modal.getInstance($('examPeriodModal'))?.hide(); event.currentTarget.reset(); $('nationalAssessmentWrap')?.classList.add('d-none'); await loadPeriods(); notify('Exam period created.', 'success');
      }
      catch (error) { notify(error.message || 'Unable to create exam period.', 'error'); }
      finally { button.disabled = false; }
    });
    $('examPeriodEditForm')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const id = Number($('examPeriodEditForm').dataset.periodId || 0);
      if (!id) return;
      const title = $('examPeriodEditTitle').value.trim();
      const starts_on = $('examPeriodEditStart').value;
      const ends_on = $('examPeriodEditEnd').value;
      if (!title || !starts_on || !ends_on || starts_on > ends_on) {
        notify('Check the exam dates: the end date must be on or after the start date.', 'error');
        return;
      }
      const academic_year_class_ids = [...$('examPeriodEditClasses').querySelectorAll('.exam-period-class:checked')].map((box) => Number(box.value));
      if (!academic_year_class_ids.length) {
        notify('Select at least one applicable class.', 'error');
        return;
      }
      const kindPayload = assessmentPayloadFromKind($('examPeriodEditAssessmentKind'));
      const button = $('saveExamPeriodEditBtn');
      if (button) button.disabled = true;
      try {
        const gradeSel = $('examPeriodEditGradingSystem');
        const gradingChange = gradeSel && !gradeSel.disabled && gradeSel.value && gradeSel.value !== gradeSel.dataset.original ? { grading_system_id: Number(gradeSel.value) } : {};
        await call(`/academic/exam-periods/${id}`, 'PUT', {
          ...gradingChange,
          title,
          starts_on,
          ends_on,
          entry_mode: $('examPeriodEditEntryMode')?.value || 'timetable',
          ...kindPayload,
          assessment_authority: kindPayload.assessment_kind === 'national' ? 'KNEC' : null,
          national_assessment_code: $('examPeriodEditNationalCode')?.value || null,
          academic_year_class_ids,
        });
        bootstrap.Modal.getInstance($('examPeriodEditModal'))?.hide();
        await loadPeriods();
        notify('Exam period updated.', 'success');
      } catch (error) {
        notify(error.message || 'Unable to update the exam period.', 'error');
      } finally {
        if (button) button.disabled = false;
      }
    });
    // Attach AI draft button handler
    $('draftExamPeriodTimetable')?.addEventListener('click', () => queueAiTimetableDraft(state.activePeriod, state.detail));
    // Row action menu. The buttons are re-rendered on every reload of the
    // period list, so one delegated listener on the table body owns them all.
    $('examPeriodsBody')?.addEventListener('click', onPeriodAction);
    $('notifyResultsParents')?.addEventListener('change', async (event) => {
      const options = $('resultsNotificationOptions');
      options?.classList.toggle('d-none', !event.target.checked);
      if (event.target.checked) await loadPublicationRecipients();
    });
    $('toggleResultsRecipients')?.addEventListener('click', (event) => {
      const boxes = [...document.querySelectorAll('#resultsRecipientsList [data-student-id]')];
      const select = boxes.some((box) => !box.checked);
      boxes.forEach((box) => { box.checked = select; });
      event.currentTarget.textContent = select ? 'Clear selection' : 'Select all';
    });
    $('confirmPublishResults')?.addEventListener('click', submitPublishResults);

    // Initial data load. The delegated listener above is registered first so
    // the very first render already responds to every action button.
    await Promise.all([loadOptions($('examPeriodTerm')?.value || ''), mountScope()]);
    await loadPeriods();
  }

  // ── Exam paper bank ─────────────────────────────────────────────────
  // Papers can be prepared any time before the exam: set by staff or bought
  // from a supplier, filed per class and learning area, approved, printed
  // and issued to streams. Nothing here needs results to be open.
  const PAPER_STATUS = { draft: 'Draft', submitted: 'Submitted', approved: 'Approved', printed: 'Printed', issued: 'Issued', archived: 'Archived' };
  const PAPER_KIND = { question_paper: 'Question paper', marking_scheme: 'Marking scheme', answer_sheet: 'Answer sheet', other: 'Other' };
  const fmtBytes = (n) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round((n || 0) / 1024))} KB`);

  async function showPapers(periodId, focusPaperId = null) {
    const root = $('examPeriodWorkspace');
    let data;
    try { data = await call('/academic/exam-papers', 'GET', null, { exam_period_id: periodId }); }
    catch (e) { notify(e.message || 'Unable to load exam papers.', 'error'); return; }
    const period = data.period || {};
    const classes = data.options?.classes || [];
    const papers = data.papers || [];
    const byClass = {};
    papers.forEach((pp) => { (byClass[pp.academic_year_class_id] ||= []).push(pp); });
    const classOptions = classes.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
    const statusChip = (st) => `<span class="badge ${({ draft: 'text-bg-light border', submitted: 'text-bg-warning', approved: 'text-bg-success', printed: 'text-bg-info', issued: 'text-bg-primary', archived: 'text-bg-secondary' })[st] || 'text-bg-light'}">${esc(PAPER_STATUS[st] || st)}</span>`;

    const paperRow = (pp) => {
      const files = (pp.files || []).map((f) => `<li class="d-flex align-items-center gap-2 small"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>
        <button type="button" class="btn btn-link btn-sm p-0" data-paper-download="${Number(f.id)}" data-name="${esc(f.original_name)}">${esc(f.original_name)}</button>
        <span class="text-muted">${esc(PAPER_KIND[f.kind] || f.kind)} · ${fmtBytes(Number(f.file_size_bytes))}</span>
        <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-auto" data-paper-file-delete="${Number(f.id)}" aria-label="Remove ${esc(f.original_name)}"><i class="bi bi-x-lg"></i></button></li>`).join('');
      const issues = (pp.issues || []).map((i) => `<li class="small text-muted">${esc(i.stream_name || 'Stream')}: ${Number(i.copies_issued)} copies${i.candidates_present !== null ? `, ${Number(i.candidates_present)} sat` : ''} · ${esc(String(i.issued_at || '').slice(0, 10))}</li>`).join('');
      const cls = classes.find((c) => Number(c.id) === Number(pp.academic_year_class_id));
      const streamOpts = (cls?.streams || []).map((st) => `<option value="${st.id}">${esc(st.name)}</option>`).join('');
      const source = pp.source === 'purchased'
        ? `Purchased${pp.supplier_name ? ` from ${esc(pp.supplier_name)}` : ''}${pp.purchase_cost ? ` · KES ${Number(pp.purchase_cost).toLocaleString('en-KE')}` : ''}${pp.purchase_reference ? ` · receipt ${esc(pp.purchase_reference)}` : ''}`
        : 'Set by school staff';
      return `<div class="border rounded-3 p-3 mb-2 ${Number(pp.id) === Number(focusPaperId) ? 'border-success' : ''}" data-paper="${Number(pp.id)}">
        <div class="d-flex flex-wrap justify-content-between gap-2">
          <div><div class="fw-semibold">${esc(pp.title)} ${statusChip(pp.status)}</div>
            <div class="small text-muted">${esc(pp.learning_area_name)} · Paper ${Number(pp.paper_number)}${pp.max_marks ? ` · out of ${Number(pp.max_marks)}` : ''}${pp.duration_minutes ? ` · ${Number(pp.duration_minutes)} min` : ''} · ${source}</div></div>
          <div class="d-flex gap-2 align-items-start">
            <select class="form-select form-select-sm" style="width:auto" data-paper-status="${Number(pp.id)}" aria-label="Paper status">
              ${Object.entries(PAPER_STATUS).map(([k, v]) => `<option value="${k}" ${pp.status === k ? 'selected' : ''}>${v}</option>`).join('')}
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-paper-edit="${Number(pp.id)}" aria-label="Edit ${esc(pp.title)}"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-paper-delete="${Number(pp.id)}" aria-label="Remove ${esc(pp.title)}"><i class="bi bi-trash"></i></button>
          </div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-lg-6">
            <div class="small fw-semibold mb-1">Files</div>
            <ul class="list-unstyled mb-2">${files || '<li class="small text-muted">No files yet.</li>'}</ul>
            <form class="d-flex gap-2 flex-wrap" data-paper-upload="${Number(pp.id)}">
              <select class="form-select form-select-sm" style="width:auto" name="kind" aria-label="File type">${Object.entries(PAPER_KIND).map(([k, v]) => `<option value="${k}">${v}</option>`).join('')}</select>
              <input class="form-control form-control-sm" style="max-width:240px" type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" aria-label="Paper file" required>
              <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-upload me-1"></i>Upload</button>
            </form>
          </div>
          <div class="col-lg-6">
            <div class="small fw-semibold mb-1">Issued${pp.copies_required ? ` · ${Number(pp.copies_issued_total || 0)} of ${Number(pp.copies_required)} copies` : ''}</div>
            <ul class="list-unstyled mb-2">${issues || '<li class="small text-muted">Not issued yet.</li>'}</ul>
            <form class="d-flex gap-2 flex-wrap" data-paper-issue="${Number(pp.id)}">
              <select class="form-select form-select-sm" style="width:auto" name="academic_year_class_stream_id" aria-label="Stream" required>${streamOpts}</select>
              <input class="form-control form-control-sm" style="width:90px" type="number" min="1" name="copies_issued" placeholder="Copies" aria-label="Copies issued" required>
              <input class="form-control form-control-sm" style="width:90px" type="number" min="0" name="candidates_present" placeholder="Sat" aria-label="Candidates who sat">
              <button class="btn btn-sm btn-outline-primary" type="submit">Record issue</button>
            </form>
          </div>
        </div>
      </div>`;
    };

    const folders = classes.map((c) => {
      const list = byClass[c.id] || [];
      return `<details class="mb-2" ${list.length || classes.length === 1 ? 'open' : ''}><summary class="fw-semibold py-1"><i class="bi bi-folder2 me-1 text-success" aria-hidden="true"></i>${esc(c.name)} <span class="text-muted small fw-normal">${list.length} ${list.length === 1 ? 'paper' : 'papers'}</span></summary>
        <div class="ps-3 pt-2">${list.map(paperRow).join('') || '<p class="small text-muted">No papers for this class yet.</p>'}</div></details>`;
    }).join('');

    root.querySelector('.modal-dialog').innerHTML = `<div class="modal-content"><div class="modal-header"><div>
        <h4 class="modal-title fs-5"><i class="bi bi-folder2-open me-1 text-success" aria-hidden="true"></i>${esc(period.title || 'Exam')} papers</h4>
        <small class="text-muted">${esc([period.year_code, period.term_name, period.title].filter(Boolean).join(' / '))}. Prepare papers any time before the exam.</small></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <details class="border rounded-3 p-3 mb-3" id="paperFormWrap"><summary class="fw-semibold"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><span id="paperFormTitle">Add a paper</span></summary>
          <form id="paperForm" class="row g-2 mt-1" novalidate>
            <input type="hidden" name="id">
            <div class="col-md-4"><label class="form-label small" for="ppClass">Class</label><select class="form-select form-select-sm" id="ppClass" name="academic_year_class_id" required>${classOptions}</select></div>
            <div class="col-md-5"><label class="form-label small" for="ppArea">Learning area</label><select class="form-select form-select-sm" id="ppArea" name="learning_area_id" required></select></div>
            <div class="col-md-3"><label class="form-label small" for="ppNumber">Paper number</label><input class="form-control form-control-sm" id="ppNumber" name="paper_number" type="number" min="1" max="9" value="1"></div>
            <div class="col-md-6"><label class="form-label small" for="ppTitle">Title</label><input class="form-control form-control-sm" id="ppTitle" name="title" maxlength="200" placeholder="Mathematics Paper 1" required></div>
            <div class="col-md-3"><label class="form-label small" for="ppMax">Out of</label><input class="form-control form-control-sm" id="ppMax" name="max_marks" type="number" min="1" step="0.5"></div>
            <div class="col-md-3"><label class="form-label small" for="ppDuration">Duration (minutes)</label><input class="form-control form-control-sm" id="ppDuration" name="duration_minutes" type="number" min="0"></div>
            <div class="col-md-4"><label class="form-label small" for="ppSource">Source</label><select class="form-select form-select-sm" id="ppSource" name="source"><option value="set_internally">Set by school staff</option><option value="purchased">Bought from a supplier</option></select></div>
            <div class="col-md-4"><label class="form-label small" for="ppCopies">Copies needed</label><input class="form-control form-control-sm" id="ppCopies" name="copies_required" type="number" min="0"></div>
            <div class="col-12 row g-2 m-0 p-0" id="ppPurchase" hidden>
              <div class="col-md-4"><label class="form-label small" for="ppSupplier">Supplier</label><input class="form-control form-control-sm" id="ppSupplier" name="supplier_name" maxlength="150"></div>
              <div class="col-md-3"><label class="form-label small" for="ppCost">Cost (KES)</label><input class="form-control form-control-sm" id="ppCost" name="purchase_cost" type="number" min="0" step="0.01"></div>
              <div class="col-md-2"><label class="form-label small" for="ppDate">Bought on</label><input class="form-control form-control-sm" id="ppDate" name="purchase_date" type="date"></div>
              <div class="col-md-3"><label class="form-label small" for="ppRef">Receipt number</label><input class="form-control form-control-sm" id="ppRef" name="purchase_reference" maxlength="80"></div>
            </div>
            <div class="col-12"><label class="form-label small" for="ppNotes">Notes</label><textarea class="form-control form-control-sm" id="ppNotes" name="notes" rows="2"></textarea></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-sm btn-success" type="submit" id="paperSaveBtn">Save paper</button><button class="btn btn-sm btn-outline-secondary" type="button" id="paperResetBtn">Clear</button></div>
          </form>
        </details>
        ${classes.length ? folders : '<p class="text-muted">Add classes and learning areas to this exam period first.</p>'}
      </div></div>`;
    bootstrap.Modal.getOrCreateInstance(root).show();

    const form = $('paperForm');
    const fillAreas = (selected = null) => {
      const cls = classes.find((c) => Number(c.id) === Number(form.academic_year_class_id.value));
      form.learning_area_id.innerHTML = (cls?.learning_areas || []).map((a) => `<option value="${a.id}" ${Number(selected) === Number(a.id) ? 'selected' : ''}>${esc(a.name)}</option>`).join('');
    };
    const togglePurchase = () => { $('ppPurchase').hidden = form.source.value !== 'purchased'; };
    form.academic_year_class_id.addEventListener('change', () => fillAreas());
    form.source.addEventListener('change', togglePurchase);
    fillAreas();
    $('paperResetBtn').addEventListener('click', () => { form.reset(); form.id.value = ''; $('paperFormTitle').textContent = 'Add a paper'; fillAreas(); togglePurchase(); });
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const body = Object.fromEntries(new FormData(form).entries());
      body.exam_period_id = periodId;
      const id = body.id; delete body.id;
      try {
        const saved = await call(id ? `/academic/exam-papers/${id}` : '/academic/exam-papers', id ? 'PUT' : 'POST', body);
        notify(id ? 'Paper saved.' : 'Paper added. Upload the question paper next.', 'success');
        await showPapers(periodId, saved?.id || id);
      } catch (e) { notify(e.message || 'Unable to save the paper.', 'error'); }
    });

    root.querySelectorAll('[data-paper-edit]').forEach((btn) => btn.addEventListener('click', () => {
      const pp = papers.find((x) => Number(x.id) === Number(btn.dataset.paperEdit)); if (!pp) return;
      $('paperFormWrap').open = true; $('paperFormTitle').textContent = `Edit ${pp.title}`;
      form.id.value = pp.id; form.academic_year_class_id.value = pp.academic_year_class_id; fillAreas(pp.learning_area_id);
      ['paper_number', 'title', 'max_marks', 'duration_minutes', 'source', 'copies_required', 'supplier_name', 'purchase_cost', 'purchase_date', 'purchase_reference', 'notes']
        .forEach((k) => { if (form[k]) form[k].value = pp[k] ?? ''; });
      togglePurchase(); form.title.focus();
    }));
    root.querySelectorAll('[data-paper-status]').forEach((sel) => sel.addEventListener('change', async () => {
      try { await call(`/academic/exam-paper-status/${sel.dataset.paperStatus}`, 'POST', { status: sel.value }); notify(`Marked ${PAPER_STATUS[sel.value].toLowerCase()}.`, 'success'); await showPapers(periodId, sel.dataset.paperStatus); }
      catch (e) { notify(e.message || 'Unable to change the status.', 'error'); await showPapers(periodId, sel.dataset.paperStatus); }
    }));
    root.querySelectorAll('[data-paper-delete]').forEach((btn) => btn.addEventListener('click', async () => {
      if (!confirm('Remove this paper and its record?')) return;
      try { await call(`/academic/exam-papers/${btn.dataset.paperDelete}`, 'DELETE'); notify('Paper removed.', 'success'); await showPapers(periodId); }
      catch (e) { notify(e.message || 'Unable to remove the paper.', 'error'); }
    }));
    root.querySelectorAll('[data-paper-upload]').forEach((f) => f.addEventListener('submit', async (event) => {
      event.preventDefault();
      const fd = new FormData(f);
      if (!fd.get('file')?.name) { notify('Choose a file first.', 'warning'); return; }
      try { await window.callAPI(`/academic/exam-paper-files/${f.dataset.paperUpload}`, 'POST', fd, {}, { isFile: true }); notify('File uploaded.', 'success'); await showPapers(periodId, f.dataset.paperUpload); }
      catch (e) { notify(e.message || 'Unable to upload the file.', 'error'); }
    }));
    root.querySelectorAll('[data-paper-download]').forEach((btn) => btn.addEventListener('click', async () => {
      try { await window.callAPI(`/academic/exam-paper-file/${btn.dataset.paperDownload}`, 'GET', null, {}, { isDownload: true, filename: btn.dataset.name }); }
      catch (e) { notify(e.message || 'Unable to download the file.', 'error'); }
    }));
    root.querySelectorAll('[data-paper-file-delete]').forEach((btn) => btn.addEventListener('click', async () => {
      if (!confirm('Remove this file?')) return;
      try { await call(`/academic/exam-paper-files/${btn.dataset.paperFileDelete}`, 'DELETE'); notify('File removed.', 'success'); await showPapers(periodId); }
      catch (e) { notify(e.message || 'Unable to remove the file.', 'error'); }
    }));
    root.querySelectorAll('[data-paper-issue]').forEach((f) => f.addEventListener('submit', async (event) => {
      event.preventDefault();
      try { await call(`/academic/exam-paper-issues/${f.dataset.paperIssue}`, 'POST', Object.fromEntries(new FormData(f).entries())); notify('Issue recorded.', 'success'); await showPapers(periodId, f.dataset.paperIssue); }
      catch (e) { notify(e.message || 'Unable to record the issue.', 'error'); }
    }));
  }

  async function onPeriodAction(event) {
    const button = event.target.closest('[data-period-action]');
    if (!button || button.disabled) return;
    const action = button.dataset.periodAction;
    const periodId = Number(button.dataset.id || 0);
    if (!periodId) return;
    const period = (state.allPeriods || []).find((p) => Number(p.id) === periodId);
    const label = period ? period.title : 'this exam period';

    switch (action) {
      case 'schedule': await showSchedule(periodId); break;
      case 'edit': await openEdit(periodId); break;
      case 'delete': await deletePeriod(periodId, label); break;
      case 'reopen': await reopenPeriod(periodId, label); break;
      case 'publish': await publishPeriod(periodId, label); break;
      case 'open': await openPeriodResults(periodId, label); break;
      case 'results': await showResults(periodId); break;
      case 'my-results': await showResults(periodId, true); break;
      case 'publish-results': await publishPeriodResults(periodId, label); break;
      case 'national-timetable': await showNationalTimetable(periodId); break;
      case 'papers': await showPapers(periodId); break;
      default: break;
    }
  }

  async function openEdit(periodId) {
    const data = await call(`/academic/exam-periods/${periodId}`);
    const period = data.period || {};
    $('examPeriodEditForm').dataset.periodId = String(periodId);
    if ($('examPeriodEditTitle')) $('examPeriodEditTitle').value = period.title || '';
    if ($('examPeriodEditStart')) $('examPeriodEditStart').value = period.starts_on || '';
    if ($('examPeriodEditEnd')) $('examPeriodEditEnd').value = period.ends_on || '';
    renderClasses('examPeriodEditClasses', (period.classes || []).map((c) => Number(c.academic_year_class_id)));
    bootstrap.Modal.getOrCreateInstance($('examPeriodEditModal')).show();
  }

  async function deletePeriod(periodId, label) {
    if (!confirm(`Delete ${label}? Its records stay in the system but it is hidden from the list.`)) return;
    try { await call(`/academic/exam-periods/${periodId}`, 'DELETE'); notify('Exam period deleted.', 'success'); await loadPeriods(); }
    catch (error) { notify(error.message || 'Unable to delete exam period.', 'error'); }
  }

  async function reopenPeriod(periodId, label) {
    if (!confirm(`Reopen the timetable for ${label}? Result entry is closed until it is reopened.`)) return;
    try {
      await call(`/academic/exam-periods/${periodId}/reopen`, 'POST', { target: 'timetable' });
      notify('Exam period reopened for timetable editing.', 'success');
      await loadPeriods();
    } catch (error) { notify(error.message || 'Unable to reopen the exam period.', 'error'); }
  }

  async function publishPeriod(periodId, label) {
    if (!confirm(`Publish the timetable for ${label}? Teachers and learners see the sittings after this.`)) return;
    try {
      await call(`/academic/exam-periods/${periodId}/publish`, 'POST', {});
      notify('Exam timetable published.', 'success');
      await loadPeriods();
    } catch (error) { notify(error.message || 'Unable to publish the timetable.', 'error'); }
  }

  async function openPeriodResults(periodId, label) {
    if (!confirm(`Open result entry for ${label}? Subject teachers can then record marks.`)) return;
    try {
      await call(`/academic/exam-periods/${periodId}/open-results`, 'POST', {});
      notify('Result entry opened.', 'success');
      await loadPeriods();
    } catch (error) { notify(error.message || 'Unable to open result entry.', 'error'); }
  }

  async function publishPeriodResults(periodId, label) {
    state.publishResultsPeriodId = Number(periodId);
    $('publishResultsTitle').textContent = `Publish results · ${label}`;
    $('notifyResultsParents').checked = false;
    $('resultsNotificationOptions').classList.add('d-none');
    $('resultsRecipientsList').innerHTML = '<div class="text-muted small">Turn on notifications to load eligible parents.</div>';
    $('publishResultsFeedback').textContent = 'Every register must be submitted; Grade 4–9 registers must also be approved by the class teacher.';
    bootstrap.Modal.getOrCreateInstance($('publishResultsModal')).show();
  }

  async function loadPublicationRecipients() {
    const list = $('resultsRecipientsList');
    list.innerHTML = '<div class="text-muted small">Loading learners and linked parents…</div>';
    try {
      state.publicationRecipients = await call(`/academic/exam-periods/${state.publishResultsPeriodId}/publication-recipients`) || [];
      list.innerHTML = state.publicationRecipients.length ? state.publicationRecipients.map((row) => `<label class="list-group-item d-flex gap-2 align-items-start"><input class="form-check-input mt-1" type="checkbox" data-student-id="${Number(row.student_id)}" data-parent-id="${Number(row.parent_id)}"><span><strong>${esc(row.student_name || 'Learner')}</strong><small class="d-block text-muted">${esc(row.parent_name || 'Parent/guardian')} · SMS: ${esc(row.phone || 'not recorded')} · Email: ${esc(row.email || 'not recorded')}</small></span></label>`).join('') : '<div class="alert alert-warning">No linked parents were found for learners with submitted results.</div>';
    } catch (error) {
      list.innerHTML = `<div class="alert alert-danger">${esc(error.message || 'Unable to load parent choices.')}</div>`;
    }
  }

  async function submitPublishResults() {
    const periodId = state.publishResultsPeriodId;
    const notifyParents = $('notifyResultsParents').checked;
    const selected = [...document.querySelectorAll('#resultsRecipientsList [data-student-id]:checked')].map((box) => ({ student_id: Number(box.dataset.studentId), parent_id: Number(box.dataset.parentId) }));
    const channels = [...document.querySelectorAll('[data-results-channel]:checked')].map((box) => box.value);
    if (notifyParents && !selected.length) return notify('Choose at least one learner-parent pair or turn off parent notifications.', 'warning');
    if (notifyParents && !channels.length) return notify('Choose SMS and/or email for the selected parent notifications.', 'warning');
    const button = $('confirmPublishResults'); button.disabled = true;
    try {
      const result = await call(`/academic/exam-periods/${periodId}/publish-results`, 'POST', {
        notification_recipients: notifyParents ? selected : [],
        notification_channels: notifyParents ? channels : [],
      });
      bootstrap.Modal.getInstance($('publishResultsModal'))?.hide();
      const delivery = result.notifications || {};
      const suffix = notifyParents ? ` ${Number(delivery.queued || 0)} notification recipient(s) queued${delivery.failed ? `; ${Number(delivery.failed)} failed to queue` : ''}.` : ' No parent notifications were sent.';
      notify((result.official_parent_view || 'Results published.') + suffix, delivery.failed ? 'warning' : 'success');
      await loadPeriods();
    } catch (error) { notify(error.message || 'Unable to publish results.', 'error'); }
    finally { button.disabled = false; }
  }

  async function showNationalTimetable(periodId) {
    $('examDocumentSuggestedMapping')?.classList.add('d-none');
    $('examDocumentPreviewWrap')?.classList.add('d-none');
    $('examDocumentPreviewActions')?.classList.add('d-none');
    if ($('examDocumentStatus')) $('examDocumentStatus').textContent = '';
    state.activePeriod = Number(periodId);
    bootstrap.Modal.getOrCreateInstance($('examDocumentImportModal')).show();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attach, { once: true });
  else attach();
})();
