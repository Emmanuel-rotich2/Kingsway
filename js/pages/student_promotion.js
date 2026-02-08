/**
 * Student Promotion Page Controller
 * Handles bulk and per-student promotion workflows
 */

const StudentPromotionController = {
  data: {
    students: [],
    classes: [],
    streams: [],
    streamMap: {},
    retainedIds: new Set(),
  },

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    this.attachEventListeners();
    await this.loadReferenceData();
  },

  attachEventListeners: function () {
    document
      .getElementById("loadStudents")
      ?.addEventListener("click", () => this.loadStudents());

    document
      .getElementById("processPromotion")
      ?.addEventListener("click", () => this.processPromotion());

    document
      .getElementById("promoteAll")
      ?.addEventListener("click", () => this.promoteAll());

    document
      .getElementById("retainSelected")
      ?.addEventListener("click", () => this.retainSelected());

    document
      .getElementById("selectAll")
      ?.addEventListener("change", (e) => {
        document
          .querySelectorAll(".student-select")
          .forEach((checkbox) => {
            checkbox.checked = e.target.checked;
          });
      });
  },

  loadReferenceData: async function () {
    await Promise.all([
      this.loadAcademicYears(),
      this.loadClasses(),
      this.loadStreams(),
    ]);
  },

  loadAcademicYears: async function () {
    try {
      const resp = await window.API.students.getAllAcademicYears();
      const years = this.unwrapPayload(resp) || [];
      const fromSelect = document.getElementById("fromYear");
      const toSelect = document.getElementById("toYear");
      if (!fromSelect || !toSelect) return;

      fromSelect.innerHTML = '<option value="">Select Year</option>';
      toSelect.innerHTML = '<option value="">Select Year</option>';

      years.forEach((year) => {
        const opt = document.createElement("option");
        opt.value = year.id;
        opt.textContent = year.year_code || year.year_name || year.id;
        if (year.is_current) opt.selected = true;
        fromSelect.appendChild(opt.cloneNode(true));
        toSelect.appendChild(opt);
      });
    } catch (error) {
      console.warn("Failed to load academic years", error);
    }
  },

  loadClasses: async function () {
    try {
      const resp = await window.API.academic.listClasses();
      const classes = this.unwrapPayload(resp) || [];
      this.data.classes = Array.isArray(classes) ? classes : [];

      const select = document.getElementById("selectClass");
      if (!select) return;

      select.innerHTML = '<option value="">Select Class</option>';
      this.data.classes.forEach((cls) => {
        const opt = document.createElement("option");
        opt.value = cls.id;
        opt.textContent = cls.name || cls.class_name;
        select.appendChild(opt);
      });
    } catch (error) {
      console.warn("Failed to load classes", error);
    }
  },

  loadStreams: async function () {
    try {
      const resp = await window.API.academic.listStreams();
      const streams = this.unwrapPayload(resp) || [];
      this.data.streams = Array.isArray(streams) ? streams : [];
      this.data.streamMap = {};
      this.data.streams.forEach((stream) => {
        this.data.streamMap[stream.id] = stream.class_id;
      });
    } catch (error) {
      console.warn("Failed to load streams", error);
    }
  },

  loadStudents: async function () {
    const classId = document.getElementById("selectClass").value;
    if (!classId) {
      this.showError("Please select a class");
      return;
    }

    try {
      this.data.retainedIds = new Set();
      const resp = await window.API.students.getByClass(classId);
      const payload = this.unwrapPayload(resp) || [];
      this.data.students = Array.isArray(payload) ? payload : payload.data || [];
      this.renderStudents();
    } catch (error) {
      console.error("Failed to load students", error);
      this.showError("Failed to load students");
    }
  },

  renderStudents: function () {
    const card = document.getElementById("studentsCard");
    const tbody = document.querySelector("#studentsTable tbody");
    if (!card || !tbody) return;

    card.style.display = "block";
    if (!this.data.students.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center text-muted">No students found for this class</td>
        </tr>
      `;
      return;
    }

    const streamOptions = this.data.streams
      .map(
        (stream) =>
          `<option value="${stream.id}">${
            stream.class_name || ""
          } ${stream.stream_name || stream.name || ""}</option>`
      )
      .join("");

    tbody.innerHTML = this.data.students
      .map((student) => {
        const fullName = `${student.first_name || ""} ${
          student.last_name || ""
        }`.trim();
        return `
          <tr data-student-id="${student.id}">
            <td><input type="checkbox" class="student-select" data-id="${student.id}"></td>
            <td>${student.admission_no || "-"}</td>
            <td>${fullName || "-"}</td>
            <td>${student.stream_name || "-"}</td>
            <td>-</td>
            <td>
              <select class="form-select form-select-sm promote-stream">
                <option value="">Select Target Stream</option>
                ${streamOptions}
              </select>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="StudentPromotionController.promoteSingle(${student.id})">
                Promote
              </button>
            </td>
          </tr>
        `;
      })
      .join("");
  },

  getSelectedStudentIds: function () {
    return Array.from(document.querySelectorAll(".student-select:checked")).map(
      (checkbox) => parseInt(checkbox.dataset.id, 10)
    );
  },

  getTargetStreamIdForStudent: function (studentId) {
    const row = document.querySelector(`tr[data-student-id="${studentId}"]`);
    if (!row) return null;
    const select = row.querySelector(".promote-stream");
    return select?.value ? parseInt(select.value, 10) : null;
  },

  processPromotion: async function () {
    const fromYearId = document.getElementById("fromYear").value;
    const toYearId = document.getElementById("toYear").value;

    if (!fromYearId || !toYearId) {
      this.showError("Please select both academic years");
      return;
    }

    const selectedIds = this.getSelectedStudentIds().filter(
      (id) => !this.data.retainedIds.has(id)
    );

    if (!selectedIds.length) {
      this.showError("Select at least one student to promote");
      return;
    }

    const errors = [];
    for (const studentId of selectedIds) {
      const streamId = this.getTargetStreamIdForStudent(studentId);
      const classId = this.data.streamMap[streamId];
      if (!streamId || !classId) {
        errors.push(studentId);
        continue;
      }

      try {
        await window.API.students.promoteSingle({
          student_id: studentId,
          to_class_id: classId,
          to_stream_id: streamId,
          from_year_id: fromYearId,
          to_year_id: toYearId,
        });
      } catch (error) {
        errors.push(studentId);
      }
    }

    if (errors.length) {
      this.showError(
        `Promotion completed with ${errors.length} errors. Please review selections.`
      );
    } else {
      this.showSuccess("Promotion processed successfully");
    }

    await this.loadStudents();
  },

  promoteSingle: async function (studentId) {
    const fromYearId = document.getElementById("fromYear").value;
    const toYearId = document.getElementById("toYear").value;
    if (!fromYearId || !toYearId) {
      this.showError("Please select both academic years");
      return;
    }

    const streamId = this.getTargetStreamIdForStudent(studentId);
    const classId = this.data.streamMap[streamId];

    if (!streamId || !classId) {
      this.showError("Select a target stream for this student");
      return;
    }

    try {
      await window.API.students.promoteSingle({
        student_id: studentId,
        to_class_id: classId,
        to_stream_id: streamId,
        from_year_id: fromYearId,
        to_year_id: toYearId,
      });
      this.showSuccess("Student promoted successfully");
      await this.loadStudents();
    } catch (error) {
      this.showError(error.message || "Failed to promote student");
    }
  },

  promoteAll: function () {
    document.querySelectorAll(".student-select").forEach((checkbox) => {
      checkbox.checked = true;
    });
    this.processPromotion();
  },

  retainSelected: function () {
    const selectedIds = this.getSelectedStudentIds();
    if (!selectedIds.length) {
      this.showError("Select students to retain");
      return;
    }

    selectedIds.forEach((id) => {
      this.data.retainedIds.add(id);
      const row = document.querySelector(`tr[data-student-id="${id}"]`);
      if (row) {
        row.classList.add("table-warning");
        const select = row.querySelector(".promote-stream");
        if (select) select.disabled = true;
      }
    });

    this.showSuccess("Selected students marked for retention");
  },

  unwrapPayload: function (response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined) return response.data.data;
    return response;
  },

  showSuccess: function (message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "success");
    } else {
      alert(message);
    }
  },

  showError: function (message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "error");
    } else {
      alert("Error: " + message);
    }
  },
};

document.addEventListener("DOMContentLoaded", () =>
  StudentPromotionController.init()
);

window.StudentPromotionController = StudentPromotionController;
