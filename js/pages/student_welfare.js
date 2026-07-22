/**
 * Student Welfare Controller
 * Chaplain / School Counselor dashboard for welfare cases and referrals
 */
const StudentWelfareController = {
  state: {
    cases: [],
    academicYears: [],
    terms: [],
    classes: [],
    streams: [],
    staff: [],
    students: [],
    selectedCaseId: null,
  },

  ui: {},

  async init() {
    console.log("StudentWelfareController: Initializing...");

    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.attachEvents();

    console.log("StudentWelfareController: Loading metadata...");
    await this.loadMeta();
    console.log("StudentWelfareController: Loading cases...");
    await this.loadCases();
    console.log("StudentWelfareController: Initialization complete");
  },

  cacheDom() {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      academicYearFilter: $("academicYearFilter"),
      termFilter: $("termFilter"),
      classFilter: $("classFilter"),
      streamFilter: $("streamFilter"),
      genderFilter: $("genderFilter"),
      categoryFilter: $("categoryFilter"),
      referralSourceFilter: $("referralSourceFilter"),
      priorityFilter: $("priorityFilter"),
      statusFilter: $("statusFilter"),
      assignedToFilter: $("assignedToFilter"),
      searchBox: $("searchBox"),
      applyFiltersBtn: $("applyFiltersBtn"),
      resetFiltersBtn: $("resetFiltersBtn"),
      refreshBtn: $("refreshBtn"),
      newCaseBtn: $("newCaseBtn"),
      scheduleFollowUpBtn: $("scheduleFollowUpBtn"),
      exportBtn: $("exportBtn"),

      totalCases: $("totalCases"),
      activeCases: $("activeCases"),
      highPriorityCases: $("highPriorityCases"),
      followUpsDue: $("followUpsDue"),
      referralsCount: $("referralsCount"),
      resolvedCases: $("resolvedCases"),

      casesLoading: $("casesLoading"),
      casesError: $("casesError"),
      casesForbidden: $("casesForbidden"),
      casesEmpty: $("casesEmpty"),
      casesTableBody: $("casesTableBody"),

      modal: $("welfareCaseModal"),
      modalCaseId: $("modalCaseId"),
      modalLoading: $("modalLoading"),
      modalError: $("modalError"),
      modalCaseContent: $("modalCaseContent"),
      addNoteBtn: $("addNoteBtn"),
      scheduleFollowUpModalBtn: $("scheduleFollowUpModalBtn"),
      resolveCaseBtn: $("resolveCaseBtn"),
      escalateBtn: $("escalateBtn"),

      addNoteModal: $("addNoteModal"),
      addNoteForm: $("addNoteForm"),
      noteType: $("noteType"),
      noteContent: $("noteContent"),
      noteFollowUpDate: $("noteFollowUpDate"),
      saveNoteBtn: $("saveNoteBtn"),

      scheduleFollowUpModal: $("scheduleFollowUpModal"),
      followUpForm: $("followUpForm"),
      followUpDate: $("followUpDate"),
      followUpNote: $("followUpNote"),
      saveFollowUpBtn: $("saveFollowUpBtn"),

      resolveCaseModal: $("resolveCaseModal"),
      resolveForm: $("resolveForm"),
      resolutionNote: $("resolutionNote"),
      confirmResolveBtn: $("confirmResolveBtn"),

      escalateCaseModal: $("escalateCaseModal"),
      escalateForm: $("escalateForm"),
      escalateTo: $("escalateTo"),
      escalationNote: $("escalationNote"),
      confirmEscalateBtn: $("confirmEscalateBtn"),

      newCaseModal: $("newCaseModal"),
      newCaseForm: $("newCaseForm"),
      newCaseStudent: $("newCaseStudent"),
      newCaseTitle: $("newCaseTitle"),
      newCaseCategory: $("newCaseCategory"),
      newCaseReferralSource: $("newCaseReferralSource"),
      newCasePriority: $("newCasePriority"),
      newCaseDescription: $("newCaseDescription"),
      newCaseAssignedTo: $("newCaseAssignedTo"),
      newCaseFollowUpDate: $("newCaseFollowUpDate"),
      saveNewCaseBtn: $("saveNewCaseBtn"),
    };
  },

  attachEvents() {
    this.ui.applyFiltersBtn?.addEventListener("click", () => this.loadCases());
    this.ui.resetFiltersBtn?.addEventListener("click", () => this.resetFilters());
    this.ui.refreshBtn?.addEventListener("click", () => this.loadCases());
    this.ui.newCaseBtn?.addEventListener("click", () => this.openNewCaseModal());
    this.ui.saveNewCaseBtn?.addEventListener("click", () => this.saveNewCase());

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
    this.ui.genderFilter?.addEventListener("change", () => this.loadCases());
    this.ui.categoryFilter?.addEventListener("change", () => this.loadCases());
    this.ui.referralSourceFilter?.addEventListener("change", () => this.loadCases());
    this.ui.priorityFilter?.addEventListener("change", () => this.loadCases());
    this.ui.statusFilter?.addEventListener("change", () => this.loadCases());
    this.ui.assignedToFilter?.addEventListener("change", () => this.loadCases());

    // Welfare case action buttons
    this.ui.addNoteBtn?.addEventListener("click", () => this.openAddNoteModal());
    this.ui.scheduleFollowUpModalBtn?.addEventListener("click", () => this.openFollowUpModal());
    this.ui.resolveCaseBtn?.addEventListener("click", () => this.openResolveModal());
    this.ui.escalateBtn?.addEventListener("click", () => this.openEscalateModal());

    // Modal save buttons
    this.ui.saveNoteBtn?.addEventListener("click", () => this.saveNote());
    this.ui.saveFollowUpBtn?.addEventListener("click", () => this.saveFollowUp());
    this.ui.confirmResolveBtn?.addEventListener("click", () => this.resolveCase());
    this.ui.confirmEscalateBtn?.addEventListener("click", () => this.escalateCase());
  },

  async loadMeta() {
    try {
      const response = await this.api("/students/welfare-meta", "GET");
      const data = this.unwrap(response);

      this.state.academicYears = data.academic_years || [];
      this.state.terms = data.terms || [];
      this.state.classes = data.classes || [];
      this.state.streams = data.streams || [];
      this.state.staff = data.staff || [];
      this.state.students = data.students || [];

      this.fillSelect(this.ui.academicYearFilter, this.state.academicYears, "All Years");
      this.fillSelect(this.ui.termFilter, this.state.terms, "All Terms");
      this.fillSelect(this.ui.classFilter, this.state.classes, "All Classes");
      this.fillSelect(this.ui.streamFilter, this.state.streams, "All Streams");
      this.fillSelect(this.ui.assignedToFilter, this.state.staff, "All Staff");
      this.fillSelect(this.ui.newCaseStudent, this.state.students, "Select Student");
      this.fillSelect(this.ui.newCaseAssignedTo, this.state.staff, "Select Staff");
      this.fillSelect(this.ui.escalateTo, this.state.staff, "Select Staff");
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
      const response = await this.api(`/students/welfare-cases?${params.toString()}`, "GET");
      const cases = this.unwrap(response) || [];

      this.state.cases = cases;
      this.renderCases();
    } catch (error) {
      console.error("Failed to load cases:", error);
      if (error.message.includes("forbidden") || error.message.includes("permission")) {
        this.showForbidden();
      } else {
        this.showError(error.message || "Failed to load welfare cases");
      }
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
      gender: this.ui.genderFilter?.value || "",
      welfare_category: this.ui.categoryFilter?.value || "",
      referral_source: this.ui.referralSourceFilter?.value || "",
      priority: this.ui.priorityFilter?.value || "",
      status: this.ui.statusFilter?.value || "",
      assigned_to: this.ui.assignedToFilter?.value || "",
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
    return {
      total: cases.length,
      active: cases.filter(c => c.status === 'open' || c.status === 'in_progress').length,
      high_priority: cases.filter(c => c.priority === 'high' || c.priority === 'urgent').length,
      follow_ups_due: cases.filter(c => c.next_follow_up_at && new Date(c.next_follow_up_at) <= new Date()).length,
      referrals: cases.filter(c => c.referral_source === 'discipline').length,
      resolved: cases.filter(c => c.status === 'resolved' || c.status === 'closed').length,
    };
  },

  renderSummary(summary) {
    this.ui.totalCases.textContent = summary.total ?? 0;
    this.ui.activeCases.textContent = summary.active ?? 0;
    this.ui.highPriorityCases.textContent = summary.high_priority ?? 0;
    this.ui.followUpsDue.textContent = summary.follow_ups_due ?? 0;
    this.ui.referralsCount.textContent = summary.referrals ?? 0;
    this.ui.resolvedCases.textContent = summary.resolved ?? 0;
  },

  renderTable() {
    if (!this.state.cases.length) {
      this.ui.casesTableBody.innerHTML = `
        <tr>
          <td colspan="13" class="text-center text-muted py-4">
            No welfare cases found.
          </td>
        </tr>`;
      return;
    }

    this.ui.casesTableBody.innerHTML = this.state.cases
      .map((c) => {
        const statusColors = {
          open: "warning",
          in_progress: "info",
          resolved: "success",
          closed: "secondary",
        };

        const priorityColors = {
          low: "secondary",
          medium: "info",
          high: "warning",
          urgent: "danger",
        };

        return `
          <tr>
            <td>${c.case_code || "-"}</td>
            <td><strong>${this.escape(c.full_name || "-")}</strong></td>
            <td>${this.escape(c.admission_no || "-")}</td>
            <td>${this.escape(c.class_name || "-")}</td>
            <td>${this.escape(c.stream_name || "-")}</td>
            <td>${this.escape(c.welfare_category || "-")}</td>
            <td>${this.escape(c.referral_source || "-")}</td>
            <td><span class="badge bg-${priorityColors[c.priority] || "secondary"}">${this.escape(c.priority || "-")}</span></td>
            <td><span class="badge bg-${statusColors[c.status] || "secondary"}">${this.escape(c.status || "-")}</span></td>
            <td>${this.escape(c.assigned_to_name || "-")}</td>
            <td>${this.escape(c.last_interaction || "-")}</td>
            <td>${this.escape(c.next_follow_up_at || "-")}</td>
            <td>
              <button class="btn btn-sm btn-outline-warning" onclick="StudentWelfareController.viewCase(${c.id})">
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
      this.ui.genderFilter,
      this.ui.categoryFilter,
      this.ui.referralSourceFilter,
      this.ui.priorityFilter,
      this.ui.statusFilter,
      this.ui.assignedToFilter,
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
      const response = await this.api(`/students/welfare-case/${caseId}`, "GET");
      const data = this.unwrap(response);

      this.renderCaseDetails(data);
    } catch (error) {
      console.error("Failed to load case details:", error);
      this.showModalError(error.message || "Failed to load case details");
    } finally {
      this.setModalLoading(false);
    }
  },

  renderCaseDetails(data) {
    const caseData = data.case || {};
    const student = data.student || {};
    const notes = data.notes || [];

    this.ui.modalCaseId.textContent = caseData.case_code || caseData.id || "-";

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
          <p><strong>Title:</strong> ${this.escape(caseData.title || "-")}</p>
          <p><strong>Category:</strong> ${this.escape(caseData.welfare_category || "-")}</p>
          <p><strong>Referral Source:</strong> ${this.escape(caseData.referral_source || "-")}</p>
          <p><strong>Priority:</strong> ${this.escape(caseData.priority || "-")}</p>
          <p><strong>Status:</strong> ${this.escape(caseData.status || "-")}</p>
          <p><strong>Assigned To:</strong> ${this.escape(data.assigned_to_name || "-")}</p>
          <p><strong>Opened At:</strong> ${this.escape(caseData.opened_at || "-")}</p>
          <p><strong>Next Follow-up:</strong> ${this.escape(caseData.next_follow_up_at || "-")}</p>
          <p><strong>Description:</strong></p>
          <p>${this.escape(caseData.description || "-")}</p>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Notes (${notes.length})</h5>
          ${notes.length === 0 ? '<p class="text-muted">No notes.</p>' : ''}
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

  openNewCaseModal() {
    this.ui.newCaseForm.reset();
    if (typeof bootstrap !== "undefined" && this.ui.newCaseModal) {
      const modalInstance = new bootstrap.Modal(this.ui.newCaseModal);
      modalInstance.show();
    }
  },

  async saveNewCase() {
    const formData = {
      student_id: this.ui.newCaseStudent.value,
      title: this.ui.newCaseTitle.value,
      welfare_category: this.ui.newCaseCategory.value,
      referral_source: this.ui.newCaseReferralSource.value,
      priority: this.ui.newCasePriority.value,
      description: this.ui.newCaseDescription.value,
      assigned_to: this.ui.newCaseAssignedTo.value,
      next_follow_up_at: this.ui.newCaseFollowUpDate.value,
    };

    try {
      const response = await this.api("/students/welfare-case", "POST", formData);
      this.notify("Welfare case created successfully", "success");
      if (typeof bootstrap !== "undefined" && this.ui.newCaseModal) {
        const modalInstance = bootstrap.Modal.getInstance(this.ui.newCaseModal);
        modalInstance?.hide();
      }
      this.loadCases();
    } catch (error) {
      this.notify(error.message || "Failed to create welfare case", "error");
    }
  },

  openAddNoteModal() {
    this.ui.addNoteForm.reset();
    if (typeof bootstrap !== "undefined" && this.ui.addNoteModal) {
      const modalInstance = new bootstrap.Modal(this.ui.addNoteModal);
      modalInstance.show();
    }
  },

  async saveNote() {
    const formData = {
      note_type: this.ui.noteType.value,
      note: this.ui.noteContent.value,
      follow_up_date: this.ui.noteFollowUpDate.value || null,
    };

    try {
      const response = await this.api(`/students/welfare-case/${this.state.selectedCaseId}/note`, "POST", formData);
      this.notify("Note added successfully", "success");
      if (typeof bootstrap !== "undefined" && this.ui.addNoteModal) {
        const modalInstance = bootstrap.Modal.getInstance(this.ui.addNoteModal);
        modalInstance?.hide();
      }
      this.loadCaseDetails(this.state.selectedCaseId);
    } catch (error) {
      this.notify(error.message || "Failed to add note", "error");
    }
  },

  openFollowUpModal() {
    this.ui.followUpForm.reset();
    if (typeof bootstrap !== "undefined" && this.ui.scheduleFollowUpModal) {
      const modalInstance = new bootstrap.Modal(this.ui.scheduleFollowUpModal);
      modalInstance.show();
    }
  },

  async saveFollowUp() {
    const formData = {
      follow_up_date: this.ui.followUpDate.value,
      note: this.ui.followUpNote.value || null,
    };

    try {
      const response = await this.api(`/students/welfare-case/${this.state.selectedCaseId}/follow-up`, "POST", formData);
      this.notify("Follow-up scheduled successfully", "success");
      if (typeof bootstrap !== "undefined" && this.ui.scheduleFollowUpModal) {
        const modalInstance = bootstrap.Modal.getInstance(this.ui.scheduleFollowUpModal);
        modalInstance?.hide();
      }
      this.loadCaseDetails(this.state.selectedCaseId);
    } catch (error) {
      this.notify(error.message || "Failed to schedule follow-up", "error");
    }
  },

  openResolveModal() {
    this.ui.resolveForm.reset();
    if (typeof bootstrap !== "undefined" && this.ui.resolveCaseModal) {
      const modalInstance = new bootstrap.Modal(this.ui.resolveCaseModal);
      modalInstance.show();
    }
  },

  async resolveCase() {
    const formData = {
      resolution_note: this.ui.resolutionNote.value || null,
    };

    try {
      const response = await this.api(`/students/welfare-case/${this.state.selectedCaseId}/resolve`, "POST", formData);
      this.notify("Case resolved successfully", "success");
      if (typeof bootstrap !== "undefined" && this.ui.resolveCaseModal) {
        const modalInstance = bootstrap.Modal.getInstance(this.ui.resolveCaseModal);
        modalInstance?.hide();
      }
      if (typeof bootstrap !== "undefined" && this.ui.modal) {
        const modalInstance = bootstrap.Modal.getInstance(this.ui.modal);
        modalInstance?.hide();
      }
      this.loadCases();
    } catch (error) {
      this.notify(error.message || "Failed to resolve case", "error");
    }
  },

  openEscalateModal() {
    this.ui.escalateForm.reset();
    if (typeof bootstrap !== "undefined" && this.ui.escalateCaseModal) {
      const modalInstance = new bootstrap.Modal(this.ui.escalateCaseModal);
      modalInstance.show();
    }
  },

  async escalateCase() {
    const formData = {
      escalated_to: this.ui.escalateTo.value || null,
      escalation_note: this.ui.escalationNote.value || null,
    };

    try {
      const response = await this.api(`/students/welfare-case/${this.state.selectedCaseId}/escalate`, "POST", formData);
      this.notify("Case escalated successfully", "success");
      if (typeof bootstrap !== "undefined" && this.ui.escalateCaseModal) {
        const modalInstance = bootstrap.Modal.getInstance(this.ui.escalateCaseModal);
        modalInstance?.hide();
      }
      this.loadCaseDetails(this.state.selectedCaseId);
    } catch (error) {
      this.notify(error.message || "Failed to escalate case", "error");
    }
  },

  setLoading(loading) {
    this.ui.casesLoading?.classList.toggle("d-none", !loading);
    this.ui.casesError?.classList.add("d-none");
    this.ui.casesForbidden?.classList.add("d-none");
  },

  showError(message) {
    if (!this.ui.casesError) return;
    this.ui.casesError.textContent = message;
    this.ui.casesError.classList.remove("d-none");
  },

  showForbidden() {
    this.ui.casesLoading?.classList.add("d-none");
    this.ui.casesError?.classList.add("d-none");
    this.ui.casesEmpty?.classList.add("d-none");
    this.ui.casesForbidden?.classList.remove("d-none");
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
      option.value = item.id ?? item.value ?? "";
      option.textContent = item.name || item.full_name || item.class_name || item.stream_name || item.label || option.value;
      select.appendChild(option);
    });
  },

  api: async function (endpoint, method = "GET", data = null) {
    // ALL HTTP goes through API.callAPI (aliased apiCall) in js/api.js.
    // It returns response.data on success and throws {message} on failure.
    // Re-wrap into the envelope shape this.unwrap() callers expect.
    try {
      const result = await API.callAPI(endpoint, method, data);
      return { success: true, data: result };
    } catch (err) {
      throw new Error((err && err.message) || "Request failed.");
    }
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
  StudentWelfareController.init(),
);

window.StudentWelfareController = StudentWelfareController;
