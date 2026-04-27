/**
 * Shared attendance viewer.
 * Uses the scoped attendance endpoints so the same page can serve admins,
 * class teachers, boarding staff, and other authorized roles safely.
 */

const viewAttendanceController = {
  classes: [],
  sessions: [],
  dormitories: [],
  academicData: null,
  dailyData: [],
  boardingData: [],
  permissionsData: [],
  canAccessBoarding: true,
  charts: {
    trend: null,
    status: null,
  },

  init: async function () {
    this.setDefaultDates();
    this.bindEvents();

    await Promise.all([
      this.configureSharedActions(),
      this.loadClasses(),
      this.loadSessions(),
      this.loadDormitories(),
    ]);

    this.handleAttendanceTypeChange();
    await Promise.all([this.loadAttendance(), this.loadPermissions()]);
  },

  setDefaultDates: function () {
    const today = new Date();
    const todayString = this.toDateInputValue(today);
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    const monthStartString = this.toDateInputValue(monthStart);

    const dateFrom = document.getElementById("dateFrom");
    const dateTo = document.getElementById("dateTo");
    const dailyDate = document.getElementById("dailyDate");
    const boardingDate = document.getElementById("boardingDate");

    if (dateFrom) {
      dateFrom.value = monthStartString;
    }
    if (dateTo) {
      dateTo.value = todayString;
    }
    if (dailyDate) {
      dailyDate.value = todayString;
    }
    if (boardingDate) {
      boardingDate.value = todayString;
    }
  },

  bindEvents: function () {
    const attendanceType = document.getElementById("attendanceType");
    if (attendanceType) {
      attendanceType.addEventListener("change", () =>
        this.handleAttendanceTypeChange(),
      );
    }

    const classSelect = document.getElementById("classSelect");
    if (classSelect) {
      classSelect.addEventListener("change", () => this.loadPermissions());
    }

    const dormitorySelect = document.getElementById("dormitorySelect");
    if (dormitorySelect) {
      dormitorySelect.addEventListener("change", () => {
        if (this.getAttendanceType() === "boarding") {
          this.loadBoardingSummary();
        }
      });
    }

    const loadAttendanceBtn = document.getElementById("loadAttendanceBtn");
    if (loadAttendanceBtn) {
      loadAttendanceBtn.addEventListener("click", () => {
        if (this.getAttendanceType() === "boarding") {
          this.loadBoardingSummary();
          return;
        }

        this.loadAttendance();
      });
    }

    const loadDailyBtn = document.getElementById("loadDailyBtn");
    if (loadDailyBtn) {
      loadDailyBtn.addEventListener("click", () => this.loadDailyRegister());
    }

    const loadBoardingBtn = document.getElementById("loadBoardingBtn");
    if (loadBoardingBtn) {
      loadBoardingBtn.addEventListener("click", () => this.loadBoardingSummary());
    }

    const refreshPermissionsBtn = document.getElementById(
      "refreshPermissionsBtn",
    );
    if (refreshPermissionsBtn) {
      refreshPermissionsBtn.addEventListener("click", () =>
        this.loadPermissions(),
      );
    }

    const permissionsTab = document.getElementById("permissions-tab");
    if (permissionsTab) {
      permissionsTab.addEventListener("click", () => this.loadPermissions());
    }

    const exportBtn = document.getElementById("exportBtn");
    if (exportBtn) {
      exportBtn.addEventListener("click", () => this.exportCurrentView());
    }

    const printBtn = document.getElementById("printBtn");
    if (printBtn) {
      printBtn.addEventListener("click", () => window.print());
    }

    const printStudentBtn = document.getElementById("printStudentBtn");
    if (printStudentBtn) {
      printStudentBtn.addEventListener("click", () => window.print());
    }

    const dateInputs = ["dateFrom", "dateTo", "dailyDate"];
    dateInputs.forEach((id) => {
      const input = document.getElementById(id);
      if (input) {
        input.addEventListener("change", () => this.loadSessions());
      }
    });
  },

  configureSharedActions: async function () {
    if (!window.AppRouteAccess?.authorizeRoute) {
      return;
    }

    const attendanceType = document.getElementById("attendanceType");
    const boardingLink = document.getElementById("boardingRollCallLink");
    const boardingTabItem = document.getElementById("boardingTabItem");
    const markAttendanceLink = document.getElementById("markAttendanceLink");

    try {
      const [boardingAccess, markAccess] = await Promise.all([
        window.AppRouteAccess.authorizeRoute("boarding_roll_call"),
        window.AppRouteAccess.authorizeRoute("mark_attendance"),
      ]);

      this.canAccessBoarding = boardingAccess?.authorized !== false;

      if (!this.canAccessBoarding) {
        if (boardingLink) {
          boardingLink.classList.add("d-none");
        }
        if (boardingTabItem) {
          boardingTabItem.style.display = "none";
        }
        if (attendanceType) {
          const boardingOption = attendanceType.querySelector(
            'option[value="boarding"]',
          );
          if (boardingOption) {
            boardingOption.remove();
          }
          if (attendanceType.value === "boarding") {
            attendanceType.value = "academic";
          }
        }
      }

      if (markAttendanceLink && markAccess?.authorized === false) {
        markAttendanceLink.classList.add("d-none");
      }
    } catch (error) {
      console.warn("Could not resolve shared attendance route access:", error);
    }
  },

  getAttendanceType: function () {
    return document.getElementById("attendanceType")?.value || "academic";
  },

  handleAttendanceTypeChange: function () {
    const type = this.getAttendanceType();
    const classWrapper = document.getElementById("classSelectWrapper");
    const dormitoryWrapper = document.getElementById("dormitorySelectWrapper");
    const loadButton = document.getElementById("loadAttendanceBtn");

    if (classWrapper) {
      classWrapper.style.display = type === "academic" ? "" : "none";
    }
    if (dormitoryWrapper) {
      dormitoryWrapper.style.display =
        type === "boarding" && this.canAccessBoarding ? "" : "none";
    }
    if (loadButton) {
      loadButton.innerHTML =
        type === "boarding"
          ? '<i class="bi bi-house-door me-1"></i> Load Boarding Summary'
          : '<i class="bi bi-search me-1"></i> Load Attendance';
    }

    if (type === "boarding" && this.canAccessBoarding) {
      this.showTab("boarding-tab");
      this.loadBoardingSummary();
      return;
    }

    this.showTab("summary-tab");
  },

  loadClasses: async function () {
    try {
      const classes = await window.API.apiCall("/attendance/classes", "GET");
      this.classes = Array.isArray(classes) ? classes : [];
      this.renderClassDropdown();
    } catch (error) {
      this.notify(error.message || "Failed to load classes", "error");
    }
  },

  renderClassDropdown: function () {
    const select = document.getElementById("classSelect");
    if (!select) {
      return;
    }

    select.innerHTML = '<option value="">All Accessible Classes</option>';

    this.classes.forEach((cls) => {
      const option = document.createElement("option");
      option.value = cls.stream_id;
      option.textContent = `${cls.display_name || cls.name} (${cls.student_count} students)`;
      select.appendChild(option);
    });

    if (this.classes.length === 1) {
      select.value = String(this.classes[0].stream_id);
    }
  },

  loadSessions: async function () {
    const referenceDate =
      document.getElementById("dailyDate")?.value ||
      document.getElementById("dateTo")?.value ||
      this.toDateInputValue(new Date());

    try {
      const sessions = await window.API.apiCall("/attendance/sessions", "GET", null, {
        type: "academic",
        day: this.getDayName(referenceDate),
      });

      this.sessions = Array.isArray(sessions) ? sessions : [];
      this.renderSessionDropdowns();
    } catch (error) {
      this.notify(error.message || "Failed to load attendance sessions", "error");
    }
  },

  renderSessionDropdowns: function () {
    const sessionSelect = document.getElementById("sessionSelect");
    const dailySessionSelect = document.getElementById("dailySessionSelect");
    const selects = [sessionSelect, dailySessionSelect].filter(Boolean);

    selects.forEach((select) => {
      const previousValue = select.value;
      select.innerHTML = '<option value="">All Sessions</option>';

      this.sessions.forEach((session) => {
        const option = document.createElement("option");
        option.value = session.id;
        option.textContent = `${session.name} (${session.start_time} - ${session.end_time})`;
        select.appendChild(option);
      });

      if (
        previousValue &&
        this.sessions.some((session) => String(session.id) === previousValue)
      ) {
        select.value = previousValue;
      }
    });
  },

  loadDormitories: async function () {
    if (!this.canAccessBoarding) {
      return;
    }

    try {
      const dormitories = await window.API.apiCall(
        "/attendance/dormitories",
        "GET",
      );
      this.dormitories = Array.isArray(dormitories) ? dormitories : [];
      this.renderDormitoryDropdown();
    } catch (error) {
      this.canAccessBoarding = false;
      const boardingLink = document.getElementById("boardingRollCallLink");
      const boardingTabItem = document.getElementById("boardingTabItem");
      const attendanceType = document.getElementById("attendanceType");

      if (boardingLink) {
        boardingLink.classList.add("d-none");
      }
      if (boardingTabItem) {
        boardingTabItem.style.display = "none";
      }
      if (attendanceType) {
        const boardingOption = attendanceType.querySelector(
          'option[value="boarding"]',
        );
        if (boardingOption) {
          boardingOption.remove();
        }
      }
    }
  },

  renderDormitoryDropdown: function () {
    const select = document.getElementById("dormitorySelect");
    if (!select) {
      return;
    }

    select.innerHTML = '<option value="">All Dormitories</option>';

    this.dormitories.forEach((dormitory) => {
      const option = document.createElement("option");
      option.value = dormitory.id;
      option.textContent = `${dormitory.name} (${dormitory.student_count || 0} students)`;
      select.appendChild(option);
    });
  },

  loadAttendance: async function () {
    try {
      const response = await window.API.attendance.getAcademicSummary(
        this.getAcademicParams(),
      );
      this.academicData = response || {
        students: [],
        summary: {},
        trend: [],
        low_attendance: [],
      };

      this.renderAcademicSummary(this.academicData);
      await this.loadPermissions();
    } catch (error) {
      this.notify(
        error.message || "Failed to load academic attendance summary",
        "error",
      );
      this.renderAcademicSummary({
        students: [],
        summary: {
          present: 0,
          absent: 0,
          late: 0,
          permission: 0,
          average_attendance: 0,
        },
        trend: [],
        low_attendance: [],
      });
    }
  },

  getAcademicParams: function () {
    const streamId = document.getElementById("classSelect")?.value;
    const sessionId = document.getElementById("sessionSelect")?.value;
    const dateFrom = document.getElementById("dateFrom")?.value;
    const dateTo = document.getElementById("dateTo")?.value;
    const status = document.getElementById("statusFilter")?.value;

    const params = {};
    if (streamId) {
      params.stream_id = streamId;
    }
    if (sessionId) {
      params.session_id = sessionId;
    }
    if (dateFrom) {
      params.date_from = dateFrom;
    }
    if (dateTo) {
      params.date_to = dateTo;
    }
    if (status) {
      params.status = status;
    }
    return params;
  },

  renderAcademicSummary: function (data) {
    const summary = data?.summary || {};
    const students = Array.isArray(data?.students) ? data.students : [];
    const lowAttendance = Array.isArray(data?.low_attendance)
      ? data.low_attendance
      : [];

    this.updateSummaryCards({
      average_attendance: summary.average_attendance || 0,
      present: summary.present || 0,
      absent: summary.absent || 0,
      late: summary.late || 0,
      permission: summary.permission || 0,
    });

    const tbody = document.querySelector("#summaryTable tbody");
    if (tbody) {
      if (!students.length) {
        tbody.innerHTML =
          '<tr><td colspan="10" class="text-center text-muted py-4">No attendance records found for the selected filters.</td></tr>';
      } else {
        tbody.innerHTML = students
          .map(
            (student) => `
              <tr>
                  <td>${this.escapeHtml(student.admission_no || "-")}</td>
                  <td>${this.escapeHtml(student.student_name || "-")}</td>
                  <td>${this.renderStudentType(student.student_type_code, student.student_type)}</td>
                  <td>${student.total_days || 0}</td>
                  <td>${student.present || 0}</td>
                  <td>${student.absent || 0}</td>
                  <td>${student.late || 0}</td>
                  <td>${student.permission || 0}</td>
                  <td>${this.formatPercent(student.attendance_percentage)}</td>
                  <td>
                      <button
                          type="button"
                          class="btn btn-sm btn-outline-primary view-attendance-details"
                          data-student-id="${student.student_id}"
                          data-student-name="${this.escapeAttribute(student.student_name || "")}"
                      >
                          <i class="bi bi-eye"></i> View
                      </button>
                  </td>
              </tr>
          `,
          )
          .join("");
      }

      tbody.querySelectorAll(".view-attendance-details").forEach((button) => {
        button.addEventListener("click", () => {
          this.viewDetails(
            button.dataset.studentId,
            button.dataset.studentName || "Student",
          );
        });
      });
    }

    const lowAttendanceBody = document.getElementById("lowAttendanceBody");
    if (lowAttendanceBody) {
      if (!lowAttendance.length) {
        lowAttendanceBody.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted py-3">No learners are currently below the attendance threshold.</td></tr>';
      } else {
        lowAttendanceBody.innerHTML = lowAttendance
          .map(
            (row) => `
              <tr>
                  <td>${this.escapeHtml(row.student_name || "-")}</td>
                  <td>${this.formatPercent(row.attendance_percentage)}</td>
                  <td>${row.absent_days || 0}</td>
                  <td>${this.formatDate(row.last_absent_date)}</td>
              </tr>
          `,
          )
          .join("");
      }
    }

    this.renderCharts(data?.trend || [], summary);
  },

  loadDailyRegister: async function () {
    try {
      const params = {
        date:
          document.getElementById("dailyDate")?.value ||
          document.getElementById("dateTo")?.value,
      };

      const streamId = document.getElementById("classSelect")?.value;
      const sessionId = document.getElementById("dailySessionSelect")?.value;

      if (streamId) {
        params.stream_id = streamId;
      }
      if (sessionId) {
        params.session_id = sessionId;
      }

      this.dailyData = await window.API.attendance.getDailyRegister(params);
      this.renderDailyRegister(this.dailyData);
      this.showTab("daily-tab");
    } catch (error) {
      this.notify(error.message || "Failed to load daily register", "error");
      this.renderDailyRegister([]);
    }
  },

  renderDailyRegister: function (rows) {
    const tbody = document.querySelector("#dailyTable tbody");
    if (!tbody) {
      return;
    }

    if (!Array.isArray(rows) || !rows.length) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">No daily attendance records found.</td></tr>';
      return;
    }

    tbody.innerHTML = rows
      .map(
        (row) => `
          <tr>
              <td>${this.escapeHtml(row.admission_no || "-")}</td>
              <td>${this.escapeHtml(`${row.first_name || ""} ${row.last_name || ""}`.trim() || "-")}</td>
              <td>${this.renderStudentType(row.student_type_code, row.student_type)}</td>
              <td>${this.escapeHtml(row.session_name || "-")}</td>
              <td>${this.renderStatusBadge(row.status)}</td>
              <td>${this.escapeHtml(row.marked_at || "-")}</td>
              <td>${this.escapeHtml(row.notes || "-")}</td>
          </tr>
      `,
      )
      .join("");
  },

  loadBoardingSummary: async function () {
    if (!this.canAccessBoarding) {
      return;
    }

    try {
      const response = await window.API.attendance.getBoardingSummary({
        date:
          document.getElementById("boardingDate")?.value ||
          this.toDateInputValue(new Date()),
      });

      const summary = Array.isArray(response?.summary) ? response.summary : [];
      const dormitoryId = document.getElementById("dormitorySelect")?.value;

      this.boardingData = dormitoryId
        ? summary.filter(
            (row) => String(row.dormitory_id) === String(dormitoryId),
          )
        : summary;

      this.renderBoardingSummary(this.boardingData);
      this.updateSummaryCards(this.summarizeBoardingRows(this.boardingData));
      this.showTab("boarding-tab");
    } catch (error) {
      this.notify(error.message || "Failed to load boarding summary", "error");
      this.renderBoardingSummary([]);
      this.updateSummaryCards({
        average_attendance: 0,
        present: 0,
        absent: 0,
        late: 0,
        permission: 0,
      });
    }
  },

  summarizeBoardingRows: function (rows) {
    const summary = {
      present: 0,
      absent: 0,
      permission: 0,
      late: 0,
      total_days: 0,
      average_attendance: 0,
    };

    rows.forEach((row) => {
      summary.present += Number(row.present || 0);
      summary.absent += Number(row.absent || 0);
      summary.permission += Number(row.on_permission || 0);
      summary.total_days += Number(row.total_students || 0);
    });

    if (summary.total_days > 0) {
      summary.average_attendance = Number(
        ((summary.present / summary.total_days) * 100).toFixed(1),
      );
    }

    return summary;
  },

  renderBoardingSummary: function (rows) {
    const container = document.getElementById("boardingSummaryCards");
    if (!container) {
      return;
    }

    if (!Array.isArray(rows) || !rows.length) {
      container.innerHTML =
        '<div class="col-12"><div class="alert alert-light border text-muted mb-0">No boarding attendance records found for the selected date.</div></div>';
      return;
    }

    container.innerHTML = rows
      .map((row) => {
        const total = Number(row.total_students || 0);
        const present = Number(row.present || 0);
        const absent = Number(row.absent || 0);
        const permission = Number(row.on_permission || 0);
        const sickBay = Number(row.sick_bay || 0);
        const rate = total > 0 ? ((present / total) * 100).toFixed(1) : "0.0";

        return `
          <div class="col-md-6 col-xl-4 mb-3">
              <div class="card dormitory-card h-100 border-0 shadow-sm">
                  <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start mb-2">
                          <div>
                              <h5 class="mb-1">${this.escapeHtml(row.dormitory_name || "-")}</h5>
                              <div class="text-muted small">${this.escapeHtml(row.session_name || "-")}</div>
                          </div>
                          <span class="badge bg-light text-dark">${this.escapeHtml(row.code || "")}</span>
                      </div>
                      <div class="row g-2 text-center">
                          <div class="col-6">
                              <div class="border rounded p-2">
                                  <div class="text-muted small">Present</div>
                                  <div class="fw-semibold text-success">${present}</div>
                              </div>
                          </div>
                          <div class="col-6">
                              <div class="border rounded p-2">
                                  <div class="text-muted small">Absent</div>
                                  <div class="fw-semibold text-danger">${absent}</div>
                              </div>
                          </div>
                          <div class="col-6">
                              <div class="border rounded p-2">
                                  <div class="text-muted small">Permission</div>
                                  <div class="fw-semibold text-info">${permission}</div>
                              </div>
                          </div>
                          <div class="col-6">
                              <div class="border rounded p-2">
                                  <div class="text-muted small">Sick Bay</div>
                                  <div class="fw-semibold text-primary">${sickBay}</div>
                              </div>
                          </div>
                      </div>
                      <div class="mt-3 d-flex justify-content-between">
                          <span class="text-muted small">Total: ${total}</span>
                          <span class="fw-semibold">${rate}% present</span>
                      </div>
                  </div>
              </div>
          </div>
        `;
      })
      .join("");
  },

  loadPermissions: async function () {
    try {
      const params = {
        status: "approved",
        active: true,
      };

      const streamId = document.getElementById("classSelect")?.value;
      if (streamId) {
        params.stream_id = streamId;
      }

      this.permissionsData = await window.API.attendance.getPermissions(params);
      this.renderPermissions(this.permissionsData);
    } catch (error) {
      this.notify(error.message || "Failed to load student permissions", "error");
      this.renderPermissions([]);
    }
  },

  renderPermissions: function (rows) {
    const tbody = document.getElementById("permissionsTableBody");
    if (!tbody) {
      return;
    }

    if (!Array.isArray(rows) || !rows.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center text-muted py-4">No active permissions found.</td></tr>';
      return;
    }

    tbody.innerHTML = rows
      .map(
        (row) => `
          <tr>
              <td>
                  <div class="fw-semibold">${this.escapeHtml(row.student_name || "-")}</div>
                  <div class="text-muted small">${this.escapeHtml(row.admission_no || "-")}</div>
              </td>
              <td>${this.escapeHtml(
                [row.class_name, row.stream_name].filter(Boolean).join(" - ") || "-",
              )}</td>
              <td>${this.escapeHtml(row.permission_type_name || row.permission_type_code || "-")}</td>
              <td>${this.formatDate(row.start_date)}</td>
              <td>${this.formatDate(row.end_date)}</td>
              <td>${this.escapeHtml(row.reason || "-")}</td>
              <td>${this.escapeHtml(row.approved_by_name || "-")}</td>
              <td>${this.renderStatusBadge(row.status)}</td>
          </tr>
      `,
      )
      .join("");
  },

  updateSummaryCards: function (summary) {
    const avgAttendance = document.getElementById("avgAttendance");
    const presentCount = document.getElementById("presentCount");
    const absentCount = document.getElementById("absentCount");
    const lateCount = document.getElementById("lateCount");
    const permissionCount = document.getElementById("permissionCount");

    if (avgAttendance) {
      avgAttendance.textContent = this.formatPercent(
        summary.average_attendance || 0,
      );
    }
    if (presentCount) {
      presentCount.textContent = Number(summary.present || 0);
    }
    if (absentCount) {
      absentCount.textContent = Number(summary.absent || 0);
    }
    if (lateCount) {
      lateCount.textContent = Number(summary.late || 0);
    }
    if (permissionCount) {
      permissionCount.textContent = Number(summary.permission || 0);
    }
  },

  renderCharts: function (trendRows, summary) {
    if (typeof Chart === "undefined") {
      return;
    }

    const trendCanvas = document.getElementById("trendChart");
    const statusCanvas = document.getElementById("statusPieChart");

    if (this.charts.trend) {
      this.charts.trend.destroy();
    }
    if (this.charts.status) {
      this.charts.status.destroy();
    }

    if (trendCanvas) {
      this.charts.trend = new Chart(trendCanvas.getContext("2d"), {
        type: "line",
        data: {
          labels: trendRows.map((row) => this.formatDate(row.date)),
          datasets: [
            {
              label: "Present",
              data: trendRows.map((row) => Number(row.present || 0)),
              borderColor: "#198754",
              backgroundColor: "rgba(25, 135, 84, 0.15)",
              fill: true,
              tension: 0.25,
            },
            {
              label: "Absent",
              data: trendRows.map((row) => Number(row.absent || 0)),
              borderColor: "#dc3545",
              backgroundColor: "rgba(220, 53, 69, 0.08)",
              fill: false,
              tension: 0.25,
            },
            {
              label: "Late",
              data: trendRows.map((row) => Number(row.late || 0)),
              borderColor: "#ffc107",
              backgroundColor: "rgba(255, 193, 7, 0.08)",
              fill: false,
              tension: 0.25,
            },
            {
              label: "Permission",
              data: trendRows.map((row) => Number(row.permission || 0)),
              borderColor: "#0dcaf0",
              backgroundColor: "rgba(13, 202, 240, 0.08)",
              fill: false,
              tension: 0.25,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "bottom",
            },
          },
        },
      });
    }

    if (statusCanvas) {
      this.charts.status = new Chart(statusCanvas.getContext("2d"), {
        type: "doughnut",
        data: {
          labels: ["Present", "Absent", "Late", "Permission"],
          datasets: [
            {
              data: [
                Number(summary.present || 0),
                Number(summary.absent || 0),
                Number(summary.late || 0),
                Number(summary.permission || 0),
              ],
              backgroundColor: ["#198754", "#dc3545", "#ffc107", "#0dcaf0"],
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "bottom",
            },
          },
        },
      });
    }
  },

  viewDetails: async function (studentId, studentName) {
    try {
      const [summaryRows, historyRows] = await Promise.all([
        window.API.attendance.getStudentSummary(studentId),
        window.API.attendance.getStudentHistory(studentId),
      ]);

      const modalStudent = document.getElementById("modalStudent");
      const modalPresent = document.getElementById("modalPresent");
      const modalAbsent = document.getElementById("modalAbsent");
      const modalRate = document.getElementById("modalRate");
      const modalAttendanceBody = document.getElementById("modalAttendanceBody");

      const computed = this.summarizeStudentDetails(summaryRows || [], historyRows || []);

      if (modalStudent) {
        modalStudent.textContent = studentName || "Student";
      }
      if (modalPresent) {
        modalPresent.textContent = computed.present;
      }
      if (modalAbsent) {
        modalAbsent.textContent = computed.absent;
      }
      if (modalRate) {
        modalRate.textContent = this.formatPercent(computed.rate);
      }

      if (modalAttendanceBody) {
        if (!computed.history.length) {
          modalAttendanceBody.innerHTML =
            '<tr><td colspan="4" class="text-center text-muted py-3">No attendance history found.</td></tr>';
        } else {
          modalAttendanceBody.innerHTML = computed.history
            .map(
              (row) => `
                <tr>
                    <td>${this.formatDate(row.date)}</td>
                    <td>${this.renderStatusBadge(row.effective_status)}</td>
                    <td>${this.escapeHtml(row.check_in_time || "-")}</td>
                    <td>${this.escapeHtml(row.notes || "-")}</td>
                </tr>
            `,
            )
            .join("");
        }
      }

      const modalElement = document.getElementById("detailsModal");
      if (modalElement && window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
      }
    } catch (error) {
      this.notify(
        error.message || "Failed to load learner attendance details",
        "error",
      );
    }
  },

  summarizeStudentDetails: function (summaryRows, historyRows) {
    let present = 0;
    let absent = 0;
    let total = 0;

    (Array.isArray(summaryRows) ? summaryRows : []).forEach((row) => {
      present += Number(row.present_days || 0);
      absent += Number(row.absent_days || 0);
      total += Number(row.total_days || 0);
    });

    const history = (Array.isArray(historyRows) ? historyRows : []).map((row) => {
      const effectiveStatus =
        row.absence_reason === "permission" ? "permission" : row.status;
      return {
        ...row,
        effective_status: effectiveStatus || "not_marked",
      };
    });

    if (!total && history.length) {
      history.forEach((row) => {
        total += 1;
        if (row.effective_status === "present") {
          present += 1;
        } else if (
          ["absent", "permission", "late"].includes(row.effective_status)
        ) {
          absent += row.effective_status === "late" ? 0 : 1;
        }
      });
    }

    return {
      present: present,
      absent: absent,
      total: total,
      rate: total > 0 ? (present / total) * 100 : 0,
      history: history,
    };
  },

  exportCurrentView: function () {
    const activePane =
      document.querySelector(".tab-pane.active.show") ||
      document.querySelector(".tab-pane.active");
    const activeId = activePane?.id || "summary";
    let filename = "attendance-export.csv";
    let rows = [];

    if (activeId === "daily") {
      filename = "daily-attendance-register.csv";
      rows = this.dailyData.map((row) => ({
        admission_no: row.admission_no,
        student_name: `${row.first_name || ""} ${row.last_name || ""}`.trim(),
        student_type: row.student_type || row.student_type_code,
        session: row.session_name,
        status: row.status,
        marked_at: row.marked_at,
        notes: row.notes,
      }));
    } else if (activeId === "boarding") {
      filename = "boarding-attendance-summary.csv";
      rows = this.boardingData.map((row) => ({
        dormitory: row.dormitory_name,
        session: row.session_name,
        total_students: row.total_students,
        present: row.present,
        absent: row.absent,
        on_permission: row.on_permission,
        sick_bay: row.sick_bay,
      }));
    } else if (activeId === "permissions") {
      filename = "active-student-permissions.csv";
      rows = this.permissionsData.map((row) => ({
        student_name: row.student_name,
        admission_no: row.admission_no,
        class_name: [row.class_name, row.stream_name].filter(Boolean).join(" - "),
        permission_type: row.permission_type_name || row.permission_type_code,
        start_date: row.start_date,
        end_date: row.end_date,
        reason: row.reason,
        approved_by: row.approved_by_name,
        status: row.status,
      }));
    } else {
      filename = "attendance-summary.csv";
      rows = (this.academicData?.students || []).map((row) => ({
        admission_no: row.admission_no,
        student_name: row.student_name,
        student_type: row.student_type || row.student_type_code,
        total_days: row.total_days,
        present: row.present,
        absent: row.absent,
        late: row.late,
        permission: row.permission,
        attendance_percentage: row.attendance_percentage,
      }));
    }

    if (!rows.length) {
      this.notify("There is no data to export for the current tab.", "warning");
      return;
    }

    const csv = this.toCsv(rows);
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  showTab: function (tabId) {
    const trigger = document.getElementById(tabId);
    if (trigger && window.bootstrap?.Tab) {
      window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    }
  },

  renderStatusBadge: function (status) {
    const normalized = String(status || "not_marked").toLowerCase();
    const labelMap = {
      present: "Present",
      absent: "Absent",
      late: "Late",
      permission: "On Permission",
      sick_bay: "Sick Bay",
      approved: "Approved",
      pending: "Pending",
      rejected: "Rejected",
      cancelled: "Cancelled",
      not_marked: "Not Marked",
    };

    const cssClass = `status-${normalized.replace(/_/g, "-")}`;
    return `<span class="status-badge ${cssClass}">${this.escapeHtml(labelMap[normalized] || status || "-")}</span>`;
  },

  renderStudentType: function (code, name) {
    const label = name || code || "-";
    return this.escapeHtml(label);
  },

  notify: function (message, type = "info") {
    if (window.API?.showNotification) {
      window.API.showNotification(message, type);
      return;
    }
    console[type === "error" ? "error" : "log"](message);
  },

  formatPercent: function (value) {
    return `${Number(value || 0).toFixed(1)}%`;
  },

  formatDate: function (value) {
    if (!value) {
      return "-";
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return this.escapeHtml(String(value));
    }

    return date.toLocaleDateString("en-KE", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  },

  toDateInputValue: function (date) {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
      .toISOString()
      .split("T")[0];
  },

  getDayName: function (dateString) {
    const date = dateString ? new Date(dateString) : new Date();
    return date.toLocaleDateString("en-US", { weekday: "long" });
  },

  escapeHtml: function (value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  },

  escapeAttribute: function (value) {
    return this.escapeHtml(value).replace(/`/g, "&#96;");
  },

  toCsv: function (rows) {
    const headers = Object.keys(rows[0]);
    const escapeCell = (value) =>
      `"${String(value ?? "")
        .replace(/"/g, '""')
        .replace(/\r?\n/g, " ")}"`;

    const lines = [
      headers.map(escapeCell).join(","),
      ...rows.map((row) => headers.map((header) => escapeCell(row[header])).join(",")),
    ];

    return lines.join("\n");
  },
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    viewAttendanceController.init(),
  );
} else {
  viewAttendanceController.init();
}
