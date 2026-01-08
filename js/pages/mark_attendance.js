/**
 * Mark Attendance Page Controller
 * Manages student attendance marking workflow
 * 
 * Enhanced with:
 * - Session-based attendance (Morning Class, Afternoon Class)
 * - Permission indicators for students on leave
 * - School day awareness (holidays, weekends)
 */

const markAttendanceController = {
  classes: [],
  sessions: [],
  students: [],
  selectedStreamId: null,
  selectedSessionId: null,
  selectedDate: null,
  isSchoolDay: true,

  init: function () {
    console.log("✅ Mark Attendance Controller initialized");
    this.setDefaultDate();
    this.loadClasses();
    this.loadSessions();
    this.bindEvents();

    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach((t) => new bootstrap.Tooltip(t));
  },

  setDefaultDate: function () {
    const today = new Date().toISOString().split("T")[0];
    document.getElementById("attendanceDate").value = today;
    this.selectedDate = today;
    this.checkSchoolDay(today);
  },

  bindEvents: function () {
    // Load students button
    document.getElementById("loadStudentsBtn").addEventListener("click", () => {
      this.loadStudents();
    });

    // Class select change
    document.getElementById("classSelect").addEventListener("change", (e) => {
      this.selectedStreamId = e.target.value;
    });

    // Session select change
    document.getElementById("sessionSelect").addEventListener("change", (e) => {
      this.selectedSessionId = e.target.value;
    });

    // Date change
    document
      .getElementById("attendanceDate")
      .addEventListener("change", (e) => {
        this.selectedDate = e.target.value;
        this.checkSchoolDay(e.target.value);
      });

    // Bulk actions
    document.getElementById("markAllPresent").addEventListener("click", () => {
      this.markAll("present");
    });
    document.getElementById("markAllAbsent").addEventListener("click", () => {
      this.markAll("absent");
    });
    document.getElementById("markAllLate").addEventListener("click", () => {
      this.markAll("late");
    });

    // Submit attendance
    document
      .getElementById("submitAttendance")
      .addEventListener("click", () => {
        this.submitAttendance();
      });
  },

  async checkSchoolDay(date) {
    try {
      const response = await window.API.apiCall(
        `/api/?route=attendance&action=is-school-day&date=${date}`,
        "GET"
      );
      if (response && response.success && response.data) {
        this.isSchoolDay = response.data.is_school_day;
        const alertEl = document.getElementById("schoolDayAlert");

        if (!this.isSchoolDay && response.data.calendar_event) {
          alertEl.classList.remove("d-none");
          document.getElementById("schoolDayAlertTitle").textContent =
            response.data.calendar_event.event_type || "Non-School Day";
          document.getElementById("schoolDayAlertText").textContent =
            response.data.calendar_event.event_name || "No classes scheduled";
        } else {
          alertEl.classList.add("d-none");
        }
      }
    } catch (error) {
      console.warn("Could not check school day status:", error);
    }
  },

  loadClasses: async function () {
    try {
      const response = await window.API.apiCall(
        "/api/?route=attendance&action=classes",
        "GET"
      );
      if (response && response.success) {
        this.classes = response.data || [];
        this.renderClassDropdown();
      } else {
        console.error("Failed to load classes:", response?.message);
      }
    } catch (error) {
      console.error("Error loading classes:", error);
    }
  },

  loadSessions: async function () {
    try {
      const response = await window.API.apiCall(
        "/api/?route=attendance&action=sessions",
        "GET"
      );
      if (response && response.success) {
        // Filter to academic sessions only (for class teachers)
        this.sessions = (response.data || []).filter((s) =>
          ["MORNING_CLASS", "AFTERNOON_CLASS", "SATURDAY_CLASS"].includes(
            s.session_code
          )
        );
        this.renderSessionDropdown();
      } else {
        console.error("Failed to load sessions:", response?.message);
      }
    } catch (error) {
      console.error("Error loading sessions:", error);
    }
  },

  renderClassDropdown: function () {
    const select = document.getElementById("classSelect");
    select.innerHTML = '<option value="">-- Select Class --</option>';

    this.classes.forEach((cls) => {
      const option = document.createElement("option");
      option.value = cls.stream_id;
      option.textContent = `${cls.name} (${cls.student_count} students)`;
      select.appendChild(option);
    });
  },

  renderSessionDropdown: function () {
    const select = document.getElementById("sessionSelect");
    select.innerHTML = '<option value="">-- Select Session --</option>';

    this.sessions.forEach((session) => {
      const option = document.createElement("option");
      option.value = session.id;
      option.textContent = `${session.session_name} (${session.start_time} - ${session.end_time})`;
      option.dataset.code = session.session_code;
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

    // If weekend, try Saturday class
    if (now.getDay() === 6) {
      targetCode = "SATURDAY_CLASS";
    }

    const select = document.getElementById("sessionSelect");
    for (let option of select.options) {
      if (option.dataset?.code === targetCode) {
        select.value = option.value;
        this.selectedSessionId = option.value;
        break;
      }
    }
  },

  loadStudents: async function () {
    const streamId = document.getElementById("classSelect").value;
    const sessionId = document.getElementById("sessionSelect").value;
    const date = document.getElementById("attendanceDate").value;

    if (!streamId) {
      alert("Please select a class first");
      return;
    }
    if (!sessionId) {
      alert("Please select a session");
      return;
    }

    this.selectedStreamId = streamId;
    this.selectedSessionId = sessionId;
    this.selectedDate = date;

    // Show loading
    document.getElementById("loadingState").style.display = "block";
    document.getElementById("attendanceCard").style.display = "none";
    document.getElementById("emptyState").style.display = "none";

    try {
      // Use session-aware endpoint
      const response = await window.API.apiCall(
        `/api/?route=attendance&action=session-attendance&stream_id=${streamId}&session_id=${sessionId}&date=${date}`,
        "GET"
      );

      document.getElementById("loadingState").style.display = "none";

      if (response && response.success) {
        this.students = response.data || [];
        if (this.students.length > 0) {
          this.renderStudentsTable();
          document.getElementById("attendanceCard").style.display = "block";
        } else {
          document.getElementById("emptyState").style.display = "block";
        }
      } else {
        console.error("Failed to load students:", response?.message);
        document.getElementById("emptyState").style.display = "block";
      }
    } catch (error) {
      console.error("Error loading students:", error);
      document.getElementById("loadingState").style.display = "none";
      document.getElementById("emptyState").style.display = "block";
    }
  },

  renderStudentsTable: function () {
    const tbody = document.getElementById("studentsTableBody");
    tbody.innerHTML = "";

    // Get selected class and session names
    const classSelect = document.getElementById("classSelect");
    const sessionSelect = document.getElementById("sessionSelect");
    const className =
      classSelect.options[classSelect.selectedIndex]?.textContent || "Students";
    const sessionName =
      sessionSelect.options[sessionSelect.selectedIndex]?.textContent || "";

    document.getElementById("classTitle").textContent = className;
    document.getElementById(
      "attendanceInfo"
    ).textContent = `${sessionName} | Date: ${this.selectedDate}`;

    this.students.forEach((student, index) => {
      const status =
        student.existing_status || student.attendance_status || "present";
      const hasPermission = student.has_permission;
      const permissionInfo = student.permission_info;

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
                      student.student_type
                    )}">
                        ${this.getTypeShortName(student.student_type)}
                    </span>
                </td>
                <td>
                    ${
                      hasPermission
                        ? `
                        <span class="badge bg-warning text-dark" title="${
                          permissionInfo?.reason || "Active permission"
                        }">
                            ${permissionInfo?.type_code || "PERM"}
                        </span>
                    `
                        : '<span class="text-muted">-</span>'
                    }
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group" data-student-id="${
                      student.student_id || student.id
                    }">
                        <input type="radio" class="btn-check" name="status_${
                          student.student_id || student.id
                        }" 
                               id="present_${
                                 student.student_id || student.id
                               }" value="present" ${
        status === "present" ? "checked" : ""
      }>
                        <label class="btn btn-outline-success" for="present_${
                          student.student_id || student.id
                        }">
                            <i class="bi bi-check"></i>
                        </label>
                        
                        <input type="radio" class="btn-check" name="status_${
                          student.student_id || student.id
                        }" 
                               id="absent_${
                                 student.student_id || student.id
                               }" value="absent" ${
        status === "absent" ? "checked" : ""
      }>
                        <label class="btn btn-outline-danger" for="absent_${
                          student.student_id || student.id
                        }">
                            <i class="bi bi-x"></i>
                        </label>
                        
                        <input type="radio" class="btn-check" name="status_${
                          student.student_id || student.id
                        }" 
                               id="late_${
                                 student.student_id || student.id
                               }" value="late" ${
        status === "late" ? "checked" : ""
      }>
                        <label class="btn btn-outline-warning" for="late_${
                          student.student_id || student.id
                        }">
                            <i class="bi bi-clock"></i>
                        </label>

                        ${
                          hasPermission
                            ? `
                            <input type="radio" class="btn-check" name="status_${
                              student.student_id || student.id
                            }" 
                                   id="permission_${
                                     student.student_id || student.id
                                   }" value="permission" ${
                                status === "permission" ? "checked" : ""
                              }>
                            <label class="btn btn-outline-info" for="permission_${
                              student.student_id || student.id
                            }">
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

  markAll: function (status) {
    this.students.forEach((student) => {
      const studentId = student.student_id || student.id;
      // Skip permission status for students with active permissions when marking absent
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
        `permission_${studentId}`
      );

      if (presentRadio?.checked) present++;
      else if (absentRadio?.checked) absent++;
      else if (lateRadio?.checked) late++;
      else if (permissionRadio?.checked) permission++;
    });

    document.getElementById("presentCount").textContent = `Present: ${present}`;
    document.getElementById("absentCount").textContent = `Absent: ${absent}`;
    document.getElementById("lateCount").textContent = `Late: ${late}`;
    document.getElementById(
      "permissionCount"
    ).textContent = `Permission: ${permission}`;
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
      const presentRadio = document.getElementById(`present_${studentId}`);
      const absentRadio = document.getElementById(`absent_${studentId}`);
      const lateRadio = document.getElementById(`late_${studentId}`);
      const permissionRadio = document.getElementById(
        `permission_${studentId}`
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
    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

    try {
      // Use session-aware endpoint
      const response = await window.API.apiCall(
        "/api/?route=attendance&action=mark-session",
        "POST",
        {
          stream_id: this.selectedStreamId,
          session_id: this.selectedSessionId,
          date: this.selectedDate,
          attendance: attendance,
        }
      );

      submitBtn.disabled = false;
      submitBtn.innerHTML =
        '<i class="bi bi-check-circle me-2"></i>Submit Attendance';

      if (response && response.success) {
        const data = response.data || {};
        alert(
          `✅ Attendance submitted successfully!\n\nCreated: ${
            data.created || 0
          }\nUpdated: ${data.updated || 0}\nTotal: ${data.total || 0}`
        );

        // Reload to show updated status
        this.loadStudents();
      } else {
        alert(
          "❌ Failed to submit attendance: " +
            (response?.message || "Unknown error")
        );
      }
    } catch (error) {
      console.error("Error submitting attendance:", error);
      submitBtn.disabled = false;
      submitBtn.innerHTML =
        '<i class="bi bi-check-circle me-2"></i>Submit Attendance';
      alert("❌ Error submitting attendance. Please try again.");
    }
  },
};

// Initialize on page load
document.addEventListener("DOMContentLoaded", () =>
  markAttendanceController.init()
);
