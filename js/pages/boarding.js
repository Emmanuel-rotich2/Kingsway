/**
 * Boarding Controller
 * Manages dormitory facilities, student assignments, roll call, and exeat requests.
 * API base: /api/boarding/*
 */

const boardingController = {

  _dormitories: [],
  _students:    [],
  _exeats:      [],
  _charts:      {},
  _editId:      null,

  // ── ENTRY POINT ────────────────────────────────────────────────────

  init: function (role) {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    role = role || 'viewer';
    if      (role === 'admin')    this._initAdmin();
    else if (role === 'manager')  this._initManager();
    else if (role === 'operator') this._initOperator();
    else                          this._initViewer();
  },

  // ── ADMIN ──────────────────────────────────────────────────────────

  _initAdmin: async function () {
    await Promise.all([
      this._loadStats(),
      this._loadDormitories(),
      this._loadRecentActivity(),
    ]);
    this._bindAdminButtons();
  },

  _loadStats: async function () {
    try {
      const r = await callAPI('/boarding/stats', 'GET');
      const d = r?.data ?? r ?? {};
      this._set('totalCapacity',     d.total_capacity     ?? '0');
      this._set('occupiedBeds',      d.assigned_beds      ?? '0');
      this._set('availableBeds',     d.available_beds     ?? '0');
      this._set('occupancyRate',     (d.occupancy_rate ?? '0') + '%');
      this._set('healthIssues',      d.health_issues      ?? '0');
      this._set('onLeave',           d.on_leave           ?? '0');
      this._set('disciplinaryCases', d.disciplinary_cases ?? '0');
      this._set('rollCallComplete',  (d.roll_call_pct ?? '0') + '%');
      this._set('totalBoarders',     d.total_boarders     ?? '0');
      this._set('presentToday',      d.present_tonight    ?? '0');
      this._set('pendingLeaves',     d.pending_leaves     ?? '0');
      this._set('presentCount',      d.present_tonight    ?? '0');
      this._set('absentCount',       Math.max(0, (d.total_boarders ?? 0) - (d.present_tonight ?? 0)));
      this._set('pendingLeaveCount', d.pending_leaves     ?? '0');
      this._set('availableRooms',    d.available_beds     ?? '0');
    } catch (e) { console.warn('Boarding stats failed:', e); }
  },

  _loadDormitories: async function () {
    try {
      const r = await callAPI('/boarding/dormitories', 'GET');
      this._dormitories = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._renderDormitoriesTable();
      this._renderOccupancyChart();
    } catch (e) { console.warn('Dormitories load failed:', e); }
  },

  _renderDormitoriesTable: function () {
    const tbody = document.querySelector('#dormitoriesTable tbody');
    if (!tbody) return;
    if (!this._dormitories.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No dormitories found. Click <strong>Add Dormitory</strong> to get started.</td></tr>';
      return;
    }
    const search = (document.getElementById('searchDormitory')?.value || '').toLowerCase();
    const list   = search
      ? this._dormitories.filter(d => d.name.toLowerCase().includes(search))
      : this._dormitories;

    tbody.innerHTML = list.map(d => `
      <tr>
        <td><strong>${this._esc(d.name)}</strong>${d.location ? '<br><small class="text-muted">' + this._esc(d.location) + '</small>' : ''}</td>
        <td><span class="badge bg-${d.gender === 'male' ? 'primary' : d.gender === 'female' ? 'danger' : 'secondary'}">${this._esc(d.gender)}</span></td>
        <td>${d.capacity}</td>
        <td><span class="fw-semibold">${d.occupied ?? 0}</span></td>
        <td><span class="text-${(d.available ?? 0) > 0 ? 'success' : 'danger'}">${d.available ?? 0}</span></td>
        <td>${this._esc(d.patron_name || '—')}</td>
        <td><span class="badge bg-${d.status === 'active' ? 'success' : 'secondary'}">${d.status}</span></td>
        <td>
          <button class="btn btn-sm btn-outline-primary me-1" onclick="boardingController._editDormitory(${d.id})">
            <i class="bi bi-pencil"></i>
          </button>
          <button class="btn btn-sm btn-outline-danger" onclick="boardingController._deleteDormitory(${d.id},'${this._esc(d.name).replace(/'/g,"\\'")}')">
            <i class="bi bi-trash"></i>
          </button>
        </td>
      </tr>`).join('');
  },

  _renderOccupancyChart: function () {
    const canvas = document.getElementById('occupancyChart');
    if (!canvas || !this._dormitories.length) return;
    if (this._charts.occupancy) this._charts.occupancy.destroy();

    this._charts.occupancy = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: this._dormitories.map(d => d.name),
        datasets: [
          { label: 'Occupied',  data: this._dormitories.map(d => d.occupied  ?? 0), backgroundColor: 'rgba(40,167,69,0.8)' },
          { label: 'Available', data: this._dormitories.map(d => d.available ?? 0), backgroundColor: 'rgba(255,193,7,0.6)' },
        ],
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
      },
    });
  },

  _loadRecentActivity: async function () {
    const el = document.getElementById('recentActivity');
    if (!el) return;
    try {
      const r = await callAPI('/boarding/activity', 'GET');
      const items = Array.isArray(r?.data) ? r.data : [];
      if (!items.length) {
        el.innerHTML = '<div class="text-center text-muted py-3">No recent activity.</div>';
        return;
      }
      el.innerHTML = items.map(item => {
        const icon  = item.type === 'roll_call' ? 'bi-clipboard-check' : 'bi-calendar-x';
        const color = item.detail === 'approved' ? 'success' : item.detail === 'absent' ? 'danger' : 'secondary';
        return `
          <div class="list-group-item d-flex align-items-center gap-3 py-2">
            <span class="badge bg-${color} rounded-pill"><i class="bi ${icon}"></i></span>
            <div class="flex-grow-1">
              <span class="fw-semibold">${this._esc(item.name)}</span>
              <span class="text-muted ms-2 small">${item.detail}</span>
            </div>
            <small class="text-muted">${this._relTime(item.ts)}</small>
          </div>`;
      }).join('');
    } catch (e) {
      el.innerHTML = '<div class="text-muted small p-3">Could not load activity.</div>';
    }
  },

  _bindAdminButtons: function () {
    const addBtn    = document.getElementById('addDormitoryBtn');
    const saveBtn   = document.getElementById('saveDormitoryBtn');
    const leaveBtn  = document.getElementById('leaveRequestsBtn');
    const exportBtn = document.getElementById('exportReportBtn');
    const search    = document.getElementById('searchDormitory');

    if (addBtn)    addBtn.addEventListener('click',  () => this._openDormModal());
    if (saveBtn)   saveBtn.addEventListener('click', () => this._saveDormitory());
    if (leaveBtn)  leaveBtn.addEventListener('click',() => this._loadLeaveRequests());
    if (exportBtn) exportBtn.addEventListener('click',() => this._exportReport());
    if (search)    search.addEventListener('input',  () => this._renderDormitoriesTable());

    this._loadStaffDropdown();
  },

  _openDormModal: function (dorm) {
    this._editId = dorm ? dorm.id : null;
    const form = document.getElementById('dormitoryForm');
    if (!form) return;
    form.reset();
    if (dorm) {
      form.querySelector('[name=name]').value        = dorm.name        || '';
      form.querySelector('[name=gender]').value      = dorm.gender      || 'male';
      form.querySelector('[name=capacity]').value    = dorm.capacity    || '';
      form.querySelector('[name=patron_id]').value   = dorm.patron_id   || '';
      form.querySelector('[name=location]').value    = dorm.location    || '';
      form.querySelector('[name=description]').value = dorm.description || '';
    }
    const modal = document.getElementById('addDormitoryModal');
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  },

  _editDormitory: function (id) {
    const dorm = this._dormitories.find(d => d.id == id);
    if (dorm) this._openDormModal(dorm);
  },

  _saveDormitory: async function () {
    const form = document.getElementById('dormitoryForm');
    if (!form || !form.checkValidity()) { form?.reportValidity(); return; }
    const payload = {
      name:        form.querySelector('[name=name]').value.trim(),
      gender:      form.querySelector('[name=gender]').value,
      capacity:    parseInt(form.querySelector('[name=capacity]').value) || 0,
      patron_id:   form.querySelector('[name=patron_id]').value || null,
      location:    form.querySelector('[name=location]').value.trim(),
      description: form.querySelector('[name=description]').value.trim(),
    };
    try {
      if (this._editId) {
        await callAPI('/boarding/dormitories/' + this._editId, 'PUT', payload);
        showNotification('Dormitory updated', 'success');
      } else {
        await callAPI('/boarding/dormitories', 'POST', payload);
        showNotification('Dormitory added', 'success');
      }
      const modal = document.getElementById('addDormitoryModal');
      if (modal) bootstrap.Modal.getInstance(modal)?.hide();
      await this._loadDormitories();
      await this._loadStats();
    } catch (e) {
      showNotification('Failed to save: ' + (e.message || e), 'error');
    }
  },

  _deleteDormitory: async function (id, name) {
    if (!confirm('Delete dormitory "' + name + '"? This cannot be undone.')) return;
    try {
      await callAPI('/boarding/dormitories/' + id, 'DELETE');
      showNotification('Dormitory deleted', 'success');
      await this._loadDormitories();
      await this._loadStats();
    } catch (e) {
      showNotification(e?.message || 'Cannot delete dormitory', 'error');
    }
  },

  _loadLeaveRequests: async function () {
    const modal = document.getElementById('leaveRequestsModal');
    const tbody = document.querySelector('#leaveRequestsTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();

    try {
      const r = await callAPI('/boarding/exeats?status=pending', 'GET');
      this._exeats = Array.isArray(r?.data) ? r.data : [];
      tbody.innerHTML = this._exeats.length
        ? this._exeats.map(e => `
          <tr>
            <td>${this._esc(e.student_name)}<br><small class="text-muted">${e.admission_no}</small></td>
            <td>${this._esc(e.dormitory_name || '—')}</td>
            <td>${this._esc(e.permission_type_name || 'Leave')}</td>
            <td>${e.start_date || e.departure_date || '—'}</td>
            <td>${e.end_date   || e.return_date    || '—'}</td>
            <td>${this._esc(e.reason || '—')}</td>
            <td>
              <button class="btn btn-sm btn-success me-1" onclick="boardingController._actLeave(${e.id},'approve')"><i class="bi bi-check"></i> Approve</button>
              <button class="btn btn-sm btn-danger"        onclick="boardingController._actLeave(${e.id},'reject')"><i class="bi bi-x"></i> Reject</button>
            </td>
          </tr>`).join('')
        : '<tr><td colspan="7" class="text-center text-muted py-3">No pending leave requests.</td></tr>';
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center">Failed to load requests.</td></tr>';
    }
  },

  _actLeave: async function (id, action) {
    try {
      await callAPI('/boarding/exeats/' + id, 'PUT', { action });
      showNotification('Leave request ' + action + 'd', 'success');
      await this._loadLeaveRequests();
      await this._loadStats();
    } catch (e) {
      showNotification('Action failed: ' + (e.message || e), 'error');
    }
  },

  _exportReport: function () {
    const rows = [['Dormitory','Gender','Capacity','Occupied','Available','Patron','Status']];
    this._dormitories.forEach(d => rows.push([
      d.name, d.gender, d.capacity, d.occupied ?? 0, d.available ?? 0, d.patron_name || '', d.status,
    ]));
    const csv  = rows.map(r => r.map(v => '"' + String(v).replace(/"/g,'""') + '"').join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a    = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'boarding_report_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
  },

  _loadStaffDropdown: async function () {
    const sel = document.querySelector('#dormitoryForm select[name=patron_id]');
    if (!sel) return;
    try {
      const r = await callAPI('/staff?status=active&limit=200', 'GET');
      const staff = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      sel.innerHTML = '<option value="">Select Staff</option>' +
        staff.map(s => `<option value="${s.id}">${this._esc(s.first_name + ' ' + s.last_name)}</option>`).join('');
    } catch (e) { /* staff dropdown is optional */ }
  },

  // ── MANAGER ────────────────────────────────────────────────────────

  _initManager: async function () {
    await Promise.all([this._loadStats(), this._loadStudents(), this._loadExeats()]);
    this._bindManagerButtons();
  },

  _loadStudents: async function () {
    const tbody = document.querySelector('#dormitoryStudentsTable tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await callAPI('/boarding/students', 'GET');
      this._students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._renderStudentsTable();
      const dormName = document.getElementById('myDormitoryName');
      if (dormName && this._students.length) dormName.textContent = this._students[0].dormitory_name || 'All';
    } catch (e) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-danger text-center">Failed to load students.</td></tr>';
    }
  },

  _renderStudentsTable: function () {
    const tbody = document.querySelector('#dormitoryStudentsTable tbody');
    if (!tbody) return;
    const q = (document.getElementById('searchStudent')?.value || '').toLowerCase();
    const list = q
      ? this._students.filter(s => (s.student_name||'').toLowerCase().includes(q) || (s.admission_no||'').toLowerCase().includes(q))
      : this._students;

    tbody.innerHTML = list.length
      ? list.map(s => `
        <tr>
          <td>${this._esc(s.student_name)}</td>
          <td>${this._esc(s.admission_no || '—')}</td>
          <td>${this._esc(s.class_name || '—')}</td>
          <td>${this._esc(s.dormitory_name)}</td>
          <td>${this._esc(s.bed_number || '—')}</td>
          <td><span class="badge bg-${s.tonight_status === 'present' ? 'success' : s.tonight_status === 'absent' ? 'danger' : 'secondary'}">${s.tonight_status || 'not marked'}</span></td>
        </tr>`).join('')
      : '<tr><td colspan="6" class="text-center text-muted py-3">No students found.</td></tr>';
  },

  _loadExeats: async function () {
    const tbody = document.querySelector('#leaveRequestsTable tbody');
    if (!tbody) return;
    try {
      const r = await callAPI('/boarding/exeats?status=pending', 'GET');
      const items = Array.isArray(r?.data) ? r.data : [];
      tbody.innerHTML = items.length
        ? items.map(e => `
          <tr>
            <td>${this._esc(e.student_name)}</td>
            <td>${e.start_date || '—'}</td>
            <td>${e.end_date   || '—'}</td>
            <td>${this._esc(e.reason || '—')}</td>
            <td><span class="badge bg-warning">${e.status}</span></td>
          </tr>`).join('')
        : '<tr><td colspan="5" class="text-center text-muted py-3">No pending requests.</td></tr>';
    } catch (e) { console.warn('Exeats load failed:', e); }
  },

  _bindManagerButtons: function () {
    const refresh = document.getElementById('refreshBtn');
    const assign  = document.getElementById('assignRoomBtn');
    const search  = document.getElementById('searchStudent');
    const confirm = document.getElementById('confirmAssignBtn');

    if (refresh) refresh.addEventListener('click', () => this._initManager());
    if (assign)  assign.addEventListener('click', () => {
      const m = document.getElementById('assignRoomModal');
      if (m) bootstrap.Modal.getOrCreateInstance(m).show();
    });
    if (search)  search.addEventListener('input', () => this._renderStudentsTable());
    if (confirm) confirm.addEventListener('click', () => this._saveAssignment());
  },

  _saveAssignment: async function () {
    const form = document.getElementById('assignRoomForm');
    if (!form || !form.checkValidity()) { form?.reportValidity(); return; }
    try {
      await callAPI('/boarding/students', 'POST', Object.fromEntries(new FormData(form)));
      showNotification('Room assigned', 'success');
      const m = document.getElementById('assignRoomModal');
      if (m) bootstrap.Modal.getInstance(m)?.hide();
      await this._loadStudents();
    } catch (e) {
      showNotification('Failed: ' + (e.message || e), 'error');
    }
  },

  // ── OPERATOR ───────────────────────────────────────────────────────

  _initOperator: async function () {
    await Promise.all([this._loadStats(), this._loadDormitoriesGrid()]);
    const refresh  = document.getElementById('refreshBtn');
    const search   = document.getElementById('searchBoarder');
    const searchBtn = document.getElementById('searchBtn');
    if (refresh)   refresh.addEventListener('click', () => this._initOperator());
    if (search)    search.addEventListener('keydown', e => { if (e.key === 'Enter') this._loadStudents(); });
    if (searchBtn) searchBtn.addEventListener('click', () => this._loadStudents());
  },

  _loadDormitoriesGrid: async function () {
    const grid = document.getElementById('dormitoriesList');
    if (!grid) return;
    try {
      const r = await callAPI('/boarding/dormitories', 'GET');
      const dorms = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      grid.innerHTML = dorms.length
        ? dorms.map(d => `
          <div class="col-md-4 mb-3">
            <div class="card h-100 border-${(d.available ?? 0) > 0 ? 'success' : 'danger'}">
              <div class="card-body">
                <h6 class="card-title">${this._esc(d.name)}</h6>
                <p class="text-muted mb-1 small">${this._esc(d.gender)} · ${this._esc(d.location || '')}</p>
                <div class="progress mb-2" style="height:8px">
                  <div class="progress-bar bg-${d.capacity > 0 && d.occupied/d.capacity > 0.9 ? 'danger' : 'success'}"
                       style="width:${d.capacity > 0 ? Math.round((d.occupied/d.capacity)*100) : 0}%"></div>
                </div>
                <small>${d.occupied ?? 0} / ${d.capacity} · <span class="${(d.available??0)>0?'text-success':'text-danger'}">${d.available??0} available</span></small>
              </div>
            </div>
          </div>`).join('')
        : '<div class="col-12 text-center text-muted py-5">No dormitories configured.</div>';
    } catch (e) {
      grid.innerHTML = '<div class="col-12 text-danger">Failed to load dormitories.</div>';
    }
  },

  // ── VIEWER (Parent) ────────────────────────────────────────────────

  _initViewer: async function () {
    await this._loadChildSelector();
    const childSel   = document.getElementById('childSelector');
    const leaveBtn   = document.getElementById('requestLeaveBtn');
    const submitBtn  = document.getElementById('submitLeaveRequestBtn');

    if (childSel) {
      childSel.addEventListener('change', () => this._loadChildInfo(childSel.value));
      if (childSel.value) this._loadChildInfo(childSel.value);
    }
    if (leaveBtn) leaveBtn.addEventListener('click', () => {
      const m = document.getElementById('requestLeaveModal');
      if (m) bootstrap.Modal.getOrCreateInstance(m).show();
    });
    if (submitBtn) submitBtn.addEventListener('click', () => this._submitLeaveRequest());
  },

  _loadChildSelector: async function () {
    const sel = document.getElementById('childSelector');
    if (!sel) return;
    try {
      const r = await callAPI('/parent-portal/children', 'GET');
      const children = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      sel.innerHTML = children.length
        ? children.map(c => `<option value="${c.id}">${this._esc(c.first_name + ' ' + c.last_name)} (${c.admission_no || ''})</option>`).join('')
        : '<option value="">No children found</option>';
      if (children.length) this._loadChildInfo(children[0].id);
    } catch (e) {
      sel.innerHTML = '<option value="">Could not load children</option>';
    }
  },

  _loadChildInfo: async function (studentId) {
    if (!studentId) return;
    try {
      const r = await callAPI('/boarding/students?student_id=' + studentId, 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const s = items[0] || {};
      this._set('studentName',     s.student_name   || '—');
      this._set('studentClass',    s.class_name     || '—');
      this._set('dormitoryName',   s.dormitory_name || 'Not assigned');
      this._set('bedNumber',       s.bed_number     || '—');
      this._set('patronName',      s.patron_name    || '—');
      this._set('patronContact',   s.patron_contact || '—');
      const st = s.tonight_status || 'not marked';
      this._set('attendanceStatus', st.charAt(0).toUpperCase() + st.slice(1));
      this._loadLeaveHistory(studentId);
    } catch (e) { console.warn('Child boarding info failed:', e); }
  },

  _loadLeaveHistory: async function (studentId) {
    const tbody = document.querySelector('#leaveHistoryTable tbody');
    if (!tbody) return;
    try {
      const r = await callAPI('/boarding/exeats?student_id=' + studentId, 'GET');
      const items = Array.isArray(r?.data) ? r.data : [];
      tbody.innerHTML = items.length
        ? items.map(e => `
          <tr>
            <td>${this._esc(e.permission_type_name || 'Leave')}</td>
            <td>${e.start_date || '—'}</td>
            <td>${e.end_date   || '—'}</td>
            <td>${this._esc(e.reason || '—')}</td>
            <td><span class="badge bg-${e.status==='approved'?'success':e.status==='rejected'?'danger':'warning'}">${e.status}</span></td>
          </tr>`).join('')
        : '<tr><td colspan="5" class="text-muted text-center py-3">No leave history.</td></tr>';
    } catch (e) { console.warn('Leave history failed:', e); }
  },

  _submitLeaveRequest: async function () {
    const form  = document.getElementById('leaveRequestForm');
    const child = document.getElementById('childSelector')?.value;
    if (!form || !form.checkValidity()) { form?.reportValidity(); return; }
    if (!child) { showNotification('Please select a child first', 'warning'); return; }
    const data = Object.fromEntries(new FormData(form));
    data.student_id = child;
    try {
      await callAPI('/boarding/exeats', 'POST', data);
      showNotification('Leave request submitted', 'success');
      const m = document.getElementById('requestLeaveModal');
      if (m) bootstrap.Modal.getInstance(m)?.hide();
      await this._loadLeaveHistory(child);
    } catch (e) {
      showNotification('Failed to submit: ' + (e.message || e), 'error');
    }
  },

  // ── HELPERS ────────────────────────────────────────────────────────

  _set: function (id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  },

  _esc: function (str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  },

  _relTime: function (ts) {
    if (!ts) return '';
    const diff = Date.now() - new Date(ts).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1)  return 'just now';
    if (mins < 60) return mins + 'm ago';
    const hrs = Math.floor(mins / 60);
    if (hrs < 24)  return hrs + 'h ago';
    return Math.floor(hrs / 24) + 'd ago';
  },
};
