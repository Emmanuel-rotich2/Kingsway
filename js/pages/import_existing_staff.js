/**
 * Existing Staff Migration Controller
 * Handles import_existing_staff.php through window.API.staffMigration.
 */
const ImportExistingStaffController = {
  initialized: false,
  initializationPromise: null,
  eventsBound: false,
  currentBatch: null,

  async init() {
    if (this.initializationPromise) return this.initializationPromise;

    this.initializationPromise = this._initialize().catch((error) => {
      this.initializationPromise = null;
      throw error;
    });

    return this.initializationPromise;
  },

  async _initialize() {
    if (this.initialized) return this;

    if (window.AuthContext?.ready) {
      await window.AuthContext.ready();
    }

    if (!window.AuthContext?.isAuthenticated?.()) {
      this.setState("Please log in to access staff migration.", "danger");
      window.setTimeout(() => {
        window.location.replace(`${window.APP_BASE || ""}/index.php`);
      }, 800);
      return this;
    }

    if (!window.API?.staffMigration) {
      throw new Error("Staff migration API is unavailable.");
    }

    this.bindEvents();
    await this.loadWorkspace();

    this.initialized = true;
    return this;
  },

  bindEvents() {
    if (this.eventsBound) return;
    this.eventsBound = true;

    this.byId("smTemplateCsv")?.addEventListener("click", () => this.downloadTemplate("csv"));
    this.byId("smTemplateXlsx")?.addEventListener("click", () => this.downloadTemplate("xlsx"));
    this.byId("smRefresh")?.addEventListener("click", () => this.loadWorkspace());

    this.byId("smFile")?.addEventListener("change", () => {
      const hasFile = Boolean(this.byId("smFile")?.files?.length);
      const preview = this.byId("smPreview");
      if (preview) preview.disabled = !hasFile;
    });

    this.byId("smPreview")?.addEventListener("click", () => this.validateFile());
    this.byId("smCommit")?.addEventListener("click", () => this.commitImport());
    this.byId("smRollback")?.addEventListener("click", () => this.rollbackImport());

    this.byId("smBatches")?.addEventListener("click", (event) => {
      const button = event.target.closest("[data-view]");
      if (button) void this.viewBatch(button.dataset.view);
    });

    this.byId("smRows")?.addEventListener("click", (event) => {
      const button = event.target.closest("[data-errors]");
      if (!button) return;
      const errors = JSON.parse(button.dataset.errors || "[]");
      alert(errors.join("\n"));
    });
  },

  async loadWorkspace() {
    this.setState("Loading staff migration workspace...", "info");
    try {
      await Promise.all([this.loadReferenceData(), this.loadBatches()]);
      this.setState("Ready. Download the template before preparing the migration file.", "success");
    } catch (error) {
      console.error("[ImportExistingStaffController] Workspace load failed:", error);
      this.setState(error.message || "Workspace failed to load.", "danger");
    }
  },

  async loadReferenceData() {
    const data = this.unwrap(await window.API.staffMigration.referenceData()) || {};
    const departments = Array.isArray(data.departments) ? data.departments : [];
    const roles = Array.isArray(data.roles) ? data.roles : [];
    const staffTypes = Array.isArray(data.staff_types) ? data.staff_types : [];
    const categories = Array.isArray(data.staff_categories) ? data.staff_categories : [];

    const reference = this.byId("smReference");
    if (!reference) return;

    reference.innerHTML = `
      <div class="mb-3">
        <div class="fw-semibold mb-1">Departments</div>
        <div class="small text-muted">${departments.length ? departments.map((item) => this.badge(`${item.code || ""} - ${item.name || ""}`)).join(" ") : "No active departments found."}</div>
      </div>
      <div class="mb-3">
        <div class="fw-semibold mb-1">School roles</div>
        <div class="small text-muted">${roles.length ? roles.map((item) => this.badge(item.name || item.role_name || "Role")).join(" ") : "No active school roles found."}</div>
      </div>
      <div class="mb-3">
        <div class="fw-semibold mb-1">Staff types</div>
        <div class="small text-muted">${staffTypes.length ? staffTypes.map((item) => this.badge(item.name || "Type")).join(" ") : "No staff types found."}</div>
      </div>
      <div>
        <div class="fw-semibold mb-1">Staff categories</div>
        <div class="small text-muted">${categories.length ? categories.map((item) => this.badge(`${item.staff_type || ""} - ${item.name || ""}`)).join(" ") : "No staff categories found."}</div>
      </div>
    `;
  },

  async loadBatches() {
    const rows = this.unwrap(await window.API.staffMigration.batches()) || [];
    const tbody = this.byId("smBatches");
    if (!tbody) return;

    if (!Array.isArray(rows) || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No imports yet.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map((batch) => `
      <tr>
        <td>#${this.escapeHtml(batch.id)}</td>
        <td>${this.escapeHtml(batch.source_filename)}</td>
        <td>${this.escapeHtml(batch.valid_rows)}/${this.escapeHtml(batch.total_rows)}</td>
        <td>${this.statusBadge(batch.status)}</td>
        <td>${this.escapeHtml(batch.imported_by_name || "-")}</td>
        <td>${this.escapeHtml(batch.created_at || "-")}</td>
        <td><button class="btn btn-sm btn-outline-primary" type="button" data-view="${this.escapeAttribute(batch.id)}">View</button></td>
      </tr>
    `).join("");
  },

  async downloadTemplate(type) {
    try {
      if (type === "xlsx") {
        await window.API.staffMigration.downloadTemplateXlsx();
      } else {
        await window.API.staffMigration.downloadTemplate();
      }
    } catch (error) {
      console.error("[ImportExistingStaffController] Template download failed:", error);
      this.setState(error.message || "Template download failed.", "danger");
    }
  },

  async validateFile() {
    const file = this.byId("smFile")?.files?.[0];
    if (!file) return;

    this.setState("Uploading and validating...", "info");
    const formData = new FormData();
    formData.append("file", file);

    try {
      const detail = this.unwrap(await window.API.staffMigration.stage(formData));
      this.renderBatchDetail(detail);
      this.setState("Validation completed.", "success");
      await this.loadBatches();
    } catch (error) {
      console.error("[ImportExistingStaffController] Validation failed:", error);
      this.setState(error.message || "Validation failed.", "danger");
    }
  },

  async commitImport() {
    if (!this.currentBatch) return;
    if (!confirm("Create all staff and user records from this validated batch?")) return;

    this.setState("Importing atomically...", "info");
    try {
      const result = this.unwrap(await window.API.staffMigration.commit(this.currentBatch));
      this.renderBatchDetail(result.batch || result);
      this.setState("Import completed and invitations queued.", "success");
      await this.loadBatches();
    } catch (error) {
      console.error("[ImportExistingStaffController] Commit failed:", error);
      this.setState(error.message || "Import failed. No partial records were kept.", "danger");
    }
  },

  async rollbackImport() {
    if (!this.currentBatch) return;
    if (!confirm("Rollback this import? This is blocked when operational records already exist.")) return;

    try {
      const detail = this.unwrap(await window.API.staffMigration.rollback(this.currentBatch));
      this.renderBatchDetail(detail);
      this.setState("Import rolled back.", "success");
      await this.loadBatches();
    } catch (error) {
      console.error("[ImportExistingStaffController] Rollback failed:", error);
      this.setState(error.message || "Rollback blocked.", "danger");
    }
  },

  async viewBatch(id) {
    try {
      const detail = this.unwrap(await window.API.staffMigration.batch(id));
      this.renderBatchDetail(detail);
    } catch (error) {
      console.error("[ImportExistingStaffController] Batch load failed:", error);
      this.setState(error.message || "Batch could not be loaded.", "danger");
    }
  },

  renderBatchDetail(detail) {
    if (!detail?.batch) {
      this.setState("Batch detail response was invalid.", "danger");
      return;
    }

    this.currentBatch = detail.batch.id;
    this.byId("smPreviewCard")?.classList.remove("d-none");

    const batch = detail.batch;
    const summary = this.byId("smSummary");
    if (summary) {
      summary.innerHTML = [
        ["Total", batch.total_rows],
        ["Valid", batch.valid_rows],
        ["Invalid", batch.invalid_rows],
        ["Status", batch.status],
      ].map(([label, value]) => `
        <div class="col-md-3">
          <div class="border rounded p-2 h-100">
            <div class="small text-muted">${this.escapeHtml(label)}</div>
            <div class="fw-semibold">${this.escapeHtml(value)}</div>
          </div>
        </div>
      `).join("");
    }

    const rows = Array.isArray(detail.rows) ? detail.rows : [];
    const body = this.byId("smRows");
    if (body) {
      body.innerHTML = rows.length ? rows.map((row) => this.renderValidationRow(row)).join("") : '<tr><td colspan="5" class="text-center text-muted py-4">No rows found.</td></tr>';
    }

    const commit = this.byId("smCommit");
    if (commit) commit.disabled = !detail.can_commit;
    const rollback = this.byId("smRollback");
    if (rollback) rollback.disabled = !detail.can_rollback;
  },

  renderValidationRow(row) {
    const data = row.data || {};
    const errors = Array.isArray(row.errors) ? row.errors : [];
    const name = `${data.first_name || ""} ${data.last_name || ""}`.trim() || "-";
    return `
      <tr>
        <td>${this.escapeHtml(row.row_number)}</td>
        <td>${this.escapeHtml(name)}</td>
        <td>${this.escapeHtml(data.email || "-")}</td>
        <td>${this.escapeHtml(data.department_code || "-")}</td>
        <td>${errors.length ? `<button class="btn btn-sm btn-outline-danger" type="button" data-errors="${this.escapeAttribute(JSON.stringify(errors))}">${errors.length} errors</button>` : '<span class="badge bg-success">Valid</span>'}</td>
      </tr>
    `;
  },

  unwrap(response) {
    return response?.data?.data ?? response?.data ?? response;
  },

  byId(id) {
    return document.getElementById(id);
  },

  setState(message, type = "info") {
    const state = this.byId("smState");
    if (!state) return;
    state.className = `alert alert-${type}`;
    state.textContent = message;
  },

  statusBadge(status) {
    const palette = {
      validated: "success",
      completed: "primary",
      validation_failed: "danger",
      failed: "danger",
      rolled_back: "secondary",
      processing: "warning",
      validating: "info",
    };
    return `<span class="badge bg-${palette[status] || "secondary"}">${this.escapeHtml(status || "unknown")}</span>`;
  },

  badge(value) {
    return `<span class="badge bg-light text-dark border me-1 mb-1">${this.escapeHtml(value)}</span>`;
  },

  escapeHtml(value) {
    const div = document.createElement("div");
    div.textContent = String(value ?? "");
    return div.innerHTML;
  },

  escapeAttribute(value) {
    return this.escapeHtml(value).replace(/"/g, "&quot;");
  },
};

window.ImportExistingStaffController = ImportExistingStaffController;

function initializeImportExistingStaffController() {
  void ImportExistingStaffController.init().catch((error) => {
    console.error("[ImportExistingStaffController] Initialization failed:", error);
    ImportExistingStaffController.setState(error.message || "Staff migration failed to initialize.", "danger");
  });
}

if (window.__APP_BOOTED__) {
  initializeImportExistingStaffController();
} else {
  window.addEventListener("kingsway:ready", initializeImportExistingStaffController, { once: true });
}
