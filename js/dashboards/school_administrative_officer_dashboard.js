/**
 * School Administrator Dashboard Controller
 * 
 * TIER 3: Operational School Management Dashboard
 * 
 * Purpose: Day-to-day school operations management
 * - Manage daily operations
 * - Coordinate activities and staff
 * - Monitor student enrollment and attendance
 * - Manage communications and admissions
 * 
 * Role: School Administrative Officer (Role ID: 4)
 * Update Frequency: 15-minute auto-refresh
 * 
 * Summary Cards (10):
 * Row 1: Active Students, Teaching Staff, Staff Activities, Class Timetables, Daily Attendance
 * Row 2: Announcements, Student Admissions, Staff Leaves, Class Distribution, System Status
 * 
 * Charts (2):
 * 1. Weekly Attendance Trend (Line Chart)
 * 2. Class Distribution (Bar Chart)
 * 
 * Tables (3 tabs):
 * 1. Pending Items - Admission applications, leave requests, staff assignments
 * 2. Today's Schedule - Classes, activities, events
 * 3. Staff Directory - Contact info for all active staff
 * 
 * @package App\JS\Dashboards
 * @since 2025-01-06
 */

const schoolAdminDashboardController = {
  // =========================================================================
  // STATE MANAGEMENT
  // =========================================================================
  state: {
    cards: {
      activeStudents: { value: "--", classes: "--" },
      teachingStaff: { value: "--", presentPercent: "--" },
      staffActivities: { onLeave: "--", assignments: "--" },
      classTimetables: { value: "--", classesPerWeek: "--" },
      dailyAttendance: { percent: "--", present: "--", absent: "--" },
      announcements: { count: "--", recipients: "--" },
      studentAdmissions: { pending: "--", approved: "--" },
      staffLeaves: { today: "--" },
      classDistribution: { average: "--", max: "--" },
      systemStatus: { status: "Loading...", uptime: "--" },
    },
    charts: {
      attendanceTrend: { labels: [], data: [] },
      classDistribution: { labels: [], data: [] },
    },
    tables: {
      pendingItems: [],
      todaySchedule: [],
      staffDirectory: [],
    },
    lastRefresh: null,
    isLoading: false,
    error: null,
  },

  // Chart.js instances
  chartInstances: {
    attendanceTrend: null,
    classDistribution: null,
  },

  // Configuration
  config: {
    refreshInterval: 900000, // 15 minutes
    apiBase: "/Kingsway/api/",
    debug: true,
  },

  // =========================================================================
  // INITIALIZATION
  // =========================================================================
  init: function () {
    this.log("🚀 School Administrator Dashboard initializing...");

    // Check authentication
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      this.log("❌ User not authenticated, redirecting...");
      window.location.href = "/Kingsway/index.php";
      return;
    }

    // Initialize dashboard
    this.bindEventListeners();
    this.loadDashboardData();
    this.setupAutoRefresh();

    this.log("✓ School Administrator Dashboard initialized");
  },

  // =========================================================================
  // EVENT LISTENERS
  // =========================================================================
  bindEventListeners: function () {
    // Refresh button
    const refreshBtn = document.getElementById("refreshDashboard");
    if (refreshBtn) {
      refreshBtn.addEventListener("click", () => this.loadDashboardData());
    }

    // Export button
    const exportBtn = document.getElementById("exportDashboard");
    if (exportBtn) {
      exportBtn.addEventListener("click", () => this.exportDashboard());
    }

    // Chart filter buttons
    document.querySelectorAll("[data-range]").forEach((btn) => {
      btn.addEventListener("click", (e) => this.handleChartRangeChange(e));
    });

    // Class distribution filter
    const classFilter = document.getElementById("classDistributionFilter");
    if (classFilter) {
      classFilter.addEventListener("change", (e) =>
        this.handleClassFilterChange(e)
      );
    }

    // Staff search
    const staffSearch = document.getElementById("staffSearchInput");
    if (staffSearch) {
      staffSearch.addEventListener("input", (e) => this.handleStaffSearch(e));
    }
  },

  setupAutoRefresh: function () {
    setInterval(() => {
      if (!this.state.isLoading) {
        this.log("🔄 Auto-refreshing dashboard...");
        this.loadDashboardData();
      }
    }, this.config.refreshInterval);
  },

  // =========================================================================
  // DATA LOADING
  // =========================================================================
  loadDashboardData: async function () {
    if (this.state.isLoading) return;

    this.state.isLoading = true;
    this.state.error = null;
    const startTime = performance.now();

    try {
      this.log("📡 Fetching operational metrics from API...");

      // Use the optimized full dashboard endpoint for initial load
      const response = await API.dashboard.getSchoolAdminFull();

      // DEBUG: Log the full response
      console.log("[SchoolAdminDashboard] API Response:", response);
      console.log("[SchoolAdminDashboard] Response type:", typeof response);
      console.log("[SchoolAdminDashboard] Has cards?:", !!response?.cards);
      console.log(
        "[SchoolAdminDashboard] Has data.cards?:",
        !!response?.data?.cards
      );

      // api.js handleApiResponse() unwraps successful responses
      // So response IS the data directly (not wrapped in {status, data})
      // Check if we have the data directly or wrapped
      let data;
      if (response && response.cards) {
        // Response is the data directly (unwrapped by handleApiResponse)
        data = response;
        console.log("[SchoolAdminDashboard] Using direct response (unwrapped)");
      } else if (response && response.data && response.data.cards) {
        // Response is wrapped {status, data}
        data = response.data;
        console.log("[SchoolAdminDashboard] Using response.data (wrapped)");
      } else if (
        response &&
        (response.success || response.status === "success")
      ) {
        // Old format check
        data = response.data || response;
        console.log("[SchoolAdminDashboard] Using old format check");
      } else {
        data = null;
        console.log("[SchoolAdminDashboard] No valid data found");
      }

      if (data && data.cards) {
        console.log(
          "[SchoolAdminDashboard] Data extracted successfully:",
          data
        );
        console.log("[SchoolAdminDashboard] Cards:", data.cards);

        // Process cards data
        this.processCardsData(data.cards);

        console.log(
          "[SchoolAdminDashboard] State after processCardsData:",
          this.state.cards
        );

        // Process charts data
        this.processChartsData(data.charts);

        // Process tables data
        this.processTablesData(data.tables);
      } else {
        console.log("[SchoolAdminDashboard] Response check FAILED:", {
          response: response,
          hasCards: !!response?.cards,
          hasDataCards: !!response?.data?.cards,
        });
        // Fallback: Fetch individual endpoints if full endpoint fails
        this.log("⚠️ Full endpoint failed, fetching individually...", "warn");
        await this.loadDataIndividually();
      }

      // Update UI
      this.renderCards();
      this.renderCharts();
      this.renderTables();
      this.updateLastRefreshTime();

      const duration = (performance.now() - startTime).toFixed(2);
      this.log(`✓ Dashboard loaded in ${duration}ms`);
    } catch (error) {
      this.log(`❌ Error loading dashboard: ${error.message}`, "error");
      this.state.error = error.message;
      this.showError(error.message);
      // Load placeholder data on error
      this.loadFallbackData();
      this.renderCards();
      this.renderCharts();
      this.renderTables();
    } finally {
      this.state.isLoading = false;
    }
  },

  /**
   * Helper: Extract data from API response
   * api.js handleApiResponse() unwraps successful responses, so response IS the data directly
   * @param {Object} response - The API response (may be unwrapped or wrapped)
   * @returns {Object|null} - The data or null if invalid
   */
  extractData: function (response) {
    if (!response) return null;
    // If response has the expected data structure directly (unwrapped by handleApiResponse)
    if (response && typeof response === "object" && !response.status) {
      return response;
    }
    // If response is wrapped with {status, data}
    if (response.status === "success" && response.data) {
      return response.data;
    }
    // If response has success flag
    if (response.success && response.data) {
      return response.data;
    }
    // Assume response is the data
    return response;
  },

  /**
   * Fallback: Load data from individual endpoints
   */
  loadDataIndividually: async function () {
    const [
      studentsResponse,
      staffResponse,
      attendanceResponse,
      admissionsResponse,
      timetablesResponse,
      announcementsResponse,
      systemResponse,
      pendingResponse,
      classDistResponse,
      attendanceTrendResponse,
    ] = await Promise.allSettled([
      API.dashboard.getSchoolAdminStudents(),
      API.dashboard.getSchoolAdminStaff(),
      API.dashboard.getSchoolAdminAttendance(),
      API.dashboard.getSchoolAdminAdmissions(),
      API.dashboard.getSchoolAdminTimetables(),
      API.dashboard.getSchoolAdminAnnouncements(),
      API.dashboard.getSchoolAdminSystemStatus(),
      API.dashboard.getSchoolAdminPendingItems(),
      API.dashboard.getSchoolAdminClassDistribution(),
      API.dashboard.getSchoolAdminAttendanceTrend(4),
    ]);

    // Build cards data from individual responses
    // Note: api.js handleApiResponse() unwraps successful responses
    // So .value IS the data directly, not wrapped in {status, data}
    const cards = {};

    // Students data
    if (studentsResponse.status === "fulfilled" && studentsResponse.value) {
      const data = this.extractData(studentsResponse.value);
      console.log("[SchoolAdminDashboard] Students data:", data);
      if (data) {
        // Handle both nested (students property) and flat structure
        const studentsData = data.students || data;
        cards.active_students = studentsData;
        if (data.class_distribution) {
          cards.class_distribution = data.class_distribution;
        }
      }
    }

    // Staff data
    if (staffResponse.status === "fulfilled" && staffResponse.value) {
      const data = this.extractData(staffResponse.value);
      console.log("[SchoolAdminDashboard] Staff data:", data);
      if (data) {
        cards.teaching_staff = data.teaching || data;
        cards.staff_activities = data.activities || {};
        cards.staff_leaves = data.leaves || {};
      }
    }

    // Attendance data
    if (attendanceResponse.status === "fulfilled" && attendanceResponse.value) {
      const data = this.extractData(attendanceResponse.value);
      console.log("[SchoolAdminDashboard] Attendance data:", data);
      if (data) {
        cards.daily_attendance = data.today || data;
      }
    }

    // Admissions data
    if (admissionsResponse.status === "fulfilled" && admissionsResponse.value) {
      const data = this.extractData(admissionsResponse.value);
      console.log("[SchoolAdminDashboard] Admissions data:", data);
      if (data) {
        cards.student_admissions = data;
      }
    }

    // Timetables data
    if (timetablesResponse.status === "fulfilled" && timetablesResponse.value) {
      const data = this.extractData(timetablesResponse.value);
      console.log("[SchoolAdminDashboard] Timetables data:", data);
      if (data) {
        cards.class_timetables = data.stats || data;
        this.state.tables.todaySchedule = data.today || [];
      }
    }

    // Announcements data
    if (
      announcementsResponse.status === "fulfilled" &&
      announcementsResponse.value
    ) {
      const data = this.extractData(announcementsResponse.value);
      console.log("[SchoolAdminDashboard] Announcements data:", data);
      if (data) {
        cards.announcements = data;
      }
    }

    // System status data
    if (systemResponse.status === "fulfilled" && systemResponse.value) {
      const data = this.extractData(systemResponse.value);
      console.log("[SchoolAdminDashboard] System status data:", data);
      if (data) {
        cards.system_status = data;
      }
    }

    console.log("[SchoolAdminDashboard] Built cards object:", cards);
    this.processCardsData(cards);

    // Process charts
    const charts = {};
    if (classDistResponse.status === "fulfilled" && classDistResponse.value) {
      const data = this.extractData(classDistResponse.value);
      if (data) {
        charts.class_distribution = data;
      }
    }
    if (
      attendanceTrendResponse.status === "fulfilled" &&
      attendanceTrendResponse.value
    ) {
      const data = this.extractData(attendanceTrendResponse.value);
      if (data) {
        charts.attendance_trend = data;
      }
    }
    this.processChartsData(charts);

    // Process tables
    const tables = {};
    if (pendingResponse.status === "fulfilled" && pendingResponse.value) {
      const data = this.extractData(pendingResponse.value);
      if (data) {
        tables.pending_items = data.items || data || [];
      }
    }
    this.processTablesData(tables);
  },

  /**
   * Load fallback/placeholder data when API fails
   */
  loadFallbackData: function () {
    this.log("⚠️ Loading fallback placeholder data", "warn");

    this.state.cards = {
      activeStudents: { value: 0, classes: 0 },
      teachingStaff: { value: 0, presentPercent: 0 },
      staffActivities: { onLeave: 0, assignments: 0 },
      classTimetables: { value: 0, classesPerWeek: 0 },
      dailyAttendance: { percent: 0, present: 0, absent: 0 },
      announcements: { count: 0, recipients: 0 },
      studentAdmissions: { pending: 0, approved: 0 },
      staffLeaves: { today: 0 },
      classDistribution: { average: 0, max: 0 },
      systemStatus: { status: "Unavailable", uptime: 0 },
    };

    this.state.charts = {
      attendanceTrend: { labels: ["No Data"], data: [0] },
      classDistribution: { labels: ["No Data"], data: [0] },
    };

    this.state.tables = {
      pendingItems: [],
      todaySchedule: [],
      staffDirectory: [],
    };
  },

  fetchAPI: async function (route, action) {
    try {
      const response = await fetch(
        `${this.config.apiBase}?route=${route}&action=${action}`,
        {
          method: "GET",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${localStorage.getItem("token") || ""}`,
          },
        }
      );

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      this.log(`API Error (${route}/${action}): ${error.message}`, "warn");
      return null;
    }
  },

  /**
   * Process cards data from API response
   * Handles both snake_case (from API) and camelCase property names
   * @param {Object} cards - Cards data from API
   */
  processCardsData: function (cards) {
    console.log("[SchoolAdminDashboard] processCardsData called with:", cards);
    if (!cards) {
      console.warn(
        "[SchoolAdminDashboard] processCardsData: cards is null/undefined"
      );
      return;
    }

    // Card 1: Active Students (API key: active_students)
    const activeStudents = cards.activeStudents || cards.active_students;
    console.log(
      "[SchoolAdminDashboard] activeStudents extracted:",
      activeStudents
    );
    if (activeStudents) {
      this.state.cards.activeStudents = {
        value: activeStudents.total_students || activeStudents.value || 0,
        classes: activeStudents.active_classes || activeStudents.classes || 0,
      };
    }

    // Card 2: Teaching Staff (API key: teaching_staff)
    const teachingStaff = cards.teachingStaff || cards.teaching_staff;
    if (teachingStaff) {
      this.state.cards.teachingStaff = {
        value:
          teachingStaff.teaching_staff ||
          teachingStaff.total ||
          teachingStaff.value ||
          0,
        presentPercent:
          teachingStaff.present_percent || teachingStaff.presentPercent || 0,
      };
    }

    // Card 3: Staff Activities (API key: staff_activities)
    const staffActivities = cards.staffActivities || cards.staff_activities;
    if (staffActivities) {
      this.state.cards.staffActivities = {
        onLeave: staffActivities.on_leave || staffActivities.onLeave || 0,
        assignments:
          staffActivities.pending_assignments ||
          staffActivities.assignments ||
          0,
      };
    }

    // Card 4: Class Timetables (API key: class_timetables)
    const classTimetables = cards.classTimetables || cards.class_timetables;
    if (classTimetables) {
      this.state.cards.classTimetables = {
        value: classTimetables.active_timetables || classTimetables.value || 0,
        classesPerWeek:
          classTimetables.classes_per_week ||
          classTimetables.classesPerWeek ||
          0,
      };
    }

    // Card 5: Daily Attendance (API key: daily_attendance)
    const dailyAttendance = cards.dailyAttendance || cards.daily_attendance;
    if (dailyAttendance) {
      this.state.cards.dailyAttendance = {
        percent: dailyAttendance.percentage || dailyAttendance.percent || 0,
        present: dailyAttendance.present || 0,
        absent: dailyAttendance.absent || 0,
      };
    }

    // Card 6: Announcements
    const announcements = cards.announcements;
    if (announcements) {
      this.state.cards.announcements = {
        count: announcements.count || announcements.active || 0,
        recipients:
          announcements.total_recipients || announcements.recipients || 0,
      };
    }

    // Card 7: Student Admissions (API key: student_admissions)
    const studentAdmissions =
      cards.studentAdmissions || cards.student_admissions;
    if (studentAdmissions) {
      this.state.cards.studentAdmissions = {
        pending: studentAdmissions.pending || 0,
        approved: studentAdmissions.approved || 0,
      };
    }

    // Card 8: Staff Leaves (API key: staff_leaves)
    const staffLeaves = cards.staffLeaves || cards.staff_leaves;
    if (staffLeaves) {
      this.state.cards.staffLeaves = {
        today: staffLeaves.today || staffLeaves.on_leave_today || 0,
      };
    }

    // Card 9: Class Distribution (API key: class_distribution)
    const classDistribution =
      cards.classDistribution || cards.class_distribution;
    if (classDistribution) {
      this.state.cards.classDistribution = {
        average:
          classDistribution.average || classDistribution.avg_class_size || 0,
        max: classDistribution.max || classDistribution.max_class_size || 0,
      };
    }

    // Card 10: System Status (API key: system_status)
    const systemStatus = cards.systemStatus || cards.system_status;
    if (systemStatus) {
      const status = systemStatus.status || "Unknown";
      this.state.cards.systemStatus = {
        status:
          status === "healthy" ||
          status === "operational" ||
          status === "Operational"
            ? "Operational"
            : status,
        uptime: systemStatus.uptime || systemStatus.uptime_percent || 0,
      };
    }
  },

  /**
   * Process charts data from API response
   * Handles both snake_case (from API) and camelCase property names
   * @param {Object} charts - Charts data from API
   */
  processChartsData: function (charts) {
    if (!charts) return;

    // Attendance Trend Chart (API key: attendance_trend)
    const attendanceTrend = charts.attendanceTrend || charts.attendance_trend;
    if (attendanceTrend) {
      this.state.charts.attendanceTrend = {
        labels: attendanceTrend.labels || [],
        data: attendanceTrend.data || [],
      };
    }

    // Class Distribution Chart (API key: class_distribution)
    const classDistribution =
      charts.classDistribution || charts.class_distribution;
    if (classDistribution) {
      this.state.charts.classDistribution = {
        labels: classDistribution.labels || [],
        data: classDistribution.data || [],
      };
    }
  },

  /**
   * Process tables data from API response
   * Handles both snake_case (from API) and camelCase property names
   * @param {Object} tables - Tables data from API
   */
  processTablesData: function (tables) {
    if (!tables) return;

    // Pending Items Table (API key: pending_items)
    const pendingItems = tables.pendingItems || tables.pending_items;
    if (pendingItems) {
      this.state.tables.pendingItems = pendingItems.map((item) => ({
        type: item.type || "Unknown",
        description: item.description || "",
        count: item.count || 0,
        priority: item.priority || "Low",
        action: item.action_url || item.action || "",
      }));
    }

    // Today's Schedule Table (API key: today_schedule)
    const todaySchedule = tables.todaySchedule || tables.today_schedule;
    if (todaySchedule) {
      this.state.tables.todaySchedule = todaySchedule.map((item) => ({
        time: item.time || item.start_time || "",
        event: item.event || item.title || item.subject_name || "",
        location: item.location || item.room || "",
        attendees: item.attendees || item.students_count || "",
        status:
          item.status || this.getEventStatus(item.time || item.start_time),
      }));
    }

    // Staff Directory Table (API key: staff_directory)
    const staffDirectory = tables.staffDirectory || tables.staff_directory;
    if (staffDirectory) {
      this.state.tables.staffDirectory = staffDirectory.map((item) => ({
        name:
          item.name ||
          `${item.first_name || ""} ${item.last_name || ""}`.trim(),
        position: item.position || item.designation || "",
        department: item.department || "",
        contact: item.contact || item.email || item.phone || "",
        status: item.status || item.attendance_status || "Unknown",
      }));
    }
  },

  /**
   * Determine event status based on time
   * @param {string} timeStr - Time string (HH:MM)
   * @returns {string} - Status (Completed, In Progress, Upcoming)
   */
  getEventStatus: function (timeStr) {
    if (!timeStr) return "Upcoming";

    const now = new Date();
    const [hours, minutes] = timeStr.split(":").map(Number);
    const eventTime = new Date();
    eventTime.setHours(hours, minutes, 0, 0);

    const diffMinutes = (now - eventTime) / (1000 * 60);

    if (diffMinutes > 60) return "Completed";
    if (diffMinutes > -10 && diffMinutes <= 60) return "In Progress";
    return "Upcoming";
  },

  // =========================================================================
  // RENDERING
  // =========================================================================
  renderCards: function () {
    const cards = this.state.cards;
    console.log("[SchoolAdminDashboard] renderCards - state.cards:", cards);

    // Card 1: Active Students
    console.log(
      "[SchoolAdminDashboard] Rendering activeStudents:",
      cards.activeStudents
    );
    this.updateCard("active-students", {
      value: this.formatNumber(cards.activeStudents.value),
      secondary: `Classes: ${cards.activeStudents.classes}`,
    });

    // Card 2: Teaching Staff
    this.updateCard("teaching-staff", {
      value: this.formatNumber(cards.teachingStaff.value),
      secondary: `Present today: ${cards.teachingStaff.presentPercent}%`,
    });

    // Card 3: Staff Activities
    this.updateCard("staff-activities", {
      value:
        parseInt(cards.staffActivities.onLeave) +
        parseInt(cards.staffActivities.assignments),
      secondary: `On leave: ${cards.staffActivities.onLeave} | Assignments: ${cards.staffActivities.assignments}`,
    });

    // Card 4: Class Timetables
    this.updateCard("class-timetables", {
      value: cards.classTimetables.value,
      secondary: `Classes/week: ${cards.classTimetables.classesPerWeek}`,
    });

    // Card 5: Daily Attendance
    this.updateCard("daily-attendance", {
      value: `${cards.dailyAttendance.percent}%`,
      secondary: `Present: ${this.formatNumber(
        cards.dailyAttendance.present
      )} | Absent: ${this.formatNumber(cards.dailyAttendance.absent)}`,
    });

    // Card 6: Announcements
    this.updateCard("announcements", {
      value: cards.announcements.count,
      secondary: `To: ${this.formatNumber(
        cards.announcements.recipients
      )} recipients`,
    });

    // Card 7: Student Admissions
    this.updateCard("student-admissions", {
      value: cards.studentAdmissions.pending,
      secondary: `Pending: ${cards.studentAdmissions.pending} | Approved: ${cards.studentAdmissions.approved}`,
    });

    // Card 8: Staff Leaves
    this.updateCard("staff-leaves", {
      value: cards.staffLeaves.today,
      secondary: `On leave today: ${cards.staffLeaves.today}`,
    });

    // Card 9: Class Distribution
    this.updateCard("class-distribution", {
      value: `${cards.classDistribution.average} avg`,
      secondary: `Avg: ${cards.classDistribution.average} | Max: ${cards.classDistribution.max}`,
    });

    // Card 10: System Status
    const statusEl = document.getElementById("system-status-value");
    if (statusEl) {
      statusEl.textContent = cards.systemStatus.status;
      statusEl.className =
        cards.systemStatus.status === "Operational"
          ? "mb-0 fw-bold text-success"
          : "mb-0 fw-bold text-warning";
    }
    this.updateCard("system-status", {
      secondary: `Uptime: ${cards.systemStatus.uptime}%`,
    });
  },

  updateCard: function (cardId, data) {
    if (data.value !== undefined) {
      const valueEl = document.getElementById(`${cardId}-value`);
      if (valueEl) valueEl.textContent = data.value;
    }
    if (data.subtitle !== undefined) {
      const subtitleEl = document.getElementById(`${cardId}-subtitle`);
      if (subtitleEl) subtitleEl.textContent = data.subtitle;
    }
    if (data.secondary !== undefined) {
      const secondaryEl = document.getElementById(`${cardId}-secondary`);
      if (secondaryEl) secondaryEl.textContent = data.secondary;
    }
  },

  renderCharts: function () {
    this.renderAttendanceTrendChart();
    this.renderClassDistributionChart();
  },

  renderAttendanceTrendChart: function () {
    const ctx = document.getElementById("attendanceTrendChart");
    if (!ctx) return;

    // Destroy existing chart
    if (this.chartInstances.attendanceTrend) {
      this.chartInstances.attendanceTrend.destroy();
    }

    const data = this.state.charts.attendanceTrend;

    this.chartInstances.attendanceTrend = new Chart(ctx, {
      type: "line",
      data: {
        labels: data.labels,
        datasets: [
          {
            label: "Attendance %",
            data: data.data,
            borderColor: "#008080",
            backgroundColor: "rgba(0, 128, 128, 0.1)",
            tension: 0.4,
            fill: true,
            borderWidth: 3,
            pointRadius: 5,
            pointBackgroundColor: "#008080",
            pointBorderColor: "#fff",
            pointBorderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                return `Attendance: ${context.parsed.y}%`;
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: false,
            min: 70,
            max: 100,
            ticks: {
              callback: function (value) {
                return value + "%";
              },
            },
            grid: {
              color: "rgba(0, 0, 0, 0.05)",
            },
          },
          x: {
            grid: {
              display: false,
            },
          },
        },
      },
    });
  },

  renderClassDistributionChart: function () {
    const ctx = document.getElementById("classDistributionChart");
    if (!ctx) return;

    // Destroy existing chart
    if (this.chartInstances.classDistribution) {
      this.chartInstances.classDistribution.destroy();
    }

    const data = this.state.charts.classDistribution;

    this.chartInstances.classDistribution = new Chart(ctx, {
      type: "bar",
      data: {
        labels: data.labels,
        datasets: [
          {
            label: "Students",
            data: data.data,
            backgroundColor: [
              "#0d6efd",
              "#198754",
              "#0dcaf0",
              "#ffc107",
              "#6f42c1",
              "#fd7e14",
              "#20c997",
              "#d63384",
            ],
            borderRadius: 4,
            borderSkipped: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                return `${context.parsed.y} students`;
              },
            },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 50,
            grid: {
              color: "rgba(0, 0, 0, 0.05)",
            },
          },
          x: {
            grid: {
              display: false,
            },
          },
        },
      },
    });
  },

  renderTables: function () {
    this.renderPendingItemsTable();
    this.renderScheduleTable();
    this.renderStaffDirectoryTable();
  },

  renderPendingItemsTable: function () {
    const tbody = document.getElementById("pending-items-table");
    if (!tbody) return;

    const items = this.state.tables.pendingItems;
    const pendingCount = document.getElementById("pending-count");

    if (pendingCount) {
      const total = items.reduce((sum, item) => sum + item.count, 0);
      pendingCount.textContent = total;
    }

    if (items.length === 0) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <i class="fas fa-check-circle me-2"></i>No pending items
                    </td>
                </tr>
            `;
      return;
    }

    tbody.innerHTML = items
      .map(
        (item) => `
            <tr>
                <td><span class="badge bg-secondary">${this.escapeHtml(
                  item.type
                )}</span></td>
                <td>${this.escapeHtml(item.description)}</td>
                <td><strong>${item.count}</strong></td>
                <td>
                    <span class="badge ${this.getPriorityBadgeClass(
                      item.priority
                    )}">
                        ${item.priority}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="schoolAdminDashboardController.handlePendingItemAction('${
                      item.type
                    }')">
                        <i class="fas fa-arrow-right"></i> View
                    </button>
                </td>
            </tr>
        `
      )
      .join("");
  },

  renderScheduleTable: function () {
    const tbody = document.getElementById("schedule-items-table");
    if (!tbody) return;

    const items = this.state.tables.todaySchedule;

    if (items.length === 0) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-times me-2"></i>No events scheduled for today
                    </td>
                </tr>
            `;
      return;
    }

    tbody.innerHTML = items
      .map(
        (item) => `
            <tr>
                <td><strong>${this.escapeHtml(item.time)}</strong></td>
                <td>${this.escapeHtml(item.event)}</td>
                <td><i class="fas fa-map-marker-alt me-1 text-muted"></i>${this.escapeHtml(
                  item.location
                )}</td>
                <td><i class="fas fa-users me-1 text-muted"></i>${
                  item.attendees
                }</td>
                <td>
                    <span class="badge ${this.getStatusBadgeClass(
                      item.status
                    )}">
                        ${item.status}
                    </span>
                </td>
            </tr>
        `
      )
      .join("");
  },

  renderStaffDirectoryTable: function () {
    const tbody = document.getElementById("staff-directory-table");
    if (!tbody) return;

    const items = this.state.tables.staffDirectory;

    if (items.length === 0) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <i class="fas fa-users-slash me-2"></i>No staff records found
                    </td>
                </tr>
            `;
      return;
    }

    tbody.innerHTML = items
      .map(
        (item) => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle bg-primary bg-opacity-10 text-primary me-2">
                            ${this.getInitials(item.name)}
                        </div>
                        <strong>${this.escapeHtml(item.name)}</strong>
                    </div>
                </td>
                <td>${this.escapeHtml(item.position)}</td>
                <td>${this.escapeHtml(item.department)}</td>
                <td>
                    <a href="tel:${item.contact}" class="text-decoration-none">
                        <i class="fas fa-phone me-1"></i>${item.contact}
                    </a>
                </td>
                <td>
                    <span class="badge ${
                      item.status === "Present"
                        ? "bg-success"
                        : "bg-warning text-dark"
                    }">
                        ${item.status}
                    </span>
                </td>
            </tr>
        `
      )
      .join("");
  },

  // =========================================================================
  // EVENT HANDLERS
  // =========================================================================
  handleChartRangeChange: async function (e) {
    const buttons = e.target.closest(".btn-group").querySelectorAll("button");
    buttons.forEach((btn) => btn.classList.remove("active"));
    e.target.classList.add("active");

    const range = e.target.dataset.range;
    this.log(`Chart range changed to: ${range}`);

    // Map range to weeks
    const weeksMap = { "1w": 1, "2w": 2, "1m": 4, "3m": 12 };
    const weeks = weeksMap[range] || 4;

    try {
      const response = await API.dashboard.getSchoolAdminAttendanceTrend(weeks);
      if (response && (response.success || response.status === "success")) {
        this.state.charts.attendanceTrend = {
          labels: response.data.labels || [],
          data: response.data.data || [],
        };
        this.renderAttendanceTrendChart();
      }
    } catch (error) {
      this.log(`Error updating chart range: ${error.message}`, "error");
    }
  },

  handleClassFilterChange: async function (e) {
    const filter = e.target.value;
    this.log(`Class filter changed to: ${filter}`);

    try {
      const response = await API.dashboard.getSchoolAdminClassDistribution(
        filter
      );
      if (response && (response.success || response.status === "success")) {
        this.state.charts.classDistribution = {
          labels: response.data.labels || [],
          data: response.data.data || [],
        };
        this.renderClassDistributionChart();
      }
    } catch (error) {
      this.log(`Error updating class distribution: ${error.message}`, "error");
    }
  },

  handleStaffSearch: async function (e) {
    const query = e.target.value.toLowerCase().trim();

    // If query is short, just filter client-side
    if (query.length < 2) {
      const rows = document.querySelectorAll("#staff-directory-table tr");
      rows.forEach((row) => {
        row.style.display = "";
      });
      return;
    }

    // For longer queries, use the API
    try {
      const response = await API.dashboard.getSchoolAdminStaffDirectory(query);
      if (response && (response.success || response.status === "success")) {
        this.state.tables.staffDirectory = response.data.staff || [];
        this.processTablesData({
          staffDirectory: this.state.tables.staffDirectory,
        });
        this.renderStaffDirectoryTable();
      }
    } catch (error) {
      // Fallback to client-side filtering
      const rows = document.querySelectorAll("#staff-directory-table tr");
      rows.forEach((row) => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? "" : "none";
      });
    }
  },

  handlePendingItemAction: function (type) {
    const routes = {
      Admission: "manage_admissions",
      "Leave Request": "manage_staff",
      Assignment: "manage_staff",
      Communication: "manage_announcements",
    };

    const route = routes[type] || "dashboard";
    window.location.href = `home.php?route=${route}`;
  },

  // =========================================================================
  // UTILITY FUNCTIONS
  // =========================================================================
  updateLastRefreshTime: function () {
    const el = document.getElementById("lastRefreshTime");
    if (el) {
      const now = new Date();
      el.textContent = now.toLocaleTimeString();
    }
    this.state.lastRefresh = new Date();
  },

  formatNumber: function (num) {
    if (num === "--" || num === undefined || num === null) return "--";
    const n = parseInt(num);
    if (isNaN(n)) return num;
    return new Intl.NumberFormat().format(n);
  },

  escapeHtml: function (text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  },

  getInitials: function (name) {
    if (!name) return "??";
    return name
      .split(" ")
      .map((n) => n[0])
      .join("")
      .toUpperCase()
      .slice(0, 2);
  },

  getPriorityBadgeClass: function (priority) {
    const classes = {
      High: "bg-danger",
      Medium: "bg-warning text-dark",
      Low: "bg-info",
    };
    return classes[priority] || "bg-secondary";
  },

  getStatusBadgeClass: function (status) {
    const classes = {
      Completed: "bg-success",
      "In Progress": "bg-primary",
      Upcoming: "bg-secondary",
      Cancelled: "bg-danger",
    };
    return classes[status] || "bg-secondary";
  },

  showError: function (message) {
    // Show error notification (could be a toast or alert)
    console.error("Dashboard Error:", message);
  },

  exportDashboard: function () {
    try {
      const data = {
        dashboard: "School Administrator Dashboard",
        exportedAt: new Date().toISOString(),
        cards: this.state.cards,
        tables: this.state.tables,
      };

      const blob = new Blob([JSON.stringify(data, null, 2)], {
        type: "application/json",
      });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `school-admin-dashboard-${Date.now()}.json`;
      link.click();
      URL.revokeObjectURL(url);

      this.log("✓ Dashboard exported successfully");
    } catch (error) {
      this.log(`❌ Export failed: ${error.message}`, "error");
    }
  },

  log: function (message, level = "info") {
    if (!this.config.debug) return;

    const prefix = "[SchoolAdminDashboard]";
    switch (level) {
      case "error":
        console.error(prefix, message);
        break;
      case "warn":
        console.warn(prefix, message);
        break;
      default:
        console.log(prefix, message);
    }
  },
};

// Initialize on DOM ready
document.addEventListener("DOMContentLoaded", () => {
  schoolAdminDashboardController.init();
});

// Also support legacy name for backward compatibility
const adminOfficerDashboardController = schoolAdminDashboardController;
