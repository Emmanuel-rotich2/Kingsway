/**
 * Manage SMS Page Controller
 * Handles listing, filtering, and composing SMS communications.
 */

(function () {
  "use strict";

  const ManageSmsController = {
    data: [],
    filtered: [],
    currentPage: 1,
    perPage: 10,
    smsModal: null,

    init: async function () {
      if (!this.pageExists()) return;

      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = "/Kingsway/index.php";
        return;
      }

      this.smsModal = new bootstrap.Modal(document.getElementById("smsModal"));
      this.bindEvents();
      this.updateCharMetrics();
      await this.loadData();
    },

    pageExists: function () {
      return !!document.getElementById("smsTable");
    },

    bindEvents: function () {
      const self = this;

      this.on("composeSMSBtn", "click", function () {
        self.openComposeModal();
      });

      this.on("sendSMSBtn", "click", async function () {
        await self.submitSMS();
      });

      this.on("checkBalanceBtn", "click", async function () {
        await self.checkBalance();
      });

      this.on("recipient_type", "change", function () {
        self.toggleRecipientSections(this.value);
      });

      this.on("sms_message", "input", function () {
        self.updateCharMetrics();
      });

      ["smsSearch", "statusFilter", "recipientTypeFilter", "dateFilter"].forEach(function (id) {
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
        const response = await window.API.communications.getMessages({ type: "sms" });
        this.data = this.unwrapList(response, ["items", "communications", "messages"]);
      } catch (error) {
        console.error("Failed to load SMS records:", error);
        this.data = [];
        this.notify("Failed to load SMS records", "error");
      }

      this.applyFilters();
    },

    applyFilters: function () {
      const search = this.value("smsSearch").toLowerCase();
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
      let failed = 0;

      this.data.forEach((item) => {
        const status = String(item.status || "").toLowerCase();
        const createdDate = String(item.created_at || "").split(" ")[0];

        if (status === "sent" && createdDate === today) sentToday += 1;
        if (status === "pending" || status === "draft" || status === "scheduled") pending += 1;
        if (status === "failed") failed += 1;
      });

      this.setText("sentToday", sentToday);
      this.setText("pendingSMS", pending);
      this.setText("failedSMS", failed);
      if (!document.getElementById("smsBalance").textContent.trim()) {
        this.setText("smsBalance", "0");
      }
    },

    renderTable: function () {
      const tbody = document.querySelector("#smsTable tbody");
      if (!tbody) return;

      if (this.filtered.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="7" class="text-center text-muted py-4">No SMS records found.</td></tr>';
        return;
      }

      const start = (this.currentPage - 1) * this.perPage;
      const pageRows = this.filtered.slice(start, start + this.perPage);

      tbody.innerHTML = pageRows
        .map((row, index) => {
          const rowIndex = start + index;
          const message = String(row.content || row.message || "");
          const preview = message.length > 70 ? `${message.substring(0, 70)}...` : message;
          const recipientType = row.recipient_type || row.audience || "-";
          const recipients = row.recipient_summary || row.recipient || "-";
          const cost = this.formatCurrency(this.estimateCost(message));

          return `
            <tr>
              <td>${this.formatDate(row.created_at || row.scheduled_at || row.updated_at)}</td>
              <td>${this.escapeHtml(recipientType)}</td>
              <td>${this.escapeHtml(recipients)}</td>
              <td>${this.escapeHtml(preview)}</td>
              <td>${this.statusBadge(row.status)}</td>
              <td>KES ${cost}</td>
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
          self.viewSMS(idx);
        });
      });
    },

    renderPagination: function () {
      const container = document.getElementById("smsPagination");
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
      const form = document.getElementById("smsForm");
      if (form) form.reset();
      this.toggleRecipientSections("");
      this.updateCharMetrics();
      this.smsModal.show();
    },

    toggleRecipientSections: function (recipientType) {
      const specificRecipientsDiv = document.getElementById("specificRecipientsDiv");
      const customNumbersDiv = document.getElementById("customNumbersDiv");

      if (specificRecipientsDiv) {
        const showSpecific =
          recipientType === "specific_class" || recipientType === "specific_students";
        specificRecipientsDiv.style.display = showSpecific ? "block" : "none";
      }

      if (customNumbersDiv) {
        customNumbersDiv.style.display = recipientType === "custom_numbers" ? "block" : "none";
      }
    },

    updateCharMetrics: function () {
      const message = this.value("sms_message");
      const length = message.length;
      const smsCount = length === 0 ? 0 : Math.ceil(length / 160);
      const cost = this.estimateCost(message);

      this.setText("charCount", length);
      this.setText("smsCount", smsCount);
      this.setText("estimatedCost", this.formatCurrency(cost));
    },

    estimateCost: function (message) {
      const smsCount = message.length === 0 ? 0 : Math.ceil(message.length / 160);
      const costPerSms = 0.8;
      return smsCount * costPerSms;
    },

    submitSMS: async function () {
      const recipientType = this.value("recipient_type");
      const message = this.value("sms_message").trim();
      const scheduleTime = this.value("schedule_time");

      if (!recipientType || !message) {
        this.notify("Recipient type and message are required", "warning");
        return;
      }

      const payload = {
        type: "sms",
        channel: "sms",
        subject: "SMS Broadcast",
        message,
        status: scheduleTime ? "scheduled" : "sent",
        recipient_type: recipientType,
        scheduled_at: scheduleTime || null,
      };

      if (recipientType === "custom_numbers") {
        payload.recipient_summary = this.value("custom_numbers");
      } else {
        payload.recipient_summary = this.value("specific_recipients");
      }

      try {
        await window.API.communications.createCommunication(payload);
        this.smsModal.hide();
        this.notify("SMS queued successfully", "success");
        await this.loadData();
      } catch (error) {
        console.error("Failed to submit SMS:", error);
        this.notify(error.message || "Failed to submit SMS", "error");
      }
    },

    checkBalance: async function () {
      try {
        const response = await window.API.communications.index();
        const balance =
          (response && response.sms_balance) ||
          (response && response.data && response.data.sms_balance) ||
          0;
        this.setText("smsBalance", balance);
        this.notify("SMS balance refreshed", "success");
      } catch (error) {
        console.error("Failed to fetch SMS balance:", error);
        this.notify("Unable to fetch SMS balance", "warning");
      }
    },

    viewSMS: function (index) {
      const item = this.filtered[index];
      if (!item) return;

      const message = item.content || item.message || "No message";
      const recipient = item.recipient_summary || item.recipient_type || "-";
      const status = item.status || "unknown";

      alert(`Recipients: ${recipient}\nStatus: ${status}\n\n${String(message).substring(0, 1600)}`);
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

    formatCurrency: function (value) {
      return Number(value || 0).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
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

  window.ManageSmsController = ManageSmsController;
  document.addEventListener("DOMContentLoaded", function () {
    ManageSmsController.init();
  });
})();
