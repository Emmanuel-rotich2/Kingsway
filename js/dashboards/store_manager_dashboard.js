/**
 * Store Manager (Inventory) Dashboard Controller
 * Role: Inventory Manager (ID 14)
 */
const storeDashboardController = {
    init: function () {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        this.loadAll();
    },

    refresh: function () { this.loadAll(); },

    loadAll: async function () {
        const token = localStorage.getItem('token');
        const h = { 'Authorization': 'Bearer ' + token };
        const get = url => fetch((window.APP_BASE || '') + url, { headers: h }).then(r => r.json()).catch(() => null);

        const [stats, lowStock, requisitions] = await Promise.allSettled([
            get('/api/inventory/stats'),
            get('/api/inventory/low-stock?limit=8'),
            get('/api/requisitions?status=pending&limit=8')
        ]);

        if (stats.value) this.renderStats(stats.value?.data || stats.value);
        if (lowStock.value) this.renderLowStock(lowStock.value?.data || lowStock.value || []);
        if (requisitions.value) this.renderRequisitions(requisitions.value?.data || requisitions.value || []);
    },

    renderStats: function (d) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v ?? 0; };
        set('totalItems', d.total_items || d.total || 0);
        set('lowStockCount', d.low_stock || d.low_stock_count || 0);
        set('pendingRequisitions', d.pending_requisitions || d.pending || 0);
        const stockEl = document.getElementById('stockValue');
        if (stockEl) stockEl.textContent = 'KES ' + (d.stock_value || 0).toLocaleString();
    },

    renderLowStock: function (list) {
        const tbody = document.getElementById('lowStockTableBody');
        if (!tbody) return;
        if (!list.length) { tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No low stock items.</td></tr>'; return; }
        tbody.innerHTML = list.map(item => {
            const level = Number(item.current_quantity || item.quantity || 0);
            const min = Number(item.minimum_quantity || item.min_level || 0);
            const pct = min > 0 ? Math.round((level / min) * 100) : 100;
            const cls = pct < 25 ? 'danger' : pct < 50 ? 'warning' : 'success';
            return `<tr>
                <td>${this.esc(item.name || item.item_name)}</td>
                <td><span class="text-${cls} fw-bold">${level}</span></td>
                <td>${min}</td>
                <td>
                    <button class="btn btn-xs btn-outline-primary btn-sm py-0 px-1"
                        onclick="storeDashboardController.navigate('manage_stock')">
                        Reorder
                    </button>
                </td>
            </tr>`;
        }).join('');
    },

    renderRequisitions: function (list) {
        const tbody = document.getElementById('requisitionsTableBody');
        if (!tbody) return;
        if (!list.length) { tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No pending requisitions.</td></tr>'; return; }
        tbody.innerHTML = list.map(r => {
            const s = r.status || 'pending';
            return `<tr>
                <td>${this.esc(r.item_name || r.name)}</td>
                <td>${r.quantity || 0}</td>
                <td>${this.esc(r.requested_by || r.requester || '—')}</td>
                <td><span class="badge bg-${s === 'approved' ? 'success' : s === 'rejected' ? 'danger' : 'warning'} text-${s === 'pending' ? 'dark' : 'white'}">${s}</span></td>
            </tr>`;
        }).join('');
    },

    navigate: function (route) {
        window.location.href = (window.APP_BASE || '') + '/home.php?route=' + route;
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
};

document.addEventListener('DOMContentLoaded', () => storeDashboardController.init());
