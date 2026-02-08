/**
 * Schemes of Work Page Controller
 * Manages schemes of work CRUD and approval workflow using api.js
 */

const SchemesOfWorkController = (() => {
  // Private state
  const state = {
    schemes: [],
    classes: [],
    subjects: [],
    academicYears: [],
    pagination: { page: 1, limit: 10, total: 0 },
    summary: { total: 0, approved: 0, pending: 0, overdue: 0 },
  };

  const filters = {
    term: "",
    subject: "",
    class_id: "",
    status: "",
  };

  // ---- Helpers ----

  function unwrapPayload(response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined)
      return response.data.data;
    return response;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function showSuccess(message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "success");
    } else {
      alert(message);
    }
  }

  function showError(message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "error");
    } else {
      alert("Error: " + message);
    }
  }

  function statusBadge(status) {
    const map = {
      approved: "success",
      pending: "warning",
      rejected: "danger",
      overdue: "danger",
    };
    return map[status] || "secondary";
  }

  function formatStatus(status) {
    const map = {
      approved: "Approved",
      pending: "Pending Review",
      rejected: "Rejected",
      overdue: "Overdue",
    };
    return map[status] || status || "-";
  }

  // ---- Data Loading ----

  async function loadReferenceData() {
    try {
      const classResp = await window.API.academic.listClasses();
      const classPayload = unwrapPayload(classResp);
      state.classes = Array.isArray(classPayload) ? classPayload : [];
      populateClassDropdowns();
    } catch (error) {
      console.warn("Failed to load classes", error);
    }

    try {
      const subjectResp = await window.API.apiCall("/academic/subjects", "GET");
      const subjectPayload = unwrapPayload(subjectResp);
      state.subjects = Array.isArray(subjectPayload) ? subjectPayload : [];
      populateSubjectDropdowns();
    } catch (error) {
      console.warn("Failed to load subjects", error);
    }

    try {
      const yearResp = await window.API.students.getAllAcademicYears();
      const yearPayload = unwrapPayload(yearResp);
      state.academicYears = Array.isArray(yearPayload) ? yearPayload : [];
      populateYearDropdown();
    } catch (error) {
      console.warn("Failed to load academic years", error);
    }
  }

  function populateClassDropdowns() {
    const filterSelect = document.getElementById("classFilter");
    const formSelect = document.getElementById("schemeClass");

    state.classes.forEach((cls) => {
      const name = cls.name || cls.class_name;
      if (filterSelect) {
        const opt = document.createElement("option");
        opt.value = cls.id;
        opt.textContent = name;
        filterSelect.appendChild(opt);
      }
      if (formSelect) {
        const opt = document.createElement("option");
        opt.value = cls.id;
        opt.textContent = name;
        formSelect.appendChild(opt);
      }
    });
  }

  function populateSubjectDropdowns() {
    const filterSelect = document.getElementById("subjectFilter");
    const formSelect = document.getElementById("schemeSubject");

    state.subjects.forEach((subj) => {
      const name = subj.name || subj.subject_name;
      if (filterSelect) {
        const opt = document.createElement("option");
        opt.value = subj.id;
        opt.textContent = name;
        filterSelect.appendChild(opt);
      }
      if (formSelect) {
        const opt = document.createElement("option");
        opt.value = subj.id;
        opt.textContent = name;
        formSelect.appendChild(opt);
      }
    });
  }

  function populateYearDropdown() {
    const select = document.getElementById("schemeYear");
    if (!select) return;

    state.academicYears.forEach((year) => {
      const yearCode =
        year.year_code || year.year_name || year.academic_year || "";
      const opt = document.createElement("option");
      opt.value = year.id || yearCode;
      opt.textContent = yearCode;
      if (year.is_current === 1 || year.is_current === true)
        opt.selected = true;
      select.appendChild(opt);
    });
  }

  async function loadSchemes(page = 1) {
    try {
      state.pagination.page = page;

      const params = new URLSearchParams({
        page,
        limit: state.pagination.limit,
      });

      if (filters.term) params.append("term", filters.term);
      if (filters.subject) params.append("subject_id", filters.subject);
      if (filters.class_id) params.append("class_id", filters.class_id);
      if (filters.status) params.append("status", filters.status);

      const resp = await window.API.apiCall(
        `/academic/schemes-of-work?${params.toString()}`,
        "GET",
      );

      const payload = unwrapPayload(resp) || {};
      state.schemes = payload.schemes || payload.data || [];
      if (!Array.isArray(state.schemes)) state.schemes = [];

      state.pagination = payload.pagination || state.pagination;
      state.summary = payload.summary || computeSummary(state.schemes);

      renderSummary();
      renderTable();
      renderPagination();
    } catch (error) {
      console.error("Error loading schemes:", error);
      showError("Failed to load schemes of work");
    }
  }

  function computeSummary(schemes) {
    return {
      total: schemes.length,
      approved: schemes.filter((s) => s.status === "approved").length,
      pending: schemes.filter((s) => s.status === "pending").length,
      overdue: schemes.filter((s) => s.status === "overdue").length,
    };
  }

  // ---- Rendering ----

  function renderSummary() {
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalSchemes", state.summary.total || 0);
    el("approvedSchemes", state.summary.approved || 0);
    el("pendingSchemes", state.summary.pending || 0);
    el("overdueSchemes", state.summary.overdue || 0);
  }

  function renderTable() {
    const tbody = document.querySelector("#schemesTable tbody");
    if (!tbody) return;

    if (!state.schemes.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center text-muted py-4">No schemes of work found</td>
        </tr>`;
      return;
    }

    tbody.innerHTML = state.schemes
      .map((s) => {
        const subjectName = s.subject_name || s.subject || "-";
        const className = s.class_name || s.class || "-";
        const teacherName =
          s.teacher_name ||
          `${s.first_name || ""} ${s.last_name || ""}`.trim() ||
          "-";
        const term = s.term ? `Term ${s.term}` : "-";
        const topicCount = s.topic_count || 0;
        const status = s.status || "pending";
        const lastUpdated = s.updated_at || s.last_updated || "-";

        return `
          <tr>
            <td>${escapeHtml(subjectName)}</td>
            <td>${escapeHtml(className)}</td>
            <td>${escapeHtml(teacherName)}</td>
            <td>${escapeHtml(term)}</td>
            <td>${topicCount}</td>
            <td><span class="badge bg-${statusBadge(status)}">${formatStatus(status)}</span></td>
            <td>${escapeHtml(lastUpdated)}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-info" onclick="SchemesOfWorkController.viewScheme(${s.id})" title="View">
                  <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-outline-primary" onclick="SchemesOfWorkController.editScheme(${s.id})" title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="SchemesOfWorkController.deleteScheme(${s.id})" title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>`;
      })
      .join("");
  }

  function renderPagination() {
    const container = document.getElementById("pagination");
    if (!container) return;

    const { page, total, limit } = state.pagination;
    const totalPages = Math.ceil(total / limit) || 1;

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `
        <li class="page-item ${i === page ? "active" : ""}">
          <a class="page-link" href="#" onclick="SchemesOfWorkController.loadPage(${i}); return false;">${i}</a>
        </li>`;
    }
    container.innerHTML = html;
  }

  // ---- CRUD Actions ----

  function openSchemeModal(schemeId = null) {
    const modalEl = document.getElementById("schemeModal");
    const form = document.getElementById("schemeForm");
    if (!modalEl || !form) return;

    form.reset();
    document.getElementById("schemeId").value = "";
    document.getElementById("schemeModalTitle").textContent =
      "Upload Scheme of Work";

    if (schemeId) {
      const record = state.schemes.find((s) => s.id == schemeId);
      if (record) {
        document.getElementById("schemeId").value = record.id;
        document.getElementById("schemeModalTitle").textContent =
          "Edit Scheme of Work";
        document.getElementById("schemeSubject").value =
          record.subject_id || "";
        document.getElementById("schemeClass").value = record.class_id || "";
        document.getElementById("schemeTerm").value = record.term || "";
        document.getElementById("schemeTitle").value = record.title || "";
        document.getElementById("schemeTopics").value = record.topics || "";
        document.getElementById("schemeNotes").value = record.notes || "";
      }
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  async function saveScheme() {
    const schemeId = document.getElementById("schemeId").value;

    const payload = {
      subject_id: document.getElementById("schemeSubject").value,
      class_id: document.getElementById("schemeClass").value,
      term: document.getElementById("schemeTerm").value,
      academic_year_id: document.getElementById("schemeYear").value,
      title: document.getElementById("schemeTitle").value,
      topics: document.getElementById("schemeTopics").value,
      notes: document.getElementById("schemeNotes").value,
    };

    if (
      !payload.subject_id ||
      !payload.class_id ||
      !payload.term ||
      !payload.title
    ) {
      showError("Please fill in all required fields");
      return;
    }

    try {
      if (schemeId) {
        await window.API.apiCall(
          `/academic/schemes-of-work/${schemeId}`,
          "PUT",
          payload,
        );
        showSuccess("Scheme updated successfully");
      } else {
        await window.API.apiCall("/academic/schemes-of-work", "POST", payload);
        showSuccess("Scheme uploaded successfully");
      }

      bootstrap.Modal.getInstance(
        document.getElementById("schemeModal"),
      ).hide();
      await loadSchemes(state.pagination.page);
    } catch (error) {
      console.error("Error saving scheme:", error);
      showError(error.message || "Failed to save scheme");
    }
  }

  function viewScheme(schemeId) {
    const record = state.schemes.find((s) => s.id == schemeId);
    if (!record) return;

    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };

    el("viewSubject", record.subject_name || "-");
    el("viewClass", record.class_name || "-");
    el(
      "viewTeacher",
      record.teacher_name ||
        `${record.first_name || ""} ${record.last_name || ""}`.trim() ||
        "-",
    );
    el("viewTerm", record.term ? `Term ${record.term}` : "-");
    el("viewTopicCount", record.topic_count || 0);

    const statusEl = document.getElementById("viewStatus");
    if (statusEl) {
      statusEl.innerHTML = `<span class="badge bg-${statusBadge(record.status)}">${formatStatus(record.status)}</span>`;
    }

    const topicsEl = document.getElementById("viewTopics");
    if (topicsEl) topicsEl.textContent = record.topics || "No topics listed";

    const notesEl = document.getElementById("viewNotes");
    if (notesEl) notesEl.textContent = record.notes || "No notes";

    const fileSection = document.getElementById("viewFileSection");
    if (fileSection && record.file_url) {
      fileSection.style.display = "block";
      document.getElementById("viewFileLink").href = record.file_url;
    } else if (fileSection) {
      fileSection.style.display = "none";
    }

    // Store current scheme ID for approval actions
    state.currentViewId = schemeId;

    const modal = new bootstrap.Modal(
      document.getElementById("viewSchemeModal"),
    );
    modal.show();
  }

  async function approveScheme() {
    if (!state.currentViewId) return;
    try {
      await window.API.apiCall(
        `/academic/schemes-of-work/${state.currentViewId}/approve`,
        "PUT",
      );
      showSuccess("Scheme approved successfully");
      bootstrap.Modal.getInstance(
        document.getElementById("viewSchemeModal"),
      ).hide();
      await loadSchemes(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to approve scheme");
    }
  }

  async function rejectScheme() {
    if (!state.currentViewId) return;
    const reason = prompt("Enter reason for rejection:");
    if (reason === null) return;

    try {
      await window.API.apiCall(
        `/academic/schemes-of-work/${state.currentViewId}/reject`,
        "PUT",
        { reason },
      );
      showSuccess("Scheme rejected");
      bootstrap.Modal.getInstance(
        document.getElementById("viewSchemeModal"),
      ).hide();
      await loadSchemes(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to reject scheme");
    }
  }

  async function deleteScheme(schemeId) {
    if (!confirm("Are you sure you want to delete this scheme of work?"))
      return;

    try {
      await window.API.apiCall(
        `/academic/schemes-of-work/${schemeId}`,
        "DELETE",
      );
      showSuccess("Scheme deleted successfully");
      await loadSchemes(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to delete scheme");
    }
  }

  function exportSchemes() {
    if (!state.schemes.length) {
      showError("No data to export");
      return;
    }

    const rows = ["Subject,Class,Teacher,Term,Topic Count,Status,Last Updated"];
    state.schemes.forEach((s) => {
      rows.push(
        `"${s.subject_name || ""}","${s.class_name || ""}","${s.teacher_name || ""}","Term ${s.term || ""}",${s.topic_count || 0},"${s.status || ""}","${s.updated_at || ""}"`,
      );
    });

    const blob = new Blob([rows.join("\n")], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "schemes_of_work.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  // ---- Event Listeners ----

  function attachEventListeners() {
    document
      .getElementById("uploadSchemeBtn")
      ?.addEventListener("click", () => openSchemeModal());

    document
      .getElementById("saveSchemeBtn")
      ?.addEventListener("click", () => saveScheme());

    document
      .getElementById("exportSchemesBtn")
      ?.addEventListener("click", () => exportSchemes());

    document
      .getElementById("approveSchemeBtn")
      ?.addEventListener("click", () => approveScheme());

    document
      .getElementById("rejectSchemeBtn")
      ?.addEventListener("click", () => rejectScheme());

    // Filters
    document.getElementById("termFilter")?.addEventListener("change", (e) => {
      filters.term = e.target.value;
      loadSchemes(1);
    });

    document
      .getElementById("subjectFilter")
      ?.addEventListener("change", (e) => {
        filters.subject = e.target.value;
        loadSchemes(1);
      });

    document.getElementById("classFilter")?.addEventListener("change", (e) => {
      filters.class_id = e.target.value;
      loadSchemes(1);
    });

    document.getElementById("statusFilter")?.addEventListener("change", (e) => {
      filters.status = e.target.value;
      loadSchemes(1);
    });
  }

  // ---- Initialization ----

  async function init() {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    attachEventListeners();
    await loadReferenceData();
    await loadSchemes();
  }

  // ---- Public API ----
  return {
    init,
    refresh: loadSchemes,
    loadPage: loadSchemes,
    viewScheme,
    editScheme: openSchemeModal,
    deleteScheme,
  };
})();

document.addEventListener("DOMContentLoaded", () =>
  SchemesOfWorkController.init(),
);

window.SchemesOfWorkController = SchemesOfWorkController;
