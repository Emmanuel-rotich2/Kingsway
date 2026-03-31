/**
 * All Students - Accountant View
 * Shows student list and fee tracking history.
 */

const studentFeeTracker = {
  state: {
    students: [],
    classes: [],
    streams: [],
    studentTypes: [],
    balancesById: {},
    pagination: { page: 1, limit: 20, total: 0, total_pages: 1 },
  },
  modal: null,

  init: async function () {
    if (typeof AuthContext === "undefined" || !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    const user = AuthContext.getUser();
    if (user && document.getElementById("userInitial")) {
      document.getElementById("userInitial").textContent =
        (user.name || "A").charAt(0).toUpperCase();
    }

    await this.loadFilters();
    await this.loadStudentTypes();
    await this.loadStudents(1);
    this.bindEvents();
  },

  bindEvents: function () {
    const classFilter = document.getElementById("filterClass");
    const streamFilter = document.getElementById("filterStream");
    const statusFilter = document.getElementById("filterStatus");
    const searchInput = document.getElementById("searchStudent");
    const exportBtn = document.getElementById("exportStudentsBtn");

    if (classFilter)
      classFilter.addEventListener("change", () => this.loadStudents(1));
    if (streamFilter)
      streamFilter.addEventListener("change", () => this.loadStudents(1));
    if (statusFilter)
      statusFilter.addEventListener("change", () => this.loadStudents(1));
    if (searchInput)
      searchInput.addEventListener(
        "input",
        this.debounce(() => this.loadStudents(1), 300)
      );
    if (exportBtn)
      exportBtn.addEventListener("click", () => this.exportCsv());

    const tableBody = document.getElementById("studentsTableBody");
    if (tableBody) {
      tableBody.addEventListener("click", (event) => {
        const btn = event.target.closest("[data-action='fee-track']");
        if (!btn) return;
        const studentId = btn.getAttribute("data-id");
        if (studentId) this.openFeeTrack(parseInt(studentId, 10));
      });
    }
  },

  loadFilters: async function () {
    try {
      const [classRes, streamRes] = await Promise.all([
        this.safeCall(API.academic.listClasses()),
        this.safeCall(API.academic.listStreams()),
      ]);

      const classes = this.unwrapList(classRes, ["classes"]);
      const streams = this.unwrapList(streamRes, ["streams"]);

      this.state.classes = classes;
      this.state.streams = streams;

      const classSelect = document.getElementById("filterClass");
      const streamSelect = document.getElementById("filterStream");

      if (classSelect) {
        classes.forEach((cls) => {
          const opt = document.createElement("option");
          opt.value = cls.id;
          opt.textContent = cls.name || cls.class_name || cls.stream_name || "Class";
          classSelect.appendChild(opt);
        });
      }

      if (streamSelect) {
        streams.forEach((stream) => {
          const opt = document.createElement("option");
          opt.value = stream.id;
          opt.textContent = stream.stream_name || stream.name;
          streamSelect.appendChild(opt);
        });
      }
    } catch (error) {
      console.error("Error loading filters:", error);
    }
  },

  loadStudentTypes: async function () {
    try {
      const resp = await this.safeCall(API.finance.listStudentTypes());
      const types = this.unwrapList(resp, ["studentTypes", "types"]);
      this.state.studentTypes = types;
    } catch (error) {
      console.warn("Could not load student types:", error);
    }
  },

  loadStudents: async function (page = 1) {
    try {
      const params = {
        page: page,
        limit: this.state.pagination.limit,
      };

      const classId = document.getElementById("filterClass")?.value;
      const streamId = document.getElementById("filterStream")?.value;
      const status = document.getElementById("filterStatus")?.value;
      const search = document.getElementById("searchStudent")?.value;

      if (classId) params.class_id = classId;
      if (streamId) params.stream_id = streamId;
      if (status) params.status = status;
      if (search) params.search = search;

      const resp = await this.safeCall(API.students.list(params));
      const payload = this.unwrapPayload(resp) || {};

      const students = this.unwrapList(payload, ["students"]);
      const pagination = payload.pagination || payload.data?.pagination || {};

      this.state.students = students;
      this.state.pagination = {
        ...this.state.pagination,
        ...pagination,
        page: pagination.page || page,
      };

      this.state.balancesById = {};
      this.renderTable();
      this.renderPagination();
      this.loadBalances();
    } catch (error) {
      console.error("Error loading students:", error);
      this.renderError("Failed to load students.");
    }
  },

  loadBalances: async function () {
    const studentIds = this.state.students.map((student) => student.id).filter(Boolean);
    if (!studentIds.length) return;

    const resp = await this.safeCall(
      API.finance.getStudentsBalances(studentIds)
    );
    const payload = this.unwrapPayload(resp) || {};
    const balances = this.unwrapList(payload, ["balances"]);

    const balanceMap = {};
    balances.forEach((row) => {
      if (!row || row.student_id === undefined) return;
      const value =
        row.balance ??
        row.current_balance ??
        row.balance_outstanding ??
        row.term_balance ??
        0;
      balanceMap[row.student_id] = parseFloat(value) || 0;
    });

    this.state.balancesById = balanceMap;
    this.renderTable();
  },

  renderTable: function () {
    const tbody = document.getElementById("studentsTableBody");
    if (!tbody) return;

    if (!this.state.students.length) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center text-muted">No students found</td></tr>';
      document.getElementById("showingCount").textContent = "0";
      document.getElementById("totalCount").textContent = "0";
      return;
    }

    const typeMap = this.state.studentTypes.reduce((acc, item) => {
      if (item && item.id) acc[item.id] = item.name || item.code || item.id;
      return acc;
    }, {});

    tbody.innerHTML = this.state.students
      .map((student) => {
        const fullName = [
          student.first_name,
          student.middle_name,
          student.last_name,
        ]
          .filter(Boolean)
          .join(" ");

        const gender = student.gender
          ? student.gender.charAt(0).toUpperCase() + student.gender.slice(1)
          : "-";
        const typeLabel = typeMap[student.student_type_id] || student.student_type_id || "-";
        const statusLabel = student.status || "-";
        const statusClass = statusLabel === "active" ? "success" : "secondary";
        const balanceValue = this.state.balancesById[student.id];
        const balanceDisplay =
          balanceValue === undefined
            ? '<span class="text-muted">...</span>'
            : this.formatCurrency(balanceValue);

        return `
          <tr>
            <td>${this.escapeHtml(student.admission_no || "-")}</td>
            <td><strong>${this.escapeHtml(fullName || "-")}</strong></td>
            <td>${this.escapeHtml(student.class_name || "-")}</td>
            <td>${this.escapeHtml(student.stream_name || "-")}</td>
            <td>${this.escapeHtml(gender)}</td>
            <td>${this.escapeHtml(typeLabel)}</td>
            <td><span class="badge bg-${statusClass}">${this.escapeHtml(statusLabel)}</span></td>
            <td>${balanceDisplay}</td>
            <td>
              <button class="btn btn-sm btn-outline-success" data-action="fee-track" data-id="${student.id}">
                Fee Track
              </button>
            </td>
          </tr>
        `;
      })
      .join("");

    const total = this.state.pagination.total || this.state.students.length;
    document.getElementById("showingCount").textContent = String(this.state.students.length);
    document.getElementById("totalCount").textContent = String(total);
  },

  renderPagination: function () {
    const container = document.getElementById("pagination");
    if (!container) return;

    const total = this.state.pagination.total || this.state.students.length;
    const limit = this.state.pagination.limit || 20;
    const page = this.state.pagination.page || 1;
    const totalPages = Math.max(1, Math.ceil(total / limit));

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `
        <button class="btn btn-sm ${i === page ? "btn-primary" : "btn-outline-secondary"} me-1" data-page="${i}">
          ${i}
        </button>
      `;
    }

    container.innerHTML = html;
    container.querySelectorAll("button[data-page]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const targetPage = parseInt(btn.getAttribute("data-page"), 10);
        this.loadStudents(targetPage);
      });
    });
  },

  openFeeTrack: async function (studentId) {
    const student = this.state.students.find((s) => s.id === studentId);
    const title = document.getElementById("feeTrackTitle");
    const content = document.getElementById("feeTrackContent");

    if (!content) return;
    content.innerHTML = `<div class="text-center py-4">
      <div class="spinner-border text-success" role="status"></div>
      <p class="mt-2 text-muted">Loading fee history...</p>
    </div>`;

    if (title) {
      const name = student
        ? [student.first_name, student.middle_name, student.last_name]
            .filter(Boolean)
            .join(" ")
        : "Student";
      title.textContent = `Fee Track: ${name}`;
    }

    if (!this.modal) {
      const modalEl = document.getElementById("feeTrackModal");
      if (modalEl && window.bootstrap) {
        this.modal = new bootstrap.Modal(modalEl);
      }
    }
    if (this.modal) this.modal.show();

    const [historyResp, balanceResp, enrollmentResp, invoiceResp] = await Promise.all([
      this.safeCall(API.finance.getStudentPaymentHistory(studentId)),
      this.safeCall(API.finance.getStudentBalance(studentId)),
      this.safeCall(API.students.getEnrollmentHistory(studentId)),
      this.safeCall(API.finance.getStudentInvoiceTrack(studentId)),
    ]);

    const historyPayload = this.unwrapPayload(historyResp) || {};
    const balancePayload = this.unwrapPayload(balanceResp) || {};
    const enrollmentPayload = this.unwrapPayload(enrollmentResp) || {};
    const invoicePayload = this.unwrapPayload(invoiceResp) || {};

    const paymentHistory = historyPayload.history || [];
    const summary = historyPayload.summary || {};
    const balances = this.unwrapList(balancePayload, ["balances"]);
    const enrollments = this.unwrapList(enrollmentPayload, ["data"]) || this.unwrapList(enrollmentPayload, []);
    const invoices = this.unwrapList(invoicePayload, ["invoices"]);

    const currentBalance = this.resolveCurrentBalance(balances, summary);

    content.innerHTML = this.renderFeeTrackContent({
      student,
      summary,
      paymentHistory,
      balances,
      enrollments,
      invoices,
      currentBalance,
    });
  },

  renderFeeTrackContent: function ({
    student,
    summary,
    paymentHistory,
    balances,
    enrollments,
    invoices,
    currentBalance,
  }) {
    const totalPaid = summary.total_paid || 0;
    const totalDue = summary.total_due || 0;
    const totalBalance = summary.total_balance || 0;

    const studentInfo = student
      ? `<div class="mb-3">
            <strong>${this.escapeHtml(
              [student.first_name, student.middle_name, student.last_name]
                .filter(Boolean)
                .join(" ")
            )}</strong>
            <span class="text-muted">(${this.escapeHtml(
              student.admission_no || "-"
            )})</span>
            <div class="text-muted">${this.escapeHtml(
              student.class_name || "-"
            )} ${student.stream_name ? "- " + this.escapeHtml(student.stream_name) : ""}</div>
        </div>`
      : "";

    const summaryCards = `
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <div class="card border-success">
            <div class="card-body text-center">
              <div class="text-muted">Total Paid</div>
              <div class="fw-bold text-success">${this.formatCurrency(totalPaid)}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-primary">
            <div class="card-body text-center">
              <div class="text-muted">Total Due</div>
              <div class="fw-bold text-primary">${this.formatCurrency(totalDue)}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-warning">
            <div class="card-body text-center">
              <div class="text-muted">Total Balance</div>
              <div class="fw-bold text-warning">${this.formatCurrency(totalBalance)}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-danger">
            <div class="card-body text-center">
              <div class="text-muted">Current Balance</div>
              <div class="fw-bold text-danger">${this.formatCurrency(currentBalance)}</div>
            </div>
          </div>
        </div>
      </div>
    `;

    const paymentRows = paymentHistory.length
      ? paymentHistory
          .map((row) => {
            const termLabel = row.term_name || (row.term_number ? `Term ${row.term_number}` : "-");
            return `
              <tr>
                <td>${this.escapeHtml(row.academic_year || "-")}</td>
                <td>${this.escapeHtml(termLabel)}</td>
                <td>${this.formatCurrency(row.amount_due || 0)}</td>
                <td>${this.formatCurrency(row.total_paid || 0)}</td>
                <td>${this.formatCurrency(row.balance || 0)}</td>
                <td>${this.escapeHtml(row.fee_status || "-")}</td>
              </tr>
            `;
          })
          .join("")
      : `<tr><td colspan="6" class="text-center text-muted">No payment history recorded.</td></tr>`;

    const paymentTable = `
      <div class="card mb-3">
        <div class="card-header bg-light">
          <strong>Payment History</strong>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>Academic Year</th>
                  <th>Term</th>
                  <th>Amount Due</th>
                  <th>Total Paid</th>
                  <th>Balance</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>${paymentRows}</tbody>
            </table>
          </div>
        </div>
      </div>
    `;

    const invoiceSection = this.renderInvoiceSection(invoices || []);

    const enrollmentRows = enrollments.length
      ? enrollments
          .map((row) => {
            return `
              <tr>
                <td>${this.escapeHtml(row.year_name || row.year_code || "-")}</td>
                <td>${this.escapeHtml(row.class_name || "-")}</td>
                <td>${this.escapeHtml(row.stream_name || "-")}</td>
                <td>${this.escapeHtml(row.enrollment_status || "-")}</td>
                <td>${this.escapeHtml(row.enrollment_date || "-")}</td>
              </tr>
            `;
          })
          .join("")
      : `<tr><td colspan="5" class="text-center text-muted">No enrollment history recorded.</td></tr>`;

    const enrollmentTable = `
      <div class="card">
        <div class="card-header bg-light">
          <strong>Enrollment History</strong>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>Academic Year</th>
                  <th>Class</th>
                  <th>Stream</th>
                  <th>Status</th>
                  <th>Enrollment Date</th>
                </tr>
              </thead>
              <tbody>${enrollmentRows}</tbody>
            </table>
          </div>
        </div>
      </div>
    `;

    return `${studentInfo}${summaryCards}${paymentTable}${invoiceSection}${enrollmentTable}`;
  },

  renderInvoiceSection: function (invoices) {
    if (!invoices.length) {
      return `
        <div class="card mb-3">
          <div class="card-header bg-light">
            <strong>Invoices</strong>
          </div>
          <div class="card-body text-muted">No invoices available for this student.</div>
        </div>
      `;
    }

    const items = invoices
      .map((invoice, index) => {
        const yearLabel =
          invoice.year_code ||
          invoice.year_name ||
          invoice.term_year ||
          invoice.academic_year_id ||
          "-";
        const termLabel =
          invoice.term_name ||
          (invoice.term_number ? `Term ${invoice.term_number}` : "-");
        const headerId = `invoiceHeading_${invoice.id}_${index}`;
        const collapseId = `invoiceCollapse_${invoice.id}_${index}`;

        const allocations = Array.isArray(invoice.allocations)
          ? invoice.allocations
          : [];
        const receipts = Array.isArray(invoice.receipts)
          ? invoice.receipts
          : [];

        const allocationRows = allocations.length
          ? allocations
              .map((alloc) => {
                return `
                  <tr>
                    <td>${this.escapeHtml(alloc.receipt_no || alloc.payment_id || "-")}</td>
                    <td>${this.escapeHtml(alloc.fee_type_name || "-")}</td>
                    <td>${this.formatCurrency(alloc.amount_allocated || 0)}</td>
                    <td>${this.escapeHtml(alloc.allocation_date || "-")}</td>
                  </tr>
                `;
              })
              .join("")
          : `<tr><td colspan="4" class="text-center text-muted">No allocations recorded.</td></tr>`;

        const receiptRows = receipts.length
          ? receipts
              .map((receipt) => {
                return `
                  <tr>
                    <td>${this.escapeHtml(receipt.receipt_no || receipt.payment_id || "-")}</td>
                    <td>${this.escapeHtml(receipt.payment_method || "-")}</td>
                    <td>${this.escapeHtml(receipt.reference_no || "-")}</td>
                    <td>${this.escapeHtml(receipt.payment_date || "-")}</td>
                    <td>${this.formatCurrency(receipt.amount_paid || 0)}</td>
                    <td>${this.formatCurrency(receipt.allocated_total || 0)}</td>
                  </tr>
                `;
              })
              .join("")
          : `<tr><td colspan="6" class="text-center text-muted">No receipts recorded.</td></tr>`;

        return `
          <div class="accordion-item">
            <h2 class="accordion-header" id="${headerId}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                Invoice #${this.escapeHtml(invoice.id || "-")} · ${this.escapeHtml(
                  yearLabel
                )} · ${this.escapeHtml(termLabel)} · Balance ${this.formatCurrency(
                  invoice.balance || 0
                )}
              </button>
            </h2>
            <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="${headerId}" data-bs-parent="#invoiceAccordion">
              <div class="accordion-body">
                <div class="row g-3 mb-3">
                  <div class="col-md-3">
                    <div class="card border-primary">
                      <div class="card-body text-center">
                        <div class="text-muted">Total Amount</div>
                        <div class="fw-bold text-primary">${this.formatCurrency(
                          invoice.total_amount || 0
                        )}</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card border-success">
                      <div class="card-body text-center">
                        <div class="text-muted">Amount Paid</div>
                        <div class="fw-bold text-success">${this.formatCurrency(
                          invoice.amount_paid || 0
                        )}</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card border-warning">
                      <div class="card-body text-center">
                        <div class="text-muted">Balance</div>
                        <div class="fw-bold text-warning">${this.formatCurrency(
                          invoice.balance || 0
                        )}</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card border-secondary">
                      <div class="card-body text-center">
                        <div class="text-muted">Status</div>
                        <div class="fw-bold text-secondary">${this.escapeHtml(
                          invoice.status || "-"
                        )}</div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card mb-3">
                  <div class="card-header bg-light">
                    <strong>Allocations</strong>
                  </div>
                  <div class="card-body p-0">
                    <div class="table-responsive">
                      <table class="table table-sm mb-0">
                        <thead class="table-light">
                          <tr>
                            <th>Receipt</th>
                            <th>Fee Type</th>
                            <th>Allocated</th>
                            <th>Allocation Date</th>
                          </tr>
                        </thead>
                        <tbody>${allocationRows}</tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <div class="card">
                  <div class="card-header bg-light">
                    <strong>Receipts</strong>
                  </div>
                  <div class="card-body p-0">
                    <div class="table-responsive">
                      <table class="table table-sm mb-0">
                        <thead class="table-light">
                          <tr>
                            <th>Receipt</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Payment Date</th>
                            <th>Amount Paid</th>
                            <th>Allocated</th>
                          </tr>
                        </thead>
                        <tbody>${receiptRows}</tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
      })
      .join("");

    return `
      <div class="card mb-3">
        <div class="card-header bg-light">
          <strong>Invoices</strong>
        </div>
        <div class="card-body">
          <div class="accordion" id="invoiceAccordion">
            ${items}
          </div>
        </div>
      </div>
    `;
  },

  resolveCurrentBalance: function (balances, summary) {
    if (balances && balances.length) {
      const latest = balances[0];
      const value = latest.balance ?? latest.term_balance ?? latest.year_balance;
      if (value !== undefined && value !== null) return parseFloat(value);
    }
    if (summary && summary.total_balance !== undefined) {
      return parseFloat(summary.total_balance) || 0;
    }
    return 0;
  },

  exportCsv: function () {
    if (!this.state.students.length) return;

    const headers = [
      "Admission No",
      "Name",
      "Class",
      "Stream",
      "Gender",
      "Type",
      "Status",
      "Balance",
    ];

    const rows = this.state.students.map((student) => {
      const fullName = [
        student.first_name,
        student.middle_name,
        student.last_name,
      ]
        .filter(Boolean)
        .join(" ");

      const balanceValue = this.state.balancesById[student.id];
      const balance = balanceValue === undefined ? "" : balanceValue;

      return [
        student.admission_no || "",
        fullName,
        student.class_name || "",
        student.stream_name || "",
        student.gender || "",
        student.student_type_id || "",
        student.status || "",
        balance,
      ];
    });

    const csv = [headers, ...rows]
      .map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(","))
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.download = `students_export_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  },

  renderError: function (message) {
    const tbody = document.getElementById("studentsTableBody");
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">${this.escapeHtml(
      message
    )}</td></tr>`;
  },

  escapeHtml: function (value) {
    if (value === null || value === undefined) return "";
    return String(value).replace(/[&<>"']/g, (match) => {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[match];
    });
  },

  formatCurrency: function (amount) {
    const value = parseFloat(amount);
    if (Number.isNaN(value)) return "KES 0";
    try {
      return new Intl.NumberFormat("en-KE", {
        style: "currency",
        currency: "KES",
        maximumFractionDigits: 0,
      }).format(value);
    } catch (e) {
      return `KES ${value.toFixed(0)}`;
    }
  },

  debounce: function (fn, delay) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  },

  safeCall: async function (promise) {
    try {
      return await promise;
    } catch (error) {
      console.warn("Student fee tracker API call failed:", error?.message || error);
      return null;
    }
  },

  unwrapPayload: function (response) {
    if (!response) return null;
    if (response.data && response.data.data !== undefined) return response.data.data;
    if (response.data !== undefined) return response.data;
    return response;
  },

  unwrapList: function (response, keys = []) {
    if (!response) return [];
    if (Array.isArray(response)) return response;
    if (Array.isArray(response.data)) return response.data;
    for (const key of keys) {
      if (Array.isArray(response[key])) return response[key];
      if (Array.isArray(response.data?.[key])) return response.data[key];
    }
    return [];
  },
};

document.addEventListener("DOMContentLoaded", () => {
  studentFeeTracker.init();
});
