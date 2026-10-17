const ExamTimetableDraftsController = {
  initialized: false,
  state: { draft: null, drafts: [], streams: [], types: [], staff: [], yearId: null, termId: null, referenceCache: null },

  escape(value) {
    const node = document.createElement("span");
    node.textContent = String(value ?? "");
    return node.innerHTML;
  },

  unwrap(response) {
    return response && Object.prototype.hasOwnProperty.call(response, "data") ? response.data : response;
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("academic", "exam-timetable-drafts-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Exam timetable drafts Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    if (this.initialized) return;
    await window.AuthContext?.ready?.();
    if (!window.AuthContext?.isAuthenticated?.()) return;
    if (window.AcademicContext && !AcademicContext.isLoaded()) await AcademicContext.init();
    this.state.yearId = Number(window.AcademicContext?.getAcademicYearId?.() || 0);
    this.state.termId = Number(window.AcademicContext?.getTermId?.() || 0);
    this.bindEvents();
    await this.loadReferencesComposed();
    await this.loadReferences();
    await this.loadDrafts();
    window.APIRealtime?.register?.("exam_timetable_drafts", { refresh: async () => { this.state.referenceCache = null; await this.loadReferencesComposed(); await Promise.all([this.loadReferences(), this.loadDrafts()]); } }, ["academic", "schedules"]);
    this.initialized = true;
  },

  bindEvents() {
    document.getElementById("newExamDraft")?.addEventListener("click", () => this.open());
    document.getElementById("addExamRow")?.addEventListener("click", () => this.addRow());
    document.getElementById("saveExamDraft")?.addEventListener("click", () => this.save(false));
    document.getElementById("submitExamDraft")?.addEventListener("click", () => this.save(true));
    document.getElementById("examEntryRows")?.addEventListener("click", (event) => {
      if (event.target.closest(".remove-exam-row")) event.target.closest("tr").remove();
    });
    document.getElementById("examEntryRows")?.addEventListener("change", (event) => {
      if (event.target.matches(".exam-stream")) this.populateAreaForRow(event.target.closest("tr"));
    });
  },

  async loadReferences() {
    const cache = this.state.referenceCache;
    const [streamsResponse, typesResponse, staffResponse] = await Promise.all([
      window.API.schedules.listTimetableStreams({ academic_year_id: this.state.yearId }),
      cache?.types !== undefined ? cache.types : window.API.academic.getAssessmentTypes({ filter: "summative" }),
      cache?.staff !== undefined ? cache.staff : window.API.apiCall("/staff/staff", "GET", null, { limit: 1000 }),
    ]);
    this.state.streams = this.unwrap(streamsResponse) || [];
    this.state.types = this.unwrap(typesResponse) || [];
    const staffPayload = this.unwrap(staffResponse) || {};
    this.state.staff = staffPayload.staff || (Array.isArray(staffPayload) ? staffPayload : []);
  },

  async loadDrafts() {
    try {
      this.state.drafts = this.unwrap(await window.API.schedules.listExamTimetableDrafts()) || [];
      this.renderDrafts();
    } catch (error) {
      window.showNotification?.(error.message || "Unable to load exam timetable drafts", "error");
    }
  },

  renderDrafts() {
    const tbody = document.getElementById("examDraftRows");
    tbody.innerHTML = this.state.drafts.map((draft) => {
      const actions = [`<button class="btn btn-sm btn-outline-secondary" onclick="ExamTimetableDraftsController.open(${Number(draft.id)})">${["draft", "changes_requested"].includes(draft.status) ? "Edit" : "View"}</button>`];
      if (draft.status === "draft" || draft.status === "changes_requested") actions.push(this.actionButton(draft.id, "submit", "Submit", "primary"));
      if (draft.status === "submitted") {
        actions.push(this.actionButton(draft.id, "approve", "Approve", "success"));
        actions.push(this.actionButton(draft.id, "request_changes", "Request changes", "warning"));
      }
      if (draft.status === "approved") actions.push(this.actionButton(draft.id, "publish", "Publish", "primary"));
      const colour = draft.status === "published" ? "success" : draft.status === "changes_requested" ? "danger" : draft.status === "approved" ? "info" : "secondary";
      return `<tr><td><strong>${this.escape(draft.title)}</strong></td><td>${Number(draft.entry_count || 0)}</td>
        <td><span class="badge bg-${colour}">${this.escape(String(draft.status).replaceAll("_", " "))}</span></td>
        <td>${this.escape(draft.updated_at || "—")}</td><td><div class="d-flex flex-wrap gap-1">${actions.join("")}</div></td></tr>`;
    }).join("") || '<tr><td colspan="5" class="text-center text-muted py-4">No exam timetable drafts in this academic context.</td></tr>';
  },

  actionButton(id, action, label, colour) {
    return `<button class="btn btn-sm btn-outline-${colour}" onclick="ExamTimetableDraftsController.transition(${Number(id)}, '${action}')">${label}</button>`;
  },

  streamOptions(selected) {
    return '<option value="">Select class</option>' + this.state.streams.map((stream) => {
      const id = Number(stream.academic_year_class_stream_id);
      const label = [stream.class_name, stream.stream_name].filter(Boolean).join(" — ");
      return `<option value="${id}" ${id === Number(selected) ? "selected" : ""}>${this.escape(label)}</option>`;
    }).join("");
  },

  areaOptions(streamId, selected) {
    const stream = this.state.streams.find((item) => Number(item.academic_year_class_stream_id) === Number(streamId));
    return '<option value="">Select area</option>' + (stream?.learning_areas || []).map((area) =>
      `<option value="${Number(area.id)}" ${Number(area.id) === Number(selected) ? "selected" : ""}>${this.escape(area.name)}</option>`,
    ).join("");
  },

  typeOptions(selected) {
    return '<option value="">Select type</option>' + this.state.types.map((type) =>
      `<option value="${Number(type.id)}" ${Number(type.id) === Number(selected) ? "selected" : ""}>${this.escape(type.name)}</option>`,
    ).join("");
  },

  staffOptions(selected) {
    return '<option value="">Unassigned</option>' + this.state.staff.map((staff) => {
      const label = [staff.first_name, staff.middle_name, staff.last_name].filter(Boolean).join(" ") || staff.name || `Staff ${staff.id}`;
      return `<option value="${Number(staff.id)}" ${Number(staff.id) === Number(selected) ? "selected" : ""}>${this.escape(label)}</option>`;
    }).join("");
  },

  addRow(entry = {}) {
    const tbody = document.getElementById("examEntryRows");
    const row = document.createElement("tr");
    row.innerHTML = `<td><select class="form-select form-select-sm exam-stream">${this.streamOptions(entry.academic_year_class_stream_id)}</select></td>
      <td><select class="form-select form-select-sm exam-area">${this.areaOptions(entry.academic_year_class_stream_id, entry.learning_area_id)}</select></td>
      <td><input class="form-control form-control-sm exam-name" maxlength="255" value="${this.escape(entry.exam_name || "")}"></td>
      <td><select class="form-select form-select-sm exam-type">${this.typeOptions(entry.assessment_type_id)}</select></td>
      <td><input class="form-control form-control-sm exam-max" type="number" min="1" step="0.01" value="${this.escape(entry.max_marks || 100)}"></td>
      <td><input class="form-control form-control-sm exam-date" type="date" value="${this.escape(entry.exam_date || "")}"></td>
      <td><input class="form-control form-control-sm exam-start" type="time" value="${this.escape(String(entry.start_time || "").slice(0, 5))}"></td>
      <td><input class="form-control form-control-sm exam-end" type="time" value="${this.escape(String(entry.end_time || "").slice(0, 5))}"></td>
      <td><input class="form-control form-control-sm exam-venue" maxlength="100" value="${this.escape(entry.venue || "")}"></td>
      <td><select class="form-select form-select-sm exam-invigilator">${this.staffOptions(entry.invigilator_id)}</select></td>
      <td><button class="btn btn-sm btn-outline-danger remove-exam-row" type="button" aria-label="Remove"><i class="bi bi-x-lg"></i></button></td>`;
    tbody.appendChild(row);
  },

  populateAreaForRow(row) {
    row.querySelector(".exam-area").innerHTML = this.areaOptions(row.querySelector(".exam-stream").value, null);
  },

  async open(id = null) {
    try {
      this.state.draft = id ? this.unwrap(await window.API.schedules.getExamTimetableDraft(id)) : null;
      const draft = this.state.draft;
      const editable = !draft || ["draft", "changes_requested"].includes(draft.status);
      document.getElementById("examDraftTitle").value = draft?.title || "End of Term Examination Timetable";
      document.getElementById("examDraftTitle").disabled = !editable;
      document.getElementById("examDraftStatus").textContent = draft ? `Draft #${draft.id} · ${String(draft.status).replaceAll("_", " ")}` : "New draft";
      document.getElementById("examEntryRows").innerHTML = "";
      (draft?.entries || [{}]).forEach((entry) => this.addRow(entry));
      document.querySelectorAll("#examEntryRows input, #examEntryRows select, #examEntryRows button").forEach((control) => { control.disabled = !editable; });
      document.getElementById("addExamRow").disabled = !editable;
      document.getElementById("saveExamDraft").classList.toggle("d-none", !editable);
      document.getElementById("submitExamDraft").classList.toggle("d-none", !editable);
      bootstrap.Modal.getOrCreateInstance(document.getElementById("examDraftModal")).show();
    } catch (error) {
      window.showNotification?.(error.message || "Unable to open exam timetable draft", "error");
    }
  },

  collectEntries() {
    return [...document.querySelectorAll("#examEntryRows tr")].map((row) => ({
      academic_year_class_stream_id: Number(row.querySelector(".exam-stream").value),
      learning_area_id: Number(row.querySelector(".exam-area").value),
      exam_name: row.querySelector(".exam-name").value.trim(),
      assessment_type_id: Number(row.querySelector(".exam-type").value),
      max_marks: Number(row.querySelector(".exam-max").value),
      exam_date: row.querySelector(".exam-date").value,
      start_time: row.querySelector(".exam-start").value,
      end_time: row.querySelector(".exam-end").value,
      venue: row.querySelector(".exam-venue").value.trim(),
      invigilator_id: Number(row.querySelector(".exam-invigilator").value) || null,
    }));
  },

  async save(submit) {
    try {
      const title = document.getElementById("examDraftTitle").value.trim();
      if (!title) throw new Error("Enter a timetable title.");
      const result = this.unwrap(await window.API.schedules.saveExamTimetableDraft({
        id: this.state.draft?.id, academic_year_id: this.state.yearId,
        academic_year_term_id: this.state.termId, title, entries: this.collectEntries(),
      }));
      this.state.draft = { id: Number(result.id), status: "draft" };
      if (submit) await window.API.schedules.transitionExamTimetableDraft({ id: Number(result.id), action: "submit" });
      window.showNotification?.(submit ? "Exam timetable submitted for review" : "Exam timetable draft saved", "success");
      bootstrap.Modal.getInstance(document.getElementById("examDraftModal"))?.hide();
      await this.loadDrafts();
    } catch (error) {
      window.showNotification?.(error.message || "Unable to save exam timetable", "error");
    }
  },

  async transition(id, action) {
    try {
      let comments = "";
      if (action === "request_changes") {
        comments = window.prompt("Describe the required timetable changes:", "") || "";
        if (!comments.trim()) return;
      }
      const label = action === "publish" ? "publish this approved timetable and create teacher score registers" : action.replace("_", " ");
      if (window.confirmAction && !(await window.confirmAction("Confirm workflow action", `Proceed to ${label}?`))) return;
      await window.API.schedules.transitionExamTimetableDraft({ id, action, comments });
      window.showNotification?.(`Exam timetable ${action.replace("_", " ")} completed`, "success");
      await this.loadDrafts();
    } catch (error) {
      window.showNotification?.(error.message || "Unable to update exam timetable workflow", "error");
    }
  },
};

window.ExamTimetableDraftsController = ExamTimetableDraftsController;
document.addEventListener("DOMContentLoaded", () => ExamTimetableDraftsController.init());
