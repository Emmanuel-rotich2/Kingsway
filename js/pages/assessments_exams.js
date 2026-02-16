/**
 * Assessments & Exams Controller
 * Page: assessments_exams.php
 * Manages exam schedules, supervision, grading, performance charts
 */
const AssessmentsExamsController = {
  state: {
    exams: [],
    schedules: [],
    gradingStatus: [],
    years: [],
    terms: [],
    classes: [],
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
    // Create exam form
    const createForm =
      document.getElementById("createExamForm") ||
      document.getElementById("examSetupForm");
    if (createForm) {
      createForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.createExam();
      });
    }

    // Tab switching
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tab) => {
      tab.addEventListener("shown.bs.tab", (e) => {
        const target =
          e.target.getAttribute("data-bs-target") ||
          e.target.getAttribute("href");
        if (target?.includes("grading")) this.loadGradingStatus();
        if (target?.includes("performance") || target?.includes("analysis"))
          this.renderCharts();
      });
    });
  },

  async loadData() {
    try {
      this.showLoading();
      const [classesRes, yearsRes] = await Promise.all([
        window.API.academic.listClasses(),
        window.API.academic.getCurrentAcademicYear(),
      ]);

      if (classesRes?.success) this.state.classes = classesRes.data || [];
      if (yearsRes?.success) {
        const year = yearsRes.data;
        if (year) {
          this.state.years = [year];
          const termsRes = await window.API.academic.listTerms({
            academic_year_id: year.id,
          });
          if (termsRes?.success) this.state.terms = termsRes.data || [];
        }
      }

      // Load exam schedules
      const schedulesRes = await window.API.academic.listSchedules();
      if (schedulesRes?.success) {
        this.state.schedules = schedulesRes.data || [];
      }

      this.updateStatCards();
      this.renderExamScheduleTable();
      this.renderSupervisionTable();
    } catch (error) {
      console.error("Error loading assessments data:", error);
      this.showError("Failed to load exam data");
    }
  },

  updateStatCards() {
    const schedules = this.state.schedules;
    const now = new Date().toISOString().split("T")[0];

    const upcoming = schedules.filter(
      (s) => s.exam_date >= now || s.status === "scheduled",
    );
    const pendingGrading = schedules.filter(
      (s) => s.status === "conducted" || s.status === "marking",
    );
    const completed = schedules.filter(
      (s) => s.status === "completed" || s.status === "graded",
    );
    const reportsReady = schedules.filter(
      (s) => s.status === "published" || s.reports_ready,
    );

    this.setText("#upcomingExamsCount", upcoming.length);
    this.setText("#pendingGradingCount", pendingGrading.length);
    this.setText("#completedExamsCount", completed.length);
    this.setText("#reportsReadyCount", reportsReady.length);
  },

  renderExamScheduleTable() {
    const tbody = document.querySelector("#examScheduleTable tbody");
    if (!tbody) return;

    const schedules = this.state.schedules;
    if (schedules.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">No exam schedules found</td></tr>';
      return;
    }

    tbody.innerHTML = schedules
      .slice(0, 20)
      .map((exam) => {
        const statusColors = {
          scheduled: "primary",
          conducted: "info",
          marking: "warning",
          completed: "success",
          published: "success",
          cancelled: "danger",
        };
        const statusColor = statusColors[exam.status] || "secondary";

        return `
            <tr>
                <td><strong>${this.escapeHtml(exam.name || exam.exam_name || "")}</strong></td>
                <td>${this.escapeHtml(exam.subject_name || exam.subject || "")}</td>
                <td>${this.escapeHtml(exam.class_name || exam.class || "")}</td>
                <td>${exam.exam_date || exam.date || "--"}</td>
                <td>${exam.start_time || "--"} - ${exam.end_time || "--"}</td>
                <td><span class="badge bg-${statusColor}">${exam.status || "pending"}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="AssessmentsExamsController.viewExam(${exam.id})" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${
                          exam.status === "conducted"
                            ? `
                        <button class="btn btn-outline-warning" onclick="AssessmentsExamsController.enterMarks(${exam.id})" title="Enter Marks">
                            <i class="fas fa-pen"></i>
                        </button>`
                            : ""
                        }
                        <button class="btn btn-outline-danger" onclick="AssessmentsExamsController.deleteExam(${exam.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
      })
      .join("");
  },

  renderSupervisionTable() {
    const tbody = document.querySelector("#supervisionTable tbody");
    if (!tbody) return;

    const now = new Date().toISOString().split("T")[0];
    const upcoming = this.state.schedules.filter(
      (s) => s.exam_date >= now || s.status === "scheduled",
    );

    if (upcoming.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="5" class="text-center text-muted py-4">No upcoming supervision duties</td></tr>';
      return;
    }

    tbody.innerHTML = upcoming
      .slice(0, 10)
      .map(
        (exam) => `
            <tr>
                <td>${exam.exam_date || "--"}</td>
                <td>${this.escapeHtml(exam.name || exam.exam_name || "")}</td>
                <td>${this.escapeHtml(exam.room || exam.venue || "--")}</td>
                <td>${this.escapeHtml(exam.supervisor || exam.invigilator || "TBA")}</td>
                <td>${exam.start_time || "--"} - ${exam.end_time || "--"}</td>
            </tr>`,
      )
      .join("");
  },

  async loadGradingStatus() {
    const tbody = document.querySelector("#gradingStatusTable tbody");
    if (!tbody) return;

    const pending = this.state.schedules.filter(
      (s) => s.status === "conducted" || s.status === "marking",
    );

    if (pending.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="5" class="text-center text-muted py-4">All grading complete</td></tr>';
      return;
    }

    tbody.innerHTML = pending
      .map((exam) => {
        const progress = parseInt(exam.grading_progress || 0);
        return `
            <tr>
                <td>${this.escapeHtml(exam.name || exam.exam_name || "")}</td>
                <td>${this.escapeHtml(exam.subject_name || "")}</td>
                <td>${this.escapeHtml(exam.class_name || "")}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-${progress >= 100 ? "success" : progress >= 50 ? "info" : "warning"}" style="width: ${progress}%">${progress}%</div>
                    </div>
                </td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="AssessmentsExamsController.enterMarks(${exam.id})">
                        <i class="fas fa-pen me-1"></i>Grade
                    </button>
                </td>
            </tr>`;
      })
      .join("");
  },

  renderCharts() {
    // Class performance chart
    const perfCanvas = document.getElementById("classPerformanceChart");
    if (perfCanvas && typeof Chart !== "undefined") {
      if (this.state.charts.performance)
        this.state.charts.performance.destroy();

      const classData = {};
      this.state.schedules.forEach((s) => {
        if (s.class_name && s.average_score) {
          if (!classData[s.class_name]) classData[s.class_name] = [];
          classData[s.class_name].push(parseFloat(s.average_score));
        }
      });

      const labels = Object.keys(classData);
      const avgScores = labels.map((l) => {
        const scores = classData[l];
        return scores.reduce((a, b) => a + b, 0) / scores.length;
      });

      this.state.charts.performance = new Chart(perfCanvas.getContext("2d"), {
        type: "bar",
        data: {
          labels,
          datasets: [
            {
              label: "Average Score",
              data: avgScores,
              backgroundColor: "rgba(54, 162, 235, 0.7)",
            },
          ],
        },
        options: {
          responsive: true,
          scales: { y: { beginAtZero: true, max: 100 } },
        },
      });
    }

    // Grade distribution chart
    const distCanvas = document.getElementById("gradeDistributionChart");
    if (distCanvas && typeof Chart !== "undefined") {
      if (this.state.charts.distribution)
        this.state.charts.distribution.destroy();

      this.state.charts.distribution = new Chart(distCanvas.getContext("2d"), {
        type: "doughnut",
        data: {
          labels: ["A", "B", "C", "D", "E"],
          datasets: [
            {
              data: [15, 25, 30, 20, 10],
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
        options: { responsive: true },
      });
    }
  },

  async createExam() {
    const form =
      document.getElementById("createExamForm") ||
      document.getElementById("examSetupForm");
    if (!form) return;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
      const res = await window.API.academic.createSchedule(data);
      if (res?.success) {
        this.showNotification("Exam created successfully", "success");
        const modal = bootstrap.Modal.getInstance(
          document.getElementById("createExamModal"),
        );
        if (modal) modal.hide();
        form.reset();
        await this.loadData();
      } else {
        this.showNotification(res?.message || "Failed to create exam", "error");
      }
    } catch (error) {
      console.error("Error creating exam:", error);
      this.showNotification("Error creating exam", "error");
    }
  },

  async viewExam(examId) {
    try {
      const res = await window.API.academic.getSchedule(examId);
      if (res?.success && res.data) {
        const exam = res.data;
        const html = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Exam:</strong> ${this.escapeHtml(exam.name || "")}</p>
                            <p><strong>Subject:</strong> ${this.escapeHtml(exam.subject_name || "")}</p>
                            <p><strong>Class:</strong> ${this.escapeHtml(exam.class_name || "")}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Date:</strong> ${exam.exam_date || "--"}</p>
                            <p><strong>Time:</strong> ${exam.start_time || "--"} - ${exam.end_time || "--"}</p>
                            <p><strong>Status:</strong> <span class="badge bg-info">${exam.status || "pending"}</span></p>
                        </div>
                    </div>`;
        this.showModal("Exam Details", html);
      }
    } catch (error) {
      console.error("Error viewing exam:", error);
    }
  },

  enterMarks(examId) {
    window.location.href = `/Kingsway/pages/enter_results.php?exam_id=${examId}`;
  },

  async deleteExam(examId) {
    if (!confirm("Are you sure you want to delete this exam?")) return;
    try {
      const res = await window.API.academic.deleteSchedule(examId);
      if (res?.success) {
        this.showNotification("Exam deleted", "success");
        await this.loadData();
      }
    } catch (error) {
      console.error("Error deleting exam:", error);
    }
  },

  // Helpers
  setText(sel, val) {
    const el = document.querySelector(sel);
    if (el) el.textContent = val;
  },
  escapeHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  },
  showLoading() {
    document.querySelectorAll("tbody").forEach((tb) => {
      const cols =
        tb.closest("table")?.querySelector("thead tr")?.children.length || 7;
      tb.innerHTML = `<tr><td colspan="${cols}" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>`;
    });
  },
  showError(msg) {
    this.showNotification(msg, "error");
  },
  showNotification(message, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },
  showModal(title, bodyHtml) {
    let modal = document.getElementById("dynamicModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "dynamicModal";
      modal.className = "modal fade";
      modal.innerHTML = `<div class="modal-dialog modal-lg"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"></div></div></div>`;
      document.body.appendChild(modal);
    }
    modal.querySelector(".modal-title").textContent = title;
    modal.querySelector(".modal-body").innerHTML = bodyHtml;
    new bootstrap.Modal(modal).show();
  },
};

document.addEventListener("DOMContentLoaded", () =>
  AssessmentsExamsController.init(),
);
