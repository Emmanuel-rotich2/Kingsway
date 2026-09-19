(() => {
  'use strict';
  const state = { data: null, charts: {}, loading: false };
  const $ = (id) => document.getElementById(id);
  const unwrap = (response) => { let value = response; for (let i = 0; i < 4 && value && typeof value === 'object' && !Array.isArray(value) && 'data' in value; i += 1) value = value.data; return value || {}; };
  const number = (value) => Number(value || 0);
  const text = (value) => String(value ?? '—').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
  const money = (value) => new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES', maximumFractionDigits: 0 }).format(number(value));
  const date = (value) => value ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value)) : '—';
  const badge = (value) => `<span class="inventory-status inventory-status--${text(String(value || '').toLowerCase().replace(/\s+/g, '-'))}">${text(value || 'Unknown')}</span>`;

  function setLoading(active) {
    state.loading = active;
    const button = $('storeDashboardRefresh');
    if (!button) return;
    button.disabled = active;
    button.innerHTML = active ? '<span class="spinner-border spinner-border-sm"></span> Loading' : '<i class="bi bi-arrow-clockwise"></i> Refresh data';
  }
  function drawChart(id, config) {
    state.charts[id]?.destroy();
    const canvas = $(id);
    if (canvas && typeof Chart !== 'undefined') state.charts[id] = new Chart(canvas, config);
  }
  function populateCategories(rows) {
    const select = $('storeCategoryFilter');
    const selected = select.value;
    select.innerHTML = '<option value="">All categories</option>' + rows.map((row) => `<option value="${text(row.category)}">${text(row.category)}</option>`).join('');
    if ([...select.options].some((option) => option.value === selected)) select.value = selected;
  }
  function render() {
    const source = state.data || {}, summary = source.summary || {};
    const categories = Array.isArray(source.by_category) ? source.by_category : [];
    const health = Array.isArray(source.stock_health) ? source.stock_health : [];
    const categoryFilter = $('storeCategoryFilter').value, statusFilter = $('storeStatusFilter').value, priorityFilter = $('storePriorityFilter').value;
    const selectedCategories = categoryFilter ? categories.filter((row) => row.category === categoryFilter) : categories;
    const categoryHealth = categoryFilter ? health.filter((row) => row.category === categoryFilter) : health;
    const healthTotals = Object.values(categoryHealth.reduce((totals, row) => {
      const status = row.stock_status || 'Unknown';
      totals[status] ||= { stock_status: status, item_count: 0 };
      totals[status].item_count += number(row.item_count);
      return totals;
    }, {}));
    const selectedHealth = statusFilter ? healthTotals.filter((row) => row.stock_status === statusFilter) : healthTotals;
    const active = categoryFilter ? selectedCategories.reduce((sum, row) => sum + number(row.item_count), 0) : number(summary.active_items);
    const value = categoryFilter ? selectedCategories.reduce((sum, row) => sum + number(row.inventory_value), 0) : number(summary.inventory_value);
    const healthCount = (statuses) => healthTotals.filter((row) => statuses.includes(row.stock_status)).reduce((sum, row) => sum + number(row.item_count), 0);
    const low = healthCount(['REORDER', 'LOW STOCK']), out = healthCount(['OUT OF STOCK']);
    const healthy = healthCount(['ADEQUATE']);
    const rate = active ? Math.round((healthy / active) * 100) : 0;
    $('storeItems').textContent = active.toLocaleString(); $('storeValue').textContent = money(value);
    $('storeLowStock').textContent = low.toLocaleString(); $('storeOutStock').textContent = out.toLocaleString(); $('storeHealthy').textContent = healthy.toLocaleString();
    $('storeHealthRate').textContent = `${rate}%`; $('storeHealthGauge').style.setProperty('--health', rate);

    drawChart('storeCategoryChart', { type: 'doughnut', data: { labels: selectedCategories.map((row) => row.category || 'Uncategorised'), datasets: [{ data: selectedCategories.map((row) => number(row.item_count)), backgroundColor: ['#07566d', '#0f8497', '#38a7a0', '#e7a832', '#dc6b45', '#8c6bb1'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } } } } });
    const healthColours = { 'OUT OF STOCK': '#b42318', REORDER: '#e7a832', 'LOW STOCK': '#dc6b45', ADEQUATE: '#16865b' };
    drawChart('storeHealthChart', { type: 'bar', data: { labels: selectedHealth.map((row) => String(row.stock_status || 'Unknown').replace(/_/g, ' ')), datasets: [{ label: 'Items', data: selectedHealth.map((row) => number(row.item_count)), backgroundColor: selectedHealth.map((row) => healthColours[row.stock_status] || '#718b92'), borderRadius: 5, barThickness: 24 }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid: { color: '#e8eef0' } }, y: { grid: { display: false } } } } });

    const lowRows = (source.low_stock_items || []).filter((row) => (!categoryFilter || row.category === categoryFilter) && (!statusFilter || row.stock_status === statusFilter));
    $('storeLowBody').innerHTML = lowRows.length ? lowRows.slice(0, 8).map((row) => `<tr><td><strong>${text(row.name)}</strong><small>${text(row.code)}</small></td><td>${text(row.category || 'Uncategorised')}</td><td>${number(row.current_quantity).toLocaleString()}</td><td>${number(row.minimum_quantity).toLocaleString()}</td><td>${badge(row.stock_status)}</td></tr>`).join('') : '<tr><td colspan="5" class="text-center py-4 text-muted">No replenishment items match these filters.</td></tr>';
    const reqRows = (source.pending_requisitions || []).filter((row) => !priorityFilter || String(row.priority).toLowerCase() === priorityFilter);
    $('storeReqsBody').innerHTML = reqRows.length ? reqRows.slice(0, 7).map((row) => `<tr><td><strong>${text(row.requisition_number)}</strong></td><td>${text(row.department)}</td><td>${number(row.item_count).toLocaleString()}</td><td>${date(row.required_date)}</td><td>${badge(row.priority)}</td></tr>`).join('') : '<tr><td colspan="5" class="text-center py-4 text-muted">No pending requisitions match these filters.</td></tr>';
  }
  async function load() {
    if (state.loading) return;
    setLoading(true);
    try {
      const [inventoryResponse, dashboardResponse, lowStockResponse] = await Promise.all([
        window.API.inventory.getDashboard(),
        window.API.inventory.getUniformDashboard(),
        window.API.inventory.getLowStockUniforms(),
      ]);
      const uniform = { ...unwrap(inventoryResponse), ...unwrap(dashboardResponse) };
      const stock = uniform.inventory_status || {};
      const monthly = uniform.monthly_metrics || {};
      const topItems = Array.isArray(uniform.top_selling_items) ? uniform.top_selling_items : [];
      const lowStockPayload = unwrap(lowStockResponse);
      const lowStockRows = Array.isArray(lowStockPayload)
        ? lowStockPayload
        : (lowStockPayload.items || lowStockPayload.uniforms || []);
      const lowStock = lowStockRows.map((item) => ({
        ...item,
        name: `${item.item_name || item.name || 'Uniform'}${item.size ? ` · ${item.size}` : ''}`,
        code: item.item_code || item.code,
        category: 'Uniforms',
        current_quantity: item.quantity_available,
        minimum_quantity: item.reorder_level || 20,
        stock_status: item.stock_status === 'out_of_stock' ? 'OUT OF STOCK' : 'LOW STOCK',
      }));
      state.data = {
        summary: {
          active_items: number(stock.total_items),
          inventory_value: number(monthly.total_revenue),
        },
        by_category: [{
          category: 'Uniforms',
          item_count: number(stock.total_items),
          inventory_value: number(monthly.total_revenue),
        }],
        stock_health: [
          { category: 'Uniforms', stock_status: 'ADEQUATE', item_count: number(stock.in_stock) },
          { category: 'Uniforms', stock_status: 'LOW STOCK', item_count: number(stock.low_stock) },
          { category: 'Uniforms', stock_status: 'OUT OF STOCK', item_count: number(stock.out_of_stock) },
        ],
        low_stock_items: lowStock,
        pending_requisitions: topItems.map((item, index) => ({
          requisition_number: item.name || `Uniform item ${index + 1}`,
          department: 'Uniform Store',
          item_count: number(item.total_quantity),
          required_date: new Date().toISOString(),
          priority: 'normal',
        })),
      };
      populateCategories(Array.isArray(state.data.by_category) ? state.data.by_category : []);
      render();
      const updated = $('storeDashboardLastUpdated');
      if (updated) updated.textContent = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' }).format(new Date());
    } catch (error) {
      console.error('[InventoryDashboard]', error);
      window.showNotification?.(error.message || 'Inventory dashboard could not be loaded.', 'error');
    } finally { setLoading(false); }
  }
  function init() {
    if (!$('storeDashboard')) return;
    $('storeDashboardRefresh').addEventListener('click', load);
    ['storeCategoryFilter', 'storeStatusFilter', 'storePriorityFilter'].forEach((id) => $(id).addEventListener('change', render));
    $('storeDashboard').addEventListener('click', (event) => { const link = event.target.closest('[data-route]'); if (!link) return; event.preventDefault(); window.AppRouter?.go?.(link.dataset.route); });
    load();
  }
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init, { once: true }) : init();
  window.StoreManagerDashboardController = { load, render };
})();
