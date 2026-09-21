/**
 * New Applications Controller
 * Handles the new applications page - receiving, tracking, and creating applications
 */


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

    try {
      // Wait for AuthContext to finish bootstrapping (restores session
      // from HttpOnly refresh cookie) before checking auth state.
      // Without this, isAuthenticated() returns false on page load
      // because the access token is only kept in memory.
      if (window.AuthContext?.ready) {
        await window.AuthContext.ready();
      } else {
        console.warn("newApplicationsController: AuthContext not available");
      }

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
      }

      // Check for URL parameters that might trigger auto-view
      const urlParams = new URLSearchParams(window.location.search);
      const applicationId = urlParams.get('application_id');
      const viewId = urlParams.get('view');
      
      if (applicationId && !viewId) {
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
      targetTermSelect: document.getElementById("targetTermSelect"),
      academicYearInput: document.getElementById("academicYearInput"),
      targetTermInput: document.getElementById("targetTermInput"),

      parentTypeExisting: document.getElementById("parentTypeExisting"),
      parentTypeNew: document.getElementById("parentTypeNew"),
      existingParentFields: document.getElementById("existingParentFields"),
      newParentFields: document.getElementById("newParentFields"),
      newParentIdField: document.getElementById("newParentIdField"),
      newParentPhoneField: document.getElementById("newParentPhoneField"),
      newParentEmailField: document.getElementById("newParentEmailField"),
      newParentAddressField: document.getElementById("newParentAddressField"),
      gradeSelect: document.getElementById("gradeSelect"),
      immunizationDocWrap: document.getElementById("immunizationDocWrap"),
      schoolReportDocWrap: document.getElementById("schoolReportDocWrap"),
      medicalDocWrap: document.getElementById("medicalDocWrap"),
      newParentIdDocWrap: document.getElementById("newParentIdDocWrap"),

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

    if (this.dom.parentTypeExisting && this.dom.parentTypeNew) {
      this.dom.parentTypeExisting.addEventListener("change", () =>
        this.toggleParentType(false),
      );
      this.dom.parentTypeNew.addEventListener("change", () =>
        this.toggleParentType(true),
      );
    }

    this.safeListen("gradeSelect", "change", () =>
      this.toggleDocumentRequirements(),
    );

    this.safeListen("targetTermSelect", "change", () =>
      this.syncYearFromTerm(),
    );
  },

  toggleParentType: function (isNew) {
    const show = (el) => {
      if (el) el.style.display = isNew ? "none" : "block";
    };
    const showNew = (el) => {
      if (el) el.style.display = isNew ? "block" : "none";
    };

    show(this.dom.existingParentFields);
    ["newParentFields", "newParentIdField", "newParentPhoneField",
     "newParentEmailField", "newParentAddressField"].forEach((key) =>
      showNew(this.dom[key]),
    );

    const parentSelect = this.dom.parentSelect;
    if (parentSelect) parentSelect.required = !isNew;

    const docParentWrap = this.dom.newParentIdDocWrap;
    if (docParentWrap) {
      const input = docParentWrap.querySelector("input");
      if (input) {
        if (isNew) input.setAttribute("required", "required");
        else input.removeAttribute("required");
      }
      const mark = docParentWrap.querySelector(".text-danger");
      if (mark) mark.textContent = isNew ? " *" : "";
    }
  },

  isUpperGrade: function (grade) {
    return /^Grade\s*[4-9]$/i.test(String(grade || "").trim());
  },

  toggleDocumentRequirements: function () {
    const grade = String(this.dom.gradeSelect?.value || "").trim();
    const isUpper = this.isUpperGrade(grade);
    // With no grade chosen yet, default to the immunization requirement.
    const showImmunization = !isUpper;

    const setWrap = (wrap, show) => {
      if (!wrap) return;
      wrap.style.display = show ? "" : "none";
      const input = wrap.querySelector("input");
      if (input) {
        if (show) input.setAttribute("required", "required");
        else { input.removeAttribute("required"); input.value = ""; }
      }
    };

    setWrap(this.dom.immunizationDocWrap, showImmunization);
    setWrap(this.dom.schoolReportDocWrap, isUpper);
    setWrap(this.dom.medicalDocWrap, isUpper);
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
    await this.loadClasses();

    // Pull the real academic years and the open intake terms from the API
    // instead of guessing "this year / next year". The intake term drives the
    // target_term_id on the application; the year is derived from it.
    try {
      const [yearsResponse, termsResponse] = await Promise.all([
        window.API?.academic?.listYears
          ? window.API.academic.listYears({ limit: 50 })
          : Promise.resolve(null),
        window.API?.admission?.getOpenAdmissionTerms
          ? window.API.admission.getOpenAdmissionTerms()
          : Promise.resolve(null),
      ]);

      const yearsPayload = yearsResponse?.data ?? yearsResponse ?? {};
      const years = Array.isArray(yearsPayload)
        ? yearsPayload
        : yearsPayload.years || yearsPayload.items || [];

      const termsPayload = termsResponse?.data ?? termsResponse ?? {};
      const terms = Array.isArray(termsPayload)
        ? termsPayload
        : termsPayload.terms || termsPayload.items || [];

      this.academicYears = years.map((year) => ({
        id: year.id,
        year_code: year.year_code || year.year_name || String(year.id),
      }));

      if (this.academicYears.length === 0) {
        // Fallback so the form still works even if the API is unreachable.
        const currentYear = new Date().getFullYear();
        const yearCode = `${currentYear}/${currentYear + 1}`;
        this.academicYears = [{ id: yearCode, year_code: yearCode }];
      }

      this.populateAcademicYearDropdown();
      this.populateTargetTermDropdown(terms);
      this.applyIntakeDefaults(terms);
    } catch (error) {
      console.error("Failed to load academic metadata:", error);
      const currentYear = new Date().getFullYear();
      const yearCode = `${currentYear}/${currentYear + 1}`;
      this.academicYears = [{ id: yearCode, year_code: yearCode }];
      this.populateAcademicYearDropdown();
      this.populateTargetTermDropdown([]);
    }
  },

  applyIntakeDefaults: function (terms) {
    const intake = Array.isArray(terms) ? terms[0] : null;
    const category = document.getElementById('admissionCategorySelect');
    if (category && intake?.default_admission_category) {
      category.value = intake.default_admission_category;
      category.disabled = true;
      let hidden = document.getElementById('admissionCategoryInput');
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'admission_category'; hidden.id = 'admissionCategoryInput';
        category.form?.appendChild(hidden);
      }
      hidden.value = intake.default_admission_category;
    }
    if (intake?.eligible_grades) {
      let allowed = [];
      try { allowed = JSON.parse(intake.eligible_grades) || []; } catch (_) {}
      if (allowed.length && this.dom.gradeSelect) {
        const current = this.dom.gradeSelect.value;
        this.dom.gradeSelect.innerHTML = '<option value="">Select Grade</option>' + allowed.map((grade) => `<option value="${this.escapeHtml(grade)}">${this.escapeHtml(grade)}</option>`).join('');
        if (allowed.includes(current)) this.dom.gradeSelect.value = current;
      }
    }
  },

  loadClasses: async function () {
    try {
      let classes = [];
      if (window.API?.admission?.getPlacementClasses) {
        const response = await window.API.admission.getPlacementClasses();
        const payload = response?.data || response || {};
        classes = payload.classes || (Array.isArray(payload) ? payload : []);
      }
      if (!classes.length && window.API?.academic?.listClasses) {
        const response = await window.API.academic.listClasses({ limit: 200 });
        const payload = response?.data || response || {};
        classes = Array.isArray(payload) ? payload : payload.classes || [];
      }
      this.populateClassSelects(classes);
    } catch (error) {
      console.error('Failed to load classes:', error);
    }
  },

  populateClassSelects: function (classes) {
    ['filterClass', 'gradeSelect'].forEach(selectId => {
      const select = document.getElementById(selectId);
      if (!select) return;
      const currentVal = select.value;
      const firstOption = select.querySelector('option:first-child');
      const firstLabel = firstOption ? firstOption.textContent : 'All Classes';
      const firstValue = firstOption ? firstOption.value : '';
      let html = `<option value="${firstValue}">${firstLabel}</option>`;
      html += classes.map(c => `<option value="${c.name || c.class_name || c.id}">${c.name || c.class_name || c.id}</option>`).join('');
      select.innerHTML = html;
      if (currentVal) select.value = currentVal;
    });
    this.toggleDocumentRequirements();
  },

  loadParents: async function () {
    try {
      const response = await this.apiCall("/students/parents/list", "GET");
      const parents = this.extractList(response);

      this.parents = parents;
      this.populateParentDropdown();

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
      const value = this.intakeYearFromCode(year.year_code);
      const option = document.createElement("option");
      option.value = value;
      option.textContent = year.year_code;
      select.appendChild(option);
    });

    // Default to the year of the first term once the term list is loaded;
    // otherwise pick the first year.
    if (this.dom.targetTermSelect && this.dom.targetTermSelect.value) {
      const term = this.openTerms?.find(
        (t) => String(t.target_term_id) === this.dom.targetTermSelect.value,
      );
      if (term) {
        const intakeYear = this.intakeYearFromCode(term.year_code || term.year_name);
        select.value = intakeYear;
      }
    }

    if (!select.value && this.academicYears.length > 0) {
      select.value = this.intakeYearFromCode(this.academicYears[0].year_code);
    }
  },

  intakeYearFromCode: function (yearCode) {
    const code = String(yearCode || "");
    const match = code.match(/\d{4}/g);
    if (match && match.length > 1) {
      return match[match.length - 1];
    }
    return match ? match[0] : String(new Date().getFullYear());
  },

  populateTargetTermDropdown: function (terms = []) {
    this.openTerms = Array.isArray(terms) ? terms : [];
    const select = this.dom.targetTermSelect;
    if (!select) return;

    select.innerHTML = '<option value="">Select Term</option>';

    if (this.openTerms.length === 0) {
      select.innerHTML +=
        '<option value="" disabled>No intake windows open — ask an administrator to open one.</option>';
      select.disabled = true;
      return;
    }

    this.openTerms.forEach((term) => {
      const yearLabel = term.year_code || term.year_name || "";
      const label = `${term.term_name || term.term_number || "Term"} ${yearLabel}`.trim();
      const option = document.createElement("option");
      option.value = term.target_term_id ?? term.academic_year_term_id;
      option.textContent = label;
      select.appendChild(option);
    });

    // Default to the first (current/upcoming) open term and sync the year.
    if (this.openTerms.length > 0) {
      select.value = String(
        this.openTerms[0].target_term_id ?? this.openTerms[0].academic_year_term_id,
      );
      select.disabled = false;
      this.syncYearFromTerm();
    }
  },

  syncYearFromTerm: function () {
    const select = this.dom.targetTermSelect;
    const yearSelect = this.dom.academicYearSelect;
    if (!select || !yearSelect) return;

    const term = this.openTerms?.find(
      (t) => String(t.target_term_id ?? t.academic_year_term_id) === select.value,
    );
    if (!term) return;

    const intakeYear = this.intakeYearFromCode(term.year_code || term.year_name);
    if (![...yearSelect.options].some((o) => o.value === intakeYear)) {
      const option = document.createElement("option");
      option.value = intakeYear;
      option.textContent = term.year_code || intakeYear;
      yearSelect.appendChild(option);
    }
    yearSelect.value = intakeYear;
    if (this.dom.academicYearInput) this.dom.academicYearInput.value = intakeYear;
    if (this.dom.targetTermInput) this.dom.targetTermInput.value = select.value;
  },

  loadApplications: async function () {
    this.setTableLoading();

    try {
      const response = await this.apiCall("/admission/queues", "GET");


      if (!this.isSuccessfulResponse(response)) {
        throw new Error(response?.message || "Failed to load applications.");
      }

      const payload = this.unwrapPayload(response);
      const queues = payload?.queues || {};
      const summary = payload?.summary || {};


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

    const docTypeMap = {
      doc_birth_certificate: "birth_certificate",
      doc_passport_photo: "passport_photo",
      doc_parent_id: "parent_id",
      doc_previous_school_report: "previous_school_report",
      doc_immunization_card: "immunization_card",
      doc_progress_report: "progress_report",
      doc_leaving_certificate: "leaving_certificate",
      doc_transfer_letter: "transfer_letter",
      doc_medical_records: "medical_records",
      doc_other: "other",
    };

    // Separate file fields from regular fields
    const data = {};
    const files = [];
    formData.forEach((value, key) => {
      if (docTypeMap[key]) {
        if (value && value.size > 0) {
          files.push({ fieldName: key, docType: docTypeMap[key], file: value });
        }
      } else {
        data[key] = value;
      }
    });

    const isNewParent = data.parent_type === "new";

    // Documents are part of the application — never allow a submission with no
    // documents (there is no "upload documents later" workflow).
    const fileSet = new Set(files.map((f) => f.docType));
    const gradeValue = String(data.grade_applying_for || "").trim();
    const missing = [];
    if (!fileSet.has("birth_certificate")) missing.push("Birth Certificate");
    if (!fileSet.has("passport_photo")) missing.push("Passport Photo");
    if (isNewParent && !fileSet.has("parent_id")) missing.push("Parent/Guardian ID");
    if (this.isUpperGrade(gradeValue)) {
      if (!fileSet.has("previous_school_report")) missing.push("Previous School Report");
      if (!fileSet.has("medical_records")) missing.push("Medical Test Results");
    } else {
      if (!fileSet.has("immunization_card")) missing.push("Immunization Card");
    }
    if (missing.length) {
      this.notify("error", "Missing required documents: " + missing.join(", "));
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-send me-1"></i>Submit Application';
      }
      return;
    }

    try {
      // 1. Register a new parent/guardian when one was entered manually.
      if (isNewParent) {
        const nameParts = String(data.new_parent_name || "").trim().split(/\s+/);
        const createResp = await this.apiCall("/students/parents/create", "POST", {
          first_name: nameParts[0] || "",
          last_name: nameParts.slice(1).join(" ") || nameParts[0] || "",
          id_number: data.new_parent_id_number || null,
          phone_1: data.new_parent_phone || "",
          email: data.new_parent_email || null,
          address: data.new_parent_address || null,
        });
        if (!this.isSuccessfulResponse(createResp)) {
          throw new Error(createResp?.message || "Failed to create parent record.");
        }
        const createPayload = this.unwrapPayload(createResp);
        data.parent_id = createPayload?.id ?? createPayload?.parent_id ?? createPayload?.parentId;
        if (!data.parent_id) {
          throw new Error("New parent record was created but its ID was not returned.");
        }
      }

      delete data.parent_type;
      delete data.new_parent_name;
      delete data.new_parent_id_number;
      delete data.new_parent_phone;
      delete data.new_parent_email;
      delete data.new_parent_address;

      // 2. Submit the application.
      const response = await this.apiCall(
        "/admission/submit-application",
        "POST",
        data,
      );

      if (!this.isSuccessfulResponse(response)) {
        throw new Error(response?.message || "Failed to submit application.");
      }

      const payload = this.unwrapPayload(response);
      const applicationId = payload?.application_id ?? data.application_id;
      if (!applicationId) {
        throw new Error("Application was created but its ID was not returned.");
      }

      // 3. Upload every submitted document through the upload API — in the same
      //    submit action, before the application is considered complete.
      const uploaded = [];
      for (const { docType, file } of files) {
        const fd = new FormData();
        fd.append("application_id", applicationId);
        fd.append("document_type", docType);
        fd.append("document", file);
        try {
          const uploadResp = window.API?.admission?.uploadDocument
            ? await window.API.admission.uploadDocument(fd)
            : await this.apiCall("/admission/upload-document", "POST", fd);
          if (!this.isSuccessfulResponse(uploadResp)) {
            throw new Error(uploadResp?.message || "Upload failed");
          }
          uploaded.push(docType);
        } catch (uploadErr) {
          throw new Error(`Application submitted, but document "${docType}" failed to upload: ${uploadErr?.message || "unknown error"}`);
        }
      }

      this.notify(
        "success",
        `Application submitted successfully${uploaded.length ? ` with ${uploaded.length} document(s).` : "."}`,
      );

      const modalInstance = bootstrap.Modal.getInstance(
        this.dom.newApplicationModal,
      );

      if (modalInstance) {
        modalInstance.hide();
      }

      this.dom.newApplicationForm.reset();
      this.toggleParentType(false);
      this.toggleDocumentRequirements();
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

    const latestValue = (value) => {
      if (!Array.isArray(value)) return value;
      const meaningful = value.filter((item) => item !== null && item !== undefined && item !== '');
      return meaningful.length ? meaningful[meaningful.length - 1] : null;
    };
    const renderValue = (key, value) => {
      if (key === 'assessment_items' && Array.isArray(value)) {
        const unique = new Map();
        value.forEach((item) => {
          if (!item || typeof item !== 'object') return;
          unique.set(String(item.learning_area_id || item.learning_area_name || JSON.stringify(item)), item);
        });
        const rows = [...unique.values()];
        return rows.length
          ? `<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Learning area</th><th>Score</th><th>Grade</th><th>Level</th></tr></thead><tbody>${rows.map((item) => `<tr><td>${this.escapeHtml(item.learning_area_name || '—')}</td><td>${this.escapeHtml(item.score ?? '—')}/${this.escapeHtml(item.max_score ?? 100)}</td><td>${this.escapeHtml(item.grade_code || '—')}</td><td>${this.escapeHtml(item.performance_level || '—')}</td></tr>`).join('')}</tbody></table></div>`
          : 'No assessment items recorded';
      }
      const display = latestValue(value);
      if (display === null || display === undefined || display === '') return '—';
      if (typeof display === 'boolean') return display ? 'Yes' : 'No';
      if (typeof display === 'object') return this.escapeHtml(JSON.stringify(display));
      return this.escapeHtml(String(display));
    };

    return `
      <dl class="row mb-0">
        ${Object.entries(workflowData)
          .map(([key, value]) => `
            <dt class="col-sm-5">${this.escapeHtml(this.formatStatus(key))}</dt>
            <dd class="col-sm-7">${renderValue(key, value)}</dd>
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

  notify: async function (type, message) {
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
      await window.infoDialog('Notice', `Error: ${message}`);
      return;
    }

    console.log(`${type}: ${message}`);
    await window.infoDialog('Notice', message);
  },

  isSuccessfulResponse: function (response) {
    if (!response) return false;

    if (response.success === true) return true;
    if (response.status === true) return true;
    if (response.status === "success") return true;
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

    if (response.data !== undefined) return true;

    // api.js handleApiResponse unwraps response.data on success —
    // if we got a non-null object with no wrapper, it's the payload itself
    if (typeof response === "object" && Object.keys(response).length > 0) {
      return true;
    }

    return false;
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
