/**
 * Special Needs Page Controller
 * Manages special education records and IEP workflow using api.js
 */

const SpecialNeedsController = (() => {
  // Private state
  const state = {
    records: [],
    students: [],
    classes: [],
    pagination: { page: 1, limit: 10, total: 0 },
    summary: { total: 0, with_iep: 0, under_review: 0, support_active: 0 },
    currentViewId: null,
  };

  const filters = {
    class_id: "",
    category: "",
    status: "",
    search: "",
  };

  let searchTimeout = null;

  // ---- Helpers ----

  function unwrapPayload(response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined)
      return response.data.data;
    return response;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function showSuccess(message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "success");
    } else {
      alert(message);
    }
  }

  function showError(message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "error");
    } else {
      alert("Error: " + message);
    }
  }

  function statusBadge(status) {
    const map = {
      active: "success",
      under_review: "warning",
      pending: "info",
      graduated: "secondary",
    };
    return map[status] || "secondary";
  }

  function formatStatus(status) {
    const map = {
      active: "Active IEP",
      under_review: "Under Review",
      pending: "Pending Assessment",
      graduated: "Graduated/Exited",
    };
    return map[status] || status || "-";
  }

  function formatCategory(category) {
    const map = {
      learning_disability: "Learning Disability",
      physical_disability: "Physical Disability",
      visual_impairment: "Visual Impairment",
      hearing_impairment: "Hearing Impairment",
      speech_disorder: "Speech Disorder",
      autism: "Autism Spectrum",
      adhd: "ADHD",
      emotional_behavioral: "Emotional/Behavioral",
      gifted: "Gifted & Talented",
      other: "Other",
    };
    return map[category] || category || "-";
  }

  // ---- Data Loading ----

  async function loadReferenceData() {
    try {
      const classResp = await window.API.academic.listClasses();
      const classPayload = unwrapPayload(classResp);
      state.classes = Array.isArray(classPayload) ? classPayload : [];
      populateClassFilter();
    } catch (error) {
      console.warn("Failed to load classes", error);
    }

    try {
      const studentResp = await window.API.apiCall(
        "/students?limit=500",
        "GET",
      );
      const payload = unwrapPayload(studentResp);
      const students = payload?.students || payload || [];
      state.students = Array.isArray(students) ? students : [];
      populateStudentDropdown();
    } catch (error) {
      console.warn("Failed to load students", error);
    }
  }

  function populateClassFilter() {
    const select = document.getElementById("classFilter");
    if (!select) return;

    state.classes.forEach((cls) => {
      const opt = document.createElement("option");
      opt.value = cls.id;
      opt.textContent = cls.name || cls.class_name;
      select.appendChild(opt);
    });
  }

  function populateStudentDropdown() {
    const select = document.getElementById("recordStudent");
    if (!select) return;

    state.students.forEach((student) => {
      const opt = document.createElement("option");
      opt.value = student.id;
      opt.textContent =
        `${student.admission_no || ""} - ${student.first_name || ""} ${student.last_name || ""}`.trim();
      select.appendChild(opt);
    });
  }

  async function loadData(page = 1) {
    try {
      state.pagination.page = page;

      const params = new URLSearchParams({
        page,
        limit: state.pagination.limit,
      });

      if (filters.class_id) params.append("class_id", filters.class_id);
      if (filters.category) params.append("category", filters.category);
      if (filters.status) params.append("status", filters.status);
      if (filters.search) params.append("search", filters.search);

      const resp = await window.API.apiCall(
        `/students/special-needs?${params.toString()}`,
        "GET",
      );

      const payload = unwrapPayload(resp) || {};
      state.records = payload.records || payload.data || [];
      if (!Array.isArray(state.records)) state.records = [];

      state.pagination = payload.pagination || state.pagination;
      state.summary = payload.summary || computeSummary(state.records);

      renderSummary();
      renderTable();
      renderPagination();
    } catch (error) {
      console.error("Error loading special needs records:", error);
      showError("Failed to load special needs records");
    }
  }

  function computeSummary(records) {
    return {
      total: records.length,
      with_iep: records.filter((r) => r.iep_status === "active").length,
      under_review: records.filter((r) => r.iep_status === "under_review")
        .length,
      support_active: records.filter(
        (r) => r.iep_status === "active" || r.iep_status === "under_review",
      ).length,
    };
  }

  // ---- Rendering ----

  function renderSummary() {
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalSNStudents", state.summary.total || 0);
    el("withIEP", state.summary.with_iep || 0);
    el("underReview", state.summary.under_review || 0);
    el("supportActive", state.summary.support_active || 0);
  }

  function renderTable() {
    const tbody = document.querySelector("#specialNeedsTable tbody");
    if (!tbody) return;

    if (!state.records.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center text-muted py-4">No special needs records found</td>
        </tr>`;
      return;
    }

    tbody.innerHTML = state.records
      .map((r) => {
        const studentName =
          `${r.first_name || ""} ${r.last_name || ""}`.trim() ||
          r.student_name ||
          "-";
        const className = r.class_name || "-";
        const category = formatCategory(r.category);
        const iepStatus = r.iep_status || "pending";
        const supportPlan = r.support_plan || "-";
        const lastReview = r.last_review_date || r.updated_at || "-";

        return `
          <tr>
            <td>${escapeHtml(studentName)}</td>
            <td>${escapeHtml(className)}</td>
            <td>${escapeHtml(category)}</td>
            <td><span class="badge bg-${statusBadge(iepStatus)}">${formatStatus(iepStatus)}</span></td>
            <td>${escapeHtml(supportPlan.length > 60 ? supportPlan.substring(0, 60) + "..." : supportPlan)}</td>
            <td>${escapeHtml(lastReview)}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-info" onclick="SpecialNeedsController.viewRecord(${r.id})" title="View">
                  <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-outline-primary" onclick="SpecialNeedsController.editRecord(${r.id})" title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="SpecialNeedsController.deleteRecord(${r.id})" title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>`;
      })
      .join("");
  }

  function renderPagination() {
    const container = document.getElementById("pagination");
    if (!container) return;

    const { page, total, limit } = state.pagination;
    const totalPages = Math.ceil(total / limit) || 1;

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `
        <li class="page-item ${i === page ? "active" : ""}">
          <a class="page-link" href="#" onclick="SpecialNeedsController.loadPage(${i}); return false;">${i}</a>
        </li>`;
    }
    container.innerHTML = html;
  }

  // ---- CRUD Actions ----

  function openRecordModal(recordId = null) {
    const modalEl = document.getElementById("snRecordModal");
    const form = document.getElementById("snRecordForm");
    if (!modalEl || !form) return;

    form.reset();
    document.getElementById("recordId").value = "";
    document.getElementById("snRecordModalTitle").textContent =
      "Add Special Needs Record";

    if (recordId) {
      const record = state.records.find((r) => r.id == recordId);
      if (record) {
        document.getElementById("recordId").value = record.id;
        document.getElementById("snRecordModalTitle").textContent =
          "Edit Special Needs Record";
        document.getElementById("recordStudent").value =
          record.student_id || "";
        document.getElementById("recordCategory").value = record.category || "";
        document.getElementById("recordDiagnosis").value =
          record.diagnosis || "";
        document.getElementById("recordIEPStatus").value =
          record.iep_status || "pending";
        document.getElementById("recordReviewDate").value =
          record.next_review_date || "";
        document.getElementById("recordSupportPlan").value =
          record.support_plan || "";
        document.getElementById("recordGoals").value = record.goals || "";
        document.getElementById("recordParentNotes").value =
          record.parent_notes || "";
      }
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  async function saveRecord() {
    const recordId = document.getElementById("recordId").value;

    const payload = {
      student_id: document.getElementById("recordStudent").value,
      category: document.getElementById("recordCategory").value,
      diagnosis: document.getElementById("recordDiagnosis").value,
      iep_status: document.getElementById("recordIEPStatus").value,
      next_review_date: document.getElementById("recordReviewDate").value,
      support_plan: document.getElementById("recordSupportPlan").value,
      goals: document.getElementById("recordGoals").value,
      parent_notes: document.getElementById("recordParentNotes").value,
    };

    if (
      !payload.student_id ||
      !payload.category ||
      !payload.diagnosis ||
      !payload.support_plan
    ) {
      showError("Please fill in all required fields");
      return;
    }

    try {
      if (recordId) {
        await window.API.apiCall(
          `/students/special-needs/${recordId}`,
          "PUT",
          payload,
        );
        showSuccess("Record updated successfully");
      } else {
        await window.API.apiCall("/students/special-needs", "POST", payload);
        showSuccess("Record created successfully");
      }

      bootstrap.Modal.getInstance(
        document.getElementById("snRecordModal"),
      ).hide();
      await loadData(state.pagination.page);
    } catch (error) {
      console.error("Error saving record:", error);
      showError(error.message || "Failed to save record");
    }
  }

  function viewRecord(recordId) {
    const record = state.records.find((r) => r.id == recordId);
    if (!record) return;

    state.currentViewId = recordId;

    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };

    const studentName =
      `${record.first_name || ""} ${record.last_name || ""}`.trim() ||
      record.student_name ||
      "-";

    el("viewSNStudent", studentName);
    el("viewSNClass", record.class_name || "-");
    el("viewSNCategory", formatCategory(record.category));

    const iepStatusEl = document.getElementById("viewSNIEPStatus");
    if (iepStatusEl) {
      iepStatusEl.innerHTML = `<span class="badge bg-${statusBadge(record.iep_status)}">${formatStatus(record.iep_status)}</span>`;
    }

    el("viewSNLastReview", record.last_review_date || record.updated_at || "-");
    el("viewSNNextReview", record.next_review_date || "-");
    el("viewSNDiagnosis", record.diagnosis || "No diagnosis recorded");
    el("viewSNSupportPlan", record.support_plan || "No support plan");
    el("viewSNGoals", record.goals || "No goals set");

    const modal = new bootstrap.Modal(
      document.getElementById("viewRecordModal"),
    );
    modal.show();
  }

  async function deleteRecord(recordId) {
    if (!confirm("Are you sure you want to delete this special needs record?"))
      return;

    try {
      await window.API.apiCall(`/students/special-needs/${recordId}`, "DELETE");
      showSuccess("Record deleted successfully");
      await loadData(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to delete record");
    }
  }

  function exportRecords() {
    if (!state.records.length) {
      showError("No data to export");
      return;
    }

    const rows = ["Student,Class,Category,IEP Status,Support Plan,Last Review"];
    state.records.forEach((r) => {
      const name =
        `${r.first_name || ""} ${r.last_name || ""}`.trim() ||
        r.student_name ||
        "";
      rows.push(
        `"${name}","${r.class_name || ""}","${formatCategory(r.category)}","${formatStatus(r.iep_status)}","${(r.support_plan || "").replace(/"/g, '""')}","${r.last_review_date || r.updated_at || ""}"`,
      );
    });

    const blob = new Blob([rows.join("\n")], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "special_needs_records.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  // ---- Event Listeners ----

  function attachEventListeners() {
    document
      .getElementById("addRecordBtn")
      ?.addEventListener("click", () => openRecordModal());

    document
      .getElementById("saveRecordBtn")
      ?.addEventListener("click", () => saveRecord());

    document
      .getElementById("exportRecordsBtn")
      ?.addEventListener("click", () => exportRecords());

    document
      .getElementById("editFromViewBtn")
      ?.addEventListener("click", () => {
        bootstrap.Modal.getInstance(
          document.getElementById("viewRecordModal"),
        ).hide();
        if (state.currentViewId) openRecordModal(state.currentViewId);
      });

    document
      .getElementById("printRecordBtn")
      ?.addEventListener("click", () => window.print());

    // Filters
    document.getElementById("classFilter")?.addEventListener("change", (e) => {
      filters.class_id = e.target.value;
      loadData(1);
    });

    document
      .getElementById("categoryFilter")
      ?.addEventListener("change", (e) => {
        filters.category = e.target.value;
        loadData(1);
      });

    document.getElementById("statusFilter")?.addEventListener("change", (e) => {
      filters.status = e.target.value;
      loadData(1);
    });

    document.getElementById("searchBox")?.addEventListener("keyup", (e) => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        filters.search = e.target.value.trim();
        loadData(1);
      }, 300);
    });
  }

  // ---- Initialization ----

  async function init() {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    attachEventListeners();
    await loadReferenceData();
    await loadData();
  }

  // ---- Public API ----
  return {
    init,
    refresh: loadData,
    loadPage: loadData,
    viewRecord,
    editRecord: openRecordModal,
    deleteRecord,
  };
})();

document.addEventListener("DOMContentLoaded", () =>
  SpecialNeedsController.init(),
);

window.SpecialNeedsController = SpecialNeedsController;
