/* Exam-period workflow UI. Relationships and validation are enforced by the API. */
(() => {
  const $ = (id) => document.getElementById(id);
  const state = { terms: [], classes: [], periods: [], activePeriod: null, detail: null, aiPollTimer: null };
  const esc = (value) => { const n = document.createElement('span'); n.textContent = String(value ?? ''); return n.innerHTML; };
  const payload = (response) => response?.data?.data ?? response?.data ?? response;
  const notify = (message, type = 'info') => window.API?.showNotification?.(message, type) || window.showNotification?.(message, type);
  const canManage = () => window.AuthContext?.hasAnyPermission?.(['academic_manage','academic_edit','academics_manage','academics_edit']) || ['System Administrator','School Administrator','Headteacher'].some((r) => window.AuthContext?.hasRole?.(r));
  const canViewResults = () => canManage() || ['Deputy Head - Academic','Deputy Head - Discipline'].some((r) => window.AuthContext?.hasRole?.(r)) || window.AuthContext?.hasAnyPermission?.(['assessments_view','academic_view']);
  const canViewSchoolResults = () => ['System Administrator','School Administrator','Headteacher','Deputy Head - Academic','Deputy Head - Discipline'].some((r) => window.AuthContext?.hasRole?.(r));
  const termLabel = (term) => `${term.academic_year_name || `Academic year ${term.academic_year_id}`} · Term ${term.term_id}`;

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
  }
  function renderClasses() {
    const root = $('examPeriodClasses');
    if (!root) return;
    root.innerHTML = state.classes.length ? state.classes.map((classRow) => {
      const areas = (classRow.learning_areas || []).map((area) => area.name).join(', ');
      return `<div class="col-md-6"><label class="form-check border rounded p-2 h-100"><input class="form-check-input me-2 exam-period-class" type="checkbox" value="${Number(classRow.academic_year_class_id)}" ${areas ? '' : 'disabled'}><span class="fw-semibold">${esc(classRow.class_name)}</span><small class="d-block text-muted ms-4">${esc(areas || 'No configured learning areas')}</small></label></div>`;
    }).join('') : '<div class="text-muted">No classes with active streams are configured for this academic year.</div>';
  }
  async function loadPeriods() {
    try {
      state.periods = await call('/academic/exam-periods') || [];
      if (!Array.isArray(state.periods)) state.periods = state.periods.items || [];
      renderPeriods();
    } catch (error) { $('examPeriodsBody').innerHTML = `<tr><td colspan="9" class="text-danger">${esc(error.message || 'Unable to load exam periods.')}</td></tr>`; }
  }
  function renderPeriods() {
    const body = $('examPeriodsBody');
    if (!state.periods.length) { body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No exam periods yet.</td></tr>'; return; }
    body.innerHTML = state.periods.map((p) => {
      const resultCount = `${Number(p.approved_count || 0)} approved · ${Number(p.submitted_count || 0)} submitted`;
      let actions = `<button class="btn btn-sm btn-outline-primary" data-period-action="schedule" data-id="${Number(p.id)}">${p.status === 'draft' ? 'Set timetable' : 'View timetable'}</button>`;
      if (canManage() && p.status === 'draft') actions += ` <button class="btn btn-sm btn-primary" data-period-action="publish" data-id="${Number(p.id)}" ${Number(p.scheduled_count) < Number(p.learning_area_count) ? 'disabled title="Schedule every learning area first"' : ''}>Publish</button>`;
      if (canManage() && p.status === 'published') actions += ` <button class="btn btn-sm btn-outline-success" data-period-action="open" data-id="${Number(p.id)}">Open results</button>`;
      if (canViewSchoolResults()) actions += ` <button class="btn btn-sm btn-outline-secondary" data-period-action="results" data-id="${Number(p.id)}">School results</button>`;
      else if (canViewResults()) actions += ` <button class="btn btn-sm btn-outline-secondary" data-period-action="my-results" data-id="${Number(p.id)}">My results</button>`;
      return `<tr><td class="fw-semibold">${esc(p.title)}</td><td>${esc(p.academic_year_name || 'Academic year')} · Term ${Number(p.term_id || 0)}</td><td>${esc(p.starts_on)} – ${esc(p.ends_on)}</td><td>${Number(p.class_count || 0)}</td><td>${Number(p.learning_area_count || 0)}</td><td>${Number(p.scheduled_count || 0)} / ${Number(p.learning_area_count || 0)}</td><td>${esc(resultCount)}</td><td><span class="badge text-bg-${p.status === 'completed' ? 'success' : p.status === 'draft' ? 'secondary' : 'primary'}">${esc(String(p.status).replaceAll('_', ' '))}</span></td><td class="text-nowrap">${actions}</td></tr>`;
    }).join('');
  }
  async function showSchedule(periodId) {
    const data = await call(`/academic/exam-periods/${periodId}`);
    state.activePeriod = Number(periodId); state.detail = data;
    const p = data.period; const editable = canManage() && p.status === 'draft';
    const rows = (data.entries || []).map((entry) => `<tr data-area-id="${Number(entry.exam_period_class_learning_area_id)}"><td>${esc(entry.class_name)}</td><td>${esc(entry.learning_area_name)}</td><td><input type="date" min="${esc(p.starts_on)}" max="${esc(p.ends_on)}" class="form-control form-control-sm slot-date" value="${esc(entry.exam_date || '')}" ${editable ? '' : 'disabled'}></td><td><input type="time" class="form-control form-control-sm slot-start" value="${esc(String(entry.start_time || '').slice(0,5))}" ${editable ? '' : 'disabled'}></td><td><input type="time" class="form-control form-control-sm slot-end" value="${esc(String(entry.end_time || '').slice(0,5))}" ${editable ? '' : 'disabled'}></td><td><input type="number" min="1" step="0.5" class="form-control form-control-sm slot-marks" value="${esc(entry.max_marks || 100)}" ${editable ? '' : 'disabled'}></td><td><input type="text" maxlength="100" class="form-control form-control-sm slot-venue" value="${esc(entry.venue || '')}" ${editable ? '' : 'disabled'}></td></tr>`).join('');
    const root = $('examPeriodWorkspace'); root.classList.remove('d-none');
    root.innerHTML = `<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><div><h4 class="h6 mb-1">${esc(p.title)} · Timetable</h4><span class="small text-muted">One sitting per class and learning area. All streams of a class share this paper and sitting time.</span></div><div class="d-flex flex-wrap gap-2">${editable ? '<button class="btn btn-sm btn-outline-primary" id="draftExamPeriodTimetable"><i class="bi bi-stars me-1"></i>Draft with AI</button><button class="btn btn-sm btn-primary" id="saveExamPeriodTimetable">Save timetable</button>' : ''}<button class="btn btn-sm btn-outline-secondary" id="examTimetableCsv">Export CSV</button><button class="btn btn-sm btn-outline-secondary" id="examTimetablePrint">Print / PDF</button><button class="btn btn-sm btn-outline-secondary" id="closeExamPeriodWorkspace">Close</button></div></div>${editable ? `<div class="border rounded p-3 mb-3 bg-light"><div class="fw-semibold mb-1">AI timetable draft</div><p class="small text-muted mb-2">AI proposes dates and sittings for your review. It will not save or publish the timetable. Early classes: up to two morning papers a day. Grades 4–9: up to three papers a day. The system checks assigned-teacher conflicts.</p><div class="row g-2 align-items-end"><div class="col-6 col-md-2"><label class="form-label small" for="examDayStart">Exam day starts</label><input type="time" class="form-control form-control-sm" id="examDayStart" required></div><div class="col-6 col-md-2"><label class="form-label small" for="examMorningEnd">Morning ends</label><input type="time" class="form-control form-control-sm" id="examMorningEnd" required></div><div class="col-6 col-md-2"><label class="form-label small" for="examDayEnd">Exam day ends</label><input type="time" class="form-control form-control-sm" id="examDayEnd" required></div><div class="col-6 col-md-2"><label class="form-label small" for="examPaperMinutes">Paper duration (min)</label><input type="number" min="1" max="600" class="form-control form-control-sm" id="examPaperMinutes" required></div><div class="col-6 col-md-2"><label class="form-label small" for="examBreakMinutes">Break (min)</label><input type="number" min="0" max="180" class="form-control form-control-sm" id="examBreakMinutes" value="0" required></div><div class="col-6 col-md-2"><span class="small text-muted" id="examAiDraftStatus" aria-live="polite"></span></div></div><div class="mt-2 d-none" id="examAiDraftNotes"></div></div>` : ''}<div class="table-responsive"><table class="table table-sm table-bordered align-middle" id="examTimetableTable"><thead><tr><th>Class (all streams)</th><th>Learning area</th><th>Date</th><th>Start</th><th>End</th><th>Max marks</th><th>Venue</th></tr></thead><tbody>${rows}</tbody></table></div>`;
    $('closeExamPeriodWorkspace').onclick = () => root.classList.add('d-none');
    const exportAllowed = typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
    const printAllowed = typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');
    $('examTimetableCsv').classList.toggle('d-none', !exportAllowed);
    $('examTimetablePrint').classList.toggle('d-none', !printAllowed);
    $('examTimetableCsv').onclick = () => { if (exportAllowed) exportTable('examTimetableTable', `${p.title}-timetable.csv`); };
    $('examTimetablePrint').onclick = () => { if (printAllowed) window.print(); };
    $('draftExamPeriodTimetable')?.addEventListener('click', () => queueAiTimetableDraft(periodId, p));
    $('saveExamPeriodTimetable')?.addEventListener('click', async () => {
      const entries = [...root.querySelectorAll('tbody tr[data-area-id]')].map((tr) => ({ exam_period_class_learning_area_id: Number(tr.dataset.areaId), exam_date: tr.querySelector('.slot-date').value, start_time: tr.querySelector('.slot-start').value, end_time: tr.querySelector('.slot-end').value, max_marks: Number(tr.querySelector('.slot-marks').value), venue: tr.querySelector('.slot-venue').value }));
      try { await call(`/academic/exam-periods/${periodId}/timetable`, 'PUT', { entries }); notify('Exam timetable saved.', 'success'); await loadPeriods(); await showSchedule(periodId); }
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
  async function showResults(periodId, mine = false) {
    const data = await call(`/academic/exam-periods/${periodId}/${mine ? 'my-results' : 'results'}`); const p = data.period;
    const root = $('examPeriodWorkspace'); root.classList.remove('d-none');
    const rows = (data.items || []).map((r) => `<tr><td>${esc(r.class_name)}${r.stream_name ? ` · ${esc(r.stream_name)}` : ''}</td><td>${esc(r.learning_area)}</td><td>${esc(r.admission_no)}</td><td>${esc(r.learner_name)}</td><td>${esc(r.marks_obtained ?? '—')}</td><td>${esc(r.max_marks)}</td><td>${esc(r.grade || '—')}</td><td>${esc(r.entry_status || 'Not entered')}</td><td>${esc(r.assessment_status)}</td></tr>`).join('');
    root.innerHTML = `<div class="d-flex justify-content-between align-items-center mb-2"><div><h4 class="h6 mb-0">${esc(p.title)} · ${mine ? 'My teaching results' : 'School results'}</h4><small class="text-muted">${mine ? 'Results are limited to streams and learning areas assigned to you for this term.' : 'Leadership view across the selected classes and learning areas.'}</small></div><div class="d-flex gap-2"><button class="btn btn-sm btn-outline-secondary" id="examResultsCsv">Export CSV</button><button class="btn btn-sm btn-outline-secondary" id="examResultsPrint">Print / PDF</button><button class="btn btn-sm btn-outline-secondary" id="closeExamResults">Close</button></div></div><div class="table-responsive"><table class="table table-sm table-striped" id="examPeriodResultsTable"><thead><tr><th>Class / stream</th><th>Learning area</th><th>Admission no.</th><th>Learner</th><th>Mark</th><th>Out of</th><th>Grade</th><th>Entry</th><th>Assessment</th></tr></thead><tbody>${rows || '<tr><td colspan="9" class="text-center text-muted">No results found for this period and teaching assignment.</td></tr>'}</tbody></table></div>`;
    $('closeExamResults').onclick = () => root.classList.add('d-none');
    const exportAllowed = typeof window.AuthContext?.canExport !== 'function' || window.AuthContext.canExport('academic');
    const printAllowed = typeof window.AuthContext?.canPrint !== 'function' || window.AuthContext.canPrint('academic');
    $('examResultsCsv').classList.toggle('d-none', !exportAllowed);
    $('examResultsPrint').classList.toggle('d-none', !printAllowed);
    $('examResultsPrint').onclick = () => { if (printAllowed) window.print(); };
    $('examResultsCsv').onclick = () => { if (exportAllowed) exportTable('examPeriodResultsTable', `${p.title}-results.csv`); };
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
    $('openExamPeriodCreate')?.addEventListener('click', async () => { await loadOptions($('examPeriodTerm')?.value || ''); bootstrap.Modal.getOrCreateInstance($('examPeriodModal')).show(); });
    $('examPeriodTerm')?.addEventListener('change', async (event) => loadOptions(event.target.value));
    $('examPeriodToggleClasses')?.addEventListener('click', (event) => { const boxes = [...document.querySelectorAll('.exam-period-class:not(:disabled)')]; const select = boxes.some((box) => !box.checked); boxes.forEach((box) => { box.checked = select; }); event.currentTarget.textContent = select ? 'Clear selection' : 'Select all'; });
    $('examPeriodForm')?.addEventListener('submit', async (event) => {
      event.preventDefault(); const academic_year_class_ids = [...document.querySelectorAll('.exam-period-class:checked')].map((box) => Number(box.value));
      const startsOn = $('examPeriodStart').value;
      const endsOn = $('examPeriodEnd').value;
      if (!startsOn || !endsOn || startsOn > endsOn) {
        notify('Check the exam dates: the end date must be on or after the start date.', 'error');
        return;
      }
      const button = $('createExamPeriodBtn'); button.disabled = true;
      try { await call('/academic/exam-periods', 'POST', { academic_year_term_id: Number($('examPeriodTerm').value), title: $('examPeriodTitle').value.trim(), starts_on: startsOn, ends_on: endsOn, academic_year_class_ids }); bootstrap.Modal.getInstance($('examPeriodModal'))?.hide(); event.currentTarget.reset(); await loadPeriods(); notify('Exam period created. Set one sitting per class and learning area.', 'success'); }
      catch (error) { notify(error.message || 'Unable to create exam period.', 'error'); }
      finally { button.disabled = false; }
    });
    $('examPeriodsBody')?.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-period-action]'); if (!button) return;
      const id = Number(button.dataset.id); const action = button.dataset.periodAction; button.disabled = true;
      try {
      if (action === 'schedule') await showSchedule(id);
      if (action === 'my-results') await showResults(id, true);
      if (action === 'results') await showResults(id);
        if (action === 'publish' || action === 'open') { await call(`/academic/exam-periods/${id}/${action === 'publish' ? 'publish' : 'open-results'}`, 'POST', {}); notify(action === 'publish' ? 'Timetable published.' : 'Results entry opened.', 'success'); await loadPeriods(); }
      } catch (error) { notify(error.message || 'Unable to complete this action.', 'error'); }
      finally { button.disabled = false; }
    });
    $('examPeriodsCsv')?.addEventListener('click', () => { if (exportAllowed) exportTable('examPeriodsTable', 'exam-periods.csv'); });
    $('examPeriodsPrint')?.addEventListener('click', () => { if (printAllowed) window.print(); });
  }
  document.addEventListener('DOMContentLoaded', async () => {
    if (!$('examPeriodWorkflow')) return;
    await window.AuthContext?.ready?.();
    attach();
    try { await loadOptions(); } catch (error) { notify(error.message || 'Unable to load term and class options.', 'error'); }
    await loadPeriods();
  });
})();
