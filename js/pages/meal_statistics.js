/** Catering meal allocation report. */
const mealStatisticsController = {
  rows: [],

  esc(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  },

  localDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  },

  async load() {
    const body = document.getElementById('msBody');
    if (!body) return;
    const from = document.getElementById('msFrom')?.value;
    const to = document.getElementById('msTo')?.value;
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading meal allocations…</td></tr>';

    try {
      const response = await window.API.reports.getMealAllocations({ date_from: from, date_to: to });
      const payload = response?.data ?? response;
      this.rows = Array.isArray(payload) ? payload : [];
      this.render();
    } catch (error) {
      body.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${this.esc(error?.message || 'Could not load meal allocations.')}</td></tr>`;
      const stats = document.getElementById('msStats');
      if (stats) stats.replaceChildren();
    }
  },

  render() {
    const body = document.getElementById('msBody');
    if (!body) return;
    if (!this.rows.length) {
      body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No meal allocations for this date range.</td></tr>';
    } else {
      body.innerHTML = this.rows.map((row) => {
        const planned = Number(row.planned_items || 0);
        const allocated = Number(row.allocated_count || 0);
        const served = Number(row.served_count || 0);
        const rate = allocated > 0 ? Math.round((served / allocated) * 100) : 0;
        return `<tr><td>${this.esc(row.meal_date || '—')}</td><td>${this.esc(row.meal_type || '—')}</td><td>${planned}</td><td>${allocated.toLocaleString()}</td><td>${served.toLocaleString()}</td><td>${rate}%</td></tr>`;
      }).join('');
    }

    const stats = document.getElementById('msStats');
    if (!stats) return;
    const allocated = this.rows.reduce((sum, row) => sum + Number(row.allocated_count || 0), 0);
    const served = this.rows.reduce((sum, row) => sum + Number(row.served_count || 0), 0);
    const meals = this.rows.reduce((sum, row) => sum + Number(row.planned_items || 0), 0);
    const rate = allocated > 0 ? Math.round((served / allocated) * 100) : 0;
    stats.innerHTML = [
      ['Meal plans', meals], ['Allocated servings', allocated.toLocaleString()],
      ['Served servings', served.toLocaleString()], ['Serve rate', `${rate}%`],
    ].map(([label, value]) => `<div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">${label}</div><div class="fs-4 fw-semibold">${value}</div></div></div></div>`).join('');
  },

  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('catering')) return window.showNotification?.('You do not have permission to export catering data.', 'warning');
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [['Date', 'Meal Type', 'Planned Items', 'Allocated', 'Served', 'Serve Rate'], ...this.rows.map((row) => {
      const allocated = Number(row.allocated_count || 0);
      const served = Number(row.served_count || 0);
      return [row.meal_date, row.meal_type, row.planned_items, allocated, served, `${allocated ? Math.round(served / allocated * 100) : 0}%`];
    })].map((row) => row.map(cell).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'meal_allocations.csv', 'text/csv;charset=utf-8;');
  },

  print() {
    if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('catering')) window.print();
  },

  async init() {
    const from = document.getElementById('msFrom');
    const to = document.getElementById('msTo');
    const today = new Date();
    if (from && !from.value) {
      const start = new Date(today);
      start.setDate(start.getDate() - 29);
      from.value = this.localDate(start);
    }
    if (to && !to.value) to.value = this.localDate(today);
    from?.addEventListener('change', () => this.load());
    to?.addEventListener('change', () => this.load());
    document.getElementById('msExportBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('msPrintBtn')?.addEventListener('click', () => this.print());
    await this.load();
  },
};

window.mealStatisticsController = mealStatisticsController;
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => mealStatisticsController.init().catch(console.error));
} else {
  mealStatisticsController.init().catch(console.error);
}
