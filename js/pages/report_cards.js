/**
 * Report Cards Page Controller
 * Manages report card generation and distribution workflow using api.js
 */

const ReportCardsController = (() => {
  // Private state
  const state = {
    students: [],
    classes: [],
    pagination: { page: 1, limit: 10, total: 0 },
    summary: { total: 0, generated: 0, pending: 0, downloaded: 0 },
  };

  const filters = {
    term: "",
    class_id: "",
    search: "",
  };

  let searchTimeout = null;

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
      generated: "success",
      pending: "warning",
      downloaded: "info",
      not_generated: "secondary",
    };
    return map[status] || "secondary";
  }

  function formatStatus(status) {
    const map = {
      generated: "Generated",
      pending: "Pending",
      downloaded: "Downloaded",
      not_generated: "Not Generated",
    };
    return map[status] || status || "-";
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
      if (filters.search) params.append("search", filters.search);

      const resp = await window.API.apiCall(
        `/academic/report-cards?${params.toString()}`,
        "GET",
      );

      const payload = unwrapPayload(resp) || {};
      state.students = payload.students || payload.data || [];
      if (!Array.isArray(state.students)) state.students = [];

      state.pagination = payload.pagination || state.pagination;
      state.summary = payload.summary || computeSummary(state.students);

      renderSummary();
      renderTable();
      renderPagination();
    } catch (error) {
      console.error("Error loading report cards:", error);
      showError("Failed to load report cards data");
    }
  }

  function computeSummary(students) {
    return {
      total: students.length,
      generated: students.filter(
        (s) => s.card_status === "generated" || s.card_status === "downloaded",
      ).length,
      pending: students.filter(
        (s) =>
          !s.card_status ||
          s.card_status === "pending" ||
          s.card_status === "not_generated",
      ).length,
      downloaded: students.filter((s) => s.card_status === "downloaded").length,
    };
  }

  // ---- Rendering ----

  function renderSummary() {
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalStudents", state.summary.total || 0);
    el("cardsGenerated", state.summary.generated || 0);
    el("cardsPending", state.summary.pending || 0);
    el("cardsDownloaded", state.summary.downloaded || 0);
  }

  function renderTable() {
    const tbody = document.querySelector("#reportCardsTable tbody");
    if (!tbody) return;

    if (!state.students.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center text-muted py-4">No students found. Select a class and term to load report cards.</td>
        </tr>`;
      return;
    }

    tbody.innerHTML = state.students
      .map((s) => {
        const studentName =
          `${s.first_name || ""} ${s.last_name || ""}`.trim() || "-";
        const admNo = s.admission_no || "-";
        const className = s.class_name || "-";
        const avgScore =
          s.average_score !== undefined ? `${s.average_score}%` : "-";
        const rank = s.rank || s.position || "-";
        const status = s.card_status || "not_generated";

        return `
          <tr>
            <td><input type="checkbox" class="student-select" data-id="${s.id}"></td>
            <td>${escapeHtml(studentName)}</td>
            <td>${escapeHtml(admNo)}</td>
            <td>${escapeHtml(className)}</td>
            <td>${escapeHtml(avgScore)}</td>
            <td>${escapeHtml(String(rank))}</td>
            <td><span class="badge bg-${statusBadge(status)}">${formatStatus(status)}</span></td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" onclick="ReportCardsController.generateCard(${s.id})" title="Generate">
                  <i class="bi bi-file-earmark-plus"></i>
                </button>
                <button class="btn btn-outline-success" onclick="ReportCardsController.downloadCard(${s.id})" title="Download"
                        ${status === "not_generated" || status === "pending" ? "disabled" : ""}>
                  <i class="bi bi-download"></i>
                </button>
                <button class="btn btn-outline-info" onclick="ReportCardsController.printCard(${s.id})" title="Print"
                        ${status === "not_generated" || status === "pending" ? "disabled" : ""}>
                  <i class="bi bi-printer"></i>
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
          <a class="page-link" href="#" onclick="ReportCardsController.loadPage(${i}); return false;">${i}</a>
        </li>`;
    }
    container.innerHTML = html;
  }

  // ---- Actions ----

  async function generateCard(studentId) {
    try {
      const term = filters.term || document.getElementById("termFilter").value;
      if (!term) {
        showError("Please select a term before generating report cards");
        return;
      }

      await window.API.apiCall(`/academic/report-cards/generate`, "POST", {
        student_id: studentId,
        term,
      });
      showSuccess("Report card generated successfully");
      await loadData(state.pagination.page);
    } catch (error) {
      console.error("Error generating report card:", error);
      showError(error.message || "Failed to generate report card");
    }
  }

  async function generateAll() {
    const term = filters.term || document.getElementById("termFilter").value;
    const classId =
      filters.class_id || document.getElementById("classFilter").value;

    if (!term || !classId) {
      showError(
        "Please select both a term and a class to generate all report cards",
      );
      return;
    }

    if (
      !confirm("Generate report cards for all students in the selected class?")
    )
      return;

    try {
      await window.API.apiCall(`/academic/report-cards/generate-all`, "POST", {
        class_id: classId,
        term,
      });
      showSuccess("All report cards generated successfully");
      await loadData(state.pagination.page);
    } catch (error) {
      console.error("Error generating all report cards:", error);
      showError(error.message || "Failed to generate report cards");
    }
  }

  function downloadCard(studentId) {
    const term = filters.term || document.getElementById("termFilter").value;
    window.open(
      `/Kingsway/api/academic/report-cards/download/${studentId}?term=${term}`,
      "_blank",
    );
  }

  function downloadAll() {
    const term = filters.term || document.getElementById("termFilter").value;
    const classId =
      filters.class_id || document.getElementById("classFilter").value;

    if (!term || !classId) {
      showError("Please select both a term and class to download all cards");
      return;
    }

    window.open(
      `/Kingsway/api/academic/report-cards/download-all?class_id=${classId}&term=${term}`,
      "_blank",
    );
  }

  function printCard(studentId) {
    const term = filters.term || document.getElementById("termFilter").value;
    const printWindow = window.open(
      `/Kingsway/api/academic/report-cards/print/${studentId}?term=${term}`,
      "_blank",
    );
    if (printWindow) {
      printWindow.onload = () => printWindow.print();
    }
  }

  function printAll() {
    const term = filters.term || document.getElementById("termFilter").value;
    const classId =
      filters.class_id || document.getElementById("classFilter").value;

    if (!term || !classId) {
      showError("Please select both a term and class to print all cards");
      return;
    }

    const printWindow = window.open(
      `/Kingsway/api/academic/report-cards/print-all?class_id=${classId}&term=${term}`,
      "_blank",
    );
    if (printWindow) {
      printWindow.onload = () => printWindow.print();
    }
  }

  // ---- Event Listeners ----

  function attachEventListeners() {
    document
      .getElementById("generateAllBtn")
      ?.addEventListener("click", () => generateAll());

    document
      .getElementById("downloadAllBtn")
      ?.addEventListener("click", () => downloadAll());

    document
      .getElementById("printAllBtn")
      ?.addEventListener("click", () => printAll());

    document
      .getElementById("loadBtn")
      ?.addEventListener("click", () => loadData(1));

    document.getElementById("selectAll")?.addEventListener("change", (e) => {
      document
        .querySelectorAll(".student-select")
        .forEach((cb) => (cb.checked = e.target.checked));
    });

    // Filters
    document.getElementById("termFilter")?.addEventListener("change", (e) => {
      filters.term = e.target.value;
      loadData(1);
    });

    document.getElementById("classFilter")?.addEventListener("change", (e) => {
      filters.class_id = e.target.value;
      loadData(1);
    });

    document.getElementById("searchBox")?.addEventListener("keyup", (e) => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        filters.search = e.target.value.trim();
        loadData(1);
      }, 300);
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
    generateCard,
    downloadCard,
    printCard,
  };
})();

document.addEventListener("DOMContentLoaded", () =>
  ReportCardsController.init(),
);

window.ReportCardsController = ReportCardsController;
