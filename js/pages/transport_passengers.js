/**
 * Driver's live transport manifest. Data and writes are scoped by the PHP
 * transport controller to the authenticated driver and assigned routes.
 */
const transportPassengersController = {
  state: { manifest: null, students: [], routes: [], selectedStudent: null },

  el(id) { return document.getElementById(id); },
  esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[ch]));
  },
  list(payload, key) {
    if (Array.isArray(payload?.data?.[key])) return payload.data[key];
    if (Array.isArray(payload?.[key])) return payload[key];
    if (Array.isArray(payload?.data)) return payload.data;
    return [];
  },
  setText(id, value) { const node = this.el(id); if (node) node.textContent = value ?? ''; },
  show(id) { const node = this.el(id); if (node) node.classList.remove('d-none'); },
  hide(id) { const node = this.el(id); if (node) node.classList.add('d-none'); },
  value(id) { return this.el(id)?.value || ''; },
  notify(message, type = 'info') {
    if (window.showNotification) window.showNotification(message, type);
    else if (window.showToast) window.showToast(message, type);
  },
  currentDate() { return this.value('dateFilter') || new Date().toISOString().slice(0, 10); },
  currentTrip() { return this.value('tripSessionFilter') || 'morning_pickup'; },

  async load() {
    this.show('passengersLoading');
    this.hide('passengersError');
    this.hide('passengersForbidden');
    this.hide('passengersEmpty');
    try {
      const params = new URLSearchParams({ date: this.currentDate(), trip_session: this.currentTrip() });
      const response = await window.API.callAPI(`/transport/driver-manifest?${params}`, 'GET');
      const data = response?.data || response || {};
      this.state.manifest = data;
      this.state.students = Array.isArray(data.students) ? data.students : [];
      this.state.routes = Array.isArray(data.routes) ? data.routes : [];
      this.renderRouteOptions();
      this.renderIncidentOptions();
      this.render();
    } catch (error) {
      const status = Number(error?.status || error?.code || 0);
      this.hide('passengersCard');
      if (status === 401 || status === 403) this.show('passengersForbidden');
      else {
        const box = this.el('passengersError');
        if (box) box.textContent = error?.message || 'The passenger manifest could not be loaded.';
        this.show('passengersError');
      }
      console.error('transport passengers manifest load failed', error);
    } finally {
      this.hide('passengersLoading');
    }
  },

  renderRouteOptions() {
    const routeOptions = this.state.routes.map(route =>
      `<option value="${Number(route.id)}">${this.esc(route.name || 'Route')}</option>`).join('');
    const routeFilter = this.el('routeFilter');
    const incidentRoute = this.el('incidentRoute');
    if (routeFilter) {
      const selected = routeFilter.value;
      routeFilter.innerHTML = '<option value="">All assigned routes</option>' + routeOptions;
      routeFilter.value = selected;
    }
    if (incidentRoute) incidentRoute.innerHTML = '<option value="">Select Route</option>' + routeOptions;

    const vehicles = new Map();
    this.state.routes.forEach(route => {
      if (route.vehicle_id) vehicles.set(String(route.vehicle_id), route.vehicle_registration || route.vehicle_name || 'Vehicle');
    });
    const vehicleOptions = [...vehicles].map(([id, name]) => `<option value="${Number(id)}">${this.esc(name)}</option>`).join('');
    const vehicleFilter = this.el('vehicleFilter');
    const incidentVehicle = this.el('incidentVehicle');
    if (vehicleFilter) {
      const selected = vehicleFilter.value;
      vehicleFilter.innerHTML = '<option value="">All assigned vehicles</option>' + vehicleOptions;
      vehicleFilter.value = selected;
    }
    if (incidentVehicle) incidentVehicle.innerHTML = '<option value="">Select Vehicle</option>' + vehicleOptions;
  },

  renderIncidentOptions() {
    const select = this.el('incidentStudent');
    if (!select) return;
    const options = this.state.students.map(student =>
      `<option value="${Number(student.student_id)}">${this.esc(student.full_name || `${student.first_name || ''} ${student.last_name || ''}`.trim())} (${this.esc(student.admission_no || '')})</option>`).join('');
    select.innerHTML = '<option value="">Select Student (Optional)</option>' + options;
  },

  filteredStudents() {
    const filters = {
      route: this.value('routeFilter'), vehicle: this.value('vehicleFilter'),
      className: this.value('classFilter'), stream: this.value('streamFilter'),
      gender: this.value('genderFilter'), transport: this.value('transportStatusFilter'),
      attendance: this.value('attendanceStatusFilter'), search: this.value('searchBox').toLowerCase(),
    };
    const routeById = new Map(this.state.routes.map(route => [String(route.id), route]));
    return this.state.students.filter(student => {
      const route = routeById.get(String(student.route_id));
      const vehicleId = student.vehicle_id || route?.vehicle_id;
      const status = student.attendance_status || 'pending';
      const haystack = [student.admission_no, student.full_name, student.first_name, student.last_name,
        student.pickup_point, student.dropoff_point, student.guardian_phone].join(' ').toLowerCase();
      return (!filters.route || String(student.route_id) === filters.route)
        && (!filters.vehicle || String(vehicleId || '') === filters.vehicle)
        && (!filters.className || String(student.class_id || '') === filters.className)
        && (!filters.stream || String(student.stream_id || '') === filters.stream)
        && (!filters.gender || String(student.gender || '').toLowerCase() === filters.gender)
        && (!filters.transport || String(student.assignment_status || 'active') === filters.transport)
        && (!filters.attendance || status === filters.attendance)
        && (!filters.search || haystack.includes(filters.search));
    });
  },

  render() {
    const body = this.el('passengersTableBody');
    if (!body) return;
    const students = this.filteredStudents();
    const routeById = new Map(this.state.routes.map(route => [String(route.id), route]));
    const vehicleName = (student, route) => student.vehicle_registration || student.vehicle_name
      || route?.vehicle_registration || route?.vehicle_name || '—';
    const statusNames = { picked_up: 'Picked up', dropped_off: 'Dropped off', absent: 'Absent', excused: 'Excused', not_riding: 'Not riding', pending: 'Pending' };

    this.hide('passengersCard');
    if (!students.length) {
      body.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-4">No passengers found for the selected date, trip, and filters.</td></tr>';
      this.show('passengersEmpty');
    } else {
      this.hide('passengersEmpty');
      body.innerHTML = students.map(student => {
        const route = routeById.get(String(student.route_id));
        const status = student.attendance_status || 'pending';
        return `<tr>
          <td class="small">${this.esc(student.admission_no || '—')}</td>
          <td class="small">${this.esc(student.full_name || `${student.first_name || ''} ${student.last_name || ''}`.trim() || '—')}</td>
          <td class="small">${this.esc(student.class_name || '—')}</td>
          <td class="small">${this.esc(student.stream_name || '—')}</td>
          <td class="small text-capitalize">${this.esc(student.gender || '—')}</td>
          <td class="small">${this.esc(route?.name || student.route_name || '—')}</td>
          <td class="small">${this.esc(vehicleName(student, route))}</td>
          <td class="small">${this.esc(student.pickup_point || student.stop_name || '—')}</td>
          <td class="small">${this.esc(student.dropoff_point || '—')}</td>
          <td class="small">${this.esc(student.guardian_phone || '—')}</td>
          <td class="small"><span class="badge bg-${status === 'picked_up' || status === 'dropped_off' ? 'success' : status === 'absent' ? 'danger' : 'secondary'}">${this.esc(statusNames[status] || status)}</span></td>
          <td><button class="btn btn-sm btn-outline-primary" type="button" onclick="transportPassengersController.openPassenger(${Number(student.student_id)})" aria-label="View passenger"><i class="bi bi-eye"></i></button></td>
        </tr>`;
      }).join('');
      this.show('passengersCard');
    }

    const summary = this.state.manifest?.summary || {};
    const values = students;
    this.setText('totalPassengers', summary.expected ?? this.state.students.length);
    this.setText('expectedToday', summary.expected ?? this.state.students.length);
    this.setText('pickedUp', values.filter(student => student.attendance_status === 'picked_up').length);
    this.setText('droppedOff', values.filter(student => student.attendance_status === 'dropped_off').length);
    this.setText('absentNotRiding', values.filter(student => ['absent', 'not_riding', 'excused'].includes(student.attendance_status)).length);
    this.setText('pendingPickup', values.filter(student => !student.attendance_status || student.attendance_status === 'pending').length);
    this.setText('emergencyAlerts', 0);
    this.setText('routeVehicle', this.state.routes.map(route => `${route.name || 'Route'}${route.vehicle_registration ? ` · ${route.vehicle_registration}` : ''}`).join(', ') || 'No assigned route');
  },

  openPassenger(studentId) {
    const student = this.state.students.find(item => Number(item.student_id) === Number(studentId));
    if (!student) return;
    this.state.selectedStudent = student;
    const route = this.state.routes.find(item => Number(item.id) === Number(student.route_id));
    this.setText('modalSubtitle', `${student.full_name || `${student.first_name || ''} ${student.last_name || ''}`.trim()} · ${student.admission_no || ''}`);
    const content = this.el('modalPassengerContent');
    if (content) content.innerHTML = `<dl class="row mb-0">
      <dt class="col-sm-4">Class / stream</dt><dd class="col-sm-8">${this.esc(student.class_name || '—')} / ${this.esc(student.stream_name || '—')}</dd>
      <dt class="col-sm-4">Route / vehicle</dt><dd class="col-sm-8">${this.esc(route?.name || '—')} / ${this.esc(student.vehicle_registration || route?.vehicle_registration || '—')}</dd>
      <dt class="col-sm-4">Pickup / drop-off</dt><dd class="col-sm-8">${this.esc(student.pickup_point || student.stop_name || '—')} / ${this.esc(student.dropoff_point || '—')}</dd>
      <dt class="col-sm-4">Guardian contact</dt><dd class="col-sm-8">${this.esc(student.guardian_phone || '—')}</dd>
      <dt class="col-sm-4">Trip status</dt><dd class="col-sm-8">${this.esc(student.attendance_status || 'pending')}</dd>
    </dl>`;
    const modal = this.el('passengerModal');
    if (modal && window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(modal).show();
  },

  async markStatus(status) {
    const student = this.state.selectedStudent;
    if (!student) return;
    const effectiveStatus = status === 'dropped_off' && this.currentTrip() !== 'evening_dropoff' ? 'dropped_off' : status;
    try {
      await window.API.callAPI('/transport/attendance', 'POST', {
        date: this.currentDate(), route_id: Number(student.route_id), trip_session: this.currentTrip(),
        status: effectiveStatus, present_student_ids: [Number(student.student_id)],
      });
      window.bootstrap?.Modal.getInstance(this.el('passengerModal'))?.hide();
      this.notify('Passenger attendance updated.', 'success');
      await this.load();
    } catch (error) {
      this.notify(error?.message || 'Passenger attendance could not be updated.', 'danger');
    }
  },

  openIncident() {
    const now = new Date();
    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    const dateTime = this.el('incidentDateTime');
    if (dateTime) dateTime.value = local;
    const modal = this.el('incidentModal');
    if (modal && window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(modal).show();
  },

  async submitIncident() {
    const type = this.value('incidentType');
    const description = this.value('incidentDescription').trim();
    if (!type || !description) { this.notify('Incident type and description are required.', 'warning'); return; }
    const routeId = this.value('incidentRoute');
    const vehicleId = this.value('incidentVehicle');
    const route = this.state.routes.find(item => String(item.id) === routeId);
    const vehicle = [...(this.el('incidentVehicle')?.options || [])].find(option => option.value === vehicleId)?.textContent;
    const studentId = this.value('incidentStudent');
    const student = this.state.students.find(item => String(item.student_id) === studentId);
    const location = [route?.name, vehicle && vehicle !== 'Select Vehicle' ? vehicle : null].filter(Boolean).join(' / ');
    const action = [this.value('incidentActionTaken'), this.value('incidentNotes')].filter(Boolean).join('\n');
    const severityByType = { accident: 'high', medical: 'critical', late_pickup: 'low', late_dropoff: 'low', wrong_stop: 'medium', behavior: 'medium', vehicle_breakdown: 'high', other: 'medium' };
    const severity = this.value('incidentEscalate') === '1' ? 'high' : severityByType[type] || 'medium';
    const occurredAt = this.value('incidentDateTime').replace('T', ' ') + ':00';
    const details = [description, student ? `Passenger: ${student.full_name || `${student.first_name || ''} ${student.last_name || ''}`.trim()} (${student.admission_no || ''})` : null].filter(Boolean).join('\n');
    try {
      await window.API.callAPI('/incidents', 'POST', {
        title: `Transport incident: ${type.replace(/_/g, ' ')}`, category: 'transport', severity,
        occurred_at: occurredAt, location: location || null, description: details,
        immediate_action: action || null,
      });
      window.bootstrap?.Modal.getInstance(this.el('incidentModal'))?.hide();
      this.el('incidentForm')?.reset();
      this.notify('Transport incident reported.', 'success');
    } catch (error) {
      this.notify(error?.message || 'Incident could not be reported.', 'danger');
    }
  },

  bind() {
    this.el('refreshBtn')?.addEventListener('click', () => this.load());
    this.el('dateFilter')?.addEventListener('change', () => this.load());
    this.el('tripSessionFilter')?.addEventListener('change', () => this.load());
    ['routeFilter', 'vehicleFilter', 'classFilter', 'streamFilter', 'genderFilter', 'transportStatusFilter', 'attendanceStatusFilter']
      .forEach(id => this.el(id)?.addEventListener('change', () => this.render()));
    this.el('searchBox')?.addEventListener('input', () => this.render());
    this.el('resetFiltersBtn')?.addEventListener('click', () => {
      ['routeFilter', 'vehicleFilter', 'classFilter', 'streamFilter', 'genderFilter', 'transportStatusFilter', 'attendanceStatusFilter', 'searchBox']
        .forEach(id => { const node = this.el(id); if (node) node.value = ''; });
      this.el('dateFilter').value = new Date().toISOString().slice(0, 10);
      this.el('tripSessionFilter').value = 'morning_pickup';
      this.load();
    });
    this.el('reportIncidentBtn')?.addEventListener('click', () => this.openIncident());
    this.el('saveIncidentBtn')?.addEventListener('click', () => this.submitIncident());
    this.el('markPickedUpBtn')?.addEventListener('click', () => this.markStatus('picked_up'));
    this.el('markDroppedOffBtn')?.addEventListener('click', () => this.markStatus('dropped_off'));
    this.el('markAbsentBtn')?.addEventListener('click', () => this.markStatus('absent'));
    this.el('printListBtn')?.addEventListener('click', () => window.print());
    this.el('exportSheetBtn')?.addEventListener('click', () => this.exportCsv());
  },

  exportCsv() {
    const rows = [['Admission number', 'Student', 'Class', 'Stream', 'Route', 'Pickup', 'Drop-off', 'Trip status']];
    this.filteredStudents().forEach(student => rows.push([
      student.admission_no || '', student.full_name || `${student.first_name || ''} ${student.last_name || ''}`.trim(),
      student.class_name || '', student.stream_name || '', student.route_name || '', student.pickup_point || student.stop_name || '',
      student.dropoff_point || '', student.attendance_status || 'pending',
    ]));
    const csv = rows.map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob); link.download = 'transport-passengers.csv'; link.click(); URL.revokeObjectURL(link.href);
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const date = this.el('dateFilter');
    if (date && !date.value) date.value = new Date().toISOString().slice(0, 10);
    this.bind();
    await this.load();
    window.APIRealtime?.register?.('transport_passengers', { refresh: () => this.load() }, ['transport']);
  },
};

window.transportPassengersController = transportPassengersController;
document.addEventListener('DOMContentLoaded', () => transportPassengersController.init().catch(error => {
  console.error('Transport passenger page failed to initialize:', error);
}));
