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
      window.location.href = "/Kingsway/index.php";
      return;
    }
    await this.loadInitialData();
    await this.loadStudents();
    this.attachEventListeners();
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
        `/students/student?${params.toString()}`,
        "GET"
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

    tbody.innerHTML = this.data.students
      .map(
        (s, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${s.admission_no}</td>
                <td>${s.first_name} ${s.middle_name || ""} ${s.last_name}</td>
                <td>${s.class_name || "-"} ${
          s.stream_name ? "(" + s.stream_name + ")" : ""
        }</td>
                <td>${
                  s.gender === "M" || s.gender === "male" ? "Male" : "Female"
                }</td>
                <td>${s.phone || s.email || "-"}</td>
                <td><span class="badge bg-${
                  s.status === "active" ? "success" : "secondary"
                }">${s.status}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-info btn-sm" onclick="studentsManagementController.viewStudent(${
                          s.id
                        })" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-warning btn-sm" onclick="studentsManagementController.editStudent(${
                          s.id
                        })" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="studentsManagementController.deleteStudent(${
                          s.id
                        })" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `
      )
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
      total
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
        (s) => s.status === "active"
      ).length;
      const inactive = this.data.students.filter(
        (s) => s.status !== "active"
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
      "/Kingsway/images/default-avatar.png";
  },

  populateForm: function (student) {
    document.getElementById("studentId").value = student.id;
    document.getElementById("firstName").value = student.first_name || "";
    document.getElementById("middleName").value = student.middle_name || "";
    document.getElementById("lastName").value = student.last_name || "";
    document.getElementById("dateOfBirth").value = student.date_of_birth || "";
    document.getElementById("gender").value = student.gender || "";
    document.getElementById("nationalId").value = student.national_id || "";
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
      gender: document.getElementById("gender").value,
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
          "Students must have an initial payment recorded OR be marked as sponsored. Please enter payment details or check 'Is Sponsored'."
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

      // Handle file upload
      const photoFile = document.getElementById("studentProfilePic")?.files[0];

      if (photoFile) {
        // Use FormData for file upload
        const formData = new FormData();
        formData.append("profile_pic", photoFile);
        Object.entries(studentData).forEach(([key, val]) => {
          if (val !== null && val !== undefined) {
            if (typeof val === "object") {
              formData.append(key, JSON.stringify(val));
            } else {
              formData.append(key, val);
            }
          }
        });

        if (id) {
          response = await window.API.apiCall(
            `/students/student/${id}`,
            "PUT",
            formData,
            {},
            { isFile: true }
          );
        } else {
          response = await window.API.apiCall(
            "/students/student",
            "POST",
            formData,
            {},
            { isFile: true }
          );
        }
      } else {
        if (id) {
          response = await window.API.apiCall(
            `/students/student/${id}`,
            "PUT",
            studentData
          );
        } else {
          response = await window.API.apiCall(
            "/students/student",
            "POST",
            studentData
          );
        }
      }

      this.showSuccess(
        id ? "Student updated successfully" : "Student created successfully"
      );
      bootstrap.Modal.getInstance(document.getElementById("studentModal")).hide();
      await this.loadStudents();
    } catch (error) {
      console.error("Save error:", error);
      this.showError(error.message || "Failed to save student");
    }
  },

  editStudent: async function (id) {
    try {
      const resp = await window.API.apiCall(`/students/student/${id}`, "GET");
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
      const resp = await window.API.apiCall(`/students/student/${id}`, "GET");
      const payload = this.unwrapPayload(resp);
      if (payload) {
        const student = payload;
        const content = document.getElementById("viewStudentContent");
        content.innerHTML = `
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <img src="${
                              student.photo_url ||
                              "/Kingsway/images/default-avatar.png"
                            }" 
                                 class="img-thumbnail rounded-circle" width="120" height="120" 
                                 style="object-fit: cover;">
                            <h5 class="mt-2">${student.first_name} ${
          student.last_name
        }</h5>
                            <span class="badge bg-${
                              student.status === "active"
                                ? "success"
                                : "secondary"
                            }">${student.status}</span>
                        </div>
                        <div class="col-md-4">
                            <h6>Personal Information</h6>
                            <p><strong>Admission No:</strong> ${
                              student.admission_no
                            }</p>
                            <p><strong>Gender:</strong> ${student.gender}</p>
                            <p><strong>Date of Birth:</strong> ${
                              student.date_of_birth || "-"
                            }</p>
                            <p><strong>Blood Group:</strong> ${
                              student.blood_group || "-"
                            }</p>
                            <p><strong>KNEC Assessment No:</strong> ${
                              student.assessment_number || "-"
                            }</p>
                            <p><strong>NEMIS Number:</strong> ${
                              student.nemis_number || "-"
                            }</p>
                        </div>
                        <div class="col-md-5">
                            <h6>Academic Information</h6>
                            <p><strong>Class/Stream:</strong> ${
                              student.class_name || "-"
                            } ${
          student.stream_name ? "(" + student.stream_name + ")" : ""
        }</p>
                            <p><strong>Admission Date:</strong> ${
                              student.admission_date || "-"
                            }</p>
                            <p><strong>Boarding:</strong> ${
                              student.boarding_status || "Day"
                            }</p>
                            <h6 class="mt-3">Sponsorship</h6>
                            <p><strong>Sponsored:</strong> ${
                              student.is_sponsored ? "Yes" : "No"
                            }</p>
                            ${
                              student.is_sponsored
                                ? `<p><strong>Sponsor:</strong> ${
                                    student.sponsor_name || "-"
                                  } (${
                                    student.sponsor_waiver_percentage || 0
                                  }% waiver)</p>`
                                : ""
                            }
                        </div>
                    </div>
                `;
        const modal = new bootstrap.Modal(
          document.getElementById("viewStudentModal")
        );
        modal.show();
      }
    } catch (error) {
      this.showError("Failed to load student details");
    }
  },

  deleteStudent: async function (id) {
    if (!confirm("Are you sure you want to delete this student?")) return;

    try {
      await window.API.apiCall(`/students/student/${id}`, "DELETE");
      this.showSuccess("Student deleted successfully");
      await this.loadStudents();
    } catch (error) {
      this.showError("Failed to delete student");
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
      document.getElementById("bulkImportModal")
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
      document.getElementById("updateExisting")?.checked ? 1 : 0
    );

    try {
      const resp = await window.API.apiCall(
        "/students/bulk-create",
        "POST",
        formData,
        {},
        { isFile: true }
      );

      this.renderBulkImportResults(resp);

      const hasErrors = Array.isArray(resp?.errors) && resp.errors.length > 0;
      const hasWarnings =
        Array.isArray(resp?.warnings) && resp.warnings.length > 0;
      const hasDuplicates =
        Array.isArray(resp?.duplicates) && resp.duplicates.length > 0;

      if (hasErrors) {
        this.showError(
          "Import completed with errors. Review the details below."
        );
      } else if (hasWarnings || hasDuplicates) {
        this.showSuccess(
          "Import completed with warnings. Review the details below."
        );
      } else {
        this.showSuccess("Students imported successfully");
        bootstrap.Modal.getInstance(
          document.getElementById("bulkImportModal")
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
      window.open("/Kingsway/api/?route=students/export&format=csv", "_blank");
    } catch (error) {
      this.showError("Failed to export students");
    }
  },

  downloadTemplate: function () {
    window.open("/Kingsway/templates/student_import_template.csv", "_blank");
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
    if (processed !== null) summaryItems.push(`<strong>${processed}</strong> processed`);
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
          `
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
    if (key && response.data && response.data.data && Array.isArray(response.data.data[key]))
      return response.data.data[key];
    if (key && Array.isArray(response[key])) return response[key];
    return [];
  },

  unwrapPayload: function (response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined) return response.data.data;
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
            (p) => p.id == selectedId
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
};

// Initialize on DOM ready
document.addEventListener("DOMContentLoaded", () =>
  studentsManagementController.init()
);
