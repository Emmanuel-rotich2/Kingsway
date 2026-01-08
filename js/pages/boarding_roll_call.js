/**
 * Boarding Roll Call JavaScript Controller
 *
 * Handles dormitory attendance marking for house parents/boarding masters
 * - Morning roll call
 * - Night roll call
 * - Weekend roll calls
 * - Permission status display
 */

class BoardingRollCallController {
  constructor() {
    this.students = [];
    this.dormitories = [];
    this.sessions = [];
    this.selectedDormitory = null;
    this.selectedSession = null;
    this.selectedDate = null;
    this.attendanceMarked = {};

    this.init();
  }

  async init() {
    // Set today's date
    const today = new Date().toISOString().split("T")[0];
    document.getElementById("rollCallDate").value = today;
    this.selectedDate = today;

    // Load initial data
    await this.loadDormitories();
    await this.loadSessions();

    // Bind events
    this.bindEvents();
  }

  bindEvents() {
    // Dormitory selection
    document
      .getElementById("dormitorySelect")
      .addEventListener("change", (e) => {
        this.selectedDormitory = e.target.value;
        this.updateUI();
      });

    // Session selection
    document.getElementById("sessionSelect").addEventListener("change", (e) => {
      this.selectedSession = e.target.value;
      this.updateUI();
    });

    // Date selection
    document.getElementById("rollCallDate").addEventListener("change", (e) => {
      this.selectedDate = e.target.value;
    });

    // Load students button
    document.getElementById("loadStudentsBtn").addEventListener("click", () => {
      this.loadStudents();
    });

    // Refresh button
    document.getElementById("refreshBtn").addEventListener("click", () => {
      this.refresh();
    });

    // Bulk actions
    document.getElementById("markAllPresent").addEventListener("click", () => {
      this.markAll("present");
    });

    document.getElementById("markAllAbsent").addEventListener("click", () => {
      this.markAll("absent");
    });

    // Submit roll call
    document.getElementById("submitRollCall").addEventListener("click", () => {
      this.submitRollCall();
    });
  }

  async loadDormitories() {
    try {
      const response = await API.get(
        "/api/?route=attendance&action=dormitories"
      );
      if (response.success && response.data) {
        this.dormitories = response.data;
        this.renderDormitorySelect();
      } else {
        console.error("Failed to load dormitories:", response.message);
      }
    } catch (error) {
      console.error("Error loading dormitories:", error);
      this.showToast("Failed to load dormitories", "error");
    }
  }

  async loadSessions() {
    try {
      const response = await API.get("/api/?route=attendance&action=sessions");
      if (response.success && response.data) {
        // Filter to boarding-related sessions only
        this.sessions = response.data.filter((s) =>
          [
            "MORNING_ROLL_CALL",
            "NIGHT_ROLL_CALL",
            "WEEKEND_ROLL_CALL",
            "MORNING_PREP",
            "EVENING_PREP",
          ].includes(s.session_code)
        );
        this.renderSessionSelect();
      } else {
        console.error("Failed to load sessions:", response.message);
      }
    } catch (error) {
      console.error("Error loading sessions:", error);
      this.showToast("Failed to load sessions", "error");
    }
  }

  renderDormitorySelect() {
    const select = document.getElementById("dormitorySelect");
    select.innerHTML = '<option value="">-- Select Dormitory --</option>';

    this.dormitories.forEach((dorm) => {
      const option = document.createElement("option");
      option.value = dorm.id;
      option.textContent = `${dorm.name} (${dorm.student_count || 0} students)`;
      select.appendChild(option);
    });
  }

  renderSessionSelect() {
    const select = document.getElementById("sessionSelect");
    select.innerHTML = '<option value="">-- Select Session --</option>';

    this.sessions.forEach((session) => {
      const option = document.createElement("option");
      option.value = session.id;
      option.textContent = `${session.session_name} (${session.start_time} - ${session.end_time})`;
      option.dataset.code = session.session_code;
      select.appendChild(option);
    });
  }

