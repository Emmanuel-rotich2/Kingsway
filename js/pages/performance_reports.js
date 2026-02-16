/**
 * Performance Reports Controller
 * Page: performance_reports.php
 * Generate academic performance reports with subject charts, grade distribution
 */
const PerformanceReportsController = {
  state: {
    data: [],
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
    document
      .getElementById("generateBtn")
      ?.addEventListener("click", () => this.generateReport());
    document
      .getElementById("exportReportBtn")
      ?.addEventListener("click", () => this.exportCSV());
    document
      .getElementById("printBtn")
      ?.addEventListener("click", () => window.print());
    document
      .getElementById("reportType")
      ?.addEventListener("change", () => this.updateTableHeaders());
  },

  async loadFilters() {
    try {
      const [yearsRes, classesRes, subjectsRes] = await Promise.all([
        window.API.academic.getAllAcademicYears(),
        window.API.academic.listClasses(),
        window.API.academic.listLearningAreas().catch(() => null),
      ]);

      if (yearsRes?.success) {
        const sel = document.getElementById("examTerm");
        if (sel) {
          const years = yearsRes.data || [];
          const currentYear = years.find((y) => y.is_current);
          if (currentYear) {
            const terms = await window.API.academic
              .listTerms(currentYear.id)
              .catch(() => ({ data: [] }));
            sel.innerHTML =
              '<option value="">Select Term</option>' +
              (terms.data || [])
                .map(
                  (t) =>
                    `<option value="${t.id}" ${t.is_current ? "selected" : ""}>${this.esc(currentYear.name)} - ${this.esc(t.name)}</option>`,
                )
                .join("");
          }
        }
      }

      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
        const sel = document.getElementById("classFilter");
        if (sel)
          sel.innerHTML =
            '<option value="">All Classes</option>' +
            this.state.classes
              .map(
                (c) => `<option value="${c.id}">${this.esc(c.name)}</option>`,
              )
              .join("");
      }

      if (subjectsRes?.success) {
        this.state.subjects = subjectsRes.data || [];
        const sel = document.getElementById("subjectFilter");
        if (sel) {
          const existing = sel.innerHTML;
          sel.innerHTML =
            '<option value="">All Subjects</option>' +
            this.state.subjects
              .map(
                (s) => `<option value="${s.id}">${this.esc(s.name)}</option>`,
              )
              .join("");
        }
      }
    } catch (error) {
      console.error("Error loading filters:", error);
    }
  },

  async generateReport() {
    const btn = document.getElementById("generateBtn");
    if (btn) {
      btn.disabled = true;
      btn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
    }

    try {
      const reportType =
        document.getElementById("reportType")?.value || "class_performance";
      const termId = document.getElementById("examTerm")?.value;
      const classId = document.getElementById("classFilter")?.value;
      const subjectId = document.getElementById("subjectFilter")?.value;

      const res = (await window.API.academic.compileData)
        ? window.API.academic.compileData({
            report_type: reportType,
            term_id: termId,
            class_id: classId,
            subject_id: subjectId,
          })
        : window.API.academic.getCustom({
            action: "performance-report",
            report_type: reportType,
            term_id: termId,
            class_id: classId,
            subject_id: subjectId,
          });

      this.state.data = res?.success ? res.data || [] : [];
      this.updateStats();
      this.renderTable();
      this.renderCharts();
    } catch (error) {
      console.error("Error generating report:", error);
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = "Generate Report";
      }
    }
  },

  updateStats() {
    const data = this.state.data;
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };

    const scores = data
      .map((d) => parseFloat(d.average || d.score || d.mean || 0))
      .filter((s) => s > 0);
    const avg =
      scores.length > 0
        ? (scores.reduce((s, v) => s + v, 0) / scores.length).toFixed(1)
        : 0;
    const passCount = scores.filter((s) => s >= 50).length;

    el("classAverage", avg + "%");
    el(
      "passRate",
      scores.length > 0
        ? Math.round((passCount / scores.length) * 100) + "%"
        : "0%",
    );
    el(
      "topScore",
      scores.length > 0 ? Math.max(...scores).toFixed(1) + "%" : "0%",
    );
    el("studentsCount", data.length);
  },

  updateTableHeaders() {
    const type = document.getElementById("reportType")?.value;
    const headers = document.getElementById("tableHeaders");
    if (!headers) return;

    if (type === "subject_analysis") {
      headers.innerHTML =
        "<th>#</th><th>Subject</th><th>Students</th><th>Mean</th><th>Highest</th><th>Lowest</th><th>Pass Rate</th>";
    } else {
      headers.innerHTML =
        "<th>#</th><th>Student</th><th>Class</th><th>Average</th><th>Grade</th><th>Position</th><th>Remarks</th>";
    }
  },

  renderTable() {
    const tbody = document.getElementById("tableBody");
    if (!tbody) return;

    if (this.state.data.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-3">No data. Click Generate Report.</td></tr>';
      return;
    }

    const type = document.getElementById("reportType")?.value;
    this.updateTableHeaders();

    if (type === "subject_analysis") {
      tbody.innerHTML = this.state.data
        .map(
          (d, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${this.esc(d.subject || d.name || "")}</td>
                <td>${d.students || d.count || 0}</td>
                <td><strong>${(d.mean || d.average || 0).toFixed ? parseFloat(d.mean || d.average || 0).toFixed(1) : d.mean || d.average || 0}%</strong></td>
                <td>${d.highest || d.max || 0}%</td>
                <td>${d.lowest || d.min || 0}%</td>
                <td>${d.pass_rate || 0}%</td>
            </tr>`,
        )
        .join("");
    } else {
      tbody.innerHTML = this.state.data
        .map((d, i) => {
          const avg = parseFloat(d.average || d.score || d.mean || 0);
          const grade =
            avg >= 80
              ? "A"
              : avg >= 70
                ? "B"
                : avg >= 60
                  ? "C"
                  : avg >= 50
                    ? "D"
                    : "E";
          const gradeColor = {
            A: "success",
            B: "primary",
            C: "info",
            D: "warning",
            E: "danger",
          };
          return `
                <tr>
                    <td>${i + 1}</td>
                    <td>${this.esc(d.student_name || d.name || "")}</td>
                    <td>${this.esc(d.class_name || d.form || "")}</td>
                    <td><strong>${avg.toFixed(1)}%</strong></td>
                    <td><span class="badge bg-${gradeColor[grade]}">${grade}</span></td>
                    <td>${d.position || i + 1}</td>
                    <td>${d.remarks || (avg >= 50 ? "Pass" : "Below Average")}</td>
                </tr>`;
        })
        .join("");
    }
  },

  renderCharts() {
    if (typeof Chart === "undefined") return;

    // Subject Performance Bar Chart
    const ctx1 = document
      .getElementById("subjectPerformanceChart")
      ?.getContext("2d");
    if (ctx1 && this.state.data.length > 0) {
      if (this.state.charts.subject) this.state.charts.subject.destroy();
      const labels = this.state.data
        .slice(0, 15)
        .map((d) => d.subject || d.name || d.student_name || "");
      const values = this.state.data
        .slice(0, 15)
        .map((d) => parseFloat(d.average || d.mean || d.score || 0));
      this.state.charts.subject = new Chart(ctx1, {
        type: "bar",
        data: {
          labels,
          datasets: [
            {
              label: "Average (%)",
              data: values,
              backgroundColor: "rgba(54,162,235,0.7)",
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

    // Grade Distribution Doughnut
    const ctx2 = document
      .getElementById("gradeDistributionChart")
      ?.getContext("2d");
    if (ctx2 && this.state.data.length > 0) {
      if (this.state.charts.grade) this.state.charts.grade.destroy();
      const grades = { A: 0, B: 0, C: 0, D: 0, E: 0 };
      this.state.data.forEach((d) => {
        const avg = parseFloat(d.average || d.score || d.mean || 0);
        if (avg >= 80) grades.A++;
        else if (avg >= 70) grades.B++;
        else if (avg >= 60) grades.C++;
        else if (avg >= 50) grades.D++;
        else grades.E++;
      });
      this.state.charts.grade = new Chart(ctx2, {
        type: "doughnut",
        data: {
          labels: Object.keys(grades),
          datasets: [
            {
              data: Object.values(grades),
              backgroundColor: [
                "#28a745",
                "#007bff",
                "#17a2b8",
                "#ffc107",
                "#dc3545",
              ],
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { position: "bottom" } },
        },
      });
    }
  },

  exportCSV() {
    if (this.state.data.length === 0) {
      this.showNotification("No data to export", "warning");
      return;
    }
    const type = document.getElementById("reportType")?.value;
    let headers, rows;
    if (type === "subject_analysis") {
      headers = [
        "#",
        "Subject",
        "Students",
        "Mean",
        "Highest",
        "Lowest",
        "Pass Rate",
      ];
      rows = this.state.data.map((d, i) => [
        i + 1,
        d.subject || d.name,
        d.students || 0,
        d.mean || d.average || 0,
        d.highest || 0,
        d.lowest || 0,
        d.pass_rate || 0,
      ]);
    } else {
      headers = ["#", "Student", "Class", "Average", "Grade", "Position"];
      rows = this.state.data.map((d, i) => {
        const avg = parseFloat(d.average || d.score || 0);
        return [
          i + 1,
          d.student_name || d.name,
          d.class_name || "",
          avg.toFixed(1),
          avg >= 80
            ? "A"
            : avg >= 70
              ? "B"
              : avg >= 60
                ? "C"
                : avg >= 50
                  ? "D"
                  : "E",
          d.position || i + 1,
        ];
      });
    }
    const csv = [headers, ...rows]
      .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(","))
      .join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "performance_report.csv";
    a.click();
    URL.revokeObjectURL(url);
  },

  esc(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  },
  showNotification(msg, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },
};

document.addEventListener('DOMContentLoaded', () => PerformanceReportsController.init());
