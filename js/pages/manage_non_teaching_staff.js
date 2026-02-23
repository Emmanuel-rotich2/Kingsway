/**
 * Manage Non-Teaching Staff Page Controller
 * Full CRUD for non-teaching staff using api.js endpoints
 */

const manageNonTeachingStaffController = {
  allStaff: [],
  filteredStaff: [],
  departments: [],
  searchTerm: "",
  departmentFilter: "",
  statusFilter: "",
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

  notify(msg, type = "info") {
    if (window.API?.showNotification) {
      window.API.showNotification(msg, type);
    } else {
      alert(msg);
    }
  },

  // ── Init ─────────────────────────────────────────────
  init: async function () {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    await Promise.all([this.loadStaff(), this.loadDepartments()]);
  },

  // ── Data Loading ─────────────────────────────────────
  loadStaff: async function () {
    try {
      const response = await window.API.staff.index();
      const all = this.extractList(response);
      // Filter to non-teaching only
      this.allStaff = all.filter(
        (s) =>
          (s.staff_type || "").toLowerCase() === "non-teaching" ||
          (s.staff_type || "").toLowerCase() === "non_teaching" ||
          (s.type || "").toLowerCase() === "non-teaching",
      );
      // If server doesn't distinguish, show all (fallback)
      if (this.allStaff.length === 0 && all.length > 0) {
        this.allStaff = all;
      }
      this.applyFilters();
    } catch (error) {
      console.error("Error loading non-teaching staff:", error);
      this.renderTable([]);
    }
  },

  loadDepartments: async function () {
    try {
      const response = await window.API.staff.getDepartments();
      this.departments = response?.data || response || [];
      this.populateDepartmentDropdowns();
    } catch (error) {
      console.warn("Error loading departments:", error);
    }
  },

  populateDepartmentDropdowns: function () {
    const selects = [
      document.getElementById("departmentFilter"),
      document.getElementById("departmentSelect"),
    ];
    selects.forEach((el) => {
      if (!el) return;
      const isFilter = el.id === "departmentFilter";
      el.innerHTML = isFilter
        ? '<option value="">-- All Departments --</option>'
        : '<option value="">-- Select Department --</option>';
      (Array.isArray(this.departments) ? this.departments : []).forEach((d) => {
        const name = d.name || d.department_name || d;
        const id = d.id || d.department_id || name;
        el.innerHTML += `<option value="${id}">${name}</option>`;
      });
    });
  },

  // ── Filtering ────────────────────────────────────────
  search: function (term) {
    this.searchTerm = (term || "").toLowerCase();
    this.applyFilters();
  },

  filterByDepartment: function (dept) {
    this.departmentFilter = dept || "";
    this.applyFilters();
  },

  filterByStatus: function (status) {
    this.statusFilter = (status || "").toLowerCase();
    this.applyFilters();
  },

  applyFilters: function () {
    let list = [...this.allStaff];

    if (this.searchTerm) {
      list = list.filter((s) => {
        const name =
          `${s.first_name || ""} ${s.last_name || ""} ${s.name || ""}`.toLowerCase();
        const staffNo = (s.staff_no || "").toLowerCase();
        const email = (s.email || "").toLowerCase();
        return (
          name.includes(this.searchTerm) ||
          staffNo.includes(this.searchTerm) ||
          email.includes(this.searchTerm)
        );
      });
    }

    if (this.departmentFilter) {
      list = list.filter((s) => {
        const deptId = String(s.department_id || s.department || "");
        return deptId === String(this.departmentFilter);
      });
    }

    if (this.statusFilter) {
      list = list.filter(
        (s) => (s.status || "").toLowerCase() === this.statusFilter,
      );
    }

    this.filteredStaff = list;
    this.renderTable(list);
  },

  // ── Render Table ─────────────────────────────────────
  renderTable: function (staff) {
    const container = document.getElementById("staffTableContainer");
    if (!container) return;

    if (!staff || staff.length === 0) {
      container.innerHTML =
        '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No non-teaching staff records found.</div>';
      return;
    }

    let html = `
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-striped">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Staff No</th>
              <th>Name</th>
              <th>Email</th>
              <th>Department</th>
              <th>Role/Position</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>`;

    staff.forEach((s, i) => {
      const name =
        s.name || `${s.first_name || ""} ${s.last_name || ""}`.trim();
      const dept = s.department_name || s.department || "N/A";
      const role = s.position || s.role || s.job_title || "N/A";
      const statusClass =
        (s.status || "").toLowerCase() === "active"
          ? "bg-success"
          : "bg-secondary";

      html += `
            <tr>
              <td>${i + 1}</td>
              <td>${this.esc(s.staff_no || "—")}</td>
              <td>${this.esc(name)}</td>
              <td>${this.esc(s.email || "—")}</td>
              <td>${this.esc(dept)}</td>
              <td>${this.esc(role)}</td>
              <td><span class="badge ${statusClass}">${this.esc(s.status || "Unknown")}</span></td>
              <td>
                <button class="btn btn-sm btn-outline-info me-1" onclick="manageNonTeachingStaffController.viewStaff(${s.id})" title="View"><i class="bi bi-eye"></i></button>
                <button class="btn btn-sm btn-outline-warning me-1" onclick="manageNonTeachingStaffController.showEditForm(${s.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="manageNonTeachingStaffController.deleteStaff(${s.id})" title="Delete"><i class="bi bi-trash"></i></button>
              </td>
            </tr>`;
    });

    html += `</tbody></table></div>`;
    container.innerHTML = html;
  },

  esc: function (str) {
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

  // ── CRUD Operations ──────────────────────────────────
  showCreateForm: function () {
    this.editingId = null;
    document.getElementById("staffModalLabel").textContent =
      "Add Non-Teaching Staff";
    document.getElementById("staffId").value = "";
    document.getElementById("staffForm").reset();
    const modal = new bootstrap.Modal(document.getElementById("staffModal"));
    modal.show();
  },

  showEditForm: async function (id) {
    try {
      const response = await window.API.staff.get(id);
      const staff = response?.data || response;
      if (!staff) {
        this.notify("Staff record not found", "danger");
        return;
      }
      this.editingId = id;
      document.getElementById("staffModalLabel").textContent = "Edit Staff";
      document.getElementById("staffId").value = id;
      document.getElementById("firstName").value = staff.first_name || "";
      document.getElementById("lastName").value = staff.last_name || "";
      document.getElementById("email").value = staff.email || "";
      document.getElementById("role").value =
        staff.position || staff.role || "";
      document.getElementById("statusSelect").value = staff.status || "active";

      // Set department
      const deptEl = document.getElementById("departmentSelect");
      if (deptEl) {
        deptEl.value = staff.department_id || staff.department || "";
      }

      const modal = new bootstrap.Modal(document.getElementById("staffModal"));
      modal.show();
    } catch (error) {
      console.error("Error loading staff for edit:", error);
      this.notify("Failed to load staff details", "danger");
    }
  },

  saveStaff: async function (e) {
    if (e) e.preventDefault();

    const data = {
      first_name: document.getElementById("firstName").value.trim(),
      last_name: document.getElementById("lastName").value.trim(),
      email: document.getElementById("email").value.trim(),
      department_id: document.getElementById("departmentSelect").value,
      position: document.getElementById("role").value.trim(),
      status: document.getElementById("statusSelect").value,
      staff_type: "non-teaching",
    };

    if (!data.first_name || !data.last_name) {
      this.notify("First name and last name are required", "warning");
      return;
    }

    try {
      if (this.editingId) {
        await window.API.staff.update(this.editingId, data);
        this.notify("Staff updated successfully!", "success");
      } else {
        await window.API.staff.create(data);
        this.notify("Staff created successfully!", "success");
      }

      bootstrap.Modal.getInstance(
        document.getElementById("staffModal"),
      )?.hide();
      await this.loadStaff();
    } catch (error) {
      console.error("Error saving staff:", error);
      this.notify("Failed to save staff record", "danger");
    }
  },

  viewStaff: async function (id) {
    try {
      const response = await window.API.staff.get(id);
      const s = response?.data || response;
      if (!s) {
        this.notify("Staff not found", "danger");
        return;
      }

      const name =
        s.name || `${s.first_name || ""} ${s.last_name || ""}`.trim();
      const html = `
        <div class="row">
          <div class="col-md-6">
            <p><strong>Staff No:</strong> ${this.esc(s.staff_no || "—")}</p>
            <p><strong>Name:</strong> ${this.esc(name)}</p>
            <p><strong>Email:</strong> ${this.esc(s.email || "—")}</p>
            <p><strong>Phone:</strong> ${this.esc(s.phone || "—")}</p>
          </div>
          <div class="col-md-6">
            <p><strong>Department:</strong> ${this.esc(s.department_name || s.department || "—")}</p>
            <p><strong>Position:</strong> ${this.esc(s.position || s.role || "—")}</p>
            <p><strong>Status:</strong> <span class="badge ${(s.status || "").toLowerCase() === "active" ? "bg-success" : "bg-secondary"}">${this.esc(s.status || "Unknown")}</span></p>
            <p><strong>Join Date:</strong> ${this.esc(s.date_joined || s.created_at || "—")}</p>
          </div>
        </div>`;

      document.getElementById("staffModalLabel").textContent = "Staff Details";
      // Replace modal body temporarily
      const modalBody = document.querySelector("#staffModal .modal-body");
      const formEl = document.getElementById("staffForm");
      if (formEl) formEl.style.display = "none";
      const viewDiv = document.createElement("div");
      viewDiv.id = "staffViewContent";
      viewDiv.innerHTML = html;
      modalBody.parentNode.insertBefore(viewDiv, modalBody.nextSibling);

      const modal = new bootstrap.Modal(document.getElementById("staffModal"));
      modal.show();

      // Clean up when modal closes
      document
        .getElementById("staffModal")
        .addEventListener("hidden.bs.modal", function cleanup() {
          const vc = document.getElementById("staffViewContent");
          if (vc) vc.remove();
          if (formEl) formEl.style.display = "";
          document
            .getElementById("staffModal")
            .removeEventListener("hidden.bs.modal", cleanup);
        });
    } catch (error) {
      console.error("Error viewing staff:", error);
      this.notify("Failed to load staff details", "danger");
    }
  },

  deleteStaff: async function (id) {
    if (!confirm("Are you sure you want to delete this staff member?")) return;

    try {
      await window.API.staff.delete(id);
      this.notify("Staff deleted successfully!", "success");
      await this.loadStaff();
    } catch (error) {
      console.error("Error deleting staff:", error);
      this.notify("Failed to delete staff", "danger");
    }
  },
};

// ── Bootstrap ────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  manageNonTeachingStaffController.init();

  // Bind form submit
  const form = document.getElementById("staffForm");
  if (form) {
    form.addEventListener("submit", (e) =>
      manageNonTeachingStaffController.saveStaff(e)
    );
  }
});
