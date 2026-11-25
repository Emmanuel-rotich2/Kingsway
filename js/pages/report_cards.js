/** Report card release list and generation actions. */
const reportCardsController = {
  state: { years: [], terms: [], classes: [], streams: [], releases: [], visible: [], page: 1, pageSize: 20, releaseId: null, parents: [], canManage: false },
  API: (method, endpoint, data = null, params = null) => window.callAPI(endpoint, method, data, params),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]); },
  rows(response) { const data = response?.data?.data ?? response?.data ?? response ?? []; return Array.isArray(data) ? data : Array.isArray(data?.rows) ? data.rows : []; },
  payload(response) { return response?.data?.data ?? response?.data ?? response ?? {}; },
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const reportUser = window.AuthContext?.getUser?.() || {};
    const reportRoles = [reportUser.role_id, reportUser.roleId, reportUser.primary_role_id, ...(reportUser.role_ids || [])].map(Number);
    const canManage = reportRoles.some((id) => [1, 3, 4, 5, 6].includes(id)) ||
      (window.AuthContext?.hasPermission && (AuthContext.hasPermission('academic_manage') || AuthContext.hasPermission('academics_manage') || AuthContext.hasPermission('academic_approve')));
    this.state.canManage = canManage;
    const generateButton = document.getElementById('generateAllBtn'); if (generateButton) generateButton.hidden = !canManage;
    this.state.classes = this.rows(await this.API('GET', '/api/academic/classes-list'));
    this.fill('classFilter', this.state.classes, 'All Classes', (row) => row.name || row.class_name);
    await this.loadStreams();
    document.getElementById('classFilter')?.addEventListener('change', async () => { await this.loadStreams(); await this.load(); });
    document.getElementById('streamFilter')?.addEventListener('change', () => this.load());
    document.getElementById('reportResultMode')?.addEventListener('change', () => this.load());
    document.getElementById('searchBox')?.addEventListener('input', () => { this.state.page = 1; this.render(); });
    document.getElementById('clearBtn')?.addEventListener('click', () => this.clearFilters());
    document.getElementById('generateAllBtn')?.addEventListener('click', () => this.generateClass());
    document.getElementById('downloadAllBtn')?.addEventListener('click', () => this.downloadAll());
    document.getElementById('printCardsBtn')?.addEventListener('click', () => this.printSelected());
    document.getElementById('printAllBtn')?.addEventListener('click', () => this.print());
    document.getElementById('selectAll')?.addEventListener('change', (event) => {
      document.querySelectorAll('#reportCardsTbody [data-release-select]').forEach((box) => { box.checked = event.target.checked; });
      this.updateSelectedButton();
    });
    document.getElementById('reportCardsTbody')?.addEventListener('change', (event) => { if (event.target.matches('[data-release-select]')) this.updateSelectedButton(); });
    document.getElementById('reportCardsTbody')?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-release-action]'); if (!button) return;
      const release = this.state.releases.find((row) => Number(row.release_id) === Number(button.dataset.releaseId));
      if (button.dataset.releaseAction === 'download' && release?.download_url) window.open(release.download_url, '_blank', 'noopener');
      if (button.dataset.releaseAction === 'regenerate' && release) this.generateStudents([Number(release.student_id)]);
      if (button.dataset.releaseAction === 'approve' && release) this.approve(release);
      if (button.dataset.releaseAction === 'release' && release) this.openRelease(release);
    });
    document.getElementById('confirmReleaseBtn')?.addEventListener('click', () => this.release());
    document.getElementById('toggleReleaseParents')?.addEventListener('click', (event) => {
      const boxes = Array.from(document.querySelectorAll('#releaseParentsList [data-parent-id]'));
      const select = boxes.some((box) => !box.checked); boxes.forEach((box) => { box.checked = select; });
      event.currentTarget.textContent = select ? 'Select none' : 'Select all';
    });
    document.getElementById('pagination')?.addEventListener('click', (event) => {
      const link = event.target.closest('[data-page]'); if (!link) return;
      event.preventDefault(); this.state.page = Number(link.dataset.page); this.render();
    });
    // Year and term: the shared filter (current year and running term by
    // default, kept in the URL). It loads the list now and on every change.
    const yearSelect = document.getElementById('yearFilter');
    const termSelect = document.getElementById('termFilter');
    const mounted = window.AcademicYearFilter && yearSelect
      ? await window.AcademicYearFilter.mount(yearSelect, { termSelect, onChange: () => this.load() })
      : null;
    if (!mounted) await this.load();
    window.APIRealtime?.register?.('report_cards', { refresh: () => this.load() }, ['academic','reports']);
  },
  fill(id, items, first, label) {
    const select = document.getElementById(id); if (!select) return;
    select.innerHTML = `<option value="">${this.esc(first)}</option>` + items.map((item) => `<option value="${Number(item.id)}">${this.esc(label(item))}</option>`).join('');
  },
  async loadStreams() {
    const classId = document.getElementById('classFilter')?.value || '';
    const response = await this.API('GET', '/api/academic/streams-list', null, classId ? { class_id: classId } : {});
    this.state.streams = this.rows(response); this.fill('streamFilter', this.state.streams, 'All Streams', (row) => row.name || row.stream_name);
  },
  async load() {
    const body = document.getElementById('reportCardsTbody'); if (body) body.innerHTML = '<tr><td colspan="11" class="rc-loading"><div class="spinner-border"></div><div class="mt-2">Loading report card releases…</div></td></tr>';
    const params = {
      year_id: document.getElementById('yearFilter')?.value || '',
      term_id: document.getElementById('termFilter')?.value || '',
      class_id: document.getElementById('classFilter')?.value || '',
      stream_id: document.getElementById('streamFilter')?.value || '',
    };
    Object.keys(params).forEach((key) => { if (!params[key]) delete params[key]; });
    try {
      this.state.releases = this.rows(await this.API('GET', '/api/academic/report-card-releases', null, params));
      this.state.page = 1; this.render();
    } catch (error) {
      if (body) body.innerHTML = `<tr><td colspan="11" class="text-center text-danger py-4">${this.esc(error.message || 'Report card releases could not be loaded.')}</td></tr>`;
      this.setKPIs([]);
    }
  },
  render() {
    const search = (document.getElementById('searchBox')?.value || '').trim().toLowerCase();
    this.state.visible = this.state.releases.filter((row) => !search || `${row.student_name || ''} ${row.admission_no || ''}`.toLowerCase().includes(search));
    this.setKPIs(this.state.visible);
    const start = (this.state.page - 1) * this.state.pageSize;
    const pageRows = this.state.visible.slice(start, start + this.state.pageSize);
    const body = document.getElementById('reportCardsTbody'); if (!body) return;
    body.innerHTML = pageRows.length ? pageRows.map((row) => {
      const status = String(row.status || 'pending_approval').toLowerCase();
      const statusClass = status === 'released' ? 'rc-distributed' : status === 'approved' ? 'rc-approved' : status === 'pending_approval' ? 'rc-pending' : 'rc-generated';
      const download = row.download_url ? `<button type="button" class="btn btn-sm btn-outline-success" data-release-action="download" data-release-id="${Number(row.release_id)}" title="Download PDF"><i class="bi bi-download"></i></button>` : '';
      const workflow = this.state.canManage && status === 'pending_approval' ? `<button type="button" class="btn btn-sm btn-outline-primary" data-release-action="approve" data-release-id="${Number(row.release_id)}">Approve</button>` : this.state.canManage && status === 'approved' ? `<button type="button" class="btn btn-sm btn-success" data-release-action="release" data-release-id="${Number(row.release_id)}">Release</button>` : '';
      const regenerate = this.state.canManage ? `<button type="button" class="btn btn-sm btn-outline-primary" data-release-action="regenerate" data-release-id="${Number(row.release_id)}" title="Generate a new version"><i class="bi bi-arrow-repeat"></i></button>` : '';
      return `<tr data-release-row="${Number(row.release_id)}"><td><input type="checkbox" data-release-select value="${Number(row.release_id)}" aria-label="Select ${this.esc(row.student_name)}"></td>
        <td>${this.esc(row.student_name || '—')}</td><td>${this.esc(row.admission_no || '—')}</td><td>${this.esc(row.class_name || '—')}</td><td>${this.esc(row.stream_name || '—')}</td><td>—</td>
        <td>${row.overall_percentage == null ? '—' : `${Number(row.overall_percentage).toFixed(1)}%`}</td><td>${row.class_position == null ? '—' : `${Number(row.class_position)} / ${Number(row.class_population || 0)}`}</td>
        <td><span class="rc-pill ${statusClass}">${this.esc(status.replaceAll('_',' '))}</span></td><td>${this.esc(row.term_name || '—')}</td><td class="d-flex gap-1">${download}${workflow}${regenerate}</td></tr>`;
    }).join('') : '<tr><td colspan="11" class="rc-empty"><i class="bi bi-file-earmark-text"></i>No report card releases match these filters.</td></tr>';
    const totalPages = Math.max(1, Math.ceil(this.state.visible.length / this.state.pageSize));
    const meta = `${this.state.visible.length ? start + 1 : 0}–${Math.min(start + this.state.pageSize, this.state.visible.length)} of ${this.state.visible.length}`;
    ['tableMeta','paginationMeta'].forEach((id) => { const element = document.getElementById(id); if (element) element.textContent = meta; });
    const pagination = document.getElementById('pagination'); if (pagination) pagination.innerHTML = totalPages > 1 ? Array.from({length:totalPages},(_,index)=>`<li class="page-item ${this.state.page===index+1?'active':''}"><a class="page-link" href="#" data-page="${index+1}">${index+1}</a></li>`).join('') : '';
    this.updateSelectedButton();
  },
  setKPIs(rows) {
    const set = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = String(value); };
    set('totalStudents', rows.length);
    set('cardsGenerated', rows.length);
    set('cardsPending', rows.filter((row) => row.status === 'pending_approval').length);
    set('cardsDownloaded', rows.filter((row) => Boolean(row.download_url)).length);
  },
  async generateClass() {
    const termId = document.getElementById('termFilter')?.value;
    const classId = document.getElementById('classFilter')?.value;
    if (!termId || !classId) return this.notify('Select an academic year, term, and class before generating class report cards.', 'warning');
    if (document.getElementById('streamFilter')?.value) return this.notify('Clear the stream filter; class generation covers the full class.', 'warning');
    await this.generateStudents([], { term_id: Number(termId), class_id: Number(classId) });
  },
  async generateStudents(studentIds, extra = {}) {
    const payload = { term_id: Number(document.getElementById('termFilter')?.value || extra.term_id || 0), result_mode: document.getElementById('reportResultMode')?.value || 'both', ...extra };
    if (studentIds.length) payload.student_ids = studentIds;
    if (!payload.term_id || (!studentIds.length && !payload.class_id)) return this.notify('Choose a term and class or student first.', 'warning');
    const button = document.getElementById('generateAllBtn'); if (button) button.disabled = true;
    try {
      const result = this.payload(await this.API('POST', '/api/academic/reports/generate-student-reports', payload));
      this.notify(`${Number(result.generated_count || 0)} report card(s) generated; ${Number(result.failed_count || 0)} need review.`, result.failed_count ? 'warning' : 'success');
      await this.load();
    } catch (error) { this.notify(error.message || 'Report cards could not be generated.', 'danger'); }
    finally { if (button) button.disabled = false; }
  },
  async approve(release) {
    if (!(await window.confirmAction('Approve report card', `Approve the report card for ${release.student_name}?`, { confirmText: 'Approve' }))) return;
    try { await this.API('POST', '/api/academic/reports-review-and-approve', { release_id: Number(release.release_id) }); this.notify('Report card approved. It is not visible to parents until released.', 'success'); await this.load(); }
    catch (error) { this.notify(error.message || 'Approval failed.', 'danger'); }
  },
  async openRelease(release) {
    this.state.releaseId = Number(release.release_id);
    const list = document.getElementById('releaseParentsList'); list.innerHTML = '<div class="text-muted small">Loading linked guardians…</div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('releaseParentsModal')).show();
    try {
      this.state.parents = this.rows(await this.API('GET', `/api/academic/report-card-releases/${this.state.releaseId}/parents`));
      list.innerHTML = this.state.parents.length ? this.state.parents.map((parent) => {
        const name = `${parent.first_name || ''} ${parent.last_name || ''}`.trim() || `Guardian ${parent.parent_id}`;
        return `<label class="list-group-item d-flex gap-2 align-items-start"><input class="form-check-input mt-1" type="checkbox" data-parent-id="${Number(parent.parent_id)}" checked><span>${this.esc(name)}<small class="d-block text-muted">${this.esc(parent.phone || parent.email || 'No phone/email recorded')}</small></span></label>`;
      }).join('') : '<div class="alert alert-warning">No active linked guardians are available for notification.</div>';
    } catch (error) { list.innerHTML = `<div class="alert alert-danger">${this.esc(error.message || 'Could not load guardians.')}</div>`; }
  },
  async release() {
    const parentIds = Array.from(document.querySelectorAll('#releaseParentsList [data-parent-id]:checked')).map((box) => Number(box.dataset.parentId));
    const channels = Array.from(document.querySelectorAll('#releaseChannels input:checked')).map((box) => box.value);
    if (!parentIds.length) return this.notify('Select at least one linked parent or guardian.', 'warning');
    if (!channels.length) return this.notify('Select at least one notification channel.', 'warning');
    const button = document.getElementById('confirmReleaseBtn'); button.disabled = true;
    try {
      const result = this.payload(await this.API('POST', '/api/academic/reports-distribute', { release_id: this.state.releaseId, parent_ids: parentIds, channels }));
      bootstrap.Modal.getInstance(document.getElementById('releaseParentsModal'))?.hide();
      const failed = Object.values(result.channels || {}).some((item) => item?.status === 'failed');
      this.notify(failed ? 'Report card released; review communication delivery for failed channels.' : 'Report card released. The official report is now available in the parent portal.', failed ? 'warning' : 'success');
      await this.load();
    } catch (error) { this.notify(error.message || 'Report card release failed.', 'danger'); }
    finally { button.disabled = false; }
  },
  selectedIds() { return Array.from(document.querySelectorAll('#reportCardsTbody [data-release-select]:checked')).map((box) => Number(box.value)); },
  updateSelectedButton() { const button = document.getElementById('printSelectedBtn'); if (button) button.style.display = this.selectedIds().length ? '' : 'none'; },
  printSelected() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('reports')) return this.notify('You do not have permission to print reports.', 'error');
    const ids = new Set(this.selectedIds().map(String));
    if (!ids.size) return this.print();
    const selected = this.state.visible.filter((row) => ids.has(String(row.release_id)) && row.download_url);
    if (!selected.length) return this.notify('Selected rows do not have report-card PDFs available.', 'warning');
    selected.forEach((row) => window.open(row.download_url, '_blank', 'noopener'));
    this.notify('Opened the selected report-card PDFs. Print from each PDF viewer.', 'info');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('reports')) return this.notify('You do not have permission to print reports.', 'error'); window.print(); },
  downloadAll() {
    const links = this.state.visible.filter((row) => row.download_url).map((row) => row.download_url);
    if (!links.length) return this.notify('No report card PDFs are available for the current filters.', 'info');
    links.forEach((url) => window.open(url, '_blank', 'noopener'));
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('reports')) return this.notify('You do not have permission to export reports.', 'error');
    const cols = ['student_name','admission_no','class_name','stream_name','overall_percentage','class_position','status','term_name','generated_at'];
    const csv = [cols, ...this.state.visible.map((row) => cols.map((key) => row[key] ?? ''))].map((row) => row.map((value) => `"${String(value).replaceAll('"','""')}"`).join(',')).join('\r\n');
    const yearLabel = (document.getElementById('yearFilter')?.selectedOptions?.[0]?.textContent || '').replace(/\s*\(.*\)$/, '').replace(/[^0-9A-Za-z-]+/g, '-');
    window.KingswayFileLifecycle?.exportText?.(csv, `report-card-releases${yearLabel ? '_' + yearLabel : ''}.csv`, 'text/csv;charset=utf-8;');
  },
  clearFilters() {
    // Year and term stay: they set the context, they are not narrowing filters.
    ['classFilter','streamFilter','searchBox'].forEach((id) => { const element = document.getElementById(id); if (element) element.value = ''; });
    this.loadStreams().then(() => this.load());
  },
  notify(message, type = 'info') { window.showNotification?.(message, type); },
};
window.reportCardsController = reportCardsController;
window.reportCardsCtrl = reportCardsController;
document.addEventListener('DOMContentLoaded', () => reportCardsController.init().catch((error) => reportCardsController.notify(error.message || 'Report cards page setup failed.', 'danger')));
