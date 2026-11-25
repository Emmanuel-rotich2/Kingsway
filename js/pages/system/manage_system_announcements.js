(function () {
  "use strict";
  const AnnouncementsController = {
    items: [],
    filtered: [],
    pagination: { total: 0, page: 1, limit: 25, pages: 0 },
    currentPage: 1,
    perPage: 25,
    modal: null,
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
      this.modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("annModal"));
      this.bind();
      await Promise.all([this.load(), this.loadStats()]);
    },

    bind() {
      this.on("annCreateBtn", "click", () => this.openModal());
      this.on("annRefresh", "click", () => { this.load(); this.loadStats(); });
      this.on("annForm", "submit", (e) => { e.preventDefault(); this.save(); });
      this.on("annClearFilters", "click", () => this.clearFilters());
      this.on("annExportCsv", "click", () => this.exportCsv());
      this.on("annPrint", "click", () => window.PrintManager?.printPage({ title: "System Announcements", orientation: "landscape" }) ?? window.print());
      this.on("annTableBody", "click", (event) => {
        const button = event.target.closest("[data-action]");
        if (!button) return;
        const id = Number(button.dataset.id);
        if (button.dataset.action === "view") this.viewItem(id);
        else if (button.dataset.action === "edit") this.editItem(id);
        else if (button.dataset.action === "delete") this.deleteItem(id);
      });
      ["annSearch", "annStatusFilter", "annTypeFilter", "annPriorityFilter"].forEach(id => {
        this.on(id, "input", () => { this.currentPage = 1; this.applyFilters(); });
        this.on(id, "change", () => { this.currentPage = 1; this.applyFilters(); });
      });
    },

    on(id, evt, fn) { const el = document.getElementById(id); if (el) el.addEventListener(evt, fn); },

    async load() {
      const state = document.getElementById("annState");
      const body = document.getElementById("annTableBody");
      this.setState(state, "Loading announcements...", "info");
      try {
        const params = { page: this.currentPage, limit: this.perPage };
        const s = document.getElementById("annSearch")?.value?.trim(); if (s) params.search = s;
        const st = document.getElementById("annStatusFilter")?.value; if (st) params.status = st;
        const tp = document.getElementById("annTypeFilter")?.value; if (tp) params.announcement_type = tp;
        const pr = document.getElementById("annPriorityFilter")?.value; if (pr) params.priority = pr;
        const res = await window.API.system.getSystemAnnouncements(params);
        this.items = res?.items || [];
        this.pagination = res?.pagination || this.pagination;
        this.filtered = [...this.items];
        this.renderTable();
        if (state) state.classList.add("d-none");
      } catch (e) {
        console.error("[Announcements] load failed", e);
        this.setState(state, "Failed to load announcements.", "danger");
      }
    },

    async loadStats() {
      try {
        const [totalRes, draftRes, pubRes, schedRes] = await Promise.all([
          window.API.system.getSystemAnnouncements({ limit: 1 }),
          window.API.system.getSystemAnnouncements({ status: "draft", limit: 1 }),
          window.API.system.getSystemAnnouncements({ status: "published", limit: 1 }),
          window.API.system.getSystemAnnouncements({ status: "scheduled", limit: 1 }),
        ]);
        const archRes = await window.API.system.getSystemAnnouncements({ status: "archived", limit: 1 }).catch(() => ({pagination:{total:0}}));
        this.setStat("annStatTotal", totalRes?.pagination?.total ?? 0);
        this.setStat("annStatPublished", pubRes?.pagination?.total ?? 0);
        this.setStat("annStatDraft", draftRes?.pagination?.total ?? 0);
        this.setStat("annStatScheduled", schedRes?.pagination?.total ?? 0);
        this.setStat("annStatExpiring", 0);
        this.setStat("annStatArchived", archRes?.pagination?.total ?? 0);
      } catch (e) { console.error("[Announcements] stats load failed", e); }
    },

    setStat(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; },

    applyFilters() {
      let items = [...this.items];
      const search = document.getElementById("annSearch")?.value?.trim().toLowerCase();
      if (search) items = items.filter(i => (i.title||'').toLowerCase().includes(search) || (i.content||'').toLowerCase().includes(search));
      const status = document.getElementById("annStatusFilter")?.value;
      if (status) items = items.filter(i => i.status === status);
      const type = document.getElementById("annTypeFilter")?.value;
      if (type) items = items.filter(i => i.announcement_type === type);
      const priority = document.getElementById("annPriorityFilter")?.value;
      if (priority) items = items.filter(i => i.priority === priority);
      this.filtered = items;
      this.renderTable();
    },

    clearFilters() {
      ["annSearch", "annStatusFilter", "annTypeFilter", "annPriorityFilter"].forEach(id => { const el = document.getElementById(id); if (el) el.value = ""; });
      this.currentPage = 1;
      this.load();
    },

    renderTable() {
      const body = document.getElementById("annTableBody");
      if (!body) return;
      const stateRow = document.getElementById("annTableState");
      const stateCell = stateRow?.querySelector("td");
      body.querySelectorAll("[data-announcement-row]").forEach((row) => row.remove());
      if (!this.filtered.length) {
        if (stateCell) stateCell.textContent = "No announcements found.";
        if (stateRow) stateRow.hidden = false;
        this.renderPagination();
        return;
      }
      if (stateRow) stateRow.hidden = true;
      const template = document.getElementById("annRowTemplate");
      if (!template) return;
      const typeBadge = { general: "secondary", academic: "info", administrative: "primary", event: "warning", emergency: "danger", maintenance: "dark" };
      const priorityBadge = { low: "secondary", normal: "primary", high: "warning", critical: "danger" };
      const statusBadge = { draft: "secondary", scheduled: "info", published: "success", archived: "dark", expired: "danger" };
      const fragment = document.createDocumentFragment();
      this.filtered.forEach((announcement) => {
        const row = template.content.firstElementChild.cloneNode(true);
        const set = (field, value) => {
          const element = row.querySelector(`[data-field="${field}"]`);
          if (element) element.textContent = value ?? "—";
        };
        set("title", announcement.title);
        set("type", announcement.announcement_type);
        set("priority", announcement.priority);
        set("audience", announcement.target_audience);
        set("status", announcement.status);
        set("created", announcement.created_at || "—");
        const classes = [
          ["type", typeBadge[announcement.announcement_type]],
          ["priority", priorityBadge[announcement.priority]],
          ["status", statusBadge[announcement.status]],
        ];
        classes.forEach(([field, color]) => row.querySelector(`[data-field="${field}"]`)?.classList.add(`bg-${color || "secondary"}`));
        row.querySelectorAll("[data-action]").forEach((button) => { button.dataset.id = String(announcement.id); });
        fragment.appendChild(row);
      });
      body.insertBefore(fragment, stateRow || null);
      this.renderPagination();
    },

    renderPagination() {
      const nav = document.getElementById("annPagination");
      if (!nav) return;
      nav.replaceChildren();
      if (this.pagination.pages <= 1) return;
      const appendPage = (templateId, page, active = false) => {
        const template = document.getElementById(templateId);
        if (!template) return;
        const item = template.content.firstElementChild.cloneNode(true);
        const link = item.querySelector("[data-page]");
        if (link && page != null) {
          link.dataset.page = String(page);
          if (templateId === "annPageNumberTemplate") link.textContent = String(page);
        }
        if (active) item.classList.add("active");
        nav.appendChild(item);
      };
      if (this.currentPage > 1) appendPage("annPagePreviousTemplate", this.currentPage - 1);
      for (let i = 1; i <= this.pagination.pages; i++) {
        if (i === 1 || i === this.pagination.pages || Math.abs(i - this.currentPage) <= 2) {
          appendPage("annPageNumberTemplate", i, i === this.currentPage);
        } else if (Math.abs(i - this.currentPage) === 3) {
          const template = document.getElementById("annPageEllipsisTemplate");
          if (template) nav.appendChild(template.content.firstElementChild.cloneNode(true));
        }
      }
      if (this.currentPage < this.pagination.pages) appendPage("annPageNextTemplate", this.currentPage + 1);
      nav.querySelectorAll('[data-page]').forEach(a => a.addEventListener("click", e => { e.preventDefault(); this.currentPage = +a.dataset.page; this.load(); }));
    },

    openModal(item = null) {
      document.getElementById("annEditId").value = item?.id || "";
      document.getElementById("annTitle").value = item?.title || "";
      document.getElementById("annType").value = item?.announcement_type || "general";
      document.getElementById("annPriority").value = item?.priority || "normal";
      document.getElementById("annAudience").value = item?.target_audience || "all";
      document.getElementById("annContent").value = item?.content || "";
      document.getElementById("annStatus").value = item?.status || "draft";
      document.getElementById("annScheduledAt").value = item?.scheduled_at ? item.scheduled_at.replace(' ', 'T').slice(0,16) : "";
      document.getElementById("annExpiresAt").value = item?.expires_at ? item.expires_at.replace(' ', 'T').slice(0,16) : "";
      document.getElementById("annModalTitle").textContent = item ? "Edit Announcement" : "New System Announcement";
      this.modal?.show();
    },

    async save() {
      const id = document.getElementById("annEditId")?.value;
      const data = {
        title: document.getElementById("annTitle")?.value?.trim(),
        content: document.getElementById("annContent")?.value,
        announcement_type: document.getElementById("annType")?.value,
        priority: document.getElementById("annPriority")?.value,
        target_audience: document.getElementById("annAudience")?.value,
        status: document.getElementById("annStatus")?.value,
        scheduled_at: document.getElementById("annScheduledAt")?.value?.replace('T',' ') || null,
        expires_at: document.getElementById("annExpiresAt")?.value?.replace('T',' ') || null,
      };
      if (!data.title || !data.content) { window.showNotification?.("Title and content are required.", "warning"); return; }
      try {
        if (id) await window.API.system.updateSystemAnnouncement(+id, data);
        else await window.API.system.createSystemAnnouncement(data);
        this.modal?.hide();
        window.showNotification?.(`Announcement ${id ? 'updated' : 'created'} successfully.`, "success");
        this.load(); this.loadStats();
      } catch (e) {
        console.error("[Announcements] save failed", e);
        window.showNotification?.("Failed to save announcement.", "error");
      }
    },

    async viewItem(id) {
      try {
        const a = await window.API.system.getSystemAnnouncement(id);
        if (!a?.id) { window.showNotification?.("Announcement not found.", "warning"); return; }
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("annViewModal"));
        document.getElementById("annViewTitle").textContent = a.title;
        document.querySelectorAll("#annViewBody [data-view]").forEach((element) => {
          element.textContent = ({
            type: a.announcement_type,
            priority: a.priority,
            status: a.status,
            audience: a.target_audience,
            views: a.view_count ?? 0,
            content: a.content,
            created: a.created_at || "—",
            updated: a.updated_at || "—",
          })[element.dataset.view] ?? "—";
        });
        modal.show();
      } catch (e) { console.error("[Announcements] view failed", e); window.showNotification?.("Failed to load announcement.", "error"); }
    },

    async editItem(id) {
      try {
        const a = await window.API.system.getSystemAnnouncement(id);
        if (a?.id) this.openModal(a);
        else window.showNotification?.("Announcement not found.", "warning");
      } catch (e) { console.error("[Announcements] edit fetch failed", e); }
    },

    async deleteItem(id) {
      if (!(await window.confirmAction("Delete announcement", "Delete this announcement permanently?", { confirmText: "Delete", danger: true }))) return;
      try {
        await window.API.system.deleteSystemAnnouncement(id);
        window.showNotification?.("Announcement deleted.", "success");
        this.load(); this.loadStats();
      } catch (e) { console.error("[Announcements] delete failed", e); window.showNotification?.("Failed to delete announcement.", "error"); }
    },

    exportCsv() {
      const csv = "Title,Type,Priority,Audience,Status,Created\n" + this.filtered.map(a => `"${(a.title||'').replace(/"/g,'""')}","${a.announcement_type}","${a.priority}","${a.target_audience}","${a.status}","${a.created_at||''}"`).join("\n");
      window.KingswayFileLifecycle?.exportText?.(csv, "system-announcements.csv", "text/csv");
    },

    setState(element, message, type) {
      if (!element) return;
      element.className = `alert alert-${type}`;
      const text = element.querySelector("[data-ann-state-message]");
      if (text) text.textContent = message;
    },
  };
  window.AnnouncementsController = AnnouncementsController;
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", () => AnnouncementsController.init());
  else AnnouncementsController.init();
})();
