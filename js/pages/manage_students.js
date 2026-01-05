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
      const classesResp = await window.API.apiCall(
        "/academic/classes-list",
        "GET"
      );
      if (classesResp && Array.isArray(classesResp)) {
        this.data.classes = classesResp;
        this.populateClassDropdowns();
      }

      // Load student types
      const typesResp = await window.API.apiCall(
        "/academic/student-types",
        "GET"
      );
      if (typesResp && typesResp.data) {
        this.data.studentTypes = typesResp.data;
        this.populateStudentTypeDropdown();
      }

      // Load existing parents for dropdown
      await this.loadExistingParents();
    } catch (error) {
      console.error("Error loading initial data:", error);
    }
  },

  loadExistingParents: async function () {
    try {
      const resp = await window.API.apiCall("/parents/list", "GET");
      if (resp && resp.data) {
        this.data.parents = resp.data;
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
      const resp = await window.API.apiCall(
        `/academic/streams?class_id=${classId}`,
        "GET"
      );
      if (resp && resp.data) {
        this.data.streams = resp.data;
        resp.data.forEach((stream) => {
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

      const statusFilter = document.getElementById("statusFilter")?.value;
      if (statusFilter) params.append("status", statusFilter);

      const resp = await window.API.apiCall(
        `/students/student?${params.toString()}`,
        "GET"
      );

      if (resp && resp.data) {
        this.data.students = resp.data.students || resp.data;
        this.data.pagination = resp.data.pagination || this.data.pagination;
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
        first_name: "Existing", // Backend will use parent_id to look up
        phone_1: "existing", // Placeholder to pass validation
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
            true
          );
        } else {
          response = await window.API.apiCall(
            "/students/student",
            "POST",
            formData,
            true
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

      if (response && (response.status === "success" || response.success)) {
        this.showSuccess(
          id ? "Student updated successfully" : "Student created successfully"
        );
        bootstrap.Modal.getInstance(
          document.getElementById("studentModal")
        ).hide();
        await this.loadStudents();
      } else {
        this.showError(response?.message || "Failed to save student");
      }
    } catch (error) {
      console.error("Save error:", error);
      this.showError(error.message || "Failed to save student");
    }
  },

  editStudent: async function (id) {
    try {
      const resp = await window.API.apiCall(`/students/student/${id}`, "GET");
      if (resp && resp.data) {
        this.showStudentModal(resp.data);
      }
    } catch (error) {
      this.showError("Failed to load student details");
    }
  },

  viewStudent: async function (id) {
    try {
      const resp = await window.API.apiCall(`/students/student/${id}`, "GET");
      if (resp && resp.data) {
        const student = resp.data;
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
                            <p><strong>Assessment No:</strong> ${
                              student.assessment_number || "-"
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
      const resp = await window.API.apiCall(
        `/students/student/${id}`,
        "DELETE"
      );
      if (resp && (resp.status === "success" || resp.success)) {
        this.showSuccess("Student deleted successfully");
        await this.loadStudents();
      } else {
        this.showError(resp?.message || "Failed to delete student");
      }
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

  // Bulk import
  showBulkImportModal: function () {
    const modal = new bootstrap.Modal(
      document.getElementById("bulkImportModal")
    );
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
        "/students/bulk/create",
        "POST",
        formData,
        true
      );
      if (resp && resp.status === "success") {
        this.showSuccess("Students imported successfully");
        bootstrap.Modal.getInstance(
          document.getElementById("bulkImportModal")
        ).hide();
        await this.loadStudents();
      } else {
        this.showError(resp?.message || "Import failed");
      }
    } catch (error) {
      this.showError("Failed to import students");
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
