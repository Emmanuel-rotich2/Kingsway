/**
 * Student Health Records Controller
 * Health profiles, vaccinations.
 * API base: /api/health/*
 */

const healthController = {

  _students: [],

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await this._loadStudentDropdowns();
    this.loadStats();
    this.loadRecords();
    this._bindTabs();
    // default today on vax date
    const dg = document.getElementById('vaxDateGiven');
    if (dg) dg.value = new Date().toISOString().split('T')[0];
  },

  _bindTabs: function () {
    document.querySelectorAll('#healthTabs button[data-bs-toggle="tab"]').forEach(btn => {
      btn.addEventListener('shown.bs.tab', e => {
        const target = e.target.getAttribute('data-bs-target');
        if (target === '#hTabVax') this.loadVaccinations();
      });
    });
  },

  // ── STATS ──────────────────────────────────────────────────────────

  loadStats: async function () {
    try {
      const r = await callAPI('/health/summary', 'GET');
      const d = r?.data ?? r ?? {};
      this._set('hStatActiveSickBay', d.active_sick_bay_visits    ?? '—');
      this._set('hStatToday',         d.visits_today              ?? '—');
      this._set('hStatRecords',       d.students_with_records     ?? '—');
      this._set('hStatVaxDue',        d.vaccinations_due_30days   ?? '—');
    } catch (e) { console.warn('Health stats failed:', e); }
  },

  // ── HEALTH RECORDS ─────────────────────────────────────────────────

  loadRecords: async function () {
    const container = document.getElementById('healthRecordsContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    try {
      const s = document.getElementById('hSearch')?.value || '';
      const r = await callAPI('/health/records' + (s ? '?search=' + encodeURIComponent(s) : ''), 'GET');
      const recs = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      if (!recs.length) {
        container.innerHTML = '<div class="alert alert-info text-center">No health profiles found. Start adding student records.</div>';
        return;
      }
      const rows = recs.map(rec => `
        <tr>
          <td class="fw-semibold">${this._esc(rec.first_name + ' ' + rec.last_name)}</td>
          <td>${this._esc(rec.admission_no || '—')}</td>
          <td>${this._esc(rec.class_name || '—')}</td>
          <td><span class="badge bg-danger bg-opacity-75 text-white">${this._esc(rec.blood_group || '?')}</span></td>
          <td>${this._esc(rec.allergies || '—')}</td>
          <td>${this._esc(rec.chronic_conditions || '—')}</td>
          <td>${this._esc(rec.emergency_contact_name || '—')}</td>
          <td>${this._esc(rec.emergency_contact_phone || '—')}</td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick="healthController.editRecord(${rec.student_id})">
              <i class="bi bi-pencil"></i>
            </button>
          </td>
        </tr>
      `).join('');
      container.innerHTML = `<div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light"><tr>
            <th>Student</th><th>Adm No</th><th>Class</th><th>Blood</th>
            <th>Allergies</th><th>Conditions</th><th>Emergency Contact</th><th>Phone</th><th></th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table></div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load records: ${this._esc(e.message)}</div>`;
    }
  },

  showRecordModal: function () {
    ['hrStudentId','hrBloodGroup','hrMedAidProvider','hrMedAidNo','hrAllergies','hrChronic',
     'hrDiet','hrDisability','hrDoctorName','hrDoctorPhone','hrEcName','hrEcPhone','hrNotes']
      .forEach(id => { const el = document.getElementById(id); if (el) el.value = el.tagName === 'SELECT' ? (id === 'hrBloodGroup' ? 'Unknown' : '') : ''; });
    document.getElementById('hrError').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('healthRecordModal')).show();
  },

  editRecord: async function (studentId) {
    try {
      const r = await callAPI('/health/records/' + studentId, 'GET');
      const rec = r?.data ?? r;
      if (!rec) { this.showRecordModal(); document.getElementById('hrStudentId').value = studentId; return; }
      document.getElementById('hrStudentId').value        = rec.student_id   || studentId;
      document.getElementById('hrBloodGroup').value       = rec.blood_group  || 'Unknown';
      document.getElementById('hrMedAidProvider').value   = rec.medical_aid_provider  || '';
      document.getElementById('hrMedAidNo').value         = rec.medical_aid_number    || '';
      document.getElementById('hrAllergies').value        = rec.allergies             || '';
      document.getElementById('hrChronic').value          = rec.chronic_conditions    || '';
      document.getElementById('hrDiet').value             = rec.special_diet          || '';
      document.getElementById('hrDisability').value       = rec.disability_notes      || '';
      document.getElementById('hrDoctorName').value       = rec.doctor_name           || '';
      document.getElementById('hrDoctorPhone').value      = rec.doctor_phone          || '';
      document.getElementById('hrEcName').value           = rec.emergency_contact_name  || '';
      document.getElementById('hrEcPhone').value          = rec.emergency_contact_phone || '';
      document.getElementById('hrNotes').value            = rec.notes                 || '';
      document.getElementById('hrError').classList.add('d-none');
      new bootstrap.Modal(document.getElementById('healthRecordModal')).show();
    } catch (e) { showNotification('Failed to load record', 'danger'); }
  },

  saveRecord: async function () {
    const errEl = document.getElementById('hrError');
    errEl.classList.add('d-none');
    const studentId = document.getElementById('hrStudentId').value;
    if (!studentId) { errEl.textContent = 'Please select a student'; errEl.classList.remove('d-none'); return; }
    const payload = {
      student_id:             parseInt(studentId),
      blood_group:            document.getElementById('hrBloodGroup').value,
      allergies:              document.getElementById('hrAllergies').value.trim(),
      chronic_conditions:     document.getElementById('hrChronic').value.trim(),
      disability_notes:       document.getElementById('hrDisability').value.trim(),
      special_diet:           document.getElementById('hrDiet').value.trim(),
      emergency_contact_name: document.getElementById('hrEcName').value.trim(),
      emergency_contact_phone:document.getElementById('hrEcPhone').value.trim(),
      medical_aid_provider:   document.getElementById('hrMedAidProvider').value.trim(),
      medical_aid_number:     document.getElementById('hrMedAidNo').value.trim(),
      doctor_name:            document.getElementById('hrDoctorName').value.trim(),
      doctor_phone:           document.getElementById('hrDoctorPhone').value.trim(),
      notes:                  document.getElementById('hrNotes').value.trim(),
    };
    try {
      await callAPI('/health/records', 'POST', payload);
      bootstrap.Modal.getInstance(document.getElementById('healthRecordModal')).hide();
      showNotification('Health profile saved', 'success');
      this.loadStats(); this.loadRecords();
    } catch (e) { errEl.textContent = e.message || 'Save failed'; errEl.classList.remove('d-none'); }
  },

  // ── VACCINATIONS ───────────────────────────────────────────────────

  loadVaccinations: async function () {
    const container = document.getElementById('vaccinationsContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    const dueOnly = document.getElementById('vaxDueOnly')?.checked ? '?due_only=1' : '';
    try {
      const r    = await callAPI('/health/vaccinations' + dueOnly, 'GET');
      const list = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      if (!list.length) {
        container.innerHTML = '<div class="alert alert-info text-center">No vaccination records found.</div>'; return;
      }
      const rows = list.map(v => {
        const due = v.next_due_date ? new Date(v.next_due_date) : null;
        const overdue = due && due < new Date();
        return `<tr class="${overdue ? 'table-warning' : ''}">
          <td class="fw-semibold">${this._esc(v.first_name + ' ' + v.last_name)}</td>
          <td>${this._esc(v.admission_no || '—')}</td>
          <td>${this._esc(v.class_name  || '—')}</td>
          <td>${this._esc(v.vaccine_name)}</td>
          <td>${v.dose_number}</td>
          <td>${this._esc(v.date_given || '—')}</td>
          <td>${v.next_due_date ? `<span class="badge bg-${overdue ? 'danger' : 'info'}">${v.next_due_date}</span>` : '—'}</td>
          <td>${this._esc(v.given_by || '—')}</td>
        </tr>`;
      }).join('');
      container.innerHTML = `<div class="table-responsive"><table class="table table-sm table-hover align-middle">
        <thead class="table-light"><tr>
          <th>Student</th><th>Adm No</th><th>Class</th>
          <th>Vaccine</th><th>Dose</th><th>Given</th><th>Next Due</th><th>Given By</th>
        </tr></thead>
        <tbody>${rows}</tbody>
      </table></div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed: ${this._esc(e.message)}</div>`;
    }
  },

  showVaxModal: function () {
    ['vaxStudentId','vaxName','vaxGivenBy','vaxBatch','vaxNotes','vaxNextDue'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    document.getElementById('vaxDose').value = '1';
    document.getElementById('vaxDateGiven').value = new Date().toISOString().split('T')[0];
    document.getElementById('vaxError').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('vaxModal')).show();
  },

  saveVax: async function () {
    const errEl = document.getElementById('vaxError');
    errEl.classList.add('d-none');
    const payload = {
      student_id:    document.getElementById('vaxStudentId').value,
      vaccine_name:  document.getElementById('vaxName').value.trim(),
      dose_number:   document.getElementById('vaxDose').value,
      date_given:    document.getElementById('vaxDateGiven').value,
      next_due_date: document.getElementById('vaxNextDue').value || null,
      given_by:      document.getElementById('vaxGivenBy').value.trim(),
      batch_number:  document.getElementById('vaxBatch').value.trim(),
      notes:         document.getElementById('vaxNotes').value.trim(),
    };
    if (!payload.student_id || !payload.vaccine_name) {
      errEl.textContent = 'Student and vaccine name are required'; errEl.classList.remove('d-none'); return;
    }
    try {
      await callAPI('/health/vaccinations', 'POST', payload);
      bootstrap.Modal.getInstance(document.getElementById('vaxModal')).hide();
      showNotification('Vaccination recorded', 'success');
      this.loadStats(); this.loadVaccinations();
    } catch (e) { errEl.textContent = e.message || 'Save failed'; errEl.classList.remove('d-none'); }
  },

  // ── STUDENT DROPDOWN ───────────────────────────────────────────────

  _loadStudentDropdowns: async function () {
    try {
      const r = await callAPI('/students/list', 'GET').catch(() => callAPI('/students', 'GET'));
      this._students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      ['hrStudentId','vaxStudentId'].forEach(id => {
        const sel = document.getElementById(id);
        if (!sel) return;
        sel.innerHTML = '<option value="">— Select student —</option>';
        this._students.forEach(s => {
          const o = document.createElement('option');
          o.value = s.id;
          o.textContent = `${s.first_name} ${s.last_name} (${s.admission_no || s.id})`;
          sel.appendChild(o);
        });
      });
    } catch (e) { console.warn('Could not load students:', e); }
  },

  // ── UTILS ──────────────────────────────────────────────────────────

  _set: function (id, val) { const el = document.getElementById(id); if (el) el.textContent = val; },
  _esc: function (s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  },
};
