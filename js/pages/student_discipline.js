/**
 * Student Discipline Page Controller
 * Manages Student Discipline workflow using api.js
 */

const StudentDisciplineController = {
  data: {
    cases: [],
    students: [],
    classes: [],
    pagination: { page: 1, limit: 10, total: 0 },
    summary: {},
  },
  filters: {
    search: "",
    status: "",
    severity: "",
    class_id: "",
  },

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    this.attachEventListeners();
    await this.loadReferenceData();
    await this.loadCases();
  },

  attachEventListeners: function () {
    document
      .getElementById("addCaseBtn")
      ?.addEventListener("click", () => this.openCaseModal());

    document
      .getElementById("caseForm")
      ?.addEventListener("submit", (e) => this.submitCaseForm(e));

    document
      .getElementById("searchBox")
      ?.addEventListener("keyup", (e) => {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
          this.filters.search = e.target.value.trim();
          this.loadCases(1);
        }, 300);
      });

    document
      .getElementById("statusFilter")
      ?.addEventListener("change", (e) => {
        this.filters.status = e.target.value;
        this.loadCases(1);
      });

    document
      .getElementById("severityFilter")
      ?.addEventListener("change", (e) => {
        this.filters.severity = e.target.value;
        this.loadCases(1);
      });

    document
      .getElementById("classFilter")
      ?.addEventListener("change", (e) => {
        this.filters.class_id = e.target.value;
        this.loadCases(1);
      });

    document
      .getElementById("resolveMultipleBtn")
      ?.addEventListener("click", () => this.bulkResolve());

    document
      .getElementById("selectAllCases")
      ?.addEventListener("change", (e) => {
        document
          .querySelectorAll(".case-select")
          .forEach((checkbox) => {
            checkbox.checked = e.target.checked;
          });
      });
  },

  loadReferenceData: async function () {
    try {
      const classResp = await window.API.academic.listClasses();
      const classPayload = this.unwrapPayload(classResp);
      this.data.classes = Array.isArray(classPayload) ? classPayload : [];
      this.populateClassFilter();
    } catch (error) {
      console.warn("Failed to load classes", error);
    }

    try {
      const studentResp = await window.API.apiCall(
        `/students?limit=500`,
        "GET",
      );
      const payload = this.unwrapPayload(studentResp);
      const students = payload?.students || payload || [];
      this.data.students = Array.isArray(students) ? students : [];
      this.populateStudentDropdown();
    } catch (error) {
      console.warn("Failed to load students", error);
    }
  },

  populateClassFilter: function () {
    const select = document.getElementById("classFilter");
    if (!select) return;

    const firstOpt = select.options[0];
    select.innerHTML = "";
    select.appendChild(firstOpt);

    this.data.classes.forEach((cls) => {
      const opt = document.createElement("option");
      opt.value = cls.id;
      opt.textContent = cls.name || cls.class_name;
      select.appendChild(opt);
    });
  },

  populateStudentDropdown: function () {
    const select = document.getElementById("student");
    if (!select) return;

    select.innerHTML = "";
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = "Select Student";
    select.appendChild(placeholder);

    this.data.students.forEach((student) => {
      const opt = document.createElement("option");
      opt.value = student.id;
      opt.textContent = `${student.admission_no || ""} - ${
        student.first_name
      } ${student.last_name}`.trim();
      select.appendChild(opt);
    });
  },

  loadCases: async function (page = 1) {
    try {
      const params = new URLSearchParams({
        page,
        limit: this.data.pagination.limit,
      });

      if (this.filters.search) params.append("search", this.filters.search);
      if (this.filters.status) params.append("status", this.filters.status);
      if (this.filters.severity)
        params.append("severity", this.filters.severity);
      if (this.filters.class_id)
        params.append("class_id", this.filters.class_id);

      const resp = await window.API.apiCall(
        `/students/discipline-get?${params.toString()}`,
        "GET"
      );

      const payload = this.unwrapPayload(resp) || {};
      this.data.cases = payload.cases || [];
      this.data.pagination = payload.pagination || this.data.pagination;
      this.data.summary = payload.summary || {};

      this.renderSummary();
      this.renderTable();
      this.renderPagination();
    } catch (error) {
      console.error("Error loading discipline cases:", error);
      this.showError("Failed to load discipline cases");
    }
  },

  renderSummary: function () {
    const totalEl = document.getElementById("totalCases");
    if (totalEl) totalEl.textContent = this.data.summary.total || 0;
    const pendingEl = document.getElementById("pendingCases");
    if (pendingEl) pendingEl.textContent = this.data.summary.pending || 0;
    const resolvedEl = document.getElementById("resolvedCases");
    if (resolvedEl) resolvedEl.textContent = this.data.summary.resolved || 0;
    const termEl = document.getElementById("casesTerm");
    if (termEl) termEl.textContent = this.data.summary.term || 0;
    const escalatedEl = document.getElementById("escalatedCases");
    if (escalatedEl) escalatedEl.textContent = this.data.summary.escalated || 0;
  },

  renderTable: function () {
    const tbody = document.querySelector("#disciplineTable tbody");
    if (!tbody) return;

    if (!this.data.cases.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="9" class="text-center text-muted py-4">No discipline cases found</td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = this.data.cases
      .map((c) => {
        const severityLabel = this.formatSeverity(c.severity);
        const statusLabel = this.formatStatus(c.status);
        const studentName = `${c.first_name || ""} ${c.last_name || ""}`.trim();
        const className = `${c.class_name || ""}${
          c.stream_name ? " (" + c.stream_name + ")" : ""
        }`;

        return `
          <tr>
            <td><input type="checkbox" class="case-select" data-id="${c.id}"></td>
            <td>${c.incident_date || "-"}</td>
            <td>${studentName || "-"}</td>
            <td>${className || "-"}</td>
            <td>${this.escapeHtml(c.description || "-")}</td>
            <td><span class="badge bg-${this.severityBadge(c.severity)}">${
              severityLabel
            }</span></td>
            <td>${this.escapeHtml(c.action_taken || "-")}</td>
            <td><span class="badge bg-${this.statusBadge(c.status)}">${
              statusLabel
            }</span></td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" onclick="StudentDisciplineController.openCaseModal(${c.id})" title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-success" onclick="StudentDisciplineController.resolveCase(${c.id})" title="Resolve">
                  <i class="bi bi-check-circle"></i>
                </button>
              </div>
            </td>
          </tr>
        `;
      })
      .join("");
  },

  renderPagination: function () {
    const container = document.getElementById("pagination");
    if (!container) return;

    const { page, total, limit } = this.data.pagination;
    const totalPages = Math.ceil(total / limit) || 1;

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `
        <li class="page-item ${i === page ? "active" : ""}">
          <a class="page-link" href="#" onclick="StudentDisciplineController.loadCases(${i}); return false;">${i}</a>
        </li>
      `;
    }

    container.innerHTML = html;
  },

  openCaseModal: function (caseId = null) {
    const modalEl = document.getElementById("caseModal");
    const form = document.getElementById("caseForm");
    if (!modalEl || !form) return;

    form.reset();
    document.getElementById("caseId").value = "";
    document.getElementById("caseModalTitle").textContent = "Record Discipline Case";

    if (caseId) {
      const record = this.data.cases.find((c) => c.id == caseId);
      if (record) {
        document.getElementById("caseId").value = record.id;
        document.getElementById("caseModalTitle").textContent = "Edit Discipline Case";
        document.getElementById("student").value = record.student_id;
        document.getElementById("incidentDate").value = record.incident_date || "";
        document.getElementById("offenseCategory").value = "other";
        document.getElementById("severity").value = record.severity || "low";
        document.getElementById("offenseDescription").value = record.description || "";
        document.getElementById("actionTaken").value = "other";
        document.getElementById("actionDetails").value = record.action_taken || "";
        document.getElementById("status").value = record.status || "pending";
      }
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  },

  submitCaseForm: async function (event) {
    event.preventDefault();

    const caseId = document.getElementById("caseId").value;
    const studentId = document.getElementById("student").value;

    const offenseCategory = this.getSelectLabel("offenseCategory");
    const offenseDescription = document.getElementById("offenseDescription").value;
    const location = document.getElementById("location").value;
    const reportedBy = document.getElementById("reportedBy").value;
    const witnesses = document.getElementById("witnesses").value;

    const descriptionParts = [
      offenseCategory ? `[${offenseCategory}] ${offenseDescription}` : offenseDescription,
    ];
    if (location) descriptionParts.push(`Location: ${location}`);
    if (reportedBy) descriptionParts.push(`Reported by: ${reportedBy}`);
    if (witnesses) descriptionParts.push(`Witnesses: ${witnesses}`);

    const actionTakenLabel = this.getSelectLabel("actionTaken");
    const actionDetails = document.getElementById("actionDetails").value;
    const parentNotified = document.getElementById("parentNotified").value;
    const followUpNotes = document.getElementById("followUpNotes").value;

    const actionParts = [];
    if (actionTakenLabel) actionParts.push(`Action: ${actionTakenLabel}`);
    if (actionDetails) actionParts.push(`Details: ${actionDetails}`);
    if (parentNotified) actionParts.push(`Parent Notified: ${parentNotified}`);
    if (followUpNotes) actionParts.push(`Follow-up: ${followUpNotes}`);

    const payload = {
      incident_date: document.getElementById("incidentDate").value,
      severity: document.getElementById("severity").value,
      status: document.getElementById("status").value,
      description: descriptionParts.filter(Boolean).join("\n"),
      action_taken: actionParts.filter(Boolean).join("\n"),
    };

    try {
      if (caseId) {
        await window.API.students.updateDiscipline(caseId, payload);
        this.showSuccess("Discipline case updated");
      } else {
        if (!studentId) {
          this.showError("Please select a student");
          return;
        }
        await window.API.students.recordDiscipline(studentId, payload);
        this.showSuccess("Discipline case recorded");
      }

      bootstrap.Modal.getInstance(document.getElementById("caseModal")).hide();
      await this.loadCases();
    } catch (error) {
      console.error("Error saving discipline case", error);
      this.showError(error.message || "Failed to save discipline case");
    }
  },

  resolveCase: async function (caseId) {
    const actionTaken = prompt("Enter resolution notes", "Resolved");
    if (actionTaken === null) return;

    try {
      await window.API.students.resolveDiscipline(caseId, {
        action_taken: actionTaken,
      });
      this.showSuccess("Case resolved successfully");
      await this.loadCases();
    } catch (error) {
      this.showError(error.message || "Failed to resolve case");
    }
  },

  bulkResolve: async function () {
    const selected = Array.from(
      document.querySelectorAll(".case-select:checked")
    ).map((checkbox) => checkbox.dataset.id);

    if (!selected.length) {
      this.showError("Select at least one case to resolve");
      return;
    }

    if (!confirm(`Resolve ${selected.length} selected cases?`)) return;

    try {
      for (const id of selected) {
        await window.API.students.resolveDiscipline(id, {
          action_taken: "Resolved",
        });
      }
      this.showSuccess("Selected cases resolved");
      await this.loadCases();
    } catch (error) {
      this.showError(error.message || "Failed to resolve selected cases");
    }
  },

  formatSeverity: function (value) {
    if (value === "low") return "Low";
    if (value === "medium") return "Medium";
    if (value === "high") return "High";
    return value || "-";
  },

  severityBadge: function (value) {
    if (value === "high") return "danger";
    if (value === "medium") return "warning";
    return "secondary";
  },

  formatStatus: function (value) {
    if (value === "pending") return "Pending";
    if (value === "resolved") return "Resolved";
    if (value === "escalated") return "Escalated";
    return value || "-";
  },

  statusBadge: function (value) {
    if (value === "resolved") return "success";
    if (value === "escalated") return "danger";
    return "warning";
  },

  getSelectLabel: function (id) {
    const select = document.getElementById(id);
    if (!select) return "";
    const option = select.options[select.selectedIndex];
    return option ? option.textContent.trim() : "";
  },

  unwrapPayload: function (response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined) return response.data.data;
    return response;
  },

  escapeHtml: function (value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
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

document.addEventListener("DOMContentLoaded", () =>
  StudentDisciplineController.init()
);

window.StudentDisciplineController = StudentDisciplineController;
