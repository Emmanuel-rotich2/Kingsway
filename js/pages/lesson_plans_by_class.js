/**
 * Lesson Plans by Class Page Controller
 * Displays lesson plan coverage per class with drill-down capability
 */
const LessonPlansByClassController = (() => {
  let classData = [];
  let pagination = { page: 1, limit: 15, total: 0 };

  async function loadData(page = 1) {
    try {
      pagination.page = page;
      const params = new URLSearchParams({ page, limit: pagination.limit });

      const cls = document.getElementById("classFilterLPC")?.value;
      if (cls) params.append("class_id", cls);
      const coverage = document.getElementById("coverageFilter")?.value;
      if (coverage) params.append("coverage", coverage);
      const search = document.getElementById("searchByClass")?.value;
      if (search) params.append("search", search);

      const response = await window.API.apiCall(
        `/academic/lesson-plans/by-class?${params.toString()}`,
        "GET",
      );
      const data = response?.data || response || [];
      classData = Array.isArray(data) ? data : data.classes || data.data || [];
      if (data.pagination) pagination = { ...pagination, ...data.pagination };
      pagination.total = data.total || classData.length;

      renderStats(classData);
      renderTable(classData);
      renderPagination();
    } catch (e) {
      console.error("Load class coverage failed:", e);
      renderTable([]);
    }
  }

  function renderStats(data) {
    const withPlans = data.filter(
      (d) => (d.with_plans || d.plans_count || 0) > 0,
    ).length;
    const full = data.filter(
      (d) => parseFloat(d.coverage_percentage || d.coverage || 0) >= 100,
    ).length;
    const partial = data.filter((d) => {
      const cov = parseFloat(d.coverage_percentage || d.coverage || 0);
      return cov > 0 && cov < 100;
    }).length;
    const none = data.filter(
      (d) => (d.with_plans || d.plans_count || 0) === 0,
    ).length;

    document.getElementById("classesWithPlans").textContent = withPlans;
    document.getElementById("fullCoverage").textContent = full;
    document.getElementById("partialCoverage").textContent = partial;
    document.getElementById("noPlans").textContent = none;
  }

  function renderTable(items) {
    const tbody = document.getElementById("byClassTableBody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center py-4 text-muted">No class data found</td></tr>';
      return;
    }

    tbody.innerHTML = items
      .map((c, i) => {
        const coverage = parseFloat(c.coverage_percentage || c.coverage || 0);
        const coverageColor =
          coverage >= 100 ? "success" : coverage >= 50 ? "warning" : "danger";
        const totalSubjects = c.total_subjects || 0;
        const withPlans = c.with_plans || c.plans_count || 0;
        const withoutPlans = totalSubjects - withPlans;

        return `
                <tr>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td><strong>${c.class_name || c.name || "-"}</strong> ${c.stream_name ? "(" + c.stream_name + ")" : ""}</td>
                    <td>${totalSubjects}</td>
                    <td><span class="badge bg-success">${withPlans}</span></td>
                    <td><span class="badge bg-danger">${withoutPlans > 0 ? withoutPlans : 0}</span></td>
                    <td>
                        <div class="progress" style="height:20px;">
                            <div class="progress-bar bg-${coverageColor}" style="width:${coverage}%">${coverage.toFixed(0)}%</div>
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-info btn-sm" onclick="LessonPlansByClassController.viewDetail(${c.id || c.class_id})" title="View Details">
                            <i class="bi bi-eye"></i> Details
                        </button>
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
                <a class="page-link" href="#" onclick="LessonPlansByClassController.loadPage(${i}); return false;">${i}</a>
            </li>`;
    }
    container.innerHTML = html;
  }

  async function viewDetail(classId) {
    try {
      const resp = await window.API.apiCall(
        `/academic/lesson-plans/by-class/${classId}`,
        "GET",
      );
      const detail = resp?.data || resp;
      const subjects = detail?.subjects || detail?.data || [];
      const content = document.getElementById("classDetailContent");

      let tableRows = subjects
        .map(
          (s) => `
                <tr>
                    <td>${s.subject_name || "-"}</td>
                    <td>${s.teacher_name || "-"}</td>
                    <td><span class="badge bg-${s.has_plan ? "success" : "danger"}">${s.has_plan ? "Yes" : "No"}</span></td>
                    <td>${s.plan_status || "-"}</td>
                    <td>${s.last_submitted || "-"}</td>
                </tr>
            `,
        )
        .join("");

      content.innerHTML = `
                <h6>${detail.class_name || "Class"} - Subject Coverage</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr><th>Subject</th><th>Teacher</th><th>Has Plan</th><th>Status</th><th>Last Submitted</th></tr>
                        </thead>
                        <tbody>${tableRows || '<tr><td colspan="5" class="text-center text-muted">No data</td></tr>'}</tbody>
                    </table>
                </div>
            `;
      document.getElementById("classDetailLabel").textContent =
        `Lesson Plans: ${detail.class_name || "Class"}`;
      new bootstrap.Modal(document.getElementById("classDetailModal")).show();
    } catch (e) {
      showNotification("Failed to load class details", "error");
    }
  }

  function showNotification(message, type) {
    if (window.API?.showNotification)
      window.API.showNotification(message, type);
    else alert((type === "error" ? "Error: " : "") + message);
  }

  function attachListeners() {
    document
      .getElementById("classFilterLPC")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("coverageFilter")
      ?.addEventListener("change", () => loadData(1));
    document.getElementById("searchByClass")?.addEventListener("keyup", () => {
      clearTimeout(window._lpcSearchTimeout);
      window._lpcSearchTimeout = setTimeout(() => loadData(1), 300);
    });
    document
      .getElementById("exportByClassBtn")
      ?.addEventListener("click", () => {
        window.open(
          "/Kingsway/api/?route=academic/lesson-plans/by-class/export&format=csv",
          "_blank",
        );
      });
  }

  async function init() {
    attachListeners();
    await loadData();
  }

  return { init, refresh: loadData, loadPage: loadData, viewDetail };
})();

document.addEventListener("DOMContentLoaded", () =>
  LessonPlansByClassController.init(),
);
