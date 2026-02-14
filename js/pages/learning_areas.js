/**
 * Learning Areas Controller
 * Page: learning_areas.php
 * Manages subjects, teacher assignments, curriculum (CBC), schemes of work
 */
const LearningAreasController = {
  state: {
    subjects: [],
    assignments: [],
    schemesOfWork: [],
    classes: [],
    teachers: [],
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    this.bindEvents();
    await this.loadData();
  },

  bindEvents() {
    // Search
    const search = document.getElementById("searchSubjects");
    if (search)
      search.addEventListener("input", (e) =>
        this.filterSubjects(e.target.value),
      );

    // Add subject modal form
    const addForm = document.querySelector("#addSubjectModal form");
    if (addForm) {
      addForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.saveSubject();
      });
    }

    // Tab switching to lazy-load data
    document
      .querySelectorAll('#learningAreasTabs [data-bs-toggle="tab"]')
      .forEach((tab) => {
        tab.addEventListener("shown.bs.tab", (e) => {
          const target = e.target.getAttribute("data-bs-target");
          if (target === "#teacherAssignments") this.loadAssignments();
          if (target === "#curriculum") this.loadCurriculum();
          if (target === "#schemesOfWork") this.loadSchemesOfWork();
        });
      });

    // Filter dropdowns in assignments tab
    const filterClass = document.getElementById("filterByClass");
    const filterSubject = document.getElementById("filterBySubject");
    if (filterClass)
      filterClass.addEventListener("change", () =>
        this.renderAssignmentsTable(),
      );
    if (filterSubject)
      filterSubject.addEventListener("change", () =>
        this.renderAssignmentsTable(),
      );
  },

  async loadData() {
    try {
      this.showTableLoading("#subjectsTable");
      const [subjectsRes, classesRes] = await Promise.all([
        window.API.academic.listLearningAreas(),
        window.API.academic.listClasses(),
      ]);

      if (subjectsRes?.success) this.state.subjects = subjectsRes.data || [];
      if (classesRes?.success) this.state.classes = classesRes.data || [];

      this.updateStats();
      this.renderSubjectsTable();
      this.populateFilters();
    } catch (error) {
      console.error("Error loading learning areas:", error);
      this.showNotification("Failed to load subjects", "error");
    }
  },

  updateStats() {
    const subjects = this.state.subjects;
    const active = subjects.filter((s) => s.status === "active" || !s.status);
    const teachersAssigned = new Set(
      subjects.filter((s) => s.teacher_id).map((s) => s.teacher_id),
    ).size;
    const pendingSow = subjects.filter(
      (s) => !s.scheme_of_work || s.sow_status === "pending",
    ).length;

    this.setText("#totalSubjects", subjects.length);
    this.setText("#activeSubjects", active.length);
    this.setText("#teachersAssigned", teachersAssigned);
    this.setText("#pendingSow", pendingSow);
  },

  renderSubjectsTable() {
    const tbody = document.querySelector("#subjectsTable tbody");
    if (!tbody) return;

    if (this.state.subjects.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">No subjects found</td></tr>';
      return;
    }

    tbody.innerHTML = this.state.subjects
      .map(
        (subject) => `
            <tr>
                <td><code>${this.escapeHtml(subject.code || subject.subject_code || "--")}</code></td>
                <td><strong>${this.escapeHtml(subject.name || subject.subject_name || "")}</strong></td>
                <td>${this.escapeHtml(subject.category || subject.department || "--")}</td>
                <td>${subject.class_count || subject.classes?.length || "--"}</td>
                <td>${subject.teacher_count || "--"}</td>
                <td>
                    <span class="badge bg-${subject.status === "active" || !subject.status ? "success" : "secondary"}">
                        ${subject.status || "active"}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="LearningAreasController.viewSubject(${subject.id})" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="LearningAreasController.editSubject(${subject.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="LearningAreasController.deleteSubject(${subject.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`,
      )
      .join("");
  },

  filterSubjects(query) {
    const q = query.toLowerCase();
    const rows = document.querySelectorAll("#subjectsTable tbody tr");
    rows.forEach((row) => {
      row.style.display = row.textContent.toLowerCase().includes(q)
        ? ""
        : "none";
    });
  },

  async loadAssignments() {
    try {
      this.showTableLoading("#assignmentsTable");
      const res = await window.API.academic.getCustom({
        action: "subject-teacher-assignments",
      });
      if (res?.success) {
        this.state.assignments = res.data || [];
      } else {
        // Build assignments from subjects data
        this.state.assignments = this.state.subjects
          .filter((s) => s.teacher_name || s.teacher_id)
          .map((s) => ({
            teacher_name: s.teacher_name,
            subject_name: s.name || s.subject_name,
            class_name: s.class_name || "",
            lessons_per_week: s.lessons_per_week || "--",
            status: "active",
          }));
      }
      this.renderAssignmentsTable();
    } catch (error) {
      console.error("Error loading assignments:", error);
    }
  },

  renderAssignmentsTable() {
    const tbody = document.querySelector("#assignmentsTable tbody");
    if (!tbody) return;

    const classFilter = document.getElementById("filterByClass")?.value;
    const subjectFilter = document.getElementById("filterBySubject")?.value;

    let filtered = this.state.assignments;
    if (classFilter)
      filtered = filtered.filter(
        (a) => a.class_id == classFilter || a.class_name === classFilter,
      );
    if (subjectFilter)
      filtered = filtered.filter(
        (a) =>
          a.subject_id == subjectFilter || a.subject_name === subjectFilter,
      );

    if (filtered.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="6" class="text-center text-muted py-4">No assignments found</td></tr>';
      return;
    }

    tbody.innerHTML = filtered
      .map(
        (a) => `
            <tr>
                <td>${this.escapeHtml(a.teacher_name || "")}</td>
                <td>${this.escapeHtml(a.subject_name || "")}</td>
                <td>${this.escapeHtml(a.class_name || "")}</td>
                <td>${a.lessons_per_week || "--"}</td>
                <td><span class="badge bg-${a.status === "active" ? "success" : "secondary"}">${a.status || "active"}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-danger" onclick="LearningAreasController.removeAssignment(${a.id})">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>`,
      )
      .join("");
  },

  async loadCurriculum() {
    const container = document.getElementById("curriculumTree");
    if (!container) return;

    try {
      const res = await window.API.academic.listCurriculumUnits();
      if (res?.success && res.data?.length > 0) {
        const units = res.data;
        container.innerHTML = this.buildCurriculumTree(units);
      } else {
        container.innerHTML =
          '<p class="text-muted">No curriculum data available. Add subjects and curriculum units to get started.</p>';
      }
    } catch (error) {
      console.error("Error loading curriculum:", error);
      container.innerHTML =
        '<p class="text-danger">Failed to load curriculum data</p>';
    }
  },

  buildCurriculumTree(units) {
    // Group by subject
    const grouped = {};
    units.forEach((u) => {
      const key = u.subject_name || u.learning_area || "General";
      if (!grouped[key]) grouped[key] = [];
      grouped[key].push(u);
    });

    return Object.entries(grouped)
      .map(
        ([subject, items]) => `
            <div class="mb-3">
                <h6 class="text-primary"><i class="fas fa-book me-1"></i>${this.escapeHtml(subject)}</h6>
                <ul class="list-group list-group-flush">
                    ${items
                      .map(
                        (item) => `
                        <li class="list-group-item">
                            <strong>${this.escapeHtml(item.name || item.strand || "")}</strong>
                            ${item.description ? `<br><small class="text-muted">${this.escapeHtml(item.description)}</small>` : ""}
                        </li>`,
                      )
                      .join("")}
                </ul>
            </div>`,
      )
      .join("");
  },

  async loadSchemesOfWork() {
    const tbody = document.querySelector("#sowTable tbody");
    if (!tbody) return;

    try {
      this.showTableLoading("#sowTable");
      const res = await window.API.academic.getSchemeOfWork();
      if (res?.success) {
        this.state.schemesOfWork = res.data || [];
        if (this.state.schemesOfWork.length === 0) {
          tbody.innerHTML =
            '<tr><td colspan="6" class="text-center text-muted py-4">No schemes of work uploaded</td></tr>';
          return;
        }
        tbody.innerHTML = this.state.schemesOfWork
          .map(
            (sow) => `
                    <tr>
                        <td>${this.escapeHtml(sow.subject_name || "")}</td>
                        <td>${this.escapeHtml(sow.class_name || sow.grade || "")}</td>
                        <td>${this.escapeHtml(sow.term || "")}</td>
                        <td>${this.escapeHtml(sow.teacher_name || "")}</td>
                        <td><span class="badge bg-${sow.status === "approved" ? "success" : sow.status === "pending" ? "warning" : "secondary"}">${sow.status || "pending"}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="LearningAreasController.viewSow(${sow.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>`,
          )
          .join("");
      }
    } catch (error) {
      console.error("Error loading SOW:", error);
      tbody.innerHTML =
        '<tr><td colspan="6" class="text-center text-danger">Failed to load schemes of work</td></tr>';
    }
  },

  async saveSubject() {
    const modal = document.getElementById("addSubjectModal");
    const form = modal?.querySelector("form");
    if (!form) return;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
      const editId = form.dataset.editId;
      let res;
      if (editId) {
        res = await window.API.academic.updateLearningArea(editId, data);
      } else {
        res = await window.API.academic.createLearningArea(data);
      }

      if (res?.success) {
        this.showNotification(
          editId ? "Subject updated" : "Subject added",
          "success",
        );
        bootstrap.Modal.getInstance(modal)?.hide();
        form.reset();
        delete form.dataset.editId;
        await this.loadData();
      } else {
        this.showNotification(res?.message || "Operation failed", "error");
      }
    } catch (error) {
      console.error("Error saving subject:", error);
      this.showNotification("Failed to save subject", "error");
    }
  },

  async viewSubject(id) {
    try {
      const res = await window.API.academic.getLearningArea(id);
      if (res?.success && res.data) {
        const s = res.data;
        const html = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Code:</strong> ${this.escapeHtml(s.code || "")}</p>
                            <p><strong>Name:</strong> ${this.escapeHtml(s.name || "")}</p>
                            <p><strong>Category:</strong> ${this.escapeHtml(s.category || "")}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> <span class="badge bg-success">${s.status || "active"}</span></p>
                            <p><strong>Teachers:</strong> ${s.teacher_count || "--"}</p>
                            <p><strong>Classes:</strong> ${s.class_count || "--"}</p>
                        </div>
                    </div>
                    ${s.description ? `<p><strong>Description:</strong> ${this.escapeHtml(s.description)}</p>` : ""}`;
        this.showModal("Subject Details", html);
      }
    } catch (error) {
      console.error("Error viewing subject:", error);
    }
  },

  async editSubject(id) {
    try {
      const res = await window.API.academic.getLearningArea(id);
      if (res?.success && res.data) {
        const s = res.data;
        const modal = document.getElementById("addSubjectModal");
        const form = modal?.querySelector("form");
        if (form) {
          form.dataset.editId = id;
          Object.entries(s).forEach(([key, val]) => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input && val) input.value = val;
          });
          new bootstrap.Modal(modal).show();
        }
      }
    } catch (error) {
      console.error("Error loading subject for edit:", error);
    }
  },

  async deleteSubject(id) {
    if (!confirm("Delete this subject? This action cannot be undone.")) return;
    try {
      const res = await window.API.academic.deleteLearningArea(id);
      if (res?.success) {
        this.showNotification("Subject deleted", "success");
        await this.loadData();
      } else {
        this.showNotification(res?.message || "Failed to delete", "error");
      }
    } catch (error) {
      console.error("Error deleting subject:", error);
    }
  },

  async removeAssignment(id) {
    if (!confirm("Remove this assignment?")) return;
    this.showNotification("Assignment removed", "info");
    this.state.assignments = this.state.assignments.filter((a) => a.id !== id);
    this.renderAssignmentsTable();
  },

  viewSow(id) {
    window.location.href = `/Kingsway/pages/schemes_of_work.php?id=${id}`;
  },

  populateFilters() {
    const classFilter = document.getElementById("filterByClass");
    const subjectFilter = document.getElementById("filterBySubject");

    if (classFilter) {
      classFilter.innerHTML =
        '<option value="">All Classes</option>' +
        this.state.classes
          .map(
            (c) =>
              `<option value="${c.id}">${this.escapeHtml(c.name || "")}</option>`,
          )
          .join("");
    }
    if (subjectFilter) {
      subjectFilter.innerHTML =
        '<option value="">All Subjects</option>' +
        this.state.subjects
          .map(
            (s) =>
              `<option value="${s.id}">${this.escapeHtml(s.name || s.subject_name || "")}</option>`,
          )
          .join("");
    }
  },

  // Utility
  setText(sel, val) {
    const el = document.querySelector(sel);
    if (el) el.textContent = val;
  },
  escapeHtml(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  },
  showTableLoading(tableId) {
    const tbody = document.querySelector(`${tableId} tbody`);
    if (tbody) {
      const cols =
        tbody.closest("table")?.querySelector("thead tr")?.children.length || 7;
      tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>`;
    }
  },
  showNotification(msg, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },
  showModal(title, bodyHtml) {
    let modal = document.getElementById("dynamicModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "dynamicModal";
      modal.className = "modal fade";
      modal.innerHTML = `<div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>`;
      document.body.appendChild(modal);
    }
    modal.querySelector(".modal-title").textContent = title;
    modal.querySelector(".modal-body").innerHTML = bodyHtml;
    new bootstrap.Modal(modal).show();
  },
};

document.addEventListener("DOMContentLoaded", () =>
  LearningAreasController.init(),
);
