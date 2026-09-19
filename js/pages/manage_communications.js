/**
 * Manage Communications Hub Page Controller
 * Shows overview of all communication channels with summary stats and recent activity.
 */

(function () {
  "use strict";

  const CommunicationsHubController = {
    data: null,

    init: async function () {
      if (!this.pageExists()) return;

      await window.AuthContext?.ready();
      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }

      this.bindEvents();
      this.updateVisibilityHint();
      await this.loadStatistics();
      await this.loadTemplates();
      await this.loadAiDrafts();
    },

    updateVisibilityHint: function () {
      const user = window.AuthContext?.getUser?.() || {};
      const roles = [user.role_name, ...(Array.isArray(user.roles) ? user.roles.map(role => role?.name || role) : [])].filter(Boolean).map(role => String(role).toLowerCase());
      const isManagement = roles.some(role => ['system administrator', 'director', 'school administrator'].includes(role));
      const isHeadteacher = roles.includes('headteacher');
      const subtitle = document.querySelector('.app-page-subtitle');
      if (subtitle && !isManagement) subtitle.textContent = isHeadteacher
        ? 'Teacher-to-parent communications and your communication activity'
        : 'Your sent messages and communications where you are a recipient';
      const templateButton = document.getElementById('newTemplateBtn');
      if (templateButton && !isManagement) templateButton.title = 'Only your templates are shown here';
    },

    pageExists: function () {
      return !!document.getElementById("totalCommunications");
    },

    bindEvents: function () {
      const self = this;

      document.getElementById("refreshActivities")?.addEventListener("click", async function () {
        self.loadStatistics();
      });

      document.getElementById("composeMessageBtn")?.addEventListener("click", function () {
        window.location.href = (window.APP_BASE || "") + "/home.php?route=communications/messages_inbox";
      });

      document.getElementById("createInternalRequestBtn")?.addEventListener("click", async function () {
        const { default: Modal } = await import("bootstrap/dist/js/bootstrap.bundle.min.js");
        self.openInternalRequestModal();
      });
      document.getElementById('newTemplateBtn')?.addEventListener('click', () => this.openTemplate());
      document.getElementById('saveTemplateBtn')?.addEventListener('click', () => this.saveTemplate());
      document.getElementById('submitProviderTemplateBtn')?.addEventListener('click', () => this.submitProviderTemplate());
      document.getElementById('templateChannel')?.addEventListener('change', e => { document.getElementById('submitProviderTemplateBtn').classList.toggle('d-none', e.target.value !== 'whatsapp'); });
      document.getElementById('aiDraftMessageBtn')?.addEventListener('click', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('aiCommunicationDraftModal')).show();
      });
      document.getElementById('queueAiCommunicationDraft')?.addEventListener('click', () => this.queueAiDraft());
      document.getElementById('refreshAiCommunicationDrafts')?.addEventListener('click', () => this.loadAiDrafts());

      document.querySelectorAll(".channel-card").forEach(function (card) {
        card.addEventListener("mouseenter", function () {
          this.style.transform = "translateY(-2px)";
        });
        card.addEventListener("mouseleave", function () {
          this.style.transform = "";
        });
      });
    },

    async loadTemplates() {
      try { const r = await window.API.communications.getTemplates(); const rows = Array.isArray(r) ? r : (r?.data || []); const body = document.getElementById('templateManagerBody'); if (!body) return; body.innerHTML = rows.length ? rows.map(t => `<tr><td>${this.escapeHtml(t.name)}</td><td>${this.escapeHtml(t.template_type || t.type || 'sms')}</td><td>${this.escapeHtml(t.category || '—')}</td><td><button class="btn btn-sm btn-outline-primary me-1" data-template-edit="${t.id}">Edit</button><button class="btn btn-sm btn-outline-danger" data-template-delete="${t.id}">Delete</button></td></tr>`).join('') : '<tr><td colspan="4" class="text-muted">No templates created yet.</td></tr>'; body.querySelectorAll('[data-template-edit]').forEach(b => b.addEventListener('click', () => this.openTemplate(rows.find(t => String(t.id) === b.dataset.templateEdit)))); body.querySelectorAll('[data-template-delete]').forEach(b => b.addEventListener('click', () => this.deleteTemplate(b.dataset.templateDelete))); } catch (e) { console.error(e); }
    },

    openTemplate(t = null) { document.getElementById('templateId').value = t?.id || ''; document.getElementById('templateName').value = t?.name || ''; document.getElementById('templateChannel').value = t?.template_type || t?.type || 'sms'; document.getElementById('templateCategory').value = t?.category || ''; document.getElementById('templateBody').value = t?.template_body || t?.body || ''; document.getElementById('submitProviderTemplateBtn').classList.toggle('d-none', document.getElementById('templateChannel').value !== 'whatsapp'); new bootstrap.Modal(document.getElementById('templateManagerModal')).show(); },
    async submitProviderTemplate() { const name = document.getElementById('templateName').value.trim(); const body = document.getElementById('templateBody').value.trim(); const category = (document.getElementById('templateCategory').value.trim() || 'UTILITY').toUpperCase(); if (!name || !body) return this.showNotification('Name and body are required', 'warning'); try { const r = await window.API.communications.createWhatsappTemplate({ name, language: 'en', category: ['MARKETING','UTILITY','AUTHENTICATION'].includes(category) ? category : 'UTILITY', components: { body: { type: 'BODY', text: body } } }); this.showNotification(r?.message || 'Submitted for WhatsApp approval', 'success'); } catch (e) { this.showNotification(e.message || 'Provider template submission failed', 'error'); } },
    async saveTemplate() { const id = document.getElementById('templateId').value; const data = { name: document.getElementById('templateName').value.trim(), template_type: document.getElementById('templateChannel').value, category: document.getElementById('templateCategory').value.trim() || null, template_body: document.getElementById('templateBody').value }; if (!data.name || !data.template_body) return this.showNotification('Name and body are required', 'warning'); try { if (id) await window.API.communications.updateTemplate(id, data); else await window.API.communications.createTemplate(data); bootstrap.Modal.getInstance(document.getElementById('templateManagerModal'))?.hide(); this.showNotification('Template saved', 'success'); await this.loadTemplates(); } catch (e) { this.showNotification(e.message || 'Template save failed', 'error'); } },
    async deleteTemplate(id) { if (!(await window.confirmAction('Delete template', 'Delete this communication template?'))) return; try { await window.API.communications.deleteTemplate(id); this.showNotification('Template deleted', 'success'); await this.loadTemplates(); } catch (e) { this.showNotification(e.message || 'Template deletion failed', 'error'); } },

    async queueAiDraft() {
      const form = document.getElementById('aiCommunicationDraftForm');
      const button = document.getElementById('queueAiCommunicationDraft');
      if (!form || !form.reportValidity()) return;
      const data = Object.fromEntries(new FormData(form).entries());
      if (button) button.disabled = true;
      try {
        const queued = await window.API.apiCall('/communications/ai-message-draft-queue', 'POST', data);
        bootstrap.Modal.getInstance(document.getElementById('aiCommunicationDraftModal'))?.hide();
        this.showNotification(`Communication draft queued${queued?.job_id ? ` (job ${queued.job_id})` : ''}.`, 'info');
        for (let attempt = 0; attempt < 8; attempt += 1) {
          await new Promise((resolve) => setTimeout(resolve, attempt === 0 ? 1000 : 2500));
          await this.loadAiDrafts();
        }
      } catch (error) {
        this.showNotification(error.message || 'Unable to queue communication draft', 'error');
      } finally {
        if (button) button.disabled = false;
      }
    },

    async loadAiDrafts() {
      const container = document.getElementById('aiCommunicationDrafts');
      if (!container) return;
      try {
        const own = await window.API.apiCall('/communications/ai-drafts?scope=own', 'GET');
        let review = { drafts: [] };
        try { review = await window.API.apiCall('/communications/ai-drafts?scope=review', 'GET'); } catch (_) {}
        const drafts = [
          ...(Array.isArray(own?.drafts) ? own.drafts : []).map((draft) => ({ ...draft, __review: false })),
          ...(Array.isArray(review?.drafts) ? review.drafts : []).map((draft) => ({ ...draft, __review: true }))
        ];
        if (!drafts.length) {
          container.innerHTML = '<div class="col-12 text-muted small">No assistant drafts available.</div>';
          return;
        }
        container.innerHTML = drafts.map((draft) => {
          const generated = draft.draft || {};
          const metadata = draft.metadata || {};
          const canApprove = draft.__review && draft.status === 'pending_approval';
          const materialized = metadata.materialized_communication_id;
          return `<div class="col-12 col-lg-6"><article class="card h-100 border-0 shadow-sm" data-ai-draft-status="${this.escapeHtml(draft.status || '')}">
            <div class="card-body">
              <div class="d-flex justify-content-between gap-2 mb-2"><h6 class="mb-0">${this.escapeHtml(generated.title || 'Communication draft')}</h6><span class="badge text-bg-secondary">${this.escapeHtml(draft.status || 'queued')}</span></div>
              <div class="small text-muted mb-2">${this.escapeHtml(metadata.channel || 'channel')} · ${this.escapeHtml(metadata.audience || 'audience')}</div>
              <p class="small">${this.escapeHtml(generated.body || 'Draft is still being generated.')}</p>
              ${Array.isArray(generated.next_steps) && generated.next_steps.length ? `<ul class="small">${generated.next_steps.map((step) => `<li>${this.escapeHtml(step)}</li>`).join('')}</ul>` : ''}
              ${materialized ? `<div class="alert alert-success small mb-0">Saved to communications drafts (#${this.escapeHtml(materialized)}). Select recipients and dispatch through the normal workflow.</div>` : ''}
              ${canApprove ? `<button class="btn btn-sm btn-success" onclick="CommunicationsHubController.approveAiDraft(${Number(draft.id)})">Approve into communications drafts</button>` : ''}
            </div>
          </article></div>`;
        }).join('');
      } catch (error) {
        container.innerHTML = '<div class="col-12 text-warning small">Assistant drafts are temporarily unavailable.</div>';
      }
    },

    async approveAiDraft(draftId) {
      try {
        await window.API.apiCall(`/communications/ai-draft-approve/${Number(draftId)}`, 'POST', {});
        this.showNotification('Approved and saved as a normal communication draft.', 'success');
        await this.loadAiDrafts();
        await this.loadStatistics();
      } catch (error) {
        this.showNotification(error.message || 'Unable to approve communication draft', 'error');
      }
    },

    loadStatistics: async function () {
      try {
        const response = await window.API.communications.index();
        this.data = response?.data || null;

        this.updateStats();
        this.renderRecentActivity();
      } catch (error) {
        console.error("Communications hub load error:", error);
        this.showNotification("Failed to load statistics", "error");
      }
    },

    updateStats: function () {
      if (!this.data || !this.data.totals) return;

      const t = this.data.totals;

      this.setText("totalCommunications", t.communications || 0);
      this.setText("totalAnnouncements", t.announcements?.total || 0);
      this.setText("unreadConversations", t.messaging?.unread || 0);
      this.setText("scheduledCount", t.by_status?.scheduled || 0);

      const byType = t.by_type || {};
      this.setText("messagesCount", byType.internal || 0);
      this.setText("announcementsCount", byType.notification || 0);
      this.setText("smsCount", byType.sms || 0);
      this.setText("emailCount", byType.email || 0);
      this.setText("whatsappCount", byType.whatsapp || 0);
    },

    renderRecentActivity: function () {
      const tbody = document.getElementById("activitiesTablebody");
      if (!tbody || !this.data?.recent) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3">No recent activity</td></tr>';
        return;
      }

      const recent = this.data.recent;
      tbody.innerHTML = recent
        .map((item) => {
          const typeLabel = {
            email: "Email",
            sms: "SMS",
            whatsapp: "WhatsApp",
            notification: "Announcement",
            internal: "Message",
            [item.type]: item.type.charAt(0).toUpperCase() + item.type.slice(1),
          }[item.type] || item.type;

          const statusClass = {
            sent: "success",
            draft: "secondary",
            scheduled: "warning",
            failed: "danger",
          }[item.status] || "primary";

          return `
            <tr>
              <td class="fw-medium">${this.escapeHtml(item.subject || "—")}</td>
              <td><span class="badge bg-light text-dark border">${this.escapeHtml(typeLabel)}</span></td>
              <td><span class="badge bg-${statusClass}">${this.escapeHtml(item.status)}</span></td>
              <td class="d-none d-md-table-cell">${this.formatDate(item.created_at)}</td>
            </tr>
          `;
        })
        .join("");

      if (!recent.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3">No recent activity</td></tr>';
      }
    },

    openInternalRequestModal: function () {
      const modalEl = document.getElementById("conversationModal");
      if (!modalEl) return;
      const modal = new bootstrap.Modal(modalEl);
      modal.show();

      document.getElementById("conversationContent").innerHTML = `
        <div class="mb-3">
          <label class="form-label">Request Title</label>
          <input type="text" class="form-control" id="requestTitle" placeholder="e.g. Warning Notice to Student...">
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="requestBody" rows="3" placeholder="Describe the issue..."></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Recipient</label>
          <select class="form-select" id="requestRecipient">
            <option value="">Select...</option>
            <option value="staff">All Staff</option>
            <option value="parents">All Parents</option>
          </select>
        </div>
      `;
    },

    setText: function (id, text) {
      const el = document.getElementById(id);
      if (el) el.textContent = text;
    },

    escapeHtml: function (value) {
      if (value === null || value === undefined) return "";
      return String(value).replace(/[&<>"']/g, (m) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      })[m]);
    },

    formatDate: function (value) {
      if (!value) return "";
      const d = new Date(value);
      if (isNaN(d.getTime())) return "";
      return d.toLocaleString();
    },

    showNotification: function (message, type = "info") {
      if (typeof Kingsway !== "undefined" && Kingsway.showNotification) {
        Kingsway.showNotification(message, type);
      }
    },
  };

  // Auto init when page loads
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      CommunicationsHubController.init();
    });
  } else {
    CommunicationsHubController.init();
  }

  // Expose for debugging
  window.CommunicationsHubController = CommunicationsHubController;
})();
