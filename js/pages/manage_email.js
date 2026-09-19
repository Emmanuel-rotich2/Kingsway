(function () {
  "use strict";

  const ManageEmailController = {
    mailboxes: [],
    activeMailbox: null,
    folders: [],
    activeFolder: "INBOX",
    messages: [],
    messagePage: 1,
    messagePages: 1,
    messageLimit: 25,
    selectedMessageId: null,
    selectedMessage: null,

    async init() {
      if (!document.getElementById("mailboxes-root")) return;
      await window.AuthContext?.ready();
      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }
      this.bindEvents();
      await this.loadMailboxes();
    },

    bindEvents() {
      const self = this;
      this.on("mailRefreshBtn", "click", () => self.refreshCurrentMailbox());
      this.on("mailComposeBtn", "click", () => self.openCompose());
      this.on("composeSendBtn", "click", () => self.sendComposed());
      this.on("mvBackBtn", "click", () => self.hideMessage());
      this.on("msgPrevPage", "click", () => {
        if (self.messagePage > 1) { self.messagePage--; self.loadMessages(); }
      });
      this.on("msgNextPage", "click", () => {
        if (self.messagePage < self.messagePages) { self.messagePage++; self.loadMessages(); }
      });
      document.getElementById("folderList")?.addEventListener("click", (e) => {
        const btn = e.target.closest("[data-folder]");
        if (btn) self.selectFolder(btn.getAttribute("data-folder"));
      });
      document.getElementById("messageList")?.addEventListener("click", (e) => {
        const btn = e.target.closest("[data-msg-id]");
        if (btn) self.openMessage(btn.getAttribute("data-msg-id"));
      });
    },

    /* ---- Mailboxes ---- */

    async loadMailboxes() {
      const root = document.getElementById("mailboxTabs");
      root.innerHTML = '<li class="nav-item"><span class="nav-link text-muted">Loading mailboxes...</span></li>';
      try {
        const res = await window.API.communications.getMailboxes();
        this.mailboxes = res?.data?.mailboxes ?? res?.mailboxes ?? [];
      } catch (e) {
        console.error("[manage_email] mailboxes failed:", e);
        this.mailboxes = [];
      }
      if (!this.mailboxes.length) {
        root.innerHTML = '<li class="nav-item"><span class="nav-link text-muted">No mailboxes assigned</span></li>';
        document.getElementById("folderList").innerHTML = '<div class="list-group-item text-muted small">No mailboxes available</div>';
        return;
      }
      this.renderMailboxTabs();
      this.selectMailbox(this.mailboxes[0].id);
    },

    renderMailboxTabs() {
      const root = document.getElementById("mailboxTabs");
      root.innerHTML = this.mailboxes.map((m) => {
        const active = this.activeMailbox?.id === m.id ? " active" : "";
        const label = this.esc(m.label || m.display_name || m.email_address || "Mailbox");
        return `<li class="nav-item">
          <button class="nav-link${active}" data-mailbox-id="${m.id}" title="${this.esc(m.email_address || '')}">${label}</button>
        </li>`;
      }).join("");
      const self = this;
      root.querySelectorAll("[data-mailbox-id]").forEach((btn) => {
        btn.addEventListener("click", () => self.selectMailbox(btn.getAttribute("data-mailbox-id")));
      });
    },

    selectMailbox(mailboxId) {
      const mb = this.mailboxes.find((m) => String(m.id) === String(mailboxId));
      if (!mb) return;
      this.activeMailbox = mb;
      this.activeFolder = "INBOX";
      this.messagePage = 1;
      this.selectedMessageId = null;
      this.hideMessage();
      this.renderMailboxTabs();
      this.loadFolders();
    },

    /* ---- Folders ---- */

    async loadFolders() {
      const list = document.getElementById("folderList");
      list.innerHTML = '<div class="list-group-item text-muted small"><div class="spinner-border spinner-border-sm"></div> Loading folders...</div>';
      this.hideMessage();
      try {
        const res = await window.API.communications.getMailboxFolders(this.activeMailbox.id);
        const payload = res?.data ?? res ?? {};
        this.folders = payload?.folders ?? [];
      } catch (e) {
        console.error("[manage_email] folders failed:", e);
        this.folders = [];
      }
      this.renderFolders();
      this.loadMessages();
    },

    renderFolders() {
      const list = document.getElementById("folderList");
      if (!this.folders.length) {
        list.innerHTML = '<div class="list-group-item text-muted small">No folders</div>';
        return;
      }
      const active = this.activeFolder;
      list.innerHTML = this.folders.map((f) => {
        const isActive = f.name === active ? " active" : "";
        const unreadBadge = f.unread > 0
          ? ` <span class="badge bg-primary rounded-pill float-end">${f.unread}</span>`
          : "";
        return `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center${isActive}" data-folder="${this.esc(f.name)}">
          <span><i class="fas fa-folder me-1 text-warning"></i>${this.esc(f.name)}</span>
          <span class="text-muted small">${f.total ?? ""}${unreadBadge}</span>
        </button>`;
      }).join("");
    },

    selectFolder(folderName) {
      this.activeFolder = folderName;
      this.messagePage = 1;
      this.selectedMessageId = null;
      this.hideMessage();
      this.renderFolders();
      this.loadMessages();
    },

    /* ---- Messages ---- */

    async loadMessages() {
      if (!this.activeMailbox) return;
      const list = document.getElementById("messageList");
      const notice = document.getElementById("emptyFolderNotice");
      const pagination = document.getElementById("msgPagination");
      const loader = document.getElementById("loadingMessages");
      this.hideMessage();
      list.innerHTML = "";
      notice.style.display = "none";
      pagination.style.display = "none";
      loader.style.display = "";

      try {
        const res = await window.API.communications.getMailboxMessages(this.activeMailbox.id, {
          folder: this.activeFolder,
          page: this.messagePage,
          limit: this.messageLimit,
        });
        const payload = res?.data ?? res ?? {};
        this.messages = payload?.messages ?? [];
        const pg = payload?.pagination ?? {};
        this.messagePages = pg.pages ?? 1;
        this.messagePage = pg.page ?? this.messagePage;
      } catch (e) {
        console.error("[manage_email] messages failed:", e);
        this.messages = [];
        this.messagePages = 1;
      }

      loader.style.display = "none";

      if (!this.messages.length) {
        notice.style.display = "";
        return;
      }
      this.renderMessages();
      this.renderPagination();
    },

    renderMessages() {
      const list = document.getElementById("messageList");
      list.innerHTML = this.messages.map((msg) => {
        const unread = msg.is_read ? "" : " fw-bold";
        const date = msg.date ? this.formatDate(msg.date) : "—";
        const attachBadge = msg.has_attachments
          ? ` <i class="fas fa-paperclip text-muted ms-1" title="Attachments"></i>`
          : "";
        const preview = this.esc((msg.preview ?? "").substring(0, 120));
        return `<div class="d-flex align-items-start p-2 border-bottom msg-row${unread}" data-msg-id="${this.esc(msg.id)}" role="button" tabindex="0">
          <div class="me-2 text-muted" style="width:150px;" title="${this.esc(msg.from_email || msg.from || '')}">
            <div class="text-truncate">${this.esc(msg.from || "Unknown")}</div>
            <small class="text-nowrap">${date}</small>
          </div>
          <div class="flex-grow-1 min-width-0">
            <span class="text-truncate d-block">${this.esc(msg.subject || "(No Subject)")}${attachBadge}</span>
            <small class="text-muted text-truncate d-block">${preview}</small>
          </div>
        </div>`;
      }).join("");
    },

    renderPagination() {
      const pagination = document.getElementById("msgPagination");
      if (this.messagePages <= 1) { pagination.style.display = "none"; return; }
      pagination.style.display = "";
      document.getElementById("msgPageLabel").textContent = this.messagePage + " / " + this.messagePages;
      document.getElementById("msgPaginationInfo").textContent = "Page " + this.messagePage;
      document.getElementById("msgPrevPage").disabled = this.messagePage <= 1;
      document.getElementById("msgNextPage").disabled = this.messagePage >= this.messagePages;
    },

    /* ---- Message viewer ---- */

    async openMessage(messageId) {
      if (!this.activeMailbox) return;
      this.selectedMessageId = messageId;
      document.getElementById("messageViewer").style.display = "";
      document.getElementById("messageList").style.display = "none";
      document.getElementById("msgPagination").style.display = "none";

      document.getElementById("mvSubject").textContent = "Loading...";
      document.getElementById("mvFrom").textContent = "";
      document.getElementById("mvTo").textContent = "";
      document.getElementById("mvDate").textContent = "";
      document.getElementById("mvAttachments").style.display = "none";
      document.getElementById("mvBody").innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

      try {
        const res = await window.API.communications.getMailboxMessage(this.activeMailbox.id, {
          message_id: messageId,
          folder: this.activeFolder,
          profile_id: this.activeMailbox.id,
        });
        this.selectedMessage = res?.data?.message ?? res?.message ?? res?.data ?? null;
      } catch (e) {
        console.error("[manage_email] message load failed:", e);
        this.selectedMessage = null;
      }
      if (!this.selectedMessage) {
        document.getElementById("mvSubject").textContent = "Message not found";
        document.getElementById("mvBody").innerHTML = '<div class="text-muted">Could not load message.</div>';
        return;
      }
      this.renderMessage();
    },

    renderMessage() {
      const msg = this.selectedMessage;
      if (!msg) return;
      document.getElementById("mvSubject").textContent = msg.subject || "(No Subject)";
      document.getElementById("mvFrom").textContent = msg.from_email ? msg.from + " <" + msg.from_email + ">" : (msg.from || "Unknown");
      document.getElementById("mvTo").textContent = msg.to || "—";
      document.getElementById("mvDate").textContent = msg.date || "—";
      const attDiv = document.getElementById("mvAttachments");
      if (msg.attachments?.length) {
        attDiv.innerHTML = '<strong class="small">Attachments:</strong> ' +
          msg.attachments.map((a) => `<span class="badge bg-secondary me-1">${this.esc(a.name)}</span>`).join("");
        attDiv.style.display = "";
      } else {
        attDiv.style.display = "none";
      }
      document.getElementById("mvBody").innerHTML = msg.html || "<pre>" + this.esc(msg.text || "No content") + "</pre>";
    },

    hideMessage() {
      document.getElementById("messageViewer").style.display = "none";
      document.getElementById("messageList").style.display = "";
      this.selectedMessageId = null;
      this.selectedMessage = null;
      this.renderPagination();
    },

    /* ---- Compose (sends via platform SMTP) ---- */

    openCompose() {
      document.getElementById("composeForm").reset();
      const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("composeModal"));
      modal.show();
    },

    async sendComposed() {
      const to = document.getElementById("composeTo").value.trim();
      const subject = document.getElementById("composeSubject").value.trim();
      const body = document.getElementById("composeBody").value.trim();
      if (!to || !subject || !body) { showNotification("To, Subject and Message are required", "warning"); return; }
      const sendBtn = document.getElementById("composeSendBtn");
      sendBtn.disabled = true;
      sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
      try {
        const created = await window.API.communications.createCommunication({
          type: "email",
          status: "sent",
          recipients: [to],
          subject,
          message: body,
        });
        const commId = created?.data?.id ?? created?.id ?? created?.data?.communication_id ?? created?.communication_id;
        if (commId) {
          await window.API.communications.dispatchCommunication(commId);
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById("composeModal")).hide();
        showNotification("Email sent", "success");
        await this.refreshCurrentMailbox();
      } catch (e) {
        console.error("[manage_email] send failed:", e);
        showNotification(e.message || "Failed to send email", "error");
      } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send';
      }
    },

    async refreshCurrentMailbox() {
      if (!this.activeMailbox) return;
      if (this.selectedMessageId) {
        await this.openMessage(this.selectedMessageId);
      } else if (this.activeFolder) {
        await this.loadMessages();
      } else {
        await this.loadFolders();
      }
    },

    /* ---- Utilities ---- */

    formatDate(v) {
      if (!v) return "—";
      const d = new Date(v);
      if (isNaN(d.getTime())) return v;
      const now = new Date();
      const isToday = d.toDateString() === now.toDateString();
      if (isToday) return d.toLocaleTimeString("en-KE", { hour: "2-digit", minute: "2-digit" });
      return d.toLocaleDateString("en-KE", { month: "short", day: "2-digit", year: "numeric" });
    },

    esc(s) { return String(s || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); },
    on(id, ev, fn) { const el = document.getElementById(id); if (el) el.addEventListener(ev, fn); },
  };

  window.ManageEmailController = ManageEmailController;
  document.addEventListener("DOMContentLoaded", () => ManageEmailController.init());
})();
