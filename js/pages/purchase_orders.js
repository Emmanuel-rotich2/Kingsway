/**
 * Purchase Orders Controller
 * Handles purchase order management: create, approve, receive, cancel
 * Integrates with /api/finance/purchase-orders endpoints
 *
 * @package App\JS\Pages
 */

(function () {
    "use strict";

    const PurchaseOrdersController = {
        // State
        data: [],
        filtered: [],
        currentPage: 1,
        perPage: 15,
        itemRowIndex: 1,

        /**
         * Initialize controller
         */
        init: async function () {
            try {
                console.log("Initializing PurchaseOrdersController...");
                await this.loadData();
                console.log("PurchaseOrdersController initialized successfully");
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
                const response = await fetch(API_BASE_URL + "/finance/purchase-orders", {
                    headers: this.getHeaders()
                });
                const result = await response.json();

                if (result.status === "success" || result.success) {
                    this.data = result.data || result.purchase_orders || [];
                } else {
                    this.data = [];
                }
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
                return sum + (parseFloat(po.total_amount) || parseFloat(po.total) || 0);
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
                    '<td><strong>' + self.escapeHtml(po.po_number || po.id) + '</strong></td>' +
                    '<td>' + self.formatDate(po.po_date || po.created_at) + '</td>' +
                    '<td>' + self.escapeHtml(po.vendor_name || po.vendor || "-") + '</td>' +
                    '<td>' + self.escapeHtml(po.description || "-") + '</td>' +
                    '<td class="text-center">' + (po.items_count || po.items?.length || 0) + '</td>' +
                    '<td class="text-end">KES ' + self.formatCurrency(po.total_amount || po.total || 0) + '</td>' +
                    '<td class="text-center">' + statusBadge + '</td>' +
                    '<td class="text-center">' +
                        '<div class="btn-group btn-group-sm">' +
                            '<button class="btn btn-outline-primary" onclick="PurchaseOrdersController.viewPO(' + po.id + ')" title="View"><i class="fas fa-eye"></i></button>' +
                            '<button class="btn btn-outline-warning" onclick="PurchaseOrdersController.editPO(' + po.id + ')" title="Edit"><i class="fas fa-edit"></i></button>' +
                            '<button class="btn btn-outline-danger" onclick="PurchaseOrdersController.deletePO(' + po.id + ')" title="Delete"><i class="fas fa-trash"></i></button>' +
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
            var statusFilter = document.getElementById("poStatusFilter")?.value || "";
            var vendorFilter = document.getElementById("poVendorFilter")?.value || "";
            var dateFrom = document.getElementById("poDateFrom")?.value || "";
            var dateTo = document.getElementById("poDateTo")?.value || "";

            this.filtered = this.data.filter(function (po) {
                if (statusFilter && (po.status || "") !== statusFilter) return false;
                if (vendorFilter && (po.vendor_name || po.vendor || "") !== vendorFilter) return false;

                if (dateFrom) {
                    var poDate = po.po_date || po.created_at || "";
                    if (poDate && new Date(poDate) < new Date(dateFrom)) return false;
                }
                if (dateTo) {
                    var poDate2 = po.po_date || po.created_at || "";
                    if (poDate2 && new Date(poDate2) > new Date(dateTo)) return false;
                }

                if (search) {
                    var hay = ((po.po_number || "") + " " + (po.vendor_name || po.vendor || "") + " " + (po.description || "")).toLowerCase();
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
         * Populate vendor filter dropdown
         */
        populateVendorFilters: function () {
            var vendorFilter = document.getElementById("poVendorFilter");
            var vendorSelect = document.getElementById("po_vendor");
            var vendors = [];
            var seen = {};

            this.data.forEach(function (po) {
                var name = po.vendor_name || po.vendor || "";
                if (name && !seen[name]) {
                    seen[name] = true;
                    vendors.push(name);
                }
            });
            vendors.sort();

            if (vendorFilter) {
                var current = vendorFilter.value;
                vendorFilter.innerHTML = '<option value="">All Vendors</option>';
                vendors.forEach(function (v) {
                    vendorFilter.innerHTML += '<option value="' + v + '">' + v + '</option>';
                });
                vendorFilter.value = current || "";
            }

            if (vendorSelect) {
                vendorSelect.innerHTML = '<option value="">Select Vendor</option>';
                vendors.forEach(function (v) {
                    vendorSelect.innerHTML += '<option value="' + v + '">' + v + '</option>';
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
            document.getElementById("po_id").value = "";
            document.getElementById("po_date").value = new Date().toISOString().split("T")[0];
            document.getElementById("po_grand_total").value = "";

            // Reset items to a single row
            var container = document.getElementById("poItemsContainer");
            container.innerHTML = '';
            this.itemRowIndex = 0;
            this.addItemRow();

            var modal = new bootstrap.Modal(document.getElementById("purchaseOrderModal"));
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
                    '<input type="text" class="form-control form-control-sm" placeholder="Item name" name="item_name[]" required>' +
                '</div>' +
                '<div class="col-md-2">' +
                    '<input type="number" class="form-control form-control-sm" placeholder="Qty" name="item_qty[]" min="1" value="1" required onchange="PurchaseOrdersController.recalcTotal()">' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<input type="number" class="form-control form-control-sm" placeholder="Unit Price" name="item_price[]" step="0.01" min="0" required onchange="PurchaseOrdersController.recalcTotal()">' +
                '</div>' +
                '<div class="col-md-2">' +
                    '<input type="text" class="form-control form-control-sm" placeholder="Total" name="item_total[]" readonly>' +
                '</div>' +
                '<div class="col-md-1">' +
                    '<button type="button" class="btn btn-outline-danger btn-sm" onclick="PurchaseOrdersController.removeItemRow(this)" title="Remove">' +
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

            var items = [];
            document.querySelectorAll(".po-item-row").forEach(function (row) {
                items.push({
                    name: row.querySelector('[name="item_name[]"]').value,
                    quantity: parseFloat(row.querySelector('[name="item_qty[]"]').value) || 0,
                    unit_price: parseFloat(row.querySelector('[name="item_price[]"]').value) || 0,
                    total: parseFloat(row.querySelector('[name="item_total[]"]').value) || 0
                });
            });

            var payload = {
                id: document.getElementById("po_id").value || null,
                vendor: document.getElementById("po_vendor").value,
                po_date: document.getElementById("po_date").value,
                description: document.getElementById("po_description").value,
                items: items,
                notes: document.getElementById("po_notes").value
            };

            try {
                var url = API_BASE_URL + "/finance/purchase-orders";
                var method = payload.id ? "PUT" : "POST";
                if (payload.id) url += "/" + payload.id;

                var response = await fetch(url, {
                    method: method,
                    headers: this.getHeaders(),
                    body: JSON.stringify(payload)
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.showNotification("Purchase order saved successfully", "success");
                    bootstrap.Modal.getInstance(document.getElementById("purchaseOrderModal")).hide();
                    await this.loadData();
                } else {
                    this.showNotification(result.message || "Failed to save purchase order", "error");
                }
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

            var msg = "PO: " + (po.po_number || po.id) + "\n" +
                "Vendor: " + (po.vendor_name || po.vendor || "-") + "\n" +
                "Date: " + this.formatDate(po.po_date || po.created_at) + "\n" +
                "Description: " + (po.description || "-") + "\n" +
                "Total: KES " + this.formatCurrency(po.total_amount || po.total || 0) + "\n" +
                "Status: " + (po.status || "Draft");
            alert(msg);
        },

        /**
         * Edit purchase order
         */
        editPO: async function (id) {
            var po = this.data.find(function (p) { return p.id === id; });
            if (!po) return;

            document.getElementById("purchaseOrderModalLabel").innerHTML =
                '<i class="fas fa-file-invoice me-2"></i> Edit Purchase Order';
            document.getElementById("po_id").value = po.id;
            document.getElementById("po_vendor").value = po.vendor_name || po.vendor || "";
            document.getElementById("po_date").value = (po.po_date || po.created_at || "").split("T")[0];
            document.getElementById("po_description").value = po.description || "";
            document.getElementById("po_notes").value = po.notes || "";

            // Rebuild items
            var container = document.getElementById("poItemsContainer");
            container.innerHTML = "";
            this.itemRowIndex = 0;
            var poItems = po.items || [];
            if (poItems.length === 0) {
                this.addItemRow();
            } else {
                var self = this;
                poItems.forEach(function (item) {
                    self.addItemRow();
                    var rows = container.querySelectorAll(".po-item-row");
                    var lastRow = rows[rows.length - 1];
                    lastRow.querySelector('[name="item_name[]"]').value = item.name || item.item_name || "";
                    lastRow.querySelector('[name="item_qty[]"]').value = item.quantity || item.qty || 1;
                    lastRow.querySelector('[name="item_price[]"]').value = item.unit_price || item.price || 0;
                });
                this.recalcTotal();
            }

            var modal = new bootstrap.Modal(document.getElementById("purchaseOrderModal"));
            modal.show();
        },

        /**
         * Delete purchase order
         */
        deletePO: async function (id) {
            if (!confirm("Are you sure you want to delete this purchase order?")) return;

            try {
                var response = await fetch(API_BASE_URL + "/finance/purchase-orders/" + id, {
                    method: "DELETE",
                    headers: this.getHeaders()
                });
                var result = await response.json();

                if (result.status === "success" || result.success) {
                    this.showNotification("Purchase order deleted", "success");
                    await this.loadData();
                } else {
                    this.showNotification(result.message || "Failed to delete", "error");
                }
            } catch (error) {
                console.error("Error deleting PO:", error);
                this.showNotification("Failed to delete purchase order", "error");
            }
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
                    po.po_number || po.id,
                    self.formatDate(po.po_date || po.created_at),
                    po.vendor_name || po.vendor || "",
                    (po.description || "").replace(/,/g, " "),
                    po.items_count || (po.items ? po.items.length : 0),
                    po.total_amount || po.total || 0,
                    po.status || "Draft"
                ];
            });

            var csv = [headers.join(",")].concat(rows.map(function (r) { return r.join(","); })).join("\n");
            var blob = new Blob([csv], { type: "text/csv" });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "purchase_orders_" + new Date().toISOString().split("T")[0] + ".csv";
            a.click();
            window.URL.revokeObjectURL(url);
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
                '<a class="page-link" href="#" onclick="PurchaseOrdersController.goToPage(' + (this.currentPage - 1) + '); return false;">&laquo;</a></li>';

            for (var i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                    html += '<li class="page-item ' + (i === this.currentPage ? "active" : "") + '">' +
                        '<a class="page-link" href="#" onclick="PurchaseOrdersController.goToPage(' + i + '); return false;">' + i + '</a></li>';
                } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                    html += '<li class="page-item disabled"><a class="page-link">...</a></li>';
                }
            }

            html += '<li class="page-item ' + (this.currentPage === totalPages ? "disabled" : "") + '">' +
                '<a class="page-link" href="#" onclick="PurchaseOrdersController.goToPage(' + (this.currentPage + 1) + '); return false;">&raquo;</a></li>';

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
            var s = (status || "Draft").toLowerCase();
            var map = {
                draft: '<span class="badge bg-secondary">Draft</span>',
                pending: '<span class="badge bg-warning text-dark">Pending</span>',
                approved: '<span class="badge bg-success">Approved</span>',
                received: '<span class="badge bg-primary">Received</span>',
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

    window.PurchaseOrdersController = PurchaseOrdersController;
})();
