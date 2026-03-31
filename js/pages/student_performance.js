/**
 * Student Performance Page Controller
 * Real-data implementation with schema-aware normalization.
 */

const StudentPerformanceController = {
  state: {
    students: [],
    academicYears: [],
    terms: [],
    profile: null,
    performance: [],
    attendanceRecords: [],
    attendanceSummary: {},
    charts: {
      subject: null,
      trend: null,
    },
  },

  ui: {},

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.attachEventListeners();
    await this.loadReferenceData();
  },

  cacheDom: function () {
    this.ui = {
      studentSelect: document.getElementById("studentSelect"),
      academicYear: document.getElementById("academicYear"),
      term: document.getElementById("term"),
      loadBtn: document.getElementById("loadBtn"),
      exportBtn: document.getElementById("exportBtn"),
      printBtn: document.getElementById("printBtn"),
      reportContent: document.getElementById("reportContent"),
      emptyState: document.getElementById("emptyState"),
      subjectsTableBody: document.getElementById("subjectsTableBody"),
      teacherComments: document.getElementById("teacherComments"),
      recommendations: document.getElementById("recommendations"),
      studentPhoto: document.getElementById("studentPhoto"),
      studentName: document.getElementById("studentName"),
      admNo: document.getElementById("admNo"),
      studentClass: document.getElementById("studentClass"),
      stream: document.getElementById("stream"),
      overallAvg: document.getElementById("overallAvg"),
      position: document.getElementById("position"),
      overallGrade: document.getElementById("overallGrade"),
      totalMarks: document.getElementById("totalMarks"),
      meanScore: document.getElementById("meanScore"),
      subjectsCount: document.getElementById("subjectsCount"),
      attendanceRate: document.getElementById("attendanceRate"),
      subjectChart: document.getElementById("subjectPerformanceChart"),
      trendChart: document.getElementById("progressTrendChart"),
    };
  },

  attachEventListeners: function () {
    this.ui.loadBtn?.addEventListener("click", () => this.loadReport());
    this.ui.exportBtn?.addEventListener("click", () => this.exportReport());
    this.ui.printBtn?.addEventListener("click", () => window.print());

    this.ui.academicYear?.addEventListener("change", async () => {
      await this.loadTerms(this.ui.academicYear.value || "");
    });
  },

  loadReferenceData: async function () {
    await Promise.all([this.loadStudents(), this.loadAcademicYears()]);
    await this.loadTerms(this.ui.academicYear?.value || "");
  },

  loadStudents: async function () {
    try {
      const response = await window.API.students.getAll({
        page: 1,
        limit: 1000,
        status: "active",
      });
      this.state.students = Array.isArray(response?.data) ? response.data : [];
      this.populateStudents();
    } catch (error) {
      console.warn("Failed to load students", error);
      this.state.students = [];
      this.populateStudents();
    }
  },

  loadAcademicYears: async function () {
    try {
      const response = await window.API.academic.getAllAcademicYears();
      const years = this.extractList(response);
      this.state.academicYears = years;
      this.populateAcademicYears();
    } catch (error) {
      console.warn("Failed to load academic years", error);
      this.state.academicYears = [];
      this.populateAcademicYears();
    }
  },

  loadTerms: async function (academicYear = "") {
    try {
      const params = {};
      if (academicYear) {
        params.academic_year = academicYear;
        params.year = academicYear;
      }

      const response = await window.API.academic.listTerms(params);
      const terms = this.extractList(response);
      this.state.terms = terms;
      this.populateTerms(terms);
    } catch (error) {
      console.warn("Failed to load terms", error);
      this.state.terms = [];
      this.populateTerms([]);
    }
  },

  populateStudents: function () {
    if (!this.ui.studentSelect) {
      return;
    }

    this.ui.studentSelect.innerHTML =
      '<option value="">Choose a student...</option>';

    this.state.students.forEach((student) => {
      const firstName = student.first_name || "";
      const lastName = student.last_name || "";
      const admissionNo = student.admission_no || "";

      const option = document.createElement("option");
      option.value = student.id;
      option.textContent = `${admissionNo} - ${firstName} ${lastName}`.trim();
      this.ui.studentSelect.appendChild(option);
    });
  },

  populateAcademicYears: function () {
    if (!this.ui.academicYear) {
      return;
    }

    this.ui.academicYear.innerHTML = '<option value="">All Years</option>';

    let selectedValue = "";
    this.state.academicYears.forEach((year) => {
      const yearValue = this.resolveAcademicYearValue(year);
      if (!yearValue) {
        return;
      }

      const option = document.createElement("option");
      option.value = String(yearValue);
      option.textContent = year.year_code || year.year_name || String(yearValue);
      this.ui.academicYear.appendChild(option);

      const isCurrent = year.is_current === 1 || year.is_current === "1" ||
        year.is_current === true;
      if (isCurrent) {
        selectedValue = String(yearValue);
      }
    });

    if (selectedValue) {
      this.ui.academicYear.value = selectedValue;
    } else if (this.ui.academicYear.options.length > 1) {
      this.ui.academicYear.selectedIndex = 1;
    }
  },

  populateTerms: function (terms) {
    if (!this.ui.term) {
      return;
    }

    this.ui.term.innerHTML = '<option value="">All Terms</option>';

    const filtered = (terms || [])
      .filter((term) => term && term.id !== undefined && term.id !== null)
      .sort((a, b) => {
        const ayA = parseInt(a.year || 0, 10);
        const ayB = parseInt(b.year || 0, 10);
        if (ayA !== ayB) {
          return ayB - ayA;
        }
        return (parseInt(a.term_number || 0, 10) - parseInt(b.term_number || 0, 10));
      });

    filtered.forEach((term) => {
      const option = document.createElement("option");
      option.value = term.id;
      const termLabel = term.term_number ? `Term ${term.term_number}` : (term.name || "Term");
      const yearLabel = term.year ? ` (${term.year})` : "";
      option.textContent = `${termLabel}${yearLabel}`;
      this.ui.term.appendChild(option);
    });
  },

  loadReport: async function () {
    const studentId = this.ui.studentSelect?.value || "";
    const year = this.ui.academicYear?.value || "";
    const termId = this.ui.term?.value || "";

    if (!studentId) {
      this.showError("Please select a student.");
      return;
    }

    this.setLoading(true);

    try {
      const profileResp = await window.API.students.getProfile(studentId);
      this.state.profile = this.unwrapPayload(profileResp) || null;

      const params = new URLSearchParams();
      if (year) {
        params.append("academic_year", year);
        params.append("year", year);
      }
      if (termId) {
        params.append("term_id", termId);
        params.append("term", termId);
      }

      const query = params.toString();
      const performanceResp = await window.API.apiCall(
        `/students/performance-get/${studentId}${query ? `?${query}` : ""}`,
        "GET",
      );
      const performancePayload = this.unwrapPayload(performanceResp);

      const attendanceResp = await window.API.apiCall(
        `/students/attendance-get/${studentId}${query ? `?${query}` : ""}`,
        "GET",
      );
      const attendancePayload = this.unwrapPayload(attendanceResp);

      this.state.performance = this.normalizePerformanceRecords(performancePayload);
      this.state.attendanceRecords = this.extractAttendanceRecords(attendancePayload);
      this.state.attendanceSummary = this.extractAttendanceSummary(
        attendancePayload,
        this.state.attendanceRecords,
      );

      this.renderReport();
    } catch (error) {
      console.error("Failed to load report", error);
      this.showError(error.message || "Failed to load student performance report.");
      this.hideReport();
    } finally {
      this.setLoading(false);
    }
  },

  renderReport: function () {
    this.ui.emptyState.style.display = "none";
    this.ui.reportContent.style.display = "block";

    this.renderProfile();
    this.renderSummary();
    this.renderSubjectsTable();
    this.renderCharts();
    this.renderComments();
  },

  hideReport: function () {
    this.ui.reportContent.style.display = "none";
    this.ui.emptyState.style.display = "block";
  },

  renderProfile: function () {
    const profile = this.state.profile || {};
    const photo = profile.photo_url || (window.APP_BASE || "") + "/images/default-avatar.png";

    this.ui.studentPhoto.src = photo;
    this.ui.studentName.textContent = `${profile.first_name || ""} ${profile.last_name || ""}`.trim();
    this.ui.admNo.textContent = profile.admission_no || "-";
    this.ui.studentClass.textContent = profile.class_name || "-";
    this.ui.stream.textContent = profile.stream_name || "-";
  },

  renderSummary: function () {
    const totals = this.calculateTotals(this.state.performance);
    const attendanceRate = this.calculateAttendanceRate(this.state.attendanceSummary);
    const classPosition = this.state.profile?.class_position || this.state.profile?.position || "-";

    this.ui.totalMarks.textContent = totals.totalMarks;
    this.ui.meanScore.textContent = totals.meanScore;
    this.ui.subjectsCount.textContent = totals.subjects;
    this.ui.attendanceRate.textContent = `${attendanceRate}%`;

    this.ui.overallAvg.textContent = `${totals.meanScore}%`;
    this.ui.overallGrade.textContent = this.gradeFromScore(totals.meanScore);
    this.ui.position.textContent = classPosition;
  },

  renderSubjectsTable: function () {
    if (!this.ui.subjectsTableBody) {
      return;
    }

    const subjectStats = this.groupBySubject(this.state.performance);
    if (!subjectStats.length) {
      this.ui.subjectsTableBody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center text-muted">No performance data available for this selection.</td>
        </tr>
      `;
      return;
    }

    this.ui.subjectsTableBody.innerHTML = subjectStats
      .map((item) => `
        <tr>
          <td>${item.subject}</td>
          <td>${item.score}%</td>
          <td>${this.gradeFromScore(item.score)}</td>
          <td>${item.classAverage !== null ? `${item.classAverage}%` : "-"}</td>
          <td>${item.position || "-"}</td>
          <td>${item.teacher || "-"}</td>
          <td>${item.remarks || this.remarkFromScore(item.score)}</td>
        </tr>
      `)
      .join("");
  },

  renderCharts: function () {
    if (!window.Chart) {
      return;
    }

    this.destroyCharts();

    const subjectStats = this.groupBySubject(this.state.performance);
    const subjectLabels = subjectStats.map((item) => item.subject);
    const subjectScores = subjectStats.map((item) => item.score);

    if (this.ui.subjectChart && subjectLabels.length) {
      this.state.charts.subject = new Chart(this.ui.subjectChart, {
        type: "bar",
        data: {
          labels: subjectLabels,
          datasets: [{
            label: "Score %",
            data: subjectScores,
            backgroundColor: "rgba(25, 135, 84, 0.65)",
            borderColor: "rgba(25, 135, 84, 1)",
            borderWidth: 1,
          }],
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
          },
          scales: {
            y: {
              beginAtZero: true,
              max: 100,
            },
          },
        },
      });
    }

    const trend = this.buildTrendData(this.state.performance);
    if (this.ui.trendChart && trend.labels.length) {
      this.state.charts.trend = new Chart(this.ui.trendChart, {
        type: "line",
        data: {
          labels: trend.labels,
          datasets: [{
            label: "Average %",
            data: trend.values,
            borderColor: "#198754",
            backgroundColor: "rgba(25, 135, 84, 0.15)",
            fill: true,
            tension: 0.2,
            pointRadius: 4,
          }],
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
          },
          scales: {
            y: {
              beginAtZero: true,
              max: 100,
            },
          },
        },
      });
    }
  },

  renderComments: function () {
    const records = this.state.performance || [];
    const totals = this.calculateTotals(records);
    const weakSubjects = this.groupBySubject(records).filter((row) => row.score < 50);

    const comments = records
      .map((row) => row.remarks || "")
      .filter((text) => text && text.trim().length > 0);

    if (this.ui.teacherComments) {
      if (comments.length > 0) {
        this.ui.teacherComments.innerHTML = comments
          .slice(0, 6)
          .map((comment) => `<div class="alert alert-light border mb-2">${comment}</div>`)
          .join("");
      } else {
        this.ui.teacherComments.innerHTML = `
          <div class="alert alert-info mb-0">
            No teacher comments were submitted for the selected period.
          </div>
        `;
      }
    }

    const recommendationItems = [];
    if (totals.meanScore >= 75) {
      recommendationItems.push("Maintain current performance and introduce enrichment tasks.");
    } else if (totals.meanScore >= 50) {
      recommendationItems.push("Schedule weekly revision for core learning areas to improve consistency.");
    } else {
      recommendationItems.push("Set up targeted academic intervention with subject teachers and guardians.");
    }

    if (weakSubjects.length > 0) {
      recommendationItems.push(
        `Focus remedial support on: ${weakSubjects.map((row) => row.subject).join(", ")}.`,
      );
    }

    const attendanceRate = this.calculateAttendanceRate(this.state.attendanceSummary);
    if (attendanceRate < 85) {
      recommendationItems.push("Attendance is below target; follow up with class teacher/guardian for regular attendance.");
    }

    if (this.ui.recommendations) {
      this.ui.recommendations.innerHTML = `
        <ul class="mb-0">
          ${recommendationItems.map((item) => `<li>${item}</li>`).join("")}
        </ul>
      `;
    }
  },

  calculateTotals: function (records) {
    if (!Array.isArray(records) || records.length === 0) {
      return { totalMarks: 0, meanScore: 0, subjects: 0 };
    }

    let obtained = 0;
    let max = 0;
    let percentageSum = 0;
    let percentageCount = 0;
    const subjects = new Set();

    records.forEach((row) => {
      if (row.subject) {
        subjects.add(row.subject);
      }

      if (row.scoreMax > 0) {
        obtained += row.scoreObtained;
        max += row.scoreMax;
      }

      if (this.isFiniteNumber(row.percentage)) {
        percentageSum += row.percentage;
        percentageCount += 1;
      }
    });

    const meanScore = max > 0
      ? Math.round((obtained / max) * 100)
      : (percentageCount > 0 ? Math.round(percentageSum / percentageCount) : 0);

    return {
      totalMarks: Math.round(obtained),
      meanScore,
      subjects: subjects.size,
    };
  },

  calculateAttendanceRate: function (summary) {
    const present = parseInt(summary.present || 0, 10);
    const total = parseInt(summary.total || summary.total_days || 0, 10);
    if (total <= 0) {
      return 0;
    }
    return Math.round((present / total) * 100);
  },

  groupBySubject: function (records) {
    const grouped = new Map();

    (records || []).forEach((row) => {
      const subject = row.subject || "Unknown";
      if (!grouped.has(subject)) {
        grouped.set(subject, {
          subject,
          percentageTotal: 0,
          percentageCount: 0,
          classAverageTotal: 0,
          classAverageCount: 0,
          position: row.position || null,
          teacher: row.teacher || null,
          remarks: row.remarks || null,
        });
      }

      const entry = grouped.get(subject);

      if (this.isFiniteNumber(row.percentage)) {
        entry.percentageTotal += row.percentage;
        entry.percentageCount += 1;
      }

      if (this.isFiniteNumber(row.classAverage)) {
        entry.classAverageTotal += row.classAverage;
        entry.classAverageCount += 1;
      }

      if (!entry.position && row.position) {
        entry.position = row.position;
      }
      if (!entry.teacher && row.teacher) {
        entry.teacher = row.teacher;
      }
      if (!entry.remarks && row.remarks) {
        entry.remarks = row.remarks;
      }
    });

    return Array.from(grouped.values()).map((entry) => ({
      subject: entry.subject,
      score: entry.percentageCount > 0
        ? Math.round(entry.percentageTotal / entry.percentageCount)
        : 0,
      classAverage: entry.classAverageCount > 0
        ? Math.round(entry.classAverageTotal / entry.classAverageCount)
        : null,
      position: entry.position,
      teacher: entry.teacher,
      remarks: entry.remarks,
    }));
  },

  buildTrendData: function (records) {
    const grouped = new Map();

    (records || []).forEach((row) => {
      const year = row.academicYear || "";
      const termNumber = row.termNumber || "";
      const label = `${year ? `${year} - ` : ""}${termNumber ? `Term ${termNumber}` : (row.termName || "Term")}`;

      if (!grouped.has(label)) {
        grouped.set(label, { total: 0, count: 0 });
      }

      if (this.isFiniteNumber(row.percentage)) {
        const item = grouped.get(label);
        item.total += row.percentage;
        item.count += 1;
      }
    });

    const labels = Array.from(grouped.keys());
    const values = labels.map((label) => {
      const item = grouped.get(label);
      if (!item || item.count === 0) {
        return 0;
      }
      return Math.round(item.total / item.count);
    });

    return { labels, values };
  },

  normalizePerformanceRecords: function (payload) {
    const rows = this.extractList(payload);
    return rows.map((row) => {
      const subject = row.subject_name || row.subject || row.learning_area || "Unknown";
      const scoreObtained = this.parseNumber(
        row.marks_obtained ?? row.overall_score ?? row.score ?? 0,
      );
      const scoreMax = this.parseNumber(
        row.max_marks ?? row.overall_max ?? 100,
      );

      let percentage = null;
      if (this.isFiniteNumber(row.overall_percentage)) {
        percentage = this.parseNumber(row.overall_percentage);
      } else if (scoreMax > 0) {
        percentage = (scoreObtained / scoreMax) * 100;
      } else if (this.isFiniteNumber(row.score)) {
        percentage = this.parseNumber(row.score);
      }

      return {
        subject,
        percentage: percentage === null ? 0 : Math.round(percentage * 100) / 100,
        scoreObtained,
        scoreMax,
        grade: row.overall_grade || row.grade || null,
        classAverage: this.parseNullableNumber(row.class_average || row.class_avg),
        position: row.subject_position || row.position || null,
        teacher: row.teacher_name || row.teacher || null,
        remarks: row.remarks || row.teacher_comment || null,
        termName: row.term_name || null,
        termNumber: row.term_number || null,
        academicYear: row.academic_year || row.year || null,
      };
    });
  },

  extractAttendanceRecords: function (payload) {
    if (Array.isArray(payload)) {
      return payload;
    }
    if (Array.isArray(payload?.records)) {
      return payload.records;
    }
    if (Array.isArray(payload?.data)) {
      return payload.data;
    }
    return [];
  },

  extractAttendanceSummary: function (payload, records = []) {
    if (payload && typeof payload === "object" && payload.summary) {
      return payload.summary;
    }

    const total = records.length;
    const present = records.filter((row) => String(row.status).toLowerCase() === "present").length;
    const absent = records.filter((row) => String(row.status).toLowerCase() === "absent").length;
    const late = records.filter((row) => String(row.status).toLowerCase() === "late").length;

    return { total, total_days: total, present, absent, late };
  },

  remarkFromScore: function (score) {
    if (score >= 75) return "Excellent";
    if (score >= 60) return "Good";
    if (score >= 50) return "Fair";
    return "Needs Support";
  },

  gradeFromScore: function (score) {
    const value = this.parseNumber(score);
    if (value >= 80) return "A";
    if (value >= 70) return "B";
    if (value >= 60) return "C";
    if (value >= 50) return "D";
    return "E";
  },

  exportReport: function () {
    const rows = this.groupBySubject(this.state.performance);
    if (!rows.length) {
      this.showError("No data available to export.");
      return;
    }

    const csvRows = [
      ["Subject", "Score (%)", "Grade", "Class Average (%)", "Position", "Teacher", "Remarks"],
      ...rows.map((row) => ([
        row.subject,
        row.score,
        this.gradeFromScore(row.score),
        row.classAverage === null ? "" : row.classAverage,
        row.position || "",
        row.teacher || "",
        row.remarks || "",
      ])),
    ];

    const csv = csvRows.map((line) => line
      .map((value) => `"${String(value ?? "").replace(/"/g, '""')}"`)
      .join(",")).join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `student_performance_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
  },

  setLoading: function (loading) {
    if (!this.ui.loadBtn) {
      return;
    }
    this.ui.loadBtn.disabled = loading;
    this.ui.loadBtn.textContent = loading ? "Loading..." : "Load Report";
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

  unwrapPayload: function (response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined) return response.data.data;
    return response;
  },

  extractList: function (response) {
    const payload = this.unwrapPayload(response);
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.items)) return payload.items;
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload?.students)) return payload.students;
    return [];
  },

  resolveAcademicYearValue: function (year) {
    if (this.isFiniteNumber(year?.year)) return parseInt(year.year, 10);
    if (this.isFiniteNumber(year?.academic_year)) return parseInt(year.academic_year, 10);
    if (this.isFiniteNumber(year?.year_code)) return parseInt(year.year_code, 10);

    const code = String(year?.year_code || "");
    const match = code.match(/(\d{4})/);
    if (match) {
      return parseInt(match[1], 10);
    }

    return null;
  },

  parseNumber: function (value) {
    const number = Number(value);
    return Number.isFinite(number) ? number : 0;
  },

  parseNullableNumber: function (value) {
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
  },

  isFiniteNumber: function (value) {
    const number = Number(value);
    return Number.isFinite(number);
  },

  showError: function (message) {
    if (typeof showNotification === "function") {
      showNotification(message, "error");
      return;
    }
    if (window.API && typeof window.API.showNotification === "function") {
      window.API.showNotification(message, "error");
      return;
    }
    alert(`Error: ${message}`);
  },
};

document.addEventListener("DOMContentLoaded", () =>
  StudentPerformanceController.init(),
);

window.StudentPerformanceController = StudentPerformanceController;
