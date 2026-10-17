(function () {
  "use strict";

  const ManageSmsController = {
    data: [],
    filtered: [],
    contacts: [],
    groups: [],
    templates: [],
    audiences: { parents: [], students: [], classes: [], student_types: [], school_levels: [], vendors: [], staff: [] },
    currentPage: 1,
    perPage: 10,
    activeContacts: [],
    composeModal: null,
    groupModal: null,
    contactModal: null,
    activeGroupId: null,
    remoteLoaded: false,
    gatewayLoaded: false,

    init: async function () {
      if (!document.getElementById("messagesTable")) return;
      await window.AuthContext?.ready();
      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }
      this.composeModal = new bootstrap.Modal(document.getElementById("composeModal"));
      this.groupModal = new bootstrap.Modal(document.getElementById("groupModal"));
      this.contactModal = new bootstrap.Modal(document.getElementById("contactModal"));
      this.bindEvents();
      this.updateCharMetrics();
      this.toggleChannelOptions(this.value("messageChannel") || "sms");
      this.subscribeRealtime();
      this.applyPermissionGates();
      await Promise.all([this.loadData(), this.loadGroups(), this.loadTemplates(), this.loadAudienceOptions()]);
      this.setupVariableMenu();
      this.checkBalance();
    },

    setupVariableMenu: function () {
      const menu = document.getElementById("messageVariableMenu");
      if (!menu || !window.RecipientPicker) return;
      const self = this;
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
      const ex = document.getElementById("exportSmsBtn");
      const pr = document.getElementById("printSmsBtn");
      if (ex && !canExport) ex.style.display = "none";
      if (pr && !canPrint) pr.style.display = "none";
    },

    bindEvents: function () {
      const self = this;
      this.on("composeBtn", "click", () => self.openCompose());
      this.on("sendBtn", "click", () => self.submitMessage());
      this.on("checkBalanceBtn", "click", () => self.checkBalance());
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
      this.on("messageBody", "input", function () { self.updateCharMetrics(); });
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

      this.on("messageChannel", "change", function () { self.toggleChannelOptions(this.value); });

      // Tabs: lazy-load provider data on first open.
      this.on("tab-gateway", "shown.bs.tab", function () { if (!self.gatewayLoaded) self.loadGateway(); });
      this.on("tab-log", "shown.bs.tab", function () { if (!self.remoteLoaded) self.loadRemoteMessages(); });
      this.on("tab-contacts", "shown.bs.tab", function () { if (!self.groups.length) self.loadGroups(); });

      // Groups
      this.on("addGroupBtn", "click", function () { self.openGroupModal(); });
      this.on("saveGroupBtn", "click", function () { self.saveGroup(); });
      this.on("syncGroupsBtn", "click", function () { self.syncGroups(); });
      this.on("provisionGroupsBtn", "click", function () { self.provisionGroups(); });

      // Contacts
      this.on("addContactBtn", "click", function () { self.openContactModal(); });
      this.on("saveContactBtn", "click", function () { self.saveContact(); });
      this.on("syncContactsBtn", "click", function () { self.syncContacts(); });
      this.on("importContactsBtn", "click", function () { self.importContacts(); });
      this.on("contactFilter", "input", function () { self.renderContacts(); });

      // Gateway / log
      this.on("refreshGatewayBtn", "click", function () { self.loadGateway(); });
      this.on("refreshProfileBtn", "click", function () { self.loadProfile(); });
      this.on("refreshBalanceBtn", "click", function () { self.checkBalance(); });
      this.on("refreshRemoteMsgsBtn", "click", function () { self.loadRemoteMessages(); });

      // Export / print
      this.on("exportSmsBtn", "click", function () { self.exportSms(); });
      this.on("printSmsBtn", "click", function () { self.printSms(); });
    },

    subscribeRealtime: function () {
      const self = this;
      window.APIRealtime?.register?.('manageSms', this, ['communications']);
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
          if (scope && scope !== "all" && !scope.includes("communic") && !scope.includes("sms")) return;
          refresh();
        });
      });
    },

    toggleChannelOptions: function (channel) {
      const mms = document.getElementById("mediaUrlDiv");
      const voice = document.getElementById("voiceOptionsDiv");
      const hashed = document.getElementById("hashedOptionsDiv");
      if (mms) mms.style.display = channel === "mms" ? "" : "none";
      if (voice) voice.style.display = channel === "voice" ? "" : "none";
      if (hashed) hashed.style.display = channel === "hashed" ? "" : "none";
      const campaign = channel === "campaign";
      const hint = document.getElementById("groupSelector");
      if (campaign && hint) hint.style.display = "";
    },

    loadData: async function () {
      try {
        const response = await window.API.communications.getMessages({ type: "sms" });
        this.data = this.unwrapList(response, ["items", "communications", "messages"]);
      } catch (e) {
        console.error("Failed to load SMS records:", e);
        this.data = [];
      }
      this.applyFilters();
    },

    loadContacts: async function () {
      try {
        const res = await window.API.communications.getContact();
        this.contacts = Array.isArray(res) ? res : (res?.data || []);
      } catch (e) {
        this.contacts = [];
      }
    },

    loadGroups: async function () {
      try {
        let list = [];
        if (window.API.messaging) {
          const res = await window.API.messaging.getGroups();
          list = this.unwrapList(res, ["groups"]);
        }
        if (!list.length) {
          const res = await window.API.communications.getGroup();
          list = Array.isArray(res) ? res : (res?.data || []);
        }
        this.groups = list;
      } catch (e) {
        this.groups = [];
      }
      const sel = document.getElementById("groupSelect");
      if (sel) {
        sel.innerHTML = '<option value="">Select group...</option>' +
          this.groups.map(g => `<option value="${g.id}">${this.esc(g.name)}</option>`).join("");
      }
      this.renderGroups();
    },

    renderGroups: function () {
      const container = document.getElementById("groupsList");
      if (!container) return;
      if (!this.groups.length) {
        container.innerHTML = '<div class="text-center text-muted py-3 small">No groups yet. Add one or sync from the provider.</div>';
        return;
      }
      const self = this;
      container.innerHTML = this.groups.map(g => {
        const active = String(g.id) === String(self.activeGroupId);
        const count = Number(g.contact_count || g.contacts_count || 0);
        const synced = g.sync_status ? `<span class="badge bg-light text-dark border ms-1">${self.esc(g.sync_status)}</span>` : "";
        return `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${active ? "active" : ""}" data-group-id="${g.id}">
            <span><i class="bi bi-people"></i> ${self.esc(g.name)} ${synced}</span>
            <span class="d-flex align-items-center gap-1">
              <span class="badge ${active ? "bg-light text-dark" : "bg-secondary"} rounded-pill">${count}</span>
              <span class="btn-group btn-group-sm">
                <span class="btn btn-link p-0 px-1 text-${active ? "light" : "primary"}" data-group-action="edit" title="Rename"><i class="bi bi-pencil"></i></span>
                <span class="btn btn-link p-0 px-1 text-${active ? "light" : "danger"}" data-group-action="delete" title="Delete"><i class="bi bi-trash"></i></span>
              </span>
            </span>
          </button>`;
      }).join("");
      container.querySelectorAll("button[data-group-id]").forEach(btn => btn.addEventListener("click", function (e) {
        const action = e.target.closest("[data-group-action]")?.getAttribute("data-group-action");
        const groupId = this.getAttribute("data-group-id");
        if (action === "edit") { e.stopPropagation(); self.openGroupModal(groupId); return; }
        if (action === "delete") { e.stopPropagation(); self.deleteGroup(groupId); return; }
        self.selectGroup(groupId);
      }));
    },

    selectGroup: function (groupId) {
      this.activeGroupId = groupId;
      const group = this.groups.find(g => String(g.id) === String(groupId));
      this.setText("activeGroupLabel", group ? "· " + group.name : "");
      this.renderGroups();
      this.loadGroupContacts();
    },

    loadGroupContacts: async function () {
      if (!this.activeGroupId) return;
      try {
        const res = await window.API.messaging.getContacts(this.activeGroupId, this.value("contactFilter"));
        this.activeContacts = this.unwrapList(res, ["contacts"]);
      } catch (e) {
        this.activeContacts = [];
        this.notify("Unable to load contacts", "warning");
      }
      this.renderContacts();
    },

    renderContacts: function () {
      const tbody = document.querySelector("#contactsTable tbody");
      if (!tbody) return;
      const q = this.value("contactFilter").toLowerCase();
      const rows = (this.activeContacts || []).filter(c =>
        !q || String(c.name || "").toLowerCase().includes(q) || String(c.phone || "").includes(q));
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No contacts.</td></tr>';
        return;
      }
      const self = this;
      tbody.innerHTML = rows.map(c => {
        const name = c.name || [c.first_name, c.last_name].filter(Boolean).join(" ") || "—";
        const status = c.status || "active";
        const badge = status === "synced" || status === "active" ? "bg-success" : "bg-secondary";
        return `<tr>
          <td class="small">${self.esc(name)}</td>
          <td class="small">${self.esc(c.phone || c.address || "—")}</td>
          <td><span class="badge ${badge}">${self.esc(status)}</span></td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" data-contact-action="edit" data-id="${c.id}"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-outline-danger" data-contact-action="delete" data-id="${c.id}"><i class="bi bi-trash"></i></button>
            </div>
          </td>
        </tr>`;
      }).join("");
      tbody.querySelectorAll("button[data-contact-action]").forEach(btn => btn.addEventListener("click", function () {
        const id = this.getAttribute("data-id");
        if (this.getAttribute("data-contact-action") === "edit") self.openContactModal(id);
        else self.deleteContact(id);
      }));
    },

    // ── Group CRUD ──────────────────────────────────────────────────────────
    openGroupModal: function (groupId) {
      const group = groupId ? this.groups.find(g => String(g.id) === String(groupId)) : null;
      document.getElementById("groupEditId").value = group ? group.id : "";
      document.getElementById("groupName").value = group ? (group.name || "") : "";
      document.getElementById("groupModalTitle").innerHTML = group
        ? '<i class="bi bi-pencil"></i> Rename Group'
        : '<i class="bi bi-plus-lg"></i> New Group';
      this.groupModal.show();
    },

    saveGroup: async function () {
      const name = this.value("groupName").trim();
      const id = this.value("groupEditId");
      if (!name) { this.notify("Group name is required", "warning"); return; }
      const btn = document.getElementById("saveGroupBtn");
      btn.disabled = true;
      try {
        if (id) await window.API.messaging.updateGroup(id, { name });
        else await window.API.messaging.createGroup({ name });
        this.groupModal.hide();
        this.notify(id ? "Group updated" : "Group created", "success");
        await this.loadGroups();
      } catch (e) {
        this.notify(e.message || "Failed to save group", "error");
      } finally {
        btn.disabled = false;
      }
    },

    deleteGroup: async function (groupId) {
      if (!(await window.confirmAction("Confirm", "Delete this contact group?"))) return;
      try {
        await window.API.messaging.deleteGroup(groupId);
        if (String(this.activeGroupId) === String(groupId)) { this.activeGroupId = null; this.activeContacts = []; this.renderContacts(); }
        this.notify("Group deleted", "success");
        await this.loadGroups();
      } catch (e) {
        this.notify(e.message || "Failed to delete group", "error");
      }
    },

    syncGroups: async function () {
      const btn = document.getElementById("syncGroupsBtn");
      btn.disabled = true;
      try {
        const res = await window.API.messaging.syncGroups();
        this.notify((res?.message) || "Groups synchronised", "success");
        await this.loadGroups();
      } catch (e) {
        this.notify(e.message || "Group sync failed", "error");
      } finally { btn.disabled = false; }
    },

    provisionGroups: async function () {
      const btn = document.getElementById("provisionGroupsBtn");
      btn.disabled = true;
      try {
        const res = await window.API.messaging.provisionGroups();
        this.notify((res?.message) || "Groups provisioned", "success");
        await this.loadGroups();
      } catch (e) {
        this.notify(e.message || "Provisioning failed", "error");
      } finally { btn.disabled = false; }
    },

    // ── Contact CRUD ────────────────────────────────────────────────────────
    openContactModal: function (contactId) {
      if (!this.activeGroupId) { this.notify("Select a group first", "warning"); return; }
      const contact = contactId ? (this.activeContacts || []).find(c => String(c.id) === String(contactId)) : null;
      document.getElementById("contactEditId").value = contact ? contact.id : "";
      document.getElementById("contactGroupId").value = this.activeGroupId;
      document.getElementById("contactPhone").value = contact ? (contact.phone || contact.address || "") : "";
      document.getElementById("contactFirstName").value = contact ? (contact.first_name || "") : "";
      document.getElementById("contactLastName").value = contact ? (contact.last_name || "") : "";
      document.getElementById("contactModalTitle").innerHTML = contact
        ? '<i class="bi bi-pencil"></i> Edit Contact'
        : '<i class="bi bi-person-plus"></i> Add Contact';
      this.contactModal.show();
    },

    saveContact: async function () {
      const groupId = this.value("contactGroupId");
      const id = this.value("contactEditId");
      const phone = this.value("contactPhone").trim();
      if (!phone) { this.notify("Phone is required", "warning"); return; }
      const payload = {
        group_id: groupId,
        phone,
        first_name: this.value("contactFirstName").trim(),
        last_name: this.value("contactLastName").trim(),
      };
      const btn = document.getElementById("saveContactBtn");
      btn.disabled = true;
      try {
        if (id) await window.API.messaging.updateContact(id, payload);
        else await window.API.messaging.addContact(payload);
        this.contactModal.hide();
        this.notify(id ? "Contact updated" : "Contact added", "success");
        await this.loadGroupContacts();
      } catch (e) {
        this.notify(e.message || "Failed to save contact", "error");
      } finally {
        btn.disabled = false;
      }
    },

    deleteContact: async function (contactId) {
      if (!(await window.confirmAction("Confirm", "Delete this contact?"))) return;
      try {
        await window.API.messaging.deleteContact(contactId);
        this.notify("Contact deleted", "success");
        await this.loadGroupContacts();
      } catch (e) {
        this.notify(e.message || "Failed to delete contact", "error");
      }
    },

    syncContacts: async function () {
      if (!this.activeGroupId) { this.notify("Select a group first", "warning"); return; }
      const btn = document.getElementById("syncContactsBtn");
      btn.disabled = true;
      try {
        const res = await window.API.messaging.syncContacts(this.activeGroupId);
        this.notify((res?.message) || "Contacts synchronised", "success");
        await this.loadGroupContacts();
      } catch (e) {
        this.notify(e.message || "Contact sync failed", "error");
      } finally { btn.disabled = false; }
    },

    importContacts: async function () {
      if (!this.activeGroupId) { this.notify("Select a group first", "warning"); return; }
      const group = this.groups.find(g => String(g.id) === String(this.activeGroupId));
      const entity = group ? (group.entity_type || "") : "";
      if (!["parents", "staff", "students", "vendors"].includes(String(entity).toLowerCase())) {
        this.notify("This group is not linked to a school directory audience.", "warning");
        return;
      }
      const btn = document.getElementById("importContactsBtn");
      btn.disabled = true;
      try {
        const res = await window.API.messaging.importContacts(this.activeGroupId);
        this.notify((res?.message) || "Contacts imported from directory", "success");
        await Promise.all([this.loadGroups(), this.loadGroupContacts()]);
      } catch (e) {
        this.notify(e.message || "Import failed", "error");
      } finally { btn.disabled = false; }
    },

    // ── Gateway / profile / log ─────────────────────────────────────────────
    loadGateway: async function () {
      if (!window.API.messaging) return;
      try {
        const res = await window.API.messaging.getGateway();
        const info = res?.data && typeof res.data === "object" ? res.data : (res || {});
        const rows = [
          ["Provider", info.provider || "—"],
          ["Sender ID", info.sender_id || "—"],
          ["API URL", info.api_url || "—"],
          ["API Key", info.api_key_masked || "—"],
          ["Configured", info.configured ? "Yes" : "No"],
        ];
        document.getElementById("gatewayInfo").innerHTML = rows.map(([k, v]) =>
          `<dt class="col-5 text-muted fw-normal">${this.esc(k)}</dt><dd class="col-7">${this.esc(v)}</dd>`).join("");
        this.gatewayLoaded = true;
      } catch (e) {
        document.getElementById("gatewayInfo").innerHTML = '<dd class="text-danger">Unable to load provider info.</dd>';
      }
      this.loadSmsTopup();
    },

    loadSmsTopup: async function () {
      const set = (id, value, fallback = "—") => {
        const el = document.getElementById(id);
        if (el) el.textContent = value || fallback;
      };
      try {
        const res = await window.API.system.getCommunicationConfigs();
        const data = res?.data && typeof res.data === "object" ? res.data : (res || {});
        const fields = Array.isArray(data.sms) ? data.sms : [];
        const byKey = {};
        fields.forEach(f => { byKey[f.key] = f.value; });
        set("smsTopupPaybill", byKey["comms_sms_topup_paybill"]);
        set("smsTopupAccount", byKey["comms_sms_topup_account"]);
        set("smsTopupNotes", byKey["comms_sms_topup_notes"], "Units are credited automatically after confirmation.");
      } catch (e) {
        // Non-admins cannot read system configuration; show the guidance text only.
        set("smsTopupPaybill", null);
        set("smsTopupAccount", null);
      }
    },

    loadProfile: async function () {
      try {
        const res = await window.API.messaging.getProfile();
        const profile = res?.data?.profile ?? res?.profile ?? res?.data ?? res;
        document.getElementById("profilePanel").innerHTML =
          '<pre class="small bg-light border rounded p-2 mb-0" style="max-height:220px;overflow:auto;">' +
          this.esc(typeof profile === "object" ? JSON.stringify(profile, null, 2) : String(profile)) + "</pre>";
      } catch (e) {
        this.notify("Unable to load profile", "warning");
      }
    },

    loadRemoteMessages: async function () {
      const tbody = document.querySelector("#remoteMessagesTable tbody");
      if (!tbody) return;
      try {
        const res = await window.API.messaging.getRemoteMessages();
        const list = this.unwrapList(res, ["messages", "data"]);
        if (!list.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No provider messages.</td></tr>';
          return;
        }
        const self = this;
        tbody.innerHTML = list.map(m => `<tr>
          <td class="small">${self.esc(m.uid || m.id || "—")}</td>
          <td class="small">${self.esc(m.recipient || m.to || "—")}</td>
          <td><span class="badge bg-light text-dark border">${self.esc(m.status || "—")}</span></td>
          <td class="small">${self.esc(m.type || "—")}</td>
          <td class="small">${self.formatDate(m.sent_at || m.created_at)}</td>
        </tr>`).join("");
        this.remoteLoaded = true;
      } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Unable to load provider log.</td></tr>';
      }
    },

    loadTemplates: async function () {
      try {
        const res = await window.API.communications.getTemplates();
        this.templates = Array.isArray(res) ? res : (res?.data || []);
        const sel = document.getElementById("templateSelect");
        if (sel) {
          sel.innerHTML = '<option value="">No template</option>' +
            this.templates.filter(t => t.template_type === 'sms' || !t.template_type)
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
        opt.textContent = (c.name || c.code || '') + (c.phone ? " (" + c.phone + ")" : '') + (c.school_level ? ' · ' + c.school_level : '');
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
        this.updateCharMetrics();
      }
    },

    updateCharMetrics: function () {
      const msg = this.value("messageBody");
      const length = msg.length;
      const smsCount = length === 0 ? 0 : Math.ceil(length / 160);
      const costPerSms = 0.8;
      this.setText("charCount", length);
      this.setText("smsCount", smsCount);
      this.setText("estimatedCost", (smsCount * costPerSms).toFixed(2));
    },

    openCompose: function () {
      document.getElementById("composeForm").reset();
      document.getElementById("recipientSelector").style.display = "none";
      document.getElementById("groupSelector").style.display = "none";
      document.getElementById("customNumbersDiv").style.display = "none";
      document.getElementById("reminderOptions").style.display = "none";
      this.updateCharMetrics();
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
        type: "sms",
        subject: msgType ? msgType.replace(/_/g, " ").replace(/\b\w/g, l => l.toUpperCase()) : "SMS Broadcast",
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
      const channel = this.value("messageChannel") || "sms";
      if (!msg && channel !== "mms") { this.notify("Message is required", "warning"); return; }
      if (!this.value("recipientType")) { this.notify("Select recipients", "warning"); return; }
      const sendBtn = document.getElementById("sendBtn");
      sendBtn.disabled = true;
      sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
      try {
        if (channel !== "sms") {
          await this.sendViaChannel(channel, msg);
        } else {
          const data = this.collectFormData();
          const created = await window.API.communications.createCommunication(data);
          const commId = created?.id || created?.communication_id || created?.data?.id;
          const result = data.status === "sent" && commId
            ? await window.API.communications.dispatchCommunication(commId)
            : created;
          this.notify(result?.status === "failed" ? "SMS delivery failed" : (data.status === "scheduled" ? "SMS scheduled" : "SMS sent or queued"), result?.status === "failed" ? "error" : "success");
        }
        this.composeModal.hide();
        await this.loadData();
      } catch (e) {
        console.error("Send error:", e);
        this.notify(e.message || "Failed to send", "error");
      } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send"></i> Send Message';
      }
    },

    sendViaChannel: async function (channel, message) {
      const recipientType = this.value("recipientType");
      const payload = {
        mode: channel,
        message,
        schedule_time: this.value("scheduleTime") || null,
      };
      if (channel === "mms") payload.media_url = this.value("mediaUrl").trim();
      if (channel === "voice") {
        payload.language = this.value("voiceLanguage") || "en";
        payload.gender = this.value("voiceGender") || "female";
      }
      if (channel === "hashed") payload.recipient_format = this.value("recipientFormat") || "hashed";

      if (recipientType === "custom_numbers") {
        payload.recipients = this.value("customNumbers").split(",").map(s => s.trim()).filter(Boolean);
      } else if (recipientType === "contact_group" || channel === "campaign") {
        const groupId = this.value("groupSelect");
        if (!groupId) throw new Error("Select a contact group");
        if (channel === "campaign") payload.contact_list_ids = [groupId];
        else payload.group_id = groupId;
      } else if (recipientType === "all_parents") {
        payload.audience = "parents";
      } else if (recipientType === "all_staff") {
        payload.audience = "staff";
      } else if (recipientType === "all_vendors") {
        payload.audience = "vendors";
      } else if (["selected_students", "selected_class", "student_type", "school_level", "selected_parents", "selected_staff", "selected_vendors", "custom_selection"].includes(recipientType)) {
        throw new Error("Selected recipients are only supported for SMS. Choose a contact group or an all-<audience> option for this channel.");
      }
      const result = await window.API.messaging.send(payload);
      this.notify(result?.message || (result?.status === "scheduled" ? "Message scheduled" : "Message sent or queued"), "success");
    },

    checkBalance: async function () {
      try {
        const response = await window.API.communications.getSmsBalance();
        const payload = response?.data && typeof response.data === "object" ? response.data : response;
        const available = payload?.available !== false;
        const balance = Number(payload?.balance ?? 0);
        const display = available ? balance : "—";
        this.setText("smsBalance", display);
        this.setText("gatewayBalance", display);
        const msg = available
          ? "Balance read from the configured provider."
          : (payload?.message || "SMS balance unavailable.");
        this.setText("gatewayBalanceMsg", msg);
        this.notify(available ? "SMS balance refreshed" : msg, available ? "success" : "warning");
      } catch (e) {
        console.error("Balance fetch error:", e);
        this.setText("smsBalance", "—");
        this.setText("gatewayBalance", "—");
        this.setText("gatewayBalanceMsg", "Unable to fetch SMS balance.");
        this.notify("Unable to fetch SMS balance", "warning");
      }
    },

    applyFilters: function () {
      const search = this.value("searchFilter").toLowerCase();
      const status = this.value("statusFilter").toLowerCase();
      const category = this.value("categoryFilter").toLowerCase();
      const recipient = this.value("recipientFilter").toLowerCase();
      const dateFilter = this.value("dateFilter");

      this.filtered = this.data.filter(item => {
        const content = String(item.content || item.body || item.message || "").toLowerCase();
        const subject = String(item.subject || "SMS Broadcast").toLowerCase();
        const st = String(item.status || "").toLowerCase();
        const summary = String(item.recipient_summary || "").toLowerCase();
        const cd = item.created_at ? String(item.created_at).slice(0, 10) : "";
        if (search && !content.includes(search) && !subject.includes(search) && !summary.includes(search)) return false;
        if (status && st !== status) return false;
        if (category && !subject.replace(/_/g, " ").includes(category.replace(/_/g, " "))) return false;
        if (recipient && !summary.includes(recipient) && !content.includes(recipient) && !subject.includes(recipient)) return false;
        if (dateFilter && cd !== dateFilter) return false;
        return true;
      });
      this.renderStats();
      this.renderTable();
      this.renderPagination();
    },

    renderStats: function () {
      const all = this.data;
      const today = new Date().toISOString().split("T")[0];
      this.setText("sentToday", all.filter(i => ["sent", "delivered"].includes(i.status) &&
        String(i.created_at || "").startsWith(today)).length);
      this.setText("pendingCount", all.filter(i => ["pending", "queued", "scheduled"].includes(i.status)).length);
      this.setText("failedCount", all.filter(i => i.status === "failed").length);
    },

    renderTable: function () {
      const tbody = document.querySelector("#messagesTable tbody");
      if (!tbody) return;
      if (!this.filtered.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No SMS messages found.</td></tr>';
        return;
      }
      const start = (this.currentPage - 1) * this.perPage;
      const page = this.filtered.slice(start, start + this.perPage);
      const self = this;
      tbody.innerHTML = page.map((row, i) => {
        const idx = start + i;
        const msg = String(row.content || row.message || row.body || "");
        const preview = msg.length > 60 ? msg.substring(0, 60) + "..." : msg;
        const type = row.subject ? String(row.subject) : "—";
        const recipientCount = Number(row.recipient_count || 0);
        const recipients = row.recipient_summary || (recipientCount ? recipientCount + " recipients" : "—");
        const badge = { sent: "bg-success", delivered: "bg-success", queued: "bg-info text-dark", draft: "bg-secondary", pending: "bg-warning text-dark", scheduled: "bg-info text-dark", failed: "bg-danger" };
        const stCls = badge[row.status] || "bg-light text-dark border";
        return `<tr>
          <td class="small">${self.formatDate(row.created_at || row.scheduled_at)}</td>
          <td><span class="badge bg-light text-dark border">${self.esc(type)}</span></td>
          <td class="small">${self.esc(recipients)}</td>
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
      const recipients = item.recipient_summary || (item.recipient_count ? item.recipient_count + " recipients" : "—");
      const msg = String(item.content || item.message || item.body || item.subject || "No message").substring(0, 1600);
      await window.infoDialog('Notice', "Recipients: " + recipients + "\nType: " + (item.subject || "SMS Broadcast") + "\nStatus: " + (item.status || "—") + "\n\n" + msg);
    },

    resendMessage: async function (index) {
      const item = this.filtered[index];
      if (!item || !(await window.confirmAction('Confirm', "Resend this SMS?"))) return;
      const id = item.id || item.communication_id;
      if (!id) { this.notify("Missing ID", "error"); return; }
      try {
        const btn = document.querySelector(`button[data-action="resend"][data-index="${index}"]`);
        if (btn) btn.disabled = true;
        const result = await window.API.communications.resendCommunication({ id });
        if (result?.status === "success") this.notify(result.message || "Resent", "success");
        else this.notify(result?.message || "Resend failed", "error");
        await this.loadData();
      } catch (e) {
        console.error(e);
        this.notify("Failed to resend", "error");
      }
    },

    exportSms: function () {
      if (window.AuthContext?.canExport && !AuthContext.canExport("communications")) return;
      const rows = [["Date/Time", "Type", "Recipients", "Message", "Status", "Channel"]];
      (this.filtered || []).forEach((item) => {
        rows.push([
          this.formatDate(item.created_at || item.sent_at || item.scheduled_at),
          item.subject || item.template_name || "SMS Broadcast",
          item.recipient_summary || (item.recipient_count ? item.recipient_count + " recipients" : ""),
          String(item.content || item.message || item.body || "").replace(/\s+/g, " ").slice(0, 500),
          item.status || "",
          item.channel || item.message_channel || "sms",
        ]);
      });
      const csv = rows.map((row) => row.map((v) => '"' + String(v ?? "").replace(/"/g, '""') + '"').join(",")).join("\r\n");
      if (window.KingswayFileLifecycle?.exportText) {
        window.KingswayFileLifecycle.exportText(csv, "sms_messages_" + new Date().toISOString().slice(0, 10) + ".csv", "text/csv;charset=utf-8;");
      } else {
        this.notify("Export unavailable", "error");
      }
    },

    printSms: function () {
      if (window.AuthContext?.canPrint && !AuthContext.canPrint("communications")) return;
      window.PrintManager?.printPage({ title: "SMS Messages", orientation: "landscape" }) ?? window.print();
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

  window.ManageSmsController = ManageSmsController;
  document.addEventListener("DOMContentLoaded", () => ManageSmsController.init());
})();
