/**
 * Manage Lesson Plans Controller
 * CRUD, approval, tabs, stat cards — via API.academic.lessonPlans*
 */
const lessonPlansController = (() => {
  let allPlans = [],
    myPlans = [],
    pendingPlans = [],
    approvedPlans = [];
  const statusBadge = (s) => {
    const m = {
      Approved: "success",
      "Pending Review": "warning",
      Draft: "secondary",
      Rejected: "danger",
    };
    return `<span class="badge bg-${m[s] || "secondary"}">${s}</span>`;
  };

  async function init() {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    await loadPlans();
  }

  async function loadPlans() {
    try {
      const res = await API.academic.listLessonPlans();
      allPlans = res?.data || res || [];
      const user =
        typeof AuthContext !== "undefined" ? AuthContext.getUser() : null;
      const uid = user?.id || user?.user_id || "";
      myPlans = allPlans.filter(
        (p) => (p.teacher_id || "").toString() === uid.toString(),
      );
      pendingPlans = allPlans.filter(
        (p) => p.status === "Pending Review" || p.status === "pending",
      );
      approvedPlans = allPlans.filter(
        (p) => p.status === "Approved" || p.status === "approved",
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
    const cards = document.querySelectorAll(
      ".card.bg-primary h3, .card.bg-success h3, .card.bg-warning h3, .card.bg-info h3",
    );
    if (cards[0]) cards[0].textContent = allPlans.length;
    if (cards[1]) cards[1].textContent = approvedPlans.length;
    if (cards[2]) cards[2].textContent = pendingPlans.length;
    const drafts = allPlans.filter(
      (p) => p.status === "Draft" || p.status === "draft",
    );
    if (cards[3]) cards[3].textContent = drafts.length;
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
            <thead class="table-light"><tr><th>#</th><th>Lesson Title</th><th>Subject</th><th>Class</th><th>Teacher</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>`;
    plans.forEach((p, i) => {
      html += `<tr>
                <td>${i + 1}</td>
                <td>${p.title || p.lesson_title || ""}</td>
                <td>${p.subject_name || p.subject || ""}</td>
                <td>${p.class_name || p.class || ""}</td>
                <td>${p.teacher_name || p.teacher || ""}</td>
                <td>${p.date || p.created_at || ""}</td>
                <td>${statusBadge(p.status || "Draft")}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="lessonPlansController.viewPlan(${p.id})" title="View"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-outline-warning" onclick="lessonPlansController.editPlan(${p.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                        ${p.status === "Pending Review" || p.status === "pending" ? `<button class="btn btn-outline-success" onclick="lessonPlansController.approvePlan(${p.id})" title="Approve"><i class="bi bi-check-circle"></i></button>` : ""}
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
            <div class="modal-header"><h5 class="modal-title">${plan.title || plan.lesson_title}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Subject:</strong> ${plan.subject_name || plan.subject || ""}</div>
                    <div class="col-md-4"><strong>Class:</strong> ${plan.class_name || plan.class || ""}</div>
                    <div class="col-md-4"><strong>Teacher:</strong> ${plan.teacher_name || plan.teacher || ""}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Date:</strong> ${plan.date || plan.created_at || ""}</div>
                    <div class="col-md-4"><strong>Status:</strong> ${statusBadge(plan.status || "Draft")}</div>
                    <div class="col-md-4"><strong>Duration:</strong> ${plan.duration || "N/A"}</div>
                </div>
                <hr>
                <h6>Objectives</h6><p>${plan.objectives || "No objectives specified."}</p>
                <h6>Content</h6><p>${plan.content || plan.description || "No content specified."}</p>
                <h6>Teaching Aids</h6><p>${plan.teaching_aids || plan.resources || "N/A"}</p>
                <h6>Assessment</h6><p>${plan.assessment || "N/A"}</p>
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
    const m = document.createElement("div");
    m.innerHTML = `<div class="modal fade" id="lessonFormModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">${isEdit ? "Edit" : "Create"} Lesson Plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="lessonPlanForm">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" value="${plan?.title || plan?.lesson_title || ""}" required></div>
                        <div class="col-md-3"><label class="form-label">Subject</label><input class="form-control" name="subject" value="${plan?.subject_name || plan?.subject || ""}"></div>
                        <div class="col-md-3"><label class="form-label">Class</label><input class="form-control" name="class" value="${plan?.class_name || plan?.class || ""}"></div>
                        <div class="col-md-6"><label class="form-label">Date</label><input type="date" class="form-control" name="date" value="${plan?.date || ""}"></div>
                        <div class="col-md-6"><label class="form-label">Duration</label><input class="form-control" name="duration" value="${plan?.duration || ""}" placeholder="e.g. 40 minutes"></div>
                        <div class="col-12"><label class="form-label">Objectives</label><textarea class="form-control" name="objectives" rows="2">${plan?.objectives || ""}</textarea></div>
                        <div class="col-12"><label class="form-label">Content / Activities</label><textarea class="form-control" name="content" rows="4">${plan?.content || plan?.description || ""}</textarea></div>
                        <div class="col-md-6"><label class="form-label">Teaching Aids</label><textarea class="form-control" name="teaching_aids" rows="2">${plan?.teaching_aids || plan?.resources || ""}</textarea></div>
                        <div class="col-md-6"><label class="form-label">Assessment</label><textarea class="form-control" name="assessment" rows="2">${plan?.assessment || ""}</textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="lessonPlansController.savePlan(${plan?.id || "null"})">${isEdit ? "Update" : "Create"}</button>
            </div>
        </div></div></div>`;
    document.body.appendChild(m);
    new bootstrap.Modal(document.getElementById("lessonFormModal")).show();
    document
      .getElementById("lessonFormModal")
      .addEventListener("hidden.bs.modal", () => m.remove());
  }

  async function savePlan(id) {
    const form = document.getElementById("lessonPlanForm");
    const data = Object.fromEntries(new FormData(form));
    try {
      if (id) {
        await API.academic.updateLessonPlan(id, data);
      } else {
        await API.academic.createLessonPlan(data);
      }
      bootstrap.Modal.getInstance(
        document.getElementById("lessonFormModal"),
      )?.hide();
      await loadPlans();
      alert(id ? "Lesson plan updated." : "Lesson plan created.");
    } catch (e) {
      console.error("Save plan:", e);
      alert("Failed to save lesson plan.");
    }
  }

  async function approvePlan(id) {
    if (!confirm("Approve this lesson plan?")) return;
    try {
      await API.academic.approveLessonPlan(id);
      await loadPlans();
      alert("Lesson plan approved.");
    } catch (e) {
      console.error("Approve:", e);
      alert("Failed to approve.");
    }
  }

  async function duplicatePlan(id) {
    const plan = allPlans.find((p) => p.id == id);
    if (!plan) return;
    const copy = {
      ...plan,
      title: (plan.title || plan.lesson_title) + " (Copy)",
      status: "Draft",
    };
    delete copy.id;
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
    approvePlan,
    duplicatePlan,
  };
})();

document.addEventListener("DOMContentLoaded", () =>
  lessonPlansController.init(),
);
