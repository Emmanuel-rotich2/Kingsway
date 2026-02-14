/**
 * Performance Trends Controller
 * Page: performance_trends.php
 * Academic performance analysis, trends by term/class/subject
 */
const PerformanceTrendsController = {
  state: {
    years: [],
    classes: [],
    subjects: [],
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
    const analyzeBtn =
      document.getElementById("exportAnalysis") ||
      document.querySelector('[onclick*="analyze"]');
    // Year/class/subject filter changes
    ["academicYear", "classFilter", "subjectFilter", "analysisType"].forEach(
      (id) => {
        const el = document.getElementById(id);
        if (el) el.addEventListener("change", () => this.analyze());
      },
    );
  },

  async loadFilters() {
    try {
      const [yearsRes, classesRes, subjectsRes] = await Promise.all([
        window.API.academic.getAllAcademicYears(),
        window.API.academic.listClasses(),
        window.API.academic.listLearningAreas(),
      ]);

      if (yearsRes?.success) {
        this.state.years = yearsRes.data || [];
        this.populateSelect("#academicYear", this.state.years, "id", "name");
      }
      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
        this.populateSelect("#classFilter", this.state.classes, "id", "name");
      }
      if (subjectsRes?.success) {
        this.state.subjects = subjectsRes.data || [];
        this.populateSelect(
          "#subjectFilter",
          this.state.subjects,
          "id",
          "name",
        );
      }

      // Auto-analyze with defaults
      this.analyze();
    } catch (error) {
      console.error("Error loading filters:", error);
    }
  },

  async analyze() {
    try {
      const yearId = document.getElementById("academicYear")?.value;
      const classId = document.getElementById("classFilter")?.value;
      const subjectId = document.getElementById("subjectFilter")?.value;

      const params = {};
      if (yearId) params.academic_year_id = yearId;
      if (classId) params.class_id = classId;
      if (subjectId) params.subject_id = subjectId;

      const res =
        (await window.API.academic.analyzeResults(params).catch(() => null)) ||
        (await window.API.academic.compileData(params).catch(() => null));

      if (res?.success && res.data) {
        this.renderTermPerformanceChart(res.data);
        this.renderSubjectComparisonChart(res.data);
        this.renderTopImproving(res.data);
        this.renderNeedAttention(res.data);
      }
    } catch (error) {
      console.error("Error analyzing performance:", error);
    }
  },

  renderTermPerformanceChart(data) {
    const canvas = document.getElementById("termPerformanceChart");
    if (!canvas || typeof Chart === "undefined") return;
    if (this.state.charts.termPerf) this.state.charts.termPerf.destroy();

    const terms = data.term_trends || data.trends || [];
    this.state.charts.termPerf = new Chart(canvas.getContext("2d"), {
      type: "line",
      data: {
        labels: terms.map((t) => t.term_name || t.term || ""),
        datasets: [
          {
            label: "Mean Score",
            data: terms.map((t) => parseFloat(t.mean || t.average || 0)),
            borderColor: "#007bff",
            backgroundColor: "rgba(0,123,255,0.1)",
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
  },

  renderSubjectComparisonChart(data) {
    const canvas = document.getElementById("subjectComparisonChart");
    if (!canvas || typeof Chart === "undefined") return;
    if (this.state.charts.subjectComp) this.state.charts.subjectComp.destroy();

    const subjects = data.subject_performance || data.subjects || [];
    const colors = [
      "#007bff",
      "#28a745",
      "#ffc107",
      "#dc3545",
      "#17a2b8",
      "#6f42c1",
      "#fd7e14",
      "#20c997",
    ];

    this.state.charts.subjectComp = new Chart(canvas.getContext("2d"), {
      type: "bar",
      data: {
        labels: subjects.map((s) => s.name || s.subject_name || ""),
        datasets: [
          {
            label: "Average Score",
            data: subjects.map((s) =>
              parseFloat(s.average || s.avg_score || 0),
            ),
            backgroundColor: subjects.map((_, i) => colors[i % colors.length]),
          },
        ],
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: true, max: 100 } },
      },
    });
  },

  renderTopImproving(data) {
    const container = document.getElementById("topImproving");
    if (!container) return;

    const students = data.top_improving || data.improving_students || [];
    if (students.length === 0) {
      container.innerHTML =
        '<p class="text-muted text-center">No data available</p>';
      return;
    }

    container.innerHTML = `<div class="list-group list-group-flush">
            ${students
              .slice(0, 5)
              .map(
                (s, i) => `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-success me-2">${i + 1}</span>
                        ${this.escapeHtml(s.name || s.student_name || "")}
                        <small class="text-muted ms-2">${this.escapeHtml(s.class_name || "")}</small>
                    </div>
                    <span class="text-success fw-bold">+${s.improvement || s.change || 0}%</span>
                </div>`,
              )
              .join("")}
        </div>`;
  },

  renderNeedAttention(data) {
    const container = document.getElementById("needAttention");
    if (!container) return;

    const students = data.need_attention || data.declining_students || [];
    if (students.length === 0) {
      container.innerHTML =
        '<p class="text-muted text-center">All students performing well</p>';
      return;
    }

    container.innerHTML = `<div class="list-group list-group-flush">
            ${students
              .slice(0, 5)
              .map(
                (s, i) => `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-danger me-2">${i + 1}</span>
                        ${this.escapeHtml(s.name || s.student_name || "")}
                        <small class="text-muted ms-2">${this.escapeHtml(s.class_name || "")}</small>
                    </div>
                    <span class="text-danger fw-bold">${s.decline || s.change || 0}%</span>
                </div>`,
              )
              .join("")}
        </div>`;
  },

  populateSelect(selector, items, valueKey, labelKey) {
    const select = document.querySelector(selector);
    if (!select) return;
    const first = select.querySelector("option");
    select.innerHTML = "";
    if (first) select.appendChild(first);
    items.forEach((item) => {
      const opt = document.createElement("option");
      opt.value = item[valueKey];
      opt.textContent = item[labelKey] || item.name || "";
      select.appendChild(opt);
    });
  },
  escapeHtml(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  },
};

document.addEventListener("DOMContentLoaded", () =>
  PerformanceTrendsController.init(),
);
