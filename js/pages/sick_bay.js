/**
 * Sick Bay Controller
 * Daily log of students currently in or dismissed from sick bay.
 * API base: /api/health/sick-bay
 */

const sickBayController = {

  _students: [],
  _activeStatus: '',

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    // Set today as default date filter
    const df = document.getElementById('sbDateFilter');
    if (df) df.value = new Date().toISOString().split('T')[0];

    // Status tab buttons
    document.querySelectorAll('#sbStatusTabs button').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#sbStatusTabs button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        this._activeStatus = btn.dataset.status || '';
        this.loadVisits();
      });
    });

    // Set default time
    const vt = document.getElementById('sbVisitTime');
    if (vt) vt.value = new Date().toTimeString().substring(0, 5);
    const vd = document.getElementById('sbVisitDate');
    if (vd) vd.value = new Date().toISOString().split('T')[0];

    await this._loadStudentDropdown();
    this.loadStats();
    this.loadVisits();
  },

  // ── STATS ──────────────────────────────────────────────────────────

  loadStats: async function () {
    try {
      const r = await callAPI('/health/summary', 'GET');
      const d = r?.data ?? r ?? {};
      this._set('sbStatActive',    d.active_sick_bay_visits ?? '—');
      this._set('sbStatToday',     d.visits_today           ?? '—');
      this._set('sbStatReferred',  d.referred_to_hospital   ?? '—');
    } catch (e) { console.warn('Sick bay stats failed:', e); }
    // Count dismissed today separately
    try {
      const r2 = await callAPI('/health/sick-bay?status=dismissed&date=' + (document.getElementById('sbDateFilter')?.value || new Date().toISOString().split('T')[0]), 'GET');
      const list = Array.isArray(r2?.data) ? r2.data : [];
      this._set('sbStatDismissed', list.length);
    } catch (e) {}
  },

  // ── VISITS ─────────────────────────────────────────────────────────

  loadVisits: async function () {
    const container = document.getElementById('sbVisitsContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    try {
      const date   = document.getElementById('sbDateFilter')?.value || '';
      const status = this._activeStatus;
      const params = new URLSearchParams();
      if (date)   params.set('date',   date);
      if (status) params.set('status', status);

      const r     = await callAPI('/health/sick-bay' + (params.toString() ? '?' + params : ''), 'GET');
      const list  = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!list.length) {
        container.innerHTML = '<div class="alert alert-info text-center">No sick bay visits found for the selected filter.</div>';
        return;
      }

      const rows = list.map(v => {
        const sc = v.status === 'active' ? 'danger' : v.status === 'referred' ? 'warning' : 'success';
        return `<tr>
          <td class="fw-semibold">${this._esc(v.first_name + ' ' + v.last_name)}</td>
          <td>${this._esc(v.admission_no || '—')}</td>
          <td>${this._esc(v.class_name   || '—')}</td>
          <td>${v.visit_time ? v.visit_time.substring(0,5) : '—'}</td>
          <td>${this._esc(v.complaint)}</td>
          <td>${v.temperature ? v.temperature + '°C' : '—'}</td>
          <td>${this._esc(v.treatment_given || '—')}</td>
          <td>${v.referred_to_hospital ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-success">No</span>'}</td>
          <td>${v.parent_notified ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>'}</td>
          <td><span class="badge bg-${sc}">${v.status}</span></td>
          <td>
            <button class="btn btn-sm btn-outline-secondary me-1" onclick="sickBayController.editVisit(${v.id})">
              <i class="bi bi-pencil"></i>
            </button>
            ${v.status === 'active' ? `<button class="btn btn-sm btn-success" onclick="sickBayController.dismissStudent(${v.id})">
              <i class="bi bi-box-arrow-right me-1"></i>Dismiss
            </button>` : ''}
          </td>
        </tr>`;
      }).join('');

      container.innerHTML = `<div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light"><tr>
            <th>Student</th><th>Adm No</th><th>Class</th><th>Time</th><th>Complaint</th>
            <th>Temp</th><th>Treatment</th><th>Referred</th><th>Parent Notified</th><th>Status</th><th></th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table></div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load visits: ${this._esc(e.message)}</div>`;
    }
  },

  // ── ADMIT / EDIT ───────────────────────────────────────────────────

  showAdmitModal: function () {
    document.getElementById('admitModalTitle').textContent = 'Admit Student to Sick Bay';
    document.getElementById('sbVisitId').value   = '';
    document.getElementById('sbStudentId').value = '';
    document.getElementById('sbComplaint').value = '';
    document.getElementById('sbSymptoms').value  = '';
    document.getElementById('sbDiagnosis').value = '';
    document.getElementById('sbTreatment').value = '';
    document.getElementById('sbMeds').value      = '';
    document.getElementById('sbTemp').value      = '';
    document.getElementById('sbWeight').value    = '';
    document.getElementById('sbRefHospital').value = '';
    document.getElementById('sbNotes').value     = '';
    document.getElementById('sbReferred').checked        = false;
    document.getElementById('sbParentNotified').checked  = false;
    document.getElementById('sbStatus').value    = 'active';
    document.getElementById('sbVisitDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('sbVisitTime').value = new Date().toTimeString().substring(0,5);
    document.getElementById('sbRefHospital').closest('.col-md-6').style.display = 'none';
    document.getElementById('sbError').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('admitModal')).show();
  },

  editVisit: async function (id) {
    try {
      // Fetch visit by filtering all and finding by id — or we can use a direct endpoint
      const r   = await callAPI('/health/sick-bay', 'GET');
      const all = Array.isArray(r?.data) ? r.data : [];
      const v   = all.find(x => x.id == id);
      if (!v) return this.showAdmitModal();

      document.getElementById('admitModalTitle').textContent = 'Update Sick Bay Visit';
      document.getElementById('sbVisitId').value       = v.id;
      document.getElementById('sbStudentId').value     = v.student_id;
      document.getElementById('sbComplaint').value     = v.complaint || '';
      document.getElementById('sbSymptoms').value      = v.symptoms  || '';
      document.getElementById('sbDiagnosis').value     = v.diagnosis || '';
      document.getElementById('sbTreatment').value     = v.treatment_given || '';
      document.getElementById('sbMeds').value          = v.medication_given || '';
      document.getElementById('sbTemp').value          = v.temperature || '';
      document.getElementById('sbWeight').value        = v.weight_kg  || '';
      document.getElementById('sbRefHospital').value   = v.referral_hospital || '';
      document.getElementById('sbNotes').value         = v.notes || '';
      document.getElementById('sbReferred').checked        = !!v.referred_to_hospital;
      document.getElementById('sbParentNotified').checked  = !!v.parent_notified;
      document.getElementById('sbStatus').value        = v.status || 'active';
      document.getElementById('sbVisitDate').value     = v.visit_date || '';
      document.getElementById('sbVisitTime').value     = (v.visit_time || '').substring(0,5);
      document.getElementById('sbRefHospital').closest('.col-md-6').style.display = v.referred_to_hospital ? 'block' : 'none';
      document.getElementById('sbError').classList.add('d-none');
      new bootstrap.Modal(document.getElementById('admitModal')).show();
    } catch (e) { showNotification('Failed to load visit', 'danger'); }
  },

  saveVisit: async function () {
    const errEl = document.getElementById('sbError');
    errEl.classList.add('d-none');
    const visitId   = document.getElementById('sbVisitId').value;
    const studentId = document.getElementById('sbStudentId').value;
    const complaint = document.getElementById('sbComplaint').value.trim();
    if (!studentId || !complaint) {
      errEl.textContent = 'Student and complaint are required'; errEl.classList.remove('d-none'); return;
    }
    const payload = {
      student_id:           parseInt(studentId),
      visit_date:           document.getElementById('sbVisitDate').value,
      visit_time:           document.getElementById('sbVisitTime').value,
      complaint,
      symptoms:             document.getElementById('sbSymptoms').value.trim(),
      diagnosis:            document.getElementById('sbDiagnosis').value.trim(),
      treatment_given:      document.getElementById('sbTreatment').value.trim(),
      medication_given:     document.getElementById('sbMeds').value.trim(),
      temperature:          document.getElementById('sbTemp').value || null,
      weight_kg:            document.getElementById('sbWeight').value || null,
      referred_to_hospital: document.getElementById('sbReferred').checked ? 1 : 0,
      referral_hospital:    document.getElementById('sbRefHospital').value.trim(),
      parent_notified:      document.getElementById('sbParentNotified').checked ? 1 : 0,
      status:               document.getElementById('sbStatus').value,
      notes:                document.getElementById('sbNotes').value.trim(),
    };
    try {
      if (visitId) {
        await callAPI('/health/sick-bay/' + visitId, 'PUT', payload);
        showNotification('Visit updated', 'success');
      } else {
        await callAPI('/health/sick-bay', 'POST', payload);
        showNotification('Student admitted to sick bay', 'success');
      }
      bootstrap.Modal.getInstance(document.getElementById('admitModal')).hide();
      this.loadStats(); this.loadVisits();
    } catch (e) { errEl.textContent = e.message || 'Save failed'; errEl.classList.remove('d-none'); }
  },

  dismissStudent: async function (id) {
    if (!confirm('Dismiss this student from the sick bay?')) return;
    try {
      await callAPI('/health/sick-bay/' + id + '/dismiss', 'PUT', {});
      showNotification('Student dismissed', 'success');
      this.loadStats(); this.loadVisits();
    } catch (e) { showNotification('Error: ' + e.message, 'danger'); }
  },

  // ── STUDENTS ───────────────────────────────────────────────────────

  _loadStudentDropdown: async function () {
    try {
      const r = await callAPI('/students/list', 'GET').catch(() => callAPI('/students', 'GET'));
      const students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('sbStudentId');
      if (!sel) return;
      sel.innerHTML = '<option value="">— Select student —</option>';
      students.forEach(s => {
        const o = document.createElement('option');
        o.value = s.id;
        o.textContent = `${s.first_name} ${s.last_name} (${s.admission_no || s.id})`;
        sel.appendChild(o);
      });
    } catch (e) { console.warn('Could not load students', e); }
  },

  // ── UTILS ──────────────────────────────────────────────────────────

  _set: function (id, val) { const el = document.getElementById(id); if (el) el.textContent = val; },
  _esc: function (s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  },
};
