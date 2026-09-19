/**
 * Webhook Registry Controller
 * Page: webhook_registry.php
 * Dedicated system-admin controller — full CRUD over outbound webhook
 * registrations via window.API.system.getWebhookRegistry /
 * createWebhook / updateWebhook / deleteWebhook.
 */
const WebhookRegistryController = {
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
      if (!window.API?.system?.getWebhookRegistry) throw new Error("The Webhook Registry API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[WebhookRegistryController] Initialization failed:", error);
      this.showState(error?.message || "Webhook Registry could not initialize.", "danger");
      this.showTableMessage("Webhook Registry could not initialize.", "text-danger");
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
      state: document.getElementById("webhookRegistryState"),
      search: document.getElementById("webhookRegistrySearch"),
      count: document.getElementById("webhookRegistryCount"),
      body: document.getElementById("webhookRegistryTableBody"),
      rowTemplate: document.getElementById("webhookRegistryRowTemplate"),
      exportCsvBtn: document.getElementById("webhookRegistryExportCsvBtn"),
      printBtn: document.getElementById("webhookRegistryPrintBtn"),
      refreshBtn: document.getElementById("webhookRegistryRefreshBtn"),
      createBtn: document.getElementById("webhookRegistryCreateBtn"),
      previousPage: document.getElementById("webhookRegistryPreviousPage"),
      nextPage: document.getElementById("webhookRegistryNextPage"),
      pageIndicator: document.getElementById("webhookRegistryPageIndicator"),
      modal: document.getElementById("webhookRegistryModal"),
      form: document.getElementById("webhookRegistryForm"),
      editId: document.getElementById("webhookRegistryEditId"),
      name: document.getElementById("webhookRegistryName"),
      url: document.getElementById("webhookRegistryUrl"),
      secret: document.getElementById("webhookRegistrySecret"),
      events: document.getElementById("webhookRegistryEvents"),
      status: document.getElementById("webhookRegistryStatus"),
      saveBtn: document.getElementById("webhookRegistrySaveBtn"),
      modalTitle: document.getElementById("webhookRegistryModalTitle"),
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
    this.showState("Loading webhooks...", "info");
    try {
      const response = await window.API.system.getWebhookRegistry({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[WebhookRegistryController] loadData failed:", error);
      this.showState(error?.message || "Failed to load webhooks.", "danger");
      this.showTableMessage("Failed to load webhooks.", "text-danger");
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

  statusBadge(status) {
    const map = { active: "bg-success", enabled: "bg-success", inactive: "bg-secondary", disabled: "bg-secondary" };
    return `<span class="badge ${map[String(status).toLowerCase()] || "bg-secondary"}">${this.escapeHtml(status || "—")}</span>`;
  },

  formatEvents(events) {
    if (events === undefined || events === null || events === "") return "—";
    if (Array.isArray(events)) return events.join(", ");
    let text = events;
    if (typeof events === "object") text = JSON.stringify(events);
    return String(text).length > 80 ? String(text).slice(0, 80) + "…" : String(text);
  },

  maskSecret(secret) {
    if (!secret) return "Not set";
    if (secret === true) return "Set";
    const text = String(secret);
    if (text.length <= 4) return "****";
    return "••••" + text.slice(-4);
  },

  render() {
    const start = (this.state.page - 1) * this.state.pageSize;
    const pageRows = this.state.filtered.slice(start, start + this.state.pageSize);

    if (!pageRows.length) {
      this.showTableMessage("No webhooks found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = pageRows
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="id"]').textContent = row.id ?? "—";
          fragment.querySelector('[data-fill="name"]').textContent = row.name ?? "—";
          fragment.querySelector('[data-fill="url"]').textContent = row.url ?? "—";
          fragment.querySelector('[data-fill="events"]').textContent = this.formatEvents(row.events);
          fragment.querySelector('[data-fill="secret"]').textContent = this.maskSecret(row.secret);
          fragment.querySelector('[data-fill="is_active"]').outerHTML = this.statusBadge(
            row.is_active === true || row.is_active === 1 || row.is_active === "1" || row.status ? (row.is_active === true || row.is_active === 1 || row.is_active === "1" ? "active" : "inactive") : row.status,
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
      "Showing " + showing + "–" + until + " of " + total + " webhooks" +
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
    this.elements.saveBtn.textContent = editing ? "Save changes" : "Save webhook";
    this.elements.modalTitle.textContent = editing ? "Edit Webhook" : "New Webhook";

    if (editing) {
      const row = this.state.rows.find((item) => String(item.id) === String(id));
      if (row) {
        this.elements.name.value = row.name ?? "";
        this.elements.url.value = row.url ?? "";
        this.elements.secret.value = "";
        this.elements.secret.placeholder = row.secret ? "Leave blank to keep the current secret" : this.elements.secret.placeholder;
        this.elements.secret.disabled = Boolean(row.secret) && !this.elements.secret.value;
        this.elements.events.value = this.formatEvents(row.events).replace("…", "");
        this.elements.status.value =
          row.is_active === true || row.is_active === 1 || row.is_active === "1" || row.status === "active"
            ? "active"
            : "inactive";
      }
    }

    if (!this.state.modal || !this.state.modal._isShown) {
      this.state.modal = bootstrap.Modal.getOrCreateInstance(this.elements.modal);
    }
    this.state.modal.show();
  },

  parseEvents(value) {
    return value
      .split(",")
      .map((event) => event.trim())
      .filter(Boolean);
  },

  async saveRecord() {
    if (!this.elements.form.checkValidity()) {
      this.elements.form.reportValidity();
      return;
    }
    const id = this.elements.editId.value;
    const data = {
      name: this.elements.name.value.trim(),
      url: this.elements.url.value.trim(),
      events: this.parseEvents(this.elements.events.value),
      status: this.elements.status.value,
    };
    const secretValue = this.elements.secret.value.trim();
    if (secretValue) data.secret = secretValue;
    try {
      if (id) {
        await window.API.system.updateWebhook(id, data);
        this.notify("Webhook updated.", "success");
      } else {
        await window.API.system.createWebhook(data);
        this.notify("Webhook created.", "success");
      }
      this.state.modal?.hide();
      await this.loadData();
    } catch (error) {
      console.error("[WebhookRegistryController] saveRecord failed:", error);
      this.notify(error?.message || "Failed to save webhook.", "error");
    }
  },

  async deleteRecord(id) {
    const confirmFn = window.confirmAction || window.confirm;
    const confirmed =
      typeof window.confirmAction === "function"
        ? await window.confirmAction("Delete webhook", "Delete this webhook registration? Deliveries will stop.", { confirmText: "Delete", danger: true })
        : window.confirm("Delete this webhook registration? Deliveries will stop.");
    if (!confirmed) return;
    try {
      await window.API.system.deleteWebhook(id);
      this.notify("Webhook deleted.", "success");
      await this.loadData();
    } catch (error) {
      console.error("[WebhookRegistryController] deleteRecord failed:", error);
      this.notify(error?.message || "Failed to delete webhook.", "error");
    }
  },

  buildCsv() {
    const headers = ["ID", "Name", "URL", "Events", "Secret", "Status"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.id, row.name, row.url, this.formatEvents(row.events), this.maskSecret(row.secret),
      row.is_active === true || row.is_active === 1 || row.is_active === "1" ? "active" : row.status,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "webhook_registry_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view the Webhook Registry.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.WebhookRegistryController = WebhookRegistryController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => WebhookRegistryController.init());
} else {
  WebhookRegistryController.init();
}