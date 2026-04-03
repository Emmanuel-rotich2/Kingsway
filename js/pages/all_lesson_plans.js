/**
 * All Lesson Plans Page Controller
 * Manages lesson plan listing, CRUD, and submission workflow
 */
const AllLessonPlansController = (() => {
  let lessonPlans = [];
  let teachers = [];
  let subjects = [];
  let classes = [];
  let pagination = { page: 1, limit: 15, total: 0 };

  async function loadData(page = 1) {
    try {
      pagination.page = page;
      const params = new URLSearchParams({ page, limit: pagination.limit });

      const teacher = document.getElementById("teacherFilterLP")?.value;
      if (teacher) params.append("teacher_id", teacher);
      const subject = document.getElementById("subjectFilterLP")?.value;
      if (subject) params.append("subject_id", subject);
      const cls = document.getElementById("classFilterLP")?.value;
      if (cls) params.append("class_id", cls);
      const status = document.getElementById("statusFilterLP")?.value;
      if (status) params.append("status", status);
      const search = document.getElementById("searchLessonPlans")?.value;
      if (search) params.append("search", search);

      const response = await window.API.apiCall(
        `/academic/lesson-plans?${params.toString()}`,
        "GET",
      );
      const data = response?.data || response || [];
      lessonPlans = Array.isArray(data)
        ? data
        : data.lesson_plans || data.data || [];
      if (data.pagination) pagination = { ...pagination, ...data.pagination };
      pagination.total = data.total || lessonPlans.length;

      renderStats(lessonPlans);
      renderTable(lessonPlans);
      renderPagination();
    } catch (e) {
      console.error("Load lesson plans failed:", e);
      renderTable([]);
    }
  }

  async function loadReferenceData() {
    try {
      const [teacherResp, subjectResp, classResp] = await Promise.all([
        window.API.apiCall("/staff/teachers", "GET").catch(() => []),
        window.API.apiCall("/academic/subjects", "GET").catch(() => []),
        window.API.apiCall("/academic/classes", "GET").catch(() => []),
      ]);
      teachers = Array.isArray(teacherResp?.data || teacherResp)
        ? teacherResp?.data || teacherResp
        : [];
      subjects = Array.isArray(subjectResp?.data || subjectResp)
        ? subjectResp?.data || subjectResp
        : [];
      classes = Array.isArray(classResp?.data || classResp)
        ? classResp?.data || classResp
        : [];
      populateDropdowns();
    } catch (e) {
      console.warn("Failed to load reference data:", e);
    }
  }

  function populateDropdowns() {
    const populate = (ids, items, labelFn) => {
      ids.forEach((selectId) => {
        const el = document.getElementById(selectId);
        if (!el) return;
        const first = el.options[0];
        el.innerHTML = "";
        el.appendChild(first);
        items.forEach((item) => {
          const opt = document.createElement("option");
          opt.value = item.id;
          opt.textContent = labelFn(item);
          el.appendChild(opt);
        });
      });
    };

    populate(["teacherFilterLP"], teachers, (t) =>
      `${t.first_name || ""} ${t.last_name || ""}`.trim(),
    );
    populate(
      ["subjectFilterLP", "lpSubject"],
      subjects,
      (s) => s.name || s.subject_name || "",
    );
    populate(
      ["classFilterLP", "lpClass"],
      classes,
      (c) => c.name || c.class_name || "",
    );
  }

  function renderStats(data) {
    const total = pagination.total || data.length;
    const approved = data.filter(
      (p) => (p.status || "").toLowerCase() === "approved",
    ).length;
    const pending = data.filter(
      (p) => (p.status || "").toLowerCase() === "pending",
    ).length;
    const rejected = data.filter(
      (p) => (p.status || "").toLowerCase() === "rejected",
    ).length;

    document.getElementById("totalPlans").textContent = total;
    document.getElementById("approvedPlans").textContent = approved;
    document.getElementById("pendingPlans").textContent = pending;
    document.getElementById("rejectedPlans").textContent = rejected;
  }

  function renderTable(items) {
    const tbody = document.getElementById("lessonPlansTableBody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center py-4 text-muted">No lesson plans found</td></tr>';
      return;
    }

    const statusColors = {
      approved: "success",
      pending: "warning",
      rejected: "danger",
      draft: "secondary",
    };

    tbody.innerHTML = items
      .map((lp, i) => {
        const statusColor =
          statusColors[(lp.status || "").toLowerCase()] || "secondary";
        return `
                <tr>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td><strong>${lp.title || "-"}</strong></td>
                    <td>${lp.teacher_name || ((lp.first_name || "") + " " + (lp.last_name || "")).trim() || "-"}</td>
                    <td>${lp.subject_name || "-"}</td>
                    <td>${lp.class_name || "-"}</td>
                    <td>${lp.date || lp.lesson_date || "-"}</td>
                    <td><span class="badge bg-${statusColor}">${lp.status || "draft"}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info btn-sm" onclick="AllLessonPlansController.view(${lp.id})" title="View"><i class="bi bi-eye"></i></button>
                            <button class="btn btn-warning btn-sm" onclick="AllLessonPlansController.edit(${lp.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="AllLessonPlansController.remove(${lp.id})" title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `;
      })
      .join("");
  }

  function renderPagination() {
    const container = document.getElementById("pagination");
    if (!container) return;
    const totalPages = Math.ceil(pagination.total / pagination.limit);

    const fromEl = document.getElementById("showingFrom");
    const toEl = document.getElementById("showingTo");
    const totalEl = document.getElementById("totalRecords");
    if (fromEl)
      fromEl.textContent =
        pagination.total > 0 ? (pagination.page - 1) * pagination.limit + 1 : 0;
    if (toEl)
      toEl.textContent = Math.min(
        pagination.page * pagination.limit,
        pagination.total,
      );
    if (totalEl) totalEl.textContent = pagination.total;

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `<li class="page-item ${i === pagination.page ? "active" : ""}">
                <a class="page-link" href="#" onclick="AllLessonPlansController.loadPage(${i}); return false;">${i}</a>
            </li>`;
    }
    container.innerHTML = html;
  }

  function openModal(plan = null) {
    document.getElementById("lessonPlanId").value = plan?.id || "";
    document.getElementById("lpTitle").value = plan?.title || "";
    document.getElementById("lpSubject").value = plan?.subject_id || "";
    document.getElementById("lpClass").value = plan?.class_id || "";
    document.getElementById("lpDate").value =
      plan?.date || plan?.lesson_date || "";
    document.getElementById("lpObjectives").value =
      plan?.objectives || plan?.learning_objectives || "";
    document.getElementById("lpContent").value =
      plan?.content || plan?.activities || "";
    document.getElementById("lpResources").value =
      plan?.resources || plan?.materials || "";
    document.getElementById("lpAssessment").value = plan?.assessment || "";
    document.getElementById("lessonPlanModalLabel").textContent = plan
      ? "Edit Lesson Plan"
      : "New Lesson Plan";
    new bootstrap.Modal(document.getElementById("lessonPlanModal")).show();
  }

  async function save(status = "pending") {
    const id = document.getElementById("lessonPlanId").value;
    const payload = {
      title: document.getElementById("lpTitle").value,
      subject_id: document.getElementById("lpSubject").value,
      class_id: document.getElementById("lpClass").value,
      date: document.getElementById("lpDate").value,
      objectives: document.getElementById("lpObjectives").value || null,
      content: document.getElementById("lpContent").value || null,
      resources: document.getElementById("lpResources").value || null,
      assessment: document.getElementById("lpAssessment").value || null,
      status,
    };
    if (
      !payload.title ||
      !payload.subject_id ||
      !payload.class_id ||
      !payload.date
    ) {
      showNotification("Please fill all required fields", "error");
      return;
    }
    try {
      if (id) {
        await window.API.apiCall(
          `/academic/lesson-plans/${id}`,
          "PUT",
          payload,
        );
      } else {
        await window.API.apiCall("/academic/lesson-plans", "POST", payload);
      }
      bootstrap.Modal.getInstance(
        document.getElementById("lessonPlanModal"),
      ).hide();
      showNotification(
        id ? "Lesson plan updated" : "Lesson plan created",
        "success",
      );
      await loadData();
    } catch (e) {
      showNotification(e.message || "Failed to save lesson plan", "error");
    }
  }

  async function view(id) {
    try {
      const resp = await window.API.apiCall(
        `/academic/lesson-plans/${id}`,
        "GET",
      );
      const lp = resp?.data || resp;
      const statusColors = {
        approved: "success",
        pending: "warning",
        rejected: "danger",
        draft: "secondary",
      };
      const content = document.getElementById("viewLessonPlanContent");
      content.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-8"><h5>${lp.title || "-"}</h5></div>
                    <div class="col-md-4 text-end"><span class="badge bg-${statusColors[(lp.status || "").toLowerCase()] || "secondary"}">${lp.status || "draft"}</span></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4"><p><strong>Teacher:</strong> ${lp.teacher_name || "-"}</p></div>
                    <div class="col-md-4"><p><strong>Subject:</strong> ${lp.subject_name || "-"}</p></div>
                    <div class="col-md-4"><p><strong>Date:</strong> ${lp.date || lp.lesson_date || "-"}</p></div>
                </div>
                <div class="mb-3"><h6>Learning Objectives</h6><p>${lp.objectives || lp.learning_objectives || "Not specified"}</p></div>
                <div class="mb-3"><h6>Content / Activities</h6><p>${lp.content || lp.activities || "Not specified"}</p></div>
                <div class="mb-3"><h6>Resources</h6><p>${lp.resources || lp.materials || "Not specified"}</p></div>
                <div class="mb-3"><h6>Assessment</h6><p>${lp.assessment || "Not specified"}</p></div>
                ${lp.feedback ? `<div class="mb-3"><h6>Reviewer Feedback</h6><div class="alert alert-info">${lp.feedback}</div></div>` : ""}
            `;
      new bootstrap.Modal(
        document.getElementById("viewLessonPlanModal"),
      ).show();
    } catch (e) {
      showNotification("Failed to load lesson plan", "error");
    }
  }

  async function edit(id) {
    try {
      const resp = await window.API.apiCall(
        `/academic/lesson-plans/${id}`,
        "GET",
      );
      openModal(resp?.data || resp);
    } catch (e) {
      showNotification("Failed to load lesson plan", "error");
    }
  }

  async function remove(id) {
    if (!confirm("Delete this lesson plan?")) return;
    try {
      await window.API.apiCall(`/academic/lesson-plans/${id}`, "DELETE");
      showNotification("Lesson plan deleted", "success");
      await loadData();
    } catch (e) {
      showNotification("Failed to delete", "error");
    }
  }

  function showNotification(message, type) {
    if (window.API?.showNotification)
      window.API.showNotification(message, type);
    else alert((type === "error" ? "Error: " : "") + message);
  }

  function attachListeners() {
    document
      .getElementById("addLessonPlanBtn")
      ?.addEventListener("click", () => openModal());
    document
      .getElementById("submitLessonPlanBtn")
      ?.addEventListener("click", () => save("pending"));
    document
      .getElementById("saveDraftBtn")
      ?.addEventListener("click", () => save("draft"));
    document
      .getElementById("teacherFilterLP")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("subjectFilterLP")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("classFilterLP")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("statusFilterLP")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("searchLessonPlans")
      ?.addEventListener("keyup", () => {
        clearTimeout(window._lpSearchTimeout);
        window._lpSearchTimeout = setTimeout(() => loadData(1), 300);
      });
    document
      .getElementById("exportLessonPlansBtn")
      ?.addEventListener("click", () => {
        window.open(
          (window.APP_BASE || "") + "/api/?route=academic/lesson-plans/export&format=csv",
          "_blank",
        );
      });
  }

  async function init() {
    attachListeners();
    await loadReferenceData();
    await loadData();
  }

  return { init, refresh: loadData, loadPage: loadData, view, edit, remove };
})();

document.addEventListener("DOMContentLoaded", () =>
  AllLessonPlansController.init(),
);
