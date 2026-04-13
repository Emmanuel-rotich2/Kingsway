const TeacherPerformanceReviewsController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document.getElementById('dateFilter')?.addEventListener('change', filterData);
    }
    async function loadData() {
        try {
            const r = await window.API.apiCall('/staff/performance-reviews', 'GET');
            allData = r?.data || r || [];
            renderStats(allData); renderTable(Array.isArray(allData) ? allData : []);

        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const total = items.length;
        const avg = total ? (items.reduce((s, i) => s + (parseFloat(i.rating) || 0), 0) / total).toFixed(1) : '0.0';
        const excellent = items.filter(i => parseFloat(i.rating) >= 4).length;
        const low = items.filter(i => parseFloat(i.rating) > 0 && parseFloat(i.rating) < 2.5).length;
        document.getElementById('statTotal').textContent = total;
        document.getElementById('statAvg').textContent = avg;
        document.getElementById('statExcellent').textContent = excellent;
        document.getElementById('statLow').textContent = low;
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!items.length) { tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No records found</td></tr>'; return; }
        const ratingBadge = r => {
            const v = parseFloat(r) || 0;
            const cls = v >= 4 ? 'success' : v >= 3 ? 'primary' : v >= 2 ? 'warning' : 'danger';
            return `<span class="badge bg-${cls}">${v ? v.toFixed(1) : '--'}</span>`;
        };
        tbody.innerHTML = items.map((item, i) => `<tr>
            <td>${i + 1}</td>
            <td>${escapeHtml(item.teacher_name || item.staff_name || '--')}</td>
            <td>${escapeHtml(item.subject || '--')}</td>
            <td>${item.review_date || item.date || '--'}</td>
            <td>${escapeHtml(item.reviewer_name || item.reviewed_by || '--')}</td>
            <td>${ratingBadge(item.rating)}</td>
            <td><span class="badge bg-info text-dark">${escapeHtml(item.category || item.review_type || 'General')}</span></td>
            <td class="small text-muted">${escapeHtml((item.remarks || item.comments || '').substring(0, 60))}${(item.remarks || '').length > 60 ? '...' : ''}</td>
            <td><button class="btn btn-xs btn-outline-primary" onclick="TeacherPerformanceReviewsController.viewDetail(${i})"><i class="fas fa-eye"></i></button></td>
        </tr>`).join('');
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const f = document.getElementById('filterSelect')?.value || '';
        renderTable(allData.filter(item => {
            if (s && !JSON.stringify(item).toLowerCase().includes(s)) return false;
            if (f && (item.category || item.review_type || '') !== f) return false;
            return true;
        }));
    }
    function viewDetail(index) {
        const item = allData[index]; if (!item) return;
        alert(`Teacher: ${item.teacher_name || item.staff_name || '--'}\nSubject: ${item.subject || '--'}\nRating: ${item.rating || '--'}\nRemarks: ${item.remarks || item.comments || 'None'}`);
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = ['#', 'Teacher', 'Subject', 'Review Date', 'Reviewer', 'Rating', 'Category', 'Remarks', 'Actions'];
        const rows = allData.map(item => Object.values(item).slice(0, headers.length));
        let csv = headers.join(',') + '\n' + rows.map(r => r.map(v => '"' + (v||'') + '"').join(',')).join('\n');
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download = 'teacher_performance_reviews.csv'; a.click();
    }
    function escapeHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function showNotification(msg, type) { const modal = document.getElementById('notificationModal'); if(modal){const m=modal.querySelector('.notification-message'),c=modal.querySelector('.modal-content');if(m)m.textContent=msg;if(c)c.className='modal-content notification-'+(type||'info');const b=bootstrap.Modal.getOrCreateInstance(modal);b.show();setTimeout(()=>b.hide(),3000);} }
    return { init, refresh: loadData, exportCSV, viewDetail };
})();
document.addEventListener('DOMContentLoaded', () => TeacherPerformanceReviewsController.init());
