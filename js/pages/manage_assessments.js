/**
 * Manage Assessments Controller
 * Full assessment lifecycle: create, enter marks, approve, publish, export
 * Targets IDs: totalAssessments, completedAssessments, inProgressAssessments, scheduledAssessments,
 *   pendingApproval, marksNotEntered, readyToPublish, publishedThisTerm,
 *   searchAssessment, classFilter, subjectFilter, statusFilter, termFilter,
 *   tabs: allAssessments, exams, tests, assignments, myAssessments, pendingApprovalTab
 */
const assessmentsController = (() => {
  let allData = [],
    classes = [],
    subjects = [];
  const statusBadge = (s) => {
    const m = {
      Scheduled: "info",
      "In Progress": "warning",
      Completed: "success",
      Published: "primary",
      Draft: "secondary",
    };
    return `<span class="badge bg-${m[s] || "secondary"}">${s}</span>`;
  };
  const typeBadge = (t) => {
    const m = {
      Exam: "danger",
      CAT: "warning",
      Test: "warning",
      Assignment: "info",
      Project: "success",
      Quiz: "secondary",
    };
    return `<span class="badge bg-${m[t] || "secondary"}">${t}</span>`;
  };

  async function init() {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }
    await loadFilters();
    bindEvents();
    await loadAssessments();
  }

  async function loadFilters() {
    try {
      const [clsRes, subRes] = await Promise.all([
        API.academic.listClasses(),
        API.academic.listLearningAreas(),
      ]);
      classes = clsRes?.data || clsRes || [];
      subjects = subRes?.data || subRes || [];
      const cf = document.getElementById("classFilter");
      if (cf)
        classes.forEach((c) => {
          const o = document.createElement("option");
          o.value = c.id || c.class_id;
          o.textContent = c.class_name || c.name;
          cf.appendChild(o);
        });
      const sf = document.getElementById("subjectFilter");
      if (sf)
        subjects.forEach((s) => {
          const o = document.createElement("option");
          o.value = s.id || s.subject_id;
          o.textContent = s.subject_name || s.name;
          sf.appendChild(o);
        });
    } catch (e) {
      console.error("Load filters:", e);
    }
  }

  function bindEvents() {
    document
      .getElementById("searchAssessment")
      ?.addEventListener("input", applyFilters);
    document
      .getElementById("classFilter")
      ?.addEventListener("change", applyFilters);
    document
      .getElementById("subjectFilter")
      ?.addEventListener("change", applyFilters);
    document
      .getElementById("statusFilter")
      ?.addEventListener("change", applyFilters);
    document
      .getElementById("termFilter")
      ?.addEventListener("change", applyFilters);
    // Wire create button
    const createBtn = document.querySelector(
      '[data-bs-target="#addAssessmentModal"]',
    );
    if (createBtn) {
      createBtn.removeAttribute("data-bs-toggle");
      createBtn.removeAttribute("data-bs-target");
      createBtn.addEventListener("click", () => showCreateModal());
    }
  }

  async function loadAssessments() {
    try {
      const res = await API.academic.getCustom({ action: "list-assessments" });
      allData = res?.data || res || [];
    } catch (e) {
      try {
        const res2 = await API.apiCall(
          "/api/?route=assessments&action=list",
          "GET",
        );
        allData = res2?.data || res2 || [];
      } catch (e2) {
        console.error("Load assessments:", e2);
      }
    }
    updateStats();
    applyFilters();
  }

  function updateStats() {
    const el = (id) => document.getElementById(id);
    const completed = allData.filter(
      (a) => (a.status || "").toLowerCase() === "completed",
    );
    const inProgress = allData.filter((a) =>
      (a.status || "").toLowerCase().includes("progress"),
    );
    const scheduled = allData.filter(
      (a) => (a.status || "").toLowerCase() === "scheduled",
    );
    const published = allData.filter(
      (a) => (a.status || "").toLowerCase() === "published",
    );
    const pending = allData.filter((a) =>
      (a.status || "").toLowerCase().includes("pending"),
    );
    const noMarks = allData.filter(
      (a) => !a.marks_entered && (a.status || "").toLowerCase() !== "scheduled",
    );
    const ready = allData.filter(
      (a) => a.marks_entered && (a.status || "").toLowerCase() === "completed",
    );

    if (el("totalAssessments"))
      el("totalAssessments").textContent = allData.length;
    if (el("completedAssessments"))
      el("completedAssessments").textContent = completed.length;
    if (el("inProgressAssessments"))
      el("inProgressAssessments").textContent = inProgress.length;
    if (el("scheduledAssessments"))
      el("scheduledAssessments").textContent = scheduled.length;
    if (el("pendingApproval"))
      el("pendingApproval").textContent = pending.length;
    if (el("marksNotEntered"))
      el("marksNotEntered").textContent = noMarks.length;
    if (el("readyToPublish")) el("readyToPublish").textContent = ready.length;
    if (el("publishedThisTerm"))
      el("publishedThisTerm").textContent = published.length;
  }

  function applyFilters() {
    const search = (
      document.getElementById("searchAssessment")?.value || ""
    ).toLowerCase();
    const classF = document.getElementById("classFilter")?.value || "";
    const subjectF = document.getElementById("subjectFilter")?.value || "";
    const statusF = document.getElementById("statusFilter")?.value || "";
    const termF = document.getElementById("termFilter")?.value || "";

    let filtered = allData.filter((a) => {
      if (
        search &&
        !(a.name || a.assessment_name || "").toLowerCase().includes(search) &&
        !(a.class_name || "").toLowerCase().includes(search)
      )
        return false;
      if (classF && (a.class_id || "").toString() !== classF) return false;
      if (subjectF && (a.subject_id || "").toString() !== subjectF)
        return false;
      if (
        statusF &&
        (a.status || "").toLowerCase().replace(/\s/g, "_") !== statusF
      )
        return false;
      if (termF && (a.term || "").toString() !== termF) return false;
      return true;
    });

    const user =
      typeof AuthContext !== "undefined" ? AuthContext.getUser() : null;
    const uid = (user?.id || user?.user_id || "").toString();

    renderTable("allAssessments", filtered);
    renderTable(
      "exams",
      filtered.filter(
        (a) => (a.type || a.assessment_type || "").toLowerCase() === "exam",
      ),
    );
    renderTable(
      "tests",
      filtered.filter((a) =>
        ["test", "cat", "quiz"].includes(
          (a.type || a.assessment_type || "").toLowerCase(),
        ),
      ),
    );
    renderTable(
      "assignments",
      filtered.filter((a) =>
        ["assignment", "project"].includes(
          (a.type || a.assessment_type || "").toLowerCase(),
        ),
      ),
    );
    renderTable(
      "myAssessments",
      filtered.filter((a) => (a.teacher_id || "").toString() === uid),
    );
    renderTable(
      "pendingApprovalTab",
      filtered.filter((a) =>
        (a.status || "").toLowerCase().includes("pending"),
      ),
    );
  }

  function renderTable(tabId, items) {
    const pane = document.getElementById(tabId);
    if (!pane) return;
    if (items.length === 0) {
      pane.innerHTML =
        '<div class="text-center text-muted py-4">No assessments found.</div>';
      return;
    }
    let html = `<div class="table-responsive"><table class="table table-hover table-striped">
            <thead class="table-light"><tr><th>#</th><th>Assessment</th><th>Type</th><th>Class</th><th>Subject</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>`;
    items.forEach((a, i) => {
      html += `<tr>
                <td>${i + 1}</td>
                <td>${a.name || a.assessment_name || ""}</td>
                <td>${typeBadge(a.type || a.assessment_type || "Exam")}</td>
                <td>${a.class_name || a.class || ""}</td>
                <td>${a.subject_name || a.subject || ""}</td>
                <td>${a.date || a.created_at || ""}</td>
                <td>${statusBadge(a.status || "Draft")}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="assessmentsController.viewAssessment(${a.id})" title="View"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-outline-warning" onclick="assessmentsController.editAssessment(${a.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-info" onclick="assessmentsController.enterMarksFor(${a.id})" title="Enter Marks"><i class="bi bi-pencil-square"></i></button>
                        <button class="btn btn-outline-success" onclick="assessmentsController.publishAssessment(${a.id})" title="Publish"><i class="bi bi-check2-circle"></i></button>
                    </div>
                </td>
            </tr>`;
    });
    html += "</tbody></table></div>";
    pane.innerHTML = html;
  }

  function viewAssessment(id) {
    const a = allData.find((x) => x.id == id);
    if (!a) return;
    alert(
      `Assessment: ${a.name || a.assessment_name}\nClass: ${a.class_name || a.class}\nSubject: ${a.subject_name || a.subject}\nDate: ${a.date || a.created_at}\nStatus: ${a.status}\nType: ${a.type || a.assessment_type}`,
    );
  }

  function editAssessment(id) {
    const a = allData.find((x) => x.id == id);
    showCreateModal(a);
  }

  function showCreateModal(assessment = null) {
    const isEdit = !!assessment;
    const classOpts = classes
      .map(
        (c) =>
          `<option value="${c.id || c.class_id}" ${assessment && (assessment.class_id || "").toString() === (c.id || c.class_id).toString() ? "selected" : ""}>${c.class_name || c.name}</option>`,
      )
      .join("");
    const subOpts = subjects
      .map(
        (s) =>
          `<option value="${s.id || s.subject_id}" ${assessment && (assessment.subject_id || "").toString() === (s.id || s.subject_id).toString() ? "selected" : ""}>${s.subject_name || s.name}</option>`,
      )
      .join("");
    const m = document.createElement("div");
    m.innerHTML = `<div class="modal fade" id="assessmentFormModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">${isEdit ? "Edit" : "Create"} Assessment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><form id="assessmentForm"><div class="row g-3">
                <div class="col-12"><label class="form-label">Assessment Name</label><input class="form-control" name="name" value="${assessment?.name || assessment?.assessment_name || ""}" required></div>
                <div class="col-md-6"><label class="form-label">Type</label><select class="form-select" name="type">
                    <option value="Exam" ${(assessment?.type || "") == "Exam" ? "selected" : ""}>Exam</option><option value="CAT" ${(assessment?.type || "") == "CAT" ? "selected" : ""}>CAT</option>
                    <option value="Test" ${(assessment?.type || "") == "Test" ? "selected" : ""}>Test</option><option value="Assignment" ${(assessment?.type || "") == "Assignment" ? "selected" : ""}>Assignment</option>
                    <option value="Project" ${(assessment?.type || "") == "Project" ? "selected" : ""}>Project</option></select></div>
                <div class="col-md-6"><label class="form-label">Date</label><input type="date" class="form-control" name="date" value="${assessment?.date || ""}"></div>
                <div class="col-md-6"><label class="form-label">Class</label><select class="form-select" name="class_id"><option value="">Select</option>${classOpts}</select></div>
                <div class="col-md-6"><label class="form-label">Subject</label><select class="form-select" name="subject_id"><option value="">Select</option>${subOpts}</select></div>
                <div class="col-md-6"><label class="form-label">Term</label><select class="form-select" name="term"><option value="1">Term 1</option><option value="2">Term 2</option><option value="3">Term 3</option></select></div>
                <div class="col-md-6"><label class="form-label">Total Marks</label><input type="number" class="form-control" name="total_marks" value="${assessment?.total_marks || 100}"></div>
            </div></form></div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="assessmentsController.saveAssessment(${assessment?.id || "null"})">${isEdit ? "Update" : "Create"}</button></div>
        </div></div></div>`;
    document.body.appendChild(m);
    new bootstrap.Modal(document.getElementById("assessmentFormModal")).show();
    document
      .getElementById("assessmentFormModal")
      .addEventListener("hidden.bs.modal", () => m.remove());
  }

  async function saveAssessment(id) {
    const data = Object.fromEntries(
      new FormData(document.getElementById("assessmentForm")),
    );
    try {
      if (id) {
        await API.academic.getCustom({
          action: "update-assessment",
          id,
          ...data,
        });
      } else {
        await API.academic.getCustom({ action: "create-assessment", ...data });
      }
      bootstrap.Modal.getInstance(
        document.getElementById("assessmentFormModal"),
      )?.hide();
      await loadAssessments();
      alert(id ? "Assessment updated." : "Assessment created.");
    } catch (e) {
      console.error("Save:", e);
      alert("Failed to save assessment.");
    }
  }

  function enterMarksFor(id) {
    window.location.href = `/Kingsway/home.php?route=enter_results&assessment_id=${id}`;
  }

  function showBulkMarksModal() {
    window.location.href = "/Kingsway/home.php?route=enter_results";
  }

  async function publishAssessment(id) {
    if (
      !confirm(
        "Publish results for this assessment? Students and parents will be able to view them.",
      )
    )
      return;
    try {
      await API.academic.approveResults({ assessment_id: id });
      await loadAssessments();
      alert("Results published successfully.");
    } catch (e) {
      console.error("Publish:", e);
      alert("Failed to publish.");
    }
  }

  function showPublishModal() {
    const ready = allData.filter(
      (a) => a.marks_entered && (a.status || "").toLowerCase() === "completed",
    );
    if (ready.length === 0) {
      alert("No assessments are ready to publish.");
      return;
    }
    let list = ready
      .map(
        (a) => `• ${a.name || a.assessment_name} (${a.class_name || a.class})`,
      )
      .join("\n");
    if (
      confirm(`Publish results for ${ready.length} assessment(s)?\n\n${list}`)
    ) {
      ready.forEach((a) => publishAssessment(a.id));
    }
  }

  function exportResults() {
    const pane = document.querySelector(".tab-pane.active .table");
    if (!pane) {
      alert("No data to export.");
      return;
    }
    let csv = "";
    pane.querySelectorAll("tr").forEach((row) => {
      const cells = [];
      row
        .querySelectorAll("th,td")
        .forEach((c) =>
          cells.push('"' + c.textContent.replace(/"/g, '""').trim() + '"'),
        );
      csv += cells.join(",") + "\n";
    });
    const blob = new Blob([csv], { type: "text/csv" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "assessments_export.csv";
    a.click();
  }

  return {
    init,
    loadAssessments,
    viewAssessment,
    editAssessment,
    saveAssessment,
    enterMarksFor,
    showBulkMarksModal,
    publishAssessment,
    showPublishModal,
    exportResults,
  };
})();

document.addEventListener("DOMContentLoaded", () =>
  assessmentsController.init(),
);
