<?php
/**
 * Communications - Operator Layout
 * Minimal layout for Class Teachers, Subject Teachers, etc.
 * 
 * Features:
 * - Mini sidebar
 * - 2 stat cards
 * - Simple message list
 * - Can compose to own class only
 * - View own messages only
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/operator-theme.css">

<div class="operator-layout">
    <!-- Mini Sidebar -->
    <aside class="operator-sidebar" id="operatorSidebar">
        <div class="logo-section">
            <img src="/images/logo.png" alt="KA">
        </div>

        <nav class="operator-nav">
            <a href="/pages/dashboard.php" class="operator-nav-item" data-tooltip="Dashboard">🏠</a>
            <a href="/pages/manage_activities.php" class="operator-nav-item" data-tooltip="Activities">🏆</a>
            <a href="/pages/manage_communications.php" class="operator-nav-item active" data-tooltip="Messages">💬</a>
            <a href="/pages/my_classes.php" class="operator-nav-item" data-tooltip="My Classes">📚</a>
        </nav>

        <div class="user-avatar" id="userAvatar">T</div>
    </aside>

    <!-- Main Content -->
    <main class="operator-main">
        <!-- Header -->
        <header class="operator-header">
            <h1 class="page-title">💬 Messages</h1>
            <button class="btn btn-primary btn-sm" id="composeBtn">✉️ Send to Class</button>
        </header>

        <!-- Content -->
        <div class="operator-content">
            <!-- Stats - 2 columns -->
            <div class="operator-stats">
                <div class="operator-stat-card">
                    <div class="stat-icon">📤</div>
                    <div class="stat-info">
                        <div class="stat-value" id="sentCount">0</div>
                        <div class="stat-label">Sent</div>
                    </div>
                </div>
                <div class="operator-stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <div class="stat-value" id="deliveredCount">0</div>
                        <div class="stat-label">Delivered</div>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="operator-filters">
                <input type="text" class="search-input form-control" id="searchMessages"
                    placeholder="Search messages...">
            </div>

            <!-- Message Table - Essential columns -->
            <div class="operator-table-card">
                <div class="operator-table-header">
                    <span class="table-title">My Messages</span>
                </div>

                <table class="operator-data-table" id="messagesTable">
                    <thead>
                        <tr>
                            <th>Recipient</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="messagesTableBody">
                        <!-- Data loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Simple Compose Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Send to Class Parents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="composeForm">
                    <div class="mb-3">
                        <label class="form-label">Class</label>
                        <select class="form-select" id="classSelect">
                            <option value="">Select your class</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" id="message" rows="4"
                            placeholder="Type your message to class parents..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="sendBtn">Send</button>
            </div>
        </div>
    </div>
</div>

<script src="/js/components/RoleBasedUI.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        RoleBasedUI.applyLayout();

        const user = AuthContext.getUser();
        if (user) {
            document.getElementById('userAvatar').textContent = (user.name || 'T').charAt(0).toUpperCase();
        }

        loadMyMessages();
        loadMyClasses();

        document.getElementById('composeBtn').addEventListener('click', () => {
            new bootstrap.Modal(document.getElementById('composeModal')).show();
        });
        document.getElementById('searchMessages').addEventListener('input', debounce(filterMessages, 300));
    });

    async function loadMyMessages() {
        try {
            // Load only messages sent by current user
            const response = await API.communications.getMine();
            if (response.success) {
                renderMessagesTable(response.data);
                updateStats(response.data);
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    async function loadMyClasses() {
        try {
            const response = await API.classes.getMyClasses();
            if (response.success) {
                const select = document.getElementById('classSelect');
                response.data.forEach(cls => {
                    const opt = document.createElement('option');
                    opt.value = cls.id;
                    opt.textContent = cls.name;
                    select.appendChild(opt);
                });
            }
        } catch (error) {
            console.error('Error loading classes:', error);
        }
    }

    function renderMessagesTable(messages) {
        const tbody = document.getElementById('messagesTableBody');
        tbody.innerHTML = '';

        if (messages.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted p-4">No messages yet</td></tr>';
            return;
        }

        messages.forEach(msg => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${escapeHtml(msg.recipient_name || msg.recipient)}</td>
            <td class="text-truncate" style="max-width:200px;">${escapeHtml(msg.message)}</td>
            <td><span class="status-badge status-${msg.status}">${msg.status}</span></td>
            <td class="operator-row-actions">
                <button class="action-btn" onclick="viewMessage(${msg.id})">View</button>
            </td>
        `;
            tbody.appendChild(row);
        });
    }

    function updateStats(messages) {
        document.getElementById('sentCount').textContent = messages.length;
        document.getElementById('deliveredCount').textContent = messages.filter(m => m.status === 'delivered').length;
    }

    function filterMessages() {
        const search = document.getElementById('searchMessages').value.toLowerCase();
        document.querySelectorAll('#messagesTableBody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
        });
    }

    function viewMessage(id) {
        console.log('View message:', id);
    }

    function escapeHtml(str) { return str ? str.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, delay) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), delay); }; }
</script>