/**
 * Student Profile Viewer Controller
 * Supports search-then-view and direct ?id= deep-link.
 * API: /students/*, /finance
 */

const studentProfilesController = {

  _currentId: null,
  _searchTimer: null,

  // ── INIT ────────────────────────────────────────────────────────────

  init: function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }

    // Show Edit button only when user has students.edit
    const editBtn = document.getElementById('spEditBtn');
    if (editBtn && AuthContext.hasPermission('students.edit')) {
      editBtn.style.display = '';
    }

    // Bind lazy-load on tab switch
    this._bindTabSwitch();

    // Check for ?id= in URL
    const params = new URLSearchParams(window.location.search);
    const id = parseInt(params.get('id'), 10);
    if (id && id > 0) {
      this.loadProfile(id);
    } else {
      this._showSearch();
    }
  },

  _bindTabSwitch: function () {
    document.querySelectorAll('#spTabs button[data-bs-toggle="tab"]').forEach(btn => {
      btn.addEventListener('shown.bs.tab', () => {
        if (!this._currentId) return;
        // Reload if container still shows a spinner (not yet populated)
        const target = btn.getAttribute('data-bs-target');
        const map = {
          '#spTabAcademic':   ['spAcademicBody',   () => this.loadAcademic(this._currentId)],
          '#spTabAttendance': ['spAttendanceBody',  () => this.loadAttendance(this._currentId)],
          '#spTabFees':       ['spFeesBody',        () => this.loadFees(this._currentId)],
          '#spTabDiscipline': ['spDisciplineBody',  () => this.loadDiscipline(this._currentId)],
        };
        if (map[target]) {
          const [containerId, loader] = map[target];
          const el = document.getElementById(containerId);
          if (el && el.querySelector('.spinner-border')) loader();
        }
      });
    });
  },

  // ── SEARCH ──────────────────────────────────────────────────────────

  _showSearch: function () {
    const sv = document.getElementById('spSearchView');
    const pv = document.getElementById('spProfileView');
    if (sv) sv.style.display = '';
    if (pv) pv.style.display = 'none';
  },

  backToSearch: function () {
    this._currentId = null;
    this._showSearch();
  },

  search: function (query) {
    clearTimeout(this._searchTimer);
    const container = document.getElementById('spSearchResults');
    if (!container) return;

    if (!query || query.trim().length < 2) {
      container.innerHTML = '<div class="text-center text-muted py-5">' +
        '<i class="bi bi-person-circle fs-1 d-block mb-2 opacity-25"></i>' +
        'Start typing to find a student.</div>';
      return;
    }

    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

    this._searchTimer = setTimeout(async () => {
      try {
        const r = await callAPI('/students?search=' + encodeURIComponent(query.trim()), 'GET');
        const students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

        if (!students.length) {
          container.innerHTML = '<div class="alert alert-warning">No students found matching <strong>' +
            this._esc(query) + '</strong>.</div>';
          return;
        }

        const rows = students.map(s => `
          <tr style="cursor:pointer;" onclick="studentProfilesController.loadProfile(${s.id})">
            <td class="fw-semibold">${this._esc((s.first_name || '') + ' ' + (s.last_name || ''))}</td>
            <td>${this._esc(s.admission_no || '—')}</td>
            <td>${this._esc(s.class_name || s.grade || '—')}</td>
            <td>${this._esc(s.gender || '—')}</td>
            <td>
              <span class="badge bg-${s.status === 'active' ? 'success' : 'secondary'}">
                ${this._esc(s.status || 'unknown')}
              </span>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-primary"
                      onclick="event.stopPropagation(); studentProfilesController.loadProfile(${s.id})">
                <i class="bi bi-eye me-1"></i>View
              </button>
            </td>
          </tr>
        `).join('');

        container.innerHTML = `<div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Name</th><th>Adm No</th><th>Class</th>
                <th>Gender</th><th>Status</th><th></th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>`;
      } catch (e) {
        container.innerHTML = `<div class="alert alert-danger">Search failed: ${this._esc(e.message)}</div>`;
      }
    }, 350);
  },

  // ── LOAD FULL PROFILE ───────────────────────────────────────────────

  loadProfile: async function (id) {
    this._currentId = id;

    // Reset spinners in all tabs
    ['spGeneralBody', 'spAcademicBody', 'spAttendanceBody', 'spFeesBody', 'spDisciplineBody'].forEach(cid => {
      const el = document.getElementById(cid);
      if (el) el.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    });

    // Switch to profile view
    const sv = document.getElementById('spSearchView');
    const pv = document.getElementById('spProfileView');
    if (sv) sv.style.display = 'none';
    if (pv) pv.style.display = '';

    // Load General and Academic immediately; others load on tab switch (lazy)
    await Promise.all([
      this.loadGeneral(id),
      this.loadAcademic(id),
    ]);
  },

  // ── GENERAL TAB ─────────────────────────────────────────────────────

  loadGeneral: async function (id) {
    const container = document.getElementById('spGeneralBody');
    if (!container) return;
    try {
      const r = await callAPI('/students/profile-get/' + id, 'GET');
      const s = r?.data ?? r;

      if (!s || !s.id) {
        container.innerHTML = '<div class="alert alert-warning">No data found.</div>';
        return;
      }

      // Populate summary card
      const fullName = this._esc((s.first_name || '') + ' ' + (s.last_name || ''));
      this._set('spStudentName',    fullName);
      this._set('spStudentHeading', fullName + ' — Profile');
      this._set('spClass',          s.class_name || s.grade || '—');
      this._set('spAdmNo',          s.admission_no || '—');
      this._set('spGender',         s.gender || '—');
      this._set('spDob',            s.date_of_birth || s.dob || '—');

      const badge = document.getElementById('spStatusBadge');
      if (badge) {
        const status  = (s.status || 'unknown').toLowerCase();
        const colourMap = { active: 'success', inactive: 'secondary', suspended: 'warning', expelled: 'danger' };
        badge.className = 'badge fs-6 bg-' + (colourMap[status] || 'secondary');
        badge.textContent = s.status || 'Unknown';
      }

      // General tab body — detailed fields
      container.innerHTML = `
        <div class="row g-3">
          <div class="col-md-6">
            <h6 class="fw-semibold text-muted text-uppercase mb-2 small">Personal Details</h6>
            ${this._row('Full Name',      fullName)}
            ${this._row('Date of Birth',  this._esc(s.date_of_birth || s.dob || '—'))}
            ${this._row('Gender',         this._esc(s.gender || '—'))}
            ${this._row('Nationality',    this._esc(s.nationality || '—'))}
            ${this._row('Religion',       this._esc(s.religion || '—'))}
            ${this._row('Special Needs',  this._esc(s.special_needs || 'None'))}
          </div>
          <div class="col-md-6">
            <h6 class="fw-semibold text-muted text-uppercase mb-2 small">Academic Details</h6>
            ${this._row('Admission No.',  this._esc(s.admission_no || '—'))}
            ${this._row('Class',          this._esc(s.class_name || s.grade || '—'))}
            ${this._row('Stream',         this._esc(s.stream || '—'))}
            ${this._row('Admission Date', this._esc(s.admission_date || '—'))}
            ${this._row('Status',         this._esc(s.status || '—'))}
          </div>
          <div class="col-12"><hr class="my-1"></div>
          <div class="col-md-6">
            <h6 class="fw-semibold text-muted text-uppercase mb-2 small">Parent / Guardian</h6>
            ${this._row('Parent Name',    this._esc(s.parent_name || s.guardian_name || '—'))}
            ${this._row('Phone',          this._esc(s.parent_phone || s.guardian_phone || '—'))}
            ${this._row('Email',          this._esc(s.parent_email || s.guardian_email || '—'))}
            ${this._row('Relationship',   this._esc(s.parent_relationship || '—'))}
          </div>
          <div class="col-md-6">
            <h6 class="fw-semibold text-muted text-uppercase mb-2 small">Address</h6>
            ${this._row('Address',        this._esc(s.address || s.home_address || '—'))}
            ${this._row('County',         this._esc(s.county || '—'))}
            ${this._row('Sub-county',     this._esc(s.sub_county || '—'))}
          </div>
        </div>`;
    } catch (e) {
      if (container) container.innerHTML = `<div class="alert alert-danger">Failed to load profile: ${this._esc(e.message)}</div>`;
    }
  },

  // ── ACADEMIC TAB ────────────────────────────────────────────────────

  loadAcademic: async function (id) {
    const container = document.getElementById('spAcademicBody');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    try {
      const r = await callAPI('/students/performance-get/' + id, 'GET');
      const subjects = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!subjects.length) {
        container.innerHTML = '<div class="alert alert-warning">No academic records found.</div>';
        return;
      }

      const rows = subjects.map(sub => {
        const combined = sub.combined_score ?? sub.score ?? '—';
        const grade    = sub.cbc_grade || sub.grade || '—';
        const gradeCls = { EE: 'success', ME: 'info', AE: 'warning', BE: 'danger' }[grade] || 'secondary';
        return `<tr>
          <td class="fw-semibold">${this._esc(sub.subject || sub.learning_area || '—')}</td>
          <td>${this._esc(sub.formative_avg ?? sub.formative_score ?? '—')}</td>
          <td>${this._esc(sub.summative_avg ?? sub.summative_score ?? '—')}</td>
          <td class="fw-bold">${this._esc(combined)}</td>
          <td><span class="badge bg-${gradeCls}">${this._esc(grade)}</span></td>
          <td class="text-muted small">${this._esc(sub.term || sub.period || '—')}</td>
        </tr>`;
      }).join('');

      container.innerHTML = `<div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Subject / Learning Area</th>
              <th>Formative (40%)</th>
              <th>Summative (60%)</th>
              <th>Combined</th>
              <th>CBC Grade</th>
              <th>Term / Period</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
    } catch (e) {
      if (container) container.innerHTML = `<div class="alert alert-danger">Failed to load academic data: ${this._esc(e.message)}</div>`;
    }
  },

  // ── ATTENDANCE TAB ──────────────────────────────────────────────────

  loadAttendance: async function (id) {
    const container = document.getElementById('spAttendanceBody');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    try {
      const r = await callAPI('/students/attendance-get/' + id, 'GET');
      const d = r?.data ?? r ?? {};

      const summary = d.summary ?? d;
      const records  = Array.isArray(d.records) ? d.records : (Array.isArray(r?.data) ? [] : []);

      const present  = summary.present   ?? summary.days_present  ?? '—';
      const absent   = summary.absent    ?? summary.days_absent   ?? '—';
      const late     = summary.late      ?? summary.days_late     ?? '—';
      const rate     = summary.rate      ?? summary.attendance_rate ?? null;

      let summaryHtml = `
        <div class="row g-3 mb-4">
          <div class="col-6 col-md-3">
            <div class="card border-0 bg-success bg-opacity-10 text-center">
              <div class="card-body py-2">
                <div class="fs-3 fw-bold text-success">${this._esc(present)}</div>
                <div class="small text-muted">Days Present</div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 bg-danger bg-opacity-10 text-center">
              <div class="card-body py-2">
                <div class="fs-3 fw-bold text-danger">${this._esc(absent)}</div>
                <div class="small text-muted">Days Absent</div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 bg-warning bg-opacity-10 text-center">
              <div class="card-body py-2">
                <div class="fs-3 fw-bold text-warning">${this._esc(late)}</div>
                <div class="small text-muted">Days Late</div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 bg-info bg-opacity-10 text-center">
              <div class="card-body py-2">
                <div class="fs-3 fw-bold text-info">${rate !== null ? this._esc(rate) + '%' : '—'}</div>
                <div class="small text-muted">Attendance Rate</div>
              </div>
            </div>
          </div>
        </div>`;

      let recordsHtml = '';
      if (records.length) {
        const rows = records.map(rec => {
          const statusCls = { present: 'success', absent: 'danger', late: 'warning', excused: 'info' }
            [(rec.status || '').toLowerCase()] || 'secondary';
          return `<tr>
            <td>${this._esc(rec.date || '—')}</td>
            <td>${this._esc(rec.subject || rec.session || 'School')}</td>
            <td><span class="badge bg-${statusCls}">${this._esc(rec.status || '—')}</span></td>
            <td class="text-muted small">${this._esc(rec.remarks || rec.notes || '')}</td>
          </tr>`;
        }).join('');
        recordsHtml = `<h6 class="fw-semibold mb-2">Recent Attendance Records</h6>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
              <thead class="table-light">
                <tr><th>Date</th><th>Subject / Session</th><th>Status</th><th>Remarks</th></tr>
              </thead>
              <tbody>${rows}</tbody>
            </table>
          </div>`;
      } else {
        recordsHtml = '<div class="alert alert-warning mt-3">No attendance records found.</div>';
      }

      container.innerHTML = summaryHtml + recordsHtml;
    } catch (e) {
      if (container) container.innerHTML = `<div class="alert alert-danger">Failed to load attendance: ${this._esc(e.message)}</div>`;
    }
  },

  // ── FEES TAB ────────────────────────────────────────────────────────

  loadFees: async function (id) {
    const container = document.getElementById('spFeesBody');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    try {
      const r = await callAPI('/finance?student_id=' + id, 'GET');
      const d = r?.data ?? r ?? {};

      const items    = Array.isArray(d.fees) ? d.fees : (Array.isArray(d) ? d : []);
      const balance  = d.balance ?? d.outstanding_balance ?? null;
      const paid     = d.total_paid ?? null;
      const expected = d.total_expected ?? d.total_fees ?? null;

      let balanceHtml = '';
      if (balance !== null || paid !== null) {
        const isCredit = parseFloat(balance) >= 0;
        balanceHtml = `
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <div class="card border-0 bg-primary bg-opacity-10 text-center">
                <div class="card-body py-2">
                  <div class="fs-3 fw-bold text-primary">KES ${this._esc(expected !== null ? Number(expected).toLocaleString() : '—')}</div>
                  <div class="small text-muted">Total Expected</div>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border-0 bg-success bg-opacity-10 text-center">
                <div class="card-body py-2">
                  <div class="fs-3 fw-bold text-success">KES ${this._esc(paid !== null ? Number(paid).toLocaleString() : '—')}</div>
                  <div class="small text-muted">Total Paid</div>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border-0 bg-${isCredit ? 'warning' : 'success'} bg-opacity-10 text-center">
                <div class="card-body py-2">
                  <div class="fs-3 fw-bold text-${isCredit ? 'warning' : 'success'}">
                    KES ${this._esc(balance !== null ? Math.abs(Number(balance)).toLocaleString() : '—')}
                  </div>
                  <div class="small text-muted">${isCredit ? 'Balance Due' : 'Overpaid'}</div>
                </div>
              </div>
            </div>
          </div>`;
      }

      let feesTableHtml = '';
      if (items.length) {
        const rows = items.map(f => `<tr>
          <td>${this._esc(f.term || f.period || '—')}</td>
          <td>${this._esc(f.description || f.fee_type || '—')}</td>
          <td>KES ${this._esc(Number(f.amount || 0).toLocaleString())}</td>
          <td>KES ${this._esc(Number(f.paid || f.amount_paid || 0).toLocaleString())}</td>
          <td>
            <span class="badge bg-${parseFloat(f.balance ?? (f.amount - f.paid)) <= 0 ? 'success' : 'warning'}">
              KES ${this._esc(Math.abs(Number(f.balance ?? (f.amount - (f.paid || 0)))).toLocaleString())}
            </span>
          </td>
          <td class="text-muted small">${this._esc(f.due_date || '—')}</td>
        </tr>`).join('');
        feesTableHtml = `<h6 class="fw-semibold mb-2">Fee Breakdown</h6>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
              <thead class="table-light">
                <tr><th>Term</th><th>Description</th><th>Expected</th><th>Paid</th><th>Balance</th><th>Due Date</th></tr>
              </thead>
              <tbody>${rows}</tbody>
            </table>
          </div>`;
      } else {
        feesTableHtml = '<div class="alert alert-warning mt-3">No fee records found.</div>';
      }

      container.innerHTML = (balanceHtml || '') + feesTableHtml;
    } catch (e) {
      if (container) container.innerHTML = `<div class="alert alert-danger">Failed to load fees: ${this._esc(e.message)}</div>`;
    }
  },

  // ── DISCIPLINE TAB ──────────────────────────────────────────────────

  loadDiscipline: async function (id) {
    const container = document.getElementById('spDisciplineBody');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    try {
      const r = await callAPI('/students/discipline-get?student_id=' + id, 'GET');
      const cases = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!cases.length) {
        container.innerHTML = '<div class="alert alert-info">No discipline cases on record. <i class="bi bi-check-circle ms-1 text-success"></i></div>';
        return;
      }

      const rows = cases.map(c => {
        const severityCls = { low: 'success', medium: 'warning', high: 'danger', critical: 'dark' }
          [(c.severity || '').toLowerCase()] || 'secondary';
        const statusCls  = { open: 'danger', resolved: 'success', pending: 'warning', closed: 'secondary' }
          [(c.status || '').toLowerCase()] || 'secondary';
        return `<tr>
          <td>${this._esc(c.date || c.incident_date || '—')}</td>
          <td class="fw-semibold">${this._esc(c.offense || c.incident_type || c.title || '—')}</td>
          <td><span class="badge bg-${severityCls}">${this._esc(c.severity || '—')}</span></td>
          <td><span class="badge bg-${statusCls}">${this._esc(c.status || '—')}</span></td>
          <td>${this._esc(c.action_taken || c.action || '—')}</td>
          <td class="text-muted small">${this._esc(c.reported_by || c.teacher_name || '—')}</td>
        </tr>`;
      }).join('');

      container.innerHTML = `<div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Date</th><th>Incident</th><th>Severity</th>
              <th>Status</th><th>Action Taken</th><th>Reported By</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
    } catch (e) {
      if (container) container.innerHTML = `<div class="alert alert-danger">Failed to load discipline records: ${this._esc(e.message)}</div>`;
    }
  },

  // ── EDIT REDIRECT ───────────────────────────────────────────────────

  editStudent: function () {
    if (!this._currentId) return;
    const base = window.APP_BASE || '';
    window.location.href = base + '/home.php?route=manage_students&edit=' + this._currentId;
  },

  // ── UTILS ────────────────────────────────────────────────────────────

  _set: function (id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  },

  _esc: function (s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },

  /** Render a label-value row for the general info grid */
  _row: function (label, value) {
    return `<div class="d-flex justify-content-between border-bottom py-1 small">
      <span class="text-muted">${label}</span>
      <span class="fw-semibold text-end">${value}</span>
    </div>`;
  },
};

document.addEventListener('DOMContentLoaded', () => studentProfilesController.init());
