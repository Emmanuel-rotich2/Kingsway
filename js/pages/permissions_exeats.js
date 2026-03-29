const PermissionsExeatsController = (() => {
  const state = {
    requests: [],
    allRequests: [],
    students: [],
    permissionTypes: [],
    pagination: {
      page: 1,
      limit: 10,
      total: 0,
    },
  };

  const filters = {
    status: "",
    permission_type_id: "",
    search: "",
    date_from: "",
    date_to: "",
  };

  let searchTimeout = null;

  function notify(message, type = "info") {
    if (window.API?.showNotification) {
      window.API.showNotification(message, type);
      return;
    }
    console[type === "error" ? "error" : "log"](message);
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, "&#96;");
  }

  function hasAnyPermission(permissions = []) {
    if (!window.AuthContext) {
      return false;
    }
    return window.AuthContext.hasAnyPermission(permissions);
  }

  function statusBadgeClass(status) {
    const map = {
      pending: "warning",
      approved: "success",
      rejected: "danger",
      cancelled: "secondary",
      completed: "primary",
    };
    return map[status] || "secondary";
  }

  function formatStatus(status) {
    const map = {
      pending: "Pending",
      approved: "Approved",
      rejected: "Rejected",
      cancelled: "Cancelled",
      completed: "Completed",
    };
    return map[status] || status || "-";
  }

  function formatDate(value) {
    if (!value) {
      return "-";
    }
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
      return String(value);
    }
    return date.toLocaleDateString("en-KE", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  }

  function toDateInputValue(date) {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
      .toISOString()
      .split("T")[0];
  }

  function computeSummary(rows) {
    return {
      total: rows.length,
      pending: rows.filter((row) => row.status === "pending").length,
      approved: rows.filter((row) => row.status === "approved").length,
      rejected: rows.filter((row) => row.status === "rejected").length,
    };
  }

  function getStudentById(studentId) {
    return state.students.find((student) => String(student.id) === String(studentId));
  }

  function isBoarderStudent(student) {
    const code = String(student?.student_type_code || "").toUpperCase();
    const label = String(student?.student_type || "").toUpperCase();
    return code.includes("BOARD") || label.includes("BOARD");
  }

  function getFilteredPermissionTypes(studentId = null) {
    const student = studentId ? getStudentById(studentId) : null;
    const boarder = student ? isBoarderStudent(student) : null;

    return state.permissionTypes.filter((type) => {
      if (!student) {
        return true;
      }
      if (type.applies_to === "boarders_only") {
        return boarder === true;
      }
      if (type.applies_to === "day_only") {
        return boarder === false;
      }
      return true;
    });
  }

  function renderSummary() {
    const summary = computeSummary(state.allRequests || []);
    const setText = (id, value) => {
      const element = document.getElementById(id);
      if (element) {
        element.textContent = value;
      }
    };
    setText("totalRequests", summary.total);
    setText("pendingRequests", summary.pending);
    setText("approvedRequests", summary.approved);
    setText("deniedRequests", summary.rejected);
  }

  function renderPagination() {
    const container = document.getElementById("pagination");
    if (!container) {
      return;
    }

    const totalPages = Math.max(1, Math.ceil(state.pagination.total / state.pagination.limit));
    const currentPage = state.pagination.page;
    let html = "";

    for (let page = 1; page <= totalPages; page++) {
      html += `
        <li class="page-item ${page === currentPage ? "active" : ""}">
          <a class="page-link" href="#" data-page="${page}">${page}</a>
        </li>
      `;
    }

    container.innerHTML = html;
    container.querySelectorAll("[data-page]").forEach((link) => {
      link.addEventListener("click", (event) => {
        event.preventDefault();
        loadData(Number(link.dataset.page));
      });
    });
  }

  function renderTable() {
    const tbody = document.querySelector("#requestsTable tbody");
    if (!tbody) {
      return;
    }

    if (!state.requests.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center text-muted py-4">No permission requests found for the selected filters.</td></tr>';
      return;
    }

    const canReview = hasAnyPermission([
      "attendance_boarding_approve",
      "attendance_boarding_approve_final",
    ]);
    const canEdit = hasAnyPermission([
      "attendance_boarding_edit",
      "attendance_boarding_create",
      "attendance_boarding_submit",
    ]);

    tbody.innerHTML = state.requests
      .map((request) => {
        const classDisplay = [request.class_name, request.stream_name]
          .filter(Boolean)
          .join(" - ");
        const dates = `${formatDate(request.start_date)} to ${formatDate(request.end_date)}`;
        const requestedMeta = [
          request.requested_at ? formatDate(request.requested_at.split(" ")[0]) : null,
          Number(request.requested_by_parent || 0) === 1 ? "Parent request" : "School request",
        ]
          .filter(Boolean)
          .join(" • ");

        const actions = [];
        if (canReview && request.status === "pending") {
          actions.push(`
            <button class="btn btn-outline-success btn-sm review-request-btn"
                    data-request-id="${request.id}"
                    title="Review request">
              <i class="bi bi-check2-circle"></i>
            </button>
          `);
        }
        if (canEdit && request.status === "pending") {
          actions.push(`
            <button class="btn btn-outline-primary btn-sm edit-request-btn"
                    data-request-id="${request.id}"
                    title="Edit request">
              <i class="bi bi-pencil"></i>
            </button>
          `);
          actions.push(`
            <button class="btn btn-outline-danger btn-sm cancel-request-btn"
                    data-request-id="${request.id}"
                    title="Cancel request">
              <i class="bi bi-x-circle"></i>
            </button>
          `);
        }

        return `
          <tr>
            <td>
              <div class="fw-semibold">${escapeHtml(request.student_name || "-")}</div>
              <div class="text-muted small">${escapeHtml(request.admission_no || "-")}</div>
            </td>
            <td>${escapeHtml(classDisplay || "-")}</td>
            <td>
              <span class="badge bg-info text-dark">${escapeHtml(request.permission_type_name || request.permission_type_code || "-")}</span>
            </td>
            <td>${escapeHtml(dates)}</td>
            <td>${escapeHtml(request.reason || "-")}</td>
            <td>${escapeHtml(requestedMeta || "-")}</td>
            <td><span class="badge bg-${statusBadgeClass(request.status)}">${formatStatus(request.status)}</span></td>
            <td>
              <div class="btn-group btn-group-sm">
                ${actions.length ? actions.join("") : '<span class="text-muted small">No actions</span>'}
              </div>
            </td>
          </tr>
        `;
      })
      .join("");

    tbody.querySelectorAll(".edit-request-btn").forEach((button) => {
      button.addEventListener("click", () => openRequestModal(button.dataset.requestId));
    });
    tbody.querySelectorAll(".review-request-btn").forEach((button) => {
      button.addEventListener("click", () => reviewRequest(button.dataset.requestId));
    });
    tbody.querySelectorAll(".cancel-request-btn").forEach((button) => {
      button.addEventListener("click", () => cancelRequest(button.dataset.requestId));
    });
  }

  function populateStudentDropdown() {
    const select = document.getElementById("requestStudent");
    if (!select) {
      return;
    }

    const previousValue = select.value;
    select.innerHTML = '<option value="">Select Student</option>';
    state.students.forEach((student) => {
      const option = document.createElement("option");
      option.value = student.id;
      option.textContent = `${student.admission_no || ""} - ${student.first_name || ""} ${student.last_name || ""}`.trim();
      select.appendChild(option);
    });
    select.value = previousValue;
  }

  function populatePermissionTypeFilters() {
    const filter = document.getElementById("typeFilter");
    if (!filter) {
      return;
    }

    const previousValue = filter.value;
    filter.innerHTML = '<option value="">All Types</option>';
    state.permissionTypes.forEach((type) => {
      const option = document.createElement("option");
      option.value = type.id;
      option.textContent = type.name;
      filter.appendChild(option);
    });
    filter.value = previousValue;
  }

  function populateRequestTypeDropdown(studentId = null, selectedTypeId = "") {
    const select = document.getElementById("requestPermissionType");
    if (!select) {
      return;
    }

    select.innerHTML = '<option value="">Select Permission Type</option>';
    getFilteredPermissionTypes(studentId).forEach((type) => {
      const option = document.createElement("option");
      option.value = type.id;
      option.textContent = type.name;
      option.dataset.code = type.code;
      option.dataset.appliesTo = type.applies_to;
      select.appendChild(option);
    });

    if (selectedTypeId) {
      select.value = String(selectedTypeId);
    }
  }

  async function loadReferenceData() {
    try {
      const [studentsResponse, permissionTypesResponse] = await Promise.all([
        window.API.students.getAll({ limit: 500, status: "active" }),
        window.API.attendance.getPermissionTypes(),
      ]);

      state.students = Array.isArray(studentsResponse?.data)
        ? studentsResponse.data
        : [];
      state.permissionTypes = Array.isArray(permissionTypesResponse)
        ? permissionTypesResponse
        : [];

      populateStudentDropdown();
      populatePermissionTypeFilters();
      populateRequestTypeDropdown();
    } catch (error) {
      notify(error.message || "Failed to load permission workflow reference data", "error");
    }
  }

  async function loadData(page = 1) {
    try {
      const response = await window.API.attendance.getPermissions({
        status: filters.status || undefined,
        permission_type_id: filters.permission_type_id || undefined,
        search: filters.search || undefined,
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
      });

      const requests = Array.isArray(response) ? response : [];
      state.pagination.total = requests.length;
      const totalPages = Math.max(1, Math.ceil(requests.length / state.pagination.limit));
      state.pagination.page = Math.min(page, totalPages);
      state.allRequests = requests;

      const start = (state.pagination.page - 1) * state.pagination.limit;
      const end = start + state.pagination.limit;
      state.requests = requests.slice(start, end);

      renderSummary();
      renderTable();
      renderPagination();
    } catch (error) {
      notify(error.message || "Failed to load permission requests", "error");
      state.requests = [];
      state.allRequests = [];
      state.pagination.total = 0;
      renderSummary();
      renderTable();
      renderPagination();
    }
  }

  function resetRequestForm() {
    const form = document.getElementById("requestForm");
    if (form) {
      form.reset();
    }
    document.getElementById("requestId").value = "";
    document.getElementById("requestModalTitle").textContent = "New Permission / Exeat Request";
    populateRequestTypeDropdown();
  }

  function openRequestModal(requestId = null) {
    const modalEl = document.getElementById("requestModal");
    if (!modalEl) {
      return;
    }

    resetRequestForm();

    if (requestId) {
      const record = (state.allRequests || []).find((request) => String(request.id) === String(requestId));
      if (record) {
        document.getElementById("requestId").value = record.id;
        document.getElementById("requestModalTitle").textContent = "Edit Permission Request";
        document.getElementById("requestStudent").value = record.student_id || "";
        populateRequestTypeDropdown(record.student_id, record.permission_type_id);
        document.getElementById("requestPermissionType").value = String(record.permission_type_id || "");
        document.getElementById("startDate").value = record.start_date || "";
        document.getElementById("endDate").value = record.end_date || "";
        document.getElementById("startTime").value = record.start_time || "";
        document.getElementById("endTime").value = record.end_time || "";
        document.getElementById("expectedReturn").value = record.expected_return
          ? String(record.expected_return).replace(" ", "T").slice(0, 16)
          : "";
        document.getElementById("requestedByParent").checked = Number(record.requested_by_parent || 0) === 1;
        document.getElementById("requestReason").value = record.reason || "";
        document.getElementById("requestNotes").value = record.notes || "";
      }
    }

    window.bootstrap?.Modal.getOrCreateInstance(modalEl).show();
  }

  async function saveRequest() {
    const requestId = document.getElementById("requestId").value;
    const payload = {
      student_id: document.getElementById("requestStudent").value,
      permission_type_id: document.getElementById("requestPermissionType").value,
      start_date: document.getElementById("startDate").value,
      start_time: document.getElementById("startTime").value || null,
      end_date: document.getElementById("endDate").value,
      end_time: document.getElementById("endTime").value || null,
      expected_return: document.getElementById("expectedReturn").value || null,
      requested_by_parent: document.getElementById("requestedByParent").checked,
      reason: document.getElementById("requestReason").value.trim(),
      notes: document.getElementById("requestNotes").value.trim() || null,
    };

    if (!payload.student_id || !payload.permission_type_id || !payload.start_date || !payload.end_date || !payload.reason) {
      notify("Please fill in all required fields.", "warning");
      return;
    }

    try {
      if (requestId) {
        await window.API.attendance.updatePermission(requestId, payload);
        notify("Permission request updated successfully.", "success");
      } else {
        await window.API.attendance.createPermission(payload);
        notify("Permission request submitted successfully.", "success");
      }

      window.bootstrap?.Modal.getInstance(document.getElementById("requestModal"))?.hide();
      await loadData(state.pagination.page);
    } catch (error) {
      notify(error.message || "Failed to save permission request.", "error");
    }
  }

  function reviewRequest(requestId) {
    const record = (state.allRequests || []).find((request) => String(request.id) === String(requestId));
    if (!record) {
      return;
    }

    document.getElementById("approvalRequestId").value = record.id;
    document.getElementById("approvalStudent").textContent = record.student_name || "-";
    document.getElementById("approvalType").textContent = record.permission_type_name || record.permission_type_code || "-";
    document.getElementById("approvalReason").textContent = record.reason || "-";
    document.getElementById("approvalDates").textContent = `${formatDate(record.start_date)} to ${formatDate(record.end_date)}`;
    document.getElementById("approvalDecision").value = "";
    document.getElementById("approvalComments").value = "";

    window.bootstrap?.Modal.getOrCreateInstance(document.getElementById("approvalModal")).show();
  }

  async function submitApproval() {
    const requestId = document.getElementById("approvalRequestId").value;
    const decision = document.getElementById("approvalDecision").value;
    const comments = document.getElementById("approvalComments").value.trim();

    if (!requestId || !decision) {
      notify("Please select a decision before submitting.", "warning");
      return;
    }

    try {
      await window.API.attendance.updatePermission(requestId, {
        status: decision,
        rejection_reason: decision === "rejected" ? comments : null,
        notes: comments || null,
      });

      notify(`Request ${formatStatus(decision).toLowerCase()} successfully.`, "success");
      window.bootstrap?.Modal.getInstance(document.getElementById("approvalModal"))?.hide();
      await loadData(state.pagination.page);
    } catch (error) {
      notify(error.message || "Failed to submit decision.", "error");
    }
  }

  async function cancelRequest(requestId) {
    if (!window.confirm("Cancel this permission request?")) {
      return;
    }

    try {
      await window.API.attendance.updatePermission(requestId, { status: "cancelled" });
      notify("Permission request cancelled.", "success");
      await loadData(state.pagination.page);
    } catch (error) {
      notify(error.message || "Failed to cancel request.", "error");
    }
  }

  function exportRequests() {
    const rows = state.allRequests || [];
    if (!rows.length) {
      notify("There is no data to export.", "warning");
      return;
    }

    const header = [
      "Student",
      "Admission No",
      "Class",
      "Permission Type",
      "Start Date",
      "End Date",
      "Requested At",
      "Requested By Parent",
      "Status",
      "Reason",
    ];

    const csvRows = [header.join(",")];
    rows.forEach((request) => {
      const values = [
        request.student_name || "",
        request.admission_no || "",
        [request.class_name, request.stream_name].filter(Boolean).join(" - "),
        request.permission_type_name || request.permission_type_code || "",
        request.start_date || "",
        request.end_date || "",
        request.requested_at || "",
        Number(request.requested_by_parent || 0) === 1 ? "Yes" : "No",
        request.status || "",
        request.reason || "",
      ].map((value) => `"${String(value).replace(/"/g, '""')}"`);

      csvRows.push(values.join(","));
    });

    const blob = new Blob([csvRows.join("\n")], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "permissions_exeats.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  function attachEventListeners() {
    document.getElementById("newRequestBtn")?.addEventListener("click", () => openRequestModal());
    document.getElementById("saveRequestBtn")?.addEventListener("click", () => saveRequest());
    document.getElementById("submitApprovalBtn")?.addEventListener("click", () => submitApproval());
    document.getElementById("exportRequestsBtn")?.addEventListener("click", () => exportRequests());

    document.getElementById("statusFilter")?.addEventListener("change", (event) => {
      filters.status = event.target.value;
      loadData(1);
    });

    document.getElementById("typeFilter")?.addEventListener("change", (event) => {
      filters.permission_type_id = event.target.value;
      loadData(1);
    });

    document.getElementById("searchBox")?.addEventListener("input", (event) => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        filters.search = event.target.value.trim();
        loadData(1);
      }, 250);
    });

    document.getElementById("dateFrom")?.addEventListener("change", (event) => {
      filters.date_from = event.target.value;
      loadData(1);
    });

    document.getElementById("dateTo")?.addEventListener("change", (event) => {
      filters.date_to = event.target.value;
      loadData(1);
    });

    document.getElementById("requestStudent")?.addEventListener("change", (event) => {
      const selectedTypeId = document.getElementById("requestPermissionType")?.value || "";
      populateRequestTypeDropdown(event.target.value, selectedTypeId);
    });
  }

  async function init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    const today = new Date();
    const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const dateFrom = document.getElementById("dateFrom");
    const dateTo = document.getElementById("dateTo");
    if (dateFrom) {
      dateFrom.value = toDateInputValue(firstOfMonth);
      filters.date_from = dateFrom.value;
    }
    if (dateTo) {
      dateTo.value = toDateInputValue(today);
      filters.date_to = dateTo.value;
    }

    attachEventListeners();
    await loadReferenceData();
    await loadData(1);
  }

  return {
    init,
    refresh: () => loadData(state.pagination.page),
    loadPage: loadData,
    editRequest: openRequestModal,
    reviewRequest,
    cancelRequest,
  };
})();

document.addEventListener("DOMContentLoaded", () => PermissionsExeatsController.init());
window.PermissionsExeatsController = PermissionsExeatsController;
