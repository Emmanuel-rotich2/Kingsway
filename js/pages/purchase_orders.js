/**
 * Purchase Orders Controller
 * Handles purchase order management: create, view
 * Integrates with /api/vendors/purchase-orders endpoints
 *
 * @package App\JS\Pages
 */

(function () {
    "use strict";

    const PurchaseOrdersController = {
        // State
        data: [],
        filtered: [],
        vendors: [],
        currentPage: 1,
        perPage: 15,
        itemRowIndex: 1,

        /**
         * Initialize controller
         */
        init: async function () {
            if (window.AuthContext?.ready) await window.AuthContext.ready();
            try {
                await Promise.all([this.loadData(), this.loadVendors()]);
            } catch (error) {
                console.error("Error initializing PurchaseOrdersController:", error);
                this.showNotification("Failed to load purchase orders", "error");
            }
        },

        /**
         * Load data from API
         */
        loadData: async function () {
            try {
                var response = await API.callAPI("/vendors/purchase-orders", "GET");
                this.data = (response && response.purchase_orders) ? response.purchase_orders : [];
                if (!Array.isArray(this.data)) this.data = [];
            } catch (error) {
                console.error("Error loading purchase orders:", error);
                this.data = [];
            }

            this.filtered = [...this.data];
            this.renderStats();
            this.renderTable();
            this.populateVendorFilters();
        },

        /**
         * Load vendors for the create form
         */
        loadVendors: async function () {
            try {
                var response = await API.callAPI("/vendors", "GET");
                this.vendors = (response && response.vendors) ? response.vendors : [];
                if (!Array.isArray(this.vendors)) this.vendors = [];
            } catch (error) {
                console.error("Error loading vendors:", error);
                this.vendors = [];
            }
        },

        /**
         * Render KPI stats
         */
        renderStats: function () {
            const total = this.data.length;
            const pending = this.data.filter(function (po) {
                return (po.status || "").toLowerCase() === "pending";
            }).length;
            const approved = this.data.filter(function (po) {
                return (po.status || "").toLowerCase() === "approved";
            }).length;
            const totalValue = this.data.reduce(function (sum, po) {
                return sum + (parseFloat(po.total_amount) || 0);
            }, 0);

            var el;
            el = document.getElementById("kpiTotalPOs");
            if (el) el.textContent = total;

            el = document.getElementById("kpiPendingApproval");
            if (el) el.textContent = pending;

            el = document.getElementById("kpiApproved");
            if (el) el.textContent = approved;

            el = document.getElementById("kpiTotalValue");
            if (el) el.textContent = "KES " + this.formatCurrency(totalValue);
        },

        /**
         * Render data table
         */
        renderTable: function () {
            var tbody = document.getElementById("purchaseOrdersTableBody");
            if (!tbody) return;

            if (this.filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4">' +
                    '<i class="fas fa-file-invoice fa-3x text-muted mb-3 d-block"></i>' +
                    '<p class="text-muted mb-0">No purchase orders found</p></td></tr>';
                this.updateTableInfo(0);
                this.renderPagination();
                return;
            }

            var start = (this.currentPage - 1) * this.perPage;
            var end = start + this.perPage;
            var pageItems = this.filtered.slice(start, end);
            var self = this;
            var html = "";

            pageItems.forEach(function (po, index) {
                var statusBadge = self.getStatusBadge(po.status);
                html += '<tr>' +
                    '<td>' + (start + index + 1) + '</td>' +
                    '<td><strong>' + self.escapeHtml(po.order_number || po.id) + '</strong></td>' +
                    '<td>' + self.formatDate(po.order_date || po.created_at) + '</td>' +
                    '<td>' + self.escapeHtml(po.supplier_name || "-") + '</td>' +
                    '<td>' + self.escapeHtml(po.remarks || "-") + '</td>' +
                    '<td class="text-center">' + (po.item_count || 0) + '</td>' +
                    '<td class="text-end">KES ' + self.formatCurrency(po.total_amount || 0) + '</td>' +
                    '<td class="text-center">' + statusBadge + '</td>' +
                    '<td class="text-center">' +
                        '<div class="btn-group btn-group-sm">' +
                            '<button class="btn btn-outline-primary" data-kw-onclick="PurchaseOrdersController.viewPO(' + po.id + ')" title="View"><i class="fas fa-eye"></i></button>' +
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
            var search = (document.getElementById("poSearch")?.value || "").toLowerCase();
            var statusFilter = (document.getElementById("poStatusFilter")?.value || "").toLowerCase();
            var vendorFilter = document.getElementById("poVendorFilter")?.value || "";
            var dateFrom = document.getElementById("poDateFrom")?.value || "";
            var dateTo = document.getElementById("poDateTo")?.value || "";

            this.filtered = this.data.filter(function (po) {
                if (statusFilter && (po.status || "").toLowerCase() !== statusFilter) return false;
                if (vendorFilter && (po.supplier_name || "") !== vendorFilter) return false;

                if (dateFrom) {
                    var poDate = po.order_date || po.created_at || "";
                    if (poDate && new Date(poDate) < new Date(dateFrom)) return false;
                }
                if (dateTo) {
                    var poDate2 = po.order_date || po.created_at || "";
                    if (poDate2 && new Date(poDate2) > new Date(dateTo)) return false;
                }

                if (search) {
                    var hay = ((po.order_number || "") + " " + (po.supplier_name || "") + " " + (po.remarks || "")).toLowerCase();
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
            var ids = ["poSearch", "poStatusFilter", "poVendorFilter", "poDateFrom", "poDateTo"];
            ids.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = "";
            });
            this.filtered = [...this.data];
            this.currentPage = 1;
            this.renderTable();
        },

        /**
         * Populate vendor filter dropdown (from PO rows) and the form select (from vendors)
         */
        populateVendorFilters: function () {
            var vendorFilter = document.getElementById("poVendorFilter");
            var vendorSelect = document.getElementById("po_vendor");
            var vendors = [];
            var seen = {};

            this.data.forEach(function (po) {
                var name = po.supplier_name || "";
                if (name && !seen[name]) {
                    seen[name] = true;
                    vendors.push(name);
                }
            });
            vendors.sort();

            if (vendorFilter) {
                var current = vendorFilter.value;
                vendorFilter.innerHTML = '<option value="">All Vendors</option>';
                var self = this;
                vendors.forEach(function (v) {
                    vendorFilter.innerHTML += '<option value="' + self.escapeHtml(v) + '">' + self.escapeHtml(v) + '</option>';
                });
                vendorFilter.value = current || "";
            }

            if (vendorSelect) {
                vendorSelect.innerHTML = '<option value="">Select Vendor</option>';
                this.vendors.forEach(function (v) {
                    vendorSelect.innerHTML += '<option value="' + v.id + '">' + self.escapeHtml(v.supplier_name || v.name || "") + '</option>';
                });
            }
        },

        /**
         * Show create PO modal
         */
        showCreateModal: function () {
            document.getElementById("purchaseOrderModalLabel").innerHTML =
                '<i class="fas fa-file-invoice me-2"></i> New Purchase Order';
            document.getElementById("purchaseOrderForm").reset();
            document.getElementById("po_date").value = new Date().toISOString().split("T")[0];
            document.getElementById("po_grand_total").value = "";

            // Reset items to a single row
            var container = document.getElementById("poItemsContainer");
            container.innerHTML = '';
            this.itemRowIndex = 0;
            this.addItemRow();

            var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("purchaseOrderModal"));
            modal.show();
        },

        /**
         * Add an item row to the PO form
         */
        addItemRow: function () {
            var idx = this.itemRowIndex++;
            var container = document.getElementById("poItemsContainer");
            var row = document.createElement("div");
            row.className = "row g-2 mb-2 po-item-row";
            row.setAttribute("data-index", idx);
            row.innerHTML =
                '<div class="col-md-4">' +
                    '<input type="text" class="form-control form-control-sm" placeholder="Item name" name="item_name[]">' +
                '</div>' +
                '<div class="col-md-2">' +
                    '<input type="number" class="form-control form-control-sm" placeholder="Qty" name="item_qty[]" min="1" value="1" data-kw-onchange="PurchaseOrdersController.recalcTotal()">' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<input type="number" class="form-control form-control-sm" placeholder="Unit Price" name="item_price[]" step="0.01" min="0" required data-kw-onchange="PurchaseOrdersController.recalcTotal()">' +
                '</div>' +
                '<div class="col-md-2">' +
                    '<input type="text" class="form-control form-control-sm" placeholder="Total" name="item_total[]" readonly>' +
                '</div>' +
                '<div class="col-md-1">' +
                    '<button type="button" class="btn btn-outline-danger btn-sm" data-kw-onclick="PurchaseOrdersController.removeItemRow(this)" title="Remove" data-year-write>' +
                        '<i class="fas fa-trash"></i>' +
                    '</button>' +
                '</div>';
            container.appendChild(row);
        },

        /**
         * Remove an item row
         */
        removeItemRow: function (btn) {
            var row = btn.closest(".po-item-row");
            var container = document.getElementById("poItemsContainer");
            if (container.querySelectorAll(".po-item-row").length > 1) {
                row.remove();
                this.recalcTotal();
            } else {
                this.showNotification("At least one item is required", "warning");
            }
        },

        /**
         * Recalculate row totals and grand total
         */
        recalcTotal: function () {
            var rows = document.querySelectorAll(".po-item-row");
            var grandTotal = 0;
            rows.forEach(function (row) {
                var qty = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
                var price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
                var lineTotal = qty * price;
                row.querySelector('[name="item_total[]"]').value = lineTotal.toFixed(2);
                grandTotal += lineTotal;
            });
            document.getElementById("po_grand_total").value = "KES " + this.formatCurrency(grandTotal);
        },

        /**
         * Save purchase order
         */
        savePO: async function () {
            var form = document.getElementById("purchaseOrderForm");
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            var supplierId = document.getElementById("po_vendor").value;
            if (!supplierId) {
                this.showNotification("Please select a vendor", "warning");
                return;
            }

            var grandTotal = 0;
            document.querySelectorAll(".po-item-row").forEach(function (row) {
                var qty = parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0;
                var price = parseFloat(row.querySelector('[name="item_price[]"]').value) || 0;
                grandTotal += qty * price;
            });

            if (grandTotal <= 0) {
                this.showNotification("Enter at least one item with a unit price", "warning");
                return;
            }

            var description = document.getElementById("po_description").value;
            var notes = document.getElementById("po_notes").value;
            var remarks = description;
            if (notes) {
                remarks = description ? (description + " — " + notes) : notes;
            }

            var payload = {
                supplier_id: supplierId,
                total_amount: grandTotal,
                payment_terms: "Net 30",
                remarks: remarks
            };

            try {
                await API.callAPI("/vendors/purchase-orders", "POST", payload);

                this.showNotification("Purchase order created successfully", "success");
                bootstrap.Modal.getInstance(document.getElementById("purchaseOrderModal")).hide();
                await this.loadData();
            } catch (error) {
                console.error("Error saving PO:", error);
                this.showNotification("Failed to save purchase order", "error");
            }
        },

        /**
         * View purchase order details
         */
        viewPO: async function (id) {
            var po = this.data.find(function (p) { return p.id === id; });
            if (!po) return;

            var msg = "PO: " + (po.order_number || po.id) + "\n" +
                "Vendor: " + (po.supplier_name || "-") + "\n" +
                "Date: " + this.formatDate(po.order_date || po.created_at) + "\n" +
                "Description: " + (po.remarks || "-") + "\n" +
                "Items: " + (po.item_count || 0) + "\n" +
                "Total: KES " + this.formatCurrency(po.total_amount || 0) + "\n" +
                "Status: " + (po.status || "Draft");
            await window.infoDialog('Notice', msg);
        },

        /**
         * Export data as CSV
         */
        exportCSV: function () {
            var headers = ["#", "PO Number", "Date", "Vendor", "Description", "Items Count", "Total (KES)", "Status"];
            var self = this;
            var rows = this.filtered.map(function (po, i) {
                return [
                    i + 1,
                    po.order_number || po.id,
                    self.formatDate(po.order_date || po.created_at),
                    po.supplier_name || "",
                    (po.remarks || "").replace(/,/g, " "),
                    po.item_count || 0,
                    po.total_amount || 0,
                    po.status || "Draft"
                ];
            });

            var csv = [headers.join(",")].concat(rows.map(function (r) { return r.join(","); })).join("\n");
            KingswayFileLifecycle.exportText(csv, "purchase_orders_" + new Date().toISOString().split("T")[0] + ".csv", "text/csv");
            this.showNotification("Export completed", "success");
        },

        // ====================================================================
        // Pagination
        // ====================================================================

        renderPagination: function () {
            var pagination = document.getElementById("poPagination");
            if (!pagination) return;

            var totalPages = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
            if (totalPages <= 1) {
                pagination.innerHTML = "";
                return;
            }

            var html = "";
            html += '<li class="page-item ' + (this.currentPage === 1 ? "disabled" : "") + '">' +
                '<a class="page-link" href="#" data-kw-onclick="PurchaseOrdersController.goToPage(' + (this.currentPage - 1) + '); return false;">&laquo;</a></li>';

            for (var i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                    html += '<li class="page-item ' + (i === this.currentPage ? "active" : "") + '">' +
                        '<a class="page-link" href="#" data-kw-onclick="PurchaseOrdersController.goToPage(' + i + '); return false;">' + i + '</a></li>';
                } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                    html += '<li class="page-item disabled"><a class="page-link">...</a></li>';
                }
            }

            html += '<li class="page-item ' + (this.currentPage === totalPages ? "disabled" : "") + '">' +
                '<a class="page-link" href="#" data-kw-onclick="PurchaseOrdersController.goToPage(' + (this.currentPage + 1) + '); return false;">&raquo;</a></li>';

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
            var el = document.getElementById("poTableInfo");
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
            var s = (status || "draft").toLowerCase();
            var map = {
                draft: '<span class="badge bg-secondary">Draft</span>',
                pending: '<span class="badge bg-warning text-dark">Pending</span>',
                approved: '<span class="badge bg-success">Approved</span>',
                ordered: '<span class="badge bg-primary">Ordered</span>',
                received: '<span class="badge bg-info">Received</span>',
                cancelled: '<span class="badge bg-danger">Cancelled</span>'
            };
            return map[s] || '<span class="badge bg-secondary">' + status + '</span>';
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
        }
    };

    window.PurchaseOrdersController = PurchaseOrdersController;
})();
