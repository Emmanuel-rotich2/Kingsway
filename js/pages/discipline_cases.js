/**
 * Discipline Cases Controller
 * Page: discipline_cases.php
 * View and manage student discipline cases
 */
const DisciplineCasesController = {
  state: {
    cases: [],
    allCases: [],
    classes: [],
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
    const search = document.getElementById("searchStudent");
    if (search) search.addEventListener("input", () => this.applyFilters());

    const filterClass = document.getElementById("filterClass");
    if (filterClass)
      filterClass.addEventListener("change", () => this.applyFilters());

    const filterSeverity = document.getElementById("filterSeverity");
    if (filterSeverity)
      filterSeverity.addEventListener("change", () => this.applyFilters());
  },

  async loadData() {
    try {
      this.showTableLoading();
      const [casesRes, classesRes] = await Promise.all([
        window.API.students
          .get()
          .then((r) => r)
          .catch(() => null),
        window.API.academic.listClasses(),
      ]);

      // Try fetching discipline data via custom endpoint
      const disciplineRes = await window.API.academic
        .getCustom({ action: "discipline-cases" })
        .catch(() => null);

      if (disciplineRes?.success) {
        this.state.allCases = disciplineRes.data || [];
      }

      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
        this.populateClassFilter();
      }

      this.state.cases = [...this.state.allCases];
      this.updateStats();
      this.renderTable();
    } catch (error) {
      console.error("Error loading discipline cases:", error);
    }
  },

  updateStats() {
    const cases = this.state.allCases;
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalCases", cases.length);
    el(
      "openCases",
      cases.filter((c) => c.status === "open" || c.status === "pending").length,
    );
    el(
      "resolvedCases",
      cases.filter((c) => c.status === "resolved" || c.status === "closed")
        .length,
    );
  },

  applyFilters() {
    const search = document
      .getElementById("searchStudent")
      ?.value?.toLowerCase();
    const classId = document.getElementById("filterClass")?.value;
    const severity = document.getElementById("filterSeverity")?.value;

    let filtered = [...this.state.allCases];
    if (search)
      filtered = filtered.filter((c) =>
        (c.student_name || "").toLowerCase().includes(search),
      );
    if (classId) filtered = filtered.filter((c) => c.class_id == classId);
    if (severity) filtered = filtered.filter((c) => c.severity === severity);

    this.state.cases = filtered;
    this.renderTable();
  },

  renderTable() {
    const tbody = document.querySelector("#casesTable tbody, .table tbody");
    if (!tbody) return;

    if (this.state.cases.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center text-muted py-4">No discipline cases found</td></tr>';
      return;
    }

    tbody.innerHTML = this.state.cases
      .map((c) => {
        const severityColors = {
          minor: "info",
          moderate: "warning",
          major: "danger",
          critical: "dark",
        };
        const statusColors = {
          open: "warning",
          pending: "info",
          resolved: "success",
          closed: "secondary",
        };

        return `
            <tr>
                <td>${c.date || c.incident_date || "--"}</td>
                <td><strong>${this.escapeHtml(c.student_name || "")}</strong></td>
                <td>${this.escapeHtml(c.class_name || "")}</td>
                <td>${this.escapeHtml(c.offense || c.description || "")}</td>
                <td><span class="badge bg-${severityColors[c.severity] || "secondary"}">${c.severity || "--"}</span></td>
                <td>${this.escapeHtml(c.action_taken || c.punishment || "--")}</td>
                <td><span class="badge bg-${statusColors[c.status] || "secondary"}">${c.status || "open"}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="DisciplineCasesController.viewCase(${c.id})" title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-outline-success" onclick="DisciplineCasesController.resolveCase(${c.id})" title="Resolve"><i class="fas fa-check"></i></button>
                    </div>
                </td>
            </tr>`;
      })
      .join("");
  },

  async viewCase(id) {
    const c = this.state.allCases.find((x) => x.id == id);
    if (!c) return;
    this.showModal(
      "Discipline Case",
      `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Student:</strong> ${this.escapeHtml(c.student_name || "")}</p>
                    <p><strong>Class:</strong> ${this.escapeHtml(c.class_name || "")}</p>
                    <p><strong>Date:</strong> ${c.date || c.incident_date || "--"}</p>
                    <p><strong>Severity:</strong> <span class="badge bg-warning">${c.severity || "--"}</span></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Reported By:</strong> ${this.escapeHtml(c.reported_by || "--")}</p>
                    <p><strong>Status:</strong> <span class="badge bg-info">${c.status || "open"}</span></p>
                    <p><strong>Action Taken:</strong> ${this.escapeHtml(c.action_taken || "--")}</p>
                </div>
            </div>
            <hr>
            <p><strong>Description:</strong></p>
            <p>${this.escapeHtml(c.description || c.offense || "No description")}</p>
            ${c.parent_notified ? '<p class="text-success"><i class="fas fa-check me-1"></i>Parent notified</p>' : ""}`,
    );
  },

  async resolveCase(id) {
    const action = prompt("Enter resolution action:");
    if (!action) return;
    this.showNotification("Case resolved", "success");
    const c = this.state.allCases.find((x) => x.id == id);
    if (c) {
      c.status = "resolved";
      c.action_taken = action;
    }
    this.renderTable();
  },

  populateClassFilter() {
    const select = document.getElementById("filterClass");
    if (!select) return;
    select.innerHTML =
      '<option value="">All Classes</option>' +
      this.state.classes
        .map(
          (c) =>
            `<option value="${c.id}">${this.escapeHtml(c.name || "")}</option>`,
        )
        .join("");
  },

  showTableLoading() {
    const tbody = document.querySelector("#casesTable tbody, .table tbody");
    if (tbody)
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>';
  },

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
  DisciplineCasesController.init(),
);
