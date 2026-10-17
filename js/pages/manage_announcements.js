/**
 * Manage Announcements Page Controller
 * Handles CRUD for notification-type communications used as announcements.
 */

(function () {
  "use strict";

  const ManageAnnouncementsController = {
    data: [],
    filtered: [],
    currentPage: 1,
    perPage: 9,
    announcementModal: null,
    audiences: { parents: [], students: [], classes: [], student_types: [], school_levels: [], vendors: [], staff: [] },

    init: async function () {
      if (!this.pageExists()) return;

      await window.AuthContext?.ready();
      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }

      if (
        typeof PageShell !== "undefined" &&
        !PageShell.hasAny(["communications_view", "announcements_view", "communications_manage"])
      ) {
        document.getElementById("announcementsList").innerHTML =
          '<div class="alert alert-warning border-0 shadow-sm">' +
          '<i class="bi bi-shield-lock me-2"></i>' +
          "You do not have permission to view announcements." +
          "</div>";
        return;
      }

      this.announcementModal = new bootstrap.Modal(document.getElementById("announcementModal"));
      this.bindEvents();
      this.applyPermissionGates();
      await Promise.all([this.loadData(), this.loadAudienceOptions()]);
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
          window.RecipientPicker.insertVariable("content", this.getAttribute("data-var"));
        });
      });
    },

    pageExists: function () {
      return !!document.getElementById("announcementsList");
    },

    on: function (id, event, fn) {
      const el = document.getElementById(id);
      if (el) el.addEventListener(event, fn);
    },

    bindEvents: function () {
      const self = this;

      this.on("createAnnouncementBtn", "click", function () {
        self.openModal();
      });

      this.on("publishAnnouncementBtn", "click", async function () {
        await self.saveAnnouncement("sent");
      });

      this.on("saveDraftBtn", "click", async function () {
        await self.saveAnnouncement("draft");
      });

      this.on("exportAnnouncementsBtn", "click", function () {
        self.exportCSV();
      });

      this.on("printAnnouncementsBtn", "click", function () {
        self.printAnnouncements();
      });

      this.on("audience", "change", function () {
        self.toggleAudiencePicker(this.value);
      });

      ["announcementSearch", "statusFilter", "categoryFilter", "audienceFilter"].forEach(function (id) {
        self.on(id, "input", function () {
          self.currentPage = 1;
          self.applyFilters();
        });
        self.on(id, "change", function () {
          self.currentPage = 1;
          self.applyFilters();
        });
      });
    },

    applyPermissionGates: function () {
      const canExport = window.AuthContext?.canExport ? AuthContext.canExport("communications") : true;
      const canPrint = window.AuthContext?.canPrint ? AuthContext.canPrint("communications") : true;
      const ex = document.getElementById("exportAnnouncementsBtn");
      const pr = document.getElementById("printAnnouncementsBtn");
      if (ex && !canExport) ex.style.display = "none";
      if (pr && !canPrint) pr.style.display = "none";
    },

    loadAudienceOptions: async function () {
      try {
        const response = await window.API.communications.getAudienceOptions();
        this.audiences = response?.data || response || this.audiences;
      } catch (error) {
        console.error("Failed to load audience options:", error);
      }
    },

    toggleAudiencePicker: function (value) {
      const div = document.getElementById("audienceSpecificDiv");
      if (div) div.classList.toggle("d-none", value !== "specific");
      if (!this.audiencePicker && window.RecipientPicker) {
        this.audiencePicker = window.RecipientPicker.create("audiencePicker", {
          mode: "multi",
          placeholder: "Search learner, parent, staff, class or vendor…",
        });
      }
    },

    loadData: async function () {
      try {
        const response = await window.API.communications.getMessages({ type: "notification" });
        this.data = this.unwrapList(response, ["items", "communications", "messages"]).map(
          (item) => this.normalize(item)
        );
      } catch (error) {
        console.error("Failed to load announcements:", error);
        this.data = [];
        this.notify("Failed to load announcements", "error");
      }

      this.applyFilters();
    },

    normalize: function (item) {
      let parsedMeta = {};

      if (typeof item.content === "string" && item.content.trim().startsWith("{")) {
        try {
          parsedMeta = JSON.parse(item.content);
        } catch (e) {
          parsedMeta = {};
        }
      }

      const statusRaw = String(item.status || "").toLowerCase();
      const status = statusRaw === "sent" ? "published" : statusRaw || "draft";

      return {
        id: item.id,
        subject: item.subject || item.title || "(Untitled)",
        body:
          parsedMeta.body ||
          (typeof item.content === "string" ? item.content : item.message || ""),
        status,
        category: parsedMeta.category || item.category || "general",
        audience: parsedMeta.audience || item.audience || "all",
        audience_ids: parsedMeta.audience_ids || [],
        audience_labels: parsedMeta.audience_labels || [],
        priority: item.priority || parsedMeta.priority || "normal",
        publish_date: item.scheduled_at || parsedMeta.publish_date || item.created_at,
        expiry_date: parsedMeta.expiry_date || item.expiry_date || "",
        send_notification: !!parsedMeta.send_notification,
        created_at: item.created_at,
        raw: item,
      };
    },

    applyFilters: function () {
      const search = this.value("announcementSearch").toLowerCase();
      const status = this.value("statusFilter").toLowerCase();
      const category = this.value("categoryFilter").toLowerCase();
      const audience = this.value("audienceFilter").toLowerCase();

      this.filtered = this.data.filter((item) => {
        if (status && item.status !== status) return false;
        if (category && String(item.category).toLowerCase() !== category) return false;
        if (audience && String(item.audience).toLowerCase() !== audience) return false;

        if (search) {
          const hay = `${item.subject} ${item.body} ${item.category} ${item.audience}`.toLowerCase();
          if (!hay.includes(search)) return false;
        }

        return true;
      });

      this.renderStats();
      this.renderCards();
      this.renderPagination();
    },

    renderStats: function () {
      const total = this.data.length;
      const published = this.data.filter((x) => x.status === "published").length;
      const draft = this.data.filter((x) => x.status === "draft" || x.status === "pending").length;
      const scheduled = this.data.filter((x) => x.status === "scheduled").length;

      this.setText("totalAnnouncements", total);
      this.setText("publishedAnnouncements", published);
      this.setText("draftAnnouncements", draft);
      this.setText("scheduledAnnouncements", scheduled);
    },

    renderCards: function () {
      const container = document.getElementById("announcementsList");
      if (!container) return;

      if (this.filtered.length === 0) {
        container.innerHTML =
          '<div class="col-12"><div class="alert alert-info">No announcements found.</div></div>';
        return;
      }

      const start = (this.currentPage - 1) * this.perPage;
      const pageItems = this.filtered.slice(start, start + this.perPage);

      container.innerHTML = pageItems
        .map((item, i) => {
          const index = start + i;
          const preview = item.body.length > 150 ? `${item.body.substring(0, 150)}...` : item.body;

          return `
            <div class="col-md-6 col-lg-4 mb-3">
              <div class="card app-ann-card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="card-title mb-0">${this.escapeHtml(item.subject)}</h6>
                    ${this.statusBadge(item.status)}
                  </div>
                  <div class="mb-2 text-muted small">
                    <span class="me-2"><i class="bi bi-tag"></i> ${this.escapeHtml(item.category)}</span>
                    <span><i class="bi bi-people"></i> ${this.escapeHtml(item.audience)}</span>
                  </div>
                  <p class="card-text flex-grow-1">${this.escapeHtml(preview)}</p>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted">${this.formatDate(item.publish_date || item.created_at)}</small>
                    <div class="btn-group btn-group-sm">
                      <button class="btn btn-outline-primary" data-action="view" data-index="${index}">
                        <i class="bi bi-eye"></i>
                      </button>
                      <button class="btn btn-outline-warning" data-action="edit" data-index="${index}">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-outline-danger" data-action="delete" data-index="${index}">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          `;
        })
        .join("");

      const self = this;
      container.querySelectorAll("button[data-action]").forEach(function (btn) {
        btn.addEventListener("click", async function () {
          const action = this.getAttribute("data-action");
          const index = Number(this.getAttribute("data-index"));
          if (action === "view") self.view(index);
          if (action === "edit") self.openModal(self.filtered[index]);
          if (action === "delete") await self.remove(index);
        });
      });
    },

    renderPagination: function () {
      const container = document.getElementById("announcementsPagination");
      if (!container) return;

      const totalPages = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
      this.currentPage = Math.min(this.currentPage, totalPages);

      container.innerHTML = `
        <li class="page-item ${this.currentPage === 1 ? "disabled" : ""}">
          <button class="page-link" data-page="prev">Previous</button>
        </li>
        <li class="page-item disabled"><span class="page-link">${this.currentPage} / ${totalPages}</span></li>
        <li class="page-item ${this.currentPage === totalPages ? "disabled" : ""}">
          <button class="page-link" data-page="next">Next</button>
        </li>
      `;

      const self = this;
      container.querySelectorAll("button[data-page]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const dir = this.getAttribute("data-page");
          if (dir === "prev" && self.currentPage > 1) self.currentPage -= 1;
          if (dir === "next" && self.currentPage < totalPages) self.currentPage += 1;
          self.renderCards();
          self.renderPagination();
        });
      });
    },

    openModal: function (item) {
      const form = document.getElementById("announcementForm");
      if (form) form.reset();

      this.setValue("announcement_id", item ? item.id : "");
      this.setValue("title", item ? item.subject : "");
      this.setValue("content", item ? item.body : "");
      this.setValue("category", item ? item.category : "general");
      this.setValue("priority", item ? item.priority : "medium");
      this.setValue("audience", item ? item.audience : "all");
      this.setValue("publish_date", item ? this.toDateTimeLocal(item.publish_date) : "");
      this.setValue("expiry_date", item ? this.toDateTimeLocal(item.expiry_date) : "");

      const sendToggle = document.getElementById("send_notification");
      if (sendToggle) sendToggle.checked = !!(item && item.send_notification);

      const audience = item ? item.audience : "all";
      this.toggleAudiencePicker(audience);
      if (this.audiencePicker) {
        const selected = item && item.audience === "specific" && Array.isArray(item.audience_ids)
          ? item.audience_ids.map((token, i) => {
              const [type, id] = String(token).split(":");
              return { type, id, label: (item.audience_labels || [])[i] || token };
            })
          : [];
        this.audiencePicker.setSelection(selected);
      }

      this.announcementModal.show();
    },

    saveAnnouncement: async function (requestedStatus) {
      const id = this.value("announcement_id");
      const title = this.value("title").trim();
      const body = this.value("content").trim();

      if (!title || !body) {
        this.notify("Title and content are required", "warning");
        return;
      }

      const metadata = {
        body,
        category: this.value("category") || "general",
        audience: this.value("audience") || "all",
        publish_date: this.value("publish_date") || null,
        expiry_date: this.value("expiry_date") || null,
        send_notification: !!document.getElementById("send_notification")?.checked,
        priority: this.value("priority") || "medium",
      };

      const publishDate = metadata.publish_date;
      let status = requestedStatus;
      if (publishDate) {
        const when = new Date(publishDate);
        const isFuture = !Number.isNaN(when.getTime()) && when.getTime() > Date.now() + 60000;
        status = isFuture ? "scheduled" : requestedStatus;
      }

      const audience = metadata.audience;
      if (audience === "specific") {
        const picked = this.audiencePicker ? this.audiencePicker.getSelection() : [];
        metadata.audience_ids = picked.map((r) => r.type + ":" + r.id);
        metadata.audience_labels = picked.map((r) => r.label);
        if (!picked.length) {
          this.notify("Select at least one specific audience entry", "warning");
          return;
        }
      }

      const payload = {
        type: "notification",
        subject: title,
        content: JSON.stringify(metadata),
        status,
        priority: metadata.priority,
        scheduled_at: status === "scheduled" ? publishDate : null,
      };

      try {
        if (id) {
          await window.API.communications.updateCommunication(id, payload);
        } else {
          await window.API.communications.createCommunication(payload);
        }

        this.announcementModal.hide();
        this.notify("Announcement saved", "success");
        await this.loadData();
      } catch (error) {
        console.error("Failed to save announcement:", error);
        this.notify(error.message || "Failed to save announcement", "error");
      }
    },

    view: function (index) {
      const item = this.filtered[index];
      if (!item) return;

      const titleEl = document.getElementById("viewAnnouncementTitle");
      const metaEl = document.getElementById("viewAnnouncementMeta");
      const contentEl = document.getElementById("viewAnnouncementContent");
      if (!titleEl || !metaEl || !contentEl) return;

      titleEl.innerHTML = `${this.statusBadge(item.status)} ${this.escapeHtml(item.subject)}`;
      metaEl.innerHTML = `
        <span class="badge rounded-pill bg-light text-dark border"><i class="bi bi-tag me-1"></i>${this.escapeHtml(item.category)}</span>
        <span class="badge rounded-pill bg-light text-dark border"><i class="bi bi-people me-1"></i>${this.escapeHtml(item.audience)}</span>
        <span class="badge rounded-pill ${item.priority === "urgent" ? "bg-danger" : item.priority === "high" ? "bg-warning text-dark" : "bg-light text-dark border"}"><i class="bi bi-flag me-1"></i>${this.escapeHtml(item.priority)}</span>
        <span class="badge rounded-pill bg-light text-dark border"><i class="bi bi-calendar-event me-1"></i>${this.formatDate(item.publish_date || item.created_at)}</span>
      `;
      contentEl.innerHTML = this.escapeHtml(item.body).replace(/\n/g, "<br>");
      new bootstrap.Modal(document.getElementById("viewAnnouncementModal")).show();
    },

    remove: async function (index) {
      const item = this.filtered[index];
      if (!item) return;
      if (!(await window.confirmAction('Confirm Deletion', "Delete this announcement?", { confirmText: 'Delete', danger: true }))) return;

      try {
        await window.API.communications.deleteCommunication(item.id);
        this.notify("Announcement deleted", "success");
        await this.loadData();
      } catch (error) {
        console.error("Failed to delete announcement:", error);
        this.notify(error.message || "Failed to delete announcement", "error");
      }
    },

    exportCSV: function () {
      if (window.AuthContext && typeof window.AuthContext.canExport === "function" && !window.AuthContext.canExport("communications")) {
        this.notify("You do not have permission to export announcements", "warning");
        return;
      }
      if (!this.data.length) {
        this.notify("Nothing to export", "info");
        return;
      }

      const headers = ["Title", "Status", "Category", "Audience", "Priority", "Publish Date"];
      const rows = this.data.map((item) => [
        item.subject,
        item.status,
        item.category,
        item.audience,
        item.priority,
        item.publish_date || item.created_at || "",
      ]);

      const csv = [headers, ...rows]
        .map((row) => row.map((v) => `"${String(v || "").replace(/"/g, '""')}"`).join(","))
        .join("\n");

      KingswayFileLifecycle.exportText(csv, "announcements.csv", "text/csv;charset=utf-8;");
    },

    printAnnouncements: function () {
      if (window.AuthContext && typeof window.AuthContext.canPrint === "function" && !window.AuthContext.canPrint("communications")) {
        this.notify("You do not have permission to print announcements", "warning");
        return;
      }
      if (window.PrintManager && typeof window.PrintManager.printPage === "function") {
        window.PrintManager.printPage({ title: "Announcements", orientation: "landscape" });
      } else {
        window.print();
      }
    },

    priorityBorder: function (priority) {
      const key = String(priority || "normal").toLowerCase();
      if (key === "urgent") return "danger";
      if (key === "high") return "warning";
      return "secondary";
    },

    statusBadge: function (status) {
      const key = String(status || "").toLowerCase();
      const map = {
        published: "bg-success",
        sent: "bg-success",
        draft: "bg-secondary",
        pending: "bg-warning text-dark",
        scheduled: "bg-info text-dark",
      };
      const css = map[key] || "bg-light text-dark border";
      return `<span class="badge ${css}">${this.escapeHtml(key || "unknown")}</span>`;
    },

    toDateTimeLocal: function (value) {
      if (!value) return "";
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return "";
      const pad = (n) => String(n).padStart(2, "0");
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(
        date.getHours()
      )}:${pad(date.getMinutes())}`;
    },

    unwrapList: function (response, keys) {
      if (!response) return [];
      if (Array.isArray(response)) return response;
      if (response.data && Array.isArray(response.data)) return response.data;

      const allKeys = keys || [];
      for (let i = 0; i < allKeys.length; i += 1) {
        const key = allKeys[i];
        if (Array.isArray(response[key])) return response[key];
        if (response.data && Array.isArray(response.data[key])) return response.data[key];
      }
      return [];
    },

    value: function (id) {
      const el = document.getElementById(id);
      return el ? String(el.value || "") : "";
    },

    setValue: function (id, value) {
      const el = document.getElementById(id);
      if (el) el.value = value == null ? "" : String(value);
    },

    setText: function (id, value) {
      const el = document.getElementById(id);
      if (el) el.textContent = String(value);
    },

    formatDate: function (value) {
      if (!value) return "-";
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return this.escapeHtml(String(value));
      return date.toLocaleString("en-KE", {
        year: "numeric",
        month: "short",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      });
    },

    on: function (id, event, handler) {
      const el = document.getElementById(id);
      if (el) el.addEventListener(event, handler);
    },

    notify: function (message, type) {
      if (typeof showNotification === "function") {
        showNotification(message, type || "info");
      } else {
        console.log(`${type || "info"}: ${message}`);
      }
    },

    escapeHtml: function (value) {
      return String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
    },
  };

  window.ManageAnnouncementsController = ManageAnnouncementsController;
  document.addEventListener("DOMContentLoaded", function () {
    ManageAnnouncementsController.init();
  });
})();
