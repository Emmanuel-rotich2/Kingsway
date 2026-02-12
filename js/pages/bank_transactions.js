/**
 * Bank Transactions Controller
 * Handles bank transaction management: record credits/debits, reconcile
 * Integrates with /api/finance/bank-transactions endpoints
 *
 * @package App\JS\Pages
 */

(function () {
    "use strict";

    const BankTransactionsController = {
        // State
        data: [],
        filtered: [],
        accounts: [],
        currentPage: 1,
        perPage: 15,

        /**
         * Initialize controller
         */
        init: async function () {
            try {
                console.log("Initializing BankTransactionsController...");
                await Promise.all([
                    this.loadData(),
                    this.loadAccounts()
                ]);
                console.log("BankTransactionsController initialized successfully");
            } catch (error) {
                console.error("Error initializing BankTransactionsController:", error);
                this.showNotification("Failed to load bank transactions", "error");
            }
        },

        /**
         * Load transaction data from API
         */
        loadData: async function () {
            try {
                var response = await fetch(API_BASE_URL + "/finance/bank-transactions", {
                    headers: this.getHeaders()
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.data = result.data || result.transactions || [];
                } else {
                    this.data = [];
                }
            } catch (error) {
                console.error("Error loading bank transactions:", error);
                this.data = [];
            }

            this.filtered = [...this.data];
            this.renderStats();
            this.renderTable();
        },

        /**
         * Load bank accounts for dropdowns
         */
        loadAccounts: async function () {
            try {
                var response = await fetch(API_BASE_URL + "/finance/bank-accounts", {
                    headers: this.getHeaders()
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.accounts = result.data || result.accounts || [];
                } else {
                    this.accounts = [];
                }
            } catch (error) {
                console.error("Error loading accounts:", error);
                this.accounts = [];
            }

            this.populateAccountFilters();
        },

        /**
         * Populate account filter and modal dropdowns
         */
        populateAccountFilters: function () {
            var filterSelect = document.getElementById("btAccountFilter");
            var modalSelect = document.getElementById("bt_account");

            var options = '<option value="">All Accounts</option>';
            var modalOptions = '<option value="">Select Account</option>';

            this.accounts.forEach(function (acc) {
                var label = (acc.bank_name || "") + " - " + (acc.account_name || acc.account_number || "");
                options += '<option value="' + acc.id + '">' + label + '</option>';
                modalOptions += '<option value="' + acc.id + '">' + label + '</option>';
            });

            if (filterSelect) filterSelect.innerHTML = options;
            if (modalSelect) modalSelect.innerHTML = modalOptions;
        },

        /**
         * Render KPI stats
         */
        renderStats: function () {
            var totalCount = this.data.length;
            var credits = 0;
            var debits = 0;
            var unreconciled = 0;

            this.data.forEach(function (txn) {
                var amount = parseFloat(txn.amount) || 0;
                var type = (txn.type || txn.transaction_type || "").toLowerCase();

                if (type === "credit") {
                    credits += amount;
                } else if (type === "debit") {
                    debits += amount;
                }

                var reconciled = txn.reconciled || txn.is_reconciled || false;
                if (!reconciled || reconciled === "No" || reconciled === "0" || reconciled === false) {
                    unreconciled++;
                }
            });

            var el;
            el = document.getElementById("kpiTotalTransactions");
            if (el) el.textContent = totalCount;

            el = document.getElementById("kpiCredits");
            if (el) el.textContent = "KES " + this.formatCurrency(credits);

            el = document.getElementById("kpiDebits");
            if (el) el.textContent = "KES " + this.formatCurrency(debits);

            el = document.getElementById("kpiUnreconciled");
            if (el) el.textContent = unreconciled;
        },

        /**
         * Render data table
         */
        renderTable: function () {
            var tbody = document.getElementById("bankTransactionsTableBody");
            if (!tbody) return;

            if (this.filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4">' +
                    '<i class="fas fa-exchange-alt fa-3x text-muted mb-3 d-block"></i>' +
                    '<p class="text-muted mb-0">No bank transactions found</p></td></tr>';
                this.updateTableInfo(0);
                this.renderPagination();
                return;
            }

            var start = (this.currentPage - 1) * this.perPage;
            var end = start + this.perPage;
            var pageItems = this.filtered.slice(start, end);
            var self = this;
            var html = "";

            pageItems.forEach(function (txn, index) {
                var type = txn.type || txn.transaction_type || "Credit";
                var typeBadge = type.toLowerCase() === "credit"
                    ? '<span class="badge bg-success">Credit</span>'
                    : '<span class="badge bg-danger">Debit</span>';

                var reconciled = txn.reconciled || txn.is_reconciled || false;
                var isReconciled = reconciled === true || reconciled === "Yes" || reconciled === "1" || reconciled === 1;
                var reconciledBadge = isReconciled
                    ? '<span class="badge bg-success"><i class="fas fa-check"></i> Yes</span>'
                    : '<span class="badge bg-warning text-dark"><i class="fas fa-times"></i> No</span>';

                var accountName = txn.account_name || txn.bank_account || "-";
                if (!accountName || accountName === "-") {
                    var acc = self.accounts.find(function (a) { return a.id == txn.account_id; });
                    if (acc) accountName = (acc.bank_name || "") + " - " + (acc.account_name || "");
                }

                html += '<tr>' +
                    '<td>' + (start + index + 1) + '</td>' +
                    '<td>' + self.formatDate(txn.date || txn.transaction_date || txn.created_at) + '</td>' +
                    '<td>' + self.escapeHtml(accountName) + '</td>' +
                    '<td><code>' + self.escapeHtml(txn.reference || txn.reference_no || "-") + '</code></td>' +
                    '<td>' + self.escapeHtml(txn.description || "-") + '</td>' +
                    '<td class="text-center">' + typeBadge + '</td>' +
                    '<td class="text-end fw-bold">KES ' + self.formatCurrency(txn.amount) + '</td>' +
                    '<td class="text-end">KES ' + self.formatCurrency(txn.balance_after || txn.running_balance || 0) + '</td>' +
                    '<td class="text-center">' + reconciledBadge + '</td>' +
                    '<td class="text-center">' +
                        '<div class="btn-group btn-group-sm">' +
                            '<button class="btn btn-outline-primary" onclick="BankTransactionsController.viewTransaction(' + txn.id + ')" title="View"><i class="fas fa-eye"></i></button>' +
                            (!isReconciled ?
                                '<button class="btn btn-outline-success" onclick="BankTransactionsController.reconcile(' + txn.id + ')" title="Reconcile"><i class="fas fa-check-double"></i></button>'
                                : '') +
                            '<button class="btn btn-outline-danger" onclick="BankTransactionsController.deleteTransaction(' + txn.id + ')" title="Delete"><i class="fas fa-trash"></i></button>' +
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
            var search = (document.getElementById("btSearch")?.value || "").toLowerCase();
            var accountFilter = document.getElementById("btAccountFilter")?.value || "";
            var typeFilter = document.getElementById("btTypeFilter")?.value || "";
            var reconciledFilter = document.getElementById("btReconciledFilter")?.value || "";
            var dateFrom = document.getElementById("btDateFrom")?.value || "";
            var dateTo = document.getElementById("btDateTo")?.value || "";

            this.filtered = this.data.filter(function (txn) {
                if (accountFilter && String(txn.account_id) !== accountFilter) return false;

                var type = (txn.type || txn.transaction_type || "").toLowerCase();
                if (typeFilter && type !== typeFilter.toLowerCase()) return false;

                if (reconciledFilter) {
                    var reconciled = txn.reconciled || txn.is_reconciled || false;
                    var isReconciled = reconciled === true || reconciled === "Yes" || reconciled === "1" || reconciled === 1;
                    if (reconciledFilter === "Yes" && !isReconciled) return false;
                    if (reconciledFilter === "No" && isReconciled) return false;
                }

                var txnDate = txn.date || txn.transaction_date || txn.created_at || "";
                if (dateFrom && txnDate && new Date(txnDate) < new Date(dateFrom)) return false;
                if (dateTo && txnDate && new Date(txnDate) > new Date(dateTo)) return false;

                if (search) {
                    var hay = ((txn.reference || "") + " " + (txn.description || "") + " " +
                        (txn.account_name || "")).toLowerCase();
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
            var ids = ["btSearch", "btAccountFilter", "btTypeFilter", "btReconciledFilter", "btDateFrom", "btDateTo"];
            ids.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = "";
            });
            this.filtered = [...this.data];
            this.currentPage = 1;
            this.renderTable();
        },

        /**
         * Show create modal
         */
        showCreateModal: function () {
            document.getElementById("bankTransactionModalLabel").innerHTML =
                '<i class="fas fa-exchange-alt me-2"></i> Add Bank Transaction';
            document.getElementById("bankTransactionForm").reset();
            document.getElementById("bt_id").value = "";
            document.getElementById("bt_date").value = new Date().toISOString().split("T")[0];

            var modal = new bootstrap.Modal(document.getElementById("bankTransactionModal"));
            modal.show();
        },

        /**
         * Save transaction (create or update)
         */
        saveTransaction: async function () {
            var form = document.getElementById("bankTransactionForm");
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            var payload = {
                id: document.getElementById("bt_id").value || null,
                account_id: document.getElementById("bt_account").value,
                date: document.getElementById("bt_date").value,
                reference: document.getElementById("bt_reference").value,
                type: document.getElementById("bt_type").value,
                description: document.getElementById("bt_description").value,
                amount: parseFloat(document.getElementById("bt_amount").value) || 0
            };

            try {
                var url = API_BASE_URL + "/finance/bank-transactions";
                var method = payload.id ? "PUT" : "POST";
                if (payload.id) url += "/" + payload.id;

                var response = await fetch(url, {
                    method: method,
                    headers: this.getHeaders(),
                    body: JSON.stringify(payload)
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.showNotification("Transaction saved successfully", "success");
                    bootstrap.Modal.getInstance(document.getElementById("bankTransactionModal")).hide();
                    await this.loadData();
                } else {
                    this.showNotification(result.message || "Failed to save transaction", "error");
                }
            } catch (error) {
                console.error("Error saving transaction:", error);
                this.showNotification("Failed to save transaction", "error");
            }
        },

        /**
         * View transaction details
         */
        viewTransaction: function (id) {
            var txn = this.data.find(function (t) { return t.id === id; });
            if (!txn) return;

            var msg = "Date: " + this.formatDate(txn.date || txn.transaction_date) + "\n" +
                "Account: " + (txn.account_name || txn.bank_account || "-") + "\n" +
                "Reference: " + (txn.reference || txn.reference_no || "-") + "\n" +
                "Type: " + (txn.type || txn.transaction_type || "-") + "\n" +
                "Description: " + (txn.description || "-") + "\n" +
                "Amount: KES " + this.formatCurrency(txn.amount) + "\n" +
                "Balance After: KES " + this.formatCurrency(txn.balance_after || txn.running_balance || 0) + "\n" +
                "Reconciled: " + (txn.reconciled ? "Yes" : "No");
            alert(msg);
        },

        /**
         * Reconcile a transaction
         */
        reconcile: async function (id) {
            if (!confirm("Mark this transaction as reconciled?")) return;

            try {
                var response = await fetch(API_BASE_URL + "/finance/bank-transactions/" + id + "/reconcile", {
                    method: "PUT",
                    headers: this.getHeaders(),
                    body: JSON.stringify({ reconciled: true })
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.showNotification("Transaction reconciled", "success");
                    await this.loadData();
                } else {
                    this.showNotification(result.message || "Failed to reconcile", "error");
                }
            } catch (error) {
                console.error("Error reconciling transaction:", error);
                this.showNotification("Failed to reconcile transaction", "error");
            }
        },

        /**
         * Delete transaction
         */
        deleteTransaction: async function (id) {
            if (!confirm("Are you sure you want to delete this transaction?")) return;

            try {
                var response = await fetch(API_BASE_URL + "/finance/bank-transactions/" + id, {
                    method: "DELETE",
                    headers: this.getHeaders()
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.showNotification("Transaction deleted", "success");
                    await this.loadData();
                } else {
                    this.showNotification(result.message || "Failed to delete transaction", "error");
                }
            } catch (error) {
                console.error("Error deleting transaction:", error);
                this.showNotification("Failed to delete transaction", "error");
            }
        },

        /**
         * Export data as CSV
         */
        exportCSV: function () {
            var headers = ["#", "Date", "Account", "Reference", "Description", "Type", "Amount (KES)", "Balance After (KES)", "Reconciled"];
            var self = this;
            var rows = this.filtered.map(function (txn, i) {
                return [
                    i + 1,
                    self.formatDate(txn.date || txn.transaction_date || txn.created_at),
                    (txn.account_name || txn.bank_account || "").replace(/,/g, " "),
                    txn.reference || txn.reference_no || "",
                    (txn.description || "").replace(/,/g, " "),
                    txn.type || txn.transaction_type || "",
                    txn.amount || 0,
                    txn.balance_after || txn.running_balance || 0,
                    txn.reconciled ? "Yes" : "No"
                ];
            });

            var csv = [headers.join(",")].concat(rows.map(function (r) { return r.join(","); })).join("\n");
            var blob = new Blob([csv], { type: "text/csv" });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "bank_transactions_" + new Date().toISOString().split("T")[0] + ".csv";
            a.click();
            window.URL.revokeObjectURL(url);
            this.showNotification("Export completed", "success");
        },

        // ====================================================================
        // Pagination
        // ====================================================================

        renderPagination: function () {
            var pagination = document.getElementById("btPagination");
            if (!pagination) return;

            var totalPages = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
            if (totalPages <= 1) {
                pagination.innerHTML = "";
                return;
            }

            var html = "";
            html += '<li class="page-item ' + (this.currentPage === 1 ? "disabled" : "") + '">' +
                '<a class="page-link" href="#" onclick="BankTransactionsController.goToPage(' + (this.currentPage - 1) + '); return false;">&laquo;</a></li>';

            for (var i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                    html += '<li class="page-item ' + (i === this.currentPage ? "active" : "") + '">' +
                        '<a class="page-link" href="#" onclick="BankTransactionsController.goToPage(' + i + '); return false;">' + i + '</a></li>';
                } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                    html += '<li class="page-item disabled"><a class="page-link">...</a></li>';
                }
            }

            html += '<li class="page-item ' + (this.currentPage === totalPages ? "disabled" : "") + '">' +
                '<a class="page-link" href="#" onclick="BankTransactionsController.goToPage(' + (this.currentPage + 1) + '); return false;">&raquo;</a></li>';

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
            var el = document.getElementById("btTableInfo");
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

    window.BankTransactionsController = BankTransactionsController;
})();
