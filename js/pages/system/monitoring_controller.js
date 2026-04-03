class MonitoringController {
    constructor(config) {
        this.config = Object.assign({ title: 'Monitor', apiEndpoint: '/system/health', refreshInterval: 30000 }, config);
        this.allData = {}; this.init();
    }
    async init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        await this.loadData();
        if (this.config.refreshInterval > 0) this.timer = setInterval(() => this.loadData(), this.config.refreshInterval);
    }
    async loadData() {
        try {
            const r = await window.API.apiCall(this.config.apiEndpoint, 'GET');
            this.allData = r?.data || r || {}; this.renderMetrics(this.allData);
        } catch (e) { console.error('Monitor load failed:', e); }
    }
    renderMetrics(data) {
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el('statStatus', data.status || 'Unknown');
        el('statUptime', data.uptime || '--');
        el('statValue1', data.value1 || data.cpu_usage || data.count || '0');
        el('statValue2', data.value2 || data.memory_usage || data.active || '0');
        const si = document.getElementById('statusIndicator');
        if (si) { const s = (data.status||'').toLowerCase(); si.className = 'badge fs-5 bg-' + (s==='healthy'||s==='online'||s==='active'?'success':s==='degraded'||s==='warning'?'warning':'danger'); si.textContent = data.status||'Unknown'; }
        const t = document.getElementById('lastUpdated'); if (t) t.textContent = 'Last updated: ' + new Date().toLocaleTimeString();
    }
    stop() { if (this.timer) clearInterval(this.timer); }
    exportCSV() {
        const csv = Object.entries(this.allData).map(([k,v]) => '"'+k+'","'+v+'"').join('\n');
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['Metric,Value\n'+csv],{type:'text/csv'}));
        a.download = this.config.title.toLowerCase().replace(/\s+/g,'_') + '.csv'; a.click();
    }
}