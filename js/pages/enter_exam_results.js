const ExamResultsController = {
  initialized: false,
  state: { exams: [], filteredExams: [], context: null, selectedExamId: null },
  autosaveTimer: null,

  draftKey() {
    const user = window.AuthContext?.getUser?.() || {};
    const uid = Number(user.id || user.user_id || 0);
    return `kingsway:exam-marks:v1:u${uid}:a${Number(this.state.context?.exam?.assessment_id || 0)}`;
  },

  persistLocalDraft() {
    if (!this.state.context?.editable) return;
    const rows = [...document.querySelectorAll("#resultsTableBody tr[data-student-id]")].map((row) => ({
      student_id: Number(row.dataset.studentId),
      entry_status: row.querySelector(".entry-status")?.value || "present",
      marks_obtained: row.querySelector(".score-input")?.value ?? "",
      remarks: row.querySelector(".remarks-input")?.value || "",
    }));
    try { localStorage.setItem(this.draftKey(), JSON.stringify({ saved_at: Date.now(), rows })); } catch (_) { /* Storage may be disabled or full. */ }
  },

  restoreLocalDraft() {
    if (!this.state.context?.editable) return;
    try {
      const stored = JSON.parse(localStorage.getItem(this.draftKey()) || "null");
      if (!stored || !Array.isArray(stored.rows)) return;
      const byId = new Map(stored.rows.map((row) => [Number(row.student_id), row]));
      document.querySelectorAll("#resultsTableBody tr[data-student-id]").forEach((row) => {
        const saved = byId.get(Number(row.dataset.studentId));
        if (!saved) return;
        row.querySelector(".entry-status").value = saved.entry_status || "present";
        const score = row.querySelector(".score-input");
        score.disabled = row.querySelector(".entry-status").value !== "present";
        score.value = saved.marks_obtained ?? "";
        row.querySelector(".remarks-input").value = saved.remarks || "";
        this.renderGrade(row);
      });
      window.showNotification?.("Unsubmitted marks restored from this device.", "info");
    } catch (_) { /* Ignore malformed stale browser state. */ }
  },

  escape(value) {
    const node = document.createElement("span");
    node.textContent = String(value ?? "");
    return node.innerHTML;
  },

  unwrap(response) {
    return response && Object.prototype.hasOwnProperty.call(response, "data") ? response.data : response;
  },

  async init() {
    if (this.initialized) return;
    await window.AuthContext?.ready?.();
    if (!window.AuthContext?.isAuthenticated?.()) return;
    await window.GradingScale?.preload?.();
    if (window.AcademicContext && !AcademicContext.isLoaded()) await AcademicContext.init();
    this.initialized = true;
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('academic')) document.getElementById('examRegisterCsv')?.classList.add('d-none');
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('academic')) document.getElementById('examRegisterPrint')?.classList.add('d-none');
    this.setupEventListeners();
    await this.loadExams();
  },

  setupEventListeners() {
    document.getElementById("classFilter")?.addEventListener("change", () => this.applyFilters());
    document.getElementById("subjectFilter")?.addEventListener("change", () => this.applyFilters());
    document.getElementById("examFilter")?.addEventListener("change", (event) => this.loadEntry(Number(event.target.value)));
    document.getElementById("refreshExamsBtn")?.addEventListener("click", () => this.loadExams());
    document.getElementById("saveDraftBtn")?.addEventListener("click", () => this.save(false));
    document.getElementById("submitResultsBtn")?.addEventListener("click", () => this.save(true));
    document.getElementById("examRegisterCsv")?.addEventListener("click", () => this.exportRegister());
    document.getElementById("examRegisterPrint")?.addEventListener("click", () => window.print());
    document.getElementById("resultsTableBody")?.addEventListener("input", (event) => {
      if (event.target.matches(".score-input")) this.renderGrade(event.target.closest("tr"));
      this.updateProgress();
      clearTimeout(this.autosaveTimer);
      this.autosaveTimer = setTimeout(() => this.persistLocalDraft(), 250);
    });
    document.getElementById("resultsTableBody")?.addEventListener("change", (event) => {
      if (!event.target.matches(".entry-status")) return;
      const row = event.target.closest("tr");
      const score = row.querySelector(".score-input");
      const present = event.target.value === "present";
      score.disabled = !present || !this.state.context?.editable;
      if (!present) score.value = "";
      this.renderGrade(row);
      this.updateProgress();
      this.persistLocalDraft();
    });
    window.AcademicContext?.subscribe?.((_context, event) => {
      if (["yearChanged", "termChanged", "initialized", "refreshed"].includes(event)) this.loadExams();
    });
  },

  async loadExams() {
    try {
      const params = { limit: 1000 };
      const yearId = window.AcademicContext?.getAcademicYearId?.();
      const termId = window.AcademicContext?.getTermId?.();
      if (yearId) params.academic_year_id = yearId;
      if (termId) params.term_id = termId;
      const payload = this.unwrap(await window.API.academic.listExamSchedules(params)) || {};
      this.state.exams = (payload.exams || []).filter((exam) =>
        Number(exam.assessment_id) > 0 &&
        (!exam.exam_period_id || ['results_open', 'moderation'].includes(exam.exam_period_status))
      );
      this.populateFilters();
      this.applyFilters();
      const requestedScheduleId = Number(new URLSearchParams(window.location.search).get("exam_schedule_id") || 0);
      if (requestedScheduleId) {
        const requested = this.state.filteredExams.find((exam) => Number(exam.exam_schedule_id) === requestedScheduleId || Number(exam.id) === requestedScheduleId);
        if (requested) {
          const examSelect = document.getElementById("examFilter");
          examSelect.value = String(requested.id);
          await this.loadEntry(Number(requested.id));
        }
      }
      document.getElementById("examAcademicContext").textContent =
        window.AcademicContext?.getContextLabel?.() || "Current academic context";
    } catch (error) {
      window.showNotification?.(error.message || "Unable to load examinations open for result entry", "error");
    }
  },

  populateFilters() {
    const uniqueOptions = (key, label) => {
      const values = new Map();
      this.state.exams.forEach((exam) => values.set(String(exam[key]), label(exam)));
      return [...values.entries()].sort((a, b) => a[1].localeCompare(b[1]));
    };
    const classOptions = uniqueOptions("class_id", (exam) =>
      [exam.class_name, exam.stream_name].filter(Boolean).join(" — "),
    );
    const areaOptions = uniqueOptions("subject_id", (exam) => exam.subject_name || "Learning area");
    const fill = (id, first, options) => {
      const select = document.getElementById(id);
      const selected = select.value;
      select.innerHTML = `<option value="">${first}</option>` + options
        .map(([value, label]) => `<option value="${this.escape(value)}">${this.escape(label)}</option>`)
        .join("");
      if ([...select.options].some((option) => option.value === selected)) select.value = selected;
    };
    fill("classFilter", "All assigned classes", classOptions);
    fill("subjectFilter", "All assigned learning areas", areaOptions);
  },

  applyFilters() {
    const classId = document.getElementById("classFilter")?.value || "";
    const subjectId = document.getElementById("subjectFilter")?.value || "";
    this.state.filteredExams = this.state.exams.filter((exam) =>
      (!classId || String(exam.class_id) === classId) &&
      (!subjectId || String(exam.subject_id) === subjectId),
    );
    const select = document.getElementById("examFilter");
    select.innerHTML = '<option value="">Select an examination paper</option>' + this.state.filteredExams
      .map((exam) => {
        const className = [exam.class_name, exam.stream_name].filter(Boolean).join(" — ");
        const label = `${exam.exam_name || exam.assessment_title} · ${className} · ${exam.subject_name}`;
        return `<option value="${Number(exam.id)}">${this.escape(label)}</option>`;
      }).join("");
    this.clearWorkspace();
  },

  async loadEntry(examScheduleId) {
    if (!examScheduleId) return this.clearWorkspace();
    try {
      const context = this.unwrap(await window.API.academic.getExamResultEntry(examScheduleId));
      this.state.context = context;
      this.state.selectedExamId = examScheduleId;
      this.render();
    } catch (error) {
      this.clearWorkspace();
      window.showNotification?.(error.message || "This examination is outside your teaching assignment", "error");
    }
  },

  clearWorkspace() {
    this.state.context = null;
    this.state.selectedExamId = null;
    document.getElementById("examEntryWorkspace")?.classList.add("d-none");
    document.getElementById("examEmptyState")?.classList.remove("d-none");
  },

  render() {
    const { exam, students, editable } = this.state.context;
    document.getElementById("examEmptyState").classList.add("d-none");
    document.getElementById("examEntryWorkspace").classList.remove("d-none");
    document.getElementById("examName").textContent = exam.assessment_title || exam.exam_name;
    document.getElementById("examDetails").textContent = [
      [exam.class_name, exam.stream_name].filter(Boolean).join(" — "), exam.learning_area_name,
      exam.assessment_type_name, `${exam.exam_date} ${String(exam.start_time || "").slice(0, 5)}`,
      `Maximum ${Number(exam.max_marks)} marks`,
    ].filter(Boolean).join(" · ");
    const status = document.getElementById("assessmentStatus");
    status.textContent = String(exam.assessment_status || "").replaceAll("_", " ");
    status.className = `badge ${exam.assessment_status === "approved" ? "bg-success" : exam.assessment_status === "submitted" ? "bg-warning text-dark" : "bg-primary"}`;

    document.getElementById("resultsTableBody").innerHTML = students.map((student, index) => {
      const entryStatus = student.entry_status || "present";
      const nonPresent = entryStatus !== "present";
      const name = [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(" ");
      return `<tr data-student-id="${Number(student.student_id)}">
        <td>${index + 1}</td><td>${this.escape(student.admission_no || "—")}</td><td><strong>${this.escape(name)}</strong></td>
        <td><select class="form-select form-select-sm entry-status" ${editable ? "" : "disabled"}>
          <option value="present" ${entryStatus === "present" ? "selected" : ""}>Present</option>
          <option value="absent" ${entryStatus === "absent" ? "selected" : ""}>Absent</option>
          <option value="exempted" ${entryStatus === "exempted" ? "selected" : ""}>Exempted</option>
        </select></td>
        <td><div class="input-group input-group-sm"><input type="number" class="form-control score-input" min="0" max="${Number(exam.max_marks)}" step="0.01"
          value="${student.marks_obtained === null ? "" : this.escape(student.marks_obtained)}" ${editable && !nonPresent ? "" : "disabled"}>
          <span class="input-group-text">/${Number(exam.max_marks)}</span></div></td>
        <td><span class="grade-display badge bg-secondary">${this.escape(student.grade || "—")}</span></td>
        <td><input class="form-control form-control-sm remarks-input" maxlength="255" value="${this.escape(student.remarks || "")}" ${editable ? "" : "disabled"}></td>
        <td class="small ${student.moderation_note ? "text-danger" : "text-muted"}">${this.escape(student.moderation_note || (student.is_approved == 1 ? "Approved" : "—"))}</td>
      </tr>`;
    }).join("") || '<tr><td colspan="8" class="text-center text-muted py-4">No active learners are enrolled in this class stream.</td></tr>';

    document.querySelectorAll("#resultsTableBody tr[data-student-id]").forEach((row) => this.renderGrade(row));
    this.restoreLocalDraft();
    document.getElementById("saveDraftBtn").disabled = !editable;
    document.getElementById("submitResultsBtn").disabled = !editable || !students.length;
    const lifecycle = document.getElementById("resultLifecycleMessage");
    if (editable) lifecycle.classList.add("d-none");
    else {
      lifecycle.className = `alert ${exam.assessment_status === "approved" ? "alert-success" : "alert-warning"}`;
      lifecycle.textContent = exam.assessment_status === "approved"
        ? "These official scores are approved and locked."
        : "These scores are submitted and locked while academic leadership moderates them.";
    }
    this.updateProgress();
  },

  renderGrade(row) {
    if (!row?.dataset.studentId) return;
    const entryStatus = row.querySelector(".entry-status").value;
    const input = row.querySelector(".score-input");
    const badge = row.querySelector(".grade-display");
    const code = entryStatus === "present" && input.value !== ""
      ? window.GradingScale?.grade?.(Number(input.value), Number(this.state.context.exam.max_marks)) || ""
      : "";
    const band = window.GradingScale?.band?.(code) || "";
    badge.textContent = code || (entryStatus === "present" ? "—" : entryStatus.toUpperCase());
    badge.className = `grade-display badge ${band === "EE" ? "bg-success" : band === "ME" ? "bg-primary" : band === "AE" ? "bg-warning text-dark" : band === "BE" ? "bg-danger" : "bg-secondary"}`;
  },

  collect(submit) {
    const rows = [];
    for (const row of document.querySelectorAll("#resultsTableBody tr[data-student-id]")) {
      const entryStatus = row.querySelector(".entry-status").value;
      const value = row.querySelector(".score-input").value;
      const complete = entryStatus !== "present" || value !== "";
      if (submit && !complete) throw new Error("Every learner must have marks, Absent, or Exempted status before submission.");
      if (!complete) continue;
      rows.push({
        student_id: Number(row.dataset.studentId), entry_status: entryStatus,
        marks_obtained: entryStatus === "present" ? Number(value) : null,
        remarks: row.querySelector(".remarks-input").value.trim(),
      });
    }
    if (!rows.length) throw new Error("Enter at least one learner result before saving.");
    return rows;
  },

  async save(submit) {
    try {
      const rows = this.collect(submit);
      if (submit && window.confirmAction && !(await window.confirmAction("Submit scores", "Submit this complete score register for moderation? You cannot edit it after submission."))) return;
      const button = document.getElementById(submit ? "submitResultsBtn" : "saveDraftBtn");
      button.disabled = true;
      await window.API.academic.markAndGrade({
        assessment_id: Number(this.state.context.exam.assessment_id), grading_data: rows, is_final: submit,
      });
      if (submit) localStorage.removeItem(this.draftKey());
      else this.persistLocalDraft();
      window.showNotification?.(submit ? "Scores submitted for moderation" : "Score draft saved", "success");
      await this.loadEntry(this.state.selectedExamId);
    } catch (error) {
      window.showNotification?.(error.message || "Unable to save examination scores", "error");
    }
  },

  updateProgress() {
    const rows = [...document.querySelectorAll("#resultsTableBody tr[data-student-id]")];
    const complete = rows.filter((row) => {
      const entryStatus = row.querySelector(".entry-status").value;
      return entryStatus !== "present" || row.querySelector(".score-input").value !== "";
    }).length;
    document.getElementById("entryProgress").textContent = `${complete} of ${rows.length} complete`;
  },

  exportRegister() {
    const rows = [...document.querySelectorAll("#resultsTableBody tr[data-student-id]")].map((row) => {
      const cells = [...row.querySelectorAll("td")];
      return [cells[0]?.innerText, cells[1]?.innerText, cells[2]?.innerText,
        row.querySelector(".entry-status")?.value, row.querySelector(".score-input")?.value,
        cells[5]?.innerText, row.querySelector(".remarks-input")?.value].map((value) => `"${String(value ?? "").replaceAll('"','""')}"`).join(",");
    });
    const csv = ['"#","Admission no.","Learner","Status","Marks","Grade","Comment"', ...rows].join("\r\n");
    if (window.AuthContext?.canExport && !window.AuthContext.canExport("academic")) return;
    window.KingswayFileLifecycle?.exportText(csv, `exam-register-${this.state.selectedExamId || 'draft'}.csv`, "text/csv");
  },
};

document.addEventListener("DOMContentLoaded", () => ExamResultsController.init());

window.ExamResultsController = ExamResultsController;
