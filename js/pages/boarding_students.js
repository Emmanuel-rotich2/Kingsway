/**
 * Boarding Students Controller
 * Boarding operations dashboard for Boarding Master / Matron / Housemother
 */
const BoardingStudentsController = {
  state: {
    students: [],
    summary: {},
    academicYears: [],
    classes: [],
    streams: [],
    dormitories: [],
    selectedStudentId: null,
    selectedDate: new Date().toISOString().slice(0, 10),
  },

  ui: {},

  async init() {
    console.log("BoardingStudentsController: Initializing...");

    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    this.attachEvents();

    console.log("BoardingStudentsController: Loading metadata...");
    await this.loadMeta();
    console.log("BoardingStudentsController: Loading summary...");
    await this.loadSummary();
    console.log("BoardingStudentsController: Loading students...");
    await this.loadStudents();
    console.log("BoardingStudentsController: Initialization complete");
  },

  cacheDom() {
    const $ = (id) => document.getElementById(id);

    this.ui = {
      academicYearFilter: $("academicYearFilter"),
      classFilter: $("classFilter"),
      streamFilter: $("streamFilter"),
      genderFilter: $("genderFilter"),
      dormitoryFilter: $("dormitoryFilter"),
      bedStatusFilter: $("bedStatusFilter"),
      boardingStatusFilter: $("boardingStatusFilter"),
      rollCallStatusFilter: $("rollCallStatusFilter"),
      searchBox: $("searchBox"),
      applyFiltersBtn: $("applyFiltersBtn"),
      resetFiltersBtn: $("resetFiltersBtn"),
      refreshBtn: $("refreshBtn"),
      assignDormBtn: $("assignDormBtn"),
      rollCallBtn: $("rollCallBtn"),
      createExeatBtn: $("createExeatBtn"),
      printBoardingListBtn: $("printBoardingListBtn"),
      exportBoardingSheetBtn: $("exportBoardingSheetBtn"),

      totalBoarders: $("totalBoarders"),
      boysBoarders: $("boysBoarders"),
      girlsBoarders: $("girlsBoarders"),
      onExeatCount: $("onExeatCount"),
      absentCount: $("absentCount"),
      specialAlertsCount: $("specialAlertsCount"),

      studentsLoading: $("studentsLoading"),
      studentsError: $("studentsError"),
      studentsForbidden: $("studentsForbidden"),
      studentsEmpty: $("studentsEmpty"),
      studentsTableBody: $("studentsTableBody"),
      selectAll: $("selectAll"),

      modal: $("boardingProfileModal"),
      modalLoading: $("modalLoading"),
      modalError: $("modalError"),
      modalBoardingContent: $("modalBoardingContent"),
      assignDormModalBtn: $("assignDormModalBtn"),
      addBoardingNoteBtn: $("addBoardingNoteBtn"),

      assignDormModal: $("assignDormModal"),
      assignDormForm: $("assignDormForm"),
      assignStudentName: $("assignStudentName"),
      assignStudentId: $("assignStudentId"),
      assignDormitory: $("assignDormitory"),
      assignRoom: $("assignRoom"),
      assignBed: $("assignBed"),
      assignDate: $("assignDate"),
      assignNotes: $("assignNotes"),
      saveAssignDormBtn: $("saveAssignDormBtn"),

      rollCallModal: $("rollCallModal"),
      rollCallForm: $("rollCallForm"),
      rollCallDate: $("rollCallDate"),
      rollCallSession: $("rollCallSession"),
      rollCallDormitory: $("rollCallDormitory"),
      rollCallStudentsList: $("rollCallStudentsList"),
      saveRollCallBtn: $("saveRollCallBtn"),

      exeatModal: $("exeatModal"),
      exeatForm: $("exeatForm"),
      exeatStudent: $("exeatStudent"),
      exeatType: $("exeatType"),
      exeatReason: $("exeatReason"),
      exeatDestination: $("exeatDestination"),
      exeatLeaveAt: $("exeatLeaveAt"),
      exeatExpectedReturn: $("exeatExpectedReturn"),
      exeatGuardianContacted: $("exeatGuardianContacted"),
      exeatNotes: $("exeatNotes"),
      saveExeatBtn: $("saveExeatBtn"),

      addBoardingNoteModal: $("addBoardingNoteModal"),
      addBoardingNoteForm: $("addBoardingNoteForm"),
      boardingNoteType: $("boardingNoteType"),
      boardingNoteContent: $("boardingNoteContent"),
      boardingNoteVisibility: $("boardingNoteVisibility"),
      boardingNotePriority: $("boardingNotePriority"),
      saveBoardingNoteBtn: $("saveBoardingNoteBtn"),
    };
  },

  attachEvents() {
    this.ui.applyFiltersBtn?.addEventListener("click", () => {
      this.loadSummary();
      this.loadStudents();
    });
    this.ui.resetFiltersBtn?.addEventListener("click", () => this.resetFilters());
    this.ui.refreshBtn?.addEventListener("click", () => {
      this.loadSummary();
      this.loadStudents();
    });
    this.ui.rollCallBtn?.addEventListener("click", () => this.openRollCallModal());
    this.ui.createExeatBtn?.addEventListener("click", () => this.openExeatModal());
    this.ui.exportBoardingSheetBtn?.addEventListener("click", () => this.exportBoardingSheet());

    // Boarding note modal
    this.ui.addBoardingNoteBtn?.addEventListener("click", () => this.openBoardingNoteModal());
    this.ui.saveBoardingNoteBtn?.addEventListener("click", () => this.saveBoardingNote());

    this.ui.searchBox?.addEventListener(
      "input",
      this.debounce(() => this.loadStudents(), 400)
    );

    this.ui.classFilter?.addEventListener("change", () => {
      this.updateStreamsFilter();
      this.loadStudents();
    });

    this.ui.streamFilter?.addEventListener("change", () => this.loadStudents());
    this.ui.academicYearFilter?.addEventListener("change", () => this.loadStudents());
    this.ui.genderFilter?.addEventListener("change", () => this.loadStudents());
    this.ui.dormitoryFilter?.addEventListener("change", () => this.loadStudents());
    this.ui.bedStatusFilter?.addEventListener("change", () => this.loadStudents());
    this.ui.boardingStatusFilter?.addEventListener("change", () => this.loadStudents());
    this.ui.rollCallStatusFilter?.addEventListener("change", () => this.loadStudents());

    this.ui.saveAssignDormBtn?.addEventListener("click", () => this.saveDormAssignment());
    this.ui.saveRollCallBtn?.addEventListener("click", () => this.saveRollCall());
    this.ui.saveExeatBtn?.addEventListener("click", () => this.saveExeat());
    this.ui.assignDormModalBtn?.addEventListener("click", () => this.openAssignDormModal(this.state.selectedStudentId));
  },

  async loadMeta() {
    try {
      const response = await this.api("/students/boarding-meta", "GET");
      const data = this.unwrap(response);

      this.state.academicYears = data.academic_years || [];
      this.state.classes = data.classes || [];
      this.state.streams = data.streams || [];
      this.state.dormitories = data.dormitories || [];

      this.fillSelect(this.ui.academicYearFilter, this.state.academicYears, "All Years");
      this.fillSelect(this.ui.classFilter, this.state.classes, "All Classes");
      this.fillSelect(this.ui.streamFilter, this.state.streams, "All Streams");
      this.fillSelect(this.ui.dormitoryFilter, this.state.dormitories, "All Dormitories");
      this.fillSelect(this.ui.assignDormitory, this.state.dormitories, "Select Dormitory");
      this.fillSelect(this.ui.rollCallDormitory, this.state.dormitories, "All Dormitories");
    } catch (error) {
      console.error("Failed to load metadata:", error);
      if (error.message.includes("forbidden") || error.message.includes("permission")) {
        this.showForbidden();
      }
    }
  },

  updateStreamsFilter() {
    const classId = this.ui.classFilter?.value || "";
    const filtered = classId
      ? this.state.streams.filter((s) => String(s.class_id) === String(classId))
      : this.state.streams;
    this.fillSelect(this.ui.streamFilter, filtered, "All Streams");
  },

  async loadSummary() {
    try {
      const params = this.getParams();
      const response = await this.api(`/students/boarding-summary?${params.toString()}`, "GET");
      this.state.summary = this.unwrap(response) || {};
      this.renderSummary();
    } catch (error) {
      console.error("Failed to load summary:", error);
    }
  },

  async loadStudents() {
    this.setLoading(true);

    try {
      const params = this.getParams();
      const response = await this.api(`/students/boarding-students?${params.toString()}`, "GET");
      const students = this.unwrap(response) || [];

      this.state.students = students;
      this.renderStudents();
    } catch (error) {
      console.error("Failed to load students:", error);
      if (error.message.includes("forbidden") || error.message.includes("permission")) {
        this.showForbidden();
      } else {
        this.showError(error.message || "Failed to load boarding students");
      }
    } finally {
      this.setLoading(false);
    }
  },

  getParams() {
    const params = new URLSearchParams();
    const filters = {
      academic_year: this.ui.academicYearFilter?.value || "",
      class_id: this.ui.classFilter?.value || "",
      stream_id: this.ui.streamFilter?.value || "",
      gender: this.ui.genderFilter?.value || "",
      dormitory_id: this.ui.dormitoryFilter?.value || "",
      bed_status: this.ui.bedStatusFilter?.value || "",
      boarding_status: this.ui.boardingStatusFilter?.value || "",
      roll_call_status: this.ui.rollCallStatusFilter?.value || "",
      search: this.ui.searchBox?.value.trim() || "",
    };

    Object.entries(filters).forEach(([key, val]) => {
      if (val !== "") params.set(key, val);
    });

    return params;
  },

  renderSummary() {
    const s = this.state.summary;
    this.ui.totalBoarders.textContent = s.total_boarders ?? 0;
    this.ui.boysBoarders.textContent = s.boys_boarders ?? 0;
    this.ui.girlsBoarders.textContent = s.girls_boarders ?? 0;
    this.ui.onExeatCount.textContent = s.on_exeat_count ?? 0;
    this.ui.absentCount.textContent = s.absent_count ?? 0;
    this.ui.specialAlertsCount.textContent = s.special_alerts_count ?? 0;
  },

  renderStudents() {
    if (!this.state.students.length) {
      this.ui.studentsTableBody.innerHTML = `
        <tr>
          <td colspan="13" class="text-center text-muted py-4">
            No boarding students found.
          </td>
        </tr>`;
      return;
    }

    this.ui.studentsTableBody.innerHTML = this.state.students
      .map((s) => {
        const statusColors = {
          active: "success",
          on_leave: "warning",
          sick: "danger",
          checked_out: "secondary",
          suspended: "dark",
        };

        const rollCallColors = {
          present: "success",
          absent: "danger",
          late: "warning",
          excused: "info",
        };

        return `
          <tr>
            <td><input type="checkbox" class="student-checkbox" data-student-id="${s.student_id}"></td>
            <td>${this.escape(s.admission_no || "-")}</td>
            <td><strong>${this.escape(s.full_name || "-")}</strong></td>
            <td>${this.escape(s.class_name || "-")}</td>
            <td>${this.escape(s.stream_name || "-")}</td>
            <td>${this.escape(s.gender || "-")}</td>
            <td>${this.escape(s.dormitory_name || "-")}</td>
            <td>${this.escape(s.bed_number || "-")}</td>
            <td><span class="badge bg-${statusColors[s.boarding_status] || "secondary"}">${this.escape(s.boarding_status || "-")}</span></td>
            <td><span class="badge bg-${rollCallColors[s.roll_call_status_today] || "secondary"}">${this.escape(s.roll_call_status_today || "-")}</span></td>
            <td>${this.escape(s.exeat_status || "-")}</td>
            <td>${s.has_special_alert ? '<i class="bi bi-exclamation-triangle text-danger"></i>' : ''}</td>
            <td>
              <button class="btn btn-sm btn-outline-success" onclick="BoardingStudentsController.viewBoardingProfile(${s.student_id})">
                <i class="bi bi-eye"></i> View
              </button>
            </td>
          </tr>`;
      })
      .join("");

    this.ui.studentsEmpty.classList.toggle("d-none", this.state.students.length > 0);
  },

  resetFilters() {
    [
      this.ui.academicYearFilter,
      this.ui.classFilter,
      this.ui.streamFilter,
      this.ui.genderFilter,
      this.ui.dormitoryFilter,
      this.ui.bedStatusFilter,
      this.ui.boardingStatusFilter,
      this.ui.rollCallStatusFilter,
      this.ui.searchBox,
    ].forEach((el) => {
      if (el) el.value = "";
    });

    this.updateStreamsFilter();
    this.loadSummary();
    this.loadStudents();
  },

  async viewBoardingProfile(studentId) {
    this.state.selectedStudentId = studentId;

    if (typeof bootstrap !== "undefined" && this.ui.modal) {
      const modalInstance = new bootstrap.Modal(this.ui.modal);
      modalInstance.show();
    }

    await this.loadBoardingProfile(studentId);
  },

  async loadBoardingProfile(studentId) {
    this.setModalLoading(true);

    try {
      const response = await this.api(`/students/boarding-student/${studentId}`, "GET");
      const data = this.unwrap(response);

      this.renderBoardingProfile(data);
    } catch (error) {
      console.error("Failed to load boarding profile:", error);
      this.showModalError(error.message || "Failed to load boarding profile");
    } finally {
      this.setModalLoading(false);
    }
  },

  renderBoardingProfile(data) {
    const student = data.student || {};
    const boarding = data.boarding || {};
    const rollCallHistory = data.roll_call_history || [];
    const exeatHistory = data.exeat_history || [];
    const boardingNotes = data.boarding_notes || [];

    this.ui.modalBoardingContent.innerHTML = `
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Student Profile</h5>
          <p><strong>Name:</strong> ${this.escape(student.first_name || "")} ${this.escape(student.last_name || "")}</p>
          <p><strong>Admission No:</strong> ${this.escape(student.admission_no || "-")}</p>
          <p><strong>Class:</strong> ${this.escape(data.class_name || "-")}</p>
          <p><strong>Stream:</strong> ${this.escape(data.stream_name || "-")}</p>
          <p><strong>Gender:</strong> ${this.escape(student.gender || "-")}</p>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Boarding Information</h5>
          <p><strong>Dormitory:</strong> ${this.escape(data.dormitory_name || "-")}</p>
          <p><strong>Room:</strong> ${this.escape(boarding.room_name || "-")}</p>
          <p><strong>Bed:</strong> ${this.escape(boarding.bed_number || "-")}</p>
          <p><strong>Status:</strong> ${this.escape(boarding.status || "-")}</p>
          <p><strong>Assigned Date:</strong> ${this.escape(boarding.assigned_date || "-")}</p>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Roll Call History (${rollCallHistory.length})</h5>
          ${rollCallHistory.length === 0 ? '<p class="text-muted">No roll call history.</p>' : ''}
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Date</th>
                <th>Session</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              ${rollCallHistory.slice(0, 5).map(h => `
                <tr>
                  <td>${this.escape(h.date || "-")}</td>
                  <td>${this.escape(h.session || "-")}</td>
                  <td>${this.escape(h.status || "-")}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Exeat History (${exeatHistory.length})</h5>
          ${exeatHistory.length === 0 ? '<p class="text-muted">No exeat history.</p>' : ''}
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Type</th>
                <th>Leave</th>
                <th>Return</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              ${exeatHistory.slice(0, 5).map(h => `
                <tr>
                  <td>${this.escape(h.exeat_type || "-")}</td>
                  <td>${this.escape(h.leave_at || "-")}</td>
                  <td>${this.escape(h.expected_return_at || "-")}</td>
                  <td>${this.escape(h.status || "-")}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Boarding Notes (${boardingNotes.length})</h5>
          ${boardingNotes.length === 0 ? '<p class="text-muted">No boarding notes.</p>' : ''}
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Type</th>
                <th>Note</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              ${boardingNotes.slice(0, 5).map(n => `
                <tr>
                  <td>${this.escape(n.note_type || "-")}</td>
                  <td>${this.escape(n.note || "-")}</td>
                  <td>${this.escape(n.created_at || "-")}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  },

  openAssignDormModal(studentId = null, studentName = null) {
    if (studentId === null) {
      studentId = this.state.selectedStudentId;
    }
    if (studentId) {
      this.ui.assignStudentId.value = studentId;
      // Find student name from state if not provided
      if (!studentName) {
        const student = this.state.students.find(s => s.student_id === studentId);
        studentName = student ? student.full_name : '';
      }
      this.ui.assignStudentName.value = studentName;
    }
    this.ui.assignDate.value = new Date().toISOString().slice(0, 10);
    if (typeof bootstrap !== "undefined" && this.ui.assignDormModal) {
      const modalInstance = new bootstrap.Modal(this.ui.assignDormModal);
      modalInstance.show();
    }
  },

  async saveDormAssignment() {
    const formData = {
      student_id: this.ui.assignStudentId.value,
      dormitory_id: this.ui.assignDormitory.value,
      room_id: null,
      bed_number: this.ui.assignBed.value,
      allocation_date: this.ui.assignDate.value,
      notes: this.ui.assignNotes.value,
    };

    try {
      const response = await this.api("/students/boarding-assign-dorm", "POST", formData);
      this.notify("Dormitory assigned successfully", "success");
      if (typeof bootstrap !== "undefined" && this.ui.assignDormModal) {
        const modalInstance = bootstrap.Modal.getInstance(this.ui.assignDormModal);
        modalInstance?.hide();
      }
      this.loadStudents();
    } catch (error) {
      this.notify(error.message || "Failed to assign dormitory", "error");
    }
  },

  openRollCallModal() {
    this.ui.rollCallDate.value = new Date().toISOString().slice(0, 10);
    if (typeof bootstrap !== "undefined" && this.ui.rollCallModal) {
      const modalInstance = new bootstrap.Modal(this.ui.rollCallModal);
      modalInstance.show();
    }
  },

  openExeatModal() {
    this.ui.exeatForm.reset();
    if (typeof bootstrap !== "undefined" && this.ui.exeatModal) {
      const modalInstance = new bootstrap.Modal(this.ui.exeatModal);
      modalInstance.show();
    }
  },

  async saveRollCall() {
    this.notify("Roll call saved (placeholder - implement endpoint)", "info");
  },

  async saveExeat() {
    this.notify("Exeat created (placeholder - implement endpoint)", "info");
  },

  exportBoardingSheet() {
    if (!this.state.students.length) {
      this.notify("No data to export", "warning");
      return;
    }

    const headers = ["Adm No", "Student Name", "Class", "Stream", "Gender", "Dormitory", "Room/Bed", "Boarding Status", "Roll Call", "Exeat", "Alert"];
    const rows = this.state.students.map(s => [
      s.admission_no || "",
      s.full_name || "",
      s.class_name || "",
      s.stream_name || "",
      s.gender || "",
      s.dormitory_name || "",
      s.bed_number || "",
      s.boarding_status || "",
      s.roll_call_status_today || "",
      s.exeat_status || "",
      s.has_special_alert ? "Yes" : "No",
    ]);

    const csv = [headers, ...rows].map(row => row.map(cell => `"${String(cell || "").replace(/"/g, '""')}"`).join(",")).join("\n");
    KingswayFileLifecycle.exportText(csv, `boarding_sheet_${new Date().toISOString().slice(0, 10)}.csv`, "text/csv");
  },

  openBoardingNoteModal() {
    this.ui.addBoardingNoteForm.reset();
    if (typeof bootstrap !== "undefined" && this.ui.addBoardingNoteModal) {
      const modalInstance = new bootstrap.Modal(this.ui.addBoardingNoteModal);
      modalInstance.show();
    }
  },

  async saveBoardingNote() {
    const formData = {
      student_id: this.state.selectedStudentId,
      note_type: this.ui.boardingNoteType.value,
      note: this.ui.boardingNoteContent.value,
      visibility: this.ui.boardingNoteVisibility.value,
      priority: this.ui.boardingNotePriority.value,
    };

    try {
      const response = await this.api("/students/boarding-note", "POST", formData);
      this.notify("Boarding note added successfully", "success");
      if (typeof bootstrap !== "undefined" && this.ui.addBoardingNoteModal) {
        const modalInstance = bootstrap.Modal.getInstance(this.ui.addBoardingNoteModal);
        modalInstance?.hide();
      }
      this.loadBoardingProfile(this.state.selectedStudentId);
    } catch (error) {
      this.notify(error.message || "Failed to add boarding note", "error");
    }
  },

  setLoading(loading) {
    this.ui.studentsLoading?.classList.toggle("d-none", !loading);
    this.ui.studentsError?.classList.add("d-none");
    this.ui.studentsForbidden?.classList.add("d-none");
  },

  showError(message) {
    if (!this.ui.studentsError) return;
    this.ui.studentsError.textContent = message;
    this.ui.studentsError.classList.remove("d-none");
  },

  showForbidden() {
    this.ui.studentsLoading?.classList.add("d-none");
    this.ui.studentsError?.classList.add("d-none");
    this.ui.studentsEmpty?.classList.add("d-none");
    this.ui.studentsForbidden?.classList.remove("d-none");
  },

  setModalLoading(loading) {
    this.ui.modalLoading?.classList.toggle("d-none", !loading);
    this.ui.modalError?.classList.add("d-none");
    this.ui.modalBoardingContent?.classList.toggle("opacity-50", loading);
  },

  showModalError(message) {
    if (!this.ui.modalError) return;
    this.ui.modalError.textContent = message;
    this.ui.modalError.classList.remove("d-none");
  },

  fillSelect(select, items, placeholder) {
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    (items || []).forEach((item) => {
      const option = document.createElement("option");
      option.value = item.id ?? item.value ?? "";
      option.textContent = item.name || item.class_name || item.stream_name || item.dormitory_name || item.label || option.value;
      select.appendChild(option);
    });
  },

  api: async function (endpoint, method = "GET", data = null) {
    // ALL HTTP goes through API.callAPI (aliased apiCall) in js/api.js.
    // It returns response.data on success and throws {message} on failure.
    // Re-wrap into the envelope shape this.unwrap() callers expect.
    try {
      const result = await API.callAPI(endpoint, method, data);
      return { success: true, data: result };
    } catch (err) {
      throw new Error((err && err.message) || "Request failed.");
    }
  },

  unwrap(response) {
    if (!response) return {};
    if (response.data && response.data.data !== undefined)
      return response.data.data;
    if (response.data !== undefined) return response.data;
    return response;
  },

  escape(value) {
    return String(value ?? "").replace(
      /[&<>"']/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#039;",
        })[char]
    );
  },

  debounce(fn, delay) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  },

  notify(message, type = "info") {
    if (typeof showNotification === "function") {
      showNotification(message, type);
      return;
    }

    if (window.API && typeof window.API.showNotification === "function") {
      window.API.showNotification(message, type);
      return;
    }

    alert(message);
  },
};

document.addEventListener("DOMContentLoaded", () =>
  BoardingStudentsController.init(),
);

window.BoardingStudentsController = BoardingStudentsController;
