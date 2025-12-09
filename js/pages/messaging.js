/**
 * Messaging Page Controller - COMPLETE EXAMPLE
 * Demonstrates TabNavigator for multi-view interfaces
 * Tabs: Inbox, Sent, Drafts, Compose
 */

let tabNav = null;
let inboxTable = null;
let sentTable = null;
let draftsTable = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    initializeMessagingTabs();
    loadMessagingStatistics();
});

function initializeMessagingTabs() {
    // Create tab navigator
    tabNav = new TabNavigator('messagingContainer');

    // Register Inbox Tab
    tabNav.registerTab('inbox', {
        label: 'Inbox',
        icon: 'bi-inbox',
        badge: 0, // Will be updated with unread count
        render: renderInboxTab,
        onActivate: loadInboxMessages
    });

    // Register Sent Tab
    tabNav.registerTab('sent', {
        label: 'Sent',
        icon: 'bi-send',
        render: renderSentTab,
        onActivate: loadSentMessages
    });

    // Register Drafts Tab
    tabNav.registerTab('drafts', {
        label: 'Drafts',
        icon: 'bi-file-earmark-text',
        render: renderDraftsTab,
        onActivate: loadDrafts
    });

    // Register Compose Tab
    tabNav.registerTab('compose', {
        label: 'Compose',
        icon: 'bi-pencil-square',
        render: renderComposeTab,
        onActivate: initializeComposeForm
    });

    // Render all tabs
    tabNav.renderTabs();
}

// ============ INBOX TAB ============
function renderInboxTab() {
    return `
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <input type="text" class="form-control" id="inboxSearch" placeholder="Search messages...">
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-sm btn-outline-primary" id="markAllReadBtn">
                            <i class="bi bi-check-all"></i> Mark All Read
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="inboxTableContainer"></div>
            </div>
        </div>
    `;
}

async function loadInboxMessages() {
    inboxTable = new DataTable('inboxTableContainer', {
        apiEndpoint: '/communications/inbox',
        pageSize: 15,
        columns: [
            { 
                field: 'read_status', 
                label: '', 
                type: 'custom',
                formatter: (value) => value === 'unread' ? '<i class="bi bi-circle-fill text-primary" style="font-size: 8px;"></i>' : ''
            },
            { field: 'from_name', label: 'From', sortable: true },
            { field: 'subject', label: 'Subject', sortable: true },
            { 
                field: 'preview', 
                label: 'Preview', 
                type: 'custom',
                formatter: (value) => `<small class="text-muted">${value?.substring(0, 50) || ''}...</small>`
            },
            { field: 'received_date', label: 'Received', type: 'datetime', sortable: true }
        ],
        searchFields: ['from_name', 'subject'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye', permission: 'communications_view' },
            { id: 'reply', label: 'Reply', icon: 'bi-reply', permission: 'communications_send' },
            { id: 'forward', label: 'Forward', icon: 'bi-forward', permission: 'communications_send' },
            { id: 'delete', label: 'Delete', icon: 'bi-trash', variant: 'danger', permission: 'communications_delete' }
        ],
        onRowAction: handleInboxAction,
        onRowClick: (data) => viewMessage(data.id)
    });

    // Wire up search
    document.getElementById('inboxSearch')?.addEventListener('keyup', (e) => {
        inboxTable.search(e.target.value);
    });

    // Mark all as read button
    document.getElementById('markAllReadBtn')?.addEventListener('click', markAllAsRead);
}

async function handleInboxAction(action, data) {
    switch(action) {
        case 'view':
            await viewMessage(data.id);
            break;
        case 'reply':
            replyToMessage(data);
            break;
        case 'forward':
            forwardMessage(data);
            break;
        case 'delete':
            if (await ActionButtons.confirm('Delete this message?')) {
                await deleteMessage(data.id);
                inboxTable.refresh();
            }
            break;
    }
}

