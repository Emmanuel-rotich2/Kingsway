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
            if (window.AuthContext?.ready) await window.AuthContext.ready();
            try {
                await this.loadData();
            } catch (error) {
                console.error("Error initializing MpesaSettlementsController:", error);
                this.showNotification("Failed to load M-Pesa settlements", "error");
            }
            window.APIRealtime?.register?.("mpesa_settlements", this, ["payments"]);
        },

        /**
         * Load data from API
         */
        loadData: async function () {
            try {
                var response = await API.payments.getMpesaSettlements();
                this.data = (response && response.transactions) ? response.transactions : [];
                if (!Array.isArray(this.data)) this.data = [];
            } catch (error) {
                console.error("Error loading M-Pesa transactions:", error);
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
                return sum + (parseFloat(s.amount) || 0);
            }, 0);
            var pendingAmount = this.data.filter(function (s) {
                return ["pending", "pending_callback"].indexOf((s.settlement_status || s.status || "").toLowerCase()) !== -1;
            }).reduce(function (sum, s) {
                return sum + (parseFloat(s.amount) || 0);
            }, 0);

            var lastDate = null;
            this.data.forEach(function (s) {
                var d = new Date(s.transaction_date || s.created_at);
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
                tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4">' +
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
                var statusBadge = self.getStatusBadge(settlement.settlement_status || settlement.status);
                var grossAmount = parseFloat(settlement.amount) || 0;
                var netAmount = grossAmount;

                html += '<tr>' +
                    '<td>' + (start + index + 1) + '</td>' +
                    '<td>' + self.formatDate(settlement.transaction_date || settlement.created_at) + '</td>' +
                    '<td><code>' + self.escapeHtml(settlement.mpesa_code || settlement.id) + '</code></td>' +
                    '<td>' + self.escapeHtml(settlement.phone_number || "-") + '</td>' +
                    '<td class="text-center">1</td>' +
                    '<td class="text-end">KES ' + self.formatCurrency(grossAmount) + '</td>' +
                    '<td class="text-end fw-bold text-success">KES ' + self.formatCurrency(netAmount) + '</td>' +
                    '<td class="text-center">' + statusBadge + '<div class="small text-muted mt-1" title="' + self.escapeHtml(settlement.status_reason || '') + '">' + self.escapeHtml(self.shortReason(settlement.status_reason)) + '</div></td>' +
                    '<td class="text-center">' +
                        '<div class="btn-group btn-group-sm">' +
                            '<button class="btn btn-outline-primary" onclick="MpesaSettlementsController.viewDetails(' + settlement.id + ')" title="View Details"><i class="fas fa-eye"></i></button>' +
                            (settlement.can_reconcile ? '<button class="btn btn-outline-warning" onclick="MpesaSettlementsController.openReconciliation()" title="Reconcile / allocate"><i class="fas fa-link"></i></button>' : '') +
                            (settlement.checkout_request_id && settlement.settlement_status === 'pending_callback' ? '<button class="btn btn-outline-info" onclick="MpesaSettlementsController.queryStatus(' + settlement.id + ')" title="Query provider status"><i class="fas fa-sync"></i></button>' : '') +
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
                if (statusFilter && (settlement.settlement_status || settlement.status || "").toLowerCase() !== statusFilter.toLowerCase()) return false;

                var sDate = settlement.transaction_date || settlement.created_at || "";
                if (dateFrom && sDate && new Date(sDate) < new Date(dateFrom)) return false;
                if (dateTo && sDate && new Date(sDate) > new Date(dateTo)) return false;

                if (search) {
                    var hay = ((settlement.mpesa_code || "") + " " + (settlement.phone_number || "") + " " +
                        (settlement.settlement_status || settlement.status || "") + " " + (settlement.status_reason || "")).toLowerCase();
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
            document.getElementById("detailRef").textContent = settlement.mpesa_code || settlement.id;
            document.getElementById("detailDate").textContent = this.formatDate(settlement.transaction_date || settlement.created_at);
            var netAmount = parseFloat(settlement.amount) || 0;
            document.getElementById("detailNetAmount").textContent = "KES " + this.formatCurrency(netAmount);
            var detailStatus = document.getElementById("detailStatus");
            if (detailStatus) detailStatus.innerHTML = this.getStatusBadge(settlement.settlement_status || settlement.status) + '<div class="small text-muted mt-1">' + this.escapeHtml(settlement.status_reason || "") + '</div>';
            var detailStudent = document.getElementById("detailStudent");
            if (detailStudent) detailStudent.textContent = settlement.admission_no ? settlement.admission_no + " — " + ([settlement.first_name, settlement.middle_name, settlement.last_name].filter(Boolean).join(" ") || "") : "Not linked";

            // Render the single transaction breakdown
            var transBody = document.getElementById("settlementTransactionsBody");
            var transactions = [settlement];

            if (transBody) {
                var self = this;
                var html = "";
                transactions.forEach(function (txn, i) {
                    var name = [txn.first_name, txn.middle_name, txn.last_name].filter(Boolean).join(" ") || "-";
                    html += '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td><code>' + self.escapeHtml(txn.mpesa_code || txn.id) + '</code></td>' +
                        '<td>' + self.escapeHtml(txn.phone_number || "-") + '</td>' +
                        '<td>' + self.escapeHtml(name) + '</td>' +
                        '<td class="text-end">KES ' + self.formatCurrency(txn.amount) + '</td>' +
                        '<td>' + self.formatDateTime(txn.transaction_date || txn.created_at) + '</td>' +
                        '</tr>';
                });
                transBody.innerHTML = html;
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

            var grossAmount = parseFloat(settlement.amount) || 0;
            var name = [settlement.first_name, settlement.middle_name, settlement.last_name].filter(Boolean).join(" ") || "-";

            var csv = "M-Pesa Settlement Report\n";
            csv += "Code," + (settlement.mpesa_code || settlement.id) + "\n";
            csv += "Date," + this.formatDate(settlement.transaction_date || settlement.created_at) + "\n";
            csv += "Phone," + (settlement.phone_number || "-") + "\n";
            csv += "Name," + name + "\n";
            csv += "Amount (KES)," + grossAmount + "\n";
            csv += "Status," + (settlement.settlement_status || settlement.status || "-") + "\n";
            csv += "Reason," + (settlement.status_reason || "-") + "\n";

            KingswayFileLifecycle.exportText(csv, "mpesa_settlement_" + (settlement.mpesa_code || settlement.id) + ".csv", "text/csv");
            this.showNotification("Settlement exported", "success");
        },

        /**
         * Print current settlement details
         */
        printSettlement: async function () {
            if (!this.currentSettlement) {
                this.showNotification("No settlement is selected", "warning");
                return;
            }

            const settlement = this.currentSettlement;
            const gross = Number(settlement.amount || 0);
            const net = gross;
            const name = [settlement.first_name, settlement.middle_name, settlement.last_name].filter(Boolean).join(" ") || "—";

            await window.PrintManager.printRecord({
                title: "M-Pesa Settlement Report",
                subtitle: settlement.mpesa_code || `Settlement ${settlement.id}`,
                sections: [{
                    title: "Transaction Details",
                    fields: [
                        { label: "Date", value: this.formatDate(settlement.transaction_date || settlement.created_at) },
                        { label: "M-Pesa Code", value: settlement.mpesa_code || settlement.id },
                        { label: "Phone", value: settlement.phone_number || "—" },
                        { label: "Name", value: name },
                        { label: "Amount", value: `KSh ${gross.toLocaleString("en-KE", { minimumFractionDigits: 2 })}` },
                        { label: "Status", value: settlement.settlement_status || settlement.status || "—" },
                        { label: "Reason", value: settlement.status_reason || "—" },
                    ],
                }],
                reportCode: `MPESA-SETTLEMENT-${settlement.id}`,
                filename: `mpesa_settlement_${settlement.id}`,
                signatureSection: [
                    { label: "Accountant", dateLine: true },
                    { label: "Headteacher", dateLine: true },
                ],
            });
        },

        /**
         * Export all settlements as CSV
         */
        exportCSV: function () {
            var headers = ["#", "Date", "M-Pesa Code", "Phone Number", "Name", "Amount (KES)", "Status", "Reason"];
            var self = this;
            var rows = this.filtered.map(function (s, i) {
                var name = [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(" ") || "";

                return [
                    i + 1,
                    self.formatDate(s.transaction_date || s.created_at),
                    s.mpesa_code || s.id,
                    s.phone_number || "",
                    name.replace(/,/g, " "),
                    s.amount || 0,
                    s.settlement_status || s.status || "",
                    s.status_reason || ""
                ];
            });

            var csv = [headers.join(",")].concat(rows.map(function (r) { return r.join(","); })).join("\n");
            KingswayFileLifecycle.exportText(csv, "mpesa_settlements_" + new Date().toISOString().split("T")[0] + ".csv", "text/csv");
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
            var s = (status || "pending").toLowerCase();
            var map = {
                reconciled: '<span class="badge bg-success">Reconciled</span>',
                processed: '<span class="badge bg-success">Processed</span>',
                settled: '<span class="badge bg-success">Settled</span>',
                pending: '<span class="badge bg-warning text-dark">Pending</span>',
                failed: '<span class="badge bg-danger">Failed</span>',
                processing: '<span class="badge bg-info">Processing</span>'
            };
            map.posted = '<span class="badge bg-success">Posted</span>';
            map.received_unallocated = '<span class="badge bg-warning text-dark">Received · Unallocated</span>';
            map.pending_callback = '<span class="badge bg-warning text-dark">Pending callback</span>';
            map.pending = '<span class="badge bg-secondary">Pending</span>';
            return map[s] || '<span class="badge bg-secondary">' + (status || "Unknown") + '</span>';
        },

        shortReason: function (reason) {
            if (!reason) return "";
            return reason.length > 42 ? reason.substring(0, 39) + "…" : reason;
        },

        openReconciliation: function () {
            window.location.href = (window.APP_BASE || "") + "/home.php?route=mpesa_reconciliation";
        },

        queryStatus: async function (id) {
            var row = this.data.find(function (s) { return s.id === id; });
            if (!row || !row.checkout_request_id) return;
            try {
                await API.payments.stkQuery({ checkout_request_id: row.checkout_request_id });
                this.showNotification("Provider status query sent; refresh shortly for the callback result.", "info");
                await this.loadData();
            } catch (error) {
                this.showNotification(error.message || "Unable to query provider status", "error");
            }
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

    window.MpesaSettlementsController = MpesaSettlementsController;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => MpesaSettlementsController.init().catch(() => {}));
    } else {
        MpesaSettlementsController.init().catch(() => {});
    }
})();
