/**
 * Messages Inbox Controller
 *
 * Internal user-to-user messaging for the messages_inbox.php two-pane UI.
 * Backend (CommunicationsController):
 *   GET  /communications/conversations        -> listConversations
 *   GET  /communications/conversations/{id}   -> thread (marks read)
 *   POST /communications/conversations        -> create + first message
 *   POST /communications/conversations/{id}   -> reply
 *   GET  /communications/recipients?q=        -> recipient search
 *
 * All dynamic content is escaped before injection (XSS-safe).
 */
const MessagesInboxController = {
  API: window.API,
  conversations: [],
  activeId: null,
  filter: "all",
  search: "",
  recipients: [],
  selectedRecipients: [],
  recipientTimer: null,

  esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  },

  initials(name) {
    const parts = String(name || "").trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return "?";
    return (parts[0][0] + (parts[1] ? parts[1][0] : "")).toUpperCase();
  },

  avatar(name, extra = "") {
    const palette = ["kw-av-green", "kw-av-blue", "kw-av-gold", "kw-av-purple", "kw-av-teal", "kw-av-orange"];
    const key = String(name || "?").split("").reduce((a, c) => a + c.charCodeAt(0), 0);
    const cls = palette[key % palette.length];
    return `<span class="kw-avatar ${cls}${extra ? " " + extra : ""}">${this.esc(this.initials(name))}</span>`;
  },

  async init() {
    if (!document.getElementById("kwMessaging")) return;
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.bindEvents();
    this.subscribeRealtime();
    await this.loadConversations();
    const initial = Number(new URLSearchParams(window.location.search).get("conversation_id") || 0);
    if (initial) this.openThread(initial);
  },

  bindEvents() {
    const startCompose = () => this.openCompose();
    document.getElementById("btnNewMessage")?.addEventListener("click", startCompose);
    document.querySelectorAll("[data-start-compose]").forEach((el) => el.addEventListener("click", startCompose));
    document.querySelectorAll("[data-cancel-compose]").forEach((el) => el.addEventListener("click", () => this.closeCompose()));

    document.getElementById("msgSearch")?.addEventListener("input", (e) => {
      this.search = e.target.value.trim();
      this.renderList();
    });

    document.querySelectorAll("#msgFilters .kw-msg-filter").forEach((el) => {
      el.addEventListener("click", () => {
        document.querySelectorAll("#msgFilters .kw-msg-filter").forEach((b) => b.classList.remove("active"));
        el.classList.add("active");
        this.filter = el.dataset.filter || "all";
        this.renderList();
      });
    });

    document.getElementById("btnSendReply")?.addEventListener("click", () => this.sendReply());
    document.getElementById("replyBox")?.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) this.sendReply();
    });

    const search = document.getElementById("newRecipientSearch");
    search?.addEventListener("input", () => {
      clearTimeout(this.recipientTimer);
      const term = search.value.trim();
      this.recipientTimer = setTimeout(() => this.searchRecipients(term), 220);
    });
    search?.addEventListener("focus", () => {
      if (!this.recipients.length) this.searchRecipients(search.value.trim());
    });
    document.addEventListener("click", (e) => {
      const results = document.getElementById("recipientResults");
      if (results && !results.contains(e.target) && e.target !== search) results.classList.add("d-none");
    });

    document.getElementById("btnSendMessage")?.addEventListener("click", () => this.sendMessage());
  },

  subscribeRealtime() {
    window.addEventListener("kingsway:data-mutated", (e) => this.realtimeHandler(e));
  },

  realtimeHandler(e) {
    const detail = e.detail || {};
    const scope = String(detail.scope || "").toLowerCase();
    const targets = Array.isArray(detail.payload?.targets) ? detail.payload.targets : [];
    const relevant = !scope || scope === "all" || scope.startsWith("users:") ||
      scope.includes("conversation") || targets.includes("conversations");
    if (!relevant) return;
    this.loadConversations();
    const convId = Number(detail.payload?.conversation_id || 0);
    if (this.activeId && convId && convId === Number(this.activeId)) {
      this.openThread(this.activeId);
    }
  },

  async loadConversations() {
    try {
      const data = await this.API.communications.getConversations();
      this.conversations = Array.isArray(data) ? data : (data?.data || []);
    } catch (error) {
      console.error("Failed to load conversations", error);
      this.conversations = [];
    }
    this.renderList();
  },

  visibleConversations() {
    const term = this.search.toLowerCase();
    return this.conversations.filter((c) => {
      if (this.filter === "unread" && Number(c.unread_count || 0) < 1) return false;
      if (this.filter === "group" && c.conversation_type !== "group") return false;
      if (term) {
        const hay = `${c.title || ""} ${c.participant_names || ""} ${c.last_message || ""}`.toLowerCase();
        if (!hay.includes(term)) return false;
      }
      return true;
    });
  },

  renderList() {
    const list = document.getElementById("conversationList");
    if (!list) return;
    const rows = this.visibleConversations();
    if (!rows.length) {
      list.innerHTML = '<div class="kw-msg-empty-list"><i class="bi bi-chat-square-dots"></i><span>No conversations.</span></div>';
      return;
    }
    list.innerHTML = rows.map((c) => {
      const unread = Number(c.unread_count || 0);
      const title = c.title || c.participant_names || "Conversation";
      const active = Number(c.id) === Number(this.activeId) ? " active" : "";
      const preview = String(c.last_message || "").replace(/\s+/g, " ").slice(0, 80);
      return `<div class="kw-msg-item${active}" data-conversation="${c.id}">
        ${this.avatar(title)}
        <div class="kw-msg-item-body">
          <div class="kw-msg-item-top">
            <span class="kw-msg-item-name">${this.esc(title)}</span>
            <span class="kw-msg-item-time">${this.esc(this.fmtWhen(c.last_message_at || c.updated_at || c.created_at))}</span>
          </div>
          <div class="kw-msg-item-bottom">
            <span class="kw-msg-item-preview">${this.esc(preview || "No messages yet")}</span>
            <span class="kw-msg-item-meta">${unread > 0 ? `<span class="kw-msg-unread">${unread}</span>` : ""}</span>
          </div>
        </div>
      </div>`;
    }).join("");
    list.querySelectorAll("[data-conversation]").forEach((el) => {
      el.addEventListener("click", () => this.openThread(el.getAttribute("data-conversation")));
    });
  },

  showPane(name) {
    const map = { empty: "threadEmpty", thread: "threadView", compose: "composeView" };
    Object.values(map).forEach((id) => document.getElementById(id)?.classList.add("d-none"));
    document.getElementById(map[name])?.classList.remove("d-none");
  },

  async openThread(id) {
    this.activeId = id;
    this.closeCompose(false);
    this.renderList();
    this.showPane("thread");
    const scroll = document.getElementById("threadMessages");
    if (scroll) scroll.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-success"></div></div>';
    try {
      const data = await this.API.communications.getConversation(id);
      const conv = data?.conversation || {};
      const messages = Array.isArray(data?.messages) ? data.messages : [];
      const titleEl = document.getElementById("threadTitle");
      const partEl = document.getElementById("threadParticipants");
      const badge = document.getElementById("threadGroupBadge");
      if (titleEl) titleEl.textContent = conv.title || conv.participant_names || "Conversation";
      if (partEl) partEl.textContent = conv.participant_names || "";
      if (badge) badge.classList.toggle("d-none", conv.conversation_type !== "group");
      this.renderThread(messages);
    } catch (error) {
      console.error("Failed to open conversation", error);
      const scroll = document.getElementById("threadMessages");
      if (scroll) scroll.innerHTML = '<div class="text-danger text-center py-4">Unable to load conversation.</div>';
      this.notify(error.message || "Unable to load conversation", "error");
    }
  },

  renderThread(messages) {
    const scroll = document.getElementById("threadMessages");
    if (!scroll) return;
    if (!messages.length) {
      scroll.innerHTML = '<div class="text-center text-muted py-4">No messages yet. Say hello.</div>';
      return;
    }
    scroll.innerHTML = messages.map((m) => {
      const mine = Number(m.is_mine) === 1;
      const sender = mine ? "You" : (m.sender_name || "Unknown");
      const priority = m.priority && m.priority !== "normal"
        ? `<span class="kw-msg-priority">${this.esc(m.priority)}</span>` : "";
      const subject = m.subject ? `<div class="fw-semibold small mb-1">${this.esc(m.subject)}</div>` : "";
      return `<div class="kw-msg-row ${mine ? "mine" : "theirs"}">
        <div class="kw-msg-bubble">
          <div class="kw-msg-bubble-meta"><span class="kw-msg-bubble-sender">${this.esc(sender)}</span></div>
          ${subject}
          <div class="kw-msg-bubble-text">${this.esc(m.message_body || "")}</div>
          <div class="kw-msg-bubble-time">${this.esc(this.fmtWhen(m.created_at))}${priority}</div>
        </div>
      </div>`;
    }).join("");
    scroll.scrollTop = scroll.scrollHeight;
  },

  async sendReply() {
    const box = document.getElementById("replyBox");
    if (!box || !this.activeId) return;
    const message = box.value.trim();
    if (!message) { this.notify("Type a reply first", "warning"); return; }
    const btn = document.getElementById("btnSendReply");
    if (btn) btn.disabled = true;
    try {
      await this.API.communications.replyConversation(this.activeId, { message });
      box.value = "";
      await this.openThread(this.activeId);
      this.loadConversations();
    } catch (error) {
      console.error(error);
      this.notify(error.message || "Reply failed", "error");
    } finally {
      if (btn) btn.disabled = false;
    }
  },

  openCompose() {
    this.selectedRecipients = [];
    const form = document.getElementById("composeForm");
    if (form) form.reset();
    const search = document.getElementById("newRecipientSearch");
    if (search) { search.value = ""; }
    const results = document.getElementById("recipientResults");
    if (results) results.classList.add("d-none");
    this.renderSelectedRecipients();
    this.showPane("compose");
  },

  closeCompose(showEmpty = true) {
    document.getElementById("composeView")?.classList.add("d-none");
    if (showEmpty && !this.activeId) this.showPane("empty");
  },

  async searchRecipients(term) {
    try {
      const data = await this.API.communications.searchMessageRecipients(term || "");
      this.recipients = Array.isArray(data) ? data : (data?.data || []);
    } catch (error) {
      console.error("Recipient search failed", error);
      this.recipients = [];
    }
    this.renderRecipientResults();
  },

  renderRecipientResults() {
    const box = document.getElementById("recipientResults");
    if (!box) return;
    const chosen = new Set(this.selectedRecipients.map((r) => Number(r.id)));
    const rows = this.recipients.filter((r) => !chosen.has(Number(r.id)));
    if (!rows.length) {
      box.innerHTML = '<div class="kw-msg-recipient-empty">No matches.</div>';
      box.classList.remove("d-none");
      return;
    }
    box.innerHTML = rows.map((r) => `<button type="button" class="kw-msg-recipient-item" data-recipient="${r.id}">
      ${this.avatar(r.full_name || r.username)}
      <span class="kw-msg-recipient-item-name">${this.esc(r.full_name || r.username || "User")}</span>
      <span class="kw-msg-recipient-item-role">${this.esc(r.roles || r.email || "")}</span>
    </button>`).join("");
    box.classList.remove("d-none");
    box.querySelectorAll("[data-recipient]").forEach((el) => {
      el.addEventListener("click", () => this.addRecipient(Number(el.getAttribute("data-recipient"))));
    });
  },

  addRecipient(id) {
    const found = this.recipients.find((r) => Number(r.id) === Number(id));
    if (!found || this.selectedRecipients.some((r) => Number(r.id) === Number(id))) return;
    this.selectedRecipients.push(found);
    const search = document.getElementById("newRecipientSearch");
    if (search) { search.value = ""; }
    document.getElementById("recipientResults")?.classList.add("d-none");
    this.renderSelectedRecipients();
  },

  removeRecipient(id) {
    this.selectedRecipients = this.selectedRecipients.filter((r) => Number(r.id) !== Number(id));
    this.renderSelectedRecipients();
  },

  renderSelectedRecipients() {
    const box = document.getElementById("selectedRecipients");
    if (!box) return;
    box.innerHTML = this.selectedRecipients.map((r) => `<span class="badge bg-success me-1 mb-1" data-chip="${r.id}">
      ${this.esc(r.full_name || r.username || "User")}
      <button type="button" class="btn-close btn-close-white ms-1" style="font-size:.6rem" aria-label="Remove"></button>
    </span>`).join("");
    box.querySelectorAll("[data-chip]").forEach((el) => {
      el.querySelector("button")?.addEventListener("click", () => this.removeRecipient(Number(el.getAttribute("data-chip"))));
    });
  },

  async sendMessage() {
    if (!this.selectedRecipients.length) { this.notify("Add at least one recipient", "warning"); return; }
    const message = (document.getElementById("newMessage")?.value || "").trim();
    if (!message) { this.notify("Type a message first", "warning"); return; }
    const subject = (document.getElementById("newSubject")?.value || "").trim();
    const priority = document.getElementById("newPriority")?.value || "normal";
    const recipients = this.selectedRecipients.map((r) => Number(r.id));
    const btn = document.getElementById("btnSendMessage");
    if (btn) btn.disabled = true;
    try {
      const data = await this.API.communications.createConversation({ recipients, subject, message, priority });
      const convId = data?.conversation?.id;
      this.selectedRecipients = [];
      this.notify("Message sent", "success");
      await this.loadConversations();
      if (convId) this.openThread(convId);
      else this.showPane("empty");
    } catch (error) {
      console.error(error);
      this.notify(error.message || "Failed to send message", "error");
    } finally {
      if (btn) btn.disabled = false;
    }
  },

  fmtWhen(value) {
    if (!value) return "";
    const d = new Date(String(value).replace(" ", "T"));
    if (isNaN(d.getTime())) return String(value);
    const sameDay = d.toDateString() === new Date().toDateString();
    return sameDay
      ? d.toLocaleTimeString("en-KE", { hour: "2-digit", minute: "2-digit" })
      : d.toLocaleDateString("en-KE", { month: "short", day: "2-digit" });
  },

  notify(msg, type = "info") {
    if (window.showNotification) window.showNotification(msg, type);
    else console.warn(msg);
  },
};

window.MessagesInboxController = MessagesInboxController;
document.addEventListener("DOMContentLoaded", () => MessagesInboxController.init().catch((e) => console.error(e)));
