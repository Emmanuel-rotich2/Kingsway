/**
 * Student Performance Page Controller
 * Manages Student Performance workflow using api.js
 */

const StudentPerformanceController = {
  state: {
    students: [],
    academicYears: [],
    performance: [],
    profile: null,
    attendance: [],
  },

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    this.attachEventListeners();
    await this.loadReferenceData();
  },

  attachEventListeners: function () {
    document
      .getElementById("loadBtn")
      ?.addEventListener("click", () => this.loadReport());

    document
      .getElementById("exportBtn")
      ?.addEventListener("click", () => this.exportReport());

    document
      .getElementById("printBtn")
      ?.addEventListener("click", () => window.print());
  },

  loadReferenceData: async function () {
    await Promise.all([this.loadStudents(), this.loadAcademicYears()]);
  },

  loadStudents: async function () {
    try {
      const resp = await window.API.apiCall(
        "/students/student?limit=500",
        "GET"
      );
      const payload = this.unwrapPayload(resp) || {};
      const students = payload.students || payload || [];
      this.state.students = Array.isArray(students) ? students : [];
      this.populateStudents();
    } catch (error) {
      console.warn("Failed to load students", error);
    }
  },

  loadAcademicYears: async function () {
    try {
      const resp = await window.API.students.getAllAcademicYears();
      const payload = this.unwrapPayload(resp) || [];
      this.state.academicYears = Array.isArray(payload) ? payload : [];
      this.populateAcademicYears();
    } catch (error) {
      console.warn("Failed to load academic years", error);
    }
  },

  populateStudents: function () {
    const select = document.getElementById("studentSelect");
    if (!select) return;

    select.innerHTML = '<option value="">Choose a student...</option>';
    this.state.students.forEach((student) => {
      const opt = document.createElement("option");
      opt.value = student.id;
      opt.textContent = `${student.admission_no || ""} - ${
        student.first_name
      } ${student.last_name}`.trim();
      select.appendChild(opt);
    });
  },

  populateAcademicYears: function () {
    const select = document.getElementById("academicYear");
    if (!select) return;

    let options = '<option value="">All Years</option>';
    let selectedSet = false;

    this.state.academicYears.forEach((year) => {
      const yearCode = year.year_code || year.year_name || "";
      let yearValue = year.academic_year || year.year || null;

      if (!yearValue && yearCode) {
        const parts = yearCode.split("/");
        const candidate = parts[parts.length - 1];
        yearValue = parseInt(candidate, 10) || null;
      }

      if (!yearValue && year.start_date) {
        yearValue = new Date(year.start_date).getFullYear();
      }

      if (!yearValue) return;

      const isCurrent = year.is_current === 1 || year.is_current === true;
      if (isCurrent) selectedSet = true;
      options += `<option value="${yearValue}"${
        isCurrent ? " selected" : ""
      }>${yearCode || yearValue}</option>`;
    });

    select.innerHTML = options;

    if (!selectedSet && select.options.length > 1) {
      select.selectedIndex = 1;
    }
  },

  loadReport: async function () {
    const studentId = document.getElementById("studentSelect").value;
    const year = document.getElementById("academicYear").value;
    const term = document.getElementById("term").value;

    if (!studentId) {
      this.showError("Please select a student");
      return;
    }

    try {
      const profileResp = await window.API.students.getProfile(studentId);
      this.state.profile = this.unwrapPayload(profileResp);

      const params = new URLSearchParams();
      if (year) params.append("year", year);
      if (term) params.append("term", term);

      const performanceResp = await window.API.apiCall(
        `/students/performance-get/${studentId}?${params.toString()}`,
        "GET"
      );
      const perfPayload = this.unwrapPayload(performanceResp);
      this.state.performance = Array.isArray(perfPayload)
        ? perfPayload
        : perfPayload?.data || [];

      const attendanceResp = await window.API.apiCall(
        `/students/attendance-get/${studentId}`,
        "GET"
      );
      const attendancePayload = this.unwrapPayload(attendanceResp);
      this.state.attendance = Array.isArray(attendancePayload)
        ? attendancePayload
        : attendancePayload?.data || [];

      this.renderReport();
    } catch (error) {
      console.error("Failed to load report", error);
      this.showError(error.message || "Failed to load performance report");
    }
  },

  renderReport: function () {
    document.getElementById("emptyState").style.display = "none";
    document.getElementById("reportContent").style.display = "block";

    this.renderProfile();
    this.renderSummary();
    this.renderSubjectsTable();
    this.renderCharts();
    this.renderComments();
  },

  renderProfile: function () {
    const profile = this.state.profile || {};
    const photo = profile.photo_url || "/Kingsway/images/default-avatar.png";

    document.getElementById("studentPhoto").src = photo;
    document.getElementById("studentName").textContent = `${
      profile.first_name || ""
    } ${profile.last_name || ""}`.trim();
    document.getElementById("admNo").textContent = profile.admission_no || "-";
    document.getElementById("studentClass").textContent =
      profile.class_name || "-";
    document.getElementById("stream").textContent = profile.stream_name || "-";
  },

  renderSummary: function () {
    const performance = this.state.performance || [];
    const totals = this.calculateTotals(performance);

    document.getElementById("totalMarks").textContent = totals.totalMarks;
    document.getElementById("meanScore").textContent = totals.meanScore;
    document.getElementById("subjectsCount").textContent = totals.subjects;
    document.getElementById("attendanceRate").textContent =
      this.calculateAttendanceRate();

    document.getElementById("overallAvg").textContent = totals.meanScore + "%";
    document.getElementById("overallGrade").textContent =
      this.gradeFromScore(totals.meanScore);
    document.getElementById("position").textContent = "-";
  },

  renderSubjectsTable: function () {
    const tbody = document.getElementById("subjectsTableBody");
    if (!tbody) return;

    const subjectStats = this.groupBySubject(this.state.performance || []);

    if (!subjectStats.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center text-muted">No performance data available</td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = subjectStats
      .map((item) => {
        return `
          <tr>
            <td>${item.subject}</td>
            <td>${item.score}%</td>
            <td>${this.gradeFromScore(item.score)}</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>${item.score >= 70 ? "Excellent" : item.score >= 50 ? "Good" : "Needs Support"}</td>
          </tr>
        `;
      })
      .join("");
  },

  renderCharts: function () {
    if (!window.Chart) return;

    const subjectStats = this.groupBySubject(this.state.performance || []);
    const labels = subjectStats.map((item) => item.subject);
    const scores = subjectStats.map((item) => item.score);

    const subjectCanvas = document.getElementById("subjectPerformanceChart");
    if (subjectCanvas) {
      new Chart(subjectCanvas, {
        type: "bar",
        data: {
          labels,
          datasets: [
            {
              label: "Score %",
              data: scores,
              backgroundColor: "rgba(25, 135, 84, 0.6)",
            },
          ],
        },
        options: {
          responsive: true,
          scales: {
            y: { beginAtZero: true, max: 100 },
          },
        },
      });
    }
  },

  renderComments: function () {
    const commentsEl = document.getElementById("teacherComments");
    const recommendationsEl = document.getElementById("recommendations");

    if (commentsEl) {
      commentsEl.innerHTML = `
        <div class="alert alert-info">
          No teacher comments available for this selection.
        </div>
      `;
    }

    if (recommendationsEl) {
      recommendationsEl.innerHTML = `
        <div class="alert alert-light">
          Recommendations will appear here once term data is available.
        </div>
      `;
    }
  },

  calculateTotals: function (records) {
    if (!records.length) {
      return { totalMarks: 0, meanScore: 0, subjects: 0 };
    }

    let totalMarks = 0;
    let totalMax = 0;
    const subjects = new Set();

    records.forEach((row) => {
      const marks = parseFloat(row.marks_obtained || 0);
      const max = parseFloat(row.max_marks || 0);
      totalMarks += marks;
      totalMax += max;
      if (row.subject_name) subjects.add(row.subject_name);
    });

    const meanScore = totalMax > 0 ? Math.round((totalMarks / totalMax) * 100) : 0;

    return {
      totalMarks: Math.round(totalMarks),
      meanScore,
      subjects: subjects.size,
    };
  },

  calculateAttendanceRate: function () {
    const records = this.state.attendance || [];
    if (!records.length) return "0%";

    const total = records.length;
    const present = records.filter((r) => r.status === "present").length;
    const rate = total > 0 ? Math.round((present / total) * 100) : 0;
    return `${rate}%`;
  },

  groupBySubject: function (records) {
    const map = new Map();

    records.forEach((row) => {
      const subject = row.subject_name || "Unknown";
      const marks = parseFloat(row.marks_obtained || 0);
      const max = parseFloat(row.max_marks || 0);

      if (!map.has(subject)) {
        map.set(subject, { subject, marks: 0, max: 0 });
      }

      const entry = map.get(subject);
      entry.marks += marks;
      entry.max += max;
    });

    return Array.from(map.values()).map((entry) => ({
      subject: entry.subject,
      score: entry.max > 0 ? Math.round((entry.marks / entry.max) * 100) : 0,
    }));
  },

  gradeFromScore: function (score) {
    if (score >= 80) return "A";
    if (score >= 70) return "B";
    if (score >= 60) return "C";
    if (score >= 50) return "D";
    return "E";
  },

  exportReport: function () {
    const subjectStats = this.groupBySubject(this.state.performance || []);
    if (!subjectStats.length) {
      this.showError("No data to export");
      return;
    }

    const rows = ["Subject,Score"]; 
    subjectStats.forEach((item) => {
      rows.push(`${item.subject},${item.score}`);
    });

    const blob = new Blob([rows.join("\n")], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "student_performance.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  unwrapPayload: function (response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined) return response.data.data;
    return response;
  },

  showError: function (message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "error");
    } else {
      alert("Error: " + message);
    }
  },
};

document.addEventListener("DOMContentLoaded", () =>
  StudentPerformanceController.init()
);

window.StudentPerformanceController = StudentPerformanceController;