async function viewMessage(messageId) {
    try {
        const message = await window.API.apiCall(`/communications/message/${messageId}`, 'GET');
        
        // Mark as read
        await window.API.apiCall(`/communications/mark-read/${messageId}`, 'POST');
        
        // Show in modal
        const modal = new bootstrap.Modal(document.getElementById('viewMessageModal'));
        document.getElementById('messageFrom').textContent = message.from_name;
        document.getElementById('messageSubject').textContent = message.subject;
        document.getElementById('messageDate').textContent = new Date(message.sent_date).toLocaleString();
        document.getElementById('messageBody').innerHTML = message.body;
        
        // Attachments
        if (message.attachments && message.attachments.length > 0) {
            document.getElementById('messageAttachments').innerHTML = message.attachments.map(att => 
                `<a href="${att.url}" class="btn btn-sm btn-outline-secondary me-2" download>
                    <i class="bi bi-paperclip"></i> ${att.filename}
                </a>`
            ).join('');
        }
        
        modal.show();
        
        // Refresh inbox to update unread badge
        await loadMessagingStatistics();
        inboxTable.refresh();
    } catch (error) {
        console.error('Failed to load message:', error);
    }
}

// ============ SENT TAB ============
function renderSentTab() {
    return `
        <div class="card">
            <div class="card-header">
                <input type="text" class="form-control" id="sentSearch" placeholder="Search sent messages...">
            </div>
            <div class="card-body p-0">
                <div id="sentTableContainer"></div>
            </div>
        </div>
    `;
}

async function loadSentMessages() {
    sentTable = new DataTable('sentTableContainer', {
        apiEndpoint: '/communications/sent',
        pageSize: 15,
        columns: [
            { field: 'to_name', label: 'To', sortable: true },
            { field: 'subject', label: 'Subject', sortable: true },
            { field: 'sent_date', label: 'Sent', type: 'datetime', sortable: true },
            { 
                field: 'delivery_status', 
                label: 'Status', 
                type: 'badge',
                badgeMap: {
                    'delivered': 'success',
                    'pending': 'warning',
                    'failed': 'danger'
                }
            }
        ],
        searchFields: ['to_name', 'subject'],
        rowActions: [
            { id: 'view', label: 'View', icon: 'bi-eye' },
            { id: 'resend', label: 'Resend', icon: 'bi-arrow-repeat', permission: 'communications_send' }
        ],
        onRowClick: (data) => viewSentMessage(data.id)
    });

    document.getElementById('sentSearch')?.addEventListener('keyup', (e) => {
        sentTable.search(e.target.value);
    });
}

// ============ DRAFTS TAB ============
function renderDraftsTab() {
    return `
        <div class="card">
            <div class="card-body p-0">
                <div id="draftsTableContainer"></div>
            </div>
        </div>
    `;
}

async function loadDrafts() {
    draftsTable = new DataTable('draftsTableContainer', {
        apiEndpoint: '/communications/drafts',
        pageSize: 15,
        columns: [
            { field: 'subject', label: 'Subject', sortable: true },
            { field: 'last_modified', label: 'Last Modified', type: 'datetime', sortable: true }
        ],
        rowActions: [
            { id: 'edit', label: 'Continue Editing', icon: 'bi-pencil', variant: 'primary' },
            { id: 'delete', label: 'Delete', icon: 'bi-trash', variant: 'danger' }
        ],
        onRowAction: async (action, data) => {
            if (action === 'edit') {
                await editDraft(data.id);
            } else if (action === 'delete') {
                if (await ActionButtons.confirm('Delete this draft?')) {
                    await deleteDraft(data.id);
                    draftsTable.refresh();
                }
            }
        }
    });
}

