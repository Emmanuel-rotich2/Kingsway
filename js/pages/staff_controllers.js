/**
 * Staff Management Controllers - Full Database Integration
 * Handles all staff CRUD operations for different pages
 */

// ==================== MANAGE STAFF CONTROLLER ====================
const manageStaffController = {
  allStaff: [],
  filteredStaff: [],
  departments: [],
  roles: [],
  currentStaff: null,

  init: async function () {
    try {
      await this.loadDepartments();
      await this.loadRoles();
      await this.loadStaff();
      this.attachEventListeners();
    } catch (error) {
      console.error("Error initializing staff management:", error);
      this.showNotification("Failed to initialize staff management", "error");
    }
  },

  loadStaff: async function () {
    try {
      this.showLoading("staffTableContainer", "Loading staff...");
      const response = await API.staff.index();
      this.allStaff = this.extractStaffList(response);
      this.filteredStaff = [...this.allStaff];
      this.renderStaffTable();
      this.updateStatistics();
    } catch (error) {
      console.error("Error loading staff:", error);
      this.showError("staffTableContainer", "Failed to load staff data");
    }
  },

  extractStaffList: function (response) {
    if (!response) return [];
    if (Array.isArray(response)) return response;
    if (Array.isArray(response.staff)) return response.staff;
    if (Array.isArray(response.data?.staff)) return response.data.staff;
    if (Array.isArray(response.data)) return response.data;
    return [];
  },

  renderStaffTable: function () {
    const container = document.getElementById("staffTableContainer");
    if (!container) return;

    if (this.filteredStaff.length === 0) {
      container.innerHTML =
        '<div class="alert alert-info">No staff members found</div>';
      return;
    }

    const html = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Staff No</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${this.filteredStaff.map((staff, index) => this.renderStaffRow(staff, index + 1)).join("")}
                    </tbody>
                </table>
            </div>
        `;
    container.innerHTML = html;
  },

  renderStaffRow: function (staff, index) {
    const statusBadge =
      staff.status === "active"
        ? '<span class="badge bg-success">Active</span>'
        : '<span class="badge bg-secondary">Inactive</span>';

    const staffType = staff.staff_type || staff.staff_type_name || "N/A";
    const typeBadge =
      staffType === "teaching"
        ? '<span class="badge bg-info">Teaching</span>'
        : '<span class="badge bg-secondary">Non-Teaching</span>';

    // Check if user can edit (not director)
    const userRole = window.currentUserRole || "";
    const canEdit = userRole !== "Director" && userRole !== "director";

    const actionButtons = canEdit
      ? `
        <button class="btn btn-info btn-sm" onclick="manageStaffController.viewStaff(${staff.id})" title="View">
            <i class="bi bi-eye"></i>
        </button>
        <button class="btn btn-warning btn-sm" onclick="manageStaffController.editStaff(${staff.id})" title="Edit">
            <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-danger btn-sm" onclick="manageStaffController.deleteStaff(${staff.id})" title="Delete">
            <i class="bi bi-trash"></i>
        </button>
    `
      : `
        <button class="btn btn-info btn-sm" onclick="manageStaffController.viewStaff(${staff.id})" title="View">
            <i class="bi bi-eye"></i>
        </button>
    `;

    return `
            <tr>
                <td>${index}</td>
                <td>${staff.staff_no || "-"}</td>
                <td>${staff.first_name || ""} ${staff.last_name || ""}</td>
                <td>${typeBadge}</td>
                <td>${staff.department_name || staff.department || "-"}</td>
                <td>${staff.position || "-"}</td>
                <td>${staff.email || "-"}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        ${actionButtons}
                    </div>
                </td>
            </tr>
        `;
  },

  loadDepartments: async function () {
    try {
      const response = await API.staff.getDepartments();
      this.departments = Array.isArray(response)
        ? response
        : Array.isArray(response.data)
          ? response.data
          : [];
      this.populateDepartmentDropdown();
    } catch (error) {
      console.error("Error loading departments:", error);
    }
  },

  populateDepartmentDropdown: function () {
    const selects = ["departmentFilter", "staffDepartment"];
    selects.forEach((selectId) => {
      const select = document.getElementById(selectId);
      if (!select) return;

      const options = this.departments
        .map((dept) => `<option value="${dept.id}">${dept.name}</option>`)
        .join("");

      if (selectId === "departmentFilter") {
        select.innerHTML =
          '<option value="">-- All Departments --</option>' + options;
      } else {
        select.innerHTML =
          '<option value="">-- Select Department --</option>' + options;
      }
    });

    // Also update department count
    const deptCountEl = document.getElementById("totalDepartments");
    if (deptCountEl) deptCountEl.textContent = this.departments.length;
  },

  loadRoles: async function () {
    try {
      // Load roles for staff assignment
      const response = await fetch((window.APP_BASE || '') + '/api/?route=roles&action=list');
      const data = await response.json();
      this.roles = Array.isArray(data.data) ? data.data : [];
    } catch (error) {
      console.error("Error loading roles:", error);
    }
  },

  updateStatistics: function () {
    const total = this.allStaff.length;
    const active = this.allStaff.filter((s) => s.status === "active").length;
    const teaching = this.allStaff.filter(
      (s) => s.staff_type === "teaching" || s.staff_type_name === "teaching",
    ).length;

    this.updateStatCard("totalStaff", total);
    this.updateStatCard("activeStaff", active);
    this.updateStatCard("teachingStaff", teaching);
  },

  updateStatCard: function (id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value;
  },

  viewStaff: async function (staffId) {
    try {
      const response = await API.staff.get(staffId);
      const staff = response.data || response;
      this.showStaffModal(staff, "view");
    } catch (error) {
      console.error("Error viewing staff:", error);
      this.showNotification("Failed to load staff details", "error");
    }
  },

  editStaff: async function (staffId) {
    try {
      const response = await API.staff.get(staffId);
      const staff = response.data || response;
      this.currentStaff = staff;
      this.showStaffModal(staff, "edit");
    } catch (error) {
      console.error("Error loading staff for edit:", error);
      this.showNotification("Failed to load staff details", "error");
    }
  },

  showStaffModal: function (staff, mode = "view") {
    const modal = document.getElementById("staffModal");
    const form = document.getElementById("staffForm");
    const title = document.getElementById("staffModalLabel");

    if (!modal || !form) return;

    title.textContent =
      mode === "view"
        ? "Staff Details"
        : mode === "edit"
          ? "Edit Staff"
          : "Add Staff";

    // Populate form fields
    if (staff) {
      document.getElementById("staffId").value = staff.id || "";
      document.getElementById("firstName").value = staff.first_name || "";
      document.getElementById("lastName").value = staff.last_name || "";
      document.getElementById("email").value = staff.email || "";
      document.getElementById("phone").value = staff.phone_number || "";
      document.getElementById("staffPosition").value = staff.position || "";
      document.getElementById("staffDepartment").value =
        staff.department_id || "";
      document.getElementById("employmentDate").value =
        staff.employment_date || "";
      document.getElementById("staffStatus").value = staff.status || "active";
    } else {
      form.reset();
    }

    // Disable fields in view mode
    const formElements = form.querySelectorAll("input, select, textarea");
    formElements.forEach((el) => (el.disabled = mode === "view"));

    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
  },

  saveStaff: async function (event) {
    event.preventDefault();

    const staffId = document.getElementById("staffId").value;
    const position = document.getElementById("staffPosition").value;
    const payload = {
      first_name: document.getElementById("firstName").value,
      last_name: document.getElementById("lastName").value,
      email: document.getElementById("email").value,
      phone_number: document.getElementById("phone").value,
      position: position,
      department_id: document.getElementById("staffDepartment").value,
      employment_date: document.getElementById("employmentDate").value,
      status: document.getElementById("staffStatus").value,
      // Infer staff type from position
      staff_type_id: this.inferStaffType(position),
    };

    try {
      if (staffId) {
        await API.staff.update(staffId, payload);
        this.showNotification("Staff updated successfully", "success");
      } else {
        // Add default password for new staff
        payload.password = "Kingsway@" + Math.random().toString(36).slice(-8);
        await API.staff.create(payload);
        this.showNotification("Staff created successfully", "success");
      }

      bootstrap.Modal.getInstance(document.getElementById("staffModal")).hide();
      await this.loadStaff();
    } catch (error) {
      console.error("Error saving staff:", error);
      this.showNotification(
        "Failed to save staff: " + (error.message || "Unknown error"),
        "error",
      );
    }
  },

  inferStaffType: function (position) {
    // Teaching positions
    const teachingKeywords = [
      "teacher",
      "head of department",
      "hod",
      "subject",
      "class teacher",
    ];
    const posLower = (position || "").toLowerCase();
    if (teachingKeywords.some((kw) => posLower.includes(kw))) {
      return 1; // Teaching
    }
    return 2; // Non-teaching
  },

  deleteStaff: async function (staffId) {
    if (!confirm("Are you sure you want to delete this staff member?")) return;

    try {
      await API.staff.delete(staffId);
      this.showNotification("Staff deleted successfully", "success");
      await this.loadStaff();
    } catch (error) {
      console.error("Error deleting staff:", error);
      this.showNotification("Failed to delete staff", "error");
    }
  },

  search: function (query) {
    query = query.toLowerCase().trim();
    if (!query) {
      this.filteredStaff = [...this.allStaff];
    } else {
      this.filteredStaff = this.allStaff.filter(
        (staff) =>
          staff.first_name?.toLowerCase().includes(query) ||
          staff.last_name?.toLowerCase().includes(query) ||
          staff.staff_no?.toLowerCase().includes(query) ||
          staff.email?.toLowerCase().includes(query),
      );
    }
    this.renderStaffTable();
  },

  filterByDepartment: function (departmentId) {
    if (!departmentId) {
      this.filteredStaff = [...this.allStaff];
    } else {
      this.filteredStaff = this.allStaff.filter(
        (staff) => staff.department_id == departmentId,
      );
    }
    this.renderStaffTable();
  },

  filterByStatus: function (status) {
    if (!status) {
      this.filteredStaff = [...this.allStaff];
    } else {
      this.filteredStaff = this.allStaff.filter(
        (staff) => staff.status === status,
      );
    }
    this.renderStaffTable();
  },

  attachEventListeners: function () {
    const form = document.getElementById("staffForm");
    if (form) {
      form.addEventListener("submit", (e) => this.saveStaff(e));
    }
  },

  showLoading: function (containerId, message = "Loading...") {
    const container = document.getElementById(containerId);
    if (container) {
      container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">${message}</span>
                    </div>
                    <p class="mt-3">${message}</p>
                </div>
            `;
    }
  },

  showError: function (containerId, message) {
    const container = document.getElementById(containerId);
    if (container) {
      container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
    }
  },

  showNotification: function (message, type = "info") {
    if (typeof showNotification === "function") {
      showNotification(message, type);
    } else {
      alert(message);
    }
  },
};

