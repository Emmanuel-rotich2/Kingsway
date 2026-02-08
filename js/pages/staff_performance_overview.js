/**
 * Staff Performance Overview Page Controller
 * Manages staff performance data display, charts, and filtering
 */
const StaffPerformanceController = (() => {
  let performanceData = [];
  let pagination = { page: 1, limit: 15, total: 0 };
  let chartInstances = {};

  async function loadData(page = 1) {
    try {
      pagination.page = page;
      const params = new URLSearchParams({ page, limit: pagination.limit });

      const dept = document.getElementById("departmentFilterPerf")?.value;
      if (dept) params.append("department", dept);
      const rating = document.getElementById("ratingFilter")?.value;
      if (rating) params.append("rating", rating);
      const period = document.getElementById("periodFilter")?.value;
      if (period) params.append("period", period);
      const search = document.getElementById("searchPerformance")?.value;
      if (search) params.append("search", search);

      const response = await window.API.apiCall(
        `/staff/performance?${params.toString()}`,
        "GET",
      );
      const data = response?.data || response || [];
      performanceData = Array.isArray(data)
        ? data
        : data.staff || data.data || [];
      if (data.pagination) pagination = { ...pagination, ...data.pagination };
      pagination.total = data.total || performanceData.length;

      renderStats(performanceData);
      renderTable(performanceData);
      renderPagination();
      renderCharts(performanceData);
    } catch (e) {
      console.error("Load performance failed:", e);
      renderTable([]);
    }
  }

  function renderStats(data) {
    const total = pagination.total || data.length;
    const excellent = data.filter(
      (d) => (d.rating || "").toLowerCase() === "excellent",
    ).length;
    const good = data.filter(
      (d) => (d.rating || "").toLowerCase() === "good",
    ).length;
    const needsImprovement = data.filter((d) =>
      ["needs_improvement", "needs improvement", "poor"].includes(
        (d.rating || "").toLowerCase(),
      ),
    ).length;

    document.getElementById("totalStaff").textContent = total;
    document.getElementById("excellentCount").textContent = excellent;
    document.getElementById("goodCount").textContent = good;
    document.getElementById("needsImprovementCount").textContent =
      needsImprovement;
  }

  function renderTable(items) {
    const tbody = document.getElementById("performanceTableBody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center py-4 text-muted">No performance data found</td></tr>';
      return;
    }

    tbody.innerHTML = items
      .map((s, i) => {
        const ratingColor =
          {
            excellent: "success",
            good: "info",
            average: "warning",
            needs_improvement: "danger",
            poor: "danger",
          }[(s.rating || "").toLowerCase()] || "secondary";
        const attendance = s.attendance_percentage || s.attendance || 0;
        const attendanceColor =
          attendance >= 90
            ? "success"
            : attendance >= 75
              ? "warning"
              : "danger";

        return `
                <tr>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td><strong>${s.name || ((s.first_name || "") + " " + (s.last_name || "")).trim() || "-"}</strong></td>
                    <td>${s.department || s.department_name || "-"}</td>
                    <td>${s.role || s.position || "-"}</td>
                    <td><span class="badge bg-${ratingColor}">${s.rating || "-"}</span></td>
                    <td><span class="text-${attendanceColor} fw-bold">${attendance}%</span></td>
                    <td>${s.tasks_completed || s.tasks || 0}</td>
                    <td>
                        <button class="btn btn-info btn-sm" onclick="StaffPerformanceController.viewDetail(${s.id})" title="View Details">
                            <i class="bi bi-eye"></i>
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
                <a class="page-link" href="#" onclick="StaffPerformanceController.loadPage(${i}); return false;">${i}</a>
            </li>`;
    }
    container.innerHTML = html;
  }

  function renderCharts(data) {
    if (!window.Chart) return;

    // Performance Distribution Bar Chart
    const barCanvas = document.getElementById("performanceChart");
    if (barCanvas) {
      if (chartInstances.bar) chartInstances.bar.destroy();

      const deptMap = {};
      data.forEach((s) => {
        const d = s.department || s.department_name || "Other";
        if (!deptMap[d])
          deptMap[d] = {
            excellent: 0,
            good: 0,
            average: 0,
            needs_improvement: 0,
          };
        const r = (s.rating || "").toLowerCase().replace(" ", "_");
        if (deptMap[d][r] !== undefined) deptMap[d][r]++;
      });

      const labels = Object.keys(deptMap);
      chartInstances.bar = new Chart(barCanvas, {
        type: "bar",
        data: {
          labels,
          datasets: [
            {
              label: "Excellent",
              data: labels.map((l) => deptMap[l].excellent),
              backgroundColor: "rgba(25,135,84,0.7)",
            },
            {
              label: "Good",
              data: labels.map((l) => deptMap[l].good),
              backgroundColor: "rgba(13,202,240,0.7)",
            },
            {
              label: "Average",
              data: labels.map((l) => deptMap[l].average),
              backgroundColor: "rgba(255,193,7,0.7)",
            },
            {
              label: "Needs Improvement",
              data: labels.map((l) => deptMap[l].needs_improvement),
              backgroundColor: "rgba(220,53,69,0.7)",
            },
          ],
        },
        options: {
          responsive: true,
          scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true },
          },
        },
      });
    }

    // Rating Pie Chart
    const pieCanvas = document.getElementById("ratingPieChart");
    if (pieCanvas) {
      if (chartInstances.pie) chartInstances.pie.destroy();

      const excellent = data.filter(
        (d) => (d.rating || "").toLowerCase() === "excellent",
      ).length;
      const good = data.filter(
        (d) => (d.rating || "").toLowerCase() === "good",
      ).length;
      const average = data.filter(
        (d) => (d.rating || "").toLowerCase() === "average",
      ).length;
      const needs = data.filter((d) =>
        ["needs_improvement", "needs improvement", "poor"].includes(
          (d.rating || "").toLowerCase(),
        ),
      ).length;

      chartInstances.pie = new Chart(pieCanvas, {
        type: "doughnut",
        data: {
          labels: ["Excellent", "Good", "Average", "Needs Improvement"],
          datasets: [
            {
              data: [excellent, good, average, needs],
              backgroundColor: ["#198754", "#0dcaf0", "#ffc107", "#dc3545"],
            },
          ],
        },
        options: { responsive: true },
      });
    }
  }

  async function viewDetail(id) {
    try {
      const resp = await window.API.apiCall(`/staff/performance/${id}`, "GET");
      const s = resp?.data || resp;
      const content = document.getElementById("performanceDetailContent");
      content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Staff Information</h6>
                        <p><strong>Name:</strong> ${s.name || ((s.first_name || "") + " " + (s.last_name || "")).trim()}</p>
                        <p><strong>Department:</strong> ${s.department || s.department_name || "-"}</p>
                        <p><strong>Role:</strong> ${s.role || s.position || "-"}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Performance Metrics</h6>
                        <p><strong>Rating:</strong> <span class="badge bg-primary">${s.rating || "-"}</span></p>
                        <p><strong>Attendance:</strong> ${s.attendance_percentage || s.attendance || 0}%</p>
                        <p><strong>Tasks Completed:</strong> ${s.tasks_completed || s.tasks || 0}</p>
                    </div>
                </div>
                ${s.remarks ? `<div class="mt-3"><h6>Remarks</h6><p>${s.remarks}</p></div>` : ""}
            `;
      document.getElementById("perfDetailLabel").textContent =
        `Performance: ${s.name || ((s.first_name || "") + " " + (s.last_name || "")).trim()}`;
      new bootstrap.Modal(
        document.getElementById("performanceDetailModal"),
      ).show();
    } catch (e) {
      showNotification("Failed to load performance details", "error");
    }
  }

  function showNotification(message, type) {
    if (window.API?.showNotification)
      window.API.showNotification(message, type);
    else alert((type === "error" ? "Error: " : "") + message);
  }

  function attachListeners() {
    document
      .getElementById("departmentFilterPerf")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("ratingFilter")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("periodFilter")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("searchPerformance")
      ?.addEventListener("keyup", () => {
        clearTimeout(window._perfSearchTimeout);
        window._perfSearchTimeout = setTimeout(() => loadData(1), 300);
      });
    document
      .getElementById("exportPerformanceBtn")
      ?.addEventListener("click", () => {
        window.open(
          "/Kingsway/api/?route=staff/performance/export&format=csv",
          "_blank",
        );
      });
    document
      .getElementById("printPerformanceBtn")
      ?.addEventListener("click", () => window.print());
  }

  async function init() {
    attachListeners();
    await loadData();
  }

  return { init, refresh: loadData, loadPage: loadData, viewDetail };
})();

document.addEventListener("DOMContentLoaded", () =>
  StaffPerformanceController.init(),
);
