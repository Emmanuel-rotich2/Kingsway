/**
 * Manage Teachers Page Controller
 * Full CRUD for teaching staff with assignments, subjects, and class management
 * Uses window.API namespace from api.js
 */

const manageTeachersController = {
  allTeachers: [],
  filteredTeachers: [],
  departments: [],
  classes: [],
  learningAreas: [],
  pagination: { page: 1, limit: 15, total: 0 },
  searchTerm: "",
  departmentFilter: "",
  statusFilter: "",
  subjectFilter: "",
  editingId: null,

  // ── Helpers ──────────────────────────────────────────
  extractList(response) {
    if (!response) return [];
    if (Array.isArray(response)) return response;
    if (Array.isArray(response.staff)) return response.staff;
    if (Array.isArray(response.data?.staff)) return response.data.staff;
    if (Array.isArray(response.data)) return response.data;
    return [];
  },

  unwrap(response) {
    if (!response) return null;
    if (response.data?.data) return response.data.data;
    if (response.data) return response.data;
    return response;
  },

  esc(str) {
    if (!str) return "";
    return String(str).replace(
      /[&<>"']/g,
      (m) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[m],
    );
  },

  toast(message, type = "success") {
    const toastEl = document.getElementById("academicToast");
    const title = document.getElementById("toastTitle");
    const body = document.getElementById("toastBody");
    if (!toastEl || !body) {
      alert(message);
      return;
    }
    title.textContent =
      type === "success" ? "Success" : type === "danger" ? "Error" : "Notice";
    body.textContent = message;
    toastEl.classList.remove(
      "bg-success",
      "bg-danger",
      "bg-warning",
      "bg-info",
    );
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();
  },

  // ── Init ─────────────────────────────────────────────
  init: async function () {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.bindEvents();
    await Promise.all([
      this.loadTeachers(),
      this.loadDepartments(),
      this.loadClasses(),
      this.loadLearningAreas(),
      this.loadStats(),
    ]);
  },

  bindEvents: function () {
    // Search
    const searchInput = document.getElementById("teacherSearch");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => {
        this.searchTerm = e.target.value.toLowerCase();
        this.applyFilters();
      });
    }

    // Filters
    const deptFilter = document.getElementById("departmentFilter");
    if (deptFilter) {
      deptFilter.addEventListener("change", (e) => {
        this.departmentFilter = e.target.value;
        this.applyFilters();
      });
    }

    const statusFilter = document.getElementById("statusFilter");
    if (statusFilter) {
      statusFilter.addEventListener("change", (e) => {
        this.statusFilter = e.target.value;
        this.applyFilters();
      });
    }

    const subjectFilter = document.getElementById("subjectFilter");
    if (subjectFilter) {
      subjectFilter.addEventListener("change", (e) => {
        this.subjectFilter = e.target.value;
        this.applyFilters();
      });
    }

    // Form submit
    const form = document.getElementById("teacherForm");
    if (form) {
      form.addEventListener("submit", (e) => this.saveTeacher(e));
    }

    // Save button in modal
    const saveBtn = document.getElementById("saveTeacherBtn");
    if (saveBtn) {
      saveBtn.addEventListener("click", () => {
        document.getElementById("teacherForm")?.requestSubmit();
      });
    }
  },

  // ── Data Loading ─────────────────────────────────────
  loadTeachers: async function () {
    try {
      const response = await window.API.apiCall(
        "/staff?limit=200&staff_type=teaching",
        "GET",
      );
      const all = this.extractList(this.unwrap(response));
      this.allTeachers = all.filter(
        (s) =>
          (s.staff_type || "").toLowerCase() === "teaching" ||
          (s.staff_type_name || "").toLowerCase().includes("teaching") ||
          (s.department_name || "").toLowerCase() === "academics",
      );
      // If filtering produced no results, show all (may not have staff_type set)
      if (this.allTeachers.length === 0 && all.length > 0) {
        this.allTeachers = all;
      }
      this.applyFilters();
    } catch (error) {
      console.error("Error loading teachers:", error);
      this.renderTable([]);
    }
  },

  loadStats: async function () {
    try {
      const response = await window.API.apiCall("/staff/stats", "GET");
      const stats = response?.data || response;
      if (stats) {
        document.getElementById("statTotalTeachers").textContent =
          stats.teacher_count || this.allTeachers.length || 0;
        document.getElementById("statActiveTeachers").textContent =
          stats.teacher_count || 0;
        document.getElementById("statPresentToday").textContent =
          stats.staff_present_today || 0;
      }
    } catch (e) {
      console.warn("Could not load stats:", e);
    }
    // Set subjects covered from learning areas
    document.getElementById("statSubjectsCovered").textContent =
      this.learningAreas.length || 0;
  },

  loadDepartments: async function () {
    try {
      const response = await window.API.apiCall(
        "/staff/departments/get",
        "GET",
      );
      this.departments = this.unwrap(response) || [];
      if (!Array.isArray(this.departments)) this.departments = [];
      this.populateSelect(
        "departmentFilter",
        this.departments,
        "name",
        "id",
        "All Departments",
      );
      this.populateSelect(
        "teacherDepartment",
        this.departments,
        "name",
        "id",
        "Select Department",
      );
    } catch (e) {
      console.warn("Could not load departments:", e);
    }
  },

  loadClasses: async function () {
    try {
      const response = await window.API.academic.listClasses();
      this.classes = this.unwrap(response) || [];
      if (!Array.isArray(this.classes)) this.classes = [];
    } catch (e) {
      console.warn("Could not load classes:", e);
    }
  },

  loadLearningAreas: async function () {
    try {
      const response = await window.API.academic.listLearningAreas();
      this.learningAreas = this.unwrap(response) || [];
      if (!Array.isArray(this.learningAreas)) this.learningAreas = [];
      this.populateSelect(
        "subjectFilter",
        this.learningAreas,
        "name",
        "id",
        "All Subjects",
      );
    } catch (e) {
      console.warn("Could not load learning areas:", e);
    }
  },

  populateSelect: function (elementId, items, labelKey, valueKey, defaultText) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.innerHTML = `<option value="">${defaultText}</option>`;
    (Array.isArray(items) ? items : []).forEach((item) => {
      const label = item[labelKey] || item.name || item;
      const value = item[valueKey] || item.id || label;
      el.innerHTML += `<option value="${value}">${this.esc(String(label))}</option>`;
    });
  },

  // ── Filtering ────────────────────────────────────────
  applyFilters: function () {
    let list = [...this.allTeachers];

    if (this.searchTerm) {
      list = list.filter((s) => {
        const name =
          `${s.first_name || ""} ${s.last_name || ""} ${s.name || ""}`.toLowerCase();
        const staffNo = (s.staff_no || "").toLowerCase();
        const email = (s.email || "").toLowerCase();
        const tsc = (s.tsc_no || "").toLowerCase();
        return (
          name.includes(this.searchTerm) ||
          staffNo.includes(this.searchTerm) ||
          email.includes(this.searchTerm) ||
          tsc.includes(this.searchTerm)
        );
      });
    }

    if (this.departmentFilter) {
      list = list.filter(
        (s) => String(s.department_id || "") === String(this.departmentFilter),
      );
    }

    if (this.statusFilter) {
      list = list.filter(
        (s) =>
          (s.status || "").toLowerCase() === this.statusFilter.toLowerCase(),
      );
    }

    this.filteredTeachers = list;
    this.pagination.total = list.length;
    this.renderTable(list);
    this.updateStats(list);
  },

  updateStats: function (list) {
    const total = list.length;
    const active = list.filter(
      (s) => (s.status || "").toLowerCase() === "active",
    ).length;
    document.getElementById("statTotalTeachers").textContent = total;
    document.getElementById("statActiveTeachers").textContent = active;
  },

  // ── Render Table ─────────────────────────────────────
  renderTable: function (teachers) {
    const tbody = document.getElementById("teachersTableBody");
    if (!tbody) return;

    if (!teachers || teachers.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>No teachers found matching your criteria.</td></tr>`;
      this.updatePaginationInfo(0, 0, 0);
      return;
    }

    const start = (this.pagination.page - 1) * this.pagination.limit;
    const end = Math.min(start + this.pagination.limit, teachers.length);
    const pageTeachers = teachers.slice(start, end);

    tbody.innerHTML = pageTeachers
      .map((s, i) => {
        const name =
          `${s.first_name || ""} ${s.last_name || ""}`.trim() || s.name || "—";
        const dept = s.department_name || s.department || "—";
        const statusClass =
          (s.status || "").toLowerCase() === "active" ? "success" : "secondary";

        return `
          <tr>
            <td>${start + i + 1}</td>
            <td><span class="fw-semibold">${this.esc(s.staff_no || "—")}</span></td>
            <td>
              <div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center me-2" style="width:36px;height:36px;">
                  <i class="bi bi-person-fill"></i>
                </div>
                <div>
                  <div class="fw-semibold">${this.esc(name)}</div>
                  <small class="text-muted">${this.esc(s.email || "")}</small>
                </div>
              </div>
            </td>
            <td>${this.esc(dept)}</td>
            <td><small class="text-muted">${this.esc(s.tsc_no || "—")}</small></td>
            <td>—</td>
            <td><span class="badge bg-${statusClass}">${this.esc(s.status || "unknown")}</span></td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-info" onclick="manageTeachersController.viewTeacher(${s.id})" title="View">
                  <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-outline-warning" onclick="manageTeachersController.showEditForm(${s.id})" title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="manageTeachersController.deleteTeacher(${s.id})" title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>`;
      })
      .join("");

    this.updatePaginationInfo(start + 1, end, teachers.length);
    this.renderPagination(teachers.length);
  },

  updatePaginationInfo: function (from, to, total) {
    const el = document.getElementById("teacherPaginationInfo");
    if (el) el.textContent = `Showing ${from} to ${to} of ${total} teachers`;
  },

  renderPagination: function (total) {
    const container = document.getElementById("teacherPagination");
    if (!container) return;

    const totalPages = Math.ceil(total / this.pagination.limit);
    if (totalPages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = `<li class="page-item ${this.pagination.page <= 1 ? "disabled" : ""}">
      <a class="page-link" href="#" onclick="manageTeachersController.goToPage(${this.pagination.page - 1}); return false;">&laquo;</a></li>`;

    for (let i = 1; i <= totalPages; i++) {
      html += `<li class="page-item ${i === this.pagination.page ? "active" : ""}">
        <a class="page-link" href="#" onclick="manageTeachersController.goToPage(${i}); return false;">${i}</a></li>`;
    }

    html += `<li class="page-item ${this.pagination.page >= totalPages ? "disabled" : ""}">
      <a class="page-link" href="#" onclick="manageTeachersController.goToPage(${this.pagination.page + 1}); return false;">&raquo;</a></li>`;

    container.innerHTML = html;
  },

  goToPage: function (page) {
    const totalPages = Math.ceil(
      this.filteredTeachers.length / this.pagination.limit,
    );
    if (page < 1 || page > totalPages) return;
    this.pagination.page = page;
    this.renderTable(this.filteredTeachers);
  },

  // ── CRUD Operations ──────────────────────────────────
  showCreateForm: function () {
    this.editingId = null;
    document.getElementById("teacherModalLabel").textContent =
      "Add New Teacher";
    const form = document.getElementById("teacherForm");
    if (form) form.reset();

    // Reset tab to first
    const firstTab = document.querySelector(
      '#teacherModal .nav-link[data-bs-target="#tabPersonal"]',
    );
    if (firstTab) new bootstrap.Tab(firstTab).show();

    const modal = new bootstrap.Modal(document.getElementById("teacherModal"));
    modal.show();
  },

  showEditForm: async function (id) {
    try {
      const response = await window.API.apiCall(`/staff/${id}`, "GET");
      const staff = this.unwrap(response);
      if (!staff) {
        this.toast("Teacher not found", "danger");
        return;
      }

      this.editingId = id;
      document.getElementById("teacherModalLabel").textContent = "Edit Teacher";

      // Personal tab
      this.setVal("teacherFirstName", staff.first_name);
      this.setVal("teacherLastName", staff.last_name);
      this.setVal("teacherEmail", staff.email);
      this.setVal("teacherPhone", staff.phone);
      this.setVal("teacherGender", staff.gender);
      this.setVal("teacherDOB", staff.date_of_birth);
      this.setVal("teacherMaritalStatus", staff.marital_status);
      this.setVal("teacherAddress", staff.address);

      // Professional tab
      this.setVal("teacherTSC", staff.tsc_no);
      this.setVal("teacherStaffNo", staff.staff_no);
      this.setVal("teacherDepartment", staff.department_id);
      this.setVal("teacherPosition", staff.position);
      this.setVal("teacherEmployDate", staff.employment_date);
      this.setVal("teacherContractType", staff.contract_type);

      // Statutory tab
      this.setVal("teacherKRA", staff.kra_pin);
      this.setVal("teacherNHIF", staff.nhif_no);
      this.setVal("teacherNSSF", staff.nssf_no);
      this.setVal("teacherBank", staff.bank_account);

      // Reset to first tab
      const firstTab = document.querySelector(
        '#teacherModal .nav-link[data-bs-target="#tabPersonal"]',
      );
      if (firstTab) new bootstrap.Tab(firstTab).show();

      const modal = new bootstrap.Modal(
        document.getElementById("teacherModal"),
      );
      modal.show();
    } catch (error) {
      console.error("Error loading teacher:", error);
      this.toast("Failed to load teacher details", "danger");
    }
  },

  setVal: function (id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value || "";
  },

  saveTeacher: async function (e) {
    if (e) e.preventDefault();

    const data = {
      first_name: document.getElementById("teacherFirstName")?.value?.trim(),
      last_name: document.getElementById("teacherLastName")?.value?.trim(),
      email: document.getElementById("teacherEmail")?.value?.trim() || null,
      phone: document.getElementById("teacherPhone")?.value?.trim() || null,
      gender: document.getElementById("teacherGender")?.value || null,
      date_of_birth: document.getElementById("teacherDOB")?.value || null,
      marital_status:
        document.getElementById("teacherMaritalStatus")?.value || null,
      address: document.getElementById("teacherAddress")?.value?.trim() || null,
      tsc_no: document.getElementById("teacherTSC")?.value?.trim() || null,
      department_id:
        document.getElementById("teacherDepartment")?.value || null,
      position:
        document.getElementById("teacherPosition")?.value?.trim() || "Teacher",
      employment_date:
        document.getElementById("teacherEmployDate")?.value || null,
      contract_type:
        document.getElementById("teacherContractType")?.value || "permanent",
      kra_pin: document.getElementById("teacherKRA")?.value?.trim() || null,
      nhif_no: document.getElementById("teacherNHIF")?.value?.trim() || null,
      nssf_no: document.getElementById("teacherNSSF")?.value?.trim() || null,
      bank_account:
        document.getElementById("teacherBank")?.value?.trim() || null,
      staff_type_id: 1, // Teaching staff
    };

    if (!data.first_name || !data.last_name) {
      this.toast("First name and last name are required", "danger");
      return;
    }

    try {
      if (this.editingId) {
        await window.API.apiCall(`/staff/${this.editingId}`, "PUT", data);
        this.toast("Teacher updated successfully!");
      } else {
        await window.API.apiCall("/staff", "POST", data);
        this.toast("Teacher added successfully!");
      }

      bootstrap.Modal.getInstance(
        document.getElementById("teacherModal"),
      )?.hide();
      await this.loadTeachers();
      await this.loadStats();
    } catch (error) {
      console.error("Error saving teacher:", error);
      this.toast(error.message || "Failed to save teacher", "danger");
    }
  },

  viewTeacher: async function (id) {
    try {
      const response = await window.API.apiCall(`/staff/${id}`, "GET");
      const s = this.unwrap(response);
      if (!s) {
        this.toast("Teacher not found", "danger");
        return;
      }

      const name = `${s.first_name || ""} ${s.last_name || ""}`.trim() || "—";
      const content = document.getElementById("viewTeacherContent");
      if (!content) return;

      content.innerHTML = `
        <div class="row">
          <div class="col-md-3 text-center">
            <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:100px;height:100px;">
              <i class="bi bi-person-fill text-success fs-1"></i>
            </div>
            <h5 class="mb-1">${this.esc(name)}</h5>
            <p class="text-muted mb-1">${this.esc(s.staff_no || "")}</p>
            <span class="badge bg-${(s.status || "").toLowerCase() === "active" ? "success" : "secondary"} mb-2">${this.esc(s.status || "unknown")}</span>
          </div>
          <div class="col-md-9">
            <ul class="nav nav-tabs mb-3">
              <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#vtPersonal">Personal</a></li>
              <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#vtProfessional">Professional</a></li>
              <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#vtAssignments">Assignments</a></li>
              <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#vtStatutory">Statutory</a></li>
            </ul>
            <div class="tab-content">
              <div class="tab-pane fade show active" id="vtPersonal">
                <div class="row">
                  <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                      <tr><td class="text-muted" style="width:40%">Email</td><td>${this.esc(s.email || "—")}</td></tr>
                      <tr><td class="text-muted">Phone</td><td>${this.esc(s.phone || "—")}</td></tr>
                      <tr><td class="text-muted">Gender</td><td>${this.esc(s.gender || "—")}</td></tr>
                    </table>
                  </div>
                  <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                      <tr><td class="text-muted" style="width:40%">Date of Birth</td><td>${this.esc(s.date_of_birth || "—")}</td></tr>
                      <tr><td class="text-muted">Marital Status</td><td>${this.esc(s.marital_status || "—")}</td></tr>
                      <tr><td class="text-muted">Address</td><td>${this.esc(s.address || "—")}</td></tr>
                    </table>
                  </div>
                </div>
              </div>
              <div class="tab-pane fade" id="vtProfessional">
                <div class="row">
                  <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                      <tr><td class="text-muted" style="width:40%">TSC No</td><td>${this.esc(s.tsc_no || "—")}</td></tr>
                      <tr><td class="text-muted">Department</td><td>${this.esc(s.department_name || "—")}</td></tr>
                      <tr><td class="text-muted">Position</td><td>${this.esc(s.position || "—")}</td></tr>
                    </table>
                  </div>
                  <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                      <tr><td class="text-muted" style="width:40%">Employment Date</td><td>${this.esc(s.employment_date || "—")}</td></tr>
                      <tr><td class="text-muted">Contract Type</td><td><span class="badge bg-info">${this.esc(s.contract_type || "—")}</span></td></tr>
                    </table>
                  </div>
                </div>
              </div>
              <div class="tab-pane fade" id="vtAssignments">
                <p class="text-muted">Assignments will be loaded from staff assignments API.</p>
              </div>
              <div class="tab-pane fade" id="vtStatutory">
                <div class="row">
                  <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                      <tr><td class="text-muted" style="width:40%">KRA PIN</td><td>${this.esc(s.kra_pin || "—")}</td></tr>
                      <tr><td class="text-muted">NHIF No</td><td>${this.esc(s.nhif_no || "—")}</td></tr>
                    </table>
                  </div>
                  <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                      <tr><td class="text-muted" style="width:40%">NSSF No</td><td>${this.esc(s.nssf_no || "—")}</td></tr>
                      <tr><td class="text-muted">Bank Account</td><td>${this.esc(s.bank_account || "—")}</td></tr>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>`;

      const modal = new bootstrap.Modal(
        document.getElementById("viewTeacherModal"),
      );
      modal.show();
    } catch (error) {
      console.error("Error viewing teacher:", error);
      this.toast("Failed to load teacher details", "danger");
    }
  },

  deleteTeacher: async function (id) {
    if (
      !confirm(
        "Are you sure you want to delete this teacher? This action cannot be undone.",
      )
    )
      return;

    try {
      await window.API.apiCall(`/staff/${id}`, "DELETE");
      this.toast("Teacher deleted successfully!");
      await this.loadTeachers();
      await this.loadStats();
    } catch (error) {
      console.error("Error deleting teacher:", error);
      this.toast("Failed to delete teacher", "danger");
    }
  },

  // ── Export ───────────────────────────────────────────
  exportTeachers: function () {
    const teachers = this.filteredTeachers;
    if (!teachers.length) {
      this.toast("No data to export", "danger");
      return;
    }

    let csv =
      "Staff No,Name,Email,Department,TSC No,Position,Contract,Status\n";
    teachers.forEach((s) => {
      const name = `${s.first_name || ""} ${s.last_name || ""}`.trim();
      csv += `"${s.staff_no || ""}","${name}","${s.email || ""}","${s.department_name || ""}","${s.tsc_no || ""}","${s.position || ""}","${s.contract_type || ""}","${s.status || ""}"\n`;
    });

    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `teachers_export_${new Date().toISOString().split("T")[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  },

  printTeachers: function () {
    window.print();
  },
};

// ── Bootstrap ────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  manageTeachersController.init();
});
