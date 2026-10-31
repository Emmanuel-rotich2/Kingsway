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
      const [routes, vehicles, drivers, stops] = await Promise.all([
        cache?.routes !== undefined ? cache.routes : this.API('GET', '/api/transport/all-routes'),
        cache?.vehicles !== undefined ? cache.vehicles : this.API('GET', '/api/transport/all-vehicles'),
        cache?.drivers !== undefined ? cache.drivers : this.API('GET', '/api/transport/all-drivers'),
        cache?.stops !== undefined ? cache.stops : this.API('GET', '/api/transport/all-stops'),
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
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="transportAdminController.editRoute(${Number(r.id)})"><i class="bi bi-pencil"></i></button></td>
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
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="transportAdminController.editVehicle(${Number(v.id)})"><i class="bi bi-pencil"></i></button></td>
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
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="transportAdminController.editDriver(${Number(d.id)})"><i class="bi bi-pencil"></i></button></td>
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
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="transportAdminController.editStop(${Number(s.id)})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderFormOptions() {
    const vehicles = this.state.vehicles;
    const drivers = this.state.drivers;
    const vehicleSelect = document.getElementById('traRouteVehicleSelect');
    const driverSelect = document.getElementById('traRouteDriverSelect');
    if (vehicleSelect) vehicleSelect.innerHTML = '<option value="">Select Vehicle</option>' + vehicles.map(v => `<option value="${v.id}">${this.esc(v.registration)}</option>`).join('');
    if (driverSelect) driverSelect.innerHTML = '<option value="">Select Driver</option>' + drivers.map(d => `<option value="${d.id}">${this.esc(d.name)}</option>`).join('');
  },

  fmtDate(v) { if (!v) return '—'; const dt = new Date(String(v).replace(' ', 'T')); return isNaN(dt) ? this.esc(v) : dt.toLocaleDateString(); },
  fmtTime(v) { if (!v) return '—'; return v.length > 5 ? v.substring(0, 5) : v; },

  async editRoute(id) { /* open edit modal */ },
  async editVehicle(id) { /* open edit modal */ },
  async editDriver(id) { /* open edit modal */ },
  async editStop(id) { /* open edit modal */ },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
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
