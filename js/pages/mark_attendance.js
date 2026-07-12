/**
 * Mark Attendance Controller
 * Driver version - Mark passenger attendance for transport trips
 */
const MarkAttendanceController = {
  state: {
    passengers: [],
    routes: [],
    vehicles: [],
    attendance: {},
    selectedDate: new Date().toISOString().slice(0, 10),
  },

  ui: {},

  async init() {
    console.log("MarkAttendanceController: Initializing...");

    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.attachEvents();

    this.ui.attendanceDate.value = this.state.selectedDate;

    const user = window.AuthContext?.getUser() || {};
    this.ui.driverName.value = user.full_name || user.name || "Driver";

    console.log("MarkAttendanceController: Loading metadata...");
    await this.loadMeta();
    console.log("MarkAttendanceController: Initialization complete");
  },

  cacheDom() {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      attendanceDate: $("attendanceDate"),
      routeSelect: $("routeSelect"),
      vehicleSelect: $("vehicleSelect"),
      tripSession: $("tripSession"),
      driverName: $("driverName"),
      tripNotes: $("tripNotes"),
      loadPassengersBtn: $("loadPassengersBtn"),
      refreshBtn: $("refreshBtn"),
      saveAttendanceBtn: $("saveAttendanceBtn"),
      submitTripReportBtn: $("submitTripReportBtn"),
      printSheetBtn: $("printSheetBtn"),

      totalExpected: $("totalExpected"),
      markedPresent: $("markedPresent"),
      droppedOff: $("droppedOff"),
      absent: $("absent"),
      notRiding: $("notRiding"),
      pending: $("pending"),
      incidents: $("incidents"),

      attendanceLoading: $("attendanceLoading"),
      attendanceError: $("attendanceError"),
      attendanceForbidden: $("attendanceForbidden"),
      attendanceEmpty: $("attendanceEmpty"),
      attendanceCard: $("attendanceCard"),
      passengersTableBody: $("passengersTableBody"),
      selectAll: $("selectAll"),

      pickedUpCount: $("pickedUpCount"),
      droppedOffCount: $("droppedOffCount"),
      absentCount: $("absentCount"),
      notRidingCount: $("notRidingCount"),
      pendingCount: $("pendingCount"),

      markSelectedPickedUp: $("markSelectedPickedUp"),
      markSelectedDroppedOff: $("markSelectedDroppedOff"),
      markSelectedAbsent: $("markSelectedAbsent"),
      markSelectedNotRiding: $("markSelectedNotRiding"),
      clearSelected: $("clearSelected"),

      saveConfirmationModal: $("saveConfirmationModal"),
      confirmPickedUp: $("confirmPickedUp"),
      confirmDroppedOff: $("confirmDroppedOff"),
      confirmAbsent: $("confirmAbsent"),
      confirmNotRiding: $("confirmNotRiding"),
      confirmPending: $("confirmPending"),
      confirmSaveBtn: $("confirmSaveBtn"),
    };
  },

  attachEvents() {
    this.ui.loadPassengersBtn?.addEventListener("click", () => this.loadPassengers());
    this.ui.refreshBtn?.addEventListener("click", () => this.loadPassengers());
    this.ui.saveAttendanceBtn?.addEventListener("click", () => this.showSaveConfirmation());
    this.ui.printSheetBtn?.addEventListener("click", () => window.print());

    this.ui.markSelectedPickedUp?.addEventListener("click", () => this.markSelected("picked_up"));
    this.ui.markSelectedDroppedOff?.addEventListener("click", () => this.markSelected("dropped_off"));
    this.ui.markSelectedAbsent?.addEventListener("click", () => this.markSelected("absent"));
    this.ui.markSelectedNotRiding?.addEventListener("click", () => this.markSelected("not_riding"));
    this.ui.clearSelected?.addEventListener("click", () => this.clearSelected());

    this.ui.selectAll?.addEventListener("change", (e) => {
      document.querySelectorAll('.passenger-checkbox').forEach(cb => cb.checked = e.target.checked);
    });

    this.ui.confirmSaveBtn?.addEventListener("click", () => this.saveAttendance());
  },

  async loadMeta() {
    try {
      const response = await this.api("/students/transport-meta", "GET");
      const data = this.unwrap(response);

      this.state.routes = data.routes || [];
      this.state.vehicles = data.vehicles || [];

      this.fillSelect(this.ui.routeSelect, this.state.routes, "Select Route");
      this.fillSelect(this.ui.vehicleSelect, this.state.vehicles, "Select Vehicle");
    } catch (error) {
      console.error("Failed to load metadata:", error);
    }
  },

  async loadPassengers() {
    const date = this.ui.attendanceDate.value;
    const routeId = this.ui.routeSelect.value;
    const vehicleId = this.ui.vehicleSelect.value;
    const tripSession = this.ui.tripSession.value;

    if (!date || !routeId || !vehicleId || !tripSession) {
      this.notify("Please select date, route, vehicle, and trip session", "warning");
      return;
    }

    this.setLoading(true);

    try {
      const params = new URLSearchParams({
        date,
        route_id: routeId,
        vehicle_id: vehicleId,
        trip_session: tripSession,
      });

      const response = await this.api(`/students/transport-passengers?${params.toString()}`, "GET");
      const passengers = this.unwrap(response) || [];

      this.state.passengers = passengers;
      this.state.attendance = {};

      passengers.forEach(p => {
        this.state.attendance[p.student_id] = {
          status: p.today_status || "pending",
          time: "",
          notes: "",
        };
      });

      this.renderPassengers();
      this.updateSummary();
      this.ui.attendanceCard.style.display = "block";
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

  renderPassengers() {
    if (!this.state.passengers.length) {
      this.ui.passengersTableBody.innerHTML = `
        <tr>
          <td colspan="12" class="text-center text-muted py-4">
            No passengers found for this trip.
          </td>
        </tr>`;
      return;
    }

    this.ui.passengersTableBody.innerHTML = this.state.passengers
      .map((p) => {
        const att = this.state.attendance[p.student_id] || {};
        return `
          <tr data-student-id="${p.student_id}">
            <td><input type="checkbox" class="passenger-checkbox" data-student-id="${p.student_id}"></td>
            <td>${this.escape(p.admission_no || "-")}</td>
            <td><strong>${this.escape(p.full_name || "-")}</strong></td>
            <td>${this.escape(p.class_name || "-")}</td>
            <td>${this.escape(p.stream_name || "-")}</td>
            <td>${this.escape(p.pickup_point || "-")}</td>
            <td>${this.escape(p.dropoff_point || "-")}</td>
            <td><i class="bi bi-check-circle text-success"></i></td>
            <td>
              <select class="form-select form-select-sm status-select" data-student-id="${p.student_id}">
                <option value="pending" ${att.status === "pending" ? "selected" : ""}>Pending</option>
                <option value="picked_up" ${att.status === "picked_up" ? "selected" : ""}>Picked Up</option>
                <option value="dropped_off" ${att.status === "dropped_off" ? "selected" : ""}>Dropped Off</option>
                <option value="absent" ${att.status === "absent" ? "selected" : ""}>Absent</option>
                <option value="excused" ${att.status === "excused" ? "selected" : ""}>Excused</option>
                <option value="not_riding" ${att.status === "not_riding" ? "selected" : ""}>Not Riding</option>
              </select>
            </td>
            <td>
              <input type="time" class="form-control form-control-sm time-input" data-student-id="${p.student_id}" value="${att.time || ""}">
            </td>
            <td>
              <input type="text" class="form-control form-control-sm notes-input" data-student-id="${p.student_id}" value="${this.escape(att.notes || "")}" placeholder="Notes">
            </td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="MarkAttendanceController.markOne(${p.student_id}, 'picked_up')">
                <i class="bi bi-arrow-up"></i>
              </button>
            </td>
          </tr>`;
      })
      .join("");

    // Attach event listeners to dynamic elements
    document.querySelectorAll('.status-select').forEach(select => {
      select.addEventListener('change', (e) => {
        const studentId = e.target.dataset.studentId;
        if (this.state.attendance[studentId]) {
          this.state.attendance[studentId].status = e.target.value;
          this.updateSummary();
        }
      });
    });

    document.querySelectorAll('.time-input').forEach(input => {
      input.addEventListener('change', (e) => {
        const studentId = e.target.dataset.studentId;
        if (this.state.attendance[studentId]) {
          this.state.attendance[studentId].time = e.target.value;
        }
      });
    });

    document.querySelectorAll('.notes-input').forEach(input => {
      input.addEventListener('change', (e) => {
        const studentId = e.target.dataset.studentId;
        if (this.state.attendance[studentId]) {
          this.state.attendance[studentId].notes = e.target.value;
        }
      });
    });
  },

  updateSummary() {
    const counts = {
      picked_up: 0,
      dropped_off: 0,
      absent: 0,
      not_riding: 0,
      pending: 0,
    };

    Object.values(this.state.attendance).forEach(att => {
      if (counts[att.status] !== undefined) {
        counts[att.status]++;
      }
    });

    this.ui.totalExpected.textContent = this.state.passengers.length;
    this.ui.markedPresent.textContent = counts.picked_up + counts.dropped_off;
    this.ui.droppedOff.textContent = counts.dropped_off;
    this.ui.absent.textContent = counts.absent;
    this.ui.notRiding.textContent = counts.not_riding;
    this.ui.pending.textContent = counts.pending;

    this.ui.pickedUpCount.textContent = `Picked Up: ${counts.picked_up}`;
    this.ui.droppedOffCount.textContent = `Dropped Off: ${counts.dropped_off}`;
    this.ui.absentCount.textContent = `Absent: ${counts.absent}`;
    this.ui.notRidingCount.textContent = `Not Riding: ${counts.not_riding}`;
    this.ui.pendingCount.textContent = `Pending: ${counts.pending}`;
  },

  markSelected(status) {
    document.querySelectorAll('.passenger-checkbox:checked').forEach(cb => {
      const studentId = cb.dataset.studentId;
      if (this.state.attendance[studentId]) {
        this.state.attendance[studentId].status = status;
        const select = document.querySelector(`.status-select[data-student-id="${studentId}"]`);
        if (select) select.value = status;
      }
    });
    this.updateSummary();
  },

  clearSelected() {
    document.querySelectorAll('.passenger-checkbox:checked').forEach(cb => {
      cb.checked = false;
    });
  },

  markOne(studentId, status) {
    if (this.state.attendance[studentId]) {
      this.state.attendance[studentId].status = status;
      this.state.attendance[studentId].time = new Date().toTimeString().slice(0, 5);
      const select = document.querySelector(`.status-select[data-student-id="${studentId}"]`);
      const timeInput = document.querySelector(`.time-input[data-student-id="${studentId}"]`);
      if (select) select.value = status;
      if (timeInput) timeInput.value = this.state.attendance[studentId].time;
      this.updateSummary();
    }
  },

  showSaveConfirmation() {
    const counts = {
      picked_up: 0,
      dropped_off: 0,
      absent: 0,
      not_riding: 0,
      pending: 0,
    };

    Object.values(this.state.attendance).forEach(att => {
      if (counts[att.status] !== undefined) {
        counts[att.status]++;
      }
    });

    this.ui.confirmPickedUp.textContent = counts.picked_up;
    this.ui.confirmDroppedOff.textContent = counts.dropped_off;
    this.ui.confirmAbsent.textContent = counts.absent;
    this.ui.confirmNotRiding.textContent = counts.not_riding;
    this.ui.confirmPending.textContent = counts.pending;

    if (typeof bootstrap !== "undefined" && this.ui.saveConfirmationModal) {
      const modalInstance = new bootstrap.Modal(this.ui.saveConfirmationModal);
      modalInstance.show();
    }
  },

  async saveAttendance() {
    const date = this.ui.attendanceDate.value;
    const routeId = this.ui.routeSelect.value;
    const vehicleId = this.ui.vehicleSelect.value;
    const tripSession = this.ui.tripSession.value;

    const records = Object.entries(this.state.attendance).map(([studentId, att]) => ({
      student_id: parseInt(studentId),
      status: att.status,
      marked_time: att.time,
      notes: att.notes,
    }));

    const data = {
      attendance_date: date,
      route_id: parseInt(routeId),
      vehicle_id: parseInt(vehicleId),
      trip_session: tripSession,
      records,
    };

    try {
      const response = await this.api("/students/transport-attendance", "POST", data);
      this.notify("Attendance saved successfully", "success");
      if (typeof bootstrap !== "undefined" && this.ui.saveConfirmationModal) {
        const modalInstance = bootstrap.Modal.getInstance(this.ui.saveConfirmationModal);
        modalInstance?.hide();
      }
    } catch (error) {
      this.notify(error.message || "Failed to save attendance", "error");
    }
  },

  setLoading(loading) {
    this.ui.attendanceLoading?.classList.toggle("d-none", !loading);
    this.ui.attendanceError?.classList.add("d-none");
    this.ui.attendanceForbidden?.classList.add("d-none");
  },

  showError(message) {
    if (!this.ui.attendanceError) return;
    this.ui.attendanceError.textContent = message;
    this.ui.attendanceError.classList.remove("d-none");
  },

  showForbidden() {
    this.ui.attendanceLoading?.classList.add("d-none");
    this.ui.attendanceError?.classList.add("d-none");
    this.ui.attendanceEmpty?.classList.add("d-none");
    this.ui.attendanceForbidden?.classList.remove("d-none");
  },

  fillSelect(select, items, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    (items || []).forEach((item) => {
      const option = document.createElement("option");
      option.value = item.id ?? item.value ?? "";
      option.textContent = item.name || item.route_name || item.vehicle_name || item.label || option.value;
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
  MarkAttendanceController.init(),
);

window.MarkAttendanceController = MarkAttendanceController;
