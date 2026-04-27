const BoardingRollCall = {
  students: [],
  dormitories: [],
  sessions: [],
  selectedDormitory: "",
  selectedSession: "",
  selectedDate: "",
  attendanceMarked: {},
  loadedRegister: null,
  elements: {},

  init: async function () {
    this.cacheElements();
    this.bindEvents();

    const today = this.toDateInputValue(new Date());
    if (this.elements.rollCallDate) {
      this.elements.rollCallDate.value = today;
    }
    this.selectedDate = today;
    this.updateHeader();

    await Promise.all([
      this.configureSharedActions(),
      this.loadDormitories(),
      this.loadSessions(),
    ]);

    await this.loadBoardingSummary();
  },

  cacheElements: function () {
    this.elements = {
      dormitorySelect: document.getElementById("dormitorySelect"),
      sessionSelect: document.getElementById("sessionSelect"),
      rollCallDate: document.getElementById("rollCallDate"),
      loadStudentsBtn: document.getElementById("loadStudentsBtn"),
      refreshBtn: document.getElementById("refreshBtn"),
      markAllPresent: document.getElementById("markAllPresent"),
      markAllAbsent: document.getElementById("markAllAbsent"),
      submitRollCall: document.getElementById("submitRollCall"),
      studentsTableBody: document.getElementById("studentsTableBody"),
      dormitoryTitle: document.getElementById("dormitoryTitle"),
      sessionBadge: document.getElementById("sessionBadge"),
      dateDisplay: document.getElementById("dateDisplay"),
      loadingState: document.getElementById("loadingState"),
      emptyState: document.getElementById("emptyState"),
      rollCallCard: document.getElementById("rollCallCard"),
      quickStats: document.getElementById("quickStats"),
      statTotal: document.getElementById("statTotal"),
      statPresent: document.getElementById("statPresent"),
      statAbsent: document.getElementById("statAbsent"),
      statPermission: document.getElementById("statPermission"),
      statSickBay: document.getElementById("statSickBay"),
      summaryCard: document.getElementById("summaryCard"),
      summaryContent: document.getElementById("summaryContent"),
      historyLink: document.getElementById("viewAttendanceHistoryLink"),
    };
  },

  bindEvents: function () {
    this.elements.dormitorySelect?.addEventListener("change", async (event) => {
      this.selectedDormitory = event.target.value;
      this.resetRegisterView();
      this.updateHeader();
      await this.loadBoardingSummary();
    });

    this.elements.sessionSelect?.addEventListener("change", async (event) => {
      this.selectedSession = event.target.value;
      this.resetRegisterView();
      this.updateHeader();
      await this.loadBoardingSummary();
    });

    this.elements.rollCallDate?.addEventListener("change", async (event) => {
      this.selectedDate = event.target.value;
      this.resetRegisterView();
      this.updateHeader();
      await this.loadSessions();
      await this.loadBoardingSummary();
    });

    this.elements.loadStudentsBtn?.addEventListener("click", () => {
      this.loadStudents();
    });

    this.elements.refreshBtn?.addEventListener("click", () => {
      this.refresh();
    });

    this.elements.markAllPresent?.addEventListener("click", () => {
      this.markAll("present");
    });

    this.elements.markAllAbsent?.addEventListener("click", () => {
      this.markAll("absent");
    });

    this.elements.submitRollCall?.addEventListener("click", () => {
      this.submitRollCall();
    });
  },

  configureSharedActions: async function () {
    if (!this.elements.historyLink || !window.AppRouteAccess?.authorizeRoute) {
      return;
    }

    try {
      const access = await window.AppRouteAccess.authorizeRoute(
        "view_attendance",
      );
      if (access?.authorized === false) {
        this.elements.historyLink.classList.add("d-none");
      } else {
        this.elements.historyLink.classList.remove("d-none");
      }
    } catch (error) {
      console.warn("Could not resolve view attendance route access:", error);
    }
  },

  loadDormitories: async function () {
    try {
      const dormitories = await window.API.attendance.getDormitories();
      this.dormitories = Array.isArray(dormitories) ? dormitories : [];
      this.renderDormitorySelect();
    } catch (error) {
      console.error("Failed to load dormitories:", error);
      this.notify(error.message || "Failed to load dormitories", "error");
    }
  },

  loadSessions: async function () {
    try {
      const sessions = await window.API.attendance.getSessions({
        type: "boarding",
        day: this.getDayName(this.selectedDate),
      });

      this.sessions = Array.isArray(sessions) ? sessions : [];
      if (
        this.selectedSession &&
        !this.sessions.some(
          (session) => String(session.id) === String(this.selectedSession),
        )
      ) {
        this.selectedSession = "";
      }

      this.renderSessionSelect();
      this.updateHeader();
    } catch (error) {
      console.error("Failed to load boarding sessions:", error);
      this.notify(error.message || "Failed to load sessions", "error");
    }
  },

  renderDormitorySelect: function () {
    if (!this.elements.dormitorySelect) {
      return;
    }

    this.elements.dormitorySelect.innerHTML =
      '<option value="">-- Select Dormitory --</option>';

    this.dormitories.forEach((dormitory) => {
      const option = document.createElement("option");
      option.value = dormitory.id;
      option.textContent = `${dormitory.name} (${Number(dormitory.student_count || 0)} students)`;
      if (String(this.selectedDormitory) === String(dormitory.id)) {
        option.selected = true;
      }
      this.elements.dormitorySelect.appendChild(option);
    });

    if (!this.selectedDormitory && this.dormitories.length === 1) {
      this.selectedDormitory = String(this.dormitories[0].id);
      this.elements.dormitorySelect.value = this.selectedDormitory;
    }

    this.updateHeader();
  },

  renderSessionSelect: function () {
    if (!this.elements.sessionSelect) {
      return;
    }

    this.elements.sessionSelect.innerHTML =
      '<option value="">-- Select Session --</option>';

    this.sessions.forEach((session) => {
      const option = document.createElement("option");
      option.value = session.id;
      option.textContent = `${session.name} (${session.start_time} - ${session.end_time})`;
      option.dataset.code = session.code || "";
      if (String(this.selectedSession) === String(session.id)) {
        option.selected = true;
      }
      this.elements.sessionSelect.appendChild(option);
    });

    if (!this.selectedSession && this.sessions.length === 1) {
      this.selectedSession = String(this.sessions[0].id);
      this.elements.sessionSelect.value = this.selectedSession;
    }

    this.updateHeader();
  },

  updateHeader: function (dormitoryOverride = null) {
    const selectedDormitory =
      dormitoryOverride ||
      this.dormitories.find(
        (dormitory) => String(dormitory.id) === String(this.selectedDormitory),
      );
    const selectedSession = this.sessions.find(
      (session) => String(session.id) === String(this.selectedSession),
    );

    if (this.elements.dormitoryTitle) {
      this.elements.dormitoryTitle.textContent =
        selectedDormitory?.name || "Boarding Roll Call";
    }

    if (this.elements.sessionBadge) {
      this.elements.sessionBadge.textContent =
        selectedSession?.name || "Select Session";
    }

    if (this.elements.dateDisplay) {
      this.elements.dateDisplay.textContent = this.selectedDate
        ? this.formatDate(this.selectedDate)
        : "Select Date";
    }
  },

  loadStudents: async function (options = {}) {
    if (!this.selectedDormitory) {
      this.notify("Please select a dormitory", "warning");
      return;
    }
    if (!this.selectedSession) {
      this.notify("Please select a session", "warning");
      return;
    }
    if (!this.selectedDate) {
      this.notify("Please select a date", "warning");
      return;
    }

    const skipSchoolDayCheck = options.skipSchoolDayCheck === true;
    const selectedSession = this.sessions.find(
      (session) => String(session.id) === String(this.selectedSession),
    );

    if (!skipSchoolDayCheck && selectedSession?.code !== "WEEKEND_ROLL_CALL") {
      const proceed = await this.checkSchoolDay();
      if (!proceed) {
        return;
      }
    }

    this.showLoading(true);

    try {
      const response = await window.API.attendance.getDormitoryStudents({
        dormitory_id: this.selectedDormitory,
        session_id: this.selectedSession,
        date: this.selectedDate,
      });

      this.students = Array.isArray(response?.students)
        ? response.students.map((student) => this.normalizeStudent(student))
        : [];
      this.attendanceMarked = {};

      this.students.forEach((student) => {
        if (student.current_status) {
          this.attendanceMarked[student.id] = {
            status: student.current_status,
            notes: student.notes || "",
          };
        }
      });

      this.loadedRegister = {
        dormitoryId: this.selectedDormitory,
        sessionId: this.selectedSession,
        date: this.selectedDate,
      };

      this.updateHeader(response?.dormitory || null);
      this.renderStudentsTable();
      this.updateStats();
      this.showRollCallCard(true);
      await this.loadBoardingSummary();
    } catch (error) {
      console.error("Failed to load dormitory students:", error);
      this.notify(error.message || "Failed to load students", "error");
      this.resetRegisterView();
    } finally {
      this.showLoading(false);
    }
  },

  normalizeStudent: function (student) {
    return {
      ...student,
      id: Number(student.id),
      permission_id: student.permission_id ? Number(student.permission_id) : null,
    };
  },

  checkSchoolDay: async function () {
    try {
      const schoolDay = await window.API.attendance.isSchoolDay({
        date: this.selectedDate,
      });

      if (schoolDay?.is_school_day === false) {
        const reason =
          schoolDay.reason ||
          schoolDay.calendar_event?.event_name ||
          "non-school day";
        return window.confirm(
          `${this.formatDate(this.selectedDate)} is marked as "${reason}". Continue with roll call anyway?`,
        );
      }
    } catch (error) {
      console.warn("Failed to validate school day:", error);
    }

    return true;
  },

  renderStudentsTable: function () {
    if (!this.elements.studentsTableBody) {
      return;
    }

    if (!this.students.length) {
      this.elements.studentsTableBody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-4 text-muted">
            <i class="bi bi-inbox display-6 d-block mb-2"></i>
            No active students were found in this dormitory.
          </td>
        </tr>
      `;
      return;
    }

    this.elements.studentsTableBody.innerHTML = this.students
      .map((student, index) => {
        const currentStatus = this.attendanceMarked[student.id]?.status || "";
        const hasPermission =
          !!student.permission_id || currentStatus === "permission";

        return `
          <tr class="student-row ${hasPermission ? "has-permission" : ""} ${this.getStatusRowClass(currentStatus)}" data-student-id="${student.id}">
            <td>${index + 1}</td>
            <td><strong>${this.escapeHtml(student.admission_no || "N/A")}</strong></td>
            <td>
              <div class="d-flex align-items-center">
                <div class="avatar bg-secondary text-white rounded-circle me-2 d-flex align-items-center justify-content-center"
                     style="width: 35px; height: 35px; font-size: 0.8rem;">
                  ${this.escapeHtml(this.getInitials(student.first_name, student.last_name))}
                </div>
                <div>
                  <div class="fw-semibold">${this.escapeHtml(`${student.first_name || ""} ${student.last_name || ""}`.trim() || "Unnamed student")}</div>
                  <small class="text-muted">${this.escapeHtml(student.check_time ? `Last marked ${student.check_time}` : "Awaiting roll call")}</small>
                </div>
              </div>
            </td>
            <td>${this.escapeHtml(student.class_name || "-")}</td>
            <td>${this.escapeHtml(student.bed_number || "-")}</td>
            <td>${this.renderPermissionCell(student)}</td>
            <td>
              <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Attendance status">
                ${this.renderStatusOption(student.id, "present", "btn-outline-success", "bi-check-lg", "Present", currentStatus === "present")}
                ${this.renderStatusOption(student.id, "absent", "btn-outline-danger", "bi-x-lg", "Absent", currentStatus === "absent")}
                ${
                  hasPermission
                    ? this.renderStatusOption(
                        student.id,
                        "permission",
                        "btn-outline-warning",
                        "bi-door-open",
                        "On Permission",
                        currentStatus === "permission",
                      )
                    : ""
                }
                ${this.renderStatusOption(student.id, "sick_bay", "btn-outline-info", "bi-hospital", "Sick Bay", currentStatus === "sick_bay", "sick")}
              </div>
            </td>
          </tr>
        `;
      })
      .join("");

    this.elements.studentsTableBody
      .querySelectorAll('input[type="radio"]')
      .forEach((radio) => {
        radio.addEventListener("change", (event) => {
          const studentId = Number(event.target.dataset.studentId);
          const status = event.target.value;
          const student = this.students.find((row) => row.id === studentId);
          if (!student) {
            return;
          }

          this.attendanceMarked[studentId] = {
            status,
            notes: this.attendanceMarked[studentId]?.notes || student.notes || "",
          };

          const row = event.target.closest("tr");
          if (row) {
            this.updateRowAppearance(row, status);
          }
          this.updateStats();
        });
      });
  },

  renderPermissionCell: function (student) {
    if (!student.permission_id) {
      return '<span class="text-muted">-</span>';
    }

    const permissionType = this.escapeHtml(student.permission_type || "Approved");
    const permissionUntil = student.permission_until
      ? this.escapeHtml(this.formatDate(student.permission_until))
      : "Active";

    return `
      <span class="badge bg-warning text-dark">
        <i class="bi bi-door-open me-1"></i>${permissionType}
      </span>
      <div class="small text-muted mt-1">Until ${permissionUntil}</div>
    `;
  },

  renderStatusOption: function (
    studentId,
    status,
    buttonClass,
    iconClass,
    label,
    checked,
    idPrefix = null,
  ) {
    const inputId = `${idPrefix || status}_${studentId}`;
    return `
      <input
        type="radio"
        class="btn-check"
        name="status_${studentId}"
        id="${inputId}"
        value="${status}"
        data-student-id="${studentId}"
        ${checked ? "checked" : ""}
      >
      <label class="btn ${buttonClass}" for="${inputId}" title="${label}">
        <i class="bi ${iconClass}"></i>
      </label>
    `;
  },

  markAll: function (status) {
    if (!this.students.length) {
      this.notify("Load students before using bulk actions", "warning");
      return;
    }

    this.students.forEach((student) => {
      const nextStatus =
        status === "absent" && student.permission_id ? "permission" : status;
      this.attendanceMarked[student.id] = {
        status: nextStatus,
        notes: this.attendanceMarked[student.id]?.notes || student.notes || "",
      };
      this.applyStudentSelection(student.id, nextStatus);
    });

    this.updateStats();
  },

  applyStudentSelection: function (studentId, status) {
    const inputId =
      status === "sick_bay" ? `sick_${studentId}` : `${status}_${studentId}`;
    const radio = document.getElementById(inputId);
    if (radio) {
      radio.checked = true;
    }

    const row = this.elements.studentsTableBody?.querySelector(
      `tr[data-student-id="${studentId}"]`,
    );
    if (row) {
      this.updateRowAppearance(row, status);
    }
  },

  updateRowAppearance: function (row, status) {
    row.classList.remove(
      "status-present",
      "status-absent",
      "status-permission",
      "status-sick-bay",
    );

    const statusClass = this.getStatusRowClass(status);
    if (statusClass) {
      row.classList.add(statusClass);
    }
  },

  getStatusRowClass: function (status) {
    return status ? `status-${status.replace("_", "-")}` : "";
  },

  updateStats: function () {
    const stats = {
      total: this.students.length,
      present: 0,
      absent: 0,
      permission: 0,
      sick_bay: 0,
    };

    Object.values(this.attendanceMarked).forEach((record) => {
      if (
        record?.status &&
        Object.prototype.hasOwnProperty.call(stats, record.status)
      ) {
        stats[record.status] += 1;
      }
    });

    if (this.elements.statTotal) {
      this.elements.statTotal.textContent = stats.total;
    }
    if (this.elements.statPresent) {
      this.elements.statPresent.textContent = stats.present;
    }
    if (this.elements.statAbsent) {
      this.elements.statAbsent.textContent = stats.absent;
    }
    if (this.elements.statPermission) {
      this.elements.statPermission.textContent = stats.permission;
    }
    if (this.elements.statSickBay) {
      this.elements.statSickBay.textContent = stats.sick_bay;
    }
    if (this.elements.quickStats) {
      this.elements.quickStats.style.display = stats.total ? "block" : "none";
    }
  },

  submitRollCall: async function () {
    if (!this.students.length) {
      this.notify("Load a dormitory register before submitting roll call", "warning");
      return;
    }

    const unmarkedStudents = this.students.filter(
      (student) => !this.attendanceMarked[student.id]?.status,
    );

    if (unmarkedStudents.length) {
      const proceed = window.confirm(
        `${unmarkedStudents.length} students have not been marked. Continue and auto-mark them as absent or permission where applicable?`,
      );
      if (!proceed) {
        return;
      }

      unmarkedStudents.forEach((student) => {
        const fallbackStatus = student.permission_id ? "permission" : "absent";
        this.attendanceMarked[student.id] = {
          status: fallbackStatus,
          notes: student.permission_id
            ? "Auto-marked from approved permission"
            : "Not marked during roll call",
        };
        this.applyStudentSelection(student.id, fallbackStatus);
      });
      this.updateStats();
    }

    const attendance = this.students.map((student) => ({
      student_id: student.id,
      status: this.attendanceMarked[student.id]?.status || "absent",
      notes: this.attendanceMarked[student.id]?.notes || null,
    }));

    this.setSubmitting(true);

    try {
      const response = await window.API.attendance.markBoarding({
        dormitory_id: Number(this.selectedDormitory),
        session_id: Number(this.selectedSession),
        date: this.selectedDate,
        attendance,
      });

      const total = Number(response?.total || attendance.length);
      this.notify(`Saved boarding roll call for ${total} students.`, "success");
      await this.loadStudents({ skipSchoolDayCheck: true });
    } catch (error) {
      console.error("Failed to submit boarding roll call:", error);
      this.notify(error.message || "Failed to submit roll call", "error");
    } finally {
      this.setSubmitting(false);
    }
  },

  loadBoardingSummary: async function () {
    if (!this.selectedDate || !this.elements.summaryContent) {
      return;
    }

    try {
      const response = await window.API.attendance.getBoardingSummary({
        date: this.selectedDate,
      });

      let rows = Array.isArray(response?.summary) ? response.summary : [];

      if (this.selectedDormitory) {
        rows = rows.filter(
          (row) => String(row.dormitory_id) === String(this.selectedDormitory),
        );
      }

      if (this.selectedSession) {
        rows = rows.filter(
          (row) => String(row.session_id) === String(this.selectedSession),
        );
      }

      this.renderSummary(rows);
    } catch (error) {
      console.error("Failed to load boarding summary:", error);
      this.renderSummary([]);
    }
  },

  renderSummary: function (rows) {
    if (!this.elements.summaryCard || !this.elements.summaryContent) {
      return;
    }

    this.elements.summaryCard.style.display = "block";

    if (!Array.isArray(rows) || !rows.length) {
      this.elements.summaryContent.innerHTML = `
        <div class="alert alert-light border text-muted mb-0">
          No boarding summary records were found for ${this.escapeHtml(this.formatDate(this.selectedDate))}.
        </div>
      `;
      return;
    }

    this.elements.summaryContent.innerHTML = `
      <div class="row g-3">
        ${rows
          .map((row) => {
            const total = Number(row.total_students || 0);
            const present = Number(row.present || 0);
            const absent = Number(row.absent || 0);
            const permission = Number(row.on_permission || 0);
            const sickBay = Number(row.sick_bay || 0);
            const rate = total ? ((present / total) * 100).toFixed(1) : "0.0";

            return `
              <div class="col-md-6 col-xl-4">
                <div class="card h-100 border-0 shadow-sm">
                  <div class="card-header bg-white">
                    <div class="fw-semibold">${this.escapeHtml(row.dormitory_name || "-")}</div>
                    <div class="small text-muted">${this.escapeHtml(row.session_name || "-")}</div>
                  </div>
                  <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                      <span>Total Students</span>
                      <strong>${total}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-success">
                      <span>Present</span>
                      <strong>${present}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-danger">
                      <span>Absent</span>
                      <strong>${absent}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-warning">
                      <span>Permission</span>
                      <strong>${permission}</strong>
                    </div>
                    <div class="d-flex justify-content-between text-info">
                      <span>Sick Bay</span>
                      <strong>${sickBay}</strong>
                    </div>
                  </div>
                  <div class="card-footer bg-white d-flex justify-content-between small">
                    <span class="text-muted">${this.escapeHtml(row.code || "")}</span>
                    <span class="fw-semibold">${rate}% present</span>
                  </div>
                </div>
              </div>
            `;
          })
          .join("")}
      </div>
    `;
  },

  showLoading: function (show) {
    if (this.elements.loadingState) {
      this.elements.loadingState.style.display = show ? "block" : "none";
    }
    if (this.elements.loadStudentsBtn) {
      this.elements.loadStudentsBtn.disabled = show;
    }
    if (this.elements.refreshBtn) {
      this.elements.refreshBtn.disabled = show;
    }
    if (show && this.elements.emptyState) {
      this.elements.emptyState.style.display = "none";
    }
  },

  showRollCallCard: function (show) {
    if (this.elements.rollCallCard) {
      this.elements.rollCallCard.style.display = show ? "block" : "none";
    }
    if (this.elements.emptyState) {
      this.elements.emptyState.style.display = show ? "none" : "block";
    }
  },

  resetRegisterView: function () {
    this.students = [];
    this.attendanceMarked = {};
    this.loadedRegister = null;

    if (this.elements.studentsTableBody) {
      this.elements.studentsTableBody.innerHTML = "";
    }
    if (this.elements.quickStats) {
      this.elements.quickStats.style.display = "none";
    }

    this.showRollCallCard(false);
  },

  refresh: async function () {
    await this.loadSessions();

    if (this.selectedDormitory && this.selectedSession) {
      await this.loadStudents({ skipSchoolDayCheck: true });
      return;
    }

    await this.loadBoardingSummary();
  },

  setSubmitting: function (submitting) {
    if (!this.elements.submitRollCall) {
      return;
    }

    if (!this.elements.submitRollCall.dataset.defaultLabel) {
      this.elements.submitRollCall.dataset.defaultLabel =
        this.elements.submitRollCall.innerHTML;
    }

    this.elements.submitRollCall.disabled = submitting;
    this.elements.submitRollCall.innerHTML = submitting
      ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...'
      : this.elements.submitRollCall.dataset.defaultLabel;
  },

  getInitials: function (firstName, lastName) {
    return `${(firstName || "")[0] || ""}${(lastName || "")[0] || ""}`.toUpperCase();
  },

  getDayName: function (dateString) {
    const date = dateString ? new Date(`${dateString}T00:00:00`) : new Date();
    return date.toLocaleDateString("en-US", { weekday: "long" });
  },

  toDateInputValue: function (date) {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
      .toISOString()
      .split("T")[0];
  },

  formatDate: function (dateString) {
    if (!dateString) {
      return "-";
    }

    const date = new Date(`${dateString}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
      return dateString;
    }

    return date.toLocaleDateString("en-GB", {
      day: "numeric",
      month: "long",
      year: "numeric",
    });
  },

  escapeHtml: function (value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#39;");
  },

  notify: function (message, type = "info") {
    let toastContainer = document.getElementById("toastContainer");
    if (!toastContainer) {
      toastContainer = document.createElement("div");
      toastContainer.id = "toastContainer";
      toastContainer.className =
        "toast-container position-fixed bottom-0 end-0 p-3";
      document.body.appendChild(toastContainer);
    }

    const toastId = `toast_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
    const bgClass =
      type === "success"
        ? "bg-success"
        : type === "error"
          ? "bg-danger"
          : type === "warning"
            ? "bg-warning"
            : "bg-info";

    toastContainer.insertAdjacentHTML(
      "beforeend",
      `
        <div id="${toastId}" class="toast ${bgClass} text-white" role="alert" aria-live="assertive" aria-atomic="true">
          <div class="toast-body d-flex justify-content-between align-items-center gap-3">
            <span>${this.escapeHtml(message)}</span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
        </div>
      `,
    );

    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3500 });
    toast.show();
    toastElement.addEventListener("hidden.bs.toast", () => {
      toastElement.remove();
    });
  },
};

document.addEventListener("DOMContentLoaded", () => {
  window.boardingRollCall = BoardingRollCall;
  BoardingRollCall.init();
});
