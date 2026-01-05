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
    queues: {},
    summary: {},
    selectedApplication: null,
    userRole: null,
    isLoading: false,
  },

  // Workflow stage configuration
  stages: {
    documents_pending: {
      label: "Documents",
      icon: "bi-file-earmark-text",
      color: "warning",
      roles: [
        "admin",
        "registrar",
        "deputy_headteacher",
        "headteacher",
        "school_admin",
      ],
    },
    interview_pending: {
      label: "Interview",
      icon: "bi-calendar-event",
      color: "info",
      roles: ["admin", "headteacher", "deputy_headteacher", "school_admin"],
    },
    placement_pending: {
      label: "Placement",
      icon: "bi-check-circle",
      color: "primary",
      roles: ["admin", "headteacher", "director", "school_admin"],
    },
    payment_pending: {
      label: "Payment",
      icon: "bi-cash-stack",
      color: "success",
      roles: ["admin", "accountant", "bursar", "finance_officer", "director"],
    },
    enrollment_pending: {
      label: "Enrollment",
      icon: "bi-person-check",
      color: "dark",
      roles: ["admin", "registrar", "school_admin"],
    },
  },

  // Grades that skip interview (ECD and transitional grades)
  noInterviewGrades: [
    "ECD",
    "PP1",
    "PP2",
    "Grade 1",
    "Grade1",
    "Grade 7",
    "Grade7",
  ],

  /**
   * Initialize the controller
   */
  async init() {
    console.log("[AdmissionsController] Initializing...");

    // Get user role from session
    this.state.userRole =
      window.currentUserRole || document.body.dataset.userRole || "guest";

    // Setup event listeners
    this.setupEventListeners();

    // Load workflow queues
    await this.loadQueues();

    // Render initial view
    this.renderTabs();
    this.switchTab(this.getDefaultTab());

    console.log("[AdmissionsController] Initialized");
  },

  /**
   * Get the default tab based on user role
   */
  getDefaultTab() {
    const role = this.state.userRole;

    // Direct user to their most relevant queue
    if (["accountant", "bursar", "finance_officer"].includes(role)) {
      return "payment_pending";
    }
    if (role === "headteacher") {
      return "interview_pending";
    }
    // Default to documents for registrars and admins
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

    // Action buttons
    document.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-action]");
      if (!btn) return;

      const action = btn.dataset.action;
      const applicationId = btn.dataset.applicationId;

      switch (action) {
        case "view":
          this.viewApplication(applicationId);
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
   * Load workflow queues from API
   */
  async loadQueues() {
    this.state.isLoading = true;
    this.showLoading();

    try {
      const response = await API.admission.getQueues();

      if (response.success) {
        this.state.queues = response.data.queues;
        this.state.summary = response.data.summary;
        this.updateTabBadges();
        this.renderCurrentQueue();
      } else {
        showNotification(
          "Failed to load admissions: " + (response.message || "Unknown error"),
          "error"
        );
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

    const role = this.state.userRole;
    let tabsHtml = "";

    for (const [key, config] of Object.entries(this.stages)) {
      // Check if user role has access to this tab
      const hasAccess =
        config.roles.includes(role) || role === "admin" || role === "director";
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
  },

  /**
   * Switch to a different tab
   */
  switchTab(tabKey) {
    this.state.currentTab = tabKey;

    // Update active states
    document.querySelectorAll(".admission-tab").forEach((tab) => {
      tab.classList.toggle("active", tab.dataset.tab === tabKey);
    });

    // Render the queue for this tab
    this.renderCurrentQueue();
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
   * Render the current queue table
   */
  renderCurrentQueue() {
    const container = document.getElementById("admissionQueueContent");
    if (!container) return;

    const tabKey = this.state.currentTab;
    const queue = this.state.queues[tabKey] || [];
    const config = this.stages[tabKey];

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
    const actions = this.getActionsForTab(tabKey);

    let rows = queue
      .map((app) => {
        const parentName =
          `${app.parent_first_name || ""} ${
            app.parent_last_name || ""
          }`.trim() || "N/A";
        const createdDate = new Date(app.created_at).toLocaleDateString();

        let statusBadge = this.getStatusBadge(app.status);
        let actionButtons = actions
          .map((action) => this.renderActionButton(action, app.id))
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
                        <small class="text-muted">${app.phone_1 || ""}</small>
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
    const role = this.state.userRole;

    switch (tabKey) {
      case "documents_pending":
        return ["verify-documents"];
      case "interview_pending":
        if (["headteacher", "deputy_headteacher", "admin"].includes(role)) {
          return ["schedule-interview", "record-interview"];
        }
        return ["schedule-interview"];
      case "placement_pending":
        return ["generate-placement"];
      case "payment_pending":
        if (
          [
            "accountant",
            "bursar",
            "finance_officer",
            "admin",
            "director",
          ].includes(role)
        ) {
          return ["record-payment"];
        }
        return [];
      case "enrollment_pending":
        return ["complete-enrollment"];
      default:
        return [];
    }
  },

  /**
   * Render an action button
   */
  renderActionButton(action, applicationId) {
    const configs = {
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
                    data-application-id="${applicationId}" title="${config.title}">
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
      interview_scheduled: { color: "primary", text: "Interview Scheduled" },
      interview_passed: { color: "success", text: "Interview Passed" },
      interview_failed: { color: "danger", text: "Interview Failed" },
      auto_qualified: { color: "success", text: "Auto Qualified" },
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

      if (!response.success) {
        showNotification("Failed to load application details", "error");
        return;
      }

      const { application, documents, workflow_data, available_actions } =
        response.data;
      this.state.selectedApplication = response.data;

      // Render in modal
      this.showApplicationModal(
        application,
        documents,
        workflow_data,
        available_actions
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
      workflowData
    );

    // Build action buttons
    let actionsHtml = availableActions
      .map((action) => this.renderActionButton(action, application.id))
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
                        <dd class="col-7">${
                          application.date_of_birth || "N/A"
                        }</dd>
                        <dt class="col-5">Gender</dt>
                        <dd class="col-7">${application.gender || "N/A"}</dd>
                        <dt class="col-5">Status</dt>
                        <dd class="col-7">${this.getStatusBadge(
                          application.status
                        )}</dd>
                    </dl>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Parent/Guardian</h6>
                    <dl class="row">
                        <dt class="col-5">Name</dt>
                        <dd class="col-7">${parentName || "N/A"}</dd>
                        <dt class="col-5">Phone</dt>
                        <dd class="col-7">${application.phone_1 || "N/A"}</dd>
                        <dt class="col-5">Email</dt>
                        <dd class="col-7">${
                          application.parent_email || "N/A"
                        }</dd>
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
      { key: "application_submission", label: "Application" },
      { key: "document_verification", label: "Documents" },
      { key: "interview_scheduling", label: "Interview Schedule" },
      { key: "interview_assessment", label: "Interview" },
      { key: "placement_offer", label: "Placement" },
      { key: "fee_payment", label: "Payment" },
      { key: "enrollment", label: "Enrollment" },
    ];

    const currentIndex = stages.findIndex((s) => s.key === currentStage);

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
      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    }
  },

  /**
   * Submit new application
   */
  async submitNewApplication(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
      this.showButtonLoading(form.querySelector('[type="submit"]'));

      const response = await API.admission.submitApplication(data);

      if (response.success) {
        showNotification("Application submitted successfully", "success");
        this.closeModal("newApplicationModal");
        await this.loadQueues();
      } else {
        showNotification(
          response.message || "Failed to submit application",
          "error"
        );
      }
    } catch (error) {
      console.error(
        "[AdmissionsController] Error submitting application:",
        error
      );
      showNotification("Error submitting application", "error");
    } finally {
      this.hideButtonLoading(form.querySelector('[type="submit"]'));
    }
  },

  /**
   * Open verify documents modal
   */
  async openVerifyDocumentsModal(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      if (!response.success) {
        showNotification("Failed to load documents", "error");
        return;
      }

      const { application, documents } = response.data;
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
                                      doc.file_path
                                        ? `<a href="${doc.file_path}" target="_blank" class="ms-2"><i class="bi bi-download"></i></a>`
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
                `
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
        error
      );
      showNotification("Error loading documents", "error");
    }
  },

  /**
   * Submit document verification
   */
  async submitDocumentVerification(form) {
    const formData = new FormData(form);
    const applicationId = formData.get("application_id");

    // Collect document statuses
    const documentStatuses = [];
    form.querySelectorAll('input[type="radio"]:checked').forEach((input) => {
      const docId = input.name.replace("doc_", "");
      documentStatuses.push({
        document_id: docId,
        status: input.value,
      });
    });

    try {
      this.showButtonLoading(form.querySelector('[type="submit"]'));

      const response = await API.admission.verifyDocument({
        application_id: applicationId,
        documents: documentStatuses,
        notes: formData.get("notes"),
      });

      if (response.success) {
        showNotification("Documents verified successfully", "success");
        this.closeModal("verifyDocumentsModal");
        await this.loadQueues();
      } else {
        showNotification(
          response.message || "Failed to verify documents",
          "error"
        );
      }
    } catch (error) {
      console.error("[AdmissionsController] Error verifying documents:", error);
      showNotification("Error verifying documents", "error");
    } finally {
      this.hideButtonLoading(form.querySelector('[type="submit"]'));
    }
  },

  /**
   * Open schedule interview modal
   */
  async openScheduleInterviewModal(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      if (!response.success) {
        showNotification("Failed to load application", "error");
        return;
      }

      const { application } = response.data;

      // Check if interview can be skipped
      const skipInterview = this.noInterviewGrades.some((g) =>
        application.grade_applying_for.toLowerCase().includes(g.toLowerCase())
      );

      if (skipInterview) {
        // Auto-qualify for ECD grades
        const confirm = await this.confirm(
          "Auto-Qualification",
          `${application.grade_applying_for} students don't require an interview. Do you want to auto-qualify this applicant?`
        );
        if (confirm) {
          await this.autoQualify(applicationId);
        }
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
                        <input type="text" name="location" class="form-control" 
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
        error
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

    try {
      this.showButtonLoading(form.querySelector('[type="submit"]'));

      const response = await API.admission.scheduleInterview(data);

      if (response.success) {
        showNotification("Interview scheduled successfully", "success");
        this.closeModal("scheduleInterviewModal");
        await this.loadQueues();
      } else {
        showNotification(
          response.message || "Failed to schedule interview",
          "error"
        );
      }
    } catch (error) {
      console.error(
        "[AdmissionsController] Error scheduling interview:",
        error
      );
      showNotification("Error scheduling interview", "error");
    } finally {
      this.hideButtonLoading(form.querySelector('[type="submit"]'));
    }
  },

  /**
   * Open record interview results modal
   */
  async openRecordInterviewModal(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      if (!response.success) {
        showNotification("Failed to load application", "error");
        return;
      }

      const { application, workflow_data } = response.data;
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
        error
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

    try {
      this.showButtonLoading(form.querySelector('[type="submit"]'));

      const response = await API.admission.recordInterviewResults(data);

      if (response.success) {
        showNotification("Interview results recorded successfully", "success");
        this.closeModal("recordInterviewModal");
        await this.loadQueues();
      } else {
        showNotification(
          response.message || "Failed to record results",
          "error"
        );
      }
    } catch (error) {
      console.error(
        "[AdmissionsController] Error recording interview results:",
        error
      );
      showNotification("Error recording results", "error");
    } finally {
      this.hideButtonLoading(form.querySelector('[type="submit"]'));
    }
  },

  /**
   * Auto-qualify applicant (for ECD/transitional grades)
   */
  async autoQualify(applicationId) {
    try {
      const response = await API.admission.recordInterviewResults({
        application_id: applicationId,
        result: "passed",
        notes: "Auto-qualified - interview not required for this grade level",
      });

      if (response.success) {
        showNotification("Applicant auto-qualified successfully", "success");
        await this.loadQueues();
      } else {
        showNotification(response.message || "Failed to auto-qualify", "error");
      }
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
      if (!response.success) {
        showNotification("Failed to load application", "error");
        return;
      }

      const { application } = response.data;
      const modal = document.getElementById("placementModal");
      if (!modal) return;

      // Load available classes for this grade
      let classesOptions = '<option value="">-- Select Class --</option>';
      try {
        const classesResponse = (await API.classes?.list?.({
          grade: application.grade_applying_for,
        })) || { success: false };
        if (classesResponse.success && classesResponse.data) {
          classesOptions += classesResponse.data
            .map(
              (c) =>
                `<option value="${c.id}">${c.class_name} (${
                  c.current_enrollment || 0
                }/${c.capacity || "∞"})</option>`
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
                        <select name="class_id" class="form-select" required>
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
        error
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

    try {
      this.showButtonLoading(form.querySelector('[type="submit"]'));

      const response = await API.admission.generatePlacementOffer(data);

      if (response.success) {
        showNotification("Placement offer generated successfully", "success");
        this.closeModal("placementModal");
        await this.loadQueues();
      } else {
        showNotification(
          response.message || "Failed to generate offer",
          "error"
        );
      }
    } catch (error) {
      console.error(
        "[AdmissionsController] Error generating placement offer:",
        error
      );
      showNotification("Error generating offer", "error");
    } finally {
      this.hideButtonLoading(form.querySelector('[type="submit"]'));
    }
  },

  /**
   * Open payment recording modal
   */
  async openPaymentModal(applicationId) {
    try {
      const response = await API.admission.getApplication(applicationId);
      if (!response.success) {
        showNotification("Failed to load application", "error");
        return;
      }

      const { application, workflow_data } = response.data;
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
                              totalFees
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
        error
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

    try {
      this.showButtonLoading(form.querySelector('[type="submit"]'));

      const response = await API.admission.recordFeePayment(data);

      if (response.success) {
        showNotification("Payment recorded successfully", "success");
        this.closeModal("paymentModal");
        await this.loadQueues();
      } else {
        showNotification(
          response.message || "Failed to record payment",
          "error"
        );
      }
    } catch (error) {
      console.error("[AdmissionsController] Error recording payment:", error);
      showNotification("Error recording payment", "error");
    } finally {
      this.hideButtonLoading(form.querySelector('[type="submit"]'));
    }
  },

  /**
   * Complete enrollment
   */
  async completeEnrollment(applicationId) {
    const confirmed = await this.confirm(
      "Complete Enrollment",
      "This will create the student record and finalize the admission. Continue?"
    );

    if (!confirmed) return;

    try {
      const response = await API.admission.completeEnrollment({
        application_id: applicationId,
      });

      if (response.success) {
        showNotification(
          "Enrollment completed successfully! Student has been created.",
          "success"
        );
        await this.loadQueues();

        // Optionally redirect to student profile
        if (response.data?.student_id) {
          const viewStudent = await this.confirm(
            "View Student",
            "Would you like to view the new student record?"
          );
          if (viewStudent) {
            window.location.href = `/pages/student_profile.php?id=${response.data.student_id}`;
          }
        }
      } else {
        showNotification(
          response.message || "Failed to complete enrollment",
          "error"
        );
      }
    } catch (error) {
      console.error(
        "[AdmissionsController] Error completing enrollment:",
        error
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
};

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  // Only initialize on admissions page
  if (
    document.getElementById("admissionTabs") ||
    document.querySelector('[data-page="admissions"]')
  ) {
    AdmissionsController.init();
  }
});

// Export for external use
window.AdmissionsController = AdmissionsController;
