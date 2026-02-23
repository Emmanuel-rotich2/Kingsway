/**
 * Manage Finance Page Controller
 * Loads financial overview data and renders into the role-based finance templates.
 * Works with the shared DOM IDs used across admin_finance.php, manager_finance.php,
 * operator_finance.php and viewer_finance.php templates.
 *
 * Uses window.API.finance namespace from api.js
 */

const ManageFinanceController = {
  transactions: [],
  filtered: [],
  feeTypes: [],
  studentTypes: [],
  paymentMethods: [],
  pagination: { page: 1, limit: 15, total: 0 },

  // ── Helpers ──────────────────────────────────────────

  notify: function (message, type) {
    if (typeof showNotification === "function") {
      showNotification(message, type || "info");
    } else if (
      window.API &&
      typeof window.API.showNotification === "function"
    ) {
      window.API.showNotification(message, type || "info");
    } else {
      alert(message);
    }
  },

  esc: function (str) {
    if (!str && str !== 0) return "";
    return String(str).replace(/[&<>"']/g, function (m) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[m];
    });
  },

  formatCurrency: function (value) {
    return Number(value || 0).toLocaleString("en-KE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  },

  formatDate: function (value) {
    if (!value) return "";
    var d = new Date(value);
    if (isNaN(d.getTime())) return value;
    return d.toLocaleDateString("en-KE", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  },

  unwrapList: function (resp, keys) {
    if (!resp) return [];
    if (Array.isArray(resp)) return resp;
    if (Array.isArray(resp.data)) return resp.data;
    for (var i = 0; i < (keys || []).length; i++) {
      var k = keys[i];
      if (Array.isArray(resp[k])) return resp[k];
      if (resp.data && Array.isArray(resp.data[k])) return resp.data[k];
    }
    return [];
  },

  safeCall: async function (promise) {
    try {
      return await promise;
    } catch (error) {
      console.warn(
        "ManageFinanceController API call failed:",
        error && error.message ? error.message : error,
      );
      return null;
    }
  },

  // ── Init ─────────────────────────────────────────────

  init: async function () {
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = "/Kingsway/index.php";
      return;
    }

    this.bindEvents();
    await this.loadData();
  },

  bindEvents: function () {
    var self = this;

    var search = document.getElementById("financeSearch");
    if (search) {
      search.addEventListener("input", function () {
        self.pagination.page = 1;
        self.applyFilters();
      });
    }

    var typeFilter = document.getElementById("transactionTypeFilter");
    if (typeFilter) {
      typeFilter.addEventListener("change", function () {
        self.pagination.page = 1;
        self.applyFilters();
      });
    }

    var catFilter = document.getElementById("categoryFilter");
    if (catFilter) {
      catFilter.addEventListener("change", function () {
        self.pagination.page = 1;
        self.applyFilters();
      });
    }

    var dateFrom = document.getElementById("dateFromFilter");
    if (dateFrom) {
      dateFrom.addEventListener("change", function () {
        self.pagination.page = 1;
        self.applyFilters();
      });
    }

    var dateTo = document.getElementById("dateToFilter");
    if (dateTo) {
      dateTo.addEventListener("change", function () {
        self.pagination.page = 1;
        self.applyFilters();
      });
    }
  },

  // ── Data Loading ─────────────────────────────────────

  loadData: async function () {
    var results = await Promise.all([
      this.safeCall(
        window.API.finance.getTransactions({ type: "payments", limit: 500 }),
      ),
      this.safeCall(
        window.API.finance.getTransactions({ type: "expenses", limit: 500 }),
      ),
      this.safeCall(window.API.finance.listFeeTypes()),
      this.safeCall(window.API.finance.listStudentTypes()),
    ]);

    var payments = this.unwrapList(results[0], ["payments"]);
    var expenses = this.unwrapList(results[1], ["expenses"]);
    this.feeTypes = this.unwrapList(results[2], ["fee_types"]);
    this.studentTypes = this.unwrapList(results[3], ["student_types"]);

    this.transactions = this.normalizeTransactions(payments, expenses);
    this.applyFilters();
    this.renderStats();
  },

  normalizeTransactions: function (payments, expenses) {
    var normalized = [];

    payments.forEach(function (p) {
      var amount = parseFloat(p.amount_paid || p.amount || p.total_amount || 0);
      normalized.push({
        id: p.id,
        source: "payment",
        type: "income",
        category: p.payment_type || p.fee_type || "Payment",
        description:
          p.description || "Payment - " + (p.student_name || "Student"),
        amount: amount,
        date: p.payment_date || p.created_at,
        status: p.status || "completed",
        recorded_by: p.received_by_name || p.recorded_by_name || "-",
        raw: p,
      });
    });

    expenses.forEach(function (e) {
      var amount = parseFloat(e.amount || 0);
      normalized.push({
        id: e.id,
        source: "expense",
        type: "expense",
        category: e.expense_category || e.budget_category || "Expense",
        description: e.description || e.vendor_name || "Expense",
        amount: amount,
        date: e.expense_date || e.created_at,
        status: e.status || "pending",
        recorded_by: e.recorded_by_name || e.recorded_by || "-",
        raw: e,
      });
    });

    normalized.sort(function (a, b) {
      return new Date(b.date || 0) - new Date(a.date || 0);
    });

    return normalized;
  },

  // ── Stats ────────────────────────────────────────────

  renderStats: function () {
    var revenue = 0;
    var expenseTotal = 0;
    var pendingCount = 0;

    this.transactions.forEach(function (t) {
      if (t.type === "income") revenue += t.amount || 0;
      if (t.type === "expense") expenseTotal += t.amount || 0;
      if ((t.status || "").toLowerCase() === "pending") pendingCount++;
    });

    var net = revenue - expenseTotal;

    var el;
    el = document.getElementById("totalRevenue");
    if (el) el.textContent = "KES " + this.formatCurrency(revenue);

    el = document.getElementById("totalExpenses");
    if (el) el.textContent = "KES " + this.formatCurrency(expenseTotal);

    el = document.getElementById("netBalance");
    if (el) el.textContent = "KES " + this.formatCurrency(net);

    el = document.getElementById("pendingApprovals");
    if (el) el.textContent = pendingCount;
  },

  // ── Filtering ────────────────────────────────────────

  applyFilters: function () {
    var search = (document.getElementById("financeSearch") || {}).value || "";
    search = search.toLowerCase();
    var typeFilter =
      (document.getElementById("transactionTypeFilter") || {}).value || "";
    var catFilter =
      (document.getElementById("categoryFilter") || {}).value || "";
    var dateFrom =
      (document.getElementById("dateFromFilter") || {}).value || "";
    var dateTo = (document.getElementById("dateToFilter") || {}).value || "";

    this.filtered = this.transactions.filter(function (t) {
      if (typeFilter && t.type !== typeFilter) return false;
      if (catFilter && t.category !== catFilter) return false;
      if (dateFrom && t.date && new Date(t.date) < new Date(dateFrom))
        return false;
      if (dateTo && t.date && new Date(t.date) > new Date(dateTo)) return false;

      if (search) {
        var hay = (
          t.category +
          " " +
          t.description +
          " " +
          t.type +
          " " +
          t.status
        ).toLowerCase();
        if (hay.indexOf(search) === -1) return false;
      }
      return true;
    });

    this.pagination.total = this.filtered.length;
    this.renderTable();
    this.renderPagination();
    this.populateCategoryFilter();
  },

  populateCategoryFilter: function () {
    var catFilter = document.getElementById("categoryFilter");
    if (!catFilter) return;

    var seen = {};
    this.transactions.forEach(function (t) {
      if (t.category) seen[t.category] = true;
    });

    var categories = Object.keys(seen).sort();
    var current = catFilter.value;
    catFilter.innerHTML = '<option value="">All Categories</option>';
    categories.forEach(function (cat) {
      var opt = document.createElement("option");
      opt.value = cat;
      opt.textContent = cat;
      catFilter.appendChild(opt);
    });
    catFilter.value = current || "";
  },

  // ── Table Rendering ──────────────────────────────────

  renderTable: function () {
    var tbody = document.getElementById("financeTableBody");
    if (!tbody) return;

    if (this.filtered.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center text-muted py-4">' +
        "No transactions found.</td></tr>";
      return;
    }

    var start = (this.pagination.page - 1) * this.pagination.limit;
    var end = Math.min(start + this.pagination.limit, this.filtered.length);
    var pageItems = this.filtered.slice(start, end);
    var self = this;

    tbody.innerHTML = pageItems
      .map(function (t) {
        var amount = self.formatCurrency(t.amount);
        var date = self.formatDate(t.date);
        var typeBadge =
          t.type === "income"
            ? '<span class="badge bg-success">Income</span>'
            : '<span class="badge bg-danger">Expense</span>';
        var statusBadge = self.getStatusBadge(t.status);

        return (
          "<tr>" +
          "<td>" +
          date +
          "</td>" +
          "<td>" +
          typeBadge +
          "</td>" +
          "<td>" +
          self.esc(t.category) +
          "</td>" +
          "<td>" +
          self.esc(t.description) +
          "</td>" +
          '<td class="text-end">KES ' +
          amount +
          "</td>" +
          "<td>" +
          self.esc(String(t.recorded_by || "-")) +
          "</td>" +
          "<td>" +
          statusBadge +
          "</td>" +
          "<td>" +
          '<button class="btn btn-sm btn-outline-primary" onclick="ManageFinanceController.viewTransaction(\'' +
          t.source +
          "', " +
          t.id +
          ')">View</button>' +
          "</td>" +
          "</tr>"
        );
      })
      .join("");

    var info = document.getElementById("paginationInfo");
    if (info) {
      var from = this.filtered.length === 0 ? 0 : start + 1;
      info.textContent =
        "Showing " + from + " to " + end + " of " + this.filtered.length;
    }
  },

  getStatusBadge: function (status) {
    var s = String(status || "").toLowerCase();
    if (s === "approved" || s === "completed" || s === "paid") {
      return '<span class="badge bg-success">Approved</span>';
    }
    if (s === "pending") {
      return '<span class="badge bg-warning">Pending</span>';
    }
    if (s === "rejected") {
      return '<span class="badge bg-danger">Rejected</span>';
    }
    return '<span class="badge bg-secondary">Unknown</span>';
  },

  renderPagination: function () {
    var controls = document.getElementById("paginationControls");
    if (!controls) return;

    var totalPages = Math.max(
      1,
      Math.ceil(this.filtered.length / this.pagination.limit),
    );
    if (totalPages <= 1) {
      controls.innerHTML = "";
      return;
    }

    var html = "";
    for (var i = 1; i <= totalPages; i++) {
      var cls =
        i === this.pagination.page ? "btn-primary" : "btn-outline-secondary";
      html +=
        '<button class="btn btn-sm ' +
        cls +
        ' me-1" onclick="ManageFinanceController.goToPage(' +
        i +
        ')">' +
        i +
        "</button>";
    }
    controls.innerHTML = html;
  },

  goToPage: function (page) {
    var totalPages = Math.ceil(this.filtered.length / this.pagination.limit);
    if (page < 1 || page > totalPages) return;
    this.pagination.page = page;
    this.renderTable();
    this.renderPagination();
  },

  // ── Actions ──────────────────────────────────────────

  viewTransaction: function (source, id) {
    var record = this.transactions.find(function (t) {
      return t.source === source && t.id === id;
    });
    if (!record) return;

    var lines = [
      "Type: " + record.type,
      "Category: " + record.category,
      "Amount: KES " + this.formatCurrency(record.amount),
      "Date: " + this.formatDate(record.date),
      "Status: " + record.status,
      "Description: " + (record.description || "-"),
    ];
    alert(lines.join("\n"));
  },

  exportReport: function () {
    if (!this.filtered.length) {
      this.notify("No data to export", "warning");
      return;
    }

    var self = this;
    var headers = [
      "Date",
      "Type",
      "Category",
      "Description",
      "Amount",
      "Status",
    ];
    var rows = this.filtered.map(function (r) {
      return [
        self.formatDate(r.date),
        r.type,
        r.category,
        (r.description || "").replace(/\n/g, " "),
        r.amount,
        r.status,
      ];
    });

    var csv = [headers]
      .concat(rows)
      .map(function (r) {
        return r.join(",");
      })
      .join("\n");
    var blob = new Blob([csv], { type: "text/csv" });
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download =
      "finance_export_" + new Date().toISOString().split("T")[0] + ".csv";
    a.click();
    window.URL.revokeObjectURL(url);
  },
};

// ── Bootstrap ────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", function () {
  // Only init if the FinanceController (from finance.js) has not already taken over.
  // This avoids double-init when the role-based templates load finance.js.
  if (typeof window.FinanceController === "undefined") {
    ManageFinanceController.init();
  }
});
