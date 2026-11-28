/**
 * Formative Assessments — teacher workspace.
 *
 * Scope comes from the teacher's own stream x learning-area assignments for
 * the selected term (GET academic/lesson-planning-options); the server
 * re-checks every read and write. Questions are kept per assessment
 * (academic/formative-assessment-questions); marks are entered on
 * Subject Grade Entry. Completed years are read-only via [data-year-write].
 */
const myCatsCtrl = (() => {
  const state = {
    cats: [],
    yearId: null,
    termId: null,
    assignments: [],
    current: null,      // assessment open in the questions modal
    questions: [],
    aiPoll: null,
    afterSaveMode: null,
  };
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[c]));
  const notify = (msg, type = "info") => window.showNotification?.(msg, type === "error" ? "danger" : type);
  const api = (endpoint, method = "GET", data = null, params = {}, options = {}) =>
    window.API.apiCall(endpoint, method, data, params, { checkPermission: false, ...options });
  const rowsOf = (r) => (Array.isArray(r) ? r : Array.isArray(r?.data) ? r.data : []);
  const scopeKey = (streamId, areaId) => `${Number(streamId)}|${Number(areaId)}`;
  const TYPES = { short_answer: "Short answer", long_answer: "Long answer", multiple_choice: "Multiple choice", true_false: "True / false", practical: "Practical task" };

  // ------------------------------------------------------------------ scope
  async function loadAssignments() {
    state.assignments = [];
    if (!state.termId) return renderScopeOptions();
    try {
      const res = await window.API.academic.getLessonPlanningOptions({ academic_year_term_id: state.termId });
      const data = res?.data?.data || res?.data || res || {};
      state.assignments = Array.isArray(data.assignments) ? data.assignments : [];
    } catch (e) {
      state.assignments = [];
    }
    renderScopeOptions();
  }

  function assignmentLabel(a) {
    return `${a.class_name || ""} ${a.stream_name || ""} · ${a.learning_area_name || ""}`.replace(/\s+/g, " ").trim();
  }

  function renderScopeOptions() {
    const filter = $("faScopeFilter");
    const current = filter.value;
    const opts = state.assignments.map((a) => `<option value="${esc(scopeKey(a.academic_year_class_stream_id, a.learning_area_id))}">${esc(assignmentLabel(a))}</option>`).join("");
    filter.innerHTML = '<option value="">All my classes and learning areas</option>' + opts;
    if ([...filter.options].some((o) => o.value === current)) filter.value = current;
  }

  // ------------------------------------------------------------------- list
  async function loadCats() {
    const tbody = $("catsTableBody");
    if (!state.yearId) return;
    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Loading formative assessments…</td></tr>';
    try {
      const res = await api("academic/formative-assessments", "GET", null, {
        year_id: state.yearId,
        term_id: state.termId || "",
        teacher_scope_only: true,
      });
      state.cats = rowsOf(res);
      render();
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">${esc(e.message || "Formative assessments could not be loaded.")}</td></tr>`;
    }
  }

  function visibleCats() {
    const scope = $("faScopeFilter").value;
    const q = $("faSearch").value.trim().toLowerCase();
    return state.cats.filter((c) => {
      if (scope && scopeKey(c.academic_year_class_stream_id, c.learning_area_id) !== scope) return false;
      if (!q) return true;
      return [c.name, c.title, c.strand_name, c.sub_strand_name, c.sub_strand_names].join(" ").toLowerCase().includes(q);
    });
  }

  function render() {
    const rows = visibleCats();
    const set = (id, v) => { const el = $(id); if (el) el.textContent = v; };
    set("totalCats", rows.length);
    set("draftCats", rows.filter((c) => c.status === "draft").length);
    set("activeCats", rows.filter((c) => c.status !== "draft").length);
    set("noQuestionCats", rows.filter((c) => !Number(c.question_count)).length);
    set("noMarksCats", rows.filter((c) => !Number(c.student_count)).length);
    const tbody = $("catsTableBody");
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4">${state.cats.length ? "No assessments match these filters." : "No formative assessments for this term yet."}</td></tr>`;
      return;
    }
    const statusBadge = (s) => s === "draft" ? '<span class="badge bg-warning text-dark">Draft</span>' : s === "completed" ? '<span class="badge bg-primary">Approved</span>' : '<span class="badge bg-success">Given</span>';
    tbody.innerHTML = rows.map((c) => {
      const subs = c.sub_strand_names || c.sub_strand_name || "";
      const marksUrl = `${window.APP_BASE || ""}/home.php?route=subject_grade_entry&academic_year_term_id=${Number(c.academic_year_term_id) || ""}&assessment_id=${Number(c.id)}`;
      return `<tr>
        <td><strong>${esc(c.name || c.title || "Untitled")}</strong></td>
        <td>${esc([c.class_name, c.stream_name].filter(Boolean).join(" ")) || "—"}</td>
        <td>${esc(c.subject_name) || "—"}</td>
        <td class="small">${esc(c.strand_name || "—")}${subs ? `<div class="text-muted">${esc(subs)}</div>` : ""}</td>
        <td><span class="badge bg-light text-dark border">${esc(c.type_name || c.type || "—")}</span></td>
        <td>${esc(c.cat_date || "—")}</td>
        <td>${Number(c.question_count) ? `${Number(c.question_count)} <span class="text-muted small">(${Number(c.question_marks)} mk)</span>` : '<span class="text-muted">None</span>'}</td>
        <td>${Number(c.student_count) || 0}</td>
        <td>${statusBadge(c.status)}</td>
        <td class="d-print-none"><div class="btn-group btn-group-sm">
          <button class="btn btn-outline-primary" title="Questions" data-act="questions" data-id="${Number(c.id)}"><i class="bi bi-list-ol"></i></button>
          <a class="btn btn-outline-success" title="Enter / view marks" href="${esc(marksUrl)}"><i class="bi bi-check2-square"></i></a>
          <button class="btn btn-outline-secondary" title="Edit" data-act="edit" data-id="${Number(c.id)}" data-year-write><i class="bi bi-pencil"></i></button>
          ${c.status === "draft" ? `<button class="btn btn-outline-danger" title="Delete" data-act="delete" data-id="${Number(c.id)}" data-year-write><i class="bi bi-trash"></i></button>` : ""}
        </div></td>
      </tr>`;
    }).join("");
  }

  // ------------------------------------------------------------ assessment
  async function showCatModal(catId = null) {
    const cat = catId ? state.cats.find((c) => Number(c.id) === Number(catId)) : null;
    if (!state.termId && !cat) { notify("Choose the term in the Term filter first.", "warning"); return; }
    $("catForm").reset();
    $("catId").value = cat ? cat.id : "";
    $("catModalTitle").textContent = cat ? "Edit Formative Assessment" : "New Formative Assessment";
    $("catQuestionModeWrap").classList.toggle("d-none", !!cat);
    $("catScope").innerHTML = '<option value="">Select one of your classes</option>' + state.assignments.map((a) => `<option value="${Number(a.stream_learning_area_id)}" data-key="${esc(scopeKey(a.academic_year_class_stream_id, a.learning_area_id))}">${esc(assignmentLabel(a))}</option>`).join("");
    $("catScope").disabled = !!cat;
    $("catStrand").disabled = !!cat;
    if (cat) {
      const key = scopeKey(cat.academic_year_class_stream_id, cat.learning_area_id);
      const opt = [...$("catScope").options].find((o) => o.dataset.key === key);
      if (opt) $("catScope").value = opt.value;
      else $("catScope").innerHTML += `<option value="" selected>${esc([cat.class_name, cat.stream_name, "·", cat.subject_name].filter(Boolean).join(" "))}</option>`;
      $("catName").value = cat.name || cat.title || "";
      const type = String(cat.type || cat.type_name || "").toLowerCase();
      const match = [...$("catType").options].find((o) => type.includes(o.value));
      if (match) $("catType").value = match.value;
      $("catDate").value = cat.cat_date || "";
      $("catMaxMarks").value = Number(cat.max_marks) || 20;
      $("catDescription").value = cat.description || "";
      $("catStatus").value = cat.status === "draft" ? "draft" : "active";
      await loadStrands(cat.strand_id);
      await loadSubStrands(new Set([String(cat.sub_strand_id), ...String(cat.sub_strand_ids || "").split(",")].filter(Boolean)), cat);
    } else {
      $("catDate").value = new Date().toISOString().slice(0, 10);
      if (state.assignments.length === 1) { $("catScope").selectedIndex = 1; await loadStrands(); }
    }
    bootstrap.Modal.getOrCreateInstance($("catModal")).show();
  }

  function selectedAssignment() {
    const id = Number($("catScope").value) || 0;
    return state.assignments.find((a) => Number(a.stream_learning_area_id) === id) || null;
  }

  async function loadStrands(selected = null) {
    const a = selectedAssignment();
    const sel = $("catStrand");
    $("catSubStrands").innerHTML = '<span class="text-muted">Choose a strand.</span>';
    if (!a && !selected) { sel.innerHTML = '<option value="">Choose the class and learning area</option>'; return; }
    const params = a ? { learning_area_id: a.learning_area_id, grade_level: a.grade_level || "" } : {};
    const rows = rowsOf(await api("academic/strands", "GET", null, params).catch(() => []));
    sel.innerHTML = '<option value="">Select strand</option>' + rows.map((r) => `<option value="${Number(r.id)}">${esc((r.code ? r.code + " · " : "") + r.name)}</option>`).join("");
    if (selected) sel.value = String(selected);
  }

  async function loadSubStrands(checked = new Set(), cat = null) {
    const box = $("catSubStrands");
    const strandId = $("catStrand").value;
    if (!strandId) { box.innerHTML = '<span class="text-muted">Choose a strand.</span>'; return; }
    const rows = rowsOf(await api("academic/sub-strands", "GET", null, { strand_id: strandId }).catch(() => []));
    if (!rows.length) { box.innerHTML = '<span class="text-muted">No sub-strands are configured for this strand.</span>'; return; }
    box.innerHTML = '<div class="row g-1">' + rows.map((r) => `<div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="catSub" value="${Number(r.id)}" ${checked.has(String(r.id)) ? "checked" : ""} ${cat ? "disabled" : ""}> <span class="form-check-label">${esc((r.code ? r.code + " · " : "") + r.name)}</span></label></div>`).join("") + "</div>";
    if (!cat) {
      box.querySelectorAll('input[name="catSub"]').forEach((i) => i.addEventListener("change", suggestTitle));
    }
  }

  function suggestTitle() {
    const name = $("catName");
    if (name.value.trim() && !name.dataset.auto) return;
    const ticked = [...document.querySelectorAll('input[name="catSub"]:checked')].map((i) => i.closest("label").innerText.trim().replace(/^\S+ · /, ""));
    const strand = $("catStrand").selectedOptions[0]?.textContent.replace(/^\S+ · /, "") || "";
    const label = ticked.length === 1 ? ticked[0] : strand;
    if (label) { name.value = `${label} — ${ticked.length > 1 ? "end of strand assessment" : "assessment"}`; name.dataset.auto = "1"; }
  }

  async function saveCat() {
    const catId = Number($("catId").value) || 0;
    const subIds = [...document.querySelectorAll('input[name="catSub"]:checked')].map((i) => Number(i.value));
    const a = selectedAssignment();
    const title = $("catName").value.trim();
    const max = Number($("catMaxMarks").value);
    const problems = [];
    if (!catId && !a) problems.push("the class and learning area");
    if (!catId && !$("catStrand").value) problems.push("the strand");
    if (!catId && !subIds.length) problems.push("at least one sub-strand");
    if (!title) problems.push("the title");
    if (!$("catDate").value) problems.push("the date");
    if (!(max > 0)) problems.push("the marks it is out of");
    if (problems.length) { notify(`Complete ${problems.join(", ")}.`, "warning"); return; }
    const payload = {
      academic_year_id: state.yearId,
      title, name: title,
      type: $("catType").value,
      assessment_date: $("catDate").value, cat_date: $("catDate").value,
      max_marks: max,
      description: $("catDescription").value.trim(),
      status: $("catStatus").value,
    };
    if (!catId) {
      Object.assign(payload, {
        academic_year_term_id: state.termId, term_id: state.termId,
        academic_year_class_stream_learning_area_id: a.stream_learning_area_id,
        academic_year_class_stream_id: a.academic_year_class_stream_id,
        learning_area_id: a.learning_area_id, subject_id: a.learning_area_id,
        strand_id: Number($("catStrand").value),
        sub_strand_id: subIds[0], sub_strand_ids: subIds,
      });
    }
    try {
      const res = await api(catId ? `academic/formative-assessments/${catId}` : "academic/formative-assessments", catId ? "PUT" : "POST", payload);
      bootstrap.Modal.getInstance($("catModal"))?.hide();
      notify(catId ? "Assessment updated." : "Assessment created.", "success");
      await loadCats();
      const newId = Number(res?.id || res?.data?.id || 0);
      const mode = document.querySelector('input[name="catQuestionMode"]:checked')?.value || "manual";
      if (!catId && newId && mode !== "none") openQuestions(newId, mode);
    } catch (e) {
      notify(e.message || "The assessment could not be saved.", "error");
    }
  }

  async function deleteCat(id) {
    if (!(await window.confirmAction("Delete assessment", "Delete this draft assessment and its questions?", { confirmText: "Delete", danger: true }))) return;
    try {
      await api(`academic/formative-assessments/${id}`, "DELETE");
      notify("Assessment deleted.", "success");
      await loadCats();
    } catch (e) {
      notify(e.message || "The assessment could not be deleted.", "error");
    }
  }

  // ------------------------------------------------------------- questions
  async function openQuestions(id, mode = "manual") {
    const cat = state.cats.find((c) => Number(c.id) === Number(id)) || { id };
    state.current = cat;
    state.questions = [];
    $("faQTitle").textContent = `Questions · ${cat.name || cat.title || "Assessment"}`;
    $("faQSubtitle").textContent = [[cat.class_name, cat.stream_name].filter(Boolean).join(" "), cat.subject_name, cat.strand_name].filter(Boolean).join(" · ");
    $("faQMax").textContent = Number(cat.max_marks) || 0;
    $("faUploadState").textContent = "";
    $("faAiState").textContent = "";
    $("faQList").innerHTML = '<div class="text-muted small py-3">Loading questions…</div>';
    bootstrap.Modal.getOrCreateInstance($("faQuestionsModal")).show();
    try {
      const res = await api(`academic/formative-assessment-questions/${Number(id)}`);
      const data = res?.data || res || {};
      state.questions = (data.questions || []).map((q) => ({ ...q }));
    } catch (e) {
      notify(e.message || "Questions could not be loaded.", "error");
    }
    renderQuestions();
    const tab = mode === "upload" ? "faTabUploadBtn" : mode === "ai" ? "faTabAiBtn" : null;
    if (tab) bootstrap.Tab.getOrCreateInstance($(tab)).show();
    else if (!state.questions.length && window.YearMode?.editable !== false) addQuestion();
  }

  function renderQuestions() {
    const list = $("faQList");
    if (!state.questions.length) {
      list.innerHTML = '<div class="text-muted small py-3">No questions yet. Add them one by one, upload a paper, or ask the assistant.</div>';
    } else {
      list.innerHTML = state.questions.map((q, i) => `
        <div class="card fa-q-card mb-2" data-source="${esc(q.source || "manual")}" data-index="${i}">
          <div class="card-body py-2">
            <div class="row g-2 align-items-start">
              <div class="col-auto fw-bold pt-2">${i + 1}.</div>
              <div class="col">
                <textarea class="form-control form-control-sm mb-1" rows="2" data-f="question_text" data-year-write placeholder="Question">${esc(q.question_text)}</textarea>
                <div class="${q.question_type === "multiple_choice" ? "" : "d-none"}" data-mc>
                  <textarea class="form-control form-control-sm mb-1" rows="3" data-f="options" data-year-write placeholder="One choice per line">${esc((q.options || []).join("\n"))}</textarea>
                </div>
                <input class="form-control form-control-sm" data-f="answer_text" data-year-write placeholder="Expected answer / marking guide" value="${esc(q.answer_text || "")}">
              </div>
              <div class="col-md-2">
                <select class="form-select form-select-sm mb-1" data-f="question_type" data-year-write>${Object.entries(TYPES).map(([k, v]) => `<option value="${k}" ${q.question_type === k ? "selected" : ""}>${v}</option>`).join("")}</select>
                <div class="input-group input-group-sm"><input type="number" class="form-control" min="0" max="1000" step="0.5" data-f="marks" data-year-write value="${Number(q.marks) || 0}"><span class="input-group-text">mk</span></div>
                ${q.source && q.source !== "manual" ? `<span class="badge bg-light text-dark border mt-1">${q.source === "ai" ? "assistant" : "uploaded"}</span>` : ""}
              </div>
              <div class="col-auto d-flex flex-column gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-q="up" title="Move up" data-year-write><i class="bi bi-arrow-up"></i></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-q="down" title="Move down" data-year-write><i class="bi bi-arrow-down"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-q="remove" title="Remove" data-year-write><i class="bi bi-x-lg"></i></button>
              </div>
            </div>
          </div>
        </div>`).join("");
    }
    updateTotal();
  }

  function readQuestionsFromDom() {
    document.querySelectorAll("#faQList .fa-q-card").forEach((card) => {
      const q = state.questions[Number(card.dataset.index)];
      if (!q) return;
      card.querySelectorAll("[data-f]").forEach((el) => {
        const f = el.dataset.f;
        if (f === "options") q.options = el.value.split("\n").map((s) => s.trim()).filter(Boolean);
        else if (f === "marks") q.marks = Number(el.value) || 0;
        else q[f] = el.value;
      });
    });
  }

  function updateTotal() {
    const total = state.questions.reduce((sum, q) => sum + (Number(q.marks) || 0), 0);
    $("faQTotal").textContent = Math.round(total * 100) / 100;
  }

  function addQuestion(q = {}) {
    readQuestionsFromDom();
    state.questions.push({ question_type: "short_answer", question_text: "", options: [], answer_text: "", marks: 1, source: "manual", ...q });
    renderQuestions();
    const last = document.querySelector("#faQList .fa-q-card:last-child textarea");
    if (!q.question_text) last?.focus();
  }

  function onQuestionListEvent(event) {
    const card = event.target.closest(".fa-q-card");
    if (!card) return;
    const i = Number(card.dataset.index);
    const btn = event.target.closest("[data-q]");
    if (event.type === "click" && btn) {
      readQuestionsFromDom();
      const act = btn.dataset.q;
      if (act === "remove") state.questions.splice(i, 1);
      if (act === "up" && i > 0) [state.questions[i - 1], state.questions[i]] = [state.questions[i], state.questions[i - 1]];
      if (act === "down" && i < state.questions.length - 1) [state.questions[i + 1], state.questions[i]] = [state.questions[i], state.questions[i + 1]];
      renderQuestions();
      return;
    }
    if (event.target.dataset.f === "question_type") {
      card.querySelector("[data-mc]")?.classList.toggle("d-none", event.target.value !== "multiple_choice");
    }
    if (event.target.dataset.f === "marks") { readQuestionsFromDom(); updateTotal(); }
  }

  async function saveQuestions() {
    if (!state.current) return;
    readQuestionsFromDom();
    const questions = state.questions.filter((q) => String(q.question_text || "").trim());
    const badMc = questions.findIndex((q) => q.question_type === "multiple_choice" && (q.options || []).length < 2);
    if (badMc >= 0) { notify(`Question ${badMc + 1} is multiple choice: give at least two choices, one per line.`, "warning"); return; }
    try {
      const res = await api(`academic/formative-assessment-questions/${Number(state.current.id)}`, "POST", {
        mode: "replace",
        questions,
        sync_max_marks: $("faSyncMax").checked,
      });
      const data = res?.data || res || {};
      state.questions = (data.questions || questions).map((q) => ({ ...q }));
      renderQuestions();
      notify("Questions saved.", "success");
      await loadCats();
      const refreshed = state.cats.find((c) => Number(c.id) === Number(state.current.id));
      if (refreshed) { state.current = refreshed; $("faQMax").textContent = Number(refreshed.max_marks) || 0; }
    } catch (e) {
      notify(e.message || "The questions could not be saved.", "error");
    }
  }

  async function uploadPaper() {
    const file = $("faUploadFile").files?.[0];
    if (!file) { notify("Choose the question paper first.", "warning"); return; }
    if (file.size > 8 * 1024 * 1024) { notify("The file is larger than 8 MB.", "warning"); return; }
    const fd = new FormData();
    fd.append("file", file);
    fd.append("assessment_id", String(state.current?.id || ""));
    $("faUploadState").innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Reading the paper…';
    $("faUploadBtn").disabled = true;
    try {
      const res = await api("academic/formative-assessment-questions-extract", "POST", fd, {}, { isFile: true });
      const data = res?.data || res || {};
      const found = Array.isArray(data.questions) ? data.questions : [];
      readQuestionsFromDom();
      found.forEach((q) => state.questions.push({ ...q, source: "upload", source_ref: data.filename || file.name }));
      renderQuestions();
      $("faUploadState").innerHTML = found.length
        ? `<span class="text-success">${found.length} question(s) found and added to the list. Check each one, then save.</span>`
        : '<span class="text-warning">No numbered questions were found. Type them on the Questions tab.</span>';
      if (found.length) bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#faTabManual"]')).show();
    } catch (e) {
      $("faUploadState").innerHTML = `<span class="text-danger">${esc(e.message || "The file could not be read.")}</span>`;
    } finally {
      $("faUploadBtn").disabled = false;
    }
  }

  async function draftWithAi() {
    if (!state.current) return;
    $("faAiBtn").disabled = true;
    $("faAiState").innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>The assistant is writing questions…';
    try {
      const res = await window.API.academic.queueAiAssessmentDraft({
        assessment_id: Number(state.current.id),
        question_count: Number($("faAiCount").value) || 10,
        teacher_intent: $("faAiIntent").value.trim(),
        assessment_type: $("catType")?.selectedOptions?.[0]?.textContent || state.current.type || "Classroom quiz",
      });
      const jobId = Number(res?.job_id || res?.data?.job_id || 0);
      pollAi(jobId, 0);
    } catch (e) {
      $("faAiBtn").disabled = false;
      $("faAiState").innerHTML = `<span class="text-danger">${esc(e.message || "The assistant is unavailable right now.")}</span>`;
    }
  }

  function pollAi(jobId, attempt) {
    clearTimeout(state.aiPoll);
    state.aiPoll = setTimeout(async () => {
      if (!$("faQuestionsModal")?.classList.contains("show")) { $("faAiBtn").disabled = false; return; }
      let drafts = [];
      try {
        const res = await window.API.academic.getAiAssessmentDrafts("own");
        drafts = res?.data?.drafts || res?.drafts || [];
      } catch (_) {}
      const match = drafts.find((d) => d.workflow_id === "academics.assessment_draft" && (!jobId || Number(d.metadata?.queue_job_id) === jobId));
      const items = Array.isArray(match?.draft?.items) ? match.draft.items : [];
      if (match && match.status !== "failed" && (items.length || match.draft?.body)) {
        readQuestionsFromDom();
        items.forEach((it) => state.questions.push({
          question_type: TYPES[it.type] ? it.type : (Array.isArray(it.options) && it.options.length > 1 ? "multiple_choice" : "short_answer"),
          question_text: it.question || "",
          options: Array.isArray(it.options) ? it.options : [],
          answer_text: it.answer || "",
          marks: Number(it.marks) || 1,
          source: "ai",
          source_ref: `ai-draft-${Number(match.id) || ""}`,
        }));
        renderQuestions();
        $("faAiBtn").disabled = false;
        $("faAiState").innerHTML = items.length
          ? `<span class="text-success">${items.length} question(s) drafted and added to the list. Review and edit them, then save.</span>`
          : '<span class="text-warning">The assistant returned no questions. Try again with more guidance.</span>';
        if (items.length) bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#faTabManual"]')).show();
        return;
      }
      if (match?.status === "failed" || attempt >= 24) {
        $("faAiBtn").disabled = false;
        $("faAiState").innerHTML = '<span class="text-danger">The draft is taking longer than expected. You can keep typing questions and try again.</span>';
        return;
      }
      pollAi(jobId, attempt + 1);
    }, attempt === 0 ? 2500 : 5000);
  }

  function printPaper(withAnswers) {
    readQuestionsFromDom();
    const cat = state.current || {};
    const qs = state.questions.filter((q) => String(q.question_text || "").trim());
    if (!qs.length) { notify("There are no questions to print.", "warning"); return; }
    const total = qs.reduce((s, q) => s + (Number(q.marks) || 0), 0);
    $("faPaper").innerHTML = `
      <h4 class="text-center mb-1">${esc(cat.name || cat.title || "Formative assessment")}</h4>
      <div class="text-center mb-2">${esc([[cat.class_name, cat.stream_name].filter(Boolean).join(" "), cat.subject_name, cat.cat_date].filter(Boolean).join(" · "))} · ${total} marks</div>
      ${withAnswers ? "" : '<div class="mb-3">Name: ____________________________ &nbsp; Adm. No: __________</div>'}
      ${cat.description ? `<p><em>${esc(cat.description)}</em></p>` : ""}
      <ol>${qs.map((q) => `<li class="mb-3">${esc(q.question_text)} <strong>(${Number(q.marks) || 0} mk)</strong>
        ${q.question_type === "multiple_choice" ? `<ol type="a">${(q.options || []).map((o) => `<li>${esc(o)}</li>`).join("")}</ol>` : ""}
        ${q.question_type === "true_false" ? "<div>True / False</div>" : ""}
        ${withAnswers && q.answer_text ? `<div class="fa-answer">Answer: ${esc(q.answer_text)}</div>` : ""}
        ${!withAnswers && ["short_answer", "long_answer"].includes(q.question_type) ? `<div style="border-bottom:1px dotted #999;height:${q.question_type === "long_answer" ? 90 : 30}px"></div>` : ""}
      </li>`).join("")}</ol>`;
    document.body.classList.add("fa-printing");
    const done = () => { document.body.classList.remove("fa-printing"); window.removeEventListener("afterprint", done); };
    window.addEventListener("afterprint", done);
    window.print();
    setTimeout(done, 1500);
  }

  // ------------------------------------------------------------ export
  async function exportCsv() {
    const cell = (v) => { const t = String(v ?? ""); return /[",\r\n]/.test(t) ? `"${t.replace(/"/g, '""')}"` : t; };
    const lines = [["Assessment", "Class", "Learning area", "Strand", "Sub-strands", "Type", "Date", "Out of", "Questions", "Marks entered", "Status"].join(",")];
    visibleCats().forEach((c) => lines.push([c.name || c.title, [c.class_name, c.stream_name].filter(Boolean).join(" "), c.subject_name, c.strand_name, c.sub_strand_names || c.sub_strand_name, c.type_name || c.type, c.cat_date, c.max_marks, c.question_count, c.student_count, c.status].map(cell).join(",")));
    try {
      await window.KingswayFileLifecycle.exportText(`﻿${lines.join("\r\n")}`, `formative_assessments_${new Date().toISOString().slice(0, 10)}.csv`, "text/csv;charset=utf-8");
    } catch (e) {
      notify(e.message || "Export failed", "error");
    }
  }

  // --------------------------------------------------------------- wiring
  function bindEvents() {
    $("createCatBtn")?.addEventListener("click", () => showCatModal());
    $("saveCatBtn")?.addEventListener("click", saveCat);
    $("catScope")?.addEventListener("change", () => loadStrands());
    $("catStrand")?.addEventListener("change", () => loadSubStrands());
    $("faScopeFilter")?.addEventListener("change", render);
    let t = null;
    $("faSearch")?.addEventListener("input", () => { clearTimeout(t); t = setTimeout(render, 250); });
    $("catsTableBody")?.addEventListener("click", (e) => {
      const b = e.target.closest("[data-act]");
      if (!b) return;
      const id = Number(b.dataset.id);
      if (b.dataset.act === "questions") openQuestions(id);
      if (b.dataset.act === "edit") showCatModal(id);
      if (b.dataset.act === "delete") deleteCat(id);
    });
    $("faAddQuestion")?.addEventListener("click", () => addQuestion());
    $("faQList")?.addEventListener("click", onQuestionListEvent);
    $("faQList")?.addEventListener("change", onQuestionListEvent);
    $("faQList")?.addEventListener("input", (e) => { if (e.target.dataset.f === "marks") onQuestionListEvent(e); });
    $("faSaveQuestions")?.addEventListener("click", saveQuestions);
    $("faUploadBtn")?.addEventListener("click", uploadPaper);
    $("faAiBtn")?.addEventListener("click", draftWithAi);
    $("faPrintPaper")?.addEventListener("click", () => printPaper(false));
    $("faPrintGuide")?.addEventListener("click", () => printPaper(true));
    $("faPrintList")?.addEventListener("click", () => window.print());
    $("faExport")?.addEventListener("click", exportCsv);
    $("faQuestionsModal")?.addEventListener("hidden.bs.modal", () => { clearTimeout(state.aiPoll); $("faAiBtn").disabled = false; });
  }

  async function init() {
    if (!$("myCatsPage")) return;
    await window.AuthContext?.ready?.();
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    bindEvents();
    // Year and term: the shared filter (current year and running term by
    // default, kept in the URL). Past years show their assessments as
    // history; YearMode makes a completed year read-only.
    const mounted = window.AcademicYearFilter
      ? await window.AcademicYearFilter.mount($("yearFilter"), {
          termSelect: $("termFilter"),
          onChange: async (yearId, year, termId) => {
            state.yearId = yearId;
            state.termId = termId;
            await loadAssignments();
            await loadCats();
          },
        })
      : null;
    if (!mounted) $("catsTableBody").innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">The academic year filter could not be loaded.</td></tr>';
  }

  return { init, editCat: showCatModal, deleteCat, openQuestions, refresh: loadCats };
})();

document.addEventListener("DOMContentLoaded", myCatsCtrl.init);
window.myCatsCtrl = myCatsCtrl;
window.APIRealtime?.register?.("my_cats", { refresh: () => myCatsCtrl.refresh() }, ["academic", "assessment_result"]);
