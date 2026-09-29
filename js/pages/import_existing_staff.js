/**
 * Existing Staff Migration Controller
 * Handles import_existing_staff.php through window.API.staffMigration.
 */
const ImportExistingStaffController = {
  initialized: false,
  initializationPromise: null,
  eventsBound: false,
  currentBatch: null,
  previewToken: 0,
  previewState: null,

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
    this.byId("smTemplateOds")?.addEventListener("click", () => this.downloadTemplate("ods"));

    this.byId("smFile")?.addEventListener("change", (event) => void this.previewFile(event.target.files?.[0] || null));

    this.byId("smPreview")?.addEventListener("click", () => this.validateFile());
    this.byId("smCommit")?.addEventListener("click", () => this.commitImport());
    this.byId("smRollback")?.addEventListener("click", () => this.rollbackImport());

    this.byId("smRows")?.addEventListener("click", async (event) => {
      const cancel = event.target.closest("[data-cancel-invitation]");
      if (cancel) {
        const confirmed = await window.confirmAction(
          "Cancel staff invitation",
          "The setup link will stop working and queued invitation emails will be cancelled.",
          { confirmText: "Cancel invitation", danger: true },
        );
        if (!confirmed) return;
        cancel.disabled = true;
        try {
          await window.API.staffMigration.cancelInvitation(Number(cancel.dataset.cancelInvitation));
          this.setState("Staff invitation cancelled.", "success");
          await this.viewBatch(this.currentBatch);
        } catch (error) {
          this.setState(error.message || "Could not cancel invitation.", "danger");
          cancel.disabled = false;
        }
        return;
      }
      const resend = event.target.closest("[data-resend-invitation]");
      if (resend) {
        resend.disabled = true;
        try {
          await window.API.staffMigration.resendInvitation(Number(resend.dataset.resendInvitation));
          this.setState("A replacement staff invitation was queued.", "success");
          await this.viewBatch(this.currentBatch);
        } catch (error) {
          this.setState(error.message || "Could not queue a replacement invitation.", "danger");
          resend.disabled = false;
        }
        return;
      }
      const resendOtp = event.target.closest("[data-resend-setup-otp]");
      if (resendOtp) {
        resendOtp.disabled = true;
        try {
          await window.API.staffMigration.resendSetupOtp(Number(resendOtp.dataset.resendSetupOtp));
          this.setState("A new setup verification code was sent.", "success");
          await this.viewBatch(this.currentBatch);
        } catch (error) {
          this.setState(error.message || "Could not resend the verification code.", "danger");
          resendOtp.disabled = false;
        }
        return;
      }
      const button = event.target.closest("[data-errors]");
      if (!button) return;
      const errors = JSON.parse(button.dataset.errors || "[]");
      await window.infoDialog('Notice', errors.join("\n"));
    });
  },

  async loadWorkspace() {
    this.setState("Loading staff migration workspace...", "info");
    try {
      this.setState("Choose a completed staff file to preview it.", "info");
    } catch (error) {
      console.error("[ImportExistingStaffController] Workspace load failed:", error);
      this.setState(error.message || "Workspace failed to load.", "danger");
    }
  },

  async previewFile(file) {
    const token = ++this.previewToken;
    const section = this.byId("smClientPreview");
    const summary = this.byId("smClientSummary");
    const table = this.byId("smClientTable");
    const button = this.byId("smPreview");
    this.previewState = null;
    if (!file) { section.hidden = true; button.disabled = true; return; }
    section.hidden = false;
    this.byId("smFilename").textContent = file.name;
    summary.innerHTML = '<div class="alert alert-info py-2 mb-0">Reading spreadsheet…</div>';
    table.innerHTML = "";
    button.disabled = true;
    try {
      const ext = file.name.split(".").pop()?.toLowerCase();
      if (!['csv', 'xlsx', 'xls', 'ods'].includes(ext)) throw new Error("Choose a CSV, XLSX, XLS, or ODS spreadsheet.");
      if (!window.XLSX?.read) throw new Error("Spreadsheet preview could not load. Refresh and try again.");
      const workbook = window.XLSX.read(await file.arrayBuffer(), { type: "array", cellDates: false, dense: true });
      if (token !== this.previewToken) return;
      const sheetName = workbook.SheetNames?.find((name) => name.trim().toLowerCase() === "staff import") || workbook.SheetNames?.[0];
      if (!sheetName) throw new Error("This file does not contain a worksheet.");
      const grid = window.XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], { header: 1, defval: "", raw: false, blankrows: false });
      const headers = (grid.shift() || []).map((value) => String(value ?? "").trim());
      if (!headers.length || !headers.some(Boolean)) throw new Error("The staff worksheet has no column headers.");
      const records = grid.map((values, i) => ({ number: i + 2, values })).filter((row) => row.values.some((v) => String(v ?? "").trim()));
      this.previewState = { file, headers, records };
      const visible = records.slice(0, 100);
      const esc = (v) => this.escapeHtml(v);
      table.innerHTML = `<table class="table table-sm table-striped table-bordered mb-0"><thead class="table-light sticky-top"><tr><th>Row</th>${headers.map((h) => `<th class="text-nowrap">${esc(h)}</th>`).join("")}</tr></thead><tbody>${visible.map((r) => `<tr><th>${r.number}</th>${headers.map((_, i) => `<td class="text-nowrap">${esc(r.values[i] ?? "")}</td>`).join("")}</tr>`).join("")}</tbody></table>`;
      summary.innerHTML = `<div class="border rounded px-3 py-2"><strong>${records.length}</strong> staff rows found <span class="text-muted ms-2">Worksheet: ${esc(sheetName)}</span></div>`;
      const notes = [];
      if (!records.length) notes.push("No staff rows were found under the header row.");
      if (records.length > visible.length) notes.push(`Showing ${visible.length} of ${records.length} rows.`);
      if (grid.some((row) => row.length > headers.length)) notes.push("Some data extends beyond the header columns; check the source sheet.");
      this.byId("smClientNote").textContent = notes.join(" ") || "Review the sheet, then validate all rows with the server before creating staff.";
      button.disabled = records.length === 0;
      this.setState("Preview ready. Nothing has been uploaded or saved yet.", "info");
    } catch (error) {
      if (token !== this.previewToken) return;
      summary.innerHTML = `<div class="alert alert-danger py-2 mb-0">${this.escapeHtml(error.message || "Could not read this file.")}</div>`;
      this.byId("smClientNote").textContent = "Select a valid CSV, Excel, or OpenDocument spreadsheet.";
    }
  },

  async downloadTemplate(type) {
    try {
      if (type === "xlsx") {
        await window.API.staffMigration.downloadTemplateXlsx();
      } else if (type === "ods") {
        await window.API.staffMigration.downloadTemplateOds();
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
    if (this.previewState?.file !== file) return this.setState("Wait for the file preview to finish.", "warning");

    this.setState("Uploading and validating...", "info");
    const formData = new FormData();
    formData.append("file", file);

    try {
      const detail = this.unwrap(await window.API.staffMigration.stage(formData));
      this.renderBatchDetail(detail);
      this.setState("Validation completed.", "success");
    } catch (error) {
      console.error("[ImportExistingStaffController] Validation failed:", error);
      this.setState(error.message || "Validation failed.", "danger");
    }
  },

  async commitImport() {
    if (!this.currentBatch) return;
    if (!(await window.confirmAction('Confirm', "Create all staff and user records from this validated batch?"))) return;

    this.setState("Importing atomically...", "info");
    try {
      const result = this.unwrap(await window.API.staffMigration.commit(this.currentBatch));
      this.renderBatchDetail(result.batch || result);
      this.setState("Import completed and invitations queued.", "success");
    } catch (error) {
      console.error("[ImportExistingStaffController] Commit failed:", error);
      this.setState(error.message || "Import failed. No partial records were kept.", "danger");
    }
  },

  async rollbackImport() {
    if (!this.currentBatch) return;
    if (!(await window.confirmAction('Confirm', "Rollback this import? This is blocked when operational records already exist."))) return;

    try {
      const detail = this.unwrap(await window.API.staffMigration.rollback(this.currentBatch));
      this.renderBatchDetail(detail);
      this.setState("Import rolled back.", "success");
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
    const fields = [...new Set(rows.flatMap((row) => Object.keys(row.data || {})))];
    this.validationFields = fields;
    const head = this.byId("smRowsHead");
    if (head) head.innerHTML = `<tr><th>Row</th>${fields.map((field) => `<th class="text-nowrap">${this.escapeHtml(field.replaceAll("_", " "))}</th>`).join("")}<th>Validation</th></tr>`;
    if (body) {
      body.innerHTML = rows.length ? rows.map((row) => this.renderValidationRow(row)).join("") : '<tr><td colspan="20" class="text-center text-muted py-4">No rows found.</td></tr>';
    }

    const commit = this.byId("smCommit");
    if (commit) commit.disabled = !detail.can_commit;
    const rollback = this.byId("smRollback");
    if (rollback) rollback.disabled = !detail.can_rollback;
  },

  renderValidationRow(row) {
    const data = row.data || {};
    const errors = Array.isArray(row.errors) ? row.errors : [];
    const statusCell = errors.length
      ? `<button class="btn btn-sm btn-outline-danger" type="button" data-errors="${this.escapeAttribute(JSON.stringify(errors))}">${errors.length} errors</button>`
      : row.user_id
        ? `<div class="small">Invitation: ${this.escapeHtml(row.invitation_status || "not_sent")}<br>Email: ${this.escapeHtml(row.invitation_delivery_status || "not_queued")}</div>${Number(row.setup_required) === 1 ? `<button class="btn btn-sm btn-outline-primary mt-1" type="button" data-resend-invitation="${this.escapeAttribute(row.user_id)}">Resend setup link</button>${row.invitation_status === "pending" ? `<button class="btn btn-sm btn-outline-danger mt-1 ms-1" type="button" data-cancel-invitation="${this.escapeAttribute(row.user_id)}">Cancel</button>` : ""}` : row.invitation_status === "accepted" && Number(row.profile_completed) !== 1 ? `<button class="btn btn-sm btn-outline-primary mt-1" type="button" data-resend-setup-otp="${this.escapeAttribute(row.user_id)}">Resend verification code</button>` : ""}`
        : '<span class="badge bg-success">Valid</span>';
    return `<tr><th>${this.escapeHtml(row.row_number)}</th>${(this.validationFields || []).map((field) => `<td class="text-nowrap">${this.escapeHtml(data[field] ?? "")}</td>`).join("")}<td>${statusCell}</td></tr>`;
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
