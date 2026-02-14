/**
 * Staff Page Controller
 * Initializes DataTable for staff management with create/edit/delete/assignment operations
 */

let staffTable = null;
let staffModal = null;

// StaffManagementController for manage_staff.php - handles staff CRUD with file uploads
const staffManagementController = {
  allStaff: [],
  filteredStaff: [],
  departments: [],
  roles: [],
  currentFilters: {},
  editingStaff: null,
  currentContracts: [],
  currentPayroll: [],
  currentAttendance: [],
  currentLeaveRequests: [],

  extractStaffList: function (response) {
    if (!response) return [];
    if (Array.isArray(response)) return response;
    if (Array.isArray(response.staff)) return response.staff;
    if (Array.isArray(response.data?.staff)) return response.data.staff;
    if (Array.isArray(response.data)) return response.data;
    return [];
  },

  extractStaffRecord: function (response) {
    if (!response) return null;
    if (response.data && !Array.isArray(response.data)) return response.data;
    return response;
  },

  init: async function () {
    try {
      if (window.RoleBasedUI) {
        RoleBasedUI.apply();
      }
      await Promise.all([
        this.loadStaff(),
        this.loadDepartments(),
        this.loadRoles(),
        this.loadSupervisors(),
      ]);
      this.loadStatistics();
      this.loadContracts();
      this.loadPayrollSummary();
      this.loadPayroll();
      this.loadAttendance();
    } catch (error) {
      console.error("Error initializing staff management:", error);
    }
  },

  loadStaff: async function () {
    try {
      const response = await window.API.staff.index();
      this.allStaff = this.extractStaffList(response);
      this.filteredStaff = [...this.allStaff];
      this.renderStaffTables();
      this.populateContractStaffSelect();
    } catch (error) {
      console.error("Error loading staff:", error);
    }
  },

  loadSupervisors: async function () {
    try {
      // Load all staff members as potential supervisors
      const response = await window.API.staff.index();
      const supervisors = this.extractStaffList(response);
      const el = document.getElementById("staffSupervisor");
      if (el) {
        el.innerHTML = '<option value="">-- Select Supervisor --</option>';
        supervisors.forEach((s) => {
          el.innerHTML += `<option value="${s.id}">${s.first_name} ${s.last_name} (${s.staff_no || s.position || "Staff"})</option>`;
        });
      }
    } catch (error) {
      console.warn("Error loading supervisors:", error);
    }
  },

  loadDepartments: async function () {
    try {
      const response = await window.API.staff.getDepartments();
      this.departments = response?.data || response || [];
      this.populateDepartmentDropdowns();
    } catch (error) {
      console.error("Error loading departments:", error);
    }
  },

  loadRoles: async function () {
    try {
      const response = await API.users.getRoles();
      this.roles = response?.data || response || [];
      this.populateRoleDropdowns();
    } catch (error) {
      console.error("Error loading roles:", error);
    }
  },

  populateDepartmentDropdowns: function () {
    const selects = ["staffDepartment", "departmentFilter"];
    selects.forEach((id) => {
      const el = document.getElementById(id);
      if (el) {
        const isFilter = id.includes("Filter");
        el.innerHTML = isFilter
          ? '<option value="">All Departments</option>'
          : '<option value="">Select Department</option>';
        this.departments.forEach((dept) => {
          el.innerHTML += `<option value="${dept.id}">${dept.name}</option>`;
        });
      }
    });
  },

  populateRoleDropdowns: function () {
    const el = document.getElementById("staffRole");
    if (el) {
      el.innerHTML = '<option value="">Select Role</option>';
      this.roles.forEach((role) => {
        el.innerHTML += `<option value="${role.role_id || role.id}">${role.role_name || role.name}</option>`;
      });
    }
    const filterEl = document.getElementById("roleFilter");
    if (filterEl) {
      filterEl.innerHTML = '<option value="">All Roles</option>';
      this.roles.forEach((role) => {
        filterEl.innerHTML += `<option value="${role.role_id || role.id}">${role.role_name || role.name}</option>`;
      });
    }
  },

  populateContractStaffSelect: function () {
    const select = document.getElementById("contractStaffId");
    if (!select) return;

    select.innerHTML = '<option value="">Select Staff</option>';
    this.allStaff.forEach((staff) => {
      const option = document.createElement("option");
      option.value = staff.id;
      option.textContent = `${staff.first_name || ""} ${staff.last_name || ""} (${staff.staff_no || "-"})`;
      select.appendChild(option);
    });
  },

  loadStatistics: function () {
    const total = this.allStaff.length;
    const teaching = this.allStaff.filter(
      (s) => s.staff_type === "teaching",
    ).length;
    const nonTeaching = this.allStaff.filter(
      (s) => s.staff_type === "non-teaching",
    ).length;
    const onLeave = this.allStaff.filter((s) => s.status === "on_leave").length;

    document.getElementById("totalStaffCount").textContent = total;
    document.getElementById("teachingStaffCount").textContent = teaching;
    document.getElementById("nonTeachingCount").textContent = nonTeaching;
    document.getElementById("onLeaveCount").textContent = onLeave;
  },

  renderStaffTables: function () {
    this.renderAllStaffTable();
    this.renderCategoryTable(
      "teachingStaffContainer",
      this.filteredStaff.filter((s) => s.staff_type === "teaching"),
    );
    this.renderCategoryTable(
      "nonTeachingStaffContainer",
      this.filteredStaff.filter((s) => s.staff_type === "non-teaching"),
    );
  },

  renderAllStaffTable: function () {
    const tbody = document.getElementById("staffTableBody");
    if (!tbody) return;

    if (!this.filteredStaff.length) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center text-muted py-4">No staff found</td></tr>';
      return;
    }

    tbody.innerHTML = this.filteredStaff
      .map((staff, index) => {
        const statusBadge = this.getStatusBadge(staff.status);
        const typeBadge = this.getTypeBadge(staff.staff_type);
        const roleLabel = staff.role_name || staff.position || "-";
        const email = staff.email || "-";

        return `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${staff.staff_no || "-"}</td>
                        <td>${staff.first_name || ""} ${staff.last_name || ""}</td>
                        <td>${typeBadge}</td>
                        <td>${staff.department_name || "-"}</td>
                        <td>${roleLabel}</td>
                        <td>${email}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-info" onclick="staffManagementController.viewStaff(${staff.id})" title="View">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-warning" onclick="staffManagementController.editStaff(${staff.id})" title="Edit" data-permission="staff_edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                ${staff.status === 'active' ? `
                                <button class="btn btn-secondary" onclick="staffManagementController.deactivateStaff(${staff.id})" title="Deactivate" data-permission="staff_edit">
                                    <i class="bi bi-person-x"></i>
                                </button>` : `
                                <button class="btn btn-success" onclick="staffManagementController.activateStaff(${staff.id})" title="Activate" data-permission="staff_edit">
                                    <i class="bi bi-person-check"></i>
                                </button>`}
                                <button class="btn btn-danger" onclick="staffManagementController.deleteStaff(${staff.id})" title="Delete" data-permission="staff_delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
      })
      .join("");

    const fromEl = document.getElementById("staffShowingFrom");
    const toEl = document.getElementById("staffShowingTo");
    const totalEl = document.getElementById("staffTotalRecords");
    if (fromEl && toEl && totalEl) {
      fromEl.textContent = this.filteredStaff.length ? "1" : "0";
      toEl.textContent = String(this.filteredStaff.length);
      totalEl.textContent = String(this.filteredStaff.length);
    }

    if (window.RoleBasedUI) {
      RoleBasedUI.applyTo(tbody.closest(".table-responsive") || tbody);
    }
  },

  renderCategoryTable: function (containerId, staffList) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!staffList.length) {
      container.innerHTML = '<div class="text-muted">No staff found.</div>';
      return;
    }

    const rows = staffList
      .map(
        (staff) => `
                <tr>
                    <td>${staff.staff_no || "-"}</td>
                    <td>${staff.first_name || ""} ${staff.last_name || ""}</td>
                    <td>${staff.department_name || "-"}</td>
                    <td>${this.getStatusBadge(staff.status)}</td>
                </tr>
            `,
      )
      .join("");

    container.innerHTML = `
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Staff No.</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
  },

  getStatusBadge: function (status) {
    const badges = {
      active: '<span class="badge bg-success">Active</span>',
      inactive: '<span class="badge bg-secondary">Inactive</span>',
      on_leave: '<span class="badge bg-warning">On Leave</span>',
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
  },

  getTypeBadge: function (type) {
    const badges = {
      teaching: '<span class="badge bg-primary">Teaching</span>',
      "non-teaching": '<span class="badge bg-info">Non-Teaching</span>',
      admin: '<span class="badge bg-dark">Admin</span>',
    };
    return badges[type] || '<span class="badge bg-secondary">Unknown</span>';
  },

  showStaffModal: function (staff = null) {
    const form = document.getElementById("staffForm");
    form.reset();
    document.getElementById("staffId").value = staff?.id || "";
    document.getElementById("staffModalLabel").textContent = staff
      ? "Edit Staff Member"
      : "Add Staff Member";
    this.editingStaff = staff || null;

    if (staff) {
      // Personal Information
      document.getElementById("staffFirstName").value = staff.first_name || "";
      document.getElementById("staffMiddleName").value =
        staff.middle_name || "";
      document.getElementById("staffLastName").value = staff.last_name || "";
      document.getElementById("staffGender").value = staff.gender || "";
      document.getElementById("staffDOB").value = staff.date_of_birth || "";
      document.getElementById("staffNationalId").value =
        staff.national_id || "";
      document.getElementById("staffMaritalStatus").value =
        staff.marital_status || "";

      // Employment Information
      document.getElementById("staffNumber").value =
        staff.staff_no || staff.employee_number || "";
      document.getElementById("staffType").value = staff.staff_type || "";
      document.getElementById("staffDepartment").value =
        staff.department_id || "";
      document.getElementById("staffRole").value = staff.role_id || "";
      document.getElementById("staffPosition").value = staff.position || "";
      document.getElementById("employmentDate").value =
        staff.employment_date || "";
      document.getElementById("staffContractType").value =
        staff.contract_type || "";
      document.getElementById("staffSupervisor").value =
        staff.supervisor_id || "";
      document.getElementById("staffTscNo").value = staff.tsc_no || "";
      document.getElementById("staffStatus").value = staff.status || "active";
      document.getElementById("staffPassword").value = ""; // Never pre-fill password

      // Statutory Information
      document.getElementById("staffNssfNo").value = staff.nssf_no || "";
      document.getElementById("staffKraPin").value = staff.kra_pin || "";
      document.getElementById("staffNhifNo").value = staff.nhif_no || "";

      // Financial Information
      document.getElementById("staffBankAccount").value =
        staff.bank_account || "";
      document.getElementById("staffSalary").value = staff.salary || "";

      // Contact Information
      document.getElementById("staffEmail").value = staff.email || "";
      document.getElementById("staffPhone").value = staff.phone || "";
      document.getElementById("staffAddress").value = staff.address || "";
    }

    const modal = new bootstrap.Modal(document.getElementById("staffModal"));
    modal.show();
  },

  saveStaff: async function (event) {
    event.preventDefault();

    try {
      const staffId = document.getElementById("staffId").value;
      const profilePicFile =
        document.getElementById("staffProfilePic")?.files[0];
      if (profilePicFile) {
        showNotification(
          "Profile picture upload is handled separately.",
          "info",
        );
      }

      const roleId = document.getElementById("staffRole").value;
      const payload = {
        first_name: document.getElementById("staffFirstName").value,
        middle_name: document.getElementById("staffMiddleName").value || null,
        last_name: document.getElementById("staffLastName").value,
        gender: document.getElementById("staffGender").value,
        date_of_birth: document.getElementById("staffDOB").value,
        marital_status:
          document.getElementById("staffMaritalStatus").value || null,
        staff_type: document.getElementById("staffType").value,
        department_id: document.getElementById("staffDepartment").value,
        role_id: roleId || null,
        position: document.getElementById("staffPosition").value,
        employment_date: document.getElementById("employmentDate").value,
        contract_type:
          document.getElementById("staffContractType").value || null,
        supervisor_id: document.getElementById("staffSupervisor").value || null,
        tsc_no: document.getElementById("staffTscNo").value || null,
        status: document.getElementById("staffStatus").value,
        nssf_no: document.getElementById("staffNssfNo").value,
        kra_pin: document.getElementById("staffKraPin").value,
        nhif_no: document.getElementById("staffNhifNo").value,
        bank_account: document.getElementById("staffBankAccount").value,
        salary: document.getElementById("staffSalary").value,
        email: document.getElementById("staffEmail").value,
        address: document.getElementById("staffAddress").value || null,
      };

      const password = document.getElementById("staffPassword").value;
      if (password) {
        payload.password = password;
      }

      if (staffId) {
        payload.staff_no = document.getElementById("staffNumber").value || null;
        await window.API.staff.update(staffId, payload);
      } else {
        await window.API.staff.create(payload);
      }

      showNotification(
        staffId ? "Staff updated successfully" : "Staff created successfully",
        "success",
      );
      bootstrap.Modal.getInstance(document.getElementById("staffModal")).hide();
      await this.loadStaff();
      this.loadStatistics();
    } catch (error) {
      console.error("Error saving staff:", error);
      showNotification(
        "Failed to save staff: " + (error.message || "Unknown error"),
        "error",
      );
    }
  },

  viewStaff: async function (staffId) {
    try {
      const resp = await window.API.staff.get(staffId);
      const staff = this.extractStaffRecord(resp);
      const photo =
        staff.profile_pic_url || "/Kingsway/images/default-avatar.png";

      const html = `
                <div class="row">
                    <div class="col-md-4 text-center">
                        <img src="${photo}" class="img-fluid rounded mb-3" style="max-width: 150px" onerror="this.src='/Kingsway/images/default-avatar.png'">
                        <h5>${staff.first_name || ""} ${staff.last_name || ""}</h5>
                        <p class="text-muted">${staff.staff_no || ""}</p>
                    </div>
                    <div class="col-md-8">
                        <h6>Personal Information</h6>
                        <p><strong>Email:</strong> ${staff.email || "-"}</p>
                        <p><strong>Gender:</strong> ${staff.gender || "-"}</p>
                        <p><strong>Date of Birth:</strong> ${staff.date_of_birth || "-"}</p>
                        <hr>
                        <h6>Employment Information</h6>
                        <p><strong>Department:</strong> ${staff.department_name || "-"}</p>
                        <p><strong>Type:</strong> ${staff.staff_type || "-"}</p>
                        <p><strong>Role:</strong> ${staff.role_name || "-"}</p>
                        <p><strong>Status:</strong> ${this.getStatusBadge(staff.status)}</p>
                        <p><strong>Employment Date:</strong> ${staff.employment_date || "-"}</p>
                    </div>
                </div>
            `;

      document.getElementById("viewStaffContent").innerHTML = html;
      new bootstrap.Modal(document.getElementById("viewStaffModal")).show();
    } catch (error) {
      console.error("Error loading staff:", error);
      showNotification("Failed to load staff details", "error");
    }
  },

  editStaff: async function (staffId) {
    try {
      const resp = await window.API.staff.get(staffId);
      const staff = this.extractStaffRecord(resp);
      this.showStaffModal(staff);
    } catch (error) {
      console.error("Error loading staff for edit:", error);
      showNotification("Failed to load staff details", "error");
    }
  },

  deleteStaff: async function (staffId) {
    if (!confirm("Are you sure you want to delete this staff member?")) return;

    try {
      await window.API.staff.delete(staffId);
      showNotification("Staff deleted successfully", "success");
      await this.loadStaff();
      this.loadStatistics();
    } catch (error) {
      console.error("Error deleting staff:", error);
      showNotification("Failed to delete staff", "error");
    }
  },

  searchStaff: function (query) {
    const q = query.toLowerCase();
    this.filteredStaff = this.allStaff.filter(
      (s) =>
        (s.first_name || "").toLowerCase().includes(q) ||
        (s.last_name || "").toLowerCase().includes(q) ||
        (s.email || "").toLowerCase().includes(q) ||
        (s.staff_no || "").toLowerCase().includes(q),
    );
    this.renderStaffTables();
  },

  filterByDepartment: function (deptId) {
    this.currentFilters.department_id = deptId;
    this.applyFilters();
  },

  filterByType: function (type) {
    this.currentFilters.staff_type = type;
    this.applyFilters();
  },

  filterByStatus: function (status) {
    this.currentFilters.status = status;
    this.applyFilters();
  },

  filterByRole: function (roleId) {
    this.currentFilters.role_id = roleId;
    this.applyFilters();
  },

  applyFilters: function () {
    this.filteredStaff = this.allStaff.filter((s) => {
      if (
        this.currentFilters.department_id &&
        s.department_id != this.currentFilters.department_id
      )
        return false;
      if (
        this.currentFilters.staff_type &&
        s.staff_type !== this.currentFilters.staff_type
      )
        return false;
      if (this.currentFilters.status && s.status !== this.currentFilters.status)
        return false;
      if (
        this.currentFilters.role_id &&
        s.role_id != this.currentFilters.role_id
      )
        return false;
      return true;
    });
    this.renderStaffTables();
  },

  showBulkImportModal: function () {
    new bootstrap.Modal(document.getElementById("bulkImportModal")).show();
  },

  bulkImport: async function (event) {
    event.preventDefault();

    const fileInput = document.getElementById("bulkImportStaffFile");
    if (!fileInput.files[0]) {
      showNotification("Please select a file", "warning");
      return;
    }

    try {
      const formData = new FormData();
      formData.append("file", fileInput.files[0]);

      showNotification("Bulk import is not available yet.", "warning");
      bootstrap.Modal.getInstance(
        document.getElementById("bulkImportModal"),
      ).hide();
    } catch (error) {
      console.error("Error importing staff:", error);
      showNotification(
        "Failed to import staff: " + (error.message || "Unknown error"),
        "error",
      );
    }
  },

  exportStaff: async function () {
    try {
      this.exportCsv(this.filteredStaff, "staff_export.csv");
    } catch (error) {
      console.error("Error exporting staff:", error);
      showNotification("Failed to export staff", "error");
    }
  },

  showLeaveRequests: function () {
    this.loadLeaveRequests();
    new bootstrap.Modal(document.getElementById("leaveRequestsModal")).show();
  },

  loadLeaveRequests: async function () {
    try {
      const response = await window.API.staff.listLeaves({});
      const leaves = response?.data || response || [];
      this.currentLeaveRequests = Array.isArray(leaves)
        ? leaves
        : leaves.leaves || [];
      this.renderLeaveRequests();
    } catch (error) {
      console.error("Error loading leave requests:", error);
      this.showError("Failed to load leave requests");
    }
  },

  renderLeaveRequests: function () {
    const tbody = document.getElementById("leaveRequestsTableBody");
    if (!tbody) return;

    if (!this.currentLeaveRequests.length) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-3">No leave requests found</td></tr>';
      return;
    }

    tbody.innerHTML = this.currentLeaveRequests
      .map((leave) => {
        return `
                <tr>
                    <td>${leave.staff_no || "-"}</td>
                    <td>${leave.first_name || ""} ${leave.last_name || ""}</td>
                    <td>${leave.type || leave.leave_type || "-"}</td>
                    <td>${leave.start_date || "-"}</td>
                    <td>${leave.end_date || "-"}</td>
                    <td><span class="badge bg-${leave.status === "approved" ? "success" : leave.status === "rejected" ? "danger" : "warning"}">${leave.status || "pending"}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-success" onclick="staffManagementController.updateLeaveStatus(${leave.id}, 'approved')">Approve</button>
                            <button class="btn btn-outline-danger" onclick="staffManagementController.updateLeaveStatus(${leave.id}, 'rejected')">Reject</button>
                        </div>
                    </td>
                </tr>
            `;
      })
      .join("");
  },

  updateLeaveStatus: async function (leaveId, status) {
    try {
      await window.API.staff.updateLeaveStatus(leaveId, status);
      this.loadLeaveRequests();
      this.loadStaff();
      this.loadStatistics();
    } catch (error) {
      console.error("Error updating leave status:", error);
      this.showError("Failed to update leave status");
    }
  },

  loadAttendance: async function () {
    const date = document.getElementById("attendanceDate")?.value;
    const params = date ? { start_date: date, end_date: date } : {};

    try {
      const response = await window.API.staff.getAttendance(null, params);
      const attendance = response?.data || response || [];
      this.currentAttendance = Array.isArray(attendance)
        ? attendance
        : attendance.attendance || [];
      this.renderAttendanceTable();
    } catch (error) {
      console.error("Error loading attendance:", error);
    }
  },

  renderAttendanceTable: function () {
    const tbody = document.getElementById("staffAttendanceTableBody");
    if (!tbody) return;

    if (!this.currentAttendance.length) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-3">No attendance data</td></tr>';
      return;
    }

    let present = 0;
    let absent = 0;
    let late = 0;

    tbody.innerHTML = this.currentAttendance
      .map((row) => {
        if (row.status === "present") present += 1;
        if (row.status === "absent") absent += 1;
        if (row.status === "late") late += 1;

        return `
                <tr>
                    <td>${row.staff_no || "-"}</td>
                    <td>${row.first_name || ""} ${row.last_name || ""}</td>
                    <td>${row.department_name || "-"}</td>
                    <td>${row.check_in || row.check_in_time || "-"}</td>
                    <td>${row.check_out || row.check_out_time || "-"}</td>
                    <td>${this.getAttendanceBadge(row.status)}</td>
                    <td>${row.remarks || row.notes || "-"}</td>
                </tr>
            `;
      })
      .join("");

    const presentEl = document.getElementById("presentToday");
    const absentEl = document.getElementById("absentToday");
    const lateEl = document.getElementById("lateArrivals");
    if (presentEl) presentEl.textContent = present;
    if (absentEl) absentEl.textContent = absent;
    if (lateEl) lateEl.textContent = late;
  },

  markAttendance: function () {
    window.location.href = "/Kingsway/home.php?route=staff_attendance";
  },

  showAttendanceReport: function () {
    window.location.href = "/Kingsway/home.php?route=staff_attendance";
  },

  loadPayrollSummary: async function () {
    try {
      const response = await window.API.staff.getPayrollSummary();
      const summary = response?.data?.summary || response?.summary || null;
      if (!summary) return;

      document.getElementById("grossPayroll").textContent = this.formatCurrency(
        summary.gross_payroll || 0,
      );
      document.getElementById("totalDeductions").textContent =
        this.formatCurrency(summary.total_deductions || 0);
      document.getElementById("netPayroll").textContent = this.formatCurrency(
        summary.net_payroll || 0,
      );
      const pending = document.getElementById("pendingPayrollApproval");
      if (pending) pending.textContent = summary.pending_approval || 0;
    } catch (error) {
      console.error("Error loading payroll summary:", error);
    }
  },

  loadPayroll: async function () {
    try {
      const response = await window.API.staff.listPayroll();
      const payroll = response?.data?.payroll || response?.payroll || [];
      this.currentPayroll = Array.isArray(payroll) ? payroll : [];
      this.renderPayrollTable();
    } catch (error) {
      console.error("Error loading payroll:", error);
    }
  },

  renderPayrollTable: function () {
    const tbody = document.getElementById("payrollTableBody");
    if (!tbody) return;

    if (!this.currentPayroll.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center text-muted py-3">No payroll data available</td></tr>';
      return;
    }

    tbody.innerHTML = this.currentPayroll
      .map((row) => {
        return `
                <tr>
                    <td>${row.staff_no || "-"}</td>
                    <td>${row.first_name || ""} ${row.last_name || ""}</td>
                    <td>${this.formatCurrency(row.basic_salary || 0)}</td>
                    <td>${this.formatCurrency(row.allowances || 0)}</td>
                    <td>${this.formatCurrency(row.total_deductions || row.deductions || 0)}</td>
                    <td>${this.formatCurrency(row.net_salary || 0)}</td>
                    <td>${row.status || "-"}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-info" onclick="staffManagementController.viewPayslip(${row.staff_id || row.id}, ${row.month || new Date().getMonth() + 1}, ${row.year || new Date().getFullYear()})" title="View Payslip">
                                <i class="bi bi-receipt"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="staffManagementController.generatePayslip(${row.staff_id || row.id})" title="Generate Payslip" data-role="hr_manager,bursar,admin">
                                <i class="bi bi-file-earmark-plus"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
      })
      .join("");
  },

  runPayroll: function () {
    window.location.href = "/Kingsway/home.php?route=manage_payrolls";
  },

  exportPayroll: function () {
    this.exportCsv(this.currentPayroll, "staff_payroll.csv");
  },

  showPayslips: function () {
    window.location.href = "/Kingsway/home.php?route=payroll";
  },

  approvePayroll: function () {
    showNotification(
      "Payroll approvals are managed in the payroll module.",
      "info",
    );
  },

  loadContracts: async function () {
    try {
      const response = await window.API.staff.listContracts();
      const contracts = response?.data?.contracts || response?.contracts || [];
      this.currentContracts = Array.isArray(contracts) ? contracts : [];
      this.renderContractsTable();
    } catch (error) {
      console.error("Error loading contracts:", error);
    }
  },

  renderContractsTable: function () {
    const tbody = document.getElementById("contractsTableBody");
    if (!tbody) return;

    if (!this.currentContracts.length) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-3">No contracts found</td></tr>';
      return;
    }

    tbody.innerHTML = this.currentContracts
      .map((contract) => {
        return `
                <tr>
                    <td>${contract.staff_no || "-"}</td>
                    <td>${contract.first_name || ""} ${contract.last_name || ""}</td>
                    <td>${contract.contract_type || "-"}</td>
                    <td>${contract.start_date || "-"}</td>
                    <td>${contract.end_date || "N/A"}</td>
                    <td>${contract.status || "-"}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="staffManagementController.openContractEditor(${contract.id})">Edit</button>
                    </td>
                </tr>
            `;
      })
      .join("");
  },

  showContractModal: function () {
    this.openContractEditor(null);
  },

  openContractEditor: function (contractId) {
    const modalEl = document.getElementById("contractModal");
    if (!modalEl) return;

    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById("contractForm");
    form.reset();
    document.getElementById("contractId").value = contractId || "";

    if (contractId) {
      const contract = this.currentContracts.find(
        (row) => row.id === contractId,
      );
      if (contract) {
        document.getElementById("contractStaffId").value =
          contract.staff_id || "";
        document.getElementById("contractType").value =
          contract.contract_type || "";
        document.getElementById("contractStartDate").value =
          contract.start_date || "";
        document.getElementById("contractEndDate").value =
          contract.end_date || "";
        document.getElementById("contractSalary").value = contract.salary || "";
        document.getElementById("contractAllowances").value =
          contract.allowances || 0;
        document.getElementById("contractTerms").value = contract.terms || "";
        document.getElementById("contractStatus").value =
          contract.status || "active";
      }
    }

    modal.show();
  },

  saveContract: async function (event) {
    event.preventDefault();
    try {
      const contractId = document.getElementById("contractId").value;
      const payload = {
        staff_id: document.getElementById("contractStaffId").value,
        contract_type: document.getElementById("contractType").value,
        start_date: document.getElementById("contractStartDate").value,
        end_date: document.getElementById("contractEndDate").value || null,
        salary: document.getElementById("contractSalary").value,
        allowances: document.getElementById("contractAllowances").value || 0,
        terms: document.getElementById("contractTerms").value || null,
        status: document.getElementById("contractStatus").value || "active",
        created_by: this.getCurrentUserId(),
      };

      if (contractId) {
        await window.API.staff.updateContract(contractId, payload);
      } else {
        await window.API.staff.createContract(payload);
      }

      bootstrap.Modal.getInstance(
        document.getElementById("contractModal"),
      ).hide();
      this.loadContracts();
      showNotification("Contract saved successfully", "success");
    } catch (error) {
      console.error("Error saving contract:", error);
      this.showError("Failed to save contract");
    }
  },

  showRenewalQueue: function () {
    const cutoff = new Date();
    cutoff.setDate(cutoff.getDate() + 30);
    const cutoffStr = cutoff.toISOString().split("T")[0];
    this.currentContracts = this.currentContracts.filter((contract) => {
      if (!contract.end_date) return false;
      return contract.end_date <= cutoffStr && contract.status === "active";
    });
    this.renderContractsTable();
  },

  exportContracts: function () {
    this.exportCsv(this.currentContracts, "staff_contracts.csv");
  },

  getCurrentUserId: function () {
    const user = AuthContext.getUser();
    return user?.user_id || user?.id || user?.userId || null;
  },

  getAttendanceBadge: function (status) {
    const map = {
      present: '<span class="badge bg-success">Present</span>',
      absent: '<span class="badge bg-danger">Absent</span>',
      late: '<span class="badge bg-warning">Late</span>',
    };
    return map[status] || '<span class="badge bg-secondary">Unknown</span>';
  },

  formatCurrency: function (amount) {
    return (
      "KES " +
      Number(amount || 0).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  },

  exportCsv: function (rows, filename) {
    if (!Array.isArray(rows) || !rows.length) {
      this.showError("No data to export");
      return;
    }

    const headers = Object.keys(rows[0]);
    const escape = (value) => `"${String(value ?? "").replace(/"/g, '""')}"`;
    const csv = [headers.join(",")]
      .concat(rows.map((row) => headers.map((h) => escape(row[h])).join(",")))
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  },

  showError: function (message) {
    showNotification(message, "error");
  },

  // ==================== ASSIGNMENTS TAB ====================

  assignmentsLoaded: false,
  currentAssignments: [],

  loadAssignmentsTab: async function () {
    if (this.assignmentsLoaded) return;
    try {
      // Populate staff filter dropdown
      const filterEl = document.getElementById("assignmentStaffFilter");
      if (filterEl && filterEl.options.length <= 1) {
        this.allStaff.forEach((s) => {
          if (s.staff_type === "teaching") {
            filterEl.innerHTML += `<option value="${s.id}">${s.first_name} ${s.last_name}</option>`;
          }
        });
      }
      await this.loadAllAssignments();
      this.assignmentsLoaded = true;
    } catch (error) {
      console.error("Error loading assignments tab:", error);
    }
  },

  loadAllAssignments: async function () {
    try {
      const response = await window.API.staff.getAssignments();
      const data = response?.data || response || [];
      this.currentAssignments = Array.isArray(data) ? data : data.assignments || [];
      this.renderAssignmentsTable(this.currentAssignments);
    } catch (error) {
      console.error("Error loading assignments:", error);
      this.currentAssignments = [];
      this.renderAssignmentsTable([]);
    }
  },

  filterAssignments: async function (staffId) {
    if (!staffId) {
      this.renderAssignmentsTable(this.currentAssignments);
      return;
    }
    try {
      const response = await window.API.staff.getCurrentAssignments(staffId);
      const data = response?.data || response || [];
      this.renderAssignmentsTable(Array.isArray(data) ? data : data.assignments || []);
    } catch (error) {
      console.error("Error filtering assignments:", error);
    }
  },

  renderAssignmentsTable: function (assignments) {
    const tbody = document.getElementById("assignmentsTableBody");
    if (!tbody) return;

    if (!assignments.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No assignments found</td></tr>';
      return;
    }

    tbody.innerHTML = assignments
      .map((a) => `
        <tr>
          <td>${a.staff_no || "-"}</td>
          <td>${a.first_name || a.staff_name || ""} ${a.last_name || ""}</td>
          <td>${a.class_name || "-"}</td>
          <td>${a.stream_name || "-"}</td>
          <td>${a.subject_name || a.learning_area || "-"}</td>
          <td><span class="badge bg-${a.role === "class_teacher" ? "primary" : "secondary"}">${a.role || a.assignment_type || "-"}</span></td>
          <td>${a.workload_hours || a.hours_per_week || "-"}</td>
          <td>
            <button class="btn btn-sm btn-outline-danger" onclick="staffManagementController.removeAssignment(${a.id})" data-role="hr_manager,headteacher,admin">
              <i class="bi bi-x-circle"></i>
            </button>
          </td>
        </tr>
      `)
      .join("");
  },

  showAssignClassModal: async function () {
    const staffSelect = document.getElementById("assignClassStaffId");
    const classSelect = document.getElementById("assignClassId");
    const streamSelect = document.getElementById("assignStreamId");

    // Populate staff (teaching only)
    staffSelect.innerHTML = '<option value="">Select Staff</option>';
    this.allStaff
      .filter((s) => s.staff_type === "teaching" && s.status === "active")
      .forEach((s) => {
        staffSelect.innerHTML += `<option value="${s.id}">${s.first_name} ${s.last_name}</option>`;
      });

    // Populate classes
    try {
      const classResp = await window.API.academic.listClasses();
      const classes = classResp?.data || classResp || [];
      classSelect.innerHTML = '<option value="">Select Class</option>';
      (Array.isArray(classes) ? classes : []).forEach((c) => {
        classSelect.innerHTML += `<option value="${c.id}">${c.name || c.class_name}</option>`;
      });
    } catch (e) {
      console.error("Error loading classes:", e);
    }

    // Load streams when class changes
    classSelect.onchange = async function () {
      const classId = this.value;
      streamSelect.innerHTML = '<option value="">All Streams</option>';
      if (classId) {
        try {
          const streamResp = await window.API.academic.listStreams({ class_id: classId });
          const streams = streamResp?.data || streamResp || [];
          (Array.isArray(streams) ? streams : []).forEach((s) => {
            streamSelect.innerHTML += `<option value="${s.id}">${s.name || s.stream_name}</option>`;
          });
        } catch (e) {
          console.warn("Error loading streams:", e);
        }
      }
    };

    new bootstrap.Modal(document.getElementById("assignClassModal")).show();
  },

  saveClassAssignment: async function (event) {
    event.preventDefault();
    try {
      const data = {
        staff_id: document.getElementById("assignClassStaffId").value,
        class_id: document.getElementById("assignClassId").value,
        stream_id: document.getElementById("assignStreamId").value || null,
        role: document.getElementById("assignClassRole").value,
      };

      await window.API.staff.assignClass(data);
      showNotification("Staff assigned to class successfully", "success");
      bootstrap.Modal.getInstance(document.getElementById("assignClassModal")).hide();
      this.assignmentsLoaded = false;
      this.loadAssignmentsTab();
    } catch (error) {
      console.error("Error assigning class:", error);
      showNotification("Failed to assign class: " + (error.message || "Unknown error"), "error");
    }
  },

  showAssignSubjectModal: async function () {
    const staffSelect = document.getElementById("assignSubjectStaffId");
    const subjectSelect = document.getElementById("assignSubjectId");
    const classSelect = document.getElementById("assignSubjectClassId");

    // Populate staff
    staffSelect.innerHTML = '<option value="">Select Staff</option>';
    this.allStaff
      .filter((s) => s.staff_type === "teaching" && s.status === "active")
      .forEach((s) => {
        staffSelect.innerHTML += `<option value="${s.id}">${s.first_name} ${s.last_name}</option>`;
      });

    // Populate subjects
    try {
      const subjResp = await window.API.academic.listLearningAreas();
      const subjects = subjResp?.data || subjResp || [];
      subjectSelect.innerHTML = '<option value="">Select Subject</option>';
      (Array.isArray(subjects) ? subjects : []).forEach((s) => {
        subjectSelect.innerHTML += `<option value="${s.id}">${s.name || s.subject_name}</option>`;
      });
    } catch (e) {
      console.error("Error loading subjects:", e);
    }

    // Populate classes
    try {
      const classResp = await window.API.academic.listClasses();
      const classes = classResp?.data || classResp || [];
      classSelect.innerHTML = '<option value="">All Classes</option>';
      (Array.isArray(classes) ? classes : []).forEach((c) => {
        classSelect.innerHTML += `<option value="${c.id}">${c.name || c.class_name}</option>`;
      });
    } catch (e) {
      console.error("Error loading classes:", e);
    }

    new bootstrap.Modal(document.getElementById("assignSubjectModal")).show();
  },

  saveSubjectAssignment: async function (event) {
    event.preventDefault();
    try {
      const data = {
        staff_id: document.getElementById("assignSubjectStaffId").value,
        subject_id: document.getElementById("assignSubjectId").value,
        class_id: document.getElementById("assignSubjectClassId").value || null,
      };

      await window.API.staff.assignSubject(data);
      showNotification("Subject assigned successfully", "success");
      bootstrap.Modal.getInstance(document.getElementById("assignSubjectModal")).hide();
      this.assignmentsLoaded = false;
      this.loadAssignmentsTab();
    } catch (error) {
      console.error("Error assigning subject:", error);
      showNotification("Failed to assign subject: " + (error.message || "Unknown error"), "error");
    }
  },

  removeAssignment: async function (assignmentId) {
    if (!confirm("Remove this assignment?")) return;
    try {
      await window.API.apiCall(`/staff/assignments/remove/${assignmentId}`, "DELETE");
      showNotification("Assignment removed", "success");
      this.assignmentsLoaded = false;
      this.loadAssignmentsTab();
    } catch (error) {
      console.error("Error removing assignment:", error);
      showNotification("Failed to remove assignment", "error");
    }
  },

  exportAssignments: function () {
    this.exportCsv(this.currentAssignments, "staff_assignments.csv");
  },

  // ==================== PERFORMANCE TAB ====================

  performanceLoaded: false,
  performanceData: [],

  loadPerformanceTab: async function () {
    if (this.performanceLoaded) return;
    try {
      // Populate staff filter
      const filterEl = document.getElementById("performanceStaffFilter");
      if (filterEl && filterEl.options.length <= 1) {
        this.allStaff.forEach((s) => {
          filterEl.innerHTML += `<option value="${s.id}">${s.first_name} ${s.last_name}</option>`;
        });
      }
      // Load overview performance data for all teaching staff
      await this.loadPerformanceOverview();
      this.performanceLoaded = true;
    } catch (error) {
      console.error("Error loading performance tab:", error);
    }
  },

  loadPerformanceOverview: async function () {
    try {
      const response = await window.API.apiCall("/staff/performance", "GET");
      const data = response?.data || response || [];
      this.performanceData = Array.isArray(data) ? data : data.reviews || data.staff || [];
      this.renderPerformanceTable(this.performanceData);
      this.updatePerformanceStats();
    } catch (error) {
      console.error("Error loading performance overview:", error);
      this.performanceData = [];
      this.renderPerformanceTable([]);
    }
  },

  loadStaffPerformance: async function (staffId) {
    if (!staffId) {
      this.renderPerformanceTable(this.performanceData);
      return;
    }
    try {
      const response = await window.API.staff.getPerformanceReviewHistory(staffId);
      const data = response?.data || response || [];
      const reviews = Array.isArray(data) ? data : data.reviews || [];
      this.renderPerformanceTable(reviews);
    } catch (error) {
      console.error("Error loading staff performance:", error);
    }
  },

  updatePerformanceStats: function () {
    if (!this.performanceData.length) return;

    const ratings = this.performanceData.filter((p) => p.rating || p.overall_rating).map((p) => parseFloat(p.rating || p.overall_rating));
    const avgRating = ratings.length ? (ratings.reduce((a, b) => a + b, 0) / ratings.length).toFixed(1) : "-";
    const done = this.performanceData.filter((p) => p.status === "completed" || p.status === "reviewed").length;
    const pending = this.performanceData.filter((p) => p.status === "pending" || p.status === "in_progress").length;
    const top = ratings.filter((r) => r >= 4.0).length;

    const avgEl = document.getElementById("avgPerformanceRating");
    const doneEl = document.getElementById("reviewsDone");
    const pendingEl = document.getElementById("pendingReviews");
    const topEl = document.getElementById("topPerformers");
    if (avgEl) avgEl.textContent = avgRating;
    if (doneEl) doneEl.textContent = done;
    if (pendingEl) pendingEl.textContent = pending;
    if (topEl) topEl.textContent = top;
  },

  renderPerformanceTable: function (reviews) {
    const tbody = document.getElementById("performanceTableBody");
    if (!tbody) return;

    if (!reviews.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No performance data available</td></tr>';
      return;
    }

    tbody.innerHTML = reviews
      .map((r) => {
        const rating = r.rating || r.overall_rating || "-";
        const ratingColor = parseFloat(rating) >= 4 ? "success" : parseFloat(rating) >= 3 ? "warning" : parseFloat(rating) >= 0 ? "danger" : "secondary";

        return `
          <tr>
            <td>${r.staff_no || "-"}</td>
            <td>${r.first_name || r.staff_name || ""} ${r.last_name || ""}</td>
            <td>${r.department_name || "-"}</td>
            <td><span class="badge bg-${ratingColor}">${rating}/5</span></td>
            <td>${r.review_date || r.last_review || "-"}</td>
            <td>${r.kpi_score || "-"}</td>
            <td><span class="badge bg-${r.status === "completed" ? "success" : "warning"}">${r.status || "-"}</span></td>
            <td>
              <button class="btn btn-sm btn-outline-info" onclick="staffManagementController.viewPerformanceDetail(${r.id || r.review_id})">
                <i class="bi bi-eye"></i>
              </button>
            </td>
          </tr>
        `;
      })
      .join("");
  },

  viewPerformanceDetail: function (reviewId) {
    window.location.href = "/Kingsway/home.php?route=staff_performance";
  },

  goToPerformancePage: function () {
    window.location.href = "/Kingsway/home.php?route=staff_performance_overview";
  },

  exportPerformance: function () {
    this.exportCsv(this.performanceData, "staff_performance.csv");
  },

  // ==================== SCHEDULE TAB ====================

  scheduleLoaded: false,

  loadScheduleTab: function () {
    if (this.scheduleLoaded) return;
    // Populate staff select
    const staffSelect = document.getElementById("scheduleStaffSelect");
    if (staffSelect && staffSelect.options.length <= 1) {
      this.allStaff
        .filter((s) => s.staff_type === "teaching" && s.status === "active")
        .forEach((s) => {
          staffSelect.innerHTML += `<option value="${s.id}">${s.first_name} ${s.last_name}</option>`;
        });
    }
    this.scheduleLoaded = true;
  },

  loadStaffSchedule: async function (staffId) {
    const tbody = document.getElementById("scheduleGridBody");
    if (!tbody) return;

    if (!staffId) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Select a staff member to view their schedule</td></tr>';
      return;
    }

    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading schedule...</td></tr>';

    try {
      const response = await window.API.staff.getSchedule(staffId);
      const schedule = response?.data || response || [];
      const slots = Array.isArray(schedule) ? schedule : schedule.schedule || schedule.slots || [];
      this.renderScheduleGrid(slots);
    } catch (error) {
      console.error("Error loading schedule:", error);
      tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-danger">Failed to load schedule</td></tr>';
    }
  },

  renderScheduleGrid: function (slots) {
    const tbody = document.getElementById("scheduleGridBody");
    if (!tbody) return;

    if (!slots.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">No schedule entries found for this staff member</td></tr>';
      return;
    }

    // Build a time-based grid
    const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
    const timeSlots = {};

    slots.forEach((slot) => {
      const time = slot.start_time || slot.time || slot.period || "TBD";
      const day = slot.day || slot.day_of_week || "";
      if (!timeSlots[time]) timeSlots[time] = {};
      timeSlots[time][day] = {
        subject: slot.subject_name || slot.learning_area || slot.subject || "",
        class: slot.class_name || slot.class || "",
        stream: slot.stream_name || slot.stream || "",
        room: slot.room || "",
      };
    });

    const sortedTimes = Object.keys(timeSlots).sort();

    tbody.innerHTML = sortedTimes
      .map((time) => {
        const cells = days
          .map((day) => {
            const entry = timeSlots[time][day];
            if (entry) {
              return `<td class="p-2">
                <div class="bg-light p-2 rounded border-start border-3 border-primary">
                  <strong class="text-primary">${entry.subject}</strong><br>
                  <small class="text-muted">${entry.class}${entry.stream ? " - " + entry.stream : ""}</small>
                  ${entry.room ? "<br><small class='text-info'><i class='bi bi-geo-alt'></i> " + entry.room + "</small>" : ""}
                </div>
              </td>`;
            }
            return '<td class="p-2 text-center text-muted">-</td>';
          })
          .join("");

        return `<tr><td class="fw-bold text-center bg-light">${time}</td>${cells}</tr>`;
      })
      .join("");
  },

  printSchedule: function () {
    window.print();
  },

  exportSchedule: function () {
    showNotification("Schedule export coming soon", "info");
  },

  // ==================== ENHANCED VIEW STAFF ====================

  viewingStaffId: null,

  viewStaff: async function (staffId) {
    this.viewingStaffId = staffId;
    try {
      const resp = await window.API.staff.get(staffId);
      const staff = this.extractStaffRecord(resp);
      const photo = staff.profile_pic_url || "/Kingsway/images/default-avatar.png";

      // Load additional data in parallel
      let assignments = [];
      let payrollHistory = [];
      let reviewHistory = [];
      try {
        const [assignResp, payResp, revResp] = await Promise.allSettled([
          window.API.staff.getCurrentAssignments(staffId),
          window.API.staff.getPayrollHistory(staffId),
          window.API.staff.getPerformanceReviewHistory(staffId),
        ]);
        assignments = this.extractList(assignResp);
        payrollHistory = this.extractList(payResp);
        reviewHistory = this.extractList(revResp);
      } catch (e) {
        console.warn("Error loading additional staff data:", e);
      }

      const html = `
        <div class="row">
          <div class="col-md-3 text-center border-end">
            <img src="${photo}" class="img-fluid rounded-circle mb-3" style="max-width:130px;max-height:130px;object-fit:cover" onerror="this.src='/Kingsway/images/default-avatar.png'">
            <h5 class="mb-0">${staff.first_name || ""} ${staff.middle_name || ""} ${staff.last_name || ""}</h5>
            <p class="text-muted mb-1">${staff.staff_no || ""}</p>
            ${this.getStatusBadge(staff.status)}
            ${this.getTypeBadge(staff.staff_type)}
            <hr>
            <p class="mb-1"><i class="bi bi-envelope"></i> ${staff.email || "-"}</p>
            <p class="mb-1"><i class="bi bi-telephone"></i> ${staff.phone || "-"}</p>
            <p class="mb-0"><i class="bi bi-geo-alt"></i> ${staff.address || "-"}</p>
          </div>
          <div class="col-md-9">
            <ul class="nav nav-pills mb-3" role="tablist">
              <li class="nav-item"><a class="nav-link active" data-bs-toggle="pill" href="#viewPersonal">Personal</a></li>
              <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#viewEmployment">Employment</a></li>
              <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#viewFinancial">Financial</a></li>
              <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#viewAssignments">Assignments</a></li>
              <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#viewPayroll">Payroll</a></li>
              <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#viewPerformance">Performance</a></li>
            </ul>
            <div class="tab-content">
              <!-- Personal Info -->
              <div class="tab-pane fade show active" id="viewPersonal">
                <div class="row">
                  <div class="col-md-6"><p><strong>Gender:</strong> ${staff.gender || "-"}</p></div>
                  <div class="col-md-6"><p><strong>Date of Birth:</strong> ${staff.date_of_birth || "-"}</p></div>
                  <div class="col-md-6"><p><strong>National ID:</strong> ${staff.national_id || "-"}</p></div>
                  <div class="col-md-6"><p><strong>Marital Status:</strong> ${staff.marital_status || "-"}</p></div>
                </div>
              </div>
              <!-- Employment Info -->
              <div class="tab-pane fade" id="viewEmployment">
                <div class="row">
                  <div class="col-md-6"><p><strong>Department:</strong> ${staff.department_name || "-"}</p></div>
                  <div class="col-md-6"><p><strong>Position:</strong> ${staff.position || "-"}</p></div>
                  <div class="col-md-6"><p><strong>Role:</strong> ${staff.role_name || "-"}</p></div>
                  <div class="col-md-6"><p><strong>Staff Type:</strong> ${staff.staff_type || "-"}</p></div>
                  <div class="col-md-6"><p><strong>Employment Date:</strong> ${staff.employment_date || "-"}</p></div>
                  <div class="col-md-6"><p><strong>Contract Type:</strong> ${staff.contract_type || "-"}</p></div>
                  <div class="col-md-6"><p><strong>TSC Number:</strong> ${staff.tsc_no || "-"}</p></div>
                  <div class="col-md-6"><p><strong>Supervisor:</strong> ${staff.supervisor_name || staff.supervisor_id || "-"}</p></div>
                </div>
              </div>
              <!-- Financial Info -->
              <div class="tab-pane fade" id="viewFinancial">
                <div class="row">
                  <div class="col-md-6"><p><strong>Basic Salary:</strong> ${this.formatCurrency(staff.salary || 0)}</p></div>
                  <div class="col-md-6"><p><strong>Bank Account:</strong> ${staff.bank_account || "-"}</p></div>
                  <div class="col-md-6"><p><strong>KRA PIN:</strong> ${staff.kra_pin || "-"}</p></div>
                  <div class="col-md-6"><p><strong>NHIF No:</strong> ${staff.nhif_no || "-"}</p></div>
                  <div class="col-md-6"><p><strong>NSSF No:</strong> ${staff.nssf_no || "-"}</p></div>
                </div>
              </div>
              <!-- Assignments -->
              <div class="tab-pane fade" id="viewAssignments">
                ${assignments.length ? `
                  <table class="table table-sm"><thead><tr><th>Class</th><th>Subject</th><th>Role</th></tr></thead><tbody>
                    ${assignments.map((a) => `<tr><td>${a.class_name || "-"}</td><td>${a.subject_name || a.learning_area || "-"}</td><td>${a.role || a.assignment_type || "-"}</td></tr>`).join("")}
                  </tbody></table>
                ` : '<p class="text-muted">No current assignments</p>'}
              </div>
              <!-- Payroll History -->
              <div class="tab-pane fade" id="viewPayroll">
                ${payrollHistory.length ? `
                  <table class="table table-sm"><thead><tr><th>Period</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Net</th><th></th></tr></thead><tbody>
                    ${payrollHistory.slice(0, 6).map((p) => `<tr>
                      <td>${p.month || p.pay_period || "-"}/${p.year || ""}</td>
                      <td>${this.formatCurrency(p.basic_salary || 0)}</td>
                      <td>${this.formatCurrency(p.allowances || 0)}</td>
                      <td>${this.formatCurrency(p.total_deductions || p.deductions || 0)}</td>
                      <td>${this.formatCurrency(p.net_salary || p.net_pay || 0)}</td>
                      <td><button class="btn btn-sm btn-outline-info" onclick="staffManagementController.viewPayslip(${staff.id}, ${p.month || 0}, ${p.year || 0})"><i class="bi bi-receipt"></i></button></td>
                    </tr>`).join("")}
                  </tbody></table>
                ` : '<p class="text-muted">No payroll history available</p>'}
              </div>
              <!-- Performance Reviews -->
              <div class="tab-pane fade" id="viewPerformance">
                ${reviewHistory.length ? `
                  <table class="table table-sm"><thead><tr><th>Date</th><th>Rating</th><th>Status</th><th>Reviewer</th></tr></thead><tbody>
                    ${reviewHistory.slice(0, 6).map((r) => `<tr>
                      <td>${r.review_date || r.created_at || "-"}</td>
                      <td><span class="badge bg-${parseFloat(r.rating || r.overall_rating || 0) >= 4 ? "success" : "warning"}">${r.rating || r.overall_rating || "-"}/5</span></td>
                      <td>${r.status || "-"}</td>
                      <td>${r.reviewer_name || "-"}</td>
                    </tr>`).join("")}
                  </tbody></table>
                ` : '<p class="text-muted">No performance reviews available</p>'}
              </div>
            </div>
          </div>
        </div>
      `;

      document.getElementById("viewStaffContent").innerHTML = html;
      new bootstrap.Modal(document.getElementById("viewStaffModal")).show();
    } catch (error) {
      console.error("Error loading staff:", error);
      showNotification("Failed to load staff details", "error");
    }
  },

  extractList: function (settledPromise) {
    if (settledPromise.status !== "fulfilled") return [];
    const resp = settledPromise.value;
    const data = resp?.data || resp || [];
    return Array.isArray(data) ? data : data.records || data.history || data.reviews || data.assignments || [];
  },

  editFromView: function () {
    if (this.viewingStaffId) {
      bootstrap.Modal.getInstance(document.getElementById("viewStaffModal")).hide();
      this.editStaff(this.viewingStaffId);
    }
  },

  // ==================== PAYSLIP VIEW ====================

  viewPayslip: async function (staffId, month, year) {
    const content = document.getElementById("payslipContent");
    if (!content) return;
    content.innerHTML = '<div class="text-center py-3"><div class="spinner-border"></div></div>';
    new bootstrap.Modal(document.getElementById("payslipModal")).show();

    try {
      const response = await window.API.staff.getPayslip(staffId, { month, year });
      const payslip = response?.data || response || {};

      content.innerHTML = `
        <div class="border p-4">
          <div class="text-center mb-3">
            <h4>Kingsway Academy</h4>
            <h6 class="text-muted">Payslip for ${this.getMonthName(month)} ${year}</h6>
          </div>
          <hr>
          <div class="row mb-3">
            <div class="col-md-6">
              <p><strong>Name:</strong> ${payslip.first_name || payslip.staff_name || ""} ${payslip.last_name || ""}</p>
              <p><strong>Staff No:</strong> ${payslip.staff_no || "-"}</p>
              <p><strong>Department:</strong> ${payslip.department_name || "-"}</p>
            </div>
            <div class="col-md-6 text-end">
              <p><strong>KRA PIN:</strong> ${payslip.kra_pin || "-"}</p>
              <p><strong>NHIF:</strong> ${payslip.nhif_no || "-"}</p>
              <p><strong>NSSF:</strong> ${payslip.nssf_no || "-"}</p>
            </div>
          </div>
          <table class="table table-bordered">
            <thead class="table-light"><tr><th>Description</th><th class="text-end">Amount (KES)</th></tr></thead>
            <tbody>
              <tr><td>Basic Salary</td><td class="text-end">${this.formatCurrency(payslip.basic_salary || payslip.salary || 0)}</td></tr>
              <tr><td>Housing Allowance</td><td class="text-end">${this.formatCurrency(payslip.housing_allowance || 0)}</td></tr>
              <tr><td>Transport Allowance</td><td class="text-end">${this.formatCurrency(payslip.transport_allowance || 0)}</td></tr>
              <tr><td>Other Allowances</td><td class="text-end">${this.formatCurrency(payslip.other_allowances || payslip.allowances || 0)}</td></tr>
              <tr class="table-success fw-bold"><td>Gross Pay</td><td class="text-end">${this.formatCurrency(payslip.gross_pay || payslip.gross_salary || 0)}</td></tr>
              <tr><td>PAYE (Tax)</td><td class="text-end text-danger">-${this.formatCurrency(payslip.paye || payslip.tax || 0)}</td></tr>
              <tr><td>NHIF Contribution</td><td class="text-end text-danger">-${this.formatCurrency(payslip.nhif_deduction || 0)}</td></tr>
              <tr><td>NSSF Contribution</td><td class="text-end text-danger">-${this.formatCurrency(payslip.nssf_deduction || 0)}</td></tr>
              <tr><td>Housing Levy</td><td class="text-end text-danger">-${this.formatCurrency(payslip.housing_levy || 0)}</td></tr>
              <tr><td>Other Deductions</td><td class="text-end text-danger">-${this.formatCurrency(payslip.other_deductions || 0)}</td></tr>
              <tr><td>Children Fee Deductions</td><td class="text-end text-danger">-${this.formatCurrency(payslip.children_fee_deduction || 0)}</td></tr>
              <tr class="table-warning fw-bold"><td>Total Deductions</td><td class="text-end">-${this.formatCurrency(payslip.total_deductions || 0)}</td></tr>
              <tr class="table-primary fw-bold fs-5"><td>Net Pay</td><td class="text-end">${this.formatCurrency(payslip.net_pay || payslip.net_salary || 0)}</td></tr>
            </tbody>
          </table>
          <p class="text-muted text-center small">Generated on ${new Date().toLocaleDateString()}</p>
        </div>
      `;
    } catch (error) {
      console.error("Error loading payslip:", error);
      content.innerHTML = '<div class="text-center text-danger py-3"><i class="bi bi-exclamation-triangle fs-1"></i><br>Failed to load payslip</div>';
    }
  },

  getMonthName: function (month) {
    const months = ["", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    return months[parseInt(month)] || month;
  },

  printPayslip: function () {
    const content = document.getElementById("payslipContent");
    if (!content) return;
    const win = window.open("", "_blank");
    win.document.write("<html><head><title>Payslip</title>");
    win.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    win.document.write("</head><body class='p-4'>");
    win.document.write(content.innerHTML);
    win.document.write("</body></html>");
    win.document.close();
    win.onload = function () { win.print(); };
  },

  generatePayslip: async function (staffId) {
    const now = new Date();
    const month = now.getMonth() + 1;
    const year = now.getFullYear();

    if (!confirm(`Generate payslip for ${this.getMonthName(month)} ${year}?`)) return;

    try {
      const response = await window.API.staff.generateDetailedPayslip(staffId, month, year);
      if (response?.success || response?.data) {
        showNotification("Payslip generated successfully", "success");
        this.viewPayslip(staffId, month, year);
      } else {
        showNotification(response?.message || "Failed to generate payslip", "error");
      }
    } catch (error) {
      console.error("Error generating payslip:", error);
      showNotification("Failed to generate payslip: " + (error.message || "Unknown error"), "error");
    }
  },

  // ==================== DEACTIVATION ====================

  deactivateStaff: async function (staffId) {
    if (!confirm("Are you sure you want to deactivate this staff member? They will no longer be able to access the system.")) return;
    try {
      await window.API.staff.update(staffId, { status: "inactive" });
      showNotification("Staff deactivated successfully", "success");
      await this.loadStaff();
      this.loadStatistics();
    } catch (error) {
      console.error("Error deactivating staff:", error);
      showNotification("Failed to deactivate staff", "error");
    }
  },

  activateStaff: async function (staffId) {
    if (!confirm("Reactivate this staff member?")) return;
    try {
      await window.API.staff.update(staffId, { status: "active" });
      showNotification("Staff reactivated successfully", "success");
      await this.loadStaff();
      this.loadStatistics();
    } catch (error) {
      console.error("Error activating staff:", error);
      showNotification("Failed to activate staff", "error");
    }
  },
};

document.addEventListener("DOMContentLoaded", () => {
  if (!AuthContext.isAuthenticated()) {
    window.location.href = "/Kingsway/index.php";
    return;
  }

  window.staffManagementController = staffManagementController;
  staffManagementController.init();
});
