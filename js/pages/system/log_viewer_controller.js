class LogViewerController {
    constructor(config) {
        this.config = Object.assign({ title: 'Logs', apiEndpoint: '/system/logs', columns: ['#','Timestamp','Level','Message'] }, config);
        this.allData = []; this.init();
    }
    async init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
        this.setupEventListeners(); await this.loadData();
    }
    setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', () => this.filterData());
        document.getElementById('severityFilter')?.addEventListener('change', () => this.filterData());
        document.getElementById('dateFrom')?.addEventListener('change', () => this.filterData());
        document.getElementById('dateTo')?.addEventListener('change', () => this.filterData());
    }
    async loadData() {
        try {
            const r = await window.API.apiCall(this.config.apiEndpoint, 'GET');
            this.allData = r?.data || r || []; this.renderStats(this.allData); this.renderTable(this.allData);
        } catch (e) { console.error('Load failed:', e); this.renderTable([]); }
    }
    renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el('statTotal', items.length);
        el('statErrors', items.filter(i => (i.level||i.severity||'').toLowerCase() === 'error').length);
        el('statWarnings', items.filter(i => (i.level||i.severity||'').toLowerCase() === 'warning').length);
        const today = new Date().toISOString().split('T')[0];
        el('statToday', items.filter(i => i.created_at && i.created_at.startsWith(today)).length);
    }
    renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        const cols = this.config.columns.length;
        if (!items.length) { tbody.innerHTML = '<tr><td colspan="' + cols + '" class="text-center text-muted py-4">No records found</td></tr>'; return; }
        tbody.innerHTML = items.slice(0, 500).map((item, i) => {
            const level = (item.level || item.severity || 'info').toLowerCase();
            const badge = level === 'error' ? 'danger' : level === 'warning' ? 'warning' : level === 'critical' ? 'dark' : 'info';
            return '<tr><td>' + (i+1) + '</td><td><small>' + (item.created_at || item.timestamp || '--') + '</small></td><td><span class="badge bg-' + badge + '">' + this.esc(level) + '</span></td><td>' + this.esc(item.message || item.description || '--') + '</td><td>' + this.esc(item.source || '--') + '</td><td><button class="btn btn-sm btn-outline-primary" onclick="window._logCtrl.showDetail(' + i + ')"><i class="fas fa-eye"></i></button></td></tr>';
        }).join('');
    }
    filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const sev = document.getElementById('severityFilter')?.value || '';
        const from = document.getElementById('dateFrom')?.value || '';
        const to = document.getElementById('dateTo')?.value || '';
        this.renderTable(this.allData.filter(item => {
            if (s && !JSON.stringify(item).toLowerCase().includes(s)) return false;
            if (sev && (item.level||item.severity||'').toLowerCase() !== sev) return false;
            if (from && (item.created_at||'') < from) return false;
            if (to && (item.created_at||'') > to + 'T99') return false;
            return true;
        }));
    }
    showDetail(index) {
        const item = this.allData[index]; if (!item) return;
        const det = document.getElementById('detailContent');
        if (det) det.innerHTML = '<pre class="mb-0" style="white-space:pre-wrap">' + this.esc(JSON.stringify(item, null, 2)) + '</pre>';
        const m = document.getElementById('detailModal');
        if (m) new bootstrap.Modal(m).show();
    }
    exportCSV() {
        if (!this.allData.length) return;
        const h = this.config.columns; const rows = this.allData.map(item => Object.values(item).slice(0, h.length));
        let csv = h.join(',') + '\n' + rows.map(r => r.map(v => '"'+(v||'')+'"').join(',')).join('\n');
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv],{type:'text/csv'}));
        a.download = this.config.title.toLowerCase().replace(/\s+/g,'_') + '.csv'; a.click();
    }
    esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
}