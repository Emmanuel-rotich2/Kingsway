/**
 * Class Teacher Dashboard Controller
 * 
 * Purpose: MY CLASS MANAGEMENT
 * - Monitor assigned class only (data isolation)
 * - Track student attendance and performance
 * - Manage assessments and lesson plans
 * - Communicate with students and parents
 * 
 * Role: Class Teacher (Role ID: 7)
 * Update Frequency: 15-minute refresh
 * 
 * Data Isolation: ONLY MY ASSIGNED CLASS
 * 
 * Summary Cards (6):
 * 1. My Students - Total students in assigned class
 * 2. Today Attendance - Attendance rate for today
 * 3. Pending Assessments - Assessments to grade
 * 4. Lesson Plans - Weekly lesson plans status
 * 5. Communications - Messages sent this week
 * 6. Class Performance - Average grade for class
 * 
 * Charts (2):
 * 1. Weekly Attendance Trend
 * 2. Assessment Performance Distribution
 * 
 * Tables (3):
 * 1. Today's Schedule
 * 2. Student Assessment Status
 * 3. Class Roster
 */

const classTeacherDashboardController = {
  state: {
    className: "",
    summaryCards: {},
    chartData: {},
    tableData: {},
    lastRefresh: null,
    isLoading: false,
    errorMessage: null,
  },

  charts: {},

  config: {
    refreshInterval: 900000, // 15 minutes
  },

  init: function () {
    console.log("🚀 Class Teacher Dashboard initializing...");

    // Check API availability
    if (
      typeof window.API === "undefined" ||
      typeof window.API.dashboard === "undefined"
    ) {
      console.error("❌ API module not available");
      this.showErrorState("API module not loaded. Please refresh the page.");
      this.loadFallbackData();
      this.renderDashboard();
      return;
    }

    this.loadDashboardData();
    this.setupEventListeners();
    this.setupAutoRefresh();

    console.log("✓ Class Teacher Dashboard initialized");
  },

  loadDashboardData: async function () {
    if (this.state.isLoading) return;

    this.state.isLoading = true;
    this.state.errorMessage = null;
    this.showLoading(true);
    this.hideErrorState();
    const startTime = performance.now();

    try {
      console.log("📡 Fetching class teacher dashboard data via API...");

      // Use the centralized API module - response is already unwrapped
      const data = await window.API.dashboard.getClassTeacherFull();

      console.log("📦 Received dashboard data:", data);

      if (!data) {
        throw new Error("No data received from API");
      }

      // Set class name
      if (data.className) {
        this.state.className = data.className;
        const badge = document.getElementById("classNameBadge");
        if (badge) badge.textContent = data.className;
      }

      // Process cards data
      if (data.cards) {
        this.processCardsData(data.cards);
      }

      // Process charts data
      if (data.charts) {
        this.state.chartData = data.charts;
      }

      // Process tables data
      if (data.tables) {
        this.state.tableData = data.tables;
      }

      // Render dashboard
      this.renderDashboard();

      this.state.lastRefresh = new Date();
      const duration = (performance.now() - startTime).toFixed(2);
      console.log(`✓ Dashboard loaded in ${duration}ms`);
    } catch (error) {
      console.error("❌ Error loading dashboard:", error);
      this.state.errorMessage = error.message;
      this.showErrorState(error.message);

      // Load fallback data
      this.loadFallbackData();
      this.renderDashboard();
    } finally {
      this.state.isLoading = false;
      this.showLoading(false);
    }
  },

  processCardsData: function (cards) {
    console.log("[ClassTeacherDashboard] processCardsData called with:", cards);
    if (!cards) {
      console.warn(
        "[ClassTeacherDashboard] processCardsData: cards is null/undefined"
      );
      return;
    }

    // Card 1: My Students
    const myStudents = cards.my_students || cards.myStudents;
    if (myStudents) {
      this.state.summaryCards.myStudents = {
        title: "My Class",
        value: myStudents.total || "0",
        subtitle: myStudents.class_name || "Students",
        secondary:
          (myStudents.male || "0") +
          " boys, " +
          (myStudents.female || "0") +
          " girls",
        color: "primary",
        icon: "bi-people-fill",
      };
    }

    // Card 2: Today's Attendance
    const todayAttendance = cards.today_attendance || cards.todayAttendance;
    if (todayAttendance) {
      this.state.summaryCards.todayAttendance = {
        title: "Today's Attendance",
        value: (todayAttendance.percentage || "0") + "%",
        subtitle: "Present",
        secondary:
          (todayAttendance.present || "0") +
          " present, " +
          (todayAttendance.absent || "0") +
          " absent",
        color: "success",
        icon: "bi-check-circle",
      };
    }

    // Card 3: Pending Assessments
    const pendingAssessments =
      cards.pending_assessments || cards.pendingAssessments;
    if (pendingAssessments) {
      this.state.summaryCards.pendingAssessments = {
        title: "Pending Assessments",
        value: pendingAssessments.pending || "0",
        subtitle: "To grade",
        secondary:
          (pendingAssessments.graded_this_week || "0") + " graded this week",
        color: "warning",
        icon: "bi-clipboard-check",
      };
    }

    // Card 4: Lesson Plans
    const lessonPlans = cards.lesson_plans || cards.lessonPlans;
    if (lessonPlans) {
      this.state.summaryCards.lessonPlans = {
        title: "Lesson Plans",
        value: lessonPlans.this_week || "0",
        subtitle: "This week",
        secondary: (lessonPlans.total || "0") + " total",
        color: "info",
        icon: "bi-journal-bookmark",
      };
    }

    // Card 5: Communications
    const communications = cards.communications || cards.communications;
    if (communications) {
      this.state.summaryCards.communications = {
        title: "Communications",
        value: communications.sent_this_week || "0",
        subtitle: "Sent",
        secondary: (communications.unread_responses || "0") + " responses",
        color: "secondary",
        icon: "bi-chat-dots",
      };
    }

    // Card 6: Class Performance
    const classPerformance = cards.class_performance || cards.classPerformance;
    if (classPerformance) {
      this.state.summaryCards.classPerformance = {
        title: "Class Performance",
        value: (classPerformance.average_score || "0") + "%",
        subtitle: "Average",
        secondary:
          (classPerformance.high_performers || "0") + " excellent performers",
        color: "success",
        icon: "bi-graph-up",
      };
    }
  },

  loadFallbackData: function () {
    console.log("⚠️ Loading fallback data...");

    this.state.className = "Form 3A";
    const badge = document.getElementById("classNameBadge");
    if (badge) badge.textContent = "Form 3A";

    // Fallback cards
    this.state.summaryCards = {
      myStudents: {
        title: "My Students",
        value: "32",
        subtitle: "Enrolled",
        secondary: "Form 3A",
        color: "primary",
        icon: "bi-people-fill",
      },
      todayAttendance: {
        title: "Today Attendance",
        value: "87%",
        subtitle: "Present",
        secondary: "28 of 32",
        color: "success",
        icon: "bi-check-circle",
      },
      pendingAssessments: {
        title: "Pending Assessments",
        value: "5",
        subtitle: "To grade",
        secondary: "2 overdue",
        color: "warning",
        icon: "bi-clipboard-check",
      },
      lessonPlans: {
        title: "Lesson Plans",
        value: "12",
        subtitle: "This week",
        secondary: "10 completed",
        color: "info",
        icon: "bi-journal-bookmark",
      },
      communications: {
        title: "Communications",
        value: "8",
        subtitle: "Sent",
        secondary: "15 responses",
        color: "secondary",
        icon: "bi-chat-dots",
      },
      classPerformance: {
        title: "Class Performance",
        value: "76%",
        subtitle: "Average",
        secondary: "↑ 3% from last week",
        color: "success",
        icon: "bi-graph-up",
      },
    };

    // Fallback charts
    this.state.chartData = {
      attendance: {
        labels: ["Mon", "Tue", "Wed", "Thu", "Fri"],
        data: [90, 88, 92, 87, 91],
      },
      performance: {
        labels: ["Quiz 1", "Quiz 2", "Test 1", "Quiz 3", "Test 2"],
        data: [68, 72, 75, 79, 81],
      },
    };

    // Fallback tables
    this.state.tableData = {
      schedule: [
        {
          time: "08:00 - 08:40",
          subject: "Mathematics",
          topic: "Quadratic Equations",
          status: "Completed",
        },
        {
          time: "08:40 - 09:20",
          subject: "English",
          topic: "Essay Writing",
          status: "In Progress",
        },
        {
          time: "10:00 - 10:40",
          subject: "Science",
          topic: "Chemical Reactions",
          status: "Upcoming",
        },
      ],
      assessments: [
        {
          student: "John Ochieng",
          subject: "Math",
          assessment: "Quiz 3",
          score: 85,
          grade: "A",
        },
        {
          student: "Sarah Wanjiru",
          subject: "Math",
          assessment: "Quiz 3",
          score: 78,
          grade: "B",
        },
      ],
      roster: [
        {
          id: 1,
          name: "John Ochieng",
          admNo: "ADM001",
          gender: "M",
          attendance: "95%",
          avgScore: "82%",
        },
        {
          id: 2,
          name: "Sarah Wanjiru",
          admNo: "ADM002",
          gender: "F",
          attendance: "92%",
          avgScore: "78%",
        },
      ],
    };
  },

  renderDashboard: function () {
    console.log("🎨 Rendering dashboard...");

    this.renderSummaryCards();
    this.renderCharts();
    this.renderTables();

    // Update last refresh time
    const refreshTime = document.getElementById("lastRefreshTime");
    if (refreshTime && this.state.lastRefresh) {
      refreshTime.textContent = this.state.lastRefresh.toLocaleTimeString();
    }

    console.log("✓ Dashboard rendered");
  },

  renderSummaryCards: function () {
    const container = document.getElementById("summaryCardsContainer");
    if (!container) {
      console.warn("⚠️ summaryCardsContainer not found");
      return;
    }

    container.innerHTML = "";

    const cardOrder = [
      "myStudents",
      "todayAttendance",
      "pendingAssessments",
      "lessonPlans",
      "communications",
      "classPerformance",
    ];

    cardOrder.forEach((key) => {
      const card = this.state.summaryCards[key];
      if (!card) return;

      const cardHtml = `
                <div class="col-md-6 col-lg-4 col-xl-2">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-title text-muted mb-2 small">${
                                      card.title
                                    }</h6>
                                    <h3 class="fw-bold text-${
                                      card.color
                                    } mb-1">${card.value}</h3>
                                    <small class="text-secondary">${
                                      card.subtitle
                                    }</small>
                                    ${
                                      card.secondary
                                        ? `<br><small class="text-muted">${card.secondary}</small>`
                                        : ""
                                    }
                                </div>
                                <div class="text-${card.color} opacity-50">
                                    <i class="bi ${card.icon} fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

      container.insertAdjacentHTML("beforeend", cardHtml);
    });
  },

  renderCharts: function () {
    this.destroyCharts();

    // Attendance Trend Chart
    const attendanceCtx = document.getElementById("attendanceChart");
    if (attendanceCtx && this.state.chartData.attendance_trend) {
      const chartData = this.state.chartData.attendance_trend;
      this.charts.attendance = new Chart(attendanceCtx, {
        type: "line",
        data: {
          labels: chartData.labels || ["Mon", "Tue", "Wed", "Thu", "Fri"],
          datasets: [
            {
              label: "Attendance %",
              data: chartData.data || [90, 88, 92, 87, 91],
              borderColor: "#198754",
              backgroundColor: "rgba(25, 135, 84, 0.1)",
              fill: true,
              tension: 0.4,
              pointRadius: 5,
              pointBackgroundColor: "#198754",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: { legend: { display: false } },
          scales: {
            y: { min: 0, max: 100, ticks: { callback: (v) => v + "%" } },
          },
        },
      });
    }

    // Assessment Performance Chart
    const performanceCtx = document.getElementById("performanceChart");
    if (performanceCtx && this.state.chartData.assessment_performance) {
      const chartData = this.state.chartData.assessment_performance;
      this.charts.performance = new Chart(performanceCtx, {
        type: "bar",
        data: {
          labels: chartData.labels || [
            "A (80-100)",
            "B (60-79)",
            "C (40-59)",
            "D (<40)",
          ],
          datasets: [
            {
              label: "Students",
              data: chartData.data || [8, 15, 12, 5],
              backgroundColor: "#0d6efd",
              borderColor: "#0d6efd",
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: { legend: { display: false } },
          scales: {
            y: { min: 0, ticks: { callback: (v) => v + " students" } },
          },
        },
      });
    }
  },

  renderTables: function () {
    // Today's Schedule Table
    const scheduleTable = document.querySelector("#scheduleTable tbody");
    if (scheduleTable && this.state.tableData.today_schedule) {
      const data = this.state.tableData.today_schedule || [];
      if (data.length === 0) {
        scheduleTable.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted py-3">No classes scheduled today</td></tr>';
      } else {
        scheduleTable.innerHTML = data
          .map((row) => {
            return `
                        <tr>
                            <td><small>${
                              row.time || row.start_time || "-"
                            }</small></td>
                            <td>${row.subject || row.name || "-"}</td>
                            <td><small>${
                              row.location || row.room || "-"
                            }</small></td>
                            <td>${row.teacher || "-"}</td>
                        </tr>
                    `;
          })
          .join("");
      }
    }

    // Student Assessment Status Table
    const assessmentTable = document.querySelector("#assessmentTable tbody");
    if (assessmentTable && this.state.tableData.student_assessment_status) {
      const data = this.state.tableData.student_assessment_status || [];
      if (data.length === 0) {
        assessmentTable.innerHTML =
          '<tr><td colspan="5" class="text-center text-muted py-3">No assessment data</td></tr>';
      } else {
        assessmentTable.innerHTML = data
          .map(
            (row) => `
                    <tr>
                        <td>${row.student_name || "-"}</td>
                        <td><small>${row.admission_no || "-"}</small></td>
                        <td>${row.average_score || "-"}</td>
                        <td>${row.assessments_taken || "-"}</td>
                        <td><span class="badge bg-${this.getStatusBadgeColor(
                          row.status
                        )}">${row.status || "-"}</span></td>
                    </tr>
                `
          )
          .join("");
      }
    }

    // Student Roster Table
    const rosterTable = document.querySelector("#studentRosterTable tbody");
    if (rosterTable && this.state.tableData.student_roster) {
      const data = this.state.tableData.student_roster || [];
      if (data.length === 0) {
        rosterTable.innerHTML =
          '<tr><td colspan="6" class="text-center text-muted py-3">No students in class</td></tr>';
      } else {
        rosterTable.innerHTML = data
          .slice(0, 20)
          .map(
            (row, idx) => `
                    <tr>
                        <td><small>${idx + 1}</small></td>
                        <td>${row.name || row.student_name || "-"}</td>
                        <td><small class="text-muted">${
                          row.admission_no || "-"
                        }</small></td>
                        <td>${row.gender || "-"}</td>
                        <td><span class="badge bg-${this.getAttendanceBadgeColor(
                          row.attendance_today
                        )}">${row.attendance_today || "-"}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" title="View Profile">
                                <i class="bi bi-person"></i>
                            </button>
                        </td>
                    </tr>
                `
          )
          .join("");
      }
    }
  },

  getStatusBadgeColor: function (status) {
    const colors = {
      Excellent: "success",
      Good: "primary",
      "Needs Support": "warning",
    };
    return colors[status] || "secondary";
  },

  getAttendanceBadgeColor: function (attendance) {
    if (!attendance) return "secondary";
    if (attendance === "Present") return "success";
    if (attendance === "Late") return "warning";
    if (attendance === "Absent") return "danger";
    return "secondary";
  },

  destroyCharts: function () {
    Object.values(this.charts).forEach((chart) => {
      if (chart && typeof chart.destroy === "function") {
        chart.destroy();
      }
    });
    this.charts = {};
  },

  showLoading: function (show) {
    const loading = document.getElementById("dashboardLoading");
    const content = document.getElementById("summaryCardsContainer");

    if (loading) loading.style.display = show ? "block" : "none";
    if (content) content.style.opacity = show ? "0.5" : "1";
  },

  showErrorState: function (message) {
    const errorDiv = document.getElementById("dashboardError");
    const errorMsg = document.getElementById("dashboardErrorMessage");

    if (errorDiv) {
      errorDiv.style.display = "block";
      if (errorMsg)
        errorMsg.textContent = message || "Failed to load dashboard data";
    }
  },

  hideErrorState: function () {
    const errorDiv = document.getElementById("dashboardError");
    if (errorDiv) errorDiv.style.display = "none";
  },

  setupEventListeners: function () {
    const refreshBtn = document.getElementById("refreshDashboard");
    if (refreshBtn) {
      refreshBtn.addEventListener("click", () => {
        this.hideErrorState();
        this.loadDashboardData();
      });
    }
  },

  setupAutoRefresh: function () {
    setInterval(() => {
      console.log("🔄 Auto-refreshing class teacher dashboard...");
      this.loadDashboardData();
    }, this.config.refreshInterval);
  },

  // Utility methods
  formatNumber: function (num) {
    if (num === undefined || num === null) return "0";
    return Number(num).toLocaleString();
  },

  formatPercent: function (num) {
    return Math.round(Number(num) || 0) + "%";
  },

  formatDate: function (date) {
    if (!date) return "-";
    try {
      return new Date(date).toLocaleDateString("en-KE", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
    } catch (e) {
      return date;
    }
  },

  formatTitle: function (key) {
    return key
      .replace(/([A-Z])/g, " $1")
      .replace(/^./, (str) => str.toUpperCase());
  },
};

// Note: Init is called from the PHP component
// document.addEventListener('DOMContentLoaded', () => classTeacherDashboardController.init());
