/*
 * Student Fees Page Controller
 * Manages student fee tracking, statements, and payment recording.
 */

const StudentFeesController = {
  data: {
    rows: [],
    classes: [],
    years: [],
    pagination: { page: 1, limit: 25, total: 0 },
    summary: {
      total_due: 0,
      total_paid: 0,
      total_balance: 0,
      collection_rate: 0,
    },
  },
  filters: {
    search: "",
    class_name: "",
    status: "",
    term_number: "",
    academic_year: "",
    page: 1,
    limit: 25,
  },
  ui: {},

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }

    this.cacheDom();
    await this.loadInitialData();
    await this.loadPaymentStatus();
    this.attachEvents();
  },

  cacheDom: function () {
    this.ui = {
      searchInput: document.getElementById("searchStudent"),
      classFilter: document.getElementById("classFilter"),
      statusFilter: document.getElementById("statusFilter"),
      termFilter: document.getElementById("termFilter"),
      recordPaymentBtn: document.getElementById("recordPaymentBtn"),
      exportBtn: document.getElementById("exportBtn"),
      tableBody: document.querySelector("#feesTable tbody"),
      pagination: document.getElementById("pagination"),
      totalExpected: document.getElementById("totalExpected"),
      totalCollected: document.getElementById("totalCollected"),
      totalOutstanding: document.getElementById("totalOutstanding"),
      collectionRate: document.getElementById("collectionRate"),
      paymentModal: document.getElementById("paymentModal"),
      paymentForm: document.getElementById("paymentForm"),
      paymentStudent: document.getElementById("paymentStudent"),
      paymentStudentId: document.getElementById("studentId"),
      paymentAmount: document.getElementById("amount"),
      paymentMethod: document.getElementById("paymentMethod"),
      paymentReference: document.getElementById("reference"),
      paymentDate: document.getElementById("paymentDate"),
      paymentNotes: document.getElementById("notes"),
      savePaymentBtn: document.getElementById("savePaymentBtn"),
      outstandingAmount: document.getElementById("outstandingAmount"),
      feeDetailsModal: document.getElementById("feeDetailsModal"),
      studentName: document.getElementById("studentName"),
      admNo: document.getElementById("admNo"),
      modalTotalFee: document.getElementById("modalTotalFee"),
      modalTotalPaid: document.getElementById("modalTotalPaid"),
      modalBalance: document.getElementById("modalBalance"),
      feeBreakdownBody: document.getElementById("feeBreakdownBody"),
      paymentHistoryBody: document.getElementById("paymentHistoryBody"),
      printStatementBtn: document.getElementById("printStatementBtn"),
    };
  },

  attachEvents: function () {
    if (this.ui.searchInput) {
      this.ui.searchInput.addEventListener(
        "input",
        this.debounce((event) => {
          this.filters.search = event.target.value.trim();
          this.filters.page = 1;
          this.loadPaymentStatus();
        }, 300),
      );
    }

    if (this.ui.classFilter) {
      this.ui.classFilter.addEventListener("change", (event) => {
        this.filters.class_name = event.target.value;
        this.filters.page = 1;
        this.loadPaymentStatus();
      });
    }

    if (this.ui.statusFilter) {
      this.ui.statusFilter.addEventListener("change", (event) => {
        const value = event.target.value;
        const statusMap = {
          paid: "paid",
          partial: "partial",
          unpaid: "pending",
          overpaid: "paid",
        };
        this.filters.status = value ? statusMap[value] || value : "";
        this.filters.page = 1;
        this.loadPaymentStatus();
      });
    }

    if (this.ui.termFilter) {
      this.ui.termFilter.addEventListener("change", (event) => {
        const value = event.target.value;
        this.filters.term_number = value ? value : "";
        this.filters.page = 1;
        this.loadPaymentStatus();
      });
    }

    if (this.ui.recordPaymentBtn) {
      this.ui.recordPaymentBtn.addEventListener("click", () => {
        this.openPaymentModal();
      });
    }

    if (this.ui.exportBtn) {
      this.ui.exportBtn.addEventListener("click", () => this.exportTable());
    }

    if (this.ui.paymentStudent) {
      this.ui.paymentStudent.addEventListener("change", async (event) => {
        const studentId = event.target.value;
        this.ui.paymentStudentId.value = studentId || "";
        await this.updateOutstandingAmount(studentId);
      });
    }

    if (this.ui.paymentMethod) {
      this.ui.paymentMethod.addEventListener("change", () => {
        const method = this.ui.paymentMethod.value;
        const refDiv = document.getElementById("referenceDiv");
        if (refDiv) {
          refDiv.style.display = method === "cash" ? "none" : "block";
        }
      });
    }

    if (this.ui.savePaymentBtn) {
      this.ui.savePaymentBtn.addEventListener("click", () =>
        this.savePayment(),
      );
    }

    if (this.ui.printStatementBtn) {
      this.ui.printStatementBtn.addEventListener("click", () => {
        if (this.ui.feeDetailsModal) {
          const modal = bootstrap.Modal.getInstance(this.ui.feeDetailsModal);
          if (modal) {
            window.print();
          }
        }
      });
    }
  },

  loadInitialData: async function () {
    try {
      const [classesResp, yearsResp] = await Promise.all([
        window.API.academic.listClasses(),
        window.API.academic.listYears(),
      ]);

      const classes = this.unwrapList(classesResp);
      this.data.classes = classes;
      this.populateClassFilter(classes);

      const years = this.unwrapList(yearsResp);
      this.data.years = years;
      const currentYear = years.find(
        (year) => year.is_current == 1 || year.is_current === "1",
      );
      let activeAcademicYear = "";
      if (currentYear) {
        activeAcademicYear = this.normalizeAcademicYearValue(
          currentYear.year_code || currentYear.year || currentYear.name || "",
        );
        this.filters.academic_year = activeAcademicYear;
      }

      try {
        const termParams = {};
        if (activeAcademicYear) {
          termParams.academic_year = activeAcademicYear;
          termParams.year = activeAcademicYear;
        }
        const termsResp = await window.API.academic.listTerms(termParams);
        const terms = this.unwrapList(termsResp);
        this.populateTermFilter(terms);
      } catch (termError) {
        console.warn("Failed to load terms:", termError);
        this.populateTermFilter([]);
      }
    } catch (error) {
      console.error("Failed to load initial data:", error);
    }
  },

  loadPaymentStatus: async function () {
    try {
      const params = { ...this.filters };
      const response =
        await window.API.finance.getStudentPaymentStatusList(params);
      const payload = response?.data ?? response;
      const items = payload?.items ?? payload?.data?.items ?? [];
      const pagination = payload?.pagination ??
        payload?.data?.pagination ?? { page: 1, limit: 25, total: 0 };
      const summary =
        payload?.summary ?? payload?.data?.summary ?? this.data.summary;

      this.data.rows = Array.isArray(items) ? items : [];
      this.data.pagination = {
        page: pagination.page || 1,
        limit: pagination.limit || this.filters.limit,
        total: pagination.total || 0,
      };
      this.data.summary = summary;

      this.renderTable();
      this.renderSummary();
      this.renderPagination();
      this.populatePaymentStudents();
    } catch (error) {
      console.error("Failed to load fee status:", error);
    }
  },

  renderSummary: function () {
    const summary = this.data.summary || {};
    this.ui.totalExpected.textContent = this.formatCurrency(
      summary.total_due || 0,
    );
    this.ui.totalCollected.textContent = this.formatCurrency(
      summary.total_paid || 0,
    );
    this.ui.totalOutstanding.textContent = this.formatCurrency(
      summary.total_balance || 0,
    );
    this.ui.collectionRate.textContent = `${summary.collection_rate || 0}%`;
  },

  renderTable: function () {
    if (!this.ui.tableBody) {
      return;
    }

    if (!this.data.rows.length) {
      this.ui.tableBody.innerHTML =
        '<tr><td colspan="8" class="text-center text-muted">No fee records found.</td></tr>';
      return;
    }

    this.ui.tableBody.innerHTML = this.data.rows
      .map((row) => {
        const status = this.formatPaymentStatus(row.payment_status);
        const safeName = (row.student_name || "").replace(/'/g, "\\'");
        return `
          <tr>
            <td>${row.admission_no || "-"}</td>
            <td>${row.student_name || "-"}</td>
            <td>${row.class_name || row.level_name || "-"}</td>
            <td>${this.formatCurrency(row.total_due || 0)}</td>
            <td>${this.formatCurrency(row.total_paid || 0)}</td>
            <td>${this.formatCurrency(row.current_balance || 0)}</td>
            <td><span class="badge ${status.badge}">${status.label}</span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1" data-action="view" data-student-id="${row.id}">
                View
              </button>
              <button class="btn btn-sm btn-outline-secondary" data-action="history" data-student-id="${row.id}" data-student-name="${safeName}">
                <i class="fas fa-history"></i> Full History
              </button>
            </td>
          </tr>
        `;
      })
      .join("");

    this.ui.tableBody
      .querySelectorAll("button[data-action='view']")
      .forEach((btn) => {
        btn.addEventListener("click", (event) => {
          const studentId = event.currentTarget.getAttribute("data-student-id");
          this.openFeeDetails(studentId);
        });
      });

    this.ui.tableBody
      .querySelectorAll("button[data-action='history']")
      .forEach((btn) => {
        btn.addEventListener("click", (event) => {
          const studentId = event.currentTarget.getAttribute("data-student-id");
          const studentName = event.currentTarget.getAttribute("data-student-name");
          this.openBillingHistory(studentId, studentName);
        });
      });
  },

  renderPagination: function () {
    if (!this.ui.pagination) {
      return;
    }

    const { page, limit, total } = this.data.pagination;
    const totalPages = Math.max(1, Math.ceil(total / limit));

    if (totalPages <= 1) {
      this.ui.pagination.innerHTML = "";
      return;
    }

    const createItem = (label, targetPage, disabled, active) => {
      const li = document.createElement("li");
      li.className = `page-item${disabled ? " disabled" : ""}${active ? " active" : ""}`;
      const link = document.createElement("a");
      link.className = "page-link";
      link.href = "#";
      link.textContent = label;
      if (!disabled) {
        link.addEventListener("click", (event) => {
          event.preventDefault();
          this.filters.page = targetPage;
          this.loadPaymentStatus();
        });
      }
      li.appendChild(link);
      return li;
    };

    this.ui.pagination.innerHTML = "";
    this.ui.pagination.appendChild(
      createItem("Prev", Math.max(1, page - 1), page === 1, false),
    );

    for (let p = 1; p <= totalPages; p += 1) {
      this.ui.pagination.appendChild(
        createItem(String(p), p, false, p === page),
      );
    }

    this.ui.pagination.appendChild(
      createItem(
        "Next",
        Math.min(totalPages, page + 1),
        page === totalPages,
        false,
      ),
    );
  },

  openFeeDetails: async function (studentId) {
    if (!studentId) {
      return;
    }

    try {
      const statementResp = await window.API.finance.getStudentFeeStatement(
        studentId,
        {
          academic_year: this.filters.academic_year || undefined,
        },
      );
      const payload = statementResp?.data ?? statementResp;
      const student = payload?.student || {};
      const summary = payload?.summary || {};
      const obligations = payload?.obligations || [];
      const payments = payload?.payments || [];
      const balance = payload?.balance || {};

      this.ui.studentName.textContent = student.student_name || "-";
      this.ui.admNo.textContent = student.admission_no || "-";
      this.ui.modalTotalFee.textContent = this.formatCurrency(
        summary.total_due ?? balance.total_fee ?? 0,
      );
      this.ui.modalTotalPaid.textContent = this.formatCurrency(
        summary.total_paid ?? balance.amount_paid ?? 0,
      );
      this.ui.modalBalance.textContent = this.formatCurrency(
        summary.balance ?? balance.balance ?? 0,
      );

      this.ui.feeBreakdownBody.innerHTML = obligations
        .map((item) => {
          const status = this.formatPaymentStatus(
            item.payment_status || "pending",
          );
          return `
            <tr>
              <td>${item.fee_structure_name || item.fee_type_name || "-"}</td>
              <td>${this.formatCurrency(item.amount_due || 0)}</td>
              <td><span class="badge ${status.badge}">${status.label}</span></td>
            </tr>
          `;
        })
        .join("");

      this.ui.paymentHistoryBody.innerHTML =
        payments
          .map((payment) => {
            return `
            <tr>
              <td>${this.formatDate(payment.payment_date)}</td>
              <td>${payment.receipt_no || payment.reference_no || "-"}</td>
              <td>${this.formatCurrency(payment.amount_paid || payment.amount || 0)}</td>
              <td>${payment.payment_method || payment.method || "-"}</td>
              <td>${payment.received_by_name || payment.received_by || "-"}</td>
            </tr>
          `;
          })
          .join("") ||
        '<tr><td colspan="5" class="text-muted text-center">No payments recorded.</td></tr>';

      const modal = new bootstrap.Modal(this.ui.feeDetailsModal);
      modal.show();
    } catch (error) {
      console.error("Failed to load fee statement:", error);
    }
  },

  openPaymentModal: function () {
    if (!this.ui.paymentModal) {
      return;
    }

    this.resetPaymentForm();
    const modal = new bootstrap.Modal(this.ui.paymentModal);
    modal.show();
  },

  populateClassFilter: function (classes) {
    if (!this.ui.classFilter) {
      return;
    }

    const firstOption = this.ui.classFilter.options[0];
    this.ui.classFilter.innerHTML = "";
    this.ui.classFilter.appendChild(firstOption);

    classes.forEach((cls) => {
      const option = document.createElement("option");
      option.value = cls.name || cls.class_name || cls.id;
      option.textContent = cls.name || cls.class_name || "";
      this.ui.classFilter.appendChild(option);
    });
  },

  populateTermFilter: function (terms) {
    if (!this.ui.termFilter) {
      return;
    }

    this.ui.termFilter.innerHTML = '<option value="">Current Term</option>';

    if (!Array.isArray(terms) || terms.length === 0) {
      return;
    }

    const unique = new Map();
    terms.forEach((term) => {
      const termNumber = term.term_number ?? null;
      if (!termNumber) {
        return;
      }
      const key = `${termNumber}-${term.year || ""}`;
      if (!unique.has(key)) {
        unique.set(key, term);
      }
    });

    const sorted = Array.from(unique.values()).sort((a, b) =>
      Number(a.term_number || 0) - Number(b.term_number || 0),
    );

    sorted.forEach((term) => {
      const option = document.createElement("option");
      option.value = term.term_number;
      const yearLabel = term.year ? ` (${term.year})` : "";
      option.textContent = `Term ${term.term_number}${yearLabel}`;
      this.ui.termFilter.appendChild(option);
    });

    const currentTerm = sorted.find(
      (term) =>
        term.status === "current" ||
        term.status === "active" ||
        term.is_current == 1 ||
        term.is_current === "1",
    );
    if (currentTerm && currentTerm.term_number) {
      this.ui.termFilter.value = String(currentTerm.term_number);
      this.filters.term_number = String(currentTerm.term_number);
    }
  },

  populatePaymentStudents: function () {
    if (!this.ui.paymentStudent) {
      return;
    }

    const firstOption =
      this.ui.paymentStudent.options[0] || new Option("Select student", "");
    this.ui.paymentStudent.innerHTML = "";
    this.ui.paymentStudent.appendChild(firstOption);

    const unique = new Map();
    this.data.rows.forEach((row) => {
      if (row.id && !unique.has(row.id)) {
        unique.set(row.id, row);
      }
    });

    unique.forEach((row) => {
      const option = document.createElement("option");
      option.value = row.id;
      option.textContent =
        `${row.admission_no || ""} - ${row.student_name || ""}`.trim();
      this.ui.paymentStudent.appendChild(option);
    });
  },

  updateOutstandingAmount: async function (studentId) {
    if (!studentId) {
      this.ui.outstandingAmount.textContent = this.formatCurrency(0);
      return;
    }

    try {
      const balanceResp = await window.API.finance.getStudentBalance(studentId);
      const payload = balanceResp?.data ?? balanceResp;
      const balances = payload?.balances || [];
      const latest = balances[0] || {};
      const balanceValue =
        latest.balance || latest.term_balance || latest.year_balance || 0;
      this.ui.outstandingAmount.textContent = this.formatCurrency(balanceValue);
    } catch (error) {
      console.warn("Failed to load student balance:", error);
      this.ui.outstandingAmount.textContent = this.formatCurrency(0);
    }
  },

  resetPaymentForm: function () {
    if (!this.ui.paymentForm) {
      return;
    }

    this.ui.paymentForm.reset();
    this.ui.paymentStudentId.value = "";
    this.ui.outstandingAmount.textContent = this.formatCurrency(0);
    if (this.ui.paymentDate) {
      const today = new Date().toISOString().split("T")[0];
      this.ui.paymentDate.value = today;
    }
  },

  savePayment: async function () {
    const studentId = this.ui.paymentStudent.value;
    const amount = parseFloat(this.ui.paymentAmount.value || "0");
    const paymentMethod = this.ui.paymentMethod.value;
    const paymentDate = this.ui.paymentDate.value;

    if (!studentId || !amount || amount <= 0 || !paymentDate) {
      showNotification(
        "Please provide student, amount, and payment date.",
        NOTIFICATION_TYPES.WARNING,
      );
      return;
    }

    const payload = {
      type: "payment",
      student_id: studentId,
      amount: amount,
      payment_method: paymentMethod === "bank" ? "bank_transfer" : paymentMethod,
      reference_no: this.ui.paymentReference.value || null,
      payment_date: paymentDate,
      notes: this.ui.paymentNotes.value || null,
    };

    try {
      await window.API.finance.recordPayment(payload);
      showNotification(
        "Payment recorded successfully.",
        NOTIFICATION_TYPES.SUCCESS,
      );
      const modal = bootstrap.Modal.getInstance(this.ui.paymentModal);
      if (modal) {
        modal.hide();
      }
      await this.loadPaymentStatus();
    } catch (error) {
      console.error("Failed to record payment:", error);
      showNotification("Failed to record payment.", NOTIFICATION_TYPES.ERROR);
    }
  },

  exportTable: function () {
    if (!this.data.rows.length) {
      showNotification("No data available to export.", NOTIFICATION_TYPES.INFO);
      return;
    }

    const headers = [
      "Admission No",
      "Student Name",
      "Class",
      "Expected",
      "Paid",
      "Balance",
      "Status",
    ];

    const rows = this.data.rows.map((row) => [
      row.admission_no || "",
      row.student_name || "",
      row.class_name || row.level_name || "",
      row.total_due || 0,
      row.total_paid || 0,
      row.current_balance || 0,
      row.payment_status || "",
    ]);

    const csv = [headers, ...rows]
      .map((line) =>
        line
          .map((value) => {
            const text = String(value ?? "");
            return `"${text.replace(/"/g, '""')}"`;
          })
          .join(","),
      )
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    downloadFile(
      blob,
      `student_fees_${new Date().toISOString().slice(0, 10)}.csv`,
    );
  },

  formatCurrency: function (value) {
    const number = Number(value || 0);
    return `KES ${number.toLocaleString("en-KE", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  },

  formatPaymentStatus: function (status) {
    const normalized = String(status || "").toLowerCase();
    if (normalized === "paid" || normalized === "fully_paid") {
      return { label: "Paid", badge: "bg-success" };
    }
    if (normalized === "partial") {
      return { label: "Partial", badge: "bg-warning text-dark" };
    }
    if (normalized === "overpaid") {
      return { label: "Overpaid", badge: "bg-info" };
    }
    if (normalized === "arrears") {
      return { label: "Arrears", badge: "bg-danger" };
    }
    if (normalized === "waived") {
      return { label: "Waived", badge: "bg-primary" };
    }
    return { label: "Pending", badge: "bg-secondary" };
  },

  formatDate: function (value) {
    if (!value) {
      return "-";
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return value;
    }
    return date.toLocaleDateString();
  },

  unwrapList: function (resp) {
    if (!resp) return [];
    if (Array.isArray(resp)) return resp;
    if (Array.isArray(resp.data)) return resp.data;
    if (Array.isArray(resp.items)) return resp.items;
    if (Array.isArray(resp.data?.items)) return resp.data.items;
    if (Array.isArray(resp.data?.data)) return resp.data.data;
    if (Array.isArray(resp.data?.data?.items)) return resp.data.data.items;
    return [];
  },

  openBillingHistory: function(studentId, studentName) {
    document.getElementById('historyStudentName').textContent = studentName;
    document.getElementById('billingHistoryContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    var modal = new bootstrap.Modal(document.getElementById('studentBillingHistoryModal'));
    modal.show();

    window.API.apiCall('/finance/students-billing-history/' + studentId, 'GET')
      .then(function(resp) {
        var data = resp.data || resp;
        StudentFeesController.renderBillingHistory(data, studentId);
      })
      .catch(function() {
        document.getElementById('billingHistoryContent').innerHTML = '<div class="alert alert-danger">Failed to load billing history.</div>';
      });
  },

  renderBillingHistory: function(data, studentId) {
    // data.academic_years is array of { year, terms: [{ term_id, term_name, obligations: [...], payments: [...], total_due, total_paid, balance }] }
    var years = data.academic_years || data || [];
    if (!years.length) {
      document.getElementById('billingHistoryContent').innerHTML = '<div class="alert alert-info">No billing history found.</div>';
      return;
    }

    var html = '';
    years.forEach(function(yr) {
      html += '<div class="card mb-3">';
      html += '<div class="card-header fw-bold bg-light">Academic Year ' + yr.year + '</div>';
      html += '<div class="card-body p-0">';

      // Tabs for terms
      html += '<ul class="nav nav-tabs px-3 pt-2" id="tabs-' + yr.year + '">';
      (yr.terms || []).forEach(function(term, i) {
        html += '<li class="nav-item"><a class="nav-link' + (i === 0 ? ' active' : '') + '" data-bs-toggle="tab" href="#term-' + yr.year + '-' + term.term_id + '">' + term.term_name + '</a></li>';
      });
      html += '</ul>';

      html += '<div class="tab-content p-3">';
      (yr.terms || []).forEach(function(term, i) {
        html += '<div class="tab-pane fade' + (i === 0 ? ' show active' : '') + '" id="term-' + yr.year + '-' + term.term_id + '">';

        // Obligations table
        html += '<h6 class="text-muted mb-2">Fee Obligations</h6>';
        html += '<table class="table table-sm table-bordered mb-3"><thead class="table-light"><tr><th>Fee Type</th><th>Amount Due</th><th>Paid</th><th>Waived</th><th>Balance</th><th>Status</th></tr></thead><tbody>';
        (term.obligations || []).forEach(function(o) {
          var statusClass = o.payment_status === 'paid' ? 'success' : o.payment_status === 'partial' ? 'warning' : 'danger';
          html += '<tr><td>' + (o.fee_type_name || '') + '</td><td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td><td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td><td>KES ' + Number(o.amount_waived || 0).toLocaleString() + '</td><td><strong>KES ' + Number(o.balance || 0).toLocaleString() + '</strong></td><td><span class="badge bg-' + statusClass + '">' + (o.payment_status || 'pending') + '</span></td></tr>';
        });
        html += '<tr class="table-light fw-bold"><td>TOTAL</td><td>KES ' + Number(term.total_due || 0).toLocaleString() + '</td><td>KES ' + Number(term.total_paid || 0).toLocaleString() + '</td><td>—</td><td>KES ' + Number(term.balance || 0).toLocaleString() + '</td><td></td></tr>';
        html += '</tbody></table>';

        // Payments table
        if ((term.payments || []).length > 0) {
          html += '<h6 class="text-muted mb-2">Payments Received</h6>';
          html += '<table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th><th>Reference</th></tr></thead><tbody>';
          (term.payments || []).forEach(function(p) {
            html += '<tr><td>' + (p.payment_date || '').substring(0, 10) + '</td><td>' + (p.payment_method || '') + '</td><td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td><td>' + (p.receipt_no || '—') + '</td><td>' + (p.reference_no || '—') + '</td></tr>';
          });
          html += '</tbody></table>';
        }

        html += '</div>'; // tab-pane
      });
      html += '</div></div></div>';
    });

    document.getElementById('billingHistoryContent').innerHTML = html;
  },

  debounce: function (fn, delay) {
    let timer = null;
    return function (...args) {
      if (timer) {
        clearTimeout(timer);
      }
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  },

  normalizeAcademicYearValue: function (value) {
    if (value === null || value === undefined) {
      return "";
    }

    const text = String(value).trim();
    if (!text) {
      return "";
    }

    const match = text.match(/(\d{4})/);
    return match ? match[1] : text;
  },
};

document.addEventListener("DOMContentLoaded", () =>
  StudentFeesController.init(),
);
