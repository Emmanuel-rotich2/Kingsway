/** Read-only weekly menu view backed by CateringController::getMenu(). */
const weeklyMenuController = {
  state: { menus: [], request: 0 },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]); },
  iso(date) { return date.toISOString().slice(0, 10); },
  weekRange(value) {
    const date = new Date(`${value || this.iso(new Date())}T12:00:00`);
    const monday = new Date(date); monday.setDate(date.getDate() - ((date.getDay() + 6) % 7));
    const friday = new Date(monday); friday.setDate(monday.getDate() + 4);
    return { from: this.iso(monday), to: this.iso(friday) };
  },
  async load() {
    const request = ++this.state.request;
    const host = document.getElementById('wmDays'); if (!host) return;
    host.innerHTML = '<div class="text-center py-4" role="status"><span class="spinner-border spinner-border-sm me-2"></span>Loading weekly menu…</div>';
    const range = this.weekRange(document.getElementById('wmPicker')?.value);
    const title = document.querySelector('.container-fluid h4 + p');
    if (title) title.textContent = `Meals planned Monday–Friday · ${range.from} to ${range.to}`;
    try {
      const response = await window.API.catering.getMenu({ date_from: range.from, date_to: range.to });
      if (request !== this.state.request) return;
      const payload = response?.data?.data ?? response?.data ?? response ?? [];
      this.state.menus = Array.isArray(payload) ? payload : [];
      this.render();
    } catch (error) {
      if (request !== this.state.request) return;
      host.innerHTML = `<div class="alert alert-danger" role="alert">${this.esc(error?.message || 'Weekly menu could not be loaded.')}</div>`;
    }
  },
  render() {
    const host = document.getElementById('wmDays'); if (!host) return;
    const range = this.weekRange(document.getElementById('wmPicker')?.value);
    const grouped = new Map();
    for (let day = new Date(`${range.from}T12:00:00`); this.iso(day) <= range.to; day.setDate(day.getDate() + 1)) grouped.set(this.iso(day), []);
    this.state.menus.forEach((meal) => { if (grouped.has(String(meal.plan_date).slice(0, 10))) grouped.get(String(meal.plan_date).slice(0, 10)).push(meal); });
    host.innerHTML = [...grouped.entries()].map(([date, meals]) => `<section class="card mb-3"><div class="card-header fw-semibold">${this.esc(new Date(`${date}T12:00:00`).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }))}</div><div class="card-body">${meals.length ? `<div class="row g-3">${meals.map((meal) => `<div class="col-md-6 col-xl-3"><div class="border rounded p-3 h-100"><span class="badge text-bg-success mb-2">${this.esc(meal.meal_type || 'Meal')}</span><h6>${this.esc(meal.menu_item || 'Menu item')}</h6>${meal.description ? `<p class="small text-muted">${this.esc(meal.description)}</p>` : ''}<div class="small">Planned servings: ${Number(meal.planned_servings || 0).toLocaleString()}</div><div class="small text-muted">Status: ${this.esc(meal.status || 'planned')}</div></div></div>`).join('')}</div>` : '<p class="text-muted mb-0">No meals are planned for this day.</p>'}</div></section>`).join('');
  },
  init() {
    const picker = document.getElementById('wmPicker');
    const today = new Date(); picker.value = this.iso(today);
    picker.addEventListener('change', () => this.load());
    document.getElementById('wmPrintBtn')?.addEventListener('click', () => { if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('catering')) window.print(); });
    this.load();
    window.APIRealtime?.register?.('weekly_menu', { refresh: () => this.load() }, ['catering']);
  },
};
window.weeklyMenuController = weeklyMenuController;
document.addEventListener('DOMContentLoaded', () => weeklyMenuController.init());
