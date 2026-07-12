/**
 * Student Counseling Controller
 * Manages counseling cases, welfare concerns, interventions, and follow-ups
 */
const StudentCounselingController = {
  state: {
    cases: [],
    academicYears: [],
    terms: [],
    classes: [],
    streams: [],
    selectedCaseId: null,
  },

  ui: {},

  async init() {
    console.log("StudentCounselingController: Initializing...");

    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.attachEvents();

    console.log("StudentCounselingController: Loading metadata...");
    await this.loadMeta();
    console.log("StudentCounselingController: Loading cases...");
    await this.loadCases();
    console.log("StudentCounselingController: Initialization complete");
  },

  cacheDom() {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      academicYearFilter: $("academicYearFilter"),
      termFilter: $("termFilter"),
      classFilter: $("classFilter"),
      streamFilter: $("streamFilter"),
      caseTypeFilter: $("caseTypeFilter"),
      priorityFilter: $("priorityFilter"),
      statusFilter: $("statusFilter"),
      genderFilter: $("genderFilter"),
      searchBox: $("searchBox"),
      applyFiltersBtn: $("applyFiltersBtn"),
      resetFiltersBtn: $("resetFiltersBtn"),
      refreshBtn: $("refreshBtn"),
      exportBtn: $("exportBtn"),
      addCaseBtn: $("addCaseBtn"),

      totalCases: $("totalCases"),
      openCases: $("openCases"),
      followUpsDue: $("followUpsDue"),
      resolvedCases: $("resolvedCases"),
      highPriorityCases: $("highPriorityCases"),
      thisTermCases: $("thisTermCases"),

      casesLoading: $("casesLoading"),
      casesError: $("casesError"),
      casesEmpty: $("casesEmpty"),
      casesTableBody: $("casesTableBody"),

      modal: $("caseModal"),
      modalLoading: $("modalLoading"),
      modalError: $("modalError"),
      modalCaseContent: $("modalCaseContent"),
      modalCaseId: $("modalCaseId"),
      addSessionBtn: $("addSessionBtn"),
      scheduleFollowUpBtn: $("scheduleFollowUpBtn"),
      closeCaseBtn: $("closeCaseBtn"),
    };
  },

  attachEvents() {
    this.ui.applyFiltersBtn?.addEventListener("click", () => this.loadCases());
    this.ui.resetFiltersBtn?.addEventListener("click", () => this.resetFilters());
    this.ui.refreshBtn?.addEventListener("click", () => this.loadCases());
    this.ui.exportBtn?.addEventListener("click", () => this.exportData());

    this.ui.searchBox?.addEventListener(
      "input",
      this.debounce(() => this.loadCases(), 400)
    );

    this.ui.classFilter?.addEventListener("change", () => {
      this.updateStreamsFilter();
      this.loadCases();
    });

    this.ui.streamFilter?.addEventListener("change", () => this.loadCases());
    this.ui.academicYearFilter?.addEventListener("change", () => this.loadCases());
    this.ui.termFilter?.addEventListener("change", () => this.loadCases());
    this.ui.caseTypeFilter?.addEventListener("change", () => this.loadCases());
    this.ui.priorityFilter?.addEventListener("change", () => this.loadCases());
    this.ui.statusFilter?.addEventListener("change", () => this.loadCases());
    this.ui.genderFilter?.addEventListener("change", () => this.loadCases());
  },

  async loadMeta() {
    try {
      const response = await this.api("/students/counseling-meta", "GET");
      const data = this.unwrap(response);

      this.state.academicYears = data.academic_years || [];
      this.state.terms = data.terms || [];
      this.state.classes = data.classes || [];
      this.state.streams = data.streams || [];

      this.fillSelect(this.ui.academicYearFilter, this.state.academicYears, "All Years");
      this.fillSelect(this.ui.termFilter, this.state.terms, "All Terms");
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

  async loadCases() {
    this.setLoading(true);

    try {
      const params = this.getParams();
      const response = await this.api(`/students/counseling-cases?${params.toString()}`, "GET");
      const cases = this.unwrap(response) || [];

      this.state.cases = cases;
      this.renderCases();
    } catch (error) {
      console.error("Failed to load cases:", error);
      this.showError(error.message || "Failed to load counseling cases");
    } finally {
      this.setLoading(false);
    }
  },

  getParams() {
    const params = new URLSearchParams();
    const filters = {
      academic_year: this.ui.academicYearFilter?.value || "",
      term_id: this.ui.termFilter?.value || "",
      class_id: this.ui.classFilter?.value || "",
      stream_id: this.ui.streamFilter?.value || "",
      case_type: this.ui.caseTypeFilter?.value || "",
      priority: this.ui.priorityFilter?.value || "",
      status: this.ui.statusFilter?.value || "",
      gender: this.ui.genderFilter?.value || "",
      search: this.ui.searchBox?.value.trim() || "",
    };

    Object.entries(filters).forEach(([key, val]) => {
      if (val !== "") params.set(key, val);
    });

    return params;
  },

  renderCases() {
    const summary = this.calculateSummary(this.state.cases);
    this.renderSummary(summary);
    this.renderTable();

    this.ui.casesEmpty.classList.toggle("d-none", this.state.cases.length > 0);
  },

  calculateSummary(cases) {
    const now = new Date();
    return {
      total: cases.length,
      open: cases.filter(c => c.status === 'open' || c.status === 'in_progress').length,
      followUpsDue: cases.filter(c => c.next_follow_up_at && new Date(c.next_follow_up_at) <= now).length,
      resolved: cases.filter(c => c.status === 'resolved' || c.status === 'closed').length,
      highPriority: cases.filter(c => c.priority === 'high' || c.priority === 'urgent').length,
      thisTerm: cases.length, // Would need term filtering logic
    };
  },

  renderSummary(summary) {
    this.ui.totalCases.textContent = summary.total ?? 0;
    this.ui.openCases.textContent = summary.open ?? 0;
    this.ui.followUpsDue.textContent = summary.followUpsDue ?? 0;
    this.ui.resolvedCases.textContent = summary.resolved ?? 0;
    this.ui.highPriorityCases.textContent = summary.highPriority ?? 0;
    this.ui.thisTermCases.textContent = summary.thisTerm ?? 0;
  },

  renderTable() {
    if (!this.state.cases.length) {
      this.ui.casesTableBody.innerHTML = `
        <tr>
          <td colspan="12" class="text-center text-muted py-4">
            No counseling cases found.
          </td>
        </tr>`;
      return;
    }

    this.ui.casesTableBody.innerHTML = this.state.cases
      .map((c) => {
        const priorityColors = {
          low: "secondary",
          medium: "info",
          high: "warning",
          urgent: "danger",
        };

        const statusColors = {
          open: "primary",
          in_progress: "info",
          resolved: "success",
          closed: "secondary",
          cancelled: "danger",
        };

        return `
          <tr>
            <td><small>${this.escape(c.case_code || "-")}</small></td>
            <td><strong>${this.escape(c.student_name || "-")}</strong></td>
            <td>${this.escape(c.admission_no || "-")}</td>
            <td>${this.escape(c.class_name || "-")}</td>
            <td>${this.escape(c.stream_name || "-")}</td>
            <td>${this.escape(c.case_type || "-")}</td>
            <td><span class="badge bg-${priorityColors[c.priority] || "secondary"}">${this.escape(c.priority || "-")}</span></td>
            <td><span class="badge bg-${statusColors[c.status] || "secondary"}">${this.escape(c.status || "-")}</span></td>
            <td>${this.escape(c.counselor_name || "-")}</td>
            <td>${this.escape(c.last_session || "-")}</td>
            <td>${this.escape(c.next_follow_up_at || "-")}</td>
            <td>
              <button class="btn btn-sm btn-outline-info" onclick="StudentCounselingController.viewCase(${c.id})">
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
      this.ui.termFilter,
      this.ui.classFilter,
      this.ui.streamFilter,
      this.ui.caseTypeFilter,
      this.ui.priorityFilter,
      this.ui.statusFilter,
      this.ui.genderFilter,
      this.ui.searchBox,
    ].forEach((el) => {
      if (el) el.value = "";
    });

    this.updateStreamsFilter();
    this.loadCases();
  },

  async viewCase(caseId) {
    this.state.selectedCaseId = caseId;

    if (typeof bootstrap !== "undefined" && this.ui.modal) {
      const modalInstance = new bootstrap.Modal(this.ui.modal);
      modalInstance.show();
    }

    await this.loadCaseDetails(caseId);
  },

  async loadCaseDetails(caseId) {
    this.setModalLoading(true);

    try {
      const response = await this.api(`/students/counseling-case/${caseId}`, "GET");
      const data = this.unwrap(response);

      this.ui.modalCaseId.textContent = data.case_code || caseId;
      this.renderCaseDetails(data);
    } catch (error) {
      console.error("Failed to load case details:", error);
      this.showModalError(error.message || "Failed to load case details");
    } finally {
      this.setModalLoading(false);
    }
  },

  renderCaseDetails(data) {
    const sessions = data.sessions || [];
    const student = data.student || {};

    this.ui.modalCaseContent.innerHTML = `
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
          <h5 class="card-title">Case Details</h5>
          <p><strong>Title:</strong> ${this.escape(data.title || "-")}</p>
          <p><strong>Case Type:</strong> ${this.escape(data.case_type || "-")}</p>
          <p><strong>Referral Source:</strong> ${this.escape(data.referral_source || "-")}</p>
          <p><strong>Priority:</strong> ${this.escape(data.priority || "-")}</p>
          <p><strong>Status:</strong> ${this.escape(data.status || "-")}</p>
          <p><strong>Assigned Counselor:</strong> ${this.escape(data.counselor_name || "-")}</p>
          <p><strong>Opened:</strong> ${this.escape(data.opened_at || "-")}</p>
          <p><strong>Next Follow-up:</strong> ${this.escape(data.next_follow_up_at || "-")}</p>
          <p><strong>Description:</strong> ${this.escape(data.description || "-")}</p>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Session History (${sessions.length})</h5>
          ${sessions.length === 0 ? '<p class="text-muted">No sessions recorded.</p>' : ''}
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Summary</th>
              </tr>
            </thead>
            <tbody>
              ${sessions.map(s => `
                <tr>
                  <td>${this.escape(s.session_date || "-")}</td>
                  <td>${this.escape(s.session_type || "-")}</td>
                  <td>${this.escape(s.summary || "-")}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  },

  exportData() {
    if (!this.state.cases.length) {
      this.notify("No data to export", "warning");
      return;
    }

    const headers = ["Case ID", "Student", "Adm No", "Class", "Stream", "Case Type", "Priority", "Status", "Counselor", "Last Session", "Next Follow-up"];
    const rows = this.state.cases.map(c => [
      c.case_code || "",
      c.student_name || "",
      c.admission_no || "",
      c.class_name || "",
      c.stream_name || "",
      c.case_type || "",
      c.priority || "",
      c.status || "",
      c.counselor_name || "",
      c.last_session || "",
      c.next_follow_up_at || "",
    ]);

    const csv = [headers, ...rows].map(row => row.map(cell => `"${String(cell || "").replace(/"/g, '""')}"`).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `counseling_cases_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  setLoading(loading) {
    this.ui.casesLoading?.classList.toggle("d-none", !loading);
    this.ui.casesError?.classList.add("d-none");
  },

  showError(message) {
    if (!this.ui.casesError) return;
    this.ui.casesError.textContent = message;
    this.ui.casesError.classList.remove("d-none");
  },

  setModalLoading(loading) {
    this.ui.modalLoading?.classList.toggle("d-none", !loading);
    this.ui.modalError?.classList.add("d-none");
    this.ui.modalCaseContent?.classList.toggle("opacity-50", loading);
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
  StudentCounselingController.init(),
);

window.StudentCounselingController = StudentCounselingController;
