/**
 * Student Performance Overview Controller
 * One page:
 * - overview list
 * - class/stream/school modes
 * - modal drill-down for individual student
 */

const StudentPerformanceController = {
  state: {
    overviewRows: [],
    academicYears: [],
    terms: [],
    classes: [],
    streams: [],
    selectedStudentId: null,
    profile: null,
    performance: [],
    attendanceSummary: {},
    discipline: [],
    activities: [],
    financeSummary: {},
    healthSummary: {},
    charts: {
      subject: null,
      trend: null,
    },
  },

  ui: {},

  init: async function () {
    console.log("StudentPerformanceController: Initializing...");

    // 1. Check authentication safely if AuthContext exists
    if (window.AuthContext && typeof window.AuthContext.isAuthenticated === "function") {
      if (!window.AuthContext.isAuthenticated()) {
        console.warn("StudentPerformanceController: Not authenticated, redirecting to login");
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }
    } else {
      console.warn("StudentPerformanceController: AuthContext not available");
    }

    this.cacheDom();
    this.attachEvents();

    console.log("StudentPerformanceController: Loading metadata...");
    // 2. Load meta first, then overview immediately
    await this.loadMeta();
    console.log("StudentPerformanceController: Loading overview...");
    await this.loadOverview();
    console.log("StudentPerformanceController: Initialization complete");
  },

  cacheDom: function () {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      viewMode: $("viewMode"),
      academicYearFilter: $("academicYearFilter"),
      termFilter: $("termFilter"),
      classFilter: $("classFilter"),
      streamFilter: $("streamFilter"),
      genderFilter: $("genderFilter"),
      monthFilter: $("monthFilter"),
      studentSearch: $("studentSearch"),
      applyFiltersBtn: $("applyFiltersBtn"),
      resetFiltersBtn: $("resetFiltersBtn"),
      exportOverviewBtn: $("exportOverviewBtn"),
      printOverviewBtn: $("printOverviewBtn"),

      summaryStudents: $("summaryStudents"),
      summaryAverage: $("summaryAverage"),
      summaryTopStudent: $("summaryTopStudent"),
      summaryBestGroup: $("summaryBestGroup"),
      viewModeBadge: $("viewModeBadge"),

      overviewLoading: $("overviewLoading"),
      overviewError: $("overviewError"),
      overviewEmpty: $("overviewEmpty"),
      overviewTableHead: $("overviewTableHead"),
      performanceOverviewBody: $("performanceOverviewBody"),

      modal: $("studentPerformanceModal"),
      modalStudentSubtitle: $("modalStudentSubtitle"),
      modalAcademicYear: $("modalAcademicYear"),
      modalTerm: $("modalTerm"),
      modalAssessment: $("modalAssessment"),
      reloadStudentReportBtn: $("reloadStudentReportBtn"),
      printStudentReportBtn: $("printStudentReportBtn"),
      modalLoading: $("modalLoading"),
      modalError: $("modalError"),
      modalReportContent: $("modalReportContent"),

      studentPhoto: $("studentPhoto"),
      studentName: $("studentName"),
      admNo: $("admNo"),
      studentClass: $("studentClass"),
      stream: $("stream"),
      overallAvg: $("overallAvg"),
      position: $("position"),
      overallGrade: $("overallGrade"),

      totalMarks: $("totalMarks"),
      meanScore: $("meanScore"),
      subjectsCount: $("subjectsCount"),
      attendanceRate: $("attendanceRate"),

      disciplineCases: $("disciplineCases"),
      activitiesCount: $("activitiesCount"),
      feeBalance: $("feeBalance"),
      healthAlerts: $("healthAlerts"),

      subjectChart: $("subjectPerformanceChart"),
      trendChart: $("progressTrendChart"),
      subjectsTableBody: $("subjectsTableBody"),

      teacherComments: $("teacherComments"),
      disciplineDetails: $("disciplineDetails"),
      activitiesDetails: $("activitiesDetails"),
      attendanceDetails: $("attendanceDetails"),
      financeDetails: $("financeDetails"),
      recommendations: $("recommendations"),
    };

    // Check if critical DOM elements exist
    if (!this.ui.performanceOverviewBody) {
      console.error("StudentPerformanceController: Critical DOM element 'performanceOverviewBody' not found");
    }
    if (!this.ui.overviewTableHead) {
      console.error("StudentPerformanceController: Critical DOM element 'overviewTableHead' not found");
    }
  },

  attachEvents: function () {
    this.ui.applyFiltersBtn?.addEventListener("click", () => this.loadOverview());
    this.ui.resetFiltersBtn?.addEventListener("click", () => this.resetFilters());
    this.ui.printOverviewBtn?.addEventListener("click", () => this.prepareAndPrint('overview'));
    this.ui.exportOverviewBtn?.addEventListener("click", () => this.exportOverview());

    this.ui.studentSearch?.addEventListener(
      "input",
      this.debounce(() => this.loadOverview(), 400)
    );

    this.ui.viewMode?.addEventListener("change", () => this.loadOverview());

    this.ui.classFilter?.addEventListener("change", () => {
      this.updateStreamsFilter();
      this.loadOverview();
    });

    this.ui.streamFilter?.addEventListener("change", () => this.loadOverview());
    this.ui.academicYearFilter?.addEventListener("change", () => this.loadOverview());
    this.ui.termFilter?.addEventListener("change", () => this.loadOverview());

    this.ui.reloadStudentReportBtn?.addEventListener("click", () => {
      if (this.state.selectedStudentId) {
        this.loadStudentReport(this.state.selectedStudentId);
      }
    });

    this.ui.printStudentReportBtn?.addEventListener("click", () => this.prepareAndPrint('modal'));

    // Set print date on load
    this.setPrintDate();
  },

  loadMeta: async function () {
    try {
      console.log("StudentPerformanceController: Fetching performance metadata...");
      const response = await this.api("/students/performance-meta", "GET");
      console.log("StudentPerformanceController: Metadata response:", response);
      const data = this.unwrap(response);
      console.log("StudentPerformanceController: Unwrapped metadata:", data);

      this.state.classes = data.classes || [];
      this.state.streams = data.streams || [];
      this.state.academicYears = data.academic_years || [];
      this.state.terms = data.terms || [];

      console.log("StudentPerformanceController: Loaded classes:", this.state.classes.length);
      console.log("StudentPerformanceController: Loaded streams:", this.state.streams.length);
      console.log("StudentPerformanceController: Loaded academic years:", this.state.academicYears.length);
      console.log("StudentPerformanceController: Loaded terms:", this.state.terms.length);

      // Fill overview filters
      this.fillSelect(this.ui.academicYearFilter, this.state.academicYears, "All Years");
      this.fillSelect(this.ui.termFilter, this.state.terms, "All Terms");
      this.fillSelect(this.ui.classFilter, this.state.classes, "All Classes");

      this.updateStreamsFilter();

      // Fill modal filters
      this.fillSelect(this.ui.modalAcademicYear, this.state.academicYears, "All Years");
      this.fillSelect(this.ui.modalTerm, this.state.terms, "All Terms");

      const assessments = data.assessments || [];
      this.fillSelect(this.ui.modalAssessment, assessments, "All Assessments");
    } catch (error) {
      console.error("StudentPerformanceController: Failed to load metadata:", error);
      // Don't show error for metadata failure - just log it and continue with empty data
      console.warn("StudentPerformanceController: Continuing with empty filter data");
      this.state.classes = [];
      this.state.streams = [];
      this.state.academicYears = [];
      this.state.terms = [];
    }
  },

  updateStreamsFilter: function () {
    const classId = this.ui.classFilter?.value || "";
    const filtered = classId
      ? this.state.streams.filter((s) => String(s.class_id) === String(classId))
      : this.state.streams;
    this.fillSelect(this.ui.streamFilter, filtered, "All Streams");
  },

  loadOverview: async function () {
    this.setOverviewLoading(true);

    try {
      const params = this.getOverviewParams();
      console.log("StudentPerformanceController: Loading overview with params:", params.toString());
      const response = await this.api(`/students/performance-overview?${params.toString()}`, "GET");
      console.log("StudentPerformanceController: Overview response:", response);
      const rows = this.unwrap(response) || [];
      console.log("StudentPerformanceController: Unwrapped rows:", rows.length);

      this.state.overviewRows = rows;
      this.renderOverview();
    } catch (error) {
      console.error("StudentPerformanceController: Failed to load overview:", error);
      this.showOverviewError(error.message || "Failed to load performance overview.");
    } finally {
      this.setOverviewLoading(false);
    }
  },

  getOverviewParams: function () {
    const params = new URLSearchParams();
    params.set("view_mode", this.ui.viewMode?.value || "students");

    const filters = {
      academic_year: this.ui.academicYearFilter?.value || "",
      term_id: this.ui.termFilter?.value || "",
      class_id: this.ui.classFilter?.value || "",
      stream_id: this.ui.streamFilter?.value || "",
      gender: this.ui.genderFilter?.value || "",
      month: this.ui.monthFilter?.value || "",
      search: this.ui.studentSearch?.value.trim() || "",
    };

    Object.entries(filters).forEach(([key, val]) => {
      if (val !== "") params.set(key, val);
    });

    return params;
  },

  renderOverview: function () {
    const viewMode = this.ui.viewMode?.value || "students";
    const summary = this.calculateOverviewSummary(this.state.overviewRows);

    this.renderSummary(summary);
    this.renderOverviewHeader(viewMode);
    this.renderOverviewRows(viewMode, this.state.overviewRows);

    this.ui.viewModeBadge.textContent = this.labelForViewMode(viewMode);
    this.ui.overviewEmpty.classList.toggle("d-none", this.state.overviewRows.length > 0);
  },

  renderSummary: function (summary) {
    this.ui.summaryStudents.textContent = summary.total_students ?? 0;
    this.ui.summaryAverage.textContent = `${summary.average_score ?? 0}%`;
    this.ui.summaryTopStudent.textContent = summary.top_student ?? "-";
    this.ui.summaryBestGroup.textContent = summary.best_group ?? "-";
  },

  renderOverviewHeader: function (viewMode) {
    if (viewMode === "class") {
      this.ui.overviewTableHead.innerHTML = `
        <tr>
          <th>Class</th>
          <th>Students</th>
          <th>Average Score</th>
          <th>Grade</th>
          <th>Attendance Rate</th>
          <th>Total Fee Balance</th>
          <th>Discipline Cases</th>
          <th>Activities</th>
        </tr>
      `;
      return;
    }

    if (viewMode === "stream") {
      this.ui.overviewTableHead.innerHTML = `
        <tr>
          <th>Class</th>
          <th>Stream</th>
          <th>Students</th>
          <th>Average Score</th>
          <th>Grade</th>
          <th>Attendance Rate</th>
          <th>Total Fee Balance</th>
          <th>Discipline Cases</th>
          <th>Activities</th>
        </tr>
      `;
      return;
    }

    if (viewMode === "school") {
      this.ui.overviewTableHead.innerHTML = `
        <tr>
          <th>Scope</th>
          <th>Students</th>
          <th>Average Score</th>
          <th>Grade</th>
          <th>Attendance Rate</th>
          <th>Total Fee Balance</th>
          <th>Discipline Cases</th>
          <th>Activities</th>
        </tr>
      `;
      return;
    }

    // Default: students view
    this.ui.overviewTableHead.innerHTML = `
      <tr>
        <th>Student ID</th>
        <th>Adm No</th>
        <th>Name</th>
        <th>Class</th>
        <th>Stream</th>
        <th>Gender</th>
        <th>Average Score</th>
        <th>Grade</th>
        <th>Position</th>
        <th>Attendance %</th>
        <th>Fee Balance</th>
        <th>Discipline Cases</th>
        <th>Activities</th>
        <th>Action</th>
      </tr>
    `;
  },

  renderOverviewRows: function (viewMode, rows) {
    if (!rows.length) {
      this.ui.performanceOverviewBody.innerHTML = `
        <tr>
          <td colspan="14" class="text-center text-muted py-4">
            No records found.
          </td>
        </tr>
      `;
      return;
    }

    if (viewMode === "class") {
      this.ui.performanceOverviewBody.innerHTML = rows
        .map(
          (row) => `
        <tr>
          <td>${this.escape(row.class_name || "-")}</td>
          <td>${row.total_students ?? 0}</td>
          <td>${row.average_score ?? 0}%</td>
          <td><span class="badge bg-primary">${this.escape(row.grade || "-")}</span></td>
          <td>${row.attendance_rate ?? 0}%</td>
          <td>${this.formatMoney(row.fee_balance)}</td>
          <td>${row.discipline_cases ?? 0}</td>
          <td>${row.activities_count ?? 0}</td>
        </tr>
      `
        )
        .join("");
      return;
    }

    if (viewMode === "stream") {
      this.ui.performanceOverviewBody.innerHTML = rows
        .map(
          (row) => `
        <tr>
          <td>${this.escape(row.class_name || "-")}</td>
          <td>${this.escape(row.stream_name || "-")}</td>
          <td>${row.total_students ?? 0}</td>
          <td>${row.average_score ?? 0}%</td>
          <td><span class="badge bg-primary">${this.escape(row.grade || "-")}</span></td>
          <td>${row.attendance_rate ?? 0}%</td>
          <td>${this.formatMoney(row.fee_balance)}</td>
          <td>${row.discipline_cases ?? 0}</td>
          <td>${row.activities_count ?? 0}</td>
        </tr>
      `
        )
        .join("");
      return;
    }

    if (viewMode === "school") {
      this.ui.performanceOverviewBody.innerHTML = rows
        .map(
          (row) => `
        <tr>
          <td>${this.escape(row.scope || "Whole School")}</td>
          <td>${row.total_students ?? 0}</td>
          <td>${row.average_score ?? 0}%</td>
          <td><span class="badge bg-primary">${this.escape(row.grade || "-")}</span></td>
          <td>${row.attendance_rate ?? 0}%</td>
          <td>${this.formatMoney(row.fee_balance)}</td>
          <td>${row.discipline_cases ?? 0}</td>
          <td>${row.activities_count ?? 0}</td>
        </tr>
      `
        )
        .join("");
      return;
    }

    // Default: students view
    this.ui.performanceOverviewBody.innerHTML = rows
      .map((row) => {
        const id = row.student_id;
        return `
        <tr>
          <td>${this.escape(id || "-")}</td>
          <td>${this.escape(row.admission_no || "-")}</td>
          <td><strong>${this.escape(row.full_name || "-")}</strong></td>
          <td>${this.escape(row.class_name || "-")}</td>
          <td>${this.escape(row.stream_name || "-")}</td>
          <td>${this.escape(row.gender || "-")}</td>
          <td>${row.average_score ?? 0}%</td>
          <td><span class="badge bg-primary">${this.escape(row.grade || "-")}</span></td>
          <td>${this.escape(row.position || "-")}</td>
          <td>${row.attendance_rate ?? 0}%</td>
          <td class="${row.fee_balance > 0 ? "text-danger fw-semibold" : ""}">${this.formatMoney(row.fee_balance)}</td>
          <td><span class="badge bg-${row.discipline_cases > 0 ? "danger" : "secondary"}">${row.discipline_cases ?? 0}</span></td>
          <td>${row.activities_count ?? 0}</td>
          <td>
            <button class="btn btn-sm btn-success"
                    onclick="StudentPerformanceController.openStudentModal(${Number(id)})">
              <i class="bi bi-eye me-1"></i> View
            </button>
          </td>
        </tr>
      `;
      })
      .join("");
  },

  openStudentModal: async function (studentId) {
    if (!studentId) return;

    this.state.selectedStudentId = studentId;

    if (typeof bootstrap !== "undefined" && this.ui.modal) {
      const modalInstance = new bootstrap.Modal(this.ui.modal);
      modalInstance.show();
    }

    await this.loadStudentReport(studentId);
  },

  loadStudentReport: async function (studentId) {
    this.setModalLoading(true);

    try {
      const params = new URLSearchParams();
      const academicYear = this.ui.modalAcademicYear?.value || this.ui.academicYearFilter?.value || "";
      const termId = this.ui.modalTerm?.value || this.ui.termFilter?.value || "";
      const assessmentId = this.ui.modalAssessment?.value || "";

      if (academicYear) params.set("academic_year", academicYear);
      if (termId) params.set("term_id", termId);
      if (assessmentId) params.set("assessment_id", assessmentId);

      const query = params.toString();
      const response = await this.api(
        `/students/performance-full/${studentId}${query ? `?${query}` : ""}`,
        "GET"
      );

      const data = this.unwrap(response);

      this.state.profile = data.student || {};
      this.state.performance = data.performance || [];
      this.state.attendanceSummary = data.attendance_summary || {};
      this.state.discipline = data.discipline_summary?.records || data.discipline || [];
      this.state.activities = data.activities || [];
      this.state.financeSummary = data.finance_summary || {};
      this.state.healthSummary = data.health_summary || {};

      this.renderStudentReport(data);
    } catch (error) {
      console.error(error);
      this.showModalError(error.message || "Failed to load student report.");
    } finally {
      this.setModalLoading(false);
    }
  },

  renderStudentReport: function (data) {
    this.renderProfile();
    this.renderStudentSummary();
    this.renderSubjectsTable();
    this.renderCharts();
    this.renderTabs(data);
  },

  renderProfile: function () {
    const profile = this.state.profile || {};
    const fullName = profile.full_name || `${profile.first_name || ""} ${profile.last_name || ""}`.trim();

    this.ui.studentPhoto.src =
      profile.photo_url ||
      profile.photo ||
      `${window.APP_BASE || ""}/images/default-avatar.png`;
    this.ui.studentName.textContent = fullName || "-";
    this.ui.modalStudentSubtitle.textContent = fullName || "Student full school profile";
    this.ui.admNo.textContent = profile.admission_no || "-";
    this.ui.studentClass.textContent = profile.class_name || "-";
    this.ui.stream.textContent = profile.stream_name || "-";
  },

  renderStudentSummary: function () {
    const totals = this.calculateTotals(this.state.performance);
    const attendanceRate = this.calculateAttendanceRate(this.state.attendanceSummary);
    const finance = this.state.financeSummary || {};
    const health = this.state.healthSummary || {};

    this.ui.totalMarks.textContent = totals.totalMarks;
    this.ui.meanScore.textContent = `${totals.meanScore}%`;
    this.ui.subjectsCount.textContent = totals.subjects;
    this.ui.attendanceRate.textContent = `${attendanceRate}%`;

    this.ui.overallAvg.textContent = `${totals.meanScore}%`;
    this.ui.overallGrade.textContent = this.gradeFromScore(totals.meanScore);
    this.ui.position.textContent = this.state.profile?.position || "-";

    this.ui.disciplineCases.textContent = this.state.discipline.length;
    this.ui.activitiesCount.textContent = this.state.activities.length;
    this.ui.feeBalance.textContent = this.formatMoney(finance.balance);
    this.ui.healthAlerts.textContent = health.alerts_count ?? 0;
  },

  renderSubjectsTable: function () {
    const rows = this.groupBySubject(this.state.performance);

    if (!rows.length) {
      this.ui.subjectsTableBody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center text-muted">No performance data available.</td>
        </tr>
      `;
      return;
    }

    this.ui.subjectsTableBody.innerHTML = rows
      .map(
        (row) => `
      <tr>
        <td>${this.escape(row.subject)}</td>
        <td>${row.score}%</td>
        <td><span class="badge bg-primary">${this.gradeFromScore(row.score)}</span></td>
        <td>${row.classAverage !== null ? `${row.classAverage}%` : "-"}</td>
        <td>${this.escape(row.position || "-")}</td>
        <td>${this.escape(row.teacher || "-")}</td>
        <td>${this.escape(row.remarks || this.remarkFromScore(row.score))}</td>
      </tr>
    `
      )
      .join("");
  },

  renderCharts: function () {
    if (!window.Chart) return;

    this.destroyCharts();

    const subjects = this.groupBySubject(this.state.performance);

    if (subjects.length && this.ui.subjectChart) {
      this.state.charts.subject = new Chart(this.ui.subjectChart, {
        type: "bar",
        data: {
          labels: subjects.map((s) => s.subject),
          datasets: [
            {
              label: "Score %",
              data: subjects.map((s) => s.score),
              backgroundColor: "rgba(25, 135, 84, 0.65)",
              borderColor: "rgba(25, 135, 84, 1)",
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, max: 100 } },
        },
      });
    }

    const trend = this.buildTrendData(this.state.performance);

    if (trend.labels.length && this.ui.trendChart) {
      this.state.charts.trend = new Chart(this.ui.trendChart, {
        type: "line",
        data: {
          labels: trend.labels,
          datasets: [
            {
              label: "Average %",
              data: trend.values,
              borderColor: "#198754",
              backgroundColor: "rgba(25, 135, 84, 0.15)",
              fill: true,
              tension: 0.25,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, max: 100 } },
        },
      });
    }
  },

  renderTabs: function (data) {
    this.renderTeacherComments(data.teacher_comments || []);
    this.renderDiscipline(data.discipline_summary?.records || data.discipline || []);
    this.renderActivities(data.activities || []);
    this.renderAttendanceDetails(data.attendance_summary || {});
    this.renderFinanceDetails(data.finance_summary || {});
    this.renderRecommendations(data.recommendations || []);
  },

  renderTeacherComments: function (comments) {
    const list = comments || [];
    if (!list.length) {
      this.ui.teacherComments.innerHTML = `<div class="alert alert-info">No teacher comments available.</div>`;
      return;
    }

    this.ui.teacherComments.innerHTML = list
      .map((item) => {
        const text = typeof item === "string" ? item : item.comment || item.remarks || "";
        const teacher = typeof item === "object" ? item.teacher_name || item.teacher || "" : "";
        return `
        <div class="alert alert-light border">
          ${teacher ? `<strong>${this.escape(teacher)}:</strong> ` : ""}
          ${this.escape(text)}
        </div>
      `;
      })
      .join("");
  },

  renderDiscipline: function (records) {
    const list = records || [];
    if (!list.length) {
      this.ui.disciplineDetails.innerHTML = `<div class="alert alert-success">No discipline records found.</div>`;
      return;
    }

    this.ui.disciplineDetails.innerHTML = `
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Case</th>
              <th>Status</th>
              <th>Action Taken</th>
            </tr>
          </thead>
          <tbody>
            ${list.map(
              (row) => `
              <tr>
                <td>${this.escape(row.date || "-")}</td>
                <td>${this.escape(row.case_title || "-")}</td>
                <td><span class="badge bg-${row.status === "resolved" ? "success" : "warning"}">${this.escape(row.status || "pending")}</span></td>
                <td>${this.escape(row.action_taken || "-")}</td>
              </tr>
            `
            ).join("")}
          </tbody>
        </table>
      </div>
    `;
  },

  renderActivities: function (activities) {
    const list = activities || [];
    if (!list.length) {
      this.ui.activitiesDetails.innerHTML = `<div class="alert alert-info">No co-curricular activities found.</div>`;
      return;
    }

    this.ui.activitiesDetails.innerHTML = `
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th>Activity</th>
              <th>Joined Date</th>
            </tr>
          </thead>
          <tbody>
            ${list.map(
              (row) => `
              <tr>
                <td>${this.escape(row.title || "-")}</td>
                <td>${this.escape(row.joined_at ? new Date(row.joined_at).toLocaleDateString() : "-")}</td>
              </tr>
            `
            ).join("")}
          </tbody>
        </table>
      </div>
    `;
  },

  renderAttendanceDetails: function (summary) {
    const s = summary || {};
    this.ui.attendanceDetails.innerHTML = `
      <div class="card border-0 bg-light p-3">
        <div class="row g-3 text-center">
          <div class="col-md-4">
            <small class="text-muted">Days Present</small>
            <h4 class="mb-0 text-success">${s.days_present ?? 0}</h4>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Days Absent</small>
            <h4 class="mb-0 text-danger">${s.days_absent ?? 0}</h4>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Attendance Rate</small>
            <h4 class="mb-0 text-primary">${s.attendance_rate ?? 100}%</h4>
          </div>
        </div>
      </div>
    `;
  },

  renderFinanceDetails: function (finance) {
    const f = finance || {};
    this.ui.financeDetails.innerHTML = `
      <div class="card border-0 bg-light p-3">
        <div class="row g-3 text-center">
          <div class="col-md-4">
            <small class="text-muted">Total Due</small>
            <h4 class="mb-0 text-dark">${this.formatMoney(f.total_due ?? 0)}</h4>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Total Paid</small>
            <h4 class="mb-0 text-success">${this.formatMoney(f.total_paid ?? 0)}</h4>
          </div>
          <div class="col-md-4">
            <small class="text-muted">Outstanding Balance</small>
            <h4 class="mb-0 text-danger fw-bold">${this.formatMoney(f.balance ?? 0)}</h4>
          </div>
        </div>
      </div>
    `;
  },

  renderRecommendations: function (recommendations) {
    const list = recommendations || [];
    if (!list.length) {
      this.ui.recommendations.innerHTML = `<div class="alert alert-info">No academic recommendations available.</div>`;
      return;
    }

    this.ui.recommendations.innerHTML = list
      .map(
        (rec) => `
        <div class="alert alert-warning border border-warning-subtle">
          <i class="bi bi-lightbulb text-warning me-2"></i>
          ${this.escape(typeof rec === "string" ? rec : rec.recommendation || "")}
        </div>
      `
      )
      .join("");
  },

  calculateOverviewSummary: function (rows) {
    if (!rows.length) {
      return {
        total_students: 0,
        average_score: 0,
        top_student: "-",
        best_group: "-",
      };
    }

    let total = 0;
    let count = 0;
    let top = null;

    rows.forEach((row) => {
      const avg = Number(row.average_score);
      if (Number.isFinite(avg)) {
        total += avg;
        count += 1;
      }
      if (Number.isFinite(avg) && (!top || avg > Number(top.average_score))) {
        top = row;
      }
    });

    return {
      total_students: rows.length,
      average_score: count ? Math.round(total / count) : 0,
      top_student: top ? top.full_name || "-" : "-",
      best_group: top ? top.class_name || "-" : "-",
    };
  },

  calculateTotals: function (records) {
    if (!Array.isArray(records) || !records.length) {
      return { totalMarks: 0, meanScore: 0, subjects: 0 };
    }

    let obtained = 0;
    let percentageSum = 0;
    let percentageCount = 0;
    const subjects = new Set();

    records.forEach((row) => {
      const subject = row.subject;
      if (subject) subjects.add(subject);

      const score = Number(row.score ?? 0);
      if (Number.isFinite(score)) {
        obtained += score;
        percentageSum += score;
        percentageCount += 1;
      }
    });

    return {
      totalMarks: Math.round(obtained),
      meanScore: percentageCount ? Math.round(percentageSum / percentageCount) : 0,
      subjects: subjects.size,
    };
  },

  groupBySubject: function (records) {
    return records || [];
  },

  buildTrendData: function (records) {
    if (!Array.isArray(records) || !records.length) {
      return { labels: [], values: [] };
    }
    const grouped = new Map();
    records.forEach((row) => {
      const subject = row.subject || "Unknown";
      const score = Number(row.score ?? 0);
      if (!grouped.has(subject)) {
        grouped.set(subject, { total: 0, count: 0 });
      }
      const item = grouped.get(subject);
      item.total += score;
      item.count += 1;
    });

    const labels = Array.from(grouped.keys());
    const values = labels.map((label) => {
      const item = grouped.get(label);
      return item.count ? Math.round(item.total / item.count) : 0;
    });

    return { labels, values };
  },

  calculateAttendanceRate: function (summary) {
    if (summary && summary.attendance_rate !== undefined) {
      return Math.round(Number(summary.attendance_rate || 0));
    }
    const total = Number(summary.days_present ?? 0) + Number(summary.days_absent ?? 0);
    const present = Number(summary.days_present ?? 0);
    return total > 0 ? Math.round((present / total) * 100) : 100;
  },

  resetFilters: function () {
    [
      this.ui.viewMode,
      this.ui.academicYearFilter,
      this.ui.termFilter,
      this.ui.classFilter,
      this.ui.streamFilter,
      this.ui.genderFilter,
      this.ui.monthFilter,
      this.ui.studentSearch,
    ].forEach((el) => {
      if (el) el.value = "";
    });

    if (this.ui.viewMode) this.ui.viewMode.value = "students";
    this.updateStreamsFilter();
    this.loadOverview();
  },

  exportOverview: function () {
    if (!this.state.overviewRows.length) {
      this.notify("No data available to export.", "warning");
      return;
    }

    const rows = this.state.overviewRows;
    const headers = Object.keys(rows[0]);

    const csv = [
      headers,
      ...rows.map((row) => headers.map((key) => row[key] ?? "")),
    ]
      .map((line) =>
        line.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(",")
      )
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `student_performance_overview_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
  },

  fillSelect: function (select, items, placeholder) {
    if (!select) return;

    select.innerHTML = `<option value="">${placeholder}</option>`;

    (items || []).forEach((item) => {
      const option = document.createElement("option");
      option.value = item.id ?? item.year ?? item.academic_year ?? item.value ?? "";
      option.textContent =
        item.name ||
        item.class_name ||
        item.stream_name ||
        item.year_name ||
        item.year_code ||
        item.label ||
        option.value;
      select.appendChild(option);
    });
  },

  api: async function (endpoint, method = "GET", data = null) {
    if (window.API && typeof window.API.apiCall === "function") {
      return window.API.apiCall(endpoint, method, data);
    }

    const base = window.APP_BASE || "";
    const url = `${base}/api${endpoint.startsWith("/") ? endpoint : `/${endpoint}`}`;

    const options = { method, headers: {} };

    if (data) {
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);
    const json = await response.json().catch(() => ({}));

    if (!response.ok || json.success === false) {
      throw new Error(json.message || json.error || "Request failed.");
    }

    return json;
  },

  unwrap: function (response) {
    if (!response) return {};
    if (response.data && response.data.data !== undefined)
      return response.data.data;
    if (response.data !== undefined) return response.data;
    return response;
  },

  setOverviewLoading: function (loading) {
    this.ui.overviewLoading?.classList.toggle("d-none", !loading);
    this.ui.overviewError?.classList.add("d-none");
  },

  setModalLoading: function (loading) {
    this.ui.modalLoading?.classList.toggle("d-none", !loading);
    this.ui.modalError?.classList.add("d-none");
    this.ui.modalReportContent?.classList.toggle("opacity-50", loading);
  },

  showOverviewError: function (message) {
    if (!this.ui.overviewError) return;
    this.ui.overviewError.textContent = message;
    this.ui.overviewError.classList.remove("d-none");
  },

  showModalError: function (message) {
    if (!this.ui.modalError) return;
    this.ui.modalError.textContent = message;
    this.ui.modalError.classList.remove("d-none");
  },

  destroyCharts: function () {
    if (this.state.charts.subject) {
      this.state.charts.subject.destroy();
      this.state.charts.subject = null;
    }

    if (this.state.charts.trend) {
      this.state.charts.trend.destroy();
      this.state.charts.trend = null;
    }
  },

  labelForViewMode: function (mode) {
    return (
      {
        students: "Students View",
        class: "Class View",
        stream: "Stream View",
        school: "Whole School View",
      }[mode] || "Students View"
    );
  },

  gradeFromScore: function (score) {
    const value = Number(score);
    if (value >= 80) return "A";
    if (value >= 70) return "B";
    if (value >= 60) return "C";
    if (value >= 50) return "D";
    return "E";
  },

  remarkFromScore: function (score) {
    if (score >= 75) return "Excellent";
    if (score >= 60) return "Good";
    if (score >= 50) return "Fair";
    return "Needs Support";
  },

  formatMoney: function (value) {
    const amount = Number(value);
    if (!Number.isFinite(amount)) return "-";
    return `KES ${amount.toLocaleString()}`;
  },

  debounce: function (fn, delay) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  },

  escape: function (value) {
    return String(value ?? "").replace(
      /[&<>"']/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#039;",
        })[char]
    );
  },

  setPrintDate: function () {
    const printDateEl = document.getElementById("printDate");
    if (printDateEl) {
      const now = new Date();
      printDateEl.textContent = now.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
      });
    }
  },

  prepareAndPrint: function (printType) {
    // Set print date
    this.setPrintDate();

    // Remove any existing print classes
    document.body.classList.remove('printing', 'printing-modal', 'printing-overview');

    // Add appropriate print class to body for CSS targeting
    if (printType === 'modal') {
      document.body.classList.add('printing-modal');
      if (this.ui.modal) {
        this.ui.modal.classList.add('print-mode');
      }
    } else {
      document.body.classList.add('printing-overview');
    }

    // Print
    window.print();

    // Clean up after print dialog closes
    setTimeout(() => {
      document.body.classList.remove('printing', 'printing-modal', 'printing-overview');
      if (printType === 'modal' && this.ui.modal) {
        this.ui.modal.classList.remove('print-mode');
      }
    }, 1000);
  },

  notify: function (message, type = "info") {
    if (typeof showNotification === "function") {
      showNotification(message, type);
      return;
    }

    if (window.API && typeof window.API.showNotification === "function") {
      window.API.showNotification(message, type);
      return;
    }

    alert(message);
  },
};

document.addEventListener("DOMContentLoaded", () => {
  StudentPerformanceController.init();
});

window.StudentPerformanceController = StudentPerformanceController;
