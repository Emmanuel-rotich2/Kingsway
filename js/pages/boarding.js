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

  init: async function (role) {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    role = role || 'viewer';
    this._role = role;
    window.APIRealtime?.register?.('boarding', this, ['boarding']);
    if      (role === 'admin')    this._initAdmin();
    else if (role === 'manager')  this._initManager();
    else if (role === 'operator') this._initOperator();
    else                          this._initViewer();
  },

  refreshData: function () {
    if (this._role === 'admin') return this._initAdmin();
    if (this._role === 'manager') return this._initManager();
    if (this._role === 'operator') return this._initOperator();
    return this._initViewer();
  },

  // ── ADMIN ──────────────────────────────────────────────────────────

  _initAdmin: async function () {
    try {
      const response = await window.API.nodeViews.resolve('boarding', 'admin-overview');
      const sources = response?.data?.sources || {};
      await Promise.all([
        this._loadStats(sources.stats),
        this._loadDormitories(sources.dormitories),
        this._loadRecentActivity(sources.activity),
      ]);
    } catch (error) {
      // Preserve the existing PHP API path if the private Node composition is
      // temporarily unavailable; PHP remains the authorization authority.
      console.warn('Boarding overview composition failed; using PHP endpoints:', error);
      await Promise.all([
        this._loadStats(),
        this._loadDormitories(),
        this._loadRecentActivity(),
      ]);
    }
    this._bindAdminButtons();
  },

  _loadStats: async function (composedData) {
    try {
      let d = composedData;
      if (d === undefined) {
        const r = await callAPI('/boarding/stats', 'GET');
        d = r?.data ?? r ?? {};
      }
      this._set('totalCapacity',     d.total_capacity     ?? '0');
      this._set('occupiedBeds',      d.assigned_beds      ?? '0');
      this._set('availableBeds',     d.available_beds     ?? '0');
      this._set('occupancyRate',     (d.occupancy_rate ?? '0') + '%');
      this._set('healthIssues',      d.urgent_notes       ?? '0');
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

  _loadDormitories: async function (composedData) {
    try {
      let rows = composedData;
      if (rows === undefined) {
        const r = await callAPI('/boarding/dormitories', 'GET');
        rows = Array.isArray(r?.data) ? r.data : r;
      }
      this._dormitories = Array.isArray(rows) ? rows : [];
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
      ? this._dormitories.filter(d => String(d.name || '').toLowerCase().includes(search))
      : this._dormitories;

    tbody.innerHTML = list.map(d => `
      <tr>
        <td><strong>${this._esc(d.name)}</strong>${d.location ? '<br><small class="text-muted">' + this._esc(d.location) + '</small>' : ''}</td>
        <td><span class="badge bg-${d.gender === 'male' ? 'primary' : d.gender === 'female' ? 'danger' : 'secondary'}">${this._esc(d.gender)}</span></td>
        <td>${this._esc(d.capacity)}</td>
        <td><span class="fw-semibold">${this._esc(d.occupied ?? 0)}</span></td>
        <td><span class="text-${(Number(d.available) || 0) > 0 ? 'success' : 'danger'}">${this._esc(d.available ?? 0)}</span></td>
        <td>${this._esc(d.patron_name || '—')}</td>
        <td><span class="badge bg-${d.status === 'active' ? 'success' : 'secondary'}">${this._esc(d.status)}</span></td>
        <td>
          <button type="button" class="btn btn-sm btn-outline-primary me-1" data-dormitory-action="edit" data-id="${Number(d.id) || 0}">
            <i class="bi bi-pencil"></i>
          </button>
          <button type="button" class="btn btn-sm btn-outline-danger" data-dormitory-action="delete" data-id="${Number(d.id) || 0}">
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

  _loadRecentActivity: async function (composedData) {
    const el = document.getElementById('recentActivity');
    if (!el) return;
    try {
      let items = composedData;
      if (items === undefined) {
        const r = await callAPI('/boarding/activity', 'GET');
        items = Array.isArray(r?.data) ? r.data : [];
      }
      if (!Array.isArray(items)) items = [];
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
              <span class="text-muted ms-2 small">${this._esc(item.detail)}</span>
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

    const dormitoriesBody = document.querySelector('#dormitoriesTable tbody');
    if (dormitoriesBody && !dormitoriesBody.dataset.actionsBound) {
      dormitoriesBody.dataset.actionsBound = 'true';
      dormitoriesBody.addEventListener('click', (event) => {
      const button = event.target.closest('[data-dormitory-action]');
      if (!button || !dormitoriesBody.contains(button)) return;
      const id = Number(button.dataset.id);
      if (!Number.isSafeInteger(id) || id < 1) return;
      if (button.dataset.dormitoryAction === 'edit') this._editDormitory(id);
      if (button.dataset.dormitoryAction === 'delete') this._deleteDormitory(id);
      });
    }

    const leaveBody = document.querySelector('#leaveRequestsTable tbody');
    if (leaveBody && !leaveBody.dataset.actionsBound) {
      leaveBody.dataset.actionsBound = 'true';
      leaveBody.addEventListener('click', (event) => {
      const button = event.target.closest('[data-exeat-action]');
      if (!button || !leaveBody.contains(button)) return;
      const id = Number(button.dataset.id);
      const action = button.dataset.exeatAction;
      if (Number.isSafeInteger(id) && id > 0 && ['approve', 'reject'].includes(action)) {
        this._actLeave(id, action);
      }
      });
    }

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

  _deleteDormitory: async function (id) {
    id = Number(id);
    const dormitory = this._dormitories.find(item => Number(item.id) === id);
    if (!Number.isSafeInteger(id) || id < 1 || !dormitory) return;
    const name = String(dormitory.name || 'this dormitory');
    if (!(await window.confirmAction('Confirm Deletion', 'Delete dormitory "' + name + '"? This cannot be undone.', { confirmText: 'Delete', danger: true }))) return;
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
            <td>${this._esc(e.student_name)}<br><small class="text-muted">${this._esc(e.admission_no)}</small></td>
            <td>${this._esc(e.dormitory_name || '—')}</td>
            <td>${this._esc(e.permission_type_name || 'Leave')}</td>
            <td>${this._esc(e.start_date || e.departure_date || '—')}</td>
            <td>${this._esc(e.end_date   || e.return_date    || '—')}</td>
            <td>${this._esc(e.reason || '—')}</td>
            <td>
              <button type="button" class="btn btn-sm btn-success me-1" data-exeat-action="approve" data-id="${Number(e.id) || 0}"><i class="bi bi-check"></i> Approve</button>
              <button type="button" class="btn btn-sm btn-danger" data-exeat-action="reject" data-id="${Number(e.id) || 0}"><i class="bi bi-x"></i> Reject</button>
            </td>
          </tr>`).join('')
        : '<tr><td colspan="7" class="text-center text-muted py-3">No pending leave requests.</td></tr>';
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center">Failed to load requests.</td></tr>';
    }
  },

  _actLeave: async function (id, action) {
    id = Number(id);
    if (!Number.isSafeInteger(id) || id < 1 || !['approve', 'reject'].includes(action)) return;
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
    KingswayFileLifecycle.exportText(csv, 'boarding_report_' + new Date().toISOString().slice(0,10) + '.csv', 'text/csv');
  },

  _loadStaffDropdown: async function () {
    const sel = document.querySelector('#dormitoryForm select[name=patron_id]');
    if (!sel) return;
    try {
      const r = await callAPI('/staff?status=active&limit=200', 'GET');
      const staff = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      sel.innerHTML = '<option value="">Select Staff</option>' +
        staff.map(s => `<option value="${Number(s.id) || 0}">${this._esc(s.first_name + ' ' + s.last_name)}</option>`).join('');
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
      ? this._students.filter(s => String(s.student_name || '').toLowerCase().includes(q) || String(s.admission_no || '').toLowerCase().includes(q))
      : this._students;

    tbody.innerHTML = list.length
      ? list.map(s => `
        <tr>
          <td>${this._esc(s.student_name)}</td>
          <td>${this._esc(s.admission_no || '—')}</td>
          <td>${this._esc(s.class_name || '—')}</td>
          <td>${this._esc(s.dormitory_name)}</td>
          <td>${this._esc(s.bed_number || '—')}</td>
          <td><span class="badge bg-${s.tonight_status === 'present' ? 'success' : s.tonight_status === 'absent' ? 'danger' : 'secondary'}">${this._esc(s.tonight_status || 'not marked')}</span></td>
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
            <td>${this._esc(e.start_date || '—')}</td>
            <td>${this._esc(e.end_date   || '—')}</td>
            <td>${this._esc(e.reason || '—')}</td>
            <td><span class="badge bg-warning">${this._esc(e.status)}</span></td>
          </tr>`).join('')
        : '<tr><td colspan="5" class="text-center text-muted py-3">No pending requests.</td></tr>';
    } catch (e) { console.warn('Exeats load failed:', e); }
  },

  _bindManagerButtons: function () {
    const refresh = document.getElementById('refreshBtn');
    const assign  = document.getElementById('assignRoomBtn');
    const search  = document.getElementById('searchStudent');
    const confirm = document.getElementById('confirmAssignBtn');
    const healthCard = document.getElementById('healthCard');
    const reportHealthBtn = document.getElementById('reportHealthBtn');

    if (refresh) refresh.addEventListener('click', () => this._initManager());
    if (assign)  assign.addEventListener('click', () => this._openAssignModal());
    if (search)  search.addEventListener('input', () => this._renderStudentsTable());
    if (confirm) confirm.addEventListener('click', () => this._saveAssignment());
    if (healthCard) healthCard.addEventListener('click', () => this._loadHealthIssueModal());
    if (reportHealthBtn) reportHealthBtn.addEventListener('click', () => this._saveHealthIssue());
  },

  _openAssignModal: function () {
    const form = document.getElementById('assignRoomForm');
    if (!form) return;
    form.reset();

    const studentSel = form.querySelector('[name=student_id]');
    if (studentSel) {
      studentSel.innerHTML = '<option value="">Select Student</option>' +
        this._students.map(s => '<option value="' + s.id + '">' + this._esc(s.student_name) + ' (' + this._esc(s.admission_no || '') + ')</option>').join('');
    }

    const bedSel = form.querySelector('[name=bed_number]');
    if (bedSel) {
      bedSel.innerHTML = '<option value="">Select Available Bed</option>';
      for (let i = 1; i <= 12; i++) {
        bedSel.insertAdjacentHTML('beforeend', '<option value="B' + i + '">Bed B' + i + '</option>');
      }
    }

    const modal = document.getElementById('assignRoomModal');
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  },

  _saveAssignment: async function () {
    const form = document.getElementById('assignRoomForm');
    if (!form || !form.checkValidity()) { form?.reportValidity(); return; }
    const data = Object.fromEntries(new FormData(form));
    const student = this._students.find(s => s.id == data.student_id);
    if (!student || !student.dormitory_id) {
      showNotification('Selected student has no dormitory assignment', 'warning');
      return;
    }
    data.dormitory_id = student.dormitory_id;
    try {
      await callAPI('/students/boarding-assign-dorm', 'POST', data);
      showNotification('Room assigned', 'success');
      const m = document.getElementById('assignRoomModal');
      if (m) bootstrap.Modal.getInstance(m)?.hide();
      await this._loadStudents();
    } catch (e) {
      showNotification('Failed: ' + (e.message || e), 'error');
    }
  },

  _loadHealthIssueModal: function (studentId) {
    const form = document.getElementById('healthIssueForm');
    if (!form) return;
    form.reset();

    const sel = form.querySelector('[name=student_id]');
    if (sel) {
      sel.innerHTML = '<option value="">Select Student</option>' +
        this._students.map(s => '<option value="' + s.id + '">' + this._esc(s.student_name) + ' (' + this._esc(s.admission_no || '') + ')</option>').join('');
      if (studentId) sel.value = studentId;
    }

    const modal = document.getElementById('healthIssueModal');
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  },

  _saveHealthIssue: async function () {
    const form = document.getElementById('healthIssueForm');
    if (!form || !form.checkValidity()) { form?.reportValidity(); return; }

    const issueType = form.querySelector('[name=issue_type]').value;
    const severity  = form.querySelector('[name=severity]').value;
    const data = {
      student_id:   form.querySelector('[name=student_id]').value,
      complaint:    form.querySelector('[name=description]').value.trim(),
      observation:  '[' + issueType + '] Severity: ' + severity,
      action_taken: form.querySelector('[name=action_taken]').value.trim() || null,
    };

    try {
      await callAPI('/health/sick-bay', 'POST', data);
      showNotification('Health issue reported', 'success');
      const modal = document.getElementById('healthIssueModal');
      if (modal) bootstrap.Modal.getInstance(modal)?.hide();
      await this._loadStats();
    } catch (e) {
      showNotification('Failed to report: ' + (e.message || e), 'error');
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
            <div class="card h-100 border-${(Number(d.available) || 0) > 0 ? 'success' : 'danger'}">
              <div class="card-body">
                <h6 class="card-title">${this._esc(d.name)}</h6>
                <p class="text-muted mb-1 small">${this._esc(d.gender)} · ${this._esc(d.location || '')}</p>
                <div class="progress mb-2" style="height:8px">
                  <div class="progress-bar bg-${Number(d.capacity) > 0 && Number(d.occupied)/Number(d.capacity) > 0.9 ? 'danger' : 'success'}"
                       style="width:${Number(d.capacity) > 0 ? Math.max(0, Math.min(100, Math.round((Number(d.occupied) || 0)/Number(d.capacity)*100))) : 0}%"></div>
                </div>
                <small>${this._esc(d.occupied ?? 0)} / ${this._esc(d.capacity)} · <span class="${(Number(d.available) || 0)>0?'text-success':'text-danger'}">${this._esc(d.available ?? 0)} available</span></small>
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
      const r = await callAPI('/parent-portal/dashboard', 'GET');
      const d = r?.data ?? r ?? {};
      const children = Array.isArray(d.children) ? d.children : [];
      sel.innerHTML = children.length
        ? children.map(c => `<option value="${Number(c.id) || 0}">${this._esc(c.first_name + ' ' + c.last_name)} (${this._esc(c.admission_no || '')})</option>`).join('')
        : '<option value="">No children found</option>';
      if (children.length) this._loadChildInfo(children[0].id);
    } catch (e) {
      sel.innerHTML = '<option value="">Could not load children</option>';
    }
  },

  _loadChildInfo: async function (studentId) {
    if (!studentId) return;
    try {
      if (!this._students.length) {
        const r = await callAPI('/boarding/students', 'GET');
        this._students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      }
      const s = this._students.find(x => x.id == studentId) || {};
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
      const r = await callAPI('/boarding/exeats', 'GET');
      const items = Array.isArray(r?.data) ? r.data : [];
      const mine = items.filter(e => e.student_id == studentId);
      tbody.innerHTML = mine.length
        ? mine.map(e => `
          <tr>
            <td>${this._esc(e.permission_type_name || 'Leave')}</td>
            <td>${this._esc(e.start_date || '—')}</td>
            <td>${this._esc(e.end_date   || '—')}</td>
            <td>${this._esc(e.reason || '—')}</td>
            <td><span class="badge bg-${e.status==='approved'?'success':e.status==='rejected'?'danger':'warning'}">${this._esc(e.status)}</span></td>
          </tr>`).join('')
        : '<tr><td colspan="5" class="text-muted text-center py-3">No leave history.</td></tr>';
    } catch (e) { console.warn('Leave history failed:', e); }
  },

  _submitLeaveRequest: async function () {
    const form  = document.getElementById('leaveRequestForm');
    const child = document.getElementById('childSelector')?.value;
    if (!form || !form.checkValidity()) { form?.reportValidity(); return; }
    if (!child) { showNotification('Please select a child first', 'warning'); return; }

    const typeMap = { weekend: 1, holiday: 1, medical: 4, family: 3, other: 1 };
    const f = Object.fromEntries(new FormData(form));
    const data = {
      student_id:         child,
      permission_type_id: typeMap[f.leave_type] || 1,
      departure_date:     f.from_date,
      return_date:        f.to_date,
      reason:             f.reason,
      pickup_person:      f.pickup_person,
      pickup_contact:     f.pickup_contact,
    };
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

window.boardingController = boardingController;
