/**
 * Manage Lesson Plans Controller
 * CRUD, approval, tabs, stat cards — via API.academic.lessonPlans*
 */
const lessonPlansController = (() => {
  let allPlans = [],
    myPlans = [],
    pendingPlans = [],
    approvedPlans = [];
  // Reference data for dropdowns
  let classes = [],
    learningAreas = [],
    curriculumUnits = [],
    teachers = [];
  let currentStaffId = null;
  let planningOptions = { assignments: [], days: [], academic_year_term_id: null };
  let activeChoices = null;
  let aiPoll = null;
  let userRoles = [];
  let isTeacherUser = false;
  let isAcademicLeader = false;
  let personalMode = false;

  const esc = (value) => {
    const node = document.createElement('span');
    node.textContent = String(value ?? '');
    return node.innerHTML;
  };

  const statusBadge = (s) => {
    const normalized = String(s || "draft").toLowerCase();
    const m = {
      published: "primary",
      approved: "success",
      submitted: "warning",
      draft: "secondary",
      delivered: "info",
    };
    // Teacher validates the assistant's draft and submits; the academic heads
    // approve; the School Administrator publishes to parents.
    const labels = {
      published: "Published to parents",
      approved: "Approved, awaiting publishing",
      submitted: "With the academic heads",
      draft: "Draft",
      delivered: "Taught",
    };
    return `<span class="badge bg-${m[normalized] || "secondary"}">${labels[normalized] || esc(s)}</span>`;
  };

  async function init() {
    await window.AuthContext?.ready();
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    if (
      typeof PageShell !== "undefined" &&
      !PageShell.hasAny(["lesson_plans_view", "lesson_plans_manage", "lesson_plans_create"])
    ) {
      const container = document.querySelector(".container-fluid") || document.getElementById("lessonPlansContainer");
      if (container) {
        container.innerHTML =
          '<div class="alert alert-warning border-0 shadow-sm">' +
          '<i class="bi bi-shield-lock me-2"></i>' +
          "You do not have permission to view lesson plans." +
          "</div>";
      }
      return;
    }

    userRoles = (window.AuthContext?.getRoles?.() || []).map((role) =>
      String(typeof role === "object" ? (role.name || role.role_name || "") : role).toLowerCase(),
    );
    isTeacherUser = userRoles.some((role) => role.includes("teacher"));
    isAcademicLeader = userRoles.some((role) => /admin|director|headteacher|deputy/.test(role));

    await loadReferenceData();
    personalMode = isTeacherUser && !!currentStaffId;
    configureRoleView();
    await loadPlans();
  }

  async function loadReferenceData() {
    try {
      const [classRes, laRes, teacherRes] = await Promise.all([
        API.academic.listClasses().catch(() => ({ data: [] })),
        API.academic.listLearningAreas().catch(() => ({ data: [] })),
        API.academic.getTeachers().catch(() => ({ data: [] })),
      ]);
      classes = classRes?.data || classRes || [];
      learningAreas = laRes?.data || laRes || [];
      teachers = teacherRes?.data || teacherRes || [];
      const user = typeof AuthContext !== "undefined" ? AuthContext.getUser() : null;
      const userId = user?.id || user?.user_id || "";
      const staffRecord = teachers.find(
        (t) => String(t.user_id || "") === String(userId),
      );
      currentStaffId = staffRecord?.id || user?.staff_id || null;

      if (isTeacherUser) {
        const scope = await window.AuthContext.getTeacherScope?.();
        currentStaffId = currentStaffId || scope?.staff_id || null;
        const yearId = window.AcademicContext?.getAcademicYearId?.();
        const streamResponse = await API.schedules.listTimetableStreams({ academic_year_id: yearId });
        const streams = streamResponse?.data || streamResponse || [];
        const classStreamIds = new Set((scope?.class_stream_ids || []).map(Number));
        const classIds = new Set((scope?.class_stream_pairs || []).map((pair) => Number(pair.class_id)));
        const areaIds = new Set((scope?.subject_assignments || []).map((assignment) => Number(assignment.learning_area_id)));
        streams.forEach((stream) => {
          if (classStreamIds.has(Number(stream.academic_year_class_stream_id))) {
            classIds.add(Number(stream.class_id));
            (stream.learning_areas || []).forEach((area) => areaIds.add(Number(area.id)));
          }
        });
        classes = classes.filter((item) => classIds.has(Number(item.id)));
        learningAreas = learningAreas.filter((item) => areaIds.has(Number(item.id)));
        teachers = teachers.filter((item) => Number(item.id) === Number(currentStaffId));
        const header = document.querySelector(".academic-header h2");
        const subtitle = document.querySelector(".academic-header small");
        if (header) header.innerHTML = '<i class="bi bi-journal-text me-2"></i>My Lesson Plans';
        if (subtitle) subtitle.textContent = "Create and track lesson plans for your authorised streams and learning areas.";
      }
    } catch (e) {
      console.warn("Failed to load reference data:", e);
    }
  }

  async function loadCurriculumUnits(learningAreaId) {
    try {
      const res = await API.academic.getCurriculumUnits({ learning_area_id: learningAreaId });
      curriculumUnits = res?.data || res || [];
      return curriculumUnits;
    } catch (e) {
      console.warn("Failed to load units:", e);
      return [];
    }
  }

  async function loadPlans() {
    try {
      // A teacher, including a teacher who is also Headteacher/Deputy/Admin,
      // receives their personal authoring workspace. Leadership review is a
      // separate capability and must never silently turn this page into an
      // all-school register.
      const res = await API.academic.listLessonPlans(personalMode ? { scope: "mine" } : {});
      const payload = res?.data?.data || res?.data || res || [];
      allPlans = payload.lesson_plans || payload || [];
      if (!Array.isArray(allPlans)) allPlans = [];
      myPlans = allPlans.filter(
        (p) =>
          currentStaffId &&
          (p.teacher_id || "").toString() === currentStaffId.toString(),
      );
      pendingPlans = allPlans.filter((p) => p.status === "submitted");
      approvedPlans = allPlans.filter(
        (p) => ["approved", "published", "delivered"].includes(p.status),
      );
      updateStats();
      renderTab("allLessons", allPlans);
      renderTab("myLessons", myPlans);
      renderTab("pending", pendingPlans);
      renderTab("approved", approvedPlans);
    } catch (e) {
      console.error("Load lesson plans:", e);
    }
  }

  function configureRoleView() {
    const header = document.querySelector(".academic-header h2");
    const subtitle = document.querySelector(".academic-header small");
    const createButton = document.getElementById("btnCreateLessonPlan");
    const tabLinks = Array.from(document.querySelectorAll('.nav-tabs [data-bs-toggle="tab"]'));
    const allLink = tabLinks.find((link) => link.getAttribute("href") === "#allLessons");
    const myLink = tabLinks.find((link) => link.getAttribute("href") === "#myLessons");
    const pendingLink = tabLinks.find((link) => link.getAttribute("href") === "#pending");
    const filterBar = document.querySelector(".academic-card.p-3.mb-4");

    if (personalMode) {
      if (header) header.innerHTML = '<i class="bi bi-journal-text me-2"></i>My Lesson Plans';
      if (subtitle) subtitle.textContent = "Your plans for your authorised stream and learning-area assignments.";
      if (createButton) createButton.style.display = "";
      if (allLink) {
        allLink.innerHTML = '<i class="bi bi-person me-1"></i>My Plans';
        allLink.setAttribute("href", "#allLessons");
      }
      if (myLink) myLink.closest(".nav-item").style.display = "none";
      if (pendingLink) pendingLink.closest(".nav-item").style.display = "none";
      // The teacher should not be asked to filter across the school. The
      // backend already resolves the current user's exact teaching scope.
      if (filterBar) filterBar.style.display = "none";
    } else if (!isAcademicLeader) {
      if (createButton) createButton.style.display = "none";
    }

    if (!personalMode && myLink) myLink.closest(".nav-item").style.display = "none";
    if (pendingLink) pendingLink.closest(".nav-item").style.display = "none";

    // A leadership-only user reviews through the dedicated approval page;
    // this page remains read-only when reached directly.
    if (!isTeacherUser && createButton) createButton.style.display = "none";
  }

  function updateStats() {
    const setCount = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };
    setCount("lpTotalCount", allPlans.length);
    setCount("lpApprovedCount", approvedPlans.length);
    setCount("lpPendingCount", pendingPlans.length);
    const drafts = allPlans.filter(
      (p) => p.status === "draft",
    );
    setCount("lpDraftCount", drafts.length);
  }

  function renderTab(tabId, plans) {
    const pane = document.getElementById(tabId);
    if (!pane) return;
    if (plans.length === 0) {
      pane.innerHTML =
        '<div class="text-center text-muted py-4">No lesson plans found in this category.</div>';
      return;
    }
    const teacherColumn = personalMode ? "" : "<th>Teacher</th>";
    let html = `<div class="table-responsive"><table class="table table-hover table-striped">
            <thead class="table-light"><tr><th>#</th><th>Topic</th><th>Subject</th><th>Class / Stream</th>${teacherColumn}<th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>`;
    plans.forEach((p, i) => {
      html += `<tr>
                <td>${i + 1}</td>
                <td>${esc(p.topic)}</td>
                <td>${esc(p.learning_area_name)}</td>
                <td>${esc(p.class_name)}</td>
                ${personalMode ? "" : `<td>${esc(p.teacher_name)}</td>`}
                <td>${esc(p.lesson_date)}</td>
                <td>${statusBadge(p.status)}${p.status === "draft" && p.review_note ? `<div class="small text-warning-emphasis mt-1">Returned by the academic heads: ${esc(p.review_note)}</div>` : ""}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" data-kw-onclick="lessonPlansController.viewPlan(${Number(p.id) || 0})" title="View"><i class="bi bi-eye"></i></button>
                        ${personalMode && p.status !== "archived" ? `<button class="btn btn-outline-warning" data-kw-onclick="lessonPlansController.editPlan(${Number(p.id) || 0})" title="Edit (stays editable after submitting and publishing)" data-year-write><i class="bi bi-pencil"></i></button>` : ""}
                        ${personalMode && p.status === "draft" ? `<button class="btn btn-outline-success" data-kw-onclick="lessonPlansController.submitPlan(${Number(p.id) || 0})" title="Submit to the academic heads" data-year-write><i class="bi bi-send"></i></button>` : ""}
                        ${personalMode && p.status === "draft" ? `<button class="btn btn-outline-danger" data-kw-onclick="lessonPlansController.deletePlan(${Number(p.id) || 0})" title="Archive" data-year-write><i class="bi bi-trash"></i></button>` : ""}
                        ${personalMode ? `<button class="btn btn-outline-secondary" data-kw-onclick="lessonPlansController.duplicatePlan(${Number(p.id) || 0})" title="Duplicate" data-year-write><i class="bi bi-files"></i></button>` : ""}
                    </div>
                </td>
            </tr>`;
    });
    html += "</tbody></table></div>";
    pane.innerHTML = html;
  }

  function viewPlan(id) {
    const plan = allPlans.find((p) => p.id == id);
    if (!plan) return;
    const modal = document.createElement("div");
    modal.innerHTML = `<div class="modal fade" id="viewPlanModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">${esc(plan.topic || "Lesson Plan")}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Subject:</strong> ${esc(plan.learning_area_name)}</div>
                    <div class="col-md-4"><strong>Class:</strong> ${esc(plan.class_name)}</div>
                    <div class="col-md-4"><strong>Teacher:</strong> ${esc(plan.teacher_name)}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Date:</strong> ${esc(plan.lesson_date)}</div>
                    <div class="col-md-4"><strong>Status:</strong> ${statusBadge(plan.status)}</div>
                    <div class="col-md-4"><strong>Duration:</strong> ${Number(plan.duration) > 0 ? `${Number(plan.duration)} min` : "N/A"}</div>
                </div>
                ${plan.subtopic ? `<div class="mb-3"><strong>Subtopic:</strong> ${esc(plan.subtopic)}</div>` : ""}
                ${plan.unit_name ? `<div class="mb-3"><strong>Curriculum Unit:</strong> ${esc(plan.unit_name)}</div>` : ""}
                <hr>
                <h6>Objectives</h6><p>${esc(plan.objectives || "Not specified.")}</p>
                <h6>Activities</h6><p>${esc(plan.activities || "Not specified.")}</p>
                <h6>Resources / Teaching Aids</h6><p>${esc(plan.resources || "N/A")}</p>
                <h6>Assessment</h6><p>${esc(plan.assessment || "N/A")}</p>
                <h6>Homework</h6><p>${esc(plan.homework || "N/A")}</p>
                ${plan.remarks ? `<h6>Remarks</h6><p>${esc(plan.remarks)}</p>` : ""}
                ${plan.approved_by_name ? `<p class="text-muted mt-3"><small>Approved by ${esc(plan.approved_by_name)} on ${esc(plan.approved_at)}</small></p>` : ""}
            </div>
        </div></div></div>`;
    document.body.appendChild(modal);
    bootstrap.Modal.getOrCreateInstance(document.getElementById("viewPlanModal")).show();
    document
      .getElementById("viewPlanModal")
      .addEventListener("hidden.bs.modal", () => modal.remove());
  }

  async function editPlan(id) {
    const plan = allPlans.find((p) => p.id == id);
    if (!plan) return;
    try {
      const response = await API.academic.getLessonPlan(id);
      const detail = response?.data?.data || response?.data || response || {};
      showFormModal({...plan, ...detail});
    } catch (error) {
      await window.infoDialog('Notice', 'Unable to load the lesson plan curriculum details.');
    }
  }

  function planningTermId() {
    const fromUrl = Number(new URLSearchParams(window.location.search).get("academic_year_term_id")) || 0;
    return fromUrl || Number(window.AcademicContext?.getPlanningTermId?.()) || 0;
  }

  async function loadPlanningOptions() {
    const termId = planningTermId();
    const res = await API.academic.getLessonPlanningOptions(termId ? { academic_year_term_id: termId } : {});
    const data = res?.data?.data || res?.data || res || {};
    planningOptions = {
      academic_year_term_id: data.academic_year_term_id || null,
      assignments: Array.isArray(data.assignments) ? data.assignments : [],
      days: Array.isArray(data.days) ? data.days : [],
    };
    return planningOptions;
  }

  const planDateMissing = (plan) => {
    const date = plan?.date || plan?.lesson_date;
    return Boolean(date) && !planningOptions.days.some((d) => d.date === date);
  };

  const weekday = (date) => {
    const d = new Date(`${date}T00:00:00`);
    return Number.isNaN(d.getTime()) ? "" : d.toLocaleDateString(undefined, { weekday: "short" });
  };

  // A lesson plan is its own document, planned for delivery in class. It is
  // not created from a scheme of work (the scheme is the MoE record of the
  // term's teaching). The assistant drafts it; the teacher reviews and edits.
  async function showFormModal(plan = null) {
    const isEdit = !!plan?.id;
    try { await loadPlanningOptions(); } catch (e) { planningOptions = { assignments: [], days: [] }; }
    const planSla = Number(plan?.academic_year_class_stream_learning_area_id) || 0;
    if (planSla && !planningOptions.assignments.some((a) => Number(a.stream_learning_area_id) === planSla)) {
      // A plan from another term keeps its own class and learning area.
      planningOptions.assignments.push({
        stream_learning_area_id: planSla, class_id: plan.class_id, class_name: plan.class_name || "",
        stream_name: plan.stream_name || "", learning_area_id: plan.learning_area_id,
        learning_area_name: plan.subject_name || plan.learning_area_name || "", grade_level: "",
      });
    }
    if (planDateMissing(plan)) planningOptions.days.unshift({ date: plan.date || plan.lesson_date, title: "" });
    const assignments = planningOptions.assignments;
    const today = new Date().toISOString().slice(0, 10);
    const planDate = plan?.date || plan?.lesson_date || "";
    const defaultDate = planDate || (planningOptions.days.find((d) => d.date >= today)?.date || "");
    const assignmentOpts = assignments.map((a) => `<option value="${Number(a.stream_learning_area_id)}" ${Number(plan?.academic_year_class_stream_learning_area_id) === Number(a.stream_learning_area_id) ? "selected" : ""}>${esc(a.class_name)} ${esc(a.stream_name || "")} · ${esc(a.learning_area_name)}</option>`).join("");
    const dayOpts = planningOptions.days.map((d) => `<option value="${esc(d.date)}" ${defaultDate === d.date ? "selected" : ""}>${esc(weekday(d.date))} ${esc(d.date)}${d.title ? " · " + esc(d.title) : ""}</option>`).join("");
    const publishedNote = isEdit && ["submitted", "approved", "published"].includes(String(plan.status))
      ? `<div class="alert alert-info py-2 small mb-0"><i class="bi bi-info-circle me-1"></i>This plan is ${esc(String(plan.status))}. You can still edit it; your changes are saved to the same plan.</div>` : "";

    const m = document.createElement("div");
    m.innerHTML = `<div class="modal fade" id="lessonFormModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable modal-xl"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">${isEdit ? "Edit Lesson Plan" : "New Lesson Plan"}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="lessonPlanForm">
                    <div class="row g-3">
                        ${publishedNote ? `<div class="col-12">${publishedNote}</div>` : ""}
                        <div class="col-md-6"><label class="form-label fw-semibold" for="lpFormAssignment">Class stream &amp; learning area *</label>
                            <select class="form-select" id="lpFormAssignment" name="stream_learning_area_id" ${isEdit ? "disabled" : ""} required><option value="">${assignments.length ? "Select one of your classes" : "No teaching assignment for this term"}</option>${assignmentOpts}</select></div>
                        <div class="col-md-3"><label class="form-label fw-semibold" for="lpFormDate">Lesson date *</label>
                            <select class="form-select" name="lesson_date" id="lpFormDate" required><option value="">Select school day</option>${dayOpts}</select></div>
                        <div class="col-md-3"><label class="form-label" for="lpFormDuration">Duration (minutes) *</label><input type="number" class="form-control" id="lpFormDuration" name="duration" value="${Number(plan?.duration) || 40}" min="10" max="240" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold" for="lpFormStrand">Strand *</label><select class="form-select" id="lpFormStrand" ${isEdit ? "disabled" : ""}><option value="">Select the class and learning area first</option></select></div>
                        <div class="col-md-6"><label class="form-label fw-semibold" for="lpFormSubStrand">Sub-strand *</label><select class="form-select" id="lpFormSubStrand" ${isEdit ? "disabled" : ""}><option value="">Select a strand first</option></select></div>
                        <div class="col-12"><div id="lpAiState" class="d-flex flex-wrap align-items-center gap-2 small text-muted">
                            <span id="lpAiStateText">${isEdit ? "Edit the plan below, or ask the assistant for a fresh draft." : "Choose the class, date and sub-strand. The assistant then drafts the whole lesson for you to review."}</span>
                            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="lpAiRedraft" ${isEdit ? "" : "disabled"}><i class="bi bi-stars me-1"></i>${isEdit ? "Redraft with assistant" : "Draft with assistant"}</button>
                        </div></div>
                        <div class="col-md-6"><label class="form-label" for="lpFormTopic">Lesson topic *</label><input class="form-control" id="lpFormTopic" name="topic" value="${esc(plan?.topic || plan?.title || "")}" required></div>
                        <div class="col-md-6"><label class="form-label" for="lpFormSubtopic">Subtopic</label><input class="form-control" id="lpFormSubtopic" name="subtopic" value="${esc(plan?.subtopic || "")}"></div>
                        <div class="col-12"><label class="form-label" for="lpFormActivities">Lesson steps (introduction, development, conclusion) *</label><textarea class="form-control" id="lpFormActivities" name="activities" rows="9" required>${esc(plan?.activities || plan?.content || "")}</textarea></div>
                        <div class="col-12"><label class="form-label">Specific learning outcomes *</label><div id="lpOutcomes" class="row g-1"><div class="text-muted small">Choose a sub-strand.</div></div></div>
                        <div class="col-12"><label class="form-label">Learning experiences *</label><div id="lpExperiences" class="row g-1"><div class="text-muted small">Choose a sub-strand.</div></div></div>
                        <div class="col-12"><label class="form-label">Key inquiry questions</label><div id="lpQuestions" class="row g-1"><div class="text-muted small">Choose a sub-strand.</div></div></div>
                        <div class="col-12"><label class="form-label">Resources, competencies, assessment tools and rubrics</label><div id="lpAssessment" class="row g-1"><div class="text-muted small">Choose a sub-strand.</div></div></div>
                        <div class="col-12"><label class="form-label" for="lpFormHomework">Homework</label><textarea class="form-control" id="lpFormHomework" name="homework" rows="2">${esc(plan?.homework || "")}</textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-outline-primary" data-kw-onclick="lessonPlansController.savePlan(${isEdit ? Number(plan.id) : "null"}, 'draft')" data-year-write><i class="bi bi-save"></i> Save</button>
                ${!isEdit || plan.status === "draft" ? `<button class="btn btn-primary" data-kw-onclick="lessonPlansController.savePlan(${isEdit ? Number(plan.id) : "null"}, 'submitted')" data-year-write><i class="bi bi-send"></i> Save and submit for approval</button>` : ""}
            </div>
        </div></div></div>`;
    document.body.appendChild(m);

    const assignmentSel = document.getElementById("lpFormAssignment");
    const strandSel = document.getElementById("lpFormStrand");
    const subSel = document.getElementById("lpFormSubStrand");
    const redraft = document.getElementById("lpAiRedraft");
    assignmentSel?.addEventListener("change", () => loadStrands());
    strandSel?.addEventListener("change", () => loadSubStrands());
    subSel?.addEventListener("change", async () => {
      await loadChoices(null);
      if (subSel.value) queueAiDraft();
    });
    redraft?.addEventListener("click", () => queueAiDraft());

    bootstrap.Modal.getOrCreateInstance(document.getElementById("lessonFormModal")).show();
    document.getElementById("lessonFormModal").addEventListener("hidden.bs.modal", () => {
      clearTimeout(aiPoll); aiPoll = null; m.remove();
    });

    if (plan?.academic_year_class_stream_learning_area_id) {
      await loadStrands(plan.strand_id || plan.unit_id);
      await loadSubStrands(plan.sub_strand_id || plan.topic_id);
      await loadChoices(plan);
    } else if (assignments.length === 1) {
      assignmentSel.value = String(assignments[0].stream_learning_area_id);
      await loadStrands();
    }
  }

  function selectedAssignment() {
    const id = Number(document.getElementById("lpFormAssignment")?.value) || 0;
    return planningOptions.assignments.find((a) => Number(a.stream_learning_area_id) === id) || null;
  }

  async function loadStrands(selectedId = null) {
    const strandSel = document.getElementById("lpFormStrand");
    const subSel = document.getElementById("lpFormSubStrand");
    const a = selectedAssignment();
    if (!strandSel) return;
    subSel.innerHTML = '<option value="">Select a strand first</option>';
    if (!a) { strandSel.innerHTML = '<option value="">Select the class and learning area first</option>'; return; }
    const res = await window.API.apiCall("/academic/strands", "GET", null, { learning_area_id: a.learning_area_id, grade_level: a.grade_level || "" }).catch(() => []);
    const rows = Array.isArray(res) ? res : (res?.data || []);
    strandSel.innerHTML = `<option value="">${rows.length ? "Select strand" : "No strands configured for this learning area"}</option>` + rows.map((r) => `<option value="${Number(r.id)}" ${Number(selectedId) === Number(r.id) ? "selected" : ""}>${esc((r.code ? r.code + " · " : "") + r.name)}</option>`).join("");
    if (selectedId) strandSel.value = String(selectedId);
  }

  async function loadSubStrands(selectedId = null) {
    const strandSel = document.getElementById("lpFormStrand");
    const subSel = document.getElementById("lpFormSubStrand");
    if (!subSel) return;
    if (!strandSel?.value) { subSel.innerHTML = '<option value="">Select a strand first</option>'; return; }
    const res = await window.API.apiCall("/academic/sub-strands", "GET", null, { strand_id: strandSel.value }).catch(() => []);
    const rows = Array.isArray(res) ? res : (res?.data || []);
    subSel.innerHTML = `<option value="">${rows.length ? "Select sub-strand" : "No sub-strands configured"}</option>` + rows.map((r) => `<option value="${Number(r.id)}">${esc((r.code ? r.code + " · " : "") + r.name)}</option>`).join("");
    if (selectedId) subSel.value = String(selectedId);
  }

  // Load the curriculum choices for the sub-strand. For a new plan every
  // outcome and experience starts ticked: the assistant has drafted against
  // all of them and the teacher unticks what this lesson will not cover.
  async function loadChoices(plan = null) {
    const slaId = Number(document.getElementById("lpFormAssignment")?.value) || 0;
    const subId = Number(document.getElementById("lpFormSubStrand")?.value) || 0;
    const redraft = document.getElementById("lpAiRedraft");
    if (redraft) redraft.disabled = !(slaId && subId);
    if (!slaId || !subId) { activeChoices = null; return; }
    try {
      const res = await API.academic.getLessonPlanningChoices({ stream_learning_area_id: slaId, sub_strand_id: subId });
      activeChoices = res?.data?.data || res?.data || res || null;
    } catch (e) {
      activeChoices = null;
      window.showNotification?.(e.message || "The curriculum for this sub-strand could not be loaded.", "danger");
      return;
    }
    const choices = activeChoices?.choices || {};
    const saved = plan?.atomic_content || null;
    const pick = (list, key) => saved ? new Set((saved[key] || []).map((x) => String(x.id))) : new Set((list || []).map((x) => String(x.id)));
    const makeChecks = (items, name, selected, kind = "") => (items || []).map((item) => {
      const label = item.criteria_name || item.text || item.name || item.code || "";
      return `<div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="${name}" value="${Number(item.id) || 0}" data-text="${esc(label)}" ${selected.has(String(item.id)) ? "checked" : ""}><span class="form-check-label small">${kind ? `<span class="badge text-bg-light me-1">${esc(kind)}</span>` : ""}${esc(label)}</span></label></div>`;
    }).join("");
    const none = '<div class="text-muted small">None configured for this sub-strand.</div>';
    document.getElementById("lpOutcomes").innerHTML = makeChecks(choices.outcomes, "learning_outcome_ids", pick(choices.outcomes, "outcomes")) || none;
    document.getElementById("lpExperiences").innerHTML = makeChecks(choices.experiences, "experience_ids", pick(choices.experiences, "experiences")) || none;
    document.getElementById("lpQuestions").innerHTML = makeChecks(choices.inquiry_questions, "inquiry_question_ids", saved ? new Set() : pick(choices.inquiry_questions)) || none;
    const sel = (key, list) => saved ? new Set((saved[key] || []).map((x) => String(x.id))) : new Set((list || []).map((x) => String(x.id)));
    document.getElementById("lpAssessment").innerHTML = [
      makeChecks(choices.resources, "scheme_resource_ids", sel("resources", choices.resources), "resource"),
      makeChecks(choices.competencies, "scheme_competency_ids", sel("competencies", choices.competencies), "competency"),
      makeChecks(choices.assessment_tools, "scheme_tool_ids", sel("assessment_tools", []), "tool"),
      makeChecks(choices.rubrics, "scheme_rubric_ids", sel("rubrics", choices.rubrics), "rubric"),
      makeChecks(choices.assessment_rubrics, "scheme_assessment_rubric_ids", sel("assessment_rubrics", []), "criterion"),
    ].join("") || none;
  }

  function setAiState(text, kind = "muted") {
    const node = document.getElementById("lpAiStateText");
    if (!node) return;
    node.className = kind === "danger" ? "text-danger" : kind === "success" ? "text-success" : "text-muted";
    node.innerHTML = text;
  }

  // Queue the assistant, then poll for the draft and fill the form with it.
  async function queueAiDraft() {
    const a = selectedAssignment();
    const strandSel = document.getElementById("lpFormStrand");
    const subSel = document.getElementById("lpFormSubStrand");
    if (!a || !subSel?.value) return;
    const redraft = document.getElementById("lpAiRedraft");
    if (redraft) redraft.disabled = true;
    setAiState('<span class="spinner-border spinner-border-sm me-1"></span>The assistant is drafting this lesson…');
    try {
      const queued = await API.academic.queueAiLessonPlanDraft({
        stream_learning_area_id: a.stream_learning_area_id,
        sub_strand_id: Number(subSel.value),
        learning_area_name: a.learning_area_name,
        strand_name: strandSel?.selectedOptions?.[0]?.textContent || "",
        sub_strand_name: subSel.selectedOptions?.[0]?.textContent || "",
        topic: document.getElementById("lpFormTopic")?.value || "",
        lesson_date: document.getElementById("lpFormDate")?.value || "",
      });
      const jobId = Number(queued?.data?.job_id || queued?.job_id || 0);
      pollAiDraft(jobId, 0);
    } catch (e) {
      if (redraft) redraft.disabled = false;
      setAiState(`The assistant is unavailable right now (${esc(e.message || "error")}). You can write the plan yourself.`, "danger");
    }
  }

  function pollAiDraft(jobId, attempt) {
    clearTimeout(aiPoll);
    aiPoll = setTimeout(async () => {
      if (!document.getElementById("lessonFormModal")) return;
      let drafts = [];
      try {
        const res = await API.academic.getAiLessonPlanDrafts("own");
        drafts = res?.data?.drafts || res?.drafts || [];
      } catch (_) {}
      const match = drafts.find((d) => d.workflow_id === "academics.lesson_plan_draft" && (!jobId || Number(d.metadata?.queue_job_id) === jobId));
      const redraft = document.getElementById("lpAiRedraft");
      if (match && match.status !== "failed" && match.draft?.body) {
        applyAiDraft(match.draft);
        if (redraft) redraft.disabled = false;
        setAiState("Drafted by the assistant. Review every part, edit where needed, then save.", "success");
        return;
      }
      if (match?.status === "failed" || attempt >= 24) {
        if (redraft) redraft.disabled = false;
        setAiState("The draft is taking longer than expected. Keep working; use “Draft with assistant” to try again.", "danger");
        return;
      }
      pollAiDraft(jobId, attempt + 1);
    }, attempt === 0 ? 2500 : 5000);
  }

  function applyAiDraft(draft) {
    const topic = document.getElementById("lpFormTopic");
    const activities = document.getElementById("lpFormActivities");
    if (topic && draft.title) topic.value = String(draft.title).slice(0, 200);
    const steps = Array.isArray(draft.next_steps) ? draft.next_steps.filter(Boolean) : [];
    const items = Array.isArray(draft.items) ? draft.items.map((x) => (typeof x === "string" ? x : x?.text || x?.title || "")).filter(Boolean) : [];
    if (activities) {
      activities.value = [String(draft.body || "").trim(), items.length ? items.map((x) => `- ${x}`).join("\n") : "", steps.length ? "Next steps:\n" + steps.map((x) => `- ${x}`).join("\n") : ""].filter(Boolean).join("\n\n");
    }
  }

  async function savePlan(id, status = "draft") {
    const form = document.getElementById("lessonPlanForm");
    if (!form) return;
    const formData = Object.fromEntries(new FormData(form));
    const a = selectedAssignment();
    formData.stream_learning_area_id = Number(document.getElementById("lpFormAssignment")?.value) || null;
    formData.sub_strand_id = Number(document.getElementById("lpFormSubStrand")?.value) || null;
    formData.strand_id = Number(document.getElementById("lpFormStrand")?.value) || null;
    if (a) { formData.learning_area_id = a.learning_area_id; formData.class_id = a.class_id; }
    formData.learning_outcome_ids = [...form.querySelectorAll('input[name="learning_outcome_ids"]:checked')].map((input) => Number(input.value));
    formData.experiences = [...form.querySelectorAll('input[name="experience_ids"]:checked')].map((input) => ({ id: Number(input.value), text: input.dataset.text || "" }));
    formData.resources_items = [...form.querySelectorAll('input[name="scheme_resource_ids"]:checked')].map((input) => ({ id: Number(input.value) }));
    formData.resources = formData.resources_items;
    const activitiesText = String(formData.activities || "").trim();
    formData.activities_items = activitiesText ? [{ text: activitiesText, stage: "development" }] : [];
    formData.competency_ids = [...form.querySelectorAll('input[name="scheme_competency_ids"]:checked')].map((input) => Number(input.value));
    formData.assessment_tool_ids = [...form.querySelectorAll('input[name="scheme_tool_ids"]:checked')].map((input) => Number(input.value));
    formData.rubric_ids = [...form.querySelectorAll('input[name="scheme_rubric_ids"]:checked')].map((input) => Number(input.value));
    formData.assessment_rubric_ids = [...form.querySelectorAll('input[name="scheme_assessment_rubric_ids"]:checked')].map((input) => Number(input.value));
    formData.inquiry_questions = [...form.querySelectorAll('input[name="inquiry_question_ids"]:checked')].map((input) => ({ id: Number(input.value), text: input.dataset.text || "" }));

    const missing = [];
    if (!id && !formData.stream_learning_area_id) missing.push("the class stream and learning area");
    if (!id && !formData.sub_strand_id) missing.push("the strand and sub-strand");
    if (!String(formData.lesson_date || "").trim()) missing.push("the lesson date");
    if (!String(formData.topic || "").trim()) missing.push("the lesson topic");
    if (!activitiesText) missing.push("the lesson steps");
    if (missing.length) { await window.infoDialog("Notice", `Complete ${missing.join(", ")} before saving.`); return; }
    if (!formData.learning_outcome_ids.length || !formData.experiences.length) {
      await window.infoDialog("Notice", "Tick at least one learning outcome and one learning experience.");
      return;
    }
    if (id) {
      // The curriculum context of an existing plan is fixed.
      delete formData.stream_learning_area_id; delete formData.sub_strand_id; delete formData.strand_id;
      delete formData.learning_area_id; delete formData.class_id;
    }
    formData.duration = parseInt(formData.duration, 10) || 40;
    formData.status = status;
    try {
      if (id) await API.academic.updateLessonPlan(id, formData);
      else await API.academic.createLessonPlan(formData);
      bootstrap.Modal.getInstance(document.getElementById("lessonFormModal"))?.hide();
      await loadPlans();
      window.showNotification?.(status === "submitted" ? "Lesson plan saved and submitted for approval." : "Lesson plan saved.", "success");
    } catch (e) {
      console.error("Save plan:", e);
      await window.infoDialog("Notice", "Failed to save lesson plan: " + (e.message || ""));
    }
  }

  async function submitPlan(id) {
    if (!(await window.confirmAction('Submit lesson plan', "You have reviewed the assistant's draft and it is ready. Submit it to the academic heads?"))) return;
    try {
      await API.academic.submitLessonPlan({ plan_id: id });
      await loadPlans();
      window.showNotification?.("Lesson plan submitted to the academic heads.", "success");
    } catch (e) {
      window.showNotification?.(e.message || "The lesson plan could not be submitted.", "danger");
    }
  }

  async function approvePlan(id) {
    if (!(await window.confirmAction('Confirm', "Approve this lesson plan?"))) return;
    try {
      await API.academic.approveLessonPlan({ plan_id: id });
      await loadPlans();
      await window.infoDialog('Notice', "Lesson plan approved.");
    } catch (e) {
      console.error("Approve:", e);
      await window.infoDialog('Notice', "Failed to approve.");
    }
  }

  async function rejectPlan(id) {
    const remarks = await window.promptAction('Input', "Reason for rejection:");
    if (remarks === null) return; // User cancelled
    try {
      await API.academic.rejectLessonPlan({ plan_id: id, remarks: remarks });
      await loadPlans();
      await window.infoDialog('Notice', "Lesson plan rejected.");
    } catch (e) {
      console.error("Reject:", e);
      await window.infoDialog('Notice', "Failed to reject.");
    }
  }

  async function deletePlan(id) {
    if (!(await window.confirmAction('Confirm Deletion', "Delete this lesson plan?", { confirmText: 'Delete', danger: true }))) return;
    try {
      await API.academic.deleteLessonPlan(id);
      await loadPlans();
      await window.infoDialog('Notice', "Lesson plan deleted.");
    } catch (e) {
      console.error("Delete:", e);
      await window.infoDialog('Notice', "Failed to delete.");
    }
  }

  async function duplicatePlan(id) {
    try {
      const response = await API.academic.getLessonPlan(id);
      const detail = response?.data?.data || response?.data || response || {};
      const copy = { ...detail, id: null, status: "draft", topic: `${detail.topic || detail.title || ""} (Copy)`, date: "", lesson_date: "" };
      await showFormModal(copy);
    } catch (e) {
      await window.infoDialog("Notice", "Unable to copy this lesson plan.");
    }
  }

  // Wire the "Create Lesson Plan" button in PHP header
  document.addEventListener("DOMContentLoaded", () => {
    const createBtn = document.querySelector(
      '[data-bs-target="#addLessonPlanModal"]',
    );
    if (createBtn) {
      createBtn.removeAttribute("data-bs-toggle");
      createBtn.removeAttribute("data-bs-target");
      createBtn.addEventListener("click", () => {
        // Lesson-plan creation belongs to this scoped workspace for every
        // teacher, including teachers who also hold a leadership role. The
        // old standalone route bypassed the scoped teaching assignment.
        showFormModal();
      });
    }
  });

  return {
    init,
    loadPlans,
    viewPlan,
    editPlan,
    savePlan,
    submitPlan,
    approvePlan,
    rejectPlan,
    deletePlan,
    duplicatePlan,
  };
})();

document.addEventListener("DOMContentLoaded", () =>
  lessonPlansController.init(),
);

window.lessonPlansController = lessonPlansController;

// Shared year bar: reload this page's data in place for the chosen year.
window.KingswayYearScope?.onYearChange?.(() => lessonPlansController.loadPlans());
