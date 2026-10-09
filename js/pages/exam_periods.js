/* Exam-period workflow UI. Relationships and validation are enforced by the API. */
(() => {
  const $ = (id) => document.getElementById(id);
  const state = { terms: [], classes: [], periods: [], kinds: [], activePeriod: null, detail: null, aiPollTimer: null, importPollTimer: null };
  const esc = (value) => { const n = document.createElement('span'); n.textContent = String(value ?? ''); return n.innerHTML; };
  const payload = (response) => response?.data?.data ?? response?.data ?? response;
  const notify = (message, type = 'info') => window.API?.showNotification?.(message, type) || window.showNotification?.(message, type);
  const canManage = () => window.AuthContext?.hasAnyPermission?.(['academic_manage','academic_edit','academics_manage','academics_edit']) || ['System Administrator','School Administrator','Headteacher'].some((r) => window.AuthContext?.hasRole?.(r));
  const canViewResults = () => canManage() || ['Deputy Head - Academic','Deputy Head - Discipline'].some((r) => window.AuthContext?.hasRole?.(r)) || window.AuthContext?.hasAnyPermission?.(['assessments_view','academic_view']);
  const canReviewClassRegisters = () => window.AuthContext?.hasRole?.('Class Teacher') || window.AuthContext?.hasAnyPermission?.(['results_review']);
  const canViewSchoolResults = () => ['System Administrator','School Administrator','Headteacher','Deputy Head - Academic','Deputy Head - Discipline'].some((r) => window.AuthContext?.hasRole?.(r));
  const canPublishResults = () => ['System Administrator','School Administrator'].some((r) => window.AuthContext?.hasRole?.(r));
  const canImportExamDocuments = () => ['System Administrator','School Administrator'].some((r) => window.AuthContext?.hasRole?.(r));
  const termLabel = (term) => `${term.academic_year_name || `Academic year ${term.academic_year_id}`} · Term ${term.term_id}`;
  function printScope(scope) {
    const className = `print-${scope}`;
    document.body.classList.add(className);
    const cleanup = () => document.body.classList.remove(className);
    window.addEventListener('afterprint', cleanup, { once: true });
    window.print();
    setTimeout(cleanup, 1500);
  }

  async function call(path, method = 'GET', body = null, query = null) {
    return payload(await window.API.apiCall(path, method, body, query));
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
      renderClasses();
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

  function syncNationalWrap(selectEl) {
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
  async function loadPeriods() {
    try {
      state.periods = await call('/academic/exam-periods') || [];
      if (!Array.isArray(state.periods)) state.periods = state.periods.items || [];
      // Deleted periods are GONE — never render them in the active list.
      state.periods = state.periods.filter((p) => !p.deleted_at);
      renderPeriods();
    } catch (error) { $('examPeriodsBody').innerHTML = `<tr><td colspan="10" class="text-danger">${esc(error.message || 'Unable to load exam periods.')}</td></tr>`; }
  }
  function renderPeriods() {
    const body = $('examPeriodsBody');
    if (!state.periods.length) { body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No exam periods yet.</td></tr>'; return; }
    body.innerHTML = state.periods.map((p) => {
      const resultCount = `${Number(p.approved_count || 0)} approved · ${Number(p.submitted_count || 0)} submitted`;
      const item = (action, icon, label, extra = '', disabled = false) => `<li><button class="dropdown-item${extra ? ` ${extra}` : ''}" type="button" data-period-action="${action}" data-id="${Number(p.id)}"${disabled ? ' disabled title="Schedule every learning area first"' : ''}><i class="bi ${icon} me-2"></i>${esc(label)}</button></li>`;
      const items = [];
      // Deleted periods are filtered out entirely — no restore action needed.
      items.push('<li><h6 class="dropdown-header">Timetable</h6></li>');
      items.push(item('schedule', 'bi-calendar-week', p.status === 'draft' ? 'Set timetable' : 'View timetable'));
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
      return `<tr class="${p.deleted_at ? 'table-secondary' : ''}"><td class="fw-semibold">${esc(p.title)}${p.deleted_at ? ' <span class="badge text-bg-danger">deleted</span>' : ''}</td><td>${esc(assessmentType)}</td><td>${esc(p.academic_year_name || 'Academic year')} · Term ${Number(p.term_id || 0)}</td><td>${esc(p.starts_on)} – ${esc(p.ends_on)}</td><td>${Number(p.class_count || 0)}</td><td>${Number(p.learning_area_count || 0)}</td><td>${Number(p.scheduled_count || 0)} / ${Number(p.learning_area_count || 0)}</td><td>${esc(resultCount)}</td><td><span class="badge text-bg-${p.status === 'completed' ? 'success' : p.status === 'draft' ? 'secondary' : 'primary'}">${esc(String(p.status).replaceAll('_', ' '))}</span></td><td class="text-nowrap">${actions}</td></tr>`;
    }).join('');
  }
  async function showSchedule(periodId) {
    const data = await call(`/academic/exam-periods/${periodId}`);
    state.activePeriod = Number(periodId); state.detail = data;
    const p = data.period; const editable = canManage() && !p.deleted_at; const aiDraftable = editable && p.status === 'draft';
    const rows = (data.entries || []).map((entry) => `<tr data-area-id="${Number(entry.exam_period_class_learning_area_id)}"><td>${esc(entry.class_name)}</td><td>${esc(entry.learning_area_name)}</td><td><input type="date" min="${esc(p.starts_on)}" max="${esc(p.ends_on)}" class="form-control form-control-sm slot-date" value="${esc(entry.exam_date || '')}" ${editable ? '' : 'disabled'}></td><td><input type="time" class="form-control form-control-sm slot-start" value="${esc(String(entry.start_time || '').slice(0,5))}" ${editable ? '' : 'disabled'}></td><td><input type="time" class="form-control form-control-sm slot-end" value="${esc(String(entry.end_time || '').slice(0,5))}" ${editable ? '' : 'disabled'}></td><td><input type="number" min="1" step="0.5" class="form-control form-control-sm slot-marks" value="${esc(entry.max_marks || 100)}" ${editable ? '' : 'disabled'}></td><td><input type="text" maxlength="100" class="form-control form-control-sm slot-venue" value="${esc(entry.venue || '')}" ${editable ? '' : 'disabled'}></td></tr>`).join('');
    const root = $('examPeriodWorkspace');
    root.querySelector('.modal-dialog').innerHTML = `<div class="modal-content"><div class="modal-header"><h4 class="modal-title fs-5">${esc(p.title)} · Timetable</h4><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="d-flex flex-wrap gap-2 mb-3">${editable ? `<button class="btn btn-sm btn-outline-primary" id="draftExamPeriodTimetable"><i class="bi bi-stars me-1"></i>Draft with AI</button><button class="btn btn-sm btn-primary" id="saveExamPeriodTimetable">${p.status === 'draft' ? 'Save draft' : 'Save timetable changes'}</button><span class="small text-muted" id="timetableLocalStatus">Progress autosaves on this device.</span>` : ''}<button class="btn btn-sm btn-outline-secondary" id="examTimetableCsv">Export CSV</button><button class="btn btn-sm btn-outline-secondary" id="examTimetablePrint">Print / PDF</button></div>${editable ? `<div class="border rounded p-3 mb-3 bg-light"><div class="fw-semibold mb-1">AI timetable draft</div><p class="small text-muted mb-2">AI proposes dates and sittings for your review. It will not save or publish the timetable. Early classes: up to two morning papers a day. Grades 4–9: up to three papers a day. The system checks assigned-teacher conflicts.</p><div class="row g-2 align-items-end"><div class="col-6 col-md-2"><label class="form-label small" for="examDayStart">Exam day starts</label><input type="time" class="form-control form-control-sm" id="examDayStart" required></div><div class="col-6 col-md-2"><label class="form-label small" for="examMorningEnd">Morning ends</label><input type="time" class="form-control form-control-sm" id="examMorningEnd" required></div><div class="col-6 col-md-2"><label class="form-label small" for="examDayEnd">Exam day ends</label><input type="time" class="form-control form-control-sm" id="examDayEnd" required></div><div class="col-6 col-md-2"><label class="form-label small" for="examPaperMinutes">Paper duration (min)</label><input type="number" min="1" max="600" class="form-control form-control-sm" id="examPaperMinutes" required></div><div class="col-6 col-md-2"><label class="form-label small" for="examBreakMinutes">Break (min)</label><input type="number" min="0" max="180" class="form-control form-control-sm" id="examBreakMinutes" value="0" required></div><div class="col-6 col-md-2"><span class="small text-muted" id="examAiDraftStatus" aria-live="polite"></span></div></div><div class="mt-2 d-none" id="examAiDraftNotes"></div></div>` : ''}<div class="table-responsive"><table class="table table-sm table-bordered align-middle" id="examTimetableTable"><thead><tr><th>Class (all streams)</th><th>Learning area</th><th>Date</th><th>Start</th><th>End</th><th>Max marks</th><th>Venue</th></tr></thead><tbody>${rows}</tbody></table></div></div></div>`;
    const cacheKey = `exam-period-timetable:${periodId}`;
    if (editable) { const cache = JSON.parse(localStorage.getItem(cacheKey) || '{}'); root.querySelectorAll('tbody tr[data-area-id]').forEach((tr) => { const v=cache[tr.dataset.areaId]; if(v) ['date','start','end','marks','venue'].forEach((k)=>{ if(v[k]!==undefined) tr.querySelector(`.slot-${k}`).value=v[k]; }); }); root.oninput = (event) => { if(!event.target.matches('.slot-date,.slot-start,.slot-end,.slot-marks,.slot-venue')) return; const snapshot={}; root.querySelectorAll('tbody tr[data-area-id]').forEach((tr)=>snapshot[tr.dataset.areaId]=Object.fromEntries(['date','start','end','marks','venue'].map((k)=>[k,tr.querySelector(`.slot-${k}`).value]))); localStorage.setItem(cacheKey,JSON.stringify(snapshot)); $('timetableLocalStatus').textContent='Unsaved progress saved locally.'; }; }
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
      try { await call(`/academic/exam-periods/${periodId}/timetable`, 'PUT', { entries }); localStorage.removeItem(cacheKey); notify('Exam timetable saved.', 'success'); await loadPeriods(); await showSchedule(periodId); }
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
      await call('/academic/exam-periods-ai-timetable-draft-queue', 'POST', { period_id: periodId, ...constraints });
      status.textContent = 'Queued. Waiting for the background AI draft…';
      pollAiTimetableDraft(periodId, period, constraints, 0);
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
      const result = await call('/academic/exam-periods-ai-timetable-drafts', 'GET', null, { period_id: periodId });
      if (result.status === 'pending' && attempt < 20) {
        status.textContent = `AI is preparing the draft… (${attempt + 1}/20)`;
        state.aiPollTimer = setTimeout(() => pollAiTimetableDraft(periodId, period, constraints, attempt + 1), 6000);
        return;
      }
      if (result.status === 'pending') {
        status.textContent = 'Still processing. Reopen this timetable later to retrieve the draft.';
        button.disabled = false;
        return;
      }
      if (!result.ready) {
        status.textContent = 'No complete timetable draft was produced.';
        notes.innerHTML = `<div class="alert alert-warning py-2 mb-0">${(result.unresolved_constraints || []).map((item) => `<div>${esc(item)}</div>`).join('') || 'Review teacher assignments and the available dates or session settings, then request another draft.'}</div>`;
        notes.classList.remove('d-none');
        button.disabled = false;
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
      status.textContent = 'Draft loaded into the timetable for your review. Nothing has been saved.';
      notes.innerHTML = `<div class="alert alert-info py-2 mb-0"><strong>${esc(result.title || 'AI timetable proposal')}</strong>${result.body ? `<div>${esc(result.body)}</div>` : ''}${(result.next_steps || []).length ? `<ul class="mb-0 mt-1">${result.next_steps.map((item) => `<li>${esc(item)}</li>`).join('')}</ul>` : ''}</div>`;
      notes.classList.remove('d-none');
      button.disabled = false;
      notify('AI draft loaded. Review the timetable and save it when ready.', 'success');
    } catch (error) {
      status.textContent = 'Unable to retrieve the AI draft.';
      button.disabled = false;
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

    // Keep the reviewed grid so the save step posts exactly what staff saw.
    state.previewPayload = { headers, rows, filename: String(preview.filename || 'national-timetable') };
    renderNationalSaveControls();
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
    const national = (state.periods || []).filter((period) => String(period.assessment_kind || '') === 'national' && !period.deleted_at);
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
        $('examDocumentSaveStatus').textContent = 'No KNEC timetable saved for this period yet. Upload and review one to create the paper schedule.';
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
      return `<tr class="${r.result_deleted_at ? 'table-secondary' : ''}"><td>${esc(r.class_name)}${r.stream_name ? ` · ${esc(r.stream_name)}` : ''}</td><td>${esc(r.learning_area)}</td><td>${esc(r.admission_no)}</td><td>${esc(r.learner_name)}</td><td>${esc(r.marks_obtained ?? '—')}</td><td>${esc(r.max_marks)}</td><td>${esc(r.grade || '—')}</td><td>${esc(r.entry_status || 'Not entered')}</td><td>${esc(r.assessment_status)}</td>${canReview ? `<td>${reviewAction}</td>` : ''}${!mine && canManage() && r.result_id ? `<td>${r.result_deleted_at ? `<button class="btn btn-sm btn-outline-success" data-result-restore="${Number(r.result_id)}">Restore</button>` : `<button class="btn btn-sm btn-outline-primary" data-result-edit="${Number(r.result_id)}" data-marks="${esc(r.marks_obtained ?? '')}" data-entry="${esc(r.entry_status || 'present')}">Edit</button> <button class="btn btn-sm btn-outline-danger" data-result-delete="${Number(r.result_id)}">Delete</button>`}</td>` : ''}</tr>`;
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
    root.querySelectorAll('[data-result-edit]').forEach((button)=>button.onclick=async()=>{ const marks=prompt('Corrected marks',button.dataset.marks); if(marks===null)return; const entry_status=prompt('Result status: present, absent, or exempted',button.dataset.entry); if(!entry_status)return; try{await call(`/academic/exam-period-result/${button.dataset.resultEdit}`,'PUT',{marks_obtained:marks,entry_status,reason:'School administrator correction'}); notify('Result corrected.','success'); await showResults(periodId,mine);}catch(e){notify(e.message||'Unable to update result.','error');} });
    root.querySelectorAll('[data-result-delete]').forEach((button)=>button.onclick=async()=>{if(!confirm('Delete this result record?'))return;try{await call(`/academic/exam-period-result/${button.dataset.resultDelete}`,'DELETE');notify('Result record deleted.','success');await showResults(periodId,mine);}catch(e){notify(e.message||'Unable to delete result.','error');}});
    root.querySelectorAll('[data-result-restore]').forEach((button)=>button.onclick=async()=>{try{await call(`/academic/exam-period-result/${button.dataset.resultRestore}/restore`,'POST',{});notify('Result record restored.','success');await showResults(periodId,mine,true);}catch(e){notify(e.message||'Unable to restore result.','error');}});
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
    const row = state.periods.find((x) => Number(x.id) === periodId);
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
  function attach() {
    const exportAllowed = typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
    const printAllowed = typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');
    if (!exportAllowed) { $('examPeriodsCsv')?.classList.add('d-none'); }
    if (!printAllowed) { $('examPeriodsPrint')?.classList.add('d-none'); }
    if (!canManage()) $('openExamPeriodCreate')?.classList.add('d-none');
    if (!canImportExamDocuments()) $('openExamDocumentImport')?.classList.add('d-none');
    $('openExamPeriodCreate')?.addEventListener('click', async () => { await loadOptions($('examPeriodTerm')?.value || ''); bootstrap.Modal.getOrCreateInstance($('examPeriodModal')).show(); });
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
        await call('/academic/exam-periods', 'POST', { academic_year_term_id: Number($('examPeriodTerm').value), title: $('examPeriodTitle').value.trim(), starts_on: startsOn, ends_on: endsOn, kind: $('examPeriodKind').value, entry_mode: $('examPeriodEntryMode').value, ...kindPayload, assessment_authority: kindPayload.assessment_kind === 'national' ? 'KNEC' : null, national_assessment_code: $('nationalAssessmentCode')?.value || null, academic_year_class_ids }); bootstrap.Modal.getInstance($('examPeriodModal'))?.hide(); event.currentTarget.reset(); $('nationalAssessmentWrap')?.classList.add('d-none'); await loadPeriods(); notify('Exam period created.', 'success');
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
      const nationalCode = kindPayload.assessment_kind === 'national' ? $('examPeriodEditNationalCode').value : null;
      if (kindPayload.assessment_kind === 'national' && !nationalCode) {
        notify('Select the national assessment (KPSEA, KJSEA, or other).', 'error');
        return;
      }
      const button = $('saveExamPeriodEditBtn'); button.disabled = true;
      try {
        await call(`/academic/exam-periods/${id}`, 'PUT', {
          title, starts_on, ends_on, academic_year_class_ids,
          ...kindPayload,
          assessment_authority: kindPayload.assessment_kind === 'national' ? 'KNEC' : null,
          national_assessment_code: nationalCode,
          entry_mode: $('examPeriodEditEntryMode').value,
        });
        bootstrap.Modal.getInstance($('examPeriodEditModal'))?.hide();
        await loadPeriods();
        notify('Exam period updated.', 'success');
      } catch (error) { notify(error.message || 'Unable to update exam period.', 'error'); }
      finally { button.disabled = false; }
    });
    $('examPeriodDeleteForm')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const id = Number($('examPeriodDeleteForm').dataset.periodId || 0);
      if (!id) return;
      const button = $('examPeriodDeleteBtn'); button.disabled = true;
      try { await call(`/academic/exam-periods/${id}`, 'DELETE'); bootstrap.Modal.getInstance($('examPeriodDeleteModal'))?.hide(); await loadPeriods(); notify('Exam period deleted.', 'success'); }
      catch (error) { notify(error.message || 'Unable to delete exam period.', 'error'); }
      finally { button.disabled = false; }
    });
    $('examPeriodsBody')?.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-period-action]'); if (!button) return;
      const id = Number(button.dataset.id); const action = button.dataset.periodAction; button.disabled = true;
      try {
      if (action === 'national-timetable') { await showNationalPapers(id); return; }
      if (action === 'schedule') await showSchedule(id);
      if (action === 'my-results') await showResults(id, true);
      if (action === 'results') await showResults(id);
        if (action === 'delete') openDeleteModal(id);
        if (action === 'restore') { await call(`/academic/exam-periods/${id}/restore`, 'POST', {}); await loadPeriods(); notify('Exam period restored.','success'); }
        if (action === 'edit') await openEditModal(id);
        if (action === 'reopen') { const choice=confirm('Reopen results entry? Choose Cancel to reopen timetable editing.'); await call(`/academic/exam-periods/${id}/reopen`,'POST',{target:choice?'results':'timetable'}); await loadPeriods(); notify('Exam period reopened for editing.','success'); }
        if (action === 'publish' || action === 'open') { await call(`/academic/exam-periods/${id}/${action === 'publish' ? 'publish' : 'open-results'}`, 'POST', {}); notify(action === 'publish' ? 'Timetable published.' : 'Results entry opened.', 'success'); await loadPeriods(); }
        if (action === 'publish-results' && confirm('Publish these reviewed marks as official term results? This makes results visible through the parent results surfaces.')) { await call(`/academic/exam-periods/${id}/publish-results`, 'POST', {}); notify('Official summative results published. Continue to report-card release to generate parent PDFs and notifications.', 'success'); await loadPeriods(); }
      } catch (error) { notify(error.message || 'Unable to complete this action.', 'error'); }
      finally { button.disabled = false; }
    });
    $('examPeriodsCsv')?.addEventListener('click', () => { if (exportAllowed) exportTable('examPeriodsTable', 'exam-periods.csv'); });
    $('examPeriodsPrint')?.addEventListener('click', () => { if (printAllowed) printScope('exam-periods'); });
  }
  document.addEventListener('DOMContentLoaded', async () => {
    if (!$('examPeriodWorkflow')) return;
    await window.AuthContext?.ready?.();
    attach();
    try { await loadOptions(); } catch (error) { notify(error.message || 'Unable to load term and class options.', 'error'); }
    await loadPeriods();
  });
})();
