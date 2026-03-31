/**
 * Staff Performance Page Controller
 * Loads staff list, KPI summaries, and review history.
 */

const StaffPerformanceController = {
  staff: [],
  reviews: [],
  kpis: [],
  charts: {},
  currentYearId: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.bindEvents();
    await this.loadStaffList();
    await this.loadCurrentAcademicYear();
  },

  bindEvents: function () {
    const generateBtn = document.getElementById("generateBtn");
    if (generateBtn) {
      generateBtn.addEventListener("click", () => this.generateReport());
    }
  },

  loadStaffList: async function () {
    try {
      const response = await window.API.staff.index();
      const list = this.extractStaffList(response);
      this.staff = list;
      this.populateStaffDropdown();
    } catch (error) {
      console.error("Error loading staff list:", error);
    }
  },

  loadCurrentAcademicYear: async function () {
    try {
      const response = await window.API.academic.getCurrentAcademicYear();
      const year = response?.data || response || null;
      this.currentYearId = year?.id || null;
    } catch (error) {
      console.warn("Error loading current academic year:", error);
    }
  },

  extractStaffList: function (response) {
    if (!response) return [];
    if (Array.isArray(response)) return response;
    if (Array.isArray(response.data?.staff)) return response.data.staff;
    if (Array.isArray(response.data)) return response.data;
    if (Array.isArray(response.staff)) return response.staff;
    return [];
  },

  populateStaffDropdown: function () {
    const select = document.getElementById("staffSelect");
    if (!select) return;

    const firstOpt = select.options[0];
    select.innerHTML = "";
    if (firstOpt) select.appendChild(firstOpt);

    this.staff.forEach((s) => {
      const option = document.createElement("option");
      option.value = s.id;
      option.textContent = `${s.first_name || ""} ${s.last_name || ""}`.trim();
      select.appendChild(option);
    });
  },

  generateReport: async function () {
    const staffId = document.getElementById("staffSelect")?.value || "";
    if (!staffId) {
      showNotification(
        "Select a staff member to generate a report.",
        "warning",
      );
      return;
    }

    await Promise.all([
      this.loadReviewHistory(staffId),
      this.loadKpiSummary(staffId),
    ]);

    this.renderSummaryCards();
    this.renderKpiBadges();
    this.renderPerformanceTable();
    this.renderAppraisalHistory();
    this.renderCharts();
  },

  loadReviewHistory: async function (staffId) {
    try {
      const response =
        await window.API.staff.getPerformanceReviewHistory(staffId);
      const payload = response?.data || response || {};
      const reviews = payload.reviews || payload.data?.reviews || [];
      this.reviews = Array.isArray(reviews) ? reviews : [];
    } catch (error) {
      console.error("Error loading review history:", error);
      this.reviews = [];
    }
  },

  loadKpiSummary: async function (staffId) {
    try {
      const params = this.currentYearId
        ? { academic_year_id: this.currentYearId }
        : {};
      const response = await window.API.staff.getAcademicKPISummary(
        staffId,
        params,
      );
      const payload = response?.data || response || {};
      const kpis = payload.kpis || payload.data?.kpis || [];
      this.kpis = Array.isArray(kpis) ? kpis : [];
    } catch (error) {
      console.error("Error loading KPI summary:", error);
      this.kpis = [];
    }
  },

  renderSummaryCards: function () {
    const overallScores = this.reviews
      .map((r) => Number(r.overall_score || 0))
      .filter((n) => !Number.isNaN(n) && n > 0);

    const avgScore = overallScores.length
      ? overallScores.reduce((a, b) => a + b, 0) / overallScores.length
      : 0;

    const avgRating = avgScore ? (avgScore / 20).toFixed(1) : "0.0";

    const attendanceRate = this.getKpiPercent(["attendance", "punctuality"]);
    const tasksCompleted = this.getKpiPercent(["task", "completion"]);
    const passRate = this.getKpiPercent(["student", "performance", "results"]);

    const avgRatingEl = document.getElementById("avgRating");
    const attendanceEl = document.getElementById("attendanceRate");
    const tasksEl = document.getElementById("tasksCompleted");
    const passRateEl = document.getElementById("passRate");

    if (avgRatingEl) avgRatingEl.textContent = avgRating;
    if (attendanceEl) attendanceEl.textContent = `${attendanceRate}%`;
    if (tasksEl) tasksEl.textContent = Math.round(tasksCompleted / 10);
    if (passRateEl) passRateEl.textContent = `${passRate}%`;
  },

  renderPerformanceTable: function () {
    const tbody = document.querySelector("#performanceTable tbody");
    if (!tbody) return;

    if (!this.reviews.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center text-muted py-3">No performance data found</td></tr>';
      return;
    }

    const latest = this.reviews[0];
    const attendance = this.getKpiPercent(["attendance", "punctuality"]);
    const punctuality = this.getKpiPercent(["punctuality"]);
    const tasks = this.getKpiPercent(["task", "completion"]);
    const studentResults = this.getKpiPercent([
      "student",
      "performance",
      "results",
    ]);
    const rating = latest.performance_grade || latest.overall_score || "-";

    tbody.innerHTML = `
            <tr>
                <td>${latest.staff_name || "-"}</td>
                <td>${latest.department || "-"}</td>
                <td>${attendance}%</td>
                <td>${punctuality}%</td>
                <td>${tasks}%</td>
                <td>${studentResults}%</td>
                <td>${rating}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="StaffPerformanceController.showReport(${latest.review_id})">
                        <i class="bi bi-file-earmark-text"></i>
                    </button>
                </td>
            </tr>
        `;
  },

  renderKpiBadges: function () {
    const mappings = [
      { id: "lessonPlanningScore", keys: ["lesson", "planning"] },
      { id: "studentPerfScore", keys: ["student", "performance", "results"] },
      { id: "classroomMgmtScore", keys: ["classroom", "management"] },
      { id: "profDevScore", keys: ["professional", "development"] },
      { id: "punctualityScore", keys: ["punctuality", "attendance"] },
      { id: "teamworkScore", keys: ["teamwork", "collaboration"] },
      { id: "communicationScore", keys: ["communication"] },
      { id: "initiativeScore", keys: ["initiative", "innovation"] },
    ];

    mappings.forEach((m) => {
      const el = document.getElementById(m.id);
      if (!el) return;
      const value = this.getKpiPercent(m.keys);
      el.textContent = value ? `${value}%` : "-";
    });
  },

  renderAppraisalHistory: function () {
    const tbody = document.getElementById("appraisalHistoryBody");
    if (!tbody) return;

    if (!this.reviews.length) {
      tbody.innerHTML =
        '<tr><td colspan="6" class="text-center text-muted py-3">No appraisal history found</td></tr>';
      return;
    }

    tbody.innerHTML = this.reviews
      .map((r) => {
        return `
                    <tr>
                        <td>${r.review_date || "-"}</td>
                        <td>${r.review_period || "-"}</td>
                        <td>${r.reviewer_name || "-"}</td>
                        <td>${r.overall_score || "-"}</td>
                        <td>${r.performance_grade || "-"}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" onclick="StaffPerformanceController.showReport(${r.review_id})">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
      })
      .join("");
  },

  renderCharts: function () {
    if (!window.Chart) return;

    const metrics = [
      this.getKpiPercent(["attendance"]),
      this.getKpiPercent(["punctuality"]),
      this.getKpiPercent(["task", "completion"]),
      this.getKpiPercent(["student", "performance"]),
    ];

    const radar = document.getElementById("metricsRadarChart");
    if (radar) {
      if (this.charts.radar) this.charts.radar.destroy();
      this.charts.radar = new Chart(radar, {
        type: "radar",
        data: {
          labels: ["Attendance", "Punctuality", "Tasks", "Results"],
          datasets: [
            {
              label: "KPI Achievement",
              data: metrics,
              backgroundColor: "rgba(13, 110, 253, 0.2)",
              borderColor: "rgba(13, 110, 253, 0.8)",
            },
          ],
        },
        options: {
          responsive: true,
          scales: { r: { beginAtZero: true, max: 100 } },
        },
      });
    }

    const trend = document.getElementById("trendLineChart");
    if (trend) {
      if (this.charts.trend) this.charts.trend.destroy();

      const labels = this.reviews
        .map((r) => r.review_date || r.review_period || "")
        .reverse();
      const scores = this.reviews
        .map((r) => Number(r.overall_score || 0))
        .reverse();

      this.charts.trend = new Chart(trend, {
        type: "line",
        data: {
          labels,
          datasets: [
            {
              label: "Overall Score",
              data: scores,
              borderColor: "rgba(25, 135, 84, 0.8)",
              backgroundColor: "rgba(25, 135, 84, 0.2)",
              tension: 0.3,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          scales: { y: { beginAtZero: true, max: 100 } },
        },
      });
    }
  },

  getKpiPercent: function (keywords) {
    if (!this.kpis.length) return 0;

    const lowered = keywords.map((k) => k.toLowerCase());
    const match = this.kpis.find((kpi) => {
      const name = `${kpi.kpi_name || ""} ${kpi.kpi_code || ""}`.toLowerCase();
      return lowered.some((key) => name.includes(key));
    });

    if (!match) return 0;
    const value = Number(match.achievement_percentage || 0);
    return Number.isNaN(value) ? 0 : Math.round(value);
  },

  showReport: async function (reviewId) {
    try {
      const response =
        await window.API.staff.generatePerformanceReport(reviewId);
      const payload = response?.data || response || {};
      showNotification(
        payload?.message || "Performance report generated.",
        "info",
      );
    } catch (error) {
      console.error("Error generating report:", error);
      showNotification("Failed to generate report.", "error");
    }
  },
};

document.addEventListener("DOMContentLoaded", () =>
  StaffPerformanceController.init(),
);
