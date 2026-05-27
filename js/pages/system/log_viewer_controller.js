class LogViewerController {
    constructor(config) {
        this.config = Object.assign({ title: 'Logs', apiEndpoint: '/system/logs', columns: ['#', 'Timestamp', 'Level', 'Message', 'Source', 'Actions'] }, config);
        this.allData = [];
        this.visibleData = [];
        this.init();
    }

    async init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        this.setupEventListeners();
        await this.loadData();
    }

    setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', () => this.filterData());
        document.getElementById('severityFilter')?.addEventListener('change', () => this.filterData());
        document.getElementById('dateFrom')?.addEventListener('change', () => this.filterData());
        document.getElementById('dateTo')?.addEventListener('change', () => this.filterData());
    }

    async loadData() {
        try {
            const response = await window.API.apiCall(this.config.apiEndpoint, 'GET');
            this.allData = this.unwrapList(response);
            this.renderStats(this.allData);
            this.renderTable(this.allData);
        } catch (error) {
            console.error('Load failed:', error);
            this.allData = [];
            this.visibleData = [];
            this.renderStats([]);
            this.renderError(error.message || 'Load failed');
        }
    }

    renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const setText = (id, value) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        };

        setText('statTotal', items.length);
        setText('statErrors', items.filter(item => this.getLevel(item) === 'error').length);
        setText('statWarnings', items.filter(item => this.getLevel(item) === 'warning').length);

        const today = new Date().toISOString().split('T')[0];
        setText('statToday', items.filter(item => this.getTimestamp(item).startsWith(today)).length);
    }

    renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;

        this.visibleData = Array.isArray(items) ? items : [];
        const colspan = this.getColumnCount();

        if (!this.visibleData.length) {
            tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="text-center text-muted py-4">No records found</td></tr>';
            return;
        }

        tbody.innerHTML = this.visibleData.slice(0, 500).map((item, index) => {
            const level = this.getLevel(item);
            const badge = level === 'error' ? 'danger' : level === 'warning' ? 'warning' : level === 'critical' ? 'dark' : 'info';
            const timestamp = this.getTimestamp(item) || '--';
            const message = item.message || item.description || item.endpoint || item.action || '--';
            const source = item.source || item.module || item.controller || item.user_email || '--';

            return '<tr>'
                + '<td>' + (index + 1) + '</td>'
                + '<td><small>' + this.esc(timestamp) + '</small></td>'
                + '<td><span class="badge bg-' + badge + '">' + this.esc(level) + '</span></td>'
                + '<td>' + this.esc(message) + '</td>'
                + '<td>' + this.esc(source) + '</td>'
                + '<td><button class="btn btn-sm btn-outline-primary" onclick="window._logCtrl.showDetail(' + index + ')"><i class="fas fa-eye"></i></button></td>'
                + '</tr>';
        }).join('');
    }

    filterData() {
        const search = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const severity = document.getElementById('severityFilter')?.value || '';
        const from = document.getElementById('dateFrom')?.value || '';
        const to = document.getElementById('dateTo')?.value || '';

        const filtered = this.allData.filter(item => {
            if (search && !JSON.stringify(item).toLowerCase().includes(search)) return false;
            if (severity && this.getLevel(item) !== severity) return false;

            const timestamp = this.getTimestamp(item);
            if (from && timestamp < from) return false;
            if (to && timestamp > to + 'T23:59:59') return false;

            return true;
        });

        this.renderTable(filtered);
    }

    showDetail(index) {
        const item = this.visibleData[index];
        if (!item) return;

        const detail = document.getElementById('detailContent');
        if (detail) {
            detail.innerHTML = '<pre class="mb-0" style="white-space:pre-wrap">' + this.esc(JSON.stringify(item, null, 2)) + '</pre>';
        }

        const modal = document.getElementById('detailModal');
        if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    exportCSV() {
        if (!this.allData.length) return;

        const headers = this.config.columns;
        const rows = this.allData.map(item => Object.values(item).slice(0, headers.length));
        const csv = headers.join(',') + '\n' + rows.map(row => row.map(value => this.csv(value)).join(',')).join('\n');
        const link = document.createElement('a');

        link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        link.download = this.config.title.toLowerCase().replace(/\s+/g, '_') + '.csv';
        link.click();
    }

    unwrapList(response) {
        const payload = response?.data?.data ?? response?.data ?? response;
        if (Array.isArray(payload)) return payload;
        if (Array.isArray(payload?.items)) return payload.items;
        if (Array.isArray(payload?.records)) return payload.records;
        if (Array.isArray(payload?.rows)) return payload.rows;
        if (payload && typeof payload === 'object') return Object.values(payload).filter(value => value && typeof value === 'object');
        return [];
    }

    renderError(message) {
        const tbody = document.querySelector('#dataTable tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="' + this.getColumnCount() + '" class="text-center text-danger py-4">' + this.esc(message) + '</td></tr>';
        }
    }

    getLevel(item) {
        const status = String(item?.level || item?.severity || item?.status || '').toLowerCase();
        if (status.includes('error') || status.includes('fail')) return 'error';
        if (status.includes('warn')) return 'warning';
        if (status.includes('critical')) return 'critical';
        return status || 'info';
    }

    getTimestamp(item) {
        return String(item?.created_at || item?.timestamp || item?.updated_at || item?.date || '');
    }

    getColumnCount() {
        return document.querySelectorAll('#dataTable thead th').length || this.config.columns.length || 1;
    }

    csv(value) {
        return '"' + String(value ?? '').replace(/"/g, '""') + '"';
    }

    esc(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
}
