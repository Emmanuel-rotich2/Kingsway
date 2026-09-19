(function () {
  "use strict";
  const WhatsappConfigController = {
    fields: [],
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        await window.AuthContext?.ready();
        if (!window.AuthContext?.isAuthenticated?.()) {
          window.location.href = (window.APP_BASE || "") + "/index.php";
          return;
        }
      } catch (e) { console.warn("Auth init failed", e); }
      this.bind();
      await this.load();
    },

    bind() {
      this.on("whatsappRefresh", "click", () => this.load());
      this.on("whatsappSaveBtn", "click", () => this.save());
      this.on("whatsappTestBtn", "click", () => this.test());
      this.on("whatsappExportCsv", "click", () => this.exportCsv());
      this.on("whatsappPrint", "click", () => window.print());
    },

    on(id, evt, fn) { const el = document.getElementById(id); if (el) el.addEventListener(evt, fn); },

    async load() {
      const state = document.getElementById("whatsappState");
      const body = document.getElementById("whatsappFieldsBody");
      if (state) state.className = "alert alert-info";
      if (state) state.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Loading WhatsApp settings...';
      if (body) body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr>';
      try {
        const res = await window.API.system.getCommunicationConfigs();
        this.fields = res?.whatsapp || [];
        this.renderTable();
        if (state) { state.className = "alert alert-success py-2"; state.innerHTML = '<i class="bi bi-check-circle me-1"></i>WhatsApp configuration loaded.'; }
      } catch (e) {
        console.error("[WhatsappConfig] load failed", e);
        if (state) { state.className = "alert alert-danger"; state.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Failed to load WhatsApp configuration.'; }
        if (body) body.innerHTML = '<tr><td colspan="4" class="text-danger text-center py-3">Load failed.</td></tr>';
      }
    },

    renderTable() {
      const body = document.getElementById("whatsappFieldsBody");
      if (!body) return;
      if (!this.fields.length) { body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No WhatsApp fields found.</td></tr>'; return; }
      body.innerHTML = this.fields.map(f => `<tr>
        <td><strong>${this.esc(f.label || f.key)}</strong><br><small class="text-muted">${this.esc(f.key)}</small></td>
        <td>${f.is_secret ? (f.configured ? '<span class="badge bg-success">Configured</span>' : '<span class="badge bg-secondary">Not set</span>') : `<code>${this.esc(f.value || '—')}</code>`}</td>
        <td>${f.source === 'db' ? '<span class="badge bg-primary">Database</span>' : '<span class="badge bg-secondary">Environment</span>'}</td>
        <td>${f.is_secret ? '' : `<input type="text" class="form-control form-control-sm whatsapp-field-input" data-key="${this.esc(f.key)}" value="${this.esc(f.value || '')}">`}</td>
      </tr>`).join("");
    },

    async save() {
      const state = document.getElementById("whatsappState");
      const inputs = document.querySelectorAll(".whatsapp-field-input");
      const fields = Array.from(inputs).map(i => ({ key: i.dataset.key, value: i.value.trim() }));
      if (!fields.length) { this.showMsg("No editable fields to save.", "warning"); return; }
      if (state) { state.className = "alert alert-info"; state.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving...'; }
      try {
        const res = await window.API.system.saveCommunicationConfig("whatsapp", fields);
        this.fields = res?.whatsapp || [];
        this.renderTable();
        if (state) { state.className = "alert alert-success py-2"; state.innerHTML = '<i class="bi bi-check-circle me-1"></i>WhatsApp settings saved successfully.'; }
      } catch (e) {
        console.error("[WhatsappConfig] save failed", e);
        if (state) { state.className = "alert alert-danger"; state.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Failed to save WhatsApp settings.'; }
      }
    },

    async test() {
      const result = document.getElementById("whatsappTestResult");
      if (result) result.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split me-1"></i>Testing...</span>';
      try {
        const res = await window.API.system.testCommunicationConfig("whatsapp");
        if (result) result.innerHTML = res?.success
          ? `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${this.esc(res.message || 'Test passed.')}</span>`
          : `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${this.esc(res.message || 'Test failed.')}</span>`;
      } catch (e) {
        if (result) result.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${this.esc(e.message || 'Test failed.')}</span>`;
      }
    },

    exportCsv() { if (this.fields.length) window.KingswayFileLifecycle?.exportText?.(this.csvContent(), "whatsapp-config.csv", "text/csv"); },
    csvContent() { return "Key,Label,Value,Source\n" + this.fields.map(f => `${f.key},"${(f.label||'').replace(/"/g,'""')}","${(f.value||'').replace(/"/g,'""')}",${f.source}`).join("\n"); },

    showMsg(msg, type = "info") { window.showNotification?.(msg, type); },
    esc(s) { const d = document.createElement("div"); d.textContent = s; return d.innerHTML; },
  };
  window.WhatsappConfigController = WhatsappConfigController;
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", () => WhatsappConfigController.init());
  else WhatsappConfigController.init();
})();
