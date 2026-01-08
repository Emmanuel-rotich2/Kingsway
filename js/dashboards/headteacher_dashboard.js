/**
 * Headteacher Dashboard Controller
 * 
 * Purpose: ACADEMIC OVERSIGHT & ADMINISTRATION
 * - Monitor all classes and student progress
 * - Manage timetables and schedules
 * - Handle admissions and discipline
 * - Track parent communications
 * 
 * Role: Headteacher (Role ID: 5), Deputy Head (Role ID: 6), HOD (Role ID: 63)
 * Update Frequency: 30-minute refresh
 * 
 * Data Isolation: Academic data only, department-level (no finance, staff salary, system data)
 * 
 * Summary Cards (8):
 * 1. Total Students - Enrolled in department/year level
 * 2. Attendance Today - Student presence percentage
 * 3. Class Schedules - Active classes this week
 * 4. Pending Admissions - Applications waiting review
 * 5. Discipline Cases - Open discipline issues
 * 6. Parent Communications - Messages sent this week
 * 7. Student Assessments - Recent test results summary
 * 8. Class Performance - Academic results trend
 * 
 * Charts (2):
 * 1. Weekly Class Attendance Trend
 * 2. Academic Performance by Class
 * 
 * Tables (2):
 * 1. Pending Admissions
 * 2. Open Discipline Cases
 */