// ==================== STAFF ATTENDANCE CONTROLLER ====================
const staffAttendanceController = {
  attendance: [],
  departments: [],
  dutyTypes: ["Teaching", "Boarding", "Gate", "Security", "Maintenance"],

  init: async function () {
    try {
      await this.loadDepartments();
      await this.loadAttendance();
      this.attachEventListeners();
    } catch (error) {
      console.error("Error initializing attendance:", error);
    }
  },

  loadDepartments: async function () {
    try {
      const response = await API.staff.getDepartments();
      this.departments = Array.isArray(response)
        ? response
        : Array.isArray(response.data)
          ? response.data
          : [];
      this.populateDepartmentFilter();
    } catch (error) {
      console.error("Error loading departments:", error);
    }
  },

  populateDepartmentFilter: function () {
    const select = document.getElementById("department");
    if (!select) return;

    const options = this.departments
      .map((dept) => `<option value="${dept.id}">${dept.name}</option>`)
      .join("");
    select.innerHTML = '<option value="">All Departments</option>' + options;

    // Populate duty types
    const dutySelect = document.getElementById("dutyType");
    if (dutySelect) {
      dutySelect.innerHTML =
        '<option value="">All Types</option>' +
        this.dutyTypes
          .map((type) => `<option value="${type}">${type}</option>`)
          .join("");
    }
  },

  loadAttendance: async function () {
    try {
      const dateFrom =
        document.getElementById("dateFrom")?.value || this.getToday();
      const dateTo =
        document.getElementById("dateTo")?.value || this.getToday();
      const department = document.getElementById("department")?.value || "";
      const status = document.getElementById("statusFilter")?.value || "";

      const params = { date_from: dateFrom, date_to: dateTo };
      if (department) params.department_id = department;
      if (status) params.status = status;

      const response = await API.staff.getAttendance(params);
      this.attendance = Array.isArray(response)
        ? response
        : Array.isArray(response.data)
          ? response.data
          : [];

      this.renderAttendanceTable();
      this.updateAttendanceStats();
    } catch (error) {
      console.error("Error loading attendance:", error);
      this.showError(
        "attendanceTableContainer",
        "Failed to load attendance data",
      );
    }
  },

  renderAttendanceTable: function () {
    const container = document.getElementById("attendanceTableContainer");
    if (!container) return;

    if (this.attendance.length === 0) {
      container.innerHTML =
        '<div class="alert alert-info">No attendance records found</div>';
      return;
    }

    const html = `
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Staff Name</th>
                            <th>Department</th>
                            <th>Duty Type</th>
                            <th>Status</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${this.attendance.map((record) => this.renderAttendanceRow(record)).join("")}
                    </tbody>
                </table>
            </div>
        `;
    container.innerHTML = html;
  },

  renderAttendanceRow: function (record) {
    const statusBadge = this.getStatusBadge(record.status);
    const hours = this.calculateHours(record.time_in, record.time_out);

    return `
            <tr>
                <td>${this.formatDate(record.date)}</td>
                <td>${record.staff_name || "N/A"}</td>
                <td>${record.department_name || "-"}</td>
                <td>${record.duty_type || "N/A"}</td>
                <td>${statusBadge}</td>
                <td>${record.time_in || "-"}</td>
                <td>${record.time_out || "-"}</td>
                <td>${hours}</td>
            </tr>
        `;
  },

  getStatusBadge: function (status) {
    const badges = {
      present: '<span class="badge bg-success">Present</span>',
      absent: '<span class="badge bg-danger">Absent</span>',
      late: '<span class="badge bg-warning">Late</span>',
      leave: '<span class="badge bg-info">On Leave</span>',
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
  },

  calculateHours: function (timeIn, timeOut) {
    if (!timeIn || !timeOut) return "-";
    try {
      const [inH, inM] = timeIn.split(":").map(Number);
      const [outH, outM] = timeOut.split(":").map(Number);
      const hours = outH - inH + (outM - inM) / 60;
      return hours.toFixed(2) + "h";
    } catch {
      return "-";
    }
  },

  updateAttendanceStats: function () {
    const total = this.attendance.length;
    const present = this.attendance.filter(
      (a) => a.status === "present",
    ).length;
    const absent = this.attendance.filter((a) => a.status === "absent").length;

    const totalEl = document.getElementById("statTotalAttendance");
    const presentEl = document.getElementById("statPresentToday");
    const absentEl = document.getElementById("statAbsentToday");

    if (totalEl) totalEl.textContent = total;
    if (presentEl) presentEl.textContent = present;
    if (absentEl) absentEl.textContent = absent;
  },

  markAttendance: async function (event) {
    event.preventDefault();

    const form = document.getElementById("markAttendanceForm");
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData);

    try {
      await API.staff.markAttendance(payload);
      this.showNotification("Attendance marked successfully", "success");
      bootstrap.Modal.getInstance(
        document.getElementById("markStaffModal"),
      ).hide();
      await this.loadAttendance();
    } catch (error) {
      console.error("Error marking attendance:", error);
      this.showNotification("Failed to mark attendance", "error");
    }
  },

  attachEventListeners: function () {
    const filterBtn = document.getElementById("filterBtn");
    if (filterBtn) {
      filterBtn.addEventListener("click", () => this.loadAttendance());
    }

    const markForm = document.getElementById("markAttendanceForm");
    if (markForm) {
      markForm.addEventListener("submit", (e) => this.markAttendance(e));
    }
  },

  getToday: function () {
    return new Date().toISOString().split("T")[0];
  },

  formatDate: function (date) {
    if (!date) return "-";
    return new Date(date).toLocaleDateString();
  },

  showError: function (containerId, message) {
    const container = document.getElementById(containerId);
    if (container) {
      container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
    }
  },

  showNotification: function (message, type = "info") {
    if (typeof showNotification === "function") {
      showNotification(message, type);
    } else {
      alert(message);
    }
  },
};

