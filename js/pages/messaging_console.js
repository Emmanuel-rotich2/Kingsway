(function () {
  "use strict";

  const FOLDERS = [
    { id: "all", label: "All messages", icon: "bi-inbox" },
    { id: "sent", label: "Sent", icon: "bi-check2-circle" },
    { id: "scheduled", label: "Scheduled", icon: "bi-clock" },
    { id: "pending", label: "Pending / Queued", icon: "bi-hourglass-split" },
    { id: "failed", label: "Failed", icon: "bi-x-octagon" },
  ];

  const FOLDER_STATUS = {
    sent: ["sent", "delivered", "read"],
    scheduled: ["scheduled"],
    pending: ["pending", "queued", "draft", "processing"],
    failed: ["failed", "bounced", "rejected", "expired", "undelivered"],
  };

  const SELECTED_RECIPIENT_TYPES = ["selected_students", "selected_class", "student_type", "school_level", "selected_parents", "selected_staff", "selected_vendors"];

  function badgeClass(status) {
    const st = String(status || "").toLowerCase();
    if (["sent", "delivered", "read", "completed", "success", "synced", "active"].includes(st)) return "kw-badge kw-badge-ok";
    if (["scheduled", "queued", "pending", "draft", "waiting"].includes(st)) return "kw-badge kw-badge-warn";
    if (["failed", "error", "bounced", "rejected", "expired", "undelivered"].includes(st)) return "kw-badge kw-badge-err";
    if (["processing", "sending", "dispatched"].includes(st)) return "kw-badge kw-badge-info";
    return "kw-badge";
  }

  function severityRank(status) {
    const st = String(status || "").toLowerCase();
    if (["failed", "error", "bounced", "rejected", "expired", "undelivered"].includes(st)) return 5;
    if (["pending", "queued", "sending", "processing", "dispatched", "sent", "waiting"].includes(st)) return 3;
    if (["delivered", "read", "completed", "success"].includes(st)) return 1;
    return st ? 2 : 0;
  }

  function create(config) {
    if (!document.getElementById("messagesTable")) return null;

    const Console = {
      cfg: Object.assign(
        {
          channel: "sms",
          realtimeKey: "manageSms",
          permPrefix: "sms",
          title: "SMS",
          sendLabel: '<i class="bi bi-send"></i> Send Message',
        },
        config || {}
      ),
      root: null,
      data: [],
      filtered: [],
      groups: [],
      contacts: [],
      templates: [],
      optOuts: [],
      audiences: { parents: [], students: [], classes: [], student_types: [], school_levels: [], vendors: [], staff: [] },
      currentPage: 1,
      perPage: 10,
      folder: "all",
      selectedId: null,
      activeGroupId: null,
      gatewayLoaded: false,
      optOutsLoaded: false,
      remoteLoaded: false,
      recipientPicker: null,
      composeOffcanvas: null,
      groupModal: null,
      contactsModal: null,
      templateModal: null,

      init: async function () {
        this.root = document.getElementById("kwConsole");
        if (this.cfg.channel === "whatsapp") this.cfg.sendLabel = '<i class="bi bi-send"></i> Send Now';
        await window.AuthContext?.ready();
        if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
          window.location.href = (window.APP_BASE || "") + "/index.php";
          return;
        }
        const composeEl = document.getElementById("composeOffcanvas");
        this.composeOffcanvas = window.bootstrap?.Offcanvas && composeEl ? new bootstrap.Offcanvas(composeEl) : null;
        const groupEl = document.getElementById("groupModal");
        const contactsEl = document.getElementById("contactsModal");
        const templateEl = document.getElementById("templateModal");
        if (groupEl) this.groupModal = new bootstrap.Modal(groupEl);
        if (contactsEl) this.contactsModal = new bootstrap.Modal(contactsEl);
        if (templateEl) this.templateModal = new bootstrap.Modal(templateEl);
        this.bindEvents();
        this.updateCharMetrics();
        this.subscribeRealtime();
        this.applyPermissionGates();
        await Promise.all([this.loadData(), this.loadGroups(), this.loadTemplates(), this.loadAudienceOptions()]);
        this.setupVariableMenu();
        if (this.cfg.channel === "sms") this.checkBalance();
        else this.hideBalanceChip();
        this.loadGateway();
      },

      hideBalanceChip: function () {
        const chip = document.getElementById("smsBalanceChip");
        if (chip) chip.style.display = "none";
      },

      setupVariableMenu: function () {
        const menu = document.getElementById("messageVariableMenu");
        if (!menu || !window.RecipientPicker) return;
        const self = this;
        menu.innerHTML = window.RecipientPicker.variables()
          .map((v) => '<li><button type="button" class="dropdown-item" data-var="' + v.token + '">' + v.token + ' <span class="text-muted small">' + v.label + '</span></button></li>')
          .join("");
        menu.querySelectorAll("[data-var]").forEach(function (btn) {
          btn.addEventListener("click", function () {
            window.RecipientPicker.insertVariable("messageBody", this.getAttribute("data-var"));
          });
        });
        menu.querySelectorAll("[data-var]").forEach((b) => b.addEventListener("click", () => self.updateCharMetrics()));
      },

      applyPermissionGates: function () {
        const canExport = window.AuthContext?.canExport ? AuthContext.canExport("communications") : true;
        const canPrint = window.AuthContext?.canPrint ? AuthContext.canPrint("communications") : true;
        const ex = document.getElementById("exportBtn");
        const pr = document.getElementById("printBtn");
        if (ex && !canExport) ex.style.display = "none";
        if (pr && !canPrint) pr.style.display = "none";
      },

      bindEvents: function () {
        const self = this;
        this.on("composeBtn", "click", () => self.openCompose());
        this.on("sendBtn", "click", () => self.submitMessage());
        this.on("refreshBtn", "click", async () => { await self.loadData(); if (self.cfg.channel === "sms") self.checkBalance(); self.notify("Refreshed", "info"); });
        this.on("exportBtn", "click", () => self.exportConsole());
        this.on("printBtn", "click", () => self.printConsole());

        this.root.querySelectorAll("[data-folder]").forEach(function (btn) {
          btn.addEventListener("click", function () { self.setFolder(this.getAttribute("data-folder")); });
        });
        ["searchFilter"].forEach(function (id) {
          self.on(id, "input", function () { self.currentPage = 1; self.applyFilters(); });
        });
        ["statusFilter", "categoryFilter", "dateFilter"].forEach(function (id) {
          self.on(id, "change", function () {
            if (id === "statusFilter") self.setFolder("all", true);
            self.currentPage = 1;
            self.applyFilters();
          });
        });
        this.on("clearFiltersBtn", "click", function () {
          const s = document.getElementById("searchFilter");
          const st = document.getElementById("statusFilter");
          const c = document.getElementById("categoryFilter");
          const d = document.getElementById("dateFilter");
          if (s) s.value = "";
          if (st) st.value = "";
          if (c) c.value = "";
          if (d) d.value = "all";
          self.setFolder("all", true);
          self.currentPage = 1;
          self.applyFilters();
        });

        this.on("recipientType", "change", function () { self.toggleRecipientSection(this.value); });
        this.on("contactSearch", "input", function () { self.filterContactList(this.value); });
        this.on("enableReminder", "change", function () {
          const ro = document.getElementById("reminderOptions");
          if (ro) ro.style.display = this.checked ? "" : "none";
          if (this.checked && !self.value("reminderAfter")) {
            const ra = document.getElementById("reminderAfter");
            if (ra) ra.value = "24";
          }
        });
        this.on("messageType", "change", function () { self.suggestTemplate(this.value); });
        this.on("templateSelect", "change", function () { self.applyTemplate(this.value); });
        this.on("messageBody", "input", function () { self.updateCharMetrics(); });
        this.on("messageChannel", "change", function () { self.toggleChannelOptions(this.value); self.updateCharMetrics(); });

        this.on("newTemplateBtn", "click", () => self.openTemplateModal());
        this.on("saveTemplateBtn", "click", () => self.saveTemplate());
        const templatesList = document.getElementById("templatesList");
        if (templatesList) templatesList.addEventListener("click", function (e) {
          const actionBtn = e.target.closest("[data-template-action]");
          const item = e.target.closest("[data-template-id]");
          if (!item) return;
          const id = item.getAttribute("data-template-id");
          const action = actionBtn ? actionBtn.getAttribute("data-template-action") : "use";
          if (action === "edit") self.openTemplateModal(id);
          else if (action === "delete") self.deleteTemplate(id);
          else self.useTemplate(id);
        });

        this.on("addGroupBtn", "click", () => self.openGroupModal());
        this.on("saveGroupBtn", "click", () => self.saveGroup());
        this.on("syncGroupsBtn", "click", () => self.syncGroups());
        this.on("provisionGroupsBtn", "click", () => self.provisionGroups());
        const groupsList = document.getElementById("groupsList");
        if (groupsList) groupsList.addEventListener("click", function (e) {
          const actionBtn = e.target.closest("[data-group-action]");
          const item = e.target.closest("[data-group-id]");
          if (!item) return;
          const id = item.getAttribute("data-group-id");
          const action = actionBtn ? actionBtn.getAttribute("data-group-action") : "compose";
          if (action === "edit") self.openGroupModal(id);
          else if (action === "delete") self.deleteGroup(id);
          else if (action === "contacts") self.openContacts(id);
          else self.composeToGroup(id);
        });

        const optOutsCollapse = document.getElementById("optOutsCollapse");
        if (optOutsCollapse) optOutsCollapse.addEventListener("shown.bs.collapse", function () { if (!self.optOutsLoaded) self.loadOptOuts(); });

        this.on("refreshGatewayBtn", "click", () => self.loadGateway(true));
        this.on("refreshBalanceBtn", "click", () => self.checkBalance());
        this.on("loadProfileBtn", "click", () => self.loadProfile());
        const remoteDetails = document.getElementById("remoteLogDetails");
        if (remoteDetails) remoteDetails.addEventListener("toggle", function () { if (this.open && !self.remoteLoaded) self.loadRemoteMessages(); });
        this.on("refreshRemoteMsgsBtn", "click", () => self.loadRemoteMessages(true));

        this.on("addContactBtn", "click", () => self.openContactForm());
        this.on("cancelContactBtn", "click", () => self.hideContactForm());
        this.on("saveContactBtn", "click", () => self.saveContact());
        this.on("syncContactsBtn", "click", () => self.syncContacts());
        this.on("importContactsBtn", "click", () => self.importContacts());
        this.on("contactFilter", "input", function () { self.renderContacts(); });

        const tbody = document.querySelector("#messagesTable tbody");
        if (tbody) tbody.addEventListener("click", function (e) {
          const tr = e.target.closest("tr[data-idx]");
          if (tr) self.openDetail(Number(tr.getAttribute("data-idx")));
        });

        this.on("closeDetailBtn", "click", () => this.closeDetail());
        this.on("resendBtn", "click", () => self.resendDetail());
        this.on("copyBtn", "click", () => self.copyDetail());
        this.on("updateScheduleBtn", "click", () => self.updateSchedule());
      },

      subscribeRealtime: function () {
        const self = this;
        window.APIRealtime?.register?.(this.cfg.realtimeKey, this, ["communications"]);
        const refresh = (() => {
          let timer = null;
          return function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
              if (!self.root || !document.contains(self.root)) return;
              self.loadData();
            }, 400);
          };
        })();
        const channel = this.cfg.channel;
        ["kingsway:data-mutated", "kingsway:job"].forEach(function (evt) {
          window.addEventListener(evt, function (e) {
            const scope = String(e.detail?.scope || "").toLowerCase();
            if (scope && scope !== "all" && !scope.includes("communic") && !scope.includes("sms") && !scope.includes("whatsapp") && !scope.includes(channel)) return;
            refresh();
          });
        });
      },

      loadData: async function () {
        try {
          const response = await window.API.communications.getMessages({ type: this.cfg.channel });
          this.data = this.unwrapList(response, ["items", "communications", "messages"]);
        } catch (e) {
          console.error("Failed to load " + this.cfg.title + " records:", e);
          this.data = [];
        }
        this.applyFilters();
      },

      loadAudienceOptions: async function () {
        try {
          const r = await window.API.communications.getAudienceOptions();
          const data = r?.data || r || {};
          this.audiences = data;
          const allowed = data.allowed_audiences || [];
          const select = document.getElementById("recipientType");
          if (select && allowed.length) Array.from(select.options).forEach((o) => { if (o.value && !allowed.includes(o.value)) o.remove(); });
        } catch (e) {
          console.error("Audience load failed", e);
        }
      },

      setFolder: function (folder, keepStatus) {
        if (!FOLDERS.some((f) => f.id === folder)) folder = "all";
        this.folder = folder;
        if (!keepStatus) {
          const st = document.getElementById("statusFilter");
          if (st) st.value = "";
        }
        this.root.querySelectorAll("[data-folder]").forEach((b) => b.classList.toggle("active", b.getAttribute("data-folder") === folder));
        const scope = document.getElementById("folderScopeLabel");
        if (scope) {
          const def = FOLDERS.find((f) => f.id === folder);
          scope.innerHTML = '<i class="bi ' + (def ? def.icon : "bi-inbox") + '"></i> ' + this.esc(def ? def.label : "All messages");
        }
        this.currentPage = 1;
        this.applyFilters();
      },

      inFolder: function (item) {
        if (this.folder === "all") return true;
        const st = String(item.status || "").toLowerCase();
        return (FOLDER_STATUS[this.folder] || []).includes(st);
      },

      applyFilters: function () {
        const search = this.value("searchFilter").toLowerCase();
        const status = this.value("statusFilter").toLowerCase();
        const category = this.value("categoryFilter").toLowerCase();
        const range = this.value("dateFilter") || "30";
        const today = new Date().toISOString().slice(0, 10);
        let cutoff = null;
        if (range === "1") cutoff = today;
        else if (range !== "all") {
          const d = new Date();
          d.setDate(d.getDate() - (parseInt(range, 10) || 30));
          cutoff = d.toISOString().slice(0, 10);
        }
        this.filtered = this.data.filter((item) => {
          const content = String(item.content || item.body || item.message || "").toLowerCase();
          const subject = String(item.subject || item.category || this.cfg.title + " Broadcast").toLowerCase();
          const st = String(item.status || "").toLowerCase();
          const summary = String(item.recipient_summary || item.recipient_type || "").toLowerCase();
          const cd = item.created_at || item.sent_at || item.scheduled_at ? String(item.created_at || item.sent_at || item.scheduled_at).slice(0, 10) : "";
          if (!this.inFolder(item)) return false;
          if (search && !content.includes(search) && !subject.includes(search) && !summary.includes(search)) return false;
          if (status && st !== status) return false;
          if (category && !String(item.category || "").toLowerCase().includes(category) && !subject.replace(/_/g, " ").includes(category.replace(/_/g, " "))) return false;
          if (cutoff && !(cd && cd >= cutoff)) return false;
          return true;
        });
        this.renderStats();
        this.renderFolderCounts();
        this.renderTable();
        this.renderPagination();
      },

      renderStats: function () {
        const all = this.data;
        const today = new Date().toISOString().slice(0, 10);
        this.setText("sentToday", all.filter((i) => ["sent", "delivered"].includes(String(i.status || "").toLowerCase()) && String(i.created_at || "").startsWith(today)).length);
        this.setText("pendingCount", all.filter((i) => ["pending", "queued", "scheduled", "processing", "draft"].includes(String(i.status || "").toLowerCase())).length);
        this.setText("failedCount", all.filter((i) => String(i.status || "").toLowerCase() === "failed").length);
        this.setText("totalCount", this.filtered.length);
      },

      renderFolderCounts: function () {
        const self = this;
        FOLDERS.forEach(function (f) {
          const count = self.data.filter((item) => {
            if (f.id === "all") return true;
            return (FOLDER_STATUS[f.id] || []).includes(String(item.status || "").toLowerCase());
          }).length;
          self.setText("folderCount" + f.id.charAt(0).toUpperCase() + f.id.slice(1), count);
        });
      },

      renderTable: function () {
        const tbody = document.querySelector("#messagesTable tbody");
        if (!tbody) return;
        if (!this.filtered.length) {
          tbody.innerHTML = '<tr class="kw-empty-row"><td colspan="5">No ' + this.esc(this.cfg.title) + ' messages match the current filters.</td></tr>';
          return;
        }
        const start = (this.currentPage - 1) * this.perPage;
        const page = this.filtered.slice(start, start + this.perPage);
        const self = this;
        tbody.innerHTML = page.map(function (row, i) {
          const msg = String(row.content || row.message || row.body || "");
          const preview = msg.length > 90 ? msg.substring(0, 90) + "…" : (msg || "—");
          const type = row.subject || row.category ? String(row.subject || row.category).replace(/_/g, " ") : self.cfg.title + " Broadcast";
          const recipientCount = Number(row.recipient_count || 0);
          const recipients = row.recipient_summary || row.recipient_type || (recipientCount ? recipientCount + " recipients" : "—");
          const st = String(row.status || "unknown");
          const active = String(row.id || row.communication_id) === String(self.selectedId);
          return '<tr data-idx="' + (start + i) + '" class="' + (active ? "kw-row-active" : "") + '">' +
            '<td class="kw-cell-subject">' + self.esc(type) + "</td>" +
            '<td class="kw-cell-preview">' + self.esc(preview) + "</td>" +
            '<td class="kw-cell-recipients">' + self.esc(recipients) + "</td>" +
            "<td><span class=\"" + badgeClass(st) + '">' + self.esc(st) + "</span></td>" +
            '<td class="text-nowrap">' + self.formatDate(row.created_at || row.scheduled_at) + "</td>" +
            "</tr>";
        }).join("");
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
          if (!disabled) a.addEventListener("click", (e) => { e.preventDefault(); this.currentPage = page; this.renderTable(); this.renderPagination(); });
          li.appendChild(a);
          container.appendChild(li);
        };
        add("«", this.currentPage - 1, this.currentPage <= 1);
        const windowSize = 7;
        let first = Math.max(1, this.currentPage - Math.floor(windowSize / 2));
        const last = Math.min(total, first + windowSize - 1);
        first = Math.max(1, last - windowSize + 1);
        for (let p = first; p <= last; p++) add(p, p, false);
        add("»", this.currentPage + 1, this.currentPage >= total);
      },

      openDetail: async function (index) {
        const item = this.filtered[index];
        if (!item) return;
        const id = item.id || item.communication_id;
        this.selectedId = id;
        const body = String(item.content || item.message || item.body || "No message content.");
        const msgType = String(item.subject || item.category || this.cfg.title + " Broadcast").replace(/_/g, " ");
        const recipients = item.recipient_summary || item.recipient_type || (item.recipient_count ? item.recipient_count + " recipients" : "—");
        const empty = document.getElementById("detailEmpty");
        const content = document.getElementById("detailContent");
        if (empty) empty.hidden = true;
        if (content) content.hidden = false;
        this.setText("detailSubject", msgType);
        const statusEl = document.getElementById("detailStatus");
        if (statusEl) {
          statusEl.className = badgeClass(item.status);
          statusEl.textContent = item.status || "unknown";
        }
        const meta = [];
        meta.push("Created: " + this.formatDate(item.created_at));
        if (item.scheduled_at) meta.push("Scheduled: " + this.formatDate(item.scheduled_at));
        if (item.reminder_at) meta.push("Reminder: " + this.formatDate(item.reminder_at));
        meta.push("Channel: " + String(item.channel || item.message_channel || this.cfg.channel));
        this.setText("detailMeta", meta.join("  ·  "));
        const bodyEl = document.getElementById("detailBody");
        if (bodyEl) bodyEl.textContent = body.substring(0, 2000);
        this.setText("detailAudience", "Recipients: " + recipients + "  ·  Type: " + String(item.recipient_type || "—"));
        const schedWrap = document.getElementById("scheduleEditWrap");
        const isScheduled = String(item.status || "").toLowerCase() === "scheduled";
        if (schedWrap) schedWrap.hidden = !(isScheduled && window.API?.communications?.updateCommunication);
        const schedInput = document.getElementById("detailScheduleTime");
        if (schedInput && item.scheduled_at) schedInput.value = String(item.scheduled_at).replace(" ", "T").slice(0, 16);
        if (this.root && window.matchMedia("(max-width:1200px)").matches) this.root.classList.add("kw-detail-open");
        this.renderTable();
        if (id) this.loadDelivery(id);
      },

      closeDetail: function () {
        this.selectedId = null;
        const empty = document.getElementById("detailEmpty");
        const content = document.getElementById("detailContent");
        if (empty) empty.hidden = false;
        if (content) content.hidden = true;
        if (this.root) this.root.classList.remove("kw-detail-open");
        this.renderTable();
      },

      loadDelivery: async function (id) {
        const listEl = document.getElementById("deliveryList");
        if (!listEl) return;
        if (!window.API?.communications?.getRecipients) {
          listEl.innerHTML = '<div class="kw-rail-empty">Delivery detail unavailable.</div>';
          return;
        }
        listEl.innerHTML = '<div class="kw-rail-empty">Loading delivery status…</div>';
        try {
          const res = await window.API.communications.getRecipients(id);
          if (String(this.selectedId) !== String(id)) return;
          const rows = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);
          if (!rows.length) {
            listEl.innerHTML = '<div class="kw-rail-empty">No per-recipient delivery records yet.</div>';
            this.setText("deliveryCount", "");
            return;
          }
          const groups = this.groupDelivery(rows);
          this.setText("deliveryCount", "· " + groups.length + " endpoint" + (groups.length === 1 ? "" : "s"));
          const self = this;
          listEl.innerHTML = groups.map(function (g) {
            return '<div class="kw-delivery-row">' +
              '<div class="kw-delivery-main">' +
              '<span class="kw-delivery-addr">' + self.esc(g.address || g.name || "—") + "</span>" +
              '<span class="kw-delivery-sub">' + self.esc(g.channels.filter(Boolean).join(", ") || "—") + (g.at ? "  ·  " + self.esc(g.at) : "") + "</span>" +
              (g.error ? '<span class="kw-delivery-err"><i class="bi bi-exclamation-triangle"></i> ' + self.esc(g.error) + "</span>" : "") +
              "</div>" +
              '<div class="kw-delivery-side">' +
              '<span class="' + badgeClass(g.status) + '">' + self.esc(g.status || "unknown") + "</span>" +
              '<span class="kw-delivery-attempts" title="Delivery attempts">' + (g.attempts || 0) + " attempt" + (g.attempts === 1 ? "" : "s") + "</span>" +
              "</div>" +
              "</div>";
          }).join("");
        } catch (e) {
          if (String(this.selectedId) !== String(id)) return;
          listEl.innerHTML = '<div class="kw-rail-empty kw-danger">Unable to load delivery status.</div>';
        }
      },

      groupDelivery: function (rows) {
        const byKey = {};
        rows.forEach((r) => {
          const key = String(r.recipient_id || r.id || "");
          if (!byKey[key]) {
            byKey[key] = { name: r.name || r.recipient_name || "", address: r.address || r.phone || "", channels: [], status: r.endpoint_status || r.status || "", attempts: 0, error: "", at: r.endpoint_delivered_at || r.delivered_at || "" };
          }
          const g = byKey[key];
          if (!g.address && (r.address || r.phone)) g.address = r.address || r.phone;
          if (!g.name && (r.name || r.recipient_name)) g.name = r.name || r.recipient_name;
          if (r.channel && !g.channels.includes(r.channel)) g.channels.push(r.channel);
          const st = r.endpoint_status || r.status || "";
          if (severityRank(st) > severityRank(g.status)) g.status = st;
          g.attempts = Math.max(g.attempts, Number(r.attempt_count || r.delivery_attempts || 0));
          const err = r.last_error || r.error_message || "";
          if (err) g.error = err;
          if (!g.at && (r.endpoint_delivered_at || r.delivered_at)) g.at = r.endpoint_delivered_at || r.delivered_at;
        });
        return Object.values(byKey).sort((a, b) => severityRank(b.status) - severityRank(a.status));
      },

      resendDetail: async function () {
        if (!this.selectedId) return;
        if (!(await window.confirmAction("Confirm", "Resend this message to its recipients?"))) return;
        try {
          const btn = document.getElementById("resendBtn");
          if (btn) btn.disabled = true;
          const result = await window.API.communications.resendCommunication({ id: this.selectedId });
          if (result?.status === "success" || result?.success) this.notify(result.message || "Resent", "success");
          else this.notify(result?.message || "Resend failed", "error");
          await this.loadData();
        } catch (e) {
          this.notify(e.message || "Failed to resend", "error");
        } finally {
          const btn = document.getElementById("resendBtn");
          if (btn) btn.disabled = false;
        }
      },

      copyDetail: function () {
        const body = this.text("detailBody");
        if (!body || body === "No message content.") { this.notify("Nothing to copy", "warning"); return; }
        const done = () => this.notify("Message copied", "success");
        if (navigator.clipboard?.writeText) {
          navigator.clipboard.writeText(body).then(done).catch(() => this.notify("Copy failed", "error"));
        } else {
          const ta = document.createElement("textarea");
          ta.value = body;
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand("copy"); done(); } catch (e) { this.notify("Copy failed", "error"); }
          ta.remove();
        }
      },

      updateSchedule: async function () {
        const value = this.value("detailScheduleTime");
        if (!value || !this.selectedId) { this.notify("Pick a new schedule time", "warning"); return; }
        try {
          const res = await window.API.communications.updateCommunication(this.selectedId, { scheduled_at: value, status: "scheduled" });
          if (res?.success === false) this.notify(res.message || "Reschedule failed", "error");
          else { this.notify("Message rescheduled", "success"); await this.loadData(); this.openDetail(this.filtered.findIndex((i) => String(i.id || i.communication_id) === String(this.selectedId))); }
        } catch (e) {
          this.notify(e.message || "Reschedule failed", "error");
        }
      },

      loadGroups: async function () {
        try {
          let list = [];
          if (window.API.messaging?.getGroups) {
            const res = await window.API.messaging.getGroups();
            list = this.unwrapList(res, ["groups"]);
          }
          if (!list.length && window.API.communications?.getGroup) {
            const res = await window.API.communications.getGroup();
            list = Array.isArray(res) ? res : (res?.data || []);
          }
          this.groups = list;
        } catch (e) {
          this.groups = [];
        }
        const sel = document.getElementById("groupSelect");
        if (sel) {
          const current = sel.value;
          sel.innerHTML = '<option value="">Select group...</option>' +
            this.groups.map((g) => '<option value="' + g.id + '">' + this.esc(g.name) + "</option>").join("");
          if (current) sel.value = current;
        }
        this.renderGroups();
      },

      renderGroups: function () {
        const container = document.getElementById("groupsList");
        if (!container) return;
        if (!this.groups.length) {
          container.innerHTML = '<div class="kw-rail-empty">No groups yet. Add one or sync from the provider.</div>';
          return;
        }
        const self = this;
        container.innerHTML = this.groups.map(function (g) {
          const count = Number(g.contact_count || g.contacts_count || 0);
          return '<div class="kw-rail-item" data-group-id="' + g.id + '">' +
            '<button type="button" class="kw-rail-item-main" title="Compose to this group">' +
            '<i class="bi bi-people"></i>' +
            '<span class="kw-rail-item-label">' + self.esc(g.name) + "</span>" +
            '<span class="kw-folder-count">' + count + "</span>" +
            "</button>" +
            '<span class="kw-rail-item-actions">' +
            '<button data-group-action="contacts" title="Manage contacts"><i class="bi bi-person-lines-fill"></i></button>' +
            '<button data-group-action="edit" title="Rename"><i class="bi bi-pencil"></i></button>' +
            '<button data-group-action="delete" title="Delete"><i class="bi bi-trash"></i></button>' +
            "</span>" +
            "</div>";
        }).join("");
      },

      composeToGroup: function (groupId) {
        this.openCompose();
        const rt = document.getElementById("recipientType");
        const gs = document.getElementById("groupSelect");
        if (rt) rt.value = "contact_group";
        if (gs) gs.value = groupId;
        this.toggleRecipientSection("contact_group");
      },

      openGroupModal: function (groupId) {
        const group = groupId ? this.groups.find((g) => String(g.id) === String(groupId)) : null;
        const idEl = document.getElementById("groupEditId");
        const nameEl = document.getElementById("groupName");
        const titleEl = document.getElementById("groupModalTitle");
        if (idEl) idEl.value = group ? group.id : "";
        if (nameEl) nameEl.value = group ? group.name || "" : "";
        if (titleEl) titleEl.innerHTML = group ? '<i class="bi bi-pencil"></i> Rename Group' : '<i class="bi bi-plus-lg"></i> New Group';
        this.groupModal?.show();
      },

      saveGroup: async function () {
        const name = this.value("groupName").trim();
        const id = this.value("groupEditId");
        if (!name) { this.notify("Group name is required", "warning"); return; }
        const btn = document.getElementById("saveGroupBtn");
        if (btn) btn.disabled = true;
        try {
          if (id) await window.API.messaging.updateGroup(id, { name });
          else await window.API.messaging.createGroup({ name });
          this.groupModal?.hide();
          this.notify(id ? "Group updated" : "Group created", "success");
          await this.loadGroups();
        } catch (e) {
          this.notify(e.message || "Failed to save group", "error");
        } finally {
          if (btn) btn.disabled = false;
        }
      },

      deleteGroup: async function (groupId) {
        if (!(await window.confirmAction("Confirm", "Delete this contact group?"))) return;
        try {
          await window.API.messaging.deleteGroup(groupId);
          if (String(this.activeGroupId) === String(groupId)) { this.activeGroupId = null; this.contacts = []; }
          this.notify("Group deleted", "success");
          await this.loadGroups();
        } catch (e) {
          this.notify(e.message || "Failed to delete group", "error");
        }
      },

      syncGroups: async function () {
        const btn = document.getElementById("syncGroupsBtn");
        if (btn) btn.disabled = true;
        try {
          const res = await window.API.messaging.syncGroups();
          this.notify(res?.message || "Groups synchronised", "success");
          await this.loadGroups();
        } catch (e) {
          this.notify(e.message || "Group sync failed", "error");
        } finally { if (btn) btn.disabled = false; }
      },

      provisionGroups: async function () {
        const btn = document.getElementById("provisionGroupsBtn");
        if (btn) btn.disabled = true;
        try {
          const res = await window.API.messaging.provisionGroups();
          this.notify(res?.message || "Groups provisioned", "success");
          await this.loadGroups();
        } catch (e) {
          this.notify(e.message || "Provisioning failed", "error");
        } finally { if (btn) btn.disabled = false; }
      },

      openContacts: function (groupId) {
        this.activeGroupId = groupId;
        const group = this.groups.find((g) => String(g.id) === String(groupId));
        this.setText("contactsModalTitle", group ? group.name : "Contacts");
        this.hideContactForm();
        const filter = document.getElementById("contactFilter");
        if (filter) filter.value = "";
        this.contactsModal?.show();
        this.loadGroupContacts();
      },

      loadGroupContacts: async function () {
        if (!this.activeGroupId) return;
        try {
          const res = await window.API.messaging.getContacts(this.activeGroupId, this.value("contactFilter"));
          this.contacts = this.unwrapList(res, ["contacts"]);
        } catch (e) {
          this.contacts = [];
          this.notify("Unable to load contacts", "warning");
        }
        this.renderContacts();
      },

      renderContacts: function () {
        const tbody = document.querySelector("#contactsTable tbody");
        if (!tbody) return;
        const q = this.value("contactFilter").toLowerCase();
        const rows = (this.contacts || []).filter((c) =>
          !q || String(c.name || [c.first_name, c.last_name].filter(Boolean).join(" ")).toLowerCase().includes(q) || String(c.phone || c.address || "").includes(q));
        if (!rows.length) {
          tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">No contacts.</td></tr>';
          return;
        }
        const self = this;
        tbody.innerHTML = rows.map(function (c) {
          const name = c.name || [c.first_name, c.last_name].filter(Boolean).join(" ") || "—";
          const status = c.status || "active";
          return "<tr>" +
            '<td class="small">' + self.esc(name) + "</td>" +
            '<td class="small">' + self.esc(c.phone || c.address || "—") + "</td>" +
            '<td class="text-end text-nowrap">' +
            '<span class="' + badgeClass(status) + '">' + self.esc(status) + "</span> " +
            '<div class="btn-group btn-group-sm">' +
            '<button class="btn btn-outline-primary" data-contact-action="edit" data-id="' + c.id + '" title="Edit"><i class="bi bi-pencil"></i></button>' +
            '<button class="btn btn-outline-danger" data-contact-action="delete" data-id="' + c.id + '" title="Delete"><i class="bi bi-trash"></i></button>' +
            "</div></td>" +
            "</tr>";
        }).join("");
        tbody.querySelectorAll("button[data-contact-action]").forEach(function (btn) {
          btn.addEventListener("click", function () {
            const id = this.getAttribute("data-id");
            if (this.getAttribute("data-contact-action") === "edit") self.openContactForm(id);
            else self.deleteContact(id);
          });
        });
      },

      openContactForm: function (contactId) {
        if (!this.activeGroupId) { this.notify("Open a group first", "warning"); return; }
        const contact = contactId ? (this.contacts || []).find((c) => String(c.id) === String(contactId)) : null;
        const form = document.getElementById("contactForm");
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
        set("contactEditId", contact ? contact.id : "");
        set("contactGroupId", this.activeGroupId);
        set("contactPhone", contact ? contact.phone || contact.address || "" : "");
        set("contactFirstName", contact ? contact.first_name || "" : "");
        set("contactLastName", contact ? contact.last_name || "" : "");
        if (form) form.hidden = false;
        const phone = document.getElementById("contactPhone");
        if (phone) phone.focus();
      },

      hideContactForm: function () {
        const form = document.getElementById("contactForm");
        if (form) form.hidden = true;
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
        if (btn) btn.disabled = true;
        try {
          if (id) await window.API.messaging.updateContact(id, payload);
          else await window.API.messaging.addContact(payload);
          this.hideContactForm();
          this.notify(id ? "Contact updated" : "Contact added", "success");
          await Promise.all([this.loadGroupContacts(), this.loadGroups()]);
        } catch (e) {
          this.notify(e.message || "Failed to save contact", "error");
        } finally {
          if (btn) btn.disabled = false;
        }
      },

      deleteContact: async function (contactId) {
        if (!(await window.confirmAction("Confirm", "Delete this contact?"))) return;
        try {
          await window.API.messaging.deleteContact(contactId);
          this.notify("Contact deleted", "success");
          await Promise.all([this.loadGroupContacts(), this.loadGroups()]);
        } catch (e) {
          this.notify(e.message || "Failed to delete contact", "error");
        }
      },

      syncContacts: async function () {
        if (!this.activeGroupId) return;
        const btn = document.getElementById("syncContactsBtn");
        if (btn) btn.disabled = true;
        try {
          const res = await window.API.messaging.syncContacts(this.activeGroupId);
          this.notify(res?.message || "Contacts synchronised", "success");
          await this.loadGroupContacts();
        } catch (e) {
          this.notify(e.message || "Contact sync failed", "error");
        } finally { if (btn) btn.disabled = false; }
      },

      importContacts: async function () {
        if (!this.activeGroupId) return;
        const group = this.groups.find((g) => String(g.id) === String(this.activeGroupId));
        const entity = group ? String(group.entity_type || "").toLowerCase() : "";
        if (!["parents", "staff", "students", "vendors"].includes(entity)) {
          this.notify("This group is not linked to a school directory audience.", "warning");
          return;
        }
        const btn = document.getElementById("importContactsBtn");
        if (btn) btn.disabled = true;
        try {
          const res = await window.API.messaging.importContacts(this.activeGroupId);
          this.notify(res?.message || "Contacts imported from directory", "success");
          await Promise.all([this.loadGroups(), this.loadGroupContacts()]);
        } catch (e) {
          this.notify(e.message || "Import failed", "error");
        } finally { if (btn) btn.disabled = false; }
      },

      templateVisible: function (t) {
        const type = String(t.template_type || "").toLowerCase();
        if (!type) return true;
        if (this.cfg.channel === "whatsapp") return ["whatsapp", "sms", "announcement"].includes(type);
        return type === "sms";
      },

      loadTemplates: async function () {
        try {
          const res = await window.API.communications.getTemplates();
          this.templates = (Array.isArray(res) ? res : (res?.data || [])).filter((t) => this.templateVisible(t));
        } catch (e) {
          this.templates = [];
        }
        this.renderTemplates();
        const sel = document.getElementById("templateSelect");
        if (sel) {
          const current = sel.value;
          sel.innerHTML = '<option value="">No template</option>' +
            this.templates.map((t) => '<option value="' + t.id + '">' + this.esc(t.name) + "</option>").join("");
          if (current) sel.value = current;
        }
      },

      renderTemplates: function () {
        const container = document.getElementById("templatesList");
        if (!container) return;
        if (!this.templates.length) {
          container.innerHTML = '<div class="kw-rail-empty">No templates yet.</div>';
          return;
        }
        const self = this;
        container.innerHTML = this.templates.map(function (t) {
          return '<div class="kw-rail-item" data-template-id="' + t.id + '">' +
            '<button type="button" class="kw-rail-item-main" title="Insert into compose">' +
            '<i class="bi bi-file-earmark-text"></i>' +
            '<span class="kw-rail-item-label">' + self.esc(t.name) + "</span>" +
            '<span class="kw-badge">' + self.esc(t.template_type || "any") + "</span>" +
            "</button>" +
            '<span class="kw-rail-item-actions">' +
            '<button data-template-action="edit" title="Edit"><i class="bi bi-pencil"></i></button>' +
            '<button data-template-action="delete" title="Delete"><i class="bi bi-trash"></i></button>' +
            "</span>" +
            "</div>";
        }).join("");
      },

      useTemplate: function (templateId) {
        const open = this.composeOffcanvas && !document.getElementById("composeOffcanvas").classList.contains("show");
        if (open) this.openCompose();
        const sel = document.getElementById("templateSelect");
        if (sel) sel.value = templateId;
        this.applyTemplate(templateId);
      },

      openTemplateModal: function (templateId) {
        const tmpl = templateId ? this.templates.find((t) => String(t.id) === String(templateId)) : null;
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
        set("templateEditId", tmpl ? tmpl.id : "");
        set("templateName", tmpl ? tmpl.name || "" : "");
        set("templateType", tmpl ? tmpl.template_type || this.cfg.channel : this.cfg.channel);
        set("templateBody", tmpl ? tmpl.template_body || "" : "");
        const titleEl = document.getElementById("templateModalTitle");
        if (titleEl) titleEl.innerHTML = tmpl ? '<i class="bi bi-pencil"></i> Edit Template' : '<i class="bi bi-plus-lg"></i> New Template';
        this.templateModal?.show();
      },

      saveTemplate: async function () {
        const name = this.value("templateName").trim();
        const body = this.value("templateBody").trim();
        const type = this.value("templateType") || this.cfg.channel;
        const id = this.value("templateEditId");
        if (!name || !body) { this.notify("Template name and body are required", "warning"); return; }
        const payload = { name, template_type: type, template_body: body };
        const btn = document.getElementById("saveTemplateBtn");
        if (btn) btn.disabled = true;
        try {
          if (id) await window.API.communications.updateTemplate(id, payload);
          else await window.API.communications.createTemplate(payload);
          this.templateModal?.hide();
          this.notify(id ? "Template updated" : "Template created", "success");
          await this.loadTemplates();
        } catch (e) {
          this.notify(e.message || "Failed to save template", "error");
        } finally {
          if (btn) btn.disabled = false;
        }
      },

      deleteTemplate: async function (templateId) {
        if (!(await window.confirmAction("Confirm", "Delete this template?"))) return;
        try {
          await window.API.communications.deleteTemplate(templateId);
          this.notify("Template deleted", "success");
          await this.loadTemplates();
        } catch (e) {
          this.notify(e.message || "Failed to delete template", "error");
        }
      },

      loadOptOuts: async function () {
        const listEl = document.getElementById("optOutsList");
        if (!listEl) return;
        listEl.innerHTML = '<div class="kw-rail-empty">Loading…</div>';
        try {
          const res = await window.API.communications.getOptOuts();
          this.optOuts = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);
          this.optOutsLoaded = true;
        } catch (e) {
          this.optOuts = [];
          listEl.innerHTML = '<div class="kw-rail-empty kw-danger">Unable to load opt-outs.</div>';
          return;
        }
        this.renderOptOuts();
      },

      renderOptOuts: function () {
        const listEl = document.getElementById("optOutsList");
        if (!listEl) return;
        this.setText("optOutCount", this.optOuts.length);
        if (!this.optOuts.length) {
          listEl.innerHTML = '<div class="kw-rail-empty">No opted-out contacts.</div>';
          return;
        }
        const self = this;
        listEl.innerHTML = this.optOuts.slice(0, 60).map(function (o) {
          const label = o.name || o.phone || o.email || "Contact";
          const detail = o.phone || o.email || "";
          return '<div class="kw-rail-item" title="' + self.esc(o.notes || "") + '">' +
            '<span class="kw-rail-item-main"><i class="bi bi-person-dash"></i>' +
            '<span class="kw-rail-item-label">' + self.esc(label) + "</span></span>" +
            '<span class="kw-rail-item-meta">' + self.esc(detail) + "</span>" +
            "</div>";
        }).join("") + (this.optOuts.length > 60 ? '<div class="kw-rail-empty">Showing 60 of ' + this.optOuts.length + "</div>" : "");
      },

      loadGateway: async function (force) {
        if (this.gatewayLoaded && !force) return;
        if (!window.API.messaging?.getGateway) return;
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
          const dl = document.getElementById("gatewayInfo");
          if (dl) dl.innerHTML = rows.map(([k, v]) => "<dt>" + this.esc(k) + "</dt><dd>" + this.esc(v) + "</dd>").join("");
          this.gatewayLoaded = true;
        } catch (e) {
          const dl = document.getElementById("gatewayInfo");
          if (dl) dl.innerHTML = '<dd class="text-danger">Unable to load provider info.</dd>';
        }
        this.loadSmsTopup();
      },

      loadSmsTopup: async function () {
        if (this.cfg.channel !== "sms") return;
        const set = (id, value, fallback) => {
          const el = document.getElementById(id);
          if (el) el.textContent = value || fallback || "—";
        };
        try {
          const res = await window.API.system.getCommunicationConfigs();
          const data = res?.data && typeof res.data === "object" ? res.data : (res || {});
          const fields = Array.isArray(data.sms) ? data.sms : [];
          const byKey = {};
          fields.forEach((f) => { byKey[f.key] = f.value; });
          set("smsTopupPaybill", byKey["comms_sms_topup_paybill"]);
          set("smsTopupAccount", byKey["comms_sms_topup_account"]);
          set("smsTopupNotes", byKey["comms_sms_topup_notes"], "Units are credited automatically after confirmation.");
        } catch (e) {
          set("smsTopupPaybill", null);
          set("smsTopupAccount", null);
        }
      },

      loadProfile: async function () {
        const panel = document.getElementById("profilePanel");
        try {
          const res = await window.API.messaging.getProfile();
          const profile = res?.data?.profile ?? res?.profile ?? res?.data ?? res;
          if (panel) panel.innerHTML = '<pre class="small bg-light border rounded p-2 mb-0" style="max-height:200px;overflow:auto;">' +
            this.esc(typeof profile === "object" ? JSON.stringify(profile, null, 2) : String(profile)) + "</pre>";
        } catch (e) {
          this.notify("Unable to load profile", "warning");
        }
      },

      loadRemoteMessages: async function (force) {
        const listEl = document.getElementById("remoteMessagesList");
        if (!listEl) return;
        try {
          const res = await window.API.messaging.getRemoteMessages();
          const list = this.unwrapList(res, ["messages", "data"]);
          this.remoteLoaded = true;
          if (!list.length) {
            listEl.innerHTML = '<div class="kw-rail-empty">No provider messages.</div>';
            return;
          }
          const self = this;
          listEl.innerHTML = list.slice(0, 30).map(function (m) {
            return '<div class="kw-log-row">' +
              "<span>" + self.esc(m.recipient || m.to || m.uid || m.id || "—") + "</span>" +
              '<span class="' + badgeClass(m.status) + '">' + self.esc(m.status || "—") + "</span>" +
              "</div>";
          }).join("");
        } catch (e) {
          listEl.innerHTML = '<div class="kw-rail-empty kw-danger">Unable to load provider log.</div>';
        }
      },

      checkBalance: async function () {
        try {
          const response = await window.API.communications.getSmsBalance();
          const payload = response?.data && typeof response.data === "object" ? response.data : response;
          const available = payload?.available !== false;
          const balance = Number(payload?.balance ?? 0);
          const display = available ? balance : "—";
          this.setText("smsBalance", display);
          this.setText("railBalance", display);
          const msg = available ? "Balance read from the configured provider." : (payload?.message || "SMS balance unavailable.");
          this.setText("gatewayBalanceMsg", msg);
        } catch (e) {
          this.setText("smsBalance", "—");
          this.setText("railBalance", "—");
          this.setText("gatewayBalanceMsg", "Unable to fetch SMS balance.");
        }
      },

      toggleChannelOptions: function (channel) {
        const mms = document.getElementById("mediaUrlDiv");
        const voice = document.getElementById("voiceOptionsDiv");
        const hashed = document.getElementById("hashedOptionsDiv");
        if (mms) mms.style.display = channel === "mms" ? "" : "none";
        if (voice) voice.style.display = channel === "voice" ? "" : "none";
        if (hashed) hashed.style.display = channel === "hashed" ? "" : "none";
      },

      toggleRecipientSection: function (val) {
        const show = (id, on) => { const el = document.getElementById(id); if (el) el.style.display = on ? "" : "none"; };
        show("recipientSelector", false);
        show("groupSelector", false);
        show("customNumbersDiv", false);
        show("recipientPickerDiv", false);
        const sel = document.getElementById("specificRecipients");
        if (sel) sel.innerHTML = "";
        if (val === "custom_selection") {
          show("recipientPickerDiv", true);
          const mount = document.getElementById("recipientPickerMount");
          if (mount && !this.recipientPicker && window.RecipientPicker) {
            this.recipientPicker = window.RecipientPicker.create(mount, {
              mode: "multi",
              placeholder: "Search learner, parent, staff, class or vendor…",
            });
          }
        } else if (SELECTED_RECIPIENT_TYPES.includes(val)) {
          show("recipientSelector", true);
          this.populateRecipientList(val);
        } else if (val === "contact_group") {
          show("groupSelector", true);
        } else if (val === "custom_numbers") {
          show("customNumbersDiv", true);
        }
      },

      populateRecipientList: function (type) {
        const sel = document.getElementById("specificRecipients");
        if (!sel) return;
        sel.innerHTML = "";
        const map = {
          selected_students: this.audiences.students,
          selected_class: this.audiences.classes,
          student_type: this.audiences.student_types,
          school_level: this.audiences.school_levels,
          selected_parents: this.audiences.parents,
          selected_staff: this.audiences.staff,
          selected_vendors: this.audiences.vendors,
        };
        const items = map[type] || [];
        items.forEach((c) => {
          const opt = document.createElement("option");
          opt.value = c.id;
          opt.textContent = (c.name || c.code || "") + (c.phone ? " (" + c.phone + ")" : "") + (c.school_level ? " · " + c.school_level : "");
          sel.appendChild(opt);
        });
      },

      filterContactList: function (query) {
        const sel = document.getElementById("specificRecipients");
        if (!sel) return;
        const q = String(query || "").toLowerCase();
        Array.from(sel.options).forEach((opt) => {
          opt.style.display = opt.textContent.toLowerCase().includes(q) ? "" : "none";
        });
      },

      suggestTemplate: function (msgType) {
        if (!msgType) return;
        const matching = this.templates.find((t) => t.category === msgType || String(t.name || "").toLowerCase().includes(msgType));
        if (matching) {
          const sel = document.getElementById("templateSelect");
          if (sel) sel.value = matching.id;
          this.applyTemplate(matching.id);
        }
      },

      applyTemplate: function (templateId) {
        if (!templateId) return;
        const tmpl = this.templates.find((t) => String(t.id) === String(templateId));
        const body = document.getElementById("messageBody");
        if (tmpl && tmpl.template_body && body) {
          body.value = tmpl.template_body;
          this.updateCharMetrics();
        }
      },

      updateCharMetrics: function () {
        const msg = this.value("messageBody");
        const length = msg.length;
        const segments = length === 0 ? 0 : Math.ceil(length / 160);
        const costPerSms = 0.8;
        this.setText("charCount", length);
        this.setText("smsCount", segments);
        this.setText("estimatedCost", (segments * costPerSms).toFixed(2));
        const metrics = document.getElementById("smsMetrics");
        const channel = this.value("messageChannel") || this.cfg.channel;
        if (metrics) metrics.style.display = channel === "sms" ? "" : "none";
      },

      openCompose: function () {
        const form = document.getElementById("composeForm");
        if (form) form.reset();
        ["recipientSelector", "groupSelector", "customNumbersDiv", "recipientPickerDiv", "reminderOptions"].forEach((id) => {
          const el = document.getElementById(id);
          if (el) el.style.display = "none";
        });
        const sel = document.getElementById("specificRecipients");
        if (sel) sel.innerHTML = "";
        const sig = document.getElementById("senderSignature");
        if (sig && !sig.value) sig.value = "Kingsway Preparatory School";
        this.toggleChannelOptions(this.value("messageChannel") || this.cfg.channel);
        this.updateCharMetrics();
        if (this.composeOffcanvas) this.composeOffcanvas.show();
        else document.getElementById("composeOffcanvas")?.classList.add("show");
      },

      collectFormData: function (channelOverride) {
        const channel = channelOverride || this.cfg.channel;
        const recipientType = this.value("recipientType");
        let recipients = [];
        let payloadRecipientType = recipientType;
        if (recipientType === "custom_numbers") {
          recipients = this.value("customNumbers").split(",").map((s) => s.trim()).filter(Boolean);
        } else if (recipientType === "contact_group") {
          recipients = [this.value("groupSelect")];
        } else if (recipientType === "custom_selection") {
          recipients = this.recipientPicker ? this.recipientPicker.getTokens() : [];
          payloadRecipientType = "mixed";
        } else if (SELECTED_RECIPIENT_TYPES.includes(recipientType)) {
          recipients = Array.from(document.getElementById("specificRecipients").selectedOptions).map((o) => o.value);
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
        if (document.getElementById("enableReminder")?.checked) {
          const val = parseInt(this.value("reminderAfter"), 10) || 24;
          const unit = this.value("reminderUnit");
          const ms = unit === "days" ? val * 86400000 : val * 3600000;
          reminderAt = scheduleTime
            ? new Date(new Date(scheduleTime).getTime() + ms).toISOString().slice(0, 19).replace("T", " ")
            : new Date(Date.now() + ms).toISOString().slice(0, 19).replace("T", " ");
        }
        const payload = {
          recipients,
          message: fullMessage,
          type: channel,
          subject: msgType ? msgType.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase()) : (channel === "whatsapp" ? "WhatsApp Message" : "SMS Broadcast"),
          status: scheduleTime ? "scheduled" : "sent",
          scheduled_at: scheduleTime || null,
          reminder_at: reminderAt,
          sender_signature: signature,
          recipient_type: payloadRecipientType,
          target_ids: payloadRecipientType === "mixed" ? [] : recipients,
        };
        if (channel === "whatsapp") payload.category = msgType;
        return payload;
      },

      submitMessage: async function () {
        const msg = this.value("messageBody").trim();
        const channel = this.value("messageChannel") || this.cfg.channel;
        if (!msg && channel !== "mms") { this.notify("Message is required", "warning"); return; }
        if (!this.value("recipientType")) { this.notify("Select recipients", "warning"); return; }
        const sendBtn = document.getElementById("sendBtn");
        if (sendBtn) {
          sendBtn.disabled = true;
          sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending…';
        }
        try {
          if (channel !== "sms" && channel !== "whatsapp") {
            await this.sendViaChannel(channel, msg);
            if (this.composeOffcanvas) this.composeOffcanvas.hide();
          } else {
            const data = this.collectFormData(channel);
            const created = await window.API.communications.createCommunication(data);
            const commId = created?.id || created?.communication_id || created?.data?.id;
            if (channel === "whatsapp") await this.uploadMedia(commId);
            const result = data.status === "sent" && commId
              ? await window.API.communications.dispatchCommunication(commId)
              : created;
            const label = channel === "whatsapp" ? "WhatsApp" : "SMS";
            if (result?.status === "failed") this.notify(label + " delivery failed", "error");
            else this.notify(data.status === "scheduled" ? label + " scheduled" : label + " sent or queued", "success");
            if (this.composeOffcanvas) this.composeOffcanvas.hide();
            await this.loadData();
          }
        } catch (e) {
          console.error("Send error:", e);
          this.notify(e.message || "Failed to send", "error");
        } finally {
          if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = this.cfg.sendLabel;
          }
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
          payload.recipients = this.value("customNumbers").split(",").map((s) => s.trim()).filter(Boolean);
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
        } else if (SELECTED_RECIPIENT_TYPES.includes(recipientType) || recipientType === "custom_selection") {
          throw new Error("Selected recipients are only supported for SMS/WhatsApp. Choose a contact group or an all-<audience> option for this channel.");
        }
        const result = await window.API.messaging.send(payload);
        this.notify(result?.message || (result?.status === "scheduled" ? "Message scheduled" : "Message sent or queued"), "success");
      },

      exportConsole: function () {
        if (window.AuthContext?.canExport && !AuthContext.canExport("communications")) return;
        const rows = [["Date/Time", "Type", "Recipients", "Message", "Status", "Channel"]];
        (this.filtered || []).forEach((item) => {
          rows.push([
            this.formatDate(item.created_at || item.sent_at || item.scheduled_at),
            item.subject || item.category || this.cfg.title + " Broadcast",
            item.recipient_summary || item.recipient_type || (item.recipient_count ? item.recipient_count + " recipients" : ""),
            String(item.content || item.message || item.body || "").replace(/\s+/g, " ").slice(0, 500),
            item.status || "",
            item.channel || item.message_channel || this.cfg.channel,
          ]);
        });
        const csv = rows.map((row) => row.map((v) => '"' + String(v ?? "").replace(/"/g, '""') + '"').join(",")).join("\r\n");
        if (window.KingswayFileLifecycle?.exportText) {
          window.KingswayFileLifecycle.exportText(csv, this.cfg.channel + "_messages_" + new Date().toISOString().slice(0, 10) + ".csv", "text/csv;charset=utf-8;");
        } else {
          this.notify("Export unavailable", "error");
        }
      },

      printConsole: function () {
        if (window.AuthContext?.canPrint && !AuthContext.canPrint("communications")) return;
        window.PrintManager?.printPage({ title: this.cfg.title + " Messages", orientation: "landscape" }) ?? window.print();
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
        const d = new Date(String(v).replace(" ", "T"));
        return isNaN(d.getTime()) ? v : d.toLocaleString("en-KE", { month: "short", day: "2-digit", hour: "2-digit", minute: "2-digit" });
      },

      esc: function (v) { return String(v || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); },
      value: function (id) { const el = document.getElementById(id); return el ? el.value || "" : ""; },
      text: function (id) { const el = document.getElementById(id); return el ? el.textContent || "" : ""; },
      setText: function (id, v) { const el = document.getElementById(id); if (el) el.textContent = String(v); },
      on: function (id, ev, fn) { const el = document.getElementById(id); if (el) el.addEventListener(ev, fn); },
      notify: function (msg, type) { if (typeof showNotification === "function") showNotification(msg, type || "info"); else console.log(type + ": " + msg); },
    };

    Console.init();
    window.__kwMessagingConsole = Console;
    return Console;
  }

  window.MessagingConsole = { create };
})();
