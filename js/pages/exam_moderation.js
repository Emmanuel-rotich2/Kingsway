const ModerationController = {
    state: {
        pending: [],
        currentAssessment: null,
        classes: [],
        subjects: [],
        terms: [],
    },
    esc: (s) => {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    },
    async init() {
        await window.AuthContext?.ready?.();
        if (!AuthContext.isAuthenticated()) return;
        await window.GradingScale?.preload?.();
        await this.loadReferencesComposed();
        this.setupFilters();
        window.APIRealtime?.register?.("exam_moderation", { refresh: async () => { await this.loadReferencesComposed(); await this.loadPending(); } }, ["academic"]);
    },
    // Composed startup read: one authorized Node view supplies moderation
    // filter references; pending rows stay a filtered direct read.
    async loadReferencesComposed() {
        try {
            const response = await window.API.nodeViews.resolve("academic", "moderation-references");
            const sources = response?.data?.sources || response?.sources || {};
            const pick = (value) => (Array.isArray(value) ? value : (value?.data || []));
            this.state.classes = pick(sources.classes);
            this.state.subjects = pick(sources.subjects);
            this.populateSelects();
        } catch (e) {
            console.error('Moderation reference Node view failed; falling back to direct reads:', e);
            await this.loadReferences();
        }
    },
    async callAPI(endpoint, method, data) {
        const r = await window.API.apiCall(endpoint, method, data || undefined);
        return r?.data ?? r ?? [];
    },
    async loadReferences() {
        const [classes, subs] = await Promise.all([
            this.callAPI('/academic/classes-list', 'GET'),
            this.callAPI('/academic/subjects', 'GET'),
        ]);
        this.state.classes = Array.isArray(classes) ? classes : [];
        this.state.subjects = Array.isArray(subs) ? subs : [];
        this.populateSelects();
    },
    populateSelects() {
        const classEl = document.getElementById('modClassFilter');
        classEl.innerHTML = '<option value="">All Classes</option>';
        this.state.classes.forEach(c => {
            classEl.innerHTML += `<option value="${c.id}">${this.esc(c.name)}</option>`;
        });
        const subjEl = document.getElementById('modSubjectFilter');
        subjEl.innerHTML = '<option value="">All Subjects</option>';
        this.state.subjects.forEach(s => {
            subjEl.innerHTML += `<option value="${s.id}">${this.esc(s.name)}</option>`;
        });
    },
    setupFilters() {
        document.getElementById('modClassFilter')?.addEventListener('change', () => this.loadPending());
        document.getElementById('modSubjectFilter')?.addEventListener('change', () => this.loadPending());
        document.getElementById('modTermFilter')?.addEventListener('change', () => this.loadPending());
    },
    async loadPending() {
        const classId = document.getElementById('modClassFilter')?.value;
        const subjectId = document.getElementById('modSubjectFilter')?.value;
        const termId = document.getElementById('modTermFilter')?.value;
        const params = new URLSearchParams();
        if (classId) params.append('class_id', classId);
        if (subjectId) params.append('subject_id', subjectId);
        if (termId) params.append('term_id', termId);
        const qs = params.toString();
        try {
            let data = await this.callAPI('/academic/pending-moderation' + (qs ? '?' + qs : ''), 'GET');
            if (!Array.isArray(data)) data = [];
            this.state.pending = data;
            this.render();
        } catch (e) {
            console.error('loadPending:', e);
            document.getElementById('moderationContainer').innerHTML = '<p class="text-danger text-center py-3">Failed to load moderation data.</p>';
        }
    },
    render() {
        const container = document.getElementById('moderationContainer');
        if (!container) return;
        if (!this.state.pending.length) {
            container.innerHTML = '<div class="alert alert-success text-center">No pending moderation items. All results have been approved.</div>';
            return;
        }
        let html = '';
        this.state.pending.forEach(a => {
            const pending = parseInt(a.pending_count || 0);
            const total = parseInt(a.total_students || 0);
            const progress = total > 0 ? Math.round((total - pending) / total * 100) : 0;
            const barClass = progress === 100 ? 'bg-success' : (progress > 50 ? 'bg-warning' : 'bg-danger');
            html += `<div class="card mb-2">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${this.esc(a.title)}</strong>
                        <span class="badge bg-secondary ms-2">${this.esc(a.class_name)}</span>
                        <span class="badge bg-info ms-1">${this.esc(a.subject_name)}</span>
                        <span class="badge bg-light text-dark ms-1">${this.esc(a.term_name)}</span>
                    </div>
                    <div>
                        <small class="text-muted me-2">${a.assessment_date || ''}</small>
                        <button class="btn btn-sm btn-outline-primary" onclick="ModerationController.openResults(${a.assessment_id})" title="Review">
                            <i class="bi bi-eye"></i> Review (${pending} pending)
                        </button>
                        <button class="btn btn-sm btn-success" onclick="ModerationController.approveAssessment(${a.assessment_id})" title="Approve All">
                            <i class="bi bi-check-all"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body py-2">
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar ${barClass}" style="width: ${progress}%"></div>
                    </div>
                    <small class="text-muted">${total - pending}/${total} approved | Avg: ${a.avg_mark ? parseFloat(a.avg_mark).toFixed(1) : '-'}</small>
                </div>
            </div>`;
        });
        container.innerHTML = html;
    },
    async openResults(assessmentId) {
        const a = this.state.pending.find(x => parseInt(x.assessment_id) === assessmentId);
        if (!a) return;
        this.state.currentAssessment = a;
        document.getElementById('modalAssessmentTitle').textContent = this.esc(a.title);
        const tbody = document.getElementById('studentResultsBody');
        if (!a.results || !a.results.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No results found.</td></tr>';
        } else {
            tbody.innerHTML = a.results.map((r, i) => {
                const approved = r.is_approved == 1;
                return `<tr class="${approved ? 'table-success' : ''}">
                    <td>${i + 1}</td>
                    <td>${this.esc(r.student_name)}</td>
                    <td>${this.esc(r.admission_no || '')}</td>
                    <td><strong>${r.entry_status === 'present' ? r.marks_obtained : this.esc(r.entry_status || '-')}</strong></td>
                    <td><span class="badge ${GradingScale.band(r.grade) === 'EE' ? 'bg-primary' : GradingScale.band(r.grade) === 'ME' ? 'bg-success' : GradingScale.band(r.grade) === 'AE' ? 'bg-warning text-dark' : 'bg-danger'}">${r.grade || '-'}</span></td>
                    <td>${r.points || '-'}</td>
                    <td>${approved ? '<span class="badge bg-success">Approved</span>' : '<span class="badge bg-warning text-dark">Pending</span>'}</td>
                    <td>
                        ${!approved ? `<button class="btn btn-sm btn-success me-1" onclick="ModerationController.approveResult(${a.assessment_id}, ${r.student_id})" title="Approve"><i class="bi bi-check"></i></button>` : ''}
                        <button class="btn btn-sm btn-danger" onclick="ModerationController.rejectResult(${a.assessment_id}, ${r.student_id})" title="Reject"><i class="bi bi-x"></i></button>
                    </td>
                </tr>`;
            }).join('');
        }
        new bootstrap.Modal(document.getElementById('studentResultsModal')).show();
    },
    async approveResult(assessmentId, studentId) {
        try {
            await this.callAPI('/academic/approve-assessment', 'POST', { assessment_id: assessmentId, student_id: studentId });
            window.showNotification?.('Result approved', 'success');
            await this.loadPending();
            this.openResults(assessmentId);
        } catch (e) { window.showNotification?.(e.message || 'Failed to approve', 'error'); }
    },
    async rejectResult(assessmentId, studentId) {
        const reason = await window.promptAction('Input', 'Reason for returning this assessment to the teacher:');
        if (!String(reason || '').trim()) {
            window.showNotification?.('A moderation reason is required', 'warning');
            return;
        }
        try {
            await this.callAPI('/academic/reject-assessment', 'POST', { assessment_id: assessmentId, student_id: studentId, reason: reason || '' });
            window.showNotification?.('Result rejected', 'warning');
            await this.loadPending();
            this.openResults(assessmentId);
        } catch (e) { window.showNotification?.(e.message || 'Failed to reject', 'error'); }
    },
    async approveAssessment(assessmentId) {
        if (!(await window.confirmAction('Confirm', 'Approve all pending results in this assessment?'))) return;
        try {
            const r = await window.API.apiCall('/academic/approve-assessment', 'POST', { assessment_id: assessmentId });
            window.showNotification?.(r?.message || 'Assessment approved', 'success');
            await this.loadPending();
        } catch (e) { window.showNotification?.(e.message || 'Failed to approve', 'error'); }
    },
    async approveAll() {
        if (!this.state.pending.length || !(await window.confirmAction('Confirm', 'Approve ALL pending results?'))) return;
        let count = 0;
        for (const a of this.state.pending) {
            try {
                await this.callAPI('/academic/approve-assessment', 'POST', { assessment_id: a.assessment_id });
                count++;
            } catch (e) { console.error('Failed to approve', a.assessment_id, e); }
        }
        window.showNotification?.(`${count} assessments approved`, 'success');
        await this.loadPending();
    },
    async approveAllInModal() {
        const a = this.state.currentAssessment;
        if (!a) return;
        await this.approveAssessment(a.assessment_id);
        bootstrap.Modal.getInstance(document.getElementById('studentResultsModal'))?.hide();
    },
};

document.addEventListener('DOMContentLoaded', async () => await ModerationController.init());

window.ModerationController = ModerationController;
