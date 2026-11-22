/**
 * Transport Admin Controller
 * Administrative interface for transport management.
 */
const transportAdminController = {
  state: { referenceCache: null, routes: [], vehicles: [], drivers: [], stops: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  isActive() { return !!document.getElementById('transportRoutesBody'); },

  async loadReferencesComposed() {
    try {
      if (!this.isActive()) { this.state.referenceCache = null; return; }
      const response = await window.API.nodeViews.read("transport", "transport-admin-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Transport Admin Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      if (!this.isActive()) return;
      const cache = this.state.referenceCache;
      const t = window.API?.transport;
      const read = (key, fallback) => (cache?.[key] !== undefined ? cache[key] : fallback);
      const [routes, vehicles, drivers, stops] = await Promise.all([
        read('routes', t ? t.getAllRoutes() : this.API('GET', '/api/transport/all-routes')),
        read('vehicles', t ? t.getAllVehicles() : this.API('GET', '/api/transport/all-vehicles')),
        read('drivers', t ? t.getAllDrivers() : this.API('GET', '/api/transport/all-drivers')),
        read('stops', t ? t.getAllStops() : this.API('GET', '/api/transport/all-stops')),
      ]);
      this.state.routes = Array.isArray(routes?.data) ? routes.data : (Array.isArray(routes) ? routes : []);
      this.state.vehicles = Array.isArray(vehicles?.data) ? vehicles.data : (Array.isArray(vehicles) ? vehicles : []);
      this.state.drivers = Array.isArray(drivers?.data) ? drivers.data : (Array.isArray(drivers) ? drivers : []);
      this.state.stops = Array.isArray(stops?.data) ? stops.data : (Array.isArray(stops) ? stops : []);
      this.renderRoutes();
      this.renderVehicles();
      this.renderDrivers();
      this.renderStops();
      this.renderFormOptions();
      this.renderKpis();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderRoutes() {
    const routes = this.state.routes;
    const body = document.getElementById('transportRoutesBody');
    if (!body) return;
    if (!routes.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No routes defined.</td></tr>'; return; }
    body.innerHTML = routes.map(r => {
      const roads = [r.start_point, r.end_point].filter(Boolean).join(' → ') || '—';
      return `<tr>
      <td class="small">${this.esc(r.name || '—')}${r.code ? `<div class="text-muted">${this.esc(r.code)}</div>` : ''}</td>
      <td class="small">${this.esc(roads)}</td>
      <td class="small">${this.esc(this.fmtTime(r.morning_departure))} / ${this.esc(this.fmtTime(r.afternoon_departure))}</td>
      <td class="small">${this.esc(r.vehicle_registration || '—')}${r.driver_name ? `<div class="text-muted">${this.esc(r.driver_name)}</div>` : ''}</td>
      <td class="small">${Number(r.stop_count) || 0} stops</td>
      <td class="small"><span class="badge bg-${r.status === 'active' ? 'success' : 'secondary'}">${r.status === 'active' ? 'Active' : 'Inactive'}</span></td>
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" data-kw-onclick="transportAdminController.editRoute(${Number(r.id)})"><i class="bi bi-pencil"></i></button></td>
    </tr>`;
    }).join('');
  },

  renderVehicles() {
    const vehicles = this.state.vehicles;
    const body = document.getElementById('transportVehiclesBody');
    if (!body) return;
    if (!vehicles.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No vehicles registered.</td></tr>'; return; }
    body.innerHTML = vehicles.map(v => {
      const model = [v.type, v.model].filter(Boolean).join(' ');
      const statusClass = v.status === 'active' ? 'success' : v.status === 'maintenance' ? 'warning' : 'secondary';
      return `<tr>
      <td class="small">${this.esc(v.registration_number || '—')}</td>
      <td class="small">${this.esc(model || '—')}</td>
      <td class="small">${Number(v.capacity) || 0} seats</td>
      <td class="small">${this.esc(this.fmtDate(v.insurance_expiry))}</td>
      <td class="small">${this.esc(this.fmtDate(v.service_due_date))}</td>
      <td class="small"><span class="badge bg-${statusClass}">${this.esc(v.status || '—')}</span></td>
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" data-kw-onclick="transportAdminController.editVehicle(${Number(v.id)})"><i class="bi bi-pencil"></i></button></td>
    </tr>`;
    }).join('');
  },

  renderDrivers() {
    const drivers = this.state.drivers;
    const body = document.getElementById('transportDriversBody');
    if (!body) return;
    if (!drivers.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No drivers registered.</td></tr>'; return; }
    body.innerHTML = drivers.map(d => {
      const name = [d.first_name, d.last_name].filter(Boolean).join(' ');
      return `<tr>
      <td class="small">${this.esc(name || '—')}</td>
      <td class="small">${this.esc(d.staff_no || '—')}</td>
      <td class="small">${this.esc(d.phone || '—')}</td>
      <td class="small">${this.esc(d.license_number || '—')}</td>
      <td class="small"><span class="badge bg-${d.status === 'active' ? 'success' : 'secondary'}">${this.esc(d.status || '—')}</span></td>
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" data-kw-onclick="transportAdminController.editDriver(${Number(d.id)})"><i class="bi bi-pencil"></i></button></td>
    </tr>`;
    }).join('');
  },

  renderStops() {
    const stops = this.state.stops;
    const body = document.getElementById('transportStopsBody');
    if (!body) return;
    if (!stops.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No stops defined.</td></tr>'; return; }
    const routeNames = new Map(this.state.routes.map(r => [String(r.id), r.name]));
    body.innerHTML = stops.map(s => `<tr>
      <td class="small">${this.esc(s.name || '—')}</td>
      <td class="small">${this.esc(routeNames.get(String(s.route_id)) || '—')}</td>
      <td class="small">${Number(s.sequence) || 0}</td>
      <td class="small">${this.esc(s.location || '—')}</td>
      <td class="small"><span class="badge bg-${s.status === 'active' ? 'success' : 'secondary'}">${s.status === 'active' ? 'Active' : 'Inactive'}</span></td>
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" data-kw-onclick="transportAdminController.editStop(${Number(s.id)})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderFormOptions() {
    const vehicles = this.state.vehicles;
    const drivers = this.state.drivers;
    const routes = this.state.routes;
    const vehicleSelect = document.getElementById('traRouteVehicleSelect');
    const driverSelect = document.getElementById('traRouteDriverSelect');
    const stopRouteSelect = document.getElementById('traStopRouteSelect');
    // The route/stop selects are rebuilt when the editor body is injected, so
    // all three are refreshed here rather than only the vehicle/driver pair.
    if (vehicleSelect) {
      vehicleSelect.innerHTML = '<option value="">Select Vehicle</option>' + vehicles
        .map(v => `<option value="${Number(v.id)}">${this.esc(v.registration_number || '—')}</option>`).join('');
    }
    if (driverSelect) {
      driverSelect.innerHTML = '<option value="">Select Driver</option>' + drivers
        .map(d => `<option value="${Number(d.id)}">${this.esc([d.first_name, d.last_name].filter(Boolean).join(' ') || '—')}</option>`).join('');
    }
    if (stopRouteSelect) {
      stopRouteSelect.innerHTML = '<option value="">Select Route</option>' + routes
        .map(r => `<option value="${Number(r.id)}">${this.esc(r.name || '—')}</option>`).join('');
    }
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },
  fmtTime(v) { if (!v) return '—'; return v.length > 5 ? v.substring(0, 5) : v; },

  // Toast wrapper. The modal save path reports its own outcome, and a page
  // controller must never assume a caller-provided notifier exists.
  notify(message, kind = 'info') {
    if (typeof window.showNotification === 'function') {
      window.showNotification(message, kind);
      return;
    }
    console.warn(kind === 'danger' ? message : message);
  },

  // The four hero cards are static markup with placeholder dashes; without
  // this they stay "—" no matter how much data the tables hold.
  renderKpis() {
    const routes = this.state.routes.filter(r => r.status === 'active').length;
    const vehicles = this.state.vehicles.filter(v => v.status === 'active').length;
    // Assigned learners are carried per route on the routes payload.
    const learners = this.state.routes.reduce((sum, r) => sum + (Number(r.student_count) || 0), 0);
    const attention = this.state.vehicles.filter(v => v.status === 'maintenance').length
      + this.state.stops.filter(s => s.status === 'inactive').length;
    const set = (key, value) => {
      const el = document.querySelector(`[data-kpi="${key}"]`);
      if (el) el.textContent = String(value);
    };
    set('routes', routes);
    set('vehicles', vehicles);
    set('learners', learners);
    set('attention', attention);
  },

  bindUi() {
    const root = document.getElementById('transport-content') || document;

    // Custom tab switching (not Bootstrap tabs)
    root.querySelectorAll('.transport-tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        root.querySelectorAll('.transport-tab').forEach((t) => t.classList.remove('active'));
        root.querySelectorAll('.transport-panel').forEach((p) => p.classList.remove('active'));
        tab.classList.add('active');
        const panel = document.getElementById(tab.dataset.panel);
        if (panel) panel.classList.add('active');
      });
    });

    // Refresh
    document.getElementById('transportRefresh')?.addEventListener('click', () => {
      this.state.referenceCache = null;
      this.loadReferencesComposed().then(() => this.load());
    });

    // Add buttons
    // Every Add action posts with transport_create. A role that opens this
    // workspace without it (school administrators) only got "Access Denied"
    // after filling the form, so the buttons are hidden instead.
    if (window.AuthContext?.hasPermission?.('transport_create') === false) {
      ['transportAddRoute', 'transportAddVehicle', 'transportAddDriver', 'transportAddStop']
        .forEach((buttonId) => { const button = document.getElementById(buttonId); if (button) button.hidden = true; });
    }
    document.getElementById('transportAddRoute')?.addEventListener('click', () => this.openEditor('route'));
    document.getElementById('transportAddVehicle')?.addEventListener('click', () => this.openEditor('vehicle'));
    document.getElementById('transportAddDriver')?.addEventListener('click', () => this.openEditor('driver'));
    document.getElementById('transportAddStop')?.addEventListener('click', () => this.openEditor('stop'));

    // Search / status filter
    document.getElementById('transportSearch')?.addEventListener('input', () => this.applyFilters());
    document.getElementById('transportStatus')?.addEventListener('change', () => this.applyFilters());

    // Editor commit. Without this binding the modal's "Save changes" button
    // does nothing at all - saveEditor() is correct but never wired up.
    document.getElementById('transportEditorSave')?.addEventListener('click', () => {
      this.saveEditor().catch((error) => this.notify(error.message || 'Could not save the record.', 'danger'));
    });

    // CSV export of whatever the current table holds (Hard Rule 7).
    document.getElementById('transportExport')?.addEventListener('click', () => this.exportCsv());

    // Collections auto-load (Rule 6): default to the current month and reload
    // whenever the month changes - no "Load" button.
    const billingMonth = document.getElementById('transportBillingMonth');
    if (billingMonth) {
      if (!billingMonth.value) billingMonth.value = new Date().toISOString().slice(0, 7);
      billingMonth.addEventListener('change', () => this.loadBilling());
      document.querySelector('[data-panel="billingPanel"]')?.addEventListener('click', () => this.loadBilling());
    }
  },

  exportCsv() {
    const rows = [['Route', 'Code', 'Roads', 'Morning', 'Afternoon', 'Vehicle', 'Driver', 'Stops', 'Status']];
    this.state.routes.forEach(r => rows.push([
      r.name || '', r.code || '',
      [r.start_point, r.end_point].filter(Boolean).join(' -> '),
      this.fmtTime(r.morning_departure), this.fmtTime(r.afternoon_departure),
      r.vehicle_registration || '', r.driver_name || '',
      String(Number(r.stop_count) || 0), r.status || '',
    ]));
    if (typeof window.KingswayFileLifecycle?.exportText === 'function') {
      window.KingswayFileLifecycle.exportText(
        rows.map(row => row.join(',')).join('\n'),
        'transport-routes.csv',
        'text/csv'
      );
      this.notify('Routes exported.', 'success');
      return;
    }
    this.notify('CSV export is unavailable on this page.', 'warning');
  },

  async loadBilling() {
    const summary = document.getElementById('transportBillingSummary');
    const body = document.getElementById('transportBillingBody');
    const month = document.getElementById('transportBillingMonth')?.value || '';
    if (!month) { this.notify('Choose a month first.', 'warning'); return; }
    try {
      const t = window.API?.transport;
      if (!t) throw new Error('The transport API is unavailable on this page.');
      const [bills, totals] = await Promise.all([
        t.getBills({ month }),
        t.getBillsSummary({ month }),
      ]);
      const rows = Array.isArray(bills?.data) ? bills.data : (Array.isArray(bills) ? bills : []);
      body.innerHTML = rows.length ? rows.map(b => `<tr>
        <td class="small">${this.esc([b.first_name, b.last_name].filter(Boolean).join(' ') || '—')}</td>
        <td class="small">${this.esc(b.route_name || '—')}</td>
        <td class="small">${Number(b.amount_due || 0).toFixed(2)}</td>
        <td class="small">${Number(b.amount_paid || 0).toFixed(2)}</td>
        <td class="small">${Number(b.balance || 0).toFixed(2)}</td>
        <td class="small"><span class="badge bg-${b.payment_status === 'paid' ? 'success' : 'warning'}">${this.esc(b.payment_status || '—')}</span></td>
      </tr>`).join('') : '<tr><td colspan="6" class="transport-empty">No collections for this month.</td></tr>';
      const s = totals?.data?.summary || totals?.summary || {};
      if (summary) {
        // Fixed order and readable labels; money as KES, counts as integers.
        const money = (v) => 'KES ' + Number(v || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const count = (v) => Number(v || 0).toLocaleString('en-KE');
        const chips = [
          ['total_bills', 'Bills', count],
          ['total_due', 'Total due', money],
          ['total_paid', 'Collected', money],
          ['total_outstanding', 'Outstanding', money],
          ['paid_count', 'Paid', count],
          ['pending_count', 'Pending', count],
        ];
        summary.innerHTML = Object.keys(s).length
          ? chips.map(([key, label, fmt]) => `<div class="transport-summary-chip"><small>${this.esc(label)}</small><strong>${this.esc(fmt(s[key]))}</strong></div>`).join('')
          : '';
      }
    } catch (error) {
      this.notify(error.message || 'Could not load collections.', 'danger');
    }
  },

  openEditor(type, id) {
    const body = document.getElementById('transportEditorBody');
    const title = document.getElementById('transportEditorTitle');
    const subtitle = document.getElementById('transportEditorSubtitle');
    const saveBtn = document.getElementById('transportEditorSave');

    // Reset form. The mode must be staged BEFORE the per-type pre-fill below,
    // which reads it: leaving it unset meant the record was never loaded into
    // the form and every edit row opened an empty editor that created a new row.
    saveBtn.dataset.mode = id ? 'edit' : 'add';
    saveBtn.dataset.type = type;
    saveBtn.dataset.id = id || '';

    // Reset form
    body.innerHTML = '';

    if (type === 'route') {
      title.textContent = 'Add / Edit Route';
      subtitle.textContent = '';
      body.innerHTML = `
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label small">Route name</label>
            <input type="text" class="form-control" id="traRouteName" placeholder="e.g. Main Route">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Code (optional)</label>
            <input type="text" class="form-control" id="traRouteCode" placeholder="e.g. MR01">
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label small">Morning departure</label>
            <input type="text" class="form-control" id="traRouteMorning" placeholder="HH:MM">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Afternoon departure</label>
            <input type="text" class="form-control" id="traRouteAfternoon" placeholder="HH:MM">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Status</label>
            <select class="form-select form-select-sm" id="traRouteStatus">
              <option value="active">Active</option>
              <option value="maintenance">Maintenance</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-6">
            <label class="form-label small">Vehicle</label>
            <select class="form-select form-select-sm" id="traRouteVehicleSelect"></select>
          </div>
          <div class="col-6">
            <label class="form-label small">Driver</label>
            <select class="form-select form-select-sm" id="traRouteDriverSelect"></select>
          </div>
        </div>
      `;
      // populate vehicle/driver selects
      if (this.state.vehicles.length) this.renderFormOptions();
      if (saveBtn.dataset.mode === 'edit' && id) {
        const route = this.state.routes.find(r => r.id === id);
        if (route) {
          document.getElementById('traRouteName').value = route.name || '';
          document.getElementById('traRouteCode').value = route.code || '';
          document.getElementById('traRouteMorning').value = this.fmtTime(route.morning_departure || '');
          document.getElementById('traRouteAfternoon').value = this.fmtTime(route.afternoon_departure || '');
          document.getElementById('traRouteStatus').value = route.status || 'active';
        }
      }
    } else if (type === 'vehicle') {
      title.textContent = 'Add / Edit Vehicle';
      subtitle.textContent = '';
      body.innerHTML = `
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label small">Registration number</label>
            <input type="text" class="form-control" id="traVehicleReg" placeholder="e.g. KRC 123AB">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Type / model</label>
            <input type="text" class="form-control" id="traVehicleModel" placeholder="e.g. Bus / Mini-bus">
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label small">Capacity</label>
            <input type="number" class="form-control" id="traVehicleCapacity" min="1" placeholder="e.g. 40">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Status</label>
            <select class="form-select form-select-sm" id="traVehicleStatus">
              <option value="active">Active</option>
              <option value="maintenance">Maintenance</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
      `;
      if (saveBtn.dataset.mode === 'edit' && id) {
        const vehicle = this.state.vehicles.find(v => v.id === id);
        if (vehicle) {
          document.getElementById('traVehicleReg').value = vehicle.registration_number || '';
          document.getElementById('traVehicleModel').value = [(vehicle.type || ''), (vehicle.model || '')].filter(Boolean).join(' ') || '';
          document.getElementById('traVehicleCapacity').value = Number(vehicle.capacity) || 0;
          document.getElementById('traVehicleStatus').value = vehicle.status || 'active';
        }
      }
    } else if (type === 'driver') {
      title.textContent = 'Add / Edit Driver';
      subtitle.textContent = '';
      body.innerHTML = `
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label small">First name</label>
            <input type="text" class="form-control" id="traDriverFirst" placeholder="John">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Last name</label>
            <input type="text" class="form-control" id="traDriverLast" placeholder="Doe">
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label small">Staff number</label>
            <input type="text" class="form-control" id="traDriverStaffNo" placeholder="e.g. STF-001">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Phone</label>
            <input type="text" class="form-control" id="traDriverPhone" placeholder="e.g. 0712345678">
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label small">License number</label>
            <input type="text" class="form-control" id="traDriverLicense" placeholder="e.g. ABC123">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Status</label>
            <select class="form-select form-select-sm" id="traDriverStatus">
              <option value="active">Active</option>
              <option value="maintenance">Maintenance</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
      `;
      if (saveBtn.dataset.mode === 'edit' && id) {
        const driver = this.state.drivers.find(d => d.id === id);
        if (driver) {
          document.getElementById('traDriverFirst').value = driver.first_name || '';
          document.getElementById('traDriverLast').value = driver.last_name || '';
          document.getElementById('traDriverStaffNo').value = driver.staff_no || '';
          document.getElementById('traDriverPhone').value = driver.phone || '';
          document.getElementById('traDriverLicense').value = driver.license_number || '';
          document.getElementById('traDriverStatus').value = driver.status || 'active';
        }
      }
    } else if (type === 'stop') {
      title.textContent = 'Add / Edit Stop';
      subtitle.textContent = '';
      body.innerHTML = `
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label small">Stop name</label>
            <input type="text" class="form-control" id="traStopName" placeholder="e.g. Main Gate">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Route</label>
            <select class="form-select form-select-sm" id="traStopRouteSelect"></select>
          </div>
        </div>
        <div class="row mb-3">
          <label class="form-label small">Sequence</label>
          <input type="number" class="form-control" id="traStopSequence" min="1" placeholder="1">
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label small">Location</label>
            <input type="text" class="form-control" id="traStopLocation" placeholder="e.g. Gate A">
          </div>
          <div class="col-md-6">
            <label class="form-label small">Status</label>
            <select class="form-select form-select-sm" id="traStopStatus">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
      `;
      // populate route select
      const routeSelect = document.getElementById('traStopRouteSelect');
      if (routeSelect) {
        routeSelect.innerHTML = '<option value="">Select Route</option>' +
          this.state.routes.map(r => `<option value="${r.id}">${this.esc(r.name)}</option>`).join('');
      }      if (saveBtn.dataset.mode === 'edit' && id) {
        const stop = this.state.stops.find(s => s.id === id);
        if (stop) {
          document.getElementById('traStopName').value = stop.name || '';
          const routeSelect = document.getElementById('traStopRouteSelect');
          if (routeSelect) {
            routeSelect.value = String(stop.route_id || '');
          }
          document.getElementById('traStopSequence').value = Number(stop.sequence) || 1;
          document.getElementById('traStopLocation').value = stop.location || '';
          document.getElementById('traStopStatus').value = stop.status || 'active';
        }
      }
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('transportEditorModal')).show();
  },

  // Client-side filtering for the search box and status select. The inputs are
  // bound in bindUi(), so this must exist or every keystroke throws.
  applyFilters() {
    const query = (document.getElementById('transportSearch')?.value || '').trim().toLowerCase();
    const status = document.getElementById('transportStatus')?.value || '';
    const activePanel = document.querySelector('.transport-panel.active');
    const body = activePanel?.querySelector('tbody');
    if (!body) return;
    const rows = Array.from(body.querySelectorAll('tr'));
    let visible = 0;
    rows.forEach((row) => {
      const text = row.textContent.toLowerCase();
      const matchesQuery = query === '' || text.includes(query);
      const matchesStatus = status === '' || text.includes(status);
      const show = matchesQuery && matchesStatus;
      row.style.display = show ? '' : 'none';
      if (show) visible += 1;
    });
    let empty = body.querySelector('.transport-filter-empty');
    if (visible === 0 && rows.length) {
      if (!empty) {
        empty = document.createElement('tr');
        empty.className = 'transport-filter-empty';
        empty.innerHTML = '<td colspan="' + (body.closest('table')?.querySelectorAll('thead th').length || 1) + '" class="transport-empty text-center py-3">No records match the current filters.</td>';
        body.appendChild(empty);
      }
    } else if (empty) {
      empty.remove();
    }
  },

  async saveEditor() {
    const saveBtn = document.getElementById('transportEditorSave');
    const type = saveBtn.dataset.type;
    const id = saveBtn.dataset.id;

    // Read form values
    let formData;
    if (type === 'route') {
      formData = {
        name: document.getElementById('traRouteName')?.value.trim(),
        code: document.getElementById('traRouteCode')?.value.trim(),
        morning_departure: document.getElementById('traRouteMorning')?.value.trim(),
        afternoon_departure: document.getElementById('traRouteAfternoon')?.value.trim(),
        status: document.getElementById('traRouteStatus')?.value || 'active',
        vehicle_id: document.getElementById('traRouteVehicleSelect')?.value || null,
        driver_id: document.getElementById('traRouteDriverSelect')?.value || null,
      };
    } else if (type === 'vehicle') {
      formData = {
        registration_number: document.getElementById('traVehicleReg')?.value.trim(),
        type: (document.getElementById('traVehicleModel')?.value.trim() || '').split(' ')[0] || '',
        model: (document.getElementById('traVehicleModel')?.value.trim() || '').split(' ').slice(1).join(' ') || '',
        capacity: Number(document.getElementById('traVehicleCapacity')?.value) || 0,
        status: document.getElementById('traVehicleStatus')?.value || 'active',
      };
    } else if (type === 'driver') {
      formData = {
        first_name: document.getElementById('traDriverFirst')?.value.trim(),
        last_name: document.getElementById('traDriverLast')?.value.trim(),
        staff_no: document.getElementById('traDriverStaffNo')?.value.trim(),
        phone: document.getElementById('traDriverPhone')?.value.trim(),
        license_number: document.getElementById('traDriverLicense')?.value.trim(),
        status: document.getElementById('traDriverStatus')?.value || 'active',
      };
    } else if (type === 'stop') {
      formData = {
        name: document.getElementById('traStopName')?.value.trim(),
        route_id: document.getElementById('traStopRouteSelect')?.value || null,
        sequence: Number(document.getElementById('traStopSequence')?.value) || 1,
        location: document.getElementById('traStopLocation')?.value.trim(),
        status: document.getElementById('traStopStatus')?.value || 'active',
      };
    }

    if (!formData.name) {
      this.notify('Enter a name.', 'warning');
      return;
    }

    const submit = saveBtn;
    if (submit) submit.disabled = true;
    try {
      // Always commit through the canonical window.API.transport wrappers:
      // the write routes are `/transport/transport-route` (POST/PUT), which is
      // not derivable from the type name, and the wrappers also carry the
      // permission contract and response envelope handling.
      const t = window.API?.transport;
      if (!t) throw new Error('The transport API is unavailable on this page.');
      // Explicit branching, never an eagerly-evaluated lookup object: building
      // `{ route: t.createRoute(...) }` would call every write the moment the
      // object is constructed, saving a vehicle and a stop too.
      if (id) {
        if (type === 'route') await t.updateRoute(id, formData);
        else if (type === 'vehicle') await t.updateVehicle(id, formData);
        else if (type === 'driver') await t.updateDriver(id, formData);
        else if (type === 'stop') await t.updateStop(id, formData);
      } else {
        if (type === 'route') await t.createRoute(formData);
        else if (type === 'vehicle') await t.createVehicle(formData);
        else if (type === 'driver') await t.createDriver(formData);
        else if (type === 'stop') await t.createStop(formData);
      }
      bootstrap.Modal.getInstance(document.getElementById('transportEditorModal')).hide();
      this.notify(`${type.charAt(0).toUpperCase() + type.slice(1)} ${id ? 'updated' : 'saved'}.`, 'success');
      this.load();
    } catch (error) {
      this.notify(error.message || `Could not ${id ? 'update' : 'save'} ${type}.`, 'danger');
    } finally {
      if (submit) submit.disabled = false;
    }
  },

  async editRoute(id) { await this.openEditor('route', id); },
  async editVehicle(id) { await this.openEditor('vehicle', id); },
  async editDriver(id) { await this.openEditor('driver', id); },
  async editStop(id) { await this.openEditor('stop', id); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    this.bindUi();   // ← new: bind UI event listeners
    window.APIRealtime?.register?.("transport_admin", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["transport"]);
  },
};

window.transportAdminController = transportAdminController;
function initializeTransportAdminPage() {
  transportAdminController.init().catch((error) => console.error('Transport administration failed to initialize:', error));
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeTransportAdminPage, { once: true });
} else {
  initializeTransportAdminPage();
}
