/**
 * Academic Reports Controller
 * Page: academic_reports.php
 * Generates performance reports, trends, class analysis
 */
const AcademicReportsController = {
  state: {
    years: [],
    terms: [],
    classes: [],
    reportData: null,
    charts: {},
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    this.bindEvents();
    await this.loadFilters();
  },

  bindEvents() {
    const yearSelect = document.getElementById("selectYear");
    if (yearSelect) {
      yearSelect.addEventListener("change", (e) =>
        this.loadTermsForYear(e.target.value),
      );
    }

    const generateBtn = document.getElementById("generateReport");
    if (generateBtn) {
      generateBtn.addEventListener("click", () => this.generateReport());
    }

    // Tab switching
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
      tab.addEventListener("shown.bs.tab", () => this.resizeCharts());
    });
  },

  async loadFilters() {
    try {
      const [yearsRes, classesRes] = await Promise.all([
        window.API.academic.getAllAcademicYears(),
        window.API.academic.listClasses(),
      ]);

      if (yearsRes?.success) {
        this.state.years = yearsRes.data || [];
        this.populateSelect("#selectYear", this.state.years, "id", "name");
      }

      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
        this.populateSelect("#selectClass", this.state.classes, "id", "name");
      }

      // Load current year terms
      const currentRes = await window.API.academic.getCurrentAcademicYear();
      if (currentRes?.success && currentRes.data) {
        const yearSelect = document.getElementById("selectYear");
        if (yearSelect) yearSelect.value = currentRes.data.id;
        await this.loadTermsForYear(currentRes.data.id);
      }
    } catch (error) {
      console.error("Error loading filters:", error);
    }
  },

  async loadTermsForYear(yearId) {
    if (!yearId) return;
    try {
      const res = await window.API.academic.listTerms({
        academic_year_id: yearId,
      });
      if (res?.success) {
        this.state.terms = res.data || [];
        this.populateSelect("#selectTerm", this.state.terms, "id", "name");
      }
    } catch (error) {
      console.error("Error loading terms:", error);
    }
  },

  async generateReport() {
    const yearId = document.getElementById("selectYear")?.value;
    const termId = document.getElementById("selectTerm")?.value;
    const reportType =
      document.getElementById("reportType")?.value || "overview";
    const classId = document.getElementById("selectClass")?.value;

    if (!yearId || !termId) {
      this.showNotification("Please select academic year and term", "warning");
      return;
    }

    try {
      const btn = document.getElementById("generateReport");
      if (btn) {
        btn.disabled = true;
        btn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
      }

      const params = {
        academic_year_id: yearId,
        term_id: termId,
        type: reportType,
      };
      if (classId) params.class_id = classId;

      const res = await window.API.academic.compileData(params);

      if (res?.success) {
        this.state.reportData = res.data;
        this.renderOverview();
        this.renderDetailedTable();
        this.renderTrends();
        this.showNotification("Report generated successfully", "success");
      } else {
        this.showNotification(
          res?.message || "Failed to generate report",
          "error",
        );
      }

      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-chart-bar me-1"></i>Generate Report';
      }
    } catch (error) {
      console.error("Error generating report:", error);
      this.showNotification("Error generating report", "error");
      const btn = document.getElementById("generateReport");
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-chart-bar me-1"></i>Generate Report';
      }
    }
  },

  renderOverview() {
    const data = this.state.reportData;
    if (!data) return;

    // Performance chart
    const perfCanvas = document.getElementById("performanceChart");
    if (perfCanvas && typeof Chart !== "undefined") {
      if (this.state.charts.performance)
        this.state.charts.performance.destroy();

      const subjects = data.subjects || data.subject_performance || [];
      this.state.charts.performance = new Chart(perfCanvas.getContext("2d"), {
        type: "bar",
        data: {
          labels: subjects.map((s) => s.name || s.subject_name || ""),
          datasets: [
            {
              label: "Average Score",
              data: subjects.map((s) =>
                parseFloat(s.average || s.avg_score || 0),
              ),
              backgroundColor: "rgba(54, 162, 235, 0.7)",
              borderColor: "rgba(54, 162, 235, 1)",
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          scales: { y: { beginAtZero: true, max: 100 } },
          plugins: { legend: { display: false } },
        },
      });
    }
  },

  renderTrends() {
    const data = this.state.reportData;
    if (!data) return;

    const trendsCanvas = document.getElementById("trendsChart");
    if (trendsCanvas && typeof Chart !== "undefined") {
      if (this.state.charts.trends) this.state.charts.trends.destroy();

      const trends = data.trends || data.term_trends || [];
      this.state.charts.trends = new Chart(trendsCanvas.getContext("2d"), {
        type: "line",
        data: {
          labels: trends.map((t) => t.term || t.term_name || ""),
          datasets: [
            {
              label: "Mean Score",
              data: trends.map((t) => parseFloat(t.mean || t.average || 0)),
              borderColor: "rgba(75, 192, 192, 1)",
              backgroundColor: "rgba(75, 192, 192, 0.2)",
              fill: true,
              tension: 0.3,
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

  renderDetailedTable() {
    const data = this.state.reportData;
    const tbody = document.querySelector("#detailedTable tbody");
    if (!tbody || !data) return;

    const students = data.students || data.student_results || [];
    if (students.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted">No data available</td></tr>';
      return;
    }

    tbody.innerHTML = students
      .map(
        (s, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${this.escapeHtml(s.name || s.student_name || "")}</td>
                <td>${this.escapeHtml(s.class_name || s.class || "")}</td>
                <td><strong>${parseFloat(s.total || s.total_marks || 0).toFixed(1)}</strong></td>
                <td>${parseFloat(s.average || s.mean || 0).toFixed(1)}</td>
                <td>${s.rank || s.position || "--"}</td>
                <td><span class="badge bg-${this.getGradeColor(s.grade || s.mean_grade)}">${s.grade || s.mean_grade || "--"}</span></td>
            </tr>`,
      )
      .join("");
  },

  resizeCharts() {
    Object.values(this.state.charts).forEach((chart) => {
      if (chart && typeof chart.resize === "function") chart.resize();
    });
  },

  getGradeColor(grade) {
    if (!grade) return "secondary";
    const g = grade.toUpperCase();
    if (g.startsWith("A")) return "success";
    if (g.startsWith("B")) return "primary";
    if (g.startsWith("C")) return "info";
    if (g.startsWith("D")) return "warning";
    return "danger";
  },

  populateSelect(selector, items, valueKey, labelKey) {
    const select = document.querySelector(selector);
    if (!select) return;
    const defaultOption =
      select.querySelector("option") || document.createElement("option");
    select.innerHTML = "";
    select.appendChild(defaultOption);
    items.forEach((item) => {
      const opt = document.createElement("option");
      opt.value = item[valueKey];
      opt.textContent = item[labelKey] || item.name || "";
      select.appendChild(opt);
    });
  },

  escapeHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  },

  showNotification(message, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },
};

document.addEventListener("DOMContentLoaded", () =>
  AcademicReportsController.init(),
);
