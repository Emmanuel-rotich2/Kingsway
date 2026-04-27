const StaffAttendanceController = {
  departments: [],
  dutyTypes: [],
  todayStaff: [],
  reportData: null,
  attendanceMarked: {},
  _registerContext: null,    // from getStaffRegisterContext
  _currentShift: 'full_day', // shift being marked
  charts: {
    trend: null,
    pie: null,
  },

  init: async function () {
    this.setDefaultDates();
    this.bindEvents();
    await Promise.all([this.loadDepartments(), this.loadDutyTypes()]);
    await Promise.all([this.loadTodayStaff(), this.generateReport()]);
  },

  setDefaultDates: function () {
    const today = new Date();
    const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

    const setValue = (id, value) => {
      const element = document.getElementById(id);
      if (element) {
        element.value = value;
      }
    };

    setValue("dateFrom", this.toDateInputValue(firstOfMonth));
    setValue("dateTo", this.toDateInputValue(today));
    setValue("markDate", this.toDateInputValue(today));

    const todayDate = document.getElementById("todayDate");
    if (todayDate) {
      todayDate.textContent = today.toLocaleDateString("en-KE", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
      });
    }
  },

  bindEvents: function () {
    const bind = (id, event, handler) => {
      const element = document.getElementById(id);
      if (element) {
        element.addEventListener(event, handler);
      }
    };

    bind("generateBtn", "click", () => this.generateReport());
    bind("exportBtn", "click", () => this.exportData());
    bind("printBtn", "click", () => window.print());
    bind("loadStaffForMarkingBtn", "click", () => this.loadStaffForMarking());
    bind("markAllPresentBtn", "click", () => this.markAllStaff("present"));
    bind("markAllAbsentBtn", "click", () => this.markAllStaff("absent"));
    bind("submitStaffAttendanceBtn", "click", () => this.submitStaffAttendance());
  },

  async loadDepartments() {
    try {
      const response = await window.API.apiCall("/staff/departments/get", "GET");
      this.departments = Array.isArray(response) ? response : [];
      this.renderDepartmentDropdowns();
    } catch (error) {
      this.notify(error.message || "Failed to load departments", "error");
    }
  },

  renderDepartmentDropdowns: function () {
    ["department", "markDepartment"].forEach((id) => {
      const select = document.getElementById(id);
      if (!select) {
        return;
      }

      const previousValue = select.value;
      select.innerHTML = '<option value="">All Departments</option>';
      this.departments.forEach((department) => {
        const option = document.createElement("option");
        option.value = department.id;
        option.textContent = department.name;
        select.appendChild(option);
      });
      select.value = previousValue;
    });
  },

  async loadDutyTypes() {
    try {
      this.dutyTypes = await window.API.attendance.getDutyTypes();
      this.renderDutyTypeDropdown();
    } catch (error) {
      this.notify(error.message || "Failed to load duty types", "error");
      this.dutyTypes = [];
      this.renderDutyTypeDropdown();
    }
  },

  renderDutyTypeDropdown: function () {
    const select = document.getElementById("dutyType");
    if (!select) {
      return;
    }

    const previousValue = select.value;
    select.innerHTML = '<option value="">All Types</option>';
    this.dutyTypes.forEach((dutyType) => {
      const option = document.createElement("option");
      option.value = dutyType.id;
      option.textContent = dutyType.duty_name || dutyType.name;
      select.appendChild(option);
    });
    select.value = previousValue;
  },

  async loadTodayStaff() {
    const date = this.toDateInputValue(new Date());
    const departmentId = document.getElementById("markDepartment")?.value;

    try {
      const response = await window.API.attendance.getStaffToday({
        date: date,
        department_id: departmentId || undefined,
      });
      this.todayStaff = Array.isArray(response?.staff) ? response.staff : [];
      this.renderTodayStaffGrid(this.todayStaff);
    } catch (error) {
      this.notify(error.message || "Failed to load today's staff attendance", "error");
      this.renderTodayStaffGrid([]);
    }
  },

  renderTodayStaffGrid: function (staffRows) {
    const container = document.getElementById("todayStaffGrid");
    if (!container) {
      return;
    }

    if (!staffRows.length) {
      container.innerHTML =
        '<div class="col-12 text-center text-muted py-4">No staff attendance records available for today.</div>';
      return;
    }

    const grouped = {
      present: [],
      absent: [],
      late: [],
      on_leave: [],
      off_day: [],
      not_marked: [],
    };

    staffRows.forEach((staff) => {
      const status = staff.effective_status || "not_marked";
      if (!grouped[status]) {
        grouped.not_marked.push(staff);
        return;
      }
      grouped[status].push(staff);
    });

    const statusConfig = {
      present: { label: "Present", color: "success" },
      absent: { label: "Absent", color: "danger" },
      late: { label: "Late", color: "warning" },
      on_leave: { label: "On Leave", color: "info" },
      off_day: { label: "Off Day", color: "secondary" },
      not_marked: { label: "Not Marked", color: "light" },
    };

    container.innerHTML = Object.entries(statusConfig)
      .filter(([status]) => grouped[status].length > 0)
      .map(([status, config]) => {
        const staff = grouped[status];
        return `
          <div class="col-md-4 col-lg-2 mb-3">
            <div class="card staff-status-card status-${status.replace(/_/g, "-")}">
              <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="badge bg-${config.color}">${config.label}</span>
                  <strong class="text-${config.color}">${staff.length}</strong>
                </div>
                <div class="small text-muted" style="max-height: 90px; overflow-y: auto;">
                  ${staff
                    .slice(0, 5)
                    .map(
                      (member) => `
                        <div>
                          ${this.escapeHtml(`${member.first_name || ""} ${member.last_name || ""}`.trim())}
                          <div class="small text-muted">${this.escapeHtml(member.duty_type || member.position || "General")}</div>
                        </div>
                      `,
                    )
                    .join("")}
                  ${
                    staff.length > 5
                      ? `<div class="small fst-italic mt-1">+${staff.length - 5} more</div>`
                      : ""
                  }
                </div>
              </div>
            </div>
          </div>
        `;
      })
      .join("");
  },

  async generateReport() {
    const params = {
      date_from: document.getElementById("dateFrom")?.value,
      date_to: document.getElementById("dateTo")?.value,
      department_id: document.getElementById("department")?.value || undefined,
      duty_type_id: document.getElementById("dutyType")?.value || undefined,
      status: document.getElementById("statusFilter")?.value || undefined,
    };

    try {
      this.reportData = await window.API.attendance.getStaffReport(params);
      this.renderAttendanceTable(this.reportData?.staff || []);
      this.updateSummaryCards(this.reportData?.summary || {});
      this.renderCharts(this.reportData?.trend || [], this.reportData?.summary || {});
      this.renderDailyBreakdown(
        this.reportData?.daily_breakdown || [],
        this.reportData?.date_from,
        this.reportData?.date_to,
      );
    } catch (error) {
      this.notify(error.message || "Failed to generate staff attendance report", "error");
      this.reportData = null;
      this.renderAttendanceTable([]);
      this.updateSummaryCards({});
      this.renderDailyBreakdown([], null, null);
    }
  },

  renderAttendanceTable: function (staffRows) {
    const tbody = document.getElementById("attendanceTableBody");
    if (!tbody) {
      return;
    }

    if (!staffRows.length) {
      tbody.innerHTML =
        '<tr><td colspan="10" class="text-center py-4 text-muted">No data found for the selected criteria.</td></tr>';
      return;
    }

    tbody.innerHTML = staffRows
      .map((staff) => {
        const workDays = Number(staff.present || 0) + Number(staff.absent || 0) + Number(staff.late || 0);
        const attendanceRate = workDays > 0
          ? (((Number(staff.present || 0) + Number(staff.late || 0)) / workDays) * 100).toFixed(1)
          : "0.0";
        const attendanceClass =
          Number(attendanceRate) >= 90
            ? "text-success"
            : Number(attendanceRate) >= 75
            ? "text-warning"
            : "text-danger";

        return `
          <tr>
            <td>
              <strong>${this.escapeHtml(`${staff.first_name || ""} ${staff.last_name || ""}`.trim())}</strong>
              <br><small class="text-muted">${this.escapeHtml(staff.staff_no || "-")}</small>
            </td>
            <td>${this.escapeHtml(staff.department_name || "N/A")}</td>
            <td><span class="badge bg-secondary">${this.escapeHtml(staff.duty_type || "General")}</span></td>
            <td><span class="text-success">${Number(staff.present || 0)}</span></td>
            <td><span class="text-danger">${Number(staff.absent || 0)}</span></td>
            <td><span class="text-warning">${Number(staff.late || 0)}</span></td>
            <td><span class="text-info">${Number(staff.on_leave || 0)}</span></td>
            <td><span class="text-secondary">${Number(staff.off_days || 0)}</span></td>
            <td><strong class="${attendanceClass}">${attendanceRate}%</strong></td>
            <td>
              <button type="button"
                      class="btn btn-sm btn-outline-primary staff-details-btn"
                      data-staff-id="${staff.staff_id}"
                      data-staff-name="${this.escapeAttribute(`${staff.first_name || ""} ${staff.last_name || ""}`.trim())}">
                <i class="bi bi-eye"></i>
              </button>
            </td>
          </tr>
        `;
      })
      .join("");

    tbody.querySelectorAll(".staff-details-btn").forEach((button) => {
      button.addEventListener("click", () => {
        this.viewDetails(button.dataset.staffId, button.dataset.staffName || "Staff");
      });
    });
  },

  updateSummaryCards: function (summary) {
    const setText = (id, value) => {
      const element = document.getElementById(id);
      if (element) {
        element.textContent = value;
      }
    };

    setText("avgAttendance", `${Number(summary.average_attendance || 0).toFixed(1)}%`);
    setText("presentDays", Number(summary.present || 0));
    setText("absentDays", Number(summary.absent || 0));
    setText("lateDays", Number(summary.late || 0));
    setText("leaveDays", Number(summary.on_leave || 0));
    setText("offDays", Number(summary.off_day || 0));
  },

  renderCharts: function (trendRows, summary) {
    if (typeof Chart === "undefined") {
      return;
    }

    const trendCanvas = document.getElementById("attendanceTrendChart");
    const pieCanvas = document.getElementById("statusPieChart");

    if (this.charts.trend) {
      this.charts.trend.destroy();
    }
    if (this.charts.pie) {
      this.charts.pie.destroy();
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
              backgroundColor: "rgba(25, 135, 84, 0.12)",
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
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: "top",
            },
          },
        },
      });
    }

    if (pieCanvas) {
      this.charts.pie = new Chart(pieCanvas.getContext("2d"), {
        type: "doughnut",
        data: {
          labels: ["Present", "Absent", "Late", "On Leave", "Off Day", "Not Marked"],
          datasets: [
            {
              data: [
                Number(summary.present || 0),
                Number(summary.absent || 0),
                Number(summary.late || 0),
                Number(summary.on_leave || 0),
                Number(summary.off_day || 0),
                Number(summary.not_marked || 0),
              ],
              backgroundColor: [
                "#198754",
                "#dc3545",
                "#ffc107",
                "#0dcaf0",
                "#6c757d",
                "#adb5bd",
              ],
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: "bottom",
            },
          },
        },
      });
    }
  },

  renderDailyBreakdown: function (rows, dateFrom, dateTo) {
    const headersRow = document.getElementById("dailyHeaders");
    const tbody = document.getElementById("dailyBody");
    if (!headersRow || !tbody) {
      return;
    }

    if (!rows.length || !dateFrom || !dateTo) {
      headersRow.innerHTML = "<th>Staff</th><th>Duty</th>";
      tbody.innerHTML =
        '<tr><td colspan="10" class="text-center py-4 text-muted">No daily breakdown available for the selected filters.</td></tr>';
      return;
    }

    const dates = this.buildDateRange(dateFrom, dateTo);
    headersRow.innerHTML = `
      <th>Staff</th>
      <th>Duty</th>
      ${dates
        .map(
          (date) => `<th class="text-center small">${this.formatDateShort(date)}</th>`,
        )
        .join("")}
    `;

    tbody.innerHTML = rows
      .map((row) => {
        const statusMap = {};
        (row.statuses || []).forEach((statusRow) => {
          statusMap[statusRow.date] = statusRow;
        });

        return `
          <tr>
            <td>
              <strong>${this.escapeHtml(row.staff_name || "-")}</strong>
              <br><small class="text-muted">${this.escapeHtml(row.staff_no || "-")}</small>
            </td>
            <td>${this.escapeHtml(row.duty_type || "General")}</td>
            ${dates
              .map((date) => this.renderDailyStatusCell(statusMap[date] || { status: "not_marked" }))
              .join("")}
          </tr>
        `;
      })
      .join("");
  },

  renderDailyStatusCell: function (statusRow) {
    const status = statusRow.status || "not_marked";
    const map = {
      present: { label: "P", className: "bg-success" },
      absent: { label: "A", className: "bg-danger" },
      late: { label: "L", className: "bg-warning text-dark" },
      on_leave: { label: "LV", className: "bg-info text-dark" },
      off_day: { label: "O", className: "bg-secondary" },
      not_marked: { label: "-", className: "bg-light text-dark border" },
    };

    const config = map[status] || map.not_marked;
    const titleParts = [
      statusRow.label || status,
      statusRow.duty_type ? `Duty: ${statusRow.duty_type}` : null,
      statusRow.leave_type ? `Leave: ${statusRow.leave_type}` : null,
    ].filter(Boolean);

    return `
      <td class="text-center">
        <span class="badge ${config.className}" title="${this.escapeAttribute(titleParts.join(" | "))}">
          ${config.label}
        </span>
      </td>
    `;
  },

  async loadStaffForMarking() {
    const date         = document.getElementById("markDate")?.value;
    const departmentId = document.getElementById("markDepartment")?.value;
    const shift        = document.getElementById("markShift")?.value || 'full_day';
    this._currentShift = shift;

    try {
      // Use the new register context endpoint — returns pre-computed effective status per staff
      const qs = `/attendance/staff-register-context?date=${date}&shift=${shift}${departmentId ? '&department_id='+departmentId : ''}`;
      const r  = await window.API.apiCall(qs, 'GET');
      this._registerContext = r;

      const staff = Array.isArray(r?.staff) ? r.staff : [];

      // Show day type banner
      const banner = document.getElementById('markDayBanner');
      if (banner) {
        if (r?.day_type && r.day_type !== 'school_day') {
          banner.className = 'alert alert-' + (r.day_type === 'public_holiday' ? 'danger' : 'warning') + ' mb-3';
          banner.innerHTML = `<i class="bi bi-calendar-x me-2"></i><strong>${r.day_name}:</strong> ${r.event_name}` +
            (r.only_roster ? ' — Only staff on duty roster should be marked.' : '');
          banner.style.display = '';
        } else {
          banner.style.display = 'none';
        }
      }

      // Populate shift selector based on context
      const shiftSel = document.getElementById('markShift');
      if (shiftSel && r?.available_shifts) {
        const cur = shiftSel.value;
        shiftSel.innerHTML = Object.entries(r.available_shifts)
          .map(([k,v]) => `<option value="${k}" ${k===cur?'selected':''}>${v}</option>`).join('');
      }

      // Update summary badges
      const sum = r?.summary || {};
      const setBadge = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
      setBadge('ctxTotal',    sum.total    || 0);
      setBadge('ctxMarked',   (sum.present||0)+(sum.absent||0)+(sum.late||0));
      setBadge('ctxOnLeave',  sum.on_leave || 0);
      setBadge('ctxOffDay',   sum.off_day  || 0);
      setBadge('ctxOnDuty',   sum.on_duty  || 0);

      this.renderMarkStaffTable(staff);
    } catch (error) {
      this.notify(error.message || "Failed to load staff for marking", "error");
      this.renderMarkStaffTable([]);
    }
  },

  renderMarkStaffTable: function (staffRows) {
    const tbody = document.getElementById("markStaffTableBody");
    if (!tbody) {
      return;
    }

    this.attendanceMarked = {};

    if (!staffRows.length) {
      tbody.innerHTML =
        '<tr><td colspan="5" class="text-center py-4 text-muted">No staff found for the selected filter.</td></tr>';
      return;
    }

    tbody.innerHTML = staffRows
      .map((staff) => {
        // Context endpoint provides effective_status and can_mark
        const existingStatus = ['present','absent','late'].includes(staff.marked_status)
          ? staff.marked_status
          : (staff.effective_status === 'not_marked' ? '' : '');
        const canMark  = Number(staff.can_mark ?? 1) === 1;
        const effSt    = staff.effective_status || 'not_marked';
        this.attendanceMarked[staff.staff_id] = existingStatus;

        // Late threshold indicator
        const lateMin  = staff.late_threshold_minutes || 15;
        const expTime  = staff.work_start_time ? staff.work_start_time.slice(0,5) : '08:00';
        const lateTime = this._addMinutes(expTime, lateMin);

        // Check-in input (shown when marking present/late so HR can record actual time)
        const checkInInput = canMark ? `
          <input type="time" class="form-control form-control-sm mt-1"
                 id="checkin_${staff.staff_id}"
                 value="${staff.check_in_time ? staff.check_in_time.slice(0,5) : ''}"
                 placeholder="${expTime}"
                 title="Expected: ${expTime} | Late after: ${lateTime}">
        ` : '';

        // Duty badge
        const dutyBadge = staff.duty_code && !['OFF','WEEKEND_OFF'].includes(staff.duty_code)
          ? `<span class="badge bg-info text-dark ms-1" title="${this.escapeAttribute(staff.duty_location||'')}">${this.escapeHtml(staff.duty_code)}</span>`
          : '';

        // Reason selector for absent
        const reasonSel = canMark ? `
          <select class="form-select form-select-sm mt-1" id="reason_${staff.staff_id}" style="display:none">
            <option value="unauthorized">Unauthorized</option>
            <option value="sick">Sick / Medical</option>
            <option value="other">Other</option>
          </select>` : '';

        return `
          <tr data-staff-id="${staff.staff_id}">
            <td>
              <strong>${this.escapeHtml(staff.staff_name || `${staff.first_name||''} ${staff.last_name||''}`.trim())}</strong>
              <br><small class="text-muted">${this.escapeHtml(staff.staff_no || '-')}</small>
              ${dutyBadge}
            </td>
            <td>${this.escapeHtml(staff.department_name || 'N/A')}</td>
            <td>
              <small class="text-muted d-block"><i class="bi bi-clock me-1"></i>In: ${expTime} | Late: ${lateTime}</small>
              ${checkInInput}
              ${reasonSel}
            </td>
            <td>${this._renderEffectiveStatus(staff)}</td>
            <td>
              ${!canMark
                ? `<span class="badge bg-${effSt==='on_leave'?'info':'secondary'} text-${effSt==='on_leave'?'dark':'white'}">${effSt==='on_leave'?'On Leave':'Off Day'}</span>`
                : `<div class="btn-group btn-group-sm" role="group">
                    ${this.renderStaffMarkOption(staff.staff_id, 'present', 'P', 'success', existingStatus)}
                    ${this.renderStaffMarkOption(staff.staff_id, 'absent',  'A', 'danger',  existingStatus)}
                    ${this.renderStaffMarkOption(staff.staff_id, 'late',    'L', 'warning', existingStatus)}
                  </div>`
              }
            </td>
          </tr>
        `;
      })
      .join("");

    // Show/hide reason selector when absent is clicked
    tbody.querySelectorAll('input[type="radio"]').forEach((radio) => {
      radio.addEventListener('change', (e) => {
        const sid    = e.target.dataset.staffId;
        const reason = document.getElementById(`reason_${sid}`);
        if (reason) reason.style.display = e.target.value === 'absent' ? '' : 'none';
        this.attendanceMarked[sid] = e.target.value;
      });
    });

    tbody.querySelectorAll('input[type="radio"]').forEach((radio) => {
      radio.addEventListener("change", (event) => {
        const staffId = event.target.dataset.staffId;
        this.attendanceMarked[staffId] = event.target.value;
      });
    });
  },

  renderStaffMarkOption: function (staffId, value, label, color, existingStatus) {
    return `
      <input type="radio"
             class="btn-check"
             name="staff_${staffId}"
             id="${value}_${staffId}"
             data-staff-id="${staffId}"
             value="${value}"
             ${existingStatus === value ? "checked" : ""}>
      <label class="btn btn-outline-${color}" for="${value}_${staffId}">${label}</label>
    `;
  },

  renderCurrentStaffStatus: function (staff) {
    if (Number(staff.is_on_leave || 0) === 1) {
      return '<span class="badge bg-info text-dark">On Leave</span>';
    }
    if (Number(staff.is_off_day || 0) === 1) {
      return '<span class="badge bg-secondary">Off Day</span>';
    }
    return this.renderStatusBadge(staff.effective_status || "not_marked");
  },

  markAllStaff: function (status) {
    const tbody = document.getElementById("markStaffTableBody");
    if (!tbody) {
      return;
    }

    tbody.querySelectorAll("tr[data-staff-id]").forEach((row) => {
      const readonlyLabel = row.querySelector(".text-muted.small");
      if (readonlyLabel) {
        return;
      }

      const staffId = row.dataset.staffId;
      const radio = document.getElementById(`${status}_${staffId}`);
      if (radio) {
        radio.checked = true;
        this.attendanceMarked[staffId] = status;
      }
    });
  },

  async submitStaffAttendance() {
    const date  = document.getElementById("markDate")?.value;
    const shift = this._currentShift || 'full_day';

    const attendance = Object.entries(this.attendanceMarked)
      .filter(([, status]) => ["present", "absent", "late"].includes(status))
      .map(([staffId, status]) => ({
        staff_id:        Number(staffId),
        status:          status,
        check_in_time:   document.getElementById(`checkin_${staffId}`)?.value  || null,
        absence_reason:  document.getElementById(`reason_${staffId}`)?.value   || null,
      }));

    if (!attendance.length) {
      this.notify("Please mark attendance for at least one staff member.", "warning");
      return;
    }

    try {
      await window.API.attendance.markStaff({
        date: date, shift: shift,
        attendance: attendance,
      });

      this.notify("Staff attendance submitted successfully.", "success");
      window.bootstrap?.Modal.getInstance(document.getElementById("markStaffModal"))?.hide();
      await Promise.all([this.loadTodayStaff(), this.generateReport()]);
    } catch (error) {
      this.notify(error.message || "Failed to submit staff attendance.", "error");
    }
  },

  async viewDetails(staffId, staffName) {
    try {
      const [summaryRows, historyRows] = await Promise.all([
        window.API.attendance.getStaffSummary(staffId),
        window.API.attendance.getStaffHistory(staffId),
      ]);

      const computed = this.computeStaffDetails(summaryRows || [], historyRows || []);
      const meta = document.getElementById("staffDetailsMeta");
      const present = document.getElementById("staffDetailsPresent");
      const absent = document.getElementById("staffDetailsAbsent");
      const rate = document.getElementById("staffDetailsRate");
      const body = document.getElementById("staffDetailsBody");

      if (meta) {
        meta.textContent = staffName || "Staff";
      }
      if (present) {
        present.textContent = computed.present;
      }
      if (absent) {
        absent.textContent = computed.absent;
      }
      if (rate) {
        rate.textContent = `${computed.rate.toFixed(1)}%`;
      }
      if (body) {
        if (!computed.history.length) {
          body.innerHTML =
            '<tr><td colspan="5" class="text-center text-muted py-3">No attendance history found.</td></tr>';
        } else {
          body.innerHTML = computed.history
            .map(
              (row) => `
                <tr>
                  <td>${this.formatDate(row.date)}</td>
                  <td>${this.renderStatusBadge(row.status)}</td>
                  <td>${this.escapeHtml(row.check_in_time || "-")}</td>
                  <td>${this.escapeHtml(row.check_out_time || "-")}</td>
                  <td>${this.escapeHtml(row.notes || "-")}</td>
                </tr>
              `,
            )
            .join("");
        }
      }

      const modalElement = document.getElementById("staffDetailsModal");
      if (modalElement && window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
      }
    } catch (error) {
      this.notify(error.message || "Failed to load staff attendance details.", "error");
    }
  },

  computeStaffDetails: function (summaryRows, historyRows) {
    let present = 0;
    let absent = 0;
    let late = 0;

    (Array.isArray(summaryRows) ? summaryRows : []).forEach((row) => {
      present += Number(row.present_days || 0);
      absent += Number(row.absent_days || 0);
      late += Number(row.late_days || 0);
    });

    const history = (Array.isArray(historyRows) ? historyRows : []).map((row) => ({
      ...row,
      status: row.status || "not_marked",
    }));

    if (!present && !absent && !late && history.length) {
      history.forEach((row) => {
        if (row.status === "present") {
          present++;
        } else if (row.status === "absent") {
          absent++;
        } else if (row.status === "late") {
          late++;
        }
      });
    }

    const workDays = present + absent + late;
    const rate = workDays > 0 ? ((present + late) / workDays) * 100 : 0;

    return {
      present,
      absent,
      late,
      rate,
      history,
    };
  },

  exportData: function () {
    const rows = this.reportData?.staff || [];
    if (!rows.length) {
      this.notify("There is no report data to export.", "warning");
      return;
    }

    const header = [
      "Staff Name",
      "Staff No",
      "Department",
      "Duty Type",
      "Present",
      "Absent",
      "Late",
      "On Leave",
      "Off Days",
      "Attendance Rate",
    ];

    const csvRows = [header.join(",")];
    rows.forEach((staff) => {
      const workDays = Number(staff.present || 0) + Number(staff.absent || 0) + Number(staff.late || 0);
      const attendanceRate = workDays > 0
        ? (((Number(staff.present || 0) + Number(staff.late || 0)) / workDays) * 100).toFixed(1)
        : "0.0";
      const values = [
        `${staff.first_name || ""} ${staff.last_name || ""}`.trim(),
        staff.staff_no || "",
        staff.department_name || "",
        staff.duty_type || "",
        staff.present || 0,
        staff.absent || 0,
        staff.late || 0,
        staff.on_leave || 0,
        staff.off_days || 0,
        `${attendanceRate}%`,
      ].map((value) => `"${String(value).replace(/"/g, '""')}"`);

      csvRows.push(values.join(","));
    });

    const blob = new Blob([csvRows.join("\n")], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `staff_attendance_report_${this.toDateInputValue(new Date())}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  renderStatusBadge: function (status) {
    const normalized = String(status || "not_marked").toLowerCase();
    const labels = {
      present: "Present",
      absent: "Absent",
      late: "Late",
      on_leave: "On Leave",
      off_day: "Off Day",
      not_marked: "Not Marked",
    };
    const classes = {
      present: "bg-success",
      absent: "bg-danger",
      late: "bg-warning text-dark",
      on_leave: "bg-info text-dark",
      off_day: "bg-secondary",
      not_marked: "bg-light text-dark border",
    };

    return `<span class="badge ${classes[normalized] || classes.not_marked}">${labels[normalized] || this.escapeHtml(status)}</span>`;
  },

  notify: function (message, type = "info") {
    if (window.API?.showNotification) {
      window.API.showNotification(message, type);
      return;
    }
    console[type === "error" ? "error" : "log"](message);
  },

  buildDateRange: function (dateFrom, dateTo) {
    const dates = [];
    const current = new Date(`${dateFrom}T00:00:00`);
    const end = new Date(`${dateTo}T00:00:00`);

    while (current <= end) {
      dates.push(this.toDateInputValue(current));
      current.setDate(current.getDate() + 1);
    }

    return dates;
  },

  formatDate: function (value) {
    if (!value) {
      return "-";
    }
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
      return String(value);
    }
    return date.toLocaleDateString("en-KE", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  },

  formatDateShort: function (value) {
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
      return String(value);
    }
    return date.toLocaleDateString("en-KE", {
      day: "numeric",
      month: "short",
    });
  },

  toDateInputValue: function (date) {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
      .toISOString()
      .split("T")[0];
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

  // Add minutes to a HH:MM string
  _addMinutes: function (timeStr, minutes) {
    const [h, m] = timeStr.split(':').map(Number);
    const total  = h * 60 + m + minutes;
    return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
  },

  // Render effective status using context data (richer than old renderCurrentStaffStatus)
  _renderEffectiveStatus: function (staff) {
    const st    = staff.effective_status || 'not_marked';
    const extra = [];
    if (staff.leave_type)  extra.push(`Leave: ${staff.leave_type}`);
    if (staff.duty_name && !['Off','Weekend Off'].includes(staff.duty_name)) extra.push(`Duty: ${staff.duty_name}`);
    if (staff.is_late)     extra.push(`Late ${Math.round(staff.minutes_late||0)}min`);
    if (staff.relief_staff_name) extra.push(`Relief: ${staff.relief_staff_name}`);

    const map = {
      present: 'bg-success', absent: 'bg-danger', late: 'bg-warning text-dark',
      on_leave: 'bg-info text-dark', off_day: 'bg-secondary', not_marked: 'bg-light text-dark border'
    };
    const label = {
      present:'Present', absent:'Absent', late:'Late', on_leave:'On Leave',
      off_day:'Off Day', not_marked:'Not Marked'
    };
    return `<span class="badge ${map[st]||map.not_marked}" title="${this.escapeAttribute(extra.join(' | '))}">${label[st]||st}</span>`;
  },
};

document.addEventListener("DOMContentLoaded", () => StaffAttendanceController.init());
