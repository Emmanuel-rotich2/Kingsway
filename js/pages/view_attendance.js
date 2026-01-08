/**
 * View Attendance Page Controller
 * Enhanced with session-based filtering, boarding support, and permission indicators
 * 
 * Features:
 * - Academic attendance (class-based)
 * - Boarding attendance (dormitory-based)
 * - Session filtering
 * - Permission status display
 * - Trend analytics
 */

const ViewAttendanceController = {
  classes: [],
  dormitories: [],
  sessions: [],
  attendanceData: [],
  permissionsData: [],
  currentType: "academic",
  charts: {},

  init: function () {
    console.log("✅ View Attendance Controller initialized");
    this.setDefaultDates();
    this.loadInitialData();
    this.bindEvents();
  },

  setDefaultDates: function () {
    const today = new Date();
    const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

    document.getElementById("dateFrom").value = firstOfMonth
      .toISOString()
      .split("T")[0];
    document.getElementById("dateTo").value = today.toISOString().split("T")[0];
    document.getElementById("dailyDate").value = today
      .toISOString()
      .split("T")[0];

    const boardingDateEl = document.getElementById("boardingDate");
    if (boardingDateEl) {
      boardingDateEl.value = today.toISOString().split("T")[0];
    }
  },

  async loadInitialData() {
    await Promise.all([
      this.loadClasses(),
      this.loadDormitories(),
      this.loadSessions(),
    ]);
    this.loadActivePermissions();
  },

  bindEvents: function () {
    // Attendance type toggle
    document
      .getElementById("attendanceType")
      .addEventListener("change", (e) => {
        this.currentType = e.target.value;
        this.toggleAttendanceType();
      });

    // Load attendance button
    document
      .getElementById("loadAttendanceBtn")
      .addEventListener("click", () => {
        this.loadAttendance();
      });

    // Load daily register
    document.getElementById("loadDailyBtn").addEventListener("click", () => {
      this.loadDailyRegister();
    });

    // Load boarding summary
    const loadBoardingBtn = document.getElementById("loadBoardingBtn");
    if (loadBoardingBtn) {
      loadBoardingBtn.addEventListener("click", () => {
        this.loadBoardingSummary();
      });
    }

    // Refresh permissions
    const refreshPermBtn = document.getElementById("refreshPermissionsBtn");
    if (refreshPermBtn) {
      refreshPermBtn.addEventListener("click", () => {
        this.loadActivePermissions();
      });
    }

    // Export
    document.getElementById("exportBtn").addEventListener("click", () => {
      this.exportData();
    });

    // Print
    document.getElementById("printBtn").addEventListener("click", () => {
      window.print();
    });
  },

  toggleAttendanceType: function () {
    const classWrapper = document.getElementById("classSelectWrapper");
    const dormWrapper = document.getElementById("dormitorySelectWrapper");
    const boardingTab = document.getElementById("boardingTabItem");

    if (this.currentType === "academic") {
      classWrapper.style.display = "block";
      dormWrapper.style.display = "none";
      boardingTab.style.display = "none";
      this.loadAcademicSessions();
    } else {
      classWrapper.style.display = "none";
      dormWrapper.style.display = "block";
      boardingTab.style.display = "block";
      this.loadBoardingSessions();
    }
  },

  async loadClasses() {
    try {
      const response = await window.API.apiCall(
        "/api/?route=attendance&action=classes",
        "GET"
      );
      if (response && response.success) {
        this.classes = response.data || [];
        this.renderClassDropdown();
      }
    } catch (error) {
      console.error("Error loading classes:", error);
    }
  },

  async loadDormitories() {
    try {
      const response = await window.API.apiCall(
        "/api/?route=attendance&action=dormitories",
        "GET"
      );
      if (response && response.success) {
        this.dormitories = response.data || [];
        this.renderDormitoryDropdown();
      }
    } catch (error) {
      console.error("Error loading dormitories:", error);
    }
  },

  async loadSessions() {
    try {
      const response = await window.API.apiCall(
        "/api/?route=attendance&action=sessions",
        "GET"
      );
      if (response && response.success) {
        this.sessions = response.data || [];
        this.loadAcademicSessions(); // Default to academic
      }
    } catch (error) {
      console.error("Error loading sessions:", error);
    }
  },

  loadAcademicSessions: function () {
    const academicCodes = [
      "MORNING_CLASS",
      "AFTERNOON_CLASS",
      "SATURDAY_CLASS",
    ];
    const filtered = this.sessions.filter((s) =>
      academicCodes.includes(s.session_code)
    );
    this.renderSessionDropdown(filtered);
    this.renderDailySessionDropdown(filtered);
  },

  loadBoardingSessions: function () {
    const boardingCodes = [
      "MORNING_ROLL_CALL",
      "NIGHT_ROLL_CALL",
      "WEEKEND_ROLL_CALL",
      "MORNING_PREP",
      "EVENING_PREP",
    ];
    const filtered = this.sessions.filter((s) =>
      boardingCodes.includes(s.session_code)
    );
    this.renderSessionDropdown(filtered);
    this.renderDailySessionDropdown(filtered);
  },

  renderClassDropdown: function () {
    const select = document.getElementById("classSelect");
    select.innerHTML = '<option value="">All Classes</option>';
    this.classes.forEach((cls) => {
      const option = document.createElement("option");
      option.value = cls.stream_id;
      option.textContent = `${cls.name} (${cls.student_count})`;
      select.appendChild(option);
    });
  },

  renderDormitoryDropdown: function () {
    const select = document.getElementById("dormitorySelect");
    if (!select) return;
    select.innerHTML = '<option value="">All Dormitories</option>';
    this.dormitories.forEach((dorm) => {
      const option = document.createElement("option");
      option.value = dorm.id;
      option.textContent = `${dorm.name} (${dorm.student_count || 0})`;
      select.appendChild(option);
    });
  },

  renderSessionDropdown: function (sessions) {
    const select = document.getElementById("sessionSelect");
    select.innerHTML = '<option value="">All Sessions</option>';
    sessions.forEach((session) => {
      const option = document.createElement("option");
      option.value = session.id;
      option.textContent = session.session_name;
      select.appendChild(option);
    });
  },

  renderDailySessionDropdown: function (sessions) {
    const select = document.getElementById("dailySessionSelect");
    if (!select) return;
    select.innerHTML = '<option value="">All Sessions</option>';
    sessions.forEach((session) => {
      const option = document.createElement("option");
      option.value = session.id;
      option.textContent = session.session_name;
      select.appendChild(option);
    });
  },

  async loadAttendance() {
    const params = this.getFilterParams();

    try {
      let response;
      if (this.currentType === "academic") {
        response = await window.API.apiCall(
          `/api/?route=attendance&action=summary&${new URLSearchParams(
            params
          )}`,
          "GET"
        );
      } else {
        response = await window.API.apiCall(
          `/api/?route=attendance&action=boarding-summary&${new URLSearchParams(
            params
          )}`,
          "GET"
        );
      }

      if (response && response.success) {
        this.attendanceData = response.data || [];
        this.renderSummaryTable();
        this.updateSummaryCards();
        this.renderTrendChart();
      } else {
        alert("Failed to load attendance data");
      }
    } catch (error) {
      console.error("Error loading attendance:", error);
      alert("Error loading attendance data");
    }
  },

  getFilterParams: function () {
    const params = {
      date_from: document.getElementById("dateFrom").value,
      date_to: document.getElementById("dateTo").value,
      status: document.getElementById("statusFilter").value,
      session_id: document.getElementById("sessionSelect").value,
    };

    if (this.currentType === "academic") {
      params.stream_id = document.getElementById("classSelect").value;
    } else {
      params.dormitory_id = document.getElementById("dormitorySelect").value;
    }

    return params;
  },

  renderSummaryTable: function () {
    const tbody = document.querySelector("#summaryTable tbody");
    tbody.innerHTML = "";

    if (!this.attendanceData || this.attendanceData.length === 0) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center py-4 text-muted">
                        No attendance data found for the selected criteria
                    </td>
                </tr>
            `;
      return;
    }

    this.attendanceData.forEach((student, index) => {
      const percentage =
        student.total_days > 0
          ? Math.round((student.present / student.total_days) * 100)
          : 0;
      const percentageClass =
        percentage >= 80
          ? "text-success"
          : percentage >= 60
          ? "text-warning"
          : "text-danger";

      const tr = document.createElement("tr");
      tr.innerHTML = `
                <td><code>${student.admission_no || "N/A"}</code></td>
                <td><strong>${student.first_name} ${
        student.last_name
      }</strong></td>
                <td>
                    <span class="badge ${this.getTypeBadgeClass(
                      student.student_type
                    )}">
                        ${this.getTypeShortName(student.student_type)}
                    </span>
                </td>
                <td>${student.total_days || 0}</td>
                <td><span class="text-success">${
                  student.present || 0
                }</span></td>
                <td><span class="text-danger">${student.absent || 0}</span></td>
                <td><span class="text-warning">${student.late || 0}</span></td>
                <td><span class="text-info">${
                  student.permission || 0
                }</span></td>
                <td><strong class="${percentageClass}">${percentage}%</strong></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="ViewAttendanceController.viewDetails(${
                      student.student_id || student.id
                    })">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            `;
      tbody.appendChild(tr);
    });
  },

  updateSummaryCards: function () {
    let totalPresent = 0,
      totalAbsent = 0,
      totalLate = 0,
      totalPermission = 0,
      totalDays = 0;

    this.attendanceData.forEach((student) => {
      totalPresent += student.present || 0;
      totalAbsent += student.absent || 0;
      totalLate += student.late || 0;
      totalPermission += student.permission || 0;
      totalDays += student.total_days || 0;
    });

    const avgAttendance =
      totalDays > 0 ? Math.round((totalPresent / totalDays) * 100) : 0;

    document.getElementById("avgAttendance").textContent = `${avgAttendance}%`;
    document.getElementById("presentCount").textContent = totalPresent;
    document.getElementById("absentCount").textContent = totalAbsent;
    document.getElementById("lateCount").textContent = totalLate;
    document.getElementById("permissionCount").textContent = totalPermission;
  },

  async loadDailyRegister() {
    const date = document.getElementById("dailyDate").value;
    const sessionId =
      document.getElementById("dailySessionSelect")?.value || "";
    const classId =
      this.currentType === "academic"
        ? document.getElementById("classSelect").value
        : "";
    const dormId =
      this.currentType === "boarding"
        ? document.getElementById("dormitorySelect").value
        : "";

    try {
      let url = `/api/?route=attendance&action=daily&date=${date}`;
      if (sessionId) url += `&session_id=${sessionId}`;
      if (classId) url += `&stream_id=${classId}`;
      if (dormId) url += `&dormitory_id=${dormId}`;

      const response = await window.API.apiCall(url, "GET");
      if (response && response.success) {
        this.renderDailyTable(response.data || []);
      }
    } catch (error) {
      console.error("Error loading daily register:", error);
    }
  },

  renderDailyTable: function (data) {
    const tbody = document.querySelector("#dailyTable tbody");
    tbody.innerHTML = "";

    if (!data || data.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center py-4 text-muted">No records found</td></tr>';
      return;
    }

    data.forEach((record) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
                <td><code>${record.admission_no || "N/A"}</code></td>
                <td>${record.first_name} ${record.last_name}</td>
                <td>
                    <span class="badge ${this.getTypeBadgeClass(
                      record.student_type
                    )}">
                        ${this.getTypeShortName(record.student_type)}
                    </span>
                </td>
                <td>${record.session_name || "N/A"}</td>
                <td><span class="status-badge status-${record.status}">${
        record.status
      }</span></td>
                <td>${record.marked_at || "-"}</td>
                <td>${record.notes || "-"}</td>
            `;
      tbody.appendChild(tr);
    });
  },

  async loadBoardingSummary() {
    const date =
      document.getElementById("boardingDate")?.value ||
      new Date().toISOString().split("T")[0];

    try {
      const response = await window.API.apiCall(
        `/api/?route=attendance&action=boarding-summary&date=${date}`,
        "GET"
      );
      if (response && response.success) {
        this.renderBoardingSummaryCards(response.data || []);
      }
    } catch (error) {
      console.error("Error loading boarding summary:", error);
    }
  },

  renderBoardingSummaryCards: function (data) {
    const container = document.getElementById("boardingSummaryCards");
    if (!container) return;

    container.innerHTML = "";

    if (!data || data.length === 0) {
      container.innerHTML =
        '<div class="col-12 text-center py-4 text-muted">No boarding data found</div>';
      return;
    }

    const colors = [
      "primary",
      "success",
      "info",
      "warning",
      "danger",
      "secondary",
    ];

    data.forEach((dorm, index) => {
      const color = colors[index % colors.length];
      const col = document.createElement("div");
      col.className = "col-md-4 mb-3";
      col.innerHTML = `
                <div class="card dormitory-card" style="border-left-color: var(--bs-${color});">
                    <div class="card-header bg-light">
                        <strong><i class="bi bi-house-door me-2"></i>${
                          dorm.dormitory_name
                        }</strong>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="text-muted small">Total</div>
                                <strong>${dorm.total_students || 0}</strong>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small">Present</div>
                                <strong class="text-success">${
                                  dorm.present_count || 0
                                }</strong>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small">Absent</div>
                                <strong class="text-danger">${
                                  dorm.absent_count || 0
                                }</strong>
                            </div>
                            <div class="col-3">
                                <div class="text-muted small">Perm</div>
                                <strong class="text-warning">${
                                  dorm.permission_count || 0
                                }</strong>
                            </div>
                        </div>
                    </div>
                </div>
            `;
      container.appendChild(col);
    });
  },

  async loadActivePermissions() {
    try {
      const response = await window.API.apiCall(
        "/api/?route=attendance&action=permissions&status=approved&active=true",
        "GET"
      );
      if (response && response.success) {
        this.permissionsData = response.data || [];
        this.renderPermissionsTable();
      }
    } catch (error) {
      console.error("Error loading permissions:", error);
    }
  },

  renderPermissionsTable: function () {
    const tbody = document.getElementById("permissionsTableBody");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (!this.permissionsData || this.permissionsData.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center py-4 text-muted">No active permissions found</td></tr>';
      return;
    }

    this.permissionsData.forEach((perm) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
                <td><strong>${perm.first_name} ${
        perm.last_name
      }</strong><br><small class="text-muted">${perm.admission_no}</small></td>
                <td>${perm.class_name || "N/A"}</td>
                <td><span class="badge bg-warning text-dark">${
                  perm.type_name
                }</span></td>
                <td>${perm.start_date}</td>
                <td>${perm.end_date}</td>
                <td>${perm.reason || "-"}</td>
                <td>${perm.approved_by_name || "-"}</td>
                <td><span class="badge bg-success">${perm.status}</span></td>
            `;
      tbody.appendChild(tr);
    });
  },

  renderTrendChart: function () {
    const ctx = document.getElementById("trendChart");
    if (!ctx) return;

    // Destroy existing chart if any
    if (this.charts.trend) {
      this.charts.trend.destroy();
    }

    // Simple trend data - would need date-based grouping for actual implementation
    const labels = ["Week 1", "Week 2", "Week 3", "Week 4"];
    const presentData = [85, 88, 82, 90];
    const absentData = [10, 8, 12, 7];
    const lateData = [5, 4, 6, 3];

    this.charts.trend = new Chart(ctx, {
      type: "line",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Present %",
            data: presentData,
            borderColor: "#28a745",
            backgroundColor: "rgba(40, 167, 69, 0.1)",
            fill: true,
            tension: 0.3,
          },
          {
            label: "Absent %",
            data: absentData,
            borderColor: "#dc3545",
            backgroundColor: "rgba(220, 53, 69, 0.1)",
            fill: true,
            tension: 0.3,
          },
          {
            label: "Late %",
            data: lateData,
            borderColor: "#ffc107",
            backgroundColor: "rgba(255, 193, 7, 0.1)",
            fill: true,
            tension: 0.3,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: "top" },
        },
        scales: {
          y: { beginAtZero: true, max: 100 },
        },
      },
    });

    // Status pie chart
    this.renderStatusPieChart();
  },

  renderStatusPieChart: function () {
    const ctx = document.getElementById("statusPieChart");
    if (!ctx) return;

    if (this.charts.pie) {
      this.charts.pie.destroy();
    }

    let present = 0,
      absent = 0,
      late = 0,
      permission = 0;
    this.attendanceData.forEach((s) => {
      present += s.present || 0;
      absent += s.absent || 0;
      late += s.late || 0;
      permission += s.permission || 0;
    });

    this.charts.pie = new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: ["Present", "Absent", "Late", "Permission"],
        datasets: [
          {
            data: [present, absent, late, permission],
            backgroundColor: ["#28a745", "#dc3545", "#ffc107", "#17a2b8"],
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: "bottom" },
        },
      },
    });
  },

  viewDetails: function (studentId) {
    // Open modal with student details
    console.log("View details for student:", studentId);
    // Would implement modal population here
    const modal = new bootstrap.Modal(document.getElementById("detailsModal"));
    modal.show();
  },

  exportData: function () {
    // Simple CSV export
    if (!this.attendanceData || this.attendanceData.length === 0) {
      alert("No data to export");
      return;
    }

    let csv =
      "Admission No,Student Name,Type,Total Days,Present,Absent,Late,Permission,Attendance %\n";
    this.attendanceData.forEach((s) => {
      const pct =
        s.total_days > 0 ? Math.round((s.present / s.total_days) * 100) : 0;
      csv += `${s.admission_no},"${s.first_name} ${s.last_name}",${
        s.student_type || "Day"
      },${s.total_days || 0},${s.present || 0},${s.absent || 0},${
        s.late || 0
      },${s.permission || 0},${pct}%\n`;
    });

    const blob = new Blob([csv], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `attendance_report_${
      new Date().toISOString().split("T")[0]
    }.csv`;
    a.click();
  },

  getTypeBadgeClass: function (type) {
    switch (type) {
      case "Full Boarder":
        return "bg-primary";
      case "Weekly Boarder":
        return "bg-info";
      default:
        return "bg-secondary";
    }
  },

  getTypeShortName: function (type) {
    switch (type) {
      case "Full Boarder":
        return "FB";
      case "Weekly Boarder":
        return "WB";
      case "Day":
        return "Day";
      default:
        return type || "Day";
    }
  },
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => ViewAttendanceController.init());
