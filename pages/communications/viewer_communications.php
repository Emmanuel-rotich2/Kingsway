<?php
/**
 * Communications - Viewer Layout
 * Read-only layout for Students, Parents, Guardians
 * 
 * Features:
 * - No sidebar
 * - Single summary card
 * - Simple inbox list
 * - Read-only (no actions)
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/viewer-theme.css">

<div class="viewer-layout">
    <!-- Header -->
    <header class="viewer-header">
        <a href="/pages/dashboard.php" class="back-link">← Dashboard</a>
        <h1 class="page-title">📬 My Messages</h1>
    </header>

    <!-- Content -->
    <main class="viewer-main">
        <!-- Summary Card -->
        <div class="viewer-summary-card">
            <div class="summary-icon">📧</div>
            <div class="summary-stat">
                <span class="summary-value" id="totalMessages">0</span>
                <span class="summary-label">Messages</span>
            </div>
            <div class="summary-stat">
                <span class="summary-value" id="unreadCount">0</span>
                <span class="summary-label">Unread</span>
            </div>
        </div>

        <!-- Messages Inbox -->
        <div class="viewer-inbox" id="inboxContainer">
            <div class="inbox-header">
                <span class="inbox-title">Inbox</span>
                <button class="mark-read-btn" id="markAllRead">Mark all read</button>
            </div>

            <div class="inbox-list" id="inboxList">
                <!-- Messages loaded dynamically -->
            </div>
        </div>
    </main>
</div>

<!-- Message View Modal -->
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="msgModalTitle">Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="message-meta text-muted mb-2">
                    <small>From: <span id="msgFrom"></span></small><br>
                    <small>Date: <span id="msgDate"></span></small>
                </div>
                <div id="msgContent" class="message-content"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();
        loadInbox();

        document.getElementById('markAllRead').addEventListener('click', markAllAsRead);
    });

    async function loadInbox() {
        try {
            const response = await API.communications.getInbox();
            if (response.success) {
                renderInbox(response.data);
                updateSummary(response.data);
            }
        } catch (error) {
            console.error('Error loading inbox:', error);
            document.getElementById('inboxList').innerHTML = '<div class="empty-inbox">Unable to load messages</div>';
        }
    }

    function renderInbox(messages) {
        const container = document.getElementById('inboxList');
        container.innerHTML = '';

        if (messages.length === 0) {
            container.innerHTML = '<div class="empty-inbox">No messages yet</div>';
            return;
        }

        messages.forEach(msg => {
            const item = document.createElement('div');
            item.className = 'inbox-item' + (msg.is_read ? '' : ' unread');
            item.innerHTML = `
            <div class="inbox-item-indicator"></div>
            <div class="inbox-item-content">
                <div class="inbox-item-header">
                    <span class="inbox-from">${escapeHtml(msg.from_name || 'School')}</span>
                    <span class="inbox-date">${formatDate(msg.sent_at)}</span>
                </div>
                <div class="inbox-preview">${escapeHtml(truncate(msg.message, 100))}</div>
            </div>
        `;
            item.addEventListener('click', () => openMessage(msg));
            container.appendChild(item);
        });
    }

    function updateSummary(messages) {
        document.getElementById('totalMessages').textContent = messages.length;
        document.getElementById('unreadCount').textContent = messages.filter(m => !m.is_read).length;
    }

    function openMessage(msg) {
        document.getElementById('msgModalTitle').textContent = 'Message';
        document.getElementById('msgFrom').textContent = msg.from_name || 'School';
        document.getElementById('msgDate').textContent = formatDate(msg.sent_at);
        document.getElementById('msgContent').innerHTML = escapeHtml(msg.message).replace(/\n/g, '<br>');

        new bootstrap.Modal(document.getElementById('messageModal')).show();

        // Mark as read
        if (!msg.is_read) {
            API.communications.markRead(msg.id).then(loadInbox);
        }
    }

    async function markAllAsRead() {
        try {
            await API.communications.markAllRead();
            loadInbox();
        } catch (error) {
            console.error('Error marking messages read:', error);
        }
    }

    function formatDate(date) {
        if (!date) return '';
        const d = new Date(date);
        const now = new Date();
        if (d.toDateString() === now.toDateString()) {
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        return d.toLocaleDateString();
    }

    function truncate(str, len) { return str && str.length > len ? str.slice(0, len) + '...' : str || ''; }
    function escapeHtml(str) { return str ? str.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
</script>