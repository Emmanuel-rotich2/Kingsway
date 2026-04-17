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
      approved: "Approved",
      submitted: "Pending Review",
      draft: "Draft",
      rejected: "Rejected",
      completed: "Completed",
    };
    return `<span class="badge bg-${m[normalized] || "secondary"}">${labels[normalized] || s}</span>`;
  };

  async function init() {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    await loadReferenceData();
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
      const res = await API.academic.listLessonPlans();
      allPlans = res?.data || res || [];
      if (!Array.isArray(allPlans)) allPlans = [];
      const user =
        typeof AuthContext !== "undefined" ? AuthContext.getUser() : null;
      const uid = user?.id || user?.user_id || "";
      myPlans = allPlans.filter(
        (p) => (p.teacher_id || "").toString() === uid.toString(),
      );
      pendingPlans = allPlans.filter(
        (p) => p.status === "submitted",
      );
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
    let html = `<div class="table-responsive"><table class="table table-hover table-striped">
            <thead class="table-light"><tr><th>#</th><th>Topic</th><th>Subject</th><th>Class</th><th>Teacher</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>`;
    plans.forEach((p, i) => {
      html += `<tr>
                <td>${i + 1}</td>
                <td>${p.topic || ""}</td>
                <td>${p.learning_area_name || ""}</td>
                <td>${p.class_name || ""}</td>
                <td>${p.teacher_name || ""}</td>
                <td>${p.lesson_date || ""}</td>
                <td>${statusBadge(p.status)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="lessonPlansController.viewPlan(${p.id})" title="View"><i class="bi bi-eye"></i></button>
                        ${p.status !== "approved" ? `<button class="btn btn-outline-warning" onclick="lessonPlansController.editPlan(${p.id})" title="Edit"><i class="bi bi-pencil"></i></button>` : ""}
                        ${p.status === "draft" ? `<button class="btn btn-outline-info" onclick="lessonPlansController.submitPlan(${p.id})" title="Submit for Review"><i class="bi bi-send"></i></button>` : ""}
                        ${p.status === "submitted" ? `<button class="btn btn-outline-success" onclick="lessonPlansController.approvePlan(${p.id})" title="Approve"><i class="bi bi-check-circle"></i></button>` : ""}
                        ${p.status === "submitted" ? `<button class="btn btn-outline-danger" onclick="lessonPlansController.rejectPlan(${p.id})" title="Reject"><i class="bi bi-x-circle"></i></button>` : ""}
                        ${p.status !== "approved" ? `<button class="btn btn-outline-danger" onclick="lessonPlansController.deletePlan(${p.id})" title="Delete"><i class="bi bi-trash"></i></button>` : ""}
                        <button class="btn btn-outline-secondary" onclick="lessonPlansController.duplicatePlan(${p.id})" title="Duplicate"><i class="bi bi-files"></i></button>
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
    modal.innerHTML = `<div class="modal fade" id="viewPlanModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
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

  function editPlan(id) {
    const plan = allPlans.find((p) => p.id == id);
    showFormModal(plan);
  }

  function showFormModal(plan = null) {
    const isEdit = !!plan;
    const user = typeof AuthContext !== "undefined" ? AuthContext.getUser() : null;
    const currentTeacherId = user?.staff_id || user?.id || "";

    const classOpts = classes.map(
      (c) => `<option value="${c.id}" ${plan && plan.class_id == c.id ? "selected" : ""}>${c.name}</option>`
    ).join("");
    const laOpts = learningAreas.map(
      (la) => `<option value="${la.id}" ${plan && plan.learning_area_id == la.id ? "selected" : ""}>${la.name}</option>`
    ).join("");
    const teacherOpts = teachers.map(
      (t) => `<option value="${t.id}" ${(plan ? plan.teacher_id == t.id : t.id == currentTeacherId) ? "selected" : ""}>${t.first_name || ""} ${t.last_name || ""}</option>`
    ).join("");

    const m = document.createElement("div");
    m.innerHTML = `<div class="modal fade" id="lessonFormModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">${isEdit ? "Edit" : "Create"} Lesson Plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="lessonPlanForm">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Topic *</label><input class="form-control" name="topic" value="${plan?.topic || ""}" required></div>
                        <div class="col-md-6"><label class="form-label">Subtopic</label><input class="form-control" name="subtopic" value="${plan?.subtopic || ""}"></div>
                        <div class="col-md-4">
                            <label class="form-label">Subject (Learning Area) *</label>
                            <select class="form-select" name="learning_area_id" id="lpFormLearningArea" required>
                                <option value="">Select Subject</option>${laOpts}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Curriculum Unit *</label>
                            <select class="form-select" name="unit_id" id="lpFormUnit" required>
                                <option value="">Select Unit</option>
                                ${plan?.unit_id ? `<option value="${plan.unit_id}" selected>${plan.unit_name || "Unit " + plan.unit_id}</option>` : ""}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Class *</label>
                            <select class="form-select" name="class_id" required>
                                <option value="">Select Class</option>${classOpts}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Teacher *</label>
                            <select class="form-select" name="teacher_id" required>
                                <option value="">Select Teacher</option>${teacherOpts}
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">Lesson Date *</label><input type="date" class="form-control" name="lesson_date" value="${plan?.lesson_date || ""}" required></div>
                        <div class="col-md-4"><label class="form-label">Duration (minutes) *</label><input type="number" class="form-control" name="duration" value="${plan?.duration || "40"}" min="1" required></div>
                        <div class="col-12"><label class="form-label">Objectives *</label><textarea class="form-control" name="objectives" rows="2" required>${plan?.objectives || ""}</textarea></div>
                        <div class="col-12"><label class="form-label">Activities *</label><textarea class="form-control" name="activities" rows="3" required>${plan?.activities || ""}</textarea></div>
                        <div class="col-md-6"><label class="form-label">Resources / Teaching Aids</label><textarea class="form-control" name="resources" rows="2">${plan?.resources || ""}</textarea></div>
                        <div class="col-md-6"><label class="form-label">Assessment</label><textarea class="form-control" name="assessment" rows="2">${plan?.assessment || ""}</textarea></div>
                        <div class="col-12"><label class="form-label">Homework</label><textarea class="form-control" name="homework" rows="2">${plan?.homework || ""}</textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-outline-primary" onclick="lessonPlansController.savePlan(${plan?.id || "null"}, 'draft')"><i class="bi bi-save"></i> Save as Draft</button>
                <button class="btn btn-primary" onclick="lessonPlansController.savePlan(${plan?.id || "null"}, 'submitted')"><i class="bi bi-send"></i> ${isEdit ? "Update & Submit" : "Create & Submit"}</button>
            </div>
        </div></div></div>`;
    document.body.appendChild(m);

    // Wire learning area → curriculum unit cascade
    const laSelect = document.getElementById("lpFormLearningArea");
    if (laSelect) {
      laSelect.addEventListener("change", async () => {
        const unitSelect = document.getElementById("lpFormUnit");
        if (!unitSelect) return;
        unitSelect.innerHTML = '<option value="">Loading...</option>';
        const units = await loadCurriculumUnits(laSelect.value);
        unitSelect.innerHTML = '<option value="">Select Unit</option>' + units.map(
          (u) => `<option value="${u.id}">${u.name}</option>`
        ).join("");
      });
    }

    new bootstrap.Modal(document.getElementById("lessonFormModal")).show();
    document
      .getElementById("lessonFormModal")
      .addEventListener("hidden.bs.modal", () => m.remove());
  }

  async function savePlan(id, status = "draft") {
    const form = document.getElementById("lessonPlanForm");
    const formData = Object.fromEntries(new FormData(form));
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
      alert(id ? "Lesson plan updated." : "Lesson plan created.");
    } catch (e) {
      console.error("Save plan:", e);
      alert("Failed to save lesson plan: " + (e.message || ""));
    }
  }

  async function submitPlan(id) {
    if (!confirm("Submit this lesson plan for headteacher review?")) return;
    try {
      await API.academic.submitLessonPlan({ plan_id: id });
      await loadPlans();
      alert("Lesson plan submitted for review.");
    } catch (e) {
      console.error("Submit:", e);
      alert("Failed to submit.");
    }
  }

  async function approvePlan(id) {
    if (!confirm("Approve this lesson plan?")) return;
    try {
      await API.academic.approveLessonPlan({ plan_id: id });
      await loadPlans();
      alert("Lesson plan approved.");
    } catch (e) {
      console.error("Approve:", e);
      alert("Failed to approve.");
    }
  }

  async function rejectPlan(id) {
    const remarks = prompt("Reason for rejection:");
    if (remarks === null) return; // User cancelled
    try {
      await API.academic.rejectLessonPlan({ plan_id: id, remarks: remarks });
      await loadPlans();
      alert("Lesson plan rejected.");
    } catch (e) {
      console.error("Reject:", e);
      alert("Failed to reject.");
    }
  }

  async function deletePlan(id) {
    if (!confirm("Delete this lesson plan?")) return;
    try {
      await API.academic.deleteLessonPlan(id);
      await loadPlans();
      alert("Lesson plan deleted.");
    } catch (e) {
      console.error("Delete:", e);
      alert("Failed to delete.");
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
      alert("Lesson plan duplicated.");
    } catch (e) {
      console.error("Duplicate:", e);
      alert("Failed to duplicate.");
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
      createBtn.addEventListener("click", () => showFormModal());
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
