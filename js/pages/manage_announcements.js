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

    init: async function () {
      if (!this.pageExists()) return;

      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = "/Kingsway/index.php";
        return;
      }

      this.announcementModal = new bootstrap.Modal(document.getElementById("announcementModal"));
      this.bindEvents();
      await this.loadData();
    },

    pageExists: function () {
      return !!document.getElementById("announcementsList");
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
              <div class="card h-100 border-${this.priorityBorder(item.priority)}">
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
      this.setValue("priority", item ? item.priority : "normal");
      this.setValue("audience", item ? item.audience : "all");
      this.setValue("publish_date", item ? this.toDateTimeLocal(item.publish_date) : "");
      this.setValue("expiry_date", item ? this.toDateTimeLocal(item.expiry_date) : "");

      const sendToggle = document.getElementById("send_notification");
      if (sendToggle) sendToggle.checked = !!(item && item.send_notification);

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
        priority: this.value("priority") || "normal",
      };

      const publishDate = metadata.publish_date;
      const status = publishDate ? "scheduled" : requestedStatus;

      const payload = {
        type: "notification",
        subject: title,
        content: JSON.stringify(metadata),
        status: status === "published" ? "sent" : status,
        priority: metadata.priority,
        scheduled_at: publishDate || null,
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

      alert(
        `Title: ${item.subject}\nStatus: ${item.status}\nAudience: ${item.audience}\nCategory: ${item.category}\n\n${item.body}`
      );
    },

    remove: async function (index) {
      const item = this.filtered[index];
      if (!item) return;
      if (!confirm("Delete this announcement?")) return;

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

      const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.download = "announcements.csv";
      link.click();
      URL.revokeObjectURL(link.href);
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
