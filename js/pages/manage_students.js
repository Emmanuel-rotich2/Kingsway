/**
 * Manage Students Page Controller
 * Manages student CRUD operations, parent linking, and data loading
 */

const studentsManagementController = {
  data: {
    students: [],
    classes: [],
    streams: [],
    studentTypes: [],
    parents: [],
    pagination: { page: 1, limit: 10, total: 0 },
  },
  editingId: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    await this.loadInitialData();
    await this.loadStudents();
    this.attachEventListeners();
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
      return window.RoleBasedUI.canPerformAction("students", action);
    }
    return AuthContext.hasPermission?.(`students_${action}`) || false;
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
    try {
      // Load classes
      const classesResp = await window.API.academic.listClasses();
      const classes = this.unwrapList(classesResp);
      if (classes.length) {
        this.data.classes = classes;
        this.populateClassDropdowns();
      }

      // Load student types
      const typesResp = await window.API.finance.listStudentTypes();
      const studentTypes = this.unwrapList(typesResp);
      if (studentTypes.length) {
        this.data.studentTypes = studentTypes;
        this.populateStudentTypeDropdown();
      }

      // Load streams for filter dropdown
      const streamsResp = await window.API.academic.listStreams();
      const streams = this.unwrapList(streamsResp);
      if (streams.length) {
        this.data.streams = streams;
        this.populateStreamFilter();
      }

      // Load existing parents for dropdown
      await this.loadExistingParents();
    } catch (error) {
      console.error("Error loading initial data:", error);
    }
  },

  loadExistingParents: async function () {
    try {
      const resp = await window.API.students.getParentsList();
      const parents = this.unwrapList(resp);
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
      const streams = this.unwrapList(resp);
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

      const resp = await window.API.apiCall(
        `/students?${params.toString()}`,
        "GET",
      );

      const payload = this.unwrapPayload(resp);
      if (payload) {
        this.data.students = payload.students || payload;
        this.data.pagination = payload.pagination || this.data.pagination;
        this.renderTable();
        this.updateStatistics();
      }
    } catch (error) {
      console.error("Error loading students:", error);
      this.showError("Failed to load students");
    }
  },

  renderTable: function () {
    const tbody = document.getElementById("studentsTableBody");
    if (!tbody) return;

    if (!this.data.students.length) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No students found</td></tr>`;
      return;
    }

    const canViewContact = this.canViewContactInfo();

    tbody.innerHTML = this.data.students
      .map((s, i) => {
        const contactValue = canViewContact
          ? s.phone || s.email || "-"
          : "Restricted";

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

        return `
            <tr>
                <td>${i + 1}</td>
                <td>${s.admission_no || "-"}</td>
                <td>${s.first_name || ""} ${s.middle_name || ""} ${
                  s.last_name || ""
                }</td>
                <td>${s.class_name || "-"} ${
                  s.stream_name ? "(" + s.stream_name + ")" : ""
                }</td>
                <td>${this.formatGender(s.gender)}</td>
                <td>${contactValue}</td>
                <td><span class="badge bg-${
                  s.status === "active" ? "success" : "secondary"
                }">${s.status || "unknown"}</span></td>
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

    document.getElementById("showingFrom").textContent = (page - 1) * limit + 1;
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

  updateStatistics: async function () {
    try {
      const total = this.data.pagination.total || this.data.students.length;
      const active = this.data.students.filter(
        (s) => s.status === "active",
      ).length;
      const inactive = this.data.students.filter(
        (s) => s.status !== "active",
      ).length;

      document.getElementById("totalStudentsCount").textContent = total;
      document.getElementById("activeStudentsCount").textContent = active;
      document.getElementById("inactiveStudentsCount").textContent = inactive;
    } catch (e) {
      console.warn("Could not update statistics");
    }
  },

  // UI Toggle Functions
  toggleSponsorFields: function () {
    const isSponsored = document.getElementById("isSponsored")?.checked;

    // Show/hide sponsor details
    ["sponsorNameDiv", "sponsorTypeDiv", "sponsorWaiverDiv"].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.style.display = isSponsored ? "block" : "none";
    });

    // Show/hide payment section (hide if sponsored)
    const paymentSection = document.getElementById("paymentFieldsSection");
    const paymentHeader = document.getElementById("paymentSectionHeader");
    const paymentAlert = document.getElementById("paymentAlert");

    if (paymentSection)
      paymentSection.style.display = isSponsored ? "none" : "";
    if (paymentHeader) paymentHeader.style.display = isSponsored ? "none" : "";
    if (paymentAlert) paymentAlert.style.display = isSponsored ? "none" : "";

    // Clear payment fields if sponsored
    if (isSponsored) {
      const paymentAmount = document.getElementById("initialPaymentAmount");
      const paymentMethod = document.getElementById("paymentMethod");
      const paymentRef = document.getElementById("paymentReference");
      const receiptNo = document.getElementById("receiptNo");

      if (paymentAmount) paymentAmount.value = "";
      if (paymentMethod) paymentMethod.value = "";
      if (paymentRef) paymentRef.value = "";
      if (receiptNo) receiptNo.value = "";
    }
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
      title.textContent = "Add Student";
      // Set default admission date to today
      document.getElementById("admissionDate").value = new Date()
        .toISOString()
        .split("T")[0];
    }

    modal.show();
  },

  resetForm: function () {
    const form = document.getElementById("studentForm");
    if (form) form.reset();

    document.getElementById("studentId").value = "";
    document.getElementById("isNewParent").checked = true;
    this.toggleParentType();
    this.toggleSponsorFields();

    // Reset payment fields
    const paymentAmount = document.getElementById("initialPaymentAmount");
    const paymentMethod = document.getElementById("paymentMethod");
    const paymentRef = document.getElementById("paymentReference");
    const receiptNo = document.getElementById("receiptNo");

    if (paymentAmount) paymentAmount.value = "";
    if (paymentMethod) paymentMethod.value = "";
    if (paymentRef) paymentRef.value = "";
    if (receiptNo) receiptNo.value = "";

    // Reset photo preview
    document.getElementById("studentPhotoPreview").src =
      (window.APP_BASE || "") + "/images/default-avatar.png";
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
    document.getElementById("studentClass").value = student.class_id || "";

    if (student.class_id) {
      this.loadStreamsForClass(student.class_id).then(() => {
        document.getElementById("studentStream").value =
          student.stream_id || "";
      });
    }

    document.getElementById("studentTypeId").value =
      student.student_type_id || "";
    document.getElementById("admissionDate").value =
      student.admission_date || "";
    document.getElementById("studentStatus").value = student.status || "active";
    document.getElementById("boardingStatus").value =
      student.boarding_status || "day";
    document.getElementById("assessmentNumber").value =
      student.assessment_number || "";
    document.getElementById("assessmentStatus").value =
      student.assessment_status || "";
    document.getElementById("nemisNumber").value = student.nemis_number || "";
    document.getElementById("nemisStatus").value =
      student.nemis_status || "not_assigned";
    document.getElementById("studentEmail").value = student.email || "";
    document.getElementById("studentPhone").value = student.phone || "";
    document.getElementById("studentAddress").value = student.address || "";

    // Sponsorship
    const isSponsored =
      student.is_sponsored === 1 ||
      student.is_sponsored === "1" ||
      student.is_sponsored === true;
    document.getElementById("isSponsored").checked = isSponsored;
    document.getElementById("sponsorName").value = student.sponsor_name || "";
    document.getElementById("sponsorType").value = student.sponsor_type || "";
    document.getElementById("sponsorWaiverPercentage").value =
      student.sponsor_waiver_percentage || "";
    this.toggleSponsorFields();

    // Photo preview
    if (student.photo_url) {
      document.getElementById("studentPhotoPreview").src = student.photo_url;
    }
  },

  saveStudent: async function (event) {
    event.preventDefault();

    const isNew = document.getElementById("isNewParent")?.checked;
    const isSponsored = document.getElementById("isSponsored")?.checked;

    // Build student data
    const studentData = {
      admission_no: document.getElementById("admissionNumber").value,
      first_name: document.getElementById("firstName").value,
      middle_name: document.getElementById("middleName").value || null,
      last_name: document.getElementById("lastName").value,
      date_of_birth: document.getElementById("dateOfBirth").value,
      gender: this.normalizeGender(document.getElementById("gender").value),
      stream_id: document.getElementById("studentStream").value,
      student_type_id: document.getElementById("studentTypeId").value || null,
      admission_date: document.getElementById("admissionDate").value,
      assessment_number:
        document.getElementById("assessmentNumber").value || null,
      assessment_status:
        document.getElementById("assessmentStatus").value || "not_assigned",
      nemis_number: document.getElementById("nemisNumber").value || null,
      nemis_status:
        document.getElementById("nemisStatus").value || "not_assigned",
      status: document.getElementById("studentStatus").value,
      blood_group: document.getElementById("bloodGroup").value || null,
      is_sponsored: isSponsored ? 1 : 0,
      sponsor_name: document.getElementById("sponsorName").value || null,
      sponsor_type: document.getElementById("sponsorType").value || null,
      sponsor_waiver_percentage:
        document.getElementById("sponsorWaiverPercentage").value || null,
    };

    // Add payment data if not sponsored (required for new students)
    if (!isSponsored && !this.editingId) {
      const paymentAmount =
        parseFloat(document.getElementById("initialPaymentAmount").value) || 0;
      const paymentMethod = document.getElementById("paymentMethod").value;

      if (paymentAmount <= 0 || !paymentMethod) {
        this.showError(
          "Students must have an initial payment recorded OR be marked as sponsored. Please enter payment details or check 'Is Sponsored'.",
        );
        return;
      }

      studentData.initial_payment_amount = paymentAmount;
      studentData.payment_method = paymentMethod;
      studentData.payment_reference =
        document.getElementById("paymentReference").value || null;
      studentData.receipt_no =
        document.getElementById("receiptNo").value || null;
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
      !studentData.admission_no ||
      !studentData.first_name ||
      !studentData.last_name ||
      !studentData.stream_id ||
      !studentData.date_of_birth ||
      !studentData.gender ||
      !studentData.admission_date
    ) {
      this.showError("Please fill all required fields");
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

    try {
      const id = document.getElementById("studentId").value;
      let response;
      const photoFile = document.getElementById("studentProfilePic")?.files[0];

      if (id) {
        response = await window.API.apiCall(
          `/students/${id}`,
          "PUT",
          studentData,
        );
      } else {
        response = await window.API.apiCall("/students", "POST", studentData);
      }

      if (photoFile) {
        this.showInfo(
          "Student saved. Photo upload is not available yet for this form.",
        );
      }

      this.showSuccess(
        id ? "Student updated successfully" : "Student created successfully",
      );
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
      const resp = await window.API.apiCall(`/students/${id}`, "GET");
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
        window.API.apiCall(`/students/${id}`, "GET"),
        window.API.students.getParents(id),
        window.API.students.getFees(id),
        window.API.students.getAttendance(id),
        window.API.students.getPerformance(id),
        window.API.students.getDiscipline(id),
      ]);

      const student = this.unwrapPayload(studentResp.value) || {};
      content.dataset.studentId = (student.id || student.student_id || '').toString();
      const parents = this.unwrapPayload(parentsResp.value) || [];
      const fees = this.unwrapPayload(feesResp.value) || {};
      const attendance = this.unwrapPayload(attendanceResp.value) || {};
      const performance = this.unwrapPayload(performanceResp.value) || {};
      const discipline = this.unwrapPayload(disciplineResp.value) || {};

      const showSensitive = this.canViewSensitiveInfo();
      const showContact = this.canViewContactInfo();
      const showFinance =
        window.RoleBasedUI?.hasAnyPermission?.(["fees_view", "finance_view"]) ||
        showSensitive;

      // Build tabbed UI
      content.innerHTML = `
        <div class="row mb-3">
          <div class="col-md-2 text-center">
            <img src="${student.photo_url || (window.APP_BASE || "") + "/images/default-avatar.png"}"
                 class="img-thumbnail rounded-circle" width="100" height="100" style="object-fit:cover;"
                 onerror="this.src=(window.APP_BASE || '') + '/images/default-avatar.png'">
            <h6 class="mt-2 mb-0">${student.first_name || ""} ${student.middle_name || ""} ${student.last_name || ""}</h6>
            <small class="text-muted">${student.admission_no || ""}</small><br>
            <span class="badge bg-${student.status === "active" ? "success" : student.status === "suspended" ? "danger" : "secondary"} mt-1">
              ${student.status || "unknown"}
            </span>
          </div>
          <div class="col-md-10">
            <ul class="nav nav-tabs" id="studentDetailTabs">
              <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabPersonal"><i class="bi bi-person"></i> Personal</a></li>
              <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabAcademic"><i class="bi bi-mortarboard"></i> Academic</a></li>
              ${showFinance ? '<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabFees"><i class="bi bi-cash-coin"></i> Fees</a></li>' : ""}
              <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabAttendance"><i class="bi bi-calendar-check"></i> Attendance</a></li>
              <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabPerformance"><i class="bi bi-graph-up"></i> Performance</a></li>
              ${showSensitive ? '<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabDiscipline"><i class="bi bi-shield-exclamation"></i> Discipline</a></li>' : ""}
              ${showContact ? '<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabParents"><i class="bi bi-people"></i> Parents</a></li>' : ""}
            </ul>

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
                    <h6 class="text-primary">Contact & IDs</h6>
                    <table class="table table-sm table-borderless">
                      ${
                        showContact
                          ? `
                        <tr><td class="text-muted" style="width:40%">Email</td><td>${student.email || "-"}</td></tr>
                        <tr><td class="text-muted">Phone</td><td>${student.phone || "-"}</td></tr>
                        <tr><td class="text-muted">Address</td><td>${student.address || "-"}</td></tr>
                      `
                          : '<tr><td class="text-muted" colspan="2">Contact info restricted</td></tr>'
                      }
                      ${
                        showSensitive
                          ? `
                        <tr><td class="text-muted">KNEC Assessment</td><td>${student.assessment_number || "-"} <span class="badge bg-secondary">${student.assessment_status || ""}</span></td></tr>
                        <tr><td class="text-muted">NEMIS Number</td><td>${student.nemis_number || "-"} <span class="badge bg-secondary">${student.nemis_status || ""}</span></td></tr>
                      `
                          : ""
                      }
                    </table>
                    ${
                      showFinance && student.is_sponsored
                        ? `
                      <h6 class="text-primary mt-2">Sponsorship</h6>
                      <table class="table table-sm table-borderless">
                        <tr><td class="text-muted" style="width:40%">Sponsor</td><td>${student.sponsor_name || "-"}</td></tr>
                        <tr><td class="text-muted">Type</td><td>${student.sponsor_type || "-"}</td></tr>
                        <tr><td class="text-muted">Waiver</td><td>${student.sponsor_waiver_percentage || 0}%</td></tr>
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
                      <tr><td class="text-muted">Class / Stream</td><td>${student.class_name || "-"} ${student.stream_name ? "(" + student.stream_name + ")" : ""}</td></tr>
                      <tr><td class="text-muted">Student Type</td><td>${student.student_type || student.student_type_name || "-"}</td></tr>
                      <tr><td class="text-muted">Admission Date</td><td>${student.admission_date || "-"}</td></tr>
                      <tr><td class="text-muted">Status</td><td><span class="badge bg-${student.status === "active" ? "success" : "secondary"}">${student.status || "-"}</span></td></tr>
                    </table>
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
          </div>
        </div>
      `;
    } catch (error) {
      console.error("Error viewing student:", error);
      this.showError("Failed to load student details");
    }
  },

  _renderPerformanceMini: function (performance) {
    const records = performance?.records || performance?.subjects || [];
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
    const summary = fees?.summary || fees || {};
    const payments = fees?.payments || fees?.payment_history || [];
    const totalFees = parseFloat(summary.total_fees || summary.expected || 0);
    const totalPaid = parseFloat(summary.total_paid || summary.paid || 0);
    const balance = parseFloat(
      summary.balance || summary.outstanding || totalFees - totalPaid,
    );
    const pct = totalFees > 0 ? Math.round((totalPaid / totalFees) * 100) : 0;

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
          <small class="text-muted">Paid %</small>
          <div class="progress mt-1" style="height:20px;">
            <div class="progress-bar bg-${pct >= 100 ? "success" : pct >= 50 ? "info" : "danger"}" style="width:${Math.min(pct, 100)}%">${pct}%</div>
          </div>
        </div></div></div>
      </div>
      <h6>Recent Payments</h6>
      <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>Date</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th></tr></thead>
        <tbody>${paymentRows}</tbody>
      </table>`;
  },

  _renderAttendanceTab: function (attendance) {
    const records = attendance?.records || attendance?.data || [];
    const summary = attendance?.summary || {};
    const total = parseInt(summary.total_days || summary.total || 0);
    const present = parseInt(summary.present || 0);
    const absent = parseInt(summary.absent || 0);
    const late = parseInt(summary.late || 0);
    const rate = total > 0 ? Math.round((present / total) * 100) : 0;

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
          <small class="text-muted">Total Days</small><h5 class="mb-0">${total}</h5>
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
      ${late > 0 ? `<div class="alert alert-warning py-1 mb-2"><small><i class="bi bi-clock"></i> Late arrivals: <strong>${late}</strong></small></div>` : ""}
      <h6>Recent Attendance</h6>
      <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
        <tbody>${recentRows}</tbody>
      </table>`;
  },

  _renderPerformanceTab: function (performance) {
    const records =
      performance?.records || performance?.subjects || performance?.data || [];
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
    if (score >= 80) return "EE";
    if (score >= 65) return "ME";
    if (score >= 50) return "AE";
    if (score >= 35) return "BE";
    return "BEL";
  },

  deleteStudent: async function (id) {
    if (!confirm("Are you sure you want to delete this student?")) return;

    try {
      await window.API.apiCall(`/students/${id}`, "DELETE");
      this.showSuccess("Student deleted successfully");
      await this.loadStudents();
    } catch (error) {
      this.showError("Failed to delete student");
    }
  },

  deactivateStudent: async function (id) {
    const reason = prompt("Reason for deactivating this student:", "");
    if (reason === null) return;

    try {
      await window.API.students.update(id, {
        status: "inactive",
        deactivation_reason: reason,
      });
      this.showSuccess("Student deactivated");
      await this.loadStudents();
    } catch (error) {
      this.showError(error.message || "Failed to deactivate student");
    }
  },

  activateStudent: async function (id) {
    if (!confirm("Reactivate this student?")) return;

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
        <div class="modal-dialog">
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
    modal.show();
  },

  bulkImport: async function (event) {
    event.preventDefault();
    const file = document.getElementById("bulkImportFile")?.files[0];
    if (!file) {
      this.showError("Please select a file");
      return;
    }

    const formData = new FormData();
    formData.append("file", file);
    formData.append(
      "update_existing",
      document.getElementById("updateExisting")?.checked ? 1 : 0,
    );

    try {
      const resp = await window.API.apiCall(
        "/students/bulk-create",
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
          "Import completed with errors. Review the details below.",
        );
      } else if (hasWarnings || hasDuplicates) {
        this.showSuccess(
          "Import completed with warnings. Review the details below.",
        );
      } else {
        this.showSuccess("Students imported successfully");
        bootstrap.Modal.getInstance(
          document.getElementById("bulkImportModal"),
        ).hide();
      }

      await this.loadStudents();
    } catch (error) {
      const errorData = error?.response?.data || {};
      this.renderBulkImportResults(errorData, true);
      this.showError(error.message || "Failed to import students");
    }
  },

  exportStudents: async function () {
    try {
      window.open((window.APP_BASE || '') + '/api/?route=students/export&format=csv', "_blank");
    } catch (error) {
      this.showError("Failed to export students");
    }
  },

  downloadTemplate: function () {
    window.open((window.APP_BASE || '') + '/templates/student_import_template.csv', "_blank");
  },

  renderBulkImportResults: function (result, isError = false) {
    const container = document.getElementById("bulkImportResults");
    if (!container) return;

    const payload = this.unwrapPayload(result) || {};
    const errors = payload?.errors || [];
    const warnings = payload?.warnings || [];
    const duplicates = payload?.duplicates || [];
    const processed = payload?.processed ?? payload?.insert?.processed ?? null;

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
              <td>${item.row || "-"}</td>
              <td>${item.admission_no || "-"}</td>
              <td>${item.message || item.error || "-"}</td>
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

  showSuccess: function (message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "success");
    } else {
      alert(message);
    }
  },

  showError: function (message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "error");
    } else {
      alert("Error: " + message);
    }
  },

  showInfo: function (message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "info");
    } else {
      alert(message);
    }
  },
};

// Initialize on DOM ready
document.addEventListener("DOMContentLoaded", () =>
  studentsManagementController.init()
);
