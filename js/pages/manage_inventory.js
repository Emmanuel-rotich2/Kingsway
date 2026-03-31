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

    // ── state ─────────────────────────────────────────────────────────────────
    const PAGE_SIZE = 15;
    let allItems = [];
    let filteredItems = [];
    let currentPage = 1;

    // ── Controller ────────────────────────────────────────────────────────────
    const ManageInventoryController = {

        init() {
            if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
                window.location.href = (window.APP_BASE || "") + "/index.php";
                return;
            }
            this.bindEvents();
            this.loadAll();
        },

        async loadAll() {
            await Promise.all([
                this.loadStats(),
                this.loadItems(),
                this.loadCategories(),
                this.loadLocations(),
            ]);
        },

        // ── Stats ──────────────────────────────────────────────────────────────
        async loadStats() {
            try {
                const [lowStock, valuation] = await Promise.all([
                    window.API.inventory.getLowStockItems(),
                    window.API.inventory.getStockValuation(),
                ]);
                const low = Array.isArray(lowStock) ? lowStock : (lowStock?.items || []);
                const val = valuation?.total_value ?? valuation ?? 0;

                // item counts come from allItems once loaded; refresh after items load
                this._lowStockCount = low.length;
                this._totalValue = val;
                this._pendingRequisitions = valuation?.pending_requisitions ?? 0;
                this._expiringSoon = valuation?.expiring_soon ?? 0;
                this.renderStats();
            } catch (e) {
                console.error("Stats load error:", e);
            }
        },

        renderStats() {
            const inStock   = allItems.filter(i => (i.quantity ?? i.current_stock ?? 0) > (i.reorder_level ?? 0)).length;
            const outOfStock = allItems.filter(i => (i.quantity ?? i.current_stock ?? 0) <= 0).length;
            const low       = allItems.filter(i => {
                const qty = i.quantity ?? i.current_stock ?? 0;
                const rl  = i.reorder_level ?? 0;
                return qty > 0 && qty <= rl;
            }).length;

            setText("totalItems",        allItems.length);
            setText("inStockItems",      inStock);
            setText("lowStockItems",     low);
            setText("outOfStockItems",   outOfStock);
            setText("totalStockValue",   currency(this._totalValue));
            setText("pendingRequisitions", this._pendingRequisitions ?? 0);
            setText("expiringSoon",      this._expiringSoon ?? 0);
        },

        // ── Items ──────────────────────────────────────────────────────────────
        async loadItems() {
            try {
                const res = await window.API.inventory.getItemsWithStock();
                allItems = Array.isArray(res) ? res : (res?.items || res?.data || []);
                filteredItems = [...allItems];
                currentPage = 1;
                this.renderStats();
                this.renderTable();
            } catch (e) {
                console.error("Items load error:", e);
                showToast("Failed to load inventory items", "error");
                allItems = [];
                filteredItems = [];
                this.renderTable();
            }
        },

        renderTable() {
            const tbody = document.querySelector("#inventoryTable tbody");
            if (!tbody) return;

            if (!filteredItems.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No inventory items found</td></tr>';
                this.renderPagination(0);
                return;
            }

            const start   = (currentPage - 1) * PAGE_SIZE;
            const pageItems = filteredItems.slice(start, start + PAGE_SIZE);

            tbody.innerHTML = pageItems.map((item, idx) => {
                const qty  = item.quantity ?? item.current_stock ?? 0;
                const rl   = item.reorder_level ?? 0;
                let badge, status;
                if (qty <= 0)      { badge = "danger";  status = "Out of Stock"; }
                else if (qty <= rl){ badge = "warning"; status = "Low Stock"; }
                else               { badge = "success"; status = "In Stock"; }

                return `<tr>
                    <td>${start + idx + 1}</td>
                    <td><code>${esc(item.item_code || item.code || "--")}</code></td>
                    <td>${esc(item.item_name || item.name || "--")}</td>
                    <td>${esc(item.category_name || item.category || "--")}</td>
                    <td>${esc(item.location_name || item.location || "--")}</td>
                    <td class="fw-bold">${qty} ${esc(item.unit || "")}</td>
                    <td><span class="badge bg-${badge}">${status}</span></td>
                    <td>${currency(item.unit_price || 0)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="ManageInventoryController.openEditModal(${JSON.stringify(item).replace(/"/g,"&quot;")})" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="ManageInventoryController.deleteItem('${esc(item.id)}')" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>`;
            }).join("");

            this.renderPagination(filteredItems.length);
        },

        renderPagination(total) {
            const container = document.getElementById("inventoryPagination");
            if (!container) return;
            const pages = Math.ceil(total / PAGE_SIZE);
            if (pages <= 1) { container.innerHTML = ""; return; }

            let html = '<ul class="pagination pagination-sm mb-0">';
            html += `<li class="page-item ${currentPage === 1 ? "disabled" : ""}">
                <a class="page-link" href="#" onclick="ManageInventoryController.goPage(${currentPage - 1});return false;">&laquo;</a></li>`;
            for (let p = 1; p <= pages; p++) {
                html += `<li class="page-item ${p === currentPage ? "active" : ""}">
                    <a class="page-link" href="#" onclick="ManageInventoryController.goPage(${p});return false;">${p}</a></li>`;
            }
            html += `<li class="page-item ${currentPage === pages ? "disabled" : ""}">
                <a class="page-link" href="#" onclick="ManageInventoryController.goPage(${currentPage + 1});return false;">&raquo;</a></li>`;
            html += "</ul>";
            container.innerHTML = html;
        },

        goPage(p) {
            const pages = Math.ceil(filteredItems.length / PAGE_SIZE);
            if (p < 1 || p > pages) return;
            currentPage = p;
            this.renderTable();
        },

        // ── Filters ────────────────────────────────────────────────────────────
        applyFilters() {
            const search   = (document.getElementById("itemSearch")?.value || "").toLowerCase();
            const category = document.getElementById("categoryFilter")?.value || "";
            const status   = document.getElementById("stockStatusFilter")?.value || "";
            const location = document.getElementById("locationFilter")?.value || "";

            filteredItems = allItems.filter(item => {
                const name = (item.item_name || item.name || "").toLowerCase();
                const code = (item.item_code || item.code || "").toLowerCase();
                if (search && !name.includes(search) && !code.includes(search)) return false;
                if (category && String(item.category_id || item.category || "") !== category) return false;
                if (location && String(item.location_id || item.location || "") !== location) return false;
                if (status) {
                    const qty = item.quantity ?? item.current_stock ?? 0;
                    const rl  = item.reorder_level ?? 0;
                    if (status === "in_stock"    && !(qty > rl))          return false;
                    if (status === "low_stock"   && !(qty > 0 && qty <= rl)) return false;
                    if (status === "out_of_stock"&& qty > 0)               return false;
                }
                return true;
            });

            currentPage = 1;
            this.renderTable();
        },

        // ── Categories & Locations for dropdowns ──────────────────────────────
        async loadCategories() {
            try {
                const res = await window.API.inventory.listCategories();
                const cats = Array.isArray(res) ? res : (res?.categories || res?.data || []);
                const sel  = document.getElementById("categoryFilter");
                const formSel = document.getElementById("category");
                [sel, formSel].forEach(el => {
                    if (!el) return;
                    const existing = el.querySelectorAll("option[value='']");
                    // keep the first blank option
                    const blank = el.options[0]?.value === "" ? el.options[0].outerHTML : '<option value="">All Categories</option>';
                    el.innerHTML = blank + cats.map(c =>
                        `<option value="${esc(c.id || c.category_id)}">${esc(c.name || c.category_name)}</option>`
                    ).join("");
                });
            } catch (e) { console.error("Categories load error:", e); }
        },

        async loadLocations() {
            try {
                const res = await window.API.inventory.listLocations();
                const locs = Array.isArray(res) ? res : (res?.locations || res?.data || []);
                const sel  = document.getElementById("locationFilter");
                const formSel = document.getElementById("location");
                [sel, formSel].forEach(el => {
                    if (!el) return;
                    const blank = el.options[0]?.value === "" ? el.options[0].outerHTML : '<option value="">All Locations</option>';
                    el.innerHTML = blank + locs.map(l =>
                        `<option value="${esc(l.id || l.location_id)}">${esc(l.name || l.location_name)}</option>`
                    ).join("");
                });
            } catch (e) { console.error("Locations load error:", e); }
        },

        // ── Modal – Add / Edit Item ────────────────────────────────────────────
        openAddModal() {
            const form = document.getElementById("itemForm");
            if (form) form.reset();
            setVal("item_id", "");
            const modal = document.getElementById("itemModal");
            if (modal) new bootstrap.Modal(modal).show();
        },

        openEditModal(item) {
            if (typeof item === "string") {
                try { item = JSON.parse(item); } catch { return; }
            }
            setVal("item_id",      item.id || "");
            setVal("item_code",    item.item_code || item.code || "");
            setVal("item_name",    item.item_name || item.name || "");
            setVal("category",     item.category_id || item.category || "");
            setVal("location",     item.location_id || item.location || "");
            setVal("quantity",     item.quantity ?? item.current_stock ?? "");
            setVal("unit",         item.unit || "");
            setVal("reorder_level",item.reorder_level ?? "");
            setVal("unit_price",   item.unit_price || "");
            setVal("supplier",     item.supplier || "");
            setVal("description",  item.description || "");
            const modal = document.getElementById("itemModal");
            if (modal) new bootstrap.Modal(modal).show();
        },

        async saveItem() {
            const id = getVal("item_id");
            const data = {
                item_code:    getVal("item_code"),
                item_name:    getVal("item_name"),
                category_id:  getVal("category"),
                location_id:  getVal("location"),
                quantity:     parseFloat(getVal("quantity")) || 0,
                unit:         getVal("unit"),
                reorder_level:parseFloat(getVal("reorder_level")) || 0,
                unit_price:   parseFloat(getVal("unit_price")) || 0,
                supplier:     getVal("supplier"),
                description:  getVal("description"),
            };
            if (!data.item_name) { showToast("Item name is required", "warning"); return; }

            const btn = document.getElementById("saveItemBtn");
            if (btn) { btn.disabled = true; btn.textContent = "Saving..."; }
            try {
                if (id) {
                    await window.API.inventory.update(id, data);
                    showToast("Item updated successfully");
                } else {
                    await window.API.inventory.create(data);
                    showToast("Item created successfully");
                }
                bootstrap.Modal.getInstance(document.getElementById("itemModal"))?.hide();
                await this.loadItems();
            } catch (e) {
                showToast(e.message || "Failed to save item", "error");
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = "Save Item"; }
            }
        },

        async deleteItem(id) {
            if (!id || !confirm("Delete this inventory item? This cannot be undone.")) return;
            try {
                await window.API.inventory.delete(id);
                showToast("Item deleted");
                await this.loadItems();
            } catch (e) {
                showToast(e.message || "Failed to delete item", "error");
            }
        },

        // ── Stock adjust (restock / issue) ─────────────────────────────────────
        openRestockModal() {
            this._adjustType = "in";
            this._openAdjustModal("Restock / Stock In", "in");
        },

        openIssueModal() {
            this._adjustType = "out";
            this._openAdjustModal("Issue / Stock Out", "out");
        },

        _openAdjustModal(title, type) {
            // Reuse stockInModal if present, otherwise fallback to a simple prompt
            const modal = document.getElementById("stockInModal");
            if (!modal) {
                const item = prompt("Enter item ID:");
                if (!item) return;
                const qty   = parseFloat(prompt("Quantity:") || "0");
                const notes = prompt("Notes:") || "";
                this._submitAdjust({ item_id: item, quantity: qty, movement_type: type, notes });
                return;
            }
            modal.querySelector(".modal-title") && (modal.querySelector(".modal-title").textContent = title);
            const form = modal.querySelector("form");
            if (form) form.reset();
            new bootstrap.Modal(modal).show();
        },

        async submitAdjust() {
            const data = {
                item_id:       getVal("stock_in_item") || getVal("stock_out_item"),
                quantity:      parseFloat(getVal("stock_in_quantity") || getVal("stock_out_quantity")) || 0,
                unit_price:    parseFloat(getVal("stock_in_unit_price") || "0") || 0,
                source:        getVal("stock_in_source") || "",
                movement_date: getVal("stock_in_date") || new Date().toISOString().split("T")[0],
                supplier:      getVal("stock_in_supplier") || "",
                reference_no:  getVal("stock_in_reference") || "",
                notes:         getVal("stock_in_notes") || "",
                movement_type: this._adjustType || "in",
            };
            await this._submitAdjust(data);
        },

        async _submitAdjust(data) {
            if (!data.item_id || !data.quantity) {
                showToast("Item and quantity are required", "warning");
                return;
            }
            try {
                await window.API.inventory.adjustStock(data);
                showToast("Stock adjusted successfully");
                ["stockInModal", "stockOutModal"].forEach(id => {
                    const m = document.getElementById(id);
                    if (m) bootstrap.Modal.getInstance(m)?.hide();
                });
                await this.loadItems();
                await this.loadStats();
            } catch (e) {
                showToast(e.message || "Failed to adjust stock", "error");
            }
        },

        // ── Export ─────────────────────────────────────────────────────────────
        exportCSV() {
            if (!filteredItems.length) { showToast("No data to export", "warning"); return; }
            const headers = ["Code", "Name", "Category", "Location", "Quantity", "Unit", "Reorder Level", "Unit Price", "Status"];
            const rows = filteredItems.map(item => {
                const qty  = item.quantity ?? item.current_stock ?? 0;
                const rl   = item.reorder_level ?? 0;
                const status = qty <= 0 ? "Out of Stock" : qty <= rl ? "Low Stock" : "In Stock";
                return [
                    item.item_code || item.code || "",
                    item.item_name || item.name || "",
                    item.category_name || item.category || "",
                    item.location_name || item.location || "",
                    qty,
                    item.unit || "",
                    rl,
                    item.unit_price || 0,
                    status,
                ];
            });
            const csv = [headers, ...rows].map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(",")).join("\n");
            const a = document.createElement("a");
            a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
            a.download = `inventory_${new Date().toISOString().split("T")[0]}.csv`;
            a.click();
        },

        // ── Event bindings ─────────────────────────────────────────────────────
        bindEvents() {
            document.getElementById("addItemBtn")?.addEventListener("click", () => this.openAddModal());
            document.getElementById("restockBtn")?.addEventListener("click", () => this.openRestockModal());
            document.getElementById("issueStockBtn")?.addEventListener("click", () => this.openIssueModal());
            document.getElementById("exportInventoryBtn")?.addEventListener("click", () => this.exportCSV());
            document.getElementById("saveItemBtn")?.addEventListener("click", () => this.saveItem());

            ["itemSearch", "categoryFilter", "stockStatusFilter", "locationFilter"].forEach(id => {
                document.getElementById(id)?.addEventListener("input", () => this.applyFilters());
                document.getElementById(id)?.addEventListener("change", () => this.applyFilters());
            });
        },
    };

    window.ManageInventoryController = ManageInventoryController;
    document.addEventListener("DOMContentLoaded", () => ManageInventoryController.init());
})();
