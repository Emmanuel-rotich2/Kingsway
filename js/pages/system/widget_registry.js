/**
 * Widget Registry Controller
 * Page: widget_registry.php
 * Dedicated system-admin controller — full CRUD over dashboard widget
 * registrations via window.API.system.getWidgets /
 * createWidget / updateWidget / deleteWidget.
 */
const WidgetRegistryController = {
  state: {
    rows: [],
    filtered: [],
    search: "",
    page: 1,
    pageSize: 25,
    loading: false,
    initialized: false,
    eventsBound: false,
    initializationPromise: null,
    searchTimer: null,
    modal: null,
  },

  elements: {},

  async init() {
    if (this.state.initializationPromise) return this.state.initializationPromise;
    this.state.initializationPromise = this.initialize();
    return this.state.initializationPromise;
  },

  async initialize() {
    try {
      if (!window.AuthContext?.ready) throw new Error("Authentication context is unavailable.");
      await window.AuthContext.ready();
      if (!window.AuthContext.isAuthenticated?.()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }
      if (!this.hasAccess()) {
        this.renderForbidden();
        return;
      }
      if (!window.API?.system?.getWidgets) throw new Error("The Widget Registry API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[WidgetRegistryController] Initialization failed:", error);
      this.showState(error?.message || "Widget Registry could not initialize.", "danger");
      this.showTableMessage("Widget Registry could not initialize.", "text-danger");
    }
  },

  hasAccess() {
    const roles = (window.AuthContext.getRoles?.() || []).map((role) =>
      String(typeof role === "string" ? role : role?.name || role?.role_name || "")
        .trim()
        .toLowerCase(),
    );
    return Boolean(
      roles.includes("system administrator") ||
        window.AuthContext.hasRole?.("System Administrator") ||
        window.AuthContext.hasPermission?.("*"),
    );
  },

  cacheElements() {
    this.elements = {
      state: document.getElementById("widgetRegistryState"),
      search: document.getElementById("widgetRegistrySearch"),
      count: document.getElementById("widgetRegistryCount"),
      body: document.getElementById("widgetRegistryTableBody"),
      rowTemplate: document.getElementById("widgetRegistryRowTemplate"),
      exportCsvBtn: document.getElementById("widgetRegistryExportCsvBtn"),
      printBtn: document.getElementById("widgetRegistryPrintBtn"),
      refreshBtn: document.getElementById("widgetRegistryRefreshBtn"),
      createBtn: document.getElementById("widgetRegistryCreateBtn"),
      previousPage: document.getElementById("widgetRegistryPreviousPage"),
      nextPage: document.getElementById("widgetRegistryNextPage"),
      pageIndicator: document.getElementById("widgetRegistryPageIndicator"),
      modal: document.getElementById("widgetRegistryModal"),
      form: document.getElementById("widgetRegistryForm"),
      editId: document.getElementById("widgetRegistryEditId"),
      key: document.getElementById("widgetRegistryKey"),
      name: document.getElementById("widgetRegistryName"),
      type: document.getElementById("widgetRegistryType"),
      permission: document.getElementById("widgetRegistryPermission"),
      status: document.getElementById("widgetRegistryStatus"),
      description: document.getElementById("widgetRegistryDescription"),
      saveBtn: document.getElementById("widgetRegistrySaveBtn"),
      modalTitle: document.getElementById("widgetRegistryModalTitle"),
    };
  },

  escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  },

  notify(message, type) {
    if (typeof window.showNotification === "function") window.showNotification(message, type);
    else if (window.API?.showNotification) window.API.showNotification(message, type);
  },

  showState(message, type) {
    if (!this.elements.state) return;
    this.elements.state.className = "alert alert-" + (type || "info");
    this.elements.state.textContent = message;
    this.elements.state.style.display = message ? "" : "none";
  },

  showTableMessage(message, className) {
    this.elements.body.innerHTML =
      '<tr><td colspan="7" class="text-center py-5 ' +
      this.escapeHtml(className || "text-muted") +
      '">' +
      this.escapeHtml(message) +
      "</td></tr>";
  },

  bindEvents() {
    if (this.state.eventsBound) return;
    this.state.eventsBound = true;
    this.elements.refreshBtn.addEventListener("click", () => this.loadData());
    this.elements.createBtn.addEventListener("click", () => this.showModal());
    this.elements.search.addEventListener("input", () => {
      window.clearTimeout(this.state.searchTimer);
      this.state.searchTimer = window.setTimeout(() => {
        this.state.search = this.elements.search.value.trim().toLowerCase();
        this.state.page = 1;
        this.applyFilter();
      }, 200);
    });
    this.elements.exportCsvBtn.addEventListener("click", () => this.exportCsv());
    this.elements.printBtn.addEventListener("click", () => this.printView());
    this.elements.previousPage.addEventListener("click", () => {
      if (this.state.page > 1) {
        this.state.page -= 1;
        this.render();
      }
    });
    this.elements.nextPage.addEventListener("click", () => {
      const totalPages = Math.max(1, Math.ceil(this.state.filtered.length / this.state.pageSize));
      if (this.state.page < totalPages) {
        this.state.page += 1;
        this.render();
      }
    });
    this.elements.form.addEventListener("submit", (event) => {
      event.preventDefault();
      this.saveRecord();
    });
    this.elements.body.addEventListener("click", (event) => {
      const button = event.target.closest("[data-action]");
      if (!button) return;
      const row = button.closest("tr");
      const id = row ? row.dataset.id : null;
      if (!id) return;
      if (button.dataset.action === "edit") this.showModal(id);
      if (button.dataset.action === "delete") this.deleteRecord(id);
    });
  },

  async loadData() {
    if (this.state.loading) return;
    this.state.loading = true;
    this.showState("Loading widgets...", "info");
    try {
      const response = await window.API.system.getWidgets({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[WidgetRegistryController] loadData failed:", error);
      this.showState(error?.message || "Failed to load widgets.", "danger");
      this.showTableMessage("Failed to load widgets.", "text-danger");
    } finally {
      this.state.loading = false;
    }
  },

  applyFilter() {
    const term = this.state.search;
    this.state.filtered = term
      ? this.state.rows.filter((row) =>
          Object.values(row || {}).some((value) =>
            String(typeof value === "object" ? JSON.stringify(value) : value ?? "").toLowerCase().includes(term),
          ),
        )
      : this.state.rows.slice();
    this.state.page = 1;
    this.render();
  },

  typeBadge(type) {
    const map = { chart: "bg-primary", stat: "bg-success", table: "bg-info", list: "bg-warning text-dark", custom: "bg-dark" };
    return `<span class="badge ${map[String(type).toLowerCase()] || "bg-secondary"}">${this.escapeHtml(type || "—")}</span>`;
  },

  statusBadge(status) {
    const map = { active: "bg-success", enabled: "bg-success", inactive: "bg-secondary", disabled: "bg-secondary" };
    return `<span class="badge ${map[String(status).toLowerCase()] || "bg-secondary"}">${this.escapeHtml(status || "—")}</span>`;
  },

  render() {
    const start = (this.state.page - 1) * this.state.pageSize;
    const pageRows = this.state.filtered.slice(start, start + this.state.pageSize);

    if (!pageRows.length) {
      this.showTableMessage("No widgets found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = pageRows
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="id"]').textContent = row.id ?? "—";
          fragment.querySelector('[data-fill="key"]').textContent = row.key ?? row.widget_key ?? "—";
          fragment.querySelector('[data-fill="name"]').textContent = row.name ?? "—";
          fragment.querySelector('[data-fill="type"]').outerHTML = this.typeBadge(row.type);
          fragment.querySelector('[data-fill="permission"]').textContent = row.permission || "—";
          fragment.querySelector('[data-fill="status"]').outerHTML = this.statusBadge(
            row.status ?? (row.is_active === true || row.is_active === 1 ? "active" : "inactive"),
          );
          const tr = fragment.querySelector("tr");
          tr.dataset.id = row.id;
          return tr.outerHTML;
        })
        .join("");
    }

    const total = this.state.filtered.length;
    const showing = total ? start + 1 : 0;
    const until = Math.min(start + this.state.pageSize, total);
    this.elements.count.textContent =
      "Showing " + showing + "–" + until + " of " + total + " widgets" +
      (this.state.search ? " (filtered)" : "");

    const totalPages = Math.max(1, Math.ceil(total / this.state.pageSize));
    this.elements.pageIndicator.textContent = "Page " + this.state.page + " of " + totalPages;
    this.elements.previousPage.disabled = this.state.page <= 1;
    this.elements.nextPage.disabled = this.state.page >= totalPages;
  },

  showModal(id) {
    if (!this.elements.modal) return;
    const editing = Boolean(id);
    this.elements.form.reset();
    this.elements.editId.value = editing ? id : "";
    this.elements.saveBtn.textContent = editing ? "Save changes" : "Save widget";
    this.elements.modalTitle.textContent = editing ? "Edit Widget" : "New Widget";

    if (editing) {
      const row = this.state.rows.find((item) => String(item.id) === String(id));
      if (row) {
        this.elements.key.value = row.key ?? row.widget_key ?? "";
        this.elements.name.value = row.name ?? "";
        const typeMap = { chart: "chart", stat: "stat", table: "table", list: "list", custom: "custom" };
        this.elements.type.value = typeMap[String(row.type).toLowerCase()] || "custom";
        this.elements.permission.value = row.permission ?? "";
        this.elements.status.value =
          row.status ?? (row.is_active === true || row.is_active === 1 ? "active" : "inactive");
        this.elements.description.value = row.description ?? "";
      }
    }

    if (!this.state.modal || !this.state.modal._isShown) {
      this.state.modal = bootstrap.Modal.getOrCreateInstance(this.elements.modal);
    }
    this.state.modal.show();
  },

  async saveRecord() {
    if (!this.elements.form.checkValidity()) {
      this.elements.form.reportValidity();
      return;
    }
    const id = this.elements.editId.value;
    const data = {
      key: this.elements.key.value.trim(),
      name: this.elements.name.value.trim(),
      type: this.elements.type.value,
      permission: this.elements.permission.value.trim(),
      status: this.elements.status.value,
      description: this.elements.description.value.trim(),
    };
    try {
      if (id) {
        await window.API.system.updateWidget(id, data);
        this.notify("Widget updated.", "success");
      } else {
        await window.API.system.createWidget(data);
        this.notify("Widget created.", "success");
      }
      this.state.modal?.hide();
      await this.loadData();
    } catch (error) {
      console.error("[WidgetRegistryController] saveRecord failed:", error);
      this.notify(error?.message || "Failed to save widget.", "error");
    }
  },

  async deleteRecord(id) {
    const confirmFn = window.confirmAction || window.confirm;
    const confirmed =
      typeof window.confirmAction === "function"
        ? await window.confirmAction("Delete widget", "Delete this widget registration? It will be removed from dashboards.", { confirmText: "Delete", danger: true })
        : window.confirm("Delete this widget registration? It will be removed from dashboards.");
    if (!confirmed) return;
    try {
      await window.API.system.deleteWidget(id);
      this.notify("Widget deleted.", "success");
      await this.loadData();
    } catch (error) {
      console.error("[WidgetRegistryController] deleteRecord failed:", error);
      this.notify(error?.message || "Failed to delete widget.", "error");
    }
  },

  buildCsv() {
    const headers = ["ID", "Key", "Name", "Type", "Permission", "Status", "Description"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.id, row.key ?? row.widget_key, row.name, row.type, row.permission,
      row.status ?? (row.is_active === true || row.is_active === 1 ? "active" : "inactive"),
      row.description,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "widget_registry_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view the Widget Registry.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.WidgetRegistryController = WidgetRegistryController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => WidgetRegistryController.init());
} else {
  WidgetRegistryController.init();
}