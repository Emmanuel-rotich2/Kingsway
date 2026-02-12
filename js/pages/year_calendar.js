const YearCalendarController = (() => {
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
            const r = await window.API.apiCall('/academic/year-calendar', 'GET');
            allData = r?.data || r || [];
            renderStats(allData); renderTable(Array.isArray(allData) ? allData : []);

        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        document.getElementById('statEvents').textContent = items.length;
        document.getElementById('statTermDays').textContent = items.length;
        document.getElementById('statHolidays').textContent = items.length;
        document.getElementById('statExamDays').textContent = items.length;
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
    function exportCSV() {
        if (!allData.length) return;
        const headers = ['#', 'Month', 'Week', 'Date', 'Day', 'Event', 'Type'];
        const rows = allData.map(item => Object.values(item).slice(0, headers.length));
        let csv = headers.join(',') + '\n' + rows.map(r => r.map(v => '"' + (v||'') + '"').join(',')).join('\n');
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download = 'year_calendar.csv'; a.click();
    }
    function escapeHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function showNotification(msg, type) { const modal = document.getElementById('notificationModal'); if(modal){const m=modal.querySelector('.notification-message'),c=modal.querySelector('.modal-content');if(m)m.textContent=msg;if(c)c.className='modal-content notification-'+(type||'info');const b=bootstrap.Modal.getOrCreateInstance(modal);b.show();setTimeout(()=>b.hide(),3000);} }
    return { init, refresh: loadData, exportCSV };
})();
document.addEventListener('DOMContentLoaded', () => YearCalendarController.init());
