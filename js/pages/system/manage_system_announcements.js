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
      this.modal = new bootstrap.Modal(document.getElementById("annModal"));
      this.bind();
      await Promise.all([this.load(), this.loadStats()]);
    },

    bind() {
      this.on("annCreateBtn", "click", () => this.openModal());
      this.on("annRefresh", "click", () => { this.load(); this.loadStats(); });
      this.on("annForm", "submit", (e) => { e.preventDefault(); this.save(); });
      this.on("annClearFilters", "click", () => this.clearFilters());
      this.on("annExportCsv", "click", () => this.exportCsv());
      this.on("annPrint", "click", () => window.print());
      ["annSearch", "annStatusFilter", "annTypeFilter", "annPriorityFilter"].forEach(id => {
        this.on(id, "input", () => { this.currentPage = 1; this.applyFilters(); });
        this.on(id, "change", () => { this.currentPage = 1; this.applyFilters(); });
      });
    },

    on(id, evt, fn) { const el = document.getElementById(id); if (el) el.addEventListener(evt, fn); },

    async load() {
      const state = document.getElementById("annState");
      const body = document.getElementById("annTableBody");
      if (state) { state.className = "alert alert-info"; state.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Loading announcements...'; }
      if (body) body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>';
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
        if (state) { state.className = "alert alert-success py-2 d-none"; }
      } catch (e) {
        console.error("[Announcements] load failed", e);
        if (state) { state.className = "alert alert-danger"; state.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Failed to load announcements.'; }
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
      if (!this.filtered.length) { body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No announcements found.</td></tr>'; this.renderPagination(); return; }
      body.innerHTML = this.filtered.map(a => {
        const typeBadge = {general:'secondary',academic:'info',administrative:'primary',event:'warning',emergency:'danger',maintenance:'dark'};
        const priBadge = {low:'secondary',normal:'primary',high:'warning',critical:'danger'};
        const statusBadge = {draft:'secondary',scheduled:'info',published:'success',archived:'dark',expired:'danger'};
        return `<tr>
          <td class="fw-semibold">${this.esc(a.title)}</td>
          <td><span class="badge bg-${typeBadge[a.announcement_type]||'secondary'}">${this.esc(a.announcement_type)}</span></td>
          <td><span class="badge bg-${priBadge[a.priority]||'secondary'}">${this.esc(a.priority)}</span></td>
          <td>${this.esc(a.target_audience)}</td>
          <td><span class="badge bg-${statusBadge[a.status]||'secondary'}">${this.esc(a.status)}</span></td>
          <td><small>${this.esc(a.created_at || '—')}</small></td>
          <td>
            <button class="btn btn-sm btn-outline-info me-1" data-action="view" data-id="${a.id}" title="View"><i class="bi bi-eye"></i></button>
            <button class="btn btn-sm btn-outline-primary me-1" data-action="edit" data-id="${a.id}" title="Edit"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${a.id}" title="Delete"><i class="bi bi-trash"></i></button>
          </td>
        </tr>`;
      }).join("");
      body.querySelectorAll("[data-action='view']").forEach(b => b.addEventListener("click", () => this.viewItem(+b.dataset.id)));
      body.querySelectorAll("[data-action='edit']").forEach(b => b.addEventListener("click", () => this.editItem(+b.dataset.id)));
      body.querySelectorAll("[data-action='delete']").forEach(b => b.addEventListener("click", () => this.deleteItem(+b.dataset.id)));
      this.renderPagination();
    },

    renderPagination() {
      const nav = document.getElementById("annPagination");
      if (!nav || this.pagination.pages <= 1) { if (nav) nav.innerHTML = ""; return; }
      let html = '';
      if (this.currentPage > 1) html += `<li class="page-item"><a class="page-link" data-page="${this.currentPage-1}">&laquo;</a></li>`;
      for (let i = 1; i <= this.pagination.pages; i++) {
        if (i === 1 || i === this.pagination.pages || Math.abs(i - this.currentPage) <= 2) {
          html += `<li class="page-item ${i===this.currentPage?'active':''}"><a class="page-link" data-page="${i}">${i}</a></li>`;
        } else if (Math.abs(i - this.currentPage) === 3) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
      }
      if (this.currentPage < this.pagination.pages) html += `<li class="page-item"><a class="page-link" data-page="${this.currentPage+1}">&raquo;</a></li>`;
      nav.innerHTML = html;
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
        const modal = new bootstrap.Modal(document.getElementById("annViewModal"));
        document.getElementById("annViewTitle").textContent = a.title;
        document.getElementById("annViewBody").innerHTML = `
          <div class="mb-3"><span class="badge bg-secondary me-1">${this.esc(a.announcement_type)}</span><span class="badge bg-primary me-1">${this.esc(a.priority)}</span><span class="badge bg-success me-1">${this.esc(a.status)}</span></div>
          <p><strong>Target:</strong> ${this.esc(a.target_audience)} &bull; <strong>Views:</strong> ${a.view_count ?? 0}</p>
          <div class="p-3 bg-light rounded mb-3" style="white-space:pre-wrap">${this.esc(a.content)}</div>
          <small class="text-muted">Created: ${this.esc(a.created_at || '—')} | Updated: ${this.esc(a.updated_at || '—')}</small>`;
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
      if (!confirm("Delete this announcement permanently?")) return;
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

    esc(s) { const d = document.createElement("div"); d.textContent = s; return d.innerHTML; },
  };
  window.AnnouncementsController = AnnouncementsController;
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", () => AnnouncementsController.init());
  else AnnouncementsController.init();
})();
