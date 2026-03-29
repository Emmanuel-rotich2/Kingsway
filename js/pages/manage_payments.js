/**
 * Manage Payments Controller
 * Real payments workflow: list, filter, record, export
 */

const managePaymentsController = {
  state: {
    payments: [],
    students: [],
    pagination: { page: 1, limit: 20, total: 0, pages: 0 },
    summary: {
      total_amount: 0,
      pending_amount: 0,
      confirmed_amount: 0,
      today_amount: 0,
      outstanding_amount: 0,
    },
  },

  searchTimer: null,
  paymentModal: null,
  isSaving: false,

  init: async function () {
    if (!window.AuthContext?.isAuthenticated?.()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    this.paymentModal = new bootstrap.Modal(document.getElementById("paymentModal"));
    this.attachEventListeners();
    this.setDefaultDates();
    this.setTableLoadingState();

    await Promise.all([this.loadStudents(), this.loadPayments(1)]);
  },

  attachEventListeners: function () {
    document
      .getElementById("recordPaymentBtn")
      ?.addEventListener("click", () => this.openPaymentModal());

    document
      .getElementById("savePaymentBtn")
      ?.addEventListener("click", () => this.savePayment());

    document
      .getElementById("exportPaymentsBtn")
      ?.addEventListener("click", () => this.exportCurrentPayments());

    document.getElementById("clearFilters")?.addEventListener("click", () => {
      this.clearFilters();
      this.loadPayments(1);
    });

    ["paymentStatusFilter", "paymentMethodFilter", "dateFrom", "dateTo"].forEach(
      (id) => {
        document.getElementById(id)?.addEventListener("change", () => this.loadPayments(1));
      },
    );

    document.getElementById("paymentSearch")?.addEventListener("input", () => {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.loadPayments(1), 350);
    });

    document.getElementById("paymentForm")?.addEventListener("submit", (event) => {
      event.preventDefault();
      this.savePayment();
    });
  },

  setDefaultDates: function () {
    const today = new Date();
    const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

    const dateTo = document.getElementById("dateTo");
    const dateFrom = document.getElementById("dateFrom");

    if (dateTo && !dateTo.value) {
      dateTo.value = this.formatDateForInput(today);
    }
    if (dateFrom && !dateFrom.value) {
      dateFrom.value = this.formatDateForInput(firstOfMonth);
    }

    const paymentDate = document.getElementById("payment_date");
    if (paymentDate && !paymentDate.value) {
      paymentDate.value = this.formatDateForInput(today);
    }
  },

  formatDateForInput: function (date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  },

  loadStudents: async function () {
    try {
      const response = await window.API.students.list({ limit: 500, status: "active" });
      const payload = this.unwrapPayload(response);
      const students = Array.isArray(payload?.students)
        ? payload.students
        : Array.isArray(payload)
          ? payload
          : [];

      this.state.students = students;
      this.renderStudentsDropdown();
    } catch (error) {
      console.error("Failed to load students for payments:", error);
      this.showError("Unable to load student list for payment recording.");
    }
  },

  renderStudentsDropdown: function () {
    const select = document.getElementById("student_id");
    if (!select) return;

    const previous = select.value;
    select.innerHTML = '<option value="">Select Student</option>';

    this.state.students.forEach((student) => {
      const option = document.createElement("option");
      option.value = student.id;
      const fullName = student.full_name
        || `${student.first_name || ""} ${student.last_name || ""}`.trim();
      option.textContent = `${student.admission_no || "N/A"} - ${fullName}`;
      select.appendChild(option);
    });

    if (previous) {
      select.value = previous;
    }
  },

  getListParams: function (page = 1) {
    const params = {
      type: "payments",
      page,
      limit: this.state.pagination.limit,
    };

    const search = document.getElementById("paymentSearch")?.value?.trim();
    const status = document.getElementById("paymentStatusFilter")?.value;
    const method = document.getElementById("paymentMethodFilter")?.value;
    const dateFrom = document.getElementById("dateFrom")?.value;
    const dateTo = document.getElementById("dateTo")?.value;

    if (search) params.search = search;
    if (status) params.status = status;
    if (method) params.payment_method = method;
    if (dateFrom) params.date_from = `${dateFrom} 00:00:00`;
    if (dateTo) params.date_to = `${dateTo} 23:59:59`;

    return params;
  },

  loadPayments: async function (page = 1) {
    this.setTableLoadingState();

    try {
      const params = this.getListParams(page);
      const response = await window.API.apiCall("/finance", "GET", null, params);
      const payload = this.unwrapPayload(response);

      this.state.payments = Array.isArray(payload?.payments) ? payload.payments : [];
      this.state.pagination = {
        page: Number(payload?.pagination?.page || page),
        limit: Number(payload?.pagination?.limit || this.state.pagination.limit),
        total: Number(payload?.pagination?.total || 0),
        pages: Number(payload?.pagination?.pages || 0),
      };
      this.state.summary = {
        ...this.state.summary,
        ...(payload?.summary || {}),
      };

      await this.loadOutstandingSummary();
      this.renderPaymentsTable();
      this.renderPagination();
      this.renderSummaryCards();
    } catch (error) {
      console.error("Failed to load payments:", error);
      this.renderPaymentsError(error?.message || "Unable to load payments.");
    }
  },

  loadOutstandingSummary: async function () {
    try {
      const statusResponse = await window.API.finance.getStudentPaymentStatusList({
        page: 1,
        limit: 1,
      });
      const payload = this.unwrapPayload(statusResponse);
      const summary = payload?.summary || {};
      this.state.summary.outstanding_amount = Number(summary.total_balance || 0);
    } catch (error) {
      this.state.summary.outstanding_amount = 0;
    }
  },

  setTableLoadingState: function () {
    const tbody = document.querySelector("#paymentsTable tbody");
    if (!tbody) return;

    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-4 text-muted">
          <span class="spinner-border spinner-border-sm me-2" role="status"></span>
          Loading payments...
        </td>
      </tr>
    `;
  },

  renderPaymentsError: function (message) {
    const tbody = document.querySelector("#paymentsTable tbody");
    if (!tbody) return;

    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-4 text-danger">
          <i class="bi bi-exclamation-triangle me-1"></i>
          ${this.escapeHtml(message)}
        </td>
      </tr>
    `;
  },

  renderPaymentsTable: function () {
    const tbody = document.querySelector("#paymentsTable tbody");
    if (!tbody) return;

    if (!this.state.payments.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-4 text-muted">
            No payment records found for the selected filters.
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = this.state.payments
      .map((payment) => {
        const amount = Number(payment.amount || 0);
        const status = String(payment.status || "pending").toLowerCase();
        const statusClass =
          status === "confirmed"
            ? "success"
            : status === "pending"
              ? "warning"
              : status === "failed"
                ? "danger"
                : status === "reversed"
                  ? "secondary"
                  : "info";

        return `
          <tr>
            <td>${this.formatDateTime(payment.transaction_date)}</td>
            <td>${this.escapeHtml(payment.receipt_no || payment.transaction_ref || "-")}</td>
            <td>
              <div class="fw-semibold">${this.escapeHtml(payment.student_name || "-")}</div>
              <small class="text-muted">${this.escapeHtml(payment.admission_no || payment.student_no || "-")}</small>
            </td>
            <td class="text-end">KES ${amount.toLocaleString()}</td>
            <td>${this.escapeHtml(this.formatMethod(payment.payment_method))}</td>
            <td><span class="badge bg-${statusClass}">${this.escapeHtml(status)}</span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary" onclick="managePaymentsController.viewPayment(${Number(payment.id)})">
                <i class="bi bi-eye"></i>
              </button>
            </td>
          </tr>
        `;
      })
      .join("");
  },

  renderPagination: function () {
    const container = document.getElementById("paymentsPagination");
    if (!container) return;

    const { page, pages } = this.state.pagination;
    if (!pages || pages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = "";

    const addPageItem = (label, targetPage, disabled = false, active = false) => {
      html += `
        <li class="page-item ${disabled ? "disabled" : ""} ${active ? "active" : ""}">
          <a class="page-link" href="#" onclick="return managePaymentsController.goToPage(${targetPage}, ${disabled})">${label}</a>
        </li>
      `;
    };

    addPageItem("«", page - 1, page <= 1);

    const start = Math.max(1, page - 2);
    const end = Math.min(pages, page + 2);
    for (let i = start; i <= end; i++) {
      addPageItem(String(i), i, false, i === page);
    }

    addPageItem("»", page + 1, page >= pages);

    container.innerHTML = html;
  },

  goToPage: function (page, disabled) {
    if (disabled) return false;
    this.loadPayments(page);
    return false;
  },

  renderSummaryCards: function () {
    const summary = this.state.summary || {};
    const setText = (id, value) => {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    };

    setText("totalReceived", `KES ${Number(summary.confirmed_amount || 0).toLocaleString()}`);
    setText("pendingPayments", `KES ${Number(summary.pending_amount || 0).toLocaleString()}`);
    setText("outstandingPayments", `KES ${Number(summary.outstanding_amount || 0).toLocaleString()}`);
    setText("todayCollections", `KES ${Number(summary.today_amount || 0).toLocaleString()}`);
  },

  openPaymentModal: function () {
    document.getElementById("paymentForm")?.reset();
    document.getElementById("payment_id").value = "";
    document.getElementById("payment_date").value = this.formatDateForInput(new Date());
    this.paymentModal.show();
  },

  savePayment: async function () {
    if (this.isSaving) return;

    const studentId = Number(document.getElementById("student_id")?.value || 0);
    const paymentDate = document.getElementById("payment_date")?.value;
    const amount = Number(document.getElementById("payment_amount")?.value || 0);
    const method = document.getElementById("payment_method")?.value;
    const reference = document.getElementById("transaction_reference")?.value?.trim() || null;
    const notes = document.getElementById("payment_notes")?.value?.trim() || null;

    if (!studentId || !paymentDate || amount <= 0 || !method) {
      this.showError("Student, payment date, amount, and payment method are required.");
      return;
    }

    this.isSaving = true;
    const saveBtn = document.getElementById("savePaymentBtn");
    const originalBtnHtml = saveBtn?.innerHTML;
    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    }

    try {
      const user = window.AuthContext?.getUser?.() || {};

      await window.API.apiCall("/finance", "POST", {
        type: "payment",
        student_id: studentId,
        amount,
        payment_method: method,
        reference_no: reference,
        payment_date: `${paymentDate} 00:00:00`,
        notes,
        received_by: user.id || user.user_id || 1,
      });

      this.paymentModal.hide();
      this.showSuccess("Payment recorded successfully.");
      await this.loadPayments(this.state.pagination.page || 1);
    } catch (error) {
      console.error("Failed to save payment:", error);
      this.showError(error?.message || "Failed to save payment.");
    } finally {
      this.isSaving = false;
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml || "Save Payment";
      }
    }
  },

  viewPayment: async function (paymentId) {
    try {
      const details = await window.API.apiCall(`/finance/${paymentId}`, "GET", null, {
        type: "payment",
      });
      const payload = this.unwrapPayload(details) || {};

      this.showInfo(
        `Receipt: ${payload.receipt_no || "-"} | Student: ${payload.student_name || "-"} | Amount: KES ${Number(payload.amount_paid || payload.amount || 0).toLocaleString()}`,
      );
    } catch (error) {
      this.showError("Unable to fetch payment details.");
    }
  },

  exportCurrentPayments: function () {
    if (!this.state.payments.length) {
      this.showInfo("No payment data to export.");
      return;
    }

    const header = [
      "Date",
      "Receipt",
      "Admission No",
      "Student",
      "Amount",
      "Method",
      "Status",
      "Reference",
    ];

    const rows = this.state.payments.map((payment) => [
      this.formatDateTime(payment.transaction_date),
      payment.receipt_no || "",
      payment.admission_no || payment.student_no || "",
      payment.student_name || "",
      Number(payment.amount || 0),
      this.formatMethod(payment.payment_method),
      payment.status || "",
      payment.transaction_ref || "",
    ]);

    const csv = [header, ...rows]
      .map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(","))
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `payments_${this.formatDateForInput(new Date())}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  },

  clearFilters: function () {
    [
      "paymentSearch",
      "paymentStatusFilter",
      "paymentMethodFilter",
      "dateFrom",
      "dateTo",
    ].forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.value = "";
    });
    this.setDefaultDates();
  },

  formatMethod: function (method) {
    const normalized = String(method || "").toLowerCase();
    if (normalized === "mpesa") return "M-Pesa";
    if (normalized === "bank_transfer") return "Bank Transfer";
    if (normalized === "cheque") return "Cheque";
    if (normalized === "cash") return "Cash";
    return normalized || "-";
  },

  formatDateTime: function (value) {
    if (!value) return "-";
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return this.escapeHtml(String(value));
    return dt.toLocaleString("en-KE", {
      year: "numeric",
      month: "short",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
  },

  unwrapPayload: function (response) {
    if (!response) return null;
    if (response.data !== undefined) return response.data;
    return response;
  },

  escapeHtml: function (value) {
    return String(value || "").replace(/[&<>'"]/g, (ch) => {
      const map = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "'": "&#39;",
        '"': "&quot;",
      };
      return map[ch] || ch;
    });
  },

  showSuccess: function (message) {
    if (window.API?.showNotification) {
      window.API.showNotification(message, "success");
    }
  },

  showError: function (message) {
    if (window.API?.showNotification) {
      window.API.showNotification(message, "error");
    }
  },

  showInfo: function (message) {
    if (window.API?.showNotification) {
      window.API.showNotification(message, "info");
    }
  },
};

window.managePaymentsController = managePaymentsController;
document.addEventListener("DOMContentLoaded", () => managePaymentsController.init());
