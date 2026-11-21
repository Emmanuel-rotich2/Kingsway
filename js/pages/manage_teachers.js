/**
 * Manage Teachers Page Controller
 * Full CRUD for teaching staff with assignments, subjects, and class management
 * Uses window.API namespace from api.js
 * Page: pages/manage_teachers.php
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
  viewingId: null,

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

  async toast(message, type = "success") {
    const toastEl = document.getElementById("teacherToast");
    const icon = document.getElementById("toastIcon");
    const title = document.getElementById("toastTitle");
    const body = document.getElementById("toastBody");
    if (!toastEl || !body) {
      await window.infoDialog('Notice', message);
      return;
    }
    if (icon) {
      icon.className =
        type === "success"
          ? "bi bi-check-circle-fill me-2 text-success"
          : type === "danger"
            ? "bi bi-exclamation-circle-fill me-2 text-danger"
            : type === "warning"
              ? "bi bi-exclamation-triangle-fill me-2 text-warning"
              : "bi bi-info-circle-fill me-2 text-info";
    }
    title.textContent =
      type === "success" ? "Success" : type === "danger" ? "Error" : "Notice";
    body.textContent = message;
    const bsToast = new bootstrap.Toast(toastEl, { delay: 3000 });
    bsToast.show();
  },

  setVal(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value || "";
  },

  // ── Init ─────────────────────────────────────────────
  init: async function () {
    await window.AuthContext?.ready();
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
    const searchInput = document.getElementById("searchTeachers");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => {
        this.searchTerm = e.target.value.toLowerCase();
        this.pagination.page = 1;
        this.applyFilters();
      });
    }

    const deptFilter = document.getElementById("filterDepartment");
    if (deptFilter) {
      deptFilter.addEventListener("change", (e) => {
        this.departmentFilter = e.target.value;
        this.pagination.page = 1;
        this.applyFilters();
      });
    }

    const statusFilter = document.getElementById("filterStatus");
    if (statusFilter) {
      statusFilter.addEventListener("change", (e) => {
        this.statusFilter = e.target.value;
        this.pagination.page = 1;
        this.applyFilters();
      });
    }

    const subjectFilter = document.getElementById("filterSubject");
    if (subjectFilter) {
      subjectFilter.addEventListener("change", (e) => {
        this.subjectFilter = e.target.value;
        this.pagination.page = 1;
        this.applyFilters();
      });
    }

    const clearBtn = document.getElementById("btnClearFilters");
    if (clearBtn) {
      clearBtn.addEventListener("click", () => this.clearFilters());
    }

    const addBtn = document.getElementById("btnAddTeacher");
    if (addBtn) {
      addBtn.addEventListener("click", () => this.showCreateForm());
    }

    const exportBtn = document.getElementById("btnExportTeachers");
    if (exportBtn) {
      exportBtn.addEventListener("click", () => this.exportTeachers());
    }

    const printBtn = document.getElementById("btnPrintTeachers");
    if (printBtn) {
      printBtn.addEventListener("click", () => this.printTeachers());
    }

    const saveBtn = document.getElementById("btnSaveTeacher");
    if (saveBtn) {
      saveBtn.addEventListener("click", () => this.saveTeacher());
    }

    const confirmDeleteBtn = document.getElementById("btnConfirmDelete");
    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener("click", () => this.confirmDelete());
    }

    const editFromViewBtn = document.getElementById("btnEditFromView");
    if (editFromViewBtn) {
      editFromViewBtn.addEventListener("click", () => this.editFromView());
    }

    const printProfileBtn = document.getElementById("btnPrintProfile");
    if (printProfileBtn) {
      printProfileBtn.addEventListener("click", () => this.printProfile());
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
    document.getElementById("statSubjectsCovered").textContent =
      this.learningAreas.length || 0;
  },

  loadDepartments: async function () {
    try {
      const response = await window.API.apiCall(
        "/staff/departments-get",
        "GET",
      );
      this.departments = this.unwrap(response) || [];
      if (!Array.isArray(this.departments)) this.departments = [];
      this.populateSelect(
        "filterDepartment",
        this.departments,
        "name",
        "id",
        "All Departments",
      );
      this.populateSelect(
        "formDepartment",
        this.departments,
        "name",
        "id",
        "-- Select Department --",
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
      this.populateMultiSelect(
        "formClassAssignments",
        this.classes,
        "name",
        "id",
      );
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
        "filterSubject",
        this.learningAreas,
        "name",
        "id",
        "All Subjects",
      );
      this.populateMultiSelect(
        "formSubjectAssignments",
        this.learningAreas,
        "name",
        "id",
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

  populateMultiSelect: function (elementId, items, labelKey, valueKey) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.innerHTML = "";
    (Array.isArray(items) ? items : []).forEach((item) => {
      const label = item[labelKey] || item.name || item;
      const value = item[valueKey] || item.id || label;
      el.innerHTML += `<option value="${value}">${this.esc(String(label))}</option>`;
    });
  },

  setMultiSelectValues: function (elementId, values) {
    const el = document.getElementById(elementId);
    if (!el || !values) return;
    const valArr = Array.isArray(values) ? values.map(String) : [];
    Array.from(el.options).forEach((opt) => {
      opt.selected = valArr.includes(String(opt.value));
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

    if (this.subjectFilter) {
      list = list.filter((s) => {
        const subjects = s.subjects || s.subject_names || "";
        if (Array.isArray(subjects)) {
          return subjects.some(
            (sub) =>
              String(sub.id || sub) === String(this.subjectFilter) ||
              (sub.name || "").toLowerCase().includes(this.subjectFilter.toLowerCase()),
          );
        }
        return String(subjects).includes(String(this.subjectFilter));
      });
    }

    this.filteredTeachers = list;
    this.pagination.total = list.length;
    this.renderTable(list);
  },

  clearFilters: function () {
    this.searchTerm = "";
    this.departmentFilter = "";
    this.statusFilter = "";
    this.subjectFilter = "";
    this.pagination.page = 1;
    const searchEl = document.getElementById("searchTeachers");
    if (searchEl) searchEl.value = "";
    const deptEl = document.getElementById("filterDepartment");
    if (deptEl) deptEl.value = "";
    const statusEl = document.getElementById("filterStatus");
    if (statusEl) statusEl.value = "";
    const subjectEl = document.getElementById("filterSubject");
    if (subjectEl) subjectEl.value = "";
    this.applyFilters();
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
        const subjects = Array.isArray(s.subjects)
          ? s.subjects.map((x) => x.name || x).join(", ")
          : s.subject_name || "—";
        const classes = Array.isArray(s.classes)
          ? s.classes.map((x) => x.name || x).join(", ")
          : s.class_name || "—";
        const status = (s.status || "").toLowerCase();
        const statusClass = status === "active" ? "success" : status === "on_leave" ? "warning" : "secondary";

        return `
          <tr>
            <td>${start + i + 1}</td>
            <td><span class="fw-semibold">${this.esc(s.staff_no || "—")}</span></td>
            <td>
              <div class="d-flex align-items-center">
                <div class="teacher-avatar">${this.esc((s.first_name || "?")[0])}</div>
                <div class="ms-2">
                  <div class="fw-semibold">${this.esc(name)}</div>
                  <small class="text-muted">${this.esc(s.email || "")}</small>
                </div>
              </div>
            </td>
            <td>${this.esc(dept)}</td>
            <td><small>${this.esc(subjects)}</small></td>
            <td><small>${this.esc(classes)}</small></td>
            <td><span class="badge bg-${statusClass}">${this.esc(s.status || "unknown")}</span></td>
            <td>
              <div class="action-dropdown dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                  <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="#" data-kw-onclick="manageTeachersController.viewTeacher(${s.id}); return false;"><i class="bi bi-eye me-2"></i>View</a></li>
                  <li><a class="dropdown-item" href="#" data-kw-onclick="manageTeachersController.showEditForm(${s.id}); return false;"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item text-danger" href="#" data-kw-onclick="manageTeachersController.deleteTeacher(${s.id}); return false;"><i class="bi bi-trash me-2"></i>Delete</a></li>
                </ul>
              </div>
            </td>
          </tr>`;
      })
      .join("");

    this.updatePaginationInfo(start + 1, end, teachers.length);
    this.renderPagination(teachers.length);
  },

  updatePaginationInfo: function (from, to, total) {
    const fromEl = document.getElementById("showingFrom");
    const toEl = document.getElementById("showingTo");
    const totalEl = document.getElementById("totalRecords");
    if (fromEl) fromEl.textContent = total > 0 ? from : 0;
    if (toEl) toEl.textContent = to;
    if (totalEl) totalEl.textContent = total;
  },

  renderPagination: function (total) {
    const container = document.getElementById("teachersPagination");
    if (!container) return;

    const totalPages = Math.ceil(total / this.pagination.limit);
    if (totalPages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = `<li class="page-item ${this.pagination.page <= 1 ? "disabled" : ""}">
      <a class="page-link" href="#" data-kw-onclick="manageTeachersController.goToPage(${this.pagination.page - 1}); return false;">&laquo;</a></li>`;

    for (let i = 1; i <= totalPages; i++) {
      if (totalPages > 7 && i > 3 && i < totalPages - 1 && Math.abs(i - this.pagination.page) > 1) {
        if (i === 4 || i === totalPages - 2) {
          html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        continue;
      }
      html += `<li class="page-item ${i === this.pagination.page ? "active" : ""}">
        <a class="page-link" href="#" data-kw-onclick="manageTeachersController.goToPage(${i}); return false;">${i}</a></li>`;
    }

    html += `<li class="page-item ${this.pagination.page >= totalPages ? "disabled" : ""}">
      <a class="page-link" href="#" data-kw-onclick="manageTeachersController.goToPage(${this.pagination.page + 1}); return false;">&raquo;</a></li>`;

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
    document.getElementById("teacherFormModalLabel").innerHTML =
      '<i class="bi bi-mortarboard me-2"></i>Add Teacher';
    const form = document.getElementById("teacherForm");
    if (form) form.reset();
    document.getElementById("formTeacherId").value = "";

    const summary = document.getElementById("currentAssignmentsSummary");
    if (summary) summary.style.display = "none";

    this.populateMultiSelect("formSubjectAssignments", this.learningAreas, "name", "id");
    this.populateMultiSelect("formClassAssignments", this.classes, "name", "id");

    const firstTab = document.getElementById("tab-personal");
    if (firstTab) new bootstrap.Tab(firstTab).show();

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("teacherFormModal"));
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
      document.getElementById("teacherFormModalLabel").innerHTML =
        '<i class="bi bi-mortarboard me-2"></i>Edit Teacher';
      document.getElementById("formTeacherId").value = id;

      this.setVal("formFirstName", staff.first_name);
      this.setVal("formLastName", staff.last_name);
      this.setVal("formEmail", staff.email);
      this.setVal("formPhone", staff.phone);
      this.setVal("formGender", staff.gender);
      this.setVal("formDob", staff.date_of_birth);
      this.setVal("formMaritalStatus", staff.marital_status);
      this.setVal("formAddress", staff.address);

      this.setVal("formTscNo", staff.tsc_no);
      this.setVal("formStaffNo", staff.staff_no);
      this.setVal("formDepartment", staff.department_id);
      this.setVal("formPosition", staff.position);
      this.setVal("formEmploymentDate", staff.employment_date);
      this.setVal("formContractType", staff.contract_type);
      this.setVal("formQualifications", staff.qualifications);

      this.setVal("formKraPin", staff.kra_pin);
      this.setVal("formNhifNo", staff.nhif_no);
      this.setVal("formNssfNo", staff.nssf_no);
      this.setVal("formBankAccount", staff.bank_account);

      this.populateMultiSelect("formSubjectAssignments", this.learningAreas, "name", "id");
      const subjectIds = (staff.subjects || staff.subject_ids || []).map(
        (s) => (typeof s === "object" ? s.id || s.learning_area_id : s),
      );
      this.setMultiSelectValues("formSubjectAssignments", subjectIds);

      this.populateMultiSelect("formClassAssignments", this.classes, "name", "id");
      const classIds = (staff.classes || staff.class_ids || []).map(
        (c) => (typeof c === "object" ? c.id || c.class_id : c),
      );
      this.setMultiSelectValues("formClassAssignments", classIds);

      const summary = document.getElementById("currentAssignmentsSummary");
      const list = document.getElementById("assignmentsList");
      if (summary && list) {
        const allAssigned = [...subjectIds, ...classIds];
        if (allAssigned.length > 0) {
          summary.style.display = "block";
          const items = [];
          subjectIds.forEach((sid) => {
            const la = this.learningAreas.find((l) => String(l.id) === String(sid));
            items.push(`<li class="list-group-item"><i class="bi bi-book me-2 text-success"></i>${this.esc(la?.name || sid)}</li>`);
          });
          classIds.forEach((cid) => {
            const cls = this.classes.find((c) => String(c.id) === String(cid));
            items.push(`<li class="list-group-item"><i class="bi bi-building me-2 text-primary"></i>${this.esc(cls?.name || cid)}</li>`);
          });
          list.innerHTML = items.join("");
        } else {
          summary.style.display = "none";
        }
      }

      const firstTab = document.getElementById("tab-personal");
      if (firstTab) new bootstrap.Tab(firstTab).show();

      const modal = bootstrap.Modal.getOrCreateInstance(
        document.getElementById("teacherFormModal"),
      );
      modal.show();
    } catch (error) {
      console.error("Error loading teacher:", error);
      this.toast("Failed to load teacher details", "danger");
    }
  },

  saveTeacher: async function (e) {
    if (e) e.preventDefault();

    const firstName = document.getElementById("formFirstName")?.value?.trim();
    const lastName = document.getElementById("formLastName")?.value?.trim();

    if (!firstName || !lastName) {
      this.toast("First name and last name are required", "danger");
      return;
    }

    const deptVal = document.getElementById("formDepartment")?.value;

    const subjectSelect = document.getElementById("formSubjectAssignments");
    const subjectIds = subjectSelect
      ? Array.from(subjectSelect.selectedOptions).map((o) => o.value)
      : [];

    const classSelect = document.getElementById("formClassAssignments");
    const classIds = classSelect
      ? Array.from(classSelect.selectedOptions).map((o) => o.value)
      : [];

    const data = {
      first_name: firstName,
      last_name: lastName,
      email: document.getElementById("formEmail")?.value?.trim() || null,
      phone: document.getElementById("formPhone")?.value?.trim() || null,
      gender: document.getElementById("formGender")?.value || null,
      date_of_birth: document.getElementById("formDob")?.value || null,
      marital_status:
        document.getElementById("formMaritalStatus")?.value || null,
      address: document.getElementById("formAddress")?.value?.trim() || null,
      tsc_no: document.getElementById("formTscNo")?.value?.trim() || null,
      department_id: deptVal || null,
      position:
        document.getElementById("formPosition")?.value?.trim() || "Teacher",
      employment_date:
        document.getElementById("formEmploymentDate")?.value || null,
      contract_type:
        document.getElementById("formContractType")?.value || "permanent",
      qualifications:
        document.getElementById("formQualifications")?.value?.trim() || null,
      kra_pin: document.getElementById("formKraPin")?.value?.trim() || null,
      nhif_no: document.getElementById("formNhifNo")?.value?.trim() || null,
      nssf_no: document.getElementById("formNssfNo")?.value?.trim() || null,
      bank_account:
        document.getElementById("formBankAccount")?.value?.trim() || null,
      subject_ids: subjectIds,
      class_ids: classIds,
      staff_type: "teaching",
    };

    try {
      const saveBtn = document.getElementById("btnSaveTeacher");
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving...';
      }

      if (this.editingId) {
        await window.API.apiCall(`/staff/${this.editingId}`, "PUT", data);
        this.toast("Teacher updated successfully!");
      } else {
        await window.API.apiCall("/staff", "POST", data);
        this.toast("Teacher added successfully!");
      }

      bootstrap.Modal.getInstance(
        document.getElementById("teacherFormModal"),
      )?.hide();
      await this.loadTeachers();
      await this.loadStats();
    } catch (error) {
      console.error("Error saving teacher:", error);
      this.toast(error.message || "Failed to save teacher", "danger");
    } finally {
      const saveBtn = document.getElementById("btnSaveTeacher");
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Save Teacher';
      }
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

      this.viewingId = id;
      const name = `${s.first_name || ""} ${s.last_name || ""}`.trim() || "—";
      const initials = `${(s.first_name || "?")[0]}${(s.last_name || "?")[0]}`.toUpperCase();
      const status = (s.status || "").toLowerCase();

      document.getElementById("viewAvatar").textContent = initials;
      document.getElementById("viewFullName").textContent = name;
      document.getElementById("viewStaffNo").textContent = s.staff_no || "—";
      document.getElementById("viewPosition").textContent = s.position || "—";
      document.getElementById("viewDepartment").textContent =
        s.department_name || s.department || "—";

      const badge = document.getElementById("viewStatusBadge");
      if (badge) {
        badge.textContent = s.status || "—";
        badge.className =
          "badge " +
          (status === "active"
            ? "bg-success"
            : status === "on_leave"
              ? "bg-warning text-dark"
              : "bg-secondary");
      }

      document.getElementById("viewFirstName").textContent = s.first_name || "—";
      document.getElementById("viewLastName").textContent = s.last_name || "—";
      document.getElementById("viewEmail").textContent = s.email || "—";
      document.getElementById("viewPhone").textContent = s.phone || "—";
      document.getElementById("viewGender").textContent = s.gender || "—";
      document.getElementById("viewDob").textContent = s.date_of_birth || "—";
      document.getElementById("viewMaritalStatus").textContent =
        s.marital_status || "—";
      document.getElementById("viewAddress").textContent = s.address || "—";

      document.getElementById("viewTscNo").textContent = s.tsc_no || "—";
      document.getElementById("viewStaffNoDetail").textContent =
        s.staff_no || "—";
      document.getElementById("viewDeptDetail").textContent =
        s.department_name || "—";
      document.getElementById("viewPositionDetail").textContent =
        s.position || "—";
      document.getElementById("viewEmploymentDate").textContent =
        s.employment_date || "—";
      document.getElementById("viewContractType").textContent =
        s.contract_type || "—";
      document.getElementById("viewQualifications").textContent =
        s.qualifications || "—";

      document.getElementById("viewKraPin").textContent = s.kra_pin || "—";
      document.getElementById("viewNhifNo").textContent = s.nhif_no || "—";
      document.getElementById("viewNssfNo").textContent = s.nssf_no || "—";
      document.getElementById("viewBankAccount").textContent =
        s.bank_account || "—";

      const subjectContainer = document.getElementById("viewSubjectAssignments");
      if (subjectContainer) {
        const subjects = s.subjects || [];
        if (Array.isArray(subjects) && subjects.length > 0) {
          subjectContainer.innerHTML = subjects
            .map(
              (sub) =>
                `<span class="badge bg-success bg-opacity-10 text-success me-1 mb-1">${this.esc(sub.name || sub)}</span>`,
            )
            .join("");
        } else {
          subjectContainer.innerHTML =
            '<p class="text-muted">No subject assignments found.</p>';
        }
      }

      const classContainer = document.getElementById("viewClassAssignments");
      if (classContainer) {
        const classes = s.classes || [];
        if (Array.isArray(classes) && classes.length > 0) {
          classContainer.innerHTML = classes
            .map(
              (cls) =>
                `<span class="badge bg-primary bg-opacity-10 text-primary me-1 mb-1">${this.esc(cls.name || cls)}</span>`,
            )
            .join("");
        } else {
          classContainer.innerHTML =
            '<p class="text-muted">No class assignments found.</p>';
        }
      }

      const modal = bootstrap.Modal.getOrCreateInstance(
        document.getElementById("viewTeacherModal"),
      );
      modal.show();
    } catch (error) {
      console.error("Error viewing teacher:", error);
      this.toast("Failed to load teacher details", "danger");
    }
  },

  deleteTeacher: function (id) {
    const teacher = this.allTeachers.find((t) => t.id === id);
    if (!teacher) return;

    document.getElementById("deleteTeacherId").value = id;
    document.getElementById("deleteTeacherName").textContent =
      `${teacher.first_name || ""} ${teacher.last_name || ""}`.trim() ||
      "—";

    const modal = bootstrap.Modal.getOrCreateInstance(
      document.getElementById("deleteTeacherModal"),
    );
    modal.show();
  },

  confirmDelete: async function () {
    const id = document.getElementById("deleteTeacherId")?.value;
    if (!id) return;

    try {
      const btn = document.getElementById("btnConfirmDelete");
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Deleting...';
      }

      await window.API.apiCall(`/staff/${id}`, "DELETE");

      bootstrap.Modal.getInstance(
        document.getElementById("deleteTeacherModal"),
      )?.hide();
      this.toast("Teacher deleted successfully!");
      await this.loadTeachers();
      await this.loadStats();
    } catch (error) {
      console.error("Error deleting teacher:", error);
      this.toast("Failed to delete teacher", "danger");
    } finally {
      const btn = document.getElementById("btnConfirmDelete");
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash me-1"></i>Delete';
      }
    }
  },

  editFromView: function () {
    const id = this.viewingId;
    if (!id) return;
    bootstrap.Modal.getInstance(
      document.getElementById("viewTeacherModal"),
    )?.hide();
    setTimeout(() => this.showEditForm(id), 400);
  },

  printProfile: function () {
    if (!this.viewingId) return;
    if (window.PrintManager) {
      window.PrintManager.printElement("viewTeacherBody", {
        title: "Teacher Profile",
        subtitle: new Date().toLocaleDateString(),
      });
    } else {
      window.print();
    }
  },

  // ── Export / Print ───────────────────────────────────
  exportTeachers: function () {
    const teachers = this.filteredTeachers;
    if (!teachers.length) {
      this.toast("No data to export", "warning");
      return;
    }

    let csv =
      "Staff No,First Name,Last Name,Email,Phone,Department,Subjects,Position,Status\n";
    teachers.forEach((s) => {
      const subjects = Array.isArray(s.subjects)
        ? s.subjects.map((x) => x.name || x).join("; ")
        : s.subject_name || "";
      csv += [
        s.staff_no || "",
        s.first_name || "",
        s.last_name || "",
        s.email || "",
        s.phone || "",
        s.department_name || "",
        subjects,
        s.position || "",
        s.status || "",
      ]
        .map((v) => `"${String(v).replace(/"/g, '""')}"`)
        .join(",");
      csv += "\n";
    });

    if (window.KingswayFileLifecycle) {
      KingswayFileLifecycle.exportText(
        csv,
        `teachers_export_${new Date().toISOString().split("T")[0]}.csv`,
        "text/csv",
      );
    } else {
      const blob = new Blob([csv], { type: "text/csv" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `teachers_export_${new Date().toISOString().split("T")[0]}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    }
  },

  printTeachers: function () {
    const teachers = this.filteredTeachers;
    if (!teachers.length) {
      this.toast("No teacher data to print", "warning");
      return;
    }

    if (window.PrintManager) {
      window.PrintManager.printTable({
        title: "Teachers List",
        subtitle: "School Teaching Staff",
        columns: [
          { key: "staff_no", label: "Staff No" },
          { key: "full_name", label: "Teacher Name" },
          { key: "department_name", label: "Department" },
          { key: "position", label: "Position" },
          { key: "phone", label: "Phone" },
          { key: "email", label: "Email" },
          { key: "status", label: "Status" },
        ],
        rows: teachers.map((s) => ({
          ...s,
          full_name: `${s.first_name || ""} ${s.last_name || ""}`.trim(),
        })),
        summary: {
          "Total Teachers": teachers.length,
          "Generated Date": new Date().toLocaleDateString(),
        },
        orientation: "landscape",
        paperSize: "A4",
      });
    } else {
      window.print();
    }
  },
};

// ── Bootstrap ────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  manageTeachersController.init();
});

window.manageTeachersController = manageTeachersController;
