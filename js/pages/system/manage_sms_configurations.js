(function () {
  "use strict";
  const SmsConfigController = {
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
      this.on("smsRefresh", "click", () => this.load());
      this.on("smsSaveBtn", "click", () => this.save());
      this.on("smsTestBtn", "click", () => this.test());
      this.on("smsExportCsv", "click", () => this.exportCsv());
      this.on("smsPrint", "click", () => window.print());
    },

    on(id, evt, fn) { const el = document.getElementById(id); if (el) el.addEventListener(evt, fn); },

    async load() {
      const state = document.getElementById("smsState");
      const body = document.getElementById("smsFieldsBody");
      if (state) state.className = "alert alert-info";
      if (state) state.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Loading SMS settings...';
      if (body) body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr>';
      try {
        const res = await window.API.system.getCommunicationConfigs();
        this.fields = res?.sms || [];
        this.renderTable();
        if (state) { state.className = "alert alert-success py-2"; state.innerHTML = '<i class="bi bi-check-circle me-1"></i>SMS configuration loaded.'; }
      } catch (e) {
        console.error("[SmsConfig] load failed", e);
        if (state) { state.className = "alert alert-danger"; state.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Failed to load SMS configuration.'; }
        if (body) body.innerHTML = '<tr><td colspan="4" class="text-danger text-center py-3">Load failed.</td></tr>';
      }
    },

    renderTable() {
      const body = document.getElementById("smsFieldsBody");
      if (!body) return;
      if (!this.fields.length) { body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No SMS fields found.</td></tr>'; return; }
      body.innerHTML = this.fields.map(f => `<tr>
        <td><strong>${this.esc(f.label || f.key)}</strong><br><small class="text-muted">${this.esc(f.key)}</small></td>
        <td>${f.is_secret ? (f.configured ? '<span class="badge bg-success">Configured</span>' : '<span class="badge bg-secondary">Not set</span>') : `<code>${this.esc(f.value || '—')}</code>`}</td>
        <td>${f.source === 'db' ? '<span class="badge bg-primary">Database</span>' : '<span class="badge bg-secondary">Environment</span>'}</td>
        <td>${f.is_secret ? '' : `<input type="text" class="form-control form-control-sm sms-field-input" data-key="${this.esc(f.key)}" value="${this.esc(f.value || '')}">`}</td>
      </tr>`).join("");
    },

    async save() {
      const state = document.getElementById("smsState");
      const inputs = document.querySelectorAll(".sms-field-input");
      const fields = Array.from(inputs).map(i => ({ key: i.dataset.key, value: i.value.trim() }));
      if (!fields.length) { this.showMsg("No editable fields to save.", "warning"); return; }
      if (state) { state.className = "alert alert-info"; state.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving...'; }
      try {
        const res = await window.API.system.saveCommunicationConfig("sms", fields);
        this.fields = res?.sms || [];
        this.renderTable();
        if (state) { state.className = "alert alert-success py-2"; state.innerHTML = '<i class="bi bi-check-circle me-1"></i>SMS settings saved successfully.'; }
      } catch (e) {
        console.error("[SmsConfig] save failed", e);
        if (state) { state.className = "alert alert-danger"; state.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Failed to save SMS settings.'; }
      }
    },

    async test() {
      const result = document.getElementById("smsTestResult");
      const phone = document.getElementById("smsTestPhone")?.value?.trim();
      if (!phone) { if (result) result.innerHTML = '<span class="text-danger">Enter a test phone number.</span>'; return; }
      if (result) result.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split me-1"></i>Testing...</span>';
      try {
        const res = await window.API.system.testCommunicationConfig("sms", phone);
        if (result) result.innerHTML = res?.success
          ? `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${this.esc(res.message || 'Test passed.')} ${res.balance != null ? '(Balance: ' + res.balance + ')' : ''}</span>`
          : `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${this.esc(res.message || 'Test failed.')}</span>`;
      } catch (e) {
        if (result) result.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${this.esc(e.message || 'Test failed.')}</span>`;
      }
    },

    exportCsv() { if (this.fields.length) window.KingswayFileLifecycle?.exportText?.(this.csvContent(), "sms-config.csv", "text/csv"); },
    csvContent() { return "Key,Label,Value,Source\n" + this.fields.map(f => `${f.key},"${(f.label||'').replace(/"/g,'""')}","${(f.value||'').replace(/"/g,'""')}",${f.source}`).join("\n"); },

    showMsg(msg, type = "info") { window.showNotification?.(msg, type); },
    esc(s) { const d = document.createElement("div"); d.textContent = s; return d.innerHTML; },
  };
  window.SmsConfigController = SmsConfigController;
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", () => SmsConfigController.init());
  else SmsConfigController.init();
})();
