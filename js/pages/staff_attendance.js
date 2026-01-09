/**
 * Staff Attendance Page Controller
 * Enhanced with duty types, off-day patterns, and leave indicators
 * 
 * Features:
 * - Today's staff overview with duty types
 * - Off-day pattern awareness
 * - Leave status indicators
 * - Department and duty type filtering
 * - Mark attendance modal
 */

const StaffAttendanceController = {
  departments: [],
  dutyTypes: [],
  staffData: [],
  todayStaff: [],
  charts: {},
  attendanceMarked: {},

  init: function () {
    console.log("✅ Staff Attendance Controller initialized");
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

    const markDateEl = document.getElementById("markDate");
    if (markDateEl) {
      markDateEl.value = today.toISOString().split("T")[0];
    }

    // Set today's date display
    const todayDateEl = document.getElementById("todayDate");
    if (todayDateEl) {
      todayDateEl.textContent = today.toLocaleDateString("en-GB", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
      });
    }
  },

  async loadInitialData() {
    await Promise.all([this.loadDepartments(), this.loadDutyTypes()]);
    this.loadTodayStaff();
  },

  bindEvents: function () {
    // Generate report button
    document.getElementById("generateBtn").addEventListener("click", () => {
      this.generateReport();
    });

    // Export
    document.getElementById("exportBtn").addEventListener("click", () => {
      this.exportData();
    });

    // Print
    document.getElementById("printBtn").addEventListener("click", () => {
      window.print();
    });

    // Mark staff modal events
    const loadStaffBtn = document.getElementById("loadStaffForMarkingBtn");
    if (loadStaffBtn) {
      loadStaffBtn.addEventListener("click", () => {
        this.loadStaffForMarking();
      });
    }

    const markAllPresentBtn = document.getElementById("markAllPresentBtn");
    if (markAllPresentBtn) {
      markAllPresentBtn.addEventListener("click", () => {
        this.markAllStaff("present");
      });
    }

    const markAllAbsentBtn = document.getElementById("markAllAbsentBtn");
    if (markAllAbsentBtn) {
      markAllAbsentBtn.addEventListener("click", () => {
        this.markAllStaff("absent");
      });
    }

    const submitBtn = document.getElementById("submitStaffAttendanceBtn");
    if (submitBtn) {
      submitBtn.addEventListener("click", () => {
        this.submitStaffAttendance();
      });
    }
  },

  async loadDepartments() {
    try {
      const response = await window.API.apiCall(
        "/api/?route=staff&action=departments",
        "GET"
      );
      if (response && response.success) {
        this.departments = response.data || [];
        this.renderDepartmentDropdowns();
      }
    } catch (error) {
      console.error("Error loading departments:", error);
    }
  },

  async loadDutyTypes() {
    try {
      // Load duty types from staff duty roster
      const response = await window.API.apiCall(
        "/api/?route=attendance&action=duty-types",
        "GET"
      );
      if (response && response.success) {
        this.dutyTypes = response.data || [];
        this.renderDutyTypeDropdown();
      }
    } catch (error) {
      console.warn("Error loading duty types:", error);
      // Use default duty types if API not available
      this.dutyTypes = [
        { id: 1, duty_code: "TEACHING", duty_name: "Teaching" },
        { id: 2, duty_code: "BOARDING", duty_name: "Boarding Duty" },
        { id: 3, duty_code: "OFFICE", duty_name: "Office Duty" },
        { id: 4, duty_code: "OFF", duty_name: "Off Day" },
      ];
      this.renderDutyTypeDropdown();
    }
  },

  renderDepartmentDropdowns: function () {
    const selects = [
      document.getElementById("department"),
      document.getElementById("markDepartment"),
    ];

    selects.forEach((select) => {
      if (!select) return;
      const currentValue = select.value;
      select.innerHTML = '<option value="">All Departments</option>';
      this.departments.forEach((dept) => {
        const option = document.createElement("option");
        option.value = dept.id;
        option.textContent = dept.name;
        select.appendChild(option);
      });
      select.value = currentValue;
    });
  },

  renderDutyTypeDropdown: function () {
    const select = document.getElementById("dutyType");
    if (!select) return;

    select.innerHTML = '<option value="">All Types</option>';
    this.dutyTypes.forEach((duty) => {
      const option = document.createElement("option");
      option.value = duty.id;
      option.textContent = duty.duty_name;
      select.appendChild(option);
    });
  },

  async loadTodayStaff() {
    const today = new Date().toISOString().split("T")[0];

    try {
      const response = await window.API.apiCall(
        `/api/?route=attendance&action=staff-today&date=${today}`,
        "GET"
      );
      if (response && response.success) {
        this.todayStaff = response.data || [];
        this.renderTodayStaffGrid();
      }
    } catch (error) {
      console.error("Error loading today's staff:", error);
    }
  },

  renderTodayStaffGrid: function () {
    const container = document.getElementById("todayStaffGrid");
    if (!container) return;

    container.innerHTML = "";

    if (!this.todayStaff || this.todayStaff.length === 0) {
      container.innerHTML =
        '<div class="col-12 text-center text-muted py-4">No staff data available for today</div>';
      return;
    }

    // Group by status
    const grouped = {
      present: [],
      absent: [],
      late: [],
      on_leave: [],
      off_day: [],
      not_marked: [],
    };

    this.todayStaff.forEach((staff) => {
      const status = staff.status || "not_marked";
      if (grouped[status]) {
        grouped[status].push(staff);
      } else {
        grouped["not_marked"].push(staff);
      }
    });

    // Create status cards
    const statusConfig = {
      present: { label: "Present", color: "success", icon: "check-circle" },
      absent: { label: "Absent", color: "danger", icon: "x-circle" },
      late: { label: "Late", color: "warning", icon: "clock" },
      on_leave: { label: "On Leave", color: "info", icon: "calendar-x" },
      off_day: { label: "Off Day", color: "secondary", icon: "house" },
      not_marked: {
        label: "Not Marked",
        color: "light",
        icon: "question-circle",
      },
    };

    Object.entries(statusConfig).forEach(([status, config]) => {
      const staff = grouped[status] || [];
      if (staff.length === 0) return;

      const col = document.createElement("div");
      col.className = "col-md-4 col-lg-2 mb-3";
      col.innerHTML = `
                <div class="card staff-status-card status-${status.replace(
                  "_",
                  "-"
                )}">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-${config.color}">${
        config.label
      }</span>
                            <strong class="text-${config.color}">${
        staff.length
      }</strong>
                        </div>
                        <div class="small text-muted" style="max-height: 80px; overflow-y: auto;">
                            ${staff
                              .slice(0, 5)
                              .map(
                                (s) =>
                                  s.first_name +
                                  " " +
                                  (s.last_name || "").charAt(0) +
                                  "."
                              )
                              .join("<br>")}
                            ${
                              staff.length > 5
                                ? `<br><em>+${staff.length - 5} more</em>`
                                : ""
                            }
                        </div>
                    </div>
                </div>
            `;
      container.appendChild(col);
    });
  },

  async generateReport() {
    const params = {
      date_from: document.getElementById("dateFrom").value,
      date_to: document.getElementById("dateTo").value,
      department_id: document.getElementById("department").value,
      duty_type_id: document.getElementById("dutyType").value,
      status: document.getElementById("statusFilter").value,
    };

    try {
      const response = await window.API.apiCall(
        `/api/?route=attendance&action=staff-report&${new URLSearchParams(
          params
        )}`,
        "GET"
      );

      if (response && response.success) {
        this.staffData = response.data || [];
        this.renderAttendanceTable();
        this.updateSummaryCards();
        this.renderCharts();
        this.renderDailyBreakdown();
      } else {
        alert("Failed to generate report");
      }
    } catch (error) {
      console.error("Error generating report:", error);
      alert("Error generating report");
    }
  },

  renderAttendanceTable: function () {
    const tbody = document.getElementById("attendanceTableBody");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (!this.staffData || this.staffData.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="10" class="text-center py-4 text-muted">No data found for selected criteria</td></tr>';
      return;
    }

    this.staffData.forEach((staff) => {
      const workDays =
        (staff.present || 0) + (staff.absent || 0) + (staff.late || 0);
      const percentage =
        workDays > 0
          ? Math.round(
              (((staff.present || 0) + (staff.late || 0)) / workDays) * 100
            )
          : 0;
      const percentageClass =
        percentage >= 90
          ? "text-success"
          : percentage >= 75
          ? "text-warning"
          : "text-danger";

      const tr = document.createElement("tr");
      tr.innerHTML = `
                <td>
                    <strong>${staff.first_name} ${staff.last_name}</strong>
                    <br><small class="text-muted">${
                      staff.staff_no || ""
                    }</small>
                </td>
                <td>${staff.department_name || "N/A"}</td>
                <td><span class="badge bg-secondary">${
                  staff.duty_type || "General"
                }</span></td>
                <td><span class="text-success">${staff.present || 0}</span></td>
                <td><span class="text-danger">${staff.absent || 0}</span></td>
                <td><span class="text-warning">${staff.late || 0}</span></td>
                <td><span class="text-info">${staff.on_leave || 0}</span></td>
                <td><span class="text-secondary">${
                  staff.off_days || 0
                }</span></td>
                <td><strong class="${percentageClass}">${percentage}%</strong></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="StaffAttendanceController.viewDetails(${
                      staff.staff_id
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
      totalLeave = 0,
      totalOff = 0,
      totalDays = 0;

    this.staffData.forEach((staff) => {
      totalPresent += staff.present || 0;
      totalAbsent += staff.absent || 0;
      totalLate += staff.late || 0;
      totalLeave += staff.on_leave || 0;
      totalOff += staff.off_days || 0;
    });

    totalDays = totalPresent + totalAbsent + totalLate;
    const avgAttendance =
      totalDays > 0
        ? Math.round(((totalPresent + totalLate) / totalDays) * 100)
        : 0;

    document.getElementById("avgAttendance").textContent = `${avgAttendance}%`;
    document.getElementById("presentDays").textContent = totalPresent;
    document.getElementById("absentDays").textContent = totalAbsent;
    document.getElementById("lateDays").textContent = totalLate;
    document.getElementById("leaveDays").textContent = totalLeave;
    document.getElementById("offDays").textContent = totalOff;
  },

  renderCharts: function () {
    this.renderTrendChart();
    this.renderPieChart();
  },

  renderTrendChart: function () {
    const ctx = document.getElementById("attendanceTrendChart");
    if (!ctx) return;

    if (this.charts.trend) {
      this.charts.trend.destroy();
    }

    // Generate week labels
    const labels = ["Week 1", "Week 2", "Week 3", "Week 4"];
    const presentData = [92, 88, 95, 90];
    const absentData = [5, 8, 3, 6];

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
        ],
      },
      options: {
        responsive: true,
        plugins: { legend: { position: "top" } },
        scales: { y: { beginAtZero: true, max: 100 } },
      },
    });
  },

  renderPieChart: function () {
    const ctx = document.getElementById("statusPieChart");
    if (!ctx) return;

    if (this.charts.pie) {
      this.charts.pie.destroy();
    }

    let present = 0,
      absent = 0,
      late = 0,
      leave = 0,
      off = 0;
    this.staffData.forEach((s) => {
      present += s.present || 0;
      absent += s.absent || 0;
      late += s.late || 0;
      leave += s.on_leave || 0;
      off += s.off_days || 0;
    });

    this.charts.pie = new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: ["Present", "Absent", "Late", "Leave", "Off Day"],
        datasets: [
          {
            data: [present, absent, late, leave, off],
            backgroundColor: [
              "#28a745",
              "#dc3545",
              "#ffc107",
              "#17a2b8",
              "#6c757d",
            ],
          },
        ],
      },
      options: {
        responsive: true,
        plugins: { legend: { position: "bottom" } },
      },
    });
  },

  renderDailyBreakdown: function () {
    // This would require date-range data - simplified version
    const headersRow = document.getElementById("dailyHeaders");
    const tbody = document.getElementById("dailyBody");

    if (!headersRow || !tbody) return;

    // For now, just show a message
    tbody.innerHTML =
      '<tr><td colspan="10" class="text-center py-4 text-muted">Daily breakdown requires date-range data</td></tr>';
  },

  async loadStaffForMarking() {
    const date = document.getElementById("markDate").value;
    const departmentId = document.getElementById("markDepartment").value;

    try {
      let url = `/api/?route=attendance&action=staff-today&date=${date}`;
      if (departmentId) url += `&department_id=${departmentId}`;

      const response = await window.API.apiCall(url, "GET");
      if (response && response.success) {
        this.renderMarkStaffTable(response.data || []);
      }
    } catch (error) {
      console.error("Error loading staff for marking:", error);
    }
  },

  renderMarkStaffTable: function (staff) {
    const tbody = document.getElementById("markStaffTableBody");
    if (!tbody) return;

    tbody.innerHTML = "";
    this.attendanceMarked = {};

    if (staff.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="5" class="text-center py-4 text-muted">No staff found</td></tr>';
      return;
    }

    staff.forEach((s) => {
      const existingStatus = s.status || "";
      this.attendanceMarked[s.staff_id] = existingStatus;

      const tr = document.createElement("tr");
      tr.dataset.staffId = s.staff_id;
      tr.innerHTML = `
                <td>
                    <strong>${s.first_name} ${s.last_name}</strong>
                    <br><small class="text-muted">${s.staff_no || ""}</small>
                </td>
                <td>${s.department_name || "N/A"}</td>
                <td><span class="badge bg-secondary">${
                  s.duty_type || "General"
                }</span></td>
                <td>
                    ${
                      s.is_off_day
                        ? '<span class="badge bg-secondary">Off Day</span>'
                        : ""
                    }
                    ${
                      s.is_on_leave
                        ? '<span class="badge bg-info">On Leave</span>'
                        : ""
                    }
                    ${
                      !s.is_off_day && !s.is_on_leave
                        ? '<span class="text-muted">Working</span>'
                        : ""
                    }
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="staff_${
                          s.staff_id
                        }" 
                               id="present_${s.staff_id}" value="present" ${
        existingStatus === "present" ? "checked" : ""
      }>
                        <label class="btn btn-outline-success" for="present_${
                          s.staff_id
                        }">P</label>

                        <input type="radio" class="btn-check" name="staff_${
                          s.staff_id
                        }" 
                               id="absent_${s.staff_id}" value="absent" ${
        existingStatus === "absent" ? "checked" : ""
      }>
                        <label class="btn btn-outline-danger" for="absent_${
                          s.staff_id
                        }">A</label>

                        <input type="radio" class="btn-check" name="staff_${
                          s.staff_id
                        }" 
                               id="late_${s.staff_id}" value="late" ${
        existingStatus === "late" ? "checked" : ""
      }>
                        <label class="btn btn-outline-warning" for="late_${
                          s.staff_id
                        }">L</label>
                    </div>
                </td>
            `;
      tbody.appendChild(tr);

      // Bind events
      tr.querySelectorAll('input[type="radio"]').forEach((radio) => {
        radio.addEventListener("change", (e) => {
          this.attendanceMarked[s.staff_id] = e.target.value;
        });
      });
    });
  },

  markAllStaff: function (status) {
    const tbody = document.getElementById("markStaffTableBody");
    if (!tbody) return;

    tbody.querySelectorAll("tr[data-staff-id]").forEach((row) => {
      const staffId = row.dataset.staffId;
      const radio = document.getElementById(`${status}_${staffId}`);
      if (radio) {
        radio.checked = true;
        this.attendanceMarked[staffId] = status;
      }
    });
  },

  async submitStaffAttendance() {
    const date = document.getElementById("markDate").value;

    const attendance = Object.entries(this.attendanceMarked)
      .filter(([_, status]) => status)
      .map(([staffId, status]) => ({
        staff_id: parseInt(staffId),
        status: status,
      }));

    if (attendance.length === 0) {
      alert("Please mark attendance for at least one staff member");
      return;
    }

    try {
      const response = await window.API.apiCall(
        "/api/?route=attendance&action=mark-staff",
        "POST",
        {
          date: date,
          attendance: attendance,
        }
      );

      if (response && response.success) {
        alert("Staff attendance submitted successfully!");
        bootstrap.Modal.getInstance(
          document.getElementById("markStaffModal")
        )?.hide();
        this.loadTodayStaff();
      } else {
        alert(
          "Failed to submit attendance: " +
            (response?.message || "Unknown error")
        );
      }
    } catch (error) {
      console.error("Error submitting staff attendance:", error);
      alert("Error submitting attendance");
    }
  },

  viewDetails: function (staffId) {
    console.log("View details for staff:", staffId);
    // Would implement modal with detailed attendance history
  },

  exportData: function () {
    if (!this.staffData || this.staffData.length === 0) {
      alert("No data to export");
      return;
    }

    let csv =
      "Staff Name,Staff No,Department,Duty Type,Present,Absent,Late,Leave,Off Days,Attendance %\n";
    this.staffData.forEach((s) => {
      const workDays = (s.present || 0) + (s.absent || 0) + (s.late || 0);
      const pct =
        workDays > 0
          ? Math.round((((s.present || 0) + (s.late || 0)) / workDays) * 100)
          : 0;
      csv += `"${s.first_name} ${s.last_name}",${s.staff_no || ""},${
        s.department_name || ""
      },${s.duty_type || ""},${s.present || 0},${s.absent || 0},${
        s.late || 0
      },${s.on_leave || 0},${s.off_days || 0},${pct}%\n`;
    });

    const blob = new Blob([csv], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `staff_attendance_report_${
      new Date().toISOString().split("T")[0]
    }.csv`;
    a.click();
  },
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => StaffAttendanceController.init());
