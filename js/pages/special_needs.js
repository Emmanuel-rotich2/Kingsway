/**
 * Special Needs Page Controller
 * Manages special education records and IEP workflow using api.js
 * Boarding role aware - shows boarding-relevant support needs for Boarding Master/Matron
 */
const SpecialNeedsController = {
  state: {
    ieps: [],
    academicYears: [],
    classes: [],
    streams: [],
    dormitories: [],
    selectedIepId: null,
    isBoardingRole: false,
  },

  ui: {},

  async init() {
    console.log("SpecialNeedsController: Initializing...");

    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    // Check if user is boarding role
    const user = window.AuthContext?.getUser() || {};
    this.state.isBoardingRole = ['boarding_master', 'boarding_matron', 'housemother'].includes(user.role);

    this.cacheDom();
    this.attachEvents();
    this.setPrintDate();

    // Update UI for boarding role
    if (this.state.isBoardingRole) {
      this.updateForBoardingRole();
    }

    console.log("SpecialNeedsController: Loading metadata...");
    await this.loadMeta();
    console.log("SpecialNeedsController: Loading IEPs...");
    await this.loadIEPs();
    console.log("SpecialNeedsController: Initialization complete");
  },

  cacheDom() {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      academicYearFilter: $("academicYearFilter"),
      classFilter: $("classFilter"),
      streamFilter: $("streamFilter"),
      dormitoryFilter: $("dormitoryFilter"),
      statusFilter: $("statusFilter"),
      searchBox: $("searchBox"),
      applyFiltersBtn: $("applyFiltersBtn"),
      resetFiltersBtn: $("resetFiltersBtn"),
      exportRecordsBtn: $("exportRecordsBtn"),
      printRecordsBtn: $("printRecordsBtn"),
      addRecordBtn: $("addRecordBtn"),

      totalIEPs: $("totalIEPs"),
      activeIEPs: $("activeIEPs"),
      draftIEPs: $("draftIEPs"),
      completedIEPs: $("completedIEPs"),
      healthRecords: $("healthRecords"),
      archivedIEPs: $("archivedIEPs"),

      iepsLoading: $("iepsLoading"),
      iepsError: $("iepsError"),
      iepsEmpty: $("iepsEmpty"),
      iepsForbidden: $("iepsForbidden"),
      iepsTableBody: $("iepsTableBody"),

      modal: $("iepModal"),
      modalIepSubtitle: $("modalIepSubtitle"),
      modalIepId: $("modalIepId"),
      modalLoading: $("modalLoading"),
      modalError: $("modalError"),
      modalIepContent: $("modalIepContent"),

      studentPhoto: $("studentPhoto"),
      studentName: $("studentName"),
      admNo: $("admNo"),
      studentClass: $("studentClass"),
      stream: $("stream"),
      iepType: $("iepType"),
      iepCategory: $("iepCategory"),
      academicYear: $("academicYear"),
      statusBadge: $("statusBadge"),
      createdDate: $("createdDate"),
      approvedDate: $("approvedDate"),
      goalsSummary: $("goalsSummary"),
      strategies: $("strategies"),
      accommodations: $("accommodations"),
      progressMonitoring: $("progressMonitoring"),
      printIepBtn: $("printIepBtn"),
    };
  },

  updateForBoardingRole() {
    // Update header for boarding role
    const scopeSubtitle = document.getElementById('scopeSubtitle');
    if (scopeSubtitle) {
      scopeSubtitle.textContent = 'Boarding-relevant support needs and care instructions';
    }

    // Add dormitory filter
    const filterRow = document.querySelector('.row.g-3.mb-4');
    if (filterRow && !this.ui.dormitoryFilter) {
      const dormitoryCol = document.createElement('div');
      dormitoryCol.className = 'col-xl-3 col-md-6';
      dormitoryCol.innerHTML = `
        <label class="form-label fw-semibold">Dormitory</label>
        <select class="form-select" id="dormitoryFilter">
          <option value="">All Dormitories</option>
        </select>
      `;
      filterRow.appendChild(dormitoryCol);
      this.ui.dormitoryFilter = document.getElementById('dormitoryFilter');
    }

    // Hide add record button for boarding role
    if (this.ui.addRecordBtn) {
      this.ui.addRecordBtn.style.display = 'none';
    }
  },

  attachEvents() {
    this.ui.applyFiltersBtn?.addEventListener("click", () => this.loadIEPs());
    this.ui.resetFiltersBtn?.addEventListener("click", () => this.resetFilters());
    this.ui.printRecordsBtn?.addEventListener("click", () => this.prepareAndPrint('overview'));
    this.ui.exportRecordsBtn?.addEventListener("click", () => this.exportRecords());

    this.ui.searchBox?.addEventListener(
      "input",
      this.debounce(() => this.loadIEPs(), 400)
    );

    this.ui.classFilter?.addEventListener("change", () => {
      this.updateStreamsFilter();
      this.loadIEPs();
    });

    this.ui.streamFilter?.addEventListener("change", () => this.loadIEPs());
    this.ui.academicYearFilter?.addEventListener("change", () => this.loadIEPs());
    this.ui.statusFilter?.addEventListener("change", () => this.loadIEPs());
    this.ui.dormitoryFilter?.addEventListener("change", () => this.loadIEPs());

    this.ui.printIepBtn?.addEventListener("click", () => this.prepareAndPrint('modal'));
  },

  async loadMeta() {
    try {
      console.log("SpecialNeedsController: Fetching special needs metadata...");
      const response = await this.api("/students/special-needs-meta", "GET");
      console.log("SpecialNeedsController: Metadata response:", response);
      const data = this.unwrap(response);
      console.log("SpecialNeedsController: Unwrapped metadata:", data);

      this.state.classes = data.classes || [];
      this.state.streams = data.streams || [];
      this.state.academicYears = data.academic_years || [];
      this.state.dormitories = data.dormitories || [];

      console.log("SpecialNeedsController: Loaded classes:", this.state.classes.length);
      console.log("SpecialNeedsController: Loaded streams:", this.state.streams.length);
      console.log("SpecialNeedsController: Loaded academic years:", this.state.academicYears.length);
      console.log("SpecialNeedsController: Loaded dormitories:", this.state.dormitories.length);

      this.fillSelect(this.ui.academicYearFilter, this.state.academicYears, "All Years");
      this.fillSelect(this.ui.classFilter, this.state.classes, "All Classes");
      this.fillSelect(this.ui.dormitoryFilter, this.state.dormitories, "All Dormitories");

      this.updateStreamsFilter();
    } catch (error) {
      console.error("SpecialNeedsController: Failed to load metadata:", error);
      console.warn("SpecialNeedsController: Continuing with empty filter data");
      this.state.classes = [];
      this.state.streams = [];
      this.state.academicYears = [];
      this.state.dormitories = [];
    }
  },

  updateStreamsFilter() {
    const classId = this.ui.classFilter?.value || "";
    const filtered = classId
      ? this.state.streams.filter((s) => String(s.class_id) === String(classId))
      : this.state.streams;
    this.fillSelect(this.ui.streamFilter, filtered, "All Streams");
  },

  async loadIEPs() {
    this.setIepsLoading(true);

    try {
      const params = this.getIepParams();
      console.log("SpecialNeedsController: Loading IEPs with params:", params.toString());
      const response = await this.api(`/students/special-needs-ieps?${params.toString()}`, "GET");
      console.log("SpecialNeedsController: IEPs response:", response);
      const ieps = this.unwrap(response) || [];
      console.log("SpecialNeedsController: Unwrapped IEPs:", ieps.length);

      this.state.ieps = ieps;
      this.renderIEPs();
    } catch (error) {
      console.error("SpecialNeedsController: Failed to load IEPs:", error);
      this.showIepsError(error.message || "Failed to load special needs records.");
    } finally {
      this.setIepsLoading(false);
    }
  },

  getIepParams() {
    const params = new URLSearchParams();
    const filters = {
      academic_year: this.ui.academicYearFilter?.value || "",
      class_id: this.ui.classFilter?.value || "",
      stream_id: this.ui.streamFilter?.value || "",
      dormitory_id: this.ui.dormitoryFilter?.value || "",
      status: this.ui.statusFilter?.value || "",
      search: this.ui.searchBox?.value.trim() || "",
    };

    Object.entries(filters).forEach(([key, val]) => {
      if (val !== "") params.set(key, val);
    });

    return params;
  },

  renderIEPs() {
    const summary = this.calculateSummary(this.state.ieps);
    this.renderSummary(summary);
    this.renderTable();

    this.ui.iepsEmpty.classList.toggle("d-none", this.state.ieps.length > 0);
  },

  calculateSummary(ieps) {
    return {
      total: ieps.length,
      active: ieps.filter(i => i.status === 'active').length,
      draft: ieps.filter(i => i.status === 'draft').length,
      completed: ieps.filter(i => i.status === 'completed').length,
      archived: ieps.filter(i => i.status === 'archived').length,
      health: 0, // Placeholder - would need separate query to student_health_records
    };
  },

  renderSummary(summary) {
    this.ui.totalIEPs.textContent = summary.total ?? 0;
    this.ui.activeIEPs.textContent = summary.active ?? 0;
    this.ui.draftIEPs.textContent = summary.draft ?? 0;
    this.ui.completedIEPs.textContent = summary.completed ?? 0;
    this.ui.healthRecords.textContent = summary.health ?? 0;
    this.ui.archivedIEPs.textContent = summary.archived ?? 0;
  },

  renderTable() {
    if (!this.state.ieps.length) {
      this.ui.iepsTableBody.innerHTML = `
        <tr>
          <td colspan="${this.state.isBoardingRole ? 12 : 11}" class="text-center text-muted py-4">
            No IEP records found.
          </td>
        </tr>`;
      return;
    }

    this.ui.iepsTableBody.innerHTML = this.state.ieps
      .map((i) => {
        const statusColors = {
          draft: "warning",
          active: "success",
          completed: "primary",
          archived: "secondary",
        };

        return `
          <tr>
            <td>${i.id || "-"}</td>
            <td><strong>${this.escape(i.full_name || i.student_name || "-")}</strong></td>
            <td>${this.escape(i.admission_no || "-")}</td>
            <td>${this.escape(i.class_name || "-")}</td>
            <td>${this.escape(i.stream_name || "-")}</td>
            ${this.state.isBoardingRole ? `<td>${this.escape(i.dormitory_name || "-")}</td>` : ''}
            <td>${this.escape(i.iep_type || "-")}</td>
            <td>${this.escape(i.special_needs_category || "-")}</td>
            <td><span class="badge bg-${statusColors[i.status] || "secondary"}">${this.escape(i.status || "-")}</span></td>
            <td>${this.escape(i.academic_year || "-")}</td>
            <td>${this.escape(i.created_at || "-")}</td>
            <td>
              <button class="btn btn-sm btn-outline-info" onclick="SpecialNeedsController.viewIep(${i.id})">
                <i class="bi bi-eye me-1"></i> View
              </button>
            </td>
          </tr>`;
      })
      .join("");
  },

  async viewIep(iepId) {
    if (!iepId) return;

    this.state.selectedIepId = iepId;

    if (typeof bootstrap !== "undefined" && this.ui.modal) {
      const modalInstance = new bootstrap.Modal(this.ui.modal);
      modalInstance.show();
    }

    await this.loadIepDetails(iepId);
  },

  async loadIepDetails(iepId) {
    this.setModalLoading(true);

    try {
      const response = await this.api(`/students/special-needs-ieps/${iepId}`, "GET");
      const data = this.unwrap(response);

      this.renderIepDetails(data);
    } catch (error) {
      console.error("SpecialNeedsController: Failed to load IEP details:", error);
      this.showModalError(error.message || "Failed to load IEP details.");
    } finally {
      this.setModalLoading(false);
    }
  },

  renderIepDetails(data) {
    const student = data.student || {};
    const iep = data.iep || {};

    this.ui.studentPhoto.src = student.photo_url || student.photo || `${window.APP_BASE || ""}/images/default-avatar.png`;
    this.ui.studentName.textContent = `${student.first_name || ""} ${student.last_name || ""}`.trim() || "-";

    this.ui.admNo.textContent = student.admission_no || "-";
    this.ui.studentClass.textContent = data.class_name || "-";
    this.ui.stream.textContent = data.stream_name || "-";

    // For boarding role, add dormitory information to the student info section
    if (this.state.isBoardingRole && data.dormitory_name) {
      const studentInfoRow = this.ui.studentClass.parentElement.parentElement;
      if (studentInfoRow) {
        // Add dormitory as an additional field
        const dormDiv = document.createElement('div');
        dormDiv.className = 'col-md-4';
        dormDiv.innerHTML = `<strong>Dormitory:</strong> <span>${this.escape(data.dormitory_name || "-")}</span>`;
        studentInfoRow.appendChild(dormDiv);
      }
    }

    this.ui.modalIepId.textContent = iep.id || "-";
    this.ui.iepType.textContent = iep.iep_type || "-";
    this.ui.iepCategory.textContent = iep.special_needs_category || "-";
    this.ui.academicYear.textContent = iep.academic_year || "-";

    const statusColors = { draft: "warning", active: "success", completed: "primary", archived: "secondary" };
    this.ui.statusBadge.innerHTML = `<span class="badge bg-${statusColors[iep.status] || "secondary"}">${this.escape(iep.status || "-")}</span>`;

    this.ui.createdDate.textContent = iep.created_at || "-";
    this.ui.approvedDate.textContent = iep.approved_date || "-";
    this.ui.goalsSummary.textContent = iep.goals_summary || "-";
    this.ui.strategies.textContent = iep.strategies || "-";
    this.ui.accommodations.textContent = iep.accommodations || "-";
    this.ui.progressMonitoring.textContent = iep.progress_monitoring_plan || "-";
  },

  resetFilters() {
    [
      this.ui.academicYearFilter,
      this.ui.classFilter,
      this.ui.streamFilter,
      this.ui.statusFilter,
      this.ui.searchBox,
    ].forEach((el) => {
      if (el) el.value = "";
    });

    this.updateStreamsFilter();
    this.loadIEPs();
  },

  exportRecords() {
    if (!this.state.ieps.length) {
      this.notify("No data to export", "warning");
      return;
    }

    const headers = ["IEP ID", "Student", "Admission No", "Class", "Stream", "IEP Type", "Category", "Status", "Academic Year", "Created Date"];
    const rows = this.state.ieps.map(i => [
      i.id || "",
      i.full_name || i.student_name || "",
      i.admission_no || "",
      i.class_name || "",
      i.stream_name || "",
      i.iep_type || "",
      i.special_needs_category || "",
      i.status || "",
      i.academic_year || "",
      i.created_at || "",
    ]);

    const csv = [headers, ...rows].map(row => row.map(cell => `"${String(cell || "").replace(/"/g, '""')}"`).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `special_needs_ieps_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  setIepsLoading(loading) {
    this.ui.iepsLoading?.classList.toggle("d-none", !loading);
    this.ui.iepsError?.classList.add("d-none");
  },

  setModalLoading(loading) {
    this.ui.modalLoading?.classList.toggle("d-none", !loading);
    this.ui.modalError?.classList.add("d-none");
    this.ui.modalIepContent?.classList.toggle("opacity-50", loading);
  },

  showIepsError(message) {
    if (!this.ui.iepsError) return;
    this.ui.iepsError.textContent = message;
    this.ui.iepsError.classList.remove("d-none");
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
  SpecialNeedsController.init(),
);

window.SpecialNeedsController = SpecialNeedsController;
