/** Student performance overview and individual report controller. */
const studentPerformanceController = (() => {
  const state = { years: [], terms: [], classes: [], streams: [], rows: [], summary: {}, studentId: null };
  const $ = (id) => document.getElementById(id);
  const payloadOf = (response) => response?.data ?? response ?? {};
  const rowsOf = (response) => {
    const payload = payloadOf(response);
    return Array.isArray(payload) ? payload : Array.isArray(payload.rows) ? payload.rows : [];
  };
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[character]);
  const setText = (id, value) => { if ($(id)) $(id).textContent = String(value ?? '—'); };

  async function get(path, params = {}) {
    return window.API.apiCall(path, 'GET', null, params, { checkPermission: false });
  }

  function showState({ loading = false, error = '', empty = false } = {}) {
    $('overviewLoading')?.classList.toggle('d-none', !loading);
    $('overviewError')?.classList.toggle('d-none', !error);
    if ($('overviewError')) $('overviewError').textContent = error;
    $('overviewEmpty')?.classList.toggle('d-none', !empty);
    $('overviewCard')?.classList.toggle('d-none', empty && !state.rows.length);
  }

  async function loadReferences() {
    const [years, classes, streams] = await Promise.all([
      get('/academic/years/list'), get('/academic/classes-list'), get('/academic/streams-list'),
    ]);
    state.years = rowsOf(years);
    state.classes = rowsOf(classes);
    state.streams = rowsOf(streams);
    const yearSelect = $('academicYearFilter');
    if (yearSelect) {
      yearSelect.innerHTML = '<option value="">Current Academic Year</option>' + state.years.map((year) => `<option value="${Number(year.id)}">${escapeHtml(year.year_name || year.name || year.year_code)}</option>`).join('');
      const current = state.years.find((year) => Boolean(Number(year.is_current)) || year.status === 'active');
      if (current) yearSelect.value = String(current.id);
    }
    const classSelect = $('classFilter');
    if (classSelect) classSelect.innerHTML = '<option value="">All Classes</option>' + state.classes.map((item) => `<option value="${Number(item.id)}">${escapeHtml(item.name || item.class_name)}</option>`).join('');
    renderStreamOptions();
    await loadTerms();
  }

  function renderStreamOptions() {
    const select = $('streamFilter');
    if (!select) return;
    const classId = $('classFilter')?.value || '';
    const streams = state.streams.filter((stream) => !classId || String(stream.class_id) === classId);
    select.innerHTML = '<option value="">All Streams</option>' + streams.map((stream) => `<option value="${Number(stream.stream_id || stream.id)}">${escapeHtml(stream.stream_name || stream.name)}</option>`).join('');
  }

  async function loadTerms() {
    const yearId = $('academicYearFilter')?.value || '';
    const termsResponse = await get('/academic/terms', yearId ? { academic_year_id: yearId } : {});
    state.terms = rowsOf(termsResponse);
    const select = $('termFilter');
    const previous = select?.value || '';
    if (select) {
      select.innerHTML = '<option value="">All Terms</option>' + state.terms.map((term) => `<option value="${Number(term.id)}">${escapeHtml(term.name || term.term_name)}</option>`).join('');
      if (state.terms.some((term) => String(term.id) === previous)) select.value = previous;
    }
  }

  function currentFilters() {
    const filters = {
      view_mode: $('viewMode')?.value || 'students',
      year_id: $('academicYearFilter')?.value || '',
      term_id: $('termFilter')?.value || '',
      class_id: $('classFilter')?.value || '',
      stream_id: $('streamFilter')?.value || '',
      gender: $('genderFilter')?.value || '',
      search: $('studentSearch')?.value.trim() || '',
      page: 1,
      limit: 500,
    };
    Object.keys(filters).forEach((key) => { if (filters[key] === '') delete filters[key]; });
    return filters;
  }

  async function load() {
    showState({ loading: true });
    const body = $('performanceOverviewBody');
    if (body) body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading performance records…</td></tr>';
    try {
      const response = await get('/academic/performance-overview', currentFilters());
      const data = payloadOf(response);
      state.rows = Array.isArray(data.rows) ? data.rows : [];
      state.summary = data.summary || {};
      render();
      showState({ empty: state.rows.length === 0 });
    } catch (error) {
      console.error('Student performance overview failed:', error);
      state.rows = [];
      if (body) body.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Could not load performance records.</td></tr>';
      showState({ error: error.message || 'Could not load performance records.' });
    }
  }

  function render() {
    const view = $('viewMode')?.value || 'students';
    const head = $('overviewTableHead');
    const body = $('performanceOverviewBody');
    const labels = {
      students: ['#', 'Admission No.', 'Learner', 'Class', 'Stream', 'Average', 'Grade', 'Attendance', 'Position'],
      class: ['Class', 'Learners', 'Average', 'Attendance', 'Top learner', 'Lowest learner'],
      stream: ['Class', 'Stream', 'Learners', 'Average', 'Attendance', 'Top learner'],
      school: ['Scope', 'Learners', 'Average', 'Attendance', 'Top class', 'Top learner'],
    }[view] || [];
    if (head) head.innerHTML = `<tr>${labels.map((label) => `<th>${escapeHtml(label)}</th>`).join('')}</tr>`;
    if (body) {
      body.innerHTML = state.rows.map((row, index) => {
        if (view === 'students') return `<tr><td>${index + 1}</td><td>${escapeHtml(row.admission_no || '—')}</td><td><button type="button" class="btn btn-link p-0 text-start" data-student-id="${Number(row.student_id)}">${escapeHtml(row.full_name || '—')}</button></td><td>${escapeHtml(row.class_name || '—')}</td><td>${escapeHtml(row.stream_name || '—')}</td><td>${row.average_score == null ? '—' : `${Number(row.average_score).toFixed(1)}%`}</td><td>${escapeHtml(row.grade || '—')}</td><td>${Number(row.attendance_rate || 0).toFixed(1)}%</td><td>${escapeHtml(row.position || '—')}</td></tr>`;
        if (view === 'class') return `<tr><td>${escapeHtml(row.class_name || '—')}</td><td>${Number(row.total_students || 0)}</td><td>${Number(row.average_score || 0).toFixed(1)}%</td><td>${Number(row.attendance_rate || 0).toFixed(1)}%</td><td>${escapeHtml(row.top_student || '—')}</td><td>${escapeHtml(row.lowest_student || '—')}</td></tr>`;
        if (view === 'stream') return `<tr><td>${escapeHtml(row.class_name || '—')}</td><td>${escapeHtml(row.stream_name || '—')}</td><td>${Number(row.total_students || 0)}</td><td>${Number(row.average_score || 0).toFixed(1)}%</td><td>${Number(row.attendance_rate || 0).toFixed(1)}%</td><td>${escapeHtml(row.top_student || '—')}</td></tr>`;
        return `<tr><td>${escapeHtml(row.scope || 'Whole School')}</td><td>${Number(row.total_students || 0)}</td><td>${Number(row.average_score || 0).toFixed(1)}%</td><td>${Number(row.attendance_rate || 0).toFixed(1)}%</td><td>${escapeHtml(row.top_class || '—')}</td><td>${escapeHtml(row.top_student || '—')}</td></tr>`;
      }).join('') || `<tr><td colspan="${labels.length}" class="text-center text-muted py-4">No performance records found for the selected filters.</td></tr>`;
      body.querySelectorAll('[data-student-id]').forEach((button) => button.addEventListener('click', () => openStudent(button.dataset.studentId)));
    }
    setText('summaryStudents', state.summary.total_students ?? state.rows.reduce((sum, row) => sum + Number(row.total_students ?? 1), 0));
    const average = state.summary.average_score;
    setText('summaryAverage', average == null ? '—' : `${Number(average).toFixed(1)}%`);
    setText('summaryTopStudent', state.summary.top_student || '—');
    setText('summaryBestGroup', state.summary.best_group || '—');
    setText('viewModeBadge', `${view.charAt(0).toUpperCase()}${view.slice(1)} View`);
  }

  function bindFilters() {
    $('viewMode')?.addEventListener('change', load);
    $('academicYearFilter')?.addEventListener('change', async () => { await loadTerms(); await load(); });
    $('termFilter')?.addEventListener('change', load);
    $('classFilter')?.addEventListener('change', () => { renderStreamOptions(); load(); });
    ['streamFilter', 'genderFilter'].forEach((id) => $(id)?.addEventListener('change', load));
    let searchTimer;
    $('studentSearch')?.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(load, 300); });
    $('resetFiltersBtn')?.addEventListener('click', async () => {
      if ($('viewMode')) $('viewMode').value = 'students';
      if ($('academicYearFilter')) $('academicYearFilter').value = '';
      if ($('termFilter')) $('termFilter').value = '';
      if ($('classFilter')) $('classFilter').value = '';
      if ($('streamFilter')) $('streamFilter').value = '';
      if ($('genderFilter')) $('genderFilter').value = '';
      if ($('studentSearch')) $('studentSearch').value = '';
      await loadTerms();
      await load();
    });
  }

  function exportOverview() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport()) return;
    const rows = state.rows;
    if (!rows.length) return;
    const view = $('viewMode')?.value || 'students';
    const columns = view === 'students'
      ? [['Admission No.', 'admission_no'], ['Learner', 'full_name'], ['Class', 'class_name'], ['Stream', 'stream_name'], ['Average', 'average_score'], ['Grade', 'grade'], ['Attendance', 'attendance_rate'], ['Position', 'position']]
      : Object.keys(rows[0]).map((key) => [key.replaceAll('_', ' '), key]);
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [columns.map(([label]) => label), ...rows.map((row) => columns.map(([, key]) => row[key]))].map((row) => row.map(cell).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'student_performance.csv', 'text/csv;charset=utf-8;');
  }

  function bindActions() {
    $('exportOverviewBtn')?.addEventListener('click', exportOverview);
    $('printOverviewBtn')?.addEventListener('click', () => {
      if (window.AuthContext?.canPrint && !window.AuthContext.canPrint()) return;
      setText('printDate', new Date().toLocaleDateString());
      window.print();
    });
    $('printStudentReportBtn')?.addEventListener('click', () => {
      if (window.AuthContext?.canPrint && !window.AuthContext.canPrint()) return;
      window.print();
    });
    $('reloadStudentReportBtn')?.addEventListener('click', () => state.studentId && openStudent(state.studentId));
  }

  async function openStudent(studentId) {
    state.studentId = Number(studentId);
    $('modalLoading')?.classList.remove('d-none');
    $('modalError')?.classList.add('d-none');
    const modal = $('studentPerformanceModal');
    if (modal && window.bootstrap?.Modal) bootstrap.Modal.getOrCreateInstance(modal).show();
    const termSelect = $('modalTerm');
    if (termSelect) {
      termSelect.innerHTML = '<option value="">Current year / all terms</option>' + state.terms.map((term) => `<option value="${Number(term.id)}">${escapeHtml(term.name || term.term_name)}</option>`).join('');
      termSelect.value = $('termFilter')?.value || '';
    }
    try {
      const resultResponse = await get('/academic/student-results', {
        student_id: state.studentId,
        ...(termSelect?.value ? { term_id: termSelect.value } : {}),
      });
      const report = payloadOf(resultResponse);
      const student = report.student || {};
      const summary = report.summary || {};
      const performance = Array.isArray(report.subjects) ? report.subjects : [];
      setText('studentName', student.full_name || student.name || `${student.first_name || ''} ${student.last_name || ''}`.trim());
      setText('admNo', student.admission_no || student.admission_number || '—');
      setText('studentClass', student.class_name || '—');
      setText('stream', student.stream_name || '—');
      setText('overallAvg', summary.percentage == null ? '—' : `${Number(summary.percentage).toFixed(1)}%`);
      setText('overallGrade', summary.grade || '—');
      setText('position', summary.class_rank || summary.stream_rank || '—');
      setText('attendanceRate', summary.attendance_percentage == null ? '—' : `${Number(summary.attendance_percentage).toFixed(1)}%`);
      setText('meanScore', summary.percentage == null ? '—' : `${Number(summary.percentage).toFixed(1)}%`);
      setText('subjectsCount', performance.length);
      const photo = $('studentPhoto');
      if (photo) photo.src = student.photo_url || '';
      const subjects = $('subjectsTableBody');
      if (subjects) subjects.innerHTML = performance.length ? performance.map((row) => `<tr><td>${escapeHtml(row.subject_name || row.subject || '—')}</td><td>${escapeHtml(row.percentage ?? row.score ?? '—')}</td><td>${escapeHtml(row.grade || '—')}</td><td>${escapeHtml(row.class_average ?? '—')}</td><td>${escapeHtml(row.position ?? '—')}</td><td>${escapeHtml(row.teacher_name || row.teacher || '—')}</td><td>${escapeHtml(row.remarks || '—')}</td></tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted">No subject results available.</td></tr>';
      $('modalReportContent')?.classList.remove('d-none');
    } catch (error) {
      if ($('modalError')) { $('modalError').textContent = error.message || 'Could not load this learner report.'; $('modalError').classList.remove('d-none'); }
    } finally {
      $('modalLoading')?.classList.add('d-none');
    }
  }

  async function loadAiSupportReviews(scope = 'own') {
    const host = $('aiSupportPlanningReviews');
    const list = $('aiSupportPlanningReviewsList');
    const status = $('aiSupportPlanningReviewsStatus');
    if (!host || !list) return;
    host.hidden = false;
    if (status) status.textContent = 'Loading support review drafts…';
    try {
      const response = await window.API.students.getAiSupportPlanningReviews(scope);
      const drafts = payloadOf(response).drafts || response?.drafts || [];
      list.innerHTML = drafts.length ? drafts.map((draft) => {
        const content = draft.draft || {};
        const canReview = scope === 'review' && draft.status === 'pending_approval';
        return `<div class="col-12"><article class="border rounded p-3 bg-white"><div class="d-flex justify-content-between gap-2"><strong>${escapeHtml(content.title || 'Learner support review')}</strong><span class="badge text-bg-secondary">${escapeHtml(draft.status || 'queued')}</span></div><p class="small mt-2 mb-2">${escapeHtml(content.body || 'Review is being prepared.')}</p>${canReview ? `<button type="button" class="btn btn-sm btn-outline-success" data-ai-support-approve="${Number(draft.id)}">Approve for staff guidance</button>` : ''}<div class="small text-muted mt-2">Advisory only. This does not update learner records.</div></article></div>`;
      }).join('') : '<div class="col-12 small text-muted">No support review drafts in this view.</div>';
      if (status) status.textContent = `${drafts.length} draft${drafts.length === 1 ? '' : 's'} loaded.`;
      list.querySelectorAll('[data-ai-support-approve]').forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        try { await window.API.students.approveAiSupportPlanning(button.dataset.aiSupportApprove); await loadAiSupportReviews('review'); }
        catch (error) { if (status) status.textContent = error.message || 'Unable to approve this review.'; button.disabled = false; }
      }));
    } catch (error) { list.replaceChildren(); if (status) status.textContent = error.message || 'Unable to load support reviews.'; }
  }

  function bindAiSupportPlanning() {
    $('queueAiSupportPlanningBtn')?.addEventListener('click', async (event) => {
      const button = event.currentTarget;
      button.disabled = true;
      if ($('aiSupportPlanningStatus')) $('aiSupportPlanningStatus').textContent = 'Preparing an authorized aggregate support review…';
      try {
        const queued = await window.API.apiCall('/students/ai-support-planning-queue', 'POST', { context: 'oversight' });
        const jobId = payloadOf(queued).job_id;
        if ($('aiSupportPlanningStatus')) $('aiSupportPlanningStatus').textContent = jobId ? `Support review queued (job ${jobId}).` : 'Support review queued.';
        await loadAiSupportReviews('own');
      } catch (error) { if ($('aiSupportPlanningStatus')) $('aiSupportPlanningStatus').textContent = error.message || 'Unable to queue a support review.'; }
      finally { button.disabled = false; }
    });
    $('loadOwnAiSupportReviewsBtn')?.addEventListener('click', () => loadAiSupportReviews('own'));
    $('loadReviewAiSupportReviewsBtn')?.addEventListener('click', () => loadAiSupportReviews('review'));
  }

  async function init() {
    await window.AuthContext?.ready?.();
    bindFilters();
    bindActions();
    bindAiSupportPlanning();
    try {
      await loadReferences();
      await load();
    } catch (error) {
      console.error('Student performance page initialization failed:', error);
      showState({ error: error.message || 'Could not prepare performance filters.' });
    }
    window.APIRealtime?.register?.('student_performance', { refresh: load }, ['students', 'academic']);
  }

  return { init, load, openStudent };
})();

window.studentPerformanceController = studentPerformanceController;
document.addEventListener('DOMContentLoaded', () => studentPerformanceController.init());
