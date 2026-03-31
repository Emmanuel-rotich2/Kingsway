/**
 * Finance Approvals Controller
 * Handles approval workflows for:
 * - Expenses
 * - Department budget proposals
 * - Payroll/payment approvals
 */

const FinanceApprovalsController = (() => {
  const state = {
    allRecords: [],
    filteredRecords: [],
    page: 1,
    limit: 12,
    selectedUid: null,
  };

  let searchDebounce = null;

  function getCurrentUserId() {
    try {
      const user = AuthContext.getUser?.() || {};
      return Number(user.id || user.user_id || 0) || null;
    } catch (error) {
      return null;
    }
  }

  function esc(value) {
    return value == null
      ? ""
      : String(value)
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#39;");
  }

  function toDateString(value) {
    if (!value) return "";
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
      return String(value).slice(0, 10);
    }
    return parsed.toISOString().slice(0, 10);
  }

  function formatDate(value) {
    if (!value) return "—";
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
      return esc(String(value));
    }
    return parsed.toLocaleDateString();
  }

  function formatAmount(value) {
    const num = Number(value || 0);
    return Number.isFinite(num) ? num.toLocaleString("en-KE", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "0.00";
  }

  function showNotice(message, type = "info") {
    if (window.API?.showNotification) {
      window.API.showNotification(message, type);
      return;
    }
    window.alert(type === "error" ? `Error: ${message}` : message);
  }

  function normalizeStatus(status) {
    const s = String(status || "").toLowerCase();
    if (["approved", "paid", "completed", "active"].includes(s)) return "approved";
    if (["rejected", "declined", "cancelled", "failed"].includes(s)) return "rejected";
    return "pending";
  }

  function normalizeTypeLabel(type) {
    if (type === "expense") return "Expense";
    if (type === "budget") return "Budget";
    if (type === "payment") return "Payment";
    return "Finance";
  }

  function buildRecord(type, id, date, requestedBy, description, amount, status, raw = {}) {
    const numericId = Number(id) || id;
    return {
      uid: `${type}-${id}`,
      type,
      id: numericId,
      ref: `${type.toUpperCase().slice(0, 3)}-${id}`,
      date,
      requested_by: requestedBy || "—",
      description: description || "—",
      amount: Number(amount || 0),
      status: normalizeStatus(status),
      raw,
    };
  }

  async function fetchExpenses(filters) {
    try {
      const params = {
        type: "expenses",
        page: 1,
        limit: 500,
      };
      if (filters.status) params.status = filters.status;
      if (filters.search) params.search = filters.search;
      if (filters.date_from) params.date_from = filters.date_from;
      if (filters.date_to) params.date_to = filters.date_to;

      const payload = await window.API.apiCall("/finance", "GET", null, params);
      const rows = Array.isArray(payload?.expenses)
        ? payload.expenses
        : Array.isArray(payload?.data?.expenses)
        ? payload.data.expenses
        : [];

      return rows.map((row) =>
        buildRecord(
          "expense",
          row.id,
          row.expense_date || row.created_at || row.updated_at,
          row.recorded_by_name || row.recorded_by || row.created_by || "—",
          row.description || row.vendor_name || row.expense_category,
          row.amount,
          row.status,
          row
        )
      );
    } catch (error) {
      console.warn("[FinanceApprovals] Expenses load failed:", error?.message || error);
      return [];
    }
  }

  async function fetchBudgetProposals(filters) {
    try {
      const params = {};
      if (filters.status) params.status = filters.status;

      const payload = await window.API.apiCall(
        "/finance/department-budgets/proposals",
        "GET",
        null,
        params
      );

      const rows = Array.isArray(payload)
        ? payload
        : Array.isArray(payload?.proposals)
        ? payload.proposals
        : [];

      return rows.map((row) =>
        buildRecord(
          "budget",
          row.id,
          row.created_at || row.requested_at || row.reviewed_at,
          row.created_by || "—",
          row.title || row.description || "Department budget proposal",
          row.amount_requested || row.amount || 0,
          row.status,
          row
        )
      );
    } catch (error) {
      console.warn("[FinanceApprovals] Budget proposals load failed:", error?.message || error);
      return [];
    }
  }

  async function fetchPayrollApprovals() {
    try {
      const payload = await window.API.apiCall("/finance/payrolls/list", "GET", null, {
        page: 1,
        limit: 500,
      });

      const rows = Array.isArray(payload?.payrolls)
        ? payload.payrolls
        : Array.isArray(payload?.data?.payrolls)
        ? payload.data.payrolls
        : [];

      return rows.map((row) =>
        buildRecord(
          "payment",
          row.id,
          row.created_at || row.updated_at || row.payroll_period,
          row.staff_name || row.staff_id || "Payroll",
          `Payroll ${row.payroll_period || ""}`.trim(),
          row.net_salary || row.gross_salary || row.total_amount || 0,
          row.status,
          row
        )
      );
    } catch (error) {
      console.warn("[FinanceApprovals] Payroll approvals load failed:", error?.message || error);
      return [];
    }
  }

  function getFilters() {
    return {
      search: (document.getElementById("searchBox")?.value || "").trim(),
      status: document.getElementById("statusFilter")?.value || "",
      type: document.getElementById("typeFilter")?.value || "",
      date_from: document.getElementById("dateFrom")?.value || "",
      date_to: document.getElementById("dateTo")?.value || "",
    };
  }

  function applyFilters() {
    const filters = getFilters();
    const search = filters.search.toLowerCase();

    state.filteredRecords = state.allRecords.filter((record) => {
      if (filters.type && record.type !== filters.type) return false;
      if (filters.status && record.status !== filters.status) return false;

      const recordDate = toDateString(record.date);
      if (filters.date_from && recordDate && recordDate < filters.date_from) return false;
      if (filters.date_to && recordDate && recordDate > filters.date_to) return false;

      if (search) {
        const haystack = [
          record.ref,
          record.description,
          record.requested_by,
          normalizeTypeLabel(record.type),
        ]
          .join(" ")
          .toLowerCase();
        if (!haystack.includes(search)) return false;
      }

      return true;
    });

    state.page = 1;
    renderAll();
  }

  function renderStatusBadge(status) {
    if (status === "approved") return '<span class="badge bg-success">Approved</span>';
    if (status === "rejected") return '<span class="badge bg-danger">Rejected</span>';
    return '<span class="badge bg-warning text-dark">Pending</span>';
  }

  function renderActions(record) {
    const view = `<button class="btn btn-sm btn-outline-primary me-1" onclick="FinanceApprovalsController.viewRequest('${record.uid}')"><i class="bi bi-eye"></i></button>`;
    if (record.status !== "pending") {
      return `${view}<span class="text-muted small">—</span>`;
    }
    const approve = `<button class="btn btn-sm btn-outline-success me-1" onclick="FinanceApprovalsController.quickApprove('${record.uid}')"><i class="bi bi-check"></i></button>`;
    const reject = `<button class="btn btn-sm btn-outline-danger" onclick="FinanceApprovalsController.quickReject('${record.uid}')"><i class="bi bi-x"></i></button>`;
    return `${view}${approve}${reject}`;
  }

  function paginate(records) {
    const total = records.length;
    const pages = Math.max(1, Math.ceil(total / state.limit));
    if (state.page > pages) state.page = pages;
    const start = (state.page - 1) * state.limit;
    const end = start + state.limit;
    return {
      pageRecords: records.slice(start, end),
      total,
      pages,
    };
  }

  function renderTable() {
    const tbody = document.querySelector("#approvalsTable tbody");
    if (!tbody) return;

    const { pageRecords, total, pages } = paginate(state.filteredRecords);

    if (!pageRecords.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No approval requests found for the selected filters.</td></tr>';
      renderPagination(1);
      return;
    }

    tbody.innerHTML = pageRecords
      .map(
        (record) => `
          <tr>
            <td><code>${esc(record.ref)}</code></td>
            <td>${esc(normalizeTypeLabel(record.type))}</td>
            <td>${formatDate(record.date)}</td>
            <td>${esc(record.requested_by)}</td>
            <td>${esc(record.description)}</td>
            <td class="text-end">${formatAmount(record.amount)}</td>
            <td>${renderStatusBadge(record.status)}</td>
            <td>${renderActions(record)}</td>
          </tr>
        `
      )
      .join("");

    renderPagination(pages);

    const pagination = document.getElementById("pagination");
    if (pagination) {
      pagination.dataset.total = String(total);
    }
  }

  function renderPagination(totalPages) {
    const container = document.getElementById("pagination");
    if (!container) return;

    if (totalPages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = "";
    const prevDisabled = state.page <= 1 ? " disabled" : "";
    const nextDisabled = state.page >= totalPages ? " disabled" : "";

    html += `<li class="page-item${prevDisabled}"><a class="page-link" href="#" data-page="${Math.max(1, state.page - 1)}">Prev</a></li>`;

    const start = Math.max(1, state.page - 2);
    const end = Math.min(totalPages, state.page + 2);
    for (let p = start; p <= end; p += 1) {
      html += `<li class="page-item${p === state.page ? " active" : ""}"><a class="page-link" href="#" data-page="${p}">${p}</a></li>`;
    }

    html += `<li class="page-item${nextDisabled}"><a class="page-link" href="#" data-page="${Math.min(totalPages, state.page + 1)}">Next</a></li>`;
    container.innerHTML = html;
  }

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = String(value);
  }

  function updateSummaryCards() {
    const rows = state.filteredRecords;
    const today = new Date().toISOString().slice(0, 10);
    const monthPrefix = today.slice(0, 7);

    const pending = rows.filter((r) => r.status === "pending");
    const approvedToday = rows.filter((r) => r.status === "approved" && toDateString(r.date) === today);
    const rejectedToday = rows.filter((r) => r.status === "rejected" && toDateString(r.date) === today);
    const thisMonth = rows.filter((r) => toDateString(r.date).startsWith(monthPrefix));

    const sum = (list) => list.reduce((acc, row) => acc + Number(row.amount || 0), 0);

    setText("pendingCount", pending.length);
    setText("pendingAmount", formatAmount(sum(pending)));
    setText("approvedCount", approvedToday.length);
    setText("approvedAmount", formatAmount(sum(approvedToday)));
    setText("rejectedCount", rejectedToday.length);
    setText("rejectedAmount", formatAmount(sum(rejectedToday)));
    setText("totalCount", thisMonth.length);
    setText("totalAmount", formatAmount(sum(thisMonth)));
  }

  function renderAll() {
    renderTable();
    updateSummaryCards();
  }

  function findRecord(uid) {
    return state.allRecords.find((r) => r.uid === uid) || null;
  }

  function openModal(uid) {
    const record = findRecord(uid);
    if (!record) return;

    state.selectedUid = uid;

    setText("requestId", record.ref);
    setText("requestType", normalizeTypeLabel(record.type));
    setText("requestedBy", record.requested_by);
    setText("requestDate", formatDate(record.date));
    setText("requestAmount", `KES ${formatAmount(record.amount)}`);

    const requestStatus = document.getElementById("requestStatus");
    if (requestStatus) requestStatus.innerHTML = renderStatusBadge(record.status);

    setText("requestDescription", record.description || "—");
    setText(
      "requestCategory",
      record.raw.expense_category || record.raw.category || record.raw.department_name || record.raw.payroll_period || "—"
    );

    const attachmentSection = document.getElementById("attachmentsSection");
    const attachmentsList = document.getElementById("attachmentsList");
    if (attachmentSection && attachmentsList) {
      attachmentsList.innerHTML = "";
      const file = record.raw.attachment_url || record.raw.receipt_file || record.raw.document_url;
      if (file) {
        attachmentsList.innerHTML = `<li><a href="${esc(file)}" target="_blank" rel="noopener">View attachment</a></li>`;
        attachmentSection.style.display = "";
      } else {
        attachmentSection.style.display = "none";
      }
    }

    const comments = document.getElementById("approvalComments");
    if (comments) comments.value = "";

    const approveBtn = document.getElementById("approveBtn");
    const rejectBtn = document.getElementById("rejectBtn");
    const disabled = record.status !== "pending";
    if (approveBtn) approveBtn.disabled = disabled;
    if (rejectBtn) rejectBtn.disabled = disabled;

    const modalEl = document.getElementById("approvalModal");
    if (modalEl) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  }

  async function submitDecision(decision) {
    const record = findRecord(state.selectedUid);
    if (!record) return;
    if (record.status !== "pending") {
      showNotice("Only pending requests can be actioned.", "warning");
      return;
    }

    const comments = (document.getElementById("approvalComments")?.value || "").trim();
    if (!comments) {
      showNotice("Please add comments/remarks before submitting.", "warning");
      return;
    }

    const userId = getCurrentUserId();

    try {
      if (record.type === "expense") {
        if (decision === "approve") {
          await window.API.apiCall("/finance/expenses/approve", "POST", {
            expense_id: record.id,
            notes: comments,
          });
        } else {
          await window.API.apiCall("/finance/expenses/reject", "POST", {
            expense_id: record.id,
            reason: comments,
          });
        }
      } else if (record.type === "budget") {
        await window.API.apiCall("/finance/department-budgets/approve", "POST", {
          proposal_id: record.id,
          status: decision === "approve" ? "approved" : "rejected",
          reviewed_by: userId,
        });
      } else if (record.type === "payment") {
        if (!userId) {
          throw new Error("Unable to resolve current user ID for payroll approval.");
        }
        if (decision === "approve") {
          await window.API.apiCall("/finance/payrolls/approve", "POST", {
            payroll_id: record.id,
            user_id: userId,
            comments,
          });
        } else {
          await window.API.apiCall("/finance/payrolls/reject", "POST", {
            payroll_id: record.id,
            user_id: userId,
            reason: comments,
          });
        }
      } else {
        throw new Error("Unsupported approval type.");
      }

      showNotice(
        `${normalizeTypeLabel(record.type)} ${record.ref} ${decision === "approve" ? "approved" : "rejected"} successfully.`,
        "success"
      );

      const modalEl = document.getElementById("approvalModal");
      if (modalEl) {
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      }

      await loadData();
    } catch (error) {
      showNotice(error?.message || `Failed to ${decision} request.`, "error");
    }
  }

  function exportCsv() {
    if (!state.filteredRecords.length) {
      showNotice("No records available to export.", "warning");
      return;
    }

    const rows = [
      ["Reference", "Type", "Date", "Requested By", "Description", "Amount", "Status"],
      ...state.filteredRecords.map((r) => [
        r.ref,
        normalizeTypeLabel(r.type),
        toDateString(r.date),
        r.requested_by,
        r.description,
        Number(r.amount || 0).toFixed(2),
        r.status,
      ]),
    ];

    const csv = rows
      .map((row) => row.map((col) => `"${String(col).replace(/"/g, '""')}"`).join(","))
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `finance_approvals_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  async function loadData() {
    const tbody = document.querySelector("#approvalsTable tbody");
    if (tbody) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading approvals...</td></tr>';
    }

    const filters = getFilters();
    const [expenses, budgets, payrolls] = await Promise.all([
      fetchExpenses(filters),
      fetchBudgetProposals(filters),
      fetchPayrollApprovals(filters),
    ]);

    state.allRecords = [...expenses, ...budgets, ...payrolls].sort((a, b) => {
      const ad = new Date(a.date || 0).getTime();
      const bd = new Date(b.date || 0).getTime();
      return bd - ad;
    });

    applyFilters();
  }

  function handlePaginationClick(event) {
    const link = event.target.closest("a[data-page]");
    if (!link) return;
    event.preventDefault();
    const nextPage = Number(link.dataset.page || 1);
    if (!Number.isFinite(nextPage) || nextPage < 1) return;
    state.page = nextPage;
    renderTable();
  }

  function bindEvents() {
    const search = document.getElementById("searchBox");
    search?.addEventListener("input", () => {
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => applyFilters(), 250);
    });

    ["statusFilter", "typeFilter", "dateFrom", "dateTo"].forEach((id) => {
      document.getElementById(id)?.addEventListener("change", applyFilters);
    });

    document.getElementById("pagination")?.addEventListener("click", handlePaginationClick);
    document.getElementById("exportBtn")?.addEventListener("click", exportCsv);
    document.getElementById("approveBtn")?.addEventListener("click", () => submitDecision("approve"));
    document.getElementById("rejectBtn")?.addEventListener("click", () => submitDecision("reject"));
  }

  async function init() {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    bindEvents();
    await loadData();
  }

  async function quickApprove(uid) {
    openModal(uid);
    const comments = document.getElementById("approvalComments");
    if (comments && !comments.value.trim()) {
      comments.value = "Approved after review.";
    }
    await submitDecision("approve");
  }

  async function quickReject(uid) {
    openModal(uid);
    const comments = document.getElementById("approvalComments");
    if (comments && !comments.value.trim()) {
      comments.value = "Rejected after review.";
    }
    await submitDecision("reject");
  }

  return {
    init,
    loadData,
    viewRequest: openModal,
    quickApprove,
    quickReject,
  };
})();

document.addEventListener("DOMContentLoaded", () => FinanceApprovalsController.init());
window.FinanceApprovalsController = FinanceApprovalsController;
