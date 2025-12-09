/**
 * Communications Controller
 * Handles messaging, announcements, SMS, parent communications, staff forums
 * Integrates with /api/communications endpoints
 */

const communicationsController = {
    messages: [],
    announcements: [],
    contacts: [],
    groups: [],
    filteredData: [],
    currentFilters: {},

    /**
     * Initialize controller
     */
    init: async function() {
        try {
            showNotification('Loading communications data...', 'info');
            await Promise.all([
                this.loadMessages(),
                this.loadAnnouncements(),
                this.loadContacts()
            ]);
            this.checkUserPermissions();
            showNotification('Communications loaded successfully', 'success');
        } catch (error) {
            console.error('Error initializing communications controller:', error);
            showNotification('Failed to load communications', 'error');
        }
    },

    // ============================================================================
    // MESSAGES & COMMUNICATIONS
    // ============================================================================

    /**
     * Load messages
     */
    loadMessages: async function() {
        try {
            const response = await API.communications.getCommunication();
            this.messages = response.data || response || [];
            this.filteredData = [...this.messages];
            this.renderMessagesTable();
        } catch (error) {
            console.error('Error loading messages:', error);
            const container = document.getElementById('messagesContainer');
            if (container) {
                container.innerHTML = '<div class="alert alert-danger">Failed to load messages</div>';
            }
        }
    },

    /**
     * Render messages table
     */
    renderMessagesTable: function() {
        const container = document.getElementById('messagesContainer');
        if (!container) return;

        if (this.filteredData.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No messages found.</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>From</th>
                            <th>To</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        this.filteredData.forEach(msg => {
            const statusBadge = this.getMessageStatusBadge(msg.status);
            const typeBadge = this.getMessageTypeBadge(msg.type);
            
            html += `
                <tr>
                    <td>${msg.from_name || 'N/A'}</td>
                    <td>${msg.to_name || msg.recipient_count ? `${msg.recipient_count} recipients` : 'N/A'}</td>
                    <td><strong>${msg.subject || msg.message_preview || 'No Subject'}</strong></td>
                    <td>${typeBadge}</td>
                    <td>${this.formatDate(msg.created_at)}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info" onclick="communicationsController.viewMessage(${msg.id})" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-warning" onclick="communicationsController.replyMessage(${msg.id})" title="Reply" data-permission="communications_send">
                                <i class="bi bi-reply"></i>
                            </button>
                            <button class="btn btn-danger" onclick="communicationsController.deleteMessage(${msg.id})" title="Delete" data-permission="communications_delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
        this.checkUserPermissions();
    },

    /**
     * Get message status badge
     */
    getMessageStatusBadge: function(status) {
        const badges = {
            'sent': '<span class="badge bg-success">Sent</span>',
            'pending': '<span class="badge bg-warning">Pending</span>',
            'failed': '<span class="badge bg-danger">Failed</span>',
            'delivered': '<span class="badge bg-info">Delivered</span>',
            'read': '<span class="badge bg-primary">Read</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    },

    /**
     * Get message type badge
     */
    getMessageTypeBadge: function(type) {
        const badges = {
            'sms': '<span class="badge bg-primary">SMS</span>',
            'email': '<span class="badge bg-info">Email</span>',
            'notification': '<span class="badge bg-warning">Notification</span>',
            'internal': '<span class="badge bg-secondary">Internal</span>'
        };
        return badges[type] || '<span class="badge bg-secondary">Message</span>';
    },

    /**
     * Send message
     */
    sendMessage: async function() {
        try {
            const data = {
                subject: prompt('Subject:'),
                message: prompt('Message:'),
                type: prompt('Type (SMS/Email/Internal):'),
                recipient_type: prompt('Recipient Type (Student/Parent/Staff/Class):'),
                recipient_ids: prompt('Recipient IDs (comma-separated):').split(',').map(id => id.trim())
            };

            await API.communications.createCommunication(data);
            showNotification('Message sent successfully', 'success');
            await this.loadMessages();
        } catch (error) {
            console.error('Error sending message:', error);
            showNotification('Failed to send message', 'error');
        }
    },

    /**
     * View message
     */
    viewMessage: async function(messageId) {
        try {
            const message = await API.communications.getCommunication(messageId);
            alert(`Message Details:\n\nFrom: ${message.from_name}\nTo: ${message.to_name}\nSubject: ${message.subject}\nMessage: ${message.message}\nDate: ${message.created_at}\nStatus: ${message.status}`);
        } catch (error) {
            console.error('Error loading message:', error);
            showNotification('Failed to load message', 'error');
        }
    },

    /**
     * Reply to message
     */
    replyMessage: function(messageId) {
        console.log('Reply feature coming soon');
    },

    /**
     * Delete message
     */
    deleteMessage: async function(messageId) {
        if (!confirm('Delete this message?')) return;

        try {
            await API.communications.deleteCommunication(messageId);
            showNotification('Message deleted successfully', 'success');
            await this.loadMessages();
        } catch (error) {
            console.error('Error deleting message:', error);
            showNotification('Failed to delete message', 'error');
        }
    },

    // ============================================================================
    // ANNOUNCEMENTS
    // ============================================================================

    /**
     * Load announcements
     */
    loadAnnouncements: async function() {
        try {
            const response = await API.communications.getAnnouncement();
            this.announcements = response.data || response || [];
        } catch (error) {
            console.error('Error loading announcements:', error);
            this.announcements = [];
        }
    },

    /**
     * Create announcement
     */
    createAnnouncement: async function() {
        try {
            const data = {
                title: prompt('Title:'),
                content: prompt('Content:'),
                target_audience: prompt('Target Audience (All/Students/Parents/Staff):'),
                priority: prompt('Priority (Normal/High/Urgent):')
            };

            await API.communications.createAnnouncement(data);
            showNotification('Announcement created successfully', 'success');
            await this.loadAnnouncements();
        } catch (error) {
            console.error('Error creating announcement:', error);
            showNotification('Failed to create announcement', 'error');
        }
    },

    /**
     * View announcement
     */
    viewAnnouncement: async function(announcementId) {
        try {
            const announcement = await API.communications.getAnnouncement(announcementId);
            alert(`Announcement:\n\nTitle: ${announcement.title}\nContent: ${announcement.content}\nAudience: ${announcement.target_audience}\nDate: ${announcement.created_at}`);
        } catch (error) {
            console.error('Error loading announcement:', error);
            showNotification('Failed to load announcement', 'error');
        }
    },

    /**
     * Delete announcement
     */
    deleteAnnouncement: async function(announcementId) {
        if (!confirm('Delete this announcement?')) return;

        try {
            await API.communications.deleteAnnouncement(announcementId);
            showNotification('Announcement deleted successfully', 'success');
            await this.loadAnnouncements();
        } catch (error) {
            console.error('Error deleting announcement:', error);
            showNotification('Failed to delete announcement', 'error');
        }
    },

    // ============================================================================
    // CONTACTS & GROUPS
    // ============================================================================

    /**
     * Load contacts
     */
    loadContacts: async function() {
        try {
            const response = await API.communications.getContact();
            this.contacts = response.data || response || [];
        } catch (error) {
            console.error('Error loading contacts:', error);
            this.contacts = [];
        }
    },

    /**
     * Create contact
     */
    createContact: async function() {
        try {
            const data = {
                name: prompt('Name:'),
                email: prompt('Email:'),
                phone: prompt('Phone:'),
                type: prompt('Type (Student/Parent/Staff/Other):')
            };

            await API.communications.createContact(data);
            showNotification('Contact created successfully', 'success');
            await this.loadContacts();
        } catch (error) {
            console.error('Error creating contact:', error);
            showNotification('Failed to create contact', 'error');
        }
    },

    /**
     * Create group
     */
    createGroup: async function() {
        try {
            const data = {
                name: prompt('Group Name:'),
                description: prompt('Description:'),
                type: prompt('Type (Class/Department/Custom):')
            };

            await API.communications.createGroup(data);
            showNotification('Group created successfully', 'success');
        } catch (error) {
            console.error('Error creating group:', error);
            showNotification('Failed to create group', 'error');
        }
    },

    // ============================================================================
    // PARENT MESSAGES
    // ============================================================================

    /**
     * Send parent message
     */
    sendParentMessage: async function() {
        try {
            const data = {
                student_id: prompt('Student ID:'),
                subject: prompt('Subject:'),
                message: prompt('Message:'),
                type: prompt('Type (SMS/Email/Both):')
            };

            await API.communications.createParentMessage(data);
            showNotification('Message sent to parent successfully', 'success');
        } catch (error) {
            console.error('Error sending parent message:', error);
            showNotification('Failed to send parent message', 'error');
        }
    },

    /**
     * Broadcast to parents
     */
    broadcastToParents: async function() {
        try {
            const data = {
                class_id: prompt('Class ID (or leave empty for all):'),
                subject: prompt('Subject:'),
                message: prompt('Message:'),
                type: 'sms'
            };

            await API.communications.createParentMessage(data);
            showNotification('Broadcast sent successfully', 'success');
        } catch (error) {
            console.error('Error broadcasting to parents:', error);
            showNotification('Failed to broadcast message', 'error');
        }
    },

    // ============================================================================
    // STAFF COMMUNICATIONS
    // ============================================================================

    /**
     * Create staff forum topic
     */
    createStaffForumTopic: async function() {
        try {
            const data = {
                title: prompt('Topic Title:'),
                content: prompt('Content:'),
                category: prompt('Category (General/Academic/Admin/Other):')
            };

            await API.communications.createStaffForumTopic(data);
            showNotification('Forum topic created successfully', 'success');
        } catch (error) {
            console.error('Error creating forum topic:', error);
            showNotification('Failed to create forum topic', 'error');
        }
    },

    /**
     * Create staff request
     */
    createStaffRequest: async function() {
        try {
            const data = {
                request_type: prompt('Request Type (Leave/Resource/Other):'),
                subject: prompt('Subject:'),
                description: prompt('Description:')
            };

            await API.communications.createStaffRequest(data);
            showNotification('Request submitted successfully', 'success');
        } catch (error) {
            console.error('Error creating staff request:', error);
            showNotification('Failed to create request', 'error');
        }
    },

    // ============================================================================
    // SMS
    // ============================================================================

    /**
     * Send bulk SMS
     */
    sendBulkSMS: async function() {
        try {
            const data = {
                message: prompt('SMS Message:'),
                recipient_type: prompt('Recipient Type (Students/Parents/Staff):'),
                filter: prompt('Filter (e.g., class_id, grade_level):')
            };

            await API.communications.createCommunication({
                ...data,
                type: 'sms'
            });
            showNotification('Bulk SMS sent successfully', 'success');
        } catch (error) {
            console.error('Error sending bulk SMS:', error);
            showNotification('Failed to send bulk SMS', 'error');
        }
    },

    /**
     * View SMS delivery reports
     */
    viewSMSReports: async function() {
        try {
            const logs = await API.communications.getLog();
            
            let message = 'SMS Delivery Reports:\n\n';
            if (logs.data && logs.data.length > 0) {
                logs.data.slice(0, 10).forEach(log => {
                    message += `${log.recipient}: ${log.status} - ${log.created_at}\n`;
                });
            } else {
                message += 'No SMS logs found.';
            }
            
            alert(message);
        } catch (error) {
            console.error('Error loading SMS reports:', error);
            showNotification('Failed to load SMS reports', 'error');
        }
    },

    // ============================================================================
    // UTILITIES
    // ============================================================================

    /**
     * Format date
     */
    formatDate: function(date) {
        if (!date) return 'N/A';
        return new Date(date).toLocaleString('en-GB');
    },

    /**
     * Check user permissions
     */
    checkUserPermissions: function() {
        const currentUser = AuthContext.getUser();
        if (!currentUser || !currentUser.permissions) return;

        document.querySelectorAll('[data-permission]').forEach(btn => {
            const requiredPerm = btn.getAttribute('data-permission');
            if (!currentUser.permissions.includes(requiredPerm)) {
                btn.style.display = 'none';
            }
        });
    },

    /**
     * Show quick actions
     */
    showQuickActions: function() {
        alert('Quick Actions:\n1. Send Message\n2. Create Announcement\n3. Send Parent Message\n4. Bulk SMS\n5. Create Forum Topic');
    },

    /**
     * Search messages
     */
    searchMessages: function(query) {
        const term = query.toLowerCase();
        this.filteredData = this.messages.filter(m =>
            (m.subject || '').toLowerCase().includes(term) ||
            (m.message || '').toLowerCase().includes(term) ||
            (m.from_name || '').toLowerCase().includes(term)
        );
        this.renderMessagesTable();
    },

    /**
     * Filter by type
     */
    filterByType: function(type) {
        if (type) {
            this.filteredData = this.messages.filter(m => m.type === type);
        } else {
            this.filteredData = [...this.messages];
        }
        this.renderMessagesTable();
    },

    /**
     * Filter by status
     */
    filterByStatus: function(status) {
        if (status) {
            this.filteredData = this.messages.filter(m => m.status === status);
        } else {
            this.filteredData = [...this.messages];
        }
        this.renderMessagesTable();
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('messagesContainer') || document.getElementById('communicationsContainer')) {
        communicationsController.init();
    }
});
