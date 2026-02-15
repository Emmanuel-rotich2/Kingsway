/**
 * Enrollment Reports Controller
 * Page: enrollment_reports.php
 * Generate enrollment reports with charts, gender breakdown, class breakdown
 */
const EnrollmentReportsController = {
  state: {
    data: [],
    years: [],
    classes: [],
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
      .getElementById("generateReportBtn")
      ?.addEventListener("click", () => this.generateReport());
    document
      .getElementById("exportExcelBtn")
      ?.addEventListener("click", () => this.exportCSV());
    document
      .getElementById("printBtn")
      ?.addEventListener("click", () => window.print());
  },

  async loadFilters() {
    try {
      const [yearsRes, classesRes] = await Promise.all([
        window.API.academic.getAllAcademicYears(),
        window.API.academic.listClasses(),
      ]);

      if (yearsRes?.success) {
        this.state.years = yearsRes.data || [];
        const sel = document.getElementById("academicYear");
        if (sel) {
          sel.innerHTML =
            '<option value="">Select Year</option>' +
            this.state.years
              .map(
                (y) =>
                  `<option value="${y.id}" ${y.is_current ? "selected" : ""}>${this.esc(y.name || y.year)}</option>`,
              )
              .join("");
        }
      }

      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
        const sel = document.getElementById("classLevel");
        if (sel) {
          sel.innerHTML =
            '<option value="">All Classes</option>' +
            this.state.classes
              .map(
                (c) => `<option value="${c.id}">${this.esc(c.name)}</option>`,
              )
              .join("");
        }
      }
    } catch (error) {
      console.error("Error loading filters:", error);
    }
  },

  async generateReport() {
    const yearId = document.getElementById("academicYear")?.value;
    const term = document.getElementById("term")?.value;
    const classLevel = document.getElementById("classLevel")?.value;

    if (!yearId) {
      this.showNotification("Please select academic year", "warning");
      return;
    }

    const btn = document.getElementById("generateReportBtn");
    if (btn) {
      btn.disabled = true;
      btn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
    }

    try {
      const res = await window.API.academic
        .getCustom({
          action: "enrollment-report",
          year_id: yearId,
          term,
          class_id: classLevel || undefined,
        })
        .catch(() => null);

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

    const totalBoys = data.reduce((s, d) => s + (d.boys || 0), 0);
    const totalGirls = data.reduce((s, d) => s + (d.girls || 0), 0);
    const total = totalBoys + totalGirls;
    const boarding = data.reduce((s, d) => s + (d.boarding || 0), 0);
    const day = data.reduce((s, d) => s + (d.day_scholars || 0), 0);

    el("totalStudents", total);
    el(
      "newAdmissions",
      data.reduce((s, d) => s + (d.new_admissions || 0), 0),
    );
    el(
      "transfersOut",
      data.reduce((s, d) => s + (d.transfers_out || 0), 0),
    );
    el(
      "retentionRate",
      total > 0
        ? Math.round(
            ((total -
              data.reduce(
                (s, d) => s + (d.transfers_out || d.dropouts || 0),
                0,
              )) /
              total) *
              100,
          ) + "%"
        : "0%",
    );
    el("totalBoys", totalBoys);
    el("totalGirls", totalGirls);
    el("grandTotal", total);
    el("totalBoarding", boarding);
    el("totalDay", day);
  },

  renderTable() {
    const tbody = document.querySelector("#enrollmentTable tbody");
    if (!tbody) return;

    if (this.state.data.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-3">No enrollment data. Click Generate Report.</td></tr>';
      return;
    }

    tbody.innerHTML = this.state.data
      .map(
        (d) => `
        <tr>
            <td>${this.esc(d.class_name || d.name || "")}</td>
            <td>${d.boys || 0}</td>
            <td>${d.girls || 0}</td>
            <td><strong>${(d.boys || 0) + (d.girls || 0)}</strong></td>
            <td>${d.boarding || 0}</td>
            <td>${d.day_scholars || 0}</td>
        </tr>`,
      )
      .join("");

    // Stream enrollment
    const streamTbody = document.getElementById("streamEnrollment");
    if (streamTbody) {
      streamTbody.innerHTML = this.state.data
        .map(
          (d) => `
            <tr><td>${this.esc(d.class_name || d.name || "")}</td><td>${(d.boys || 0) + (d.girls || 0)}</td></tr>`,
        )
        .join("");
    }
  },

  renderCharts() {
    if (typeof Chart === "undefined") return;
    const data = this.state.data;

    // Enrollment by class bar chart
    const ctx1 = document
      .getElementById("enrollmentByClassChart")
      ?.getContext("2d");
    if (ctx1) {
      if (this.state.charts.byClass) this.state.charts.byClass.destroy();
      this.state.charts.byClass = new Chart(ctx1, {
        type: "bar",
        data: {
          labels: data.map((d) => d.class_name || d.name),
          datasets: [
            {
              label: "Boys",
              data: data.map((d) => d.boys || 0),
              backgroundColor: "rgba(54,162,235,0.7)",
            },
            {
              label: "Girls",
              data: data.map((d) => d.girls || 0),
              backgroundColor: "rgba(255,99,132,0.7)",
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { position: "top" } },
          scales: { y: { beginAtZero: true } },
        },
      });
    }

    // Gender distribution doughnut
    const ctx3 = document.getElementById("genderChart")?.getContext("2d");
    if (ctx3) {
      if (this.state.charts.gender) this.state.charts.gender.destroy();
      const totalBoys = data.reduce((s, d) => s + (d.boys || 0), 0);
      const totalGirls = data.reduce((s, d) => s + (d.girls || 0), 0);
      this.state.charts.gender = new Chart(ctx3, {
        type: "doughnut",
        data: {
          labels: ["Boys", "Girls"],
          datasets: [
            {
              data: [totalBoys, totalGirls],
              backgroundColor: ["rgba(54,162,235,0.8)", "rgba(255,99,132,0.8)"],
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
    const headers = ["Class", "Boys", "Girls", "Total", "Boarding", "Day"];
    const rows = this.state.data.map((d) => [
      d.class_name || d.name,
      d.boys || 0,
      d.girls || 0,
      (d.boys || 0) + (d.girls || 0),
      d.boarding || 0,
      d.day_scholars || 0,
    ]);
    const csv = [headers, ...rows].map((r) => r.join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "enrollment_report.csv";
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

document.addEventListener('DOMContentLoaded', () => EnrollmentReportsController.init());
