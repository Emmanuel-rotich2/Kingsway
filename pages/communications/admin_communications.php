<?php
/**
 * Communications - Admin Layout
 * Full-featured layout for System Administrator, Director, Headteacher, School Administrator
 * 
 * Features:
 * - Full sidebar with all navigation
 * - 4+ stat cards (Total Sent, Scheduled, Drafts, Failed, Cost stats)
 * - All channels: SMS, WhatsApp, Email, Push
 * - Templates management
 * - Campaigns and bulk operations
 * - Full analytics charts
 */
?>

<link rel="stylesheet" href="/css/school-theme.css">
<link rel="stylesheet" href="/css/roles/admin-theme.css">

<div class="admin-layout">
    <!-- Sidebar Navigation -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="logo-section">
            <img src="/images/logo.png" alt="Kingsway Academy">
            <span class="logo-text">Kingsway Academy</span>
        </div>

        <nav class="admin-nav">
            <div class="nav-section">
                <span class="nav-section-title">Main</span>
                <a href="/pages/dashboard.php" class="admin-nav-item">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="/pages/manage_activities.php" class="admin-nav-item">
                    <span class="nav-icon">🏆</span>
                    <span class="nav-label">Activities</span>
                </a>
                <a href="/pages/manage_communications.php" class="admin-nav-item active">
                    <span class="nav-icon">💬</span>
                    <span class="nav-label">Communications</span>
                </a>
            </div>

            <div class="nav-section">
                <span class="nav-section-title">Academics</span>
                <a href="/pages/all_students.php" class="admin-nav-item">
                    <span class="nav-icon">👨‍🎓</span>
                    <span class="nav-label">Students</span>
                </a>
                <a href="/pages/all_teachers.php" class="admin-nav-item">
                    <span class="nav-icon">👨‍🏫</span>
                    <span class="nav-label">Teachers</span>
                </a>
            </div>
        </nav>

        <div class="user-section">
            <div class="user-avatar" id="userAvatar">A</div>
            <div class="user-info">
                <span class="user-name" id="userName">Administrator</span>
                <span class="user-role" id="userRole">Admin</span>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="admin-main">
        <!-- Page Header -->
        <header class="admin-header">
            <div class="breadcrumb">
                <a href="/pages/dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="/pages/manage_communications.php">Communications</a>
            </div>
            <h1 class="page-title">Communications Hub</h1>
            <div class="admin-header-actions">
                <button class="btn btn-outline btn-sm" id="templatesBtn">
                    📝 Templates
                </button>
                <button class="btn btn-outline btn-sm" id="campaignBtn">
                    📢 Campaign
                </button>
                <button class="btn btn-outline btn-sm" id="exportBtn">
                    📤 Export
                </button>
                <button class="btn btn-primary" id="composeBtn">
                    ✉️ New Message
                </button>
            </div>
        </header>

        <!-- Content Area -->
        <div class="admin-content">
            <!-- Stats Grid - 4+ columns -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <div class="stat-icon">📤</div>
                    <div class="stat-content">
                        <div class="stat-value" id="totalSent">0</div>
                        <div class="stat-label">Total Sent</div>
                        <div class="stat-change positive">↑ 15%</div>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-icon">⏰</div>
                    <div class="stat-content">
                        <div class="stat-value" id="scheduled">0</div>
                        <div class="stat-label">Scheduled</div>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-content">
                        <div class="stat-value" id="drafts">0</div>
                        <div class="stat-label">Drafts</div>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-icon">❌</div>
                    <div class="stat-content">
                        <div class="stat-value" id="failed">0</div>
                        <div class="stat-label">Failed</div>
                        <div class="stat-change negative">↓ 5%</div>
                    </div>
                </div>
            </div>

            <!-- Cost Stats (Admin/Finance only) -->
            <div class="admin-stats" style="margin-top: 1rem;">
                <div class="admin-stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <div class="stat-value" id="smsCredits">0</div>
                        <div class="stat-label">SMS Credits Used</div>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-icon">💵</div>
                    <div class="stat-content">
                        <div class="stat-value" id="totalCost">KES 0</div>
                        <div class="stat-label">Total Cost (Month)</div>
                    </div>
                </div>
                <div class="admin-stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-value" id="deliveryRate">0%</div>
                        <div class="stat-label">Delivery Rate</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="admin-charts">
                <div class="chart-card large">
                    <div class="chart-header">
                        <h3 class="chart-title">Message Trends</h3>
                        <select class="chart-filter" id="trendPeriod">
                            <option value="7days">Last 7 Days</option>
                            <option value="30days" selected>Last 30 Days</option>
                            <option value="quarter">This Quarter</option>
                        </select>
                    </div>
                    <div class="chart-body">
                        <canvas id="messageTrendsChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">By Channel</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="channelChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Channel Tabs -->
            <ul class="nav nav-tabs mb-3" id="channelTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#allMessages">All Messages</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#smsTab">📱 SMS</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#whatsappTab">💬 WhatsApp</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#emailTab">📧 Email</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#pushTab">🔔 Push</a>
                </li>
            </ul>

            <!-- Data Table -->
            <div class="admin-table-card tab-content">
                <div id="allMessages" class="tab-pane fade show active">
                    <div class="admin-table-header">
                        <span class="table-title">All Messages</span>
                        <span id="recordCount">0 records</span>
                    </div>

                    <div class="admin-filters">
                        <input type="text" class="search-input form-control" id="searchMessages"
                            placeholder="Search messages...">
                        <select class="filter-select" id="channelFilter">
                            <option value="">All Channels</option>
                            <option value="sms">SMS</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="email">Email</option>
                            <option value="push">Push</option>
                        </select>
                        <select class="filter-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="sent">Sent</option>
                            <option value="delivered">Delivered</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="failed">Failed</option>
                            <option value="draft">Draft</option>
                        </select>
                        <div class="bulk-actions" id="bulkActions" style="display:none;">
                            <span class="selected-count">0 selected</span>
                            <button class="btn btn-warning btn-sm">Resend</button>
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="admin-data-table" id="messagesTable">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" class="select-all" id="selectAll"></th>
                                    <th>ID</th>
                                    <th>Recipient</th>
                                    <th>Channel</th>
                                    <th>Subject/Message</th>
                                    <th>Status</th>
                                    <th>Sent At</th>
                                    <th>Sent By</th>
                                    <th>Cost</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="messagesTableBody">
                                <!-- Data loaded dynamically -->
                            </tbody>
                        </table>
                    </div>

                    <div class="table-pagination">
                        <div class="pagination-info">
                            Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span
                                id="totalRecords">0</span>
                        </div>
                        <div class="pagination-controls" id="paginationControls"></div>
                    </div>
                </div>

                <div id="smsTab" class="tab-pane fade">
                    <div class="p-4 text-muted">SMS messages filtered here...</div>
                </div>
                <div id="whatsappTab" class="tab-pane fade">
                    <div class="p-4 text-muted">WhatsApp messages filtered here...</div>
                </div>
                <div id="emailTab" class="tab-pane fade">
                    <div class="p-4 text-muted">Email messages filtered here...</div>
                </div>
                <div id="pushTab" class="tab-pane fade">
                    <div class="p-4 text-muted">Push notifications filtered here...</div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Compose Message Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">✉️ Compose Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="composeForm">
                    <div class="mb-3">
                        <label class="form-label">Channel</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="channel" id="channelSMS" value="sms" checked>
                            <label class="btn btn-outline-primary" for="channelSMS">📱 SMS</label>

                            <input type="radio" class="btn-check" name="channel" id="channelWhatsApp" value="whatsapp">
                            <label class="btn btn-outline-success" for="channelWhatsApp">💬 WhatsApp</label>

                            <input type="radio" class="btn-check" name="channel" id="channelEmail" value="email">
                            <label class="btn btn-outline-info" for="channelEmail">📧 Email</label>

                            <input type="radio" class="btn-check" name="channel" id="channelPush" value="push">
                            <label class="btn btn-outline-warning" for="channelPush">🔔 Push</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recipients</label>
                        <select class="form-select" id="recipientType">
                            <option value="individual">Individual</option>
                            <option value="class">By Class</option>
                            <option value="form">By Form</option>
                            <option value="all_parents">All Parents</option>
                            <option value="all_staff">All Staff</option>
                            <option value="custom">Custom List</option>
                        </select>
                    </div>
                    <div class="mb-3" id="recipientSelector">
                        <input type="text" class="form-control" id="recipientInput" placeholder="Search recipients...">
                    </div>
                    <div class="mb-3" id="emailSubjectGroup" style="display:none;">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" id="emailSubject">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" id="messageContent" rows="5"
                            placeholder="Type your message..."></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">Characters: <span id="charCount">0</span>/160 (SMS)</small>
                            <button type="button" class="btn btn-link btn-sm" onclick="insertVariable()">Insert
                                Variable</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Schedule (optional)</label>
                        <input type="datetime-local" class="form-control" id="scheduleTime">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-outline-primary" id="saveDraftBtn">Save Draft</button>
                <button class="btn btn-primary" id="sendMessageBtn">Send Now</button>
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
            document.getElementById('userName').textContent = user.name || 'Administrator';
            document.getElementById('userRole').textContent = user.role || 'Admin';
            document.getElementById('userAvatar').textContent = (user.name || 'A').charAt(0).toUpperCase();
        }

        loadMessages();
        initCharts();

        // Event listeners
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
            <td><input type="checkbox" class="row-select" data-id="${msg.id}"></td>
            <td>${msg.id}</td>
            <td>${escapeHtml(msg.recipient_name || msg.recipient)}</td>
            <td><span class="badge channel-${msg.channel}">${msg.channel}</span></td>
            <td class="text-truncate" style="max-width:200px;">${escapeHtml(msg.message || msg.subject)}</td>
            <td><span class="status-badge status-${msg.status}">${msg.status}</span></td>
            <td>${formatDateTime(msg.sent_at)}</td>
            <td>${escapeHtml(msg.sent_by_name || 'System')}</td>
            <td>${msg.cost ? 'KES ' + msg.cost : '-'}</td>
            <td class="admin-row-actions">
                <button class="action-btn view-btn" onclick="viewMessage(${msg.id})">👁️</button>
                <button class="action-btn edit-btn" onclick="resendMessage(${msg.id})">🔄</button>
                <button class="action-btn delete-btn" onclick="deleteMessage(${msg.id})">🗑️</button>
            </td>
        `;
            tbody.appendChild(row);
        });

        document.getElementById('recordCount').textContent = `${messages.length} records`;
    }

    function updateStats(messages) {
        document.getElementById('totalSent').textContent = messages.filter(m => m.status === 'sent' || m.status === 'delivered').length;
        document.getElementById('scheduled').textContent = messages.filter(m => m.status === 'scheduled').length;
        document.getElementById('drafts').textContent = messages.filter(m => m.status === 'draft').length;
        document.getElementById('failed').textContent = messages.filter(m => m.status === 'failed').length;
    }

    function initCharts() {
        const trendsCtx = document.getElementById('messageTrendsChart');
        if (trendsCtx) {
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [{
                        label: 'Messages Sent',
                        data: [120, 190, 150, 220],
                        borderColor: '#166534',
                        backgroundColor: 'rgba(22, 101, 52, 0.1)',
                        fill: true
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }

        const channelCtx = document.getElementById('channelChart');
        if (channelCtx) {
            new Chart(channelCtx, {
                type: 'doughnut',
                data: {
                    labels: ['SMS', 'WhatsApp', 'Email', 'Push'],
                    datasets: [{
                        data: [45, 30, 15, 10],
                        backgroundColor: ['#166534', '#25D366', '#0369a1', '#ca8a04']
                    }]
                },
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

    function viewMessage(id) { console.log('View message:', id); }
    function resendMessage(id) { console.log('Resend message:', id); }
    function deleteMessage(id) { if (confirm('Delete this message?')) console.log('Delete:', id); }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    }

    function debounce(fn, delay) {
        let timeout;
        return function (...args) { clearTimeout(timeout); timeout = setTimeout(() => fn.apply(this, args), delay); };
    }
</script>