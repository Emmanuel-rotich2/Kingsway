/**
 * New Applications Controller
 * Handles the new applications page - receiving, tracking, and creating applications
 */

console.log("new_applications.js loaded successfully");

const newApplicationsController = {
  applications: [],
  filteredApplications: [],
  parents: [],
  academicYears: [],
  initialized: false,
  dom: {},

  init: async function () {
    if (this.initialized) return;
    this.initialized = true;

    console.log("newApplicationsController: Initializing...");

    try {
      if (
        window.AuthContext &&
        typeof window.AuthContext.isAuthenticated === "function"
      ) {
        if (!window.AuthContext.isAuthenticated()) {
          console.warn(
            "newApplicationsController: Not authenticated, redirecting to login",
          );
          window.location.href = `${window.APP_BASE || ""}/index.php`;
          return;
        }
      } else {
        console.warn("newApplicationsController: AuthContext not available");
      }

      // Check for URL parameters that might trigger auto-view
      const urlParams = new URLSearchParams(window.location.search);
      const applicationId = urlParams.get('application_id');
      const viewId = urlParams.get('view');
      
      if (applicationId && !viewId) {
        console.log("Auto-viewing application from URL parameter:", applicationId);
        // Remove the parameter from URL without triggering page reload
        const newUrl = window.location.pathname + window.location.search.replace(/[?&]application_id=[^&]+/, '').replace(/^&/, '?');
        window.history.replaceState({}, '', newUrl);
        // Auto-view the application
        setTimeout(() => this.viewApplication(applicationId), 100);
      }

      this.cacheDom();
      this.validateRequiredDom();
      this.attachEvents();
      this.setupSpecialNeedsToggle();

      await this.loadMetadata();
      await this.loadApplications();

      console.log("newApplicationsController: Initialization complete");
    } catch (error) {
      console.error("Failed to initialize New Applications Controller:", error);
      this.showError(
        error.message || "Failed to initialize new applications page.",
      );
    }
  },

  apiCall: function (endpoint, method = "GET", data = null) {
    if (window.API && typeof window.API.callAPI === "function") {
      return window.API.callAPI(endpoint, method, data);
    }

    if (window.API && typeof window.API.apiCall === "function") {
      return window.API.apiCall(endpoint, method, data);
    }

    throw new Error(
      "API helper not available. Expected window.API.callAPI or window.API.apiCall.",
    );
  },

  cacheDom: function () {
    this.dom = {
      applicationsTableBody: document.getElementById("applicationsTableBody"),

      filterApplicantType: document.getElementById("filterApplicantType"),
      filterClass: document.getElementById("filterClass"),
      filterStatus: document.getElementById("filterStatus"),
      searchApplications: document.getElementById("searchApplications"),

      parentSelect: document.getElementById("parentSelect"),
      academicYearSelect: document.getElementById("academicYearSelect"),

      newApplicationBtn: document.getElementById("newApplicationBtn"),
      newApplicationModal: document.getElementById("newApplicationModal"),
      newApplicationForm: document.getElementById("newApplicationForm"),
      viewApplicationModal: document.getElementById("viewApplicationModal"),
      viewApplicationContent: document.getElementById("viewApplicationContent"),
      startIntakeBtn: document.getElementById("startIntakeBtn"),

      hasSpecialNeeds: document.getElementById("hasSpecialNeeds"),
      specialNeedsDetailsGroup: document.getElementById(
        "specialNeedsDetailsGroup",
      ),

      statTotalApplications: document.getElementById("statTotalApplications"),
      statNewToday: document.getElementById("statNewToday"),
      statIntakePending: document.getElementById("statIntakePending"),
      statDocumentsPending: document.getElementById("statDocumentsPending"),
    };
  },

  validateRequiredDom: function () {
    if (!this.dom.applicationsTableBody) {
      console.error("Missing required element: #applicationsTableBody");
    }

    if (!this.dom.newApplicationForm) {
      console.warn("Missing optional element: #newApplicationForm");
    }

    if (!this.dom.newApplicationModal) {
      console.warn("Missing optional element: #newApplicationModal");
    }

    if (!this.dom.viewApplicationModal || !this.dom.viewApplicationContent) {
      console.warn("Missing optional element: #viewApplicationModal or #viewApplicationContent");
    }
  },

  attachEvents: function () {
    this.safeListen("newApplicationBtn", "click", () =>
      this.showNewApplicationModal(),
    );

    this.safeListen("filterApplicantType", "change", () => this.applyFilters());
    this.safeListen("filterClass", "change", () => this.applyFilters());
    this.safeListen("filterStatus", "change", () => this.applyFilters());

    this.safeListen(
      "searchApplications",
      "input",
      this.debounce(() => this.applyFilters(), 300),
    );

    if (this.dom.newApplicationForm) {
      this.dom.newApplicationForm.addEventListener("submit", (event) => {
        event.preventDefault();
        this.submitNewApplication(new FormData(this.dom.newApplicationForm));
      });
    }
  },

  safeListen: function (id, event, handler) {
    const element = document.getElementById(id);

    if (!element) {
      console.warn(`Missing element #${id}; listener skipped.`);
      return;
    }

    element.addEventListener(event, handler);
  },

  setupSpecialNeedsToggle: function () {
    if (!this.dom.hasSpecialNeeds || !this.dom.specialNeedsDetailsGroup) {
      return;
    }

    this.dom.specialNeedsDetailsGroup.style.display = this.dom.hasSpecialNeeds
      .checked
      ? "block"
      : "none";

    this.dom.hasSpecialNeeds.addEventListener("change", (event) => {
      this.dom.specialNeedsDetailsGroup.style.display = event.target.checked
        ? "block"
        : "none";
    });
  },

  loadMetadata: async function () {
    await this.loadParents();

    const currentYear = new Date().getFullYear();
    this.academicYears = [currentYear, currentYear + 1];
    this.populateAcademicYearDropdown();
  },

  loadParents: async function () {
    try {
      const response = await this.apiCall("/students/parents/list", "GET");
      const parents = this.extractList(response);

      this.parents = parents;
      this.populateParentDropdown();

      console.log("Parents loaded:", this.parents.length);
    } catch (error) {
      console.error("Failed to load parents:", error);
      this.parents = [];
      this.populateParentDropdown();
    }
  },

  populateParentDropdown: function () {
    const select = this.dom.parentSelect;
    if (!select) return;

    select.innerHTML = '<option value="">Select Parent/Guardian</option>';

    this.parents.forEach((parent) => {
      const firstName = parent.first_name || parent.firstname || "";
      const lastName = parent.last_name || parent.lastname || "";
      const phone =
        parent.phone_1 ||
        parent.phone ||
        parent.phone_number ||
        parent.mobile ||
        "No phone";

      const option = document.createElement("option");
      option.value = parent.id;
      option.textContent = `${firstName} ${lastName} (${phone})`.trim();
      select.appendChild(option);
    });
  },

  populateAcademicYearDropdown: function () {
    const select = this.dom.academicYearSelect;
    if (!select) return;

    select.innerHTML = '<option value="">Select Year</option>';

    this.academicYears.forEach((year) => {
      const option = document.createElement("option");
      option.value = String(year);
      option.textContent = String(year);
      select.appendChild(option);
    });

    if (this.academicYears.length > 0) {
      select.value = String(this.academicYears[0]);
    }
  },

  loadApplications: async function () {
    this.setTableLoading();

    try {
      const response = await this.apiCall("/admission/queues", "GET");

      console.log("Admission queues response:", response);
      console.log("Response success field:", response.success);
      console.log("Response structure:", JSON.stringify(response, null, 2));

      if (!this.isSuccessfulResponse(response)) {
        throw new Error(response?.message || "Failed to load applications.");
      }

      const payload = this.unwrapPayload(response);
      const queues = payload?.queues || {};
      const summary = payload?.summary || {};

      console.log("Payload:", payload);
      console.log("Queues:", queues);
      console.log("Summary:", summary);

      const allApplications = [];

      Object.keys(queues).forEach((queueName) => {
        if (!Array.isArray(queues[queueName])) return;

        queues[queueName].forEach((application) => {
          allApplications.push({
            ...application,
            queue_name: queueName,
          });
        });
      });

      this.applications = allApplications;
      this.updateSummaryCards(summary);
      this.applyFilters();

      console.log("Applications loaded:", this.applications.length);
    } catch (error) {
      console.error("Failed to load applications:", error);
      this.applications = [];
      this.filteredApplications = [];
      this.showError(error.message || "Failed to load applications.");
    }
  },

  setTableLoading: function () {
    if (!this.dom.applicationsTableBody) return;

    this.dom.applicationsTableBody.innerHTML = `
      <tr>
        <td colspan="9" class="text-center py-4">
          <div class="spinner-border text-success" role="status"></div>
          <div class="mt-2 text-muted">Loading applications...</div>
        </td>
      </tr>
    `;
  },

  updateSummaryCards: function (summary = {}) {
    this.setText("statTotalApplications", summary.total_pending ?? 0);
    this.setText(
      "statNewToday",
      summary.review_pending ?? summary.application_received ?? 0,
    );
    this.setText(
      "statIntakePending",
      summary.documents_pending ?? 0,
    );
    this.setText(
      "statDocumentsPending",
      summary.documents_pending ?? 0,
    );
  },

  setText: function (domKey, value) {
    if (this.dom[domKey]) {
      this.dom[domKey].textContent = value;
    }
  },

  applyFilters: function () {
    const applicantType = this.dom.filterApplicantType?.value || "";
    const classFilter = this.dom.filterClass?.value || "";
    const statusFilter = this.dom.filterStatus?.value || "";
    const searchTerm = (this.dom.searchApplications?.value || "")
      .trim()
      .toLowerCase();

    this.filteredApplications = this.applications.filter((application) => {
      const applicationApplicantType =
        application.applicant_type ||
        application.enrollment_type ||
        application.type ||
        "";

      const applicationClass =
        application.grade_applying_for ||
        application.class_applied_for ||
        application.class_name ||
        "";

      const applicationStatus = application.status || "";

      if (applicantType && applicationApplicantType !== applicantType) {
        return false;
      }

      if (classFilter && String(applicationClass) !== String(classFilter)) {
        return false;
      }

      if (statusFilter && applicationStatus !== statusFilter) {
        return false;
      }

      if (searchTerm) {
        const searchFields = [
          application.application_no,
          application.applicant_name,
          application.first_name,
          application.middle_name,
          application.last_name,
          application.parent_first_name,
          application.parent_last_name,
          application.guardian_name,
          application.phone_1,
          application.parent_phone_1,
          application.guardian_phone,
        ]
          .filter(Boolean)
          .join(" ")
          .toLowerCase();

        if (!searchFields.includes(searchTerm)) {
          return false;
        }
      }

      return true;
    });

    this.renderApplications(this.filteredApplications);
  },

  renderApplications: function (applications = []) {
    if (!this.dom.applicationsTableBody) return;

    if (!Array.isArray(applications) || applications.length === 0) {
      this.dom.applicationsTableBody.innerHTML = `
        <tr>
          <td colspan="9" class="text-center py-4">
            <div class="text-muted">
              <i class="bi bi-inbox display-4 d-block mb-2"></i>
              No applications found
            </div>
          </td>
        </tr>
      `;
      return;
    }

    this.dom.applicationsTableBody.innerHTML = applications
      .map((application) => this.renderApplicationRow(application))
      .join("");
  },

  renderApplicationRow: function (application) {
    const id = application.id;
    const applicationNo = this.escapeHtml(application.application_no || "N/A");
    const applicantName = this.escapeHtml(
      application.applicant_name ||
        [application.first_name, application.middle_name, application.last_name]
          .filter(Boolean)
          .join(" ") ||
        "N/A",
    );

    const gender = this.escapeHtml(this.formatGender(application.gender));
    const classApplied = this.escapeHtml(
      application.grade_applying_for ||
        application.class_applied_for ||
        application.class_name ||
        "N/A",
    );

    const guardianName = this.escapeHtml(
      [application.parent_first_name, application.parent_last_name]
        .filter(Boolean)
        .join(" ") ||
        application.guardian_name ||
        "N/A",
    );

    const guardianPhone = this.escapeHtml(
      application.parent_phone_1 ||
        application.phone_1 ||
        application.guardian_phone ||
        "N/A",
    );

    const status = application.current_stage || application.status || "unknown";
    const statusLabel = this.escapeHtml(this.formatStatus(status));
    const queueName = this.escapeHtml(
      this.formatQueueName(application.queue_name),
    );
    const createdAt = this.escapeHtml(this.formatDate(application.created_at));

    return `
      <tr>
        <td><strong>${applicationNo}</strong></td>
        <td>
          <div class="fw-semibold">${applicantName}</div>
          <small class="text-muted">${queueName}</small>
        </td>
        <td>${gender}</td>
        <td>${classApplied}</td>
        <td>${guardianName}</td>
        <td>${guardianPhone}</td>
        <td>
          <span class="badge bg-${this.getStatusBadgeClass(status)}">
            ${statusLabel}
          </span>
        </td>
        <td>${createdAt}</td>
        <td>
          <div class="btn-group btn-group-sm">
            <button
              type="button"
              class="btn btn-outline-primary"
              onclick="event.preventDefault(); event.stopPropagation(); window.newApplicationsController.viewApplication(${Number(id)})"
              title="View / Continue Intake"
            >
              <i class="bi bi-eye"></i>
            </button>
            <button
              type="button"
              class="btn btn-outline-success"
              onclick="event.preventDefault(); event.stopPropagation(); window.newApplicationsController.startIntake(${Number(id)})"
              title="Start Intake"
            >
              <i class="bi bi-arrow-right"></i>
            </button>
          </div>
        </td>
      </tr>
    `;
  },

  getStatusBadgeClass: function (status) {
    const statusMap = {
      draft: "secondary",
      submitted: "primary",
      intake_in_progress: "info",
      intake_completed: "info",
      documents_pending: "warning",
      documents_verified: "success",
      interview_pending: "info",
      interview_scheduled: "info",
      interview_completed: "primary",
      placement_pending: "secondary",
      placement_recommended: "primary",
      fee_pending: "warning",
      payment_pending: "warning",
      fee_paid: "success",
      ht_review_pending: "warning",
      ht_approved: "success",
      ht_rejected: "danger",
      waitlisted: "secondary",
      ready_for_student_creation: "primary",
      student_created: "success",
      class_assigned: "success",
      id_generated: "success",
      enrollment_pending: "warning",
      enrollment_pending_confirmation: "warning",
      enrolled: "success",
      enrolled_confirmed: "success",
      cancelled: "danger",
      rejected: "danger",
    };

    return statusMap[status] || "secondary";
  },

  formatStatus: function (status) {
    if (!status) return "Unknown";

    return String(status)
      .replace(/_/g, " ")
      .replace(/\b\w/g, (letter) => letter.toUpperCase());
  },

  formatQueueName: function (queueName) {
    if (!queueName) return "Queue not set";

    return String(queueName)
      .replace(/_/g, " ")
      .replace(/\b\w/g, (letter) => letter.toUpperCase());
  },

  formatGender: function (gender) {
    const value = String(gender || "").toLowerCase();

    const genders = {
      male: "Male",
      female: "Female",
      other: "Other",
      m: "Male",
      f: "Female",
    };

    return genders[value] || gender || "N/A";
  },

  formatDate: function (dateString) {
    if (!dateString) return "N/A";

    const date = new Date(dateString);

    if (Number.isNaN(date.getTime())) {
      return "N/A";
    }

    return date.toLocaleDateString("en-GB", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    });
  },

  showNewApplicationModal: function () {
    if (!this.dom.newApplicationModal) {
      console.error("Missing #newApplicationModal");
      this.notify("error", "New application form is not available.");
      return;
    }

    if (!window.bootstrap || !bootstrap.Modal) {
      console.error("Bootstrap Modal is not available.");
      this.notify("error", "Modal system is not available.");
      return;
    }

    const modal = new bootstrap.Modal(this.dom.newApplicationModal);
    modal.show();
  },

  submitNewApplication: async function (formData) {
    if (!this.dom.newApplicationForm) {
      this.notify("error", "Application form is not available.");
      return;
    }

    const submitBtn = this.dom.newApplicationForm.querySelector(
      'button[type="submit"]',
    );

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    }

    const data = {};
    formData.forEach((value, key) => {
      data[key] = value;
    });

    try {
      console.log("Submitting application data:", data);

      const response = await this.apiCall(
        "/admission/submit-application",
        "POST",
        data,
      );

      console.log("Submit application response:", response);

      if (!this.isSuccessfulResponse(response)) {
        throw new Error(response?.message || "Failed to submit application.");
      }

      this.notify("success", "Application submitted successfully.");

      const modalInstance = bootstrap.Modal.getInstance(
        this.dom.newApplicationModal,
      );

      if (modalInstance) {
        modalInstance.hide();
      }

      this.dom.newApplicationForm.reset();
      await this.loadApplications();
    } catch (error) {
      console.error("Failed to submit application:", error);
      this.notify("error", error.message || "Failed to submit application.");
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML =
          '<i class="bi bi-send me-1"></i>Submit Application';
      }
    }
  },

  viewApplication: async function (applicationId) {
    if (!applicationId || Number.isNaN(Number(applicationId))) {
      this.notify("error", "Invalid application selected.");
      return;
    }

    try {
      const response = await this.apiCall(
        `/admission/application/${applicationId}`,
        "GET",
      );

      if (!this.isSuccessfulResponse(response)) {
        throw new Error(response?.message || "Failed to load application.");
      }

      const payload = this.unwrapPayload(response);

      if (!payload?.application) {
        throw new Error("Application details were not returned.");
      }

      this.renderApplicationDetails(payload);
      this.showApplicationDetailsModal(applicationId);
    } catch (error) {
      console.error("Failed to load application:", error);
      this.notify("error", error.message || "Failed to load application.");
    }
  },

  showApplicationDetailsModal: function (applicationId) {
    if (!this.dom.viewApplicationModal) {
      this.notify("error", "Application details modal is not available.");
      return;
    }

    if (this.dom.startIntakeBtn) {
      this.dom.startIntakeBtn.onclick = () => {
        const modal = bootstrap.Modal.getInstance(this.dom.viewApplicationModal);
        if (modal) modal.hide();
        this.startIntake(applicationId);
      };
    }

    const modal = new bootstrap.Modal(this.dom.viewApplicationModal);
    modal.show();
  },

  renderApplicationDetails: function (payload) {
    if (!this.dom.viewApplicationContent) return;

    const app = payload.application || {};
    const documents = Array.isArray(payload.documents) ? payload.documents : [];
    const workflowData = payload.workflow_data || {};
    const stageMeta = payload.stage_metadata || {};
    const parentName = [app.parent_first_name, app.parent_last_name]
      .filter(Boolean)
      .join(" ") || "N/A";
    const currentStage =
      stageMeta.display_name || this.formatQueueName(stageMeta.current_stage || app.current_stage);
    const documentsHtml = documents.length
      ? documents.map((document) => {
          const status = document.verification_status || "pending";
          return `
            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
              <div>
                <i class="bi bi-file-earmark me-2"></i>
                ${this.escapeHtml(this.formatStatus(document.document_type || "Document"))}
                ${
                  Number(document.is_mandatory) === 1
                    ? '<span class="badge bg-danger ms-1">Required</span>'
                    : ""
                }
              </div>
              <span class="badge bg-${this.getStatusBadgeClass(status)}">
                ${this.escapeHtml(this.formatStatus(status))}
              </span>
            </div>
          `;
        }).join("")
      : '<p class="text-muted mb-0">No documents uploaded.</p>';

    this.dom.viewApplicationContent.innerHTML = `
      <div class="row g-4">
        <div class="col-lg-6">
          <h6 class="fw-semibold mb-3">Applicant Information</h6>
          <dl class="row mb-0">
            <dt class="col-sm-5">Application No</dt>
            <dd class="col-sm-7">${this.escapeHtml(app.application_no || "N/A")}</dd>
            <dt class="col-sm-5">Name</dt>
            <dd class="col-sm-7">${this.escapeHtml(app.applicant_name || "N/A")}</dd>
            <dt class="col-sm-5">Date of Birth</dt>
            <dd class="col-sm-7">${this.escapeHtml(this.formatDate(app.date_of_birth))}</dd>
            <dt class="col-sm-5">Gender</dt>
            <dd class="col-sm-7">${this.escapeHtml(this.formatGender(app.gender))}</dd>
            <dt class="col-sm-5">Grade Applying For</dt>
            <dd class="col-sm-7">${this.escapeHtml(app.grade_applying_for || "N/A")}</dd>
            <dt class="col-sm-5">Status</dt>
            <dd class="col-sm-7">
              <span class="badge bg-${this.getStatusBadgeClass(app.status)}">
                ${this.escapeHtml(this.formatStatus(app.status))}
              </span>
            </dd>
          </dl>
        </div>
        <div class="col-lg-6">
          <h6 class="fw-semibold mb-3">Parent / Guardian</h6>
          <dl class="row mb-0">
            <dt class="col-sm-5">Name</dt>
            <dd class="col-sm-7">${this.escapeHtml(parentName)}</dd>
            <dt class="col-sm-5">Phone</dt>
            <dd class="col-sm-7">${this.escapeHtml(app.phone_1 || app.parent_phone_1 || "N/A")}</dd>
            <dt class="col-sm-5">Email</dt>
            <dd class="col-sm-7">${this.escapeHtml(app.parent_email || "N/A")}</dd>
            <dt class="col-sm-5">Current Stage</dt>
            <dd class="col-sm-7">${this.escapeHtml(currentStage)}</dd>
            <dt class="col-sm-5">Created</dt>
            <dd class="col-sm-7">${this.escapeHtml(this.formatDate(app.created_at))}</dd>
          </dl>
        </div>
      </div>

      <hr>

      <div class="row g-4">
        <div class="col-lg-6">
          <h6 class="fw-semibold mb-3">Documents (${documents.length})</h6>
          ${documentsHtml}
        </div>
        <div class="col-lg-6">
          <h6 class="fw-semibold mb-3">Workflow Data</h6>
          ${this.renderWorkflowData(workflowData)}
        </div>
      </div>
    `;
  },

  renderWorkflowData: function (workflowData) {
    if (!workflowData || Object.keys(workflowData).length === 0) {
      return '<p class="text-muted mb-0">No workflow details recorded.</p>';
    }

    return `
      <dl class="row mb-0">
        ${Object.entries(workflowData)
          .map(([key, value]) => `
            <dt class="col-sm-5">${this.escapeHtml(this.formatStatus(key))}</dt>
            <dd class="col-sm-7">${this.escapeHtml(
              typeof value === "object" ? JSON.stringify(value) : value || "N/A",
            )}</dd>
          `)
          .join("")}
      </dl>
    `;
  },

  startIntake: function (applicationId) {
    if (!applicationId || Number.isNaN(Number(applicationId))) {
      this.notify("error", "Invalid application selected.");
      return;
    }

    const appBase = window.APP_BASE || "";
    window.location.href = `${appBase}/home.php?route=manage_students_admissions&application_id=${encodeURIComponent(
      applicationId,
    )}`;
  },

  showError: function (message) {
    if (!this.dom.applicationsTableBody) {
      console.error(message);
      return;
    }

    this.dom.applicationsTableBody.innerHTML = `
      <tr>
        <td colspan="9" class="text-center py-4">
          <div class="text-danger">
            <i class="bi bi-exclamation-triangle display-4 d-block mb-2"></i>
            ${this.escapeHtml(message)}
          </div>
        </td>
      </tr>
    `;
  },

  notify: function (type, message) {
    if (typeof window.showNotification === "function") {
      window.showNotification(type, message);
      return;
    }

    if (window.API && typeof window.API.showNotification === "function") {
      window.API.showNotification(message, type);
      return;
    }

    if (type === "error") {
      console.error(message);
      alert(`Error: ${message}`);
      return;
    }

    console.log(`${type}: ${message}`);
    alert(message);
  },

  isSuccessfulResponse: function (response) {
    if (!response) return false;

    if (response.success === true) return true;
    if (response.status === true) return true;
    if (response.ok === true) return true;

    if (response.success === false || response.status === false) {
      return false;
    }

    // For admission queues endpoint, check if it has the expected structure
    if (response.queues !== undefined || response.summary !== undefined) {
      return true;
    }

    if (
      response.application !== undefined ||
      response.documents !== undefined ||
      response.workflow_data !== undefined ||
      response.stage_metadata !== undefined
    ) {
      return true;
    }

    return response.data !== undefined;
  },

  unwrapPayload: function (response) {
    if (!response) return null;

    // For admission queues endpoint, data is directly in response
    if (response.queues !== undefined || response.summary !== undefined) {
      return response;
    }

    if (response.data && response.data.data !== undefined) {
      return response.data.data;
    }

    if (response.data !== undefined) {
      return response.data;
    }

    return response;
  },

  extractList: function (response) {
    const payload = this.unwrapPayload(response);

    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload?.items)) return payload.items;
    if (Array.isArray(payload?.parents)) return payload.parents;
    if (Array.isArray(payload?.guardians)) return payload.guardians;

    return [];
  },

  escapeHtml: function (value) {
    return String(value ?? "").replace(/[&<>"']/g, (character) => {
      const entities = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      };

      return entities[character];
    });
  },

  debounce: function (func, wait) {
    let timeout;

    return (...args) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), wait);
    };
  },
};

window.newApplicationsController = newApplicationsController;

document.addEventListener("DOMContentLoaded", () => {
  window.newApplicationsController.init();
});
