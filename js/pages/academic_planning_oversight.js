const academicPlanningOversight = {
  schemes: [], lessons: [], classes: [], reconciliation: [],
  esc(v) { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; },
  setHTML(selector, html) { const target = document.querySelector(selector); if (target) target.innerHTML = html; },
  setText(id, value) { const target = document.getElementById(id); if (target) target.textContent = String(value); },
  rows(items, cols, text = 'No records found for the current academic context.') { return items.length ? items : [{ __empty: true, __text: text, __cols: cols }]; },
  status(v) { const s = String(v || 'not_started').toLowerCase(); const cls = s === 'approved' ? 'success' : (s === 'submitted' || s === 'pending') ? 'warning text-dark' : s === 'rejected' ? 'danger' : 'secondary'; return `<span class="badge bg-${cls}">${this.esc(s.replace(/_/g, ' '))}</span>`; },
  emptyRow(x) { return x.__empty ? `<tr><td colspan="${x.__cols}" class="text-center text-muted apo-empty">${this.esc(x.__text)}</td></tr>` : ''; },
  getContext() { return { academic_year_id: window.AcademicContext?.getAcademicYearId?.() || '', term_id: window.AcademicContext?.getTermId?.() || '' }; },
  async load() {
    const context = this.getContext();
    const params = { ...context, limit: 100 };
    this.setText('apoContext', context.academic_year_id ? 'Current academic year and term · filters supplied automatically' : 'Current academic context');
    const results = await Promise.allSettled([
      window.API.apiCall('/academic/scheme-of-work-get', 'GET', null, params),
      window.API.academic.listLessonPlans(params),
      window.API.academic.getLessonPlansByClass({ ...context, page: 1, limit: 100 }),
      window.callAPI('academic/legacy-content-reconciliation', 'GET')
    ]);
    const val = i => results[i].status === 'fulfilled' ? (results[i].value?.data || results[i].value || {}) : {};
    const schemes = val(0), lessons = val(1), classes = val(2), recon = val(3);
    this.schemes = schemes.schemes || (Array.isArray(schemes) ? schemes : []);
    this.lessons = lessons.lesson_plans || lessons.data?.lesson_plans || (Array.isArray(lessons) ? lessons : []);
    this.classes = classes.data || classes.classes || (Array.isArray(classes) ? classes : []);
    this.reconciliation = recon.items || [];
    this.render();
  },
  render() {
    this.renderSchemes(); this.renderLessons(); this.renderClasses(); this.renderTeachers(); this.renderCoverage(); this.renderApproval();
    const pending = [...this.schemeGroups.map(x => this.groupStatus(x)), ...this.lessonGroups.map(x => this.lessonGroupStatus(x))]
      .filter(x => ['draft','submitted','pending'].includes(String(x).toLowerCase())).length;
    this.setText('apoSchemeCount', this.schemeGroups.length);
    this.setText('apoLessonCount', this.lessonGroups.length);
    this.setText('apoPendingCount', pending);
    this.setText('apoReconciliationCount', this.reconciliationGroups.length);
  },
  renderSchemes() {
    const q = (document.getElementById('apoSchemeSearch')?.value || '').toLowerCase();
    const items = this.schemes.filter(x => !q || [x.subject_name,x.learning_area_name,x.class_name,x.stream_name,x.teacher_name,x.strand_name,x.sub_strand_name].join(' ').toLowerCase().includes(q));
    const groups = {};
    items.forEach(x => {
      const key = [x.subject_id || x.learning_area_id || x.subject_name, x.class_id || x.class_name, x.stream_id || x.stream_name || '', x.teacher_id || x.teacher_name || ''].join('|');
      if (!groups[key]) groups[key] = { ...x, weeks: [], rows: [] };
      groups[key].rows.push(x);
      if (!groups[key].weeks.some(w => String(w.week_number) === String(x.week_number))) groups[key].weeks.push({ week_number: x.week_number, week_start: x.week_start, week_end: x.week_end });
    });
    this.schemeGroups = Object.values(groups).sort((a,b) => String(a.subject_name || '').localeCompare(String(b.subject_name || '')));
    this.setHTML('#apoSchemesTable tbody', this.rows(this.schemeGroups, 9).map((x,i) => this.emptyRow(x) || `<tr><td>${i+1}</td><td><strong>${this.esc(x.subject_name || x.learning_area_name || '—')}</strong></td><td>${this.esc(x.class_name || '—')}</td><td>${this.esc(x.stream_name || '—')}</td><td>${this.esc(x.teacher_name || '—')}</td><td><span class="badge bg-light text-dark border">${x.weeks.length} week${x.weeks.length === 1 ? '' : 's'}</span></td><td>${this.schemeProgress(x)}</td><td>${this.status(this.groupStatus(x))}</td><td><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-success" onclick="academicPlanningOversight.viewScheme(${i})"><i class="bi bi-eye me-1"></i>Review</button>${x.scheme_workbook_id && ['submitted','approved'].includes(this.groupStatus(x)) ? `<button type="button" class="btn btn-outline-warning" onclick="academicPlanningOversight.reopenRevision(${x.scheme_workbook_id})" title="Create teacher revision"><i class="bi bi-arrow-repeat"></i></button>` : ''}</div></td></tr>`).join(''));
  },
  schemeGroups: [],
  groupStatus(group) {
    const workbookStatus = String(group.workbook_status || '').toLowerCase();
    if (workbookStatus) return workbookStatus === 'submitted' ? 'submitted' : workbookStatus;
    const statuses = group.rows.map(x => String(x.status || 'draft').toLowerCase());
    if (statuses.every(x => x === 'approved')) return 'approved';
    if (statuses.some(x => x === 'submitted' || x === 'pending')) return 'submitted';
    if (statuses.some(x => x === 'approved')) return 'in progress';
    return statuses[0] || 'draft';
  },
  schemeProgress(group) {
    const total = group.weeks.length || 1;
    const complete = group.rows.filter(x => ['approved','submitted'].includes(String(x.status || '').toLowerCase())).length;
    const pct = Math.min(100, Math.round((complete / total) * 100));
    return `<div class="progress apo-progress"><div class="progress-bar bg-success" style="width:${pct}%"></div></div><small>${pct}%</small>`;
  },
  viewScheme(index) {
    const group = this.schemeGroups[index]; if (!group) return;
    const weeks = {};
    group.rows.forEach(x => { const k = String(x.week_number || '—'); (weeks[k] ||= []).push(x); });
    document.getElementById('apoSchemeModalTitle').textContent = `${group.subject_name || group.learning_area_name || 'Learning Area'} · ${group.class_name || 'Class'}${group.stream_name ? ' · ' + group.stream_name : ''}`;
    document.getElementById('apoSchemeModalMeta').textContent = `${group.teacher_name || 'Unassigned teacher'} · ${Object.keys(weeks).length} planned week${Object.keys(weeks).length === 1 ? '' : 's'} · ${this.groupStatus(group).replace(/_/g, ' ')}`;
    const weekKeys = Object.keys(weeks).sort((a,b) => Number(a) - Number(b));
    document.getElementById('apoSchemeModalBody').innerHTML = weekKeys.map(week => `<section class="card border-0 shadow-sm mb-3"><div class="card-header bg-success-subtle d-flex justify-content-between"><strong>Week ${this.esc(week)}</strong><small>${this.esc(weeks[week][0].week_start || '')}${weeks[week][0].week_end ? ' to ' + this.esc(weeks[week][0].week_end) : ''}</small></div><div class="card-body"><div class="row g-3">${weeks[week].map(x => `<div class="col-12"><div class="border rounded p-3"><div class="d-flex justify-content-between gap-2"><strong>${this.esc(x.title || 'Scheme entry')}</strong>${this.status(x.status)}</div><div class="small text-success mt-1"><strong>Strand:</strong> ${this.esc(x.strand_name || '—')} &nbsp; <strong>Sub-strand:</strong> ${this.esc(x.sub_strand_name || '—')}</div><div class="row g-2 mt-2 small"><div class="col-md-4"><strong>Learning activities</strong><div class="text-muted">${this.esc(x.activities || 'Not recorded')}</div></div><div class="col-md-4"><strong>Resources</strong><div class="text-muted">${this.esc(x.resources || 'Not recorded')}</div></div><div class="col-md-4"><strong>Assessment approach</strong><div class="text-muted">${this.esc(x.assessment_methods || 'Not recorded')}</div></div></div></div></div>`).join('')}</div></div></section>`).join('') || '<div class="apo-empty text-muted">No weekly entries found.</div>';
    const approve = document.getElementById('apoApproveScheme');
    if (approve) { approve.classList.toggle('d-none', !(group.scheme_workbook_id && this.groupStatus(group) === 'submitted')); approve.onclick = () => this.approveWorkbook(group.scheme_workbook_id); }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('apoSchemeModal')).show();
  },
  async approveWorkbook(workbookId) {
    if (!workbookId || !(await window.confirmAction('Approve complete workbook', 'Approve this complete scheme as the official version?'))) return;
    try { await window.API.academic.approveSchemeWorkbook(workbookId); window.showNotification?.('Complete scheme workbook approved and locked.', 'success'); bootstrap.Modal.getInstance(document.getElementById('apoSchemeModal'))?.hide(); await this.load(); }
    catch (error) { window.showNotification?.(error.message || 'Unable to approve workbook', 'danger'); }
  },
  async reopenRevision(workbookId) {
    const reason = window.prompt('Why should this workbook be revised?', 'Please add or correct the required planning details');
    if (reason === null) return;
    try {
      await window.API.academic.reopenSchemeWorkbookRevision(workbookId, { reason });
      window.showNotification?.('A new teacher revision draft was created. The official version remains preserved.', 'success');
      await this.load();
    } catch (error) { window.showNotification?.(error.message || 'Unable to reopen workbook', 'danger'); }
  },
  renderLessons() {
    const status = document.getElementById('apoLessonStatus')?.value || '';
    const items = this.lessons.filter(x => !status || String(x.status || '').toLowerCase() === status);
    const groups = {};
    items.forEach(x => {
      const key = [x.subject_id || x.learning_area_id || x.subject_name, x.class_id || x.class_name, x.stream_id || x.stream_name || '', x.teacher_id || x.teacher_name || ''].join('|');
      if (!groups[key]) groups[key] = { ...x, plans: [] };
      groups[key].plans.push(x);
    });
    this.lessonGroups = Object.values(groups).sort((a,b) => String(a.subject_name || '').localeCompare(String(b.subject_name || '')));
    this.setHTML('#apoLessonsTable tbody', this.rows(this.lessonGroups, 9, 'No lesson plans found for the current academic context.').map((x,i) => this.emptyRow(x) || `<tr><td>${i+1}</td><td><strong>${this.esc(x.subject_name || '—')}</strong></td><td>${this.esc(x.class_name || '—')}</td><td>${this.esc(x.stream_name || '—')}</td><td>${this.esc(x.teacher_name || '—')}</td><td><span class="badge bg-light text-dark border">${x.plans.length}</span></td><td>${this.esc(this.lessonDateRange(x.plans))}</td><td>${this.status(this.lessonGroupStatus(x))}</td><td><button type="button" class="btn btn-sm btn-outline-primary" onclick="academicPlanningOversight.viewLessons(${i})"><i class="bi bi-eye me-1"></i>View</button></td></tr>`).join(''));
  },
  lessonGroups: [],
  lessonGroupStatus(group) {
    const statuses = group.plans.map(x => String(x.status || 'draft').toLowerCase());
    if (statuses.every(x => x === 'approved')) return 'approved';
    if (statuses.some(x => x === 'submitted' || x === 'pending')) return 'submitted';
    if (statuses.some(x => x === 'approved')) return 'in progress';
    return statuses[0] || 'draft';
  },
  lessonDateRange(plans) {
    const dates = plans.map(x => x.lesson_date || x.date).filter(Boolean).sort();
    if (!dates.length) return 'No date';
    return dates[0] === dates[dates.length - 1] ? dates[0] : `${dates[0]} → ${dates[dates.length - 1]}`;
  },
  viewLessons(index) {
    const group = this.lessonGroups[index]; if (!group) return;
    document.getElementById('apoLessonModalTitle').textContent = `${group.subject_name || 'Learning Area'} · ${group.class_name || 'Class'}${group.stream_name ? ' · ' + group.stream_name : ''}`;
    document.getElementById('apoLessonModalMeta').textContent = `${group.teacher_name || 'Unassigned teacher'} · ${group.plans.length} lesson plan${group.plans.length === 1 ? '' : 's'} · ${this.lessonGroupStatus(group).replace(/_/g, ' ')}`;
    const plans = [...group.plans].sort((a,b) => String(a.lesson_date || a.date || '').localeCompare(String(b.lesson_date || b.date || '')));
    document.getElementById('apoLessonModalBody').innerHTML = `<div class="table-responsive"><table class="table table-hover apo-table"><thead><tr><th>Date</th><th>Lesson</th><th>Week</th><th>Scheme Link</th><th>Status</th></tr></thead><tbody>${plans.map(x => `<tr><td>${this.esc(x.lesson_date || x.date || '—')}</td><td>${this.esc(x.title || x.topic || '—')}</td><td>${this.esc(x.week_number ? 'Week ' + x.week_number : '—')}</td><td>${x.scheme_of_work_id ? '<span class="badge bg-success">Linked</span>' : '<span class="badge bg-warning text-dark">Missing</span>'}</td><td>${this.status(x.status)}</td></tr>`).join('')}</tbody></table></div>`;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('apoLessonModal')).show();
  },
  renderClasses() {
    this.setHTML('#apoClassesTable tbody', this.rows(this.classes, 5).map(x => this.emptyRow(x) || `<tr><td>${this.esc(x.class_name || x.name || '—')}</td><td>${this.esc(x.total_subjects || 0)}</td><td>${this.esc(x.with_plans || x.plans_count || 0)}</td><td>${this.esc(Math.max(0, Number(x.total_subjects || 0) - Number(x.with_plans || x.plans_count || 0)))}</td><td><div class="progress apo-progress"><div class="progress-bar bg-${Number(x.coverage_percentage || x.coverage || 0) >= 100 ? 'success' : 'warning'}" style="width:${Math.min(100, Number(x.coverage_percentage || x.coverage || 0))}%"></div></div><small>${this.esc(x.coverage_percentage || x.coverage || 0)}%</small></td></tr>`).join(''));
  },
  renderTeachers() {
    const map = {};
    this.lessons.forEach(x => { const k = x.teacher_name || 'Unassigned'; map[k] ||= { name:k, plans:0, approved:0, pending:0 }; map[k].plans++; if (x.status === 'approved') map[k].approved++; if (['submitted','draft','pending'].includes(String(x.status || '').toLowerCase())) map[k].pending++; });
    const items = Object.values(map).map(x => ({ ...x, coverage: x.plans ? Math.round((x.approved / x.plans) * 100) : 0 }));
    this.setHTML('#apoTeachersTable tbody', this.rows(items, 5, 'No lesson plans have been recorded for teachers in the current term.').map(x => this.emptyRow(x) || `<tr><td>${this.esc(x.name)}</td><td>${x.plans}</td><td>${x.approved}</td><td>${x.pending}</td><td>${x.coverage}%</td></tr>`).join(''));
  },
  renderCoverage() { const full=this.classes.filter(x=>Number(x.coverage_percentage||x.coverage||0)>=100).length, partial=this.classes.filter(x=>Number(x.coverage_percentage||x.coverage||0)>0&&Number(x.coverage_percentage||x.coverage||0)<100).length; this.setText('apoFullCoverage', full); this.setText('apoPartialCoverage', partial); this.setText('apoNoCoverage', Math.max(0,this.classes.length-full-partial)); },
  renderApproval() { const statuses = [...this.schemeGroups.map(x=>this.groupStatus(x)), ...this.lessonGroups.map(x=>this.lessonGroupStatus(x))].reduce((a,s)=>{s=String(s||'not_started');a[s]=(a[s]||0)+1;return a;},{}); this.setHTML('#apoApprovalCards', Object.entries(statuses).map(([s,n])=>`<div class="col-6 col-md-3"><div class="border rounded p-3"><strong>${n}</strong><br><small>${this.esc(s.replace(/_/g,' '))}</small></div></div>`).join('') || '<div class="col-12 text-muted">No planning records available.</div>'); const grouped={}; this.reconciliation.forEach(x=>{const key=[x.content_type||'unknown',x.status||'unknown',x.reason||'No reason recorded'].join('|');(grouped[key] ||= {type:x.content_type||'unknown',status:x.status||'unknown',reason:x.reason||'No reason recorded',items:[]}).items.push(x);}); const groups=Object.values(grouped); this.setHTML('#apoReconciliationTable tbody', this.rows(groups,5,'No unresolved academic-content reconciliation items.').map((x,i)=>this.emptyRow(x)||`<tr><td>${this.esc(x.type)}</td><td>${this.status(x.status)}</td><td>${this.esc(x.reason)}</td><td><span class="badge bg-light text-dark border">${x.items.length}</span></td><td><button type="button" class="btn btn-sm btn-outline-secondary" onclick="academicPlanningOversight.viewReconciliation(${i})">View</button></td></tr>`).join('')); this.reconciliationGroups=groups; },
  reconciliationGroups: [],
  viewReconciliation(index) { const group=this.reconciliationGroups[index]; if(!group)return; document.getElementById('apoSchemeModalTitle').textContent=`${group.type} reconciliation`; document.getElementById('apoSchemeModalMeta').textContent=`${group.items.length} records · ${group.status}`; document.getElementById('apoSchemeModalBody').innerHTML=`<div class="alert alert-warning small">These records are preserved for controlled reconciliation and are not part of current teacher workflows.</div><div class="table-responsive"><table class="table table-hover apo-table"><thead><tr><th>Record</th><th>Title</th><th>Reason</th><th>Status</th></tr></thead><tbody>${group.items.map(x=>`<tr><td>#${this.esc(x.content_id)}</td><td>${this.esc(x.title||'—')}</td><td>${this.esc(x.reason||'—')}</td><td>${this.status(x.status)}</td></tr>`).join('')}</tbody></table></div>`; bootstrap.Modal.getOrCreateInstance(document.getElementById('apoSchemeModal')).show(); },
  async init() { document.getElementById('apoRefresh')?.addEventListener('click',()=>this.load().catch(e=>window.showNotification?.(e.message||'Unable to refresh','danger'))); document.getElementById('apoSchemeSearch')?.addEventListener('input',()=>this.renderSchemes()); document.getElementById('apoLessonStatus')?.addEventListener('change',()=>this.renderLessons()); try { await window.AcademicContext?.init?.(); } catch (e) { console.warn('Academic context unavailable; loading unfiltered oversight data.', e); } await this.load(); }
};
window.academicPlanningOversight = academicPlanningOversight;
document.addEventListener('DOMContentLoaded', () => academicPlanningOversight.init().catch(e=>window.showNotification?.(e.message||'Unable to load academic planning oversight','danger')));

window.academicPlanningOversight = academicPlanningOversight;
window.APIRealtime?.register?.(academicPlanningOversight, academicPlanningOversight);
