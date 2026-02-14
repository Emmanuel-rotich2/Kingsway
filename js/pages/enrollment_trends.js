/**
 * Enrollment Trends Controller
 * Page: enrollment_trends.php
 * Enrollment analytics, year-over-year comparisons
 */
const EnrollmentTrendsController = {
  state: {
    data: [],
    charts: {},
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    this.bindEvents();
    await this.loadData();
  },

  bindEvents() {
    const analyzeBtn = document.getElementById("analyzeBtn");
    if (analyzeBtn) analyzeBtn.addEventListener("click", () => this.loadData());

    const exportBtn = document.getElementById("exportReport");
    if (exportBtn)
      exportBtn.addEventListener("click", () => this.exportToCSV());
  },

  async loadData() {
    try {
      const yearRange = document.getElementById("yearRange")?.value;
      const groupBy = document.getElementById("groupBy")?.value || "year";

      const res = await window.API.academic
        .getCustom({
          action: "enrollment-trends",
          year_range: yearRange,
          group_by: groupBy,
        })
        .catch(() => null);

      if (res?.success) {
        this.state.data = res.data || [];
      } else {
        // Fallback: use years & classes data
        const [yearsRes, classesRes] = await Promise.all([
          window.API.academic.getAllAcademicYears(),
          window.API.academic.listClasses(),
        ]);
        const years = yearsRes?.data || [];
        const classes = classesRes?.data || [];
        this.state.data = years.map((y) => ({
          year: y.name,
          total_students:
            y.total_students ||
            classes.reduce((s, c) => s + parseInt(c.student_count || 0), 0),
          new_admissions: y.new_admissions || 0,
          graduates: y.graduates || 0,
        }));
      }

      this.renderTrendChart();
      this.renderDistributionChart();
      this.renderTable();
    } catch (error) {
      console.error("Error loading enrollment trends:", error);
    }
  },

  renderTrendChart() {
    const canvas = document.getElementById("enrollmentTrendChart");
    if (!canvas || typeof Chart === "undefined") return;
    if (this.state.charts.trend) this.state.charts.trend.destroy();

    const data = this.state.data;
    this.state.charts.trend = new Chart(canvas.getContext("2d"), {
      type: "line",
      data: {
        labels: data.map((d) => d.year || d.period || ""),
        datasets: [
          {
            label: "Total Students",
            data: data.map((d) => d.total_students || d.total || 0),
            borderColor: "#007bff",
            backgroundColor: "rgba(0,123,255,0.1)",
            fill: true,
            tension: 0.3,
          },
          {
            label: "New Admissions",
            data: data.map((d) => d.new_admissions || 0),
            borderColor: "#28a745",
            borderDash: [5, 5],
            fill: false,
          },
        ],
      },
      options: { responsive: true, scales: { y: { beginAtZero: true } } },
    });
  },

  renderDistributionChart() {
    const canvas = document.getElementById("distributionChart");
    if (!canvas || typeof Chart === "undefined") return;
    if (this.state.charts.dist) this.state.charts.dist.destroy();

    const latest = this.state.data[this.state.data.length - 1];
    if (!latest) return;

    this.state.charts.dist = new Chart(canvas.getContext("2d"), {
      type: "doughnut",
      data: {
        labels: ["Continuing", "New Admissions", "Graduates"],
        datasets: [
          {
            data: [
              (latest.total_students || 0) - (latest.new_admissions || 0),
              latest.new_admissions || 0,
              latest.graduates || 0,
            ],
            backgroundColor: ["#007bff", "#28a745", "#ffc107"],
          },
        ],
      },
      options: { responsive: true },
    });
  },

  renderTable() {
    const tbody = document.querySelector("#enrollmentTable tbody");
    if (!tbody) return;

    if (this.state.data.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="5" class="text-center text-muted py-4">No enrollment data available</td></tr>';
      return;
    }

    tbody.innerHTML = this.state.data
      .map((d) => {
        const growth = d.growth_rate || d.growth || "--";
        return `
            <tr>
                <td><strong>${this.escapeHtml(d.year || d.period || "")}</strong></td>
                <td>${(d.total_students || d.total || 0).toLocaleString()}</td>
                <td class="text-success">${(d.new_admissions || 0).toLocaleString()}</td>
                <td>${(d.graduates || 0).toLocaleString()}</td>
                <td>
                    ${
                      typeof growth === "number"
                        ? `<span class="text-${growth >= 0 ? "success" : "danger"}">${growth >= 0 ? "+" : ""}${growth}%</span>`
                        : growth
                    }
                </td>
            </tr>`;
      })
      .join("");
  },

  exportToCSV() {
    const rows = [
      ["Year", "Total Students", "New Admissions", "Graduates", "Growth"],
    ];
    this.state.data.forEach((d) => {
      rows.push([
        d.year || "",
        d.total_students || 0,
        d.new_admissions || 0,
        d.graduates || 0,
        d.growth_rate || "",
      ]);
    });
    const csv = rows.map((r) => r.join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `enrollment_trends_${new Date().toISOString().split("T")[0]}.csv`;
    a.click();
  },

  escapeHtml(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  },
};

document.addEventListener("DOMContentLoaded", () =>
  EnrollmentTrendsController.init(),
);
