/**
 * Lesson Plan Approval Page Controller
 * Manages lesson plan review and approval workflow
 */
const LessonPlanApprovalController = (() => {
  let plans = [];
  let pagination = { page: 1, limit: 15, total: 0 };

  async function loadData(page = 1) {
    try {
      pagination.page = page;
      const params = new URLSearchParams({ page, limit: pagination.limit });

      const teacher = document.getElementById("teacherFilterApproval")?.value;
      if (teacher) params.append("teacher_id", teacher);
      const subject = document.getElementById("subjectFilterApproval")?.value;
      if (subject) params.append("subject_id", subject);
      const dateFrom = document.getElementById("dateFromApproval")?.value;
      if (dateFrom) params.append("date_from", dateFrom);
      const dateTo = document.getElementById("dateToApproval")?.value;
      if (dateTo) params.append("date_to", dateTo);
      const status = document.getElementById("approvalStatusFilter")?.value;
      if (status) params.append("status", status);

      const response = await window.API.academic.listLessonPlansApproval(
        Object.fromEntries(params),
      );
      const data = response?.data || response || [];
      plans = Array.isArray(data) ? data : data.lesson_plans || data.data || [];
      if (data.pagination) pagination = { ...pagination, ...data.pagination };
      pagination.total = data.total || plans.length;

      renderStats(plans);
      renderTable(plans);
      renderPagination();
    } catch (e) {
      console.error("Load approval list failed:", e);
      renderTable([]);
    }
  }

  async function loadReferenceData() {
    try {
      const [teacherResp, subjectResp] = await Promise.all([
        window.API.apiCall("/staff/teachers", "GET").catch(() => []),
        window.API.apiCall("/academic/subjects", "GET").catch(() => []),
      ]);
      const teachers = Array.isArray(teacherResp?.data || teacherResp)
        ? teacherResp?.data || teacherResp
        : [];
      const subjects = Array.isArray(subjectResp?.data || subjectResp)
        ? subjectResp?.data || subjectResp
        : [];

      const teacherSelect = document.getElementById("teacherFilterApproval");
      if (teacherSelect) {
        teachers.forEach((t) => {
          const opt = document.createElement("option");
          opt.value = t.id;
          opt.textContent = `${t.first_name || ""} ${t.last_name || ""}`.trim();
          teacherSelect.appendChild(opt);
        });
      }

      const subjectSelect = document.getElementById("subjectFilterApproval");
      if (subjectSelect) {
        subjects.forEach((s) => {
          const opt = document.createElement("option");
          opt.value = s.id;
          opt.textContent = s.name || s.subject_name || "";
          subjectSelect.appendChild(opt);
        });
      }
    } catch (e) {
      console.warn("Failed to load reference data:", e);
    }
  }

  function renderStats(data) {
    const pending = data.filter(
      (p) => (p.status || "").toLowerCase() === "submitted",
    ).length;
    const today = new Date().toISOString().split("T")[0];
    const approvedToday = data.filter(
      (p) =>
        (p.status || "").toLowerCase() === "approved" &&
        (p.approved_at || p.approved_date || "").startsWith(today),
    ).length;
    const rejected = data.filter(
      (p) => (p.status || "").toLowerCase() === "rejected",
    ).length;

    document.getElementById("pendingApproval").textContent = pending;
    document.getElementById("approvedToday").textContent = approvedToday;
    document.getElementById("rejectedCount").textContent = rejected;
    document.getElementById("avgReviewTime").textContent =
      data.length > 0 ? "~2 days" : "-";
  }

  function renderTable(items) {
    const tbody = document.getElementById("approvalTableBody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center py-4 text-muted">No lesson plans to review</td></tr>';
      return;
    }

    const statusColors = {
      approved: "success",
      submitted: "warning",
      rejected: "danger",
      draft: "secondary",
    };

    tbody.innerHTML = items
      .map((lp, i) => {
        const statusColor =
          statusColors[(lp.status || "").toLowerCase()] || "secondary";
        return `
                <tr>
                    <td><input type="checkbox" class="approval-select" value="${lp.id}"></td>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td><strong>${lp.title || "-"}</strong></td>
                    <td>${lp.teacher_name || "-"}</td>
                    <td>${lp.subject_name || "-"}</td>
                    <td>${lp.class_name || "-"}</td>
                    <td>${lp.submitted_date || lp.created_at || "-"}</td>
                    <td><span class="badge bg-${statusColor}">${lp.status || "pending"}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-primary btn-sm" onclick="LessonPlanApprovalController.review(${lp.id})" title="Review">
                                <i class="bi bi-eye"></i> Review
                            </button>
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
                <a class="page-link" href="#" onclick="LessonPlanApprovalController.loadPage(${i}); return false;">${i}</a>
            </li>`;
    }
    container.innerHTML = html;
  }

  async function review(id) {
    try {
      const resp = await window.API.academic.getLessonPlan(id);
      const lp = resp?.data || resp;

      document.getElementById("reviewPlanId").value = id;
      document.getElementById("reviewFeedback").value = "";
      document.getElementById("reviewPlanContent").innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-8"><h5>${lp.title || "-"}</h5></div>
                    <div class="col-md-4 text-end"><span class="badge bg-warning">${lp.status || "pending"}</span></div>
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
            `;
      document.getElementById("reviewModalLabel").textContent =
        `Review: ${lp.title || "Lesson Plan"}`;
      new bootstrap.Modal(document.getElementById("reviewModal")).show();
    } catch (e) {
      showNotification("Failed to load lesson plan", "error");
    }
  }

  async function submitReview(status) {
    const id = document.getElementById("reviewPlanId").value;
    const feedback = document.getElementById("reviewFeedback").value;

    if (!feedback && status === "rejected") {
      showNotification(
        "Please provide feedback when rejecting a plan",
        "error",
      );
      return;
    }

    try {
      await window.API.academic.reviewLessonPlan(id, {
        status,
        feedback,
      });
      bootstrap.Modal.getInstance(
        document.getElementById("reviewModal"),
      ).hide();
      showNotification(`Lesson plan ${status}`, "success");
      await loadData();
    } catch (e) {
      showNotification(e.message || "Failed to submit review", "error");
    }
  }

  async function bulkApprove() {
    const selected = Array.from(
      document.querySelectorAll(".approval-select:checked"),
    ).map((cb) => cb.value);
    if (!selected.length) {
      showNotification("Please select lesson plans to approve", "error");
      return;
    }
    if (!confirm(`Approve ${selected.length} selected lesson plans?`)) return;

    try {
      await window.API.academic.bulkApproveLessonPlans(selected);
      showNotification(`${selected.length} plans approved`, "success");
      await loadData();
    } catch (e) {
      showNotification(e.message || "Bulk approval failed", "error");
    }
  }

  function showNotification(message, type) {
    if (window.API?.showNotification)
      window.API.showNotification(message, type);
    else alert((type === "error" ? "Error: " : "") + message);
  }

  function attachListeners() {
    document
      .getElementById("approvePlanBtn")
      ?.addEventListener("click", () => submitReview("approved"));
    document
      .getElementById("rejectPlanBtn")
      ?.addEventListener("click", () => submitReview("rejected"));
    document
      .getElementById("bulkApproveBtn")
      ?.addEventListener("click", () => bulkApprove());
    document
      .getElementById("selectAllApproval")
      ?.addEventListener("change", (e) => {
        document
          .querySelectorAll(".approval-select")
          .forEach((cb) => (cb.checked = e.target.checked));
      });
    document
      .getElementById("teacherFilterApproval")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("subjectFilterApproval")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("dateFromApproval")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("dateToApproval")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("approvalStatusFilter")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("exportApprovalBtn")
      ?.addEventListener("click", () => {
        window.open(
          (window.APP_BASE || "") + "/api/?route=academic/lesson-plans/approval/export&format=csv",
          "_blank",
        );
      });
  }

  async function init() {
    attachListeners();
    await loadReferenceData();
    await loadData();
  }

  return { init, refresh: loadData, loadPage: loadData, review };
})();

document.addEventListener("DOMContentLoaded", () =>
  LessonPlanApprovalController.init(),
);
