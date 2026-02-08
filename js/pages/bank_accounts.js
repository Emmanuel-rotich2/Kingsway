/**
 * Bank Accounts Controller
 * Handles bank account management: add, edit, reconcile
 * Integrates with /api/finance/bank-accounts endpoints
 *
 * @package App\JS\Pages
 */

(function () {
    "use strict";

    const BankAccountsController = {
        // State
        data: [],
        filtered: [],
        currentPage: 1,
        perPage: 15,

        /**
         * Initialize controller
         */
        init: async function () {
            try {
                console.log("Initializing BankAccountsController...");
                await this.loadData();
                console.log("BankAccountsController initialized successfully");
            } catch (error) {
                console.error("Error initializing BankAccountsController:", error);
                this.showNotification("Failed to load bank accounts", "error");
            }
        },

        /**
         * Load data from API
         */
        loadData: async function () {
            try {
                var response = await fetch(API_BASE_URL + "/finance/bank-accounts", {
                    headers: this.getHeaders()
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.data = result.data || result.accounts || [];
                } else {
                    this.data = [];
                }
            } catch (error) {
                console.error("Error loading bank accounts:", error);
                this.data = [];
            }

            this.filtered = [...this.data];
            this.renderStats();
            this.renderTable();
            this.populateBankFilter();
        },

        /**
         * Render KPI stats
         */
        renderStats: function () {
            var total = this.data.length;
            var combinedBalance = this.data.reduce(function (sum, acc) {
                return sum + (parseFloat(acc.balance) || parseFloat(acc.current_balance) || 0);
            }, 0);
            var activeCount = this.data.filter(function (acc) {
                return (acc.status || "").toLowerCase() === "active";
            }).length;

            var lastReconciled = null;
            this.data.forEach(function (acc) {
                if (acc.last_reconciled || acc.last_reconciled_date) {
                    var d = new Date(acc.last_reconciled || acc.last_reconciled_date);
                    if (!lastReconciled || d > lastReconciled) {
                        lastReconciled = d;
                    }
                }
            });

            var el;
            el = document.getElementById("kpiTotalAccounts");
            if (el) el.textContent = total;

            el = document.getElementById("kpiCombinedBalance");
            if (el) el.textContent = "KES " + this.formatCurrency(combinedBalance);

            el = document.getElementById("kpiActiveAccounts");
            if (el) el.textContent = activeCount;

            el = document.getElementById("kpiLastReconciled");
            if (el) el.textContent = lastReconciled ? this.formatDate(lastReconciled) : "N/A";
        },

        /**
         * Render data table
         */
        renderTable: function () {
            var tbody = document.getElementById("bankAccountsTableBody");
            if (!tbody) return;

            if (this.filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4">' +
                    '<i class="fas fa-university fa-3x text-muted mb-3 d-block"></i>' +
                    '<p class="text-muted mb-0">No bank accounts found</p></td></tr>';
                this.updateTableInfo(0);
                this.renderPagination();
                return;
            }

            var start = (this.currentPage - 1) * this.perPage;
            var end = start + this.perPage;
            var pageItems = this.filtered.slice(start, end);
            var self = this;
            var html = "";

            pageItems.forEach(function (acc, index) {
                var statusBadge = self.getStatusBadge(acc.status);
                var typeBadge = (acc.account_type || acc.type || "Current").toLowerCase() === "savings"
                    ? '<span class="badge bg-info">Savings</span>'
                    : '<span class="badge bg-secondary">Current</span>';

                html += '<tr>' +
                    '<td>' + (start + index + 1) + '</td>' +
                    '<td><strong>' + self.escapeHtml(acc.bank_name || "-") + '</strong></td>' +
                    '<td>' + self.escapeHtml(acc.account_name || "-") + '</td>' +
                    '<td><code>' + self.escapeHtml(acc.account_number || "-") + '</code></td>' +
                    '<td class="text-center">' + typeBadge + '</td>' +
                    '<td class="text-end fw-bold">KES ' + self.formatCurrency(acc.balance || acc.current_balance || 0) + '</td>' +
                    '<td class="text-center">' + statusBadge + '</td>' +
                    '<td>' + self.formatDate(acc.last_transaction_date || acc.last_transaction || acc.updated_at) + '</td>' +
                    '<td class="text-center">' +
                        '<div class="btn-group btn-group-sm">' +
                            '<button class="btn btn-outline-primary" onclick="BankAccountsController.viewAccount(' + acc.id + ')" title="View"><i class="fas fa-eye"></i></button>' +
                            '<button class="btn btn-outline-warning" onclick="BankAccountsController.editAccount(' + acc.id + ')" title="Edit"><i class="fas fa-edit"></i></button>' +
                            '<button class="btn btn-outline-danger" onclick="BankAccountsController.deleteAccount(' + acc.id + ')" title="Delete"><i class="fas fa-trash"></i></button>' +
                        '</div>' +
                    '</td>' +
                    '</tr>';
            });

            tbody.innerHTML = html;
            this.updateTableInfo(this.filtered.length);
            this.renderPagination();
        },

        /**
         * Filter data based on current filter values
         */
        filterData: function () {
            var search = (document.getElementById("baSearch")?.value || "").toLowerCase();
            var bankFilter = document.getElementById("baBankFilter")?.value || "";
            var typeFilter = document.getElementById("baTypeFilter")?.value || "";
            var statusFilter = document.getElementById("baStatusFilter")?.value || "";

            this.filtered = this.data.filter(function (acc) {
                if (bankFilter && (acc.bank_name || "") !== bankFilter) return false;
                if (typeFilter && (acc.account_type || acc.type || "") !== typeFilter) return false;
                if (statusFilter && (acc.status || "") !== statusFilter) return false;

                if (search) {
                    var hay = ((acc.bank_name || "") + " " + (acc.account_name || "") + " " +
                        (acc.account_number || "")).toLowerCase();
                    if (hay.indexOf(search) === -1) return false;
                }
                return true;
            });

            this.currentPage = 1;
            this.renderTable();
        },

        /**
         * Clear all filters
         */
        clearFilters: function () {
            var ids = ["baSearch", "baBankFilter", "baTypeFilter", "baStatusFilter"];
            ids.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = "";
            });
            this.filtered = [...this.data];
            this.currentPage = 1;
            this.renderTable();
        },

        /**
         * Populate bank filter dropdown
         */
        populateBankFilter: function () {
            var bankFilter = document.getElementById("baBankFilter");
            if (!bankFilter) return;

            var banks = [];
            var seen = {};
            this.data.forEach(function (acc) {
                var name = acc.bank_name || "";
                if (name && !seen[name]) {
                    seen[name] = true;
                    banks.push(name);
                }
            });
            banks.sort();

            var current = bankFilter.value;
            bankFilter.innerHTML = '<option value="">All Banks</option>';
            banks.forEach(function (b) {
                bankFilter.innerHTML += '<option value="' + b + '">' + b + '</option>';
            });
            bankFilter.value = current || "";
        },

        /**
         * Show create modal
         */
        showCreateModal: function () {
            document.getElementById("bankAccountModalLabel").innerHTML =
                '<i class="fas fa-university me-2"></i> Add Bank Account';
            document.getElementById("bankAccountForm").reset();
            document.getElementById("ba_id").value = "";

            var modal = new bootstrap.Modal(document.getElementById("bankAccountModal"));
            modal.show();
        },

        /**
         * Save account (create or update)
         */
        saveAccount: async function () {
            var form = document.getElementById("bankAccountForm");
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            var payload = {
                id: document.getElementById("ba_id").value || null,
                bank_name: document.getElementById("ba_bank_name").value,
                branch: document.getElementById("ba_branch").value,
                account_name: document.getElementById("ba_account_name").value,
                account_number: document.getElementById("ba_account_number").value,
                account_type: document.getElementById("ba_type").value,
                opening_balance: parseFloat(document.getElementById("ba_opening_balance").value) || 0
            };

            try {
                var url = API_BASE_URL + "/finance/bank-accounts";
                var method = payload.id ? "PUT" : "POST";
                if (payload.id) url += "/" + payload.id;

                var response = await fetch(url, {
                    method: method,
                    headers: this.getHeaders(),
                    body: JSON.stringify(payload)
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.showNotification("Bank account saved successfully", "success");
                    bootstrap.Modal.getInstance(document.getElementById("bankAccountModal")).hide();
                    await this.loadData();
                } else {
                    this.showNotification(result.message || "Failed to save bank account", "error");
                }
            } catch (error) {
                console.error("Error saving bank account:", error);
                this.showNotification("Failed to save bank account", "error");
            }
        },

        /**
         * View account details
         */
        viewAccount: function (id) {
            var acc = this.data.find(function (a) { return a.id === id; });
            if (!acc) return;

            var msg = "Bank: " + (acc.bank_name || "-") + "\n" +
                "Account Name: " + (acc.account_name || "-") + "\n" +
                "Account Number: " + (acc.account_number || "-") + "\n" +
                "Type: " + (acc.account_type || acc.type || "-") + "\n" +
                "Branch: " + (acc.branch || "-") + "\n" +
                "Balance: KES " + this.formatCurrency(acc.balance || acc.current_balance || 0) + "\n" +
                "Status: " + (acc.status || "-");
            alert(msg);
        },

        /**
         * Edit account
         */
        editAccount: function (id) {
            var acc = this.data.find(function (a) { return a.id === id; });
            if (!acc) return;

            document.getElementById("bankAccountModalLabel").innerHTML =
                '<i class="fas fa-university me-2"></i> Edit Bank Account';
            document.getElementById("ba_id").value = acc.id;
            document.getElementById("ba_bank_name").value = acc.bank_name || "";
            document.getElementById("ba_branch").value = acc.branch || "";
            document.getElementById("ba_account_name").value = acc.account_name || "";
            document.getElementById("ba_account_number").value = acc.account_number || "";
            document.getElementById("ba_type").value = acc.account_type || acc.type || "";
            document.getElementById("ba_opening_balance").value = acc.opening_balance || acc.balance || 0;

            var modal = new bootstrap.Modal(document.getElementById("bankAccountModal"));
            modal.show();
        },

        /**
         * Delete account
         */
        deleteAccount: async function (id) {
            if (!confirm("Are you sure you want to delete this bank account?")) return;

            try {
                var response = await fetch(API_BASE_URL + "/finance/bank-accounts/" + id, {
                    method: "DELETE",
                    headers: this.getHeaders()
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.showNotification("Bank account deleted", "success");
                    await this.loadData();
                } else {
                    this.showNotification(result.message || "Failed to delete account", "error");
                }
            } catch (error) {
                console.error("Error deleting bank account:", error);
                this.showNotification("Failed to delete bank account", "error");
            }
        },

        /**
         * Export data as CSV
         */
        exportCSV: function () {
            var headers = ["#", "Bank Name", "Account Name", "Account Number", "Type", "Balance (KES)", "Status", "Last Transaction"];
            var self = this;
            var rows = this.filtered.map(function (acc, i) {
                return [
                    i + 1,
                    (acc.bank_name || "").replace(/,/g, " "),
                    (acc.account_name || "").replace(/,/g, " "),
                    acc.account_number || "",
                    acc.account_type || acc.type || "",
                    acc.balance || acc.current_balance || 0,
                    acc.status || "",
                    self.formatDate(acc.last_transaction_date || acc.last_transaction || acc.updated_at)
                ];
            });

            var csv = [headers.join(",")].concat(rows.map(function (r) { return r.join(","); })).join("\n");
            var blob = new Blob([csv], { type: "text/csv" });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "bank_accounts_" + new Date().toISOString().split("T")[0] + ".csv";
            a.click();
            window.URL.revokeObjectURL(url);
            this.showNotification("Export completed", "success");
        },

        // ====================================================================
        // Pagination
        // ====================================================================

        renderPagination: function () {
            var pagination = document.getElementById("baPagination");
            if (!pagination) return;

            var totalPages = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
            if (totalPages <= 1) {
                pagination.innerHTML = "";
                return;
            }

            var html = "";
            html += '<li class="page-item ' + (this.currentPage === 1 ? "disabled" : "") + '">' +
                '<a class="page-link" href="#" onclick="BankAccountsController.goToPage(' + (this.currentPage - 1) + '); return false;">&laquo;</a></li>';

            for (var i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                    html += '<li class="page-item ' + (i === this.currentPage ? "active" : "") + '">' +
                        '<a class="page-link" href="#" onclick="BankAccountsController.goToPage(' + i + '); return false;">' + i + '</a></li>';
                } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                    html += '<li class="page-item disabled"><a class="page-link">...</a></li>';
                }
            }

            html += '<li class="page-item ' + (this.currentPage === totalPages ? "disabled" : "") + '">' +
                '<a class="page-link" href="#" onclick="BankAccountsController.goToPage(' + (this.currentPage + 1) + '); return false;">&raquo;</a></li>';

            pagination.innerHTML = html;
        },

        goToPage: function (page) {
            var totalPages = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
            if (page >= 1 && page <= totalPages) {
                this.currentPage = page;
                this.renderTable();
            }
        },

        updateTableInfo: function (total) {
            var el = document.getElementById("baTableInfo");
            if (!el) return;
            if (total === 0) {
                el.textContent = "Showing 0 records";
            } else {
                var start = (this.currentPage - 1) * this.perPage + 1;
                var end = Math.min(this.currentPage * this.perPage, total);
                el.textContent = "Showing " + start + " to " + end + " of " + total + " records";
            }
        },

        // ====================================================================
        // Utilities
        // ====================================================================

        getStatusBadge: function (status) {
            var s = (status || "Active").toLowerCase();
            var map = {
                active: '<span class="badge bg-success">Active</span>',
                inactive: '<span class="badge bg-warning text-dark">Inactive</span>',
                closed: '<span class="badge bg-danger">Closed</span>'
            };
            return map[s] || '<span class="badge bg-secondary">' + (status || "Unknown") + '</span>';
        },

        formatCurrency: function (amount) {
            return parseFloat(amount || 0).toLocaleString("en-KE", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        formatDate: function (value) {
            if (!value) return "-";
            var d = new Date(value);
            if (isNaN(d.getTime())) return value;
            return d.toLocaleDateString("en-KE", { year: "numeric", month: "short", day: "numeric" });
        },

        escapeHtml: function (str) {
            if (!str && str !== 0) return "";
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#39;");
        },

        showNotification: function (message, type) {
            if (typeof showNotification === "function") {
                showNotification(message, type || "info");
            } else {
                alert(message);
            }
        },

        getHeaders: function () {
            var headers = { "Content-Type": "application/json" };
            if (typeof AuthContext !== "undefined") {
                var token = AuthContext.getToken ? AuthContext.getToken() : null;
                if (token) headers["Authorization"] = "Bearer " + token;
            }
            return headers;
        }
    };

    window.BankAccountsController = BankAccountsController;
})();
