/**
 * Staff Onboarding Controller
 * API: GET/POST /staff/onboarding
 *      PUT       /staff/onboarding/{id}
 *      PUT       /staff/onboarding-task/{id}
 *      POST      /staff/onboarding-document
 *      POST      /staff/probation-review
 */
const staffOnboardingController = {
  _data: [], _filtered: [],
  _currentId: null,
  _currentDetail: null,
  _modals: {},
  _offcanvas: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) return;
    this._canCreate  = AuthContext.hasPermission('staff.create') || AuthContext.hasPermission('hr.manage');
    this._canApprove = AuthContext.hasPermission('staff.approve') || AuthContext.hasPermission('hr.manage');

    const addBtn = document.getElementById('newOnboardingBtn');
    if (addBtn) addBtn.style.display = this._canCreate ? '' : 'none';

    this._modals.initiate  = new bootstrap.Modal(document.getElementById('initiateOnboardModal'));
    this._modals.doc       = new bootstrap.Modal(document.getElementById('obDocModal'));
    this._modals.review    = new bootstrap.Modal(document.getElementById('obReviewModal'));
    this._offcanvas = new bootstrap.Offcanvas(document.getElementById('obDetailOffcanvas'));

    this._bindFilters();
    await Promise.all([this._loadDepartments(), this._loadStaffList(), this._load()]);

    // Set default date on initiate modal
    document.getElementById('ob_start_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('rev_date').value       = new Date().toISOString().split('T')[0];
  },

  _bindFilters: function () {
    ['obSearch','obStatusFilter','obDeptFilter'].forEach(id => {
      document.getElementById(id)?.addEventListener('input',  () => this._applyFilters());
      document.getElementById(id)?.addEventListener('change', () => this._applyFilters());
    });
  },

  _load: async function () {
    const grid = document.getElementById('obCardGrid');
    if (grid) grid.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-success"></div></div>';
    try {
      const r    = await callAPI('/staff/onboarding', 'GET');
      const resp = r?.data || r;
      this._data     = Array.isArray(resp?.onboardings) ? resp.onboardings : [];
      this._filtered = [...this._data];
      this._setStats(resp?.stats || {});
      this._renderCards();
    } catch (e) {
      if (grid) grid.innerHTML = `<div class="col-12 text-danger text-center py-4">${e.message||'Load failed'}</div>`;
    }
  },

  _loadDepartments: async function () {
    try {
      const r = await callAPI('/staff/departments/get', 'GET');
      const list = r?.data || r || [];
      const sel = document.getElementById('obDeptFilter');
      if (sel) sel.innerHTML = '<option value="">All Departments</option>' +
        list.map(d => `<option value="${d.id}">${this._esc(d.name)}</option>`).join('');
    } catch (e) {}
  },

  _loadStaffList: async function () {
    try {
      const r = await callAPI('/staff/all', 'GET');
      const list = r?.data || r || [];
      const staffOpts = list.map(s =>
        `<option value="${s.id}">${this._esc(s.full_name||(s.first_name+' '+s.last_name))} (${s.staff_no||'—'})</option>`
      ).join('');
      document.getElementById('ob_staff_id') && (document.getElementById('ob_staff_id').innerHTML = '<option value="">— Select staff member —</option>' + staffOpts);
      document.getElementById('ob_mentor_id') && (document.getElementById('ob_mentor_id').innerHTML = '<option value="">— No mentor —</option>' + staffOpts);
    } catch (e) {}
  },

  _setStats: function (s) {
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('obStatTotal',      s.total      || 0);
    set('obStatInProgress', s.in_progress || 0);
    set('obStatCompleted',  s.completed  || 0);
    set('obStatOverdue',    s.overdue    || 0);
    set('obStatPending',    s.pending    || 0);
  },

  _applyFilters: function () {
    const q    = (document.getElementById('obSearch')?.value      || '').toLowerCase();
    const st   = document.getElementById('obStatusFilter')?.value || '';
    const dept = document.getElementById('obDeptFilter')?.value   || '';
    this._filtered = this._data.filter(ob => {
      if (st   && ob.status !== st)       return false;
      if (dept && String(ob.department_id) !== dept) return false;
      if (q    && !(ob.staff_name||'').toLowerCase().includes(q)
               && !(ob.staff_no  ||'').toLowerCase().includes(q)) return false;
      return true;
    });
    this._renderCards();
  },

  clearFilters: function () {
    ['obSearch','obStatusFilter','obDeptFilter'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    this._applyFilters();
  },

  _renderCards: function () {
    const grid = document.getElementById('obCardGrid');
    if (!grid) return;
    if (!this._filtered.length) {
      grid.innerHTML = '<div class="col-12 text-center py-5 text-muted"><i class="bi bi-person-check fs-1 d-block mb-2"></i>No onboarding records found.</div>';
      return;
    }

    const stColors = {pending:'secondary',in_progress:'warning',completed:'success',extended:'info',terminated:'danger'};
    const catIcons = {documentation:'folder2',hr_admin:'briefcase',it_setup:'laptop',
                      finance_setup:'cash-coin',academic:'book',welfare:'heart',probation:'star'};

    grid.innerHTML = this._filtered.map(ob => {
      const pct       = ob.progress_percent || 0;
      const barColor  = pct >= 80 ? 'bg-success' : pct >= 50 ? 'bg-warning' : 'bg-danger';
      const overdue   = (ob.overdue_tasks  || 0) > 0;
      const daysLeft  = ob.days_remaining;

      return `<div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 ${overdue ? 'border-danger border-2' : ''}">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <div class="fw-bold">${this._esc(ob.staff_name||'—')}</div>
                <div class="small text-muted">${this._esc(ob.staff_no||'—')} · ${this._esc(ob.position||'—')}</div>
                <div class="small text-muted">${this._esc(ob.department||'—')}</div>
              </div>
              <span class="badge bg-${stColors[ob.status]||'secondary'}">${ob.status||'—'}</span>
            </div>
            <div class="mb-2">
              <div class="d-flex justify-content-between small mb-1">
                <span>${ob.done_tasks||0}/${ob.total_tasks||0} tasks</span>
                <span class="${overdue?'text-danger fw-bold':''}">${overdue?`⚠ ${ob.overdue_tasks} overdue`:''}</span>
                <span class="fw-semibold">${pct}%</span>
              </div>
              <div class="progress" style="height:7px">
                <div class="progress-bar ${barColor}" style="width:${pct}%"></div>
              </div>
            </div>
            <div class="row g-1 small text-muted mb-2">
              <div class="col-auto"><i class="bi bi-calendar3 me-1"></i>Joined: ${ob.start_date||'—'}</div>
              <div class="col-auto"><i class="bi bi-calendar-x me-1"></i>Target: ${ob.target_completion||'—'}</div>
              ${ob.mentor_name ? `<div class="col-auto"><i class="bi bi-person-fill me-1"></i>Mentor: ${this._esc(ob.mentor_name)}</div>` : ''}
              ${daysLeft !== null && daysLeft >= 0 ? `<div class="col-auto ms-auto text-${daysLeft<14?'danger':'success'} fw-semibold">${daysLeft}d left</div>` : ''}
            </div>
            <button class="btn btn-sm btn-outline-success w-100" onclick="staffOnboardingController.openDetail(${ob.onboarding_id})">
              <i class="bi bi-list-check me-1"></i>View Tasks & Progress
            </button>
          </div>
        </div>
      </div>`;
    }).join('');
  },

  openDetail: async function (onboardingId) {
    this._currentId = onboardingId;
    try {
      const r = await callAPI('/staff/onboarding/' + onboardingId, 'GET');
      this._currentDetail = r?.data || r;
      this._renderDetail();
      this._offcanvas.show();
    } catch (e) {
      showNotification(e.message || 'Failed to load detail.', 'danger');
    }
  },

  _renderDetail: function () {
    const d  = this._currentDetail;
    const ob = d?.onboarding;
    if (!ob) return;

    this._set('obDetailName', ob.staff_name || '—');
    this._set('obDetailMeta', `${ob.position||'—'} · ${ob.department||'—'} · ${ob.contract_type||'probation'}`);

    const pct = ob.progress_percent || 0;
    this._set('obDetailPct', pct + '%');
    const bar = document.getElementById('obDetailProgressBar');
    if (bar) bar.style.width = pct + '%';

    this._set('obDoneCount',    ob.done_tasks    || 0);
    this._set('obPendingCount', ob.pending_tasks || 0);
    this._set('obOverdueCount', ob.overdue_tasks || 0);
    this._set('obDaysLeft',     ob.days_remaining >= 0 ? ob.days_remaining + ' days' : 'Overdue');

    // Tasks
    this._renderTasks(d.tasks || []);
    this._renderDocs(d.documents  || []);
    this._renderReviews(d.reviews || []);
  },

  _renderTasks: function (tasks) {
    const el = document.getElementById('obTasksList');
    if (!el) return;
    if (!tasks.length) { el.innerHTML = '<p class="text-muted text-center py-3">No tasks found.</p>'; return; }

    const catColors = {documentation:'primary',hr_admin:'warning',it_setup:'info',
                       finance_setup:'success',academic:'danger',welfare:'secondary',probation:'dark'};
    const catLabels = {documentation:'Documents',hr_admin:'HR Admin',it_setup:'IT Setup',
                       finance_setup:'Finance',academic:'Academic',welfare:'Welfare',probation:'Probation'};

    const grouped = {};
    tasks.forEach(t => { if (!grouped[t.category]) grouped[t.category] = []; grouped[t.category].push(t); });

    el.innerHTML = Object.entries(grouped).map(([cat, catTasks]) => `
      <div class="mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge bg-${catColors[cat]||'secondary'}">${catLabels[cat]||cat}</span>
          <small class="text-muted">${catTasks.filter(t=>t.status==='completed').length}/${catTasks.length} done</small>
        </div>
        ${catTasks.map(t => {
          const overdue = t.status !== 'completed' && t.status !== 'skipped' && t.due_date < new Date().toISOString().split('T')[0];
          return `<div class="d-flex align-items-center gap-2 py-2 border-bottom ${overdue?'bg-danger bg-opacity-10 rounded px-2':''}">
            <input type="checkbox" class="form-check-input flex-shrink-0" style="width:18px;height:18px"
                   ${t.status==='completed'?'checked':''} ${t.status==='completed'?'disabled':''}
                   onchange="staffOnboardingController.toggleTask(${t.id}, this.checked)">
            <div class="flex-grow-1">
              <div class="fw-semibold small ${t.status==='completed'?'text-decoration-line-through text-muted':''}">${this._esc(t.task_name)}</div>
              <div class="text-muted" style="font-size:.75rem">${this._esc(t.description||'')} · Due: ${t.due_date||'—'} ${overdue?'<span class="text-danger">OVERDUE</span>':''}</div>
            </div>
            <span class="badge bg-${t.status==='completed'?'success':t.status==='blocked'?'danger':'secondary'} ms-1" style="font-size:.65rem">${t.status}</span>
          </div>`;
        }).join('')}
      </div>
    `).join('');
  },

  _renderDocs: function (docs) {
    const el = document.getElementById('obDocsList');
    if (!el) return;
    if (!docs.length) { el.innerHTML = '<p class="text-muted text-center py-3">No documents collected yet.</p>'; return; }
    el.innerHTML = `<div class="list-group">${docs.map(doc => `
      <div class="list-group-item py-2">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-semibold small">${this._esc(doc.document_type||'—').replace(/_/g,' ')}</div>
            <div class="text-muted" style="font-size:.75rem">${this._esc(doc.document_name||'—')}</div>
          </div>
          <div class="d-flex gap-1">
            ${doc.is_original_seen?'<span class="badge bg-success">Original</span>':''}
            ${doc.is_copy_filed?'<span class="badge bg-primary">Filed</span>':''}
          </div>
        </div>
      </div>`).join('')}</div>`;
  },

  _renderReviews: function (reviews) {
    const el = document.getElementById('obReviewsList');
    if (!el) return;
    if (!reviews.length) { el.innerHTML = '<p class="text-muted text-center py-3">No reviews recorded yet.</p>'; return; }
    const outcomeColor = {continue:'secondary',extend_probation:'warning',confirm_permanent:'success',terminate:'danger'};
    el.innerHTML = `<div class="list-group">${reviews.map(r => `
      <div class="list-group-item py-2">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-semibold">Month ${r.review_month} Review — ${r.review_date||'—'}</div>
            <div class="small text-muted">Reviewed by: ${this._esc(r.reviewer_name||'—')}</div>
            ${r.strengths?`<div class="small mt-1"><strong>Strengths:</strong> ${this._esc(r.strengths)}</div>`:''}
            ${r.areas_to_improve?`<div class="small"><strong>Improve:</strong> ${this._esc(r.areas_to_improve)}</div>`:''}
          </div>
          <div class="text-end">
            <span class="badge bg-${outcomeColor[r.outcome]||'secondary'} d-block mb-1">${(r.outcome||'').replace(/_/g,' ')}</span>
            <span class="badge bg-light text-dark border">${r.overall_rating||'—'}</span>
          </div>
        </div>
      </div>`).join('')}</div>`;
  },

  toggleTask: async function (taskId, checked) {
    try {
      await callAPI('/staff/onboarding-task/' + taskId, 'PUT', {
        status: checked ? 'completed' : 'in_progress'
      });
      showNotification(checked ? 'Task marked complete.' : 'Task re-opened.', 'success');
      await this.openDetail(this._currentId); // Refresh
      await this._load(); // Refresh progress on card
    } catch (e) {
      showNotification(e.message || 'Update failed.', 'danger');
    }
  },

  // ── Initiate onboarding ────────────────────────────────────────────────────
  showInitiateModal: function () { this._modals.initiate.show(); },

  initiateOnboarding: async function () {
    const staffId = document.getElementById('ob_staff_id')?.value;
    const start   = document.getElementById('ob_start_date')?.value;
    if (!staffId || !start) { showNotification('Staff member and start date are required.', 'warning'); return; }
    try {
      const r = await callAPI('/staff/onboarding', 'POST', {
        staff_id:         staffId,
        start_date:       start,
        probation_months: document.getElementById('ob_probation_months')?.value || 3,
        contract_type:    document.getElementById('ob_contract_type')?.value    || 'probation',
        mentor_id:        document.getElementById('ob_mentor_id')?.value        || null,
        notes:            document.getElementById('ob_notes')?.value            || null,
      });
      const res = r?.data || r;
      showNotification(`Onboarding started. ${res.tasks_created} tasks generated automatically.`, 'success');
      this._modals.initiate.hide();
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Failed to start onboarding.', 'danger');
    }
  },

  // ── Document ───────────────────────────────────────────────────────────────
  showDocModal: function () {
    const ob = this._currentDetail?.onboarding;
    if (!ob) return;
    document.getElementById('doc_onboarding_id').value = ob.onboarding_id;
    document.getElementById('doc_staff_id').value      = ob.staff_id;
    document.getElementById('doc_type').value          = '';
    document.getElementById('doc_name').value          = '';
    document.getElementById('doc_original_seen').checked = false;
    document.getElementById('doc_copy_filed').checked    = false;
    document.getElementById('doc_notes').value = '';
    this._modals.doc.show();
  },

  saveDocument: async function () {
    const docType = document.getElementById('doc_type')?.value;
    if (!docType) { showNotification('Document type required.', 'warning'); return; }
    try {
      await callAPI('/staff/onboarding-document', 'POST', {
        onboarding_id:    document.getElementById('doc_onboarding_id').value,
        staff_id:         document.getElementById('doc_staff_id').value,
        document_type:    docType,
        document_name:    document.getElementById('doc_name').value     || null,
        is_original_seen: document.getElementById('doc_original_seen').checked ? 1 : 0,
        is_copy_filed:    document.getElementById('doc_copy_filed').checked     ? 1 : 0,
        notes:            document.getElementById('doc_notes').value || null,
      });
      showNotification('Document recorded.', 'success');
      this._modals.doc.hide();
      await this.openDetail(this._currentId);
    } catch (e) {
      showNotification(e.message || 'Save failed.', 'danger');
    }
  },

  // ── Probation Review ───────────────────────────────────────────────────────
  showReviewModal: function () {
    const ob = this._currentDetail?.onboarding;
    if (!ob) return;
    document.getElementById('rev_onboarding_id').value = ob.onboarding_id;
    document.getElementById('rev_staff_id').value      = ob.staff_id;
    document.getElementById('rev_date').value          = new Date().toISOString().split('T')[0];
    this._modals.review.show();
  },

  onOutcomeChange: function () {
    const outcome = document.getElementById('rev_outcome')?.value;
    const extRow  = document.getElementById('revExtendMonthsRow');
    if (extRow) extRow.style.display = outcome === 'extend_probation' ? '' : 'none';
  },

  saveReview: async function () {
    const outcome = document.getElementById('rev_outcome')?.value;
    if (!outcome) { showNotification('Outcome is required.', 'warning'); return; }
    try {
      await callAPI('/staff/probation-review', 'POST', {
        onboarding_id:    document.getElementById('rev_onboarding_id').value,
        staff_id:         document.getElementById('rev_staff_id').value,
        review_month:     document.getElementById('rev_month').value,
        review_date:      document.getElementById('rev_date').value,
        overall_rating:   document.getElementById('rev_rating').value,
        attendance_score: document.getElementById('rev_attendance').value  || null,
        performance_score:document.getElementById('rev_performance').value || null,
        conduct_score:    document.getElementById('rev_conduct').value     || null,
        strengths:        document.getElementById('rev_strengths').value   || null,
        areas_to_improve: document.getElementById('rev_areas').value       || null,
        outcome,
        extend_months:    document.getElementById('rev_extend_months')?.value || null,
        outcome_notes:    document.getElementById('rev_notes').value || null,
      });
      const msgs = {
        confirm_permanent: 'Staff confirmed as permanent. Contract updated.',
        extend_probation:  'Probation extended.',
        terminate:         'Employment terminated. Staff deactivated.',
        continue:          'Review saved. Probation continues.',
      };
      showNotification(msgs[outcome] || 'Review saved.', outcome === 'terminate' ? 'warning' : 'success');
      this._modals.review.hide();
      await this.openDetail(this._currentId);
      await this._load();
    } catch (e) {
      showNotification(e.message || 'Save failed.', 'danger');
    }
  },

  showPendingPanel: async function () {
    try {
      const r    = await callAPI('/staff/onboarding-pending', 'GET');
      const rows = r?.data || r || [];
      if (!rows.length) { showNotification('No pending onboarding actions right now.', 'info'); return; }
      const overdueCount = rows.filter(r => r.is_overdue).length;
      showNotification(`${rows.length} pending tasks (${overdueCount} overdue). Check the onboarding cards.`, overdueCount > 0 ? 'warning' : 'info');
    } catch (e) {}
  },

  _set: (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};
