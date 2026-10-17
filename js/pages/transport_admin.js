/**
 * Transport Admin Controller
 * Administrative interface for transport management.
 */
const transportAdminController = {
  state: { referenceCache: null, routes: [], vehicles: [], drivers: [], stops: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("transport", "transport-admin-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Transport Admin Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
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
    const body = document.getElementById('traRoutesBody');
    if (!routes.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No routes defined.</td></tr>'; return; }
    body.innerHTML = routes.map(r => `<tr>
      <td class="small">${this.esc(r.name || '—')}</td>
      <td class="small">${this.esc(r.vehicle_reg || '—')}</td>
      <td class="small">${this.esc(r.driver_name || '—')}</td>
      <td class="small">${r.stops_count || 0} stops</td>
      <td class="small"><span class="badge bg-${r.is_active ? 'success' : 'secondary'}">${r.is_active ? 'Active' : 'Inactive'}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="transportAdminController.editRoute(${r.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderVehicles() {
    const vehicles = this.state.vehicles;
    const body = document.getElementById('traVehiclesBody');
    if (!vehicles.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No vehicles registered.</td></tr>'; return; }
    body.innerHTML = vehicles.map(v => `<tr>
      <td class="small">${this.esc(v.registration || '—')}</td>
      <td class="small">${this.esc(v.type || '—')}</td>
      <td class="small">${v.capacity || 0} seats</td>
      <td class="small">${this.fmtDate(v.last_service)}</td>
      <td class="small"><span class="badge bg-${v.status === 'active' ? 'success' : v.status === 'maintenance' ? 'warning' : 'secondary'}">${this.esc(v.status || '—')}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="transportAdminController.editVehicle(${v.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderDrivers() {
    const drivers = this.state.drivers;
    const body = document.getElementById('traDriversBody');
    if (!drivers.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No drivers registered.</td></tr>'; return; }
    body.innerHTML = drivers.map(d => `<tr>
      <td class="small">${this.esc(d.name || '—')}</td>
      <td class="small">${this.esc(d.license_number || '—')}</td>
      <td class="small">${this.esc(d.phone || '—')}</td>
      <td class="small"><span class="badge bg-${d.status === 'active' ? 'success' : 'secondary'}">${this.esc(d.status || '—')}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="transportAdminController.editDriver(${d.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderStops() {
    const stops = this.state.stops;
    const body = document.getElementById('traStopsBody');
    if (!stops.length) { body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No stops defined.</td></tr>'; return; }
    body.innerHTML = stops.map(s => `<tr>
      <td class="small">${this.esc(s.name || '—')}</td>
      <td class="small">${this.esc(s.location || '—')}</td>
      <td class="small">${this.fmtTime(s.pickup_time)}</td>
      <td class="small"><span class="badge bg-${s.is_active ? 'success' : 'secondary'}">${s.is_active ? 'Active' : 'Inactive'}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="transportAdminController.editStop(${s.id})"><i class="bi bi-pencil"></i></button></td>
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
document.addEventListener('DOMContentLoaded', () => transportAdminController.init().catch(() => {}));