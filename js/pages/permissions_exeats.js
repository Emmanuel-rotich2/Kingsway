/**
 * Permissions & Exeats Page Controller
 * Manages permission/exeat request workflow using api.js
 */

const PermissionsExeatsController = (() => {
  // Private state
  const state = {
    requests: [],
    students: [],
    pagination: { page: 1, limit: 10, total: 0 },
    summary: { total: 0, pending: 0, approved: 0, denied: 0 },
  };

  const filters = {
    status: "",
    search: "",
    date_from: "",
    date_to: "",
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
      pending: "warning",
      approved: "success",
      denied: "danger",
      returned: "info",
      overdue: "danger",
    };
    return map[status] || "secondary";
  }

  function formatStatus(status) {
    const map = {
      pending: "Pending",
      approved: "Approved",
      denied: "Denied",
      returned: "Returned",
      overdue: "Overdue",
    };
    return map[status] || status || "-";
  }

  function typeBadge(type) {
    return type === "exeat" ? "primary" : "info";
  }

  function formatType(type) {
    const map = {
      permission: "Permission",
      exeat: "Exeat",
    };
    return map[type] || type || "-";
  }

  // ---- Data Loading ----

  async function loadReferenceData() {
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

  function populateStudentDropdown() {
    const select = document.getElementById("requestStudent");
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

      if (filters.status) params.append("status", filters.status);
      if (filters.search) params.append("search", filters.search);
      if (filters.date_from) params.append("date_from", filters.date_from);
      if (filters.date_to) params.append("date_to", filters.date_to);

      const resp = await window.API.apiCall(
        `/students/permissions-exeats?${params.toString()}`,
        "GET",
      );

      const payload = unwrapPayload(resp) || {};
      state.requests = payload.requests || payload.data || [];
      if (!Array.isArray(state.requests)) state.requests = [];

      state.pagination = payload.pagination || state.pagination;
      state.summary = payload.summary || computeSummary(state.requests);

      renderSummary();
      renderTable();
      renderPagination();
    } catch (error) {
      console.error("Error loading permissions/exeats:", error);
      showError("Failed to load permission/exeat requests");
    }
  }

  function computeSummary(requests) {
    return {
      total: requests.length,
      pending: requests.filter((r) => r.status === "pending").length,
      approved: requests.filter((r) => r.status === "approved").length,
      denied: requests.filter((r) => r.status === "denied").length,
    };
  }

  // ---- Rendering ----

  function renderSummary() {
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalRequests", state.summary.total || 0);
    el("pendingRequests", state.summary.pending || 0);
    el("approvedRequests", state.summary.approved || 0);
    el("deniedRequests", state.summary.denied || 0);
  }

  function renderTable() {
    const tbody = document.querySelector("#requestsTable tbody");
    if (!tbody) return;

    if (!state.requests.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center text-muted py-4">No permission/exeat requests found</td>
        </tr>`;
      return;
    }

    tbody.innerHTML = state.requests
      .map((r) => {
        const studentName =
          `${r.first_name || ""} ${r.last_name || ""}`.trim() ||
          r.student_name ||
          "-";
        const className = r.class_name || "-";
        const type = r.request_type || r.type || "-";
        const reason = r.reason || "-";
        const requestedDate = r.departure_date || r.requested_date || "-";
        const returnDate = r.return_date || r.expected_return_date || "-";
        const status = r.status || "pending";

        return `
          <tr>
            <td>${escapeHtml(studentName)}</td>
            <td>${escapeHtml(className)}</td>
            <td><span class="badge bg-${typeBadge(type)}">${formatType(type)}</span></td>
            <td>${escapeHtml(reason.length > 50 ? reason.substring(0, 50) + "..." : reason)}</td>
            <td>${escapeHtml(requestedDate)}</td>
            <td>${escapeHtml(returnDate)}</td>
            <td><span class="badge bg-${statusBadge(status)}">${formatStatus(status)}</span></td>
            <td>
              <div class="btn-group btn-group-sm">
                ${
                  status === "pending"
                    ? `
                  <button class="btn btn-outline-success" onclick="PermissionsExeatsController.reviewRequest(${r.id})" title="Review">
                    <i class="bi bi-check-circle"></i>
                  </button>
                `
                    : ""
                }
                <button class="btn btn-outline-primary" onclick="PermissionsExeatsController.editRequest(${r.id})" title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="PermissionsExeatsController.deleteRequest(${r.id})" title="Delete">
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
          <a class="page-link" href="#" onclick="PermissionsExeatsController.loadPage(${i}); return false;">${i}</a>
        </li>`;
    }
    container.innerHTML = html;
  }

  // ---- CRUD Actions ----

  function openRequestModal(requestId = null) {
    const modalEl = document.getElementById("requestModal");
    const form = document.getElementById("requestForm");
    if (!modalEl || !form) return;

    form.reset();
    document.getElementById("requestId").value = "";
    document.getElementById("requestModalTitle").textContent =
      "New Permission / Exeat Request";

    if (requestId) {
      const record = state.requests.find((r) => r.id == requestId);
      if (record) {
        document.getElementById("requestId").value = record.id;
        document.getElementById("requestModalTitle").textContent =
          "Edit Request";
        document.getElementById("requestStudent").value =
          record.student_id || "";
        document.getElementById("requestType").value =
          record.request_type || record.type || "";
        document.getElementById("departureDate").value =
          record.departure_date || record.requested_date || "";
        document.getElementById("returnDate").value =
          record.return_date || record.expected_return_date || "";
        document.getElementById("requestReason").value = record.reason || "";
        document.getElementById("guardianContact").value =
          record.guardian_contact || "";
        document.getElementById("destination").value = record.destination || "";
        document.getElementById("requestNotes").value = record.notes || "";
      }
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  async function saveRequest() {
    const requestId = document.getElementById("requestId").value;

    const payload = {
      student_id: document.getElementById("requestStudent").value,
      request_type: document.getElementById("requestType").value,
      departure_date: document.getElementById("departureDate").value,
      return_date: document.getElementById("returnDate").value,
      reason: document.getElementById("requestReason").value,
      guardian_contact: document.getElementById("guardianContact").value,
      destination: document.getElementById("destination").value,
      notes: document.getElementById("requestNotes").value,
    };

    if (
      !payload.student_id ||
      !payload.request_type ||
      !payload.departure_date ||
      !payload.reason
    ) {
      showError("Please fill in all required fields");
      return;
    }

    try {
      if (requestId) {
        await window.API.apiCall(
          `/students/permissions-exeats/${requestId}`,
          "PUT",
          payload,
        );
        showSuccess("Request updated successfully");
      } else {
        await window.API.apiCall(
          "/students/permissions-exeats",
          "POST",
          payload,
        );
        showSuccess("Request submitted successfully");
      }

      bootstrap.Modal.getInstance(
        document.getElementById("requestModal"),
      ).hide();
      await loadData(state.pagination.page);
    } catch (error) {
      console.error("Error saving request:", error);
      showError(error.message || "Failed to save request");
    }
  }

  function reviewRequest(requestId) {
    const record = state.requests.find((r) => r.id == requestId);
    if (!record) return;

    const modalEl = document.getElementById("approvalModal");
    if (!modalEl) return;

    const studentName =
      `${record.first_name || ""} ${record.last_name || ""}`.trim() ||
      record.student_name ||
      "-";

    document.getElementById("approvalRequestId").value = record.id;
    document.getElementById("approvalStudent").textContent = studentName;
    document.getElementById("approvalType").textContent = formatType(
      record.request_type || record.type,
    );
    document.getElementById("approvalReason").textContent =
      record.reason || "-";
    document.getElementById("approvalDates").textContent =
      `${record.departure_date || "-"} to ${record.return_date || "-"}`;
    document.getElementById("approvalDecision").value = "";
    document.getElementById("approvalComments").value = "";

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  async function submitApproval() {
    const requestId = document.getElementById("approvalRequestId").value;
    const decision = document.getElementById("approvalDecision").value;
    const comments = document.getElementById("approvalComments").value;

    if (!decision) {
      showError("Please select a decision");
      return;
    }

    try {
      await window.API.apiCall(
        `/students/permissions-exeats/${requestId}/review`,
        "PUT",
        { status: decision, comments },
      );
      showSuccess(`Request ${decision} successfully`);
      bootstrap.Modal.getInstance(
        document.getElementById("approvalModal"),
      ).hide();
      await loadData(state.pagination.page);
    } catch (error) {
      console.error("Error submitting approval:", error);
      showError(error.message || "Failed to submit decision");
    }
  }

  async function deleteRequest(requestId) {
    if (!confirm("Are you sure you want to delete this request?")) return;

    try {
      await window.API.apiCall(
        `/students/permissions-exeats/${requestId}`,
        "DELETE",
      );
      showSuccess("Request deleted successfully");
      await loadData(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to delete request");
    }
  }

  function exportRequests() {
    if (!state.requests.length) {
      showError("No data to export");
      return;
    }

    const rows = [
      "Student,Class,Type,Reason,Departure Date,Return Date,Status",
    ];
    state.requests.forEach((r) => {
      const name =
        `${r.first_name || ""} ${r.last_name || ""}`.trim() ||
        r.student_name ||
        "";
      rows.push(
        `"${name}","${r.class_name || ""}","${r.request_type || r.type || ""}","${r.reason || ""}","${r.departure_date || ""}","${r.return_date || ""}","${r.status || ""}"`,
      );
    });

    const blob = new Blob([rows.join("\n")], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "permissions_exeats.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  // ---- Event Listeners ----

  function attachEventListeners() {
    document
      .getElementById("newRequestBtn")
      ?.addEventListener("click", () => openRequestModal());

    document
      .getElementById("saveRequestBtn")
      ?.addEventListener("click", () => saveRequest());

    document
      .getElementById("submitApprovalBtn")
      ?.addEventListener("click", () => submitApproval());

    document
      .getElementById("exportRequestsBtn")
      ?.addEventListener("click", () => exportRequests());

    // Filters
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

    document.getElementById("dateFrom")?.addEventListener("change", (e) => {
      filters.date_from = e.target.value;
      loadData(1);
    });

    document.getElementById("dateTo")?.addEventListener("change", (e) => {
      filters.date_to = e.target.value;
      loadData(1);
    });
  }

  // ---- Initialization ----

  async function init() {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
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
    editRequest: openRequestModal,
    reviewRequest,
    deleteRequest,
  };
})();

document.addEventListener("DOMContentLoaded", () =>
  PermissionsExeatsController.init(),
);

window.PermissionsExeatsController = PermissionsExeatsController;