const headteacherDashboardController = {
  state: {
    summaryCards: {},
    chartData: {},
    tableData: {},
    lastRefresh: null,
    isLoading: false,
    errorMessage: null,
  },

  charts: {},

  config: {
    refreshInterval: 1800000, // 30 minutes
  },

  init: function () {
    console.log("🚀 Headteacher Dashboard initializing...");

    // Check API availability
    if (
      typeof window.API === "undefined" ||
      typeof window.API.dashboard === "undefined"
    ) {
      console.error("❌ API module not available");
      this.showErrorState("API module not loaded. Please refresh the page.");
      // Still try to load with fallback data
      this.loadFallbackData();
      this.renderDashboard();
      return;
    }

    this.loadDashboardData();
    this.setupEventListeners();
    this.setupAutoRefresh();

    console.log("✓ Headteacher Dashboard initialized");
  },

  loadDashboardData: async function () {
    if (this.state.isLoading) return;

    this.state.isLoading = true;
    this.state.errorMessage = null;
    this.showLoading(true);
    this.hideErrorState();
    const startTime = performance.now();

    try {
      console.log("📡 Fetching headteacher dashboard data via API...");

      // Use the centralized API module - response is already unwrapped
      const data = await window.API.dashboard.getHeadteacherFull();

      console.log("📦 Received dashboard data:", data);

      if (!data) {
        throw new Error("No data received from API");
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
    console.log("[HeadteacherDashboard] processCardsData called with:", cards);
    if (!cards) {
      console.warn(
        "[HeadteacherDashboard] processCardsData: cards is null/undefined"
      );
      return;
    }

    // Card 1: Total Students
    const totalStudents = cards.total_students || cards.totalStudents;
    if (totalStudents) {
      this.state.summaryCards.students = {
        title: "Total Students",
        value: totalStudents.total_students || totalStudents.value || "0",
        subtitle: "Enrolled",
        secondary:
          (totalStudents.active_streams || totalStudents.streams || "0") +
          " streams",
        color: "primary",
        icon: "bi-people",
      };
    }

    // Card 2: Attendance Today
    const attendanceToday = cards.attendance_today || cards.attendanceToday;
    if (attendanceToday) {
      this.state.summaryCards.attendance = {
        title: "Attendance Today",
        value: (attendanceToday.percentage || "0") + "%",
        subtitle: "Present",
        secondary:
          (attendanceToday.present || "0") +
          " of " +
          (attendanceToday.total || "0"),
        color: "success",
        icon: "bi-check-circle",
      };
    }

    // Card 3: Class Schedules
    const schedules = cards.class_schedules || cards.classSchedules;
    if (schedules) {
      this.state.summaryCards.schedules = {
        title: "Class Schedules",
        value: schedules.total_sessions || schedules.value || "0",
        subtitle: "This week",
        secondary: (schedules.upcoming || "0") + " upcoming",
        color: "info",
        icon: "bi-calendar3",
      };
    }

    // Card 4: Pending Admissions
    const admissions = cards.pending_admissions || cards.pendingAdmissions;
    if (admissions) {
      this.state.summaryCards.admissions = {
        title: "Pending Admissions",
        value: admissions.pending_applications || admissions.pending || "0",
        subtitle: "To review",
        secondary: (admissions.approved || "0") + " approved",
        color: "warning",
        icon: "bi-inbox",
      };
    }

    // Card 5: Discipline Cases
    const discipline = cards.discipline_cases || cards.disciplineCases;
    if (discipline) {
      this.state.summaryCards.discipline = {
        title: "Discipline Cases",
        value: discipline.open_cases || discipline.open || "0",
        subtitle: "Open",
        secondary: (discipline.resolved_this_month || "0") + " resolved",
        color: "danger",
        icon: "bi-exclamation-triangle",
      };
    }

    // Card 6: Parent Communications
    const communications =
      cards.parent_communications || cards.parentCommunications;
    if (communications) {
      this.state.summaryCards.communications = {
        title: "Communications",
        value: communications.sent_this_week || communications.value || "0",
        subtitle: "Sent",
        secondary:
          (communications.pending_responses || "0") + " responses pending",
        color: "secondary",
        icon: "bi-chat-dots",
      };
    }

    // Card 7: Student Assessments
    const assessments = cards.student_assessments || cards.studentAssessments;
    if (assessments) {
      this.state.summaryCards.assessments = {
        title: "Assessments",
        value:
          assessments.total_assessments ||
          assessments.graded_this_month ||
          assessments.value ||
          "0",
        subtitle: "Total",
        secondary:
          (assessments.pending_approval || assessments.pending_marking || "0") +
          " pending approval",
        color: "success",
        icon: "bi-graph-up",
      };
    }

    // Card 8: Class Performance
    const performance = cards.class_performance || cards.classPerformance;
    if (performance) {
      this.state.summaryCards.performance = {
        title: "Class Performance",
        value: (performance.average_performance || "0") + "%",
        subtitle: "Average",
        secondary: (performance.high_performers || "0") + " high performers",
        color: "primary",
        icon: "bi-bar-chart",
      };
    }
  },

  loadFallbackData: function () {
    console.warn(
      "⚠️ Loading FALLBACK demo data - API unavailable or returned error"
    );
    console.warn("⚠️ The values displayed below are NOT from the database!");
    this.state.usingFallbackData = true;

    // Fallback cards - SAMPLE DATA ONLY (not from database)
    this.state.summaryCards = {
      students: {
        title: "Total Students",
        value: "--",
        subtitle: "Enrolled",
        secondary: "No data",
        color: "primary",
        icon: "bi-people",
      },
      attendance: {
        title: "Attendance Today",
        value: "--%",
        subtitle: "Present",
        secondary: "No data",
        color: "success",
        icon: "bi-check-circle",
      },
      schedules: {
        title: "Class Schedules",
        value: "--",
        subtitle: "This week",
        secondary: "No data",
        color: "info",
        icon: "bi-calendar3",
      },
      admissions: {
        title: "Pending Admissions",
        value: "--",
        subtitle: "To review",
        secondary: "No data",
        color: "warning",
        icon: "bi-inbox",
      },
      discipline: {
        title: "Discipline Cases",
        value: "--",
        subtitle: "Open",
        secondary: "No data",
        color: "danger",
        icon: "bi-exclamation-triangle",
      },
      communications: {
        title: "Communications",
        value: "--",
        subtitle: "Sent",
        secondary: "No data",
        color: "secondary",
        icon: "bi-chat-dots",
      },
      assessments: {
        title: "Assessments",
        value: "--",
        subtitle: "Total",
        secondary: "No data",
        color: "success",
        icon: "bi-graph-up",
      },
      performance: {
        title: "Class Performance",
        value: "--%",
        subtitle: "Average",
        secondary: "No data",
        color: "primary",
        icon: "bi-bar-chart",
      },
    };

    // Fallback charts - empty
    this.state.chartData = {
      attendance_trend: { labels: [], data: [] },
      class_performance: { labels: [], data: [] },
    };

    // Fallback tables - empty
    this.state.tableData = {
      pending_admissions: { data: [], total: 0 },
      discipline_cases: { data: [], total: 0 },
      upcoming_events: { data: [], total: 0 },
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
    // Update individual card elements (matching the PHP stat-card structure)
    const cards = this.state.summaryCards;

    // Card 1: Total Students
    if (cards.students) {
      this.updateElement("totalStudents", cards.students.value);
      this.updateElement(
        "studentGrowth",
        cards.students.secondary || "Enrolled this term"
      );
    }

    // Card 2: Attendance Today
    if (cards.attendance) {
      this.updateElement("attendanceToday", cards.attendance.value);
      this.updateElement(
        "attendanceDetails",
        cards.attendance.secondary || "Present: -- | Absent: --"
      );
    }

    // Card 3: Class Schedules
    if (cards.schedules) {
      this.updateElement("classSchedules", cards.schedules.value);
    }

    // Card 4: Pending Admissions
    if (cards.admissions) {
      this.updateElement("pendingAdmissions", cards.admissions.value);
      this.updateElement(
        "admissionDetails",
        cards.admissions.secondary || "Applications awaiting review"
      );
    }

    // Card 5: Discipline Cases
    if (cards.discipline) {
      this.updateElement("disciplineCases", cards.discipline.value);
      this.updateElement(
        "disciplineDetails",
        cards.discipline.secondary || "Open cases requiring attention"
      );
    }

    // Card 6: Parent Communications
    if (cards.communications) {
      this.updateElement("parentComms", cards.communications.value);
    }

    // Card 7: Student Assessments
    if (cards.assessments) {
      this.updateElement("assessments", cards.assessments.value);
      this.updateElement(
        "assessmentDetails",
        cards.assessments.secondary || "Recent tests & exams"
      );
    }

    // Card 8: Class Performance
    if (cards.performance) {
      this.updateElement("classPerformance", cards.performance.value);
    }

    // Update last updated time
    this.updateElement("lastUpdated", new Date().toLocaleTimeString());
  },

  updateElement: function (id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  },

  renderCharts: function () {
    // Destroy existing charts first
    this.destroyCharts();

    // Attendance Trend Chart
    const attendanceCtx = document.getElementById("attendanceChart");
    if (attendanceCtx && this.state.chartData.attendance_trend) {
      const chartData = this.state.chartData.attendance_trend;
      this.charts.attendance = new Chart(attendanceCtx, {
        type: "line",
        data: {
          labels: chartData.labels ||
            chartData.days || ["Mon", "Tue", "Wed", "Thu", "Fri"],
          datasets: [
            {
              label: "Attendance %",
              data: chartData.data || [87, 89, 91, 88, 90],
              borderColor: "#0d6efd",
              backgroundColor: "rgba(13, 110, 253, 0.1)",
              fill: true,
              tension: 0.4,
              pointRadius: 5,
              pointBackgroundColor: "#0d6efd",
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

    // Performance Chart
    const performanceCtx = document.getElementById("performanceChart");
    if (performanceCtx && this.state.chartData.class_performance) {
      const chartData = this.state.chartData.class_performance;
      this.charts.performance = new Chart(performanceCtx, {
        type: "bar",
        data: {
          labels: chartData.labels || ["Form 1", "Form 2", "Form 3", "Form 4"],
          datasets: [
            {
              label: "Average Score",
              data: chartData.data || [72, 75, 78, 81],
              backgroundColor: "#198754",
              borderColor: "#198754",
              borderWidth: 1,
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
  },

  renderTables: function () {
    // Pending Admissions Table (matches PHP id="admissionsTableBody")
    const admissionsTable = document.getElementById("admissionsTableBody");
    if (admissionsTable && this.state.tableData.pending_admissions) {
      const data =
        this.state.tableData.pending_admissions.data ||
        this.state.tableData.pending_admissions ||
        [];
      if (data.length === 0) {
        admissionsTable.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted py-3">No pending admissions</td></tr>';
      } else {
        admissionsTable.innerHTML = data
          .slice(0, 5)
          .map(
            (row) => `
                    <tr>
                        <td>${row.student_name || row.name || "-"}</td>
                        <td>${row.class_applied || row.form || "-"}</td>
                        <td><small>${this.formatDate(
                          row.submitted_at || row.applied
                        )}</small></td>
                        <td>
                            <span class="badge bg-warning">Pending</span>
                        </td>
                    </tr>
                `
          )
          .join("");
      }
    }

    // Discipline Cases Table (matches PHP id="disciplineTableBody")
    const disciplineTable = document.getElementById("disciplineTableBody");
    if (disciplineTable && this.state.tableData.discipline_cases) {
      const data =
        this.state.tableData.discipline_cases.data ||
        this.state.tableData.discipline_cases ||
        [];
      if (data.length === 0) {
        disciplineTable.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted py-3">No open discipline cases</td></tr>';
      } else {
        disciplineTable.innerHTML = data
          .slice(0, 5)
          .map(
            (row) => `
                    <tr>
                        <td>${row.student_name || row.student || "-"}</td>
                        <td>${row.class_name || row.form || "-"}</td>
                        <td><small>${
                          row.violation || row.description || row.offense || "-"
                        }</small></td>
                        <td>
                            <span class="badge bg-${
                              row.severity === "high" ||
                              row.severity === "Major"
                                ? "danger"
                                : "warning"
                            }">
                                ${row.severity || "Pending"}
                            </span>
                        </td>
                    </tr>
                `
          )
          .join("");
      }
    }

    // Upcoming Events (matches PHP id="upcomingEvents")
    const eventsContainer = document.getElementById("upcomingEvents");
    if (eventsContainer && this.state.tableData.upcoming_events) {
      const events =
        this.state.tableData.upcoming_events.data ||
        this.state.tableData.upcoming_events ||
        [];
      if (events.length === 0) {
        eventsContainer.innerHTML =
          '<li class="list-group-item text-center text-muted py-3">No upcoming events</li>';
      } else {
        eventsContainer.innerHTML = events
          .slice(0, 5)
          .map(
            (event) => `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${event.title || event.name || "-"}</strong>
                            <br><small class="text-muted">${this.formatDate(
                              event.event_date || event.date
                            )}</small>
                        </div>
                        <span class="badge bg-info">${
                          event.type || "Event"
                        }</span>
                    </li>
                `
          )
          .join("");
      }
    }
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
    // Update refresh button state
    const refreshBtn = document.getElementById("refreshDashboard");
    if (refreshBtn) {
      if (show) {
        refreshBtn.disabled = true;
        refreshBtn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';
      } else {
        refreshBtn.disabled = false;
        refreshBtn.innerHTML =
          '<i class="bi bi-arrow-clockwise me-1"></i> Refresh';
      }
    }
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
    // Refresh button
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
      console.log("🔄 Auto-refreshing headteacher dashboard...");
      this.loadDashboardData();
    }, this.config.refreshInterval);
  },

  // Utility methods
  formatNumber: function (num) {
    if (num === undefined || num === null) return "0";
    num = Number(num);
    if (num >= 1000000) return (num / 1000000).toFixed(1) + "M";
    if (num >= 1000) return (num / 1000).toFixed(1) + "K";
    return num.toLocaleString();
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
    return key.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
  },
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    headteacherDashboardController.init();
});
