/**
 * Backups & System Health Controller
 * Show system health status and backup information
 * Role: System Administrator (ID 2)
 */
const backupsController = {
    health: null,
    logs: [],
    filters: { search: '', level: '' },

    init: async function () {
        if (!AuthContext.isAuthenticated()) { window.location.href = '/'; return; }
        if (!AuthContext.hasPermission('system_view')) {
            const el = document.getElementById('mainTable');
            if (el) el.innerHTML = '<div class="alert alert-danger m-3">Access denied</div>';
            return;
        }
        await this.loadData();
        this.render();
        this.bindEvents();
    },

    loadData: async function () {
        try {
            const [healthRes, logsRes] = await Promise.all([
                window.API.system.getHealth(),
                window.API.system.getLogs({ limit: 50 }).catch(() => ({ data: [] }))
            ]);
            this.health = healthRes?.data ?? healthRes ?? null;
            this.logs = logsRes?.data ?? logsRes ?? [];
        } catch (e) {
            console.error('backups: loadData error', e);
            showNotification('Failed to load system health data', 'error');
        }
    },

    renderHealthCard: function (label, value, icon, colorClass) {
        const display = value !== null && value !== undefined ? String(value) : 'N/A';
        return `<div class="col-md-3 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle ${colorClass} bg-opacity-10 p-3 me-3">
                            <i class="bi bi-${icon} ${colorClass.replace('bg-','text-')} fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small">${label}</div>
                            <div class="fw-bold">${this.esc(display)}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    },

    render: function () {
        const container = document.getElementById('mainTable');
        if (!container) return;

        let html = '';

        // System Health Section
        if (this.health) {
            const h = this.health;
            const dbStatus = h.database_ok || h.db_status === 'ok' || h.database === 'connected';
            const cacheStatus = h.cache_ok || h.cache_status === 'ok';
            const storageUsed = h.storage_used || h.disk_used || 'N/A';
            const storageTotal = h.storage_total || h.disk_total || null;
            const storageDisplay = storageTotal ? `${storageUsed} / ${storageTotal}` : storageUsed;
            const uptime = h.uptime || h.server_uptime || 'N/A';
            const version = h.app_version || h.version || 'N/A';
            const lastBackup = h.last_backup || h.backup_date || 'N/A';
            const phpVersion = h.php_version || 'N/A';

            html += `<div class="row g-3 mb-4">
                ${this.renderHealthCard('Database', dbStatus ? 'Connected' : 'Disconnected', 'database', dbStatus ? 'bg-success' : 'bg-danger')}
                ${this.renderHealthCard('Storage', storageDisplay, 'hdd', 'bg-primary')}
                ${this.renderHealthCard('Last Backup', lastBackup, 'cloud-arrow-up', 'bg-info')}
                ${this.renderHealthCard('App Version', version, 'info-circle', 'bg-secondary')}
                ${this.renderHealthCard('PHP Version', phpVersion, 'code', 'bg-warning')}
                ${this.renderHealthCard('Cache', cacheStatus ? 'OK' : 'N/A', 'lightning', cacheStatus ? 'bg-success' : 'bg-secondary')}
                ${this.renderHealthCard('Server Uptime', uptime, 'clock', 'bg-success')}
            `;

            // Additional health keys
            const knownKeys = new Set(['database_ok','db_status','database','cache_ok','cache_status',
                'storage_used','disk_used','storage_total','disk_total','uptime','server_uptime',
                'app_version','version','last_backup','backup_date','php_version']);
            Object.entries(h).forEach(([k, v]) => {
                if (!knownKeys.has(k) && typeof v !== 'object') {
                    html += this.renderHealthCard(
                        k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
                        v, 'gear', 'bg-secondary'
                    );
                }
            });

            html += `</div>`;

            // Backup Actions
            html += `<div class="card mb-4 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-cloud-arrow-up me-2"></i>Backup Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary" id="refreshHealthBtn">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh Status
                        </button>
                        <button class="btn btn-outline-secondary" id="archiveLogsBtn">
                            <i class="bi bi-archive me-1"></i>Archive Logs
                        </button>
                        <button class="btn btn-outline-danger" id="clearLogsBtn">
                            <i class="bi bi-trash me-1"></i>Clear Old Logs
                        </button>
                    </div>
                </div>
            </div>`;
        } else {
            html += `<div class="alert alert-warning mb-4">
                <i class="bi bi-exclamation-triangle me-2"></i>System health data unavailable.
                <button class="btn btn-sm btn-outline-warning ms-3" id="refreshHealthBtn">Retry</button>
            </div>`;
        }

        // System Logs Section
        const term = this.filters.search.toLowerCase();
        const filteredLogs = (Array.isArray(this.logs) ? this.logs : []).filter(log => {
            const mSearch = !term ||
                (log.message || log.msg || '').toLowerCase().includes(term) ||
                (log.level || '').toLowerCase().includes(term) ||
                (log.context || '').toLowerCase().includes(term);
            const mLevel = !this.filters.level || (log.level || '').toLowerCase() === this.filters.level;
            return mSearch && mLevel;
        });

        html += `<div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Recent System Logs</h6>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" id="logSearch"
                        placeholder="Search logs..." value="${this.esc(this.filters.search)}" style="width:180px">
                    <select class="form-select form-select-sm" id="levelFilter" style="width:130px">
                        <option value="">All Levels</option>
                        <option value="error"${this.filters.level === 'error' ? ' selected' : ''}>Error</option>
                        <option value="warning"${this.filters.level === 'warning' ? ' selected' : ''}>Warning</option>
                        <option value="info"${this.filters.level === 'info' ? ' selected' : ''}>Info</option>
                        <option value="debug"${this.filters.level === 'debug' ? ' selected' : ''}>Debug</option>
                    </select>
                </div>
            </div>
            <div class="card-body p-0">`;

        if (!filteredLogs.length) {
            html += '<div class="alert alert-info m-3">No logs found.</div>';
        } else {
            html += `<div class="table-responsive"><table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr><th>Time</th><th>Level</th><th>Message</th><th>Context</th></tr>
                </thead><tbody>`;
            filteredLogs.forEach(log => {
                const level = (log.level || 'info').toLowerCase();
                const levelClass = { error: 'danger', warning: 'warning', info: 'info', debug: 'secondary' }[level] || 'secondary';
                const ts = log.created_at || log.timestamp || log.time || '';
                html += `<tr>
                    <td class="text-nowrap text-muted small">${this.esc(ts)}</td>
                    <td><span class="badge bg-${levelClass}">${this.esc(level)}</span></td>
                    <td class="small">${this.esc(log.message || log.msg || '')}</td>
                    <td class="text-muted small">${this.esc(log.context || log.user || '')}</td>
                </tr>`;
            });
            html += `</tbody></table></div>
            <div class="p-2 text-muted small">${filteredLogs.length} of ${this.logs.length} log entries</div>`;
        }

        html += `</div></div>`;

        container.innerHTML = html;
        this.bindFilterEvents();
    },

    bindEvents: function () {},

    bindFilterEvents: function () {
        document.getElementById('logSearch')?.addEventListener('input', e => {
            this.filters.search = e.target.value;
            this.render();
        });
        document.getElementById('levelFilter')?.addEventListener('change', e => {
            this.filters.level = e.target.value;
            this.render();
        });
        document.getElementById('refreshHealthBtn')?.addEventListener('click', async () => {
            showNotification('Refreshing system health...', 'info');
            await this.loadData();
            this.render();
        });
        document.getElementById('archiveLogsBtn')?.addEventListener('click', async () => {
            if (!confirm('Archive current logs? Archived logs will be stored for later review.')) return;
            try {
                await window.API.system.archiveLogs({ all: true });
                showNotification('Logs archived successfully', 'success');
                await this.loadData();
                this.render();
            } catch (e) {
                showNotification(e.message || 'Failed to archive logs', 'error');
            }
        });
        document.getElementById('clearLogsBtn')?.addEventListener('click', async () => {
            if (!confirm('Clear old logs? This will remove logs older than 30 days.')) return;
            try {
                await window.API.system.clearLogs({ older_than_days: 30 });
                showNotification('Old logs cleared', 'success');
                await this.loadData();
                this.render();
            } catch (e) {
                showNotification(e.message || 'Failed to clear logs', 'error');
            }
        });
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', () => backupsController.init());
