/** Date-scoped menu view backed by CateringController::getMenu(). */
const todaysMenuController = {
  state: { meals: [], request: 0 },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]); },
  iso(date) { return date.toISOString().slice(0, 10); },
  async load() {
    const request = ++this.state.request;
    const host = document.getElementById('tmMeals'); if (!host) return;
    host.innerHTML = '<div class="text-center py-4" role="status"><span class="spinner-border spinner-border-sm me-2"></span>Loading menu…</div>';
    const date = document.getElementById('tmPicker')?.value || this.iso(new Date());
    const label = document.getElementById('tmDate'); if (label) label.textContent = `Meals planned for ${date}.`;
    try {
      const response = await window.API.catering.getMenu({ date });
      if (request !== this.state.request) return;
      const payload = response?.data?.data ?? response?.data ?? response ?? [];
      this.state.meals = Array.isArray(payload) ? payload : [];
      this.render();
    } catch (error) {
      if (request === this.state.request) {
        host.innerHTML = `<div class="alert alert-danger" role="alert">${this.esc(error?.message || 'Menu could not be loaded.')}</div>`;
        document.getElementById('tmStats').replaceChildren();
      }
    }
  },
  render() {
    const host = document.getElementById('tmMeals'); if (!host) return;
    const stats = document.getElementById('tmStats');
    const meals = this.state.meals;
    const planned = meals.reduce((sum, meal) => sum + Number(meal.planned_servings || 0), 0);
    if (stats) stats.innerHTML = `<div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted small">Planned meal items</div><strong class="fs-4">${meals.length}</strong></div></div></div><div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-muted small">Planned servings</div><strong class="fs-4">${planned.toLocaleString()}</strong></div></div></div>`;
    host.innerHTML = meals.length ? `<div class="row g-3">${meals.map((meal) => `<div class="col-md-6 col-xl-3"><article class="card h-100"><div class="card-body"><span class="badge text-bg-success mb-2">${this.esc(meal.meal_type || 'Meal')}</span><h5>${this.esc(meal.menu_item || 'Menu item')}</h5>${meal.description ? `<p class="text-muted">${this.esc(meal.description)}</p>` : ''}<div>Planned servings: ${Number(meal.planned_servings || 0).toLocaleString()}</div><div>Prepared: ${Number(meal.prepared_quantity || 0).toLocaleString()}</div><div>Served: ${Number(meal.actual_servings || 0).toLocaleString()}</div><span class="badge text-bg-secondary mt-2">${this.esc(meal.status || 'planned')}</span></div></article></div>`).join('')}</div>` : '<div class="alert alert-info">No meals are planned for this date.</div>';
  },
  init() {
    const picker = document.getElementById('tmPicker'); picker.value = this.iso(new Date());
    picker.addEventListener('change', () => this.load());
    document.getElementById('tmPrintBtn')?.addEventListener('click', () => { if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('catering')) window.print(); });
    this.load();
    window.APIRealtime?.register?.('todays_menu', { refresh: () => this.load() }, ['catering']);
  },
};
window.todaysMenuController = todaysMenuController;
document.addEventListener('DOMContentLoaded', () => todaysMenuController.init());
