/**
 * Petty Cash Controller
 * Handles petty cash management: expenses, top-ups, reconciliation
 * Integrates with /api/finance/petty-cash endpoints
 *
 * @package App\JS\Pages
 */

(function () {
    "use strict";

    const PettyCashController = {
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
                console.log("Initializing PettyCashController...");
                await this.loadData();
                console.log("PettyCashController initialized successfully");
            } catch (error) {
                console.error("Error initializing PettyCashController:", error);
                this.showNotification("Failed to load petty cash records", "error");
            }
        },

        /**
         * Load data from API
         */
        loadData: async function () {
            try {
                var response = await fetch(API_BASE_URL + "/finance/petty-cash", {
                    headers: this.getHeaders()
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.data = result.data || result.transactions || [];
                } else {
                    this.data = [];
                }
            } catch (error) {
                console.error("Error loading petty cash:", error);
                this.data = [];
            }

            this.filtered = [...this.data];
            this.renderStats();
            this.renderTable();
        },

        /**
         * Render KPI stats
         */
        renderStats: function () {
            var now = new Date();
            var currentMonth = now.getMonth();
            var currentYear = now.getFullYear();

            var expensesThisMonth = 0;
            var topupsThisMonth = 0;
            var lastReconciliation = null;

            this.data.forEach(function (record) {
                var recDate = new Date(record.date || record.transaction_date || record.created_at);
                var type = (record.type || record.transaction_type || "").toLowerCase();
                var amount = parseFloat(record.amount) || 0;

                if (recDate.getMonth() === currentMonth && recDate.getFullYear() === currentYear) {
                    if (type === "expense") {
                        expensesThisMonth += amount;
                    } else if (type === "top-up" || type === "topup" || type === "top_up") {
                        topupsThisMonth += amount;
                    }
                }

                if (record.reconciled_at || record.reconciliation_date) {
                    var reconDate = new Date(record.reconciled_at || record.reconciliation_date);
                    if (!lastReconciliation || reconDate > lastReconciliation) {
                        lastReconciliation = reconDate;
                    }
                }
            });

            // Calculate running balance: sum of top-ups minus sum of expenses
            var totalTopups = this.data.reduce(function (sum, r) {
                var type = (r.type || r.transaction_type || "").toLowerCase();
                if (type === "top-up" || type === "topup" || type === "top_up") {
                    return sum + (parseFloat(r.amount) || 0);
                }
                return sum;
            }, 0);
            var totalExpenses = this.data.reduce(function (sum, r) {
                var type = (r.type || r.transaction_type || "").toLowerCase();
                if (type === "expense") {
                    return sum + (parseFloat(r.amount) || 0);
                }
                return sum;
            }, 0);
            var currentBalance = totalTopups - totalExpenses;

            var el;
            el = document.getElementById("kpiCurrentBalance");
            if (el) el.textContent = "KES " + this.formatCurrency(currentBalance);

            el = document.getElementById("kpiExpensesMonth");
            if (el) el.textContent = "KES " + this.formatCurrency(expensesThisMonth);

            el = document.getElementById("kpiTopupsMonth");
            if (el) el.textContent = "KES " + this.formatCurrency(topupsThisMonth);

            el = document.getElementById("kpiLastReconciliation");
            if (el) el.textContent = lastReconciliation ? this.formatDate(lastReconciliation) : "N/A";
        },

        /**
         * Render data table
         */
        renderTable: function () {
            var tbody = document.getElementById("pettyCashTableBody");
            if (!tbody) return;

            if (this.filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4">' +
                    '<i class="fas fa-wallet fa-3x text-muted mb-3 d-block"></i>' +
                    '<p class="text-muted mb-0">No petty cash records found</p></td></tr>';
                this.updateTableInfo(0);
                this.renderPagination();
                return;
            }

            var start = (this.currentPage - 1) * this.perPage;
            var end = start + this.perPage;
            var pageItems = this.filtered.slice(start, end);
            var self = this;
            var html = "";

            pageItems.forEach(function (record, index) {
                var type = record.type || record.transaction_type || "Expense";
                var typeBadge = type.toLowerCase() === "expense"
                    ? '<span class="badge bg-danger">Expense</span>'
                    : '<span class="badge bg-success">Top-up</span>';

                html += '<tr>' +
                    '<td>' + (start + index + 1) + '</td>' +
                    '<td>' + self.formatDate(record.date || record.transaction_date || record.created_at) + '</td>' +
                    '<td>' + self.escapeHtml(record.description || "-") + '</td>' +
                    '<td>' + self.escapeHtml(record.category || "-") + '</td>' +
                    '<td class="text-center">' + typeBadge + '</td>' +
                    '<td class="text-end">KES ' + self.formatCurrency(record.amount) + '</td>' +
                    '<td>' + self.escapeHtml(record.received_by || record.recipient || "-") + '</td>' +
                    '<td>' + self.escapeHtml(record.authorized_by || record.approved_by || "-") + '</td>' +
                    '<td class="text-center">' +
                        '<div class="btn-group btn-group-sm">' +
                            '<button class="btn btn-outline-primary" onclick="PettyCashController.viewRecord(' + record.id + ')" title="View"><i class="fas fa-eye"></i></button>' +
                            '<button class="btn btn-outline-warning" onclick="PettyCashController.editRecord(' + record.id + ')" title="Edit"><i class="fas fa-edit"></i></button>' +
                            '<button class="btn btn-outline-danger" onclick="PettyCashController.deleteRecord(' + record.id + ')" title="Delete"><i class="fas fa-trash"></i></button>' +
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
            var search = (document.getElementById("pcSearch")?.value || "").toLowerCase();
            var categoryFilter = document.getElementById("pcCategoryFilter")?.value || "";
            var typeFilter = document.getElementById("pcTypeFilter")?.value || "";
            var dateFrom = document.getElementById("pcDateFrom")?.value || "";
            var dateTo = document.getElementById("pcDateTo")?.value || "";

            this.filtered = this.data.filter(function (record) {
                var type = record.type || record.transaction_type || "";
                if (typeFilter) {
                    var normalizedType = type.toLowerCase().replace(/[-_]/g, "");
                    var normalizedFilter = typeFilter.toLowerCase().replace(/[-_]/g, "");
                    if (normalizedType !== normalizedFilter) return false;
                }

                if (categoryFilter && (record.category || "") !== categoryFilter) return false;

                var recDate = record.date || record.transaction_date || record.created_at || "";
                if (dateFrom && recDate && new Date(recDate) < new Date(dateFrom)) return false;
                if (dateTo && recDate && new Date(recDate) > new Date(dateTo)) return false;

                if (search) {
                    var hay = ((record.description || "") + " " + (record.category || "") + " " +
                        (record.received_by || "") + " " + (record.authorized_by || "")).toLowerCase();
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
            var ids = ["pcSearch", "pcCategoryFilter", "pcTypeFilter", "pcDateFrom", "pcDateTo"];
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
            document.getElementById("pettyCashModalLabel").innerHTML =
                '<i class="fas fa-wallet me-2"></i> Record Petty Cash Transaction';
            document.getElementById("pettyCashForm").reset();
            document.getElementById("pc_id").value = "";
            document.getElementById("pc_date").value = new Date().toISOString().split("T")[0];

            var modal = new bootstrap.Modal(document.getElementById("pettyCashModal"));
            modal.show();
        },

        /**
         * Save record (create or update)
         */
        saveRecord: async function () {
            var form = document.getElementById("pettyCashForm");
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            var payload = {
                id: document.getElementById("pc_id").value || null,
                date: document.getElementById("pc_date").value,
                type: document.getElementById("pc_type").value,
                description: document.getElementById("pc_description").value,
                category: document.getElementById("pc_category").value,
                amount: parseFloat(document.getElementById("pc_amount").value) || 0,
                received_by: document.getElementById("pc_received_by").value,
                authorized_by: document.getElementById("pc_authorized_by").value,
                notes: document.getElementById("pc_notes").value
            };

            try {
                var url = API_BASE_URL + "/finance/petty-cash";
                var method = payload.id ? "PUT" : "POST";
                if (payload.id) url += "/" + payload.id;

                var response = await fetch(url, {
                    method: method,
                    headers: this.getHeaders(),
                    body: JSON.stringify(payload)
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.showNotification("Petty cash record saved successfully", "success");
                    bootstrap.Modal.getInstance(document.getElementById("pettyCashModal")).hide();
                    await this.loadData();
                } else {
                    this.showNotification(result.message || "Failed to save record", "error");
                }
            } catch (error) {
                console.error("Error saving petty cash record:", error);
                this.showNotification("Failed to save record", "error");
            }
        },

        /**
         * View record details
         */
        viewRecord: function (id) {
            var record = this.data.find(function (r) { return r.id === id; });
            if (!record) return;

            var msg = "Date: " + this.formatDate(record.date || record.transaction_date) + "\n" +
                "Type: " + (record.type || record.transaction_type || "-") + "\n" +
                "Category: " + (record.category || "-") + "\n" +
                "Description: " + (record.description || "-") + "\n" +
                "Amount: KES " + this.formatCurrency(record.amount) + "\n" +
                "Received By: " + (record.received_by || "-") + "\n" +
                "Authorized By: " + (record.authorized_by || "-");
            alert(msg);
        },

        /**
         * Edit record
         */
        editRecord: function (id) {
            var record = this.data.find(function (r) { return r.id === id; });
            if (!record) return;

            document.getElementById("pettyCashModalLabel").innerHTML =
                '<i class="fas fa-wallet me-2"></i> Edit Petty Cash Record';
            document.getElementById("pc_id").value = record.id;
            document.getElementById("pc_date").value = (record.date || record.transaction_date || "").split("T")[0];
            document.getElementById("pc_type").value = record.type || record.transaction_type || "";
            document.getElementById("pc_description").value = record.description || "";
            document.getElementById("pc_category").value = record.category || "";
            document.getElementById("pc_amount").value = record.amount || 0;
            document.getElementById("pc_received_by").value = record.received_by || record.recipient || "";
            document.getElementById("pc_authorized_by").value = record.authorized_by || record.approved_by || "";
            document.getElementById("pc_notes").value = record.notes || "";

            var modal = new bootstrap.Modal(document.getElementById("pettyCashModal"));
            modal.show();
        },

        /**
         * Delete record
         */
        deleteRecord: async function (id) {
            if (!confirm("Are you sure you want to delete this petty cash record?")) return;

            try {
                var response = await fetch(API_BASE_URL + "/finance/petty-cash/" + id, {
                    method: "DELETE",
                    headers: this.getHeaders()
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.showNotification("Record deleted successfully", "success");
                    await this.loadData();
                } else {
                    this.showNotification(result.message || "Failed to delete record", "error");
                }
            } catch (error) {
                console.error("Error deleting petty cash record:", error);
                this.showNotification("Failed to delete record", "error");
            }
        },

        /**
         * Export data as CSV
         */
        exportCSV: function () {
            var headers = ["#", "Date", "Description", "Category", "Type", "Amount (KES)", "Received By", "Authorized By"];
            var self = this;
            var rows = this.filtered.map(function (r, i) {
                return [
                    i + 1,
                    self.formatDate(r.date || r.transaction_date || r.created_at),
                    (r.description || "").replace(/,/g, " "),
                    r.category || "",
                    r.type || r.transaction_type || "",
                    r.amount || 0,
                    (r.received_by || "").replace(/,/g, " "),
                    (r.authorized_by || "").replace(/,/g, " ")
                ];
            });

            var csv = [headers.join(",")].concat(rows.map(function (r) { return r.join(","); })).join("\n");
            var blob = new Blob([csv], { type: "text/csv" });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "petty_cash_" + new Date().toISOString().split("T")[0] + ".csv";
            a.click();
            window.URL.revokeObjectURL(url);
            this.showNotification("Export completed", "success");
        },

        // ====================================================================
        // Pagination
        // ====================================================================

        renderPagination: function () {
            var pagination = document.getElementById("pcPagination");
            if (!pagination) return;

            var totalPages = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
            if (totalPages <= 1) {
                pagination.innerHTML = "";
                return;
            }

            var html = "";
            html += '<li class="page-item ' + (this.currentPage === 1 ? "disabled" : "") + '">' +
                '<a class="page-link" href="#" onclick="PettyCashController.goToPage(' + (this.currentPage - 1) + '); return false;">&laquo;</a></li>';

            for (var i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                    html += '<li class="page-item ' + (i === this.currentPage ? "active" : "") + '">' +
                        '<a class="page-link" href="#" onclick="PettyCashController.goToPage(' + i + '); return false;">' + i + '</a></li>';
                } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                    html += '<li class="page-item disabled"><a class="page-link">...</a></li>';
                }
            }

            html += '<li class="page-item ' + (this.currentPage === totalPages ? "disabled" : "") + '">' +
                '<a class="page-link" href="#" onclick="PettyCashController.goToPage(' + (this.currentPage + 1) + '); return false;">&raquo;</a></li>';

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
            var el = document.getElementById("pcTableInfo");
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

    window.PettyCashController = PettyCashController;
})();
