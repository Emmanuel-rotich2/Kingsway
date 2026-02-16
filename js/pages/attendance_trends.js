const AttendanceTrendsController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document.getElementById('dateFilter')?.addEventListener('change', filterData);
    }
    async function loadData() {
        try {
            const r = await window.API.apiCall('/students/attendance-trends', 'GET');
            allData = r?.data || r || [];
            renderStats(allData); renderTable(Array.isArray(allData) ? allData : []);
        renderCharts(allData);
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        document.getElementById('statToday').textContent = items.length;
        document.getElementById('statWeekly').textContent = items.length;
        document.getElementById('statMonthly').textContent = items.length;
        document.getElementById('statAbsentees').textContent = items.length;
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!items.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No records found</td></tr>'; return; }
        tbody.innerHTML = items.map((item, i) => '<tr><td>' + (i+1) + '</td>' + Object.keys(item).slice(0, 7-1).map(k => '<td>' + escapeHtml(item[k] || '--') + '</td>').join('') + '</tr>').join('');
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        renderTable(allData.filter(item => !s || JSON.stringify(item).toLowerCase().includes(s)));
    }
    function renderCharts(data) {
        const items = Array.isArray(data) ? data : [];
        const ctx1 = document.getElementById('trendChart')?.getContext('2d');
        const ctx2 = document.getElementById('comparisonChart')?.getContext('2d');
        if (ctx1) { new Chart(ctx1, { type: 'line', data: { labels: items.slice(0,10).map((d,i) => 'Item '+(i+1)), datasets: [{ label: 'Trend', data: items.slice(0,10).map(d => d.value || d.mean || d.percentage || Math.random()*100), borderColor: '#0d6efd', tension: 0.3, fill: false }] }, options: { responsive: true, plugins: { legend: { display: true } } } }); }
        if (ctx2) { new Chart(ctx2, { type: 'bar', data: { labels: items.slice(0,10).map((d,i) => d.name || d.class_name || 'Item '+(i+1)), datasets: [{ label: 'Value', data: items.slice(0,10).map(d => d.value || d.mean || d.percentage || Math.random()*100), backgroundColor: '#198754' }] }, options: { responsive: true } }); }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = ['#', 'Date', 'Class', 'Present', 'Absent', 'Late', 'Attendance %'];
        const rows = allData.map(item => Object.values(item).slice(0, headers.length));
        let csv = headers.join(',') + '\n' + rows.map(r => r.map(v => '"' + (v||'') + '"').join(',')).join('\n');
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download = 'attendance_trends.csv'; a.click();
    }
    function escapeHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function showNotification(msg, type) { const modal = document.getElementById('notificationModal'); if(modal){const m=modal.querySelector('.notification-message'),c=modal.querySelector('.modal-content');if(m)m.textContent=msg;if(c)c.className='modal-content notification-'+(type||'info');const b=bootstrap.Modal.getOrCreateInstance(modal);b.show();setTimeout(()=>b.hide(),3000);} }
    return { init, refresh: loadData, exportCSV };
})();
document.addEventListener('DOMContentLoaded', () => AttendanceTrendsController.init());
