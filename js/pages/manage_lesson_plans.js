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
  let approvedSchemes = [];
  let activeSchemeContext = null;
  let userRoles = [];
  let isTeacherUser = false;
  let isAcademicLeader = false;
  let personalMode = false;

  const statusBadge = (s) => {
    const normalized = (s || "draft").toLowerCase();
    const m = {
      approved: "success",
      submitted: "warning",
      draft: "secondary",
      rejected: "danger",
      completed: "info",
    };
    const labels = {
      approved: "Published",
      submitted: "Pending Review",
      draft: "Draft",
      rejected: "Rejected",
      completed: "Completed",
    };
    return `<span class="badge bg-${m[normalized] || "secondary"}">${labels[normalized] || s}</span>`;
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
        const schemeResponse = await API.academic.getSchemeOfWork({
          status: "approved",
          teacher_id: currentStaffId,
        }).catch(() => ({ data: { schemes: [] } }));
        const schemePayload = schemeResponse?.data?.data || schemeResponse?.data || schemeResponse || {};
        approvedSchemes = Array.isArray(schemePayload)
          ? schemePayload
          : (schemePayload.schemes || []);
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
        (p) => p.status === "approved",
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
                <td>${p.topic || ""}</td>
                <td>${p.learning_area_name || ""}</td>
                <td>${p.class_name || ""}</td>
                ${personalMode ? "" : `<td>${p.teacher_name || ""}</td>`}
                <td>${p.lesson_date || ""}</td>
                <td>${statusBadge(p.status)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="lessonPlansController.viewPlan(${p.id})" title="View"><i class="bi bi-eye"></i></button>
                        ${personalMode && p.status !== "archived" ? `<button class="btn btn-outline-warning" onclick="lessonPlansController.editPlan(${p.id})" title="Edit"><i class="bi bi-pencil"></i></button>` : ""}
                        ${personalMode && p.status === "draft" ? `<button class="btn btn-outline-danger" onclick="lessonPlansController.deletePlan(${p.id})" title="Archive"><i class="bi bi-trash"></i></button>` : ""}
                        ${personalMode ? `<button class="btn btn-outline-secondary" onclick="lessonPlansController.duplicatePlan(${p.id})" title="Duplicate"><i class="bi bi-files"></i></button>` : ""}
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
            <div class="modal-header"><h5 class="modal-title">${plan.topic || "Lesson Plan"}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Subject:</strong> ${plan.learning_area_name || ""}</div>
                    <div class="col-md-4"><strong>Class:</strong> ${plan.class_name || ""}</div>
                    <div class="col-md-4"><strong>Teacher:</strong> ${plan.teacher_name || ""}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Date:</strong> ${plan.lesson_date || ""}</div>
                    <div class="col-md-4"><strong>Status:</strong> ${statusBadge(plan.status)}</div>
                    <div class="col-md-4"><strong>Duration:</strong> ${plan.duration ? plan.duration + " min" : "N/A"}</div>
                </div>
                ${plan.subtopic ? `<div class="mb-3"><strong>Subtopic:</strong> ${plan.subtopic}</div>` : ""}
                ${plan.unit_name ? `<div class="mb-3"><strong>Curriculum Unit:</strong> ${plan.unit_name}</div>` : ""}
                <hr>
                <h6>Objectives</h6><p>${plan.objectives || "Not specified."}</p>
                <h6>Activities</h6><p>${plan.activities || "Not specified."}</p>
                <h6>Resources / Teaching Aids</h6><p>${plan.resources || "N/A"}</p>
                <h6>Assessment</h6><p>${plan.assessment || "N/A"}</p>
                <h6>Homework</h6><p>${plan.homework || "N/A"}</p>
                ${plan.remarks ? `<h6>Remarks</h6><p>${plan.remarks}</p>` : ""}
                ${plan.approved_by_name ? `<p class="text-muted mt-3"><small>Approved by ${plan.approved_by_name} on ${plan.approved_at || ""}</small></p>` : ""}
            </div>
        </div></div></div>`;
    document.body.appendChild(modal);
    new bootstrap.Modal(document.getElementById("viewPlanModal")).show();
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

  function showFormModal(plan = null) {
    const isEdit = !!plan;
    const currentTeacherId = currentStaffId || "";

    const schemeOpts = approvedSchemes.map((s) => `<option value="${s.id}" ${plan?.scheme_of_work_id == s.id ? "selected" : ""}>Week ${s.week_number || "-"} · ${s.class_name || ""} ${s.stream_name || ""} · ${s.learning_area_name || s.subject_name || ""} · ${s.strand_name || ""} / ${s.sub_strand_name || ""}</option>`).join("");

    const m = document.createElement("div");
    m.innerHTML = `<div class="modal fade" id="lessonFormModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">${isEdit ? "Edit" : "Create"} Lesson Plan from Approved Scheme</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="lessonPlanForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Approved Scheme of Work *</label>
                            <select class="form-select" name="scheme_of_work_id" id="lpFormScheme" ${isEdit ? "disabled" : "required"}>
                                <option value="">Select an approved scheme week</option>${schemeOpts}
                            </select>
                            ${isEdit ? `<input type="hidden" name="scheme_of_work_id" value="${plan.scheme_of_work_id || ""}">` : ""}
                            <div class="form-text">The scheme supplies the authorised stream, learning area, strand, sub-strand and teaching week.</div>
                        </div>
                        <div class="col-12"><div id="lpSchemeContext" class="alert alert-light border mb-0">Select a scheme row to load its curriculum choices and calendar days.</div></div>
                        <div class="col-md-6"><label class="form-label">Topic *</label><input class="form-control" name="topic" value="${plan?.topic || ""}" required></div>
                        <div class="col-md-6"><label class="form-label">Subtopic</label><input class="form-control" name="subtopic" value="${plan?.subtopic || ""}"></div>
                        <div class="col-md-4"><label class="form-label">Lesson Date *</label><select class="form-select" name="lesson_date" id="lpFormDate" required><option value="">Select calendar day</option></select></div>
                        <div class="col-md-4"><label class="form-label">Duration (minutes) *</label><input type="number" class="form-control" name="duration" value="${plan?.duration || "40"}" min="1" required></div>
                        <div class="col-12"><label class="form-label">Specific learning outcomes *</label><div id="lpSchemeOutcomes" class="row g-2"><div class="text-muted">Load a scheme first.</div></div></div>
                        <div class="col-12"><label class="form-label">Learning experiences *</label><div id="lpSchemeExperiences" class="row g-2"><div class="text-muted">Load a scheme first.</div></div></div>
                        <div class="col-12"><label class="form-label">Key inquiry questions</label><div id="lpSchemeQuestions" class="row g-2"><div class="text-muted">Load a scheme first.</div></div></div>
                        <div class="col-12"><label class="form-label">Activities *</label><textarea class="form-control" name="activities" rows="3" required>${plan?.activities || ""}</textarea></div>
                        <div class="col-12"><label class="form-label">Resources, competencies, tools and rubrics</label><div id="lpSchemeAssessment" class="row g-2"><div class="text-muted">Load a scheme first.</div></div></div>
                        <div class="col-12"><label class="form-label">Homework</label><textarea class="form-control" name="homework" rows="2">${plan?.homework || ""}</textarea></div>
                        <input type="hidden" name="teacher_id" value="${currentTeacherId}">
                        <input type="hidden" name="learning_area_id" value="${plan?.learning_area_id || ""}">
                        <input type="hidden" name="class_id" value="${plan?.class_id || ""}">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-outline-primary" onclick="lessonPlansController.savePlan(${plan?.id || "null"}, 'draft')"><i class="bi bi-save"></i> Save Draft</button>
                <button class="btn btn-primary" onclick="lessonPlansController.savePlan(${plan?.id || "null"}, 'published')"><i class="bi bi-send"></i> ${isEdit ? "Update & Publish" : "Publish Lesson Plan"}</button>
            </div>
        </div></div></div>`;
    document.body.appendChild(m);

    const schemeSelect = document.getElementById("lpFormScheme");
    if (schemeSelect) {
      schemeSelect.addEventListener("change", () => loadSchemeContext(schemeSelect.value, plan));
      if (schemeSelect.value) loadSchemeContext(schemeSelect.value, plan);
    }

    new bootstrap.Modal(document.getElementById("lessonFormModal")).show();
    document
      .getElementById("lessonFormModal")
      .addEventListener("hidden.bs.modal", () => m.remove());
  }

  async function loadSchemeContext(schemeId, plan = null) {
    const contextBox = document.getElementById("lpSchemeContext");
    if (!schemeId) return;
    if (contextBox) contextBox.innerHTML = "Loading approved scheme context…";
    try {
      const response = await API.academic.getLessonPlanningContext(schemeId);
      activeSchemeContext = response?.data?.data || response?.data || response || null;
      const choices = activeSchemeContext?.choices || {};
      const dateSelect = document.getElementById("lpFormDate");
      const calendarDays = choices.calendar_days || [];
      const today = new Date().toISOString().slice(0, 10);
      const defaultDate = plan?.lesson_date || (calendarDays.some((d) => d.date === today) ? today : (calendarDays[0]?.date || ""));
      if (dateSelect) dateSelect.innerHTML = '<option value="">Select calendar day</option>' + calendarDays.map((d) => `<option value="${d.date}" ${defaultDate === d.date ? "selected" : ""}>${d.date}${d.title ? " · " + d.title : ""}</option>`).join("");
      if (contextBox) contextBox.innerHTML = `<strong>${activeSchemeContext.learning_area_name || "Learning area"}</strong> · ${activeSchemeContext.class_name || ""} ${activeSchemeContext.stream_name || ""}<br><span class="small">Week ${activeSchemeContext.week_number || "-"}: ${activeSchemeContext.week_start || ""} to ${activeSchemeContext.week_end || ""} · ${activeSchemeContext.strand_name || ""} / ${activeSchemeContext.sub_strand_name || ""}</span>`;
      const selected = activeSchemeContext?.selected || {};
      const checked = new Set((plan?.atomic_content?.outcomes || []).map((x) => String(x.id)));
      const expChecked = new Set((plan?.atomic_content?.experiences || []).map((x) => String(x.id)));
      if (!plan) {
        (selected.outcome_ids || []).forEach((id) => checked.add(String(id)));
        (selected.experience_ids || []).forEach((id) => expChecked.add(String(id)));
      }
      const makeChecks = (items, name, selected) => (items || []).map((item) => `<div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="${name}" value="${item.id}" data-text="${String(item.text || item.name || "").replace(/"/g, '&quot;')}" ${selected.has(String(item.id)) ? "checked" : ""}><span class="form-check-label small">${item.text || item.name || ""}</span></label></div>`).join("") || '<div class="text-muted small">No suggested choices configured for this scheme row.</div>';
      document.getElementById("lpSchemeOutcomes").innerHTML = makeChecks(choices.outcomes, "learning_outcome_ids", checked);
      const experiences = [...(choices.experiences || [])];
      (selected.experiences || []).forEach((item) => {
        const id = Number(item.id || 0);
        if (id && experiences.some((choice) => Number(choice.id) === id)) return;
        experiences.push({ id, text: item.text || "" });
        if (!id) expChecked.add("0");
      });
      document.getElementById("lpSchemeExperiences").innerHTML = makeChecks(experiences, "experience_ids", expChecked);
      document.getElementById("lpSchemeQuestions").innerHTML = makeChecks(choices.inquiry_questions, "inquiry_question_ids", new Set((selected.inquiry_question_ids || []).map(String)));
      const assessmentItems = [...(choices.resources || []).map((x) => ({...x, kind: "resource"})), ...(choices.competencies || []).map((x) => ({...x, kind: "competency"})), ...(choices.assessment_tools || []).map((x) => ({...x, kind: "tool"})), ...(choices.rubrics || []).map((x) => ({...x, kind: "rubric"})), ...(choices.assessment_rubrics || []).map((x) => ({...x, kind: "assessment_rubric"}))];
      const selectedIds = {
        resource: new Set((selected.resource_ids || []).map(String)),
        competency: new Set((selected.competency_ids || []).map(String)),
        tool: new Set((selected.tool_ids || []).map(String)),
        rubric: new Set((selected.rubric_ids || []).map(String)),
        assessment_rubric: new Set((selected.assessment_rubric_ids || []).map(String)),
      };
      document.getElementById("lpSchemeAssessment").innerHTML = assessmentItems.map((item) => {
        const label = item.kind === "assessment_rubric" ? (item.criteria_name || "Assessment rubric") : (item.name || item.text || item.code || "");
        return `<div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="scheme_${item.kind}_ids" value="${item.id}" ${selectedIds[item.kind]?.has(String(item.id)) ? "checked" : ""}><span class="form-check-label small"><span class="badge text-bg-light me-1">${item.kind === "assessment_rubric" ? "rubric" : item.kind}</span>${label}</span></label></div>`;
      }).join("") || '<div class="text-muted small">No linked resources or assessment mappings configured.</div>';
      document.querySelector('[name="learning_area_id"]').value = activeSchemeContext.learning_area_id || "";
      document.querySelector('[name="class_id"]').value = activeSchemeContext.class_id || "";
      const topicInput = document.querySelector('[name="topic"]');
      if (topicInput && !topicInput.value.trim()) {
        topicInput.value = activeSchemeContext.title
          || [activeSchemeContext.learning_area_name, activeSchemeContext.strand_name, activeSchemeContext.sub_strand_name].filter(Boolean).join(" · ")
          || `Lesson plan · Week ${activeSchemeContext.week_number || ""}`.trim();
      }
    } catch (error) {
      activeSchemeContext = null;
      if (contextBox) contextBox.innerHTML = `<span class="text-danger">Unable to load this approved scheme row.</span>`;
    }
  }

  async function savePlan(id, status = "draft") {
    const form = document.getElementById("lessonPlanForm");
    const formData = Object.fromEntries(new FormData(form));
    formData.scheme_of_work_id = form.querySelector('[name="scheme_of_work_id"]:checked')?.value || form.querySelector('#lpFormScheme')?.value || form.querySelector('input[name="scheme_of_work_id"]')?.value || "";
    formData.learning_outcome_ids = [...form.querySelectorAll('input[name="learning_outcome_ids"]:checked')].map((input) => Number(input.value));
    formData.experiences = [...form.querySelectorAll('input[name="experience_ids"]:checked')].map((input) => ({ id: Number(input.value), text: input.dataset.text || input.closest("label")?.innerText.trim() || "" }));
    formData.resources_items = [...form.querySelectorAll('input[name="scheme_resource_ids"]:checked')].map((input) => ({ id: Number(input.value) }));
    formData.resources = formData.resources_items;
    const activitiesText = String(formData.activities || "").trim();
    formData.activities_items = activitiesText ? [{ text: activitiesText, stage: "development" }] : [];
    formData.competency_ids = [...form.querySelectorAll('input[name="scheme_competency_ids"]:checked')].map((input) => Number(input.value));
    formData.assessment_tool_ids = [...form.querySelectorAll('input[name="scheme_tool_ids"]:checked')].map((input) => Number(input.value));
    formData.rubric_ids = [...form.querySelectorAll('input[name="scheme_rubric_ids"]:checked')].map((input) => Number(input.value));
    formData.assessment_rubric_ids = [...form.querySelectorAll('input[name="scheme_assessment_rubric_ids"]:checked')].map((input) => Number(input.value));
    formData.inquiry_questions = [...form.querySelectorAll('input[name="inquiry_question_ids"]:checked')].map((input) => ({ id: Number(input.value), text: input.dataset.text || "" }));
    if (!formData.scheme_of_work_id) {
      await window.infoDialog('Notice', "Select an approved scheme row before creating a lesson plan.");
      return;
    }
    const requiredFields = [
      ["topic", "a lesson topic"],
      ["lesson_date", "a lesson date"],
    ];
    const missingFields = requiredFields.filter(([name]) => !String(formData[name] || "").trim()).map(([, label]) => label);
    if (missingFields.length) {
      await window.infoDialog('Notice', `Complete ${missingFields.join(", ")} before saving.`);
      return;
    }
    if (!formData.learning_outcome_ids.length || !formData.experiences.length) {
      await window.infoDialog('Notice', "Select at least one scheme outcome and learning experience.");
      return;
    }
    // Cast numeric fields
    formData.duration = parseInt(formData.duration) || 40;
    formData.status = status;
    try {
      if (id) {
        await API.academic.updateLessonPlan(id, formData);
      } else {
        await API.academic.createLessonPlan(formData);
      }
      bootstrap.Modal.getInstance(
        document.getElementById("lessonFormModal"),
      )?.hide();
      await loadPlans();
      await window.infoDialog('Notice', id ? "Lesson plan updated." : "Lesson plan created.");
    } catch (e) {
      console.error("Save plan:", e);
      await window.infoDialog('Notice', "Failed to save lesson plan: " + (e.message || ""));
    }
  }

  async function submitPlan(id) {
    if (!(await window.confirmAction('Confirm', "Submit this lesson plan for headteacher review?"))) return;
    try {
      await API.academic.submitLessonPlan({ plan_id: id });
      await loadPlans();
      await window.infoDialog('Notice', "Lesson plan submitted for review.");
    } catch (e) {
      console.error("Submit:", e);
      await window.infoDialog('Notice', "Failed to submit.");
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
    const plan = allPlans.find((p) => p.id == id);
    if (!plan) return;
    const copy = {
      topic: (plan.topic || "") + " (Copy)",
      subtopic: plan.subtopic || "",
      learning_area_id: plan.learning_area_id,
      class_id: plan.class_id,
      teacher_id: plan.teacher_id,
      unit_id: plan.unit_id,
      objectives: plan.objectives || "",
      activities: plan.activities || "",
      resources: plan.resources || "",
      assessment: plan.assessment || "",
      homework: plan.homework || "",
      lesson_date: plan.lesson_date || "",
      duration: plan.duration || 40,
      status: "draft",
    };
    try {
      await API.academic.createLessonPlan(copy);
      await loadPlans();
      await window.infoDialog('Notice', "Lesson plan duplicated.");
    } catch (e) {
      console.error("Duplicate:", e);
      await window.infoDialog('Notice', "Failed to duplicate.");
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
        // old standalone route bypassed the approved-scheme workflow.
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