  updateUI() {
    const dormName =
      this.dormitories.find((d) => d.id == this.selectedDormitory)?.name || "";
    const sessionName =
      this.sessions.find((s) => s.id == this.selectedSession)?.session_name ||
      "";

    if (dormName) {
      document.getElementById("dormitoryTitle").textContent = dormName;
    }
    if (sessionName) {
      document.getElementById("sessionBadge").textContent = sessionName;
    }
  }

  async loadStudents() {
    if (!this.selectedDormitory) {
      this.showToast("Please select a dormitory", "warning");
      return;
    }
    if (!this.selectedSession) {
      this.showToast("Please select a session", "warning");
      return;
    }
    if (!this.selectedDate) {
      this.showToast("Please select a date", "warning");
      return;
    }

    // Check if it's a school day (for weekday sessions)
    const sessionCode = this.sessions.find(
      (s) => s.id == this.selectedSession
    )?.session_code;
    if (!["WEEKEND_ROLL_CALL"].includes(sessionCode)) {
      await this.checkSchoolDay();
    }

    this.showLoading(true);

    try {
      const response = await API.get(
        `/api/?route=attendance&action=dormitory-students&dormitory_id=${this.selectedDormitory}&date=${this.selectedDate}&session_id=${this.selectedSession}`
      );

      if (response.success && response.data) {
        this.students = response.data;
        this.attendanceMarked = {};

        // Pre-populate attendance status from existing records
        this.students.forEach((student) => {
          if (student.existing_status) {
            this.attendanceMarked[student.student_id] = {
              status: student.existing_status,
              notes: student.existing_notes || "",
            };
          }
        });

        this.renderStudentsTable();
        this.updateStats();
        this.showRollCallCard(true);
      } else {
        this.showToast(response.message || "Failed to load students", "error");
      }
    } catch (error) {
      console.error("Error loading students:", error);
      this.showToast("Failed to load students", "error");
    } finally {
      this.showLoading(false);
    }
  }

  async checkSchoolDay() {
    try {
      const response = await API.get(
        `/api/?route=attendance&action=is-school-day&date=${this.selectedDate}`
      );
      if (response.success && response.data && !response.data.is_school_day) {
        const proceed = confirm(
          `${this.selectedDate} is marked as "${
            response.data.calendar_event?.event_type || "non-school day"
          }". Continue anyway?`
        );
        if (!proceed) {
          throw new Error("User cancelled");
        }
      }
    } catch (error) {
      if (error.message === "User cancelled") {
        throw error;
      }
      // Continue even if check fails
      console.warn("Could not check if school day:", error);
    }
  }

