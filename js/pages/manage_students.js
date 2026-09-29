/**
 * Manage Students Page Controller
 * Manages student CRUD operations, parent linking, and data loading
 */

window.studentsManagementController = window.studentsManagementController || {
  data: {
    students: [],
    classes: [],
    streams: [],
    studentTypes: [],
    parents: [],
    pagination: { page: 1, limit: 10, total: 0 },
    transportRoutes: [],
    transportStops: [],
  },
  editingId: null,
  bulkImportPreviewState: null,
  bulkImportPreviewToken: 0,

  escapeHtml: function (value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
  },

  avatarUrl: function () {
    return window.KingswayFileLifecycle?.assetUrl?.('students', 'avatar.jpg')
      || `${window.APP_BASE || ''}/uploads/students/avatar.jpg`;
  },

  photoUrl: function (value) {
    return window.KingswayFileLifecycle?.resolveUrl?.(value) || String(value || this.avatarUrl());
  },

  init: async function () {
    if (this.initialized) {
      return;
    }
    this.initialized = true;

    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    if (!this.canPerformAction("view")) {
      this.renderForbidden();
      return;
    }
    await GradingScale.preload();
    await this.loadInitialData();
    await this.loadStudents();
    this.loadPendingPhotos();
    this.attachEventListeners();
  },

  loadPendingPhotos: async function () {
    const panel = document.getElementById('studentPhotoApprovalPanel');
    const rows = document.getElementById('studentPhotoApprovalRows');
    if (!panel || !rows) return;
    try {
      const response = await window.API.students.getPendingPhotos();
      const photos = response?.data?.photos || response?.photos || [];
      panel.classList.toggle('d-none', !photos.length);
      document.getElementById('studentPhotoApprovalCount').textContent = photos.length;
      rows.innerHTML = photos.map((photo) => `
        <div class="col-md-6 col-xl-4">
          <div class="card border-warning h-100">
            <div class="card-body d-flex gap-2 align-items-center">
              <img src="${this.escapeHtml(photo.file_url || this.avatarUrl())}" alt="" style="width:52px;height:64px;object-fit:cover" class="rounded border">
              <div class="flex-grow-1"><strong>${this.escapeHtml([photo.first_name, photo.middle_name, photo.last_name].filter(Boolean).join(' '))}</strong><small class="d-block text-muted">${this.escapeHtml(photo.admission_no || '')}</small>
                <div class="btn-group btn-group-sm mt-2"><button class="btn btn-success" onclick="studentsManagementController.approvePhoto(${Number(photo.id)})">Approve</button><button class="btn btn-outline-danger" onclick="studentsManagementController.rejectPhoto(${Number(photo.id)})">Reject</button></div>
              </div>
            </div>
          </div>
        </div>`).join('');
    } catch (_) { panel.classList.add('d-none'); }
  },

  approvePhoto: async function (versionId) {
    await window.API.students.approvePhoto(versionId, 'Approved by school administrator.');
    await this.loadPendingPhotos();
    await this.loadStudents();
  },

  rejectPhoto: async function (versionId) {
    const reason = window.prompt('Reason for rejecting this photo:');
    if (!reason) return;
    await window.API.students.rejectPhoto(versionId, reason);
    await this.loadPendingPhotos();
  },

  getPrimaryRole: function () {
    const roles = AuthContext.getRoles ? AuthContext.getRoles() : [];
    if (!roles.length) return null;
    const role = roles[0]?.name || roles[0];
    return String(role)
      .toLowerCase()
      .replace(/[\s/]+/g, "_");
  },

  canPerformAction: function (action) {
    if (window.RoleBasedUI?.canPerformAction) {
      const roleBased = window.RoleBasedUI.canPerformAction("students", action);
      if (roleBased) return true;
    }
    const aliases = {
      view: ["students_view", "students_view_all", "students_view_own", "students_edit", "students_create"],
      create: ["students_create"],
      edit: ["students_edit", "students_update"],
      delete: ["students_delete"],
      promote: ["students_promote", "students_generate", "students_edit"],
      export: ["students_export", "students_print"],
    };
    const permissions = aliases[action] || [`students_${action}`];
    return permissions.some((permission) => AuthContext.hasPermission?.(permission));
  },

  canViewSensitiveInfo: function () {
    const permissionCandidates = [
      "students_view_sensitive",
      "parents_view",
      "health_view",
      "discipline_view",
      "admissions_view",
    ];
    if (window.RoleBasedUI?.hasAnyPermission) {
      if (window.RoleBasedUI.hasAnyPermission(permissionCandidates)) {
        return true;
      }
    }

    const role = this.getPrimaryRole();
    return [
      "headteacher",
      "school_administrator",
      "deputy_head_academic",
      "deputy_head_discipline",
      "registrar",
      "director",
      "system_administrator",
    ].includes(role);
  },

  canViewContactInfo: function () {
    const permissionCandidates = [
      "parents_view",
      "communications_view",
      "fees_view",
      "finance_view",
      "admissions_view",
    ];
    if (window.RoleBasedUI?.hasAnyPermission) {
      if (window.RoleBasedUI.hasAnyPermission(permissionCandidates)) {
        return true;
      }
    }

    const role = this.getPrimaryRole();
    return [
      "headteacher",
      "school_administrator",
      "deputy_head_academic",
      "deputy_head_discipline",
      "registrar",
      "director",
      "accountant",
      "bursar",
      "system_administrator",
    ].includes(role);
  },

  normalizeGender: function (value) {
    if (!value) return "";
    const normalized = String(value).toLowerCase();
    if (normalized === "m" || normalized === "male") return "male";
    if (normalized === "f" || normalized === "female") return "female";
    return normalized === "other" ? "other" : "";
  },

  formatGender: function (value) {
    const normalized = this.normalizeGender(value);
    if (normalized === "male") return "Male";
    if (normalized === "female") return "Female";
    if (normalized === "other") return "Other";
    return "-";
  },

  // Load dropdown data
  loadInitialData: async function () {
    // Each source is independent. A fee-context or transport permission
    // failure must never prevent classes, streams, types, or parents from
    // populating the form.
    const load = async (label, loader) => {
      try {
        return await loader();
      } catch (error) {
        console.warn(`Could not load ${label}:`, error);
        return null;
      }
    };

    const importContextResp = await load("existing-student fee context", () =>
      window.API.students.getImportContext()
    );
    const importContext = importContextResp?.data?.data || importContextResp?.data || importContextResp;
    this.data.importContext = importContext || { fee_schedules: [] };
    this.bindImportFinancialInputs();
    this.populateSchoolReliefDropdowns();

    const classesResp = await load("classes", () => window.API.academic.listClasses());
    const classes = this.unwrapList(classesResp, "classes");
    this.data.classes = classes;
    this.populateClassDropdowns();

    const typesResp = await load("student types", () => window.API.finance.listStudentTypes());
    const studentTypes = this.unwrapList(typesResp, "student_types");
    this.data.studentTypes = studentTypes;
    this.populateStudentTypeDropdown();

    const streamsResp = await load("streams", () => window.API.academic.listStreams());
    const streams = this.unwrapList(streamsResp, "streams");
    this.data.streams = streams;
    this.populateStreamFilter();

    await this.loadExistingParents();
    await this.loadTransportOptions();
    this.refreshImportFeePreview();
  },

  bindImportFinancialInputs: function () {
    ["studentClass", "studentTypeId", "academicYearPaidAmount", "currentTermPaidAmount"].forEach((id) => {
      const element = document.getElementById(id);
      if (!element || element.dataset.importBound === "1") return;
      element.addEventListener("change", () => this.refreshImportFeePreview());
      element.addEventListener("input", () => this.refreshImportFeePreview());
      element.dataset.importBound = "1";
    });
  },

  refreshImportFeePreview: function () {
    const context = this.data.importContext || {};
    const schedules = Array.isArray(context.fee_schedules) ? context.fee_schedules : [];
    const classId = String(document.getElementById("studentClass")?.value || "");
    const typeId = String(document.getElementById("studentTypeId")?.value || "");
    const selected = schedules.filter((row) =>
      String(row.class_id || "") === classId && String(row.student_type_id || "") === typeId
    );
    const annualDue = selected.reduce((sum, row) => sum + Number(row.amount || 0), 0);
    const currentTermId = String(context.current_term?.id || "");
    const currentRows = selected.filter((row) => String(row.academic_year_term_id || "") === currentTermId);
    const currentTermDue = currentRows.reduce((sum, row) => sum + Number(row.amount || 0), 0);
    const money = (value) => `KES ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const paid = Math.max(0, Number(document.getElementById("academicYearPaidAmount")?.value || 0));
    const currentPaid = Math.max(0, Number(document.getElementById("currentTermPaidAmount")?.value || 0));
    const balance = Math.max(0, annualDue - paid);
    const advance = Math.max(0, paid - annualDue);

    const yearCode = context.academic_year?.year_code || "";
    const term = context.current_term;
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
    setText("importAcademicYearLabel", yearCode || "—");
    setText("importCurrentTermLabel", term ? `${term.name || term.code || "Term"}` : "—");
    setText("importAnnualDueLabel", money(annualDue));
    setText("importCurrentTermDueLabel", money(currentTermDue));
    setText("importFeeContextStatus", selected.length ? "Live schedule loaded" : "Select class and student type");

    const yearInput = document.getElementById("financialAcademicYearCode");
    if (yearInput) yearInput.value = yearCode;
    const setValue = (id, value) => { const el = document.getElementById(id); if (el) el.value = Number(value || 0).toFixed(2); };
    setValue("feeArrearsAmount", balance);
    setValue("advanceAmount", advance);
    setValue("legacyCalculatedBalance", balance);
  },

  populateSchoolReliefDropdowns: function () {
    const context = this.data.importContext || {};
    const programs = Array.isArray(context.scholarship_programs)
      ? context.scholarship_programs
      : [];
    const programSelect = document.getElementById("schoolSponsorshipProgram");
    if (programSelect) {
      programSelect.innerHTML = '<option value="">No school sponsorship</option>' +
        programs.map((program) => `<option value="${Number(program.id)}">${this.escapeHtml(program.name)}</option>`).join("");
      this.updateSchoolSponsorshipFields();
    }
    const termSelect = document.getElementById("schoolSponsorshipTerm");
    if (termSelect) {
      termSelect.innerHTML = (Array.isArray(context.academic_year_terms) ? context.academic_year_terms : [])
        .map((term) => `<option value="${Number(term.id)}">${this.escapeHtml(term.name || term.code || `Term ${term.term_id}`)}</option>`).join("");
    }

    const waiverSelect = document.getElementById("schoolFeeWaiverType");
    if (waiverSelect) {
      const labels = {
        percentage: "Percentage waiver",
        fixed_amount: "Fixed amount waiver",
        full_waiver: "Full waiver",
        merit: "Merit waiver",
        need_based: "Need-based waiver",
        sibling: "Sibling waiver",
        other: "Other waiver",
      };
      const types = Array.isArray(context.fee_waiver_types)
        ? context.fee_waiver_types.filter((type) => type !== "none")
        : [];
      waiverSelect.innerHTML = '<option value="none">No fee waiver</option>' +
        types.map((type) => `<option value="${this.escapeHtml(type)}">${this.escapeHtml(labels[type] || type)}</option>`).join("");
      this.updateSchoolFeeWaiverFields();
    }
  },

  updateSchoolSponsorshipFields: function () {
    const programId = Number(document.getElementById("schoolSponsorshipProgram")?.value || 0);
    const programs = Array.isArray(this.data.importContext?.scholarship_programs)
      ? this.data.importContext.scholarship_programs
      : [];
    const program = programs.find((item) => Number(item.id) === programId);
    const selected = Boolean(program);
    const show = (id, visible) => {
      const element = document.getElementById(id);
      if (element) element.style.display = visible ? "block" : "none";
    };
    show("schoolSponsorshipCoverageWrap", selected);
    show("schoolSponsorshipReasonWrap", selected);
    show("schoolSponsorshipPercentageWrap", selected && program?.coverage_type === "percentage");
    show("schoolSponsorshipAmountWrap", selected && program?.coverage_type === "fixed_amount");
    show("schoolSponsorshipPeriodWrap", selected);
    const periodType = document.getElementById("schoolSponsorshipPeriodType")?.value || "academic_year";
    show("schoolSponsorshipTermWrap", selected && periodType === "term");
    show("schoolSponsorshipStartsWrap", selected && periodType === "custom");
    show("schoolSponsorshipEndsWrap", selected && periodType === "custom");
    const termSelect = document.getElementById("schoolSponsorshipTerm");
    const starts = document.getElementById("schoolSponsorshipStartsOn");
    const ends = document.getElementById("schoolSponsorshipEndsOn");
    if (termSelect) termSelect.required = selected && periodType === "term";
    if (starts) starts.required = selected && periodType === "custom";
    if (ends) ends.required = selected && periodType === "custom";
    const description = document.getElementById("schoolSponsorshipDescription");
    if (description) description.textContent = program?.description || (selected ? "School-configured sponsorship programme." : "Select a programme configured by the school.");
    const coverage = document.getElementById("schoolSponsorshipCoverage");
    if (coverage) {
      const type = program?.coverage_type;
      const defaultValue = type === "full" ? "100% covered" : type === "percentage" ? `${program.default_percentage ?? "School-defined"}% covered` : type === "fixed_amount" ? "Fixed amount per obligation" : "";
      coverage.value = defaultValue;
    }
    const percentage = document.getElementById("schoolSponsorshipPercentage");
    if (percentage && selected && program?.coverage_type === "percentage" && !percentage.value) percentage.value = program.default_percentage ?? "";
    const amount = document.getElementById("schoolSponsorshipAmount");
    if (amount && selected && program?.coverage_type === "fixed_amount" && !amount.value) amount.value = program.default_amount ?? "";
  },

  updateSchoolFeeWaiverFields: function () {
    const type = document.getElementById("schoolFeeWaiverType")?.value || "none";
    const selected = type !== "none";
    const valueWrap = document.getElementById("schoolFeeWaiverValueWrap");
    const reasonWrap = document.getElementById("schoolFeeWaiverReasonWrap");
    if (valueWrap) valueWrap.style.display = selected && type !== "full_waiver" ? "block" : "none";
    if (reasonWrap) reasonWrap.style.display = selected ? "block" : "none";
    const label = document.getElementById("schoolFeeWaiverValueLabel");
    if (label) label.textContent = type === "percentage" ? "Waiver percentage (%)" : "Waiver amount (KES)";
  },

  loadTransportOptions: async function () {
    try {
      const context = this.data.importContext || {};
      this.data.transportRoutes = Array.isArray(context.transport_routes)
        ? context.transport_routes
        : [];
      this.data.transportStops = Array.isArray(context.transport_stops)
        ? context.transport_stops
        : [];

      // Compatibility fallback for deployments whose context endpoint has
      // not yet been updated. These are read-only lookups only. An empty
      // configured list is valid and must not trigger a permission-sensitive
      // transport-management request.
      const contextHasTransportOptions =
        Array.isArray(context.transport_routes) &&
        Array.isArray(context.transport_stops);
      if (!contextHasTransportOptions) {
        const [routesResponse, stopsResponse] = await Promise.all([
          window.API.transport.getAllRoutes(),
          window.API.transport.getAllStops(),
        ]);
        this.data.transportRoutes = this.unwrapList(routesResponse, "routes");
        this.data.transportStops = this.unwrapList(stopsResponse, "stops");
      }
      const route = document.getElementById("studentTransportRoute");
      if (route) route.innerHTML = '<option value="">Select route</option>' + this.data.transportRoutes.map(item => `<option value="${item.id}">${this.escapeHtml(item.name || item.route_name)}</option>`).join('');
      const periods = Array.isArray(context.transport_periods) ? context.transport_periods : [];
      const periodSelect = document.getElementById("studentTransportPeriod");
      if (periodSelect && periods.length) {
        periodSelect.innerHTML = periods.map(item => `<option value="${this.escapeHtml(item.code)}">${this.escapeHtml(item.label)}</option>`).join('');
        periodSelect.value = periods.some(item => item.code === "term") ? "term" : periods[0].code;
      }
      this.updateStudentTransportStops();
    } catch (error) {
      console.warn("Could not load transport routes and points:", error);
    }
  },

  updateStudentTransportStops: function () {
    const routeId = Number(document.getElementById("studentTransportRoute")?.value || 0);
    const stops = this.data.transportStops.filter(item => Number(item.route_id) === routeId && item.status !== "inactive").sort((a, b) => Number(a.sequence) - Number(b.sequence));
    const options = stops.length ? '<option value="">Select point</option>' + stops.map(item => `<option value="${item.id}">${this.escapeHtml(item.name)}</option>`).join('') : `<option value="">${routeId ? 'No points configured' : 'Select route first'}</option>`;
    ["studentPickupStop", "studentDropoffStop"].forEach(id => { const select = document.getElementById(id); if (select) select.innerHTML = options; });
  },

  toggleStudentTransport: function () {
    const enabled = Boolean(document.getElementById("usesSchoolTransport")?.checked);
    document.getElementById("studentTransportFields")?.classList.toggle("d-none", !enabled);
  },

  loadExistingParents: async function () {
    try {
      const resp = await window.API.students.getParentsList();
      const parents = this.unwrapList(resp, "parents");
      if (parents.length) {
        this.data.parents = parents;
        this.populateParentsDropdown();
      }
    } catch (error) {
      console.warn("Could not load parents:", error);
    }
  },

  populateClassDropdowns: function () {
    const classFilter = document.getElementById("classFilter");
    const studentClass = document.getElementById("studentClass");

    [classFilter, studentClass].forEach((select) => {
      if (!select) return;
      // Keep first option
      const firstOpt = select.options[0];
      select.innerHTML = "";
      select.appendChild(firstOpt);

      this.data.classes.forEach((cls) => {
        const opt = document.createElement("option");
        opt.value = cls.id;
        opt.textContent = cls.name || cls.class_name;
        select.appendChild(opt);
      });
    });
  },

  populateStudentTypeDropdown: function () {
    const select = document.getElementById("studentTypeId");
    if (!select) return;

    const firstOpt = select.options[0];
    select.innerHTML = "";
    select.appendChild(firstOpt);

    this.data.studentTypes.forEach((type) => {
      const opt = document.createElement("option");
      opt.value = type.id;
      opt.textContent = type.name;
      select.appendChild(opt);
    });
  },

  populateStreamFilter: function () {
    const select = document.getElementById("streamFilter");
    if (!select) return;

    const firstOpt = select.options[0];
    select.innerHTML = "";
    select.appendChild(firstOpt);

    this.data.streams.forEach((stream) => {
      const opt = document.createElement("option");
      opt.value = stream.id;
      opt.textContent = `${stream.class_name || ""} ${
        stream.stream_name || stream.name || ""
      }`.trim();
      select.appendChild(opt);
    });
  },

  populateParentsDropdown: function () {
    const select = document.getElementById("existingParentId");
    if (!select) return;

    const firstOpt = select.options[0];
    select.innerHTML = "";
    select.appendChild(firstOpt);

    this.data.parents.forEach((parent) => {
      const opt = document.createElement("option");
      opt.value = parent.id;
      opt.textContent = `${parent.first_name} ${parent.last_name || ""} - ${
        parent.phone_1 || parent.email || "No contact"
      }`;
      select.appendChild(opt);
    });
    if (select.dataset.importBound !== "1") {
      select.addEventListener("change", () => this.populateSelectedExistingParent());
      select.dataset.importBound = "1";
    }
  },

  populateSelectedExistingParent: function () {
    const id = String(document.getElementById("existingParentId")?.value || "");
    const parent = this.data.parents.find((item) => String(item.id) === id);
    if (!parent) return;
    const values = {
      parentFirstName: parent.first_name || "",
      parentLastName: parent.last_name || "",
      parentPhone1: parent.phone_1 || parent.phone || "",
      parentPhone2: parent.phone_2 || "",
      parentEmail: parent.email || "",
      parentOccupation: parent.occupation || "",
      parentAddress: parent.address || "",
    };
    Object.entries(values).forEach(([field, value]) => {
      const element = document.getElementById(field);
      if (element) element.value = value;
    });
  },

  loadStreamsForClass: async function (classId) {
    const streamSelect = document.getElementById("studentStream");
    if (!streamSelect) return;

    // Reset stream dropdown
    streamSelect.innerHTML = '<option value="">Select Stream</option>';

    if (!classId) return;

    try {
      const resp = await window.API.academic.listStreams({
        class_id: classId,
      });
      const streams = this.unwrapList(resp, "streams");
      if (streams.length) {
        this.data.streams = streams;
        streams.forEach((stream) => {
          const opt = document.createElement("option");
          opt.value = stream.id;
          opt.textContent = stream.stream_name || stream.name;
          streamSelect.appendChild(opt);
        });
      }
    } catch (error) {
      console.error("Error loading streams:", error);
    }
  },

  loadStudents: async function (page = 1) {
    try {
      const params = new URLSearchParams({
        page: page,
        limit: this.data.pagination.limit,
      });

      const search = document.getElementById("searchStudents")?.value;
      if (search) params.append("search", search);

      const classFilter = document.getElementById("classFilter")?.value;
      if (classFilter) params.append("class_id", classFilter);

      const streamFilter = document.getElementById("streamFilter")?.value;
      if (streamFilter) params.append("stream_id", streamFilter);

      const genderFilter = document.getElementById("genderFilter")?.value;
      if (genderFilter) params.append("gender", genderFilter);

      const statusFilter = document.getElementById("statusFilter")?.value;
      if (statusFilter) params.append("status", statusFilter);

      const feeStatus = document.getElementById("feeStatusFilter")?.value;
      if (feeStatus) params.append("fee_status", feeStatus);

      const resp = await window.API.students.list(Object.fromEntries(params.entries()));

      const payload = this.unwrapPayload(resp);
      if (payload) {
        this.data.students = payload.students || payload;
        this.data.pagination = payload.pagination || this.data.pagination;
        this.renderTable();
        let statistics = null;
        try {
          const statisticsResponse = await window.API.students.getStats();
          statistics = statisticsResponse?.data?.data || statisticsResponse?.data || statisticsResponse;
        } catch (statisticsError) {
          console.warn("Could not load student statistics:", statisticsError);
        }
        this.updateStatistics(statistics);
      }
    } catch (error) {
      console.error("Error loading students:", error);
      if (Number(error?.code || error?.response?.code || 0) === 403) {
        this.renderForbidden();
        return;
      }
      this.renderTableError(error.message || "Failed to load students");
      this.showError(error.message || "Failed to load students");
    }
  },

  renderForbidden: function () {
    const container = document.getElementById("studentsTableContainer") || document.getElementById("all-students-content");
    if (container) {
      container.innerHTML = `
        <div class="alert alert-warning mb-0">
          <i class="bi bi-shield-lock me-2"></i>
          You do not have permission to view student records.
        </div>`;
    }
    document.querySelectorAll('[data-permission="students_create"], [data-permission="students_edit"], [data-permission="students_delete"]').forEach((el) => {
      el.style.display = "none";
    });
  },

  renderTableError: function (message) {
    const tbody = document.getElementById("studentsTableBody");
    if (!tbody) return;
    tbody.innerHTML = `
      <tr>
        <td colspan="8" class="text-center py-4 text-danger">
          <i class="bi bi-exclamation-triangle me-2"></i>${this.escapeHtml(message)}
        </td>
      </tr>`;
  },

  renderTable: function () {
    const tbody = document.getElementById("studentsTableBody");
    if (!tbody) return;

    if (!this.data.students.length) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No students found</td></tr>`;
      this.renderPagination();
      return;
    }

    const canViewContact = this.canViewContactInfo();

    tbody.innerHTML = this.data.students
      .map((s, i) => {
        const contactValue = canViewContact
          ? this.escapeHtml(s.guardian_contact || s.parent_phone || s.guardian_email || s.parent_email || s.phone || s.email || "-")
          : "Restricted";
        const studentPhoto = this.escapeHtml(this.photoUrl(s.photo_url));
        const studentName = this.escapeHtml(`${s.first_name || ""} ${s.middle_name || ""} ${s.last_name || ""}`.replace(/\s+/g, " ").trim() || "Unnamed learner");

        const actions = [];
        if (this.canPerformAction("view")) {
          actions.push(`
              <button class="btn btn-info btn-sm" onclick="studentsManagementController.viewStudent(${s.id})" title="View">
                  <i class="bi bi-eye"></i>
              </button>
          `);
        }
        if (this.canPerformAction("edit")) {
          actions.push(`
              <button class="btn btn-warning btn-sm" onclick="studentsManagementController.editStudent(${s.id})" title="Edit">
                  <i class="bi bi-pencil"></i>
              </button>
          `);
        }
        if (this.canPerformAction("delete")) {
          actions.push(`
              <button class="btn btn-danger btn-sm" onclick="studentsManagementController.deleteStudent(${s.id})" title="Delete">
                  <i class="bi bi-trash"></i>
              </button>
          `);
        }
        if (this.canPerformAction("edit")) {
          if (s.status === "active") {
            actions.push(`
              <button class="btn btn-outline-secondary btn-sm" onclick="studentsManagementController.deactivateStudent(${s.id})" title="Deactivate">
                <i class="bi bi-person-x"></i>
              </button>
            `);
            actions.push(`
              <button class="btn btn-outline-info btn-sm" onclick="studentsManagementController.transferStudent(${s.id})" title="Transfer">
                <i class="bi bi-arrow-left-right"></i>
              </button>
            `);
          } else {
            actions.push(`
              <button class="btn btn-outline-success btn-sm" onclick="studentsManagementController.activateStudent(${s.id})" title="Activate">
                <i class="bi bi-person-check"></i>
              </button>
            `);
          }
        }

        const actionsHtml = actions.length
          ? `<div class="btn-group btn-group-sm">${actions.join("")}</div>`
          : '<span class="text-muted">No actions</span>';
        const entrySource = s.entry_source === "admission"
          ? '<span class="badge bg-primary">Admitted</span>'
          : '<span class="badge bg-secondary">Existing student</span>';

        return `
            <tr>
                <td>${i + 1}</td>
                <td>${s.admission_no || "-"}</td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <img src="${studentPhoto}" alt="" class="rounded-circle border" style="width:36px;height:36px;object-fit:cover" onerror="this.onerror=null;this.src='${this.escapeHtml(this.avatarUrl())}'">
                    <span>${studentName}</span>
                  </div>
                </td>
                <td>${s.class_name || "-"} ${
                  s.stream_name ? "(" + s.stream_name + ")" : ""
                }</td>
                <td>${this.formatGender(s.gender)}</td>
                <td>${contactValue}</td>
                <td><span class="badge bg-${
                  s.status === "active" ? "success" : "secondary"
                }">${s.status || "unknown"}</span><div class="mt-1">${entrySource}</div></td>
                <td>${actionsHtml}</td>
            </tr>
        `;
      })
      .join("");

    this.renderPagination();
  },

  renderPagination: function () {
    const container = document.getElementById("pagination");
    if (!container) return;

    const { page, total, limit } = this.data.pagination;
    const totalPages = Math.ceil(total / limit);

    document.getElementById("showingFrom").textContent = total > 0 ? (page - 1) * limit + 1 : 0;
    document.getElementById("showingTo").textContent = Math.min(
      page * limit,
      total,
    );
    document.getElementById("totalRecords").textContent = total;

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `<li class="page-item ${i === page ? "active" : ""}">
                <a class="page-link" href="#" onclick="studentsManagementController.loadStudents(${i}); return false;">${i}</a>
            </li>`;
    }
    container.innerHTML = html;
  },

  updateStatistics: async function (statistics = null) {
    try {
      const total = Number(statistics?.total ?? this.data.pagination.total ?? this.data.students.length);
      const active = Number(statistics?.active ?? this.data.students.filter((s) => s.status === "active").length);
      const inactive = Number(statistics?.inactive ?? Math.max(0, total - active));

      document.getElementById("totalStudentsCount").textContent = total;
      document.getElementById("activeStudentsCount").textContent = active;
      document.getElementById("inactiveStudentsCount").textContent = inactive;
      const newCount = document.getElementById("newStudentsCount");
      if (newCount) newCount.textContent = Number(statistics?.new_this_term || 0);
      const outstandingCount = document.getElementById("studentsWithBalanceCount");
      if (outstandingCount) outstandingCount.textContent = Number(statistics?.with_outstanding_fees || 0);
      const paidCount = document.getElementById("studentsPaidCount");
      if (paidCount) paidCount.textContent = Number(statistics?.fully_paid || 0);
      const outstandingTotal = document.getElementById("totalOutstandingFees");
      if (outstandingTotal) outstandingTotal.textContent = `KES ${Number(statistics?.total_outstanding || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    } catch (e) {
      console.warn("Could not update statistics");
    }
  },

  // UI Toggle Functions
  toggleSchoolSponsorshipFields: function () {
    this.updateSchoolSponsorshipFields();
  },

  // Backward-compatible alias for any older page fragment still calling the
  // previous generic name. The form itself is school-sponsorship-only.
  toggleSponsorFields: function () {
    this.updateSchoolSponsorshipFields();
    this.updateSchoolFeeWaiverFields();
  },

  toggleParentType: function () {
    const isNew = document.getElementById("isNewParent")?.checked;
    const newSection = document.getElementById("newParentSection");
    const existingSection = document.getElementById("existingParentSection");

    if (newSection) newSection.style.display = isNew ? "block" : "none";
    if (existingSection)
      existingSection.style.display = isNew ? "none" : "block";
  },

  showStudentModal: function (student = null) {
    this.editingId = student?.id || null;
    this.resetForm();

    const modal = new bootstrap.Modal(document.getElementById("studentModal"));
    const title = document.getElementById("studentModalLabel");

    if (student) {
      title.textContent = "Edit Student";
      this.populateForm(student);
    } else {
      title.textContent = "Add Existing Student";
      this.bindImportFinancialInputs();
      this.refreshImportFeePreview();
    }

    modal.show();
  },

  resetForm: function () {
    const form = document.getElementById("studentForm");
    if (form) form.reset();

    document.getElementById("studentId").value = "";
    const admissionNumber = document.getElementById("admissionNumber");
    if (admissionNumber) {
      admissionNumber.value = "";
      admissionNumber.readOnly = false;
    }
    document.getElementById("isNewParent").checked = true;
    this.toggleParentType();
    this.toggleSponsorFields();
    this.toggleStudentTransport();

    // Reset payment fields
    const paymentAmount = document.getElementById("initialPaymentAmount");
    const paymentMethod = document.getElementById("paymentMethod");
    const paymentRef = document.getElementById("paymentReference");
    const receiptNo = document.getElementById("receiptNo");

    if (paymentAmount) paymentAmount.value = "";
    if (paymentMethod) paymentMethod.value = "";
    if (paymentRef) paymentRef.value = "";
    if (receiptNo) receiptNo.value = "";

    const annualPaid = document.getElementById("academicYearPaidAmount");
    const termPaid = document.getElementById("currentTermPaidAmount");
    if (annualPaid) annualPaid.value = "0";
    if (termPaid) termPaid.value = "0";

    // Reset photo preview
    document.getElementById("studentPhotoPreview").src = this.avatarUrl();
    this.refreshImportFeePreview();
  },

  populateForm: function (student) {
    document.getElementById("studentId").value = student.id;
    document.getElementById("firstName").value = student.first_name || "";
    document.getElementById("middleName").value = student.middle_name || "";
    document.getElementById("lastName").value = student.last_name || "";
    document.getElementById("dateOfBirth").value = student.date_of_birth || "";
    document.getElementById("gender").value = this.normalizeGender(
      student.gender,
    );
    document.getElementById("bloodGroup").value = student.blood_group || "";
    document.getElementById("admissionNumber").value =
      student.admission_no || "";
    document.getElementById("admissionNumber").readOnly = true;
    document.getElementById("studentClass").value = student.class_id || "";

    if (student.class_id) {
      this.loadStreamsForClass(student.class_id).then(() => {
        document.getElementById("studentStream").value =
          student.stream_id || "";
      });
    }

    document.getElementById("studentTypeId").value =
      student.student_type_id || "";
    document.getElementById("studentStatus").value = student.status || "active";
    document.getElementById("assessmentNumber").value =
      student.assessment_number || "";
    document.getElementById("assessmentStatus").value =
      student.assessment_status || (student.assessment_number ? "assigned" : "not_assigned");
    document.getElementById("nemisNumber").value = student.nemis_number || "";
    document.getElementById("nemisStatus").value =
      student.nemis_status || (student.nemis_number ? "assigned" : "not_assigned");

    const financial = student.financial_migration || {};
    const financialYear = financial.academic_year || financial.academic_year_code || this.data.importContext?.academic_year?.year_code || "";
    document.getElementById("financialAcademicYearCode").value = financialYear;
    document.getElementById("academicYearPaidAmount").value = Number(financial.academic_year_paid_amount || 0).toFixed(2);
    document.getElementById("currentTermPaidAmount").value = Number(financial.current_term_paid_amount || 0).toFixed(2);
    document.getElementById("feeArrearsAmount").value = Number(financial.arrears_amount ?? financial.fee_arrears_amount ?? 0).toFixed(2);
    document.getElementById("advanceAmount").value = Number(financial.advance_amount || 0).toFixed(2);

    // Existing awards/waivers are managed by Finance. Populate the current
    // form's configured choices only when the student record exposes them;
    // never invent an external sponsor name or type.
    const sponsorship = (student.active_sponsorships || [])[0] || null;
    if (sponsorship?.scholarship_program_id) {
      document.getElementById("schoolSponsorshipProgram").value = String(sponsorship.scholarship_program_id);
      document.getElementById("schoolSponsorshipPercentage").value = sponsorship.coverage_percentage ?? "";
      document.getElementById("schoolSponsorshipAmount").value = sponsorship.coverage_amount ?? "";
      document.getElementById("schoolSponsorshipPeriodType").value = sponsorship.period_type || "academic_year";
      document.getElementById("schoolSponsorshipStartsOn").value = sponsorship.starts_on || "";
      document.getElementById("schoolSponsorshipEndsOn").value = sponsorship.ends_on || "";
      document.getElementById("schoolSponsorshipReason").value = sponsorship.reason || "";
    }
    const waiver = student.fee_waiver;
    if (waiver) {
      document.getElementById("schoolFeeWaiverType").value = waiver.discount_type || "none";
      document.getElementById("schoolFeeWaiverValue").value = waiver.discount_value ?? waiver.discount_percentage ?? "";
      document.getElementById("schoolFeeWaiverReason").value = waiver.reason || "";
    }
    this.updateSchoolSponsorshipFields();
    this.updateSchoolFeeWaiverFields();

    const parent = (student.parents || [])[0] || null;
    if (parent) {
      document.getElementById("isNewParent").checked = false;
      document.getElementById("existingParentId").value = String(parent.parent_id || "");
      document.getElementById("guardianRelationship").value = parent.relationship || "parent";
    }
    this.toggleParentType();

    const transport = (student.transport_arrangements || [])[0] || null;
    if (transport) {
      document.getElementById("usesSchoolTransport").checked = true;
      document.getElementById("studentTransportRoute").value = String(transport.route_id || "");
      this.updateStudentTransportStops();
      document.getElementById("studentPickupStop").value = String(transport.pickup_stop_id || "");
      document.getElementById("studentDropoffStop").value = String(transport.dropoff_stop_id || "");
      document.getElementById("studentTransportPeriod").value = transport.period_type || "term";
      document.getElementById("studentTransportStart").value = transport.period_start || "";
      document.getElementById("studentTransportEnd").value = transport.period_end || "";
      document.getElementById("studentTransportAmount").value = transport.entitlement_amount ?? transport.expected_amount ?? "";
      document.getElementById("studentTransportNotes").value = transport.notes || "";
    }
    this.toggleStudentTransport();
    this.refreshImportFeePreview();
    // refreshImportFeePreview supplies a live schedule but must not replace
    // the saved financial figures while editing an existing learner.
    document.getElementById("academicYearPaidAmount").value = Number(financial.academic_year_paid_amount || 0).toFixed(2);
    document.getElementById("currentTermPaidAmount").value = Number(financial.current_term_paid_amount || 0).toFixed(2);
    document.getElementById("feeArrearsAmount").value = Number(financial.arrears_amount ?? financial.fee_arrears_amount ?? 0).toFixed(2);
    document.getElementById("advanceAmount").value = Number(financial.advance_amount || 0).toFixed(2);

    // Photo preview
    if (student.photo_url) {
      document.getElementById("studentPhotoPreview").src = this.photoUrl(student.photo_url);
    } else {
      document.getElementById("studentPhotoPreview").src = this.avatarUrl();
    }
  },

  saveStudent: async function (event) {
    event.preventDefault();

    // Recalculate from the current class/type selection immediately before
    // saving; this prevents stale browser values from becoming ledger data.
    this.refreshImportFeePreview();

    const isNew = document.getElementById("isNewParent")?.checked;
    const programId = Number(document.getElementById("schoolSponsorshipProgram")?.value || 0);
    const selectedProgram = (this.data.importContext?.scholarship_programs || []).find((program) => Number(program.id) === programId);
    const schoolSponsorship = selectedProgram ? {
      scholarship_program_id: programId,
      coverage_type: selectedProgram.coverage_type,
      coverage_percentage: Number(document.getElementById("schoolSponsorshipPercentage")?.value || selectedProgram.default_percentage || 0),
      coverage_amount: Number(document.getElementById("schoolSponsorshipAmount")?.value || selectedProgram.default_amount || 0),
      period_type: document.getElementById("schoolSponsorshipPeriodType")?.value || "academic_year",
      academic_year_term_id: Number(document.getElementById("schoolSponsorshipTerm")?.value || 0) || null,
      starts_on: document.getElementById("schoolSponsorshipStartsOn")?.value || null,
      ends_on: document.getElementById("schoolSponsorshipEndsOn")?.value || null,
      reason: document.getElementById("schoolSponsorshipReason")?.value.trim() || "",
    } : null;
    const waiverType = document.getElementById("schoolFeeWaiverType")?.value || "none";
    const schoolFeeWaiver = waiverType !== "none" ? {
      discount_type: waiverType,
      discount_value: Number(document.getElementById("schoolFeeWaiverValue")?.value || 0),
      discount_percentage: waiverType === "percentage" ? Number(document.getElementById("schoolFeeWaiverValue")?.value || 0) : null,
      reason: document.getElementById("schoolFeeWaiverReason")?.value.trim() || "",
    } : null;

    // Build student data
    const studentData = {
      admission_no: document.getElementById("admissionNumber").value || null,
      first_name: document.getElementById("firstName").value,
      middle_name: document.getElementById("middleName").value || null,
      last_name: document.getElementById("lastName").value,
      date_of_birth: document.getElementById("dateOfBirth").value,
      gender: this.normalizeGender(document.getElementById("gender").value),
      stream_id: document.getElementById("studentStream").value,
      student_type_id: document.getElementById("studentTypeId").value || null,
      admission_date: null,
      assessment_number:
        document.getElementById("assessmentNumber").value || null,
      assessment_status:
        document.getElementById("assessmentStatus").value || "not_assigned",
      nemis_number: document.getElementById("nemisNumber").value || null,
      nemis_status:
        document.getElementById("nemisStatus").value || "not_assigned",
      status: document.getElementById("studentStatus").value,
      blood_group: document.getElementById("bloodGroup").value || null,
      school_sponsorship: schoolSponsorship,
      school_fee_waiver: schoolFeeWaiver,
    };

    // Existing-learner import is not a payment transaction. Do not send
    // receipt/reference fields to the import endpoint.
    const paymentAmount = 0;
    studentData.initial_payment_amount = 0;

    const financialMigration = {
      academic_year_code: document.getElementById("financialAcademicYearCode")?.value.trim() || "",
      academic_year_paid_amount: parseFloat(document.getElementById("academicYearPaidAmount")?.value) || 0,
      current_term_paid_amount: parseFloat(document.getElementById("currentTermPaidAmount")?.value) || 0,
      fee_arrears_amount: parseFloat(document.getElementById("feeArrearsAmount")?.value) || 0,
      advance_amount: parseFloat(document.getElementById("advanceAmount")?.value) || 0,
      calculate_from_schedule: true,
    };
    const hasFinancialMigration = financialMigration.academic_year_code ||
      financialMigration.academic_year_paid_amount > 0 ||
      financialMigration.current_term_paid_amount > 0 ||
      financialMigration.fee_arrears_amount > 0 ||
      financialMigration.advance_amount > 0;
    if (hasFinancialMigration) {
      if (financialMigration.current_term_paid_amount > financialMigration.academic_year_paid_amount) {
        this.showError("Current-term paid cannot exceed academic-year paid");
        return;
      }
      studentData.financial_migration = financialMigration;
    }

    // Build parent_info
    if (isNew) {
      studentData.parent_info = {
        first_name: document.getElementById("parentFirstName").value,
        last_name: document.getElementById("parentLastName").value || null,
        gender: document.getElementById("parentGender").value || null,
        phone_1: document.getElementById("parentPhone1").value,
        phone_2: document.getElementById("parentPhone2").value || null,
        email: document.getElementById("parentEmail").value || null,
        occupation: document.getElementById("parentOccupation").value || null,
        address: document.getElementById("parentAddress").value || null,
        relationship: document.getElementById("guardianRelationship").value,
      };
    } else {
      const existingParentId =
        document.getElementById("existingParentId").value;
      if (!existingParentId) {
        this.showError("Please select an existing parent");
        return;
      }
      studentData.parent_id = existingParentId;
      studentData.parent_info = {
        parent_id: existingParentId,
        relationship: document.getElementById("guardianRelationship").value,
      };
    }

    // Validate required fields
    if (
      !studentData.first_name ||
      !studentData.last_name ||
      !studentData.stream_id ||
      !studentData.date_of_birth ||
      !studentData.gender
    ) {
      this.showError("Please fill all required fields");
      return;
    }

    if (schoolSponsorship && !schoolSponsorship.reason) {
      this.showError("Enter the school sponsorship approval reason");
      return;
    }
    if (schoolSponsorship?.coverage_type === "percentage" && (schoolSponsorship.coverage_percentage < 0 || schoolSponsorship.coverage_percentage > 100)) {
      this.showError("Sponsorship percentage must be between 0 and 100");
      return;
    }
    if (schoolSponsorship?.coverage_type === "fixed_amount" && schoolSponsorship.coverage_amount <= 0) {
      this.showError("Enter the fixed school grant amount per obligation");
      return;
    }
    if (schoolFeeWaiver && !schoolFeeWaiver.reason) {
      this.showError("Enter the school fee-waiver approval reason");
      return;
    }
    if (schoolFeeWaiver && schoolFeeWaiver.discount_type !== "full_waiver" && schoolFeeWaiver.discount_value <= 0) {
      this.showError("Enter the approved fee-waiver value");
      return;
    }

    // Validate parent info for new parent
    if (
      isNew &&
      (!studentData.parent_info.first_name ||
        (!studentData.parent_info.phone_1 && !studentData.parent_info.email))
    ) {
      this.showError("Parent must have first name and either phone or email");
      return;
    }

    let transportArrangement = null;
    if (document.getElementById("usesSchoolTransport")?.checked) {
      transportArrangement = {
        route_id: Number(document.getElementById("studentTransportRoute").value),
        pickup_stop_id: Number(document.getElementById("studentPickupStop").value),
        dropoff_stop_id: Number(document.getElementById("studentDropoffStop").value),
        period_type: document.getElementById("studentTransportPeriod").value,
        period_start: document.getElementById("studentTransportStart").value,
        period_end: document.getElementById("studentTransportEnd").value,
        amount_due: Number(document.getElementById("studentTransportAmount").value),
        allocated_school_days: Number(document.getElementById("studentTransportDays").value),
        source_type: "subscription",
        notes: document.getElementById("studentTransportNotes").value.trim(),
      };
      if (!transportArrangement.route_id || !transportArrangement.pickup_stop_id || !transportArrangement.dropoff_stop_id || !transportArrangement.period_start || !transportArrangement.period_end || transportArrangement.allocated_school_days < 1 || transportArrangement.amount_due < 0) {
        this.showError("Complete the transport route, both gate points, eligible dates and agreed charge");
        return;
      }
      if (transportArrangement.period_end < transportArrangement.period_start) {
        this.showError("Transport eligibility end date cannot be before its start date");
        return;
      }
    }

    // Persist the arrangement through the student add/update API so the form
    // has one authoritative write path.
    studentData.transport_arrangement = transportArrangement;

    try {
      const id = document.getElementById("studentId").value;
      let response;
      const photoFile = document.getElementById("studentProfilePic")?.files[0];

      if (id) {
        response = await window.API.students.update(id, studentData);
      } else {
        const streamSelect = document.getElementById("studentStream");
        const existingStudentData = {
          ...studentData,
          class_id: document.getElementById("studentClass").value,
          stream_name:
            streamSelect?.options[streamSelect.selectedIndex]?.textContent?.trim() ||
            null,
          student_type_id: document.getElementById("studentTypeId").value || 1,
          parent: studentData.parent_info,
          initial_payment: 0,
          // The manual form always collects the four finance position fields;
          // persist zero explicitly when no amount has yet been paid.
          financial_migration: financialMigration,
        };
        response = await window.API.students.addExisting(existingStudentData);
      }

      let photoUploaded = false;
      let photoUploadFailed = false;
      if (photoFile) {
        const savedStudentId =
          id ||
          response?.data?.id ||
          response?.data?.data?.id ||
          response?.id;

        if (savedStudentId) {
          const photoData = new FormData();
          photoData.append("student_id", savedStudentId);
          photoData.append("photo", photoFile);
          try {
            await window.API.students.uploadPhoto(photoData);
            photoUploaded = true;
          } catch (photoError) {
            console.error("Photo upload error:", photoError);
            photoUploadFailed = true;
          }
        }
      }

      if (photoUploadFailed) {
        this.showError(
          id
            ? "Student updated, but photo upload failed"
            : "Student created, but photo upload failed",
        );
      } else {
        this.showSuccess(
          photoUploaded
            ? id
              ? "Student updated successfully with photo"
              : "Student created successfully with photo"
            : id
              ? "Student updated successfully"
              : "Student created successfully",
        );
      }
      bootstrap.Modal.getInstance(
        document.getElementById("studentModal"),
      ).hide();
      await this.loadStudents();
    } catch (error) {
      console.error("Save error:", error);
      this.showError(error.message || "Failed to save student");
    }
  },

  editStudent: async function (id) {
    try {
      const resp = await window.API.students.get(id);
      const payload = this.unwrapPayload(resp);
      if (payload) {
        this.showStudentModal(payload);
      }
    } catch (error) {
      this.showError("Failed to load student details");
    }
  },

  viewStudent: async function (id) {
    try {
      const content = document.getElementById("viewStudentContent");
      content.innerHTML =
        '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading student details...</p></div>';
      const modal = new bootstrap.Modal(
        document.getElementById("viewStudentModal"),
      );
      modal.show();

      // Load student data + parallel sub-data
      const [
        studentResp,
        parentsResp,
        feesResp,
        attendanceResp,
        performanceResp,
        disciplineResp,
      ] = await Promise.allSettled([
        window.API.students.get(id),
        window.API.students.getParents(id),
        window.API.students.getFees(id),
        window.API.students.getAttendance(id, { academic_year: new Date().getFullYear() }),
        window.API.students.getPerformance(id),
        window.API.students.getDiscipline(id),
      ]);

      const student = this.unwrapPayload(studentResp.value) || {};
      content.dataset.studentId = (student.id || student.student_id || '').toString();
      const payloadOrError = (result) => {
        if (result.status !== "fulfilled") {
          return {
            load_error: result.reason?.message || "This section could not be loaded from the server.",
          };
        }
        const payload = this.unwrapPayload(result.value) || {};
        return payload.status === "error"
          ? { load_error: payload.message || "The server could not load this section." }
          : payload;
      };
      const parents = payloadOrError(parentsResp);
      const fees = payloadOrError(feesResp);
      const attendance = payloadOrError(attendanceResp);
      const performance = payloadOrError(performanceResp);
      const discipline = payloadOrError(disciplineResp);

      const showSensitive = this.canViewSensitiveInfo();
      const showContact = this.canViewContactInfo();
      const showFinance =
        window.RoleBasedUI?.hasAnyPermission?.(["fees_view", "finance_view"]) ||
        showSensitive;
      const editButton = document.getElementById("viewStudentEditButton");
      if (editButton) editButton.hidden = !this.canPerformAction("edit");

      // Build tabbed UI
      content.innerHTML = `
        <div class="kw-detail-intro">
          <div class="d-flex align-items-center gap-3">
            <img src="${this.photoUrl(student.photo_url)}"
                 class="kw-detail-photo" style="width:96px;height:116px;"
                 alt="Learner photo"
                 onerror="this.onerror=null; this.src='${this.avatarUrl()}'">
            <div>
              <div class="kw-detail-eyebrow">Learner profile</div>
              <h4>${student.first_name || ""} ${student.middle_name || ""} ${student.last_name || ""}</h4>
              <div class="kw-detail-muted">${student.admission_no || "Admission number not assigned"}</div>
              <span class="badge bg-${student.status === "active" ? "success" : student.status === "suspended" ? "danger" : "secondary"} mt-2">
                ${student.status || "unknown"}
            </div>
          </div>
          <div class="text-md-end kw-detail-profile-facts">
            <strong>${this.escapeHtml(student.class_name || "Class not assigned")} ${student.stream_name ? `(${this.escapeHtml(student.stream_name)})` : ""}</strong>
            <span>${this.escapeHtml(student.student_type || student.student_type_name || "Student type not assigned")}</span>
            <span>${student.entry_source === "admission" ? "Admissions record" : "Existing student record"}</span>
          </div>
        </div>
        <div class="kw-detail-card mb-3">
            <ul class="nav nav-tabs" id="studentDetailTabs" role="tablist" aria-label="Student profile sections">
              <li class="nav-item" role="presentation"><a class="nav-link active" id="student-tab-personal" role="tab" aria-selected="true" data-bs-toggle="tab" href="#tabPersonal"><i class="bi bi-person" aria-hidden="true"></i> Personal</a></li>
              <li class="nav-item" role="presentation"><a class="nav-link" id="student-tab-academic" role="tab" aria-selected="false" data-bs-toggle="tab" href="#tabAcademic"><i class="bi bi-mortarboard" aria-hidden="true"></i> Academic</a></li>
              ${showFinance ? '<li class="nav-item" role="presentation"><a class="nav-link" id="student-tab-fees" role="tab" aria-selected="false" data-bs-toggle="tab" href="#tabFees"><i class="bi bi-cash-coin" aria-hidden="true"></i> Fees</a></li>' : ""}
              <li class="nav-item" role="presentation"><a class="nav-link" id="student-tab-attendance" role="tab" aria-selected="false" data-bs-toggle="tab" href="#tabAttendance"><i class="bi bi-calendar-check" aria-hidden="true"></i> Attendance</a></li>
              <li class="nav-item" role="presentation"><a class="nav-link" id="student-tab-performance" role="tab" aria-selected="false" data-bs-toggle="tab" href="#tabPerformance"><i class="bi bi-graph-up" aria-hidden="true"></i> Performance</a></li>
              ${showSensitive ? '<li class="nav-item" role="presentation"><a class="nav-link" id="student-tab-discipline" role="tab" aria-selected="false" data-bs-toggle="tab" href="#tabDiscipline"><i class="bi bi-shield-exclamation" aria-hidden="true"></i> Discipline</a></li>' : ""}
              ${showContact ? '<li class="nav-item" role="presentation"><a class="nav-link" id="student-tab-parents" role="tab" aria-selected="false" data-bs-toggle="tab" href="#tabParents"><i class="bi bi-people" aria-hidden="true"></i> Parents</a></li>' : ""}
            </ul>
        </div>

            <div class="tab-content pt-3">
              <!-- Personal Tab -->
              <div class="tab-pane fade show active" id="tabPersonal">
                <div class="row">
                  <div class="col-md-6">
                    <h6 class="text-primary">Personal Details</h6>
                    <table class="table table-sm table-borderless">
                      <tr><td class="text-muted" style="width:40%">Full Name</td><td>${student.first_name || ""} ${student.middle_name || ""} ${student.last_name || ""}</td></tr>
                      <tr><td class="text-muted">Gender</td><td>${this.formatGender(student.gender)}</td></tr>
                      ${
                        showSensitive
                          ? `
                        <tr><td class="text-muted">Date of Birth</td><td>${student.date_of_birth || "-"}</td></tr>
                        <tr><td class="text-muted">Blood Group</td><td>${student.blood_group || "-"}</td></tr>
                      `
                          : ""
                      }
                      <tr><td class="text-muted">Boarding Status</td><td><span class="badge bg-${student.boarding_status === "boarding" ? "primary" : "info"}">${student.boarding_status || "Day"}</span></td></tr>
                    </table>
                  </div>
                  <div class="col-md-6">
                    <h6 class="text-primary">Learner identifiers</h6>
                    <table class="table table-sm table-borderless">
                      ${
                        showSensitive
                          ? `
                        <tr><td class="text-muted" style="width:40%">KNEC Assessment No.</td><td>${student.assessment_number ? this.escapeHtml(student.assessment_number) : "Not recorded"} <span class="badge bg-${student.assessment_number ? "success" : "secondary"}">${student.assessment_number ? "Recorded" : "Not assigned"}</span></td></tr>
                        <tr><td class="text-muted">NEMIS No.</td><td>${student.nemis_number ? this.escapeHtml(student.nemis_number) : "Not recorded"} <span class="badge bg-${student.nemis_number ? "success" : "secondary"}">${student.nemis_number ? "Recorded" : "Not assigned"}</span></td></tr>
                      `
                          : ""
                      }
                    </table>
                    ${showContact ? `<div class="small text-muted mt-2">Learner contact is handled through the parent or guardian. See the Parents tab.</div>` : ""}
                    ${
                      showFinance && (student.active_sponsorships || []).length
                        ? `
                      <h6 class="text-primary mt-2">School sponsorship / fee relief</h6>
                     <table class="table table-sm table-borderless">
                        ${student.active_sponsorships.map((award) => `<tr><td class="text-muted" style="width:40%">${this.escapeHtml(award.programme_name || "School sponsorship")}</td><td>${award.coverage_type === "percentage" ? `${this.escapeHtml(award.coverage_percentage || 0)}%` : award.coverage_type === "full" ? "Full coverage" : `KES ${Number(award.coverage_amount || 0).toLocaleString()}`} · ${this.escapeHtml(award.period_type || "academic year")}</td></tr>`).join("")}
                     </table>
                    `
                        : ""
                    }
                  </div>
                </div>
              </div>

              <!-- Academic Tab -->
              <div class="tab-pane fade" id="tabAcademic">
                <div class="row">
                  <div class="col-md-6">
                    <h6 class="text-primary">Current Enrollment</h6>
                    <table class="table table-sm table-borderless">
                      <tr><td class="text-muted" style="width:40%">Admission No</td><td><strong>${student.admission_no || "-"}</strong></td></tr>
                      <tr><td class="text-muted">Record source</td><td>${student.entry_source === "admission" ? "Admitted through admissions" : "Existing student added to register"}</td></tr>
                      <tr><td class="text-muted">Class / Stream</td><td>${student.class_name || "-"} ${student.stream_name ? "(" + student.stream_name + ")" : ""}</td></tr>
                      <tr><td class="text-muted">Student Type</td><td>${student.student_type || student.student_type_name || "-"}</td></tr>
                      ${student.entry_source === "admission" ? `<tr><td class="text-muted">Admission date</td><td>${student.admission_date || "-"}</td></tr>` : ""}
                      <tr><td class="text-muted">Status</td><td><span class="badge bg-${student.status === "active" ? "success" : "secondary"}">${student.status || "-"}</span></td></tr>
                    </table>
                    <h6 class="text-primary mt-3">Current learning areas</h6>
                    ${(student.learning_areas || []).length
                      ? `<div class="d-flex flex-wrap gap-2">${student.learning_areas.map((area) => `<span class="badge rounded-pill text-bg-light border">${this.escapeHtml(area.name || area.code || "Learning area")}</span>`).join("")}</div>`
                      : '<p class="text-muted mb-0">No learning areas are configured for this class and stream.</p>'}
                    <h6 class="text-primary mt-3">Transport</h6>
                    ${(student.transport_arrangements || []).length
                      ? student.transport_arrangements.slice(0, 1).map((transport) => `<div class="small"><strong>${this.escapeHtml(transport.route_name || "Assigned route")}</strong> <span class="badge text-bg-success">${this.escapeHtml(transport.status || "active")}</span><br><span class="text-muted">Pickup:</span> ${this.escapeHtml(transport.pickup_stop || "Not recorded")} · <span class="text-muted">Drop-off:</span> ${this.escapeHtml(transport.dropoff_stop || "Not recorded")}</div>`).join("")
                      : '<p class="text-muted mb-0">No transport subscription is recorded.</p>'}
                  </div>
                  <div class="col-md-6">
                    <h6 class="text-primary">Performance Summary</h6>
                    ${this._renderPerformanceMini(performance)}
                  </div>
                </div>
              </div>

              <!-- Fees Tab -->
              ${
                showFinance
                  ? `
              <div class="tab-pane fade" id="tabFees">
                ${this._renderFeesTab(fees, student)}
              </div>`
                  : ""
              }

              <!-- Attendance Tab -->
              <div class="tab-pane fade" id="tabAttendance">
                ${this._renderAttendanceTab(attendance)}
              </div>

              <!-- Performance Tab -->
              <div class="tab-pane fade" id="tabPerformance">
                ${this._renderPerformanceTab(performance)}
              </div>

              <!-- Discipline Tab -->
              ${
                showSensitive
                  ? `
              <div class="tab-pane fade" id="tabDiscipline">
                ${this._renderDisciplineTab(discipline)}
              </div>`
                  : ""
              }

              <!-- Parents Tab -->
              ${
                showContact
                  ? `
              <div class="tab-pane fade" id="tabParents">
                ${this._renderParentsTab(parents)}
              </div>`
                  : ""
              }
            </div>
      `;
    } catch (error) {
      console.error("Error viewing student:", error);
      this.showError("Failed to load student details");
    }
  },

  editViewedStudent: function () {
    const id = Number(document.getElementById("viewStudentContent")?.dataset.studentId || 0);
    if (id) this.editStudent(id);
  },

  printStudentDetails: function () {
    const modal = document.getElementById("viewStudentModal");
    if (!modal) return;
    const previousTitle = document.title;
    document.title = "Student profile";
    window.print();
    document.title = previousTitle;
  },

  _renderPerformanceMini: function (performance) {
    const records = Array.isArray(performance)
      ? performance
      : performance?.records || performance?.subjects || performance?.data || [];
    if (!records.length)
      return '<p class="text-muted">No performance data available</p>';

    let totalScore = 0,
      count = 0;
    records.forEach((r) => {
      if (r.score || r.average) {
        totalScore += parseFloat(r.score || r.average);
        count++;
      }
    });
    const avg = count > 0 ? (totalScore / count).toFixed(1) : "-";
    const grade = count > 0 ? this._gradeFromScore(totalScore / count) : "-";

    return `
      <div class="text-center">
        <h2 class="text-primary">${avg}</h2>
        <p class="text-muted">Average Score</p>
        <span class="badge bg-primary fs-6">${grade}</span>
        <p class="mt-2 text-muted">${count} subject(s) graded</p>
      </div>`;
  },

  _renderFeesTab: function (fees, student) {
    if (fees?.load_error) return `<div class="alert alert-danger" role="alert"><strong>Fees unavailable.</strong> ${this.escapeHtml(fees.load_error)} Refresh the profile and try again.</div>`;
    const summary = fees?.summary || fees || {};
    const payments = fees?.payments || fees?.payment_history || [];
    const totalFees = parseFloat(summary.total_fees || summary.expected || 0);
    const totalPaid = parseFloat(summary.total_paid || summary.paid || 0);
    const balance = parseFloat(
      summary.balance || summary.outstanding || totalFees - totalPaid,
    );
    const coveredAmount = parseFloat(
      summary.covered_amount ?? totalPaid + parseFloat(summary.total_waived || 0),
    );
    const pct = Math.round(
      parseFloat(summary.coverage_percentage ?? summary.payment_percentage ?? (totalFees > 0 ? (coveredAmount / totalFees) * 100 : 0)),
    );

    let paymentRows = "";
    if (payments.length) {
      paymentRows = payments
        .slice(0, 10)
        .map(
          (p) => `
        <tr>
          <td>${p.payment_date || p.date || "-"}</td>
          <td>${p.payment_method || p.method || "-"}</td>
          <td>${p.reference || p.receipt_no || "-"}</td>
          <td class="text-end">KES ${parseFloat(p.amount || 0).toLocaleString()}</td>
        </tr>`,
        )
        .join("");
    } else {
      paymentRows =
        '<tr><td colspan="4" class="text-center text-muted">No payment records</td></tr>';
    }

    const migration = summary.migration_position || {};
    const obligations = fees?.obligations || [];
    const termRows = obligations.length
      ? obligations.map((o) => `<tr><td>${o.term_name || `Term ${o.term_number || "-"}`}</td><td class="text-end">KES ${parseFloat(o.amount_due || 0).toLocaleString()}</td><td class="text-end">KES ${parseFloat(o.amount_waived || 0).toLocaleString()}</td><td class="text-end">KES ${parseFloat(o.amount_paid || 0).toLocaleString()}</td><td class="text-end">KES ${parseFloat(o.balance || 0).toLocaleString()}</td></tr>`).join("")
      : '<tr><td colspan="5" class="text-center text-muted">No fee obligations found</td></tr>';

    return `
      <div class="row mb-3">
        <div class="col-md-3"><div class="card border-primary"><div class="card-body text-center py-2">
          <small class="text-muted">Total Fees</small><h5 class="mb-0">KES ${totalFees.toLocaleString()}</h5>
        </div></div></div>
        <div class="col-md-3"><div class="card border-success"><div class="card-body text-center py-2">
          <small class="text-muted">Paid</small><h5 class="mb-0 text-success">KES ${totalPaid.toLocaleString()}</h5>
        </div></div></div>
        <div class="col-md-3"><div class="card border-danger"><div class="card-body text-center py-2">
          <small class="text-muted">Balance</small><h5 class="mb-0 text-danger">KES ${balance.toLocaleString()}</h5>
        </div></div></div>
        <div class="col-md-3"><div class="card border-info"><div class="card-body text-center py-2">
          <small class="text-muted">Fees covered</small>
          <div class="progress mt-1" style="height:20px;">
            <div class="progress-bar bg-${pct >= 100 ? "success" : pct >= 50 ? "info" : "danger"}" style="width:${Math.min(pct, 100)}%">${pct}%</div>
          </div>
        </div></div></div>
      </div>
      <div class="card border-0 bg-light mb-3">
        <div class="card-body py-3">
          <div class="d-flex justify-content-between align-items-center mb-2"><strong>Fee position</strong><span class="text-muted small">${migration.academic_year || summary.academic_year || "Current academic year"}</span></div>
          <div class="row g-2 small">
            <div class="col-md-3"><span class="text-muted d-block">Expected for year (tuition)</span><strong>KES ${parseFloat(migration.gross_annual_fees || summary.annual_expected || summary.tuition_fees || totalFees).toLocaleString()}</strong></div>
            <div class="col-md-3"><span class="text-muted d-block">Sponsorship / waivers</span><strong class="text-success">KES ${parseFloat(summary.total_waived || migration.annual_sponsorship || 0).toLocaleString()}</strong></div>
            <div class="col-md-3"><span class="text-muted d-block">Paid this academic year</span><strong>KES ${parseFloat(summary.annual_paid ?? migration.academic_year_paid_amount ?? totalPaid).toLocaleString()}</strong></div>
            <div class="col-md-3"><span class="text-muted d-block">Recorded paid this term (${this.escapeHtml(summary.current_term || "current")})</span><strong>KES ${parseFloat(summary.current_term_paid ?? migration.current_term_paid_amount ?? 0).toLocaleString()}</strong></div>
          </div>
          <div class="row g-2 small mt-2">
            <div class="col-md-3"><span class="text-muted d-block">Net expected for year</span><strong>KES ${parseFloat(migration.net_annual_fees || (totalFees - (summary.total_waived || 0))).toLocaleString()}</strong></div>
            <div class="col-md-3"><span class="text-muted d-block">Year balance</span><strong class="${balance > 0 ? "text-danger" : "text-success"}">KES ${balance.toLocaleString()}</strong></div>
            <div class="col-md-3"><span class="text-muted d-block">${this.escapeHtml(summary.current_term || "Current term")} expected (gross)</span><strong>KES ${parseFloat(summary.current_term_gross_expected ?? 0).toLocaleString()}</strong></div>
            <div class="col-md-3"><span class="text-muted d-block">Term net due / balance</span><strong>KES ${parseFloat(summary.current_term_net_expected ?? summary.current_term_expected ?? 0).toLocaleString()} / ${parseFloat(summary.current_term_balance || 0).toLocaleString()}</strong></div>
            <div class="col-md-3"><span class="text-muted d-block">Historical record</span><strong>Retained in migration record</strong></div>
          </div>
        </div>
      </div>
      <h6>Expected, paid and balance by term</h6>
      <div class="table-responsive"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Term</th><th class="text-end">Expected</th><th class="text-end">Sponsorship / waiver</th><th class="text-end">Ledger credit</th><th class="text-end">Balance</th></tr></thead><tbody>${termRows}</tbody></table></div>
      <h6>Recent Payments</h6>
      <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>Date</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th></tr></thead>
        <tbody>${paymentRows}</tbody>
      </table>`;
  },

  _renderAttendanceTab: function (attendance) {
    if (attendance?.load_error) return `<div class="alert alert-danger" role="alert"><strong>Attendance unavailable.</strong> ${this.escapeHtml(attendance.load_error)} Refresh the profile and try again.</div>`;
    const records = attendance?.records || attendance?.data || [];
    const summary = attendance?.summary || {};
    const expectedDays = parseInt(summary.expected_days ?? summary.total_days ?? summary.total ?? 0);
    const markedDays = parseInt(summary.marked_days ?? summary.total ?? 0);
    const unmarkedDays = parseInt(summary.unmarked_days ?? Math.max(expectedDays - markedDays, 0));
    const present = parseInt(summary.present || 0);
    const absent = parseInt(summary.absent || 0);
    const late = parseInt(summary.late || 0);
    const rate = expectedDays > 0 ? Math.round((present / expectedDays) * 100) : 0;
    const unmarkedDateText = Array.isArray(summary.unmarked_dates) && summary.unmarked_dates.length
      ? summary.unmarked_dates.join(", ")
      : "None";

    let recentRows = "";
    if (records.length) {
      recentRows = records
        .slice(0, 15)
        .map(
          (r) => `
        <tr>
          <td>${r.date || r.attendance_date || "-"}</td>
          <td><span class="badge bg-${r.status === "present" ? "success" : r.status === "late" ? "warning" : "danger"}">${r.status || "-"}</span></td>
          <td>${r.remarks || "-"}</td>
        </tr>`,
        )
        .join("");
    } else {
      recentRows =
        '<tr><td colspan="3" class="text-center text-muted">No attendance records</td></tr>';
    }

    return `
      <div class="row mb-3">
        <div class="col-md-3"><div class="card border-primary"><div class="card-body text-center py-2">
          <small class="text-muted">Expected School Days</small><h5 class="mb-0">${expectedDays}</h5>
        </div></div></div>
        <div class="col-md-3"><div class="card border-secondary"><div class="card-body text-center py-2">
          <small class="text-muted">Days Marked</small><h5 class="mb-0">${markedDays}</h5>
        </div></div></div>
        <div class="col-md-3"><div class="card border-warning"><div class="card-body text-center py-2">
          <small class="text-muted">Days Not Marked</small><h5 class="mb-0 text-warning">${unmarkedDays}</h5>
        </div></div></div>
        <div class="col-md-3"><div class="card border-success"><div class="card-body text-center py-2">
          <small class="text-muted">Present</small><h5 class="mb-0 text-success">${present}</h5>
        </div></div></div>
        <div class="col-md-3"><div class="card border-danger"><div class="card-body text-center py-2">
          <small class="text-muted">Absent</small><h5 class="mb-0 text-danger">${absent}</h5>
        </div></div></div>
        <div class="col-md-3"><div class="card border-info"><div class="card-body text-center py-2">
          <small class="text-muted">Attendance Rate</small>
          <div class="progress mt-1" style="height:20px;">
            <div class="progress-bar bg-${rate >= 90 ? "success" : rate >= 75 ? "warning" : "danger"}" style="width:${rate}%">${rate}%</div>
          </div>
        </div></div></div>
      </div>
      <div class="alert alert-warning py-2">
        <strong>Attendance coverage:</strong> ${this.escapeHtml(summary.attendance_date_from || "-")} to ${this.escapeHtml(summary.attendance_date_to || "-")}.
        <strong>${unmarkedDays}</strong> expected school day(s) have no attendance mark.
        <div class="small mt-1"><strong>Unmarked dates:</strong> ${this.escapeHtml(unmarkedDateText)}</div>
      </div>
      ${late > 0 ? `<div class="alert alert-warning py-1 mb-2"><small><i class="bi bi-clock"></i> Late arrivals: <strong>${late}</strong></small></div>` : ""}
      <h6>Recent Attendance</h6>
      <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
        <tbody>${recentRows}</tbody>
      </table>`;
  },

  _renderPerformanceTab: function (performance) {
    if (performance?.load_error) return `<div class="alert alert-danger" role="alert"><strong>Performance unavailable.</strong> ${this.escapeHtml(performance.load_error)} Refresh the profile and try again.</div>`;
    const records = Array.isArray(performance)
      ? performance
      : performance?.records || performance?.subjects || performance?.data || [];
    if (!records.length)
      return '<div class="alert alert-info">No performance records found for this student.</div>';

    let rows = records
      .map((r) => {
        const score = parseFloat(r.score || r.average || r.marks || 0);
        const grade = this._gradeFromScore(score);
        return `
        <tr>
          <td>${r.subject_name || r.subject || r.learning_area || "-"}</td>
          <td class="text-center">${score.toFixed(1)}</td>
          <td class="text-center"><span class="badge bg-${score >= 70 ? "success" : score >= 50 ? "warning" : "danger"}">${grade}</span></td>
          <td>${r.teacher_comment || r.remarks || "-"}</td>
        </tr>`;
      })
      .join("");

    let totalScore = 0,
      count = 0;
    records.forEach((r) => {
      const s = parseFloat(r.score || r.average || r.marks || 0);
      if (s > 0) {
        totalScore += s;
        count++;
      }
    });
    const avg = count > 0 ? (totalScore / count).toFixed(1) : "-";

    return `
      <div class="row mb-3">
        <div class="col-md-4"><div class="card border-primary"><div class="card-body text-center py-2">
          <small class="text-muted">Average Score</small><h4 class="mb-0 text-primary">${avg}</h4>
        </div></div></div>
        <div class="col-md-4"><div class="card border-info"><div class="card-body text-center py-2">
          <small class="text-muted">Subjects</small><h4 class="mb-0">${count}</h4>
        </div></div></div>
        <div class="col-md-4"><div class="card border-success"><div class="card-body text-center py-2">
          <small class="text-muted">Grade</small><h4 class="mb-0 text-success">${count > 0 ? this._gradeFromScore(totalScore / count) : "-"}</h4>
        </div></div></div>
      </div>
      <table class="table table-sm table-bordered table-hover">
        <thead class="table-light"><tr><th>Subject</th><th class="text-center">Score</th><th class="text-center">Grade</th><th>Remarks</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>`;
  },

  _renderDisciplineTab: function (discipline) {
    if (discipline?.load_error) return `<div class="alert alert-danger" role="alert"><strong>Discipline unavailable.</strong> ${this.escapeHtml(discipline.load_error)} Refresh the profile and try again.</div>`;
    const cases =
      discipline?.cases ||
      discipline?.records ||
      discipline?.data ||
      (Array.isArray(discipline) ? discipline : []);
    if (!cases.length)
      return '<div class="alert alert-success"><i class="bi bi-check-circle"></i> No discipline cases recorded.</div>';

    const rows = cases
      .slice(0, 15)
      .map(
        (c) => `
      <tr>
        <td>${c.incident_date || c.date || "-"}</td>
        <td>${c.description || c.offense || "-"}</td>
        <td><span class="badge bg-${c.severity === "high" ? "danger" : c.severity === "medium" ? "warning" : "secondary"}">${c.severity || "-"}</span></td>
        <td>${c.action_taken || "-"}</td>
        <td><span class="badge bg-${c.status === "resolved" ? "success" : "warning"}">${c.status || "-"}</span></td>
      </tr>`,
      )
      .join("");

    return `
      <div class="alert alert-warning py-1 mb-2"><small>Total cases: <strong>${cases.length}</strong></small></div>
      <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>Date</th><th>Description</th><th>Severity</th><th>Action Taken</th><th>Status</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>`;
  },

  _renderParentsTab: function (parents) {
    if (parents?.load_error) return `<div class="alert alert-danger" role="alert"><strong>Guardians unavailable.</strong> ${this.escapeHtml(parents.load_error)} Refresh the profile and try again.</div>`;
    const list = Array.isArray(parents)
      ? parents
      : parents?.parents || parents?.data || [];
    if (!list.length)
      return '<div class="alert alert-info">No parent/guardian information available.</div>';

    return list
      .map(
        (p) => `
      <div class="card mb-2">
        <div class="card-body py-2">
          <div class="row">
            <div class="col-md-4">
              <strong>${p.first_name || ""} ${p.last_name || ""}</strong><br>
              <small class="text-muted">${p.relationship || "Guardian"}</small>
            </div>
            <div class="col-md-4">
              <small><i class="bi bi-telephone"></i> ${p.phone || p.phone1 || "-"}</small><br>
              <small><i class="bi bi-envelope"></i> ${p.email || "-"}</small>
            </div>
            <div class="col-md-4">
              <small><i class="bi bi-briefcase"></i> ${p.occupation || "-"}</small><br>
              <small><i class="bi bi-geo-alt"></i> ${p.address || "-"}</small>
            </div>
          </div>
        </div>
      </div>
    `,
      )
      .join("");
  },

  _gradeFromScore: function (score) {
    return GradingScale.grade(score) || "-";
  },

  deleteStudent: async function (id) {
    const confirmed = await this.showConfirmModal({
      title: "Delete student",
      message: "This will remove the student master record. Continue only if this is intentional.",
      confirmText: "Delete Student",
      confirmClass: "btn-danger",
    });
    if (!confirmed) return;

    try {
      await window.API.students.delete(id);
      this.showSuccess("Student deleted successfully");
      await this.loadStudents();
    } catch (error) {
      this.showError("Failed to delete student");
    }
  },

  deactivateStudent: async function (id) {
    const result = await this.showConfirmModal({
      title: "Deactivate student",
      message: "Record the reason for deactivating this student.",
      confirmText: "Deactivate Student",
      confirmClass: "btn-warning",
      requireReason: true,
      reasonLabel: "Deactivation reason",
    });
    if (!result.confirmed) return;

    try {
      await window.API.students.update(id, {
        status: "inactive",
        deactivation_reason: result.reason,
      });
      this.showSuccess("Student deactivated");
      await this.loadStudents();
    } catch (error) {
      this.showError(error.message || "Failed to deactivate student");
    }
  },

  activateStudent: async function (id) {
    const confirmed = await this.showConfirmModal({
      title: "Reactivate student",
      message: "This will restore the student to active status.",
      confirmText: "Reactivate Student",
      confirmClass: "btn-success",
    });
    if (!confirmed) return;

    try {
      await window.API.students.update(id, { status: "active" });
      this.showSuccess("Student activated");
      await this.loadStudents();
    } catch (error) {
      this.showError(error.message || "Failed to activate student");
    }
  },

  transferStudent: async function (id) {
    // Build a quick transfer modal
    const student = this.data.students.find((s) => s.id == id);
    const classOptions = this.data.classes
      .map((c) => `<option value="${c.id}">${c.name || c.class_name}</option>`)
      .join("");

    let modalHtml = `
      <div class="modal fade" id="transferStudentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header bg-info text-white">
              <h5 class="modal-title">Transfer Student — ${student ? student.first_name + " " + student.last_name : ""}</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" id="transferStudentId" value="${id}">
              <div class="mb-3">
                <label class="form-label">Transfer To Class <span class="text-danger">*</span></label>
                <select id="transferTargetClass" class="form-select" required onchange="studentsManagementController.loadTransferStreams(this.value)">
                  <option value="">Select Class</option>
                  ${classOptions}
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Stream <span class="text-danger">*</span></label>
                <select id="transferTargetStream" class="form-select" required>
                  <option value="">Select Stream</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Reason</label>
                <textarea id="transferReason" class="form-control" rows="2" placeholder="Reason for transfer"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-info" onclick="studentsManagementController.executeTransfer()">
                <i class="bi bi-arrow-left-right"></i> Transfer
              </button>
            </div>
          </div>
        </div>
      </div>`;

    document.getElementById("transferStudentModal")?.remove();
    document.body.insertAdjacentHTML("beforeend", modalHtml);
    new bootstrap.Modal(document.getElementById("transferStudentModal")).show();
  },

  loadTransferStreams: async function (classId) {
    const select = document.getElementById("transferTargetStream");
    if (!select) return;
    select.innerHTML = '<option value="">Loading...</option>';

    try {
      const resp = await window.API.academic.listStreams({ class_id: classId });
      const payload = this.unwrapPayload(resp);
      const streams = Array.isArray(payload)
        ? payload
        : payload?.streams || payload?.data || [];

      select.innerHTML = '<option value="">Select Stream</option>';
      streams.forEach((s) => {
        const opt = document.createElement("option");
        opt.value = s.id;
        opt.textContent = s.name || s.stream_name;
        select.appendChild(opt);
      });
    } catch (error) {
      select.innerHTML = '<option value="">No streams found</option>';
    }
  },

  executeTransfer: async function () {
    const studentId = document.getElementById("transferStudentId")?.value;
    const targetClass = document.getElementById("transferTargetClass")?.value;
    const targetStream = document.getElementById("transferTargetStream")?.value;
    const reason = document.getElementById("transferReason")?.value;

    if (!targetClass || !targetStream) {
      this.showError("Please select both class and stream");
      return;
    }

    try {
      await window.API.students.startTransferWorkflow({
        student_id: studentId,
        target_class_id: targetClass,
        target_stream_id: targetStream,
        reason: reason || "Class transfer",
      });

      bootstrap.Modal.getInstance(
        document.getElementById("transferStudentModal"),
      )?.hide();
      this.showSuccess("Transfer initiated successfully");
      await this.loadStudents();
    } catch (error) {
      this.showError(error.message || "Failed to transfer student");
    }
  },

  // Filter functions
  searchStudents: function (value) {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => this.loadStudents(), 300);
  },

  filterByClass: function (value) {
    this.loadStudents();
  },

  filterByStream: function (value) {
    this.loadStudents();
  },

  filterByGender: function (value) {
    this.loadStudents();
  },

  filterByStatus: function (value) {
    this.loadStudents();
  },

  filterByFeeStatus: function (value) {
    this.loadStudents();
  },

  // Bulk import
  showBulkImportModal: function () {
    const modal = new bootstrap.Modal(
      document.getElementById("bulkImportModal"),
    );
    const results = document.getElementById("bulkImportResults");
    if (results) {
      results.style.display = "none";
      results.innerHTML = "";
    }
    this.bulkImportPreviewState = null;
    this.bulkImportPreviewToken++;
    const fileInput = document.getElementById("bulkImportFile");
    if (fileInput) fileInput.value = "";
    const preview = document.getElementById("bulkImportPreview");
    if (preview) preview.style.display = "none";
    const submit = document.getElementById("bulkImportSubmit");
    if (submit) submit.disabled = true;
    modal.show();
  },

  previewBulkImportFile: async function (file) {
    const previewToken = ++this.bulkImportPreviewToken;
    const preview = document.getElementById("bulkImportPreview");
    const filename = document.getElementById("bulkImportPreviewFilename");
    const summary = document.getElementById("bulkImportPreviewSummary");
    const table = document.getElementById("bulkImportPreviewTable");
    const note = document.getElementById("bulkImportPreviewNote");
    const submit = document.getElementById("bulkImportSubmit");
    this.bulkImportPreviewState = null;
    if (submit) submit.disabled = true;
    if (!preview || !summary || !table) return;

    preview.style.display = "block";
    if (filename) filename.textContent = file?.name || "";
    summary.innerHTML = '<div class="alert alert-info mb-0">Reading spreadsheet in this browser…</div>';
    table.innerHTML = "";
    if (note) note.textContent = "";

    try {
      if (!file) throw new Error("Choose a spreadsheet file to preview.");
      if (file.size > 5 * 1024 * 1024) throw new Error("The selected file exceeds the 5 MB upload limit.");
      const extension = file.name.split(".").pop()?.toLowerCase();
      if (!['csv', 'xlsx', 'xls', 'ods'].includes(extension)) {
        throw new Error("Only CSV, XLSX, XLS, and ODS spreadsheet files can be previewed and imported.");
      }
      if (!window.XLSX?.read) throw new Error("The spreadsheet preview library did not load. Refresh the page and try again.");

      const workbook = window.XLSX.read(await file.arrayBuffer(), {
        type: "array",
        cellDates: false,
        dense: true,
      });
      if (previewToken !== this.bulkImportPreviewToken) return;
      const sheetName = workbook.SheetNames?.[0];
      if (!sheetName) throw new Error("The selected file does not contain a worksheet.");
      const sheet = workbook.Sheets[sheetName];
      const rows = window.XLSX.utils.sheet_to_json(sheet, {
        header: 1,
        defval: "",
        raw: false,
        blankrows: true,
        range: { s: { r: 0, c: 0 }, e: { r: 5000, c: 29 } },
      });
      if (!rows.length || !rows[0]?.some((value) => String(value).trim())) {
        throw new Error("The selected file is empty or has no column headers.");
      }

      const normalizeHeader = (value) => {
        let key = String(value ?? "").replace(/^\uFEFF/, "").trim().toLowerCase()
          .replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "");
        const aliases = {
          admission_number: "admission_no", class: "class_name", class_name: "class_name",
          primary_phone: "parent_phone", parent_phone_primary: "parent_phone",
          student_type_name: "student_type", student_type_: "student_type",
          first_name_: "first_name", last_name_: "last_name", date_of_birth_: "date_of_birth",
          gender_: "gender", status_: "status", parent_relationship_: "parent_relationship",
          parent_first_name_: "parent_first_name", parent_last_name_: "parent_last_name",
        };
        return aliases[key] || key;
      };
      const headers = rows[0].map(normalizeHeader);
      const required = [
        "first_name", "last_name", "date_of_birth", "gender", "class_name", "student_type",
        "status", "parent_relationship", "parent_first_name", "parent_last_name", "parent_phone",
      ];
      const missingHeaders = required.filter((field) => !headers.includes(field));
      const records = rows.slice(1)
        .map((row, index) => ({
          rowNumber: index + 2,
          values: row,
          blank: !row.some((value) => String(value ?? "").trim()),
        }))
        .filter((record) => !record.blank);

      let incompleteRows = 0;
      let repeatedAdmissions = 0;
      const admissions = new Set();
      records.forEach((record) => {
        const valueFor = (field) => {
          const index = headers.indexOf(field);
          return index < 0 ? "" : String(record.values[index] ?? "").trim();
        };
        record.missingValues = required.filter((field) => headers.includes(field) && !valueFor(field));
        if (record.missingValues.length) incompleteRows++;
        const admission = valueFor("admission_no").toLowerCase();
        if (admission && admissions.has(admission)) repeatedAdmissions++;
        if (admission) admissions.add(admission);
      });

      this.bulkImportPreviewState = {
        file,
        headers,
        records,
        blockingErrors: missingHeaders.length + incompleteRows,
      };
      if (submit) submit.disabled = this.bulkImportPreviewState.blockingErrors > 0 || records.length === 0;

      const alertClass = missingHeaders.length || incompleteRows || records.length === 0 ? "alert-warning" : "alert-success";
      summary.innerHTML = `<div class="alert ${alertClass} py-2 mb-0">
        <strong>${records.length}</strong> rows to import
        <span class="mx-1">|</span><strong>${missingHeaders.length + incompleteRows}</strong> required-field issues
        <span class="mx-1">|</span><strong>${repeatedAdmissions}</strong> repeated admission numbers in this file
      </div>`;

      const shownRecords = records.slice(0, 100);
      const shownHeaders = rows[0].slice(0, 30);
      const previewRows = shownRecords.map((record) => {
        const rowCells = shownHeaders.map((_, columnIndex) =>
          `<td class="text-nowrap">${this.escapeHtml(record.values[columnIndex] ?? "")}</td>`
        ).join("");
        const rowClass = record.missingValues.length ? "table-warning" : "";
        const rowStatus = record.missingValues.length
          ? `<span class="badge text-bg-warning">${record.missingValues.length} missing</span>`
          : '<span class="badge text-bg-success">Ready</span>';
        return `<tr class="${rowClass}"><th scope="row">${record.rowNumber}</th><td>${rowStatus}</td>${rowCells}</tr>`;
      }).join("");
      table.innerHTML = `<table class="table table-sm table-striped table-bordered mb-0">
        <thead class="table-light sticky-top"><tr><th scope="col">Row</th><th scope="col">Check</th>${shownHeaders.map((header) => `<th scope="col" class="text-nowrap">${this.escapeHtml(header)}</th>`).join("")}</tr></thead>
        <tbody>${previewRows}</tbody>
      </table>`;

      const notes = [];
      if (!records.length) notes.push("No non-empty student rows were found in this worksheet.");
      if (missingHeaders.length) notes.push(`Missing required columns: ${missingHeaders.join(", ")}.`);
      if (incompleteRows) notes.push(`${incompleteRows} rows have one or more required values missing. Complete these before uploading.`);
      if (repeatedAdmissions) notes.push("Repeated admission numbers are likely to be skipped by the server.");
      if (records.length > shownRecords.length) notes.push(`Showing the first ${shownRecords.length} of ${records.length} non-empty rows.`);
      if (rows.length >= 5001) notes.push("The preview is limited to the first 5,000 worksheet rows; the server will validate the uploaded file.");
      if (rows[0].length > shownHeaders.length) notes.push(`Showing the first ${shownHeaders.length} columns.`);
      if (note) note.textContent = notes.join(" ") || "Review the rows above. The server will revalidate all data before saving.";
    } catch (error) {
      if (previewToken !== this.bulkImportPreviewToken) return;
      summary.innerHTML = `<div class="alert alert-danger mb-0">${this.escapeHtml(error.message || "Could not read this file.")}</div>`;
      table.innerHTML = "";
      if (note) note.textContent = "Choose a valid CSV, XLSX, XLS, or ODS spreadsheet.";
    }
  },

  bulkImport: async function (event) {
    event.preventDefault();
    const file = document.getElementById("bulkImportFile")?.files[0];
    if (!file) {
      this.showError("Please select a file");
      return;
    }
    if (this.bulkImportPreviewState?.file !== file) {
      this.showError("Wait for the selected file preview to finish before uploading.");
      return;
    }
    if (this.bulkImportPreviewState.blockingErrors > 0) {
      this.showError("Fix the missing required columns or values shown in the preview before uploading.");
      return;
    }

    const formData = new FormData();
    formData.append("file", file);

    try {
      const resp = await window.API.apiCall(
        "/students/import-existing",
        "POST",
        formData,
        {},
        { isFile: true },
      );

      this.renderBulkImportResults(resp);

      const hasErrors = Array.isArray(resp?.errors) && resp.errors.length > 0;
      const hasWarnings =
        Array.isArray(resp?.warnings) && resp.warnings.length > 0;
      const hasDuplicates =
        Array.isArray(resp?.duplicates) && resp.duplicates.length > 0;

      if (hasErrors) {
        this.showError(
          "Add completed with errors. Review the details below.",
        );
      } else if (hasWarnings || hasDuplicates) {
        this.showSuccess(
          "Add completed with warnings. Review the details below.",
        );
      } else {
        this.showSuccess("Students added successfully");
        bootstrap.Modal.getInstance(
          document.getElementById("bulkImportModal"),
        ).hide();
      }

      await this.loadStudents();
    } catch (error) {
      const errorData = error?.response?.data || {};
      this.renderBulkImportResults(errorData, true);
      this.showError(error.message || "Failed to add students");
    }
  },

  exportStudents: async function () {
    this.showInfo("Student export is disabled until a routed, permission-checked export API is available.");
  },

  downloadTemplate: async function (format = 'xlsx') {
    try {
      await window.API.imports.downloadTemplate('students', format);
    } catch (error) {
      this.showError(error?.message || 'Could not download the student import template.');
    }
  },

  renderBulkImportResults: function (result, isError = false) {
    const container = document.getElementById("bulkImportResults");
    if (!container) return;

    const payload = this.unwrapPayload(result) || {};
    const errors = payload?.errors || [];
    const warnings = payload?.warnings || [];
    const duplicates = payload?.duplicates || [];
    const processed =
      payload?.processed ?? payload?.successful ?? payload?.insert?.processed ?? null;

    const summaryItems = [];
    if (processed !== null)
      summaryItems.push(`<strong>${processed}</strong> processed`);
    summaryItems.push(`<strong>${errors.length}</strong> errors`);
    summaryItems.push(`<strong>${warnings.length}</strong> warnings`);
    summaryItems.push(`<strong>${duplicates.length}</strong> duplicates`);

    const renderList = (items, title, color) => {
      if (!items.length) return "";
      const rows = items
        .map(
          (item) => `
            <tr>
              <td>${this.escapeHtml(item.row || "-")}</td>
              <td>${this.escapeHtml(item.admission_no || "-")}</td>
              <td>${this.escapeHtml(item.message || item.error || "-")}</td>
            </tr>
          `,
        )
        .join("");
      return `
        <div class="mt-3">
          <h6 class="text-${color} mb-2">${title} (${items.length})</h6>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:80px;">Row</th>
                  <th style="width:160px;">Admission No</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody>${rows}</tbody>
            </table>
          </div>
        </div>
      `;
    };

    container.innerHTML = `
      <div class="alert ${isError ? "alert-danger" : "alert-info"} mb-2">
        <i class="bi bi-info-circle me-1"></i>
        ${summaryItems.join(" | ")}
      </div>
      ${renderList(errors, "Errors", "danger")}
      ${renderList(warnings, "Warnings", "warning")}
      ${renderList(duplicates, "Duplicates", "secondary")}
    `;
    container.style.display = "block";
  },

  unwrapList: function (response, key) {
    if (!response) return [];
    if (Array.isArray(response)) return response;
    if (Array.isArray(response.data)) return response.data;
    if (response.data && Array.isArray(response.data.data))
      return response.data.data;
    if (key && response.data && Array.isArray(response.data[key]))
      return response.data[key];
    if (
      key &&
      response.data &&
      response.data.data &&
      Array.isArray(response.data.data[key])
    )
      return response.data.data[key];
    if (key && Array.isArray(response[key])) return response[key];
    return [];
  },

  unwrapPayload: function (response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined)
      return response.data.data;
    return response;
  },

  attachEventListeners: function () {
    document.getElementById("bulkImportFile")?.addEventListener("change", (event) => {
      const resultBox = document.getElementById("bulkImportResults");
      if (resultBox) { resultBox.style.display = "none"; resultBox.innerHTML = ""; }
      this.previewBulkImportFile(event.target.files?.[0] || null);
    });
    document.getElementById("usesSchoolTransport")?.addEventListener("change", () => this.toggleStudentTransport());
    document.getElementById("studentTransportRoute")?.addEventListener("change", () => this.updateStudentTransportStops());
    // Photo preview
    document
      .getElementById("studentProfilePic")
      ?.addEventListener("change", function (e) {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function (e) {
            document.getElementById("studentPhotoPreview").src =
              e.target.result;
          };
          reader.readAsDataURL(file);
        }
      });

    // Existing parent selection preview
    document
      .getElementById("existingParentId")
      ?.addEventListener("change", function (e) {
        const selectedId = e.target.value;
        const preview = document.getElementById("selectedParentPreview");
        const info = document.getElementById("selectedParentInfo");

        if (selectedId) {
          const parent = studentsManagementController.data.parents.find(
            (p) => p.id == selectedId,
          );
          if (parent) {
            info.textContent = `${parent.first_name} ${
              parent.last_name || ""
            } - ${parent.phone_1 || parent.email}`;
            preview.style.display = "block";
          }
        } else {
          preview.style.display = "none";
        }
      });
  },

  showConfirmModal: function ({
    title,
    message,
    confirmText = "Confirm",
    confirmClass = "btn-primary",
    requireReason = false,
    reasonLabel = "Reason",
  }) {
    const modalId = "studentsConfirmModal";
    document.getElementById(modalId)?.remove();

    const modalEl = document.createElement("div");
    modalEl.className = "modal fade";
    modalEl.id = modalId;
    modalEl.tabIndex = -1;

    const dialog = document.createElement("div");
    dialog.className = "modal-dialog modal-dialog-scrollable modal-dialog-centered";
    const content = document.createElement("div");
    content.className = "modal-content";

    const header = document.createElement("div");
    header.className = "modal-header bg-primary text-white";
    const titleEl = document.createElement("h5");
    titleEl.className = "modal-title";
    titleEl.textContent = title;
    const closeButton = document.createElement("button");
    closeButton.type = "button";
    closeButton.className = "btn-close btn-close-white";
    closeButton.setAttribute("data-bs-dismiss", "modal");
    header.append(titleEl, closeButton);

    const body = document.createElement("div");
    body.className = "modal-body";
    const messageEl = document.createElement("p");
    messageEl.textContent = message;
    body.appendChild(messageEl);

    let reasonInput = null;
    let reasonError = null;
    if (requireReason) {
      const label = document.createElement("label");
      label.className = "form-label";
      label.setAttribute("for", "studentsConfirmReason");
      label.textContent = `${reasonLabel} *`;
      reasonInput = document.createElement("textarea");
      reasonInput.id = "studentsConfirmReason";
      reasonInput.className = "form-control";
      reasonInput.rows = 3;
      reasonInput.required = true;
      reasonError = document.createElement("div");
      reasonError.className = "text-danger small mt-1";
      reasonError.style.display = "none";
      reasonError.textContent = "Reason is required.";
      body.append(label, reasonInput, reasonError);
    }

    const footer = document.createElement("div");
    footer.className = "modal-footer";
    const cancelButton = document.createElement("button");
    cancelButton.type = "button";
    cancelButton.className = "btn btn-secondary";
    cancelButton.setAttribute("data-bs-dismiss", "modal");
    cancelButton.textContent = "Cancel";
    const actionButton = document.createElement("button");
    actionButton.type = "button";
    actionButton.className = `btn ${confirmClass}`;
    actionButton.textContent = confirmText;
    footer.append(cancelButton, actionButton);

    content.append(header, body, footer);
    dialog.appendChild(content);
    modalEl.appendChild(dialog);
    document.body.appendChild(modalEl);

    return new Promise((resolve) => {
      const modal = new bootstrap.Modal(modalEl);
      let resolved = false;

      const finish = (value) => {
        resolved = true;
        modal.hide();
        resolve(value);
      };

      actionButton.addEventListener("click", () => {
        const reason = reasonInput?.value.trim() || "";
        if (requireReason && !reason) {
          reasonError.style.display = "block";
          reasonInput.focus();
          return;
        }
        finish(requireReason ? { confirmed: true, reason } : true);
      });

      modalEl.addEventListener("hidden.bs.modal", () => {
        modalEl.remove();
        if (!resolved) {
          resolve(requireReason ? { confirmed: false, reason: "" } : false);
        }
      });

      modal.show();
    });
  },

  showSuccess: function (message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "success");
    } else if (typeof showNotification === "function") {
      showNotification(message, "success");
    } else {
      console.info(message);
    }
  },

  showError: function (message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "error");
    } else if (typeof showNotification === "function") {
      showNotification(message, "error");
    } else {
      console.error(message);
    }
  },

  showInfo: function (message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "info");
    } else if (typeof showNotification === "function") {
      showNotification(message, "info");
    } else {
      console.info(message);
    }
  },
};

// Initialize whether this file was loaded with the page or injected by all_students.php.
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    window.studentsManagementController.init()
  );
} else {
  window.studentsManagementController.init();
}
