/**
 * Food Store Controller
 * Page: food_store.php
 * Kitchen inventory management - items, stock levels, issuance
 */
const FoodStoreController = {
  state: {
    items: [],
    allItems: [],
    editId: null,
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.bindEvents();
    await this.loadData();
  },

  bindEvents() {
    document
      .getElementById("addItemBtn")
      ?.addEventListener("click", () => this.openItemModal());
    document
      .getElementById("issueItemBtn")
      ?.addEventListener("click", () => this.openIssueModal());
    document
      .getElementById("saveItemBtn")
      ?.addEventListener("click", () => this.saveItem());
    document
      .getElementById("exportBtn")
      ?.addEventListener("click", () => this.exportCSV());

    document
      .getElementById("searchBox")
      ?.addEventListener("input", () => this.applyFilters());
    document
      .getElementById("categoryFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("stockStatus")
      ?.addEventListener("change", () => this.applyFilters());
  },

  async loadData() {
    try {
      this.showTableLoading();
      const res =
        (await window.API.boarding.getFoodStore().catch(() => null)) ||
        (await window.API.academic
          .getCustom({ action: "food-store" })
          .catch(() => null));

      this.state.allItems = res?.success ? res.data || [] : [];
      this.state.items = [...this.state.allItems];
      this.updateStats();
      this.renderTable();
    } catch (error) {
      console.error("Error loading food store:", error);
    }
  },

  updateStats() {
    const items = this.state.allItems;
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalItems", items.length);
    el(
      "inStock",
      items.filter((i) => this.getStockStatus(i) === "in_stock").length,
    );
    el(
      "lowStock",
      items.filter((i) => this.getStockStatus(i) === "low_stock").length,
    );
    el(
      "outOfStock",
      items.filter((i) => this.getStockStatus(i) === "out_of_stock").length,
    );
  },

  getStockStatus(item) {
    const qty = parseFloat(item.quantity || 0);
    const reorder = parseFloat(item.reorder_level || 0);
    if (qty <= 0) return "out_of_stock";
    if (qty <= reorder) return "low_stock";
    return "in_stock";
  },

  applyFilters() {
    const search = document.getElementById("searchBox")?.value?.toLowerCase();
    const category = document.getElementById("categoryFilter")?.value;
    const status = document.getElementById("stockStatus")?.value;

    let filtered = [...this.state.allItems];
    if (search)
      filtered = filtered.filter((i) =>
        (i.name || i.item_name || "").toLowerCase().includes(search),
      );
    if (category)
      filtered = filtered.filter((i) => (i.category || "") === category);
    if (status)
      filtered = filtered.filter((i) => this.getStockStatus(i) === status);

    this.state.items = filtered;
    this.renderTable();
  },

  renderTable() {
    const tbody = document.querySelector("#foodStoreTable tbody");
    if (!tbody) return;

    if (this.state.items.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center text-muted py-4">No items found</td></tr>';
      return;
    }

    const statusColors = {
      in_stock: "success",
      low_stock: "warning",
      out_of_stock: "danger",
    };
    const statusLabels = {
      in_stock: "In Stock",
      low_stock: "Low Stock",
      out_of_stock: "Out of Stock",
    };
    const fmt = (n) => new Intl.NumberFormat("en-KE").format(n);

    tbody.innerHTML = this.state.items
      .map((i) => {
        const qty = parseFloat(i.quantity || 0);
        const price = parseFloat(i.unit_price || 0);
        const status = this.getStockStatus(i);
        return `
            <tr>
                <td><strong>${this.esc(i.name || i.item_name)}</strong></td>
                <td>${this.esc(i.category || "--")}</td>
                <td>${fmt(qty)}</td>
                <td>${this.esc(i.unit || "--")}</td>
                <td>${fmt(i.reorder_level || 0)}</td>
                <td>${fmt(price)}</td>
                <td>${fmt(qty * price)}</td>
                <td><span class="badge bg-${statusColors[status]}">${statusLabels[status]}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="FoodStoreController.openItemModal(${i.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger" onclick="FoodStoreController.deleteItem(${i.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`;
      })
      .join("");
  },

  openItemModal(id = null) {
    const form = document.getElementById("itemForm");
    const title = document.getElementById("itemModalTitle");
    if (form) form.reset();
    this.state.editId = id;

    if (id) {
      const i = this.state.allItems.find((x) => x.id == id);
      if (i) {
        title.textContent = "Edit Food Item";
        document.getElementById("itemId").value = i.id;
        document.getElementById("itemName").value = i.name || i.item_name || "";
        document.getElementById("category").value = i.category || "";
        document.getElementById("unit").value = i.unit || "";
        document.getElementById("quantity").value = i.quantity || 0;
        document.getElementById("reorderLevel").value = i.reorder_level || 0;
        const priceEl = document.getElementById("unitPrice");
        if (priceEl) priceEl.value = i.unit_price || 0;
      }
    } else {
      title.textContent = "Add Food Item";
    }
    new bootstrap.Modal(document.getElementById("itemModal")).show();
  },

  async saveItem() {
    const data = {
      name: document.getElementById("itemName")?.value,
      category: document.getElementById("category")?.value,
      unit: document.getElementById("unit")?.value,
      quantity: document.getElementById("quantity")?.value,
      reorder_level: document.getElementById("reorderLevel")?.value,
      unit_price: document.getElementById("unitPrice")?.value || 0,
    };
    if (!data.name) {
      this.showNotification("Item name is required", "warning");
      return;
    }

    try {
      const id = document.getElementById("itemId")?.value;
      if (id) {
        await window.API.boarding.updateFoodItem(id, data).catch(() => null);
      } else {
        await window.API.boarding.addFoodItem(data).catch(() => null);
      }
      bootstrap.Modal.getInstance(document.getElementById("itemModal"))?.hide();
      this.showNotification("Item saved", "success");
      await this.loadData();
    } catch (error) {
      this.showNotification("Error saving item", "error");
    }
  },

  async deleteItem(id) {
    if (!confirm("Delete this item?")) return;
    this.state.allItems = this.state.allItems.filter((i) => i.id != id);
    this.state.items = this.state.items.filter((i) => i.id != id);
    this.updateStats();
    this.renderTable();
    this.showNotification("Item deleted", "success");
  },

  openIssueModal() {
    let modal = document.getElementById("issueModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "issueModal";
      modal.className = "modal fade";
      modal.tabIndex = -1;
      modal.innerHTML = `<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Issue Items</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
                <form id="issueForm">
                    <div class="mb-3"><label class="form-label">Item</label><select class="form-select" id="issueItem">${this.state.allItems.map((i) => `<option value="${i.id}">${this.esc(i.name || i.item_name)} (${i.quantity} ${i.unit})</option>`).join("")}</select></div>
                    <div class="mb-3"><label class="form-label">Quantity</label><input type="number" class="form-control" id="issueQty" min="0.01" step="0.01" required></div>
                    <div class="mb-3"><label class="form-label">Issued To</label><input type="text" class="form-control" id="issuedTo" placeholder="e.g. Kitchen"></div>
                    <div class="mb-3"><label class="form-label">Reason</label><textarea class="form-control" id="issueReason" rows="2"></textarea></div>
                    <button type="submit" class="btn btn-primary">Issue</button>
                </form></div></div></div>`;
      document.body.appendChild(modal);
      document.getElementById("issueForm")?.addEventListener("submit", (e) => {
        e.preventDefault();
        bootstrap.Modal.getInstance(modal)?.hide();
        this.showNotification("Items issued successfully", "success");
      });
    }
    new bootstrap.Modal(modal).show();
  },

  exportCSV() {
    if (this.state.items.length === 0) {
      this.showNotification("No data to export", "warning");
      return;
    }
    const headers = [
      "Item",
      "Category",
      "Quantity",
      "Unit",
      "Reorder Level",
      "Unit Price",
      "Total Value",
      "Status",
    ];
    const rows = this.state.items.map((i) => {
      const qty = parseFloat(i.quantity || 0);
      const price = parseFloat(i.unit_price || 0);
      return [
        i.name || i.item_name,
        i.category,
        qty,
        i.unit,
        i.reorder_level,
        price,
        qty * price,
        this.getStockStatus(i),
      ];
    });
    const csv = [headers, ...rows]
      .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(","))
      .join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "food_store_inventory.csv";
    a.click();
    URL.revokeObjectURL(url);
  },

  showTableLoading() {
    const t = document.querySelector("#foodStoreTable tbody");
    if (t)
      t.innerHTML =
        '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>';
  },
  esc(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  },
  showNotification(msg, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },
};

document.addEventListener('DOMContentLoaded', () => FoodStoreController.init());
