/**
 * Mark Attendance Page Controller
 * Manages student attendance marking workflow
 *
 * Features:
 * - Session-based attendance (Morning Class, Afternoon Class)
 * - Permission indicators for students on leave
 * - School day awareness (holidays, weekends)
 * - Bulk marking (All Present / All Absent / All Late)
 */

const markAttendanceController = {
  classes: [],
  sessions: [],
  students: [],
  selectedStreamId: null,
  selectedSessionId: null,
  selectedDate: null,
  isSchoolDay: true,
  _context: null,          // register context from /attendance/register-context
  _registerType: 'class',  // 'class' | 'boarding' | 'activity'

  init: function () {
    console.log("Mark Attendance Controller initialized");
    this.setDefaultDate();
    this.configureSharedActions();
    this.loadClasses();
    this.loadSessions();
    this.bindEvents();

    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach((t) => new bootstrap.Tooltip(t));
  },

  async configureSharedActions() {
    if (!window.AppRouteAccess?.authorizeRoute) {
      return;
    }

    const boardingLink = document.getElementById("boardingRollCallLink");
    const viewAttendanceLink = document.getElementById("viewAttendanceLink");
    try {
      const [boardingAccess, viewAttendanceAccess] = await Promise.all([
        window.AppRouteAccess.authorizeRoute("boarding_roll_call"),
        window.AppRouteAccess.authorizeRoute("view_attendance"),
      ]);
      if (boardingLink && boardingAccess?.authorized === false) {
        boardingLink.classList.add("d-none");
      }
      if (viewAttendanceLink && viewAttendanceAccess?.authorized === false) {
        viewAttendanceLink.classList.add("d-none");
      }
    } catch (error) {
      console.warn("Could not resolve shared attendance route access:", error);
    }
  },

  setDefaultDate: function () {
    const today = new Date().toISOString().split("T")[0];
    const dateInput = document.getElementById("attendanceDate");
    if (dateInput) {
      dateInput.value = today;
    }
    this.selectedDate = today;
    this.checkSchoolDay(today);
  },

  bindEvents: function () {
    const loadBtn = document.getElementById("loadStudentsBtn");
    if (loadBtn) loadBtn.addEventListener("click", () => this.loadStudents());

    const classSelect = document.getElementById("classSelect");
    if (classSelect) {
      classSelect.addEventListener("change", (e) => {
        this.selectedStreamId = e.target.value;
      });
    }

    const sessionSelect = document.getElementById("sessionSelect");
    if (sessionSelect) {
      sessionSelect.addEventListener("change", (e) => {
        this.selectedSessionId = e.target.value;
      });
    }

    const dateInput = document.getElementById("attendanceDate");
    if (dateInput) {
      dateInput.addEventListener("change", (e) => {
        this.selectedDate = e.target.value;
        this.checkSchoolDay(e.target.value);
      });
    }

    // Bulk actions
    const markPresent = document.getElementById("markAllPresent");
    if (markPresent)
      markPresent.addEventListener("click", () => this.markAll("present"));
    const markAbsent = document.getElementById("markAllAbsent");
    if (markAbsent)
      markAbsent.addEventListener("click", () => this.markAll("absent"));
    const markLate = document.getElementById("markAllLate");
    if (markLate)
      markLate.addEventListener("click", () => this.markAll("late"));

    // Submit attendance
    const submitBtn = document.getElementById("submitAttendance");
    if (submitBtn)
      submitBtn.addEventListener("click", () => this.submitAttendance());
  },

  async checkSchoolDay(date) {
    // Load full register context (replaces the old is-school-day call)
    await this._loadRegisterContext(date);
  },

  async _loadRegisterContext(date) {
    try {
      const streamId  = this.selectedStreamId  || document.getElementById("classSelect")?.value || '';
      const sessionId = this.selectedSessionId || document.getElementById("sessionSelect")?.value || '';
      const params    = `?date=${date}${streamId ? '&stream_id=' + streamId : ''}${sessionId ? '&session_id=' + sessionId : ''}`;

      const r = await window.API.apiCall(`/attendance/register-context${params}`, "GET");
      if (!r) return;
      this._context     = r;
      this.isSchoolDay  = r.is_class_day;

      // Update session dropdown to only show sessions applicable today
      if (r.applicable_sessions?.length) {
        this._updateSessionsFromContext(r.applicable_sessions);
      }

      // Show/hide the school day alert
      const alertEl = document.getElementById("schoolDayAlert");
      if (!alertEl) return;
      if (r.blocked_reason) {
        alertEl.classList.remove("d-none");
        alertEl.className = alertEl.className.replace(/alert-\w+/g, '') + (r.is_boarding_day ? ' alert-info' : ' alert-warning');
        const titleEl = document.getElementById("schoolDayAlertTitle");
        const textEl  = document.getElementById("schoolDayAlertText");
        if (titleEl) titleEl.textContent = r.day_type === 'public_holiday' ? 'Public Holiday' : (r.day_type === 'weekend' ? 'Weekend' : 'School Holiday');
        if (textEl)  textEl.textContent  = r.blocked_reason;

        // If boarding is still active (boarders in school), show boarding option
        if (r.is_boarding_day && r.applicable_sessions?.some(s => s.session_type === 'boarding')) {
          const boardingNote = document.getElementById("boardingNote") || this._createBoardingNote(alertEl);
          if (boardingNote) boardingNote.style.display = '';
        }
      } else {
        alertEl.classList.add("d-none");
        const bn = document.getElementById("boardingNote");
        if (bn) bn.style.display = 'none';
      }

      // Update header with current term/year info
      const termInfo = r.current_term;
      if (termInfo) {
        const termBadge = document.getElementById("currentTermBadge");
        if (termBadge) termBadge.textContent = `${termInfo.year_code} – ${termInfo.term_name}`;
      }

      // Show existing marks count
      if (r.existing_marks && r.total_students > 0) {
        const classMarked = r.existing_marks.class || 0;
        const info = document.getElementById("marksProgressInfo");
        if (info) info.textContent = `${classMarked}/${r.total_students} students marked`;
      }
    } catch (err) {
      console.warn('Register context load failed:', err);
    }
  },

  _createBoardingNote(alertEl) {
    const div = document.createElement('div');
    div.id = 'boardingNote';
    div.className = 'mt-2';
    div.innerHTML = '<i class="bi bi-house-door me-1"></i><strong>Boarders are present:</strong> Boarding roll call sessions are still active today.';
    alertEl.appendChild(div);
    return div;
  },

  _updateSessionsFromContext(applicableSessions) {
    const select = document.getElementById("sessionSelect");
    if (!select) return;

    // Re-populate dropdown with only today's applicable sessions
    const currentVal = select.value;
    select.innerHTML = '<option value="">-- Select Session --</option>';

    // Separate class vs boarding sessions visually
    const classOpts    = applicableSessions.filter(s => s.session_type === 'academic');
    const boardingOpts = applicableSessions.filter(s => s.session_type === 'boarding');
    const activityOpts = applicableSessions.filter(s => s.session_type === 'activity');

    const addGroup = (label, list) => {
      if (!list.length) return;
      const og = document.createElement('optgroup');
      og.label = label;
      list.forEach(s => {
        const o = document.createElement('option');
        o.value = s.id;
        o.textContent = `${s.name} (${s.start_time?.slice(0,5)} – ${s.end_time?.slice(0,5)})`;
        o.dataset.code = s.code;
        o.dataset.type = s.session_type;
        og.appendChild(o);
      });
      select.appendChild(og);
    };
    addGroup('Class Sessions', classOpts);
    addGroup('Boarding Sessions', boardingOpts);
    addGroup('Activity Sessions', activityOpts);

    // Restore previous selection or auto-select
    if (currentVal) select.value = currentVal;
    if (!select.value) this.autoSelectSession();
  },

  loadClasses: async function () {
    try {
      const response = await window.API.apiCall("/attendance/classes", "GET");
      // response is the data array: [{id, name, stream_id, student_count}, ...]
      if (response && Array.isArray(response)) {
        this.classes = response;
        this.renderClassDropdown();
      } else {
        console.error("Failed to load classes:", response);
      }
    } catch (error) {
      console.error("Error loading classes:", error);
    }
  },

  loadSessions: async function () {
    try {
      const response = await window.API.apiCall("/attendance/sessions", "GET");
      if (response && Array.isArray(response)) {
        // Store all sessions; the register context will filter to applicable ones for today
        this.sessions = response;
        this.renderSessionDropdown();
        // Re-apply context filter now that sessions are loaded
        if (this._context?.applicable_sessions?.length) {
          this._updateSessionsFromContext(this._context.applicable_sessions);
        }
      }
    } catch (error) {
      console.error("Error loading sessions:", error);
    }
  },

  renderClassDropdown: function () {
    const select = document.getElementById("classSelect");
    if (!select) return;
    select.innerHTML = '<option value="">-- Select Class --</option>';

    this.classes.forEach((cls) => {
      const option = document.createElement("option");
      option.value = cls.stream_id;
      option.textContent = `${cls.display_name || cls.name} (${cls.student_count} students)`;
      select.appendChild(option);
    });
  },

  renderSessionDropdown: function () {
    const select = document.getElementById("sessionSelect");
    if (!select) return;
    select.innerHTML = '<option value="">-- Select Session --</option>';

    this.sessions.forEach((session) => {
      const option = document.createElement("option");
      option.value = session.id;
      option.textContent = `${session.name} (${session.start_time} - ${session.end_time})`;
      option.dataset.code = session.code;
      select.appendChild(option);
    });

    // Auto-select based on current time
    this.autoSelectSession();
  },

  autoSelectSession: function () {
    const now = new Date();
    const hour = now.getHours();

    // Morning: 6-12, Afternoon: 12-18
    let targetCode = hour < 12 ? "MORNING_CLASS" : "AFTERNOON_CLASS";

    // If Saturday, try Saturday class
    if (now.getDay() === 6) {
      targetCode = "SATURDAY_CLASS";
    }

    const select = document.getElementById("sessionSelect");
    if (!select) return;
    for (let option of select.options) {
      if (option.dataset?.code === targetCode) {
        select.value = option.value;
        this.selectedSessionId = option.value;
        break;
      }
    }
  },

  loadStudents: async function () {
    const streamId = document.getElementById("classSelect")?.value;
    const sessionId = document.getElementById("sessionSelect")?.value;
    const date = document.getElementById("attendanceDate")?.value;

    if (!streamId) {
      alert("Please select a class first");
      return;
    }
    if (!sessionId) {
      alert("Please select a session");
      return;
    }

    this.selectedStreamId  = streamId;
    this.selectedSessionId = sessionId;
    this.selectedDate      = date;

    // Refresh context now that we have stream + session selected
    await this._loadRegisterContext(date);

    // If it's not a class day and the session is academic, show a prompt
    const sessionType = document.getElementById("sessionSelect")?.selectedOptions[0]?.dataset?.type || 'academic';
    if (!this.isSchoolDay && sessionType === 'academic') {
      const isBoardingDay = this._context?.is_boarding_day;
      const boardingActive = isBoardingDay && this._context?.applicable_sessions?.some(s => s.session_type === 'boarding');
      if (!boardingActive) {
        // Nothing to mark today — show info
        const emptyEl = document.getElementById("emptyState");
        if (emptyEl) {
          emptyEl.innerHTML = `<div class="text-center py-5 text-muted">
            <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
            <h5>${this._context?.blocked_reason || 'Not a school day'}</h5>
            <p>Class register is not required. No students need to be marked.</p>
          </div>`;
          emptyEl.style.display = "block";
        }
        const loadingEl = document.getElementById("loadingState");
        const attendanceCard = document.getElementById("attendanceCard");
        if (loadingEl) loadingEl.style.display = "none";
        if (attendanceCard) attendanceCard.style.display = "none";
        return;
      }
    }

    // Show loading
    const loadingEl = document.getElementById("loadingState");
    const attendanceCard = document.getElementById("attendanceCard");
    const emptyEl = document.getElementById("emptyState");
    if (loadingEl) loadingEl.style.display = "block";
    if (attendanceCard) attendanceCard.style.display = "none";
    if (emptyEl) emptyEl.style.display = "none";

    try {
      // Use session-aware endpoint
      const response = await window.API.apiCall(
        `/attendance/session-attendance`,
        "GET",
        null,
        { stream_id: streamId, session_id: sessionId, date: date },
      );

      if (loadingEl) loadingEl.style.display = "none";

      // response is: { session: {...}, date: "...", students: [...] }
      const students = response?.students || response || [];
      if (Array.isArray(students) && students.length > 0) {
        this.students = students;
        this.renderStudentsTable();
        if (attendanceCard) attendanceCard.style.display = "block";
      } else {
        if (emptyEl) emptyEl.style.display = "block";
      }
    } catch (error) {
      console.error("Error loading students:", error);
      if (loadingEl) loadingEl.style.display = "none";
      if (emptyEl) emptyEl.style.display = "block";
    }
  },

  renderStudentsTable: function () {
    const tbody = document.getElementById("studentsTableBody");
    if (!tbody) return;
    tbody.innerHTML = "";

    // Get selected class and session names
    const classSelect = document.getElementById("classSelect");
    const sessionSelect = document.getElementById("sessionSelect");
    const className =
      classSelect?.options[classSelect.selectedIndex]?.textContent ||
      "Students";
    const sessionName =
      sessionSelect?.options[sessionSelect.selectedIndex]?.textContent || "";

    const classTitle = document.getElementById("classTitle");
    const attendanceInfo = document.getElementById("attendanceInfo");
    if (classTitle) classTitle.textContent = className;
    if (attendanceInfo)
      attendanceInfo.textContent = `${sessionName} | Date: ${this.selectedDate}`;

    this.students.forEach((student, index) => {
      const status =
        student.effective_status ||
        student.attendance_status ||
        student.existing_status ||
        "present";
      const hasPermission = Boolean(
        Number(student.has_permission) || student.has_permission,
      );
      const studentId = student.student_id || student.id;

      const tr = document.createElement("tr");
      tr.className = hasPermission ? "table-warning" : "";
      tr.innerHTML = `
                <td>${index + 1}</td>
                <td><code>${student.admission_no || "N/A"}</code></td>
                <td>
                    <strong>${student.first_name} ${student.last_name}</strong>
                    ${
                      hasPermission
                        ? '<br><small class="text-warning"><i class="bi bi-exclamation-triangle"></i> Has active permission</small>'
                        : ""
                    }
                </td>
                <td>
                    <span class="badge ${this.getTypeBadgeClass(
                      student.student_type_code || student.student_type,
                    )}">
                        ${this.getTypeShortName(
                          student.student_type_code || student.student_type,
                        )}
                    </span>
                </td>
                <td>
                    ${
                      hasPermission
                        ? `
                        <span class="badge bg-warning text-dark" title="${
                          student.permission_reason || "Active permission"
                        }">
                            ${student.permission_type_code || "PERM"}
                        </span>
                    `
                        : '<span class="text-muted">-</span>'
                    }
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group" data-student-id="${studentId}">
                        <input type="radio" class="btn-check" name="status_${studentId}"
                               id="present_${studentId}" value="present" ${
                                 status === "present" ? "checked" : ""
                               }>
                        <label class="btn btn-outline-success" for="present_${studentId}">
                            <i class="bi bi-check"></i>
                        </label>

                        <input type="radio" class="btn-check" name="status_${studentId}"
                               id="absent_${studentId}" value="absent" ${
                                 status === "absent" ? "checked" : ""
                               }>
                        <label class="btn btn-outline-danger" for="absent_${studentId}">
                            <i class="bi bi-x"></i>
                        </label>

                        <input type="radio" class="btn-check" name="status_${studentId}"
                               id="late_${studentId}" value="late" ${
                                 status === "late" ? "checked" : ""
                               }>
                        <label class="btn btn-outline-warning" for="late_${studentId}">
                            <i class="bi bi-clock"></i>
                        </label>

                        ${
                          hasPermission
                            ? `
                            <input type="radio" class="btn-check" name="status_${studentId}"
                                   id="permission_${studentId}" value="permission" ${
                                     status === "permission" ? "checked" : ""
                                   }>
                            <label class="btn btn-outline-info" for="permission_${studentId}">
                                <i class="bi bi-door-open"></i>
                            </label>
                        `
                            : ""
                        }
                    </div>
                </td>
            `;
      tbody.appendChild(tr);
    });

    // Add change listeners to update summary
    tbody.querySelectorAll('input[type="radio"]').forEach((radio) => {
      radio.addEventListener("change", () => this.updateSummary());
    });

    this.updateSummary();
  },

  getTypeBadgeClass: function (type) {
    switch (String(type || "").toUpperCase()) {
      case "BOARD":
      case "FULL_BOARDER":
      case "FULL BOARDER":
        return "bg-primary";
      case "WEEKLY":
      case "WEEKLY_BOARDER":
      case "WEEKLY BOARDER":
        return "bg-info";
      default:
        return "bg-secondary";
    }
  },

  getTypeShortName: function (type) {
    switch (String(type || "").toUpperCase()) {
      case "BOARD":
      case "FULL_BOARDER":
      case "FULL BOARDER":
        return "FB";
      case "WEEKLY":
      case "WEEKLY_BOARDER":
      case "WEEKLY BOARDER":
        return "WB";
      case "DAY":
        return "Day";
      default:
        return type || "Day";
    }
  },

  markAll: function (status) {
    this.students.forEach((student) => {
      const studentId = student.student_id || student.id;
      // Students with active permissions get marked as 'permission' when marking all absent
      if (status === "absent" && student.has_permission) {
        const permRadio = document.getElementById(`permission_${studentId}`);
        if (permRadio) {
          permRadio.checked = true;
          return;
        }
      }
      const radio = document.getElementById(`${status}_${studentId}`);
      if (radio) radio.checked = true;
    });
    this.updateSummary();
  },

  updateSummary: function () {
    let present = 0,
      absent = 0,
      late = 0,
      permission = 0;

    this.students.forEach((student) => {
      const studentId = student.student_id || student.id;
      const presentRadio = document.getElementById(`present_${studentId}`);
      const absentRadio = document.getElementById(`absent_${studentId}`);
      const lateRadio = document.getElementById(`late_${studentId}`);
      const permissionRadio = document.getElementById(
        `permission_${studentId}`,
      );

      if (presentRadio?.checked) present++;
      else if (absentRadio?.checked) absent++;
      else if (lateRadio?.checked) late++;
      else if (permissionRadio?.checked) permission++;
    });

    const el = (id, text) => {
      const e = document.getElementById(id);
      if (e) e.textContent = text;
    };
    el("presentCount", `Present: ${present}`);
    el("absentCount", `Absent: ${absent}`);
    el("lateCount", `Late: ${late}`);
    el("permissionCount", `Permission: ${permission}`);
  },

  submitAttendance: async function () {
    if (!this.selectedStreamId || !this.selectedDate) {
      alert("Please select a class and date first");
      return;
    }
    if (!this.selectedSessionId) {
      alert("Please select an attendance session");
      return;
    }

    // Collect attendance data
    const attendance = this.students.map((student) => {
      const studentId = student.student_id || student.id;
      const absentRadio = document.getElementById(`absent_${studentId}`);
      const lateRadio = document.getElementById(`late_${studentId}`);
      const permissionRadio = document.getElementById(
        `permission_${studentId}`,
      );

      let status = "present";
      if (absentRadio?.checked) status = "absent";
      else if (lateRadio?.checked) status = "late";
      else if (permissionRadio?.checked) status = "permission";

      return {
        student_id: studentId,
        status: status,
      };
    });

    const submitBtn = document.getElementById("submitAttendance");
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    }

    // Guard: if not a school day and selected session is academic → warn but allow boarding
    const sessionSelect = document.getElementById("sessionSelect");
    const sessionType   = sessionSelect?.selectedOptions[0]?.dataset?.type || 'academic';
    if (!this.isSchoolDay && sessionType === 'academic') {
      const force = confirm(
        (this._context?.blocked_reason || 'This is not a regular school day') +
        '\n\nAre you sure you want to mark class attendance today?'
      );
      if (!force) { if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Submit Attendance'; } return; }
    }
    // Determine register_type from selected session
    this._registerType = sessionType === 'boarding' ? 'boarding' : (sessionType === 'activity' ? 'activity' : 'class');

    try {
      const response = await window.API.apiCall(
        "/attendance/mark-session",
        "POST",
        {
          stream_id:     this.selectedStreamId,
          session_id:    this.selectedSessionId,
          date:          this.selectedDate,
          register_type: this._registerType,
          attendance:    attendance,
        },
      );

      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML =
          '<i class="bi bi-check-circle me-2"></i>Submit Attendance';
      }

      // response is the data directly: { created, updated, excused, total, session_id, date }
      if (response && response.total !== undefined) {
        alert(
          `Attendance submitted successfully!\n\nCreated: ${
            response.created || 0
          }\nUpdated: ${response.updated || 0}\nTotal: ${response.total || 0}`,
        );
        // Reload to show updated status
        this.loadStudents();
      } else {
        alert("Attendance submitted.");
        this.loadStudents();
      }
    } catch (error) {
      console.error("Error submitting attendance:", error);
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML =
          '<i class="bi bi-check-circle me-2"></i>Submit Attendance';
      }
      alert(
        "Failed to submit attendance: " +
          (error.message || "Please try again."),
      );
    }
  },
};

// Initialize on page load
document.addEventListener("DOMContentLoaded", () =>
  markAttendanceController.init()
);
