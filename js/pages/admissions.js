/**
 * Admissions Workflow Controller
 *
 * Manages the complete 7-stage admission workflow:
 * 1. Application Submission
 * 2. Document Upload & Verification
 * 3. Interview Scheduling (skipped for ECD/PP1/PP2/Grade1/Grade7)
 * 4. Interview Assessment
 * 5. Placement Offer
 * 6. Fee Payment
 * 7. Enrollment Completion
 *
 * Role-specific views:
 * - Registrar/Deputy/Head: Document verification, interview scheduling
 * - Headteacher: Interview assessment, placement decisions
 * - Accountant: Fee payment recording
 * - Admin: All stages, enrollment completion
 */

const AdmissionsController = {
  // State management
  state: {
    currentTab: "documents_pending",
    requestedTab: null,
    queues: {},
    summary: {},
    allowedTabs: {},
    selectedApplication: null,
    userRole: null,
    isLoading: false,
    isInitialized: false,
    referenceData: {
      parents: [],
      academicYears: [],
    },
  },

  // Workflow stage configuration
  stages: {
    documents_pending: {
      label: "Documents",
      icon: "bi-file-earmark-text",
      color: "warning",
      permissions: [
        "admission_documents_verify",
        "admission_documents_approve",
        "admission_documents_validate",
        "admission_applications_verify",
      ],
    },
    interview_pending: {
      label: "Interview",
      icon: "bi-calendar-event",
      color: "info",
      permissions: [
        "admission_interviews_schedule",
        "admission_interviews_create",
        "admission_interviews_edit",
        "admission_applications_schedule",
      ],
    },
    placement_pending: {
      label: "Placement",
      icon: "bi-check-circle",
      color: "primary",
      permissions: [
        "admission_applications_generate",
        "admission_applications_approve",
        "admission_applications_assign",
      ],
    },
    payment_pending: {
      label: "Payment",
      icon: "bi-cash-stack",
      color: "success",
      permissions: [
        "admission_applications_approve",
        "admission_applications_validate",
        "admission_applications_edit",
      ],
    },
    enrollment_pending: {
      label: "Enrollment",
      icon: "bi-person-check",
      color: "dark",
      permissions: [
        "admission_applications_approve_final",
        "admission_applications_approve",
        "admission_applications_validate",
      ],
    },
  },

  // Grades that skip interview — must match DB enum values exactly.
  // Playground = ECD/pre-school level. Grade7-9 = direct entry / CBC transition.
  noInterviewGrades: [
    "Playground", // DB enum value for ECD/pre-school
    "ECD",        // legacy alias kept for compatibility
    "PP1",
    "PP2",
    "Grade1",
    "Grade 1",    // space variant
    "Grade7",
    "Grade 7",    // space variant
    "Grade8",
    "Grade 8",
    "Grade9",
    "Grade 9",
  ],

  transferDocumentGrades: [
    "Grade2",
    "Grade 2",
    "Grade3",
    "Grade 3",
    "Grade4",
    "Grade 4",
    "Grade5",
    "Grade 5",
    "Grade6",
    "Grade 6",
  ],

  actionPermissions: {
    "upload-documents": [
      "admission_documents_upload",
      "admission_documents_create",
      "admission_applications_upload",
    ],
    "verify-documents": [
      "admission_documents_verify",
      "admission_documents_approve",
      "admission_documents_validate",
      "admission_applications_verify",
    ],
    "schedule-interview": [
      "admission_interviews_schedule",
      "admission_applications_schedule",
    ],
    "record-interview": [
      "admission_interviews_create",
      "admission_interviews_edit",
      "admission_interviews_approve",
      "admission_interviews_verify",
    ],
    "generate-placement": [
      "admission_applications_generate",
      "admission_applications_approve",
      "admission_applications_assign",
    ],
    "record-payment": [
      "admission_applications_approve",
      "admission_applications_validate",
      "admission_applications_edit",
    ],
    "complete-enrollment": [
      "admission_applications_approve_final",
      "admission_applications_approve",
      "admission_applications_validate",
    ],
    "new-application": [
      "admission_applications_create",
      "admission_applications_submit",
    ],
  },

  /**
   * Initialize the controller
   */
  async init() {
    if (this.state.isInitialized) {
      return;
    }

    console.log("[AdmissionsController] Initializing...");

    // Get user role from session
    this.state.userRole = this.resolveUserRole();
    this.state.requestedTab = this.resolveRequestedTab();

    // Setup event listeners
    this.setupEventListeners();

    // Load reference data for forms
    await this.loadReferenceData();

    // Load workflow queues
    await this.loadQueues();

    // Render initial view
    this.renderTabs();
    this.switchTab(this.getDefaultTab());

    this.state.isInitialized = true;

    console.log("[AdmissionsController] Initialized");
  },

  resolveUserRole() {
    const roles = AuthContext.getRoles ? AuthContext.getRoles() : [];
    const role = roles[0]?.name || roles[0];
    if (!role) return "guest";
    return String(role)
      .toLowerCase()
      .replace(/[\s/]+/g, "_");
  },

  resolveRequestedTab() {
    try {
      const params = new URLSearchParams(window.location.search || "");
      const tab = (params.get("tab") || "").trim();
      return Object.prototype.hasOwnProperty.call(this.stages, tab) ? tab : null;
    } catch (error) {
      return null;
    }
  },

  hasAnyPermission(permissionCandidates = []) {
    if (window.RoleBasedUI?.hasAnyPermission) {
      return window.RoleBasedUI.hasAnyPermission(permissionCandidates);
    }
    if (window.AuthContext?.hasAnyPermission) {
      return window.AuthContext.hasAnyPermission(permissionCandidates);
    }
    return false;
  },

  canAccessStage(tabKey) {
    if (Object.prototype.hasOwnProperty.call(this.state.allowedTabs, tabKey)) {
      return Boolean(this.state.allowedTabs[tabKey]);
    }

    const stage = this.stages[tabKey];
    if (!stage?.permissions?.length) return false;

    if (this.hasAnyPermission(stage.permissions)) {
      return true;
    }

    return this.hasAdmissionsViewAccess();
  },

  hasAdmissionsViewAccess() {
    if (
      this.hasAnyPermission([
        "admission_view",
        "admission_applications_view_all",
        "admission_applications_view_own",
        "admission_applications_view",
      ])
    ) {
      return true;
    }

    return Boolean(window.__admissionsRouteAuthorized);
  },

  canViewParentContact() {
    const permissionCandidates = [
      "admission_applications_view_all",
      "admission_applications_view_own",
      "admission_applications_view",
      "admission_documents_view_all",
      "admission_documents_view_own",
      "admission_documents_view",
    ];

    return this.hasAnyPermission(permissionCandidates);
  },

  canViewApplicantSensitive() {
    const permissionCandidates = [
      "admission_applications_view_all",
      "admission_applications_view_own",
      "admission_applications_view",
      "students_view_sensitive",
    ];

    return this.hasAnyPermission(permissionCandidates);
  },

  canPerformAction(action) {
    if (action === "view" || action === "refresh") {
      return this.hasAdmissionsViewAccess();
    }

    const permissionCandidates = this.actionPermissions[action] || [];
    if (!permissionCandidates.length) {
      return false;
    }
    return this.hasAnyPermission(permissionCandidates);
  },

  isActionAvailableForPayload(payload, actionKey) {
    const actions = Array.isArray(payload?.available_actions)
      ? payload.available_actions
      : [];
    return actions.includes(actionKey);
  },

  /**
   * Get the default tab based on user role
   */
  getDefaultTab() {
    if (this.state.requestedTab && this.canAccessStage(this.state.requestedTab)) {
      return this.state.requestedTab;
    }

    const priority = [
      "documents_pending",
      "interview_pending",
      "placement_pending",
      "payment_pending",
      "enrollment_pending",
    ];

    for (const tabKey of priority) {
      if (this.canAccessStage(tabKey)) {
        return tabKey;
      }
    }

    return "documents_pending";
  },

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Tab clicks
    document.addEventListener("click", (e) => {
      if (e.target.closest(".admission-tab")) {
        const tab = e.target.closest(".admission-tab").dataset.tab;
        this.switchTab(tab);
      }
    });

    // Special needs toggle
    document.addEventListener("change", (e) => {
      if (e.target && e.target.id === "hasSpecialNeeds") {
        this.toggleSpecialNeeds(e.target.checked);
      }
    });

    // Action buttons
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-action]");
      if (!btn) return;

      const action = btn.dataset.action;
      const applicationId = btn.dataset.applicationId;
      const supportedActions = new Set([
        "view",
        "upload-documents",
        "verify-documents",
        "schedule-interview",
        "record-interview",
        "generate-placement",
        "record-payment",
        "complete-enrollment",
        "new-application",
        "refresh",
      ]);

      if (!supportedActions.has(action)) {
        return;
      }

      const isServerAuthorized = btn.dataset.serverAuthorized === "1";
      if (!isServerAuthorized && !this.canPerformAction(action)) {
        showNotification("You do not have permission to perform this action", "warning");
        return;
      }

      switch (action) {
        case "view":
          this.viewApplication(applicationId);
          break;
        case "upload-documents":
          this.openUploadDocumentsModal(applicationId);
          break;
        case "verify-documents":
          this.openVerifyDocumentsModal(applicationId);
          break;
        case "schedule-interview":
          this.openScheduleInterviewModal(applicationId);
          break;
        case "record-interview":
          this.openRecordInterviewModal(applicationId);
          break;
        case "generate-placement":
          this.openPlacementModal(applicationId);
          break;
        case "record-payment":
          this.openPaymentModal(applicationId);
          break;
        case "complete-enrollment":
          this.completeEnrollment(applicationId);
          break;
        case "new-application":
          this.openNewApplicationModal();
          break;
        case "refresh":
          this.loadQueues();
          break;
      }
    });

    // Form submissions
    document.addEventListener("submit", async (e) => {
      if (e.target.id === "newApplicationForm") {
        e.preventDefault();
        await this.submitNewApplication(e.target);
      }
      if (e.target.id === "verifyDocumentsForm") {
        e.preventDefault();
        await this.submitDocumentVerification(e.target);
      }
      if (e.target.id === "uploadDocumentsForm") {
        e.preventDefault();
        await this.submitUploadDocuments(e.target);
      }
      if (e.target.id === "scheduleInterviewForm") {
        e.preventDefault();
        await this.submitInterviewSchedule(e.target);
      }
      if (e.target.id === "recordInterviewForm") {
        e.preventDefault();
        await this.submitInterviewResults(e.target);
      }
      if (e.target.id === "placementForm") {
        e.preventDefault();
        await this.submitPlacementOffer(e.target);
      }
      if (e.target.id === "paymentForm") {
        e.preventDefault();
        await this.submitPaymentRecord(e.target);
      }
    });
  },

  /**
   * Load reference data for forms (parents, academic years)
   */
  async loadReferenceData() {
    await Promise.all([this.loadParents(), this.loadAcademicYears()]);
  },

  async loadParents() {
    try {
      if (!API?.students?.getParentsList) return;
      const response = await API.students.getParentsList();
      const parents = this.unwrapList(response, "parents");
      this.state.referenceData.parents = parents;
      this.populateParentSelect(parents);
    } catch (error) {
      console.warn("[AdmissionsController] Failed to load parents:", error);
    }
  },

  async loadAcademicYears() {
    try {
      if (!API?.students?.getAllAcademicYears) return;
      const response = await API.students.getAllAcademicYears();
      const years = this.unwrapList(response, "academic_years");
      this.state.referenceData.academicYears = years;
      this.populateAcademicYearSelect(years);
    } catch (error) {
      console.warn(
        "[AdmissionsController] Failed to load academic years:",
        error,
      );
    }
  },

  unwrapList(response, key) {
    if (!response) return [];
    if (Array.isArray(response)) return response;
    if (Array.isArray(response.data)) return response.data;
    if (response.data && Array.isArray(response.data[key]))
      return response.data[key];
    if (Array.isArray(response[key])) return response[key];
    return [];
  },

  unwrapPayload(response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined)
      return response.data.data;
    return response;
  },

  populateParentSelect(parents) {
    const select = document.getElementById("parentSelect");
    if (!select) return;
    const options = parents
      .map((parent) => {
        const name =
          `${parent.first_name || ""} ${parent.last_name || ""}`.trim();
        const contact = parent.phone_1 || parent.email || "No contact";
        return `<option value="${parent.id}">${name || "Parent"} - ${contact}</option>`;
      })
      .join("");

    select.innerHTML =
      '<option value="">Select Parent/Guardian</option>' + options;
  },

  populateAcademicYearSelect(years) {
    const select = document.getElementById("academicYearSelect");
    if (!select) return;

    let optionsHtml = '<option value="">Select Year</option>';
    let hasCurrent = false;

    years.forEach((year) => {
      const yearCode = year.year_code || year.year_name || "";
      let yearValue = year.academic_year || year.year || null;

      if (!yearValue && yearCode) {
        const parts = yearCode.split("/");
        const candidate = parts[parts.length - 1];
        yearValue = parseInt(candidate, 10) || null;
      }

      if (!yearValue && year.start_date) {
        yearValue = new Date(year.start_date).getFullYear();
      }

      if (!yearValue) return;

      const isCurrent = year.is_current === 1 || year.is_current === true;
      if (isCurrent) hasCurrent = true;
      optionsHtml += `<option value="${yearValue}"${
        isCurrent ? " selected" : ""
      }>${yearCode || yearValue}</option>`;
    });

    if (!optionsHtml.includes("option value=") || !years.length) {
      const currentYear = new Date().getFullYear();
      optionsHtml += `<option value="${currentYear}" selected>${currentYear}</option>`;
      hasCurrent = true;
    }

    select.innerHTML = optionsHtml;

    if (!hasCurrent && select.options.length > 1) {
      select.selectedIndex = 1;
    }
  },

  toggleSpecialNeeds(isChecked) {
    const group = document.getElementById("specialNeedsDetailsGroup");
    if (group) {
      group.style.display = isChecked ? "" : "none";
    }
  },

  /**
   * Load workflow queues from API
   */
  async loadQueues() {
    this.state.isLoading = true;
    this.showLoading();

    try {
      const response = await API.admission.getQueues();
      const payload = this.unwrapPayload(response);

      if (payload && payload.queues) {
        this.state.queues = payload.queues;
        this.state.summary = payload.summary || {};
        this.state.allowedTabs = payload.allowed_tabs || {};

        if (!this.canAccessStage(this.state.currentTab)) {
          this.state.currentTab = this.getDefaultTab();
        } else if (
          this.state.requestedTab &&
          this.canAccessStage(this.state.requestedTab)
        ) {
          this.state.currentTab = this.state.requestedTab;
        }

        this.state.requestedTab = null;
        await this.loadStats();
        this.updateTabBadges();
        this.updateStatsRow();
        this.renderTabs();
        this.renderCurrentQueue();
      } else {
        showNotification("Failed to load admissions", "error");
      }
    } catch (error) {
      console.error("[AdmissionsController] Error loading queues:", error);
      showNotification("Error loading admissions data", "error");
    } finally {
      this.state.isLoading = false;
      this.hideLoading();
    }
  },

  /**
   * Render workflow stage tabs
   */
  renderTabs() {
    const tabsContainer = document.getElementById("admissionTabs");
    if (!tabsContainer) return;

    let tabsHtml = "";

    for (const [key, config] of Object.entries(this.stages)) {
      const hasAccess = this.canAccessStage(key);
      if (!hasAccess) continue;

      const count = this.state.summary[key] || 0;
      const isActive = key === this.state.currentTab;

      tabsHtml += `
                <li class="nav-item">
                    <a class="nav-link admission-tab ${
                      isActive ? "active" : ""
                    }" 
                       data-tab="${key}" href="javascript:void(0)">
                        <i class="bi ${config.icon} me-1"></i>
                        ${config.label}
                        <span class="badge bg-${
                          config.color
                        } ms-2" id="badge-${key}">${count}</span>
                    </a>
                </li>
            `;
    }

    tabsContainer.innerHTML = tabsHtml;

    if (!tabsHtml.trim()) {
      const queueContainer = document.getElementById("admissionQueueContent");
      if (queueContainer) {
        queueContainer.innerHTML = `
                <div class="alert alert-info mb-0">
                    <i class="bi bi-shield-lock me-2"></i>
                    You do not have workflow-stage permissions to process admissions in this view.
                </div>
            `;
      }
    }
  },

  /**
   * Switch to a different tab
   */
  switchTab(tabKey) {
    if (!this.canAccessStage(tabKey)) {
      this.state.currentTab = this.getDefaultTab();
    } else {
      this.state.currentTab = tabKey;
    }

    this.syncTabToUrl(this.state.currentTab);

    // Update active states
    document.querySelectorAll(".admission-tab").forEach((tab) => {
      tab.classList.toggle("active", tab.dataset.tab === this.state.currentTab);
    });

    // Render the queue for this tab
    this.renderCurrentQueue();
  },

  syncTabToUrl(tabKey) {
    try {
      const url = new URL(window.location.href);
      if (tabKey) {
        url.searchParams.set("tab", tabKey);
      } else {
        url.searchParams.delete("tab");
      }
      window.history.replaceState({}, "", url.toString());
    } catch (error) {
      // no-op
    }
  },

  /**
   * Update badge counts on tabs
   */
  updateTabBadges() {
    for (const key of Object.keys(this.stages)) {
      const badge = document.getElementById(`badge-${key}`);
      if (badge) {
        badge.textContent = this.state.summary[key] || 0;
      }
    }
  },

  /**
   * Populate stats row cards from summary data
   */
  updateStatsRow() {
    const row = document.getElementById("admissionStatsRow");
    if (!row) return;

    const s = this.state.summary;
    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val ?? "0";
    };
    set("stat-documents-pending", s.documents_pending);
    set("stat-interview-pending", s.interview_pending);
    set("stat-placement-pending", s.placement_pending);
    // 'enrolled' comes from stats endpoint; keep graceful fallback.
    set("stat-enrolled", s.enrolled ?? "–");

    row.style.display = "";
  },

  /**
   * Render the current queue table
   */
  renderCurrentQueue() {
    const container = document.getElementById("admissionQueueContent");
    if (!container) return;

    const tabKey = this.state.currentTab;
    const queue = this.state.queues[tabKey] || [];
    const config = this.stages[tabKey];

    if (!config) {
      container.innerHTML = `
                <div class="alert alert-info mb-0">
                    <i class="bi bi-shield-lock me-2"></i>
                    Admissions workflow stages are not available for your current permissions.
                </div>
            `;
      return;
    }

    if (queue.length === 0) {
      container.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi ${config.icon} display-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No ${config.label.toLowerCase()} pending</h5>
                    <p class="text-muted">All applications at this stage have been processed.</p>
                </div>
            `;
      return;
    }

    const tableHtml = this.renderQueueTable(queue, tabKey);
    container.innerHTML = tableHtml;
  },

  /**
   * Render a queue as a table
   */
  renderQueueTable(queue, tabKey) {
    const defaultActions = this.getActionsForTab(tabKey);

    let rows = queue
      .map((app) => {
        const parentName =
          `${app.parent_first_name || ""} ${
            app.parent_last_name || ""
          }`.trim() || "N/A";
        const showParentContact = this.canViewParentContact();
        const parentContact = showParentContact
          ? app.phone_1 || app.parent_email || "-"
          : "Restricted";
        const createdDate = new Date(app.created_at).toLocaleDateString();

        let statusBadge = this.getStatusBadge(app.status);
        const usingServerActions = Array.isArray(app.available_actions);
        const rowActions = usingServerActions ? app.available_actions : defaultActions;

        let actionButtons = rowActions
          .map((action) =>
            this.renderActionButton(action, app.id, usingServerActions),
          )
          .join(" ");

        // Add document count for documents_pending tab
        let extraInfo = "";
        if (tabKey === "documents_pending" && app.doc_count !== undefined) {
          extraInfo = `<small class="text-muted">${app.verified_count || 0}/${
            app.doc_count || 0
          } docs verified</small>`;
        }

        return `
                <tr>
                    <td>
                        <strong>${app.application_no}</strong><br>
                        ${extraInfo}
                    </td>
                    <td>
                        ${app.applicant_name}<br>
                        <small class="text-muted">${
                          app.grade_applying_for
                        }</small>
                    </td>
                    <td>
                      ${parentName}<br>
                      <small class="text-muted">${parentContact}</small>
                    </td>
                    <td>${statusBadge}</td>
                    <td>${createdDate}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" data-action="view" data-application-id="${
                              app.id
                            }">
                                <i class="bi bi-eye"></i>
                            </button>
                            ${actionButtons}
                        </div>
                    </td>
                </tr>
            `;
      })
      .join("");

    return `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Application #</th>
                            <th>Applicant</th>
                            <th>Parent/Guardian</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                </table>
            </div>
        `;
  },

  /**
   * Get actions available for a tab
   */
  getActionsForTab(tabKey) {
    switch (tabKey) {
      case "documents_pending":
        return ["upload-documents", "verify-documents"].filter((action) =>
          this.canPerformAction(action),
        );
      case "interview_pending":
        if (this.canPerformAction("record-interview")) {
          if (this.canPerformAction("schedule-interview")) {
            return ["schedule-interview", "record-interview"];
          }
          return ["record-interview"];
        }
        return this.canPerformAction("schedule-interview")
          ? ["schedule-interview"]
          : [];
      case "placement_pending":
        return this.canPerformAction("generate-placement")
          ? ["generate-placement"]
          : [];
      case "payment_pending":
        return this.canPerformAction("record-payment")
          ? ["record-payment"]
          : [];
      case "enrollment_pending":
        return this.canPerformAction("complete-enrollment")
          ? ["complete-enrollment"]
          : [];
      default:
        return [];
    }
  },

  /**
   * Render an action button
   */
  renderActionButton(action, applicationId, serverAuthorized = false) {
    const configs = {
      "upload-documents": {
        icon: "bi-paperclip",
        color: "secondary",
        title: "Upload Documents",
      },
      "verify-documents": {
        icon: "bi-check-circle",
        color: "primary",
        title: "Verify Documents",
      },
      "schedule-interview": {
        icon: "bi-calendar-plus",
        color: "info",
        title: "Schedule Interview",
      },
      "record-interview": {
        icon: "bi-clipboard-check",
        color: "success",
        title: "Record Results",
      },
      "generate-placement": {
        icon: "bi-award",
        color: "primary",
        title: "Generate Placement",
      },
      "record-payment": {
        icon: "bi-cash",
        color: "success",
        title: "Record Payment",
      },
      "complete-enrollment": {
        icon: "bi-person-check",
        color: "dark",
        title: "Complete Enrollment",
      },
    };

    const config = configs[action];
    if (!config) return "";

    return `
            <button class="btn btn-${config.color}" data-action="${action}" 
                    data-application-id="${applicationId}" title="${config.title}"
                    data-server-authorized="${serverAuthorized ? "1" : "0"}">
                <i class="bi ${config.icon}"></i>
            </button>
        `;
  },

  /**
   * Get status badge HTML
   */
  getStatusBadge(status) {
    const configs = {
      submitted: { color: "secondary", text: "Submitted" },
      documents_pending: { color: "warning", text: "Docs Pending" },
      documents_verified: { color: "info", text: "Docs Verified" },
      placement_offered: { color: "primary", text: "Placement Offered" },
      fees_pending: { color: "warning", text: "Fees Pending" },
      enrolled: { color: "success", text: "Enrolled" },
      cancelled: { color: "danger", text: "Cancelled" },
    };

    const config = configs[status] || { color: "secondary", text: status };
    return `<span class="badge bg-${config.color}">${config.text}</span>`;
  },

  /**
   * View application details
   */
  async viewApplication(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      const payload = this.unwrapPayload(response);

      if (!payload || !payload.application) {
        showNotification("Failed to load application details", "error");
        return;
      }

      const { application, documents, workflow_data, available_actions } =
        payload;
      this.state.selectedApplication = payload;

      // Render in modal
      this.showApplicationModal(
        application,
        documents,
        workflow_data,
        available_actions,
      );
    } catch (error) {
      console.error("[AdmissionsController] Error viewing application:", error);
      showNotification("Error loading application", "error");
    }
  },

  /**
   * Show application details modal
   */
  showApplicationModal(application, documents, workflowData, availableActions) {
    const modal = document.getElementById("applicationDetailModal");
    if (!modal) return;

    const parentName = `${application.parent_first_name || ""} ${
      application.parent_last_name || ""
    }`.trim();
    const showSensitive = this.canViewApplicantSensitive();
    const showParentContact = this.canViewParentContact();
    const parentDisplay = showParentContact
      ? parentName || "N/A"
      : "Restricted";
    const parentPhone = showParentContact
      ? application.phone_1 || "N/A"
      : "Restricted";
    const parentEmail = showParentContact
      ? application.parent_email || "N/A"
      : "Restricted";
    const dobValue = showSensitive
      ? application.date_of_birth || "N/A"
      : "Restricted";

    // Build documents list
    let docsHtml =
      documents.length === 0
        ? '<p class="text-muted">No documents uploaded</p>'
        : documents
            .map((doc) => {
              const statusClass =
                doc.verification_status === "verified"
                  ? "success"
                  : doc.verification_status === "rejected"
                    ? "danger"
                    : "warning";
              return `
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <i class="bi bi-file-earmark me-2"></i>
                            ${doc.document_type}
                            ${
                              doc.is_mandatory
                                ? '<span class="badge bg-danger ms-1">Required</span>'
                                : ""
                            }
                        </div>
                        <span class="badge bg-${statusClass}">${
                          doc.verification_status
                        }</span>
                    </div>
                `;
            })
            .join("");

    // Build workflow timeline
    let timelineHtml = this.buildWorkflowTimeline(
      application.current_stage,
      workflowData,
    );

    // Build action buttons
    const normalizedActions = Array.isArray(availableActions)
      ? availableActions
      : [];
    let actionsHtml = normalizedActions
      .map((action) => this.renderActionButton(action, application.id, true))
      .join(" ");

    modal.querySelector(".modal-body").innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Applicant Information</h6>
                    <dl class="row">
                        <dt class="col-5">Application #</dt>
                        <dd class="col-7">${application.application_no}</dd>
                        <dt class="col-5">Name</dt>
                        <dd class="col-7">${application.applicant_name}</dd>
                        <dt class="col-5">Grade</dt>
                        <dd class="col-7">${application.grade_applying_for}</dd>
                        <dt class="col-5">Date of Birth</dt>
                        <dd class="col-7">${dobValue}</dd>
                        <dt class="col-5">Gender</dt>
                        <dd class="col-7">${application.gender || "N/A"}</dd>
                        <dt class="col-5">Status</dt>
                        <dd class="col-7">${this.getStatusBadge(
                          application.status,
                        )}</dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Parent/Guardian</h6>
                    <dl class="row">
                        <dt class="col-5">Name</dt>
                        <dd class="col-7">${parentDisplay}</dd>
                        <dt class="col-5">Phone</dt>
                        <dd class="col-7">${parentPhone}</dd>
                        <dt class="col-5">Email</dt>
                        <dd class="col-7">${parentEmail}</dd>
                    </dl>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Documents</h6>
                    ${docsHtml}
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Workflow Progress</h6>
                    ${timelineHtml}
                </div>
            </div>
            
            ${
              actionsHtml
                ? `<hr><div class="text-end">${actionsHtml}</div>`
                : ""
            }
        `;

    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  },

  /**
   * Build workflow timeline HTML
   */
  buildWorkflowTimeline(currentStage, workflowData) {
    const stages = [
      { key: "application", label: "Application" },
      { key: "document_verification", label: "Documents" },
      { key: "interview_scheduling", label: "Interview Schedule" },
      { key: "interview_assessment", label: "Interview" },
      { key: "placement_offer", label: "Placement" },
      { key: "fee_payment", label: "Payment" },
      { key: "enrollment", label: "Enrollment" },
    ];

    const normalizedStage =
      currentStage === "application_submission" ? "application" : currentStage;
    const currentIndex = stages.findIndex((s) => s.key === normalizedStage);

    return `
            <ul class="list-unstyled">
                ${stages
                  .map((stage, index) => {
                    let icon, colorClass;
                    if (index < currentIndex) {
                      icon = "bi-check-circle-fill";
                      colorClass = "text-success";
                    } else if (index === currentIndex) {
                      icon = "bi-arrow-right-circle-fill";
                      colorClass = "text-primary";
                    } else {
                      icon = "bi-circle";
                      colorClass = "text-muted";
                    }
                    return `
                        <li class="mb-2 ${colorClass}">
                            <i class="bi ${icon} me-2"></i>
                            ${stage.label}
                        </li>
                    `;
                  })
                  .join("")}
            </ul>
        `;
  },

  // =====================================================
  // MODAL HANDLERS FOR EACH WORKFLOW STAGE
  // =====================================================

  /**
   * Open new application modal
   */
  openNewApplicationModal() {
    const modal = document.getElementById("newApplicationModal");
    if (modal) {
      if (
        !this.state.referenceData.parents.length ||
        !this.state.referenceData.academicYears.length
      ) {
        this.loadReferenceData();
      }
      const checkbox = modal.querySelector("#hasSpecialNeeds");
      this.toggleSpecialNeeds(checkbox?.checked);
      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    }
  },

  /**
   * Submit new application (fields first, then optional document uploads)
   */
  async submitNewApplication(form) {
    const submitBtn = form.querySelector('[type="submit"]');
    const formData = new FormData(form);

    // Separate file fields from regular fields
    const fileFields = ["doc_birth_certificate", "doc_immunization_card",
                        "doc_passport_photo", "doc_progress_report", "doc_leaving_certificate"];
    const data = {};
    for (const [key, value] of formData.entries()) {
      if (!fileFields.includes(key)) {
        data[key] = value;
      }
    }

    try {
      this.showButtonLoading(submitBtn);

      // 1. Submit application (JSON)
      const response = await API.admission.submitApplication(data);
      const payload = this.unwrapPayload(response);
      const applicationId = payload?.application_id;

      // 2. Upload any provided documents
      if (applicationId) {
        const docTypeMap = {
          doc_birth_certificate: "birth_certificate",
          doc_immunization_card: "immunization_card",
          doc_passport_photo: "passport_photo",
          doc_progress_report: "progress_report",
          doc_leaving_certificate: "leaving_certificate",
        };
        for (const [fieldName, docType] of Object.entries(docTypeMap)) {
          const file = formData.get(fieldName);
          if (file && file.size > 0) {
            const fd = new FormData();
            fd.append("application_id", applicationId);
            fd.append("document_type", docType);
            fd.append("document", file);
            try {
              await API.admission.uploadDocument(fd);
            } catch (uploadErr) {
              console.warn(`[AdmissionsController] Upload failed for ${docType}:`, uploadErr);
            }
          }
        }
      }

      showNotification("Application submitted successfully", "success");
      form.reset();
      this.closeModal("newApplicationModal");
      await this.loadQueues();
    } catch (error) {
      console.error("[AdmissionsController] Error submitting application:", error);
      showNotification(
        error?.message || "Error submitting application — check all required fields",
        "error",
      );
    } finally {
      this.hideButtonLoading(submitBtn);
    }
  },

  requiresTransferDocuments(grade) {
    const normalized = String(grade || "")
      .toLowerCase()
      .replace(/\s+/g, "");
    if (!normalized) {
      return false;
    }

    return this.transferDocumentGrades.some(
      (gradeCode) =>
        String(gradeCode).toLowerCase().replace(/\s+/g, "") === normalized,
    );
  },

  /**
   * Open upload documents modal
   */
  async openUploadDocumentsModal(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      const payload = this.unwrapPayload(response);
      if (!payload || !payload.application) {
        showNotification("Failed to load application", "error");
        return;
      }

      if (!this.isActionAvailableForPayload(payload, "upload-documents")) {
        showNotification(
          "Document upload is not available at the current workflow stage",
          "warning",
        );
        return;
      }

      const { application } = payload;
      const documents = Array.isArray(payload.documents) ? payload.documents : [];
      const modal = document.getElementById("uploadDocumentsModal");
      if (!modal) return;

      const existingByType = {};
      documents.forEach((doc) => {
        const key = String(doc.document_type || "").trim();
        if (!key) return;
        const current = existingByType[key];
        if (!current || Number(doc.id || 0) > Number(current.id || 0)) {
          existingByType[key] = doc;
        }
      });

      const includeTransferDocs = this.requiresTransferDocuments(
        application.grade_applying_for,
      );

      const uploadFields = [
        {
          inputName: "doc_birth_certificate",
          documentType: "birth_certificate",
          label: "Birth Certificate",
          mandatory: true,
          accept: ".pdf,.jpg,.jpeg,.png",
        },
        {
          inputName: "doc_immunization_card",
          documentType: "immunization_card",
          label: "Immunization Card",
          mandatory: true,
          accept: ".pdf,.jpg,.jpeg,.png",
        },
        {
          inputName: "doc_passport_photo",
          documentType: "passport_photo",
          label: "Passport Photo",
          mandatory: true,
          accept: ".jpg,.jpeg,.png",
        },
      ];

      if (includeTransferDocs) {
        uploadFields.push(
          {
            inputName: "doc_progress_report",
            documentType: "progress_report",
            label: "Latest Progress Report",
            mandatory: true,
            accept: ".pdf,.jpg,.jpeg,.png",
          },
          {
            inputName: "doc_leaving_certificate",
            documentType: "leaving_certificate",
            label: "Leaving Certificate",
            mandatory: true,
            accept: ".pdf,.jpg,.jpeg,.png",
          },
        );
      }

      const fieldsHtml = uploadFields
        .map((field) => {
          const existing = existingByType[field.documentType];
          const uploadStatus = existing
            ? '<span class="badge bg-success">Uploaded</span>'
            : '<span class="badge bg-secondary">Not Uploaded</span>';

          const verificationStatus = existing?.verification_status
            ? `<span class="badge bg-${
                existing.verification_status === "verified"
                  ? "success"
                  : existing.verification_status === "rejected"
                    ? "danger"
                    : "warning"
              } ms-2">${existing.verification_status}</span>`
            : "";

          const existingLink =
            existing?.document_path || existing?.file_path
              ? `<a href="${
                  existing.document_path || existing.file_path
                }" target="_blank" class="ms-2 text-decoration-none">
                   <i class="bi bi-box-arrow-up-right"></i>
                 </a>`
              : "";

          return `
            <div class="card mb-3">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                  <div>
                    <strong>${field.label}</strong>
                    ${field.mandatory ? '<span class="text-danger ms-1">*</span>' : ""}
                  </div>
                  <div class="text-end">
                    ${uploadStatus}
                    ${verificationStatus}
                    ${existingLink}
                  </div>
                </div>
                <input type="file"
                       class="form-control"
                       name="${field.inputName}"
                       accept="${field.accept}">
                <small class="text-muted d-block mt-1">Upload to add or replace this document.</small>
              </div>
            </div>
          `;
        })
        .join("");

      modal.querySelector(".modal-body").innerHTML = `
        <form id="uploadDocumentsForm">
          <input type="hidden" name="application_id" value="${applicationId}">
          <h6 class="mb-3">${application.applicant_name} - ${application.application_no}</h6>
          <p class="text-muted mb-3">
            Grade: <strong>${application.grade_applying_for || "N/A"}</strong>
          </p>
          ${fieldsHtml}
        </form>
      `;

      modal.querySelector(".modal-footer").innerHTML = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="uploadDocumentsForm" class="btn btn-primary">
          <i class="bi bi-upload me-1"></i>Upload Selected
        </button>
      `;

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    } catch (error) {
      console.error(
        "[AdmissionsController] Error opening upload documents modal:",
        error,
      );
      showNotification("Error loading upload form", "error");
    }
  },

  async submitUploadDocuments(form) {
    const formData = new FormData(form);
    const applicationId = formData.get("application_id");
    const submitButton = document.querySelector(
      '#uploadDocumentsModal button[form="uploadDocumentsForm"]',
    );

    const fieldToType = {
      doc_birth_certificate: "birth_certificate",
      doc_immunization_card: "immunization_card",
      doc_passport_photo: "passport_photo",
      doc_progress_report: "progress_report",
      doc_leaving_certificate: "leaving_certificate",
    };

    const uploadJobs = [];
    for (const [inputName, documentType] of Object.entries(fieldToType)) {
      const file = formData.get(inputName);
      const isFileObject =
        typeof File === "undefined"
          ? Boolean(file && typeof file === "object")
          : file instanceof File;
      if (!file || !isFileObject || file.size <= 0) {
        continue;
      }

      const payload = new FormData();
      payload.append("application_id", applicationId);
      payload.append("document_type", documentType);
      payload.append("document", file);
      uploadJobs.push(API.admission.uploadDocument(payload));
    }

    if (!uploadJobs.length) {
      showNotification("Select at least one document to upload", "warning");
      return;
    }

    try {
      this.showButtonLoading(submitButton);
      const results = await Promise.allSettled(uploadJobs);
      const failed = results.filter((item) => item.status === "rejected").length;
      const uploaded = uploadJobs.length - failed;

      if (uploaded > 0) {
        showNotification(
          `${uploaded} document${uploaded === 1 ? "" : "s"} uploaded successfully`,
          "success",
        );
      }

      if (failed > 0) {
        showNotification(
          `${failed} upload${failed === 1 ? "" : "s"} failed. Please retry.`,
          "error",
        );
      }

      await this.loadQueues();

      if (failed === 0) {
        this.closeModal("uploadDocumentsModal");
      }
    } catch (error) {
      console.error("[AdmissionsController] Error uploading documents:", error);
      showNotification("Error uploading documents", "error");
    } finally {
      this.hideButtonLoading(submitButton);
    }
  },

  /**
   * Open verify documents modal
   */
  async openVerifyDocumentsModal(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      const payload = this.unwrapPayload(response);
      if (!payload || !payload.application) {
        showNotification("Failed to load documents", "error");
        return;
      }

      if (!this.isActionAvailableForPayload(payload, "verify-documents")) {
        showNotification(
          "Document verification is not available at the current workflow stage",
          "warning",
        );
        return;
      }

      const { application, documents } = payload;
      const modal = document.getElementById("verifyDocumentsModal");
      if (!modal) return;

      // Build document verification form
      let docsForm =
        documents.length === 0
          ? '<p class="text-muted">No documents to verify</p>'
          : documents
              .map(
                (doc) => `
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${doc.document_type}</strong>
                                    ${
                                      doc.is_mandatory
                                        ? '<span class="badge bg-danger ms-1">Required</span>'
                                        : ""
                                    }
                                    ${
                                      (doc.document_path || doc.file_path)
                                        ? `<a href="${
                                            doc.document_path || doc.file_path
                                          }" target="_blank" class="ms-2"><i class="bi bi-download"></i></a>`
                                        : ""
                                    }
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <input type="radio" class="btn-check" name="doc_${
                                      doc.id
                                    }" 
                                           id="doc_${
                                             doc.id
                                           }_verify" value="verified"
                                           ${
                                             doc.verification_status ===
                                             "verified"
                                               ? "checked"
                                               : ""
                                           }>
                                    <label class="btn btn-outline-success" for="doc_${
                                      doc.id
                                    }_verify">
                                        <i class="bi bi-check"></i> Verify
                                    </label>
                                    <input type="radio" class="btn-check" name="doc_${
                                      doc.id
                                    }" 
                                           id="doc_${
                                             doc.id
                                           }_reject" value="rejected"
                                           ${
                                             doc.verification_status ===
                                             "rejected"
                                               ? "checked"
                                               : ""
                                           }>
                                    <label class="btn btn-outline-danger" for="doc_${
                                      doc.id
                                    }_reject">
                                        <i class="bi bi-x"></i> Reject
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                `,
              )
              .join("");

      modal.querySelector(".modal-body").innerHTML = `
                <form id="verifyDocumentsForm">
                    <input type="hidden" name="application_id" value="${applicationId}">
                    <h6 class="mb-3">${application.applicant_name} - ${application.application_no}</h6>
                    ${docsForm}
                    <div class="mb-3">
                        <label class="form-label">Verification Notes</label>
                        <textarea name="notes" class="form-control" rows="2" 
                                  placeholder="Optional notes about document verification"></textarea>
                    </div>
                </form>
            `;

      modal.querySelector(".modal-footer").innerHTML = `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="verifyDocumentsForm" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Complete Verification
                </button>
            `;

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    } catch (error) {
      console.error(
        "[AdmissionsController] Error opening verify modal:",
        error,
      );
      showNotification("Error loading documents", "error");
    }
  },

  /**
   * Submit document verification
   */
  async submitDocumentVerification(form) {
    const formData = new FormData(form);
    const submitButton = document.querySelector(
      '#verifyDocumentsModal button[form="verifyDocumentsForm"]',
    );

    // Collect document statuses
    const documentStatuses = [];
    form.querySelectorAll('input[type="radio"]:checked').forEach((input) => {
      const docId = input.name.replace("doc_", "");
      documentStatuses.push({
        document_id: docId,
        status: input.value,
      });
    });

    if (!documentStatuses.length) {
      showNotification("Select at least one document status", "warning");
      return;
    }

    try {
      this.showButtonLoading(submitButton);

      const notes = formData.get("notes") || "";
      await Promise.all(
        documentStatuses.map((doc) =>
          API.admission.verifyDocument({
            document_id: doc.document_id,
            status: doc.status,
            notes,
          }),
        ),
      );
      showNotification("Documents verified successfully", "success");
      this.closeModal("verifyDocumentsModal");
      await this.loadQueues();
    } catch (error) {
      console.error("[AdmissionsController] Error verifying documents:", error);
      showNotification("Error verifying documents", "error");
    } finally {
      this.hideButtonLoading(submitButton);
    }
  },

  /**
   * Open schedule interview modal
   */
  async openScheduleInterviewModal(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      const payload = this.unwrapPayload(response);
      if (!payload || !payload.application) {
        showNotification("Failed to load application", "error");
        return;
      }

      if (!this.isActionAvailableForPayload(payload, "schedule-interview")) {
        showNotification(
          "Interview scheduling is not available at the current workflow stage",
          "warning",
        );
        return;
      }

      const { application } = payload;

      // Check if interview can be skipped
      const gradeApplyingFor = String(application.grade_applying_for || "");
      const skipInterview = this.noInterviewGrades.some((g) =>
        gradeApplyingFor.toLowerCase().includes(g.toLowerCase()),
      );

      if (skipInterview) {
        showNotification(
          `${application.grade_applying_for} does not require an interview. Continue from Placement stage.`,
          "info",
        );
        this.switchTab("placement_pending");
        return;
      }

      const modal = document.getElementById("scheduleInterviewModal");
      if (!modal) return;

      // Get tomorrow's date as default
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      const defaultDate = tomorrow.toISOString().split("T")[0];

      modal.querySelector(".modal-body").innerHTML = `
                <form id="scheduleInterviewForm">
                    <input type="hidden" name="application_id" value="${applicationId}">
                    <h6 class="mb-3">${application.applicant_name} - ${application.grade_applying_for}</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interview Date <span class="text-danger">*</span></label>
                            <input type="date" name="interview_date" class="form-control" 
                                   value="${defaultDate}" required min="${defaultDate}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interview Time <span class="text-danger">*</span></label>
                            <input type="time" name="interview_time" class="form-control" 
                                   value="09:00" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Interview Location</label>
                      <input type="text" name="venue" class="form-control" 
                               placeholder="e.g., Headteacher's Office">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Interviewer</label>
                        <input type="text" name="interviewer" class="form-control" 
                               placeholder="e.g., Headteacher">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes for Parent</label>
                        <textarea name="notes" class="form-control" rows="2" 
                                  placeholder="Any special instructions for the parent"></textarea>
                    </div>
                </form>
            `;

      modal.querySelector(".modal-footer").innerHTML = `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="scheduleInterviewForm" class="btn btn-info">
                    <i class="bi bi-calendar-plus"></i> Schedule Interview
                </button>
            `;

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    } catch (error) {
      console.error(
        "[AdmissionsController] Error opening interview modal:",
        error,
      );
      showNotification("Error loading application", "error");
    }
  },

  /**
   * Submit interview schedule
   */
  async submitInterviewSchedule(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    const submitButton = document.querySelector(
      '#scheduleInterviewModal button[form="scheduleInterviewForm"]',
    );

    try {
      this.showButtonLoading(submitButton);

      await API.admission.scheduleInterview(data);
      showNotification("Interview scheduled successfully", "success");
      this.closeModal("scheduleInterviewModal");
      await this.loadQueues();
    } catch (error) {
      console.error(
        "[AdmissionsController] Error scheduling interview:",
        error,
      );
      showNotification("Error scheduling interview", "error");
    } finally {
      this.hideButtonLoading(submitButton);
    }
  },

  /**
   * Open record interview results modal
   */
  async openRecordInterviewModal(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      const payload = this.unwrapPayload(response);
      if (!payload || !payload.application) {
        showNotification("Failed to load application", "error");
        return;
      }

      if (!this.isActionAvailableForPayload(payload, "record-interview")) {
        showNotification(
          "Interview assessment is not available at the current workflow stage",
          "warning",
        );
        return;
      }

      const { application, workflow_data } = payload;
      const modal = document.getElementById("recordInterviewModal");
      if (!modal) return;

      modal.querySelector(".modal-body").innerHTML = `
                <form id="recordInterviewForm">
                    <input type="hidden" name="application_id" value="${applicationId}">
                    <h6 class="mb-3">${application.applicant_name} - ${
                      application.grade_applying_for
                    }</h6>
                    
                    ${
                      workflow_data.interview_date
                        ? `
                        <div class="alert alert-info">
                            <i class="bi bi-calendar"></i> Scheduled: ${
                              workflow_data.interview_date
                            } at ${workflow_data.interview_time || "TBD"}
                        </div>
                    `
                        : ""
                    }
                    
                    <div class="mb-3">
                        <label class="form-label">Interview Result <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="result" id="result_pass" value="passed" required>
                            <label class="btn btn-outline-success" for="result_pass">
                                <i class="bi bi-check-lg"></i> Pass
                            </label>
                            <input type="radio" class="btn-check" name="result" id="result_fail" value="failed">
                            <label class="btn btn-outline-danger" for="result_fail">
                                <i class="bi bi-x-lg"></i> Fail
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assessment Score (Optional)</label>
                        <input type="number" name="score" class="form-control" 
                               min="0" max="100" placeholder="0-100">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Interview Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Observations, strengths, areas of concern"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Recommendations</label>
                        <textarea name="recommendations" class="form-control" rows="2" 
                                  placeholder="Any recommendations for the student"></textarea>
                    </div>
                </form>
            `;

      modal.querySelector(".modal-footer").innerHTML = `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="recordInterviewForm" class="btn btn-success">
                    <i class="bi bi-clipboard-check"></i> Save Results
                </button>
            `;

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    } catch (error) {
      console.error(
        "[AdmissionsController] Error opening interview results modal:",
        error,
      );
      showNotification("Error loading application", "error");
    }
  },

  /**
   * Submit interview results
   */
  async submitInterviewResults(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    const submitButton = document.querySelector(
      '#recordInterviewModal button[form="recordInterviewForm"]',
    );

    try {
      this.showButtonLoading(submitButton);

      await API.admission.recordInterviewResults(data);
      showNotification("Interview results recorded successfully", "success");
      this.closeModal("recordInterviewModal");
      await this.loadQueues();
    } catch (error) {
      console.error(
        "[AdmissionsController] Error recording interview results:",
        error,
      );
      showNotification("Error recording results", "error");
    } finally {
      this.hideButtonLoading(submitButton);
    }
  },

  /**
   * Auto-qualify applicant (for ECD/transitional grades)
   */
  async autoQualify(applicationId) {
    try {
      await API.admission.recordInterviewResults({
        application_id: applicationId,
        result: "passed",
        notes: "Auto-qualified - interview not required for this grade level",
      });
      showNotification("Applicant auto-qualified successfully", "success");
      await this.loadQueues();
    } catch (error) {
      console.error("[AdmissionsController] Error auto-qualifying:", error);
      showNotification("Error auto-qualifying applicant", "error");
    }
  },

  /**
   * Open placement offer modal
   */
  async openPlacementModal(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      const payload = this.unwrapPayload(response);
      if (!payload || !payload.application) {
        showNotification("Failed to load application", "error");
        return;
      }

      if (!this.isActionAvailableForPayload(payload, "generate-placement")) {
        showNotification(
          "Placement generation is not available at the current workflow stage",
          "warning",
        );
        return;
      }

      const { application } = payload;
      const modal = document.getElementById("placementModal");
      if (!modal) return;

      // Load available classes for this grade
      let classesOptions = '<option value="">-- Select Class --</option>';
      try {
        let classes = [];

        if (API?.admission?.getPlacementClasses) {
          const classesResponse = await API.admission.getPlacementClasses();
          classes = this.unwrapList(classesResponse, "classes");
        }

        // Fallback for legacy backends that do not yet expose the admission endpoint.
        if (!classes.length && API?.academic?.listClasses) {
          const classesResponse = await API.academic.listClasses();
          classes = this.unwrapList(classesResponse);
        }

        if (classes.length) {
          classesOptions += classes
            .map(
              (c) =>
                `<option value="${c.id}">${c.name || c.class_name} (${
                  c.student_count || 0
                }/${c.capacity || "∞"})</option>`,
            )
            .join("");
        }
      } catch (e) {
        console.warn("[AdmissionsController] Could not load classes:", e);
      }

      modal.querySelector(".modal-body").innerHTML = `
                <form id="placementForm">
                    <input type="hidden" name="application_id" value="${applicationId}">
                    <h6 class="mb-3">${application.applicant_name} - ${application.grade_applying_for}</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Assign to Class <span class="text-danger">*</span></label>
                      <select name="assigned_class_id" class="form-select" required>
                            ${classesOptions}
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Total Fees (KES)</label>
                        <input type="number" name="total_fees" class="form-control" 
                               placeholder="Leave blank to use standard fees">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Admission Type</label>
                        <select name="admission_type" class="form-select">
                            <option value="new">New Student</option>
                            <option value="transfer">Transfer</option>
                            <option value="returning">Returning Student</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Offer Notes</label>
                        <textarea name="notes" class="form-control" rows="2" 
                                  placeholder="Any special notes for the placement offer"></textarea>
                    </div>
                </form>
            `;

      modal.querySelector(".modal-footer").innerHTML = `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="placementForm" class="btn btn-primary">
                    <i class="bi bi-award"></i> Generate Placement Offer
                </button>
            `;

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    } catch (error) {
      console.error(
        "[AdmissionsController] Error opening placement modal:",
        error,
      );
      showNotification("Error loading application", "error");
    }
  },

  /**
   * Submit placement offer
   */
  async submitPlacementOffer(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    const submitButton = document.querySelector(
      '#placementModal button[form="placementForm"]',
    );

    try {
      this.showButtonLoading(submitButton);

      await API.admission.generatePlacementOffer(data);
      showNotification("Placement offer generated successfully", "success");
      this.closeModal("placementModal");
      await this.loadQueues();
    } catch (error) {
      console.error(
        "[AdmissionsController] Error generating placement offer:",
        error,
      );
      showNotification("Error generating offer", "error");
    } finally {
      this.hideButtonLoading(submitButton);
    }
  },

  /**
   * Open payment recording modal
   */
  async openPaymentModal(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      const payload = this.unwrapPayload(response);
      if (!payload || !payload.application) {
        showNotification("Failed to load application", "error");
        return;
      }

      if (!this.isActionAvailableForPayload(payload, "record-payment")) {
        showNotification(
          "Payment recording is not available at the current workflow stage",
          "warning",
        );
        return;
      }

      const { application, workflow_data } = payload;
      const modal = document.getElementById("paymentModal");
      if (!modal) return;

      const totalFees = workflow_data.total_fees || 0;

      modal.querySelector(".modal-body").innerHTML = `
                <form id="paymentForm">
                    <input type="hidden" name="application_id" value="${applicationId}">
                    <h6 class="mb-3">${application.applicant_name} - ${
                      application.grade_applying_for
                    }</h6>
                    
                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between">
                            <span>Total Fees:</span>
                            <strong>KES ${Number(
                              totalFees,
                            ).toLocaleString()}</strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Amount Paid (KES) <span class="text-danger">*</span></label>
                        <input type="number" name="amount_paid" class="form-control" 
                               required min="0" step="0.01" placeholder="Enter amount paid">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select" required>
                            <option value="">-- Select Method --</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                            <option value="card">Card Payment</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Transaction Reference</label>
                        <input type="text" name="transaction_reference" class="form-control" 
                               placeholder="M-Pesa code, Bank ref, etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" 
                               value="${
                                 new Date().toISOString().split("T")[0]
                               }">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" 
                                  placeholder="Payment notes"></textarea>
                    </div>
                </form>
            `;

      modal.querySelector(".modal-footer").innerHTML = `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="paymentForm" class="btn btn-success">
                    <i class="bi bi-cash"></i> Record Payment
                </button>
            `;

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    } catch (error) {
      console.error(
        "[AdmissionsController] Error opening payment modal:",
        error,
      );
      showNotification("Error loading application", "error");
    }
  },

  /**
   * Submit payment record
   */
  async submitPaymentRecord(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    const submitButton = document.querySelector(
      '#paymentModal button[form="paymentForm"]',
    );

    try {
      this.showButtonLoading(submitButton);

      await API.admission.recordFeePayment(data);
      showNotification("Payment recorded successfully", "success");
      this.closeModal("paymentModal");
      await this.loadQueues();
    } catch (error) {
      console.error("[AdmissionsController] Error recording payment:", error);
      showNotification("Error recording payment", "error");
    } finally {
      this.hideButtonLoading(submitButton);
    }
  },

  /**
   * Complete enrollment
   */
  async completeEnrollment(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      const payload = this.unwrapPayload(response);
      if (!payload || !payload.application) {
        showNotification("Failed to load application", "error");
        return;
      }

      if (!this.isActionAvailableForPayload(payload, "complete-enrollment")) {
        showNotification(
          "Enrollment completion is not available at the current workflow stage",
          "warning",
        );
        return;
      }
    } catch (error) {
      console.error(
        "[AdmissionsController] Error validating enrollment action:",
        error,
      );
      showNotification("Error validating enrollment action", "error");
      return;
    }

    const confirmed = await this.confirm(
      "Complete Enrollment",
      "This will create the student record and finalize the admission. Continue?",
    );

    if (!confirmed) return;

    try {
      const response = await API.admission.completeEnrollment({
        application_id: applicationId,
      });
      const payload = this.unwrapPayload(response);

      showNotification(
        "Enrollment completed successfully! Student has been created.",
        "success",
      );
      await this.loadQueues();

      // Optionally redirect to student profile
      if (payload?.student_id) {
        const viewStudent = await this.confirm(
          "View Student",
          "Would you like to view the new student record?",
        );
        if (viewStudent) {
          const studentId = encodeURIComponent(payload.student_id);
          const route = this.resolveStudentRecordRoute();
          window.location.href = (window.APP_BASE || "") + `/home.php?route=${encodeURIComponent(route)}&student_id=${studentId}&view=profile`;
        }
      }
    } catch (error) {
      console.error(
        "[AdmissionsController] Error completing enrollment:",
        error,
      );
      showNotification("Error completing enrollment", "error");
    }
  },

  // =====================================================
  // UTILITY METHODS
  // =====================================================

  /**
   * Show a confirmation dialog
   */
  confirm(title, message) {
    return new Promise((resolve) => {
      // Use browser confirm for now, can be replaced with modal
      resolve(window.confirm(`${title}\n\n${message}`));
    });
  },

  /**
   * Close a modal by ID
   */
  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      const bsModal = bootstrap.Modal.getInstance(modal);
      if (bsModal) {
        bsModal.hide();
      }
    }
  },

  resolveStudentRecordRoute() {
    const allowedRoutes = window.AppRouteAccess?.getAllowedRoutes?.();
    if (allowedRoutes && typeof allowedRoutes.has === "function") {
      if (allowedRoutes.has("manage_students")) {
        return "manage_students";
      }
      if (allowedRoutes.has("all_students")) {
        return "all_students";
      }
    }
    return "manage_students";
  },

  /**
   * Show loading state
   */
  showLoading() {
    const container = document.getElementById("admissionQueueContent");
    if (container) {
      container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading admissions...</p>
                </div>
            `;
    }
  },

  /**
   * Hide loading state
   */
  hideLoading() {
    // Loading is replaced by content in renderCurrentQueue
  },

  /**
   * Show button loading state
   */
  showButtonLoading(button) {
    if (button) {
      button.disabled = true;
      button.dataset.originalText = button.innerHTML;
      button.innerHTML =
        '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
    }
  },

  /**
   * Hide button loading state
   */
  hideButtonLoading(button) {
    if (button) {
      button.disabled = false;
      button.innerHTML = button.dataset.originalText || "Submit";
    }
  },

  async loadStats() {
    try {
      const response = await API.admission.getStats();
      const payload = this.unwrapPayload(response);
      if (payload && typeof payload.enrolled !== "undefined") {
        this.state.summary.enrolled = Number(payload.enrolled) || 0;
      }
    } catch (error) {
      console.warn("[AdmissionsController] Failed to load admission stats:", error);
    }
  },
};

// Initialize controller (supports both static and dynamically injected script load)
const bootstrapAdmissionsController = () => {
  // Only initialize on admissions page
  if (
    document.getElementById("admissionTabs") ||
    document.querySelector('[data-page="admissions"]')
  ) {
    AdmissionsController.init();
  }
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", bootstrapAdmissionsController);
} else {
  bootstrapAdmissionsController();
}

// Export for external use
window.AdmissionsController = AdmissionsController;
