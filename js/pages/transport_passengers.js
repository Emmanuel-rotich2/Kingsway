/**
 * Transport Passengers Controller
 * Manages student transport assignments and passenger lists.
 */
const transportPassengersController = {
  state: { referenceCache: null, passengers: [], routes: [], vehicles: [], classes: [], streams: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"'); },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("transport", "transport-passengers-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Transport Passengers Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async load() {
    try {
      const cache = this.state.referenceCache;
      const [passengers, routes, vehicles, classes, streams] = await Promise.all([
        cache?.passengers !== undefined ? cache.passengers : this.API('GET', '/api/transport/passengers'),
        cache?.routes !== undefined ? cache.routes : this.API('GET', '/api/transport/all-routes'),
        cache?.vehicles !== undefined ? cache.vehicles : this.API('GET', '/api/transport/all-vehicles'),
        cache?.classes !== undefined ? cache.classes : this.API('GET', '/api/academic/classes-list'),
        cache?.streams !== undefined ? cache.streams : this.API('GET', '/api/academic/streams-list'),
      ]);
      this.state.passengers = Array.isArray(passengers?.data) ? passengers.data : (Array.isArray(passengers) ? passengers : []);
      this.state.routes = Array.isArray(routes?.data) ? routes.data : (Array.isArray(routes) ? routes : []);
      this.state.vehicles = Array.isArray(vehicles?.data) ? vehicles.data : (Array.isArray(vehicles) ? vehicles : []);
      this.state.classes = Array.isArray(classes?.data) ? classes.data : (Array.isArray(classes) ? classes : []);
      this.state.streams = Array.isArray(streams?.data) ? streams.data : (Array.isArray(streams) ? streams : []);
      this.renderPassengers();
      this.renderFilters();
      this.renderFormOptions();
    } catch (e) { console.error(e); }
  },

  getCache(key) { return this.state.referenceCache?.[key]; },

  renderPassengers() {
    const passengers = this.state.passengers;
    const body = document.getElementById('tpPassengersBody');
    if (!passengers.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No passengers assigned.</td></tr>'; return; }
    body.innerHTML = passengers.map(p => `<tr>
      <td class="small">${this.esc(p.admission_number || '—')}</td>
      <td class="small">${this.esc(p.name || '—')}</td>
      <td class="small">${this.esc(p.class_name || '—')}</td>
      <td class="small">${this.esc(p.route_name || '—')}</td>
      <td class="small">${this.esc(p.stop_name || '—')}</td>
      <td class="small"><span class="badge bg-${p.status === 'active' ? 'success' : 'secondary'}">${this.esc(p.status || '—')}</span></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="transportPassengersController.editPassenger(${p.id})"><i class="bi bi-pencil"></i></button></td>
    </tr>`).join('');
  },

  renderFilters() {
    const routes = this.state.routes;
    const classes = this.state.classes;
    const routeSelect = document.getElementById('tpRouteFilter');
    const classSelect = document.getElementById('tpClassFilter');
    if (routeSelect) routeSelect.innerHTML = '<option value="">All Routes</option>' + routes.map(r => `<option value="${r.id}">${this.esc(r.name)}</option>`).join('');
    if (classSelect) classSelect.innerHTML = '<option value="">All Classes</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
  },

  renderFormOptions() {
    const routes = this.state.routes;
    const vehicles = this.state.vehicles;
    const stops = this.getCache('stops') || [];
    const routeSelect = document.getElementById('tpRouteSelect');
    const vehicleSelect = document.getElementById('tpVehicleSelect');
    const stopSelect = document.getElementById('tpStopSelect');
    if (routeSelect) routeSelect.innerHTML = '<option value="">Select Route</option>' + routes.map(r => `<option value="${r.id}">${this.esc(r.name)}</option>`).join('');
    if (vehicleSelect) vehicleSelect.innerHTML = '<option value="">Select Vehicle</option>' + vehicles.map(v => `<option value="${v.id}">${this.esc(v.registration)}</option>`).join('');
    if (stopSelect) stopSelect.innerHTML = '<option value="">Select Stop</option>' + stops.map(s => `<option value="${s.id}">${this.esc(s.name)}</option>`).join('');
  },

  async assignPassenger() {
    const student = document.getElementById('tpStudentSelect')?.value;
    const route = document.getElementById('tpRouteSelect')?.value;
    const vehicle = document.getElementById('tpVehicleSelect')?.value;
    const stop = document.getElementById('tpStopSelect')?.value;
    if (!student || !route) { this.notify('Student and route required', 'warning'); return; }
    try {
      await this.API('POST', '/api/transport/passengers', { student_id: student, route_id: route, vehicle_id: vehicle, stop_id: stop });
      this.notify('Passenger assigned', 'success');
      document.getElementById('tpAssignForm')?.reset();
      await this.load();
    } catch (e) { this.notify('Assignment failed', 'danger'); }
  },

  async editPassenger(id) { /* open edit modal */ },

  notify(msg, type = 'info') { if (window.showNotification) window.showNotification(msg, type); else if (window.showToast) window.showToast(msg, type); else alert(msg); },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.load();
    window.APIRealtime?.register?.("transport_passengers", {
      refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await this.load(); }
    }, ["transport"]);
  },
};

window.transportPassengersController = transportPassengersController;
document.addEventListener('DOMContentLoaded', () => transportPassengersController.init().catch(() => {}));