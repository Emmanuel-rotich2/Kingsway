const CurrentAcademicYearController = (() => {
    let allData = [];
    let yearInfo = {};
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
    }
    async function loadData() {
        try {
            // Reference data: cache 7d (stale-while-revalidate) to skip DB re-query.
            // Keep the secondary fallback if the cache/network both fail.
            const r = await DataStore.fetchPage('current-year', {
              endpoint: '/academic/current-year', storeName: 'reference_academic_years',
              ttl: DataStore.DEFAULT_TTL.LONG, strategy: 'stale-while-revalidate'
            }).catch(() => null)
                || await window.API.academic?.getCurrentAcademicYear?.().catch(() => null);
            const raw = r?.data || r || {};
            yearInfo = Array.isArray(raw) ? {} : raw;
            // Terms may be in raw.terms or the raw response itself
            allData = Array.isArray(raw) ? raw : (raw.terms || []);
            renderStats(yearInfo, allData);
            renderTable(allData);
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(year, terms) {
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el('statYear', year.name || year.year || year.academic_year || new Date().getFullYear());
        
        // Find current term
        const now = new Date();
        const currentTerm = terms.find(t => {
            const start = new Date(t.start_date);
            const end = new Date(t.end_date);
            return now >= start && now <= end;
        });
        el('statTerm', currentTerm ? (currentTerm.name || currentTerm.term_name || 'Term ' + currentTerm.term_number) : (year.current_term || 'N/A'));
        
        // Calculate weeks remaining
        if (currentTerm) {
            const endDate = new Date(currentTerm.end_date);
            const weeksLeft = Math.max(0, Math.ceil((endDate - now) / (7 * 24 * 60 * 60 * 1000)));
            el('statWeeks', weeksLeft + ' left');
            const daysLeft = Math.max(0, Math.ceil((endDate - now) / (24 * 60 * 60 * 1000)));
            el('statDays', daysLeft + ' left');
        } else {
            const totalWeeks = terms.reduce((s, t) => {
                const start = new Date(t.start_date);
                const end = new Date(t.end_date);
                return s + Math.ceil((end - start) / (7 * 24 * 60 * 60 * 1000));
            }, 0);
            el('statWeeks', totalWeeks || year.total_weeks || '--');
            el('statDays', year.total_days || '--');
        }
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No term data found</td></tr>'; return; }
        const now = new Date();
        tbody.innerHTML = items.map((d, i) => {
            const start = new Date(d.start_date);
            const end = new Date(d.end_date);
            const isCurrent = now >= start && now <= end;
            const isPast = now > end;
            const weeks = Math.ceil((end - start) / (7 * 24 * 60 * 60 * 1000));
            const status = d.status || (isCurrent ? 'Current' : isPast ? 'Completed' : 'Upcoming');
            const statusColor = isCurrent ? 'success' : isPast ? 'secondary' : 'primary';
            return `<tr class="${isCurrent ? 'table-success' : ''}">
                <td>${i + 1}</td>
                <td><strong>${escapeHtml(d.name || d.term_name || 'Term ' + (d.term_number || i + 1))}</strong></td>
                <td>${d.start_date || '--'}</td>
                <td>${d.end_date || '--'}</td>
                <td>${weeks} weeks</td>
                <td><span class="badge bg-${statusColor}">${status}</span></td>
            </tr>`;
        }).join('');
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        renderTable(allData.filter(item => !s || JSON.stringify(item).toLowerCase().includes(s)));
    }
    function exportCSV() {
        if (!allData.length) return;
        if (!window.PrintManager) {
            showNotification('PrintManager not available for export', 'error');
            return;
        }

        const columns = [
            { key: 'index', label: '#' },
            { key: 'term_name', label: 'Term Name' },
            { key: 'start_date', label: 'Start Date' },
            { key: 'end_date', label: 'End Date' },
            { key: 'weeks', label: 'Weeks' },
            { key: 'status', label: 'Status' }
        ];

        const now = new Date();
        const rows = allData.map((d, i) => {
            const start = new Date(d.start_date);
            const end = new Date(d.end_date);
            return {
                index: i + 1,
                term_name: d.name || d.term_name,
                start_date: d.start_date,
                end_date: d.end_date,
                weeks: Math.ceil((end - start) / (7 * 24 * 60 * 60 * 1000)),
                status: now >= start && now <= end ? 'Current' : now > end ? 'Completed' : 'Upcoming'
            };
        });

        window.PrintManager.exportToCSV({
            filename: `current_academic_year_${new Date().toISOString().slice(0, 10)}.csv`,
            columns: columns,
            rows: rows
        });
    }
    function escapeHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function showNotification(msg, type) { const modal = document.getElementById('notificationModal'); if (modal) { const m = modal.querySelector('.notification-message'), c = modal.querySelector('.modal-content'); if (m) m.textContent = msg; if (c) c.className = 'modal-content notification-' + (type || 'info'); const b = bootstrap.Modal.getOrCreateInstance(modal); b.show(); setTimeout(() => b.hide(), 3000); } }
    return { init, refresh: loadData, exportCSV };
})();
document.addEventListener('DOMContentLoaded', () => CurrentAcademicYearController.init());
