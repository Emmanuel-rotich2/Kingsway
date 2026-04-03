/**
 * Manage Communications Page Controller
 * School Administrator communications management — announcements, messages, SMS.
 * Uses window.API.communications.* exclusively (no direct fetch).
 */

const communicationsController = {
    announcements: [],
    messages: [],
    smsRecords: [],
    canCreate: false,

    init: async function () {
        if (!AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        if (!AuthContext.hasPermission('communications_view')) {
            const c = document.getElementById('pageContent') || document.querySelector('.container-fluid');
            if (c) c.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger">Access denied: you do not have permission to view communications.</div>');
            return;
        }
        this.canCreate = AuthContext.hasPermission('communications_create');
        this.renderLayout();
        await this.loadAnnouncements();
        this.bindTabEvents();
    },

    renderLayout: function () {
        const container = document.getElementById('pageContent') || document.querySelector('.card-body') || document.body;
        container.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <ul class="nav nav-tabs" id="commTabs">
                <li class="nav-item"><a class="nav-link active" href="#" data-tab="announcements">
                    <i class="bi bi-megaphone me-1"></i>Announcements</a></li>
                <li class="nav-item"><a class="nav-link" href="#" data-tab="messages">
                    <i class="bi bi-chat-dots me-1"></i>Messages</a></li>
                <li class="nav-item"><a class="nav-link" href="#" data-tab="sms">
                    <i class="bi bi-phone me-1"></i>SMS</a></li>
            </ul>
            ${this.canCreate ? '<button class="btn btn-primary btn-sm" id="newCommBtn"><i class="bi bi-plus-lg me-1"></i>New Announcement</button>' : ''}
        </div>
        <div id="commTabContent">${this._spinner()}</div>`;
    },

    _spinner: function () {
        return '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    },

    _emptyState: function (message) {
        return `<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>${message}</div>`;
    },

    _setContent: function (html) {
        const c = document.getElementById('commTabContent');
        if (c) c.innerHTML = html;
    },

    loadAnnouncements: async function () {
        try {
            const res = await window.API.communications.getAnnouncement();
            this.announcements = res?.data ?? res ?? [];
            this.renderAnnouncements();
        } catch (e) {
            console.error('Communications: failed to load announcements', e);
            showNotification('Failed to load announcements', 'error');
        }
    },

    loadMessages: async function () {
        try {
            const res = await window.API.communications.getThread();
            this.messages = res?.data ?? res ?? [];
            this.renderMessages();
        } catch (e) {
            console.error('Communications: failed to load messages', e);
            showNotification('Failed to load messages', 'error');
        }
    },

    loadSms: async function () {
        try {
            const res = await window.API.communications.getInbound();
            this.smsRecords = res?.data ?? res ?? [];
            this.renderSms();
        } catch (e) {
            console.error('Communications: failed to load SMS records', e);
            showNotification('Failed to load SMS records', 'error');
        }
    },

    renderAnnouncements: function () {
        if (!this.announcements.length) {
            this._setContent(this._emptyState('No announcements found.'));
            return;
        }
        let html = `<div class="table-responsive"><table class="table table-hover align-middle">
            <thead class="table-dark"><tr>
                <th>Title</th><th>Target Audience</th><th>Date</th><th>Status</th>
                ${this.canCreate ? '<th>Actions</th>' : ''}
            </tr></thead><tbody>`;
        this.announcements.forEach(a => {
            const statusColor = a.status === 'sent' ? 'success' : a.status === 'draft' ? 'secondary' : 'warning';
            html += `<tr>
                <td>${a.title ?? a.subject ?? ''}</td>
                <td><span class="badge bg-info">${a.target_audience ?? a.recipient_type ?? 'All'}</span></td>
                <td>${a.created_at ? new Date(a.created_at).toLocaleDateString() : ''}</td>
                <td><span class="badge bg-${statusColor}">${a.status ?? 'draft'}</span></td>
                ${this.canCreate ? `<td>
                    <button class="btn btn-sm btn-outline-danger del-ann" data-id="${a.id}" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>` : ''}
            </tr>`;
        });
        html += '</tbody></table></div>';
        this._setContent(html);

        document.querySelectorAll('#commTabContent .del-ann').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                if (!confirm('Delete this announcement?')) return;
                try {
                    await window.API.communications.deleteAnnouncement(e.currentTarget.dataset.id);
                    showNotification('Announcement deleted', 'success');
                    await this.loadAnnouncements();
                } catch (err) {
                    showNotification('Failed to delete announcement', 'error');
                }
            });
        });
    },

    renderMessages: function () {
        if (!this.messages.length) {
            this._setContent(this._emptyState('No messages found.'));
            return;
        }
        let html = `<div class="table-responsive"><table class="table table-hover align-middle">
            <thead class="table-dark"><tr><th>Subject</th><th>Participants</th><th>Date</th><th>Status</th></tr></thead><tbody>`;
        this.messages.forEach(m => {
            html += `<tr>
                <td>${m.subject ?? m.title ?? '(no subject)'}</td>
                <td>${m.participant_count ?? m.participants ?? ''}</td>
                <td>${m.created_at ? new Date(m.created_at).toLocaleDateString() : ''}</td>
                <td><span class="badge bg-secondary">${m.status ?? 'open'}</span></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        this._setContent(html);
    },

    renderSms: function () {
        if (!this.smsRecords.length) {
            this._setContent(this._emptyState('No SMS records found.'));
            return;
        }
        let html = `<div class="table-responsive"><table class="table table-hover align-middle">
            <thead class="table-dark"><tr><th>Sender</th><th>Message</th><th>Status</th><th>Date</th></tr></thead><tbody>`;
        this.smsRecords.forEach(s => {
            html += `<tr>
                <td>${s.sender ?? s.from_number ?? s.phone ?? ''}</td>
                <td class="text-truncate" style="max-width:300px">${s.message ?? s.body ?? ''}</td>
                <td><span class="badge bg-${s.status === 'delivered' ? 'success' : 'warning'}">${s.status ?? ''}</span></td>
                <td>${s.created_at ? new Date(s.created_at).toLocaleDateString() : ''}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        this._setContent(html);
    },

    showNewCommForm: function () {
        const form = new ModalForm('newCommModal', {
            title: '<i class="bi bi-megaphone me-2"></i>New Announcement',
            submitButtonLabel: '<i class="bi bi-send me-1"></i>Send Announcement',
            fields: [
                { name: 'title', label: 'Title', type: 'text', required: true, placeholder: 'Announcement title' },
                { name: 'message', label: 'Message', type: 'textarea', required: true, rows: 5, placeholder: 'Write your announcement here...' },
                {
                    name: 'target_audience', label: 'Target Audience', type: 'select',
                    options: [
                        { value: 'all', label: 'All' },
                        { value: 'parents', label: 'Parents' },
                        { value: 'staff', label: 'Staff' },
                        { value: 'students', label: 'Students' },
                    ]
                },
            ],
            onSubmit: async (data) => {
                if (!data.title || !data.message) {
                    showNotification('Title and message are required', 'warning');
                    return false;
                }
                await window.API.communications.createAnnouncement(data);
                showNotification('Announcement sent successfully', 'success');
                await this.loadAnnouncements();
            },
        });
        form.show();
    },

    bindTabEvents: function () {
        const tabs = document.querySelectorAll('#commTabs [data-tab]');
        tabs.forEach(link => {
            link.addEventListener('click', async (e) => {
                e.preventDefault();
                tabs.forEach(l => l.classList.remove('active'));
                e.currentTarget.classList.add('active');
                this._setContent(this._spinner());
                const tab = e.currentTarget.dataset.tab;
                if (tab === 'announcements') await this.loadAnnouncements();
                else if (tab === 'messages') await this.loadMessages();
                else if (tab === 'sms') await this.loadSms();
            });
        });

        document.getElementById('newCommBtn')?.addEventListener('click', () => this.showNewCommForm());
    }
};

document.addEventListener('DOMContentLoaded', () => communicationsController.init());
