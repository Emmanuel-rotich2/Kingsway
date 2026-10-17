(function () {
  "use strict";

  const ManageWhatsAppController = {
    data: [],
    filtered: [],
    contacts: [],
    groups: [],
    templates: [],
    audiences: { parents: [], students: [], classes: [], student_types: [], school_levels: [], vendors: [], staff: [] },
    currentPage: 1,
    perPage: 10,
    composeModal: null,

    init: async function () {
      if (!document.getElementById("messagesTable")) return;
      await window.AuthContext?.ready();
      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }
      this.composeModal = new bootstrap.Modal(document.getElementById("composeModal"));
      this.bindEvents();
      this.subscribeRealtime();
      this.applyPermissionGates();
      await Promise.all([this.loadData(), this.loadGroups(), this.loadTemplates(), this.loadAudienceOptions()]);
      this.setupVariableMenu();
    },

    setupVariableMenu: function () {
      const menu = document.getElementById("messageVariableMenu");
      if (!menu || !window.RecipientPicker) return;
      menu.innerHTML = window.RecipientPicker.variables()
        .map((v) => `<li><button type="button" class="dropdown-item" data-var="${v.token}">${v.token} <span class="text-muted small">${v.label}</span></button></li>`)
        .join("");
      menu.querySelectorAll("[data-var]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          window.RecipientPicker.insertVariable("messageBody", this.getAttribute("data-var"));
        });
      });
    },

    applyPermissionGates: function () {
      const canExport = window.AuthContext?.canExport ? AuthContext.canExport("communications") : true;
      const canPrint = window.AuthContext?.canPrint ? AuthContext.canPrint("communications") : true;
      const ex = document.getElementById("exportWaBtn");
      const pr = document.getElementById("printWaBtn");
      if (ex && !canExport) ex.style.display = "none";
      if (pr && !canPrint) pr.style.display = "none";
    },

    bindEvents: function () {
      const self = this;
      this.on("composeBtn", "click", () => self.openCompose());
      this.on("sendBtn", "click", () => self.submitMessage());
      this.on("recipientType", "change", function () { self.toggleRecipientSection(this.value); });
      this.on("contactSearch", "input", function () { self.filterContactList(this.value); });
      this.on("enableReminder", "change", function () {
        document.getElementById("reminderOptions").style.display = this.checked ? "" : "none";
        if (this.checked && !document.getElementById("reminderAfter").value) {
          document.getElementById("reminderAfter").value = "24";
        }
      });
      this.on("messageType", "change", function () { self.suggestTemplate(this.value); });
      this.on("templateSelect", "change", function () { self.applyTemplate(this.value); });
      this.on("messageBody", "input", function () { self.updateCharCount(); });
      this.on("clearFiltersBtn", "click", function () {
        document.getElementById("searchFilter").value = "";
        document.getElementById("statusFilter").value = "";
        document.getElementById("categoryFilter").value = "";
        document.getElementById("recipientFilter").value = "";
        document.getElementById("dateFilter").value = "";
        self.currentPage = 1;
        self.applyFilters();
      });
      ["searchFilter", "statusFilter", "categoryFilter", "recipientFilter", "dateFilter"].forEach(function (id) {
        self.on(id, "input", function () { self.currentPage = 1; self.applyFilters(); });
        self.on(id, "change", function () { self.currentPage = 1; self.applyFilters(); });
      });
      this.on("exportWaBtn", "click", function () { self.exportWa(); });
      this.on("printWaBtn", "click", function () { self.printWa(); });
    },

    subscribeRealtime: function () {
      const self = this;
      window.APIRealtime?.register?.('manageWhatsApp', this, ['communications']);
      const refresh = (() => {
        let timer = null;
        return function () {
          clearTimeout(timer);
          timer = setTimeout(function () { self.loadData(); }, 400);
        };
      })();
      ["kingsway:data-mutated", "kingsway:job"].forEach(function (evt) {
        window.addEventListener(evt, function (e) {
          const scope = String(e.detail?.scope || "").toLowerCase();
          if (scope && scope !== "all" && !scope.includes("communic") && !scope.includes("whatsapp")) return;
          refresh();
        });
      });
    },

    loadData: async function () {
      try {
        const response = await window.API.communications.getMessages({ type: "whatsapp" });
        this.data = this.unwrapList(response, ["items", "communications", "messages"]);
      } catch (e) {
        console.error("Failed to load WhatsApp records:", e);
        this.data = [];
      }
      this.applyFilters();
    },

    loadGroups: async function () {
      try {
        const res = await window.API.communications.getGroup();
        this.groups = Array.isArray(res) ? res : (res?.data || []);
        const sel = document.getElementById("groupSelect");
        if (sel) {
          sel.innerHTML = '<option value="">Select group...</option>' +
            this.groups.map(g => `<option value="${g.id}">${this.esc(g.name)}</option>`).join("");
        }
      } catch (e) {
        this.groups = [];
      }
    },

    loadTemplates: async function () {
      try {
        const res = await window.API.communications.getTemplates();
        this.templates = Array.isArray(res) ? res : (res?.data || []);
        const sel = document.getElementById("templateSelect");
        if (sel) {
          sel.innerHTML = '<option value="">No template</option>' +
            this.templates.filter(t => t.template_type === 'sms' || t.template_type === 'whatsapp' || t.template_type === 'announcement')
              .map(t => `<option value="${t.id}">${this.esc(t.name)}</option>`).join("");
        }
      } catch (e) {
        this.templates = [];
      }
    },

    loadAudienceOptions: async function () {
      try { const r = await window.API.communications.getAudienceOptions(); const data = r?.data || r || {}; this.audiences = data; const allowed = data.allowed_audiences || []; const select = document.getElementById('recipientType'); if (select) Array.from(select.options).forEach(o => { if (o.value && !allowed.includes(o.value)) o.remove(); }); } catch (e) { console.error('Audience load failed', e); }
    },

    toggleRecipientSection: function (val) {
      document.getElementById("recipientSelector").style.display = "none";
      document.getElementById("groupSelector").style.display = "none";
      document.getElementById("customNumbersDiv").style.display = "none";
      const pickerDiv = document.getElementById("recipientPickerDiv");
      if (pickerDiv) pickerDiv.style.display = "none";
      const sel = document.getElementById("specificRecipients");
      sel.innerHTML = "";
      if (val === "custom_selection") {
        if (pickerDiv) pickerDiv.style.display = "";
        if (!this.recipientPicker && window.RecipientPicker) {
          this.recipientPicker = window.RecipientPicker.create("recipientPicker", {
            mode: "multi",
            placeholder: "Search learner, parent, staff, class or vendor…",
          });
        }
      } else if (["selected_students", "selected_class", "student_type", "school_level", "selected_parents", "selected_staff", "selected_vendors"].includes(val)) {
        document.getElementById("recipientSelector").style.display = "";
        this.populateRecipientList(val);
      } else if (val === "contact_group") {
        document.getElementById("groupSelector").style.display = "";
      } else if (val === "custom_numbers") {
        document.getElementById("customNumbersDiv").style.display = "";
      }
    },

    populateRecipientList: function (type) {
      const sel = document.getElementById("specificRecipients");
      sel.innerHTML = "";
      const map = { selected_students: this.audiences.students, selected_class: this.audiences.classes, student_type: this.audiences.student_types, school_level: this.audiences.school_levels, selected_parents: this.audiences.parents, selected_staff: this.audiences.staff, selected_vendors: this.audiences.vendors };
      const items = map[type] || [];
      items.forEach(c => {
        const opt = document.createElement("option");
        opt.value = c.id;
        opt.textContent = (c.name || c.code || '') + (c.phone ? " (" + c.phone + ")" : "") + (c.school_level ? ' · ' + c.school_level : '');
        sel.appendChild(opt);
      });
    },

    filterContactList: function (query) {
      const sel = document.getElementById("specificRecipients");
      const q = query.toLowerCase();
      Array.from(sel.options).forEach(opt => {
        opt.style.display = opt.textContent.toLowerCase().includes(q) ? "" : "none";
      });
    },

    suggestTemplate: function (msgType) {
      const matching = this.templates.find(t => t.category === msgType || t.name.toLowerCase().includes(msgType));
      if (matching) {
        document.getElementById("templateSelect").value = matching.id;
        this.applyTemplate(matching.id);
      }
    },

    applyTemplate: function (templateId) {
      if (!templateId) return;
      const tmpl = this.templates.find(t => t.id == templateId);
      if (tmpl && tmpl.template_body) {
        document.getElementById("messageBody").value = tmpl.template_body;
        this.updateCharCount();
      }
    },

    updateCharCount: function () {
      const el = document.getElementById("charCount");
      if (el) el.textContent = document.getElementById("messageBody").value.length;
    },

    openCompose: function () {
      document.getElementById("composeForm").reset();
      document.getElementById("recipientSelector").style.display = "none";
      document.getElementById("groupSelector").style.display = "none";
      document.getElementById("customNumbersDiv").style.display = "none";
      document.getElementById("reminderOptions").style.display = "none";
      document.getElementById("charCount").textContent = "0";
      const sig = document.getElementById("senderSignature");
      if (sig && !sig.value) {
        sig.value = "Kingsway Preparatory School";
      }
      this.composeModal.show();
    },

    collectFormData: function () {
      const recipientType = this.value("recipientType");
      let recipients = [];
      let payloadRecipientType = recipientType;
      if (recipientType === "custom_numbers") {
        recipients = this.value("customNumbers").split(",").map(s => s.trim()).filter(Boolean);
      } else if (recipientType === "contact_group") {
        recipients = [this.value("groupSelect")];
      } else if (recipientType === "custom_selection") {
        recipients = this.recipientPicker ? this.recipientPicker.getTokens() : [];
        payloadRecipientType = "mixed";
      } else if (["selected_students", "selected_class", "student_type", "school_level", "selected_parents", "selected_staff", "selected_vendors"].includes(recipientType)) {
        recipients = Array.from(document.getElementById("specificRecipients").selectedOptions).map(o => o.value);
      } else {
        recipients = [recipientType];
      }
      const message = this.value("messageBody").trim();
      if (!recipients.length) throw new Error("Select at least one recipient");
      const msgType = this.value("messageType");
      const signature = this.value("senderSignature").trim();
      const fullMessage = signature ? message + "\n\n— " + signature : message;
      const scheduleTime = this.value("scheduleTime");
      let reminderAt = null;
      if (document.getElementById("enableReminder").checked) {
        const val = parseInt(this.value("reminderAfter")) || 24;
        const unit = this.value("reminderUnit");
        const ms = unit === "days" ? val * 86400000 : val * 3600000;
        if (scheduleTime) {
          reminderAt = new Date(new Date(scheduleTime).getTime() + ms).toISOString().slice(0, 19).replace("T", " ");
        } else {
          reminderAt = new Date(Date.now() + ms).toISOString().slice(0, 19).replace("T", " ");
        }
      }
      return {
        recipients,
        message: fullMessage,
        type: "whatsapp",
        category: msgType,
        subject: msgType ? msgType.replace(/_/g, " ").replace(/\b\w/g, l => l.toUpperCase()) : "WhatsApp Message",
        status: scheduleTime ? "scheduled" : "sent",
        scheduled_at: scheduleTime || null,
        reminder_at: reminderAt,
        sender_signature: signature,
        recipient_type: payloadRecipientType,
        target_ids: payloadRecipientType === "mixed" ? [] : recipients,
      };
    },

    submitMessage: async function () {
      const msg = this.value("messageBody").trim();
      if (!msg) { this.notify("Message is required", "warning"); return; }
      if (!this.value("recipientType")) { this.notify("Select recipients", "warning"); return; }
      const data = this.collectFormData();
      const sendBtn = document.getElementById("sendBtn");
      sendBtn.disabled = true;
      sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
      try {
        const created = await window.API.communications.createCommunication(data);
        const commId = created?.id || created?.communication_id || created?.data?.id;
        await this.uploadMedia(commId);
        const result = data.status === "sent" && commId
          ? await window.API.communications.dispatchCommunication(commId)
          : created;
        this.composeModal.hide();
        this.notify(result?.status === "failed" ? "WhatsApp delivery failed" : (data.status === "scheduled" ? "WhatsApp scheduled" : "WhatsApp sent or queued"), result?.status === "failed" ? "error" : "success");
        await this.loadData();
      } catch (e) {
        console.error("Send error:", e);
        this.notify(e.message || "Failed to send", "error");
      } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send"></i> Send Now';
      }
    },

    uploadMedia: async function (communicationId) {
      const input = document.getElementById("mediaAttachment");
      if (!input || !input.files || !input.files.length || !communicationId) return;
      const formData = new FormData();
      formData.append("communication_id", communicationId);
      formData.append("file", input.files[0]);
      formData.append("file_name", input.files[0].name);
      formData.append("channel", "whatsapp");
      await window.API.communications.createAttachment(formData);
    },

    applyFilters: function () {
      const search = this.value("searchFilter").toLowerCase();
      const status = this.value("statusFilter").toLowerCase();
      const category = this.value("categoryFilter").toLowerCase();
      const recipient = this.value("recipientFilter").toLowerCase();
      const dateFilter = this.value("dateFilter");

      this.filtered = this.data.filter(item => {
        const s = String(item.content || item.message || item.subject || "").toLowerCase();
        const st = String(item.status || "").toLowerCase();
        const cat = String(item.category || "").toLowerCase();
        const rt = String(item.recipient_type || item.audience || "").toLowerCase();
        const cd = item.created_at ? String(item.created_at).split(" ")[0] : "";
        if (search && !s.includes(search) && !String(item.subject || "").toLowerCase().includes(search)) return false;
        if (status && st !== status) return false;
        if (category && cat !== category) return false;
        if (recipient && !rt.includes(recipient)) return false;
        if (dateFilter && cd !== dateFilter) return false;
        return true;
      });
      this.renderStats();
      this.renderTable();
      this.renderPagination();
    },

    renderStats: function () {
      const all = this.filtered;
      const today = new Date().toISOString().split("T")[0];
      this.setText("sentToday", all.filter(i => (i.status === "sent" || i.status === "delivered") &&
        String(i.created_at || "").startsWith(today)).length);
      this.setText("pendingCount", all.filter(i => i.status === "pending").length);
      this.setText("failedCount", all.filter(i => i.status === "failed").length);
      this.setText("scheduledCount", all.filter(i => i.status === "scheduled").length);
    },

    renderTable: function () {
      const tbody = document.querySelector("#messagesTable tbody");
      if (!tbody) return;
      if (!this.filtered.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No WhatsApp messages found.</td></tr>';
        return;
      }
      const start = (this.currentPage - 1) * this.perPage;
      const page = this.filtered.slice(start, start + this.perPage);
      const self = this;
      tbody.innerHTML = page.map((row, i) => {
        const idx = start + i;
        const msg = String(row.content || row.message || "");
        const preview = msg.length > 60 ? msg.substring(0, 60) + "..." : msg;
        const cat = row.category ? row.category.replace(/_/g, " ").replace(/\b\w/g, l => l.toUpperCase()) : "—";
        const badge = { sent: "bg-success", delivered: "bg-success", draft: "bg-secondary", pending: "bg-warning text-dark", scheduled: "bg-info text-dark", failed: "bg-danger" };
        const stCls = badge[row.status] || "bg-light text-dark border";
        return `<tr>
          <td class="small">${self.formatDate(row.created_at || row.scheduled_at)}</td>
          <td><span class="badge bg-light text-dark border">${self.esc(cat)}</span></td>
          <td>${self.esc(row.recipient_type || row.recipient_summary || "—")}</td>
          <td class="small text-truncate" style="max-width:220px">${self.esc(preview)}</td>
          <td><span class="badge ${stCls}">${row.status || "unknown"}</span></td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" data-action="view" data-index="${idx}" title="View"><i class="bi bi-eye"></i></button>
              ${row.status === "failed" || row.status === "pending" ? `<button class="btn btn-outline-warning" data-action="resend" data-index="${idx}" title="Resend"><i class="bi bi-arrow-repeat"></i></button>` : ""}
            </div>
          </td>
        </tr>`;
      }).join("");
      tbody.querySelectorAll("button[data-action='view']").forEach(b => b.addEventListener("click", function () {
        self.viewMessage(Number(this.getAttribute("data-index")));
      }));
      tbody.querySelectorAll("button[data-action='resend']").forEach(b => b.addEventListener("click", function () {
        self.resendMessage(Number(this.getAttribute("data-index")));
      }));
    },

    renderPagination: function () {
      const container = document.getElementById("pagination");
      if (!container) return;
      const total = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
      this.currentPage = Math.min(this.currentPage, total);
      container.innerHTML = "";
      const add = (label, page, disabled) => {
        const li = document.createElement("li");
        li.className = "page-item" + (disabled ? " disabled" : "") + (page === this.currentPage ? " active" : "");
        const a = document.createElement("a");
        a.className = "page-link";
        a.href = "#";
        a.textContent = label;
        if (!disabled) a.addEventListener("click", e => { e.preventDefault(); this.currentPage = page; this.renderTable(); });
        li.appendChild(a);
        container.appendChild(li);
      };
      add("«", this.currentPage - 1, this.currentPage <= 1);
      for (let p = 1; p <= total; p++) add(p, p, false);
      add("»", this.currentPage + 1, this.currentPage >= total);
    },

    viewMessage: async function (index) {
      const item = this.filtered[index];
      if (!item) return;
      await window.infoDialog('Notice', "Recipients: " + (item.recipient_type || item.recipient_summary || "—") +
        "\nType: " + (item.category || "—") +
        "\nStatus: " + (item.status || "—") +
        "\n\n" + String(item.content || item.message || item.subject || "No message").substring(0, 1600));
    },

    resendMessage: async function (index) {
      const item = this.filtered[index];
      if (!item || !(await window.confirmAction('Confirm', "Resend this WhatsApp message?"))) return;
      const id = item.id || item.communication_id;
      if (!id) { this.notify("Missing ID", "error"); return; }
      try {
        const btn = document.querySelector(`button[data-action="resend"][data-index="${index}"]`);
        if (btn) btn.disabled = true;
        const result = window.API?.communications?.resendCommunication
          ? await window.API.communications.resendCommunication({ id })
          : null;
        if (result?.status === "success") this.notify(result.message || "Resent", "success");
        else this.notify(result?.message || "Resend failed", "error");
        await this.loadData();
      } catch (e) {
        console.error(e);
        this.notify("Failed to resend", "error");
      }
    },

    exportWa: function () {
      if (window.AuthContext?.canExport && !AuthContext.canExport("communications")) return;
      const rows = [["Date/Time", "Type", "Recipient", "Message", "Status"]];
      (this.filtered || []).forEach((item) => {
        rows.push([
          this.formatDate(item.created_at || item.sent_at || item.scheduled_at),
          item.category ? String(item.category).replace(/_/g, " ") : "",
          item.recipient_type || item.recipient_summary || "",
          String(item.content || item.message || item.body || "").replace(/\s+/g, " ").slice(0, 500),
          item.status || "",
        ]);
      });
      const csv = rows.map((row) => row.map((v) => '"' + String(v ?? "").replace(/"/g, '""') + '"').join(",")).join("\r\n");
      if (window.KingswayFileLifecycle?.exportText) {
        window.KingswayFileLifecycle.exportText(csv, "whatsapp_messages_" + new Date().toISOString().slice(0, 10) + ".csv", "text/csv;charset=utf-8;");
      } else {
        this.notify("Export unavailable", "error");
      }
    },

    printWa: function () {
      if (window.AuthContext?.canPrint && !AuthContext.canPrint("communications")) return;
      window.PrintManager?.printPage({ title: "WhatsApp Messages", orientation: "landscape" }) ?? window.print();
    },

    unwrapList: function (response, keys) {
      if (!response) return [];
      if (Array.isArray(response)) return response;
      if (response.data && Array.isArray(response.data)) return response.data;
      for (const k of (keys || [])) {
        if (Array.isArray(response[k])) return response[k];
        if (response.data && Array.isArray(response.data[k])) return response.data[k];
      }
      return [];
    },

    formatDate: function (v) {
      if (!v) return "—";
      const d = new Date(v);
      return isNaN(d.getTime()) ? v : d.toLocaleString("en-KE", { month: "short", day: "2-digit", hour: "2-digit", minute: "2-digit" });
    },

    esc: function (v) { return String(v || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); },
    value: function (id) { const el = document.getElementById(id); return el ? el.value || "" : ""; },
    setText: function (id, v) { const el = document.getElementById(id); if (el) el.textContent = String(v); },
    on: function (id, ev, fn) { const el = document.getElementById(id); if (el) el.addEventListener(ev, fn); },
    notify: function (msg, type) { if (typeof showNotification === "function") showNotification(msg, type || "info"); else console.log(type + ": " + msg); },
  };

  window.ManageWhatsAppController = ManageWhatsAppController;
  document.addEventListener("DOMContentLoaded", () => ManageWhatsAppController.init());
})();
