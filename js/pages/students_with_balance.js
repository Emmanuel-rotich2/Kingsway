/**
 * Students with Balance Page Controller
 * Manages display and actions for students with outstanding fee balances
 */

const StudentsWithBalanceController = {
  data: {
    students: [],
    classes: [],
    summary: {},
    pagination: { page: 1, limit: 20, total: 0 },
  },
  filters: {
    search: "",
    class_id: "",
    amount_range: "",
  },

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.attachEventListeners();
    await this.loadClasses();
    await this.loadData();
  },

  attachEventListeners: function () {
    const searchEl = document.getElementById("searchStudent");
    if (searchEl) {
      searchEl.addEventListener("keyup", () => {
        clearTimeout(this._searchTimer);
        this._searchTimer = setTimeout(() => {
          this.filters.search = searchEl.value.trim();
          this.loadData(1);
        }, 300);
      });
    }

    const classFilter = document.getElementById("filterClass");
    if (classFilter) {
      classFilter.addEventListener("change", () => {
        this.filters.class_id = classFilter.value;
        this.loadData(1);
      });
    }

    const amountFilter = document.getElementById("filterAmount");
    if (amountFilter) {
      amountFilter.addEventListener("change", () => {
        this.filters.amount_range = amountFilter.value;
        this.loadData(1);
      });
    }

    document.getElementById("resetFilters")?.addEventListener("click", () => {
      this.filters = { search: "", class_id: "", amount_range: "" };
      if (searchEl) searchEl.value = "";
      if (classFilter) classFilter.value = "";
      if (amountFilter) amountFilter.value = "";
      this.loadData(1);
    });

    document.getElementById("sendReminders")?.addEventListener("click", () => this.sendReminders());
    document.getElementById("exportBalances")?.addEventListener("click", () => this.exportCSV());

    document.getElementById("selectAll")?.addEventListener("change", (e) => {
      document.querySelectorAll(".student-select").forEach((cb) => {
        cb.checked = e.target.checked;
      });
    });
  },

  loadClasses: async function () {
    try {
      const resp = await window.API.academic.listClasses();
      const payload = this.unwrapPayload(resp);
      this.data.classes = Array.isArray(payload) ? payload : [];
      this.populateClassFilter();
    } catch (error) {
      console.warn("Failed to load classes:", error);
    }
  },

  populateClassFilter: function () {
    const select = document.getElementById("filterClass");
    if (!select) return;
    const firstOpt = select.options[0];
    select.innerHTML = "";
    select.appendChild(firstOpt);

    this.data.classes.forEach((cls) => {
      const opt = document.createElement("option");
      opt.value = cls.id;
      opt.textContent = cls.name || cls.class_name;
      select.appendChild(opt);
    });
  },

  loadData: async function (page = 1) {
    this.data.pagination.page = page;

    try {
      // Load payment status from finance API
      const params = {
        page,
        limit: this.data.pagination.limit,
        balance_only: 1,
      };

      if (this.filters.search) params.search = this.filters.search;
      if (this.filters.class_id) params.class_id = this.filters.class_id;
      if (this.filters.amount_range) params.amount_range = this.filters.amount_range;

      const resp = await window.API.finance.getPaymentStatus(params);
      const payload = this.unwrapPayload(resp);

      if (payload) {
        this.data.students = payload.students || payload.data || (Array.isArray(payload) ? payload : []);
        this.data.pagination = payload.pagination || this.data.pagination;
        this.data.summary = payload.summary || {};
      }

      // Also try loading outstanding fees summary
      try {
        const summaryResp = await window.API.finance.getOutstandingFees();
        const summaryPayload = this.unwrapPayload(summaryResp);
        if (summaryPayload) {
          this.data.summary = {
            ...this.data.summary,
            total_outstanding: summaryPayload.total_outstanding || summaryPayload.total || 0,
            students_count: summaryPayload.students_count || this.data.students.length,
            avg_balance: summaryPayload.avg_balance || 0,
            cleared_today: summaryPayload.cleared_today || 0,
          };
        }
      } catch (e) {
        // Summary is optional, use fallback
      }

      this.renderSummary();
      this.renderTable();
      this.renderPagination();
    } catch (error) {
      console.error("Error loading students with balance:", error);
      this.showError("Failed to load student balances");
    }
  },

  renderSummary: function () {
    const s = this.data.summary;
    const total = s.total_outstanding || 0;
    const count = s.students_count || this.data.students.length;
    const avg = count > 0 ? (s.avg_balance || total / count) : 0;
    const cleared = s.cleared_today || 0;

    const totalEl = document.getElementById("totalBalance");
    if (totalEl) totalEl.textContent = this.formatCurrency(total);

    const countEl = document.getElementById("studentsWithBalance");
    if (countEl) countEl.textContent = count;

    const avgEl = document.getElementById("avgBalance");
    if (avgEl) avgEl.textContent = this.formatCurrency(avg);

    const clearedEl = document.getElementById("clearedToday");
    if (clearedEl) clearedEl.textContent = cleared;
  },

  renderTable: function () {
    const tbody = document.querySelector("#balancesTable tbody");
    if (!tbody) return;

    if (!this.data.students.length) {
      tbody.innerHTML = `
        <tr><td colspan="9" class="text-center text-muted py-4">
          <i class="fas fa-check-circle me-2"></i>No students with outstanding balances found
        </td></tr>`;
      return;
    }

    tbody.innerHTML = this.data.students
      .map((s) => {
        const name = `${s.first_name || ""} ${s.last_name || ""}`.trim();
        const className = s.class_name || s.stream_name || "-";
        const totalFees = parseFloat(s.total_fees || s.expected || 0);
        const paid = parseFloat(s.total_paid || s.paid || 0);
        const balance = parseFloat(s.balance || s.outstanding || totalFees - paid);
        const lastPayment = s.last_payment_date || s.last_payment || "-";

        const balanceClass = balance > 20000 ? "text-danger fw-bold" : balance > 5000 ? "text-warning" : "text-dark";

        return `
          <tr>
            <td><input type="checkbox" class="student-select" data-id="${s.id || s.student_id}"></td>
            <td>${s.admission_no || "-"}</td>
            <td>${this.escapeHtml(name)}</td>
            <td>${this.escapeHtml(className)}</td>
            <td>${this.formatCurrency(totalFees)}</td>
            <td>${this.formatCurrency(paid)}</td>
            <td class="${balanceClass}">${this.formatCurrency(balance)}</td>
            <td>${lastPayment !== "-" ? new Date(lastPayment).toLocaleDateString() : "-"}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-info" onclick="StudentsWithBalanceController.viewStatement(${s.id || s.student_id})" title="View Statement">
                  <i class="fas fa-file-invoice"></i>
                </button>
                <button class="btn btn-outline-primary" onclick="StudentsWithBalanceController.recordPayment(${s.id || s.student_id})" title="Record Payment">
                  <i class="fas fa-money-bill"></i>
                </button>
                <button class="btn btn-outline-warning" onclick="StudentsWithBalanceController.sendReminder(${s.id || s.student_id})" title="Send Reminder">
                  <i class="fas fa-bell"></i>
                </button>
              </div>
            </td>
          </tr>`;
      })
      .join("");
  },

  renderPagination: function () {
    // Add pagination below the table
    const card = document.querySelector("#balancesTable")?.closest(".card-body");
    if (!card) return;

    let pagEl = document.getElementById("balancePagination");
    if (!pagEl) {
      pagEl = document.createElement("nav");
      pagEl.id = "balancePagination";
      pagEl.className = "mt-3";
      pagEl.innerHTML = '<ul class="pagination justify-content-center"></ul>';
      card.appendChild(pagEl);
    }

    const ul = pagEl.querySelector("ul");
    const { page, total, limit } = this.data.pagination;
    const totalPages = Math.ceil(total / limit) || 1;

    if (totalPages <= 1) {
      ul.innerHTML = "";
      return;
    }

    let html = `
      <li class="page-item ${page <= 1 ? "disabled" : ""}">
        <a class="page-link" href="#" onclick="StudentsWithBalanceController.loadData(${page - 1}); return false;">&laquo;</a>
      </li>`;

    const start = Math.max(1, page - 2);
    const end = Math.min(totalPages, page + 2);

    for (let i = start; i <= end; i++) {
      html += `
        <li class="page-item ${i === page ? "active" : ""}">
          <a class="page-link" href="#" onclick="StudentsWithBalanceController.loadData(${i}); return false;">${i}</a>
        </li>`;
    }

    html += `
      <li class="page-item ${page >= totalPages ? "disabled" : ""}">
        <a class="page-link" href="#" onclick="StudentsWithBalanceController.loadData(${page + 1}); return false;">&raquo;</a>
      </li>`;

    ul.innerHTML = html;
  },

  viewStatement: async function (studentId) {
    try {
      const resp = await window.API.finance.getStudentFeeStatement(studentId);
      const payload = this.unwrapPayload(resp);

      if (!payload) {
        this.showError("No statement data available");
        return;
      }

      const student = payload.student || {};
      const invoices = payload.invoices || [];
      const payments = payload.payments || [];
      const summary = payload.summary || {};

      let modalHtml = `
        <div class="modal fade" id="statementModal" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Fee Statement — ${this.escapeHtml(student.first_name || "")} ${this.escapeHtml(student.last_name || "")}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="row mb-3">
                  <div class="col-md-4"><strong>Admission No:</strong> ${student.admission_no || "-"}</div>
                  <div class="col-md-4"><strong>Class:</strong> ${student.class_name || "-"}</div>
                  <div class="col-md-4"><strong>Balance:</strong> <span class="text-danger fw-bold">${this.formatCurrency(summary.balance || 0)}</span></div>
                </div>

                <h6>Invoices</h6>
                <table class="table table-sm table-bordered">
                  <thead class="table-light">
                    <tr><th>Date</th><th>Description</th><th>Amount</th><th>Status</th></tr>
                  </thead>
                  <tbody>
                    ${invoices.length ? invoices.map((inv) => `
                      <tr>
                        <td>${inv.date || inv.created_at || "-"}</td>
                        <td>${this.escapeHtml(inv.description || inv.fee_type || "-")}</td>
                        <td>${this.formatCurrency(inv.amount || 0)}</td>
                        <td><span class="badge bg-${inv.status === "paid" ? "success" : "warning"}">${inv.status || "-"}</span></td>
                      </tr>`).join("") : '<tr><td colspan="4" class="text-center text-muted">No invoices</td></tr>'}
                  </tbody>
                </table>

                <h6 class="mt-3">Payment History</h6>
                <table class="table table-sm table-bordered">
                  <thead class="table-light">
                    <tr><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th></tr>
                  </thead>
                  <tbody>
                    ${payments.length ? payments.map((p) => `
                      <tr>
                        <td>${p.payment_date || p.date || "-"}</td>
                        <td>${p.payment_method || p.method || "-"}</td>
                        <td>${p.reference || p.receipt_no || "-"}</td>
                        <td>${this.formatCurrency(p.amount || 0)}</td>
                      </tr>`).join("") : '<tr><td colspan="4" class="text-center text-muted">No payments recorded</td></tr>'}
                  </tbody>
                </table>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="StudentsWithBalanceController.printStatement()">
                  <i class="fas fa-print"></i> Print
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>`;

      // Remove existing modal if any
      document.getElementById("statementModal")?.remove();
      document.body.insertAdjacentHTML("beforeend", modalHtml);
      new bootstrap.Modal(document.getElementById("statementModal")).show();
    } catch (error) {
      console.error("Error loading statement:", error);
      this.showError("Failed to load fee statement");
    }
  },

  recordPayment: function (studentId) {
    // Show a quick payment recording modal
    let modalHtml = `
      <div class="modal fade" id="quickPaymentModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title">Record Payment</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" id="payStudentId" value="${studentId}">
              <div class="mb-3">
                <label class="form-label">Amount (KES) <span class="text-danger">*</span></label>
                <input type="number" id="payAmount" class="form-control" min="1" step="0.01" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                <select id="payMethod" class="form-select" required>
                  <option value="">Select</option>
                  <option value="cash">Cash</option>
                  <option value="mpesa">M-Pesa</option>
                  <option value="bank">Bank Transfer</option>
                  <option value="cheque">Cheque</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Reference</label>
                <input type="text" id="payReference" class="form-control" placeholder="Receipt/transaction ref">
              </div>
              <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea id="payNotes" class="form-control" rows="2"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary" onclick="StudentsWithBalanceController.submitPayment()">
                <i class="fas fa-save"></i> Save Payment
              </button>
            </div>
          </div>
        </div>
      </div>`;

    document.getElementById("quickPaymentModal")?.remove();
    document.body.insertAdjacentHTML("beforeend", modalHtml);
    new bootstrap.Modal(document.getElementById("quickPaymentModal")).show();
  },

  submitPayment: async function () {
    const studentId = document.getElementById("payStudentId").value;
    const amount = document.getElementById("payAmount").value;
    const method = document.getElementById("payMethod").value;
    const reference = document.getElementById("payReference").value;
    const notes = document.getElementById("payNotes").value;

    if (!amount || !method) {
      this.showError("Amount and method are required");
      return;
    }

    try {
      await window.API.finance.recordPayment({
        student_id: studentId,
        amount: parseFloat(amount),
        payment_method: method,
        reference: reference,
        notes: notes,
      });

      bootstrap.Modal.getInstance(document.getElementById("quickPaymentModal"))?.hide();
      this.showSuccess("Payment recorded successfully");
      await this.loadData(this.data.pagination.page);
    } catch (error) {
      console.error("Error recording payment:", error);
      this.showError(error.message || "Failed to record payment");
    }
  },

  sendReminder: async function (studentId) {
    if (!confirm("Send payment reminder to this student's parent/guardian?")) return;

    try {
      await window.API.apiCall("/communications/send-reminder", "POST", {
        student_id: studentId,
        type: "fee_reminder",
      });
      this.showSuccess("Payment reminder sent");
    } catch (error) {
      this.showError(error.message || "Failed to send reminder");
    }
  },

  sendReminders: async function () {
    const selected = Array.from(document.querySelectorAll(".student-select:checked")).map(
      (cb) => cb.dataset.id
    );

    if (!selected.length) {
      this.showError("Select at least one student");
      return;
    }

    if (!confirm(`Send payment reminders to ${selected.length} parent(s)/guardian(s)?`)) return;

    try {
      for (const id of selected) {
        await window.API.apiCall("/communications/send-reminder", "POST", {
          student_id: id,
          type: "fee_reminder",
        });
      }
      this.showSuccess(`Reminders sent to ${selected.length} parent(s)`);
    } catch (error) {
      this.showError(error.message || "Failed to send reminders");
    }
  },

  exportCSV: function () {
    const rows = [["Admission No", "Student Name", "Class", "Total Fees", "Paid", "Balance", "Last Payment"]];

    this.data.students.forEach((s) => {
      const name = `${s.first_name || ""} ${s.last_name || ""}`.trim();
      const totalFees = parseFloat(s.total_fees || s.expected || 0);
      const paid = parseFloat(s.total_paid || s.paid || 0);
      const balance = parseFloat(s.balance || s.outstanding || totalFees - paid);

      rows.push([
        s.admission_no || "",
        name,
        s.class_name || "",
        totalFees.toFixed(2),
        paid.toFixed(2),
        balance.toFixed(2),
        s.last_payment_date || "",
      ]);
    });

    const csvContent = "data:text/csv;charset=utf-8," + rows.map((r) => r.join(",")).join("\n");
    const link = document.createElement("a");
    link.href = encodeURI(csvContent);
    link.download = `students_with_balance_${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
  },

  printStatement: function () {
    const content = document.querySelector("#statementModal .modal-body");
    if (!content) return;

    const printWindow = window.open("", "_blank");
    printWindow.document.write(`
      <html>
        <head>
          <title>Fee Statement</title>
          <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
          <style>@media print { .no-print { display: none; } }</style>
        </head>
        <body class="p-4">${content.innerHTML}</body>
      </html>`);
    printWindow.document.close();
    printWindow.onload = () => printWindow.print();
  },

  formatCurrency: function (value) {
    const num = parseFloat(value) || 0;
    return "KES " + num.toLocaleString("en-KE", { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  },

  escapeHtml: function (value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  },

  unwrapPayload: function (response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined) return response.data.data;
    return response;
  },

  showSuccess: function (message) {
    if (window.API?.showNotification) {
      window.API.showNotification(message, "success");
    } else {
      alert(message);
    }
  },

  showError: function (message) {
    if (window.API?.showNotification) {
      window.API.showNotification(message, "error");
    } else {
      alert("Error: " + message);
    }
  },
};

document.addEventListener("DOMContentLoaded", () => StudentsWithBalanceController.init());
window.StudentsWithBalanceController = StudentsWithBalanceController;
