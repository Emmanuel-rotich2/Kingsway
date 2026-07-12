/**
 * Discipline Cases Controller
 * Page: discipline_cases.php
 * View and manage student discipline cases
 */
const DisciplineCasesController = {
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
    console.log("DisciplineCasesController: Initializing...");

    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.attachEvents();
    this.setPrintDate();

    console.log("DisciplineCasesController: Loading metadata...");
    await this.loadMeta();
    console.log("DisciplineCasesController: Loading cases...");
    await this.loadCases();
    console.log("DisciplineCasesController: Initialization complete");
  },

  cacheDom() {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      academicYearFilter: $("academicYearFilter"),
      termFilter: $("termFilter"),
      classFilter: $("classFilter"),
      streamFilter: $("streamFilter"),
      statusFilter: $("statusFilter"),
      severityFilter: $("severityFilter"),
      searchBox: $("searchBox"),
      applyFiltersBtn: $("applyFiltersBtn"),
      resetFiltersBtn: $("resetFiltersBtn"),
      exportCasesBtn: $("exportCasesBtn"),
      printCasesBtn: $("printCasesBtn"),
      addCaseBtn: $("addCaseBtn"),

      totalCases: $("totalCases"),
      openCases: $("openCases"),
      resolvedCases: $("resolvedCases"),
      seriousCases: $("seriousCases"),
      repeatOffenders: $("repeatOffenders"),
      thisTermCases: $("thisTermCases"),

      casesLoading: $("casesLoading"),
      casesError: $("casesError"),
      casesEmpty: $("casesEmpty"),
      casesForbidden: $("casesForbidden"),
      casesTableBody: $("casesTableBody"),

      modal: $("disciplineCaseModal"),
      modalCaseSubtitle: $("modalCaseSubtitle"),
      modalCaseId: $("modalCaseId"),
      modalLoading: $("modalLoading"),
      modalError: $("modalError"),
      modalCaseContent: $("modalCaseContent"),

      studentPhoto: $("studentPhoto"),
      studentName: $("studentName"),
      admNo: $("admNo"),
      studentClass: $("studentClass"),
      stream: $("stream"),
      incidentDate: $("incidentDate"),
      severityBadge: $("severityBadge"),
      statusBadge: $("statusBadge"),
      reportedBy: $("reportedBy"),
      resolvedBy: $("resolvedBy"),
      resolutionDate: $("resolutionDate"),
      caseDescription: $("caseDescription"),
      actionTaken: $("actionTaken"),

      actionsCard: $("actionsCard"),
      updateStatus: $("updateStatus"),
      addComment: $("addComment"),
      updateCaseBtn: $("updateCaseBtn"),
      printCaseBtn: $("printCaseBtn"),
    };
  },

  attachEvents() {
    this.ui.applyFiltersBtn?.addEventListener("click", () => this.loadCases());
    this.ui.resetFiltersBtn?.addEventListener("click", () => this.resetFilters());
    this.ui.printCasesBtn?.addEventListener("click", () => this.prepareAndPrint('overview'));
    this.ui.exportCasesBtn?.addEventListener("click", () => this.exportCases());

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
    this.ui.statusFilter?.addEventListener("change", () => this.loadCases());
    this.ui.severityFilter?.addEventListener("change", () => this.loadCases());

    this.ui.updateCaseBtn?.addEventListener("click", () => this.updateCase());
    this.ui.printCaseBtn?.addEventListener("click", () => this.prepareAndPrint('modal'));
  },

  async loadMeta() {
    try {
      console.log("DisciplineCasesController: Fetching discipline metadata...");
      const response = await this.api("/students/discipline-meta", "GET");
      console.log("DisciplineCasesController: Metadata response:", response);
      const data = this.unwrap(response);
      console.log("DisciplineCasesController: Unwrapped metadata:", data);

      this.state.classes = data.classes || [];
      this.state.streams = data.streams || [];
      this.state.academicYears = data.academic_years || [];
      this.state.terms = data.terms || [];

      console.log("DisciplineCasesController: Loaded classes:", this.state.classes.length);
      console.log("DisciplineCasesController: Loaded streams:", this.state.streams.length);
      console.log("DisciplineCasesController: Loaded academic years:", this.state.academicYears.length);
      console.log("DisciplineCasesController: Loaded terms:", this.state.terms.length);

      this.fillSelect(this.ui.academicYearFilter, this.state.academicYears, "All Years");
      this.fillSelect(this.ui.termFilter, this.state.terms, "All Terms");
      this.fillSelect(this.ui.classFilter, this.state.classes, "All Classes");

      this.updateStreamsFilter();
    } catch (error) {
      console.error("DisciplineCasesController: Failed to load metadata:", error);
      console.warn("DisciplineCasesController: Continuing with empty filter data");
      this.state.classes = [];
      this.state.streams = [];
      this.state.academicYears = [];
      this.state.terms = [];
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
    this.setCasesLoading(true);

    try {
      const params = this.getCaseParams();
      console.log("DisciplineCasesController: Loading cases with params:", params.toString());
      const response = await this.api(`/students/discipline-cases?${params.toString()}`, "GET");
      console.log("DisciplineCasesController: Cases response:", response);
      const cases = this.unwrap(response) || [];
      console.log("DisciplineCasesController: Unwrapped cases:", cases.length);

      this.state.cases = cases;
      this.renderCases();
    } catch (error) {
      console.error("DisciplineCasesController: Failed to load cases:", error);
      this.showCasesError(error.message || "Failed to load discipline cases.");
    } finally {
      this.setCasesLoading(false);
    }
  },

  getCaseParams() {
    const params = new URLSearchParams();
    const filters = {
      academic_year: this.ui.academicYearFilter?.value || "",
      term_id: this.ui.termFilter?.value || "",
      class_id: this.ui.classFilter?.value || "",
      stream_id: this.ui.streamFilter?.value || "",
      status: this.ui.statusFilter?.value || "",
      severity: this.ui.severityFilter?.value || "",
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
    const studentCaseCounts = {};
    cases.forEach(c => {
      if (c.student_id) {
        studentCaseCounts[c.student_id] = (studentCaseCounts[c.student_id] || 0) + 1;
      }
    });

    return {
      total: cases.length,
      open: cases.filter(c => c.status === 'pending').length,
      resolved: cases.filter(c => c.status === 'resolved').length,
      serious: cases.filter(c => c.severity === 'high').length,
      repeat: Object.values(studentCaseCounts).filter(count => count > 1).length,
      thisTerm: cases.length, // Simplified - could be filtered by date
    };
  },

  renderSummary(summary) {
    this.ui.totalCases.textContent = summary.total ?? 0;
    this.ui.openCases.textContent = summary.open ?? 0;
    this.ui.resolvedCases.textContent = summary.resolved ?? 0;
    this.ui.seriousCases.textContent = summary.serious ?? 0;
    this.ui.repeatOffenders.textContent = summary.repeat ?? 0;
    this.ui.thisTermCases.textContent = summary.thisTerm ?? 0;
  },

  renderTable() {
    if (!this.state.cases.length) {
      this.ui.casesTableBody.innerHTML = `
        <tr>
          <td colspan="11" class="text-center text-muted py-4">
            No discipline cases found.
          </td>
        </tr>`;
      return;
    }

    this.ui.casesTableBody.innerHTML = this.state.cases
      .map((c) => {
        const severityColors = {
          low: "success",
          medium: "warning",
          high: "danger",
        };
        const statusColors = {
          pending: "warning",
          resolved: "success",
          escalated: "danger",
        };

        return `
          <tr>
            <td>${c.id || "-"}</td>
            <td><strong>${this.escape(c.full_name || c.student_name || "-")}</strong></td>
            <td>${this.escape(c.admission_no || "-")}</td>
            <td>${this.escape(c.class_name || "-")}</td>
            <td>${this.escape(c.stream_name || "-")}</td>
            <td>${this.escape(c.description || "-")}</td>
            <td><span class="badge bg-${severityColors[c.severity] || "secondary"}">${this.escape(c.severity || "-")}</span></td>
            <td><span class="badge bg-${statusColors[c.status] || "secondary"}">${this.escape(c.status || "-")}</span></td>
            <td>${this.escape(c.incident_date || "-")}</td>
            <td>${this.escape(c.action_taken || "-")}</td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="DisciplineCasesController.viewCase(${c.id})">
                <i class="bi bi-eye me-1"></i> View
              </button>
            </td>
          </tr>`;
      })
      .join("");
  },

  async viewCase(caseId) {
    if (!caseId) return;

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
      const response = await this.api(`/students/discipline-case/${caseId}`, "GET");
      const data = this.unwrap(response);

      this.renderCaseDetails(data);
    } catch (error) {
      console.error("DisciplineCasesController: Failed to load case details:", error);
      this.showModalError(error.message || "Failed to load case details.");
    } finally {
      this.setModalLoading(false);
    }
  },

  renderCaseDetails(data) {
    const student = data.student || {};
    const caseData = data.case || {};

    this.ui.studentPhoto.src = student.photo_url || student.photo || `${window.APP_BASE || ""}/images/default-avatar.png`;
    this.ui.studentName.textContent = `${student.first_name || ""} ${student.last_name || ""}`.trim() || "-";
    this.ui.admNo.textContent = student.admission_no || "-";
    this.ui.studentClass.textContent = data.class_name || "-";
    this.ui.stream.textContent = data.stream_name || "-";

    this.ui.modalCaseId.textContent = caseData.id || "-";
    this.ui.incidentDate.textContent = caseData.incident_date || "-";

    const severityColors = { low: "success", medium: "warning", high: "danger" };
    this.ui.severityBadge.innerHTML = `<span class="badge bg-${severityColors[caseData.severity] || "secondary"}">${this.escape(caseData.severity || "-")}</span>`;

    const statusColors = { pending: "warning", resolved: "success", escalated: "danger" };
    this.ui.statusBadge.innerHTML = `<span class="badge bg-${statusColors[caseData.status] || "secondary"}">${this.escape(caseData.status || "-")}</span>`;

    this.ui.reportedBy.textContent = data.reported_by_name || "-";
    this.ui.resolvedBy.textContent = data.resolved_by_name || "-";
    this.ui.resolutionDate.textContent = caseData.resolution_date || "-";
    this.ui.caseDescription.textContent = caseData.description || "-";
    this.ui.actionTaken.textContent = caseData.action_taken || "-";

    // Show actions card if user has permission
    if (window.AuthContext && (window.AuthContext.hasPermission('discipline_edit') || window.AuthContext.hasPermission('discipline_resolve'))) {
      this.ui.actionsCard.style.display = 'block';
    }
  },

  async updateCase() {
    const status = this.ui.updateStatus?.value;
    const comment = this.ui.addComment?.value;

    if (!status && !comment) {
      this.notify("Please enter a status or comment", "warning");
      return;
    }

    try {
      const payload = {};
      if (status) payload.status = status;
      if (comment) payload.action_taken = comment;

      await this.api(`/students/discipline-case/${this.state.selectedCaseId}`, "PUT", payload);
      this.notify("Case updated successfully", "success");

      // Reload data
      await this.loadCases();
      await this.loadCaseDetails(this.state.selectedCaseId);

      // Clear form
      this.ui.addComment.value = "";
    } catch (error) {
      console.error("DisciplineCasesController: Failed to update case:", error);
      this.notify(error.message || "Failed to update case", "error");
    }
  },

  resetFilters() {
    [
      this.ui.academicYearFilter,
      this.ui.termFilter,
      this.ui.classFilter,
      this.ui.streamFilter,
      this.ui.statusFilter,
      this.ui.severityFilter,
      this.ui.searchBox,
    ].forEach((el) => {
      if (el) el.value = "";
    });

    this.updateStreamsFilter();
    this.loadCases();
  },

  exportCases() {
    if (!this.state.cases.length) {
      this.notify("No data to export", "warning");
      return;
    }

    const headers = ["Case ID", "Student", "Admission No", "Class", "Stream", "Incident", "Severity", "Status", "Incident Date", "Action Taken"];
    const rows = this.state.cases.map(c => [
      c.id || "",
      c.full_name || c.student_name || "",
      c.admission_no || "",
      c.class_name || "",
      c.stream_name || "",
      c.description || "",
      c.severity || "",
      c.status || "",
      c.incident_date || "",
      c.action_taken || "",
    ]);

    const csv = [headers, ...rows].map(row => row.map(cell => `"${String(cell || "").replace(/"/g, '""')}"`).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `discipline_cases_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  setCasesLoading(loading) {
    this.ui.casesLoading?.classList.toggle("d-none", !loading);
    this.ui.casesError?.classList.add("d-none");
  },

  setModalLoading(loading) {
    this.ui.modalLoading?.classList.toggle("d-none", !loading);
    this.ui.modalError?.classList.add("d-none");
    this.ui.modalCaseContent?.classList.toggle("opacity-50", loading);
  },

  showCasesError(message) {
    if (!this.ui.casesError) return;
    this.ui.casesError.textContent = message;
    this.ui.casesError.classList.remove("d-none");
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
      option.value = item.id ?? item.year ?? item.academic_year ?? item.value ?? "";
      option.textContent = item.name || item.class_name || item.stream_name || item.year_name || item.year_code || item.label || option.value;
      select.appendChild(option);
    });
  },

  setPrintDate() {
    const printDateEl = document.getElementById("printDate");
    if (printDateEl) {
      const now = new Date();
      printDateEl.textContent = now.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
      });
    }
  },

  prepareAndPrint(printType) {
    this.setPrintDate();
    document.body.classList.remove('printing', 'printing-modal', 'printing-overview');

    if (printType === 'modal') {
      document.body.classList.add('printing-modal');
      if (this.ui.modal) {
        this.ui.modal.classList.add('print-mode');
      }
    } else {
      document.body.classList.add('printing-overview');
    }

    window.print();

    setTimeout(() => {
      document.body.classList.remove('printing', 'printing-modal', 'printing-overview');
      if (printType === 'modal' && this.ui.modal) {
        this.ui.modal.classList.remove('print-mode');
      }
    }, 1000);
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
  DisciplineCasesController.init(),
);

window.DisciplineCasesController = DisciplineCasesController;
