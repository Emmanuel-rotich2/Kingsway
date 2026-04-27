/**
 * Student Timeline Controller
 * API: GET /academic/student-timeline/{id}
 *      POST /academic/transfer-requests
 */
const studentTimelineController = {
  _data: null,
  _studentId: null,
  _modal: null,
  _allStudents: [],

  init: async function () {
    if (!AuthContext.isAuthenticated()) return;
    this._canTransfer = AuthContext.hasPermission('students.transfer') || AuthContext.hasPermission('academic.manage');
    this._modal = new bootstrap.Modal(document.getElementById('transferModal'));
    await this._loadStudentList();

    document.getElementById('tlStudentSearch')?.addEventListener('input', () => this._filterStudents());
  },

  _loadStudentList: async function () {
    try {
      const r = await callAPI('/students/all', 'GET');
      this._allStudents = (r?.data || r || []).filter(s => s.status === 'active' || s.status === 'inactive');
      this._populateSelect(this._allStudents);
    } catch (e) { /* non-fatal */ }
  },

  _filterStudents: function () {
    const q = (document.getElementById('tlStudentSearch')?.value || '').toLowerCase();
    if (!q) { this._populateSelect(this._allStudents); return; }
    const filtered = this._allStudents.filter(s =>
      (s.full_name || '').toLowerCase().includes(q) ||
      (s.admission_no || '').toLowerCase().includes(q) ||
      (s.nemis_number || '').toLowerCase().includes(q)
    );
    this._populateSelect(filtered);
  },

  _populateSelect: function (list) {
    const sel = document.getElementById('tlStudentSelect');
    if (!sel) return;
    sel.innerHTML = '<option value="">— Select student —</option>' +
      list.map(s => `<option value="${s.id}">${s.full_name || (s.first_name + ' ' + s.last_name)} (${s.admission_no})</option>`).join('');
  },

  load: async function () {
    const sel = document.getElementById('tlStudentSelect');
    const id  = sel?.value;
    if (!id) { showNotification('Please select a student first.', 'warning'); return; }
    this._studentId = id;

    document.getElementById('tlContent').style.display = 'none';
    document.getElementById('tlEmpty').style.display = 'none';

    try {
      const r = await callAPI('/academic/student-timeline/' + id, 'GET');
      this._data = r?.data || r;
      this._render();
    } catch (e) {
      showNotification(e.message || 'Failed to load student record.', 'danger');
      document.getElementById('tlEmpty').style.display = '';
    }
  },

  _render: function () {
    const d = this._data;
    if (!d?.student) return;
    const s = d.student;

    // Bio
    document.getElementById('tlPhoto').src = (window.APP_BASE || '') + '/' + (s.photo_url || 'assets/images/avatar.png');
    document.getElementById('tlStudentName').textContent = [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' ');
    document.getElementById('tlAdmNo').textContent     = s.admission_no || '—';
    document.getElementById('tlDob').textContent       = s.date_of_birth || '—';
    document.getElementById('tlGender').textContent    = s.gender || '—';
    document.getElementById('tlClass').textContent     = [s.current_class, s.current_stream].filter(Boolean).join(' / ') || '—';
    document.getElementById('tlAdmDate').textContent   = s.admission_date || '—';
    document.getElementById('tlStudentType').textContent = s.student_type || '—';
    const badge = document.getElementById('tlStatusBadge');
    const colors = {active:'success',inactive:'secondary',transferred:'warning',graduated:'primary',suspended:'danger'};
    badge.className = 'badge fs-6 bg-' + (colors[s.status] || 'secondary');
    badge.textContent = s.status || '—';
    if (s.is_sponsored) {
      document.getElementById('tlSponsor').textContent = `Sponsored by ${s.sponsor_name} (${s.sponsor_type})`;
    }

    // Summary KPIs
    const sum = d.summary || {};
    this._set('tlYearsEnrolled', sum.years_enrolled || 0);
    this._set('tlTotalPaid', 'KES ' + Number(sum.total_fees_paid || 0).toLocaleString());
    this._set('tlBalance', 'KES ' + Number(sum.current_balance || 0).toLocaleString());
    this._set('tlDisciplineCount', sum.discipline_cases || 0);

    document.getElementById('exportBtn').style.display = '';

    // Academic tab
    this._renderAcademic(d.academics || [], d.subject_scores || []);

    // Finance
    this._renderPayments(d.payments || []);
    this._renderCredits(d.credit_notes || []);
    this._renderObligations(d.fee_obligations || []);

    // Attendance
    this._renderAttendance(d.attendance || []);

    // Discipline
    this._renderDiscipline(d.discipline || []);

    // Transfers
    this._renderTransfers(d.transfers || []);

    document.getElementById('tlContent').style.display = '';
  },

  _renderAcademic: function (years, scores) {
    const container = document.getElementById('tlAcademicContent');
    if (!years.length) {
      container.innerHTML = '<p class="text-muted text-center py-4">No academic records found.</p>';
      return;
    }
    // Group scores by academic_year
    const scoresByYear = {};
    scores.forEach(sc => {
      const key = sc.academic_year + '-' + sc.term_number;
      if (!scoresByYear[key]) scoresByYear[key] = [];
      scoresByYear[key].push(sc);
    });

    const gradeColor = {'EE':'success','ME':'primary','AE':'warning','BE':'danger'};

    container.innerHTML = years.map((y, i) => {
      const promoStatus = {promoted:'success',retained:'warning',transferred:'info',graduated:'primary',withdrawn:'secondary'}[y.promotion_status] || 'secondary';
      const terms = [1,2,3].map(tn => {
        const key = y.academic_year + '-' + tn;
        const termScores = scoresByYear[key] || [];
        if (!termScores.length) return `<li class="list-group-item small text-muted">Term ${tn}: No results recorded</li>`;
        return `<li class="list-group-item p-2">
          <strong class="d-block mb-1">Term ${tn}</strong>
          <table class="table table-sm table-bordered mb-0 small">
            <thead class="table-light"><tr><th>Subject</th><th class="text-center">Formative</th><th class="text-center">Summative</th><th class="text-center">Overall</th><th class="text-center">Grade</th></tr></thead>
            <tbody>${termScores.map(sc => `<tr>
              <td>${this._esc(sc.subject_name)}</td>
              <td class="text-center">${sc.formative_percentage ? sc.formative_percentage + '%' : '—'}</td>
              <td class="text-center">${sc.summative_percentage ? sc.summative_percentage + '%' : '—'}</td>
              <td class="text-center">${sc.overall_percentage ? sc.overall_percentage + '%' : '—'}</td>
              <td class="text-center"><span class="badge bg-${gradeColor[sc.overall_grade] || 'secondary'}">${sc.overall_grade || '—'}</span></td>
            </tr>`).join('')}</tbody>
          </table>
        </li>`;
      }).join('');

      return `<div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
          <strong><i class="bi bi-calendar-year me-2"></i>${this._esc(y.year_name)} — ${this._esc(y.class_name)} ${y.stream_name || ''}</strong>
          <div class="d-flex gap-2 align-items-center">
            ${y.year_average ? `<span class="badge bg-white text-dark">Avg: ${y.year_average}%</span>` : ''}
            ${y.overall_grade ? `<span class="badge bg-${gradeColor[y.overall_grade] || 'secondary'}">${y.overall_grade}</span>` : ''}
            ${y.class_rank ? `<span class="badge bg-warning text-dark">Rank ${y.class_rank}</span>` : ''}
            ${y.promotion_status ? `<span class="badge bg-${promoStatus}">${y.promotion_status}</span>` : ''}
          </div>
        </div>
        <div class="card-body p-0">
          <div class="row g-0 border-bottom small py-2 px-3 text-muted">
            <div class="col-auto me-3"><i class="bi bi-calendar-check me-1"></i>T1: ${y.term1_average || '—'}%</div>
            <div class="col-auto me-3"><i class="bi bi-calendar-check me-1"></i>T2: ${y.term2_average || '—'}%</div>
            <div class="col-auto me-3"><i class="bi bi-calendar-check me-1"></i>T3: ${y.term3_average || '—'}%</div>
            <div class="col-auto me-3"><i class="bi bi-person-check me-1"></i>Attendance: ${y.attendance_percentage || '—'}%</div>
            ${y.promoted_to_class ? `<div class="col-auto text-success"><i class="bi bi-arrow-up-circle me-1"></i>Promoted to ${this._esc(y.promoted_to_class)}</div>` : ''}
          </div>
          <ul class="list-group list-group-flush">${terms}</ul>
          ${y.teacher_comments ? `<div class="p-3 border-top small"><strong>Teacher:</strong> ${this._esc(y.teacher_comments)}</div>` : ''}
          ${y.head_teacher_comments ? `<div class="p-3 border-top small"><strong>Head Teacher:</strong> ${this._esc(y.head_teacher_comments)}</div>` : ''}
        </div>
      </div>`;
    }).join('');
  },

  _renderPayments: function (payments) {
    const tbody = document.getElementById('tlPaymentsBody');
    if (!payments.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No payment records</td></tr>';
      return;
    }
    tbody.innerHTML = payments.map(p => `<tr>
      <td>${p.payment_date ? p.payment_date.split(' ')[0] : '—'}</td>
      <td>${p.academic_year || '—'}</td>
      <td>${p.term_name || '—'}</td>
      <td class="text-end text-success fw-bold">KES ${Number(p.amount_paid || 0).toLocaleString()}</td>
      <td>${this._esc(p.payment_method || '—')}</td>
      <td><small>${this._esc(p.receipt_no || '—')}</small></td>
      <td><span class="badge bg-${p.status === 'confirmed' ? 'success' : 'secondary'}">${p.status || '—'}</span></td>
    </tr>`).join('');
  },

  _renderCredits: function (credits) {
    const tbody = document.getElementById('tlCreditsBody');
    if (!credits.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-2">No credit notes</td></tr>';
      return;
    }
    const colors = {available:'success',partially_applied:'primary',fully_applied:'secondary',refunded:'info',expired:'danger'};
    tbody.innerHTML = credits.map(c => `<tr>
      <td><small>${this._esc(c.credit_number)}</small></td>
      <td class="text-end">KES ${Number(c.credit_amount || 0).toLocaleString()}</td>
      <td class="text-end text-success">KES ${Number(c.remaining_amount || 0).toLocaleString()}</td>
      <td><span class="badge bg-${colors[c.status] || 'secondary'}">${c.status || '—'}</span></td>
    </tr>`).join('');
  },

  _renderObligations: function (obligations) {
    const tbody = document.getElementById('tlObligationsBody');
    const outstanding = obligations.filter(o => (o.balance || 0) > 0);
    if (!outstanding.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-success py-2"><i class="bi bi-check-circle me-1"></i>No outstanding fees</td></tr>';
      return;
    }
    tbody.innerHTML = outstanding.map(o => `<tr>
      <td>${o.academic_year || '—'}</td>
      <td>${o.term_name || '—'}</td>
      <td class="small">${this._esc(o.fee_name || '—')}</td>
      <td class="text-end fw-bold text-danger">KES ${Number(o.balance || 0).toLocaleString()}</td>
    </tr>`).join('');
  },

  _renderAttendance: function (att) {
    const tbody = document.getElementById('tlAttendanceBody');
    if (!att.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No attendance data</td></tr>';
      return;
    }
    tbody.innerHTML = att.map(a => {
      const pct = a.total_recorded > 0 ? Math.round(a.days_present / a.total_recorded * 100) : 0;
      const color = pct >= 80 ? 'success' : pct >= 70 ? 'warning' : 'danger';
      return `<tr>
        <td>${a.academic_year || '—'}</td>
        <td>${a.term_name || 'Term ' + a.term_number}</td>
        <td class="text-center text-success">${a.days_present || 0}</td>
        <td class="text-center text-danger">${a.days_absent || 0}</td>
        <td class="text-center text-warning">${a.days_late || 0}</td>
        <td class="text-center">${a.total_recorded || 0}</td>
        <td class="text-end"><span class="badge bg-${color}">${pct}%</span></td>
      </tr>`;
    }).join('');
  },

  _renderDiscipline: function (cases) {
    const container = document.getElementById('tlDisciplineContent');
    if (!cases.length) {
      container.innerHTML = '<div class="text-center py-4 text-success"><i class="bi bi-shield-check fs-3 me-2"></i>No conduct issues on record</div>';
      return;
    }
    const sevColor = {low:'info',medium:'warning',high:'danger',critical:'dark'};
    container.innerHTML = `<div class="list-group">${cases.map(c => `
      <div class="list-group-item">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="badge bg-${sevColor[c.severity] || 'secondary'} me-2">${c.severity || '—'}</span>
            <strong>${this._esc(c.incident_type || '—')}</strong>
            <span class="text-muted ms-2 small">${c.academic_year || ''} ${c.term_number ? '— T' + c.term_number : ''}</span>
          </div>
          <small class="text-muted">${c.incident_date || ''}</small>
        </div>
        <p class="mb-1 mt-1 small">${this._esc(c.description || '—')}</p>
        ${c.action_taken ? `<small class="text-muted"><i class="bi bi-check2-circle me-1"></i>Action: ${this._esc(c.action_taken)}</small>` : ''}
      </div>`).join('')}</div>`;
  },

  _renderTransfers: function (transfers) {
    const container = document.getElementById('tlTransfersContent');
    const actions   = document.getElementById('tlTransferActions');

    if (!transfers.length) {
      container.innerHTML = '<p class="text-muted text-center py-3">No transfer requests on record.</p>';
    } else {
      const stColors = {draft:'secondary',pending_clearance:'warning',clearance_passed:'info',approved:'success',rejected:'danger',completed:'primary',cancelled:'secondary'};
      container.innerHTML = `<div class="list-group">${transfers.map(t => `
        <div class="list-group-item">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <strong>${this._esc(t.request_number || '—')}</strong>
              <span class="badge bg-${stColors[t.status] || 'secondary'} ms-2">${t.status}</span>
              <span class="ms-2 small text-muted">${t.transfer_type}</span>
            </div>
            <small class="text-muted">${t.request_date || ''}</small>
          </div>
          ${t.destination_school ? `<div class="small mt-1"><i class="bi bi-building me-1"></i>To: ${this._esc(t.destination_school)}</div>` : ''}
          ${t.fee_balance_at_request > 0 ? `<div class="small text-danger mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Fee balance at request: KES ${Number(t.fee_balance_at_request).toLocaleString()}</div>` : ''}
        </div>`).join('')}</div>`;
    }

    if (this._canTransfer) {
      const student = this._data?.student;
      const hasActive = transfers.some(t => ['draft','pending_clearance','clearance_passed','approved'].includes(t.status));
      if (!hasActive && student?.status === 'active') {
        actions.innerHTML = `<button class="btn btn-warning" onclick="studentTimelineController.showTransferModal()">
          <i class="bi bi-arrow-right-circle me-2"></i>Initiate Transfer / Withdrawal
        </button>`;
      }
    }
  },

  showTransferModal: function () {
    document.getElementById('tr_student_id').value = this._studentId;
    document.getElementById('tr_destination').value = '';
    document.getElementById('tr_reason').value = '';
    this._modal.show();
  },

  submitTransfer: async function () {
    const studentId = document.getElementById('tr_student_id').value;
    try {
      await callAPI('/academic/transfer-requests', 'POST', {
        student_id: studentId,
        transfer_type: document.getElementById('tr_type').value,
        destination_school: document.getElementById('tr_destination').value,
        reason: document.getElementById('tr_reason').value,
      });
      showNotification('Transfer request submitted. Clearance process started.', 'success');
      this._modal.hide();
      await this.load();
    } catch (e) {
      showNotification(e.message || 'Transfer request failed.', 'danger');
    }
  },

  exportPDF: function () {
    showNotification('PDF export not yet implemented — use browser Print → Save as PDF.', 'info');
    window.print();
  },

  _set: (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};
