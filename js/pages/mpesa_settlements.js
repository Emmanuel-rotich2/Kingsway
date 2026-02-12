/**
 * M-Pesa Settlements Controller
 * Handles M-Pesa settlement viewing: list, details, transaction breakdowns
 * Integrates with /api/finance/mpesa-settlements endpoints
 *
 * @package App\JS\Pages
 */

(function () {
    "use strict";

    const MpesaSettlementsController = {
        // State
        data: [],
        filtered: [],
        currentPage: 1,
        perPage: 15,
        currentSettlement: null,

        /**
         * Initialize controller
         */
        init: async function () {
            try {
                console.log("Initializing MpesaSettlementsController...");
                await this.loadData();
                console.log("MpesaSettlementsController initialized successfully");
            } catch (error) {
                console.error("Error initializing MpesaSettlementsController:", error);
                this.showNotification("Failed to load M-Pesa settlements", "error");
            }
        },

        /**
         * Load data from API
         */
        loadData: async function () {
            try {
                var response = await fetch(API_BASE_URL + "/finance/mpesa-settlements", {
                    headers: this.getHeaders()
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.data = result.data || result.settlements || [];
                } else {
                    this.data = [];
                }
            } catch (error) {
                console.error("Error loading M-Pesa settlements:", error);
                this.data = [];
            }

            this.filtered = [...this.data];
            this.renderStats();
            this.renderTable();
        },

        /**
         * Refresh data
         */
        refreshData: async function () {
            await this.loadData();
            this.showNotification("Data refreshed", "success");
        },

        /**
         * Render KPI stats
         */
        renderStats: function () {
            var totalSettlements = this.data.length;
            var totalAmount = this.data.reduce(function (sum, s) {
                return sum + (parseFloat(s.net_amount) || parseFloat(s.gross_amount) || 0);
            }, 0);
            var pendingAmount = this.data.filter(function (s) {
                return (s.status || "").toLowerCase() === "pending";
            }).reduce(function (sum, s) {
                return sum + (parseFloat(s.net_amount) || parseFloat(s.gross_amount) || 0);
            }, 0);

            var lastDate = null;
            this.data.forEach(function (s) {
                var d = new Date(s.settlement_date || s.date || s.created_at);
                if (!isNaN(d.getTime()) && (!lastDate || d > lastDate)) {
                    lastDate = d;
                }
            });

            var el;
            el = document.getElementById("kpiTotalSettlements");
            if (el) el.textContent = totalSettlements;

            el = document.getElementById("kpiTotalAmount");
            if (el) el.textContent = "KES " + this.formatCurrency(totalAmount);

            el = document.getElementById("kpiPendingSettlement");
            if (el) el.textContent = "KES " + this.formatCurrency(pendingAmount);

            el = document.getElementById("kpiLastSettlementDate");
            if (el) el.textContent = lastDate ? this.formatDate(lastDate) : "N/A";
        },

        /**
         * Render data table
         */
        renderTable: function () {
            var tbody = document.getElementById("mpesaSettlementsTableBody");
            if (!tbody) return;

            if (this.filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4">' +
                    '<i class="fas fa-mobile-alt fa-3x text-muted mb-3 d-block"></i>' +
                    '<p class="text-muted mb-0">No M-Pesa settlements found</p></td></tr>';
                this.updateTableInfo(0);
                this.renderPagination();
                return;
            }

            var start = (this.currentPage - 1) * this.perPage;
            var end = start + this.perPage;
            var pageItems = this.filtered.slice(start, end);
            var self = this;
            var html = "";

            pageItems.forEach(function (settlement, index) {
                var statusBadge = self.getStatusBadge(settlement.status);
                var grossAmount = parseFloat(settlement.gross_amount) || 0;
                var charges = parseFloat(settlement.charges) || parseFloat(settlement.fees) || 0;
                var netAmount = parseFloat(settlement.net_amount) || (grossAmount - charges);

                html += '<tr>' +
                    '<td>' + (start + index + 1) + '</td>' +
                    '<td>' + self.formatDate(settlement.settlement_date || settlement.date || settlement.created_at) + '</td>' +
                    '<td><code>' + self.escapeHtml(settlement.reference || settlement.settlement_ref || settlement.id) + '</code></td>' +
                    '<td class="text-center">' + (settlement.transaction_count || settlement.txn_count || 0) + '</td>' +
                    '<td class="text-end">KES ' + self.formatCurrency(grossAmount) + '</td>' +
                    '<td class="text-end text-danger">KES ' + self.formatCurrency(charges) + '</td>' +
                    '<td class="text-end fw-bold text-success">KES ' + self.formatCurrency(netAmount) + '</td>' +
                    '<td class="text-center">' + statusBadge + '</td>' +
                    '<td class="text-center">' +
                        '<div class="btn-group btn-group-sm">' +
                            '<button class="btn btn-outline-primary" onclick="MpesaSettlementsController.viewDetails(' + settlement.id + ')" title="View Details"><i class="fas fa-eye"></i></button>' +
                            '<button class="btn btn-outline-secondary" onclick="MpesaSettlementsController.exportSettlement(' + settlement.id + ')" title="Export"><i class="fas fa-download"></i></button>' +
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
            var search = (document.getElementById("msSearch")?.value || "").toLowerCase();
            var dateFrom = document.getElementById("msDateFrom")?.value || "";
            var dateTo = document.getElementById("msDateTo")?.value || "";
            var statusFilter = document.getElementById("msStatusFilter")?.value || "";

            this.filtered = this.data.filter(function (settlement) {
                if (statusFilter && (settlement.status || "") !== statusFilter) return false;

                var sDate = settlement.settlement_date || settlement.date || settlement.created_at || "";
                if (dateFrom && sDate && new Date(sDate) < new Date(dateFrom)) return false;
                if (dateTo && sDate && new Date(sDate) > new Date(dateTo)) return false;

                if (search) {
                    var hay = ((settlement.reference || "") + " " + (settlement.settlement_ref || "") + " " +
                        (settlement.status || "")).toLowerCase();
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
            var ids = ["msSearch", "msDateFrom", "msDateTo", "msStatusFilter"];
            ids.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = "";
            });
            this.filtered = [...this.data];
            this.currentPage = 1;
            this.renderTable();
        },

        /**
         * View settlement details with transactions
         */
        viewDetails: async function (id) {
            var settlement = this.data.find(function (s) { return s.id === id; });
            if (!settlement) return;

            this.currentSettlement = settlement;

            // Fill summary
            document.getElementById("detailRef").textContent = settlement.reference || settlement.settlement_ref || settlement.id;
            document.getElementById("detailDate").textContent = this.formatDate(settlement.settlement_date || settlement.date);
            var netAmount = parseFloat(settlement.net_amount) || (parseFloat(settlement.gross_amount || 0) - parseFloat(settlement.charges || settlement.fees || 0));
            document.getElementById("detailNetAmount").textContent = "KES " + this.formatCurrency(netAmount);

            // Load transactions for this settlement
            var transBody = document.getElementById("settlementTransactionsBody");

            try {
                var response = await fetch(API_BASE_URL + "/finance/mpesa-settlements/" + id + "/transactions", {
                    headers: this.getHeaders()
                });
                var result = await response.json();
                var transactions = [];

                if (result.status === "success" || result.success) {
                    transactions = result.data || result.transactions || [];
                }

                if (transactions.length === 0) {
                    // Fallback: use embedded transactions if any
                    transactions = settlement.transactions || [];
                }

                if (transactions.length === 0) {
                    transBody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">No transaction details available</td></tr>';
                } else {
                    var self = this;
                    var html = "";
                    transactions.forEach(function (txn, i) {
                        html += '<tr>' +
                            '<td>' + (i + 1) + '</td>' +
                            '<td><code>' + self.escapeHtml(txn.transaction_id || txn.mpesa_receipt || txn.id) + '</code></td>' +
                            '<td>' + self.escapeHtml(txn.phone_number || txn.phone || txn.msisdn || "-") + '</td>' +
                            '<td>' + self.escapeHtml(txn.name || txn.sender_name || txn.account_name || "-") + '</td>' +
                            '<td class="text-end">KES ' + self.formatCurrency(txn.amount) + '</td>' +
                            '<td>' + self.formatDateTime(txn.transaction_date || txn.date || txn.created_at) + '</td>' +
                            '</tr>';
                    });
                    transBody.innerHTML = html;
                }
            } catch (error) {
                console.error("Error loading settlement transactions:", error);
                transBody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-danger">Failed to load transactions</td></tr>';
            }

            var modal = new bootstrap.Modal(document.getElementById("settlementDetailsModal"));
            modal.show();
        },

        /**
         * Export a single settlement
         */
        exportSettlement: function (id) {
            var settlement = this.data.find(function (s) { return s.id === id; });
            if (!settlement) return;

            var grossAmount = parseFloat(settlement.gross_amount) || 0;
            var charges = parseFloat(settlement.charges) || parseFloat(settlement.fees) || 0;
            var netAmount = parseFloat(settlement.net_amount) || (grossAmount - charges);

            var csv = "Settlement Report\n";
            csv += "Reference," + (settlement.reference || settlement.settlement_ref || settlement.id) + "\n";
            csv += "Date," + this.formatDate(settlement.settlement_date || settlement.date) + "\n";
            csv += "Transaction Count," + (settlement.transaction_count || settlement.txn_count || 0) + "\n";
            csv += "Gross Amount (KES)," + grossAmount + "\n";
            csv += "Charges (KES)," + charges + "\n";
            csv += "Net Amount (KES)," + netAmount + "\n";
            csv += "Status," + (settlement.status || "-") + "\n";

            var blob = new Blob([csv], { type: "text/csv" });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "mpesa_settlement_" + (settlement.reference || settlement.id) + ".csv";
            a.click();
            window.URL.revokeObjectURL(url);
            this.showNotification("Settlement exported", "success");
        },

        /**
         * Print current settlement details
         */
        printSettlement: function () {
            if (!this.currentSettlement) return;

            var content = document.querySelector("#settlementDetailsModal .modal-body").innerHTML;
            var printWindow = window.open("", "_blank");
            printWindow.document.write(
                '<html><head><title>M-Pesa Settlement</title>' +
                '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' +
                '<style>body { padding: 20px; } @media print { .no-print { display: none; } }</style>' +
                '</head><body>' +
                '<h4 class="text-center mb-4">KINGSWAY ACADEMY - M-Pesa Settlement Report</h4>' +
                content +
                '</body></html>'
            );
            printWindow.document.close();
            printWindow.onload = function () {
                printWindow.print();
            };
        },

        /**
         * Export all settlements as CSV
         */
        exportCSV: function () {
            var headers = ["#", "Settlement Date", "Reference", "Transaction Count", "Gross Amount (KES)", "Charges (KES)", "Net Amount (KES)", "Status"];
            var self = this;
            var rows = this.filtered.map(function (s, i) {
                var grossAmount = parseFloat(s.gross_amount) || 0;
                var charges = parseFloat(s.charges) || parseFloat(s.fees) || 0;
                var netAmount = parseFloat(s.net_amount) || (grossAmount - charges);

                return [
                    i + 1,
                    self.formatDate(s.settlement_date || s.date || s.created_at),
                    s.reference || s.settlement_ref || s.id,
                    s.transaction_count || s.txn_count || 0,
                    grossAmount,
                    charges,
                    netAmount,
                    s.status || ""
                ];
            });

            var csv = [headers.join(",")].concat(rows.map(function (r) { return r.join(","); })).join("\n");
            var blob = new Blob([csv], { type: "text/csv" });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "mpesa_settlements_" + new Date().toISOString().split("T")[0] + ".csv";
            a.click();
            window.URL.revokeObjectURL(url);
            this.showNotification("Export completed", "success");
        },

        // ====================================================================
        // Pagination
        // ====================================================================

        renderPagination: function () {
            var pagination = document.getElementById("msPagination");
            if (!pagination) return;

            var totalPages = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
            if (totalPages <= 1) {
                pagination.innerHTML = "";
                return;
            }

            var html = "";
            html += '<li class="page-item ' + (this.currentPage === 1 ? "disabled" : "") + '">' +
                '<a class="page-link" href="#" onclick="MpesaSettlementsController.goToPage(' + (this.currentPage - 1) + '); return false;">&laquo;</a></li>';

            for (var i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                    html += '<li class="page-item ' + (i === this.currentPage ? "active" : "") + '">' +
                        '<a class="page-link" href="#" onclick="MpesaSettlementsController.goToPage(' + i + '); return false;">' + i + '</a></li>';
                } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                    html += '<li class="page-item disabled"><a class="page-link">...</a></li>';
                }
            }

            html += '<li class="page-item ' + (this.currentPage === totalPages ? "disabled" : "") + '">' +
                '<a class="page-link" href="#" onclick="MpesaSettlementsController.goToPage(' + (this.currentPage + 1) + '); return false;">&raquo;</a></li>';

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
            var el = document.getElementById("msTableInfo");
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
            var s = (status || "Pending").toLowerCase();
            var map = {
                settled: '<span class="badge bg-success">Settled</span>',
                pending: '<span class="badge bg-warning text-dark">Pending</span>',
                failed: '<span class="badge bg-danger">Failed</span>',
                processing: '<span class="badge bg-info">Processing</span>'
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

        formatDateTime: function (value) {
            if (!value) return "-";
            var d = new Date(value);
            if (isNaN(d.getTime())) return value;
            return d.toLocaleDateString("en-KE", { year: "numeric", month: "short", day: "numeric" }) +
                " " + d.toLocaleTimeString("en-KE", { hour: "2-digit", minute: "2-digit" });
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

    window.MpesaSettlementsController = MpesaSettlementsController;
})();
