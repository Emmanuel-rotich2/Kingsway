/**
 * Manage Email Page Controller
 * Handles listing, filtering, and composing email communications.
 */

(function () {
  "use strict";

  const ManageEmailController = {
    data: [],
    filtered: [],
    currentPage: 1,
    perPage: 10,
    emailModal: null,

    init: async function () {
      if (!this.pageExists()) {
        return;
      }

      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = "/Kingsway/index.php";
        return;
      }

      this.emailModal = new bootstrap.Modal(document.getElementById("emailModal"));
      this.bindEvents();
      await this.loadData();
    },

    pageExists: function () {
      return !!document.getElementById("emailTable");
    },

    bindEvents: function () {
      const self = this;

      this.on("composeEmailBtn", "click", function () {
        self.openComposeModal();
      });

      this.on("emailTemplatesBtn", "click", async function () {
        await self.loadTemplates();
      });

      this.on("sendEmailBtn", "click", async function () {
        await self.submitEmail("sent");
      });

      this.on("saveDraftBtn", "click", async function () {
        await self.submitEmail("draft");
      });

      this.on("recipient_type", "change", function () {
        self.toggleRecipientSections(this.value);
      });

      ["emailSearch", "statusFilter", "recipientTypeFilter", "dateFilter"].forEach(function (id) {
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
        const response = await window.API.communications.getMessages({ type: "email" });
        this.data = this.unwrapList(response, ["items", "communications", "messages"]);
      } catch (error) {
        console.error("Failed to load emails:", error);
        this.data = [];
        this.notify("Failed to load email records", "error");
      }

      this.applyFilters();
    },

    applyFilters: function () {
      const search = this.value("emailSearch").toLowerCase();
      const status = this.value("statusFilter").toLowerCase();
      const recipientType = this.value("recipientTypeFilter").toLowerCase();
      const dateFilter = this.value("dateFilter");

      this.filtered = this.data.filter((item) => {
        const itemStatus = String(item.status || "").toLowerCase();
        const itemRecipientType = String(
          item.recipient_type || item.audience || item.recipient_group || ""
        ).toLowerCase();
        const created = item.created_at || item.scheduled_at || "";
        const createdDate = created ? String(created).split(" ")[0] : "";

        if (status && itemStatus !== status) return false;
        if (recipientType && itemRecipientType !== recipientType) return false;
        if (dateFilter && createdDate !== dateFilter) return false;

        if (search) {
          const haystack = [
            item.subject,
            item.content,
            item.recipient_type,
            item.recipient_summary,
            item.status,
          ]
            .join(" ")
            .toLowerCase();
          if (!haystack.includes(search)) return false;
        }

        return true;
      });

      this.renderStats();
      this.renderTable();
      this.renderPagination();
    },

    renderStats: function () {
      const today = new Date().toISOString().split("T")[0];
      let sentToday = 0;
      let pending = 0;
      let scheduled = 0;
      let failed = 0;

      this.data.forEach((item) => {
        const status = String(item.status || "").toLowerCase();
        const createdDate = String(item.created_at || "").split(" ")[0];
        if (status === "sent" && createdDate === today) sentToday += 1;
        if (status === "pending" || status === "draft") pending += 1;
        if (status === "scheduled") scheduled += 1;
        if (status === "failed") failed += 1;
      });

      this.setText("sentToday", sentToday);
      this.setText("pendingEmails", pending);
      this.setText("scheduledEmails", scheduled);
      this.setText("failedEmails", failed);
    },

    renderTable: function () {
      const tbody = document.querySelector("#emailTable tbody");
      if (!tbody) return;

      if (this.filtered.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="5" class="text-center text-muted py-4">No email records found.</td></tr>';
        return;
      }

      const start = (this.currentPage - 1) * this.perPage;
      const pageRows = this.filtered.slice(start, start + this.perPage);

      tbody.innerHTML = pageRows
        .map((row, index) => {
          const rowIndex = start + index;
          const recipients = this.escapeHtml(
            row.recipient_summary || row.recipient_type || row.audience || "-"
          );
          const subject = this.escapeHtml(row.subject || "(No Subject)");
          const dateValue = row.created_at || row.scheduled_at || row.updated_at;
          const dateText = dateValue ? this.formatDate(dateValue) : "-";

          return `
            <tr>
              <td>${dateText}</td>
              <td>${recipients}</td>
              <td>${subject}</td>
              <td>${this.statusBadge(row.status)}</td>
              <td>
                <button class="btn btn-sm btn-outline-primary" data-action="view" data-index="${rowIndex}">
                  <i class="bi bi-eye"></i>
                </button>
              </td>
            </tr>
          `;
        })
        .join("");

      const self = this;
      tbody.querySelectorAll("button[data-action='view']").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const idx = Number(this.getAttribute("data-index"));
          self.viewEmail(idx);
        });
      });
    },

    renderPagination: function () {
      const container = document.getElementById("emailPagination");
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
          self.renderTable();
          self.renderPagination();
        });
      });
    },

    openComposeModal: function () {
      const form = document.getElementById("emailForm");
      if (form) form.reset();
      this.toggleRecipientSections("");
      this.emailModal.show();
    },

    loadTemplates: async function () {
      try {
        const response = await window.API.communications.getTemplates();
        const templates = this.unwrapList(response, ["templates", "items"]);
        if (!templates.length) {
          this.notify("No saved templates found", "info");
          return;
        }

        const select = document.getElementById("email_template");
        if (!select) return;

        const existing = new Set(
          Array.from(select.options).map((option) => String(option.value))
        );

        templates.forEach((template) => {
          const value = String(template.id || "");
          if (!value || existing.has(value)) return;
          const option = document.createElement("option");
          option.value = value;
          option.textContent = template.name || `Template ${value}`;
          select.appendChild(option);
        });

        this.notify("Templates loaded", "success");
      } catch (error) {
        console.error("Failed to load templates:", error);
        this.notify("Failed to load templates", "error");
      }
    },

    toggleRecipientSections: function (recipientType) {
      const specificRecipientsDiv = document.getElementById("specificRecipientsDiv");
      const customEmailsDiv = document.getElementById("customEmailsDiv");

      if (specificRecipientsDiv) {
        const showSpecific =
          recipientType === "specific_class" || recipientType === "specific_students";
        specificRecipientsDiv.style.display = showSpecific ? "block" : "none";
      }

      if (customEmailsDiv) {
        customEmailsDiv.style.display = recipientType === "custom_emails" ? "block" : "none";
      }
    },

    submitEmail: async function (requestedStatus) {
      const subject = this.value("email_subject").trim();
      const message = this.value("email_message").trim();
      const recipientType = this.value("recipient_type");

      if (!recipientType || !subject || !message) {
        this.notify("Recipient type, subject, and message are required", "warning");
        return;
      }

      const scheduleTime = this.value("schedule_time");
      const status = scheduleTime ? "scheduled" : requestedStatus;
      const priority = this.value("email_priority") || "normal";
      const templateId = this.value("email_template");

      const payload = {
        type: "email",
        channel: "email",
        subject,
        message,
        status,
        priority,
        recipient_type: recipientType,
        scheduled_at: scheduleTime || null,
        template_id: templateId || null,
      };

      if (recipientType === "custom_emails") {
        payload.recipient_summary = this.value("custom_emails");
      } else {
        payload.recipient_summary = this.value("specific_recipients");
      }

      try {
        const created = await window.API.communications.createCommunication(payload);
        const communicationId = this.extractId(created);
        await this.uploadAttachments(communicationId);

        this.emailModal.hide();
        this.notify(status === "draft" ? "Draft saved" : "Email queued successfully", "success");
        await this.loadData();
      } catch (error) {
        console.error("Failed to submit email:", error);
        this.notify(error.message || "Failed to submit email", "error");
      }
    },

    uploadAttachments: async function (communicationId) {
      const input = document.getElementById("email_attachments");
      if (!input || !input.files || !input.files.length || !communicationId) return;

      const uploads = [];
      for (let i = 0; i < input.files.length; i += 1) {
        const formData = new FormData();
        formData.append("communication_id", communicationId);
        formData.append("file", input.files[i]);
        formData.append("file_name", input.files[i].name);
        uploads.push(window.API.communications.createAttachment(formData));
      }

      await Promise.allSettled(uploads);
    },

    viewEmail: function (index) {
      const item = this.filtered[index];
      if (!item) return;

      const subject = item.subject || "(No Subject)";
      const content = item.content || item.message || "No content";
      const recipient = item.recipient_summary || item.recipient_type || "-";
      const status = item.status || "unknown";

      alert(
        `Subject: ${subject}\nRecipients: ${recipient}\nStatus: ${status}\n\n${String(content).substring(0, 1600)}`
      );
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

    extractId: function (response) {
      if (!response) return null;
      return (
        response.id ||
        response.communication_id ||
        (response.data && (response.data.id || response.data.communication_id)) ||
        null
      );
    },

    formatDate: function (value) {
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

    statusBadge: function (status) {
      const normalized = String(status || "").toLowerCase();
      const classes = {
        sent: "bg-success",
        delivered: "bg-success",
        draft: "bg-secondary",
        pending: "bg-warning text-dark",
        scheduled: "bg-info text-dark",
        failed: "bg-danger",
      };
      const css = classes[normalized] || "bg-light text-dark border";
      const text = normalized ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : "Unknown";
      return `<span class="badge ${css}">${this.escapeHtml(text)}</span>`;
    },

    value: function (id) {
      const el = document.getElementById(id);
      return el ? String(el.value || "") : "";
    },

    setText: function (id, value) {
      const el = document.getElementById(id);
      if (el) el.textContent = String(value);
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

  window.ManageEmailController = ManageEmailController;
  document.addEventListener("DOMContentLoaded", function () {
    ManageEmailController.init();
  });
})();
