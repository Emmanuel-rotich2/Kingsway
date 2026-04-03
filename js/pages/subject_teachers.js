/**
 * Subject Teachers Page Controller
 * Displays teacher workload, subjects, and qualifications
 */
const SubjectTeachersController = (() => {
  let teachers = [];
  let pagination = { page: 1, limit: 15, total: 0 };

  async function loadData(page = 1) {
    try {
      pagination.page = page;
      const params = new URLSearchParams({ page, limit: pagination.limit });

      const search = document.getElementById("searchSubjectTeachers")?.value;
      if (search) params.append("search", search);
      const dept = document.getElementById("departmentFilterST")?.value;
      if (dept) params.append("department", dept);
      const empType = document.getElementById("employmentTypeFilter")?.value;
      if (empType) params.append("employment_type", empType);
      const status = document.getElementById("statusFilterST")?.value;
      if (status) params.append("status", status);

      const response = await window.API.apiCall(
        `/academic/subject-teachers?${params.toString()}`,
        "GET",
      );
      const data = response?.data || response || [];
      teachers = Array.isArray(data) ? data : data.teachers || data.data || [];
      if (data.pagination) pagination = { ...pagination, ...data.pagination };
      pagination.total = data.total || teachers.length;

      renderStats(teachers);
      renderTable(teachers);
      renderPagination();
    } catch (e) {
      console.error("Load subject teachers failed:", e);
      renderTable([]);
    }
  }

  function renderStats(data) {
    const total = pagination.total || data.length;
    const fullTime = data.filter(
      (t) => (t.employment_type || t.type || "").toLowerCase() === "full-time",
    ).length;
    const contract = data.filter((t) =>
      ["contract", "part-time"].includes(
        (t.employment_type || t.type || "").toLowerCase(),
      ),
    ).length;
    const totalPeriods = data.reduce(
      (sum, t) => sum + parseInt(t.periods_per_week || t.total_periods || 0),
      0,
    );
    const avgWorkload =
      data.length > 0 ? Math.round(totalPeriods / data.length) : 0;

    document.getElementById("totalTeachers").textContent = total;
    document.getElementById("fullTimeTeachers").textContent = fullTime;
    document.getElementById("contractTeachers").textContent = contract;
    document.getElementById("avgWorkload").textContent = avgWorkload;
  }

  function renderTable(items) {
    const tbody = document.getElementById("subjectTeachersTableBody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center py-4 text-muted">No teachers found</td></tr>';
      return;
    }

    tbody.innerHTML = items
      .map((t, i) => {
        const subjects = t.subjects || t.subject_names || "-";
        const classesAssigned = t.classes || t.class_names || "-";
        return `
                <tr>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td><strong>${t.name || ((t.first_name || "") + " " + (t.last_name || "")).trim() || "-"}</strong></td>
                    <td><span class="badge bg-secondary">${t.employee_id || t.emp_id || "-"}</span></td>
                    <td>${Array.isArray(subjects) ? subjects.join(", ") : subjects}</td>
                    <td>${Array.isArray(classesAssigned) ? classesAssigned.join(", ") : classesAssigned}</td>
                    <td><span class="badge bg-${parseInt(t.periods_per_week || t.total_periods || 0) > 25 ? "danger" : "primary"}">${t.periods_per_week || t.total_periods || 0}</span></td>
                    <td>${t.qualification || t.highest_qualification || "-"}</td>
                    <td><span class="badge bg-${t.status === "active" ? "success" : "secondary"}">${t.status || "active"}</span></td>
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
                <a class="page-link" href="#" onclick="SubjectTeachersController.loadPage(${i}); return false;">${i}</a>
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
      .getElementById("searchSubjectTeachers")
      ?.addEventListener("keyup", () => {
        clearTimeout(window._stSearchTimeout);
        window._stSearchTimeout = setTimeout(() => loadData(1), 300);
      });
    document
      .getElementById("departmentFilterST")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("employmentTypeFilter")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("statusFilterST")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("exportSubjectTeachersBtn")
      ?.addEventListener("click", () => {
        window.open(
          (window.APP_BASE || "") + "/api/?route=academic/subject-teachers/export&format=csv",
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
  SubjectTeachersController.init(),
);
