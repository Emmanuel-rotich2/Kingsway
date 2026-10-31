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
            if (window.AuthContext?.ready) await window.AuthContext.ready();
            try {
                await this.loadData();
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
                var response = await API.callAPI("/accounts/bank-accounts", "GET");
                // The bank-account endpoint returns { bank_accounts: [...] }.
                // Keep the page read-only and never mix in collection routes or
                // other financial-account kinds.
                this.data = Array.isArray(response)
                    ? response
                    : (Array.isArray(response?.bank_accounts) ? response.bank_accounts : []);
                if (!Array.isArray(this.data)) this.data = [];
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
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4">' +
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

                html += '<tr>' +
                    '<td>' + (start + index + 1) + '</td>' +
                    '<td><strong>' + self.escapeHtml(acc.bank_name || "-") + '</strong></td>' +
                    '<td>' + self.escapeHtml(acc.account_name || "-") + '</td>' +
                    '<td><code>' + self.escapeHtml(acc.account_number || "-") + '</code></td>' +
                    '<td class="text-end fw-bold">KES ' + self.formatCurrency(acc.balance || acc.current_balance || 0) + '</td>' +
                    '<td class="text-center">' + statusBadge + '</td>' +
                    '<td>' + self.formatDate(acc.last_transaction_date || acc.last_transaction || acc.updated_at) + '</td>' +
                    '<td class="text-center"><button class="btn btn-sm btn-outline-primary" onclick="BankAccountsController.viewAccount(' + acc.id + ')" title="View bank account"><i class="fas fa-eye me-1"></i>View</button></td>' +
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
            var statusFilter = document.getElementById("baStatusFilter")?.value || "";

            this.filtered = this.data.filter(function (acc) {
                if (bankFilter && (acc.bank_name || "") !== bankFilter) return false;
                if (statusFilter && (acc.status || "").toLowerCase() !== statusFilter.toLowerCase()) return false;

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
            var ids = ["baSearch", "baBankFilter", "baStatusFilter"];
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
         * View account details
         */
        viewAccount: async function (id) {
            var acc = this.data.find(function (a) { return a.id === id; });
            if (!acc) return;

            var msg = "Bank: " + (acc.bank_name || "-") + "\n" +
                "Account Name: " + (acc.account_name || "-") + "\n" +
                "Account Number: " + (acc.account_number || "-") + "\n" +
                "Branch: " + (acc.branch || "-") + "\n" +
                "Balance: KES " + this.formatCurrency(acc.balance || acc.current_balance || 0) + "\n" +
                "Status: " + (acc.status || "-");
            await window.infoDialog('Notice', msg);
        },

        /**
         * Export data as CSV
         */
        exportCSV: function () {
            var headers = ["#", "Bank Name", "Account Name", "Account Number", "Balance (KES)", "Status", "Last Transaction"];
            var self = this;
            var rows = this.filtered.map(function (acc, i) {
                return [
                    i + 1,
                    (acc.bank_name || "").replace(/,/g, " "),
                    (acc.account_name || "").replace(/,/g, " "),
                    acc.account_number || "",
                    acc.balance || acc.current_balance || 0,
                    acc.status || "",
                    self.formatDate(acc.last_transaction_date || acc.last_transaction || acc.updated_at)
                ];
            });

            var csv = [headers.join(",")].concat(rows.map(function (r) { return r.join(","); })).join("\n");
            KingswayFileLifecycle.exportText(csv, "bank_accounts_" + new Date().toISOString().split("T")[0] + ".csv", "text/csv");
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
                pending_verification: '<span class="badge bg-warning text-dark">Pending verification</span>',
                suspended: '<span class="badge bg-warning text-dark">Suspended</span>',
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

        showNotification: async function (message, type) {
            if (typeof showNotification === "function") {
                showNotification(message, type || "info");
            } else {
                await window.infoDialog('Notice', message);
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

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => BankAccountsController.init().catch(() => {}));
    } else {
        BankAccountsController.init().catch(() => {});
    }
})();
