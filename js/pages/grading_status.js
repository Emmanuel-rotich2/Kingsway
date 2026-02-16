/**
 * Grading Status Page Controller
 * Monitors grading completion progress across subjects using api.js
 */

const GradingStatusController = (() => {
  // Private state
  const state = {
    gradingData: [],
    classes: [],
    pagination: { page: 1, limit: 10, total: 0 },
    summary: { total: 0, fully_graded: 0, partially_graded: 0, not_started: 0 },
    overallPercentage: 0,
  };

  const filters = {
    term: "",
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

  function getGradingStatus(percentage) {
    if (percentage >= 100) return "complete";
    if (percentage > 0) return "partial";
    return "not_started";
  }

  function statusBadge(status) {
    const map = {
      complete: "success",
      partial: "warning",
      not_started: "danger",
    };
    return map[status] || "secondary";
  }

  function formatStatus(status) {
    const map = {
      complete: "Fully Graded",
      partial: "Partially Graded",
      not_started: "Not Started",
    };
    return map[status] || status || "-";
  }

  function progressBarColor(percentage) {
    if (percentage >= 100) return "bg-success";
    if (percentage >= 50) return "bg-warning";
    if (percentage > 0) return "bg-danger";
    return "bg-secondary";
  }

  // ---- Data Loading ----

  async function loadReferenceData() {
    try {
      const classResp = await window.API.academic.listClasses();
      const classPayload = unwrapPayload(classResp);
      state.classes = Array.isArray(classPayload) ? classPayload : [];
      populateClassFilter();
    } catch (error) {
      console.warn("Failed to load classes", error);
    }
  }

  function populateClassFilter() {
    const select = document.getElementById("classFilter");
    if (!select) return;

    state.classes.forEach((cls) => {
      const opt = document.createElement("option");
      opt.value = cls.id;
      opt.textContent = cls.name || cls.class_name;
      select.appendChild(opt);
    });
  }

  async function loadData(page = 1) {
    try {
      state.pagination.page = page;

      const params = new URLSearchParams({
        page,
        limit: state.pagination.limit,
      });

      if (filters.term) params.append("term", filters.term);
      if (filters.class_id) params.append("class_id", filters.class_id);
      if (filters.status) params.append("status", filters.status);

      const resp = await window.API.apiCall(
        `/academic/grading-status?${params.toString()}`,
        "GET"
      );

      const payload = unwrapPayload(resp) || {};
      state.gradingData = payload.subjects || payload.data || [];
      if (!Array.isArray(state.gradingData)) state.gradingData = [];

      state.pagination = payload.pagination || state.pagination;
      state.summary = payload.summary || computeSummary(state.gradingData);
      state.overallPercentage = payload.overall_percentage || computeOverallPercentage(state.gradingData);

      renderSummary();
      renderOverallProgress();
      renderTable();
      renderPagination();
    } catch (error) {
      console.error("Error loading grading status:", error);
      showError("Failed to load grading status data");
    }
  }

  function computeSummary(data) {
    return {
      total: data.length,
      fully_graded: data.filter((d) => {
        const pct = d.total_students > 0 ? (d.graded_count / d.total_students) * 100 : 0;
        return pct >= 100;
      }).length,
      partially_graded: data.filter((d) => {
        const pct = d.total_students > 0 ? (d.graded_count / d.total_students) * 100 : 0;
        return pct > 0 && pct < 100;
      }).length,
      not_started: data.filter((d) => {
        return !d.graded_count || d.graded_count === 0;
      }).length,
    };
  }

  function computeOverallPercentage(data) {
    if (!data.length) return 0;
    const totalStudents = data.reduce((sum, d) => sum + (d.total_students || 0), 0);
    const totalGraded = data.reduce((sum, d) => sum + (d.graded_count || 0), 0);
    return totalStudents > 0 ? Math.round((totalGraded / totalStudents) * 100) : 0;
  }

  // ---- Rendering ----

  function renderSummary() {
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalSubjects", state.summary.total || 0);
    el("fullyGraded", state.summary.fully_graded || 0);
    el("partiallyGraded", state.summary.partially_graded || 0);
    el("notStarted", state.summary.not_started || 0);
  }

  function renderOverallProgress() {
    const pct = state.overallPercentage;

    const percentageEl = document.getElementById("overallPercentage");
    if (percentageEl) percentageEl.textContent = `${pct}%`;

    const progressBar = document.getElementById("overallProgressBar");
    if (progressBar) {
      progressBar.style.width = `${pct}%`;
      progressBar.setAttribute("aria-valuenow", pct);
      progressBar.textContent = `${pct}%`;

      // Update color based on percentage
      progressBar.className = `progress-bar ${progressBarColor(pct)}`;
    }
  }

  function renderTable() {
    const tbody = document.querySelector("#gradingStatusTable tbody");
    if (!tbody) return;

    if (!state.gradingData.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center text-muted py-4">No grading data available. Select a term to view status.</td>
        </tr>`;
      return;
    }

    tbody.innerHTML = state.gradingData
      .map((d) => {
        const subject = d.subject_name || d.subject || "-";
        const teacher = d.teacher_name || `${d.first_name || ""} ${d.last_name || ""}`.trim() || "-";
        const className = d.class_name || "-";
        const totalStudents = d.total_students || 0;
        const graded = d.graded_count || 0;
        const pending = totalStudents - graded;
        const percentage = totalStudents > 0 ? Math.round((graded / totalStudents) * 100) : 0;
        const status = getGradingStatus(percentage);

        return `
          <tr>
            <td>${escapeHtml(subject)}</td>
            <td>${escapeHtml(teacher)}</td>
            <td>${escapeHtml(className)}</td>
            <td>${totalStudents}</td>
            <td><span class="text-success fw-bold">${graded}</span></td>
            <td><span class="${pending > 0 ? "text-danger fw-bold" : "text-muted"}">${pending}</span></td>
            <td>
              <div class="d-flex align-items-center">
                <div class="progress flex-grow-1 me-2" style="height: 20px;">
                  <div class="progress-bar ${progressBarColor(percentage)}"
                       role="progressbar"
                       style="width: ${percentage}%;"
                       aria-valuenow="${percentage}"
                       aria-valuemin="0"
                       aria-valuemax="100">
                    ${percentage}%
                  </div>
                </div>
              </div>
            </td>
            <td><span class="badge bg-${statusBadge(status)}">${formatStatus(status)}</span></td>
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
          <a class="page-link" href="#" onclick="GradingStatusController.loadPage(${i}); return false;">${i}</a>
        </li>`;
    }
    container.innerHTML = html;
  }

  // ---- Export ----

  function exportGrading() {
    if (!state.gradingData.length) {
      showError("No data to export");
      return;
    }

    const rows = ["Subject,Teacher,Class,Total Students,Graded,Pending,Percentage,Status"];
    state.gradingData.forEach((d) => {
      const totalStudents = d.total_students || 0;
      const graded = d.graded_count || 0;
      const pending = totalStudents - graded;
      const percentage = totalStudents > 0 ? Math.round((graded / totalStudents) * 100) : 0;
      const status = formatStatus(getGradingStatus(percentage));
      const teacher = d.teacher_name || `${d.first_name || ""} ${d.last_name || ""}`.trim() || "";

      rows.push(
        `"${d.subject_name || ""}","${teacher}","${d.class_name || ""}",${totalStudents},${graded},${pending},${percentage}%,"${status}"`
      );
    });

    const blob = new Blob([rows.join("\n")], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "grading_status.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  // ---- Event Listeners ----

  function attachEventListeners() {
    document
      .getElementById("refreshBtn")
      ?.addEventListener("click", () => loadData(state.pagination.page));

    document
      .getElementById("exportGradingBtn")
      ?.addEventListener("click", () => exportGrading());

    document
      .getElementById("printGradingBtn")
      ?.addEventListener("click", () => window.print());

    // Filters
    document
      .getElementById("termFilter")
      ?.addEventListener("change", (e) => {
        filters.term = e.target.value;
        loadData(1);
      });

    document
      .getElementById("classFilter")
      ?.addEventListener("change", (e) => {
        filters.class_id = e.target.value;
        loadData(1);
      });

    document
      .getElementById("statusFilter")
      ?.addEventListener("change", (e) => {
        filters.status = e.target.value;
        loadData(1);
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
    await loadData();
  }

  // ---- Public API ----
  return {
    init,
    refresh: loadData,
    loadPage: loadData,
  };
})();

document.addEventListener("DOMContentLoaded", () =>
  GradingStatusController.init()
);

window.GradingStatusController = GradingStatusController;
