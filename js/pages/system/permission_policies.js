/**
 * Permission Policies Controller
 * Page: permission_policies.php
 * Dedicated system-admin controller — full CRUD over permission policy
 * rules via window.API.system.getPermissionPolicies /
 * createPermissionPolicy / updatePermissionPolicy / deletePermissionPolicy.
 */
const PermissionPoliciesController = {
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
      if (!window.API?.system?.getPermissionPolicies) throw new Error("The Permission Policies API is unavailable.");
      this.cacheElements();
      this.bindEvents();
      this.state.initialized = true;
      await this.loadData();
    } catch (error) {
      console.error("[PermissionPoliciesController] Initialization failed:", error);
      this.showState(error?.message || "Permission Policies could not initialize.", "danger");
      this.showTableMessage("Permission Policies could not initialize.", "text-danger");
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
      state: document.getElementById("permissionPoliciesState"),
      search: document.getElementById("permissionPoliciesSearch"),
      count: document.getElementById("permissionPoliciesCount"),
      body: document.getElementById("permissionPoliciesTableBody"),
      rowTemplate: document.getElementById("permissionPoliciesRowTemplate"),
      exportCsvBtn: document.getElementById("permissionPoliciesExportCsvBtn"),
      printBtn: document.getElementById("permissionPoliciesPrintBtn"),
      refreshBtn: document.getElementById("permissionPoliciesRefreshBtn"),
      createBtn: document.getElementById("permissionPoliciesCreateBtn"),
      previousPage: document.getElementById("permissionPoliciesPreviousPage"),
      nextPage: document.getElementById("permissionPoliciesNextPage"),
      pageIndicator: document.getElementById("permissionPoliciesPageIndicator"),
      modal: document.getElementById("permissionPoliciesModal"),
      form: document.getElementById("permissionPoliciesForm"),
      editId: document.getElementById("permissionPoliciesEditId"),
      name: document.getElementById("permissionPoliciesName"),
      description: document.getElementById("permissionPoliciesDescription"),
      status: document.getElementById("permissionPoliciesStatus"),
      rules: document.getElementById("permissionPoliciesRules"),
      saveBtn: document.getElementById("permissionPoliciesSaveBtn"),
      modalTitle: document.getElementById("permissionPoliciesModalTitle"),
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
    this.showState("Loading permission policies...", "info");
    try {
      const response = await window.API.system.getPermissionPolicies({ limit: 500 });
      const raw = Array.isArray(response) ? response : (response && response.data) || [];
      this.state.rows = Array.isArray(raw) ? raw : [];
      this.applyFilter();
      this.showState("", "");
    } catch (error) {
      console.error("[PermissionPoliciesController] loadData failed:", error);
      this.showState(error?.message || "Failed to load permission policies.", "danger");
      this.showTableMessage("Failed to load permission policies.", "text-danger");
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

  formatRules(rules) {
    if (rules === undefined || rules === null || rules === "") return "—";
    let text = rules;
    if (typeof rules === "object") text = JSON.stringify(rules);
    return String(text).length > 60 ? text.slice(0, 60) + "…" : String(text);
  },

  render() {
    const start = (this.state.page - 1) * this.state.pageSize;
    const pageRows = this.state.filtered.slice(start, start + this.state.pageSize);

    if (!pageRows.length) {
      this.showTableMessage("No policies found." + (this.state.search ? " Adjust your search." : ""));
    } else {
      this.elements.body.innerHTML = pageRows
        .map((row) => {
          const fragment = this.elements.rowTemplate.content.cloneNode(true);
          fragment.querySelector('[data-fill="id"]').textContent = row.id ?? "—";
          fragment.querySelector('[data-fill="name"]').textContent = row.name ?? "—";
          fragment.querySelector('[data-fill="description"]').textContent = row.description ?? "—";
          fragment.querySelector('[data-fill="rules"]').textContent = this.formatRules(row.rules);
          fragment.querySelector('[data-fill="status"]').outerHTML = this.statusBadge(row.status);
          fragment.querySelector('[data-fill="updated_at"]').textContent = row.updated_at ?? "—";
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
      "Showing " + showing + "–" + until + " of " + total + " policies" +
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
    this.elements.saveBtn.textContent = editing ? "Save changes" : "Save policy";
    this.elements.modalTitle.textContent = editing ? "Edit Permission Policy" : "New Permission Policy";

    if (editing) {
      const row = this.state.rows.find((item) => String(item.id) === String(id));
      if (row) {
        this.elements.name.value = row.name ?? "";
        this.elements.description.value = row.description ?? "";
        this.elements.status.value = row.status ?? "active";
        this.elements.rules.value =
          typeof row.rules === "object" ? JSON.stringify(row.rules, null, 2) : row.rules ?? "";
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
      name: this.elements.name.value.trim(),
      description: this.elements.description.value.trim(),
      status: this.elements.status.value,
      rules: this.elements.rules.value.trim(),
    };
    try {
      if (id) {
        await window.API.system.updatePermissionPolicy(id, data);
        this.notify("Permission policy updated.", "success");
      } else {
        await window.API.system.createPermissionPolicy(data);
        this.notify("Permission policy created.", "success");
      }
      this.state.modal?.hide();
      await this.loadData();
    } catch (error) {
      console.error("[PermissionPoliciesController] saveRecord failed:", error);
      this.notify(error?.message || "Failed to save permission policy.", "error");
    }
  },

  async deleteRecord(id) {
    const confirmFn = window.confirmAction || window.confirm;
    const confirmed =
      typeof window.confirmAction === "function"
        ? await window.confirmAction("Delete permission policy", "Delete this policy? This action cannot be undone.", { confirmText: "Delete", danger: true })
        : window.confirm("Delete this policy? This action cannot be undone.");
    if (!confirmed) return;
    try {
      await window.API.system.deletePermissionPolicy(id);
      this.notify("Permission policy deleted.", "success");
      await this.loadData();
    } catch (error) {
      console.error("[PermissionPoliciesController] deleteRecord failed:", error);
      this.notify(error?.message || "Failed to delete permission policy.", "error");
    }
  },

  buildCsv() {
    const headers = ["ID", "Name", "Description", "Rules", "Status", "Updated"];
    const escape = (value) => {
      const text = String(value ?? "");
      return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };
    const rows = this.state.filtered.map((row) => [
      row.id, row.name, row.description,
      typeof row.rules === "object" ? JSON.stringify(row.rules) : row.rules,
      row.status, row.updated_at,
    ]);
    return [headers, ...rows].map((line) => line.map(escape).join(",")).join("\r\n");
  },

  async exportCsv() {
    if (!window.KingswayFileLifecycle?.exportText) {
      this.notify("CSV export is unavailable.", "error");
      return;
    }
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(this.buildCsv(), "permission_policies_" + date + ".csv", "text/csv;charset=utf-8");
  },

  printView() {
    window.print();
  },

  renderForbidden() {
    this.showState("You do not have permission to view Permission Policies.", "danger");
    this.showTableMessage("Access denied.", "text-danger");
  },
};

window.PermissionPoliciesController = PermissionPoliciesController;
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => PermissionPoliciesController.init());
} else {
  PermissionPoliciesController.init();
}