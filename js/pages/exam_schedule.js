/**
 * Exam Schedule Page Controller
 * Manages exam schedule CRUD and viewing workflow using api.js
 */

const ExamScheduleController = (() => {
  // Private state
  const state = {
    exams: [],
    classes: [],
    subjects: [],
    teachers: [],
    pagination: { page: 1, limit: 10, total: 0 },
    summary: { total: 0, upcoming: 0, in_progress: 0, completed: 0 },
  };

  const filters = {
    term: "",
    class_id: "",
    subject: "",
    status: "",
  };

  // ---- Helpers ----

  function unwrapPayload(response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined)
      return response.data.data;
    return response;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function showSuccess(message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "success");
    } else {
      alert(message);
    }
  }

  function showError(message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "error");
    } else {
      alert("Error: " + message);
    }
  }

  function statusBadge(status) {
    const map = {
      upcoming: "info",
      in_progress: "warning",
      completed: "success",
      postponed: "secondary",
      cancelled: "danger",
    };
    return map[status] || "secondary";
  }

  function formatStatus(status) {
    const map = {
      upcoming: "Upcoming",
      in_progress: "In Progress",
      completed: "Completed",
      postponed: "Postponed",
      cancelled: "Cancelled",
    };
    return map[status] || status || "-";
  }

  function formatTime(time) {
    if (!time) return "-";
    const [h, m] = time.split(":");
    const hour = parseInt(h, 10);
    const ampm = hour >= 12 ? "PM" : "AM";
    const display = hour > 12 ? hour - 12 : hour === 0 ? 12 : hour;
    return `${display}:${m} ${ampm}`;
  }

  // ---- Data Loading ----

  async function loadReferenceData() {
    try {
      const classResp = await window.API.academic.listClasses();
      const classPayload = unwrapPayload(classResp);
      state.classes = Array.isArray(classPayload) ? classPayload : [];
      populateClassDropdowns();
    } catch (error) {
      console.warn("Failed to load classes", error);
    }

    try {
      const subjectResp = await window.API.apiCall("/academic/subjects", "GET");
      const subjectPayload = unwrapPayload(subjectResp);
      state.subjects = Array.isArray(subjectPayload) ? subjectPayload : [];
      populateSubjectDropdowns();
    } catch (error) {
      console.warn("Failed to load subjects", error);
    }

    try {
      const staffResp = await window.API.apiCall(
        "/staff/staff?limit=500",
        "GET",
      );
      const staffPayload = unwrapPayload(staffResp);
      const teachers = staffPayload?.staff || staffPayload || [];
      state.teachers = Array.isArray(teachers) ? teachers : [];
      populateSupervisorDropdown();
    } catch (error) {
      console.warn("Failed to load teachers", error);
    }
  }

  function populateClassDropdowns() {
    const filterSelect = document.getElementById("classFilter");
    const formSelect = document.getElementById("examClass");

    state.classes.forEach((cls) => {
      const name = cls.name || cls.class_name;
      if (filterSelect) {
        const opt = document.createElement("option");
        opt.value = cls.id;
        opt.textContent = name;
        filterSelect.appendChild(opt);
      }
      if (formSelect) {
        const opt = document.createElement("option");
        opt.value = cls.id;
        opt.textContent = name;
        formSelect.appendChild(opt);
      }
    });
  }

  function populateSubjectDropdowns() {
    const filterSelect = document.getElementById("subjectFilter");
    const formSelect = document.getElementById("examSubject");

    state.subjects.forEach((subj) => {
      const name = subj.name || subj.subject_name;
      if (filterSelect) {
        const opt = document.createElement("option");
        opt.value = subj.id;
        opt.textContent = name;
        filterSelect.appendChild(opt);
      }
      if (formSelect) {
        const opt = document.createElement("option");
        opt.value = subj.id;
        opt.textContent = name;
        formSelect.appendChild(opt);
      }
    });
  }

  function populateSupervisorDropdown() {
    const select = document.getElementById("examSupervisor");
    if (!select) return;

    state.teachers.forEach((teacher) => {
      const opt = document.createElement("option");
      opt.value = teacher.id;
      opt.textContent =
        `${teacher.first_name || ""} ${teacher.last_name || ""}`.trim();
      select.appendChild(opt);
    });
  }

  async function loadData(page = 1) {
    try {
      state.pagination.page = page;

      const params = new URLSearchParams({
        page,
        limit: state.pagination.limit,
      });

      if (filters.term) params.append("term", filters.term);
      if (filters.class_id) params.append("class_id", filters.class_id);
      if (filters.subject) params.append("subject_id", filters.subject);
      if (filters.status) params.append("status", filters.status);

      const resp = await window.API.apiCall(
        `/academic/exam-schedule?${params.toString()}`,
        "GET",
      );

      const payload = unwrapPayload(resp) || {};
      state.exams = payload.exams || payload.data || [];
      if (!Array.isArray(state.exams)) state.exams = [];

      state.pagination = payload.pagination || state.pagination;
      state.summary = payload.summary || computeSummary(state.exams);

      renderSummary();
      renderTable();
      renderPagination();
    } catch (error) {
      console.error("Error loading exam schedule:", error);
      showError("Failed to load exam schedule");
    }
  }

  function computeSummary(exams) {
    return {
      total: exams.length,
      upcoming: exams.filter((e) => e.status === "upcoming").length,
      in_progress: exams.filter((e) => e.status === "in_progress").length,
      completed: exams.filter((e) => e.status === "completed").length,
    };
  }

  // ---- Rendering ----

  function renderSummary() {
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalExams", state.summary.total || 0);
    el("upcomingExams", state.summary.upcoming || 0);
    el("inProgressExams", state.summary.in_progress || 0);
    el("completedExams", state.summary.completed || 0);
  }

  function renderTable() {
    const tbody = document.querySelector("#examScheduleTable tbody");
    if (!tbody) return;

    if (!state.exams.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="10" class="text-center text-muted py-4">No exams scheduled</td>
        </tr>`;
      return;
    }

    tbody.innerHTML = state.exams
      .map((exam) => {
        const examName = exam.exam_name || exam.name || "-";
        const subject = exam.subject_name || "-";
        const className = exam.class_name || "-";
        const date = exam.exam_date || exam.date || "-";
        const time = formatTime(exam.start_time || exam.time);
        const duration = exam.duration ? `${exam.duration} min` : "-";
        const venue = exam.venue || "-";
        const supervisor =
          exam.supervisor_name ||
          `${exam.supervisor_first_name || ""} ${exam.supervisor_last_name || ""}`.trim() ||
          "-";
        const status = exam.status || "upcoming";

        return `
          <tr>
            <td>${escapeHtml(examName)}</td>
            <td>${escapeHtml(subject)}</td>
            <td>${escapeHtml(className)}</td>
            <td>${escapeHtml(date)}</td>
            <td>${escapeHtml(time)}</td>
            <td>${escapeHtml(duration)}</td>
            <td>${escapeHtml(venue)}</td>
            <td>${escapeHtml(supervisor)}</td>
            <td><span class="badge bg-${statusBadge(status)}">${formatStatus(status)}</span></td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" onclick="ExamScheduleController.editExam(${exam.id})" title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="ExamScheduleController.deleteExam(${exam.id})" title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>`;
      })
      .join("");
  }

  function renderPagination() {
    const container = document.getElementById("pagination");
    if (!container) return;

    const { page, total, limit } = state.pagination;
    const totalPages = Math.ceil(total / limit) || 1;

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `
        <li class="page-item ${i === page ? "active" : ""}">
          <a class="page-link" href="#" onclick="ExamScheduleController.loadPage(${i}); return false;">${i}</a>
        </li>`;
    }
    container.innerHTML = html;
  }

  // ---- CRUD Actions ----

  function openExamModal(examId = null) {
    const modalEl = document.getElementById("examModal");
    const form = document.getElementById("examForm");
    if (!modalEl || !form) return;

    form.reset();
    document.getElementById("examId").value = "";
    document.getElementById("examModalTitle").textContent = "Add Exam Schedule";

    if (examId) {
      const record = state.exams.find((e) => e.id == examId);
      if (record) {
        document.getElementById("examId").value = record.id;
        document.getElementById("examModalTitle").textContent =
          "Edit Exam Schedule";
        document.getElementById("examName").value =
          record.exam_name || record.name || "";
        document.getElementById("examSubject").value = record.subject_id || "";
        document.getElementById("examClass").value = record.class_id || "";
        document.getElementById("examTerm").value = record.term || "";
        document.getElementById("examDate").value =
          record.exam_date || record.date || "";
        document.getElementById("examTime").value =
          record.start_time || record.time || "";
        document.getElementById("examDuration").value = record.duration || "";
        document.getElementById("examVenue").value = record.venue || "";
        document.getElementById("examSupervisor").value =
          record.supervisor_id || "";
        document.getElementById("examNotes").value = record.notes || "";
      }
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  async function saveExam() {
    const examId = document.getElementById("examId").value;

    const payload = {
      exam_name: document.getElementById("examName").value,
      subject_id: document.getElementById("examSubject").value,
      class_id: document.getElementById("examClass").value,
      term: document.getElementById("examTerm").value,
      exam_date: document.getElementById("examDate").value,
      start_time: document.getElementById("examTime").value,
      duration:
        parseInt(document.getElementById("examDuration").value, 10) || 0,
      venue: document.getElementById("examVenue").value,
      supervisor_id: document.getElementById("examSupervisor").value,
      notes: document.getElementById("examNotes").value,
    };

    if (
      !payload.exam_name ||
      !payload.subject_id ||
      !payload.class_id ||
      !payload.exam_date
    ) {
      showError("Please fill in all required fields");
      return;
    }

    try {
      if (examId) {
        await window.API.apiCall(
          `/academic/exam-schedule/${examId}`,
          "PUT",
          payload,
        );
        showSuccess("Exam schedule updated successfully");
      } else {
        await window.API.apiCall("/academic/exam-schedule", "POST", payload);
        showSuccess("Exam scheduled successfully");
      }

      bootstrap.Modal.getInstance(document.getElementById("examModal")).hide();
      await loadData(state.pagination.page);
    } catch (error) {
      console.error("Error saving exam:", error);
      showError(error.message || "Failed to save exam schedule");
    }
  }

  async function deleteExam(examId) {
    if (!confirm("Are you sure you want to delete this exam schedule?")) return;

    try {
      await window.API.apiCall(`/academic/exam-schedule/${examId}`, "DELETE");
      showSuccess("Exam schedule deleted successfully");
      await loadData(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to delete exam schedule");
    }
  }

  function exportSchedule() {
    if (!state.exams.length) {
      showError("No data to export");
      return;
    }

    const rows = [
      "Exam Name,Subject,Class,Date,Time,Duration,Venue,Supervisor,Status",
    ];
    state.exams.forEach((e) => {
      rows.push(
        `"${e.exam_name || ""}","${e.subject_name || ""}","${e.class_name || ""}","${e.exam_date || ""}","${e.start_time || ""}","${e.duration || ""} min","${e.venue || ""}","${e.supervisor_name || ""}","${e.status || ""}"`,
      );
    });

    const blob = new Blob([rows.join("\n")], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "exam_schedule.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  // ---- Event Listeners ----

  function attachEventListeners() {
    document
      .getElementById("addExamBtn")
      ?.addEventListener("click", () => openExamModal());

    document
      .getElementById("saveExamBtn")
      ?.addEventListener("click", () => saveExam());

    document
      .getElementById("exportScheduleBtn")
      ?.addEventListener("click", () => exportSchedule());

    document
      .getElementById("printScheduleBtn")
      ?.addEventListener("click", () => window.print());

    // Filters
    document.getElementById("termFilter")?.addEventListener("change", (e) => {
      filters.term = e.target.value;
      loadData(1);
    });

    document.getElementById("classFilter")?.addEventListener("change", (e) => {
      filters.class_id = e.target.value;
      loadData(1);
    });

    document
      .getElementById("subjectFilter")
      ?.addEventListener("change", (e) => {
        filters.subject = e.target.value;
        loadData(1);
      });

    document.getElementById("statusFilter")?.addEventListener("change", (e) => {
      filters.status = e.target.value;
      loadData(1);
    });
  }

  // ---- Initialization ----

  async function init() {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    attachEventListeners();
    await loadReferenceData();
    await loadData();
  }

  // ---- Public API ----
  return {
    init,
    refresh: loadData,
    loadPage: loadData,
    editExam: openExamModal,
    deleteExam,
  };
})();

document.addEventListener("DOMContentLoaded", () =>
  ExamScheduleController.init(),
);

window.ExamScheduleController = ExamScheduleController;
