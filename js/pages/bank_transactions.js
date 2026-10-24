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
            if (window.AuthContext?.ready) await window.AuthContext.ready();
            try {
                await Promise.all([
                    this.loadData(),
                    this.loadAccounts()
                ]);
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
                var response = await API.callAPI("/accounts/bank-transactions", "GET");
                this.data = (response && response.transactions) ? response.transactions : [];
                if (!Array.isArray(this.data)) this.data = [];
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
                var response = await API.callAPI("/accounts/bank-accounts", "GET");
                this.accounts = (response && response.bank_accounts) ? response.bank_accounts : [];
                if (!Array.isArray(this.accounts)) this.accounts = [];
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
                var label = (acc.bank_name || "") + " - " + (acc.name || acc.account_no || "");
                var filterValue = acc.account_no || acc.id;
                options += '<option value="' + filterValue + '">' + label + '</option>';
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
            var unreconciled = 0;

            this.data.forEach(function (txn) {
                var amount = parseFloat(txn.amount) || 0;
                credits += amount;

                if (!txn.reconciled) {
                    unreconciled++;
                }
            });

            var el;
            el = document.getElementById("kpiTotalTransactions");
            if (el) el.textContent = totalCount;

            el = document.getElementById("kpiCredits");
            if (el) el.textContent = "KES " + this.formatCurrency(credits);

            el = document.getElementById("kpiDebits");
            if (el) el.textContent = "KES 0.00";

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
                var status = txn.status || "pending";
                var statusBadge = '<span class="badge bg-' + self.statusColor(status) + '">' +
                    self.escapeHtml(status.charAt(0).toUpperCase() + status.slice(1)) + '</span>';

                var isReconciled = !!txn.reconciled;
                var reconciledBadge = isReconciled
                    ? '<span class="badge bg-success"><i class="fas fa-check"></i> Yes</span>'
                    : '<span class="badge bg-warning text-dark"><i class="fas fa-times"></i> No</span>';

                var accountName = (txn.bank_name || "") + " - " + (txn.account_number || "");
                var depositChannel = txn.deposit_channel || 'N/A';

                html += '<tr>' +
                    '<td>' + (start + index + 1) + '</td>' +
                    '<td>' + self.formatDate(txn.transaction_date || txn.created_at) + '</td>' +
                    '<td>' + self.escapeHtml(accountName) + '</td>' +
                    '<td><code>' + self.escapeHtml(txn.transaction_ref || "-") + '</code></td>' +
                    '<td>' + self.escapeHtml(txn.narration || "-") + '</td>' +
                    '<td class="text-center">' + self.escapeHtml(txn.deposit_channel || 'N/A') + '</td>' +
                    '<td class="text-center">' + statusBadge + '</td>' +
                    '<td class="text-end fw-bold">KES ' + self.formatCurrency(txn.amount) + '</td>' +
                    '<td class="text-end text-muted">-</td>' +
                    '<td class="text-center">' + reconciledBadge + '</td>' +
                    '<td class="text-center">' +
                        '<div class="btn-group btn-group-sm">' +
                            '<button class="btn btn-outline-primary" onclick="BankTransactionsController.viewTransaction(' + txn.id + ')" title="View"><i class="fas fa-eye"></i></button>' +
                            '<button class="btn btn-outline-info" onclick="BankTransactionsController.viewJournal(' + txn.id + ')" title="View Journal"><i class="fas fa-book"></i></button>' +
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
                if (accountFilter) {
                    var txnAccount = txn.account_number || txn.bank_name || "";
                    if (String(txnAccount) !== accountFilter) return false;
                }

                var status = (txn.status || "").toLowerCase();
                if (typeFilter && status !== typeFilter.toLowerCase()) return false;

                if (reconciledFilter) {
                    var isReconciled = !!txn.reconciled;
                    if (reconciledFilter === "Yes" && !isReconciled) return false;
                    if (reconciledFilter === "No" && isReconciled) return false;
                }

                var txnDate = txn.transaction_date || txn.created_at || "";
                if (dateFrom && txnDate && new Date(txnDate) < new Date(dateFrom)) return false;
                if (dateTo && txnDate && new Date(txnDate) > new Date(dateTo)) return false;

                if (search) {
                    var hay = ((txn.transaction_ref || "") + " " + (txn.narration || "") + " " +
                        (txn.bank_name || "") + " " + (txn.account_number || "")).toLowerCase();
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
                status: document.getElementById("bt_status").value || "pending",
                description: document.getElementById("bt_description").value,
                amount: parseFloat(document.getElementById("bt_amount").value) || 0
            };

            try {
                var endpoint = "/accounts/bank-transactions";
                var method = payload.id ? "PUT" : "POST";
                if (payload.id) endpoint += "/" + payload.id;

                await API.callAPI(endpoint, method, payload);

                this.showNotification("Transaction saved successfully", "success");
                bootstrap.Modal.getInstance(document.getElementById("bankTransactionModal")).hide();
                await this.loadData();
            } catch (error) {
                console.error("Error saving transaction:", error);
                this.showNotification("Failed to save transaction", "error");
            }
        },

        /**
         * View transaction details
         */
        viewTransaction: async function (id) {
            var txn = this.data.find(function (t) { return t.id === id; });
            if (!txn) return;

            var msg = "Date: " + this.formatDate(txn.transaction_date || txn.created_at) + "\n" +
                "Account: " + ((txn.bank_name || "") + " - " + (txn.account_number || "-")) + "\n" +
                "Reference: " + (txn.transaction_ref || "-") + "\n" +
                "Deposit Channel: " + (txn.deposit_channel || "N/A") + "\n" +
                "Status: " + (txn.status || "-") + "\n" +
                "Description: " + (txn.narration || "-") + "\n" +
                "Amount: KES " + this.formatCurrency(txn.amount) + "\n" +
                "Reconciled: " + (txn.reconciled ? "Yes" : "No");
            await window.infoDialog('Notice', msg);
        },

        viewJournal: async function (id) {
            var txn = this.data.find(function (t) { return t.id === id; });
            if (!txn) return;

            try {
                var response = await API.callAPI("/accounts/bank-transactions/" + id + "/journal", "GET");
                var journal = (response && response.journal) ? response.journal : [];

                if (!journal.length) {
                    await window.infoDialog('Journal', 'No journal entries found for this transaction.');
                    return;
                }

                var lines = journal.map(function (line) {
                    var direction = line.debit > 0 ? 'Dr' : 'Cr';
                    var amount = parseFloat(line.debit || line.credit || 0).toLocaleString("en-KE", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    return direction + ' ' + line.account_code + ' - ' + line.description + ' (' + amount + ')';
                }).join('\n');

                var msg = 'Journal for Transaction: ' + (txn.transaction_ref || '-') + '\n' +
                    'Date: ' + this.formatDate(txn.transaction_date || txn.created_at) + '\n' +
                    'Amount: KES ' + this.formatCurrency(txn.amount) + '\n\n' +
                    'Journal Lines:\n' + lines;

                await window.infoDialog('Transaction Journal', msg);
            } catch (error) {
                console.error("Error loading journal:", error);
                this.showNotification("Failed to load journal entries", "error");
            }
        },

        /**
         * Reconcile a transaction
         */
        reconcile: async function (id) {
            if (!(await window.confirmAction('Confirm', "Mark this transaction as reconciled?"))) return;

            try {
                await API.callAPI("/accounts/bank-transactions/" + id, "PUT", { reconciled: true });

                this.showNotification("Transaction reconciled", "success");
                await this.loadData();
            } catch (error) {
                console.error("Error reconciling transaction:", error);
                this.showNotification("Failed to reconcile transaction", "error");
            }
        },

        /**
         * Delete transaction
         */
        deleteTransaction: async function (id) {
            if (!(await window.confirmAction('Confirm Deletion', "Are you sure you want to delete this transaction?", { confirmText: 'Delete', danger: true }))) return;

            try {
                await API.callAPI("/accounts/bank-transactions/" + id, "DELETE");

                this.showNotification("Transaction deleted", "success");
                await this.loadData();
            } catch (error) {
                console.error("Error deleting transaction:", error);
                this.showNotification("Failed to delete transaction", "error");
            }
        },

        /**
         * Export data as CSV
         */
        exportCSV: function () {
            var headers = ["#", "Date", "Account", "Reference", "Description", "Deposit Channel", "Status", "Amount (KES)", "Reconciled"];
            var self = this;
            var rows = this.filtered.map(function (txn, i) {
                return [
                    i + 1,
                    self.formatDate(txn.transaction_date || txn.created_at),
                    ((txn.bank_name || "") + " - " + (txn.account_number || "")).replace(/,/g, " "),
                    txn.transaction_ref || "",
                    (txn.narration || "").replace(/,/g, " "),
                    txn.deposit_channel || "N/A",
                    txn.status || "",
                    txn.amount || 0,
                    txn.reconciled ? "Yes" : "No"
                ];
            });

            var csv = [headers.join(",")].concat(rows.map(function (r) { return r.join(","); })).join("\n");
            KingswayFileLifecycle.exportText(csv, "bank_transactions_" + new Date().toISOString().split("T")[0] + ".csv", "text/csv");
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

        statusColor: function (status) {
            status = (status || "").toLowerCase();
            if (status === "processed") return "success";
            if (status === "failed") return "danger";
            return "warning text-dark";
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

    window.BankTransactionsController = BankTransactionsController;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => BankTransactionsController.init().catch(() => {}));
    } else {
        BankTransactionsController.init().catch(() => {});
    }
})();