  renderStudentsTable() {
    const tbody = document.getElementById("studentsTableBody");
    tbody.innerHTML = "";

    if (this.students.length === 0) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox display-4"></i>
                        <p class="mt-2">No students found in this dormitory</p>
                    </td>
                </tr>
            `;
      return;
    }

    this.students.forEach((student, index) => {
      const currentStatus =
        this.attendanceMarked[student.student_id]?.status || "";
      const hasPermission = student.has_permission;
      const permissionInfo = student.permission_info;

      const row = document.createElement("tr");
      row.className = `student-row ${hasPermission ? "has-permission" : ""} ${
        currentStatus ? "status-" + currentStatus : ""
      }`;
      row.dataset.studentId = student.student_id;

      row.innerHTML = `
                <td>${index + 1}</td>
                <td><strong>${student.admission_no || "N/A"}</strong></td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar bg-secondary text-white rounded-circle me-2 d-flex align-items-center justify-content-center" 
                             style="width: 35px; height: 35px; font-size: 0.8rem;">
                            ${this.getInitials(
                              student.first_name,
                              student.last_name
                            )}
                        </div>
                        <div>
                            <div class="fw-semibold">${student.first_name} ${
        student.last_name
      }</div>
                            <small class="text-muted">${
                              student.student_type || "Boarder"
                            }</small>
                        </div>
                    </div>
                </td>
                <td>${student.class_name || "N/A"}</td>
                <td>${student.bed_number || "-"}</td>
                <td>
                    ${
                      hasPermission
                        ? `
                        <span class="badge bg-warning text-dark" title="${
                          permissionInfo?.type_name || "Permission"
                        }">
                            <i class="bi bi-exclamation-triangle me-1"></i>${
                              permissionInfo?.type_code || "PERM"
                            }
                        </span>
                        <br><small class="text-muted">${
                          permissionInfo?.end_date || ""
                        }</small>
                    `
                        : '<span class="text-muted">-</span>'
                    }
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="status_${
                          student.student_id
                        }" 
                               id="present_${
                                 student.student_id
                               }" value="present"
                               ${currentStatus === "present" ? "checked" : ""}>
                        <label class="btn btn-outline-success" for="present_${
                          student.student_id
                        }" title="Present">
                            <i class="bi bi-check-lg"></i>
                        </label>

                        <input type="radio" class="btn-check" name="status_${
                          student.student_id
                        }" 
                               id="absent_${student.student_id}" value="absent"
                               ${currentStatus === "absent" ? "checked" : ""}>
                        <label class="btn btn-outline-danger" for="absent_${
                          student.student_id
                        }" title="Absent">
                            <i class="bi bi-x-lg"></i>
                        </label>

                        ${
                          hasPermission
                            ? `
                            <input type="radio" class="btn-check" name="status_${
                              student.student_id
                            }" 
                                   id="permission_${
                                     student.student_id
                                   }" value="permission"
                                   ${
                                     currentStatus === "permission"
                                       ? "checked"
                                       : ""
                                   }>
                            <label class="btn btn-outline-warning" for="permission_${
                              student.student_id
                            }" title="On Permission">
                                <i class="bi bi-door-open"></i>
                            </label>
                        `
                            : ""
                        }

                        <input type="radio" class="btn-check" name="status_${
                          student.student_id
                        }" 
                               id="sick_${student.student_id}" value="sick_bay"
                               ${currentStatus === "sick_bay" ? "checked" : ""}>
                        <label class="btn btn-outline-info" for="sick_${
                          student.student_id
                        }" title="Sick Bay">
                            <i class="bi bi-hospital"></i>
                        </label>
                    </div>
                </td>
            `;

      tbody.appendChild(row);

      // Bind status change events
      row.querySelectorAll('input[type="radio"]').forEach((radio) => {
        radio.addEventListener("change", (e) => {
          this.setStudentStatus(student.student_id, e.target.value);
          this.updateRowAppearance(row, e.target.value);
          this.updateStats();
        });
      });
    });
  }

  getInitials(firstName, lastName) {
    return `${(firstName || "")[0] || ""}${
      (lastName || "")[0] || ""
    }`.toUpperCase();
  }

  setStudentStatus(studentId, status) {
    this.attendanceMarked[studentId] = { status, notes: "" };
  }

  updateRowAppearance(row, status) {
    row.classList.remove(
      "status-present",
      "status-absent",
      "status-permission",
      "status-sick-bay"
    );
    if (status) {
      row.classList.add("status-" + status.replace("_", "-"));
    }
  }

  markAll(status) {
    this.students.forEach((student) => {
      // Skip students with permission if marking absent
      if (status === "absent" && student.has_permission) {
        this.setStudentStatus(student.student_id, "permission");
        document.getElementById(`permission_${student.student_id}`)?.click();
      } else {
        this.setStudentStatus(student.student_id, status);
        const radio = document.getElementById(
          `${status}_${student.student_id}`
        );
        if (radio) radio.checked = true;
      }

      const row = document.querySelector(
        `tr[data-student-id="${student.student_id}"]`
      );
      if (row) {
        const actualStatus =
          student.has_permission && status === "absent" ? "permission" : status;
        this.updateRowAppearance(row, actualStatus);
      }
    });
    this.updateStats();
  }

  updateStats() {
    const stats = {
      total: this.students.length,
      present: 0,
      absent: 0,
      permission: 0,
      sick_bay: 0,
    };

    Object.values(this.attendanceMarked).forEach((record) => {
      if (stats.hasOwnProperty(record.status)) {
        stats[record.status]++;
      }
    });

    document.getElementById("statTotal").textContent = stats.total;
    document.getElementById("statPresent").textContent = stats.present;
    document.getElementById("statAbsent").textContent = stats.absent;
    document.getElementById("statPermission").textContent = stats.permission;
    document.getElementById("statSickBay").textContent = stats.sick_bay;

    document.getElementById("quickStats").style.display = "block";
  }

  async submitRollCall() {
    // Check if all students have been marked
    const unmarkedStudents = this.students.filter(
      (s) => !this.attendanceMarked[s.student_id]
    );

    if (unmarkedStudents.length > 0) {
      const proceed = confirm(
        `${unmarkedStudents.length} students have not been marked. Continue anyway? (Unmarked students will be counted as absent)`
      );
      if (!proceed) return;

      // Mark unmarked as absent
      unmarkedStudents.forEach((s) => {
        this.attendanceMarked[s.student_id] = {
          status: "absent",
          notes: "Not marked during roll call",
        };
      });
    }

    const attendanceData = Object.entries(this.attendanceMarked).map(
      ([studentId, record]) => ({
        student_id: parseInt(studentId),
        status: record.status,
        notes: record.notes,
      })
    );

    try {
      const response = await API.post(
        "/api/?route=attendance&action=mark-boarding",
        {
          dormitory_id: parseInt(this.selectedDormitory),
          session_id: parseInt(this.selectedSession),
          date: this.selectedDate,
          attendance: attendanceData,
        }
      );

      if (response.success) {
        this.showToast("Roll call submitted successfully!", "success");
        await this.loadBoardingSummary();
      } else {
        this.showToast(
          response.message || "Failed to submit roll call",
          "error"
        );
      }
    } catch (error) {
      console.error("Error submitting roll call:", error);
      this.showToast("Failed to submit roll call", "error");
    }
  }

  async loadBoardingSummary() {
    try {
      const response = await API.get(
        `/api/?route=attendance&action=boarding-summary&date=${this.selectedDate}`
      );
      if (response.success && response.data) {
        this.renderSummary(response.data);
      }
    } catch (error) {
      console.error("Error loading summary:", error);
    }
  }

  renderSummary(data) {
    const summaryCard = document.getElementById("summaryCard");
    const summaryContent = document.getElementById("summaryContent");

    let html = '<div class="row">';

    data.forEach((dorm) => {
      html += `
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <strong>${dorm.dormitory_name}</strong>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total:</span>
                                <strong>${dorm.total_students}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-success">Present:</span>
                                <strong>${dorm.present_count}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-danger">Absent:</span>
                                <strong>${dorm.absent_count}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-warning">On Permission:</span>
                                <strong>${dorm.permission_count}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            `;
    });

    html += "</div>";
    summaryContent.innerHTML = html;
    summaryCard.style.display = "block";
  }

  showLoading(show) {
    document.getElementById("loadingState").style.display = show
      ? "block"
      : "none";
    document.getElementById("emptyState").style.display = "none";
  }

  showRollCallCard(show) {
    document.getElementById("rollCallCard").style.display = show
      ? "block"
      : "none";
    document.getElementById("emptyState").style.display = show
      ? "none"
      : "block";

    // Update date display
    const date = new Date(this.selectedDate);
    document.getElementById("dateDisplay").textContent =
      date.toLocaleDateString("en-GB", {
        day: "numeric",
        month: "long",
        year: "numeric",
      });
  }

  async refresh() {
    if (this.selectedDormitory && this.selectedSession) {
      await this.loadStudents();
    }
  }

  showToast(message, type = "info") {
    // Create toast container if not exists
    let toastContainer = document.getElementById("toastContainer");
    if (!toastContainer) {
      toastContainer = document.createElement("div");
      toastContainer.id = "toastContainer";
      toastContainer.className =
        "toast-container position-fixed bottom-0 end-0 p-3";
      document.body.appendChild(toastContainer);
    }

    const toastId = "toast_" + Date.now();
    const bgClass =
      type === "success"
        ? "bg-success"
        : type === "error"
        ? "bg-danger"
        : type === "warning"
        ? "bg-warning"
        : "bg-info";

    const toastHtml = `
            <div id="${toastId}" class="toast ${bgClass} text-white" role="alert">
                <div class="toast-body d-flex justify-content-between align-items-center">
                    <span>${message}</span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

    toastContainer.insertAdjacentHTML("beforeend", toastHtml);

    const toastEl = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();

    toastEl.addEventListener("hidden.bs.toast", () => toastEl.remove());
  }
}

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  window.boardingRollCall = new BoardingRollCallController();
});