// ============ COMPOSE TAB ============
function renderComposeTab() {
    return `
        <div class="card">
            <div class="card-body">
                <form id="composeForm">
                    <div class="mb-3">
                        <label class="form-label">Recipient Type</label>
                        <select class="form-select" id="recipientType" required>
                            <option value="">Select recipient type...</option>
                            <option value="individual">Individual</option>
                            <option value="class">Class</option>
                            <option value="staff">All Staff</option>
                            <option value="parents">All Parents</option>
                            <option value="students">All Students</option>
                        </select>
                    </div>

                    <div class="mb-3" id="recipientSelectContainer" style="display: none;">
                        <label class="form-label">Select Recipient</label>
                        <select class="form-select" id="recipientSelect" multiple></select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" id="messageSubject" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" id="messageBody" rows="8" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Attachments</label>
                        <input type="file" class="form-control" id="messageAttachments" multiple>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="sendSMS">
                        <label class="form-check-label" for="sendSMS">
                            Also send as SMS notification
                        </label>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="sendEmail">
                        <label class="form-check-label" for="sendEmail">
                            Also send as Email
                        </label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Send Message
                        </button>
                        <button type="button" class="btn btn-secondary" id="saveDraftBtn">
                            <i class="bi bi-save"></i> Save Draft
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="clearFormBtn">
                            <i class="bi bi-x"></i> Clear
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
}

async function initializeComposeForm() {
    const form = document.getElementById('composeForm');
    const recipientType = document.getElementById('recipientType');
    const recipientContainer = document.getElementById('recipientSelectContainer');
    const recipientSelect = document.getElementById('recipientSelect');

    // Handle recipient type change
    recipientType?.addEventListener('change', async (e) => {
        const type = e.target.value;
        
        if (type === 'individual') {
            recipientContainer.style.display = 'block';
            // Load all users for selection
            const users = await window.API.apiCall('/users/list', 'GET');
            recipientSelect.innerHTML = users.map(u => 
                `<option value="${u.id}">${u.full_name} (${u.role_name})</option>`
            ).join('');
        } else if (type === 'class') {
            recipientContainer.style.display = 'block';
            // Load classes
            const classes = await window.API.apiCall('/academic/classes-list', 'GET');
            recipientSelect.innerHTML = classes.map(c => 
                `<option value="${c.id}">${c.name}</option>`
            ).join('');
        } else {
            recipientContainer.style.display = 'none';
        }
    });

    // Handle form submission
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await sendMessage();
    });

    // Save draft button
    document.getElementById('saveDraftBtn')?.addEventListener('click', saveDraft);

    // Clear form button
    document.getElementById('clearFormBtn')?.addEventListener('click', () => {
        form.reset();
        recipientContainer.style.display = 'none';
    });
}

async function sendMessage() {
    const formData = new FormData();
    formData.append('recipient_type', document.getElementById('recipientType').value);
    formData.append('subject', document.getElementById('messageSubject').value);
    formData.append('body', document.getElementById('messageBody').value);
    formData.append('send_sms', document.getElementById('sendSMS').checked ? '1' : '0');
    formData.append('send_email', document.getElementById('sendEmail').checked ? '1' : '0');

    // Add recipients
    const recipientType = document.getElementById('recipientType').value;
    if (recipientType === 'individual' || recipientType === 'class') {
        const selected = Array.from(document.getElementById('recipientSelect').selectedOptions);
        selected.forEach(opt => formData.append('recipients[]', opt.value));
    }

    // Add attachments
    const files = document.getElementById('messageAttachments').files;
    for (let i = 0; i < files.length; i++) {
        formData.append('attachments[]', files[i]);
    }

    try {
        const result = await window.API.apiCall('/communications/send', 'POST', formData, true);
        
        if (result.success) {
            alert('Message sent successfully!');
            document.getElementById('composeForm').reset();
            
            // Switch to sent tab
            tabNav.activateTab('sent');
        }
    } catch (error) {
        console.error('Failed to send message:', error);
        alert('Failed to send message. Please try again.');
    }
}

async function saveDraft() {
    const data = {
        recipient_type: document.getElementById('recipientType').value,
        subject: document.getElementById('messageSubject').value,
        body: document.getElementById('messageBody').value
    };

    try {
        await window.API.apiCall('/communications/save-draft', 'POST', data);
        alert('Draft saved successfully!');
    } catch (error) {
        console.error('Failed to save draft:', error);
    }
}

// ============ STATISTICS ============
async function loadMessagingStatistics() {
    try {
        const stats = await window.API.apiCall('/reports/messaging-stats', 'GET');
        
        if (stats) {
            document.getElementById('unreadCount').textContent = stats.unread_messages || 0;
            document.getElementById('totalInbox').textContent = stats.total_inbox || 0;
            document.getElementById('totalSent').textContent = stats.total_sent || 0;
            document.getElementById('totalDrafts').textContent = stats.total_drafts || 0;
            
            // Update inbox badge
            tabNav.updateBadge('inbox', stats.unread_messages || 0);
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

// Helper functions
async function markAllAsRead() {
    try {
        await window.API.apiCall('/communications/mark-all-read', 'POST');
        await loadMessagingStatistics();
        inboxTable.refresh();
    } catch (error) {
        console.error('Failed to mark all as read:', error);
    }
}

function replyToMessage(data) {
    tabNav.activateTab('compose');
    setTimeout(() => {
        document.getElementById('recipientType').value = 'individual';
        document.getElementById('messageSubject').value = 'Re: ' + data.subject;
    }, 100);
}

function forwardMessage(data) {
    tabNav.activateTab('compose');
    setTimeout(() => {
        document.getElementById('messageSubject').value = 'Fwd: ' + data.subject;
    }, 100);
}
