/**
 * Transport Passengers Controller
 * Driver's passenger list and route dashboard
 */
const TransportPassengersController = {
  state: {
    passengers: [],
    summary: {},
    routes: [],
    vehicles: [],
    classes: [],
    streams: [],
    selectedStudentId: null,
    selectedDate: new Date().toISOString().slice(0, 10),
  },

  ui: {},

  async init() {
    console.log("TransportPassengersController: Initializing...");

    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.attachEvents();

    this.ui.dateFilter.value = this.state.selectedDate;

    console.log("TransportPassengersController: Loading metadata...");
    await this.loadMeta();
    console.log("TransportPassengersController: Loading summary...");
    await this.loadSummary();
    console.log("TransportPassengersController: Loading passengers...");
    await this.loadPassengers();
    console.log("TransportPassengersController: Initialization complete");
  },

  cacheDom() {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      dateFilter: $("dateFilter"),
      routeFilter: $("routeFilter"),
      vehicleFilter: $("vehicleFilter"),
      tripSessionFilter: $("tripSessionFilter"),
      classFilter: $("classFilter"),
      streamFilter: $("streamFilter"),
      genderFilter: $("genderFilter"),
      transportStatusFilter: $("transportStatusFilter"),
      attendanceStatusFilter: $("attendanceStatusFilter"),
      searchBox: $("searchBox"),
      applyFiltersBtn: $("applyFiltersBtn"),
      resetFiltersBtn: $("resetFiltersBtn"),
      refreshBtn: $("refreshBtn"),
      printListBtn: $("printListBtn"),
      exportSheetBtn: $("exportSheetBtn"),
      reportIncidentBtn: $("reportIncidentBtn"),

      totalPassengers: $("totalPassengers"),
      expectedToday: $("expectedToday"),
      pickedUp: $("pickedUp"),
      droppedOff: $("droppedOff"),
      absentNotRiding: $("absentNotRiding"),
      pendingPickup: $("pendingPickup"),
      emergencyAlerts: $("emergencyAlerts"),
      routeVehicle: $("routeVehicle"),

      passengersLoading: $("passengersLoading"),
      passengersError: $("passengersError"),
      passengersForbidden: $("passengersForbidden"),
      passengersEmpty: $("passengersEmpty"),
      passengersTableBody: $("passengersTableBody"),

      modal: $("passengerModal"),
      modalLoading: $("modalLoading"),
      modalError: $("modalError"),
      modalPassengerContent: $("modalPassengerContent"),
      markPickedUpBtn: $("markPickedUpBtn"),
      markDroppedOffBtn: $("markDroppedOffBtn"),
      markAbsentBtn: $("markAbsentBtn"),
      addTransportNoteBtn: $("addTransportNoteBtn"),

      incidentModal: $("incidentModal"),
      incidentForm: $("incidentForm"),
      incidentStudent: $("incidentStudent"),
      incidentDateTime: $("incidentDateTime"),
      incidentRoute: $("incidentRoute"),
      incidentVehicle: $("incidentVehicle"),
      incidentType: $("incidentType"),
      incidentDescription: $("incidentDescription"),
      incidentActionTaken: $("incidentActionTaken"),
      incidentEscalate: $("incidentEscalate"),
      incidentNotes: $("incidentNotes"),
      saveIncidentBtn: $("saveIncidentBtn"),
    };
  },

  attachEvents() {
    this.ui.applyFiltersBtn?.addEventListener("click", () => {
      this.loadSummary();
      this.loadPassengers();
    });
    this.ui.resetFiltersBtn?.addEventListener("click", () => this.resetFilters());
    this.ui.refreshBtn?.addEventListener("click", () => {
      this.loadSummary();
      this.loadPassengers();
    });
    this.ui.printListBtn?.addEventListener("click", () => this.printPassengerList());
    this.ui.exportSheetBtn?.addEventListener("click", () => this.exportSheet());
    this.ui.reportIncidentBtn?.addEventListener("click", () => this.openIncidentModal());

    this.ui.searchBox?.addEventListener(
      "input",
      this.debounce(() => this.loadPassengers(), 400)
    );

    this.ui.classFilter?.addEventListener("change", () => {
      this.updateStreamsFilter();
      this.loadPassengers();
    });

    this.ui.streamFilter?.addEventListener("change", () => this.loadPassengers());
    this.ui.dateFilter?.addEventListener("change", () => {
      this.state.selectedDate = this.ui.dateFilter.value;
      this.loadSummary();
      this.loadPassengers();
    });
    this.ui.routeFilter?.addEventListener("change", () => this.loadPassengers());
    this.ui.vehicleFilter?.addEventListener("change", () => this.loadPassengers());
    this.ui.tripSessionFilter?.addEventListener("change", () => this.loadPassengers());
    this.ui.genderFilter?.addEventListener("change", () => this.loadPassengers());
    this.ui.transportStatusFilter?.addEventListener("change", () => this.loadPassengers());
    this.ui.attendanceStatusFilter?.addEventListener("change", () => this.loadPassengers());

    this.ui.saveIncidentBtn?.addEventListener("click", () => this.saveIncident());
  },

  async loadMeta() {
    try {
      const response = await this.api("/students/transport-meta", "GET");
      const data = this.unwrap(response);

      this.state.routes = data.routes || [];
      this.state.vehicles = data.vehicles || [];
      this.state.classes = data.classes || [];
      this.state.streams = data.streams || [];

      this.fillSelect(this.ui.routeFilter, this.state.routes, "All Routes");
      this.fillSelect(this.ui.vehicleFilter, this.state.vehicles, "All Vehicles");
      this.fillSelect(this.ui.classFilter, this.state.classes, "All Classes");
      this.fillSelect(this.ui.streamFilter, this.state.streams, "All Streams");
      this.fillSelect(this.ui.incidentRoute, this.state.routes, "Select Route");
      this.fillSelect(this.ui.incidentVehicle, this.state.vehicles, "Select Vehicle");
    } catch (error) {
      console.error("Failed to load metadata:", error);
      if (error.message.includes("forbidden") || error.message.includes("permission")) {
        this.showForbidden();
      }
    }
  },

  updateStreamsFilter() {
    const classId = this.ui.classFilter?.value || "";
    const filtered = classId
      ? this.state.streams.filter((s) => String(s.class_id) === String(classId))
      : this.state.streams;
    this.fillSelect(this.ui.streamFilter, filtered, "All Streams");
  },

  async loadSummary() {
    try {
      const params = this.getParams();
      const response = await this.api(`/students/transport-summary?${params.toString()}`, "GET");
      this.state.summary = this.unwrap(response) || {};
      this.renderSummary();
    } catch (error) {
      console.error("Failed to load summary:", error);
    }
  },

  async loadPassengers() {
    this.setLoading(true);

    try {
      const params = this.getParams();
      const response = await this.api(`/students/transport-passengers?${params.toString()}`, "GET");
      const passengers = this.unwrap(response) || [];

      this.state.passengers = passengers;
      this.renderPassengers();
    } catch (error) {
      console.error("Failed to load passengers:", error);
      if (error.message.includes("forbidden") || error.message.includes("permission")) {
        this.showForbidden();
      } else {
        this.showError(error.message || "Failed to load passengers");
      }
    } finally {
      this.setLoading(false);
    }
  },

  getParams() {
    const params = new URLSearchParams();
    const filters = {
      date: this.ui.dateFilter?.value || "",
      route_id: this.ui.routeFilter?.value || "",
      vehicle_id: this.ui.vehicleFilter?.value || "",
      trip_session: this.ui.tripSessionFilter?.value || "",
      class_id: this.ui.classFilter?.value || "",
      stream_id: this.ui.streamFilter?.value || "",
      gender: this.ui.genderFilter?.value || "",
      transport_status: this.ui.transportStatusFilter?.value || "",
      attendance_status: this.ui.attendanceStatusFilter?.value || "",
      search: this.ui.searchBox?.value.trim() || "",
    };

    Object.entries(filters).forEach(([key, val]) => {
      if (val !== "") params.set(key, val);
    });

    return params;
  },

  renderSummary() {
    const s = this.state.summary;
    this.ui.totalPassengers.textContent = s.total_passengers ?? 0;
    this.ui.expectedToday.textContent = s.expected_today ?? 0;
    this.ui.pickedUp.textContent = s.picked_up ?? 0;
    this.ui.droppedOff.textContent = s.dropped_off ?? 0;
    this.ui.absentNotRiding.textContent = (s.absent ?? 0) + (s.not_riding ?? 0);
    this.ui.pendingPickup.textContent = s.pending ?? 0;
    this.ui.emergencyAlerts.textContent = s.emergency_alerts ?? 0;
    this.ui.routeVehicle.textContent = `${s.route_name || "-"} / ${s.vehicle_name || "-"}`;
  },

  renderPassengers() {
    if (!this.state.passengers.length) {
      this.ui.passengersTableBody.innerHTML = `
        <tr>
          <td colspan="12" class="text-center text-muted py-4">
            No passengers found.
          </td>
        </tr>`;
      return;
    }

    this.ui.passengersTableBody.innerHTML = this.state.passengers
      .map((p) => {
        const statusColors = {
          pending: "secondary",
          picked_up: "success",
          dropped_off: "info",
          absent: "danger",
          excused: "warning",
          not_riding: "dark",
        };

        return `
          <tr>
            <td>${this.escape(p.admission_no || "-")}</td>
            <td><strong>${this.escape(p.full_name || "-")}</strong></td>
            <td>${this.escape(p.class_name || "-")}</td>
            <td>${this.escape(p.stream_name || "-")}</td>
            <td>${this.escape(p.gender || "-")}</td>
            <td>${this.escape(p.route_name || "-")}</td>
            <td>${this.escape(p.vehicle_name || "-")}</td>
            <td>${this.escape(p.pickup_point || "-")}</td>
            <td>${this.escape(p.dropoff_point || "-")}</td>
            <td>${this.escape(p.guardian_phone || "-")}</td>
            <td><span class="badge bg-${statusColors[p.today_status] || "secondary"}">${this.escape(p.today_status || "-")}</span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="TransportPassengersController.viewPassenger(${p.student_id})">
                <i class="bi bi-eye"></i> View
              </button>
            </td>
          </tr>`;
      })
      .join("");

    this.ui.passengersEmpty.classList.toggle("d-none", this.state.passengers.length > 0);
  },

  resetFilters() {
    [
      this.ui.dateFilter,
      this.ui.routeFilter,
      this.ui.vehicleFilter,
      this.ui.tripSessionFilter,
      this.ui.classFilter,
      this.ui.streamFilter,
      this.ui.genderFilter,
      this.ui.transportStatusFilter,
      this.ui.attendanceStatusFilter,
      this.ui.searchBox,
    ].forEach((el) => {
      if (el) el.value = "";
    });

    this.ui.dateFilter.value = new Date().toISOString().slice(0, 10);
    this.state.selectedDate = this.ui.dateFilter.value;
    this.updateStreamsFilter();
    this.loadSummary();
    this.loadPassengers();
  },

  async viewPassenger(studentId) {
    this.state.selectedStudentId = studentId;

    if (typeof bootstrap !== "undefined" && this.ui.modal) {
      const modalInstance = new bootstrap.Modal(this.ui.modal);
      modalInstance.show();
    }

    await this.loadPassengerProfile(studentId);
  },

  async loadPassengerProfile(studentId) {
    this.setModalLoading(true);

    try {
      const response = await this.api(`/students/transport-passenger/${studentId}`, "GET");
      const data = this.unwrap(response);

      this.renderPassengerProfile(data);
    } catch (error) {
      console.error("Failed to load passenger profile:", error);
      this.showModalError(error.message || "Failed to load passenger profile");
    } finally {
      this.setModalLoading(false);
    }
  },

  renderPassengerProfile(data) {
    const student = data.student || {};
    const transport = data.transport || {};
    const attendance = data.attendance || [];
    const notes = data.notes || [];

    this.ui.modalPassengerContent.innerHTML = `
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Student Profile</h5>
          <p><strong>Name:</strong> ${this.escape(student.first_name || "")} ${this.escape(student.last_name || "")}</p>
          <p><strong>Admission No:</strong> ${this.escape(student.admission_no || "-")}</p>
          <p><strong>Class:</strong> ${this.escape(data.class_name || "-")}</p>
          <p><strong>Stream:</strong> ${this.escape(data.stream_name || "-")}</p>
          <p><strong>Gender:</strong> ${this.escape(student.gender || "-")}</p>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Transport Information</h5>
          <p><strong>Route:</strong> ${this.escape(data.route_name || "-")}</p>
          <p><strong>Vehicle:</strong> ${this.escape(data.vehicle_name || "-")}</p>
          <p><strong>Pickup Point:</strong> ${this.escape(transport.pickup_point || "-")}</p>
          <p><strong>Drop-off Point:</strong> ${this.escape(transport.dropoff_point || "-")}</p>
          <p><strong>Pickup Time:</strong> ${this.escape(transport.pickup_time || "-")}</p>
          <p><strong>Drop-off Time:</strong> ${this.escape(transport.dropoff_time || "-")}</p>
          <p><strong>Status:</strong> ${this.escape(transport.status || "-")}</p>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Guardian Contact</h5>
          <p><strong>Emergency Contact:</strong> ${this.escape(data.guardian_phone || "-")}</p>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Recent Attendance (${attendance.length})</h5>
          ${attendance.length === 0 ? '<p class="text-muted">No attendance records.</p>' : ''}
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Date</th>
                <th>Session</th>
                <th>Status</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              ${attendance.slice(0, 5).map(a => `
                <tr>
                  <td>${this.escape(a.date || "-")}</td>
                  <td>${this.escape(a.trip_session || "-")}</td>
                  <td>${this.escape(a.status || "-")}</td>
                  <td>${this.escape(a.marked_time || "-")}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Transport Notes (${notes.length})</h5>
          ${notes.length === 0 ? '<p class="text-muted">No transport notes.</p>' : ''}
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Type</th>
                <th>Note</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              ${notes.slice(0, 5).map(n => `
                <tr>
                  <td>${this.escape(n.note_type || "-")}</td>
                  <td>${this.escape(n.note || "-")}</td>
                  <td>${this.escape(n.created_at || "-")}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  },

  openIncidentModal() {
    this.ui.incidentForm.reset();
    this.ui.incidentDateTime.value = new Date().toISOString().slice(0, 16);
    if (typeof bootstrap !== "undefined" && this.ui.incidentModal) {
      const modalInstance = new bootstrap.Modal(this.ui.incidentModal);
      modalInstance.show();
    }
  },

  async saveIncident() {
    const formData = {
      student_id: this.ui.incidentStudent.value || null,
      route_id: this.ui.incidentRoute.value || null,
      vehicle_id: this.ui.incidentVehicle.value || null,
      incident_datetime: this.ui.incidentDateTime.value,
      incident_type: this.ui.incidentType.value,
      description: this.ui.incidentDescription.value,
      action_taken: this.ui.incidentActionTaken.value,
      escalated: this.ui.incidentEscalate.value,
      notes: this.ui.incidentNotes.value,
    };

    try {
      const response = await this.api("/students/transport-incident", "POST", formData);
      this.notify("Incident reported successfully", "success");
      if (typeof bootstrap !== "undefined" && this.ui.incidentModal) {
        const modalInstance = bootstrap.Modal.getInstance(this.ui.incidentModal);
        modalInstance?.hide();
      }
      this.loadSummary();
    } catch (error) {
      this.notify(error.message || "Failed to report incident", "error");
    }
  },

  exportSheet() {
    if (!this.state.passengers.length) {
      this.notify("No data to export", "warning");
      return;
    }

    const columns = [
      { key: 'admission_no', label: 'Adm No' },
      { key: 'full_name', label: 'Student Name' },
      { key: 'class_name', label: 'Class' },
      { key: 'stream_name', label: 'Stream' },
      { key: 'gender', label: 'Gender' },
      { key: 'route_name', label: 'Route' },
      { key: 'vehicle_name', label: 'Vehicle' },
      { key: 'pickup_point', label: 'Pickup Point' },
      { key: 'dropoff_point', label: 'Drop-off Point' },
      { key: 'guardian_phone', label: 'Guardian Contact' },
      { key: 'today_status', label: 'Today Status' }
    ];

    window.PrintManager.exportToCSV({
      columns: columns,
      rows: this.state.passengers,
      filename: 'transport_sheet'
    });
  },

  printPassengerList() {
    if (!this.state.passengers.length) {
      this.notify("No data to print", "warning");
      return;
    }

    const filters = {
      'Date': this.state.selectedDate,
      'Route': this.ui.routeFilter?.options[this.ui.routeFilter.selectedIndex]?.text || 'All',
      'Vehicle': this.ui.vehicleFilter?.options[this.ui.vehicleFilter.selectedIndex]?.text || 'All',
      'Class': this.ui.classFilter?.options[this.ui.classFilter.selectedIndex]?.text || 'All',
      'Stream': this.ui.streamFilter?.options[this.ui.streamFilter.selectedIndex]?.text || 'All'
    };

    // Remove empty filters
    Object.keys(filters).forEach(key => {
      if (filters[key] === 'All' || !filters[key]) {
        delete filters[key];
      }
    });

    const columns = [
      { key: 'admission_no', label: 'Adm No' },
      { key: 'full_name', label: 'Student Name' },
      { key: 'class_name', label: 'Class' },
      { key: 'stream_name', label: 'Stream' },
      { key: 'gender', label: 'Gender' },
      { key: 'route_name', label: 'Route' },
      { key: 'vehicle_name', label: 'Vehicle' },
      { key: 'pickup_point', label: 'Pickup Point' },
      { key: 'dropoff_point', label: 'Drop-off Point' },
      { key: 'guardian_phone', label: 'Guardian Contact' },
      { key: 'today_status', label: 'Today Status' }
    ];

    window.PrintManager.printTable({
      title: 'Transport Passenger List',
      subtitle: 'Daily Transport Manifest',
      columns: columns,
      rows: this.state.passengers,
      summary: {
        'Total Passengers': this.state.passengers.length,
        'Date': this.state.selectedDate,
        'Generated Date': new Date().toLocaleDateString()
      },
      filters: filters,
      orientation: 'landscape',
      paperSize: 'A4',
      reportCode: 'TRN-' + this.state.selectedDate.replace(/-/g, ''),
      signatureSection: [
        { label: 'Driver' },
        { label: 'Transport Manager' }
      ]
    });
  },

  setLoading(loading) {
    this.ui.passengersLoading?.classList.toggle("d-none", !loading);
    this.ui.passengersError?.classList.add("d-none");
    this.ui.passengersForbidden?.classList.add("d-none");
  },

  showError(message) {
    if (!this.ui.passengersError) return;
    this.ui.passengersError.textContent = message;
    this.ui.passengersError.classList.remove("d-none");
  },

  showForbidden() {
    this.ui.passengersLoading?.classList.add("d-none");
    this.ui.passengersError?.classList.add("d-none");
    this.ui.passengersEmpty?.classList.add("d-none");
    this.ui.passengersForbidden?.classList.remove("d-none");
  },

  setModalLoading(loading) {
    this.ui.modalLoading?.classList.toggle("d-none", !loading);
    this.ui.modalError?.classList.add("d-none");
    this.ui.modalPassengerContent?.classList.toggle("opacity-50", loading);
  },

  showModalError(message) {
    if (!this.ui.modalError) return;
    this.ui.modalError.textContent = message;
    this.ui.modalError.classList.remove("d-none");
  },

  fillSelect(select, items, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    (items || []).forEach((item) => {
      const option = document.createElement("option");
      option.value = item.id ?? item.value ?? "";
      option.textContent = item.name || item.route_name || item.vehicle_name || item.class_name || item.stream_name || item.label || option.value;
      select.appendChild(option);
    });
  },

  api: async function (endpoint, method = "GET", data = null) {
    if (window.API && typeof window.API.apiCall === "function") {
      return window.API.apiCall(endpoint, method, data);
    }

    const base = window.APP_BASE || "";
    const url = `${base}/api${endpoint.startsWith("/") ? endpoint : `/${endpoint}`}`;

    const options = { method, headers: {} };

    if (data) {
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);
    const json = await response.json().catch(() => ({}));

    if (!response.ok || json.success === false) {
      throw new Error(json.message || json.error || "Request failed.");
    }

    return json;
  },

  unwrap(response) {
    if (!response) return {};
    if (response.data && response.data.data !== undefined)
      return response.data.data;
    if (response.data !== undefined) return response.data;
    return response;
  },

  escape(value) {
    return String(value ?? "").replace(
      /[&<>"']/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#039;",
        })[char]
    );
  },

  debounce(fn, delay) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  },

  notify(message, type = "info") {
    if (typeof showNotification === "function") {
      showNotification(message, type);
      return;
    }

    if (window.API && typeof window.API.showNotification === "function") {
      window.API.showNotification(message, type);
      return;
    }

    alert(message);
  },
};

document.addEventListener("DOMContentLoaded", () =>
  TransportPassengersController.init(),
);

window.TransportPassengersController = TransportPassengersController;
