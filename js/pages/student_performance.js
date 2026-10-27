/**
 * Student Performance Controller
 * Overview of student academic performance.
 */
const studentPerformanceController = {
  state: { referenceCache: null, performance: [], student: null },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("students", "student-performance-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Student Performance Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load(studentId) {
    try {
      const cache = this.state.referenceCache;
      const [performance, student] = await Promise.all([
        cache?.performance !== undefined ? cache.performance : this.API('GET', `/api/students/${studentId}/performance`),
        cache?.student !== undefined ? cache.student : this.API('GET', `/api/students/${studentId}`),
      ]);
      this.state.performance = Array.isArray(performance?.data) ? performance.data : (Array.isArray(performance) ? performance : []);
      this.state.student = student?.data || student || {};
      this.render();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  render() {
    const performance = this.state.performance;
    const student = this.state.student;
    { const el = document.getElementById('spStudentName'); if (el) el.textContent = student?.name || '—'; }
    { const el = document.getElementById('spAdmissionNo'); if (el) el.textContent = student?.admission_number || '—'; }
    const body = document.getElementById('spPerformanceBody');
    if (!performance.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No performance data.</td></tr>'; return; }
    body.innerHTML = performance.map(p => `<tr>
      <td class="small">${this.esc(p.term || '—')}</td>
      <td class="small">${this.esc(p.subject || '—')}</td>
      <td class="small text-center">${this.esc(p.score || '—')}</td>
      <td class="small text-center">${this.esc(p.grade || '—')}</td>
      <td class="small">${this.esc(p.teacher || '—')}</td>
    </tr>`).join('');
    this.renderTermFilter();
  },

  renderTermFilter() {
    const terms = this.getCache('terms') || [];
    const select = document.getElementById('spTermFilter');
    if (select) select.innerHTML = '<option value="">All Terms</option>' + terms.map(t => `<option value="${t.id}">${this.esc(t.name)}</option>`).join('');
  },

  async loadAiSupportReviews(scope = 'own') {
    const host = document.getElementById('aiSupportPlanningReviews');
    const list = document.getElementById('aiSupportPlanningReviewsList');
    const status = document.getElementById('aiSupportPlanningReviewsStatus');
    if (!host || !list) return;
    host.hidden = false;
    if (status) status.textContent = 'Loading support review drafts…';
    try {
      const response = await window.API.students.getAiSupportPlanningReviews(scope);
      const drafts = response?.data?.drafts || response?.drafts || [];
      list.innerHTML = drafts.length ? drafts.map((draft) => {
        const content = draft.draft || {};
        const canReview = scope === 'review' && draft.status === 'pending_approval';
        return `<div class="col-12"><article class="border rounded p-3 bg-white">
          <div class="d-flex justify-content-between gap-2"><strong>${this.esc(content.title || 'Learner support review')}</strong><span class="badge text-bg-secondary">${this.esc(draft.status || 'queued')}</span></div>
          <p class="small mt-2 mb-2">${this.esc(content.body || 'Review is being prepared.')}</p>
          ${Array.isArray(content.next_steps) && content.next_steps.length ? `<ul class="small">${content.next_steps.map((item) => `<li>${this.esc(item)}</li>`).join('')}</ul>` : ''}
          ${canReview ? `<button type="button" class="btn btn-sm btn-outline-success" data-ai-support-approve="${Number(draft.id)}">Approve for staff guidance</button>` : ''}
          <div class="small text-muted mt-2">Advisory only. Verify through the responsible staff workflow; this does not update learner records.</div>
        </article></div>`;
      }).join('') : '<div class="col-12 small text-muted">No support review drafts in this view.</div>';
      if (status) status.textContent = `${drafts.length} draft${drafts.length === 1 ? '' : 's'} loaded.`;
      list.querySelectorAll('[data-ai-support-approve]').forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        try {
          await window.API.students.approveAiSupportPlanning(button.dataset.aiSupportApprove);
          if (status) status.textContent = 'Review approved for staff guidance.';
          await this.loadAiSupportReviews('review');
        } catch (error) {
          if (status) status.textContent = error.message || 'Unable to approve this review.';
          button.disabled = false;
        }
      }));
    } catch (error) {
      list.replaceChildren();
      if (status) status.textContent = error.message || 'Unable to load support reviews.';
    }
  },

  bindAiSupportPlanning() {
    const button = document.getElementById('queueAiSupportPlanningBtn');
    const status = document.getElementById('aiSupportPlanningStatus');
    button?.addEventListener('click', async () => {
      button.disabled = true;
      if (status) status.textContent = 'Preparing an authorized aggregate support review…';
      try {
        const queued = await this.API('POST', '/students/ai-support-planning-queue', { context: "oversight" });
        const jobId = queued?.data?.job_id || queued?.job_id;
        if (status) status.textContent = jobId ? `Support review queued (job ${jobId}).` : 'Support review queued.';
        await this.loadAiSupportReviews('own');
      } catch (error) {
        if (status) status.textContent = error.message || 'Unable to queue a support review.';
      } finally {
        button.disabled = false;
      }
    }, { once: true });
    document.getElementById('loadOwnAiSupportReviewsBtn')?.addEventListener('click', () => this.loadAiSupportReviews('own'));
    document.getElementById('loadReviewAiSupportReviewsBtn')?.addEventListener('click', () => this.loadAiSupportReviews('review'));
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.bindAiSupportPlanning();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    if (studentId) {
      await this.loadReferencesComposed();
      await this.load(studentId);
      window.APIRealtime?.register?.("student_performance", {
        refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(studentId); }
      }, ["students", "academic"]);
    }
  },
};

window.studentPerformanceController = studentPerformanceController;
document.addEventListener('DOMContentLoaded', () => studentPerformanceController.init().catch(() => {}));