// ==================== MANAGE TEACHERS CONTROLLER ====================
const manageTeachersController = {
  teachers: [],

  init: async function () {
    await manageStaffController.init();
    await this.loadTeachers();
  },

  loadTeachers: async function () {
    try {
      const response = await API.staff.index();
      const allStaff = manageStaffController.extractStaffList(response);
      this.teachers = allStaff.filter(
        (s) =>
          s.staff_type === "teaching" ||
          s.staff_type_name === "Teaching" ||
          s.staff_type_id === 1,
      );
      this.renderTeachersTable();
    } catch (error) {
      console.error("Error loading teachers:", error);
      this.showError("teachersTableContainer", "Failed to load teachers");
    }
  },

  renderTeachersTable: function () {
    const container = document.getElementById("teachersTableContainer");
    if (!container) return;

    if (this.teachers.length === 0) {
      container.innerHTML =
        '<div class="alert alert-info">No teachers found</div>';
      return;
    }

    const html = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${this.teachers.map((teacher, idx) => this.renderTeacherRow(teacher, idx + 1)).join("")}
                    </tbody>
                </table>
            </div>
        `;
    container.innerHTML = html;
  },

  renderTeacherRow: function (teacher, index) {
    const statusBadge =
      teacher.status === "active"
        ? '<span class="badge bg-success">Active</span>'
        : '<span class="badge bg-secondary">Inactive</span>';

    return `
            <tr>
                <td>${index}</td>
                <td>${teacher.first_name || ""} ${teacher.last_name || ""}</td>
                <td>${teacher.department_name || "-"}</td>
                <td>${teacher.position || "-"}</td>
                <td>${teacher.email || "-"}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-info" onclick="manageStaffController.viewStaff(${teacher.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-warning" onclick="manageStaffController.editStaff(${teacher.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
  },

  search: function (query) {
    query = query.toLowerCase().trim();
    const filtered = query
      ? this.teachers.filter(
          (t) =>
            t.first_name?.toLowerCase().includes(query) ||
            t.last_name?.toLowerCase().includes(query) ||
            t.email?.toLowerCase().includes(query),
        )
      : this.teachers;

    this.renderFilteredTable(filtered);
  },

  filterByDepartment: function (departmentId) {
    const filtered = departmentId
      ? this.teachers.filter((t) => t.department_id == departmentId)
      : this.teachers;
    this.renderFilteredTable(filtered);
  },

  filterByStatus: function (status) {
    const filtered = status
      ? this.teachers.filter((t) => t.status === status)
      : this.teachers;
    this.renderFilteredTable(filtered);
  },

  renderFilteredTable: function (teachers) {
    const container = document.getElementById("teachersTableContainer");
    if (!container) return;

    const html = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${teachers.map((teacher, idx) => this.renderTeacherRow(teacher, idx + 1)).join("")}
                    </tbody>
                </table>
            </div>
        `;
    container.innerHTML = html;
  },

  showCreateForm: function () {
    manageStaffController.showStaffModal(null, "create");
  },

  showError: function (containerId, message) {
    const container = document.getElementById(containerId);
    if (container) {
      container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
    }
  },
};

// ==================== MANAGE NON-TEACHING STAFF CONTROLLER ====================
const manageNonTeachingStaffController = {
  staff: [],

  init: async function () {
    await manageStaffController.init();
    await this.loadNonTeachingStaff();
  },

  loadNonTeachingStaff: async function () {
    try {
      const response = await API.staff.index();
      const allStaff = manageStaffController.extractStaffList(response);
      this.staff = allStaff.filter(
        (s) =>
          s.staff_type === "non-teaching" ||
          s.staff_type_name === "Non-Teaching" ||
          s.staff_type_id === 2 ||
          s.staff_type_id === 3,
      );
      this.renderStaffTable();
    } catch (error) {
      console.error("Error loading non-teaching staff:", error);
      this.showError("staffTableContainer", "Failed to load staff");
    }
  },

  renderStaffTable: function () {
    const container = document.getElementById("staffTableContainer");
    if (!container) return;

    if (this.staff.length === 0) {
      container.innerHTML =
        '<div class="alert alert-info">No non-teaching staff found</div>';
      return;
    }

    const html = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${this.staff.map((s, idx) => this.renderStaffRow(s, idx + 1)).join("")}
                    </tbody>
                </table>
            </div>
        `;
    container.innerHTML = html;
  },

  renderStaffRow: function (staff, index) {
    const statusBadge =
      staff.status === "active"
        ? '<span class="badge bg-success">Active</span>'
        : '<span class="badge bg-secondary">Inactive</span>';

    return `
            <tr>
                <td>${index}</td>
                <td>${staff.first_name || ""} ${staff.last_name || ""}</td>
                <td>${staff.department_name || "-"}</td>
                <td>${staff.position || "-"}</td>
                <td>${staff.email || "-"}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-info" onclick="manageStaffController.viewStaff(${staff.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-warning" onclick="manageStaffController.editStaff(${staff.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
  },

  search: function (query) {
    query = query.toLowerCase().trim();
    const filtered = query
      ? this.staff.filter(
          (s) =>
            s.first_name?.toLowerCase().includes(query) ||
            s.last_name?.toLowerCase().includes(query) ||
            s.email?.toLowerCase().includes(query),
        )
      : this.staff;

    this.renderFilteredTable(filtered);
  },

  filterByDepartment: function (departmentId) {
    const filtered = departmentId
      ? this.staff.filter((s) => s.department_id == departmentId)
      : this.staff;
    this.renderFilteredTable(filtered);
  },

  filterByStatus: function (status) {
    const filtered = status
      ? this.staff.filter((s) => s.status === status)
      : this.staff;
    this.renderFilteredTable(filtered);
  },

  renderFilteredTable: function (staff) {
    const container = document.getElementById("staffTableContainer");
    if (!container) return;

    const html = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${staff.map((s, idx) => this.renderStaffRow(s, idx + 1)).join("")}
                    </tbody>
                </table>
            </div>
        `;
    container.innerHTML = html;
  },

  showCreateForm: function () {
    manageStaffController.showStaffModal(null, "create");
  },

  showError: function (containerId, message) {
    const container = document.getElementById(containerId);
    if (container) {
      container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
    }
  },
};

// Auto-initialize based on page
document.addEventListener("DOMContentLoaded", function () {
  const currentRoute = new URLSearchParams(window.location.search).get("route");

  if (currentRoute === "manage_staff") {
    manageStaffController.init();
  } else if (currentRoute === "staff_attendance") {
    staffAttendanceController.init();
  } else if (currentRoute === "manage_teachers") {
    manageTeachersController.init();
  } else if (currentRoute === "manage_non_teaching_staff") {
    manageNonTeachingStaffController.init();
  }
});
