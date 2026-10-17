/**
 * Food Store Controller
 * Page: food_store.php
 * Kitchen inventory management - items, stock levels, issuance
 * Backed by the inventory module (inventory_items / vw_inventory_health).
 */
const FoodStoreController = {
  state: {
    items: [],
    allItems: [],
    editId: null,
    categories: [],
    locations: [],
    suppliers: [],
    referenceCache: null,
  },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("catering", "food-store-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Food Store Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.bindEvents();
    await this.loadReferencesComposed();
    await this.loadReferenceData();
    await this.loadData();
    window.APIRealtime?.register?.("food_store", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.loadReferenceData();
        await this.loadData();
      }
    }, ["inventory"]);
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
      .getElementById("saveIssueBtn")
      ?.addEventListener("click", () => this.issueItems());
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

  async loadReferenceData() {
    try {
      const cache = this.state.referenceCache;
      const cat = cache?.categories !== undefined ? cache.categories : (await window.API.inventory.listCategories().catch(() => null)) || {};
      this.state.categories = Array.isArray(cat.categories) ? cat.categories : [];

      const loc = cache?.locations !== undefined ? cache.locations : (await window.API.inventory
        .listLocations({ limit: 200 })
        .catch(() => null)) || {};
      this.state.locations = Array.isArray(loc.locations) ? loc.locations : [];

      const sup = cache?.suppliers !== undefined ? cache.suppliers : (await window.API.inventory
        .listSuppliers({ limit: 200 })
        .catch(() => null)) || {};
      this.state.suppliers = Array.isArray(sup.suppliers) ? sup.suppliers : [];

      this.populateReferenceSelects();
    } catch (error) {
      console.error("Error loading reference data:", error);
    }
  },

  populateReferenceSelects() {
    const catSel = document.getElementById("category");
    if (catSel) {
      catSel.innerHTML = this.state.categories
        .map(
          (c) =>
            `<option value="${this.esc(c.category_name)}">${this.esc(
              c.category_name,
            )}</option>`,
        )
        .join("");
    }
    const catFilter = document.getElementById("categoryFilter");
    if (catFilter) {
      catFilter.innerHTML =
        '<option value="">All Categories</option>' +
        this.state.categories
          .map(
            (c) =>
              `<option value="${this.esc(c.category_name)}">${this.esc(
                c.category_name,
              )}</option>`,
          )
          .join("");
    }
    const locSel = document.getElementById("storageLocation");
    if (locSel) {
      locSel.innerHTML = this.state.locations
        .map(
          (l) =>
            `<option value="${l.id}">${this.esc(l.location_name)}</option>`,
        )
        .join("");
    }
  },

  mapItem(i) {
    return {
      id: i.id,
      name: i.name || i.item_name || "",
      category: i.category_name || "",
      quantity: parseFloat(i.current_quantity ?? i.quantity ?? 0),
      unit: i.unit || "",
      reorder_level: parseFloat(i.reorder_level ?? 0),
      unit_price: parseFloat(i.unit_cost ?? i.unit_price ?? 0),
      location_id: i.location_id ?? null,
      supplier_name: i.supplier_name || "",
    };
  },

  async loadData() {
    try {
      this.showTableLoading();
      const cache = this.state.referenceCache;
      const res = cache?.items !== undefined ? cache.items : (await window.API.inventory
        .listItems({ limit: 200, status: "active" })
        .catch(() => null)) || {};
      const rows = Array.isArray(res.items) ? res.items : [];
      this.state.allItems = rows.map((i) => this.mapItem(i));
      this.state.items = [...this.state.allItems];
      this.updateStats();
      this.renderTable();
    } catch (error) {
      console.error("Error loading food store:", error);
      this.renderTable();
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
        const locEl = document.getElementById("storageLocation");
        if (locEl) locEl.value = i.location_id || "";
      }
    } else {
      title.textContent = "Add Food Item";
    }
    new bootstrap.Modal(document.getElementById("itemModal")).show();
  },

  async saveItem() {
    const id = document.getElementById("itemId")?.value;
    const name = document.getElementById("itemName")?.value?.trim();
    const catName = document.getElementById("category")?.value;
    const unit = document.getElementById("unit")?.value;
    const quantity = parseFloat(document.getElementById("quantity")?.value || 0);
    const reorder = parseFloat(
      document.getElementById("reorderLevel")?.value || 0,
    );
    const price = parseFloat(document.getElementById("unitPrice")?.value || 0);
    const locationId = document.getElementById("storageLocation")?.value || null;
    const supplierName =
      document.getElementById("supplier")?.value?.trim() || "";

    if (!name) {
      this.showNotification("Item name is required", "warning");
      return;
    }
    const categoryId = this.state.categories.find(
      (c) => c.category_name === catName,
    )?.id;
    if (!categoryId) {
      this.showNotification("Select a valid category", "warning");
      return;
    }
    const supplierId = this.state.suppliers.find(
      (s) => (s.supplier_name || "").toLowerCase() === supplierName.toLowerCase(),
    )?.id || null;

    try {
      if (id) {
        const item = this.state.allItems.find((x) => x.id == id);
        await window.API.inventory.update(id, {
          item_name: name,
          category_id: categoryId,
          unit_of_measure: unit,
          unit_cost: price,
          reorder_level: reorder,
          location_id: locationId,
          supplier_id: supplierId,
        });
        const prevQty = item ? parseFloat(item.quantity || 0) : 0;
        if (prevQty !== quantity) {
          const delta = quantity - prevQty;
          await window.API.inventory.recordMovement({
            transaction_type: delta > 0 ? "adjustment_in" : "adjustment_out",
            item_id: parseInt(id, 10),
            quantity: Math.abs(delta),
            unit_cost: price,
            notes: "Food store quantity update",
            transaction_date: new Date().toISOString().slice(0, 10),
            update_quantity: true,
          });
        }
      } else {
        await window.API.inventory.create({
          item_name: name,
          item_code: this.generateCode(),
          category_id: categoryId,
          unit_of_measure: unit,
          quantity_on_hand: quantity,
          unit_cost: price,
          reorder_level: reorder,
          location_id: locationId,
          supplier_id: supplierId,
        });
      }
      bootstrap.Modal.getInstance(document.getElementById("itemModal"))?.hide();
      this.showNotification("Item saved", "success");
      await this.loadData();
    } catch (error) {
      console.error("Error saving item:", error);
      this.showNotification("Error saving item", "error");
    }
  },

  async deleteItem(id) {
    if (!(await window.confirmAction('Confirm Deletion', "Delete this item?", { confirmText: 'Delete', danger: true }))) return;
    try {
      await window.API.inventory.delete(id);
      this.showNotification("Item deleted", "success");
      await this.loadData();
    } catch (error) {
      console.error("Error deleting item:", error);
      this.showNotification("Error deleting item", "error");
    }
  },

  generateCode() {
    return "FS-" + Date.now().toString(36).toUpperCase();
  },

  openIssueModal() {
    const modal = document.getElementById("issueModal");
    if (!modal) return;
    const sel = document.getElementById("issueItem");
    if (sel) {
      sel.innerHTML = this.state.allItems
        .map(
          (i) =>
            `<option value="${i.id}">${this.esc(i.name)} (${i.quantity} ${i.unit})</option>`,
        )
        .join("");
      sel.onchange = () => this.updateIssueAvailability();
      this.updateIssueAvailability();
    }
    const dateEl = document.getElementById("issueDate");
    if (dateEl) dateEl.value = new Date().toISOString().slice(0, 10);
    new bootstrap.Modal(modal).show();
  },

  updateIssueAvailability() {
    const sel = document.getElementById("issueItem");
    const item = this.state.allItems.find((x) => x.id == sel?.value);
    const qtyEl = document.getElementById("availableQty");
    const unitEl = document.getElementById("availableUnit");
    if (qtyEl) qtyEl.textContent = item ? item.quantity : 0;
    if (unitEl) unitEl.textContent = item ? item.unit : "";
  },

  async issueItems() {
    const id = document.getElementById("issueItem")?.value;
    const qty = parseFloat(document.getElementById("issueQuantity")?.value || 0);
    const issuedTo = document.getElementById("issuedTo")?.value?.trim();
    const purpose = document.getElementById("purpose")?.value;
    const date = document.getElementById("issueDate")?.value;
    const notes = document.getElementById("issueNotes")?.value?.trim() || "";
    if (!id || !qty || !issuedTo || !date) {
      this.showNotification("Complete all required fields", "warning");
      return;
    }
    const item = this.state.allItems.find((x) => x.id == id);
    if (!item) return;
    try {
      await window.API.inventory.recordMovement({
        transaction_type: "issue",
        item_id: parseInt(id, 10),
        quantity: qty,
        unit_cost: parseFloat(item.unit_price || 0),
        notes: `Issued to ${issuedTo} (${purpose || "other"})${notes ? ": " + notes : ""}`,
        transaction_date: date,
        update_quantity: true,
      });
      bootstrap.Modal.getInstance(document.getElementById("issueModal"))?.hide();
      this.showNotification("Items issued", "success");
      await this.loadData();
    } catch (error) {
      console.error("Error issuing items:", error);
      this.showNotification("Error issuing items", "error");
    }
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
    KingswayFileLifecycle.exportText(csv, "food_store_inventory.csv", "text/csv");
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
  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("catering", "food-store-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Food Store Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.bindEvents();
    await this.loadReferencesComposed();
    await this.loadReferenceData();
    await this.loadData();
    window.APIRealtime?.register?.("food_store", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.loadReferenceData();
        await this.loadData();
      }
    }, ["inventory"]);
  },
};

document.addEventListener('DOMContentLoaded', () => FoodStoreController.init());

window.FoodStoreController = FoodStoreController;