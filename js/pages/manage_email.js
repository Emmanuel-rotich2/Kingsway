(function () {
  "use strict";

  const ManageEmailController = {
    mailboxes: [],
    activeMailbox: null,
    folders: [],
    activeFolder: "INBOX",
    draftFolderName: null,
    trashFolderName: null,
    view: "messages", // "messages" | "outbox"
    messages: [],
    messagePage: 1,
    messagePages: 1,
    messageTotal: 0,
    messageLimit: 25,
    filters: { q: "", unread: false, flagged: false },
    outbox: [],
    outboxPage: 1,
    outboxPages: 1,
    outboxTotal: 0,
    outboxStatus: "",
    selectedUid: null,
    selectedMessage: null,
    composeContext: null,
    objectUrls: [],
    searchTimer: null,
    canManageAccess: false,
    accessMailbox: null,
    accessMembers: [],
    accessSearchCache: {},
    accessSearchTimer: null,
    realtimeMailTimer: null,

    async init() {
      if (!document.getElementById("mailboxes-root")) return;
      await window.AuthContext?.ready?.();
      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }
      this.applyCapabilityGates();
      this.applyUserChip();
      this.bindEvents();
      this.bindComposeEvents();
      this.bindAccessEvents();
      this.bindRealtime();
      this.setupVariableMenu();
      await this.loadMailboxes();
    },

    applyUserChip() {
      const user = window.AuthContext?.getUser?.() || {};
      const name = user.full_name || user.username || "Kingsway Staff";
      const email = user.email || "";
      const nameEl = document.getElementById("mailAccountName");
      const emailEl = document.getElementById("mailAccountEmail");
      const avatarEl = document.getElementById("mailAccountAvatar");
      if (nameEl) nameEl.textContent = name;
      if (emailEl) emailEl.textContent = email;
      if (avatarEl) avatarEl.textContent = this.initials(name);
      this.canManageAccess = Boolean(
        window.AuthContext?.hasRole?.("School Administrator") ||
        window.AuthContext?.hasRole?.("School Admin") ||
        window.AuthContext?.hasPermission?.("*"),
      );
    },

    initials(value) {
      const parts = String(value || "?").trim().split(/\s+/).filter(Boolean);
      if (!parts.length) return "?";
      if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
      return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    },

    setupVariableMenu() {
      const menu = document.getElementById("messageVariableMenu");
      if (!menu || !window.RecipientPicker) return;
      menu.innerHTML = window.RecipientPicker.variables()
        .map((v) => `<li><button type="button" class="dropdown-item" data-var="${v.token}">${v.token} <span class="text-muted small">${v.label}</span></button></li>`)
        .join("");
      menu.querySelectorAll("[data-var]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          window.RecipientPicker.insertVariable("composeBody", this.getAttribute("data-var"));
        });
      });
    },

    toggleDirectoryPicker() {
      const wrap = document.getElementById("composeDirectoryWrap");
      if (!wrap) return;
      const show = wrap.style.display === "none";
      wrap.style.display = show ? "" : "none";
      if (show && !this.directoryPicker && window.RecipientPicker) {
        const self = this;
        this.directoryPicker = window.RecipientPicker.create("composeRecipientPicker", {
          mode: "multi",
          types: ["student", "parent", "staff", "vendor"],
          placeholder: "Browse or search learner, guardian, staff or vendor…",
          onChange: function (selection) {
            const emails = (selection || []).map((r) => r.email).filter(Boolean);
            if (emails.length) self.appendToRecipients(emails);
          },
        });
      }
    },

    appendToRecipients(emails) {
      const input = document.getElementById("composeTo");
      if (!input) return;
      const current = input.value.split(",").map((s) => s.trim()).filter(Boolean);
      emails.forEach((email) => { if (!current.includes(email)) current.push(email); });
      input.value = current.join(", ");
    },

    applyCapabilityGates() {
      const canExport = window.AuthContext?.canExport ? AuthContext.canExport("communications") : true;
      const canPrint = window.AuthContext?.canPrint ? AuthContext.canPrint("communications") : true;
      const exportBtn = document.getElementById("mailExportBtn");
      const printBtn = document.getElementById("mailPrintBtn");
      if (exportBtn && !canExport) exportBtn.style.display = "none";
      if (printBtn && !canPrint) printBtn.style.display = "none";
    },

    bindEvents() {
      const self = this;
      this.on("mailRefreshBtn", "click", () => self.refreshAll());
      this.on("mailComposeBtn", "click", () => self.openCompose("new"));
      this.on("mailExportBtn", "click", () => self.exportCurrentView());
      this.on("mailPrintBtn", "click", () => self.printCurrentView());
      this.on("mvBackBtn", "click", () => self.hideMessage());
      this.on("mvReplyBtn", "click", () => self.openCompose("reply"));
      this.on("mvReplyAllBtn", "click", () => self.openCompose("reply_all"));
      this.on("mvForwardBtn", "click", () => self.openCompose("forward"));
      this.on("mvReadBtn", "click", () => self.toggleRead());
      this.on("mvFlagBtn", "click", () => self.toggleFlagged());
      this.on("mvMoveBtn", "click", () => self.moveSelected());
      this.on("mvDeleteBtn", "click", () => self.deleteSelected());
      this.on("msgPrevPage", "click", () => self.goPage(-1));
      this.on("msgNextPage", "click", () => self.goPage(1));
      this.on("outboxStatus", "change", (e) => {
        self.outboxStatus = e.target.value;
        self.outboxPage = 1;
        self.loadOutbox();
      });

      this.on("msgUnreadToggle", "click", () => {
        self.filters.unread = !self.filters.unread;
        document.getElementById("msgUnreadToggle").classList.toggle("active", self.filters.unread);
        self.messagePage = 1;
        self.loadMessages();
      });
      this.on("msgFlaggedToggle", "click", () => {
        self.filters.flagged = !self.filters.flagged;
        document.getElementById("msgFlaggedToggle").classList.toggle("active", self.filters.flagged);
        self.messagePage = 1;
        self.loadMessages();
      });
      this.on("msgSearch", "input", (e) => {
        clearTimeout(self.searchTimer);
        self.searchTimer = setTimeout(() => {
          self.filters.q = e.target.value.trim();
          self.messagePage = 1;
          self.outboxPage = 1;
          if (self.view === "outbox") self.loadOutbox();
          else self.loadMessages();
        }, 400);
      });

      document.getElementById("folderList")?.addEventListener("click", (e) => {
        const btn = e.target.closest("[data-folder]");
        if (btn) self.selectFolder(btn.getAttribute("data-folder"));
      });
      document.getElementById("mailboxTabs")?.addEventListener("click", (e) => {
        const gear = e.target.closest("[data-mailbox-gear]");
        if (gear) {
          e.stopPropagation();
          const mb = self.mailboxes.find((m) => String(m.id) === String(gear.getAttribute("data-mailbox-gear")));
          if (mb) self.openAccessModal(mb);
          return;
        }
        const btn = e.target.closest("[data-mailbox-id]");
        if (btn) {
          self.selectMailbox(btn.getAttribute("data-mailbox-id"));
          document.getElementById("mailboxes-root")?.classList.remove("sidebar-open");
        }
      });
      this.on("mailSidebarToggle", "click", () => {
        document.getElementById("mailboxes-root")?.classList.toggle("sidebar-open");
      });
      this.on("mailAccessBtn", "click", () => {
        const target = this.activeMailbox || this.mailboxes[0];
        if (target) self.openAccessModal(target);
      });
      document.getElementById("messageList")?.addEventListener("click", (e) => {
        const actionBtn = e.target.closest("[data-outbox-action]");
        if (actionBtn) {
          const id = actionBtn.getAttribute("data-outbox-id");
          const action = actionBtn.getAttribute("data-outbox-action");
          e.stopPropagation();
          if (action === "retry") self.retryOutbox(id);
          else if (action === "delete") self.deleteOutboxRow(id);
          return;
        }
        const row = e.target.closest("[data-msg-uid]");
        if (row && self.view === "messages") self.openMessage(row.getAttribute("data-msg-uid"));
      });
    },

    /**
     * React to realtime mailbox changes (a message sent or retried — by this
     * tab, a sibling tab, or the shared mailbox's other members). The Node SSE
     * stream and the static-buffer fallback both surface these through the
     * single dispatcher, so this handler never needs to know the transport.
     * Refreshes are conservative: they never clobber an open message or an
     * in-progress compose.
     */
    bindRealtime() {
      window.addEventListener("kingsway:data-mutated", (event) => {
        const targets = Array.isArray(event?.detail?.targets) ? event.detail.targets : [];
        if (!targets.includes("communications")) return;
        if (this.realtimeMailTimer) window.clearTimeout(this.realtimeMailTimer);
        this.realtimeMailTimer = window.setTimeout(() => {
          this.realtimeMailTimer = null;
          this.handleRealtimeMailChange();
        }, 1000);
      });
    },

    handleRealtimeMailChange() {
      if (!this.activeMailbox) return;
      if (this.view === "outbox") {
        this.loadOutbox();
        return;
      }
      if (this.selectedUid) return;
      const modal = document.getElementById("composeModal");
      if (modal && modal.classList.contains("show")) return;
      this.loadFolders();
      this.loadMessages();
    },

    bindComposeEvents() {
      const self = this;
      this.on("composeSendBtn", "click", () => self.sendComposed());
      this.on("composeSaveDraftBtn", "click", () => self.saveDraft());
      this.on("composeCcBccToggle", "click", () => {
        const wrap = document.getElementById("ccBccWrap");
        const hidden = wrap.style.display === "none";
        wrap.style.display = hidden ? "" : "none";
        document.getElementById("composeCcBccToggle").textContent = hidden ? "Hide Cc / Bcc" : "Show Cc / Bcc";
      });
      this.on("composeAttachments", "change", () => self.renderAttachmentChips());
      this.on("composeDirectoryToggle", "click", () => self.toggleDirectoryPicker());
      document.getElementById("composeModal")?.addEventListener("hidden.bs.modal", () => {
        self.composeContext = null;
        document.getElementById("composeStatus").textContent = "";
      });
    },

    bindAccessEvents() {
      const self = this;
      this.on("accessUserSearch", "input", (e) => {
        clearTimeout(self.accessSearchTimer);
        const q = e.target.value.trim();
        self.accessSearchTimer = setTimeout(() => self.searchAccessUsers(q), 300);
      });
      document.getElementById("accessUserResults")?.addEventListener("click", (e) => {
        const btn = e.target.closest("[data-add-user]");
        if (btn) self.addAccessMember(Number(btn.getAttribute("data-add-user")));
      });
      document.getElementById("accessMembersList")?.addEventListener("click", (e) => {
        const btn = e.target.closest("[data-remove]");
        if (btn) {
          self.accessMembers.splice(Number(btn.getAttribute("data-remove")), 1);
          self.renderAccessMembers();
        }
      });
      this.on("accessSaveBtn", "click", () => self.saveAccess());
      document.getElementById("mailboxAccessModal")?.addEventListener("hidden.bs.modal", () => {
        self.accessMailbox = null;
        document.getElementById("accessUserResults").innerHTML = "";
        document.getElementById("accessUserSearch").value = "";
        document.getElementById("accessStatus").textContent = "";
      });
    },

    /* ---- Mailbox access management (School Administrator) ---- */

    async openAccessModal(mailbox) {
      if (!this.canManageAccess) {
        showNotification("Only a School Administrator can manage mailbox access.", "warning");
        return;
      }
      if (!mailbox) return;
      this.accessMailbox = mailbox;
      this.accessMembers = [];
      this.accessSearchCache = {};
      document.getElementById("accessMailboxName").textContent =
        `${mailbox.label || "Mailbox"} <${mailbox.email_address || ""}>`;
      document.getElementById("accessUserSearch").value = "";
      document.getElementById("accessUserResults").innerHTML = "";
      document.getElementById("accessMembersList").innerHTML =
        '<div class="text-muted small py-3">Loading members…</div>';
      document.getElementById("accessMembersEmpty").style.display = "none";
      document.getElementById("accessStatus").textContent = "";
      bootstrap.Modal.getOrCreateInstance(document.getElementById("mailboxAccessModal")).show();
      await this.loadAccessMembers(mailbox.id);
    },

    async loadAccessMembers(profileId) {
      try {
        const res = await window.API.communications.getMailboxMembers(profileId);
        this.accessMembers = (res?.members || []).map((m) => ({
          user_id: Number(m.user_id),
          full_name: m.full_name || m.username || ("User #" + m.user_id),
          username: m.username || "",
          email: m.email || "",
          can_read: !!m.can_read,
          can_reply: !!m.can_reply,
          can_compose: !!m.can_compose,
          is_owner: !!m.is_owner,
        }));
      } catch (e) {
        document.getElementById("accessMembersList").innerHTML =
          `<div class="text-danger small py-3">${this.esc(e?.message || "Members could not be loaded.")}</div>`;
        return;
      }
      this.renderAccessMembers();
    },

    renderAccessMembers() {
      const list = document.getElementById("accessMembersList");
      const empty = document.getElementById("accessMembersEmpty");
      if (!this.accessMembers.length) {
        list.innerHTML = "";
        empty.style.display = "";
        return;
      }
      empty.style.display = "none";
      list.innerHTML = this.accessMembers.map((m, i) => `
        <div class="kw-access-member" data-index="${i}">
          <span class="kw-avatar">${this.esc(this.initials(m.full_name))}</span>
          <div class="kw-access-name">
            <strong>${this.esc(m.full_name)}${m.is_owner ? ' <span class="text-muted small">(owner)</span>' : ""}</strong>
            <span>${this.esc(m.email || m.username || "")}</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <div class="form-check form-check-inline m-0">
              <input class="form-check-input" type="checkbox" data-flag="can_read" data-index="${i}" ${m.can_read ? "checked" : ""} id="accRead${i}">
              <label class="form-check-label small" for="accRead${i}">Read</label>
            </div>
            <div class="form-check form-check-inline m-0">
              <input class="form-check-input" type="checkbox" data-flag="can_reply" data-index="${i}" ${m.can_reply ? "checked" : ""} id="accReply${i}">
              <label class="form-check-label small" for="accReply${i}">Reply</label>
            </div>
            <div class="form-check form-check-inline m-0">
              <input class="form-check-input" type="checkbox" data-flag="can_compose" data-index="${i}" ${m.can_compose ? "checked" : ""} id="accCompose${i}">
              <label class="form-check-label small" for="accCompose${i}">Compose</label>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm" data-remove="${i}" title="Remove access"><i class="fas fa-user-minus"></i></button>
          </div>
        </div>`).join("");
      list.querySelectorAll("[data-flag]").forEach((cb) => {
        cb.addEventListener("change", () => {
          const idx = Number(cb.getAttribute("data-index"));
          const flag = cb.getAttribute("data-flag");
          if (this.accessMembers[idx]) {
            this.accessMembers[idx][flag] = cb.checked;
            if (cb.checked) this.accessMembers[idx].can_read = true;
          }
        });
      });
    },

    async searchAccessUsers(q) {
      const box = document.getElementById("accessUserResults");
      if (!q || q.length < 2) { box.innerHTML = ""; this.accessSearchCache = {}; return; }
      try {
        const res = await window.API.communications.searchMessageRecipients(q);
        const users = Array.isArray(res) ? res : (res?.results || res?.recipients || res?.users || []);
        const existing = new Set(this.accessMembers.map((m) => m.user_id));
        this.accessSearchCache = {};
        const matches = users.filter((u) => {
          const id = Number(u.id ?? u.user_id);
          if (existing.has(id)) return false;
          this.accessSearchCache[id] = {
            user_id: id,
            full_name: u.full_name || u.username || ("User #" + id),
            username: u.username || "",
            email: u.email || "",
          };
          return true;
        });
        box.innerHTML = matches.length
          ? matches.map((u) => {
              const id = Number(u.id ?? u.user_id);
              return `<button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" data-add-user="${id}">
                <span class="kw-avatar" style="width:26px;height:26px;min-width:26px;font-size:.7rem;">${this.esc(this.initials(u.full_name || u.username || "?"))}</span>
                <span>${this.esc(u.full_name || u.username || ("User #" + id))}<br><small class="text-muted">${this.esc(u.email || u.username || "")}</small></span>
              </button>`;
            }).join("")
          : '<div class="text-muted small p-2">No matching staff found.</div>';
      } catch (e) {
        box.innerHTML = `<div class="text-danger small p-2">${this.esc(e?.message || "Search failed")}</div>`;
      }
    },

    addAccessMember(userId) {
      const cached = (this.accessSearchCache || {})[Number(userId)];
      if (!cached) return;
      if (this.accessMembers.some((m) => m.user_id === Number(userId))) return;
      this.accessMembers.push({
        user_id: Number(userId),
        full_name: cached.full_name,
        username: cached.username,
        email: cached.email,
        can_read: true,
        can_reply: false,
        can_compose: false,
        is_owner: false,
      });
      document.getElementById("accessUserSearch").value = "";
      document.getElementById("accessUserResults").innerHTML = "";
      this.accessSearchCache = {};
      this.renderAccessMembers();
    },

    collectAccessMembers() {
      return this.accessMembers.map((m) => ({
        user_id: m.user_id,
        can_read: !!m.can_read,
        can_reply: !!m.can_reply,
        can_compose: !!m.can_compose,
      }));
    },

    async saveAccess() {
      if (!this.accessMailbox) return;
      const btn = document.getElementById("accessSaveBtn");
      btn.disabled = true;
      document.getElementById("accessStatus").textContent = "Saving…";
      try {
        await window.API.communications.setMailboxMembers(this.accessMailbox.id, this.collectAccessMembers());
        document.getElementById("accessStatus").textContent = "";
        bootstrap.Modal.getOrCreateInstance(document.getElementById("mailboxAccessModal")).hide();
        showNotification("Mailbox access updated", "success");
        await this.loadMailboxes();
      } catch (e) {
        document.getElementById("accessStatus").textContent = e?.message || "Save failed";
        showNotification(e?.message || "Access could not be saved", "error");
      } finally {
        btn.disabled = false;
      }
    },

    /* ---- Mailboxes ---- */

    async loadMailboxes() {
      const root = document.getElementById("mailboxTabs");
      root.innerHTML = '<div class="kw-state" style="padding:16px;"><div class="spinner-border spinner-border-sm"></div></div>';
      try {
        const res = await window.API.communications.getMailboxes();
        this.mailboxes = res?.mailboxes ?? [];
      } catch (e) {
        console.error("[manage_email] mailboxes failed:", e);
        this.mailboxes = [];
      }
      const accessBtn = document.getElementById("mailAccessBtn");
      if (accessBtn) accessBtn.style.display = this.canManageAccess ? "" : "none";
      if (!this.mailboxes.length) {
        root.innerHTML = '<div class="text-muted small px-3 py-2">No mailboxes are assigned to your role. Contact the School Administrator.</div>';
        document.getElementById("folderList").innerHTML = "";
        document.getElementById("mailComposeBtn").disabled = true;
        return;
      }
      if (!this.mailboxes.some((m) => m.can_send)) {
        document.getElementById("mailComposeBtn").disabled = true;
        document.getElementById("mailComposeBtn").title = "You do not have send access to any mailbox";
      }
      this.renderMailboxTabs();
      const preferred = this.activeMailbox
        ? this.mailboxes.find((m) => String(m.id) === String(this.activeMailbox.id))
        : null;
      this.selectMailbox((preferred || this.mailboxes[0]).id);
    },

    renderMailboxTabs() {
      const root = document.getElementById("mailboxTabs");
      root.innerHTML = this.mailboxes.map((m) => {
        const active = String(this.activeMailbox?.id) === String(m.id) ? " active" : "";
        const isActiveAccount = m.is_active === 0 || m.is_active === false;
        const label = this.esc(m.label || m.display_name || "Mailbox");
        const email = this.esc(m.email_address || "");
        const setup = isActiveAccount ? ' <span class="kw-badge-setup">Setup</span>' : "";
        const owner = m.is_owner ? ' <i class="fas fa-user" title="Owner" style="font-size:.7rem;color:#5f6368;"></i>' : "";
        const gear = this.canManageAccess
          ? `<button type="button" class="kw-account-gear" data-mailbox-gear="${this.esc(m.id)}" title="Manage access"><i class="fas fa-user-shield"></i></button>`
          : (m.can_send === false ? '<i class="fas fa-lock text-muted" title="Read-only" style="font-size:.72rem;"></i>' : "");
        return `<div class="kw-nav-item kw-account${active}" role="tab" tabindex="0" data-mailbox-id="${this.esc(m.id)}" title="${email}">
          <span class="kw-avatar">${this.esc(this.initials(m.label || m.display_name || "Mailbox"))}</span>
          <span class="kw-account-meta">
            <strong>${label}${setup}${owner}</strong>
            <span>${email}</span>
          </span>
          ${gear}
        </div>`;
      }).join("");
    },

    selectMailbox(mailboxId) {
      const mb = this.mailboxes.find((m) => String(m.id) === String(mailboxId));
      if (!mb) return;
      this.activeMailbox = mb;
      this.activeFolder = "INBOX";
      this.view = "messages";
      this.messagePage = 1;
      this.outboxPage = 1;
      this.filters = { q: "", unread: false, flagged: false };
      const searchInput = document.getElementById("msgSearch");
      if (searchInput) searchInput.value = "";
      document.getElementById("msgUnreadToggle")?.classList.remove("active");
      document.getElementById("msgFlaggedToggle")?.classList.remove("active");
      this.hideMessage();
      this.renderMailboxTabs();
      this.updateHeader();
      if (mb.is_active === 0 || mb.is_active === false) {
        this.renderMailboxUnavailable(mb);
        return;
      }
      this.loadFolders();
    },

    renderMailboxUnavailable(mb) {
      const label = String(mb.email_address || mb.label || "This mailbox");
      document.getElementById("folderList").innerHTML =
        `<div class="text-muted small px-3 py-2">No folders to show until this account is configured.</div>`;
      document.getElementById("listToolbar").style.display = "none";
      document.getElementById("loadingMessages").style.display = "none";
      document.getElementById("msgPagination").style.display = "none";
      document.getElementById("messageList").innerHTML = "";
      const notice = document.getElementById("emptyFolderNotice");
      const text = document.getElementById("emptyFolderText");
      const canSee = this.canManageAccess;
      text.innerHTML = canSee
        ? `<strong>${this.esc(label)}</strong> is registered but has no password yet.<br>
           A System Administrator must finish its setup in <em>Email Config</em> before it can be opened.`
        : `The mailbox <strong>${this.esc(label)}</strong> is not available yet. Please contact the School Administrator.`;
      notice.style.display = "";
    },

    updateHeader() {
      const mb = this.activeMailbox;
      const title = document.getElementById("mailFolderTitle");
      const crumb = document.getElementById("folderBreadcrumbLabel");
      const isOutbox = this.view === "outbox";
      const folder = isOutbox ? "Outbox" : this.activeFolder;
      if (title) title.textContent = this.folderDisplayName(folder);
      if (crumb && mb) crumb.textContent = isOutbox
        ? `Outgoing — ${mb.label || ""} <${mb.email_address || ""}>`
        : `${mb.label || ""} <${mb.email_address || ""}>`;
    },

    folderDisplayName(name) {
      const n = String(name || "").toLowerCase();
      if (n === "inbox") return "Inbox";
      return String(name || "Inbox");
    },

    /* ---- Folders ---- */

    async loadFolders() {
      const list = document.getElementById("folderList");
      list.innerHTML = '<div class="kw-state" style="padding:16px;"><div class="spinner-border spinner-border-sm"></div></div>';
      this.hideMessage();
      let folders = [];
      try {
        const res = await window.API.communications.getMailboxFolders(this.activeMailbox.id);
        folders = res?.folders ?? [];
      } catch (e) {
        console.error("[manage_email] folders failed:", e);
        list.innerHTML = `<div class="text-danger small px-3 py-2">${this.esc(e?.message || "Unable to connect to mailbox")}</div>`;
        return;
      }
      this.folders = folders;
      this.draftFolderName = this.findSpecialFolder(/draft/i);
      this.trashFolderName = this.findSpecialFolder(/trash|deleted/i);
      this.renderFolders();
      if (this.view === "messages") {
        const exists = this.folders.some((f) => f.name === this.activeFolder);
        if (!exists) this.activeFolder = this.folders[0]?.name || "INBOX";
        this.loadMessages();
      } else {
        this.loadOutbox();
      }
    },

    findSpecialFolder(pattern) {
      const match = this.folders.find((f) => pattern.test(String(f.name || "")));
      return match ? match.name : null;
    },

    folderIcon(name) {
      const n = String(name || "").toLowerCase();
      if (n === "inbox") return "fa-inbox";
      if (/draft/.test(n)) return "fa-file-alt";
      if (/sent/.test(n)) return "fa-paper-plane";
      if (/trash|deleted/.test(n)) return "fa-trash";
      if (/junk|spam/.test(n)) return "fa-exclamation-circle";
      if (/archive/.test(n)) return "fa-archive";
      return "fa-folder";
    },

    renderFolders() {
      const list = document.getElementById("folderList");
      const order = ["INBOX", "Drafts", "Sent", "Junk", "Trash"];
      const sorted = [...this.folders].sort((a, b) => {
        const ia = order.indexOf(String(a.name));
        const ib = order.indexOf(String(b.name));
        if (ia !== -1 || ib !== -1) return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
        return String(a.name).localeCompare(String(b.name));
      });
      const items = sorted.map((f) => {
        const isActive = this.view === "messages" && f.name === this.activeFolder ? " active" : "";
        const unread = Number(f.unread || 0);
        const total = Number(f.total || 0);
        const count = unread > 0
          ? `<span class="kw-count unread">${unread}</span>`
          : (total > 0 ? `<span class="kw-count">${total}</span>` : "");
        const label = f.name === "INBOX" ? "Inbox" : f.name;
        return `<button type="button" class="kw-nav-item${isActive}" data-folder="${this.esc(f.name)}">
          <i class="fas ${this.folderIcon(f.name)}"></i>
          <span class="text-truncate flex-grow-1">${this.esc(f.name === "INBOX" ? "Inbox" : f.name)}</span>
          ${count}
        </button>`;
      }).join("");
      const outboxActive = this.view === "outbox" ? " active" : "";
      list.innerHTML = items + `
        <button type="button" class="kw-nav-item${outboxActive}" data-folder="__outbox__">
          <i class="fas fa-clock"></i>
          <span class="text-truncate flex-grow-1">Outbox</span>
          ${Number(this.outboxTotal || 0) > 0 ? `<span class="kw-count">${Number(this.outboxTotal)}</span>` : '<span class="kw-count">local</span>'}
        </button>`;
    },

    selectFolder(folderName) {
      this.selectedUid = null;
      this.hideMessage();
      if (folderName === "__outbox__") {
        this.view = "outbox";
        this.outboxPage = 1;
        document.getElementById("outboxStatusWrap").style.display = "";
        document.getElementById("msgUnreadToggle").style.display = "none";
        document.getElementById("msgFlaggedToggle").style.display = "none";
        this.renderFolders();
        this.updateHeader();
        this.loadOutbox();
        return;
      }
      this.view = "messages";
      this.activeFolder = folderName;
      this.messagePage = 1;
      document.getElementById("outboxStatusWrap").style.display = "none";
      document.getElementById("msgUnreadToggle").style.display = "";
      document.getElementById("msgFlaggedToggle").style.display = "";
      this.renderFolders();
      this.updateHeader();
      this.loadMessages();
    },

    /* ---- Messages ---- */

    async loadMessages() {
      if (!this.activeMailbox || this.view !== "messages") return;
      const list = document.getElementById("messageList");
      const notice = document.getElementById("emptyFolderNotice");
      const pagination = document.getElementById("msgPagination");
      const loader = document.getElementById("loadingMessages");
      const toolbar = document.getElementById("listToolbar");
      this.hideMessage();
      list.innerHTML = "";
      notice.style.display = "none";
      pagination.style.display = "none";
      toolbar.style.display = "";
      loader.style.display = "";

      const params = {
        folder: this.activeFolder,
        page: this.messagePage,
        per_page: this.messageLimit,
      };
      if (this.filters.q) params.q = this.filters.q;
      if (this.filters.unread) params.unread = 1;
      if (this.filters.flagged) params.flagged = 1;

      let data = {};
      try {
        const res = await window.API.communications.getMailboxMessages(this.activeMailbox.id, params);
        data = res ?? {};
      } catch (e) {
        console.error("[manage_email] messages failed:", e);
        data = { success: false, message: e?.message || "Unable to read mailbox." };
      }
      loader.style.display = "none";

      if (data.success === false) {
        notice.querySelector("#emptyFolderText").textContent = data.message || "Folder could not be loaded.";
        notice.style.display = "";
        return;
      }
      this.messages = data.messages ?? [];
      const pg = data.pagination ?? {};
      this.messagePages = pg.pages ?? 1;
      this.messageTotal = pg.total ?? this.messages.length;
      document.getElementById("listSummary").textContent =
        `${this.messageTotal} message${this.messageTotal === 1 ? "" : "s"}`;
      if (!this.messages.length) {
        notice.querySelector("#emptyFolderText").textContent =
          this.filters.q || this.filters.unread || this.filters.flagged
            ? "No messages match the current filters."
            : "No messages in this folder.";
        notice.style.display = "";
        return;
      }
      this.renderMessages();
      this.renderPagination();
    },

    renderMessages() {
      const list = document.getElementById("messageList");
      list.innerHTML = this.messages.map((msg) => {
        const unread = msg.is_read ? "" : " unread";
        const date = this.formatDate(msg.date);
        const preview = this.esc(String(msg.preview || "").substring(0, 160));
        return `<div class="kw-msg-row${unread}" data-msg-uid="${this.esc(msg.uid)}" role="button" tabindex="0">
          <span class="kw-msg-star${msg.is_flagged ? "" : " off"}"><i class="fas fa-star"></i></span>
          <span class="kw-avatar">${this.esc(this.initials(msg.from || msg.from_email || "?"))}</span>
          <span class="kw-msg-sender">${this.esc(msg.from || msg.from_email || "Unknown")}</span>
          <span class="kw-msg-snippet">${this.esc(msg.subject || "(No Subject)")} <span class="preview">— ${preview}</span>${msg.has_attachments ? ' <i class="fas fa-paperclip text-muted"></i>' : ""}</span>
          <span class="kw-msg-date">${this.esc(date)}</span>
        </div>`;
      }).join("");
    },

    renderPagination() {
      const pagination = document.getElementById("msgPagination");
      const page = this.view === "outbox" ? this.outboxPage : this.messagePage;
      const pages = this.view === "outbox" ? this.outboxPages : this.messagePages;
      if (pages <= 1) { pagination.style.display = "none"; return; }
      pagination.style.display = "";
      document.getElementById("msgPageLabel").textContent = page + " / " + pages;
      document.getElementById("msgPaginationInfo").textContent = "Page " + page + " of " + pages;
      document.getElementById("msgPrevPage").disabled = page <= 1;
      document.getElementById("msgNextPage").disabled = page >= pages;
    },

    goPage(delta) {
      if (this.view === "outbox") {
        const next = this.outboxPage + delta;
        if (next < 1 || next > this.outboxPages) return;
        this.outboxPage = next;
        this.loadOutbox();
        return;
      }
      const next = this.messagePage + delta;
      if (next < 1 || next > this.messagePages) return;
      this.messagePage = next;
      this.loadMessages();
    },

    /* ---- Outbox ---- */

    async loadOutbox() {
      if (!this.activeMailbox) return;
      const list = document.getElementById("messageList");
      const notice = document.getElementById("emptyFolderNotice");
      const pagination = document.getElementById("msgPagination");
      const loader = document.getElementById("loadingMessages");
      document.getElementById("listToolbar").style.display = "";
      this.hideMessage();
      list.innerHTML = "";
      notice.style.display = "none";
      pagination.style.display = "none";
      loader.style.display = "";

      const params = { page: this.outboxPage, per_page: this.messageLimit };
      if (this.outboxStatus) params.status = this.outboxStatus;
      if (this.filters.q) params.q = this.filters.q;

      let data = {};
      try {
        const res = await window.API.communications.getMailboxOutbox(this.activeMailbox.id, params);
        data = res ?? {};
      } catch (e) {
        console.error("[manage_email] outbox failed:", e);
        data = { messages: [] };
      }
      loader.style.display = "none";

      this.outbox = data.messages ?? [];
      const pg = data.pagination ?? {};
      this.outboxPages = pg.pages ?? 1;
      this.outboxTotal = pg.total ?? this.outbox.length;
      document.getElementById("listSummary").textContent =
        `${this.outboxTotal} outgoing message${this.outboxTotal === 1 ? "" : "s"}`;

      if (!this.outbox.length) {
        notice.querySelector("#emptyFolderText").textContent = "No outgoing messages recorded for this mailbox yet.";
        notice.style.display = "";
        return;
      }
      this.renderOutbox();
      this.renderPagination();
    },

    renderOutbox() {
      const badges = { sent: "success", failed: "danger", queued: "secondary", sending: "info" };
      const list = document.getElementById("messageList");
      list.innerHTML = `<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Date</th><th>To</th><th>Subject</th><th>Status</th><th>Attempts</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>${this.outbox.map((row) => {
          const status = String(row.status || "queued");
          const badge = badges[status] || "secondary";
          const to = Array.isArray(row.to) ? row.to.join(", ") : "";
          const retriable = status === "failed" || status === "queued";
          const canDelete = status !== "sending";
          const error = row.error
            ? `<div class="small text-danger text-truncate" style="max-width:280px;" title="${this.esc(row.error)}">${this.esc(row.error)}</div>`
            : "";
          return `<tr>
            <td class="text-nowrap small">${this.esc(this.formatDate(row.sent_at || row.created_at))}</td>
            <td class="small" style="max-width:200px;"><div class="text-truncate" title="${this.esc(to)}">${this.esc(to)}</div></td>
            <td class="small"><div class="text-truncate" style="max-width:260px;" title="${this.esc(row.subject)}">${this.esc(row.subject || "(No Subject)")}</div>${error}</td>
            <td><span class="badge bg-${badge}">${this.esc(status)}</span></td>
            <td class="small">${Number(row.attempts || 0)}</td>
            <td class="text-end text-nowrap">
              ${retriable ? `<button class="btn btn-outline-primary btn-sm" data-outbox-action="retry" data-outbox-id="${Number(row.id)}" title="Retry send"><i class="fas fa-redo"></i></button>` : ""}
              ${canDelete ? `<button class="btn btn-outline-danger btn-sm ms-1" data-outbox-action="delete" data-outbox-id="${Number(row.id)}" title="Remove from outbox"><i class="fas fa-times"></i></button>` : ""}
            </td>
          </tr>`;
        }).join("")}</tbody>
      </table></div>`;
    },

    async retryOutbox(outboxId) {
      try {
        const res = await window.API.communications.retryEmailOutbox(outboxId);
        const warning = res?.warning;
        showNotification(warning ? `Sent — ${warning}` : "Message sent successfully.", warning ? "warning" : "success");
      } catch (e) {
        showNotification(e?.message || "Retry failed", "error");
      }
      await this.loadFolders();
    },

    async deleteOutboxRow(outboxId) {
      if (!confirm("Remove this entry from the outbox record?")) return;
      try {
        await window.API.communications.deleteEmailOutbox(outboxId);
        showNotification("Outbox entry removed", "success");
      } catch (e) {
        showNotification(e?.message || "Could not remove entry", "error");
      }
      await this.loadOutbox();
    },

    /* ---- Message viewer ---- */

    async openMessage(uid) {
      if (!this.activeMailbox) return;
      if (this.draftFolderName && this.activeFolder === this.draftFolderName) {
        await this.openDraft(uid);
        return;
      }
      this.selectedUid = uid;
      const viewer = document.getElementById("messageViewer");
      viewer.style.display = "";
      document.getElementById("messageList").style.display = "none";
      document.getElementById("msgPagination").style.display = "none";
      document.getElementById("mvSubject").textContent = "Loading...";
      document.getElementById("mvFrom").textContent = "";
      document.getElementById("mvTo").textContent = "";
      document.getElementById("mvDate").textContent = "";
      document.getElementById("mvCcRow").style.display = "none";
      document.getElementById("mvAttachments").style.display = "none";
      document.getElementById("mvAttachments").innerHTML = "";
      document.getElementById("mvBody").innerHTML =
        '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

      let msg = null;
      try {
        const res = await window.API.communications.getMailboxMessage(this.activeMailbox.id, {
          message_id: uid,
          folder: this.activeFolder,
          profile_id: this.activeMailbox.id,
        });
        msg = res?.message ?? null;
      } catch (e) {
        console.error("[manage_email] message load failed:", e);
      }
      if (!msg) {
        document.getElementById("mvSubject").textContent = "Message not found";
        document.getElementById("mvBody").innerHTML = '<div class="text-muted">The message could not be loaded. It may have been moved or deleted on the server.</div>';
        return;
      }
      this.selectedMessage = msg;
      this.renderMessage();
      if (!msg.is_read) this.markRead(uid);
    },

    renderMessage() {
      const msg = this.selectedMessage;
      if (!msg) return;
      document.getElementById("mvSubject").textContent = msg.subject || "(No Subject)";
      document.getElementById("mvFrom").textContent = this.formatAddressFirst(msg);
      document.getElementById("mvTo").textContent = this.formatAddressList(msg.to);
      const avatar = document.getElementById("mvAvatar");
      if (avatar) avatar.textContent = this.initials(msg.from || msg.from_email || "?");
      const ccRow = document.getElementById("mvCcRow");
      if (Array.isArray(msg.cc) && msg.cc.length) {
        document.getElementById("mvCc").textContent = this.formatAddressList(msg.cc);
        ccRow.style.display = "";
      } else {
        ccRow.style.display = "none";
      }
      document.getElementById("mvDate").textContent = msg.date || "—";

      const attDiv = document.getElementById("mvAttachments");
      if (Array.isArray(msg.attachments) && msg.attachments.length) {
        attDiv.innerHTML = '<strong class="small me-1">Attachments:</strong> ' +
          msg.attachments.map((a) => {
            const size = a.size ? ` (${this.formatSize(a.size)})` : "";
            return `<button type="button" class="btn btn-outline-secondary btn-sm me-1 mb-1" data-att-index="${Number(a.index)}" title="Download ${this.esc(a.name)}">
              <i class="fas fa-paperclip me-1"></i>${this.esc(a.name || "attachment")}${size}<i class="fas fa-download ms-1"></i>
            </button>`;
          }).join("");
        attDiv.style.display = "";
        attDiv.querySelectorAll("[data-att-index]").forEach((btn) => {
          btn.addEventListener("click", () => this.downloadAttachment(Number(btn.getAttribute("data-att-index"))));
        });
      } else {
        attDiv.innerHTML = "";
        attDiv.style.display = "none";
      }

      const body = document.getElementById("mvBody");
      body.innerHTML = msg.html
        ? msg.html
        : `<pre class="mb-0" style="white-space:pre-wrap;">${this.esc(msg.text || "No content")}</pre>`;
      this.resolveInlineImages();
      this.renderMoveOptions();
    },

    async resolveInlineImages() {
      const body = document.getElementById("mvBody");
      const imgs = Array.from(body.querySelectorAll('img[src*="mailboxes/messages/attachments/"]'));
      for (const img of imgs) {
        try {
          const url = new URL(img.getAttribute("src"), window.location.origin);
          const index = parseInt(url.pathname.split("/").pop(), 10);
          const blob = await window.API.communications.fetchMailAttachmentBlob({
            index,
            profile_id: url.searchParams.get("profile_id"),
            folder: url.searchParams.get("folder"),
            uid: url.searchParams.get("uid"),
          });
          const objectUrl = URL.createObjectURL(blob);
          this.objectUrls.push(objectUrl);
          img.src = objectUrl;
          img.removeAttribute("loading");
        } catch (e) {
          img.replaceWith(document.createTextNode("[inline image unavailable]"));
        }
      }
    },

    renderMoveOptions() {
      const select = document.getElementById("mvMoveSelect");
      select.innerHTML = '<option value="">Move to...</option>' +
        this.folders
          .filter((f) => f.name !== this.activeFolder)
          .map((f) => `<option value="${this.esc(f.name)}">${this.esc(f.name)}</option>`)
          .join("");
    },

    hideMessage() {
      this.revokeObjectUrls();
      document.getElementById("messageViewer").style.display = "none";
      document.getElementById("messageList").style.display = "";
      this.selectedUid = null;
      this.selectedMessage = null;
      if (this.view === "messages") this.renderPagination();
      else if (this.view === "outbox") this.renderPagination();
    },

    revokeObjectUrls() {
      this.objectUrls.forEach((u) => { try { URL.revokeObjectURL(u); } catch (e) { /* ignore */ } });
      this.objectUrls = [];
    },

    /* ---- Message actions ---- */

    async markRead(uid) {
      try {
        await window.API.communications.mailMessageAction(uid, {
          profile_id: this.activeMailbox.id,
          folder: this.activeFolder,
          action: "flags",
          add: ["Seen"],
        });
        const row = this.messages.find((m) => String(m.uid) === String(uid));
        if (row) row.is_read = true;
        const rowEl = document.querySelector(`[data-msg-uid="${uid}"]`);
        rowEl?.classList.remove("fw-semibold", "bg-light");
      } catch (e) {
        console.warn("[manage_email] mark read failed:", e);
      }
    },

    async toggleRead() {
      const msg = this.selectedMessage;
      if (!msg) return;
      const action = msg.is_read
        ? { add: [], remove: ["Seen"] }
        : { add: ["Seen"], remove: [] };
      await this.applyFlags(action);
      msg.is_read = !msg.is_read;
      const row = this.messages.find((m) => String(m.uid) === String(msg.uid));
      if (row) row.is_read = msg.is_read;
      showNotification(msg.is_read ? "Marked as read" : "Marked as unread", "success");
    },

    async toggleFlagged() {
      const msg = this.selectedMessage;
      if (!msg) return;
      const action = msg.is_flagged
        ? { add: [], remove: ["Flagged"] }
        : { add: ["Flagged"], remove: [] };
      await this.applyFlags(action);
      msg.is_flagged = !msg.is_flagged;
      const row = this.messages.find((m) => String(m.uid) === String(msg.uid));
      if (row) row.is_flagged = msg.is_flagged;
      showNotification(msg.is_flagged ? "Message flagged" : "Flag removed", "success");
    },

    async applyFlags({ add, remove }) {
      try {
        await window.API.communications.mailMessageAction(this.selectedUid, {
          profile_id: this.activeMailbox.id,
          folder: this.activeFolder,
          action: "flags",
          add,
          remove,
        });
        await this.loadFolders();
        if (this.view === "messages") await this.loadMessages();
      } catch (e) {
        showNotification(e?.message || "The message could not be updated", "error");
      }
    },

    async moveSelected() {
      const target = document.getElementById("mvMoveSelect").value;
      if (!target) { showNotification("Choose a folder to move the message to", "warning"); return; }
      try {
        await window.API.communications.mailMessageAction(this.selectedUid, {
          profile_id: this.activeMailbox.id,
          folder: this.activeFolder,
          action: "move",
          target,
        });
        showNotification(`Message moved to ${target}`, "success");
        this.hideMessage();
        await this.loadFolders();
      } catch (e) {
        showNotification(e?.message || "The message could not be moved", "error");
      }
    },

    async deleteSelected() {
      if (!this.selectedUid) return;
      const inTrash = this.trashFolderName && this.activeFolder === this.trashFolderName;
      const question = inTrash
        ? "Permanently delete this message? This cannot be undone."
        : "Move this message to Trash?";
      if (!confirm(question)) return;
      try {
        await window.API.communications.mailMessageAction(this.selectedUid, {
          profile_id: this.activeMailbox.id,
          folder: this.activeFolder,
          action: "delete",
          permanent: inTrash ? 1 : 0,
        });
        showNotification(inTrash ? "Message permanently deleted" : "Message moved to Trash", "success");
        this.hideMessage();
        await this.loadFolders();
      } catch (e) {
        showNotification(e?.message || "The message could not be deleted", "error");
      }
    },

    /* ---- Compose / drafts ---- */

    async openDraft(uid) {
      let msg = null;
      try {
        const res = await window.API.communications.getMailboxMessage(this.activeMailbox.id, {
          message_id: uid,
          folder: this.activeFolder,
          profile_id: this.activeMailbox.id,
        });
        msg = res?.message ?? null;
      } catch (e) {
        showNotification("Draft could not be loaded", "error");
        return;
      }
      if (!msg) { showNotification("Draft could not be loaded", "error"); return; }
      this.openComposeModal({
        mode: "draft",
        draftUid: uid,
        mailboxId: this.activeMailbox.id,
        to: this.formatAddressList(msg.to),
        cc: this.formatAddressList(msg.cc),
        subject: msg.subject || "",
        body: msg.text || "",
      });
    },

    openCompose(mode, sourceMessage) {
      const source = sourceMessage || (mode === "new" ? null : this.selectedMessage);
      const mailbox = this.activeMailbox?.can_send
        ? this.activeMailbox
        : this.mailboxes.find((m) => m.can_send);
      if (!mailbox) { showNotification("You do not have send access to any mailbox", "warning"); return; }

      let to = "";
      let cc = "";
      let subject = "";
      let body = "";
      let inReplyTo = "";
      let references = "";
      let title = "Compose Email";

      if (source && mode !== "new" && mode !== "draft") {
        const replyTargets = Array.isArray(source.reply_to) && source.reply_to.length
          ? source.reply_to
          : [{ name: source.from, email: source.from_email }];
        const own = String(mailbox.email_address || "").toLowerCase();
        const self = this;

        if (mode === "reply") {
          to = replyTargets.map((a) => a.email).filter(Boolean).join(", ");
          title = "Reply";
        } else if (mode === "reply_all") {
          to = replyTargets.map((a) => a.email).filter(Boolean).join(", ");
          cc = [...(source.to || []), ...(source.cc || [])]
            .map((a) => a.email)
            .filter((email) => email && email.toLowerCase() !== own && !to.toLowerCase().includes(email.toLowerCase()))
            .join(", ");
          title = "Reply All";
        } else if (mode === "forward") {
          title = "Forward";
        }
        subject = source.subject || "";
        subject = mode === "forward"
          ? (/^fwd:/i.test(subject) ? subject : `Fwd: ${subject}`)
          : (/^re:/i.test(subject) ? subject : `Re: ${subject}`);
        const quoted = String(source.text || "").substring(0, 4000);
        body = `\n\n---------- Original message ----------\nFrom: ${self.formatAddressFirst(source)}\nDate: ${source.date || ""}\nSubject: ${source.subject || ""}\nTo: ${self.formatAddressList(source.to)}\n\n${quoted}`;
        inReplyTo = source.message_id || "";
        references = [String(source.references || "").trim(), inReplyTo].filter(Boolean).join(" ").trim();
      }

      this.openComposeModal({
        mode: mode === "draft" ? "draft" : "send",
        draftUid: null,
        mailboxId: mailbox.id,
        to: mode === "forward" ? "" : to,
        cc,
        subject,
        body,
        inReplyTo,
        references,
        title,
      });
    },

    openComposeModal(config) {
      this.composeContext = config;
      document.getElementById("composeTitle").textContent = config.title || "Compose Email";
      const select = document.getElementById("composeMailbox");
      select.innerHTML = this.mailboxes
        .filter((m) => m.can_send)
        .map((m) => `<option value="${this.esc(m.id)}">${this.esc(m.email_address || m.label)}</option>`)
        .join("");
      select.value = String(config.mailboxId);
      document.getElementById("composeTo").value = config.to || "";
      document.getElementById("composeCc").value = config.cc || "";
      document.getElementById("composeBcc").value = "";
      document.getElementById("composeSubject").value = config.subject || "";
      document.getElementById("composeBody").value = config.body || "";
      document.getElementById("composeAttachments").value = "";
      document.getElementById("composeAttachmentList").innerHTML = "";
      document.getElementById("composeStatus").textContent =
        config.mode === "draft" ? "Editing saved draft" : "";
      const ccWrap = document.getElementById("ccBccWrap");
      ccWrap.style.display = config.cc ? "" : "none";
      document.getElementById("composeCcBccToggle").textContent = config.cc ? "Hide Cc / Bcc" : "Show Cc / Bcc";
      const dirWrap = document.getElementById("composeDirectoryWrap");
      const dirBtn = document.getElementById("composeDirectoryToggle");
      if (dirWrap) dirWrap.style.display = "";
      if (dirBtn) dirBtn.textContent = "Hide directory";
      this.ensureDirectoryPicker();
      if (this.directoryPicker) this.directoryPicker.clear();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("composeModal")).show();
    },

    renderAttachmentChips() {
      const input = document.getElementById("composeAttachments");
      const list = document.getElementById("composeAttachmentList");
      list.innerHTML = Array.from(input.files || []).map((f) =>
        `<span class="badge bg-secondary me-1 mb-1">${this.esc(f.name)} <span class="opacity-75">(${this.formatSize(f.size)})</span></span>`
      ).join("");
    },

    parseAddresses(value) {
      return String(value || "")
        .split(/[,;]+/)
        .map((s) => s.trim())
        .filter(Boolean);
    },

    buildComposePayload() {
      const ctx = this.composeContext || {};
      const body = document.getElementById("composeBody").value;
      const payload = {
        to: this.parseAddresses(document.getElementById("composeTo").value),
        cc: this.parseAddresses(document.getElementById("composeCc").value),
        bcc: this.parseAddresses(document.getElementById("composeBcc").value),
        subject: document.getElementById("composeSubject").value.trim(),
        body_text: body,
        body_html: this.esc(body).replace(/\r?\n/g, "<br>"),
        attachments: ctx.uploaded || [],
      };
      if (ctx.inReplyTo) payload.in_reply_to = ctx.inReplyTo;
      if (ctx.references) payload.references = ctx.references;
      if (ctx.draftUid) payload.draft_uid = ctx.draftUid;
      return payload;
    },

    validateCompose() {
      const mailboxId = document.getElementById("composeMailbox").value;
      const to = document.getElementById("composeTo").value.trim();
      const subject = document.getElementById("composeSubject").value.trim();
      const body = document.getElementById("composeBody").value.trim();
      if (!mailboxId) { showNotification("Choose the sending mailbox", "warning"); return false; }
      if (!to) { showNotification("At least one recipient is required", "warning"); return false; }
      if (!subject) { showNotification("A subject is required", "warning"); return false; }
      if (!body) { showNotification("The message body is required", "warning"); return false; }
      return true;
    },

    async uploadPendingAttachments(profileId) {
      const ctx = this.composeContext || {};
      const files = Array.from(document.getElementById("composeAttachments").files || []);
      if (!files.length) return ctx.uploaded || [];
      const res = await window.API.communications.uploadMailAttachments(profileId, files);
      const uploaded = res?.attachments ?? [];
      ctx.uploaded = uploaded;
      document.getElementById("composeAttachments").value = "";
      this.renderAttachmentChips();
      return uploaded;
    },

    setComposeBusy(busy, label) {
      const sendBtn = document.getElementById("composeSendBtn");
      const draftBtn = document.getElementById("composeSaveDraftBtn");
      sendBtn.disabled = busy;
      draftBtn.disabled = busy;
      document.getElementById("composeStatus").textContent = busy ? label : "";
      sendBtn.innerHTML = busy && label === "Sending..."
        ? '<span class="spinner-border spinner-border-sm me-1"></span>Sending...'
        : '<i class="fas fa-paper-plane me-1"></i>Send';
    },

    async sendComposed() {
      if (!this.validateCompose()) return;
      const profileId = document.getElementById("composeMailbox").value;
      this.setComposeBusy(true, "Sending...");
      try {
        await this.uploadPendingAttachments(profileId);
        const res = await window.API.communications.sendMailboxMessage(profileId, this.buildComposePayload());
        const warning = res?.warning;
        bootstrap.Modal.getOrCreateInstance(document.getElementById("composeModal")).hide();
        showNotification(warning ? `Message sent — ${warning}` : "Message sent successfully.", warning ? "warning" : "success");
        this.activeFolder = this.findSpecialFolder(/sent/i) || this.activeFolder;
        this.view = "messages";
        await this.loadFolders();
      } catch (e) {
        console.error("[manage_email] send failed:", e);
        document.getElementById("composeStatus").textContent = e?.message || "Send failed";
        showNotification(e?.message || "The message could not be sent. It has been recorded in the Outbox.", "error");
      } finally {
        this.setComposeBusy(false, "");
      }
    },

    async saveDraft() {
      if (!this.validateCompose()) return;
      const profileId = document.getElementById("composeMailbox").value;
      this.setComposeBusy(true, "Saving draft...");
      try {
        await this.uploadPendingAttachments(profileId);
        const res = await window.API.communications.saveMailboxDraft(profileId, this.buildComposePayload());
        bootstrap.Modal.getOrCreateInstance(document.getElementById("composeModal")).hide();
        showNotification("Draft saved", "success");
        if (this.draftFolderName && this.view === "messages" && this.activeFolder === this.draftFolderName) {
          await this.loadMessages();
        } else {
          await this.loadFolders();
        }
      } catch (e) {
        console.error("[manage_email] draft save failed:", e);
        document.getElementById("composeStatus").textContent = e?.message || "Draft could not be saved";
        showNotification(e?.message || "Draft could not be saved", "error");
      } finally {
        this.setComposeBusy(false, "");
      }
    },

    downloadAttachment(index) {
      const msg = this.selectedMessage;
      if (!msg) return;
      const att = (msg.attachments || []).find((a) => Number(a.index) === Number(index));
      window.API.communications.downloadMailAttachment({
        index,
        profile_id: this.activeMailbox.id,
        folder: this.activeFolder,
        uid: msg.uid,
        filename: att?.filename || att?.name || "attachment",
      }).catch((e) => showNotification(e?.message || "Download failed", "error"));
    },

    /* ---- Export / print ---- */

    exportCurrentView() {
      if (window.AuthContext?.canExport && !AuthContext.canExport("communications")) return;
      if (this.view === "outbox") {
        const rows = [["Date", "From", "To", "Subject", "Status", "Attempts", "Error"]];
        this.outbox.forEach((row) => rows.push([
          row.sent_at || row.created_at || "",
          row.from_email || "",
          (Array.isArray(row.to) ? row.to.join("; ") : ""),
          row.subject || "",
          row.status || "",
          String(row.attempts ?? 0),
          row.error || "",
        ]));
        KingswayFileLifecycle.exportText(this.toCsv(rows), `mailbox_outbox_${this.csvDate()}.csv`, "text/csv");
        return;
      }
      const rows = [["From", "From Email", "Subject", "Date", "Read", "Flagged", "Attachments"]];
      this.messages.forEach((m) => rows.push([
        m.from || "",
        m.from_email || "",
        m.subject || "",
        m.date || "",
        m.is_read ? "Yes" : "No",
        m.is_flagged ? "Yes" : "No",
        m.has_attachments ? "Yes" : "No",
      ]));
      const scope = this.view === "outbox" ? "outbox" : String(this.activeFolder || "inbox").replace(/[^A-Za-z0-9]+/g, "_");
      KingswayFileLifecycle.exportText(this.toCsv(rows), `mailbox_${scope}_${this.csvDate()}.csv`, "text/csv");
    },

    printCurrentView() {
      if (window.AuthContext?.canPrint && !AuthContext.canPrint("communications")) return;
      const title = this.view === "outbox"
        ? `Outbox — ${this.activeMailbox?.label || ""}`
        : `${this.activeFolder} — ${this.activeMailbox?.label || ""}`;
      if (window.PrintManager?.printPage) {
        window.PrintManager.printPage({ title, orientation: "landscape" });
      } else {
        window.print();
      }
    },

    toCsv(rows) {
      return rows.map((r) => r.map((v) => `"${String(v ?? "").replace(/"/g, '""')}"`).join(",")).join("\n");
    },

    csvDate() { return new Date().toISOString().split("T")[0]; },

    /* ---- Utilities ---- */

    async refreshAll() {
      if (!this.activeMailbox) { await this.loadMailboxes(); return; }
      await this.loadFolders();
    },

    formatDate(v) {
      if (!v) return "—";
      const d = new Date(v);
      if (isNaN(d.getTime())) return String(v);
      const now = new Date();
      if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString("en-KE", { hour: "2-digit", minute: "2-digit" });
      }
      return d.toLocaleDateString("en-KE", { month: "short", day: "2-digit", year: "numeric" });
    },

    formatSize(bytes) {
      const n = Number(bytes || 0);
      if (n < 1024) return n + " B";
      if (n < 1048576) return (n / 1024).toFixed(1) + " KB";
      return (n / 1048576).toFixed(1) + " MB";
    },

    formatAddressFirst(msg) {
      if (!msg) return "Unknown";
      return msg.from_email ? `${msg.from || ""} <${msg.from_email}>`.trim() : (msg.from || "Unknown");
    },

    formatAddressList(list) {
      if (!Array.isArray(list) || !list.length) return "—";
      return list.map((a) => {
        if (a && typeof a === "object") return a.email ? (a.name ? `${a.name} <${a.email}>` : a.email) : "";
        return String(a || "");
      }).filter(Boolean).join(", ") || "—";
    },

    esc(s) {
      return String(s ?? "")
        .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    },

    on(id, ev, fn) { const el = document.getElementById(id); if (el) el.addEventListener(ev, fn); },
  };

  window.ManageEmailController = ManageEmailController;
  document.addEventListener("DOMContentLoaded", () => ManageEmailController.init());
})();
