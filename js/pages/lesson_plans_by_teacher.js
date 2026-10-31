/**
 * Lesson Plans by Teacher Page Controller
 * Displays lesson plan submission tracking per teacher
 * Integrates with AcademicContext for academic year awareness
 */
const LessonPlansByTeacherController = (() => {
  let teacherData = [];
  let pagination = { page: 1, limit: 15, total: 0 };
  let currentAcademicYear = null;
  let currentTerm = null;

  async function loadData(page = 1, options = {}) {
    try {
      const requestedLimit = Number(options.limit) || pagination.limit;
      if (!options.exportOnly) pagination.page = page;
      const params = new URLSearchParams({
        page,
        limit: requestedLimit,
        academic_year_id: currentAcademicYear || '',
        term_id: currentTerm || '',
      });
      if (!options.exportOnly) {
        const tbody = document.getElementById('byTeacherTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading teacher coverage…</td></tr>';
      }

      const dept = document.getElementById("departmentFilterLPT")?.value;
      if (dept) params.append("department", dept);
      const status = document.getElementById("submissionStatusFilter")?.value;
      if (status) params.append("submission_status", status);
      const search = document.getElementById("searchByTeacher")?.value;
      if (search) params.append("search", search);

      const response = await window.API.academic.getLessonPlansByTeacher(
        Object.fromEntries(params.entries()),
      );
      const data = response?.data || response || [];
      const rows = Array.isArray(data)
        ? data
        : data.teachers || data.data || [];
      if (options.exportOnly) return rows;
      teacherData = rows;
      if (data.pagination) pagination = { ...pagination, ...data.pagination };
      pagination.total = data.total ?? teacherData.length;

      populateDepartments(data.departments || []);
      renderStats(teacherData, data.stats || {});
      renderTable(teacherData);
      renderPagination();
    } catch (e) {
      console.error("Load teacher coverage failed:", e);
      if (options.exportOnly) throw e;
      renderError(e?.message || 'Could not load teacher lesson-plan coverage.');
    }
  }

  function renderStats(data, summary = {}) {
    const total = summary.total ?? pagination.total ?? data.length;
    const fully = summary.fully_submitted ?? data.filter((d) => Number(d.coverage_percentage || d.coverage || 0) >= 100).length;
    const partial = summary.partially_submitted ?? data.filter((d) => {
      const coverage = Number(d.coverage_percentage || d.coverage || 0);
      return coverage > 0 && coverage < 100;
    }).length;
    const none = summary.not_submitted ?? data.filter((d) => Number(d.submitted || d.plans_submitted || 0) === 0).length;

    document.getElementById("teachersTotal").textContent = total;
    document.getElementById("fullySubmitted").textContent = fully;
    document.getElementById("partiallySubmitted").textContent = partial;
    document.getElementById("notSubmitted").textContent = none;
  }

  function populateDepartments(departments) {
    const select = document.getElementById('departmentFilterLPT');
    if (!select || select.dataset.optionsLoaded === 'true') return;
    const selected = select.value;
    const escape = (value) => String(value).replace(/[&<>"']/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);
    select.innerHTML = '<option value="">All Departments</option>' + departments.map((name) =>
      `<option value="${escape(name)}">${escape(name)}</option>`
    ).join('');
    select.value = selected;
    select.dataset.optionsLoaded = 'true';
  }

  async function exportData() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) {
      showNotification('You do not have permission to export academic data.', 'error');
      return;
    }
    const rows = await loadData(1, { limit: 1000, exportOnly: true });
    if (!Array.isArray(rows)) return;
    const columns = [
      ['Teacher Name', 'teacher_name'], ['Department', 'department'],
      ['Plans Expected', 'plans_expected'], ['Submitted', 'submitted'],
      ['Approved', 'approved'], ['Pending', 'pending'], ['Coverage %', 'coverage_percentage'],
    ];
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const csv = [columns.map(([label]) => quote(label)).join(','), ...rows.map((row) =>
      columns.map(([, key]) => quote(row[key])).join(',')
    )].join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'lesson-plans-by-teacher.csv', 'text/csv;charset=utf-8;');
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
        const expected = Number(t.plans_expected || t.total_expected || 0);
        const submitted = Number(t.submitted || t.plans_submitted || 0);
        const approved = Number(t.approved || t.plans_approved || 0);
        const pending = Number(t.pending || t.plans_pending || 0);
        const teacherName = escapeHtml(t.teacher_name || t.name || ((t.first_name || "") + " " + (t.last_name || "")).trim() || "-");
        const department = escapeHtml(t.department || t.department_name || "-");
        const coverageDisplay = expected > 0
          ? `<div class="progress" style="height:20px;"><div class="progress-bar bg-${coverageColor}" style="width:${coverage}%">${coverage.toFixed(0)}%</div></div>`
          : '<span class="text-muted">No active assignments</span>';

        return `
                <tr>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td><strong>${teacherName}</strong></td>
                    <td>${department}</td>
                    <td>${expected}</td>
                    <td><span class="badge bg-primary">${submitted}</span></td>
                    <td><span class="badge bg-success">${approved}</span></td>
                    <td><span class="badge bg-warning">${pending}</span></td>
                    <td>${coverageDisplay}</td>
                </tr>
            `;
      })
      .join("");
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);
  }

  function renderError(message) {
    const tbody = document.getElementById('byTeacherTableBody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${escapeHtml(message)}</td></tr>`;
    ['teachersTotal', 'fullySubmitted', 'partiallySubmitted', 'notSubmitted'].forEach((id) => {
      const element = document.getElementById(id);
      if (element) element.textContent = '—';
    });
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

  function showNotification(message, type) { window.showNotification?.(message, type); }

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
      ?.addEventListener("click", () => exportData().catch((error) => {
        console.error('Lesson-plan coverage export failed:', error);
        showNotification('Could not export lesson-plan coverage.', 'error');
      }));
  }

  async function init() {
    // Initialize Academic Context if available
    if (window.AcademicContext) {
      // Subscribe to context changes
      window.AcademicContext.subscribe((context, event, data) => {
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          // Reload data when academic year or term changes
          loadData();
        }
      });
      
      // Ensure context is loaded
      if (!window.AcademicContext.isLoaded()) {
        await window.AcademicContext.init();
      }
      
      // Get current academic context
      currentAcademicYear = window.AcademicContext.getAcademicYearId();
      currentTerm = window.AcademicContext.getTermId();
    }
    
    attachListeners();
    window.APIRealtime?.register?.("lesson_plans_by_teacher", {
      refresh: () => loadData(pagination.page),
    }, ["academic"]);
    await loadData();
  }

  return { init, refresh: loadData, loadPage: loadData };
})();

document.addEventListener("DOMContentLoaded", () =>
  LessonPlansByTeacherController.init(),
);

window.LessonPlansByTeacherController = LessonPlansByTeacherController;
