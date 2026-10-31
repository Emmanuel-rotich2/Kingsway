/**
 * Student Timeline Controller
 * Displays chronological student events and milestones.
 */
const studentTimelineController = {
  state: { referenceCache: null, timeline: [], student: null, summary: {}, record: {} },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); },

  async load(studentId) {
    try {
      const cache = this.state.referenceCache;
      const response = cache?.timeline !== undefined ? cache.timeline : await this.API('GET', `/api/academic/student-timeline/${studentId}`);
      const payload = response?.data || response || {};
      this.state.timeline = Array.isArray(payload.academics) ? payload.academics : [];
      this.state.student = payload.student || {};
      this.state.summary = payload.summary || {};
      this.state.record = payload;
      this.render();
      const select = document.getElementById('tlStudentSelect');
      if (select && this.state.student?.id) {
        const id = String(this.state.student.id);
        if (!Array.from(select.options).some((option) => option.value === id)) {
          const name = [this.state.student.first_name, this.state.student.middle_name, this.state.student.last_name].filter(Boolean).join(' ');
          select.add(new Option(`${this.state.student.admission_no || ''} ${name}`.trim(), id), 1);
        }
        select.value = id;
      }
    } catch (e) { console.error(e); }
  },

  render() {
    const timeline = this.state.timeline;
    const student = this.state.student;
    const setText = (id, value) => {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    };
    setText('tlStudentName', [student?.first_name, student?.middle_name, student?.last_name].filter(Boolean).join(' ') || student?.name || '—');
    setText('tlAdmNo', student?.admission_no || '—');
    setText('tlDob', student?.date_of_birth || '—');
    setText('tlGender', student?.gender || '—');
    setText('tlClass', student?.current_class || '—');
    setText('tlAdmDate', student?.admission_date || '—');
    setText('tlStudentType', student?.student_type || '—');
    setText('tlSponsor', student?.sponsor_name || '');
    setText('tlYearsEnrolled', this.state.summary.years_enrolled ?? '—');
    setText('tlTotalPaid', `KES ${Number(this.state.summary.total_fees_paid || 0).toLocaleString()}`);
    setText('tlBalance', `KES ${Number(this.state.summary.current_balance || 0).toLocaleString()}`);
    setText('tlDisciplineCount', this.state.summary.discipline_cases ?? '—');
    const content = document.getElementById('tlContent');
    const empty = document.getElementById('tlEmpty');
    if (content) content.style.display = 'block';
    if (empty) empty.hidden = true;
    const body = document.getElementById('tlAcademicContent');
    if (!body) return;
    if (!timeline.length) { body.innerHTML = '<div class="alert alert-light text-muted">No academic timeline records.</div>'; return; }
    body.innerHTML = timeline.map(e => `<div class="card mb-2"><div class="card-body py-2 d-flex justify-content-between gap-3"><div><strong>${this.esc(e.year_name || e.type || 'Academic record')}</strong><div class="small text-muted">${this.esc(e.class_name || e.description || '—')}</div></div><div class="text-end small"><div>${this.esc(e.year_average ?? e.overall_percentage ?? '—')}${e.year_average != null || e.overall_percentage != null ? '%' : ''}</div><span class="text-muted">${this.fmtDate(e.enrolled_on || e.date)}</span></div></div></div>`).join('');
    const rows = (id, values, colspan, renderRow, message) => {
      const target = document.getElementById(id);
      if (target) target.innerHTML = values.length ? values.map(renderRow).join('') : `<tr><td colspan="${colspan}" class="text-center text-muted py-3">${message}</td></tr>`;
    };
    rows('tlPaymentsBody', this.state.record.payments || [], 7, (p) => `<tr><td>${this.fmtDate(p.payment_date)}</td><td>${this.esc(p.academic_year || '—')}</td><td>${this.esc(p.term_name || '—')}</td><td class="text-end">KES ${Number(p.amount_paid || 0).toLocaleString()}</td><td>${this.esc(p.payment_method || '—')}</td><td>${this.esc(p.receipt_no || p.reference_no || '—')}</td><td>${this.esc(p.status || '—')}</td></tr>`, 'No payments found.');
    rows('tlCreditsBody', this.state.record.credit_notes || [], 4, (c) => `<tr><td>${this.esc(c.credit_number || '—')}</td><td>KES ${Number(c.credit_amount || 0).toLocaleString()}</td><td>KES ${Number(c.remaining_amount || 0).toLocaleString()}</td><td>${this.esc(c.status || '—')}</td></tr>`, 'No fee credits found.');
    rows('tlObligationsBody', this.state.record.fee_obligations || [], 4, (o) => `<tr><td>${this.esc(o.academic_year || '—')}</td><td>${this.esc(o.term_name || o.term_number || '—')}</td><td>${this.esc(o.fee_name || 'School Fees')}</td><td class="text-end">KES ${Number(o.amount_due || 0).toLocaleString()}</td></tr>`, 'No fee obligations found.');
    rows('tlAttendanceBody', this.state.record.attendance || [], 7, (a) => { const total = Number(a.total_recorded || 0); const present = Number(a.days_present || 0); return `<tr><td>${this.esc(a.academic_year || '—')}</td><td>All terms</td><td class="text-center">${present}</td><td class="text-center">${Number(a.days_absent || 0)}</td><td class="text-center">${Number(a.days_late || 0)}</td><td class="text-center">${total}</td><td class="text-end">${total ? Math.round(present / total * 100) : 0}%</td></tr>`; }, 'No attendance records found.');
    const discipline = document.getElementById('tlDisciplineContent');
    if (discipline) discipline.innerHTML = (this.state.record.discipline || []).length ? `<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Date</th><th>Type</th><th>Severity</th><th>Description</th><th>Action</th><th>Status</th></tr></thead><tbody>${this.state.record.discipline.map((d) => `<tr><td>${this.fmtDate(d.incident_date)}</td><td>${this.esc(d.incident_type || '—')}</td><td>${this.esc(d.severity || '—')}</td><td>${this.esc(d.description || '—')}</td><td>${this.esc(d.action_taken || '—')}</td><td>${this.esc(d.status || '—')}</td></tr>`).join('')}</tbody></table></div>` : '<div class="alert alert-light text-muted">No conduct incidents found.</div>';
    const transfers = document.getElementById('tlTransfersContent');
    if (transfers) transfers.innerHTML = (this.state.record.transfers || []).length ? `<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Request</th><th>Date</th><th>Type</th><th>Reason</th><th>Finance clearance</th><th>Completed</th></tr></thead><tbody>${this.state.record.transfers.map((t) => `<tr><td>#${this.esc(t.request_number)}</td><td>${this.fmtDate(t.request_date)}</td><td>${this.esc(t.transfer_type || '—')}</td><td>${this.esc(t.reason || '—')}</td><td>${this.esc(t.status || '—')}</td><td>${this.fmtDate(t.completed_at)}</td></tr>`).join('')}</tbody></table></div>` : '<div class="alert alert-light text-muted">No transfer records found.</div>';
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },

  async loadSelected() {
    const id = document.getElementById('tlStudentSelect')?.value;
    if (!id) return;
    const url = new URL(window.location.href);
    url.searchParams.set('student_id', id);
    history.replaceState({}, '', url);
    await this.load(id);
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const studentId = new URLSearchParams(window.location.search).get('student_id');
    const select = document.getElementById('tlStudentSelect');
    const search = document.getElementById('tlStudentSearch');
    let searchTimer = null;
    let searchSequence = 0;
    if (search) search.addEventListener('input', () => {
      clearTimeout(searchTimer);
      const query = search.value.trim();
      if (!select || query.length < 2) {
        if (select) select.innerHTML = '<option value="">Type at least 2 characters to search</option>';
        return;
      }
      searchTimer = setTimeout(async () => {
        const sequence = ++searchSequence;
        select.innerHTML = '<option value="">Searching learners…</option>';
        try {
          const response = await this.API('GET', '/api/students/get-all', null, { limit: '50', search: query, status: 'active' });
          if (sequence !== searchSequence) return;
          const results = Array.isArray(response?.data) ? response.data : (Array.isArray(response) ? response : []);
          select.innerHTML = '<option value="">Select a learner</option>' + results.map((item) => {
            const name = [item.first_name, item.middle_name, item.last_name].filter(Boolean).join(' ');
            return `<option value="${Number(item.id)}">${this.esc(item.admission_no || '')} ${this.esc(name)}</option>`;
          }).join('');
          if (!results.length) select.innerHTML = '<option value="">No matching learners</option>';
        } catch (error) {
          if (sequence === searchSequence) select.innerHTML = '<option value="">Search unavailable</option>';
        }
      }, 250);
    });
    if (select) select.addEventListener('change', () => this.loadSelected());
    if (studentId) await this.load(studentId);
    window.APIRealtime?.register?.("student_timeline", {
      refresh: async () => { if (studentId) { this.state.referenceCache = null; await this.load(studentId); } }
    }, ["students"]);
  },
};

window.studentTimelineController = studentTimelineController;
document.addEventListener('DOMContentLoaded', () => studentTimelineController.init().catch(() => {}));
