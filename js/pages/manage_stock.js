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
    // ── state ─────────────────────────────────────────────────────────────────
    const PAGE_SIZE = 20;
    let allMovements = [];
    let filteredMovements = [];
    let currentPage = 1;
    let allItems = [];

    // ── Controller ────────────────────────────────────────────────────────────
    const ManageStockController = {

        init: async function() {
            await window.AuthContext?.ready();
            if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
                window.location.href = (window.APP_BASE || "") + "/index.php";
                return;
            }
            this.bindEvents();
            window.APIRealtime?.register?.("manageStock", this, ["inventory"]);
            this.loadAll();
        },

        async loadAll() {
            try {
                const result = await window.API.nodeViews.resolve("inventory", "stock-register");
                const sources = result?.data?.sources || result?.sources;
                if (!sources?.items || !sources?.movements) throw new Error("Node returned incomplete stock data");
                const selectedItem = document.getElementById("itemFilter")?.value || "";
                allItems = (sources.items.items || []).filter(item => !item.status || String(item.status).toLowerCase() === "active").map(item => ({
                    ...item,
                    item_name: item.item_name || item.name || "",
                    quantity: item.quantity ?? item.quantity_on_hand ?? item.current_stock ?? 0,
                    current_stock: item.current_stock ?? item.quantity_on_hand ?? item.quantity ?? 0,
                }));
                allMovements = (sources.movements.items || []).map(row => ({
                    ...row,
                    movement_date: row.transaction_date || row.movement_date || "",
                    movement_type: row.transaction_type || row.movement_type || "",
                    movement_category: row.reference_type || row.movement_category || "",
                    unit: row.unit_of_measure || row.unit || "",
                    unit_price: row.unit_cost ?? row.unit_price ?? 0,
                    reference_no: row.reference_id ?? row.reference_no ?? "",
                }));
                this.populateItemFilter();
                const itemFilter = document.getElementById("itemFilter");
                if (itemFilter && [...itemFilter.options].some(option => option.value === selectedItem)) itemFilter.value = selectedItem;
                this.populateStockFormItems();
                this.applyFilters();
                this.renderStats();
            } catch (error) {
                console.error("Node stock register failed:", error);
                showToast("Failed to load fresh stock data", "error");
            }
        },

        refreshData() { return this.loadAll(); },

        populateItemFilter() {
            const sel = document.getElementById("itemFilter");
            if (!sel) return;
            sel.innerHTML = '<option value="">All Items</option>' +
                allItems.map(i =>
                    `<option value="${esc(i.id)}">${esc(i.item_name || i.name)}</option>`
                ).join("");
        },

        populateStockFormItems() {
            ["stock_in_item", "stock_out_item"].forEach(selId => {
                const sel = document.getElementById(selId);
                if (!sel) return;
                const selected = sel.value;
                sel.innerHTML = '<option value="">Select Item</option>' +
                    allItems.map(i =>
                        `<option value="${esc(i.id)}" data-qty="${esc(i.quantity ?? i.current_stock ?? 0)}">${esc(i.item_name || i.name)} (Qty: ${i.quantity ?? i.current_stock ?? 0})</option>`
                    ).join("");
                if ([...sel.options].some(option => option.value === selected)) sel.value = selected;
            });
        },

        // ── Stats ──────────────────────────────────────────────────────────────
        renderStats() {
            const todayDate = todayStr();
            const thisMonth = todayDate.substring(0, 7);

            const todayIn  = allMovements.filter(m => m.movement_date?.startsWith(todayDate) && m.movement_type === "in").length;
            const todayOut = allMovements.filter(m => m.movement_date?.startsWith(todayDate) && m.movement_type === "out").length;
            const monthAdj = allMovements.filter(m => m.movement_date?.startsWith(thisMonth) && m.movement_category === "adjustment").length;

            setText("stockInToday",      todayIn);
            setText("stockOutToday",     todayOut);
            setText("adjustmentsMonth",  monthAdj);
            setText("totalTransactions", allMovements.length);
        },

        // ── Table ──────────────────────────────────────────────────────────────
        renderTable() {
            const tbody = document.querySelector("#stockMovementsTable tbody");
            if (!tbody) return;

            if (!filteredMovements.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No stock movements found</td></tr>';
                this.renderPagination(0);
                return;
            }

            const start = (currentPage - 1) * PAGE_SIZE;
            const page  = filteredMovements.slice(start, start + PAGE_SIZE);

            tbody.innerHTML = page.map((m, idx) => {
                const typeBadge = m.movement_type === "in"
                    ? "success" : m.movement_type === "out"
                    ? "danger" : "secondary";
                return `<tr>
                    <td>${start + idx + 1}</td>
                    <td>${esc(m.movement_date?.split("T")[0] || "--")}</td>
                    <td>${esc(m.item_name || m.item_code || "--")}</td>
                    <td><span class="badge bg-${typeBadge} text-capitalize">${esc(m.movement_type || "--")}</span></td>
                    <td class="fw-bold">${Number(m.quantity || 0).toLocaleString()} ${esc(m.unit || "")}</td>
                    <td>${currency(m.unit_price || 0)}</td>
                    <td>${esc(m.reference_no || m.source || "--")}</td>
                    <td>${esc(m.performed_by || m.created_by || "--")}</td>
                    <td class="text-muted small">${esc(m.notes || "--")}</td>
                </tr>`;
            }).join("");

            this.renderPagination(filteredMovements.length);
        },

        renderPagination(total) {
            const container = document.getElementById("stockPagination");
            if (!container) return;
            const pages = Math.ceil(total / PAGE_SIZE);
            if (pages <= 1) { container.innerHTML = ""; return; }

            let html = '<ul class="pagination pagination-sm mb-0">';
            html += `<li class="page-item ${currentPage === 1 ? "disabled" : ""}">
                <a class="page-link" href="#" onclick="ManageStockController.goPage(${currentPage - 1});return false;">&laquo;</a></li>`;
            for (let p = 1; p <= pages; p++) {
                html += `<li class="page-item ${p === currentPage ? "active" : ""}">
                    <a class="page-link" href="#" onclick="ManageStockController.goPage(${p});return false;">${p}</a></li>`;
            }
            html += `<li class="page-item ${currentPage === pages ? "disabled" : ""}">
                <a class="page-link" href="#" onclick="ManageStockController.goPage(${currentPage + 1});return false;">&raquo;</a></li>`;
            html += "</ul>";
            container.innerHTML = html;
        },

        goPage(p) {
            const pages = Math.ceil(filteredMovements.length / PAGE_SIZE);
            if (p < 1 || p > pages) return;
            currentPage = p;
            this.renderTable();
        },

        // ── Filters ────────────────────────────────────────────────────────────
        applyFilters() {
            const search   = (document.getElementById("stockSearch")?.value || "").toLowerCase();
            const typeFilter = document.getElementById("transactionTypeFilter")?.value || "";
            const itemId   = document.getElementById("itemFilter")?.value || "";
            const dateFrom = document.getElementById("dateFromFilter")?.value || "";
            const dateTo   = document.getElementById("dateToFilter")?.value || "";

            filteredMovements = allMovements.filter(m => {
                const text = JSON.stringify(m).toLowerCase();
                if (search && !text.includes(search)) return false;
                if (typeFilter === "stock_in" && m.movement_type !== "in") return false;
                if (typeFilter === "stock_out" && m.movement_type !== "out") return false;
                if (typeFilter === "adjustment" && m.movement_category !== "adjustment") return false;
                if (typeFilter === "transfer" && m.movement_category !== "transfer") return false;
                if (itemId && String(m.item_id) !== itemId) return false;
                const mDate = m.movement_date?.split("T")[0] || "";
                if (dateFrom && mDate < dateFrom) return false;
                if (dateTo   && mDate > dateTo)   return false;
                return true;
            });
            currentPage = 1;
            this.renderTable();
        },

        clearFilters() {
            ["stockSearch", "transactionTypeFilter", "itemFilter", "dateFromFilter", "dateToFilter"]
                .forEach(id => setVal(id, ""));
            filteredMovements = [...allMovements];
            currentPage = 1;
            this.renderTable();
        },

        // ── Stock In modal ─────────────────────────────────────────────────────
        openStockInModal() {
            const form = document.getElementById("stockInForm");
            if (form) form.reset();
            setVal("stock_in_date", todayStr());
            const modal = document.getElementById("stockInModal");
            if (modal) new bootstrap.Modal(modal).show();
        },

        async saveStockIn() {
            const data = {
                item_id:       getVal("stock_in_item"),
                quantity:      parseFloat(getVal("stock_in_quantity")) || 0,
                unit_cost:     parseFloat(getVal("stock_in_unit_price")) || 0,
                transaction_date: getVal("stock_in_date") || todayStr(),
                adjustment_type: "increase",
                reason:        [getVal("stock_in_source"), getVal("stock_in_supplier"), getVal("stock_in_reference"), getVal("stock_in_notes")].filter(Boolean).join(" | "),
            };
            if (!data.item_id) { showToast("Please select an item", "warning"); return; }
            if (!data.quantity || data.quantity <= 0) { showToast("Quantity must be greater than 0", "warning"); return; }

            const btn = document.getElementById("saveStockInBtn");
            if (btn) { btn.disabled = true; btn.textContent = "Saving..."; }
            try {
                await window.API.inventory.adjustStock(data);
                showToast("Stock in recorded successfully");
                bootstrap.Modal.getInstance(document.getElementById("stockInModal"))?.hide();
                await this.loadAll();
            } catch (e) {
                showToast(e.message || "Failed to record stock in", "error");
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = "Save"; }
            }
        },

        // ── Stock Out modal ────────────────────────────────────────────────────
        openStockOutModal() {
            const form = document.getElementById("stockOutForm");
            if (form) form.reset();
            const modal = document.getElementById("stockOutModal");
            if (modal) new bootstrap.Modal(modal).show();
        },

        updateAvailableQty() {
            const sel = document.getElementById("stock_out_item");
            const display = document.getElementById("available_quantity");
            if (!sel || !display) return;
            const opt = sel.options[sel.selectedIndex];
            display.textContent = opt ? (opt.dataset.qty || "0") : "0";
        },

        async saveStockOut() {
            const data = {
                item_id:       getVal("stock_out_item"),
                quantity:      parseFloat(getVal("stock_out_quantity") || document.getElementById("stock_out_qty")?.value || "0") || 0,
                transaction_date: getVal("stock_out_date") || todayStr(),
                notes:         getVal("stock_out_notes") || "",
                adjustment_type: "decrease",
                reason:        [getVal("stock_out_reason"), getVal("stock_out_destination"), getVal("stock_out_reference"), getVal("stock_out_notes")].filter(Boolean).join(" | "),
            };
            if (!data.item_id) { showToast("Please select an item", "warning"); return; }
            if (!data.quantity || data.quantity <= 0) { showToast("Quantity must be greater than 0", "warning"); return; }

            const avail = parseInt(document.getElementById("available_quantity")?.textContent || "0");
            if (data.quantity > avail) { showToast(`Only ${avail} units available`, "warning"); return; }

            const btn = document.getElementById("saveStockOutBtn");
            if (btn) { btn.disabled = true; btn.textContent = "Saving..."; }
            try {
                await window.API.inventory.adjustStock(data);
                showToast("Stock out recorded successfully");
                bootstrap.Modal.getInstance(document.getElementById("stockOutModal"))?.hide();
                await this.loadAll();
            } catch (e) {
                showToast(e.message || "Failed to record stock out", "error");
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = "Save"; }
            }
        },

        // ── Export ─────────────────────────────────────────────────────────────
        exportCSV() {
            if (!filteredMovements.length) { showToast("No data to export", "warning"); return; }
            const headers = ["#", "Date", "Item", "Type", "Quantity", "Unit Price", "Reference", "Performed By", "Notes"];
            const rows = filteredMovements.map((m, i) => [
                i + 1,
                m.movement_date?.split("T")[0] || "",
                m.item_name || m.item_code || "",
                m.movement_type || "",
                m.quantity || 0,
                m.unit_price || 0,
                m.reference_no || m.source || "",
                m.performed_by || m.created_by || "",
                m.notes || "",
            ]);
            const csv = [headers, ...rows].map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(",")).join("\n");
            KingswayFileLifecycle.exportText(csv, `stock_movements_${todayStr()}.csv`, "text/csv");
        },

        // ── Event bindings ─────────────────────────────────────────────────────
        bindEvents() {
            document.getElementById("addStockBtn")?.addEventListener("click",    () => this.openStockInModal());
            document.getElementById("removeStockBtn")?.addEventListener("click", () => this.openStockOutModal());
            document.getElementById("exportStockBtn")?.addEventListener("click", () => this.exportCSV());
            document.getElementById("saveStockInBtn")?.addEventListener("click", () => this.saveStockIn());
            document.getElementById("clearFiltersBtn")?.addEventListener("click",() => this.clearFilters());
            document.getElementById("stock_out_item")?.addEventListener("change",() => this.updateAvailableQty());

            ["stockSearch", "transactionTypeFilter", "itemFilter", "dateFromFilter", "dateToFilter"]
                .forEach(id => {
                    document.getElementById(id)?.addEventListener("input",  () => this.applyFilters());
                    document.getElementById(id)?.addEventListener("change", () => this.applyFilters());
                });
        },
    };

    window.ManageStockController = ManageStockController;
    document.addEventListener("DOMContentLoaded", () => ManageStockController.init());
})();
