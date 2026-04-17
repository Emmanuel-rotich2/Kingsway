/**
 * Catering Manager / Cook Lead Dashboard Controller
 * Role: Cateress (ID 16)
 */
const cateringDashboardController = {
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

        const today = new Date().toISOString().slice(0, 10);
        const [stats, menu, stock] = await Promise.allSettled([
            get('/api/catering/stats'),
            get('/api/catering/menu?date=' + today),
            get('/api/catering/food-stock?low_stock=1&limit=8')
        ]);

        if (stats.value) this.renderStats(stats.value?.data || stats.value);
        if (menu.value) this.renderMenu(menu.value?.data || menu.value || []);
        if (stock.value) this.renderStock(stock.value?.data || stock.value || []);
    },

    renderStats: function (d) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v ?? 0; };
        set('mealsToday', d.meals_today || d.meals || 0);
        set('foodItems', d.food_items || d.total_items || 0);
        set('lowFoodStock', d.low_stock || d.low_stock_count || 0);
        const costEl = document.getElementById('dailyCost');
        if (costEl) costEl.textContent = 'KES ' + (d.daily_cost || 0).toLocaleString();
    },

    renderMenu: function (list) {
        const el = document.getElementById('todaysMenuList');
        if (!el) return;
        if (!list.length) { el.innerHTML = '<div class="text-center text-muted py-3 small">No menu planned for today.</div>'; return; }
        const mealOrder = ['breakfast', 'lunch', 'supper', 'snack'];
        const byMeal = {};
        list.forEach(m => { const meal = (m.meal_type || m.type || 'other').toLowerCase(); (byMeal[meal] = byMeal[meal] || []).push(m); });
        el.innerHTML = Object.entries(byMeal).sort((a, b) => (mealOrder.indexOf(a[0]) + 1 || 99) - (mealOrder.indexOf(b[0]) + 1 || 99)).map(([meal, items]) => `
            <div class="list-group-item py-2">
                <div class="text-muted small text-uppercase fw-bold mb-1">${meal}</div>
                ${items.map(i => '<div class="small">' + this.esc(i.name || i.dish || i.item_name) + '</div>').join('')}
            </div>`).join('');
    },

    renderStock: function (list) {
        const tbody = document.getElementById('foodStockTableBody');
        if (!tbody) return;
        if (!list.length) { tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Stock levels OK.</td></tr>'; return; }
        tbody.innerHTML = list.map(item => {
            const qty = Number(item.current_quantity || item.quantity || 0);
            const min = Number(item.minimum_quantity || item.min_level || 1);
            const cls = qty <= 0 ? 'danger' : qty < min ? 'warning' : 'success';
            return `<tr>
                <td>${this.esc(item.name || item.item_name)}</td>
                <td><span class="text-${cls} fw-bold">${qty}</span></td>
                <td>${this.esc(item.unit || 'units')}</td>
                <td><span class="badge bg-${cls}">${qty <= 0 ? 'Out of Stock' : qty < min ? 'Low' : 'OK'}</span></td>
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

document.addEventListener('DOMContentLoaded', () => cateringDashboardController.init());
