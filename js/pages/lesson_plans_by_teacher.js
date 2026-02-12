/**
 * Lesson Plans by Teacher Page Controller
 * Displays lesson plan submission tracking per teacher
 */
const LessonPlansByTeacherController = (() => {
  let teacherData = [];
  let pagination = { page: 1, limit: 15, total: 0 };

  async function loadData(page = 1) {
    try {
      pagination.page = page;
      const params = new URLSearchParams({ page, limit: pagination.limit });

      const dept = document.getElementById("departmentFilterLPT")?.value;
      if (dept) params.append("department", dept);
      const status = document.getElementById("submissionStatusFilter")?.value;
      if (status) params.append("submission_status", status);
      const search = document.getElementById("searchByTeacher")?.value;
      if (search) params.append("search", search);

      const response = await window.API.apiCall(
        `/academic/lesson-plans/by-teacher?${params.toString()}`,
        "GET",
      );
      const data = response?.data || response || [];
      teacherData = Array.isArray(data)
        ? data
        : data.teachers || data.data || [];
      if (data.pagination) pagination = { ...pagination, ...data.pagination };
      pagination.total = data.total || teacherData.length;

      renderStats(teacherData);
      renderTable(teacherData);
      renderPagination();
    } catch (e) {
      console.error("Load teacher coverage failed:", e);
      renderTable([]);
    }
  }

  function renderStats(data) {
    const total = pagination.total || data.length;
    const fully = data.filter((d) => {
      const coverage = parseFloat(d.coverage_percentage || d.coverage || 0);
      return coverage >= 100;
    }).length;
    const partial = data.filter((d) => {
      const coverage = parseFloat(d.coverage_percentage || d.coverage || 0);
      return coverage > 0 && coverage < 100;
    }).length;
    const none = data.filter((d) => {
      const submitted = d.submitted || d.plans_submitted || 0;
      return submitted === 0;
    }).length;

    document.getElementById("teachersTotal").textContent = total;
    document.getElementById("fullySubmitted").textContent = fully;
    document.getElementById("partiallySubmitted").textContent = partial;
    document.getElementById("notSubmitted").textContent = none;
  }

  function renderTable(items) {
    const tbody = document.getElementById("byTeacherTableBody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center py-4 text-muted">No teacher data found</td></tr>';
      return;
    }

    tbody.innerHTML = items
      .map((t, i) => {
        const coverage = parseFloat(t.coverage_percentage || t.coverage || 0);
        const coverageColor =
          coverage >= 100 ? "success" : coverage >= 50 ? "warning" : "danger";
        const expected = t.plans_expected || t.total_expected || 0;
        const submitted = t.submitted || t.plans_submitted || 0;
        const approved = t.approved || t.plans_approved || 0;
        const pending = t.pending || t.plans_pending || 0;

        return `
                <tr>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td><strong>${t.teacher_name || t.name || ((t.first_name || "") + " " + (t.last_name || "")).trim() || "-"}</strong></td>
                    <td>${t.department || t.department_name || "-"}</td>
                    <td>${expected}</td>
                    <td><span class="badge bg-primary">${submitted}</span></td>
                    <td><span class="badge bg-success">${approved}</span></td>
                    <td><span class="badge bg-warning">${pending}</span></td>
                    <td>
                        <div class="progress" style="height:20px;">
                            <div class="progress-bar bg-${coverageColor}" style="width:${coverage}%">${coverage.toFixed(0)}%</div>
                        </div>
                    </td>
                </tr>
            `;
      })
      .join("");
  }

  function renderPagination() {
    const container = document.getElementById("pagination");
    if (!container) return;
    const totalPages = Math.ceil(pagination.total / pagination.limit);

    const fromEl = document.getElementById("showingFrom");
    const toEl = document.getElementById("showingTo");
    const totalEl = document.getElementById("totalRecords");
    if (fromEl)
      fromEl.textContent =
        pagination.total > 0 ? (pagination.page - 1) * pagination.limit + 1 : 0;
    if (toEl)
      toEl.textContent = Math.min(
        pagination.page * pagination.limit,
        pagination.total,
      );
    if (totalEl) totalEl.textContent = pagination.total;

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `<li class="page-item ${i === pagination.page ? "active" : ""}">
                <a class="page-link" href="#" onclick="LessonPlansByTeacherController.loadPage(${i}); return false;">${i}</a>
            </li>`;
    }
    container.innerHTML = html;
  }

  function showNotification(message, type) {
    if (window.API?.showNotification)
      window.API.showNotification(message, type);
    else alert((type === "error" ? "Error: " : "") + message);
  }

  function attachListeners() {
    document
      .getElementById("departmentFilterLPT")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("submissionStatusFilter")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("searchByTeacher")
      ?.addEventListener("keyup", () => {
        clearTimeout(window._lptSearchTimeout);
        window._lptSearchTimeout = setTimeout(() => loadData(1), 300);
      });
    document
      .getElementById("exportByTeacherBtn")
      ?.addEventListener("click", () => {
        window.open(
          "/Kingsway/api/?route=academic/lesson-plans/by-teacher/export&format=csv",
          "_blank",
        );
      });
  }

  async function init() {
    attachListeners();
    await loadData();
  }

  return { init, refresh: loadData, loadPage: loadData };
})();

document.addEventListener("DOMContentLoaded", () =>
  LessonPlansByTeacherController.init(),
);
