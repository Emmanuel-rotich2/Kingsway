/**
 * Manage Expenses Page Controller
 * Handles listing, filtering, creating, updating, and approving expenses.
 */

(function () {
  "use strict";

  const ManageExpensesController = {
    data: [],
    filtered: [],
    currentPage: 1,
    perPage: 12,
    expenseModal: null,

    init: async function () {
      if (!this.pageExists()) return;

      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = "/Kingsway/index.php";
        return;
      }

      this.expenseModal = new bootstrap.Modal(document.getElementById("expenseModal"));
      this.bindEvents();
      await this.loadData();
    },

    pageExists: function () {
      return !!document.getElementById("expensesTable");
    },

    bindEvents: function () {
      const self = this;

      this.on("addExpenseBtn", "click", function () {
        self.openModal();
      });

      this.on("saveExpenseBtn", "click", async function () {
        await self.saveExpense();
      });

      this.on("clearFilters", "click", function () {
        self.clearFilters();
      });

      this.on("exportExpensesBtn", "click", function () {
        self.exportCSV();
      });

      ["expenseSearch", "categoryFilter", "statusFilter", "dateFrom", "dateTo"].forEach(function (id) {
        self.on(id, "input", function () {
          self.currentPage = 1;
          self.applyFilters();
        });
        self.on(id, "change", function () {
          self.currentPage = 1;
          self.applyFilters();
        });
      });
    },

    loadData: async function () {
      try {
        const response = await window.API.finance.getTransactions({ type: "expenses", limit: 2000 });
        this.data = this.unwrapList(response, ["expenses", "items", "data"]);
      } catch (error) {
        console.error("Failed to load expenses:", error);
        this.data = [];
        this.notify("Failed to load expenses", "error");
      }

      this.applyFilters();
    },

    applyFilters: function () {
      const search = this.value("expenseSearch").toLowerCase();
      const category = this.value("categoryFilter").toLowerCase();
      const status = this.value("statusFilter").toLowerCase();
      const dateFrom = this.value("dateFrom");
      const dateTo = this.value("dateTo");

      this.filtered = this.data.filter((item) => {
        const itemCategory = String(item.expense_category || "").toLowerCase();
        const itemStatus = String(item.status || "").toLowerCase();
        const itemDate = String(item.expense_date || item.created_at || "").split(" ")[0];

        if (category && itemCategory !== category) return false;
        if (status && itemStatus !== status) return false;
        if (dateFrom && itemDate && itemDate < dateFrom) return false;
        if (dateTo && itemDate && itemDate > dateTo) return false;

        if (search) {
          const hay = [
            item.description,
            item.vendor_name,
            item.receipt_number,
            item.expense_category,
            item.status,
          ]
            .join(" ")
            .toLowerCase();
          if (!hay.includes(search)) return false;
        }

        return true;
      });

      this.renderStats();
      this.renderTable();
      this.renderPagination();
    },

    renderStats: function () {
      const total = this.data.reduce((sum, item) => sum + this.toNumber(item.amount), 0);
      const pending = this.data.filter((item) => String(item.status).toLowerCase() === "pending").length;
      const approved = this.data.filter((item) => String(item.status).toLowerCase() === "approved").length;

      const thisMonthKey = new Date().toISOString().slice(0, 7);
      const monthTotal = this.data
        .filter((item) => String(item.expense_date || item.created_at || "").startsWith(thisMonthKey))
        .reduce((sum, item) => sum + this.toNumber(item.amount), 0);

      this.setText("totalExpenses", `KES ${this.formatCurrency(total)}`);
      this.setText("pendingExpenses", pending);
      this.setText("approvedExpenses", approved);
      this.setText("monthExpenses", `KES ${this.formatCurrency(monthTotal)}`);
    },

    renderTable: function () {
      const tbody = document.querySelector("#expensesTable tbody");
      if (!tbody) return;

      if (!this.filtered.length) {
        tbody.innerHTML =
          '<tr><td colspan="7" class="text-center text-muted py-4">No expense records found.</td></tr>';
        return;
      }

      const start = (this.currentPage - 1) * this.perPage;
      const rows = this.filtered.slice(start, start + this.perPage);

      tbody.innerHTML = rows
        .map((item, i) => {
          const index = start + i;
          const status = String(item.status || "pending").toLowerCase();
          const canEdit = status === "pending" || status === "rejected";

          return `
            <tr>
              <td>${this.formatDate(item.expense_date || item.created_at)}</td>
              <td>${this.escapeHtml(item.expense_category || "-")}</td>
              <td>${this.escapeHtml(item.description || "-")}</td>
              <td>KES ${this.formatCurrency(item.amount)}</td>
              <td>${this.statusBadge(status)}</td>
              <td>${this.escapeHtml(item.recorded_by_name || item.recorded_by || "-")}</td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-primary" data-action="view" data-index="${index}">
                    <i class="bi bi-eye"></i>
                  </button>
                  <button class="btn btn-outline-warning ${canEdit ? "" : "disabled"}" data-action="edit" data-index="${index}">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-outline-success ${status === "pending" ? "" : "disabled"}" data-action="approve" data-index="${index}">
                    <i class="bi bi-check"></i>
                  </button>
                  <button class="btn btn-outline-danger ${status === "pending" ? "" : "disabled"}" data-action="reject" data-index="${index}">
                    <i class="bi bi-x"></i>
                  </button>
                </div>
              </td>
            </tr>
          `;
        })
        .join("");

      const self = this;
      tbody.querySelectorAll("button[data-action]").forEach(function (btn) {
        btn.addEventListener("click", async function () {
          const action = this.getAttribute("data-action");
          const index = Number(this.getAttribute("data-index"));

          if (this.classList.contains("disabled")) return;

          if (action === "view") self.view(index);
          if (action === "edit") self.openModal(self.filtered[index]);
          if (action === "approve") await self.approve(index);
          if (action === "reject") await self.reject(index);
        });
      });
    },

    renderPagination: function () {
      const container = document.getElementById("expensesPagination");
      if (!container) return;

      const totalPages = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
      this.currentPage = Math.min(this.currentPage, totalPages);

      container.innerHTML = `
        <li class="page-item ${this.currentPage === 1 ? "disabled" : ""}">
          <button class="page-link" data-page="prev">Previous</button>
        </li>
        <li class="page-item disabled"><span class="page-link">${this.currentPage} / ${totalPages}</span></li>
        <li class="page-item ${this.currentPage === totalPages ? "disabled" : ""}">
          <button class="page-link" data-page="next">Next</button>
        </li>
      `;

      const self = this;
      container.querySelectorAll("button[data-page]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const dir = this.getAttribute("data-page");
          if (dir === "prev" && self.currentPage > 1) self.currentPage -= 1;
          if (dir === "next" && self.currentPage < totalPages) self.currentPage += 1;
          self.renderTable();
          self.renderPagination();
        });
      });
    },

    openModal: function (item) {
      const form = document.getElementById("expenseForm");
      if (form) form.reset();

      this.setValue("expense_id", item ? item.id : "");
      this.setValue("expense_category", item ? item.expense_category : "");
      this.setValue("expense_amount", item ? item.amount : "");
      this.setValue("expense_date", item ? this.toDate(item.expense_date || item.created_at) : this.toDate(new Date()));
      this.setValue("payment_method", item ? item.payment_method : "cash");
      this.setValue("expense_description", item ? item.description : "");

      this.expenseModal.show();
    },

    saveExpense: async function () {
      const id = this.value("expense_id");
      const payload = {
        type: "expense",
        expense_category: this.value("expense_category"),
        amount: this.toNumber(this.value("expense_amount")),
        expense_date: this.value("expense_date"),
        payment_method: this.normalizePaymentMethod(this.value("payment_method")),
        description: this.value("expense_description").trim(),
        recorded_by: this.currentUserId(),
      };

      if (!payload.expense_category || !payload.amount || !payload.expense_date || !payload.description) {
        this.notify("Category, amount, date, and description are required", "warning");
        return;
      }

      const fileInput = document.getElementById("expense_document");
      if (fileInput && fileInput.files && fileInput.files.length) {
        this.notify("Document uploads for expenses are not wired to an endpoint yet", "info");
      }

      try {
        if (id) {
          await window.API.finance.update(id, payload);
        } else {
          await window.API.finance.create(payload);
        }

        this.expenseModal.hide();
        this.notify("Expense saved successfully", "success");
        await this.loadData();
      } catch (error) {
        console.error("Failed to save expense:", error);
        this.notify(error.message || "Failed to save expense", "error");
      }
    },

    approve: async function (index) {
      const item = this.filtered[index];
      if (!item || !item.id) return;

      const notes = prompt("Approval notes (optional):", "") || "";
      try {
        await window.API.finance.approveExpense(item.id, notes);
        this.notify("Expense approved", "success");
        await this.loadData();
      } catch (error) {
        console.error("Failed to approve expense:", error);
        this.notify(error.message || "Failed to approve expense", "error");
      }
    },

    reject: async function (index) {
      const item = this.filtered[index];
      if (!item || !item.id) return;

      const reason = prompt("Rejection reason:", "") || "Rejected";
      try {
        await window.API.finance.rejectExpense(item.id, reason);
        this.notify("Expense rejected", "success");
        await this.loadData();
      } catch (error) {
        console.error("Failed to reject expense:", error);
        this.notify(error.message || "Failed to reject expense", "error");
      }
    },

    view: function (index) {
      const item = this.filtered[index];
      if (!item) return;

      alert(
        `Category: ${item.expense_category || "-"}\nAmount: KES ${this.formatCurrency(item.amount)}\nStatus: ${item.status || "-"}\nDate: ${this.formatDate(
          item.expense_date || item.created_at
        )}\n\nDescription:\n${item.description || "-"}`
      );
    },

    clearFilters: function () {
      ["expenseSearch", "categoryFilter", "statusFilter", "dateFrom", "dateTo"].forEach((id) =>
        this.setValue(id, "")
      );
      this.currentPage = 1;
      this.applyFilters();
    },

    exportCSV: function () {
      if (!this.filtered.length) {
        this.notify("Nothing to export", "info");
        return;
      }

      const headers = ["Date", "Category", "Description", "Amount", "Status", "Recorded By"];
      const rows = this.filtered.map((item) => [
        item.expense_date || item.created_at || "",
        item.expense_category || "",
        item.description || "",
        item.amount || 0,
        item.status || "",
        item.recorded_by_name || item.recorded_by || "",
      ]);

      const csv = [headers, ...rows]
        .map((row) => row.map((v) => `"${String(v || "").replace(/"/g, '""')}"`).join(","))
        .join("\n");

      const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.download = "expenses.csv";
      link.click();
      URL.revokeObjectURL(link.href);
    },

    normalizePaymentMethod: function (method) {
      const value = String(method || "cash").toLowerCase();
      if (value === "bank_transfer") return "bank";
      return value;
    },

    unwrapList: function (response, keys) {
      if (!response) return [];
      if (Array.isArray(response)) return response;
      if (response.data && Array.isArray(response.data)) return response.data;

      const allKeys = keys || [];
      for (let i = 0; i < allKeys.length; i += 1) {
        const key = allKeys[i];
        if (Array.isArray(response[key])) return response[key];
        if (response.data && Array.isArray(response.data[key])) return response.data[key];
      }
      return [];
    },

    currentUserId: function () {
      const user = typeof AuthContext !== "undefined" ? AuthContext.getUser() : null;
      return (user && (user.id || user.user_id)) || null;
    },

    toNumber: function (value) {
      const number = Number(value);
      return Number.isFinite(number) ? number : 0;
    },

    toDate: function (value) {
      const date = value instanceof Date ? value : new Date(value);
      if (Number.isNaN(date.getTime())) return "";
      return date.toISOString().split("T")[0];
    },

    statusBadge: function (status) {
      const key = String(status || "").toLowerCase();
      const map = {
        pending: "bg-warning text-dark",
        approved: "bg-success",
        paid: "bg-primary",
        rejected: "bg-danger",
      };
      const css = map[key] || "bg-light text-dark border";
      return `<span class="badge ${css}">${this.escapeHtml(key || "unknown")}</span>`;
    },

    value: function (id) {
      const el = document.getElementById(id);
      return el ? String(el.value || "") : "";
    },

    setValue: function (id, value) {
      const el = document.getElementById(id);
      if (el) el.value = value == null ? "" : String(value);
    },

    setText: function (id, value) {
      const el = document.getElementById(id);
      if (el) el.textContent = String(value);
    },

    formatCurrency: function (value) {
      return Number(value || 0).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },

    formatDate: function (value) {
      if (!value) return "-";
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return this.escapeHtml(String(value));
      return date.toLocaleDateString("en-KE", {
        year: "numeric",
        month: "short",
        day: "2-digit",
      });
    },

    on: function (id, event, handler) {
      const el = document.getElementById(id);
      if (el) el.addEventListener(event, handler);
    },

    notify: function (message, type) {
      if (typeof showNotification === "function") {
        showNotification(message, type || "info");
      } else {
        console.log(`${type || "info"}: ${message}`);
      }
    },

    escapeHtml: function (value) {
      return String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
    },
  };

  window.ManageExpensesController = ManageExpensesController;
  document.addEventListener("DOMContentLoaded", function () {
    ManageExpensesController.init();
  });
})();
