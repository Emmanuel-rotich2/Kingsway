<?php
/**
 * Communications - Manager Layout
 * Compact layout for HODs, Deputy Heads, Accountant, etc.
 * 
 * Features:
 * - Compact sidebar (80px expandable)
 * - 3 stat cards
 * - 2 channels (SMS, Email primarily)
 * - Standard table with view/edit
 * - Can compose messages
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/manager-theme.css">

<div class="manager-layout">
    <!-- Compact Sidebar -->
    <aside class="manager-sidebar" id="managerSidebar">
        <div class="logo-section">
            <img src="/images/logo.png" alt="KA">
        </div>

        <nav class="manager-nav">
            <a href="/pages/dashboard.php" class="manager-nav-item" data-tooltip="Dashboard">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="/pages/manage_activities.php" class="manager-nav-item" data-tooltip="Activities">
                <span class="nav-icon">🏆</span>
                <span class="nav-label">Activities</span>
            </a>
            <a href="/pages/manage_communications.php" class="manager-nav-item active" data-tooltip="Communications">
                <span class="nav-icon">💬</span>
                <span class="nav-label">Communications</span>
            </a>
            <a href="/pages/all_students.php" class="manager-nav-item" data-tooltip="Students">
                <span class="nav-icon">👨‍🎓</span>
                <span class="nav-label">Students</span>
            </a>
            <a href="/pages/reports.php" class="manager-nav-item" data-tooltip="Reports">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Reports</span>
            </a>
        </nav>

        <div class="user-avatar" id="userAvatar" title="Profile">M</div>
    </aside>

    <!-- Main Content -->
    <main class="manager-main">
        <!-- Header -->
        <header class="manager-header">
            <h1 class="page-title">💬 Communications</h1>
            <div class="manager-header-actions">
                <button class="btn btn-outline btn-sm" id="templatesBtn">Templates</button>
                <button class="btn btn-primary btn-sm" id="composeBtn">✉️ Compose</button>
            </div>
        </header>

        <!-- Content -->
        <div class="manager-content">
            <!-- Stats - 3 columns -->
            <div class="manager-stats">
                <div class="manager-stat-card">
                    <div class="stat-icon">📤</div>
                    <div class="stat-content">
                        <div class="stat-value" id="totalSent">0</div>
                        <div class="stat-label">Sent</div>
                    </div>
                </div>
                <div class="manager-stat-card">
                    <div class="stat-icon">⏰</div>
                    <div class="stat-content">
                        <div class="stat-value" id="scheduled">0</div>
                        <div class="stat-label">Scheduled</div>
                    </div>
                </div>
                <div class="manager-stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-content">
                        <div class="stat-value" id="deliveryRate">0%</div>
                        <div class="stat-label">Delivery Rate</div>
                    </div>
                </div>
            </div>

            <!-- Simple Chart -->
            <div class="manager-charts">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Recent Activity</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="activityChart" height="200"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">By Channel</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="channelChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="manager-table-card">
                <div class="manager-table-header">
                    <span class="table-title">Messages</span>
                    <span class="table-count" id="recordCount">0 records</span>
                </div>

                <div class="manager-filters">
                    <input type="text" class="search-input form-control" id="searchMessages" placeholder="Search...">
                    <select class="filter-select" id="channelFilter">
                        <option value="">All Channels</option>
                        <option value="sms">SMS</option>
                        <option value="email">Email</option>
                    </select>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="sent">Sent</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="manager-data-table" id="messagesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Recipient</th>
                                <th>Channel</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Sent At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="messagesTableBody">
                            <!-- Data loaded dynamically -->
                        </tbody>
                    </table>
                </div>

                <div class="table-pagination">
                    <span class="pagination-info">Showing 1-20 of <span id="totalRecords">0</span></span>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Compose Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">✉️ New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="composeForm">
                    <div class="mb-3">
                        <label class="form-label">Channel</label>
                        <select class="form-select" id="channelSelect">
                            <option value="sms">📱 SMS</option>
                            <option value="email">📧 Email</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recipient</label>
                        <input type="text" class="form-control" id="recipient" placeholder="Search parent/staff...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" id="message" rows="4"></textarea>
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
            document.getElementById('userAvatar').textContent = (user.name || 'M').charAt(0).toUpperCase();
        }

        loadMessages();
        initCharts();

        document.getElementById('composeBtn').addEventListener('click', () => {
            new bootstrap.Modal(document.getElementById('composeModal')).show();
        });
        document.getElementById('searchMessages').addEventListener('input', debounce(filterMessages, 300));
    });

    async function loadMessages() {
        try {
            const response = await API.communications.getAll();
            if (response.success) {
                renderMessagesTable(response.data);
                updateStats(response.data);
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    function renderMessagesTable(messages) {
        const tbody = document.getElementById('messagesTableBody');
        tbody.innerHTML = '';

        messages.forEach(msg => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${msg.id}</td>
            <td>${escapeHtml(msg.recipient_name || msg.recipient)}</td>
            <td><span class="badge">${msg.channel}</span></td>
            <td class="text-truncate" style="max-width:200px;">${escapeHtml(msg.message)}</td>
            <td><span class="status-badge status-${msg.status}">${msg.status}</span></td>
            <td>${formatDateTime(msg.sent_at)}</td>
            <td class="manager-row-actions">
                <button class="action-btn view-btn" onclick="viewMessage(${msg.id})">👁️</button>
                <button class="action-btn edit-btn" onclick="resendMessage(${msg.id})">🔄</button>
            </td>
        `;
            tbody.appendChild(row);
        });

        document.getElementById('recordCount').textContent = `${messages.length} records`;
    }

    function updateStats(messages) {
        const sent = messages.filter(m => m.status === 'sent' || m.status === 'delivered').length;
        const total = messages.length;
        document.getElementById('totalSent').textContent = sent;
        document.getElementById('scheduled').textContent = messages.filter(m => m.status === 'scheduled').length;
        document.getElementById('deliveryRate').textContent = total > 0 ? Math.round((sent / total) * 100) + '%' : '0%';
    }

    function initCharts() {
        const actCtx = document.getElementById('activityChart');
        if (actCtx) {
            new Chart(actCtx, {
                type: 'bar',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                    datasets: [{ label: 'Sent', data: [12, 19, 15, 22, 18], backgroundColor: '#166534' }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        const chCtx = document.getElementById('channelChart');
        if (chCtx) {
            new Chart(chCtx, {
                type: 'pie',
                data: { labels: ['SMS', 'Email'], datasets: [{ data: [70, 30], backgroundColor: ['#166534', '#0369a1'] }] },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    }

    function filterMessages() {
        const search = document.getElementById('searchMessages').value.toLowerCase();
        document.querySelectorAll('#messagesTableBody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
        });
    }

    function viewMessage(id) { console.log('View:', id); }
    function resendMessage(id) { console.log('Resend:', id); }

    function escapeHtml(str) { return str ? str.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function formatDateTime(d) { return d ? new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-'; }
    function debounce(fn, delay) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), delay); }; }
</script>