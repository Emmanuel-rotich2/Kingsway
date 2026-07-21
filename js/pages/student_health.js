/**
 * Student Health Controller
 * Manages health records, clinic visits, medication, allergies, and emergency information
 */
const StudentHealthController = {
  state: {
    records: [],
    academicYears: [],
    classes: [],
    streams: [],
    selectedRecordId: null,
  },

  ui: {},

  async init() {
    console.log("StudentHealthController: Initializing...");

    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.attachEvents();

    console.log("StudentHealthController: Loading metadata...");
    await this.loadMeta();
    console.log("StudentHealthController: Loading records...");
    await this.loadRecords();
    console.log("StudentHealthController: Initialization complete");
  },

  cacheDom() {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      academicYearFilter: $("academicYearFilter"),
      classFilter: $("classFilter"),
      streamFilter: $("streamFilter"),
      healthCategoryFilter: $("healthCategoryFilter"),
      alertStatusFilter: $("alertStatusFilter"),
      severityFilter: $("severityFilter"),
      searchBox: $("searchBox"),
      applyFiltersBtn: $("applyFiltersBtn"),
      resetFiltersBtn: $("resetFiltersBtn"),
      refreshBtn: $("refreshBtn"),
      exportBtn: $("exportBtn"),
      addRecordBtn: $("addRecordBtn"),

      totalRecords: $("totalRecords"),
      activeAlerts: $("activeAlerts"),
      clinicVisits: $("clinicVisits"),
      allergiesCount: $("allergiesCount"),
      medicationCount: $("medicationCount"),
      emergencyCount: $("emergencyCount"),

      recordsLoading: $("recordsLoading"),
      recordsError: $("recordsError"),
      recordsEmpty: $("recordsEmpty"),
      recordsTableBody: $("recordsTableBody"),

      modal: $("recordModal"),
      modalLoading: $("modalLoading"),
      modalError: $("modalError"),
      modalRecordContent: $("modalRecordContent"),
      modalRecordId: $("modalRecordId"),
      addClinicVisitBtn: $("addClinicVisitBtn"),
      addMedicationBtn: $("addMedicationBtn"),
      markReviewedBtn: $("markReviewedBtn"),
    };
  },

  attachEvents() {
    this.ui.applyFiltersBtn?.addEventListener("click", () => this.loadRecords());
    this.ui.resetFiltersBtn?.addEventListener("click", () => this.resetFilters());
    this.ui.refreshBtn?.addEventListener("click", () => this.loadRecords());
    this.ui.exportBtn?.addEventListener("click", () => this.exportData());

    this.ui.searchBox?.addEventListener(
      "input",
      this.debounce(() => this.loadRecords(), 400)
    );

    this.ui.classFilter?.addEventListener("change", () => {
      this.updateStreamsFilter();
      this.loadRecords();
    });

    this.ui.streamFilter?.addEventListener("change", () => this.loadRecords());
    this.ui.academicYearFilter?.addEventListener("change", () => this.loadRecords());
    this.ui.healthCategoryFilter?.addEventListener("change", () => this.loadRecords());
    this.ui.alertStatusFilter?.addEventListener("change", () => this.loadRecords());
    this.ui.severityFilter?.addEventListener("change", () => this.loadRecords());
  },

  async loadMeta() {
    try {
      const response = await this.api("/students/health-meta", "GET");
      const data = this.unwrap(response);

      this.state.academicYears = data.academic_years || [];
      this.state.classes = data.classes || [];
      this.state.streams = data.streams || [];

      this.fillSelect(this.ui.academicYearFilter, this.state.academicYears, "All Years");
      this.fillSelect(this.ui.classFilter, this.state.classes, "All Classes");
      this.updateStreamsFilter();
    } catch (error) {
      console.error("Failed to load metadata:", error);
    }
  },

  updateStreamsFilter() {
    const classId = this.ui.classFilter?.value || "";
    const filtered = classId
      ? this.state.streams.filter((s) => String(s.class_id) === String(classId))
      : this.state.streams;
    this.fillSelect(this.ui.streamFilter, filtered, "All Streams");
  },

  async loadRecords() {
    this.setLoading(true);

    try {
      const params = this.getParams();
      const response = await this.api(`/students/health-records?${params.toString()}`, "GET");
      const records = this.unwrap(response) || [];

      this.state.records = records;
      this.renderRecords();
    } catch (error) {
      console.error("Failed to load records:", error);
      this.showError(error.message || "Failed to load health records");
    } finally {
      this.setLoading(false);
    }
  },

  getParams() {
    const params = new URLSearchParams();
    const filters = {
      academic_year: this.ui.academicYearFilter?.value || "",
      class_id: this.ui.classFilter?.value || "",
      stream_id: this.ui.streamFilter?.value || "",
      health_category: this.ui.healthCategoryFilter?.value || "",
      alert_status: this.ui.alertStatusFilter?.value || "",
      severity: this.ui.severityFilter?.value || "",
      search: this.ui.searchBox?.value.trim() || "",
    };

    Object.entries(filters).forEach(([key, val]) => {
      if (val !== "") params.set(key, val);
    });

    return params;
  },

  renderRecords() {
    const summary = this.calculateSummary(this.state.records);
    this.renderSummary(summary);
    this.renderTable();

    this.ui.recordsEmpty.classList.toggle("d-none", this.state.records.length > 0);
  },

  calculateSummary(records) {
    return {
      total: records.length,
      activeAlerts: records.filter(r => r.status === 'active' && r.severity !== 'low').length,
      clinicVisits: records.reduce((sum, r) => sum + (r.clinic_visits_count || 0), 0),
      allergies: records.filter(r => r.health_category === 'allergy' && r.status === 'active').length,
      medication: records.filter(r => r.health_category === 'medication' && r.status === 'active').length,
      emergency: records.filter(r => r.emergency_flag === 1).length,
    };
  },

  renderSummary(summary) {
    this.ui.totalRecords.textContent = summary.total ?? 0;
    this.ui.activeAlerts.textContent = summary.activeAlerts ?? 0;
    this.ui.clinicVisits.textContent = summary.clinicVisits ?? 0;
    this.ui.allergiesCount.textContent = summary.allergies ?? 0;
    this.ui.medicationCount.textContent = summary.medication ?? 0;
    this.ui.emergencyCount.textContent = summary.emergency ?? 0;
  },

  renderTable() {
    if (!this.state.records.length) {
      this.ui.recordsTableBody.innerHTML = `
        <tr>
          <td colspan="12" class="text-center text-muted py-4">
            No health records found.
          </td>
        </tr>`;
      return;
    }

    this.ui.recordsTableBody.innerHTML = this.state.records
      .map((r) => {
        const severityColors = {
          low: "secondary",
          medium: "info",
          high: "warning",
          critical: "danger",
        };

        const statusColors = {
          active: "primary",
          inactive: "secondary",
          resolved: "success",
          monitoring: "warning",
        };

        return `
          <tr>
            <td><small>${this.escape(r.record_code || r.id || "-")}</small></td>
            <td><strong>${this.escape(r.student_name || "-")}</strong></td>
            <td>${this.escape(r.admission_no || "-")}</td>
            <td>${this.escape(r.class_name || "-")}</td>
            <td>${this.escape(r.stream_name || "-")}</td>
            <td>${this.escape(r.health_category || "-")}</td>
            <td>${this.escape(r.alert_type || r.condition_name || r.allergy_name || r.medication_name || "-")}</td>
            <td><span class="badge bg-${severityColors[r.severity] || "secondary"}">${this.escape(r.severity || "-")}</span></td>
            <td><span class="badge bg-${statusColors[r.status] || "secondary"}">${this.escape(r.status || "-")}</span></td>
            <td>${this.escape(r.last_visit || "-")}</td>
            <td>${this.escape(r.next_review_date || "-")}</td>
            <td>
              <button class="btn btn-sm btn-outline-danger" onclick="StudentHealthController.viewRecord(${r.id})">
                <i class="bi bi-eye"></i> View
              </button>
            </td>
          </tr>`;
      })
      .join("");
  },

  resetFilters() {
    [
      this.ui.academicYearFilter,
      this.ui.classFilter,
      this.ui.streamFilter,
      this.ui.healthCategoryFilter,
      this.ui.alertStatusFilter,
      this.ui.severityFilter,
      this.ui.searchBox,
    ].forEach((el) => {
      if (el) el.value = "";
    });

    this.updateStreamsFilter();
    this.loadRecords();
  },

  async viewRecord(recordId) {
    this.state.selectedRecordId = recordId;

    if (typeof bootstrap !== "undefined" && this.ui.modal) {
      const modalInstance = new bootstrap.Modal(this.ui.modal);
      modalInstance.show();
    }

    await this.loadRecordDetails(recordId);
  },

  async loadRecordDetails(recordId) {
    this.setModalLoading(true);

    try {
      const response = await this.api(`/students/health-record/${recordId}`, "GET");
      const data = this.unwrap(response);

      this.ui.modalRecordId.textContent = data.record_code || recordId;
      this.renderRecordDetails(data);
    } catch (error) {
      console.error("Failed to load record details:", error);
      this.showModalError(error.message || "Failed to load record details");
    } finally {
      this.setModalLoading(false);
    }
  },

  renderRecordDetails(data) {
    const visits = data.visits || [];
    const student = data.student || {};

    this.ui.modalRecordContent.innerHTML = `
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Student Profile</h5>
          <p><strong>Name:</strong> ${this.escape(student.first_name || "")} ${this.escape(student.last_name || "")}</p>
          <p><strong>Admission No:</strong> ${this.escape(student.admission_no || "-")}</p>
          <p><strong>Class:</strong> ${this.escape(data.class_name || "-")}</p>
          <p><strong>Stream:</strong> ${this.escape(data.stream_name || "-")}</p>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Health Details</h5>
          <p><strong>Category:</strong> ${this.escape(data.health_category || "-")}</p>
          <p><strong>Alert Type:</strong> ${this.escape(data.alert_type || "-")}</p>
          <p><strong>Condition:</strong> ${this.escape(data.condition_name || "-")}</p>
          <p><strong>Allergy:</strong> ${this.escape(data.allergy_name || "-")}</p>
          <p><strong>Medication:</strong> ${this.escape(data.medication_name || "-")}</p>
          <p><strong>Severity:</strong> ${this.escape(data.severity || "-")}</p>
          <p><strong>Status:</strong> ${this.escape(data.status || "-")}</p>
          <p><strong>Emergency Flag:</strong> ${data.emergency_flag ? 'Yes' : 'No'}</p>
          <p><strong>Description:</strong> ${this.escape(data.description || "-")}</p>
          <p><strong>Action Instructions:</strong> ${this.escape(data.action_instructions || "-")}</p>
          <p><strong>Next Review Date:</strong> ${this.escape(data.next_review_date || "-")}</p>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Emergency Contact</h5>
          <p><strong>Contact Name:</strong> ${this.escape(data.emergency_contact_name || "-")}</p>
          <p><strong>Contact Phone:</strong> ${this.escape(data.emergency_contact_phone || "-")}</p>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Clinic Visit History (${visits.length})</h5>
          ${visits.length === 0 ? '<p class="text-muted">No clinic visits recorded.</p>' : ''}
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Date</th>
                <th>Complaint</th>
                <th>Observation</th>
                <th>Action Taken</th>
              </tr>
            </thead>
            <tbody>
              ${visits.map(v => `
                <tr>
                  <td>${this.escape(v.visit_date || "-")}</td>
                  <td>${this.escape(v.complaint || "-")}</td>
                  <td>${this.escape(v.observation || "-")}</td>
                  <td>${this.escape(v.action_taken || "-")}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  },

  exportData() {
    if (!this.state.records.length) {
      this.notify("No data to export", "warning");
      return;
    }

    const headers = ["Record ID", "Student", "Adm No", "Class", "Stream", "Category", "Alert Type", "Severity", "Status", "Last Visit", "Next Review"];
    const rows = this.state.records.map(r => [
      r.record_code || r.id || "",
      r.student_name || "",
      r.admission_no || "",
      r.class_name || "",
      r.stream_name || "",
      r.health_category || "",
      r.alert_type || r.condition_name || r.allergy_name || r.medication_name || "",
      r.severity || "",
      r.status || "",
      r.last_visit || "",
      r.next_review_date || "",
    ]);

    const csv = [headers, ...rows].map(row => row.map(cell => `"${String(cell || "").replace(/"/g, '""')}"`).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `health_records_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  setLoading(loading) {
    this.ui.recordsLoading?.classList.toggle("d-none", !loading);
    this.ui.recordsError?.classList.add("d-none");
  },

  showError(message) {
    if (!this.ui.recordsError) return;
    this.ui.recordsError.textContent = message;
    this.ui.recordsError.classList.remove("d-none");
  },

  setModalLoading(loading) {
    this.ui.modalLoading?.classList.toggle("d-none", !loading);
    this.ui.modalError?.classList.add("d-none");
    this.ui.modalRecordContent?.classList.toggle("opacity-50", loading);
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
      option.value = item.id ?? item.year ?? item.year_code ?? item.value ?? "";
      option.textContent = item.name || item.class_name || item.stream_name || item.year_name || item.year_code || item.label || option.value;
      select.appendChild(option);
    });
  },

  api: async function (endpoint, method = "GET", data = null) {
    return API.callAPI(endpoint, method, data);
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
  StudentHealthController.init(),
);

window.StudentHealthController = StudentHealthController;
