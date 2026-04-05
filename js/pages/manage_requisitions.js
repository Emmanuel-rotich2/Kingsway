(function () {
    "use strict";

    // ── helpers ──────────────────────────────────────────────────────────────
    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }
    function currency(val) {
        return "KES " + Number(val || 0).toLocaleString("en-KE", { minimumFractionDigits: 2 });
    }
    function showToast(message, type = "success") {
        const el = document.createElement("div");
        el.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible position-fixed top-0 end-0 m-3`;
        el.style.zIndex = "9999";
        el.innerHTML = esc(message) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }
    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }
    function getVal(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() : "";
    }
    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val;
    }
    function todayStr() {
        return new Date().toISOString().split("T")[0];
    }
    function statusBadge(status) {
        const map = {
            pending:   "warning",
            approved:  "success",
            rejected:  "danger",
            fulfilled: "primary",
            cancelled: "secondary",
        };
        return `<span class="badge bg-${map[status] || "secondary"} text-capitalize">${esc(status || "unknown")}</span>`;
    }

    // ── state ─────────────────────────────────────────────────────────────────
    const PAGE_SIZE = 15;
    let allRequisitions = [];
    let filteredRequisitions = [];
    let currentPage = 1;
    let currentRequisition = null;
    let allInventoryItems = [];

    // ── Controller ────────────────────────────────────────────────────────────
    const ManageRequisitionsController = {

        init() {
            if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
                window.location.href = (window.APP_BASE || "") + "/index.php";
                return;
            }
            this.bindEvents();
            this.loadAll();
        },

        async loadAll() {
            await Promise.all([this.loadRequisitions(), this.loadInventoryItems()]);
        },

        // ── Inventory items for requisition form ──────────────────────────────
        async loadInventoryItems() {
            try {
                const res = await window.API.inventory.listItems();
                allInventoryItems = Array.isArray(res) ? res : (res?.items || res?.data || []);
            } catch (e) {
                console.error("Inventory items load error:", e);
            }
        },

        // ── Requisitions list ─────────────────────────────────────────────────
        async loadRequisitions() {
            try {
                const res = await window.API.inventory.listRequisitions();
                allRequisitions = Array.isArray(res) ? res : (res?.requisitions || res?.data || []);
                filteredRequisitions = [...allRequisitions];
                currentPage = 1;
                this.renderStats();
                this.renderTable();
                this.populateDepartmentFilter();
            } catch (e) {
                console.error("Requisitions load error:", e);
                showToast("Failed to load requisitions", "error");
                allRequisitions = [];
                filteredRequisitions = [];
                this.renderTable();
            }
        },

        // ── Stats ──────────────────────────────────────────────────────────────
        renderStats() {
            setText("totalRequisitions",    allRequisitions.length);
            setText("pendingRequisitions",  allRequisitions.filter(r => r.status === "pending").length);
            setText("approvedRequisitions", allRequisitions.filter(r => r.status === "approved").length);
            setText("fulfilledRequisitions",allRequisitions.filter(r => r.status === "fulfilled").length);
        },

        // ── Table ──────────────────────────────────────────────────────────────
        renderTable() {
            const tbody = document.querySelector("#requisitionsTable tbody");
            if (!tbody) return;

            if (!filteredRequisitions.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No requisitions found</td></tr>';
                this.renderPagination(0);
                return;
            }

            const start = (currentPage - 1) * PAGE_SIZE;
            const page  = filteredRequisitions.slice(start, start + PAGE_SIZE);

            tbody.innerHTML = page.map((req, idx) => {
                const itemCount = Array.isArray(req.items) ? req.items.length : (req.item_count || "--");
                const totalVal  = Array.isArray(req.items)
                    ? req.items.reduce((s, i) => s + (parseFloat(i.total_price || 0) || (parseFloat(i.quantity || 0) * parseFloat(i.unit_price || 0))), 0)
                    : (req.total_value || 0);
                return `<tr>
                    <td>${start + idx + 1}</td>
                    <td><code>${esc(req.requisition_no || req.id || "--")}</code></td>
                    <td>${esc(req.department || "--")}</td>
                    <td>${esc(req.purpose || "--")}</td>
                    <td>${itemCount}</td>
                    <td>${currency(totalVal)}</td>
                    <td>${statusBadge(req.status)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-1" onclick="ManageRequisitionsController.viewRequisition('${esc(req.id)}')" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        ${req.status === "pending" ? `
                        <button class="btn btn-sm btn-outline-success me-1" onclick="ManageRequisitionsController.approveRequisition('${esc(req.id)}')" title="Approve">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="ManageRequisitionsController.rejectRequisition('${esc(req.id)}')" title="Reject">
                            <i class="bi bi-x-lg"></i>
                        </button>` : ""}
                    </td>
                </tr>`;
            }).join("");

            this.renderPagination(filteredRequisitions.length);
        },

        renderPagination(total) {
            const container = document.getElementById("requisitionsPagination");
            if (!container) return;
            const pages = Math.ceil(total / PAGE_SIZE);
            if (pages <= 1) { container.innerHTML = ""; return; }

            let html = '<ul class="pagination pagination-sm mb-0">';
            html += `<li class="page-item ${currentPage === 1 ? "disabled" : ""}">
                <a class="page-link" href="#" onclick="ManageRequisitionsController.goPage(${currentPage - 1});return false;">&laquo;</a></li>`;
            for (let p = 1; p <= pages; p++) {
                html += `<li class="page-item ${p === currentPage ? "active" : ""}">
                    <a class="page-link" href="#" onclick="ManageRequisitionsController.goPage(${p});return false;">${p}</a></li>`;
            }
            html += `<li class="page-item ${currentPage === pages ? "disabled" : ""}">
                <a class="page-link" href="#" onclick="ManageRequisitionsController.goPage(${currentPage + 1});return false;">&raquo;</a></li>`;
            html += "</ul>";
            container.innerHTML = html;
        },

        goPage(p) {
            const pages = Math.ceil(filteredRequisitions.length / PAGE_SIZE);
            if (p < 1 || p > pages) return;
            currentPage = p;
            this.renderTable();
        },

        // ── Department filter dropdown ─────────────────────────────────────────
        populateDepartmentFilter() {
            const sel = document.getElementById("departmentFilter");
            if (!sel) return;
            const departments = [...new Set(allRequisitions.map(r => r.department).filter(Boolean))].sort();
            sel.innerHTML = '<option value="">All Departments</option>' +
                departments.map(d => `<option value="${esc(d)}">${esc(d)}</option>`).join("");
        },

        // ── Filters ────────────────────────────────────────────────────────────
        applyFilters() {
            const search  = (document.getElementById("requisitionSearch")?.value || "").toLowerCase();
            const status  = document.getElementById("statusFilter")?.value || "";
            const dept    = document.getElementById("departmentFilter")?.value || "";
            const from    = document.getElementById("dateFromFilter")?.value || "";
            const to      = document.getElementById("dateToFilter")?.value || "";

            filteredRequisitions = allRequisitions.filter(req => {
                const text = JSON.stringify(req).toLowerCase();
                if (search && !text.includes(search)) return false;
                if (status && req.status !== status) return false;
                if (dept   && req.department !== dept) return false;
                const rDate = (req.created_at || req.requisition_date || "").split("T")[0];
                if (from && rDate < from) return false;
                if (to   && rDate > to)   return false;
                return true;
            });
            currentPage = 1;
            this.renderTable();
        },

        // ── Create Requisition modal ──────────────────────────────────────────
        openCreateModal() {
            const form = document.getElementById("requisitionForm");
            if (form) form.reset();
            setVal("requisition_id", "");
            // Reset item rows to a single blank row
            this.resetItemRows();
            const modal = document.getElementById("requisitionModal");
            if (modal) new bootstrap.Modal(modal).show();
        },

        resetItemRows() {
            const container = document.getElementById("requisitionItems");
            if (!container) return;
            container.innerHTML = "";
            this.addItemRow();
        },

        addItemRow() {
            const container = document.getElementById("requisitionItems");
            if (!container) return;
            const idx = container.querySelectorAll(".item-row").length;
            const itemOptions = allInventoryItems.map(i =>
                `<option value="${esc(i.id)}">${esc(i.item_name || i.name)}</option>`
            ).join("");

            const row = document.createElement("div");
            row.className = "row g-2 mb-2 item-row align-items-end";
            row.innerHTML = `
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Item</label>
                    <select class="form-select form-select-sm req-item-select" name="req_item_id[]" required>
                        <option value="">Select Item</option>
                        ${itemOptions}
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Quantity</label>
                    <input type="number" class="form-control form-control-sm req-item-qty" name="req_item_qty[]" min="1" placeholder="Qty" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Unit</label>
                    <input type="text" class="form-control form-control-sm req-item-unit" name="req_item_unit[]" placeholder="pcs">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Description / Specs</label>
                    <input type="text" class="form-control form-control-sm req-item-desc" name="req_item_desc[]" placeholder="Optional">
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-semibold d-block">&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="ManageRequisitionsController.removeItemRow(this)" title="Remove">
                        <i class="bi bi-dash-circle"></i>
                    </button>
                </div>
            `;
            container.appendChild(row);
        },

        removeItemRow(btn) {
            const container = document.getElementById("requisitionItems");
            if (!container) return;
            const rows = container.querySelectorAll(".item-row");
            if (rows.length <= 1) { showToast("At least one item is required", "warning"); return; }
            btn.closest(".item-row").remove();
        },

        async submitRequisition() {
            const container = document.getElementById("requisitionItems");
            const rows = container ? container.querySelectorAll(".item-row") : [];

            const items = [];
            let valid = true;
            rows.forEach(row => {
                const itemId = row.querySelector(".req-item-select")?.value;
                const qty    = parseFloat(row.querySelector(".req-item-qty")?.value) || 0;
                const unit   = row.querySelector(".req-item-unit")?.value?.trim() || "";
                const desc   = row.querySelector(".req-item-desc")?.value?.trim() || "";
                if (!itemId || !qty) { valid = false; return; }
                items.push({ item_id: itemId, quantity: qty, unit, description: desc });
            });

            if (!valid || !items.length) {
                showToast("All item rows must have an item and quantity", "warning");
                return;
            }

            const data = {
                department:  getVal("department"),
                purpose:     getVal("purpose"),
                notes:       getVal("notes"),
                priority:    getVal("priority") || "normal",
                required_by: getVal("required_by"),
                items,
            };

            if (!data.department) { showToast("Department is required", "warning"); return; }
            if (!data.purpose)    { showToast("Purpose is required", "warning"); return; }

            const btn = document.getElementById("submitRequisitionBtn");
            if (btn) { btn.disabled = true; btn.textContent = "Submitting..."; }
            try {
                await window.API.inventory.createRequisition(data);
                showToast("Requisition submitted successfully");
                bootstrap.Modal.getInstance(document.getElementById("requisitionModal"))?.hide();
                await this.loadRequisitions();
            } catch (e) {
                showToast(e.message || "Failed to submit requisition", "error");
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = "Submit Requisition"; }
            }
        },

        // ── View Requisition ──────────────────────────────────────────────────
        async viewRequisition(id) {
            if (!id) return;
            try {
                const res = await window.API.inventory.getRequisition(id);
                currentRequisition = Array.isArray(res) ? res[0] : (res?.requisition || res);
                this.renderRequisitionDetails(currentRequisition);
                const modal = document.getElementById("viewRequisitionModal");
                if (modal) new bootstrap.Modal(modal).show();
            } catch (e) {
                showToast(e.message || "Failed to load requisition details", "error");
            }
        },

        renderRequisitionDetails(req) {
            const container = document.getElementById("requisitionDetailsContent");
            if (!container || !req) return;

            const items = Array.isArray(req.items) ? req.items : [];
            const total = items.reduce((s, i) => s + (parseFloat(i.total_price || 0) || (parseFloat(i.quantity || 0) * parseFloat(i.unit_price || 0))), 0);

            container.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Requisition #</th><td><code>${esc(req.requisition_no || req.id)}</code></td></tr>
                            <tr><th class="text-muted">Department</th><td>${esc(req.department)}</td></tr>
                            <tr><th class="text-muted">Purpose</th><td>${esc(req.purpose)}</td></tr>
                            <tr><th class="text-muted">Priority</th><td><span class="badge bg-${req.priority === "high" ? "danger" : req.priority === "medium" ? "warning" : "secondary"} text-capitalize">${esc(req.priority || "normal")}</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Status</th><td>${statusBadge(req.status)}</td></tr>
                            <tr><th class="text-muted">Requested By</th><td>${esc(req.requested_by || req.created_by || "--")}</td></tr>
                            <tr><th class="text-muted">Date</th><td>${esc((req.created_at || "").split("T")[0] || "--")}</td></tr>
                            <tr><th class="text-muted">Required By</th><td>${esc(req.required_by || "--")}</td></tr>
                        </table>
                    </div>
                </div>
                ${req.notes ? `<div class="alert alert-light border mb-3"><strong>Notes:</strong> ${esc(req.notes)}</div>` : ""}
                <h6 class="fw-bold mb-2">Requested Items</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${items.length ? items.map((item, i) => {
                                const lineTotal = parseFloat(item.total_price || 0) || (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0));
                                return `<tr>
                                    <td>${i + 1}</td>
                                    <td>${esc(item.item_name || item.name || "--")}</td>
                                    <td>${Number(item.quantity || 0).toLocaleString()}</td>
                                    <td>${esc(item.unit || "--")}</td>
                                    <td>${currency(item.unit_price || 0)}</td>
                                    <td class="fw-bold">${currency(lineTotal)}</td>
                                    <td class="text-muted small">${esc(item.description || item.notes || "--")}</td>
                                </tr>`;
                            }).join("") : '<tr><td colspan="7" class="text-center text-muted">No items</td></tr>'}
                            ${total > 0 ? `<tr class="table-light fw-bold"><td colspan="5" class="text-end">Total Estimated Value:</td><td colspan="2">${currency(total)}</td></tr>` : ""}
                        </tbody>
                    </table>
                </div>
            `;

            // Show/hide approve and reject buttons based on status
            const approveBtn = document.getElementById("approveRequisitionBtn");
            const rejectBtn  = document.getElementById("rejectRequisitionBtn");
            const isPending  = req.status === "pending";
            if (approveBtn) approveBtn.style.display = isPending ? "" : "none";
            if (rejectBtn)  rejectBtn.style.display  = isPending ? "" : "none";
        },

        // ── Approve / Reject ──────────────────────────────────────────────────
        async approveRequisition(id) {
            const targetId = id || currentRequisition?.id;
            if (!targetId) return;
            if (!confirm("Approve this requisition?")) return;
            await this._updateStatus(targetId, "approved");
        },

        async rejectRequisition(id) {
            const targetId = id || currentRequisition?.id;
            if (!targetId) return;
            const reason = prompt("Reason for rejection (optional):");
            if (reason === null) return; // user cancelled
            await this._updateStatus(targetId, "rejected", reason);
        },

        async _updateStatus(id, status, notes = "") {
            try {
                await window.API.inventory.updateRequisitionStatus(id, status);
                showToast(`Requisition ${status}`);
                ["viewRequisitionModal"].forEach(mid => {
                    const m = document.getElementById(mid);
                    if (m) bootstrap.Modal.getInstance(m)?.hide();
                });
                await this.loadRequisitions();
            } catch (e) {
                showToast(e.message || `Failed to ${status} requisition`, "error");
            }
        },

        // ── Export ─────────────────────────────────────────────────────────────
        exportCSV() {
            if (!filteredRequisitions.length) { showToast("No data to export", "warning"); return; }
            const headers = ["#", "Requisition #", "Department", "Purpose", "Priority", "Items", "Status", "Date", "Required By"];
            const rows = filteredRequisitions.map((req, i) => [
                i + 1,
                req.requisition_no || req.id || "",
                req.department || "",
                req.purpose || "",
                req.priority || "normal",
                Array.isArray(req.items) ? req.items.length : (req.item_count || 0),
                req.status || "",
                (req.created_at || "").split("T")[0] || "",
                req.required_by || "",
            ]);
            const csv = [headers, ...rows].map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(",")).join("\n");
            const a = document.createElement("a");
            a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
            a.download = `requisitions_${todayStr()}.csv`;
            a.click();
        },

        // ── Event bindings ─────────────────────────────────────────────────────
        bindEvents() {
            document.getElementById("createRequisitionBtn")?.addEventListener("click",  () => this.openCreateModal());
            document.getElementById("exportRequisitionsBtn")?.addEventListener("click", () => this.exportCSV());
            document.getElementById("addItemRowBtn")?.addEventListener("click",         () => this.addItemRow());
            document.getElementById("submitRequisitionBtn")?.addEventListener("click",  () => this.submitRequisition());
            document.getElementById("approveRequisitionBtn")?.addEventListener("click", () => this.approveRequisition());
            document.getElementById("rejectRequisitionBtn")?.addEventListener("click",  () => this.rejectRequisition());

            ["requisitionSearch", "statusFilter", "departmentFilter", "dateFromFilter", "dateToFilter"]
                .forEach(id => {
                    document.getElementById(id)?.addEventListener("input",  () => this.applyFilters());
                    document.getElementById(id)?.addEventListener("change", () => this.applyFilters());
                });
        },
    };

    window.ManageRequisitionsController = ManageRequisitionsController;
    document.addEventListener("DOMContentLoaded", () => ManageRequisitionsController.init());
})();
